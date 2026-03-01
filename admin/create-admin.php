<?php
/**
 * Create Admin Account — Super Admin only
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireSuperAdmin();

$pageTitle = 'Add Admin Account';
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName = sanitize($_POST['full_name'] ?? '');
    $email    = sanitize($_POST['email']     ?? '');
    $role     = in_array($_POST['role'] ?? '', ['admin', 'super_admin']) ? $_POST['role'] : 'admin';

    if (empty($fullName)) {
        $error = 'Full name is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db     = Database::getInstance();
        $exists = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);

        if ($exists) {
            $error = 'An account with this email already exists.';
        } else {
            // Generate temp password
            $tempPw = 'Admin@' . date('Y') . '!';
            $hash   = password_hash($tempPw, PASSWORD_BCRYPT, ['cost' => 12]);

            $db->insert(
                "INSERT INTO users (full_name, email, password_hash, role, status, must_change_password, created_at)
                 VALUES (?, ?, ?, ?, 'active', 1, NOW())",
                [$fullName, $email, $hash, $role]
            );

            // Send welcome email
            try {
                require_once dirname(__DIR__) . '/app/mail/emails.php';
                sendWelcomeEmail($email, $fullName, 'N/A (Admin)', $tempPw);
            } catch (Exception $e) {
                error_log('Admin welcome email failed: ' . $e->getMessage());
            }

            flashSuccess("Admin account for <strong>{$fullName}</strong> created. Temp password: <code>{$tempPw}</code>");
            redirect('admin/admins.php');
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header d-flex justify-content-between flex-wrap gap-3">
    <div>
        <div class="breadcrumb-trail mb-1 text-muted" style="font-size:var(--font-size-sm)">
            <a href="<?php echo BASE_URL; ?>admin/admins.php" class="text-muted text-decoration-none">
                Admin Accounts
            </a>
            <span class="mx-2">/</span> Add Admin
        </div>
        <h1 class="content-title">Add Admin Account</h1>
        <p class="content-subtitle">Grant a user admin access to the management system.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>admin/admins.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-7 col-lg-5">
        <?php if ($error): ?>
        <div class="alert alert-danger mb-4">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h6 class="card-title"><i class="fas fa-user-shield me-2 text-primary"></i>New Admin Details</h6>
            </div>
            <div class="card-body">
                <form method="POST" novalidate>
                    <?php echo csrfField(); ?>

                    <div class="form-group">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name"
                               value="<?php echo e($_POST['full_name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group mt-4">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email"
                               value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                        <small class="text-muted">Login credentials will be sent here.</small>
                    </div>

                    <div class="form-group mt-4">
                        <label class="form-label">Role</label>
                        <select class="form-control form-select" name="role">
                            <option value="admin"       <?php echo (($_POST['role'] ?? 'admin') === 'admin')       ? 'selected' : ''; ?>>Admin</option>
                            <option value="super_admin" <?php echo (($_POST['role'] ?? '') === 'super_admin') ? 'selected' : ''; ?>>Super Admin</option>
                        </select>
                        <small class="text-muted">Super Admins can create/delete other admins and delete members.</small>
                    </div>

                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        A temporary password will be generated and emailed. The admin must change it on first login.
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-user-plus me-2"></i> Create Admin Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
