<?php
/**
 * Single Announcement — full view with reactions and comments
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

requireLogin();

$id     = (int)($_GET['id'] ?? 0);
$annObj = new Announcement();
$ann    = $annObj->getById($id);

if (!$ann) {
    flashError('Announcement not found.');
    redirect('announcements/index.php');
}

// Non-admins cannot see drafts/unpublished
if (!isAdmin() && !$ann['is_published']) {
    flashError('This announcement is not available.');
    redirect('announcements/index.php');
}

// Scheduled but not yet due
if (!isAdmin() && $ann['scheduled_at'] && strtotime($ann['scheduled_at']) > time()) {
    flashError('This announcement is not yet available.');
    redirect('announcements/index.php');
}

$annObj->incrementViews($id);

$userId    = (int)$_SESSION['user_id'];
$comments  = $annObj->getComments($id);
$reactions = $annObj->getReactions($id, $userId);

$pageTitle = e($ann['title']);

// Author initial + colour
$authorInitial = strtoupper(substr($ann['author_name'] ?? 'A', 0, 1));
$avatarColors  = ['#0A84FF','#30D158','#FF9F0A','#BF5AF2','#FF453A','#64D2FF'];
$avatarColor   = $avatarColors[ord($authorInitial) % count($avatarColors)];

$totalReactions = ($reactions['like'] ?? 0) + ($reactions['love'] ?? 0)
                + ($reactions['support'] ?? 0) + ($reactions['celebrate'] ?? 0);

// Build threaded comment tree
$commentMap   = [];
$rootComments = [];
foreach ($comments as $c) {
    $commentMap[$c['id']] = $c + ['replies' => []];
}
foreach ($commentMap as $cid => $c) {
    if ($c['parent_id'] && isset($commentMap[$c['parent_id']])) {
        $commentMap[$c['parent_id']]['replies'][] = &$commentMap[$cid];
    } else {
        $rootComments[] = &$commentMap[$cid];
    }
}
$rootComments = array_reverse($rootComments); // newest first

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
    --ios-teal:   #64D2FF;
}

/* ── Layout ── */
.ios-view-layout {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: var(--spacing-5);
    max-width: 1100px;
    margin: 0 auto;
}
@media (max-width: 992px) { .ios-view-layout { grid-template-columns: 1fr; } }

/* ── iOS Section Card ── */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: var(--spacing-4);
}
.ios-section-card.pinned { border-color: var(--ios-orange); border-width: 2px; }

.ios-section-header {
    display: flex; align-items: center; gap: var(--spacing-3);
    padding: var(--spacing-4);
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-section-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.ios-section-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-section-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-section-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-section-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }

.ios-section-title { flex: 1; min-width: 0; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

/* ── 3-dot button ── */
.ios-options-btn {
    display: flex;
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    align-items: center; justify-content: center;
    cursor: pointer;
    transition: background .2s ease, transform .15s ease;
    flex-shrink: 0;
}
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* ── Announcement detail card ── */
.ios-detail-header { padding: 18px 20px; border-bottom: 1px solid var(--border-color); }

.ios-author-row {
    display: flex; align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    gap: 12px;
}
.ios-author-info { display: flex; align-items: center; gap: 12px; }
.ios-author-avatar {
    width: 48px; height: 48px; border-radius: 50%;
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 17px; flex-shrink: 0;
}
.ios-author-name { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px; }
.ios-author-meta { font-size: 12px; color: var(--text-secondary); margin: 0; display: flex; align-items: center; gap: 6px; }

.ios-badges { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; flex-shrink: 0; }
.ios-pin-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: var(--ios-orange); color: #fff;
    padding: 4px 9px; border-radius: 8px;
    font-size: 11px; font-weight: 600;
}
.ios-draft-badge {
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(255,159,10,.15); color: var(--ios-orange);
    border: 1px solid rgba(255,159,10,.3);
    padding: 4px 9px; border-radius: 8px;
    font-size: 11px; font-weight: 600;
}

.ios-detail-title {
    font-size: 22px; font-weight: 700; color: var(--text-primary);
    line-height: 1.3; margin: 0;
}

/* Featured image */
.ios-featured-image {
    width: 100%; max-height: 420px; object-fit: cover; display: block;
}

/* Content body */
.ios-detail-body { padding: 20px; }
.ios-ann-content {
    font-size: 15px; color: var(--text-primary);
    line-height: 1.8;
}
.ios-ann-content h2, .ios-ann-content h3, .ios-ann-content h4 { margin: 1.2em 0 .5em; font-weight: 600; }
.ios-ann-content p  { margin: 0 0 1em; }
.ios-ann-content ul, .ios-ann-content ol { padding-left: 1.5em; margin: 0 0 1em; }
.ios-ann-content li { margin-bottom: .3em; }
.ios-ann-content a  { color: var(--ios-blue); text-decoration: underline; }
.ios-ann-content blockquote {
    border-left: 3px solid var(--border-color);
    padding: .5em 1em; margin: 1em 0;
    color: var(--text-secondary); font-style: italic;
}

/* Detail footer: stats + reactions */
.ios-detail-footer {
    padding: 14px 20px;
    border-top: 1px solid var(--border-color);
    display: flex; flex-direction: column; gap: 12px;
}
.ios-stats-row {
    display: flex; gap: 16px; align-items: center;
    font-size: 13px; color: var(--text-muted);
}
.ios-stats-row i { font-size: 11px; margin-right: 3px; }

/* Reaction pills */
.ios-reactions {
    display: flex; flex-wrap: wrap; gap: 8px;
}
.ios-reaction-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 10px;
    border: 1.5px solid var(--border-color);
    background: var(--bg-secondary);
    cursor: pointer; font-size: 13px; font-family: inherit;
    transition: all .15s ease;
    color: var(--text-primary);
}
.ios-reaction-btn:hover { border-color: var(--primary); background: var(--bg-hover); }
.ios-reaction-btn.active {
    border-color: var(--primary);
    background: rgba(var(--primary-rgb),.1);
    color: var(--primary); font-weight: 600;
}
.ios-reaction-btn .r-emoji { font-size: 17px; line-height: 1; }
.ios-reaction-btn .r-label { font-size: 12px; font-weight: 500; }
.ios-reaction-btn .r-count { font-size: 12px; font-weight: 700; min-width: 10px; }

/* ── Comments section ── */
.ios-comment-form {
    display: flex; gap: 12px; padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}
.ios-comment-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--primary); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 13px; flex-shrink: 0;
}
.ios-comment-input-wrap { flex: 1; }
.ios-comment-input-wrap textarea {
    border-radius: 12px; resize: none; font-size: 14px;
}
.ios-comment-submit {
    display: flex; justify-content: flex-end; margin-top: 8px;
}

.ios-comment-list { padding: 0; }
.ios-comment-item {
    display: flex; gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border-color);
}
.ios-comment-item:last-child { border-bottom: none; }
.ios-comment-item-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--bg-secondary); color: var(--text-secondary);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; flex-shrink: 0;
}
.ios-comment-body { flex: 1; min-width: 0; }
.ios-comment-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 4px; }
.ios-comment-meta strong { color: var(--text-primary); font-weight: 600; margin-right: 4px; }
.ios-comment-text { font-size: 14px; color: var(--text-primary); line-height: 1.6; }
.ios-comment-actions { font-size: 12px; margin-top: 5px; display: flex; gap: 12px; }
.ios-comment-actions a {
    color: var(--text-muted); text-decoration: none; cursor: pointer;
    transition: color .15s ease;
}
.ios-comment-actions a:hover { color: var(--primary); }
.ios-comment-actions .del-link:hover { color: var(--ios-red); }

.ios-replies { margin-left: 46px; border-left: 2px solid var(--border-color); }
.ios-replies .ios-comment-item { padding-left: 14px; }

.ios-empty-comments {
    text-align: center; padding: 36px 20px;
    color: var(--text-muted); font-size: 13px;
}
.ios-empty-comments i { font-size: 28px; display: block; margin-bottom: 10px; opacity: .35; }

/* ── Right sidebar ── */
.ios-sidebar { display: flex; flex-direction: column; }
.ios-sidebar-sticky { position: sticky; top: var(--spacing-4); }

/* Detail rows */
.ios-detail-rows { padding: 0; }
.ios-detail-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
    font-size: 14px;
}
.ios-detail-row:last-child { border-bottom: none; }
.ios-detail-row-label { color: var(--text-muted); font-size: 13px; }
.ios-detail-row-value { color: var(--text-primary); font-weight: 500; text-align: right; }

/* Admin actions in sidebar */
.ios-admin-actions { padding: var(--spacing-4); display: flex; flex-direction: column; gap: var(--spacing-3); }

/* ── iOS bottom-sheet menu ── */
.ios-menu-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.4);
    backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    z-index: 9998; opacity: 0; visibility: hidden;
    transition: opacity .3s ease, visibility .3s ease;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-primary);
    border-radius: 16px 16px 0 0;
    z-index: 9999; transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 85vh; overflow: hidden;
    display: flex; flex-direction: column;
}
.ios-menu-modal.active { transform: translateY(0); }
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ios-menu-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; transition: background .2s; }
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-left: 4px; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background .15s; cursor: pointer; width: 100%; background: transparent; border-left: none; border-right: none; border-top: none; font-family: inherit; font-size: inherit; text-align: left; }
button.ios-menu-item { border: none; border-bottom: 1px solid var(--border-color); }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-menu-item-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-menu-item-icon.red    { background: rgba(255,69,58,.15);  color: var(--ios-red); }
.ios-menu-item-label { font-size: 15px; font-weight: 500; }
.ios-menu-item-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .ios-sidebar { display: none; }
    .ios-view-layout { grid-template-columns: 1fr; }
    .ios-detail-title { font-size: 18px; }
    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 14px; }
    .ios-section-icon { width: 36px; height: 36px; font-size: 16px; }
    .ios-section-title h5 { font-size: 15px; }
    .ios-detail-header { padding: 14px 16px; }
    .ios-detail-body { padding: 14px 16px; }
    .ios-detail-footer { padding: 12px 16px; }
    .ios-comment-form { padding: 12px 16px; }
    .ios-comment-item { padding: 12px 16px; }
}
@media (max-width: 480px) {
    .ios-author-avatar { width: 40px; height: 40px; font-size: 14px; }
    .ios-reaction-btn { padding: 6px 10px; }
}
</style>

<!-- ===== DESKTOP PAGE HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-start">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>announcements/index.php" class="breadcrumb-link">Announcements</a>
                </li>
                <li class="breadcrumb-item active">View</li>
            </ol>
        </nav>
        <h1 class="content-title"><?php echo e($ann['title']); ?></h1>
        <p class="content-subtitle">
            <?php if ($ann['is_pinned']): ?>
            <span class="ios-pin-badge me-1"><i class="fas fa-thumbtack"></i> Pinned</span>
            <?php endif; ?>
            <?php if (!$ann['is_published']): ?>
            <span class="ios-draft-badge me-1">Draft</span>
            <?php endif; ?>
            By <?php echo e($ann['author_name']); ?> ·
            <?php echo formatDate($ann['created_at'], 'd M Y, g:i A'); ?> ·
            <i class="fas fa-eye" style="font-size:11px"></i> <?php echo number_format($ann['views']); ?> views
        </p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2 flex-shrink-0">
        <a href="<?php echo BASE_URL; ?>announcements/form.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-edit me-1"></i>Edit
        </a>
        <form method="POST" action="<?php echo BASE_URL; ?>announcements/actions.php" class="d-inline">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="<?php echo $ann['is_published'] ? 'unpublish' : 'publish'; ?>">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <button type="submit" class="btn btn-<?php echo $ann['is_published'] ? 'warning' : 'success'; ?> btn-sm">
                <i class="fas fa-<?php echo $ann['is_published'] ? 'eye-slash' : 'globe'; ?> me-1"></i>
                <?php echo $ann['is_published'] ? 'Unpublish' : 'Publish'; ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- ===== MOBILE HEADER (hidden on desktop) ===== -->
<div class="ios-section-header d-md-none mb-3" style="border-radius:14px;border:1px solid var(--border-color)">
    <div class="ios-section-icon orange"><i class="fas fa-bullhorn"></i></div>
    <div class="ios-section-title">
        <h5><?php echo e($ann['title']); ?></h5>
        <p><?php echo formatDate($ann['created_at'], 'd M Y'); ?><?php if ($ann['is_pinned']): ?> · 📌 Pinned<?php endif; ?></p>
    </div>
    <button class="ios-options-btn" onclick="openIosMenu()"><i class="fas fa-ellipsis-v"></i></button>
</div>

<div class="ios-view-layout">

    <!-- ===== LEFT: MAIN CONTENT ===== -->
    <div>

        <!-- Announcement detail card -->
        <div class="ios-section-card<?php echo $ann['is_pinned'] ? ' pinned' : ''; ?>">

            <!-- Author + title -->
            <div class="ios-detail-header">
                <div class="ios-author-row">
                    <div class="ios-author-info">
                        <div class="ios-author-avatar" style="background:<?php echo $avatarColor; ?>">
                            <?php echo $authorInitial; ?>
                        </div>
                        <div>
                            <p class="ios-author-name"><?php echo e($ann['author_name']); ?></p>
                            <p class="ios-author-meta">
                                <i class="fas fa-clock" style="font-size:10px"></i>
                                <?php echo formatDate($ann['created_at'], 'd M Y, g:i A'); ?>
                            </p>
                        </div>
                    </div>
                    <div class="ios-badges">
                        <?php if ($ann['is_pinned']): ?>
                        <span class="ios-pin-badge"><i class="fas fa-thumbtack"></i> Pinned</span>
                        <?php endif; ?>
                        <?php if (!$ann['is_published']): ?>
                        <span class="ios-draft-badge">Draft</span>
                        <?php endif; ?>
                    </div>
                </div>
                <h1 class="ios-detail-title"><?php echo e($ann['title']); ?></h1>
            </div>

            <!-- Featured image -->
            <?php if ($ann['image_path']): ?>
            <img src="<?php echo e($ann['image_path']); ?>"
                 alt="<?php echo e($ann['title']); ?>"
                 class="ios-featured-image">
            <?php endif; ?>

            <!-- Content -->
            <div class="ios-detail-body">
                <div class="ios-ann-content">
                    <?php
                    $allowed = '<b><i><strong><em><u><a><ul><ol><li><p><br><h2><h3><h4><blockquote><hr><img>';
                    echo strip_tags($ann['content'], $allowed);
                    ?>
                </div>
            </div>

            <!-- Footer: stats + reactions -->
            <div class="ios-detail-footer">
                <div class="ios-stats-row">
                    <span><i class="fas fa-eye"></i><?php echo number_format($ann['views']); ?> views</span>
                    <span><i class="fas fa-comments"></i><span id="commentCount"><?php echo count($comments); ?></span> comments</span>
                    <span><i class="fas fa-heart"></i><?php echo $totalReactions; ?> reactions</span>
                </div>
                <div class="ios-reactions" id="reactionBar">
                    <?php
                    $emojis = ['like' => ['emoji' => '👍', 'label' => 'Like'], 'love' => ['emoji' => '❤️', 'label' => 'Love'], 'support' => ['emoji' => '🤝', 'label' => 'Support'], 'celebrate' => ['emoji' => '🎉', 'label' => 'Celebrate']];
                    foreach ($emojis as $type => $meta):
                    ?>
                    <button class="ios-reaction-btn<?php echo $reactions['user_reaction'] === $type ? ' active' : ''; ?>"
                            data-type="<?php echo $type; ?>" data-id="<?php echo $id; ?>">
                        <span class="r-emoji"><?php echo $meta['emoji']; ?></span>
                        <span class="r-label"><?php echo $meta['label']; ?></span>
                        <span class="r-count" id="rc-<?php echo $type; ?>"><?php echo $reactions[$type] > 0 ? $reactions[$type] : ''; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div><!-- /detail card -->

        <!-- Comments card -->
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-icon blue"><i class="fas fa-comments"></i></div>
                <div class="ios-section-title">
                    <h5>Comments <span id="commentBadge" style="font-size:13px;font-weight:500;color:var(--text-muted)">(<?php echo count($comments); ?>)</span></h5>
                    <p>Share your thoughts on this announcement</p>
                </div>
            </div>

            <!-- Comment form -->
            <div class="ios-comment-form">
                <div class="ios-comment-avatar">
                    <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="ios-comment-input-wrap">
                    <textarea id="newComment" class="form-control" rows="2"
                              placeholder="Write a comment…"></textarea>
                    <div class="ios-comment-submit">
                        <button class="btn btn-primary btn-sm" id="submitComment" data-id="<?php echo $id; ?>">
                            <i class="fas fa-paper-plane me-1"></i>Post
                        </button>
                    </div>
                </div>
            </div>

            <!-- Comment list -->
            <div class="ios-comment-list" id="commentList">
                <?php if (empty($rootComments)): ?>
                <div class="ios-empty-comments" id="noComments">
                    <i class="fas fa-comments"></i>
                    No comments yet. Be the first!
                </div>
                <?php else: ?>
                <?php foreach ($rootComments as $c): ?>
                <?php renderComment($c, $id, $userId); ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /left -->

    <!-- ===== RIGHT: SIDEBAR (desktop only) ===== -->
    <div class="ios-sidebar">
        <div class="ios-sidebar-sticky">

            <!-- Details card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon orange"><i class="fas fa-info-circle"></i></div>
                    <div class="ios-section-title"><h5>Details</h5></div>
                </div>
                <div class="ios-detail-rows">
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Author</span>
                        <span class="ios-detail-row-value"><?php echo e($ann['author_name']); ?></span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Status</span>
                        <span class="ios-detail-row-value">
                            <?php if ($ann['is_published']): ?>
                            <span style="color:var(--ios-green);font-weight:600">Published</span>
                            <?php else: ?>
                            <span style="color:var(--ios-orange);font-weight:600">Draft</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Published</span>
                        <span class="ios-detail-row-value"><?php echo formatDate($ann['created_at'], 'd M Y'); ?></span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Last updated</span>
                        <span class="ios-detail-row-value"><?php echo formatDate($ann['updated_at'], 'd M Y'); ?></span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Views</span>
                        <span class="ios-detail-row-value"><?php echo number_format($ann['views']); ?></span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Comments</span>
                        <span class="ios-detail-row-value"><?php echo count($comments); ?></span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Reactions</span>
                        <span class="ios-detail-row-value"><?php echo $totalReactions; ?></span>
                    </div>
                </div>
            </div>

            <!-- Admin actions card -->
            <?php if (isAdmin()): ?>
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon purple"><i class="fas fa-cog"></i></div>
                    <div class="ios-section-title"><h5>Admin Actions</h5></div>
                </div>
                <div class="ios-admin-actions">
                    <a href="<?php echo BASE_URL; ?>announcements/form.php?id=<?php echo $id; ?>"
                       class="btn btn-secondary w-100">
                        <i class="fas fa-edit me-2"></i>Edit Announcement
                    </a>
                    <form method="POST" action="<?php echo BASE_URL; ?>announcements/actions.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="<?php echo $ann['is_published'] ? 'unpublish' : 'publish'; ?>">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <button type="submit" class="btn btn-<?php echo $ann['is_published'] ? 'warning' : 'success'; ?> w-100">
                            <i class="fas fa-<?php echo $ann['is_published'] ? 'eye-slash' : 'globe'; ?> me-2"></i>
                            <?php echo $ann['is_published'] ? 'Unpublish' : 'Publish'; ?>
                        </button>
                    </form>
                    <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="btn btn-secondary w-100">
                        <i class="fas fa-list me-2"></i>Manage Announcements
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Back link -->
            <div class="ios-section-card">
                <div class="ios-admin-actions">
                    <a href="<?php echo BASE_URL; ?>announcements/index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-arrow-left me-2"></i>Back to Feed
                    </a>
                </div>
            </div>

        </div>
    </div><!-- /sidebar -->

</div><!-- /ios-view-layout -->

<!-- ===== iOS MENU MODAL (mobile) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeIosMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h5 class="ios-menu-title">Announcement</h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <?php if (isAdmin()): ?>
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Admin Actions</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>announcements/form.php?id=<?php echo $id; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-edit"></i></div>
                        <div>
                            <div class="ios-menu-item-label">Edit Announcement</div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <form method="POST" action="<?php echo BASE_URL; ?>announcements/actions.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="<?php echo $ann['is_published'] ? 'unpublish' : 'publish'; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="ios-menu-item">
                        <div class="ios-menu-item-left">
                            <div class="ios-menu-item-icon <?php echo $ann['is_published'] ? 'orange' : 'green'; ?>">
                                <i class="fas fa-<?php echo $ann['is_published'] ? 'eye-slash' : 'globe'; ?>"></i>
                            </div>
                            <div class="ios-menu-item-label"><?php echo $ann['is_published'] ? 'Unpublish' : 'Publish'; ?></div>
                        </div>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </button>
                </form>
                <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-label">Manage Announcements</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>announcements/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-bullhorn"></i></div>
                        <div class="ios-menu-item-label">Announcements Feed</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-home"></i></div>
                        <div class="ios-menu-item-label">Dashboard</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
const BASE_URL     = '<?php echo BASE_URL; ?>';
const ANN_ID       = <?php echo $id; ?>;
const USER_INITIAL = '<?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>';

/* ── iOS menu ── */
function openIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.add('active');
    document.getElementById('iosMenuModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.remove('active');
    document.getElementById('iosMenuModal').classList.remove('active');
    document.body.style.overflow = '';
}
(function() {
    var modal = document.getElementById('iosMenuModal');
    var startY = 0, isDragging = false;
    modal.addEventListener('touchstart', function(e) { startY = e.touches[0].clientY; isDragging = true; }, { passive: true });
    modal.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        var dy = e.touches[0].clientY - startY;
        if (dy > 0) modal.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    modal.addEventListener('touchend', function(e) {
        if (!isDragging) return;
        isDragging = false;
        var dy = e.changedTouches[0].clientY - startY;
        modal.style.transform = '';
        if (dy > 80) closeIosMenu();
    });
})();

/* ── Reactions ── */
document.querySelectorAll('.ios-reaction-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var type = this.dataset.type;
        fetch(BASE_URL + 'api/announcement-reaction.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ announcement_id: ANN_ID, reaction: type })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { console.warn('Reaction error:', data.message); return; }
            document.querySelectorAll('.ios-reaction-btn').forEach(function(b) {
                b.classList.remove('active');
                var cnt = document.getElementById('rc-' + b.dataset.type);
                if (cnt) cnt.textContent = data.counts[b.dataset.type] > 0 ? data.counts[b.dataset.type] : '';
            });
            if (data.user_reaction) {
                var active = document.querySelector('[data-type="' + data.user_reaction + '"]');
                if (active) active.classList.add('active');
            }
        })
        .catch(function(err) { console.error('Reaction fetch error:', err); });
    });
});

/* ── Comments ── */
document.getElementById('submitComment').addEventListener('click', function() {
    var content = document.getElementById('newComment').value.trim();
    if (!content) return;
    postComment(ANN_ID, null, content, function(html) {
        var noComments = document.getElementById('noComments');
        if (noComments) noComments.remove();
        document.getElementById('commentList').insertAdjacentHTML('afterbegin', html);
        document.getElementById('newComment').value = '';
        incrementCommentCount();
    });
});

function postComment(annId, parentId, content, onSuccess) {
    var body = new URLSearchParams({ announcement_id: annId, content: content });
    if (parentId) body.append('parent_id', parentId);
    fetch(BASE_URL + 'api/announcement-comment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.success) { console.warn('Comment error:', data.message); return; }
        onSuccess(data.html);
    })
    .catch(function(err) { console.error('Comment fetch error:', err); });
}

function incrementCommentCount() {
    var el  = document.getElementById('commentCount');
    var el2 = document.getElementById('commentBadge');
    var n = parseInt(el.textContent || 0) + 1;
    el.textContent = n;
    if (el2) el2.textContent = '(' + n + ')';
}

/* Delegated reply handler */
document.getElementById('commentList').addEventListener('click', function(e) {
    var replyLink = e.target.closest('.reply-link');
    if (!replyLink) return;
    e.preventDefault();
    var commentId = replyLink.dataset.id;
    var existing  = document.getElementById('replyForm-' + commentId);
    if (existing) { existing.remove(); return; }

    var form = document.createElement('div');
    form.id = 'replyForm-' + commentId;
    form.className = 'mt-2';
    form.innerHTML =
        '<div class="d-flex gap-2">' +
        '<textarea class="form-control" rows="2" placeholder="Write a reply…" id="replyText-' + commentId + '" style="resize:none;font-size:13px;border-radius:10px"></textarea>' +
        '<button class="btn btn-primary btn-sm align-self-end" onclick="submitReply(' + commentId + ',' + ANN_ID + ')">Reply</button>' +
        '</div>';
    replyLink.closest('.ios-comment-item').querySelector('.ios-comment-body').appendChild(form);
});

function submitReply(parentId, annId) {
    var content = document.getElementById('replyText-' + parentId).value.trim();
    if (!content) return;
    postComment(annId, parentId, content, function(html) {
        var form = document.getElementById('replyForm-' + parentId);
        var repliesDiv = document.getElementById('replies-' + parentId);
        if (!repliesDiv) {
            repliesDiv = document.createElement('div');
            repliesDiv.id = 'replies-' + parentId;
            repliesDiv.className = 'ios-replies';
            form.parentNode.parentNode.after(repliesDiv);
        }
        repliesDiv.insertAdjacentHTML('beforeend', html);
        form.remove();
        incrementCommentCount();
    });
}

function deleteComment(id) {
    if (!confirm('Delete this comment?')) return;
    fetch(BASE_URL + 'api/announcement-comment.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ comment_id: id })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var el = document.getElementById('comment-' + id);
            if (el) el.remove();
            var cnt  = document.getElementById('commentCount');
            var cnt2 = document.getElementById('commentBadge');
            var n = Math.max(0, parseInt(cnt.textContent || 0) - 1);
            cnt.textContent = n;
            if (cnt2) cnt2.textContent = '(' + n + ')';
        }
    });
}
</script>

<?php
// Helper: render a comment (recursively)
function renderComment(array $comment, int $annId, int $userId): void
{
    $initial   = strtoupper(substr($comment['author_name'] ?: ($comment['author_email'] ?? 'U'), 0, 1));
    $canDelete = isAdmin() || (int)$comment['user_id'] === $userId;
    $colors    = ['#0A84FF','#30D158','#FF9F0A','#BF5AF2','#FF453A'];
    $color     = $colors[ord($initial) % count($colors)];
    ?>
    <div class="ios-comment-item" id="comment-<?php echo $comment['id']; ?>">
        <div class="ios-comment-item-avatar" style="background:<?php echo $color; ?>;color:#fff">
            <?php echo e($initial); ?>
        </div>
        <div class="ios-comment-body">
            <div class="ios-comment-meta">
                <strong><?php echo e($comment['author_name'] ?: ($comment['author_email'] ?? 'Unknown')); ?></strong>
                <?php echo formatDate($comment['created_at'], 'd M Y, g:i A'); ?>
            </div>
            <div class="ios-comment-text"><?php echo nl2br(e($comment['content'])); ?></div>
            <div class="ios-comment-actions">
                <?php if (!$comment['parent_id']): ?>
                <a class="reply-link" data-id="<?php echo $comment['id']; ?>">↩ Reply</a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                <a class="del-link" onclick="deleteComment(<?php echo $comment['id']; ?>)">Delete</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (!empty($comment['replies'])): ?>
    <div class="ios-replies" id="replies-<?php echo $comment['id']; ?>">
        <?php foreach ($comment['replies'] as $reply): ?>
        <?php renderComment($reply, $annId, $userId); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php
}
?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
