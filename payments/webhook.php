<?php
/**
 * Paystack Webhook — server-to-server event handler
 * Register this URL in Paystack Dashboard → Settings → Webhooks:
 *   https://app.lekkiastrosportsclub.com/payments/webhook.php
 *
 * No session or login required — Paystack calls this directly.
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
$expected  = hash_hmac('sha512', $body, PAYSTACK_SECRET_KEY);

if (!hash_equals($expected, $signature)) {
    error_log('Paystack webhook: invalid signature.');
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

// ─── Handle events ────────────────────────────────────────────

if ($event['event'] === 'charge.success') {
    $ref     = $event['data']['reference'] ?? '';
    if (!$ref) exit;

    $payObj = new Payment();
    $result = $payObj->verifyAndMarkPaid($ref);

    if (!$result['success']) {
        error_log('Paystack webhook: verification failed for ref=' . $ref . ' — ' . $result['message']);
    } else {
        error_log('Paystack webhook: payment ' . $ref . ' marked paid.');
    }
}

// Other events (refund.processed, transfer.success, etc.) can be added here.
exit;
