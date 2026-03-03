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

<!-- Dasher Theme System + global config -->
<script>
window.LASC_THEME_KEY = 'lasc-theme';
window.LASC_BASE_URL  = '<?php echo BASE_URL; ?>';
</script>
<script src="<?php echo BASE_URL; ?>assets/js/dasher-theme-system.js"></script>

<!-- Push Notifications -->
<?php if (defined('VAPID_PUBLIC_KEY') && VAPID_PUBLIC_KEY !== ''): ?>
<script src="<?php echo BASE_URL; ?>assets/js/push-notifications.js"></script>
<?php endif; ?>

<!-- Page-specific script -->
<?php if (isset($pageScript)): ?>
    <script src="<?php echo BASE_URL . e($pageScript); ?>"></script>
<?php endif; ?>

<!-- ===== THEME TOGGLE WIRING ===== -->
<script>
(function () {
    var KEY    = 'lasc-theme';
    var metaTC = document.getElementById('meta-theme-color');

    function getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    function applyIcons(theme) {
        document.querySelectorAll('.theme-toggle-trigger i').forEach(function (icon) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });
        if (metaTC) metaTC.content = theme === 'dark' ? '#1c252e' : '#00a76f';
    }

    applyIcons(getCurrentTheme());

    document.querySelectorAll('.theme-toggle-trigger').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var next = getCurrentTheme() === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            document.documentElement.setAttribute('data-bs-theme', next);
            try { localStorage.setItem(KEY, next); } catch (e) {}
            applyIcons(next);
        });
    });
})();
</script>

<!-- ===== DESKTOP USER DROPDOWN ===== -->
<script>
(function () {
    var dropdown = document.getElementById('desktopUserDropdown');
    var btn      = document.getElementById('desktopDropdownBtn');
    var menu     = document.getElementById('desktopDropdownMenu');
    if (!dropdown || !btn || !menu) return;

    btn.addEventListener('click', function (e) {
        e.preventDefault(); e.stopPropagation();
        dropdown.classList.toggle('open');
        btn.setAttribute('aria-expanded', dropdown.classList.contains('open'));
    });
    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target)) {
            dropdown.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && dropdown.classList.contains('open')) {
            dropdown.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth < 992) {
            dropdown.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>

<!-- ===== iOS HEADER MENU ===== -->
<script>
(function () {
    var menuBtn        = document.getElementById('iosHeaderMenuBtn');
    var menuBtnDesktop = document.getElementById('iosHeaderMenuBtnDesktop');
    var backdrop       = document.getElementById('iosHeaderMenuBackdrop');
    var modal          = document.getElementById('iosHeaderMenuModal');
    if (!backdrop || !modal) return;

    var startY = 0, currentY = 0, isDragging = false;

    function openMenu() {
        backdrop.classList.add('active');
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeMenu() {
        backdrop.classList.remove('active');
        modal.classList.remove('active');
        modal.style.transform = '';
        backdrop.style.opacity = '';
        document.body.style.overflow = '';
    }

    if (menuBtn)        menuBtn.addEventListener('click',        function (e) { e.preventDefault(); e.stopPropagation(); openMenu(); });
    if (menuBtnDesktop) menuBtnDesktop.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); openMenu(); });
    backdrop.addEventListener('click', closeMenu);

    // Swipe-to-close
    modal.addEventListener('touchstart', function (e) { startY = e.touches[0].clientY; isDragging = true; modal.style.transition = 'none'; }, { passive: true });
    modal.addEventListener('touchmove', function (e) {
        if (!isDragging) return;
        currentY = e.touches[0].clientY;
        var diff = currentY - startY;
        if (diff > 0) { modal.style.transform = 'translateY(' + diff + 'px)'; backdrop.style.opacity = Math.max(0, 1 - diff / 300); }
    }, { passive: true });
    modal.addEventListener('touchend', function () {
        if (!isDragging) return;
        isDragging = false;
        modal.style.transition = 'transform 0.3s cubic-bezier(0.32, 0.72, 0, 1)';
        backdrop.style.transition = 'opacity 0.3s ease';
        if ((currentY - startY) > 100) { closeMenu(); } else { modal.style.transform = 'translateY(0)'; backdrop.style.opacity = '1'; }
        startY = 0; currentY = 0;
    }, { passive: true });

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenu(); });
    window.addEventListener('resize', function () { if (window.innerWidth >= 992) closeMenu(); });

    window.iosHeaderMenu = { open: openMenu, close: closeMenu };
})();
</script>

<!-- ===== FLASH NOTIFICATION SYSTEM ===== -->
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

<?php
// Show install banner only on the first page after login.
// $_SESSION is destroyed on logout, so the flag resets automatically.
$_pwaBannerAllowed = isset($_SESSION['user_id']) && empty($_SESSION['pwa_banner_shown']);
if ($_pwaBannerAllowed) { $_SESSION['pwa_banner_shown'] = true; }
?>
<!-- ===== PWA LOGIC ===== -->
<script>
(function () {
    var deferred      = null;
    var isInstalled   = window.matchMedia('(display-mode: standalone)').matches
                      || window.navigator.standalone === true;
    var bannerAllowed = <?php echo $_pwaBannerAllowed ? 'true' : 'false'; ?>;

    var sidebarBtn = document.getElementById('pwa-install-btn');
    var banner     = document.getElementById('pwa-install-banner');
    var bannerBtn  = document.getElementById('pwa-banner-install-btn');
    var dismissBtn = document.getElementById('pwa-banner-dismiss-btn');

    if (isInstalled) { return; }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        if (sidebarBtn) sidebarBtn.style.display = 'flex';
        if (bannerAllowed) {
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
