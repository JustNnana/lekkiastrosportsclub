<?php
/**
 * Gate Wey Access Management System
 * User Profile Page - iOS Style Enhanced
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/Notification-helper.php';

// Set page title
$pageTitle = 'My Profile';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL);
    exit;
}

// Get user info
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    // If user doesn't exist, clear session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

// Get clan info if user belongs to a clan
$clan = null;
if ($currentUser->getClanId()) {
    $clan = new Clan();
    $clan->loadById($currentUser->getClanId());
}

// Get database instance
$db = Database::getInstance();

// Initialize variables
$error = '';
$success = '';
$passwordError = '';
$passwordSuccess = '';

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Normal users cannot change email and address
    $isNormalUserCheck = ($currentUser->getRole() === 'user');

    if ($isNormalUserCheck) {
        // Keep existing email and address for normal users
        $email = $currentUser->getEmail();
        $address = $userData['address'] ?? '';
    } else {
        $email = trim($_POST['email'] ?? '');
        $address = trim($_POST['address'] ?? '');
    }

    // Validate inputs
    $errors = [];

    if (empty($fullName)) {
        $errors[] = 'Full name is required.';
    }

    // Only validate email for non-normal users
    if (!$isNormalUserCheck) {
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } elseif ($email !== $currentUser->getEmail()) {
            // Check if email already exists (only if changed)
            $existingUser = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $currentUser->getId()]);
            if ($existingUser) {
                $errors[] = 'Email address is already in use by another account.';
            }
        }
    }

    // If there are no errors, update the profile
    if (empty($errors)) {
        $updateData = [
            'full_name' => $fullName,
            'phone' => $phone
        ];

        // Only include email and address if not a normal user
        if (!$isNormalUserCheck) {
            $updateData['email'] = $email;
            $updateData['address'] = $address;
        }

        if ($currentUser->updateProfile($updateData)) {
            $success = 'Profile updated successfully.';
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate inputs
    $errors = [];

    if (empty($currentPassword)) {
        $errors[] = 'Current password is required.';
    }

    if (empty($newPassword)) {
        $errors[] = 'New password is required.';
    } elseif (strlen($newPassword) < 8) {
        $errors[] = 'New password must be at least 8 characters long.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    }

    // If there are no errors, change the password
    if (empty($errors)) {
        if ($currentUser->changePassword($currentPassword, $newPassword)) {
            $passwordSuccess = 'Password changed successfully.';
        } else {
            $passwordError = 'Failed to change password. Please check your current password and try again.';
        }
    } else {
        $passwordError = implode('<br>', $errors);
    }
}

// Get user activity
$recentCodes = $db->fetchAll(
    "SELECT * FROM access_codes WHERE created_by = ? ORDER BY created_at DESC LIMIT 5",
    [$currentUser->getId()]
);

// Get user data for additional fields
$userData = $db->fetchOne("SELECT phone, address, last_login, created_at FROM users WHERE id = ?", [$currentUser->getId()]);

// Get household data if user belongs to a household
$householdData = null;
$householdAddress = '';
if ($currentUser->getHouseholdId()) {
    $householdData = $db->fetchOne(
        "SELECT h.name, h.address FROM households h WHERE h.id = ?",
        [$currentUser->getHouseholdId()]
    );
    if ($householdData) {
        $householdAddress = $householdData['name'] . ' - ' . $householdData['address'];
    }
}

// Check if user is a normal user (not admin)
$isNormalUser = ($currentUser->getRole() === 'user');

// Include header
include_once '../includes/header.php';

// Include sidebar
include_once '../includes/sidebar.php';
?>

<!-- iOS-Style Styles -->
<style>
    :root {
        --ios-red: #FF453A;
        --ios-orange: #FF9F0A;
        --ios-yellow: #FFD60A;
        --ios-green: #30D158;
        --ios-teal: #64D2FF;
        --ios-blue: #0A84FF;
        --ios-purple: #BF5AF2;
    }

    /* iOS Flash Messages */
    .ios-flash-message {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 20px;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .ios-flash-message.success {
        background: rgba(48, 209, 88, 0.15);
        border: 1px solid rgba(48, 209, 88, 0.3);
    }

    .ios-flash-message.error {
        background: rgba(255, 69, 58, 0.15);
        border: 1px solid rgba(255, 69, 58, 0.3);
    }

    .ios-flash-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .ios-flash-message.success .ios-flash-icon {
        background: var(--ios-green);
        color: white;
    }

    .ios-flash-message.error .ios-flash-icon {
        background: var(--ios-red);
        color: white;
    }

    .ios-flash-content {
        flex: 1;
    }

    .ios-flash-title {
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 2px 0;
    }

    .ios-flash-message.success .ios-flash-title {
        color: var(--ios-green);
    }

    .ios-flash-message.error .ios-flash-title {
        color: var(--ios-red);
    }

    .ios-flash-text {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0;
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

    .ios-section-body {
        padding: 20px;
    }

    .ios-section-body.no-padding {
        padding: 0;
    }

    /* iOS 3-Dot Menu Button */
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

    /* iOS Profile Avatar */
    .ios-profile-avatar {
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

    .ios-profile-name {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 4px 0;
        text-align: center;
    }

    .ios-profile-username {
        font-size: 15px;
        color: var(--text-secondary);
        margin: 0 0 16px 0;
        text-align: center;
    }

    .ios-profile-badges {
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

    .ios-badge.role {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    .ios-badge.status-active {
        background: rgba(48, 209, 88, 0.15);
        color: var(--ios-green);
    }

    .ios-badge.status-inactive {
        background: rgba(255, 69, 58, 0.15);
        color: var(--ios-red);
    }

    .ios-badge-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
    }

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

    .ios-info-item:last-child {
        border-bottom: none;
    }

    .ios-info-label {
        font-size: 15px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ios-info-label i {
        width: 20px;
        text-align: center;
    }

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

    .ios-info-value a:hover {
        text-decoration: underline;
    }

    /* iOS Tabs */
    .ios-tabs {
        display: flex;
        background: var(--bg-secondary);
        border-radius: 10px;
        padding: 4px;
        margin-bottom: 20px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .ios-tab-btn {
        flex: 1;
        min-width: max-content;
        padding: 10px 16px;
        border: none;
        background: transparent;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        color: var(--text-secondary);
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .ios-tab-btn:hover {
        color: var(--text-primary);
    }

    .ios-tab-btn.active {
        background: var(--bg-primary);
        color: var(--text-primary);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .ios-tab-content {
        display: none;
    }

    .ios-tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* iOS Form */
    .ios-form-group {
        margin-bottom: 20px;
    }

    .ios-form-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }

    .ios-form-input {
        width: 100%;
        padding: 14px 16px;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        background: var(--bg-secondary);
        font-size: 16px;
        color: var(--text-primary);
        transition: all 0.2s ease;
    }

    .ios-form-input:focus {
        outline: none;
        border-color: var(--ios-blue);
        box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.15);
    }

    .ios-form-input:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .ios-form-hint {
        font-size: 13px;
        color: var(--text-secondary);
        margin-top: 6px;
    }

    /* iOS Button */
    .ios-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px 24px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
    }

    .ios-btn.primary {
        background: var(--ios-blue);
        color: white;
    }

    .ios-btn.primary:hover {
        background: #0070e0;
    }

    .ios-btn.success {
        background: var(--ios-green);
        color: white;
    }

    .ios-btn:active {
        transform: scale(0.98);
    }

    /* iOS Activity List */
    .ios-activity-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .ios-activity-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.15s ease;
    }

    .ios-activity-item:last-child {
        border-bottom: none;
    }

    .ios-activity-item:hover {
        background: var(--bg-subtle);
    }

    .ios-activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 16px;
    }

    .ios-activity-icon.code {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    .ios-activity-content {
        flex: 1;
        min-width: 0;
    }

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

    .ios-activity-desc strong {
        color: var(--ios-blue);
        font-weight: 600;
    }

    .ios-activity-time {
        font-size: 13px;
        color: var(--text-muted);
        flex-shrink: 0;
        text-align: right;
    }

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

    .ios-empty-state p {
        font-size: 14px;
        margin: 0;
    }

    /* iOS Menu Modal */
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
    }

    .ios-menu-item-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: white;
    }

    .ios-menu-item-icon.blue { background: var(--ios-blue); }
    .ios-menu-item-icon.green { background: var(--ios-green); }
    .ios-menu-item-icon.orange { background: var(--ios-orange); }
    .ios-menu-item-icon.purple { background: var(--ios-purple); }
    .ios-menu-item-icon.red { background: var(--ios-red); }

    .ios-menu-item-label {
        font-size: 15px;
        font-weight: 500;
    }

    .ios-menu-item-chevron {
        color: var(--text-secondary);
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

    /* Notification Toggle */
    .ios-toggle-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 16px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .ios-toggle-card:hover {
        background: var(--bg-subtle);
    }

    .ios-toggle-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .ios-toggle-content {
        flex: 1;
    }

    .ios-toggle-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }

    .ios-toggle-desc {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Grid Layout */
    .ios-grid {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 20px;
    }

    /* Mobile Styles */
    @media (max-width: 992px) {
        .ios-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .content-header {
            display: none !important;
        }

        .ios-options-btn {
            display: flex;
        }

        .ios-section-card {
            border-radius: 12px;
        }

        .ios-section-header {
            padding: 16px;
        }

        .ios-section-icon {
            width: 40px;
            height: 40px;
            font-size: 16px;
        }

        .ios-section-body {
            padding: 16px;
        }

        .ios-profile-avatar {
            width: 80px;
            height: 80px;
            font-size: 2rem;
        }

        .ios-tabs {
            flex-wrap: nowrap;
            overflow-x: auto;
        }

        .ios-tab-btn {
            padding: 10px 14px;
            font-size: 13px;
        }

        .ios-info-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .ios-info-value {
            max-width: 100%;
            text-align: left;
        }

        .ios-activity-item {
            padding: 14px 16px;
        }

        .ios-activity-time {
            display: none;
        }

        .ios-activity-desc {
            font-size: 13px;
        }
    }

    /* Extra small screens */
    @media (max-width: 480px) {
        .ios-section-header {
            padding: 14px;
            gap: 12px;
        }

        .ios-section-icon {
            width: 36px;
            height: 36px;
            font-size: 14px;
            border-radius: 10px;
        }

        .ios-section-title h5 {
            font-size: 15px;
        }

        .ios-section-title p {
            font-size: 12px;
        }

        .ios-section-body {
            padding: 14px;
        }

        .ios-profile-avatar {
            width: 70px;
            height: 70px;
            font-size: 1.75rem;
            margin-bottom: 12px;
        }

        .ios-profile-name {
            font-size: 18px;
        }

        .ios-profile-username {
            font-size: 14px;
            margin-bottom: 12px;
        }

        .ios-profile-badges {
            gap: 8px;
        }

        .ios-badge {
            padding: 5px 10px;
            font-size: 12px;
        }

        .ios-info-item {
            padding: 12px 0;
        }

        .ios-info-label {
            font-size: 13px;
            gap: 8px;
        }

        .ios-info-label i {
            width: 18px;
            font-size: 14px;
        }

        .ios-info-value {
            font-size: 14px;
            margin-top: 2px;
        }

        .ios-form-group {
            margin-bottom: 16px;
        }

        .ios-form-label {
            font-size: 12px;
        }

        .ios-form-input {
            padding: 12px 14px;
            font-size: 15px;
        }

        .ios-form-hint {
            font-size: 12px;
        }

        .ios-btn {
            padding: 12px 20px;
            font-size: 15px;
            width: 100%;
        }

        .ios-tabs {
            padding: 3px;
            margin-bottom: 16px;
        }

        .ios-tab-btn {
            padding: 8px 12px;
            font-size: 12px;
        }

        .ios-options-btn {
            width: 32px;
            height: 32px;
        }

        .ios-options-btn i {
            font-size: 14px;
        }
    }

    /* Very small screens (iPhone SE, etc.) */
    @media (max-width: 390px) {
        .ios-section-header {
            padding: 12px;
            gap: 10px;
        }

        .ios-section-icon {
            width: 32px;
            height: 32px;
            font-size: 13px;
        }

        .ios-section-title h5 {
            font-size: 14px;
        }

        .ios-section-title p {
            font-size: 11px;
        }

        .ios-section-body {
            padding: 12px;
        }

        .ios-profile-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }

        .ios-profile-name {
            font-size: 16px;
        }

        .ios-profile-username {
            font-size: 13px;
        }

        .ios-badge {
            padding: 4px 8px;
            font-size: 11px;
        }

        .ios-badge-dot {
            width: 6px;
            height: 6px;
        }

        .ios-info-label {
            font-size: 12px;
        }

        .ios-info-value {
            font-size: 13px;
        }

        .ios-tab-btn {
            padding: 8px 10px;
            font-size: 11px;
        }

        .ios-tab-btn i {
            display: none;
        }

        .ios-form-input {
            padding: 10px 12px;
            font-size: 14px;
        }

        .ios-btn {
            padding: 10px 16px;
            font-size: 14px;
        }
    }
</style>

<!-- Dasher UI Content Area -->
<div class="content">
    <!-- Content Header -->
    <div class="content-header">
        <div>
            <h1 class="content-title">My Profile</h1>
            <nav class="content-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">My Profile</li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- iOS Grid Layout -->
    <div class="ios-grid">
        <!-- Left Column - Profile Card -->
        <div>
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Profile</h5>
                        <p>Your account information</p>
                    </div>
                    <!-- Mobile 3-Dot Menu Button -->
                    <button class="ios-options-btn" id="iosOptionsBtn" aria-label="Open menu">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>

                <div class="ios-section-body">
                    <!-- User Avatar and Basic Info -->
                    <div class="ios-profile-avatar" style="background: <?php echo getUserAvatarGradient($currentUser->getRole()); ?>">
                        <?php echo strtoupper(substr($currentUser->getFullName() ?: $currentUser->getUsername(), 0, 1)); ?>
                    </div>

                    <h3 class="ios-profile-name"><?php echo htmlspecialchars($currentUser->getFullName()); ?></h3>
                    <p class="ios-profile-username">@<?php echo htmlspecialchars($currentUser->getUsername()); ?></p>

                    <div class="ios-profile-badges">
                        <span class="ios-badge role">
                            <span class="ios-badge-dot"></span>
                            <?php echo ucwords(str_replace('_', ' ', $currentUser->getRole())); ?>
                        </span>
                        <span class="ios-badge status-<?php echo $currentUser->getStatus(); ?>">
                            <span class="ios-badge-dot"></span>
                            <?php echo ucfirst($currentUser->getStatus()); ?>
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
                            <span class="ios-info-value"><?php echo htmlspecialchars($currentUser->getEmail()); ?></span>
                        </li>

                        <?php if ($userData && $userData['phone']): ?>
                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-phone icon-green"></i>
                                Phone
                            </span>
                            <span class="ios-info-value"><?php echo htmlspecialchars($userData['phone']); ?></span>
                        </li>
                        <?php endif; ?>

                        <?php if ($householdAddress): ?>
                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-home icon-red"></i>
                                Household
                            </span>
                            <span class="ios-info-value"><?php echo htmlspecialchars($householdAddress); ?></span>
                        </li>
                        <?php endif; ?>

                        <?php if ($clan): ?>
                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-building icon-purple"></i>
                                Community
                            </span>
                            <span class="ios-info-value">
                                <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($clan->getId()); ?>">
                                    <?php echo htmlspecialchars($clan->getName()); ?>
                                </a>
                            </span>
                        </li>
                        <?php endif; ?>

                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-calendar-alt icon-orange"></i>
                                Joined
                            </span>
                            <span class="ios-info-value">
                                <?php echo $userData && $userData['created_at'] ? date('M d, Y', strtotime($userData['created_at'])) : 'Unknown'; ?>
                            </span>
                        </li>

                        <li class="ios-info-item">
                            <span class="ios-info-label">
                                <i class="fas fa-clock icon-teal"></i>
                                Last Login
                            </span>
                            <span class="ios-info-value">
                                <?php echo $userData && $userData['last_login'] ? date('M d, Y g:i A', strtotime($userData['last_login'])) : 'Never'; ?>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Right Column - Edit Forms -->
        <div>
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon green">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Settings</h5>
                        <p>Manage your account settings</p>
                    </div>
                </div>

                <div class="ios-section-body">
                    <!-- iOS Tabs -->
                    <div class="ios-tabs">
                        <button class="ios-tab-btn active" data-tab="profile-info">
                            <i class="fas fa-user me-2"></i>Profile
                        </button>
                        <button class="ios-tab-btn" data-tab="change-password">
                            <i class="fas fa-key me-2"></i>Password
                        </button>
                        <button class="ios-tab-btn" data-tab="notifications">
                            <i class="fas fa-bell me-2"></i>Notifications
                        </button>
                    </div>

                    <!-- Profile Information Tab -->
                    <div class="ios-tab-content active" id="profile-info">
                        <?php if ($error): ?>
                            <div class="ios-flash-message error">
                                <div class="ios-flash-icon"><i class="fas fa-times" style="font-size: 12px;"></i></div>
                                <div class="ios-flash-content">
                                    <p class="ios-flash-title">Error</p>
                                    <p class="ios-flash-text"><?php echo $error; ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="ios-flash-message success">
                                <div class="ios-flash-icon"><i class="fas fa-check" style="font-size: 12px;"></i></div>
                                <div class="ios-flash-content">
                                    <p class="ios-flash-title">Success</p>
                                    <p class="ios-flash-text"><?php echo $success; ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="">
                            <div class="ios-form-group">
                                <label class="ios-form-label">Full Name <span style="color: var(--ios-red);">*</span></label>
                                <input type="text" class="ios-form-input" name="full_name"
                                       value="<?php echo htmlspecialchars($currentUser->getFullName()); ?>" required>
                            </div>

                            <div class="ios-form-group">
                                <label class="ios-form-label">Email Address <?php if (!$isNormalUser): ?><span style="color: var(--ios-red);">*</span><?php endif; ?></label>
                                <input type="email" class="ios-form-input" name="email"
                                       value="<?php echo htmlspecialchars($currentUser->getEmail()); ?>"
                                       <?php echo $isNormalUser ? 'readonly disabled style="opacity: 0.7; cursor: not-allowed;"' : 'required'; ?>>
                                <?php if ($isNormalUser): ?>
                                    <p class="ios-form-hint"><i class="fas fa-lock me-1"></i>Contact admin to change email</p>
                                <?php endif; ?>
                            </div>

                            <div class="ios-form-group">
                                <label class="ios-form-label">Phone Number</label>
                                <input type="tel" class="ios-form-input" name="phone"
                                       value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>">
                            </div>

                            <!-- <div class="ios-form-group">
                                <label class="ios-form-label">Destination</label>
                                <input type="text" class="ios-form-input" name="destination"
                                       value="<?php echo htmlspecialchars($householdAddress); ?>"
                                       placeholder="No household assigned"
                                       readonly disabled style="opacity: 0.7; cursor: not-allowed;">
                                <p class="ios-form-hint">
                                    <i class="fas fa-home me-1"></i>
                                    <?php if ($householdAddress): ?>
                                        Automatically set to your household
                                    <?php else: ?>
                                        You are not assigned to a household
                                    <?php endif; ?>
                                </p>
                            </div> -->

                            <button type="submit" name="update_profile" class="ios-btn primary">
                                <i class="fas fa-save"></i>
                                Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Change Password Tab -->
                    <div class="ios-tab-content" id="change-password">
                        <?php if ($passwordError): ?>
                            <div class="ios-flash-message error">
                                <div class="ios-flash-icon"><i class="fas fa-times" style="font-size: 12px;"></i></div>
                                <div class="ios-flash-content">
                                    <p class="ios-flash-title">Error</p>
                                    <p class="ios-flash-text"><?php echo $passwordError; ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($passwordSuccess): ?>
                            <div class="ios-flash-message success">
                                <div class="ios-flash-icon"><i class="fas fa-check" style="font-size: 12px;"></i></div>
                                <div class="ios-flash-content">
                                    <p class="ios-flash-title">Success</p>
                                    <p class="ios-flash-text"><?php echo $passwordSuccess; ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="">
                            <div class="ios-form-group">
                                <label class="ios-form-label">Current Password <span style="color: var(--ios-red);">*</span></label>
                                <input type="password" class="ios-form-input" name="current_password" required>
                            </div>

                            <div class="ios-form-group">
                                <label class="ios-form-label">New Password <span style="color: var(--ios-red);">*</span></label>
                                <input type="password" class="ios-form-input" name="new_password" required>
                                <p class="ios-form-hint">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Password must be at least 8 characters long.
                                </p>
                            </div>

                            <div class="ios-form-group">
                                <label class="ios-form-label">Confirm New Password <span style="color: var(--ios-red);">*</span></label>
                                <input type="password" class="ios-form-input" name="confirm_password" required>
                            </div>

                            <button type="submit" name="change_password" class="ios-btn primary">
                                <i class="fas fa-key"></i>
                                Change Password
                            </button>
                        </form>
                    </div>

                    <!-- Notifications Tab -->
                    <div class="ios-tab-content" id="notifications">
                        <p style="color: var(--text-secondary); margin-bottom: 20px; line-height: 1.6;">
                            Enable push notifications to receive instant alerts about access codes, visitor arrivals, and important updates.
                        </p>

                        <label class="ios-toggle-card">
                            <div class="ios-toggle-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div class="ios-toggle-content">
                                <p class="ios-toggle-title">Push Notifications</p>
                                <p class="ios-toggle-desc">Receive instant notifications on your device</p>
                            </div>
                            <input type="checkbox" id="push-notification-toggle" data-user-id="<?php echo $_SESSION['user_id']; ?>"
                                   style="width: 20px; height: 20px; cursor: pointer;">
                        </label>

                        <div id="notification-status" style="margin-top: 16px; padding: 14px; border-radius: 12px; display: none;"></div>

                        <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                            <p style="font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 12px;">
                                Test Your Notifications
                            </p>
                            <button type="button" id="test-push-notification" class="ios-btn primary" disabled style="opacity: 0.6;">
                                <i class="fas fa-paper-plane"></i>
                                Send Test Notification
                            </button>
                            <p class="ios-form-hint" style="margin-top: 8px;">
                                <i class="fas fa-info-circle me-1"></i>
                                Enable push notifications first to test
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon orange">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Recent Activity</h5>
                        <p>Your latest actions</p>
                    </div>
                </div>

                <div class="ios-section-body no-padding">
                    <?php if (empty($recentCodes)): ?>
                        <div class="ios-empty-state">
                            <i class="fas fa-history"></i>
                            <h4>No Recent Activity</h4>
                            <p>Your recent actions will appear here.</p>
                        </div>
                    <?php else: ?>
                        <ul class="ios-activity-list">
                            <?php foreach ($recentCodes as $code): ?>
                                <li class="ios-activity-item">
                                    <div class="ios-activity-icon code">
                                        <i class="fas fa-qrcode"></i>
                                    </div>
                                    <div class="ios-activity-content">
                                        <p class="ios-activity-title">Access Code Generated</p>
                                        <p class="ios-activity-desc">
                                            Code <strong><?php echo htmlspecialchars($code['code']); ?></strong> for
                                            <?php echo htmlspecialchars($code['visitor_name']); ?>
                                            (<?php echo htmlspecialchars($code['purpose']); ?>)
                                        </p>
                                    </div>
                                    <span class="ios-activity-time">
                                        <?php echo date('M d', strtotime($code['created_at'])); ?><br>
                                        <small><?php echo date('g:i A', strtotime($code['created_at'])); ?></small>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- iOS-Style Mobile Menu Modal -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Profile</h3>
        <button class="ios-menu-close" id="iosMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="ios-menu-content">
        <!-- Profile Summary -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Account</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Name</span>
                    <span class="ios-menu-stat-value"><?php echo htmlspecialchars($currentUser->getFullName()); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Username</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-blue);">@<?php echo htmlspecialchars($currentUser->getUsername()); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Role</span>
                    <span class="ios-menu-stat-value"><?php echo ucwords(str_replace('_', ' ', $currentUser->getRole())); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Status</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-green);"><?php echo ucfirst($currentUser->getStatus()); ?></span>
                </div>
                <?php if ($householdAddress): ?>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Household</span>
                    <span class="ios-menu-stat-value" style="font-size: 13px;"><?php echo htmlspecialchars($householdAddress); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <div class="ios-menu-item" onclick="switchToTab('profile-info')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="ios-menu-item-label">Edit Profile</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <div class="ios-menu-item" onclick="switchToTab('change-password')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange">
                            <i class="fas fa-key"></i>
                        </div>
                        <span class="ios-menu-item-label">Change Password</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <div class="ios-menu-item" onclick="switchToTab('notifications')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple">
                            <i class="fas fa-bell"></i>
                        </div>
                        <span class="ios-menu-item-label">Notifications</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green">
                            <i class="fas fa-home"></i>
                        </div>
                        <span class="ios-menu-item-label">Back to Dashboard</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initializeIosTabs();
    initializeIosMenu();
    handleHashNavigation();
    initializeTestNotificationButton();
});

// iOS Tabs
function initializeIosTabs() {
    const tabBtns = document.querySelectorAll('.ios-tab-btn');
    const tabContents = document.querySelectorAll('.ios-tab-content');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');

            tabBtns.forEach(b => b.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));

            this.classList.add('active');
            document.getElementById(targetTab).classList.add('active');

            history.replaceState(null, null, '#' + targetTab);
        });
    });
}

// Switch to tab (for menu items)
function switchToTab(tabId) {
    closeIosMenu();
    setTimeout(() => {
        const tabBtn = document.querySelector(`[data-tab="${tabId}"]`);
        if (tabBtn) tabBtn.click();
    }, 300);
}

// iOS Menu
function initializeIosMenu() {
    const backdrop = document.getElementById('iosMenuBackdrop');
    const modal = document.getElementById('iosMenuModal');
    const optionsBtn = document.getElementById('iosOptionsBtn');
    const closeBtn = document.getElementById('iosMenuClose');

    if (optionsBtn) {
        optionsBtn.addEventListener('click', openIosMenu);
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeIosMenu);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeIosMenu);
    }

    // Swipe to close
    let startY = 0;
    let currentY = 0;

    modal.addEventListener('touchstart', (e) => {
        startY = e.touches[0].clientY;
    }, { passive: true });

    modal.addEventListener('touchmove', (e) => {
        currentY = e.touches[0].clientY;
        const diff = currentY - startY;
        if (diff > 0) {
            modal.style.transform = `translateY(${diff}px)`;
        }
    }, { passive: true });

    modal.addEventListener('touchend', () => {
        const diff = currentY - startY;
        if (diff > 100) {
            closeIosMenu();
        }
        modal.style.transform = '';
        startY = 0;
        currentY = 0;
    }, { passive: true });
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

// Hash navigation
function handleHashNavigation() {
    if (window.location.hash) {
        const targetTab = window.location.hash.substring(1);
        const tabBtn = document.querySelector(`[data-tab="${targetTab}"]`);
        if (tabBtn) {
            setTimeout(() => tabBtn.click(), 100);
        }
    }
}

// Test notification button
function initializeTestNotificationButton() {
    const testButton = document.getElementById('test-push-notification');
    const toggleButton = document.getElementById('push-notification-toggle');

    if (!testButton || !toggleButton) return;

    testButton.addEventListener('click', async function() {
        const button = this;
        const originalText = button.innerHTML;

        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

        try {
            const response = await fetch('/api/send-notification.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    user_id: <?php echo $_SESSION['user_id']; ?>,
                    title: 'GateWey Test Notification',
                    body: 'Push notifications are working perfectly!',
                    url: '<?php echo BASE_URL; ?>dashboard/',
                    icon: '<?php echo BASE_URL; ?>assets/images/icons/icon-192x192.png'
                })
            });

            const result = await response.json();

            if (result.success) {
                showNotificationStatus('success', 'Test notification sent successfully!');
            } else {
                showNotificationStatus('error', result.message || 'Failed to send notification');
            }
        } catch (error) {
            showNotificationStatus('error', 'Error: ' + error.message);
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    });

    toggleButton.addEventListener('change', function() {
        testButton.disabled = !this.checked;
        testButton.style.opacity = this.checked ? '1' : '0.6';
    });

    setTimeout(() => {
        testButton.disabled = !toggleButton.checked;
        testButton.style.opacity = toggleButton.checked ? '1' : '0.6';
    }, 1000);
}

function showNotificationStatus(type, message) {
    const statusDiv = document.getElementById('notification-status');
    if (!statusDiv) return;

    statusDiv.style.display = 'block';
    statusDiv.style.backgroundColor = type === 'success' ? 'rgba(48, 209, 88, 0.15)' : 'rgba(255, 69, 58, 0.15)';
    statusDiv.style.border = `1px solid ${type === 'success' ? 'rgba(48, 209, 88, 0.3)' : 'rgba(255, 69, 58, 0.3)'}`;
    statusDiv.style.color = type === 'success' ? 'var(--ios-green)' : 'var(--ios-red)';
    statusDiv.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>${message}`;

    setTimeout(() => {
        statusDiv.style.display = 'none';
    }, 5000);
}
</script>

<?php
/**
 * Helper function to get avatar gradient
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

// Include footer
include_once '../includes/footer.php';
?>
