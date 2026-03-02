<?php
/**
 * Gate Wey Access Management System
 * Delete User Handler - CORRECTED VERSION
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../classes/User.php';

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'clan_admin'])) {
    header('Location: ' . BASE_URL);
    exit;
}

// Get current user
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

// Check if user ID is provided and decrypt it
$userId = isset($_GET['id']) ? decryptId($_GET['id']) : null;
if (!$userId || !is_numeric($userId) || $userId <= 0) {
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Cannot delete yourself
if ($userId === $currentUser->getId()) {
    setFlashMessage('error', 'You cannot delete your own account.');
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Load the user to delete
$deleteUser = new User();
if (!$deleteUser->loadById($userId)) {
    setFlashMessage('error', 'User not found.');
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Check permissions
$hasDeletePermission = false;
if ($currentUser->isSuperAdmin()) {
    $hasDeletePermission = true;
} elseif ($currentUser->isClanAdmin() && $deleteUser->getClanId() == $currentUser->getClanId() && !$deleteUser->isClanAdmin()) {
    // Clan admins can only delete regular users and guards in their clan
    $hasDeletePermission = true;
}

if (!$hasDeletePermission) {
    setFlashMessage('error', 'You do not have permission to delete this user.');
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Get database instance
$db = Database::getInstance();

// Begin transaction
$db->beginTransaction();

try {
    // Get user details for logging
    $userDetails = [
        'username' => $deleteUser->getUsername(),
        'email' => $deleteUser->getEmail(),
        'full_name' => $deleteUser->getFullName(),
        'role' => $deleteUser->getRole()
    ];
    
    // DELETE IN THIS SPECIFIC ORDER (with correct column names):
    
    // 1. Delete chat messages (uses sender_id, not user_id)
    $db->query("DELETE FROM messages WHERE sender_id = ?", [$userId]);
    
    // 2. Delete message read status
    $db->query("DELETE FROM message_read_status WHERE user_id = ?", [$userId]);
    
    // 3. Delete chat participants
    $db->query("DELETE FROM chat_participants WHERE user_id = ?", [$userId]);
    
    // 4. Delete marketplace wishlists
    $db->query("DELETE FROM marketplace_wishlists WHERE user_id = ?", [$userId]);
    
    // 5. Delete marketplace saved searches
    $db->query("DELETE FROM marketplace_saved_searches WHERE user_id = ?", [$userId]);
    
    // 6. Delete marketplace reviews (as reviewer)
    $db->query("DELETE FROM marketplace_reviews WHERE reviewer_id = ?", [$userId]);
    
    // 7. Delete marketplace reviews (for their products)
    $db->query("DELETE FROM marketplace_reviews WHERE product_id IN (SELECT id FROM marketplace_products WHERE seller_id = ?)", [$userId]);
    
    // 8. Delete marketplace product images (for their products)
    $db->query("DELETE FROM marketplace_product_images WHERE product_id IN (SELECT id FROM marketplace_products WHERE seller_id = ?)", [$userId]);
    
    // 9. Delete marketplace messages (as sender or recipient)
    $db->query("DELETE FROM marketplace_messages WHERE sender_id = ? OR recipient_id = ?", [$userId, $userId]);
    
    // 10. Delete marketplace products
    $db->query("DELETE FROM marketplace_products WHERE seller_id = ?", [$userId]);
    
    // 11. Delete marketplace user record
    $db->query("DELETE FROM marketplace_users WHERE user_id = ?", [$userId]);
    
    // 12. Delete notifications
    $db->query("DELETE FROM notifications WHERE user_id = ?", [$userId]);
    
    // 13. Delete support tickets (no child tables)
    $db->query("DELETE FROM support_tickets WHERE user_id = ?", [$userId]);
    
    // 14. Delete access logs (as guard)
    $db->query("DELETE FROM access_logs WHERE guard_id = ?", [$userId]);
    
    // 15. Delete access logs (as user)
    $db->query("DELETE FROM access_logs WHERE user_id = ?", [$userId]);
    
    // 16. Get access codes and delete their logs first
    $accessCodes = $db->fetchAll("SELECT id FROM access_codes WHERE created_by = ?", [$userId]);
    foreach ($accessCodes as $code) {
        $db->query("DELETE FROM access_logs WHERE access_code_id = ?", [$code['id']]);
    }
    
    // 17. Now delete access codes (as creator)
    $db->query("DELETE FROM access_codes WHERE created_by = ?", [$userId]);
    
    // 18. Delete access codes (as visitor)
    $db->query("DELETE FROM access_codes WHERE visitor_id = ?", [$userId]);
    
    // 19. Delete access code templates
    $db->query("DELETE FROM access_code_templates WHERE created_by = ?", [$userId]);
    
    // 20. Delete clan dues payments (as payer)
    $db->query("DELETE FROM clan_dues_payments WHERE user_id = ?", [$userId]);
    
    // 21. Delete clan dues payments (as processor)
    $db->query("DELETE FROM clan_dues_payments WHERE processed_by = ?", [$userId]);
    
    // 22. Delete clan dues (as creator)
    $db->query("DELETE FROM clan_dues WHERE created_by = ?", [$userId]);
    
    // 23. Delete password reset tokens
    $db->query("DELETE FROM password_resets WHERE user_id = ?", [$userId]);
    
    // 24. Delete activity logs
    $db->query("DELETE FROM activity_logs WHERE user_id = ?", [$userId]);
    
    // 25. Delete household licenses
    $db->query("DELETE FROM household_licenses WHERE purchased_by = ?", [$userId]);
    
    // 26. Delete push subscriptions
    $db->query("DELETE FROM push_subscriptions WHERE user_id = ?", [$userId]);
    
    // 27. Check if user is a clan admin
    if ($deleteUser->getRole() === 'clan_admin') {
        // Check if this is the only admin for the clan
        $otherAdmins = $db->fetchOne(
            "SELECT COUNT(*) as count FROM users 
             WHERE clan_id = ? AND role = 'clan_admin' AND id != ?",
            [$deleteUser->getClanId(), $userId]
        );
        
        if ($otherAdmins['count'] == 0) {
            // This is the only admin, update clan to remove admin reference
            $db->query(
                "UPDATE clans SET admin_id = NULL WHERE admin_id = ?",
                [$userId]
            );
        }
    }
    
    // 28. Update households if this user is the creator
    $db->query("UPDATE households SET created_by = NULL WHERE created_by = ?", [$userId]);
    
    // 29. Update chat rooms if this user is the creator
    $db->query("UPDATE chat_rooms SET created_by = NULL WHERE created_by = ?", [$userId]);
    
    // 30. Finally delete the user
    $db->query("DELETE FROM users WHERE id = ?", [$userId]);
    
    // Log the activity
    logActivity(
        'delete_user', 
        'Deleted user: ' . $userDetails['username'] . ' (' . $userDetails['full_name'] . ')', 
        $currentUser->getId()
    );
    
    // Commit transaction
    $db->commit();
    
    // Set success message
    setFlashMessage('success', 'User deleted successfully.');
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    
    // Log error
    error_log("Error deleting user: " . $e->getMessage());
    
    // Set error message
    setFlashMessage('error', 'An error occurred while deleting the user: ' . $e->getMessage());
}

// Redirect back to users list
header('Location: ' . BASE_URL . 'users/');
exit;
?>