<?php
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('admin/admins.php'); }
verifyCsrf();

$action = sanitize($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? 0);

if (!$id || $id === $_SESSION['user_id']) {
    flashError('Invalid request.');
    redirect('admin/admins.php');
}

$db   = Database::getInstance();
$user = $db->fetchOne("SELECT id, full_name, role FROM users WHERE id = ?", [$id]);

if (!$user || $user['role'] === 'super_admin') {
    flashError('Cannot modify this account.');
    redirect('admin/admins.php');
}

if ($action === 'revoke_admin') {
    $db->execute("UPDATE users SET role = 'user' WHERE id = ?", [$id]);
    flashSuccess("Admin access revoked from <strong>{$user['full_name']}</strong>.");
}

redirect('admin/admins.php');
