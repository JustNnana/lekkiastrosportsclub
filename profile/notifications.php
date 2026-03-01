<?php
/**
 * Profile — Notification Settings (push toggle)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/PushService.php';

requireLogin();

$pageTitle = 'Notification Settings';
$userId    = (int)$_SESSION['user_id'];
$push      = new PushService();
$isSubscribed = $push->isSubscribed($userId);
$vapidConfigured = VAPID_PUBLIC_KEY !== '';

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <nav class="mb-1"><a href="<?php echo BASE_URL; ?>profile/" class="text-muted small">← My Profile</a></nav>
        <h1 class="content-title">Notification Settings</h1>
        <p class="content-subtitle">Manage how you receive notifications from <?php echo e(SITE_NAME); ?>.</p>
    </div>
</div>

<div class="row g-4">

    <!-- Push Notifications Card -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="fas fa-bell me-2 text-primary"></i>Push Notifications</h6>
            </div>
            <div class="card-body">

                <?php if (!$vapidConfigured): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Push notifications are not configured yet. An administrator needs to set VAPID keys.
                </div>
                <?php else: ?>

                <p class="text-muted small mb-4">
                    Receive real-time notifications for payments, announcements, events, and more —
                    even when the app is not open.
                </p>

                <!-- Toggle row -->
                <div class="d-flex align-items-center justify-content-between p-3 rounded mb-3"
                     style="background:var(--bg-secondary);border:1px solid var(--border-color)">
                    <div>
                        <div class="fw-semibold mb-1">Browser Push Notifications</div>
                        <div class="text-muted small" id="push-status-text">
                            <?php if ($isSubscribed): ?>
                            <span class="text-success"><i class="fas fa-check-circle me-1"></i>Enabled on this device</span>
                            <?php else: ?>
                            <span class="text-muted"><i class="fas fa-times-circle me-1"></i>Disabled</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-check form-switch ms-3" style="transform:scale(1.4);transform-origin:right center">
                        <input class="form-check-input" type="checkbox" id="push-notification-toggle"
                               data-user-id="<?php echo $userId; ?>"
                               <?php echo $isSubscribed ? 'checked' : ''; ?>>
                    </div>
                </div>

                <!-- Status feedback -->
                <div id="notification-status" class="p-3 rounded small" style="display:none;border:1px solid transparent"></div>

                <!-- Test button -->
                <button id="test-push-notification" class="btn btn-secondary btn-sm mt-3"
                        <?php echo !$isSubscribed ? 'disabled' : ''; ?>>
                    <i class="fas fa-paper-plane me-1"></i>Send Test Notification
                </button>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- In-App Notifications Card -->
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="fas fa-inbox me-2 text-primary"></i>In-App Notifications</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    In-app notifications always appear in your notification centre (bell icon in the navbar).
                    They cannot be disabled.
                </p>
                <a href="<?php echo BASE_URL; ?>notifications/" class="btn btn-secondary btn-sm">
                    <i class="fas fa-bell me-1"></i>View All Notifications
                </a>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="fas fa-info-circle me-2 text-muted"></i>How Push Works</h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 small text-muted">
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Works on Chrome, Firefox, Edge and Android</li>
                    <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Notifications arrive even when the tab is closed</li>
                    <li class="mb-2"><i class="fas fa-exclamation text-warning me-2"></i>iOS Safari requires iOS 16.4+ and "Add to Home Screen"</li>
                    <li class="mb-2"><i class="fas fa-info text-info me-2"></i>Each device/browser subscribes separately</li>
                    <li><i class="fas fa-lock text-muted me-2"></i>Requires HTTPS on live servers</li>
                </ul>
            </div>
        </div>
    </div>

</div>

<script>
// Test push button
(function () {
    var testBtn = document.getElementById('test-push-notification');
    if (!testBtn) return;

    testBtn.addEventListener('click', async function () {
        testBtn.disabled = true;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending…';

        try {
            var res = await fetch('<?php echo BASE_URL; ?>api/send-test-push.php', {
                method: 'POST', credentials: 'same-origin'
            });
            var data = await res.json();
            showFlashNotification(data.message, data.success ? 'success' : 'error');
        } catch (e) {
            showFlashNotification('Request failed: ' + e.message, 'error');
        } finally {
            testBtn.disabled = false;
            testBtn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send Test Notification';
        }
    });
})();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
