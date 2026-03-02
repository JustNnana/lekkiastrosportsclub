<?php

/**
 * Gate Wey Access Management System
 * Edit User Page - Dasher UI Enhanced
 * RECODED WITH PROPER PREPARED STATEMENTS
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';

// Set page title
$pageTitle = 'Edit User';

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

// Check if user ID is provided
$userId = isset($_GET['id']) ? decryptId($_GET['id']) : null;
if (!$userId || !is_numeric($userId) || $userId <= 0) {
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Load the user to edit
$editUser = new User();
if (!$editUser->loadById($userId)) {
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Check permissions
$hasEditPermission = false;
if ($currentUser->isSuperAdmin()) {
    $hasEditPermission = true;
} elseif (
    $currentUser->isClanAdmin() && $editUser->getClanId() == $currentUser->getClanId()
    && !$editUser->isClanAdmin() && $editUser->getId() != $currentUser->getId()
) {
    $hasEditPermission = true;
}

if (!$hasEditPermission) {
    header('Location: ' . BASE_URL . 'users/');
    exit;
}

// Get database instance
$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize variables
$error = '';
$success = '';

// Get user household info using prepared statement
$stmt = $conn->prepare("SELECT household_id, is_household_head FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userHouseholdInfo = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt->closeCursor();

$formData = [
    'username' => $editUser->getUsername(),
    'email' => $editUser->getEmail(),
    'full_name' => $editUser->getFullName(),
    'phone' => $editUser->getPhone() ?? '',
    'household_id' => $userHouseholdInfo['household_id'] ?? '',
    'is_household_head' => $userHouseholdInfo['is_household_head'] ?? 0,
    'role' => $editUser->getRole(),
    'clan_id' => $editUser->getClanId(),
    'status' => $editUser->getStatus()
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'household_id' => isset($_POST['household_id']) ? (int)$_POST['household_id'] : ($userHouseholdInfo['household_id'] ?? ''),
        'is_household_head' => isset($_POST['is_household_head']) ? 1 : 0,
        'role' => $_POST['role'] ?? $editUser->getRole(),
        'clan_id' => isset($_POST['clan_id']) ? (int)$_POST['clan_id'] : $editUser->getClanId(),
        'status' => $_POST['status'] ?? $editUser->getStatus()
    ];

    $changePassword = !empty($_POST['password']) && !empty($_POST['confirm_password']);

    if ($changePassword) {
        $formData['password'] = $_POST['password'];
        $formData['confirm_password'] = $_POST['confirm_password'];
    }

    $errors = [];

    // Validation
    $requiredFields = ['username', 'email', 'full_name', 'role'];
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

    if ($changePassword) {
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

    $allowedRoles = [];
    if ($currentUser->isSuperAdmin()) {
        $allowedRoles = ['super_admin', 'clan_admin', 'user', 'guard'];
    } elseif ($currentUser->isClanAdmin()) {
        $allowedRoles = ['user', 'guard'];
    }

    if (!in_array($formData['role'], $allowedRoles)) {
        $errors[] = 'You do not have permission to assign this role.';
    }

    if ($formData['role'] !== 'super_admin') {
        if (empty($formData['clan_id'])) {
            $errors[] = 'Clan is required for non-super admin users.';
        } else {
            $clan = new Clan();
            if (!$clan->loadById($formData['clan_id'])) {
                $errors[] = 'Selected clan does not exist.';
            } elseif ($currentUser->isClanAdmin() && $formData['clan_id'] != $currentUser->getClanId()) {
                $errors[] = 'You can only manage users in your own clan.';
            }
        }
    } else {
        $formData['clan_id'] = null;
    }

    if (!in_array($formData['status'], ['active', 'inactive', 'suspended'])) {
        $errors[] = 'Invalid status selected.';
    }

    // Household validation - Only required for regular users
    if ($formData['role'] === 'user') {
        if (empty($formData['household_id'])) {
            $errors[] = 'Household selection is required for regular users.';
        } else {
            // Check if household exists and belongs to the clan - PROPER PREPARED STATEMENT
            $stmt = $conn->prepare("SELECT * FROM households WHERE id = ? AND clan_id = ?");
            $stmt->execute([$formData['household_id'], $formData['clan_id']]);
            $household = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (!$household) {
                $errors[] = 'Selected household does not exist or does not belong to your clan.';
            } else {
                // Check if user is changing households
                if ($formData['household_id'] != $userHouseholdInfo['household_id']) {
                    // Check household capacity - PROPER PREPARED STATEMENT
                    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE household_id = ?");
                    $stmt->execute([$formData['household_id']]);
                    $householdUserCount = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stmt->closeCursor();

                    $maxMembers = $household['max_members'] ?? 5;

                    if ($householdUserCount['count'] >= $maxMembers) {
                        $errors[] = "This household has reached its maximum capacity of {$maxMembers} users. Please purchase additional licenses for this household or select another household.";
                    }
                }

                // If user is being set as household head - PROPER PREPARED STATEMENT
                if ($formData['is_household_head']) {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE household_id = ? AND is_household_head = 1");
                    $stmt->execute([$formData['household_id']]);
                    $existingHead = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stmt->closeCursor();

                    if ($existingHead && $existingHead['id'] != $userId) {
                        $errors[] = 'This household already has a household head. Only one household head is allowed per household.';
                    }
                }
            }
        }
    } else {
        // For non-user roles (clan_admin, guard, super_admin), clear household data
        $formData['household_id'] = null;
        $formData['is_household_head'] = 0;
    }

    if (empty($errors)) {
        $updateData = [
            'email' => $formData['email'],
            'full_name' => $formData['full_name'],
            'phone' => $formData['phone'],
            'household_id' => $formData['household_id'],
            'is_household_head' => $formData['is_household_head'],
            'role' => $formData['role'],
            'clan_id' => $formData['clan_id'],
            'status' => $formData['status']
        ];

        if ($changePassword) {
            $updateData['password'] = password_hash($formData['password'], PASSWORD_DEFAULT);
        }

        // Check username uniqueness if super admin is changing it - PROPER PREPARED STATEMENT
        if ($currentUser->isSuperAdmin() && $formData['username'] !== $editUser->getUsername()) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$formData['username'], $userId]);
            $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($existingUser) {
                $error = 'Username is already taken. Please choose another one.';
            } else {
                $updateData['username'] = $formData['username'];
            }
        }

        if (empty($error)) {
            $fields = [];
            $params = [];

            foreach ($updateData as $field => $value) {
                $fields[] = "`$field` = ?";
                $params[] = $value;
            }

            if (!empty($fields)) {
                // Add user ID to params
                $params[] = $userId;
                
                // Build and execute UPDATE query - PROPER PREPARED STATEMENT
                $query = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
                
                try {
                    $stmt = $conn->prepare($query);
                    $executeResult = $stmt->execute($params);
                    $stmt->closeCursor();
                    
                    if ($executeResult) {
                        logActivity('update_user', 'Updated user: ' . $editUser->getUsername(), $currentUser->getId());

                        $success = 'User updated successfully.';
                        
                        // Reload user data
                        $editUser->loadById($userId);

                        // Refresh household info - PROPER PREPARED STATEMENT
                        $stmt = $conn->prepare("SELECT household_id, is_household_head FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $userHouseholdInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                        $stmt->closeCursor();

                        $formData = [
                            'username' => $editUser->getUsername(),
                            'email' => $editUser->getEmail(),
                            'full_name' => $editUser->getFullName(),
                            'phone' => $editUser->getPhone() ?? '',
                            'household_id' => $userHouseholdInfo['household_id'] ?? '',
                            'is_household_head' => $userHouseholdInfo['is_household_head'] ?? 0,
                            'role' => $editUser->getRole(),
                            'clan_id' => $editUser->getClanId(),
                            'status' => $editUser->getStatus()
                        ];
                    } else {
                        $error = 'Failed to update user. No changes were made.';
                    }
                } catch (PDOException $e) {
                    $error = 'Failed to update user: ' . $e->getMessage();
                    error_log("User update error: " . $e->getMessage() . " | Query: " . $query);
                } catch (Exception $e) {
                    $error = 'Failed to update user: ' . $e->getMessage();
                    error_log("User update error: " . $e->getMessage());
                }
            } else {
                $error = 'No changes were made.';
            }
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Fetch households for the clan - PROPER PREPARED STATEMENT
$households = [];
if ($currentUser->isClanAdmin()) {
    $stmt = $conn->prepare("SELECT id, name FROM households WHERE clan_id = ? ORDER BY name");
    $stmt->execute([$currentUser->getClanId()]);
    $households = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
} elseif ($currentUser->isSuperAdmin() && !empty($formData['clan_id'])) {
    $stmt = $conn->prepare("SELECT id, name FROM households WHERE clan_id = ? ORDER BY name");
    $stmt->execute([$formData['clan_id']]);
    $households = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
}

// Check if the selected household has a head - PROPER PREPARED STATEMENT
$householdHasHead = false;
$isCurrentUserTheHead = false;
if ($formData['household_id']) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE household_id = ? AND is_household_head = 1");
    $stmt->execute([$formData['household_id']]);
    $existingHead = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if ($existingHead) {
        $householdHasHead = true;
        if ($existingHead['id'] == $userId) {
            $isCurrentUserTheHead = true;
        }
    }
}

// Get current household name - PROPER PREPARED STATEMENT
$currentHouseholdName = '';
if ($formData['household_id']) {
    $stmt = $conn->prepare("SELECT name FROM households WHERE id = ?");
    $stmt->execute([$formData['household_id']]);
    $currentHousehold = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    
    if ($currentHousehold) {
        $currentHouseholdName = $currentHousehold['name'];
    }
}

// Get clans for dropdown - PROPER PREPARED STATEMENT
$clans = [];
if ($currentUser->isSuperAdmin()) {
    $stmt = $conn->prepare("SELECT id, name FROM clans ORDER BY name");
    $stmt->execute();
    $clans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
} elseif ($currentUser->isClanAdmin()) {
    $stmt = $conn->prepare("SELECT id, name FROM clans WHERE id = ? ORDER BY name");
    $stmt->execute([$currentUser->getClanId()]);
    $clans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
}

// Include header
include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>

<!-- iOS-Style Edit User Styles -->
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

/* Form Sections */
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

/* Enhanced Form Controls */
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

.form-control:read-only,
.form-control:disabled {
    background: var(--bg-subtle);
    cursor: not-allowed;
    opacity: 0.7;
}

.form-control.is-invalid {
    border-color: var(--danger);
}

.form-control::placeholder { color: var(--text-muted); }

.form-text {
    margin-top: var(--spacing-2);
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
}

.form-text .text-warning {
    color: var(--warning) !important;
}

.form-text .text-warning i {
    margin-right: var(--spacing-1);
}

/* Checkbox Styling */
.form-check-custom.disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.form-check-custom.disabled .form-check-input {
    cursor: not-allowed;
}

.form-check-custom.disabled .form-check-label {
    cursor: not-allowed;
}

/* Form Actions */
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
    <!-- Content Header -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="content-title">Edit User</h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>users/" class="breadcrumb-link">Users</a>
                        </li>
                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($editUser->getFullName()); ?></li>
                    </ol>
                </nav>
            </div>
            <div class="content-actions">
                <a href="<?php echo BASE_URL; ?>users/view.php?id=<?php echo encryptId($userId); ?>" class="btn btn-secondary">
                    <i class="fas fa-eye"></i>
                    <span>View User</span>
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
            <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                <a href="<?php echo BASE_URL; ?>users/" class="btn btn-sm btn-primary">View All Users</a>
                <a href="<?php echo BASE_URL; ?>users/view.php?id=<?php echo encryptId($userId); ?>" class="btn btn-sm btn-outline-primary">View User Profile</a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Edit User Form -->
    <div class="form-container">
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-icon blue">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="ios-section-title">
                    <h5>Edit User Information</h5>
                    <p>Update user details and account settings</p>
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

                        <div class="form-row">
                            <div class="form-group">
                                <label for="username" class="form-label required">Username</label>
                                <input type="text"
                                    class="form-control"
                                    id="username"
                                    name="username"
                                    value="<?php echo htmlspecialchars($formData['username']); ?>"
                                    <?php echo ($currentUser->isSuperAdmin() ? '' : 'readonly'); ?>
                                    required>
                                <div class="form-text">
                                    <?php if (!$currentUser->isSuperAdmin()): ?>
                                        <span class="text-warning">
                                            <i class="fas fa-lock"></i>
                                            Only super administrators can change usernames
                                        </span>
                                    <?php else: ?>
                                        Username must be 3-20 characters (letters, numbers, and underscores only)
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-row form-row-2-cols">
                            <div class="form-group">
                                <label for="full_name" class="form-label required">Full Name</label>
                                <input type="text"
                                    class="form-control"
                                    id="full_name"
                                    name="full_name"
                                    value="<?php echo htmlspecialchars($formData['full_name']); ?>"
                                    placeholder="Enter full name"
                                    required>
                                <div class="form-text">The user's complete legal name</div>
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

                            <!-- Household - Only required for regular users -->
                            <div class="form-group household-field" style="<?php echo in_array($formData['role'], ['clan_admin', 'guard', 'super_admin']) ? 'display: none;' : ''; ?>">
                                <label for="household_id" class="form-label <?php echo $formData['role'] === 'user' ? 'required' : ''; ?>">Household</label>
                                <select class="form-control form-select"
                                    id="household_id"
                                    name="household_id"
                                    <?php echo $formData['role'] === 'user' ? 'required' : ''; ?>>
                                    <option value="">-- Select Household --</option>
                                    <?php foreach ($households as $household): ?>
                                        <option value="<?php echo $household['id']; ?>"
                                            <?php echo $formData['household_id'] == $household['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($household['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Current household: <strong><?php echo htmlspecialchars($currentHouseholdName); ?></strong></div>
                            </div>

                            <!-- Household Head - Only for regular users -->
                            <div class="form-group household-field" style="<?php echo in_array($formData['role'], ['clan_admin', 'guard', 'super_admin']) ? 'display: none;' : ''; ?>">
                                <div class="form-check-custom <?php echo ($householdHasHead && !$isCurrentUserTheHead) ? 'disabled' : ''; ?>">
                                    <input type="checkbox"
                                        class="form-check-input"
                                        id="is_household_head"
                                        name="is_household_head"
                                        <?php echo $formData['is_household_head'] ? 'checked' : ''; ?>
                                        <?php echo ($householdHasHead && !$isCurrentUserTheHead) ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="is_household_head">
                                        <span class="check-title">Household Head</span>
                                        <span class="check-description">
                                            <?php if ($householdHasHead && !$isCurrentUserTheHead): ?>
                                                <span class="text-warning">
                                                    <i class="fas fa-info-circle"></i>
                                                    This household already has a household head
                                                </span>
                                            <?php else: ?>
                                                Household heads are responsible for dues and payments
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Password Change Section -->
                    <div class="form-section">
                        <div class="form-section-header">
                            <h3 class="form-section-title">Change Password</h3>
                            <p class="form-section-subtitle">Update the user's login credentials (optional)</p>
                        </div>

                        <div class="form-row form-row-2-cols">
                            <div class="form-group">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password"
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    placeholder="Enter new password">
                                <div class="form-text">
                                    <i class="fas fa-info-circle"></i>
                                    Leave blank to keep current password
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password"
                                    class="form-control"
                                    id="confirm_password"
                                    name="confirm_password"
                                    placeholder="Confirm new password">
                                <div class="form-text">Must be at least 8 characters</div>
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
                                <select class="form-control form-select"
                                    id="role"
                                    name="role"
                                    <?php echo ($userId == $currentUser->getId()) ? 'disabled' : ''; ?>
                                    required>
                                    <?php if ($currentUser->isSuperAdmin()): ?>
                                        <option value="super_admin" <?php echo ($formData['role'] === 'super_admin') ? 'selected' : ''; ?>>Super Administrator</option>
                                        <option value="clan_admin" <?php echo ($formData['role'] === 'clan_admin') ? 'selected' : ''; ?>>Clan Administrator</option>
                                    <?php endif; ?>
                                    <option value="user" <?php echo ($formData['role'] === 'user') ? 'selected' : ''; ?>>Regular User</option>
                                    <option value="guard" <?php echo ($formData['role'] === 'guard') ? 'selected' : ''; ?>>Guard</option>
                                </select>
                                <div class="form-text">
                                    <?php if ($userId == $currentUser->getId()): ?>
                                        <span class="text-warning">
                                            <i class="fas fa-lock"></i>
                                            You cannot change your own role
                                        </span>
                                    <?php else: ?>
                                        Determines user permissions and access level
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="status" class="form-label required">Account Status</label>
                                <select class="form-control form-select"
                                    id="status"
                                    name="status"
                                    <?php echo ($userId == $currentUser->getId()) ? 'disabled' : ''; ?>
                                    required>
                                    <option value="active" <?php echo ($formData['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($formData['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="suspended" <?php echo ($formData['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                </select>
                                <div class="form-text">
                                    <?php if ($userId == $currentUser->getId()): ?>
                                        <span class="text-warning">
                                            <i class="fas fa-lock"></i>
                                            You cannot change your own status
                                        </span>
                                    <?php else: ?>
                                        Controls user's ability to access the system
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-row" id="clanSection">
                            <div class="form-group">
                                <label for="clan_id" class="form-label <?php echo ($formData['role'] !== 'super_admin') ? 'required' : ''; ?>">Clan Assignment</label>
                                <select class="form-control form-select"
                                    id="clan_id"
                                    name="clan_id"
                                    <?php echo ($formData['role'] !== 'super_admin') ? 'required' : ''; ?>
                                    <?php echo (!$currentUser->isSuperAdmin()) ? 'disabled' : ''; ?>>
                                    <option value="">-- Select Clan --</option>
                                    <?php foreach ($clans as $clan): ?>
                                        <option value="<?php echo $clan['id']; ?>"
                                            <?php echo ($formData['clan_id'] == $clan['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($clan['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <?php if (!$currentUser->isSuperAdmin()): ?>
                                        <span class="text-warning">
                                            <i class="fas fa-lock"></i>
                                            Only super administrators can change clan assignments
                                        </span>
                                    <?php else: ?>
                                        Super admins do not require clan assignment
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i>
                            <span>Save Changes</span>
                        </button>
                        <a href="<?php echo BASE_URL; ?>users/" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Quick Tips Card -->
        <div class="tips-card">
            <div class="tips-header">
                <i class="fas fa-lightbulb tips-icon"></i>
                <h3 class="tips-title">Quick Tips</h3>
            </div>
            <div class="tips-content">
                <div class="tip-item">
                    <i class="fas fa-shield-alt tip-icon"></i>
                    <div class="tip-text">
                        <strong>Roles:</strong> Assign appropriate roles based on user responsibilities
                    </div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-key tip-icon"></i>
                    <div class="tip-text">
                        <strong>Password:</strong> Only fill password fields when changing login credentials
                    </div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-sitemap tip-icon"></i>
                    <div class="tip-text">
                        <strong>Clans:</strong> Users must be assigned to a clan (except super admins)
                    </div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-toggle-on tip-icon"></i>
                    <div class="tip-text">
                        <strong>Status:</strong> Inactive users cannot log in to the system
                    </div>
                </div>
                <div class="tip-item">
                    <i class="fas fa-user-lock tip-icon"></i>
                    <div class="tip-text">
                        <strong>Self-Edit:</strong> You cannot change your own role or status
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- iOS-Style Bottom Sheet Menu -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Edit User</h3>
        <button class="ios-menu-close" id="iosMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="ios-menu-content">
        <!-- Navigation Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Navigation</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>users/view.php?id=<?php echo encryptId($userId); ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">View User Profile</span>
                            <span class="ios-menu-item-desc">View full user details</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
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
                    <div class="ios-tip-icon"><i class="fas fa-shield-alt"></i></div>
                    <div class="ios-tip-text"><strong>Roles:</strong> Assign appropriate roles based on user responsibilities</div>
                </div>
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-key"></i></div>
                    <div class="ios-tip-text"><strong>Password:</strong> Only fill password fields when changing login credentials</div>
                </div>
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-sitemap"></i></div>
                    <div class="ios-tip-text"><strong>Clans:</strong> Users must be assigned to a clan (except super admins)</div>
                </div>
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-toggle-on"></i></div>
                    <div class="ios-tip-text"><strong>Status:</strong> Inactive users cannot log in to the system</div>
                </div>
                <div class="ios-tip-row">
                    <div class="ios-tip-icon"><i class="fas fa-user-lock"></i></div>
                    <div class="ios-tip-text"><strong>Self-Edit:</strong> You cannot change your own role or status</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
        // Form elements
        const roleSelect = document.getElementById('role');
        const clanSection = document.getElementById('clanSection');
        const clanSelect = document.getElementById('clan_id');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const form = document.querySelector('.enhanced-form');
        const householdSelect = document.getElementById('household_id');
        const householdHeadCheckbox = document.getElementById('is_household_head');
        const householdFields = document.querySelectorAll('.household-field');

        // Show/hide clan field based on role
        function updateClanVisibility() {
            if (roleSelect.value === 'super_admin') {
                clanSection.style.display = 'none';
                clanSelect.removeAttribute('required');
            } else {
                clanSection.style.display = 'block';
                clanSelect.setAttribute('required', '');
            }
        }

        // Show/hide household fields based on role
        function updateHouseholdVisibility() {
            if (roleSelect && householdFields.length > 0) {
                const selectedRole = roleSelect.value;

                if (selectedRole === 'user') {
                    householdFields.forEach(field => {
                        field.style.display = '';
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
                    }
                    if (householdHeadCheckbox) {
                        householdHeadCheckbox.checked = false;
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

        // Check if household has a head
        async function checkHouseholdHead(householdId) {
            if (!householdId) {
                householdHeadCheckbox.disabled = true;
                householdHeadCheckbox.checked = false;
                householdHeadCheckbox.parentElement.classList.remove('disabled');
                updateCheckboxDescription('Select a household first');
                return;
            }

            try {
                const response = await fetch(`<?php echo BASE_URL; ?>api/check-household-head.php?household_id=${householdId}&current_user_id=<?php echo $userId; ?>`);
                const data = await response.json();

                if (data.has_head && !data.is_current_user) {
                    householdHeadCheckbox.disabled = true;
                    householdHeadCheckbox.checked = false;
                    householdHeadCheckbox.parentElement.classList.add('disabled');
                    updateCheckboxDescription('<span class="text-warning"><i class="fas fa-info-circle"></i> This household already has a household head</span>');
                } else {
                    householdHeadCheckbox.disabled = false;
                    householdHeadCheckbox.parentElement.classList.remove('disabled');
                    updateCheckboxDescription('Household heads are responsible for dues and payments');
                }
            } catch (error) {
                console.error('Error checking household head:', error);
                householdHeadCheckbox.disabled = false;
                householdHeadCheckbox.parentElement.classList.remove('disabled');
            }
        }

        // Update checkbox description
        function updateCheckboxDescription(html) {
            const description = householdHeadCheckbox.parentElement.querySelector('.check-description');
            if (description) {
                description.innerHTML = html;
            }
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

        // Household selection change
        if (householdSelect && householdHeadCheckbox) {
            householdSelect.addEventListener('change', function() {
                checkHouseholdHead(this.value);
            });
        }

        // Form submission enhancement
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitButton = form.querySelector('button[type="submit"]');

                if (validatePassword()) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving Changes...</span>';
                } else {
                    e.preventDefault();
                }
            });
        }

        // Real-time validation feedback
        const formControls = document.querySelectorAll('.form-control');
        formControls.forEach(control => {
            control.addEventListener('focus', function() {
                this.classList.add('focused');
            });

            control.addEventListener('blur', function() {
                this.classList.remove('focused');
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });

            control.addEventListener('input', function() {
                if (this.classList.contains('is-invalid') && this.value.trim()) {
                    this.classList.remove('is-invalid');
                }
            });
        });

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