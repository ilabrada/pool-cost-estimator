<?php
/**
 * Database migration helpers.
 * Shared by install.php, migrate.php (web UI + deploy token) and the
 * pending-migration safety net in auth.php.
 */

require_once __DIR__ . '/db.php';

/**
 * Migration file names (e.g. "004_create_estimators.sql"), sorted.
 */
function migrationFileNames(): array {
    $files = glob(dirname(__DIR__) . '/sql/migrations/*.sql') ?: [];
    $names = array_map('basename', $files);
    sort($names);
    return $names;
}

/**
 * Migration files not yet recorded in the `migrations` table.
 * If the table itself is missing, every migration counts as pending.
 */
function getPendingMigrations(?PDO $pdo = null): array {
    $pdo = $pdo ?? getDB();
    try {
        $applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $applied = [];
    }
    return array_values(array_diff(migrationFileNames(), $applied));
}

function hasPendingMigrations(): bool {
    return !empty(getPendingMigrations());
}

/**
 * Apply the base schema (idempotent) and then every pending migration in order.
 * Stops at the first real error.
 *
 * @return array{results: array<int, array{name: string, status: string, msg?: string}>, applied: int, error: string}
 */
function runMigrations(?PDO $pdo = null): array {
    $pdo = $pdo ?? getDB();
    $results = [];
    $error = '';

    // 1. Base schema (CREATE TABLE IF NOT EXISTS — always safe)
    $pdo->exec(file_get_contents(dirname(__DIR__) . '/sql/schema.sql'));
    // schema.sql sets AUTOCOMMIT=0; restore it so migration tracking INSERTs are committed
    $pdo->exec('SET autocommit = 1');

    // 2. Pending migrations from sql/migrations/*.sql
    $alreadyStmt = $pdo->prepare('SELECT id FROM migrations WHERE migration = ?');
    foreach (migrationFileNames() as $name) {
        $alreadyStmt->execute([$name]);
        if ($alreadyStmt->fetch()) {
            $results[] = ['name' => $name, 'status' => 'skipped'];
            continue;
        }

        try {
            $pdo->exec(file_get_contents(dirname(__DIR__) . '/sql/migrations/' . $name));
            $pdo->prepare('INSERT INTO migrations (migration) VALUES (?)')->execute([$name]);
            $results[] = ['name' => $name, 'status' => 'applied'];
        } catch (PDOException $me) {
            // Duplicate column / object already exists = harmless (e.g. fresh install
            // where schema.sql already has it); record it as done
            if (strpos($me->getMessage(), 'Duplicate column') !== false ||
                strpos($me->getMessage(), 'already exists') !== false) {
                $pdo->prepare('INSERT IGNORE INTO migrations (migration) VALUES (?)')->execute([$name]);
                $results[] = ['name' => $name, 'status' => 'skipped'];
            } else {
                $results[] = ['name' => $name, 'status' => 'error', 'msg' => $me->getMessage()];
                $error = 'Migration ' . $name . ' failed: ' . $me->getMessage();
                break;
            }
        }
    }

    $applied = count(array_filter($results, fn($r) => $r['status'] === 'applied'));
    return ['results' => $results, 'applied' => $applied, 'error' => $error];
}

/**
 * True when the deploy token is configured (CI replaced the placeholder with a
 * real secret) and matches the given value.
 */
function isValidMigrateToken(string $token): bool {
    $expected = defined('MIGRATE_TOKEN') ? (string)MIGRATE_TOKEN : '';
    if (strlen($expected) < 32 || str_starts_with($expected, '{{')) {
        return false; // not configured — token access disabled
    }
    return $token !== '' && hash_equals($expected, $token);
}
