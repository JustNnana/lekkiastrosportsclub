<?php
/**
 * Document upload form (admin only)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Document.php';

requireAdmin();

$pageTitle  = 'Upload Document';
$docObj     = new Document();
$categories = array_keys($docObj->getCategories());
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $title    = sanitize($_POST['title']    ?? '');
    $category = sanitize($_POST['category'] ?? '');

    if (!$title) $errors[] = 'Title is required.';
    if (empty($_FILES['document']['name'])) $errors[] = 'Please select a file.';

    if (empty($errors)) {
        $upload = $docObj->handleUpload($_FILES['document']);
        if (isset($upload['error'])) {
            $errors[] = $upload['error'];
        } else {
            $docObj->create([
                'title'       => $title,
                'category'    => $category ?: null,
                'file_path'   => $upload['path'],
                'file_size'   => $upload['size'],
                'mime_type'   => $upload['mime'],
                'uploaded_by' => $_SESSION['user_id'],
            ]);
            flashSuccess('Document uploaded successfully.');
            redirect('documents/manage.php');
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Upload Document</h1>
        <p class="content-subtitle">Add a file to the club document library.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>documents/manage.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Document Details</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="200"
                               value="<?php echo e($_POST['title'] ?? ''); ?>"
                               placeholder="Descriptive name for this document">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" list="catList" maxlength="100"
                               value="<?php echo e($_POST['category'] ?? ''); ?>"
                               placeholder="e.g. Rules, Policies, Finance, Training">
                        <datalist id="catList">
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo e($cat); ?>">
                            <?php endforeach; ?>
                            <option value="Rules & Regulations">
                            <option value="Club Policies">
                            <option value="Finance">
                            <option value="Training">
                            <option value="Membership">
                            <option value="Minutes">
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File <span class="text-danger">*</span></label>
                        <div class="upload-zone" id="uploadZone">
                            <input type="file" name="document" id="fileInput" class="upload-input"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt" required>
                            <div class="upload-content" id="uploadContent">
                                <i class="fas fa-cloud-upload-alt fa-2x mb-2 text-muted"></i>
                                <p class="mb-1 fw-semibold">Click or drag to upload</p>
                                <p class="text-muted small mb-0">PDF, Word, Excel, PowerPoint, Images, TXT · Max 10 MB</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload Document
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.upload-zone {
    border: 2px dashed var(--border-color); border-radius: 10px;
    padding: 40px 20px; text-align: center; position: relative; cursor: pointer;
    transition: border-color .2s, background .2s;
}
.upload-zone:hover, .upload-zone.drag-over { border-color: var(--primary); background: rgba(var(--primary-rgb),.04); }
.upload-input { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; }
</style>

<script>
const zone  = document.getElementById('uploadZone');
const input = document.getElementById('fileInput');
const content = document.getElementById('uploadContent');

input.addEventListener('change', function() {
    if (this.files[0]) {
        const f = this.files[0];
        const size = f.size > 1048576 ? (f.size/1048576).toFixed(1)+' MB' : Math.round(f.size/1024)+' KB';
        content.innerHTML = `<i class="fas fa-file-check fa-2x mb-2 text-success"></i><p class="mb-1 fw-semibold">${f.name}</p><p class="text-muted small mb-0">${size}</p>`;
    }
});

['dragover','dragenter'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('drag-over'); }));
['dragleave','drop'].forEach(ev => zone.addEventListener(ev, e => { zone.classList.remove('drag-over'); }));
zone.addEventListener('drop', e => { e.preventDefault(); input.files = e.dataTransfer.files; input.dispatchEvent(new Event('change')); });
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
