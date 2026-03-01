<?php
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Member.php';

requireAdmin();

$id        = (int)($_GET['id'] ?? 0);
$memberObj = new Member();
$m         = $memberObj->getById($id);

if (!$m) { flashError('Member not found.'); redirect('members/'); }

$pageTitle = 'Edit — ' . $m['full_name'];
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'full_name'         => sanitize($_POST['full_name']         ?? ''),
        'email'             => sanitize($_POST['email']             ?? ''),
        'phone'             => sanitize($_POST['phone']             ?? ''),
        'date_of_birth'     => sanitize($_POST['date_of_birth']     ?? ''),
        'address'           => sanitize($_POST['address']           ?? ''),
        'emergency_contact' => sanitize($_POST['emergency_contact'] ?? ''),
        'position'          => sanitize($_POST['position']          ?? ''),
    ];

    if (empty($data['full_name'])) {
        $error = 'Full name is required.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($memberObj->update($id, $data)) {
        flashSuccess("Member <strong>{$data['full_name']}</strong> updated successfully.");
        redirect('members/view.php?id=' . $id);
    } else {
        $error = 'Update failed. Please try again.';
    }
}

// Prefill from DB on GET, from POST on failed submit
$val = fn(string $field) => e($_POST[$field] ?? $m[$field] ?? '');

$positions = ['Goalkeeper','Defender','Midfielder','Forward','Winger','Striker','Captain','Vice-Captain','Other'];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header d-flex justify-content-between flex-wrap gap-3">
    <div>
        <div class="breadcrumb-trail mb-1 text-muted" style="font-size:var(--font-size-sm)">
            <a href="<?php echo BASE_URL; ?>members/" class="text-muted text-decoration-none">Members</a>
            <span class="mx-2">/</span>
            <a href="<?php echo BASE_URL; ?>members/view.php?id=<?php echo $id; ?>" class="text-muted text-decoration-none">
                <?php echo e($m['full_name']); ?>
            </a>
            <span class="mx-2">/</span> Edit
        </div>
        <h1 class="content-title">Edit Member</h1>
        <p class="content-subtitle">Update details for <strong><?php echo e($m['full_name']); ?></strong>
            &nbsp;·&nbsp; <code><?php echo e($m['member_id']); ?></code>
        </p>
    </div>
    <a href="<?php echo BASE_URL; ?>members/view.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Profile
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
</div>
<?php endif; ?>

<form method="POST" action="" novalidate>
    <?php echo csrfField(); ?>

    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title"><i class="fas fa-edit me-2 text-primary"></i>Member Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">

                        <div class="col-12 col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name"
                                   value="<?php echo $val('full_name'); ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email"
                                   value="<?php echo $val('email'); ?>" required>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone"
                                   value="<?php echo $val('phone'); ?>">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="date_of_birth"
                                   value="<?php echo $val('date_of_birth'); ?>"
                                   max="<?php echo date('Y-m-d', strtotime('-5 years')); ?>">
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Playing Position</label>
                            <select class="form-control form-select" name="position">
                                <option value="">— Select Position —</option>
                                <?php foreach ($positions as $pos): ?>
                                <option value="<?php echo e($pos); ?>"
                                        <?php echo ($val('position') === $pos) ? 'selected' : ''; ?>>
                                    <?php echo e($pos); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label">Emergency Contact</label>
                            <input type="text" class="form-control" name="emergency_contact"
                                   placeholder="Name and phone number"
                                   value="<?php echo $val('emergency_contact'); ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea class="form-control" name="address" rows="3"><?php echo $val('address'); ?></textarea>
                        </div>

                    </div>
                </div>
                <div class="card-footer d-flex gap-2 justify-content-end">
                    <a href="<?php echo BASE_URL; ?>members/view.php?id=<?php echo $id; ?>"
                       class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="save-btn">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>

        <!-- Info sidebar -->
        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title"><i class="fas fa-info-circle me-2 text-info"></i>Member Info</h6>
                </div>
                <div class="card-body">
                    <ul class="detail-list">
                        <li><span class="dl-label">Member ID</span>
                            <code><?php echo e($m['member_id']); ?></code></li>
                        <li><span class="dl-label">Status</span>
                            <?php echo statusBadge($m['status']); ?></li>
                        <li><span class="dl-label">Joined</span>
                            <span><?php echo $m['joined_at'] ? formatDate($m['joined_at'], 'd M Y') : '—'; ?></span></li>
                        <li><span class="dl-label">Last Login</span>
                            <span><?php echo $m['last_login_at'] ? formatDate($m['last_login_at'], 'd M Y') : 'Never'; ?></span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>

<style>
.detail-list { list-style:none; padding:0; margin:0; }
.detail-list li { display:flex; justify-content:space-between; align-items:center; padding:var(--spacing-3) var(--spacing-5); border-bottom:1px solid var(--border-light); font-size:var(--font-size-sm); }
.detail-list li:last-child { border-bottom:none; }
.dl-label { color:var(--text-muted); min-width:90px; }
</style>

<script>
document.querySelector('form').addEventListener('submit', function() {
    var btn = document.getElementById('save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving…';
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
