<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
updateLoanStatuses();

$pdo = getPDO();

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $note      = trim($_POST['admin_note'] ?? '');

    if ($action === 'approve' && $requestId > 0) {
        $result = approveLoanRequest($requestId, currentUserId());
        if ($result['ok']) {
            setFlash('success', 'Loan request #' . $requestId . ' approved and disbursed.');
        } else {
            setFlash('danger', $result['error']);
        }
    } elseif ($action === 'reject' && $requestId > 0) {
        $result = rejectLoanRequest($requestId, currentUserId(), $note);
        if ($result['ok']) {
            setFlash('success', 'Loan request #' . $requestId . ' rejected.');
        } else {
            setFlash('danger', $result['error']);
        }
    }
    header('Location: /loan-app/admin/requests.php');
    exit;
}

$filter = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$sql = "
    SELECT r.*, u.full_name, u.username, u.email
    FROM loan_requests r
    JOIN users u ON u.id = r.user_id
";
$params = [];
if ($filter !== 'all') {
    $sql .= " WHERE r.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY r.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$pageTitle = 'Loan Requests';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Loan Requests</h2>
    <div class="btn-group">
        <a href="?status=pending"  class="btn btn-sm <?= $filter==='pending'  ? 'btn-warning' : 'btn-outline-secondary' ?>">Pending</a>
        <a href="?status=approved" class="btn btn-sm <?= $filter==='approved' ? 'btn-success' : 'btn-outline-secondary' ?>">Approved</a>
        <a href="?status=rejected" class="btn btn-sm <?= $filter==='rejected' ? 'btn-secondary' : 'btn-outline-secondary' ?>">Rejected</a>
        <a href="?status=all"      class="btn btn-sm <?= $filter==='all'      ? 'btn-primary' : 'btn-outline-secondary' ?>">All</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Borrower</th>
                    <th>Amount</th>
                    <th>Term</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($requests)): ?>
                <tr><td colspan="8" class="text-center text-muted py-5">No requests found</td></tr>
            <?php else: foreach ($requests as $r): ?>
                <tr>
                    <td>#<?= $r['id'] ?></td>
                    <td>
                        <div><?= htmlspecialchars($r['full_name']) ?></div>
                        <small class="text-muted">@<?= htmlspecialchars($r['username']) ?></small>
                    </td>
                    <td class="fw-semibold"><?= money((float)$r['amount']) ?></td>
                    <td><?= $r['term_months'] ?> months</td>
                    <td><?= htmlspecialchars($r['purpose'] ?? '—') ?></td>
                    <td>
                        <?php
                        $badge = match($r['status']) {
                            'pending'  => 'bg-warning text-dark',
                            'approved' => 'bg-success',
                            'rejected' => 'bg-secondary',
                            default    => 'bg-light'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= ucfirst($r['status']) ?></span>
                    </td>
                    <td><?= date('M j, Y H:i', strtotime($r['created_at'])) ?></td>
                    <td>
                        <?php if ($r['status'] === 'pending'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Approve and disburse this loan?');">
                                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success btn-sm">Approve</button>
                            </form>
                            <button type="button" class="btn btn-outline-danger btn-sm"
                                    data-bs-toggle="modal" data-bs-target="#rejectModal<?= $r['id'] ?>">
                                Reject
                            </button>

                            <!-- Reject Modal -->
                            <div class="modal fade" id="rejectModal<?= $r['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <form method="post" class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Reject Request #<?= $r['id'] ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <div class="mb-3">
                                                <label class="form-label">Reason / Note (optional)</label>
                                                <textarea name="admin_note" class="form-control" rows="2"></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-danger">Reject Request</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>