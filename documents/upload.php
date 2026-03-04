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

<style>
:root {
    --ios-red:    #FF453A;
    --ios-orange: #FF9F0A;
    --ios-green:  #30D158;
    --ios-blue:   #0A84FF;
    --ios-purple: #BF5AF2;
    --ios-teal:   #64D2FF;
}

/* ── Layout ── */
.form-container {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: var(--spacing-5);
    max-width: 1100px;
    margin: 0 auto;
}
@media (max-width: 992px) { .form-container { grid-template-columns: 1fr; } }

/* ── iOS Section Card ── */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: var(--spacing-4);
}
.ios-section-header {
    display: flex; align-items: center; gap: var(--spacing-3);
    padding: var(--spacing-4);
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-section-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.ios-section-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-section-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-section-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-section-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-section-icon.red    { background: rgba(255,69,58,.15);  color: var(--ios-red); }

.ios-section-title { flex: 1; min-width: 0; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

/* ── Form body ── */
.ios-section-body { padding: var(--spacing-5); }
.ios-form-group { margin-bottom: var(--spacing-4); }
.ios-form-group:last-child { margin-bottom: 0; }
.ios-form-label {
    display: block; font-size: 13px; font-weight: 600;
    color: var(--text-secondary); margin-bottom: 6px;
    text-transform: uppercase; letter-spacing: .3px;
}
.ios-form-label .req { color: var(--ios-red); margin-left: 2px; }
.ios-form-label .opt { color: var(--text-muted); font-weight: 400; text-transform: none; letter-spacing: 0; }

/* ── Upload zone ── */
.ios-upload-zone {
    border: 2px dashed var(--border-color);
    border-radius: 14px;
    padding: 36px 20px;
    text-align: center;
    position: relative;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    background: var(--bg-secondary);
}
.ios-upload-zone:hover,
.ios-upload-zone.drag-over {
    border-color: var(--ios-blue);
    background: rgba(10,132,255,.05);
}
.ios-upload-zone.has-file {
    border-color: var(--ios-green);
    background: rgba(48,209,88,.05);
}
.ios-upload-input {
    position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%;
}
.ios-upload-icon {
    font-size: 32px; color: var(--text-muted); margin-bottom: 10px;
    transition: color .2s;
}
.ios-upload-zone.has-file .ios-upload-icon { color: var(--ios-green); }
.ios-upload-zone:hover .ios-upload-icon,
.ios-upload-zone.drag-over .ios-upload-icon { color: var(--ios-blue); }
.ios-upload-title {
    font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px;
}
.ios-upload-sub {
    font-size: 12px; color: var(--text-muted); margin: 0;
}

/* ── Sidebar ── */
.ios-sidebar { display: flex; flex-direction: column; }
.ios-sidebar-sticky { position: sticky; top: var(--spacing-4); }
.ios-admin-actions { padding: var(--spacing-4); display: flex; flex-direction: column; gap: var(--spacing-3); }

.ios-tip-list { padding: var(--spacing-4) var(--spacing-5); display: flex; flex-direction: column; gap: var(--spacing-3); }
.ios-tip-item { display: flex; gap: 10px; font-size: 13px; }
.ios-tip-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.ios-tip-text { color: var(--text-secondary); line-height: 1.5; }
.ios-tip-text strong { color: var(--text-primary); }

/* ── iOS bottom-sheet ── */
.ios-menu-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    z-index: 9998; opacity: 0; visibility: hidden;
    transition: opacity .3s ease, visibility .3s ease;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-primary); border-radius: 16px 16px 0 0;
    z-index: 9999; transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 85vh; overflow: hidden; display: flex; flex-direction: column;
}
.ios-menu-modal.active { transform: translateY(0); }
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ios-menu-title  { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close  { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; }
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-left: 4px; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background .15s; cursor: pointer; width: 100%; background: transparent; border-left: none; border-right: none; border-top: none; font-family: inherit; font-size: inherit; text-align: left; }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-menu-item-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-menu-item-label   { font-size: 15px; font-weight: 500; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .ios-sidebar { display: none; }
    .form-container { grid-template-columns: 1fr; }
    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 14px; }
    .ios-section-body { padding: var(--spacing-4); }
}
</style>

<!-- ===== DESKTOP HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-start">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>documents/manage.php" class="breadcrumb-link">Documents</a>
                </li>
                <li class="breadcrumb-item active">Upload</li>
            </ol>
        </nav>
        <h1 class="content-title">Upload Document</h1>
        <p class="content-subtitle">Add a file to the club document library.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>documents/manage.php" class="btn btn-secondary flex-shrink-0">
        <i class="fas fa-arrow-left me-2"></i>Back to Documents
    </a>
</div>

<!-- ===== MOBILE HEADER ===== -->
<div class="ios-section-header d-md-none mb-3" style="border-radius:14px;border:1px solid var(--border-color)">
    <div class="ios-section-icon blue">
        <i class="fas fa-cloud-upload-alt"></i>
    </div>
    <div class="ios-section-title">
        <h5>Upload Document</h5>
        <p>Add a file to the library</p>
    </div>
    <button onclick="openIosMenu()" style="width:36px;height:36px;border-radius:50%;background:var(--bg-secondary);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0">
        <i class="fas fa-ellipsis-v" style="color:var(--text-primary);font-size:16px"></i>
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4" style="max-width:1100px;margin-left:auto;margin-right:auto;border-radius:12px">
    <i class="fas fa-exclamation-circle me-2"></i>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="uploadForm">
    <?php echo csrfField(); ?>

    <div class="form-container">

        <!-- ===== LEFT ===== -->
        <div>

            <!-- Document Details card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Document Details</h5>
                        <p>Title, category and file</p>
                    </div>
                </div>
                <div class="ios-section-body">

                    <div class="ios-form-group">
                        <label class="ios-form-label">Title <span class="req">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="200"
                               style="border-radius:10px"
                               value="<?php echo e($_POST['title'] ?? ''); ?>"
                               placeholder="e.g. Club Constitution 2026">
                    </div>

                    <div class="ios-form-group">
                        <label class="ios-form-label">Category <span class="opt">(optional)</span></label>
                        <input type="text" name="category" class="form-control" list="catList" maxlength="100"
                               style="border-radius:10px"
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

                </div>
            </div>

            <!-- File Upload card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon orange">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>File</h5>
                        <p>PDF, Word, Excel, PowerPoint, Images, TXT · Max 10 MB</p>
                    </div>
                </div>
                <div class="ios-section-body">

                    <div class="ios-form-group" style="margin-bottom:0">
                        <label class="ios-form-label">Select File <span class="req">*</span></label>
                        <div class="ios-upload-zone" id="uploadZone">
                            <input type="file" name="document" id="fileInput" class="ios-upload-input"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt" required>
                            <div id="uploadContent">
                                <div class="ios-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                <p class="ios-upload-title">Click or drag to upload</p>
                                <p class="ios-upload-sub">PDF, Word, Excel, PowerPoint, Images, TXT</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Mobile submit -->
            <div class="d-md-none" style="display:flex;flex-direction:column;gap:10px;margin-bottom:var(--spacing-4)">
                <button type="submit" class="btn btn-primary w-100" style="border-radius:12px;padding:14px">
                    <i class="fas fa-upload me-2"></i>Upload Document
                </button>
                <a href="<?php echo BASE_URL; ?>documents/manage.php" class="btn btn-secondary w-100" style="border-radius:12px;padding:14px">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>

        </div><!-- /left -->

        <!-- ===== RIGHT: SIDEBAR ===== -->
        <div class="ios-sidebar">
            <div class="ios-sidebar-sticky">

                <!-- Actions card -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon green">
                            <i class="fas fa-upload"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5>Upload Document</h5>
                        </div>
                    </div>
                    <div class="ios-admin-actions">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-upload me-2"></i>Upload Document
                        </button>
                        <a href="<?php echo BASE_URL; ?>documents/manage.php" class="btn btn-secondary w-100">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </div>

                <!-- Tips card -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue"><i class="fas fa-lightbulb"></i></div>
                        <div class="ios-section-title"><h5>Upload Tips</h5></div>
                    </div>
                    <div class="ios-tip-list">
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">📄</span>
                            <span class="ios-tip-text"><strong>Use a clear title</strong> so members can easily find the document.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">🗂️</span>
                            <span class="ios-tip-text"><strong>Add a category</strong> to keep the library organised.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">📦</span>
                            <span class="ios-tip-text"><strong>Max file size</strong> is 10 MB. Compress large files before uploading.</span>
                        </div>
                    </div>
                </div>

            </div>
        </div><!-- /sidebar -->

    </div><!-- /form-container -->
</form>

<!-- ===== iOS MENU MODAL (mobile) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeIosMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h5 class="ios-menu-title">Upload Document</h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>documents/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-folder-open"></i></div>
                        <div class="ios-menu-item-label">Manage Documents</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>documents/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-label">Document Library</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-home"></i></div>
                        <div class="ios-menu-item-label">Dashboard</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
/* ── iOS menu ── */
function openIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.add('active');
    document.getElementById('iosMenuModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.remove('active');
    document.getElementById('iosMenuModal').classList.remove('active');
    document.body.style.overflow = '';
}
(function() {
    var modal  = document.getElementById('iosMenuModal');
    var startY = 0, isDragging = false;
    modal.addEventListener('touchstart', function(e) { startY = e.touches[0].clientY; isDragging = true; }, { passive: true });
    modal.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        var dy = e.touches[0].clientY - startY;
        if (dy > 0) modal.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    modal.addEventListener('touchend', function(e) {
        if (!isDragging) return;
        isDragging = false;
        modal.style.transform = '';
        if (e.changedTouches[0].clientY - startY > 80) closeIosMenu();
    });
})();

/* ── File upload zone ── */
var zone    = document.getElementById('uploadZone');
var input   = document.getElementById('fileInput');
var content = document.getElementById('uploadContent');

input.addEventListener('change', function() {
    if (this.files[0]) {
        var f    = this.files[0];
        var size = f.size > 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.round(f.size / 1024) + ' KB';
        zone.classList.add('has-file');
        content.innerHTML =
            '<div class="ios-upload-icon"><i class="fas fa-file-check"></i></div>' +
            '<p class="ios-upload-title">' + f.name + '</p>' +
            '<p class="ios-upload-sub">' + size + '</p>';
    }
});

['dragover', 'dragenter'].forEach(function(ev) {
    zone.addEventListener(ev, function(e) { e.preventDefault(); zone.classList.add('drag-over'); });
});
['dragleave', 'drop'].forEach(function(ev) {
    zone.addEventListener(ev, function(e) { zone.classList.remove('drag-over'); });
});
zone.addEventListener('drop', function(e) {
    e.preventDefault();
    input.files = e.dataTransfer.files;
    input.dispatchEvent(new Event('change'));
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
