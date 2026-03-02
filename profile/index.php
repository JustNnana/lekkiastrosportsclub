<?php
/**
 * My Profile — view & edit own profile
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireLogin();

$db     = Database::getInstance();
$userId = (int)$_SESSION['user_id'];

// Load user + member row (if exists)
$profile = $db->fetchOne(
    "SELECT u.id, u.full_name, u.email, u.role, u.status, u.created_at, u.last_login_at,
            m.id AS member_db_id, m.member_id AS member_code, m.phone,
            m.date_of_birth, m.address, m.emergency_contact, m.position,
            m.joined_at, m.status AS member_status
     FROM users u
     LEFT JOIN members m ON m.user_id = u.id
     WHERE u.id = ?",
    [$userId]
);

$isMember  = !empty($profile['member_db_id']);
$pageTitle = 'My Profile';
$error     = '';

// ─── SAVE HANDLER ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $fullName = sanitize($_POST['full_name'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $dob      = sanitize($_POST['date_of_birth'] ?? '');
    $address  = sanitize($_POST['address'] ?? '');
    $emergency= sanitize($_POST['emergency_contact'] ?? '');
    $position = sanitize($_POST['position'] ?? '');

    if (empty($fullName)) {
        $error = 'Full name is required.';
    } else {
        // Update users table
        $db->execute(
            "UPDATE users SET full_name = ?, updated_at = NOW() WHERE id = ?",
            [$fullName, $userId]
        );

        // Update member row if exists
        if ($isMember) {
            $db->execute(
                "UPDATE members SET
                    phone             = ?,
                    date_of_birth     = ?,
                    address           = ?,
                    emergency_contact = ?,
                    position          = ?,
                    updated_at        = NOW()
                 WHERE id = ?",
                [
                    $phone    ?: null,
                    $dob      ?: null,
                    $address  ?: null,
                    $emergency?: null,
                    $position ?: null,
                    (int)$profile['member_db_id'],
                ]
            );
        }

        // Update session name so navbar reflects change immediately
        $_SESSION['full_name'] = $fullName;

        flashSuccess('Profile updated successfully.');
        redirect('profile/');
    }

    // On error — prefill from POST
    $profile['full_name']         = $fullName;
    $profile['phone']             = $phone;
    $profile['date_of_birth']     = $dob;
    $profile['address']           = $address;
    $profile['emergency_contact'] = $emergency;
    $profile['position']          = $position;
}

// ─── PAYMENT SUMMARY (members only) ──────────────────────────────────────────
$paymentSummary = null;
$recentPayments = [];
if ($isMember) {
    $paymentSummary = $db->fetchOne(
        "SELECT
            COUNT(*)                                   AS total,
            SUM(status = 'paid')                       AS paid,
            SUM(status = 'pending')                    AS pending,
            SUM(status = 'overdue')                    AS overdue,
            COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END), 0) AS total_paid
         FROM payments WHERE member_id = ?",
        [(int)$profile['member_db_id']]
    );

    $recentPayments = $db->fetchAll(
        "SELECT p.amount, p.status, p.payment_date, d.title AS due_name
         FROM payments p JOIN dues d ON d.id = p.due_id
         WHERE p.member_id = ?
         ORDER BY p.created_at DESC LIMIT 5",
        [(int)$profile['member_db_id']]
    );
}

$positions = ['Goalkeeper','Defender','Midfielder','Forward','Winger','Striker','Captain','Vice-Captain','Other'];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header d-flex justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="content-title">My Profile</h1>
        <p class="content-subtitle">View and update your personal information.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo BASE_URL; ?>profile/change-password.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-lock me-1"></i> Change Password
        </a>
        <a href="<?php echo BASE_URL; ?>profile/notifications.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-bell me-1"></i> Notifications
        </a>
    </div>
</div>

<?php foreach (getFlashMessages() as $f): ?>
<div class="alert alert-<?php echo $f['type'] === 'success' ? 'success' : ($f['type'] === 'error' ? 'danger' : $f['type']); ?> alert-dismissible">
    <?php echo e($f['message']); ?>
    <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
</div>
<?php endforeach; ?>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?></div>
<?php endif; ?>

<div class="row g-4">

    <!-- ─── LEFT: PROFILE FORM ──────────────────────────────────────────── -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user-edit me-2 text-primary"></i>Personal Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php echo csrfField(); ?>

                    <!-- Avatar + read-only info -->
                    <div class="d-flex align-items-center gap-4 mb-4 p-3 rounded"
                         style="background:var(--bg-secondary)">
                        <div class="profile-avatar-lg">
                            <?php echo e(getInitials($profile['full_name'])); ?>
                        </div>
                        <div>
                            <p class="fw-semibold mb-0"><?php echo e($profile['full_name']); ?></p>
                            <small class="text-muted"><?php echo e($profile['email']); ?></small><br>
                            <?php if ($isMember): ?>
                            <small class="text-muted"><?php echo e($profile['member_code']); ?></small>
                            <?php endif; ?>
                            <div class="mt-1">
                                <span class="badge badge-info"><?php echo e(str_replace('_', ' ', ucfirst($profile['role']))); ?></span>
                                <?php if ($isMember): ?>
                                <?php echo statusBadge($profile['member_status']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <!-- Full Name -->
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control"
                                   value="<?php echo e($profile['full_name']); ?>" required maxlength="150">
                        </div>

                        <!-- Email (read-only) -->
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" value="<?php echo e($profile['email']); ?>"
                                   readonly style="background:var(--bg-secondary)">
                            <small class="text-muted">Contact an admin to change your email.</small>
                        </div>

                        <?php if ($isMember): ?>
                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?php echo e($profile['phone'] ?? ''); ?>" maxlength="30"
                                   placeholder="+234 800 000 0000">
                        </div>

                        <!-- Date of Birth -->
                        <div class="col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control"
                                   value="<?php echo e($profile['date_of_birth'] ?? ''); ?>">
                        </div>

                        <!-- Position -->
                        <div class="col-md-6">
                            <label class="form-label">Playing Position</label>
                            <select name="position" class="form-control">
                                <option value="">— Not specified —</option>
                                <?php foreach ($positions as $pos): ?>
                                <option value="<?php echo e($pos); ?>"
                                    <?php echo ($profile['position'] ?? '') === $pos ? 'selected' : ''; ?>>
                                    <?php echo e($pos); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Emergency Contact -->
                        <div class="col-md-6">
                            <label class="form-label">Emergency Contact</label>
                            <input type="text" name="emergency_contact" class="form-control"
                                   value="<?php echo e($profile['emergency_contact'] ?? ''); ?>" maxlength="200"
                                   placeholder="Name and phone number">
                        </div>

                        <!-- Address -->
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"
                                      maxlength="500" placeholder="Your home or mailing address"><?php echo e($profile['address'] ?? ''); ?></textarea>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ─── RIGHT: SUMMARY CARDS ────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- Account Details -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-id-card me-2 text-muted"></i>Account Details</h6>
            </div>
            <ul class="list-group list-group-flush">
                <?php if ($isMember): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Member ID</span>
                    <span class="fw-semibold font-monospace"><?php echo e($profile['member_code']); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Joined</span>
                    <span class="fw-semibold"><?php echo $profile['joined_at'] ? formatDate($profile['joined_at'], 'd M Y') : '—'; ?></span>
                </li>
                <?php endif; ?>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Last Login</span>
                    <span class="fw-semibold"><?php echo $profile['last_login_at'] ? formatDate($profile['last_login_at'], 'd M Y') : 'Now'; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Account Since</span>
                    <span class="fw-semibold"><?php echo formatDate($profile['created_at'], 'd M Y'); ?></span>
                </li>
            </ul>
        </div>

        <!-- Payment Summary (members only) -->
        <?php if ($isMember && $paymentSummary): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-receipt me-2 text-muted"></i>Payments</h6>
                <a href="<?php echo BASE_URL; ?>payments/my-payments.php" class="text-primary" style="font-size:var(--font-size-sm)">View all</a>
            </div>
            <div class="card-body pb-2">
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <p class="mb-0 fw-bold text-success"><?php echo (int)$paymentSummary['paid']; ?></p>
                        <small class="text-muted">Paid</small>
                    </div>
                    <div class="col-4">
                        <p class="mb-0 fw-bold text-warning"><?php echo (int)$paymentSummary['pending']; ?></p>
                        <small class="text-muted">Pending</small>
                    </div>
                    <div class="col-4">
                        <p class="mb-0 fw-bold text-danger"><?php echo (int)$paymentSummary['overdue']; ?></p>
                        <small class="text-muted">Overdue</small>
                    </div>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">Total paid</small>
                    <span class="fw-semibold text-success">₦<?php echo number_format((float)$paymentSummary['total_paid']); ?></span>
                </div>
            </div>
            <?php if ($recentPayments): ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($recentPayments as $p): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center small py-2">
                    <span class="text-muted text-truncate me-2" style="max-width:120px"><?php echo e($p['due_name']); ?></span>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <?php echo statusBadge($p['status']); ?>
                        <span class="fw-semibold">₦<?php echo number_format((float)$p['amount']); ?></span>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-link me-2 text-muted"></i>Quick Links</h6>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item">
                    <a href="<?php echo BASE_URL; ?>profile/change-password.php"
                       class="d-flex align-items-center gap-2 text-decoration-none">
                        <i class="fas fa-lock text-primary" style="width:16px"></i>
                        <span class="small">Change Password</span>
                    </a>
                </li>
                <li class="list-group-item">
                    <a href="<?php echo BASE_URL; ?>profile/notifications.php"
                       class="d-flex align-items-center gap-2 text-decoration-none">
                        <i class="fas fa-bell text-primary" style="width:16px"></i>
                        <span class="small">Notification Preferences</span>
                    </a>
                </li>
                <?php if ($isMember): ?>
                <li class="list-group-item">
                    <a href="<?php echo BASE_URL; ?>payments/my-payments.php"
                       class="d-flex align-items-center gap-2 text-decoration-none">
                        <i class="fas fa-receipt text-primary" style="width:16px"></i>
                        <span class="small">My Payments</span>
                    </a>
                </li>
                <?php endif; ?>
                <li class="list-group-item">
                    <a href="<?php echo BASE_URL; ?>logout.php"
                       class="d-flex align-items-center gap-2 text-decoration-none text-danger">
                        <i class="fas fa-sign-out-alt" style="width:16px"></i>
                        <span class="small">Log Out</span>
                    </a>
                </li>
            </ul>
        </div>

    </div><!-- /col-lg-4 -->
</div><!-- /row -->

<style>
.profile-avatar-lg {
    width: 64px; height: 64px; flex-shrink: 0;
    border-radius: var(--border-radius-full);
    background: linear-gradient(135deg, var(--primary), var(--primary-700));
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: var(--font-weight-semibold);
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
