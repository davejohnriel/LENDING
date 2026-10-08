<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
updateLoanStatuses();

$pdo = getPDO();

// Stats
$poolBalance = getPoolBalance();

$stmt = $pdo->query("SELECT COUNT(*) FROM loan_requests WHERE status = 'pending'");
$pendingCount = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'active'");
$activeLoans = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'overdue'");
$overdueLoans = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'defaulted'");
$defaultLoans = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'paid'");
$paidLoans = (int)$stmt->fetchColumn();

// Recent requests
$recentRequests = $pdo->query("
    SELECT r.*, u.full_name 
    FROM loan_requests r 
    JOIN users u ON u.id = r.user_id 
    ORDER BY r.created_at DESC LIMIT 5
")->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Admin Dashboard</h2>
    <span class="text-muted">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="text-muted small">Pool Balance</div>
                <div class="fs-4 fw-bold text-primary"><?= money($poolBalance) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="text-muted small">Pending Requests</div>
                <div class="fs-4 fw-bold"><?= $pendingCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card success">
            <div class="card-body">
                <div class="text-muted small">Active Loans</div>
                <div class="fs-4 fw-bold"><?= $activeLoans ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card danger">
            <div class="card-body">
                <div class="text-muted small">Overdue / Default</div>
                <div class="fs-4 fw-bold"><?= $overdueLoans + $defaultLoans ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between">
                <strong>Recent Loan Requests</strong>
                <a href="/loan-app/admin/requests.php" class="small">View all</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Borrower</th>
                                <th>Amount</th>
                                <th>Term</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentRequests)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No requests yet</td></tr>
                        <?php else: foreach ($recentRequests as $r): ?>
                            <tr>
                                <td>#<?= $r['id'] ?></td>
                                <td><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><?= money((float)$r['amount']) ?></td>
                                <td><?= $r['term_months'] ?> mo</td>
                                <td>
                                    <?php
                                    $badge = match($r['status']) {
                                        'pending'  => 'bg-warning text-dark',
                                        'approved' => 'bg-success',
                                        'rejected' => 'bg-secondary',
                                        default    => 'bg-light text-dark'
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>"><?= ucfirst($r['status']) ?></span>
                                </td>
                                <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white"><strong>Quick Stats</strong></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span>Paid loans</span>
                    <strong><?= $paidLoans ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Overdue</span>
                    <strong class="text-warning"><?= $overdueLoans ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Default</span>
                    <strong class="text-danger"><?= $defaultLoans ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Interest rate</span>
                    <strong><?= getSetting('interest_rate') ?>%</strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Grace days</span>
                    <strong><?= getSetting('grace_days') ?></strong>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>