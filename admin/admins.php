<?php
/**
 * Admin Accounts — Super Admin only (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireSuperAdmin();

$pageTitle = 'Admin Accounts';
$db        = Database::getInstance();

$admins = $db->fetchAll(
    "SELECT id, full_name, email, role, status, created_at, last_login_at
     FROM users WHERE role IN ('admin','super_admin') ORDER BY role DESC, full_name ASC"
);

$totalAdmins   = count($admins);
$superAdmins   = count(array_filter($admins, fn($a) => $a['role'] === 'super_admin'));
$regularAdmins = $totalAdmins - $superAdmins;
$activeAdmins  = count(array_filter($admins, fn($a) => $a['status'] === 'active'));

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
:root {
    --ios-red:#FF453A; --ios-orange:#FF9F0A; --ios-green:#30D158;
    --ios-blue:#0A84FF; --ios-purple:#BF5AF2; --ios-gray:#8E8E93;
}

/* Stats Grid */
.content .stats-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}
.content .stat-card {
    background: transparent; border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg); padding: var(--spacing-5);
    display: flex; align-items: center; gap: var(--spacing-4);
    transition: var(--theme-transition);
}
.content .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); border-color: var(--primary); }
.content .stat-icon { width: 48px; height: 48px; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; color: white; flex-shrink: 0; }
.content .stat-primary .stat-icon { background: var(--primary); }
.content .stat-info    .stat-icon { background: var(--ios-blue); }
.content .stat-success .stat-icon { background: var(--success); }
.content .stat-warning .stat-icon { background: var(--warning); }
.content .stat-content { flex: 1; }
.content .stat-label { font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--spacing-1); }
.content .stat-value { font-size: 1.6rem; font-weight: var(--font-weight-bold); color: var(--text-primary); line-height: 1; }

/* iOS Section Card */
.ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: var(--spacing-4); }
.ios-section-header { display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-4); background: var(--bg-subtle); border-bottom: 1px solid var(--border-color); }
.ios-section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ios-section-icon.blue   { background: rgba(10,132,255,0.15);  color: var(--ios-blue);   }
.ios-section-icon.purple { background: rgba(191,90,242,0.15);  color: var(--ios-purple); }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

/* Mobile 3-dot */
.ios-options-btn { display: none; width: 36px; height: 36px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(0.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 15px; }

/* Admin Item */
.ios-admin-item { display: flex; align-items: center; gap: 13px; padding: 14px 16px; background: var(--bg-primary); border-bottom: 1px solid var(--border-color); transition: background 0.15s; }
.ios-admin-item:last-child { border-bottom: none; }
.ios-admin-item:hover { background: rgba(255,255,255,0.02); }

.admin-avatar {
    width: 44px; height: 44px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, var(--primary), var(--primary-700, #007a55));
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; letter-spacing: 0.3px;
}
.admin-avatar.super { background: linear-gradient(135deg, var(--ios-blue), #0060cc); }

.admin-content { flex: 1; min-width: 0; }
.admin-name  { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.admin-email { font-size: 12px; color: var(--text-muted); margin: 0 0 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.admin-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 4px; }

.admin-role-chip {
    font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 8px;
    white-space: nowrap;
}
.admin-role-chip.super  { background: rgba(10,132,255,0.12); color: var(--ios-blue); }
.admin-role-chip.admin  { background: rgba(48,209,88,0.12);  color: var(--ios-green); }

.admin-status-chip {
    font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 8px;
    white-space: nowrap;
}
.admin-status-chip.active     { background: rgba(48,209,88,0.12);   color: var(--ios-green);  }
.admin-status-chip.inactive   { background: rgba(142,142,147,0.12); color: var(--ios-gray);   }
.admin-status-chip.suspended  { background: rgba(255,69,58,0.12);   color: var(--ios-red);    }

.admin-meta { font-size: 11px; color: var(--text-muted); }
.admin-meta i { font-size: 9px; margin-right: 3px; }

.ios-actions-btn { width: 32px; height: 32px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-actions-btn:hover  { background: var(--border-color); }
.ios-actions-btn:active { transform: scale(0.95); }
.ios-actions-btn i { color: var(--text-primary); font-size: 13px; }
.admin-placeholder { width: 32px; flex-shrink: 0; }

/* Empty */
.ios-empty-state { text-align: center; padding: 48px 24px; }
.ios-empty-icon  { font-size: 56px; opacity: 0.35; margin-bottom: 16px; }
.ios-empty-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px; }

/* Backdrop + Sheets */
.ios-menu-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }

.ios-menu-modal, .ios-action-modal, .ios-confirm-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary);
    border-radius: 16px 16px 0 0; transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1); overflow: hidden;
}
.ios-menu-modal    { z-index: 9999;  max-height: 85vh; display: flex; flex-direction: column; }
.ios-action-modal  { z-index: 10000; max-height: 55vh; }
.ios-confirm-sheet { z-index: 10001; max-height: 55vh; }
.ios-menu-modal.active, .ios-action-modal.active, .ios-confirm-sheet.active { transform: translateY(0); }

.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ios-menu-title  { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close  { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; transition: background 0.2s; }
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-left: 4px; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background 0.15s; }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.primary { background: rgba(34,197,94,0.15);  color: var(--ios-green); }
.ios-menu-item-icon.blue    { background: rgba(10,132,255,0.15); color: var(--ios-blue);  }
.ios-menu-item-icon.purple  { background: rgba(191,90,242,0.15); color: var(--ios-purple);}
.ios-menu-item-content  { flex: 1; }
.ios-menu-item-label    { font-size: 15px; font-weight: 500; }
.ios-menu-item-desc     { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron  { color: var(--text-muted); font-size: 12px; }
.ios-menu-stat-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 15px; color: var(--text-primary); }
.ios-menu-stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); }

/* Action sheet */
.ios-action-modal-header   { padding: 16px; border-bottom: 1px solid var(--border-color); text-align: center; }
.ios-action-modal-title    { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px; }
.ios-action-modal-subtitle { font-size: 13px; color: var(--text-secondary); margin: 0; }
.ios-action-modal-body     { padding: 8px; }
.ios-action-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px; text-decoration: none; color: var(--text-primary); transition: background 0.15s; cursor: pointer; border: none; background: none; width: 100%; text-align: left; font-family: inherit; font-size: inherit; }
.ios-action-item:active { background: var(--bg-secondary); }
.ios-action-item i      { width: 24px; font-size: 18px; }
.ios-action-item.danger { color: var(--ios-red); }
.ios-action-cancel { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; background: var(--bg-secondary); border: none; border-radius: 12px; font-size: 17px; font-weight: 600; color: var(--ios-blue); text-align: center; cursor: pointer; transition: background 0.15s; font-family: inherit; }
.ios-action-cancel:active { background: var(--border-color); }

/* Confirm sheet */
.ios-confirm-body { padding: 20px 16px 8px; }
.ios-confirm-card { background: var(--bg-secondary); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.ios-confirm-card-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; }
.ios-confirm-card-desc  { font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5; }
.ios-form-btn { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; border: none; border-radius: 12px; font-size: 17px; font-weight: 600; text-align: center; cursor: pointer; transition: opacity 0.2s; font-family: inherit; color: white; }
.ios-form-btn:active { opacity: 0.8; }
.ios-form-btn.danger { background: var(--ios-red); }

/* Responsive */
@media (max-width: 992px) { .ios-options-btn { display: flex; } }
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .content .stats-overview-grid { display: flex !important; flex-wrap: nowrap !important; overflow-x: auto !important; gap: 0.75rem !important; padding-bottom: 0.5rem !important; -webkit-overflow-scrolling: touch; }
    .content .stat-card  { flex: 0 0 auto !important; min-width: 150px !important; padding: var(--spacing-4); }
    .content .stat-icon  { width: 40px !important; height: 40px !important; font-size: 1.1rem; }
    .content .stat-value { font-size: 1.4rem; }
    .admin-meta { display: none; }
}
</style>

<!-- Desktop Header -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="content-title"><i class="fas fa-user-shield me-2"></i>Admin Accounts</h1>
            <nav class="content-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a></li>
                    <li class="breadcrumb-item active">Admin Accounts</li>
                </ol>
            </nav>
        </div>
        <div class="content-actions">
            <a href="<?php echo BASE_URL; ?>admin/create-admin.php" class="btn btn-primary">
                <i class="fas fa-user-plus me-2"></i>Add Admin
            </a>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="stats-overview-grid">
    <div class="stat-card stat-primary">
        <div class="stat-icon"><i class="fas fa-users-cog"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Admins</div>
            <div class="stat-value"><?php echo $totalAdmins; ?></div>
        </div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-icon"><i class="fas fa-user-shield"></i></div>
        <div class="stat-content">
            <div class="stat-label">Super Admins</div>
            <div class="stat-value"><?php echo $superAdmins; ?></div>
        </div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div class="stat-content">
            <div class="stat-label">Regular Admins</div>
            <div class="stat-value"><?php echo $regularAdmins; ?></div>
        </div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-icon"><i class="fas fa-circle"></i></div>
        <div class="stat-content">
            <div class="stat-label">Active</div>
            <div class="stat-value"><?php echo $activeAdmins; ?></div>
        </div>
    </div>
</div>

<!-- Admin List -->
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-icon blue"><i class="fas fa-user-shield"></i></div>
        <div class="ios-section-title">
            <h5>Admin Accounts</h5>
            <p><?php echo $totalAdmins; ?> administrator<?php echo $totalAdmins != 1 ? 's' : ''; ?> on the platform</p>
        </div>
        <button class="ios-options-btn" onclick="openPageMenu()" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <?php if (empty($admins)): ?>
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-users-cog"></i></div>
        <h3 class="ios-empty-title">No admins found</h3>
    </div>
    <?php else: ?>
    <?php foreach ($admins as $a):
        $isSelf      = $a['id'] === (int)$_SESSION['user_id'];
        $isSuper     = $a['role'] === 'super_admin';
        $isRevokable = !$isSelf && !$isSuper;
        $roleLabel   = $isSuper ? 'Super Admin' : 'Admin';
        $statusLabel = ucfirst($a['status']);
        $lastLogin   = $a['last_login_at'] ? formatDate($a['last_login_at'], 'd M Y, g:i A') : 'Never';
    ?>
    <div class="ios-admin-item"
         data-id="<?php echo $a['id']; ?>"
         data-name="<?php echo e($a['full_name']); ?>"
         data-email="<?php echo e($a['email']); ?>"
         data-role="<?php echo e($roleLabel); ?>"
         data-revokable="<?php echo $isRevokable ? '1' : '0'; ?>">

        <div class="admin-avatar <?php echo $isSuper ? 'super' : ''; ?>">
            <?php echo e(getInitials($a['full_name'])); ?>
        </div>

        <div class="admin-content">
            <p class="admin-name">
                <?php echo e($a['full_name']); ?>
                <?php if ($isSelf): ?><span style="font-size:11px;color:var(--ios-blue);font-weight:500;margin-left:5px">(you)</span><?php endif; ?>
            </p>
            <p class="admin-email"><?php echo e($a['email']); ?></p>
            <div class="admin-chips">
                <span class="admin-role-chip <?php echo $isSuper ? 'super' : 'admin'; ?>">
                    <i class="fas <?php echo $isSuper ? 'fa-user-shield' : 'fa-user-cog'; ?>" style="font-size:9px;margin-right:3px"></i>
                    <?php echo $roleLabel; ?>
                </span>
                <span class="admin-status-chip <?php echo $a['status']; ?>"><?php echo $statusLabel; ?></span>
            </div>
            <p class="admin-meta">
                <i class="fas fa-clock"></i>
                Last login: <?php echo $lastLogin; ?>
            </p>
        </div>

        <?php if ($isRevokable): ?>
        <button class="ios-actions-btn"
                onclick="openActionSheet(this.closest('.ios-admin-item'))"
                aria-label="Actions">
            <i class="fas fa-ellipsis-v"></i>
        </button>
        <?php else: ?>
        <div class="admin-placeholder"></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ===== PAGE MENU SHEET ===== -->
<div class="ios-menu-backdrop" id="pageMenuBackdrop" onclick="closePageMenu()"></div>
<div class="ios-menu-modal" id="pageMenuSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Admin Accounts</h3>
        <button class="ios-menu-close" onclick="closePageMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>admin/create-admin.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-user-plus"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Add Admin</span>
                            <span class="ios-menu-item-desc">Grant admin access to a member</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-home"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Dashboard</span>
                            <span class="ios-menu-item-desc">Return to dashboard</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Overview</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Admins</span>
                    <span class="ios-menu-stat-value"><?php echo $totalAdmins; ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Super Admins</span>
                    <span class="ios-menu-stat-value"><?php echo $superAdmins; ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Regular Admins</span>
                    <span class="ios-menu-stat-value"><?php echo $regularAdmins; ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active</span>
                    <span class="ios-menu-stat-value"><?php echo $activeAdmins; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== PER-ITEM ACTION SHEET ===== -->
<div class="ios-menu-backdrop" id="actionSheetBackdrop" onclick="closeActionSheet()"></div>
<div class="ios-action-modal" id="actionSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-action-modal-header">
        <p class="ios-action-modal-title" id="actionSheetTitle">Admin</p>
        <p class="ios-action-modal-subtitle" id="actionSheetSub">Choose an action</p>
    </div>
    <div class="ios-action-modal-body">
        <button class="ios-action-item danger" onclick="openConfirmSheet()">
            <i class="fas fa-user-minus"></i><span>Revoke Admin Access</span>
        </button>
    </div>
    <button class="ios-action-cancel" onclick="closeActionSheet()">Cancel</button>
</div>

<!-- ===== REVOKE CONFIRM SHEET ===== -->
<div class="ios-menu-backdrop" id="confirmSheetBackdrop" onclick="closeConfirmSheet()"></div>
<div class="ios-confirm-sheet" id="confirmSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Revoke Admin Access</h3>
        <button class="ios-menu-close" onclick="closeConfirmSheet()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-confirm-body">
        <div class="ios-confirm-card">
            <p class="ios-confirm-card-title" id="confirmAdminName">Admin Name</p>
            <p class="ios-confirm-card-desc">This will remove admin privileges. The user will be downgraded to a regular member and lose access to all admin features.</p>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>admin/admin-actions.php" id="revokeForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="revoke_admin">
            <input type="hidden" name="id" id="revokeId">
            <button type="submit" class="ios-form-btn danger">Revoke Access</button>
        </form>
    </div>
    <button class="ios-action-cancel" onclick="closeConfirmSheet()">Cancel</button>
</div>

<script>
(function () {
    var currentAdmin = null;

    function addSwipeClose(el, closeFn) {
        var startY = 0, curY = 0;
        el.addEventListener('touchstart', function(e) { startY = e.touches[0].clientY; }, { passive: true });
        el.addEventListener('touchmove', function(e) {
            curY = e.touches[0].clientY;
            var diff = curY - startY;
            if (diff > 0) el.style.transform = 'translateY(' + diff + 'px)';
        }, { passive: true });
        el.addEventListener('touchend', function() {
            var diff = curY - startY;
            el.style.transform = '';
            if (diff > 100) closeFn();
            startY = curY = 0;
        });
    }

    // Page Menu
    var pageMenuBackdrop = document.getElementById('pageMenuBackdrop');
    var pageMenuSheet    = document.getElementById('pageMenuSheet');
    window.openPageMenu  = function() { pageMenuBackdrop.classList.add('active'); pageMenuSheet.classList.add('active'); document.body.style.overflow = 'hidden'; };
    window.closePageMenu = function() { pageMenuBackdrop.classList.remove('active'); pageMenuSheet.classList.remove('active'); document.body.style.overflow = ''; };
    addSwipeClose(pageMenuSheet, closePageMenu);

    // Action Sheet
    var actionBackdrop = document.getElementById('actionSheetBackdrop');
    var actionSheet    = document.getElementById('actionSheet');
    window.openActionSheet = function(item) {
        var d = item.dataset;
        currentAdmin = { id: d.id, name: d.name, email: d.email, role: d.role };

        document.getElementById('actionSheetTitle').textContent = currentAdmin.name;
        document.getElementById('actionSheetSub').textContent   = currentAdmin.role + ' · ' + currentAdmin.email;

        actionBackdrop.classList.add('active');
        actionSheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    window.closeActionSheet = function() {
        actionBackdrop.classList.remove('active');
        actionSheet.classList.remove('active');
        document.body.style.overflow = '';
    };
    addSwipeClose(actionSheet, closeActionSheet);

    // Confirm Sheet
    var confirmBackdrop = document.getElementById('confirmSheetBackdrop');
    var confirmSheet    = document.getElementById('confirmSheet');
    window.openConfirmSheet = function() {
        if (!currentAdmin) return;
        document.getElementById('confirmAdminName').textContent = currentAdmin.name;
        document.getElementById('revokeId').value              = currentAdmin.id;

        closeActionSheet();
        setTimeout(function() {
            confirmBackdrop.classList.add('active');
            confirmSheet.classList.add('active');
            document.body.style.overflow = 'hidden';
        }, 320);
    };
    window.closeConfirmSheet = function() {
        confirmBackdrop.classList.remove('active');
        confirmSheet.classList.remove('active');
        document.body.style.overflow = '';
    };
    addSwipeClose(confirmSheet, closeConfirmSheet);
}());
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
