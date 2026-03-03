<?php
/**
 * Login Page
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Check if app is installed
try {
    $installed = getSetting('installed', '0');
    if ($installed !== '1') {
        header('Location: install.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: install.php');
    exit;
}

// Already logged in?
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$timeout = isset($_GET['timeout']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    $adminHash = getSetting('pin_hash', '');
    $estimatorEnabled = getSetting('estimator_enabled', '0');
    $estimatorHash = getSetting('estimator_pin_hash', '');

    if ($adminHash && password_verify($pin, $adminHash)) {
        login('admin');
        header('Location: dashboard.php');
        exit;
    } elseif ($estimatorEnabled === '1' && $estimatorHash && password_verify($pin, $estimatorHash)) {
        login('estimator');
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid PIN. Please try again.';
    }
}

$businessName = getSetting('business_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0077B6">
    <title>Login - <?= e($businessName) ?></title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏊</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <span class="login-logo">🏊</span>
                <h1><?= e($businessName) ?></h1>
                <p>Pool Cost Estimator</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if ($timeout): ?>
                <div class="alert alert-warning">Your session has expired. Please log in again.</div>
            <?php endif; ?>

            <form method="POST" action="index.php" class="login-form">
                <div class="pin-input-group">
                    <label for="pin">Enter PIN to access</label>
                    <input type="password" id="pin" name="pin" 
                           inputmode="numeric" pattern="[0-9]*"
                           placeholder="••••••" 
                           maxlength="20" 
                           autofocus 
                           required
                           autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Unlock
                </button>
            </form>
        </div>
        <p class="login-footer">Pool Cost Estimator v<?= APP_VERSION ?></p>
    </div>
</body>
</html>
