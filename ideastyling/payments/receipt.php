<?php
/**
 * Gate Wey Access Management System
 * Payment Receipt Page
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/Payment.php';
require_once 'payment_constants.php';

// Set page title
$pageTitle = 'Payment Receipt';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL);
    exit;
}

// Get user info
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

// Check if payment ID is provided
$paymentId = isset($_GET['id']) ? decryptId($_GET['id']) : null;

if (!$paymentId || !is_numeric($paymentId) || $paymentId <= 0) {
    header('Location: ' . BASE_URL . 'payments/settings.php');
    exit;
}

// Get database instance
$db = Database::getInstance();

// Get payment details
$payment = $db->fetchOne(
    "SELECT p.*, pp.name as plan_name, pp.duration_days, pp.is_per_user, pp.price_per_user, c.name as clan_name
     FROM payments p
     LEFT JOIN pricing_plans pp ON p.pricing_plan_id = pp.id
     LEFT JOIN clans c ON p.clan_id = c.id
     WHERE p.id = ?",
    [$paymentId]
);

// Check if payment exists and user has access to it
if (!$payment ||
    ($currentUser->getRole() !== 'super_admin' && $payment['clan_id'] != $currentUser->getClanId())) {
    header('Location: ' . BASE_URL . 'payments/settings.php');
    exit;
}

// Format payment information
$payerInfo = $payment['payer_info'] ? json_decode($payment['payer_info'], true) : [];

// Status config
$statusConfig = [
    'completed' => ['color' => 'green', 'icon' => 'fa-check-circle', 'label' => 'Completed'],
    'pending' => ['color' => 'orange', 'icon' => 'fa-clock', 'label' => 'Pending'],
    'failed' => ['color' => 'red', 'icon' => 'fa-times-circle', 'label' => 'Failed'],
    'refunded' => ['color' => 'blue', 'icon' => 'fa-undo', 'label' => 'Refunded'],
];
$status = $statusConfig[$payment['status']] ?? ['color' => 'secondary', 'icon' => 'fa-circle', 'label' => ucfirst($payment['status'])];

// Payment method config
$methodConfig = [
    'paystack' => ['icon' => 'fa-credit-card', 'label' => 'Paystack'],
    'bank_transfer' => ['icon' => 'fa-university', 'label' => 'Bank Transfer'],
    'ussd' => ['icon' => 'fa-mobile-alt', 'label' => 'USSD'],
];
$method = $methodConfig[$payment['payment_method']] ?? ['icon' => 'fa-money-bill', 'label' => ucfirst($payment['payment_method'] ?? 'Unknown')];

// Include header
include_once '../includes/header.php';
?>

<!-- iOS Receipt Page -->
<style>
    :root {
        --ios-red: #FF453A;
        --ios-orange: #FF9F0A;
        --ios-yellow: #FFD60A;
        --ios-green: #30D158;
        --ios-teal: #64D2FF;
        --ios-blue: #0A84FF;
        --ios-purple: #BF5AF2;
    }

    /* iOS Options Button (3-dot) */
    .ios-options-btn {
        display: none;
        align-items: center;
        justify-content: center;
        width: 36px; height: 36px;
        border-radius: 50%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    .ios-options-btn:hover { background: var(--bg-tertiary); }

    /* Receipt Container */
    .ios-receipt-wrapper {
        max-width: 680px;
        margin: 0 auto;
    }

    /* Receipt Card */
    .ios-receipt-card {
        background: var(--bg-primary);
        border-radius: 16px;
        border: 1px solid var(--border-color);
        overflow: hidden;
    }

    /* Receipt Header with Logo */
    .ios-receipt-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 32px 24px 24px;
        border-bottom: 1px solid var(--border-color);
        text-align: center;
    }
    .ios-receipt-logo {
        height: 40px;
        margin-bottom: 12px;
    }
    .ios-receipt-logo-light { display: block; }
    .ios-receipt-logo-dark { display: none; }
    [data-theme="dark"] .ios-receipt-logo-light { display: none; }
    [data-theme="dark"] .ios-receipt-logo-dark { display: block; }
    .ios-receipt-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    .ios-receipt-subtitle {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Status Banner */
    .ios-status-banner {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        font-size: 14px;
        font-weight: 600;
    }
    .ios-status-banner.green { background: rgba(48, 209, 88, 0.1); color: var(--ios-green); }
    .ios-status-banner.orange { background: rgba(255, 159, 10, 0.1); color: var(--ios-orange); }
    .ios-status-banner.red { background: rgba(255, 69, 58, 0.1); color: var(--ios-red); }
    .ios-status-banner.blue { background: rgba(10, 132, 255, 0.1); color: var(--ios-blue); }
    .ios-status-banner i { font-size: 16px; }

    /* Receipt Info Rows */
    .ios-receipt-section {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
    }
    .ios-receipt-section:last-child { border-bottom: none; }
    .ios-receipt-section-title {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0 0 14px 0;
    }

    /* Info Row */
    .ios-info-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-color);
    }
    .ios-info-row:last-child { border-bottom: none; }
    .ios-info-label {
        font-size: 14px;
        color: var(--text-secondary);
        flex-shrink: 0;
        margin-right: 16px;
    }
    .ios-info-value {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-primary);
        text-align: right;
        word-break: break-word;
    }

    /* Plan Details Card */
    .ios-plan-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 16px;
    }
    .ios-plan-name {
        font-size: 16px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }
    .ios-plan-detail {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0 0 2px 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .ios-plan-detail i {
        font-size: 11px;
        color: var(--text-muted);
        width: 14px;
        text-align: center;
    }

    /* Amount Summary */
    .ios-amount-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
    }
    .ios-amount-row.total {
        border-top: 1px solid var(--border-color);
        padding-top: 14px;
        margin-top: 4px;
    }
    .ios-amount-label {
        font-size: 14px;
        color: var(--text-secondary);
    }
    .ios-amount-row.total .ios-amount-label {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
    }
    .ios-amount-value {
        font-size: 14px;
        font-weight: 500;
        color: var(--text-primary);
    }
    .ios-amount-row.total .ios-amount-value {
        font-size: 20px;
        font-weight: 700;
        color: var(--ios-green);
    }

    /* Payment Method */
    .ios-method-display {
        display: flex;
        align-items: center;
        gap: 12px;
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 14px 16px;
    }
    .ios-method-icon {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
        flex-shrink: 0;
    }
    .ios-method-name {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    .ios-method-type {
        font-size: 12px;
        color: var(--text-secondary);
        margin: 0;
    }

    /* Receipt Footer */
    .ios-receipt-footer {
        text-align: center;
        padding: 24px;
        border-top: 1px solid var(--border-color);
    }
    .ios-receipt-footer-text {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0 0 4px 0;
    }
    .ios-receipt-footer-note {
        font-size: 12px;
        color: var(--text-muted);
        margin: 0;
    }

    /* Action Buttons */
    .ios-receipt-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
        padding: 24px 0;
        flex-wrap: wrap;
    }
    .ios-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 12px;
        font-size: 15px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .ios-btn.primary { background: var(--ios-blue); color: white; }
    .ios-btn.primary:hover { background: #0070E0; color: white; }
    .ios-btn.secondary { background: var(--bg-secondary); color: var(--text-primary); border: 1px solid var(--border-color); }
    .ios-btn.secondary:hover { background: var(--bg-tertiary); color: var(--text-primary); }
    .ios-btn.success { background: var(--ios-green); color: white; }
    .ios-btn.success:hover { background: #28B84C; color: white; }

    /* iOS Bottom Sheet Menu */
    .ios-menu-backdrop {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
        z-index: 9998; opacity: 0; visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    .ios-menu-backdrop.active { opacity: 1; visibility: visible; }
    .ios-menu-modal {
        position: fixed; bottom: 0; left: 0; right: 0;
        background: var(--bg-primary);
        border-radius: 20px 20px 0 0;
        z-index: 9999;
        transform: translateY(100%);
        transition: transform 0.35s cubic-bezier(0.32, 0.72, 0, 1);
        max-height: 85vh; overflow-y: auto;
        padding-bottom: env(safe-area-inset-bottom, 20px);
    }
    .ios-menu-modal.active { transform: translateY(0); }
    .ios-menu-handle {
        width: 36px; height: 5px;
        background: var(--text-muted); opacity: 0.3;
        border-radius: 3px;
        margin: 8px auto 0;
    }
    .ios-menu-header {
        padding: 16px 20px 12px;
        border-bottom: 1px solid var(--border-color);
    }
    .ios-menu-header h3 {
        font-size: 18px; font-weight: 700;
        color: var(--text-primary); margin: 0 0 4px 0;
    }
    .ios-menu-header p {
        font-size: 13px; color: var(--text-secondary); margin: 0;
    }
    .ios-menu-content { padding: 16px 20px; }
    .ios-menu-section { margin-bottom: 20px; }
    .ios-menu-section:last-child { margin-bottom: 0; }
    .ios-menu-section-title {
        font-size: 12px; font-weight: 600; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: 0.5px;
        margin: 0 0 10px 0;
    }
    .ios-menu-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
    }
    .ios-menu-item {
        display: flex; align-items: center; gap: 14px;
        padding: 14px 16px;
        text-decoration: none;
        color: var(--text-primary);
        border-bottom: 1px solid var(--border-color);
        transition: background 0.15s ease;
    }
    .ios-menu-item:last-child { border-bottom: none; }
    .ios-menu-item:hover { background: rgba(255, 255, 255, 0.03); }
    .ios-menu-item-icon {
        width: 32px; height: 32px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 14px; flex-shrink: 0;
    }
    .ios-menu-item-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-menu-item-icon.green { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-menu-item-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-menu-item-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-menu-item-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }
    .ios-menu-item-icon.teal { background: rgba(100, 210, 255, 0.15); color: var(--ios-teal); }
    .ios-menu-item-label { font-size: 15px; font-weight: 500; }
    .ios-menu-stat-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
    }
    .ios-menu-stat-row:last-child { border-bottom: none; }
    .ios-menu-stat-label { font-size: 14px; color: var(--text-secondary); }
    .ios-menu-stat-value { font-size: 14px; font-weight: 600; color: var(--text-primary); }

    /* Responsive */
    @media (max-width: 992px) {
        .ios-options-btn { display: flex; }
    }
    @media (max-width: 768px) {
        .content-header { display: none !important; }
        .ios-receipt-wrapper { margin: 0 -4px; }
        .ios-receipt-card { border-radius: 12px; }
        .ios-receipt-header { padding: 24px 20px 20px; }
        .ios-receipt-logo { height: 36px; }
        .ios-receipt-title { font-size: 18px; }
        .ios-receipt-section { padding: 16px 20px; }
        .ios-amount-row.total .ios-amount-value { font-size: 18px; }
        .ios-receipt-actions { padding: 20px 0; }
        .ios-btn { padding: 12px 20px; font-size: 14px; }
    }
    @media (max-width: 480px) {
        .ios-receipt-header { padding: 20px 16px 16px; }
        .ios-receipt-logo { height: 32px; }
        .ios-receipt-title { font-size: 16px; }
        .ios-receipt-subtitle { font-size: 12px; }
        .ios-receipt-section { padding: 14px 16px; }
        .ios-receipt-section-title { font-size: 11px; margin-bottom: 12px; }
        .ios-info-label, .ios-info-value { font-size: 13px; }
        .ios-plan-name { font-size: 14px; }
        .ios-plan-detail { font-size: 12px; }
        .ios-amount-label { font-size: 13px; }
        .ios-amount-value { font-size: 13px; }
        .ios-amount-row.total .ios-amount-label { font-size: 14px; }
        .ios-amount-row.total .ios-amount-value { font-size: 17px; }
        .ios-method-display { padding: 12px 14px; gap: 10px; }
        .ios-method-icon { width: 36px; height: 36px; font-size: 14px; }
        .ios-method-name { font-size: 14px; }
        .ios-receipt-footer { padding: 20px 16px; }
        .ios-receipt-footer-text { font-size: 13px; }
        .ios-receipt-actions { gap: 8px; }
        .ios-btn { padding: 11px 16px; font-size: 14px; border-radius: 10px; flex: 1; min-width: 0; }
    }
    @media (max-width: 390px) {
        .ios-receipt-header { padding: 16px 14px; }
        .ios-receipt-logo { height: 28px; }
        .ios-receipt-section { padding: 12px 14px; }
        .ios-info-row { flex-direction: column; gap: 2px; }
        .ios-info-value { text-align: left; }
        .ios-btn { padding: 10px 14px; font-size: 13px; }
    }

    /* Print Styles */
    @media print {
        @page { size: A4; margin: 15mm; }

        * { color: #000000 !important; background: #ffffff !important;
            box-shadow: none !important; text-shadow: none !important; }

        body { background: #ffffff !important; }

        .content {
            margin-left: 0 !important;
            margin-top: 0 !important;
            padding: 0 !important;
        }

        /* Hide all non-receipt chrome */
        .navbar, .sidebar, .content-header, .ios-receipt-actions, .ios-options-btn,
        .ios-menu-backdrop, .ios-menu-modal,
        .mobile-bottom-nav, .ios-header-menu-modal, .ios-header-menu-backdrop,
        .mobile-search-container, .footer { display: none !important; }

        /* Receipt card — plain border, no shadow */
        .ios-receipt-card {
            border: 1px solid #cccccc !important;
            border-radius: 0 !important;
        }

        /* Logo — always show light version */
        .ios-receipt-logo-light { display: block !important; }
        .ios-receipt-logo-dark  { display: none  !important; }

        /* Header area */
        .ios-receipt-header { background: #ffffff !important; border-bottom: 1px solid #cccccc !important; }

        /* Status banner — no color, just a border separator */
        .ios-status-banner {
            background: #ffffff !important;
            border-bottom: 1px solid #cccccc !important;
        }
        .ios-status-banner i { display: none !important; }

        /* Section titles */
        .ios-receipt-section { border-bottom: 1px solid #e0e0e0 !important; }
        .ios-receipt-section-title { color: #555555 !important; }

        /* Plan card */
        .ios-plan-card { background: #f5f5f5 !important; border: 1px solid #cccccc !important; }
        .ios-plan-detail i { display: none !important; }

        /* Amount breakdown */
        .ios-amount-row { border-color: #cccccc !important; }
        .ios-amount-row.total .ios-amount-value {
            font-size: 18px !important;
            font-weight: 700 !important;
        }

        /* Payment method */
        .ios-method-display { background: #f5f5f5 !important; border: 1px solid #cccccc !important; }
        .ios-method-icon { background: #eeeeee !important; }

        /* Info rows */
        .ios-info-row { border-color: #e0e0e0 !important; }

        /* Footer */
        .ios-receipt-footer { border-top: 1px solid #cccccc !important; }

        /* Wrapper — full width */
        .ios-receipt-wrapper { max-width: 100% !important; margin: 0 !important; }
    }
</style>

<div class="main-content">
    <?php include_once '../includes/sidebar.php'; ?>
    <div class="content">
        <!-- Content Header (hidden on mobile) -->
        <div class="content-header">
            <div>
                <h1>Payment Receipt</h1>
                <p>Transaction details and payment confirmation</p>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <button onclick="window.print();" class="ios-btn primary">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="ios-options-btn" onclick="openMenu()">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
        </div>

        <div class="ios-receipt-wrapper">
            <!-- Receipt Card -->
            <div class="ios-receipt-card" id="receipt">
                <!-- Header with Logo -->
                <div class="ios-receipt-header">
                    <img src="<?php echo BASE_URL; ?>assets/images/icons/logo.png" alt="GateWey Logo" class="ios-receipt-logo ios-receipt-logo-light">
                    <img src="<?php echo BASE_URL; ?>assets/images/icons/logo-dark.png" alt="GateWey Logo" class="ios-receipt-logo ios-receipt-logo-dark">
                    <h2 class="ios-receipt-title">Payment Receipt</h2>
                    <p class="ios-receipt-subtitle">Transaction #<?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></p>
                </div>

                <!-- Status Banner -->
                <div class="ios-status-banner <?php echo $status['color']; ?>">
                    <i class="fas <?php echo $status['icon']; ?>"></i>
                    <?php echo $status['label']; ?>
                </div>

                <!-- Transaction Details -->
                <div class="ios-receipt-section">
                    <p class="ios-receipt-section-title">Transaction Details</p>
                    <div class="ios-info-row">
                        <span class="ios-info-label">Transaction ID</span>
                        <span class="ios-info-value"><?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="ios-info-row">
                        <span class="ios-info-label">Date</span>
                        <span class="ios-info-value"><?php echo date('F j, Y, g:i a', strtotime($payment['payment_date'])); ?></span>
                    </div>
                    <div class="ios-info-row">
                        <span class="ios-info-label">Reference</span>
                        <span class="ios-info-value"><?php echo htmlspecialchars($payment['paystack_reference'] ?? $payment['transaction_id'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <!-- Clan & Payer Info -->
                <div class="ios-receipt-section">
                    <p class="ios-receipt-section-title">Billing Information</p>
                    <div class="ios-info-row">
                        <span class="ios-info-label">Clan</span>
                        <span class="ios-info-value"><?php echo htmlspecialchars($payment['clan_name']); ?></span>
                    </div>
                    <div class="ios-info-row">
                        <span class="ios-info-label">Payer</span>
                        <span class="ios-info-value"><?php echo htmlspecialchars($payerInfo['name'] ?? $currentUser->getFullName()); ?></span>
                    </div>
                    <div class="ios-info-row">
                        <span class="ios-info-label">Email</span>
                        <span class="ios-info-value"><?php echo htmlspecialchars($payerInfo['email'] ?? $currentUser->getEmail()); ?></span>
                    </div>
                </div>

                <!-- Plan Details -->
                <div class="ios-receipt-section">
                    <p class="ios-receipt-section-title">Subscription Details</p>
                    <div class="ios-plan-card">
                        <p class="ios-plan-name"><?php echo htmlspecialchars($payment['plan_name'] ?? 'Plan Subscription'); ?></p>
                        <p class="ios-plan-detail">
                            <i class="fas fa-calendar-alt"></i>
                            <?php echo $payment['duration_days'] ?? '30'; ?> days subscription
                        </p>
                        <?php if ($payment['period_start'] && $payment['period_end']): ?>
                            <p class="ios-plan-detail">
                                <i class="fas fa-clock"></i>
                                <?php echo date('M d, Y', strtotime($payment['period_start'])); ?> — <?php echo date('M d, Y', strtotime($payment['period_end'])); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($payment['is_per_user']): ?>
                            <p class="ios-plan-detail">
                                <i class="fas fa-users"></i>
                                <?php echo $payment['user_count']; ?> users × ₦<?php echo number_format($payment['price_per_user'], 2); ?> per user
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Amount Summary -->
                <div class="ios-receipt-section">
                    <p class="ios-receipt-section-title">Payment Summary</p>
                    <div class="ios-amount-row">
                        <span class="ios-amount-label">Subtotal</span>
                        <span class="ios-amount-value">₦<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                    <div class="ios-amount-row">
                        <span class="ios-amount-label">Tax</span>
                        <span class="ios-amount-value">₦0.00</span>
                    </div>
                    <div class="ios-amount-row total">
                        <span class="ios-amount-label">Total</span>
                        <span class="ios-amount-value">₦<?php echo number_format($payment['amount'], 2); ?></span>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="ios-receipt-section">
                    <p class="ios-receipt-section-title">Payment Method</p>
                    <div class="ios-method-display">
                        <div class="ios-method-icon">
                            <i class="fas <?php echo $method['icon']; ?>"></i>
                        </div>
                        <div>
                            <p class="ios-method-name"><?php echo $method['label']; ?></p>
                            <p class="ios-method-type">Online Payment</p>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="ios-receipt-footer">
                    <p class="ios-receipt-footer-text">Thank you for your payment!</p>
                    <?php if ($payment['status'] === 'completed'): ?>
                        <p class="ios-receipt-footer-note">This is an official receipt for your records.</p>
                    <?php else: ?>
                        <p class="ios-receipt-footer-note">This receipt will be updated when payment is complete.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="ios-receipt-actions">
                <a href="<?php echo BASE_URL; ?>payments/settings.php" class="ios-btn secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <button onclick="window.print();" class="ios-btn primary">
                    <i class="fas fa-print"></i> Print Receipt
                </button>
                <?php if ($payment['status'] === 'pending'): ?>
                    <a href="<?php echo BASE_URL; ?>payments/process.php" class="ios-btn success">
                        <i class="fas fa-credit-card"></i> Complete Payment
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- iOS Bottom Sheet Menu -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3>Receipt Options</h3>
        <p>Transaction #<?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></p>
    </div>
    <div class="ios-menu-content">
        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Quick Actions</p>
            <div class="ios-menu-card">
                <a href="javascript:void(0);" onclick="window.print(); closeMenu();" class="ios-menu-item">
                    <div class="ios-menu-item-icon blue"><i class="fas fa-print"></i></div>
                    <span class="ios-menu-item-label">Print Receipt</span>
                </a>
                <a href="<?php echo BASE_URL; ?>payments/settings.php" class="ios-menu-item">
                    <div class="ios-menu-item-icon green"><i class="fas fa-cog"></i></div>
                    <span class="ios-menu-item-label">Payment Settings</span>
                </a>
                <?php if ($payment['status'] === 'pending'): ?>
                    <a href="<?php echo BASE_URL; ?>payments/process.php" class="ios-menu-item">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-credit-card"></i></div>
                        <span class="ios-menu-item-label">Complete Payment</span>
                    </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-icon purple"><i class="fas fa-th-large"></i></div>
                    <span class="ios-menu-item-label">Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Payment Summary -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Payment Summary</p>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Amount</span>
                    <span class="ios-menu-stat-value">₦<?php echo number_format($payment['amount'], 2); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Status</span>
                    <span class="ios-menu-stat-value" style="color: var(--ios-<?php echo $status['color']; ?>);"><?php echo $status['label']; ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Method</span>
                    <span class="ios-menu-stat-value"><?php echo $method['label']; ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Date</span>
                    <span class="ios-menu-stat-value"><?php echo date('M j, Y', strtotime($payment['payment_date'])); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
console.log('📄 iOS Receipt Page loaded');

// iOS Menu Functions
function openMenu() {
    document.getElementById('iosMenuBackdrop').classList.add('active');
    document.getElementById('iosMenuModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeMenu() {
    document.getElementById('iosMenuBackdrop').classList.remove('active');
    document.getElementById('iosMenuModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Swipe to close
(function() {
    const modal = document.getElementById('iosMenuModal');
    let startY = 0, currentY = 0, isDragging = false;

    modal.addEventListener('touchstart', function(e) {
        if (modal.scrollTop <= 0) {
            startY = e.touches[0].clientY;
            isDragging = true;
        }
    }, { passive: true });

    modal.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        currentY = e.touches[0].clientY;
        const diff = currentY - startY;
        if (diff > 0) {
            modal.style.transform = `translateY(${diff}px)`;
        }
    }, { passive: true });

    modal.addEventListener('touchend', function() {
        if (!isDragging) return;
        isDragging = false;
        const diff = currentY - startY;
        if (diff > 100) {
            closeMenu();
        }
        modal.style.transform = '';
        currentY = 0;
        startY = 0;
    });
})();
</script>

<?php include_once '../includes/footer.php'; ?>
