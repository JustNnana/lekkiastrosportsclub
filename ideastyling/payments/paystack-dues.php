<?php
/**
 * Gate Wey Access Management System
 * Paystack Webhook Handler for Clan Dues
 * File: webhooks/paystack-dues.php
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Set content type
header('Content-Type: application/json');

// Get the input
$input = @file_get_contents("php://input");
$event = json_decode($input, true);

// Log webhook for debugging
error_log("Paystack Webhook Received: " . $input);

// Verify webhook signature (important for security)
function verifyWebhookSignature($input, $signature, $secret) {
    return hash_hmac('sha512', $input, $secret) === $signature;
}

// Response function
function sendResponse($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// Check if we have the required data
if (!$event || !isset($event['event']) || !isset($event['data'])) {
    sendResponse('error', 'Invalid webhook data');
}

// Get database instance
$db = Database::getInstance();

try {
    $eventType = $event['event'];
    $data = $event['data'];
    
    // We're only interested in successful charge events
    if ($eventType !== 'charge.success') {
        sendResponse('ignored', 'Event type not handled');
    }
    
    $reference = $data['reference'] ?? '';
    if (empty($reference)) {
        sendResponse('error', 'No reference found');
    }
    
    // Check if this is a clan dues payment
    if (!str_starts_with($reference, 'dues_')) {
        sendResponse('ignored', 'Not a clan dues payment');
    }
    
    // Find the payment record
    $paymentData = $db->fetchOne(
        "SELECT cdp.*, cd.clan_id, cd.title as dues_title
         FROM clan_dues_payments cdp 
         JOIN clan_dues cd ON cdp.clan_dues_id = cd.id 
         WHERE cdp.paystack_reference = ?",
        [$reference]
    );
    
    if (!$paymentData) {
        sendResponse('error', 'Payment record not found');
    }
    
    // Check if payment is already processed
    if ($paymentData['status'] === 'paid') {
        sendResponse('success', 'Payment already processed');
    }
    
    // Get clan payment settings to verify webhook
    $clanPaymentSettings = $db->fetchOne(
        "SELECT paystack_secret_key FROM clan_payment_settings WHERE clan_id = ?",
        [$paymentData['clan_id']]
    );
    
    if (!$clanPaymentSettings) {
        sendResponse('error', 'Clan payment settings not found');
    }
    
    // Verify webhook signature
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    if (!verifyWebhookSignature($input, $signature, $clanPaymentSettings['paystack_secret_key'])) {
        error_log("Webhook signature verification failed for reference: " . $reference);
        sendResponse('error', 'Invalid signature');
    }
    
    // Verify payment amount (convert from kobo)
    $paidAmount = $data['amount'] / 100;
    $expectedAmount = floatval($paymentData['total_amount']);
    
    if (abs($paidAmount - $expectedAmount) >= 0.01) {
        sendResponse('error', 'Amount mismatch');
    }
    
    // Verify payment status
    if ($data['status'] !== 'success') {
        sendResponse('error', 'Payment not successful');
    }
    
    // Update payment record
    $updateSql = "UPDATE clan_dues_payments 
                 SET payment_date = NOW(), 
                     status = 'paid', 
                     payment_method = 'paystack',
                     paystack_transaction_id = ?,
                     notes = ?
                 WHERE id = ?";
    
    $notes = 'Payment completed via Paystack webhook. Transaction ID: ' . $data['id'];
    
    if ($db->query($updateSql, [$data['id'], $notes, $paymentData['id']])) {
        
        // Log payment activity
        $db->query(
            "INSERT INTO payment_logs (payment_id, payment_type, action, details, created_at) 
             VALUES (?, 'clan_dues', 'webhook_processed', ?, NOW())",
            [$paymentData['id'], 'Paystack webhook processed successfully']
        );
        
        // Create notification for user
        $db->query(
            "INSERT INTO notifications (user_id, clan_id, title, message, type, reference_id, reference_type, created_at) 
             VALUES (?, ?, ?, ?, 'payment_success', ?, 'clan_dues_payment', NOW())",
            [
                $paymentData['user_id'],
                $paymentData['clan_id'],
                'Payment Successful',
                "Your payment of ₦" . number_format($expectedAmount, 2) . " for {$paymentData['dues_title']} has been completed successfully.",
                $paymentData['id']
            ]
        );
        
        // Create notifications for clan admins - CORRECTED: using 'status' instead of 'is_active'
        $clanAdmins = $db->fetchAll(
            "SELECT id FROM users WHERE clan_id = ? AND role = 'clan_admin' AND status = 'active'",
            [$paymentData['clan_id']]
        );
        
        foreach ($clanAdmins as $admin) {
            $db->query(
                "INSERT INTO notifications (user_id, clan_id, title, message, type, reference_id, reference_type, created_at) 
                 VALUES (?, ?, ?, ?, 'payment_received', ?, 'clan_dues_payment', NOW())",
                [
                    $admin['id'],
                    $paymentData['clan_id'],
                    'Payment Received',
                    "A dues payment of ₦" . number_format($expectedAmount, 2) . " has been received for {$paymentData['dues_title']}.",
                    $paymentData['id']
                ]
            );
        }
        
        // Send email notification (if email system is configured)
        if (function_exists('sendEmail')) {
            $userDetails = $db->fetchOne("SELECT email, full_name FROM users WHERE id = ?", [$paymentData['user_id']]);
            if ($userDetails) {
                sendEmail(
                    $userDetails['email'],
                    'Payment Confirmation - ' . $paymentData['dues_title'],
                    "Dear {$userDetails['full_name']},\n\nYour payment of ₦" . number_format($expectedAmount, 2) . " for {$paymentData['dues_title']} has been successfully processed.\n\nTransaction Reference: {$reference}\n\nThank you!"
                );
            }
        }
        
        sendResponse('success', 'Payment processed successfully');
        
    } else {
        sendResponse('error', 'Failed to update payment record');
    }
    
} catch (Exception $e) {
    error_log("Paystack webhook error: " . $e->getMessage());
    sendResponse('error', 'Internal server error');
}

?>

<!-- 
WEBHOOK SETUP INSTRUCTIONS:

1. Place this file at: webhooks/paystack-dues.php

2. Configure webhook URL in each clan's Paystack dashboard:
   - URL: https://yourdomain.com/gate-wey/webhooks/paystack-dues.php
   - Events: charge.success

3. Important security notes:
   - This webhook verifies the signature using each clan's secret key
   - Only processes clan dues payments (reference starts with 'dues_')
   - Prevents duplicate processing
   - Logs all webhook attempts for debugging

4. Required permissions for this file:
   - Web server must be able to write to error logs
   - Database connection must be established
   - No authentication required (webhook endpoint)

5. Testing the webhook:
   - Use Paystack's webhook testing tool
   - Check error logs for debugging information
   - Monitor payment_logs table for processed webhooks

6. Fallback handling:
   - If webhook fails, payment callback page still handles verification
   - Webhook is primarily for real-time notifications and automation
   - Manual payment marking is still available for admins

7. Email notifications (optional):
   - Implement sendEmailNotification() function if needed
   - Can integrate with your preferred email service
   - Currently just creates in-app notifications
-->