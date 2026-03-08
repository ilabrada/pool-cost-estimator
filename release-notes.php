<?php
/**
 * Release Notes / Changelog
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAuth();
if (!isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$pageTitle = 'Release Notes';
include __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <h2 data-i18n="nav_release_notes">Release Notes</h2>
</div>

<div class="release-notes-list">

    <div class="release-entry">
        <div class="release-meta">
            <span class="release-date">March 8, 2026</span>
        </div>
        <ul class="release-changes">
            <li>Add Release Notes link to display compact changelog</li>
            <li>Add dual language support with English/Spanish toggle</li>
        </ul>
    </div>

    <div class="release-entry">
        <div class="release-meta">
            <span class="release-date">March 7, 2026</span>
        </div>
        <ul class="release-changes">
            <li>Classify clients as premium or not-premium with hidden discount logic</li>
            <li>Support multiple photos with navigation for each pool shape</li>
        </ul>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
