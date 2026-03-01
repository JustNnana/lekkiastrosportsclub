<?php
/**
 * Reverse a Payment — Super Admin POST handler
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('payments/'); }
verifyCsrf();

$id    = (int)($_POST['id']    ?? 0);
$notes = sanitize($_POST['notes'] ?? '');

if (!$id || empty($notes)) {
    flashError('Payment ID and a reversal reason are required.');
    redirect('payments/');
}

$payObj  = new Payment();
$payment = $payObj->getById($id);

if (!$payment) { flashError('Payment not found.'); redirect('payments/'); }

if ($payment['status'] !== 'paid') {
    flashError('Only paid payments can be reversed.');
    redirect('payments/');
}

if ($payObj->reverse($id, $notes)) {
    flashSuccess("Payment for <strong>{$payment['full_name']}</strong> has been reversed.");
} else {
    flashError('Failed to reverse payment. Please try again.');
}

redirect('payments/');
