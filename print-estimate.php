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

$pdfVisibleCategories = ['custom', 'features', 'deck', 'fence'];
$pdfItems = array_values(array_filter($items, function ($item) use ($pdfVisibleCategories) {
    $category = $item['category'] ?? 'general';
    return in_array($category, $pdfVisibleCategories, true);
}));
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
        .btn-email { background: #EF476F; color: white; }
        .btn-back { background: #6C757D; color: white; }
        .btn-print:hover { background: #023E8A; }
        .btn-pdf:hover { background: #05b588; }
        .btn-email:hover { background: #d13e60; }
        .btn-back:hover { background: #565e64; }
        .btn-print:disabled, .btn-pdf:disabled, .btn-email:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Email modal */
        .email-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .email-modal-overlay.open { display: flex; }
        .email-modal {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 480px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 1.5rem;
        }
        .email-modal h2 {
            font-size: 1.15rem;
            margin-bottom: 1rem;
        }
        .email-modal label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin: 0.75rem 0 0.35rem;
        }
        .email-modal input, .email-modal textarea {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
        }
        .email-modal textarea { resize: vertical; }
        .email-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            margin-top: 1.25rem;
        }
        .email-modal-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            padding: 0.625rem 1.25rem;
            border: 2px solid transparent;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .email-modal-actions .btn-secondary { background: #e9ecef; color: #212529; }
        .email-modal-actions .btn-secondary:hover { background: #d3d8de; }
        .email-modal-actions .btn-primary { background: #eb6e1f; color: white; }
        .email-modal-actions .btn-primary:hover { background: #c45a14; }
        .email-modal-actions .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .email-modal-status {
            margin-top: 0.75rem;
            font-size: 0.85rem;
        }
        .email-modal-status.error { color: #EF476F; }
        .email-modal-status.success { color: #06D6A0; }

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
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .doc-business-logo {
            max-height: 60px;
            max-width: 180px;
            object-fit: contain;
            margin-bottom: 0.5rem;
            display: block;
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

        .doc-info-box {
            page-break-inside: avoid;
            break-inside: avoid;
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

        .doc-table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

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
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .doc-totals-table {
            width: 280px;
        }

        .doc-totals-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            font-size: 0.95rem;
            page-break-inside: avoid;
            break-inside: avoid;
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
            page-break-inside: avoid;
            break-inside: avoid;
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
            page-break-inside: avoid;
            break-inside: avoid;
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
            page-break-inside: avoid;
            break-inside: avoid;
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
    <button class="btn-back" onclick="window.close()">&#8592; <span data-i18n="print_btn_back">Back</span></button>
    <button class="btn-print" onclick="window.print()">🖨️ <span data-i18n="print_btn_print">Print</span></button>
    <button class="btn-pdf" onclick="downloadPDF()">📄 <span data-i18n="print_btn_download_pdf">Download PDF</span></button>
    <button class="btn-email" onclick="openEmailModal()">✉️ <span data-i18n="btn_email_client">Email to Client</span></button>
</div>

<div class="email-modal-overlay" id="email-modal-overlay">
    <div class="email-modal">
        <h2 id="email-modal-title">Email Estimate <?= e($estimate['estimate_number']) ?></h2>

        <label for="email-recipient" data-i18n="print_label_recipient">Recipient Email</label>
        <input type="email" id="email-recipient" value="<?= e($estimate['client_email'] ?? '') ?>" placeholder="client@example.com" required>

        <label for="email-subject" data-i18n="print_label_subject">Subject</label>
        <input type="text" id="email-subject" value="Your Estimate <?= e($estimate['estimate_number']) ?>">

        <label for="email-message" data-i18n="print_label_message">Message</label>
        <textarea id="email-message" rows="5">Hi <?= e($estimate['client_name'] ?? '') ?>,

Please find your pool estimate attached. Let us know if you have any questions!

Thank you,
<?= e($settings['business_name'] ?? 'Pool Builder') ?></textarea>

        <div class="email-modal-status" id="email-modal-status"></div>

        <div class="email-modal-actions">
            <button class="btn btn-secondary" type="button" onclick="closeEmailModal()" data-i18n="btn_cancel">Cancel</button>
            <button class="btn btn-primary" type="button" id="email-send-btn" onclick="sendEstimateEmail()" data-i18n="btn_send">Send</button>
        </div>
    </div>
</div>

<div class="estimate-document" id="estimate-pdf">
    <!-- Document Header -->
    <div class="doc-header">
        <div class="doc-business">
            <img src="assets/img/logos/logo01.png" alt="Logo" class="doc-business-logo">
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
            $currentPdfGroup = null;
            foreach ($pdfItems as $item):
                $cat = $item['category'] ?? 'general';
                $groupKey = ($cat === 'custom') ? 'custom' : 'features';
                if ($currentPdfGroup !== $groupKey):
                    $currentPdfGroup = $groupKey;
            ?>
                <tr class="category-row">
                    <td colspan="4"><?= e($groupKey === 'custom' ? 'Custom Items' : 'Features & Add-ons') ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td><?= e($item['description']) ?></td>
                <td><?= rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.') ?> <?= e($item['unit'] ?? '') ?></td>
                <td><?= $currency . number_format((float)$item['unit_price'], 2) ?></td>
                <td><?= $currency . number_format((float)$item['total'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pdfItems)): ?>
                <tr>
                    <td colspan="4" style="text-align:center; color:#6C757D; padding:1rem;">No itemized add-ons for this estimate.</td>
                </tr>
            <?php endif; ?>
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

<script src="assets/js/i18n/en.js"></script>
<script src="assets/js/i18n/es.js"></script>
<script src="assets/js/i18n/i18n.js"></script>
<script>
const PDF_OPTIONS = {
    margin:       [0.5, 0.5, 0.5, 0.5],
    filename:     'Estimate-<?= e($estimate['estimate_number']) ?>.pdf',
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2, useCORS: true },
    jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' },
    pagebreak:    { mode: ['css', 'legacy'], avoid: ['tr', '.doc-header', '.doc-info-box', '.doc-specs', '.doc-totals', '.doc-totals-row', '.doc-notes', '.doc-footer'] }
};

const ESTIMATE_NUMBER = <?= json_encode($estimate['estimate_number']) ?>;

function renderPrintTranslations() {
    // Email modal title (UI only — the sent email subject/body stay in the business's own language)
    const emailTitle = document.getElementById('email-modal-title');
    if (emailTitle) emailTitle.textContent = i18n('print_email_modal_title', { number: ESTIMATE_NUMBER });
}

document.addEventListener('i18n:applied', renderPrintTranslations);

function downloadPDF() {
    const element = document.getElementById('estimate-pdf');
    html2pdf().set(PDF_OPTIONS).from(element).save();
}

function openEmailModal() {
    document.getElementById('email-modal-overlay').classList.add('open');
}

function closeEmailModal() {
    document.getElementById('email-modal-overlay').classList.remove('open');
    document.getElementById('email-modal-status').textContent = '';
    document.getElementById('email-modal-status').className = 'email-modal-status';
}

<?php if (($_GET['email'] ?? '') === '1'): ?>
openEmailModal();
<?php endif; ?>

function sendEstimateEmail() {
    const recipient = document.getElementById('email-recipient').value.trim();
    const subject = document.getElementById('email-subject').value.trim();
    const message = document.getElementById('email-message').value.trim();
    const statusEl = document.getElementById('email-modal-status');
    const sendBtn = document.getElementById('email-send-btn');

    if (!recipient) {
        statusEl.textContent = i18n('print_err_no_recipient');
        statusEl.className = 'email-modal-status error';
        return;
    }

    sendBtn.disabled = true;
    statusEl.className = 'email-modal-status';
    statusEl.textContent = i18n('print_status_generating');

    const element = document.getElementById('estimate-pdf');
    html2pdf().set(PDF_OPTIONS).from(element).outputPdf('blob').then(function (blob) {
        statusEl.textContent = i18n('print_status_sending');

        const formData = new FormData();
        formData.append('<?= CSRF_TOKEN_NAME ?>', '<?= e(generateCSRFToken()) ?>');
        formData.append('id', '<?= (int)$id ?>');
        formData.append('recipient_email', recipient);
        formData.append('recipient_name', <?= json_encode($estimate['client_name'] ?? '') ?>);
        formData.append('subject', subject);
        formData.append('message', message);
        formData.append('pdf', blob, PDF_OPTIONS.filename);

        return fetch('send-estimate-email.php', { method: 'POST', body: formData });
    }).then(function (res) {
        return res.json();
    }).then(function (data) {
        sendBtn.disabled = false;
        if (data.success) {
            statusEl.textContent = i18n('print_status_success');
            statusEl.className = 'email-modal-status success';
            setTimeout(closeEmailModal, 1500);
        } else {
            statusEl.textContent = data.error || i18n('print_status_fail');
            statusEl.className = 'email-modal-status error';
        }
    }).catch(function () {
        sendBtn.disabled = false;
        statusEl.textContent = i18n('print_status_fail');
        statusEl.className = 'email-modal-status error';
    });
}
</script>

</body>
</html>
