<?php
/**
 * Documents — Member view (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Document.php';

requireLogin();

$pageTitle  = 'Documents';
$docObj     = new Document();
$search     = sanitize($_GET['search']   ?? '');
$category   = sanitize($_GET['category'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;

$total      = $docObj->countAll($search, $category);
$paged      = paginate($total, $perPage, $page);
$items      = $docObj->getAll($page, $perPage, $search, $category);
$categories = $docObj->getCategories();
$stats      = $docObj->getStats();

// File-type icon + color
function docIconInfo(string $mime): array {
    if (str_contains($mime, 'pdf'))                                        return ['fa-file-pdf',        'red'];
    if (str_contains($mime, 'word')  || str_contains($mime, 'document'))  return ['fa-file-word',       'blue'];
    if (str_contains($mime, 'excel') || str_contains($mime, 'sheet'))     return ['fa-file-excel',      'green'];
    if (str_contains($mime, 'powerpoint') || str_contains($mime, 'presentation')) return ['fa-file-powerpoint', 'orange'];
    if (str_contains($mime, 'image'))                                      return ['fa-file-image',      'teal'];
    return ['fa-file-alt', 'gray'];
}

// Build query string preserving search + category
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

/* ── Page Header ─────────────────────────────────────────── */
.docs-page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 24px;
}
.docs-page-title {
    font-size: 26px; font-weight: 700; color: var(--text-primary);
    margin: 0 0 4px; display: flex; align-items: center; gap: 10px;
}
.docs-page-title-icon {
    width: 42px; height: 42px; border-radius: 12px;
    background: rgba(10,132,255,0.15); color: var(--ios-blue);
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; flex-shrink: 0;
}
.docs-page-sub { font-size: 14px; color: var(--text-secondary); margin: 0; }
.docs-page-actions { display: flex; gap: 8px; flex-shrink: 0; align-items: flex-start; }
.docs-header-menu-btn {
    display: none; width: 38px; height: 38px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    align-items: center; justify-content: center; cursor: pointer;
    transition: background 0.2s, transform 0.15s; flex-shrink: 0;
}
.docs-header-menu-btn:hover  { background: var(--border-color); }
.docs-header-menu-btn:active { transform: scale(0.95); }
.docs-header-menu-btn i { color: var(--text-primary); font-size: 16px; }

/* ── Search ──────────────────────────────────────────────── */
.docs-search-section { margin-bottom: 16px; }
.docs-search-form { display: flex; align-items: center; gap: 8px; }
.docs-search-input-wrap { position: relative; flex: 1; }
.docs-search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 13px; pointer-events: none; }
.docs-search-input { width: 100%; padding: 11px 36px 11px 36px; border-radius: 14px; background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-primary); font-size: 14px; outline: none; transition: border-color 0.2s; }
.docs-search-input:focus { border-color: var(--ios-blue); }
.docs-search-input::placeholder { color: var(--text-muted); }
.docs-search-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; text-decoration: none; }
.docs-search-clear:hover { color: var(--text-primary); }
.docs-search-submit { padding: 11px 20px; border-radius: 14px; background: var(--ios-blue); border: none; color: white; font-size: 14px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: opacity 0.2s; }
.docs-search-submit:active { opacity: 0.8; }

/* ── Filter Pills ────────────────────────────────────────── */
.docs-filter-pills { display: flex; gap: 8px; margin-bottom: 20px; overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 2px; }
.docs-filter-pill { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: all 0.2s; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); }
.docs-filter-pill:hover  { background: var(--border-color); color: var(--text-primary); }
.docs-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.docs-filter-pill .pill-count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.25); }
.docs-filter-pill:not(.active) .pill-count { background: var(--border-color); color: var(--text-muted); }

/* ── Document Card Grid ──────────────────────────────────── */
.docs-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.doc-card {
    background: var(--bg-primary); border: 1px solid var(--border-color);
    border-radius: 16px; overflow: hidden; display: flex; flex-direction: column;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    cursor: default;
}
.doc-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.12); border-color: rgba(10,132,255,0.4); }

.doc-card-icon-area { padding: 28px 20px 18px; display: flex; justify-content: center; }
.doc-card-icon-wrap {
    width: 68px; height: 68px; border-radius: 18px;
    display: flex; align-items: center; justify-content: center; font-size: 32px;
}
.doc-card-icon-wrap.red    { background: rgba(255,69,58,0.12);   color: #ff6b63; }
.doc-card-icon-wrap.blue   { background: rgba(10,132,255,0.12);  color: var(--ios-blue); }
.doc-card-icon-wrap.green  { background: rgba(48,209,88,0.12);   color: var(--ios-green); }
.doc-card-icon-wrap.orange { background: rgba(255,159,10,0.12);  color: var(--ios-orange); }
.doc-card-icon-wrap.teal   { background: rgba(100,210,255,0.12); color: var(--ios-teal); }
.doc-card-icon-wrap.gray   { background: rgba(142,142,147,0.12); color: var(--ios-gray); }

.doc-card-body { padding: 0 16px 14px; flex: 1; display: flex; flex-direction: column; }
.doc-card-title {
    font-size: 14px; font-weight: 600; color: var(--text-primary);
    margin: 0 0 8px; line-height: 1.35;
    display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.doc-card-chips { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 8px; }
.doc-ext-chip {
    font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 6px;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.3px;
}
.doc-cat-chip {
    font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 8px;
    background: rgba(191,90,242,0.12); color: var(--ios-purple);
}
.doc-card-size { font-size: 12px; color: var(--text-muted); margin-top: auto; padding-top: 4px; }

.doc-card-footer { padding: 12px 16px; border-top: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.doc-dl-count { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 4px; white-space: nowrap; flex-shrink: 0; }
.doc-dl-btn {
    display: inline-flex; align-items: center; gap: 5px; padding: 8px 14px;
    border-radius: 10px; background: var(--ios-blue); color: white;
    text-decoration: none; font-size: 13px; font-weight: 600;
    transition: opacity 0.2s; white-space: nowrap;
}
.doc-dl-btn:hover  { opacity: 0.85; color: white; }
.doc-dl-btn:active { opacity: 0.7; }

/* ── Empty State ─────────────────────────────────────────── */
.docs-empty {
    text-align: center; padding: 64px 24px;
    background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px;
}
.docs-empty-icon { font-size: 64px; opacity: 0.3; margin-bottom: 16px; }
.docs-empty-title { font-size: 20px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px; }
.docs-empty-sub { font-size: 14px; color: var(--text-secondary); margin: 0; line-height: 1.5; }

/* ── Pagination ──────────────────────────────────────────── */
.ios-pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 6px; }
.ios-page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 14px; font-weight: 500; text-decoration: none; color: var(--text-secondary); background: var(--bg-secondary); border: 1px solid var(--border-color); transition: all 0.2s; }
.ios-page-btn:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-page-btn.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-pagination-info { font-size: 12px; color: var(--text-muted); text-align: center; margin-bottom: 20px; }

/* ── Admin Page Menu Sheet ───────────────────────────────── */
.ios-menu-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal { position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary); border-radius: 16px 16px 0 0; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.32,0.72,0,1); z-index: 9999; max-height: 85vh; display: flex; flex-direction: column; overflow: hidden; }
.ios-menu-modal.active { transform: translateY(0); }
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
.ios-menu-item-icon.primary { background: rgba(48,209,88,0.15);  color: var(--ios-green);  }
.ios-menu-item-icon.blue    { background: rgba(10,132,255,0.15); color: var(--ios-blue);   }
.ios-menu-item-icon.orange  { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple  { background: rgba(191,90,242,0.15); color: var(--ios-purple); }
.ios-menu-item-content { flex: 1; min-width: 0; }
.ios-menu-item-label  { font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-menu-item-desc   { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }
.ios-menu-stat-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 15px; color: var(--text-primary); }
.ios-menu-stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); }

/* ── Responsive ──────────────────────────────────────────── */
@media (max-width: 1199px) { .docs-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 768px) {
    .docs-page-actions { display: none; }
    .docs-header-menu-btn { display: flex; }
    .docs-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .doc-card-icon-wrap { width: 56px; height: 56px; font-size: 26px; border-radius: 14px; }
    .doc-card-icon-area { padding: 20px 14px 14px; }
    .doc-card-body { padding: 0 14px 12px; }
    .doc-card-footer { padding: 10px 14px; }
}
@media (max-width: 400px) {
    .doc-dl-count { display: none; }
    .doc-dl-btn { padding: 8px 12px; font-size: 12px; }
}
</style>

<!-- ── Page Header ──────────────────────────────────────────── -->
<div class="docs-page-header">
    <div>
        <h1 class="docs-page-title">
            <span class="docs-page-title-icon"><i class="fas fa-folder-open"></i></span>
            Documents
        </h1>
        <p class="docs-page-sub">Club resources, policies, and guides.</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="docs-page-actions">
        <a href="<?php echo BASE_URL; ?>documents/upload.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-upload me-1"></i>Upload
        </a>
        <a href="<?php echo BASE_URL; ?>documents/manage.php" class="btn btn-primary btn-sm">
            <i class="fas fa-cog me-1"></i>Manage
        </a>
    </div>
    <button class="docs-header-menu-btn" onclick="openPageMenu()" aria-label="Open menu">
        <i class="fas fa-ellipsis-v"></i>
    </button>
    <?php endif; ?>
</div>

<!-- ── Search ──────────────────────────────────────────────── -->
<div class="docs-search-section">
    <form method="GET" action="" class="docs-search-form">
        <input type="hidden" name="category" value="<?php echo e($category); ?>">
        <div class="docs-search-input-wrap">
            <i class="fas fa-search docs-search-icon"></i>
            <input type="text" name="search" value="<?php echo e($search); ?>"
                   placeholder="Search documents…" class="docs-search-input" autocomplete="off">
            <?php if ($search): ?>
            <a href="<?php echo BASE_URL; ?>documents/index.php<?php echo $category ? '?category=' . urlencode($category) : ''; ?>"
               class="docs-search-clear"><i class="fas fa-times-circle"></i></a>
            <?php endif; ?>
        </div>
        <button type="submit" class="docs-search-submit">
            <i class="fas fa-search me-1"></i>Search
        </button>
    </form>
</div>

<!-- ── Category Filter Pills ───────────────────────────────── -->
<?php if (!empty($categories)): ?>
<div class="docs-filter-pills">
    <a href="<?php echo BASE_URL; ?>documents/index.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>"
       class="docs-filter-pill <?php echo !$category ? 'active' : ''; ?>">
        All <span class="pill-count"><?php echo number_format($stats['total']); ?></span>
    </a>
    <?php foreach ($categories as $cat => $cnt): ?>
    <a href="<?php echo BASE_URL; ?>documents/index.php<?php echo buildDocQS($search, $cat); ?>"
       class="docs-filter-pill <?php echo $category === $cat ? 'active' : ''; ?>">
        <?php echo e($cat); ?> <span class="pill-count"><?php echo number_format($cnt); ?></span>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Document Grid ────────────────────────────────────────── -->
<?php if (empty($items)): ?>
<div class="docs-empty">
    <div class="docs-empty-icon"><i class="fas fa-folder-open"></i></div>
    <h3 class="docs-empty-title">No documents found</h3>
    <p class="docs-empty-sub">
        <?php echo ($search || $category)
            ? 'Try adjusting your search or filter.'
            : 'No documents have been uploaded yet. Check back soon.'; ?>
    </p>
</div>

<?php else: ?>
<div class="docs-grid">
    <?php foreach ($items as $d):
        $mime = $d['mime_type'] ?? '';
        [$iconFa, $iconColor] = docIconInfo($mime);
        $ext  = strtoupper(pathinfo($d['file_path'], PATHINFO_EXTENSION));
    ?>
    <div class="doc-card">
        <div class="doc-card-icon-area">
            <div class="doc-card-icon-wrap <?php echo $iconColor; ?>">
                <i class="fas <?php echo $iconFa; ?>"></i>
            </div>
        </div>
        <div class="doc-card-body">
            <h3 class="doc-card-title"><?php echo e($d['title']); ?></h3>
            <div class="doc-card-chips">
                <span class="doc-ext-chip"><?php echo e($ext); ?></span>
                <?php if ($d['category']): ?>
                <span class="doc-cat-chip"><?php echo e($d['category']); ?></span>
                <?php endif; ?>
            </div>
            <p class="doc-card-size"><?php echo Document::formatSize((int)$d['file_size']); ?></p>
        </div>
        <div class="doc-card-footer">
            <span class="doc-dl-count">
                <i class="fas fa-download" style="font-size:10px"></i>
                <?php echo number_format($d['downloads']); ?>
            </span>
            <a href="<?php echo BASE_URL; ?>documents/download.php?id=<?php echo $d['id']; ?>"
               class="doc-dl-btn">
                <i class="fas fa-download"></i>Download
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($paged['total_pages'] > 1): ?>
<p class="ios-pagination-info">
    Showing <?php echo number_format(($page - 1) * $perPage + 1); ?>–<?php echo number_format(min($page * $perPage, $total)); ?>
    of <?php echo number_format($total); ?> documents
</p>
<div class="ios-pagination">
    <?php if ($paged['has_prev']): ?>
    <a href="<?php echo buildDocQS($search, $category, $page - 1); ?>" class="ios-page-btn">
        <i class="fas fa-chevron-left"></i>
    </a>
    <?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($paged['total_pages'], $page + 2); $i++): ?>
    <a href="<?php echo buildDocQS($search, $category, $i); ?>"
       class="ios-page-btn <?php echo $i === $page ? 'active' : ''; ?>">
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
<?php endif; ?>

<!-- ── Admin Mobile Menu Sheet ─────────────────────────────── -->
<?php if (isAdmin()): ?>
<div class="ios-menu-backdrop" id="pageMenuBackdrop" onclick="closePageMenu()"></div>
<div class="ios-menu-modal" id="pageMenuSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Documents</h3>
        <button class="ios-menu-close" onclick="closePageMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Admin Actions</div>
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
                <a href="<?php echo BASE_URL; ?>documents/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-cog"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Manage Documents</span>
                            <span class="ios-menu-item-desc">Edit, delete, organise files</span>
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
                    <span class="ios-menu-stat-label">Total Downloads</span>
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
                <a href="<?php echo BASE_URL; ?>documents/index.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">All (<?php echo number_format($stats['total']); ?>)</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php foreach ($categories as $cat => $cnt): ?>
                <a href="<?php echo BASE_URL; ?>documents/index.php<?php echo buildDocQS($search, $cat); ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-tag"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label"><?php echo e($cat); ?> (<?php echo number_format($cnt); ?>)</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
(function () {
    var backdrop = document.getElementById('pageMenuBackdrop');
    var sheet    = document.getElementById('pageMenuSheet');

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

    window.openPageMenu  = function() { backdrop.classList.add('active'); sheet.classList.add('active'); document.body.style.overflow = 'hidden'; };
    window.closePageMenu = function() { backdrop.classList.remove('active'); sheet.classList.remove('active'); document.body.style.overflow = ''; };
    addSwipeClose(sheet, closePageMenu);
}());
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
