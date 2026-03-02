<?php
/**
 * Gate Wey Access Management System
 * Dashboard Router
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL);
    exit;
}

// Get user role and redirect to appropriate dashboard
$userRole = $_SESSION['role'];

switch ($userRole) {
    case 'super_admin':
        // Redirect to super admin dashboard
        include_once 'super-admin.php';
        break;
    
    case 'clan_admin':
        // Redirect to clan admin dashboard
        include_once 'clan-admin.php';
        break;
    
    case 'guard':
        // Redirect to guard dashboard
        include_once 'guard.php';
        break;
    
    case 'user':
        // Redirect to regular user dashboard
        include_once 'user.php';
        break;
    
    default:
        // Redirect to login if role is not recognized
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL);
        exit;
}
?>