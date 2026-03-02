<?php
/**
 * Print-friendly Estimate View / PDF Generation
 * Opens in a new tab with print-optimized layout
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    die('No estimate specified.');
}

$estimate = getEstimate($id);
if (!$estimate) {
    die('Estimate not found.');
}

$settings = getSettings();
$items = $estimate['items'] ?? [];
$unit = $settings['measurement_unit'] ?? 'ft';
$currency = $settings['currency_symbol'] ?? '$';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimate <?= e($estimate['estimate_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #212529;
            line-height: 1.5;
            padding: 1rem;
            background: #f5f5f5;
        }

        .print-controls {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .print-controls button {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-print { background: #0077B6; color: white; }
        .btn-pdf { background: #06D6A0; color: white; }
        .btn-back { background: #6C757D; color: white; }
        .btn-print:hover { background: #023E8A; }
        .btn-pdf:hover { background: #05b588; }
        .btn-back:hover { background: #565e64; }

        .estimate-document {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* Header */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 3px solid #0077B6;
        }

        .doc-business h1 {
            font-size: 1.5rem;
            color: #0077B6;
            margin-bottom: 0.25rem;
        }

        .doc-business p {
            font-size: 0.85rem;
            color: #6C757D;
            line-height: 1.4;
        }

        .doc-estimate-info {
            text-align: right;
        }

        .doc-estimate-info h2 {
            font-size: 1.75rem;
            color: #212529;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 0.5rem;
        }

        .doc-estimate-info .detail { 
            font-size: 0.85rem; 
            color: #6C757D; 
        }

        .doc-estimate-info .detail strong { color: #212529; }
        .doc-estimate-info .estimate-num { font-size: 1.1rem; font-weight: 700; color: #0077B6; }

        /* Client & Pool Info */
        .doc-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .doc-info-box h3 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #0077B6;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .doc-info-box p {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        /* Items Table */
        .doc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }

        .doc-table thead th {
            background: #f8f9fa;
            padding: 0.75rem;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6C757D;
            border-bottom: 2px solid #dee2e6;
        }

        .doc-table thead th:last-child { text-align: right; }
        .doc-table thead th:nth-child(3),
        .doc-table thead th:nth-child(4) { text-align: right; }

        .doc-table tbody td {
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }

        .doc-table tbody td:last-child { text-align: right; font-weight: 500; }
        .doc-table tbody td:nth-child(3),
        .doc-table tbody td:nth-child(4) { text-align: right; }

        .doc-table tbody tr:nth-child(even) { background: #fafafa; }

        .doc-table .category-row td {
            background: #f0f7fb;
            font-weight: 600;
            color: #0077B6;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.5rem 0.75rem;
        }

        /* Totals */
        .doc-totals {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 2rem;
        }

        .doc-totals-table {
            width: 280px;
        }

        .doc-totals-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.95rem;
        }

        .doc-totals-row.total {
            border-top: 2px solid #212529;
            font-size: 1.2rem;
            font-weight: 700;
            padding-top: 0.75rem;
            margin-top: 0.25rem;
            color: #0077B6;
        }

        /* Pool Specs */
        .doc-specs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .doc-spec {
            text-align: center;
        }

        .doc-spec .spec-value {
            font-size: 1.1rem;
            font-weight: 700;
            color: #0077B6;
        }

        .doc-spec .spec-label {
            font-size: 0.75rem;
            color: #6C757D;
            text-transform: uppercase;
        }

        /* Notes & Terms */
        .doc-notes {
            margin-bottom: 1.5rem;
        }

        .doc-notes h3 {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6C757D;
            margin-bottom: 0.5rem;
        }

        .doc-notes p {
            font-size: 0.85rem;
            color: #495057;
            white-space: pre-line;
        }

        .doc-footer {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid #dee2e6;
            font-size: 0.8rem;
            color: #6C757D;
        }

        .doc-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-draft { background: #e9ecef; color: #6C757D; }
        .status-sent { background: #e3f2fd; color: #0077B6; }
        .status-approved { background: #e8f8f0; color: #06D6A0; }
        .status-rejected { background: #fde8ec; color: #EF476F; }

        /* Print styles */
        @media print {
            body { background: white; padding: 0; }
            .print-controls { display: none !important; }
            .estimate-document { box-shadow: none; border-radius: 0; padding: 0; }
            @page { margin: 1.5cm; }
        }

        /* Mobile responsive */
        @media (max-width: 600px) {
            body { padding: 0.5rem; }
            .estimate-document { padding: 1.25rem; }
            .doc-header { flex-direction: column; gap: 1rem; }
            .doc-estimate-info { text-align: left; }
            .doc-info-grid { grid-template-columns: 1fr; }
            .doc-specs { grid-template-columns: repeat(2, 1fr); }
            .print-controls { flex-wrap: wrap; }
        }
    </style>
    <!-- html2pdf for PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>

<div class="print-controls">
    <button class="btn-back" onclick="window.close()">&#8592; Back</button>
    <button class="btn-print" onclick="window.print()">🖨️ Print</button>
    <button class="btn-pdf" onclick="downloadPDF()">📄 Download PDF</button>
</div>

<div class="estimate-document" id="estimate-pdf">
    <!-- Document Header -->
    <div class="doc-header">
        <div class="doc-business">
            <h1><?= e($settings['business_name'] ?? 'Pool Builder') ?></h1>
            <?php if (!empty($settings['business_phone'])): ?>
                <p>📞 <?= e($settings['business_phone']) ?></p>
            <?php endif; ?>
            <?php if (!empty($settings['business_email'])): ?>
                <p>✉️ <?= e($settings['business_email']) ?></p>
            <?php endif; ?>
            <?php if (!empty($settings['business_address'])): ?>
                <p>📍 <?= e($settings['business_address']) ?></p>
            <?php endif; ?>
        </div>
        <div class="doc-estimate-info">
            <h2>Estimate</h2>
            <div class="estimate-num"><?= e($estimate['estimate_number']) ?></div>
            <div class="detail"><strong>Date:</strong> <?= formatDate($estimate['created_at']) ?></div>
            <?php if ($estimate['valid_until']): ?>
                <div class="detail"><strong>Valid Until:</strong> <?= formatDate($estimate['valid_until']) ?></div>
            <?php endif; ?>
            <div style="margin-top: 0.5rem;">
                <span class="doc-status status-<?= e($estimate['status']) ?>"><?= ucfirst(e($estimate['status'])) ?></span>
            </div>
        </div>
    </div>

    <!-- Client & Pool Info -->
    <div class="doc-info-grid">
        <div class="doc-info-box">
            <h3>Prepared For</h3>
            <p><strong><?= e($estimate['client_name'] ?? 'N/A') ?></strong></p>
            <?php if (!empty($estimate['client_phone'])): ?>
                <p><?= e($estimate['client_phone']) ?></p>
            <?php endif; ?>
            <?php if (!empty($estimate['client_email'])): ?>
                <p><?= e($estimate['client_email']) ?></p>
            <?php endif; ?>
            <?php if (!empty($estimate['client_address'])): ?>
                <p><?= e($estimate['client_address']) ?></p>
            <?php endif; ?>
        </div>
        <div class="doc-info-box">
            <h3>Pool Specifications</h3>
            <p><strong>Dimensions:</strong> <?= e($estimate['pool_length']) ?> × <?= e($estimate['pool_width']) ?> <?= e($unit) ?></p>
            <p><strong>Depth:</strong> <?= e($estimate['pool_depth_shallow']) ?> - <?= e($estimate['pool_depth_deep']) ?> <?= e($unit) ?></p>
            <p><strong>Shape:</strong> <?= ucfirst(e($estimate['pool_shape'])) ?></p>
            <p><strong>Material:</strong> <?= ucfirst(e($estimate['pool_material'])) ?></p>
            <p><strong>Finish:</strong> <?= ucfirst(e($estimate['interior_finish'])) ?></p>
        </div>
    </div>

    <!-- Pool Metrics -->
    <?php $metrics = calculatePoolMetrics($estimate); ?>
    <div class="doc-specs">
        <div class="doc-spec">
            <div class="spec-value"><?= number_format($metrics['surface_area']) ?></div>
            <div class="spec-label">Surface (sq <?= e($unit) ?>)</div>
        </div>
        <div class="doc-spec">
            <div class="spec-value"><?= number_format($metrics['volume_gallons']) ?></div>
            <div class="spec-label">Volume (gal)</div>
        </div>
        <div class="doc-spec">
            <div class="spec-value"><?= number_format($metrics['perimeter'], 1) ?></div>
            <div class="spec-label">Perimeter (<?= e($unit) ?>)</div>
        </div>
        <div class="doc-spec">
            <div class="spec-value"><?= e($metrics['avg_depth']) ?></div>
            <div class="spec-label">Avg Depth (<?= e($unit) ?>)</div>
        </div>
    </div>

    <!-- Features Summary -->
    <?php
    $features = [];
    if ($estimate['has_jacuzzi']) $features[] = 'Spa/Jacuzzi (' . ucfirst($estimate['jacuzzi_size']) . ')';
    if ($estimate['num_lights'] > 0) $features[] = $estimate['num_lights'] . ' LED Light(s)';
    if ($estimate['has_heating']) $features[] = ucfirst($estimate['heating_type']) . ' Heating';
    if ($estimate['has_waterfall']) $features[] = 'Rock Waterfall';
    if ($estimate['has_water_feature']) $features[] = 'Water Feature';
    if ($estimate['has_auto_cover']) $features[] = 'Automatic Cover';
    if ($estimate['has_pool_cleaner']) $features[] = 'Automatic Cleaner';
    if ($estimate['has_deck']) $features[] = ucfirst($estimate['deck_material']) . ' Deck (' . $estimate['deck_area'] . ' sq ft)';
    if ($estimate['has_fence']) $features[] = ucfirst($estimate['fence_type']) . ' Fence (' . $estimate['fence_length'] . ' ft)';
    ?>
    <?php if (!empty($features)): ?>
        <div class="doc-info-box" style="margin-bottom: 1.5rem;">
            <h3>Included Features</h3>
            <p><?= e(implode(' • ', $features)) ?></p>
        </div>
    <?php endif; ?>

    <!-- Cost Breakdown Table -->
    <table class="doc-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $currentCategory = '';
            $categoryLabels = [
                'excavation'  => 'Excavation',
                'shell'       => 'Pool Shell',
                'finish'      => 'Interior Finish',
                'equipment'   => 'Equipment & Plumbing',
                'tile'        => 'Tile & Coping',
                'features'    => 'Features & Add-ons',
                'deck'        => 'Deck & Surroundings',
                'fence'       => 'Fencing',
                'other'       => 'Other',
                'custom'      => 'Custom Items',
                'general'     => 'Additional Items',
            ];
            foreach ($items as $item):
                $cat = $item['category'] ?? 'general';
                if ($cat !== $currentCategory):
                    $currentCategory = $cat;
            ?>
                <tr class="category-row">
                    <td colspan="4"><?= e($categoryLabels[$cat] ?? ucfirst($cat)) ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td><?= e($item['description']) ?></td>
                <td><?= rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.') ?> <?= e($item['unit'] ?? '') ?></td>
                <td><?= $currency . number_format((float)$item['unit_price'], 2) ?></td>
                <td><?= $currency . number_format((float)$item['total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="doc-totals">
        <div class="doc-totals-table">
            <div class="doc-totals-row">
                <span>Subtotal</span>
                <span><?= $currency . number_format((float)$estimate['subtotal'], 2) ?></span>
            </div>
            <?php if ((float)$estimate['discount_amount'] > 0): ?>
                <div class="doc-totals-row">
                    <span>Discount (<?= e($estimate['discount_percent']) ?>%)</span>
                    <span>-<?= $currency . number_format((float)$estimate['discount_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if ((float)$estimate['tax_amount'] > 0): ?>
                <div class="doc-totals-row">
                    <span>Tax (<?= e($estimate['tax_rate']) ?>%)</span>
                    <span><?= $currency . number_format((float)$estimate['tax_amount'], 2) ?></span>
                </div>
            <?php endif; ?>
            <div class="doc-totals-row total">
                <span>Total</span>
                <span><?= $currency . number_format((float)$estimate['total'], 2) ?></span>
            </div>
        </div>
    </div>

    <!-- Notes -->
    <?php if (!empty($estimate['notes'])): ?>
        <div class="doc-notes">
            <h3>Notes</h3>
            <p><?= nl2br(e($estimate['notes'])) ?></p>
        </div>
    <?php endif; ?>

    <!-- Terms -->
    <?php if (!empty($settings['estimate_terms'])): ?>
        <div class="doc-notes">
            <h3>Terms & Conditions</h3>
            <p><?= nl2br(e($settings['estimate_terms'])) ?></p>
        </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="doc-footer">
        <p>Thank you for choosing <strong><?= e($settings['business_name'] ?? 'us') ?></strong>!</p>
        <p>This estimate was generated on <?= date('F j, Y') ?></p>
    </div>
</div>

<script>
function downloadPDF() {
    const element = document.getElementById('estimate-pdf');
    const opt = {
        margin:       [0.5, 0.5, 0.5, 0.5],
        filename:     'Estimate-<?= e($estimate['estimate_number']) ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>
