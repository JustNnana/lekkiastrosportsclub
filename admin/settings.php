<?php
/**
 * System Settings — Super Admin only
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireSuperAdmin();

$pageTitle = 'Settings';
$db        = Database::getInstance();

// Ensure settings table exists
$db->execute(
    "CREATE TABLE IF NOT EXISTS settings (
        `key`      VARCHAR(100) NOT NULL PRIMARY KEY,
        `value`    TEXT         NULL,
        updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Default values (fallback to constants)
$defaults = [
    'club_name'         => SITE_NAME,
    'club_tagline'      => 'Nigeria\'s Premier Sports Community',
    'club_email'        => MAIL_FROM_EMAIL,
    'club_phone'        => '',
    'club_address'      => '',
    'registration_open' => '1',
];

// Load persisted settings
$rows = $db->fetchAll("SELECT `key`, `value` FROM settings");
$s    = $defaults;
foreach ($rows as $r) {
    $s[$r['key']] = $r['value'];
}

// System info (read-only)
$phpVersion    = PHP_VERSION;
$appEnv        = APP_ENV;
$mailHost      = MAIL_HOST;
$mailUser      = MAIL_USERNAME ? substr(MAIL_USERNAME, 0, 3) . str_repeat('*', max(0, strlen(MAIL_USERNAME) - 6)) . substr(MAIL_USERNAME, -3) : '— not configured';
$paystackOk    = !empty(PAYSTACK_PUBLIC_KEY) && !empty(PAYSTACK_SECRET_KEY);
$vapidOk       = !empty(VAPID_PUBLIC_KEY) && !empty(VAPID_PRIVATE_KEY);
$maxUploadMb   = round(MAX_FILE_SIZE / (1024 * 1024), 1);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header d-flex justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="content-title">Settings</h1>
        <p class="content-subtitle">Manage club configuration and system preferences.</p>
    </div>
</div>

<?php foreach (getFlashMessages() as $f): ?>
<div class="alert alert-<?php echo $f['type'] === 'success' ? 'success' : ($f['type'] === 'error' ? 'danger' : $f['type']); ?> alert-dismissible">
    <?php echo e($f['message']); ?>
    <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
</div>
<?php endforeach; ?>

<div class="row g-4">

    <!-- ─── LEFT COLUMN ─────────────────────────────────────────────────── -->
    <div class="col-lg-8">

        <!-- Club Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-futbol me-2 text-primary"></i>Club Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="<?php echo BASE_URL; ?>admin/settings-actions.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="save_settings">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Club Name</label>
                            <input type="text" name="club_name" class="form-control"
                                   value="<?php echo e($s['club_name']); ?>" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tagline</label>
                            <input type="text" name="club_tagline" class="form-control"
                                   value="<?php echo e($s['club_tagline']); ?>" maxlength="200"
                                   placeholder="e.g. Nigeria's Premier Sports Community">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Email</label>
                            <input type="email" name="club_email" class="form-control"
                                   value="<?php echo e($s['club_email']); ?>" maxlength="191">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" name="club_phone" class="form-control"
                                   value="<?php echo e($s['club_phone']); ?>" maxlength="30"
                                   placeholder="+234 800 000 0000">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Club Address</label>
                            <textarea name="club_address" class="form-control" rows="2"
                                      maxlength="500" placeholder="Physical address of the club"><?php echo e($s['club_address']); ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Registration toggle -->
                    <div class="d-flex align-items-center justify-content-between p-3 rounded"
                         style="background:var(--bg-secondary)">
                        <div>
                            <p class="fw-semibold mb-0">Open Member Registration</p>
                            <small class="text-muted">When off, the public registration form will be disabled.</small>
                        </div>
                        <div class="form-check form-switch ms-3 mb-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                   name="registration_open" id="regToggle" value="1"
                                   <?php echo $s['registration_open'] === '1' ? 'checked' : ''; ?>>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- ─── RIGHT COLUMN ────────────────────────────────────────────────── -->
    <div class="col-lg-4">

        <!-- System Info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-server me-2 text-muted"></i>System Information</h6>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">PHP Version</span>
                    <span class="fw-semibold"><?php echo e($phpVersion); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Environment</span>
                    <span class="badge <?php echo $appEnv === 'production' ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo e(ucfirst($appEnv)); ?>
                    </span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Max Upload</span>
                    <span class="fw-semibold"><?php echo $maxUploadMb; ?> MB</span>
                </li>
            </ul>
        </div>

        <!-- Email Config -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-envelope me-2 text-muted"></i>Email Configuration</h6>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">SMTP Host</span>
                    <span class="fw-semibold"><?php echo e($mailHost); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">SMTP Port</span>
                    <span class="fw-semibold"><?php echo e(MAIL_PORT); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Username</span>
                    <span class="fw-semibold text-truncate ms-2" style="max-width:140px"><?php echo e($mailUser); ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">From Name</span>
                    <span class="fw-semibold text-truncate ms-2" style="max-width:140px"><?php echo e(MAIL_FROM_NAME); ?></span>
                </li>
            </ul>
            <div class="card-footer text-muted" style="font-size:11px">
                Edit <code>.env</code> on the server to change mail credentials.
            </div>
        </div>

        <!-- Paystack & Push -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-plug me-2 text-muted"></i>Integrations</h6>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Paystack</span>
                    <?php if ($paystackOk): ?>
                    <span class="badge badge-success"><i class="fas fa-check me-1"></i>Configured</span>
                    <?php else: ?>
                    <span class="badge badge-danger"><i class="fas fa-times me-1"></i>Not set</span>
                    <?php endif; ?>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center small">
                    <span class="text-muted">Web Push (VAPID)</span>
                    <?php if ($vapidOk): ?>
                    <span class="badge badge-success"><i class="fas fa-check me-1"></i>Configured</span>
                    <?php else: ?>
                    <span class="badge badge-danger"><i class="fas fa-times me-1"></i>Not set</span>
                    <?php endif; ?>
                </li>
            </ul>
            <div class="card-footer text-muted" style="font-size:11px">
                Edit <code>.env</code> on the server to update API keys.
            </div>
        </div>

    </div><!-- /col-lg-4 -->

</div><!-- /row -->

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
