<?php
/**
 * Documents — Admin management
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Document.php';

requireAdmin();

$pageTitle = 'Manage Documents';
$docObj    = new Document();

$search   = sanitize($_GET['search']   ?? '');
$category = sanitize($_GET['category'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;

$total      = $docObj->countAll($search, $category);
$paged      = paginate($total, $perPage, $page);
$items      = $docObj->getAll($page, $perPage, $search, $category);
$stats      = $docObj->getStats();
$categories = $docObj->getCategories();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Documents</h1>
        <p class="content-subtitle">Upload and manage club documents and resources.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>documents/upload.php" class="btn btn-primary">
        <i class="fas fa-upload me-2"></i>Upload Document
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--primary-rgb),.12);color:var(--primary)"><i class="fas fa-folder-open"></i></div>
            <div class="stat-info"><span class="stat-label">Total Files</span><span class="stat-value"><?php echo $stats['total']; ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#16a34a"><i class="fas fa-download"></i></div>
            <div class="stat-info"><span class="stat-label">Total Downloads</span><span class="stat-value"><?php echo number_format($stats['total_downloads']); ?></span></div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(234,179,8,.12);color:#ca8a04"><i class="fas fa-hdd"></i></div>
            <div class="stat-info"><span class="stat-label">Storage Used</span><span class="stat-value"><?php echo Document::formatSize((int)$stats['total_size']); ?></span></div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Search title or category…" value="<?php echo e($search); ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-control">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat => $cnt): ?>
                    <option value="<?php echo e($cat); ?>" <?php echo $category===$cat?'selected':''; ?>><?php echo e($cat); ?> (<?php echo $cnt; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                <a href="documents/manage.php" class="btn btn-secondary flex-fill">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>File</th><th>Category</th><th>Size</th><th>Downloads</th><th>Uploaded by</th><th>Date</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No documents found.</td></tr>
                <?php else: ?>
                <?php foreach ($items as $d): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas <?php echo Document::typeIcon($d['mime_type'] ?? ''); ?> fa-lg"></i>
                            <div>
                                <div class="fw-semibold"><?php echo e($d['title']); ?></div>
                                <small class="text-muted"><?php echo strtoupper(pathinfo($d['file_path'], PATHINFO_EXTENSION)); ?></small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-info"><?php echo $d['category'] ? e($d['category']) : '—'; ?></span></td>
                    <td class="text-muted small"><?php echo Document::formatSize((int)$d['file_size']); ?></td>
                    <td class="fw-semibold"><?php echo number_format($d['downloads']); ?></td>
                    <td class="text-muted small"><?php echo e($d['uploader_name'] ?: $d['uploader_email']); ?></td>
                    <td class="text-muted small"><?php echo formatDate($d['created_at'], 'd M Y'); ?></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="<?php echo BASE_URL; ?>documents/download.php?id=<?php echo $d['id']; ?>"
                               class="btn btn-success btn-sm" title="Download">
                                <i class="fas fa-download"></i>
                            </a>
                            <button class="btn btn-secondary btn-sm" title="Edit"
                                    data-bs-toggle="modal" data-bs-target="#editModal"
                                    data-id="<?php echo $d['id']; ?>"
                                    data-title="<?php echo e($d['title']); ?>"
                                    data-category="<?php echo e($d['category'] ?? ''); ?>">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" title="Delete"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    data-id="<?php echo $d['id']; ?>"
                                    data-title="<?php echo e($d['title']); ?>">
                                <i class="fas fa-trash"></i>
                            </button>
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
            <?php if ($paged['has_prev']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">‹</a></li><?php endif; ?>
            <?php for ($i=1;$i<=$paged['total_pages'];$i++): ?><li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>"><?php echo $i; ?></a></li><?php endfor; ?>
            <?php if ($paged['has_next']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo urlencode($category); ?>">›</a></li><?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?php echo BASE_URL; ?>documents/actions.php" class="modal-content">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="id" id="editId">
            <div class="modal-header"><h5 class="modal-title">Edit Document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" id="editTitle" class="form-control" required></div>
                <div class="mb-0"><label class="form-label">Category</label>
                    <input type="text" name="category" id="editCategory" class="form-control" list="categoryList" placeholder="e.g. Rules, Policies, Finance">
                    <datalist id="categoryList"><?php foreach ($categories as $cat => $n): ?><option value="<?php echo e($cat); ?>"><?php endforeach; ?></datalist>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="<?php echo BASE_URL; ?>documents/actions.php" class="modal-content">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="deleteId">
            <div class="modal-header"><h5 class="modal-title text-danger"><i class="fas fa-trash me-2"></i>Delete Document</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><p>Delete <strong id="deleteTitle"></strong>? The file will be permanently removed.</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Delete</button></div>
        </form>
    </div>
</div>

<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('editId').value       = btn.dataset.id;
    document.getElementById('editTitle').value    = btn.dataset.title;
    document.getElementById('editCategory').value = btn.dataset.category;
});
document.getElementById('deleteModal').addEventListener('show.bs.modal', function(e) {
    document.getElementById('deleteId').value = e.relatedTarget.dataset.id;
    document.getElementById('deleteTitle').textContent = e.relatedTarget.dataset.title;
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
