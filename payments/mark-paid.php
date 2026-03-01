<?php
/**
 * Mark Payment as Paid (Manual) — Admin POST handler
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('payments/'); }
verifyCsrf();

$id    = (int)($_POST['id'] ?? 0);
$notes = sanitize($_POST['notes'] ?? '');

if (!$id) { flashError('Invalid request.'); redirect('payments/'); }

$payObj  = new Payment();
$payment = $payObj->getById($id);

if (!$payment) { flashError('Payment not found.'); redirect('payments/'); }

if (!in_array($payment['status'], ['pending', 'overdue'])) {
    flashError('Only pending or overdue payments can be marked as paid.');
    redirect('payments/');
}

if ($payObj->markPaid($id, 'manual', null, $notes ?: null)) {
    flashSuccess("Payment for <strong>{$payment['full_name']}</strong> ({$payment['due_title']}) marked as paid.");
} else {
    flashError('Failed to update payment. Please try again.');
}

redirect('payments/');
