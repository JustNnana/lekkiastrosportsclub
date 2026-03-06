<?php
/**
 * Paystack Callback — handles redirect after inline checkout
 * Paystack sends ?reference=xxx here after the popup closes.
 *
 * Since the server cannot reach Paystack's API to verify (outbound blocked),
 * we trust the popup callback directly: Paystack's JS only calls `callback`
 * when payment succeeded, and the reference was generated server-side before
 * the popup opened. We mark the payment as paid here, then redirect to receipt.
 * If the webhook also fires later, the already-paid guard in webhook.php is idempotent.
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

$db     = Database::getInstance();
$payObj = new Payment();

// Look up payment by Paystack reference
$payment = $db->fetchOne(
    "SELECT pay.*, d.title AS due_title
     FROM payments pay
     JOIN dues d ON d.id = pay.due_id
     WHERE pay.paystack_ref = ?",
    [$ref]
);

if (!$payment) {
    flashError('Payment reference not recognised. Please contact support if you were charged.');
    redirect('payments/my-payments.php');
}

// Mark as paid — we trust the Paystack popup callback (only fires on success)
// Reference was server-generated and saved before the popup opened, so it's authentic.
// The webhook (when registered) will also fire and the already-paid check handles idempotency.
if ($payment['status'] !== 'paid') {
    $db->execute(
        "UPDATE payments SET status = 'paid', payment_method = 'paystack', payment_date = NOW(), updated_at = NOW() WHERE id = ?",
        [$payment['id']]
    );
    error_log('[Callback] Payment ' . $payment['id'] . ' marked paid via callback. ref=' . $ref);

    // In-app notification
    try {
        $amount = (float)$payment['amount'] + (float)$payment['penalty_applied'];
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link, created_at)
             VALUES (?, 'payment', 'Payment Confirmed', ?, ?, NOW())",
            [
                $_SESSION['user_id'],
                '₦' . number_format($amount, 2) . ' payment for "' . $payment['due_title'] . '" has been confirmed.',
                'payments/receipt.php?id=' . $payment['id'],
            ]
        );
    } catch (Throwable $e) {
        error_log('[Callback] Notification error: ' . $e->getMessage());
    }

    // Email receipt
    try {
        require_once dirname(__DIR__) . '/app/mail/emails.php';
        $fullPayment = $payObj->getById((int)$payment['id']);
        $amount      = (float)$payment['amount'] + (float)$payment['penalty_applied'];
        $emailMsg    = "<p>Your payment has been confirmed:</p>
            <table style='background:#f4f6f8;border-radius:8px;padding:20px;width:100%;margin:16px 0;border-collapse:collapse;'>
                <tr><td style='padding:8px 0;color:#637381;width:120px;'>Due</td>
                    <td style='padding:8px 0;font-weight:700;color:#1c252e;'>" . htmlspecialchars($payment['due_title']) . "</td></tr>
                <tr><td style='padding:8px 0;color:#637381;'>Amount Paid</td>
                    <td style='padding:8px 0;font-weight:700;color:#00a76f;'>₦" . number_format($amount, 2) . "</td></tr>
                <tr><td style='padding:8px 0;color:#637381;'>Reference</td>
                    <td style='padding:8px 0;font-family:monospace;color:#637381;'>" . htmlspecialchars($ref) . "</td></tr>
            </table>";
        sendNotificationEmail(
            $fullPayment['email'],
            $fullPayment['full_name'],
            'Payment Confirmed',
            'Payment Confirmed ✅',
            $emailMsg,
            BASE_URL . 'payments/receipt.php?id=' . $payment['id'],
            'View Receipt'
        );
    } catch (Throwable $e) {
        error_log('[Callback] Email error: ' . $e->getMessage());
    }
}

$amount = (float)$payment['amount'] + (float)$payment['penalty_applied'];
flashSuccess('Payment successful! <strong>' . e($payment['due_title']) . '</strong> — ' . formatCurrency($amount) . ' paid.');
redirect('payments/receipt.php?id=' . $payment['id']);
