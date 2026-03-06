<?php
/**
 * Paystack Inline Checkout — POST handler
 * No outbound API call needed — browser handles the popup directly.
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
    $member = $db->fetchOne('SELECT id FROM members WHERE user_id = ?', [$_SESSION['user_id']]);
    if (!$member || (int)$member['id'] !== (int)$payment['member_id']) {
        flashError('Access denied.');
        redirect('payments/my-payments.php');
    }
}

if (!in_array($payment['status'], ['pending', 'overdue'])) {
    flashError('This payment has already been processed.');
    redirect('payments/my-payments.php');
}

$publicKey = paystackPublicKey();
if (empty($publicKey)) {
    flashError('Payment gateway is not configured. Please contact an administrator.');
    redirect('payments/my-payments.php');
}

// Generate reference locally — no API call needed
$ref       = 'LASC-' . $paymentId . '-' . substr(md5(uniqid('', true)), 0, 8);
$total     = (float)$payment['amount'] + (float)$payment['penalty_applied'];
$totalKobo = (int)round($total * 100);

// Save reference so callback & webhook can look it up
$payObj->setPaystackRef($paymentId, $ref);

// Safe values for JS
$jsEmail    = json_encode($payment['email']);
$jsKey      = json_encode($publicKey);
$jsRef      = json_encode($ref);
$jsAmount   = $totalKobo;
$jsDueTitle = json_encode($payment['due_title'] ?? 'Payment');
$cancelUrl  = BASE_URL . 'payments/my-payments.php';
$callbackUrl = BASE_URL . 'payments/callback.php';
$formatted  = '₦' . number_format($total, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout — <?php echo SITE_NAME; ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .checkout-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,.10);
            padding: 40px 36px;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        .brand { color: #00a76f; font-size: 22px; font-weight: 700; margin-bottom: 24px; }
        .due-title { font-size: 16px; color: #637381; margin-bottom: 6px; }
        .amount { font-size: 36px; font-weight: 700; color: #1c252e; margin-bottom: 28px; }
        .pay-btn {
            display: block; width: 100%;
            background: #00a76f; color: #fff;
            border: none; border-radius: 10px;
            padding: 15px 24px; font-size: 16px; font-weight: 600;
            cursor: pointer; transition: background .2s;
        }
        .pay-btn:hover { background: #007a52; }
        .pay-btn:disabled { background: #919eab; cursor: not-allowed; }
        .cancel-link {
            display: block; margin-top: 16px;
            color: #637381; font-size: 14px; text-decoration: none;
        }
        .cancel-link:hover { color: #1c252e; }
        .secure-note {
            margin-top: 24px; font-size: 12px; color: #919eab;
        }
        .spinner {
            display: none; width: 20px; height: 20px;
            border: 3px solid rgba(255,255,255,.4); border-top-color: #fff;
            border-radius: 50%; animation: spin .7s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
<div class="checkout-card">
    <div class="brand"><?php echo htmlspecialchars(SITE_NAME); ?></div>
    <p class="due-title"><?php echo htmlspecialchars($payment['due_title'] ?? 'Payment Due'); ?></p>
    <p class="amount"><?php echo $formatted; ?></p>

    <button class="pay-btn" id="pay-btn" onclick="payNow()">
        Pay <?php echo $formatted; ?>
    </button>
    <div class="spinner" id="spinner"></div>

    <a href="<?php echo htmlspecialchars($cancelUrl); ?>" class="cancel-link">Cancel and go back</a>

    <p class="secure-note">🔒 Secured by Paystack. Your card details are never stored.</p>
</div>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function payNow() {
    var btn = document.getElementById('pay-btn');
    var spinner = document.getElementById('spinner');
    btn.style.display = 'none';
    spinner.style.display = 'block';

    var handler = PaystackPop.setup({
        key:      <?php echo $jsKey; ?>,
        email:    <?php echo $jsEmail; ?>,
        amount:   <?php echo $jsAmount; ?>,
        ref:      <?php echo $jsRef; ?>,
        currency: 'NGN',
        label:    <?php echo $jsDueTitle; ?>,
        onClose: function() {
            btn.style.display = 'block';
            spinner.style.display = 'none';
        },
        callback: function(response) {
            window.location.href = <?php echo json_encode($callbackUrl); ?> + '?reference=' + response.reference;
        }
    });
    handler.openIframe();
}

// Auto-open on page load
window.addEventListener('load', function() { payNow(); });
</script>
</body>
</html>
