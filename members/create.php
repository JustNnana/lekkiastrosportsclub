<?php
/**
 * Create Member — Admin adds a new member and sends welcome email
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Member.php';

requireAdmin();

$pageTitle = 'Add New Member';
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

    // Validate required
    if (empty($data['full_name'])) {
        $error = 'Full name is required.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $memberObj = new Member();
        $result    = $memberObj->create($data);

        if ($result['success']) {
            // Send welcome email
            $emailSent = false;
            try {
                require_once dirname(__DIR__) . '/app/mail/emails.php';
                $emailSent = sendWelcomeEmail(
                    $data['email'],
                    $data['full_name'],
                    $result['member_id'],
                    $result['temp_password']
                );
            } catch (Exception $e) {
                error_log('Welcome email failed: ' . $e->getMessage());
            }

            $msg = "Member <strong>{$data['full_name']}</strong> added — ID: <strong>{$result['member_id']}</strong>.";
            $msg .= $emailSent
                ? ' Welcome email sent.'
                : ' <em>Welcome email could not be sent — share credentials manually.</em>';

            // Store temp password in session flash so admin can note it
            $_SESSION['new_member_creds'] = [
                'name'     => $data['full_name'],
                'id'       => $result['member_id'],
                'email'    => $data['email'],
                'password' => $result['temp_password'],
                'sent'     => $emailSent,
            ];

            flashSuccess($msg);
            redirect('members/');
        } else {
            $error = $result['error'];
        }
    }
}

$positions = [
    'Goalkeeper','Defender','Midfielder','Forward',
    'Winger','Striker','Captain','Vice-Captain','Other'
];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<!-- ===== PAGE HEADER ===== -->
<div class="content-header d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
        <div class="breadcrumb-trail mb-1">
            <a href="<?php echo BASE_URL; ?>members/" class="text-muted text-decoration-none">
                <i class="fas fa-users me-1"></i>Members
            </a>
            <span class="text-muted mx-2">/</span>
            <span>Add Member</span>
        </div>
        <h1 class="content-title">Add New Member</h1>
        <p class="content-subtitle">Create a member account. A welcome email with login credentials will be sent.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>members/" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Members
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
</div>
<?php endif; ?>

<form method="POST" action="" id="create-member-form" novalidate>
    <?php echo csrfField(); ?>

    <div class="row g-4">

        <!-- Left: Personal Info -->
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title"><i class="fas fa-user me-2 text-primary"></i>Personal Information</h6>
                </div>
                <div class="card-body">
                    <div class="row g-4">

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="full_name">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       placeholder="e.g. John Adeyemi"
                                       value="<?php echo e($_POST['full_name'] ?? ''); ?>"
                                       required autocomplete="name">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="email">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control" id="email" name="email"
                                       placeholder="e.g. john@email.com"
                                       value="<?php echo e($_POST['email'] ?? ''); ?>"
                                       required autocomplete="email">
                                <small class="text-muted">Login credentials will be sent to this email.</small>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                       placeholder="e.g. 08012345678"
                                       value="<?php echo e($_POST['phone'] ?? ''); ?>"
                                       autocomplete="tel">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="date_of_birth">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                       value="<?php echo e($_POST['date_of_birth'] ?? ''); ?>"
                                       max="<?php echo date('Y-m-d', strtotime('-5 years')); ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="position">Playing Position</label>
                                <select class="form-control form-select" id="position" name="position">
                                    <option value="">— Select Position —</option>
                                    <?php foreach ($positions as $pos): ?>
                                    <option value="<?php echo e($pos); ?>"
                                            <?php echo (($_POST['position'] ?? '') === $pos) ? 'selected' : ''; ?>>
                                        <?php echo e($pos); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label" for="emergency_contact">Emergency Contact</label>
                                <input type="text" class="form-control" id="emergency_contact"
                                       name="emergency_contact"
                                       placeholder="Name and phone number"
                                       value="<?php echo e($_POST['emergency_contact'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label" for="address">Address</label>
                                <textarea class="form-control" id="address" name="address"
                                          rows="3"
                                          placeholder="Home address"><?php echo e($_POST['address'] ?? ''); ?></textarea>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Summary + Submit -->
        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="card-title"><i class="fas fa-info-circle me-2 text-info"></i>What Happens Next</h6>
                </div>
                <div class="card-body">
                    <ul class="what-next-list">
                        <li>
                            <i class="fas fa-id-badge text-primary"></i>
                            <span>A unique Member ID will be auto-generated (SC/<?php echo date('Y'); ?>/XXXXXX)</span>
                        </li>
                        <li>
                            <i class="fas fa-key text-warning"></i>
                            <span>A temporary password will be generated</span>
                        </li>
                        <li>
                            <i class="fas fa-envelope text-info"></i>
                            <span>A welcome email with login credentials will be sent</span>
                        </li>
                        <li>
                            <i class="fas fa-lock text-success"></i>
                            <span>The member must change their password on first login</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h6 class="card-title"><i class="fas fa-paper-plane me-2 text-success"></i>Submit</h6>
                </div>
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100" id="submit-btn">
                        <i class="fas fa-user-plus me-2"></i> Add Member & Send Welcome Email
                    </button>
                    <a href="<?php echo BASE_URL; ?>members/" class="btn btn-secondary w-100 mt-2">
                        Cancel
                    </a>
                </div>
            </div>
        </div>

    </div>
</form>

<style>
.breadcrumb-trail { font-size:var(--font-size-sm); }

.what-next-list { list-style:none; padding:0; margin:0; }
.what-next-list li {
    display:flex; gap:var(--spacing-3); align-items:flex-start;
    padding:var(--spacing-3) 0;
    border-bottom:1px solid var(--border-light);
    font-size:var(--font-size-sm); color:var(--text-secondary);
}
.what-next-list li:last-child { border-bottom:none; }
.what-next-list li i { margin-top:2px; width:16px; flex-shrink:0; }
</style>

<script>
document.getElementById('create-member-form').addEventListener('submit', function() {
    var btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating account…';
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
