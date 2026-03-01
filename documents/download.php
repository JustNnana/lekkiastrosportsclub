<?php
/**
 * Document download — tracked, serves file through PHP.
 * Prevents direct URL access and increments download counter.
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Document.php';

requireLogin();

$id     = (int)($_GET['id'] ?? 0);
$docObj = new Document();
$doc    = $docObj->getById($id);

if (!$doc) {
    flashError('Document not found.');
    redirect('documents/index.php');
}

// Convert stored URL back to filesystem path
$relativePath = str_replace(UPLOAD_URL, '', $doc['file_path']);
$absPath      = UPLOAD_PATH . $relativePath;

if (!file_exists($absPath)) {
    flashError('File not found on server. Please contact an administrator.');
    redirect('documents/index.php');
}

// Increment download counter
$docObj->incrementDownloads($id);

// Serve file
$mime     = $doc['mime_type'] ?? 'application/octet-stream';
$filename = basename($doc['title']) . '.' . pathinfo($absPath, PATHINFO_EXTENSION);

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
header('Content-Length: ' . filesize($absPath));
header('Cache-Control: private, no-cache');
header('X-Content-Type-Options: nosniff');
readfile($absPath);
exit;
