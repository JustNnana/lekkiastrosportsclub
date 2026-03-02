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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-core-styles.css?v=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-table-chart-styles.css?v=1.0">

    <!-- Page-specific CSS -->
    <?php if (isset($pageCSS)): ?>
        <?php foreach ((array)$pageCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo BASE_URL . e($css); ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <style>
        /* ===== NAVBAR ===== */
        .navbar {
            background-color: var(--bg-navbar);
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            position: fixed;
            top: 0; left: 0; right: 0;
            height: var(--navbar-height);
            z-index: 1030;
            padding: 0 var(--spacing-6);
            display: flex;
            align-items: center;
            transition: var(--theme-transition);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            text-decoration: none;
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            transition: var(--transition-fast);
        }
        .navbar-brand:hover { color: var(--primary); }
        .brand-icon {
            width: 34px; height: 34px; flex-shrink: 0;
            border-radius: var(--border-radius);
            background: linear-gradient(135deg, var(--primary), #007867);
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: var(--font-weight-bold);
            letter-spacing: 0.5px;
        }

        .navbar-nav {
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            margin-left: auto;
        }

        /* Mobile hamburger */
        .sidebar-toggle-btn {
            display: none;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 1.25rem;
            padding: var(--spacing-2);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition-fast);
        }
        .sidebar-toggle-btn:hover { background-color: var(--bg-hover); }

        @media (max-width: 768px) {
            .sidebar-toggle-btn { display: flex; align-items: center; }
            .navbar { padding: 0 var(--spacing-4); }
        }

        /* Nav icon buttons */
        .nav-icon-btn {
            position: relative;
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            background: transparent;
            border: none;
            border-radius: var(--border-radius-full);
            color: var(--text-primary);
            font-size: var(--font-size-lg);
            cursor: pointer;
            text-decoration: none;
            transition: var(--transition-fast);
        }
        .nav-icon-btn:hover {
            background-color: var(--bg-hover);
            color: var(--primary);
        }

        .nav-badge {
            position: absolute;
            top: 2px; right: 2px;
            background: var(--danger);
            color: #fff;
            border-radius: var(--border-radius-full);
            min-width: 16px; height: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px;
            font-weight: var(--font-weight-bold);
            border: 2px solid var(--bg-navbar);
            line-height: 1;
        }

        /* Theme toggle */
        .theme-toggle {
            width: 40px; height: 40px;
            background: transparent;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-full);
            color: var(--text-primary);
            display: flex; align-items: center; justify-content: center;
            font-size: var(--font-size-base);
            cursor: pointer;
            transition: var(--transition-fast);
        }
        .theme-toggle:hover { background-color: var(--bg-hover); border-color: var(--primary); }

        /* User avatar */
        .user-avatar {
            width: 36px; height: 36px;
            border-radius: var(--border-radius-full);
            background: linear-gradient(135deg, var(--primary), var(--primary-700));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-sm);
            cursor: pointer;
            transition: var(--transition-fast);
            border: 2px solid transparent;
            flex-shrink: 0;
        }
        .user-avatar:hover { transform: scale(1.05); border-color: var(--primary); }

        /* User dropdown */
        .user-dropdown .dropdown-menu {
            min-width: 220px;
            right: 0; left: auto;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl);
            padding: var(--spacing-2) 0;
        }
        .user-dropdown .dropdown-header {
            padding: var(--spacing-4);
            border-bottom: 1px solid var(--border-color);
        }
        .user-dropdown .dropdown-header .user-name {
            font-weight: var(--font-weight-semibold);
            color: var(--text-primary);
            font-size: var(--font-size-sm);
            margin: 0;
        }
        .user-dropdown .dropdown-header .user-role {
            font-size: var(--font-size-xs);
            color: var(--text-muted);
            text-transform: capitalize;
        }
        .user-dropdown .dropdown-item {
            display: flex; align-items: center;
            gap: var(--spacing-3);
            padding: var(--spacing-3) var(--spacing-4);
            color: var(--text-primary);
            font-size: var(--font-size-sm);
            transition: var(--transition-fast);
        }
        .user-dropdown .dropdown-item:hover {
            background: var(--bg-hover);
            color: var(--primary);
        }
        .user-dropdown .dropdown-item.text-danger:hover { color: var(--danger) !important; }
        .user-dropdown .dropdown-divider { border-color: var(--border-color); margin: var(--spacing-1) 0; }

        /* Flash notifications */
        .flash-notification {
            position: fixed;
            top: 76px; right: 20px;
            min-width: 300px; max-width: 400px;
            padding: 12px 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            z-index: 9999;
            display: flex; align-items: center; gap: 10px;
            font-size: var(--font-size-sm);
            animation: slideInRight 0.3s ease;
        }
        .flash-notification.success { background: var(--success); color: #fff; }
        .flash-notification.error   { background: var(--danger);  color: #fff; }
        .flash-notification.warning { background: var(--warning); color: #fff; }
        .flash-notification.info    { background: var(--info);    color: #fff; }
        @keyframes slideInRight {
            from { transform: translateX(420px); opacity: 0; }
            to   { transform: translateX(0);     opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0);     opacity: 1; }
            to   { transform: translateX(420px); opacity: 0; }
        }
        @media (max-width: 768px) {
            .flash-notification { right: 10px; left: 10px; min-width: auto; max-width: none; top: 70px; }
        }

        /* PWA install banner */
        .pwa-banner {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            box-shadow: 0 -2px 10px rgba(0,0,0,.1);
            padding: var(--spacing-3) var(--spacing-5);
            z-index: 1050;
            display: none;
        }
        .pwa-banner-content { display: flex; align-items: center; gap: var(--spacing-3); max-width: 600px; margin: 0 auto; }
        .pwa-banner-icon img { width: 40px; height: 40px; border-radius: 8px; }
        .pwa-banner-text { flex: 1; }
        .pwa-banner-text p { margin: 0; font-size: var(--font-size-sm); color: var(--text-primary); font-weight: var(--font-weight-medium); }
        .pwa-banner-text small { color: var(--text-muted); }
    </style>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="navbar">
    <!-- Mobile toggle -->
    <button class="sidebar-toggle-btn me-2" id="sidebar-toggle" aria-label="Toggle menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Brand -->
    <a class="navbar-brand" href="<?php echo BASE_URL; ?>dashboard/">
        <div class="brand-icon"><?php echo e(SITE_ABBR); ?></div>
        <span class="d-none d-md-inline"><?php echo e(SITE_NAME); ?></span>
        <span class="d-md-none d-sm-inline"><?php echo e(SITE_ABBR); ?></span>
    </a>

    <!-- Right-side actions -->
    <div class="navbar-nav">

        <!-- Announcements bell -->
        <a href="<?php echo BASE_URL; ?>announcements/"
           class="nav-icon-btn"
           title="Announcements">
            <i class="fas fa-bullhorn"></i>
            <?php if ($unreadAnnouncements > 0): ?>
                <span class="nav-badge"><?php echo $unreadAnnouncements > 9 ? '9+' : $unreadAnnouncements; ?></span>
            <?php endif; ?>
        </a>

        <!-- Notifications bell -->
        <a href="<?php echo BASE_URL; ?>notifications/index.php"
           class="nav-icon-btn"
           title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($notificationCount > 0): ?>
                <span class="nav-badge"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
            <?php endif; ?>
        </a>

        <!-- Dark/light toggle -->
        <button class="theme-toggle" id="theme-toggle-btn" title="Toggle theme" aria-label="Toggle dark mode">
            <i class="fas fa-sun" id="theme-icon-light"></i>
            <i class="fas fa-moon" id="theme-icon-dark" style="display:none"></i>
        </button>

        <!-- User dropdown -->
        <div class="dropdown user-dropdown">
            <div class="user-avatar" data-bs-toggle="dropdown" aria-expanded="false"
                 title="<?php echo e($currentUser->getFullName()); ?>">
                <?php echo e(getInitials($currentUser->getFullName())); ?>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="dropdown-header">
                    <p class="user-name"><?php echo e($currentUser->getFullName()); ?></p>
                    <span class="user-role"><?php echo e(str_replace('_', ' ', $_SESSION['role'] ?? '')); ?></span>
                </li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile/">
                    <i class="fas fa-user fa-fw"></i> My Profile
                </a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile/edit.php">
                    <i class="fas fa-cog fa-fw"></i> Settings
                </a></li>
                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile/notifications.php">
                    <i class="fas fa-bell fa-fw"></i> Notification Settings
                </a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                    <i class="fas fa-sign-out-alt fa-fw"></i> Logout
                </a></li>
            </ul>
        </div>

    </div><!-- /navbar-nav -->
</nav>
<!-- ===== END NAVBAR ===== -->

<!-- Main wrapper -->
<div class="main-wrapper">
