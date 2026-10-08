<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Already logged in → redirect
if (isLoggedIn()) {
    if (currentUserRole() === 'admin') {
        header('Location: /loan-app/admin/dashboard.php');
    } else {
        header('Location: /loan-app/borrower/dashboard.php');
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } elseif (login($username, $password)) {
        if (currentUserRole() === 'admin') {
            header('Location: /loan-app/admin/dashboard.php');
        } else {
            header('Location: /loan-app/borrower/dashboard.php');
        }
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

$pageTitle = 'Login';
require_once __DIR__ . '/includes/header.php';
?>

<div class="login-card">
    <div class="card shadow">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-cash-coin display-4 text-primary"></i>
                <h3 class="mt-2">MicroLend</h3>
                <p class="text-muted small">Simple Pool Micro-Lending System</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autofocus
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>

            <div class="text-center mt-3">
                <a href="/loan-app/register.php" class="small">Create a borrower account</a>
            </div>

            <hr>
            <div class="small text-muted">
                <strong>Demo admin:</strong> admin / admin123
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>