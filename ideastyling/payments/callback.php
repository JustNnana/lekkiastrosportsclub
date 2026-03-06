<?php
// payments/callback.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once 'PaymentIntegration.php';
require_once 'payment_constants.php';

// Set page title
$pageTitle = 'Payment Verification';

// Initialize variables
$error = '';
$success = '';
$receipt = null;

// Start logging
error_log("==== PAYMENT CALLBACK: " . date('Y-m-d H:i:s') . " ====");
error_log("Session data: " . json_encode($_SESSION));

// Check if reference is provided
if (!isset($_GET['reference'])) {
    error_log("Callback called without reference");
    header('Location: ' . BASE_URL . 'payments/settings.php');
    exit;
}

$reference = $_GET['reference'];
error_log("Processing callback for reference: " . $reference);

// Check if this is a license purchase (from session)
$isLicensePurchase = isset($_SESSION['is_license_purchase']) && $_SESSION['is_license_purchase'] === true;
error_log("Is license purchase: " . ($isLicensePurchase ? 'yes' : 'no'));

// Get database instance
$db = Database::getInstance();

try {
    // Begin transaction
    $db->beginTransaction();
    
    // Check if payment is already processed
    $existingPayment = $db->fetchOne(
        "SELECT id FROM payments WHERE transaction_id = ?",
        [$reference]
    );
    
    if ($existingPayment) {
        error_log("Payment already processed: " . $reference);
        $receipt = $db->fetchOne(
            "SELECT p.*, pp.name as plan_name, pp.duration_days, pp.price_per_user, pp.is_per_user
             FROM payments p 
             LEFT JOIN pricing_plans pp ON p.pricing_plan_id = pp.id 
             WHERE p.id = ?",
            [$existingPayment['id']]
        );
        
        $success = 'Your payment has been successfully processed.';
        
        // Skip further processing
        $db->commit();
    } else {
        // Get transaction data
        $transaction = $db->fetchOne(
            "SELECT * FROM payment_transactions WHERE reference = ?",
            [$reference]
        );
        
        if (!$transaction) {
            error_log("Transaction not found: " . $reference);
            $error = 'Transaction reference not found. Please contact support.';
            $db->rollBack();
        } else {
            error_log("Found transaction: " . json_encode($transaction));
            
            // Skip if already completed
            if ($transaction['status'] === 'completed') {
                error_log("Transaction already completed");
                $success = 'Your payment has been successfully verified.';
                $receipt = $transaction;
                $db->commit();
            } else {
                // Verify transaction with Paystack
                $paystack_url = "https://api.paystack.co/transaction/verify/" . urlencode($reference);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $paystack_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . PAYSTACK_SECRET_KEY
                ]);
                $response = curl_exec($ch);
                curl_close($ch);
                
                error_log("Paystack verification response: " . $response);
                
                $result = json_decode($response, true);
                
                if (!$result || !isset($result['status']) || !$result['status']) {
                    error_log("Failed to verify with Paystack");
                    $error = 'Payment verification failed. Please contact support.';
                    $db->rollBack();
                } else {
                    // Get payment status from Paystack
                    $paystack_status = $result['data']['status'];
                    error_log("Paystack status: " . $paystack_status);
                    
                    if ($paystack_status === 'success') {
                        // Get the clan
                        $clan = new Clan();
                        if (!$clan->loadById($transaction['clan_id'])) {
                            error_log("Failed to load clan");
                            $error = 'Failed to load clan data. Please contact support.';
                            $db->rollBack();
                        } else {
                            // Get the plan with proper pricing details
                            $plan = $db->fetchOne(
                                "SELECT * FROM pricing_plans WHERE id = ?",
                                [$transaction['pricing_plan_id']]
                            );
                            
                            if (!$plan) {
                                error_log("Failed to load plan");
                                $error = 'Failed to load plan data. Please contact support.';
                                $db->rollBack();
                            } else {
                                // 1. Update transaction status
                                $db->query(
                                    "UPDATE payment_transactions SET status = 'completed', updated_at = NOW() WHERE reference = ?",
                                    [$reference]
                                );
                                
                                // 2. Calculate next payment date
                                $durationDays = $plan['duration_days'] ?: 30;
                                $nextPaymentDate = date('Y-m-d', strtotime("+$durationDays days"));
                                
                                // 3. Determine license count and calculate correct amount
                                $licenseCount = 0; // Default to 0 for clan subscriptions
                                $actualAmount = $transaction['amount']; // Use the amount already paid
                                
                                if ($isLicensePurchase) {
                                    // For license purchases, get license count from transaction
                                    $licenseCount = isset($transaction['user_count']) && $transaction['user_count'] > 0 ? 
                                        (int)$transaction['user_count'] : 1;
                                    
                                    // Verify the amount is correct for license purchase
                                    $expectedAmount = $plan['price_per_user'] * $licenseCount;
                                    error_log("License purchase - Count: $licenseCount, Expected: $expectedAmount, Actual: $actualAmount");
                                } else {
                                    // For clan subscriptions, verify amount based on plan type
                                    if ($plan['is_per_user']) {
                                        // Get current user count for per-user clan subscriptions
                                        $currentUserCount = $db->fetchOne(
                                            "SELECT COUNT(*) as count FROM users WHERE clan_id = ?",
                                            [$transaction['clan_id']]
                                        )['count'] ?? 1;
                                        
                                        $expectedAmount = $plan['price_per_user'] * $currentUserCount;
                                        error_log("Clan subscription (per-user) - Users: $currentUserCount, Expected: $expectedAmount, Actual: $actualAmount");
                                    } else {
                                        // Fixed price plan
                                        $expectedAmount = $plan['price'];
                                        error_log("Clan subscription (fixed) - Expected: $expectedAmount, Actual: $actualAmount");
                                    }
                                }
                                
                                // 4. Check if this is a license purchase
                                $isPurchasingLicenses = $isLicensePurchase || 
                                                      (isset($transaction['purpose']) && $transaction['purpose'] === 'license_purchase');
                                
                                error_log("Is license purchase (from transaction/session): " . ($isPurchasingLicenses ? 'yes' : 'no'));
                                
                               // 5. Update clan based on payment type
if ($isPurchasingLicenses) {
    // For license purchases, only increment available licenses without changing payment status
    $db->query(
        "UPDATE clans SET 
            available_licenses = available_licenses + ?,
            updated_at = NOW()
        WHERE id = ?",
        [$licenseCount, $transaction['clan_id']]
    );
    
    error_log("Adding $licenseCount licenses to clan {$transaction['clan_id']} without changing payment status");
} else {
    // For clan subscriptions, update payment status and next payment date WITHOUT adding licenses
    $db->query(
        "UPDATE clans SET 
            payment_status = 'active', 
            pricing_plan_id = ?, 
            next_payment_date = ?,
            updated_at = NOW()
        WHERE id = ?",
        [$transaction['pricing_plan_id'], $nextPaymentDate, $transaction['clan_id']]
    );
    
    error_log("Updating clan {$transaction['clan_id']} payment status to active and next payment date, WITHOUT adding licenses");
}

// ========== ADD HOUSEHOLD LICENSE HANDLING HERE ==========
// 5.5. Check if this is a household license purchase and process it
if (isset($_SESSION['household_license_payment'])) {
    $householdLicenseData = $_SESSION['household_license_payment'];
    $householdId = $householdLicenseData['household_id'];
    $householdLicenseCount = $householdLicenseData['license_count'];
    
    error_log("Processing household license purchase: Household ID=$householdId, Licenses=$householdLicenseCount");
    
    try {
        // Update household's extra_member_licenses
        $db->query(
            "UPDATE households 
             SET extra_member_licenses = extra_member_licenses + ?, 
                 updated_at = NOW() 
             WHERE id = ?",
            [$householdLicenseCount, $householdId]
        );
        
        // Record in household_licenses table
        $db->query(
            "INSERT INTO household_licenses 
             (household_id, license_count, price_paid, payment_reference, payment_method, purchased_by, status) 
             VALUES (?, ?, ?, ?, ?, ?, 'active')",
            [
                $householdId,
                $householdLicenseCount,
                $actualAmount,
                $reference,
                $transaction['payment_method'],
                $_SESSION['user_id'] ?? null
            ]
        );
        
        // Get household name for notification
        $household = $db->fetchOne("SELECT name FROM households WHERE id = ?", [$householdId]);
        $householdName = $household ? $household['name'] : 'Household';
        
        // Update the purpose and notification message for household licenses
        $isPurchasingLicenses = true; // Override to show correct notification
        $licenseCount = $householdLicenseCount; // Use household license count
        
        // This will be used in the notification section below
        $_SESSION['household_purchase_info'] = [
            'household_name' => $householdName,
            'license_count' => $householdLicenseCount
        ];
        
        error_log("Successfully added {$householdLicenseCount} licenses to household {$householdId}");
        
    } catch (Exception $e) {
        error_log("Error updating household licenses: " . $e->getMessage());
        // Don't fail the entire transaction, but log the error
    }
}
// ========== END HOUSEHOLD LICENSE HANDLING ==========

// 6. Create payment record with correct amount and license information
$db->query(
    "INSERT INTO payments (
        clan_id, 
        amount, 
        payment_method, 
        transaction_id, 
        status, 
        payment_date, 
        pricing_plan_id, 
        period_start, 
        period_end,
        is_per_user,
        user_count,
        license_count,
        purpose
    ) VALUES (?, ?, ?, ?, ?, NOW(), ?, NOW(), ?, ?, ?, ?, ?)",
    [
        $transaction['clan_id'],
        $actualAmount,
        $transaction['payment_method'],
        $reference,
        'completed',
        $transaction['pricing_plan_id'],
        $nextPaymentDate,
        $transaction['is_per_user'] ?? 0,
        $transaction['user_count'] ?? 0,
        $licenseCount,
        isset($_SESSION['household_license_payment']) ? 'household_license_purchase' : 
            ($isPurchasingLicenses ? 'license_purchase' : 'clan_subscription')
    ]
);
                                
                                $paymentId = $db->lastInsertId();
                                
                               // 7. Create notification with correct amount
$adminId = $clan->getAdminId();
if ($adminId) {
    // Customize notification based on payment type
    $notificationTitle = "Payment Successful";
    $notificationMessage = "Your payment of ₦" . number_format($actualAmount, 2) . " has been processed successfully.";
    
    // Check if this is a household license purchase
    if (isset($_SESSION['household_purchase_info'])) {
        $householdInfo = $_SESSION['household_purchase_info'];
        $notificationMessage .= " You have purchased {$householdInfo['license_count']} member license" . 
                              ($householdInfo['license_count'] > 1 ? 's' : '') . 
                              " for {$householdInfo['household_name']}.";
        $notificationMessage .= " Each license costs ₦" . number_format($plan['price_per_user'], 2) . ".";
        $notificationMessage .= " The household capacity has been increased.";
        
        // Clear the session data
        unset($_SESSION['household_purchase_info']);
        unset($_SESSION['household_license_payment']);
        
    } elseif ($isPurchasingLicenses) {
        $notificationMessage .= " You have received $licenseCount new user license" . ($licenseCount > 1 ? 's' : '') . ".";
        $notificationMessage .= " Each license costs ₦" . number_format($plan['price_per_user'], 2) . ".";
    } else {
        $notificationMessage .= " Your clan subscription is now active.";
        if ($plan['is_per_user']) {
            // Get household count instead of user count
            require_once __DIR__ . '/../classes/Payment.php';
            $householdCount = Payment::getBillableHouseholdCount($transaction['clan_id']);
            
            $notificationMessage .= " Plan covers $householdCount household" . ($householdCount > 1 ? 's' : '') . " at ₦" . 
                                   number_format($plan['price_per_user'], 2) . " per household.";
            
            // Get breakdown for additional info
            $breakdown = Payment::getBillableHouseholdBreakdown($transaction['clan_id']);
            if ($breakdown['recently_inactive'] > 0) {
                $notificationMessage .= " (Includes " . $breakdown['recently_inactive'] . 
                                      " recently inactive household" . ($breakdown['recently_inactive'] > 1 ? 's' : '') . ")";
            }
        }
    }
    
    $db->query(
        "INSERT INTO notifications (
            user_id, clan_id, title, message, type, reference_id, reference_type, is_read, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())",
        [
            $adminId,
            $transaction['clan_id'],
            $notificationTitle,
            $notificationMessage,
            'payment_success',
            $paymentId,
            'payment'
        ]
    );
}
                                
                                // Get the created payment as receipt with all details
                                $receipt = $db->fetchOne(
                                    "SELECT p.*, pp.name as plan_name, pp.duration_days, pp.price_per_user, pp.is_per_user, pp.price as plan_base_price
                                     FROM payments p 
                                     LEFT JOIN pricing_plans pp ON p.pricing_plan_id = pp.id 
                                     WHERE p.id = ?",
                                    [$paymentId]
                                );
                                
                                $success = 'Your payment has been successfully processed. Thank you!';
                                $db->commit();
                                error_log("Payment successfully completed for reference: " . $reference);
                                
                                // Clear the session variables
                                unset($_SESSION['is_license_purchase']);
                                unset($_SESSION['license_payment_id']);
                            }
                        }
                    } else {
                        error_log("Paystack status is not 'success': " . $paystack_status);
                        $error = 'Payment was not successful. Status: ' . ucfirst($paystack_status);
                        $db->rollBack();
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Callback error: " . $e->getMessage());
    $error = 'An error occurred: ' . $e->getMessage();
    
    // Rollback transaction if in progress
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
}

// Ensure user is logged in, try to login as clan admin if needed
if (!isset($_SESSION['user_id']) && $transaction) {
    $clanAdmin = $db->fetchOne(
        "SELECT u.id, u.username, u.role FROM users u 
         JOIN clans c ON u.id = c.admin_id 
         WHERE c.id = ? LIMIT 1",
        [$transaction['clan_id']]
    );
    
    if ($clanAdmin) {
        $_SESSION['user_id'] = $clanAdmin['id'];
        $_SESSION['username'] = $clanAdmin['username'];
        $_SESSION['role'] = $clanAdmin['role'];
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
                    <h3 class="page-title">Payment Verification</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>payments/settings.php">Payment Settings</a></li>
                        <li class="breadcrumb-item active">Verification</li>
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
        
        <?php if ($receipt): ?>
            <!-- Payment Receipt -->
            <div class="row mb-4">
                <div class="col-md-8 mx-auto">
                    <div class="card shadow-sm">
                        <div class="card-header bg-<?php echo $receipt['status'] === 'completed' ? 'success' : 'warning'; ?> text-white">
                            <h5 class="card-title mb-0">Payment <?php echo $receipt['status'] === 'completed' ? 'Receipt' : 'Details'; ?></h5>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <i class="fas fa-<?php echo $receipt['status'] === 'completed' ? 'check-circle' : 'clock'; ?> fa-4x text-<?php echo $receipt['status'] === 'completed' ? 'success' : 'warning'; ?>"></i>
                                <h4 class="mt-2"><?php echo $receipt['status'] === 'completed' ? 'Thank You For Your Payment!' : 'Payment Verification Pending'; ?></h4>
                                <p class="text-muted">
                                    <?php if ($receipt['status'] === 'completed'): ?>
                                        <?php if (isset($receipt['purpose']) && $receipt['purpose'] === 'license_purchase'): ?>
                                            Your payment has been processed successfully. User licenses have been added to your account.
                                        <?php else: ?>
                                            Your payment has been processed successfully. Your plan is now active.
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Your payment is being processed. It may take a few minutes to complete.
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Transaction ID:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($reference); ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Payment Method:</div>
                                <div class="col-sm-8">
                                    <i class="fas fa-credit-card me-1"></i> Paystack
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Plan:</div>
                                <div class="col-sm-8"><?php echo htmlspecialchars($receipt['plan_name'] ?? 'Unknown Plan'); ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Amount Paid:</div>
                                <div class="col-sm-8">
                                    <strong>₦<?php echo number_format($receipt['amount'], 2); ?></strong>
                                </div>
                            </div>
                            
                            <?php if (isset($receipt['purpose']) && $receipt['purpose'] === 'license_purchase' && isset($receipt['license_count']) && $receipt['license_count'] > 0): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-4 text-sm-end text-muted">Licenses Purchased:</div>
                                    <div class="col-sm-8">
                                        <strong><?php echo $receipt['license_count']; ?></strong> user license<?php echo $receipt['license_count'] > 1 ? 's' : ''; ?>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-sm-4 text-sm-end text-muted">Price per License:</div>
                                    <div class="col-sm-8">
                                        ₦<?php echo number_format($receipt['price_per_user'], 2); ?>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-sm-4 text-sm-end text-muted">Calculation:</div>
                                    <div class="col-sm-8">
                                        <?php echo $receipt['license_count']; ?> license<?php echo $receipt['license_count'] > 1 ? 's' : ''; ?> × 
                                        ₦<?php echo number_format($receipt['price_per_user'], 2); ?> = 
                                        ₦<?php echo number_format($receipt['license_count'] * $receipt['price_per_user'], 2); ?>
                                    </div>
                                </div>
                            <?php elseif (isset($receipt['is_per_user']) && $receipt['is_per_user'] && $receipt['purpose'] === 'clan_subscription'): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-4 text-sm-end text-muted">Users Covered:</div>
                                    <div class="col-sm-8">
                                        <?php echo $receipt['user_count']; ?> user<?php echo $receipt['user_count'] > 1 ? 's' : ''; ?>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-sm-4 text-sm-end text-muted">Price per User:</div>
                                    <div class="col-sm-8">
                                        ₦<?php echo number_format($receipt['price_per_user'], 2); ?>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-sm-4 text-sm-end text-muted">Calculation:</div>
                                    <div class="col-sm-8">
                                        <?php echo $receipt['user_count']; ?> user<?php echo $receipt['user_count'] > 1 ? 's' : ''; ?> × 
                                        ₦<?php echo number_format($receipt['price_per_user'], 2); ?> = 
                                        ₦<?php echo number_format($receipt['user_count'] * $receipt['price_per_user'], 2); ?>
                                    </div>
                                </div>
                            <?php elseif (!isset($receipt['is_per_user']) || !$receipt['is_per_user']): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-4 text-sm-end text-muted">Plan Type:</div>
                                    <div class="col-sm-8">Fixed Price Plan</div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-sm-4 text-sm-end text-muted">Plan Price:</div>
                                    <div class="col-sm-8">
                                        ₦<?php echo number_format($receipt['plan_base_price'] ?? $receipt['amount'], 2); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Date:</div>
                                <div class="col-sm-8"><?php echo isset($receipt['payment_date']) ? date('F j, Y, g:i a', strtotime($receipt['payment_date'])) : date('F j, Y, g:i a', strtotime($receipt['created_at'])); ?></div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-sm-4 text-sm-end text-muted">Status:</div>
                                <div class="col-sm-8">
                                    <?php if ($receipt['status'] === 'completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php elseif ($receipt['status'] === 'pending'): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($receipt['status']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white text-center">
                            <?php if (isset($receipt['id'])): ?>
                                <a href="<?php echo BASE_URL; ?>payments/receipt.php?id=<?php echo encryptId($receipt['id']); ?>" class="btn btn-primary me-2">
                                    <i class="fas fa-file-invoice me-1"></i> View Receipt
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="btn btn-outline-secondary">
                                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row mb-4">
                <div class="col-md-6 mx-auto">
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-exclamation-circle fa-4x text-danger mb-3"></i>
                            <h4>Transaction Reference Not Found</h4>
                            <p class="text-muted">We couldn't find any transaction with the provided reference. Please contact support if you believe this is an error.</p>
                            <a href="<?php echo BASE_URL; ?>payments/settings.php" class="btn btn-primary mt-3">
                                <i class="fas fa-arrow-left me-1"></i> Back to Payment Settings
                            </a>
                        </div>
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