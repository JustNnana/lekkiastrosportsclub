<?php
/**
 * Tournaments — public list for all logged-in users
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Tournament.php';

requireLogin();

$pageTitle = 'Tournaments';
$tourObj   = new Tournament();
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 12;
$status    = sanitize($_GET['status'] ?? '');

$total = $tourObj->countAll($status);
$paged = paginate($total, $perPage, $page);
$items = $tourObj->getAll($page, $perPage, $status);

$formatLabels = ['league'=>'League','knockout'=>'Knockout','group_knockout'=>'Group + Knockout'];
$statusColors = ['setup'=>'warning','active'=>'success','completed'=>'secondary'];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Tournaments</h1>
        <p class="content-subtitle">Club competitions, standings, and fixtures.</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="btn btn-secondary"><i class="fas fa-cog me-2"></i>Manage</a>
        <a href="<?php echo BASE_URL; ?>tournaments/form.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>New</a>
    </div>
    <?php endif; ?>
</div>

<!-- Filter -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <a href="tournaments/index.php" class="btn btn-sm <?php echo !$status?'btn-primary':'btn-secondary'; ?>">All</a>
    <a href="tournaments/index.php?status=active"    class="btn btn-sm <?php echo $status==='active'?'btn-primary':'btn-secondary'; ?>">Active</a>
    <a href="tournaments/index.php?status=completed" class="btn btn-sm <?php echo $status==='completed'?'btn-primary':'btn-secondary'; ?>">Completed</a>
</div>

<?php if (empty($items)): ?>
<div class="card text-center py-5">
    <div style="font-size:48px;opacity:.3">🏆</div>
    <p class="text-muted mt-3">No tournaments yet.</p>
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($items as $t): ?>
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-bold mb-0">
                        <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $t['id']; ?>"
                           class="text-body text-decoration-none"><?php echo e($t['name']); ?></a>
                    </h6>
                    <span class="badge badge-<?php echo $statusColors[$t['status']] ?? 'secondary'; ?> flex-shrink-0 ms-2">
                        <?php echo ucfirst($t['status']); ?>
                    </span>
                </div>
                <?php if ($t['description']): ?>
                <p class="text-muted small mb-3"><?php echo e(mb_substr($t['description'], 0, 100)); ?></p>
                <?php endif; ?>
                <div class="d-flex gap-3 text-muted small mb-3 flex-wrap">
                    <span><i class="fas fa-th me-1"></i><?php echo $formatLabels[$t['format']] ?? $t['format']; ?></span>
                    <span><i class="fas fa-shield-alt me-1"></i><?php echo $t['team_count']; ?> teams</span>
                    <span><i class="fas fa-futbol me-1"></i><?php echo $t['fixture_count']; ?> fixtures</span>
                </div>
                <?php if ($t['start_date']): ?>
                <div class="text-muted small"><i class="fas fa-calendar me-1"></i><?php echo formatDate($t['start_date'],'d M Y'); ?></div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $t['id']; ?>"
                   class="btn btn-secondary btn-sm w-100">View Tournament</a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($paged['total_pages'] > 1): ?>
<nav class="d-flex justify-content-center mt-4">
    <ul class="pagination">
        <?php if ($paged['has_prev']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status; ?>">‹</a></li><?php endif; ?>
        <?php for ($i=1;$i<=$paged['total_pages'];$i++): ?><li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
        <?php if ($paged['has_next']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status; ?>">›</a></li><?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
