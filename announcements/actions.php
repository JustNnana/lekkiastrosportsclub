<?php
/**
 * Announcement actions — POST handler
 * Actions: publish, unpublish, toggle_pin, delete
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Announcement.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('announcements/manage.php'); }
verifyCsrf();

$action = sanitize($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? 0);

if (!$id) { flashError('Invalid announcement.'); redirect('announcements/manage.php'); }

$annObj = new Announcement();
$ann    = $annObj->getById($id);

if (!$ann) { flashError('Announcement not found.'); redirect('announcements/manage.php'); }

switch ($action) {
    case 'publish':
        $annObj->publish($id);
        flashSuccess('Announcement published.');
        break;

    case 'unpublish':
        $annObj->unpublish($id);
        flashSuccess('Announcement unpublished (saved as draft).');
        break;

    case 'toggle_pin':
        $annObj->togglePin($id);
        $pinned = !$ann['is_pinned'];
        flashSuccess($pinned ? 'Announcement pinned.' : 'Announcement unpinned.');
        break;

    case 'delete':
        // Delete associated image from disk
        if ($ann['image_path']) {
            $filename = basename($ann['image_path']);
            $path     = UPLOAD_PATH . $filename;
            if (file_exists($path)) @unlink($path);
        }
        $annObj->delete($id);
        flashSuccess('Announcement deleted.');
        break;

    default:
        flashError('Unknown action.');
}

redirect('announcements/manage.php');
