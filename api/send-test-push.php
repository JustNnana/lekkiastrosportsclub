<?php
/**
 * API — Send a test push notification to the current user
 * POST (no body required)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/PushService.php';

header('Content-Type: application/json');

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$push = new PushService();

if (!$push->isSubscribed($userId)) {
    echo json_encode(['success' => false, 'message' => 'No active push subscription found for this user.']);
    exit;
}

$sent = $push->sendToUser(
    $userId,
    SITE_NAME . ' — Test Notification',
    'Push notifications are working correctly! 🎉',
    BASE_URL . 'notifications/'
);

echo json_encode([
    'success' => $sent > 0,
    'message' => $sent > 0 ? 'Test notification sent!' : 'Delivery failed. Check server logs.',
]);
