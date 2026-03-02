<?php

/**
 * Gate Wey Access Management System
 * Create User Page - Dasher UI Enhanced with License Check
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';

// Set page title
$pageTitle = 'Create User';

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
$formData = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'phone' => '',
    'household_id' => '',
    'is_household_head' => 0,
    'role' => 'user',
    'clan_id' => $currentUser->getClanId(),
    'status' => 'active'
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'household_id' => isset($_POST['household_id']) ? (int)$_POST['household_id'] : '',
        'is_household_head' => isset($_POST['is_household_head']) ? 1 : 0,
        'role' => $_POST['role'] ?? 'user',
        'clan_id' => isset($_POST['clan_id']) ? (int)$_POST['clan_id'] : $currentUser->getClanId(),
        'status' => $_POST['status'] ?? 'active'
    ];

    $errors = [];

    // Validation
    $requiredFields = ['username', 'email', 'password', 'confirm_password', 'full_name', 'role'];
    foreach ($requiredFields as $field) {
        if (empty($formData[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        }
    }

    if (!empty($formData['username']) && !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $formData['username'])) {
        $errors[] = 'Username must be 3-20 characters and can only contain letters, numbers, and underscores.';
    }

    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!empty($formData['password'])) {
        if (strlen($formData['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if ($formData['password'] !== $formData['confirm_password']) {
            $errors[] = 'Passwords do not match.';
        }
    }

    if (!empty($formData['phone']) && !isValidPhone($formData['phone'])) {
        $errors[] = 'Please enter a valid phone number.';
    }
// Household validation - Only required for regular users
if ($formData['role'] === 'user') {
    if (empty($formData['household_id'])) {
        $errors[] = 'Household selection is required for regular users.';
    } else {
        // Check if household exists and belongs to the clan
        $household = $db->fetchOne(
            "SELECT * FROM households WHERE id = ? AND clan_id = ?",
            [$formData['household_id'], $formData['clan_id']]
        );

        if (!$household) {
            $errors[] = 'Selected household does not exist or does not belong to your clan.';
        } else {
            // Check household capacity based on max_members
            $householdUserCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM users WHERE household_id = ?",
                [$formData['household_id']]
            );

            $maxMembers = $household['max_members'] ?? 5; // Default to 5 if not set

            if ($householdUserCount['count'] >= $maxMembers) {
                $errors[] = "This household has reached its maximum capacity of {$maxMembers} users. Please purchase additional licenses for this household or select another household.";
            }

            // If user is being set as household head, check if household already has a head
            if ($formData['is_household_head']) {
                $existingHead = $db->fetchOne(
                    "SELECT id FROM users WHERE household_id = ? AND is_household_head = 1",
                    [$formData['household_id']]
                );

                if ($existingHead) {
                    $errors[] = 'This household already has a household head. Only one household head is allowed per household.';
                }
            }
        }
    }
} else {
    // For non-user roles, clear household data
    $formData['household_id'] = null;
    $formData['is_household_head'] = 0;
}

    $allowedRoles = [];
    if ($currentUser->isSuperAdmin()) {
        $allowedRoles = ['super_admin', 'clan_admin', 'user', 'guard'];
    } elseif ($currentUser->isClanAdmin()) {
        $allowedRoles = ['user', 'guard'];
    }

    if (!in_array($formData['role'], $allowedRoles)) {
        $errors[] = 'You do not have permission to create a user with this role.';
    }

    if ($formData['role'] !== 'super_admin') {
        if (empty($formData['clan_id'])) {
            $errors[] = 'Clan is required for non-super admin users.';
        } else {
            $clan = new Clan();
            if (!$clan->loadById($formData['clan_id'])) {
                $errors[] = 'Selected clan does not exist.';
            } elseif ($currentUser->isClanAdmin() && $formData['clan_id'] != $currentUser->getClanId()) {
                $errors[] = 'You can only add users to your own clan.';
            }
        }
    } else {
        $formData['clan_id'] = null;
    }

    if (!in_array($formData['status'], ['active', 'inactive', 'suspended'])) {
        $errors[] = 'Invalid status selected.';
    }

    if (empty($errors)) {
        $user = new User();
        $userId = $user->register($formData);

        if ($userId) {
            logActivity('create_user', 'Created new user: ' . $formData['username'], $currentUser->getId());

            $success = 'User created successfully.';

            $formData = [
                'username' => '',
                'email' => '',
                'full_name' => '',
                'phone' => '',
                'address' => '',
                'role' => 'user',
                'clan_id' => $currentUser->getClanId(),
                'status' => 'active',
                'sms_notifications' => 0
            ];
        } else {
            $error = 'Failed to create user. The username or email may already be in use.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Get clans for dropdown
$clans = [];
if ($currentUser->isSuperAdmin()) {
    $clans = $db->fetchAll("SELECT id, name FROM clans ORDER BY name");
}
// Fetch households for the clan
$households = [];
if ($currentUser->isClanAdmin()) {
    $households = $db->fetchAll(
        "SELECT id, name FROM households WHERE clan_id = ? ORDER BY name",
        [$currentUser->getClanId()]
    );
} elseif ($currentUser->isSuperAdmin() && !empty($formData['clan_id'])) {
    $households = $db->fetchAll(
        "SELECT id, name FROM households WHERE clan_id = ? ORDER BY name",
        [$formData['clan_id']]
    );
}
// Include header
include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>

<!-- iOS-Style Create User Styles -->
<style>
:root {
    --ios-red: #FF453A;
    --ios-orange: #FF9F0A;
    --ios-green: #30D158;
    --ios-blue: #0A84FF;
    --ios-purple: #BF5AF2;
    --ios-teal: #64D2FF;
}

/* Form Container Layout */
.form-container {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: var(--spacing-6);
    max-width: 1200px;
    margin: 0 auto;
}

@media (max-width: 992px) {
    .form-container {
        grid-template-columns: 1fr;
    }
}

/* iOS Section Card for Form */
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
    margin: 0;
}

.ios-section-title p {
    font-size: 13px;
    color: var(--text-secondary);
    margin: 4px 0 0 0;
}

.ios-section-body {
    padding: var(--spacing-6);
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

.ios-options-btn:hover { background: var(--border-color); }
.ios-options-btn:active { transform: scale(0.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* Form Styles */
.form-section {
    margin-bottom: var(--spacing-8);
}

.form-section:last-child {
    margin-bottom: 0;
}

.form-section-header {
    margin-bottom: var(--spacing-5);
    padding-bottom: var(--spacing-3);
    border-bottom: 1px solid var(--border-color);
}

.form-section-title {
    margin: 0 0 var(--spacing-1) 0;
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
}

.form-section-subtitle {
    margin: 0;
    color: var(--text-secondary);
    font-size: var(--font-size-sm);
}

.form-row {
    margin-bottom: var(--spacing-5);
}

.form-row-2-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-4);
}

@media (max-width: 768px) {
    .form-row-2-cols {
        grid-template-columns: 1fr;
    }
}

.form-group {
    position: relative;
}

.form-label {
    display: block;
    margin-bottom: var(--spacing-2);
    font-weight: var(--font-weight-medium);
    color: var(--text-primary);
    font-size: var(--font-size-sm);
}

.form-label.required::after {
    content: ' *';
    color: var(--danger);
}

.form-control {
    display: block;
    width: 100%;
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

.form-control::placeholder { color: var(--text-muted); }

.form-text {
    margin-top: var(--spacing-2);
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
}

/* Checkbox Styling */
.checkbox-wrapper {
    position: relative;
}

.form-checkbox {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.checkbox-label {
    display: flex;
    align-items: center;
    padding: var(--spacing-3) var(--spacing-4);
    background: var(--bg-subtle);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--theme-transition);
    position: relative;
}

.checkbox-label::before {
    content: '';
    width: 16px;
    height: 16px;
    border: 2px solid var(--border-color);
    border-radius: 4px;
    margin-right: var(--spacing-3);
    flex-shrink: 0;
    transition: var(--theme-transition);
}

.form-checkbox:checked+.checkbox-label {
    background: rgba(var(--primary-rgb), 0.1);
    border-color: var(--primary);
}

.form-checkbox:checked+.checkbox-label::before {
    border-color: var(--primary);
    background: var(--primary);
}

.form-checkbox:checked+.checkbox-label::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    left: 17px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-size: 10px;
}

.checkbox-content { flex: 1; }

.checkbox-title {
    font-weight: var(--font-weight-medium);
    color: var(--text-primary);
}

.checkbox-description {
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
    margin-top: 2px;
}

.checkbox-label.disabled {
    opacity: 0.6;
    cursor: not-allowed;
    background: var(--bg-secondary);
}

.household-field {
    transition: opacity 0.3s ease, height 0.3s ease;
}

/* Info Banner */
.info-banner {
    display: flex;
    align-items: flex-start;
    padding: var(--spacing-4);
    background: var(--bg-subtle);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    margin-top: var(--spacing-4);
    gap: var(--spacing-3);
}

.info-banner.warning {
    background: rgba(var(--warning-rgb), 0.1);
    border-color: rgba(var(--warning-rgb), 0.2);
    color: var(--warning);
}

.info-banner.success {
    background: rgba(var(--success-rgb), 0.1);
    border-color: rgba(var(--success-rgb), 0.2);
    color: var(--success);
}

.info-banner i { font-size: var(--font-size-lg); flex-shrink: 0; margin-top: 2px; }
.info-banner-content { flex: 1; }
.info-banner-title { font-weight: var(--font-weight-semibold); margin-bottom: var(--spacing-1); }
.info-banner-message { font-size: var(--font-size-sm); line-height: 1.5; }

/* License Badge */
.license-badge {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-2);
    padding: var(--spacing-2) var(--spacing-4);
    background: rgba(var(--info-rgb), 0.1);
    border: 1px solid rgba(var(--info-rgb), 0.2);
    border-radius: var(--border-radius-full);
    color: var(--info);
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
}

.license-badge.success {
    background: rgba(var(--success-rgb), 0.1);
    border-color: rgba(var(--success-rgb), 0.2);
    color: var(--success);
}

.form-actions {
    margin-top: var(--spacing-8);
    padding-top: var(--spacing-6);
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: var(--spacing-3);
    justify-content: flex-end;
}

@media (max-width: 576px) {
    .form-actions {
        flex-direction: column;
    }
}

/* Tips Card (Desktop only) */
.tips-card {
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg);
    overflow: hidden;
    height: fit-content;
    position: sticky;
    top: var(--spacing-4);
}

.tips-header {
    padding: var(--spacing-4);
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
}

.tips-icon { color: var(--warning); font-size: var(--font-size-lg); }

.tips-title {
    margin: 0;
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
}

.tips-content { padding: var(--spacing-4); }

.tip-item {
    display: flex;
    align-items: flex-start;
    gap: var(--spacing-3);
    margin-bottom: var(--spacing-4);
}

.tip-item:last-child { margin-bottom: 0; }

.tip-icon {
    color: var(--success);
    font-size: var(--font-size-sm);
    margin-top: 2px;
    flex-shrink: 0;
}

.tip-text {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    line-height: 1.5;
}

.tip-text strong { color: var(--text-primary); }

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

/* iOS Tip Row in Menu */
.ios-tip-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
}

.ios-tip-row:last-child { border-bottom: none; }

.ios-tip-icon {
    width: 28px; height: 28px;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; flex-shrink: 0;
    background: rgba(48, 209, 88, 0.15);
    color: var(--ios-green);
}

.ios-tip-text {
    flex: 1;
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.4;
}

.ios-tip-text strong { color: var(--text-primary); }

/* Mobile Optimizations */
@media (max-width: 992px) {
    .ios-options-btn { display: flex; }
}

@media (max-width: 768px) {
    .content-header { display: none !important; }

    .tips-card { display: none !important; }

    .form-container {
        grid-template-columns: 1fr;
    }

    .ios-section-card { border-radius: 12px; }

    .ios-section-header { padding: 14px; }

    .ios-section-icon { width: 36px; height: 36px; font-size: 16px; }

    .ios-section-title h5 { font-size: 15px; }

    .ios-section-body { padding: var(--spacing-4); }
}

@media (max-width: 480px) {
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
                <h1 class="content-title">Create New User</h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>users/" class="breadcrumb-link">Users</a>
                        </li>
                        <li class="breadcrumb-item active">Create User</li>
                    </ol>
                </nav>
            </div>
            <div class="content-actions">
                <?php if ($currentUser->isClanAdmin()): ?>
                    <?php
                    $clan = new Clan();
                    if ($clan->loadById($currentUser->getClanId())) {
                        $pricingPlan = $db->fetchOne(
                            "SELECT * FROM pricing_plans WHERE id = ?",
                            [$clan->getPricingPlanId()]
                        );

                        if ($pricingPlan && isset($pricingPlan['is_per_user']) && $pricingPlan['is_per_user'] && !$pricingPlan['is_free']) {
                            $availableLicenses = $clan->getAvailableLicenses();
                    ?>
                            <!-- <div class="license-badge">
                                <i class="fas fa-key"></i>
                                <span>Available Licenses: <strong><?php echo $availableLicenses; ?></strong></span>
                            </div> -->
                            <a href="<?php echo BASE_URL; ?>payments/settings.php?type=license" class="btn btn-primary">
                                <i class="fas fa-shopping-cart"></i>
                                <span>Buy More Licenses</span>
                            </a>
                        <?php
                        } elseif ($pricingPlan && isset($pricingPlan['is_free']) && $pricingPlan['is_free']) {
                        ?>
                            <div class="license-badge success">
                                <i class="fas fa-check-circle"></i>
                                <span>Free Plan - Unlimited Users</span>
                            </div>
                    <?php
                        }
                    }
                    ?>
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
            <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="<?php echo BASE_URL; ?>users/" class="btn btn-sm btn-primary">View All Users</a>
                <a href="<?php echo BASE_URL; ?>users/create.php" class="btn btn-sm btn-outline-primary">Create Another User</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Create User Form -->
    <div class="form-container">
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-icon green">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="ios-section-title">
                    <h5>User Information</h5>
                    <p>Create a new user account with appropriate permissions</p>
                </div>
                <button class="ios-options-btn" onclick="openIosMenu()" aria-label="Quick tips">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>

            <div class="ios-section-body">
                <form method="post" action="" class="enhanced-form">
                    <!-- Basic Information Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <h3 class="form-section-title">Basic Information</h3>
                            <p class="form-section-subtitle">Personal details and contact information</p>
                        </div>

                        <div class="form-row form-row-2-cols">
                            <div class="form-group">
                                <label for="username" class="form-label required">Username</label>
                                <input type="text"
                                    class="form-control"
                                    id="username"
                                    name="username"
                                    value="<?php echo htmlspecialchars($formData['username']); ?>"
                                    placeholder="Enter unique username"
                                    required>
                                <div class="form-text">3-20 characters (letters, numbers, underscores only)</div>
                            </div>

                            <div class="form-group">
                                <label for="email" class="form-label required">Email Address</label>
                                <input type="email"
                                    class="form-control"
                                    id="email"
                                    name="email"
                                    value="<?php echo htmlspecialchars($formData['email']); ?>"
                                    placeholder="user@example.com"
                                    required>
                                <div class="form-text">Used for notifications and account recovery</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name" class="form-label required">Full Name</label>
                                <input type="text"
                                    class="form-control"
                                    id="full_name"
                                    name="full_name"
                                    value="<?php echo htmlspecialchars($formData['full_name']); ?>"
                                    placeholder="Enter full legal name"
                                    required>
                                <div class="form-text">The user's complete legal name</div>
                            </div>
                        </div>

                        <div class="form-row form-row-2-cols">
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text"
                                    class="form-control"
                                    id="phone"
                                    name="phone"
                                    value="<?php echo htmlspecialchars($formData['phone']); ?>"
                                    placeholder="+234 xxx xxx xxxx">
                                <div class="form-text">Optional contact number</div>
                            </div>
                            <!-- Household Selection - Only for regular users -->
                            <div class="form-group household-field" style="display: none;">
                                <label for="household_id" class="form-label">Household</label>
                                <select class="form-control form-select"
                                    id="household_id"
                                    name="household_id">
                                    <option value="">-- Select Household --</option>
                                    <?php foreach ($households as $household): ?>
                                        <option value="<?php echo $household['id']; ?>"
                                            <?php echo $formData['household_id'] == $household['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($household['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select the household this user belongs to</div>
                            </div>
                        </div>

                        <!-- Household Head - Only for regular users -->
                        <div class="form-row household-field" style="display: none;">
                            <div class="form-group">
                                <div class="checkbox-wrapper">
                                    <input type="checkbox"
                                        class="form-checkbox"
                                        id="is_household_head"
                                        name="is_household_head"
                                        <?php echo $formData['is_household_head'] ? 'checked' : ''; ?>>
                                    <label class="checkbox-label" for="is_household_head">
                                        <div class="checkbox-content">
                                            <div class="checkbox-title">Make this user the Household Head</div>
                                            <div class="checkbox-description">Household heads are responsible for dues and payments. Only one head allowed per household.</div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <h3 class="form-section-title">Account Security</h3>
                            <p class="form-section-subtitle">Set up login credentials for the user</p>
                        </div>

                        <div class="form-row form-row-2-cols">
                            <div class="form-group">
                                <label for="password" class="form-label required">Password</label>
                                <input type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    placeholder="Enter secure password"
                                    required>
                                <div class="form-text">
                                    <i class="fas fa-lock"></i>
                                    Minimum 8 characters
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password" class="form-label required">Confirm Password</label>
                                <input type="password"
                                    class="form-control"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Confirm password"
                                    required>
                                <div class="form-text">Must match the password above</div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Settings Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <h3 class="form-section-title">Account Settings</h3>
                            <p class="form-section-subtitle">Role, permissions, and account status</p>
                        </div>

                        <div class="form-row form-row-2-cols">
                            <div class="form-group">
                                <label for="role" class="form-label required">User Role</label>
                                <select class="form-control form-select" id="role" name="role" required>
                                    <?php if ($currentUser->isSuperAdmin()): ?>
                                        <option value="super_admin" <?php echo $formData['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Administrator</option>
                                        <option value="clan_admin" <?php echo $formData['role'] === 'clan_admin' ? 'selected' : ''; ?>>Clan Administrator</option>
                                    <?php endif; ?>
                                    <option value="user" <?php echo $formData['role'] === 'user' ? 'selected' : ''; ?>>Regular User</option>
                                    <option value="guard" <?php echo $formData['role'] === 'guard' ? 'selected' : ''; ?>>Guard</option>
                                </select>
                                <div class="form-text">Determines user permissions and access level</div>
                            </div>

                            <div class="form-group">
                                <label for="status" class="form-label required">Account Status</label>
                                <select class="form-control form-select" id="status" name="status" required>
                                    <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo $formData['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                                <div class="form-text">Controls user's ability to access the system</div>
                            </div>
                        </div>

                        <?php if ($currentUser->isSuperAdmin()): ?>
                            <div class="form-row" id="clanSection">
                                <div class="form-group">
                                    <label for="clan_id" class="form-label">Clan Assignment</label>
                                    <select class="form-control form-select" id="clan_id" name="clan_id">
                                        <option value="">-- Select Clan --</option>
                                        <?php foreach ($clans as $clan): ?>
                                            <option value="<?php echo $clan['id']; ?>"
                                                <?php echo $formData['clan_id'] == $clan['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($clan['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Super admins do not require clan assignment</div>
                                </div>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="clan_id" value="<?php echo $currentUser->getClanId(); ?>">
                        <?php endif; ?>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-user-plus"></i>
                            <span>Create User</span>
                        </button>
                        <a href="<?php echo BASE_URL; ?>users/" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Tips Card (Desktop only) -->
        <div class="tips-card">
            <div class="tips-header">
                <i class="fas fa-lightbulb tips-icon"></i>
                <h3 class="tips-title">Quick Tips</h3>
            </div>
            <div class="tips-content">
                <div class="tip-item">
                    <i class="fas fa-user tip-icon"></i>
                    <div class="tip-text">
                        <strong>Username:</strong> Must be unique across the entire system
                    </div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-key tip-icon"></i>
                    <div class="tip-text">
                        <strong>Password:</strong> Share securely with the user after creation
                    </div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-shield-alt tip-icon"></i>
                    <div class="tip-text">
                        <strong>Roles:</strong> Assign based on user's responsibilities and access needs
                    </div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-sitemap tip-icon"></i>
                    <div class="tip-text">
                        <strong>Clans:</strong> Users must be assigned to a clan (except super admins)
                    </div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-home tip-icon"></i>
                    <div class="tip-text">
                        <strong>Household:</strong> Only regular users need household assignment
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- iOS-Style Bottom Sheet Menu with Quick Tips -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Create User</h3>
        <button class="ios-menu-close" id="iosMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="ios-menu-content">
        <!-- Navigation Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Navigation</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>users/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-list"></i>
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

        <!-- Quick Tips Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Tips</div>
            <div class="ios-menu-card">
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-user"></i></div>
                    <div class="ios-tip-text"><strong>Username:</strong> Must be unique across the entire system</div>
                </div>
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-key"></i></div>
                    <div class="ios-tip-text"><strong>Password:</strong> Share securely with the user after creation</div>
                </div>
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="ios-tip-text"><strong>Roles:</strong> Assign based on user's responsibilities and access needs</div>
                </div>
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-sitemap"></i></div>
                    <div class="ios-tip-text"><strong>Clans:</strong> Users must be assigned to a clan (except super admins)</div>
                </div>
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-home"></i></div>
                    <div class="ios-tip-text"><strong>Household:</strong> Only regular users need household assignment</div>
                </div>
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-crown"></i></div>
                    <div class="ios-tip-text"><strong>Household Head:</strong> Only one member can be the household head</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const clanSection = document.getElementById('clanSection');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const form = document.querySelector('.enhanced-form');
    const householdFields = document.querySelectorAll('.household-field');
    const householdSelect = document.getElementById('household_id');
    const householdHeadCheckbox = document.getElementById('is_household_head');

    // Show/hide clan field based on role (for super admin)
    function updateClanVisibility() {
        if (roleSelect && clanSection) {
            if (roleSelect.value === 'super_admin') {
                clanSection.style.display = 'none';
                const clanSelect = document.getElementById('clan_id');
                if (clanSelect) clanSelect.removeAttribute('required');
            } else {
                clanSection.style.display = 'block';
                const clanSelect = document.getElementById('clan_id');
                if (clanSelect) clanSelect.setAttribute('required', '');
            }
        }
    }

    // Show/hide household fields based on role
    function updateHouseholdVisibility() {
        if (roleSelect && householdFields.length > 0) {
            const selectedRole = roleSelect.value;

            if (selectedRole === 'user') {
                householdFields.forEach(field => {
                    field.style.display = 'block';
                });
                if (householdSelect) {
                    householdSelect.setAttribute('required', '');
                }
            } else {
                householdFields.forEach(field => {
                    field.style.display = 'none';
                });
                if (householdSelect) {
                    householdSelect.removeAttribute('required');
                    householdSelect.value = '';
                }
                if (householdHeadCheckbox) {
                    householdHeadCheckbox.checked = false;
                    householdHeadCheckbox.disabled = false;
                }
            }
        }
    }

    // Password confirmation validation
    function validatePassword() {
        if (password.value && confirmPassword.value) {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
                return false;
            } else {
                confirmPassword.setCustomValidity('');
                return true;
            }
        }
        confirmPassword.setCustomValidity('');
        return true;
    }

    // Initial setup
    updateClanVisibility();
    updateHouseholdVisibility();

    // Event listeners
    if (roleSelect) {
        roleSelect.addEventListener('change', function() {
            updateClanVisibility();
            updateHouseholdVisibility();
        });
    }

    if (password && confirmPassword) {
        confirmPassword.addEventListener('input', validatePassword);
        password.addEventListener('input', function() {
            if (confirmPassword.value) {
                validatePassword();
            }
        });
    }

    // Form submission enhancement
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitButton = form.querySelector('button[type="submit"]');

            if (validatePassword()) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Creating User...</span>';
            } else {
                e.preventDefault();
            }
        });
    }

    // Real-time validation
    const formControls = document.querySelectorAll('.form-control');
    formControls.forEach(control => {
        control.addEventListener('input', function() {
            if (this.hasAttribute('required') && this.value.trim()) {
                this.style.borderColor = 'var(--success)';
            } else if (this.hasAttribute('required') && !this.value.trim()) {
                this.style.borderColor = 'var(--border-color)';
            }
        });
    });

    // Household selection and head checkbox logic
    if (householdSelect && householdHeadCheckbox) {
        householdSelect.addEventListener('change', async function() {
            if (this.value) {
                try {
                    const response = await fetch(`<?php echo BASE_URL; ?>api/check-household-head.php?household_id=${this.value}`);
                    const data = await response.json();

                    if (data.has_head) {
                        householdHeadCheckbox.disabled = true;
                        householdHeadCheckbox.checked = false;
                        const label = document.querySelector('label[for="is_household_head"]');
                        if (label) label.classList.add('disabled');
                    } else {
                        householdHeadCheckbox.disabled = false;
                        const label = document.querySelector('label[for="is_household_head"]');
                        if (label) label.classList.remove('disabled');
                    }
                } catch (error) {
                    console.error('Error checking household head:', error);
                }
            } else {
                householdHeadCheckbox.disabled = true;
                householdHeadCheckbox.checked = false;
            }
        });
    }

    // Auto-dismiss alerts
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 500);
        });
    }, 5000);
});

// iOS Menu Functions
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

if (iosMenuClose) iosMenuClose.addEventListener('click', closeIosMenu);
if (iosMenuBackdrop) iosMenuBackdrop.addEventListener('click', closeIosMenu);

// Swipe to close
let startY = 0, currentY = 0;
if (iosMenuModal) {
    iosMenuModal.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; }, { passive: true });
    iosMenuModal.addEventListener('touchmove', (e) => {
        currentY = e.touches[0].clientY;
        const diff = currentY - startY;
        if (diff > 0) iosMenuModal.style.transform = `translateY(${diff}px)`;
    }, { passive: true });
    iosMenuModal.addEventListener('touchend', () => {
        if (currentY - startY > 100) closeIosMenu();
        iosMenuModal.style.transform = '';
        startY = 0; currentY = 0;
    });
}
</script>

<?php
include_once '../includes/footer.php';
?>
