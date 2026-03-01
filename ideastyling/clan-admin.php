<?php
/**
 * Gate Wey Access Management System
 * Clan Admin Dashboard - iOS Styled
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/ClanDuesPayment.php';

// Set page title and enable charts
$pageTitle = 'Clan Admin Dashboard';
$includeCharts = true;

// Check if user is logged in and is a clan admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'clan_admin') {
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

// Get clan details
$clanId = $currentUser->getClanId();
$clan = new Clan();
if (!$clan->loadById($clanId)) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Get database instance
$db = Database::getInstance();

// Get basic clan statistics
$totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE clan_id = ? AND status = 'active'", [$clanId])['count'] ?? 0;
$totalCodes = $db->fetchOne("SELECT COUNT(*) as count FROM access_codes WHERE clan_id = ?", [$clanId])['count'] ?? 0;
$activeCodes = $db->fetchOne("SELECT COUNT(*) as count FROM access_codes WHERE clan_id = ? AND valid_until > NOW() AND status = 'active'", [$clanId])['count'] ?? 0;

// Get clan dues payment statistics
$clanDuesPayment = new ClanDuesPayment();
$duesStats = $clanDuesPayment->getClanPaymentStatistics($clanId);
$monthlyStats = $clanDuesPayment->getClanPaymentStatistics($clanId, 'current_month');
$yearlyStats = $clanDuesPayment->getClanPaymentStatistics($clanId, 'current_year');

// Get monthly revenue data for chart
$monthlyRevenue = $clanDuesPayment->getMonthlyRevenue($clanId);

// Get recent dues payments
$recentDuesPayments = $db->fetchAll(
    "SELECT cdp.*, cd.title as dues_title, u.full_name as user_name
     FROM clan_dues_payments cdp
     JOIN clan_dues cd ON cdp.clan_dues_id = cd.id
     JOIN users u ON cdp.user_id = u.id
     WHERE cd.clan_id = ? AND cdp.status = 'paid'
     ORDER BY cdp.payment_date DESC
     LIMIT 5",
    [$clanId]
);

// Get overdue payments count
$overdueCount = $db->fetchOne(
    "SELECT COUNT(*) as count
     FROM clan_dues_payments cdp
     JOIN clan_dues cd ON cdp.clan_dues_id = cd.id
     WHERE cd.clan_id = ? AND cdp.status IN ('pending', 'overdue')
     AND cdp.due_date < CURRENT_DATE()",
    [$clanId]
)['count'] ?? 0;

// Get active dues count
$activeDuesCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM clan_dues WHERE clan_id = ? AND is_active = 1",
    [$clanId]
)['count'] ?? 0;

// Check if payment settings are configured
$paymentSettingsConfigured = $db->fetchOne(
    "SELECT COUNT(*) as count FROM clan_payment_settings WHERE clan_id = ?",
    [$clanId]
)['count'] > 0;

// Get recent access codes
$recentCodes = $db->fetchAll(
    "SELECT ac.*, u.full_name as creator_name
     FROM access_codes ac
     JOIN users u ON ac.created_by = u.id
     WHERE ac.clan_id = ?
     ORDER BY ac.created_at DESC
     LIMIT 5",
    [$clanId]
);

// Estate payment due date check
$estatePaymentDueSoon = false;
$estatePaymentOverdue = false;
$estateDaysLeft = null;
$estateNextPaymentDate = null;
if ($clan->getPaymentStatus() !== 'free' && $clan->getNextPaymentDate()) {
    $estateNextPaymentDate = strtotime($clan->getNextPaymentDate());
    $estateDaysLeft = ceil(($estateNextPaymentDate - time()) / 86400);
    if ($estateDaysLeft <= 0) {
        $estatePaymentOverdue = true;
    } elseif ($estateDaysLeft <= 7) {
        $estatePaymentDueSoon = true;
    }
}

// Get upcoming events
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

    /* Alert Banners Container */
    .ios-alerts-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 24px;
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

    /* Alert Banners */
    .ios-alert-banner {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        border: 1px solid;
        border-radius: 14px;
        padding: 16px 18px;
        flex-shrink: 0;
    }
    .ios-alert-banner.warning {
        background: rgba(255, 159, 10, 0.08);
        border-color: rgba(255, 159, 10, 0.2);
    }
    .ios-alert-banner.danger {
        background: rgba(255, 69, 58, 0.08);
        border-color: rgba(255, 69, 58, 0.2);
    }
    .ios-alert-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .ios-alert-icon.warning { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-alert-icon.danger { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
    .ios-alert-title {
        font-size: 15px;
        font-weight: 600;
        margin: 0 0 4px 0;
    }
    .ios-alert-title.warning { color: var(--ios-orange); }
    .ios-alert-title.danger { color: var(--ios-red); }
    .ios-alert-text {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0 0 10px 0;
        line-height: 1.5;
    }
    .ios-alert-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .ios-alert-btn.warning { background: var(--ios-orange); color: white; }
    .ios-alert-btn.warning:hover { background: #E08E00; color: white; }

    /* Revenue Cards */
    .ios-revenue-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .ios-revenue-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 20px;
        transition: all 0.2s ease;
    }
    .ios-revenue-card:hover {
        border-color: var(--ios-blue);
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    }
    .ios-revenue-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 14px;
    }
    .ios-revenue-icon {
        width: 42px; height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 17px;
        flex-shrink: 0;
    }
    .ios-revenue-label {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
        font-weight: 500;
    }
    .ios-revenue-value {
        font-size: 26px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        line-height: 1;
    }

    /* Stats Overview Grid */
    .stats-overview-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
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
    .stat-progress { margin-top: 12px; }
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
    .stat-progress-fill.purple { background: var(--ios-purple); }
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
    .ios-quick-action-text { flex: 1; min-width: 0; }
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
    .ios-quick-action:hover .ios-quick-action-arrow { opacity: 1; transform: translateX(3px); }

    /* Mobile Greeting Card */
    .ios-mobile-greeting { display: none; margin-bottom: 20px; }
    .ios-mobile-greeting-card {
        display: flex;
        align-items: center;
        gap: 14px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 16px 18px;
    }
    .ios-mobile-greeting-icon {
        width: 46px; height: 46px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }
    .ios-mobile-greeting-text { flex: 1; min-width: 0; }
    .ios-mobile-greeting-text h2 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px 0; }
    .ios-mobile-greeting-text p { font-size: 13px; color: var(--text-secondary); margin: 0; }
    .ios-mobile-greeting-text p span { margin: 0 3px; opacity: 0.4; }
    .ios-mobile-greeting-dots {
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        font-size: 16px;
        flex-shrink: 0;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .ios-mobile-greeting-dots:hover { background: var(--bg-tertiary); }

    /* Mobile Quick Actions */
    .ios-mobile-actions {
        display: none;
        margin-bottom: 24px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        padding: 16px;
    }
    .ios-mobile-actions-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 14px 0; }
    .ios-mobile-actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    .ios-mobile-action-btn { display: flex; flex-direction: column; align-items: center; gap: 8px; text-decoration: none; padding: 4px; }
    .ios-mobile-action-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: white;
    }
    .ios-mobile-action-label { font-size: 11px; font-weight: 500; color: var(--text-primary); text-align: center; line-height: 1.3; }

    /* Upcoming Events */
    .ios-events-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 16px 20px; }
    .ios-event-card { display: flex; gap: 12px; padding: 14px; background: var(--bg-secondary); border-radius: 12px; text-decoration: none; transition: all 0.2s ease; }
    .ios-event-card:hover { background: var(--bg-tertiary); transform: translateY(-1px); }
    .ios-event-date-box { width: 46px; min-width: 46px; height: 46px; border-radius: 10px; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; }
    .ios-event-date-box .month { font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1; }
    .ios-event-date-box .day { font-size: 18px; font-weight: 700; line-height: 1.1; }
    .ios-event-date-box.meeting { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-event-date-box.social { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-event-date-box.maintenance { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-event-date-box.other { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-event-info { flex: 1; min-width: 0; }
    .ios-event-title { font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 3px 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ios-event-meta { font-size: 12px; color: var(--text-secondary); margin: 0 0 2px 0; display: flex; align-items: center; gap: 4px; }
    .ios-event-meta i { font-size: 10px; color: var(--text-muted); width: 12px; text-align: center; }
    .ios-event-attendees { font-size: 11px; color: var(--text-muted); margin: 0; }

    /* iOS Section Card */
    .ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 24px; }
    .ios-section-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border-color); }
    .ios-section-header-left { display: flex; align-items: center; gap: 12px; }
    .ios-section-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
    .ios-section-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-section-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-section-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-section-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-section-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }
    .ios-section-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
    .ios-section-title h5 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0; }
    .ios-section-title p { font-size: 12px; color: var(--text-secondary); margin: 2px 0 0 0; }
    .ios-section-body { padding: 0; }
    .ios-link-btn { font-size: 14px; font-weight: 500; color: var(--ios-blue); text-decoration: none; transition: opacity 0.2s ease; white-space: nowrap; }
    .ios-link-btn:hover { opacity: 0.7; color: var(--ios-blue); }

    /* Charts Grid */
    .ios-charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
    .ios-chart-body { padding: 20px; }
    .ios-chart-canvas { height: 260px !important; }
    .ios-chart-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 20px; color: var(--text-muted); text-align: center; }
    .ios-chart-empty i { font-size: 40px; margin-bottom: 12px; opacity: 0.4; }
    .ios-chart-empty p { font-size: 13px; margin: 0; max-width: 260px; }

    /* Tables Grid */
    .ios-tables-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 24px; }

    /* iOS List Items */
    .ios-list-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-bottom: 1px solid var(--border-color); transition: background 0.15s ease; }
    .ios-list-item:last-child { border-bottom: none; }
    .ios-list-item:hover { background: rgba(255, 255, 255, 0.02); }
    .ios-list-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .ios-list-dot.active { background: var(--ios-green); }
    .ios-list-dot.used { background: var(--ios-orange); }
    .ios-list-dot.expired { background: var(--ios-red); }
    .ios-list-dot.pending { background: var(--text-muted); }
    .ios-list-dot.revoked { background: var(--ios-red); }
    .ios-list-dot.paid { background: var(--ios-green); }
    .ios-list-content { flex: 1; min-width: 0; }
    .ios-list-primary { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ios-list-secondary { font-size: 13px; color: var(--text-secondary); margin: 2px 0 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ios-list-tertiary { font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0; }
    .ios-list-meta { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; }
    .ios-list-badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
    .ios-list-badge.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-list-badge.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-list-badge.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
    .ios-list-badge.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-list-badge.muted { background: var(--bg-tertiary); color: var(--text-muted); }
    .ios-list-date { font-size: 11px; color: var(--text-muted); margin-top: 4px; }

    /* Account Info Grid */
    .ios-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; }
    .ios-info-item { padding: 14px 20px; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); }
    .ios-info-item:nth-child(2n) { border-right: none; }
    .ios-info-item:nth-last-child(-n+2) { border-bottom: none; }
    .ios-info-label { font-size: 12px; color: var(--text-muted); margin: 0 0 4px 0; font-weight: 500; }
    .ios-info-value { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0; }

    /* Empty State */
    .ios-empty-state { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 20px; text-align: center; color: var(--text-muted); }
    .ios-empty-state i { font-size: 36px; margin-bottom: 12px; opacity: 0.4; }
    .ios-empty-state p { font-size: 13px; margin: 0 0 12px 0; }
    .ios-empty-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 10px; background: var(--ios-blue); color: white; font-size: 13px; font-weight: 600; text-decoration: none; transition: all 0.2s ease; }
    .ios-empty-btn:hover { background: #0070E0; color: white; }

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
    .ios-menu-item { display: flex; align-items: center; gap: 14px; padding: 14px 16px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color); transition: background 0.15s ease; }
    .ios-menu-item:last-child { border-bottom: none; }
    .ios-menu-item:hover { background: rgba(255,255,255,0.03); }
    .ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
    .ios-menu-item-icon.blue { background: rgba(10,132,255,0.15); color: var(--ios-blue); }
    .ios-menu-item-icon.green { background: rgba(48,209,88,0.15); color: var(--ios-green); }
    .ios-menu-item-icon.orange { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
    .ios-menu-item-icon.purple { background: rgba(191,90,242,0.15); color: var(--ios-purple); }
    .ios-menu-item-icon.red { background: rgba(255,69,58,0.15); color: var(--ios-red); }
    .ios-menu-item-icon.teal { background: rgba(100,210,255,0.15); color: var(--ios-teal); }
    .ios-menu-item-label { font-size: 15px; font-weight: 500; }
    .ios-menu-stat-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
    .ios-menu-stat-row:last-child { border-bottom: none; }
    .ios-menu-stat-label { font-size: 14px; color: var(--text-secondary); }
    .ios-menu-stat-value { font-size: 14px; font-weight: 600; color: var(--text-primary); }

    /* Responsive */
    @media (max-width: 992px) {
        .ios-options-btn { display: flex; }
        .ios-revenue-grid { grid-template-columns: repeat(3, 1fr); }
        .stats-overview-grid { grid-template-columns: repeat(2, 1fr); }
        .ios-quick-actions { grid-template-columns: 1fr; }
        .ios-charts-grid { grid-template-columns: 1fr; }
        .ios-tables-grid { grid-template-columns: 1fr; }
        .ios-info-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
        .content-header { display: none !important; }
        .ios-mobile-greeting { display: block; }
        .ios-quick-actions { display: none; }
        .ios-mobile-actions { display: block; }

        .ios-alerts-container {
            flex-direction: row;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding-bottom: 4px;
            margin-bottom: 20px;
        }
        .ios-alerts-container::-webkit-scrollbar { display: none; }
        .ios-alert-banner { min-width: 280px; flex: 0 0 auto; padding: 14px 16px; border-radius: 12px; }
        .ios-alerts-container .ios-alert-banner:only-child { min-width: 100%; }

        .ios-revenue-grid {
            display: flex;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            gap: 12px;
            padding-bottom: 4px;
            scrollbar-width: none;
        }
        .ios-revenue-grid::-webkit-scrollbar { display: none; }
        .ios-revenue-card { min-width: 160px; flex: 0 0 auto; padding: 14px; }
        .ios-revenue-header { margin-bottom: 8px; gap: 10px; }
        .ios-revenue-icon { width: 32px; height: 32px; font-size: 14px; border-radius: 9px; }
        .ios-revenue-value { font-size: 20px; }
        .ios-revenue-label { font-size: 11px; }

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

        .ios-info-grid { grid-template-columns: 1fr; }
        .ios-info-item { border-right: none !important; }
        .ios-info-item:last-child { border-bottom: none; }
    }
    @media (max-width: 480px) {
        .ios-mobile-greeting-text h2 { font-size: 15px; }
        .ios-mobile-greeting-text p { font-size: 12px; }
        .ios-mobile-greeting-icon { width: 42px; height: 42px; font-size: 18px; border-radius: 10px; }

        .ios-revenue-card { min-width: 150px; padding: 12px; }
        .ios-revenue-icon { width: 30px; height: 30px; font-size: 13px; }
        .ios-revenue-value { font-size: 18px; }

        .stat-card { min-width: 130px; padding: 12px; }
        .stat-icon { width: 30px; height: 30px; font-size: 13px; border-radius: 8px; }
        .stat-value { font-size: 18px; }
        .stat-header { margin-bottom: 6px; gap: 8px; }

        .ios-mobile-actions { padding: 14px; border-radius: 12px; }
        .ios-mobile-actions-grid { gap: 10px; }
        .ios-mobile-action-icon { width: 48px; height: 48px; border-radius: 12px; font-size: 20px; }
        .ios-mobile-action-label { font-size: 10px; }

        .ios-section-header { padding: 12px 14px; }
        .ios-section-icon { width: 34px; height: 34px; font-size: 13px; border-radius: 9px; }
        .ios-list-item { padding: 10px 14px; gap: 10px; }
        .ios-list-primary { font-size: 13px; }
        .ios-list-secondary { font-size: 11px; }
        .ios-list-badge { font-size: 10px; padding: 2px 6px; }
    }
    @media (max-width: 390px) {
        .ios-mobile-actions { padding: 12px; }
        .ios-mobile-actions-grid { grid-template-columns: repeat(3, 1fr); }
        .ios-mobile-action-icon { width: 44px; height: 44px; font-size: 18px; }
        .stat-card { min-width: 120px; padding: 10px; }
        .stat-value { font-size: 17px; }
        .ios-list-item { padding: 10px 12px; gap: 8px; }
    }
</style>

<!-- iOS Clan Admin Dashboard -->
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
                <p>Clan Admin Dashboard</p>
            </div>
            <button class="ios-options-btn" onclick="openMenu()">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Mobile Greeting Card -->
        <div class="ios-mobile-greeting">
            <div class="ios-mobile-greeting-card">
                <div class="ios-mobile-greeting-text">
                    <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($firstName); ?></h2>
                    <p>Clan Admin Dashboard</p>
                </div>
                <div class="ios-mobile-greeting-dots" onclick="openMenu()">
                    <i class="fas fa-ellipsis-v"></i>
                </div>
            </div>
        </div>

        <!-- Alert Banners -->
        <?php if (!$paymentSettingsConfigured || $overdueCount > 0 || $estatePaymentOverdue || $estatePaymentDueSoon): ?>
        <div class="ios-alerts-container">
            <?php if ($estatePaymentOverdue): ?>
                <div class="ios-alert-banner danger">
                    <div class="ios-alert-icon danger">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <p class="ios-alert-title danger">Estate Payment Overdue</p>
                        <p class="ios-alert-text">Your estate payment was due <?php echo date('M j, Y', $estateNextPaymentDate); ?>. Renew to avoid service interruption.</p>
                        <a href="<?php echo BASE_URL; ?>payments/settings.php?type=clan" class="ios-alert-btn warning">
                            <i class="fas fa-credit-card"></i> Pay Now
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($estatePaymentDueSoon): ?>
                <div class="ios-alert-banner warning">
                    <div class="ios-alert-icon warning">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <p class="ios-alert-title warning">Estate Payment Due Soon</p>
                        <p class="ios-alert-text">Your estate payment is due in <?php echo $estateDaysLeft; ?> day<?php echo $estateDaysLeft > 1 ? 's' : ''; ?> (<?php echo date('M j, Y', $estateNextPaymentDate); ?>).</p>
                        <a href="<?php echo BASE_URL; ?>payments/settings.php?type=clan" class="ios-alert-btn warning">
                            <i class="fas fa-credit-card"></i> Renew Now
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!$paymentSettingsConfigured): ?>
                <div class="ios-alert-banner warning">
                    <div class="ios-alert-icon warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <p class="ios-alert-title warning">Payment Setup Required</p>
                        <p class="ios-alert-text">Configure your Paystack payment settings to start collecting clan dues.</p>
                        <a href="<?php echo BASE_URL; ?>admin/clan-dues/payment-settings.php" class="ios-alert-btn warning">
                            <i class="fas fa-cog"></i> Setup Payments
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($overdueCount > 0): ?>
                <div class="ios-alert-banner danger">
                    <div class="ios-alert-icon danger">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div>
                        <p class="ios-alert-title danger"><?php echo $overdueCount; ?> Overdue Payment<?php echo $overdueCount > 1 ? 's' : ''; ?></p>
                        <p class="ios-alert-text">Some members have overdue dues payments. Consider sending reminders.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Revenue Cards -->
        <div class="ios-revenue-grid">
            <div class="ios-revenue-card">
                <div class="ios-revenue-header">
                    <div class="ios-revenue-icon" style="background: rgba(10, 132, 255, 0.15); color: var(--ios-blue);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <span class="ios-revenue-label">This Month</span>
                </div>
                <p class="ios-revenue-value">&#8358;<?php echo number_format($monthlyStats['paid_amount'] ?? 0, 0); ?></p>
            </div>
            <div class="ios-revenue-card">
                <div class="ios-revenue-header">
                    <div class="ios-revenue-icon" style="background: rgba(48, 209, 88, 0.15); color: var(--ios-green);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <span class="ios-revenue-label">This Year</span>
                </div>
                <p class="ios-revenue-value">&#8358;<?php echo number_format($yearlyStats['paid_amount'] ?? 0, 0); ?></p>
            </div>
            <div class="ios-revenue-card">
                <div class="ios-revenue-header">
                    <div class="ios-revenue-icon" style="background: rgba(191, 90, 242, 0.15); color: var(--ios-purple);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <span class="ios-revenue-label">All Time</span>
                </div>
                <p class="ios-revenue-value">&#8358;<?php echo number_format($duesStats['paid_amount'] ?? 0, 0); ?></p>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-overview-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue"><i class="fas fa-users"></i></div>
                    <span class="stat-label">Active Members</span>
                </div>
                <p class="stat-value"><?php echo number_format($totalUsers); ?></p>
                <div class="stat-progress">
                    <div class="stat-progress-bar"><div class="stat-progress-fill blue" style="width: 100%;"></div></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon purple"><i class="fas fa-file-invoice-dollar"></i></div>
                    <span class="stat-label">Active Dues</span>
                </div>
                <p class="stat-value"><?php echo number_format($activeDuesCount); ?></p>
                <div class="stat-progress">
                    <div class="stat-progress-bar"><div class="stat-progress-fill purple" style="width: 100%;"></div></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <span class="stat-label">Paid Dues</span>
                </div>
                <p class="stat-value"><?php echo number_format($duesStats['paid_count'] ?? 0); ?></p>
                <div class="stat-progress">
                    <div class="stat-progress-bar"><div class="stat-progress-fill green" style="width: 100%;"></div></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
                    <span class="stat-label">Pending Dues</span>
                </div>
                <p class="stat-value"><?php echo number_format($duesStats['pending_count'] ?? 0); ?></p>
                <div class="stat-progress">
                    <div class="stat-progress-bar"><div class="stat-progress-fill orange" style="width: <?php echo ($duesStats['paid_count'] ?? 0) > 0 ? (($duesStats['pending_count'] ?? 0) / (($duesStats['paid_count'] ?? 0) + ($duesStats['pending_count'] ?? 0))) * 100 : 0; ?>%;"></div></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
                    <span class="stat-label">Overdue</span>
                </div>
                <p class="stat-value"><?php echo number_format($overdueCount); ?></p>
                <div class="stat-progress">
                    <div class="stat-progress-bar"><div class="stat-progress-fill red" style="width: <?php echo $totalUsers > 0 ? ($overdueCount / $totalUsers) * 100 : 0; ?>%;"></div></div>
                </div>
            </div>
        </div>

        <!-- Quick Actions (Desktop) -->
        <div class="ios-quick-actions">
            <a href="<?php echo BASE_URL; ?>admin/clan-dues/create-dues.php" class="ios-quick-action">
                <div class="ios-quick-action-icon" style="background: rgba(10, 132, 255, 0.15); color: var(--ios-blue);">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="ios-quick-action-text">
                    <p class="ios-quick-action-title">Create New Dues</p>
                    <p class="ios-quick-action-desc">Set up payment collection</p>
                </div>
                <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>users/create.php" class="ios-quick-action">
                <div class="ios-quick-action-icon" style="background: rgba(48, 209, 88, 0.15); color: var(--ios-green);">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="ios-quick-action-text">
                    <p class="ios-quick-action-title">Add New Member</p>
                    <p class="ios-quick-action-desc">Invite to your clan</p>
                </div>
                <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>access-codes/generate.php" class="ios-quick-action">
                <div class="ios-quick-action-icon" style="background: rgba(191, 90, 242, 0.15); color: var(--ios-purple);">
                    <i class="fas fa-qrcode"></i>
                </div>
                <div class="ios-quick-action-text">
                    <p class="ios-quick-action-title">Generate Access Code</p>
                    <p class="ios-quick-action-desc">Create visitor codes</p>
                </div>
                <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
            </a>
        </div>

        <!-- Mobile Quick Actions -->
        <div class="ios-mobile-actions">
            <h2 class="ios-mobile-actions-title">Quick Actions</h2>
            <div class="ios-mobile-actions-grid">
                <a href="<?php echo BASE_URL; ?>admin/clan-dues/" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-blue);"><i class="fas fa-chart-line"></i></div>
                    <span class="ios-mobile-action-label">Payment</span>
                </a>
                <a href="<?php echo BASE_URL; ?>households/" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-green);"><i class="fas fa-home"></i></div>
                    <span class="ios-mobile-action-label">Household</span>
                </a>
                <a href="<?php echo BASE_URL; ?>users/" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-teal);"><i class="fas fa-users"></i></div>
                    <span class="ios-mobile-action-label">Users</span>
                </a>
                <a href="<?php echo BASE_URL; ?>guards/" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-purple);"><i class="fas fa-shield-alt"></i></div>
                    <span class="ios-mobile-action-label">Guards</span>
                </a>
                <a href="<?php echo BASE_URL; ?>payments/settings.php?type=clan" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-orange);"><i class="fas fa-building"></i></div>
                    <span class="ios-mobile-action-label">Estate Pay</span>
                </a>
                <a href="<?php echo BASE_URL; ?>payments/settings.php?type=license" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: var(--ios-red);"><i class="fas fa-user-plus"></i></div>
                    <span class="ios-mobile-action-label">Extra User</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/events/" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: #FF9F0A;"><i class="fas fa-calendar-alt"></i></div>
                    <span class="ios-mobile-action-label">Events</span>
                </a>
                <a href="<?php echo BASE_URL; ?>announcements/" class="ios-mobile-action-btn">
                    <div class="ios-mobile-action-icon" style="background: #64D2FF;"><i class="fas fa-bullhorn"></i></div>
                    <span class="ios-mobile-action-label">Announce</span>
                </a>
            </div>
        </div>

        <!-- Upcoming Events -->
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-header-left">
                    <div class="ios-section-icon orange"><i class="fas fa-calendar-alt"></i></div>
                    <div class="ios-section-title">
                        <h5>Upcoming Events</h5>
                        <p><?php echo count($upcomingEvents); ?> upcoming</p>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>admin/events/all-events.php" class="ios-link-btn">View All</a>
            </div>
            <?php if (empty($upcomingEvents)): ?>
                <div class="ios-empty-state">
                    <i class="fas fa-calendar-alt"></i>
                    <p>No upcoming events.</p>
                    <a href="<?php echo BASE_URL; ?>admin/events/create-event.php" class="ios-empty-btn">
                        <i class="fas fa-plus"></i> Create Event
                    </a>
                </div>
            <?php else: ?>
                <div class="ios-events-grid">
                    <?php foreach ($upcomingEvents as $event):
                        $eventType = $event['event_type'] ?? 'other';
                        $startDt = strtotime($event['start_datetime']);
                    ?>
                        <a href="<?php echo BASE_URL; ?>admin/events/view-event.php?id=<?php echo encryptId($event['id']); ?>" class="ios-event-card">
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
            <!-- Revenue Chart -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-header-left">
                        <div class="ios-section-icon green"><i class="fas fa-chart-line"></i></div>
                        <div class="ios-section-title">
                            <h5>Monthly Revenue</h5>
                            <p>Revenue over the last 12 months</p>
                        </div>
                    </div>
                </div>
                <div class="ios-chart-body">
                    <?php if (empty($monthlyRevenue)): ?>
                        <div class="ios-chart-empty">
                            <i class="fas fa-chart-line"></i>
                            <p>No revenue data available yet.</p>
                        </div>
                    <?php else: ?>
                        <canvas id="revenueChart" class="ios-chart-canvas"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions Panel (Desktop) -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-header-left">
                        <div class="ios-section-icon blue"><i class="fas fa-bolt"></i></div>
                        <div class="ios-section-title">
                            <h5>Management</h5>
                            <p>Quick access links</p>
                        </div>
                    </div>
                </div>
                <div class="ios-section-body">
                    <a href="<?php echo BASE_URL; ?>admin/clan-dues/create-dues.php" class="ios-list-item" style="text-decoration: none;">
                        <span class="ios-list-dot" style="background: var(--ios-blue);"></span>
                        <div class="ios-list-content">
                            <p class="ios-list-primary">Create New Dues</p>
                            <p class="ios-list-secondary">Set up payment collection</p>
                        </div>
                    </a>
                    <a href="<?php echo BASE_URL; ?>users/create.php" class="ios-list-item" style="text-decoration: none;">
                        <span class="ios-list-dot" style="background: var(--ios-green);"></span>
                        <div class="ios-list-content">
                            <p class="ios-list-primary">Add New Member</p>
                            <p class="ios-list-secondary">Invite to your clan</p>
                        </div>
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/clan-dues/bulk-payments.php" class="ios-list-item" style="text-decoration: none;">
                        <span class="ios-list-dot" style="background: var(--ios-purple);"></span>
                        <div class="ios-list-content">
                            <p class="ios-list-primary">Bulk Payments</p>
                            <p class="ios-list-secondary">Manage prepaid dues</p>
                        </div>
                    </a>
                    <a href="<?php echo BASE_URL; ?>admin/clan-dues/payment-settings.php" class="ios-list-item" style="text-decoration: none;">
                        <span class="ios-list-dot" style="background: var(--ios-orange);"></span>
                        <div class="ios-list-content">
                            <p class="ios-list-primary">Payment Settings</p>
                            <p class="ios-list-secondary">Configure Paystack</p>
                        </div>
                    </a>
                    <?php if ($overdueCount > 0): ?>
                    <a href="<?php echo BASE_URL; ?>admin/clan-dues/payments.php?status=overdue" class="ios-list-item" style="text-decoration: none;">
                        <span class="ios-list-dot" style="background: var(--ios-red);"></span>
                        <div class="ios-list-content">
                            <p class="ios-list-primary" style="color: var(--ios-red);">Send Reminders</p>
                            <p class="ios-list-secondary"><?php echo $overdueCount; ?> overdue payment<?php echo $overdueCount > 1 ? 's' : ''; ?></p>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Activity Tables -->
        <div class="ios-tables-grid">
            <!-- Recent Dues Payments -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-header-left">
                        <div class="ios-section-icon green"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="ios-section-title">
                            <h5>Recent Payments</h5>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>admin/clan-dues/payments.php" class="ios-link-btn">View All</a>
                </div>
                <div class="ios-section-body">
                    <?php if (empty($recentDuesPayments)): ?>
                        <div class="ios-empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No recent payments.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentDuesPayments as $payment): ?>
                            <div class="ios-list-item">
                                <span class="ios-list-dot paid"></span>
                                <div class="ios-list-content">
                                    <p class="ios-list-primary"><?php echo htmlspecialchars($payment['user_name']); ?></p>
                                    <p class="ios-list-secondary"><?php echo htmlspecialchars($payment['dues_title']); ?></p>
                                    <p class="ios-list-tertiary"><i class="fas fa-clock"></i> <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></p>
                                </div>
                                <div class="ios-list-meta">
                                    <span class="ios-list-badge green">&#8358;<?php echo number_format($payment['total_amount'], 0); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Access Codes -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-header-left">
                        <div class="ios-section-icon purple"><i class="fas fa-qrcode"></i></div>
                        <div class="ios-section-title">
                            <h5>Recent Access Codes</h5>
                        </div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>access-codes/" class="ios-link-btn">View All</a>
                </div>
                <div class="ios-section-body">
                    <?php if (empty($recentCodes)): ?>
                        <div class="ios-empty-state">
                            <i class="fas fa-qrcode"></i>
                            <p>No recent access codes.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentCodes as $code):
                            $now = time();
                            $validFrom = strtotime($code['valid_from']);
                            $validUntil = strtotime($code['valid_until']);

                            if ($code['status'] === 'used') {
                                $dotClass = 'used'; $badgeClass = 'orange'; $badgeLabel = 'Used';
                            } elseif ($code['status'] === 'revoked') {
                                $dotClass = 'revoked'; $badgeClass = 'red'; $badgeLabel = 'Revoked';
                            } elseif ($now < $validFrom) {
                                $dotClass = 'pending'; $badgeClass = 'muted'; $badgeLabel = 'Pending';
                            } elseif ($now > $validUntil) {
                                $dotClass = 'expired'; $badgeClass = 'red'; $badgeLabel = 'Expired';
                            } else {
                                $dotClass = 'active'; $badgeClass = 'green'; $badgeLabel = 'Active';
                            }
                        ?>
                            <div class="ios-list-item">
                                <span class="ios-list-dot <?php echo $dotClass; ?>"></span>
                                <div class="ios-list-content">
                                    <p class="ios-list-primary"><?php echo htmlspecialchars($code['visitor_name']); ?></p>
                                    <p class="ios-list-secondary"><?php echo htmlspecialchars($code['creator_name']); ?></p>
                                    <p class="ios-list-tertiary"><i class="fas fa-clock"></i> <?php echo date('M j, Y', strtotime($code['created_at'])); ?></p>
                                </div>
                                <div class="ios-list-meta">
                                    <span class="ios-list-badge <?php echo $badgeClass; ?>"><?php echo $badgeLabel; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Account Information -->
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-header-left">
                    <div class="ios-section-icon teal"><i class="fas fa-info-circle"></i></div>
                    <div class="ios-section-title">
                        <h5>Account Information</h5>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($clanId); ?>" class="ios-link-btn">Settings</a>
            </div>
            <div class="ios-info-grid">
                <div class="ios-info-item">
                    <p class="ios-info-label">Clan Name</p>
                    <p class="ios-info-value"><?php echo htmlspecialchars($clan->getName()); ?></p>
                </div>
                <div class="ios-info-item">
                    <p class="ios-info-label">Clan Status</p>
                    <p class="ios-info-value">
                        <?php
                        $paymentStatus = $clan->getPaymentStatus();
                        $statusColor = $paymentStatus === 'active' ? 'var(--ios-green)' : ($paymentStatus === 'free' ? 'var(--ios-orange)' : 'var(--ios-red)');
                        ?>
                        <span style="color: <?php echo $statusColor; ?>;"><?php echo ucfirst($paymentStatus); ?></span>
                    </p>
                </div>
                <?php if ($clan->getPaymentStatus() !== 'free' && $clan->getNextPaymentDate()): ?>
                <div class="ios-info-item">
                    <p class="ios-info-label">Next Payment Due</p>
                    <p class="ios-info-value">
                        <?php
                        $nextPaymentDate = strtotime($clan->getNextPaymentDate());
                        $daysLeft = ceil(($nextPaymentDate - time()) / 86400);
                        echo date('M j, Y', $nextPaymentDate);
                        if ($daysLeft > 0 && $daysLeft <= 7): ?>
                            <span class="ios-list-badge orange" style="margin-left: 6px;"><?php echo $daysLeft; ?>d left</span>
                        <?php elseif ($daysLeft <= 0): ?>
                            <span class="ios-list-badge red" style="margin-left: 6px;">Overdue</span>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
                <div class="ios-info-item">
                    <p class="ios-info-label">Payment Config</p>
                    <p class="ios-info-value">
                        <span style="color: <?php echo $paymentSettingsConfigured ? 'var(--ios-green)' : 'var(--ios-orange)'; ?>;">
                            <?php echo $paymentSettingsConfigured ? 'Configured' : 'Setup Required'; ?>
                        </span>
                    </p>
                </div>
                <div class="ios-info-item">
                    <p class="ios-info-label">Total Members</p>
                    <p class="ios-info-value"><?php echo number_format($totalUsers); ?></p>
                </div>
                <div class="ios-info-item">
                    <p class="ios-info-label">Active Dues</p>
                    <p class="ios-info-value"><?php echo number_format($activeDuesCount); ?></p>
                </div>
                <div class="ios-info-item">
                    <p class="ios-info-label">Total Access Codes</p>
                    <p class="ios-info-value"><?php echo number_format($totalCodes); ?></p>
                </div>
                <div class="ios-info-item">
                    <p class="ios-info-label">Active Codes</p>
                    <p class="ios-info-value"><?php echo number_format($activeCodes); ?></p>
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
        <h3>Admin Dashboard</h3>
        <p>Quick access and statistics</p>
    </div>
    <div class="ios-menu-content">
        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Quick Actions</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>admin/clan-dues/create-dues.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon blue"><i class="fas fa-plus-circle"></i></div>
                    <span class="ios-menu-item-label">Create New Dues</span>
                </a>
                <a href="<?php echo BASE_URL; ?>users/create.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon green"><i class="fas fa-user-plus"></i></div>
                    <span class="ios-menu-item-label">Add New Member</span>
                </a>
                <a href="<?php echo BASE_URL; ?>admin/clan-dues/payments.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon purple"><i class="fas fa-money-bill-wave"></i></div>
                    <span class="ios-menu-item-label">Manage Payments</span>
                </a>
                <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($clanId); ?>" class="ios-menu-item">
                    <div class="ios-menu-item-icon orange"><i class="fas fa-cog"></i></div>
                    <span class="ios-menu-item-label">Clan Settings</span>
                </a>
            </div>
        </div>

        <!-- Revenue Stats -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Revenue</p>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">This Month</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-blue);">&#8358;<?php echo number_format($monthlyStats['paid_amount'] ?? 0, 0); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">This Year</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-green);">&#8358;<?php echo number_format($yearlyStats['paid_amount'] ?? 0, 0); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">All Time</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-purple);">&#8358;<?php echo number_format($duesStats['paid_amount'] ?? 0, 0); ?></span>
                </div>
            </div>
        </div>

        <!-- Members Stats -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Statistics</p>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active Members</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($totalUsers); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Paid Dues</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-green);"><?php echo number_format($duesStats['paid_count'] ?? 0); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Pending Dues</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-orange);"><?php echo number_format($duesStats['pending_count'] ?? 0); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Overdue</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-red);"><?php echo number_format($overdueCount); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart & Menu Scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('iOS Clan Admin Dashboard loaded');

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
    let revenueChart = null;

    // Revenue Chart
    <?php if (!empty($monthlyRevenue)): ?>
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        const monthlyData = <?php echo json_encode($monthlyRevenue); ?>;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const revenueData = new Array(12).fill(0);

        monthlyData.forEach(item => {
            const monthIndex = parseInt(item.month.split('-')[1]) - 1;
            revenueData[monthIndex] = parseFloat(item.total_revenue || 0);
        });

        revenueChart = new Chart(revenueCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Revenue',
                    data: revenueData,
                    borderColor: c.green,
                    backgroundColor: c.green + '20',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: c.green,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
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
                        bodyFont: { family: c.font, size: 13 },
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: \u20A6' + context.parsed.y.toLocaleString();
                            }
                        }
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
                        ticks: {
                            color: c.textSecondary,
                            font: { family: c.font, size: 12 },
                            callback: function(value) { return '\u20A6' + value.toLocaleString(); }
                        }
                    }
                },
                interaction: { intersect: false, mode: 'index' }
            }
        });
    }
    <?php endif; ?>

    // Update chart when theme changes
    document.addEventListener('themeChanged', function() {
        const nc = getChartColors();
        if (revenueChart) {
            revenueChart.data.datasets[0].borderColor = nc.green;
            revenueChart.data.datasets[0].backgroundColor = nc.green + '20';
            revenueChart.data.datasets[0].pointBackgroundColor = nc.green;
            revenueChart.options.scales.x.ticks.color = nc.textSecondary;
            revenueChart.options.scales.y.ticks.color = nc.textSecondary;
            revenueChart.options.scales.y.grid.color = nc.border + '40';
            revenueChart.update('none');
        }
    });
});
</script>

<?php include_once '../includes/footer.php'; ?>
