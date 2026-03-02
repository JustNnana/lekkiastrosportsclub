<?php
/**
 * Gate Wey Access Management System
 * Browse Events - iOS App Style Design
 * File: user/events/browse-events.php
 */

// Include necessary files
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../classes/User.php';
require_once '../../classes/Clan.php';
require_once '../../classes/Event.php';
require_once '../../classes/EventRsvp.php';

// Set page title
$pageTitle = 'Browse Events';
$includeCharts = false;

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

$clanId = $currentUser->getClanId();
$userId = $_SESSION['user_id'];

// Get database instance
$db = Database::getInstance();

// Load clan details
$clan = new Clan();
$clan->loadById($clanId);

// Initialize classes
$event = new Event();
$eventRsvp = new EventRsvp();

// Handle success/error messages
$success = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Filters
$filters = [
    'status' => $_GET['status'] ?? 'all',
    'type' => $_GET['type'] ?? '',
    'search' => $_GET['search'] ?? '',
    'mine' => isset($_GET['mine']) && $_GET['mine'] === '1',
];

// Get event statistics (all visible events including cancelled)
$eventStats = $db->fetchOne(
    "SELECT
        COUNT(CASE WHEN visibility != 'admins_only' AND (status != 'pending' OR created_by = ?) THEN 1 END) as total_visible,
        COUNT(CASE WHEN status = 'upcoming' AND visibility != 'admins_only' THEN 1 END) as upcoming_count,
        COUNT(CASE WHEN status = 'ongoing' AND visibility != 'admins_only' THEN 1 END) as ongoing_count,
        COUNT(CASE WHEN status = 'completed' AND visibility != 'admins_only' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status = 'cancelled' AND visibility != 'admins_only' THEN 1 END) as cancelled_count,
        COUNT(CASE WHEN status = 'pending' AND created_by = ? THEN 1 END) as my_pending_count
     FROM events
     WHERE clan_id = ?",
    [$userId, $userId, $clanId]
);
$eventStats = $eventStats ?: [
    'total_visible' => 0,
    'upcoming_count' => 0,
    'ongoing_count' => 0,
    'completed_count' => 0,
    'cancelled_count' => 0,
    'my_pending_count' => 0,
];

// Build query
// Default: show all non-admins_only events (upcoming/ongoing/completed/cancelled) + own pending
$whereConditions = ["e.clan_id = ?", "e.visibility != 'admins_only'"];
$params = [$clanId];

if ($filters['mine']) {
    // Show only events created by this user
    $whereConditions[] = "e.created_by = ?";
    $params[] = $userId;
} else {
    // Show all statuses except pending (pending only if own)
    $whereConditions[] = "(e.status != 'pending' OR e.created_by = ?)";
    $params[] = $userId;
}

if ($filters['status'] !== 'all' && !empty($filters['status']) && !$filters['mine']) {
    // Override status filter: remove last condition and replace
    array_pop($whereConditions);
    array_pop($params);
    if ($filters['status'] === 'pending') {
        $whereConditions[] = "(e.status = 'pending' AND e.created_by = ?)";
        $params[] = $userId;
    } else {
        $whereConditions[] = "e.status = ?";
        $params[] = $filters['status'];
    }
}

if (!empty($filters['type'])) {
    $whereConditions[] = "e.event_type = ?";
    $params[] = $filters['type'];
}

if (!empty($filters['search'])) {
    $whereConditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

$allEvents = $db->fetchAll(
    "SELECT e.*,
            (SELECT COUNT(*) FROM event_rsvps er WHERE er.event_id = e.id AND er.status = 'attending') as attending_count,
            (SELECT er2.status FROM event_rsvps er2 WHERE er2.event_id = e.id AND er2.user_id = ?) as user_rsvp_status
     FROM events e
     $whereClause
     ORDER BY
        CASE e.status WHEN 'ongoing' THEN 0 WHEN 'upcoming' THEN 1 WHEN 'pending' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END,
        e.start_datetime ASC
     LIMIT 200",
    array_merge([$userId], $params)
);

// Include header
include_once '../../includes/header.php';
?>

<style>
    :root {
        --ios-red: #FF453A;
        --ios-orange: #FF9F0A;
        --ios-yellow: #FFD60A;
        --ios-green: #30D158;
        --ios-teal: #64D2FF;
        --ios-blue: #0A84FF;
        --ios-purple: #BF5AF2;
        --event-meeting: #3B82F6;
        --event-social: #10B981;
        --event-maintenance: #F59E0B;
        --event-other: #8B5CF6;
    }

    /* Stats Overview Grid */
    .stats-overview-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .stat-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 14px; padding: 20px; transition: all 0.2s ease; }
    .stat-card:hover { border-color: var(--ios-blue); box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
    .stat-header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
    .stat-icon { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0; }
    .stat-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .stat-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .stat-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .stat-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .stat-label { font-size: 13px; color: var(--text-secondary); margin: 0; font-weight: 500; }
    .stat-value { font-size: 28px; font-weight: 700; color: var(--text-primary); margin: 0; line-height: 1; }

    /* iOS Section Card */
    .ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 24px; }
    .ios-section-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border-color); }
    .ios-section-header-left { display: flex; align-items: center; gap: 12px; }
    .ios-section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
    .ios-section-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-section-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-section-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-section-title h5 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0; }
    .ios-section-title p { font-size: 12px; color: var(--text-secondary); margin: 2px 0 0 0; }
    .ios-section-body { padding: 0; }

    /* 3-Dot Menu Button */
    .ios-options-btn { display: none; width: 36px; height: 36px; border-radius: 50%; background: var(--bg-secondary); border: 1px solid var(--border-color); align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; flex-shrink: 0; }
    .ios-options-btn:hover { background: var(--bg-tertiary); }
    .ios-options-btn i { color: var(--text-primary); font-size: 16px; }

    /* iOS Filter Pills */
    .ios-filter-pills { display: flex; gap: 8px; padding: 12px 20px; overflow-x: auto; -webkit-overflow-scrolling: touch; border-bottom: 1px solid var(--border-color); scrollbar-width: none; }
    .ios-filter-pills::-webkit-scrollbar { display: none; }
    .ios-filter-pill { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; text-decoration: none; white-space: nowrap; transition: all 0.2s ease; border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary); }
    .ios-filter-pill:hover { background: var(--border-color); color: var(--text-primary); }
    .ios-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
    .ios-filter-pill .count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.2); }
    .ios-filter-pill:not(.active) .count { background: var(--border-color); }

    /* iOS Search Box */
    .ios-search-box { padding: 12px 20px; display: flex; gap: 8px; flex-wrap: wrap; border-bottom: 1px solid var(--border-color); }
    .ios-search-input-wrapper { position: relative; display: flex; align-items: center; flex: 1; min-width: 200px; }
    .ios-search-icon { position: absolute; left: 12px; color: var(--text-muted); font-size: 14px; pointer-events: none; }
    .ios-search-input { width: 100%; padding: 10px 14px 10px 36px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); color: var(--text-primary); font-size: 15px; transition: border-color 0.2s ease, box-shadow 0.2s ease; }
    .ios-search-input:focus { outline: none; border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10,132,255,0.1); }
    .ios-search-input::placeholder { color: var(--text-muted); }
    .ios-filter-select { padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); color: var(--text-primary); font-size: 14px; cursor: pointer; min-width: 130px; }
    .ios-filter-select:focus { outline: none; border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10,132,255,0.1); }
    .ios-clear-btn { padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 10px; background: var(--bg-primary); color: var(--text-secondary); font-size: 13px; font-weight: 500; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; transition: all 0.15s ease; }
    .ios-clear-btn:hover { background: rgba(255,69,58,0.1); border-color: var(--ios-red); color: var(--ios-red); }

    /* iOS List Items */
    .ios-list-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--border-color); text-decoration: none; transition: background 0.15s ease; color: var(--text-primary); }
    .ios-list-item:last-child { border-bottom: none; }
    .ios-list-item:hover { background: rgba(255,255,255,0.02); }
    .ios-list-avatar { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; flex-shrink: 0; }
    .ios-list-avatar.meeting { background: var(--event-meeting); }
    .ios-list-avatar.social { background: var(--event-social); }
    .ios-list-avatar.maintenance { background: var(--event-maintenance); }
    .ios-list-avatar.other { background: var(--event-other); }
    .ios-list-content { flex: 1; min-width: 0; }
    .ios-list-primary { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ios-list-secondary { font-size: 13px; color: var(--text-secondary); margin: 2px 0 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ios-list-tertiary { font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .ios-list-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
    .ios-list-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .ios-list-badge.green { background: rgba(48,209,88,0.15); color: var(--ios-green); }
    .ios-list-badge.orange { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
    .ios-list-badge.red { background: rgba(255,69,58,0.15); color: var(--ios-red); }
    .ios-list-badge.blue { background: rgba(10,132,255,0.15); color: var(--ios-blue); }
    .ios-list-badge.purple { background: rgba(191,90,242,0.15); color: var(--ios-purple); }
    .ios-list-badge.muted { background: var(--bg-tertiary); color: var(--text-muted); }
    .ios-list-badge.yellow { background: rgba(255,214,10,0.15); color: #B8860B; }
    .ios-list-rsvp { font-size: 12px; color: var(--text-secondary); display: flex; align-items: center; gap: 4px; }
    .ios-list-rsvp i { font-size: 11px; }

    /* Event Type Badge */
    .ios-type-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .ios-type-badge.meeting { background: rgba(59,130,246,0.15); color: var(--event-meeting); }
    .ios-type-badge.social { background: rgba(16,185,129,0.15); color: var(--event-social); }
    .ios-type-badge.maintenance { background: rgba(245,158,11,0.15); color: var(--event-maintenance); }
    .ios-type-badge.other { background: rgba(139,92,246,0.15); color: var(--event-other); }

    /* My Event Indicator */
    .ios-my-badge { background: rgba(100,210,255,0.15); color: var(--ios-teal); }

    /* RSVP Status Indicator */
    .ios-rsvp-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .ios-rsvp-dot.attending { background: var(--ios-green); }
    .ios-rsvp-dot.maybe { background: var(--ios-orange); }
    .ios-rsvp-dot.not_attending { background: var(--ios-red); }

    /* Empty State */
    .ios-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 20px; text-align: center; }
    .ios-empty-state i { font-size: 48px; margin-bottom: 16px; color: var(--text-muted); opacity: 0.4; }
    .ios-empty-state h3 { font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px 0; }
    .ios-empty-state p { font-size: 14px; color: var(--text-secondary); margin: 0 0 20px 0; }
    .ios-empty-btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 10px; background: var(--ios-blue); color: white; font-size: 14px; font-weight: 600; text-decoration: none; transition: all 0.2s ease; }
    .ios-empty-btn:hover { background: #0070E0; color: white; }

    /* Alert messages */
    .ios-alert { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-size: 14px; display: flex; align-items: center; gap: 10px; animation: fadeIn 0.3s ease; }
    .ios-alert.success { background: rgba(48,209,88,0.12); color: var(--ios-green); border: 1px solid rgba(48,209,88,0.2); }
    .ios-alert.error { background: rgba(255,69,58,0.12); color: var(--ios-red); border: 1px solid rgba(255,69,58,0.2); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }

    /* iOS Bottom Sheet Menu */
    .ios-menu-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
    .ios-menu-backdrop.active { opacity: 1; visibility: visible; }
    .ios-menu-modal { position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary); border-radius: 20px 20px 0 0; z-index: 9999; transform: translateY(100%); transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1); max-height: 85vh; overflow-y: auto; padding-bottom: env(safe-area-inset-bottom, 20px); }
    .ios-menu-modal.active { transform: translateY(0); }
    .ios-menu-handle { width: 36px; height: 5px; background: var(--text-muted); opacity: 0.3; border-radius: 3px; margin: 8px auto 0; }
    .ios-menu-header { padding: 16px 20px 12px; border-bottom: 1px solid var(--border-color); }
    .ios-menu-header h3 { font-size: 18px; font-weight: 700; color: var(--text-primary); margin: 0 0 4px 0; }
    .ios-menu-header p { font-size: 13px; color: var(--text-secondary); margin: 0; }
    .ios-menu-content { padding: 16px 20px; }
    .ios-menu-section { margin-bottom: 20px; }
    .ios-menu-section:last-child { margin-bottom: 0; }
    .ios-menu-section-title { font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 10px 0; }
    .ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
    .ios-menu-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color); transition: background 0.15s ease; cursor: pointer; }
    .ios-menu-item:last-child { border-bottom: none; }
    .ios-menu-item:hover { background: rgba(255,255,255,0.03); }
    .ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
    .ios-menu-item-icon.blue { background: rgba(10,132,255,0.15); color: var(--ios-blue); }
    .ios-menu-item-icon.green { background: rgba(48,209,88,0.15); color: var(--ios-green); }
    .ios-menu-item-icon.orange { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
    .ios-menu-item-icon.purple { background: rgba(191,90,242,0.15); color: var(--ios-purple); }
    .ios-menu-item-icon.teal { background: rgba(100,210,255,0.15); color: var(--ios-teal); }
    .ios-menu-item-label { font-size: 15px; font-weight: 500; }
    .ios-menu-stat-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
    .ios-menu-stat-row:last-child { border-bottom: none; }
    .ios-menu-stat-label { font-size: 14px; color: var(--text-secondary); }
    .ios-menu-stat-value { font-size: 14px; font-weight: 600; color: var(--text-primary); }

    /* Responsive */
    @media (max-width: 992px) {
        .ios-options-btn { display: flex; }
        .stats-overview-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .content-header { display: none !important; }
        .stats-overview-grid { display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch; gap: 12px; padding-bottom: 4px; scrollbar-width: none; }
        .stats-overview-grid::-webkit-scrollbar { display: none; }
        .stat-card { min-width: 140px; flex: 0 0 auto; padding: 14px; }
        .stat-header { margin-bottom: 8px; gap: 10px; }
        .stat-icon { width: 32px; height: 32px; font-size: 14px; border-radius: 9px; }
        .stat-value { font-size: 20px; }
        .stat-label { font-size: 11px; }
        .ios-section-card { border-radius: 12px; }
        .ios-section-header { padding: 14px 16px; }
        .ios-section-icon { width: 36px; height: 36px; font-size: 14px; }
        .ios-section-title h5 { font-size: 15px; }
        .ios-search-box { flex-direction: column; gap: 10px; padding: 12px 16px; }
        .ios-search-input-wrapper { min-width: 100%; }
        .ios-search-input { height: 44px; font-size: 15px; }
        .ios-filter-select { width: 100%; height: 44px; }
        .ios-clear-btn { width: 100%; height: 44px; justify-content: center; }
        .ios-list-item { padding: 12px 16px; }
        .ios-list-primary { font-size: 14px; }
        .ios-list-secondary { font-size: 12px; }
        .ios-list-avatar { width: 36px; height: 36px; font-size: 15px; border-radius: 10px; }
    }
    @media (max-width: 480px) {
        .stat-card { min-width: 130px; padding: 12px; }
        .stat-icon { width: 30px; height: 30px; font-size: 13px; border-radius: 8px; }
        .stat-value { font-size: 18px; }
        .ios-list-item { padding: 10px 14px; gap: 10px; }
        .ios-list-badge { font-size: 10px; padding: 2px 6px; }
    }
</style>

<!-- iOS Browse Events Page -->
<div class="main-content">
    <?php include_once '../../includes/sidebar.php'; ?>
    <div class="content">
        <!-- Content Header (hidden on mobile) -->
        <div class="content-header" style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h1>Browse Events</h1>
                <p>Explore all upcoming and past events for <?php echo htmlspecialchars($clan->getName()); ?></p>
            </div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <?php if ($currentUser->getRole() !== 'guard'): ?>
                <a href="<?php echo BASE_URL; ?>user/events/create-event.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle me-2"></i>Create Event
                </a>
                <button class="ios-options-btn" onclick="openMenu()">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($success): ?>
            <div class="ios-alert success" id="alertMsg">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="ios-alert error" id="alertMsg">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-overview-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
                    <span class="stat-label">Total Events</span>
                </div>
                <p class="stat-value"><?php echo number_format($eventStats['total_visible']); ?></p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon green"><i class="fas fa-clock"></i></div>
                    <span class="stat-label">Upcoming</span>
                </div>
                <p class="stat-value"><?php echo number_format($eventStats['upcoming_count']); ?></p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon orange"><i class="fas fa-play-circle"></i></div>
                    <span class="stat-label">Ongoing</span>
                </div>
                <p class="stat-value"><?php echo number_format($eventStats['ongoing_count']); ?></p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon purple"><i class="fas fa-check-circle"></i></div>
                    <span class="stat-label">Completed</span>
                </div>
                <p class="stat-value"><?php echo number_format($eventStats['completed_count']); ?></p>
            </div>
        </div>

        <!-- Events List -->
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-header-left">
                    <div class="ios-section-icon blue"><i class="fas fa-calendar-alt"></i></div>
                    <div class="ios-section-title">
                        <h5>Events</h5>
                        <p><?php echo count($allEvents); ?> event<?php echo count($allEvents) !== 1 ? 's' : ''; ?> found</p>
                    </div>
                </div>
                <?php if ($currentUser->getRole() !== 'guard'): ?>
                <button class="ios-options-btn" onclick="openMenu()">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                <?php endif; ?>
            </div>

            <!-- Filter Pills -->
            <div class="ios-filter-pills">
                <?php
                $baseParams = '';
                if (!empty($filters['type'])) $baseParams .= '&type=' . urlencode($filters['type']);
                if (!empty($filters['search'])) $baseParams .= '&search=' . urlencode($filters['search']);
                ?>
                <a href="?status=all<?php echo $baseParams; ?>" class="ios-filter-pill <?php echo ($filters['status'] === 'all' && !$filters['mine']) ? 'active' : ''; ?>">
                    All <span class="count"><?php echo number_format($eventStats['total_visible']); ?></span>
                </a>
                <a href="?status=upcoming<?php echo $baseParams; ?>" class="ios-filter-pill <?php echo $filters['status'] === 'upcoming' ? 'active' : ''; ?>">
                    Upcoming <span class="count"><?php echo number_format($eventStats['upcoming_count']); ?></span>
                </a>
                <a href="?status=ongoing<?php echo $baseParams; ?>" class="ios-filter-pill <?php echo $filters['status'] === 'ongoing' ? 'active' : ''; ?>">
                    Ongoing <span class="count"><?php echo number_format($eventStats['ongoing_count']); ?></span>
                </a>
                <a href="?status=completed<?php echo $baseParams; ?>" class="ios-filter-pill <?php echo $filters['status'] === 'completed' ? 'active' : ''; ?>">
                    Completed <span class="count"><?php echo number_format($eventStats['completed_count']); ?></span>
                </a>
                <a href="?status=cancelled<?php echo $baseParams; ?>" class="ios-filter-pill <?php echo $filters['status'] === 'cancelled' ? 'active' : ''; ?>">
                    Cancelled <span class="count"><?php echo number_format($eventStats['cancelled_count']); ?></span>
                </a>
                <a href="?mine=1<?php echo $baseParams; ?>" class="ios-filter-pill <?php echo $filters['mine'] ? 'active' : ''; ?>">
                    <i class="fas fa-star" style="font-size: 10px;"></i> My Events
                </a>
                <?php if ($eventStats['my_pending_count'] > 0): ?>
                <a href="?status=pending<?php echo $baseParams; ?>" class="ios-filter-pill <?php echo $filters['status'] === 'pending' ? 'active' : ''; ?>">
                    Pending <span class="count"><?php echo number_format($eventStats['my_pending_count']); ?></span>
                </a>
                <?php endif; ?>
            </div>

            <!-- Search Box -->
            <form action="" method="get" class="ios-search-box">
                <div class="ios-search-input-wrapper">
                    <i class="fas fa-search ios-search-icon"></i>
                    <input type="text" class="ios-search-input" name="search" placeholder="Search events..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                </div>
                <select name="type" class="ios-filter-select" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="meeting" <?php echo $filters['type'] === 'meeting' ? 'selected' : ''; ?>>Meeting</option>
                    <option value="social" <?php echo $filters['type'] === 'social' ? 'selected' : ''; ?>>Social</option>
                    <option value="maintenance" <?php echo $filters['type'] === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="other" <?php echo $filters['type'] === 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
                <?php if ($filters['status'] !== 'all'): ?>
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filters['status']); ?>">
                <?php endif; ?>
                <?php if ($filters['mine']): ?>
                    <input type="hidden" name="mine" value="1">
                <?php endif; ?>
                <?php if ($filters['status'] !== 'all' || !empty($filters['type']) || !empty($filters['search']) || $filters['mine']): ?>
                    <a href="browse-events.php" class="ios-clear-btn">
                        <i class="fas fa-times-circle"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <!-- List Items -->
            <div class="ios-section-body">
                <?php if (empty($allEvents)): ?>
                    <div class="ios-empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>No events found</h3>
                        <p>
                            <?php if ($filters['status'] !== 'all' || !empty($filters['search']) || !empty($filters['type']) || $filters['mine']): ?>
                                Try adjusting your filters or <a href="browse-events.php">view all events</a>.
                            <?php else: ?>
                                No events are available right now.
                            <?php endif; ?>
                        </p>
                        <a href="<?php echo BASE_URL; ?>user/events/create-event.php" class="ios-empty-btn">
                            <i class="fas fa-plus"></i> Create an Event
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($allEvents as $evt):
                        $typeClass = $evt['event_type'];
                        $displayType = $evt['event_type'] === 'other' && !empty($evt['custom_event_type'])
                            ? $evt['custom_event_type']
                            : ucfirst($evt['event_type']);

                        $typeIcon = [
                            'meeting' => 'fa-users',
                            'social' => 'fa-glass-cheers',
                            'maintenance' => 'fa-tools',
                            'other' => 'fa-calendar-day',
                        ][$evt['event_type']] ?? 'fa-calendar';

                        // Status badge config
                        $statusConfig = [
                            'upcoming' => ['class' => 'blue', 'label' => 'Upcoming'],
                            'ongoing' => ['class' => 'green', 'label' => 'Live Now'],
                            'completed' => ['class' => 'muted', 'label' => 'Completed'],
                            'cancelled' => ['class' => 'red', 'label' => 'Cancelled'],
                            'pending' => ['class' => 'yellow', 'label' => 'Pending Approval'],
                        ];
                        $statusInfo = $statusConfig[$evt['status']] ?? ['class' => 'muted', 'label' => ucfirst($evt['status'])];

                        $isOwn = ($evt['created_by'] == $userId);
                        $userRsvp = $evt['user_rsvp_status'];

                        // Date formatting
                        $startDt = new DateTime($evt['start_datetime']);
                        $now = new DateTime();
                        $isToday = $startDt->format('Y-m-d') === $now->format('Y-m-d');
                        $datePart = $isToday ? 'Today' : $startDt->format('M j, Y');
                        $timePart = $startDt->format('g:i A');
                    ?>
                        <a href="<?php echo BASE_URL; ?>user/events/view-event.php?id=<?php echo encryptId($evt['id']); ?>" class="ios-list-item">
                            <div class="ios-list-avatar <?php echo htmlspecialchars($typeClass); ?>">
                                <i class="fas <?php echo $typeIcon; ?>"></i>
                            </div>

                            <div class="ios-list-content">
                                <p class="ios-list-primary"><?php echo htmlspecialchars($evt['title']); ?></p>
                                <p class="ios-list-secondary">
                                    <?php if (!empty($evt['location'])): ?>
                                        <i class="fas fa-map-marker-alt" style="font-size: 11px;"></i>
                                        <?php echo htmlspecialchars($evt['location']); ?>
                                    <?php else: ?>
                                        <i class="fas fa-calendar" style="font-size: 11px;"></i> <?php echo $datePart; ?>
                                        &nbsp;<i class="fas fa-clock" style="font-size: 11px;"></i> <?php echo $timePart; ?>
                                    <?php endif; ?>
                                </p>
                                <div class="ios-list-tertiary">
                                    <span class="ios-type-badge <?php echo htmlspecialchars($typeClass); ?>">
                                        <?php echo htmlspecialchars($displayType); ?>
                                    </span>
                                    <?php if (!empty($evt['location'])): ?>
                                        <span><i class="fas fa-calendar" style="font-size: 10px;"></i> <?php echo $datePart; ?> &nbsp;<i class="fas fa-clock" style="font-size: 10px;"></i> <?php echo $timePart; ?></span>
                                    <?php endif; ?>
                                    <?php if ($isOwn): ?>
                                        <span class="ios-list-badge ios-my-badge" style="padding: 2px 6px;">
                                            <i class="fas fa-user" style="font-size: 9px;"></i> Mine
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="ios-list-meta">
                                <span class="ios-list-badge <?php echo $statusInfo['class']; ?>">
                                    <?php if ($evt['status'] === 'ongoing'): ?>
                                        <i class="fas fa-circle" style="font-size: 7px;"></i>
                                    <?php endif; ?>
                                    <?php echo $statusInfo['label']; ?>
                                </span>
                                <?php if ($evt['attending_count'] > 0): ?>
                                    <span class="ios-list-rsvp">
                                        <i class="fas fa-user-check"></i>
                                        <?php echo number_format($evt['attending_count']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($userRsvp): ?>
                                    <span class="ios-rsvp-dot <?php echo htmlspecialchars($userRsvp); ?>" title="Your RSVP: <?php echo ucfirst(str_replace('_', ' ', $userRsvp)); ?>"></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- iOS Bottom Sheet Menu -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3>Browse Events</h3>
        <p><?php echo htmlspecialchars($clan->getName()); ?></p>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>user/events/" class="ios-menu-item">
                    <div class="ios-menu-item-icon blue"><i class="fas fa-calendar"></i></div>
                    <span class="ios-menu-item-label">Events Dashboard</span>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/my-events.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon green"><i class="fas fa-star"></i></div>
                    <span class="ios-menu-item-label">My Events</span>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/create-event.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon orange"><i class="fas fa-plus-circle"></i></div>
                    <span class="ios-menu-item-label">Create Event</span>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-icon purple"><i class="fas fa-tachometer-alt"></i></div>
                    <span class="ios-menu-item-label">Dashboard</span>
                </a>
            </div>
        </div>

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Filter by Type</p>
            <div class="ios-menu-card">
                <a href="?type=meeting&status=<?php echo urlencode($filters['status']); ?>" class="ios-menu-item" onclick="closeMenu()">
                    <div class="ios-menu-item-icon blue"><i class="fas fa-users"></i></div>
                    <span class="ios-menu-item-label">Meetings</span>
                </a>
                <a href="?type=social&status=<?php echo urlencode($filters['status']); ?>" class="ios-menu-item" onclick="closeMenu()">
                    <div class="ios-menu-item-icon green"><i class="fas fa-glass-cheers"></i></div>
                    <span class="ios-menu-item-label">Social Events</span>
                </a>
                <a href="?type=maintenance&status=<?php echo urlencode($filters['status']); ?>" class="ios-menu-item" onclick="closeMenu()">
                    <div class="ios-menu-item-icon orange"><i class="fas fa-tools"></i></div>
                    <span class="ios-menu-item-label">Maintenance</span>
                </a>
                <a href="?type=other&status=<?php echo urlencode($filters['status']); ?>" class="ios-menu-item" onclick="closeMenu()">
                    <div class="ios-menu-item-icon teal"><i class="fas fa-calendar-day"></i></div>
                    <span class="ios-menu-item-label">Other Events</span>
                </a>
            </div>
        </div>

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Statistics</p>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Events</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($eventStats['total_visible']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Upcoming</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($eventStats['upcoming_count']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Ongoing Now</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($eventStats['ongoing_count']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Completed</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($eventStats['completed_count']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Cancelled</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($eventStats['cancelled_count']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts
    const alertMsg = document.getElementById('alertMsg');
    if (alertMsg) {
        setTimeout(() => { alertMsg.style.opacity = '0'; alertMsg.style.transition = 'opacity 0.5s'; setTimeout(() => alertMsg.remove(), 500); }, 5000);
    }
});

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

// Swipe to close
(function() {
    let startY = 0;
    const modal = document.getElementById('iosMenuModal');
    modal.addEventListener('touchstart', e => { startY = e.touches[0].clientY; }, { passive: true });
    modal.addEventListener('touchend', e => { if (e.changedTouches[0].clientY - startY > 80) closeMenu(); }, { passive: true });
})();
</script>

<?php include_once '../../includes/footer.php'; ?>
