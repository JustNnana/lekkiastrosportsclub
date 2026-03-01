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

$userId   = (int)$_SESSION['user_id'];
$comments = $annObj->getComments($id);
$reactions = $annObj->getReactions($id, $userId);

$pageTitle = e($ann['title']);

// Build threaded comment tree
$commentMap  = [];
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

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <nav class="breadcrumb-nav mb-1">
            <a href="<?php echo BASE_URL; ?>announcements/index.php" class="text-muted small">← Announcements</a>
        </nav>
        <h1 class="content-title"><?php echo e($ann['title']); ?></h1>
        <p class="content-subtitle">
            <?php if ($ann['is_pinned']): ?><span class="badge badge-danger me-1"><i class="fas fa-thumbtack me-1"></i>Pinned</span><?php endif; ?>
            <?php if (!$ann['is_published']): ?><span class="badge badge-warning me-1">Draft</span><?php endif; ?>
            By <?php echo e($ann['author_name']); ?> · <?php echo formatDate($ann['created_at'], 'd M Y, g:i A'); ?>
            · <i class="fas fa-eye me-1"></i><?php echo number_format($ann['views']); ?> views
        </p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2">
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

<div class="row g-4">
    <!-- Main content -->
    <div class="col-12 col-lg-8">

        <!-- Featured image -->
        <?php if ($ann['image_path']): ?>
        <div class="card mb-4" style="overflow:hidden">
            <img src="<?php echo e($ann['image_path']); ?>" alt="<?php echo e($ann['title']); ?>"
                 style="width:100%;max-height:380px;object-fit:cover;display:block;">
        </div>
        <?php endif; ?>

        <!-- Content -->
        <div class="card mb-4">
            <div class="card-body announcement-content" style="line-height:1.8;font-size:15px">
                <?php
                // Allow safe HTML subset
                $allowed = '<b><i><strong><em><u><a><ul><ol><li><p><br><h2><h3><h4><blockquote><hr><img>';
                echo strip_tags($ann['content'], $allowed);
                ?>
            </div>
        </div>

        <!-- Reactions -->
        <div class="card mb-4">
            <div class="card-body">
                <p class="text-muted small mb-3">How do you feel about this?</p>
                <div class="d-flex flex-wrap gap-2" id="reactionBar">
                    <?php
                    $emojis = ['like' => '👍', 'love' => '❤️', 'support' => '🤝', 'celebrate' => '🎉'];
                    foreach ($emojis as $type => $emoji):
                    ?>
                    <button class="btn btn-reaction <?php echo $reactions['user_reaction'] === $type ? 'active' : ''; ?>"
                            data-type="<?php echo $type; ?>"
                            data-id="<?php echo $id; ?>">
                        <span class="emoji"><?php echo $emoji; ?></span>
                        <span class="label"><?php echo ucfirst($type); ?></span>
                        <span class="count" id="rc-<?php echo $type; ?>"><?php echo $reactions[$type] > 0 ? $reactions[$type] : ''; ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Comments -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="fas fa-comments me-2"></i>Comments
                    <span class="badge badge-secondary ms-1" id="commentCount"><?php echo count($comments); ?></span>
                </h6>
            </div>
            <div class="card-body">
                <!-- Comment form -->
                <div class="d-flex gap-3 mb-4">
                    <div class="avatar-sm flex-shrink-0">
                        <div style="width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px">
                            <?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>
                        </div>
                    </div>
                    <div class="flex-fill">
                        <textarea id="newComment" class="form-control" rows="2"
                                  placeholder="Write a comment…" style="resize:none"></textarea>
                        <div class="d-flex justify-content-end mt-2">
                            <button class="btn btn-primary btn-sm" id="submitComment"
                                    data-id="<?php echo $id; ?>">
                                <i class="fas fa-paper-plane me-1"></i>Post
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Comment list -->
                <div id="commentList">
                    <?php if (empty($rootComments)): ?>
                    <p class="text-muted text-center py-3" id="noComments">No comments yet. Be the first!</p>
                    <?php else: ?>
                    <?php foreach ($rootComments as $c): ?>
                    <?php renderComment($c, $id, $userId); ?>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- Sidebar info -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="card-title mb-0">Details</h6></div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="text-muted">Author</span>
                    <span class="fw-semibold"><?php echo e($ann['author_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Published</span>
                    <span><?php echo $ann['is_published'] ? formatDate($ann['created_at'], 'd M Y') : '<span class="badge badge-warning">Draft</span>'; ?></span>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Last updated</span>
                    <span><?php echo formatDate($ann['updated_at'], 'd M Y'); ?></span>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Views</span>
                    <span><?php echo number_format($ann['views']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Comments</span>
                    <span><?php echo count($comments); ?></span>
                </div>
                <div class="detail-row" style="border:none">
                    <span class="text-muted">Total reactions</span>
                    <span><?php echo array_sum([$reactions['like'], $reactions['love'], $reactions['support'], $reactions['celebrate']]); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.detail-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid var(--border-color); font-size:14px; }
.btn-reaction {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 8px;
    border: 1.5px solid var(--border-color);
    background: var(--surface-2); cursor: pointer; font-size: 13px;
    transition: all .15s;
}
.btn-reaction:hover  { border-color: var(--primary); }
.btn-reaction.active { border-color: var(--primary); background: rgba(var(--primary-rgb),.1); color: var(--primary); font-weight:600; }
.btn-reaction .count { font-weight: 600; }
.comment-item { display: flex; gap: 12px; margin-bottom: 20px; }
.comment-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--primary); color:#fff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; }
.comment-body { flex: 1; }
.comment-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 4px; }
.comment-text { font-size: 14px; line-height: 1.6; }
.comment-actions { font-size: 12px; margin-top: 6px; }
.comment-actions a { color: var(--text-muted); text-decoration:none; margin-right: 12px; cursor:pointer; }
.comment-actions a:hover { color: var(--primary); }
.replies { margin-left: 46px; margin-top: 12px; }
.reply-form { margin-top: 8px; }
</style>

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
const ANN_ID   = <?php echo $id; ?>;
const USER_INITIAL = '<?php echo strtoupper(substr($_SESSION['name'] ?? 'U', 0, 1)); ?>';

// ─── Reactions ─────────────────────────────────────────────
document.querySelectorAll('.btn-reaction').forEach(btn => {
    btn.addEventListener('click', function() {
        const type = this.dataset.type;
        fetch(BASE_URL + 'api/announcement-reaction.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({announcement_id: ANN_ID, reaction: type})
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            // Update all buttons
            document.querySelectorAll('.btn-reaction').forEach(b => {
                b.classList.remove('active');
                const cnt = document.getElementById('rc-' + b.dataset.type);
                cnt.textContent = data.counts[b.dataset.type] > 0 ? data.counts[b.dataset.type] : '';
            });
            if (data.user_reaction) {
                const active = document.querySelector('[data-type="' + data.user_reaction + '"]');
                if (active) active.classList.add('active');
            }
        });
    });
});

// ─── Comments ──────────────────────────────────────────────
document.getElementById('submitComment').addEventListener('click', function() {
    const content = document.getElementById('newComment').value.trim();
    if (!content) return;
    postComment(ANN_ID, null, content, function(html) {
        const noComments = document.getElementById('noComments');
        if (noComments) noComments.remove();
        document.getElementById('commentList').insertAdjacentHTML('beforeend', html);
        document.getElementById('newComment').value = '';
        incrementCommentCount();
    });
});

function postComment(annId, parentId, content, onSuccess) {
    const body = new URLSearchParams({announcement_id: annId, content: content});
    if (parentId) body.append('parent_id', parentId);
    fetch(BASE_URL + 'api/announcement-comment.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    })
    .then(r => r.json())
    .then(data => { if (data.success) onSuccess(data.html); });
}

function incrementCommentCount() {
    const el = document.getElementById('commentCount');
    el.textContent = parseInt(el.textContent || 0) + 1;
}

// Reply button handler (delegated)
document.getElementById('commentList').addEventListener('click', function(e) {
    const replyLink = e.target.closest('.reply-link');
    if (!replyLink) return;
    e.preventDefault();
    const commentId = replyLink.dataset.id;
    const existing  = document.getElementById('replyForm-' + commentId);
    if (existing) { existing.remove(); return; }

    const form = document.createElement('div');
    form.id = 'replyForm-' + commentId;
    form.className = 'reply-form';
    form.innerHTML = `
        <div class="d-flex gap-2 mt-2">
            <textarea class="form-control" rows="2" placeholder="Write a reply…" style="resize:none;font-size:13px" id="replyText-${commentId}"></textarea>
            <button class="btn btn-primary btn-sm align-self-end" onclick="submitReply(${commentId}, ${ANN_ID})">Reply</button>
        </div>`;
    replyLink.closest('.comment-item').querySelector('.comment-body').appendChild(form);
});

function submitReply(parentId, annId) {
    const content = document.getElementById('replyText-' + parentId).value.trim();
    if (!content) return;
    postComment(annId, parentId, content, function(html) {
        const form = document.getElementById('replyForm-' + parentId);
        let repliesDiv = document.getElementById('replies-' + parentId);
        if (!repliesDiv) {
            repliesDiv = document.createElement('div');
            repliesDiv.id = 'replies-' + parentId;
            repliesDiv.className = 'replies';
            form.parentNode.parentNode.after(repliesDiv);
        }
        repliesDiv.insertAdjacentHTML('beforeend', html);
        form.remove();
        incrementCommentCount();
    });
}
</script>

<?php
// Helper: render a comment (and recursively its replies)
function renderComment(array $comment, int $annId, int $userId): void
{
    $initial = strtoupper(substr($comment['author_name'] ?: $comment['author_email'], 0, 1));
    $canDelete = isAdmin() || (int)$comment['user_id'] === $userId;
    ?>
    <div class="comment-item" id="comment-<?php echo $comment['id']; ?>">
        <div class="comment-avatar"><?php echo e($initial); ?></div>
        <div class="comment-body">
            <div class="comment-meta">
                <strong><?php echo e($comment['author_name'] ?: $comment['author_email']); ?></strong>
                · <?php echo formatDate($comment['created_at'], 'd M Y, g:i A'); ?>
            </div>
            <div class="comment-text"><?php echo nl2br(e($comment['content'])); ?></div>
            <div class="comment-actions">
                <?php if (!$comment['parent_id']): ?>
                <a class="reply-link" data-id="<?php echo $comment['id']; ?>">↩ Reply</a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                <a onclick="deleteComment(<?php echo $comment['id']; ?>)" class="text-danger">Delete</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if (!empty($comment['replies'])): ?>
    <div class="replies" id="replies-<?php echo $comment['id']; ?>">
        <?php foreach ($comment['replies'] as $reply): ?>
        <?php renderComment($reply, $annId, $userId); ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
<?php
}
?>

<script>
function deleteComment(id) {
    if (!confirm('Delete this comment?')) return;
    fetch(BASE_URL + 'api/announcement-comment.php', {
        method: 'DELETE',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({comment_id: id})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const el = document.getElementById('comment-' + id);
            if (el) el.remove();
            const cnt = document.getElementById('commentCount');
            cnt.textContent = Math.max(0, parseInt(cnt.textContent || 0) - 1);
        }
    });
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
