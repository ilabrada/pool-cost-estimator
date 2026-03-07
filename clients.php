<?php
/**
 * Clients Management Page
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$pageTitle = 'Clients';
$action = $_GET['action'] ?? 'list';
$clientId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Handle delete
if (isset($_GET['delete']) && $clientId > 0) {
    $estimateCount = getClientEstimateCount($clientId);
    if ($estimateCount > 0) {
        $error = "Cannot delete client: they have $estimateCount estimate(s) associated. Delete the estimates first.";
        $action = 'list';
    } else {
        deleteClient($clientId);
        header('Location: clients.php?msg=deleted');
        exit;
    }
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCSRFToken($csrf)) {
        die('Invalid security token.');
    }

    $clientData = [
        'id'      => (int)($_POST['client_id'] ?? 0),
        'name'    => trim($_POST['name'] ?? ''),
        'phone'   => trim($_POST['phone'] ?? ''),
        'email'   => trim($_POST['email'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'notes'   => trim($_POST['notes'] ?? ''),
        'tier'    => in_array($_POST['tier'] ?? '', ['priority', 'standard']) ? $_POST['tier'] : 'priority',
    ];

    if (empty($clientData['name'])) {
        $error = 'Client name is required.';
    } else {
        $savedId = saveClient($clientData);
        header('Location: clients.php?id=' . $savedId . '&action=view&msg=saved');
        exit;
    }
}

// Load client for edit/view
$client = null;
if ($clientId > 0) {
    $client = getClient($clientId);
    if (!$client) {
        header('Location: clients.php?msg=not_found');
        exit;
    }
    if ($action === 'view') {
        $pageTitle = 'Client: ' . $client['name'];
    } elseif ($action === 'edit') {
        $pageTitle = 'Edit Client';
    }
}

if ($action === 'new') {
    $pageTitle = 'New Client';
}

include __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['msg'])): ?>
    <script>document.addEventListener('DOMContentLoaded', () => { 
        const msgs = {saved:'Client saved!', deleted:'Client deleted.', not_found:'Client not found.'};
        showToast(msgs['<?= e($_GET['msg']) ?>'] || 'Done', '<?= $_GET['msg'] === 'deleted' ? 'warning' : 'success' ?>');
    });</script>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <!-- Client List -->
    <div class="section-header">
        <h2>All Clients</h2>
        <a href="clients.php?action=new" class="btn btn-primary btn-sm">
            <span class="material-icons-round">person_add</span> New Client
        </a>
    </div>

    <div class="search-bar">
        <form method="GET" class="search-form">
            <div class="search-input-group">
                <span class="material-icons-round">search</span>
                <input type="text" name="search" placeholder="Search clients..." 
                       value="<?= e($_GET['search'] ?? '') ?>">
                <?php if (!empty($_GET['search'])): ?>
                    <a href="clients.php" class="search-clear">&times;</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php
    $search = $_GET['search'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $total = countClients($search);
    $clients = getClients($search, $perPage, ($page - 1) * $perPage);
    ?>

    <?php if (empty($clients)): ?>
        <div class="empty-state">
            <span class="material-icons-round">people</span>
            <h3>No clients yet</h3>
            <p>Add your first client to get started.</p>
            <a href="clients.php?action=new" class="btn btn-primary">
                <span class="material-icons-round">person_add</span> Add Client
            </a>
        </div>
    <?php else: ?>
        <div class="client-list">
            <?php foreach ($clients as $c): ?>
                <a href="clients.php?id=<?= $c['id'] ?>&action=view" class="client-card">
                    <div class="client-avatar"><?= strtoupper(mb_substr($c['name'], 0, 1)) ?></div>
                    <div class="client-info">
                        <div class="client-name"><?= e($c['name']) ?></div>
                        <div class="client-detail">
                            <?php if ($c['phone']): ?>
                                <span><span class="material-icons-round">phone</span> <?= e($c['phone']) ?></span>
                            <?php endif; ?>
                            <?php if ($c['email']): ?>
                                <span><span class="material-icons-round">email</span> <?= e($c['email']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="material-icons-round client-arrow">chevron_right</span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($total > $perPage): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
                    <a href="?search=<?= urlencode($search) ?>&page=<?= $i ?>" 
                       class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

<?php elseif ($action === 'view' && $client): ?>
    <!-- Client View -->
    <div class="breadcrumb">
        <a href="clients.php">Clients</a> / <?= e($client['name']) ?>
    </div>

    <div class="form-card">
        <div class="form-card-header">
            <h3><span class="material-icons-round">person</span> <?= e($client['name']) ?></h3>
            <div>
                <a href="clients.php?id=<?= $client['id'] ?>&action=edit" class="btn btn-outline btn-sm">
                    <span class="material-icons-round">edit</span> Edit
                </a>
            </div>
        </div>
        <div class="form-card-body">
            <div class="detail-grid">
                <?php if ($client['phone']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Phone</span>
                        <span class="detail-value"><a href="tel:<?= e($client['phone']) ?>"><?= e($client['phone']) ?></a></span>
                    </div>
                <?php endif; ?>
                <?php if ($client['email']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Email</span>
                        <span class="detail-value"><a href="mailto:<?= e($client['email']) ?>"><?= e($client['email']) ?></a></span>
                    </div>
                <?php endif; ?>
                <?php if ($client['address']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Address</span>
                        <span class="detail-value"><?= e($client['address']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($client['notes']): ?>
                    <div class="detail-item">
                        <span class="detail-label">Notes</span>
                        <span class="detail-value"><?= nl2br(e($client['notes'])) ?></span>
                    </div>
                <?php endif; ?>
                <div class="detail-item">
                    <span class="detail-label">Account Type</span>
                    <span class="detail-value"><?= ($client['tier'] ?? 'priority') === 'priority' ? 'Priority' : 'Standard' ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Created</span>
                    <span class="detail-value"><?= formatDate($client['created_at']) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Client's Estimates -->
    <?php $clientEstimates = getEstimates($client['name']); ?>
    <div class="section-header">
        <h2>Estimates (<?= count($clientEstimates) ?>)</h2>
        <a href="estimate.php?client_id=<?= $client['id'] ?>" class="btn btn-primary btn-sm">
            <span class="material-icons-round">add</span> New Estimate
        </a>
    </div>

    <?php if (empty($clientEstimates)): ?>
        <p class="text-muted">No estimates for this client yet.</p>
    <?php else: ?>
        <div class="estimate-list">
            <?php foreach ($clientEstimates as $est): ?>
                <a href="estimate.php?id=<?= $est['id'] ?>" class="estimate-card">
                    <div class="estimate-card-header">
                        <span class="estimate-number"><?= e($est['estimate_number']) ?></span>
                        <?= statusBadge($est['status']) ?>
                    </div>
                    <div class="estimate-card-footer">
                        <span class="estimate-date"><?= formatDate($est['created_at']) ?></span>
                        <span class="estimate-total"><?= formatCurrency((float)$est['total']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($action === 'new' || $action === 'edit'): ?>
    <!-- Client Form -->
    <div class="breadcrumb">
        <a href="clients.php">Clients</a> / <?= $action === 'edit' ? e($client['name']) : 'New Client' ?>
    </div>

    <form method="POST" action="clients.php" class="form-narrow">
        <?= csrfField() ?>
        <input type="hidden" name="client_id" value="<?= e($client['id'] ?? '') ?>">

        <div class="form-card">
            <div class="form-card-body">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?= e($client['name'] ?? $_POST['name'] ?? '') ?>"
                           placeholder="Full name">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?= e($client['phone'] ?? $_POST['phone'] ?? '') ?>"
                               placeholder="(555) 123-4567">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?= e($client['email'] ?? $_POST['email'] ?? '') ?>"
                               placeholder="client@email.com">
                    </div>
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="2" 
                              placeholder="Street address, city, state, zip"><?= e($client['address'] ?? $_POST['address'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" 
                              placeholder="Any additional notes..."><?= e($client['notes'] ?? $_POST['notes'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="tier">Account Type</label>
                    <select id="tier" name="tier" class="form-control">
                        <option value="priority" <?= ($client['tier'] ?? 'priority') === 'priority' ? 'selected' : '' ?>>Priority</option>
                        <option value="standard" <?= ($client['tier'] ?? '') === 'standard' ? 'selected' : '' ?>>Standard</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <a href="clients.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <span class="material-icons-round">save</span> Save Client
            </button>
        </div>

        <?php if ($action === 'edit' && $client): ?>
            <div style="margin-top: 2rem; text-align: right;">
                <button type="button" class="btn btn-outline btn-danger-text btn-sm" 
                        onclick="confirmDelete(<?= $client['id'] ?>, 'client')">
                    <span class="material-icons-round">delete</span> Delete Client
                </button>
            </div>
        <?php endif; ?>
    </form>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
