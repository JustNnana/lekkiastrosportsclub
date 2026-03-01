<?php
/**
 * Change Password — handles both first-login forced change and voluntary change
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireLogin();

$isFirstLogin = isset($_GET['first']);
$pageTitle    = $isFirstLogin ? 'Set Your Password' : 'Change Password';
$error        = '';
$success      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $currentPw  = $_POST['current_password'] ?? '';
    $newPw      = $_POST['new_password']      ?? '';
    $confirmPw  = $_POST['confirm_password']  ?? '';

    // Validate
    if (!$isFirstLogin && empty($currentPw)) {
        $error = 'Please enter your current password.';
    } elseif (strlen($newPw) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $newPw)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $newPw)) {
        $error = 'Password must contain at least one number.';
    } elseif ($newPw !== $confirmPw) {
        $error = 'Passwords do not match.';
    } else {
        $db  = Database::getInstance();
        $row = $db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$_SESSION['user_id']]);

        // Verify current password (skip for first-login)
        if (!$isFirstLogin && !password_verify($currentPw, $row['password_hash'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->execute(
                "UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?",
                [$hash, $_SESSION['user_id']]
            );

            flashSuccess('Password updated successfully.');
            redirect('dashboard/');
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <h1 class="content-title"><?php echo e($pageTitle); ?></h1>
    <p class="content-subtitle">
        <?php echo $isFirstLogin
            ? 'Welcome! Please set a permanent password before continuing.'
            : 'Update your account password.'; ?>
    </p>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <?php if ($isFirstLogin): ?>
        <div class="alert alert-warning mb-4">
            <i class="fas fa-exclamation-triangle me-2"></i>
            You must set a new password before accessing the system.
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h6 class="card-title"><i class="fas fa-lock me-2 text-primary"></i><?php echo e($pageTitle); ?></h6>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" id="change-pw-form" novalidate>
                    <?php echo csrfField(); ?>

                    <?php if (!$isFirstLogin): ?>
                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" class="form-control" id="current_password"
                                   name="current_password" placeholder="Enter current password" required>
                            <button type="button" class="toggle-pw-btn" data-target="current_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="form-group mt-4">
                        <label class="form-label" for="new_password">New Password</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-key input-icon"></i>
                            <input type="password" class="form-control" id="new_password"
                                   name="new_password" placeholder="Min 8 chars, 1 uppercase, 1 number"
                                   oninput="checkStrength(this.value)" required>
                            <button type="button" class="toggle-pw-btn" data-target="new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="pw-strength mt-2" id="pw-strength" style="display:none">
                            <div class="pw-strength-bar"><div class="pw-strength-fill" id="pw-fill"></div></div>
                            <small id="pw-label" class="text-muted"></small>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-key input-icon"></i>
                            <input type="password" class="form-control" id="confirm_password"
                                   name="confirm_password" placeholder="Repeat new password" required>
                            <button type="button" class="toggle-pw-btn" data-target="confirm_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="password-rules mt-3 mb-4">
                        <p class="text-muted mb-2" style="font-size:var(--font-size-xs)">Password must have:</p>
                        <ul class="rule-list">
                            <li id="rule-len"   class="rule"><i class="fas fa-circle"></i> At least 8 characters</li>
                            <li id="rule-upper" class="rule"><i class="fas fa-circle"></i> One uppercase letter</li>
                            <li id="rule-num"   class="rule"><i class="fas fa-circle"></i> One number</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="submit-btn">
                        <i class="fas fa-save me-2"></i>
                        <?php echo $isFirstLogin ? 'Set Password & Continue' : 'Update Password'; ?>
                    </button>

                    <?php if (!$isFirstLogin): ?>
                    <a href="<?php echo BASE_URL; ?>dashboard/" class="btn btn-secondary w-100 mt-2">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.input-icon-wrap { position: relative; }
.input-icon-wrap .input-icon {
    position: absolute; top: 50%; left: 14px;
    transform: translateY(-50%);
    color: var(--text-muted); font-size: var(--font-size-sm); pointer-events: none;
}
.input-icon-wrap .form-control { padding-left: 2.5rem; padding-right: 2.5rem; }
.toggle-pw-btn {
    position: absolute; top: 50%; right: 12px;
    transform: translateY(-50%);
    background: none; border: none;
    color: var(--text-muted); cursor: pointer; padding: 4px;
}
.toggle-pw-btn:hover { color: var(--primary); }

.pw-strength-bar {
    height: 4px; background: var(--bg-tertiary);
    border-radius: 2px; overflow: hidden; margin-bottom: 4px;
}
.pw-strength-fill { height: 100%; border-radius: 2px; transition: all 0.3s; }

.rule-list { list-style: none; padding: 0; margin: 0; }
.rule { font-size: var(--font-size-xs); color: var(--text-muted); padding: 2px 0; display: flex; align-items: center; gap: 6px; }
.rule i { font-size: 6px; }
.rule.pass { color: var(--success); }
.rule.pass i::before { content: '\f058'; font-size: 12px; }
</style>

<script>
document.querySelectorAll('.toggle-pw-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var input = document.getElementById(this.dataset.target);
        var icon  = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    });
});

function checkStrength(val) {
    var wrap   = document.getElementById('pw-strength');
    var fill   = document.getElementById('pw-fill');
    var label  = document.getElementById('pw-label');
    var rLen   = document.getElementById('rule-len');
    var rUpper = document.getElementById('rule-upper');
    var rNum   = document.getElementById('rule-num');

    wrap.style.display = val ? 'block' : 'none';

    var hasLen   = val.length >= 8;
    var hasUpper = /[A-Z]/.test(val);
    var hasNum   = /[0-9]/.test(val);

    rLen.className   = 'rule' + (hasLen   ? ' pass' : '');
    rUpper.className = 'rule' + (hasUpper ? ' pass' : '');
    rNum.className   = 'rule' + (hasNum   ? ' pass' : '');

    var score = [hasLen, hasUpper, hasNum, val.length >= 12, /[^a-zA-Z0-9]/.test(val)].filter(Boolean).length;
    var colors = ['#ff5630','#ffab00','#ffab00','#00a76f','#00a76f'];
    var labels = ['Very Weak','Weak','Fair','Strong','Very Strong'];

    fill.style.width      = (score * 20) + '%';
    fill.style.background = colors[score - 1] || '#ff5630';
    label.textContent     = labels[score - 1] || '';
    label.style.color     = colors[score - 1] || '#ff5630';
}

document.getElementById('change-pw-form').addEventListener('submit', function() {
    var btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
