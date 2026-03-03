<?php
/**
 * GateWey - Delete Announcement API
 * Allows authors and admins to delete announcements
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
    
    if (!isset($data['announcement_id'])) {
        echo json_encode(['success' => false, 'message' => 'Missing announcement ID']);
        exit;
    }

    $announcementId = decryptId($data['announcement_id']);
    if (!$announcementId || !is_numeric($announcementId) || $announcementId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
        exit;
    }
    $clanId = $currentUser->getClanId();
    $userRole = $currentUser->getRole();
    
    // Fetch announcement
    $announcement = $db->fetchOne(
        "SELECT * FROM announcements WHERE id = ? AND clan_id = ?",
        [$announcementId, $clanId]
    );
    
    if (!$announcement) {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        exit;
    }
    
    // Check authorization - only author or admins can delete
    if ($announcement['author_id'] !== $currentUser->getId() && 
        !in_array($userRole, ['super_admin', 'clan_admin'])) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to delete this announcement']);
        exit;
    }
    
    // Delete associated image if it exists
    if ($announcement['image_path'] && file_exists('../' . $announcement['image_path'])) {
        unlink('../' . $announcement['image_path']);
    }
    
    // Delete announcement (CASCADE will handle reactions and comments)
    $db->query("DELETE FROM announcements WHERE id = ?", [$announcementId]);
    
    // Log activity
    $db->query(
        "INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) 
         VALUES (?, 'announcement_deleted', ?, ?, NOW())",
        [
            $currentUser->getId(),
            "Deleted announcement: {$announcement['title']}",
            $_SERVER['REMOTE_ADDR']
        ]
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcement deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Delete announcement error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the announcement'
    ]);
}