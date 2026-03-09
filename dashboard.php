<?php
/**
 * Dashboard - Main landing page after login
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$pageTitle = 'Dashboard';
$stats = getDashboardStats();
$recentEstimates = getEstimates('', '', 10);
$currencySymbol = getSetting('currency_symbol', '$');

include __DIR__ . '/includes/header.php';
?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon bg-primary">
            <span class="material-icons-round">description</span>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_estimates'] ?></span>
            <span class="stat-label" data-i18n="stat_total_estimates">Total Estimates</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-warning">
            <span class="material-icons-round">edit_note</span>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['draft_estimates'] ?></span>
            <span class="stat-label" data-i18n="stat_drafts">Drafts</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-success">
            <span class="material-icons-round">check_circle</span>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['approved_estimates'] ?></span>
            <span class="stat-label" data-i18n="stat_approved">Approved</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-info">
            <span class="material-icons-round">people</span>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $stats['total_clients'] ?></span>
            <span class="stat-label" data-i18n="stat_clients">Clients</span>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="section-header">
    <h2 data-i18n="quick_actions">Quick Actions</h2>
</div>
<div class="quick-actions">
    <a href="estimate.php" class="quick-action-btn">
        <span class="material-icons-round">add_circle</span>
        <span data-i18n="quick_new_estimate">New Estimate</span>
    </a>
    <a href="clients.php?action=new" class="quick-action-btn">
        <span class="material-icons-round">person_add</span>
        <span data-i18n="quick_new_client">New Client</span>
    </a>
    <a href="dashboard.php?view=all" class="quick-action-btn">
        <span class="material-icons-round">list_alt</span>
        <span data-i18n="quick_all_estimates">All Estimates</span>
    </a>
</div>

<!-- Recent Estimates -->
<div class="section-header">
    <h2 data-i18n="recent_estimates">Recent Estimates</h2>
    <?php if ($stats['total_estimates'] > 0): ?>
        <a href="dashboard.php?view=all" class="btn btn-sm btn-outline" data-i18n="view_all">View All</a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['view']) && $_GET['view'] === 'all'): ?>
    <!-- Search and Filter -->
    <div class="search-bar">
        <form method="GET" class="search-form">
            <input type="hidden" name="view" value="all">
            <div class="search-input-group">
                <span class="material-icons-round">search</span>
                <input type="text" name="search" data-i18n-placeholder="search_estimates" placeholder="Search estimates or clients..." 
                       value="<?= e($_GET['search'] ?? '') ?>">
                <?php if (!empty($_GET['search'])): ?>
                    <a href="dashboard.php?view=all" class="search-clear">&times;</a>
                <?php endif; ?>
            </div>
            <select name="status" onchange="this.form.submit()">
                <option value="" data-i18n="filter_all_status">All Status</option>
                <option value="draft" data-i18n="filter_draft" <?= ($_GET['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                <option value="sent" data-i18n="filter_sent" <?= ($_GET['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Sent</option>
                <option value="approved" data-i18n="filter_approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" data-i18n="filter_rejected" <?= ($_GET['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </form>
    </div>
    <?php
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $total = countEstimates($search, $status);
    $estimates = getEstimates($search, $status, $perPage, ($page - 1) * $perPage);
    ?>
<?php else:
    $estimates = $recentEstimates;
endif; ?>

<?php if (empty($estimates)): ?>
    <div class="empty-state">
        <span class="material-icons-round">description</span>
        <h3 data-i18n="no_estimates_yet">No estimates yet</h3>
        <p data-i18n="no_estimates_msg">Create your first pool estimate to get started.</p>
        <a href="estimate.php" class="btn btn-primary">
            <span class="material-icons-round">add</span>
            <span data-i18n="create_estimate">Create Estimate</span>
        </a>
    </div>
<?php else: ?>
    <div class="estimate-list">
        <?php foreach ($estimates as $est): ?>
            <a href="estimate.php?id=<?= $est['id'] ?>" class="estimate-card">
                <div class="estimate-card-header">
                    <span class="estimate-number"><?= e($est['estimate_number']) ?></span>
                    <?= statusBadge($est['status']) ?>
                </div>
                <div class="estimate-card-body">
                    <div class="estimate-client">
                        <span class="material-icons-round">person</span>
                        <?= e($est['client_name'] ?? 'No client') ?>
                    </div>
                    <div class="estimate-details">
                        <span><?= e($est['pool_length']) ?> × <?= e($est['pool_width']) ?> <?= e(getSetting('measurement_unit', 'ft')) ?></span>
                        <span><?= ucfirst(e($est['pool_material'])) ?></span>
                    </div>
                </div>
                <div class="estimate-card-footer">
                    <span class="estimate-date"><?= formatDate($est['created_at']) ?></span>
                    <span class="estimate-total"><?= formatCurrency((float)$est['total']) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (isset($total) && $total > $perPage): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
                <a href="?view=all&search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&page=<?= $i ?>" 
                   class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
