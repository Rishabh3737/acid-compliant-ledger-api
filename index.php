<?php
/**
 * Simple ACID-Compliant Ledger API
 * --------------------------------
 * * DATABASE SCHEMA: Run this in your MySQL/MariaDB client first.
 * * CREATE TABLE accounts (
 * id INT AUTO_INCREMENT PRIMARY KEY,
 * user_id INT NOT NULL,
 * balance DECIMAL(10, 2) DEFAULT 0.00,
 * created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB;
 * * CREATE TABLE transactions (
 * id INT AUTO_INCREMENT PRIMARY KEY,
 * sender_account_id INT NULL,
 * receiver_account_id INT NULL,
 * amount DECIMAL(10, 2) NOT NULL,
 * type ENUM('transfer', 'deposit', 'withdrawal') NOT NULL,
 * timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB;
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
        $stmt = $this->db->prepare("SELECT balance FROM accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        $account = $stmt->fetch();

        if (!$account) {
            http_response_code(404);
            return ["error" => "Account not found"];
        }

        return ["account_id" => $accountId, "balance" => $account['balance']];
    }

    public function getHistory(int $accountId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM transactions 
            WHERE sender_account_id = ? OR receiver_account_id = ? 
            ORDER BY timestamp DESC LIMIT 50
        ");
        $stmt->execute([$accountId, $accountId]);
        return $stmt->fetchAll();
    }

    public function transferFunds(int $senderId, int $receiverId, float $amount): array {
        if ($amount <= 0) {
            http_response_code(400);
            return ["error" => "Transfer amount must be greater than zero."];
        }

        if ($senderId === $receiverId) {
            http_response_code(400);
            return ["error" => "Cannot transfer funds to the same account."];
        }

        try {
            // Start the strict transaction
            $this->db->beginTransaction();

            // Lock the sender's row (FOR UPDATE)
            $stmt = $this->db->prepare("SELECT balance FROM accounts WHERE id = ? FOR UPDATE");
            $stmt->execute([$senderId]);
            $sender = $stmt->fetch();

            if (!$sender || $sender['balance'] < $amount) {
                $this->db->rollBack();
                http_response_code(400);
                return ["error" => "Insufficient funds or invalid sender."];
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

            // Save all changes
            $this->db->commit();

            return [
                "status" => "success",
                "message" => "Transferred $" . number_format($amount, 2) . " successfully."
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            http_response_code(500);
            return ["error" => "Transaction failed: " . $e->getMessage()];
        }
    }
}

// ==========================================
// 2. THE ROUTER (Endpoint Handling)
// ==========================================

// Update these with your local database credentials
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

// ROUTE: GET /api/v1/accounts/{id}/history
if ($method === 'GET' && preg_match('#^/api/v1/accounts/(\d+)/history$#', $uri, $matches)) {
    echo json_encode($api->getHistory((int)$matches[1]));
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
