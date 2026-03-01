<?php
/**
 * Document actions — edit, delete
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Document.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('documents/manage.php'); }
verifyCsrf();

$action = sanitize($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? 0);
if (!$id) { flashError('Invalid document.'); redirect('documents/manage.php'); }

$docObj = new Document();

switch ($action) {
    case 'edit':
        $title    = sanitize($_POST['title']    ?? '');
        $category = sanitize($_POST['category'] ?? '');
        if (!$title) { flashError('Title is required.'); redirect('documents/manage.php'); }
        $docObj->update($id, $title, $category);
        flashSuccess('Document updated.');
        break;

    case 'delete':
        $filePath = $docObj->delete($id);
        if ($filePath) {
            // Convert URL back to filesystem path
            $relativePath = str_replace(UPLOAD_URL, '', $filePath);
            $absPath      = UPLOAD_PATH . $relativePath;
            if (file_exists($absPath)) @unlink($absPath);
        }
        flashSuccess('Document deleted.');
        break;

    default:
        flashError('Unknown action.');
}

redirect('documents/manage.php');
