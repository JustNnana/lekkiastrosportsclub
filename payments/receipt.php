<?php
/**
 * Payment Receipt — printable / shareable
 * Accessible by the paying member or any admin.
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireLogin();

$id      = (int)($_GET['id'] ?? 0);
if (!$id) { flashError('Invalid receipt.'); redirect('payments/my-payments.php'); }

$payObj  = new Payment();
$payment = $payObj->getById($id);

if (!$payment) { flashError('Payment not found.'); redirect('payments/my-payments.php'); }

// Members can only view their own receipts
if (!isAdmin()) {
    $db     = Database::getInstance();
    $member = $db->fetchOne('SELECT id FROM members WHERE user_id = ?', [$_SESSION['user_id']]);
    if (!$member || (int)$member['id'] !== (int)$payment['member_id']) {
        flashError('Access denied.');
        redirect('payments/my-payments.php');
    }
}

if ($payment['status'] !== 'paid') {
    flashError('Receipt is only available for paid payments.');
    redirect(isAdmin() ? 'payments/' : 'payments/my-payments.php');
}

$totalPaid = (float)$payment['amount'] + (float)$payment['penalty_applied'];
$ref       = $payment['paystack_ref'] ?? ('MANUAL-' . str_pad($payment['id'], 8, '0', STR_PAD_LEFT));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt — <?php echo e(SITE_ABBR); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            background: #f4f6f8;
            color: #1c252e;
            padding: 40px 20px;
            font-size: 14px;
        }

        .receipt {
            max-width: 640px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,.1);
        }

        /* Header */
        .receipt-header {
            background: #00a76f;
            padding: 32px 40px;
            color: #fff;
            text-align: center;
        }
        .receipt-header h1 { font-size: 22px; font-weight: 700; letter-spacing: .5px; }
        .receipt-header p  { opacity: .85; margin-top: 4px; font-size: 13px; }

        /* Status badge */
        .receipt-status {
            background: #ecfdf5;
            border-bottom: 1px solid #d1fae5;
            padding: 16px 40px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .status-icon {
            width: 40px; height: 40px;
            background: #00a76f;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 18px; flex-shrink: 0;
        }
        .status-text strong { color: #065f46; display: block; font-size: 15px; }
        .status-text span   { color: #6b7280; font-size: 12px; }

        /* Body */
        .receipt-body { padding: 32px 40px; }
        .receipt-ref  {
            text-align: center;
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 1px dashed #e5e7eb;
        }
        .receipt-ref .amount {
            font-size: 36px;
            font-weight: 700;
            color: #00a76f;
            line-height: 1;
        }
        .receipt-ref .ref-code {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 6px;
            font-family: 'Courier New', monospace;
        }

        /* Detail rows */
        .detail-section { margin-bottom: 24px; }
        .detail-section h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #9ca3af;
            font-weight: 600;
            margin-bottom: 12px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            gap: 16px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: #6b7280; flex-shrink: 0; }
        .detail-value { font-weight: 500; text-align: right; }

        .divider { border: none; border-top: 1px dashed #e5e7eb; margin: 24px 0; }

        /* Footer */
        .receipt-footer {
            background: #f9fafb;
            border-top: 1px solid #f3f4f6;
            padding: 20px 40px;
            text-align: center;
        }
        .receipt-footer p { color: #9ca3af; font-size: 12px; line-height: 1.6; }
        .receipt-footer .site-link { color: #00a76f; text-decoration: none; }

        /* Print actions */
        .print-actions {
            max-width: 640px;
            margin: 24px auto 0;
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .btn-print {
            padding: 10px 24px;
            background: #00a76f;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .btn-back {
            padding: 10px 24px;
            background: transparent;
            color: #6b7280;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .receipt { box-shadow: none; border-radius: 0; }
            .print-actions { display: none; }
        }

        @media (max-width: 600px) {
            .receipt-header, .receipt-body, .receipt-footer,
            .receipt-status { padding-left: 24px; padding-right: 24px; }
        }
    </style>
</head>
<body>

<div class="receipt">
    <!-- Header -->
    <div class="receipt-header">
        <h1><?php echo e(SITE_NAME); ?></h1>
        <p><?php echo e(SITE_ABBR); ?> — Official Payment Receipt</p>
    </div>

    <!-- Status -->
    <div class="receipt-status">
        <div class="status-icon">&#10003;</div>
        <div class="status-text">
            <strong>Payment Confirmed</strong>
            <span>This is an official receipt. Keep for your records.</span>
        </div>
    </div>

    <!-- Amount + Reference -->
    <div class="receipt-body">
        <div class="receipt-ref">
            <div class="amount"><?php echo formatCurrency($totalPaid); ?></div>
            <div class="ref-code">REF: <?php echo e(strtoupper($ref)); ?></div>
        </div>

        <!-- Member Details -->
        <div class="detail-section">
            <h3>Member Information</h3>
            <div class="detail-row">
                <span class="detail-label">Full Name</span>
                <span class="detail-value"><?php echo e($payment['full_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Member ID</span>
                <span class="detail-value"><?php echo e($payment['member_code']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email</span>
                <span class="detail-value"><?php echo e($payment['email']); ?></span>
            </div>
        </div>

        <hr class="divider">

        <!-- Payment Details -->
        <div class="detail-section">
            <h3>Payment Details</h3>
            <div class="detail-row">
                <span class="detail-label">Due</span>
                <span class="detail-value"><?php echo e($payment['due_title']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Frequency</span>
                <span class="detail-value"><?php echo e(str_replace('_', ' ', ucfirst($payment['frequency']))); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Period Due Date</span>
                <span class="detail-value"><?php echo formatDate($payment['due_date']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Base Amount</span>
                <span class="detail-value"><?php echo formatCurrency((float)$payment['amount']); ?></span>
            </div>
            <?php if ($payment['penalty_applied'] > 0): ?>
            <div class="detail-row">
                <span class="detail-label">Penalty Applied</span>
                <span class="detail-value" style="color:#dc2626"><?php echo formatCurrency((float)$payment['penalty_applied']); ?></span>
            </div>
            <?php endif; ?>
            <div class="detail-row" style="font-size:15px;padding-top:12px">
                <span class="detail-label"><strong>Total Paid</strong></span>
                <span class="detail-value" style="color:#00a76f"><strong><?php echo formatCurrency($totalPaid); ?></strong></span>
            </div>
        </div>

        <hr class="divider">

        <!-- Transaction Info -->
        <div class="detail-section">
            <h3>Transaction Info</h3>
            <div class="detail-row">
                <span class="detail-label">Payment Method</span>
                <span class="detail-value"><?php echo $payment['payment_method'] === 'paystack' ? 'Paystack (Online)' : 'Manual (Cash/Transfer)'; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date Paid</span>
                <span class="detail-value"><?php echo formatDate($payment['payment_date'], 'd M Y, g:i A'); ?></span>
            </div>
            <?php if ($payment['notes']): ?>
            <div class="detail-row">
                <span class="detail-label">Notes</span>
                <span class="detail-value"><?php echo e($payment['notes']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="receipt-footer">
        <p>
            Thank you for your payment, <?php echo e(explode(' ', $payment['full_name'])[0]); ?>!<br>
            <?php echo e(SITE_NAME); ?> · <a class="site-link" href="<?php echo BASE_URL; ?>"><?php echo BASE_URL; ?></a><br>
            Receipt generated: <?php echo date('d M Y, g:i A'); ?>
        </p>
    </div>
</div>

<!-- Print Actions -->
<div class="print-actions">
    <button class="btn-print" onclick="window.print()">
        &#128438; Print Receipt
    </button>
    <a href="javascript:history.back()" class="btn-back">← Back</a>
</div>

</body>
</html>
