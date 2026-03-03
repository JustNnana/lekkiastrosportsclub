<?php
/**
 * Manage Dues — Admin only (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Due.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireAdmin();

$pageTitle = 'Manage Dues';
$dueObj    = new Due();

$search  = sanitize($_GET['search'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['active','inactive']) ? $_GET['status'] : '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total = $dueObj->countAll($search, $status);
$paged = paginate($total, $perPage, $page);
$dues  = $dueObj->getAll($page, $perPage, $search, $status);
$stats = $dueObj->getStats();

function buildDueQS(string $search, string $status, int $page = 1): string {
    $p = [];
    if ($search) $p['search'] = $search;
    if ($status) $p['status'] = $status;
    if ($page > 1) $p['page'] = $page;
    return $p ? '?' . http_build_query($p) : '';
}

function freqLabel(string $freq): string {
    return match($freq) {
        'one_time'  => 'One-time',
        'weekly'    => 'Weekly',
        'monthly'   => 'Monthly',
        'quarterly' => 'Quarterly',
        'annual', 'yearly' => 'Annual',
        default     => ucfirst(str_replace('_', ' ', $freq)),
    };
}

function freqColor(string $freq): string {
    return match($freq) {
        'one_time'           => 'gray',
        'weekly'             => 'blue',
        'monthly'            => 'green',
        'quarterly'          => 'orange',
        'annual', 'yearly'   => 'purple',
        default              => 'gray',
    };
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
    grid-template-columns: repeat(3, 1fr);
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
.content .stat-danger  .stat-icon { background: var(--danger);  }
.content .stat-content { flex: 1; }
.content .stat-label  { font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--spacing-1); }
.content .stat-value  { font-size: 1.75rem; font-weight: var(--font-weight-bold); color: var(--text-primary); line-height: 1; }

/* iOS Section Card */
.ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: var(--spacing-4); }
.ios-section-header { display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-4); background: var(--bg-subtle); border-bottom: 1px solid var(--border-color); }
.ios-section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ios-section-icon.green  { background: rgba(48,209,88,0.15);  color: var(--ios-green); }
.ios-section-icon.blue   { background: rgba(10,132,255,0.15); color: var(--ios-blue);  }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }
.ios-options-btn { display: none; width: 36px; height: 36px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-options-btn:hover { background: var(--border-color); }
.ios-options-btn:active { transform: scale(0.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 15px; }

/* Search */
.ios-search-wrap { padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-search-form { display: flex; align-items: center; gap: 8px; }
.ios-search-input-wrap { position: relative; flex: 1; }
.ios-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; pointer-events: none; }
.ios-search-input { width: 100%; padding: 9px 34px 9px 34px; border-radius: 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 14px; outline: none; transition: border-color 0.2s; }
.ios-search-input:focus { border-color: var(--ios-blue); }
.ios-search-input::placeholder { color: var(--text-muted); }
.ios-search-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; text-decoration: none; }
.ios-search-clear:hover { color: var(--text-primary); }
.ios-search-submit { padding: 9px 16px; border-radius: 12px; background: var(--ios-blue); border: none; color: white; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: opacity 0.2s; }
.ios-search-submit:active { opacity: 0.8; }

/* Filter Pills */
.ios-filter-pills { display: flex; gap: 8px; padding: 12px 16px; overflow-x: auto; -webkit-overflow-scrolling: touch; border-bottom: 1px solid var(--border-color); }
.ios-filter-pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: all 0.2s; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); }
.ios-filter-pill:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-filter-pill.active.green { background: var(--ios-green); border-color: var(--ios-green); }
.ios-filter-pill.active.red   { background: var(--ios-red);   border-color: var(--ios-red);   }
.pill-count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.25); }
.ios-filter-pill:not(.active) .pill-count { background: var(--border-color); color: var(--text-muted); }

/* Due Item */
.ios-due-item { display: flex; align-items: flex-start; gap: 13px; padding: 14px 16px; background: var(--bg-primary); border-bottom: 1px solid var(--border-color); transition: background 0.15s; }
.ios-due-item:last-child { border-bottom: none; }
.ios-due-item:hover { background: rgba(255,255,255,0.02); }

.due-icon { width: 42px; height: 42px; border-radius: 11px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; margin-top: 2px; }
.due-icon.active   { background: rgba(48,209,88,0.12);   color: var(--ios-green); }
.due-icon.inactive { background: rgba(142,142,147,0.12); color: var(--ios-gray);  }

.due-content { flex: 1; min-width: 0; }
.due-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.due-desc  { font-size: 12px; color: var(--text-muted); margin: 0 0 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.due-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 6px; }

.due-freq-chip { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 8px; white-space: nowrap; }
.due-freq-chip.gray   { background: rgba(142,142,147,0.12); color: var(--ios-gray);   }
.due-freq-chip.blue   { background: rgba(10,132,255,0.12);  color: var(--ios-blue);   }
.due-freq-chip.green  { background: rgba(48,209,88,0.12);   color: var(--ios-green);  }
.due-freq-chip.orange { background: rgba(255,159,10,0.12);  color: var(--ios-orange); }
.due-freq-chip.purple { background: rgba(191,90,242,0.12);  color: var(--ios-purple); }

.due-amount-chip { font-size: 12px; font-weight: 700; padding: 2px 9px; border-radius: 8px; background: rgba(10,132,255,0.1); color: var(--ios-blue); white-space: nowrap; }
.due-penalty-chip { font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 8px; background: rgba(255,69,58,0.1); color: var(--ios-red); white-space: nowrap; }
.due-status-chip { font-size: 11px; font-weight: 600; padding: 2px 9px; border-radius: 8px; white-space: nowrap; }
.due-status-chip.active   { background: rgba(48,209,88,0.12);   color: var(--ios-green); }
.due-status-chip.inactive { background: rgba(142,142,147,0.12); color: var(--ios-gray);  }

.due-meta { font-size: 12px; color: var(--text-muted); display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.due-meta-sep { opacity: 0.4; }
.due-assign-chip { font-size: 11px; font-weight: 600; color: var(--ios-green); }
.due-assign-chip.partial { color: var(--ios-orange); }
.due-assign-chip.none    { color: var(--ios-gray); }

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
.ios-menu-modal, .ios-action-modal, .ios-confirm-sheet, .ios-form-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary);
    border-radius: 16px 16px 0 0; transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1); overflow: hidden;
}
.ios-menu-modal  { z-index: 9999;  max-height: 85vh; display: flex; flex-direction: column; }
.ios-action-modal  { z-index: 10000; max-height: 65vh; }
.ios-confirm-sheet { z-index: 10001; max-height: 55vh; }
.ios-form-sheet    { z-index: 10001; max-height: 75vh; display: flex; flex-direction: column; }
.ios-menu-modal.active, .ios-action-modal.active, .ios-confirm-sheet.active, .ios-form-sheet.active { transform: translateY(0); }

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
.ios-menu-item-icon.primary { background: rgba(34,197,94,0.15);  color: var(--ios-green); }
.ios-menu-item-icon.blue    { background: rgba(10,132,255,0.15); color: var(--ios-blue);  }
.ios-menu-item-icon.orange  { background: rgba(255,159,10,0.15); color: var(--ios-orange);}
.ios-menu-item-icon.purple  { background: rgba(191,90,242,0.15); color: var(--ios-purple);}
.ios-menu-item-content { flex: 1; min-width: 0; }
.ios-menu-item-label  { font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-menu-item-desc   { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
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
.ios-confirm-card { background: var(--bg-secondary); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.ios-confirm-card-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; }
.ios-confirm-card-desc  { font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5; }
.ios-form-btn { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; border: none; border-radius: 12px; font-size: 17px; font-weight: 600; text-align: center; cursor: pointer; transition: opacity 0.2s; font-family: inherit; color: white; }
.ios-form-btn:active { opacity: 0.8; }
.ios-form-btn.success { background: var(--ios-green);  }
.ios-form-btn.warning { background: var(--ios-orange); }
.ios-form-btn.primary { background: var(--ios-blue);   }
.ios-form-btn.danger  { background: var(--ios-red);    }

/* Assign form sheet */
.ios-form-body { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-info-card { background: var(--bg-secondary); border-radius: 12px; padding: 16px; margin-bottom: 20px; }
.ios-info-card-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px; }
.ios-info-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border-color); font-size: 14px; }
.ios-info-row:last-child { border-bottom: none; padding-bottom: 0; }
.ios-info-label { color: var(--text-secondary); }
.ios-info-value { font-weight: 600; color: var(--text-primary); }
.ios-field { margin-bottom: 16px; }
.ios-field-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px; }
.ios-field-input { width: 100%; padding: 13px 14px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-primary); font-size: 15px; outline: none; transition: border-color 0.2s; box-sizing: border-box; font-family: inherit; }
.ios-field-input:focus { border-color: var(--ios-blue); }
.ios-field-note { font-size: 12px; color: var(--text-muted); margin-top: 6px; line-height: 1.4; }

/* Responsive */
@media (max-width: 992px) { .ios-options-btn { display: flex; } }
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .content .stats-overview-grid { display: flex !important; flex-wrap: nowrap !important; overflow-x: auto !important; gap: 0.75rem !important; padding-bottom: 0.5rem !important; -webkit-overflow-scrolling: touch; }
    .content .stat-card  { flex: 0 0 auto !important; min-width: 150px !important; padding: var(--spacing-4); }
    .content .stat-icon  { width: 40px !important; height: 40px !important; font-size: 1.1rem; }
    .content .stat-value { font-size: 1.4rem; }
    .due-desc { display: none; }
}
</style>

<!-- Desktop Header -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="content-title"><i class="fas fa-file-invoice-dollar me-2"></i>Manage Dues</h1>
            <nav class="content-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Dues</li>
                </ol>
            </nav>
        </div>
        <div class="content-actions">
            <a href="<?php echo BASE_URL; ?>payments/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-list me-2"></i>All Payments
            </a>
            <a href="<?php echo BASE_URL; ?>payments/due-form.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create Due
            </a>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="stats-overview-grid">
    <a href="<?php echo BASE_URL; ?>payments/dues.php" class="stat-card-link">
        <div class="stat-card stat-primary">
            <div class="stat-icon"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-content">
                <div class="stat-label">Total Dues</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            </div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>payments/dues.php?status=active" class="stat-card-link">
        <div class="stat-card stat-success">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <div class="stat-label">Active</div>
                <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
            </div>
        </div>
    </a>
    <a href="<?php echo BASE_URL; ?>payments/dues.php?status=inactive" class="stat-card-link">
        <div class="stat-card stat-danger">
            <div class="stat-icon"><i class="fas fa-pause-circle"></i></div>
            <div class="stat-content">
                <div class="stat-label">Inactive</div>
                <div class="stat-value"><?php echo number_format($stats['inactive']); ?></div>
            </div>
        </div>
    </a>
</div>

<!-- Dues Section Card -->
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-icon green"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="ios-section-title">
            <h5><?php echo $status ? ucfirst($status) . ' Dues' : 'All Dues'; ?></h5>
            <p><?php echo number_format($total); ?> due<?php echo $total != 1 ? 's' : ''; ?><?php echo $search ? ' matching "' . e($search) . '"' : ''; ?></p>
        </div>
        <button class="ios-options-btn" onclick="openPageMenu()" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <!-- Search -->
    <div class="ios-search-wrap">
        <form method="GET" action="" class="ios-search-form" id="searchForm">
            <input type="hidden" name="status" value="<?php echo e($status); ?>">
            <div class="ios-search-input-wrap">
                <i class="fas fa-search ios-search-icon"></i>
                <input type="text" name="search" id="searchInput"
                       value="<?php echo e($search); ?>"
                       placeholder="Search by title or description…"
                       class="ios-search-input" autocomplete="off">
                <?php if ($search): ?>
                <a href="<?php echo BASE_URL; ?>payments/dues.php<?php echo $status ? '?status=' . urlencode($status) : ''; ?>"
                   class="ios-search-clear"><i class="fas fa-times-circle"></i></a>
                <?php endif; ?>
            </div>
            <button type="submit" class="ios-search-submit">Search</button>
        </form>
    </div>

    <!-- Status Filter Pills -->
    <div class="ios-filter-pills">
        <a href="<?php echo BASE_URL; ?>payments/dues.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>"
           class="ios-filter-pill <?php echo !$status ? 'active' : ''; ?>">
            All <span class="pill-count"><?php echo number_format($stats['total']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>payments/dues.php<?php echo buildDueQS($search, 'active'); ?>"
           class="ios-filter-pill green <?php echo $status === 'active' ? 'active' : ''; ?>">
            Active <span class="pill-count"><?php echo number_format($stats['active']); ?></span>
        </a>
        <a href="<?php echo BASE_URL; ?>payments/dues.php<?php echo buildDueQS($search, 'inactive'); ?>"
           class="ios-filter-pill red <?php echo $status === 'inactive' ? 'active' : ''; ?>">
            Inactive <span class="pill-count"><?php echo number_format($stats['inactive']); ?></span>
        </a>
    </div>

    <!-- List -->
    <?php if (empty($dues)): ?>
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <h3 class="ios-empty-title">No dues found</h3>
        <p class="ios-empty-desc">
            <?php echo ($search || $status) ? 'Try adjusting your search or filter.' : 'No dues have been created yet.'; ?>
        </p>
    </div>
    <?php else: ?>
    <?php foreach ($dues as $d):
        $isActive     = (bool)$d['is_active'];
        $hasDueDate   = !empty($d['due_date']);
        $paidCount    = (int)$d['paid_count'];
        $assignedCount = (int)$d['assigned_count'];
        $freqLbl      = freqLabel($d['frequency']);
        $freqClr      = freqColor($d['frequency']);
        $assignClass  = $assignedCount === 0 ? 'none' : ($paidCount >= $assignedCount ? '' : 'partial');
    ?>
    <div class="ios-due-item"
         data-id="<?php echo $d['id']; ?>"
         data-title="<?php echo e($d['title']); ?>"
         data-amount="<?php echo (float)$d['amount']; ?>"
         data-due-date="<?php echo e($d['due_date'] ?? ''); ?>"
         data-frequency="<?php echo e($freqLbl); ?>"
         data-is-active="<?php echo $isActive ? '1' : '0'; ?>"
         data-has-due-date="<?php echo $hasDueDate ? '1' : '0'; ?>"
         data-assigned-count="<?php echo $assignedCount; ?>">

        <div class="due-icon <?php echo $isActive ? 'active' : 'inactive'; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>

        <div class="due-content">
            <p class="due-title"><?php echo e($d['title']); ?></p>
            <?php if ($d['description']): ?>
            <p class="due-desc"><?php echo e(mb_strimwidth($d['description'], 0, 70, '…')); ?></p>
            <?php endif; ?>
            <div class="due-chips">
                <span class="due-freq-chip <?php echo $freqClr; ?>"><?php echo $freqLbl; ?></span>
                <span class="due-amount-chip"><?php echo formatCurrency((float)$d['amount']); ?></span>
                <?php if ($d['penalty_fee'] > 0): ?>
                <span class="due-penalty-chip">+<?php echo formatCurrency((float)$d['penalty_fee']); ?> penalty</span>
                <?php endif; ?>
                <span class="due-status-chip <?php echo $isActive ? 'active' : 'inactive'; ?>">
                    <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                </span>
            </div>
            <div class="due-meta">
                <?php if ($hasDueDate): ?>
                <span><i class="fas fa-calendar-day" style="font-size:10px;margin-right:3px"></i><?php echo formatDate($d['due_date']); ?></span>
                <span class="due-meta-sep">·</span>
                <?php endif; ?>
                <?php if ($assignedCount > 0): ?>
                <span class="due-assign-chip <?php echo $assignClass; ?>">
                    <?php echo $paidCount; ?>/<?php echo $assignedCount; ?> paid
                </span>
                <?php else: ?>
                <span class="due-assign-chip none">Not assigned</span>
                <?php endif; ?>
            </div>
        </div>

        <button class="ios-actions-btn"
                onclick="openActionSheet(this.closest('.ios-due-item'))"
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
        of <?php echo number_format($total); ?> dues
    </p>
    <div class="ios-pagination">
        <?php if ($paged['has_prev']): ?>
        <a href="<?php echo buildDueQS($search, $status, $page - 1); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($paged['total_pages'], $page + 2); $i++): ?>
        <a href="<?php echo buildDueQS($search, $status, $i); ?>"
           class="ios-page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($paged['has_next']): ?>
        <a href="<?php echo buildDueQS($search, $status, $page + 1); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ===== PAGE MENU SHEET ===== -->
<div class="ios-menu-backdrop" id="pageMenuBackdrop" onclick="closePageMenu()"></div>
<div class="ios-menu-modal" id="pageMenuSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Manage Dues</h3>
        <button class="ios-menu-close" onclick="closePageMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>payments/due-form.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-plus"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Create Due</span>
                            <span class="ios-menu-item-desc">Add a new membership due</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>payments/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">All Payments</span>
                            <span class="ios-menu-item-desc">View payment records</span>
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
                    <span class="ios-menu-stat-label">Total Dues</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['active']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Inactive</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['inactive']); ?></span>
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
        <p class="ios-action-modal-title" id="actionSheetTitle">Due</p>
        <p class="ios-action-modal-subtitle" id="actionSheetSub">Choose an action</p>
    </div>
    <div class="ios-action-modal-body" id="actionSheetBody"></div>
    <button class="ios-action-cancel" onclick="closeActionSheet()">Cancel</button>
</div>

<!-- ===== ASSIGN FORM SHEET ===== -->
<div class="ios-menu-backdrop" id="assignSheetBackdrop" onclick="closeAssignSheet()"></div>
<div class="ios-form-sheet" id="assignSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Assign to All Members</h3>
        <button class="ios-menu-close" onclick="closeAssignSheet()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-form-body">
        <div class="ios-info-card">
            <p class="ios-info-card-title" id="assignDueTitle">Due Title</p>
            <div class="ios-info-row">
                <span class="ios-info-label">Amount</span>
                <span class="ios-info-value" id="assignDueAmount">—</span>
            </div>
            <div class="ios-info-row">
                <span class="ios-info-label">Frequency</span>
                <span class="ios-info-value" id="assignDueFreq">—</span>
            </div>
        </div>
        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;line-height:1.5">
            This will create a pending payment record for every <strong style="color:var(--text-primary)">active</strong> member. Members already assigned this due for the selected date will be skipped.
        </p>
        <form method="POST" action="<?php echo BASE_URL; ?>payments/due-actions.php" id="assignForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="assign_all">
            <input type="hidden" name="id" id="assignDueId">
            <div class="ios-field">
                <div class="ios-field-label">Due Date <span style="color:var(--ios-red)">*</span></div>
                <input type="date" name="due_date" id="assignDueDate" class="ios-field-input" required>
                <p class="ios-field-note">Set the date by which members must pay this due.</p>
            </div>
            <button type="submit" class="ios-form-btn primary">
                <i class="fas fa-users" style="margin-right:8px"></i>Assign to All Members
            </button>
        </form>
    </div>
    <button class="ios-action-cancel" onclick="closeAssignSheet()">Cancel</button>
</div>

<!-- ===== CONFIRM SHEET (toggle + delete) ===== -->
<div class="ios-menu-backdrop" id="confirmSheetBackdrop" onclick="closeConfirmSheet()"></div>
<div class="ios-confirm-sheet" id="confirmSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title" id="confirmTitle">Confirm</h3>
        <button class="ios-menu-close" onclick="closeConfirmSheet()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-confirm-body">
        <div class="ios-confirm-card">
            <p class="ios-confirm-card-title" id="confirmDueTitle">Due Title</p>
            <p class="ios-confirm-card-desc" id="confirmDueDesc">Description</p>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>payments/due-actions.php" id="confirmForm">
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
    var baseUrl     = '<?php echo BASE_URL; ?>';
    var currentDue  = null;

    var confirmCfg = {
        activate:   { title: 'Activate Due',   desc: 'This due will be set to active and visible for payment.',          btnClass: 'success', btnText: 'Activate',    action: 'toggle' },
        deactivate: { title: 'Deactivate Due',  desc: 'This due will be hidden and no longer available for payment.',    btnClass: 'warning', btnText: 'Deactivate',  action: 'toggle' },
        delete:     { title: 'Delete Due',      desc: 'This due will be permanently deleted. This cannot be undone.',   btnClass: 'danger',  btnText: 'Delete Due',  action: 'delete' },
    };

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
        currentDue = {
            id: d.id, title: d.title, amount: d.amount,
            dueDate: d.dueDate, frequency: d.frequency,
            isActive: d.isActive === '1',
            hasDueDate: d.hasDueDate === '1',
            assignedCount: parseInt(d.assignedCount)
        };

        var t = currentDue.title.length > 48 ? currentDue.title.slice(0, 45) + '…' : currentDue.title;
        document.getElementById('actionSheetTitle').textContent = t;
        document.getElementById('actionSheetSub').textContent   = currentDue.frequency + ' · ' + (currentDue.isActive ? 'Active' : 'Inactive');

        var body = '';
        body += '<a href="' + baseUrl + 'payments/due-form.php?id=' + currentDue.id + '" class="ios-action-item primary"><i class="fas fa-edit"></i><span>Edit Due</span></a>';
        if (currentDue.hasDueDate) {
            body += '<button class="ios-action-item success" onclick="openAssignSheet()"><i class="fas fa-users"></i><span>Assign to All Members</span></button>';
        }
        if (currentDue.isActive) {
            body += '<button class="ios-action-item warning" onclick="openConfirmSheet(\'deactivate\')"><i class="fas fa-pause-circle"></i><span>Deactivate</span></button>';
        } else {
            body += '<button class="ios-action-item success" onclick="openConfirmSheet(\'activate\')"><i class="fas fa-play-circle"></i><span>Activate</span></button>';
        }
        if (currentDue.assignedCount === 0) {
            body += '<button class="ios-action-item danger" onclick="openConfirmSheet(\'delete\')"><i class="fas fa-trash"></i><span>Delete Due</span></button>';
        }

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

    // Assign Sheet
    var assignBackdrop = document.getElementById('assignSheetBackdrop');
    var assignSheet    = document.getElementById('assignSheet');

    window.openAssignSheet = function() {
        if (!currentDue) return;
        var fmtAmount = '₦' + parseFloat(currentDue.amount).toLocaleString('en-NG', { minimumFractionDigits: 2 });
        document.getElementById('assignDueId').value    = currentDue.id;
        document.getElementById('assignDueTitle').textContent  = currentDue.title;
        document.getElementById('assignDueAmount').textContent = fmtAmount;
        document.getElementById('assignDueFreq').textContent   = currentDue.frequency;
        document.getElementById('assignDueDate').value         = currentDue.dueDate || '';

        closeActionSheet();
        setTimeout(function() {
            assignBackdrop.classList.add('active');
            assignSheet.classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('assignDueDate').focus();
        }, 320);
    };
    window.closeAssignSheet = function() {
        assignBackdrop.classList.remove('active');
        assignSheet.classList.remove('active');
        document.body.style.overflow = '';
    };
    addSwipeClose(assignSheet, closeAssignSheet);

    // Confirm Sheet
    var confirmBackdrop = document.getElementById('confirmSheetBackdrop');
    var confirmSheet    = document.getElementById('confirmSheet');

    window.openConfirmSheet = function(type) {
        if (!currentDue) return;
        var cfg = confirmCfg[type];
        if (!cfg) return;

        var t = currentDue.title.length > 65 ? currentDue.title.slice(0, 62) + '…' : currentDue.title;
        document.getElementById('confirmTitle').textContent     = cfg.title;
        document.getElementById('confirmDueTitle').textContent  = t;
        document.getElementById('confirmDueDesc').textContent   = cfg.desc;
        document.getElementById('confirmAction').value          = cfg.action;
        document.getElementById('confirmId').value              = currentDue.id;

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
