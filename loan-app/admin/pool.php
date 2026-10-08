<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getPDO();
$poolBalance = getPoolBalance();

// Recent pool-related transactions
$stmt = $pdo->query("
    SELECT * FROM transactions 
    WHERE user_id IS NULL 
    ORDER BY created_at DESC 
    LIMIT 30
");
$txns = $stmt->fetchAll();

$pageTitle = 'Loan Pool';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Shared Loan Pool</h2>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="card-body text-center py-4">
                <div class="text-muted">Current Pool Balance</div>
                <div class="display-5 fw-bold text-primary"><?= money($poolBalance) ?></div>
                <div class="small text-muted mt-2">Available for new loans</div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">How the pool works</h5>
                <ul class="mb-0">
                    <li>All approved loans are funded from this single shared pool.</li>
                    <li>When a borrower repays an installment, the money returns to the pool.</li>
                    <li>Admin cannot approve a loan if the pool balance is insufficient.</li>
                    <li>There is no real bank integration — this is a simulated internal pool.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white"><strong>Pool Transaction History</strong></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($txns)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No transactions yet</td></tr>
            <?php else: foreach ($txns as $t): ?>
                <tr>
                    <td><?= date('M j, Y H:i', strtotime($t['created_at'])) ?></td>
                    <td>
                        <?php if ($t['type'] === 'credit'): ?>
                            <span class="badge bg-success">In</span>
                        <?php else: ?>
                            <span class="badge bg-danger">Out</span>
                        <?php endif; ?>
                    </td>
                    <td class="<?= $t['type']==='credit' ? 'text-success' : 'text-danger' ?>">
                        <?= $t['type']==='credit' ? '+' : '-' ?><?= money((float)$t['amount']) ?>
                    </td>
                    <td><?= $t['balance_after'] !== null ? money((float)$t['balance_after']) : '—' ?></td>
                    <td><?= htmlspecialchars($t['description']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>