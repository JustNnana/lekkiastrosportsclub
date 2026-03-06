<?php
require_once __DIR__ . '/payment_constants.php';
require_once __DIR__ . '/../includes/db.php';

class PaymentIntegration {
    private $db;
    private $paymentMethod;
    private $secretKey;
    private $publicKey;
    private $merchantReference;
    private $callbackUrl;
    
    /**
     * Constructor
     * 
     * @param string $paymentMethod Payment method to use (paystack, bank_transfer, ussd)
     */
    public function __construct($paymentMethod = 'paystack') {
        $this->db = Database::getInstance();
        $this->paymentMethod = $paymentMethod;
        $this->loadConfiguration();
        
        // Generate unique merchant reference for this transaction
        $this->merchantReference = 'GW' . time() . rand(1000, 9999);
        
        // Set callback URL (for payment verification)
        $this->callbackUrl = BASE_URL . 'payments/webhook.php';
    }
    
    /**
     * Load payment gateway configuration based on selected method
     */
    private function loadConfiguration() {
        switch ($this->paymentMethod) {
            case 'paystack':
                // Paystack Configuration
                $this->secretKey = PAYSTACK_SECRET_KEY;
                $this->publicKey = PAYSTACK_PUBLIC_KEY;
                break;
                
            case 'ussd':
                // USSD Configuration (integrated with Paystack)
                $this->secretKey = PAYSTACK_SECRET_KEY;
                $this->publicKey = PAYSTACK_PUBLIC_KEY;
                break;
                
            case 'bank_transfer':
                // Bank Transfer doesn't need API keys
                break;
                
            default:
                // Default to Paystack
                $this->secretKey = PAYSTACK_SECRET_KEY;
                $this->publicKey = PAYSTACK_PUBLIC_KEY;
        }
    }
    
    /**
     * Initialize payment with Paystack
     * 
     * @param array $paymentData Payment data
     * @return array|bool Response data or false on failure
     */
    public function initializePaystack($paymentData) {
        $url = "https://api.paystack.co/transaction/initialize";
        
        // Calculate the CORRECT amount based on payment data
        $amount = $this->calculatePaymentAmount($paymentData);
        
        error_log("Paystack initialization - Calculated amount: " . $amount);
        error_log("Payment data: " . json_encode($paymentData));
        
        // Use the provided callback URL or default to our own
        $callbackUrl = isset($paymentData['callback_url']) ? $paymentData['callback_url'] : (BASE_URL . 'payments/callback.php');
        
        // Determine if this is a license purchase
        $isLicensePurchase = isset($paymentData['purpose']) && $paymentData['purpose'] === 'license_purchase';
        
        // Prepare request data
        $data = [
            'amount' => $amount * 100, // Paystack requires amount in kobo
            'email' => $paymentData['email'],
            'reference' => $this->merchantReference,
            'callback_url' => $callbackUrl,
            'currency' => 'NGN', // Nigerian Naira
            'metadata' => [
                'clan_id' => $paymentData['clan_id'],
                'plan_id' => $paymentData['pricing_plan_id'],
                'is_per_user' => $paymentData['is_per_user'] ?? false,
                'user_count' => $paymentData['user_count'] ?? 1,
                'purpose' => $paymentData['purpose'] ?? 'clan_subscription',
                'custom_fields' => [
                    [
                        'display_name' => 'Clan Name',
                        'variable_name' => 'clan_name',
                        'value' => $paymentData['clan_name'] ?? 'Unknown Clan'
                    ],
                    [
                        'display_name' => 'Plan Name',
                        'variable_name' => 'plan_name',
                        'value' => $paymentData['plan_name'] ?? 'Unknown Plan'
                    ],
                    [
                        'display_name' => 'Payment Purpose',
                        'variable_name' => 'purpose',
                        'value' => $isLicensePurchase ? 'License Purchase' : 'Clan Subscription'
                    ]
                ]
            ]
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->secretKey,
                "Content-Type: application/json",
                "Cache-Control: no-cache"
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            error_log("Paystack API Error: " . $err);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if ($result && $result['status']) {
            // Save transaction reference to database with CORRECT amount
            $this->saveTransaction($paymentData, $this->merchantReference, $result, $amount);
            return $result;
        }
        
        error_log("Paystack Transaction Initialization Failed: " . ($result['message'] ?? 'Unknown error'));
        return false;
    }
    
    /**
     * Calculate the correct payment amount based on payment data
     * 
     * @param array $paymentData Payment data
     * @return float Calculated amount
     */
    private function calculatePaymentAmount($paymentData) {
        // Check if amount is already correctly calculated and passed
        if (isset($paymentData['calculated_amount'])) {
            return $paymentData['calculated_amount'];
        }
        
        // Get the pricing plan details
        $plan = $this->db->fetchOne(
            "SELECT * FROM pricing_plans WHERE id = ?",
            [$paymentData['pricing_plan_id']]
        );
        
        if (!$plan) {
            error_log("Plan not found for ID: " . $paymentData['pricing_plan_id']);
            return 0;
        }
        
// Check if this is a household license purchase
$isHouseholdLicensePurchase = isset($paymentData['purpose']) && 
                               $paymentData['purpose'] === 'household_license_purchase';

if ($isHouseholdLicensePurchase) {
    // For household license purchases: price_per_user × license count
    $licenseCount = isset($paymentData['user_count']) && $paymentData['user_count'] > 0 ? 
        (int)$paymentData['user_count'] : 1;
    $amount = $plan['price_per_user'] * $licenseCount;
    
    error_log("Household license purchase calculation: {$plan['price_per_user']} × {$licenseCount} = {$amount}");
} else {
    // For clan subscriptions
    if ($plan['is_per_user']) {
        // NEW LOGIC: Per-household plan instead of per-user
        // Count billable households (active + inactive for less than 15 days)
        require_once __DIR__ . '/../classes/Payment.php';
        $householdCount = Payment::getBillableHouseholdCount($paymentData['clan_id']);
        
        // Ensure at least 1 household is counted
        $householdCount = max(1, $householdCount);
        
        $amount = $plan['price_per_user'] * $householdCount;
        
        error_log("Per-household subscription calculation: {$plan['price_per_user']} × {$householdCount} households = {$amount}");
        
        // Store household count in payment data for record keeping
        $paymentData['user_count'] = $householdCount; // Reusing user_count field for household count
    } else {
        // Fixed plan: use plan price
        $amount = $plan['price'];
        
        error_log("Fixed plan price: {$amount}");
    }
}

return $amount;
    }
    
    /**
     * Initialize USSD payment
     * 
     * @param array $paymentData Payment data
     * @return array|bool Response data or false on failure
     */
    public function initializeUssd($paymentData) {
        // USSD payments are implemented via Paystack
        
        $url = "https://api.paystack.co/transaction/initialize";
        
        // Calculate the CORRECT amount
        $amount = $this->calculatePaymentAmount($paymentData);
        
        // Prepare request data
        $data = [
            'amount' => $amount * 100, // Paystack requires amount in kobo
            'email' => $paymentData['email'],
            'reference' => $this->merchantReference,
            'currency' => 'NGN', // Nigerian Naira
            'channels' => ['ussd'],
            'metadata' => [
                'clan_id' => $paymentData['clan_id'],
                'plan_id' => $paymentData['pricing_plan_id'],
                'is_per_user' => $paymentData['is_per_user'] ?? false,
                'user_count' => $paymentData['user_count'] ?? 1,
                'purpose' => $paymentData['purpose'] ?? 'clan_subscription',
                'custom_fields' => [
                    [
                        'display_name' => 'Clan Name',
                        'variable_name' => 'clan_name',
                        'value' => $paymentData['clan_name'] ?? 'Unknown Clan'
                    ],
                    [
                        'display_name' => 'Plan Name',
                        'variable_name' => 'plan_name',
                        'value' => $paymentData['plan_name'] ?? 'Unknown Plan'
                    ]
                ]
            ]
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->secretKey,
                "Content-Type: application/json",
                "Cache-Control: no-cache"
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            error_log("USSD Payment API Error: " . $err);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if ($result && $result['status']) {
            // Save transaction reference to database with CORRECT amount
            $this->saveTransaction($paymentData, $this->merchantReference, $result, $amount);
            return $result;
        }
        
        error_log("USSD Payment Initialization Failed: " . ($result['message'] ?? 'Unknown error'));
        return false;
    }
    
    /**
     * Process bank transfer payment
     * 
     * @param array $paymentData Payment data
     * @return array|bool Response data or false on failure
     */
    public function processBankTransfer($paymentData) {
        // For bank transfers, we create a pending transaction and provide bank details to the user
        
        try {
            // Generate a unique payment reference
            $reference = $this->merchantReference;
            
            // Calculate the CORRECT amount
            $amount = $this->calculatePaymentAmount($paymentData);
            
            // Create a pending payment record with CORRECT amount
            $this->saveTransaction($paymentData, $reference, [
                'status' => 'pending',
                'message' => 'Bank transfer pending verification',
                'bank_details' => [
                    'bank_name' => BANK_NAME,
                    'account_name' => BANK_ACCOUNT_NAME,
                    'account_number' => BANK_ACCOUNT_NUMBER,
                    'reference' => $reference
                ]
            ], $amount);
            
            // Return bank account details and payment reference
            return [
                'status' => true,
                'message' => 'Bank transfer initialized',
                'data' => [
                    'reference' => $reference,
                    'bank_name' => BANK_NAME,
                    'account_name' => BANK_ACCOUNT_NAME,
                    'account_number' => BANK_ACCOUNT_NUMBER
                ]
            ];
        } catch (Exception $e) {
            error_log("Bank Transfer Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize payment based on the selected payment method
     * 
     * @param array $paymentData Payment data
     * @return array|bool Response data or false on failure
     */
    public function initializePayment($paymentData) {
        switch ($this->paymentMethod) {
            case 'paystack':
                return $this->initializePaystack($paymentData);
                
            case 'ussd':
                return $this->initializeUssd($paymentData);
                
            case 'bank_transfer':
                return $this->processBankTransfer($paymentData);
                
            default:
                // Default to Paystack if method not recognized
                return $this->initializePaystack($paymentData);
        }
    }
    
    /**
     * Verify Paystack transaction
     * 
     * @param string $reference Transaction reference
     * @return array|bool Verification data or false on failure
     */
    public function verifyPaystackTransaction($reference) {
        $url = "https://api.paystack.co/transaction/verify/" . urlencode($reference);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->secretKey,
                "Cache-Control: no-cache"
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            error_log("Paystack Verification API Error: " . $err);
            return false;
        }
        
        $result = json_decode($response, true);
        
        if ($result && $result['status'] && $result['data']['status'] === 'success') {
            // Update transaction status in database
            $this->updateTransactionStatus($reference, 'completed', $result);
            return $result;
        }
        
        // Log verification failure
        error_log("Paystack Transaction Verification Failed: " . ($result['message'] ?? 'Unknown error'));
        error_log("Response: " . $response);
        
        return false;
    }
    
    /**
     * Verify transaction based on payment method
     * 
     * @param string $reference Transaction reference
     * @param string $transactionId Additional transaction ID (for some gateways)
     * @return array|bool Verification data or false on failure
     */
    public function verifyTransaction($reference, $transactionId = null) {
        switch ($this->paymentMethod) {
            case 'paystack':
                return $this->verifyPaystackTransaction($reference);
                
            case 'ussd':
                // USSD verification is handled by Paystack
                return $this->verifyPaystackTransaction($reference);
                
            case 'bank_transfer':
                // Bank transfers are manually verified and updated
                return false;
                
            default:
                return $this->verifyPaystackTransaction($reference);
        }
    }
    
    /**
     * Manually verify bank transfer payment
     * 
     * @param string $reference Transaction reference
     * @param array $proofData Payment proof data (e.g., screenshot, description)
     * @return bool True if verification is successful, false otherwise
     */
    public function verifyBankTransfer($reference, $proofData) {
        try {
            // Save payment proof to database
            $this->db->query(
                "UPDATE payment_transactions 
                 SET proof_data = ?, updated_at = NOW()
                 WHERE reference = ?",
                [json_encode($proofData), $reference]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Bank Transfer Proof Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save transaction data to database
     * 
     * @param array $paymentData Payment data
     * @param string $reference Transaction reference
     * @param array $response Payment gateway response
     * @param float $calculatedAmount The correctly calculated amount
     * @return bool True if saved successfully, false otherwise
     */
    private function saveTransaction($paymentData, $reference, $response, $calculatedAmount = null) {
        try {
            // Use the calculated amount if provided, otherwise calculate it
            $amount = $calculatedAmount ?? $this->calculatePaymentAmount($paymentData);
            
            error_log("Saving transaction with amount: " . $amount);
            
            // Create transaction record with CORRECT amount
            $this->db->query(
                "INSERT INTO payment_transactions (
                    clan_id, 
                    amount, 
                    payment_method, 
                    reference, 
                    status, 
                    response_data, 
                    pricing_plan_id,
                    is_per_user,
                    user_count,
                    purpose
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $paymentData['clan_id'],
                    $amount, // Use calculated amount
                    $this->paymentMethod,
                    $reference,
                    'pending',
                    json_encode($response),
                    $paymentData['pricing_plan_id'],
                    isset($paymentData['is_per_user']) ? $paymentData['is_per_user'] : 0,
                    isset($paymentData['user_count']) ? $paymentData['user_count'] : 1,
                    isset($paymentData['purpose']) ? $paymentData['purpose'] : 'clan_subscription'
                ]
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Transaction Save Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update transaction status in database
     * 
     * @param string $reference Transaction reference
     * @param string $status New status (pending, completed, failed)
     * @param array $response Payment gateway response
     * @return bool True if updated successfully, false otherwise
     */
    private function updateTransactionStatus($reference, $status, $response) {
        try {
            // Update transaction status
            $this->db->query(
                "UPDATE payment_transactions 
                 SET status = ?, response_data = ?, updated_at = NOW()
                 WHERE reference = ?",
                [$status, json_encode($response), $reference]
            );
            
            // If payment is completed, process accordingly
            if ($status === 'completed') {
                // Get transaction data
                $transaction = $this->db->fetchOne(
                    "SELECT clan_id, pricing_plan_id, is_per_user, user_count, purpose, amount FROM payment_transactions WHERE reference = ?",
                    [$reference]
                );
                
                if ($transaction) {
                    // Check if this is a license purchase or clan subscription
                    $isPurchasingLicenses = isset($transaction['purpose']) && $transaction['purpose'] === 'license_purchase';
                    
                    // Extract license purchase info from metadata if available
                    $metadata = isset($response['data']['metadata']) ? $response['data']['metadata'] : null;
                    if ($metadata && isset($metadata['purpose']) && $metadata['purpose'] === 'license_purchase') {
                        $isPurchasingLicenses = true;
                    }
                    
                    error_log("Transaction purpose in updateTransactionStatus: " . ($isPurchasingLicenses ? 'license_purchase' : 'clan_subscription'));
                    
                    // Get plan duration
                    $plan = $this->db->fetchOne(
                        "SELECT duration_days FROM pricing_plans WHERE id = ?",
                        [$transaction['pricing_plan_id']]
                    );
                    
                    $durationDays = $plan ? $plan['duration_days'] : 30;
                    
                    // Calculate next payment date (for clan subscriptions)
                    $nextPaymentDate = date('Y-m-d', strtotime("+$durationDays days"));
                    
                    // Determine license count for license purchases, 0 for clan subscriptions
                    $licenseCount = 0;
                    if ($isPurchasingLicenses) {
                        $licenseCount = isset($transaction['user_count']) && $transaction['user_count'] > 0 ? 
                            (int)$transaction['user_count'] : 1;
                        error_log("License count in updateTransactionStatus: $licenseCount");
                    }
                    
                    if ($isPurchasingLicenses) {
                        // For license purchases, only increment available licenses without changing payment status
                        $this->db->query(
                            "UPDATE clans 
                             SET available_licenses = available_licenses + ?,
                                 updated_at = NOW()
                             WHERE id = ?",
                            [$licenseCount, $transaction['clan_id']]
                        );
                        
                        error_log("Adding $licenseCount licenses to clan {$transaction['clan_id']} without changing payment status");
                    } else {
                        // For clan subscriptions, update payment status and next payment date WITHOUT adding licenses
                        $this->db->query(
                            "UPDATE clans 
                             SET payment_status = 'active', 
                                 pricing_plan_id = ?, 
                                 next_payment_date = ?,
                                 updated_at = NOW()
                             WHERE id = ?",
                            [$transaction['pricing_plan_id'], $nextPaymentDate, $transaction['clan_id']]
                        );
                        
                        error_log("Updating clan {$transaction['clan_id']} payment status to active, WITHOUT adding licenses");
                    }
                    
                    // Create payment record with correct amount from transaction
                    $this->db->query(
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
                            $transaction['amount'], // Use amount from transaction table (this is correct)
                            $this->paymentMethod,
                            $reference,
                            'completed',
                            $transaction['pricing_plan_id'],
                            $nextPaymentDate,
                            $transaction['is_per_user'] ?? 0,
                            $transaction['user_count'] ?? 0,
                            $licenseCount,
                            $isPurchasingLicenses ? 'license_purchase' : 'clan_subscription'
                        ]
                    );
                    
                    // Get payment ID
                    $paymentId = $this->db->lastInsertId();
                    
                    // Get clan details to create notification
                    $clan = $this->db->fetchOne(
                        "SELECT admin_id, name FROM clans WHERE id = ?",
                        [$transaction['clan_id']]
                    );
                    
                    if ($clan && $clan['admin_id']) {
                        // Create notification with appropriate message using correct amount
                        $notificationTitle = "Payment Successful";
                        $notificationMessage = "Your payment of " . CURRENCY_SYMBOL . number_format($transaction['amount'], 2) . " has been processed successfully.";
                        
                        if ($isPurchasingLicenses) {
                            $notificationMessage .= " You have received $licenseCount new user licenses.";
                        } else {
                            $notificationMessage .= " Your clan subscription is now active.";
                        }
                        
                        // Create notification
                        $this->db->query(
                            "INSERT INTO notifications (
                                user_id, clan_id, title, message, type, reference_id, reference_type, is_read, created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW())",
                            [
                                $clan['admin_id'],
                                $transaction['clan_id'],
                                $notificationTitle,
                                $notificationMessage,
                                'payment_success',
                                $paymentId,
                                'payment'
                            ]
                        );
                    }
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Transaction Status Update Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Format currency amount with symbol
     * 
     * @param float $amount The amount to format
     * @return string Formatted amount with currency symbol
     */
    private function getFormattedAmount($amount) {
        return CURRENCY_SYMBOL . number_format($amount, 2);
    }
    
    /**
     * Get public key for client-side integration
     * 
     * @return string Public key
     */
    public function getPublicKey() {
        return $this->publicKey;
    }
    
    /**
     * Get merchant reference
     * 
     * @return string Merchant reference
     */
    public function getMerchantReference() {
        return $this->merchantReference;
    }
    
    /**
     * Get callback URL
     * 
     * @return string Callback URL
     */
    public function getCallbackUrl() {
        return $this->callbackUrl;
    }
    
    /**
     * Get payment method
     * 
     * @return string Payment method
     */
    public function getPaymentMethod() {
        return $this->paymentMethod;
    }
}