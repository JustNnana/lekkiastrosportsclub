<?php
/**
 * Gate Wey Access Management System
 * User Events Calendar - Dasher UI Enhanced
 * File: user/events/index.php
 */

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../classes/User.php';
require_once '../../classes/Event.php';
require_once '../../classes/EventRsvp.php';
require_once '../../classes/Clan.php';

// Set page title
$pageTitle = 'Community Events';

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

// Get database instance
$db = Database::getInstance();

// Load clan details
$clan = new Clan();
$clan->loadById($clanId);

// Initialize classes
$event = new Event();
$eventRsvp = new EventRsvp();

// Get user's upcoming events
$myUpcomingEvents = $eventRsvp->getUserUpcomingEvents($_SESSION['user_id'], 5);

// Get event statistics for user
$userEventStats = $db->fetchOne(
    "SELECT
        COUNT(DISTINCT er.event_id) as events_attended,
        COUNT(CASE WHEN er.status = 'attending' THEN 1 END) as upcoming_attending,
        COUNT(CASE WHEN er.checked_in = 1 THEN 1 END) as events_checked_in
     FROM event_rsvps er
     JOIN events e ON er.event_id = e.id
     WHERE er.user_id = ? AND e.clan_id = ?",
    [$_SESSION['user_id'], $clanId]
);

$userEventStats = $userEventStats ?: [
    'events_attended' => 0,
    'upcoming_attending' => 0,
    'events_checked_in' => 0
];

// Get total events count for the clan
$totalClanEvents = $db->fetchOne(
    "SELECT COUNT(*) as total FROM events WHERE clan_id = ? AND status != 'cancelled'",
    [$clanId]
)['total'] ?? 0;

// Include header
include_once '../../includes/header.php';
?>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">

<!-- Dasher UI + iOS-Style Calendar Styles -->
<style>
    :root {
        --event-meeting: #3B82F6;
        --event-social: #10B981;
        --event-maintenance: #F59E0B;
        --event-other: #8B5CF6;
        --ios-red: #FF453A;
        --ios-orange: #FF9F0A;
        --ios-yellow: #FFD60A;
        --ios-green: #30D158;
        --ios-teal: #64D2FF;
        --ios-blue: #0A84FF;
        --ios-purple: #BF5AF2;
    }

    /* Statistics Overview Grid - Dasher UI */
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

    /* Events Grid Layout - Dasher UI */
    .content .events-generation-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: var(--spacing-6);
        margin: 0 auto;
    }

    @media (max-width: 992px) {
        .content .events-generation-grid {
            grid-template-columns: 1fr;
        }

        .content .events-sidebar {
            order: -1;
        }
    }

    /* Form Section Card - Dasher UI */
    .content .form-section-card {
        background: transparent;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-lg);
        margin-bottom: var(--spacing-5);
        overflow: hidden;
        transition: var(--theme-transition);
    }

    .content .form-section-header {
        display: flex;
        align-items: flex-start;
        gap: var(--spacing-4);
        padding: var(--spacing-5);
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
    }

    .content .form-section-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--border-radius);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--font-size-xl);
        flex-shrink: 0;
    }

    .content .form-section-icon.primary {
        background: rgba(var(--primary-rgb), 0.1);
        color: var(--primary);
    }

    .content .form-section-icon.success {
        background: rgba(var(--success-rgb), 0.1);
        color: var(--success);
    }

    .content .form-section-icon.warning {
        background: rgba(var(--warning-rgb), 0.1);
        color: var(--warning);
    }

    .content .form-section-icon.danger {
        background: rgba(var(--danger-rgb), 0.1);
        color: var(--danger);
    }

    .content .form-section-title h5 {
        font-size: var(--font-size-lg);
        font-weight: var(--font-weight-semibold);
        color: var(--text-primary);
        margin: 0 0 var(--spacing-1) 0;
    }

    .content .form-section-title p {
        font-size: var(--font-size-sm);
        color: var(--text-secondary);
        margin: 0;
    }

    .content .form-section-body {
        padding: 0;
    }

    .content .form-section-body.with-padding {
        padding: var(--spacing-5);
    }

    /* Sidebar - Dasher UI */
    .content .events-sidebar {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-4);
    }

    .content .sidebar-card {
        background: transparent;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-lg);
        overflow: hidden;
    }

    .content .sidebar-card-header {
        display: flex;
        align-items: center;
        gap: var(--spacing-2);
        padding: var(--spacing-4);
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
    }

    .content .sidebar-card-header i {
        color: var(--primary);
        font-size: var(--font-size-lg);
    }

    .content .sidebar-card-header h6 {
        font-size: var(--font-size-base);
        font-weight: var(--font-weight-semibold);
        color: var(--text-primary);
        margin: 0;
    }

    .content .sidebar-card-body {
        padding: var(--spacing-4);
    }

    /* Summary Items - Dasher UI */
    .content .summary-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--spacing-3) 0;
        border-bottom: 1px solid var(--border-light);
    }

    .content .summary-item:last-child {
        border-bottom: none;
    }

    .content .summary-label {
        font-size: var(--font-size-sm);
        color: var(--text-secondary);
    }

    .content .summary-value {
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-semibold);
        color: var(--text-primary);
    }

    /* Tips List - Dasher UI */
    .content .tips-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .content .tips-list li {
        display: flex;
        align-items: flex-start;
        gap: var(--spacing-2);
        padding: var(--spacing-2);
        font-size: var(--font-size-xs);
        color: var(--text-secondary);
        line-height: 1.5;
    }

    .content .tips-list li i {
        color: var(--success);
        margin-top: 2px;
        flex-shrink: 0;
    }

    /* iOS-Style Calendar Container */
    .calendar-section {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 0;
        margin-bottom: var(--spacing-6);
        overflow: hidden;
    }

    #userEventsCalendar {
        min-height: 400px;
    }

    /* iOS Calendar Header */
    .fc .fc-toolbar {
        padding: 16px 16px 12px;
        margin-bottom: 0 !important;
        background: var(--bg-primary);
        border-bottom: none;
    }

    /* Remove top border lines */
    .fc .fc-scrollgrid-sync-table {
        border-top: none !important;
    }

    .fc .fc-scrollgrid {
        border-top: none !important;
    }

    .fc .fc-scrollgrid-section-header {
        border-bottom: none !important;
    }

    .fc .fc-scrollgrid-section-header > * {
        border-bottom: none !important;
    }

    .fc .fc-toolbar-title {
        font-size: 20px !important;
        font-weight: 700 !important;
        color: var(--text-primary);
        text-transform: capitalize;
    }

    .fc .fc-button {
        background: transparent !important;
        border: none !important;
        color: var(--ios-blue) !important;
        font-weight: 600 !important;
        padding: 6px 12px !important;
        font-size: 15px !important;
        text-transform: capitalize !important;
        box-shadow: none !important;
    }

    .fc .fc-button:hover {
        background: rgba(10, 132, 255, 0.1) !important;
        border-radius: 8px;
    }

    .fc .fc-button:disabled {
        color: var(--text-secondary) !important;
        opacity: 0.5;
    }

    .fc .fc-button-active {
        background: var(--ios-blue) !important;
        color: white !important;
        border-radius: 8px !important;
    }

    .fc .fc-prev-button,
    .fc .fc-next-button {
        padding: 8px !important;
    }

    .fc .fc-icon {
        font-size: 1.2em;
    }

    /* iOS Day Grid */
    .fc .fc-scrollgrid {
        border: none !important;
    }

    .fc .fc-scrollgrid-section > td {
        border: none !important;
    }

    .fc th {
        border: none !important;
        padding: 8px 0 !important;
    }

    .fc td {
        border-color: var(--border-color) !important;
    }

    .fc .fc-col-header-cell {
        background: var(--bg-primary);
        border: none !important;
    }

    .fc .fc-col-header-cell-cushion {
        color: var(--text-secondary);
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        padding: 8px 0;
    }

    /* iOS Day Cells */
    .fc .fc-daygrid-day {
        background: var(--bg-primary);
        min-height: 80px;
    }

    .fc .fc-daygrid-day-frame {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 4px;
        min-height: 70px;
    }

    .fc .fc-daygrid-day-top {
        display: flex;
        justify-content: center;
        width: 100%;
    }

    .fc .fc-daygrid-day-number {
        color: var(--text-primary);
        font-size: 15px;
        font-weight: 500;
        padding: 4px 8px;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    /* iOS Today Highlight */
    .fc .fc-day-today {
        background: transparent !important;
    }

    .fc .fc-day-today .fc-daygrid-day-number {
        background: var(--ios-red);
        color: white !important;
        font-weight: 600;
    }

    /* Other Month Days */
    .fc .fc-day-other .fc-daygrid-day-number {
        color: var(--text-secondary);
        opacity: 0.5;
    }

    /* iOS Event Dots */
    .fc .fc-daygrid-day-events {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 3px;
        margin-top: 4px;
        padding: 0 4px;
        min-height: 20px;
    }

    .fc .fc-daygrid-event-harness {
        margin: 0 !important;
    }

    .fc .fc-daygrid-event {
        margin: 0 !important;
        border-radius: 50% !important;
        width: 6px !important;
        height: 6px !important;
        min-height: 6px !important;
        padding: 0 !important;
        border: none !important;
    }

    .fc .fc-daygrid-event .fc-event-main {
        display: none !important;
    }

    .fc .fc-daygrid-event-dot {
        display: none !important;
    }

    .fc .fc-daygrid-more-link {
        font-size: 10px;
        color: var(--ios-blue);
        font-weight: 600;
        margin-top: 2px;
    }

    /* iOS Event Colors */
    .fc-event-meeting {
        background-color: var(--ios-blue) !important;
    }

    .fc-event-social {
        background-color: var(--ios-green) !important;
    }

    .fc-event-maintenance {
        background-color: var(--ios-orange) !important;
    }

    .fc-event-other {
        background-color: var(--ios-purple) !important;
    }

    .fc-event-cancelled {
        background-color: #6B7280 !important;
        opacity: 0.5;
    }

    /* Selected Day Events Panel */
    .selected-day-events {
        background: var(--bg-secondary);
        border-top: 1px solid var(--border-color);
        padding: 16px;
        max-height: 300px;
        overflow-y: auto;
    }

    .selected-day-header {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        margin-bottom: 12px;
        letter-spacing: 0.5px;
    }

    .selected-day-event {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px;
        background: var(--bg-primary);
        border-radius: 10px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .selected-day-event:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-sm);
    }

    .selected-day-event:last-child {
        margin-bottom: 0;
    }

    .event-dot-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 4px;
    }

    .event-dot-indicator.meeting { background: var(--ios-blue); }
    .event-dot-indicator.social { background: var(--ios-green); }
    .event-dot-indicator.maintenance { background: var(--ios-orange); }
    .event-dot-indicator.other { background: var(--ios-purple); }
    .event-dot-indicator.cancelled { background: #6B7280; }

    .selected-event-info {
        flex: 1;
        min-width: 0;
    }

    .selected-event-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .selected-event-title.cancelled {
        text-decoration: line-through;
        color: var(--text-secondary);
    }

    .selected-event-time {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
    }

    .selected-event-badge {
        font-size: 10px;
        font-weight: 700;
        color: white;
        background: var(--ios-red);
        padding: 2px 6px;
        border-radius: 4px;
        text-transform: uppercase;
        margin-left: 8px;
    }

    .no-events-msg {
        text-align: center;
        color: var(--text-secondary);
        font-size: 14px;
        padding: 20px;
    }

    /* Event Legend */
    .event-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        padding: 12px 16px;
        background: var(--bg-secondary);
        border-top: 1px solid var(--border-color);
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        color: var(--text-secondary);
    }

    .legend-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .legend-dot.meeting { background: var(--ios-blue); }
    .legend-dot.social { background: var(--ios-green); }
    .legend-dot.maintenance { background: var(--ios-orange); }
    .legend-dot.other { background: var(--ios-purple); }

    /* iOS-Style My Events Section */
    .my-events-section {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 0;
        overflow: hidden;
    }

    .my-events-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .section-title {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .view-all-link {
        font-size: 15px;
        font-weight: 600;
        color: var(--ios-blue);
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .view-all-link:hover {
        opacity: 0.8;
    }

    .my-events-list {
        padding: 0;
    }

    /* iOS-Style Event Item */
    .ios-event-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        background: var(--bg-primary);
        cursor: pointer;
        transition: background 0.15s ease;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-event-item:last-child {
        border-bottom: none;
    }

    .ios-event-item:hover {
        background: rgba(255, 255, 255, 0.03);
    }

    .ios-event-item:active {
        background: rgba(255, 255, 255, 0.06);
    }

    .ios-event-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 5px;
    }

    .ios-event-dot.meeting { background: var(--ios-blue); }
    .ios-event-dot.social { background: var(--ios-green); }
    .ios-event-dot.maintenance { background: var(--ios-orange); }
    .ios-event-dot.other { background: var(--ios-purple); }

    .ios-event-content {
        flex: 1;
        min-width: 0;
    }

    .ios-event-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ios-event-datetime {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0 0 2px 0;
    }

    .ios-event-location {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .ios-event-location i {
        font-size: 11px;
    }

    .ios-event-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        flex-shrink: 0;
    }

    .ios-event-type-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 6px;
        text-transform: capitalize;
    }

    .ios-event-type-badge.meeting {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    .ios-event-type-badge.social {
        background: rgba(48, 209, 88, 0.15);
        color: var(--ios-green);
    }

    .ios-event-type-badge.maintenance {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .ios-event-type-badge.other {
        background: rgba(191, 90, 242, 0.15);
        color: var(--ios-purple);
    }

    .ios-rsvp-badge {
        font-size: 10px;
        font-weight: 600;
        color: var(--ios-green);
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .ios-rsvp-badge i {
        font-size: 10px;
    }

    .ios-chevron {
        color: var(--text-secondary);
        font-size: 12px;
        margin-left: 4px;
        opacity: 0.5;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px 20px;
    }

    .empty-state-icon {
        font-size: 48px;
        color: var(--text-secondary);
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .empty-state-title {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 8px 0;
    }

    .empty-state-description {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0;
        line-height: 1.5;
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

    .ios-menu-item-icon.primary { background: var(--primary); }
    .ios-menu-item-icon.success { background: var(--success); }
    .ios-menu-item-icon.warning { background: var(--warning); }
    .ios-menu-item-icon.danger { background: var(--danger); }
    .ios-menu-item-icon.info { background: var(--info); }

    .ios-menu-item-label {
        font-size: 15px;
        font-weight: 500;
    }

    .ios-menu-item-value {
        font-size: 15px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 6px;
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

    .ios-menu-stat-value.success { color: var(--success); }
    .ios-menu-stat-value.warning { color: var(--warning); }

    .ios-menu-legend-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-menu-legend-item:last-child {
        border-bottom: none;
    }

    .ios-menu-legend-left {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ios-menu-legend-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
    }

    .ios-menu-legend-dot.meeting { background: var(--ios-blue); }
    .ios-menu-legend-dot.social { background: var(--ios-green); }
    .ios-menu-legend-dot.maintenance { background: var(--ios-orange); }
    .ios-menu-legend-dot.other { background: var(--ios-purple); }

    .ios-menu-tip {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-menu-tip:last-child {
        border-bottom: none;
    }

    .ios-menu-tip i {
        color: var(--success);
        font-size: 14px;
        margin-top: 2px;
    }

    .ios-menu-tip span {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.4;
    }

    /* 3-Dot Menu Button for iOS Options */
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

    /* Mobile Optimizations - Dasher UI */
    @media (max-width: 768px) {
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
            min-width: 180px !important;
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

        .content .form-section-header {
            padding: var(--spacing-4);
        }

        .content .form-section-icon {
            width: 40px;
            height: 40px;
            font-size: var(--font-size-lg);
        }

        .content-actions {
            display: flex !important;
            justify-content: flex-end !important;
            gap: 0.5rem;
            flex-wrap: wrap;
            white-space: nowrap !important;
        }

        .content-actions .btn {
            flex: 0 1 auto !important;
            white-space: nowrap;
        }

        .calendar-section {
            border-radius: 12px;
        }

        .my-events-section {
            border-radius: 12px;
        }

        .my-events-header {
            padding: 14px;
        }

        .section-title {
            font-size: 15px;
        }

        .view-all-link {
            font-size: 13px;
        }

        .ios-event-item {
            padding: 12px 14px;
        }

        .ios-event-title {
            font-size: 14px;
        }

        .ios-event-datetime,
        .ios-event-location {
            font-size: 12px;
        }

        .ios-event-type-badge {
            font-size: 10px;
            padding: 2px 6px;
        }

        .ios-rsvp-badge {
            font-size: 9px;
        }

        .empty-state {
            padding: 32px 16px;
        }

        .empty-state-icon {
            font-size: 40px;
        }

        .empty-state-title {
            font-size: 15px;
        }

        .empty-state-description {
            font-size: 13px;
        }

        /* Show iOS options button, hide desktop sidebar */
        .ios-options-btn {
            display: flex;
        }

        .events-sidebar {
            display: none !important;
        }

        .events-generation-grid {
            grid-template-columns: 1fr !important;
        }

        /* Hide content header on mobile for app-like experience */
        .content-header {
            display: none !important;
        }
    }

    /* iOS-Style Floating Action Button */
    .ios-fab {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--ios-blue) 0%, #0066CC 100%);
        color: white;
        border: none;
        box-shadow: 0 4px 14px rgba(10, 132, 255, 0.4), 0 2px 6px rgba(0, 0, 0, 0.2);
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        z-index: 1000;
        text-decoration: none;
    }

    .ios-fab:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 20px rgba(10, 132, 255, 0.5), 0 4px 10px rgba(0, 0, 0, 0.25);
    }

    .ios-fab:active {
        transform: scale(0.95);
    }

    .ios-fab i {
        font-size: 22px;
    }

    @media (max-width: 768px) {
        /* .ios-fab {
            display: flex;
        } */
    }
</style>

<!-- Main Content -->
<div class="main-content">
    <!-- Sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <!-- Dasher UI Content Area -->
    <div class="content">
        <!-- Content Header -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="content-title">
                        <i class="fas fa-calendar-alt me-2"></i>
                        Community Events
                    </h1>
                    <nav class="content-breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active">Events</li>
                        </ol>
                    </nav>
                    <p class="content-description">View and manage community events for <?php echo htmlspecialchars($clan->getName()); ?></p>
                </div>
                <div class="content-actions">
                    <a href="<?php echo BASE_URL; ?>user/events/create-event.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>
                        <span>Create Event</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-overview-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Total Events</div>
                    <div class="stat-value"><?php echo number_format($totalClanEvents); ?></div>
                    <div class="stat-detail">In your community</div>
                </div>
            </div>

            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">RSVPs Made</div>
                    <div class="stat-value"><?php echo number_format($userEventStats['events_attended']); ?></div>
                    <div class="stat-detail">Events you've responded to</div>
                </div>
            </div>

            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Upcoming</div>
                    <div class="stat-value"><?php echo number_format($userEventStats['upcoming_attending']); ?></div>
                    <div class="stat-detail">Events you're attending</div>
                </div>
            </div>

            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Attended</div>
                    <div class="stat-value"><?php echo number_format($userEventStats['events_checked_in']); ?></div>
                    <div class="stat-detail">Events checked in</div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="events-generation-grid">
            <!-- Main Content Section -->
            <div class="events-form-section">
                <!-- Calendar Card -->
                <div class="form-section-card">
                    <div class="form-section-header">
                        <div class="form-section-icon primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="form-section-title">
                            <h5>Event Calendar</h5>
                            <p>Browse upcoming community events</p>
                        </div>
                        <!-- Mobile 3-Dot Menu Button -->
                        <button class="ios-options-btn" id="iosOptionsBtn" aria-label="Open menu">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                    </div>
                    <div class="form-section-body">
                        <!-- Calendar View -->
                        <div class="calendar-section" style="border: none; border-radius: 0; margin-bottom: 0;">
                            <div id="userEventsCalendar"></div>

                            <!-- Selected Day Events Panel -->
                            <div id="selectedDayEvents" class="selected-day-events" style="display: none;">
                                <div class="selected-day-header">
                                    <span id="selectedDayTitle">Today's Events</span>
                                </div>
                                <div id="selectedDayEventsList"></div>
                            </div>

                            <!-- Event Legend -->
                            <div class="event-legend">
                                <div class="legend-item">
                                    <span class="legend-dot meeting"></span>
                                    <span>Meeting</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-dot social"></span>
                                    <span>Social</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-dot maintenance"></span>
                                    <span>Maintenance</span>
                                </div>
                                <div class="legend-item">
                                    <span class="legend-dot other"></span>
                                    <span>Other</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Upcoming Events - iOS Style -->
                <div class="my-events-section">
                    <div class="my-events-header">
                        <h2 class="section-title">
                            <i class="fas fa-clock me-2"></i>Upcoming Events
                        </h2>
                        <?php if (count($myUpcomingEvents) > 0): ?>
                        <a href="<?php echo BASE_URL; ?>user/events/my-events.php" class="view-all-link">
                            View All <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>

                    <div class="my-events-list">
                        <?php if (count($myUpcomingEvents) > 0): ?>
                            <?php foreach ($myUpcomingEvents as $evt): ?>
                                <?php
                                $eventTypeClass = $evt['event_type'];
                                $displayEventType = $evt['event_type'] === 'other' && !empty($evt['custom_event_type'])
                                    ? $evt['custom_event_type']
                                    : ucfirst($evt['event_type']);
                                ?>
                                <div class="ios-event-item" onclick="window.location.href='<?php echo BASE_URL; ?>user/events/view-event.php?id=<?php echo encryptId($evt['id']); ?>'">
                                    <span class="ios-event-dot <?php echo $eventTypeClass; ?>"></span>
                                    <div class="ios-event-content">
                                        <p class="ios-event-title"><?php echo htmlspecialchars($evt['title']); ?></p>
                                        <p class="ios-event-datetime">
                                            <?php echo date('M j', strtotime($evt['start_datetime'])); ?> &bull;
                                            <?php echo date('g:i A', strtotime($evt['start_datetime'])); ?>
                                        </p>
                                        <?php if (!empty($evt['location'])): ?>
                                        <p class="ios-event-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($evt['location']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ios-event-meta">
                                        <span class="ios-event-type-badge <?php echo $eventTypeClass; ?>">
                                            <?php echo htmlspecialchars($displayEventType); ?>
                                        </span>
                                        <span class="ios-rsvp-badge">
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo ucfirst($evt['rsvp_status']); ?>
                                            <?php if ($evt['guest_count'] > 0): ?>
                                                +<?php echo $evt['guest_count']; ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <i class="fas fa-chevron-right ios-chevron"></i>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h3 class="empty-state-title">No Upcoming Events</h3>
                                <p class="empty-state-description">
                                    You haven't RSVP'd to any events yet.<br>Browse the calendar to find events to attend.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar Section -->
            <div class="events-sidebar">
                <!-- Quick Actions Card -->
                <div class="sidebar-card">
                    <div class="sidebar-card-header">
                        <i class="fas fa-bolt"></i>
                        <h6>Quick Actions</h6>
                    </div>
                    <div class="sidebar-card-body">
                        <div class="d-grid gap-2">
                            <a href="<?php echo BASE_URL; ?>user/events/create-event.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>
                                Create Event
                            </a>
                            <a href="<?php echo BASE_URL; ?>user/events/my-events.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-list me-2"></i>
                                My Events
                            </a>
                            <a href="<?php echo BASE_URL; ?>user/events/browse-events.php" class="btn btn-outline-secondary w-100" style="border-color: var(--border-color);">
                                <i class="fas fa-search me-2"></i>
                                Browse All Events
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Your Stats Card -->
                <div class="sidebar-card">
                    <div class="sidebar-card-header">
                        <i class="fas fa-chart-pie"></i>
                        <h6>Your Activity</h6>
                    </div>
                    <div class="sidebar-card-body">
                        <div class="summary-item">
                            <span class="summary-label">RSVPs Made</span>
                            <span class="summary-value"><?php echo number_format($userEventStats['events_attended']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Upcoming</span>
                            <span class="summary-value text-warning"><?php echo number_format($userEventStats['upcoming_attending']); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">Attended</span>
                            <span class="summary-value text-success"><?php echo number_format($userEventStats['events_checked_in']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Event Types Legend Card -->
                <div class="sidebar-card">
                    <div class="sidebar-card-header">
                        <i class="fas fa-tags"></i>
                        <h6>Event Types</h6>
                    </div>
                    <div class="sidebar-card-body">
                        <div class="summary-item">
                            <span class="summary-label">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--ios-blue); margin-right: 8px;"></span>
                                Meeting
                            </span>
                            <span class="summary-value"><i class="fas fa-users" style="color: var(--ios-blue);"></i></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--ios-green); margin-right: 8px;"></span>
                                Social
                            </span>
                            <span class="summary-value"><i class="fas fa-glass-cheers" style="color: var(--ios-green);"></i></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--ios-orange); margin-right: 8px;"></span>
                                Maintenance
                            </span>
                            <span class="summary-value"><i class="fas fa-tools" style="color: var(--ios-orange);"></i></span>
                        </div>
                        <div class="summary-item">
                            <span class="summary-label">
                                <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: var(--ios-purple); margin-right: 8px;"></span>
                                Other
                            </span>
                            <span class="summary-value"><i class="fas fa-star" style="color: var(--ios-purple);"></i></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Tips Card -->
                <div class="sidebar-card">
                    <div class="sidebar-card-header">
                        <i class="fas fa-lightbulb"></i>
                        <h6>Quick Tips</h6>
                    </div>
                    <div class="sidebar-card-body">
                        <ul class="tips-list">
                            <li>
                                <i class="fas fa-check-circle"></i>
                                Tap on any date to see events scheduled for that day
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                RSVP to events to receive reminders before they start
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                You can invite guests when RSVPing to events
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- iOS-Style Floating Action Button (Mobile Only) -->
<a href="<?php echo BASE_URL; ?>user/events/create-event.php" class="ios-fab" title="Create Event">
    <i class="fas fa-calendar-plus"></i>
</a>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('userEventsCalendar');
    const selectedDayEventsEl = document.getElementById('selectedDayEvents');
    const selectedDayTitle = document.getElementById('selectedDayTitle');
    const selectedDayEventsList = document.getElementById('selectedDayEventsList');

    // Store all events for day selection
    let allEvents = [];

    // iOS-style colors
    const iosColors = {
        'meeting': '#0A84FF',
        'social': '#30D158',
        'maintenance': '#FF9F0A',
        'other': '#BF5AF2'
    };

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next',
            center: 'title',
            right: 'today'
        },
        buttonText: {
            today: 'Today'
        },
        titleFormat: { year: 'numeric', month: 'long' },
        height: 'auto',
        fixedWeekCount: false,
        showNonCurrentDates: true,
        dayMaxEvents: 3,
        events: function(info, successCallback, failureCallback) {
            fetch('<?php echo BASE_URL; ?>api/get-calendar-events.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'clan_id=<?php echo encryptId($clanId); ?>&start=' + info.startStr + '&end=' + info.endStr
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Check if data is an array (valid events) or an error object
                if (Array.isArray(data)) {
                    allEvents = data;
                    successCallback(data);
                    // Show today's events on initial load
                    showDayEvents(new Date());
                } else {
                    // API returned an error object
                    console.error('API Error:', data);
                    allEvents = [];
                    successCallback([]);
                    showDayEvents(new Date());
                }
            })
            .catch(error => {
                console.error('Error loading events:', error);
                allEvents = [];
                successCallback([]); // Return empty array instead of failing
                showDayEvents(new Date());
            });
        },
        dateClick: function(info) {
            // Show events for clicked day
            showDayEvents(info.date);

            // Highlight selected day
            document.querySelectorAll('.fc-day-selected').forEach(el => {
                el.classList.remove('fc-day-selected');
            });
            info.dayEl.classList.add('fc-day-selected');
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            window.location.href = '<?php echo BASE_URL; ?>user/events/view-event.php?id=' + info.event.id;
        },
        eventClassNames: function(arg) {
            const classes = ['fc-event-' + (arg.event.extendedProps.event_type || 'other')];
            if (arg.event.extendedProps.status === 'cancelled') {
                classes.push('fc-event-cancelled');
            }
            return classes;
        },
        eventContent: function(arg) {
            // Return empty for dot display (CSS handles the styling)
            return { html: '' };
        },
        eventDidMount: function(info) {
            const eventType = info.event.extendedProps.event_type || 'other';
            const status = info.event.extendedProps.status;

            if (status === 'cancelled') {
                info.el.style.backgroundColor = '#6B7280';
                info.el.style.opacity = '0.5';
            } else {
                info.el.style.backgroundColor = iosColors[eventType] || iosColors['other'];
            }
        }
    });

    calendar.render();

    // Function to show events for a selected day
    function showDayEvents(date) {
        const dateStr = formatDate(date);
        const dayEvents = allEvents.filter(evt => {
            const evtDate = evt.start.split('T')[0];
            return evtDate === dateStr;
        });

        // Format the header date
        const options = { weekday: 'long', month: 'long', day: 'numeric' };
        const isToday = dateStr === formatDate(new Date());
        selectedDayTitle.textContent = isToday ? "Today's Events" : date.toLocaleDateString('en-US', options);

        // Show the panel
        selectedDayEventsEl.style.display = 'block';

        if (dayEvents.length === 0) {
            selectedDayEventsList.innerHTML = '<div class="no-events-msg">No events scheduled</div>';
            return;
        }

        // Sort events by time
        dayEvents.sort((a, b) => new Date(a.start) - new Date(b.start));

        // Render events
        let html = '';
        dayEvents.forEach(evt => {
            const eventType = evt.extendedProps?.event_type || 'other';
            const status = evt.extendedProps?.status || 'upcoming';
            const isCancelled = status === 'cancelled';

            const startTime = new Date(evt.start).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            const endTime = evt.end ? new Date(evt.end).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : '';

            html += `
                <div class="selected-day-event" onclick="window.location.href='<?php echo BASE_URL; ?>user/events/view-event.php?id=${evt.id}'">
                    <span class="event-dot-indicator ${isCancelled ? 'cancelled' : eventType}"></span>
                    <div class="selected-event-info">
                        <p class="selected-event-title ${isCancelled ? 'cancelled' : ''}">
                            ${escapeHtml(evt.title)}
                            ${isCancelled ? '<span class="selected-event-badge">Cancelled</span>' : ''}
                        </p>
                        <p class="selected-event-time">${startTime}${endTime ? ' - ' + endTime : ''}</p>
                    </div>
                </div>
            `;
        });

        selectedDayEventsList.innerHTML = html;
    }

    // Helper functions
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<!-- Additional iOS Calendar Styles -->
<style>
    /* Selected Day Highlight */
    .fc-day-selected {
        background: rgba(10, 132, 255, 0.1) !important;
    }

    .fc-day-selected .fc-daygrid-day-number {
        background: var(--ios-blue) !important;
        color: white !important;
    }

    /* Mobile responsive adjustments */
    @media (max-width: 768px) {
        .fc .fc-toolbar {
            flex-direction: row;
            gap: 8px;
        }

        .fc .fc-toolbar-title {
            font-size: 18px !important;
        }

        .fc .fc-daygrid-day {
            min-height: 60px;
        }

        .fc .fc-daygrid-day-frame {
            min-height: 55px;
        }

        .fc .fc-daygrid-day-number {
            font-size: 14px;
            width: 28px;
            height: 28px;
        }

        .fc .fc-daygrid-event {
            width: 5px !important;
            height: 5px !important;
            min-height: 5px !important;
        }

        .event-legend {
            gap: 12px;
            padding: 10px 12px;
        }

        .legend-item {
            font-size: 11px;
        }

        .selected-day-events {
            padding: 12px;
        }

        .selected-day-event {
            padding: 10px;
        }

        .selected-event-title {
            font-size: 14px;
        }

        .selected-event-time {
            font-size: 12px;
        }
    }

    /* Hide default event text styling */
    .fc .fc-daygrid-event .fc-event-title,
    .fc .fc-daygrid-event .fc-event-time {
        display: none !important;
    }

    /* Cursor pointer for clickable days */
    .fc .fc-daygrid-day {
        cursor: pointer;
    }

    .fc .fc-daygrid-day:hover {
        background: rgba(255, 255, 255, 0.03);
    }
</style>

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
                <a href="<?php echo BASE_URL; ?>user/events/create-event.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-plus"></i>
                        </div>
                        <span class="ios-menu-item-label">Create Event</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/my-events.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon info">
                            <i class="fas fa-list"></i>
                        </div>
                        <span class="ios-menu-item-label">My Events</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/browse-events.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon success">
                            <i class="fas fa-search"></i>
                        </div>
                        <span class="ios-menu-item-label">Browse All Events</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <!-- Your Activity Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Your Activity</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">RSVPs Made</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($userEventStats['events_attended']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Upcoming</span>
                    <span class="ios-menu-stat-value warning"><?php echo number_format($userEventStats['upcoming_attending']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Attended</span>
                    <span class="ios-menu-stat-value success"><?php echo number_format($userEventStats['events_checked_in']); ?></span>
                </div>
            </div>
        </div>

        <!-- Event Types Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Event Types</div>
            <div class="ios-menu-card">
                <div class="ios-menu-legend-item">
                    <div class="ios-menu-legend-left">
                        <span class="ios-menu-legend-dot meeting"></span>
                        <span>Meeting</span>
                    </div>
                    <i class="fas fa-users" style="color: var(--ios-blue);"></i>
                </div>
                <div class="ios-menu-legend-item">
                    <div class="ios-menu-legend-left">
                        <span class="ios-menu-legend-dot social"></span>
                        <span>Social</span>
                    </div>
                    <i class="fas fa-glass-cheers" style="color: var(--ios-green);"></i>
                </div>
                <div class="ios-menu-legend-item">
                    <div class="ios-menu-legend-left">
                        <span class="ios-menu-legend-dot maintenance"></span>
                        <span>Maintenance</span>
                    </div>
                    <i class="fas fa-tools" style="color: var(--ios-orange);"></i>
                </div>
                <div class="ios-menu-legend-item">
                    <div class="ios-menu-legend-left">
                        <span class="ios-menu-legend-dot other"></span>
                        <span>Other</span>
                    </div>
                    <i class="fas fa-star" style="color: var(--ios-purple);"></i>
                </div>
            </div>
        </div>

        <!-- Quick Tips Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Tips</div>
            <div class="ios-menu-card">
                <div class="ios-menu-tip">
                    <i class="fas fa-check-circle"></i>
                    <span>Tap on any date to see events scheduled for that day</span>
                </div>
                <div class="ios-menu-tip">
                    <i class="fas fa-check-circle"></i>
                    <span>RSVP to events to receive reminders before they start</span>
                </div>
                <div class="ios-menu-tip">
                    <i class="fas fa-check-circle"></i>
                    <span>You can invite guests when RSVPing to events</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// iOS-Style Mobile Menu Functions
const iosMenuBackdrop = document.getElementById('iosMenuBackdrop');
const iosMenuModal = document.getElementById('iosMenuModal');
const iosOptionsBtn = document.getElementById('iosOptionsBtn');
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

if (iosOptionsBtn) {
    iosOptionsBtn.addEventListener('click', openIosMenu);
}

if (iosMenuClose) {
    iosMenuClose.addEventListener('click', closeIosMenu);
}

if (iosMenuBackdrop) {
    iosMenuBackdrop.addEventListener('click', closeIosMenu);
}

// Swipe down to close
let startY = 0;
let currentY = 0;

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
</script>

<?php include_once '../../includes/footer.php'; ?>
