<?php
/**
 * Events — Admin management list
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Event.php';

requireAdmin();

$pageTitle  = 'Manage Events';
$eventObj   = new Event();

$type    = sanitize($_GET['type']   ?? '');
$status  = sanitize($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total = $eventObj->countAll($type, $status);
$paged = paginate($total, $perPage, $page);
$items = $eventObj->getAll($page, $perPage, $type, $status);
$stats = $eventObj->getStats();

$typeLabels = [
    'training' => ['label' => 'Training',  'color' => '#3b82f6'],
    'match'    => ['label' => 'Match',     'color' => '#ef4444'],
    'meeting'  => ['label' => 'Meeting',   'color' => '#f59e0b'],
    'social'   => ['label' => 'Social',    'color' => '#8b5cf6'],
    'other'    => ['label' => 'Other',     'color' => '#6b7280'],
];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Events & Calendar</h1>
        <p class="content-subtitle">Schedule and manage club events.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>events/index.php" class="btn btn-secondary">
            <i class="fas fa-calendar me-2"></i>Calendar View
        </a>
        <a href="<?php echo BASE_URL; ?>events/form.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>New Event
        </a>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--primary-rgb),.12);color:var(--primary)"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-info"><span class="stat-label">Total Events</span><span class="stat-value"><?php echo $stats['total']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#16a34a"><i class="fas fa-arrow-right"></i></div>
            <div class="stat-info"><span class="stat-label">Upcoming</span><span class="stat-value"><?php echo $stats['upcoming']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(107,114,128,.12);color:#6b7280"><i class="fas fa-check"></i></div>
            <div class="stat-info"><span class="stat-label">Completed</span><span class="stat-value"><?php echo $stats['completed']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--danger-rgb),.12);color:var(--danger)"><i class="fas fa-times"></i></div>
            <div class="stat-info"><span class="stat-label">Cancelled</span><span class="stat-value"><?php echo $stats['cancelled']; ?></span></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-control">
                    <option value="">All Types</option>
                    <?php foreach ($typeLabels as $val => $t): ?>
                    <option value="<?php echo $val; ?>" <?php echo $type===$val?'selected':''; ?>><?php echo $t['label']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    <option value="active"    <?php echo $status==='active'?'selected':''; ?>>Active</option>
                    <option value="completed" <?php echo $status==='completed'?'selected':''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $status==='cancelled'?'selected':''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-12 col-md-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="events/manage.php" class="btn btn-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Type</th>
                    <th>Start</th>
                    <th>Location</th>
                    <th>RSVP</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No events found.</td></tr>
                <?php else: ?>
                <?php foreach ($items as $ev): ?>
                <tr>
                    <td>
                        <div class="fw-semibold">
                            <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $ev['id']; ?>"
                               class="text-body text-decoration-none"><?php echo e($ev['title']); ?></a>
                        </div>
                        <small class="text-muted"><?php echo e($ev['creator_name']); ?></small>
                    </td>
                    <td>
                        <?php $tl = $typeLabels[$ev['event_type']] ?? ['label'=>$ev['event_type'],'color'=>'#6b7280']; ?>
                        <span class="badge" style="background:<?php echo $tl['color']; ?>20;color:<?php echo $tl['color']; ?>;font-size:11px">
                            <?php echo $tl['label']; ?>
                        </span>
                        <?php if ($ev['is_recurring']): ?>
                        <i class="fas fa-redo text-muted ms-1" title="Recurring" style="font-size:10px"></i>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?php echo formatDate($ev['start_date'], 'd M Y, g:i A'); ?></td>
                    <td class="text-muted small"><?php echo $ev['location'] ? e($ev['location']) : '—'; ?></td>
                    <td>
                        <span class="text-success fw-semibold"><?php echo $ev['attending_count']; ?></span>
                        <span class="text-muted"> / <?php echo $ev['maybe_count']; ?> / </span>
                        <span class="text-danger"><?php echo $ev['not_attending_count']; ?></span>
                        <small class="text-muted d-block" style="font-size:10px">✓ / ? / ✗</small>
                    </td>
                    <td><?php echo statusBadge($ev['status']); ?></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $ev['id']; ?>"><i class="fas fa-eye me-2"></i>View</a></li>
                                <?php if ($ev['status'] === 'active'): ?>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>events/form.php?id=<?php echo $ev['id']; ?>"><i class="fas fa-edit me-2"></i>Edit</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($ev['status'] === 'active'): ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>events/actions.php">
                                        <?php echo csrfField(); ?><input type="hidden" name="action" value="complete"><input type="hidden" name="id" value="<?php echo $ev['id']; ?>">
                                        <button class="dropdown-item text-success"><i class="fas fa-check me-2"></i>Mark Completed</button>
                                    </form>
                                </li>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>events/actions.php">
                                        <?php echo csrfField(); ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?php echo $ev['id']; ?>">
                                        <button class="dropdown-item text-warning"><i class="fas fa-ban me-2"></i>Cancel Event</button>
                                    </form>
                                </li>
                                <?php elseif ($ev['status'] !== 'active'): ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>events/actions.php">
                                        <?php echo csrfField(); ?><input type="hidden" name="action" value="reactivate"><input type="hidden" name="id" value="<?php echo $ev['id']; ?>">
                                        <button class="dropdown-item"><i class="fas fa-redo me-2"></i>Reactivate</button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-id="<?php echo $ev['id']; ?>" data-title="<?php echo e($ev['title']); ?>">
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
            <?php if ($paged['has_prev']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>">‹</a></li><?php endif; ?>
            <?php for ($i=1;$i<=$paged['total_pages'];$i++): ?><li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a></li><?php endfor; ?>
            <?php if ($paged['has_next']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&type=<?php echo $type; ?>&status=<?php echo $status; ?>">›</a></li><?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="<?php echo BASE_URL; ?>events/actions.php" class="modal-content">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-header"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Event</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p>Delete <strong id="deleteTitle"></strong>? All RSVPs will be removed.</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete</button></div>
        </form>
    </div>
</div>

<script>
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('deleteId').value = e.relatedTarget.dataset.id;
    document.getElementById('deleteTitle').textContent = e.relatedTarget.dataset.title;
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
