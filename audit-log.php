<?php
/**
 * Audit Log Viewer
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$pageTitle = 'Audit Log';

// Filters
$filterType = $_GET['type'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$total = countAuditLogs($filterType);
$logs = getAuditLogs($filterType, $perPage, ($page - 1) * $perPage);
$totalPages = max(1, ceil($total / $perPage));

include __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <h2>Audit Log</h2>
    <span class="badge badge-secondary"><?= $total ?> entries</span>
</div>

<!-- Filters -->
<div class="search-bar">
    <form method="GET" class="search-form">
        <select name="type" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="estimate" <?= $filterType === 'estimate' ? 'selected' : '' ?>>Estimates</option>
            <option value="client" <?= $filterType === 'client' ? 'selected' : '' ?>>Clients</option>
            <option value="settings" <?= $filterType === 'settings' ? 'selected' : '' ?>>Settings</option>
        </select>
    </form>
</div>

<?php if (empty($logs)): ?>
    <div class="empty-state">
        <span class="material-icons-round">history</span>
        <h3>No audit entries yet</h3>
        <p>Activity will appear here when estimates or clients are saved.</p>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="data-table audit-table">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Type</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $details = $log['details'] ? json_decode($log['details'], true) : [];
                    $actionColors = [
                        'create'    => 'badge-success',
                        'update'    => 'badge-primary',
                        'delete'    => 'badge-danger',
                        'duplicate' => 'badge-warning',
                    ];
                    $actionClass = $actionColors[$log['action']] ?? 'badge-secondary';

                    // Build a human-readable summary
                    $summary = '';
                    if ($log['entity_type'] === 'estimate') {
                        $num = $details['estimate_number'] ?? '#' . $log['entity_id'];
                        if ($log['action'] === 'create') {
                            $summary = 'Created estimate ' . $num;
                        } elseif ($log['action'] === 'update') {
                            $summary = 'Updated estimate ' . $num;
                        } elseif ($log['action'] === 'delete') {
                            $summary = 'Deleted estimate ' . $num;
                        } elseif ($log['action'] === 'duplicate') {
                            $summary = 'Duplicated ' . ($details['source_number'] ?? '') . ' → ' . ($details['new_number'] ?? '');
                        }
                        if (!empty($details['total'])) {
                            $summary .= ' (' . formatCurrency((float)$details['total']) . ')';
                        }
                    } elseif ($log['entity_type'] === 'client') {
                        $name = $details['name'] ?? '#' . $log['entity_id'];
                        if ($log['action'] === 'create') {
                            $summary = 'Created client: ' . $name;
                        } elseif ($log['action'] === 'update') {
                            $summary = 'Updated client: ' . $name;
                        } elseif ($log['action'] === 'delete') {
                            $summary = 'Deleted client: ' . $name;
                        }
                    } elseif ($log['entity_type'] === 'settings') {
                        $section = ucfirst($details['section'] ?? 'unknown');
                        $summary = 'Updated settings: ' . $section;
                    }

                    // Build link to entity
                    $entityLink = '';
                    if ($log['entity_id'] && $log['action'] !== 'delete') {
                        if ($log['entity_type'] === 'estimate') {
                            $entityLink = 'estimate.php?id=' . $log['entity_id'];
                        } elseif ($log['entity_type'] === 'client') {
                            $entityLink = 'clients.php?id=' . $log['entity_id'] . '&action=view';
                        }
                    }
                    ?>
                    <tr>
                        <td class="audit-date" data-label="Date">
                            <span class="audit-date-main"><?= formatDate($log['created_at']) ?></span>
                            <span class="audit-date-time"><?= date('g:i:s A', strtotime($log['created_at'])) ?></span>
                        </td>
                        <td data-label="User">
                            <span class="badge <?= $log['user_role'] === 'admin' ? 'badge-primary' : 'badge-secondary' ?>">
                                <?= ucfirst(e($log['user_role'])) ?>
                            </span>
                        </td>
                        <td data-label="Action">
                            <span class="badge <?= $actionClass ?>"><?= ucfirst(e($log['action'])) ?></span>
                        </td>
                        <td data-label="Type">
                            <span class="audit-entity-type"><?= ucfirst(e($log['entity_type'])) ?></span>
                        </td>
                        <td class="audit-summary" data-label="Details">
                            <?php if ($entityLink): ?>
                                <a href="<?= e($entityLink) ?>"><?= e($summary) ?></a>
                            <?php else: ?>
                                <?= e($summary) ?>
                            <?php endif; ?>
                        </td>
                        <td class="audit-ip" data-label="IP">
                            <code><?= e($log['ip_address'] ?? '') ?></code>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?type=<?= urlencode($filterType) ?>&page=<?= $i ?>" 
                   class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
