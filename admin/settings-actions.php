<?php
/**
 * Settings actions — Super Admin only
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireSuperAdmin();
verifyCsrf();

$db     = Database::getInstance();
$action = $_POST['action'] ?? '';

if ($action === 'save_settings') {
    $fields = [
        'club_name', 'club_tagline', 'club_email',
        'club_phone', 'club_address', 'registration_open',
    ];

    // Checkbox: only present in POST when checked
    $_POST['registration_open'] = isset($_POST['registration_open']) ? '1' : '0';

    foreach ($fields as $key) {
        $value = sanitize($_POST[$key] ?? '');
        $db->execute(
            "INSERT INTO settings (`key`, `value`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `value` = ?",
            [$key, $value, $value]
        );
    }

    flashSuccess('Settings saved successfully.');
}

redirect('admin/settings.php');
