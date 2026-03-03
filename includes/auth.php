<?php
/**
 * Authentication Check
 * Include this at the top of every protected page.
 */

require_once __DIR__ . '/config.php';

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    session_name(SESSION_NAME);
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

function isLoggedIn(): bool {
    return !empty($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
}

function isAdmin(): bool {
    return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
    // Check session timeout
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_unset();
        session_destroy();
        header('Location: index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireAdmin(): void {
    requireAuth();
    if (!isAdmin()) {
        header('Location: dashboard.php');
        exit;
    }
}

function login(string $role = 'admin'): void {
    $_SESSION['authenticated'] = true;
    $_SESSION['user_role'] = $role;
    $_SESSION['last_activity'] = time();
    session_regenerate_id(true);
}

function logout(): void {
    session_unset();
    session_destroy();
}
