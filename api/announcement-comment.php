<?php
/**
 * API — Add / Delete announcement comment
 * POST: announcement_id, content, [parent_id]  → add comment, returns {success, html}
 * DELETE body: comment_id                        → delete comment, returns {success}
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$annObj = new Announcement();
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// ─── DELETE ───────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $input);
    $commentId = (int)($input['comment_id'] ?? 0);

    if (!$commentId) {
        echo json_encode(['success' => false, 'message' => 'Invalid comment.']);
        exit;
    }

    $comment = $annObj->getComment($commentId);
    if (!$comment) {
        echo json_encode(['success' => false, 'message' => 'Comment not found.']);
        exit;
    }

    // Only owner or admin may delete
    if (!isAdmin() && (int)$comment['user_id'] !== $userId) {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }

    $annObj->deleteComment($commentId);
    echo json_encode(['success' => true]);
    exit;
}

// ─── POST ─────────────────────────────────────────────────────────────────
if ($method !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$annId    = (int)($_POST['announcement_id'] ?? 0);
$content  = trim($_POST['content'] ?? '');
$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

if (!$annId || !$content) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

if (mb_strlen($content) > 2000) {
    echo json_encode(['success' => false, 'message' => 'Comment is too long (max 2000 characters).']);
    exit;
}

$ann = $annObj->getById($annId);
if (!$ann || !$ann['is_published']) {
    echo json_encode(['success' => false, 'message' => 'Announcement not found.']);
    exit;
}

$newId = $annObj->addComment($annId, $userId, $content, $parentId);

// Build rendered HTML for the new comment
$db      = Database::getInstance();
$userRow = $db->fetchOne(
    "SELECT CONCAT(m.first_name,' ',m.last_name) AS author_name, u.email AS author_email
     FROM users u LEFT JOIN members m ON m.user_id = u.id
     WHERE u.id=?",
    [$userId]
);
$authorName  = $userRow['author_name'] ?: ($userRow['author_email'] ?? 'Member');
$initial     = strtoupper(substr($authorName, 0, 1));

$now         = date('d M Y, g:i A');
$safeContent = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));
$commentId   = $newId;
$colors      = ['#0A84FF','#30D158','#FF9F0A','#BF5AF2','#FF453A'];
$color       = $colors[ord($initial) % count($colors)];
$safeName    = htmlspecialchars($authorName, ENT_QUOTES);
$safeInitial = htmlspecialchars($initial, ENT_QUOTES);

$html  = "<div class=\"ios-comment-item\" id=\"comment-{$commentId}\">";
$html .= "<div class=\"ios-comment-item-avatar\" style=\"background:{$color};color:#fff\">{$safeInitial}</div>";
$html .= "<div class=\"ios-comment-body\">";
$html .= "<div class=\"ios-comment-meta\"><strong>{$safeName}</strong> {$now}</div>";
$html .= "<div class=\"ios-comment-text\">{$safeContent}</div>";
$html .= "<div class=\"ios-comment-actions\">";
if (!$parentId) {
    $html .= "<a class=\"reply-link\" data-id=\"{$commentId}\">↩ Reply</a>";
}
$html .= "<a class=\"del-link\" onclick=\"deleteComment({$commentId})\">Delete</a>";
$html .= "</div></div></div>";

echo json_encode(['success' => true, 'html' => $html, 'id' => $newId]);
