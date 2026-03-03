<?php
/**
 * GateWey - Create Announcement Page
 * Allows admins and users to create announcements with push notifications
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/PushNotificationService.php';

// Set page title and enable charts
$pageTitle = 'Create Announcement';
$includeCharts = true;
// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$db = Database::getInstance();
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$userRole = $currentUser->getRole();
$clanId = $currentUser->getClanId();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';
    $isPinned = isset($_POST['is_pinned']) && ($_POST['is_pinned'] == '1') ? 1 : 0;
    
    // Validation
    if (empty($title)) {
        $error = 'Title is required';
    } elseif (empty($content)) {
        $error = 'Content is required';
    } else {
        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/announcements/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $fileName = uniqid('announcement_') . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    $imagePath = 'uploads/announcements/' . $fileName;
                }
            }
        }
        
        // Only allow pinning for admins
        if (!in_array($userRole, ['super_admin', 'clan_admin'])) {
            $isPinned = 0;
        }
        
        try {
            // Insert announcement
            $db->query(
                "INSERT INTO announcements (title, content, author_id, author_name, author_role, clan_id, is_pinned, priority, image_path, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $title,
                    $content,
                    $currentUser->getId(),
                    $currentUser->getFullName(),
                    $userRole,
                    $clanId,
                    $isPinned,
                    $priority,
                    $imagePath
                ]
            );
            
            $announcementId = $db->lastInsertId();
            
            // Send push notifications to all clan members
            if ($announcementId) {
                // Get all clan members except the author
                $clanMembers = $db->fetchAll(
                    "SELECT DISTINCT u.id FROM users u
                     WHERE u.clan_id = ? AND u.id != ? AND u.status = 'active'",
                    [$clanId, $currentUser->getId()]
                );
                
                // Prepare notification
                $notificationTitle = '🔔 New Announcement';
                if ($priority === 'urgent') {
                    $notificationTitle = '🚨 URGENT: New Announcement';
                } elseif ($isPinned) {
                    $notificationTitle = '📌 Pinned Announcement';
                }
                
                $notificationBody = $currentUser->getFullName() . ': ' . substr($title, 0, 100);
                $notificationUrl = BASE_URL . 'announcements/view.php?id=' . encryptId($announcementId);
                
                // Send notifications using PushNotificationService
                $pushService = PushNotificationService::getInstance();
                $sentCount = 0;
                
                foreach ($clanMembers as $member) {
                    $result = $pushService->sendToUser(
                        $member['id'],
                        $notificationTitle,
                        $notificationBody,
                        $notificationUrl
                    );
                    
                    if ($result['success']) {
                        $sentCount += $result['sent'];
                    }
                }
                
                // Log notification activity
                $db->query(
                    "INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) 
                     VALUES (?, 'announcement_created', ?, ?, NOW())",
                    [
                        $currentUser->getId(),
                        "Created announcement: {$title} | Notifications sent: {$sentCount}",
                        $_SERVER['REMOTE_ADDR']
                    ]
                );
                
                $success = "Announcement created successfully! Push notifications sent to {$sentCount} member(s).";
            }
            
            // Clear form
            $title = $content = '';
            $priority = 'normal';
            $isPinned = 0;
            
        } catch (Exception $e) {
            $error = 'Failed to create announcement: ' . $e->getMessage();
            error_log("Announcement creation error: " . $e->getMessage());
        }
    }
}

include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>

<!-- iOS-Style Create Announcement Styles -->
<style>
:root {
    --ios-red: #FF453A;
    --ios-orange: #FF9F0A;
    --ios-yellow: #FFD60A;
    --ios-green: var(--success, #22c55e);
    --ios-teal: #64D2FF;
    --ios-blue: #0A84FF;
    --ios-purple: #BF5AF2;
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

.ios-section-title {
    flex: 1;
    min-width: 0;
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

/* 3-Dot Menu Button */
.ios-options-btn {
    display: flex;
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

/* Form Container */
.form-container {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: var(--spacing-4);
    padding: var(--spacing-4);
}

@media (max-width: 992px) {
    .form-container {
        grid-template-columns: 1fr;
    }
}

/* iOS Form Card */
.ios-form-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    overflow: hidden;
}

.ios-form-header {
    padding: 16px;
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 12px;
}

.ios-form-icon {
    width: 44px;
    height: 44px;
    background: var(--ios-green);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 18px;
    flex-shrink: 0;
}

.ios-form-header-content {
    flex: 1;
}

.ios-form-title {
    margin: 0;
    font-size: 17px;
    font-weight: 600;
    color: var(--text-primary);
}

.ios-form-subtitle {
    margin: 4px 0 0 0;
    font-size: 13px;
    color: var(--text-secondary);
}

/* Form Body */
.ios-form-body {
    padding: 16px;
}

.ios-form-group {
    margin-bottom: 20px;
}

.ios-form-label {
    display: block;
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 8px;
    font-size: 14px;
}

.ios-form-label.required::after {
    content: ' *';
    color: var(--ios-red);
}

.ios-form-control {
    display: block;
    width: 100%;
    padding: 12px 14px;
    font-size: 15px;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    color: var(--text-primary);
    transition: all 0.2s ease;
}

.ios-form-control:focus {
    border-color: var(--ios-blue);
    box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.15);
    outline: none;
}

.ios-form-control::placeholder {
    color: var(--text-muted);
}

textarea.ios-form-control {
    min-height: 140px;
    resize: vertical;
}

.ios-form-text {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 6px;
}

/* iOS Priority Preview */
.ios-priority-preview {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 8px;
}

.ios-priority-preview.urgent {
    background: rgba(255, 69, 58, 0.15);
    color: var(--ios-red);
}

.ios-priority-preview.high {
    background: rgba(255, 159, 10, 0.15);
    color: var(--ios-orange);
}

.ios-priority-preview.normal {
    background: rgba(10, 132, 255, 0.15);
    color: var(--ios-blue);
}

.ios-priority-preview.low {
    background: rgba(100, 210, 255, 0.15);
    color: var(--ios-teal);
}

@keyframes pulse-dot {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.1); }
}

.ios-priority-preview.urgent {
    animation: pulse-dot 2s ease-in-out infinite;
}

/* iOS Image Upload */
.ios-image-upload-area {
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    background: var(--bg-subtle);
    transition: all 0.2s ease;
    cursor: pointer;
}

.ios-image-upload-area:hover {
    border-color: var(--ios-blue);
    background: rgba(10, 132, 255, 0.05);
}

.ios-upload-icon {
    font-size: 40px;
    color: var(--text-muted);
    margin-bottom: 12px;
}

.ios-upload-text {
    font-size: 15px;
    color: var(--text-secondary);
    margin-bottom: 6px;
}

.ios-image-preview {
    margin-top: 16px;
    max-width: 100%;
    max-height: 280px;
    border-radius: 12px;
    display: none;
}

#image-input {
    display: none;
}

/* iOS Checkbox Wrapper */
.ios-checkbox-wrapper {
    position: relative;
}

.ios-form-checkbox {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.ios-checkbox-label {
    display: flex;
    align-items: center;
    padding: 14px 16px;
    background: var(--bg-subtle);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.ios-checkbox-label::before {
    content: '';
    width: 22px;
    height: 22px;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    margin-right: 12px;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.ios-form-checkbox:checked + .ios-checkbox-label {
    background: rgba(10, 132, 255, 0.1);
    border-color: var(--ios-blue);
}

.ios-form-checkbox:checked + .ios-checkbox-label::before {
    border-color: var(--ios-blue);
    background: var(--ios-blue);
}

.ios-form-checkbox:checked + .ios-checkbox-label::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    left: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: white;
    font-size: 12px;
}

.ios-checkbox-content {
    flex: 1;
}

.ios-checkbox-title {
    font-weight: 500;
    color: var(--text-primary);
    font-size: 15px;
}

.ios-checkbox-description {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 3px;
}

/* iOS Form Actions */
.ios-form-actions {
    display: flex;
    gap: 12px;
    padding: 16px;
    border-top: 1px solid var(--border-color);
    background: var(--bg-subtle);
    justify-content: flex-end;
}

.ios-submit-btn {
    padding: 12px 24px;
    background: var(--ios-green);
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.15s ease;
}

.ios-submit-btn:hover {
    background: #1fa855;
}

.ios-cancel-btn {
    padding: 12px 24px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    color: var(--text-primary);
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    transition: all 0.15s ease;
}

.ios-cancel-btn:hover {
    background: var(--bg-hover);
}

@media (max-width: 576px) {
    .ios-form-actions {
        flex-direction: column;
    }

    .ios-submit-btn,
    .ios-cancel-btn {
        justify-content: center;
    }
}

/* iOS Tips Card */
.ios-tips-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    overflow: hidden;
    height: fit-content;
    position: sticky;
    top: var(--spacing-4);
}

.ios-tips-header {
    padding: 14px 16px;
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 10px;
}

.ios-tips-icon {
    color: var(--ios-orange);
    font-size: 18px;
}

.ios-tips-title {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--text-primary);
}

.ios-tips-content {
    padding: 16px;
}

.ios-tip-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
}

.ios-tip-item:last-child {
    margin-bottom: 0;
}

.ios-tip-icon {
    color: var(--ios-green);
    font-size: 13px;
    margin-top: 3px;
    flex-shrink: 0;
}

.ios-tip-text {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.5;
}

.ios-tip-text strong {
    color: var(--text-primary);
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .content-header {
        display: none !important;
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

    .form-container {
        padding: var(--spacing-3);
    }

    .ios-form-header {
        flex-direction: column;
        text-align: center;
    }

    .ios-form-icon {
        margin: 0 auto;
    }

    .ios-tips-card {
        position: static;
        display: none;
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

<!-- Use Standard Dasher UI Content Container -->
<div class="content">
    <!-- Content Header (Hidden on Mobile) -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="content-title">
                    <i class="fas fa-plus-circle me-2"></i>Create Announcement
                </h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>announcements/" class="breadcrumb-link">Announcements</a>
                        </li>
                        <li class="breadcrumb-item active">Create</li>
                    </ol>
                </nav>
            </div>
            <div class="content-actions">
                <a href="<?php echo BASE_URL; ?>announcements/" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back</span>
                </a>
            </div>
        </div>
    </div>

    <!-- iOS Section Card -->
    <div class="ios-section-card">
        <!-- Section Header with 3-Dot Menu -->
        <div class="ios-section-header">
            <div class="ios-section-icon primary">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div class="ios-section-title">
                <h5>Create Announcement</h5>
                <p>Share important updates with your estate community</p>
            </div>
            <!-- 3-Dot Menu Button -->
            <button class="ios-options-btn" id="iosOptionsBtn" aria-label="Open menu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Form Container Grid -->
        <div class="form-container">
            <!-- Form Card -->
            <div class="ios-form-card">
                <form method="POST" enctype="multipart/form-data" id="announcement-form">
                    <div class="ios-form-body">
                        <!-- Title -->
                        <div class="ios-form-group">
                            <label class="ios-form-label required" for="title">Title</label>
                            <input type="text"
                                   class="ios-form-control"
                                   id="title"
                                   name="title"
                                   placeholder="Enter announcement title"
                                   value="<?php echo htmlspecialchars($title ?? ''); ?>"
                                   required
                                   maxlength="255">
                            <div class="ios-form-text">A clear, concise title that summarizes your announcement</div>
                        </div>

                        <!-- Content -->
                        <div class="ios-form-group">
                            <label class="ios-form-label required" for="content">Content</label>
                            <textarea class="ios-form-control"
                                      id="content"
                                      name="content"
                                      placeholder="Enter announcement content"
                                      required
                                      rows="8"><?php echo htmlspecialchars($content ?? ''); ?></textarea>
                            <div class="ios-form-text">Provide detailed information about your announcement</div>
                        </div>

                        <!-- Priority -->
                        <div class="ios-form-group">
                            <label class="ios-form-label" for="priority">Priority</label>
                            <select class="ios-form-control" id="priority" name="priority" onchange="updatePriorityPreview()">
                                <option value="low" <?php echo (($priority ?? 'normal') === 'low') ? 'selected' : ''; ?>>Low</option>
                                <option value="normal" <?php echo (($priority ?? 'normal') === 'normal') ? 'selected' : ''; ?>>Normal</option>
                                <option value="high" <?php echo (($priority ?? 'normal') === 'high') ? 'selected' : ''; ?>>High</option>
                                <option value="urgent" <?php echo (($priority ?? 'normal') === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                            <div class="ios-form-text">
                                Select the priority level
                                <span id="priority-preview" class="ios-priority-preview normal">NORMAL</span>
                            </div>
                        </div>

                        <!-- Image Upload -->
                        <div class="ios-form-group">
                            <label class="ios-form-label">Image (Optional)</label>
                            <div class="ios-image-upload-area" onclick="document.getElementById('image-input').click()">
                                <div class="ios-upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="ios-upload-text">Click to upload image</div>
                                <div class="ios-form-text">JPG, PNG, GIF or WebP (Max 5MB)</div>
                            </div>
                            <input type="file"
                                   id="image-input"
                                   name="image"
                                   accept="image/jpeg,image/png,image/gif,image/webp"
                                   onchange="previewImage(this)">
                            <img id="image-preview" class="ios-image-preview" alt="Preview">
                        </div>

                        <!-- Admin Options -->
                        <?php if (in_array($userRole, ['super_admin', 'clan_admin'])): ?>
                            <div class="ios-form-group">
                                <label class="ios-form-label">Admin Options</label>
                                <div class="ios-checkbox-wrapper">
                                    <input type="checkbox"
                                           class="ios-form-checkbox"
                                           id="is_pinned"
                                           name="is_pinned"
                                           value="1"
                                           <?php echo ($isPinned ?? 0) ? 'checked' : ''; ?>>
                                    <label class="ios-checkbox-label" for="is_pinned">
                                        <div class="ios-checkbox-content">
                                            <div class="ios-checkbox-title">
                                                <i class="fas fa-thumbtack me-2"></i>Pin this announcement
                                            </div>
                                            <div class="ios-checkbox-description">
                                                Pinned announcements appear at the top of the list
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Form Actions -->
                    <div class="ios-form-actions">
                        <button type="submit" class="ios-submit-btn">
                            <i class="fas fa-paper-plane"></i>
                            Publish Announcement
                        </button>
                        <a href="<?php echo BASE_URL; ?>announcements/" class="ios-cancel-btn">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Quick Tips Card -->
            <div class="ios-tips-card">
                <div class="ios-tips-header">
                    <i class="fas fa-lightbulb ios-tips-icon"></i>
                    <h3 class="ios-tips-title">Quick Tips</h3>
                </div>
                <div class="ios-tips-content">
                    <div class="ios-tip-item">
                        <i class="fas fa-bullhorn ios-tip-icon"></i>
                        <div class="ios-tip-text">
                            <strong>Title:</strong> Keep it clear and concise - this appears in notifications
                        </div>
                    </div>
                    <div class="ios-tip-item">
                        <i class="fas fa-align-left ios-tip-icon"></i>
                        <div class="ios-tip-text">
                            <strong>Content:</strong> Provide all necessary details. Members can't reply to announcements
                        </div>
                    </div>
                    <div class="ios-tip-item">
                        <i class="fas fa-exclamation-triangle ios-tip-icon"></i>
                        <div class="ios-tip-text">
                            <strong>Priority:</strong> Use "Urgent" sparingly to maintain its impact
                        </div>
                    </div>
                    <div class="ios-tip-item">
                        <i class="fas fa-image ios-tip-icon"></i>
                        <div class="ios-tip-text">
                            <strong>Images:</strong> Add visuals to make announcements more engaging
                        </div>
                    </div>
                    <div class="ios-tip-item">
                        <i class="fas fa-thumbtack ios-tip-icon"></i>
                        <div class="ios-tip-text">
                            <strong>Pinned Posts:</strong> Only admins can pin announcements to keep them at the top
                        </div>
                    </div>
                    <div class="ios-tip-item">
                        <i class="fas fa-bell ios-tip-icon"></i>
                        <div class="ios-tip-text">
                            <strong>Notifications:</strong> All active estate members receive push notifications automatically
                        </div>
                    </div>
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
        <h3 class="ios-menu-title">Options</h3>
        <button class="ios-menu-close" id="iosMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="ios-menu-content">
        <!-- Quick Actions Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <div class="ios-menu-item" onclick="document.getElementById('announcement-form').submit();">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Publish Now</span>
                            <span class="ios-menu-item-desc">Publish your announcement</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <div class="ios-menu-item" onclick="document.getElementById('announcement-form').reset(); closeIosMenu();">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-redo"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Reset Form</span>
                            <span class="ios-menu-item-desc">Clear all fields</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
            </div>
        </div>

        <!-- Quick Tips Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Tips</div>
            <div class="ios-menu-card">
                <div class="ios-menu-item" style="cursor: default;">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Title</span>
                            <span class="ios-menu-item-desc">Keep it clear and concise - this appears in notifications</span>
                        </div>
                    </div>
                </div>
                <div class="ios-menu-item" style="cursor: default;">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-align-left"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Content</span>
                            <span class="ios-menu-item-desc">Provide all necessary details. Members can't reply to announcements</span>
                        </div>
                    </div>
                </div>
                <div class="ios-menu-item" style="cursor: default;">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon red">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Priority</span>
                            <span class="ios-menu-item-desc">Use "Urgent" sparingly to maintain its impact</span>
                        </div>
                    </div>
                </div>
                <div class="ios-menu-item" style="cursor: default;">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple">
                            <i class="fas fa-image"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Images</span>
                            <span class="ios-menu-item-desc">Add visuals to make announcements more engaging</span>
                        </div>
                    </div>
                </div>
                <div class="ios-menu-item" style="cursor: default;">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Notifications</span>
                            <span class="ios-menu-item-desc">All active estate members receive push notifications automatically</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Navigation</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>announcements/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">All Announcements</span>
                            <span class="ios-menu-item-desc">Back to announcements list</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing iOS Create Announcement Page...');

    // Initialize iOS Menu
    initializeIosMenu();

    // Initialize priority preview
    updatePriorityPreview();

    // Show flash notification if there's a success message
    <?php if ($success): ?>
    if (typeof showFlashNotification === 'function') {
        showFlashNotification('<?php echo addslashes($success); ?>', 'success');
    }
    <?php endif; ?>

    <?php if ($error): ?>
    if (typeof showFlashNotification === 'function') {
        showFlashNotification('<?php echo addslashes($error); ?>', 'error');
    }
    <?php endif; ?>
});

function initializeIosMenu() {
    const iosMenuBackdrop = document.getElementById('iosMenuBackdrop');
    const iosMenuModal = document.getElementById('iosMenuModal');
    const iosOptionsBtn = document.getElementById('iosOptionsBtn');
    const iosMenuClose = document.getElementById('iosMenuClose');

    window.openIosMenu = function() {
        iosMenuBackdrop.classList.add('active');
        iosMenuModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeIosMenu = function() {
        iosMenuBackdrop.classList.remove('active');
        iosMenuModal.classList.remove('active');
        document.body.style.overflow = '';
    };

    if (iosOptionsBtn) {
        iosOptionsBtn.addEventListener('click', openIosMenu);
    }

    if (iosMenuClose) {
        iosMenuClose.addEventListener('click', closeIosMenu);
    }

    if (iosMenuBackdrop) {
        iosMenuBackdrop.addEventListener('click', closeIosMenu);
    }

    // Close menu when clicking navigation links
    document.querySelectorAll('.ios-menu-modal .ios-menu-item[href]').forEach(item => {
        item.addEventListener('click', closeIosMenu);
    });

    // Swipe down to close
    let startY = 0;
    let currentY = 0;

    if (iosMenuModal) {
        iosMenuModal.addEventListener('touchstart', (e) => {
            startY = e.touches[0].clientY;
        });

        iosMenuModal.addEventListener('touchmove', (e) => {
            currentY = e.touches[0].clientY;
            const diff = currentY - startY;
            if (diff > 0) {
                iosMenuModal.style.transform = `translateY(${diff}px)`;
            }
        });

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

    console.log('iOS Menu initialized successfully');
}

// Image preview
function previewImage(input) {
    const preview = document.getElementById('image-preview');

    if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };

        reader.readAsDataURL(input.files[0]);
    }
}

// Priority preview update
function updatePriorityPreview() {
    const select = document.getElementById('priority');
    const preview = document.getElementById('priority-preview');
    if (!select || !preview) return;

    const value = select.value;
    preview.className = 'ios-priority-preview ' + value;
    preview.textContent = value.toUpperCase();
}

// Form validation
document.getElementById('announcement-form').addEventListener('submit', function(e) {
    const title = document.getElementById('title').value.trim();
    const content = document.getElementById('content').value.trim();

    if (!title || !content) {
        e.preventDefault();
        if (typeof showFlashNotification === 'function') {
            showFlashNotification('Please fill in all required fields', 'error');
        } else {
            alert('Please fill in all required fields');
        }
        return false;
    }

    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Publishing...';
});
</script>

<?php include_once '../includes/footer.php'; ?>