<?php
/**
 * Documents — Member view
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Document.php';

requireLogin();

$pageTitle  = 'Documents';
$docObj     = new Document();
$search     = sanitize($_GET['search']   ?? '');
$category   = sanitize($_GET['category'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 16;

$total      = $docObj->countAll($search, $category);
$paged      = paginate($total, $perPage, $page);
$items      = $docObj->getAll($page, $perPage, $search, $category);
$categories = $docObj->getCategories();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Documents</h1>
        <p class="content-subtitle">Club resources, policies, and guides.</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>documents/manage.php" class="btn btn-secondary"><i class="fas fa-cog me-2"></i>Manage</a>
        <a href="<?php echo BASE_URL; ?>documents/upload.php" class="btn btn-primary"><i class="fas fa-upload me-2"></i>Upload</a>
    </div>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="d-flex flex-wrap gap-2 mb-4">
    <a href="documents/index.php" class="btn btn-sm <?php echo !$category?'btn-primary':'btn-secondary'; ?>">All</a>
    <?php foreach ($categories as $cat => $cnt): ?>
    <a href="documents/index.php?category=<?php echo urlencode($cat); ?>"
       class="btn btn-sm <?php echo $category===$cat?'btn-primary':'btn-secondary'; ?>">
        <?php echo e($cat); ?> <span class="badge ms-1" style="background:rgba(255,255,255,.3)"><?php echo $cnt; ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Search -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="d-flex gap-2">
            <?php if ($category): ?><input type="hidden" name="category" value="<?php echo e($category); ?>"><?php endif; ?>
            <input type="text" name="search" class="form-control" placeholder="Search documents…" value="<?php echo e($search); ?>">
            <button type="submit" class="btn btn-primary flex-shrink-0"><i class="fas fa-search"></i></button>
            <?php if ($search): ?><a href="documents/index.php<?php echo $category?'?category='.urlencode($category):''; ?>" class="btn btn-secondary flex-shrink-0">Clear</a><?php endif; ?>
        </form>
    </div>
</div>

<!-- Documents grid -->
<?php if (empty($items)): ?>
<div class="card text-center py-5">
    <div style="font-size:48px;opacity:.3">📁</div>
    <p class="text-muted mt-3">No documents found.</p>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($items as $d): ?>
    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
        <div class="card h-100 doc-card">
            <div class="card-body text-center py-4">
                <i class="fas <?php echo Document::typeIcon($d['mime_type'] ?? ''); ?> fa-3x mb-3"></i>
                <div class="fw-semibold mb-1" style="font-size:13px;line-height:1.3"><?php echo e($d['title']); ?></div>
                <?php if ($d['category']): ?>
                <span class="badge badge-info mb-2" style="font-size:10px"><?php echo e($d['category']); ?></span>
                <?php endif; ?>
                <div class="text-muted" style="font-size:11px"><?php echo Document::formatSize((int)$d['file_size']); ?> · <?php echo strtoupper(pathinfo($d['file_path'], PATHINFO_EXTENSION)); ?></div>
            </div>
            <div class="card-footer text-center py-2">
                <div class="d-flex gap-2 justify-content-center align-items-center">
                    <span class="text-muted" style="font-size:11px"><i class="fas fa-download me-1"></i><?php echo $d['downloads']; ?></span>
                    <a href="<?php echo BASE_URL; ?>documents/download.php?id=<?php echo $d['id']; ?>"
                       class="btn btn-primary btn-sm">
                        <i class="fas fa-download me-1"></i>Download
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($paged['total_pages'] > 1): ?>
<nav class="d-flex justify-content-center mt-4">
    <ul class="pagination">
        <?php if ($paged['has_prev']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>">‹</a></li><?php endif; ?>
        <?php for ($i=1;$i<=$paged['total_pages'];$i++): ?><li class="page-item <?php echo $i===$page?'active':''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li><?php endfor; ?>
        <?php if ($paged['has_next']): ?><li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>">›</a></li><?php endif; ?>
    </ul>
</nav>
<?php endif; ?>

<?php endif; ?>

<style>
.doc-card { transition: box-shadow .15s; }
.doc-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.12); }
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
