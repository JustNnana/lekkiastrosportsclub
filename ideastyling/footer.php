<?php
// Include functions for flash messages
require_once __DIR__ . '/functions.php';
?>
</div> <!-- End of content div -->

<!-- Footer with responsive positioning -->
<footer class="footer bg-white mt-auto py-3 border-top d-none d-md-block">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <span class="text-muted">Copyright &copy; <?php echo date('Y'); ?> Gate Wey. All rights reserved.</span>
            </div>
            <div>
                <a href="<?php echo BASE_URL; ?>terms.php" class="text-muted me-3">Terms of Service</a>
                <a href="<?php echo BASE_URL; ?>privacy.php" class="text-muted me-3">Privacy Policy</a>
                <a href="<?php echo BASE_URL; ?>help/" class="text-muted">Help</a>
            </div>
        </div>
    </div>
</footer>
    
<!-- Bootstrap JS and dependencies -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
<!-- Custom JS -->
<script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    
<script>
    // Improved sidebar toggle with footer adjustment
    function _initSidebarToggle() {
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const sidebar = document.querySelector('.sidebar');
        const content = document.querySelector('.content');
        const footer = document.querySelector('.footer');

        if (!sidebar) return;

        // Function to update footer positioning
        function updateFooterPosition() {
            if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                footer.style.marginLeft = '250px';
                footer.style.width = 'calc(100% - 250px)';
            } else if (window.innerWidth > 768) {
                footer.style.marginLeft = '250px';
                footer.style.width = 'calc(100% - 250px)';
            } else {
                footer.style.marginLeft = '0';
                footer.style.width = '100%';
            }
        }

        // Initial positioning
        updateFooterPosition();

        // Toggle sidebar on button click
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                content.classList.toggle('sidebar-active');
                updateFooterPosition();
            });
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnToggle = sidebarToggle ? sidebarToggle.contains(event.target) : false;

            if (!isClickInsideSidebar && !isClickOnToggle && window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                content.classList.remove('sidebar-active');
                updateFooterPosition();
            }
        });

        // Update on window resize
        window.addEventListener('resize', updateFooterPosition);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _initSidebarToggle);
    } else {
        _initSidebarToggle();
    }
</script>
    
<?php if (isset($pageScript)): ?>
    <script src="<?php echo BASE_URL . $pageScript; ?>"></script>
<?php endif; ?>

<!-- PWA Install Banner (hidden by default) -->
<div id="pwa-install-banner" class="pwa-banner" style="display: none;">
    <div class="pwa-banner-content">
        <div class="pwa-banner-icon">
            <img src="<?php echo BASE_URL; ?>assets/images/icons/icon-192x192.png" alt="Gate Wey">
        </div>
        <div class="pwa-banner-text">
            <p>Add Gate Wey to your home screen</p>
            <p class="pwa-banner-subtitle">For a better experience</p>
        </div>
        <button id="banner-install-btn" class="btn btn-primary btn-sm">Install</button>
        <button id="pwa-dismiss-btn" class="btn btn-light btn-sm">Not Now</button>
    </div>
</div>

<!-- Improved PWA JavaScript -->
<script>
// PWA install variables
let deferredPrompt;
let isAppInstalled = false;
const sidebarInstallBtn = document.getElementById('pwa-install-btn');
const bannerInstallBtn = document.getElementById('banner-install-btn');
const installBanner = document.getElementById('pwa-install-banner');
const dismissBtn = document.getElementById('pwa-dismiss-btn');

// Check if app is already installed
function checkIfAppInstalled() {
    // Check if running in standalone mode (already installed)
    if (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) {
        isAppInstalled = true;
        return true;
    }
    
    // Check for iOS standalone mode
    if (window.navigator.standalone === true) {
        isAppInstalled = true;
        return true;
    }
    
    // Check if app was installed via Chrome
    if (document.referrer.includes('android-app://')) {
        isAppInstalled = true;
        return true;
    }
    
    return false;
}

// Initialize PWA functionality
function initializePWA() {
    // Check if app is already installed
    if (checkIfAppInstalled()) {
        isAppInstalled = true;
        if (sidebarInstallBtn) {
            sidebarInstallBtn.style.display = 'none';
        }
        if (installBanner) {
            installBanner.style.display = 'none';
        }
        return;
    }

    // Show install button initially (will be hidden if not installable)
    if (sidebarInstallBtn) {
        sidebarInstallBtn.style.display = 'flex';
        sidebarInstallBtn.innerHTML = '<i class="fas fa-download sidebar-icon"></i>Install Gate Wey';
    }
}

// Handle beforeinstallprompt event
window.addEventListener('beforeinstallprompt', (e) => {
    console.log('beforeinstallprompt event fired');
    
    // Prevent the mini-infobar from appearing on mobile
    e.preventDefault();
    
    // Stash the event so it can be triggered later
    deferredPrompt = e;
    
    // Show the install button in sidebar
    if (sidebarInstallBtn) {
        sidebarInstallBtn.style.display = 'flex';
        sidebarInstallBtn.innerHTML = '<i class="fas fa-download sidebar-icon"></i>Install Gate Wey';
    }
    
    // Show banner if not recently dismissed
    const lastDismissed = localStorage.getItem('pwa-install-dismissed');
    if (!lastDismissed || (Date.now() - parseInt(lastDismissed)) > 7 * 24 * 60 * 60 * 1000) {
        setTimeout(() => {
            if (installBanner && !isAppInstalled) {
                installBanner.style.display = 'block';
            }
        }, 5000); // Show after 5 seconds
    }
});

// Handle sidebar install button click
if (sidebarInstallBtn) {
    sidebarInstallBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        
        console.log('Install button clicked');
        
        // Check if app is already installed
        if (isAppInstalled) {
            showMessage('Gate Wey is already installed!', 'info');
            return;
        }
        
        // Check if we have a deferred prompt
        if (!deferredPrompt) {
            // Try to detect why installation isn't available
            if (!window.navigator.serviceWorker) {
                showMessage('Service Worker not supported. PWA installation requires a modern browser.', 'warning');
            } else if (window.location.protocol !== 'https:' && window.location.hostname !== 'localhost') {
                showMessage('PWA installation requires HTTPS or localhost.', 'warning');
            } else {
                showMessage('App installation is not available. This might be because:\n\n• App is already installed\n• Browser doesn\'t support PWA installation\n• Installation criteria not met\n\nTry refreshing the page or use Chrome/Edge browser.', 'info');
            }
            return;
        }
        
        try {
            // Show the install prompt
            const result = await deferredPrompt.prompt();
            console.log('Install prompt result:', result);
            
            // Wait for the user to respond to the prompt
            const { outcome } = await deferredPrompt.userChoice;
            console.log('User choice:', outcome);
            
            if (outcome === 'accepted') {
                showMessage('Thanks for installing Gate Wey!', 'success');
            } else {
                showMessage('Installation cancelled.', 'info');
            }
        } catch (error) {
            console.error('Error during installation:', error);
            showMessage('Installation failed. Please try again.', 'error');
        }
        
        // Clear the deferred prompt
        deferredPrompt = null;
        
        // Hide install elements
        if (sidebarInstallBtn) {
            sidebarInstallBtn.style.display = 'none';
        }
        if (installBanner) {
            installBanner.style.display = 'none';
        }
    });
}

// Handle banner install button
if (bannerInstallBtn) {
    bannerInstallBtn.addEventListener('click', async () => {
        if (deferredPrompt) {
            try {
                await deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                
                if (outcome === 'accepted') {
                    showMessage('Thanks for installing Gate Wey!', 'success');
                }
                
                deferredPrompt = null;
                installBanner.style.display = 'none';
                
                if (sidebarInstallBtn) {
                    sidebarInstallBtn.style.display = 'none';
                }
            } catch (error) {
                console.error('Banner install error:', error);
                showMessage('Installation failed. Please try again.', 'error');
            }
        }
    });
}

// Handle dismiss button
if (dismissBtn) {
    dismissBtn.addEventListener('click', () => {
        if (installBanner) {
            installBanner.style.display = 'none';
        }
        localStorage.setItem('pwa-install-dismissed', Date.now().toString());
    });
}

// Handle app installed event
window.addEventListener('appinstalled', (evt) => {
    console.log('Gate Wey app was installed');
    isAppInstalled = true;
    
    // Hide install elements
    if (sidebarInstallBtn) {
        sidebarInstallBtn.style.display = 'none';
    }
    if (installBanner) {
        installBanner.style.display = 'none';
    }
    
    showMessage('Gate Wey has been successfully installed!', 'success');
});

// Function to show messages to user
function showMessage(message, type = 'info') {
    // Check if we have Bootstrap alerts available
    if (typeof bootstrap !== 'undefined') {
        // Create a toast or alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 80px; right: 20px; z-index: 9999; max-width: 400px;';
        alertDiv.innerHTML = `
            ${message.replace(/\n/g, '<br>')}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.parentNode.removeChild(alertDiv);
            }
        }, 5000);
    } else {
        // Fallback to alert
        alert(message);
    }
}

// Check for PWA update
function checkForUpdate() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistration().then(registration => {
            if (registration) {
                registration.addEventListener('updatefound', () => {
                    const newWorker = registration.installing;
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            showMessage('A new version of Gate Wey is available. Refresh to update.', 'info');
                        }
                    });
                });
            }
        });
    }
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializePWA();
    checkForUpdate();
    
    // Debug information
    console.log('PWA Debug Info:');
    console.log('- Protocol:', window.location.protocol);
    console.log('- Host:', window.location.hostname);
    console.log('- Service Worker supported:', 'serviceWorker' in navigator);
    console.log('- Standalone mode:', window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
    console.log('- iOS standalone:', window.navigator.standalone);
});

// Register service worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js')
        .then(registration => {
            console.log('Service Worker registered successfully:', registration);
        })
        .catch(error => {
            console.error('Service Worker registration failed:', error);
        });
}
</script>

<!-- Add CSS for responsive footer -->
<style>
    body {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    .content {
        flex: 1;
        transition: margin-left 0.3s ease;
    }

    .footer {
        transition: margin-left 0.3s ease, width 0.3s ease;
    }

    @media (min-width: 769px) {
        .footer {
            margin-left: 250px;
            width: calc(100% - 250px);
        }
    }

    @media (max-width: 768px) {
        .sidebar.active + .content + .footer {
            margin-left: 250px;
            width: calc(100% - 250px);
        }
    }

    /* Flash Notification Animations */
    @keyframes slideInRight {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }

    /* Flash notification styling */
    .flash-notification {
        position: fixed;
        top: 80px;
        right: 20px;
        min-width: 300px;
        max-width: 400px;
        padding: 12px 20px;
        border-radius: var(--border-radius, 8px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 9999;
        animation: slideInRight 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        line-height: 1.5;
    }

    .flash-notification.success {
        background: var(--success, #10b981);
        color: white;
    }

    .flash-notification.error,
    .flash-notification.danger {
        background: var(--danger, #ef4444);
        color: white;
    }

    .flash-notification.warning {
        background: var(--warning, #f59e0b);
        color: white;
    }

    .flash-notification.info {
        background: var(--info, #3b82f6);
        color: white;
    }

    .flash-notification i {
        font-size: 18px;
        flex-shrink: 0;
    }

    .flash-notification-message {
        flex: 1;
    }

    @media (max-width: 768px) {
        .flash-notification {
            right: 10px;
            left: 10px;
            min-width: auto;
            max-width: none;
            top: 70px;
        }
    }

    /* Hide legacy inline alert boxes when using flash notifications */
    body.flash-notifications-enabled .legacy-alert {
        display: none !important;
    }

    /* Auto-hide ALL Bootstrap alerts when flash notifications are enabled */
    body.flash-notifications-enabled .alert.alert-success,
    body.flash-notifications-enabled .alert.alert-danger,
    body.flash-notifications-enabled .alert.alert-warning,
    body.flash-notifications-enabled .alert.alert-info {
        display: none !important;
    }

    /* PWA Banner Styling */
    .pwa-banner {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background-color: #ffffff;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        padding: 10px 20px;
        z-index: 1050;
    }
    
    .pwa-banner-content {
        display: flex;
        align-items: center;
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .pwa-banner-icon {
        flex: 0 0 40px;
        margin-right: 15px;
    }
    
    .pwa-banner-icon img {
        width: 40px;
        height: 40px;
        border-radius: 8px;
    }
    
    .pwa-banner-text {
        flex: 1;
    }
    
    .pwa-banner-text p {
        margin: 0;
        font-weight: 500;
    }
    
    .pwa-banner-subtitle {
        font-size: 0.8rem;
        color: #6c757d;
    }
    
    #pwa-dismiss-btn {
        margin-left: 10px;
    }
</style>

<!-- Global Flash Notification System -->
<script>
/**
 * Show a flash notification message
 * @param {string} message - The message to display
 * @param {string} type - Type of notification: 'success', 'error', 'warning', 'info'
 * @param {number} duration - Duration in milliseconds (default: 3000)
 */
window.showFlashNotification = function(message, type = 'success', duration = 3000) {
    const notification = document.createElement('div');
    notification.className = `flash-notification ${type}`;

    // Icon mapping
    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        danger: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };

    const icon = icons[type] || icons.info;

    notification.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <div class="flash-notification-message">${message}</div>
    `;

    document.body.appendChild(notification);

    // Auto remove after duration
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 300);
    }, duration);
};

// Alias for compatibility
window.showNotification = window.showFlashNotification;

// Auto-show PHP flash messages on page load
(function() {
    const flashMessages = <?php
        $messages = getFlashMessages();
        echo json_encode($messages);
    ?>;

    const pageMessages = [];

    <?php
    if (isset($error) && !empty($error)) {
        echo "pageMessages.push({ message: " . json_encode($error) . ", type: 'error' });\n";
    }
    if (isset($success) && !empty($success)) {
        echo "pageMessages.push({ message: " . json_encode($success) . ", type: 'success' });\n";
    }
    if (isset($warning) && !empty($warning)) {
        echo "pageMessages.push({ message: " . json_encode($warning) . ", type: 'warning' });\n";
    }
    if (isset($info) && !empty($info)) {
        echo "pageMessages.push({ message: " . json_encode($info) . ", type: 'info' });\n";
    }
    ?>

    function _showFlashMessages() {
        const allMessages = [...flashMessages, ...pageMessages];

        if (allMessages && allMessages.length > 0) {
            document.body.classList.add('flash-notifications-enabled');

            const alertBoxes = document.querySelectorAll('.alert');
            alertBoxes.forEach(alert => {
                const alertText = alert.textContent.trim();
                allMessages.forEach(msg => {
                    if (alertText.includes(msg.message)) {
                        alert.style.display = 'none';
                    }
                });
            });

            allMessages.forEach((msg, index) => {
                setTimeout(() => {
                    showFlashNotification(msg.message, msg.type);
                }, index * 200);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', _showFlashMessages);
    } else {
        _showFlashMessages();
    }
})();
</script>

<?php
// Show push notification reminder if user hasn't enabled it
if (file_exists(__DIR__ . '/push-notification-reminder.php')) {
    include_once __DIR__ . '/push-notification-reminder.php';
}

// Show iOS Safari install instructions (iPhone/iPad only, self-hides on other browsers)
if (file_exists(__DIR__ . '/ios-install-reminder.php')) {
    include_once __DIR__ . '/ios-install-reminder.php';
}
?>

<!-- Session Heartbeat: keeps $_SESSION['last_activity'] fresh while page is visible -->
<script>
(function () {
    'use strict';

    var PING_URL  = '<?php echo BASE_URL; ?>api/ping.php';
    var INTERVAL  = 30000; // ping every 30 s while visible
    var _timer    = null;

    function ping() {
        fetch(PING_URL, { method: 'POST', credentials: 'same-origin' })
            .then(function (r) {
                if (r.status === 401) {
                    // Session expired — redirect to login
                    window.location.href = '<?php echo BASE_URL; ?>';
                }
            })
            .catch(function () { /* network offline — ignore */ });
    }

    function start() {
        if (_timer) return;
        ping(); // immediate ping on resume
        _timer = setInterval(ping, INTERVAL);
    }

    function stop() {
        if (_timer) { clearInterval(_timer); _timer = null; }
    }

    // Start/stop based on page visibility
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            start();
        } else {
            stop();
        }
    });

    // Kick off when page first loads (if visible)
    if (document.visibilityState === 'visible') {
        start();
    }
})();
</script>

</body>
</html>