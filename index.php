<?php
/**
 * Advanced ACID-Compliant Ledger API
 */

header('Content-Type: application/json');

// ==========================================
// 1. THE CORE API LOGIC
// ==========================================
class LedgerAPI {
    private PDO $db;

    public function __construct(string $host, string $dbname, string $user, string $pass) {
        $this->db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    }

    public function getBalance(int $accountId): array {
        $stmt = $this->db->prepare("SELECT balance, status FROM accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        $account = $stmt->fetch();

        if (!$account) {
            http_response_code(404);
            return ["error" => "Account not found"];
        }

        return [
            "account_id" => $accountId, 
            "balance" => $account['balance'],
            "status" => $account['status']
        ];
    }

    public function getHistory(int $accountId, int $page = 1, int $limit = 20): array {
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->db->prepare("
            SELECT * FROM transactions 
            WHERE sender_account_id = :id OR receiver_account_id = :id 
            ORDER BY timestamp DESC LIMIT :limit OFFSET :offset
        ");
        
        $stmt->bindValue(':id', $accountId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return [
            "account_id" => $accountId,
            "page" => $page,
            "limit" => $limit,
            "data" => $stmt->fetchAll()
        ];
    }

    public function depositFunds(int $accountId, float $amount): array {
        if ($amount <= 0) {
            http_response_code(400);
            return ["error" => "Deposit amount must be greater than zero."];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT status FROM accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$accountId]);
            $account = $stmt->fetch();

            if (!$account || $account['status'] !== 'active') {
                $this->db->rollBack();
                http_response_code(403);
                return ["error" => "Account is invalid, frozen, or closed."];
            }

            $stmt = $this->db->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$amount, $accountId]);

            $stmt = $this->db->prepare("
                INSERT INTO transactions (receiver_account_id, amount, type) 
                VALUES (?, ?, 'deposit')
            ");
            $stmt->execute([$accountId, $amount]);

            $this->db->commit();
            return ["status" => "success", "message" => "Deposited $" . number_format($amount, 2)];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            return ["error" => "Deposit failed: " . $e->getMessage()];
        }
    }

    public function withdrawFunds(int $accountId, float $amount): array {
        if ($amount <= 0) {
            http_response_code(400);
            return ["error" => "Withdrawal amount must be greater than zero."];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT balance, status FROM accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$accountId]);
            $account = $stmt->fetch();

            if (!$account || $account['status'] !== 'active') {
                $this->db->rollBack();
                http_response_code(403);
                return ["error" => "Account is invalid, frozen, or closed."];
            }

            if ($account['balance'] < $amount) {
                $this->db->rollBack();
                http_response_code(400);
                return ["error" => "Insufficient funds for withdrawal."];
            }

            $stmt = $this->db->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$amount, $accountId]);

            $stmt = $this->db->prepare("
                INSERT INTO transactions (sender_account_id, amount, type) 
                VALUES (?, ?, 'withdrawal')
            ");
            $stmt->execute([$accountId, $amount]);

            $this->db->commit();
            return ["status" => "success", "message" => "Withdrew $" . number_format($amount, 2)];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            return ["error" => "Withdrawal failed: " . $e->getMessage()];
        }
    }

    public function transferFunds(int $senderId, int $receiverId, float $amount): array {
        if ($amount <= 0 || $senderId === $receiverId) {
            http_response_code(400);
            return ["error" => "Invalid transfer parameters."];
        }

        try {
            $this->db->beginTransaction();

            // Lock the sender's row
            $stmt = $this->db->prepare("SELECT balance, status FROM accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch();

            if (!$sender || $sender['status'] !== 'active' || $sender['balance'] < $amount) {
                $this->db->rollBack();
                http_response_code(400);
                return ["error" => "Insufficient funds or sender account is not active."];
            }

            // Lock the receiver's row
            $stmt = $this->db->prepare("SELECT status FROM accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$receiverId]);
            $receiver = $stmt->fetch();

            if (!$receiver || $receiver['status'] !== 'active') {
                $this->db->rollBack();
                http_response_code(400);
                return ["error" => "Receiver account is invalid or not active."];
            }

            // Execute transfers
            $stmt = $this->db->prepare("UPDATE accounts SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$amount, $senderId]);

            $stmt = $this->db->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$amount, $receiverId]);

            // Log it
            $stmt = $this->db->prepare("
                INSERT INTO transactions (sender_account_id, receiver_account_id, amount, type) 
                VALUES (?, ?, ?, 'transfer')
            ");
            $stmt->execute([$senderId, $receiverId, $amount]);

            $this->db->commit();
            return ["status" => "success", "message" => "Transferred $" . number_format($amount, 2) . " successfully."];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            http_response_code(500);
            return ["error" => "Transaction failed: " . $e->getMessage()];
        }
    }
}

// ==========================================
// 2. THE ROUTER (Endpoint Handling)
// ==========================================

// Developers pulling this repo will replace these with their own credentials
$db_host = 'localhost';
$db_name = 'ledger_db';
$db_user = 'root';
$db_pass = '';

try {
    $api = new LedgerAPI($db_host, $db_name, $db_user, $db_pass);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed."]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ROUTE: GET /api/v1/accounts/{id}/balance
if ($method === 'GET' && preg_match('#^/api/v1/accounts/(\d+)/balance$#', $uri, $matches)) {
    echo json_encode($api->getBalance((int)$matches[1]));
    exit;
}

// ROUTE: GET /api/v1/accounts/{id}/history?page=1&limit=20
if ($method === 'GET' && preg_match('#^/api/v1/accounts/(\d+)/history$#', $uri, $matches)) {
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 20;
    echo json_encode($api->getHistory((int)$matches[1], $page, $limit));
    exit;
}

// ROUTE: POST /api/v1/accounts/{id}/deposit
if ($method === 'POST' && preg_match('#^/api/v1/accounts/(\d+)/deposit$#', $uri, $matches)) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['amount'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing amount."]);
        exit;
    }
    echo json_encode($api->depositFunds((int)$matches[1], (float)$data['amount']));
    exit;
}

// ROUTE: POST /api/v1/accounts/{id}/withdraw
if ($method === 'POST' && preg_match('#^/api/v1/accounts/(\d+)/withdraw$#', $uri, $matches)) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['amount'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing amount."]);
        exit;
    }
    echo json_encode($api->withdrawFunds((int)$matches[1], (float)$data['amount']));
    exit;
}

// ROUTE: POST /api/v1/transactions/transfer
if ($method === 'POST' && $uri === '/api/v1/transactions/transfer') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['sender_id'], $data['receiver_id'], $data['amount'])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing required fields (sender_id, receiver_id, amount)."]);
        exit;
    }

    echo json_encode($api->transferFunds(
        (int)$data['sender_id'], 
        (int)$data['receiver_id'], 
        (float)$data['amount']
    ));
    exit;
}

// Catch-all for 404
http_response_code(404);
echo json_encode(["error" => "Endpoint not found."]);
