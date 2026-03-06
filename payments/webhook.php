<?php
/**
 * Paystack Webhook — server-to-server event handler
 * Register this URL in Paystack Dashboard → Settings → Webhooks:
 *   https://app.lekkiastrosportsclub.com/payments/webhook.php
 *
 * No session or login required — Paystack calls this directly.
 * Payment is marked paid directly from webhook payload (no outbound verify call).
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Read raw body
$body = file_get_contents('php://input');
if (!$body) {
    http_response_code(400);
    exit('Empty body');
}

// Verify Paystack signature (HMAC-SHA512)
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$expected  = hash_hmac('sha512', $body, paystackSecretKey());

if (!hash_equals($expected, $signature)) {
    error_log('[Webhook] Invalid signature.');
    http_response_code(401);
    exit('Unauthorized');
}

$event = json_decode($body, true);
if (!$event || !isset($event['event'])) {
    http_response_code(400);
    exit('Bad payload');
}

// Respond 200 immediately so Paystack doesn't retry
http_response_code(200);
echo 'OK';
flush();

// ─── Handle charge.success ────────────────────────────────────────────────────
if ($event['event'] === 'charge.success') {
    $ref    = $event['data']['reference'] ?? '';
    $status = $event['data']['status']    ?? '';

    if (!$ref || $status !== 'success') {
        error_log('[Webhook] Skipping — ref empty or status not success.');
        exit;
    }

    $db      = Database::getInstance();
    $payment = $db->fetchOne(
        "SELECT pay.*, d.title AS due_title, m.user_id, u.full_name, u.email
         FROM payments pay
         JOIN dues    d ON d.id   = pay.due_id
         JOIN members m ON m.id   = pay.member_id
         JOIN users   u ON u.id   = m.user_id
         WHERE pay.paystack_ref = ?",
        [$ref]
    );

    if (!$payment) {
        error_log('[Webhook] No payment found for ref=' . $ref);
        exit;
    }

    if ($payment['status'] === 'paid') {
        error_log('[Webhook] Already paid, ref=' . $ref);
        exit;
    }

    // Mark as paid directly — no outbound API call needed
    $db->execute(
        "UPDATE payments SET status = 'paid', payment_method = 'paystack', payment_date = NOW(), updated_at = NOW() WHERE id = ?",
        [$payment['id']]
    );

    error_log('[Webhook] Payment ' . $payment['id'] . ' marked paid. ref=' . $ref);

    // In-app notification for the member
    try {
        $amount = (float)$payment['amount'] + (float)$payment['penalty_applied'];
        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, link, created_at)
             VALUES (?, 'payment', 'Payment Confirmed', ?, ?, NOW())",
            [
                $payment['user_id'],
                '₦' . number_format($amount, 2) . ' payment for "' . $payment['due_title'] . '" has been confirmed.',
                'payments/receipt.php?id=' . $payment['id'],
            ]
        );
    } catch (Throwable $e) {
        error_log('[Webhook] Notification error: ' . $e->getMessage());
    }

    // Email receipt
    try {
        require_once dirname(__DIR__) . '/app/mail/emails.php';
        $amount = (float)$payment['amount'] + (float)$payment['penalty_applied'];
        $emailMsg = "<p>Your payment has been confirmed:</p>
            <table style='background:#f4f6f8;border-radius:8px;padding:20px;width:100%;margin:16px 0;border-collapse:collapse;'>
                <tr><td style='padding:8px 0;color:#637381;width:120px;'>Due</td>
                    <td style='padding:8px 0;font-weight:700;color:#1c252e;'>" . htmlspecialchars($payment['due_title']) . "</td></tr>
                <tr><td style='padding:8px 0;color:#637381;'>Amount Paid</td>
                    <td style='padding:8px 0;font-weight:700;color:#00a76f;'>₦" . number_format($amount, 2) . "</td></tr>
                <tr><td style='padding:8px 0;color:#637381;'>Reference</td>
                    <td style='padding:8px 0;font-family:monospace;color:#637381;'>" . htmlspecialchars($ref) . "</td></tr>
            </table>";
        sendNotificationEmail(
            $payment['email'],
            $payment['full_name'],
            'Payment Confirmed',
            'Payment Confirmed ✅',
            $emailMsg,
            BASE_URL . 'payments/receipt.php?id=' . $payment['id'],
            'View Receipt'
        );
    } catch (Throwable $e) {
        error_log('[Webhook] Email error: ' . $e->getMessage());
    }
}

exit;
