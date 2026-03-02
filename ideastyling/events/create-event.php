<?php
/**
 * Gate Wey Access Management System
 * User Create Event Form - iOS App Style Design
 * File: user/events/create-event.php
 */

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../classes/User.php';
require_once '../../classes/Clan.php';
require_once '../../classes/Event.php';
require_once '../../classes/notification.php';

// Set page title
$pageTitle = 'Create Event';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL);
    exit;
}

// Get user info
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

// Guards are not allowed here
if ($currentUser->getRole() === 'guard') {
    header('Location: ' . BASE_URL . 'dashboard/guard.php');
    exit;
}

$clanId = $currentUser->getClanId();

// Load clan details
$clan = new Clan();
if (!$clan->loadById($clanId)) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Validate required fields
    if (empty($_POST['title'])) {
        $errors[] = 'Event title is required';
    }

    if (empty($_POST['start_datetime'])) {
        $errors[] = 'Start date and time is required';
    }

    if (empty($_POST['end_datetime'])) {
        $errors[] = 'End date and time is required';
    }

    // Validate dates
    if (!empty($_POST['start_datetime']) && !empty($_POST['end_datetime'])) {
        $startTime = strtotime($_POST['start_datetime']);
        $endTime = strtotime($_POST['end_datetime']);

        if ($startTime >= $endTime) {
            $errors[] = 'End date must be after start date';
        }

        if ($startTime < time()) {
            $errors[] = 'Event cannot start in the past';
        }
    }

    // Validate event type
    if ($_POST['event_type'] === 'other' && empty($_POST['custom_event_type'])) {
        $errors[] = 'Please specify custom event type';
    }

    if (empty($errors)) {
        // Handle image upload
        $imagePath = null;
        if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../../uploads/events/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileExtension = strtolower(pathinfo($_FILES['event_image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($fileExtension, $allowedExtensions)) {
                if ($_FILES['event_image']['size'] <= 5242880) {
                    $fileName = 'event_' . time() . '_' . uniqid() . '.' . $fileExtension;
                    $targetPath = $uploadDir . $fileName;

                    if (move_uploaded_file($_FILES['event_image']['tmp_name'], $targetPath)) {
                        $imagePath = 'uploads/events/' . $fileName;
                    }
                } else {
                    $errors[] = 'Image file size must be less than 5MB';
                }
            } else {
                $errors[] = 'Invalid image format. Please use JPG, PNG, or GIF';
            }
        }

        if (empty($errors)) {
            $eventData = [
                'clan_id' => $clanId,
                'created_by' => $_SESSION['user_id'],
                'title' => trim($_POST['title']),
                'description' => trim($_POST['description'] ?? ''),
                'event_type' => $_POST['event_type'],
                'custom_event_type' => $_POST['event_type'] === 'other' ? trim($_POST['custom_event_type']) : null,
                'location' => trim($_POST['location'] ?? ''),
                'start_datetime' => $_POST['start_datetime'],
                'end_datetime' => $_POST['end_datetime'],
                'max_attendees' => !empty($_POST['max_attendees']) ? (int)$_POST['max_attendees'] : null,
                'registration_deadline' => !empty($_POST['registration_deadline']) ? $_POST['registration_deadline'] : null,
                'status' => 'pending',
                'visibility' => 'all',
                'require_rsvp' => isset($_POST['require_rsvp']) ? 1 : 0,
                'send_reminders' => isset($_POST['send_reminders']) ? 1 : 0,
                'allow_guests' => isset($_POST['allow_guests']) ? 1 : 0,
                'enable_access_codes' => 0,
                'image' => $imagePath,
                'recurring' => 0,
                'recurring_pattern' => null,
                'recurring_end_date' => null,
                'parent_event_id' => null
            ];

            $event = new Event();
            $eventId = $event->create($eventData);

            if ($eventId) {
                try {
                    $notification = new notification();
                    $notification->createForClanAdmins(
                        $clanId,
                        'New Event Pending Approval',
                        $currentUser->getFullName() . ' has created a new event: ' . $eventData['title'],
                        'event_approval',
                        $eventId,
                        'admin/events/view-event.php'
                    );
                } catch (Exception $e) {
                    error_log("Failed to send event approval notification: " . $e->getMessage());
                }

                header('Location: ' . BASE_URL . 'user/events/?success=' . urlencode('Event created successfully and is pending admin approval'));
                exit;
            } else {
                $errors[] = 'Failed to create event. Please try again.';
            }
        }
    }
}

$error = '';
if (!empty($errors)) {
    $error = implode('. ', $errors);
}

// Include header
include_once '../../includes/header.php';
?>

<!-- iOS-Style Create Event -->
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
    .ios-flash-message { display: flex; align-items: flex-start; gap: 12px; padding: 16px; border-radius: 12px; margin-bottom: 20px; animation: slideDown 0.3s ease; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
    .ios-flash-message.error { background: rgba(255, 69, 58, 0.15); border: 1px solid rgba(255, 69, 58, 0.3); }
    .ios-flash-message.info { background: rgba(100, 210, 255, 0.12); border: 1px solid rgba(100, 210, 255, 0.3); }
    .ios-flash-icon { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .ios-flash-message.error .ios-flash-icon { background: var(--ios-red); color: white; }
    .ios-flash-message.info .ios-flash-icon { background: var(--ios-teal); color: white; }
    .ios-flash-content { flex: 1; }
    .ios-flash-title { font-size: 15px; font-weight: 600; margin: 0 0 2px 0; }
    .ios-flash-message.error .ios-flash-title { color: var(--ios-red); }
    .ios-flash-message.info .ios-flash-title { color: var(--ios-teal); }
    .ios-flash-text { font-size: 14px; color: var(--text-secondary); margin: 0; }

    /* iOS Section Card */
    .ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; margin-bottom: 20px; overflow: hidden; }
    .ios-section-header { display: flex; align-items: flex-start; gap: 14px; padding: 20px; background: var(--bg-subtle); border-bottom: 1px solid var(--border-color); }
    .ios-section-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .ios-section-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-section-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-section-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-section-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
    .ios-section-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }
    .ios-section-title { flex: 1; }
    .ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px 0; }
    .ios-section-title p { font-size: 13px; color: var(--text-secondary); margin: 0; }
    .ios-section-body { padding: 20px; }

    /* 3-Dot Menu Button */
    .ios-options-btn { display: none; width: 36px; height: 36px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; margin-left: auto; flex-shrink: 0; }
    .ios-options-btn:hover { background: var(--border-color); }
    .ios-options-btn i { color: var(--text-primary); font-size: 16px; }

    /* iOS Form Controls */
    .ios-form-group { margin-bottom: 20px; }
    .ios-form-group:last-child { margin-bottom: 0; }
    .ios-form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .ios-form-label .required { color: var(--ios-red); }
    .ios-form-control { display: block; width: 100%; padding: 14px 16px; font-size: 16px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; color: var(--text-primary); transition: all 0.2s ease; box-sizing: border-box; }
    .ios-form-control:focus { border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.15); outline: none; }
    .ios-form-control::placeholder { color: var(--text-muted); }
    textarea.ios-form-control { resize: vertical; min-height: 100px; }
    .ios-form-hint { margin-top: 6px; font-size: 13px; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; }
    .ios-form-hint i { font-size: 12px; }

    /* iOS Form Row */
    .ios-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    /* iOS Select */
    .ios-form-select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; background-size: 20px; padding-right: 44px; }

    /* iOS Checkbox */
    .ios-checkbox-group { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; cursor: pointer; transition: all 0.2s ease; margin-bottom: 10px; }
    .ios-checkbox-group:last-child { margin-bottom: 0; }
    .ios-checkbox-group:hover { border-color: var(--ios-blue); }
    .ios-checkbox { width: 22px; height: 22px; border-radius: 6px; cursor: pointer; accent-color: var(--ios-blue); flex-shrink: 0; }
    .ios-checkbox-content { flex: 1; }
    .ios-checkbox-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px 0; }
    .ios-checkbox-desc { font-size: 13px; color: var(--text-secondary); margin: 0; }

    /* Collapsible */
    .ios-collapsible { display: none; margin-top: 16px; }
    .ios-collapsible.show { display: block; }

    /* iOS Form Actions */
    .ios-form-actions { display: flex; flex-direction: column; gap: 12px; padding: 20px; background: var(--bg-subtle); border-top: 1px solid var(--border-color); }
    .ios-btn { width: 100%; padding: 14px 20px; border-radius: 12px; font-size: 16px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.2s ease; text-decoration: none; border: none; }
    .ios-btn.success { background: var(--ios-green); color: white; }
    .ios-btn.success:hover { background: #28b84d; }
    .ios-btn.secondary { background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color); }
    .ios-btn.secondary:hover { background: var(--border-color); }
    .ios-btn:active { transform: scale(0.98); }

    /* Image Upload Preview */
    .ios-image-preview { margin-top: 12px; max-width: 300px; }
    .ios-image-preview img { width: 100%; border-radius: 12px; border: 1px solid var(--border-color); }

    /* iOS Grid Layout */
    .ios-grid { display: grid; grid-template-columns: 1fr 320px; gap: 20px; }
    .ios-sidebar-column { display: flex; flex-direction: column; gap: 20px; }

    /* iOS Preview List */
    .ios-preview-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
    .ios-preview-item:last-child { border-bottom: none; }
    .ios-preview-label { font-size: 14px; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; }
    .ios-preview-label i { width: 16px; text-align: center; }
    .ios-preview-value { font-size: 14px; font-weight: 500; color: var(--text-primary); text-align: right; }
    .ios-preview-badge { background: rgba(10, 132, 255, 0.1); color: var(--ios-blue); padding: 4px 10px; border-radius: 8px; font-size: 12px; font-weight: 600; text-transform: capitalize; }

    /* iOS Tip Items */
    .ios-tip-item { display: flex; align-items: flex-start; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--border-color); }
    .ios-tip-item:last-child { border-bottom: none; }
    .ios-tip-icon { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 12px; flex-shrink: 0; }
    .ios-tip-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-tip-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-tip-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-tip-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }
    .ios-tip-text { font-size: 13px; color: var(--text-secondary); line-height: 1.5; }
    .ios-tip-text strong { color: var(--text-primary); display: block; margin-bottom: 2px; font-size: 14px; }

    /* iOS Bottom Sheet Menu */
    .ios-menu-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
    .ios-menu-backdrop.active { opacity: 1; visibility: visible; }
    .ios-menu-modal { position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary); border-radius: 16px 16px 0 0; z-index: 9999; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1); max-height: 85vh; overflow: hidden; display: flex; flex-direction: column; padding-bottom: env(safe-area-inset-bottom, 20px); }
    .ios-menu-modal.active { transform: translateY(0); }
    .ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
    .ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); }
    .ios-menu-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
    .ios-menu-close { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; }
    .ios-menu-close:hover { background: var(--border-color); }
    .ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
    .ios-menu-section { margin-bottom: 20px; }
    .ios-menu-section:last-child { margin-bottom: 0; }
    .ios-menu-section-title { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-left: 4px; }
    .ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
    .ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background 0.15s ease; cursor: pointer; }
    .ios-menu-item:last-child { border-bottom: none; }
    .ios-menu-item:active { background: var(--bg-subtle); }
    .ios-menu-item-left { display: flex; align-items: center; gap: 12px; }
    .ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .ios-menu-item-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-menu-item-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-menu-item-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-menu-item-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-menu-item-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }
    .ios-menu-item-label { font-size: 15px; font-weight: 500; }
    .ios-menu-item-chevron { color: var(--text-secondary); font-size: 12px; }

    /* Responsive */
    @media (max-width: 992px) {
        .ios-grid { grid-template-columns: 1fr; }
        .ios-options-btn { display: flex; }
    }
    @media (max-width: 768px) {
        .content-header { display: none !important; }
        .ios-sidebar-column { display: none; }
        .ios-section-card { border-radius: 12px; }
        .ios-section-header { padding: 16px; }
        .ios-section-icon { width: 40px; height: 40px; font-size: 16px; }
        .ios-section-body { padding: 16px; }
        .ios-form-actions { padding: 16px; }
        .ios-form-row { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .ios-section-header { padding: 14px; gap: 12px; }
        .ios-section-icon { width: 36px; height: 36px; font-size: 14px; border-radius: 10px; }
        .ios-section-title h5 { font-size: 15px; }
        .ios-section-title p { font-size: 12px; }
        .ios-section-body { padding: 14px; }
        .ios-form-control { padding: 12px 14px; font-size: 15px; }
        .ios-btn { padding: 12px 20px; font-size: 15px; }
    }
    @media (max-width: 390px) {
        .ios-section-header { padding: 12px; gap: 10px; }
        .ios-section-icon { width: 32px; height: 32px; font-size: 13px; }
        .ios-section-title h5 { font-size: 14px; }
        .ios-section-body { padding: 12px; }
    }
</style>

<!-- iOS Create Event -->
<div class="main-content">
    <?php include_once '../../includes/sidebar.php'; ?>
    <div class="content">
        <!-- Content Header (hidden on mobile) -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="content-title">
                        <i class="fas fa-plus-circle me-2"></i>
                        Create New Event
                    </h1>
                    <nav class="content-breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="<?php echo BASE_URL; ?>user/events/" class="breadcrumb-link">Events</a>
                            </li>
                            <li class="breadcrumb-item active">Create Event</li>
                        </ol>
                    </nav>
                </div>
                <div class="content-actions">
                    <a href="<?php echo BASE_URL; ?>user/events/" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Events</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="ios-flash-message error" id="flashMsg">
                <div class="ios-flash-icon"><i class="fas fa-times" style="font-size: 12px;"></i></div>
                <div class="ios-flash-content">
                    <p class="ios-flash-title">Error</p>
                    <p class="ios-flash-text"><?php echo $error; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Approval Notice -->
        <div class="ios-flash-message info">
            <div class="ios-flash-icon"><i class="fas fa-info" style="font-size: 12px;"></i></div>
            <div class="ios-flash-content">
                <p class="ios-flash-title">Admin Approval Required</p>
                <p class="ios-flash-text">Events created by community members require approval from a clan administrator before they become visible to others. You'll be notified once your event is reviewed.</p>
            </div>
        </div>

        <!-- iOS Grid Layout -->
        <form method="POST" enctype="multipart/form-data" id="createEventForm">
            <div class="ios-grid">
                <!-- Main Form Column -->
                <div>
                    <!-- Basic Information -->
                    <div class="ios-section-card">
                        <div class="ios-section-header">
                            <div class="ios-section-icon blue">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="ios-section-title">
                                <h5>Basic Information</h5>
                                <p>Provide the essential details about your event</p>
                            </div>
                            <button type="button" class="ios-options-btn" onclick="openMenu()" aria-label="Open menu">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                        <div class="ios-section-body">
                            <div class="ios-form-group">
                                <label class="ios-form-label">Event Title <span class="required">*</span></label>
                                <input type="text"
                                       class="ios-form-control"
                                       id="title"
                                       name="title"
                                       placeholder="e.g., Community BBQ & Pool Party"
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                       required>
                            </div>

                            <div class="ios-form-group">
                                <label class="ios-form-label">Description</label>
                                <textarea class="ios-form-control"
                                          id="description"
                                          name="description"
                                          rows="3"
                                          placeholder="Describe what this event is about..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                <div class="ios-form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Provide details about the event, agenda, what to bring, etc.
                                </div>
                            </div>

                            <div class="ios-form-row" style="margin-bottom: 20px;">
                                <div class="ios-form-group" style="margin-bottom: 0;">
                                    <label class="ios-form-label">Event Type <span class="required">*</span></label>
                                    <select id="event_type"
                                            name="event_type"
                                            class="ios-form-control ios-form-select"
                                            required>
                                        <option value="meeting" <?php echo ($_POST['event_type'] ?? '') === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                                        <option value="social" <?php echo ($_POST['event_type'] ?? '') === 'social' ? 'selected' : ''; ?>>Social</option>
                                        <option value="maintenance" <?php echo ($_POST['event_type'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                        <option value="other" <?php echo ($_POST['event_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (Custom)</option>
                                    </select>
                                </div>
                                <div class="ios-form-group ios-collapsible" id="customEventTypeGroup" style="margin-bottom: 0;">
                                    <label class="ios-form-label">Custom Event Type <span class="required">*</span></label>
                                    <input type="text"
                                           class="ios-form-control"
                                           id="custom_event_type"
                                           name="custom_event_type"
                                           placeholder="e.g., Fundraiser, Workshop"
                                           value="<?php echo htmlspecialchars($_POST['custom_event_type'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="ios-form-group">
                                <label class="ios-form-label">Location</label>
                                <input type="text"
                                       class="ios-form-control"
                                       id="location"
                                       name="location"
                                       placeholder="e.g., Community Center, Pool Area"
                                       value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                                <div class="ios-form-hint">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Where will the event take place?
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date & Time -->
                    <div class="ios-section-card">
                        <div class="ios-section-header">
                            <div class="ios-section-icon orange">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="ios-section-title">
                                <h5>Date & Time</h5>
                                <p>Set when the event will start and end</p>
                            </div>
                        </div>
                        <div class="ios-section-body">
                            <div class="ios-form-row" style="margin-bottom: 20px;">
                                <div class="ios-form-group" style="margin-bottom: 0;">
                                    <label class="ios-form-label">Start Date & Time <span class="required">*</span></label>
                                    <input type="datetime-local"
                                           class="ios-form-control"
                                           id="start_datetime"
                                           name="start_datetime"
                                           value="<?php echo $_POST['start_datetime'] ?? ''; ?>"
                                           required>
                                </div>
                                <div class="ios-form-group" style="margin-bottom: 0;">
                                    <label class="ios-form-label">End Date & Time <span class="required">*</span></label>
                                    <input type="datetime-local"
                                           class="ios-form-control"
                                           id="end_datetime"
                                           name="end_datetime"
                                           value="<?php echo $_POST['end_datetime'] ?? ''; ?>"
                                           required>
                                </div>
                            </div>

                            <div class="ios-form-group">
                                <label class="ios-form-label">RSVP Deadline (Optional)</label>
                                <input type="datetime-local"
                                       class="ios-form-control"
                                       id="registration_deadline"
                                       name="registration_deadline"
                                       value="<?php echo $_POST['registration_deadline'] ?? ''; ?>">
                                <div class="ios-form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Set a deadline for RSVPs. Leave blank for no deadline.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Capacity & Settings -->
                    <div class="ios-section-card">
                        <div class="ios-section-header">
                            <div class="ios-section-icon green">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div class="ios-section-title">
                                <h5>Capacity & Settings</h5>
                                <p>Configure event capacity and RSVP options</p>
                            </div>
                        </div>
                        <div class="ios-section-body">
                            <div class="ios-form-group">
                                <label class="ios-form-label">Maximum Attendees (Optional)</label>
                                <input type="number"
                                       class="ios-form-control"
                                       id="max_attendees"
                                       name="max_attendees"
                                       min="1"
                                       placeholder="Leave blank for unlimited"
                                       value="<?php echo $_POST['max_attendees'] ?? ''; ?>">
                                <div class="ios-form-hint">
                                    <i class="fas fa-users"></i>
                                    Leave blank for unlimited capacity.
                                </div>
                            </div>

                            <label class="ios-checkbox-group">
                                <input type="checkbox"
                                       class="ios-checkbox"
                                       id="require_rsvp"
                                       name="require_rsvp"
                                       <?php echo (!isset($_POST['require_rsvp']) || $_POST['require_rsvp']) ? 'checked' : ''; ?>>
                                <div class="ios-checkbox-content">
                                    <p class="ios-checkbox-title">Require RSVP</p>
                                    <p class="ios-checkbox-desc">Attendees must confirm their attendance</p>
                                </div>
                            </label>

                            <label class="ios-checkbox-group">
                                <input type="checkbox"
                                       class="ios-checkbox"
                                       id="allow_guests"
                                       name="allow_guests"
                                       <?php echo isset($_POST['allow_guests']) ? 'checked' : ''; ?>>
                                <div class="ios-checkbox-content">
                                    <p class="ios-checkbox-title">Allow Guests</p>
                                    <p class="ios-checkbox-desc">Attendees can bring additional guests</p>
                                </div>
                            </label>

                            <label class="ios-checkbox-group">
                                <input type="checkbox"
                                       class="ios-checkbox"
                                       id="send_reminders"
                                       name="send_reminders"
                                       <?php echo (!isset($_POST['send_reminders']) || $_POST['send_reminders']) ? 'checked' : ''; ?>>
                                <div class="ios-checkbox-content">
                                    <p class="ios-checkbox-title">Send Automatic Reminders</p>
                                    <p class="ios-checkbox-desc">Notify attendees 24h, 12h, 2h, and 30min before event</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Event Image -->
                    <div class="ios-section-card">
                        <div class="ios-section-header">
                            <div class="ios-section-icon red">
                                <i class="fas fa-image"></i>
                            </div>
                            <div class="ios-section-title">
                                <h5>Event Image</h5>
                                <p>Upload a banner or poster for your event</p>
                            </div>
                        </div>
                        <div class="ios-section-body">
                            <div class="ios-form-group">
                                <label class="ios-form-label">Upload Image (Optional)</label>
                                <input type="file"
                                       class="ios-form-control"
                                       id="event_image"
                                       name="event_image"
                                       accept="image/jpeg,image/png,image/gif">
                                <div class="ios-form-hint">
                                    <i class="fas fa-info-circle"></i>
                                    Recommended: 1200x600px. Max 5MB. JPG, PNG, GIF
                                </div>
                                <div id="imagePreview" class="ios-image-preview"></div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="ios-form-actions">
                            <button type="submit" class="ios-btn success">
                                <i class="fas fa-paper-plane"></i>
                                Submit for Approval
                            </button>
                            <a href="<?php echo BASE_URL; ?>user/events/" class="ios-btn secondary">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Column -->
                <div class="ios-sidebar-column">
                    <!-- Event Preview -->
                    <div class="ios-section-card">
                        <div class="ios-section-header">
                            <div class="ios-section-icon blue">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="ios-section-title">
                                <h5>Event Preview</h5>
                                <p>Review your event details</p>
                            </div>
                        </div>
                        <div class="ios-section-body">
                            <div class="ios-preview-item">
                                <span class="ios-preview-label"><i class="fas fa-heading"></i> Title</span>
                                <span class="ios-preview-value" id="previewTitle">-</span>
                            </div>
                            <div class="ios-preview-item">
                                <span class="ios-preview-label"><i class="fas fa-tag"></i> Type</span>
                                <span class="ios-preview-value" id="previewType"><span class="ios-preview-badge">Meeting</span></span>
                            </div>
                            <div class="ios-preview-item">
                                <span class="ios-preview-label"><i class="fas fa-map-marker-alt"></i> Location</span>
                                <span class="ios-preview-value" id="previewLocation">-</span>
                            </div>
                            <div class="ios-preview-item">
                                <span class="ios-preview-label"><i class="fas fa-calendar"></i> Start</span>
                                <span class="ios-preview-value" id="previewStart">-</span>
                            </div>
                            <div class="ios-preview-item">
                                <span class="ios-preview-label"><i class="fas fa-calendar-check"></i> End</span>
                                <span class="ios-preview-value" id="previewEnd">-</span>
                            </div>
                            <div class="ios-preview-item">
                                <span class="ios-preview-label"><i class="fas fa-users"></i> Max</span>
                                <span class="ios-preview-value" id="previewCapacity">Unlimited</span>
                            </div>
                            <div class="ios-preview-item">
                                <span class="ios-preview-label"><i class="fas fa-hourglass-half"></i> Status</span>
                                <span class="ios-preview-value"><span style="background: rgba(255,159,10,0.15); color: var(--ios-orange); padding: 3px 8px; border-radius: 6px; font-size: 12px; font-weight: 600;">Pending Approval</span></span>
                            </div>
                        </div>
                    </div>

                    <!-- Tips -->
                    <div class="ios-section-card">
                        <div class="ios-section-header">
                            <div class="ios-section-icon teal">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <div class="ios-section-title">
                                <h5>Tips</h5>
                                <p>Make the most of your event</p>
                            </div>
                        </div>
                        <div class="ios-section-body">
                            <div class="ios-tip-item">
                                <div class="ios-tip-icon green"><i class="fas fa-check"></i></div>
                                <div class="ios-tip-text">
                                    <strong>Clear Title</strong>
                                    Use a descriptive title that tells attendees what to expect
                                </div>
                            </div>
                            <div class="ios-tip-item">
                                <div class="ios-tip-icon blue"><i class="fas fa-clock"></i></div>
                                <div class="ios-tip-text">
                                    <strong>RSVP Deadline</strong>
                                    Set a deadline to get accurate attendance numbers beforehand
                                </div>
                            </div>
                            <div class="ios-tip-item">
                                <div class="ios-tip-icon orange"><i class="fas fa-bell"></i></div>
                                <div class="ios-tip-text">
                                    <strong>Reminders</strong>
                                    Enable automatic reminders to reduce no-shows
                                </div>
                            </div>
                            <div class="ios-tip-item">
                                <div class="ios-tip-icon teal"><i class="fas fa-shield-alt"></i></div>
                                <div class="ios-tip-text">
                                    <strong>Approval Process</strong>
                                    Your event will be reviewed by a clan admin before going live
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- iOS Bottom Sheet Menu -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <span class="ios-menu-title">Create Event</span>
        <button class="ios-menu-close" onclick="closeMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>user/events/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-calendar"></i></div>
                        <span class="ios-menu-item-label">Events Dashboard</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/browse-events.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-list"></i></div>
                        <span class="ios-menu-item-label">Browse Events</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/my-events.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-star"></i></div>
                        <span class="ios-menu-item-label">My Events</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-tachometer-alt"></i></div>
                        <span class="ios-menu-item-label">Dashboard</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Event Types</p>
            <div class="ios-menu-card">
                <div class="ios-menu-item" onclick="setEventType('meeting')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-users"></i></div>
                        <span class="ios-menu-item-label">Meeting</span>
                    </div>
                </div>
                <div class="ios-menu-item" onclick="setEventType('social')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-glass-cheers"></i></div>
                        <span class="ios-menu-item-label">Social</span>
                    </div>
                </div>
                <div class="ios-menu-item" onclick="setEventType('maintenance')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-tools"></i></div>
                        <span class="ios-menu-item-label">Maintenance</span>
                    </div>
                </div>
                <div class="ios-menu-item" onclick="setEventType('other')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon teal"><i class="fas fa-calendar-day"></i></div>
                        <span class="ios-menu-item-label">Other (Custom)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Event Type - Show/Hide Custom Event Type
    const eventTypeSelect = document.getElementById('event_type');
    const customEventTypeGroup = document.getElementById('customEventTypeGroup');
    const customEventTypeInput = document.getElementById('custom_event_type');

    function toggleCustomEventType() {
        if (eventTypeSelect.value === 'other') {
            customEventTypeGroup.classList.add('show');
            customEventTypeInput.required = true;
        } else {
            customEventTypeGroup.classList.remove('show');
            customEventTypeInput.required = false;
        }
        updatePreview();
    }
    eventTypeSelect.addEventListener('change', toggleCustomEventType);
    toggleCustomEventType();

    // Live Preview
    function updatePreview() {
        const title = document.getElementById('title').value;
        document.getElementById('previewTitle').textContent = title || '-';

        const type = eventTypeSelect.value;
        const customType = customEventTypeInput.value;
        const displayType = type === 'other' && customType ? customType : type.charAt(0).toUpperCase() + type.slice(1);
        document.getElementById('previewType').innerHTML = '<span class="ios-preview-badge">' + displayType + '</span>';

        const location = document.getElementById('location').value;
        document.getElementById('previewLocation').textContent = location || '-';

        const startVal = document.getElementById('start_datetime').value;
        if (startVal) {
            const d = new Date(startVal);
            document.getElementById('previewStart').textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        } else {
            document.getElementById('previewStart').textContent = '-';
        }

        const endVal = document.getElementById('end_datetime').value;
        if (endVal) {
            const d = new Date(endVal);
            document.getElementById('previewEnd').textContent = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) + ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        } else {
            document.getElementById('previewEnd').textContent = '-';
        }

        const capacity = document.getElementById('max_attendees').value;
        document.getElementById('previewCapacity').textContent = capacity ? parseInt(capacity).toLocaleString() + ' max' : 'Unlimited';
    }

    ['title', 'location', 'max_attendees', 'start_datetime', 'end_datetime', 'custom_event_type'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', updatePreview);
    });
    document.getElementById('start_datetime').addEventListener('change', function() {
        document.getElementById('end_datetime').min = this.value;
        updatePreview();
    });
    document.getElementById('end_datetime').addEventListener('change', updatePreview);
    updatePreview();

    // Image Preview
    document.getElementById('event_image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('imagePreview').innerHTML = '';
        }
    });

    // Set minimum date to now
    const now = new Date().toISOString().slice(0, 16);
    document.getElementById('start_datetime').min = now;
    document.getElementById('end_datetime').min = now;

    // Auto-dismiss flash message
    const flashMsg = document.getElementById('flashMsg');
    if (flashMsg) {
        setTimeout(() => { flashMsg.style.opacity = '0'; flashMsg.style.transition = 'opacity 0.5s'; setTimeout(() => flashMsg.remove(), 500); }, 5000);
    }
});

// Bottom Sheet
function openMenu() {
    document.getElementById('iosMenuBackdrop').classList.add('active');
    document.getElementById('iosMenuModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeMenu() {
    document.getElementById('iosMenuBackdrop').classList.remove('active');
    document.getElementById('iosMenuModal').classList.remove('active');
    document.body.style.overflow = '';
}
function setEventType(type) {
    document.getElementById('event_type').value = type;
    document.getElementById('event_type').dispatchEvent(new Event('change'));
    closeMenu();
}

// Swipe to close
(function() {
    let startY = 0;
    const modal = document.getElementById('iosMenuModal');
    modal.addEventListener('touchstart', e => { startY = e.touches[0].clientY; }, { passive: true });
    modal.addEventListener('touchend', e => { if (e.changedTouches[0].clientY - startY > 80) closeMenu(); }, { passive: true });
})();
</script>

<?php include_once '../../includes/footer.php'; ?>
