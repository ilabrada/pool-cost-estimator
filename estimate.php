<?php
/**
 * Estimate Form - Create, Edit, View
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
$duplicate = isset($_GET['duplicate']);
$estimate = null;
$items = [];

// Handle delete
if (isset($_GET['delete']) && $id > 0) {
    if (deleteEstimate($id)) {
        header('Location: dashboard.php?msg=deleted');
    } else {
        header('Location: dashboard.php?msg=error');
    }
    exit;
}

// Handle duplicate
if ($duplicate && $id > 0) {
    $newId = duplicateEstimate($id);
    if ($newId) {
        header('Location: estimate.php?id=' . $newId . '&msg=duplicated');
        exit;
    }
}

// Load existing estimate
if ($id > 0) {
    $estimate = getEstimate($id);
    if (!$estimate) {
        header('Location: dashboard.php?msg=not_found');
        exit;
    }
    $items = $estimate['items'] ?? [];
}

// Handle form save via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST[CSRF_TOKEN_NAME] ?? '';
    if (!verifyCSRFToken($csrf)) {
        die('Invalid security token. Please try again.');
    }

    // Collect estimate data
    $data = [
        'id'                => (int)($_POST['estimate_id'] ?? 0),
        'client_id'         => !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null,
        'pool_length'       => (float)($_POST['pool_length'] ?? 0),
        'pool_width'        => (float)($_POST['pool_width'] ?? 0),
        'pool_depth_shallow'=> (float)($_POST['pool_depth_shallow'] ?? 0),
        'pool_depth_deep'   => (float)($_POST['pool_depth_deep'] ?? 0),
        'pool_shape'        => $_POST['pool_shape'] ?? 'rectangular',
        'pool_material'     => $_POST['pool_material'] ?? 'concrete',
        'interior_finish'   => $_POST['interior_finish'] ?? 'plaster',
        'has_jacuzzi'       => isset($_POST['has_jacuzzi']) ? 1 : 0,
        'jacuzzi_size'      => $_POST['jacuzzi_size'] ?? 'standard',
        'num_lights'        => (int)($_POST['num_lights'] ?? 0),
        'has_heating'       => isset($_POST['has_heating']) ? 1 : 0,
        'heating_type'      => $_POST['heating_type'] ?? 'gas',
        'has_waterfall'     => isset($_POST['has_waterfall']) ? 1 : 0,
        'has_water_feature' => isset($_POST['has_water_feature']) ? 1 : 0,
        'has_auto_cover'    => isset($_POST['has_auto_cover']) ? 1 : 0,
        'has_pool_cleaner'  => isset($_POST['has_pool_cleaner']) ? 1 : 0,
        'has_deck'          => isset($_POST['has_deck']) ? 1 : 0,
        'deck_material'     => $_POST['deck_material'] ?? 'concrete',
        'deck_area'         => (float)($_POST['deck_area'] ?? 0),
        'has_fence'         => isset($_POST['has_fence']) ? 1 : 0,
        'fence_type'        => $_POST['fence_type'] ?? 'aluminum',
        'fence_length'      => (float)($_POST['fence_length'] ?? 0),
        'subtotal'          => (float)($_POST['subtotal'] ?? 0),
        'tax_rate'          => (float)($_POST['tax_rate'] ?? 0),
        'tax_amount'        => (float)($_POST['tax_amount'] ?? 0),
        'discount_percent'  => (float)($_POST['discount_percent'] ?? 0),
        'discount_amount'   => (float)($_POST['discount_amount'] ?? 0),
        'total'             => (float)($_POST['total'] ?? 0),
        'notes'             => trim($_POST['notes'] ?? ''),
        'internal_notes'    => trim($_POST['internal_notes'] ?? ''),
        'status'            => $_POST['status'] ?? 'draft',
    ];

    // If existing estimate, keep the estimate_number
    if ($data['id'] > 0) {
        $existing = getEstimate($data['id']);
        $data['estimate_number'] = $existing['estimate_number'];
        $data['valid_until'] = $existing['valid_until'];
    }

    // Save new client if provided
    if (empty($data['client_id']) && !empty($_POST['client_name'])) {
        $clientData = [
            'name'  => trim($_POST['client_name']),
            'phone' => trim($_POST['client_phone'] ?? ''),
            'email' => trim($_POST['client_email'] ?? ''),
            'address' => trim($_POST['client_address'] ?? ''),
        ];
        $data['client_id'] = saveClient($clientData);
    }

    // Collect line items
    $lineItems = [];
    if (!empty($_POST['item_description'])) {
        foreach ($_POST['item_description'] as $i => $desc) {
            if (empty($desc)) continue;
            $lineItems[] = [
                'category'    => $_POST['item_category'][$i] ?? 'general',
                'description' => $desc,
                'quantity'    => (float)($_POST['item_quantity'][$i] ?? 1),
                'unit'        => $_POST['item_unit'][$i] ?? 'each',
                'unit_price'  => (float)($_POST['item_unit_price'][$i] ?? 0),
                'total'       => (float)($_POST['item_total'][$i] ?? 0),
                'sort_order'  => $i,
                'is_custom'   => (int)($_POST['item_is_custom'][$i] ?? 0),
            ];
        }
    }

    $estimateId = saveEstimate($data, $lineItems);

    $redirect = 'estimate.php?id=' . $estimateId . '&msg=saved';
    header('Location: ' . $redirect);
    exit;
}

$pageTitle = $id ? 'Edit Estimate #' . ($estimate['estimate_number'] ?? '') : 'New Estimate';
$pageTitleKey = $id ? 'page_edit_estimate' : 'page_new_estimate';
$pricing = getPricingByKey();
$pricingJson = json_encode($pricing);
$settings = getSettings();
$settingsJson = json_encode([
    'tax_rate' => (float)($settings['tax_rate'] ?? 7),
    'currency_symbol' => $settings['currency_symbol'] ?? '$',
    'measurement_unit' => $settings['measurement_unit'] ?? 'ft',
]);

// Compute pricing factor for the loaded client (used silently in JS)
$pricingFactor = 1.0;
$clientIdForFactor = (int)($estimate['client_id'] ?? $_GET['client_id'] ?? 0);
if ($clientIdForFactor > 0) {
    $clientForFactor = getClient($clientIdForFactor);
    if ($clientForFactor && ($clientForFactor['tier'] ?? 'priority') === 'standard') {
        $discountPct = (float)getSetting('standard_tier_discount', '0');
        $pricingFactor = round(1.0 - ($discountPct / 100.0), 6);
    }
}

include __DIR__ . '/includes/header.php';
?>

<?php if (isset($_GET['msg'])): ?>
    <script>document.addEventListener('DOMContentLoaded', () => { 
        showToast('<?= $_GET['msg'] === 'saved' ? 'Estimate saved successfully!' : 'Estimate duplicated!' ?>', 'success'); 
    });</script>
<?php endif; ?>

<form id="estimate-form" method="POST" action="estimate.php">
    <?= csrfField() ?>
    <input type="hidden" name="estimate_id" value="<?= $id ?>">
    <input type="hidden" name="subtotal" id="input-subtotal" value="<?= e($estimate['subtotal'] ?? '0') ?>">
    <input type="hidden" name="tax_amount" id="input-tax-amount" value="<?= e($estimate['tax_amount'] ?? '0') ?>">
    <input type="hidden" name="discount_amount" id="input-discount-amount" value="<?= e($estimate['discount_amount'] ?? '0') ?>">
    <input type="hidden" name="total" id="input-total" value="<?= e($estimate['total'] ?? '0') ?>">

    <div class="form-layout">
        <!-- Left Column: Form Fields -->
        <div class="form-main">

            <!-- Client Section -->
            <div class="form-card" id="section-client">
                <div class="form-card-header" onclick="toggleSection('client')">
                    <h3><span class="material-icons-round">person</span> <span data-i18n="section_client">Client Information</span></h3>
                    <span class="material-icons-round section-toggle">expand_less</span>
                </div>
                <div class="form-card-body">
                    <input type="hidden" name="client_id" id="client-id" value="<?= e($estimate['client_id'] ?? '') ?>">
                    <div class="form-row">
                        <div class="form-group form-group-grow">
                            <label for="client-search" data-i18n="label_client_name">Client Name *</label>
                            <div class="autocomplete-wrapper">
                                <input type="text" id="client-search" name="client_name" 
                                       data-i18n-placeholder="placeholder_client"
                                       placeholder="Search or type new client name..."
                                       value="<?= e($estimate['client_name'] ?? '') ?>"
                                       autocomplete="off" required>
                                <div class="autocomplete-results" id="client-results"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="client-phone" data-i18n="label_phone">Phone</label>
                            <input type="tel" id="client-phone" name="client_phone" 
                                   placeholder="(555) 123-4567"
                                   value="<?= e($estimate['client_phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label for="client-email" data-i18n="label_email">Email</label>
                            <input type="email" id="client-email" name="client_email" 
                                   placeholder="client@email.com"
                                   value="<?= e($estimate['client_email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="client-address" data-i18n="label_address">Address</label>
                        <input type="text" id="client-address" name="client_address" 
                               data-i18n-placeholder="placeholder_address"
                               placeholder="Project address"
                               value="<?= e($estimate['client_address'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Pool Dimensions -->
            <div class="form-card" id="section-dimensions">
                <div class="form-card-header" onclick="toggleSection('dimensions')">
                    <h3><span class="material-icons-round">straighten</span> <span data-i18n="section_dimensions">Pool Dimensions</span></h3>
                    <span class="material-icons-round section-toggle">expand_less</span>
                </div>
                <div class="form-card-body">
                    <div class="form-row form-row-4">
                        <div class="form-group">
                            <label for="pool-length" data-i18n="label_length">Length (<?= e($settings['measurement_unit'] ?? 'ft') ?>)</label>
                            <input type="number" id="pool-length" name="pool_length" 
                                   step="0.1" min="0" placeholder="30"
                                   value="<?= e($estimate['pool_length'] ?? '') ?>"
                                   oninput="recalculate()">
                        </div>
                        <div class="form-group">
                            <label for="pool-width" data-i18n="label_width">Width (<?= e($settings['measurement_unit'] ?? 'ft') ?>)</label>
                            <input type="number" id="pool-width" name="pool_width" 
                                   step="0.1" min="0" placeholder="15"
                                   value="<?= e($estimate['pool_width'] ?? '') ?>"
                                   oninput="recalculate()">
                        </div>
                        <div class="form-group">
                            <label for="pool-depth-shallow" data-i18n="label_shallow_depth">Shallow Depth</label>
                            <input type="number" id="pool-depth-shallow" name="pool_depth_shallow" 
                                   step="0.1" min="0" placeholder="3.5"
                                   value="<?= e($estimate['pool_depth_shallow'] ?? '') ?>"
                                   oninput="recalculate()">
                        </div>
                        <div class="form-group">
                            <label for="pool-depth-deep" data-i18n="label_deep_end">Deep End</label>
                            <input type="number" id="pool-depth-deep" name="pool_depth_deep" 
                                   step="0.1" min="0" placeholder="6"
                                   value="<?= e($estimate['pool_depth_deep'] ?? '') ?>"
                                   oninput="recalculate()">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="pool-shape" data-i18n="label_pool_shape">Pool Shape</label>
                            <select id="pool-shape" name="pool_shape" onchange="recalculate()">
                                <option value="rectangular" data-i18n="shape_rectangular" <?= ($estimate['pool_shape'] ?? '') === 'rectangular' ? 'selected' : '' ?>>Rectangular</option>
                                <option value="l-shaped" data-i18n="shape_l_shaped" <?= ($estimate['pool_shape'] ?? '') === 'l-shaped' ? 'selected' : '' ?>>L-Shaped</option>
                                <option value="kidney" data-i18n="shape_kidney" <?= ($estimate['pool_shape'] ?? '') === 'kidney' ? 'selected' : '' ?>>Kidney</option>
                                <option value="oval" data-i18n="shape_oval" <?= ($estimate['pool_shape'] ?? '') === 'oval' ? 'selected' : '' ?>>Oval</option>
                                <option value="freeform" data-i18n="shape_freeform" <?= ($estimate['pool_shape'] ?? '') === 'freeform' ? 'selected' : '' ?>>Freeform</option>
                            </select>
                            <a href="#" class="shape-preview-link" onclick="openShapePreview(); return false;">
                                <span class="material-icons-round">image</span> <span data-i18n="view_shape_example">View shape example</span>
                            </a>
                        </div>
                    </div>
                    <!-- Calculated Metrics Display -->
                    <div class="pool-metrics" id="pool-metrics">
                        <div class="metric"><span class="metric-label" data-i18n="metric_surface_area">Surface Area:</span> <span id="metric-surface">0</span> <span data-i18n="unit_sq_ft">sq ft</span></div>
                        <div class="metric"><span class="metric-label" data-i18n="metric_volume">Volume:</span> <span id="metric-volume">0</span> <span data-i18n="unit_gallons">gallons</span></div>
                        <div class="metric"><span class="metric-label" data-i18n="metric_perimeter">Perimeter:</span> <span id="metric-perimeter">0</span> <span data-i18n="unit_ft">ft</span></div>
                    </div>
                </div>
            </div>

            <!-- Pool Construction -->
            <div class="form-card" id="section-construction">
                <div class="form-card-header" onclick="toggleSection('construction')">
                    <h3><span class="material-icons-round">construction</span> <span data-i18n="section_construction">Pool Construction</span></h3>
                    <span class="material-icons-round section-toggle">expand_less</span>
                </div>
                <div class="form-card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label data-i18n="label_pool_material">Pool Material</label>
                            <input type="hidden" id="pool-material" name="pool_material" value="<?= e($estimate['pool_material'] ?? 'concrete') ?>">
                            <div class="form-value-label"><?= e($pricing['shell_' . ($estimate['pool_material'] ?? 'concrete')]['item_label'] ?? 'Concrete/Gunite Shell') ?></div>
                        </div>
                        <div class="form-group">
                            <label for="interior-finish" data-i18n="label_interior_finish">Interior Finish</label>
                            <select id="interior-finish" name="interior_finish" onchange="recalculate()">
                                <option value="plaster" data-i18n="finish_plaster" <?= ($estimate['interior_finish'] ?? '') === 'plaster' ? 'selected' : '' ?>>Standard Plaster</option>
                                <option value="pebble" data-i18n="finish_pebble" <?= ($estimate['interior_finish'] ?? '') === 'pebble' ? 'selected' : '' ?>>Pebble (PebbleTec)</option>
                                <option value="quartz" data-i18n="finish_quartz" <?= ($estimate['interior_finish'] ?? '') === 'quartz' ? 'selected' : '' ?>>Quartz</option>
                                <option value="tile" data-i18n="finish_tile" <?= ($estimate['interior_finish'] ?? '') === 'tile' ? 'selected' : '' ?>>Full Tile</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features & Add-ons -->
            <div class="form-card" id="section-features">
                <div class="form-card-header" onclick="toggleSection('features')">
                    <h3><span class="material-icons-round">pool</span> <span data-i18n="section_features">Features &amp; Add-ons</span></h3>
                    <span class="material-icons-round section-toggle">expand_less</span>
                </div>
                <div class="form-card-body">
                    <div class="feature-grid">
                        <!-- Jacuzzi -->
                        <div class="feature-item">
                            <label class="toggle-switch">
                                <input type="checkbox" name="has_jacuzzi" id="has-jacuzzi" 
                                       <?= !empty($estimate['has_jacuzzi']) ? 'checked' : '' ?>
                                       onchange="toggleFeatureOptions('jacuzzi'); recalculate()">
                                <span class="toggle-slider"></span>
                                <span class="toggle-label" data-i18n="feature_jacuzzi">Spa / Jacuzzi</span>
                            </label>
                            <div class="feature-options" id="options-jacuzzi" style="<?= empty($estimate['has_jacuzzi']) ? 'display:none' : '' ?>">
                                <select name="jacuzzi_size" onchange="recalculate()">
                                    <option value="standard" data-i18n="jacuzzi_standard" <?= ($estimate['jacuzzi_size'] ?? '') === 'standard' ? 'selected' : '' ?>>Standard (6-8 person)</option>
                                    <option value="large" data-i18n="jacuzzi_large" <?= ($estimate['jacuzzi_size'] ?? '') === 'large' ? 'selected' : '' ?>>Large (8-12 person)</option>
                                </select>
                            </div>
                        </div>

                        <!-- LED Lights -->
                        <div class="feature-item">
                            <label class="toggle-switch">
                                <input type="checkbox" id="has-lights" 
                                       <?= ($estimate['num_lights'] ?? 0) > 0 ? 'checked' : '' ?>
                                       onchange="toggleFeatureOptions('lights'); recalculate()">
                                <span class="toggle-slider"></span>
                                <span class="toggle-label" data-i18n="feature_led_lighting">LED Lighting</span>
                            </label>
                            <div class="feature-options" id="options-lights" style="<?= ($estimate['num_lights'] ?? 0) == 0 ? 'display:none' : '' ?>">
                                <label data-i18n="label_num_lights">Number of lights</label>
                                <input type="number" name="num_lights" id="num-lights" min="1" max="20" value="<?= e($estimate['num_lights'] ?? '2') ?>" onchange="recalculate()">
                            </div>
                        </div>

                        <!-- Heating -->
                        <div class="feature-item">
                            <label class="toggle-switch">
                                <input type="checkbox" name="has_heating" id="has-heating" 
                                       <?= !empty($estimate['has_heating']) ? 'checked' : '' ?>
                                       onchange="toggleFeatureOptions('heating'); recalculate()">
                                <span class="toggle-slider"></span>
                                <span class="toggle-label" data-i18n="feature_heating">Pool Heating</span>
                            </label>
                            <div class="feature-options" id="options-heating" style="<?= empty($estimate['has_heating']) ? 'display:none' : '' ?>">
                                <select name="heating_type" onchange="recalculate()">
                                    <option value="gas" data-i18n="heating_gas" <?= ($estimate['heating_type'] ?? '') === 'gas' ? 'selected' : '' ?>>Gas Heater</option>
                                    <option value="heatpump" data-i18n="heating_heatpump" <?= ($estimate['heating_type'] ?? '') === 'heatpump' ? 'selected' : '' ?>>Heat Pump</option>
                                    <option value="solar" data-i18n="heating_solar" <?= ($estimate['heating_type'] ?? '') === 'solar' ? 'selected' : '' ?>>Solar</option>
                                </select>
                            </div>
                        </div>

                        <!-- Waterfall -->
                        <div class="feature-item">
                            <label class="toggle-switch">
                                <input type="checkbox" name="has_waterfall" 
                                       <?= !empty($estimate['has_waterfall']) ? 'checked' : '' ?>
                                       onchange="recalculate()">
                                <span class="toggle-slider"></span>
                                <span class="toggle-label" data-i18n="feature_waterfall">Rock Waterfall</span>
                            </label>
                        </div>

                        <!-- Water Feature -->
                        <div class="feature-item">
                            <label class="toggle-switch">
                                <input type="checkbox" name="has_water_feature" 
                                       <?= !empty($estimate['has_water_feature']) ? 'checked' : '' ?>
                                       onchange="recalculate()">
                                <span class="toggle-slider"></span>
                                <span class="toggle-label" data-i18n="feature_water_feature">Fountain / Scupper</span>
                            </label>
                        </div>

                        <!-- Auto Cover -->
                        <div class="feature-item">
                            <label class="toggle-switch">
                                <input type="checkbox" name="has_auto_cover" 
                                       <?= !empty($estimate['has_auto_cover']) ? 'checked' : '' ?>
                                       onchange="recalculate()">
                                <span class="toggle-slider"></span>
                                <span class="toggle-label" data-i18n="feature_auto_cover">Automatic Cover</span>
                            </label>
                        </div>

                        <!-- Pool Cleaner -->
                        <div class="feature-item">
                            <label class="toggle-switch">
                                <input type="checkbox" name="has_pool_cleaner" 
                                       <?= !empty($estimate['has_pool_cleaner']) ? 'checked' : '' ?>
                                       onchange="recalculate()">
                                <span class="toggle-slider"></span>
                                <span class="toggle-label" data-i18n="feature_pool_cleaner">Automatic Cleaner</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Deck & Surroundings -->
            <div class="form-card" id="section-deck">
                <div class="form-card-header" onclick="toggleSection('deck')">
                    <h3><span class="material-icons-round">deck</span> <span data-i18n="section_deck">Deck &amp; Surroundings</span></h3>
                    <span class="material-icons-round section-toggle">expand_less</span>
                </div>
                <div class="form-card-body">
                    <!-- Deck -->
                    <div class="feature-item">
                        <label class="toggle-switch">
                            <input type="checkbox" name="has_deck" id="has-deck" 
                                   <?= !empty($estimate['has_deck']) ? 'checked' : '' ?>
                                   onchange="toggleFeatureOptions('deck'); recalculate()">
                            <span class="toggle-slider"></span>
                            <span class="toggle-label" data-i18n="feature_deck">Pool Deck</span>
                        </label>
                        <div class="feature-options" id="options-deck" style="<?= empty($estimate['has_deck']) ? 'display:none' : '' ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label data-i18n="label_deck_material">Deck Material</label>
                                    <select name="deck_material" onchange="recalculate()">
                                        <option value="concrete" data-i18n="deck_concrete" <?= ($estimate['deck_material'] ?? '') === 'concrete' ? 'selected' : '' ?>>Standard Concrete</option>
                                        <option value="stamped" data-i18n="deck_stamped" <?= ($estimate['deck_material'] ?? '') === 'stamped' ? 'selected' : '' ?>>Stamped Concrete</option>
                                        <option value="pavers" data-i18n="deck_pavers" <?= ($estimate['deck_material'] ?? '') === 'pavers' ? 'selected' : '' ?>>Pavers</option>
                                        <option value="travertine" data-i18n="deck_travertine" <?= ($estimate['deck_material'] ?? '') === 'travertine' ? 'selected' : '' ?>>Travertine</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label data-i18n="label_deck_area">Deck Area (sq ft)</label>
                                    <input type="number" name="deck_area" id="deck-area" 
                                           step="1" min="0" placeholder="400"
                                           value="<?= e($estimate['deck_area'] ?? '') ?>"
                                           oninput="recalculate()">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fence -->
                    <div class="feature-item" style="margin-top: 1rem;">
                        <label class="toggle-switch">
                            <input type="checkbox" name="has_fence" id="has-fence" 
                                   <?= !empty($estimate['has_fence']) ? 'checked' : '' ?>
                                   onchange="toggleFeatureOptions('fence'); recalculate()">
                            <span class="toggle-slider"></span>
                            <span class="toggle-label" data-i18n="feature_fence">Pool Fence</span>
                        </label>
                        <div class="feature-options" id="options-fence" style="<?= empty($estimate['has_fence']) ? 'display:none' : '' ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label data-i18n="label_fence_type">Fence Type</label>
                                    <select name="fence_type" onchange="recalculate()">
                                        <option value="aluminum" data-i18n="fence_aluminum" <?= ($estimate['fence_type'] ?? '') === 'aluminum' ? 'selected' : '' ?>>Aluminum</option>
                                        <option value="glass" data-i18n="fence_glass" <?= ($estimate['fence_type'] ?? '') === 'glass' ? 'selected' : '' ?>>Glass Panel</option>
                                        <option value="mesh" data-i18n="fence_mesh" <?= ($estimate['fence_type'] ?? '') === 'mesh' ? 'selected' : '' ?>>Mesh Safety</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label data-i18n="label_fence_length">Fence Length (lin ft)</label>
                                    <input type="number" name="fence_length" id="fence-length" 
                                           step="1" min="0" placeholder="100"
                                           value="<?= e($estimate['fence_length'] ?? '') ?>"
                                           oninput="recalculate()">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Line Items -->
            <div class="form-card" id="section-custom">
                <div class="form-card-header" onclick="toggleSection('custom')">
                    <h3><span class="material-icons-round">playlist_add</span> <span data-i18n="section_custom">Custom Items</span></h3>
                    <span class="material-icons-round section-toggle">expand_less</span>
                </div>
                <div class="form-card-body">
                    <p class="form-help" data-i18n="custom_help">Add custom line items for anything not covered above.</p>
                    <div id="custom-items-list">
                        <?php
                        $customItems = array_filter($items, fn($item) => !empty($item['is_custom']));
                        if (!empty($customItems)):
                            foreach ($customItems as $ci): ?>
                            <div class="custom-item-row">
                                <input type="hidden" name="item_category[]" value="custom">
                                <input type="hidden" name="item_is_custom[]" value="1">
                                <input type="hidden" name="item_unit[]" value="each">
                                <div class="form-row">
                                    <div class="form-group form-group-grow">
                                        <input type="text" name="item_description[]" data-i18n-placeholder="placeholder_description" placeholder="Description" value="<?= e($ci['description']) ?>">
                                    </div>
                                    <div class="form-group" style="width: 80px;">
                                        <input type="number" name="item_quantity[]" placeholder="Qty" min="1" step="1" value="<?= e($ci['quantity']) ?>" oninput="recalculate()">
                                    </div>
                                    <div class="form-group" style="width: 120px;">
                                        <input type="number" name="item_unit_price[]" placeholder="Unit Price" min="0" step="0.01" value="<?= e($ci['unit_price']) ?>" oninput="recalculate()">
                                    </div>
                                    <div class="form-group" style="width: 120px;">
                                        <input type="number" name="item_total[]" readonly class="item-line-total" value="<?= e($ci['total']) ?>">
                                    </div>
                                    <button type="button" class="btn-icon btn-danger-icon" onclick="removeCustomItem(this)">
                                        <span class="material-icons-round">close</span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    <button type="button" class="btn btn-outline btn-sm" onclick="addCustomItem()">
                        <span class="material-icons-round">add</span> <span data-i18n="btn_add_item">Add Item</span>
                    </button>
                </div>
            </div>

            <!-- Notes -->
            <div class="form-card" id="section-notes">
                <div class="form-card-header" onclick="toggleSection('notes')">
                    <h3><span class="material-icons-round">notes</span> <span data-i18n="section_notes">Notes</span></h3>
                    <span class="material-icons-round section-toggle">expand_less</span>
                </div>
                <div class="form-card-body">
                    <div class="form-group">
                        <label for="notes" data-i18n="label_notes_client">Notes for Client</label>
                        <textarea id="notes" name="notes" rows="3" data-i18n-placeholder="placeholder_notes" placeholder="Notes that will appear on the estimate..."><?= e($estimate['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="internal-notes" data-i18n="label_internal_notes">Internal Notes <small data-i18n="hint_internal_notes">(not shown on estimate)</small></label>
                        <textarea id="internal-notes" name="internal_notes" rows="2" data-i18n-placeholder="placeholder_internal" placeholder="Private notes..."><?= e($estimate['internal_notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Summary -->
        <div class="form-sidebar">
            <div class="summary-card sticky">
                <h3 data-i18n="cost_summary">Cost Summary</h3>

                <!-- Auto-calculated line items -->
                <div id="summary-items" class="summary-items">
                    <p class="summary-empty" data-i18n="summary_empty">Enter pool dimensions to see cost breakdown</p>
                </div>

                <div class="summary-totals">
                    <div class="summary-line">
                        <span data-i18n="label_subtotal">Subtotal</span>
                        <span id="summary-subtotal">$0.00</span>
                    </div>
                    <div class="summary-line">
                        <label class="summary-line-input">
                            <span data-i18n="label_discount">Discount</span>
                            <div class="input-with-suffix">
                                <input type="number" name="discount_percent" id="discount-percent" 
                                       min="0" max="100" step="0.5" value="<?= e($estimate['discount_percent'] ?? '0') ?>"
                                       oninput="recalculate()" style="width: 60px;">
                                <span>%</span>
                            </div>
                        </label>
                        <span id="summary-discount">-$0.00</span>
                    </div>
                    <div class="summary-line">
                        <label class="summary-line-input">
                            <span data-i18n="label_tax">Tax</span>
                            <div class="input-with-suffix">
                                <input type="number" name="tax_rate" id="tax-rate" 
                                       min="0" max="30" step="0.25" 
                                       value="<?= e($estimate['tax_rate'] ?? ($settings['tax_rate'] ?? '7.00')) ?>"
                                       oninput="recalculate()" style="width: 60px;">
                                <span>%</span>
                            </div>
                        </label>
                        <span id="summary-tax">$0.00</span>
                    </div>
                    <div class="summary-line summary-total">
                        <span data-i18n="label_total">Total</span>
                        <span id="summary-total">$0.00</span>
                    </div>
                </div>

                <!-- Status -->
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="estimate-status" data-i18n="label_status">Status</label>
                    <select name="status" id="estimate-status">
                        <option value="draft" data-i18n="status_draft" <?= ($estimate['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="sent" data-i18n="status_sent" <?= ($estimate['status'] ?? '') === 'sent' ? 'selected' : '' ?>>Sent</option>
                        <option value="approved" data-i18n="status_approved" <?= ($estimate['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" data-i18n="status_rejected" <?= ($estimate['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="summary-actions">
                    <button type="submit" class="btn btn-primary btn-block">
                        <span class="material-icons-round">save</span>
                        <span data-i18n="btn_save_estimate">Save Estimate</span>
                    </button>
                    <?php if ($id > 0): ?>
                        <div class="btn-group-row">
                            <a href="print-estimate.php?id=<?= $id ?>" target="_blank" class="btn btn-outline btn-sm">
                                <span class="material-icons-round">print</span> <span data-i18n="btn_print_pdf">Print / PDF</span>
                            </a>
                            <a href="estimate.php?id=<?= $id ?>&duplicate=1" class="btn btn-outline btn-sm">
                                <span class="material-icons-round">content_copy</span> <span data-i18n="btn_duplicate">Duplicate</span>
                            </a>
                            <button type="button" class="btn btn-outline btn-sm btn-danger-text" 
                                    onclick="confirmDelete(<?= $id ?>, 'estimate')">
                                <span class="material-icons-round">delete</span> <span data-i18n="btn_delete">Delete</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Pool Shape Preview Modal -->
<div class="modal-overlay" id="shape-preview-modal" onclick="closeShapePreview()">
    <div class="modal-dialog shape-preview-dialog" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3 id="shape-preview-title">Pool Shape</h3>
            <button type="button" class="btn-icon shape-preview-close" onclick="closeShapePreview()" title="Close">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="modal-body">
            <button class="gallery-nav gallery-prev" onclick="navigateGallery(-1)" title="Previous">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <img id="shape-preview-img" src="" alt="Pool shape example" class="shape-preview-img">
            <button class="gallery-nav gallery-next" onclick="navigateGallery(1)" title="Next">
                <span class="material-icons-round">chevron_right</span>
            </button>
            <div class="gallery-indicators" id="gallery-indicators"></div>
        </div>
    </div>
</div>

<!-- Mobile floating summary bar -->
<div class="mobile-summary-bar" id="mobile-summary">
    <div class="mobile-summary-total">
        <span data-i18n="mobile_total">Total:</span>
        <span id="mobile-total">$0.00</span>
    </div>
    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('estimate-form').submit()">
        <span class="material-icons-round">save</span> <span data-i18n="btn_save">Save</span>
    </button>
</div>

<!-- Pricing data for JS calculations -->
<script>
    const PRICING = <?= $pricingJson ?>;
    const SETTINGS = <?= $settingsJson ?>;
    const EXISTING_ITEMS = <?= json_encode($items) ?>;
    const PRICING_FACTOR = <?= json_encode($pricingFactor) ?>;
    document.addEventListener('DOMContentLoaded', () => {
        recalculate();
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
