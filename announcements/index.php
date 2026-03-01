<?php
/**
 * Announcements — Member feed (published only)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

requireLogin();

$pageTitle = 'Announcements';
$annObj    = new Announcement();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$total   = $annObj->countFeed();
$paged   = paginate($total, $perPage, $page);
$items   = $annObj->getFeed($page, $perPage);

// Mark as read so unread badge resets
$annObj->markRead((int)$_SESSION['user_id']);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Announcements</h1>
        <p class="content-subtitle">Stay up to date with the latest club news.</p>
    </div>
    <?php if (isAdmin()): ?>
    <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="btn btn-secondary me-2">
        <i class="fas fa-cog me-2"></i>Manage
    </a>
    <a href="<?php echo BASE_URL; ?>announcements/form.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>New
    </a>
    <?php endif; ?>
</div>

<?php if (empty($items)): ?>
<div class="card text-center py-5">
    <div style="font-size:48px;opacity:.3">📢</div>
    <p class="text-muted mt-3">No announcements yet. Check back soon!</p>
</div>
<?php else: ?>

<div class="announcement-feed">
    <?php foreach ($items as $a): ?>
    <div class="card announcement-card mb-4 <?php echo $a['is_pinned'] ? 'border-start border-4 border-danger' : ''; ?>">
        <?php if ($a['image_path']): ?>
        <div class="announcement-image">
            <img src="<?php echo e($a['image_path']); ?>" alt="<?php echo e($a['title']); ?>"
                 style="width:100%;max-height:280px;object-fit:cover;">
        </div>
        <?php endif; ?>
        <div class="card-body">
            <!-- Meta top -->
            <div class="d-flex align-items-center gap-2 mb-2">
                <?php if ($a['is_pinned']): ?>
                <span class="badge badge-danger" style="font-size:10px"><i class="fas fa-thumbtack me-1"></i>Pinned</span>
                <?php endif; ?>
                <small class="text-muted"><?php echo e($a['author_name']); ?> · <?php echo formatDate($a['created_at'], 'd M Y'); ?></small>
            </div>

            <!-- Title -->
            <h5 class="mb-2">
                <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $a['id']; ?>"
                   class="text-body text-decoration-none fw-bold">
                    <?php echo e($a['title']); ?>
                </a>
            </h5>

            <!-- Excerpt -->
            <div class="text-muted mb-3" style="line-height:1.6">
                <?php
                $excerpt = strip_tags($a['content']);
                echo e(mb_strlen($excerpt) > 220 ? mb_substr($excerpt, 0, 220) . '…' : $excerpt);
                ?>
            </div>

            <!-- Footer: views / comments / reactions -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex gap-3 text-muted small">
                    <span><i class="fas fa-eye me-1"></i><?php echo number_format($a['views']); ?></span>
                    <span><i class="fas fa-comment me-1"></i><?php echo $a['comment_count']; ?></span>
                    <span><i class="fas fa-heart me-1"></i><?php echo $a['reaction_count']; ?></span>
                </div>
                <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $a['id']; ?>"
                   class="btn btn-secondary btn-sm">
                    Read more <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($paged['total_pages'] > 1): ?>
<nav class="d-flex justify-content-center mt-2">
    <ul class="pagination">
        <?php if ($paged['has_prev']): ?>
        <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>">‹ Prev</a></li>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $paged['total_pages']; $i++): ?>
        <li class="page-item <?php echo $i===$page?'active':''; ?>">
            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($paged['has_next']): ?>
        <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>">Next ›</a></li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
