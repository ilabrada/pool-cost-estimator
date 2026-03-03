<?php
/**
 * Settings Page - Business info, pricing, PIN
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin();

$pageTitle = 'Settings';
$tab = $_GET['tab'] ?? 'business';
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCSRFToken($csrf)) {
        die('Invalid security token.');
    }

    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'business') {
        setSetting('business_name', trim($_POST['business_name'] ?? ''));
        setSetting('business_phone', trim($_POST['business_phone'] ?? ''));
        setSetting('business_email', trim($_POST['business_email'] ?? ''));
        setSetting('business_address', trim($_POST['business_address'] ?? ''));
        setSetting('tax_rate', (string)(float)($_POST['tax_rate'] ?? 7));
        setSetting('currency_symbol', trim($_POST['currency_symbol'] ?? '$'));
        setSetting('measurement_unit', trim($_POST['measurement_unit'] ?? 'ft'));
        setSetting('estimate_validity_days', (string)(int)($_POST['estimate_validity_days'] ?? 30));
        setSetting('estimate_prefix', trim($_POST['estimate_prefix'] ?? 'EST'));
        setSetting('estimate_terms', trim($_POST['estimate_terms'] ?? ''));
        logAudit('settings', null, 'update', ['section' => 'business']);
        $success = 'Business settings saved!';
        $tab = 'business';
    }

    if ($formAction === 'pricing') {
        $db = getDB();
        if (!empty($_POST['price_id'])) {
            foreach ($_POST['price_id'] as $i => $priceId) {
                $stmt = $db->prepare('UPDATE pricing SET unit_price = ? WHERE id = ?');
                $stmt->execute([
                    (float)($_POST['price_value'][$i] ?? 0),
                    (int)$priceId
                ]);
            }
        }
        $success = 'Pricing updated!';
        logAudit('settings', null, 'update', ['section' => 'pricing']);
        $tab = 'pricing';
    }

    if ($formAction === 'pin') {
        $currentPin = $_POST['current_pin'] ?? '';
        $newPin = $_POST['new_pin'] ?? '';
        $confirmPin = $_POST['confirm_pin'] ?? '';

        $storedHash = getSetting('pin_hash', '');

        if (!password_verify($currentPin, $storedHash)) {
            $error = 'Current PIN is incorrect.';
        } elseif (strlen($newPin) < 4) {
            $error = 'New PIN must be at least 4 characters.';
        } elseif ($newPin !== $confirmPin) {
            $error = 'New PINs do not match.';
        } else {
            setSetting('pin_hash', password_hash($newPin, PASSWORD_DEFAULT));
            logAudit('settings', null, 'update', ['section' => 'admin_pin_change']);
            $success = 'PIN changed successfully!';
        }
        $tab = 'security';
    }

    if ($formAction === 'estimator') {
        $estimatorEnabled = isset($_POST['estimator_enabled']) ? '1' : '0';
        setSetting('estimator_enabled', $estimatorEnabled);

        if ($estimatorEnabled === '1') {
            $estimatorPin = $_POST['estimator_pin'] ?? '';
            $estimatorPinConfirm = $_POST['estimator_pin_confirm'] ?? '';
            $existingHash = getSetting('estimator_pin_hash', '');

            // Only update PIN if a new one was entered
            if (!empty($estimatorPin)) {
                if (strlen($estimatorPin) < 4) {
                    $error = 'Estimator PIN must be at least 4 characters.';
                } elseif ($estimatorPin !== $estimatorPinConfirm) {
                    $error = 'Estimator PINs do not match.';
                } else {
                    // Ensure estimator PIN is different from admin PIN
                    $adminHash = getSetting('pin_hash', '');
                    if ($adminHash && password_verify($estimatorPin, $adminHash)) {
                        $error = 'Estimator PIN must be different from the Admin PIN.';
                    } else {
                        setSetting('estimator_pin_hash', password_hash($estimatorPin, PASSWORD_DEFAULT));
                        $success = 'Estimator user settings saved!';
                    }
                }
            } elseif (empty($existingHash)) {
                $error = 'Please set a PIN for the Estimator user.';
            } else {
                $success = 'Estimator user settings saved!';
            }
        } else {
            $success = 'Estimator user disabled.';
        }
        $tab = 'security';
    }
}

$settings = getSettings();
$pricingByCategory = getPricingByCategory();

include __DIR__ . '/includes/header.php';
?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<!-- Settings Tabs -->
<div class="tabs">
    <a href="?tab=business" class="tab <?= $tab === 'business' ? 'active' : '' ?>">
        <span class="material-icons-round">business</span> Business
    </a>
    <a href="?tab=pricing" class="tab <?= $tab === 'pricing' ? 'active' : '' ?>">
        <span class="material-icons-round">attach_money</span> Pricing
    </a>
    <a href="?tab=security" class="tab <?= $tab === 'security' ? 'active' : '' ?>">
        <span class="material-icons-round">lock</span> Security
    </a>
</div>

<?php if ($tab === 'business'): ?>
    <form method="POST" class="form-narrow">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="business">

        <div class="form-card">
            <div class="form-card-header">
                <h3>Business Information</h3>
            </div>
            <div class="form-card-body">
                <div class="form-group">
                    <label for="business_name">Business Name</label>
                    <input type="text" id="business_name" name="business_name" 
                           value="<?= e($settings['business_name'] ?? '') ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="business_phone">Phone</label>
                        <input type="tel" id="business_phone" name="business_phone" 
                               value="<?= e($settings['business_phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="business_email">Email</label>
                        <input type="email" id="business_email" name="business_email" 
                               value="<?= e($settings['business_email'] ?? '') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="business_address">Address</label>
                    <textarea id="business_address" name="business_address" rows="2"><?= e($settings['business_address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header">
                <h3>Estimate Settings</h3>
            </div>
            <div class="form-card-body">
                <div class="form-row form-row-3">
                    <div class="form-group">
                        <label for="currency_symbol">Currency Symbol</label>
                        <input type="text" id="currency_symbol" name="currency_symbol" 
                               maxlength="5" value="<?= e($settings['currency_symbol'] ?? '$') ?>">
                    </div>
                    <div class="form-group">
                        <label for="measurement_unit">Unit of Measure</label>
                        <select id="measurement_unit" name="measurement_unit">
                            <option value="ft" <?= ($settings['measurement_unit'] ?? '') === 'ft' ? 'selected' : '' ?>>Feet (ft)</option>
                            <option value="m" <?= ($settings['measurement_unit'] ?? '') === 'm' ? 'selected' : '' ?>>Meters (m)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tax_rate">Default Tax Rate (%)</label>
                        <input type="number" id="tax_rate" name="tax_rate" step="0.25" min="0" max="30"
                               value="<?= e($settings['tax_rate'] ?? '7') ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="estimate_prefix">Estimate Number Prefix</label>
                        <input type="text" id="estimate_prefix" name="estimate_prefix" maxlength="10"
                               value="<?= e($settings['estimate_prefix'] ?? 'EST') ?>">
                    </div>
                    <div class="form-group">
                        <label for="estimate_validity_days">Validity (days)</label>
                        <input type="number" id="estimate_validity_days" name="estimate_validity_days" min="1" max="365"
                               value="<?= e($settings['estimate_validity_days'] ?? '30') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="estimate_terms">Terms & Conditions</label>
                    <textarea id="estimate_terms" name="estimate_terms" rows="4"><?= e($settings['estimate_terms'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons-round">save</span> Save Settings
            </button>
        </div>
    </form>

<?php elseif ($tab === 'pricing'): ?>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="pricing">

        <p class="form-help">Adjust the unit prices below. These are used to auto-calculate estimates.</p>

        <?php foreach ($pricingByCategory as $category => $items): ?>
            <div class="form-card">
                <div class="form-card-header" onclick="toggleSection('pricing-<?= e($category) ?>')">
                    <h3><?= ucfirst(e($category)) ?></h3>
                    <span class="material-icons-round section-toggle">expand_less</span>
                </div>
                <div class="form-card-body" id="section-pricing-<?= e($category) ?>-body">
                    <div class="pricing-table">
                        <?php foreach ($items as $item): ?>
                            <div class="pricing-row">
                                <div class="pricing-info">
                                    <span class="pricing-label"><?= e($item['item_label']) ?></span>
                                    <span class="pricing-desc"><?= e($item['description'] ?? '') ?></span>
                                </div>
                                <div class="pricing-input">
                                    <input type="hidden" name="price_id[]" value="<?= $item['id'] ?>">
                                    <div class="input-with-prefix">
                                        <span><?= e($settings['currency_symbol'] ?? '$') ?></span>
                                        <input type="number" name="price_value[]" step="0.01" min="0"
                                               value="<?= e($item['unit_price']) ?>">
                                    </div>
                                    <span class="pricing-unit">/ <?= e($item['unit']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons-round">save</span> Save Pricing
            </button>
        </div>
    </form>

<?php elseif ($tab === 'security'): ?>
    <form method="POST" class="form-narrow">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="pin">

        <div class="form-card">
            <div class="form-card-header">
                <h3>Change Access PIN</h3>
            </div>
            <div class="form-card-body">
                <div class="form-group">
                    <label for="current_pin">Current PIN</label>
                    <input type="password" id="current_pin" name="current_pin" required 
                           inputmode="numeric">
                </div>
                <div class="form-group">
                    <label for="new_pin">New PIN <small>(min 4 characters)</small></label>
                    <input type="password" id="new_pin" name="new_pin" required minlength="4"
                           inputmode="numeric">
                </div>
                <div class="form-group">
                    <label for="confirm_pin">Confirm New PIN</label>
                    <input type="password" id="confirm_pin" name="confirm_pin" required 
                           inputmode="numeric">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons-round">lock</span> Change PIN
            </button>
        </div>
    </form>

    <!-- Estimator User Section -->
    <form method="POST" class="form-narrow" style="margin-top: 2rem;">
        <?= csrfField() ?>
        <input type="hidden" name="form_action" value="estimator">

        <div class="form-card">
            <div class="form-card-header">
                <h3>Estimator User</h3>
            </div>
            <div class="form-card-body">
                <p class="form-help">Enable a second user with a separate PIN. The Estimator user can create estimates and manage clients but cannot access Settings.</p>
                <div class="form-group">
                    <label class="toggle-label">
                        <input type="checkbox" name="estimator_enabled" value="1"
                               <?= ($settings['estimator_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                               onchange="document.getElementById('estimator-pin-fields').style.display = this.checked ? 'block' : 'none'">
                        <span>Enable Estimator User</span>
                    </label>
                </div>
                <div id="estimator-pin-fields" style="display: <?= ($settings['estimator_enabled'] ?? '0') === '1' ? 'block' : 'none' ?>">
                    <div class="form-group">
                        <label for="estimator_pin">Estimator PIN <small>(min 4 characters<?= !empty($settings['estimator_pin_hash'] ?? '') ? ' — leave blank to keep current' : '' ?>)</small></label>
                        <input type="password" id="estimator_pin" name="estimator_pin" minlength="4"
                               inputmode="numeric" placeholder="Enter Estimator PIN">
                    </div>
                    <div class="form-group">
                        <label for="estimator_pin_confirm">Confirm Estimator PIN</label>
                        <input type="password" id="estimator_pin_confirm" name="estimator_pin_confirm"
                               inputmode="numeric" placeholder="Confirm Estimator PIN">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <span class="material-icons-round">save</span> Save Estimator Settings
            </button>
        </div>
    </form>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
