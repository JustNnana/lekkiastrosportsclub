<?php
/**
 * Gate Wey Access Management System
 * User Event View - iOS App Style Design
 * File: user/events/view-event.php
 */

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../classes/User.php';
require_once '../../classes/Event.php';
require_once '../../classes/EventRsvp.php';
require_once '../../classes/Clan.php';

// Set page title
$pageTitle = 'Event Details';

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

// Get and validate event ID - support both 'id' and 'event_id' parameters
$encryptedEventId = $_GET['id'] ?? $_GET['event_id'] ?? null;

if (!$encryptedEventId || empty($encryptedEventId)) {
    $error = 'Event ID required';
    header('Location: ' . BASE_URL . 'user/events/?error=' . urlencode($error));
    exit;
}

$eventId = decryptId($encryptedEventId);

if (!$eventId || !is_numeric($eventId)) {
    $error = 'Invalid event ID';
    header('Location: ' . BASE_URL . 'user/events/?error=' . urlencode($error));
    exit;
}

// Get database instance
$db = Database::getInstance();

// Load event
$event = new Event();
if (!$event->loadById($eventId)) {
    header('Location: ' . BASE_URL . 'user/events/?error=' . urlencode('Event not found'));
    exit;
}

// Verify event belongs to user's clan
if ($event->getClanId() != $currentUser->getClanId()) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

$eventRsvp = new EventRsvp();

// Get user's RSVP if exists
$userRsvp = $eventRsvp->getUserRsvp($eventId, $_SESSION['user_id']);

// Check if current user is the event creator
$isEventCreator = ($event->getCreatedBy() == $_SESSION['user_id']);

// Get attendee statistics
$attendeeStats = $db->fetchOne(
    "SELECT
        COUNT(CASE WHEN status = 'attending' THEN 1 END) as attending_count,
        COUNT(CASE WHEN status = 'maybe' THEN 1 END) as maybe_count,
        COUNT(CASE WHEN status = 'not_attending' THEN 1 END) as not_attending_count
     FROM event_rsvps
     WHERE event_id = ?",
    [$eventId]
);

$attendeeStats = $attendeeStats ?: [
    'attending_count' => 0,
    'maybe_count' => 0,
    'not_attending_count' => 0
];

// Get attendees list (for event creator only)
$attendingList = [];
$maybeList = [];
$notAttendingList = [];
if ($isEventCreator) {
    $attendingList = $eventRsvp->getEventAttendees($eventId, 'attending');
    $maybeList = $eventRsvp->getEventAttendees($eventId, 'maybe');
    $notAttendingList = $eventRsvp->getEventAttendees($eventId, 'not_attending');
}

// Get user's own access code if they are attending and codes are enabled
$userAccessCode = null;
if ($userRsvp && $userRsvp['status'] === 'attending' && $event->getEnableAccessCodes()) {
    $userAccessCode = $eventRsvp->getUserAccessCode($userRsvp['id']);
}

// Get user's guest access codes if they exist
$guestAccessCodes = [];
if ($userRsvp && $userRsvp['status'] === 'attending' && $event->getEnableAccessCodes()) {
    $guestAccessCodes = $db->fetchAll(
        "SELECT ac.*, egac.guest_name, egac.guest_phone
         FROM event_guest_access_codes egac
         JOIN access_codes ac ON egac.access_code_id = ac.id
         WHERE egac.event_id = ? AND egac.rsvp_id = ?
         ORDER BY egac.created_at DESC",
        [$eventId, $userRsvp['id']]
    );
}

// Format event type
$eventTypeClass = $event->getEventType();
$displayEventType = $event->getEventType() === 'other' && $event->getCustomEventType()
    ? $event->getCustomEventType()
    : ucfirst($event->getEventType());

// Include header
include_once '../../includes/header.php';
?>

<!-- iOS App Style Event View -->
<style>
    :root {
        /* iOS Color Variables */
        --ios-red: #FF453A;
        --ios-orange: #FF9F0A;
        --ios-yellow: #FFD60A;
        --ios-green: #30D158;
        --ios-teal: #64D2FF;
        --ios-blue: #0A84FF;
        --ios-purple: #BF5AF2;
        --ios-gray: #8E8E93;
    }

    /* iOS 3-dot Options Button - Now in header area */
    .ios-options-btn {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-primary);
        font-size: 16px;
        transition: all 0.2s;
        flex-shrink: 0;
    }

    .ios-options-btn:hover {
        background: var(--bg-hover);
    }

    /* iOS Bottom Sheet Modal */
    .ios-menu-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10000;
    }

    .ios-menu-backdrop {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    .ios-menu-sheet {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--bg-primary);
        border-radius: 20px 20px 0 0;
        max-height: 85vh;
        overflow-y: auto;
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        padding-bottom: env(safe-area-inset-bottom, 20px);
    }

    .ios-menu-modal.active .ios-menu-sheet {
        transform: translateY(0);
    }

    .ios-menu-handle {
        width: 36px;
        height: 5px;
        background: var(--ios-gray);
        border-radius: 3px;
        margin: 10px auto 16px;
        opacity: 0.5;
    }

    .ios-menu-header {
        padding: 0 20px 16px;
        border-bottom: 1px solid var(--border-color);
        text-align: center;
    }

    .ios-menu-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .ios-menu-section {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-menu-section:last-child {
        border-bottom: none;
    }

    .ios-menu-section-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--ios-gray);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
    }

    .ios-menu-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 0;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: opacity 0.2s;
        text-decoration: none;
    }

    .ios-menu-item:last-child {
        border-bottom: none;
    }

    .ios-menu-item:active {
        opacity: 0.6;
    }

    .ios-menu-item-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
    }

    .ios-menu-item-content {
        flex: 1;
    }

    .ios-menu-item-label {
        font-size: 16px;
        font-weight: 500;
        color: var(--text-primary);
    }

    .ios-menu-item-desc {
        font-size: 13px;
        color: var(--ios-gray);
        margin-top: 2px;
    }

    .ios-menu-item-arrow {
        color: var(--ios-gray);
        font-size: 14px;
    }

    /* iOS Stats Row */
    .ios-stats-row {
        display: flex;
        gap: 12px;
        margin-top: 8px;
    }

    .ios-stat-card {
        flex: 1;
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 12px;
        text-align: center;
    }

    .ios-stat-value {
        font-size: 24px;
        font-weight: 700;
    }

    .ios-stat-label {
        font-size: 11px;
        color: var(--ios-gray);
        margin-top: 2px;
        text-transform: uppercase;
    }

    /* Stats Overview Grid - Hidden on Mobile */
    .stats-overview-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--spacing-4);
        margin-bottom: var(--spacing-6);
    }

    .stat-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: var(--spacing-4);
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .stat-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .stat-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .stat-icon.gray { background: rgba(142, 142, 147, 0.15); color: var(--ios-gray); }

    .stat-content { flex: 1; }
    .stat-label { font-size: 12px; color: var(--ios-gray); text-transform: uppercase; }
    .stat-value { font-size: 24px; font-weight: 700; color: var(--text-primary); }

    /* Form Section Card */
    .form-section-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        margin-bottom: var(--spacing-4);
        overflow: hidden;
    }

    .form-section-header {
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
        padding: var(--spacing-4);
        border-bottom: 1px solid var(--border-color);
    }

    .form-section-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: white;
    }

    .form-section-icon.primary { background: var(--ios-blue); }
    .form-section-icon.success { background: var(--ios-green); }
    .form-section-icon.warning { background: var(--ios-orange); }
    .form-section-icon.purple { background: var(--ios-purple); }

    .form-section-title { flex: 1; }
    .form-section-title h5 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
    }
    .form-section-title p {
        margin: 2px 0 0;
        font-size: 13px;
        color: var(--ios-gray);
    }

    .form-section-body {
        padding: var(--spacing-4);
    }

    /* Event Image Banner */
    .event-image-banner {
        width: 100%;
        max-height: 320px;
        overflow: hidden;
        border-bottom: 1px solid var(--border-color);
    }
    .event-image-banner img {
        width: 100%;
        height: 100%;
        max-height: 320px;
        object-fit: cover;
        display: block;
    }

    /* iOS Event Detail Items */
    .ios-detail-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        padding: 14px 0;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-detail-item:last-child {
        border-bottom: none;
    }

    .ios-detail-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
        flex-shrink: 0;
    }

    .ios-detail-content {
        flex: 1;
    }

    .ios-detail-label {
        font-size: 13px;
        color: var(--ios-gray);
        margin-bottom: 2px;
    }

    .ios-detail-value {
        font-size: 16px;
        font-weight: 500;
        color: var(--text-primary);
    }

    /* RSVP Options */
    .rsvp-options {
        display: flex;
        gap: var(--spacing-3);
        margin-bottom: var(--spacing-4);
    }

    .rsvp-option {
        flex: 1;
        position: relative;
    }

    .rsvp-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .rsvp-option-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: var(--spacing-4);
        background: var(--bg-secondary);
        border: 2px solid var(--border-color);
        border-radius: 16px;
        cursor: pointer;
        transition: all 0.2s ease;
        min-height: 90px;
        justify-content: center;
    }

    .rsvp-option input:checked + .rsvp-option-label {
        border-color: var(--ios-blue);
        background: rgba(10, 132, 255, 0.1);
    }

    .rsvp-option-label i {
        font-size: 24px;
    }

    .rsvp-option-label span {
        font-size: 14px;
        font-weight: 500;
    }

    /* Current RSVP Status Display */
    .current-rsvp-status {
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
        padding: var(--spacing-4);
        background: var(--bg-secondary);
        border-radius: 12px;
        margin-bottom: var(--spacing-3);
    }

    .current-rsvp-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .current-rsvp-icon.attending { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .current-rsvp-icon.maybe { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .current-rsvp-icon.not_attending { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }

    .current-rsvp-text {
        flex: 1;
    }

    .current-rsvp-label {
        font-size: 13px;
        color: var(--ios-gray);
    }

    .current-rsvp-value {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
    }

    /* Guest Section */
    .guest-item {
        display: flex;
        gap: var(--spacing-3);
        margin-bottom: var(--spacing-3);
        align-items: flex-end;
    }

    .guest-item .form-group {
        flex: 1;
    }

    .btn-remove-guest {
        padding: 10px 14px;
        background: var(--ios-red);
        color: white;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        min-height: 44px;
    }

    .btn-add-guest {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: transparent;
        color: var(--ios-blue);
        border: 1px solid var(--ios-blue);
        border-radius: 12px;
        cursor: pointer;
        font-size: 14px;
    }

    /* Access Code Cards */
    .access-code-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: var(--spacing-3);
        margin-bottom: var(--spacing-3);
    }

    .access-code-card:last-child {
        margin-bottom: 0;
    }

    .access-code-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--spacing-2);
    }

    .access-code-guest {
        font-weight: 600;
        color: var(--text-primary);
    }

    .access-code-phone {
        font-size: 13px;
        color: var(--ios-gray);
    }

    .access-code-value {
        font-family: 'SF Mono', 'Menlo', monospace;
        font-size: 20px;
        font-weight: 700;
        color: var(--ios-blue);
        text-align: center;
        padding: var(--spacing-3);
        background: var(--bg-primary);
        border-radius: 10px;
        letter-spacing: 3px;
        margin-bottom: var(--spacing-2);
    }

    .access-code-validity {
        font-size: 12px;
        color: var(--ios-gray);
        text-align: center;
    }

    /* Event Status Badge */
    .event-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
    }

    .event-status-badge.upcoming { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .event-status-badge.ongoing { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .event-status-badge.completed { background: rgba(142, 142, 147, 0.15); color: var(--ios-gray); }
    .event-status-badge.cancelled { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }

    /* Event Type Badge */
    .event-type-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
    }

    .event-type-badge.meeting { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .event-type-badge.social { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .event-type-badge.maintenance { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .event-type-badge.other { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }

    /* Description Text */
    .description-text {
        color: var(--text-secondary);
        line-height: 1.6;
        white-space: pre-wrap;
    }

    /* Tabbed Interface */
    .tabs {
        display: flex;
        gap: var(--spacing-2);
        border-bottom: 2px solid var(--border-color);
        margin-bottom: var(--spacing-4);
        overflow-x: auto;
        scrollbar-width: none;
    }

    .tabs::-webkit-scrollbar { display: none; }

    .tab {
        padding: var(--spacing-3) var(--spacing-4);
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        margin-bottom: -2px;
        cursor: pointer;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
        color: var(--text-secondary);
        white-space: nowrap;
        transition: all 0.2s ease;
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tab.active {
        color: var(--ios-blue);
        border-bottom-color: var(--ios-blue);
    }

    .tab-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
    }

    .tab.active .tab-badge { background: var(--ios-blue); color: white; }
    .tab:not(.active) .tab-badge { background: var(--bg-secondary); color: var(--text-secondary); }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* Attendee List */
    .attendee-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-2);
    }

    .attendee-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--spacing-3);
        background: var(--bg-secondary);
        border-radius: 12px;
    }

    .attendee-info { flex: 1; }

    .attendee-name {
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-semibold);
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }

    .attendee-details {
        font-size: var(--font-size-xs);
        color: var(--text-secondary);
    }

    .attendee-badge {
        font-size: var(--font-size-xs);
        padding: 4px 10px;
        border-radius: var(--border-radius-full);
        font-weight: 500;
    }

    .attendee-badge.checked-in { background-color: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .attendee-badge.not-checked-in { background-color: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: var(--spacing-8) var(--spacing-4);
    }

    .empty-state-icon {
        font-size: 48px;
        color: var(--text-secondary);
        margin-bottom: var(--spacing-3);
    }

    .empty-state-text {
        font-size: var(--font-size-base);
        color: var(--text-secondary);
    }

    /* Mobile Optimizations */
    @media (max-width: 768px) {
        .content-header {
            display: none !important;
        }

        .content {
            padding-top: 16px !important;
        }

        /* Hide stat cards on mobile - they're in 3-dot menu */
        .stats-overview-grid {
            display: none !important;
        }

        .rsvp-options {
            flex-direction: column;
        }

        .guest-item {
            flex-direction: column;
        }

        .guest-item .form-group {
            width: 100%;
        }

        .btn-remove-guest {
            width: 100%;
        }
    }
</style>

<!-- iOS Bottom Sheet Menu Modal -->
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-backdrop" onclick="toggleIosMenu()"></div>
    <div class="ios-menu-sheet" id="iosMenuSheet">
        <div class="ios-menu-handle"></div>

        <div class="ios-menu-header">
            <h3 class="ios-menu-title"><?php echo htmlspecialchars($event->getTitle()); ?></h3>
        </div>

        <!-- RSVP Stats Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Attendance</div>
            <div class="ios-stats-row">
                <div class="ios-stat-card">
                    <div class="ios-stat-value" style="color: var(--ios-green);"><?php echo $attendeeStats['attending_count']; ?></div>
                    <div class="ios-stat-label">Going</div>
                </div>
                <div class="ios-stat-card">
                    <div class="ios-stat-value" style="color: var(--ios-orange);"><?php echo $attendeeStats['maybe_count']; ?></div>
                    <div class="ios-stat-label">Maybe</div>
                </div>
                <div class="ios-stat-card">
                    <div class="ios-stat-value" style="color: var(--ios-gray);"><?php echo $attendeeStats['not_attending_count']; ?></div>
                    <div class="ios-stat-label">No</div>
                </div>
            </div>
        </div>

        <!-- Your RSVP Section -->
        <?php if ($userRsvp && $event->getStatus() !== 'cancelled' && $event->getStatus() !== 'completed'): ?>
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Your Response</div>
            <div class="current-rsvp-status">
                <div class="current-rsvp-icon <?php echo $userRsvp['status']; ?>">
                    <i class="fas <?php
                        echo $userRsvp['status'] === 'attending' ? 'fa-check-circle' :
                            ($userRsvp['status'] === 'maybe' ? 'fa-question-circle' : 'fa-times-circle');
                    ?>"></i>
                </div>
                <div class="current-rsvp-text">
                    <div class="current-rsvp-label">Current RSVP</div>
                    <div class="current-rsvp-value"><?php
                        echo $userRsvp['status'] === 'attending' ? 'Going' :
                            ($userRsvp['status'] === 'maybe' ? 'Maybe' : "Can't Go");
                    ?></div>
                </div>
            </div>
            <div class="ios-menu-item" onclick="scrollToRsvp(); toggleIosMenu();">
                <div class="ios-menu-item-icon" style="background: var(--ios-blue);">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="ios-menu-item-content">
                    <div class="ios-menu-item-label">Change RSVP</div>
                    <div class="ios-menu-item-desc">Update your response</div>
                </div>
                <i class="fas fa-chevron-right ios-menu-item-arrow"></i>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Actions</div>

            <a href="<?php echo BASE_URL; ?>user/events/" class="ios-menu-item">
                <div class="ios-menu-item-icon" style="background: var(--ios-blue);">
                    <i class="fas fa-arrow-left"></i>
                </div>
                <div class="ios-menu-item-content">
                    <div class="ios-menu-item-label">Back to Events</div>
                    <div class="ios-menu-item-desc">Return to calendar</div>
                </div>
                <i class="fas fa-chevron-right ios-menu-item-arrow"></i>
            </a>

            <?php if ($isEventCreator): ?>
            <a href="<?php echo BASE_URL; ?>user/events/edit-event.php?id=<?php echo encryptId($eventId); ?>" class="ios-menu-item">
                <div class="ios-menu-item-icon" style="background: var(--ios-orange);">
                    <i class="fas fa-edit"></i>
                </div>
                <div class="ios-menu-item-content">
                    <div class="ios-menu-item-label">Edit Event</div>
                    <div class="ios-menu-item-desc">Modify event details</div>
                </div>
                <i class="fas fa-chevron-right ios-menu-item-arrow"></i>
            </a>
            <?php endif; ?>

            <?php if ($userAccessCode): ?>
            <div class="ios-menu-item" onclick="scrollToAccessCodes(); toggleIosMenu();">
                <div class="ios-menu-item-icon" style="background: var(--ios-purple);">
                    <i class="fas fa-qrcode"></i>
                </div>
                <div class="ios-menu-item-content">
                    <div class="ios-menu-item-label">Your Access Code</div>
                    <div class="ios-menu-item-desc"><?php echo htmlspecialchars($userAccessCode['code']); ?></div>
                </div>
                <i class="fas fa-chevron-right ios-menu-item-arrow"></i>
            </div>
            <?php endif; ?>

            <?php if (!empty($guestAccessCodes)): ?>
            <div class="ios-menu-item" onclick="scrollToGuestAccessCodes(); toggleIosMenu();">
                <div class="ios-menu-item-icon" style="background: var(--ios-orange);">
                    <i class="fas fa-key"></i>
                </div>
                <div class="ios-menu-item-content">
                    <div class="ios-menu-item-label">Guest Codes</div>
                    <div class="ios-menu-item-desc"><?php echo count($guestAccessCodes); ?> code<?php echo count($guestAccessCodes) > 1 ? 's' : ''; ?></div>
                </div>
                <i class="fas fa-chevron-right ios-menu-item-arrow"></i>
            </div>
            <?php endif; ?>
        </div>

        <!-- Event Info -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Event Info</div>

            <div class="ios-menu-item" style="cursor: default;">
                <div class="ios-menu-item-icon" style="background: var(--ios-blue);">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="ios-menu-item-content">
                    <div class="ios-menu-item-label"><?php echo date('D, M j', strtotime($event->getStartDatetime())); ?></div>
                    <div class="ios-menu-item-desc"><?php echo date('g:i A', strtotime($event->getStartDatetime())); ?> - <?php echo date('g:i A', strtotime($event->getEndDatetime())); ?></div>
                </div>
            </div>

            <?php if ($event->getLocation()): ?>
            <div class="ios-menu-item" style="cursor: default;">
                <div class="ios-menu-item-icon" style="background: var(--ios-red);">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="ios-menu-item-content">
                    <div class="ios-menu-item-label"><?php echo htmlspecialchars($event->getLocation()); ?></div>
                    <div class="ios-menu-item-desc">Location</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="content">
        <!-- Content Header (Hidden on Mobile) -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="content-title">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Event Details
                    </h1>
                    <nav class="content-breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="<?php echo BASE_URL; ?>user/events/" class="breadcrumb-link">Events</a>
                            </li>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($event->getTitle()); ?></li>
                        </ol>
                    </nav>
                </div>
                <div class="content-actions">
                    <a href="<?php echo BASE_URL; ?>user/events/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Overview (Desktop Only) -->
        <div class="stats-overview-grid">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Attending</div>
                    <div class="stat-value"><?php echo $attendeeStats['attending_count']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-question-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Maybe</div>
                    <div class="stat-value"><?php echo $attendeeStats['maybe_count']; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gray">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Not Going</div>
                    <div class="stat-value"><?php echo $attendeeStats['not_attending_count']; ?></div>
                </div>
            </div>
        </div>

        <!-- Event Details Card -->
        <div class="form-section-card">
            <div class="form-section-header">
                <div class="form-section-icon primary">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="form-section-title">
                    <h5><?php echo htmlspecialchars($event->getTitle()); ?></h5>
                    <p>
                        <span class="event-type-badge <?php echo $eventTypeClass; ?>"><?php echo htmlspecialchars($displayEventType); ?></span>
                        <span class="event-status-badge <?php echo $event->getStatus(); ?>" style="margin-left: 8px;">
                            <?php echo ucfirst($event->getStatus()); ?>
                        </span>
                    </p>
                </div>
                <!-- 3-dot button in header -->
                <button class="ios-options-btn" onclick="toggleIosMenu()">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
            <?php if ($event->getImage()): ?>
            <div class="event-image-banner">
                <img src="<?php echo BASE_URL . htmlspecialchars($event->getImage()); ?>" alt="<?php echo htmlspecialchars($event->getTitle()); ?>">
            </div>
            <?php endif; ?>
            <div class="form-section-body">
                <div class="ios-detail-item">
                    <div class="ios-detail-icon" style="background: var(--ios-blue);">
                        <i class="fas fa-calendar"></i>
                    </div>
                    <div class="ios-detail-content">
                        <div class="ios-detail-label">Date</div>
                        <div class="ios-detail-value"><?php echo date('l, F j, Y', strtotime($event->getStartDatetime())); ?></div>
                    </div>
                </div>

                <div class="ios-detail-item">
                    <div class="ios-detail-icon" style="background: var(--ios-orange);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="ios-detail-content">
                        <div class="ios-detail-label">Time</div>
                        <div class="ios-detail-value"><?php echo date('g:i A', strtotime($event->getStartDatetime())); ?> - <?php echo date('g:i A', strtotime($event->getEndDatetime())); ?></div>
                    </div>
                </div>

                <?php if ($event->getLocation()): ?>
                <div class="ios-detail-item">
                    <div class="ios-detail-icon" style="background: var(--ios-red);">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <div class="ios-detail-content">
                        <div class="ios-detail-label">Location</div>
                        <div class="ios-detail-value"><?php echo htmlspecialchars($event->getLocation()); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($event->getMaxAttendees()): ?>
                <div class="ios-detail-item">
                    <div class="ios-detail-icon" style="background: var(--ios-purple);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="ios-detail-content">
                        <div class="ios-detail-label">Capacity</div>
                        <div class="ios-detail-value"><?php echo $attendeeStats['attending_count']; ?> / <?php echo $event->getMaxAttendees(); ?> spots filled</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($event->getDescription()): ?>
                <div class="ios-detail-item" style="border-bottom: none;">
                    <div class="ios-detail-icon" style="background: var(--ios-teal);">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="ios-detail-content">
                        <div class="ios-detail-label">Description</div>
                        <div class="description-text"><?php echo htmlspecialchars($event->getDescription()); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RSVP Card -->
        <?php if ($event->getStatus() !== 'cancelled' && $event->getStatus() !== 'completed' && $currentUser->getRole() !== 'guard'): ?>
            <?php if ($userRsvp): ?>
                <!-- User has already RSVP'd - Show current status -->
                <div class="form-section-card" id="rsvpSection">
                    <div class="form-section-header">
                        <div class="form-section-icon success">
                            <i class="fas fa-reply"></i>
                        </div>
                        <div class="form-section-title">
                            <h5>Your RSVP</h5>
                            <p>You've already responded</p>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <div class="current-rsvp-status">
                            <div class="current-rsvp-icon <?php echo $userRsvp['status']; ?>">
                                <i class="fas <?php
                                    echo $userRsvp['status'] === 'attending' ? 'fa-check-circle' :
                                        ($userRsvp['status'] === 'maybe' ? 'fa-question-circle' : 'fa-times-circle');
                                ?>"></i>
                            </div>
                            <div class="current-rsvp-text">
                                <div class="current-rsvp-label">Your Response</div>
                                <div class="current-rsvp-value"><?php
                                    echo $userRsvp['status'] === 'attending' ? "You're Going!" :
                                        ($userRsvp['status'] === 'maybe' ? 'Maybe Attending' : "Not Attending");
                                ?></div>
                            </div>
                        </div>

                        <!-- Hidden form for updating RSVP (accessible via 3-dot menu) -->
                        <div id="rsvpUpdateForm" style="display: none; margin-top: var(--spacing-4); padding-top: var(--spacing-4); border-top: 1px solid var(--border-color);">
                            <form id="rsvpForm">
                                <input type="hidden" name="event_id" value="<?php echo encryptId($eventId); ?>">

                                <div class="rsvp-options">
                                    <div class="rsvp-option">
                                        <input type="radio" name="status" id="status_attending" value="attending"
                                            <?php echo ($userRsvp['status'] === 'attending') ? 'checked' : ''; ?>>
                                        <label for="status_attending" class="rsvp-option-label">
                                            <i class="fas fa-check-circle" style="color: var(--ios-green);"></i>
                                            <span>Going</span>
                                        </label>
                                    </div>

                                    <div class="rsvp-option">
                                        <input type="radio" name="status" id="status_maybe" value="maybe"
                                            <?php echo ($userRsvp['status'] === 'maybe') ? 'checked' : ''; ?>>
                                        <label for="status_maybe" class="rsvp-option-label">
                                            <i class="fas fa-question-circle" style="color: var(--ios-orange);"></i>
                                            <span>Maybe</span>
                                        </label>
                                    </div>

                                    <div class="rsvp-option">
                                        <input type="radio" name="status" id="status_not_attending" value="not_attending"
                                            <?php echo ($userRsvp['status'] === 'not_attending') ? 'checked' : ''; ?>>
                                        <label for="status_not_attending" class="rsvp-option-label">
                                            <i class="fas fa-times-circle" style="color: var(--ios-red);"></i>
                                            <span>Can't Go</span>
                                        </label>
                                    </div>
                                </div>

                                <?php if ($event->getAllowGuests()): ?>
                                <div id="guestSection" style="display: <?php echo $userRsvp['status'] === 'attending' ? 'block' : 'none'; ?>; margin-top: var(--spacing-4); padding-top: var(--spacing-4); border-top: 1px solid var(--border-color);">
                                    <h6 style="margin-bottom: var(--spacing-3); display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-plus" style="color: var(--ios-blue);"></i>
                                        Guests
                                        <?php if ($event->getMaxGuestsPerUser()): ?>
                                            <span style="font-size: 12px; color: var(--ios-gray); font-weight: normal;">(Max <?php echo $event->getMaxGuestsPerUser(); ?>)</span>
                                        <?php endif; ?>
                                    </h6>

                                    <div id="guestList">
                                        <?php
                                        $existingGuests = json_decode($userRsvp['guest_names'] ?: '[]', true);
                                        foreach ($existingGuests as $index => $guest):
                                        ?>
                                            <div class="guest-item" data-guest-index="<?php echo $index; ?>">
                                                <div class="form-group">
                                                    <label class="form-label">Guest Name</label>
                                                    <input type="text" class="form-control" name="guests[<?php echo $index; ?>][name]"
                                                        value="<?php echo htmlspecialchars($guest['name'] ?? ''); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label class="form-label">Phone</label>
                                                    <input type="tel" class="form-control" name="guests[<?php echo $index; ?>][phone]"
                                                        value="<?php echo htmlspecialchars($guest['phone'] ?? ''); ?>" required>
                                                </div>
                                                <button type="button" class="btn-remove-guest" onclick="removeGuest(this)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <button type="button" class="btn-add-guest" onclick="addGuest()">
                                        <i class="fas fa-plus"></i>
                                        Add Guest
                                    </button>
                                </div>
                                <?php endif; ?>

                                <button type="submit" class="btn btn-primary w-100" style="margin-top: var(--spacing-4); min-height: 48px; border-radius: 12px;">
                                    <i class="fas fa-save me-2"></i>
                                    Update RSVP
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- User hasn't RSVP'd yet - Show full form -->
                <div class="form-section-card" id="rsvpSection">
                    <div class="form-section-header">
                        <div class="form-section-icon success">
                            <i class="fas fa-reply"></i>
                        </div>
                        <div class="form-section-title">
                            <h5>RSVP</h5>
                            <p>Let us know if you're coming</p>
                        </div>
                    </div>
                    <div class="form-section-body">
                        <form id="rsvpForm">
                            <input type="hidden" name="event_id" value="<?php echo encryptId($eventId); ?>">

                            <div class="rsvp-options">
                                <div class="rsvp-option">
                                    <input type="radio" name="status" id="status_attending" value="attending">
                                    <label for="status_attending" class="rsvp-option-label">
                                        <i class="fas fa-check-circle" style="color: var(--ios-green);"></i>
                                        <span>Going</span>
                                    </label>
                                </div>

                                <div class="rsvp-option">
                                    <input type="radio" name="status" id="status_maybe" value="maybe">
                                    <label for="status_maybe" class="rsvp-option-label">
                                        <i class="fas fa-question-circle" style="color: var(--ios-orange);"></i>
                                        <span>Maybe</span>
                                    </label>
                                </div>

                                <div class="rsvp-option">
                                    <input type="radio" name="status" id="status_not_attending" value="not_attending">
                                    <label for="status_not_attending" class="rsvp-option-label">
                                        <i class="fas fa-times-circle" style="color: var(--ios-red);"></i>
                                        <span>Can't Go</span>
                                    </label>
                                </div>
                            </div>

                            <?php if ($event->getAllowGuests()): ?>
                            <div id="guestSection" style="display: none; margin-top: var(--spacing-4); padding-top: var(--spacing-4); border-top: 1px solid var(--border-color);">
                                <h6 style="margin-bottom: var(--spacing-3); display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-user-plus" style="color: var(--ios-blue);"></i>
                                    Guests
                                    <?php if ($event->getMaxGuestsPerUser()): ?>
                                        <span style="font-size: 12px; color: var(--ios-gray); font-weight: normal;">(Max <?php echo $event->getMaxGuestsPerUser(); ?>)</span>
                                    <?php endif; ?>
                                </h6>

                                <div id="guestList"></div>

                                <button type="button" class="btn-add-guest" onclick="addGuest()">
                                    <i class="fas fa-plus"></i>
                                    Add Guest
                                </button>
                            </div>
                            <?php endif; ?>

                            <button type="submit" class="btn btn-primary w-100" style="margin-top: var(--spacing-4); min-height: 48px; border-radius: 12px;">
                                <i class="fas fa-save me-2"></i>
                                Submit RSVP
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
        <div class="form-section-card">
            <div class="form-section-body" style="text-align: center; padding: var(--spacing-8);">
                <i class="fas fa-info-circle" style="font-size: 48px; color: var(--ios-gray); margin-bottom: var(--spacing-3);"></i>
                <p style="color: var(--ios-gray); margin: 0;">This event is <?php echo $event->getStatus(); ?>. RSVPs are closed.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Your Access Code Card -->
        <?php if ($userAccessCode): ?>
        <div class="form-section-card" id="accessCodesSection">
            <div class="form-section-header">
                <div class="form-section-icon purple">
                    <i class="fas fa-qrcode"></i>
                </div>
                <div class="form-section-title">
                    <h5>Your Access Code</h5>
                    <p>Show this code at the entrance</p>
                </div>
            </div>
            <div class="form-section-body">
                <div class="access-code-card" style="background: linear-gradient(135deg, rgba(10, 132, 255, 0.1) 0%, rgba(191, 90, 242, 0.1) 100%); border-color: var(--ios-blue);">
                    <div class="access-code-header">
                        <span class="access-code-guest" style="color: var(--ios-blue);">Your Entry Code</span>
                        <span class="access-code-phone" style="font-size: 11px; background: var(--ios-green); color: white; padding: 2px 8px; border-radius: 10px;">Active</span>
                    </div>
                    <div class="access-code-value" style="font-size: 28px; color: var(--ios-purple);"><?php echo htmlspecialchars($userAccessCode['code']); ?></div>
                    <div class="access-code-validity">
                        Valid: <?php echo date('M j, g:i A', strtotime($userAccessCode['valid_from'])); ?> - <?php echo date('M j, g:i A', strtotime($userAccessCode['valid_until'])); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Guest Access Codes Card -->
        <?php if (!empty($guestAccessCodes)): ?>
        <div class="form-section-card" id="guestAccessCodesSection">
            <div class="form-section-header">
                <div class="form-section-icon warning">
                    <i class="fas fa-key"></i>
                </div>
                <div class="form-section-title">
                    <h5>Guest Access Codes</h5>
                    <p>Share these codes with your guests</p>
                </div>
            </div>
            <div class="form-section-body">
                <?php foreach ($guestAccessCodes as $code): ?>
                <div class="access-code-card">
                    <div class="access-code-header">
                        <span class="access-code-guest"><?php echo htmlspecialchars($code['guest_name']); ?></span>
                        <span class="access-code-phone"><?php echo htmlspecialchars($code['guest_phone']); ?></span>
                    </div>
                    <div class="access-code-value"><?php echo htmlspecialchars($code['code']); ?></div>
                    <div class="access-code-validity">
                        Valid: <?php echo date('M j, g:i A', strtotime($code['valid_from'])); ?> - <?php echo date('M j, g:i A', strtotime($code['valid_until'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Attendees Card (For Event Creator Only) -->
        <?php if ($isEventCreator): ?>
        <div class="form-section-card">
            <div class="form-section-header">
                <div class="form-section-icon success">
                    <i class="fas fa-users"></i>
                </div>
                <div class="form-section-title">
                    <h5>Registered Attendees</h5>
                    <p>People who have responded to your event</p>
                </div>
            </div>
            <div class="form-section-body">
                <!-- Tabs -->
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('attending', this)">
                        <span>Attending</span>
                        <span class="tab-badge"><?php echo count($attendingList); ?></span>
                    </button>
                    <button class="tab" onclick="switchTab('maybe', this)">
                        <span>Maybe</span>
                        <span class="tab-badge"><?php echo count($maybeList); ?></span>
                    </button>
                    <button class="tab" onclick="switchTab('not-attending', this)">
                        <span>Not Attending</span>
                        <span class="tab-badge"><?php echo count($notAttendingList); ?></span>
                    </button>
                </div>

                <!-- Attending Tab -->
                <div id="tab-attending" class="tab-content active">
                    <?php if (count($attendingList) > 0): ?>
                        <div class="attendee-list">
                            <?php foreach ($attendingList as $attendee): ?>
                                <div class="attendee-item">
                                    <div class="attendee-info">
                                        <p class="attendee-name"><?php echo htmlspecialchars($attendee['full_name']); ?></p>
                                        <p class="attendee-details">
                                            <?php if ($attendee['guest_count'] > 0): ?>
                                                <strong style="color: var(--ios-blue);">+<?php echo $attendee['guest_count']; ?> guest<?php echo $attendee['guest_count'] > 1 ? 's' : ''; ?></strong>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <span class="attendee-badge <?php echo $attendee['checked_in'] ? 'checked-in' : 'not-checked-in'; ?>">
                                        <?php echo $attendee['checked_in'] ? 'Checked In' : 'Pending'; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
                            <p class="empty-state-text">No attendees yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Maybe Tab -->
                <div id="tab-maybe" class="tab-content">
                    <?php if (count($maybeList) > 0): ?>
                        <div class="attendee-list">
                            <?php foreach ($maybeList as $attendee): ?>
                                <div class="attendee-item">
                                    <div class="attendee-info">
                                        <p class="attendee-name"><?php echo htmlspecialchars($attendee['full_name']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-question-circle"></i></div>
                            <p class="empty-state-text">No maybes yet</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Not Attending Tab -->
                <div id="tab-not-attending" class="tab-content">
                    <?php if (count($notAttendingList) > 0): ?>
                        <div class="attendee-list">
                            <?php foreach ($notAttendingList as $attendee): ?>
                                <div class="attendee-item">
                                    <div class="attendee-info">
                                        <p class="attendee-name"><?php echo htmlspecialchars($attendee['full_name']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-times-circle"></i></div>
                            <p class="empty-state-text">No declines yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
let guestIndex = <?php echo $userRsvp ? count(json_decode($userRsvp['guest_names'] ?: '[]', true)) : 0; ?>;
const maxGuests = <?php echo $event->getMaxGuestsPerUser() ?: 999; ?>;
const allowGuests = <?php echo $event->getAllowGuests() ? 'true' : 'false'; ?>;
const hasExistingRsvp = <?php echo $userRsvp ? 'true' : 'false'; ?>;

// Show/hide guest section based on RSVP status
document.querySelectorAll('input[name="status"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const guestSection = document.getElementById('guestSection');
        if (guestSection) {
            guestSection.style.display = (this.value === 'attending' && allowGuests) ? 'block' : 'none';
        }
    });
});

// Initialize guest section visibility
if (allowGuests) {
    const selectedStatus = document.querySelector('input[name="status"]:checked');
    const guestSection = document.getElementById('guestSection');
    if (selectedStatus && selectedStatus.value === 'attending' && guestSection) {
        guestSection.style.display = 'block';
    }
}

function addGuest() {
    const guestList = document.getElementById('guestList');
    const currentGuestCount = guestList.querySelectorAll('.guest-item').length;

    if (currentGuestCount >= maxGuests) {
        alert('Maximum number of guests reached');
        return;
    }

    const guestItem = document.createElement('div');
    guestItem.className = 'guest-item';
    guestItem.setAttribute('data-guest-index', guestIndex);
    guestItem.innerHTML = `
        <div class="form-group">
            <label class="form-label">Guest Name</label>
            <input type="text" class="form-control" name="guests[${guestIndex}][name]" required>
        </div>
        <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="tel" class="form-control" name="guests[${guestIndex}][phone]" required>
        </div>
        <button type="button" class="btn-remove-guest" onclick="removeGuest(this)">
            <i class="fas fa-trash"></i>
        </button>
    `;

    guestList.appendChild(guestItem);
    guestIndex++;
}

function removeGuest(button) {
    button.closest('.guest-item').remove();
}

// Tab switching
function switchTab(tabName, button) {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    button.classList.add('active');
    document.getElementById('tab-' + tabName).classList.add('active');
}

// Handle RSVP form submission
document.getElementById('rsvpForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const submitButton = this.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;

    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';

    try {
        const response = await fetch('<?php echo BASE_URL; ?>api/event-rsvp.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();

        if (result.success) {
            sessionStorage.setItem('flash_success', result.message);
            window.location.reload();
        } else {
            if (typeof showNotification === 'function') {
                showNotification(result.message || 'Failed to save RSVP', 'error');
            }
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        }
    } catch (error) {
        console.error('Error:', error);
        if (typeof showNotification === 'function') {
            showNotification('An error occurred while saving your RSVP', 'error');
        }
        submitButton.disabled = false;
        submitButton.innerHTML = originalText;
    }
});

// Flash notification on page load
window.addEventListener('DOMContentLoaded', function() {
    const flashSuccess = sessionStorage.getItem('flash_success');
    if (flashSuccess) {
        sessionStorage.removeItem('flash_success');
        if (typeof showNotification === 'function') {
            showNotification(flashSuccess, 'success');
        }
    }
});

// iOS Menu Functions
function toggleIosMenu() {
    const modal = document.getElementById('iosMenuModal');
    if (modal.classList.contains('active')) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    } else {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        requestAnimationFrame(() => { modal.classList.add('active'); });
    }
}

function scrollToRsvp() {
    const rsvpSection = document.getElementById('rsvpSection');
    const rsvpUpdateForm = document.getElementById('rsvpUpdateForm');

    if (rsvpSection) {
        rsvpSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        // If user has existing RSVP, show the update form
        if (rsvpUpdateForm && hasExistingRsvp) {
            rsvpUpdateForm.style.display = 'block';
        }
    }
}

function scrollToAccessCodes() {
    document.getElementById('accessCodesSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function scrollToGuestAccessCodes() {
    document.getElementById('guestAccessCodesSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Swipe-to-close gesture
(function() {
    const menuSheet = document.getElementById('iosMenuSheet');
    const modal = document.getElementById('iosMenuModal');
    if (!menuSheet || !modal) return;

    let startY = 0, currentY = 0, isDragging = false;

    menuSheet.addEventListener('touchstart', function(e) {
        if (e.target.closest('.ios-menu-handle') || menuSheet.scrollTop === 0) {
            startY = e.touches[0].clientY;
            isDragging = true;
        }
    }, { passive: true });

    menuSheet.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        currentY = e.touches[0].clientY;
        const diff = currentY - startY;
        if (diff > 0) menuSheet.style.transform = `translateY(${diff}px)`;
    }, { passive: true });

    menuSheet.addEventListener('touchend', function() {
        if (!isDragging) return;
        isDragging = false;
        if (currentY - startY > 100) toggleIosMenu();
        menuSheet.style.transform = '';
        currentY = startY = 0;
    });
})();
</script>

<?php include_once '../../includes/footer.php'; ?>
