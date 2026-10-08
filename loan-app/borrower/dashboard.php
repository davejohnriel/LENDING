<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireBorrower();
updateLoanStatuses();

$userId = currentUserId();
$pdo = getPDO();

// Demo top-up
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demo_topup'])) {
    $amount = (float)($_POST['topup_amount'] ?? 0);
    $result = demoTopUpWallet($userId, $amount);
    if ($result['ok']) {
        setFlash('success', 'Demo top-up successful! New balance: ' . money($result['new_balance']));
    } else {
        setFlash('danger', $result['error']);
    }
    header('Location: /loan-app/borrower/dashboard.php');
    exit;
}

$walletBalance = getWalletBalance($userId);

// Active / recent loans
$stmt = $pdo->prepare("
    SELECT l.*,
           (SELECT COUNT(*) FROM installments i WHERE i.loan_id = l.id AND i.status = 'paid') AS paid_count,
           (SELECT COUNT(*) FROM installments i WHERE i.loan_id = l.id) AS total_count
    FROM loans l
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$loans = $stmt->fetchAll();

// Pending request?
$stmt = $pdo->prepare("SELECT * FROM loan_requests WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$pendingRequest = $stmt->fetch();

// Next unpaid installment
$stmt = $pdo->prepare("
    SELECT i.*, l.id AS loan_id
    FROM installments i
    JOIN loans l ON l.id = i.loan_id
    WHERE l.user_id = ? AND i.status IN ('pending','overdue')
    ORDER BY i.due_date ASC
    LIMIT 1
");
$stmt->execute([$userId]);
$nextInstallment = $stmt->fetch();

$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></h2>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Wallet Balance</div>
                <div class="fs-3 fw-bold text-primary"><?= money($walletBalance) ?></div>
                <div class="small text-muted mt-1">Simulated balance</div>
                <!-- Demo top-up form -->
                <form method="post" class="mt-2 d-flex gap-1">
                    <input type="number" name="topup_amount" class="form-control form-control-sm" 
                           placeholder="Amount" min="100" max="50000" step="100" required style="max-width:110px">
                    <button type="submit" name="demo_topup" class="btn btn-sm btn-outline-primary">Top-up</button>
                </form>
                <div class="form-text small">Demo only – adds fake money for testing repayments</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card <?= $pendingRequest ? 'warning' : 'success' ?>">
            <div class="card-body">
                <div class="text-muted small">Loan Request Status</div>
                <?php if ($pendingRequest): ?>
                    <div class="fs-5 fw-bold">Pending review</div>
                    <div class="small"><?= money((float)$pendingRequest['amount']) ?> · <?= $pendingRequest['term_months'] ?> months</div>
                <?php else: ?>
                    <div class="fs-5 fw-bold">No pending request</div>
                    <a href="/loan-app/borrower/request_loan.php" class="small">Request a loan →</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Next Payment Due</div>
                <?php if ($nextInstallment): ?>
                    <div class="fs-5 fw-bold"><?= money((float)$nextInstallment['amount']) ?></div>
                    <div class="small">Due <?= date('M j, Y', strtotime($nextInstallment['due_date'])) ?>
                        <?php if ($nextInstallment['status'] === 'overdue'): ?>
                            <span class="badge bg-danger">Overdue</span>
                        <?php endif; ?>
                    </div>
                    <a href="/loan-app/borrower/my_loans.php" class="small">Pay now →</a>
                <?php else: ?>
                    <div class="fs-5 fw-bold text-muted">None</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between">
        <strong>My Loans</strong>
        <a href="/loan-app/borrower/my_loans.php" class="small">View all & pay</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Loan #</th>
                    <th>Principal</th>
                    <th>Total to Repay</th>
                    <th>Progress</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($loans)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">You have no loans yet</td></tr>
            <?php else: foreach ($loans as $l): ?>
                <tr>
                    <td>#<?= $l['id'] ?></td>
                    <td><?= money((float)$l['principal']) ?></td>
                    <td><?= money((float)$l['total_repayable']) ?></td>
                    <td>
                        <?= $l['paid_count'] ?> / <?= $l['total_count'] ?> paid
                        <div class="progress mt-1" style="height:6px;">
                            <?php $pct = $l['total_count'] > 0 ? ($l['paid_count'] / $l['total_count'] * 100) : 0; ?>
                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                        </div>
                    </td>
                    <td>
                        <?php
                        $badge = match($l['status']) {
                            'active'    => 'bg-success',
                            'overdue'   => 'bg-warning text-dark',
                            'defaulted' => 'bg-danger',
                            'paid'      => 'bg-primary',
                            default     => 'bg-secondary'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= ucfirst($l['status']) ?></span>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>