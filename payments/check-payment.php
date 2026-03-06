<?php
/**
 * Poll endpoint — callback.php checks this every 3s to see if webhook has confirmed payment.
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

header('Content-Type: application/json');

requireLogin();

$ref = sanitize($_GET['ref'] ?? '');
if (!$ref) {
    exit(json_encode(['paid' => false]));
}

$db  = Database::getInstance();
$row = $db->fetchOne("SELECT status FROM payments WHERE paystack_ref = ?", [$ref]);

echo json_encode(['paid' => ($row && $row['status'] === 'paid')]);
