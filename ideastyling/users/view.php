<?php

/**
 * Gate Wey Access Management System
 * View User Details Page - Dasher UI Enhanced
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';

// Set page title
$pageTitle = 'User Details';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
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

// Check if user ID is provided
$userId = isset($_GET['id']) ? decryptId($_GET['id']) : null;
if (!$userId || !is_numeric($userId) || $userId <= 0) {
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Load the user to view
$viewUser = new User();
if (!$viewUser->loadById($userId)) {
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Check permissions
$hasViewPermission = false;
if ($currentUser->isSuperAdmin()) {
    $hasViewPermission = true;
} elseif ($currentUser->isClanAdmin() && $viewUser->getClanId() == $currentUser->getClanId()) {
    $hasViewPermission = true;
} elseif ($currentUser->getId() == $viewUser->getId()) {
    $hasViewPermission = true;
}

if (!$hasViewPermission) {
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Get database instance
$db = Database::getInstance();

// Get clan details if applicable
$clanDetails = null;
if ($viewUser->getClanId()) {
    $clan = new Clan();
    if ($clan->loadById($viewUser->getClanId())) {
        $clanDetails = [
            'id' => $clan->getId(),
            'name' => $clan->getName()
        ];
    }
}
// Get household details if applicable
$householdDetails = null;
$isHouseholdHead = false;
$userHouseholdInfo = $db->fetchOne(
    "SELECT household_id, is_household_head FROM users WHERE id = ?",
    [$userId]
);

if ($userHouseholdInfo && $userHouseholdInfo['household_id']) {
    $household = $db->fetchOne(
        "SELECT id, name, address FROM households WHERE id = ?",
        [$userHouseholdInfo['household_id']]
    );

    if ($household) {
        $householdDetails = [
            'id' => $household['id'],
            'name' => $household['name'],
            'address' => $household['address'] ?? 'No address provided'
        ];
        $isHouseholdHead = (bool)$userHouseholdInfo['is_household_head'];
    }
}
// Get activity statistics
$accessCodesCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM access_codes WHERE created_by = ?",
    [$userId]
)['count'] ?? 0;

$accessLogsCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM access_logs WHERE guard_id = ?",
    [$userId]
)['count'] ?? 0;

// Get user dates
$userDates = $db->fetchOne(
    "SELECT last_login, created_at FROM users WHERE id = ?",
    [$userId]
);

// Get recent activity logs
$activityLogs = $db->fetchAll(
    "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 5",
    [$userId]
);

// Check if can edit/deactivate
$canEdit = false;
if ($currentUser->isSuperAdmin()) {
    $canEdit = true;
} elseif ($currentUser->isClanAdmin() && $viewUser->getClanId() == $currentUser->getClanId() && !$viewUser->isClanAdmin()) {
    $canEdit = true;
}

$canDeactivate = $canEdit && $viewUser->getId() != $currentUser->getId();

// Initialize flash messages
$error = '';
$success = '';
if (isset($_SESSION['flash_messages']) && is_array($_SESSION['flash_messages']) && !empty($_SESSION['flash_messages'])) {
    $lastMessage = end($_SESSION['flash_messages']);
    if ($lastMessage['type'] === 'success') {
        $success = $lastMessage['message'];
    } elseif ($lastMessage['type'] === 'error') {
        $error = $lastMessage['message'];
    }
    unset($_SESSION['flash_messages']);
}

// Handle deactivate/activate toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status']) && $canDeactivate) {
    $newStatus = $viewUser->getStatus() === 'active' ? 'inactive' : 'active';
    if ($viewUser->updateStatus($userId, $newStatus)) {
        $statusText = $newStatus === 'active' ? 'activated' : 'deactivated';
        $success = "User has been $statusText successfully.";
        // Reload user data
        $viewUser->loadById($userId);
    } else {
        $error = "Failed to update user status.";
    }
}

// Include header
include_once '../includes/header.php';

// Include sidebar
include_once '../includes/sidebar.php';
?>

<!-- iOS-Style User Details Styles -->
<style>
:root {
    --ios-red: #FF453A;
    --ios-orange: #FF9F0A;
    --ios-green: #30D158;
    --ios-blue: #0A84FF;
    --ios-purple: #BF5AF2;
    --ios-teal: #64D2FF;
}

/* iOS Grid Layout */
.ios-grid {
    display: grid;
    grid-template-columns: 350px 1fr;
    gap: 20px;
}

/* iOS Section Card */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    margin-bottom: 20px;
    overflow: hidden;
}

.ios-section-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 20px;
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}

.ios-section-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.ios-section-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
.ios-section-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
.ios-section-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
.ios-section-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
.ios-section-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
.ios-section-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }

.ios-section-title { flex: 1; }

.ios-section-title h5 {
    font-size: 17px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.ios-section-title p {
    font-size: 13px;
    color: var(--text-secondary);
    margin: 0;
}

.ios-section-body { padding: 20px; }
.ios-section-body.no-padding { padding: 0; }

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
    transition: all 0.2s ease;
    margin-left: auto;
    flex-shrink: 0;
}

.ios-options-btn:hover { background: var(--border-color); }
.ios-options-btn:active { transform: scale(0.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* iOS User Avatar */
.ios-user-avatar-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0 auto 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}

.ios-user-fullname {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px 0;
    text-align: center;
}

.ios-user-handle {
    font-size: 15px;
    color: var(--text-secondary);
    margin: 0 0 12px 0;
    text-align: center;
}

.ios-user-badges {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: wrap;
}

.ios-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.ios-badge-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: currentColor;
}

.ios-badge.status-active { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
.ios-badge.status-inactive { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
.ios-badge.status-suspended { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }

.ios-badge.role-super_admin { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
.ios-badge.role-clan_admin { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
.ios-badge.role-guard { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
.ios-badge.role-user { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }

/* iOS Info List */
.ios-info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.ios-info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid var(--border-color);
}

.ios-info-item:last-child { border-bottom: none; }

.ios-info-label {
    font-size: 15px;
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: 10px;
}

.ios-info-label i { width: 20px; text-align: center; }
.ios-info-label i.icon-blue { color: var(--ios-blue); }
.ios-info-label i.icon-green { color: var(--ios-green); }
.ios-info-label i.icon-orange { color: var(--ios-orange); }
.ios-info-label i.icon-purple { color: var(--ios-purple); }
.ios-info-label i.icon-red { color: var(--ios-red); }
.ios-info-label i.icon-teal { color: var(--ios-teal); }

.ios-info-value {
    font-size: 15px;
    font-weight: 500;
    color: var(--text-primary);
    text-align: right;
    max-width: 60%;
    word-break: break-word;
}

.ios-info-value a {
    color: var(--ios-blue);
    text-decoration: none;
}

.ios-info-value a:hover { text-decoration: underline; }

/* iOS Activity Item */
.ios-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.15s ease;
}

.ios-activity-item:last-child { border-bottom: none; }
.ios-activity-item:hover { background: var(--bg-subtle); }

.ios-activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 16px;
    background: rgba(10, 132, 255, 0.15);
    color: var(--ios-blue);
}

.ios-activity-content { flex: 1; min-width: 0; }

.ios-activity-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.ios-activity-desc {
    font-size: 14px;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.4;
}

.ios-activity-time {
    font-size: 13px;
    color: var(--text-muted);
    flex-shrink: 0;
    text-align: right;
}

/* iOS Permission Item */
.ios-permission-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border-color);
}

.ios-permission-item:last-child { border-bottom: none; }

.ios-permission-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    background: rgba(48, 209, 88, 0.15);
    color: var(--ios-green);
    flex-shrink: 0;
}

.ios-permission-label {
    font-size: 15px;
    font-weight: 500;
    color: var(--text-primary);
}

/* iOS Empty State */
.ios-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-secondary);
}

.ios-empty-state i {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.ios-empty-state h4 {
    font-size: 17px;
    font-weight: 600;
    color: var(--text-primary);
    margin: 0 0 8px 0;
}

.ios-empty-state p { font-size: 14px; margin: 0; }

/* iOS Delete Section */
.ios-delete-section {
    padding: 20px;
    border-top: 1px solid var(--border-color);
}

.ios-delete-warning {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px;
    background: rgba(255, 69, 58, 0.1);
    border: 1px solid rgba(255, 69, 58, 0.2);
    border-radius: 12px;
    margin-bottom: 16px;
}

.ios-delete-warning i { color: var(--ios-red); font-size: 18px; flex-shrink: 0; margin-top: 2px; }

.ios-delete-warning-text {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.5;
}

.ios-delete-warning-text strong { color: var(--ios-red); }

.ios-btn-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    background: var(--ios-red);
    color: white;
    transition: all 0.2s ease;
    text-decoration: none;
}

.ios-btn-delete:hover { opacity: 0.9; }
.ios-btn-delete:active { transform: scale(0.98); }

/* iOS Bottom Sheet Modal */
.ios-menu-backdrop {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 9998;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
}

.ios-menu-backdrop.active { opacity: 1; visibility: visible; }

.ios-menu-modal {
    position: fixed;
    bottom: 0; left: 0; right: 0;
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

.ios-menu-modal.active { transform: translateY(0); }

.ios-menu-handle {
    width: 36px; height: 5px;
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
    font-size: 17px; font-weight: 600;
    color: var(--text-primary); margin: 0;
}

.ios-menu-close {
    width: 30px; height: 30px;
    border-radius: 50%;
    background: var(--bg-secondary);
    border: none;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary);
    cursor: pointer;
    transition: background 0.2s ease;
}

.ios-menu-close:hover { background: var(--border-color); }

.ios-menu-content {
    padding: 16px;
    overflow-y: auto;
    flex: 1;
    -webkit-overflow-scrolling: touch;
}

.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }

.ios-menu-section-title {
    font-size: 13px; font-weight: 600;
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
    border-left: none; border-right: none; border-top: none;
    font-family: inherit; font-size: inherit; text-align: left;
}

button.ios-menu-item { border: none; border-bottom: 1px solid var(--border-color); }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }

.ios-menu-item-left {
    display: flex; align-items: center;
    gap: 12px; flex: 1; min-width: 0;
}

.ios-menu-item-icon {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}

.ios-menu-item-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
.ios-menu-item-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
.ios-menu-item-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
.ios-menu-item-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
.ios-menu-item-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }

.ios-menu-item-content { flex: 1; min-width: 0; }

.ios-menu-item-label {
    font-size: 15px; font-weight: 500;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

.ios-menu-item-desc {
    font-size: 12px; color: var(--text-secondary); margin-top: 2px;
}

.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }

.ios-menu-stat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
}

.ios-menu-stat-row:last-child { border-bottom: none; }

.ios-menu-stat-label { font-size: 15px; color: var(--text-primary); }

.ios-menu-stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); }
.ios-menu-stat-value.success { color: var(--ios-green); }
.ios-menu-stat-value.warning { color: var(--ios-orange); }
.ios-menu-stat-value.danger { color: var(--ios-red); }

/* Mobile Styles */
@media (max-width: 992px) {
    .ios-grid { grid-template-columns: 1fr; }
    .ios-options-btn { display: flex; }
}

@media (max-width: 768px) {
    .content-header { display: none !important; }

    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 16px; }
    .ios-section-icon { width: 40px; height: 40px; font-size: 16px; }
    .ios-section-body { padding: 16px; }

    .ios-user-avatar-large {
        width: 80px;
        height: 80px;
        font-size: 2rem;
    }

    .ios-user-fullname { font-size: 18px; }

    .ios-info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .ios-info-value { max-width: 100%; text-align: left; }

    .ios-activity-item { padding: 14px 16px; }
    .ios-activity-time { display: none; }
}

@media (max-width: 480px) {
    .ios-section-header { padding: 14px; gap: 12px; }
    .ios-section-icon { width: 36px; height: 36px; font-size: 14px; border-radius: 10px; }
    .ios-section-title h5 { font-size: 15px; }
    .ios-section-title p { font-size: 12px; }
    .ios-section-body { padding: 14px; }

    .ios-user-avatar-large {
        width: 70px;
        height: 70px;
        font-size: 1.75rem;
    }

    .ios-user-fullname { font-size: 16px; }

    .ios-badge { padding: 5px 10px; font-size: 12px; }
    .ios-badge-dot { width: 6px; height: 6px; }

    .ios-info-item { padding: 12px 0; }
    .ios-info-label { font-size: 13px; }
    .ios-info-value { font-size: 14px; }

    .ios-options-btn { width: 32px; height: 32px; }
    .ios-options-btn i { font-size: 14px; }
}
</style>

<!-- Dasher UI Content Area -->
<div class="content">
    <!-- Content Header (Hidden on Mobile) -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="content-title">User Details</h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>users/" class="breadcrumb-link">Users</a>
                        </li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($viewUser->getFullName()); ?></li>
                    </ol>
                </nav>
            </div>
            <div class="content-actions">
                <?php if ($canEdit): ?>
                    <a href="<?php echo BASE_URL; ?>users/edit.php?id=<?php echo encryptId($userId); ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i>
                        <span>Edit User</span>
                    </a>
                <?php endif; ?>
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

    <!-- iOS Grid Layout -->
    <div class="ios-grid">
        <!-- Left Column - User Profile -->
        <div>
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>User Profile</h5>
                        <p>Details and information</p>
                    </div>
                    <button class="ios-options-btn" id="iosOptionsBtn" aria-label="Open menu">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>

                <div class="ios-section-body">
                    <!-- User Avatar and Name -->
                    <div class="ios-user-avatar-large" style="background: <?php echo getUserAvatarGradient($viewUser->getRole()); ?>">
                        <?php echo strtoupper(substr($viewUser->getFullName() ?: $viewUser->getUsername(), 0, 1)); ?>
                    </div>
                    <h3 class="ios-user-fullname"><?php echo htmlspecialchars($viewUser->getFullName()); ?></h3>
                    <p class="ios-user-handle">@<?php echo htmlspecialchars($viewUser->getUsername()); ?></p>

                    <div class="ios-user-badges">
                        <span class="ios-badge status-<?php echo $viewUser->getStatus(); ?>">
                            <span class="ios-badge-dot"></span>
                            <?php echo ucfirst($viewUser->getStatus()); ?>
                        </span>
                        <span class="ios-badge role-<?php echo $viewUser->getRole(); ?>">
                            <?php echo getUserRoleLabel($viewUser->getRole()); ?>
                        </span>
                    </div>
                </div>

                <div class="ios-section-body" style="border-top: 1px solid var(--border-color);">
                    <ul class="ios-info-list">
                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-envelope icon-blue"></i>
                                Email
                            </span>
                            <span class="ios-info-value">
                                <a href="mailto:<?php echo htmlspecialchars($viewUser->getEmail()); ?>">
                                    <?php echo htmlspecialchars($viewUser->getEmail()); ?>
                                </a>
                            </span>
                        </li>

                        <?php if ($viewUser->getPhone()): ?>
                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-phone icon-green"></i>
                                Phone
                            </span>
                            <span class="ios-info-value">
                                <a href="tel:<?php echo htmlspecialchars($viewUser->getPhone()); ?>">
                                    <?php echo htmlspecialchars($viewUser->getPhone()); ?>
                                </a>
                            </span>
                        </li>
                        <?php endif; ?>

                        <?php if ($householdDetails): ?>
                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-home icon-orange"></i>
                                Household
                            </span>
                            <span class="ios-info-value">
                                <a href="<?php echo BASE_URL; ?>households/view.php?id=<?php echo encryptId($householdDetails['id']); ?>">
                                    <?php echo htmlspecialchars($householdDetails['name']); ?>
                                </a>
                                <?php if ($isHouseholdHead): ?>
                                    <span style="display: block; font-size: 12px; color: var(--ios-orange);">
                                        <i class="fas fa-crown" style="font-size: 10px;"></i> Household Head
                                    </span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <?php else: ?>
                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-home icon-orange"></i>
                                Household
                            </span>
                            <span class="ios-info-value" style="color: var(--text-muted); font-style: italic;">Not assigned</span>
                        </li>
                        <?php endif; ?>

                        <?php if ($clanDetails): ?>
                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-sitemap icon-purple"></i>
                                Clan
                            </span>
                            <span class="ios-info-value">
                                <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($clanDetails['id']); ?>">
                                    <?php echo htmlspecialchars($clanDetails['name']); ?>
                                </a>
                            </span>
                        </li>
                        <?php endif; ?>

                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-id-badge icon-teal"></i>
                                User ID
                            </span>
                            <span class="ios-info-value">#<?php echo str_pad($viewUser->getId(), 6, '0', STR_PAD_LEFT); ?></span>
                        </li>

                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-calendar-alt icon-teal"></i>
                                Joined
                            </span>
                            <span class="ios-info-value">
                                <?php
                                if ($userDates && $userDates['created_at']) {
                                    echo date('M d, Y', strtotime($userDates['created_at']));
                                } else {
                                    echo '<span style="color: var(--text-muted);">Unknown</span>';
                                }
                                ?>
                            </span>
                        </li>

                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-clock icon-blue"></i>
                                Last Login
                            </span>
                            <span class="ios-info-value">
                                <?php
                                if ($userDates && $userDates['last_login']) {
                                    echo date('M d, Y g:i A', strtotime($userDates['last_login']));
                                } else {
                                    echo '<span style="color: var(--text-muted);">Never</span>';
                                }
                                ?>
                            </span>
                        </li>
                    </ul>
                </div>

                <?php if ($canDeactivate): ?>
                <div class="ios-delete-section">
                    <div class="ios-delete-warning" style="<?php echo $viewUser->getStatus() === 'active' ? '' : 'background: rgba(48, 209, 88, 0.1); border-color: rgba(48, 209, 88, 0.2);'; ?>">
                        <i class="fas fa-<?php echo $viewUser->getStatus() === 'active' ? 'exclamation-triangle' : 'check-circle'; ?>" style="color: <?php echo $viewUser->getStatus() === 'active' ? 'var(--ios-orange)' : 'var(--ios-green)'; ?>;"></i>
                        <div class="ios-delete-warning-text">
                            <?php if ($viewUser->getStatus() === 'active'): ?>
                                <strong style="color: var(--ios-orange);">Deactivate User</strong><br>
                                This will disable the user's access to the system. Their data will be preserved and they can be reactivated later.
                            <?php else: ?>
                                <strong style="color: var(--ios-green);">Activate User</strong><br>
                                This will restore the user's access to the system.
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="post" action="" style="margin: 0;">
                        <button type="submit" name="toggle_status"
                                class="ios-btn-delete"
                                style="<?php echo $viewUser->getStatus() === 'active' ? 'background: var(--ios-orange);' : 'background: var(--ios-green);'; ?>"
                                onclick="return confirm('Are you sure you want to <?php echo $viewUser->getStatus() === 'active' ? 'deactivate' : 'activate'; ?> <?php echo htmlspecialchars(addslashes($viewUser->getFullName())); ?>?');">
                            <i class="fas fa-<?php echo $viewUser->getStatus() === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                            <?php echo $viewUser->getStatus() === 'active' ? 'Deactivate User' : 'Activate User'; ?>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column - Activity & Account Info -->
        <div>
            <!-- Account Stats -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon green">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Account Statistics</h5>
                        <p>Activity overview</p>
                    </div>
                </div>

                <div class="ios-section-body no-padding">
                    <div class="ios-info-list" style="padding: 0 20px;">
                        <div class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-qrcode icon-blue"></i>
                                Access Codes
                            </span>
                            <span class="ios-info-value"><?php echo number_format($accessCodesCount); ?></span>
                        </div>
                        <div class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-check-circle icon-green"></i>
                                Verifications
                            </span>
                            <span class="ios-info-value"><?php echo number_format($accessLogsCount); ?></span>
                        </div>
                        <div class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-user-tag icon-purple"></i>
                                Account Type
                            </span>
                            <span class="ios-info-value"><?php echo getUserRoleLabel($viewUser->getRole()); ?></span>
                        </div>
                        <div class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-signal icon-orange"></i>
                                Status
                            </span>
                            <span class="ios-info-value" style="color: <?php echo $viewUser->getStatus() === 'active' ? 'var(--ios-green)' : 'var(--ios-red)'; ?>;">
                                <?php echo ucfirst($viewUser->getStatus()); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon purple">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Recent Activity</h5>
                        <p><?php echo count($activityLogs); ?> recent action<?php echo count($activityLogs) != 1 ? 's' : ''; ?></p>
                    </div>
                </div>

                <div class="ios-section-body no-padding">
                    <?php if (empty($activityLogs)): ?>
                        <div class="ios-empty-state">
                            <i class="fas fa-history"></i>
                            <h4>No Recent Activity</h4>
                            <p>No activity has been recorded for this user yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($activityLogs as $log): ?>
                            <div class="ios-activity-item">
                                <div class="ios-activity-icon">
                                    <i class="fas fa-<?php echo getActivityIcon($log['action']); ?>"></i>
                                </div>
                                <div class="ios-activity-content">
                                    <p class="ios-activity-title">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['action']))); ?>
                                    </p>
                                    <p class="ios-activity-desc">
                                        <?php echo htmlspecialchars($log['details'] ?? 'No details available'); ?>
                                    </p>
                                </div>
                                <span class="ios-activity-time">
                                    <?php echo date('M d', strtotime($log['created_at'])); ?><br>
                                    <small><?php echo date('g:i A', strtotime($log['created_at'])); ?></small>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Permissions & Access -->
            <?php if ($currentUser->isSuperAdmin() || $currentUser->isClanAdmin()): ?>
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon orange">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Permissions & Access</h5>
                        <p>What this user can do</p>
                    </div>
                </div>

                <div class="ios-section-body no-padding">
                    <?php
                    $permissions = [];
                    switch ($viewUser->getRole()) {
                        case 'super_admin':
                            $permissions = ['Full System Access', 'Manage All Clans', 'Manage All Users', 'View All Reports', 'System Configuration'];
                            break;
                        case 'clan_admin':
                            $permissions = ['Manage Clan Users', 'Generate Access Codes', 'View Clan Reports', 'Manage Clan Settings'];
                            break;
                        case 'guard':
                            $permissions = ['Verify Access Codes', 'View Access Logs', 'Report Issues'];
                            break;
                        default:
                            $permissions = ['Generate Access Codes', 'View Own Activity', 'Update Profile'];
                    }

                    foreach ($permissions as $permission):
                    ?>
                        <div class="ios-permission-item">
                            <div class="ios-permission-icon">
                                <i class="fas fa-check"></i>
                            </div>
                            <span class="ios-permission-label"><?php echo $permission; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- iOS-Style Bottom Sheet Menu -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title"><?php echo htmlspecialchars($viewUser->getFullName()); ?></h3>
        <button class="ios-menu-close" id="iosMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="ios-menu-content">
        <!-- Quick Actions Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <?php if ($canEdit): ?>
                <a href="<?php echo BASE_URL; ?>users/edit.php?id=<?php echo encryptId($userId); ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Edit User</span>
                            <span class="ios-menu-item-desc">Update user details</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>users/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">All Users</span>
                            <span class="ios-menu-item-desc">Back to user list</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple">
                            <i class="fas fa-tachometer-alt"></i>
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

        <!-- User Info Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">User Info</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Status</span>
                    <span class="ios-menu-stat-value <?php echo $viewUser->getStatus() === 'active' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($viewUser->getStatus()); ?>
                    </span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Role</span>
                    <span class="ios-menu-stat-value"><?php echo getUserRoleLabel($viewUser->getRole()); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Access Codes</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($accessCodesCount); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Verifications</span>
                    <span class="ios-menu-stat-value warning"><?php echo number_format($accessLogsCount); ?></span>
                </div>
                <?php if ($clanDetails): ?>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Clan</span>
                    <span class="ios-menu-stat-value" style="font-size: 13px;"><?php echo htmlspecialchars($clanDetails['name']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canDeactivate): ?>
        <!-- Account Status -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Account Status</div>
            <div class="ios-menu-card">
                <form method="post" action="" style="margin: 0;">
                    <button type="submit" name="toggle_status" class="ios-menu-item"
                            onclick="return confirm('Are you sure you want to <?php echo $viewUser->getStatus() === 'active' ? 'deactivate' : 'activate'; ?> this user?');"
                            style="color: <?php echo $viewUser->getStatus() === 'active' ? 'var(--ios-orange)' : 'var(--ios-green)'; ?>;">
                        <div class="ios-menu-item-left">
                            <div class="ios-menu-item-icon <?php echo $viewUser->getStatus() === 'active' ? 'orange' : 'green'; ?>">
                                <i class="fas fa-<?php echo $viewUser->getStatus() === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                            </div>
                            <div class="ios-menu-item-content">
                                <span class="ios-menu-item-label" style="color: <?php echo $viewUser->getStatus() === 'active' ? 'var(--ios-orange)' : 'var(--ios-green)'; ?>;">
                                    <?php echo $viewUser->getStatus() === 'active' ? 'Deactivate User' : 'Activate User'; ?>
                                </span>
                                <span class="ios-menu-item-desc">
                                    <?php echo $viewUser->getStatus() === 'active' ? 'Disable access to the system' : 'Restore access to the system'; ?>
                                </span>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeIosMenu();
});

function initializeIosMenu() {
    const backdrop = document.getElementById('iosMenuBackdrop');
    const modal = document.getElementById('iosMenuModal');
    const optionsBtn = document.getElementById('iosOptionsBtn');
    const closeBtn = document.getElementById('iosMenuClose');

    if (optionsBtn) optionsBtn.addEventListener('click', openIosMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeIosMenu);
    if (backdrop) backdrop.addEventListener('click', closeIosMenu);

    // Swipe to close
    let startY = 0, currentY = 0;

    if (modal) {
        modal.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; }, { passive: true });
        modal.addEventListener('touchmove', (e) => {
            currentY = e.touches[0].clientY;
            const diff = currentY - startY;
            if (diff > 0) modal.style.transform = `translateY(${diff}px)`;
        }, { passive: true });
        modal.addEventListener('touchend', () => {
            if (currentY - startY > 100) closeIosMenu();
            modal.style.transform = '';
            startY = 0; currentY = 0;
        }, { passive: true });
    }
}

function openIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.add('active');
    document.getElementById('iosMenuModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.remove('active');
    document.getElementById('iosMenuModal').classList.remove('active');
    document.body.style.overflow = '';
}
</script>

<?php
/**
 * Helper function to get avatar gradient (iOS colors)
 */
function getUserAvatarGradient($role) {
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
function getUserRoleLabel($role) {
    $labels = [
        'super_admin' => 'Super Admin',
        'clan_admin' => 'Clan Admin',
        'guard' => 'Guard',
        'user' => 'User'
    ];
    return $labels[$role] ?? 'User';
}

/**
 * Helper function to get activity icon
 */
function getActivityIcon($action) {
    $icons = [
        'login' => 'sign-in-alt',
        'logout' => 'sign-out-alt',
        'create' => 'plus-circle',
        'update' => 'edit',
        'delete' => 'trash-alt',
        'verify' => 'check-circle',
        'generate' => 'qrcode',
        'view' => 'eye'
    ];

    foreach ($icons as $key => $icon) {
        if (strpos($action, $key) !== false) {
            return $icon;
        }
    }

    return 'info-circle';
}

// Include footer
include_once '../includes/footer.php';
?>
