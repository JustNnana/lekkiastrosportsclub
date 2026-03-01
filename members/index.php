<?php
/**
 * Members — List / search / filter
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Member.php';

requireAdmin();

$pageTitle = 'Members';

$member  = new Member();

// Inputs
$search  = sanitize($_GET['search'] ?? '');
$status  = in_array($_GET['status'] ?? '', ['active','inactive','suspended']) ? $_GET['status'] : '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Data
$members    = $member->getAll($page, $perPage, $search, $status);
$total      = $member->countAll($search, $status);
$pagination = paginate($total, $perPage, $page);
$stats      = $member->getStats();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<!-- ===== PAGE HEADER ===== -->
<div class="content-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="content-title">Members</h1>
        <p class="content-subtitle"><?php echo number_format($stats['total']); ?> total members registered.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo BASE_URL; ?>members/export.php<?php echo $status ? '?status='.$status : ''; ?>"
           class="btn btn-secondary btn-sm">
            <i class="fas fa-file-export"></i> Export
        </a>
        <a href="<?php echo BASE_URL; ?>members/create.php" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Add Member
        </a>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-3 mb-6">
    <?php
    $statCards = [
        ['label'=>'Total',     'value'=>$stats['total'],       'icon'=>'fas fa-users',      'color'=>'primary'],
        ['label'=>'Active',    'value'=>$stats['active'],      'icon'=>'fas fa-user-check', 'color'=>'success'],
        ['label'=>'Inactive',  'value'=>$stats['inactive'],    'icon'=>'fas fa-user-times', 'color'=>'danger'],
        ['label'=>'Suspended', 'value'=>$stats['suspended'],   'icon'=>'fas fa-user-lock',  'color'=>'warning'],
        ['label'=>'New (Month)','value'=>$stats['new_this_month'],'icon'=>'fas fa-user-plus','color'=>'info'],
    ];
    foreach ($statCards as $sc): ?>
    <div class="col-6 col-sm-4 col-md-2-4">
        <a href="<?php echo BASE_URL; ?>members/?status=<?php echo strtolower($sc['label']); ?>"
           class="card stat-mini text-decoration-none <?php echo ($status === strtolower($sc['label'])) ? 'active' : ''; ?>">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-mini-icon text-<?php echo $sc['color']; ?>">
                    <i class="<?php echo $sc['icon']; ?>"></i>
                </div>
                <div>
                    <p class="stat-mini-value"><?php echo number_format($sc['value']); ?></p>
                    <p class="stat-mini-label"><?php echo $sc['label']; ?></p>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- ===== SEARCH & FILTER ===== -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" action="" class="d-flex gap-3 flex-wrap align-items-end">
            <div class="flex-grow-1" style="min-width:200px">
                <label class="form-label mb-1">Search</label>
                <div class="input-icon-wrap">
                    <i class="fas fa-search input-icon"></i>
                    <input type="text" name="search" class="form-control"
                           placeholder="Name, email, member ID, phone…"
                           value="<?php echo e($search); ?>">
                </div>
            </div>
            <div style="min-width:160px">
                <label class="form-label mb-1">Status</label>
                <select name="status" class="form-control form-select">
                    <option value="">All Statuses</option>
                    <option value="active"    <?php echo $status === 'active'    ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive"  <?php echo $status === 'inactive'  ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search || $status): ?>
                <a href="<?php echo BASE_URL; ?>members/" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ===== MEMBERS TABLE ===== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title">
            <?php if ($search || $status): ?>
                Results for
                <?php echo $search ? '"' . e($search) . '"' : ''; ?>
                <?php echo $status ? '<span class="badge badge-secondary ms-1">' . e(ucfirst($status)) . '</span>' : ''; ?>
                — <?php echo number_format($total); ?> found
            <?php else: ?>
                All Members (<?php echo number_format($total); ?>)
            <?php endif; ?>
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th class="d-none d-md-table-cell">Member ID</th>
                    <th class="d-none d-lg-table-cell">Phone</th>
                    <th class="d-none d-lg-table-cell">Position</th>
                    <th class="d-none d-md-table-cell">Joined</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($members)): ?>
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="empty-state">
                            <i class="fas fa-users fa-3x text-muted mb-3 d-block"></i>
                            <p class="fw-semibold text-muted">No members found</p>
                            <?php if ($search || $status): ?>
                            <p class="text-muted">Try adjusting your search or filter.</p>
                            <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>members/create.php" class="btn btn-primary btn-sm">
                                Add First Member
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($members as $m): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="member-avatar"><?php echo e(getInitials($m['full_name'])); ?></div>
                            <div>
                                <a href="<?php echo BASE_URL; ?>members/view.php?id=<?php echo $m['id']; ?>"
                                   class="fw-semibold text-decoration-none member-name">
                                    <?php echo e($m['full_name']); ?>
                                </a>
                                <p class="text-muted mb-0" style="font-size:var(--font-size-xs)">
                                    <?php echo e($m['email']); ?>
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <code class="member-id-code"><?php echo e($m['member_id']); ?></code>
                    </td>
                    <td class="d-none d-lg-table-cell text-muted">
                        <?php echo e($m['phone'] ?: '—'); ?>
                    </td>
                    <td class="d-none d-lg-table-cell text-muted">
                        <?php echo e($m['position'] ?: '—'); ?>
                    </td>
                    <td class="d-none d-md-table-cell text-muted">
                        <?php echo $m['joined_at'] ? formatDate($m['joined_at'], 'd M Y') : '—'; ?>
                    </td>
                    <td><?php echo statusBadge($m['status']); ?></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-secondary btn-sm" data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>members/view.php?id=<?php echo $m['id']; ?>">
                                    <i class="fas fa-eye fa-fw me-2 text-info"></i>View Profile
                                </a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>members/edit.php?id=<?php echo $m['id']; ?>">
                                    <i class="fas fa-edit fa-fw me-2 text-primary"></i>Edit
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($m['status'] === 'active'): ?>
                                <li>
                                    <button class="dropdown-item action-btn text-warning"
                                            data-action="suspend" data-id="<?php echo $m['id']; ?>"
                                            data-name="<?php echo e($m['full_name']); ?>">
                                        <i class="fas fa-user-lock fa-fw me-2"></i>Suspend
                                    </button>
                                </li>
                                <?php elseif ($m['status'] === 'suspended'): ?>
                                <li>
                                    <button class="dropdown-item action-btn text-success"
                                            data-action="activate" data-id="<?php echo $m['id']; ?>"
                                            data-name="<?php echo e($m['full_name']); ?>">
                                        <i class="fas fa-user-check fa-fw me-2"></i>Activate
                                    </button>
                                </li>
                                <?php endif; ?>
                                <?php if ($m['status'] !== 'inactive'): ?>
                                <li>
                                    <button class="dropdown-item action-btn text-secondary"
                                            data-action="deactivate" data-id="<?php echo $m['id']; ?>"
                                            data-name="<?php echo e($m['full_name']); ?>">
                                        <i class="fas fa-user-times fa-fw me-2"></i>Deactivate
                                    </button>
                                </li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <?php if (isSuperAdmin()): ?>
                                <li>
                                    <button class="dropdown-item action-btn text-danger"
                                            data-action="delete" data-id="<?php echo $m['id']; ?>"
                                            data-name="<?php echo e($m['full_name']); ?>">
                                        <i class="fas fa-trash fa-fw me-2"></i>Delete
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

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <small class="text-muted">
            Showing <?php echo number_format(($page - 1) * $perPage + 1); ?>–<?php echo number_format(min($page * $perPage, $total)); ?>
            of <?php echo number_format($total); ?> members
        </small>
        <nav>
            <ul class="pagination mb-0">
                <li class="page-item <?php echo !$pagination['has_prev'] ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
                <?php for ($p = max(1, $page - 2); $p <= min($pagination['total_pages'], $page + 2); $p++): ?>
                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $p; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                        <?php echo $p; ?>
                    </a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo !$pagination['has_next'] ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- ===== CONFIRM MODAL ===== -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmBody">Are you sure?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="<?php echo BASE_URL; ?>members/actions.php" id="confirm-form">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" id="confirm-action">
                    <input type="hidden" name="id"     id="confirm-id">
                    <button type="submit" class="btn" id="confirm-btn">Confirm</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.col-md-2-4 { flex: 0 0 auto; width: 20%; }
@media(max-width:768px){ .col-md-2-4 { width:50%; } }

.stat-mini { cursor:pointer; transition:var(--transition-fast); }
.stat-mini:hover, .stat-mini.active { border-color:var(--primary); }
.stat-mini .card-body { padding:var(--spacing-3) var(--spacing-4); }
.stat-mini-icon { font-size:1.2rem; width:36px; }
.stat-mini-value { font-size:var(--font-size-2xl); font-weight:var(--font-weight-bold); color:var(--text-primary); margin:0; }
.stat-mini-label { font-size:var(--font-size-xs); color:var(--text-muted); margin:0; }

.member-avatar {
    width:38px; height:38px; flex-shrink:0;
    border-radius:var(--border-radius-full);
    background:linear-gradient(135deg,var(--primary),var(--primary-700));
    color:#fff; display:flex; align-items:center; justify-content:center;
    font-size:var(--font-size-xs); font-weight:var(--font-weight-semibold);
}
.member-name { color:var(--text-primary); }
.member-name:hover { color:var(--primary); }
.member-id-code {
    background:var(--bg-secondary); color:var(--primary);
    padding:2px 8px; border-radius:var(--border-radius-sm);
    font-size:var(--font-size-xs); font-family:var(--font-family-mono);
}

.input-icon-wrap { position:relative; }
.input-icon-wrap .input-icon { position:absolute; top:50%; left:14px; transform:translateY(-50%); color:var(--text-muted); font-size:var(--font-size-sm); pointer-events:none; }
.input-icon-wrap .form-control { padding-left:2.5rem; }
</style>

<script>
// Action buttons — show confirm modal
document.querySelectorAll('.action-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var action = this.dataset.action;
        var id     = this.dataset.id;
        var name   = this.dataset.name;

        var messages = {
            suspend:    { title:'Suspend Member', body:'Suspend <strong>' + name + '</strong>? They will lose access until reactivated.', btnClass:'btn-warning',  btnText:'Suspend' },
            activate:   { title:'Activate Member', body:'Reactivate <strong>' + name + '</strong>? They will regain full access.', btnClass:'btn-success', btnText:'Activate' },
            deactivate: { title:'Deactivate Member', body:'Deactivate <strong>' + name + '</strong>?', btnClass:'btn-secondary', btnText:'Deactivate' },
            delete:     { title:'Delete Member', body:'⚠ Permanently delete <strong>' + name + '</strong> and all their data? This cannot be undone.', btnClass:'btn-danger', btnText:'Delete Permanently' },
        };

        var cfg = messages[action];
        document.getElementById('confirmTitle').textContent  = cfg.title;
        document.getElementById('confirmBody').innerHTML     = cfg.body;
        document.getElementById('confirm-btn').className     = 'btn ' + cfg.btnClass;
        document.getElementById('confirm-btn').textContent   = cfg.btnText;
        document.getElementById('confirm-action').value      = action;
        document.getElementById('confirm-id').value          = id;

        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
