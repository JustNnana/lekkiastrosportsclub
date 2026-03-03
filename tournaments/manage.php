<?php
/**
 * Tournaments — Admin management list (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Tournament.php';

requireAdmin();

$pageTitle  = 'Manage Tournaments';
$tourObj    = new Tournament();

$status  = sanitize($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total = $tourObj->countAll($status);
$paged = paginate($total, $perPage, $page);
$items = $tourObj->getAll($page, $perPage, $status);
$stats = $tourObj->getStats();

$formatLabels = [
    'league'         => 'League',
    'knockout'       => 'Knockout',
    'group_knockout' => 'Group + KO',
];

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
.content .stat-icon {
    width: 56px; height: 56px; border-radius: var(--border-radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: white; flex-shrink: 0;
}
.content .stat-success   .stat-icon { background: var(--success); }
.content .stat-warning   .stat-icon { background: var(--warning); }
.content .stat-primary   .stat-icon { background: var(--primary); }
.content .stat-secondary .stat-icon { background: var(--text-muted); }
.content .stat-content { flex: 1; }
.content .stat-label  { font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--spacing-1); }
.content .stat-value  { font-size: 1.75rem; font-weight: var(--font-weight-bold); color: var(--text-primary); line-height: 1; margin-bottom: var(--spacing-2); }
.content .stat-detail { font-size: var(--font-size-xs); color: var(--text-secondary); }

/* iOS Section Card */
.ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: var(--spacing-4); }
.ios-section-header { display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-4); background: var(--bg-subtle); border-bottom: 1px solid var(--border-color); }
.ios-section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ios-section-icon.orange { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0; }

/* Mobile 3-dot */
.ios-options-btn { display: none; width: 36px; height: 36px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(0.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* Filter Pills */
.ios-filter-pills { display: flex; gap: 8px; padding: 12px 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; border-bottom: 1px solid var(--border-color); }
.ios-filter-pill { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: all 0.2s; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); }
.ios-filter-pill:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-filter-pill.pill-active.active    { background: var(--ios-green);  border-color: var(--ios-green);  }
.ios-filter-pill.pill-setup.active     { background: var(--ios-orange); border-color: var(--ios-orange); }
.ios-filter-pill.pill-completed.active { background: var(--ios-gray);   border-color: var(--ios-gray);   }
.ios-filter-pill .count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.2); }
.ios-filter-pill:not(.active) .count   { background: var(--border-color); color: var(--text-muted); }

/* Tournament Item */
.ios-tour-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: var(--bg-primary); cursor: pointer; transition: background 0.15s; border-bottom: 1px solid var(--border-color); }
.ios-tour-item:last-child { border-bottom: none; }
.ios-tour-item:hover  { background: rgba(255,255,255,0.03); }
.ios-tour-item:active { background: rgba(255,255,255,0.06); }

.ios-tour-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ios-tour-icon.active    { background: rgba(48,209,88,0.15);  color: var(--ios-green);  }
.ios-tour-icon.setup     { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-tour-icon.completed { background: rgba(142,142,147,0.15);color: var(--ios-gray);   }

.ios-tour-content { flex: 1; min-width: 0; }
.ios-tour-name    { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-tour-sub     { font-size: 12px; color: var(--text-muted); margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-tour-info    { font-size: 12px; color: var(--text-muted); margin: 0; }

.ios-tour-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; flex-shrink: 0; }
.ios-stat-chip { font-size: 12px; font-weight: 600; color: var(--ios-blue); background: rgba(10,132,255,0.1); padding: 3px 8px; border-radius: 10px; white-space: nowrap; }
.ios-status-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; text-transform: capitalize; }
.ios-status-badge.active    { background: rgba(48,209,88,0.15);  color: var(--ios-green);  }
.ios-status-badge.setup     { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-status-badge.completed { background: rgba(142,142,147,0.15);color: var(--ios-gray);   }

.ios-actions-btn { width: 32px; height: 32px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-actions-btn:hover  { background: var(--border-color); }
.ios-actions-btn:active { transform: scale(0.95); }
.ios-actions-btn i { color: var(--text-primary); font-size: 14px; }

/* Empty State */
.ios-empty-state { text-align: center; padding: 48px 24px; }
.ios-empty-icon  { font-size: 56px; color: var(--text-secondary); margin-bottom: 16px; opacity: 0.5; }
.ios-empty-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px; }
.ios-empty-description { font-size: 14px; color: var(--text-secondary); margin: 0 0 20px; line-height: 1.5; }

/* Pagination */
.ios-pagination { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 16px; border-top: 1px solid var(--border-color); }
.ios-page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 14px; font-weight: 500; text-decoration: none; color: var(--text-secondary); background: var(--bg-secondary); border: 1px solid var(--border-color); transition: all 0.2s; }
.ios-page-btn:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-page-btn.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-pagination-info { font-size: 12px; color: var(--text-muted); text-align: center; padding: 0 16px 12px; }

/* Backdrop */
.ios-menu-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }

/* Shared sheet styles */
.ios-menu-modal, .ios-action-modal, .ios-confirm-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary);
    border-radius: 16px 16px 0 0; transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1); overflow: hidden;
}
.ios-menu-modal    { z-index: 9999;  max-height: 85vh; display: flex; flex-direction: column; }
.ios-action-modal  { z-index: 10000; max-height: 70vh; }
.ios-confirm-sheet { z-index: 10001; max-height: 60vh; }
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
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background 0.15s; cursor: pointer; }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left   { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon   { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.primary{ background: rgba(34,197,94,0.15);  color: var(--ios-green);  }
.ios-menu-item-icon.orange { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,0.15); color: var(--ios-blue);   }
.ios-menu-item-icon.purple { background: rgba(191,90,242,0.15); color: var(--ios-purple); }
.ios-menu-item-icon.red    { background: rgba(255,69,58,0.15);  color: var(--ios-red);    }
.ios-menu-item-icon.teal   { background: rgba(100,210,255,0.15);color: var(--ios-teal);   }
.ios-menu-item-content  { flex: 1; min-width: 0; }
.ios-menu-item-label    { font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-menu-item-desc     { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron  { color: var(--text-muted); font-size: 12px; }
.ios-menu-stat-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 15px; color: var(--text-primary); }
.ios-menu-stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); }
.ios-menu-stat-value.success { color: var(--ios-green);  }
.ios-menu-stat-value.warning { color: var(--ios-orange); }

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
.ios-confirm-tour-card { background: var(--bg-secondary); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.ios-confirm-tour-name { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; }
.ios-confirm-tour-desc { font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5; }
.ios-form-btn { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; border: none; border-radius: 12px; font-size: 17px; font-weight: 600; text-align: center; cursor: pointer; transition: opacity 0.2s; font-family: inherit; color: white; }
.ios-form-btn:active  { opacity: 0.8; }
.ios-form-btn.success { background: var(--ios-green);  }
.ios-form-btn.warning { background: var(--ios-orange); }
.ios-form-btn.danger  { background: var(--ios-red);    }

/* Responsive */
@media (max-width: 992px) { .ios-options-btn { display: flex; } }
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .content .stats-overview-grid { display: flex !important; flex-wrap: nowrap !important; overflow-x: auto !important; gap: 0.75rem !important; padding-bottom: 0.5rem !important; -webkit-overflow-scrolling: touch; }
    .content .stat-card  { flex: 0 0 auto !important; min-width: 155px !important; padding: var(--spacing-4); }
    .content .stat-icon  { width: 40px !important; height: 40px !important; font-size: var(--font-size-lg); }
    .content .stat-value { font-size: 1.5rem; }
    .ios-tour-sub  { display: none; }
    .ios-tour-info { display: none; }
}
</style>

<!-- Content Header (desktop) -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="content-title"><i class="fas fa-trophy me-2"></i>Tournaments</h1>
            <nav class="content-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Tournaments</li>
                </ol>
            </nav>
        </div>
        <div class="content-actions">
            <a href="<?php echo BASE_URL; ?>tournaments/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-trophy me-2"></i>View Tournaments
            </a>
            <a href="<?php echo BASE_URL; ?>tournaments/form.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Tournament
            </a>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="stats-overview-grid">
    <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="stat-card stat-primary" style="text-decoration:none">
        <div class="stat-icon"><i class="fas fa-trophy"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-detail">All time</div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>tournaments/manage.php?status=active" class="stat-card stat-success" style="text-decoration:none">
        <div class="stat-icon"><i class="fas fa-play"></i></div>
        <div class="stat-content">
            <div class="stat-label">Active</div>
            <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
            <div class="stat-detail">In progress</div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>tournaments/manage.php?status=setup" class="stat-card stat-warning" style="text-decoration:none">
        <div class="stat-icon"><i class="fas fa-cog"></i></div>
        <div class="stat-content">
            <div class="stat-label">Setup</div>
            <div class="stat-value"><?php echo number_format($stats['setup']); ?></div>
            <div class="stat-detail">Being configured</div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>tournaments/manage.php?status=completed" class="stat-card stat-secondary" style="text-decoration:none">
        <div class="stat-icon"><i class="fas fa-flag-checkered"></i></div>
        <div class="stat-content">
            <div class="stat-label">Completed</div>
            <div class="stat-value"><?php echo number_format($stats['completed']); ?></div>
            <div class="stat-detail">Finished</div>
        </div>
    </a>
</div>

<!-- Tournaments Section Card -->
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-icon orange"><i class="fas fa-trophy"></i></div>
        <div class="ios-section-title">
            <h5><?php echo $status ? ucfirst($status) . ' Tournaments' : 'All Tournaments'; ?></h5>
            <p><?php echo number_format($total); ?> tournament<?php echo $total != 1 ? 's' : ''; ?></p>
        </div>
        <button class="ios-options-btn" onclick="openPageMenu()" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <!-- Filter Pills -->
    <div class="ios-filter-pills">
        <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="ios-filter-pill <?php echo !$status ? 'active' : ''; ?>">
            All <span class="count"><?php echo number_format($stats['total']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>tournaments/manage.php?status=active" class="ios-filter-pill pill-active <?php echo $status === 'active' ? 'active' : ''; ?>">
            Active <span class="count"><?php echo number_format($stats['active']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>tournaments/manage.php?status=setup" class="ios-filter-pill pill-setup <?php echo $status === 'setup' ? 'active' : ''; ?>">
            Setup <span class="count"><?php echo number_format($stats['setup']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>tournaments/manage.php?status=completed" class="ios-filter-pill pill-completed <?php echo $status === 'completed' ? 'active' : ''; ?>">
            Completed <span class="count"><?php echo number_format($stats['completed']); ?></span>
        </a>
    </div>

    <?php if (empty($items)): ?>
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-trophy"></i></div>
        <h3 class="ios-empty-title">No tournaments found</h3>
        <p class="ios-empty-description">
            <?php echo $status ? 'No ' . $status . ' tournaments at the moment.' : 'No tournaments have been created yet.'; ?>
        </p>
        <a href="<?php echo BASE_URL; ?>tournaments/form.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>Create First Tournament
        </a>
    </div>

    <?php else: ?>
    <div id="tournamentsList">
        <?php foreach ($items as $t):
            $st          = $t['status'];
            $iconCls     = $st; // active | setup | completed
            $iconFa      = match($st) {
                'active'    => 'fas fa-trophy',
                'setup'     => 'fas fa-cog',
                default     => 'fas fa-flag-checkered',
            };
            $statusLabel = match($st) { 'active' => 'Active', 'setup' => 'Setup', default => 'Done' };
            $fmt         = $formatLabels[$t['format']] ?? ucfirst(str_replace('_', ' ', $t['format']));
            $startDate   = $t['start_date'] ? formatDate($t['start_date'], 'd M Y') : null;
        ?>
        <div class="ios-tour-item"
             data-id="<?php echo $t['id']; ?>"
             data-name="<?php echo e($t['name']); ?>"
             data-status="<?php echo $st; ?>"
             data-groups="<?php echo (int)$t['group_count']; ?>"
             data-teams="<?php echo (int)$t['team_count']; ?>"
             data-fixtures="<?php echo (int)$t['fixture_count']; ?>"
             onclick="openActionSheet(this)">
            <div class="ios-tour-icon <?php echo $iconCls; ?>">
                <i class="<?php echo $iconFa; ?>"></i>
            </div>
            <div class="ios-tour-content">
                <p class="ios-tour-name"><?php echo e($t['name']); ?></p>
                <p class="ios-tour-sub">
                    <?php echo e($t['creator_name']); ?>&nbsp;·&nbsp;<?php echo e($fmt); ?>
                </p>
                <p class="ios-tour-info">
                    <i class="fas fa-shield-alt" style="font-size:10px;margin-right:3px"></i><?php echo $t['team_count']; ?> teams
                    &nbsp;·&nbsp;
                    <i class="fas fa-futbol" style="font-size:10px;margin-right:3px"></i><?php echo $t['fixture_count']; ?> fixtures
                    <?php if ($startDate): ?>&nbsp;·&nbsp;<i class="fas fa-calendar-alt" style="font-size:10px;margin-right:3px"></i><?php echo $startDate; ?><?php endif; ?>
                </p>
            </div>
            <div class="ios-tour-meta">
                <span class="ios-stat-chip">
                    <?php echo $t['group_count']; ?> group<?php echo $t['group_count'] != 1 ? 's' : ''; ?>
                </span>
                <span class="ios-status-badge <?php echo $iconCls; ?>"><?php echo $statusLabel; ?></span>
            </div>
            <button class="ios-actions-btn" onclick="event.stopPropagation(); openActionSheet(this.closest('.ios-tour-item'))" aria-label="Actions">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($paged['total_pages'] > 1): ?>
    <div class="ios-pagination-info">
        Showing <?php echo number_format(($page - 1) * $perPage + 1); ?>–<?php echo number_format(min($page * $perPage, $total)); ?>
        of <?php echo number_format($total); ?> tournaments
    </div>
    <div class="ios-pagination">
        <?php if ($paged['has_prev']): ?>
        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($paged['total_pages'], $page + 2); $i++): ?>
        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>" class="ios-page-btn <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        <?php if ($paged['has_next']): ?>
        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div><!-- /ios-section-card -->

<!-- ===== PAGE MENU SHEET ===== -->
<div class="ios-menu-backdrop" id="pageMenuBackdrop" onclick="closePageMenu()"></div>
<div class="ios-menu-modal" id="pageMenuSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Tournaments</h3>
        <button class="ios-menu-close" onclick="closePageMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>tournaments/form.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-plus"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">New Tournament</span>
                            <span class="ios-menu-item-desc">Create a new competition</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>tournaments/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-trophy"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">View Tournaments</span>
                            <span class="ios-menu-item-desc">See tournaments as members do</span>
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
                    <span class="ios-menu-stat-label">Total</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active</span>
                    <span class="ios-menu-stat-value success"><?php echo number_format($stats['active']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Setup</span>
                    <span class="ios-menu-stat-value warning"><?php echo number_format($stats['setup']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Completed</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['completed']); ?></span>
                </div>
            </div>
        </div>
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">All (<?php echo number_format($stats['total']); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php?status=active" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-play"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">Active (<?php echo number_format($stats['active']); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php?status=setup" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-cog"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">Setup (<?php echo number_format($stats['setup']); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php?status=completed" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon teal"><i class="fas fa-flag-checkered"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">Completed (<?php echo number_format($stats['completed']); ?>)</span></div>
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
        <p class="ios-action-modal-title" id="actionSheetTitle">Tournament</p>
        <p class="ios-action-modal-subtitle" id="actionSheetSub">Choose an action</p>
    </div>
    <div class="ios-action-modal-body" id="actionSheetBody"></div>
    <button class="ios-action-cancel" onclick="closeActionSheet()">Cancel</button>
</div>

<!-- ===== CONFIRM SHEET ===== -->
<div class="ios-menu-backdrop" id="confirmSheetBackdrop" onclick="closeConfirmSheet()"></div>
<div class="ios-confirm-sheet" id="confirmSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title" id="confirmSheetTitle">Confirm</h3>
        <button class="ios-menu-close" onclick="closeConfirmSheet()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-confirm-body">
        <div class="ios-confirm-tour-card">
            <p class="ios-confirm-tour-name" id="confirmTourName">Tournament name</p>
            <p class="ios-confirm-tour-desc"  id="confirmTourDesc">Description</p>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" id="confirmForm">
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
    var baseUrl = '<?php echo BASE_URL; ?>';
    var currentTour = null;

    // ─── Swipe-to-close ────────────────────────────────────────────
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

    // ─── Page Menu ─────────────────────────────────────────────────
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

    // ─── Action Sheet ───────────────────────────────────────────────
    var actionBackdrop = document.getElementById('actionSheetBackdrop');
    var actionSheet    = document.getElementById('actionSheet');
    var actionBody     = document.getElementById('actionSheetBody');

    window.openActionSheet = function(item) {
        var d = item.dataset;
        currentTour = { id: d.id, name: d.name, status: d.status, groups: d.groups, teams: d.teams, fixtures: d.fixtures };

        var nm = currentTour.name.length > 55 ? currentTour.name.slice(0, 52) + '…' : currentTour.name;
        document.getElementById('actionSheetTitle').textContent = nm;
        document.getElementById('actionSheetSub').textContent   =
            currentTour.teams + ' teams · ' + currentTour.fixtures + ' fixtures · ' + currentTour.status;

        var html = '';

        // View (always)
        html += '<a href="' + baseUrl + 'tournaments/view.php?id=' + d.id + '" class="ios-action-item">'
              + '<i class="fas fa-eye"></i><span>View Tournament</span></a>';

        if (currentTour.status === 'setup') {
            // Edit
            html += '<a href="' + baseUrl + 'tournaments/form.php?id=' + d.id + '" class="ios-action-item primary">'
                  + '<i class="fas fa-edit"></i><span>Edit Details</span></a>';
            // Setup groups & teams
            html += '<a href="' + baseUrl + 'tournaments/setup.php?id=' + d.id + '" class="ios-action-item primary">'
                  + '<i class="fas fa-layer-group"></i><span>Setup Groups &amp; Teams</span></a>';
        }

        // Add Fixture (setup + active)
        if (currentTour.status === 'setup' || currentTour.status === 'active') {
            html += '<a href="' + baseUrl + 'tournaments/fixture.php?tournament_id=' + d.id + '" class="ios-action-item">'
                  + '<i class="fas fa-futbol"></i><span>Add Fixture</span></a>';
        }

        // Status transitions
        if (currentTour.status === 'setup') {
            html += '<button class="ios-action-item success" onclick="openConfirm(\'activate\')">'
                  + '<i class="fas fa-play"></i><span>Activate Tournament</span></button>';
        } else if (currentTour.status === 'active') {
            html += '<button class="ios-action-item warning" onclick="openConfirm(\'complete\')">'
                  + '<i class="fas fa-flag-checkered"></i><span>Mark as Completed</span></button>';
        }

        // Delete (always)
        html += '<button class="ios-action-item danger" onclick="openConfirm(\'delete\')">'
              + '<i class="fas fa-trash"></i><span>Delete Tournament</span></button>';

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

    // ─── Confirm Sheet ──────────────────────────────────────────────
    var confirmBackdrop = document.getElementById('confirmSheetBackdrop');
    var confirmSheet    = document.getElementById('confirmSheet');

    var confirmCfg = {
        activate: { title: 'Activate Tournament', desc: 'The tournament will open for play. Ensure all groups and teams are configured.', btnClass: 'success', btnText: 'Activate' },
        complete: { title: 'Mark as Completed',   desc: 'The tournament will be closed. Final standings and results will be locked.',     btnClass: 'warning', btnText: 'Mark Completed' },
        delete:   { title: 'Delete Tournament',   desc: 'All groups, teams, fixtures, and stats will be permanently removed.',            btnClass: 'danger',  btnText: 'Delete Permanently' }
    };

    window.openConfirm = function(action) {
        if (!currentTour) return;
        var cfg = confirmCfg[action];
        if (!cfg) return;

        closeActionSheet();

        var nm = currentTour.name.length > 80 ? currentTour.name.slice(0, 77) + '…' : currentTour.name;
        document.getElementById('confirmSheetTitle').textContent = cfg.title;
        document.getElementById('confirmTourName').textContent   = nm;
        document.getElementById('confirmTourDesc').textContent   = cfg.desc;
        document.getElementById('confirmAction').value           = action;
        document.getElementById('confirmId').value               = currentTour.id;

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

}());
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
