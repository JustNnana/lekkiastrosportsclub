<?php
/**
 * Gate Wey Access Management System
 * Regular User Dashboard - iOS Styled
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/AccessCode.php';

// Set page title and enable charts
$pageTitle = 'User Dashboard';
$includeCharts = true;

// Check if user is logged in and is a regular user
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ' . BASE_URL);
    exit;
}

// Verify user status from database (real-time check)
require_once '../classes/User.php';
$user = new User();
if ($user->loadById($_SESSION['user_id'])) {
    if ($user->getStatus() !== 'active') {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '?error=account_inactive');
        exit;
    }
    $_SESSION['status'] = $user->getStatus();
} else {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '?error=user_not_found');
    exit;
}

// Get user and clan info
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

// Get clan details
$clanId = $currentUser->getClanId();
$clan = new Clan();
if (!$clan->loadById($clanId)) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

// Check if clan is active
$clanActive = $clan->isActive();

// Get database instance
$db = Database::getInstance();

// Get dashboard statistics
$totalCodes = $db->fetchOne(
    "SELECT COUNT(*) as count FROM access_codes WHERE created_by = ?",
    [$currentUser->getId()]
)['count'] ?? 0;

$activeCodes = $db->fetchOne(
    "SELECT COUNT(*) as count FROM access_codes
     WHERE created_by = ? AND status = 'active' AND valid_until > NOW()",
    [$currentUser->getId()]
)['count'] ?? 0;

$usedCodes = $db->fetchOne(
    "SELECT COUNT(*) as count FROM access_codes
     WHERE created_by = ? AND status = 'used'",
    [$currentUser->getId()]
)['count'] ?? 0;

$expiredCodes = $db->fetchOne(
    "SELECT COUNT(*) as count FROM access_codes
     WHERE created_by = ? AND (status = 'active' AND valid_until < NOW() OR status = 'expired')",
    [$currentUser->getId()]
)['count'] ?? 0;

// Get recent access codes
$recentCodes = $db->fetchAll(
    "SELECT * FROM access_codes
     WHERE created_by = ?
     ORDER BY created_at DESC
     LIMIT 5",
    [$currentUser->getId()]
);

// Get access logs for codes created by this user
$recentActivities = $db->fetchAll(
    "SELECT al.*, ac.code, ac.visitor_name, u.full_name as guard_name
     FROM access_logs al
     JOIN access_codes ac ON al.access_code_id = ac.id
     JOIN users u ON al.guard_id = u.id
     WHERE ac.created_by = ?
     ORDER BY al.timestamp DESC
     LIMIT 5",
    [$currentUser->getId()]
);

// Get monthly access code usage data for chart
$codeUsage = $db->fetchAll(
    "SELECT DATE_FORMAT(created_at, '%b') as month,
     COUNT(*) as count
     FROM access_codes
     WHERE created_by = ?
     AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY created_at ASC",
    [$currentUser->getId()]
);

// Get visitor purpose distribution for chart
$purposeDistribution = $db->fetchAll(
    "SELECT purpose, COUNT(*) as count
     FROM access_codes
     WHERE created_by = ?
     GROUP BY purpose
     ORDER BY count DESC",
    [$currentUser->getId()]
);

// Get upcoming events for user's clan
$upcomingEvents = $db->fetchAll(
    "SELECT e.*,
     (SELECT COUNT(*) FROM event_rsvps WHERE event_id = e.id AND status = 'attending') as attendee_count
     FROM events e
     WHERE e.clan_id = ? AND e.status != 'cancelled' AND e.start_datetime > NOW()
     ORDER BY e.start_datetime ASC
     LIMIT 2",
    [$clanId]
);

// Include header
include_once '../includes/header.php';
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
    }

    /* iOS Options Button (3-dot) */
    .ios-options-btn {
        display: none;
        align-items: center;
        justify-content: center;
        width: 36px; height: 36px;
        border-radius: 50%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .ios-options-btn:hover { background: var(--bg-tertiary); }

    /* Clan Inactive Alert */
    .ios-alert-banner {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        background: rgba(255, 69, 58, 0.08);
        border: 1px solid rgba(255, 69, 58, 0.2);
        border-radius: 14px;
        padding: 16px 18px;
        margin-bottom: 24px;
    }
    .ios-alert-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(255, 69, 58, 0.15);
        color: var(--ios-red);
        font-size: 18px;
        flex-shrink: 0;
    }
    .ios-alert-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--ios-red);
        margin: 0 0 4px 0;
    }
    .ios-alert-text {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
        line-height: 1.5;
    }

    /* Stats Overview Grid */
    .stats-overview-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .stat-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 20px;
        transition: all 0.2s ease;
    }
    .stat-card:hover {
        border-color: var(--ios-blue);
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    }
    .stat-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
    }
    .stat-icon {
        width: 42px; height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        flex-shrink: 0;
    }
    .stat-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .stat-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .stat-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .stat-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
    .stat-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .stat-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }
    .stat-label {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
        font-weight: 500;
    }
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        line-height: 1;
    }
    .stat-progress {
        margin-top: 12px;
    }
    .stat-progress-bar {
        height: 4px;
        background: var(--bg-tertiary);
        border-radius: 2px;
        overflow: hidden;
    }
    .stat-progress-fill {
        height: 100%;
        border-radius: 2px;
        transition: width 0.5s ease;
    }
    .stat-progress-fill.blue { background: var(--ios-blue); }
    .stat-progress-fill.green { background: var(--ios-green); }
    .stat-progress-fill.orange { background: var(--ios-orange); }
    .stat-progress-fill.red { background: var(--ios-red); }
    .stat-progress-label {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 6px;
    }

    /* Quick Actions Grid */
    .ios-quick-actions {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .ios-quick-action {
        display: flex;
        align-items: center;
        gap: 14px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 18px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .ios-quick-action:hover {
        border-color: var(--ios-blue);
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
        transform: translateY(-1px);
    }
    .ios-quick-action-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .ios-quick-action-text {
        flex: 1;
        min-width: 0;
    }
    .ios-quick-action-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 2px 0;
    }
    .ios-quick-action-desc {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
    }
    .ios-quick-action-arrow {
        color: var(--text-muted);
        font-size: 14px;
        opacity: 0.5;
        transition: all 0.2s ease;
    }
    .ios-quick-action:hover .ios-quick-action-arrow {
        opacity: 1;
        transform: translateX(3px);
    }

    /* Mobile Greeting */
    .ios-mobile-greeting {
        display: none;
        margin-bottom: 20px;
    }
    .ios-mobile-greeting h2 {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 2px 0;
    }
    .ios-mobile-greeting p {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Mobile Quick Actions */
    .ios-mobile-actions {
        display: none;
        margin-bottom: 24px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 16px;
    }
    .ios-mobile-actions-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 14px 0;
    }
    .ios-mobile-actions-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 12px;
    }
    .ios-mobile-action-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        padding: 4px;
    }
    .ios-mobile-action-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: white;
    }
    .ios-mobile-action-label {
        font-size: 11px;
        font-weight: 500;
        color: var(--text-primary);
        text-align: center;
        line-height: 1.3;
    }

    /* Upcoming Events */
    .ios-events-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        padding: 16px 20px;
    }
    .ios-event-card {
        display: flex;
        gap: 12px;
        padding: 14px;
        background: var(--bg-secondary);
        border-radius: 12px;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .ios-event-card:hover {
        background: var(--bg-tertiary);
        transform: translateY(-1px);
    }
    .ios-event-date-box {
        width: 46px;
        min-width: 46px;
        height: 46px;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .ios-event-date-box .month {
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        line-height: 1;
    }
    .ios-event-date-box .day {
        font-size: 18px;
        font-weight: 700;
        line-height: 1.1;
    }
    .ios-event-date-box.meeting { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-event-date-box.social { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-event-date-box.maintenance { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-event-date-box.other { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-event-info {
        flex: 1;
        min-width: 0;
    }
    .ios-event-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 3px 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .ios-event-meta {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0 0 2px 0;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .ios-event-meta i {
        font-size: 10px;
        color: var(--text-muted);
        width: 12px;
        text-align: center;
    }
    .ios-event-attendees {
        font-size: 11px;
        color: var(--text-muted);
        margin: 0;
    }

    /* iOS Section Card */
    .ios-section-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: 24px;
    }
    .ios-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border-color);
    }
    .ios-section-header-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .ios-section-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }
    .ios-section-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-section-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-section-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-section-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-section-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }
    .ios-section-title h5 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    .ios-section-title p {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 2px 0 0 0;
    }
    .ios-section-body { padding: 0; }

    /* iOS Link Button */
    .ios-link-btn {
        font-size: 14px;
        font-weight: 500;
        color: var(--ios-blue);
        text-decoration: none;
        transition: opacity 0.2s ease;
        white-space: nowrap;
    }
    .ios-link-btn:hover { opacity: 0.7; color: var(--ios-blue); }

    /* Charts Grid */
    .ios-charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }
    .ios-chart-body {
        padding: 20px;
    }
    .ios-chart-canvas {
        height: 260px !important;
    }
    .ios-chart-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 48px 20px;
        color: var(--text-muted);
        text-align: center;
    }
    .ios-chart-empty i {
        font-size: 40px;
        margin-bottom: 12px;
        opacity: 0.4;
    }
    .ios-chart-empty p {
        font-size: 13px;
        margin: 0;
        max-width: 260px;
    }

    /* Tables Grid */
    .ios-tables-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
        margin-bottom: 24px;
    }

    /* iOS List Items (card-based, no HTML table) */
    .ios-list-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.15s ease;
    }
    .ios-list-item:last-child { border-bottom: none; }
    .ios-list-item:hover { background: rgba(255, 255, 255, 0.02); }
    .ios-list-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .ios-list-dot.active { background: var(--ios-green); }
    .ios-list-dot.used { background: var(--ios-orange); }
    .ios-list-dot.expired { background: var(--ios-red); }
    .ios-list-dot.pending { background: var(--text-muted); }
    .ios-list-dot.revoked { background: var(--ios-red); }
    .ios-list-dot.entry { background: var(--ios-green); }
    .ios-list-dot.exit { background: var(--ios-red); }
    .ios-list-content {
        flex: 1;
        min-width: 0;
    }
    .ios-list-primary {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .ios-list-primary a {
        color: var(--text-primary);
        text-decoration: none;
    }
    .ios-list-primary a:hover { color: var(--ios-blue); }
    .ios-list-secondary {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 2px 0 0 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .ios-list-tertiary {
        font-size: 12px;
        color: var(--text-muted);
        margin: 2px 0 0 0;
    }
    .ios-list-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        flex-shrink: 0;
    }
    .ios-list-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }
    .ios-list-badge.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-list-badge.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-list-badge.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
    .ios-list-badge.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-list-badge.muted { background: var(--bg-tertiary); color: var(--text-muted); }
    .ios-list-date {
        font-size: 11px;
        color: var(--text-muted);
        margin-top: 4px;
    }

    /* Empty State */
    .ios-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted);
    }
    .ios-empty-state i {
        font-size: 36px;
        margin-bottom: 12px;
        opacity: 0.4;
    }
    .ios-empty-state p {
        font-size: 13px;
        margin: 0 0 12px 0;
    }
    .ios-empty-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 10px;
        background: var(--ios-blue);
        color: white;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .ios-empty-btn:hover { background: #0070E0; color: white; }

    /* iOS Bottom Sheet Menu */
    .ios-menu-backdrop {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
        z-index: 9998; opacity: 0; visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    .ios-menu-backdrop.active { opacity: 1; visibility: visible; }
    .ios-menu-modal {
        position: fixed; bottom: 0; left: 0; right: 0;
        background: var(--bg-primary);
        border-radius: 20px 20px 0 0;
        z-index: 9999;
        transform: translateY(100%);
        transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
        max-height: 85vh; overflow-y: auto;
        padding-bottom: env(safe-area-inset-bottom, 20px);
    }
    .ios-menu-modal.active { transform: translateY(0); }
    .ios-menu-handle {
        width: 36px; height: 5px;
        background: var(--text-muted); opacity: 0.3;
        border-radius: 3px;
        margin: 8px auto 0;
    }
    .ios-menu-header {
        padding: 16px 20px 12px;
        border-bottom: 1px solid var(--border-color);
    }
    .ios-menu-header h3 {
        font-size: 18px; font-weight: 700;
        color: var(--text-primary); margin: 0 0 4px 0;
    }
    .ios-menu-header p {
        font-size: 13px; color: var(--text-secondary); margin: 0;
    }
    .ios-menu-content { padding: 16px 20px; }
    .ios-menu-section { margin-bottom: 20px; }
    .ios-menu-section:last-child { margin-bottom: 0; }
    .ios-menu-section-title {
        font-size: 12px; font-weight: 600; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: 0.5px;
        margin: 0 0 10px 0;
    }
    .ios-menu-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
    }
    .ios-menu-item {
        display: flex; align-items: center; gap: 14px;
        padding: 14px 16px;
        text-decoration: none;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-color);
        transition: background 0.15s ease;
    }
    .ios-menu-item:last-child { border-bottom: none; }
    .ios-menu-item:hover { background: rgba(255, 255, 255, 0.03); }
    .ios-menu-item-icon {
        width: 32px; height: 32px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; flex-shrink: 0;
    }
    .ios-menu-item-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-menu-item-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-menu-item-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-menu-item-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-menu-item-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
    .ios-menu-item-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }
    .ios-menu-item-label { font-size: 15px; font-weight: 500; }
    .ios-menu-stat-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
    }
    .ios-menu-stat-row:last-child { border-bottom: none; }
    .ios-menu-stat-label { font-size: 14px; color: var(--text-secondary); }
    .ios-menu-stat-value { font-size: 14px; font-weight: 600; color: var(--text-primary); }

    /* Responsive */
    @media (max-width: 992px) {
        .ios-options-btn { display: flex; }
        .stats-overview-grid { grid-template-columns: repeat(2, 1fr); }
        .ios-quick-actions { grid-template-columns: 1fr; }
        .ios-charts-grid { grid-template-columns: 1fr; }
        .ios-tables-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .content-header { display: none !important; }
        .ios-mobile-greeting { display: block; }
        .ios-quick-actions { display: none; }
        .ios-mobile-actions { display: block; }

        .stats-overview-grid {
            display: flex;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            gap: 12px;
            padding-bottom: 4px;
            scrollbar-width: none;
        }
        .stats-overview-grid::-webkit-scrollbar { display: none; }
        .stat-card { min-width: 140px; flex: 0 0 auto; padding: 14px; }
        .stat-header { margin-bottom: 8px; gap: 10px; }
        .stat-icon { width: 32px; height: 32px; font-size: 14px; border-radius: 9px; }
        .stat-value { font-size: 20px; }
        .stat-label { font-size: 11px; }
        .stat-progress { display: none; }

        .ios-charts-grid { display: none; }

        .ios-events-grid { grid-template-columns: 1fr; padding: 14px 16px; gap: 10px; }
        .ios-event-card { padding: 12px; }
        .ios-event-date-box { width: 42px; min-width: 42px; height: 42px; border-radius: 9px; }
        .ios-event-date-box .day { font-size: 16px; }

        .ios-section-card { border-radius: 12px; }
        .ios-section-header { padding: 14px 16px; }
        .ios-section-icon { width: 36px; height: 36px; font-size: 14px; }
        .ios-section-title h5 { font-size: 15px; }
        .ios-list-item { padding: 12px 16px; }
        .ios-list-primary { font-size: 14px; }
        .ios-list-secondary { font-size: 12px; }
    }
    @media (max-width: 480px) {
        .ios-mobile-greeting h2 { font-size: 20px; }
        .ios-mobile-greeting p { font-size: 12px; }

        .stat-card { min-width: 130px; padding: 12px; }
        .stat-icon { width: 30px; height: 30px; font-size: 13px; border-radius: 8px; }
        .stat-value { font-size: 18px; }
        .stat-header { margin-bottom: 6px; gap: 8px; }

        .ios-mobile-actions { padding: 14px; border-radius: 12px; }
        .ios-mobile-actions-grid { gap: 10px; }
        .ios-mobile-action-icon { width: 48px; height: 48px; border-radius: 12px; font-size: 20px; }
        .ios-mobile-action-label { font-size: 10px; }

        .ios-events-grid { padding: 12px 14px; }
        .ios-event-card { padding: 10px; gap: 10px; }
        .ios-event-date-box { width: 40px; min-width: 40px; height: 40px; }
        .ios-event-date-box .month { font-size: 9px; }
        .ios-event-date-box .day { font-size: 15px; }
        .ios-event-title { font-size: 13px; }
        .ios-event-meta { font-size: 11px; }

        .ios-section-header { padding: 12px 14px; }
        .ios-section-icon { width: 34px; height: 34px; font-size: 13px; border-radius: 9px; }
        .ios-list-item { padding: 10px 14px; gap: 10px; }
        .ios-list-primary { font-size: 13px; }
        .ios-list-secondary { font-size: 11px; }
        .ios-list-badge { font-size: 10px; padding: 2px 6px; }
        .ios-list-date { font-size: 10px; }
    }
    @media (max-width: 390px) {
        .ios-mobile-actions { padding: 12px; }
        .ios-mobile-actions-grid { grid-template-columns: repeat(3, 1fr); }
        .ios-mobile-action-icon { width: 44px; height: 44px; font-size: 18px; }

        .stat-card { min-width: 120px; padding: 10px; }
        .stat-value { font-size: 17px; }
        .stat-header { margin-bottom: 4px; }

        .ios-list-item { padding: 10px 12px; gap: 8px; }
    }
</style>

<!-- iOS User Dashboard -->
<div class="main-content">
    <?php include_once '../includes/sidebar.php'; ?>
    <div class="content">
        <!-- Content Header (hidden on mobile) -->
        <div class="content-header">
            <div>
                <?php
                $fullName = $currentUser->getFullName();
                $firstName = explode(' ', $fullName)[0];
                ?>
                <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($firstName); ?></h1>
                <p>Here's your personal dashboard</p>
            </div>
            <button class="ios-options-btn" onclick="openMenu()">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Mobile Greeting (visible on mobile only) -->
        <div class="ios-mobile-greeting">
            <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($firstName); ?></h2>
            <p>Here's your personal dashboard</p>
        </div>

        <!-- Clan Inactive Alert -->
        <?php if (!$clanActive): ?>
            <div class="ios-alert-banner">
                <div class="ios-alert-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div>
                    <p class="ios-alert-title">Clan Payment Required</p>
                    <p class="ios-alert-text">Your clan's payment status is inactive. Some features may be limited. Please contact your clan administrator.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-overview-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue"><i class="fas fa-key"></i></div>
                    <span class="stat-label">Total Codes</span>
                </div>
                <p class="stat-value"><?php echo number_format($totalCodes); ?></p>
                <div class="stat-progress">
                    <div class="stat-progress-bar"><div class="stat-progress-fill blue" style="width: 100%;"></div></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <span class="stat-label">Active Codes</span>
                </div>
                <p class="stat-value"><?php echo number_format($activeCodes); ?></p>
                <div class="stat-progress">
                    <div class="stat-progress-bar"><div class="stat-progress-fill green" style="width: <?php echo $totalCodes > 0 ? ($activeCodes / $totalCodes) * 100 : 0; ?>%;"></div></div>
                    <div class="stat-progress-label">
                        <span>Active Rate</span>
                        <span><?php echo $totalCodes > 0 ? round(($activeCodes / $totalCodes) * 100) : 0; ?>%</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon orange"><i class="fas fa-user-check"></i></div>
                    <span class="stat-label">Used Codes</span>
                </div>
                <p class="stat-value"><?php echo number_format($usedCodes); ?></p>
                <div class="stat-progress">
                    <div class="stat-progress-bar"><div class="stat-progress-fill orange" style="width: <?php echo $totalCodes > 0 ? ($usedCodes / $totalCodes) * 100 : 0; ?>%;"></div></div>
                    <div class="stat-progress-label">
                        <span>Usage Rate</span>
                        <span><?php echo $totalCodes > 0 ? round(($usedCodes / $totalCodes) * 100) : 0; ?>%</span>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon red"><i class="fas fa-hourglass-end"></i></div>
                    <span class="stat-label">Expired Codes</span>
                </div>
                <p class="stat-value"><?php echo number_format($expiredCodes); ?></p>
                <div class="stat-progress">
                    <div class="stat-progress-bar"><div class="stat-progress-fill red" style="width: <?php echo $totalCodes > 0 ? ($expiredCodes / $totalCodes) * 100 : 0; ?>%;"></div></div>
                    <div class="stat-progress-label">
                        <span>Expired Rate</span>
                        <span><?php echo $totalCodes > 0 ? round(($expiredCodes / $totalCodes) * 100) : 0; ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions (Desktop) -->
        <div class="ios-quick-actions">
            <a href="<?php echo BASE_URL; ?>access-codes/generate.php" class="ios-quick-action">
                <div class="ios-quick-action-icon" style="background: rgba(10, 132, 255, 0.15); color: var(--ios-blue);">
                    <i class="fas fa-key"></i>
                </div>
                <div class="ios-quick-action-text">
                    <p class="ios-quick-action-title">Generate Access Code</p>
                    <p class="ios-quick-action-desc">Create new visitor access codes</p>
                </div>
                <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>access-codes/" class="ios-quick-action">
                <div class="ios-quick-action-icon" style="background: rgba(48, 209, 88, 0.15); color: var(--ios-green);">
                    <i class="fas fa-list"></i>
                </div>
                <div class="ios-quick-action-text">
                    <p class="ios-quick-action-title">Manage Access Codes</p>
                    <p class="ios-quick-action-desc">View and manage all your codes</p>
                </div>
                <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>reports/visitors.php" class="ios-quick-action">
                <div class="ios-quick-action-icon" style="background: rgba(191, 90, 242, 0.15); color: var(--ios-purple);">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="ios-quick-action-text">
                    <p class="ios-quick-action-title">View Reports</p>
                    <p class="ios-quick-action-desc">Access analytics and insights</p>
                </div>
                <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
            </a>
        </div>

        <!-- Mobile Quick Actions (visible on mobile only) -->
        <div class="ios-mobile-actions">
            <h2 class="ios-mobile-actions-title">Quick Actions</h2>
            <div class="ios-mobile-actions-grid">
                <a href="<?php echo BASE_URL; ?>user/my-dues.php" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-blue);"><i class="fas fa-money-bill-wave"></i></div>
                    <span class="ios-mobile-action-label">Dues</span>
                </a>
                <a href="<?php echo BASE_URL; ?>marketplace/" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-purple);"><i class="fas fa-store"></i></div>
                    <span class="ios-mobile-action-label">Marketplace</span>
                </a>
                <a href="<?php echo BASE_URL; ?>announcements/" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-teal);"><i class="fas fa-bullhorn"></i></div>
                    <span class="ios-mobile-action-label">Announcement</span>
                </a>
                <a href="<?php echo BASE_URL; ?>help/create-ticket.php" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-green);"><i class="fas fa-question-circle"></i></div>
                    <span class="ios-mobile-action-label">Help Center</span>
                </a>
                <a href="<?php echo BASE_URL; ?>user/payment-history.php" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-orange);"><i class="fas fa-history"></i></div>
                    <span class="ios-mobile-action-label">Dues History</span>
                </a>
                <a href="<?php echo BASE_URL; ?>user/events/" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-red);"><i class="fas fa-calendar-alt"></i></div>
                    <span class="ios-mobile-action-label">Events</span>
                </a>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-header-left">
                    <div class="ios-section-icon" style="background: rgba(255, 159, 10, 0.15); color: var(--ios-orange);"><i class="fas fa-calendar-alt"></i></div>
                    <div class="ios-section-title">
                        <h5>Upcoming Events</h5>
                        <p><?php echo count($upcomingEvents); ?> upcoming</p>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>user/events/browse-events.php" class="ios-link-btn">View All</a>
            </div>
            <?php if (empty($upcomingEvents)): ?>
                <div class="ios-empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No upcoming events.</p>
                    <a href="<?php echo BASE_URL; ?>user/events/create-event.php" class="ios-empty-btn">
                        <i class="fas fa-plus"></i> Create Event
                    </a>
                </div>
            <?php else: ?>
                <div class="ios-events-grid">
                    <?php foreach ($upcomingEvents as $event):
                        $eventType = $event['event_type'] ?? 'other';
                        $startDt = strtotime($event['start_datetime']);
                    ?>
                        <a href="<?php echo BASE_URL; ?>user/events/view-event.php?id=<?php echo encryptId($event['id']); ?>" class="ios-event-card">
                            <div class="ios-event-date-box <?php echo htmlspecialchars($eventType); ?>">
                                <span class="month"><?php echo date('M', $startDt); ?></span>
                                <span class="day"><?php echo date('j', $startDt); ?></span>
                            </div>
                            <div class="ios-event-info">
                                <p class="ios-event-title"><?php echo htmlspecialchars($event['title']); ?></p>
                                <p class="ios-event-meta"><i class="fas fa-clock"></i> <?php echo date('g:i A', $startDt); ?></p>
                                <?php if (!empty($event['location'])): ?>
                                    <p class="ios-event-meta"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['location']); ?></p>
                                <?php endif; ?>
                                <p class="ios-event-attendees"><i class="fas fa-users"></i> <?php echo $event['attendee_count'] ?? 0; ?> attending</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Charts (hidden on mobile) -->
        <div class="ios-charts-grid">
            <!-- Code Usage Chart -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-header-left">
                        <div class="ios-section-icon blue"><i class="fas fa-chart-bar"></i></div>
                        <div class="ios-section-title">
                            <h5>Access Code History</h5>
                            <p>Codes generated over the last 6 months</p>
                        </div>
                    </div>
                </div>
                <div class="ios-chart-body">
                    <?php if (empty($codeUsage)): ?>
                        <div class="ios-chart-empty">
                            <i class="fas fa-chart-line"></i>
                            <p>No data available. Start generating access codes to see your history.</p>
                        </div>
                    <?php else: ?>
                        <canvas id="codeUsageChart" class="ios-chart-canvas"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Purpose Distribution Chart -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-header-left">
                        <div class="ios-section-icon purple"><i class="fas fa-chart-pie"></i></div>
                        <div class="ios-section-title">
                            <h5>Purpose Distribution</h5>
                            <p>Breakdown by visit purpose</p>
                        </div>
                    </div>
                </div>
                <div class="ios-chart-body">
                    <?php if (empty($purposeDistribution)): ?>
                        <div class="ios-chart-empty">
                            <i class="fas fa-chart-pie"></i>
                            <p>No data available. Start generating access codes to see distribution.</p>
                        </div>
                    <?php else: ?>
                        <canvas id="purposeDistributionChart" class="ios-chart-canvas"></canvas>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity Tables -->
        <div class="ios-tables-grid">
            <!-- Recent Access Codes -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-header-left">
                        <div class="ios-section-icon green"><i class="fas fa-ticket-alt"></i></div>
                        <div class="ios-section-title">
                            <h5>Recent Access Codes</h5>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>access-codes/" class="ios-link-btn">View All</a>
                </div>
                <div class="ios-section-body">
                    <?php if (empty($recentCodes)): ?>
                        <div class="ios-empty-state">
                            <i class="fas fa-ticket-alt"></i>
                            <p>No access codes generated yet.</p>
                            <a href="<?php echo BASE_URL; ?>access-codes/generate.php" class="ios-empty-btn">
                                <i class="fas fa-plus"></i> Generate First Code
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentCodes as $code):
                            $now = time();
                            $validFrom = strtotime($code['valid_from']);
                            $validUntil = strtotime($code['valid_until']);

                            if ($code['status'] === 'used') {
                                $dotClass = 'used';
                                $badgeClass = 'orange';
                                $badgeLabel = 'Used';
                            } elseif ($code['status'] === 'revoked') {
                                $dotClass = 'revoked';
                                $badgeClass = 'red';
                                $badgeLabel = 'Revoked';
                            } elseif ($now < $validFrom) {
                                $dotClass = 'pending';
                                $badgeClass = 'muted';
                                $badgeLabel = 'Pending';
                            } elseif ($now > $validUntil) {
                                $dotClass = 'expired';
                                $badgeClass = 'red';
                                $badgeLabel = 'Expired';
                            } else {
                                $dotClass = 'active';
                                $badgeClass = 'green';
                                $badgeLabel = 'Active';
                            }
                        ?>
                            <div class="ios-list-item">
                                <span class="ios-list-dot <?php echo $dotClass; ?>"></span>
                                <div class="ios-list-content">
                                    <p class="ios-list-primary">
                                        <a href="<?php echo BASE_URL; ?>access-codes/view.php?id=<?php echo encryptId($code['id']); ?>">
                                            <?php echo htmlspecialchars($code['code']); ?>
                                        </a>
                                    </p>
                                    <p class="ios-list-secondary"><?php echo htmlspecialchars($code['visitor_name']); ?> &middot; <?php echo htmlspecialchars($code['purpose']); ?></p>
                                    <p class="ios-list-tertiary"><i class="fas fa-clock"></i> <?php echo date('M j, Y, g:i A', strtotime($code['valid_until'])); ?></p>
                                </div>
                                <div class="ios-list-meta">
                                    <span class="ios-list-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-header-left">
                        <div class="ios-section-icon orange"><i class="fas fa-clipboard-list"></i></div>
                        <div class="ios-section-title">
                            <h5>Recent Activities</h5>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>access-logs/" class="ios-link-btn">View All</a>
                </div>
                <div class="ios-section-body">
                    <?php if (empty($recentActivities)): ?>
                        <div class="ios-empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <p>No activities recorded yet.</p>
                            <p style="font-size: 12px; color: var(--text-muted); margin: 0;">Activities will appear here when your access codes are used.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentActivities as $activity): ?>
                            <div class="ios-list-item">
                                <span class="ios-list-dot <?php echo $activity['action'] === 'entry' ? 'entry' : 'exit'; ?>"></span>
                                <div class="ios-list-content">
                                    <p class="ios-list-primary"><?php echo htmlspecialchars($activity['code']); ?></p>
                                    <p class="ios-list-secondary"><?php echo htmlspecialchars($activity['visitor_name']); ?> &middot; Guard: <?php echo htmlspecialchars($activity['guard_name']); ?></p>
                                    <p class="ios-list-tertiary"><i class="fas fa-clock"></i> <?php echo date('M j, g:i A', strtotime($activity['timestamp'])); ?></p>
                                </div>
                                <div class="ios-list-meta">
                                    <span class="ios-list-badge <?php echo $activity['action'] === 'entry' ? 'green' : 'red'; ?>">
                                        <?php echo ucfirst($activity['action']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- iOS Bottom Sheet Menu -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3>Dashboard</h3>
        <p>Quick access and statistics</p>
    </div>
    <div class="ios-menu-content">
        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Quick Actions</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>access-codes/generate.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon blue"><i class="fas fa-key"></i></div>
                    <span class="ios-menu-item-label">Generate Access Code</span>
                </a>
                <a href="<?php echo BASE_URL; ?>access-codes/" class="ios-menu-item">
                    <div class="ios-menu-item-icon green"><i class="fas fa-list"></i></div>
                    <span class="ios-menu-item-label">Manage Access Codes</span>
                </a>
                <a href="<?php echo BASE_URL; ?>reports/visitors.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon purple"><i class="fas fa-chart-bar"></i></div>
                    <span class="ios-menu-item-label">View Reports</span>
                </a>
                <a href="<?php echo BASE_URL; ?>profile/" class="ios-menu-item">
                    <div class="ios-menu-item-icon teal"><i class="fas fa-user-cog"></i></div>
                    <span class="ios-menu-item-label">My Profile</span>
                </a>
            </div>
        </div>

        <!-- Statistics -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Statistics</p>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Codes</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($totalCodes); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active Codes</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-green);"><?php echo number_format($activeCodes); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Used Codes</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-orange);"><?php echo number_format($usedCodes); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Expired Codes</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-red);"><?php echo number_format($expiredCodes); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart & Menu Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('iOS User Dashboard loaded');

    // iOS Menu Functions
    window.openMenu = function() {
        document.getElementById('iosMenuBackdrop').classList.add('active');
        document.getElementById('iosMenuModal').classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    window.closeMenu = function() {
        document.getElementById('iosMenuBackdrop').classList.remove('active');
        document.getElementById('iosMenuModal').classList.remove('active');
        document.body.style.overflow = '';
    };

    // Swipe to close
    (function() {
        const modal = document.getElementById('iosMenuModal');
        let startY = 0, currentY = 0, isDragging = false;
        modal.addEventListener('touchstart', function(e) {
            if (modal.scrollTop <= 0) { startY = e.touches[0].clientY; isDragging = true; }
        }, { passive: true });
        modal.addEventListener('touchmove', function(e) {
            if (!isDragging) return;
            currentY = e.touches[0].clientY;
            const diff = currentY - startY;
            if (diff > 0) modal.style.transform = 'translateY(' + diff + 'px)';
        }, { passive: true });
        modal.addEventListener('touchend', function() {
            if (!isDragging) return;
            isDragging = false;
            if (currentY - startY > 100) closeMenu();
            modal.style.transform = '';
            currentY = 0; startY = 0;
        });
    })();

    // Charts
    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded');
        return;
    }

    function getChartColors() {
        const s = getComputedStyle(document.documentElement);
        return {
            blue: '#0A84FF',
            green: '#30D158',
            orange: '#FF9F0A',
            red: '#FF453A',
            purple: '#BF5AF2',
            teal: '#64D2FF',
            text: s.getPropertyValue('--text-primary').trim(),
            textSecondary: s.getPropertyValue('--text-secondary').trim(),
            border: s.getPropertyValue('--border-color').trim(),
            font: s.getPropertyValue('--font-family-base').trim() || '-apple-system, BlinkMacSystemFont, sans-serif'
        };
    }

    const c = getChartColors();
    let codeUsageChart = null;
    let purposeChart = null;

    // Code Usage Chart
    <?php if (!empty($codeUsage)): ?>
    const codeUsageCtx = document.getElementById('codeUsageChart');
    if (codeUsageCtx) {
        codeUsageChart = new Chart(codeUsageCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($codeUsage, 'month')); ?>,
                datasets: [{
                    label: 'Generated Codes',
                    data: <?php echo json_encode(array_column($codeUsage, 'count')); ?>,
                    backgroundColor: c.blue + '30',
                    borderColor: c.blue,
                    borderWidth: 2,
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 10,
                        padding: 12,
                        titleFont: { family: c.font, size: 14, weight: '600' },
                        bodyFont: { family: c.font, size: 13 }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: { color: c.textSecondary, font: { family: c.font, size: 12 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: c.border + '40', drawBorder: false },
                        border: { display: false },
                        ticks: { color: c.textSecondary, font: { family: c.font, size: 12 }, precision: 0 }
                    }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }
    <?php endif; ?>

    // Purpose Distribution Chart
    <?php if (!empty($purposeDistribution)): ?>
    const purposeCtx = document.getElementById('purposeDistributionChart');
    if (purposeCtx) {
        purposeChart = new Chart(purposeCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($purposeDistribution, 'purpose')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($purposeDistribution, 'count')); ?>,
                    backgroundColor: [c.blue, c.green, c.orange, c.teal, c.red, c.purple],
                    borderWidth: 0,
                    hoverBorderWidth: 2,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: c.text,
                            font: { family: c.font, size: 12 },
                            padding: 15,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        cornerRadius: 10,
                        padding: 12,
                        titleFont: { family: c.font, size: 14, weight: '600' },
                        bodyFont: { family: c.font, size: 13 },
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    // Update charts when theme changes
    document.addEventListener('themeChanged', function() {
        const nc = getChartColors();
        if (codeUsageChart) {
            codeUsageChart.data.datasets[0].backgroundColor = nc.blue + '30';
            codeUsageChart.data.datasets[0].borderColor = nc.blue;
            codeUsageChart.options.scales.x.ticks.color = nc.textSecondary;
            codeUsageChart.options.scales.y.ticks.color = nc.textSecondary;
            codeUsageChart.options.scales.y.grid.color = nc.border + '40';
            codeUsageChart.update('none');
        }
        if (purposeChart) {
            purposeChart.options.plugins.legend.labels.color = nc.text;
            purposeChart.update('none');
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
