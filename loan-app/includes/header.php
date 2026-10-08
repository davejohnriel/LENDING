<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$appName = 'MicroLend';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $appName) ?> · <?= $appName ?></title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/loan-app/assets/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= isLoggedIn() ? (currentUserRole() === 'admin' ? '/loan-app/admin/dashboard.php' : '/loan-app/borrower/dashboard.php') : '/loan-app/index.php' ?>">
            <i class="bi bi-cash-coin"></i> <?= $appName ?>
        </a>
        <?php if (isLoggedIn()): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto">
                <?php if (currentUserRole() === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/loan-app/admin/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'requests.php' ? 'active' : '' ?>" href="/loan-app/admin/requests.php">Loan Requests</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'loans.php' ? 'active' : '' ?>" href="/loan-app/admin/loans.php">All Loans</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'pool.php' ? 'active' : '' ?>" href="/loan-app/admin/pool.php">Pool</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="/loan-app/borrower/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'request_loan.php' ? 'active' : '' ?>" href="/loan-app/borrower/request_loan.php">Request Loan</a></li>
                    <li class="nav-item"><a class="nav-link <?= $currentPage === 'my_loans.php' ? 'active' : '' ?>" href="/loan-app/borrower/my_loans.php">My Loans</a></li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center text-white">
                <span class="me-3"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
                <a href="/loan-app/logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</nav>

<main class="container my-4">
<?php
$flash = getFlash();
if ($flash):
    $alertClass = $flash['type'] === 'success' ? 'alert-success' : ($flash['type'] === 'danger' ? 'alert-danger' : 'alert-info');
?>
    <div class="alert <?= $alertClass ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>