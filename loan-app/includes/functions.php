<?php
/**
 * Business logic & helpers for Micro-Lending
 */
require_once __DIR__ . '/../config/database.php';

/**
 * Get a single setting value
 */
function getSetting(string $key, $default = null)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

/**
 * Get current pool balance
 */
function getPoolBalance(): float
{
    $pdo = getPDO();
    $stmt = $pdo->query('SELECT balance FROM pool WHERE id = 1');
    $row = $stmt->fetch();
    return $row ? (float)$row['balance'] : 0.0;
}

/**
 * Get wallet balance for a user
 */
function getWalletBalance(int $userId): float
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ?');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? (float)$row['balance'] : 0.0;
}

/**
 * Ensure a wallet row exists for the user
 */
function ensureWallet(int $userId): void
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('INSERT IGNORE INTO wallets (user_id, balance) VALUES (?, 0.00)');
    $stmt->execute([$userId]);
}

/**
 * Format money for display
 */
function money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

/**
 * Flash message helpers
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Register a new borrower
 */
function registerBorrower(string $username, string $email, string $password, string $fullName): array
{
    $pdo = getPDO();

    // Basic validation
    if (strlen($username) < 3) return ['ok' => false, 'error' => 'Username too short'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Invalid email'];
    if (strlen($password) < 6) return ['ok' => false, 'error' => 'Password must be at least 6 characters'];

    try {
        $pdo->beginTransaction();

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$username, $email, $hash, $fullName, 'borrower']);
        $userId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare('INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)');
        $stmt->execute([$userId]);

        $pdo->commit();
        return ['ok' => true, 'user_id' => $userId];
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() == 23000) {
            return ['ok' => false, 'error' => 'Username or email already exists'];
        }
        return ['ok' => false, 'error' => 'Registration failed'];
    }
}

/**
 * Create a loan request
 */
function createLoanRequest(int $userId, float $amount, int $termMonths, ?string $purpose = null): array
{
    $pdo = getPDO();

    $minAmount = (float)getSetting('min_loan_amount', 1000);
    $maxAmount = (float)getSetting('max_loan_amount', 50000);
    $minTerm   = (int)getSetting('min_term_months', 1);
    $maxTerm   = (int)getSetting('max_term_months', 12);
    $allowMulti = getSetting('allow_multiple_active_loans', '0') === '1';

    if ($amount < $minAmount || $amount > $maxAmount) {
        return ['ok' => false, 'error' => "Amount must be between " . money($minAmount) . " and " . money($maxAmount)];
    }
    if ($termMonths < $minTerm || $termMonths > $maxTerm) {
        return ['ok' => false, 'error' => "Term must be between $minTerm and $maxTerm months"];
    }

    // Check existing active/pending loans
    if (!$allowMulti) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM loan_requests 
            WHERE user_id = ? AND status = 'pending'
        ");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'You already have a pending loan request'];
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM loans 
            WHERE user_id = ? AND status IN ('active','overdue')
        ");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'You already have an active loan. Pay it off first.'];
        }
    }

    $stmt = $pdo->prepare('
        INSERT INTO loan_requests (user_id, amount, term_months, purpose, status)
        VALUES (?, ?, ?, ?, \'pending\')
    ');
    $stmt->execute([$userId, $amount, $termMonths, $purpose]);
    return ['ok' => true, 'request_id' => (int)$pdo->lastInsertId()];
}

/**
 * Approve a loan request → disburse from pool + create schedule
 * Uses a transaction + row lock to prevent race conditions
 */
function approveLoanRequest(int $requestId, int $adminId): array
{
    $pdo = getPDO();

    try {
        $pdo->beginTransaction();

        // Lock the request row
        $stmt = $pdo->prepare('SELECT * FROM loan_requests WHERE id = ? AND status = \'pending\' FOR UPDATE');
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        if (!$request) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Request not found or already processed'];
        }

        $amount = (float)$request['amount'];
        $term   = (int)$request['term_months'];
        $userId = (int)$request['user_id'];

        // Lock pool and check balance
        $stmt = $pdo->query('SELECT balance FROM pool WHERE id = 1 FOR UPDATE');
        $pool = $stmt->fetch();
        $poolBalance = (float)$pool['balance'];

        if ($poolBalance < $amount) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Insufficient pool balance. Available: ' . money($poolBalance)];
        }

        // BRD §10: Interest = Principal × Interest Rate (flat, whole term)
        // Total Repayment = Principal + Interest
        // Installment Amount = Total Repayment ÷ Number of Installments
        $interestRate = (float)getSetting('interest_rate', 5.00);
        $totalInterest  = round($amount * ($interestRate / 100), 2);
        $totalRepayable = $amount + $totalInterest;
        $installmentAmt = round($totalRepayable / $term, 2);

        // Adjust last installment for rounding
        $totalCheck = $installmentAmt * $term;
        $lastAdjustment = round($totalRepayable - $totalCheck, 2);

        // 1. Update request
        $stmt = $pdo->prepare('
            UPDATE loan_requests 
            SET status = \'approved\', reviewed_at = NOW(), reviewed_by = ?
            WHERE id = ?
        ');
        $stmt->execute([$adminId, $requestId]);

        // 2. Create loan record
        $stmt = $pdo->prepare('
            INSERT INTO loans (request_id, user_id, principal, interest_rate, total_interest, total_repayable, term_months, status, disbursed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, \'active\', NOW())
        ');
        $stmt->execute([$requestId, $userId, $amount, $interestRate, $totalInterest, $totalRepayable, $term]);
        $loanId = (int)$pdo->lastInsertId();

        // 3. Create installment schedule
        $stmt = $pdo->prepare('
            INSERT INTO installments (loan_id, installment_no, amount, due_date, status)
            VALUES (?, ?, ?, ?, \'pending\')
        ');
        $baseDate = new DateTime('today');
        for ($i = 1; $i <= $term; $i++) {
            $due = clone $baseDate;
            $due->modify("+{$i} month");
            $amt = $installmentAmt;
            if ($i === $term) {
                $amt += $lastAdjustment; // fix rounding
            }
            $stmt->execute([$loanId, $i, $amt, $due->format('Y-m-d')]);
        }

        // 4. Deduct from pool
        $newPool = $poolBalance - $amount;
        $stmt = $pdo->prepare('UPDATE pool SET balance = ? WHERE id = 1');
        $stmt->execute([$newPool]);

        // 5. Credit borrower wallet
        ensureWallet($userId);
        $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();
        $newWallet = (float)$wallet['balance'] + $amount;

        $stmt = $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?');
        $stmt->execute([$newWallet, $userId]);

        // 6. Ledger entries
        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, type, amount, balance_after, description, reference_type, reference_id)
            VALUES (?, \'credit\', ?, ?, ?, \'loan_disbursement\', ?)
        ');
        $stmt->execute([$userId, $amount, $newWallet, "Loan disbursement #{$loanId}", $loanId]);

        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, type, amount, balance_after, description, reference_type, reference_id)
            VALUES (NULL, \'debit\', ?, ?, ?, \'loan_disbursement\', ?)
        ');
        $stmt->execute([$amount, $newPool, "Disbursement for loan #{$loanId}", $loanId]);

        $pdo->commit();
        return ['ok' => true, 'loan_id' => $loanId];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'Approval failed: ' . $e->getMessage()];
    }
}

/**
 * Reject a loan request
 */
function rejectLoanRequest(int $requestId, int $adminId, ?string $note = null): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('
        UPDATE loan_requests 
        SET status = \'rejected\', reviewed_at = NOW(), reviewed_by = ?, admin_note = ?
        WHERE id = ? AND status = \'pending\'
    ');
    $stmt->execute([$adminId, $note, $requestId]);
    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'Request not found or already processed'];
    }
    return ['ok' => true];
}

/**
 * Pay a single installment
 */
function payInstallment(int $installmentId, int $userId): array
{
    $pdo = getPDO();

    try {
        $pdo->beginTransaction();

        // Lock installment
        $stmt = $pdo->prepare('
            SELECT i.*, l.user_id, l.id AS loan_id, l.status AS loan_status
            FROM installments i
            JOIN loans l ON l.id = i.loan_id
            WHERE i.id = ? AND i.status = \'pending\'
            FOR UPDATE
        ');
        $stmt->execute([$installmentId]);
        $inst = $stmt->fetch();

        if (!$inst) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Installment not found or already paid'];
        }
        if ((int)$inst['user_id'] !== $userId) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'This installment does not belong to you'];
        }

        $amount = (float)$inst['amount'];

        // Lock wallet
        $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();
        $balance = (float)$wallet['balance'];

        if ($balance < $amount) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'Insufficient wallet balance. You need ' . money($amount)];
        }

        $newBalance = $balance - $amount;

        // Debit wallet
        $stmt = $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?');
        $stmt->execute([$newBalance, $userId]);

        // Mark installment paid
        $stmt = $pdo->prepare('UPDATE installments SET status = \'paid\', paid_at = NOW() WHERE id = ?');
        $stmt->execute([$installmentId]);

        // Credit pool
        $stmt = $pdo->query('SELECT balance FROM pool WHERE id = 1 FOR UPDATE');
        $pool = $stmt->fetch();
        $newPool = (float)$pool['balance'] + $amount;
        $stmt = $pdo->prepare('UPDATE pool SET balance = ? WHERE id = 1');
        $stmt->execute([$newPool]);

        // Ledger
        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, type, amount, balance_after, description, reference_type, reference_id)
            VALUES (?, \'debit\', ?, ?, ?, \'installment_payment\', ?)
        ');
        $stmt->execute([$userId, $amount, $newBalance, "Payment for installment #{$installmentId}", $installmentId]);

        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, type, amount, balance_after, description, reference_type, reference_id)
            VALUES (NULL, \'credit\', ?, ?, ?, \'installment_payment\', ?)
        ');
        $stmt->execute([$amount, $newPool, "Repayment received for installment #{$installmentId}", $installmentId]);

        // Check if loan is fully paid
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM installments WHERE loan_id = ? AND status != \'paid\'');
        $stmt->execute([$inst['loan_id']]);
        $remaining = (int)$stmt->fetchColumn();

        if ($remaining === 0) {
            $stmt = $pdo->prepare('UPDATE loans SET status = \'paid\' WHERE id = ?');
            $stmt->execute([$inst['loan_id']]);
        }

        $pdo->commit();
        return ['ok' => true, 'new_balance' => $newBalance];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => 'Payment failed: ' . $e->getMessage()];
    }
}

/**
 * Update OVERDUE / DEFAULTED statuses (BRD §11)
 * Call on dashboard load (or via scheduled job in a larger system).
 */
function updateLoanStatuses(): void
{
    $pdo = getPDO();
    $graceDays = (int)getSetting('grace_days', 7);

    // BR-010: missed due dates → installment OVERDUE
    $pdo->exec("
        UPDATE installments 
        SET status = 'overdue' 
        WHERE status = 'pending' AND due_date < CURDATE()
    ");

    // Loan becomes OVERDUE if any installment is overdue
    $pdo->exec("
        UPDATE loans l
        SET status = 'overdue'
        WHERE status = 'active'
          AND EXISTS (
              SELECT 1 FROM installments i 
              WHERE i.loan_id = l.id AND i.status = 'overdue'
          )
    ");

    // BR-011: past grace period → DEFAULTED
    $pdo->exec("
        UPDATE loans l
        SET status = 'defaulted'
        WHERE status IN ('active','overdue')
          AND EXISTS (
              SELECT 1 FROM installments i 
              WHERE i.loan_id = l.id 
                AND i.status = 'overdue'
                AND i.due_date < DATE_SUB(CURDATE(), INTERVAL {$graceDays} DAY)
          )
    ");
}

/**
 * Simple helper to get all pending requests
 */
function getPendingRequests(): array
{
    $pdo = getPDO();
    $stmt = $pdo->query("
        SELECT r.*, u.full_name, u.username
        FROM loan_requests r
        JOIN users u ON u.id = r.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Demo-only: top up a borrower's wallet (for testing repayments)
 * In a real system this would come from a payment gateway.
 */
function demoTopUpWallet(int $userId, float $amount): array
{
    if ($amount <= 0 || $amount > 100000) {
        return ['ok' => false, 'error' => 'Invalid amount'];
    }

    $pdo = getPDO();
    try {
        $pdo->beginTransaction();
        ensureWallet($userId);

        $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE user_id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $wallet = $stmt->fetch();
        $newBalance = (float)$wallet['balance'] + $amount;

        $stmt = $pdo->prepare('UPDATE wallets SET balance = ? WHERE user_id = ?');
        $stmt->execute([$newBalance, $userId]);

        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, type, amount, balance_after, description, reference_type)
            VALUES (?, \'credit\', ?, ?, \'Demo wallet top-up\', \'demo_topup\')
        ');
        $stmt->execute([$userId, $amount, $newBalance]);

        $pdo->commit();
        return ['ok' => true, 'new_balance' => $newBalance];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}