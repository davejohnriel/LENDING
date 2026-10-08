<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireBorrower();

$minAmount = (float)getSetting('min_loan_amount', 1000);
$maxAmount = (float)getSetting('max_loan_amount', 50000);
$minTerm   = (int)getSetting('min_term_months', 1);
$maxTerm   = (int)getSetting('max_term_months', 12);
$rate      = getSetting('interest_rate', '5.00');

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount'] ?? 0);
    $term   = (int)($_POST['term_months'] ?? 0);
    $purpose = trim($_POST['purpose'] ?? '');

    $result = createLoanRequest(currentUserId(), $amount, $term, $purpose ?: null);
    if ($result['ok']) {
        setFlash('success', 'Loan request submitted successfully. Waiting for admin approval.');
        header('Location: /loan-app/borrower/dashboard.php');
        exit;
    } else {
        $error = $result['error'];
    }
}

$pageTitle = 'Request a Loan';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h3 class="mb-3">Request a Loan</h3>
                <p class="text-muted small">
                    Interest rate: <strong><?= htmlspecialchars($rate) ?>%</strong> (simple interest for the whole term).<br>
                    Amount limits: <?= money($minAmount) ?> – <?= money($maxAmount) ?>.<br>
                    Term: <?= $minTerm ?> – <?= $maxTerm ?> months.
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Loan Amount (₱)</label>
                        <input type="number" name="amount" class="form-control" required
                               min="<?= $minAmount ?>" max="<?= $maxAmount ?>" step="100"
                               value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Term (months)</label>
                        <select name="term_months" class="form-select" required>
                            <?php for ($i = $minTerm; $i <= $maxTerm; $i++): ?>
                                <option value="<?= $i ?>" <?= (isset($_POST['term_months']) && (int)$_POST['term_months'] === $i) ? 'selected' : '' ?>>
                                    <?= $i ?> month<?= $i > 1 ? 's' : '' ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Purpose (optional)</label>
                        <input type="text" name="purpose" class="form-control" maxlength="255"
                               value="<?= htmlspecialchars($_POST['purpose'] ?? '') ?>"
                               placeholder="e.g. School fees, emergency, laptop">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Submit Request</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>