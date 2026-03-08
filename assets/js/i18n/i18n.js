/**
 * i18n.js — Lightweight internationalisation engine
 *
 * ─────────────────────────────────────────────────
 * HOW TO ADD A NEW LANGUAGE
 * ─────────────────────────────────────────────────
 * 1. Create  assets/js/i18n/<code>.js  (copy en.js, translate values).
 * 2. Add a <script> tag for the new file in includes/footer.php BEFORE
 *    the i18n.js script tag.
 * 3. Add an entry to SUPPORTED_LOCALES below.
 * 4. Add a matching <option> inside the #lang-select element in
 *    includes/header.php.
 *
 * Usage in HTML:
 *   <span data-i18n="key">Fallback text</span>
 *   <input data-i18n-placeholder="key" placeholder="Fallback">
 *   <input data-i18n-title="key" title="Fallback">
 *
 * Usage in JavaScript:
 *   i18n('key')              → translated string
 *   i18n('key', {type:'x'}) → string with {type} replaced by 'x'
 * ─────────────────────────────────────────────────
 */

(function () {
    'use strict';

    // ── Supported locales ─────────────────────────────────────────
    // Each entry: { code, label }
    const SUPPORTED_LOCALES = [
        { code: 'en', label: 'EN' },
        { code: 'es', label: 'ES' },
    ];

    const LS_KEY = 'pool_estimator_lang';
    const DEFAULT_LOCALE = 'en';

    // ── State ─────────────────────────────────────────────────────
    let currentLocale = DEFAULT_LOCALE;

    // ── Core translate function ───────────────────────────────────
    /**
     * Returns the translation for `key` in the active locale,
     * falling back to English, then the key itself.
     * @param {string} key
     * @param {Object} [vars]  Optional placeholder map, e.g. {type:'estimate'}
     */
    function i18n(key, vars) {
        const locales = window.I18N_LOCALES || {};
        let str =
            (locales[currentLocale] && locales[currentLocale][key]) ||
            (locales[DEFAULT_LOCALE] && locales[DEFAULT_LOCALE][key]) ||
            key;

        if (vars) {
            Object.keys(vars).forEach(k => {
                str = str.replace(new RegExp('\\{' + k + '\\}', 'g'), vars[k]);
            });
        }
        return str;
    }

    // ── DOM translation ───────────────────────────────────────────
    function applyTranslations() {
        // Text content
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            el.textContent = i18n(key);
        });

        // Placeholders
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            el.placeholder = i18n(el.getAttribute('data-i18n-placeholder'));
        });

        // Title / aria-label
        document.querySelectorAll('[data-i18n-title]').forEach(el => {
            el.title = i18n(el.getAttribute('data-i18n-title'));
        });

        // aria-label
        document.querySelectorAll('[data-i18n-aria]').forEach(el => {
            el.setAttribute('aria-label', i18n(el.getAttribute('data-i18n-aria')));
        });

        // Update the <html lang> attribute
        document.documentElement.lang = currentLocale;

        // Dispatch event so other scripts can react
        document.dispatchEvent(new CustomEvent('i18n:applied', { detail: { locale: currentLocale } }));
    }

    // ── Locale switching ──────────────────────────────────────────
    function setLocale(code) {
        const supported = SUPPORTED_LOCALES.map(l => l.code);
        if (!supported.includes(code)) code = DEFAULT_LOCALE;
        currentLocale = code;
        try { localStorage.setItem(LS_KEY, code); } catch (_) { /* private browsing */ }
        applyTranslations();

        // Keep the select widget in sync
        const sel = document.getElementById('lang-select');
        if (sel && sel.value !== code) sel.value = code;
    }

    // ── Initialise ────────────────────────────────────────────────
    function init() {
        // Restore preference from localStorage
        let saved = DEFAULT_LOCALE;
        try { saved = localStorage.getItem(LS_KEY) || DEFAULT_LOCALE; } catch (_) { /* */ }
        currentLocale = saved;

        // Wire up the toggle widget once the DOM is ready
        const sel = document.getElementById('lang-select');
        if (sel) {
            sel.value = currentLocale;
            sel.addEventListener('change', () => setLocale(sel.value));
        }

        applyTranslations();
    }

    // ── Public API ────────────────────────────────────────────────
    window.i18n       = i18n;
    window.setLocale  = setLocale;
    window.I18N_LOCALES = window.I18N_LOCALES || {};

    // Run after everything is painted
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
