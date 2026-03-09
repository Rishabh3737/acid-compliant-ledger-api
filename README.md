# Advanced ACID-Compliant Ledger API

A lightweight, stateless PHP microservice for securely managing virtual wallets, loyalty points, or financial transactions. 

Whether you are building a custom client management platform, tracking member credits for a SaaS application, or upgrading a simple online banking project, handling digital currency requires strict data integrity. This API uses native PDO and strict MySQL/MariaDB `InnoDB` transactions to guarantee ACID compliance—meaning if a server crashes halfway through a transfer, no funds are lost or duplicated.



## 🚀 Features

* **Strict ACID Transactions:** Uses `START TRANSACTION`, row-level locking (`FOR UPDATE`), and `ROLLBACK` to ensure zero race conditions or double-spending.
* **Deposits & Withdrawals:** Safely inject or remove funds from the ecosystem.
* **Account Status Controls:** Native support for locking/freezing accounts to prevent unauthorized transfers.
* **Paginated History Ledger:** Maintains an immutable log of all transfers, deposits, and withdrawals, optimized for massive datasets.
* **Zero Dependencies:** Pure PHP 8.1+ and PDO. No bloated frameworks required.

## 🛠 Prerequisites

* PHP 8.1 or higher
* MySQL 5.7+ or MariaDB (InnoDB engine required for row-level locking)
* A web server (Apache/Nginx) or PHP's built-in development server.

## 📦 Installation & Setup

1. **Clone the repository:**
   ```bash
   git clone https://github.com/Rishabh3737/acid-compliant-ledger-api.git
   cd acid-compliant-ledger-api
   ```

2. **Database Setup:**
   Create a new database and import the `schema.sql` file to generate the `accounts` and `transactions` tables. You can run the following via your terminal or database client:
   ```bash
   mysql -u root -p my_database < schema.sql
   ```

3. **Configure Connection:**
   Open `index.php` and update the PDO connection variables at the bottom of the file with your local database credentials.

4. **Run the server:**
   ```bash
   php -S localhost:8000
   ```

## 📡 API Endpoints

### 1. Check Account Balance
Retrieves the current balance and account status (active, frozen, or closed).

**Request:**
`GET /api/v1/accounts/{id}/balance`

**Response:**
```json
{
  "account_id": 1042,
  "balance": "150.00",
  "status": "active"
}
```

### 2. Deposit Funds
Adds money to a specific account from an external source.

**Request:**
`POST /api/v1/accounts/{id}/deposit`
```json
{
  "amount": 100.00
}
```

### 3. Withdraw Funds
Removes money from a specific account. Fails safely if the account has insufficient funds or is frozen.

**Request:**
`POST /api/v1/accounts/{id}/withdraw`
```json
{
  "amount": 25.50
}
```

### 4. Transfer Funds
Moves funds safely between two accounts. Uses row-level locking on both accounts to prevent race conditions.

**Request:**
`POST /api/v1/transactions/transfer`
```json
{
  "sender_id": 1042,
  "receiver_id": 883,
  "amount": 50.00
}
```

**Response (Success):**
```json
{
  "status": "success",
  "message": "Transferred $50.00 successfully."
}
```

### 5. Get Transaction History (Paginated)
Retrieves the ledger activity for an account. Supports dynamic pagination.

**Request:**
`GET /api/v1/accounts/{id}/history?page=1&limit=20`

**Response:**
```json
{
  "account_id": 1042,
  "page": 1,
  "limit": 20,
  "data": [
    {
      "id": 549,
      "sender_account_id": 1042,
      "receiver_account_id": 883,
      "amount": "50.00",
      "type": "transfer",
      "timestamp": "2026-03-09 14:30:00"
    }
  ]
}
```

## 🛡️ Security Notes
This repo is designed as a foundational microservice. In a production environment, you should wrap the `LedgerAPI` endpoints in an authentication middleware (such as JWT) to ensure users can only trigger transfers or withdrawals from their own authenticated `sender_id`.

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).
