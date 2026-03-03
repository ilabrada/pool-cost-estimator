<?php
/**
 * Common Header Template
 * Variables expected: $pageTitle, $bodyClass (optional)
 */
$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#0077B6">
    <title><?= e($pageTitle) ?></title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏊</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="<?= e($bodyClass) ?>">

<?php if (isLoggedIn()): ?>
<!-- Top App Bar -->
<header class="app-header">
    <div class="header-left">
        <button class="btn-icon menu-toggle" onclick="toggleSidebar()" aria-label="Menu">
            <span class="material-icons-round">menu</span>
        </button>
        <h1 class="header-title"><?= e($pageTitle) ?></h1>
    </div>
    <div class="header-right">
        <a href="estimate.php" class="btn btn-primary btn-sm header-new-btn">
            <span class="material-icons-round">add</span>
            <span class="btn-label">New Estimate</span>
        </a>
    </div>
</header>

<!-- Sidebar Navigation (desktop) / Overlay (mobile) -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <span class="sidebar-logo">🏊</span>
        <span class="sidebar-brand"><?= e(getSetting('business_name', APP_NAME)) ?></span>
    </div>
    <ul class="sidebar-nav">
        <li class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <a href="dashboard.php">
                <span class="material-icons-round">dashboard</span>
                <span>Dashboard</span>
            </a>
        </li>
        <li class="<?= $currentPage === 'estimate' ? 'active' : '' ?>">
            <a href="estimate.php">
                <span class="material-icons-round">add_circle</span>
                <span>New Estimate</span>
            </a>
        </li>
        <li class="<?= $currentPage === 'clients' ? 'active' : '' ?>">
            <a href="clients.php">
                <span class="material-icons-round">people</span>
                <span>Clients</span>
            </a>
        </li>
        <li class="<?= $currentPage === 'audit-log' ? 'active' : '' ?>">
            <a href="audit-log.php">
                <span class="material-icons-round">history</span>
                <span>Audit Log</span>
            </a>
        </li>
        <?php if (isAdmin()): ?>
        <li class="<?= $currentPage === 'settings' ? 'active' : '' ?>">
            <a href="settings.php">
                <span class="material-icons-round">settings</span>
                <span>Settings</span>
            </a>
        </li>
        <?php endif; ?>
        <li class="sidebar-divider"></li>
        <li>
            <a href="logout.php">
                <span class="material-icons-round">logout</span>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</nav>

<!-- Bottom Navigation (mobile) -->
<nav class="bottom-nav">
    <a href="dashboard.php" class="bottom-nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
        <span class="material-icons-round">dashboard</span>
        <span>Home</span>
    </a>
    <a href="estimate.php" class="bottom-nav-item <?= $currentPage === 'estimate' ? 'active' : '' ?>">
        <span class="material-icons-round">add_circle</span>
        <span>Estimate</span>
    </a>
    <a href="clients.php" class="bottom-nav-item <?= $currentPage === 'clients' ? 'active' : '' ?>">
        <span class="material-icons-round">people</span>
        <span>Clients</span>
    </a>
    <a href="audit-log.php" class="bottom-nav-item <?= $currentPage === 'audit-log' ? 'active' : '' ?>">
        <span class="material-icons-round">history</span>
        <span>Log</span>
    </a>
    <?php if (isAdmin()): ?>
    <a href="settings.php" class="bottom-nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
        <span class="material-icons-round">settings</span>
        <span>Settings</span>
    </a>
    <?php endif; ?>
</nav>

<!-- Main Content -->
<main class="main-content">
<?php endif; ?>
