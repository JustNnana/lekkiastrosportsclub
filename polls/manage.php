<?php
/**
 * Polls — Admin management list
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Poll.php';

requireAdmin();

$pageTitle = 'Manage Polls';
$pollObj   = new Poll();

$status  = sanitize($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total = $pollObj->countAll($status);
$paged = paginate($total, $perPage, $page);
$items = $pollObj->getAll($page, $perPage, $status);
$stats = $pollObj->getStats();

// Auto-close expired active polls (best-effort on page load)
$db = Database::getInstance();
$db->execute(
    "UPDATE polls SET status='closed' WHERE status='active' AND deadline < NOW()"
);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Polls & Voting</h1>
        <p class="content-subtitle">Create polls and track member participation.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>polls/form.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>New Poll
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--primary-rgb),.12);color:var(--primary)"><i class="fas fa-poll"></i></div>
            <div class="stat-info"><span class="stat-label">Total Polls</span><span class="stat-value"><?php echo $stats['total']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#16a34a"><i class="fas fa-vote-yea"></i></div>
            <div class="stat-info"><span class="stat-label">Active</span><span class="stat-value"><?php echo $stats['active']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(107,114,128,.12);color:#6b7280"><i class="fas fa-lock"></i></div>
            <div class="stat-info"><span class="stat-label">Closed</span><span class="stat-value"><?php echo $stats['closed']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(234,179,8,.12);color:#ca8a04"><i class="fas fa-users"></i></div>
            <div class="stat-info"><span class="stat-label">Total Votes Cast</span><span class="stat-value"><?php echo number_format($stats['total_votes']); ?></span></div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
            <a href="polls/manage.php" class="btn btn-sm <?php echo !$status ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
            <a href="polls/manage.php?status=active" class="btn btn-sm <?php echo $status==='active' ? 'btn-primary' : 'btn-secondary'; ?>">Active</a>
            <a href="polls/manage.php?status=closed" class="btn btn-sm <?php echo $status==='closed' ? 'btn-primary' : 'btn-secondary'; ?>">Closed</a>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Question</th>
                    <th>Created By</th>
                    <th>Options</th>
                    <th>Votes</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No polls found.</td></tr>
                <?php else: ?>
                <?php foreach ($items as $p):
                    $isClosed = $p['status'] === 'closed' || strtotime($p['deadline']) < time();
                    $isPast   = strtotime($p['deadline']) < time() && $p['status'] === 'active';
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold">
                            <a href="<?php echo BASE_URL; ?>polls/view.php?id=<?php echo $p['id']; ?>"
                               class="text-body text-decoration-none">
                                <?php echo e($p['question']); ?>
                            </a>
                        </div>
                        <?php if ($p['description']): ?>
                        <small class="text-muted"><?php echo e(mb_substr($p['description'], 0, 80)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?php echo e($p['creator_name'] ?: $p['creator_email']); ?></td>
                    <td class="text-center"><?php echo $p['option_count']; ?></td>
                    <td class="fw-semibold"><?php echo number_format($p['total_votes']); ?></td>
                    <td>
                        <div class="<?php echo $isPast ? 'text-danger' : 'text-muted'; ?> small">
                            <?php echo formatDate($p['deadline'], 'd M Y, g:i A'); ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($isClosed): ?>
                            <span class="badge badge-secondary">Closed</span>
                        <?php else: ?>
                            <span class="badge badge-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>polls/view.php?id=<?php echo $p['id']; ?>">
                                        <i class="fas fa-chart-bar me-2"></i>View Results
                                    </a>
                                </li>
                                <?php if (!$isClosed): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>polls/form.php?id=<?php echo $p['id']; ?>">
                                        <i class="fas fa-edit me-2"></i>Edit
                                    </a>
                                </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($isClosed): ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>polls/actions.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="reopen">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="dropdown-item text-success">
                                            <i class="fas fa-redo me-2"></i>Reopen
                                        </button>
                                    </form>
                                </li>
                                <?php else: ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>polls/actions.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="close">
                                        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                        <button type="submit" class="dropdown-item text-warning">
                                            <i class="fas fa-lock me-2"></i>Close Early
                                        </button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-id="<?php echo $p['id']; ?>"
                                            data-question="<?php echo e($p['question']); ?>">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($paged['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?php echo min($total,($page-1)*$perPage+1); ?>–<?php echo min($total,$page*$perPage); ?> of <?php echo $total; ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php if ($paged['has_prev']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status; ?>">‹</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $paged['total_pages']; $i++): ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($paged['has_next']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status; ?>">›</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="<?php echo BASE_URL; ?>polls/actions.php" class="modal-content">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Poll</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong id="deleteQuestion"></strong>? All votes will be permanently removed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('deleteId').value = btn.dataset.id;
    document.getElementById('deleteQuestion').textContent = btn.dataset.question;
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
