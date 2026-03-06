<?php
// At the top of webhook.php
error_log("Testing database connection...");
try {
    $testQuery = $db->query("SELECT 1");
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
}

// payments/webhook.php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/Clan.php';
require_once '../classes/User.php';
require_once 'PaymentIntegration.php';
require_once 'payment_constants.php';

// Initialize error logging
error_log("==== WEBHOOK CALLED: " . date('Y-m-d H:i:s') . " ====");

// Get database instance
$db = Database::getInstance();

try {
    // Retrieve the request body
    $input = file_get_contents('php://input');
    error_log("Webhook input: " . $input);
    
    // Parse as JSON
    $event = json_decode($input, true);
    if (!$event) {
        error_log("Failed to parse webhook JSON");
        http_response_code(400);
        exit();
    }
    
    // Extract reference from the event
    if (!isset($event['data']['reference'])) {
        error_log("No reference found in webhook data");
        http_response_code(400);
        exit();
    }
    
    $reference = $event['data']['reference'];
    error_log("Processing reference: " . $reference);
    
    // Begin transaction
    $db->beginTransaction();
    
    // 1. Get the pending transaction
    $transaction = $db->fetchOne(
        "SELECT * FROM payment_transactions WHERE reference = ? AND status = 'pending'",
        [$reference]
    );
    
    if (!$transaction) {
        error_log("Transaction not found or already processed: " . $reference);
        $db->rollBack();
        http_response_code(200); // Still return 200 to avoid Paystack retries
        exit();
    }
    
    error_log("Found transaction: " . json_encode($transaction));
    
    // 2. Get clan info
    $clan = new Clan();
    if (!$clan->loadById($transaction['clan_id'])) {
        error_log("Failed to load clan ID: " . $transaction['clan_id']);
        $db->rollBack();
        http_response_code(500);
        exit();
    }
    
    // 3. Get pricing plan info
    $plan = $db->fetchOne(
        "SELECT * FROM pricing_plans WHERE id = ?",
        [$transaction['pricing_plan_id']]
    );
    
    if (!$plan) {
        error_log("Failed to load pricing plan ID: " . $transaction['pricing_plan_id']);
        $db->rollBack();
        http_response_code(500);
        exit();
    }
    
    // 4. Update transaction status
    $db->query(
        "UPDATE payment_transactions SET status = 'completed', updated_at = NOW() WHERE id = ?",
        [$transaction['id']]
    );
    
    // 5. Calculate next payment date
    $durationDays = $plan['duration_days'] ?: 30;
    $nextPaymentDate = date('Y-m-d', strtotime("+$durationDays days"));
    
    // 6. Check if this is a license purchase by examining the purpose field
    $isPurchasingLicenses = isset($transaction['purpose']) && $transaction['purpose'] === 'license_purchase';
    error_log("Transaction purpose: " . ($isPurchasingLicenses ? 'license_purchase' : 'clan_subscription'));
    
    // 7. Determine license count (only for license purchases)
    $licenseCount = 0; // Default to 0 for clan subscriptions
    if ($isPurchasingLicenses) {
        $licenseCount = isset($transaction['user_count']) && $transaction['user_count'] > 0 ? 
            (int)$transaction['user_count'] : 1;
        error_log("License count for purchase: $licenseCount");
    }
    
    // 8. Update clan based on transaction type
    if ($isPurchasingLicenses) {
        // For license purchases, only increment available licenses without changing payment status
        $result = $db->query(
            "UPDATE clans SET 
                available_licenses = available_licenses + ?,
                updated_at = NOW()
            WHERE id = ?",
            [$licenseCount, $transaction['clan_id']]
        );
        
        error_log("Adding $licenseCount licenses to clan {$transaction['clan_id']} without changing payment status");
    } else {
        // For clan subscriptions, update payment status and next payment date WITHOUT adding licenses
        $result = $db->query(
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
    
    if (!$result) {
        error_log("Failed to update clan");
        $db->rollBack();
        http_response_code(500);
        exit();
    }
    
    // 9. Create payment record
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
            $transaction['amount'],
            $transaction['payment_method'],
            $reference,
            'completed',
            $transaction['pricing_plan_id'],
            $nextPaymentDate,
            $transaction['is_per_user'] ?? 0,
            $transaction['user_count'] ?? 0,
            $licenseCount, // Only add licenses for license purchases
            $isPurchasingLicenses ? 'license_purchase' : 'clan_subscription'
        ]
    );
    
    $paymentId = $db->lastInsertId();
    if (!$paymentId) {
        error_log("Failed to create payment record");
        $db->rollBack();
        http_response_code(500);
        exit();
    }
    
    // 10. Create notification
    $adminId = $clan->getAdminId();
    if ($adminId) {
        // Create notification with appropriate message based on payment type
        $notificationMessage = "Your payment of " . CURRENCY_SYMBOL . number_format($transaction['amount'], 2) . " has been processed successfully.";
        
        if ($isPurchasingLicenses) {
            $notificationMessage .= " You have received $licenseCount new user licenses.";
        } else {
            $notificationMessage .= " Your clan subscription is now active.";
        }
        
        $db->query(
            "INSERT INTO notifications (
                user_id, clan_id, title, message, type, reference_id, reference_type, is_read, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())",
            [
                $adminId,
                $transaction['clan_id'],
                "Payment Successful",
                $notificationMessage,
                'payment_success',
                $paymentId,
                'payment'
            ]
        );
    }
    
    // Commit transaction
    $db->commit();
    error_log("Payment processed successfully for reference: " . $reference);
    
    // Return success
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    // Log the error
    error_log("Webhook error: " . $e->getMessage());
    
    // Rollback transaction if in progress
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Return error
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}