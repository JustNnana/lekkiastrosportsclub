<?php
/**
 * Polls — Admin management list (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Poll.php';

requireAdmin();

$pageTitle = 'Manage Polls';
$pollObj   = new Poll();

$status  = sanitize($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total = $pollObj->countAll($status);
$paged = paginate($total, $perPage, $page);
$items = $pollObj->getAll($page, $perPage, $status);
$stats = $pollObj->getStats();

// Auto-close expired active polls (best-effort on page load)
$db = Database::getInstance();
$db->execute("UPDATE polls SET status='closed' WHERE status='active' AND deadline < NOW()");

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
    transition: var(--theme-transition);
}
.content .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); border-color: var(--primary); }
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
.content .stat-secondary .stat-icon { background: var(--text-muted); }
.content .stat-content { flex: 1; }
.content .stat-label  { font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--spacing-1); }
.content .stat-value  { font-size: 1.75rem; font-weight: var(--font-weight-bold); color: var(--text-primary); line-height: 1; margin-bottom: var(--spacing-2); }
.content .stat-detail { font-size: var(--font-size-xs); color: var(--text-secondary); }

/* iOS Section Card */
.ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: var(--spacing-4); }
.ios-section-header { display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-4); background: var(--bg-subtle); border-bottom: 1px solid var(--border-color); }
.ios-section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ios-section-icon.purple { background: rgba(191,90,242,0.15); color: var(--ios-purple); }
.ios-section-icon.blue   { background: rgba(10,132,255,0.15);  color: var(--ios-blue);   }
.ios-section-icon.primary{ background: rgba(34,197,94,0.15);   color: var(--ios-green);  }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0; }

/* Mobile 3-dot button */
.ios-options-btn { display: none; width: 36px; height: 36px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(0.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* Filter Pills */
.ios-filter-pills { display: flex; gap: 8px; padding: 12px 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; border-bottom: 1px solid var(--border-color); }
.ios-filter-pill { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: all 0.2s; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); }
.ios-filter-pill:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-filter-pill .count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.2); }
.ios-filter-pill:not(.active) .count { background: var(--border-color); color: var(--text-muted); }

/* Poll Item */
.ios-poll-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: var(--bg-primary); cursor: pointer; transition: background 0.15s; border-bottom: 1px solid var(--border-color); }
.ios-poll-item:last-child { border-bottom: none; }
.ios-poll-item:hover  { background: rgba(255,255,255,0.03); }
.ios-poll-item:active { background: rgba(255,255,255,0.06); }

.ios-poll-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ios-poll-icon.active { background: rgba(48,209,88,0.15);  color: var(--ios-green);  }
.ios-poll-icon.closed { background: rgba(142,142,147,0.15); color: var(--ios-gray);  }

.ios-poll-content { flex: 1; min-width: 0; }
.ios-poll-question { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-poll-sub { font-size: 12px; color: var(--text-muted); margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-poll-deadline { font-size: 12px; color: var(--text-muted); margin: 0; }
.ios-poll-deadline.overdue { color: var(--ios-red); }

.ios-poll-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; flex-shrink: 0; }
.ios-votes-chip { font-size: 12px; font-weight: 600; color: var(--ios-blue); background: rgba(10,132,255,0.1); padding: 3px 8px; border-radius: 10px; white-space: nowrap; }
.ios-status-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 6px; text-transform: capitalize; }
.ios-status-badge.active { background: rgba(48,209,88,0.15);  color: var(--ios-green); }
.ios-status-badge.closed { background: rgba(142,142,147,0.15);color: var(--ios-gray);  }

.ios-actions-btn { width: 32px; height: 32px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-actions-btn:hover  { background: var(--border-color); }
.ios-actions-btn:active { transform: scale(0.95); }
.ios-actions-btn i { color: var(--text-primary); font-size: 14px; }

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

/* Action sheet items */
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
.ios-confirm-poll-card { background: var(--bg-secondary); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.ios-confirm-poll-question { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; }
.ios-confirm-poll-desc     { font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5; }
.ios-form-btn { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; border: none; border-radius: 12px; font-size: 17px; font-weight: 600; text-align: center; cursor: pointer; transition: opacity 0.2s; font-family: inherit; color: white; }
.ios-form-btn:active     { opacity: 0.8; }
.ios-form-btn.warning    { background: var(--ios-orange); }
.ios-form-btn.success    { background: var(--ios-green);  }
.ios-form-btn.danger     { background: var(--ios-red);    }

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
    .ios-section-title h5 { font-size: 15px; }
    .ios-poll-item { padding: 12px 14px; }
    .ios-poll-icon { width: 38px; height: 38px; font-size: 16px; }
    .ios-poll-question { font-size: 14px; }
    .ios-status-badge { font-size: 10px; padding: 2px 6px; }
}
@media (max-width: 480px) {
    .ios-poll-sub  { display: none; }
    .ios-votes-chip { font-size: 11px; }
}
</style>

<!-- Content Header (hidden on mobile) -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="content-title"><i class="fas fa-poll me-2"></i>Polls & Voting</h1>
            <nav class="content-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Polls</li>
                </ol>
            </nav>
        </div>
        <div class="content-actions">
            <a href="<?php echo BASE_URL; ?>polls/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-poll-h me-2"></i>View Polls
            </a>
            <a href="<?php echo BASE_URL; ?>polls/form.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Poll
            </a>
        </div>
    </div>
</div>

<!-- Statistics Overview -->
<div class="stats-overview-grid">
    <div class="stat-card stat-primary">
        <div class="stat-icon"><i class="fas fa-poll"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Polls</div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-detail">All time</div>
        </div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-icon"><i class="fas fa-vote-yea"></i></div>
        <div class="stat-content">
            <div class="stat-label">Active</div>
            <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
            <div class="stat-detail">Open for voting</div>
        </div>
    </div>
    <div class="stat-card stat-secondary">
        <div class="stat-icon"><i class="fas fa-lock"></i></div>
        <div class="stat-content">
            <div class="stat-label">Closed</div>
            <div class="stat-value"><?php echo number_format($stats['closed']); ?></div>
            <div class="stat-detail">Voting ended</div>
        </div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-icon"><i class="fas fa-check-double"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Votes Cast</div>
            <div class="stat-value"><?php echo number_format($stats['total_votes']); ?></div>
            <div class="stat-detail">Across all polls</div>
        </div>
    </div>
</div>

<!-- Polls Section Card -->
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-icon purple"><i class="fas fa-poll"></i></div>
        <div class="ios-section-title">
            <h5><?php echo $status ? ucfirst($status) . ' Polls' : 'All Polls'; ?></h5>
            <p><?php echo number_format($total); ?> poll<?php echo $total != 1 ? 's' : ''; ?></p>
        </div>
        <button class="ios-options-btn" onclick="openPageMenu()" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <!-- Filter Pills -->
    <div class="ios-filter-pills">
        <a href="<?php echo BASE_URL; ?>polls/manage.php" class="ios-filter-pill <?php echo !$status ? 'active' : ''; ?>">
            All <span class="count"><?php echo number_format($stats['total']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>polls/manage.php?status=active" class="ios-filter-pill <?php echo $status === 'active' ? 'active' : ''; ?>">
            Active <span class="count"><?php echo number_format($stats['active']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>polls/manage.php?status=closed" class="ios-filter-pill <?php echo $status === 'closed' ? 'active' : ''; ?>">
            Closed <span class="count"><?php echo number_format($stats['closed']); ?></span>
        </a>
    </div>

    <?php if (empty($items)): ?>
    <!-- Empty State -->
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-poll"></i></div>
        <h3 class="ios-empty-title">No polls found</h3>
        <p class="ios-empty-description">
            <?php if ($status): ?>
                No <?php echo $status; ?> polls at the moment.
            <?php else: ?>
                No polls have been created yet.
            <?php endif; ?>
        </p>
        <a href="<?php echo BASE_URL; ?>polls/form.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Create First Poll
        </a>
    </div>

    <?php else: ?>
    <!-- Polls List -->
    <div class="ios-section-body" id="pollsList">
        <?php foreach ($items as $p):
            $isClosed  = $p['status'] === 'closed' || strtotime($p['deadline']) < time();
            $isPast    = strtotime($p['deadline']) < time() && $p['status'] === 'active';
            $iconClass = $isClosed ? 'closed' : 'active';
            $iconFa    = $isClosed ? 'fas fa-lock' : 'fas fa-chart-bar';
            $deadlineText = formatDate($p['deadline'], 'd M Y, g:i A');
        ?>
        <div class="ios-poll-item"
             data-id="<?php echo $p['id']; ?>"
             data-question="<?php echo e($p['question']); ?>"
             data-status="<?php echo $isClosed ? 'closed' : 'active'; ?>"
             data-votes="<?php echo (int)$p['total_votes']; ?>"
             onclick="openActionSheet(this)">
            <div class="ios-poll-icon <?php echo $iconClass; ?>">
                <i class="<?php echo $iconFa; ?>"></i>
            </div>
            <div class="ios-poll-content">
                <p class="ios-poll-question"><?php echo e($p['question']); ?></p>
                <p class="ios-poll-sub">
                    <?php echo e($p['creator_name'] ?: ($p['creator_email'] ?? 'Admin')); ?>
                    &nbsp;·&nbsp;
                    <?php echo $p['option_count']; ?> option<?php echo $p['option_count'] != 1 ? 's' : ''; ?>
                </p>
                <p class="ios-poll-deadline <?php echo $isPast ? 'overdue' : ''; ?>">
                    <i class="fas fa-clock" style="font-size:10px;margin-right:3px"></i>
                    <?php echo $deadlineText; ?>
                    <?php if ($isPast): ?> · Expired<?php endif; ?>
                </p>
            </div>
            <div class="ios-poll-meta">
                <span class="ios-votes-chip">
                    <i class="fas fa-check" style="font-size:10px;margin-right:3px"></i>
                    <?php echo number_format($p['total_votes']); ?> vote<?php echo $p['total_votes'] != 1 ? 's' : ''; ?>
                </span>
                <span class="ios-status-badge <?php echo $iconClass; ?>">
                    <?php echo $isClosed ? 'Closed' : 'Active'; ?>
                </span>
            </div>
            <button class="ios-actions-btn" onclick="event.stopPropagation(); openActionSheet(this.closest('.ios-poll-item'))" aria-label="Actions">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- iOS Pagination -->
    <?php if ($paged['total_pages'] > 1): ?>
    <div class="ios-pagination-info">
        Showing <?php echo number_format(($page - 1) * $perPage + 1); ?>–<?php echo number_format(min($page * $perPage, $total)); ?>
        of <?php echo number_format($total); ?> polls
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
        <h3 class="ios-menu-title">Polls & Voting</h3>
        <button class="ios-menu-close" onclick="closePageMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>polls/form.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-plus"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Create Poll</span>
                            <span class="ios-menu-item-desc">Start a new vote</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>polls/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-poll-h"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">View Polls</span>
                            <span class="ios-menu-item-desc">See all polls</span>
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
                    <span class="ios-menu-stat-label">Total Polls</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active</span>
                    <span class="ios-menu-stat-value success"><?php echo number_format($stats['active']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Closed</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['closed']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Votes Cast</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total_votes']); ?></span>
                </div>
            </div>
        </div>
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>polls/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">All Polls</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>polls/manage.php?status=active" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-vote-yea"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">Active (<?php echo number_format($stats['active']); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>polls/manage.php?status=closed" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-lock"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">Closed (<?php echo number_format($stats['closed']); ?>)</span></div>
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
        <p class="ios-action-modal-title" id="actionSheetTitle">Poll</p>
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
        <div class="ios-confirm-poll-card">
            <p class="ios-confirm-poll-question" id="confirmPollQuestion">Poll question</p>
            <p class="ios-confirm-poll-desc"    id="confirmPollDesc">Description</p>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>polls/actions.php" id="confirmForm">
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
    var currentPoll = null;

    // ─── Swipe-to-close helper ────────────────────────────────────────
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

    // ─── Page Menu ────────────────────────────────────────────────────
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

    // ─── Action Sheet ─────────────────────────────────────────────────
    var actionBackdrop = document.getElementById('actionSheetBackdrop');
    var actionSheet    = document.getElementById('actionSheet');
    var actionBody     = document.getElementById('actionSheetBody');

    window.openActionSheet = function(item) {
        var d = item.dataset;
        currentPoll = { id: d.id, question: d.question, status: d.status, votes: d.votes };

        var q = currentPoll.question.length > 60
              ? currentPoll.question.slice(0, 57) + '…'
              : currentPoll.question;

        document.getElementById('actionSheetTitle').textContent = q;
        document.getElementById('actionSheetSub').textContent   = currentPoll.votes + ' votes · ' + currentPoll.status;

        var html = '';
        // View Results
        html += '<a href="' + baseUrl + 'polls/view.php?id=' + d.id + '" class="ios-action-item">'
              + '<i class="fas fa-chart-bar"></i><span>View Results</span></a>';

        // Edit (active only)
        if (currentPoll.status === 'active') {
            html += '<a href="' + baseUrl + 'polls/form.php?id=' + d.id + '" class="ios-action-item primary">'
                  + '<i class="fas fa-edit"></i><span>Edit Poll</span></a>';
            html += '<button class="ios-action-item warning" onclick="openConfirm(\'close\')">'
                  + '<i class="fas fa-lock"></i><span>Close Early</span></button>';
        }

        // Reopen (closed only)
        if (currentPoll.status === 'closed') {
            html += '<button class="ios-action-item success" onclick="openConfirm(\'reopen\')">'
                  + '<i class="fas fa-redo"></i><span>Reopen Poll</span></button>';
        }

        // Delete
        html += '<button class="ios-action-item danger" onclick="openConfirm(\'delete\')">'
              + '<i class="fas fa-trash"></i><span>Delete Poll</span></button>';

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

    // ─── Confirm Sheet ────────────────────────────────────────────────
    var confirmBackdrop = document.getElementById('confirmSheetBackdrop');
    var confirmSheet    = document.getElementById('confirmSheet');

    var confirmCfg = {
        close:  { title: 'Close Poll Early',  desc: 'Voting will end immediately. Members can no longer cast votes.', btnClass: 'warning', btnText: 'Close Poll'          },
        reopen: { title: 'Reopen Poll',        desc: 'Members will be able to cast votes again until the deadline.',   btnClass: 'success', btnText: 'Reopen Poll'         },
        delete: { title: 'Delete Poll',        desc: 'All votes will be permanently removed. This cannot be undone.',  btnClass: 'danger',  btnText: 'Delete Permanently'  }
    };

    window.openConfirm = function(action) {
        if (!currentPoll) return;
        var cfg = confirmCfg[action];
        if (!cfg) return;

        closeActionSheet();

        var q = currentPoll.question.length > 80
              ? currentPoll.question.slice(0, 77) + '…'
              : currentPoll.question;

        document.getElementById('confirmSheetTitle').textContent   = cfg.title;
        document.getElementById('confirmPollQuestion').textContent = q;
        document.getElementById('confirmPollDesc').textContent     = cfg.desc;
        document.getElementById('confirmAction').value             = action;
        document.getElementById('confirmId').value                 = currentPoll.id;

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
