<?php

/**
 * GateWey - View Announcement Page
 * Displays full announcement details with comments
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';

// Set page title and enable charts
$pageTitle = 'View Announcement';
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
$clanId = $currentUser->getClanId();

// Get announcement ID
if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . 'announcements/');
    exit;
}

$announcementId = decryptId($_GET['id']);
if (!$announcementId || !is_numeric($announcementId) || $announcementId <= 0) {
    header('Location: ' . BASE_URL . 'announcements/');
    exit;
}

// Get announcement details
$announcement = $db->fetchOne(
    "SELECT a.*, 
     COUNT(DISTINCT ar.id) as reaction_count,
     COUNT(DISTINCT ac.id) as comment_count,
     EXISTS(SELECT 1 FROM announcement_reactions ar2 
            WHERE ar2.announcement_id = a.id 
            AND ar2.user_id = ?) as user_reacted
     FROM announcements a
     LEFT JOIN announcement_reactions ar ON a.id = ar.announcement_id
     LEFT JOIN announcement_comments ac ON a.id = ac.announcement_id
     WHERE a.id = ? AND a.clan_id = ?
     GROUP BY a.id",
    [$currentUser->getId(), $announcementId, $clanId]
);

if (!$announcement) {
    header('Location: ' . BASE_URL . 'announcements/');
    exit;
}

// Increment view count
$db->query("UPDATE announcements SET views = views + 1 WHERE id = ?", [$announcementId]);

// Get comments
$comments = $db->fetchAll(
    "SELECT * FROM announcement_comments 
     WHERE announcement_id = ? 
     ORDER BY created_at DESC",
    [$announcementId]
);

// Handle comment submission
$commentSuccess = '';
$commentError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment'])) {
    $comment = trim($_POST['comment']);

    if (empty($comment)) {
        $commentError = 'Comment cannot be empty';
    } else {
        try {
            $db->query(
                "INSERT INTO announcement_comments (announcement_id, user_id, user_name, comment, created_at) 
                 VALUES (?, ?, ?, ?, NOW())",
                [$announcementId, $currentUser->getId(), $currentUser->getFullName(), $comment]
            );

            $commentSuccess = 'Comment added successfully';

            // Refresh comments
            $comments = $db->fetchAll(
                "SELECT * FROM announcement_comments 
                 WHERE announcement_id = ? 
                 ORDER BY created_at DESC",
                [$announcementId]
            );

            // Clear comment field
            $_POST['comment'] = '';
        } catch (Exception $e) {
            $commentError = 'Failed to add comment';
            error_log("Comment error: " . $e->getMessage());
        }
    }
}

include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>

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

    .ios-section-icon.orange {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
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
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
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

    /* Content Layout */
    .ios-content-layout {
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: var(--spacing-4);
        padding: var(--spacing-4);
    }

    @media (max-width: 992px) {
        .ios-content-layout {
            grid-template-columns: 1fr;
        }
    }

    /* Main Content */
    .ios-main-content {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-4);
    }

    /* iOS Detail Card */
    .ios-detail-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        overflow: hidden;
    }

    .ios-detail-card.pinned {
        border-color: var(--ios-orange);
        border-width: 2px;
    }

    .ios-detail-card.urgent {
        border-color: var(--ios-red);
        border-width: 2px;
    }

    /* Detail Header */
    .ios-detail-header {
        padding: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-detail-header-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 14px;
    }

    .ios-author-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .ios-author-avatar {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: var(--ios-blue);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 16px;
    }

    .ios-author-details h6 {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }

    .ios-author-meta {
        font-size: 13px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ios-role-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
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

    /* Badges */
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

    @keyframes pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.2); }
    }

    .ios-priority-badge.urgent::before {
        animation: pulse-dot 2s ease-in-out infinite;
    }

    .ios-detail-title {
        font-size: 22px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        line-height: 1.3;
    }

    /* Detail Image */
    .ios-detail-image {
        width: 100%;
        max-height: 450px;
        object-fit: cover;
    }

    /* Detail Body */
    .ios-detail-body {
        padding: 20px 16px;
    }

    .ios-detail-content {
        font-size: 15px;
        color: var(--text-primary);
        line-height: 1.7;
        white-space: pre-wrap;
    }

    /* Detail Footer */
    .ios-detail-footer {
        padding: 14px 16px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }

    .ios-detail-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .ios-detail-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        padding: 10px 16px;
        border-radius: 10px;
        color: var(--text-primary);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .ios-detail-btn:hover {
        background: var(--bg-hover);
        border-color: var(--ios-blue);
        color: var(--ios-blue);
    }

    .ios-detail-btn.active {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
        color: white;
    }

    .ios-detail-btn i {
        font-size: 14px;
    }

    /* Comments Section */
    .ios-comments-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        overflow: hidden;
    }

    .ios-comments-header {
        padding: 16px;
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ios-comments-header i {
        color: var(--ios-blue);
        font-size: 16px;
    }

    .ios-comments-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .ios-comments-body {
        padding: 16px;
    }

    /* Comment Form */
    .ios-comment-form {
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-comment-form textarea {
        width: 100%;
        min-height: 90px;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-input);
        color: var(--text-primary);
        font-size: 14px;
        resize: vertical;
        transition: all 0.2s ease;
    }

    .ios-comment-form textarea:focus {
        border-color: var(--ios-blue);
        outline: none;
        box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.15);
    }

    .ios-comment-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 12px;
    }

    .ios-submit-btn {
        padding: 10px 20px;
        background: var(--ios-blue);
        border: none;
        border-radius: 10px;
        color: white;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.15s ease;
    }

    .ios-submit-btn:hover {
        background: #0070e0;
    }

    /* Comment List */
    .ios-comment-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .ios-comment-item {
        display: flex;
        gap: 12px;
        padding: 14px;
        background: var(--bg-subtle);
        border-radius: 12px;
    }

    .ios-comment-avatar {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: var(--ios-blue);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 13px;
        flex-shrink: 0;
    }

    .ios-comment-content {
        flex: 1;
    }

    .ios-comment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .ios-comment-author {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
    }

    .ios-comment-time {
        font-size: 12px;
        color: var(--text-muted);
    }

    .ios-comment-text {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.5;
        white-space: pre-wrap;
        margin: 0;
    }

    /* Sidebar */
    .ios-sidebar {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-4);
    }

    .ios-sidebar-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 14px;
        overflow: hidden;
    }

    .ios-sidebar-header {
        padding: 14px 16px;
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
    }

    .ios-sidebar-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .ios-sidebar-body {
        padding: 0;
    }

    .ios-sidebar-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-sidebar-item:last-child {
        border-bottom: none;
    }

    .ios-sidebar-label {
        font-size: 14px;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ios-sidebar-label i {
        width: 16px;
        text-align: center;
    }

    .ios-sidebar-value {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
    }

    /* Action Buttons */
    .ios-action-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-primary);
        text-decoration: none;
        transition: background 0.15s ease;
    }

    .ios-action-link:last-child {
        border-bottom: none;
    }

    .ios-action-link:hover {
        background: var(--bg-subtle);
    }

    .ios-action-link.danger {
        color: var(--ios-red);
    }

    .ios-action-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .ios-action-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-action-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }

    .ios-action-text {
        flex: 1;
        font-size: 15px;
        font-weight: 500;
    }

    .ios-action-chevron {
        color: var(--text-muted);
        font-size: 12px;
    }

    /* Alerts */
    .ios-alert {
        display: flex;
        align-items: flex-start;
        padding: 14px;
        border-radius: 12px;
        margin-bottom: 16px;
        gap: 12px;
    }

    .ios-alert i {
        font-size: 18px;
        flex-shrink: 0;
        margin-top: 2px;
    }

    .ios-alert-success {
        background: rgba(48, 209, 88, 0.12);
        border: 1px solid rgba(48, 209, 88, 0.2);
        color: var(--ios-green);
    }

    .ios-alert-danger {
        background: rgba(255, 69, 58, 0.12);
        border: 1px solid rgba(255, 69, 58, 0.2);
        color: var(--ios-red);
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

    .ios-menu-item.danger {
        color: var(--ios-red);
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

    /* Empty Comment State */
    .ios-empty-comments {
        text-align: center;
        padding: 24px;
        color: var(--text-muted);
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

        .ios-content-layout {
            padding: var(--spacing-3);
            gap: var(--spacing-3);
        }

        .ios-detail-title {
            font-size: 18px;
        }

        .ios-detail-actions {
            width: 100%;
        }

        .ios-detail-footer {
            flex-direction: column;
            align-items: stretch;
        }

        .ios-detail-btn {
            justify-content: center;
        }

        .ios-sidebar {
            margin-top: 0;
        }

        /* Hide Actions and Statistics cards on mobile */
        .ios-sidebar-card.ios-actions-card,
        .ios-sidebar-card.ios-stats-card {
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
                    <i class="fas fa-bullhorn me-2"></i><?php echo htmlspecialchars($announcement['title']); ?>
                </h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>announcements/" class="breadcrumb-link">Announcements</a>
                        </li>
                        <li class="breadcrumb-item active">View</li>
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
            <div class="ios-section-icon orange">
                <i class="fas fa-bullhorn"></i>
            </div>
            <div class="ios-section-title">
                <h5><?php echo htmlspecialchars($announcement['title']); ?></h5>
                <p>Posted by <?php echo htmlspecialchars($announcement['author_name']); ?></p>
            </div>
            <!-- 3-Dot Menu Button -->
            <button class="ios-options-btn" id="iosOptionsBtn" aria-label="Open menu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Content Layout -->
        <div class="ios-content-layout">
            <!-- Main Content -->
            <div class="ios-main-content">
                <!-- Announcement Detail Card -->
                <div class="ios-detail-card <?php echo $announcement['is_pinned'] ? 'pinned' : ''; ?> <?php echo $announcement['priority'] === 'urgent' ? 'urgent' : ''; ?>">
                    <div class="ios-detail-header">
                        <div class="ios-detail-header-top">
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
                        <h2 class="ios-detail-title"><?php echo htmlspecialchars($announcement['title']); ?></h2>
                    </div>

                    <?php if ($announcement['image_path']): ?>
                        <img src="<?php echo BASE_URL . $announcement['image_path']; ?>"
                            alt="<?php echo htmlspecialchars($announcement['title']); ?>"
                            class="ios-detail-image">
                    <?php endif; ?>

                    <div class="ios-detail-body">
                        <div class="ios-detail-content"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></div>
                    </div>

                    <div class="ios-detail-footer">
                        <div class="ios-detail-actions">
                            <button class="ios-detail-btn reaction-btn <?php echo $announcement['user_reacted'] ? 'active' : ''; ?>"
                                data-announcement-id="<?php echo encryptId($announcement['id']); ?>">
                                <i class="fas fa-heart"></i>
                                <span class="reaction-count"><?php echo $announcement['reaction_count']; ?></span>
                            </button>
                            <button class="ios-detail-btn">
                                <i class="fas fa-comment"></i>
                                <?php echo $announcement['comment_count']; ?>
                            </button>
                            <button class="ios-detail-btn">
                                <i class="fas fa-eye"></i>
                                <?php echo $announcement['views']; ?>
                            </button>
                        </div>
                        <button class="ios-detail-btn" onclick="shareAnnouncement()">
                            <i class="fas fa-share"></i> Share
                        </button>
                    </div>
                </div>

                <!-- Comments Section -->
                <div class="ios-comments-card">
                    <div class="ios-comments-header">
                        <i class="fas fa-comments"></i>
                        <h3 class="ios-comments-title">Comments (<?php echo count($comments); ?>)</h3>
                    </div>
                    <div class="ios-comments-body">
                        <?php if ($commentSuccess): ?>
                            <div class="ios-alert ios-alert-success">
                                <i class="fas fa-check-circle"></i>
                                <span><?php echo $commentSuccess; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($commentError): ?>
                            <div class="ios-alert ios-alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo $commentError; ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Comment Form -->
                        <form method="POST" class="ios-comment-form">
                            <textarea name="comment"
                                placeholder="Add a comment..."
                                required><?php echo htmlspecialchars($_POST['comment'] ?? ''); ?></textarea>
                            <div class="ios-comment-actions">
                                <button type="submit" class="ios-submit-btn">
                                    <i class="fas fa-paper-plane"></i>
                                    Post Comment
                                </button>
                            </div>
                        </form>

                        <!-- Comment List -->
                        <?php if (empty($comments)): ?>
                            <div class="ios-empty-comments">
                                <p>No comments yet. Be the first to comment!</p>
                            </div>
                        <?php else: ?>
                            <div class="ios-comment-list">
                                <?php foreach ($comments as $comment): ?>
                                    <div class="ios-comment-item">
                                        <div class="ios-comment-avatar">
                                            <?php echo strtoupper(substr($comment['user_name'], 0, 2)); ?>
                                        </div>
                                        <div class="ios-comment-content">
                                            <div class="ios-comment-header">
                                                <span class="ios-comment-author"><?php echo htmlspecialchars($comment['user_name']); ?></span>
                                                <span class="ios-comment-time"><?php echo date('M j, Y \a\t g:i A', strtotime($comment['created_at'])); ?></span>
                                            </div>
                                            <p class="ios-comment-text"><?php echo nl2br(htmlspecialchars($comment['comment'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="ios-sidebar">
                <?php
                // Check permissions
                $canEdit = ($announcement['author_id'] === $currentUser->getId());
                $canDelete = ($announcement['author_id'] === $currentUser->getId() || in_array($currentUser->getRole(), ['super_admin', 'clan_admin']));

                // Show actions card if user has any permission
                if ($canEdit || $canDelete):
                ?>
                <!-- Actions Card (Hidden on Mobile) -->
                <div class="ios-sidebar-card ios-actions-card">
                    <div class="ios-sidebar-header">
                        <h3 class="ios-sidebar-title">Actions</h3>
                    </div>
                    <div class="ios-sidebar-body">
                        <?php if ($canEdit): ?>
                            <a href="<?php echo BASE_URL; ?>announcements/edit.php?id=<?php echo encryptId($announcementId); ?>" class="ios-action-link">
                                <div class="ios-action-icon blue">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <span class="ios-action-text">Edit Announcement</span>
                                <i class="fas fa-chevron-right ios-action-chevron"></i>
                            </a>
                        <?php endif; ?>

                        <?php if ($canDelete): ?>
                            <div class="ios-action-link danger" onclick="confirmDelete()" style="cursor: pointer;">
                                <div class="ios-action-icon red">
                                    <i class="fas fa-trash"></i>
                                </div>
                                <span class="ios-action-text">Delete Announcement</span>
                                <i class="fas fa-chevron-right ios-action-chevron"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats Card (Hidden on Mobile) -->
                <div class="ios-sidebar-card ios-stats-card">
                    <div class="ios-sidebar-header">
                        <h3 class="ios-sidebar-title">Statistics</h3>
                    </div>
                    <div class="ios-sidebar-body">
                        <div class="ios-sidebar-item">
                            <span class="ios-sidebar-label"><i class="fas fa-eye"></i> Views</span>
                            <span class="ios-sidebar-value"><?php echo number_format($announcement['views'] + 1); ?></span>
                        </div>
                        <div class="ios-sidebar-item">
                            <span class="ios-sidebar-label"><i class="fas fa-heart"></i> Reactions</span>
                            <span class="ios-sidebar-value"><?php echo number_format($announcement['reaction_count']); ?></span>
                        </div>
                        <div class="ios-sidebar-item">
                            <span class="ios-sidebar-label"><i class="fas fa-comment"></i> Comments</span>
                            <span class="ios-sidebar-value"><?php echo number_format($announcement['comment_count']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Details Card -->
                <div class="ios-sidebar-card ios-stats-card">
                    <div class="ios-sidebar-header">
                        <h3 class="ios-sidebar-title">Details</h3>
                    </div>
                    <div class="ios-sidebar-body">
                        <div class="ios-sidebar-item">
                            <span class="ios-sidebar-label">Priority</span>
                            <span class="ios-priority-badge <?php echo $announcement['priority']; ?>">
                                <?php echo ucfirst($announcement['priority']); ?>
                            </span>
                        </div>
                        <div class="ios-sidebar-item">
                            <span class="ios-sidebar-label">Posted</span>
                            <span class="ios-sidebar-value"><?php echo date('M j, Y', strtotime($announcement['created_at'])); ?></span>
                        </div>
                        <?php if ($announcement['updated_at'] !== $announcement['created_at']): ?>
                            <div class="ios-sidebar-item">
                                <span class="ios-sidebar-label">Updated</span>
                                <span class="ios-sidebar-value"><?php echo date('M j, Y', strtotime($announcement['updated_at'])); ?></span>
                            </div>
                        <?php endif; ?>
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
                <div class="ios-menu-item" onclick="shareAnnouncement(); closeIosMenu();">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-share"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Share</span>
                            <span class="ios-menu-item-desc">Share this announcement</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <?php if ($canEdit): ?>
                <a href="<?php echo BASE_URL; ?>announcements/edit.php?id=<?php echo encryptId($announcementId); ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-edit"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Edit</span>
                            <span class="ios-menu-item-desc">Edit this announcement</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php endif; ?>
                <?php if ($canDelete): ?>
                <div class="ios-menu-item danger" onclick="closeIosMenu(); confirmDelete();">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon red">
                            <i class="fas fa-trash"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Delete</span>
                            <span class="ios-menu-item-desc">Delete this announcement</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Details Section -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Details</div>
            <div class="ios-menu-card">
                <div class="ios-menu-item" style="cursor: default;">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon <?php
                            $priorityClass = 'blue';
                            if ($announcement['priority'] === 'urgent') $priorityClass = 'red';
                            elseif ($announcement['priority'] === 'high') $priorityClass = 'orange';
                            echo $priorityClass;
                        ?>">
                            <i class="fas fa-flag"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Priority</span>
                            <span class="ios-menu-item-desc"><?php echo ucfirst($announcement['priority']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="ios-menu-item" style="cursor: default;">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Posted</span>
                            <span class="ios-menu-item-desc"><?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php if ($announcement['updated_at'] !== $announcement['created_at']): ?>
                <div class="ios-menu-item" style="cursor: default;">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Updated</span>
                            <span class="ios-menu-item-desc"><?php echo date('M j, Y g:i A', strtotime($announcement['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="ios-menu-item" style="cursor: default;">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Author</span>
                            <span class="ios-menu-item-desc"><?php echo htmlspecialchars($announcement['author_name']); ?></span>
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
    console.log('Initializing iOS View Announcement Page...');
    initializeIosMenu();
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

// Reaction functionality
document.querySelector('.reaction-btn')?.addEventListener('click', async function() {
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

// Share functionality
function shareAnnouncement() {
    const url = window.location.href;
    const title = <?php echo json_encode($announcement['title']); ?>;

    if (navigator.share) {
        navigator.share({
            title: title,
            url: url
        }).catch(err => console.log('Error sharing:', err));
    } else {
        // Fallback: Copy to clipboard
        navigator.clipboard.writeText(url).then(() => {
            if (typeof showFlashNotification === 'function') {
                showFlashNotification('Link copied to clipboard!', 'success');
            } else {
                alert('Link copied to clipboard!');
            }
        });
    }
}

// Delete confirmation
async function confirmDelete() {
    if (!confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
        return;
    }

    const announcementId = <?php echo $announcementId; ?>;

    try {
        const response = await fetch('<?php echo BASE_URL; ?>announcements/delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                announcement_id: announcementId
            })
        });

        const result = await response.json();

        if (result.success) {
            // Show flash notification and redirect
            if (typeof showFlashNotification === 'function') {
                showFlashNotification(result.message, 'success');
                setTimeout(() => {
                    window.location.href = '<?php echo BASE_URL; ?>announcements/';
                }, 1500);
            } else {
                alert(result.message);
                window.location.href = '<?php echo BASE_URL; ?>announcements/';
            }
        } else {
            if (typeof showFlashNotification === 'function') {
                showFlashNotification('Error: ' + result.message, 'error');
            } else {
                alert('Error: ' + result.message);
            }
        }
    } catch (error) {
        console.error('Error deleting announcement:', error);
        if (typeof showFlashNotification === 'function') {
            showFlashNotification('An error occurred while deleting the announcement', 'error');
        } else {
            alert('An error occurred while deleting the announcement');
        }
    }
}
</script>

<?php include_once '../includes/footer.php'; ?>