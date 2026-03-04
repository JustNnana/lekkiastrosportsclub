<?php
/**
 * Test Paystack API connectivity — Super Admin only
 * Returns JSON with success/failure and diagnostic detail.
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
}

if (!isSuperAdmin()) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Access denied.']));
}

$secretKey = paystackSecretKey();

if (empty($secretKey)) {
    exit(json_encode(['success' => false, 'message' => 'Secret key is not configured. Add it in the Paystack Keys section and save.']));
}

if (!str_starts_with($secretKey, 'sk_')) {
    exit(json_encode(['success' => false, 'message' => 'Secret key format is invalid. It must start with sk_live_ or sk_test_.']));
}

// Hit a lightweight Paystack endpoint that just validates auth
$ch = curl_init('https://api.paystack.co/bank?perPage=1');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $secretKey],
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrNo = curl_errno($ch);
curl_close($ch);

if ($curlError || !$response) {
    exit(json_encode([
        'success' => false,
        'message' => "cURL error ({$curlErrNo}): {$curlError}. The server may be blocking outbound HTTPS — contact your hosting provider.",
    ]));
}

if ($httpCode === 401) {
    exit(json_encode([
        'success' => false,
        'message' => 'Authentication failed (HTTP 401). The secret key is incorrect or has been revoked. Check your Paystack dashboard.',
    ]));
}

$data = json_decode($response, true);

if ($httpCode === 200 && ($data['status'] ?? false)) {
    $mode = str_starts_with($secretKey, 'sk_test_') ? 'Test Mode' : 'Live Mode';
    exit(json_encode([
        'success' => true,
        'message' => "Paystack API reachable. Key valid ({$mode}).",
    ]));
}

exit(json_encode([
    'success' => false,
    'message' => "Paystack returned HTTP {$httpCode}: " . ($data['message'] ?? $response),
]));
