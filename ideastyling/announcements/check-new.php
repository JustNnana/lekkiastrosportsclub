<?php
/**
 * GateWey - Check New Announcements API
 * Checks for new announcements since a given timestamp
 */

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expired']);
    exit;
}
$clanId = $currentUser->getClanId();

try {
    // Get timestamp from query parameter
    $since = isset($_GET['since']) ? $_GET['since'] : date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    // Validate and sanitize timestamp
    $sinceTimestamp = date('Y-m-d H:i:s', strtotime($since));
    
    // Count new announcements
    $result = $db->fetchOne(
        "SELECT COUNT(*) as count 
         FROM announcements 
         WHERE clan_id = ? 
         AND created_at > ? 
         AND author_id != ?",
        [$clanId, $sinceTimestamp, $currentUser->getId()]
    );
    
    $count = (int)$result['count'];
    
    echo json_encode([
        'success' => true,
        'has_new' => $count > 0,
        'count' => $count,
        'since' => $sinceTimestamp
    ]);
    
} catch (Exception $e) {
    error_log("Check new announcements error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred',
        'has_new' => false,
        'count' => 0
    ]);
}