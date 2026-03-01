<?php
/**
 * Manage Dues — Admin only
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Due.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireAdmin();

$pageTitle = 'Manage Dues';
$dueObj    = new Due();

$search  = sanitize($_GET['search'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['active','inactive']) ? $_GET['status'] : '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total   = $dueObj->countAll($search, $status);
$paged   = paginate($total, $perPage, $page);
$dues    = $dueObj->getAll($page, $perPage, $search, $status);
$stats   = $dueObj->getStats();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header d-flex justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="content-title">Manage Dues</h1>
        <p class="content-subtitle">Create and assign membership dues to members.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>payments/due-form.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> Create Due
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--primary-rgb),.12);color:var(--primary)">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Dues</span>
                <span class="stat-value"><?php echo $stats['total']; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#16a34a">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Active</span>
                <span class="stat-value"><?php echo $stats['active']; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--danger-rgb),.12);color:var(--danger)">
                <i class="fas fa-pause-circle"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Inactive</span>
                <span class="stat-value"><?php echo $stats['inactive']; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
            <div class="flex-grow-1" style="min-width:220px">
                <label class="form-label mb-1" style="font-size:var(--font-size-sm)">Search</label>
                <input type="text" class="form-control form-control-sm" name="search"
                       placeholder="Due title or description…" value="<?php echo e($search); ?>">
            </div>
            <div>
                <label class="form-label mb-1" style="font-size:var(--font-size-sm)">Status</label>
                <select class="form-control form-select form-control-sm" name="status" style="min-width:130px">
                    <option value="">All Dues</option>
                    <option value="active"   <?php echo $status === 'active'   ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="<?php echo BASE_URL; ?>payments/dues.php" class="btn btn-secondary btn-sm">Clear</a>
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
                    <th>Title</th>
                    <th>Frequency</th>
                    <th>Amount</th>
                    <th>Penalty</th>
                    <th>Due Date</th>
                    <th>Assigned</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dues)): ?>
                <tr><td colspan="8" class="text-center text-muted py-5">No dues found.</td></tr>
                <?php else: ?>
                <?php foreach ($dues as $d): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?php echo e($d['title']); ?></div>
                        <?php if ($d['description']): ?>
                        <small class="text-muted"><?php echo e(mb_strimwidth($d['description'], 0, 60, '…')); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-info"><?php echo e(str_replace('_',' ', ucfirst($d['frequency']))); ?></span>
                    </td>
                    <td class="fw-semibold"><?php echo formatCurrency((float)$d['amount']); ?></td>
                    <td class="text-muted"><?php echo $d['penalty_fee'] > 0 ? formatCurrency((float)$d['penalty_fee']) : '—'; ?></td>
                    <td class="text-muted"><?php echo $d['due_date'] ? formatDate($d['due_date']) : '—'; ?></td>
                    <td>
                        <span class="text-muted"><?php echo $d['paid_count']; ?>/<?php echo $d['assigned_count']; ?> paid</span>
                    </td>
                    <td>
                        <?php echo $d['is_active'] ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-secondary">Inactive</span>'; ?>
                    </td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                    data-bs-toggle="dropdown">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>payments/due-form.php?id=<?php echo $d['id']; ?>">
                                        <i class="fas fa-edit fa-fw me-2"></i> Edit
                                    </a>
                                </li>
                                <?php if ($d['due_date']): ?>
                                <li>
                                    <button class="dropdown-item text-primary" type="button"
                                            onclick="assignModal(<?php echo $d['id']; ?>, '<?php echo e($d['title']); ?>', <?php echo $d['amount']; ?>, '<?php echo e($d['due_date']); ?>')">
                                        <i class="fas fa-users fa-fw me-2"></i> Assign to All Members
                                    </button>
                                </li>
                                <?php endif; ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>payments/due-actions.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                        <button class="dropdown-item">
                                            <i class="fas fa-<?php echo $d['is_active'] ? 'pause' : 'play'; ?> fa-fw me-2"></i>
                                            <?php echo $d['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                    </form>
                                </li>
                                <?php if ($d['assigned_count'] == 0): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger"
                                            onclick="deleteModal(<?php echo $d['id']; ?>, '<?php echo e($d['title']); ?>')">
                                        <i class="fas fa-trash fa-fw me-2"></i> Delete
                                    </button>
                                </li>
                                <?php endif; ?>
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
        <small class="text-muted">
            Showing <?php echo min($total, ($page-1)*$perPage+1); ?>–<?php echo min($total, $page*$perPage); ?> of <?php echo $total; ?>
        </small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php if ($paged['has_prev']): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">‹</a>
                </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $paged['total_pages']; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($paged['has_next']): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">›</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Assign Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-users me-2"></i>Assign Due to All Members</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>payments/due-actions.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="assign_all">
                <input type="hidden" name="id" id="assignDueId">
                <div class="modal-body">
                    <p class="text-muted mb-3">
                        This will create a pending payment record for every <strong>active</strong> member for:
                    </p>
                    <div class="alert alert-info">
                        <strong id="assignDueTitle"></strong><br>
                        Amount: <strong id="assignDueAmount"></strong>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="due_date" id="assignDueDate" required>
                        <small class="text-muted">Members who are already assigned this due for this date will be skipped.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-users me-2"></i> Assign to All
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Due</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>payments/due-actions.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteDueId">
                <div class="modal-body">
                    <p>Are you sure you want to permanently delete <strong id="deleteDueName"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function assignModal(id, title, amount, dueDate) {
    document.getElementById('assignDueId').value    = id;
    document.getElementById('assignDueTitle').textContent  = title;
    document.getElementById('assignDueAmount').textContent = '₦' + parseFloat(amount).toLocaleString('en-NG', {minimumFractionDigits:2});
    document.getElementById('assignDueDate').value  = dueDate;
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}
function deleteModal(id, name) {
    document.getElementById('deleteDueId').value   = id;
    document.getElementById('deleteDueName').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
