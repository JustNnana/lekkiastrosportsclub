<?php
/**
 * GateWey - Toggle Announcement Reaction API
 * Allows users to like/react to announcements
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

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['announcement_id']) || !isset($data['action'])) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $announcementId = decryptId($data['announcement_id']);
    if (!$announcementId || !is_numeric($announcementId) || $announcementId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
        exit;
    }
    $action = $data['action']; // 'add' or 'remove'
    $reactionType = $data['reaction_type'] ?? 'like';
    
    // Verify announcement exists and user has access to it
    $announcement = $db->fetchOne(
        "SELECT id, clan_id FROM announcements WHERE id = ?",
        [$announcementId]
    );
    
    if (!$announcement) {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        exit;
    }
    
    // Verify user is in the same clan
    if ($announcement['clan_id'] !== $currentUser->getClanId()) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    if ($action === 'add') {
        // Add reaction
        try {
            $db->query(
                "INSERT INTO announcement_reactions (announcement_id, user_id, reaction_type, created_at)
                 VALUES (?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE reaction_type = VALUES(reaction_type)",
                [$announcementId, $currentUser->getId(), $reactionType]
            );
        } catch (Exception $e) {
            // Ignore duplicate key errors
        }
    } else {
        // Remove reaction
        $db->query(
            "DELETE FROM announcement_reactions 
             WHERE announcement_id = ? AND user_id = ?",
            [$announcementId, $currentUser->getId()]
        );
    }
    
    // Get updated reaction count
    $result = $db->fetchOne(
        "SELECT COUNT(*) as count FROM announcement_reactions WHERE announcement_id = ?",
        [$announcementId]
    );
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count'],
        'action' => $action
    ]);
    
} catch (Exception $e) {
    error_log("Toggle reaction error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}