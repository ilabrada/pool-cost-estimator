<?php
/**
 * Migration Runner
 *
 * Applies any pending SQL migration scripts to an already-configured database.
 * Does NOT prompt for or modify business information or the admin PIN.
 * Requires an authenticated admin session.
 */
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
require_once __DIR__ . '/includes/functions.php';

$pageTitle = 'Run Migrations';

$migrationResults = [];
$error   = '';
$success = '';
$ran     = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    $csrf = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCSRFToken($csrf)) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        try {
            $pdo = getDB();

            // 1. Apply base schema (CREATE TABLE IF NOT EXISTS — always safe)
            $pdo->exec(file_get_contents(__DIR__ . '/sql/schema.sql'));

            // 2. Run any pending migrations from sql/migrations/*.sql
            $migrationDir   = __DIR__ . '/sql/migrations';
            $migrationFiles = glob($migrationDir . '/*.sql');
            sort($migrationFiles);

            foreach ($migrationFiles as $file) {
                $name    = basename($file);
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
                    // Duplicate column / object already exists = harmless; record it as skipped
                    if (strpos($me->getMessage(), 'Duplicate column') !== false ||
                        strpos($me->getMessage(), 'already exists') !== false) {
                        $pdo->prepare('INSERT IGNORE INTO migrations (migration) VALUES (?)')->execute([$name]);
                        $migrationResults[] = ['name' => $name, 'status' => 'skipped'];
                    } else {
                        $migrationResults[] = ['name' => $name, 'status' => 'error', 'msg' => $me->getMessage()];
                        $error = 'Migration failed: ' . htmlspecialchars($me->getMessage());
                        break;
                    }
                }
            }

            if (!$error) {
                $applied = count(array_filter($migrationResults, fn($r) => $r['status'] === 'applied'));
                $success = $applied > 0
                    ? "Migrations complete. {$applied} new migration(s) applied."
                    : 'All migrations were already up to date. Nothing to apply.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . htmlspecialchars($e->getMessage());
        }

        $ran = true;
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
