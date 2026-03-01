<?php
/**
 * API — Cast / change poll vote
 * POST: poll_id, option_id
 * Returns: JSON { success, message }
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Poll.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$pollId   = (int)($_POST['poll_id']   ?? 0);
$optionId = (int)($_POST['option_id'] ?? 0);

if (!$pollId || !$optionId) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$pollObj = new Poll();
$result  = $pollObj->vote($pollId, $optionId, (int)$_SESSION['user_id']);

echo json_encode($result);
