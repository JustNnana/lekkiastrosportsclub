<?php

/**
 * Gate Wey Access Management System
 * Updated Sidebar Navigation with Dasher UI Design System
 * File: includes/sidebar.php
 */

$userRole = $_SESSION['role'] ?? 'user';
$currentUrl = $_SERVER['REQUEST_URI'];

// Function to check if a URL is active
function isActive($url)
{
    global $currentUrl;
    return strpos($currentUrl, $url) !== false;
}
if (!class_exists('Clan')) {
    $possible_paths = [
        __DIR__ . '/../classes/Clan.php',    // From includes/ directory
        __DIR__ . '/../../classes/Clan.php', // From deeper nested directories
        dirname(__DIR__) . '/classes/Clan.php' // Alternative approach
    ];

    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

// Auto-load MarketplaceUser class if not already loaded
if (!class_exists('MarketplaceUser')) {
    $mpPossiblePaths = [
        __DIR__ . '/../classes/MarketplaceUser.php',
        __DIR__ . '/../../classes/MarketplaceUser.php',
        dirname(__DIR__) . '/classes/MarketplaceUser.php'
    ];
    foreach ($mpPossiblePaths as $mpPath) {
        if (file_exists($mpPath)) {
            require_once $mpPath;
            break;
        }
    }
}

// Get marketplace user status if available
$isMarketplaceUser = false;
$isMarketplaceSeller = false;
$unreadMessageCount = 0;

try {
    if (class_exists('MarketplaceUser')) {
        $mpUser = new MarketplaceUser();
        if ($mpUser->loadByUserId($_SESSION['user_id'])) {
            $isMarketplaceUser = true;
            $isMarketplaceSeller = $mpUser->isSeller();
            $unreadMessageCount = $mpUser->getUnreadMessageCount();
        }
    }
} catch (Exception $e) {
    // Silently handle if marketplace is not available
}

// Get support ticket counts for users
$openTicketCount = 0;
try {
    $db = Database::getInstance();
    $ticketResult = $db->fetchOne(
        "SELECT COUNT(*) as count FROM support_tickets WHERE user_id = ? AND status NOT IN ('closed', 'resolved')",
        [$_SESSION['user_id']]
    );
    $openTicketCount = $ticketResult ? $ticketResult['count'] : 0;
} catch (Exception $e) {
    // Silently handle error
}

// Get admin ticket counts for admins
$adminTicketCount = 0;
$urgentTicketCount = 0;

// Check if user is a household head (for regular users only)
$isHouseholdHead = false;
if ($userRole === 'user') {
    try {
        $householdHeadCheck = $db->fetchOne(
            "SELECT is_household_head FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
        $isHouseholdHead = $householdHeadCheck && $householdHeadCheck['is_household_head'] == 1;
    } catch (Exception $e) {
        error_log("Error checking household head status: " . $e->getMessage());
    }
}
if ($userRole === 'super_admin' || $userRole === 'clan_admin') {
    try {
        $clanCondition = $userRole === 'clan_admin' ? " AND clan_id = " . intval($_SESSION['clan_id']) : "";

        $adminTicketResult = $db->fetchOne(
            "SELECT COUNT(*) as count FROM support_tickets WHERE status NOT IN ('closed', 'resolved')" . $clanCondition
        );

        $urgentTicketResult = $db->fetchOne(
            "SELECT COUNT(*) as count FROM support_tickets WHERE priority = 'urgent' AND status NOT IN ('closed', 'resolved')" . $clanCondition
        );

        $adminTicketCount = $adminTicketResult ? $adminTicketResult['count'] : 0;
        $urgentTicketCount = $urgentTicketResult ? $urgentTicketResult['count'] : 0;
    } catch (Exception $e) {
        // Silently handle error
        error_log("Error getting admin ticket counts: " . $e->getMessage());
    }
}

// Get clan dues related counts for admins
$clanDuesStats = [];
if (($userRole === 'super_admin' || $userRole === 'clan_admin') && class_exists('ClanDuesPayment')) {
    try {
        $clanId = $userRole === 'clan_admin' ? $_SESSION['clan_id'] : null;
        $clanDuesPayment = new ClanDuesPayment();

        if ($clanId) {
            // Get overdue payments count
            $overdueCount = $db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM clan_dues_payments cdp 
                 JOIN clan_dues cd ON cdp.clan_dues_id = cd.id 
                 WHERE cd.clan_id = ? AND cdp.status IN ('pending', 'overdue') 
                 AND cdp.due_date < CURRENT_DATE()",
                [$clanId]
            )['count'] ?? 0;

            // Get pending payments count
            $pendingCount = $db->fetchOne(
                "SELECT COUNT(*) as count 
                 FROM clan_dues_payments cdp 
                 JOIN clan_dues cd ON cdp.clan_dues_id = cd.id 
                 WHERE cd.clan_id = ? AND cdp.status = 'pending'",
                [$clanId]
            )['count'] ?? 0;

            // Check if payment settings are configured
            $paymentConfigured = $db->fetchOne(
                "SELECT COUNT(*) as count FROM clan_payment_settings WHERE clan_id = ?",
                [$clanId]
            )['count'] > 0;

            $clanDuesStats = [
                'overdue_count' => $overdueCount,
                'pending_count' => $pendingCount,
                'payment_configured' => $paymentConfigured
            ];
        }
    } catch (Exception $e) {
        error_log("Error getting clan dues stats: " . $e->getMessage());
    }
}

// Get user dues payment information for regular users only
$userDuesInfo = [];
if ($userRole === 'user' && class_exists('ClanDuesPayment')) {
    try {
        // Check if user has any dues payments (pending, overdue, or paid)
        $userDuesQuery = $db->fetchOne(
            "SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN cdp.status IN ('pending', 'overdue') THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN cdp.status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN cdp.status = 'paid' THEN 1 ELSE 0 END) as paid_count
             FROM clan_dues_payments cdp 
             JOIN clan_dues cd ON cdp.clan_dues_id = cd.id 
             WHERE cdp.user_id = ?",
            [$_SESSION['user_id']]
        );

        if ($userDuesQuery) {
            $userDuesInfo = [
                'total_payments' => (int)$userDuesQuery['total_payments'],
                'pending_count' => (int)$userDuesQuery['pending_count'],
                'overdue_count' => (int)$userDuesQuery['overdue_count'],
                'paid_count' => (int)$userDuesQuery['paid_count'],
                'has_dues' => (int)$userDuesQuery['total_payments'] > 0
            ];
        }
    } catch (Exception $e) {
        error_log("Error getting user dues info: " . $e->getMessage());
        // Initialize empty array on error
        $userDuesInfo = [
            'total_payments' => 0,
            'pending_count' => 0,
            'overdue_count' => 0,
            'paid_count' => 0,
            'has_dues' => false
        ];
    }
}
?>

<!-- Dasher UI Sidebar Styles -->
<style>
    /* ===== DASHER UI SIDEBAR STYLES ===== */

    .sidebar {
    width: var(--sidebar-width);
    background-color: var(--bg-sidebar);
    border-right: 1px solid var(--border-color);
    position: fixed;
    top: var(--navbar-height);
    left: 0;
    bottom: 0;
    overflow-y: auto;
    overflow-x: hidden;  /* ADD THIS LINE */
    transition: var(--theme-transition);
    z-index: 1020;
    box-shadow: var(--shadow-xs);
}

    .sidebar-content {
    padding: var(--spacing-6) 0;
    display: flex;
    flex-direction: column;
    gap: var(--spacing-1);
    max-width: 100%;       /* ADD THIS LINE */
    overflow-x: hidden;    /* ADD THIS LINE */
}

    /* Sidebar Categories */
    .sidebar-category {
        padding: var(--spacing-4) var(--spacing-6) var(--spacing-2);
        margin-top: var(--spacing-4);
    }

    .sidebar-category:first-child {
        margin-top: 0;
    }

    .sidebar-category-text {
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    line-height: 1.2;
    overflow: hidden;           /* ADD THIS LINE */
    text-overflow: ellipsis;    /* ADD THIS LINE */
    white-space: nowrap;        /* ADD THIS LINE */
    display: block;             /* ADD THIS LINE */
}
    /* Sidebar Links */
    .sidebar-link {
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
    padding: var(--spacing-3) var(--spacing-6);
    color: var(--text-primary);
    text-decoration: none;
    transition: var(--transition-fast);
    border-radius: 0;
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    position: relative;
    min-height: 44px;
    max-width: 100%;            /* ADD THIS LINE */
    box-sizing: border-box;     /* ADD THIS LINE */
}

    .sidebar-link:hover {
        background-color: var(--bg-hover);
        color: var(--text-primary);
        text-decoration: none;
    }

    .sidebar-link.active {
        background-color: var(--primary-light);
        color: var(--primary);
        border-right: 3px solid var(--primary);
        font-weight: var(--font-weight-semibold);
    }

    .sidebar-link.active:hover {
        background-color: var(--primary-light);
        color: var(--primary);
    }

    /* Sidebar Icons */
    .sidebar-icon {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: var(--font-size-lg);
    }

    .sidebar-text {
    flex: 1;
    line-height: 1.4;
    overflow: hidden;           /* ADD THIS LINE */
    text-overflow: ellipsis;    /* ADD THIS LINE */
    white-space: nowrap;        /* ADD THIS LINE */
    min-width: 0;              /* ADD THIS LINE - Important for flex items */
}

    /* Sidebar Badges */
    .sidebar-badge {
        background-color: var(--gray-500);
        color: white;
        border-radius: var(--border-radius-full);
        font-size: var(--font-size-xs);
        font-weight: var(--font-weight-bold);
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 var(--spacing-2);
        line-height: 1;
        flex-shrink: 0;
    }

    .sidebar-badge-primary {
        background-color: var(--primary);
    }

    .sidebar-badge-success {
        background-color: var(--success);
    }

    .sidebar-badge-warning {
        background-color: var(--warning);
        color: var(--text-primary);
    }

    .sidebar-badge-danger {
        background-color: var(--danger);
    }

    .sidebar-badge-info {
        background-color: var(--info);
    }

    /* Special Badge Animations */
    .sidebar-badge-pulse {
        animation: dasher-pulse 2s infinite;
    }

    @keyframes dasher-pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .sidebar-badge-icon {
        font-size: var(--font-size-xs);
        margin-left: var(--spacing-1);
    }

    /* Enhanced Active State for Dues Links */
    .sidebar-link-dues {
        position: relative;
    }

    .sidebar-link-dues::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(to bottom, var(--success), var(--success-300));
        opacity: 0;
        transition: var(--transition-fast);
    }

    .sidebar-link-dues:hover::before,
    .sidebar-link-dues.active::before {
        opacity: 1;
    }

    .sidebar-link-dues:hover {
        background-color: var(--success-light);
        border-left: none;
    }

    .sidebar-link-dues.active {
        background-color: var(--success-light);
        color: var(--success-700);
        border-right: 3px solid var(--success);
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform var(--transition-normal);
            z-index: 1050;
            box-shadow: none;
        }

        .sidebar.active {
            transform: translateX(0);
            box-shadow: var(--shadow-lg);
        }

        .sidebar-content {
            padding: var(--spacing-4) 0;
        }

        .sidebar-link {
            padding: var(--spacing-3) var(--spacing-4);
            min-height: 48px;
        }

        .sidebar-category {
            padding: var(--spacing-3) var(--spacing-4) var(--spacing-2);
        }

        .sidebar-badge {
            min-width: 18px;
            height: 18px;
            font-size: 0.6rem;
        }

        /* Mobile floating state */
        .sidebar.mobile-floating {
            transform: translateX(0) !important;
            box-shadow: var(--shadow-xl) !important;
            z-index: 1051 !important;
        }

        /* Overlay when floating */
        .sidebar.mobile-floating::before {
            content: '';
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            width: calc(100vw - var(--sidebar-width));
            height: 100vh;
            background: var(--bg-overlay);
            z-index: -1;
            pointer-events: auto;
        }
    }

    @media (max-width: 576px) {
        .sidebar-link {
            padding: var(--spacing-2) var(--spacing-3);
            font-size: var(--font-size-xs);
        }

        .sidebar-icon {
            width: 18px;
            height: 18px;
            font-size: var(--font-size-base);
        }

        .sidebar-category-text {
            font-size: 0.65rem;
        }
    }

    /* Scrollbar Styling */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: var(--bg-secondary);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: var(--border-radius-xl);
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: var(--text-muted);
    }

    /* Dark Mode Adjustments */
    [data-theme="dark"] .sidebar {
        box-shadow: var(--shadow-sm);
    }

    [data-theme="dark"] .sidebar-link-dues:hover {
        background-color: rgba(34, 197, 94, 0.15);
    }

    [data-theme="dark"] .sidebar-link-dues.active {
        background-color: rgba(34, 197, 94, 0.2);
        color: var(--success-300);
    }

    /* Focus States for Accessibility */
    .sidebar-link:focus {
        outline: 2px solid var(--primary);
        outline-offset: -2px;
    }

    .sidebar-link:focus-visible {
        outline: 2px solid var(--primary);
        outline-offset: -2px;
    }

    /* High Contrast Mode Support */
    @media (prefers-contrast: high) {
        .sidebar-link {
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-badge {
            border: 1px solid;
        }
    }

    /* Reduced Motion Support */
    @media (prefers-reduced-motion: reduce) {

        .sidebar,
        .sidebar-link,
        .sidebar-badge-pulse {
            animation: none;
            transition: none;
        }
    }

    /* Print Styles */
    @media print {
        .sidebar {
            display: none;
        }
    }

    /* Enhanced Visual Feedback */
    .sidebar-link:active {
        transform: translateX(2px);
    }

    /* Hover effects for badges */
    .sidebar-link:hover .sidebar-badge {
        transform: scale(1.05);
        transition: transform var(--transition-fast);
    }

    /* Special styling for urgent items */
    .sidebar-link:has(.sidebar-badge-danger) {
        position: relative;
    }

    .sidebar-link:has(.sidebar-badge-danger)::after {
        content: '';
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 2px;
        background: var(--danger);
        opacity: 0.5;
    }

    /* Content area adjustment for sidebar */
    .content {
        margin-left: var(--sidebar-width);
        transition: margin-left var(--transition-normal);
    }

    @media (max-width: 768px) {
        .content {
            margin-left: 0;
        }

        .content.sidebar-active {
            margin-left: 0;
        }
    }
</style>
<!-- Dasher UI Sidebar -->
<div class="sidebar">
    <div class="sidebar-content">
        <!-- Dashboard Menu Item (common for all roles) -->
        <a href="<?php echo BASE_URL; ?>dashboard/" class="sidebar-link <?php echo isActive('/dashboard/') ? 'active' : ''; ?>">
            <div class="sidebar-icon">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <span class="sidebar-text">Dashboard</span>
        </a>

        <?php if ($userRole === 'super_admin'): ?>
            <!-- Super Admin Menu Items -->
            <div class="sidebar-category">
                <span class="sidebar-category-text">ADMINISTRATION</span>
            </div>

            <a href="<?php echo BASE_URL; ?>clans/" class="sidebar-link <?php echo isActive('/clans/') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-sitemap"></i>
                </div>
                <span class="sidebar-text">Estate Management</span>
            </a>

            <a href="<?php echo BASE_URL; ?>users/admins.php" class="sidebar-link <?php echo isActive('admins.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <span class="sidebar-text">Estate Admins</span>
            </a>

            <a href="<?php echo BASE_URL; ?>payments/plans.php" class="sidebar-link <?php echo isActive('plans.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-tags"></i>
                </div>
                <span class="sidebar-text">Pricing Plans</span>
            </a>

            <!-- Payment Tracking (index page) -->
<a href="<?php echo BASE_URL; ?>payments/" class="sidebar-link <?php echo isActive('/payments/') && !isActive('/payments/analytics') && !isActive('plans') && !isActive('user-license-plans') ? 'active' : ''; ?>">
    <div class="sidebar-icon">
        <i class="fas fa-credit-card"></i>
    </div>
    <span class="sidebar-text">Payment Tracking</span>
</a>

<!-- Analytics -->
<a href="<?php echo BASE_URL; ?>payments/analytics" class="sidebar-link <?php echo isActive('/payments/analytics') ? 'active' : ''; ?>">
    <div class="sidebar-icon">
        <i class="fas fa-chart-bar"></i>
    </div>
    <span class="sidebar-text">Analytics</span>
</a>

            <!-- Support Ticket Management for Super Admin -->
            <a href="<?php echo BASE_URL; ?>admin/tickets/" class="sidebar-link <?php echo isActive('/admin/tickets/') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-headset"></i>
                </div>
                <span class="sidebar-text">Ticket Management</span>
                <?php if ($adminTicketCount > 0): ?>
                    <div class="sidebar-badge sidebar-badge-<?php echo $urgentTicketCount > 0 ? 'danger' : 'warning'; ?>">
                        <?php echo $adminTicketCount; ?>
                    </div>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'super_admin' || $userRole === 'clan_admin'): ?>
            <!-- Clan Admin Menu Items -->
            <?php if ($userRole === 'clan_admin'): ?>
                <div class="sidebar-category">
                    <span class="sidebar-category-text">Estate MANAGEMENT</span>
                </div>

                <?php
                // Check if the clan is on a per-user plan
                $clan = new Clan();
                $isPlanPerUser = false;
                $availableLicenses = 0;

                if ($clan->loadById($_SESSION['clan_id'])) {
                    $pricingPlan = $db->fetchOne(
                        "SELECT * FROM pricing_plans WHERE id = ?",
                        [$clan->getPricingPlanId()]
                    );

                    if ($pricingPlan && isset($pricingPlan['is_per_user']) && $pricingPlan['is_per_user']) {
                        $isPlanPerUser = true;
                        $availableLicenses = $clan->getAvailableLicenses();
                    }
                }

                // If the clan is on a per-user plan, add the license management menu items
                if ($isPlanPerUser):
                ?>
                    <a href="<?php echo BASE_URL; ?>payments/settings.php?type=license" class="sidebar-link <?php echo isActive('/payments/settings.php?type=license') ? 'active' : ''; ?>">
                        <div class="sidebar-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <span class="sidebar-text">Buy User Licenses</span>
                        <?php if ($availableLicenses <= 1): ?>
                            <div class="sidebar-badge sidebar-badge-<?php echo $availableLicenses === 0 ? 'danger' : 'warning'; ?>">
                                <?php echo $availableLicenses; ?>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>

                <?php if ($userRole === 'clan_admin'): ?>
                    <a href="<?php echo BASE_URL; ?>payments/settings.php?type=clan" class="sidebar-link <?php echo isActive('/payments/settings.php?type=clan') && !isActive('/admin/clan-dues/payment-settings.php') ? 'active' : ''; ?>">
                        <div class="sidebar-icon">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                        <span class="sidebar-text">Estate Payment</span>
                    </a>
                <?php endif; ?>

                <!-- Support Ticket Management for Clan Admin -->
                <a href="<?php echo BASE_URL; ?>admin/tickets/" class="sidebar-link <?php echo isActive('/admin/tickets/') ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-headset"></i>
                    </div>
                    <span class="sidebar-text">Support Management</span>
                    <?php if ($adminTicketCount > 0): ?>
                        <div class="sidebar-badge sidebar-badge-<?php echo $urgentTicketCount > 0 ? 'danger' : 'warning'; ?>">
                            <?php echo $adminTicketCount; ?>
                        </div>
                    <?php endif; ?>
                </a>
 <!-- Household Management (for clan admins only) -->
<?php if ($userRole === 'clan_admin'): ?>
    <a href="<?php echo BASE_URL; ?>households/" class="sidebar-link <?php echo isActive('/households/') ? 'active' : ''; ?>">
        <div class="sidebar-icon">
            <i class="fas fa-home"></i>
        </div>
        <span class="sidebar-text">Household Management</span>
    </a>
<?php endif; ?>
                <a href="<?php echo BASE_URL; ?>users/" class="sidebar-link <?php echo isActive('/users/') && !isActive('admins.php') ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="sidebar-text">User Management</span>
                    <?php if ($userRole === 'clan_admin' && $isPlanPerUser): ?>
                        <div class="sidebar-badge sidebar-badge-info">
                            <?php
                            // Get current user count for this clan
                            $userCount = $db->fetchOne(
                                "SELECT COUNT(*) as count FROM users WHERE clan_id = ? AND role != 'clan_admin'",
                                [$_SESSION['clan_id']]
                            )['count'] ?? 0;
                            echo $userCount;
                            ?>
                        </div>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <?php if ($userRole === 'clan_admin'): ?>
                <div class="sidebar-category">
                    <span class="sidebar-category-text">Service Charge</span>
                </div>

                <a href="<?php echo BASE_URL; ?>admin/clan-dues/" class="sidebar-link <?php echo isActive('/admin/clan-dues/') && !isActive('payments.php') && !isActive('payment-settings.php') && !isActive('create-dues.php') ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <span class="sidebar-text">Payment Dashboard</span>
                </a>

                <a href="<?php echo BASE_URL; ?>admin/clan-dues/create-dues.php" class="sidebar-link <?php echo isActive('create-dues.php') ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <span class="sidebar-text">New Payment</span>
                </a>

                <a href="<?php echo BASE_URL; ?>admin/clan-dues/payments.php" class="sidebar-link <?php echo isActive('/admin/clan-dues/payments.php') ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <span class="sidebar-text">Payment Management</span>
                    <?php if (!empty($clanDuesStats) && ($clanDuesStats['overdue_count'] > 0 || $clanDuesStats['pending_count'] > 0)): ?>
                        <div class="sidebar-badge sidebar-badge-<?php echo $clanDuesStats['overdue_count'] > 0 ? 'danger' : 'warning'; ?>">
                            <?php echo $clanDuesStats['overdue_count'] + $clanDuesStats['pending_count']; ?>
                        </div>
                    <?php endif; ?>
                </a>

                <a href="<?php echo BASE_URL; ?>admin/clan-dues/payment-settings.php" class="sidebar-link <?php echo isActive('payment-settings.php') ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <span class="sidebar-text">Payment Settings</span>
                    <?php if (!empty($clanDuesStats) && !$clanDuesStats['payment_configured']): ?>
                        <div class="sidebar-badge sidebar-badge-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <!-- ===== CLAN DUES SECTION - ADMIN ONLY ===== -->


            <!-- END CLAN DUES SECTION -->
        <?php endif; ?>

        <!-- Events Management Section -->
        <?php if ($userRole === 'super_admin' || $userRole === 'clan_admin'): ?>
            <div class="sidebar-category">
                <span class="sidebar-category-text">EVENTS</span>
            </div>

            <a href="<?php echo BASE_URL; ?>admin/events/" class="sidebar-link <?php echo isActive('/admin/events/') && !isActive('create-event.php') && !isActive('view-event.php') && !isActive('edit-event.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <span class="sidebar-text">Events Dashboard</span>
            </a>

            <a href="<?php echo BASE_URL; ?>admin/events/create-event.php" class="sidebar-link <?php echo isActive('create-event.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <span class="sidebar-text">Create Event</span>
            </a>
        <?php endif; ?>

        <!-- Events for Regular Users -->
        <?php if ($userRole === 'user'): ?>
            <div class="sidebar-category">
                <span class="sidebar-category-text">EVENTS</span>
            </div>

            <a href="<?php echo BASE_URL; ?>user/events/" class="sidebar-link <?php echo isActive('/user/events/') && !isActive('view-event.php') && !isActive('my-events.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <span class="sidebar-text">Community Events</span>
            </a>

            <a href="<?php echo BASE_URL; ?>user/events/my-events.php" class="sidebar-link <?php echo isActive('/user/events/my-events.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <span class="sidebar-text">My Events</span>
            </a>
        <?php endif; ?>

        <!-- Access Code Management (for all roles except Guard) -->
        <?php if ($userRole !== 'guard'): ?>
            <div class="sidebar-category">
                <span class="sidebar-category-text">ACCESS CODES</span>
            </div>

            <a href="<?php echo BASE_URL; ?>access-codes/generate.php" class="sidebar-link <?php echo isActive('generate.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <span class="sidebar-text">Generate Code</span>
            </a>

            <a href="<?php echo BASE_URL; ?>access-codes/" class="sidebar-link <?php echo isActive('/access-codes/') && !isActive('generate.php') && !isActive('verify.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-key"></i>
                </div>
                <span class="sidebar-text">Manage Codes</span>
            </a>
        <?php endif; ?>


        <!-- ===== USER DUES PAYMENT SECTION - HOUSEHOLD HEADS ONLY ===== -->
<?php if ($userRole === 'user' && $isHouseholdHead): ?>
    <div class="sidebar-category">
        <span class="sidebar-category-text">Payments</span>
    </div>

    <a href="<?php echo BASE_URL; ?>user/my-dues.php" class="sidebar-link sidebar-link-dues <?php echo isActive('/user/my-dues.php') ? 'active' : ''; ?>">
        <div class="sidebar-icon">
            <i class="fas fa-money-check-alt"></i>
        </div>
        <span class="sidebar-text">Service Charge</span>
        <?php if (!empty($userDuesInfo) && $userDuesInfo['pending_count'] > 0): ?>
            <div class="sidebar-badge sidebar-badge-<?php echo $userDuesInfo['overdue_count'] > 0 ? 'danger' : 'warning'; ?> sidebar-badge-pulse">
                <?php echo $userDuesInfo['pending_count']; ?>
                <?php if ($userDuesInfo['overdue_count'] > 0): ?>
                    <i class="fas fa-exclamation-triangle sidebar-badge-icon"></i>
                <?php endif; ?>
            </div>
        <?php elseif (!empty($userDuesInfo) && $userDuesInfo['has_dues'] && $userDuesInfo['pending_count'] === 0): ?>
            <!-- Show green checkmark if user has dues but all are paid -->
            <div class="sidebar-badge sidebar-badge-success">
                <i class="fas fa-check"></i>
            </div>
        <?php endif; ?>
    </a>

    <!-- Always show payment history link for household heads, but only show badge if they have paid dues -->
    <a href="<?php echo BASE_URL; ?>user/payment-history.php" class="sidebar-link <?php echo isActive('/user/payment-history.php') ? 'active' : ''; ?>">
        <div class="sidebar-icon">
            <i class="fas fa-history"></i>
        </div>
        <span class="sidebar-text">Payment History</span>
        <?php if (!empty($userDuesInfo) && $userDuesInfo['paid_count'] > 0): ?>
            <div class="sidebar-badge sidebar-badge-success">
                <?php echo $userDuesInfo['paid_count']; ?>
            </div>
        <?php endif; ?>
    </a>
<?php endif; ?>

        <!-- Guard Menu Items -->
        <?php if ($userRole === 'guard'): ?>
            <div class="sidebar-category">
                <span class="sidebar-category-text">SECURITY</span>
            </div>

            <a href="<?php echo BASE_URL; ?>access-codes/verify.php" class="sidebar-link <?php echo isActive('verify.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-qrcode"></i>
                </div>
                <span class="sidebar-text">Verify Access Code</span>
            </a>

            <a href="<?php echo BASE_URL; ?>access-logs/" class="sidebar-link <?php echo isActive('/access-logs/') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <span class="sidebar-text">Access Logs</span>
            </a>
        <?php endif; ?>

        <!-- Marketplace Menu Items (for all roles) -->
        <div class="sidebar-category">
            <span class="sidebar-category-text">MARKETPLACE</span>
        </div>

        <!-- Browse Marketplace - Fixed to exclude analytics pages -->
        <a href="<?php echo BASE_URL; ?>marketplace/" class="sidebar-link <?php echo isActive('/marketplace/') && !isActive('wishlist.php') && !isActive('my-listings.php') && !isActive('messages.php') && !isActive('create-listing.php') && !isActive('user-analytics.php') && !isActive('analytics.php') && !isActive('profile.php') ? 'active' : ''; ?>">
            <div class="sidebar-icon">
                <i class="fas fa-store"></i>
            </div>
            <span class="sidebar-text">Browse Marketplace</span>
        </a>

        <?php if (!$isMarketplaceUser): ?>
            <a href="<?php echo BASE_URL; ?>marketplace/register.php" class="sidebar-link <?php echo isActive('register.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <span class="sidebar-text">Join Marketplace</span>
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>marketplace/wishlist.php" class="sidebar-link <?php echo isActive('wishlist.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-heart"></i>
                </div>
                <span class="sidebar-text">My Wishlist</span>
            </a>

            <a href="<?php echo BASE_URL; ?>marketplace/messages.php" class="sidebar-link <?php echo isActive('messages.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <span class="sidebar-text">Marketplace Messages</span>
                <?php if ($unreadMessageCount > 0): ?>
                    <div class="sidebar-badge sidebar-badge-danger"><?php echo $unreadMessageCount; ?></div>
                <?php endif; ?>
            </a>

            <?php if ($isMarketplaceSeller): ?>
                <a href="<?php echo BASE_URL; ?>marketplace/my-listings.php" class="sidebar-link <?php echo isActive('my-listings.php') ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <span class="sidebar-text">My Listings</span>
                </a>

                <a href="<?php echo BASE_URL; ?>marketplace/create-listing.php" class="sidebar-link <?php echo isActive('create-listing.php') ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-plus"></i>
                    </div>
                    <span class="sidebar-text">Create Listing</span>
                </a>
            <?php endif; ?>

            <?php if (isset($isMarketplaceSeller) && $isMarketplaceSeller): ?>
                <!-- My Analytics - Only visible to sellers -->
                <a href="<?php echo BASE_URL; ?>marketplace/user-analytics.php" class="sidebar-link <?php echo isActive('user-analytics.php') && strpos($currentUrl, 'marketplace') !== false ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <span class="sidebar-text">My Analytics</span>
                </a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>marketplace/profile.php" class="sidebar-link <?php echo isActive('profile.php') && strpos($currentUrl, 'marketplace') !== false ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <span class="sidebar-text">My Profile</span>
            </a>
        <?php endif; ?>

        <!-- Marketplace Admin Analytics - Changed to more specific check to avoid matching user-analytics.php -->
        <?php if ($userRole === 'clan_admin'): ?>
            <a href="<?php echo BASE_URL; ?>marketplace/analytics.php" class="sidebar-link <?php echo (isActive('analytics.php') && !isActive('user-analytics.php') && strpos($currentUrl, 'marketplace') !== false) ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <span class="sidebar-text">Marketplace Analytics</span>
            </a>
        <?php endif; ?>

        <?php if ($userRole === 'super_admin'): ?>
            <a href="<?php echo BASE_URL; ?>marketplace/super-admin-analytics.php" class="sidebar-link <?php echo isActive('super-admin-analytics.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span class="sidebar-text">Global Marketplace Analytics</span>
            </a>
        <?php endif; ?>

        <a href="<?php echo BASE_URL; ?>marketplace/safety-tips.php" class="sidebar-link <?php echo isActive('safety-tips.php') ? 'active' : ''; ?>">
            <div class="sidebar-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <span class="sidebar-text">Safety Tips</span>
        </a>

        <div class="sidebar-category">
            <span class="sidebar-category-text">COMMUNICATION</span>
        </div>
<!-- ADD THIS - Announcements Link -->
<a href="<?php echo BASE_URL; ?>announcements/" class="sidebar-link <?php echo isActive('/announcements/') ? 'active' : ''; ?>">
    <div class="sidebar-icon">
        <i class="fas fa-bullhorn"></i>
    </div>
    <span class="sidebar-text">Announcements</span>
    <?php
    // Optional: Get unread announcements count
    try {
        $unreadAnnouncementsQuery = $db->fetchOne(
            "SELECT COUNT(*) as count 
             FROM announcements 
             WHERE clan_id = ? 
             AND created_at > COALESCE(
                 (SELECT last_read_at FROM user_announcement_reads 
                  WHERE user_id = ? LIMIT 1),
                 DATE_SUB(NOW(), INTERVAL 7 DAY)
             )",
            [$_SESSION['clan_id'], $_SESSION['user_id']]
        );
        $unreadAnnouncementsCount = $unreadAnnouncementsQuery ? $unreadAnnouncementsQuery['count'] : 0;
    } catch (Exception $e) {
        $unreadAnnouncementsCount = 0;
    }
    
    if ($unreadAnnouncementsCount > 0):
    ?>
        <div class="sidebar-badge sidebar-badge-primary"><?php echo $unreadAnnouncementsCount; ?></div>
    <?php endif; ?>
</a>
        <a href="<?php echo BASE_URL; ?>chat/" class="sidebar-link <?php echo isActive('/chat/') ? 'active' : ''; ?>">
            <div class="sidebar-icon">
                <i class="fas fa-comments"></i>
            </div>
            <span class="sidebar-text">Chat</span>
            <?php
            // Get unread messages count if available (filtered by user's clan)
            $unreadCount = 0;
            if (isset($chat)) {
                $unreadCount = $chat->getTotalUnreadCount($currentUser->getId(), $currentUser->getClanId());
            }
            if ($unreadCount > 0):
            ?>
                <div id="chatUnreadBadge" class="sidebar-badge sidebar-badge-danger"><?php echo $unreadCount; ?></div>
            <?php endif; ?>
        </a>

        <!-- Support Section for Users -->
        <div class="sidebar-category">
            <span class="sidebar-category-text">SUPPORT & HELP</span>
        </div>
        
        <a href="<?php echo BASE_URL; ?>help/create-ticket.php" class="sidebar-link <?php echo isActive('/help/create-ticket.php') ? 'active' : ''; ?>">
            <div class="sidebar-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <span class="sidebar-text">Contact Support</span>
        </a>
        <a href="<?php echo BASE_URL; ?>help/my-tickets.php" class="sidebar-link <?php echo isActive('/help/my-tickets.php') ? 'active' : ''; ?>">
            <div class="sidebar-icon">
                <i class="fas fa-ticket-alt"></i>
            </div>
            <span class="sidebar-text">My Support Tickets</span>
            <?php if ($openTicketCount > 0): ?>
                <div class="sidebar-badge sidebar-badge-warning"><?php echo $openTicketCount; ?></div>
            <?php endif; ?>
        </a>


        <!-- PWA Install Button -->
        <div class="sidebar-category">
            <span class="sidebar-category-text">INSTALL APP</span>
        </div>
        <a href="#" id="pwa-install-btn" class="sidebar-link">
            <div class="sidebar-icon">
                <i class="fas fa-download"></i>
            </div>
            <span class="sidebar-text">Install Gate Wey</span>
        </a>

        <?php if ($userRole !== 'guard'): ?>
            <!-- Reports Section -->
            <div class="sidebar-category">
                <span class="sidebar-category-text">REPORTS</span>
            </div>

            <a href="<?php echo BASE_URL; ?>reports/visitors.php" class="sidebar-link <?php echo isActive('visitors.php') ? 'active' : ''; ?>">
                <div class="sidebar-icon">
                    <i class="fas fa-user-clock"></i>
                </div>
                <span class="sidebar-text">Visitor Analytics</span>
            </a>

            <!-- Dues Reports for Admins Only -->
            <?php if (($userRole === 'super_admin' || $userRole === 'clan_admin') && !empty($clanDuesStats)): ?>
                <a href="<?php echo BASE_URL; ?>reports/dues.php" class="sidebar-link <?php echo isActive('dues.php') ? 'active' : ''; ?>">
                    <div class="sidebar-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <span class="sidebar-text">Dues Reports</span>
                </a>
            <?php endif; ?>

            <!-- Personal Dues Reports for Users (if they have dues) -->
            <!--<?php if ($userRole === 'user' && !empty($userDuesInfo) && $userDuesInfo['has_dues']): ?>-->
            <!--    <a href="<?php echo BASE_URL; ?>reports/my-dues.php" class="sidebar-link <?php echo isActive('my-dues.php') && strpos($currentUrl, 'reports') !== false ? 'active' : ''; ?>">-->
            <!--        <div class="sidebar-icon">-->
            <!--            <i class="fas fa-chart-pie"></i>-->
            <!--        </div>-->
            <!--        <span class="sidebar-text">My Dues Reports</span>-->
            <!--    </a>-->
            <!--<?php endif; ?>-->
        <?php endif; ?>

        <!-- Settings and Help Section -->
        <div class="sidebar-category">
            <span class="sidebar-category-text">ACCOUNT</span>
        </div>

        <a href="<?php echo BASE_URL; ?>profile/" class="sidebar-link <?php echo isActive('/profile/') ? 'active' : ''; ?>">
            <div class="sidebar-icon">
                <i class="fas fa-user"></i>
            </div>
            <span class="sidebar-text">Profile</span>
        </a>

        <a href="<?php echo BASE_URL; ?>logout.php" class="sidebar-link">
            <div class="sidebar-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <span class="sidebar-text">Logout</span>
        </a>
    </div>
</div>


<!-- Enhanced JavaScript for Dasher UI Sidebar -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🎨 Initializing Dasher UI sidebar...');

        // ===== MOBILE SIDEBAR FUNCTIONALITY =====
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const content = document.querySelector('.content');

        if (sidebarToggle && sidebar && content) {
            let isMobileOpen = false;

            function toggleMobileSidebar() {
                if (window.innerWidth <= 768) {
                    isMobileOpen = !isMobileOpen;

                    if (isMobileOpen) {
                        sidebar.classList.add('active', 'mobile-floating');
                        content.classList.add('sidebar-active');
                        document.body.style.overflow = 'hidden';
                        sidebarToggle.setAttribute('aria-expanded', 'true');
                    } else {
                        sidebar.classList.remove('active', 'mobile-floating');
                        content.classList.remove('sidebar-active');
                        document.body.style.overflow = '';
                        sidebarToggle.setAttribute('aria-expanded', 'false');
                    }

                    console.log('📱 Dasher sidebar:', isMobileOpen ? 'OPEN' : 'CLOSED');
                }
            }

            // Toggle on button click
            sidebarToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMobileSidebar();
            });

            // Close on outside click (mobile only)
            document.addEventListener('click', function(e) {
                if (window.innerWidth <= 768 && isMobileOpen) {
                    const clickedInSidebar = sidebar.contains(e.target);
                    const clickedToggle = sidebarToggle.contains(e.target);

                    if (!clickedInSidebar && !clickedToggle) {
                        isMobileOpen = false;
                        sidebar.classList.remove('active', 'mobile-floating');
                        content.classList.remove('sidebar-active');
                        document.body.style.overflow = '';
                        sidebarToggle.setAttribute('aria-expanded', 'false');
                    }
                }
            });

            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isMobileOpen && window.innerWidth <= 768) {
                    isMobileOpen = false;
                    sidebar.classList.remove('active', 'mobile-floating');
                    content.classList.remove('sidebar-active');
                    document.body.style.overflow = '';
                    sidebarToggle.setAttribute('aria-expanded', 'false');
                }
            });

            // Reset on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    isMobileOpen = false;
                    sidebar.classList.remove('active', 'mobile-floating');
                    content.classList.remove('sidebar-active');
                    document.body.style.overflow = '';
                    sidebarToggle.setAttribute('aria-expanded', 'false');
                }
            });

            // Close when clicking menu items on mobile
            sidebar.addEventListener('click', function(e) {
                const link = e.target.closest('a.sidebar-link');
                if (link && window.innerWidth <= 768 && isMobileOpen) {
                    setTimeout(() => {
                        isMobileOpen = false;
                        sidebar.classList.remove('active', 'mobile-floating');
                        content.classList.remove('sidebar-active');
                        document.body.style.overflow = '';
                        sidebarToggle.setAttribute('aria-expanded', 'false');
                    }, 100);
                }
            });
        }

        // ===== ENHANCED BADGE UPDATES =====

        function animateBadgeUpdate(badge, newValue) {
            if (badge) {
                badge.style.transform = 'scale(1.2)';
                badge.style.transition = 'transform 0.3s ease';

                setTimeout(() => {
                    badge.textContent = newValue;
                    badge.style.transform = 'scale(1)';
                }, 150);
            }
        }

        // Update ticket counts with Dasher UI styling
        function updateTicketCounts() {
            fetch('<?php echo BASE_URL; ?>api/get-ticket-counts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update user ticket count
                        const userTicketBadge = document.querySelector('.sidebar-link[href*="help/my-tickets.php"] .sidebar-badge');
                        if (userTicketBadge && data.openTickets > 0) {
                            animateBadgeUpdate(userTicketBadge, data.openTickets);
                            userTicketBadge.className = 'sidebar-badge sidebar-badge-warning';
                            userTicketBadge.style.display = 'flex';
                        } else if (userTicketBadge && data.openTickets === 0) {
                            userTicketBadge.style.display = 'none';
                        }

                        // Update admin ticket count
                        const adminTicketBadge = document.querySelector('.sidebar-link[href*="admin/tickets/"] .sidebar-badge');
                        if (adminTicketBadge && data.adminTickets > 0) {
                            animateBadgeUpdate(adminTicketBadge, data.adminTickets);
                            adminTicketBadge.className = `sidebar-badge sidebar-badge-${data.urgentTickets > 0 ? 'danger' : 'warning'}`;
                            adminTicketBadge.style.display = 'flex';
                        } else if (adminTicketBadge && data.adminTickets === 0) {
                            adminTicketBadge.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.log('Ticket count update failed:', error);
                });
        }

        // Update clan dues counts with Dasher UI styling
        function updateClanDuesCounts() {
            fetch('<?php echo BASE_URL; ?>api/get-clan-dues-counts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const paymentMgmtBadge = document.querySelector('.sidebar-link[href*="clan-dues/payments.php"] .sidebar-badge');
                        if (paymentMgmtBadge && (data.overdueCount > 0 || data.pendingCount > 0)) {
                            const totalCount = data.overdueCount + data.pendingCount;
                            animateBadgeUpdate(paymentMgmtBadge, totalCount);
                            paymentMgmtBadge.className = `sidebar-badge sidebar-badge-${data.overdueCount > 0 ? 'danger' : 'warning'}`;
                            paymentMgmtBadge.style.display = 'flex';
                        } else if (paymentMgmtBadge && data.overdueCount === 0 && data.pendingCount === 0) {
                            paymentMgmtBadge.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.log('Clan dues count update failed:', error);
                });
        }

        // Update user dues counts with enhanced Dasher UI styling
        function updateUserDuesCounts() {
            if ('<?php echo $userRole; ?>' !== 'user') {
                return;
            }

            fetch('<?php echo BASE_URL; ?>api/get-user-dues-counts.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const userDuesBadge = document.querySelector('.sidebar-link[href*="my-dues.php"] .sidebar-badge');
                        const userDuesLink = document.querySelector('.sidebar-link[href*="my-dues.php"]');

                        if (data.hasDues && userDuesBadge && data.pendingCount > 0) {
                            animateBadgeUpdate(userDuesBadge, data.pendingCount);

                            if (data.overdueCount > 0) {
                                userDuesBadge.className = 'sidebar-badge sidebar-badge-danger sidebar-badge-pulse';
                                // Add urgency indicator if not present
                                if (!userDuesBadge.querySelector('.sidebar-badge-icon')) {
                                    const urgentIcon = document.createElement('i');
                                    urgentIcon.className = 'fas fa-exclamation-triangle sidebar-badge-icon';
                                    userDuesBadge.appendChild(urgentIcon);
                                }
                            } else {
                                userDuesBadge.className = 'sidebar-badge sidebar-badge-warning sidebar-badge-pulse';
                                // Remove urgency indicator if present
                                const urgentIcon = userDuesBadge.querySelector('.sidebar-badge-icon');
                                if (urgentIcon) {
                                    urgentIcon.remove();
                                }
                            }
                            userDuesBadge.style.display = 'flex';
                        } else if (userDuesBadge && data.pendingCount === 0) {
                            userDuesBadge.style.display = 'none';
                        }

                        // Update payment history badge
                        const historyBadge = document.querySelector('.sidebar-link[href*="payment-history.php"] .sidebar-badge');
                        if (historyBadge && data.paidCount > 0) {
                            animateBadgeUpdate(historyBadge, data.paidCount);
                            historyBadge.className = 'sidebar-badge sidebar-badge-success';
                            historyBadge.style.display = 'flex';
                        }
                    }
                })
                .catch(error => {
                    console.log('User dues count update failed:', error);
                });
        }

        // Set up periodic updates
        setInterval(updateTicketCounts, 30000);

        if ('<?php echo $userRole; ?>' === 'super_admin' || '<?php echo $userRole; ?>' === 'clan_admin') {
            setInterval(updateClanDuesCounts, 45000);
        }

        if ('<?php echo $userRole; ?>' === 'user') {
            setInterval(updateUserDuesCounts, 60000);
            setTimeout(updateUserDuesCounts, 2000);
        }

        // ===== ENHANCED INTERACTION FEATURES =====

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.altKey) {
                switch (e.key) {
                    case 'h':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>help/';
                        break;
                    case 't':
                        e.preventDefault();
                        window.location.href = '<?php echo BASE_URL; ?>help/my-tickets.php';
                        break;
                    case 'd':
                        e.preventDefault();
                        if ('<?php echo $userRole; ?>' === 'user') {
                            window.location.href = '<?php echo BASE_URL; ?>user/my-dues.php';
                        } else if ('<?php echo $userRole; ?>' === 'clan_admin' || '<?php echo $userRole; ?>' === 'super_admin') {
                            window.location.href = '<?php echo BASE_URL; ?>admin/clan-dues/';
                        }
                        break;
                }
            }
        });

        // Add tooltips with keyboard shortcuts
        const shortcuts = {
            'help/': 'Help Center (Alt+Ctrl+H)',
            'help/my-tickets.php': 'Support Tickets (Alt+Ctrl+T)',
            'my-dues.php': 'My Dues Payments (Alt+Ctrl+D)',
            'clan-dues/': 'Clan Dues Dashboard (Alt+Ctrl+D)'
        };

        Object.entries(shortcuts).forEach(([url, tooltip]) => {
            const link = document.querySelector(`.sidebar-link[href*="${url}"]`);
            if (link) {
                link.title = tooltip;
            }
        });

        // Add click tracking
        document.querySelectorAll('.sidebar-link').forEach(link => {
            link.addEventListener('click', function() {
                const href = this.getAttribute('href');
                if (href && href !== '#') {
                    const trackingData = {
                        action: 'sidebar_navigation',
                        page: href,
                        user_role: '<?php echo $userRole; ?>',
                        timestamp: Date.now()
                    };

                    fetch('<?php echo BASE_URL; ?>api/track-usage.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(trackingData)
                    }).catch(() => {}); // Ignore errors
                }
            });
        });

        // Smooth scroll for better UX
        document.querySelectorAll('.sidebar-link[href^="#"]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Performance optimization for mobile
        if (window.innerWidth <= 768) {
            let scrollTimer;
            sidebar.addEventListener('scroll', function() {
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(() => {
                    // Update scroll-dependent elements if needed
                }, 16);
            }, {
                passive: true
            });
        }

        console.log('✅ Dasher UI sidebar initialized successfully');
    });

    // Export functions for external use
    window.updateSidebarCounts = function() {
        if (typeof updateTicketCounts === 'function') updateTicketCounts();
        if (typeof updateClanDuesCounts === 'function') updateClanDuesCounts();
        if (typeof updateUserDuesCounts === 'function') updateUserDuesCounts();
    };

    // Debug function for development
    if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
        window.testDasherSidebar = function() {
            console.log('🧪 Testing Dasher UI Sidebar...');
            console.log('Sidebar elements:', document.querySelectorAll('.sidebar-link').length);
            console.log('Badges:', document.querySelectorAll('.sidebar-badge').length);
            console.log('Categories:', document.querySelectorAll('.sidebar-category').length);
            console.log('CSS variables loaded:', !!getComputedStyle(document.documentElement).getPropertyValue('--primary'));
            console.log('User role:', '<?php echo $userRole; ?>');
        };
        console.log('🔧 Debug function available: testDasherSidebar()');
    }
</script>