<?php
/**
 * Members — List / search / filter (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Member.php';

requireAdmin();

$pageTitle = 'Members';
$member    = new Member();

// Inputs
$search  = sanitize($_GET['search'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['active','inactive','suspended']) ? $_GET['status'] : '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Data
$members    = $member->getAll($page, $perPage, $search, $status);
$total      = $member->countAll($search, $status);
$pagination = paginate($total, $perPage, $page);
$stats      = $member->getStats();
$isSuperAdmin = isSuperAdmin();

function buildMemberQS(): string {
    $parts = [];
    if (!empty($_GET['search'])) $parts[] = 'search=' . urlencode($_GET['search']);
    if (!empty($_GET['status'])) $parts[] = 'status=' . urlencode($_GET['status']);
    return !empty($parts) ? '&' . implode('&', $parts) : '';
}

function memberAvatarGradient(string $status): string {
    return match($status) {
        'active'    => 'linear-gradient(135deg,#30D158,#007b52)',
        'suspended' => 'linear-gradient(135deg,#FF9F0A,#c97800)',
        default     => 'linear-gradient(135deg,#FF453A,#c82333)',
    };
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
:root {
    --ios-red:#FF453A; --ios-orange:#FF9F0A; --ios-green:#30D158;
    --ios-teal:#64D2FF; --ios-blue:#0A84FF; --ios-purple:#BF5AF2; --ios-gray:#8E8E93;
}

/* Stats Grid */
.content .stats-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}
.content .stat-card {
    background: transparent; border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg); padding: var(--spacing-5);
    display: flex; align-items: center; gap: var(--spacing-4);
    transition: var(--theme-transition); text-decoration: none;
}
.content .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); border-color: var(--primary); }
.content .stat-card.active-filter { border-color: var(--primary); }
.content .stat-icon {
    width: 56px; height: 56px; border-radius: var(--border-radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: white; flex-shrink: 0;
}
.content .stat-success .stat-icon { background: var(--success); }
.content .stat-warning .stat-icon { background: var(--warning); }
.content .stat-danger  .stat-icon { background: var(--danger);  }
.content .stat-primary .stat-icon { background: var(--primary); }
.content .stat-info    .stat-icon { background: var(--info);    }
.content .stat-content { flex: 1; }
.content .stat-label  { font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--spacing-1); }
.content .stat-value  { font-size: 1.75rem; font-weight: var(--font-weight-bold); color: var(--text-primary); line-height: 1; margin-bottom: var(--spacing-2); }
.content .stat-detail { font-size: var(--font-size-xs); color: var(--text-secondary); }

/* iOS Section Card */
.ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: var(--spacing-4); }
.ios-section-header { display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-4); background: var(--bg-subtle); border-bottom: 1px solid var(--border-color); }
.ios-section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ios-section-icon.blue   { background: rgba(10,132,255,0.15); color: var(--ios-blue);   }
.ios-section-icon.primary{ background: rgba(34,197,94,0.15);  color: var(--ios-green);  }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0; }

/* Mobile 3-dot button */
.ios-options-btn {
    display: none; width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    align-items: center; justify-content: center; cursor: pointer;
    transition: background 0.2s, transform 0.15s; flex-shrink: 0;
}
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(0.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* Member rows */
.ios-user-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: var(--bg-primary); cursor: pointer; transition: background 0.15s; border-bottom: 1px solid var(--border-color); }
.ios-user-item:last-child { border-bottom: none; }
.ios-user-item:hover  { background: rgba(255,255,255,0.03); }
.ios-user-item:active { background: rgba(255,255,255,0.06); }

.ios-user-avatar { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 16px; flex-shrink: 0; }
.ios-user-content { flex: 1; min-width: 0; }
.ios-user-name     { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-user-username { font-size: 12px; color: var(--primary); margin: 0 0 2px; font-family: var(--font-family-mono); }
.ios-user-email    { font-size: 12px; color: var(--text-muted); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-user-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }

.ios-status-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; text-transform: capitalize; }
.ios-status-badge.active    { background: rgba(48,209,88,0.15);  color: var(--ios-green);  }
.ios-status-badge.inactive  { background: rgba(255,69,58,0.15);  color: var(--ios-red);    }
.ios-status-badge.suspended { background: rgba(255,159,10,0.15); color: var(--ios-orange); }

.ios-member-position { font-size: 11px; font-weight: 500; background: var(--bg-secondary); color: var(--text-secondary); padding: 2px 6px; border-radius: 4px; }
.ios-user-login { font-size: 11px; color: var(--text-muted); }

.ios-actions-btn { width: 32px; height: 32px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-actions-btn:hover  { background: var(--border-color); }
.ios-actions-btn:active { transform: scale(0.95); }
.ios-actions-btn i { color: var(--text-primary); font-size: 14px; }

/* Filter Pills */
.ios-filter-pills { display: flex; gap: 8px; padding: 12px 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; border-bottom: 1px solid var(--border-color); }
.ios-filter-pill { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: all 0.2s; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); }
.ios-filter-pill:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-filter-pill .count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.2); }
.ios-filter-pill:not(.active) .count { background: var(--border-color); color: var(--text-muted); }

/* Search Box */
.ios-search-box { padding: 12px 16px; background: var(--bg-subtle); }
.ios-search-input-wrapper { position: relative; display: flex; align-items: center; }
.ios-search-icon { position: absolute; left: 12px; color: var(--text-muted); font-size: 14px; pointer-events: none; }
.ios-search-input { width: 100%; padding: 10px 36px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); color: var(--text-primary); font-size: 15px; transition: border-color 0.2s, box-shadow 0.2s; }
.ios-search-input:focus { outline: none; border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10,132,255,0.1); }
.ios-search-input::placeholder { color: var(--text-muted); }
.ios-search-clear { position: absolute; right: 10px; width: 20px; height: 20px; border-radius: 50%; background: var(--border-color); border: none; display: none; align-items: center; justify-content: center; cursor: pointer; color: var(--text-secondary); font-size: 10px; }
.ios-search-clear.visible { display: flex; }

/* Empty State */
.ios-empty-state { text-align: center; padding: 48px 24px; }
.ios-empty-icon  { font-size: 56px; color: var(--text-secondary); margin-bottom: 16px; opacity: 0.5; }
.ios-empty-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px; }
.ios-empty-description { font-size: 14px; color: var(--text-secondary); margin: 0 0 20px; line-height: 1.5; }

/* iOS Pagination */
.ios-pagination { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 16px; border-top: 1px solid var(--border-color); }
.ios-page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 14px; font-weight: 500; text-decoration: none; color: var(--text-secondary); background: var(--bg-secondary); border: 1px solid var(--border-color); transition: all 0.2s; }
.ios-page-btn:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-page-btn.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-pagination-info { font-size: 12px; color: var(--text-muted); text-align: center; padding: 0 16px 12px; }

/* Backdrop */
.ios-menu-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }

/* Shared sheet base */
.ios-menu-modal, .ios-action-modal, .ios-confirm-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary);
    border-radius: 16px 16px 0 0; transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1);
    overflow: hidden;
}
.ios-menu-modal   { z-index: 9999; max-height: 85vh; display: flex; flex-direction: column; }
.ios-action-modal { z-index: 10000; max-height: 70vh; }
.ios-confirm-sheet{ z-index: 10001; max-height: 65vh; }
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
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background 0.15s; cursor: pointer; width: 100%; background: transparent; border-left: none; border-right: none; border-top: none; font-family: inherit; font-size: inherit; text-align: left; }
button.ios-menu-item { border: none; border-bottom: 1px solid var(--border-color); }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left    { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon    { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.primary{ background: rgba(34,197,94,0.15);  color: var(--ios-green);  }
.ios-menu-item-icon.orange { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,0.15); color: var(--ios-blue);   }
.ios-menu-item-icon.purple { background: rgba(191,90,242,0.15); color: var(--ios-purple); }
.ios-menu-item-icon.red    { background: rgba(255,69,58,0.15);  color: var(--ios-red);    }
.ios-menu-item-icon.teal   { background: rgba(100,210,255,0.15);color: var(--ios-teal);   }
.ios-menu-item-content { flex: 1; min-width: 0; }
.ios-menu-item-label { font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-menu-item-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }
.ios-menu-stat-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 15px; color: var(--text-primary); }
.ios-menu-stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); }
.ios-menu-stat-value.success { color: var(--ios-green);  }
.ios-menu-stat-value.warning { color: var(--ios-orange); }
.ios-menu-stat-value.danger  { color: var(--ios-red);    }

/* Action sheet */
.ios-action-modal-header   { padding: 16px; border-bottom: 1px solid var(--border-color); text-align: center; }
.ios-action-modal-title    { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px; }
.ios-action-modal-subtitle { font-size: 13px; color: var(--text-secondary); margin: 0; }
.ios-action-modal-body     { padding: 8px; overflow-y: auto; }
.ios-action-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px; text-decoration: none; color: var(--text-primary); transition: background 0.15s; cursor: pointer; border: none; background: none; width: 100%; text-align: left; font-family: inherit; font-size: inherit; }
.ios-action-item:active  { background: var(--bg-secondary); }
.ios-action-item i       { width: 24px; font-size: 18px; }
.ios-action-item.danger  { color: var(--ios-red);    }
.ios-action-item.primary { color: var(--ios-blue);   }
.ios-action-item.warning { color: var(--ios-orange); }
.ios-action-item.success { color: var(--ios-green);  }
.ios-action-cancel { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; background: var(--bg-secondary); border: none; border-radius: 12px; font-size: 17px; font-weight: 600; color: var(--ios-blue); text-align: center; cursor: pointer; transition: background 0.15s; font-family: inherit; }
.ios-action-cancel:active { background: var(--border-color); }

/* Confirm sheet */
.ios-confirm-body { padding: 20px 16px 8px; overflow-y: auto; }
.ios-confirm-member-card { background: var(--bg-secondary); border-radius: 12px; padding: 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px; }
.ios-confirm-avatar { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 16px; flex-shrink: 0; }
.ios-confirm-info { flex: 1; }
.ios-confirm-name { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px; }
.ios-confirm-desc { font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5; }
.ios-form-btn { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; border: none; border-radius: 12px; font-size: 17px; font-weight: 600; text-align: center; cursor: pointer; transition: opacity 0.2s; font-family: inherit; color: white; }
.ios-form-btn:active     { opacity: 0.8; }
.ios-form-btn.warning    { background: var(--ios-orange); }
.ios-form-btn.success    { background: var(--ios-green);  }
.ios-form-btn.danger     { background: var(--ios-red);    }
.ios-form-btn.secondary  { background: var(--ios-gray);   }

/* Responsive */
@media (max-width: 992px) { .ios-options-btn { display: flex; } }
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .content .stats-overview-grid { display: flex !important; flex-wrap: nowrap !important; overflow-x: auto !important; gap: 0.75rem !important; padding-bottom: 0.5rem !important; -webkit-overflow-scrolling: touch; }
    .content .stat-card  { flex: 0 0 auto !important; min-width: 155px !important; padding: var(--spacing-4); }
    .content .stat-icon  { width: 40px !important; height: 40px !important; font-size: var(--font-size-lg); }
    .content .stat-value { font-size: 1.5rem; }
    .ios-section-card    { border-radius: 12px; }
    .ios-section-header  { padding: 14px; }
    .ios-section-icon    { width: 36px; height: 36px; font-size: 16px; }
    .ios-section-title h5 { font-size: 15px; }
    .ios-user-item       { padding: 12px 14px; }
    .ios-user-avatar     { width: 38px; height: 38px; font-size: 14px; }
    .ios-user-name       { font-size: 14px; }
    .ios-status-badge    { font-size: 10px; padding: 2px 6px; }
}
@media (max-width: 480px) {
    .ios-user-email       { display: none; }
    .ios-member-position  { display: none; }
}
</style>

<!-- Content Header (hidden on mobile) -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="content-title"><i class="fas fa-users me-2"></i>Members</h1>
            <nav class="content-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a></li>
                    <li class="breadcrumb-item active">Members</li>
                </ol>
            </nav>
        </div>
        <div class="content-actions d-flex gap-2">
            <a href="<?php echo BASE_URL; ?>members/export.php<?php echo $status ? "?status=$status" : ''; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-export"></i> Export
            </a>
            <a href="<?php echo BASE_URL; ?>members/create.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add Member
            </a>
        </div>
    </div>
</div>

<!-- Statistics Overview -->
<div class="stats-overview-grid">
    <a href="<?php echo BASE_URL; ?>members/" class="stat-card stat-primary text-decoration-none <?php echo !$status ? 'active-filter' : ''; ?>">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Members</div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-detail">All registered</div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>members/?status=active" class="stat-card stat-success text-decoration-none <?php echo $status === 'active' ? 'active-filter' : ''; ?>">
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        <div class="stat-content">
            <div class="stat-label">Active</div>
            <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
            <div class="stat-detail"><?php echo $stats['total'] > 0 ? round(($stats['active']/$stats['total'])*100) : 0; ?>% of total</div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>members/?status=inactive" class="stat-card stat-danger text-decoration-none <?php echo $status === 'inactive' ? 'active-filter' : ''; ?>">
        <div class="stat-icon"><i class="fas fa-user-times"></i></div>
        <div class="stat-content">
            <div class="stat-label">Inactive</div>
            <div class="stat-value"><?php echo number_format($stats['inactive']); ?></div>
            <div class="stat-detail">Require attention</div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>members/?status=suspended" class="stat-card stat-warning text-decoration-none <?php echo $status === 'suspended' ? 'active-filter' : ''; ?>">
        <div class="stat-icon"><i class="fas fa-user-lock"></i></div>
        <div class="stat-content">
            <div class="stat-label">Suspended</div>
            <div class="stat-value"><?php echo number_format($stats['suspended']); ?></div>
            <div class="stat-detail">Access restricted</div>
        </div>
    </a>
    <div class="stat-card stat-info">
        <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
        <div class="stat-content">
            <div class="stat-label">New This Month</div>
            <div class="stat-value"><?php echo number_format($stats['new_this_month']); ?></div>
            <div class="stat-detail">Recent joins</div>
        </div>
    </div>
</div>

<!-- Members Section Card -->
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-icon blue"><i class="fas fa-users"></i></div>
        <div class="ios-section-title">
            <h5><?php echo $status ? ucfirst($status) . ' Members' : 'All Members'; ?></h5>
            <p>
                <?php if ($search): ?>
                    Results for "<?php echo e($search); ?>" — <?php echo number_format($total); ?> found
                <?php else: ?>
                    <?php echo number_format($total); ?> member<?php echo $total != 1 ? 's' : ''; ?>
                <?php endif; ?>
            </p>
        </div>
        <button class="ios-options-btn" onclick="openPageMenu()" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <!-- Search Box -->
    <div class="ios-search-box">
        <form method="GET" action="" id="searchForm">
            <?php if ($status): ?>
            <input type="hidden" name="status" value="<?php echo e($status); ?>">
            <?php endif; ?>
            <div class="ios-search-input-wrapper">
                <i class="fas fa-search ios-search-icon"></i>
                <input type="text" name="search" id="memberSearch" class="ios-search-input"
                       placeholder="Search name, email, member ID…"
                       value="<?php echo e($search); ?>" autocomplete="off">
                <button type="button" class="ios-search-clear" id="clearSearch">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </form>
    </div>

    <!-- Status Filter Pills -->
    <div class="ios-filter-pills">
        <a href="<?php echo BASE_URL; ?>members/<?php echo $search ? '?search=' . urlencode($search) : ''; ?>"
           class="ios-filter-pill <?php echo !$status ? 'active' : ''; ?>">
            All <span class="count"><?php echo number_format($stats['total']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>members/?status=active<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
           class="ios-filter-pill <?php echo $status === 'active' ? 'active' : ''; ?>">
            Active <span class="count"><?php echo number_format($stats['active']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>members/?status=inactive<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
           class="ios-filter-pill <?php echo $status === 'inactive' ? 'active' : ''; ?>">
            Inactive <span class="count"><?php echo number_format($stats['inactive']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>members/?status=suspended<?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
           class="ios-filter-pill <?php echo $status === 'suspended' ? 'active' : ''; ?>">
            Suspended <span class="count"><?php echo number_format($stats['suspended']); ?></span>
        </a>
    </div>

    <?php if (empty($members)): ?>
    <!-- Empty State -->
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-users"></i></div>
        <h3 class="ios-empty-title">No members found</h3>
        <p class="ios-empty-description">
            <?php if ($search || $status): ?>
                No members match your current filters. Try adjusting your search.
            <?php else: ?>
                No members have been added yet.
            <?php endif; ?>
        </p>
        <?php if ($search || $status): ?>
        <a href="<?php echo BASE_URL; ?>members/" class="btn btn-secondary btn-sm">
            <i class="fas fa-times me-1"></i> Clear Filters
        </a>
        <?php else: ?>
        <a href="<?php echo BASE_URL; ?>members/create.php" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus me-1"></i> Add First Member
        </a>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- Members List -->
    <div class="ios-section-body" id="membersList">
        <?php foreach ($members as $m):
            $gradient = memberAvatarGradient($m['status']);
        ?>
        <div class="ios-user-item member-row"
             data-id="<?php echo $m['id']; ?>"
             data-name="<?php echo e($m['full_name']); ?>"
             data-status="<?php echo e($m['status']); ?>"
             data-email="<?php echo e($m['email']); ?>"
             data-member-id="<?php echo e($m['member_id']); ?>"
             data-position="<?php echo e($m['position'] ?: ''); ?>"
             data-gradient="<?php echo $gradient; ?>"
             onclick="openActionSheet(this)">
            <div class="ios-user-avatar" style="background:<?php echo $gradient; ?>">
                <?php echo e(getInitials($m['full_name'])); ?>
            </div>
            <div class="ios-user-content">
                <p class="ios-user-name"><?php echo e($m['full_name']); ?></p>
                <p class="ios-user-username"><?php echo e($m['member_id']); ?></p>
                <p class="ios-user-email"><?php echo e($m['email']); ?></p>
            </div>
            <div class="ios-user-meta">
                <span class="ios-status-badge <?php echo e($m['status']); ?>"><?php echo ucfirst($m['status']); ?></span>
                <?php if ($m['position']): ?>
                <span class="ios-member-position"><?php echo e($m['position']); ?></span>
                <?php endif; ?>
                <span class="ios-user-login">
                    <?php echo $m['joined_at'] ? formatDate($m['joined_at'], 'd M Y') : '—'; ?>
                </span>
            </div>
            <button class="ios-actions-btn" onclick="event.stopPropagation(); openActionSheet(this.closest('.ios-user-item'))" aria-label="Actions">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- No Search Results -->
    <div id="noResults" class="ios-empty-state" style="display:none">
        <div class="ios-empty-icon"><i class="fas fa-search"></i></div>
        <h3 class="ios-empty-title">No results</h3>
        <p class="ios-empty-description">No members on this page match your search.</p>
    </div>
    <?php endif; ?>

    <!-- iOS Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="ios-pagination-info">
        Showing <?php echo number_format(($page - 1) * $perPage + 1); ?>–<?php echo number_format(min($page * $perPage, $total)); ?>
        of <?php echo number_format($total); ?> members
    </div>
    <div class="ios-pagination">
        <?php if ($pagination['has_prev']): ?>
        <a href="?page=<?php echo $page - 1; ?><?php echo buildMemberQS(); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($p = max(1, $page - 2); $p <= min($pagination['total_pages'], $page + 2); $p++): ?>
        <a href="?page=<?php echo $p; ?><?php echo buildMemberQS(); ?>" class="ios-page-btn <?php echo $p === $page ? 'active' : ''; ?>">
            <?php echo $p; ?>
        </a>
        <?php endfor; ?>
        <?php if ($pagination['has_next']): ?>
        <a href="?page=<?php echo $page + 1; ?><?php echo buildMemberQS(); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div><!-- /ios-section-card -->

<!-- ===== PAGE MENU SHEET (mobile 3-dot) ===== -->
<div class="ios-menu-backdrop" id="pageMenuBackdrop" onclick="closePageMenu()"></div>
<div class="ios-menu-modal" id="pageMenuSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Members</h3>
        <button class="ios-menu-close" onclick="closePageMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>members/create.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-user-plus"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Add Member</span>
                            <span class="ios-menu-item-desc">Register a new member</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>members/export.php<?php echo $status ? "?status=$status" : ''; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon teal"><i class="fas fa-file-export"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Export Members</span>
                            <span class="ios-menu-item-desc">Download as spreadsheet</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>payments/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Payments</span>
                            <span class="ios-menu-item-desc">View all member payments</span>
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
            <div class="ios-menu-section-title">Statistics</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Members</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active</span>
                    <span class="ios-menu-stat-value success"><?php echo number_format($stats['active']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Suspended</span>
                    <span class="ios-menu-stat-value warning"><?php echo number_format($stats['suspended']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Inactive</span>
                    <span class="ios-menu-stat-value danger"><?php echo number_format($stats['inactive']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">New This Month</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['new_this_month']); ?></span>
                </div>
            </div>
        </div>
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter by Status</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>members/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">All Members (<?php echo number_format($stats['total']); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>members/?status=active" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-user-check"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">Active (<?php echo number_format($stats['active']); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>members/?status=suspended" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-user-lock"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">Suspended (<?php echo number_format($stats['suspended']); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>members/?status=inactive" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon red"><i class="fas fa-user-times"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">Inactive (<?php echo number_format($stats['inactive']); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ===== PER-ITEM ACTION SHEET ===== -->
<div class="ios-menu-backdrop" id="actionSheetBackdrop" onclick="closeActionSheet()"></div>
<div class="ios-action-modal" id="actionSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-action-modal-header">
        <p class="ios-action-modal-title" id="actionSheetName">Member</p>
        <p class="ios-action-modal-subtitle" id="actionSheetSub">Choose an action</p>
    </div>
    <div class="ios-action-modal-body" id="actionSheetBody"></div>
    <button class="ios-action-cancel" onclick="closeActionSheet()">Cancel</button>
</div>

<!-- ===== CONFIRM ACTION SHEET ===== -->
<div class="ios-menu-backdrop" id="confirmSheetBackdrop" onclick="closeConfirmSheet()"></div>
<div class="ios-confirm-sheet" id="confirmSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title" id="confirmSheetTitle">Confirm Action</h3>
        <button class="ios-menu-close" onclick="closeConfirmSheet()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-confirm-body">
        <div class="ios-confirm-member-card">
            <div class="ios-confirm-avatar" id="confirmAvatar">AB</div>
            <div class="ios-confirm-info">
                <p class="ios-confirm-name" id="confirmMemberName">Member Name</p>
                <p class="ios-confirm-desc" id="confirmMemberDesc">Description</p>
            </div>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>members/actions.php" id="confirmForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" id="confirmAction">
            <input type="hidden" name="id"     id="confirmId">
            <button type="submit" class="ios-form-btn" id="confirmBtn">Confirm</button>
        </form>
    </div>
    <button class="ios-action-cancel" onclick="closeConfirmSheet()">Cancel</button>
</div>

<script>
(function () {
    var isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;
    var baseUrl      = '<?php echo BASE_URL; ?>';
    var currentMember = null;

    // ─── Swipe-to-close helper ───────────────────────────────────────
    function addSwipeClose(el, closeFn) {
        var startY = 0, curY = 0;
        el.addEventListener('touchstart', function(e){ startY = e.touches[0].clientY; }, { passive: true });
        el.addEventListener('touchmove', function(e){
            curY = e.touches[0].clientY;
            var diff = curY - startY;
            if (diff > 0) el.style.transform = 'translateY(' + diff + 'px)';
        }, { passive: true });
        el.addEventListener('touchend', function(){
            var diff = curY - startY;
            el.style.transform = '';
            if (diff > 100) closeFn();
            startY = curY = 0;
        });
    }

    // ─── Page Menu ───────────────────────────────────────────────────
    var pageMenuBackdrop = document.getElementById('pageMenuBackdrop');
    var pageMenuSheet    = document.getElementById('pageMenuSheet');

    window.openPageMenu = function() {
        pageMenuBackdrop.classList.add('active');
        pageMenuSheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    window.closePageMenu = function() {
        pageMenuBackdrop.classList.remove('active');
        pageMenuSheet.classList.remove('active');
        document.body.style.overflow = '';
    };
    addSwipeClose(pageMenuSheet, closePageMenu);

    // ─── Action Sheet ────────────────────────────────────────────────
    var actionBackdrop = document.getElementById('actionSheetBackdrop');
    var actionSheet    = document.getElementById('actionSheet');
    var actionBody     = document.getElementById('actionSheetBody');

    window.openActionSheet = function(item) {
        var d = item.dataset;
        var initials = (d.name || '?').split(' ').map(function(n){ return n[0] || ''; }).join('').toUpperCase().slice(0, 2);
        currentMember = {
            id: d.id, name: d.name, status: d.status,
            email: d.email, memberId: d.memberId,
            gradient: d.gradient, initials: initials
        };

        document.getElementById('actionSheetName').textContent = currentMember.name;
        document.getElementById('actionSheetSub').textContent  = currentMember.memberId || currentMember.email || 'Member';

        var html = '';
        // View
        html += '<a href="' + baseUrl + 'members/view.php?id=' + d.id + '" class="ios-action-item">'
              + '<i class="fas fa-eye"></i><span>View Profile</span></a>';
        // Edit
        html += '<a href="' + baseUrl + 'members/edit.php?id=' + d.id + '" class="ios-action-item primary">'
              + '<i class="fas fa-edit"></i><span>Edit Member</span></a>';

        // Status-based actions
        if (currentMember.status === 'active') {
            html += '<button class="ios-action-item warning" onclick="openConfirm(\'suspend\')">'
                  + '<i class="fas fa-user-lock"></i><span>Suspend</span></button>';
            html += '<button class="ios-action-item" style="color:var(--ios-gray)" onclick="openConfirm(\'deactivate\')">'
                  + '<i class="fas fa-user-times"></i><span>Deactivate</span></button>';
        } else if (currentMember.status === 'suspended') {
            html += '<button class="ios-action-item success" onclick="openConfirm(\'activate\')">'
                  + '<i class="fas fa-user-check"></i><span>Activate</span></button>';
            html += '<button class="ios-action-item" style="color:var(--ios-gray)" onclick="openConfirm(\'deactivate\')">'
                  + '<i class="fas fa-user-times"></i><span>Deactivate</span></button>';
        } else if (currentMember.status === 'inactive') {
            html += '<button class="ios-action-item success" onclick="openConfirm(\'activate\')">'
                  + '<i class="fas fa-user-check"></i><span>Activate</span></button>';
        }

        // Delete — super admin only
        if (isSuperAdmin) {
            html += '<button class="ios-action-item danger" onclick="openConfirm(\'delete\')">'
                  + '<i class="fas fa-trash"></i><span>Delete Member</span></button>';
        }

        actionBody.innerHTML = html;
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

    // ─── Confirm Sheet ───────────────────────────────────────────────
    var confirmBackdrop = document.getElementById('confirmSheetBackdrop');
    var confirmSheet    = document.getElementById('confirmSheet');

    var confirmCfg = {
        suspend:    { title: 'Suspend Member',    desc: 'will lose access until reactivated.',         btnClass: 'warning',   btnText: 'Suspend'            },
        activate:   { title: 'Activate Member',   desc: 'will regain full access to the club.',        btnClass: 'success',   btnText: 'Activate'           },
        deactivate: { title: 'Deactivate Member', desc: 'will be set to inactive status.',             btnClass: 'secondary', btnText: 'Deactivate'         },
        delete:     { title: 'Delete Member',     desc: 'and all their data will be permanently deleted. This cannot be undone.', btnClass: 'danger', btnText: 'Delete Permanently' }
    };

    window.openConfirm = function(action) {
        if (!currentMember) return;
        var cfg = confirmCfg[action];
        if (!cfg) return;

        closeActionSheet();

        document.getElementById('confirmSheetTitle').textContent  = cfg.title;
        document.getElementById('confirmMemberName').textContent  = currentMember.name;
        document.getElementById('confirmMemberDesc').textContent  = currentMember.name + ' ' + cfg.desc;
        document.getElementById('confirmAvatar').textContent      = currentMember.initials;
        document.getElementById('confirmAvatar').style.background = currentMember.gradient;
        document.getElementById('confirmAction').value            = action;
        document.getElementById('confirmId').value                = currentMember.id;

        var btn = document.getElementById('confirmBtn');
        btn.className   = 'ios-form-btn ' + cfg.btnClass;
        btn.textContent = cfg.btnText;

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

    // ─── Client-side search (filters current page rows) ──────────────
    var searchInput = document.getElementById('memberSearch');
    var clearBtn    = document.getElementById('clearSearch');
    var membersList = document.getElementById('membersList');
    var noResults   = document.getElementById('noResults');

    // Show clear button if search is pre-filled from GET
    if (searchInput && searchInput.value && clearBtn) {
        clearBtn.classList.add('visible');
    }

    if (searchInput && membersList) {
        var searchTimeout;
        searchInput.addEventListener('input', function() {
            var term = this.value.trim();
            if (clearBtn) clearBtn.classList.toggle('visible', term.length > 0);
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() { filterMembers(term); }, 300);
        });
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                this.classList.remove('visible');
                filterMembers('');
                searchInput.focus();
            });
        }
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = '';
                if (clearBtn) clearBtn.classList.remove('visible');
                filterMembers('');
            }
        });
    }

    function filterMembers(term) {
        if (!membersList) return;
        var rows    = membersList.querySelectorAll('.member-row');
        var visible = 0;
        var t = term.toLowerCase();
        rows.forEach(function(row) {
            if (!t) { row.style.display = ''; visible++; return; }
            var name  = (row.querySelector('.ios-user-name')?.textContent     || '').toLowerCase();
            var code  = (row.querySelector('.ios-user-username')?.textContent || '').toLowerCase();
            var email = (row.querySelector('.ios-user-email')?.textContent    || '').toLowerCase();
            var match = name.includes(t) || code.includes(t) || email.includes(t);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        if (noResults) {
            var empty = (visible === 0 && rows.length > 0);
            membersList.style.display = empty ? 'none' : '';
            noResults.style.display   = empty ? 'block' : 'none';
        }
    }

}());
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
