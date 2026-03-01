<?php
/**
 * Announcements — Admin management list
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

requireAdmin();

$pageTitle = 'Manage Announcements';
$annObj    = new Announcement();

$search  = sanitize($_GET['search'] ?? '');
$status  = sanitize($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$total   = $annObj->countAll($search, $status);
$paged   = paginate($total, $perPage, $page);
$items   = $annObj->getAll($page, $perPage, $search, $status);
$stats   = $annObj->getStats();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Announcements</h1>
        <p class="content-subtitle">Create, publish, and manage club announcements.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>announcements/form.php" class="btn btn-primary">
        <i class="fas fa-plus me-2"></i>New Announcement
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--primary-rgb),.12);color:var(--primary)"><i class="fas fa-bullhorn"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total</span>
                <span class="stat-value"><?php echo $stats['total']; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#16a34a"><i class="fas fa-globe"></i></div>
            <div class="stat-info">
                <span class="stat-label">Published</span>
                <span class="stat-value"><?php echo $stats['published']; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(234,179,8,.12);color:#ca8a04"><i class="fas fa-file-alt"></i></div>
            <div class="stat-info">
                <span class="stat-label">Drafts</span>
                <span class="stat-value"><?php echo $stats['drafts']; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--danger-rgb),.12);color:var(--danger)"><i class="fas fa-thumbtack"></i></div>
            <div class="stat-info">
                <span class="stat-label">Pinned</span>
                <span class="stat-value"><?php echo $stats['pinned']; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search title or content…"
                       value="<?php echo e($search); ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    <option value="published" <?php echo $status==='published'?'selected':''; ?>>Published</option>
                    <option value="draft"     <?php echo $status==='draft'?'selected':''; ?>>Drafts</option>
                    <option value="pinned"    <?php echo $status==='pinned'?'selected':''; ?>>Pinned</option>
                    <option value="scheduled" <?php echo $status==='scheduled'?'selected':''; ?>>Scheduled</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                <a href="announcements/manage.php" class="btn btn-secondary flex-fill">Reset</a>
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
                    <th>Author</th>
                    <th>Status</th>
                    <th>Views</th>
                    <th>Comments</th>
                    <th>Reactions</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="8" class="text-center text-muted py-5">No announcements found.</td></tr>
                <?php else: ?>
                <?php foreach ($items as $a): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($a['is_pinned']): ?>
                            <i class="fas fa-thumbtack text-danger" title="Pinned" style="font-size:11px"></i>
                            <?php endif; ?>
                            <div>
                                <div class="fw-semibold">
                                    <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $a['id']; ?>"
                                       class="text-body text-decoration-none">
                                        <?php echo e($a['title']); ?>
                                    </a>
                                </div>
                                <?php if ($a['scheduled_at'] && !$a['is_published']): ?>
                                <small class="text-muted"><i class="fas fa-clock me-1"></i>Scheduled: <?php echo formatDate($a['scheduled_at'], 'd M Y, g:i A'); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="text-muted small"><?php echo e($a['author_name'] ?: $a['author_email']); ?></td>
                    <td>
                        <?php if ($a['is_published']): ?>
                            <span class="badge badge-success">Published</span>
                        <?php elseif ($a['scheduled_at']): ?>
                            <span class="badge badge-info">Scheduled</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?php echo number_format($a['views']); ?></td>
                    <td class="text-muted"><?php echo $a['comment_count']; ?></td>
                    <td class="text-muted"><?php echo $a['reaction_count']; ?></td>
                    <td class="text-muted small"><?php echo formatDate($a['created_at'], 'd M Y'); ?></td>
                    <td class="text-end">
                        <div class="dropdown">
                            <button class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $a['id']; ?>">
                                        <i class="fas fa-eye me-2"></i>View
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>announcements/form.php?id=<?php echo $a['id']; ?>">
                                        <i class="fas fa-edit me-2"></i>Edit
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($a['is_published']): ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>announcements/actions.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="unpublish">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="dropdown-item">
                                            <i class="fas fa-eye-slash me-2"></i>Unpublish
                                        </button>
                                    </form>
                                </li>
                                <?php else: ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>announcements/actions.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="publish">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="dropdown-item text-success">
                                            <i class="fas fa-globe me-2"></i>Publish Now
                                        </button>
                                    </form>
                                </li>
                                <?php endif; ?>
                                <li>
                                    <form method="POST" action="<?php echo BASE_URL; ?>announcements/actions.php">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_pin">
                                        <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                                        <button type="submit" class="dropdown-item">
                                            <i class="fas fa-thumbtack me-2"></i><?php echo $a['is_pinned'] ? 'Unpin' : 'Pin'; ?>
                                        </button>
                                    </form>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button class="dropdown-item text-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-id="<?php echo $a['id']; ?>"
                                            data-title="<?php echo e($a['title']); ?>">
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
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">‹</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $paged['total_pages']; $i++): ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($paged['has_next']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">›</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="<?php echo BASE_URL; ?>announcements/actions.php" class="modal-content">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="deleteId">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Delete <strong id="deleteTitle"></strong>? This also removes all comments and reactions. This cannot be undone.</p>
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
    document.getElementById('deleteId').value    = btn.dataset.id;
    document.getElementById('deleteTitle').textContent = btn.dataset.title;
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
