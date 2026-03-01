<?php
require_once __DIR__ . '/app/config.php';

if (isLoggedIn()) redirect('dashboard/');

$token   = sanitize($_GET['token'] ?? '');
$error   = '';
$success = '';
$valid   = false;
$userId  = null;
$userName = '';

if (empty($token)) redirect('');

$db        = Database::getInstance();
$tokenHash = hash('sha256', $token);
$row       = $db->fetchOne(
    "SELECT pr.user_id, u.full_name FROM password_resets pr
     JOIN users u ON u.id = pr.user_id
     WHERE pr.token = ? AND pr.expires_at > NOW()",
    [$tokenHash]
);

if ($row) {
    $valid    = true;
    $userId   = $row['user_id'];
    $userName = $row['full_name'];
} else {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    verifyCsrf();

    $newPw     = $_POST['new_password']     ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (strlen($newPw) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $newPw)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $newPw)) {
        $error = 'Password must contain at least one number.';
    } elseif ($newPw !== $confirmPw) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->execute(
            "UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?",
            [$hash, $userId]
        );
        $db->execute("DELETE FROM password_resets WHERE user_id = ?", [$userId]);
        $success = 'Password reset successfully. You can now log in.';
        $valid   = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#00a76f" id="meta-theme-color">
    <script>
    (function(){var KEY='lasc-theme',s;try{s=localStorage.getItem(KEY);}catch(e){}
    var t=(s==='dark'||s==='light')?s:(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
    document.documentElement.setAttribute('data-theme',t);document.documentElement.setAttribute('data-bs-theme',t);
    var m=document.querySelector('#meta-theme-color');if(m)m.content=t==='dark'?'#1c252e':'#00a76f';})();
    </script>
    <title>Reset Password — <?php echo e(SITE_NAME); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-variables.css?v=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-core-styles.css?v=1.0">
    <style>
        body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:var(--bg-secondary); padding:var(--spacing-4); }
        .auth-card { width:100%; max-width:420px; background:var(--bg-card); border:1px solid var(--border-color); border-radius:var(--border-radius-xl); box-shadow:var(--shadow-xl); padding:var(--spacing-10) var(--spacing-8); }
        .auth-logo { text-align:center; margin-bottom:var(--spacing-8); }
        .auth-icon { width:64px; height:64px; border-radius:var(--border-radius-full); background:var(--primary-light); color:var(--primary); display:flex; align-items:center; justify-content:center; font-size:1.75rem; margin:0 auto var(--spacing-4); }
        .auth-title { font-size:var(--font-size-2xl); font-weight:var(--font-weight-bold); color:var(--text-primary); margin:0 0 var(--spacing-1); }
        .auth-subtitle { font-size:var(--font-size-sm); color:var(--text-muted); margin:0; }
        .input-icon-wrap { position:relative; }
        .input-icon-wrap .input-icon { position:absolute; top:50%; left:14px; transform:translateY(-50%); color:var(--text-muted); font-size:var(--font-size-sm); pointer-events:none; }
        .input-icon-wrap .form-control { padding-left:2.5rem; padding-right:2.5rem; }
        .toggle-pw-btn { position:absolute; top:50%; right:12px; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; padding:4px; }
        .toggle-pw-btn:hover { color:var(--primary); }
        @media(max-width:480px){ .auth-card{ padding:var(--spacing-8) var(--spacing-5); } }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo">
        <div class="auth-icon"><i class="fas fa-shield-alt"></i></div>
        <h1 class="auth-title">Reset Password</h1>
        <p class="auth-subtitle"><?php echo $valid ? 'Hi ' . e($userName) . ', set your new password below.' : ''; ?></p>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success mb-4">
        <i class="fas fa-check-circle me-2"></i><?php echo e($success); ?>
    </div>
    <a href="<?php echo BASE_URL; ?>" class="btn btn-primary w-100">Sign In Now</a>

    <?php elseif (!$valid): ?>
    <div class="alert alert-danger mb-4">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
    </div>
    <a href="<?php echo BASE_URL; ?>forgot-password.php" class="btn btn-primary w-100">Request New Link</a>
    <a href="<?php echo BASE_URL; ?>" class="btn btn-secondary w-100 mt-2">Back to Login</a>

    <?php else: ?>

    <?php if ($error): ?>
    <div class="alert alert-danger mb-4">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <?php echo csrfField(); ?>
        <div class="form-group">
            <label class="form-label" for="new_password">New Password</label>
            <div class="input-icon-wrap">
                <i class="fas fa-key input-icon"></i>
                <input type="password" class="form-control" id="new_password" name="new_password"
                       placeholder="Min 8 chars, 1 uppercase, 1 number" required>
                <button type="button" class="toggle-pw-btn" onclick="togglePw('new_password', this)">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <div class="form-group mt-4">
            <label class="form-label" for="confirm_password">Confirm Password</label>
            <div class="input-icon-wrap">
                <i class="fas fa-key input-icon"></i>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                       placeholder="Repeat new password" required>
                <button type="button" class="toggle-pw-btn" onclick="togglePw('confirm_password', this)">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-5">
            <i class="fas fa-save me-2"></i> Reset Password
        </button>
    </form>
    <script>
    function togglePw(id, btn) {
        var inp = document.getElementById(id);
        var ico = btn.querySelector('i');
        inp.type = inp.type === 'password' ? 'text' : 'password';
        ico.className = inp.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
    }
    </script>
    <?php endif; ?>
</div>
</body>
</html>
