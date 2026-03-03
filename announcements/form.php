<?php
/**
 * Announcement create / edit form (admin only)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

requireAdmin();

$annObj = new Announcement();
$id     = (int)($_GET['id'] ?? 0);
$ann    = $id ? $annObj->getById($id) : null;
$isEdit = (bool)$ann;

if ($id && !$ann) {
    flashError('Announcement not found.');
    redirect('announcements/manage.php');
}

$pageTitle = $isEdit ? 'Edit Announcement' : 'New Announcement';

// ─── POST: handle upload + save ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $title        = sanitize($_POST['title']       ?? '');
    $content      = trim($_POST['content']          ?? '');
    $is_pinned    = isset($_POST['is_pinned'])    ? 1 : 0;
    $is_published = isset($_POST['is_published']) ? 1 : 0;
    $scheduled_at = sanitize($_POST['scheduled_at'] ?? '');

    $errors = [];
    if (!$title)   $errors[] = 'Title is required.';
    if (!$content) $errors[] = 'Content is required.';

    // Handle image upload
    $imagePath = $ann['image_path'] ?? null;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed.';
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'Image must be under 5 MB.';
        } elseif (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
            $errors[] = 'Only JPEG, PNG, GIF, and WebP images are allowed.';
        } else {
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'ann-' . uniqid('', true) . '.' . strtolower($ext);
            $dest     = UPLOAD_PATH . $filename;

            if (!is_dir(UPLOAD_PATH)) mkdir(UPLOAD_PATH, 0755, true);

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                if ($imagePath && file_exists(UPLOAD_PATH . basename($imagePath))) {
                    @unlink(UPLOAD_PATH . basename($imagePath));
                }
                $imagePath = UPLOAD_URL . $filename;
            } else {
                $errors[] = 'Failed to save image.';
            }
        }
    }

    // Handle image removal
    if (isset($_POST['remove_image']) && $imagePath) {
        if (file_exists(UPLOAD_PATH . basename($imagePath))) {
            @unlink(UPLOAD_PATH . basename($imagePath));
        }
        $imagePath = null;
    }

    // Scheduled: only store if not publishing immediately
    $scheduledAt = null;
    if (!$is_published && $scheduled_at) {
        $scheduledAt = date('Y-m-d H:i:s', strtotime($scheduled_at));
    }

    if (empty($errors)) {
        $data = [
            'title'        => $title,
            'content'      => $content,
            'image_path'   => $imagePath,
            'is_pinned'    => $is_pinned,
            'is_published' => $is_published,
            'scheduled_at' => $scheduledAt,
            'published_by' => $_SESSION['user_id'],
        ];

        if ($isEdit) {
            $annObj->update($id, $data);
            flashSuccess('Announcement updated.');
        } else {
            $newId = $annObj->create($data);
            flashSuccess('Announcement created.');
            redirect('announcements/view.php?id=' . $newId);
        }
        redirect('announcements/manage.php');
    }
    // Fall through to show form with errors
}

// Prefill helpers
$fTitle     = e($ann['title']   ?? ($_POST['title']   ?? ''));
$fContent   = e($ann['content'] ?? ($_POST['content'] ?? ''));
$fPublished = ($ann['is_published'] ?? 0) ? 'checked' : '';
$fPinned    = ($ann['is_pinned']    ?? 0) ? 'checked' : '';
$fScheduled = $ann['scheduled_at']
    ? date('Y-m-d\TH:i', strtotime($ann['scheduled_at'])) : '';

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
    grid-template-columns: 1fr 290px;
    gap: var(--spacing-6);
    max-width: 1200px;
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
.ios-section-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-section-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-section-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-section-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-section-icon.red    { background: rgba(255,69,58,.15);  color: var(--ios-red); }

.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

.ios-section-body { padding: var(--spacing-5); }

/* ── 3-dot button (mobile only) ── */
.ios-options-btn {
    display: none;
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    align-items: center; justify-content: center;
    cursor: pointer;
    transition: background .2s ease, transform .15s ease;
    flex-shrink: 0;
}
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* ── Form elements ── */
.form-row { margin-bottom: var(--spacing-5); }
.form-row:last-child { margin-bottom: 0; }
.form-label {
    display: block;
    margin-bottom: var(--spacing-2);
    font-weight: var(--font-weight-medium);
    color: var(--text-primary);
    font-size: var(--font-size-sm);
}
.form-label.required::after { content: ' *'; color: var(--danger); }
.form-text { margin-top: var(--spacing-2); font-size: var(--font-size-xs); color: var(--text-secondary); }

/* ── iOS Toggle Switch ── */
.ios-toggle-row {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: var(--spacing-4) var(--spacing-5);
    border-bottom: 1px solid var(--border-color);
}
.ios-toggle-row:last-child { border-bottom: none; }
.ios-toggle-info { flex: 1; min-width: 0; padding-right: var(--spacing-4); }
.ios-toggle-info-title { font-size: 15px; font-weight: 500; color: var(--text-primary); margin: 0 0 2px; }
.ios-toggle-info-desc  { font-size: 12px; color: var(--text-secondary); margin: 0; }

.ios-toggle {
    position: relative;
    display: inline-flex;
    flex-shrink: 0;
}
.ios-toggle input { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
.ios-toggle-track {
    width: 51px; height: 31px;
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
    border-radius: 100px;
    cursor: pointer;
    transition: background .25s ease, border-color .25s ease;
    position: relative;
}
.ios-toggle-thumb {
    position: absolute;
    top: 2px; left: 2px;
    width: 23px; height: 23px;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
    transition: transform .25s cubic-bezier(.4,0,.2,1);
}
.ios-toggle input:checked ~ .ios-toggle-track {
    background: var(--ios-green);
    border-color: var(--ios-green);
}
.ios-toggle input:checked ~ .ios-toggle-track .ios-toggle-thumb {
    transform: translateX(20px);
}

/* ── Image preview ── */
.img-preview-wrap {
    position: relative;
    display: inline-block;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: var(--spacing-4);
}
.img-preview-wrap img {
    display: block;
    max-height: 180px;
    max-width: 100%;
    border-radius: 12px;
    object-fit: cover;
}
.img-remove-label {
    display: flex; align-items: center; gap: var(--spacing-2);
    margin-top: var(--spacing-3);
    cursor: pointer;
    font-size: var(--font-size-sm);
    color: var(--ios-red);
    user-select: none;
}
.img-remove-label input { width: 16px; height: 16px; cursor: pointer; }

/* Upload dropzone */
.upload-zone {
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: var(--spacing-6) var(--spacing-5);
    text-align: center;
    cursor: pointer;
    transition: border-color .2s ease, background .2s ease;
}
.upload-zone:hover {
    border-color: var(--primary);
    background: rgba(var(--primary-rgb), .04);
}
.upload-zone i { font-size: 28px; color: var(--text-muted); margin-bottom: var(--spacing-3); display: block; }
.upload-zone p { font-size: 14px; color: var(--text-secondary); margin: 0 0 4px; }
.upload-zone small { font-size: 12px; color: var(--text-muted); }
.upload-zone input[type="file"] { display: none; }
#upload-filename { font-size: 12px; color: var(--ios-green); margin-top: 8px; }

/* ── Right sidebar: settings ── */
.settings-sidebar { display: flex; flex-direction: column; gap: 0; }

/* Sticky sidebar */
.settings-sticky {
    position: sticky;
    top: var(--spacing-4);
}

/* ── Form actions inside card ── */
.form-actions-card {
    display: flex; flex-direction: column; gap: var(--spacing-3);
    padding: var(--spacing-5);
}

/* ── iOS bottom-sheet menu ── */
.ios-menu-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 9998;
    opacity: 0; visibility: hidden;
    transition: opacity .3s ease, visibility .3s ease;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }

.ios-menu-modal {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: var(--bg-primary);
    border-radius: 16px 16px 0 0;
    z-index: 9999;
    transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 85vh;
    overflow: hidden;
    display: flex; flex-direction: column;
}
.ios-menu-modal.active { transform: translateY(0); }
.ios-menu-handle {
    width: 36px; height: 5px;
    background: var(--border-color); border-radius: 3px;
    margin: 8px auto 4px; flex-shrink: 0;
}
.ios-menu-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px 16px;
    border-bottom: 1px solid var(--border-color);
    flex-shrink: 0;
}
.ios-menu-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--bg-secondary); border: none;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary); cursor: pointer;
    transition: background .2s ease;
}
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }

.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title {
    font-size: 13px; font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: .5px;
    margin-bottom: 10px; padding-left: 4px;
}
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }

.ios-menu-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    text-decoration: none; color: var(--text-primary);
    transition: background .15s ease;
    cursor: pointer; width: 100%; background: transparent;
    border-left: none; border-right: none; border-top: none;
    font-family: inherit; font-size: inherit; text-align: left;
}
button.ios-menu-item { border: none; border-bottom: 1px solid var(--border-color); }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }

.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.ios-menu-item-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-menu-item-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-menu-item-icon.red    { background: rgba(255,69,58,.15);  color: var(--ios-red); }

.ios-menu-item-label { font-size: 15px; font-weight: 500; }
.ios-menu-item-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }

/* In-menu toggle row */
.ios-menu-toggle-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
}
.ios-menu-toggle-row:last-child { border-bottom: none; }
.ios-menu-toggle-label { font-size: 15px; font-weight: 500; color: var(--text-primary); }
.ios-menu-toggle-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 1px; }

/* ── Responsive ── */
@media (max-width: 992px) { .ios-options-btn { display: flex; } }
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .settings-sidebar { display: none; }
    .form-container { grid-template-columns: 1fr; }
    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 14px; }
    .ios-section-icon { width: 36px; height: 36px; font-size: 16px; }
    .ios-section-title h5 { font-size: 15px; }
    .ios-section-body { padding: var(--spacing-4); }
}
@media (max-width: 480px) {
    .ios-options-btn { width: 32px; height: 32px; }
    .ios-options-btn i { font-size: 14px; }
}
</style>

<!-- ===== DESKTOP PAGE HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="breadcrumb-link">Announcements</a>
                </li>
                <li class="breadcrumb-item active"><?php echo $isEdit ? 'Edit' : 'New'; ?></li>
            </ol>
        </nav>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Announcement' : 'New Announcement'; ?></h1>
        <p class="content-subtitle"><?php echo $isEdit ? 'Update the announcement details below.' : 'Fill in the details to create a new announcement.'; ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i>
    <ul class="mb-0 ps-3"><?php foreach ($errors as $er): ?><li><?php echo e($er); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="ann-form">
    <?php echo csrfField(); ?>

    <div class="form-container">

        <!-- ===== LEFT: MAIN CONTENT ===== -->
        <div>

            <!-- Content card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5><?php echo $isEdit ? 'Edit Announcement' : 'Announcement Content'; ?></h5>
                        <p>Title and body of the announcement</p>
                    </div>
                    <button type="button" class="ios-options-btn" onclick="openIosMenu()" aria-label="Options">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
                <div class="ios-section-body">

                    <div class="form-row">
                        <label class="form-label required" for="ann-title">Title</label>
                        <input type="text" id="ann-title" name="title" class="form-control"
                               value="<?php echo $fTitle; ?>"
                               placeholder="Announcement title"
                               required maxlength="200">
                    </div>

                    <div class="form-row">
                        <label class="form-label required" for="contentEditor">Content</label>
                        <textarea id="contentEditor" name="content" class="form-control" rows="12"
                                  placeholder="Write your announcement here…"
                                  required><?php echo $fContent; ?></textarea>
                        <p class="form-text">HTML tags like &lt;b&gt;, &lt;i&gt;, &lt;a&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;p&gt; are supported.</p>
                    </div>

                </div>
            </div>

            <!-- Image card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon purple">
                        <i class="fas fa-image"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Featured Image</h5>
                        <p>Optional banner image for the announcement</p>
                    </div>
                </div>
                <div class="ios-section-body">

                    <?php if (!empty($ann['image_path'])): ?>
                    <div class="img-preview-wrap">
                        <img src="<?php echo e($ann['image_path']); ?>" alt="Current image">
                    </div>
                    <label class="img-remove-label">
                        <input type="checkbox" name="remove_image" value="1">
                        <i class="fas fa-trash-alt"></i>
                        <span>Remove current image</span>
                    </label>
                    <div style="margin: var(--spacing-4) 0; border-top: 1px solid var(--border-color)"></div>
                    <?php endif; ?>

                    <label class="upload-zone" for="image-upload" id="upload-zone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p><?php echo empty($ann['image_path']) ? 'Click to upload an image' : 'Click to replace image'; ?></p>
                        <small>Max 5 MB &nbsp;·&nbsp; JPEG, PNG, GIF, WebP</small>
                        <div id="upload-filename"></div>
                        <input type="file" id="image-upload" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                    </label>

                </div>
            </div>

        </div><!-- /left -->

        <!-- ===== RIGHT: SETTINGS SIDEBAR (desktop only) ===== -->
        <div class="settings-sidebar">
            <div class="settings-sticky">

                <!-- Publish settings -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon green">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5>Publish Settings</h5>
                        </div>
                    </div>

                    <!-- Publish now toggle -->
                    <div class="ios-toggle-row">
                        <div class="ios-toggle-info">
                            <p class="ios-toggle-info-title">Publish now</p>
                            <p class="ios-toggle-info-desc">Immediately visible to all members</p>
                        </div>
                        <label class="ios-toggle">
                            <input type="checkbox" name="is_published" value="1" id="toggle-publish" <?php echo $fPublished; ?>>
                            <span class="ios-toggle-track"><span class="ios-toggle-thumb"></span></span>
                        </label>
                    </div>

                    <!-- Schedule -->
                    <div class="ios-section-body" style="padding-top: var(--spacing-3); padding-bottom: var(--spacing-3);">
                        <label class="form-label" for="scheduled_at">Schedule for later</label>
                        <input type="datetime-local" id="scheduled_at" name="scheduled_at"
                               class="form-control"
                               value="<?php echo e($fScheduled); ?>">
                        <p class="form-text">Leave blank to save as draft.</p>
                    </div>
                </div>

                <!-- Options -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon orange">
                            <i class="fas fa-sliders-h"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5>Options</h5>
                        </div>
                    </div>

                    <!-- Pin toggle -->
                    <div class="ios-toggle-row">
                        <div class="ios-toggle-info">
                            <p class="ios-toggle-info-title"><i class="fas fa-thumbtack" style="color:var(--ios-red);margin-right:5px;font-size:11px"></i>Pin announcement</p>
                            <p class="ios-toggle-info-desc">Pinned posts appear at the top of the feed</p>
                        </div>
                        <label class="ios-toggle">
                            <input type="checkbox" name="is_pinned" value="1" id="toggle-pin" <?php echo $fPinned; ?>>
                            <span class="ios-toggle-track"><span class="ios-toggle-thumb"></span></span>
                        </label>
                    </div>
                </div>

                <!-- Actions -->
                <div class="ios-section-card">
                    <div class="form-actions-card">
                        <button type="submit" class="btn btn-primary w-100" id="submit-btn">
                            <i class="fas fa-<?php echo $isEdit ? 'save' : 'paper-plane'; ?> me-2"></i>
                            <?php echo $isEdit ? 'Update Announcement' : 'Create Announcement'; ?>
                        </button>
                        <?php if ($isEdit && $ann): ?>
                        <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $id; ?>"
                           class="btn btn-secondary w-100" target="_blank">
                            <i class="fas fa-eye me-2"></i>Preview
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="btn btn-secondary w-100">
                            Cancel
                        </a>
                    </div>
                </div>

            </div><!-- /settings-sticky -->
        </div><!-- /settings-sidebar -->

    </div><!-- /form-container -->

    <!-- ===== MOBILE SUBMIT (shown only below 768px) ===== -->
    <div class="d-md-none mt-3">
        <button type="submit" class="btn btn-primary w-100" id="submit-btn-mobile">
            <i class="fas fa-<?php echo $isEdit ? 'save' : 'paper-plane'; ?> me-2"></i>
            <?php echo $isEdit ? 'Update Announcement' : 'Create Announcement'; ?>
        </button>
    </div>

</form>

<!-- ===== iOS MENU MODAL (mobile 3-dot) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeIosMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h5 class="ios-menu-title"><?php echo $isEdit ? 'Edit Announcement' : 'New Announcement'; ?></h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Quick Actions</p>
            <div class="ios-menu-card">
                <button type="button" class="ios-menu-item" onclick="closeIosMenu(); document.getElementById('submit-btn-mobile').click()">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-<?php echo $isEdit ? 'save' : 'paper-plane'; ?>"></i></div>
                        <div>
                            <div class="ios-menu-item-label"><?php echo $isEdit ? 'Update Announcement' : 'Create Announcement'; ?></div>
                            <div class="ios-menu-item-desc">Submit the form</div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <?php if ($isEdit && $ann): ?>
                <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $id; ?>" class="ios-menu-item" target="_blank">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-eye"></i></div>
                        <div>
                            <div class="ios-menu-item-label">Preview</div>
                            <div class="ios-menu-item-desc">View the announcement</div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-list"></i></div>
                        <div>
                            <div class="ios-menu-item-label">Manage Announcements</div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <!-- Settings toggles (mobile) -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Settings</p>
            <div class="ios-menu-card">
                <div class="ios-menu-toggle-row">
                    <div>
                        <div class="ios-menu-toggle-label">Publish now</div>
                        <div class="ios-menu-toggle-desc">Immediately visible to all members</div>
                    </div>
                    <label class="ios-toggle" style="margin-left:12px">
                        <input type="checkbox" name="is_published_m" id="toggle-publish-m"
                               onchange="document.getElementById('toggle-publish').checked=this.checked"
                               <?php echo $fPublished; ?>>
                        <span class="ios-toggle-track"><span class="ios-toggle-thumb"></span></span>
                    </label>
                </div>
                <div class="ios-menu-toggle-row">
                    <div>
                        <div class="ios-menu-toggle-label">
                            <i class="fas fa-thumbtack" style="color:var(--ios-red);margin-right:5px;font-size:11px"></i>
                            Pin announcement
                        </div>
                        <div class="ios-menu-toggle-desc">Appears at top of the feed</div>
                    </div>
                    <label class="ios-toggle" style="margin-left:12px">
                        <input type="checkbox" name="is_pinned_m" id="toggle-pin-m"
                               onchange="document.getElementById('toggle-pin').checked=this.checked"
                               <?php echo $fPinned; ?>>
                        <span class="ios-toggle-track"><span class="ios-toggle-thumb"></span></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>announcements/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-bullhorn"></i></div>
                        <div class="ios-menu-item-label">Announcements Feed</div>
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
/* iOS menu */
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

/* Swipe to close */
(function() {
    var modal = document.getElementById('iosMenuModal');
    var startY = 0, isDragging = false;
    modal.addEventListener('touchstart', function(e) {
        startY = e.touches[0].clientY; isDragging = true;
    }, { passive: true });
    modal.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        var dy = e.touches[0].clientY - startY;
        if (dy > 0) modal.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    modal.addEventListener('touchend', function(e) {
        if (!isDragging) return;
        isDragging = false;
        var dy = e.changedTouches[0].clientY - startY;
        modal.style.transform = '';
        if (dy > 80) closeIosMenu();
    });
})();

/* Keep desktop + mobile toggles in sync (desktop → mobile) */
document.getElementById('toggle-publish').addEventListener('change', function() {
    document.getElementById('toggle-publish-m').checked = this.checked;
});
document.getElementById('toggle-pin').addEventListener('change', function() {
    document.getElementById('toggle-pin-m').checked = this.checked;
});

/* Upload zone: show selected filename */
document.getElementById('image-upload').addEventListener('change', function() {
    var fn = this.files[0] ? this.files[0].name : '';
    document.getElementById('upload-filename').textContent = fn;
});

/* Submit loading state */
document.getElementById('ann-form').addEventListener('submit', function() {
    ['submit-btn', 'submit-btn-mobile'].forEach(function(id) {
        var btn = document.getElementById(id);
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving…'; }
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
