<?php
/**
 * Events — Admin management list (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Event.php';

requireAdmin();

$pageTitle  = 'Manage Events';
$eventObj   = new Event();

$type    = sanitize($_GET['type']   ?? '');
$status  = sanitize($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total = $eventObj->countAll($type, $status);
$paged = paginate($total, $perPage, $page);
$items = $eventObj->getAll($page, $perPage, $type, $status);
$stats = $eventObj->getStats();

$typeLabels = [
    'training' => ['label' => 'Training', 'icon' => 'fa-running',       'color' => 'blue'],
    'match'    => ['label' => 'Match',    'icon' => 'fa-futbol',        'color' => 'red'],
    'meeting'  => ['label' => 'Meeting',  'icon' => 'fa-users',         'color' => 'orange'],
    'social'   => ['label' => 'Social',   'icon' => 'fa-glass-cheers',  'color' => 'purple'],
    'other'    => ['label' => 'Other',    'icon' => 'fa-calendar-day',  'color' => 'gray'],
];

function buildEvQS(string $type, string $status, int $page = 1): string {
    $p = [];
    if ($type)   $p['type']   = $type;
    if ($status) $p['status'] = $status;
    if ($page > 1) $p['page'] = $page;
    return $p ? '?' . http_build_query($p) : '';
}

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
    grid-template-columns: repeat(4, 1fr);
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}
.content .stat-card-link { text-decoration: none; display: block; }
.content .stat-card {
    background: transparent; border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg); padding: var(--spacing-5);
    display: flex; align-items: center; gap: var(--spacing-4);
    transition: var(--theme-transition);
}
.content .stat-card-link:hover .stat-card { transform: translateY(-2px); box-shadow: var(--shadow); border-color: var(--primary); }
.content .stat-icon { width: 52px; height: 52px; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; flex-shrink: 0; }
.content .stat-primary .stat-icon { background: var(--primary); }
.content .stat-success .stat-icon { background: var(--success); }
.content .stat-secondary .stat-icon { background: var(--ios-gray); }
.content .stat-danger  .stat-icon { background: var(--danger); }
.content .stat-content { flex: 1; }
.content .stat-label  { font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--spacing-1); }
.content .stat-value  { font-size: 1.75rem; font-weight: var(--font-weight-bold); color: var(--text-primary); line-height: 1; }

/* iOS Section Card */
.ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: var(--spacing-4); }
.ios-section-header { display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-4); background: var(--bg-subtle); border-bottom: 1px solid var(--border-color); }
.ios-section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ios-section-icon.green  { background: rgba(48,209,88,0.15);   color: var(--ios-green);  }
.ios-section-icon.orange { background: rgba(255,159,10,0.15);  color: var(--ios-orange); }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }
.ios-options-btn { display: none; width: 36px; height: 36px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-options-btn:hover { background: var(--border-color); }
.ios-options-btn:active { transform: scale(0.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 15px; }

/* Filter Pills */
.ios-filter-row { display: flex; gap: 8px; padding: 12px 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; border-bottom: 1px solid var(--border-color); }
.ios-filter-row:last-of-type { border-bottom: 1px solid var(--border-color); }
.ios-filter-pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: all 0.2s; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); }
.ios-filter-pill:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-filter-pill.active.type-blue    { background: var(--ios-blue);   border-color: var(--ios-blue);   }
.ios-filter-pill.active.type-red     { background: var(--ios-red);    border-color: var(--ios-red);    }
.ios-filter-pill.active.type-orange  { background: var(--ios-orange); border-color: var(--ios-orange); }
.ios-filter-pill.active.type-purple  { background: var(--ios-purple); border-color: var(--ios-purple); }
.ios-filter-pill.active.type-gray    { background: var(--ios-gray);   border-color: var(--ios-gray);   }
.pill-count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.25); }
.ios-filter-pill:not(.active) .pill-count { background: var(--border-color); color: var(--text-muted); }

/* Event Item */
.ios-event-item { display: flex; align-items: flex-start; gap: 13px; padding: 14px 16px; background: var(--bg-primary); border-bottom: 1px solid var(--border-color); transition: background 0.15s; }
.ios-event-item:last-child { border-bottom: none; }
.ios-event-item:hover { background: rgba(255,255,255,0.02); }

.event-type-icon { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; margin-top: 2px; }
.event-type-icon.blue   { background: rgba(10,132,255,0.12);  color: var(--ios-blue);   }
.event-type-icon.red    { background: rgba(255,69,58,0.12);   color: var(--ios-red);    }
.event-type-icon.orange { background: rgba(255,159,10,0.12);  color: var(--ios-orange); }
.event-type-icon.purple { background: rgba(191,90,242,0.12);  color: var(--ios-purple); }
.event-type-icon.gray   { background: rgba(142,142,147,0.12); color: var(--ios-gray);   }

.event-content { flex: 1; min-width: 0; }
.event-title-row { display: flex; align-items: center; gap: 7px; margin-bottom: 2px; flex-wrap: wrap; }
.event-title-link { font-size: 15px; font-weight: 600; color: var(--text-primary); text-decoration: none; }
.event-title-link:hover { color: var(--ios-blue); }
.event-recurring-icon { font-size: 9px; color: var(--ios-blue); background: rgba(10,132,255,0.1); padding: 2px 5px; border-radius: 5px; }
.event-sub  { font-size: 12px; color: var(--text-muted); margin: 0 0 6px; }
.event-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 6px; }
.event-type-chip { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 8px; white-space: nowrap; }
.event-type-chip.blue   { background: rgba(10,132,255,0.12);  color: var(--ios-blue);   }
.event-type-chip.red    { background: rgba(255,69,58,0.12);   color: var(--ios-red);    }
.event-type-chip.orange { background: rgba(255,159,10,0.12);  color: var(--ios-orange); }
.event-type-chip.purple { background: rgba(191,90,242,0.12);  color: var(--ios-purple); }
.event-type-chip.gray   { background: rgba(142,142,147,0.12); color: var(--ios-gray);   }
.event-status-chip { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 8px; white-space: nowrap; }
.event-status-chip.active    { background: rgba(48,209,88,0.12);   color: var(--ios-green);  }
.event-status-chip.completed { background: rgba(142,142,147,0.12); color: var(--ios-gray);   }
.event-status-chip.cancelled { background: rgba(255,69,58,0.12);   color: var(--ios-red);    }
.event-loc-chip { font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 8px; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-muted); white-space: nowrap; max-width: 160px; overflow: hidden; text-overflow: ellipsis; }

.event-rsvp { display: flex; align-items: center; gap: 8px; }
.rsvp-chip { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 8px; white-space: nowrap; }
.rsvp-chip.yes   { background: rgba(48,209,88,0.12);   color: var(--ios-green);  }
.rsvp-chip.maybe { background: rgba(255,159,10,0.12);  color: var(--ios-orange); }
.rsvp-chip.no    { background: rgba(255,69,58,0.12);   color: var(--ios-red);    }

.ios-actions-btn { width: 32px; height: 32px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; margin-top: 2px; }
.ios-actions-btn:hover  { background: var(--border-color); }
.ios-actions-btn:active { transform: scale(0.95); }
.ios-actions-btn i { color: var(--text-primary); font-size: 13px; }

/* Empty */
.ios-empty-state { text-align: center; padding: 48px 24px; }
.ios-empty-icon  { font-size: 56px; opacity: 0.35; margin-bottom: 16px; }
.ios-empty-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px; }
.ios-empty-desc  { font-size: 14px; color: var(--text-secondary); margin: 0; }

/* Pagination */
.ios-pagination { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 16px; border-top: 1px solid var(--border-color); }
.ios-page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 14px; font-weight: 500; text-decoration: none; color: var(--text-secondary); background: var(--bg-secondary); border: 1px solid var(--border-color); transition: all 0.2s; }
.ios-page-btn:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-page-btn.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-pagination-info { font-size: 12px; color: var(--text-muted); text-align: center; padding: 0 16px 12px; }

/* Backdrop + Sheets */
.ios-menu-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal, .ios-action-modal, .ios-confirm-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary);
    border-radius: 16px 16px 0 0; transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1); overflow: hidden;
}
.ios-menu-modal    { z-index: 9999;  max-height: 85vh; display: flex; flex-direction: column; }
.ios-action-modal  { z-index: 10000; max-height: 65vh; }
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
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background 0.15s; cursor: pointer; }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.primary { background: rgba(34,197,94,0.15);  color: var(--ios-green);  }
.ios-menu-item-icon.blue    { background: rgba(10,132,255,0.15); color: var(--ios-blue);   }
.ios-menu-item-icon.orange  { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple  { background: rgba(191,90,242,0.15); color: var(--ios-purple); }
.ios-menu-item-content { flex: 1; min-width: 0; }
.ios-menu-item-label   { font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-menu-item-desc    { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }
.ios-menu-stat-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 15px; color: var(--text-primary); }
.ios-menu-stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); }

/* Action sheet */
.ios-action-modal-header   { padding: 16px; border-bottom: 1px solid var(--border-color); text-align: center; }
.ios-action-modal-title    { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px; }
.ios-action-modal-subtitle { font-size: 13px; color: var(--text-secondary); margin: 0; }
.ios-action-modal-body     { padding: 8px; overflow-y: auto; }
.ios-action-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px; text-decoration: none; color: var(--text-primary); transition: background 0.15s; cursor: pointer; border: none; background: none; width: 100%; text-align: left; font-family: inherit; font-size: inherit; }
.ios-action-item:active  { background: var(--bg-secondary); }
.ios-action-item i       { width: 24px; font-size: 18px; }
.ios-action-item.success { color: var(--ios-green);  }
.ios-action-item.warning { color: var(--ios-orange); }
.ios-action-item.primary { color: var(--ios-blue);   }
.ios-action-item.danger  { color: var(--ios-red);    }
.ios-action-cancel { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; background: var(--bg-secondary); border: none; border-radius: 12px; font-size: 17px; font-weight: 600; color: var(--ios-blue); text-align: center; cursor: pointer; transition: background 0.15s; font-family: inherit; }
.ios-action-cancel:active { background: var(--border-color); }

/* Confirm sheet */
.ios-confirm-body { padding: 20px 16px 8px; overflow-y: auto; }
.ios-confirm-event-card { background: var(--bg-secondary); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.ios-confirm-event-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; }
.ios-confirm-event-desc  { font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5; }
.ios-form-btn { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; border: none; border-radius: 12px; font-size: 17px; font-weight: 600; text-align: center; cursor: pointer; transition: opacity 0.2s; font-family: inherit; color: white; }
.ios-form-btn:active { opacity: 0.8; }
.ios-form-btn.success { background: var(--ios-green);  }
.ios-form-btn.warning { background: var(--ios-orange); }
.ios-form-btn.primary { background: var(--ios-blue);   }
.ios-form-btn.danger  { background: var(--ios-red);    }

/* Responsive */
@media (max-width: 992px) { .ios-options-btn { display: flex; } }
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .content .stats-overview-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.75rem !important; }
    .content .stat-icon { width: 40px !important; height: 40px !important; font-size: 1.1rem; }
    .content .stat-value { font-size: 1.4rem; }
    .event-loc-chip { display: none; }
}
@media (max-width: 480px) {
    .content .stats-overview-grid { grid-template-columns: repeat(2, 1fr) !important; }
}
</style>

<!-- Desktop Header -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="content-title"><i class="fas fa-calendar-alt me-2"></i>Manage Events</h1>
            <nav class="content-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Events</li>
                </ol>
            </nav>
        </div>
        <div class="content-actions">
            <a href="<?php echo BASE_URL; ?>events/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-calendar me-2"></i>Calendar View
            </a>
            <a href="<?php echo BASE_URL; ?>events/form.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>New Event
            </a>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="stats-overview-grid">
    <a href="<?php echo BASE_URL; ?>events/manage.php" class="stat-card-link">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Events</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            </div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>events/manage.php?status=active" class="stat-card-link">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-content">
                <div class="stat-label">Upcoming</div>
                <div class="stat-value"><?php echo number_format($stats['upcoming']); ?></div>
            </div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>events/manage.php?status=completed" class="stat-card-link">
        <div class="stat-card stat-secondary">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-label">Completed</div>
                <div class="stat-value"><?php echo number_format($stats['completed']); ?></div>
            </div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>events/manage.php?status=cancelled" class="stat-card-link">
        <div class="stat-card stat-danger">
            <div class="stat-icon"><i class="fas fa-ban"></i></div>
            <div class="stat-content">
                <div class="stat-label">Cancelled</div>
                <div class="stat-value"><?php echo number_format($stats['cancelled']); ?></div>
            </div>
        </div>
    </a>
</div>

<!-- Events Section Card -->
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-icon green"><i class="fas fa-calendar-alt"></i></div>
        <div class="ios-section-title">
            <h5><?php echo $type ? ($typeLabels[$type]['label'] ?? ucfirst($type)) . ' Events' : 'All Events'; ?></h5>
            <p><?php echo number_format($total); ?> event<?php echo $total != 1 ? 's' : ''; ?><?php echo $status ? ' · ' . ucfirst($status) : ''; ?></p>
        </div>
        <button class="ios-options-btn" onclick="openPageMenu()" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <!-- Type Filter Pills -->
    <div class="ios-filter-row">
        <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS('', $status); ?>"
           class="ios-filter-pill <?php echo !$type ? 'active' : ''; ?>">All Types</a>
        <?php foreach ($typeLabels as $val => $t): ?>
        <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS($val, $status); ?>"
           class="ios-filter-pill type-<?php echo $t['color']; ?> <?php echo $type === $val ? 'active' : ''; ?>">
            <i class="fas <?php echo $t['icon']; ?>" style="font-size:11px"></i>
            <?php echo $t['label']; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Status Filter Pills -->
    <div class="ios-filter-row">
        <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS($type, ''); ?>"
           class="ios-filter-pill <?php echo !$status ? 'active' : ''; ?>">
            All <span class="pill-count"><?php echo number_format($stats['total']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS($type, 'active'); ?>"
           class="ios-filter-pill <?php echo $status === 'active' ? 'active' : ''; ?>">Active</a>
        <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS($type, 'completed'); ?>"
           class="ios-filter-pill <?php echo $status === 'completed' ? 'active' : ''; ?>">
            Completed <span class="pill-count"><?php echo number_format($stats['completed']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS($type, 'cancelled'); ?>"
           class="ios-filter-pill <?php echo $status === 'cancelled' ? 'active' : ''; ?>">
            Cancelled <span class="pill-count"><?php echo number_format($stats['cancelled']); ?></span>
        </a>
    </div>

    <!-- List -->
    <?php if (empty($items)): ?>
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-calendar-times"></i></div>
        <h3 class="ios-empty-title">No events found</h3>
        <p class="ios-empty-desc">Try adjusting your filters or create a new event.</p>
    </div>
    <?php else: ?>
    <?php foreach ($items as $ev):
        $tl     = $typeLabels[$ev['event_type']] ?? ['label' => ucfirst($ev['event_type']), 'icon' => 'fa-calendar-day', 'color' => 'gray'];
        $evStatus = $ev['status'];
    ?>
    <div class="ios-event-item"
         data-id="<?php echo $ev['id']; ?>"
         data-title="<?php echo e($ev['title']); ?>"
         data-status="<?php echo e($evStatus); ?>"
         data-type="<?php echo e($ev['event_type']); ?>"
         data-recurring="<?php echo $ev['is_recurring'] ? '1' : '0'; ?>">

        <div class="event-type-icon <?php echo $tl['color']; ?>">
            <i class="fas <?php echo $tl['icon']; ?>"></i>
        </div>

        <div class="event-content">
            <div class="event-title-row">
                <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $ev['id']; ?>"
                   class="event-title-link"><?php echo e($ev['title']); ?></a>
                <?php if ($ev['is_recurring']): ?>
                <span class="event-recurring-icon" title="Recurring"><i class="fas fa-redo"></i></span>
                <?php endif; ?>
            </div>
            <p class="event-sub">
                <?php echo e($ev['creator_name']); ?> &nbsp;·&nbsp;
                <?php echo formatDate($ev['start_date'], 'd M Y, g:i A'); ?>
            </p>
            <div class="event-chips">
                <span class="event-type-chip <?php echo $tl['color']; ?>"><?php echo $tl['label']; ?></span>
                <span class="event-status-chip <?php echo $evStatus; ?>"><?php echo ucfirst($evStatus); ?></span>
                <?php if ($ev['location']): ?>
                <span class="event-loc-chip"><i class="fas fa-map-marker-alt" style="font-size:9px;margin-right:3px"></i><?php echo e($ev['location']); ?></span>
                <?php endif; ?>
            </div>
            <div class="event-rsvp">
                <span class="rsvp-chip yes"><i class="fas fa-check" style="font-size:9px;margin-right:2px"></i><?php echo (int)$ev['attending_count']; ?></span>
                <span class="rsvp-chip maybe"><i class="fas fa-question" style="font-size:9px;margin-right:2px"></i><?php echo (int)$ev['maybe_count']; ?></span>
                <span class="rsvp-chip no"><i class="fas fa-times" style="font-size:9px;margin-right:2px"></i><?php echo (int)$ev['not_attending_count']; ?></span>
            </div>
        </div>

        <button class="ios-actions-btn"
                onclick="openActionSheet(this.closest('.ios-event-item'))"
                aria-label="Actions">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($paged['total_pages'] > 1): ?>
    <p class="ios-pagination-info">
        Showing <?php echo number_format(($page - 1) * $perPage + 1); ?>–<?php echo number_format(min($page * $perPage, $total)); ?>
        of <?php echo number_format($total); ?>
    </p>
    <div class="ios-pagination">
        <?php if ($paged['has_prev']): ?>
        <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS($type, $status, $page - 1); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($paged['total_pages'], $page + 2); $i++): ?>
        <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS($type, $status, $i); ?>"
           class="ios-page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($paged['has_next']): ?>
        <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS($type, $status, $page + 1); ?>" class="ios-page-btn">
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
        <h3 class="ios-menu-title">Events</h3>
        <button class="ios-menu-close" onclick="closePageMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>events/form.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-plus"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">New Event</span>
                            <span class="ios-menu-item-desc">Create a new event</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>events/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-calendar"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Calendar View</span>
                            <span class="ios-menu-item-desc">See events as members do</span>
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
                    <span class="ios-menu-stat-label">Total Events</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Upcoming</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['upcoming']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Completed</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['completed']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Cancelled</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['cancelled']); ?></span>
                </div>
            </div>
        </div>
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter by Type</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS('', $status); ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">All Types</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php foreach ($typeLabels as $val => $t): ?>
                <a href="<?php echo BASE_URL; ?>events/manage.php<?php echo buildEvQS($val, $status); ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon <?php echo $t['color'] === 'blue' ? 'blue' : ($t['color'] === 'red' ? 'orange' : ($t['color'] === 'orange' ? 'orange' : ($t['color'] === 'purple' ? 'purple' : 'blue'))); ?>">
                            <i class="fas <?php echo $t['icon']; ?>"></i>
                        </div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label"><?php echo $t['label']; ?></span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ===== PER-ITEM ACTION SHEET ===== -->
<div class="ios-menu-backdrop" id="actionSheetBackdrop" onclick="closeActionSheet()"></div>
<div class="ios-action-modal" id="actionSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-action-modal-header">
        <p class="ios-action-modal-title" id="actionSheetTitle">Event</p>
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
        <h3 class="ios-menu-title" id="confirmTitle">Confirm</h3>
        <button class="ios-menu-close" onclick="closeConfirmSheet()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-confirm-body">
        <div class="ios-confirm-event-card">
            <p class="ios-confirm-event-title" id="confirmEventTitle">Event Title</p>
            <p class="ios-confirm-event-desc" id="confirmEventDesc">Description</p>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>events/actions.php" id="confirmForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" id="confirmAction">
            <input type="hidden" name="id" id="confirmId">
            <button type="submit" class="ios-form-btn" id="confirmBtn">Confirm</button>
        </form>
    </div>
    <button class="ios-action-cancel" onclick="closeConfirmSheet()">Cancel</button>
</div>

<script>
(function () {
    var baseUrl      = '<?php echo BASE_URL; ?>';
    var currentEvent = null;

    var confirmCfg = {
        complete:   { title: 'Mark as Completed', desc: 'This event will be marked as completed. RSVPs will be preserved.',       btnClass: 'success', btnText: 'Mark Completed',  action: 'complete'   },
        cancel:     { title: 'Cancel Event',      desc: 'The event will be cancelled. Members will be notified.',               btnClass: 'warning', btnText: 'Cancel Event',    action: 'cancel'     },
        reactivate: { title: 'Reactivate Event',  desc: 'The event will be set back to active status.',                         btnClass: 'primary', btnText: 'Reactivate',      action: 'reactivate' },
        delete:     { title: 'Delete Event',      desc: 'This event and all RSVPs will be permanently deleted.',                btnClass: 'danger',  btnText: 'Delete Permanently', action: 'delete'   },
    };

    // ─── Swipe-to-close ──────────────────────────────────────────
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

    // ─── Page Menu ───────────────────────────────────────────────
    var pageMenuBackdrop = document.getElementById('pageMenuBackdrop');
    var pageMenuSheet    = document.getElementById('pageMenuSheet');
    window.openPageMenu  = function() { pageMenuBackdrop.classList.add('active'); pageMenuSheet.classList.add('active'); document.body.style.overflow = 'hidden'; };
    window.closePageMenu = function() { pageMenuBackdrop.classList.remove('active'); pageMenuSheet.classList.remove('active'); document.body.style.overflow = ''; };
    addSwipeClose(pageMenuSheet, closePageMenu);

    // ─── Action Sheet ─────────────────────────────────────────────
    var actionBackdrop = document.getElementById('actionSheetBackdrop');
    var actionSheet    = document.getElementById('actionSheet');

    window.openActionSheet = function(item) {
        var d = item.dataset;
        currentEvent = { id: d.id, title: d.title, status: d.status, type: d.type, recurring: d.recurring };

        var t = currentEvent.title.length > 50 ? currentEvent.title.slice(0, 47) + '…' : currentEvent.title;
        document.getElementById('actionSheetTitle').textContent = t;
        document.getElementById('actionSheetSub').textContent   = currentEvent.type.charAt(0).toUpperCase() + currentEvent.type.slice(1) + ' · ' + currentEvent.status.charAt(0).toUpperCase() + currentEvent.status.slice(1);

        var body = '';
        body += '<a href="' + baseUrl + 'events/view.php?id=' + d.id + '" class="ios-action-item primary"><i class="fas fa-eye"></i><span>View Event</span></a>';

        if (currentEvent.status === 'active') {
            body += '<a href="' + baseUrl + 'events/form.php?id=' + d.id + '" class="ios-action-item"><i class="fas fa-edit"></i><span>Edit Event</span></a>';
            body += '<button class="ios-action-item success" onclick="openConfirmSheet(\'complete\')"><i class="fas fa-check-circle"></i><span>Mark as Completed</span></button>';
            body += '<button class="ios-action-item warning" onclick="openConfirmSheet(\'cancel\')"><i class="fas fa-ban"></i><span>Cancel Event</span></button>';
        } else {
            body += '<button class="ios-action-item primary" onclick="openConfirmSheet(\'reactivate\')"><i class="fas fa-redo"></i><span>Reactivate Event</span></button>';
        }
        body += '<button class="ios-action-item danger" onclick="openConfirmSheet(\'delete\')"><i class="fas fa-trash"></i><span>Delete Event</span></button>';

        document.getElementById('actionSheetBody').innerHTML = body;

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

    // ─── Confirm Sheet ────────────────────────────────────────────
    var confirmBackdrop = document.getElementById('confirmSheetBackdrop');
    var confirmSheet    = document.getElementById('confirmSheet');

    window.openConfirmSheet = function(type) {
        if (!currentEvent) return;
        var cfg = confirmCfg[type];
        if (!cfg) return;

        var t = currentEvent.title.length > 70 ? currentEvent.title.slice(0, 67) + '…' : currentEvent.title;
        document.getElementById('confirmTitle').textContent       = cfg.title;
        document.getElementById('confirmEventTitle').textContent  = t;
        document.getElementById('confirmEventDesc').textContent   = cfg.desc;
        document.getElementById('confirmAction').value            = cfg.action;
        document.getElementById('confirmId').value                = currentEvent.id;

        var btn = document.getElementById('confirmBtn');
        btn.textContent = cfg.btnText;
        btn.className   = 'ios-form-btn ' + cfg.btnClass;

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
