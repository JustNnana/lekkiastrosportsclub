<?php
/**
 * Polls — Member view (active + past polls)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Poll.php';

requireLogin();

$pageTitle = 'Polls & Voting';
$pollObj   = new Poll();

$activePage = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 8;

$activeTotal  = $pollObj->countActive();
$activePaged  = paginate($activeTotal, $perPage, $activePage);
$activePolls  = $pollObj->getActive($activePage, $perPage);

$closedPage   = max(1, (int)($_GET['cpage'] ?? 1));
$closedTotal  = $pollObj->countClosed();
$closedPaged  = paginate($closedTotal, $perPage, $closedPage);
$closedPolls  = $pollObj->getClosed($closedPage, $perPage);

$userId = (int)$_SESSION['user_id'];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Polls & Voting</h1>
        <p class="content-subtitle">Have your say on club matters.</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>polls/manage.php" class="btn btn-secondary"><i class="fas fa-cog me-2"></i>Manage</a>
        <a href="<?php echo BASE_URL; ?>polls/form.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>New Poll</a>
    </div>
    <?php endif; ?>
</div>

<!-- Active Polls -->
<h6 class="fw-bold mb-3"><i class="fas fa-vote-yea text-success me-2"></i>Active Polls</h6>

<?php if (empty($activePolls)): ?>
<div class="card text-center py-5 mb-4">
    <div style="font-size:42px;opacity:.3">🗳️</div>
    <p class="text-muted mt-3">No active polls right now. Check back soon!</p>
</div>
<?php else: ?>
<div class="row g-3 mb-4">
    <?php foreach ($activePolls as $p):
        $voted    = $pollObj->getUserVote($p['id'], $userId);
        $timeLeft = strtotime($p['deadline']) - time();
        $days     = max(0, floor($timeLeft / 86400));
        $hours    = max(0, floor(($timeLeft % 86400) / 3600));
    ?>
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="fw-bold mb-0" style="line-height:1.4">
                        <a href="<?php echo BASE_URL; ?>polls/view.php?id=<?php echo $p['id']; ?>"
                           class="text-body text-decoration-none">
                            <?php echo e($p['question']); ?>
                        </a>
                    </h6>
                    <?php if ($voted !== null): ?>
                    <span class="badge badge-success ms-2 flex-shrink-0">Voted</span>
                    <?php endif; ?>
                </div>
                <?php if ($p['description']): ?>
                <p class="text-muted small mb-3"><?php echo e(mb_substr($p['description'], 0, 120)); ?></p>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex gap-3 text-muted small">
                        <span><i class="fas fa-users me-1"></i><?php echo number_format($p['total_votes']); ?> votes</span>
                        <span>
                            <i class="fas fa-clock me-1"></i>
                            <?php echo $days > 0 ? $days . 'd ' . $hours . 'h left' : ($hours . 'h left'); ?>
                        </span>
                    </div>
                    <a href="<?php echo BASE_URL; ?>polls/view.php?id=<?php echo $p['id']; ?>"
                       class="btn btn-primary btn-sm">
                        <?php echo $voted !== null ? 'View Results' : 'Vote Now'; ?> →
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($activePaged['total_pages'] > 1): ?>
<nav class="d-flex justify-content-center mb-4">
    <ul class="pagination pagination-sm">
        <?php if ($activePaged['has_prev']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $activePage-1; ?>">‹</a></li><?php endif; ?>
        <?php for ($i=1;$i<=$activePaged['total_pages'];$i++): ?>
        <li class="page-item <?php echo $i===$activePage?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a></li>
        <?php endfor; ?>
        <?php if ($activePaged['has_next']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $activePage+1; ?>">›</a></li><?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<!-- Closed Polls -->
<?php if (!empty($closedPolls)): ?>
<h6 class="fw-bold mb-3 mt-4"><i class="fas fa-lock text-muted me-2"></i>Past Polls</h6>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Votes</th>
                    <th>Closed</th>
                    <th>Your Vote</th>
                    <th class="text-end">Results</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($closedPolls as $p):
                    $voted = $pollObj->getUserVote($p['id'], $userId);
                    $options = $voted !== null ? $pollObj->getOptions($p['id']) : [];
                    $votedOption = $voted ? array_filter($options, fn($o) => (int)$o['id'] === $voted) : [];
                    $votedOption = $votedOption ? array_values($votedOption)[0] : null;
                ?>
                <tr>
                    <td class="fw-semibold"><?php echo e($p['question']); ?></td>
                    <td class="text-muted"><?php echo number_format($p['total_votes']); ?></td>
                    <td class="text-muted small"><?php echo formatDate($p['deadline'], 'd M Y'); ?></td>
                    <td>
                        <?php if ($votedOption): ?>
                        <span class="badge badge-success"><?php echo e($votedOption['option_text']); ?></span>
                        <?php elseif ($voted === null): ?>
                        <span class="text-muted small">Did not vote</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?php echo BASE_URL; ?>polls/view.php?id=<?php echo $p['id']; ?>"
                           class="btn btn-secondary btn-sm">
                            <i class="fas fa-chart-bar me-1"></i>Results
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($closedPaged['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-end">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php if ($closedPaged['has_prev']): ?><li class="page-item"><a class="page-link" href="?cpage=<?php echo $closedPage-1; ?>">‹</a></li><?php endif; ?>
            <?php for ($i=1;$i<=$closedPaged['total_pages'];$i++): ?>
            <li class="page-item <?php echo $i===$closedPage?'active':''; ?>"><a class="page-link" href="?cpage=<?php echo $i; ?>"><?php echo $i; ?></a></li>
            <?php endfor; ?>
            <?php if ($closedPaged['has_next']): ?><li class="page-item"><a class="page-link" href="?cpage=<?php echo $closedPage+1; ?>">›</a></li><?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
