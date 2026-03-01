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
                // Delete old image
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

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Announcement' : 'New Announcement'; ?></h1>
        <p class="content-subtitle"><?php echo $isEdit ? 'Update the announcement below.' : 'Fill in the details to create an announcement.'; ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?php echo csrfField(); ?>

    <div class="row g-4">
        <!-- Left: main content -->
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h6 class="card-title mb-0">Content</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control"
                               value="<?php echo e($ann['title'] ?? ($_POST['title'] ?? '')); ?>"
                               placeholder="Announcement title" required maxlength="200">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content <span class="text-danger">*</span></label>
                        <textarea name="content" id="contentEditor" class="form-control" rows="12"
                                  placeholder="Write your announcement here…" required><?php echo e($ann['content'] ?? ($_POST['content'] ?? '')); ?></textarea>
                        <small class="text-muted">HTML tags like &lt;b&gt;, &lt;i&gt;, &lt;a&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;p&gt; are supported.</small>
                    </div>
                </div>
            </div>

            <!-- Image -->
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Featured Image</h6></div>
                <div class="card-body">
                    <?php if (!empty($ann['image_path'])): ?>
                    <div class="mb-3">
                        <img src="<?php echo e($ann['image_path']); ?>" alt="Current image"
                             style="max-height:200px;border-radius:8px;object-fit:cover;">
                        <div class="mt-2">
                            <label class="d-flex align-items-center gap-2" style="cursor:pointer">
                                <input type="checkbox" name="remove_image" value="1">
                                <span class="text-danger small">Remove current image</span>
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="form-label">Upload <?php echo empty($ann['image_path']) ? '' : 'New '; ?>Image</label>
                        <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                        <small class="text-muted">Max 5 MB. JPEG, PNG, GIF, or WebP.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: settings -->
        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h6 class="card-title mb-0">Publish Settings</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="d-flex align-items-center gap-3" style="cursor:pointer">
                            <input type="checkbox" name="is_published" value="1"
                                   <?php echo ($ann['is_published'] ?? 0) ? 'checked' : ''; ?>>
                            <div>
                                <div class="fw-semibold">Publish now</div>
                                <small class="text-muted">Immediately visible to all members</small>
                            </div>
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Schedule for later (optional)</label>
                        <input type="datetime-local" name="scheduled_at" class="form-control"
                               value="<?php echo $ann['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($ann['scheduled_at'])) : ''; ?>">
                        <small class="text-muted">Leave blank to keep as draft.</small>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h6 class="card-title mb-0">Options</h6></div>
                <div class="card-body">
                    <label class="d-flex align-items-center gap-3" style="cursor:pointer">
                        <input type="checkbox" name="is_pinned" value="1"
                               <?php echo ($ann['is_pinned'] ?? 0) ? 'checked' : ''; ?>>
                        <div>
                            <div class="fw-semibold"><i class="fas fa-thumbtack text-danger me-1"></i>Pin announcement</div>
                            <small class="text-muted">Pinned posts appear at the top of the feed</small>
                        </div>
                    </label>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?php echo $isEdit ? 'Update Announcement' : 'Create Announcement'; ?>
                </button>
                <?php if ($isEdit && $ann): ?>
                <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $id; ?>"
                   class="btn btn-secondary" target="_blank">
                    <i class="fas fa-eye me-2"></i>Preview
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
