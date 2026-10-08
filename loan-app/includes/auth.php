<?php
/**
 * Authentication helpers
 */
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Attempt login. Returns true on success.
 */
function login(string $username, string $password): bool
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username, password, full_name, role FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role']      = $user['role'];
        return true;
    }
    return false;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /loan-app/index.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: /loan-app/borrower/dashboard.php');
        exit;
    }
}

function requireBorrower(): void
{
    requireLogin();
    if ($_SESSION['role'] !== 'borrower') {
        header('Location: /loan-app/admin/dashboard.php');
        exit;
    }
}

function currentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function currentUserRole(): string
{
    return $_SESSION['role'] ?? '';
}