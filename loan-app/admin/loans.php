<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
updateLoanStatuses();

$pdo = getPDO();

$filter = $_GET['status'] ?? 'all';
$allowed = ['all', 'active', 'overdue', 'defaulted', 'paid'];
if (!in_array($filter, $allowed)) $filter = 'all';

$sql = "
    SELECT l.*, u.full_name, u.username,
           (SELECT COUNT(*) FROM installments i WHERE i.loan_id = l.id AND i.status = 'paid') AS paid_count,
           (SELECT COUNT(*) FROM installments i WHERE i.loan_id = l.id) AS total_count
    FROM loans l
    JOIN users u ON u.id = l.user_id
";
$params = [];
if ($filter !== 'all') {
    $sql .= " WHERE l.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY l.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();

$pageTitle = 'All Loans';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">All Loans</h2>
    <div class="btn-group">
        <?php foreach (['all'=>'All','active'=>'Active','overdue'=>'Overdue','defaulted'=>'Defaulted','paid'=>'Paid'] as $k=>$label): ?>
            <a href="?status=<?= $k ?>" class="btn btn-sm <?= $filter===$k ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Loan #</th>
                    <th>Borrower</th>
                    <th>Principal</th>
                    <th>Interest</th>
                    <th>Total</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Disbursed</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($loans)): ?>
                <tr><td colspan="8" class="text-center text-muted py-5">No loans found</td></tr>
            <?php else: foreach ($loans as $l): ?>
                <tr>
                    <td>#<?= $l['id'] ?></td>
                    <td>
                        <div><?= htmlspecialchars($l['full_name']) ?></div>
                        <small class="text-muted">@<?= htmlspecialchars($l['username']) ?></small>
                    </td>
                    <td><?= money((float)$l['principal']) ?></td>
                    <td><?= money((float)$l['total_interest']) ?> <small class="text-muted">(<?= $l['interest_rate'] ?>%)</small></td>
                    <td class="fw-semibold"><?= money((float)$l['total_repayable']) ?></td>
                    <td>
                        <?= $l['paid_count'] ?> / <?= $l['total_count'] ?>
                        <div class="progress mt-1" style="height:6px;">
                            <?php $pct = $l['total_count'] > 0 ? ($l['paid_count'] / $l['total_count'] * 100) : 0; ?>
                            <div class="progress-bar" style="width:<?= $pct ?>%"></div>
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
                    <td><?= $l['disbursed_at'] ? date('M j, Y', strtotime($l['disbursed_at'])) : '—' ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>