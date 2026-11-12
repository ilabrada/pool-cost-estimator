<?php
/**
 * Authentication Check
 * Include this at the top of every protected page.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

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

/**
 * ID of the logged-in estimator, or null when the admin is logged in.
 * Records with estimator_id = NULL belong to the admin.
 */
function currentEstimatorId(): ?int {
    if (isAdmin()) {
        return null;
    }
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function currentUserName(): string {
    return $_SESSION['user_name'] ?? (isAdmin() ? 'Admin' : 'Estimator');
}

/**
 * For estimator sessions, confirm the account still exists and is active,
 * so deactivation by the admin takes effect on the next request.
 */
function isSessionUserActive(): bool {
    if (!isLoggedIn()) {
        return false;
    }
    if (isAdmin()) {
        return true;
    }
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        return false;
    }
    try {
        $stmt = getDB()->prepare('SELECT active FROM estimators WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
    } catch (PDOException $e) {
        return false; // e.g. migrations not applied yet — fail closed
    }
    return $row && (int)$row['active'] === 1;
}

/**
 * Auth check for JSON endpoints: responds 401 instead of redirecting.
 */
function requireAuthApi(): void {
    if (!isSessionUserActive()) {
        if (isLoggedIn()) {
            logout();
        }
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function requireAuth(): void {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
    if (!isSessionUserActive()) {
        logout();
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

function login(string $role = 'admin', ?int $estimatorId = null, string $name = 'Admin'): void {
    $_SESSION['authenticated'] = true;
    $_SESSION['user_role'] = $role;
    $_SESSION['user_id'] = $estimatorId;
    $_SESSION['user_name'] = $name;
    $_SESSION['last_activity'] = time();
    session_regenerate_id(true);
}

function logout(): void {
    session_unset();
    session_destroy();
}
