<?php
/**
 * Lekki Astro Sports Club
 * Sidebar Navigation — iOS-style grouped nav cards with icon chips + chevrons.
 */

$role       = $_SESSION['role'] ?? 'user';
$isAdmin    = in_array($role, ['admin', 'super_admin'], true);
$currentUri = $_SERVER['REQUEST_URI'];
?>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- ===== SIDEBAR ===== -->
<aside class="sidebar" id="sidebar">

    <!-- User profile area -->
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

        <!-- ===== MAIN ===== -->
        <div class="sidebar-section-label">Main</div>
        <div class="sidebar-nav-card">
            <a href="<?php echo BASE_URL; ?>dashboard/"
               class="sidebar-link <?php echo str_contains($currentUri, '/dashboard') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip primary"><i class="fas fa-th-large"></i></span>
                <span class="sidebar-link-label">Dashboard</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
        </div>

        <?php if ($isAdmin): ?>
        <!-- ===== MANAGEMENT ===== -->
        <div class="sidebar-section-label">Management</div>
        <div class="sidebar-nav-card">
            <a href="<?php echo BASE_URL; ?>members/"
               class="sidebar-link <?php echo str_contains($currentUri, '/members') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip blue"><i class="fas fa-users"></i></span>
                <span class="sidebar-link-label">Members</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>payments/"
               class="sidebar-link <?php echo str_contains($currentUri, '/payments') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip orange"><i class="fas fa-wallet"></i></span>
                <span class="sidebar-link-label">Payments & Dues</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
        </div>

        <!-- ===== COMMUNICATION ===== -->
        <div class="sidebar-section-label">Communication</div>
        <div class="sidebar-nav-card">
            <a href="<?php echo BASE_URL; ?>announcements/manage.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/announcements') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip purple"><i class="fas fa-bullhorn"></i></span>
                <span class="sidebar-link-label">Announcements</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>polls/manage.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/polls') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip indigo"><i class="fas fa-poll"></i></span>
                <span class="sidebar-link-label">Polls & Voting</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
        </div>

        <!-- ===== CLUB ACTIVITIES ===== -->
        <div class="sidebar-section-label">Club Activities</div>
        <div class="sidebar-nav-card">
            <a href="<?php echo BASE_URL; ?>events/manage.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/events') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip green"><i class="fas fa-calendar-alt"></i></span>
                <span class="sidebar-link-label">Events & Calendar</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>tournaments/manage.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/tournaments') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip orange"><i class="fas fa-trophy"></i></span>
                <span class="sidebar-link-label">Tournaments</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
        </div>

        <!-- ===== RESOURCES ===== -->
        <div class="sidebar-section-label">Resources</div>
        <div class="sidebar-nav-card">
            <a href="<?php echo BASE_URL; ?>documents/manage.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/documents') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip gray"><i class="fas fa-folder-open"></i></span>
                <span class="sidebar-link-label">Documents</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>reports/index.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/reports') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip cyan"><i class="fas fa-chart-bar"></i></span>
                <span class="sidebar-link-label">Reports & Analytics</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
        </div>

        <?php if (isSuperAdmin()): ?>
        <!-- ===== SYSTEM ===== -->
        <div class="sidebar-section-label">System</div>
        <div class="sidebar-nav-card">
            <a href="<?php echo BASE_URL; ?>admin/admins.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/admin/') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip red"><i class="fas fa-user-shield"></i></span>
                <span class="sidebar-link-label">Admin Accounts</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/settings.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/settings') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip gray"><i class="fas fa-sliders-h"></i></span>
                <span class="sidebar-link-label">Settings</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- ===== MY ACCOUNT (MEMBER) ===== -->
        <?php
        $db = Database::getInstance();
        $unreadCount = (int)($db->fetchOne(
            "SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0",
            [(int)$_SESSION['user_id']]
        )['n'] ?? 0);
        $annUnread = 0;
        if (class_exists('Announcement')) {
            $annUnread = (new Announcement())->getUnreadCount((int)$_SESSION['user_id']);
        }
        ?>
        <div class="sidebar-section-label">My Account</div>
        <div class="sidebar-nav-card">
            <a href="<?php echo BASE_URL; ?>profile/"
               class="sidebar-link <?php echo str_contains($currentUri, '/profile') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip purple"><i class="fas fa-user"></i></span>
                <span class="sidebar-link-label">My Profile</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>notifications/index.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/notifications') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip gray"><i class="fas fa-bell"></i></span>
                <span class="sidebar-link-label">
                    Notifications
                    <?php if ($unreadCount > 0): ?>
                    <span class="sidebar-badge"><?php echo $unreadCount > 9 ? '9+' : $unreadCount; ?></span>
                    <?php endif; ?>
                </span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>payments/my-payments.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/payments') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip orange"><i class="fas fa-receipt"></i></span>
                <span class="sidebar-link-label">My Payments</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
        </div>

        <!-- ===== CLUB (MEMBER) ===== -->
        <div class="sidebar-section-label">Club</div>
        <div class="sidebar-nav-card">
            <a href="<?php echo BASE_URL; ?>announcements/index.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/announcements') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip blue"><i class="fas fa-bullhorn"></i></span>
                <span class="sidebar-link-label">
                    Announcements
                    <?php if ($annUnread > 0): ?>
                    <span class="sidebar-badge"><?php echo $annUnread; ?></span>
                    <?php endif; ?>
                </span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>polls/index.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/polls') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip indigo"><i class="fas fa-poll"></i></span>
                <span class="sidebar-link-label">Polls</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>events/index.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/events') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip green"><i class="fas fa-calendar-alt"></i></span>
                <span class="sidebar-link-label">Events</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>tournaments/index.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/tournaments') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip orange"><i class="fas fa-trophy"></i></span>
                <span class="sidebar-link-label">Tournaments</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>documents/index.php"
               class="sidebar-link <?php echo str_contains($currentUri, '/documents') ? 'active' : ''; ?>">
                <span class="sidebar-icon-chip gray"><i class="fas fa-folder-open"></i></span>
                <span class="sidebar-link-label">Documents</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </a>
        </div>

        <?php endif; ?>

        <!-- ===== APP (PWA install) ===== -->
        <div class="sidebar-nav-card" style="margin-top: 4px;">
            <button class="sidebar-link w-100 text-start"
                    id="pwa-install-btn" style="display:none">
                <span class="sidebar-icon-chip primary"><i class="fas fa-download"></i></span>
                <span class="sidebar-link-label">Install App</span>
                <i class="fas fa-chevron-right sidebar-link-chevron"></i>
            </button>
        </div>

    </nav>
</aside>
<!-- ===== END SIDEBAR ===== -->

<!-- Content area -->
<div class="content" id="main-content">

<script>
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
