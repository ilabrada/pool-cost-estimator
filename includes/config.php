<?php
// ── Local Development Overrides ─────────────────────────────────────
// config.local.php is gitignored and loaded only when present.
// It defines DB_* constants before the token placeholders below,
// so the tokens are never actually used in local development.
$_localConfig = __DIR__ . '/config.local.php';
if (file_exists($_localConfig)) {
    require_once $_localConfig;
}
unset($_localConfig);

// ── Database Configuration ──────────────────────────────────────────
// In CI/CD these tokens are replaced by sed before deployment.
// Locally they are overridden by config.local.php above.
defined('DB_HOST')    || define('DB_HOST',    '{{DB_HOST}}');
defined('DB_PORT')    || define('DB_PORT',    '{{DB_PORT}}');
defined('DB_NAME')    || define('DB_NAME',    '{{DB_NAME}}');
defined('DB_USER')    || define('DB_USER',    '{{DB_USER}}');
defined('DB_PASS')    || define('DB_PASS',    '{{DB_PASS}}');
define('DB_CHARSET', 'utf8mb4');

// ── Application Configuration ───────────────────────────────────────
define('APP_NAME', 'Pool Cost Estimator');
define('APP_VERSION', '1.0.0');
define('APP_URL', '');                  // Leave empty for auto-detect, or set full URL

// ── Session Configuration ───────────────────────────────────────────
define('SESSION_NAME', 'pool_estimator_session');
define('SESSION_LIFETIME', 86400);      // 24 hours in seconds

// ── Security ────────────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', 'csrf_token');

// ── Timezone ────────────────────────────────────────────────────────
date_default_timezone_set('America/New_York'); // Change to your timezone

// ── Error Reporting (set to 0 for production) ───────────────────────
error_reporting(E_ALL);
ini_set('display_errors', '0');         // Set to '1' for development
ini_set('log_errors', '1');
