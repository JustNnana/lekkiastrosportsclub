<?php
/**
 * PushService — Web Push notification delivery
 *
 * Wraps minishlink/web-push for VAPID-authenticated push notifications.
 * Requires: composer require minishlink/web-push
 *
 * Setup:
 *   1. Run: php setup/generate-vapid-keys.php
 *   2. Add VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY to your .env file
 *   3. Run: php database/push_subscriptions.sql in your DB
 */

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

class PushService
{
    private Database $db;
    private ?WebPush $webPush = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->initWebPush();
    }

    private function initWebPush(): void
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!file_exists($autoload)) return;

        require_once $autoload;

        $publicKey  = VAPID_PUBLIC_KEY;
        $privateKey = VAPID_PRIVATE_KEY;
        $subject    = VAPID_SUBJECT;

        if (!$publicKey || !$privateKey) return;

        try {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject'    => $subject,
                    'publicKey'  => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
            $this->webPush->setReuseVAPIDHeaders(true);
        } catch (Throwable $e) {
            error_log('[PushService] Init failed: ' . $e->getMessage());
        }
    }

    // ─── SUBSCRIPTION MANAGEMENT ────────────────────────────────────────────

    /**
     * Save or update a push subscription for a user.
     */
    public function saveSubscription(int $userId, array $sub, string $userAgent = ''): bool
    {
        $endpoint = $sub['endpoint'] ?? '';
        $p256dh   = $sub['keys']['p256dh'] ?? '';
        $auth     = $sub['keys']['auth'] ?? '';

        if (!$endpoint || !$p256dh || !$auth) return false;

        // Upsert: if same user+endpoint exists, update keys; otherwise insert
        $this->db->execute(
            "INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key, user_agent)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE p256dh_key=VALUES(p256dh_key), auth_key=VALUES(auth_key), updated_at=NOW()",
            [$userId, $endpoint, $p256dh, $auth, $userAgent]
        );

        return true;
    }

    /**
     * Delete a push subscription by endpoint.
     */
    public function deleteSubscription(int $userId, string $endpoint): bool
    {
        $rows = $this->db->execute(
            "DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?",
            [$userId, $endpoint]
        );
        return $rows > 0;
    }

    /**
     * Get all subscriptions for a user.
     */
    public function getUserSubscriptions(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM push_subscriptions WHERE user_id = ?",
            [$userId]
        );
    }

    /**
     * Check if a user has any active subscriptions.
     */
    public function isSubscribed(int $userId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM push_subscriptions WHERE user_id = ?",
            [$userId]
        );
        return (int)($row['n'] ?? 0) > 0;
    }

    // ─── SENDING NOTIFICATIONS ───────────────────────────────────────────────

    /**
     * Send a push notification to a single user.
     *
     * @param int    $userId   Target user_id
     * @param string $title    Notification title
     * @param string $body     Notification body text
     * @param string $url      URL to open when clicked (relative or absolute)
     * @param string $icon     Icon URL (defaults to app icon)
     */
    public function sendToUser(int $userId, string $title, string $body, string $url = '', string $icon = ''): int
    {
        $subscriptions = $this->getUserSubscriptions($userId);
        if (empty($subscriptions)) return 0;

        return $this->sendToSubscriptions($subscriptions, $title, $body, $url, $icon);
    }

    /**
     * Send a push notification to ALL subscribed users.
     */
    public function sendToAll(string $title, string $body, string $url = '', string $icon = ''): int
    {
        $subscriptions = $this->db->fetchAll("SELECT * FROM push_subscriptions");
        if (empty($subscriptions)) return 0;

        return $this->sendToSubscriptions($subscriptions, $title, $body, $url, $icon);
    }

    /**
     * Send a push notification to a list of user_ids.
     */
    public function sendToUsers(array $userIds, string $title, string $body, string $url = '', string $icon = ''): int
    {
        if (empty($userIds)) return 0;

        $placeholders  = implode(',', array_fill(0, count($userIds), '?'));
        $subscriptions = $this->db->fetchAll(
            "SELECT * FROM push_subscriptions WHERE user_id IN ($placeholders)",
            $userIds
        );
        if (empty($subscriptions)) return 0;

        return $this->sendToSubscriptions($subscriptions, $title, $body, $url, $icon);
    }

    /**
     * Internal: queue and flush notifications to a set of subscription rows.
     * Returns the number of successful deliveries.
     */
    private function sendToSubscriptions(array $subscriptions, string $title, string $body, string $url, string $icon): int
    {
        if (!$this->webPush) {
            error_log('[PushService] WebPush not initialised — are VAPID keys set?');
            return 0;
        }

        $defaultIcon = BASE_URL . 'assets/images/icons/icon-192x192.png';
        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => $icon ?: $defaultIcon,
            'badge' => BASE_URL . 'assets/images/icons/badge-96x96.png',
            'url'   => $url ?: BASE_URL . 'notifications/',
            'tag'   => 'lasc-' . time(),
        ]);

        $expiredEndpoints = [];

        foreach ($subscriptions as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint'        => $sub['endpoint'],
                    'keys'            => [
                        'p256dh' => $sub['p256dh_key'],
                        'auth'   => $sub['auth_key'],
                    ],
                ]);
                $this->webPush->queueNotification($subscription, $payload);
            } catch (Throwable $e) {
                error_log('[PushService] Queue error for user ' . $sub['user_id'] . ': ' . $e->getMessage());
            }
        }

        $sent = 0;
        try {
            foreach ($this->webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $sent++;
                } else {
                    // 404/410 = subscription expired — clean it up
                    if (in_array($report->getResponse()?->getStatusCode(), [404, 410], true)) {
                        $expiredEndpoints[] = $report->getRequest()->getUri()->__toString();
                    }
                    error_log('[PushService] Delivery failed: ' . $report->getReason());
                }
            }
        } catch (Throwable $e) {
            error_log('[PushService] Flush error: ' . $e->getMessage());
        }

        // Clean up expired subscriptions
        foreach ($expiredEndpoints as $ep) {
            $this->db->execute("DELETE FROM push_subscriptions WHERE endpoint = ?", [$ep]);
        }

        return $sent;
    }

    // ─── HELPER: Also create in-app notification alongside push ──────────────

    /**
     * Send both an in-app DB notification AND a push notification to a user.
     */
    public function notify(int $userId, string $type, string $title, string $body, string $url = ''): void
    {
        // In-app notification (always)
        $this->db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $type, $title, $body, $url ?: null]
        );

        // Push notification (best-effort)
        $this->sendToUser($userId, $title, $body, $url);
    }
}
