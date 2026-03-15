/**
 * English (en) translations
 *
 * HOW TO ADD A NEW LANGUAGE
 * ─────────────────────────
 * 1. Copy this file to `assets/js/i18n/<code>.js`  (e.g. `fr.js` for French).
 * 2. Replace every value string with the translated text.
 *    Keys must remain identical to the English file.
 * 3. Register the new locale in `assets/js/i18n/i18n.js`:
 *      - Add an import/script tag to `includes/footer.php` BEFORE `i18n.js`
 *      - Add the locale code + label to the `SUPPORTED_LOCALES` array in `i18n.js`
 *      - Add the HTML option in the language toggle inside `includes/header.php`
 * 4. That's it — the i18n engine handles everything else at runtime.
 */

window.I18N_LOCALES = window.I18N_LOCALES || {};
window.I18N_LOCALES['en'] = {

    // ── Navigation / sidebar ──────────────────────────────────────
    nav_dashboard:        'Dashboard',
    nav_new_estimate:     'New Estimate',
    nav_clients:          'Clients',
    nav_audit_log:        'Audit Log',
    nav_settings:         'Settings',
    nav_logout:           'Logout',
    nav_release_notes:    'Release Notes',

    // ── Bottom nav (mobile) ───────────────────────────────────────
    nav_home:             'Home',
    nav_estimate:         'Estimate',
    nav_log:              'Log',

    // ── Header ────────────────────────────────────────────────────
    header_new_estimate:  'New Estimate',
    lang_toggle_label:    'Language',

    // ── Dashboard ─────────────────────────────────────────────────
    page_dashboard:       'Dashboard',
    stat_total_estimates: 'Total Estimates',
    stat_drafts:          'Drafts',
    stat_approved:        'Approved',
    stat_clients:         'Clients',
    quick_actions:        'Quick Actions',
    quick_new_estimate:   'New Estimate',
    quick_new_client:     'New Client',
    quick_all_estimates:  'All Estimates',
    recent_estimates:     'Recent Estimates',
    view_all:             'View All',
    no_estimates_yet:     'No estimates yet',
    no_estimates_msg:     'Create your first pool estimate to get started.',
    create_estimate:      'Create Estimate',
    search_estimates:     'Search estimates or clients…',
    filter_all_status:    'All Status',
    filter_draft:         'Draft',
    filter_sent:          'Sent',
    filter_approved:      'Approved',
    filter_rejected:      'Rejected',
    label_no_client:      'No client',

    // ── Estimate form ─────────────────────────────────────────────
    page_new_estimate:       'New Estimate',
    page_edit_estimate:      'Edit Estimate',
    section_client:          'Client Information',
    label_client_name:       'Client Name *',
    placeholder_client:      'Search or type new client name…',
    label_phone:             'Phone',
    label_email:             'Email',
    label_address:           'Address',
    placeholder_address:     'Project address',

    section_dimensions:      'Pool Dimensions',
    label_length:            'Length',
    label_width:             'Width',
    label_shallow_depth:     'Shallow Depth',
    label_deep_end:          'Deep End',
    label_pool_shape:        'Pool Shape',
    shape_rectangular:       'Rectangular',
    shape_l_shaped:          'L-Shaped',
    shape_kidney:            'Kidney',
    shape_oval:              'Oval',
    shape_freeform:          'Freeform',
    view_shape_example:      'View shape example',
    metric_surface_area:     'Surface Area:',
    metric_volume:           'Volume:',
    metric_perimeter:        'Perimeter:',
    unit_sq_ft:              'sq ft',
    unit_gallons:            'gallons',
    unit_ft:                 'ft',

    section_construction:    'Pool Construction',
    label_pool_material:     'Pool Material',
    label_interior_finish:   'Interior Finish',
    finish_plaster:          'Standard Plaster',
    finish_pebble:           'Pebble (PebbleTec)',
    finish_quartz:           'Quartz',
    finish_tile:             'Full Tile',

    section_features:        'Features & Add-ons',
    feature_jacuzzi:         'Spa / Jacuzzi',
    jacuzzi_standard:        'Standard (6-8 person)',
    jacuzzi_large:           'Large (8-12 person)',
    feature_led_lighting:    'LED Lighting',
    label_num_lights:        'Number of lights',
    feature_heating:         'Pool Heating',
    heating_gas:             'Gas Heater',
    heating_heatpump:        'Heat Pump',
    heating_solar:           'Solar',
    feature_waterfall:       'Rock Waterfall',
    feature_water_feature:   'Fountain / Scupper',
    feature_auto_cover:      'Automatic Cover',
    feature_pool_cleaner:    'Automatic Cleaner',

    section_deck:            'Deck & Surroundings',
    feature_deck:            'Pool Deck',
    label_deck_material:     'Deck Material',
    deck_concrete:           'Standard Concrete',
    deck_stamped:            'Stamped Concrete',
    deck_pavers:             'Pavers',
    deck_travertine:         'Travertine',
    label_deck_area:         'Deck Area (sq ft)',
    feature_fence:           'Pool Fence',
    label_fence_type:        'Fence Type',
    fence_aluminum:          'Aluminum',
    fence_glass:             'Glass Panel',
    fence_mesh:              'Mesh Safety',
    label_fence_length:      'Fence Length (lin ft)',

    section_custom:          'Custom Items',
    custom_help:             'Add custom line items for anything not covered above.',
    placeholder_description: 'Description',
    placeholder_qty:         'Qty',
    placeholder_unit_price:  'Unit Price',
    btn_add_item:            'Add Item',

    section_notes:           'Notes',
    label_notes_client:      'Notes for Client',
    placeholder_notes:       'Notes that will appear on the estimate…',
    label_internal_notes:    'Internal Notes',
    hint_internal_notes:     '(not shown on estimate)',
    placeholder_internal:    'Private notes…',

    cost_summary:            'Cost Summary',
    summary_empty:           'Enter pool dimensions to see cost breakdown',
    label_subtotal:          'Subtotal',
    label_discount:          'Discount',
    label_tax:               'Tax',
    label_total:             'Total',
    label_status:            'Status',
    status_draft:            'Draft',
    status_sent:             'Sent',
    status_approved:         'Approved',
    status_rejected:         'Rejected',
    btn_save_estimate:       'Save Estimate',
    btn_print_pdf:           'Print / PDF',
    btn_duplicate:           'Duplicate',
    btn_delete:              'Delete',

    // Shape preview modal
    btn_close:               'Close',

    // Mobile summary bar
    mobile_total:            'Total:',
    btn_save:                'Save',

    // Category labels (cost breakdown)
    cat_excavation:          'Excavation',
    cat_shell:               'Pool Shell',
    cat_finish:              'Interior Finish',
    cat_equipment:           'Equipment & Plumbing',
    cat_tile:                'Tile & Coping',
    cat_features:            'Features & Add-ons',
    cat_deck:                'Deck',
    cat_fence:               'Fencing',
    cat_other:               'Other',
    cat_custom:              'Custom Items',

    // ── Clients ───────────────────────────────────────────────────
    page_clients:            'Clients',
    page_new_client:         'New Client',
    page_edit_client:        'Edit Client',
    all_clients:             'All Clients',
    btn_new_client:          'New Client',
    search_clients:          'Search clients…',
    no_clients_yet:          'No clients yet',
    no_clients_msg:          'Add your first client to get started.',
    btn_add_client:          'Add Client',
    label_account_type:      'Account Type',
    label_created:           'Created',
    tier_priority:           'Priority',
    tier_standard:           'Standard',
    btn_edit:                'Edit',
    btn_cancel:              'Cancel',
    btn_save_client:         'Save Client',
    btn_delete_client:       'Delete Client',
    label_name:              'Name *',
    label_notes:             'Notes',
    estimates_count:         'Estimates',
    btn_new_estimate_for:    'New Estimate',
    no_estimates_client:     'No estimates for this client yet.',

    // ── Audit log ─────────────────────────────────────────────────
    page_audit_log:          'Audit Log',
    col_datetime:            'Date & Time',
    col_user:                'User',
    col_action:              'Action',
    col_type:                'Type',
    col_details:             'Details',
    col_ip:                  'IP Address',
    no_audit_yet:            'No audit entries yet',
    no_audit_msg:            'Activity will appear here when estimates or clients are saved.',
    filter_all_types:        'All Types',
    filter_estimates:        'Estimates',
    filter_settings:         'Settings',

    page_release_notes:      'Release Notes',

    // ── Settings ──────────────────────────────────────────────────
    page_settings:           'Settings',
    tab_business:            'Business',
    tab_pricing:             'Pricing',
    tab_security:            'Security',
    section_business_info:   'Business Information',
    label_business_name:     'Business Name',
    label_business_phone:    'Phone',
    label_business_email:    'Email',
    label_business_address:  'Address',
    section_estimate_settings:'Estimate Settings',
    label_currency_symbol:   'Currency Symbol',
    label_measure_unit:      'Unit of Measure',
    unit_ft_option:          'Feet (ft)',
    unit_m_option:           'Meters (m)',
    label_tax_rate:          'Default Tax Rate (%)',
    label_estimate_prefix:   'Estimate Number Prefix',
    label_validity_days:     'Validity (days)',
    label_standard_discount: 'Standard Account Rate Adjustment (%)',
    label_terms:             'Terms & Conditions',
    btn_save_settings:       'Save Settings',
    pricing_help:            'Adjust the unit prices below. These are used to auto-calculate estimates.',
    btn_save_pricing:        'Save Pricing',
    section_change_pin:      'Change Access PIN',
    label_current_pin:       'Current PIN',
    label_new_pin:           'New PIN',
    hint_min_4:              '(min 4 characters)',
    label_confirm_pin:       'Confirm New PIN',
    btn_change_pin:          'Change PIN',
    section_estimator_user:  'Estimator User',
    estimator_help:          'Enable a second user with a separate PIN. The Estimator user can create estimates and manage clients but cannot access Settings.',
    label_enable_estimator:  'Enable Estimator User',
    label_estimator_pin:     'Estimator PIN',
    hint_keep_current:       '— leave blank to keep current',
    label_confirm_estimator_pin: 'Confirm Estimator PIN',
    btn_save_estimator:      'Save Estimator Settings',

    // ── Login ─────────────────────────────────────────────────────
    login_subtitle:          'Pool Cost Estimator',
    label_enter_pin:         'Enter PIN to access',
    btn_unlock:              'Unlock',
    alert_session_expired:   'Your session has expired. Please log in again.',

    // ── Confirm dialog ────────────────────────────────────────────
    confirm_delete_title:    'Delete',
    confirm_delete_msg:      'Are you sure you want to delete this {type}? This action cannot be undone.',
    btn_confirm_delete:      'Delete',
    btn_cancel:              'Cancel',

    // ── Toast messages ────────────────────────────────────────────
    toast_estimate_saved:    'Estimate saved successfully!',
    toast_estimate_dup:      'Estimate duplicated!',
    toast_client_saved:      'Client saved!',
    toast_client_deleted:    'Client deleted.',
    toast_not_found:         'Not found.',
};
