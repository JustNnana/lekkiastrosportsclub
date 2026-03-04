<?php
/**
 * Lekki Astro Sports Club
 * Header / <head> + Navbar component
 * Requires: $pageTitle (string), optionally $pageCSS (string|array), $pageScript (string)
 */

requireLogin();
updateLastActivity();

// Load current user data
require_once dirname(__DIR__) . '/classes/User.php';
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    logoutUser();
    redirect('');
}

// Notification count
$db = Database::getInstance();
$notificationCount = (int)($db->fetchOne(
    "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0",
    [$_SESSION['user_id']]
)['cnt'] ?? 0);

// Unread announcements count
$unreadAnnouncements = 0;
try {
    $lastRead = $db->fetchOne(
        "SELECT last_read_at FROM user_announcement_reads WHERE user_id = ?",
        [$_SESSION['user_id']]
    );
    $lastReadTime = $lastRead['last_read_at'] ?? date('Y-m-d H:i:s', strtotime('-7 days'));
    $unreadAnnouncements = (int)($db->fetchOne(
        "SELECT COUNT(*) AS cnt FROM announcements WHERE created_at > ? AND is_published = 1",
        [$lastReadTime]
    )['cnt'] ?? 0);
} catch (Exception $e) {
    $unreadAnnouncements = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#00a76f" id="meta-theme-color">

    <!-- INSTANT THEME APPLY — must be first script to prevent flash -->
    <script>
    (function () {
        'use strict';
        var KEY = 'lasc-theme';
        var saved;
        try { saved = localStorage.getItem(KEY); } catch (e) {}
        var theme = (saved === 'dark' || saved === 'light')
            ? saved
            : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
        document.documentElement.classList.add('theme-' + theme);

        var meta = document.querySelector('#meta-theme-color');
        if (meta) meta.content = theme === 'dark' ? '#1c252e' : '#00a76f';
    })();
    </script>

    <title><?php echo e(SITE_NAME); ?> — <?php echo e($pageTitle ?? 'Dashboard'); ?></title>

    <!-- PWA Manifest -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>assets/images/icons/icon-96x96.png">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/images/icons/icon-57x57.png">

    <!-- Apple / iOS PWA -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo e(SITE_ABBR); ?>">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-touch-fullscreen" content="yes">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>assets/images/icons/icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo BASE_URL; ?>assets/images/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="120x120" href="<?php echo BASE_URL; ?>assets/images/icons/icon-120x120.png">
    <link rel="apple-touch-icon" sizes="76x76"   href="<?php echo BASE_URL; ?>assets/images/icons/icon-76x76.png">

    <!-- Android / Windows PWA -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="<?php echo e(SITE_NAME); ?>">
    <meta name="msapplication-TileImage" content="<?php echo BASE_URL; ?>assets/images/icons/icon-144x144.png">
    <meta name="msapplication-TileColor" content="#00a76f">

    <!-- Dependencies -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Dasher UI Design System -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-variables.css?v=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-core-styles.css?v=1.1">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-table-chart-styles.css?v=1.0">

    <!-- Layout (navbar, sidebar, content) — overrides core styles -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/layout.css?v=1.2">

    <!-- Page-specific CSS -->
    <?php if (isset($pageCSS)): ?>
        <?php foreach ((array)$pageCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo BASE_URL . e($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <!-- Left: sidebar toggle + logo -->
    <div class="d-flex align-items-center">
        <button class="sidebar-toggle-btn me-2" id="sidebar-toggle" aria-label="Toggle menu">
            <i class="fas fa-bars"></i>
        </button>
        <a class="navbar-brand" href="<?php echo BASE_URL; ?>dashboard/">
            <img src="<?php echo BASE_URL; ?>assets/images/icons/logo.png"
                 alt="<?php echo e(SITE_NAME); ?>" class="logo-light me-2">
            <img src="<?php echo BASE_URL; ?>assets/images/icons/logo-dark.png"
                 alt="<?php echo e(SITE_NAME); ?>" class="logo-dark me-2">
            <span class="d-none d-sm-inline"><?php echo e(SITE_NAME); ?></span>
        </a>
    </div>

    <!-- Desktop center: action icons -->
    <div class="navbar-nav d-none d-lg-flex">
        <a href="<?php echo BASE_URL; ?>announcements/" class="nav-icon-btn position-relative" title="Announcements">
            <i class="fas fa-bullhorn"></i>
            <?php if ($unreadAnnouncements > 0): ?>
                <span class="nav-badge"><?php echo $unreadAnnouncements > 9 ? '9+' : $unreadAnnouncements; ?></span>
            <?php endif; ?>
        </a>
        <a href="<?php echo BASE_URL; ?>notifications/index.php" class="nav-icon-btn position-relative" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($notificationCount > 0): ?>
                <span class="nav-badge"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
            <?php endif; ?>
        </a>
        <button class="theme-toggle theme-toggle-trigger" id="theme-toggle-btn" title="Toggle theme" aria-label="Toggle dark mode">
            <i class="fas fa-moon"></i>
        </button>
    </div>

    <!-- Right: mobile actions + desktop user area -->
    <div class="d-flex align-items-center ms-auto">

        <!-- Mobile only (d-lg-none) -->
        <div class="d-lg-none d-flex align-items-center">
            <button class="theme-toggle theme-toggle-trigger me-1" id="theme-toggle-btn-mobile" aria-label="Toggle theme">
                <i class="fas fa-moon"></i>
            </button>
            <a href="<?php echo BASE_URL; ?>notifications/index.php" class="nav-icon-btn position-relative me-1">
                <i class="fas fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="nav-badge"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo BASE_URL; ?>announcements/" class="nav-icon-btn position-relative me-2">
                <i class="fas fa-bullhorn"></i>
                <?php if ($unreadAnnouncements > 0): ?>
                    <span class="nav-badge"><?php echo $unreadAnnouncements > 9 ? '9+' : $unreadAnnouncements; ?></span>
                <?php endif; ?>
            </a>
            <button class="ios-header-menu-btn" id="iosHeaderMenuBtn" aria-label="Open menu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Desktop 3-dot -->
        <button class="ios-header-menu-btn d-none d-lg-flex me-3" id="iosHeaderMenuBtnDesktop" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>

        <!-- Desktop custom user dropdown -->
        <div class="desktop-user-dropdown d-none d-lg-block" id="desktopUserDropdown">
            <button class="desktop-dropdown-btn" type="button" id="desktopDropdownBtn" aria-expanded="false">
                <div class="user-avatar">
                    <?php echo e(getInitials($currentUser->getFullName())); ?>
                </div>
                <span class="d-none d-xl-inline-block ms-2 username-text">
                    <?php echo e($currentUser->getFullName()); ?>
                </span>
                <i class="fas fa-chevron-down desktop-dropdown-arrow ms-1"></i>
            </button>
            <div class="desktop-dropdown-menu" id="desktopDropdownMenu">
                <div class="desktop-dropdown-header">
                    <strong><?php echo e($currentUser->getFullName()); ?></strong>
                    <span class="desktop-dropdown-role"><?php echo e(ucwords(str_replace('_', ' ', $_SESSION['role'] ?? ''))); ?></span>
                </div>
                <div class="desktop-dropdown-divider"></div>
                <a class="desktop-dropdown-item" href="<?php echo BASE_URL; ?>profile/">
                    <i class="fas fa-user"></i> My Profile
                </a>
                <a class="desktop-dropdown-item" href="<?php echo BASE_URL; ?>admin/settings.php">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <?php if (isAdmin()): ?>
                <div class="desktop-dropdown-divider"></div>
                <a class="desktop-dropdown-item" href="<?php echo BASE_URL; ?>admin/admins.php">
                    <i class="fas fa-shield-alt"></i> Admin Panel
                </a>
                <?php endif; ?>
                <div class="desktop-dropdown-divider"></div>
                <a class="desktop-dropdown-item danger" href="<?php echo BASE_URL; ?>logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

    </div>
</nav>
<!-- ===== END NAVBAR ===== -->

<!-- ===== iOS MOBILE BOTTOM NAV ===== -->
<?php
$_requestUri = $_SERVER['REQUEST_URI'];
$_mobileNavActive = '';
if (strpos($_requestUri, '/profile/') !== false) {
    $_mobileNavActive = 'profile';
} elseif (strpos($_requestUri, '/payments/') !== false) {
    $_mobileNavActive = 'payments';
} elseif (strpos($_requestUri, '/tournaments/') !== false) {
    $_mobileNavActive = 'tournaments';
} elseif (strpos($_requestUri, '/events/') !== false) {
    $_mobileNavActive = 'events';
} elseif (strpos($_requestUri, '/dashboard/') !== false || preg_match('/\/index\.php$/', $_requestUri)) {
    $_mobileNavActive = 'home';
}
?>
<div class="mobile-bottom-nav d-lg-none">
    <div class="mobile-nav-items">
        <a href="<?php echo BASE_URL; ?>dashboard/" class="mobile-nav-item <?php echo $_mobileNavActive === 'home' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="<?php echo BASE_URL; ?>events/" class="mobile-nav-item <?php echo $_mobileNavActive === 'events' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Events</span>
        </a>
        <a href="<?php echo BASE_URL; ?>tournaments/" class="mobile-nav-center-btn">
            <div class="mobile-nav-plus-btn">
                <i class="fas fa-trophy"></i>
            </div>
            <span>Tournaments</span>
        </a>
        <a href="<?php echo BASE_URL; ?>payments/" class="mobile-nav-item <?php echo $_mobileNavActive === 'payments' ? 'active' : ''; ?>">
            <i class="fas fa-credit-card"></i>
            <span>Payments</span>
        </a>
        <a href="<?php echo BASE_URL; ?>profile/" class="mobile-nav-item <?php echo $_mobileNavActive === 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>
</div>

<!-- ===== iOS MENU BACKDROP ===== -->
<div class="ios-header-menu-backdrop" id="iosHeaderMenuBackdrop"></div>

<!-- ===== iOS MENU MODAL ===== -->
<div class="ios-header-menu-modal" id="iosHeaderMenuModal">
    <div class="ios-menu-handle"></div>

    <div class="ios-menu-user-header">
        <div class="ios-menu-user-avatar">
            <?php echo e(getInitials($currentUser->getFullName())); ?>
        </div>
        <div class="ios-menu-user-info">
            <p class="ios-menu-user-name"><?php echo e($currentUser->getFullName()); ?></p>
            <p class="ios-menu-user-role"><?php echo e(ucwords(str_replace('_', ' ', $_SESSION['role'] ?? ''))); ?></p>
        </div>
    </div>

    <div class="ios-menu-content">
        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>notifications/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon gray"><i class="fas fa-bell"></i></div>
                    <span class="ios-menu-item-label">Notifications<?php if ($notificationCount > 0): ?> <span class="badge bg-danger ms-1"><?php echo $notificationCount; ?></span><?php endif; ?></span>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>announcements/" class="ios-menu-item">
                    <div class="ios-menu-item-icon blue"><i class="fas fa-bullhorn"></i></div>
                    <span class="ios-menu-item-label">Announcements<?php if ($unreadAnnouncements > 0): ?> <span class="badge bg-danger ms-1"><?php echo $unreadAnnouncements; ?></span><?php endif; ?></span>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <!-- Account -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Account</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>profile/" class="ios-menu-item">
                    <div class="ios-menu-item-icon purple"><i class="fas fa-user"></i></div>
                    <span class="ios-menu-item-label">My Profile</span>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/settings.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon orange"><i class="fas fa-cog"></i></div>
                    <span class="ios-menu-item-label">Settings</span>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Administration -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Administration</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>admin/admins.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon primary"><i class="fas fa-shield-alt"></i></div>
                    <span class="ios-menu-item-label">Admin Panel</span>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Logout -->
        <div class="ios-menu-section">
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>logout.php" class="ios-menu-item danger">
                    <div class="ios-menu-item-icon red"><i class="fas fa-sign-out-alt"></i></div>
                    <span class="ios-menu-item-label">Logout</span>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Main wrapper -->
<div class="main-wrapper">
