<?php
/**
 * Lekki Astro Sports Club
 * Login Page (public entry point)
 */

require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

// Already logged in — go to dashboard
if (isLoggedIn()) {
    redirect('dashboard/');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $identifier = sanitize($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter your email/member ID and password.';
    } else {
        $user = new User();
        if ($user->authenticate($identifier, $password)) {
            loginUser($user->toArray());

            // First-time login — force password change
            if ($user->getMustChangePassword()) {
                redirect('profile/change-password.php?first=1');
            }

            redirect('dashboard/');
        } else {
            $error = 'Invalid credentials. Please try again.';
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

    <!-- Instant theme apply (no flash) -->
    <script>
    (function () {
        var KEY = 'lasc-theme';
        var saved; try { saved = localStorage.getItem(KEY); } catch(e){}
        var theme = (saved === 'dark' || saved === 'light')
            ? saved
            : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-bs-theme', theme);
        var m = document.querySelector('#meta-theme-color');
        if (m) m.content = theme === 'dark' ? '#1c252e' : '#00a76f';
    })();
    </script>

    <title>Login — <?php echo e(SITE_NAME); ?></title>

    <!-- PWA / iOS -->
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?php echo e(SITE_ABBR); ?>">
    <meta name="format-detection" content="telephone=no">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icons/icon-180x180.png">
    <link rel="shortcut icon"    href="<?php echo BASE_URL; ?>assets/images/icons/icon-57x57.png">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-variables.css?v=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dasher-core-styles.css?v=1.0">

    <style>
        html, body {
            height: 100%;
            margin: 0;
        }

        body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-secondary);
            font-family: var(--font-family-base);
            padding: var(--spacing-4);
        }

        /* ===== LOGIN CARD ===== */
        .login-card {
            width: 100%;
            max-width: 420px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-xl);
            padding: var(--spacing-10) var(--spacing-8);
            transition: var(--theme-transition);
        }

        /* Logo / branding */
        .login-logo {
            text-align: center;
            margin-bottom: var(--spacing-8);
        }
        .login-logo img {
            height: 72px;
            width: auto;
            margin-bottom: var(--spacing-4);
        }
        .login-logo-placeholder {
            width: 72px; height: 72px;
            border-radius: var(--border-radius-full);
            background: linear-gradient(135deg, var(--primary), var(--primary-700));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem;
            font-weight: var(--font-weight-bold);
            margin: 0 auto var(--spacing-4);
        }
        .login-title {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            margin: 0 0 var(--spacing-1);
        }
        .login-subtitle {
            font-size: var(--font-size-sm);
            color: var(--text-muted);
            margin: 0;
        }

        /* Form */
        .login-form { margin-top: var(--spacing-6); }

        .input-icon-wrap {
            position: relative;
        }
        .input-icon-wrap .input-icon {
            position: absolute;
            top: 50%; left: 14px;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: var(--font-size-sm);
            pointer-events: none;
        }
        .input-icon-wrap .form-control {
            padding-left: 2.5rem;
        }
        .input-icon-wrap .toggle-password {
            position: absolute;
            top: 50%; right: 12px;
            transform: translateY(-50%);
            background: none; border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: var(--font-size-sm);
            padding: 4px;
        }
        .input-icon-wrap .toggle-password:hover { color: var(--primary); }

        /* Error alert */
        .login-error {
            background: var(--danger-light);
            border: 1px solid var(--danger-200);
            color: var(--danger-700);
            border-radius: var(--border-radius);
            padding: var(--spacing-3) var(--spacing-4);
            font-size: var(--font-size-sm);
            display: flex; align-items: center; gap: var(--spacing-3);
            margin-bottom: var(--spacing-5);
        }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: var(--spacing-4);
            font-size: var(--font-size-base);
            font-weight: var(--font-weight-semibold);
            border-radius: var(--border-radius);
            background: var(--primary);
            color: #fff;
            border: none;
            cursor: pointer;
            transition: var(--transition-fast);
            min-height: 50px;
        }
        .btn-login:hover   { background: var(--primary-600); }
        .btn-login:active  { transform: scale(0.98); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; }

        /* Footer links */
        .login-footer {
            text-align: center;
            margin-top: var(--spacing-6);
            font-size: var(--font-size-xs);
            color: var(--text-muted);
        }

        /* Theme toggle on login page */
        .login-theme-toggle {
            position: fixed;
            top: var(--spacing-4); right: var(--spacing-4);
            width: 40px; height: 40px;
            display: flex; align-items: center; justify-content: center;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-full);
            color: var(--text-primary);
            cursor: pointer;
            font-size: var(--font-size-base);
            transition: var(--transition-fast);
            box-shadow: var(--shadow-xs);
        }
        .login-theme-toggle:hover { border-color: var(--primary); color: var(--primary); }

        /* iOS safe area */
        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            body { padding-bottom: calc(var(--spacing-4) + env(safe-area-inset-bottom)); }
        }

        @media (max-width: 480px) {
            .login-card { padding: var(--spacing-8) var(--spacing-5); border-radius: var(--border-radius-lg); }
        }
    </style>
</head>
<body>

<!-- Theme toggle -->
<button class="login-theme-toggle" id="theme-toggle" title="Toggle dark mode" aria-label="Toggle dark mode">
    <i class="fas fa-sun" id="icon-light"></i>
    <i class="fas fa-moon" id="icon-dark" style="display:none"></i>
</button>

<!-- Login card -->
<div class="login-card">
    <div class="login-logo">
        <img src="<?php echo BASE_URL; ?>assets/images/icons/logo.png"
             alt="<?php echo e(SITE_NAME); ?>"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
        <div class="login-logo-placeholder" style="display:none">LA</div>
        <h1 class="login-title"><?php echo e(SITE_NAME); ?></h1>
        <p class="login-subtitle">Member Portal — Sign in to continue</p>
    </div>

    <?php if ($error): ?>
    <div class="login-error">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo e($error); ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" action="" class="login-form" id="login-form" novalidate>
        <?php echo csrfField(); ?>

        <div class="form-group">
            <label class="form-label" for="identifier">Email address or Member ID</label>
            <div class="input-icon-wrap">
                <i class="fas fa-user input-icon"></i>
                <input type="text"
                       class="form-control"
                       id="identifier"
                       name="identifier"
                       placeholder="your@email.com or SC/2026/000001"
                       value="<?php echo e($_POST['identifier'] ?? ''); ?>"
                       autocomplete="username"
                       autocapitalize="none"
                       required>
            </div>
        </div>

        <div class="form-group mt-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label mb-0" for="password">Password</label>
                <a href="<?php echo BASE_URL; ?>forgot-password.php" class="text-sm" style="font-size:var(--font-size-xs)">
                    Forgot password?
                </a>
            </div>
            <div class="input-icon-wrap">
                <i class="fas fa-lock input-icon"></i>
                <input type="password"
                       class="form-control"
                       id="password"
                       name="password"
                       placeholder="Enter your password"
                       autocomplete="current-password"
                       required>
                <button type="button" class="toggle-password" id="toggle-pw" aria-label="Show/hide password">
                    <i class="fas fa-eye" id="pw-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login mt-5" id="login-btn">
            <i class="fas fa-sign-in-alt me-2"></i> Sign In
        </button>
    </form>

    <div class="login-footer">
        &copy; <?php echo date('Y'); ?> <?php echo e(SITE_NAME); ?>. All rights reserved.
    </div>
</div>

<script>
// Theme toggle
(function () {
    var KEY  = 'lasc-theme';
    var btn  = document.getElementById('theme-toggle');
    var icoL = document.getElementById('icon-light');
    var icoD = document.getElementById('icon-dark');
    var meta = document.getElementById('meta-theme-color');

    function current() { return document.documentElement.getAttribute('data-theme') || 'light'; }
    function syncIcons(t) {
        icoL.style.display = t === 'dark' ? 'none' : '';
        icoD.style.display = t === 'dark' ? '' : 'none';
        if (meta) meta.content = t === 'dark' ? '#1c252e' : '#00a76f';
    }

    syncIcons(current());

    btn.addEventListener('click', function () {
        var next = current() === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        document.documentElement.setAttribute('data-bs-theme', next);
        try { localStorage.setItem(KEY, next); } catch(e) {}
        syncIcons(next);
    });
})();

// Password toggle
document.getElementById('toggle-pw').addEventListener('click', function () {
    var pw  = document.getElementById('password');
    var eye = document.getElementById('pw-eye');
    var isText = pw.type === 'text';
    pw.type = isText ? 'password' : 'text';
    eye.className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
});

// Prevent double submit
document.getElementById('login-form').addEventListener('submit', function () {
    var btn = document.getElementById('login-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Signing in...';
});
</script>

</body>
</html>
