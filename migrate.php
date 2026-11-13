<?php
/**
 * Migration Runner
 *
 * Applies any pending SQL migration scripts to an already-configured database.
 * Does NOT prompt for or modify business information or the admin PIN.
 *
 * Two ways in:
 *  - Web UI: requires an authenticated admin session (+ CSRF).
 *  - Deploy pipeline: POST token=<MIGRATE_TOKEN> returns JSON, HTTP 500 on failure.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/migrations.php';

// ── Deploy pipeline (token) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    header('Content-Type: application/json');
    if (!isValidMigrateToken((string)$_POST['token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid or unconfigured migrate token.']);
        exit;
    }
    try {
        $run = runMigrations();
    } catch (PDOException $e) {
        $run = ['results' => [], 'applied' => 0, 'error' => 'Database error: ' . $e->getMessage()];
    }
    if ($run['error']) {
        http_response_code(500);
    }
    echo json_encode([
        'success' => $run['error'] === '',
        'applied' => $run['applied'],
        'error'   => $run['error'],
        'results' => $run['results'],
    ], JSON_PRETTY_PRINT);
    exit;
}

// ── Web UI (admin) ──────────────────────────────────────────────────
requireAdmin();
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Run Migrations';

$migrationResults = [];
$error   = '';
$success = '';
$ran     = false;
$pending = getPendingMigrations();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    $csrf = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCSRFToken($csrf)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $run = runMigrations();
            $migrationResults = $run['results'];
            if ($run['error']) {
                $error = $run['error'];
            } else {
                $success = $run['applied'] > 0
                    ? "Migrations complete. {$run['applied']} new migration(s) applied."
                    : 'All migrations were already up to date. Nothing to apply.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }

        $ran = true;
        $pending = getPendingMigrations();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title">
        <span class="material-icons-round">upgrade</span>
        Run Migrations
    </h2>
</div>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-body">

        <p>This tool applies any pending database migration scripts. It will <strong>never</strong> modify your business information or admin PIN.</p>

        <?php if (!$ran): ?>
            <?php if ($pending): ?>
                <div class="alert alert-warning">
                    The database needs updating before the app can be used.
                    Pending: <code><?= htmlspecialchars(implode(', ', $pending)) ?></code>
                </div>
            <?php else: ?>
                <p style="color:#198754">&#10003; The database is up to date.</p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($ran && $migrationResults): ?>
            <table style="width:100%;font-size:0.875rem;margin-bottom:1.25rem;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:6px 10px;border-bottom:2px solid #dee2e6">Migration File</th>
                        <th style="text-align:left;padding:6px 10px;border-bottom:2px solid #dee2e6">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($migrationResults as $r): ?>
                        <tr>
                            <td style="padding:6px 10px;font-family:monospace"><?= htmlspecialchars($r['name']) ?></td>
                            <td style="padding:6px 10px">
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
        <?php elseif ($ran && !$migrationResults): ?>
            <p style="color:#6c757d;margin-bottom:1.25rem;">No migration files found in <code>sql/migrations/</code>.</p>
        <?php endif; ?>

        <?php if (!$ran || $error): ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="run_migrations" value="1">
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons-round" style="font-size:18px;vertical-align:middle;margin-right:4px">play_arrow</span>
                    Run Pending Migrations
                </button>
                <a href="settings.php" class="btn btn-secondary" style="margin-left:8px">Cancel</a>
            </form>
        <?php else: ?>
            <a href="settings.php" class="btn btn-primary">Back to Settings</a>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
