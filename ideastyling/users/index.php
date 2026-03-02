<?php

/**
 * Gate Wey Access Management System
 * User Management Page - Dasher UI Enhanced
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';

// Set page title
$pageTitle = 'User Management';

// Check if user is logged in and has permission
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

// Get database instance
$db = Database::getInstance();

// Initialize variables
$error = '';
$success = '';

// Check for flash messages from session (for redirected actions like delete)
if (isset($_SESSION['flash_messages']) && is_array($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
    // Get the most recent message (last item in array)
    $lastMessage = end($_SESSION['flash_messages']);

    if ($lastMessage['type'] === 'success') {
        $success = $lastMessage['message'];
    } elseif ($lastMessage['type'] === 'error') {
        $error = $lastMessage['message'];
    }

    // Clear all flash messages after displaying
    unset($_SESSION['flash_messages']);
}

// Process user activation/deactivation if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && isset($_POST['user_id'])) {
    $userId = $_POST['user_id'];
    $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';

    $canUpdate = false;
    $targetUser = new User();

    if ($targetUser->loadById($userId)) {
        if ($currentUser->isSuperAdmin()) {
            $canUpdate = true;
        } elseif (
            $currentUser->isClanAdmin() && $targetUser->getClanId() == $currentUser->getClanId()
            && !$targetUser->isClanAdmin()
        ) {
            $canUpdate = true;
        }
    }

    if ($canUpdate) {
        if ($targetUser->updateStatus($userId, $newStatus)) {
            $statusText = $newStatus === 'active' ? 'activated' : 'deactivated';
            $success = "User has been $statusText successfully.";
        } else {
            $error = "Failed to update user status.";
        }
    } else {
        $error = "You don't have permission to update this user's status.";
    }
}



// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get filter parameters
$filters = [];

if (isset($_GET['role']) && !empty($_GET['role'])) {
    $filters['role'] = $_GET['role'];
}

if (isset($_GET['status']) && in_array($_GET['status'], ['active', 'inactive', 'suspended'])) {
    $filters['status'] = $_GET['status'];
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $filters['search'] = $_GET['search'];
}

if ($currentUser->isClanAdmin()) {
    $filters['clan_id'] = $currentUser->getClanId();
} elseif ($currentUser->isSuperAdmin() && isset($_GET['clan_id']) && !empty($_GET['clan_id'])) {
    $filters['clan_id'] = decryptId($_GET['clan_id']);
}

// Get users based on filters
$user = new User();
$users = $user->getUsers($filters, $limit, $offset);

// Get total user count for pagination
$countQuery = "SELECT COUNT(*) as count FROM users WHERE 1=1";
$countParams = [];

if (isset($filters['role'])) {
    $countQuery .= " AND role = ?";
    $countParams[] = $filters['role'];
}

if (isset($filters['status'])) {
    $countQuery .= " AND status = ?";
    $countParams[] = $filters['status'];
}

if (isset($filters['clan_id'])) {
    $countQuery .= " AND clan_id = ?";
    $countParams[] = $filters['clan_id'];
}

if (isset($filters['search'])) {
    $countQuery .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $searchTerm = "%{$filters['search']}%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
}

$totalUsers = $db->fetchOne($countQuery, $countParams)['count'];
$totalPages = ceil($totalUsers / $limit);

// Get clans for filter (only for super admin)
$clans = [];
if ($currentUser->isSuperAdmin()) {
    $clans = $db->fetchAll("SELECT id, name FROM clans ORDER BY name");
}

// Get statistics
$activeUsersCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'active'" .
    ($currentUser->isClanAdmin() ? " AND clan_id = {$currentUser->getClanId()}" : ""))['count'] ?? 0;
$inactiveUsersCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'inactive'" .
    ($currentUser->isClanAdmin() ? " AND clan_id = {$currentUser->getClanId()}" : ""))['count'] ?? 0;
$guardUsersCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE role = 'guard'" .
    ($currentUser->isClanAdmin() ? " AND clan_id = {$currentUser->getClanId()}" : ""))['count'] ?? 0;

// Include header
include_once '../includes/header.php';

// Include sidebar
include_once '../includes/sidebar.php';
?>

<!-- iOS-Style User Management Styles -->
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

    .ios-role-badge.super_admin {
        background: rgba(255, 69, 58, 0.15);
        color: var(--ios-red);
    }

    .ios-role-badge.clan_admin {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    .ios-role-badge.guard {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .ios-role-badge.user {
        background: rgba(48, 209, 88, 0.15);
        color: var(--ios-green);
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
    <!-- Content Header (Hidden on Mobile) -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="content-title">
                    <i class="fas fa-users me-2"></i>
                    User Management
                </h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Users</li>
                    </ol>
                </nav>
            </div>
            <div class="content-actions">
                <a href="<?php echo BASE_URL; ?>users/create.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add User</span>
                </a>
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
        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Active Users</div>
                <div class="stat-value"><?php echo number_format($activeUsersCount); ?></div>
                <div class="stat-detail"><?php echo $totalUsers > 0 ? round(($activeUsersCount / $totalUsers) * 100) : 0; ?>% of total</div>
            </div>
        </div>

        <div class="stat-card stat-danger">
            <div class="stat-icon">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Inactive Users</div>
                <div class="stat-value"><?php echo number_format($inactiveUsersCount); ?></div>
                <div class="stat-detail">Require activation</div>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Guards</div>
                <div class="stat-value"><?php echo number_format($guardUsersCount); ?></div>
                <div class="stat-detail">Security personnel</div>
            </div>
        </div>

        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                <div class="stat-detail">Across all roles</div>
            </div>
        </div>
    </div>

    <!-- Users Section -->
    <div class="ios-section-card">
        <div class="ios-section-header">
            <div class="ios-section-icon blue">
                <i class="fas fa-users"></i>
            </div>
            <div class="ios-section-title">
                <h5>All Users</h5>
                <p><?php echo number_format($totalUsers); ?> user<?php echo $totalUsers != 1 ? 's' : ''; ?> found</p>
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
                    id="userSearch"
                    class="ios-search-input"
                    placeholder="Search users..."
                    autocomplete="off"
                >
                <button class="ios-search-clear" id="clearSearch">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <!-- Filter Pills -->
        <div class="ios-filter-pills">
            <a href="<?php echo BASE_URL; ?>users/<?php echo isset($_GET['clan_id']) ? '?clan_id=' . $_GET['clan_id'] : ''; ?>"
               class="ios-filter-pill <?php echo !isset($filters['status']) && !isset($filters['role']) ? 'active' : ''; ?>">
                All
                <span class="count"><?php echo number_format($totalUsers); ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>users/?status=active<?php echo isset($_GET['clan_id']) ? '&clan_id=' . $_GET['clan_id'] : ''; ?>"
               class="ios-filter-pill <?php echo (isset($filters['status']) && $filters['status'] === 'active') ? 'active' : ''; ?>">
                Active
                <span class="count"><?php echo $activeUsersCount; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>users/?status=inactive<?php echo isset($_GET['clan_id']) ? '&clan_id=' . $_GET['clan_id'] : ''; ?>"
               class="ios-filter-pill <?php echo (isset($filters['status']) && $filters['status'] === 'inactive') ? 'active' : ''; ?>">
                Inactive
                <span class="count"><?php echo $inactiveUsersCount; ?></span>
            </a>
            <a href="<?php echo BASE_URL; ?>users/?role=guard<?php echo isset($_GET['clan_id']) ? '&clan_id=' . $_GET['clan_id'] : ''; ?>"
               class="ios-filter-pill <?php echo (isset($filters['role']) && $filters['role'] === 'guard') ? 'active' : ''; ?>">
                Guards
                <span class="count"><?php echo $guardUsersCount; ?></span>
            </a>
            <?php if ($currentUser->isSuperAdmin()): ?>
                <a href="<?php echo BASE_URL; ?>users/?role=clan_admin"
                   class="ios-filter-pill <?php echo (isset($filters['role']) && $filters['role'] === 'clan_admin') ? 'active' : ''; ?>">
                    Clan Admins
                </a>
                <a href="<?php echo BASE_URL; ?>users/?role=super_admin"
                   class="ios-filter-pill <?php echo (isset($filters['role']) && $filters['role'] === 'super_admin') ? 'active' : ''; ?>">
                    Super Admins
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($users)): ?>
            <!-- Empty State -->
            <div class="ios-empty-state">
                <div class="ios-empty-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="ios-empty-title">No users found</h3>
                <p class="ios-empty-description">
                    No users match your current filters.
                </p>
            </div>
        <?php else: ?>
            <!-- Users List -->
            <div class="ios-section-body" id="usersList">
                <?php foreach ($users as $userData): ?>
                    <div class="ios-user-item user-row"
                         data-user-id="<?php echo $userData['id']; ?>"
                         onclick="openUserActionModal(<?php echo $userData['id']; ?>)">
                        <div class="ios-user-avatar" style="background: <?php echo getUserAvatarGradient($userData['role']); ?>">
                            <?php echo strtoupper(substr($userData['full_name'], 0, 1)); ?>
                        </div>
                        <div class="ios-user-content">
                            <p class="ios-user-name"><?php echo htmlspecialchars($userData['full_name']); ?></p>
                            <p class="ios-user-username">@<?php echo htmlspecialchars($userData['username']); ?></p>
                            <p class="ios-user-email"><?php echo htmlspecialchars($userData['email']); ?></p>
                        </div>
                        <div class="ios-user-meta">
                            <span class="ios-status-badge <?php echo $userData['status']; ?>"><?php echo ucfirst($userData['status']); ?></span>
                            <span class="ios-role-badge <?php echo $userData['role']; ?>"><?php echo getUserRoleLabel($userData['role']); ?></span>
                            <span class="ios-user-login">
                                <?php echo $userData['last_login'] ? date('M j', strtotime($userData['last_login'])) : 'Never'; ?>
                            </span>
                        </div>
                        <button class="ios-actions-btn" onclick="event.stopPropagation(); openUserActionModal(<?php echo $userData['id']; ?>)" aria-label="Actions">
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
                <h3 class="ios-empty-title">No users found</h3>
                <p class="ios-empty-description">No users match your search criteria.</p>
            </div>
        <?php endif; ?>

        <!-- iOS Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="ios-pagination-info">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalUsers); ?> of <?php echo $totalUsers; ?> users
            </div>
            <div class="ios-pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo buildQueryString(); ?>"
                        class="ios-page-btn">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?page=<?php echo $i; ?><?php echo buildQueryString(); ?>"
                        class="ios-page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo buildQueryString(); ?>"
                        class="ios-page-btn">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- iOS-Style Mobile Menu Modal -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">User Management</h3>
        <button class="ios-menu-close" id="iosMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="ios-menu-content">
        <!-- Quick Actions Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>users/create.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Add User</span>
                            <span class="ios-menu-item-desc">Create a new user</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php if ($currentUser->isSuperAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>users/admins.php" class="ios-menu-item">
                        <div class="ios-menu-item-left">
                            <div class="ios-menu-item-icon orange">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="ios-menu-item-content">
                                <span class="ios-menu-item-label">Manage Admins</span>
                                <span class="ios-menu-item-desc">Clan admin management</span>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </a>
                <?php endif; ?>
                <button type="button" class="ios-menu-item" onclick="window.location.reload(); closeIosMenu();">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Refresh</span>
                            <span class="ios-menu-item-desc">Update user list</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple">
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
                    <span class="ios-menu-stat-label">Total Users</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($totalUsers); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active</span>
                    <span class="ios-menu-stat-value success"><?php echo number_format($activeUsersCount); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Inactive</span>
                    <span class="ios-menu-stat-value danger"><?php echo number_format($inactiveUsersCount); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Guards</span>
                    <span class="ios-menu-stat-value warning"><?php echo number_format($guardUsersCount); ?></span>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter by Role</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>users/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">All Users</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>users/?role=user" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Regular Users</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>users/?role=guard" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Guards (<?php echo $guardUsersCount; ?>)</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php if ($currentUser->isSuperAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>users/?role=clan_admin" class="ios-menu-item">
                        <div class="ios-menu-item-left">
                            <div class="ios-menu-item-icon teal">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div class="ios-menu-item-content">
                                <span class="ios-menu-item-label">Clan Admins</span>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($currentUser->isSuperAdmin() && !empty($clans)): ?>
            <!-- Clan Filter Section -->
            <div class="ios-menu-section">
                <div class="ios-menu-section-title">Filter by Clan</div>
                <div class="ios-menu-card">
                    <a href="<?php echo BASE_URL; ?>users/" class="ios-menu-item">
                        <div class="ios-menu-item-left">
                            <div class="ios-menu-item-icon blue">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div class="ios-menu-item-content">
                                <span class="ios-menu-item-label">All Clans</span>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </a>
                    <?php foreach ($clans as $clan): ?>
                        <a href="<?php echo BASE_URL; ?>users/?clan_id=<?php echo $clan['id']; ?>" class="ios-menu-item">
                            <div class="ios-menu-item-left">
                                <div class="ios-menu-item-icon purple">
                                    <i class="fas fa-flag"></i>
                                </div>
                                <div class="ios-menu-item-content">
                                    <span class="ios-menu-item-label"><?php echo htmlspecialchars($clan['name']); ?></span>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- iOS-Style Action Modal for Individual User -->
<div class="ios-menu-backdrop" id="iosActionBackdrop"></div>
<div class="ios-action-modal" id="iosActionModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-action-modal-header">
        <p class="ios-action-modal-title" id="actionModalTitle">User</p>
        <p class="ios-action-modal-subtitle" id="actionModalSubtitle">Choose an action</p>
    </div>
    <div class="ios-action-modal-body">
        <a href="#" class="ios-action-item" id="actionViewUser">
            <i class="fas fa-eye"></i>
            <span>View Profile</span>
        </a>
        <a href="#" class="ios-action-item primary" id="actionEditUser" style="display: none;">
            <i class="fas fa-edit"></i>
            <span>Edit User</span>
        </a>
        <form method="post" action="" id="toggleStatusForm" style="margin: 0; display: none;">
            <input type="hidden" name="user_id" id="toggleUserId" value="">
            <input type="hidden" name="status" id="toggleCurrentStatus" value="">
            <button type="submit" name="toggle_status" class="ios-action-item warning" id="actionToggleStatus">
                <i class="fas fa-ban" id="toggleStatusIcon"></i>
                <span id="toggleStatusText">Deactivate</span>
            </button>
        </form>
    </div>
    <button class="ios-action-cancel" onclick="closeActionModal()">Cancel</button>
</div>

<script>
// Store users data for JavaScript with encrypted IDs
const usersData = <?php
    $usersWithEncryptedIds = array_map(function($userData) use ($currentUser) {
        $userData['encrypted_id'] = encryptId($userData['id']);

        // Determine permissions
        $canEdit = false;
        $canToggle = false;

        if ($currentUser->isSuperAdmin()) {
            $canEdit = true;
            if ($userData['id'] != $currentUser->getId()) {
                $canToggle = true;
            }
        } elseif ($currentUser->isClanAdmin() && $userData['clan_id'] == $currentUser->getClanId() && $userData['role'] != 'clan_admin') {
            $canEdit = true;
            if ($userData['id'] != $currentUser->getId()) {
                $canToggle = true;
            }
        }

        $userData['can_edit'] = $canEdit;
        $userData['can_toggle'] = $canToggle;
        return $userData;
    }, $users);
    echo json_encode($usersWithEncryptedIds);
?>;
let currentUserId = null;

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

function openUserActionModal(userId) {
    currentUserId = userId;
    const user = usersData.find(u => u.id == userId);

    if (!user) return;

    document.getElementById('actionModalTitle').textContent = user.full_name;
    document.getElementById('actionModalSubtitle').textContent = '@' + user.username;

    // Update action links
    document.getElementById('actionViewUser').href = '<?php echo BASE_URL; ?>users/view.php?id=' + user.encrypted_id;

    // Edit
    const editLink = document.getElementById('actionEditUser');
    if (user.can_edit) {
        editLink.href = '<?php echo BASE_URL; ?>users/edit.php?id=' + user.encrypted_id;
        editLink.style.display = 'flex';
    } else {
        editLink.style.display = 'none';
    }

    // Toggle status
    const toggleForm = document.getElementById('toggleStatusForm');
    if (user.can_toggle) {
        toggleForm.style.display = 'block';
        document.getElementById('toggleUserId').value = user.id;
        document.getElementById('toggleCurrentStatus').value = user.status;

        const toggleIcon = document.getElementById('toggleStatusIcon');
        const toggleText = document.getElementById('toggleStatusText');
        const toggleBtn = document.getElementById('actionToggleStatus');

        if (user.status === 'active') {
            toggleIcon.className = 'fas fa-ban';
            toggleText.textContent = 'Deactivate';
            toggleBtn.className = 'ios-action-item warning';
        } else {
            toggleIcon.className = 'fas fa-check';
            toggleText.textContent = 'Activate';
            toggleBtn.className = 'ios-action-item success';
        }
    } else {
        toggleForm.style.display = 'none';
    }

    // Confirm before toggle
    document.getElementById('toggleStatusForm').onsubmit = function() {
        const action = user.status === 'active' ? 'deactivate' : 'activate';
        return confirm('Are you sure you want to ' + action + ' this user?');
    };

    iosActionBackdrop.classList.add('active');
    iosActionModal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeActionModal() {
    iosActionBackdrop.classList.remove('active');
    iosActionModal.classList.remove('active');
    document.body.style.overflow = '';
    currentUserId = null;
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
const searchInput = document.getElementById('userSearch');
const clearBtn = document.getElementById('clearSearch');
const usersList = document.getElementById('usersList');
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
            filterUsers(searchTerm);
        }, 300);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            this.classList.remove('visible');
            filterUsers('');
            searchInput.focus();
        });
    }

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            if (clearBtn) clearBtn.classList.remove('visible');
            filterUsers('');
        }
    });
}

function filterUsers(searchTerm) {
    if (!usersList) return;

    const rows = usersList.querySelectorAll('.user-row');
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
            usersList.style.display = 'none';
            noResults.style.display = 'block';
        } else {
            usersList.style.display = '';
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

console.log('User Management page initialized successfully');
</script>

<?php
/**
 * Helper function to build query string for pagination
 */
function buildQueryString()
{
    $params = [];
    if (isset($_GET['role'])) $params[] = 'role=' . urlencode($_GET['role']);
    if (isset($_GET['status'])) $params[] = 'status=' . urlencode($_GET['status']);
    if (isset($_GET['search'])) $params[] = 'search=' . urlencode($_GET['search']);
    if (isset($_GET['clan_id'])) $params[] = 'clan_id=' . urlencode($_GET['clan_id']);
    return !empty($params) ? '&' . implode('&', $params) : '';
}

/**
 * Get avatar gradient for user role (iOS colors)
 */
function getUserAvatarGradient($role)
{
    switch ($role) {
        case 'super_admin':
            return 'linear-gradient(135deg, #FF453A, #c82333)';
        case 'clan_admin':
            return 'linear-gradient(135deg, #0A84FF, #0a58ca)';
        case 'guard':
            return 'linear-gradient(135deg, #FF9F0A, #e0a800)';
        default:
            return 'linear-gradient(135deg, #30D158, #007b52)';
    }
}

/**
 * Get role label
 */
function getUserRoleLabel($role)
{
    $labels = [
        'super_admin' => 'Super Admin',
        'clan_admin' => 'Clan Admin',
        'guard' => 'Guard',
        'user' => 'User'
    ];
    return $labels[$role] ?? 'User';
}

/**
 * Get role badge HTML
 */
function getUserRoleBadge($role)
{
    $badges = [
        'super_admin' => '<div class="table-status"><div class="table-status-dot" style="background-color: var(--danger);"></div><span style="color: var(--danger);">Super Admin</span></div>',
        'clan_admin' => '<div class="table-status"><div class="table-status-dot" style="background-color: var(--primary);"></div><span style="color: var(--primary);">Clan Admin</span></div>',
        'guard' => '<div class="table-status"><div class="table-status-dot" style="background-color: var(--warning);"></div><span style="color: var(--warning);">Guard</span></div>',
        'user' => '<div class="table-status"><div class="table-status-dot status-active"></div><span style="color: var(--success);">User</span></div>'
    ];
    return $badges[$role] ?? $badges['user'];
}

/**
 * Get status badge HTML
 */
function getUserStatusBadge($status)
{
    $badges = [
        'active' => '<div class="table-status"><div class="table-status-dot status-active"></div><span style="color: var(--success);">Active</span></div>',
        'inactive' => '<div class="table-status"><div class="table-status-dot status-inactive"></div><span style="color: var(--danger);">Inactive</span></div>',
        'suspended' => '<div class="table-status"><div class="table-status-dot" style="background-color: var(--warning);"></div><span style="color: var(--warning);">Suspended</span></div>'
    ];
    return $badges[$status] ?? '<div class="table-status"><div class="table-status-dot" style="background-color: var(--text-muted);"></div><span style="color: var(--text-muted);">Unknown</span></div>';
}

// Include footer
include_once '../includes/footer.php';
?>
