<?php
/**
 * Announcements — Member feed (iOS styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

requireLogin();

$pageTitle = 'Announcements';
$annObj    = new Announcement();
$userId    = (int)$_SESSION['user_id'];

$filter  = sanitize($_GET['filter'] ?? 'all');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

$total = $annObj->countFeed();
$stats = $annObj->getStats();
$paged = paginate($total, $perPage, $page);
$items = $annObj->getFeed($page, $perPage);

// Apply pinned filter client-side
if ($filter === 'pinned') {
    $items = array_values(array_filter($items, fn($a) => $a['is_pinned']));
}

// Add user_reacted flag
$db = Database::getInstance();
foreach ($items as &$item) {
    $r = $db->fetchOne(
        "SELECT COUNT(*) AS c FROM announcement_reactions WHERE announcement_id = ? AND user_id = ?",
        [$item['id'], $userId]
    );
    $item['user_reacted'] = (bool)($r['c'] ?? 0);
}
unset($item);

// Mark feed as read
$annObj->markRead($userId);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
:root {
    --ios-red:    #FF453A;
    --ios-orange: #FF9F0A;
    --ios-green:  #30D158;
    --ios-blue:   #0A84FF;
    --ios-purple: #BF5AF2;
}

/* ── iOS Section card ── */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
}

.ios-section-header {
    display: flex; align-items: center; gap: 14px;
    padding: 18px 20px;
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-section-icon {
    width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 18px;
}
.ios-section-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }

.ios-section-title-wrap { flex: 1; min-width: 0; }
.ios-section-title-wrap h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title-wrap p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

.ios-options-btn {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    color: var(--text-primary); font-size: 16px; cursor: pointer;
    flex-shrink: 0; transition: background .2s, transform .15s;
}
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(.95); }

/* ── Stats grid ── */
.ios-stats-grid {
    display: grid; grid-template-columns: repeat(4,1fr); gap: 14px;
    padding: 18px 20px;
    background: var(--bg-subtle); border-bottom: 1px solid var(--border-color);
}
.ios-stat-card {
    background: var(--bg-primary); border: 1px solid var(--border-color);
    border-radius: 12px; padding: 14px;
    display: flex; align-items: center; gap: 12px; transition: all .2s;
}
.ios-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.ios-stat-icon {
    width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 18px;
}
.ios-stat-icon.primary { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-stat-icon.orange  { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-stat-icon.blue    { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-stat-icon.purple  { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-stat-label { font-size: 12px; color: var(--text-secondary); margin-bottom: 2px; }
.ios-stat-value { font-size: 24px; font-weight: 700; color: var(--text-primary); line-height: 1; }

/* ── Filter tabs ── */
.ios-filter-section {
    padding: 14px 20px; border-bottom: 1px solid var(--border-color);
}
.ios-filter-tabs {
    display: flex; gap: 8px; overflow-x: auto;
    -webkit-overflow-scrolling: touch; scrollbar-width: none; padding-bottom: 2px;
}
.ios-filter-tabs::-webkit-scrollbar { display: none; }
.ios-filter-tab {
    display: flex; align-items: center; gap: 7px; white-space: nowrap;
    padding: 9px 16px; background: var(--bg-secondary);
    border: 1px solid var(--border-color); border-radius: 10px;
    color: var(--text-secondary); font-size: 14px; font-weight: 500;
    text-decoration: none; transition: all .2s;
}
.ios-filter-tab:hover { background: var(--bg-hover); color: var(--text-primary); text-decoration: none; }
.ios-filter-tab.active { background: var(--ios-blue); border-color: var(--ios-blue); color: #fff; }
.ios-filter-badge {
    background: rgba(255,255,255,.2); padding: 2px 8px;
    border-radius: 10px; font-size: 12px; font-weight: 600;
}
.ios-filter-tab:not(.active) .ios-filter-badge {
    background: var(--bg-hover); color: var(--text-secondary);
}

/* ── Announcements grid ── */
.ios-announcements-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 16px; padding: 18px 20px;
}

/* ── Announcement card ── */
.ios-announcement-card {
    background: var(--bg-primary); border: 1px solid var(--border-color);
    border-radius: 14px; overflow: hidden;
    display: flex; flex-direction: column;
    transition: all .2s;
}
.ios-announcement-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,.1);
    border-color: var(--ios-blue);
}
.ios-announcement-card.pinned {
    border-color: var(--ios-orange); border-width: 2px;
}

.ios-card-header {
    padding: 14px 16px; border-bottom: 1px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: flex-start;
}
.ios-author-info { display: flex; align-items: center; gap: 11px; }
.ios-author-avatar {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    background: var(--ios-blue); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700;
}
.ios-author-details h6 {
    font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px;
}
.ios-author-meta { font-size: 12px; color: var(--text-secondary); }

.ios-pin-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--ios-orange); color: #fff;
    padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600;
}

.ios-card-image { width: 100%; height: 180px; object-fit: cover; display: block; }

.ios-card-body { padding: 16px; flex: 1; }
.ios-card-title {
    font-size: 17px; font-weight: 600; color: var(--text-primary);
    margin: 0 0 10px; line-height: 1.4; text-decoration: none; display: block;
}
.ios-card-title:hover { color: var(--ios-blue); }
.ios-card-content {
    font-size: 14px; color: var(--text-secondary); line-height: 1.55; margin: 0;
    display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3;
    -webkit-box-orient: vertical; overflow: hidden;
}

.ios-card-footer {
    padding: 12px 16px; border-top: 1px solid var(--border-color);
    display: flex; justify-content: space-between; align-items: center;
}
.ios-card-actions { display: flex; gap: 16px; }
.ios-action-btn {
    display: flex; align-items: center; gap: 6px; background: none; border: none;
    color: var(--text-secondary); font-size: 13px; cursor: pointer; padding: 4px;
    transition: color .15s;
}
.ios-action-btn:hover  { color: var(--ios-blue); }
.ios-action-btn.active { color: var(--ios-red); }
.ios-action-btn i { font-size: 14px; }
.ios-read-more-btn {
    padding: 7px 14px;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    border-radius: 8px; color: var(--text-primary); font-size: 13px; font-weight: 500;
    text-decoration: none; transition: all .15s;
}
.ios-read-more-btn:hover {
    background: var(--ios-blue); border-color: var(--ios-blue);
    color: #fff; text-decoration: none;
}

/* ── Empty state ── */
.ios-empty-state {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 56px 20px; text-align: center;
}
.ios-empty-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: var(--bg-secondary);
    display: flex; align-items: center; justify-content: center; margin-bottom: 16px;
}
.ios-empty-icon i { font-size: 32px; color: var(--text-muted); }
.ios-empty-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; }
.ios-empty-text  { font-size: 14px; color: var(--text-secondary); margin: 0; }

/* ── Pagination ── */
.ios-pagination {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 20px; border-top: 1px solid var(--border-color);
    background: var(--bg-subtle);
}
.ios-pagination-info { font-size: 13px; color: var(--text-secondary); }
.ios-pagination-nav  { display: flex; gap: 6px; }
.ios-page-btn {
    min-width: 36px; height: 36px; padding: 0 10px; border-radius: 8px;
    border: 1px solid var(--border-color); background: var(--bg-primary);
    color: var(--text-primary); font-size: 14px; cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center;
    text-decoration: none; transition: all .15s;
}
.ios-page-btn:hover:not(.disabled) {
    background: var(--bg-hover); border-color: var(--ios-blue); color: var(--ios-blue);
    text-decoration: none;
}
.ios-page-btn.active   { background: var(--ios-blue); border-color: var(--ios-blue); color: #fff; }
.ios-page-btn.disabled { opacity: .45; pointer-events: none; }

/* ── iOS Menu Modal (bottom sheet) ── */
.ios-menu-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.4);
    backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    z-index: 9998; opacity: 0; visibility: hidden;
    transition: opacity .3s, visibility .3s;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }

.ios-menu-modal {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-primary); border-radius: 16px 16px 0 0;
    z-index: 9999; transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 85vh; overflow: hidden;
    display: flex; flex-direction: column;
}
.ios-menu-modal.active { transform: translateY(0); }

.ios-menu-handle {
    width: 36px; height: 5px; border-radius: 3px;
    background: var(--border-color); margin: 8px auto 4px; flex-shrink: 0;
}
.ios-menu-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0;
}
.ios-menu-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--bg-secondary); border: none;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary); cursor: pointer; transition: background .2s;
}
.ios-menu-close:hover { background: var(--border-color); }

.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title {
    font-size: 13px; font-weight: 600; color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-left: 4px;
}
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; border-bottom: 1px solid var(--border-color);
    text-decoration: none; color: var(--text-primary); transition: background .15s; cursor: pointer;
}
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:hover, .ios-menu-item:active { background: var(--bg-subtle); text-decoration: none; color: var(--text-primary); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 14px;
}
.ios-menu-item-icon.primary { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-menu-item-icon.orange  { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-menu-item-icon.blue    { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-menu-item-icon.purple  { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-menu-item-icon.red     { background: rgba(255,69,58,.15);  color: var(--ios-red); }
.ios-menu-item-icon.gray    { background: rgba(107,114,128,.15); color: #6b7280; }
.ios-menu-item-content { flex: 1; min-width: 0; }
.ios-menu-item-label { font-size: 15px; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-menu-item-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }
.ios-menu-check { color: var(--ios-blue); font-size: 14px; flex-shrink: 0; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .content-header   { display: none !important; }
    .ios-stats-grid   { grid-template-columns: repeat(2,1fr); gap: 10px; padding: 14px; }
    .ios-stat-card    { padding: 12px; }
    .ios-stat-icon    { width: 36px; height: 36px; font-size: 16px; }
    .ios-stat-value   { font-size: 20px; }
    .ios-filter-section { display: none; }   /* shown in menu modal instead */
    .ios-section-header { padding: 14px; }
    .ios-announcements-grid { grid-template-columns: 1fr; gap: 12px; padding: 14px; }
    .ios-announcement-card { border-radius: 12px; }
    .ios-card-image   { height: 150px; }
    .ios-pagination   { flex-direction: column; gap: 12px; padding: 14px; }
}
@media (max-width: 480px) {
    .ios-card-title   { font-size: 15px; }
    .ios-card-content { font-size: 13px; }
    .ios-options-btn  { width: 32px; height: 32px; font-size: 14px; }
}
</style>

<!-- Desktop header -->
<div class="content-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="content-title"><i class="fas fa-bullhorn me-2"></i>Announcements</h1>
        <p class="content-subtitle">Stay up to date with the latest club news.</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-cog me-2"></i>Manage
        </a>
        <a href="<?php echo BASE_URL; ?>announcements/form.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-2"></i>New
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- iOS Section Card -->
<div class="ios-section-card">

    <!-- Section header -->
    <div class="ios-section-header">
        <div class="ios-section-icon orange">
            <i class="fas fa-bullhorn"></i>
        </div>
        <div class="ios-section-title-wrap">
            <h5>Announcements</h5>
            <p>Stay updated with important club announcements</p>
        </div>
        <button class="ios-options-btn" id="iosOptionsBtn" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <!-- Stats grid -->
    <div class="ios-stats-grid">
        <div class="ios-stat-card">
            <div class="ios-stat-icon primary"><i class="fas fa-bullhorn"></i></div>
            <div>
                <div class="ios-stat-label">Total</div>
                <div class="ios-stat-value"><?php echo number_format($stats['published'] ?? $stats['total']); ?></div>
            </div>
        </div>
        <div class="ios-stat-card">
            <div class="ios-stat-icon orange"><i class="fas fa-thumbtack"></i></div>
            <div>
                <div class="ios-stat-label">Pinned</div>
                <div class="ios-stat-value"><?php echo number_format($stats['pinned']); ?></div>
            </div>
        </div>
        <div class="ios-stat-card">
            <div class="ios-stat-icon blue"><i class="fas fa-comment"></i></div>
            <div>
                <div class="ios-stat-label">Comments</div>
                <div class="ios-stat-value"><?php
                    $totalComments = array_sum(array_column($items, 'comment_count'));
                    echo number_format($totalComments);
                ?></div>
            </div>
        </div>
        <div class="ios-stat-card">
            <div class="ios-stat-icon purple"><i class="fas fa-heart"></i></div>
            <div>
                <div class="ios-stat-label">Reactions</div>
                <div class="ios-stat-value"><?php
                    $totalReactions = array_sum(array_column($items, 'reaction_count'));
                    echo number_format($totalReactions);
                ?></div>
            </div>
        </div>
    </div>

    <!-- Filter tabs (desktop only — mobile uses menu modal) -->
    <div class="ios-filter-section">
        <div class="ios-filter-tabs">
            <a href="?filter=all" class="ios-filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                All
                <span class="ios-filter-badge"><?php echo $stats['published'] ?? $stats['total']; ?></span>
            </a>
            <a href="?filter=pinned" class="ios-filter-tab <?php echo $filter === 'pinned' ? 'active' : ''; ?>">
                <i class="fas fa-thumbtack"></i>
                Pinned
                <span class="ios-filter-badge"><?php echo $stats['pinned']; ?></span>
            </a>
        </div>
    </div>

    <!-- Announcements -->
    <?php if (empty($items)): ?>
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-bullhorn"></i></div>
        <h5 class="ios-empty-title">No Announcements Yet</h5>
        <p class="ios-empty-text">
            <?php echo $filter === 'pinned' ? 'No pinned announcements at this time.' : 'Check back soon for club updates!'; ?>
        </p>
    </div>
    <?php else: ?>
    <div class="ios-announcements-grid">
        <?php foreach ($items as $a):
            $initials  = strtoupper(mb_substr($a['author_name'] ?: $a['author_email'] ?? '?', 0, 2));
            $isPinned  = (bool)$a['is_pinned'];
        ?>
        <div class="ios-announcement-card <?php echo $isPinned ? 'pinned' : ''; ?>">

            <!-- Card header -->
            <div class="ios-card-header">
                <div class="ios-author-info">
                    <div class="ios-author-avatar"><?php echo e($initials); ?></div>
                    <div class="ios-author-details">
                        <h6><?php echo e($a['author_name'] ?: ($a['author_email'] ?? 'Admin')); ?></h6>
                        <div class="ios-author-meta">
                            <?php echo formatDate($a['created_at'], 'M j, Y'); ?>
                        </div>
                    </div>
                </div>
                <?php if ($isPinned): ?>
                <div class="ios-pin-badge"><i class="fas fa-thumbtack"></i> Pinned</div>
                <?php endif; ?>
            </div>

            <!-- Image -->
            <?php if (!empty($a['image_path'])): ?>
            <img src="<?php echo e($a['image_path']); ?>"
                 alt="<?php echo e($a['title']); ?>"
                 class="ios-card-image">
            <?php endif; ?>

            <!-- Body -->
            <div class="ios-card-body">
                <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $a['id']; ?>"
                   class="ios-card-title"><?php echo e($a['title']); ?></a>
                <p class="ios-card-content"><?php echo e(strip_tags($a['content'])); ?></p>
            </div>

            <!-- Footer -->
            <div class="ios-card-footer">
                <div class="ios-card-actions">
                    <button class="ios-action-btn reaction-btn <?php echo $a['user_reacted'] ? 'active' : ''; ?>"
                            data-id="<?php echo $a['id']; ?>">
                        <i class="fas fa-heart"></i>
                        <span class="react-count"><?php echo $a['reaction_count']; ?></span>
                    </button>
                    <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $a['id']; ?>#comments"
                       class="ios-action-btn">
                        <i class="fas fa-comment"></i>
                        <span><?php echo $a['comment_count']; ?></span>
                    </a>
                    <span class="ios-action-btn" style="pointer-events:none">
                        <i class="fas fa-eye"></i>
                        <span><?php echo number_format($a['views']); ?></span>
                    </span>
                </div>
                <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $a['id']; ?>"
                   class="ios-read-more-btn">Read More</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($paged['total_pages'] > 1): ?>
    <div class="ios-pagination">
        <div class="ios-pagination-info">
            Showing <?php echo (($page-1)*$perPage)+1; ?>–<?php echo min($page*$perPage,$total); ?> of <?php echo $total; ?>
        </div>
        <div class="ios-pagination-nav">
            <a class="ios-page-btn <?php echo !$paged['has_prev'] ? 'disabled' : ''; ?>"
               href="?filter=<?php echo $filter; ?>&page=<?php echo $page-1; ?>">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php
            $s = max(1, $page-2); $e = min($paged['total_pages'], $page+2);
            for ($i = $s; $i <= $e; $i++):
            ?>
            <a class="ios-page-btn <?php echo $i===$page?'active':''; ?>"
               href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a class="ios-page-btn <?php echo !$paged['has_next'] ? 'disabled' : ''; ?>"
               href="?filter=<?php echo $filter; ?>&page=<?php echo $page+1; ?>">
                <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div><!-- /ios-section-card -->

<!-- ===== iOS MENU MODAL (bottom sheet) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Announcements</h3>
        <button class="ios-menu-close" id="iosMenuClose"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <!-- Quick Actions -->
        <?php if (isAdmin()): ?>
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>announcements/form.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-plus"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">New Announcement</span>
                            <span class="ios-menu-item-desc">Create a new announcement</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-cog"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Manage Announcements</span>
                            <span class="ios-menu-item-desc">Edit, publish, or delete</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter</div>
            <div class="ios-menu-card">
                <a href="?filter=all" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-th-large"></i></div>
                        <span class="ios-menu-item-label">All Announcements</span>
                    </div>
                    <?php if ($filter === 'all'): ?>
                    <i class="fas fa-check ios-menu-check"></i>
                    <?php endif; ?>
                </a>
                <a href="?filter=pinned" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-thumbtack"></i></div>
                        <span class="ios-menu-item-label">Pinned</span>
                    </div>
                    <?php if ($filter === 'pinned'): ?>
                    <i class="fas fa-check ios-menu-check"></i>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Navigation -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Navigation</div>
            <div class="ios-menu-card">
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
                <div class="ios-menu-item" onclick="location.reload()">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon gray"><i class="fas fa-sync-alt"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Refresh</span>
                            <span class="ios-menu-item-desc">Reload announcements</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    var backdrop  = document.getElementById('iosMenuBackdrop');
    var modal     = document.getElementById('iosMenuModal');
    var openBtn   = document.getElementById('iosOptionsBtn');
    var closeBtn  = document.getElementById('iosMenuClose');

    function openMenu() {
        backdrop.classList.add('active');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeMenu() {
        backdrop.classList.remove('active');
        modal.classList.remove('active');
        document.body.style.overflow = '';
        modal.style.transform = '';
    }

    if (openBtn)  openBtn.addEventListener('click', openMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    if (backdrop) backdrop.addEventListener('click', closeMenu);

    document.querySelectorAll('.ios-menu-modal a.ios-menu-item').forEach(function (el) {
        el.addEventListener('click', closeMenu);
    });

    // Swipe down to close
    var startY = 0, currentY = 0;
    modal.addEventListener('touchstart', function (e) { startY = e.touches[0].clientY; }, { passive: true });
    modal.addEventListener('touchmove',  function (e) {
        currentY = e.touches[0].clientY;
        var diff = currentY - startY;
        if (diff > 0) modal.style.transform = 'translateY(' + diff + 'px)';
    }, { passive: true });
    modal.addEventListener('touchend', function () {
        if (currentY - startY > 100) closeMenu();
        else modal.style.transform = '';
        startY = 0; currentY = 0;
    }, { passive: true });

    // Reaction buttons
    document.querySelectorAll('.reaction-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id        = this.dataset.id;
            var countEl   = this.querySelector('.react-count');
            var isActive  = this.classList.contains('active');
            var self      = this;

            fetch('<?php echo BASE_URL; ?>announcements/reactions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ announcement_id: id, action: isActive ? 'remove' : 'add' })
            })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    self.classList.toggle('active');
                    if (countEl) countEl.textContent = res.count;
                }
            })
            .catch(function () {});
        });
    });
})();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
