<?php
/**
 * Session heartbeat — called every 30s by footer JS
 * Returns 200 if session alive, 401 if expired
 */
require_once dirname(__DIR__) . '/app/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'expired']);
    exit;
}

$_SESSION['last_activity'] = time();
echo json_encode(['status' => 'ok']);
