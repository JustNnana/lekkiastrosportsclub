<?php
/**
 * Paystack Callback — handles redirect after checkout
 * Paystack sends ?reference=xxx here after the user pays (or closes the page).
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireLogin();

$ref = sanitize($_GET['reference'] ?? '');

if (!$ref) {
    flashError('No payment reference received.');
    redirect('payments/my-payments.php');
}

$payObj = new Payment();
$result = $payObj->verifyAndMarkPaid($ref);

if ($result['success']) {
    $payment = $result['payment'];
    flashSuccess("Payment successful! <strong>{$payment['due_title']}</strong> — " . formatCurrency((float)$payment['amount'] + (float)$payment['penalty_applied']) . ' paid.');
    redirect('payments/receipt.php?id=' . $payment['id']);
} else {
    flashError('Payment verification failed: ' . $result['message'] . ' Please contact support if you were charged.');
    redirect('payments/my-payments.php');
}
