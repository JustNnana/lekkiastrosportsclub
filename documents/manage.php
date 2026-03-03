<?php
/**
 * Documents — Admin management (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Document.php';

requireAdmin();

$pageTitle  = 'Manage Documents';
$docObj     = new Document();

$search   = sanitize($_GET['search']   ?? '');
$category = sanitize($_GET['category'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;

$total      = $docObj->countAll($search, $category);
$paged      = paginate($total, $perPage, $page);
$items      = $docObj->getAll($page, $perPage, $search, $category);
$stats      = $docObj->getStats();
$categories = $docObj->getCategories();

// Helper: file-type icon class + color class
function docIconInfo(string $mime): array {
    if (str_contains($mime, 'pdf'))                                        return ['fa-file-pdf',        'red'];
    if (str_contains($mime, 'word')  || str_contains($mime, 'document'))  return ['fa-file-word',       'blue'];
    if (str_contains($mime, 'excel') || str_contains($mime, 'sheet'))     return ['fa-file-excel',      'green'];
    if (str_contains($mime, 'powerpoint') || str_contains($mime, 'presentation')) return ['fa-file-powerpoint', 'orange'];
    if (str_contains($mime, 'image'))                                      return ['fa-file-image',      'teal'];
    return ['fa-file-alt', 'gray'];
}

// Build QS preserving search + category
function buildDocQS(string $search, string $category, int $page = 1): string {
    $p = [];
    if ($search)   $p['search']   = $search;
    if ($category) $p['category'] = $category;
    if ($page > 1) $p['page']     = $page;
    return $p ? '?' . http_build_query($p) : '';
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
    transition: var(--theme-transition);
}
.content .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); border-color: var(--primary); }
.content .stat-icon { width: 56px; height: 56px; border-radius: var(--border-radius); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; flex-shrink: 0; }
.content .stat-primary   .stat-icon { background: var(--primary); }
.content .stat-success   .stat-icon { background: var(--success); }
.content .stat-warning   .stat-icon { background: var(--warning); }
.content .stat-content { flex: 1; }
.content .stat-label  { font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--spacing-1); }
.content .stat-value  { font-size: 1.75rem; font-weight: var(--font-weight-bold); color: var(--text-primary); line-height: 1; margin-bottom: var(--spacing-2); }
.content .stat-detail { font-size: var(--font-size-xs); color: var(--text-secondary); }

/* iOS Section Card */
.ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: var(--spacing-4); }
.ios-section-header { display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-4); background: var(--bg-subtle); border-bottom: 1px solid var(--border-color); }
.ios-section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.ios-section-icon.blue   { background: rgba(10,132,255,0.15);  color: var(--ios-blue);   }
.ios-section-icon.purple { background: rgba(191,90,242,0.15);  color: var(--ios-purple); }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0 0; }

/* Mobile 3-dot */
.ios-options-btn { display: none; width: 36px; height: 36px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(0.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* Search box */
.ios-search-wrap { padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-search-form { display: flex; align-items: center; gap: 8px; }
.ios-search-input-wrap { position: relative; flex: 1; }
.ios-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; pointer-events: none; }
.ios-search-input { width: 100%; padding: 9px 36px 9px 34px; border-radius: 12px; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 14px; outline: none; transition: border-color 0.2s; }
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
.ios-filter-pill .count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.2); }
.ios-filter-pill:not(.active) .count { background: var(--border-color); color: var(--text-muted); }

/* Document Item */
.ios-doc-item { display: flex; align-items: center; gap: 12px; padding: 13px 16px; background: var(--bg-primary); cursor: pointer; transition: background 0.15s; border-bottom: 1px solid var(--border-color); }
.ios-doc-item:last-child { border-bottom: none; }
.ios-doc-item:hover  { background: rgba(255,255,255,0.03); }
.ios-doc-item:active { background: rgba(255,255,255,0.06); }

.ios-doc-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
.ios-doc-icon.red    { background: rgba(255,69,58,0.12);   color: #ff6b63; }
.ios-doc-icon.blue   { background: rgba(10,132,255,0.12);  color: var(--ios-blue); }
.ios-doc-icon.green  { background: rgba(48,209,88,0.12);   color: var(--ios-green); }
.ios-doc-icon.orange { background: rgba(255,159,10,0.12);  color: var(--ios-orange); }
.ios-doc-icon.teal   { background: rgba(100,210,255,0.12); color: var(--ios-teal); }
.ios-doc-icon.gray   { background: rgba(142,142,147,0.12); color: var(--ios-gray); }

.ios-doc-content { flex: 1; min-width: 0; }
.ios-doc-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-doc-sub   { font-size: 12px; color: var(--text-muted); margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-doc-chips { display: flex; gap: 5px; flex-wrap: wrap; align-items: center; }
.ios-doc-cat-chip {
    font-size: 11px; font-weight: 500; padding: 2px 8px; border-radius: 8px;
    background: rgba(191,90,242,0.12); color: var(--ios-purple); white-space: nowrap;
}
.ios-doc-ext-chip {
    font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 6px;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    color: var(--text-muted); text-transform: uppercase;
}

.ios-doc-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; flex-shrink: 0; }
.ios-doc-size-chip { font-size: 12px; font-weight: 600; color: var(--ios-blue); background: rgba(10,132,255,0.1); padding: 3px 8px; border-radius: 10px; white-space: nowrap; }
.ios-doc-dl-chip   { font-size: 11px; color: var(--text-muted); white-space: nowrap; }

.ios-actions-btn { width: 32px; height: 32px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.15s; flex-shrink: 0; }
.ios-actions-btn:hover  { background: var(--border-color); }
.ios-actions-btn:active { transform: scale(0.95); }
.ios-actions-btn i { color: var(--text-primary); font-size: 14px; }

/* Empty State */
.ios-empty-state { text-align: center; padding: 48px 24px; }
.ios-empty-icon  { font-size: 56px; opacity: 0.35; margin-bottom: 16px; }
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

/* Shared sheets */
.ios-menu-modal, .ios-action-modal, .ios-confirm-sheet, .ios-edit-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary);
    border-radius: 16px 16px 0 0; transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1); overflow: hidden;
}
.ios-menu-modal    { z-index: 9999;  max-height: 85vh; display: flex; flex-direction: column; }
.ios-action-modal  { z-index: 10000; max-height: 65vh; }
.ios-confirm-sheet { z-index: 10001; max-height: 55vh; }
.ios-edit-sheet    { z-index: 10002; max-height: 80vh; display: flex; flex-direction: column; }
.ios-menu-modal.active, .ios-action-modal.active, .ios-confirm-sheet.active, .ios-edit-sheet.active { transform: translateY(0); }

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
.ios-menu-item-content  { flex: 1; min-width: 0; }
.ios-menu-item-label    { font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-menu-item-desc     { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron  { color: var(--text-muted); font-size: 12px; }
.ios-menu-stat-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 15px; color: var(--text-primary); }
.ios-menu-stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); }

/* Action sheet items */
.ios-action-modal-header   { padding: 16px; border-bottom: 1px solid var(--border-color); text-align: center; }
.ios-action-modal-title    { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px; }
.ios-action-modal-subtitle { font-size: 13px; color: var(--text-secondary); margin: 0; }
.ios-action-modal-body     { padding: 8px; overflow-y: auto; }
.ios-action-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px; text-decoration: none; color: var(--text-primary); transition: background 0.15s; cursor: pointer; border: none; background: none; width: 100%; text-align: left; font-family: inherit; font-size: inherit; }
.ios-action-item:active  { background: var(--bg-secondary); }
.ios-action-item i       { width: 24px; font-size: 18px; }
.ios-action-item.success { color: var(--ios-green);  }
.ios-action-item.primary { color: var(--ios-blue);   }
.ios-action-item.danger  { color: var(--ios-red);    }
.ios-action-cancel { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; background: var(--bg-secondary); border: none; border-radius: 12px; font-size: 17px; font-weight: 600; color: var(--ios-blue); text-align: center; cursor: pointer; transition: background 0.15s; font-family: inherit; }
.ios-action-cancel:active { background: var(--border-color); }

/* Confirm sheet */
.ios-confirm-body { padding: 20px 16px 8px; overflow-y: auto; }
.ios-confirm-doc-card { background: var(--bg-secondary); border-radius: 12px; padding: 16px; margin-bottom: 16px; }
.ios-confirm-doc-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; }
.ios-confirm-doc-desc  { font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5; }
.ios-form-btn { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; border: none; border-radius: 12px; font-size: 17px; font-weight: 600; text-align: center; cursor: pointer; transition: opacity 0.2s; font-family: inherit; color: white; }
.ios-form-btn:active  { opacity: 0.8; }
.ios-form-btn.danger  { background: var(--ios-red);  }
.ios-form-btn.primary { background: var(--ios-blue); }

/* Edit sheet */
.ios-edit-body { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-field { margin-bottom: 16px; }
.ios-field-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.4px; margin-bottom: 8px; }
.ios-field-input { width: 100%; padding: 13px 14px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-primary); font-size: 15px; outline: none; transition: border-color 0.2s; box-sizing: border-box; font-family: inherit; }
.ios-field-input:focus { border-color: var(--ios-blue); }

/* Responsive */
@media (max-width: 992px) { .ios-options-btn { display: flex; } }
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .content .stats-overview-grid { display: flex !important; flex-wrap: nowrap !important; overflow-x: auto !important; gap: 0.75rem !important; padding-bottom: 0.5rem !important; -webkit-overflow-scrolling: touch; }
    .content .stat-card  { flex: 0 0 auto !important; min-width: 155px !important; padding: var(--spacing-4); }
    .content .stat-icon  { width: 40px !important; height: 40px !important; font-size: var(--font-size-lg); }
    .content .stat-value { font-size: 1.5rem; }
    .ios-doc-sub   { display: none; }
}
</style>

<!-- Content Header (desktop) -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h1 class="content-title"><i class="fas fa-folder-open me-2"></i>Documents</h1>
            <nav class="content-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Documents</li>
                </ol>
            </nav>
        </div>
        <div class="content-actions">
            <a href="<?php echo BASE_URL; ?>documents/index.php" class="btn btn-outline-secondary">
                <i class="fas fa-folder me-2"></i>View Documents
            </a>
            <a href="<?php echo BASE_URL; ?>documents/upload.php" class="btn btn-primary">
                <i class="fas fa-upload me-2"></i>Upload Document
            </a>
        </div>
    </div>
</div>

<!-- Stats -->
<div class="stats-overview-grid">
    <div class="stat-card stat-primary">
        <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Files</div>
            <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-detail">All documents</div>
        </div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-icon"><i class="fas fa-download"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Downloads</div>
            <div class="stat-value"><?php echo number_format($stats['total_downloads']); ?></div>
            <div class="stat-detail">All time</div>
        </div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-icon"><i class="fas fa-hdd"></i></div>
        <div class="stat-content">
            <div class="stat-label">Storage Used</div>
            <div class="stat-value"><?php echo Document::formatSize((int)$stats['total_size']); ?></div>
            <div class="stat-detail">Uploaded files</div>
        </div>
    </div>
</div>

<!-- Documents Section Card -->
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-icon blue"><i class="fas fa-folder-open"></i></div>
        <div class="ios-section-title">
            <h5><?php echo $category ? e($category) : ($search ? 'Search Results' : 'All Documents'); ?></h5>
            <p><?php echo number_format($total); ?> file<?php echo $total != 1 ? 's' : ''; ?><?php echo $search ? ' matching "' . e($search) . '"' : ''; ?></p>
        </div>
        <button class="ios-options-btn" onclick="openPageMenu()" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <!-- Search box -->
    <div class="ios-search-wrap">
        <form method="GET" action="" class="ios-search-form" id="searchForm">
            <input type="hidden" name="category" value="<?php echo e($category); ?>">
            <div class="ios-search-input-wrap">
                <i class="fas fa-search ios-search-icon"></i>
                <input type="text" name="search" id="searchInput"
                       value="<?php echo e($search); ?>"
                       placeholder="Search by title or category…"
                       class="ios-search-input"
                       autocomplete="off">
                <?php if ($search): ?>
                <a href="<?php echo BASE_URL; ?>documents/manage.php<?php echo $category ? '?category=' . urlencode($category) : ''; ?>"
                   class="ios-search-clear"><i class="fas fa-times-circle"></i></a>
                <?php endif; ?>
            </div>
            <button type="submit" class="ios-search-submit">Search</button>
        </form>
    </div>

    <!-- Category Filter Pills -->
    <?php if (!empty($categories)): ?>
    <div class="ios-filter-pills">
        <a href="<?php echo BASE_URL; ?>documents/manage.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>"
           class="ios-filter-pill <?php echo !$category ? 'active' : ''; ?>">
            All <span class="count"><?php echo number_format($stats['total']); ?></span>
        </a>
        <?php foreach ($categories as $cat => $cnt): ?>
        <a href="<?php echo BASE_URL; ?>documents/manage.php<?php echo buildDocQS($search, $cat); ?>"
           class="ios-filter-pill <?php echo $category === $cat ? 'active' : ''; ?>">
            <?php echo e($cat); ?> <span class="count"><?php echo number_format($cnt); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-folder-open"></i></div>
        <h3 class="ios-empty-title">No documents found</h3>
        <p class="ios-empty-description">
            <?php if ($search || $category): ?>
                Try adjusting your search or filter.
            <?php else: ?>
                No documents have been uploaded yet.
            <?php endif; ?>
        </p>
        <a href="<?php echo BASE_URL; ?>documents/upload.php" class="btn btn-primary btn-sm">
            <i class="fas fa-upload me-1"></i>Upload First Document
        </a>
    </div>

    <?php else: ?>
    <div id="docsList">
        <?php foreach ($items as $d):
            $mime = $d['mime_type'] ?? '';
            [$iconFa, $iconColor] = docIconInfo($mime);
            $ext  = strtoupper(pathinfo($d['file_path'], PATHINFO_EXTENSION));
        ?>
        <div class="ios-doc-item"
             data-id="<?php echo $d['id']; ?>"
             data-title="<?php echo e($d['title']); ?>"
             data-category="<?php echo e($d['category'] ?? ''); ?>"
             data-ext="<?php echo e($ext); ?>"
             data-size="<?php echo e(Document::formatSize((int)$d['file_size'])); ?>"
             data-downloads="<?php echo (int)$d['downloads']; ?>"
             onclick="openActionSheet(this)">
            <div class="ios-doc-icon <?php echo $iconColor; ?>">
                <i class="fas <?php echo $iconFa; ?>"></i>
            </div>
            <div class="ios-doc-content">
                <p class="ios-doc-title"><?php echo e($d['title']); ?></p>
                <p class="ios-doc-sub">
                    <?php echo e($d['uploader_name'] ?: $d['uploader_email']); ?>
                    &nbsp;·&nbsp;
                    <?php echo formatDate($d['created_at'], 'd M Y'); ?>
                </p>
                <div class="ios-doc-chips">
                    <span class="ios-doc-ext-chip"><?php echo e($ext); ?></span>
                    <?php if ($d['category']): ?>
                    <span class="ios-doc-cat-chip"><?php echo e($d['category']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ios-doc-meta">
                <span class="ios-doc-size-chip"><?php echo Document::formatSize((int)$d['file_size']); ?></span>
                <span class="ios-doc-dl-chip">
                    <i class="fas fa-download" style="font-size:9px;margin-right:2px"></i><?php echo number_format($d['downloads']); ?>
                </span>
            </div>
            <button class="ios-actions-btn" onclick="event.stopPropagation(); openActionSheet(this.closest('.ios-doc-item'))" aria-label="Actions">
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
        of <?php echo number_format($total); ?> documents
    </div>
    <div class="ios-pagination">
        <?php if ($paged['has_prev']): ?>
        <a href="<?php echo buildDocQS($search, $category, $page - 1); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = max(1, $page - 2); $i <= min($paged['total_pages'], $page + 2); $i++): ?>
        <a href="<?php echo buildDocQS($search, $category, $i); ?>" class="ios-page-btn <?php echo $i === $page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        <?php if ($paged['has_next']): ?>
        <a href="<?php echo buildDocQS($search, $category, $page + 1); ?>" class="ios-page-btn">
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
        <h3 class="ios-menu-title">Documents</h3>
        <button class="ios-menu-close" onclick="closePageMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>documents/upload.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-upload"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Upload Document</span>
                            <span class="ios-menu-item-desc">Add a new file</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>documents/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-folder"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">View Documents</span>
                            <span class="ios-menu-item-desc">See files as members do</span>
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
                    <span class="ios-menu-stat-label">Total Files</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Downloads</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total_downloads']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Storage Used</span>
                    <span class="ios-menu-stat-value"><?php echo Document::formatSize((int)$stats['total_size']); ?></span>
                </div>
            </div>
        </div>
        <?php if (!empty($categories)): ?>
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter by Category</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>documents/manage.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label">All (<?php echo number_format($stats['total']); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php foreach ($categories as $cat => $cnt): ?>
                <a href="<?php echo BASE_URL; ?>documents/manage.php<?php echo buildDocQS($search, $cat); ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-tag"></i></div>
                        <div class="ios-menu-item-content"><span class="ios-menu-item-label"><?php echo e($cat); ?> (<?php echo number_format($cnt); ?>)</span></div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ===== PER-ITEM ACTION SHEET ===== -->
<div class="ios-menu-backdrop" id="actionSheetBackdrop" onclick="closeActionSheet()"></div>
<div class="ios-action-modal" id="actionSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-action-modal-header">
        <p class="ios-action-modal-title" id="actionSheetTitle">Document</p>
        <p class="ios-action-modal-subtitle" id="actionSheetSub">Choose an action</p>
    </div>
    <div class="ios-action-modal-body">
        <div id="actionSheetDownloadRow"></div>
        <button class="ios-action-item primary" onclick="openEditSheet()">
            <i class="fas fa-edit"></i><span>Edit Details</span>
        </button>
        <button class="ios-action-item danger" onclick="openConfirmSheet()">
            <i class="fas fa-trash"></i><span>Delete File</span>
        </button>
    </div>
    <button class="ios-action-cancel" onclick="closeActionSheet()">Cancel</button>
</div>

<!-- ===== EDIT SHEET ===== -->
<div class="ios-menu-backdrop" id="editSheetBackdrop" onclick="closeEditSheet()"></div>
<div class="ios-edit-sheet" id="editSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Edit Document</h3>
        <button class="ios-menu-close" onclick="closeEditSheet()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-edit-body">
        <form method="POST" action="<?php echo BASE_URL; ?>documents/actions.php" id="editForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="ios-field">
                <div class="ios-field-label">Title</div>
                <input type="text" name="title" id="editTitle" class="ios-field-input" required placeholder="Document title">
            </div>
            <div class="ios-field">
                <div class="ios-field-label">Category</div>
                <input type="text" name="category" id="editCategory" class="ios-field-input"
                       list="categoryList" placeholder="e.g. Rules, Finance, Policies">
                <datalist id="categoryList">
                    <?php foreach ($categories as $cat => $n): ?>
                    <option value="<?php echo e($cat); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <button type="submit" class="ios-form-btn primary">Save Changes</button>
        </form>
    </div>
    <button class="ios-action-cancel" onclick="closeEditSheet()">Cancel</button>
</div>

<!-- ===== DELETE CONFIRM SHEET ===== -->
<div class="ios-menu-backdrop" id="confirmSheetBackdrop" onclick="closeConfirmSheet()"></div>
<div class="ios-confirm-sheet" id="confirmSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Delete Document</h3>
        <button class="ios-menu-close" onclick="closeConfirmSheet()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-confirm-body">
        <div class="ios-confirm-doc-card">
            <p class="ios-confirm-doc-title" id="confirmDocTitle">Document title</p>
            <p class="ios-confirm-doc-desc">The file will be permanently removed and cannot be recovered.</p>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>documents/actions.php" id="confirmForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="confirmId">
            <button type="submit" class="ios-form-btn danger">Delete Permanently</button>
        </form>
    </div>
    <button class="ios-action-cancel" onclick="closeConfirmSheet()">Cancel</button>
</div>

<script>
(function () {
    var baseUrl    = '<?php echo BASE_URL; ?>';
    var currentDoc = null;

    // ─── Swipe-to-close ──────────────────────────────────────────
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
        currentDoc = { id: d.id, title: d.title, category: d.category, ext: d.ext, size: d.size, downloads: d.downloads };

        var t = currentDoc.title.length > 55 ? currentDoc.title.slice(0, 52) + '…' : currentDoc.title;
        document.getElementById('actionSheetTitle').textContent = t;
        document.getElementById('actionSheetSub').textContent   = currentDoc.ext + ' · ' + currentDoc.size + ' · ' + currentDoc.downloads + ' downloads';

        // Download link row
        document.getElementById('actionSheetDownloadRow').innerHTML =
            '<a href="' + baseUrl + 'documents/download.php?id=' + d.id + '" class="ios-action-item success">' +
            '<i class="fas fa-download"></i><span>Download</span></a>';

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

    // ─── Edit Sheet ───────────────────────────────────────────────
    var editBackdrop = document.getElementById('editSheetBackdrop');
    var editSheet    = document.getElementById('editSheet');

    window.openEditSheet = function() {
        if (!currentDoc) return;
        document.getElementById('editId').value       = currentDoc.id;
        document.getElementById('editTitle').value    = currentDoc.title;
        document.getElementById('editCategory').value = currentDoc.category;

        closeActionSheet();
        setTimeout(function() {
            editBackdrop.classList.add('active');
            editSheet.classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('editTitle').focus();
        }, 320);
    };
    window.closeEditSheet = function() {
        editBackdrop.classList.remove('active');
        editSheet.classList.remove('active');
        document.body.style.overflow = '';
    };
    addSwipeClose(editSheet, closeEditSheet);

    // ─── Confirm Sheet ────────────────────────────────────────────
    var confirmBackdrop = document.getElementById('confirmSheetBackdrop');
    var confirmSheet    = document.getElementById('confirmSheet');

    window.openConfirmSheet = function() {
        if (!currentDoc) return;
        var t = currentDoc.title.length > 80 ? currentDoc.title.slice(0, 77) + '…' : currentDoc.title;
        document.getElementById('confirmDocTitle').textContent = t;
        document.getElementById('confirmId').value            = currentDoc.id;

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
