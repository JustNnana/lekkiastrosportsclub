<?php

/**
 * Gate Wey Access Management System
 * Clan Admins Management - Super Admin Only - Dasher UI Enhanced
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../includes/functions.php';

// Set page title
$pageTitle = 'Clan Admins Management';

// Check if user is logged in and is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ' . BASE_URL);
    exit;
}

// Initialize flash message variables
$error = '';
$success = '';

// Get database instance
$db = Database::getInstance();

// Get current user
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    // If user doesn't exist, clear session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

// Check for flash messages
if (isset($_SESSION['flash_messages']) && is_array($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
    $lastMessage = end($_SESSION['flash_messages']);
    if ($lastMessage['type'] === 'success') {
        $success = $lastMessage['message'];
    } elseif ($lastMessage['type'] === 'error') {
        $error = $lastMessage['message'];
    }
    unset($_SESSION['flash_messages']);
}

// Handle actions (promote, demote, delete)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $userId = decryptId($_GET['id']);
    if (!$userId || !is_numeric($userId) || $userId <= 0) {
        setFlashMessage('error', 'Invalid user ID.');
        header('Location: ' . BASE_URL . 'users/admins.php');
        exit;
    }
    $action = $_GET['action'];

    // Verify user exists
    $targetUser = new User();
    if (!$targetUser->loadById($userId)) {
        setFlashMessage('error', 'User not found.');
        header('Location: ' . BASE_URL . 'users/admins.php');
        exit;
    }

    // Process actions
    switch ($action) {
        case 'promote':
            // Check if user is not already an admin
            if ($targetUser->getRole() !== 'clan_admin') {
                // Verify clan exists
                $clanId = $targetUser->getClanId();
                if ($clanId) {
                    // Check if clan already has an admin
                    $existingAdmin = $db->fetchOne(
                        "SELECT id FROM users WHERE clan_id = ? AND role = 'clan_admin'",
                        [$clanId]
                    );

                    if ($existingAdmin) {
                        setFlashMessage('error', 'This clan already has an admin. Please demote the existing admin first.');
                    } else {
                        // Promote user to clan admin
                        $db->query(
                            "UPDATE users SET role = 'clan_admin' WHERE id = ?",
                            [$userId]
                        );

                        // Update clan admin_id field
                        $db->query(
                            "UPDATE clans SET admin_id = ? WHERE id = ?",
                            [$userId, $clanId]
                        );

                        // Log activity
                        logActivity('promote_admin', 'Promoted user to clan admin: ' . $targetUser->getFullName(), $currentUser->getId());

                        setFlashMessage('success', $targetUser->getFullName() . ' has been promoted to clan admin.');
                    }
                } else {
                    setFlashMessage('error', 'User must be assigned to a clan before promotion.');
                }
            } else {
                setFlashMessage('error', 'User is already a clan admin.');
            }
            break;

        case 'demote':
            // Check if user is a clan admin
            if ($targetUser->getRole() === 'clan_admin') {
                // Demote user to regular user
                $db->query(
                    "UPDATE users SET role = 'user' WHERE id = ?",
                    [$userId]
                );

                // Update clan admin_id field
                $clanId = $targetUser->getClanId();
                if ($clanId) {
                    $db->query(
                        "UPDATE clans SET admin_id = NULL WHERE id = ? AND admin_id = ?",
                        [$clanId, $userId]
                    );
                }

                // Log activity
                logActivity('demote_admin', 'Demoted user from clan admin: ' . $targetUser->getFullName(), $currentUser->getId());

                setFlashMessage('success', $targetUser->getFullName() . ' has been demoted to regular user.');
            } else {
                setFlashMessage('error', 'User is not a clan admin.');
            }
            break;

        case 'deactivate':
            // Toggle user status to inactive
            if ($targetUser->getStatus() === 'active') {
                $db->query("UPDATE users SET status = 'inactive', updated_at = NOW() WHERE id = ?", [$userId]);
                logActivity('deactivate_user', 'Deactivated user: ' . $targetUser->getFullName(), $currentUser->getId());
                setFlashMessage('success', $targetUser->getFullName() . ' has been deactivated.');
            } else {
                $db->query("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?", [$userId]);
                logActivity('activate_user', 'Activated user: ' . $targetUser->getFullName(), $currentUser->getId());
                setFlashMessage('success', $targetUser->getFullName() . ' has been activated.');
            }
            break;

        default:
            setFlashMessage('error', 'Invalid action.');
            break;
    }

    // Redirect to avoid resubmission
    header('Location: ' . BASE_URL . 'users/admins.php');
    exit;
}

// Get list of clan admins with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// For search and filter functionality
$search = isset($_GET['search']) ? trim(htmlspecialchars($_GET['search'], ENT_QUOTES, 'UTF-8')) : '';
$statusFilter = isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive', 'suspended']) ? $_GET['status'] : '';
$searchCondition = '';
$searchParams = [];

if (!empty($search)) {
    $searchCondition .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?)";
    $searchTerm = "%$search%";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm];
}

if (!empty($statusFilter)) {
    $searchCondition .= " AND u.status = ?";
    $searchParams[] = $statusFilter;
}

// Get all clan admins
$adminsQuery = "SELECT u.id, u.username, u.email, u.full_name, u.status, u.last_login,
                c.id as clan_id, c.name as clan_name
                FROM users u
                LEFT JOIN clans c ON u.clan_id = c.id
                WHERE u.role = 'clan_admin'
                $searchCondition
                ORDER BY u.full_name ASC
                LIMIT ? OFFSET ?";

$adminParams = array_merge($searchParams, [$limit, $offset]);
$admins = $db->fetchAll($adminsQuery, $adminParams);

// Get total count for pagination
$totalQuery = "SELECT COUNT(*) as count
               FROM users u
               WHERE u.role = 'clan_admin'
               $searchCondition";
$totalResult = $db->fetchOne($totalQuery, $searchParams);
$totalAdmins = $totalResult['count'];
$totalPages = ceil($totalAdmins / $limit);

// Get unassigned clans (clans without admins)
$unassignedClansQuery = "SELECT c.id, c.name
                         FROM clans c
                         LEFT JOIN users u ON c.id = u.clan_id AND u.role = 'clan_admin'
                         WHERE u.id IS NULL
                         ORDER BY c.name ASC";
$unassignedClans = $db->fetchAll($unassignedClansQuery);

// Get potential admins (users who can be promoted)
$potentialAdminsQuery = "SELECT u.id, u.username, u.full_name, c.name as clan_name
                         FROM users u
                         JOIN clans c ON u.clan_id = c.id
                         WHERE u.role = 'user'
                         AND NOT EXISTS (
                             SELECT 1 FROM users u2
                             WHERE u2.clan_id = u.clan_id
                             AND u2.role = 'clan_admin'
                         )
                         ORDER BY c.name ASC, u.full_name ASC";
$potentialAdmins = $db->fetchAll($potentialAdminsQuery);

// Get statistics
$totalStats = [
    'total_admins' => $totalAdmins,
    'active_admins' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'clan_admin' AND status = 'active'")['count'] ?? 0,
    'inactive_admins' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'clan_admin' AND status = 'inactive'")['count'] ?? 0,
    'unassigned_clans' => count($unassignedClans)
];

// Include header
include_once '../includes/header.php';

// Include sidebar
include_once '../includes/sidebar.php';
?>

<!-- iOS-Style Clan Admins Styles -->
<style>
    :root {
        --ios-red: #FF453A;
        --ios-orange: #FF9F0A;
        --ios-yellow: #FFD60A;
        --ios-green: #30D158;
        --ios-teal: #64D2FF;
        --ios-blue: #0A84FF;
        --ios-purple: #BF5AF2;
        --ios-gray: #8E8E93;
    }

    /* Statistics Overview Grid */
    .content .stats-overview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--spacing-4);
        margin-bottom: var(--spacing-6);
    }

    .content .stat-card {
        background: transparent;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-lg);
        padding: var(--spacing-5);
        display: flex;
        align-items: center;
        gap: var(--spacing-4);
        transition: var(--theme-transition);
    }

    .content .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
        border-color: var(--primary);
    }

    .content .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--border-radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        flex-shrink: 0;
    }

    .content .stat-success .stat-icon { background: var(--success); }
    .content .stat-warning .stat-icon { background: var(--warning); }
    .content .stat-danger .stat-icon { background: var(--danger); }
    .content .stat-primary .stat-icon { background: var(--primary); }
    .content .stat-info .stat-icon { background: var(--info); }

    .content .stat-content { flex: 1; }

    .content .stat-label {
        font-size: var(--font-size-sm);
        color: var(--text-secondary);
        margin-bottom: var(--spacing-1);
    }

    .content .stat-value {
        font-size: 1.75rem;
        font-weight: var(--font-weight-bold);
        color: var(--text-primary);
        line-height: 1;
        margin-bottom: var(--spacing-2);
    }

    .content .stat-detail {
        font-size: var(--font-size-xs);
        color: var(--text-secondary);
    }

    /* iOS Section Card */
    .ios-section-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: var(--spacing-4);
    }

    .ios-section-header {
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
        padding: var(--spacing-4);
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
    }

    .ios-section-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .ios-section-icon.primary {
        background: rgba(34, 197, 94, 0.15);
        color: var(--ios-green);
    }

    .ios-section-icon.orange {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .ios-section-icon.blue {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    .ios-section-icon.purple {
        background: rgba(191, 90, 242, 0.15);
        color: var(--ios-purple);
    }

    .ios-section-icon.red {
        background: rgba(255, 69, 58, 0.15);
        color: var(--ios-red);
    }

    .ios-section-icon.teal {
        background: rgba(100, 210, 255, 0.15);
        color: var(--ios-teal);
    }

    .ios-section-title {
        flex: 1;
    }

    .ios-section-title h5 {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .ios-section-title p {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 4px 0 0 0;
    }

    .ios-section-body {
        padding: 0;
    }

    /* 3-Dot Menu Button */
    .ios-options-btn {
        display: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.15s ease;
        flex-shrink: 0;
    }

    .ios-options-btn:hover {
        background: var(--border-color);
    }

    .ios-options-btn:active {
        transform: scale(0.95);
    }

    .ios-options-btn i {
        color: var(--text-primary);
        font-size: 16px;
    }

    /* iOS User Item */
    .ios-user-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: var(--bg-primary);
        cursor: pointer;
        transition: background 0.15s ease;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-user-item:last-child {
        border-bottom: none;
    }

    .ios-user-item:hover {
        background: rgba(255, 255, 255, 0.03);
    }

    .ios-user-item:active {
        background: rgba(255, 255, 255, 0.06);
    }

    .ios-user-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 16px;
        flex-shrink: 0;
    }

    .ios-user-content {
        flex: 1;
        min-width: 0;
    }

    .ios-user-name {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 2px 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ios-user-username {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0 0 2px 0;
    }

    .ios-user-email {
        font-size: 12px;
        color: var(--text-muted);
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ios-user-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        flex-shrink: 0;
    }

    .ios-status-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 6px;
        text-transform: capitalize;
    }

    .ios-status-badge.active {
        background: rgba(48, 209, 88, 0.15);
        color: var(--ios-green);
    }

    .ios-status-badge.inactive {
        background: rgba(255, 69, 58, 0.15);
        color: var(--ios-red);
    }

    .ios-status-badge.suspended {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .ios-role-badge {
        font-size: 11px;
        font-weight: 500;
        padding: 2px 6px;
        border-radius: 4px;
    }

    .ios-role-badge.clan_admin {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    .ios-user-login {
        font-size: 11px;
        color: var(--text-muted);
    }

    /* 3-Dot Actions Button per item */
    .ios-actions-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.15s ease;
        flex-shrink: 0;
    }

    .ios-actions-btn:hover {
        background: var(--border-color);
    }

    .ios-actions-btn:active {
        transform: scale(0.95);
    }

    .ios-actions-btn i {
        color: var(--text-primary);
        font-size: 14px;
    }

    /* iOS Filter Pills */
    .ios-filter-pills {
        display: flex;
        gap: 8px;
        padding: 12px 16px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.2s ease;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-secondary);
    }

    .ios-filter-pill:hover {
        background: var(--border-color);
        color: var(--text-primary);
    }

    .ios-filter-pill.active {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
        color: white;
    }

    .ios-filter-pill .count {
        font-size: 11px;
        padding: 2px 6px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.2);
    }

    .ios-filter-pill:not(.active) .count {
        background: var(--border-color);
    }

    /* iOS Search Box */
    .ios-search-box {
        padding: 12px 16px;
        background: var(--bg-subtle);
    }

    .ios-search-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .ios-search-icon {
        position: absolute;
        left: 12px;
        color: var(--text-muted);
        font-size: 14px;
        pointer-events: none;
    }

    .ios-search-input {
        width: 100%;
        padding: 10px 36px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 15px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .ios-search-input:focus {
        outline: none;
        border-color: var(--ios-blue);
        box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.1);
    }

    .ios-search-input::placeholder {
        color: var(--text-muted);
    }

    .ios-search-clear {
        position: absolute;
        right: 10px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--border-color);
        border: none;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-secondary);
        font-size: 10px;
    }

    .ios-search-clear.visible {
        display: flex;
    }

    /* Empty State */
    .ios-empty-state {
        text-align: center;
        padding: 48px 24px;
    }

    .ios-empty-icon {
        font-size: 56px;
        color: var(--text-secondary);
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .ios-empty-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 8px 0;
    }

    .ios-empty-description {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0 0 20px 0;
        line-height: 1.5;
    }

    /* iOS Pagination */
    .ios-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 16px;
        border-top: 1px solid var(--border-color);
    }

    .ios-page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 10px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        color: var(--text-secondary);
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }

    .ios-page-btn:hover {
        background: var(--border-color);
        color: var(--text-primary);
    }

    .ios-page-btn.active {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
        color: white;
    }

    .ios-pagination-info {
        font-size: 12px;
        color: var(--text-muted);
        text-align: center;
        padding: 0 16px 12px;
    }

    /* iOS-Style Mobile Menu Modal */
    .ios-menu-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 9998;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .ios-menu-backdrop.active {
        opacity: 1;
        visibility: visible;
    }

    .ios-menu-modal {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--bg-primary);
        border-radius: 16px 16px 0 0;
        z-index: 9999;
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
        max-height: 85vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .ios-menu-modal.active {
        transform: translateY(0);
    }

    .ios-menu-handle {
        width: 36px;
        height: 5px;
        background: var(--border-color);
        border-radius: 3px;
        margin: 8px auto 4px;
        flex-shrink: 0;
    }

    .ios-menu-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px 16px;
        border-bottom: 1px solid var(--border-color);
        flex-shrink: 0;
    }

    .ios-menu-title {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .ios-menu-close {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--bg-secondary);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .ios-menu-close:hover {
        background: var(--border-color);
    }

    .ios-menu-content {
        padding: 16px;
        overflow-y: auto;
        flex: 1;
        -webkit-overflow-scrolling: touch;
    }

    .ios-menu-section {
        margin-bottom: 20px;
    }

    .ios-menu-section:last-child {
        margin-bottom: 0;
    }

    .ios-menu-section-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
        padding-left: 4px;
    }

    .ios-menu-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
    }

    .ios-menu-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
        text-decoration: none;
        color: var(--text-primary);
        transition: background 0.15s ease;
        cursor: pointer;
        width: 100%;
        background: transparent;
        border-left: none;
        border-right: none;
        border-top: none;
        font-family: inherit;
        font-size: inherit;
        text-align: left;
    }

    button.ios-menu-item {
        border: none;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-menu-item:last-child {
        border-bottom: none;
    }

    .ios-menu-item:active {
        background: var(--bg-subtle);
    }

    .ios-menu-item-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
        min-width: 0;
    }

    .ios-menu-item-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    .ios-menu-item-icon.primary { background: rgba(34, 197, 94, 0.15); color: var(--ios-green); }
    .ios-menu-item-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-menu-item-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-menu-item-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-menu-item-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
    .ios-menu-item-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }

    .ios-menu-item-content {
        flex: 1;
        min-width: 0;
    }

    .ios-menu-item-label {
        font-size: 15px;
        font-weight: 500;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ios-menu-item-desc {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    .ios-menu-item-chevron {
        color: var(--text-muted);
        font-size: 12px;
    }

    .ios-menu-stat-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-menu-stat-row:last-child {
        border-bottom: none;
    }

    .ios-menu-stat-label {
        font-size: 15px;
        color: var(--text-primary);
    }

    .ios-menu-stat-value {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .ios-menu-stat-value.success { color: var(--ios-green); }
    .ios-menu-stat-value.warning { color: var(--ios-orange); }
    .ios-menu-stat-value.danger { color: var(--ios-red); }

    /* iOS Action Modal */
    .ios-action-modal {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--bg-primary);
        border-radius: 16px 16px 0 0;
        z-index: 10000;
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
        max-height: 70vh;
        overflow: hidden;
    }

    .ios-action-modal.active {
        transform: translateY(0);
    }

    .ios-action-modal-header {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
        text-align: center;
    }

    .ios-action-modal-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }

    .ios-action-modal-subtitle {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
    }

    .ios-action-modal-body {
        padding: 8px;
    }

    .ios-action-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-radius: 12px;
        text-decoration: none;
        color: var(--text-primary);
        transition: background 0.15s ease;
        cursor: pointer;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        font-family: inherit;
        font-size: inherit;
    }

    .ios-action-item:active {
        background: var(--bg-secondary);
    }

    .ios-action-item i {
        width: 24px;
        font-size: 18px;
    }

    .ios-action-item.danger {
        color: var(--ios-red);
    }

    .ios-action-item.primary {
        color: var(--ios-blue);
    }

    .ios-action-item.warning {
        color: var(--ios-orange);
    }

    .ios-action-item.success {
        color: var(--ios-green);
    }

    .ios-action-cancel {
        display: block;
        width: calc(100% - 16px);
        margin: 8px;
        padding: 14px;
        background: var(--bg-secondary);
        border: none;
        border-radius: 12px;
        font-size: 17px;
        font-weight: 600;
        color: var(--ios-blue);
        text-align: center;
        cursor: pointer;
        transition: background 0.15s ease;
        font-family: inherit;
    }

    .ios-action-cancel:active {
        background: var(--border-color);
    }

    /* Enhanced Form Controls */
    .enhanced-form {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-4);
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-2);
    }

    .form-label {
        font-weight: var(--font-weight-medium);
        color: var(--text-primary);
        font-size: var(--font-size-sm);
    }

    .form-label.required::after {
        content: ' *';
        color: var(--danger);
    }

    .form-control {
        padding: var(--spacing-3) var(--spacing-4);
        font-size: var(--font-size-base);
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        color: var(--text-primary);
        transition: var(--theme-transition);
    }

    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
        outline: none;
    }

    .form-text {
        font-size: var(--font-size-xs);
        color: var(--text-secondary);
    }

    /* Promotion Preview */
    .promotion-preview {
        margin-top: var(--spacing-4);
        padding: var(--spacing-4);
        background: var(--bg-subtle);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
    }

    .preview-content {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-2);
    }

    .preview-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--spacing-2) 0;
        border-bottom: 1px solid var(--border-color);
        font-size: var(--font-size-sm);
    }

    .preview-item:last-child { border-bottom: none; }
    .preview-item strong { color: var(--text-primary); }

    /* Mobile Optimizations */
    @media (max-width: 992px) {
        .ios-options-btn {
            display: flex;
        }
    }

    @media (max-width: 768px) {
        /* Hide content header on mobile */
        .content-header {
            display: none !important;
        }

        .content .stats-overview-grid {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            gap: 0.75rem !important;
            padding-bottom: 0.5rem !important;
            -webkit-overflow-scrolling: touch;
        }

        .content .stat-card {
            flex: 0 0 auto !important;
            min-width: 160px !important;
            padding: var(--spacing-4);
        }

        .content .stat-icon {
            width: 40px !important;
            height: 40px !important;
            font-size: var(--font-size-lg);
        }

        .content .stat-value {
            font-size: 1.5rem;
        }

        .ios-section-card {
            border-radius: 12px;
        }

        .ios-section-header {
            padding: 14px;
        }

        .ios-section-icon {
            width: 36px;
            height: 36px;
            font-size: 16px;
        }

        .ios-section-title h5 {
            font-size: 15px;
        }

        .ios-user-item {
            padding: 12px 14px;
        }

        .ios-user-avatar {
            width: 38px;
            height: 38px;
            font-size: 14px;
        }

        .ios-user-name {
            font-size: 14px;
        }

        .ios-user-username {
            font-size: 12px;
        }

        .ios-user-email {
            font-size: 11px;
        }

        .ios-status-badge {
            font-size: 10px;
            padding: 2px 6px;
        }

        .ios-role-badge {
            font-size: 10px;
        }

        .ios-empty-state {
            padding: 32px 16px;
        }

        .ios-empty-icon {
            font-size: 48px;
        }

        .ios-empty-title {
            font-size: 16px;
        }

        .ios-empty-description {
            font-size: 13px;
        }

        .modal-dialog.modal-lg {
            margin: 1rem;
            width: calc(100% - 2rem);
        }
    }

    @media (max-width: 480px) {
        .ios-options-btn {
            width: 32px;
            height: 32px;
        }

        .ios-options-btn i {
            font-size: 14px;
        }
    }
</style>
<!-- Dasher UI Content Area -->
<div class="content">
    <!-- Content Header -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="content-title">Clan Administrators</h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>users/" class="breadcrumb-link">Users</a>
                        </li>
                        <li class="breadcrumb-item active">Clan Admins</li>
                    </ol>
                </nav>
                <p class="content-description">Manage clan administrators across all clans in the system</p>
            </div>
            <div class="content-actions">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#promoteUserModal">
                    <i class="fas fa-user-plus"></i>
                    <span>Promote User</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <!-- Statistics Overview -->
    <div class="stats-overview-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Admins</div>
                <div class="stat-value"><?php echo number_format($totalStats['total_admins']); ?></div>
                <div class="stat-detail">Across all clans</div>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Active Admins</div>
                <div class="stat-value"><?php echo number_format($totalStats['active_admins']); ?></div>
                <div class="stat-detail"><?php echo $totalStats['total_admins'] > 0 ? round(($totalStats['active_admins'] / $totalStats['total_admins']) * 100) : 0; ?>% active</div>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Unassigned Clans</div>
                <div class="stat-value"><?php echo number_format($totalStats['unassigned_clans']); ?></div>
                <div class="stat-detail">Require admin assignment</div>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Eligible Users</div>
                <div class="stat-value"><?php echo number_format(count($potentialAdmins)); ?></div>
                <div class="stat-detail">Can be promoted</div>
            </div>
        </div>
    </div>

    <!-- Clan Administrators Section -->
    <div class="ios-section-card">
        <div class="ios-section-header">
            <div class="ios-section-icon blue">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="ios-section-title">
                <h5>Clan Administrators</h5>
                <p><?php echo number_format($totalAdmins); ?> administrator<?php echo $totalAdmins != 1 ? 's' : ''; ?> found</p>
            </div>
            <button class="ios-options-btn" onclick="openIosMenu()" aria-label="Open menu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Search Box -->
        <div class="ios-search-box">
            <div class="ios-search-input-wrapper">
                <i class="fas fa-search ios-search-icon"></i>
                <input
                    type="text"
                    id="adminSearch"
                    class="ios-search-input"
                    placeholder="Search administrators..."
                    autocomplete="off"
                >
                <button class="ios-search-clear" id="clearSearch">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Filter Pills -->
        <div class="ios-filter-pills">
            <a href="<?php echo BASE_URL; ?>users/admins.php"
               class="ios-filter-pill <?php echo !isset($_GET['status']) ? 'active' : ''; ?>">
                All
                <span class="count"><?php echo number_format($totalStats['total_admins']); ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>users/admins.php?status=active"
               class="ios-filter-pill <?php echo (isset($_GET['status']) && $_GET['status'] === 'active') ? 'active' : ''; ?>">
                Active
                <span class="count"><?php echo $totalStats['active_admins']; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>users/admins.php?status=inactive"
               class="ios-filter-pill <?php echo (isset($_GET['status']) && $_GET['status'] === 'inactive') ? 'active' : ''; ?>">
                Inactive
                <span class="count"><?php echo $totalStats['inactive_admins']; ?></span>
            </a>
        </div>

        <?php if (empty($admins)): ?>
            <!-- Empty State -->
            <div class="ios-empty-state">
                <div class="ios-empty-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h3 class="ios-empty-title">No administrators found</h3>
                <p class="ios-empty-description">
                    No administrators match your current filters.
                </p>
            </div>
        <?php else: ?>
            <!-- Admins List -->
            <div class="ios-section-body" id="adminsList">
                <?php foreach ($admins as $admin): ?>
                    <div class="ios-user-item admin-row"
                         data-admin-id="<?php echo $admin['id']; ?>"
                         onclick="openAdminActionModal(<?php echo $admin['id']; ?>)">
                        <div class="ios-user-avatar" style="background: linear-gradient(135deg, #0A84FF, #0a58ca)">
                            <?php echo strtoupper(substr($admin['full_name'], 0, 1)); ?>
                        </div>
                        <div class="ios-user-content">
                            <p class="ios-user-name"><?php echo htmlspecialchars($admin['full_name']); ?></p>
                            <p class="ios-user-username">@<?php echo htmlspecialchars($admin['username']); ?></p>
                            <p class="ios-user-email"><?php echo $admin['clan_name'] ? htmlspecialchars($admin['clan_name']) : 'No clan assigned'; ?></p>
                        </div>
                        <div class="ios-user-meta">
                            <span class="ios-status-badge <?php echo $admin['status']; ?>"><?php echo ucfirst($admin['status']); ?></span>
                            <span class="ios-role-badge clan_admin">Clan Admin</span>
                            <span class="ios-user-login">
                                <?php echo $admin['last_login'] ? date('M j', strtotime($admin['last_login'])) : 'Never'; ?>
                            </span>
                        </div>
                        <button class="ios-actions-btn" onclick="event.stopPropagation(); openAdminActionModal(<?php echo $admin['id']; ?>)" aria-label="Actions">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- No Results Message (hidden by default) -->
            <div id="noResults" class="ios-empty-state" style="display: none;">
                <div class="ios-empty-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="ios-empty-title">No administrators found</h3>
                <p class="ios-empty-description">No administrators match your search criteria.</p>
            </div>
        <?php endif; ?>

        <!-- iOS Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="ios-pagination-info">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalAdmins); ?> of <?php echo $totalAdmins; ?> administrators
            </div>
            <div class="ios-pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>"
                        class="ios-page-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>"
                        class="ios-page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['status']) ? '&status=' . urlencode($_GET['status']) : ''; ?>"
                        class="ios-page-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Unassigned Clans Section -->
    <?php if (!empty($unassignedClans)): ?>
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-icon orange">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="ios-section-title">
                    <h5>Clans Without Administrators</h5>
                    <p><?php echo count($unassignedClans); ?> clan<?php echo count($unassignedClans) !== 1 ? 's' : ''; ?> need admin assignment</p>
                </div>
            </div>
            <div class="ios-section-body">
                <?php foreach ($unassignedClans as $clan): ?>
                    <a href="<?php echo BASE_URL; ?>clans/edit.php?id=<?php echo encryptId($clan['id']); ?>" class="ios-user-item" style="text-decoration: none;">
                        <div class="ios-user-avatar" style="background: linear-gradient(135deg, #FF9F0A, #e0a800)">
                            <?php echo strtoupper(substr($clan['name'], 0, 1)); ?>
                        </div>
                        <div class="ios-user-content">
                            <p class="ios-user-name"><?php echo htmlspecialchars($clan['name']); ?></p>
                            <p class="ios-user-email">Clan ID: <?php echo $clan['id']; ?></p>
                        </div>
                        <div class="ios-user-meta">
                            <span class="ios-status-badge suspended">No Admin</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- iOS-Style Mobile Menu Modal -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Clan Admins</h3>
        <button class="ios-menu-close" id="iosMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="ios-menu-content">
        <!-- Quick Actions Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <button type="button" class="ios-menu-item" onclick="closeIosMenu(); setTimeout(function(){ document.querySelector('[data-bs-target=\'#promoteUserModal\']').click(); }, 300);">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Promote User</span>
                            <span class="ios-menu-item-desc">Promote a user to clan admin</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <a href="<?php echo BASE_URL; ?>users/create.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Create New User</span>
                            <span class="ios-menu-item-desc">Create a new user account</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <button type="button" class="ios-menu-item" onclick="window.location.reload(); closeIosMenu();">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Refresh</span>
                            <span class="ios-menu-item-desc">Update admin list</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <a href="<?php echo BASE_URL; ?>users/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">All Users</span>
                            <span class="ios-menu-item-desc">View all users</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon teal">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Dashboard</span>
                            <span class="ios-menu-item-desc">Return to dashboard</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <!-- Stats Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Statistics</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Admins</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($totalStats['total_admins']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active</span>
                    <span class="ios-menu-stat-value success"><?php echo number_format($totalStats['active_admins']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Inactive</span>
                    <span class="ios-menu-stat-value danger"><?php echo number_format($totalStats['inactive_admins']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Unassigned Clans</span>
                    <span class="ios-menu-stat-value warning"><?php echo number_format($totalStats['unassigned_clans']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- iOS-Style Action Modal for Individual Admin -->
<div class="ios-menu-backdrop" id="iosActionBackdrop"></div>
<div class="ios-action-modal" id="iosActionModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-action-modal-header">
        <p class="ios-action-modal-title" id="actionModalTitle">Administrator</p>
        <p class="ios-action-modal-subtitle" id="actionModalSubtitle">Choose an action</p>
    </div>
    <div class="ios-action-modal-body">
        <a href="#" class="ios-action-item" id="actionViewProfile">
            <i class="fas fa-eye"></i>
            <span>View Profile</span>
        </a>
        <a href="#" class="ios-action-item primary" id="actionEditUser">
            <i class="fas fa-edit"></i>
            <span>Edit User</span>
        </a>
        <a href="#" class="ios-action-item success" id="actionViewClan" style="display: none;">
            <i class="fas fa-sitemap"></i>
            <span>View Clan</span>
        </a>
        <a href="#" class="ios-action-item warning" id="actionDemote">
            <i class="fas fa-user-minus"></i>
            <span>Demote to User</span>
        </a>
        <a href="#" class="ios-action-item warning" id="actionToggleStatus" style="display: none;">
            <i class="fas fa-ban" id="toggleStatusIcon"></i>
            <span id="toggleStatusText">Deactivate</span>
        </a>
    </div>
    <button class="ios-action-cancel" onclick="closeActionModal()">Cancel</button>
</div>

<!-- Promote User Modal -->
<div class="modal fade" id="promoteUserModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus" style="color: var(--primary); margin-right: 0.5rem;"></i>
                    Promote User to Clan Administrator
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if (empty($potentialAdmins)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div class="alert-content">
                            <div class="alert-title">No Eligible Users</div>
                            <div class="alert-message">
                                There are no eligible users to promote to clan administrator at this time.
                                All clans with users already have an admin assigned.
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <a href="<?php echo BASE_URL; ?>users/create.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i>
                            Create New User
                        </a>
                        <a href="<?php echo BASE_URL; ?>clans/create.php" class="btn btn-outline-primary">
                            <i class="fas fa-sitemap"></i>
                            Create New Clan
                        </a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div class="alert-content">
                            <div class="alert-title">Administrator Privileges</div>
                            <div class="alert-message">
                                The selected user will gain full administrative access to their clan, including user management,
                                access code generation, and payment settings.
                            </div>
                        </div>
                    </div>

                    <form id="promoteUserForm" action="<?php echo BASE_URL; ?>users/admins.php" method="GET" class="enhanced-form">
                        <input type="hidden" name="action" value="promote">

                        <div class="form-group">
                            <label for="userSelect" class="form-label required">Select User to Promote</label>
                            <select class="form-control form-select" id="userSelect" name="id" required>
                                <option value="">Choose a user to promote...</option>
                                <?php
                                $currentClan = '';
                                foreach ($potentialAdmins as $user):
                                    if ($currentClan !== $user['clan_name']):
                                        if ($currentClan !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($user['clan_name']) . '">';
                                        $currentClan = $user['clan_name'];
                                    endif;
                                ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                    </option>
                                <?php endforeach; ?>
                                <?php if (!empty($potentialAdmins)): ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <div class="form-text">Only users from clans without existing administrators are shown</div>
                        </div>

                        <div class="promotion-preview" id="promotionPreview" style="display: none;">
                            <h5 style="color: var(--text-primary); margin-bottom: var(--spacing-3);">Promotion Preview</h5>
                            <div class="preview-content">
                                <div class="preview-item">
                                    <strong>User:</strong> <span id="previewUserName"></span>
                                </div>
                                <div class="preview-item">
                                    <strong>Current Role:</strong> Regular User
                                </div>
                                <div class="preview-item">
                                    <strong>New Role:</strong> <span style="color: var(--primary);">Clan Administrator</span>
                                </div>
                                <div class="preview-item">
                                    <strong>Clan:</strong> <span id="previewClanName"></span>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <?php if (!empty($potentialAdmins)): ?>
                    <button type="submit" form="promoteUserForm" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Promote to Administrator
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<script>
// Store admins data for JavaScript with encrypted IDs
const adminsData = <?php
    $adminsWithEncryptedIds = array_map(function($admin) use ($currentUser) {
        $admin['encrypted_id'] = encryptId($admin['id']);
        $admin['encrypted_clan_id'] = $admin['clan_id'] ? encryptId($admin['clan_id']) : '';
        $admin['can_demote'] = ($admin['id'] != $currentUser->getId());
        $admin['can_toggle'] = ($admin['id'] != $currentUser->getId());
        return $admin;
    }, $admins);
    echo json_encode($adminsWithEncryptedIds);
?>;
let currentAdminId = null;

// iOS-Style Mobile Menu Functions
const iosMenuBackdrop = document.getElementById('iosMenuBackdrop');
const iosMenuModal = document.getElementById('iosMenuModal');
const iosMenuClose = document.getElementById('iosMenuClose');

function openIosMenu() {
    iosMenuBackdrop.classList.add('active');
    iosMenuModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeIosMenu() {
    iosMenuBackdrop.classList.remove('active');
    iosMenuModal.classList.remove('active');
    document.body.style.overflow = '';
}

if (iosMenuClose) {
    iosMenuClose.addEventListener('click', closeIosMenu);
}

if (iosMenuBackdrop) {
    iosMenuBackdrop.addEventListener('click', closeIosMenu);
}

// Swipe down to close main menu
let startY = 0;
let currentY = 0;

if (iosMenuModal) {
    iosMenuModal.addEventListener('touchstart', (e) => {
        startY = e.touches[0].clientY;
    }, { passive: true });

    iosMenuModal.addEventListener('touchmove', (e) => {
        currentY = e.touches[0].clientY;
        const diff = currentY - startY;
        if (diff > 0) {
            iosMenuModal.style.transform = `translateY(${diff}px)`;
        }
    }, { passive: true });

    iosMenuModal.addEventListener('touchend', () => {
        const diff = currentY - startY;
        if (diff > 100) {
            closeIosMenu();
        }
        iosMenuModal.style.transform = '';
        startY = 0;
        currentY = 0;
    });
}

// Action Modal Functions
const iosActionBackdrop = document.getElementById('iosActionBackdrop');
const iosActionModal = document.getElementById('iosActionModal');

function openAdminActionModal(adminId) {
    currentAdminId = adminId;
    const admin = adminsData.find(a => a.id == adminId);

    if (!admin) return;

    document.getElementById('actionModalTitle').textContent = admin.full_name;
    document.getElementById('actionModalSubtitle').textContent = admin.clan_name ? 'Admin of ' + admin.clan_name : 'No clan assigned';

    // Update action links
    document.getElementById('actionViewProfile').href = '<?php echo BASE_URL; ?>users/view.php?id=' + admin.encrypted_id;
    document.getElementById('actionEditUser').href = '<?php echo BASE_URL; ?>users/edit.php?id=' + admin.encrypted_id;

    // View Clan
    const viewClanLink = document.getElementById('actionViewClan');
    if (admin.encrypted_clan_id) {
        viewClanLink.href = '<?php echo BASE_URL; ?>clans/view.php?id=' + admin.encrypted_clan_id;
        viewClanLink.style.display = 'flex';
    } else {
        viewClanLink.style.display = 'none';
    }

    // Demote
    const demoteLink = document.getElementById('actionDemote');
    if (admin.can_demote) {
        demoteLink.href = '<?php echo BASE_URL; ?>users/admins.php?action=demote&id=' + admin.encrypted_id;
        demoteLink.style.display = 'flex';
    } else {
        demoteLink.style.display = 'none';
    }

    // Toggle Status (Deactivate/Activate)
    const toggleLink = document.getElementById('actionToggleStatus');
    const toggleIcon = document.getElementById('toggleStatusIcon');
    const toggleText = document.getElementById('toggleStatusText');
    if (admin.can_toggle) {
        toggleLink.href = '<?php echo BASE_URL; ?>users/admins.php?action=deactivate&id=' + admin.encrypted_id;
        toggleLink.style.display = 'flex';
        if (admin.status === 'active') {
            toggleIcon.className = 'fas fa-ban';
            toggleText.textContent = 'Deactivate';
            toggleLink.className = 'ios-action-item warning';
        } else {
            toggleIcon.className = 'fas fa-check-circle';
            toggleText.textContent = 'Activate';
            toggleLink.className = 'ios-action-item success';
        }
    } else {
        toggleLink.style.display = 'none';
    }

    // Confirm before demote
    demoteLink.onclick = function(e) {
        const clanInfo = admin.clan_name ? ' from ' + admin.clan_name : '';
        if (!confirm('Are you sure you want to demote ' + admin.full_name + clanInfo + ' to regular user?')) {
            e.preventDefault();
        }
    };

    // Confirm before toggle status
    toggleLink.onclick = function(e) {
        const action = admin.status === 'active' ? 'deactivate' : 'activate';
        if (!confirm('Are you sure you want to ' + action + ' ' + admin.full_name + '?')) {
            e.preventDefault();
        }
    };

    iosActionBackdrop.classList.add('active');
    iosActionModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeActionModal() {
    iosActionBackdrop.classList.remove('active');
    iosActionModal.classList.remove('active');
    document.body.style.overflow = '';
    currentAdminId = null;
}

iosActionBackdrop.addEventListener('click', closeActionModal);

// Swipe down to close action modal
iosActionModal.addEventListener('touchstart', (e) => {
    startY = e.touches[0].clientY;
}, { passive: true });

iosActionModal.addEventListener('touchmove', (e) => {
    currentY = e.touches[0].clientY;
    const diff = currentY - startY;
    if (diff > 0) {
        iosActionModal.style.transform = `translateY(${diff}px)`;
    }
}, { passive: true });

iosActionModal.addEventListener('touchend', () => {
    const diff = currentY - startY;
    if (diff > 100) {
        closeActionModal();
    }
    iosActionModal.style.transform = '';
    startY = 0;
    currentY = 0;
});

// Search functionality
const searchInput = document.getElementById('adminSearch');
const clearBtn = document.getElementById('clearSearch');
const adminsList = document.getElementById('adminsList');
const noResults = document.getElementById('noResults');

if (searchInput) {
    let searchTimeout;

    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();

        if (clearBtn) {
            clearBtn.classList.toggle('visible', searchTerm.length > 0);
        }

        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            filterAdmins(searchTerm);
        }, 300);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            this.classList.remove('visible');
            filterAdmins('');
            searchInput.focus();
        });
    }

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            if (clearBtn) clearBtn.classList.remove('visible');
            filterAdmins('');
        }
    });
}

function filterAdmins(searchTerm) {
    if (!adminsList) return;

    const rows = adminsList.querySelectorAll('.admin-row');
    let visibleCount = 0;

    rows.forEach(function(row) {
        if (!searchTerm) {
            row.style.display = '';
            visibleCount++;
            return;
        }

        const name = row.querySelector('.ios-user-name')?.textContent.toLowerCase() || '';
        const username = row.querySelector('.ios-user-username')?.textContent.toLowerCase() || '';
        const email = row.querySelector('.ios-user-email')?.textContent.toLowerCase() || '';

        const matches = name.includes(searchTerm) ||
                      username.includes(searchTerm) ||
                      email.includes(searchTerm);

        if (matches) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    if (noResults) {
        if (visibleCount === 0 && rows.length > 0) {
            adminsList.style.display = 'none';
            noResults.style.display = 'block';
        } else {
            adminsList.style.display = '';
            noResults.style.display = 'none';
        }
    }
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        });
    }, 5000);
});

console.log('Clan Admins page initialized successfully');
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>