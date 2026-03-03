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
        redirect("members/view.php?id=$id");
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

<style>
:root {
    --ios-red:    #FF453A;
    --ios-orange: #FF9F0A;
    --ios-green:  #30D158;
    --ios-blue:   #0A84FF;
    --ios-purple: #BF5AF2;
    --ios-teal:   #64D2FF;
}

/* Layout */
.form-container {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: var(--spacing-6);
    max-width: 1200px;
    margin: 0 auto;
}
@media (max-width: 992px) { .form-container { grid-template-columns: 1fr; } }

/* iOS Section Card */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: var(--spacing-4);
}
.ios-section-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
    padding: var(--spacing-4);
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-section-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.ios-section-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-section-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-section-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-section-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }

.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

.ios-section-body { padding: var(--spacing-6); }

/* 3-dot button — mobile only */
.ios-options-btn {
    display: none;
    width: 36px; height: 36px;
    border-radius: 50%;
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

/* Form layout */
.form-row-2-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-5);
}
.form-row { margin-bottom: var(--spacing-5); }
.form-row:last-child { margin-bottom: 0; }

@media (max-width: 768px) {
    .form-row-2-cols { grid-template-columns: 1fr; }
}

.form-label {
    display: block;
    margin-bottom: var(--spacing-2);
    font-weight: var(--font-weight-medium);
    color: var(--text-primary);
    font-size: var(--font-size-sm);
}
.form-label.required::after { content: ' *'; color: var(--danger); }

/* Form actions */
.form-actions {
    margin-top: var(--spacing-8);
    padding-top: var(--spacing-6);
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: var(--spacing-3);
    justify-content: flex-end;
}
@media (max-width: 576px) { .form-actions { flex-direction: column; } }

/* Member info card (right sidebar) */
.info-card {
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    height: fit-content;
    position: sticky;
    top: var(--spacing-4);
}
.info-card-header {
    padding: var(--spacing-4);
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
}
.info-card-icon { color: var(--ios-blue); font-size: var(--font-size-lg); }
.info-card-title { margin: 0; font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); color: var(--text-primary); }
.info-card-body { padding: 0; }

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-3) var(--spacing-4);
    border-bottom: 1px solid var(--border-light);
    font-size: var(--font-size-sm);
}
.detail-row:last-child { border-bottom: none; }
.detail-label { color: var(--text-muted); min-width: 90px; }
.detail-value { color: var(--text-primary); font-weight: var(--font-weight-medium); text-align: right; }

/* iOS bottom-sheet menu */
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
    background: var(--border-color);
    border-radius: 3px;
    margin: 8px auto 4px;
    flex-shrink: 0;
}
.ios-menu-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 20px 16px;
    border-bottom: 1px solid var(--border-color);
    flex-shrink: 0;
}
.ios-menu-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: var(--bg-secondary);
    border: none;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary);
    cursor: pointer;
    transition: background .2s ease;
}
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content {
    padding: 16px;
    overflow-y: auto; flex: 1;
    -webkit-overflow-scrolling: touch;
}

.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title {
    font-size: 13px; font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 10px;
    padding-left: 4px;
}
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }

.ios-menu-item {
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid var(--border-color);
    text-decoration: none;
    color: var(--text-primary);
    transition: background .15s ease;
    cursor: pointer;
    width: 100%;
    background: transparent;
    border-left: none; border-right: none; border-top: none;
    font-family: inherit; font-size: inherit; text-align: left;
}
button.ios-menu-item { border: none; border-bottom: 1px solid var(--border-color); }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }

.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
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

/* Mobile responsive */
@media (max-width: 992px) { .ios-options-btn { display: flex; } }
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .info-card { display: none !important; }
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
                    <a href="<?php echo BASE_URL; ?>members/" class="breadcrumb-link">Members</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>members/view.php?id=<?php echo $id; ?>" class="breadcrumb-link">
                        <?php echo e($m['full_name']); ?>
                    </a>
                </li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
        <h1 class="content-title">Edit Member</h1>
        <p class="content-subtitle">
            Update details for <strong><?php echo e($m['full_name']); ?></strong>
            &nbsp;&middot;&nbsp;<code><?php echo e($m['member_id']); ?></code>
        </p>
    </div>
    <a href="<?php echo BASE_URL; ?>members/view.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back to Profile
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
</div>
<?php endif; ?>

<form method="POST" action="" id="edit-member-form" novalidate>
    <?php echo csrfField(); ?>

    <div class="form-container">

        <!-- ===== LEFT: MAIN FORM ===== -->
        <div>

            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Member Information</h5>
                        <p>Update contact details and playing position</p>
                    </div>
                    <button type="button" class="ios-options-btn" onclick="openIosMenu()" aria-label="Options">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
                <div class="ios-section-body">

                    <div class="form-row-2-cols">
                        <div class="form-group">
                            <label class="form-label required" for="full_name">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name"
                                   value="<?php echo $val('full_name'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required" for="email">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?php echo $val('email'); ?>" required>
                        </div>
                    </div>

                    <div class="form-row-2-cols">
                        <div class="form-group">
                            <label class="form-label" for="phone">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone"
                                   value="<?php echo $val('phone'); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="date_of_birth">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                   value="<?php echo $val('date_of_birth'); ?>"
                                   max="<?php echo date('Y-m-d', strtotime('-5 years')); ?>">
                        </div>
                    </div>

                    <div class="form-row-2-cols">
                        <div class="form-group">
                            <label class="form-label" for="position">Playing Position</label>
                            <select class="form-control form-select" id="position" name="position">
                                <option value="">— Select Position —</option>
                                <?php foreach ($positions as $pos): ?>
                                <option value="<?php echo e($pos); ?>"
                                        <?php echo ($val('position') === $pos) ? 'selected' : ''; ?>>
                                    <?php echo e($pos); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="emergency_contact">Emergency Contact</label>
                            <input type="text" class="form-control" id="emergency_contact"
                                   name="emergency_contact"
                                   placeholder="Name and phone number"
                                   value="<?php echo $val('emergency_contact'); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="form-label" for="address">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo $val('address'); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="<?php echo BASE_URL; ?>members/view.php?id=<?php echo $id; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="save-btn">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>

                </div>
            </div>

        </div><!-- /left -->

        <!-- ===== RIGHT: MEMBER INFO (desktop only) ===== -->
        <div>
            <div class="info-card">
                <div class="info-card-header">
                    <i class="fas fa-info-circle info-card-icon"></i>
                    <h6 class="info-card-title">Member Info</h6>
                </div>
                <div class="info-card-body">
                    <div class="detail-row">
                        <span class="detail-label">Member ID</span>
                        <code class="detail-value"><?php echo e($m['member_id']); ?></code>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value"><?php echo statusBadge($m['status']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Joined</span>
                        <span class="detail-value"><?php echo $m['joined_at'] ? formatDate($m['joined_at'], 'd M Y') : '—'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Last Login</span>
                        <span class="detail-value"><?php echo $m['last_login_at'] ? formatDate($m['last_login_at'], 'd M Y') : 'Never'; ?></span>
                    </div>
                </div>
            </div>
        </div><!-- /right -->

    </div><!-- /form-container -->
</form>

<!-- ===== iOS MENU MODAL (mobile 3-dot) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeIosMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h5 class="ios-menu-title">Edit Member</h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Quick Actions</p>
            <div class="ios-menu-card">
                <button type="button" class="ios-menu-item" onclick="closeIosMenu(); document.getElementById('save-btn').click()">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-save"></i></div>
                        <div class="ios-menu-item-content">
                            <div class="ios-menu-item-label">Save Changes</div>
                            <div class="ios-menu-item-desc">Submit the updated member details</div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <a href="<?php echo BASE_URL; ?>members/view.php?id=<?php echo $id; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-user"></i></div>
                        <div class="ios-menu-item-content">
                            <div class="ios-menu-item-label">View Profile</div>
                            <div class="ios-menu-item-desc">Go back to member profile</div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>members/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-users"></i></div>
                        <div class="ios-menu-item-content">
                            <div class="ios-menu-item-label">All Members</div>
                            <div class="ios-menu-item-desc">Go back to members list</div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <!-- Member Info -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Member Info</p>
            <div class="ios-menu-card">
                <div class="ios-tip-row" style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border-color)">
                    <div style="width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;background:rgba(10,132,255,.15);color:var(--ios-blue)">
                        <i class="fas fa-id-badge"></i>
                    </div>
                    <div style="flex:1;font-size:14px;color:var(--text-secondary)">
                        ID: <strong style="color:var(--text-primary)"><?php echo e($m['member_id']); ?></strong>
                    </div>
                </div>
                <div class="ios-tip-row" style="display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border-color)">
                    <div style="width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;background:rgba(48,209,88,.15);color:var(--ios-green)">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div style="flex:1;font-size:14px;color:var(--text-secondary)">
                        Status: <strong style="color:var(--text-primary)"><?php echo ucfirst($m['status'] ?? '—'); ?></strong>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;padding:12px 16px">
                    <div style="width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;background:rgba(255,159,10,.15);color:var(--ios-orange)">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div style="flex:1;font-size:14px;color:var(--text-secondary)">
                        Joined: <strong style="color:var(--text-primary)"><?php echo $m['joined_at'] ? formatDate($m['joined_at'], 'd M Y') : '—'; ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-home"></i></div>
                        <div class="ios-menu-item-content">
                            <div class="ios-menu-item-label">Dashboard</div>
                        </div>
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
        startY = e.touches[0].clientY;
        isDragging = true;
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

/* Save button loading state */
document.getElementById('edit-member-form').addEventListener('submit', function() {
    var btn = document.getElementById('save-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving…';
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
