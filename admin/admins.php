<?php
/**
 * Admin Accounts — Super Admin only
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireSuperAdmin();

$pageTitle = 'Admin Accounts';
$db        = Database::getInstance();

$admins = $db->fetchAll(
    "SELECT id, full_name, email, role, status, created_at, last_login_at
     FROM users WHERE role IN ('admin','super_admin') ORDER BY role DESC, full_name ASC"
);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header d-flex justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="content-title">Admin Accounts</h1>
        <p class="content-subtitle">Manage system administrators. Only super admins can access this page.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>admin/create-admin.php" class="btn btn-primary btn-sm">
        <i class="fas fa-user-shield"></i> Add Admin
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Login</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="member-avatar"><?php echo e(getInitials($a['full_name'])); ?></div>
                            <span class="fw-semibold"><?php echo e($a['full_name']); ?></span>
                        </div>
                    </td>
                    <td class="text-muted"><?php echo e($a['email']); ?></td>
                    <td>
                        <span class="badge <?php echo $a['role'] === 'super_admin' ? 'badge-primary' : 'badge-info'; ?>">
                            <?php echo e(str_replace('_', ' ', ucfirst($a['role']))); ?>
                        </span>
                    </td>
                    <td><?php echo statusBadge($a['status']); ?></td>
                    <td class="text-muted">
                        <?php echo $a['last_login_at'] ? formatDate($a['last_login_at'], 'd M Y, g:i A') : 'Never'; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($a['id'] !== $_SESSION['user_id'] && $a['role'] !== 'super_admin'): ?>
                        <form method="POST" action="<?php echo BASE_URL; ?>admin/admin-actions.php"
                              onsubmit="return confirm('Remove admin access from <?php echo e($a['full_name']); ?>?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="revoke_admin">
                            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
                            <button class="btn btn-danger btn-sm">
                                <i class="fas fa-user-minus"></i> Revoke
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.member-avatar { width:34px; height:34px; flex-shrink:0; border-radius:var(--border-radius-full); background:linear-gradient(135deg,var(--primary),var(--primary-700)); color:#fff; display:flex; align-items:center; justify-content:center; font-size:var(--font-size-xs); font-weight:var(--font-weight-semibold); }
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
