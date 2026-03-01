<?php
/**
 * Notifications — actions (clear all)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('notifications/index.php');
}

verifyCsrf();

$userId = (int)$_SESSION['user_id'];
$action = sanitize($_POST['action'] ?? '');

if ($action === 'clear_all') {
    $db = Database::getInstance();
    $db->execute("DELETE FROM notifications WHERE user_id = ?", [$userId]);
    flashSuccess('All notifications cleared.');
}

redirect('notifications/index.php');
