<?php
/**
 * API: Mark Announcements as Read
 * File: /announcements/mark-announcements-read.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response
ini_set('log_errors', 1);

require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    
    // Check if record exists
    $existing = $db->fetchOne(
        "SELECT id FROM user_announcement_reads WHERE user_id = ?",
        [$userId]
    );
    
    if ($existing) {
        // Update existing record
        $result = $db->query(
            "UPDATE user_announcement_reads SET last_read_at = NOW() WHERE user_id = ?",
            [$userId]
        );
    } else {
        // Insert new record
        $result = $db->query(
            "INSERT INTO user_announcement_reads (user_id, last_read_at) VALUES (?, NOW())",
            [$userId]
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Announcements marked as read',
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $userId
    ]);
    
} catch (Exception $e) {
    error_log("Error marking announcements as read: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage() // Remove this in production
    ]);
}