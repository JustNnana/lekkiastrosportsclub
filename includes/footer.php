<?php
/**
 * Lekki Astro Sports Club
 * Footer component — closes .content and .main-wrapper, loads JS, handles flash messages & PWA.
 */
?>

</div><!-- /content -->
</div><!-- /main-wrapper -->

<!-- Footer bar (desktop only) -->
<footer class="site-footer d-none d-md-flex">
    <span class="text-muted">
        &copy; <?php echo date('Y'); ?> <?php echo e(SITE_NAME); ?>. All rights reserved.
    </span>
    <div class="footer-links">
        <a href="<?php echo BASE_URL; ?>privacy.php">Privacy Policy</a>
        <a href="<?php echo BASE_URL; ?>terms.php">Terms of Use</a>
        <a href="<?php echo BASE_URL; ?>help/">Help</a>
    </div>
</footer>

<!-- PWA install banner -->
<div id="pwa-install-banner" class="pwa-banner">
    <div class="pwa-banner-content">
        <div class="pwa-banner-icon">
            <img src="<?php echo BASE_URL; ?>assets/images/icons/icon-192x192.png" alt="<?php echo e(SITE_ABBR); ?>">
        </div>
        <div class="pwa-banner-text">
            <p><?php echo e(SITE_NAME); ?></p>
            <small>Add to your home screen for a better experience</small>
        </div>
        <button id="pwa-banner-install-btn" class="btn btn-primary btn-sm">Install</button>
        <button id="pwa-banner-dismiss-btn" class="btn btn-secondary btn-sm ms-2">Later</button>
    </div>
</div>

<!-- JS Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

<!-- Dasher Theme System -->
<script>
// Override storage key to 'lasc-theme' before loading the theme system module
window.LASC_THEME_KEY = 'lasc-theme';
</script>
<script src="<?php echo BASE_URL; ?>assets/js/dasher-theme-system.js"></script>

<!-- Page-specific script -->
<?php if (isset($pageScript)): ?>
    <script src="<?php echo BASE_URL . e($pageScript); ?>"></script>
<?php endif; ?>

<!-- ===== THEME TOGGLE WIRING ===== -->
<script>
(function () {
    var KEY    = 'lasc-theme';
    var btn    = document.getElementById('theme-toggle-btn');
    var iconL  = document.getElementById('theme-icon-light');
    var iconD  = document.getElementById('theme-icon-dark');
    var metaTC = document.getElementById('meta-theme-color');

    function applyIcons(theme) {
        if (!iconL || !iconD) return;
        if (theme === 'dark') {
            iconL.style.display = 'none';
            iconD.style.display = '';
        } else {
            iconL.style.display = '';
            iconD.style.display = 'none';
        }
        if (metaTC) metaTC.content = theme === 'dark' ? '#1c252e' : '#00a76f';
    }

    function getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    // Set icons on load
    applyIcons(getCurrentTheme());

    if (btn) {
        btn.addEventListener('click', function () {
            var next = getCurrentTheme() === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            document.documentElement.setAttribute('data-bs-theme', next);
            try { localStorage.setItem(KEY, next); } catch (e) {}
            applyIcons(next);
        });
    }
})();
</script>

<!-- ===== FLASH NOTIFICATION SYSTEM ===== -->
<style>
    .site-footer {
        margin-left: var(--sidebar-width);
        padding: var(--spacing-3) var(--spacing-8);
        border-top: 1px solid var(--border-color);
        background: var(--bg-primary);
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: var(--font-size-xs);
        color: var(--text-muted);
        transition: var(--theme-transition);
    }
    .site-footer .footer-links a {
        color: var(--text-muted);
        text-decoration: none;
        margin-left: var(--spacing-4);
        transition: var(--transition-fast);
    }
    .site-footer .footer-links a:hover { color: var(--primary); }
    @media (max-width: 768px) { .site-footer { margin-left: 0; } }
</style>

<script>
window.showFlashNotification = function (message, type, duration) {
    duration = duration || 4000;
    var icons = { success: 'check-circle', error: 'exclamation-circle', warning: 'exclamation-triangle', info: 'info-circle' };
    var el = document.createElement('div');
    el.className = 'flash-notification ' + (type || 'info');
    el.innerHTML = '<i class="fas fa-' + (icons[type] || icons.info) + '"></i>'
                 + '<div>' + message + '</div>';
    document.body.appendChild(el);
    setTimeout(function () {
        el.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(function () { el.parentNode && el.remove(); }, 300);
    }, duration);
};

// Show PHP-generated flash messages
(function () {
    var messages = <?php echo json_encode(getFlashMessages()); ?>;
    messages.forEach(function (m, i) {
        setTimeout(function () { showFlashNotification(m.message, m.type); }, i * 250);
    });
})();
</script>

<!-- ===== PWA LOGIC ===== -->
<script>
(function () {
    var deferred = null;
    var isInstalled = window.matchMedia('(display-mode: standalone)').matches
                   || window.navigator.standalone === true;

    var sidebarBtn = document.getElementById('pwa-install-btn');
    var banner     = document.getElementById('pwa-install-banner');
    var bannerBtn  = document.getElementById('pwa-banner-install-btn');
    var dismissBtn = document.getElementById('pwa-banner-dismiss-btn');

    if (isInstalled) { return; } // Already installed — do nothing

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        if (sidebarBtn) sidebarBtn.style.display = 'flex';
        var dismissed = localStorage.getItem('pwa-dismissed');
        if (!dismissed || (Date.now() - parseInt(dismissed)) > 7 * 86400000) {
            setTimeout(function () { if (banner) banner.style.display = 'block'; }, 6000);
        }
    });

    function install() {
        if (!deferred) return;
        deferred.prompt();
        deferred.userChoice.then(function (result) {
            if (result.outcome === 'accepted') {
                showFlashNotification('<?php echo e(SITE_NAME); ?> installed successfully!', 'success');
            }
            deferred = null;
            if (sidebarBtn) sidebarBtn.style.display = 'none';
            if (banner)     banner.style.display = 'none';
        });
    }

    if (sidebarBtn) sidebarBtn.addEventListener('click', install);
    if (bannerBtn)  bannerBtn.addEventListener('click', install);
    if (dismissBtn) dismissBtn.addEventListener('click', function () {
        if (banner) banner.style.display = 'none';
        localStorage.setItem('pwa-dismissed', Date.now().toString());
    });

    window.addEventListener('appinstalled', function () {
        if (sidebarBtn) sidebarBtn.style.display = 'none';
        if (banner)     banner.style.display = 'none';
    });
})();

// Session heartbeat — keeps session alive while page is open
(function () {
    var PING = '<?php echo BASE_URL; ?>api/ping.php';
    var t = null;
    function ping() {
        fetch(PING, { method: 'POST', credentials: 'same-origin' })
            .then(function (r) { if (r.status === 401) window.location.href = '<?php echo BASE_URL; ?>'; })
            .catch(function () {});
    }
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            ping(); t = setInterval(ping, 30000);
        } else {
            clearInterval(t); t = null;
        }
    });
    if (document.visibilityState === 'visible') { ping(); t = setInterval(ping, 30000); }
})();

// Register service worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?php echo BASE_URL; ?>sw.js')
        .catch(function (e) { console.warn('SW registration failed:', e); });
}
</script>

</body>
</html>
