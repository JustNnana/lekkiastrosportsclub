<?php
require_once __DIR__ . '/app/config.php';

if (isLoggedIn()) redirect('dashboard/');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email = sanitize($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $db  = Database::getInstance();
        $user = $db->fetchOne(
            "SELECT id, full_name FROM users WHERE email = ? AND status = 'active'",
            [$email]
        );

        // Always show success (prevent email enumeration)
        $success = 'If that email exists in our system, a reset link has been sent.';

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            // Store token
            $db->execute(
                "INSERT INTO password_resets (user_id, token, expires_at, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at), created_at = NOW()",
                [$user['id'], hash('sha256', $token), $expires]
            );

            $resetLink = BASE_URL . 'reset-password.php?token=' . $token;

            // Send email
            require_once __DIR__ . '/app/mail/emails.php';
            sendPasswordResetEmail($email, $user['full_name'], $resetLink);
        }
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
    <title>Forgot Password — <?php echo e(SITE_NAME); ?></title>
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
        .input-icon-wrap .form-control { padding-left:2.5rem; }
        @media(max-width:480px){ .auth-card{ padding:var(--spacing-8) var(--spacing-5); } }
    </style>
</head>
<body>
<div class="auth-card">
    <div class="auth-logo">
        <div class="auth-icon"><i class="fas fa-key"></i></div>
        <h1 class="auth-title">Forgot Password?</h1>
        <p class="auth-subtitle">Enter your email and we'll send a reset link.</p>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success mb-4">
        <i class="fas fa-check-circle me-2"></i><?php echo e($success); ?>
    </div>
    <a href="<?php echo BASE_URL; ?>" class="btn btn-primary w-100">Back to Login</a>
    <?php else: ?>

    <?php if ($error): ?>
    <div class="alert alert-danger mb-4">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <?php echo csrfField(); ?>
        <div class="form-group">
            <label class="form-label" for="email">Email Address</label>
            <div class="input-icon-wrap">
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" class="form-control" id="email" name="email"
                       placeholder="your@email.com"
                       value="<?php echo e($_POST['email'] ?? ''); ?>"
                       autocomplete="email" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary w-100 mt-5">
            <i class="fas fa-paper-plane me-2"></i> Send Reset Link
        </button>
        <a href="<?php echo BASE_URL; ?>" class="btn btn-secondary w-100 mt-2">
            <i class="fas fa-arrow-left me-2"></i> Back to Login
        </a>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
