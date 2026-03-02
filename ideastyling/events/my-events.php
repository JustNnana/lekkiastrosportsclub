<?php
/**
 * Gate Wey Access Management System
 * User My Events - iOS-Style Mobile Design
 * File: user/events/my-events.php
 */

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../classes/User.php';
require_once '../../classes/Event.php';
require_once '../../classes/EventRsvp.php';
require_once '../../classes/Clan.php';

// Set page title
$pageTitle = 'My Events';

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
$eventRsvp = new EventRsvp();

// Get filter parameters
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$filterTime = isset($_GET['time']) ? $_GET['time'] : 'all';

// Build query based on filters
$whereConditions = ["er.user_id = ?", "e.clan_id = ?"];
$params = [$_SESSION['user_id'], $clanId];

if ($filterStatus !== 'all') {
    $whereConditions[] = "er.status = ?";
    $params[] = $filterStatus;
}

if ($filterTime === 'upcoming') {
    $whereConditions[] = "e.start_datetime > NOW()";
} elseif ($filterTime === 'past') {
    $whereConditions[] = "e.end_datetime < NOW()";
}

$whereClause = implode(' AND ', $whereConditions);

// Get user's events
$userEvents = $db->fetchAll(
    "SELECT
        e.*,
        er.id as rsvp_id,
        er.status as rsvp_status,
        er.guest_count,
        er.guest_names,
        er.checked_in,
        er.checked_in_at,
        er.responded_at as rsvp_date
     FROM event_rsvps er
     JOIN events e ON er.event_id = e.id
     WHERE {$whereClause}
     ORDER BY e.start_datetime DESC",
    $params
);

// Get statistics
$stats = $db->fetchOne(
    "SELECT
        COUNT(DISTINCT er.event_id) as total_events,
        COUNT(CASE WHEN er.status = 'attending' AND e.start_datetime > NOW() THEN 1 END) as upcoming_attending,
        COUNT(CASE WHEN er.checked_in = 1 THEN 1 END) as total_checked_in,
        COUNT(CASE WHEN er.status = 'attending' AND e.end_datetime < NOW() AND er.checked_in = 0 THEN 1 END) as missed_events
     FROM event_rsvps er
     JOIN events e ON er.event_id = e.id
     WHERE er.user_id = ? AND e.clan_id = ?",
    [$_SESSION['user_id'], $clanId]
);

$stats = $stats ?: [
    'total_events' => 0,
    'upcoming_attending' => 0,
    'total_checked_in' => 0,
    'missed_events' => 0
];

// Include header
include_once '../../includes/header.php';
?>

<!-- iOS-Style My Events Styles -->
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

    /* Filter Section - iOS Style */
    .ios-filter-section {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 16px;
        margin-bottom: var(--spacing-6);
    }

    .ios-filter-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .ios-filter-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ios-filter-title i {
        color: var(--ios-blue);
    }

    .ios-filter-clear {
        font-size: 13px;
        color: var(--ios-blue);
        background: none;
        border: none;
        cursor: pointer;
        padding: 4px 8px;
    }

    .ios-filter-clear:hover {
        opacity: 0.8;
    }

    .ios-filter-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .ios-filter-group label {
        display: block;
        font-size: 11px;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
    }

    .ios-filter-group select {
        width: 100%;
        padding: 10px 12px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        font-size: 15px;
        color: var(--text-primary);
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236b7280' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10l-5 5z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 12px center;
        cursor: pointer;
    }

    .ios-filter-group select:focus {
        outline: none;
        border-color: var(--ios-blue);
    }

    /* iOS-Style Events List */
    .ios-events-section {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
    }

    .ios-events-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-events-title {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .ios-events-count {
        font-size: 13px;
        color: var(--text-secondary);
        background: var(--bg-secondary);
        padding: 4px 10px;
        border-radius: 12px;
    }

    .ios-events-list {
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

    .ios-event-item.cancelled {
        opacity: 0.6;
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

    .ios-event-title.cancelled {
        text-decoration: line-through;
        color: var(--text-secondary);
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
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .ios-rsvp-badge.attending {
        color: var(--ios-green);
    }

    .ios-rsvp-badge.maybe {
        color: var(--ios-orange);
    }

    .ios-rsvp-badge.not_attending {
        color: var(--ios-red);
    }

    .ios-rsvp-badge i {
        font-size: 10px;
    }

    .ios-checked-badge {
        font-size: 10px;
        font-weight: 600;
        color: var(--ios-green);
        display: flex;
        align-items: center;
        gap: 3px;
    }

    .ios-chevron {
        color: var(--text-secondary);
        font-size: 12px;
        margin-left: 4px;
        opacity: 0.5;
        align-self: center;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 48px 24px;
    }

    .empty-state-icon {
        font-size: 56px;
        color: var(--text-secondary);
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .empty-state-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 8px 0;
    }

    .empty-state-description {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0 0 20px 0;
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
    .ios-menu-stat-value.danger { color: var(--danger); }

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

    /* Mobile Optimizations */
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

        /* Hide content header on mobile for app-like experience */
        .content-header {
            display: none !important;
        }

        /* Hide filter section on mobile - moved to 3-dot menu */
        .ios-filter-section {
            display: none;
        }

        /* Show iOS options button */
        .ios-options-btn {
            display: flex;
        }

        .ios-events-section {
            border-radius: 12px;
        }

        .ios-events-header {
            padding: 14px;
        }

        .ios-events-title {
            font-size: 15px;
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

        .ios-rsvp-badge,
        .ios-checked-badge {
            font-size: 9px;
        }

        .empty-state {
            padding: 32px 16px;
        }

        .empty-state-icon {
            font-size: 48px;
        }

        .empty-state-title {
            font-size: 16px;
        }

        .empty-state-description {
            font-size: 13px;
        }
    }
</style>

<!-- Main Content -->
<div class="main-content">
    <!-- Sidebar -->
    <?php include_once '../../includes/sidebar.php'; ?>

    <!-- Dasher UI Content Area -->
    <div class="content">
        <!-- Content Header (Hidden on Mobile) -->
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="content-title">
                        <i class="fas fa-calendar-check me-2"></i>
                        My Events
                    </h1>
                    <nav class="content-breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item">
                                <a href="<?php echo BASE_URL; ?>user/events/" class="breadcrumb-link">Events</a>
                            </li>
                            <li class="breadcrumb-item active">My Events</li>
                        </ol>
                    </nav>
                    <p class="content-description">View your RSVP'd events and attendance history</p>
                </div>
                <div class="content-actions">
                    <a href="<?php echo BASE_URL; ?>user/events/" class="btn btn-outline-primary" style="border-color: var(--border-color);">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <span>Browse Events</span>
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
                    <div class="stat-value"><?php echo number_format($stats['total_events']); ?></div>
                    <div class="stat-detail">Events you've RSVP'd to</div>
                </div>
            </div>

            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Upcoming</div>
                    <div class="stat-value"><?php echo number_format($stats['upcoming_attending']); ?></div>
                    <div class="stat-detail">Events you're attending</div>
                </div>
            </div>

            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Attended</div>
                    <div class="stat-value"><?php echo number_format($stats['total_checked_in']); ?></div>
                    <div class="stat-detail">Events checked in</div>
                </div>
            </div>

            <div class="stat-card stat-danger">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-label">Missed</div>
                    <div class="stat-value"><?php echo number_format($stats['missed_events']); ?></div>
                    <div class="stat-detail">Events not attended</div>
                </div>
            </div>
        </div>

        <!-- Filter Section (Desktop Only) -->
        <div class="ios-filter-section">
            <div class="ios-filter-header">
                <span class="ios-filter-title">
                    <i class="fas fa-filter"></i>
                    Filter Events
                </span>
                <?php if ($filterStatus !== 'all' || $filterTime !== 'all'): ?>
                <a href="<?php echo BASE_URL; ?>user/events/my-events.php" class="ios-filter-clear">Clear Filters</a>
                <?php endif; ?>
            </div>
            <form method="GET" action="">
                <div class="ios-filter-grid">
                    <div class="ios-filter-group">
                        <label>RSVP Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="attending" <?php echo $filterStatus === 'attending' ? 'selected' : ''; ?>>Attending</option>
                            <option value="maybe" <?php echo $filterStatus === 'maybe' ? 'selected' : ''; ?>>Maybe</option>
                            <option value="not_attending" <?php echo $filterStatus === 'not_attending' ? 'selected' : ''; ?>>Not Attending</option>
                        </select>
                    </div>
                    <div class="ios-filter-group">
                        <label>Time Period</label>
                        <select name="time" onchange="this.form.submit()">
                            <option value="all" <?php echo $filterTime === 'all' ? 'selected' : ''; ?>>All Events</option>
                            <option value="upcoming" <?php echo $filterTime === 'upcoming' ? 'selected' : ''; ?>>Upcoming Only</option>
                            <option value="past" <?php echo $filterTime === 'past' ? 'selected' : ''; ?>>Past Only</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- Events List Section -->
        <div class="ios-events-section">
            <div class="ios-events-header">
                <h2 class="ios-events-title">
                    <i class="fas fa-list me-2"></i>
                    <?php
                    if ($filterTime === 'upcoming') echo 'Upcoming Events';
                    elseif ($filterTime === 'past') echo 'Past Events';
                    else echo 'All My Events';
                    ?>
                </h2>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="ios-events-count"><?php echo count($userEvents); ?> event<?php echo count($userEvents) !== 1 ? 's' : ''; ?></span>
                    <!-- Mobile 3-Dot Menu Button -->
                    <button class="ios-options-btn" id="iosOptionsBtn" aria-label="Open menu">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>

            <?php if (count($userEvents) > 0): ?>
                <div class="ios-events-list">
                    <?php foreach ($userEvents as $evt): ?>
                        <?php
                        $eventTypeClass = $evt['event_type'];
                        $displayEventType = $evt['event_type'] === 'other' && !empty($evt['custom_event_type'])
                            ? $evt['custom_event_type']
                            : ucfirst($evt['event_type']);
                        $isPast = strtotime($evt['end_datetime']) < time();
                        $isCancelled = $evt['status'] === 'cancelled';
                        ?>
                        <div class="ios-event-item <?php echo $isCancelled ? 'cancelled' : ''; ?>"
                             onclick="window.location.href='<?php echo BASE_URL; ?>user/events/view-event.php?id=<?php echo encryptId($evt['id']); ?>'">
                            <span class="ios-event-dot <?php echo $eventTypeClass; ?>"></span>
                            <div class="ios-event-content">
                                <p class="ios-event-title <?php echo $isCancelled ? 'cancelled' : ''; ?>">
                                    <?php echo htmlspecialchars($evt['title']); ?>
                                    <?php if ($isCancelled): ?>
                                        <span style="font-size: 10px; color: var(--ios-red); font-weight: 700; margin-left: 6px;">CANCELLED</span>
                                    <?php endif; ?>
                                </p>
                                <p class="ios-event-datetime">
                                    <?php echo date('M j, Y', strtotime($evt['start_datetime'])); ?> &bull;
                                    <?php echo date('g:i A', strtotime($evt['start_datetime'])); ?> - <?php echo date('g:i A', strtotime($evt['end_datetime'])); ?>
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
                                <span class="ios-rsvp-badge <?php echo $evt['rsvp_status']; ?>">
                                    <?php if ($evt['rsvp_status'] === 'attending'): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php elseif ($evt['rsvp_status'] === 'maybe'): ?>
                                        <i class="fas fa-question-circle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle"></i>
                                    <?php endif; ?>
                                    <?php echo ucfirst(str_replace('_', ' ', $evt['rsvp_status'])); ?>
                                    <?php if ($evt['guest_count'] > 0): ?>
                                        +<?php echo $evt['guest_count']; ?>
                                    <?php endif; ?>
                                </span>
                                <?php if ($evt['checked_in']): ?>
                                <span class="ios-checked-badge">
                                    <i class="fas fa-check-double"></i>
                                    Checked In
                                </span>
                                <?php endif; ?>
                            </div>
                            <i class="fas fa-chevron-right ios-chevron"></i>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <h3 class="empty-state-title">No Events Found</h3>
                    <p class="empty-state-description">
                        <?php if ($filterStatus !== 'all' || $filterTime !== 'all'): ?>
                            No events match your current filters.<br>Try changing your filter settings.
                        <?php else: ?>
                            You haven't RSVP'd to any events yet.<br>Browse the calendar to find events to attend.
                        <?php endif; ?>
                    </p>
                    <a href="<?php echo BASE_URL; ?>user/events/" class="btn btn-primary">
                        <i class="fas fa-calendar-alt me-2"></i>Browse Events
                    </a>
                </div>
            <?php endif; ?>
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
                <a href="<?php echo BASE_URL; ?>user/events/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <span class="ios-menu-item-label">Event Calendar</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/create-event.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon success">
                            <i class="fas fa-plus"></i>
                        </div>
                        <span class="ios-menu-item-label">Create Event</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/browse-events.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon info">
                            <i class="fas fa-search"></i>
                        </div>
                        <span class="ios-menu-item-label">Browse All Events</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter Events</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>user/events/my-events.php" class="ios-menu-item <?php echo ($filterStatus === 'all' && $filterTime === 'all') ? 'active' : ''; ?>">
                    <div class="ios-menu-item-left">
                        <span class="ios-menu-item-label">All Events</span>
                    </div>
                    <?php if ($filterStatus === 'all' && $filterTime === 'all'): ?>
                    <i class="fas fa-check" style="color: var(--ios-blue);"></i>
                    <?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/my-events.php?time=upcoming" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <span class="ios-menu-item-label">Upcoming Only</span>
                    </div>
                    <?php if ($filterTime === 'upcoming'): ?>
                    <i class="fas fa-check" style="color: var(--ios-blue);"></i>
                    <?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/my-events.php?time=past" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <span class="ios-menu-item-label">Past Only</span>
                    </div>
                    <?php if ($filterTime === 'past'): ?>
                    <i class="fas fa-check" style="color: var(--ios-blue);"></i>
                    <?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/my-events.php?status=attending" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <span class="ios-menu-item-label">Attending</span>
                    </div>
                    <?php if ($filterStatus === 'attending'): ?>
                    <i class="fas fa-check" style="color: var(--ios-blue);"></i>
                    <?php endif; ?>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/my-events.php?status=maybe" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <span class="ios-menu-item-label">Maybe</span>
                    </div>
                    <?php if ($filterStatus === 'maybe'): ?>
                    <i class="fas fa-check" style="color: var(--ios-blue);"></i>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Your Stats Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Your Stats</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Events</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total_events']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Upcoming</span>
                    <span class="ios-menu-stat-value success"><?php echo number_format($stats['upcoming_attending']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Attended</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total_checked_in']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Missed</span>
                    <span class="ios-menu-stat-value danger"><?php echo number_format($stats['missed_events']); ?></span>
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
