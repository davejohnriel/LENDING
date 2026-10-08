-- ============================================================
-- Micro-Lending Platform (Simple Pool)
-- Aligned with BRD-MICROLEND-2026-V1.0
-- PHP + MySQL (XAMPP ready)
-- ============================================================
-- Entity mapping (BRD → this schema):
--   users          → users
--   wallets        → wallets
--   lending_pool   → pool
--   loans          → loans (+ loan_requests for PENDING stage)
--   loan_repayments→ installments
--   transactions   → transactions
--
-- Loan status model (BRD §11):
--   PENDING (request) → ACTIVE | REJECTED
--   ACTIVE → OVERDUE → DEFAULTED | PAID
--
-- Interest (BRD §10): Interest = Principal × Rate (flat)
-- ============================================================
-- How to install:
-- 1. Open phpMyAdmin → import this file
-- 2. Default admin: admin / admin123
-- ============================================================

CREATE DATABASE IF NOT EXISTS micro_lending
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE micro_lending;

-- -------------------------------------------------
-- Users
-- -------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    email         VARCHAR(100) NOT NULL UNIQUE,
    password      VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin','borrower') NOT NULL DEFAULT 'borrower',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------
-- Wallets (1:1 with users)
-- -------------------------------------------------
CREATE TABLE wallets (
    user_id     INT UNSIGNED PRIMARY KEY,
    balance     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------
-- Shared Loan Pool (always one row, id = 1)
-- -------------------------------------------------
CREATE TABLE pool (
    id          TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
    balance     DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------
-- Configurable settings
-- -------------------------------------------------
CREATE TABLE settings (
    setting_key   VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------------------------------
-- Loan Requests
-- -------------------------------------------------
CREATE TABLE loan_requests (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    amount        DECIMAL(12,2) NOT NULL,
    term_months   TINYINT UNSIGNED NOT NULL,
    purpose       VARCHAR(255) DEFAULT NULL,
    status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    admin_note    VARCHAR(255) DEFAULT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at   TIMESTAMP NULL,
    reviewed_by   INT UNSIGNED NULL,
    FOREIGN KEY (user_id)     REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- -------------------------------------------------
-- Loans (created only when request is approved)
-- -------------------------------------------------
CREATE TABLE loans (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id      INT UNSIGNED NOT NULL UNIQUE,
    user_id         INT UNSIGNED NOT NULL,
    principal       DECIMAL(12,2) NOT NULL,
    interest_rate   DECIMAL(5,2)  NOT NULL,          -- e.g. 5.00 = 5%
    total_interest  DECIMAL(12,2) NOT NULL,
    total_repayable DECIMAL(12,2) NOT NULL,
    term_months     TINYINT UNSIGNED NOT NULL,
    status          ENUM('active','paid','overdue','defaulted') NOT NULL DEFAULT 'active',
    disbursed_at    TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES loan_requests(id),
    FOREIGN KEY (user_id)    REFERENCES users(id)
) ENGINE=InnoDB;

-- -------------------------------------------------
-- Installment schedule
-- -------------------------------------------------
CREATE TABLE installments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id         INT UNSIGNED NOT NULL,
    installment_no  TINYINT UNSIGNED NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    due_date        DATE NOT NULL,
    status          ENUM('pending','paid','overdue') NOT NULL DEFAULT 'pending',
    paid_at         TIMESTAMP NULL,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    UNIQUE KEY uk_loan_installment (loan_id, installment_no)
) ENGINE=InnoDB;

-- -------------------------------------------------
-- Transaction ledger (wallet + pool movements)
-- -------------------------------------------------
CREATE TABLE transactions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NULL,               -- NULL means pool movement
    type           ENUM('credit','debit') NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    balance_after  DECIMAL(12,2) NULL,              -- filled for wallet rows
    description    VARCHAR(255) NOT NULL,
    reference_type VARCHAR(50)  NULL,
    reference_id   INT UNSIGNED NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Useful indexes
CREATE INDEX idx_requests_status   ON loan_requests(status);
CREATE INDEX idx_loans_user_status ON loans(user_id, status);
CREATE INDEX idx_installments_due  ON installments(due_date, status);
CREATE INDEX idx_tx_user           ON transactions(user_id);

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO settings (setting_key, setting_value) VALUES
('interest_rate',              '5.00'),   -- 5% simple interest for whole term
('grace_days',                 '7'),      -- days after due date → DEFAULTED (BR-011)
('max_loan_amount',            '50000.00'),
('min_loan_amount',            '1000.00'),
('max_term_months',            '12'),
('min_term_months',            '1'),
('allow_multiple_active_loans','0');      -- 0 = only one active loan at a time

-- Starting pool balance
INSERT INTO pool (id, balance) VALUES (1, 200000.00);

-- Admin user
-- Username: admin
-- Password: admin123
INSERT INTO users (username, email, password, full_name, role) VALUES
('admin', 'admin@loanapp.local',
 '$2y$10$FSog5a4M/a9z.TWlaG1qcO3QhPtWZ4X7sL6uYP.1mUn2IJj7NLXUm',
 'System Administrator', 'admin');

-- Wallet for admin (optional, balance 0)
INSERT INTO wallets (user_id, balance) VALUES (1, 0.00);