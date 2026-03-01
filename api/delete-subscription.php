<?php
/**
 * API — Delete a push subscription
 * POST body: { subscription: { endpoint: string }, user_id: int }
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

$userId   = (int)($_SESSION['user_id'] ?? 0);
$endpoint = $body['subscription']['endpoint'] ?? '';

if (!$userId || !$endpoint) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$push = new PushService();
$ok   = $push->deleteSubscription($userId, $endpoint);

echo json_encode([
    'success' => $ok,
    'message' => $ok ? 'Subscription removed' : 'Subscription not found',
]);
