<?php
/**
 * API — Toggle announcement reaction
 * POST: announcement_id, reaction
 * Returns: JSON { success, user_reaction, counts: {like, love, support, celebrate} }
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$annId    = (int)($_POST['announcement_id'] ?? 0);
$reaction = sanitize($_POST['reaction'] ?? '');

$allowed = ['like', 'love', 'support', 'celebrate'];
if (!$annId || !in_array($reaction, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$annObj = new Announcement();
$ann    = $annObj->getById($annId);

if (!$ann || !$ann['is_published']) {
    echo json_encode(['success' => false, 'message' => 'Not found']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$annObj->toggleReaction($annId, $userId, $reaction);

// Return fresh counts + user's current reaction
$fresh = $annObj->getReactions($annId, $userId);

echo json_encode([
    'success'       => true,
    'user_reaction' => $fresh['user_reaction'],
    'counts'        => [
        'like'      => $fresh['like'],
        'love'      => $fresh['love'],
        'support'   => $fresh['support'],
        'celebrate' => $fresh['celebrate'],
    ],
]);
