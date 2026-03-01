<?php

/**
 * Gate Wey Access Management System
 * Header Component - DASHER UI with Custom Dark Header
 */

// Check if user is logged in, if not, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL);
    exit;
}

// Get current user data
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    // If user doesn't exist, clear session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

// Check if user's clan payment status is inactive and redirect to dashboard
// Super admins are exempt from this check
if (!$currentUser->isSuperAdmin() && $currentUser->getClanId()) {
    $clan = new Clan();
    if ($clan->loadById($currentUser->getClanId())) {
        if ($clan->getPaymentStatus() === 'inactive') {
            // Get current page
            $currentScript = basename($_SERVER['PHP_SELF']);

            // Allow access only to dashboard, profile, logout, and payment-related pages
            $allowedPages = [
                'index.php',          // Dashboard index
                'clan-admin.php',     // Clan admin dashboard
                'super-admin.php',    // Super admin dashboard
                'guard.php',          // Guard dashboard
                'member.php',         // Member dashboard
                'profile.php',        // User profile
                'logout.php',         // Logout
                'edit-profile.php',   // Edit profile
                'index.php',          // Payments section
                'process.php',        // Payment processing
                'status.php',         // Payment status
                'history.php',        // Payment history
                'settings.php',       // Clan settings (to make payment)
            ];

            // Check if we're on a dashboard or payment page
            $isOnDashboard = strpos($_SERVER['REQUEST_URI'], '/dashboard/') !== false;
            $isOnPayments = strpos($_SERVER['REQUEST_URI'], '/payments/') !== false;
            $isOnProfile = strpos($_SERVER['REQUEST_URI'], '/profile/') !== false;
            $isOnClanSettings = strpos($_SERVER['REQUEST_URI'], '/clans/settings.php') !== false;
            $isOnLogout = strpos($_SERVER['REQUEST_URI'], '/logout.php') !== false;

            // If not on allowed pages, redirect to dashboard
            if (!in_array($currentScript, $allowedPages) &&
                !$isOnDashboard &&
                !$isOnPayments &&
                !$isOnProfile &&
                !$isOnClanSettings &&
                !$isOnLogout) {

                // Set a session message to inform the user
                $_SESSION['warning_message'] = 'Your estate subscription is inactive. Please renew your subscription to access all features.';
                header('Location: ' . BASE_URL . 'dashboard/');
                exit;
            }
        }
    }
}

$unifiedMessageCount = 0;
if (isset($currentUser) && method_exists($currentUser, 'getId')) {
    // Only load the UnifiedMessage class if it hasn't been loaded yet
    if (!class_exists('UnifiedMessage')) {
        require_once __DIR__ . '/../classes/UnifiedMessage.php';
    }

    $unifiedMessage = new UnifiedMessage();
    $unifiedMessageCount = $unifiedMessage->getTotalUnreadCount($currentUser->getId());
}

// Get current page for sidebar active state
$currentPage = basename($_SERVER['PHP_SELF']);

// Get user notification count
$notificationCount = 0;
$db = Database::getInstance();
$headerUserClanId = isset($currentUser) && is_object($currentUser) ? $currentUser->getClanId() : null;
if ($headerUserClanId) {
    $notificationsResult = $db->fetchOne(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND (clan_id = ? OR clan_id IS NULL) AND is_read = 0",
        [$_SESSION['user_id'], $headerUserClanId]
    );
} else {
    $notificationsResult = $db->fetchOne(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
        [$_SESSION['user_id']]
    );
}
if ($notificationsResult) {
    $notificationCount = $notificationsResult['count'];
}

// Get unread announcements count
$unreadAnnouncementsCount = 0;
try {
    $lastRead = $db->fetchOne(
        "SELECT last_read_at FROM user_announcement_reads WHERE user_id = ?",
        [$_SESSION['user_id']]
    );
    
    $lastReadTime = $lastRead ? $lastRead['last_read_at'] : date('Y-m-d H:i:s', strtotime('-7 days'));
    
    $unreadQuery = $db->fetchOne(
        "SELECT COUNT(*) as count 
         FROM announcements 
         WHERE clan_id = ? 
         AND created_at > ?",
        [$currentUser->getClanId(), $lastReadTime]
    );
    
    $unreadAnnouncementsCount = $unreadQuery ? (int)$unreadQuery['count'] : 0;
} catch (Exception $e) {
    $unreadAnnouncementsCount = 0;
}

// Function to check if a menu item is active
function isMenuActive($page)
{
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

// Get current date
$currentDate = date('m/d/Y');

// Get greeting based on time of day
$hour = date('H');
$greeting = '';
if ($hour < 12) {
    $greeting = 'Good Morning';
} elseif ($hour < 18) {
    $greeting = 'Good Afternoon';
} else {
    $greeting = 'Good Evening';
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#00a76f">

    <!-- DASHER UI INSTANT THEME APPLICATION -->
    <script>
        (function() {
            'use strict';
            const THEME_KEY = 'gatewey-dasher-theme';

            function getTheme() {
                try {
                    const saved = localStorage.getItem(THEME_KEY);
                    if (saved === 'dark' || saved === 'light') return saved;
                } catch (e) {}
                return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
            }

            const theme = getTheme();
            const html = document.documentElement;

            // Apply Dasher UI theme attributes
            html.setAttribute('data-theme', theme);
            html.setAttribute('data-bs-theme', theme);

            // Add theme classes for compatibility
            if (theme === 'dark') {
                html.classList.add('theme-dark');
                html.classList.remove('theme-light');
            } else {
                html.classList.add('theme-light');
                html.classList.remove('theme-dark');
            }

            // Update meta theme color immediately
            const metaThemeColor = document.querySelector('meta[name="theme-color"]');
            if (metaThemeColor) {
                metaThemeColor.content = theme === 'dark' ? '#1c252e' : '#00a76f';
            }
        })();
    </script>

    <!-- PWA and Icon Meta Tags -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <!-- Push Notifications Script -->
    <script src="<?php echo BASE_URL; ?>assets/js/push-notifications.js"></script>
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo BASE_URL; ?>assets/images/icons/icon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo BASE_URL; ?>assets/images/icons/icon-16x16.png">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/images/icons/icon-57x57.png">

    <!-- Apple Touch Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo BASE_URL; ?>assets/images/icons/icon-180x180.png">
    <link rel="apple-touch-icon" sizes="152x152" href="<?php echo BASE_URL; ?>assets/images/icons/icon-152x152.png">
    <link rel="apple-touch-icon" sizes="144x144" href="<?php echo BASE_URL; ?>assets/images/icons/icon-144x144.png">
    <link rel="apple-touch-icon" sizes="120x120" href="<?php echo BASE_URL; ?>assets/images/icons/icon-120x120.png">
    <link rel="apple-touch-icon" sizes="114x114" href="<?php echo BASE_URL; ?>assets/images/icons/icon-114x114.png">
    <link rel="apple-touch-icon" sizes="76x76" href="<?php echo BASE_URL; ?>assets/images/icons/icon-76x76.png">
    <link rel="apple-touch-icon" sizes="72x72" href="<?php echo BASE_URL; ?>assets/images/icons/icon-72x72.png">
    <link rel="apple-touch-icon" sizes="60x60" href="<?php echo BASE_URL; ?>assets/images/icons/icon-60x60.png">
    <link rel="apple-touch-icon" sizes="57x57" href="<?php echo BASE_URL; ?>assets/images/icons/icon-57x57.png">

    <!-- Fallback for devices that don't specify size -->
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icons/icon-180x180.png">

    <!-- iOS PWA meta tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Gate Wey">
    <meta name="format-detection" content="telephone=no">
    <meta name="apple-touch-fullscreen" content="yes">

    <!-- Android PWA meta tags -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Gate Wey">

    <!-- Windows PWA meta tags -->
    <meta name="msapplication-TileImage" content="<?php echo BASE_URL; ?>assets/images/icons/icon-144x144.png">
    <meta name="msapplication-TileColor" content="#00a76f">
    <meta name="msapplication-config" content="<?php echo BASE_URL; ?>browserconfig.xml">

    <!-- Enhanced PWA Install Prompt Handler -->
    <script>
        (function() {
            'use strict';

            // Store the beforeinstallprompt event
            let deferredPrompt;
            let installButton = null;

            // PWA Install Prompt Handler
            window.addEventListener('beforeinstallprompt', (e) => {
                // Prevent the mini-infobar from appearing on mobile
                e.preventDefault();

                // Store the event so it can be triggered later
                deferredPrompt = e;

                // Optional: Show your own install button
                showInstallButton();
            });

            // Handle PWA installation
            window.addEventListener('appinstalled', (e) => {
                // Hide install button if it exists
                hideInstallButton();

                // Clear the stored prompt
                deferredPrompt = null;

                // Optional: Track installation analytics
                if (typeof gtag !== 'undefined') {
                    gtag('event', 'pwa_install', {
                        event_category: 'engagement',
                        event_label: 'GateWey PWA Installed'
                    });
                }
            });

            // Function to show install button (optional)
            function showInstallButton() {
                // You can create a custom install button here
                // For now, we'll just make the install available programmatically
                window.showPWAInstall = function() {
                    if (deferredPrompt) {
                        deferredPrompt.prompt();

                        deferredPrompt.userChoice.then((choiceResult) => {
                            deferredPrompt = null;
                        });
                    }
                };
            }

            function hideInstallButton() {
                if (installButton) {
                    installButton.style.display = 'none';
                }
            }

            // PWA Mode Detection
            function detectPWAMode() {
                const isStandalone = window.matchMedia('(display-mode: standalone)').matches;
                const isIOSStandalone = window.navigator.standalone === true;
                const isPWA = isStandalone || isIOSStandalone;

                if (isPWA) {
                    document.body.classList.add('pwa-mode');

                    // Hide install button if in PWA mode
                    hideInstallButton();
                } else {
                    document.body.classList.add('browser-mode');
                }

                return isPWA;
            }

            // Run detection when DOM is ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', detectPWAMode);
            } else {
                detectPWAMode();
            }

            // Watch for display mode changes
            const standaloneMedia = window.matchMedia('(display-mode: standalone)');
            if (standaloneMedia.addEventListener) {
                standaloneMedia.addEventListener('change', detectPWAMode);
            } else if (standaloneMedia.addListener) {
                standaloneMedia.addListener(detectPWAMode);
            }
        })();
    </script>

    <title><?php echo SITE_NAME; ?> - <?php echo $pageTitle ?? 'Dashboard'; ?></title>

    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- PURE DASHER UI CSS SYSTEM -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-variables.css?v=2.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-core-styles.css?v=2.1">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-table-chart-styles.css?v=2.0">

    <!-- Page-specific CSS -->
    <?php if (isset($pageCSS) && is_array($pageCSS)): ?>
        <?php foreach ($pageCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo BASE_URL . $css; ?>">
        <?php endforeach; ?>
    <?php elseif (isset($pageCSS)): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL . $pageCSS; ?>">
    <?php endif; ?>

    <!-- Custom Header Styling - Responsive to Theme -->
    <style>
        /* RESPONSIVE HEADER STYLING - ADAPTS TO LIGHT/DARK THEME */
        .navbar {
            background-color: var(--bg-navbar);
            border-bottom: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--navbar-height, 60px);
            z-index: 1030;
            padding: 0 var(--spacing-6, 1.5rem);
            display: flex;
            align-items: center;
            transition: var(--theme-transition);
            backdrop-filter: blur(10px);
        }

        /* Header elements - responsive to theme */
        .navbar-brand {
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            transition: var(--theme-transition);
        }

        .navbar-brand:hover {
            color: var(--primary);
        }

        .navbar-nav {
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
            margin-left: auto;
        }

        .mobile-menu-btn {
            border: none;
            background: transparent;
            padding: var(--spacing-2);
            margin-right: var(--spacing-2);
            color: var(--text-primary);
            border-radius: var(--border-radius);
            transition: var(--theme-transition);
            font-size: var(--font-size-lg);
        }

        .mobile-menu-btn:hover {
            background-color: var(--bg-hover);
        }

        /* Theme toggle - responsive styling */
        .theme-toggle {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            border-radius: var(--border-radius-full);
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-fast);
            font-size: var(--font-size-lg);
        }

        .theme-toggle:hover {
            background-color: var(--bg-hover);
            border-color: var(--primary);
        }

        /* Notification badges - responsive to theme */
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--danger);
            color: white;
            border-radius: var(--border-radius-full);
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-xs);
            font-weight: var(--font-weight-bold);
            border: 2px solid var(--bg-navbar);
        }

        /* User avatar - responsive to theme */
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: var(--border-radius-full);
            background: linear-gradient(135deg, var(--primary), var(--primary-600));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: var(--font-weight-semibold);
            font-size: var(--font-size-sm);
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow);
        }

        /* Navigation buttons - responsive to theme */
        .nav-btn {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: var(--border-radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: transparent;
            border: none;
            color: var(--text-primary);
            transition: var(--transition-fast);
            text-decoration: none;
        }

        .nav-btn:hover,
        .nav-btn:focus {
            background-color: var(--bg-hover);
            color: var(--text-primary);
            text-decoration: none;
        }

        /* Date display - responsive to theme */
        .date-display .btn {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
            padding: var(--spacing-2) var(--spacing-3);
            border-radius: var(--border-radius);
            transition: var(--transition-fast);
        }

        .date-display .btn:hover {
            background-color: var(--bg-hover);
            color: var(--text-primary);
        }

        /* Dropdown menu styling - follows Dasher UI theme */
        .dropdown-menu {
            background-color: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            padding: var(--spacing-2) 0;
            min-width: 200px;
            margin-top: var(--spacing-2);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-2) var(--spacing-4);
            color: var(--text-primary);
            text-decoration: none;
            font-size: var(--font-size-sm);
            transition: var(--transition-fast);
        }

        .dropdown-item:hover {
            background-color: var(--bg-hover);
            color: var(--primary);
        }

        .dropdown-header {
            padding: var(--spacing-3) var(--spacing-4);
            color: var(--text-primary);
            font-size: var(--font-size-sm);
        }

        .dropdown-divider {
            height: 0;
            margin: var(--spacing-2) 0;
            border-top: 1px solid var(--border-color);
        }

        /* Username text in header */
        .username-text {
            color: #ffffff !important;
            /* Always white in header */
            font-weight: var(--font-weight-medium);
        }

        /* Custom Desktop Dropdown (No Bootstrap) */
        .desktop-user-dropdown {
            position: relative;
        }

        .desktop-dropdown-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            border: none;
            padding: 6px 10px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .desktop-dropdown-btn:hover {
            background: var(--bg-hover);
        }

        .desktop-dropdown-arrow {
            font-size: 10px;
            color: var(--text-secondary);
            transition: transform 0.2s ease;
        }

        .desktop-user-dropdown.open .desktop-dropdown-arrow {
            transform: rotate(180deg);
        }

        .desktop-dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 220px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: opacity 0.2s ease, transform 0.2s ease, visibility 0.2s ease;
            z-index: 1050;
            overflow: hidden;
        }

        .desktop-user-dropdown.open .desktop-dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .desktop-dropdown-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .desktop-dropdown-header strong {
            font-size: 14px;
            color: var(--text-primary);
        }

        .desktop-dropdown-role {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: capitalize;
        }

        .desktop-dropdown-divider {
            height: 1px;
            background: var(--border-color);
            margin: 4px 0;
        }

        .desktop-dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            transition: background 0.15s ease;
        }

        .desktop-dropdown-item:hover {
            background: var(--bg-hover);
            text-decoration: none;
            color: var(--text-primary);
        }

        .desktop-dropdown-item i {
            width: 16px;
            text-align: center;
            color: var(--text-secondary);
        }

        .desktop-dropdown-item.danger {
            color: #ff3b30;
        }

        .desktop-dropdown-item.danger i {
            color: #ff3b30;
        }

        .desktop-dropdown-item.danger:hover {
            background: rgba(255, 59, 48, 0.1);
        }

        /* Logo styling - keep your existing approach */
        .logo-light,
        .logo-dark {
            width: 120px;
            height: auto;
            transition: var(--transition-normal);
        }

        /* Your existing logo switching logic */
        .logo-light {
            display: block;
        }

        .logo-dark {
            display: none;
        }

        [data-theme="dark"] .logo-light {
            display: none;
        }

        [data-theme="dark"] .logo-dark {
            display: block;
        }

        /* iOS-Style Mobile Bottom Navigation */
        .mobile-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: var(--bg-navbar);
            display: none;
            box-shadow: 0 -1px 10px rgba(0,0,0,0.1);
            z-index: 1020;
            border-top: 1px solid var(--border-color);
            padding: 8px 0 calc(8px + env(safe-area-inset-bottom, 0px));
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .mobile-nav-items {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            padding: 0 8px;
        }

        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 6px 12px;
            font-size: 10px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
            min-width: 56px;
            position: relative;
        }

        .mobile-nav-item:hover,
        .mobile-nav-item.active {
            color: var(--primary);
            text-decoration: none;
        }

        .mobile-nav-item.active i {
            color: var(--primary);
        }

        .mobile-nav-item i {
            font-size: 22px;
            margin-bottom: 4px;
            transition: all 0.2s ease;
        }

        .mobile-nav-item span {
            line-height: 1.2;
        }

        /* Center Plus Button - iOS Style */
        .mobile-nav-center-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            position: relative;
            margin-top: -20px;
        }

        .mobile-nav-plus-btn {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00a76f 0%, #00875a 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 15px rgba(0, 167, 111, 0.4);
            transition: all 0.3s ease;
            border: 3px solid var(--bg-navbar);
        }

        .mobile-nav-plus-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 20px rgba(0, 167, 111, 0.5);
        }

        .mobile-nav-plus-btn:active {
            transform: scale(0.95);
        }

        .mobile-nav-center-btn span {
            font-size: 10px;
            font-weight: 500;
            color: var(--primary);
            margin-top: 4px;
        }

        /* iOS 3-Dot Menu Button */
        .ios-header-menu-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ios-header-menu-btn:hover {
            background: var(--bg-hover);
        }

        .ios-header-menu-btn:active {
            transform: scale(0.95);
        }

        .ios-header-menu-btn i {
            font-size: 16px;
        }

        /* iOS Slide-up Menu Modal */
        .ios-header-menu-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 9998;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .ios-header-menu-backdrop.active {
            opacity: 1;
            visibility: visible;
        }

        .ios-header-menu-modal {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-card);
            border-radius: 20px 20px 0 0;
            z-index: 9999;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .ios-header-menu-modal.active {
            transform: translateY(0);
        }

        /* Desktop adjustments for iOS menu modal */
        @media (min-width: 992px) {
            .ios-header-menu-modal {
                max-width: 400px;
                left: auto;
                right: 20px;
                border-radius: 16px;
                bottom: auto;
                top: 70px;
                max-height: calc(100vh - 100px);
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                transform: translateY(-10px);
                opacity: 0;
                visibility: hidden;
                transition: transform 0.2s ease, opacity 0.2s ease, visibility 0.2s ease;
            }

            .ios-header-menu-modal.active {
                transform: translateY(0);
                opacity: 1;
                visibility: visible;
            }

            .ios-menu-handle {
                display: none;
            }
        }

        .ios-menu-handle {
            width: 36px;
            height: 5px;
            background: var(--border-color);
            border-radius: 3px;
            margin: 8px auto 4px;
            flex-shrink: 0;
        }

        .ios-menu-user-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .ios-menu-user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-600));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .ios-menu-user-info {
            flex: 1;
        }

        .ios-menu-user-name {
            font-size: 17px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .ios-menu-user-role {
            font-size: 13px;
            color: var(--text-secondary);
            margin: 0;
            text-transform: capitalize;
        }

        .ios-menu-content {
            padding: 12px 16px 32px;
            overflow-y: auto;
            flex: 1;
            -webkit-overflow-scrolling: touch;
        }

        .ios-menu-section {
            margin-bottom: 16px;
        }

        .ios-menu-section:last-child {
            margin-bottom: 0;
        }

        .ios-menu-section-title {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            padding-left: 4px;
        }

        .ios-menu-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
        }

        .ios-menu-item {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            text-decoration: none;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            transition: background 0.15s ease;
        }

        .ios-menu-item:last-child {
            border-bottom: none;
        }

        .ios-menu-item:active {
            background: var(--bg-subtle);
        }

        .ios-menu-item-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: white;
            margin-right: 14px;
            flex-shrink: 0;
        }

        .ios-menu-item-icon.primary { background: var(--primary); }
        .ios-menu-item-icon.blue { background: #007aff; }
        .ios-menu-item-icon.orange { background: #ff9500; }
        .ios-menu-item-icon.purple { background: #af52de; }
        .ios-menu-item-icon.red { background: #ff3b30; }
        .ios-menu-item-icon.green { background: #34c759; }
        .ios-menu-item-icon.gray { background: #8e8e93; }

        .ios-menu-item-label {
            flex: 1;
            font-size: 16px;
            font-weight: 500;
        }

        .ios-menu-item-chevron {
            color: var(--text-muted);
            font-size: 14px;
        }

        .ios-menu-item.danger {
            color: #ff3b30;
        }

        .ios-menu-item.danger .ios-menu-item-label {
            color: #ff3b30;
        }

        /* Quick actions menu - follows Dasher UI theme */
        .quick-actions-menu {
            position: fixed;
            bottom: var(--spacing-5);
            right: var(--spacing-5);
            z-index: 1020;
        }

        .quick-actions-btn {
            width: 56px;
            height: 56px;
            border-radius: var(--border-radius-full);
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            border: none;
            font-size: var(--font-size-xl);
            transition: var(--transition-fast);
        }

        .quick-actions-btn:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }

        .quick-actions-menu-items {
            position: absolute;
            bottom: 70px;
            right: 0;
            display: none;
            flex-direction: column;
            align-items: flex-end;
            gap: var(--spacing-2);
        }

        .quick-actions-menu-items.show {
            display: flex;
        }

        .quick-action-item {
            display: flex;
            align-items: center;
            background-color: var(--bg-card);
            padding: var(--spacing-2) var(--spacing-4);
            border-radius: var(--border-radius-full);
            text-decoration: none;
            color: var(--text-primary);
            box-shadow: var(--shadow-sm);
            font-weight: var(--font-weight-medium);
            border: 1px solid var(--border-color);
            transition: var(--transition-fast);
            font-size: var(--font-size-sm);
        }

        .quick-action-item:hover {
            background-color: var(--bg-hover);
            color: var(--primary);
            text-decoration: none;
            transform: translateX(-4px);
        }

        .quick-action-item i {
            margin-right: var(--spacing-2);
            width: 20px;
            text-align: center;
        }

        /* Mobile search overlay - follows Dasher UI theme */
        .mobile-search-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: var(--bg-overlay);
            z-index: 2000;
            display: none;
            align-items: flex-start;
            justify-content: center;
            padding-top: 100px;
        }

        .mobile-search-container.show {
            display: flex;
        }

        .mobile-search-input {
            width: 90%;
            max-width: 400px;
            padding: var(--spacing-4);
            border: 1px solid var(--border-input);
            border-radius: var(--border-radius);
            background-color: var(--bg-input);
            color: var(--text-primary);
            font-size: var(--font-size-lg);
            outline: none;
        }

        .mobile-search-input:focus {
            border-color: var(--border-input-focus);
            box-shadow: 0 0 0 3px rgba(0, 167, 111, 0.1);
        }

        .mobile-search-close {
            position: absolute;
            top: 120px;
            right: 5%;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-full);
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .mobile-search-close:hover {
            background-color: var(--bg-hover);
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .navbar {
                padding: 0 var(--spacing-4);
            }

            .logo-light,
            .logo-dark {
                width: 100px;
            }

            .mobile-bottom-nav {
                display: block;
            }

            .content {
                padding-bottom: 90px;
            }
        }

        @media (max-width: 576px) {
            .navbar-brand {
                font-size: var(--font-size-lg);
            }

            .user-avatar {
                width: 32px;
                height: 32px;
                font-size: var(--font-size-xs);
            }

            .logo-light,
            .logo-dark {
                width: 90px;
            }
        }

        /* Desktop-only elements */
        @media (min-width: 769px) {
            .mobile-only {
                display: none !important;
            }
        }

        
    
      @media (max-width: 390px) {
    .navbar {
        padding: 0 var(--spacing-2);
        height: 56px;
    }

    .mobile-menu-btn {
        padding: var(--spacing-1);
        margin-right: var(--spacing-1);
        font-size: var(--font-size-base);
    }

    .logo-light,
    .logo-dark {
        width: 70px;
    }

    .navbar-brand {
        font-size: var(--font-size-base);
        gap: var(--spacing-1);
    }

    .navbar-brand span {
        display: none !important; /* Hide "GateWey" text on very small screens */
    }

    .user-avatar {
        width: 28px;
        height: 28px;
        font-size: 10px;
    }

    .username-text {
        display: none !important; /* Hide username text on very small screens */
    }

    .nav-btn {
        width: 36px;
        height: 36px;
        font-size: var(--font-size-sm);
    }

    .theme-toggle {
        width: 36px;
        height: 36px;
        font-size: var(--font-size-base);
        margin-right: var(--spacing-1);
    }

    .notification-badge {
        width: 16px;
        height: 16px;
        font-size: 9px;
        top: -6px;
        right: -6px;
    }

    .d-flex.align-items-center {
        gap: 2px;
    }

    .dropdown-toggle {
        padding: 0 !important;
    }
}
/* Announcement dot indicator - small dot, not badge */
.notification-dot,
.announcement-dot {
    position: absolute;
    top: 8px;
    right: 8px;
    width: 8px;
    height: 8px;
    background-color: var(--danger);
    border-radius: 50%;
    border: 2px solid var(--bg-navbar);
    animation: pulse-dot 2s ease-in-out infinite;
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.2); }
}
    </style>
</head>

<body data-user-id="<?php echo $currentUser->getId(); ?>" data-user-role="<?php echo $currentUser->getRole(); ?>">

    <!-- Mobile Search Container -->
    <div class="mobile-search-container" id="mobileSearchContainer">
        <input type="text" class="mobile-search-input" placeholder="Search access codes, users..." autofocus>
        <button class="mobile-search-close" id="closeSearch">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Top Navbar - Always Dark Like Your Image -->
    <nav class="navbar">
        <div class="container-fluid">
            <!-- Left side with menu toggle and logo -->
            <div class="d-flex align-items-center">
                <!-- Mobile menu button -->
                <button class="mobile-menu-btn d-lg-none" type="button" id="sidebar-toggle" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>

                <!-- Logo with existing styling -->
                <a class="navbar-brand" href="<?php echo BASE_URL; ?>dashboard/">
                    <img src="<?php echo BASE_URL; ?>assets/images/icons/logo.png"
                        alt="GateWey Logo"
                        class="logo-light me-2">
                    <img src="<?php echo BASE_URL; ?>assets/images/icons/logo-dark.png"
                        alt="GateWey Logo"
                        class="logo-dark me-2">
                    <span class="d-none d-sm-inline">GateWey</span>
                </a>
            </div>

            <!-- Desktop navigation -->
            <div class="navbar-nav d-none d-lg-flex">
                <!-- Date display -->
                <div class="date-display">
                    <button class="btn border-0" type="button" title="Current date">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <span><?php echo $currentDate; ?></span>
                    </button>
                </div>

                <!-- Theme toggle -->
                <button class="theme-toggle" type="button" data-theme-toggle aria-label="Toggle theme">
                    <i class="fas fa-moon"></i>
                </button>

                <!-- Notifications -->
<a href="<?php echo BASE_URL; ?>notifications/" class="nav-btn position-relative" title="Notifications">
    <i class="fas fa-bell"></i>
    <?php if ($notificationCount > 0): ?>
        <span class="notification-dot"></span>
    <?php endif; ?>
</a>

                <!-- Messages -->
                <!--<a href="<?php echo BASE_URL; ?>messages/" class="nav-btn position-relative" title="Messages">-->
                <!--    <i class="fas fa-envelope"></i>-->
                <!--    <?php if ($unifiedMessageCount > 0): ?>-->
                <!--        <span class="notification-badge"><?php echo $unifiedMessageCount; ?></span>-->
                <!--    <?php endif; ?>-->
                <!--</a>-->
                <!-- Announcements -->
<a href="<?php echo BASE_URL; ?>announcements/" class="nav-btn position-relative" title="Announcements">
    <i class="fas fa-bullhorn"></i>
    <?php if ($unreadAnnouncementsCount > 0): ?>
        <span class="announcement-dot"></span>
    <?php endif; ?>
</a>
            </div>

            <!-- Right side with mobile actions and profile -->
            <div class="d-flex align-items-center">
                <!-- Mobile action buttons -->
                <div class="d-lg-none d-flex align-items-center">
                    <!-- Theme toggle (mobile) -->
                    <button class="theme-toggle me-2" type="button" data-theme-toggle aria-label="Toggle theme">
                        <i class="fas fa-moon"></i>
                    </button>

                    <!-- Notifications (mobile) -->
                    <a href="<?php echo BASE_URL; ?>notifications/" class="nav-btn position-relative me-1">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-dot"></span>
                        <?php endif; ?>
                    </a>

                    <!-- Announcements (mobile) -->
                    <a href="<?php echo BASE_URL; ?>announcements/" class="nav-btn position-relative me-2">
                        <i class="fas fa-bullhorn"></i>
                        <?php if ($unreadAnnouncementsCount > 0): ?>
                            <span class="announcement-dot"></span>
                        <?php endif; ?>
                    </a>

                    <!-- iOS 3-Dot Menu Button (Mobile Only) -->
                    <button class="ios-header-menu-btn" id="iosHeaderMenuBtn" aria-label="Open menu">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>

                <!-- Desktop 3-Dot Menu Button -->
                <button class="ios-header-menu-btn d-none d-lg-flex me-3" id="iosHeaderMenuBtnDesktop" aria-label="Open menu">
                    <i class="fas fa-ellipsis-v"></i>
                </button>

                <!-- Desktop User profile dropdown (Custom - no Bootstrap) -->
                <div class="desktop-user-dropdown d-none d-lg-block" id="desktopUserDropdown">
                    <button class="desktop-dropdown-btn" type="button" id="desktopDropdownBtn" aria-expanded="false">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($currentUser->getFullName() ?: $currentUser->getUsername(), 0, 1)); ?>
                        </div>
                        <span class="d-none d-md-inline-block ms-2 username-text"><?php echo htmlspecialchars($currentUser->getUsername()); ?></span>
                        <i class="fas fa-chevron-down desktop-dropdown-arrow"></i>
                    </button>
                    <div class="desktop-dropdown-menu" id="desktopDropdownMenu">
                        <div class="desktop-dropdown-header">
                            <strong><?php echo htmlspecialchars($currentUser->getFullName() ?: $currentUser->getUsername()); ?></strong>
                            <span class="desktop-dropdown-role"><?php echo htmlspecialchars($currentUser->getRole()); ?></span>
                        </div>
                        <div class="desktop-dropdown-divider"></div>
                        <a class="desktop-dropdown-item" href="<?php echo BASE_URL; ?>messages/">
                            <i class="fas fa-envelope"></i>Messages
                        </a>
                        <a class="desktop-dropdown-item" href="<?php echo BASE_URL; ?>profile/">
                            <i class="fas fa-user"></i>My Profile
                        </a>
                        <?php if ($currentUser->getRole() === 'clan_admin'): ?>
                            <div class="desktop-dropdown-divider"></div>
                            <a class="desktop-dropdown-item" href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($currentUser->getClanId()); ?>">
                                <i class="fas fa-cog"></i>Clan Settings
                            </a>
                        <?php endif; ?>
                        <div class="desktop-dropdown-divider"></div>
                        <a class="desktop-dropdown-item danger" href="<?php echo BASE_URL; ?>logout.php">
                            <i class="fas fa-sign-out-alt"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- iOS-Style Mobile Bottom Navigation -->
    <?php
    // Determine which nav item should be active (only one at a time)
    $requestUri = $_SERVER['REQUEST_URI'];
    $mobileNavActive = '';

    // Check in order of specificity (most specific first)
    if (strpos($requestUri, '/reports/') !== false || strpos($requestUri, '/visitors') !== false) {
        $mobileNavActive = 'reports';
    } elseif (strpos($requestUri, '/user/events/') !== false || strpos($requestUri, '/announcements/') !== false || strpos($requestUri, '/events/') !== false) {
        $mobileNavActive = 'events';
    } elseif (strpos($requestUri, '/chat/') !== false) {
        $mobileNavActive = 'chat';
    } elseif (strpos($requestUri, '/dashboard/') !== false || $requestUri === '/' || preg_match('/\/index\.php$/', $requestUri)) {
        $mobileNavActive = 'home';
    }
    ?>
    <div class="mobile-bottom-nav d-lg-none">
        <div class="mobile-nav-items">
            <!-- Home -->
            <a href="<?php echo BASE_URL; ?>dashboard/" class="mobile-nav-item <?php echo $mobileNavActive === 'home' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>

            <!-- Chat -->
            <a href="<?php echo BASE_URL; ?>chat/" class="mobile-nav-item <?php echo $mobileNavActive === 'chat' ? 'active' : ''; ?>">
                <i class="fas fa-comments"></i>
                <span>Chat</span>
            </a>

            <!-- Center Button - Role-based -->
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'guard'): ?>
                <a href="<?php echo BASE_URL; ?>access-codes/verify.php" class="mobile-nav-center-btn">
                    <div class="mobile-nav-plus-btn">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <span>Verify</span>
                </a>
            <?php else: ?>
                <a href="<?php echo BASE_URL; ?>access-codes/generate.php" class="mobile-nav-center-btn">
                    <div class="mobile-nav-plus-btn">
                        <i class="fas fa-plus"></i>
                    </div>
                    <span>Code</span>
                </a>
            <?php endif; ?>

            <!-- Events -->
            <?php
                if ($currentUser->getRole() === 'clan_admin') {
                    $eventsUrl = BASE_URL . 'admin/events/index.php';
                } elseif ($currentUser->getRole() === 'guard') {
                    $eventsUrl = BASE_URL . 'user/events/browse-events.php';
                } else {
                    $eventsUrl = BASE_URL . 'user/events/index.php';
                }
            ?>
            <a href="<?php echo $eventsUrl; ?>" class="mobile-nav-item <?php echo $mobileNavActive === 'events' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>Events</span>
            </a>

            <!-- Reports -->
            <a href="<?php echo BASE_URL; ?>reports/visitors.php" class="mobile-nav-item <?php echo $mobileNavActive === 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </div>
    </div>

    <!-- iOS Header Menu Modal -->
    <div class="ios-header-menu-backdrop" id="iosHeaderMenuBackdrop"></div>
    <div class="ios-header-menu-modal" id="iosHeaderMenuModal">
        <div class="ios-menu-handle"></div>

        <!-- User Info Header -->
        <div class="ios-menu-user-header">
            <div class="ios-menu-user-avatar">
                <?php echo strtoupper(substr($currentUser->getFullName() ?: $currentUser->getUsername(), 0, 1)); ?>
            </div>
            <div class="ios-menu-user-info">
                <p class="ios-menu-user-name"><?php echo htmlspecialchars($currentUser->getFullName() ?: $currentUser->getUsername()); ?></p>
                <p class="ios-menu-user-role"><?php echo htmlspecialchars(str_replace('_', ' ', $currentUser->getRole())); ?></p>
            </div>
        </div>

        <div class="ios-menu-content">
            <!-- Quick Actions -->
            <div class="ios-menu-section">
                <div class="ios-menu-section-title">Quick Actions</div>
                <div class="ios-menu-card">
                    <?php if ($currentUser->getRole() !== 'guard'): ?>
                    <a href="<?php echo BASE_URL; ?>access-codes/generate.php" class="ios-menu-item">
                        <div class="ios-menu-item-icon green"><i class="fas fa-plus"></i></div>
                        <span class="ios-menu-item-label">Generate Code</span>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>access-codes/" class="ios-menu-item">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-qrcode"></i></div>
                        <span class="ios-menu-item-label">My Codes</span>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </a>
                    <a href="<?php echo BASE_URL; ?>messages/" class="ios-menu-item">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-envelope"></i></div>
                        <span class="ios-menu-item-label">Messages</span>
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
                    <?php if ($currentUser->getRole() === 'clan_admin'): ?>
                    <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($currentUser->getClanId()); ?>" class="ios-menu-item">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-cog"></i></div>
                        <span class="ios-menu-item-label">Clan Settings</span>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>notifications/" class="ios-menu-item">
                        <div class="ios-menu-item-icon gray"><i class="fas fa-bell"></i></div>
                        <span class="ios-menu-item-label">Notifications</span>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </a>
                </div>
            </div>

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

    <!-- Mobile Quick Actions Menu -->
    <!--<div class="quick-actions-menu d-lg-none">-->
    <!--    <div class="quick-actions-menu-items" id="quickActionsItems">-->
    <!--        <?php if ($currentUser->getRole() !== 'guard'): ?>-->
    <!--            <a href="<?php echo BASE_URL; ?>access-codes/generate.php" class="quick-action-item">-->
    <!--                <i class="fas fa-plus"></i> Generate Code-->
    <!--            </a>-->
    <!--        <?php endif; ?>-->
    <!--        <?php if ($currentUser->getRole() === 'guard'): ?>-->
    <!--            <a href="<?php echo BASE_URL; ?>access-codes/verify.php" class="quick-action-item">-->
    <!--                <i class="fas fa-qrcode"></i> Verify Code-->
    <!--            </a>-->
    <!--        <?php endif; ?>-->
    <!--        <a href="<?php echo BASE_URL; ?>profile/" class="quick-action-item">-->
    <!--            <i class="fas fa-user"></i> Profile-->
    <!--        </a>-->
    <!--        <a href="<?php echo BASE_URL; ?>help/" class="quick-action-item">-->
    <!--            <i class="fas fa-question-circle"></i> Help-->
    <!--        </a>-->
    <!--    </div>-->
    <!--    <button class="quick-actions-btn" id="quickActionsBtn">-->
    <!--        <i class="fas fa-plus"></i>-->
    <!--    </button>-->
    <!--</div>-->

    <!-- Load Dasher UI Theme System -->
    <script src="<?php echo BASE_URL; ?>assets/js/dasher-theme-system.js"></script>

    <!-- Bootstrap JS - Load before our custom scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js for data visualization (load only if needed) -->
    <?php if (isset($includeCharts) && $includeCharts): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="<?php echo BASE_URL; ?>assets/js/dasher-chart-config.js"></script>
    <?php endif; ?>

    <!-- Page functionality with custom header -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for Bootstrap to be ready
            setTimeout(() => {
                initializeDropdowns();
            }, 100);

            // Initialize other components
            initializeQuickActions();
            initializeMobileSearch();
            initializeSidebarToggle();
            initializeMobileNavigation();

            // Listen for Dasher theme changes
            document.addEventListener('themeChanged', function(event) {
                // Update meta theme color
                const metaThemeColor = document.querySelector('meta[name="theme-color"]');
                if (metaThemeColor) {
                    metaThemeColor.content = event.detail.theme === 'dark' ? '#1c252e' : '#00a76f';
                }

                // Update any custom components
                updateCustomComponents(event.detail.theme);
            });
        });

        function initializeQuickActions() {
            const quickActionsBtn = document.getElementById('quickActionsBtn');
            const quickActionsItems = document.getElementById('quickActionsItems');

            if (quickActionsBtn && quickActionsItems) {
                quickActionsBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    quickActionsItems.classList.toggle('show');

                    const icon = quickActionsBtn.querySelector('i');
                    icon.style.transform = quickActionsItems.classList.contains('show') ? 'rotate(45deg)' : 'rotate(0deg)';
                });

                document.addEventListener('click', function(event) {
                    if (!event.target.closest('.quick-actions-menu')) {
                        quickActionsItems.classList.remove('show');
                        const icon = quickActionsBtn.querySelector('i');
                        icon.style.transform = 'rotate(0deg)';
                    }
                });
            }
        }

        function initializeMobileSearch() {
            const mobileSearchContainer = document.getElementById('mobileSearchContainer');
            const closeSearch = document.getElementById('closeSearch');

            if (mobileSearchContainer && closeSearch) {
                closeSearch.addEventListener('click', function() {
                    mobileSearchContainer.classList.remove('show');
                });

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape' && mobileSearchContainer.classList.contains('show')) {
                        mobileSearchContainer.classList.remove('show');
                    }
                });

                mobileSearchContainer.addEventListener('click', function(e) {
                    if (e.target === mobileSearchContainer) {
                        mobileSearchContainer.classList.remove('show');
                    }
                });
            }
        }

        function initializeSidebarToggle() {
            const sidebarToggle = document.getElementById('sidebar-toggle');
            const sidebar = document.querySelector('.sidebar');
            const content = document.querySelector('.content');

            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    sidebar.classList.toggle('active');
                    if (content) content.classList.toggle('sidebar-active');

                    sidebarToggle.setAttribute('aria-expanded', sidebar.classList.contains('active'));
                });

                document.addEventListener('click', function(event) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggle = sidebarToggle.contains(event.target);

                    if (!isClickInsideSidebar && !isClickOnToggle &&
                        window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                        sidebar.classList.remove('active');
                        if (content) content.classList.remove('sidebar-active');
                        sidebarToggle.setAttribute('aria-expanded', 'false');
                    }
                });

                window.addEventListener('resize', function() {
                    if (window.innerWidth > 768) {
                        sidebar.classList.remove('active');
                        if (content) content.classList.remove('sidebar-active');
                        sidebarToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        }

        function initializeMobileNavigation() {
            // Active state is now handled by PHP to ensure only one item is active
            // This function is kept for potential future enhancements
        }

        function initializeDropdowns() {
            // Custom desktop dropdown (no Bootstrap)
            const dropdown = document.getElementById('desktopUserDropdown');
            const btn = document.getElementById('desktopDropdownBtn');
            const menu = document.getElementById('desktopDropdownMenu');

            if (!dropdown || !btn || !menu) return;

            // Toggle dropdown on button click
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                dropdown.classList.toggle('open');
                btn.setAttribute('aria-expanded', dropdown.classList.contains('open'));
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });

            // Close dropdown on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && dropdown.classList.contains('open')) {
                    dropdown.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });

            // Close dropdown when resizing to mobile
            window.addEventListener('resize', function() {
                if (window.innerWidth < 992) {
                    dropdown.classList.remove('open');
                    btn.setAttribute('aria-expanded', 'false');
                }
            });
        }

        function updateCustomComponents(theme) {
            // Update any custom components that need theme-specific handling
            const isDark = theme === 'dark';

            // Example: Update chart colors if charts are present
            if (window.DasherCharts && typeof window.DasherCharts.updateAllCharts === 'function') {
                window.DasherCharts.updateAllCharts();
            }
        }

        // iOS Header Menu - Open/Close/Swipe functionality
        (function() {
            const menuBtn = document.getElementById('iosHeaderMenuBtn');
            const menuBtnDesktop = document.getElementById('iosHeaderMenuBtnDesktop');
            const backdrop = document.getElementById('iosHeaderMenuBackdrop');
            const modal = document.getElementById('iosHeaderMenuModal');

            if (!backdrop || !modal) return;

            let startY = 0;
            let currentY = 0;
            let isDragging = false;

            // Open menu (mobile button)
            if (menuBtn) {
                menuBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openMenu();
                });
            }

            // Open menu (desktop button)
            if (menuBtnDesktop) {
                menuBtnDesktop.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    openMenu();
                });
            }

            // Close on backdrop click
            backdrop.addEventListener('click', function() {
                closeMenu();
            });

            // Swipe to close functionality
            modal.addEventListener('touchstart', function(e) {
                startY = e.touches[0].clientY;
                isDragging = true;
                modal.style.transition = 'none';
            }, { passive: true });

            modal.addEventListener('touchmove', function(e) {
                if (!isDragging) return;

                currentY = e.touches[0].clientY;
                const diff = currentY - startY;

                // Only allow downward swipe
                if (diff > 0) {
                    modal.style.transform = `translateY(${diff}px)`;

                    // Fade backdrop based on drag distance
                    const opacity = Math.max(0, 1 - (diff / 300));
                    backdrop.style.opacity = opacity;
                }
            }, { passive: true });

            modal.addEventListener('touchend', function(e) {
                if (!isDragging) return;
                isDragging = false;

                modal.style.transition = 'transform 0.3s cubic-bezier(0.32, 0.72, 0, 1)';
                backdrop.style.transition = 'opacity 0.3s ease';

                const diff = currentY - startY;

                // If dragged more than 100px down, close the menu
                if (diff > 100) {
                    closeMenu();
                } else {
                    // Snap back to open position
                    modal.style.transform = 'translateY(0)';
                    backdrop.style.opacity = '1';
                }

                startY = 0;
                currentY = 0;
            }, { passive: true });

            function openMenu() {
                backdrop.classList.add('active');
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }

            function closeMenu() {
                backdrop.classList.remove('active');
                modal.classList.remove('active');
                modal.style.transform = '';
                backdrop.style.opacity = '';
                document.body.style.overflow = '';
            }

            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    closeMenu();
                }
            });

            // Close menu when resizing to desktop view (prevents frozen state)
            window.addEventListener('resize', function() {
                // 992px is Bootstrap's lg breakpoint
                if (window.innerWidth >= 992 && modal.classList.contains('active')) {
                    closeMenu();
                }
            });

            // Expose functions globally for potential external use
            window.iosHeaderMenu = {
                open: openMenu,
                close: closeMenu
            };
        })();
    </script>