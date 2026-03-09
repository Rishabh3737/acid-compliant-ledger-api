# Simple ACID-Compliant Ledger API

A lightweight, stateless PHP microservice for securely managing virtual wallets, loyalty points, or financial transactions. 

Whether you are building a custom client management platform, tracking member credits for a SaaS application, or upgrading a simple online banking project, handling digital currency requires strict data integrity. This API uses native PDO and strict MySQL/MariaDB `InnoDB` transactions to guarantee ACID compliance—meaning if a server crashes halfway through a transfer, no funds are lost or duplicated.

## 🚀 Features

* **Strict ACID Transactions:** Uses `START TRANSACTION`, row-level locking (`FOR UPDATE`), and `ROLLBACK` to ensure zero race conditions or double-spending.
* **Stateless Architecture:** Easy to drop into existing PHP projects or run as a standalone microservice.
* **Full History Ledger:** Maintains an immutable log of all transfers, deposits, and withdrawals.
* **Zero Dependencies:** Pure PHP 8.1+ and PDO. No bloated frameworks required.

## 🛠 Prerequisites

* PHP 8.1 or higher
* MySQL 5.7+ or MariaDB (InnoDB engine required for row-level locking)
* A web server (Apache/Nginx) or PHP's built-in development server.

## 📦 Installation & Setup

1. **Clone the repository:**
~~~bash
git clone https://github.com/Rishabh3737/acid-compliant-ledger-api.git
cd acid-compliant-ledger-api
~~~

2. **Database Setup:**
Create a new database and import the `schema.sql` file to generate the `accounts` and `transactions` tables. You can run the following via your terminal or database client:
~~~bash
mysql -u root -p my_database < schema.sql
~~~

*Alternatively, manually execute the following `schema.sql` contents:*
~~~sql
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_account_id INT NULL,
    receiver_account_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('transfer', 'deposit', 'withdrawal') NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
~~~

3. **Configure Connection:**
Open `index.php` and update the PDO connection variables at the bottom of the file with your local database credentials.

4. **Run the server:**
~~~bash
php -S localhost:8000
~~~

## 📡 API Endpoints

### 1. Check Account Balance
Retrieves the current balance for a specific account.

**Request:**
`GET /api/v1/accounts/{id}/balance`

**Response:**
~~~json
{
  "account_id": 1042,
  "balance": "150.00"
}
~~~

### 2. Transfer Funds
Moves funds safely between two accounts. If the sender has insufficient funds, or if the database connection drops mid-query, the entire transaction is rolled back safely.

**Request:**
`POST /api/v1/transactions/transfer`
~~~json
{
  "sender_id": 1042,
  "receiver_id": 883,
  "amount": 50.00
}
~~~

**Response (Success):**
~~~json
{
  "status": "success",
  "message": "Transferred $50.00 successfully."
}
~~~

### 3. Get Transaction History
Retrieves the most recent ledger activity for an account.

**Request:**
`GET /api/v1/accounts/{id}/history`

**Response:**
~~~json
[
  {
    "id": 549,
    "sender_account_id": 1042,
    "receiver_account_id": 883,
    "amount": "50.00",
    "type": "transfer",
    "timestamp": "2026-03-09 14:30:00"
  }
]
~~~

## 🛡️ Security Notes
This repo is designed as a foundational microservice. In a production environment, you should wrap the `LedgerAPI` endpoints in an authentication middleware (such as JWT) to ensure users can only trigger transfers from their own authenticated `sender_id`.

## 📄 License
This project is open-source and available under the [MIT License](LICENSE).
