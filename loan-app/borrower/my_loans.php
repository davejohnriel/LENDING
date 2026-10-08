<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireBorrower();
updateLoanStatuses();

$userId = currentUserId();
$pdo = getPDO();

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_installment'])) {
    $installmentId = (int)$_POST['installment_id'];
    $result = payInstallment($installmentId, $userId);
    if ($result['ok']) {
        setFlash('success', 'Payment successful! New wallet balance: ' . money($result['new_balance']));
    } else {
        setFlash('danger', $result['error']);
    }
    header('Location: /loan-app/borrower/my_loans.php');
    exit;
}

// All loans of this user
$stmt = $pdo->prepare("
    SELECT l.*,
           (SELECT COUNT(*) FROM installments i WHERE i.loan_id = l.id AND i.status = 'paid') AS paid_count,
           (SELECT COUNT(*) FROM installments i WHERE i.loan_id = l.id) AS total_count
    FROM loans l
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$userId]);
$loans = $stmt->fetchAll();

// Load installments for each loan
$loanIds = array_column($loans, 'id');
$installmentsByLoan = [];
if ($loanIds) {
    $placeholders = implode(',', array_fill(0, count($loanIds), '?'));
    $stmt = $pdo->prepare("SELECT * FROM installments WHERE loan_id IN ($placeholders) ORDER BY installment_no");
    $stmt->execute($loanIds);
    foreach ($stmt->fetchAll() as $inst) {
        $installmentsByLoan[$inst['loan_id']][] = $inst;
    }
}

$walletBalance = getWalletBalance($userId);

$pageTitle = 'My Loans';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">My Loans & Payments</h2>
    <div class="text-end">
        <div class="text-muted small">Wallet Balance</div>
        <div class="fs-5 fw-bold text-primary"><?= money($walletBalance) ?></div>
    </div>
</div>

<?php if (empty($loans)): ?>
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <p class="mb-2">You have no loans yet.</p>
            <a href="/loan-app/borrower/request_loan.php" class="btn btn-primary">Request a Loan</a>
        </div>
    </div>
<?php else: foreach ($loans as $l): ?>
    <div class="card mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <strong>Loan #<?= $l['id'] ?></strong>
                <span class="ms-2">
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
                </span>
            </div>
            <div class="small text-muted">
                Principal: <?= money((float)$l['principal']) ?> ·
                Interest: <?= money((float)$l['total_interest']) ?> (<?= $l['interest_rate'] ?>%) ·
                Total: <strong><?= money((float)$l['total_repayable']) ?></strong>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Paid At</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($installmentsByLoan[$l['id']] ?? [] as $inst): ?>
                        <tr class="<?= $inst['status'] === 'overdue' ? 'table-warning' : '' ?>">
                            <td><?= $inst['installment_no'] ?></td>
                            <td><?= money((float)$inst['amount']) ?></td>
                            <td><?= date('M j, Y', strtotime($inst['due_date'])) ?></td>
                            <td>
                                <?php
                                $ibadge = match($inst['status']) {
                                    'paid'    => 'bg-success',
                                    'overdue' => 'bg-danger',
                                    default   => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?= $ibadge ?>"><?= ucfirst($inst['status']) ?></span>
                            </td>
                            <td><?= $inst['paid_at'] ? date('M j, Y H:i', strtotime($inst['paid_at'])) : '—' ?></td>
                            <td>
                                <?php if (in_array($inst['status'], ['pending', 'overdue'])): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Pay <?= money((float)$inst['amount']) ?> from your wallet?');">
                                        <input type="hidden" name="installment_id" value="<?= $inst['id'] ?>">
                                        <button type="submit" name="pay_installment" class="btn btn-sm btn-primary"
                                            <?= $walletBalance < (float)$inst['amount'] ? 'disabled title="Insufficient balance"' : '' ?>>
                                            Pay
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-success small"><i class="bi bi-check-lg"></i></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; endif; ?>

<div class="alert alert-info small">
    <strong>Note:</strong> This is a simulated system. To test repayments you may need to manually increase a borrower’s wallet balance in the database (or we can add a “Top-up wallet” demo button later).
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>