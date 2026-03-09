-- --------------------------------------------------------
-- ACID-Compliant Ledger API - Database Schema
-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10, 2) DEFAULT 0.00,
    status ENUM('active', 'frozen', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `transactions`
--

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_account_id INT NULL,
    receiver_account_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    type ENUM('transfer', 'deposit', 'withdrawal') NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign key constraints (Optional but recommended for data integrity)
    FOREIGN KEY (sender_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (receiver_account_id) REFERENCES accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 
-- Optional: Insert some dummy data to test with
--
-- INSERT INTO accounts (user_id, balance, status) VALUES (1, 500.00, 'active');
-- INSERT INTO accounts (user_id, balance, status) VALUES (2, 100.00, 'active');
-- INSERT INTO accounts (user_id, balance, status) VALUES (3, 1000.00, 'frozen');
