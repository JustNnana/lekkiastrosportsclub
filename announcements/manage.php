<?php
/**
 * Announcements — Admin management list (iOS styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

requireAdmin();

$pageTitle = 'Manage Announcements';
$annObj    = new Announcement();

$search  = sanitize($_GET['search'] ?? '');
$status  = sanitize($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total = $annObj->countAll($search, $status);
$paged = paginate($total, $perPage, $page);
$items = $annObj->getAll($page, $perPage, $search, $status);
$stats = $annObj->getStats();

function buildAnnQS(string $srch, string $st, int $pg): string {
    $p = [];
    if ($srch) $p['search'] = $srch;
    if ($st)   $p['status'] = $st;
    if ($pg > 1) $p['page'] = $pg;
    return $p ? '?' . http_build_query($p) : '?';
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
/* ── Stat cards ── */
.stats-overview-grid {
    display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 24px;
}
.stat-card-link { text-decoration: none; display: block; }
.stat-card-link:hover .stat-card { box-shadow: 0 4px 16px rgba(0,0,0,.08); transform: translateY(-1px); }
.stat-card {
    text-align: left; border: 1px solid var(--border-color);
    border-radius: 14px; padding: 18px; transition: all .2s ease;
}
.stat-header { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.stat-icon   { width: 38px; height: 38px; border-radius: 10px; margin: 0 !important; flex-shrink: 0;
               display: flex; align-items: center; justify-content: center; font-size: 16px; }
.stat-label  { font-size: 13px; color: var(--text-muted); margin: 0; font-weight: 500; }
.stat-value  { font-size: 26px; font-weight: 700; color: var(--text-primary); margin: 0; line-height: 1; }

/* ── iOS Section card ── */
.ios-section-card {
    border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden;
}
.ios-section-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-bottom: 1px solid var(--border-color);
}
.ios-section-header-left { display: flex; align-items: center; gap: 10px; }
.ios-section-icon {
    width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.ios-section-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-count { font-size: 12px; color: var(--text-muted); margin: 0 0 0 6px; }

/* ── Search ── */
.ios-search-wrap {
    position: relative; display: flex; align-items: center;
    margin: 14px 16px 10px;
}
.ios-search-icon {
    position: absolute; left: 12px; color: var(--text-muted); font-size: 13px; pointer-events: none;
}
.ios-search-input {
    width: 100%; padding: 9px 36px 9px 34px; border-radius: 10px;
    border: 1px solid var(--border-color); background: var(--bg-secondary);
    color: var(--text-primary); font-size: 14px; outline: none;
    transition: border-color .2s;
}
.ios-search-input:focus { border-color: var(--primary); }
.ios-search-clear {
    position: absolute; right: 10px; color: var(--text-muted); font-size: 15px;
    text-decoration: none; line-height: 1;
}
.ios-search-clear:hover { color: var(--text-primary); }

/* ── Filter pills ── */
.ios-filter-pills {
    display: flex; gap: 8px; padding: 0 16px 12px; overflow-x: auto;
    -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.ios-filter-pills::-webkit-scrollbar { display: none; }
.ios-filter-pill {
    display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
    padding: 5px 13px; border-radius: 20px; font-size: 12px; font-weight: 600;
    border: 1px solid var(--border-color); background: var(--bg-secondary);
    color: var(--text-secondary); text-decoration: none; transition: all .15s;
}
.ios-filter-pill:hover { border-color: var(--primary); color: var(--primary); text-decoration: none; }
.ios-filter-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.ios-filter-pill .pill-count {
    background: rgba(255,255,255,.25); color: inherit;
    padding: 1px 6px; border-radius: 10px; font-size: 10px;
}
.ios-filter-pill:not(.active) .pill-count { background: var(--bg-hover); }

/* ── Announcement rows ── */
.ios-ann-item {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 16px; border-bottom: 1px solid var(--border-color);
    transition: background .15s;
}
.ios-ann-item:last-child { border-bottom: none; }
.ios-ann-item:hover { background: var(--bg-hover); }

.ios-ann-icon {
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 16px;
}
.ios-ann-body { flex: 1; min-width: 0; }
.ios-ann-title-row { display: flex; align-items: center; gap: 6px; margin-bottom: 2px; }
.ios-ann-pin  { font-size: 10px; color: #ef4444; flex-shrink: 0; }
.ios-ann-title {
    font-size: 14px; font-weight: 600; color: var(--text-primary); text-decoration: none;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ios-ann-title:hover { color: var(--primary); }
.ios-ann-meta {
    font-size: 11px; color: var(--text-muted); margin-bottom: 4px;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ios-ann-chips { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.ios-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600;
}
.ios-chip.green    { background: rgba(52,199,89,.15);  color: #16a34a; }
.ios-chip.orange   { background: rgba(255,149,0,.15);  color: #d97706; }
.ios-chip.blue     { background: rgba(0,122,255,.15);  color: #007aff; }
.ios-chip.gray     { background: rgba(107,114,128,.15); color: #6b7280; }
.ios-chip.red      { background: rgba(255,59,48,.15);  color: #ef4444; }
.ios-chip.stat     { background: var(--bg-secondary); color: var(--text-muted); }

.ios-ann-right { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; }
.ios-item-dots {
    width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    color: var(--text-muted); font-size: 13px; cursor: pointer; transition: background .15s;
}
.ios-item-dots:hover { background: var(--bg-hover); color: var(--text-primary); }

/* ── Empty state ── */
.ios-empty-state {
    text-align: center; padding: 48px 20px; color: var(--text-muted);
}
.ios-empty-state i  { font-size: 36px; opacity: .35; display: block; margin-bottom: 12px; }
.ios-empty-state p  { font-size: 14px; margin: 0; }

/* ── iOS Pagination ── */
.ios-pagination {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 18px; border-top: 1px solid var(--border-color);
}
.ios-pag-info { font-size: 12px; color: var(--text-muted); }
.ios-pag-links { display: flex; gap: 4px; }
.ios-pag-link {
    min-width: 32px; height: 32px; padding: 0 8px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 500; text-decoration: none;
    border: 1px solid var(--border-color); color: var(--text-primary);
    background: var(--bg-secondary); transition: all .15s;
}
.ios-pag-link:hover { border-color: var(--primary); color: var(--primary); text-decoration: none; }
.ios-pag-link.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.ios-pag-link.disabled { opacity: .4; pointer-events: none; }

/* ── Mobile page header ── */
.ios-mobile-header {
    display: none; align-items: center; justify-content: space-between; margin-bottom: 18px;
}
.ios-mobile-header h1 { font-size: 22px; font-weight: 700; color: var(--text-primary); margin: 0 0 2px; }
.ios-mobile-header p  { font-size: 13px; color: var(--text-muted); margin: 0; }
.ios-dots-btn {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    color: var(--text-primary); font-size: 15px; cursor: pointer; flex-shrink: 0;
    transition: background .15s;
}
.ios-dots-btn:hover { background: var(--bg-hover); }

/* ── Bottom sheets ── */
.ios-backdrop {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.45); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
}
.ios-backdrop.active { display: block; }
.ios-sheet {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-card); border-radius: 20px 20px 0 0;
    padding: 0 0 max(20px, env(safe-area-inset-bottom));
    transform: translateY(100%); transition: transform .35s cubic-bezier(.32,1,.23,1);
    touch-action: none;
}
.ios-sheet.open { transform: translateY(0); }
.ios-sheet-handle {
    width: 36px; height: 4px; border-radius: 2px;
    background: var(--border-color); margin: 10px auto 6px; cursor: grab;
}
.ios-sheet-title {
    font-size: 13px; font-weight: 600; color: var(--text-muted);
    text-align: center; padding: 4px 20px 12px;
    border-bottom: 1px solid var(--border-color);
}
.ios-action-row {
    display: flex; align-items: center; gap: 14px; width: 100%;
    padding: 14px 20px; border: none; background: none;
    border-bottom: 1px solid var(--border-color);
    font-size: 15px; font-weight: 500; color: var(--text-primary);
    cursor: pointer; text-align: left; transition: background .15s;
}
.ios-action-row:last-child { border-bottom: none; }
.ios-action-row:hover { background: var(--bg-hover); }
.ios-action-row.danger { color: #ef4444; }
.ios-action-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 15px;
}
.ios-action-icon.blue   { background: rgba(0,122,255,.15);  color: #007aff; }
.ios-action-icon.gray   { background: rgba(107,114,128,.15); color: #6b7280; }
.ios-action-icon.green  { background: rgba(52,199,89,.15);  color: #34c759; }
.ios-action-icon.orange { background: rgba(255,149,0,.15);  color: #ff9500; }
.ios-action-icon.red    { background: rgba(255,59,48,.15);  color: #ef4444; }
.ios-action-icon.purple { background: rgba(175,82,222,.15); color: #af52de; }

/* Confirm sheet */
.ios-confirm-info {
    margin: 14px 20px 16px; padding: 12px 14px;
    background: var(--bg-secondary); border-radius: 12px;
    border: 1px solid var(--border-color);
}
.ios-confirm-info-title { font-size: 13px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px; }
.ios-confirm-info-sub   { font-size: 12px; color: var(--text-muted); margin: 0; }
.ios-confirm-desc       { font-size: 13px; color: var(--text-secondary); padding: 0 20px 14px; margin: 0; }
.ios-confirm-btn {
    display: block; width: calc(100% - 40px); margin: 0 20px;
    padding: 14px; border-radius: 14px; border: none;
    font-size: 16px; font-weight: 600; color: #fff; cursor: pointer;
    transition: opacity .2s;
}
.ios-confirm-btn:hover { opacity: .88; }
.ios-confirm-btn.success { background: #34c759; }
.ios-confirm-btn.warning { background: #ff9500; }
.ios-confirm-btn.danger  { background: #ef4444; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .content-header    { display: none !important; }
    .ios-mobile-header { display: flex; }
    .stats-overview-grid {
        display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch;
        gap: 12px; padding-bottom: 4px; scrollbar-width: none;
    }
    .stats-overview-grid::-webkit-scrollbar { display: none; }
    .stat-card { min-width: 140px; flex: 0 0 auto; padding: 14px; }
    .stat-value { font-size: 22px; }
}
@media (max-width: 480px) {
    .stat-card  { min-width: 130px; padding: 12px; }
    .stat-value { font-size: 20px; }
    .ios-ann-item { padding: 11px 14px; }
    .ios-ann-chips .ios-chip.stat { display: none; } /* hide view/comment counts on tiny screens */
}
</style>

<!-- Mobile header -->
<div class="ios-mobile-header">
    <div>
        <h1>Announcements</h1>
        <p>Manage club announcements</p>
    </div>
    <button class="ios-dots-btn" onclick="openPageMenu()" aria-label="More options">
        <i class="fas fa-ellipsis-h"></i>
    </button>
</div>

<!-- Desktop header -->
<div class="content-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="content-title">Announcements</h1>
        <p class="content-subtitle">Create, publish, and manage club announcements.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>announcements/" class="btn btn-secondary btn-sm">
            <i class="fas fa-bullhorn me-2"></i>View Announcements
        </a>
        <a href="<?php echo BASE_URL; ?>announcements/form.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-2"></i>New Announcement
        </a>
    </div>
</div>

<!-- Stat cards -->
<div class="stats-overview-grid">
    <a href="<?php echo buildAnnQS('', '', 1); ?>" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background:rgba(0,122,255,.15);color:#007aff"><i class="fas fa-bullhorn"></i></div>
                <span class="stat-label">Total</span>
            </div>
            <p class="stat-value"><?php echo $stats['total']; ?></p>
        </div>
    </a>
    <a href="<?php echo buildAnnQS('', 'published', 1); ?>" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background:rgba(52,199,89,.15);color:#34c759"><i class="fas fa-globe"></i></div>
                <span class="stat-label">Published</span>
            </div>
            <p class="stat-value"><?php echo $stats['published']; ?></p>
        </div>
    </a>
    <a href="<?php echo buildAnnQS('', 'draft', 1); ?>" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background:rgba(255,149,0,.15);color:#ff9500"><i class="fas fa-file-alt"></i></div>
                <span class="stat-label">Drafts</span>
            </div>
            <p class="stat-value"><?php echo $stats['drafts']; ?></p>
        </div>
    </a>
    <a href="<?php echo buildAnnQS('', 'pinned', 1); ?>" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-icon" style="background:rgba(255,59,48,.15);color:#ef4444"><i class="fas fa-thumbtack"></i></div>
                <span class="stat-label">Pinned</span>
            </div>
            <p class="stat-value"><?php echo $stats['pinned']; ?></p>
        </div>
    </a>
</div>

<!-- Announcements list -->
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-header-left">
            <div class="ios-section-icon" style="background:rgba(0,122,255,.15);color:#007aff">
                <i class="fas fa-bullhorn"></i>
            </div>
            <span class="ios-section-title">All Announcements</span>
            <span class="ios-section-count"><?php echo $total; ?></span>
        </div>
    </div>

    <!-- Search -->
    <form method="GET" action="">
        <input type="hidden" name="status" value="<?php echo e($status); ?>">
        <div class="ios-search-wrap">
            <i class="fas fa-search ios-search-icon"></i>
            <input type="text" name="search" class="ios-search-input"
                   placeholder="Search title or content…"
                   value="<?php echo e($search); ?>">
            <?php if ($search): ?>
            <a href="<?php echo buildAnnQS('', $status, 1); ?>" class="ios-search-clear">
                <i class="fas fa-times-circle"></i>
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Status filter pills -->
    <div class="ios-filter-pills">
        <?php
        $pills = [
            ''          => ['label' => 'All',       'count' => $stats['total']],
            'published' => ['label' => 'Published',  'count' => $stats['published']],
            'draft'     => ['label' => 'Drafts',     'count' => $stats['drafts']],
            'scheduled' => ['label' => 'Scheduled',  'count' => $stats['scheduled'] ?? 0],
            'pinned'    => ['label' => 'Pinned',     'count' => $stats['pinned']],
        ];
        foreach ($pills as $val => $pill):
            $isActive = $status === $val;
        ?>
        <a href="<?php echo buildAnnQS($search, $val, 1); ?>"
           class="ios-filter-pill <?php echo $isActive ? 'active' : ''; ?>">
            <?php echo $pill['label']; ?>
            <span class="pill-count"><?php echo $pill['count']; ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- List -->
    <?php if (empty($items)): ?>
    <div class="ios-empty-state">
        <i class="fas fa-bullhorn"></i>
        <p>No announcements found<?php echo $search ? ' for "'.e($search).'"' : ''; ?>.</p>
    </div>
    <?php else: ?>
    <?php foreach ($items as $a):
        $isPublished = (bool)$a['is_published'];
        $isScheduled = !$isPublished && !empty($a['scheduled_at']);
        $isPinned    = (bool)$a['is_pinned'];

        if ($isPublished) {
            $iconBg = 'rgba(52,199,89,.15)'; $iconColor = '#34c759'; $iconClass = 'fas fa-globe';
        } elseif ($isScheduled) {
            $iconBg = 'rgba(0,122,255,.15)'; $iconColor = '#007aff'; $iconClass = 'fas fa-clock';
        } else {
            $iconBg = 'rgba(107,114,128,.15)'; $iconColor = '#6b7280'; $iconClass = 'fas fa-file-alt';
        }
    ?>
    <div class="ios-ann-item"
         data-id="<?php echo $a['id']; ?>"
         data-title="<?php echo e($a['title']); ?>"
         data-is-published="<?php echo $isPublished ? '1' : '0'; ?>"
         data-is-pinned="<?php echo $isPinned ? '1' : '0'; ?>"
         data-is-scheduled="<?php echo $isScheduled ? '1' : '0'; ?>">

        <div class="ios-ann-icon" style="background:<?php echo $iconBg; ?>;color:<?php echo $iconColor; ?>">
            <i class="<?php echo $iconClass; ?>"></i>
        </div>

        <div class="ios-ann-body">
            <div class="ios-ann-title-row">
                <?php if ($isPinned): ?>
                <i class="fas fa-thumbtack ios-ann-pin" title="Pinned"></i>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $a['id']; ?>"
                   class="ios-ann-title"><?php echo e($a['title']); ?></a>
            </div>
            <div class="ios-ann-meta">
                <?php echo e($a['author_name'] ?: $a['author_email']); ?>
                &nbsp;·&nbsp;
                <?php if ($isScheduled): ?>
                <i class="fas fa-clock" style="font-size:9px"></i>
                <?php echo formatDate($a['scheduled_at'], 'd M Y, g:i A'); ?>
                <?php else: ?>
                <?php echo formatDate($a['created_at'], 'd M Y'); ?>
                <?php endif; ?>
            </div>
            <div class="ios-ann-chips">
                <?php if ($isPublished): ?>
                <span class="ios-chip green"><i class="fas fa-circle" style="font-size:6px"></i>Published</span>
                <?php elseif ($isScheduled): ?>
                <span class="ios-chip blue"><i class="fas fa-clock" style="font-size:9px"></i>Scheduled</span>
                <?php else: ?>
                <span class="ios-chip gray">Draft</span>
                <?php endif; ?>
                <?php if ($isPinned): ?>
                <span class="ios-chip red"><i class="fas fa-thumbtack" style="font-size:9px"></i>Pinned</span>
                <?php endif; ?>
                <span class="ios-chip stat"><i class="fas fa-eye" style="font-size:9px"></i> <?php echo number_format($a['views']); ?></span>
                <span class="ios-chip stat"><i class="fas fa-comment" style="font-size:9px"></i> <?php echo $a['comment_count']; ?></span>
                <span class="ios-chip stat"><i class="fas fa-heart" style="font-size:9px"></i> <?php echo $a['reaction_count']; ?></span>
            </div>
        </div>

        <div class="ios-ann-right">
            <button class="ios-item-dots" onclick="openActionSheet(this)" aria-label="Actions">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($paged['total_pages'] > 1): ?>
    <div class="ios-pagination">
        <span class="ios-pag-info">
            <?php echo min($total,($page-1)*$perPage+1); ?>–<?php echo min($total,$page*$perPage); ?> of <?php echo $total; ?>
        </span>
        <div class="ios-pag-links">
            <a class="ios-pag-link <?php echo !$paged['has_prev'] ? 'disabled' : ''; ?>"
               href="<?php echo buildAnnQS($search, $status, $page-1); ?>">‹</a>
            <?php
            $start = max(1, $page - 2);
            $end   = min($paged['total_pages'], $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a class="ios-pag-link <?php echo $i === $page ? 'active' : ''; ?>"
               href="<?php echo buildAnnQS($search, $status, $i); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a class="ios-pag-link <?php echo !$paged['has_next'] ? 'disabled' : ''; ?>"
               href="<?php echo buildAnnQS($search, $status, $page+1); ?>">›</a>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Hidden forms for direct POST actions -->
<form id="togglePinForm" method="POST" action="<?php echo BASE_URL; ?>announcements/actions.php" style="display:none">
    <?php echo csrfField(); ?>
    <input type="hidden" name="action" value="toggle_pin">
    <input type="hidden" name="id" id="togglePinId">
</form>

<!-- ===== PAGE MENU SHEET ===== -->
<div class="ios-backdrop" id="pageMenuBackdrop" onclick="closePageMenu()"></div>
<div class="ios-sheet" id="pageMenuSheet" style="z-index:10000">
    <div class="ios-sheet-handle"></div>
    <div class="ios-sheet-title">Announcements</div>
    <a href="<?php echo BASE_URL; ?>announcements/form.php" class="ios-action-row" style="text-decoration:none;color:var(--text-primary)">
        <span class="ios-action-icon blue"><i class="fas fa-plus"></i></span>New Announcement
    </a>
    <a href="<?php echo BASE_URL; ?>announcements/" class="ios-action-row" style="text-decoration:none;color:var(--text-primary)">
        <span class="ios-action-icon green"><i class="fas fa-bullhorn"></i></span>View Announcements
    </a>
    <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-action-row" style="text-decoration:none;color:var(--text-primary)">
        <span class="ios-action-icon gray"><i class="fas fa-home"></i></span>Dashboard
    </a>
</div>

<!-- ===== ACTION SHEET ===== -->
<div class="ios-backdrop" id="actionBackdrop" onclick="closeActionSheet()"></div>
<div class="ios-sheet" id="actionSheet" style="z-index:10000">
    <div class="ios-sheet-handle"></div>
    <div class="ios-sheet-title" id="actionSheetTitle">Announcement</div>
    <div id="actionSheetBody"></div>
</div>

<!-- ===== CONFIRM SHEET ===== -->
<div class="ios-backdrop" id="confirmBackdrop" onclick="closeConfirmSheet()"></div>
<div class="ios-sheet" id="confirmSheet" style="z-index:10001">
    <div class="ios-sheet-handle"></div>
    <div class="ios-sheet-title" id="confirmTitle">Confirm</div>
    <div class="ios-confirm-info">
        <p class="ios-confirm-info-title" id="confirmAnnTitle"></p>
        <p class="ios-confirm-info-sub"   id="confirmAnnSub"></p>
    </div>
    <p class="ios-confirm-desc" id="confirmDesc"></p>
    <form method="POST" action="<?php echo BASE_URL; ?>announcements/actions.php" id="confirmForm">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" id="confirmAction">
        <input type="hidden" name="id"     id="confirmId">
        <button type="submit" class="ios-confirm-btn" id="confirmBtn"></button>
    </form>
</div>

<script>
var BASE_URL = '<?php echo BASE_URL; ?>';
var currentAnn = {};

/* ── confirm sheet configs ── */
var confirmCfg = {
    publish:   { title: 'Publish Announcement', btnClass: 'success', btnText: 'Publish Now',         desc: 'This will make the announcement visible to all members.' },
    unpublish: { title: 'Unpublish Announcement', btnClass: 'warning', btnText: 'Unpublish',          desc: 'This will hide the announcement from members.' },
    delete:    { title: 'Delete Announcement',  btnClass: 'danger',  btnText: 'Delete Permanently',  desc: 'This will permanently delete the announcement, including all comments and reactions.' },
};

/* ── Sheet helpers ── */
function openSheet(backdrop, sheet) {
    document.getElementById(backdrop).classList.add('active');
    requestAnimationFrame(function () { document.getElementById(sheet).classList.add('open'); });
}
function closeSheet(backdrop, sheet, cb) {
    document.getElementById(sheet).classList.remove('open');
    setTimeout(function () {
        document.getElementById(backdrop).classList.remove('active');
        if (cb) cb();
    }, 350);
}

function addSwipeClose(sheetId, closeFn) {
    var el = document.getElementById(sheetId), startY = 0;
    el.addEventListener('touchstart', function (e) { startY = e.touches[0].clientY; }, { passive: true });
    el.addEventListener('touchend', function (e) {
        if (e.changedTouches[0].clientY - startY > 60) closeFn();
    }, { passive: true });
}

/* ── Page menu ── */
window.openPageMenu  = function () { openSheet('pageMenuBackdrop', 'pageMenuSheet'); };
window.closePageMenu = function () { closeSheet('pageMenuBackdrop', 'pageMenuSheet'); };
addSwipeClose('pageMenuSheet', closePageMenu);

/* ── Action sheet ── */
window.openActionSheet = function (btn) {
    var item = btn.closest('.ios-ann-item');
    currentAnn = {
        id:          item.dataset.id,
        title:       item.dataset.title,
        isPublished: item.dataset.isPublished === '1',
        isPinned:    item.dataset.isPinned    === '1',
        isScheduled: item.dataset.isScheduled === '1',
    };

    document.getElementById('actionSheetTitle').textContent = currentAnn.title.length > 38
        ? currentAnn.title.substring(0, 35) + '…' : currentAnn.title;

    var rows = '';
    rows += '<button class="ios-action-row" onclick="window.location.href=BASE_URL+\'announcements/view.php?id=\'+currentAnn.id">' +
            '<span class="ios-action-icon blue"><i class="fas fa-eye"></i></span>View</button>';
    rows += '<button class="ios-action-row" onclick="window.location.href=BASE_URL+\'announcements/form.php?id=\'+currentAnn.id">' +
            '<span class="ios-action-icon gray"><i class="fas fa-edit"></i></span>Edit</button>';

    if (currentAnn.isPublished) {
        rows += '<button class="ios-action-row" onclick="openConfirmSheet(\'unpublish\')">' +
                '<span class="ios-action-icon orange"><i class="fas fa-eye-slash"></i></span>Unpublish</button>';
    } else if (!currentAnn.isScheduled) {
        rows += '<button class="ios-action-row" onclick="openConfirmSheet(\'publish\')">' +
                '<span class="ios-action-icon green"><i class="fas fa-globe"></i></span>Publish Now</button>';
    }

    rows += '<button class="ios-action-row" onclick="submitTogglePin()">' +
            '<span class="ios-action-icon purple"><i class="fas fa-thumbtack"></i></span>' +
            (currentAnn.isPinned ? 'Unpin' : 'Pin') + '</button>';

    rows += '<button class="ios-action-row danger" onclick="openConfirmSheet(\'delete\')">' +
            '<span class="ios-action-icon red"><i class="fas fa-trash"></i></span>Delete</button>';

    document.getElementById('actionSheetBody').innerHTML = rows;
    openSheet('actionBackdrop', 'actionSheet');
};
window.closeActionSheet = function () { closeSheet('actionBackdrop', 'actionSheet'); };
addSwipeClose('actionSheet', closeActionSheet);

/* ── Confirm sheet ── */
window.openConfirmSheet = function (type) {
    var cfg = confirmCfg[type];
    document.getElementById('confirmTitle').textContent        = cfg.title;
    document.getElementById('confirmAnnTitle').textContent     = currentAnn.title;
    document.getElementById('confirmAnnSub').textContent       = (currentAnn.isPublished ? 'Published' : (currentAnn.isScheduled ? 'Scheduled' : 'Draft'));
    document.getElementById('confirmDesc').textContent         = cfg.desc;
    document.getElementById('confirmAction').value             = type;
    document.getElementById('confirmId').value                 = currentAnn.id;
    document.getElementById('confirmBtn').textContent          = cfg.btnText;
    document.getElementById('confirmBtn').className            = 'ios-confirm-btn ' + cfg.btnClass;
    closeActionSheet();
    setTimeout(function () { openSheet('confirmBackdrop', 'confirmSheet'); }, 320);
};
window.closeConfirmSheet = function () { closeSheet('confirmBackdrop', 'confirmSheet'); };
addSwipeClose('confirmSheet', closeConfirmSheet);

/* ── Toggle pin (direct submit) ── */
window.submitTogglePin = function () {
    document.getElementById('togglePinId').value = currentAnn.id;
    closeActionSheet();
    setTimeout(function () { document.getElementById('togglePinForm').submit(); }, 350);
};
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
