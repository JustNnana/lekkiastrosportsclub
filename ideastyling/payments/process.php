<?php
/**
 * Gate Wey Access Management System
 * Payment Processing Page
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/Payment.php';
require_once 'PaymentIntegration.php';
require_once 'payment_constants.php';
require_once '../includes/functions.php';

// Set page title
$pageTitle = 'Process Payment';

// Check if user is logged in and is a clan admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'clan_admin') {
    header('Location: ' . BASE_URL);
    exit;
}

// Get user info
$currentUser = new User();
if (!$currentUser->loadById($_SESSION['user_id'])) {
    // If user doesn't exist, clear session and redirect to login
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL);
    exit;
}

// Get clan details
$clanId = $currentUser->getClanId();
$clan = new Clan();
if (!$clan->loadById($clanId)) {
    // If clan doesn't exist, redirect to dashboard
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Get database instance
$db = Database::getInstance();

// Initialize variables
$error = '';
$success = '';
$paymentCompleted = false;
$receipt = null;

// Get payment settings
$paymentSettings = $db->fetchOne(
    "SELECT * FROM payment_settings WHERE clan_id = ?",
    [$clanId]
);

// If no payment settings exist, create default settings
if (!$paymentSettings) {
    $db->query(
        "INSERT INTO payment_settings (clan_id, paystack_enabled, bank_transfer_enabled, auto_renew) 
         VALUES (?, 1, 1, 1)",
        [$clanId]
    );
    
    $paymentSettings = [
        'paystack_enabled' => 1,
        'bank_transfer_enabled' => 1,
        'auto_renew' => 1
    ];
}

// Get available plans for this clan
$availablePlans = $db->fetchAll(
    "SELECT p.* FROM pricing_plans p
     JOIN clan_pricing_plans cpp ON p.id = cpp.pricing_plan_id
     WHERE cpp.clan_id = ?",
    [$clanId]
);

// If no plans are assigned to this clan, get all plans
if (empty($availablePlans)) {
    $availablePlans = $db->fetchAll("SELECT * FROM pricing_plans WHERE is_free = 0");
}

// Get pricing plan ID from URL or use current clan's plan
$pricingPlanId = isset($_GET['plan']) ? intval($_GET['plan']) : $clan->getPricingPlanId();

// Validate that the selected plan is available for this clan
$planIsAvailable = false;
foreach ($availablePlans as $plan) {
    if ($plan['id'] == $pricingPlanId) {
        $planIsAvailable = true;
        break;
    }
}

// If selected plan is not available, use the first available plan
if (!$planIsAvailable && !empty($availablePlans)) {
    $pricingPlanId = $availablePlans[0]['id'];
}

// Get pricing plan details
$pricingPlan = null;
if ($pricingPlanId) {
    $pricingPlan = $db->fetchOne(
        "SELECT * FROM pricing_plans WHERE id = ?",
        [$pricingPlanId]
    );
    
    // Check if plan is free - redirect to settings page
    if ($pricingPlan && $pricingPlan['is_free']) {
        header('Location: ' . BASE_URL . 'payments/settings.php');
        exit;
    }
}

if (!$pricingPlan) {
    // No plan selected, redirect to settings page
    header('Location: ' . BASE_URL . 'payments/settings.php');
    exit;
}

// Get user count if this is a per-user plan
$userCount = 0;
if ($pricingPlan['is_per_user']) {
    $userCount = $db->fetchOne(
        "SELECT COUNT(*) as count FROM users WHERE clan_id = ?", 
        [$clanId]
    )['count'];
}

// Calculate total amount based on pricing type
$totalAmount = $pricingPlan['is_per_user'] 
    ? ($pricingPlan['price_per_user'] * $userCount) 
    : $pricingPlan['price'];


// Process Paystack payment
// Process Paystack payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method']) && $_POST['payment_method'] === 'paystack') {
    // Check if Paystack is enabled
    if (!$paymentSettings['paystack_enabled']) {
        $error = 'Paystack payments are currently disabled. Please choose another payment method.';
    } else {
        try {
            // Initialize Paystack payment
            $paymentIntegration = new PaymentIntegration('paystack');
            
            // Make sure to use our callback.php
            $callbackUrl = BASE_URL . 'payments/callback.php';
            
            // Determine if this is a license purchase based on payment type
            $isPurchasingLicenses = ($paymentType === 'license');
            
            // Get license count from form if it's a license purchase
            $licenseCount = 1;
            if ($isPurchasingLicenses && isset($_POST['license_count'])) {
                $licenseCount = (int)$_POST['license_count'];
                if ($licenseCount < 1) $licenseCount = 1;
            }
            
            // Prepare payment data
            $paymentData = [
                'clan_id' => $clanId,
                'amount' => $totalAmount,
                'email' => $currentUser->getEmail(),
                'pricing_plan_id' => $pricingPlanId,
                'is_per_user' => $pricingPlan['is_per_user'],
                'user_count' => $isPurchasingLicenses ? $licenseCount : $userCount,
                'clan_name' => $clan->getName(),
                'plan_name' => $pricingPlan['name'],
                'callback_url' => $callbackUrl,
                'purpose' => $isPurchasingLicenses ? 'license_purchase' : 'clan_subscription'
            ];
            
            // Set session variables for the callback to recognize license purchases
            if ($isPurchasingLicenses) {
                $_SESSION['is_license_purchase'] = true;
                error_log("Setting session is_license_purchase=true for Paystack payment");
            } else {
                // Make sure the license purchase flag is off for clan subscriptions
                $_SESSION['is_license_purchase'] = false;
                error_log("Setting session is_license_purchase=false for clan subscription payment");
            }
            
            // Initialize the payment
            $response = $paymentIntegration->initializePaystack($paymentData);
            
            if ($response && isset($response['data']['authorization_url'])) {
                // Log information for debugging
                error_log("Successfully initialized Paystack payment. Redirecting to: " . $response['data']['authorization_url']);
                error_log("Payment purpose: " . $paymentData['purpose']);
                
                // Redirect to Paystack payment page
                header('Location: ' . $response['data']['authorization_url']);
                exit;
            } else {
                $error = 'Failed to initialize Paystack payment. Please try again.';
                error_log("Failed to initialize Paystack payment: " . json_encode($response));
            }
        } catch (Exception $e) {
            $error = 'An error occurred during payment processing. Please try again later.';
            error_log('Paystack payment error: ' . $e->getMessage());
        }
    }
}

// Process Bank Transfer payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method']) && $_POST['payment_method'] === 'bank_transfer') {
    // Check if Bank Transfer is enabled
    if (!$paymentSettings['bank_transfer_enabled']) {
        $error = 'Bank Transfer payments are currently disabled. Please choose another payment method.';
    } else {
        try {
            // Create a pending payment record
            $payment = new Payment();
            
            $paymentId = $payment->create([
                'clan_id' => $clanId,
                'amount' => $totalAmount,
                'payment_method' => 'bank_transfer',
                'transaction_id' => 'BT-' . uniqid(),
                'status' => 'pending',
                'pricing_plan_id' => $pricingPlanId,
                'payer_info' => json_encode([
                    'name' => $currentUser->getFullName(),
                    'email' => $currentUser->getEmail(),
                    'clan_name' => $clan->getName()
                ]),
                'is_per_user' => $pricingPlan['is_per_user'],
                'user_count' => $userCount
            ]);
            
            if ($paymentId) {
                $success = 'Bank transfer request has been recorded. Please complete the transfer using the details below, and your account will be activated once the payment is verified.';
                $paymentCompleted = true;
                
                // Get the payment details
                $receipt = $db->fetchOne(
                    "SELECT p.*, pp.name as plan_name, pp.duration_days 
                     FROM payments p 
                     LEFT JOIN pricing_plans pp ON p.pricing_plan_id = pp.id 
                     WHERE p.id = ?",
                    [$paymentId]
                );
            } else {
                $error = 'Failed to record bank transfer request. Please try again or contact support.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred while processing your request. Please try again later.';
            error_log('Bank transfer error: ' . $e->getMessage());
        }
    }
}

// Process USSD payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method']) && $_POST['payment_method'] === 'ussd') {
    // Check if Paystack is enabled
    if (!$paymentSettings['paystack_enabled']) {
        $error = 'USSD payments are currently disabled. Please choose another payment method.';
    } else {
        try {
            // Initialize USSD payment
            $paymentIntegration = new PaymentIntegration('ussd');
            
            $paymentData = [
                'clan_id' => $clanId,
                'amount' => $totalAmount,
                'email' => $currentUser->getEmail(),
                'pricing_plan_id' => $pricingPlanId,
                'is_per_user' => $pricingPlan['is_per_user'],
                'user_count' => $userCount,
                'clan_name' => $clan->getName(),
                'plan_name' => $pricingPlan['name']
            ];
            
            $response = $paymentIntegration->initializeUssd($paymentData);
            
            if ($response && isset($response['data']['ussd_code'])) {
                $success = 'USSD code generated successfully. Please dial: ' . $response['data']['ussd_code'] . ' on your phone to complete the payment.';
                $paymentCompleted = true;
                
                // Get the transaction reference
                $transactionRef = $response['data']['reference'];
                
                // Get the payment details
                $receipt = $db->fetchOne(
                    "SELECT pt.*, pp.name as plan_name, pp.duration_days 
                     FROM payment_transactions pt 
                     LEFT JOIN pricing_plans pp ON pt.pricing_plan_id = pp.id 
                     WHERE pt.reference = ?",
                    [$transactionRef]
                );
                
                if ($receipt) {
                    $receipt['transaction_id'] = $transactionRef;
                    $receipt['status'] = 'pending';
                }
            } else {
                $error = 'Failed to generate USSD code. Please try again or choose another payment method.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred during payment processing. Please try again later.';
            error_log('USSD payment error: ' . $e->getMessage());
        }
    }
}

// Include header
include_once '../includes/header.php';

// Include sidebar
include_once '../includes/sidebar.php';
?>

<!-- Content Area -->
<div class="content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="page-header mb-4">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Process Payment</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>payments/settings.php">Payment Settings</a></li>
                        <li class="breadcrumb-item active">Process Payment</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($paymentCompleted && $receipt): ?>
            <!-- Payment Receipt -->
            <div class="row mb-4">
                <div class="col-md-8 mx-auto">
                    <div class="card shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h5 class="card-title mb-0">Payment <?php echo $receipt['status'] === 'completed' ? 'Receipt' : 'Details'; ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <i class="fas fa-check-circle fa-4x text-success"></i>
                                <h4 class="mt-2">Thank You For Your Payment!</h4>
                                <p class="text-muted">
                                    <?php if ($receipt['status'] === 'completed'): ?>
                                        Your payment has been processed successfully. Your plan is now active.
                                    <?php elseif ($receipt['status'] === 'pending' && $receipt['payment_method'] === 'bank_transfer'): ?>
                                        Your bank transfer request has been recorded. Please complete the payment using the details below.
                                    <?php elseif ($receipt['status'] === 'pending' && $receipt['payment_method'] === 'ussd'): ?>
                                        Your USSD payment is being processed. Your account will be activated once the payment is confirmed.
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Transaction ID:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($receipt['transaction_id'] ?? 'Pending'); ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Payment Method:</div>
                                <div class="col-sm-8">
                                    <?php if ($receipt['payment_method'] === 'paystack'): ?>
                                        <i class="fas fa-credit-card me-1"></i> Paystack
                                    <?php elseif ($receipt['payment_method'] === 'bank_transfer'): ?>
                                        <i class="fas fa-university me-1"></i> Bank Transfer
                                    <?php elseif ($receipt['payment_method'] === 'ussd'): ?>
                                        <i class="fas fa-mobile-alt me-1"></i> USSD
                                    <?php else: ?>
                                        <?php echo ucfirst($receipt['payment_method']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Plan:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($receipt['plan_name'] ?? 'Unknown Plan'); ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Amount:</div>
                                <div class="col-sm-8"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($receipt['amount'], 2); ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Date:</div>
                                <div class="col-sm-8"><?php echo date('F j, Y, g:i a', strtotime($receipt['payment_date'] ?? $receipt['created_at'])); ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Status:</div>
                                <div class="col-sm-8">
                                    <?php if ($receipt['status'] === 'completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php elseif ($receipt['status'] === 'pending'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php elseif ($receipt['status'] === 'failed'): ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($receipt['status']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($receipt['payment_method'] === 'bank_transfer' && $receipt['status'] === 'pending'): ?>
                                <div class="alert alert-info mt-4">
                                    <h6 class="alert-heading">Bank Transfer Details</h6>
                                    <p class="mb-0">Please transfer the amount to the following bank account:</p>
                                    <ul class="list-unstyled mt-2 mb-0">
                                        <li><strong>Bank Name:</strong> <?php echo BANK_NAME; ?></li>
                                        <li><strong>Account Name:</strong> <?php echo BANK_ACCOUNT_NAME; ?></li>
                                        <li><strong>Account Number:</strong> <?php echo BANK_ACCOUNT_NUMBER; ?></li>
                                        <li><strong>Reference:</strong> <?php echo htmlspecialchars($receipt['transaction_id']); ?></li>
                                    </ul>
                                    <p class="mt-2 mb-0"><strong>Important:</strong> Include the reference number in your transfer to help us identify your payment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <a href="<?php echo BASE_URL; ?>payments/receipt.php?id=<?php echo encryptId($receipt['id']); ?>" class="btn btn-primary me-2">
                                <i class="fas fa-file-invoice me-1"></i> View Receipt
                            </a>
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="btn btn-outline-secondary">
                                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Payment Form -->
            <div class="row">
                <div class="col-md-8 mx-auto">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">Payment Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-4">
                                <div class="flex-shrink-0 me-3">
                                    <div class="icon-bg bg-primary-light rounded p-3">
                                        <i class="fas fa-tag text-primary fa-2x"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h4 class="mb-1">
                                        <?php echo htmlspecialchars($pricingPlan['name']); ?>
                                        <?php if ($pricingPlan['is_per_user']): ?>
                                            <span class="badge bg-primary ms-2"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($pricingPlan['price_per_user'], 2); ?> / user</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary ms-2"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($pricingPlan['price'], 2); ?></span>
                                        <?php endif; ?>
                                    </h4>
                                    <p class="text-muted mb-0">
                                        <?php echo htmlspecialchars($pricingPlan['description'] ?? 'No description available.'); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="row">
                                    <div class="col-sm-4 text-sm-end text-muted">Plan Duration:</div>
                                    <div class="col-sm-8"><?php echo $pricingPlan['duration_days']; ?> days</div>
                                </div>
                                
                                <div class="row mt-2">
                                    <div class="col-sm-4 text-sm-end text-muted">Clan Name:</div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($clan->getName()); ?></div>
                                </div>
                                
                                <?php if ($pricingPlan['is_per_user']): ?>
                                <div class="row mt-2">
                                    <div class="col-sm-4 text-sm-end text-muted">Number of Users:</div>
                                    <div class="col-sm-8"><?php echo $userCount; ?></div>
                                </div>
                                
                                <div class="row mt-2">
                                    <div class="col-sm-4 text-sm-end text-muted">Price per User:</div>
                                    <div class="col-sm-8"><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($pricingPlan['price_per_user'], 2); ?></div>
                                </div>
                                
                                <div class="row mt-2">
                                    <div class="col-sm-4 text-sm-end text-muted">Total Amount:</div>
                                    <div class="col-sm-8"><strong><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalAmount, 2); ?></strong></div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="row mt-2">
                                    <div class="col-sm-4 text-sm-end text-muted">Auto-Renew:</div>
                                    <div class="col-sm-8">
                                        <?php if ($paymentSettings['auto_renew']): ?>
                                            <span class="text-success">Enabled</span>
                                        <?php else: ?>
                                            <span class="text-danger">Disabled</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="payment-methods">
                                <h5 class="mb-3">Select Payment Method</h5>
                                
                                <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
                                    <?php if ($paymentSettings['paystack_enabled']): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="paystack-tab" data-bs-toggle="tab" data-bs-target="#paystack-content" type="button" role="tab" aria-controls="paystack-content" aria-selected="true">
                                                <i class="fas fa-credit-card me-1"></i> Paystack
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($paymentSettings['paystack_enabled']): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="ussd-tab" data-bs-toggle="tab" data-bs-target="#ussd-content" type="button" role="tab" aria-controls="ussd-content" aria-selected="false">
                                                <i class="fas fa-mobile-alt me-1"></i> USSD
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php if ($paymentSettings['bank_transfer_enabled']): ?>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link <?php echo !$paymentSettings['paystack_enabled'] ? 'active' : ''; ?>" id="bank-tab" data-bs-toggle="tab" data-bs-target="#bank-content" type="button" role="tab" aria-controls="bank-content" aria-selected="<?php echo !$paymentSettings['paystack_enabled'] ? 'true' : 'false'; ?>">
                                                <i class="fas fa-university me-1"></i> Bank Transfer
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                                
                                <div class="tab-content p-4 border border-top-0 rounded-bottom" id="paymentTabsContent">
                                    <?php if ($paymentSettings['paystack_enabled']): ?>
                                        <div class="tab-pane fade show active" id="paystack-content" role="tabpanel" aria-labelledby="paystack-tab">
    <div class="text-center">
        <div class="mb-4">
            <img src="<?php echo BASE_URL; ?>assets/images/paystack-logo.png" alt="Paystack" height="50" class="mb-3">
            <h5 class="mt-2">Pay with Paystack</h5>
            <p class="text-muted">Securely pay with your credit/debit card via Paystack.</p>
        </div>
        
        <form method="post" action="">
            <input type="hidden" name="payment_method" value="paystack">
            
            <!-- Add license count field -->
            <div class="mb-3">
                <label for="license_count" class="form-label">Number of Licenses</label>
                <select class="form-select" id="license_count" name="license_count">
                    <option value="1">1 License</option>
                    <option value="5">5 Licenses</option>
                    <option value="10">10 Licenses</option>
                    <option value="20">20 Licenses</option>
                    <option value="50">50 Licenses</option>
                    <option value="100">100 Licenses</option>
                </select>
                <div class="form-text">Select how many access licenses you want to purchase.</div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-credit-card me-2"></i> Pay <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($totalAmount, 2); ?>
            </button>
        </form>
    </div>
</div>
                                    <?php endif; ?>
                                    
                                    <?php if ($paymentSettings['paystack_enabled']): ?>
                                        <div class="tab-pane fade" id="ussd-content" role="tabpanel" aria-labelledby="ussd-tab">
                                            <div class="text-center">
                                                <div class="mb-4">
                                                    <i class="fas fa-mobile-alt fa-4x text-primary mb-3"></i>
                                                    <h5 class="mt-2">Pay with USSD</h5>
                                                    <p class="text-muted">Generate a USSD code to make your payment directly from your bank account.</p>
                                                </div>
                                                
                                                <form method="post" action="">
                                                    <input type="hidden" name="payment_method" value="ussd">
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-mobile-alt me-2"></i> Generate USSD Code
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($paymentSettings['bank_transfer_enabled']): ?>
                                        <div class="tab-pane fade <?php echo !$paymentSettings['paystack_enabled'] ? 'show active' : ''; ?>" id="bank-content" role="tabpanel" aria-labelledby="bank-tab">
                                            <div class="alert alert-info">
                                                <h6 class="alert-heading">Bank Transfer Information</h6>
                                                <p>Please transfer the exact amount to the following bank account:</p>
                                                <ul class="list-unstyled mt-2">
                                                    <li><strong>Bank Name:</strong> <?php echo BANK_NAME; ?></li>
                                                    <li><strong>Account Name:</strong> <?php echo BANK_ACCOUNT_NAME; ?></li>
                                                    <li><strong>Account Number:</strong> <?php echo BANK_ACCOUNT_NUMBER; ?></li>
                                                </ul>
                                                <p class="mb-0">After completing the transfer, click the button below to notify us. Your plan will be activated once the payment is verified.</p>
                                            </div>
                                            
                                            <form method="post" action="">
                                                <input type="hidden" name="payment_method" value="bank_transfer">
                                                <div class="mb-3">
                                                    <label for="transfer_details" class="form-label">Transfer Details (Optional)</label>
                                                    <textarea class="form-control" id="transfer_details" name="transfer_details" rows="3" placeholder="Provide any additional details about your transfer"></textarea>
                                                </div>
                                                
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-university me-2"></i> Complete Bank Transfer Request
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <p class="text-muted mb-0">
                                    <i class="fas fa-shield-alt me-1"></i> All transactions are secure and encrypted.
                                </p>
                                <div>
                                    <img src="<?php echo BASE_URL; ?>assets/images/payment-methods.png" alt="Payment Methods" style="height: 24px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mb-4">
                        <a href="<?php echo BASE_URL; ?>payments/settings.php" class="btn btn-link">
                            <i class="fas fa-arrow-left me-1"></i> Back to Payment Settings
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>