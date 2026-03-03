<?php

/**
 * GateWey - Announcements Page
 * Styled like marketplace without sidebars
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';

// Set page title and enable charts
$pageTitle = 'Announcements';
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

// Get announcements with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$whereClause = "WHERE a.clan_id = ?";
$params = [$clanId];

if ($filter === 'pinned') {
    $whereClause .= " AND a.is_pinned = 1";
} elseif ($filter === 'urgent') {
    $whereClause .= " AND a.priority = 'urgent'";
} elseif ($filter === 'my-posts') {
    $whereClause .= " AND a.author_id = ?";
    $params[] = $currentUser->getId();
}

// Get total count
$totalQuery = "SELECT COUNT(*) as total FROM announcements a $whereClause";
$totalResult = $db->fetchOne($totalQuery, $params);
$totalAnnouncements = $totalResult['total'];
$totalPages = ceil($totalAnnouncements / $perPage);

// Get announcements
$query = "SELECT a.*, 
          COUNT(DISTINCT ar.id) as reaction_count,
          COUNT(DISTINCT ac.id) as comment_count,
          EXISTS(SELECT 1 FROM announcement_reactions ar2 
                 WHERE ar2.announcement_id = a.id 
                 AND ar2.user_id = ?) as user_reacted
          FROM announcements a
          LEFT JOIN announcement_reactions ar ON a.id = ar.announcement_id
          LEFT JOIN announcement_comments ac ON a.id = ac.announcement_id
          $whereClause
          GROUP BY a.id
          ORDER BY a.is_pinned DESC, a.created_at DESC
          LIMIT ? OFFSET ?";

// Rebuild params in correct order to match query placeholders
// First placeholder is for EXISTS subquery (user_id), then WHERE clause params, then LIMIT/OFFSET
$queryParams = [$currentUser->getId()]; // For EXISTS subquery
$queryParams = array_merge($queryParams, $params); // Add WHERE clause params (clan_id and possibly author_id)
$queryParams[] = $perPage;
$queryParams[] = $offset;

$announcements = $db->fetchAll($query, $queryParams);

// Get stats
$statsQuery = "SELECT 
    COUNT(*) as total_announcements,
    COALESCE(SUM(CASE WHEN is_pinned = 1 THEN 1 ELSE 0 END), 0) as pinned_count,
    COALESCE(SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END), 0) as urgent_count,
    COALESCE(SUM(CASE WHEN author_id = ? THEN 1 ELSE 0 END), 0) as my_posts
    FROM announcements 
    WHERE clan_id = ?";
$stats = $db->fetchOne($statsQuery, [$currentUser->getId(), $clanId]);

// Include header
include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>

<!-- iOS-Style Announcements Page Styles -->
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

    .ios-section-icon.orange {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .ios-section-title {
        flex: 1;
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

    /* Statistics Grid - iOS Style */
    .ios-stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: var(--spacing-3);
        padding: var(--spacing-4);
        background: var(--bg-subtle);
    }

    .ios-stat-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: var(--spacing-4);
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
        transition: all 0.2s ease;
    }

    .ios-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .ios-stat-icon {
        width: 44px;
        height: 44px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .ios-stat-icon.primary {
        background: rgba(34, 197, 94, 0.15);
        color: var(--ios-green);
    }

    .ios-stat-icon.orange {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .ios-stat-icon.blue {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    .ios-stat-icon.purple {
        background: rgba(191, 90, 242, 0.15);
        color: var(--ios-purple);
    }

    .ios-stat-content {
        flex: 1;
    }

    .ios-stat-label {
        font-size: 12px;
        color: var(--text-secondary);
        margin-bottom: 2px;
    }

    .ios-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
    }

    /* iOS Filter Section */
    .ios-filter-section {
        padding: var(--spacing-4);
        border-bottom: 1px solid var(--border-color);
    }

    .ios-filter-tabs {
        display: flex;
        gap: var(--spacing-2);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        padding-bottom: var(--spacing-2);
    }

    .ios-filter-tabs::-webkit-scrollbar {
        display: none;
    }

    .ios-filter-tab {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-secondary);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        text-decoration: none;
    }

    .ios-filter-tab:hover {
        background: var(--bg-hover);
        color: var(--text-primary);
    }

    .ios-filter-tab.active {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
        color: white;
    }

    .ios-filter-tab i {
        font-size: 14px;
    }

    .ios-filter-badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 600;
    }

    .ios-filter-tab:not(.active) .ios-filter-badge {
        background: var(--bg-hover);
        color: var(--text-secondary);
    }

    /* Announcements Grid */
    .ios-announcements-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: var(--spacing-4);
        padding: var(--spacing-4);
    }

    /* iOS Announcement Card */
    .ios-announcement-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        overflow: hidden;
        transition: all 0.2s ease;
        display: flex;
        flex-direction: column;
    }

    .ios-announcement-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        border-color: var(--ios-blue);
    }

    .ios-announcement-card.pinned {
        border-color: var(--ios-orange);
        border-width: 2px;
    }

    .ios-announcement-card.urgent {
        border-color: var(--ios-red);
        border-width: 2px;
    }

    /* Card Header */
    .ios-card-header {
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }

    .ios-author-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .ios-author-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--ios-blue);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 14px;
    }

    .ios-author-details h6 {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 2px 0;
    }

    .ios-author-meta {
        font-size: 12px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ios-role-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 11px;
        font-weight: 500;
    }

    .ios-role-badge::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--ios-green);
    }

    .ios-role-badge.super-admin::before { background: var(--ios-red); }
    .ios-role-badge.clan-admin::before { background: var(--ios-orange); }
    .ios-role-badge.guard::before { background: var(--ios-blue); }

    /* Priority/Pin Badges */
    .ios-pin-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: var(--ios-orange);
        color: white;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
    }

    .ios-priority-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .ios-priority-badge.urgent {
        background: rgba(255, 69, 58, 0.15);
        color: var(--ios-red);
    }

    .ios-priority-badge.high {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .ios-priority-badge.normal {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    /* Card Image */
    .ios-card-image {
        width: 100%;
        height: 180px;
        object-fit: cover;
        background: var(--bg-subtle);
    }

    /* Card Body */
    .ios-card-body {
        padding: 16px;
        flex: 1;
    }

    .ios-card-title {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 10px 0;
        line-height: 1.4;
    }

    .ios-card-content {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.5;
        margin: 0;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Card Footer */
    .ios-card-footer {
        padding: 12px 16px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ios-card-actions {
        display: flex;
        gap: 16px;
    }

    .ios-action-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        background: transparent;
        border: none;
        color: var(--text-secondary);
        font-size: 13px;
        cursor: pointer;
        transition: color 0.15s ease;
        padding: 4px;
    }

    .ios-action-btn:hover {
        color: var(--ios-blue);
    }

    .ios-action-btn.active {
        color: var(--ios-red);
    }

    .ios-action-btn i {
        font-size: 14px;
    }

    .ios-read-more-btn {
        padding: 8px 14px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
        text-decoration: none;
    }

    .ios-read-more-btn:hover {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
        color: white;
    }

    /* Empty State */
    .ios-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: var(--spacing-10);
        text-align: center;
    }

    .ios-empty-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--bg-secondary);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: var(--spacing-4);
    }

    .ios-empty-icon i {
        font-size: 32px;
        color: var(--text-muted);
    }

    .ios-empty-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 var(--spacing-2) 0;
    }

    .ios-empty-text {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0 0 var(--spacing-4) 0;
    }

    /* iOS Pagination */
    .ios-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--spacing-4);
        border-top: 1px solid var(--border-color);
        background: var(--bg-subtle);
    }

    .ios-pagination-info {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .ios-pagination-nav {
        display: flex;
        gap: var(--spacing-2);
    }

    .ios-page-btn {
        min-width: 36px;
        height: 36px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
    }

    .ios-page-btn:hover:not(:disabled) {
        background: var(--bg-hover);
        border-color: var(--ios-blue);
        color: var(--ios-blue);
    }

    .ios-page-btn.active {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
        color: white;
    }

    .ios-page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
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

    .ios-menu-check {
        color: var(--ios-blue);
        font-size: 14px;
        flex-shrink: 0;
    }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .content-header {
            display: none !important;
        }

        .ios-stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: var(--spacing-2);
            padding: var(--spacing-3);
        }

        .ios-stat-card {
            padding: var(--spacing-3);
        }

        .ios-stat-icon {
            width: 36px;
            height: 36px;
            font-size: 16px;
        }

        .ios-stat-value {
            font-size: 20px;
        }

        .ios-filter-section {
            display: none;
        }

        .ios-section-card {
            border-radius: 12px;
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

        .ios-announcements-grid {
            grid-template-columns: 1fr;
            gap: var(--spacing-3);
            padding: var(--spacing-3);
        }

        .ios-announcement-card {
            border-radius: 12px;
        }

        .ios-card-image {
            height: 150px;
        }

        .ios-pagination {
            flex-direction: column;
            gap: var(--spacing-3);
            padding: var(--spacing-3);
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

        .ios-card-title {
            font-size: 15px;
        }

        .ios-card-content {
            font-size: 13px;
        }
    }
</style>

<!-- Use Standard Dasher UI Content Container -->
<div class="content">
    <!-- Content Header (Hidden on Mobile) -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <h1 class="content-title">
                    <i class="fas fa-bullhorn me-2"></i>Announcements
                </h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Announcements</li>
                    </ol>
                </nav>
            </div>
            <div class="content-actions">
                <a href="<?php echo BASE_URL; ?>announcements/create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    <span>New Announcement</span>
                </a>
            </div>
        </div>
    </div>

    <!-- iOS Section Card -->
    <div class="ios-section-card">
        <!-- Section Header with 3-Dot Menu -->
        <div class="ios-section-header">
            <div class="ios-section-icon orange">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div class="ios-section-title">
                <h5>Announcements</h5>
                <p>Stay updated with important estate announcements</p>
            </div>
            <!-- 3-Dot Menu Button -->
            <button class="ios-options-btn" id="iosOptionsBtn" aria-label="Open menu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Statistics Grid -->
        <div class="ios-stats-grid">
            <div class="ios-stat-card">
                <div class="ios-stat-icon primary">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="ios-stat-content">
                    <div class="ios-stat-label">Total</div>
                    <div class="ios-stat-value"><?php echo number_format($stats['total_announcements']); ?></div>
                </div>
            </div>

            <div class="ios-stat-card">
                <div class="ios-stat-icon orange">
                    <i class="fas fa-thumbtack"></i>
                </div>
                <div class="ios-stat-content">
                    <div class="ios-stat-label">Pinned</div>
                    <div class="ios-stat-value"><?php echo number_format($stats['pinned_count']); ?></div>
                </div>
            </div>

            <div class="ios-stat-card">
                <div class="ios-stat-icon blue">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="ios-stat-content">
                    <div class="ios-stat-label">Urgent</div>
                    <div class="ios-stat-value"><?php echo number_format($stats['urgent_count']); ?></div>
                </div>
            </div>

            <div class="ios-stat-card">
                <div class="ios-stat-icon purple">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="ios-stat-content">
                    <div class="ios-stat-label">My Posts</div>
                    <div class="ios-stat-value"><?php echo number_format($stats['my_posts']); ?></div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs Section -->
        <div class="ios-filter-section">
            <div class="ios-filter-tabs">
                <a href="?filter=all" class="ios-filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i>
                    All
                    <span class="ios-filter-badge"><?php echo $stats['total_announcements']; ?></span>
                </a>
                <a href="?filter=pinned" class="ios-filter-tab <?php echo $filter === 'pinned' ? 'active' : ''; ?>">
                    <i class="fas fa-thumbtack"></i>
                    Pinned
                    <span class="ios-filter-badge"><?php echo $stats['pinned_count']; ?></span>
                </a>
                <a href="?filter=urgent" class="ios-filter-tab <?php echo $filter === 'urgent' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-circle"></i>
                    Urgent
                    <span class="ios-filter-badge"><?php echo $stats['urgent_count']; ?></span>
                </a>
                <a href="?filter=my-posts" class="ios-filter-tab <?php echo $filter === 'my-posts' ? 'active' : ''; ?>">
                    <i class="fas fa-user-edit"></i>
                    My Posts
                    <span class="ios-filter-badge"><?php echo $stats['my_posts']; ?></span>
                </a>
            </div>
        </div>

        <!-- Announcements Grid -->
        <?php if (empty($announcements)): ?>
            <div class="ios-empty-state">
                <div class="ios-empty-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <h5 class="ios-empty-title">No Announcements Yet</h5>
                <p class="ios-empty-text">Be the first to create an announcement for your estate</p>
                <a href="<?php echo BASE_URL; ?>announcements/create.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create Announcement
                </a>
            </div>
        <?php else: ?>
            <div class="ios-announcements-grid">
                <?php foreach ($announcements as $announcement): ?>
                    <div class="ios-announcement-card <?php echo $announcement['is_pinned'] ? 'pinned' : ''; ?> <?php echo $announcement['priority'] === 'urgent' ? 'urgent' : ''; ?>">
                        <!-- Card Header -->
                        <div class="ios-card-header">
                            <div class="ios-author-info">
                                <div class="ios-author-avatar">
                                    <?php echo strtoupper(substr($announcement['author_name'], 0, 2)); ?>
                                </div>
                                <div class="ios-author-details">
                                    <h6><?php echo htmlspecialchars($announcement['author_name']); ?></h6>
                                    <div class="ios-author-meta">
                                        <span class="ios-role-badge <?php echo strtolower(str_replace('_', '-', $announcement['author_role'])); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $announcement['author_role'])); ?>
                                        </span>
                                        <span>•</span>
                                        <span><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <?php if ($announcement['is_pinned']): ?>
                                    <div class="ios-pin-badge">
                                        <i class="fas fa-thumbtack"></i> Pinned
                                    </div>
                                <?php elseif ($announcement['priority'] !== 'normal'): ?>
                                    <div class="ios-priority-badge <?php echo $announcement['priority']; ?>">
                                        <?php echo ucfirst($announcement['priority']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Image (if exists) -->
                        <?php if ($announcement['image_path']): ?>
                            <img src="<?php echo BASE_URL . $announcement['image_path']; ?>"
                                alt="<?php echo htmlspecialchars($announcement['title']); ?>"
                                class="ios-card-image">
                        <?php endif; ?>

                        <!-- Card Body -->
                        <div class="ios-card-body">
                            <h3 class="ios-card-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                            <p class="ios-card-content"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        </div>

                        <!-- Card Footer -->
                        <div class="ios-card-footer">
                            <div class="ios-card-actions">
                                <button class="ios-action-btn reaction-btn <?php echo $announcement['user_reacted'] ? 'active' : ''; ?>"
                                    data-announcement-id="<?php echo encryptId($announcement['id']); ?>">
                                    <i class="fas fa-heart"></i>
                                    <span class="reaction-count"><?php echo $announcement['reaction_count']; ?></span>
                                </button>
                                <button class="ios-action-btn"
                                    onclick="viewAnnouncement('<?php echo encryptId($announcement['id']); ?>')">
                                    <i class="fas fa-comment"></i>
                                    <span><?php echo $announcement['comment_count']; ?></span>
                                </button>
                                <button class="ios-action-btn">
                                    <i class="fas fa-eye"></i>
                                    <span><?php echo $announcement['views']; ?></span>
                                </button>
                            </div>
                            <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo encryptId($announcement['id']); ?>"
                                class="ios-read-more-btn">
                                Read More
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="ios-pagination">
                    <div class="ios-pagination-info">
                        Showing <?php echo (($page - 1) * $perPage) + 1; ?>-<?php echo min($page * $perPage, $totalAnnouncements); ?> of <?php echo $totalAnnouncements; ?>
                    </div>
                    <div class="ios-pagination-nav">
                        <button class="ios-page-btn"
                            onclick="changePage(<?php echo $page - 1; ?>)"
                            <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-left"></i>
                        </button>

                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == 1 || $i == $totalPages || abs($i - $page) <= 2): ?>
                                <button class="ios-page-btn <?php echo $i == $page ? 'active' : ''; ?>"
                                    onclick="changePage(<?php echo $i; ?>)">
                                    <?php echo $i; ?>
                                </button>
                            <?php elseif (abs($i - $page) == 3): ?>
                                <span style="padding: 0 8px; color: var(--text-muted);">...</span>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <button class="ios-page-btn"
                            onclick="changePage(<?php echo $page + 1; ?>)"
                            <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- iOS-Style Mobile Menu Modal -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Announcements</h3>
        <button class="ios-menu-close" id="iosMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="ios-menu-content">
        <!-- Quick Actions Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>announcements/create.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">New Announcement</span>
                            <span class="ios-menu-item-desc">Create a new announcement</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <div class="ios-menu-item" onclick="location.reload()">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Refresh</span>
                            <span class="ios-menu-item-desc">Reload announcements</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter Announcements</div>
            <div class="ios-menu-card">
                <a href="?filter=all" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <span class="ios-menu-item-label">All Announcements</span>
                    </div>
                    <?php if ($filter === 'all'): ?>
                        <i class="fas fa-check ios-menu-check"></i>
                    <?php endif; ?>
                </a>
                <a href="?filter=pinned" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <span class="ios-menu-item-label">Pinned</span>
                    </div>
                    <?php if ($filter === 'pinned'): ?>
                        <i class="fas fa-check ios-menu-check"></i>
                    <?php endif; ?>
                </a>
                <a href="?filter=urgent" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <span class="ios-menu-item-label">Urgent</span>
                    </div>
                    <?php if ($filter === 'urgent'): ?>
                        <i class="fas fa-check ios-menu-check"></i>
                    <?php endif; ?>
                </a>
                <a href="?filter=my-posts" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <span class="ios-menu-item-label">My Posts</span>
                    </div>
                    <?php if ($filter === 'my-posts'): ?>
                        <i class="fas fa-check ios-menu-check"></i>
                    <?php endif; ?>
                </a>
            </div>
        </div>

        <!-- Navigation Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Navigation</div>
            <div class="ios-menu-card">
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
                <a href="<?php echo BASE_URL; ?>notifications/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Notifications</span>
                            <span class="ios-menu-item-desc">View all notifications</span>
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
    console.log('Initializing iOS Announcements Page...');

    // Initialize iOS Menu
    initializeIosMenu();

    // Initialize Reaction Buttons
    initializeReactions();

    // Mark announcements as read
    markAnnouncementsRead();
});

function initializeIosMenu() {
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

function initializeReactions() {
    document.querySelectorAll('.reaction-btn').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.stopPropagation();
            const announcementId = this.dataset.announcementId;
            const countSpan = this.querySelector('.reaction-count');
            const isActive = this.classList.contains('active');

            try {
                const response = await fetch('<?php echo BASE_URL; ?>announcements/toggle-reaction.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        announcement_id: announcementId,
                        action: isActive ? 'remove' : 'add'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    this.classList.toggle('active');
                    countSpan.textContent = result.count;
                }
            } catch (error) {
                console.error('Error toggling reaction:', error);
            }
        });
    });
}

// Pagination
function changePage(page) {
    const url = new URL(window.location.href);
    url.searchParams.set('page', page);
    window.location.href = url.toString();
}

// View announcement
function viewAnnouncement(id) {
    window.location.href = '<?php echo BASE_URL; ?>announcements/view.php?id=' + id;
}

// Mark announcements as read
function markAnnouncementsRead() {
    // Find dot with multiple selectors
    const dot = document.querySelector('.announcement-dot') ||
                document.querySelector('[class*="announcement-dot"]') ||
                document.querySelector('a[href*="announcements"] .announcement-dot');

    if (dot) {
        dot.style.opacity = '0';
    }

    fetch('<?php echo BASE_URL; ?>announcements/mark-announcements-read.php', {
        method: 'POST',
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && dot) {
            dot.remove();
        }
    })
    .catch(error => {
        console.error('Error marking announcements as read:', error);
        if (dot) dot.remove();
    });
}
</script>

<?php include_once '../includes/footer.php'; ?>