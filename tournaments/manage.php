<?php
/**
 * Tournaments — Admin management list
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Tournament.php';

requireAdmin();

$pageTitle  = 'Tournaments';
$tourObj    = new Tournament();

$status  = sanitize($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total = $tourObj->countAll($status);
$paged = paginate($total, $perPage, $page);
$items = $tourObj->getAll($page, $perPage, $status);
$stats = $tourObj->getStats();

$formatLabels = ['league'=>'League','knockout'=>'Knockout','group_knockout'=>'Group + Knockout'];
$statusColors = ['setup'=>'warning','active'=>'success','completed'=>'secondary'];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Tournaments</h1>
        <p class="content-subtitle">Manage club tournaments, fixtures, and standings.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>tournaments/form.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>New Tournament
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--primary-rgb),.12);color:var(--primary)"><i class="fas fa-trophy"></i></div>
            <div class="stat-info"><span class="stat-label">Total</span><span class="stat-value"><?php echo $stats['total']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(234,179,8,.12);color:#ca8a04"><i class="fas fa-cog"></i></div>
            <div class="stat-info"><span class="stat-label">Setup</span><span class="stat-value"><?php echo $stats['setup']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#16a34a"><i class="fas fa-play"></i></div>
            <div class="stat-info"><span class="stat-label">Active</span><span class="stat-value"><?php echo $stats['active']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(107,114,128,.12);color:#6b7280"><i class="fas fa-flag-checkered"></i></div>
            <div class="stat-info"><span class="stat-label">Completed</span><span class="stat-value"><?php echo $stats['completed']; ?></span></div>
        </div>
    </div>
</div>

<!-- Filter -->
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex gap-2 flex-wrap">
            <a href="tournaments/manage.php" class="btn btn-sm <?php echo !$status?'btn-primary':'btn-secondary'; ?>">All</a>
            <a href="tournaments/manage.php?status=setup"     class="btn btn-sm <?php echo $status==='setup'?'btn-primary':'btn-secondary'; ?>">Setup</a>
            <a href="tournaments/manage.php?status=active"    class="btn btn-sm <?php echo $status==='active'?'btn-primary':'btn-secondary'; ?>">Active</a>
            <a href="tournaments/manage.php?status=completed" class="btn btn-sm <?php echo $status==='completed'?'btn-primary':'btn-secondary'; ?>">Completed</a>
        </div>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Format</th>
                    <th>Groups</th>
                    <th>Teams</th>
                    <th>Fixtures</th>
                    <th>Start Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="8" class="text-center text-muted py-5">No tournaments yet.</td></tr>
                <?php else: ?>
                <?php foreach ($items as $t): ?>
                <tr>
                    <td>
                        <div class="fw-semibold">
                            <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $t['id']; ?>"
                               class="text-body text-decoration-none"><?php echo e($t['name']); ?></a>
                        </div>
                        <small class="text-muted"><?php echo e($t['creator_name']); ?></small>
                    </td>
                    <td class="text-muted small"><?php echo $formatLabels[$t['format']] ?? $t['format']; ?></td>
                    <td class="text-center"><?php echo $t['group_count']; ?></td>
                    <td class="text-center"><?php echo $t['team_count']; ?></td>
                    <td class="text-center"><?php echo $t['fixture_count']; ?></td>
                    <td class="text-muted small"><?php echo $t['start_date'] ? formatDate($t['start_date'],'d M Y') : '—'; ?></td>
                    <td><span class="badge badge-<?php echo $statusColors[$t['status']] ?? 'secondary'; ?>"><?php echo ucfirst($t['status']); ?></span></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $t['id']; ?>"><i class="fas fa-eye me-2"></i>View</a></li>
                                <?php if ($t['status'] === 'setup'): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>tournaments/form.php?id=<?php echo $t['id']; ?>"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>tournaments/setup.php?id=<?php echo $t['id']; ?>"><i class="fas fa-layer-group me-2"></i>Setup Groups & Teams</a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $t['id']; ?>"><i class="fas fa-futbol me-2"></i>Add Fixture</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($t['status'] === 'setup'): ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php">
                                        <?php echo csrfField(); ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <button class="dropdown-item text-success"><i class="fas fa-play me-2"></i>Activate</button>
                                    </form>
                                </li>
                                <?php elseif ($t['status'] === 'active'): ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php">
                                        <?php echo csrfField(); ?><input type="hidden" name="action" value="complete"><input type="hidden" name="id" value="<?php echo $t['id']; ?>">
                                        <button class="dropdown-item"><i class="fas fa-flag-checkered me-2"></i>Mark Completed</button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-id="<?php echo $t['id']; ?>" data-name="<?php echo e($t['name']); ?>">
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
            <?php if ($paged['has_prev']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status; ?>">‹</a></li><?php endif; ?>
            <?php for ($i=1;$i<=$paged['total_pages'];$i++): ?><li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
            <?php if ($paged['has_next']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status; ?>">›</a></li><?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="modal-content">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="deleteId">
            <div class="modal-header"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Tournament</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p>Delete <strong id="deleteName"></strong>? All groups, teams, fixtures, and stats will be removed.</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete</button></div>
        </form>
    </div>
</div>
<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('deleteId').value = e.relatedTarget.dataset.id;
    document.getElementById('deleteName').textContent = e.relatedTarget.dataset.name;
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
