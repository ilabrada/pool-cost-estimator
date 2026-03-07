/**
 * Pool Cost Estimator - Main JavaScript
 * Handles: cost calculations, client autocomplete, UI interactions
 */

// ═══════════════════════════════════════════════════════════════════
// SIDEBAR & NAVIGATION
// ═══════════════════════════════════════════════════════════════════

let currentImageIndex = 0;

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (sidebar) {
        sidebar.classList.toggle('open');
        overlay?.classList.toggle('show');
    }
}

// Close sidebar on overlay click (also set in HTML)
document.addEventListener('DOMContentLoaded', () => {
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
        const sidebar = document.getElementById('sidebar');
        if (sidebar?.classList.contains('open') && 
            !sidebar.contains(e.target) && 
            !e.target.closest('.menu-toggle')) {
            toggleSidebar();
        }
    });
});

// ═══════════════════════════════════════════════════════════════════
// SECTION TOGGLE (collapsible cards)
// ═══════════════════════════════════════════════════════════════════

function toggleSection(sectionName) {
    const section = document.getElementById('section-' + sectionName);
    if (section) {
        section.classList.toggle('collapsed');
    }
}

// ═══════════════════════════════════════════════════════════════════
// TOAST NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════════

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// ═══════════════════════════════════════════════════════════════════
// CONFIRM DIALOG
// ═══════════════════════════════════════════════════════════════════

function confirmDelete(id, type) {
    const modal = document.getElementById('confirm-modal');
    const title = document.getElementById('confirm-title');
    const message = document.getElementById('confirm-message');
    const actionBtn = document.getElementById('confirm-action');

    title.textContent = 'Delete ' + type.charAt(0).toUpperCase() + type.slice(1);
    message.textContent = `Are you sure you want to delete this ${type}? This action cannot be undone.`;

    modal.classList.add('show');

    actionBtn.onclick = () => {
        if (type === 'estimate') {
            window.location.href = `estimate.php?id=${id}&delete=1`;
        } else if (type === 'client') {
            window.location.href = `clients.php?id=${id}&delete=1`;
        }
        closeConfirm();
    };
}

function closeConfirm() {
    document.getElementById('confirm-modal')?.classList.remove('show');
}

// Close modal on escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeConfirm();
        closeShapePreview();
    }
});

// ═══════════════════════════════════════════════════════════════════
// POOL SHAPE PREVIEW
// ═══════════════════════════════════════════════════════════════════

let POOL_SHAPE_IMAGES = {}; // Will be populated dynamically
let POOL_SHAPE_LABELS = {
    'rectangular': 'Rectangular',
    'l-shaped':    'L-Shaped',
    'kidney':      'Kidney',
    'oval':        'Oval',
    'freeform':    'Freeform',
};

function openShapePreview() {
    const select = document.getElementById('pool-shape');
    const shape = select ? select.value : 'rectangular';

    // Fetch images for this shape if not already loaded
    if (!POOL_SHAPE_IMAGES[shape]) {
        fetch(`api.php?action=list_pool_images`)
            .then(r => r.json())
            .then(images => {
                POOL_SHAPE_IMAGES = images;
                showShapePreview(shape);
            })
            .catch(err => {
                console.error('Error loading pool images:', err);
                // Fallback to empty array
                POOL_SHAPE_IMAGES[shape] = [];
                showShapePreview(shape);
            });
    } else {
        showShapePreview(shape);
    }
}

function showShapePreview(shape) {
    const images = POOL_SHAPE_IMAGES[shape] || [];
    currentImageIndex = 0;  // Reset to first image
    updateGalleryImage(shape, images);
    updateGalleryIndicators(shape, images);
    document.getElementById('shape-preview-modal')?.classList.add('show');
}

function closeShapePreview() {
    document.getElementById('shape-preview-modal')?.classList.remove('show');
}

function navigateGallery(direction) {
    const select = document.getElementById('pool-shape');
    const shape = select ? select.value : 'rectangular';
    const images = POOL_SHAPE_IMAGES[shape] || [];
    currentImageIndex = (currentImageIndex + direction + images.length) % images.length;  // Wrap around
    updateGalleryImage(shape, images);
}

function updateGalleryImage(shape, images) {
    const img = document.getElementById('shape-preview-img');
    const title = document.getElementById('shape-preview-title');
    const label = POOL_SHAPE_LABELS[shape] || 'Pool Shape';
    img.src = images[currentImageIndex];
    img.alt = `${label} pool example ${currentImageIndex + 1}`;
    title.textContent = `${label} Pool — Shape Example (${currentImageIndex + 1} of ${images.length})`;
}

function updateGalleryIndicators(shape, images) {
    const indicators = document.getElementById('gallery-indicators');
    indicators.innerHTML = '';
    for (let i = 0; i < images.length; i++) {
        const dot = document.createElement('span');
        dot.className = `indicator-dot ${i === currentImageIndex ? 'active' : ''}`;
        dot.onclick = () => { currentImageIndex = i; updateGalleryImage(shape, images); };  // Allow clicking dots
        indicators.appendChild(dot);
    }
    // Hide indicators/buttons if only one image
    document.querySelectorAll('.gallery-nav').forEach(btn => btn.style.display = images.length > 1 ? 'block' : 'none');
    indicators.style.display = images.length > 1 ? 'flex' : 'none';
}

// ═══════════════════════════════════════════════════════════════════
// CLIENT AUTOCOMPLETE
// ═══════════════════════════════════════════════════════════════════

(function() {
    const searchInput = document.getElementById('client-search');
    const resultsDiv = document.getElementById('client-results');
    const clientIdInput = document.getElementById('client-id');
    let debounceTimer;

    if (!searchInput || !resultsDiv) return;

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query.length < 2) {
            resultsDiv.classList.remove('show');
            // Clear client ID if name changed (new client)
            if (clientIdInput) clientIdInput.value = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`api.php?action=search_clients&q=${encodeURIComponent(query)}`)
                .then(r => r.json())
                .then(clients => {
                    resultsDiv.innerHTML = '';
                    if (clients.length === 0) {
                        resultsDiv.classList.remove('show');
                        if (clientIdInput) clientIdInput.value = '';
                        return;
                    }

                    clients.forEach(client => {
                        const item = document.createElement('div');
                        item.className = 'autocomplete-item';
                        item.innerHTML = `
                            <div class="ac-name">${escapeHtml(client.name)}</div>
                            <div class="ac-detail">${escapeHtml(client.phone || '')} ${client.email ? '• ' + escapeHtml(client.email) : ''}</div>
                        `;
                        item.addEventListener('click', () => {
                            selectClient(client);
                            resultsDiv.classList.remove('show');
                        });
                        resultsDiv.appendChild(item);
                    });
                    resultsDiv.classList.add('show');
                })
                .catch(err => console.error('Search error:', err));
        }, 300);
    });

    // Close results when clicking outside
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            resultsDiv.classList.remove('show');
        }
    });

    function selectClient(client) {
        if (clientIdInput) clientIdInput.value = client.id;
        searchInput.value = client.name;
        
        const phoneInput = document.getElementById('client-phone');
        const emailInput = document.getElementById('client-email');
        const addressInput = document.getElementById('client-address');

        if (phoneInput) phoneInput.value = client.phone || '';
        if (emailInput) emailInput.value = client.email || '';
        if (addressInput) addressInput.value = client.address || '';
    }
})();

// ═══════════════════════════════════════════════════════════════════
// FEATURE OPTIONS TOGGLE
// ═══════════════════════════════════════════════════════════════════

function toggleFeatureOptions(featureName) {
    const optionsDiv = document.getElementById('options-' + featureName);
    if (!optionsDiv) return;

    let checkbox;
    switch (featureName) {
        case 'jacuzzi': checkbox = document.getElementById('has-jacuzzi'); break;
        case 'lights':  checkbox = document.getElementById('has-lights'); break;
        case 'heating': checkbox = document.getElementById('has-heating'); break;
        case 'deck':    checkbox = document.getElementById('has-deck'); break;
        case 'fence':   checkbox = document.getElementById('has-fence'); break;
        default: return;
    }

    if (checkbox && checkbox.checked) {
        optionsDiv.style.display = '';
    } else {
        optionsDiv.style.display = 'none';
        // Reset values when unchecked
        if (featureName === 'lights') {
            const numLights = document.getElementById('num-lights');
            if (numLights) numLights.value = '0';
        }
    }
}

// ═══════════════════════════════════════════════════════════════════
// CUSTOM LINE ITEMS
// ═══════════════════════════════════════════════════════════════════

function addCustomItem() {
    const list = document.getElementById('custom-items-list');
    if (!list) return;

    const row = document.createElement('div');
    row.className = 'custom-item-row';
    row.innerHTML = `
        <input type="hidden" name="item_category[]" value="custom">
        <input type="hidden" name="item_is_custom[]" value="1">
        <input type="hidden" name="item_unit[]" value="each">
        <div class="form-row">
            <div class="form-group form-group-grow">
                <input type="text" name="item_description[]" placeholder="Description" required>
            </div>
            <div class="form-group" style="width: 80px;">
                <input type="number" name="item_quantity[]" placeholder="Qty" min="1" step="1" value="1" oninput="updateCustomItemTotal(this); recalculate()">
            </div>
            <div class="form-group" style="width: 120px;">
                <input type="number" name="item_unit_price[]" placeholder="Unit Price" min="0" step="0.01" value="0" oninput="updateCustomItemTotal(this); recalculate()">
            </div>
            <div class="form-group" style="width: 120px;">
                <input type="number" name="item_total[]" readonly class="item-line-total" value="0">
            </div>
            <button type="button" class="btn-icon btn-danger-icon" onclick="removeCustomItem(this)">
                <span class="material-icons-round">close</span>
            </button>
        </div>
    `;
    list.appendChild(row);
}

function removeCustomItem(btn) {
    const row = btn.closest('.custom-item-row');
    if (row) {
        row.remove();
        recalculate();
    }
}

function updateCustomItemTotal(input) {
    const row = input.closest('.custom-item-row');
    if (!row) return;

    const qty = parseFloat(row.querySelector('[name="item_quantity[]"]')?.value) || 0;
    const unitPrice = parseFloat(row.querySelector('[name="item_unit_price[]"]')?.value) || 0;
    const totalInput = row.querySelector('[name="item_total[]"]');
    if (totalInput) {
        totalInput.value = (qty * unitPrice).toFixed(2);
    }
}

// ═══════════════════════════════════════════════════════════════════
// COST CALCULATION ENGINE
// ═══════════════════════════════════════════════════════════════════

// Shape factors for pool area calculations
const SHAPE_FACTORS = {
    'rectangular': 1.0,
    'l-shaped': 0.85,
    'kidney': 0.80,
    'oval': 0.79,
    'freeform': 0.75
};

function getFormData() {
    const val = (id) => parseFloat(document.getElementById(id)?.value) || 0;
    const sel = (id) => document.getElementById(id)?.value || '';
    const chk = (id) => document.getElementById(id)?.checked || false;
    const nameChk = (name) => document.querySelector(`[name="${name}"]`)?.checked || false;
    const nameSel = (name) => document.querySelector(`[name="${name}"]`)?.value || '';

    return {
        pool_length: val('pool-length'),
        pool_width: val('pool-width'),
        pool_depth_shallow: val('pool-depth-shallow'),
        pool_depth_deep: val('pool-depth-deep'),
        pool_shape: sel('pool-shape'),
        pool_material: document.getElementById('pool-material')?.value || 'concrete',
        interior_finish: sel('interior-finish'),
        has_jacuzzi: chk('has-jacuzzi'),
        jacuzzi_size: nameSel('jacuzzi_size'),
        num_lights: chk('has-lights') ? (parseInt(document.getElementById('num-lights')?.value) || 0) : 0,
        has_heating: chk('has-heating'),
        heating_type: nameSel('heating_type'),
        has_waterfall: nameChk('has_waterfall'),
        has_water_feature: nameChk('has_water_feature'),
        has_auto_cover: nameChk('has_auto_cover'),
        has_pool_cleaner: nameChk('has_pool_cleaner'),
        has_deck: chk('has-deck'),
        deck_material: nameSel('deck_material'),
        deck_area: val('deck-area'),
        has_fence: chk('has-fence'),
        fence_type: nameSel('fence_type'),
        fence_length: val('fence-length'),
    };
}

function calculateMetrics(data) {
    const length = data.pool_length;
    const width = data.pool_width;
    const depthShallow = data.pool_depth_shallow;
    const depthDeep = data.pool_depth_deep;
    const shapeFactor = SHAPE_FACTORS[data.pool_shape] || 1.0;

    const avgDepth = (depthShallow + depthDeep) / 2;
    const floorArea = length * width * shapeFactor;
    const wallArea = 2 * (length + width) * avgDepth * shapeFactor;
    const surfaceArea = floorArea + wallArea;
    const volumeCuFt = floorArea * avgDepth;
    const volumeGallons = volumeCuFt * 7.48;
    const perimeter = 2 * (length + width) * Math.sqrt(shapeFactor);

    return {
        floor_area: Math.round(floorArea * 100) / 100,
        wall_area: Math.round(wallArea * 100) / 100,
        surface_area: Math.round(surfaceArea * 100) / 100,
        volume_cuft: Math.round(volumeCuFt * 100) / 100,
        volume_gallons: Math.round(volumeGallons),
        perimeter: Math.round(perimeter * 100) / 100,
        avg_depth: Math.round(avgDepth * 100) / 100,
    };
}

function calculateCosts(data, metrics) {
    if (typeof PRICING === 'undefined') return { items: [], subtotal: 0 };

    const items = [];
    const p = (key) => PRICING[key] ? parseFloat(PRICING[key].unit_price) : 0;

    if (data.pool_length <= 0 || data.pool_width <= 0) {
        return { items: [], subtotal: 0 };
    }

    // Excavation
    if (metrics.volume_cuft > 0) {
        if (PRICING['excavation']) {
            items.push({
                category: 'excavation',
                description: PRICING['excavation'].item_label,
                quantity: metrics.volume_cuft,
                unit: 'cu ft',
                unit_price: p('excavation'),
                total: round2(metrics.volume_cuft * p('excavation'))
            });
        }
        if (PRICING['hauling']) {
            items.push({
                category: 'excavation',
                description: PRICING['hauling'].item_label,
                quantity: metrics.volume_cuft,
                unit: 'cu ft',
                unit_price: p('hauling'),
                total: round2(metrics.volume_cuft * p('hauling'))
            });
        }
    }

    // Pool Shell
    const shellKey = 'shell_' + data.pool_material;
    if (metrics.surface_area > 0 && PRICING[shellKey]) {
        items.push({
            category: 'shell',
            description: PRICING[shellKey].item_label,
            quantity: metrics.surface_area,
            unit: 'sq ft',
            unit_price: p(shellKey),
            total: round2(metrics.surface_area * p(shellKey))
        });
    }

    // Interior Finish (concrete only)
    if (data.pool_material === 'concrete') {
        const finishKey = 'finish_' + data.interior_finish;
        if (metrics.surface_area > 0 && PRICING[finishKey]) {
            items.push({
                category: 'finish',
                description: PRICING[finishKey].item_label,
                quantity: metrics.surface_area,
                unit: 'sq ft',
                unit_price: p(finishKey),
                total: round2(metrics.surface_area * p(finishKey))
            });
        }
    }

    // Equipment (flat rates)
    ['plumbing', 'electrical', 'filtration', 'equipment_pad'].forEach(key => {
        if (PRICING[key]) {
            items.push({
                category: 'equipment',
                description: PRICING[key].item_label,
                quantity: 1,
                unit: 'flat',
                unit_price: p(key),
                total: p(key)
            });
        }
    });

    // Coping & Waterline Tile
    if (metrics.perimeter > 0) {
        if (PRICING['coping']) {
            items.push({
                category: 'tile',
                description: PRICING['coping'].item_label,
                quantity: metrics.perimeter,
                unit: 'lin ft',
                unit_price: p('coping'),
                total: round2(metrics.perimeter * p('coping'))
            });
        }
        if (PRICING['waterline_tile']) {
            items.push({
                category: 'tile',
                description: PRICING['waterline_tile'].item_label,
                quantity: metrics.perimeter,
                unit: 'lin ft',
                unit_price: p('waterline_tile'),
                total: round2(metrics.perimeter * p('waterline_tile'))
            });
        }
    }

    // Features
    if (data.has_jacuzzi) {
        const jKey = 'jacuzzi_' + (data.jacuzzi_size || 'standard');
        if (PRICING[jKey]) {
            items.push({ category: 'features', description: PRICING[jKey].item_label, quantity: 1, unit: 'flat', unit_price: p(jKey), total: p(jKey) });
        }
    }

    if (data.num_lights > 0 && PRICING['led_light']) {
        items.push({ category: 'features', description: PRICING['led_light'].item_label, quantity: data.num_lights, unit: 'each', unit_price: p('led_light'), total: round2(data.num_lights * p('led_light')) });
    }

    if (data.has_heating) {
        const hKey = 'heater_' + (data.heating_type || 'gas');
        if (PRICING[hKey]) {
            items.push({ category: 'features', description: PRICING[hKey].item_label, quantity: 1, unit: 'flat', unit_price: p(hKey), total: p(hKey) });
        }
    }

    if (data.has_waterfall && PRICING['waterfall']) {
        items.push({ category: 'features', description: PRICING['waterfall'].item_label, quantity: 1, unit: 'flat', unit_price: p('waterfall'), total: p('waterfall') });
    }

    if (data.has_water_feature && PRICING['water_feature']) {
        items.push({ category: 'features', description: PRICING['water_feature'].item_label, quantity: 1, unit: 'flat', unit_price: p('water_feature'), total: p('water_feature') });
    }

    if (data.has_auto_cover && PRICING['auto_cover'] && metrics.floor_area > 0) {
        items.push({ category: 'features', description: PRICING['auto_cover'].item_label, quantity: metrics.floor_area, unit: 'sq ft', unit_price: p('auto_cover'), total: round2(metrics.floor_area * p('auto_cover')) });
    }

    if (data.has_pool_cleaner && PRICING['pool_cleaner']) {
        items.push({ category: 'features', description: PRICING['pool_cleaner'].item_label, quantity: 1, unit: 'flat', unit_price: p('pool_cleaner'), total: p('pool_cleaner') });
    }

    // Deck
    if (data.has_deck && data.deck_area > 0) {
        const dKey = 'deck_' + (data.deck_material || 'concrete');
        if (PRICING[dKey]) {
            items.push({ category: 'deck', description: PRICING[dKey].item_label, quantity: data.deck_area, unit: 'sq ft', unit_price: p(dKey), total: round2(data.deck_area * p(dKey)) });
        }
    }

    // Fence
    if (data.has_fence && data.fence_length > 0) {
        const fKey = 'fence_' + (data.fence_type || 'aluminum');
        if (PRICING[fKey]) {
            items.push({ category: 'fence', description: PRICING[fKey].item_label, quantity: data.fence_length, unit: 'lin ft', unit_price: p(fKey), total: round2(data.fence_length * p(fKey)) });
        }
    }

    // Other
    ['permits', 'engineering', 'startup'].forEach(key => {
        if (PRICING[key]) {
            items.push({ category: 'other', description: PRICING[key].item_label, quantity: 1, unit: 'flat', unit_price: p(key), total: p(key) });
        }
    });

    const subtotal = items.reduce((sum, item) => sum + item.total, 0);
    return { items, subtotal: round2(subtotal) };
}

function recalculate() {
    if (typeof PRICING === 'undefined') return;

    const data = getFormData();
    const metrics = calculateMetrics(data);

    // Update metrics display
    const surfaceEl = document.getElementById('metric-surface');
    const volumeEl = document.getElementById('metric-volume');
    const perimeterEl = document.getElementById('metric-perimeter');

    if (surfaceEl) surfaceEl.textContent = numberFormat(metrics.surface_area);
    if (volumeEl) volumeEl.textContent = numberFormat(metrics.volume_gallons);
    if (perimeterEl) perimeterEl.textContent = numberFormat(metrics.perimeter);

    // Calculate costs
    const result = calculateCosts(data, metrics);

    // Add custom items
    let customTotal = 0;
    document.querySelectorAll('.custom-item-row').forEach(row => {
        const total = parseFloat(row.querySelector('[name="item_total[]"]')?.value) || 0;
        customTotal += total;
    });

    const subtotal = result.subtotal + customTotal;

    // Discount & Tax
    const discountPercent = parseFloat(document.getElementById('discount-percent')?.value) || 0;
    const taxRate = parseFloat(document.getElementById('tax-rate')?.value) || 0;
    const discountAmount = round2(subtotal * discountPercent / 100);
    const afterDiscount = subtotal - discountAmount;
    const taxAmount = round2(afterDiscount * taxRate / 100);
    const total = round2(afterDiscount + taxAmount);

    const currency = (typeof SETTINGS !== 'undefined' ? SETTINGS.currency_symbol : '$') || '$';

    // Update summary display
    updateSummaryDisplay(result.items, currency);

    // Update totals
    setText('summary-subtotal', currency + numberFormat(subtotal));
    setText('summary-discount', '-' + currency + numberFormat(discountAmount));
    setText('summary-tax', currency + numberFormat(taxAmount));
    setText('summary-total', currency + numberFormat(total));
    setText('mobile-total', currency + numberFormat(total));

    // Update hidden form fields
    setVal('input-subtotal', subtotal.toFixed(2));
    setVal('input-tax-amount', taxAmount.toFixed(2));
    setVal('input-discount-amount', discountAmount.toFixed(2));
    setVal('input-total', total.toFixed(2));

    // Populate hidden line items for form submission
    populateLineItemFields(result.items);
}

function updateSummaryDisplay(items, currency) {
    const container = document.getElementById('summary-items');
    if (!container) return;

    if (items.length === 0) {
        container.innerHTML = '<p class="summary-empty">Enter pool dimensions to see cost breakdown</p>';
        return;
    }

    let html = '';
    const categoryLabels = {
        'excavation': 'Excavation',
        'shell': 'Pool Shell',
        'finish': 'Interior Finish',
        'equipment': 'Equipment & Plumbing',
        'tile': 'Tile & Coping',
        'features': 'Features & Add-ons',
        'deck': 'Deck',
        'fence': 'Fencing',
        'other': 'Other',
    };

    let currentCat = '';
    items.forEach(item => {
        if (item.category !== currentCat) {
            currentCat = item.category;
            html += `<div class="summary-category">${categoryLabels[currentCat] || currentCat}</div>`;
        }
        html += `
            <div class="summary-item">
                <span class="summary-item-label">${escapeHtml(item.description)}</span>
                <span class="summary-item-value">${currency}${numberFormat(item.total)}</span>
            </div>
        `;
    });

    // Custom items
    const customRows = document.querySelectorAll('.custom-item-row');
    if (customRows.length > 0) {
        let hasCustom = false;
        customRows.forEach(row => {
            const desc = row.querySelector('[name="item_description[]"]')?.value;
            const total = parseFloat(row.querySelector('[name="item_total[]"]')?.value) || 0;
            if (desc && total > 0) {
                if (!hasCustom) {
                    html += `<div class="summary-category">Custom Items</div>`;
                    hasCustom = true;
                }
                html += `
                    <div class="summary-item">
                        <span class="summary-item-label">${escapeHtml(desc)}</span>
                        <span class="summary-item-value">${currency}${numberFormat(total)}</span>
                    </div>
                `;
            }
        });
    }

    container.innerHTML = html;
}

function populateLineItemFields(items) {
    // Remove existing auto-generated hidden fields
    document.querySelectorAll('.auto-line-item').forEach(el => el.remove());

    const form = document.getElementById('estimate-form');
    if (!form) return;

    items.forEach((item, i) => {
        const fields = [
            { name: 'item_category[]', value: item.category },
            { name: 'item_description[]', value: item.description },
            { name: 'item_quantity[]', value: item.quantity },
            { name: 'item_unit[]', value: item.unit },
            { name: 'item_unit_price[]', value: item.unit_price },
            { name: 'item_total[]', value: item.total },
            { name: 'item_is_custom[]', value: '0' },
        ];

        fields.forEach(f => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = f.name;
            input.value = f.value;
            input.className = 'auto-line-item';
            form.appendChild(input);
        });
    });
}

// ═══════════════════════════════════════════════════════════════════
// UTILITY FUNCTIONS
// ═══════════════════════════════════════════════════════════════════

function round2(num) {
    return Math.round(num * 100) / 100;
}

function numberFormat(num) {
    return num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function setText(id, text) {
    const el = document.getElementById(id);
    if (el) el.textContent = text;
}

function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('keydown', (e) => {
    if (document.getElementById('shape-preview-modal').classList.contains('show')) {
        if (e.key === 'ArrowLeft') navigateGallery(-1);
        else if (e.key === 'ArrowRight') navigateGallery(1);
        else if (e.key === 'Escape') closeShapePreview();
    }
});
