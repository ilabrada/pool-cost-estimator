<?php
/**
 * Estimators Management (admin only)
 * Create estimators, change their PIN, activate/deactivate, and reassign their records.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Estimators';
$pageTitleKey = 'page_estimators';
$action = $_GET['action'] ?? 'list';
$estimatorId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        die('Invalid security token.');
    }

    $formAction = $_POST['form_action'] ?? '';
    $postedId = (int)($_POST['estimator_id'] ?? 0);

    if ($formAction === 'save') {
        $name = trim($_POST['name'] ?? '');
        $pin = $_POST['pin'] ?? '';
        $pinConfirm = $_POST['pin_confirm'] ?? '';
        $isNew = $postedId === 0;

        if ($name === '') {
            $error = 'Name is required.';
        } elseif (mb_strlen($name) > 100) {
            $error = 'Name must be 100 characters or fewer.';
        } elseif ($isNew && $pin === '') {
            $error = 'A PIN is required for a new estimator.';
        } elseif ($pin !== '' && strlen($pin) < 4) {
            $error = 'PIN must be at least 4 characters.';
        } elseif ($pin !== $pinConfirm) {
            $error = 'PINs do not match.';
        } elseif ($pin !== '' && isPinInUse($pin, $isNew ? null : $postedId)) {
            $error = 'This PIN is already in use. Each user needs a unique PIN.';
        } elseif (!$isNew && !getEstimatorById($postedId)) {
            $error = 'Estimator not found.';
        } else {
            $savedId = saveEstimator([
                'id'     => $isNew ? null : $postedId,
                'name'   => $name,
                'pin'    => $pin,
                'active' => $isNew ? 1 : (isset($_POST['active']) ? 1 : 0),
            ]);
            header('Location: estimators.php?id=' . $savedId . '&action=edit&msg=saved');
            exit;
        }
        $action = $isNew ? 'new' : 'edit';
        $estimatorId = $postedId;
    }

    if ($formAction === 'reassign') {
        $from = getEstimatorById($postedId);
        $targetRaw = $_POST['target'] ?? '';
        $target = $targetRaw === 'admin' ? null : (int)$targetRaw;

        if (!$from) {
            $error = 'Estimator not found.';
        } elseif ($target !== null && ($target === $postedId || !getEstimatorById($target))) {
            $error = 'Please choose a different estimator to receive the records.';
        } else {
            $moved = reassignEstimatorRecords($postedId, $target);
            header('Location: estimators.php?id=' . $postedId . '&action=edit&msg=reassigned&c=' . $moved['clients'] . '&e=' . $moved['estimates']);
            exit;
        }
        $action = 'edit';
        $estimatorId = $postedId;
    }
}

$estimator = null;
if ($estimatorId > 0) {
    $estimator = getEstimatorById($estimatorId);
    if (!$estimator) {
        header('Location: estimators.php?msg=not_found');
        exit;
    }
    if ($action === 'list') {
        $action = 'edit';
    }
    $pageTitle = 'Edit Estimator';
    $pageTitleKey = 'page_edit_estimator';
} elseif ($action === 'new') {
    $pageTitle = 'New Estimator';
    $pageTitleKey = 'page_new_estimator';
}

$estimators = getEstimators();

include __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['msg'])): ?>
    <script>document.addEventListener('DOMContentLoaded', () => {
        const msgs = {
            saved: 'Estimator saved!',
            not_found: 'Estimator not found.',
            reassigned: <?= json_encode('Moved ' . (int)($_GET['c'] ?? 0) . ' client(s) and ' . (int)($_GET['e'] ?? 0) . ' estimate(s).') ?>
        };
        showToast(msgs[<?= json_encode($_GET['msg']) ?>] || 'Done', <?= json_encode($_GET['msg'] === 'not_found' ? 'warning' : 'success') ?>);
    });</script>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
    <div class="section-header">
        <h2 data-i18n="page_estimators">Estimators</h2>
        <a href="estimators.php?action=new" class="btn btn-primary btn-sm">
            <span class="material-icons-round">person_add</span> <span data-i18n="btn_new_estimator">New Estimator</span>
        </a>
    </div>

    <p class="form-help" data-i18n="estimators_help">Each estimator logs in with their own PIN and only sees the clients and estimates they created. Only the admin can see Settings and the Audit Log.</p>

    <?php if (empty($estimators)): ?>
        <div class="empty-state">
            <span class="material-icons-round">badge</span>
            <h3 data-i18n="no_estimators_yet">No estimators yet</h3>
            <p data-i18n="no_estimators_msg">Create an estimator so a team member can log in with their own PIN.</p>
            <a href="estimators.php?action=new" class="btn btn-primary">
                <span class="material-icons-round">person_add</span> <span data-i18n="btn_new_estimator">New Estimator</span>
            </a>
        </div>
    <?php else: ?>
        <div class="client-list">
            <?php foreach ($estimators as $est): ?>
                <a href="estimators.php?id=<?= (int)$est['id'] ?>&action=edit" class="client-card">
                    <div class="client-avatar"><?= e(strtoupper(mb_substr($est['name'], 0, 1))) ?></div>
                    <div class="client-info">
                        <div class="client-name">
                            <?= e($est['name']) ?>
                            <?php if ((int)$est['active'] === 1): ?>
                                <span class="badge badge-success" data-i18n="status_active">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger" data-i18n="status_inactive">Inactive</span>
                            <?php endif; ?>
                        </div>
                        <div class="client-detail">
                            <span><span class="material-icons-round">people</span> <?= (int)$est['client_count'] ?> <span data-i18n="nav_clients">Clients</span></span>
                            <span><span class="material-icons-round">description</span> <?= (int)$est['estimate_count'] ?> <span data-i18n="filter_estimates">Estimates</span></span>
                        </div>
                    </div>
                    <span class="material-icons-round client-arrow">chevron_right</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <?php
    $isNew = $action === 'new';
    $formName = $_POST['name'] ?? ($estimator['name'] ?? '');
    $formActive = $estimator ? (int)$estimator['active'] === 1 : true;
    ?>
    <div class="breadcrumb">
        <a href="estimators.php" data-i18n="page_estimators">Estimators</a> / <?= $isNew ? '<span data-i18n="page_new_estimator">New Estimator</span>' : e($estimator['name']) ?>
    </div>

    <form method="POST" class="form-narrow">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="save">
        <input type="hidden" name="estimator_id" value="<?= $isNew ? 0 : (int)$estimator['id'] ?>">

        <div class="form-card">
            <div class="form-card-header">
                <h3><span class="material-icons-round">badge</span> <span data-i18n="section_estimator_details">Estimator Details</span></h3>
            </div>
            <div class="form-card-body">
                <div class="form-group">
                    <label for="name" data-i18n="label_estimator_name">Name</label>
                    <input type="text" id="name" name="name" required maxlength="100" value="<?= e($formName) ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="pin">
                            <span data-i18n="label_pin">PIN</span>
                            <small><?= $isNew ? '<span data-i18n="hint_min_4">(min 4 characters)</span>' : '<span data-i18n="hint_pin_keep">(leave blank to keep current)</span>' ?></small>
                        </label>
                        <input type="password" id="pin" name="pin" minlength="4" maxlength="20"
                               inputmode="numeric" autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label for="pin_confirm" data-i18n="label_confirm_pin_short">Confirm PIN</label>
                        <input type="password" id="pin_confirm" name="pin_confirm" minlength="4" maxlength="20"
                               inputmode="numeric" autocomplete="new-password" <?= $isNew ? 'required' : '' ?>>
                    </div>
                </div>
                <?php if (!$isNew): ?>
                    <div class="form-group">
                        <label class="toggle-label">
                            <input type="checkbox" name="active" value="1" <?= $formActive ? 'checked' : '' ?>>
                            <span data-i18n="label_active_can_login">Active (can log in)</span>
                        </label>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-actions">
            <a href="estimators.php" class="btn btn-outline" data-i18n="btn_cancel">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <span class="material-icons-round">save</span> <span data-i18n="btn_save_estimator_user">Save Estimator</span>
            </button>
        </div>
    </form>

    <?php if (!$isNew): ?>
        <?php
        $counts = array_values(array_filter($estimators, fn($x) => (int)$x['id'] === (int)$estimator['id']))[0] ?? ['client_count' => 0, 'estimate_count' => 0];
        $targets = array_filter($estimators, fn($x) => (int)$x['id'] !== (int)$estimator['id'] && (int)$x['active'] === 1);
        ?>
        <form method="POST" class="form-narrow" style="margin-top: 2rem;"
              onsubmit="return confirm(this.dataset.confirm);"
              data-confirm="<?= e('Move all clients and estimates from ' . $estimator['name'] . ' to the selected owner?') ?>">
            <?= csrfField() ?>
            <input type="hidden" name="form_action" value="reassign">
            <input type="hidden" name="estimator_id" value="<?= (int)$estimator['id'] ?>">

            <div class="form-card">
                <div class="form-card-header">
                    <h3><span class="material-icons-round">swap_horiz</span> <span data-i18n="section_reassign">Reassign Records</span></h3>
                </div>
                <div class="form-card-body">
                    <p class="form-help">
                        <?= e($estimator['name']) ?>:
                        <?= (int)$counts['client_count'] ?> <span data-i18n="nav_clients">Clients</span>,
                        <?= (int)$counts['estimate_count'] ?> <span data-i18n="filter_estimates">Estimates</span>.
                        <span data-i18n="reassign_help">Move all of them to another owner, for example before or after deactivating this estimator.</span>
                    </p>
                    <div class="form-group">
                        <label for="target" data-i18n="label_reassign_to">Move to</label>
                        <select id="target" name="target" required>
                            <option value="admin" data-i18n="owner_admin">Admin</option>
                            <?php foreach ($targets as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary" <?= ((int)$counts['client_count'] + (int)$counts['estimate_count']) === 0 ? 'disabled' : '' ?>>
                    <span class="material-icons-round">swap_horiz</span> <span data-i18n="btn_reassign">Reassign</span>
                </button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
