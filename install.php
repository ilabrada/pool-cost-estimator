<?php
/**
 * Installation Script
 * Creates database tables and sets initial configuration.
 * DELETE THIS FILE after installation for security!
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php'; 

$step = $_GET['step'] ?? '1';
$error = '';
$success = '';

// Step 1: Test DB connection
if ($step === '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // Try connecting to the specific database
        $pdo->exec('USE `' . DB_NAME . '`');
        header('Location: install.php?step=2');
        exit;
    } catch (PDOException $e) {
        $error = 'Database connection failed: ' . $e->getMessage() . '. Please check your credentials in includes/config.local.php (local) or your GitHub secrets (production).';
    }
}

// Step 2: Create tables + run pending migrations (safe to re-run)
$migrationResults = [];
if ($step === '2') {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // 1. Apply base schema (CREATE TABLE IF NOT EXISTS — always safe)
        $pdo->exec(file_get_contents(__DIR__ . '/sql/schema.sql'));
        // schema.sql sets AUTOCOMMIT=0; restore it so migration tracking INSERTs are committed
        $pdo->exec('SET autocommit = 1');

        // 2. Run any pending migrations from sql/migrations/*.sql
        $migrationDir = __DIR__ . '/sql/migrations';
        $migrationFiles = glob($migrationDir . '/*.sql');
        sort($migrationFiles);

        foreach ($migrationFiles as $file) {
            $name = basename($file);
            $already = $pdo->prepare('SELECT id FROM migrations WHERE migration = ?');
            $already->execute([$name]);

            if ($already->fetch()) {
                $migrationResults[] = ['name' => $name, 'status' => 'skipped'];
                continue;
            }

            try {
                $pdo->exec(file_get_contents($file));
                $pdo->prepare('INSERT INTO migrations (migration) VALUES (?)')->execute([$name]);
                $migrationResults[] = ['name' => $name, 'status' => 'applied'];
            } catch (PDOException $me) {
                // Column already exists = harmless; anything else is a real error
                if (strpos($me->getMessage(), 'Duplicate column') !== false ||
                    strpos($me->getMessage(), 'already exists') !== false) {
                    $pdo->prepare('INSERT IGNORE INTO migrations (migration) VALUES (?)')->execute([$name]);
                    $migrationResults[] = ['name' => $name, 'status' => 'skipped'];
                } else {
                    $migrationResults[] = ['name' => $name, 'status' => 'error', 'msg' => $me->getMessage()];
                    $error = 'Migration failed: ' . htmlspecialchars($me->getMessage());
                }
            }
        }

        if (!$error) {
            // If this database was already set up, skip the business-info/PIN step entirely.
            try {
                $check = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'installed'");
                $check->execute();
                $row = $check->fetch();
                if ($row && $row['setting_value'] === '1') {
                    header('Location: install.php?step=done');
                    exit;
                }
            } catch (PDOException $e) { /* new install — proceed to step 3 */ }

            $success = 'Schema and migrations applied successfully.';
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        $step = '1';
    }
}

// Step 3: Set PIN and business info
if ($step === '3' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin'] ?? '';
    $pinConfirm = $_POST['pin_confirm'] ?? '';
    $businessName = trim($_POST['business_name'] ?? '');
    $businessPhone = trim($_POST['business_phone'] ?? '');
    $businessEmail = trim($_POST['business_email'] ?? '');

    if (strlen($pin) < 4) {
        $error = 'PIN must be at least 4 characters.';
    } elseif ($pin !== $pinConfirm) {
        $error = 'PINs do not match.';
    } elseif (empty($businessName)) {
        $error = 'Business name is required.';
    } else {
        require_once __DIR__ . '/includes/functions.php';
        setSetting('pin_hash', password_hash($pin, PASSWORD_DEFAULT));
        setSetting('business_name', $businessName);
        setSetting('business_phone', $businessPhone);
        setSetting('business_email', $businessEmail);
        setSetting('installed', '1');
        header('Location: install.php?step=done');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0077B6">
    <title>Install - Pool Cost Estimator</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container" style="max-width: 500px;">
        <div class="login-card">
            <div class="login-header">
                <span class="login-logo">🏊</span>
                <h1>Pool Cost Estimator</h1>
                <p>Installation Wizard</p>
            </div>

            <!-- Progress -->
            <div class="install-progress">
                <div class="progress-step <?= $step >= '1' ? 'active' : '' ?> <?= $step > '1' ? 'done' : '' ?>">1. Database</div>
                <div class="progress-step <?= $step >= '2' ? 'active' : '' ?> <?= $step > '2' ? 'done' : '' ?>">2. Tables</div>
                <div class="progress-step <?= $step >= '3' ? 'active' : '' ?> <?= $step === 'done' ? 'done' : '' ?>">3. Setup</div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($step === '1'): ?>
                <div class="install-step">
                    <h3>Step 1: Database Connection</h3>
                    <p>Make sure you've configured your database credentials in <code>includes/config.php</code>:</p>
                    <div class="code-block">
                        <pre>DB_HOST: <?= e(DB_HOST) ?>
DB_NAME: <?= e(DB_NAME) ?>
DB_USER: <?= e(DB_USER) ?>
DB_PASS: ••••••</pre>
                    </div>
                    <form method="POST">
                        <button type="submit" class="btn btn-primary btn-block btn-lg">Test Connection</button>
                    </form>
                </div>

            <?php elseif ($step === '2'): ?>
                <div class="install-step">
                    <h3>Step 2: Schema &amp; Migrations</h3>
                    <?php if ($migrationResults): ?>
                        <table style="width:100%;font-size:0.875rem;margin-bottom:1rem;border-collapse:collapse">
                            <thead><tr>
                                <th style="text-align:left;padding:4px 8px;border-bottom:1px solid #dee2e6">Migration</th>
                                <th style="text-align:left;padding:4px 8px;border-bottom:1px solid #dee2e6">Status</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($migrationResults as $r): ?>
                                <tr>
                                    <td style="padding:4px 8px;font-family:monospace"><?= htmlspecialchars($r['name']) ?></td>
                                    <td style="padding:4px 8px">
                                        <?php if ($r['status'] === 'applied'): ?>
                                            <span style="color:#198754">&#10003; Applied</span>
                                        <?php elseif ($r['status'] === 'skipped'): ?>
                                            <span style="color:#6c757d">&ndash; Already applied</span>
                                        <?php else: ?>
                                            <span style="color:#dc3545">&#10007; Error: <?= htmlspecialchars($r['msg'] ?? '') ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>Schema is up to date. No migrations found.</p>
                    <?php endif; ?>
                    <?php if (!$error): ?>
                        <a href="install.php?step=3" class="btn btn-primary btn-block btn-lg">Continue to Setup</a>
                    <?php else: ?>
                        <a href="install.php?step=1" class="btn btn-secondary btn-block">Back</a>
                    <?php endif; ?>
                </div>

            <?php elseif ($step === '3'): ?>
                <div class="install-step">
                    <h3>Step 3: Business Setup</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label for="business_name">Business Name *</label>
                            <input type="text" id="business_name" name="business_name" required 
                                   value="<?= e($_POST['business_name'] ?? 'Pool Builder Pro') ?>">
                        </div>
                        <div class="form-group">
                            <label for="business_phone">Business Phone</label>
                            <input type="tel" id="business_phone" name="business_phone" 
                                   value="<?= e($_POST['business_phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="business_email">Business Email</label>
                            <input type="email" id="business_email" name="business_email" 
                                   value="<?= e($_POST['business_email'] ?? '') ?>">
                        </div>
                        <hr>
                        <div class="form-group">
                            <label for="pin">Access PIN * <small>(min 4 characters, numbers or letters)</small></label>
                            <input type="password" id="pin" name="pin" required minlength="4" 
                                   inputmode="numeric" placeholder="Enter your PIN">
                        </div>
                        <div class="form-group">
                            <label for="pin_confirm">Confirm PIN *</label>
                            <input type="password" id="pin_confirm" name="pin_confirm" required 
                                   inputmode="numeric" placeholder="Confirm your PIN">
                        </div>
                        <button type="submit" class="btn btn-primary btn-block btn-lg">Complete Installation</button>
                    </form>
                </div>

            <?php elseif ($step === 'done'): ?>
                <div class="install-step" style="text-align: center;">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                    <h3>Installation Complete!</h3>
                    <p>Your Pool Cost Estimator is ready to use.</p>
                    <div class="alert alert-warning" style="text-align: left;">
                        <strong>Security:</strong> Delete or rename the <code>install.php</code> file to prevent unauthorized reinstallation.
                    </div>
                    <a href="index.php" class="btn btn-primary btn-block btn-lg">Go to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
