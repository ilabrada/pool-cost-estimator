<?php
/**
 * Database settings for the Docker dev stack (docker/compose.yaml).
 * Mounted over includes/config.local.php inside the app container only.
 */
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'pool_estimator');
define('DB_USER', getenv('DB_USER') ?: 'pool_estimator');
define('DB_PASS', getenv('DB_PASS') ?: '');
