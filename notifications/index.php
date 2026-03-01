<?php
/**
 * Notifications — in-app notification center
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireLogin();

$pageTitle = 'Notifications';
$db        = Database::getInstance();
$userId    = (int)$_SESSION['user_id'];
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 20;
$offset    = ($page - 1) * $perPage;

// Mark all as read when viewing this page
$db->execute("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0", [$userId]);

// Total count (for pagination)
$total = (int)($db->fetchOne(
    "SELECT COUNT(*) AS n FROM notifications WHERE user_id = ?",
    [$userId]
)['n'] ?? 0);

$totalPages = max(1, (int)ceil($total / $perPage));

$items = $db->fetchAll(
    "SELECT * FROM notifications WHERE user_id = ?
     ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$userId, $perPage, $offset]
);

$typeIcons = [
    'payment'      => ['fas fa-wallet',       '#f59e0b'],
    'announcement' => ['fas fa-bullhorn',      '#3b82f6'],
    'event'        => ['fas fa-calendar-alt',  '#00a76f'],
    'poll'         => ['fas fa-poll',          '#6366f1'],
    'tournament'   => ['fas fa-trophy',        '#ca8a04'],
    'system'       => ['fas fa-cog',           '#6b7280'],
];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Notifications</h1>
        <p class="content-subtitle"><?php echo $total; ?> total notification<?php echo $total !== 1 ? 's' : ''; ?></p>
    </div>
    <?php if ($total > 0): ?>
    <form method="POST" action="<?php echo BASE_URL; ?>notifications/actions.php" class="d-inline">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="clear_all">
        <button type="submit" class="btn btn-secondary btn-sm"
                onclick="return confirm('Clear all notifications?')">
            <i class="fas fa-trash me-1"></i>Clear All
        </button>
    </form>
    <?php endif; ?>
</div>

<?php if (empty($items)): ?>
<div class="card text-center py-5">
    <div style="font-size:48px;opacity:.3"><i class="fas fa-bell-slash"></i></div>
    <p class="text-muted mt-3">No notifications yet.</p>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body p-0">
        <?php foreach ($items as $i => $n):
            [$icon, $color] = $typeIcons[$n['type']] ?? ['fas fa-info-circle', '#6b7280'];
            $isLast = $i === count($items) - 1;
        ?>
        <div class="d-flex gap-3 p-4 <?php echo !$isLast ? 'border-bottom' : ''; ?>"
             style="cursor:<?php echo $n['link'] ? 'pointer' : 'default'; ?>"
             <?php if ($n['link']): ?>onclick="window.location.href='<?php echo e($n['link']); ?>'"<?php endif; ?>>
            <!-- Icon -->
            <div class="flex-shrink-0" style="width:40px;height:40px;border-radius:50%;background:<?php echo $color; ?>22;display:flex;align-items:center;justify-content:center;color:<?php echo $color; ?>">
                <i class="<?php echo $icon; ?>"></i>
            </div>
            <!-- Content -->
            <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold mb-1" style="font-size:14px"><?php echo e($n['title']); ?></div>
                <div class="text-muted" style="font-size:13px;line-height:1.5"><?php echo e($n['message']); ?></div>
                <div class="text-muted mt-1" style="font-size:11px">
                    <i class="fas fa-clock me-1"></i><?php echo formatDate($n['created_at'], 'd M Y, g:i A'); ?>
                </div>
            </div>
            <?php if ($n['link']): ?>
            <div class="flex-shrink-0 align-self-center">
                <i class="fas fa-chevron-right text-muted" style="font-size:12px"></i>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="d-flex justify-content-center mt-4">
    <ul class="pagination">
        <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>">‹</a></li><?php endif; ?>
        <?php for ($i=1; $i<=$totalPages; $i++): ?><li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
        <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>">›</a></li><?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
