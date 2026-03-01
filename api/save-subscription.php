<?php
/**
 * API — Save / upsert a push subscription
 * POST body: { subscription: {...}, user_id: int }
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

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit;
}

// Security: only allow saving for the current logged-in user
$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthenticated']);
    exit;
}

$subscription = $body['subscription'] ?? null;
if (!$subscription || empty($subscription['endpoint'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid subscription data']);
    exit;
}

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

$push = new PushService();
$ok   = $push->saveSubscription($userId, $subscription, $ua);

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Subscription saved' : 'Failed to save subscription',
]);
