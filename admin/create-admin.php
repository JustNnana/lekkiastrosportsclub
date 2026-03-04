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
            $tempPw = 'Admin@' . date('Y') . '!';
            $hash   = password_hash($tempPw, PASSWORD_BCRYPT, ['cost' => 12]);

            $db->insert(
                "INSERT INTO users (full_name, email, password_hash, role, status, must_change_password, created_at)
                 VALUES (?, ?, ?, ?, 'active', 1, NOW())",
                [$fullName, $email, $hash, $role]
            );

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
    max-width: 900px;
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
.ios-form-hint { font-size: 12px; color: var(--text-muted); margin-top: 5px; }

/* ── Role selector ── */
.ios-role-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.ios-role-card {
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 14px;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    position: relative;
}
.ios-role-card:has(input:checked) {
    border-color: var(--ios-blue);
    background: rgba(10,132,255,.05);
}
.ios-role-card input[type="radio"] {
    position: absolute; opacity: 0; width: 0; height: 0;
}
.ios-role-icon {
    width: 36px; height: 36px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; margin-bottom: 8px;
}
.ios-role-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-role-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-role-name { font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 3px; }
.ios-role-desc { font-size: 12px; color: var(--text-muted); margin: 0; line-height: 1.4; }
.ios-role-check {
    position: absolute; top: 10px; right: 10px;
    width: 20px; height: 20px; border-radius: 50%;
    background: var(--border-color); border: 2px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    transition: background .2s, border-color .2s;
}
.ios-role-card:has(input:checked) .ios-role-check {
    background: var(--ios-blue); border-color: var(--ios-blue);
}
.ios-role-check::after {
    content: ''; display: none;
    width: 5px; height: 9px;
    border: 2px solid #fff; border-top: none; border-left: none;
    transform: rotate(45deg) translate(-1px, -1px);
}
.ios-role-card:has(input:checked) .ios-role-check::after { display: block; }

/* ── Temp-password info strip ── */
.ios-info-strip {
    display: flex; gap: 12px; align-items: flex-start;
    background: rgba(10,132,255,.08);
    border: 1px solid rgba(10,132,255,.2);
    border-radius: 12px;
    padding: 14px 16px;
}
.ios-info-strip-icon { color: var(--ios-blue); font-size: 16px; flex-shrink: 0; margin-top: 1px; }
.ios-info-strip-text { font-size: 13px; color: var(--text-secondary); line-height: 1.5; }
.ios-info-strip-text strong { color: var(--text-primary); }

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
    .ios-role-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ===== DESKTOP HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-start">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>admin/admins.php" class="breadcrumb-link">Admin Accounts</a>
                </li>
                <li class="breadcrumb-item active">Add Admin</li>
            </ol>
        </nav>
        <h1 class="content-title">Add Admin Account</h1>
        <p class="content-subtitle">Grant a user admin access to the management system.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>admin/admins.php" class="btn btn-secondary flex-shrink-0">
        <i class="fas fa-arrow-left me-2"></i>Back to Admins
    </a>
</div>

<!-- ===== MOBILE HEADER ===== -->
<div class="ios-section-header d-md-none mb-3" style="border-radius:14px;border:1px solid var(--border-color)">
    <div class="ios-section-icon purple">
        <i class="fas fa-user-shield"></i>
    </div>
    <div class="ios-section-title">
        <h5>Add Admin Account</h5>
        <p>Grant management access</p>
    </div>
    <button onclick="openIosMenu()" style="width:36px;height:36px;border-radius:50%;background:var(--bg-secondary);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0">
        <i class="fas fa-ellipsis-v" style="color:var(--text-primary);font-size:16px"></i>
    </button>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4" style="max-width:900px;margin-left:auto;margin-right:auto;border-radius:12px">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
</div>
<?php endif; ?>

<form method="POST" novalidate id="createAdminForm">
    <?php echo csrfField(); ?>

    <div class="form-container">

        <!-- ===== LEFT ===== -->
        <div>

            <!-- Account Details card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Account Details</h5>
                        <p>Name and email address</p>
                    </div>
                </div>
                <div class="ios-section-body">

                    <div class="ios-form-group">
                        <label class="ios-form-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" class="form-control" required maxlength="200"
                               style="border-radius:10px"
                               value="<?php echo e($_POST['full_name'] ?? ''); ?>"
                               placeholder="e.g. James Adeyemi">
                    </div>

                    <div class="ios-form-group">
                        <label class="ios-form-label">Email Address <span class="req">*</span></label>
                        <input type="email" name="email" class="form-control" required
                               style="border-radius:10px"
                               value="<?php echo e($_POST['email'] ?? ''); ?>"
                               placeholder="e.g. james@example.com">
                        <p class="ios-form-hint">Login credentials will be sent to this address.</p>
                    </div>

                </div>
            </div>

            <!-- Role card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon purple">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Role</h5>
                        <p>Select the access level for this account</p>
                    </div>
                </div>
                <div class="ios-section-body">

                    <div class="ios-form-group" style="margin-bottom:0">
                        <div class="ios-role-grid">

                            <label class="ios-role-card">
                                <input type="radio" name="role" value="admin"
                                       <?php echo (($_POST['role'] ?? 'admin') === 'admin') ? 'checked' : ''; ?>>
                                <div class="ios-role-check"></div>
                                <div class="ios-role-icon blue"><i class="fas fa-user-cog"></i></div>
                                <p class="ios-role-name">Admin</p>
                                <p class="ios-role-desc">Manage members, events, fixtures and documents.</p>
                            </label>

                            <label class="ios-role-card">
                                <input type="radio" name="role" value="super_admin"
                                       <?php echo (($_POST['role'] ?? '') === 'super_admin') ? 'checked' : ''; ?>>
                                <div class="ios-role-check"></div>
                                <div class="ios-role-icon purple"><i class="fas fa-user-shield"></i></div>
                                <p class="ios-role-name">Super Admin</p>
                                <p class="ios-role-desc">Full access — create/delete admins and members.</p>
                            </label>

                        </div>
                    </div>

                </div>
            </div>

            <!-- Info strip -->
            <div class="ios-info-strip mb-4" style="max-width:100%">
                <span class="ios-info-strip-icon"><i class="fas fa-key"></i></span>
                <span class="ios-info-strip-text">
                    A <strong>temporary password</strong> will be auto-generated and emailed to the new admin.
                    They must change it on first login.
                </span>
            </div>

            <!-- Mobile submit -->
            <div class="d-md-none" style="display:flex;flex-direction:column;gap:10px;margin-bottom:var(--spacing-4)">
                <button type="submit" class="btn btn-primary w-100" style="border-radius:12px;padding:14px">
                    <i class="fas fa-user-plus me-2"></i>Create Admin Account
                </button>
                <a href="<?php echo BASE_URL; ?>admin/admins.php" class="btn btn-secondary w-100" style="border-radius:12px;padding:14px">
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
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5>Create Account</h5>
                        </div>
                    </div>
                    <div class="ios-admin-actions">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-user-plus me-2"></i>Create Admin Account
                        </button>
                        <a href="<?php echo BASE_URL; ?>admin/admins.php" class="btn btn-secondary w-100">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </div>

                <!-- Tips card -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue"><i class="fas fa-lightbulb"></i></div>
                        <div class="ios-section-title"><h5>Role Guide</h5></div>
                    </div>
                    <div class="ios-tip-list">
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">🛡️</span>
                            <span class="ios-tip-text"><strong>Admin</strong> can manage day-to-day club operations.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">⭐</span>
                            <span class="ios-tip-text"><strong>Super Admin</strong> has full system access including creating and removing other admins.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">🔑</span>
                            <span class="ios-tip-text">A temporary password is emailed automatically. The admin must reset it on first login.</span>
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
        <h5 class="ios-menu-title">Add Admin Account</h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>admin/admins.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-users-cog"></i></div>
                        <div class="ios-menu-item-label">Admin Accounts</div>
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
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
