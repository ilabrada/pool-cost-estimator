<?php
/**
 * Pool Cost Estimator - Configuration
 * 
 * IMPORTANT: Update the database credentials below before running install.php
 * For Hostinger: Find your DB credentials in hPanel > Databases > MySQL Databases
 */

// ── Database Configuration ──────────────────────────────────────────
define('DB_HOST', '{{DB_HOST}}');          // Usually 'localhost' on Hostinger
define('DB_PORT', '{{DB_PORT}}');              // 3306 for Hostinger/production; 8889 for MAMP
define('DB_NAME', '{{DB_NAME}}');     // Your database name
define('DB_USER', '{{DB_USER}}');              // Your database username
define('DB_PASS', '{{DB_PASS}}');                  // Your database password
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
