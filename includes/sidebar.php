<?php
/**
 * Lekki Astro Sports Club
 * Sidebar Navigation Component
 * Automatically shows the correct nav items based on user role.
 */

$role       = $_SESSION['role'] ?? 'user';
$isAdmin    = in_array($role, ['admin', 'super_admin'], true);
$currentUri = $_SERVER['REQUEST_URI'];
?>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">

    <!-- User profile card inside sidebar -->
    <div class="sidebar-profile">
        <div class="sidebar-avatar">
            <?php echo e(getInitials($currentUser->getFullName())); ?>
        </div>
        <div class="sidebar-profile-info">
            <p class="sidebar-profile-name"><?php echo e($currentUser->getFullName()); ?></p>
            <span class="sidebar-profile-role"><?php echo e(str_replace('_', ' ', $role)); ?></span>
        </div>
    </div>

    <nav class="sidebar-nav-wrapper">

        <!-- ===== MAIN SECTION ===== -->
        <div class="sidebar-section-label">Main</div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>dashboard/"
                   class="sidebar-link <?php echo str_contains($currentUri, '/dashboard') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-th-large"></i></span>
                    Dashboard
                </a>
            </li>
        </ul>

        <?php if ($isAdmin): ?>
        <!-- ===== ADMIN SECTIONS ===== -->

        <div class="sidebar-section-label">Management</div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>members/"
                   class="sidebar-link <?php echo str_contains($currentUri, '/members') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-users"></i></span>
                    Members
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>payments/"
                   class="sidebar-link <?php echo str_contains($currentUri, '/payments') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-wallet"></i></span>
                    Payments & Dues
                </a>
            </li>
        </ul>

        <div class="sidebar-section-label">Communication</div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>announcements/manage.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/announcements') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-bullhorn"></i></span>
                    Announcements
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>polls/manage.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/polls') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-poll"></i></span>
                    Polls & Voting
                </a>
            </li>
        </ul>

        <div class="sidebar-section-label">Club Activities</div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>events/manage.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/events') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-calendar-alt"></i></span>
                    Events & Calendar
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/tournaments') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-trophy"></i></span>
                    Tournaments
                </a>
            </li>
        </ul>

        <div class="sidebar-section-label">Resources</div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>documents/manage.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/documents') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-folder-open"></i></span>
                    Documents
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>reports/index.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/reports') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-chart-bar"></i></span>
                    Reports & Analytics
                </a>
            </li>
        </ul>

        <?php if (isSuperAdmin()): ?>
        <div class="sidebar-section-label">System</div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/admins.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/admin/') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-user-shield"></i></span>
                    Admin Accounts
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>admin/settings.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/settings') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-sliders-h"></i></span>
                    Settings
                </a>
            </li>
        </ul>
        <?php endif; ?>

        <?php else: ?>
        <!-- ===== MEMBER SECTIONS ===== -->

        <div class="sidebar-section-label">My Account</div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>profile/"
                   class="sidebar-link <?php echo str_contains($currentUri, '/profile') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-user"></i></span>
                    My Profile
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>notifications/index.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/notifications') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-bell"></i></span>
                    Notifications
                    <?php
                    $db = Database::getInstance();
                    $unreadCount = (int)($db->fetchOne(
                        "SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0",
                        [(int)$_SESSION['user_id']]
                    )['n'] ?? 0);
                    if ($unreadCount > 0): ?>
                    <span class="badge badge-danger ms-auto" style="font-size:10px;padding:2px 6px"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>payments/my-payments.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/payments') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-receipt"></i></span>
                    My Payments
                </a>
            </li>
        </ul>

        <?php
        $annUnread = 0;
        if (class_exists('Announcement')) {
            $annUnread = (new Announcement())->getUnreadCount((int)$_SESSION['user_id']);
        }
        ?>
        <div class="sidebar-section-label">Club</div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>announcements/index.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/announcements') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-bullhorn"></i></span>
                    Announcements
                    <?php if ($annUnread > 0): ?>
                    <span class="badge badge-danger ms-auto" style="font-size:10px;padding:2px 6px"><?php echo $annUnread; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>polls/index.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/polls') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-poll"></i></span>
                    Polls
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>events/index.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/events') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-calendar-alt"></i></span>
                    Events
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>tournaments/index.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/tournaments') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-trophy"></i></span>
                    Tournaments
                </a>
            </li>
            <li class="sidebar-item">
                <a href="<?php echo BASE_URL; ?>documents/index.php"
                   class="sidebar-link <?php echo str_contains($currentUri, '/documents') ? 'active' : ''; ?>">
                    <span class="sidebar-icon"><i class="fas fa-folder-open"></i></span>
                    Documents
                </a>
            </li>
        </ul>

        <?php endif; ?>

        <!-- PWA install button (shown by JS when installable) -->
        <div class="sidebar-section-label">App</div>
        <ul class="sidebar-nav">
            <li class="sidebar-item">
                <button class="sidebar-link w-100 border-0 bg-transparent text-start"
                        id="pwa-install-btn" style="display:none">
                    <span class="sidebar-icon"><i class="fas fa-download"></i></span>
                    Install App
                </button>
            </li>
        </ul>

    </nav><!-- /sidebar-nav-wrapper -->
</aside>
<!-- ===== END SIDEBAR ===== -->

<!-- Content area starts here -->
<div class="content" id="main-content">

<style>
    /* ===== SIDEBAR STYLES ===== */
    .sidebar-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(20, 26, 33, 0.5);
        z-index: 1025;
        backdrop-filter: blur(2px);
    }
    .sidebar-overlay.show { display: block; }

    .sidebar {
        width: var(--sidebar-width);
        background: var(--bg-sidebar);
        border-right: 1px solid var(--border-color);
        position: fixed;
        top: var(--navbar-height); left: 0; bottom: 0;
        overflow-y: auto; overflow-x: hidden;
        z-index: 1026;
        transition: transform var(--transition-normal), var(--theme-transition);
        display: flex; flex-direction: column;
        scrollbar-width: thin;
        scrollbar-color: var(--border-color) transparent;
    }
    .sidebar::-webkit-scrollbar { width: 4px; }
    .sidebar::-webkit-scrollbar-track { background: transparent; }
    .sidebar::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }

    /* User profile area */
    .sidebar-profile {
        display: flex; align-items: center; gap: var(--spacing-3);
        padding: var(--spacing-5) var(--spacing-5) var(--spacing-4);
        border-bottom: 1px solid var(--border-color);
        margin-bottom: var(--spacing-2);
    }
    .sidebar-avatar {
        width: 40px; height: 40px; flex-shrink: 0;
        border-radius: var(--border-radius-full);
        background: linear-gradient(135deg, var(--primary), var(--primary-700));
        color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: var(--font-weight-semibold);
        font-size: var(--font-size-sm);
    }
    .sidebar-profile-name {
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-semibold);
        color: var(--text-primary);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar-profile-role {
        font-size: var(--font-size-xs);
        color: var(--primary);
        font-weight: var(--font-weight-medium);
        text-transform: capitalize;
    }

    /* Nav wrapper */
    .sidebar-nav-wrapper { padding: 0 0 var(--spacing-6); flex: 1; }

    /* Section labels */
    .sidebar-section-label {
        padding: var(--spacing-4) var(--spacing-5) var(--spacing-2);
        font-size: 10px;
        font-weight: var(--font-weight-bold);
        text-transform: uppercase;
        letter-spacing: 1px;
        color: var(--text-muted);
    }

    /* Nav items */
    .sidebar-nav { list-style: none; padding: 0 var(--spacing-3); margin: 0 0 var(--spacing-1); }
    .sidebar-item { margin-bottom: 2px; }
    .sidebar-link {
        display: flex; align-items: center; gap: var(--spacing-3);
        padding: var(--spacing-3) var(--spacing-3);
        color: var(--text-secondary);
        text-decoration: none;
        border-radius: var(--border-radius);
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        transition: var(--transition-fast);
        cursor: pointer;
    }
    .sidebar-link:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }
    .sidebar-link.active {
        background: var(--primary-light);
        color: var(--primary);
        font-weight: var(--font-weight-semibold);
    }
    .sidebar-icon {
        width: 22px; height: 22px;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; flex-shrink: 0;
    }

    /* Mobile behaviour */
    @media (max-width: 768px) {
        .sidebar { transform: translateX(-100%); top: var(--navbar-height); }
        .sidebar.show { transform: translateX(0); }
        .content { margin-left: 0 !important; }
    }

    /* Content offset */
    .content {
        margin-left: var(--sidebar-width);
        margin-top: var(--navbar-height);
        min-height: calc(100vh - var(--navbar-height));
        background: var(--bg-primary);
        transition: var(--theme-transition);
        padding: var(--spacing-8);
    }
    @media (max-width: 576px) { .content { padding: var(--spacing-4); } }
</style>

<script>
// Sidebar toggle for mobile
(function () {
    var toggleBtn = document.getElementById('sidebar-toggle');
    var sidebar   = document.getElementById('sidebar');
    var overlay   = document.getElementById('sidebar-overlay');

    function openSidebar()  { sidebar.classList.add('show'); overlay.classList.add('show'); }
    function closeSidebar() { sidebar.classList.remove('show'); overlay.classList.remove('show'); }

    if (toggleBtn) toggleBtn.addEventListener('click', function () {
        sidebar.classList.contains('show') ? closeSidebar() : openSidebar();
    });
    if (overlay) overlay.addEventListener('click', closeSidebar);
})();
</script>
