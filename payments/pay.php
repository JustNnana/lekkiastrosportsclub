<?php
/**
 * Initialize Paystack Payment — POST handler
 * Validates ownership, generates reference, redirects to Paystack.
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('payments/my-payments.php'); }
verifyCsrf();

$paymentId = (int)($_POST['payment_id'] ?? 0);
if (!$paymentId) { flashError('Invalid payment.'); redirect('payments/my-payments.php'); }

$payObj  = new Payment();
$payment = $payObj->getById($paymentId);

if (!$payment) { flashError('Payment not found.'); redirect('payments/my-payments.php'); }

// Members can only pay their own dues; admins can pay any
$db = Database::getInstance();
if (!isAdmin()) {
    $member = $db->fetchOne(
        'SELECT id FROM members WHERE user_id = ?',
        [$_SESSION['user_id']]
    );
    if (!$member || (int)$member['id'] !== (int)$payment['member_id']) {
        flashError('Access denied.');
        redirect('payments/my-payments.php');
    }
}

if (!in_array($payment['status'], ['pending', 'overdue'])) {
    flashError('This payment has already been processed.');
    redirect('payments/my-payments.php');
}

if (empty(paystackSecretKey())) {
    flashError('Payment gateway is not configured. Please contact an administrator.');
    redirect('payments/my-payments.php');
}

// Unique reference: LASC-{paymentId}-{microtime hash}
$ref   = 'LASC-' . $paymentId . '-' . substr(md5(uniqid('', true)), 0, 8);
$total = (float)$payment['amount'] + (float)$payment['penalty_applied'];

$result = $payObj->initializePaystack($payment['email'], $total, $ref, $paymentId);

if (isset($result['error'])) {
    flashError($result['error']);
    redirect('payments/my-payments.php');
}

// Save reference so callback can look it up
$payObj->setPaystackRef($paymentId, $result['reference']);

// Redirect to Paystack checkout
header('Location: ' . $result['authorization_url']);
exit;
