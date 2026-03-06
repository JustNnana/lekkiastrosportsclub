<?php

/**
 * Gate Wey Access Management System
 * Unified Payment Settings Page - Dasher UI Enhanced (FULLY RECODED)
 * Clan Payments + License Purchases with Modern UI
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/Payment.php';
require_once 'payment_constants.php';
require_once 'PaymentIntegration.php';

// Enable debugging for payment process
function debug_log($message)
{
    error_log("PAYMENT PROCESS: " . $message);
}

debug_log("Starting payment settings page");

// Set page title
$pageTitle = 'Payment Settings';

// Check if user is logged in and is a clan admin or super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['clan_admin', 'super_admin'])) {
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

// Get clan ID
if ($currentUser->isSuperAdmin() && isset($_GET['clan_id'])) {
    $clanId = $_GET['clan_id'];
} else {
    $clanId = $currentUser->getClanId();
}

// Get clan details
$clan = new Clan();
if (!$clan->loadById($clanId)) {
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

// Get the payment type from URL or default to 'clan'
$paymentType = isset($_GET['type']) ? $_GET['type'] : 'clan';
if (!in_array($paymentType, ['clan', 'license'])) {
    $paymentType = 'clan';
}

// License count for license purchases
$licenseCount = isset($_POST['license_count']) ? (int)$_POST['license_count'] : 1;

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

// Get pricing plans assigned to this clan
$assignedPlans = $db->fetchAll(
    "SELECT p.* FROM pricing_plans p
     JOIN clan_pricing_plans cpp ON p.id = cpp.pricing_plan_id
     WHERE cpp.clan_id = ?
     ORDER BY p.price ASC",
    [$clanId]
);

// If no plans are assigned yet, super admin sees all plans, clan admin sees none
if (empty($assignedPlans) && $currentUser->isSuperAdmin()) {
    $assignedPlans = $db->fetchAll("SELECT * FROM pricing_plans ORDER BY price ASC");
}

// Get current plan
$currentPlan = $clan->getPricingPlanId() ? $db->fetchOne(
    "SELECT * FROM pricing_plans WHERE id = ?",
    [$clan->getPricingPlanId()]
) : null;

// Get payment history with filters
$historyPage = isset($_GET['history_page']) ? max(1, (int)$_GET['history_page']) : 1;
$historyPerPage = 10;
$historyOffset = ($historyPage - 1) * $historyPerPage;

// Build filter conditions
$filterConditions = ["p.clan_id = ?"];
$filterParams = [$clanId];

// Status filter
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
if ($statusFilter && in_array($statusFilter, ['completed', 'pending', 'failed', 'refunded'])) {
    $filterConditions[] = "p.status = ?";
    $filterParams[] = $statusFilter;
}

// Purpose filter
$purposeFilter = isset($_GET['purpose']) ? $_GET['purpose'] : '';
if ($purposeFilter && in_array($purposeFilter, ['clan_subscription', 'license_purchase', 'household_license_purchase'])) {
    $filterConditions[] = "p.purpose = ?";
    $filterParams[] = $purposeFilter;
}

// Date range filter
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

if ($dateFrom) {
    $filterConditions[] = "DATE(p.payment_date) >= ?";
    $filterParams[] = $dateFrom;
}

if ($dateTo) {
    $filterConditions[] = "DATE(p.payment_date) <= ?";
    $filterParams[] = $dateTo;
}

$whereClause = implode(' AND ', $filterConditions);

// Get total count of filtered payments
$totalHistoryCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM payments p WHERE $whereClause",
    $filterParams
)['count'];

$totalHistoryPages = ceil($totalHistoryCount / $historyPerPage);

// Get payment history with pagination
$paymentHistory = $db->fetchAll(
    "SELECT p.*, pp.name as plan_name 
     FROM payments p 
     LEFT JOIN pricing_plans pp ON p.pricing_plan_id = pp.id 
     WHERE $whereClause
     ORDER BY p.payment_date DESC 
     LIMIT ? OFFSET ?",
    array_merge($filterParams, [$historyPerPage, $historyOffset])
);

// Get payment statistics
$paymentStats = $db->fetchOne(
    "SELECT 
        COUNT(*) as total_payments,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_count,
        COALESCE(SUM(amount), 0) as total_amount,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as completed_amount
     FROM payments 
     WHERE clan_id = ?",
    [$clanId]
);

// Get household count for this clan (for per-household plans)
require_once __DIR__ . '/../classes/Payment.php';
$householdCount = Payment::getBillableHouseholdCount($clanId);
$householdBreakdown = Payment::getBillableHouseholdBreakdown($clanId);

// Keep user count for display purposes
$userCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM users WHERE clan_id = ?",
    [$clanId]
)['count'];

$roleBreakdown = $db->fetchAll(
    "SELECT role, COUNT(*) as count FROM users WHERE clan_id = ? GROUP BY role",
    [$clanId]
);

// Get available licenses count
$availableLicenses = $db->fetchOne(
    "SELECT available_licenses FROM clans WHERE id = ?",
    [$clanId]
)['available_licenses'] ?? 0;

// Get total household count for this clan
$totalHouseholdsCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM households WHERE clan_id = ?",
    [$clanId]
)['count'] ?? 0;

// Process payment settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $paystackEnabled = isset($_POST['paystack_enabled']) ? 1 : 0;
    $bankTransferEnabled = isset($_POST['bank_transfer_enabled']) ? 1 : 0;
    $autoRenew = isset($_POST['auto_renew']) ? 1 : 0;

    try {
        $db->query(
            "UPDATE payment_settings SET 
             paystack_enabled = ?, 
             bank_transfer_enabled = ?, 
             auto_renew = ?, 
             updated_at = NOW() 
             WHERE clan_id = ?",
            [$paystackEnabled, $bankTransferEnabled, $autoRenew, $clanId]
        );

        $success = 'Payment settings updated successfully.';

        // Update payment settings variable
        $paymentSettings = [
            'paystack_enabled' => $paystackEnabled,
            'bank_transfer_enabled' => $bankTransferEnabled,
            'auto_renew' => $autoRenew
        ];
    } catch (Exception $e) {
        $error = 'Failed to update payment settings. Please try again.';
    }
}

// Process household license purchase request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method']) && $paymentType === 'license') {
    $paymentMethod = $_POST['payment_method'];
    $householdId = isset($_POST['household_id']) ? (int)$_POST['household_id'] : 0;
    $licenseCount = isset($_POST['license_count']) ? (int)$_POST['license_count'] : 1;
    
    debug_log("Processing household license purchase: Household ID=$householdId, Licenses=$licenseCount, Method=$paymentMethod");
    
    // Validate household
    if (!$householdId) {
        $error = 'Please select a household.';
    } else {
        // Get household details
        $household = $db->fetchOne(
            "SELECT * FROM households WHERE id = ? AND clan_id = ?",
            [$householdId, $clanId]
        );
        
        if (!$household) {
            $error = 'Invalid household selected.';
        } elseif ($household['status'] !== 'active') {
            $error = 'Cannot purchase licenses for inactive households.';
        } else {
            $currentCapacity = $household['max_members'] + $household['extra_member_licenses'];
            $maxAllowed = 5;
            $availableLicenses = $maxAllowed - $currentCapacity;
            
            // Validate license count
            if ($licenseCount < 1 || $licenseCount > 2) {
                $error = 'Invalid license count. You can only purchase 1 or 2 licenses.';
            } elseif ($licenseCount > $availableLicenses) {
                $error = "This household can only add {$availableLicenses} more license(s).";
            } elseif ($currentCapacity >= $maxAllowed) {
                $error = 'This household has already reached maximum capacity.';
            } else {
                // Calculate cost
                $totalCost = $currentPlan['price_per_user'] * $licenseCount;
                
                try {
                    $transactionId = 'HHL-' . time() . '-' . uniqid();
                    debug_log("Generated household license transaction ID: $transactionId");
                    
                    $paymentData = [
                        'clan_id' => $clanId,
                        'household_id' => $householdId,
                        'amount' => $totalCost,
                        'payment_method' => $paymentMethod,
                        'transaction_id' => $transactionId,
                        'status' => 'pending',
                        'pricing_plan_id' => $currentPlan['id'],
                        'is_per_user' => 1,
                        'user_count' => $licenseCount,
                        'purpose' => 'household_license_purchase'
                    ];
                    
                    debug_log("Household license payment data: " . json_encode($paymentData));
                    
                    // Store in session for callback processing
                    // Don't create payment record yet - it will be created in callback.php
                    $_SESSION['household_license_payment'] = [
                        'household_id' => $householdId,
                        'license_count' => $licenseCount
                    ];
                    
                    debug_log("Stored household license data in session");
                    
                    // Initialize payment with Paystack
                    if ($paymentMethod === 'paystack') {
                        $paymentIntegration = new PaymentIntegration('paystack');
                        
                        $paymentData['email'] = $currentUser->getEmail();
                        $paymentData['plan_name'] = $currentPlan['name'];
                        $paymentData['household_name'] = $household['name'];
                        $paymentData['from_page'] = 'settings';
                        $paymentData['metadata'] = [
                            'purpose' => 'household_license_purchase',
                            'household_id' => $householdId,
                            'household_name' => $household['name'],
                            'license_count' => $licenseCount,
                            'from_page' => 'settings'
                        ];
                        
                        $response = $paymentIntegration->initializePaystack($paymentData);
                        debug_log("Paystack initialization response: " . json_encode($response));
                        
                        if ($response && isset($response['data']['authorization_url'])) {
                            $authUrl = $response['data']['authorization_url'];
                            debug_log("Redirecting to: $authUrl");
                            header('Location: ' . $authUrl);
                            exit;
                        } else {
                            $error = 'Failed to initialize payment. Please try again.';
                            debug_log("Failed to initialize Paystack payment: " . json_encode($response));
                        }
                    }
                } catch (Exception $e) {
                    $error = 'An error occurred: ' . $e->getMessage();
                    debug_log("Exception: " . $e->getMessage());
                }
            }
        }
    }
}

// Process clan payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method']) && $paymentType === 'clan') {
    $planId = isset($_POST['pricing_plan_id']) ? intval($_POST['pricing_plan_id']) : 0;

    if (!$planId && $currentPlan) {
        $planId = $currentPlan['id'];
    }

    if (!$planId) {
        $error = 'Please select a pricing plan.';
    } else {
        $plan = $db->fetchOne("SELECT * FROM pricing_plans WHERE id = ?", [$planId]);

        if (!$plan) {
            $error = 'Selected pricing plan not found.';
        } elseif ($plan['is_free']) {
            try {
                $db->beginTransaction();

                $db->query(
                    "UPDATE clans SET payment_status = 'free', pricing_plan_id = ?, next_payment_date = NULL, updated_at = NOW() WHERE id = ?",
                    [$planId, $clanId]
                );

                $db->query(
                    "INSERT INTO payments (clan_id, amount, payment_method, transaction_id, status, payment_date, pricing_plan_id) 
                     VALUES (?, 0, 'free', ?, 'completed', NOW(), ?)",
                    [$clanId, 'FREE-' . time(), $planId]
                );

                $db->commit();
                $success = 'Your clan has been successfully updated to the free plan.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Failed to update plan. Please try again.';
            }
        } else {
            $paymentMethod = $_POST['payment_method'];
            // Calculate amount based on households for per-user plans
if ($plan['is_per_user']) {
    $billableHouseholds = Payment::getBillableHouseholdCount($clanId);
    $amount = $plan['price_per_user'] * max(1, $billableHouseholds);
} else {
    $amount = $plan['price'];
}

            try {
                $paymentIntegration = new PaymentIntegration($paymentMethod);

                $paymentData = [
    'clan_id' => $clanId,
    'amount' => $amount,
    'email' => $currentUser->getEmail(),
    'pricing_plan_id' => $planId,
    'is_per_user' => $plan['is_per_user'],
    'user_count' => $plan['is_per_user'] ? $billableHouseholds : $userCount, // Store household count
    'clan_name' => $clan->getName(),
    'plan_name' => $plan['name'],
    'purpose' => 'clan_subscription'
];

                if ($paymentMethod === 'paystack') {
                    $response = $paymentIntegration->initializePaystack($paymentData);

                    if ($response && isset($response['data']['authorization_url'])) {
                        $_SESSION['is_license_purchase'] = false;
                        header('Location: ' . $response['data']['authorization_url']);
                        exit;
                    } else {
                        $error = 'Failed to initialize payment. Please try again.';
                    }
                } elseif ($paymentMethod === 'bank_transfer') {
                    $response = $paymentIntegration->processBankTransfer($paymentData);

                    if ($response && isset($response['data']['reference'])) {
                        $_SESSION['is_license_purchase'] = false;
                        header('Location: ' . BASE_URL . 'payments/bank-transfer.php?reference=' . $response['data']['reference']);
                        exit;
                    } else {
                        $error = 'Failed to initialize bank transfer. Please try again.';
                    }
                }
            } catch (Exception $e) {
                $error = 'An error occurred: ' . $e->getMessage();
            }
        }
    }
}

// Include header
include_once '../includes/header.php';
include_once '../includes/sidebar.php';
?>

<!-- iOS-Style Payment Settings Page Styles -->
<style>
    :root {
        --ios-red: #FF453A;
        --ios-orange: #FF9F0A;
        --ios-yellow: #FFD60A;
        --ios-green: #30D158;
        --ios-teal: #64D2FF;
        --ios-blue: #0A84FF;
        --ios-purple: #BF5AF2;
        --ios-gray: #8E8E93;
    }

    /* iOS Section Card */
    .ios-section-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: var(--spacing-4);
    }

    .ios-section-header {
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
        padding: var(--spacing-4);
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
    }

    .ios-section-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .ios-section-icon.primary { background: rgba(34, 197, 94, 0.15); color: var(--ios-green); }
    .ios-section-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }
    .ios-section-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-section-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-section-icon.red { background: rgba(255, 69, 58, 0.15); color: var(--ios-red); }

    .ios-section-title {
        flex: 1;
    }

    .ios-section-title h5 {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .ios-section-title p {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 4px 0 0 0;
    }

    .ios-section-body {
        padding: var(--spacing-4);
    }

    /* 3-Dot Menu Button */
    .ios-options-btn {
        display: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--bg-secondary);
        border: 1px solid var(--border-color);
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.15s ease;
        flex-shrink: 0;
    }

    .ios-options-btn:hover {
        background: var(--border-color);
    }

    .ios-options-btn:active {
        transform: scale(0.95);
    }

    .ios-options-btn i {
        color: var(--text-primary);
        font-size: 16px;
    }

    /* Payment Type Tabs - iOS Style */
    .content .payment-type-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: var(--spacing-4);
        padding: 4px;
        background: var(--bg-secondary);
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .content .payment-type-tab {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        flex: 1;
        padding: 12px 16px;
        background: transparent;
        border: none;
        border-radius: 10px;
        color: var(--text-secondary);
        font-weight: 500;
        font-size: 14px;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .content .payment-type-tab:hover {
        color: var(--text-primary);
        background: var(--bg-subtle);
    }

    .content .payment-type-tab.active {
        color: white;
        background: var(--ios-blue);
    }

    .content .payment-type-tab i {
        font-size: 16px;
    }

    /* Stats Cards - iOS Style */
    .content .payment-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--spacing-4);
        margin-bottom: var(--spacing-4);
    }

    .content .payment-stat-card {
        background: transparent;
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: var(--spacing-4);
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
        transition: all 0.2s ease;
    }

    .content .payment-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
        border-color: var(--ios-blue);
    }

    .content .payment-stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        color: white;
        flex-shrink: 0;
    }

    .content .payment-stat-icon.success { background: var(--ios-green); }
    .content .payment-stat-icon.warning { background: var(--ios-orange); }
    .content .payment-stat-icon.primary { background: var(--ios-blue); }
    .content .payment-stat-icon.info { background: var(--ios-purple); }

    .content .payment-stat-content {
        flex: 1;
    }

    .content .payment-stat-label {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }

    .content .payment-stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-primary);
        line-height: 1;
        margin-bottom: 4px;
    }

    .content .payment-stat-detail {
        font-size: 11px;
        color: var(--text-muted);
    }

    /* Main Layout Grid */
    .content .payment-layout-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: var(--spacing-4);
    }

    @media (max-width: 992px) {
        .content .payment-layout-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Payment Section Cards - iOS Style */
    .content .payment-section-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        margin-bottom: var(--spacing-4);
        overflow: hidden;
    }

    .content .payment-section-header {
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
        padding: var(--spacing-4);
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
    }

    .content .payment-section-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .content .payment-section-icon.primary {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    .content .payment-section-icon.success {
        background: rgba(48, 209, 88, 0.15);
        color: var(--ios-green);
    }

    .content .payment-section-icon.warning {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .content .payment-section-icon.info {
        background: rgba(191, 90, 242, 0.15);
        color: var(--ios-purple);
    }

    .content .payment-section-title h5 {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .content .payment-section-title p {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 4px 0 0 0;
    }

    .content .payment-section-body {
        padding: var(--spacing-4);
    }

    /* Plan Cards Grid */
    .content .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: var(--spacing-4);
    }

    .content .plan-card {
        background: transparent;
        border: 2px solid var(--border-color);
        border-radius: var(--border-radius-lg);
        padding: var(--spacing-5);
        transition: var(--theme-transition);
        display: flex;
        flex-direction: column;
    }

    .content .plan-card:hover {
        border-color: var(--primary);
        box-shadow: var(--shadow);
        transform: translateY(-4px);
    }

    .content .plan-card.current-plan {
        border-color: var(--primary);
        background: rgba(var(--primary-rgb), 0.05);
    }

    .content .plan-card-header {
        text-align: center;
        padding-bottom: var(--spacing-4);
        border-bottom: 1px solid var(--border-color);
        margin-bottom: var(--spacing-4);
    }

    .content .plan-name {
        font-size: var(--font-size-xl);
        font-weight: var(--font-weight-bold);
        color: var(--text-primary);
        margin-bottom: var(--spacing-2);
    }

    .content .plan-price {
        font-size: 2rem;
        font-weight: var(--font-weight-bold);
        color: var(--primary);
        margin-bottom: var(--spacing-2);
    }

    .content .plan-price-detail {
        font-size: var(--font-size-sm);
        color: var(--text-secondary);
    }

    .content .plan-description {
        font-size: var(--font-size-sm);
        color: var(--text-secondary);
        margin-bottom: var(--spacing-4);
        flex: 1;
    }

    .content .plan-features {
        list-style: none;
        padding: 0;
        margin: 0 0 var(--spacing-4) 0;
    }

    .content .plan-features li {
        display: flex;
        align-items: flex-start;
        gap: var(--spacing-2);
        padding: var(--spacing-2) 0;
        font-size: var(--font-size-sm);
        color: var(--text-primary);
    }

    .content .plan-features li i {
        color: var(--success);
        margin-top: 2px;
        flex-shrink: 0;
    }

    /* iOS Search Box */
    .ios-search-box {
        padding: 12px 16px;
        background: var(--bg-subtle);
    }

    .ios-search-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .ios-search-icon {
        position: absolute;
        left: 12px;
        color: var(--text-muted);
        font-size: 14px;
        pointer-events: none;
    }

    .ios-search-input {
        width: 100%;
        padding: 10px 36px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 15px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .ios-search-input:focus {
        outline: none;
        border-color: var(--ios-blue);
        box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.1);
    }

    .ios-search-input::placeholder {
        color: var(--text-muted);
    }

    .ios-search-clear {
        position: absolute;
        right: 10px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: var(--border-color);
        border: none;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-secondary);
        font-size: 10px;
    }

    .ios-search-clear.visible {
        display: flex;
    }

    /* iOS Filter Pills */
    .ios-filter-pills {
        display: flex;
        gap: 8px;
        padding: 12px 16px;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-filter-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 500;
        text-decoration: none;
        white-space: nowrap;
        transition: all 0.2s ease;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-secondary);
    }

    .ios-filter-pill:hover {
        background: var(--border-color);
        color: var(--text-primary);
    }

    .ios-filter-pill.active {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
        color: white;
    }

    .ios-filter-pill.completed.active {
        background: var(--ios-green);
        border-color: var(--ios-green);
    }

    .ios-filter-pill.pending.active {
        background: var(--ios-orange);
        border-color: var(--ios-orange);
    }

    .ios-filter-pill.failed.active {
        background: var(--ios-red);
        border-color: var(--ios-red);
    }

    /* iOS Filter Section (Desktop) */
    .content .filter-section {
        background: var(--bg-secondary);
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 0;
    }

    .content .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        margin-bottom: 12px;
    }

    .content .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .content .filter-label {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .content .filter-input {
        padding: 10px 12px;
        font-size: 14px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-primary);
        transition: border-color 0.2s ease;
    }

    .content .filter-input:focus {
        outline: none;
        border-color: var(--ios-blue);
    }

    .content .filter-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    /* Desktop Filter Card */
    .desktop-filter-section {
        display: block;
    }

    /* iOS Payment Log Item - Matches access-logs pattern */
    .ios-payment-log-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 16px;
        background: var(--bg-primary);
        transition: background 0.15s ease;
        border-bottom: 1px solid var(--border-color);
        text-decoration: none;
        color: inherit;
    }

    .ios-payment-log-item:last-child {
        border-bottom: none;
    }

    .ios-payment-log-item:hover {
        background: rgba(255, 255, 255, 0.03);
    }

    .ios-payment-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
        margin-top: 5px;
    }

    .ios-payment-dot.completed { background: var(--ios-green); }
    .ios-payment-dot.pending { background: var(--ios-orange); }
    .ios-payment-dot.failed { background: var(--ios-red); }
    .ios-payment-dot.refunded { background: var(--ios-teal); }

    .ios-payment-content {
        flex: 1;
        min-width: 0;
    }

    .ios-payment-amount-title {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 4px 0;
    }

    .ios-payment-plan-info {
        font-size: 13px;
        color: var(--text-secondary);
        margin: 0 0 2px 0;
    }

    .ios-payment-plan-info i {
        width: 14px;
        font-size: 11px;
        margin-right: 4px;
    }

    .ios-payment-time {
        font-size: 12px;
        color: var(--text-muted);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .ios-payment-time i {
        font-size: 10px;
    }

    .ios-payment-meta {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 4px;
        flex-shrink: 0;
    }

    .ios-status-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 6px;
        text-transform: capitalize;
    }

    .ios-status-badge.completed {
        background: rgba(48, 209, 88, 0.15);
        color: var(--ios-green);
    }

    .ios-status-badge.pending {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .ios-status-badge.failed {
        background: rgba(255, 69, 58, 0.15);
        color: var(--ios-red);
    }

    .ios-status-badge.refunded {
        background: rgba(100, 210, 255, 0.15);
        color: var(--ios-teal);
    }

    .ios-payment-method-badge {
        font-size: 10px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .ios-payment-txn-badge {
        font-size: 10px;
        font-weight: 500;
        color: var(--text-muted);
        font-family: 'Courier New', monospace;
    }

    /* iOS Empty State */
    .ios-empty-state {
        text-align: center;
        padding: 48px 24px;
    }

    .ios-empty-icon {
        font-size: 56px;
        color: var(--text-secondary);
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .ios-empty-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 8px 0;
    }

    .ios-empty-description {
        font-size: 14px;
        color: var(--text-secondary);
        margin: 0 0 20px 0;
        line-height: 1.5;
    }

    /* iOS Pagination */
    .ios-pagination {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 16px;
        border-top: 1px solid var(--border-color);
    }

    .ios-page-btn {
        min-width: 36px;
        height: 36px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-secondary);
        color: var(--text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s ease;
    }

    .ios-page-btn:hover {
        background: var(--border-color);
        color: var(--text-primary);
    }

    .ios-page-btn.active {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
        color: white;
    }

    .ios-page-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* iOS Button */
    .ios-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.2s ease;
        border: none;
        cursor: pointer;
    }

    .ios-btn-primary {
        background: var(--ios-blue);
        color: white;
    }

    .ios-btn-primary:hover {
        background: #0070E0;
        color: white;
    }

    .ios-btn-secondary {
        background: var(--bg-secondary);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }

    .ios-btn-secondary:hover {
        background: var(--border-color);
    }

    /* iOS Filter Form in Menu */
    .ios-filter-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .ios-filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .ios-filter-label {
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .ios-filter-input {
        width: 100%;
        padding: 12px 14px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-primary);
        color: var(--text-primary);
        font-size: 15px;
        transition: border-color 0.2s ease;
        box-sizing: border-box;
    }

    .ios-filter-input:focus {
        outline: none;
        border-color: var(--ios-blue);
    }

    .ios-filter-actions {
        display: flex;
        gap: 10px;
        margin-top: 8px;
    }

    .ios-filter-actions .ios-btn {
        flex: 1;
    }

    /* Mobile Payment List - hidden on desktop */
    #paymentsList {
        display: none;
    }

    /* Dasher Table Styles - Desktop */
    .content .dasher-table-container {
        background: transparent;
        border-radius: 0;
        overflow: hidden;
    }

    .content .dasher-table {
        width: 100%;
        border-collapse: collapse;
        background: transparent;
    }

    .content .dasher-table thead {
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
    }

    .content .dasher-table th {
        padding: 14px 16px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        white-space: nowrap;
    }

    .content .dasher-table tbody tr {
        border-bottom: 1px solid var(--border-color);
        transition: background 0.15s ease;
    }

    .content .dasher-table tbody tr:hover {
        background: var(--bg-hover);
    }

    .content .dasher-table tbody tr:last-child {
        border-bottom: none;
    }

    .content .dasher-table td {
        padding: 14px 16px;
        font-size: 14px;
        color: var(--text-primary);
    }

    /* iOS Status Badges */
    .content .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        text-transform: capitalize;
    }

    .content .status-badge.completed {
        background: rgba(48, 209, 88, 0.15);
        color: var(--ios-green);
    }

    .content .status-badge.pending {
        background: rgba(255, 159, 10, 0.15);
        color: var(--ios-orange);
    }

    .content .status-badge.failed {
        background: rgba(255, 69, 58, 0.15);
        color: var(--ios-red);
    }

    .content .status-badge.refunded {
        background: rgba(100, 210, 255, 0.15);
        color: var(--ios-teal);
    }

    /* iOS Purpose Badge */
    .content .purpose-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
    }

    .content .purpose-badge.license {
        background: rgba(191, 90, 242, 0.15);
        color: var(--ios-purple);
    }

    .content .purpose-badge.subscription {
        background: rgba(10, 132, 255, 0.15);
        color: var(--ios-blue);
    }

    /* iOS Pagination */
    .content .table-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        border-top: 1px solid var(--border-color);
    }

    .content .pagination-info {
        font-size: 13px;
        color: var(--text-secondary);
    }

    .content .pagination-nav .pagination {
        display: flex;
        gap: 4px;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .content .pagination .page-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 8px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-secondary);
        color: var(--text-primary);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
    }

    .content .pagination .page-link:hover {
        background: var(--border-color);
        color: var(--text-primary);
    }

    .content .pagination .page-item.active .page-link {
        background: var(--ios-blue);
        border-color: var(--ios-blue);
        color: white;
    }

    .content .pagination .page-item.disabled .page-link {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* iOS Sidebar Cards */
    .content .payment-sidebar-card {
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        overflow: hidden;
        margin-bottom: var(--spacing-4);
    }

    .content .sidebar-card-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 16px;
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
    }

    .content .sidebar-card-header i {
        color: var(--ios-blue);
        font-size: 16px;
    }

    .content .sidebar-card-header h6 {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .content .sidebar-card-body {
        padding: 16px;
    }

    /* Desktop Sidebar */
    .desktop-sidebar {
        display: block;
    }

    /* Info Banner */
    .content .info-banner {
        display: flex;
        align-items: flex-start;
        padding: var(--spacing-4);
        background: var(--bg-subtle);
        border-bottom: 1px solid var(--border-color);
        margin-bottom: var(--spacing-4);
        gap: var(--spacing-3);
    }

    .content .info-banner.warning {
        background: rgba(var(--warning-rgb), 0.1);
        border-color: rgba(var(--warning-rgb), 0.2);
    }

    .content .info-banner.success {
        background: rgba(var(--success-rgb), 0.1);
        border-color: rgba(var(--success-rgb), 0.2);
    }

    .content .info-banner.danger {
        background: rgba(var(--danger-rgb), 0.1);
        border-color: rgba(var(--danger-rgb), 0.2);
    }

    .content .info-banner i {
        font-size: var(--font-size-lg);
        flex-shrink: 0;
        margin-top: 2px;
    }

    .content .info-banner.warning i {
        color: var(--warning);
    }

    .content .info-banner.success i {
        color: var(--success);
    }

    .content .info-banner.danger i {
        color: var(--danger);
    }

    /* License Purchase Form */
    .content .license-form-group {
        margin-bottom: var(--spacing-4);
    }

    .content .license-counter {
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
    }

    .content .counter-btn {
        width: 40px;
        height: 40px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        background: transparent;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--theme-transition);
    }

    .content .counter-btn:hover {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }

    .content .counter-input {
        width: 80px;
        text-align: center;
        padding: var(--spacing-2);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        background: var(--bg-input);
        color: var(--text-primary);
        font-weight: var(--font-weight-semibold);
    }

    .content .order-summary {
        background: var(--bg-subtle);
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius);
        padding: var(--spacing-4);
        margin-bottom: var(--spacing-4);
    }

    .content .summary-row {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-2) 0;
        font-size: var(--font-size-sm);
    }

    .content .summary-row.total {
        border-top: 2px solid var(--border-color);
        margin-top: var(--spacing-2);
        padding-top: var(--spacing-3);
        font-weight: var(--font-weight-bold);
        font-size: var(--font-size-base);
    }

    /* iOS-Style Mobile Menu Modal */
    .ios-menu-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
        z-index: 9998;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .ios-menu-backdrop.active {
        opacity: 1;
        visibility: visible;
    }

    .ios-menu-modal {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: var(--bg-primary);
        border-radius: 16px 16px 0 0;
        z-index: 9999;
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
        max-height: 85vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .ios-menu-modal.active {
        transform: translateY(0);
    }

    .ios-menu-handle {
        width: 36px;
        height: 5px;
        background: var(--border-color);
        border-radius: 3px;
        margin: 8px auto 4px;
        flex-shrink: 0;
    }

    .ios-menu-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px 16px;
        border-bottom: 1px solid var(--border-color);
        flex-shrink: 0;
    }

    .ios-menu-title {
        font-size: 17px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .ios-menu-close {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: var(--bg-secondary);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-secondary);
        cursor: pointer;
        transition: background 0.2s ease;
    }

    .ios-menu-close:hover {
        background: var(--border-color);
    }

    .ios-menu-content {
        padding: 16px;
        overflow-y: auto;
        flex: 1;
        -webkit-overflow-scrolling: touch;
    }

    .ios-menu-section {
        margin-bottom: 20px;
    }

    .ios-menu-section:last-child {
        margin-bottom: 0;
    }

    .ios-menu-section-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
        padding-left: 4px;
    }

    .ios-menu-card {
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
    }

    .ios-menu-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px;
        border-bottom: 1px solid var(--border-color);
        text-decoration: none;
        color: var(--text-primary);
        transition: background 0.15s ease;
        cursor: pointer;
        /* Reset button styles for button elements */
        background: transparent;
        width: 100%;
        text-align: left;
        font-family: inherit;
        font-size: inherit;
        outline: none;
    }

    /* Ensure button elements don't have extra borders */
    button.ios-menu-item {
        border: none;
        border-bottom: 1px solid var(--border-color);
    }

    button.ios-menu-item:last-child {
        border-bottom: none;
    }

    .ios-menu-item:last-child {
        border-bottom: none;
    }

    .ios-menu-item:active {
        background: var(--bg-subtle);
    }

    .ios-menu-item-left {
        display: flex;
        align-items: center;
        gap: 12px;
        flex: 1;
    }

    .ios-menu-item-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    .ios-menu-item-icon.primary { background: rgba(48, 209, 88, 0.15); color: var(--ios-green); }
    .ios-menu-item-icon.blue { background: rgba(10, 132, 255, 0.15); color: var(--ios-blue); }
    .ios-menu-item-icon.purple { background: rgba(191, 90, 242, 0.15); color: var(--ios-purple); }
    .ios-menu-item-icon.orange { background: rgba(255, 159, 10, 0.15); color: var(--ios-orange); }

    .ios-menu-item-content {
        flex: 1;
    }

    .ios-menu-item-label {
        font-size: 15px;
        font-weight: 500;
    }

    .ios-menu-item-desc {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 2px;
    }

    .ios-menu-item-chevron {
        color: var(--text-muted);
        font-size: 12px;
    }

    .ios-menu-stat-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 16px;
        border-bottom: 1px solid var(--border-color);
    }

    .ios-menu-stat-row:last-child {
        border-bottom: none;
    }

    .ios-menu-stat-label {
        font-size: 15px;
        color: var(--text-primary);
    }

    .ios-menu-stat-value {
        font-size: 15px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .ios-menu-stat-value.success { color: var(--ios-green); }
    .ios-menu-stat-value.warning { color: var(--ios-orange); }
    .ios-menu-stat-value.info { color: var(--ios-purple); }

    /* Mobile Responsive */
    @media (max-width: 992px) {
        .ios-options-btn {
            display: flex;
        }

        .desktop-sidebar {
            display: none !important;
        }

        .desktop-filter-section {
            display: none !important;
        }
    }

    @media (max-width: 768px) {
        .content-header {
            display: none !important;
        }

        .content .payment-type-tabs {
            margin-bottom: var(--spacing-3);
        }

        .content .payment-stats-grid {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            gap: 0.75rem !important;
            padding-bottom: 0.5rem !important;
            -webkit-overflow-scrolling: touch;
        }

        .content .payment-stat-card {
            flex: 0 0 auto !important;
            min-width: 160px !important;
            padding: 12px !important;
        }

        .content .payment-stat-icon {
            width: 40px !important;
            height: 40px !important;
            font-size: 1rem !important;
        }

        .content .payment-stat-value {
            font-size: 1.25rem !important;
        }

        .content .payment-layout-grid {
            grid-template-columns: 1fr;
        }

        .content .plans-grid {
            grid-template-columns: 1fr;
        }

        .content .filter-grid {
            grid-template-columns: 1fr;
        }

        .content .dasher-table-container {
            display: none;
        }

        #paymentsList {
            display: block;
        }

        .content .table-pagination {
            flex-direction: column;
            gap: 12px;
        }

        .content .payment-section-card {
            border-radius: 12px;
        }

        .content .payment-section-header {
            padding: 14px;
        }

        .content .payment-section-body {
            padding: 14px;
        }

        .ios-section-card {
            border-radius: 12px;
        }

        .ios-section-header {
            padding: 14px;
        }

        .ios-section-icon {
            width: 36px;
            height: 36px;
            font-size: 16px;
        }

        .ios-section-title h5 {
            font-size: 15px;
        }

        .ios-payment-log-item {
            padding: 12px 14px;
        }

        .ios-payment-amount-title {
            font-size: 14px;
        }

        .ios-payment-plan-info {
            font-size: 12px;
        }

        .ios-payment-time {
            font-size: 11px;
        }

        .ios-status-badge {
            font-size: 10px;
            padding: 2px 6px;
        }

        .ios-empty-state {
            padding: 32px 16px;
        }

        .ios-empty-icon {
            font-size: 48px;
        }

        .ios-empty-title {
            font-size: 16px;
        }

        .ios-empty-description {
            font-size: 13px;
        }
    }

    @media (max-width: 480px) {
        .ios-options-btn {
            width: 32px;
            height: 32px;
        }

        .ios-options-btn i {
            font-size: 14px;
        }

        .content .payment-type-tab {
            padding: 10px 12px;
            font-size: 13px;
        }

        .content .payment-type-tab span {
            display: none;
        }

        .content .payment-type-tab i {
            margin: 0;
        }

        .ios-payment-meta {
            display: none;
        }

        .ios-payment-log-item {
            position: relative;
        }

        .ios-payment-log-item::after {
            content: attr(data-status);
            position: absolute;
            top: 12px;
            right: 14px;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
        }

        .ios-payment-log-item[data-status="completed"]::after {
            background: rgba(48, 209, 88, 0.15);
            color: var(--ios-green);
        }

        .ios-payment-log-item[data-status="pending"]::after {
            background: rgba(255, 159, 10, 0.15);
            color: var(--ios-orange);
        }

        .ios-payment-log-item[data-status="failed"]::after {
            background: rgba(255, 69, 58, 0.15);
            color: var(--ios-red);
        }

        .ios-payment-log-item[data-status="refunded"]::after {
            background: rgba(100, 210, 255, 0.15);
            color: var(--ios-teal);
        }
    }

    /* Empty State */
    .content .empty-state {
        text-align: center;
        padding: var(--spacing-8) var(--spacing-4);
        color: var(--text-muted);
    }

    .content .empty-state i {
        font-size: 3rem;
        opacity: 0.3;
        margin-bottom: var(--spacing-4);
    }

    .content .empty-state h5 {
        color: var(--text-secondary);
        margin-bottom: var(--spacing-2);
    }
</style>

<!-- Dasher UI Content Area -->
<div class="content">
    <!-- Content Header -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="content-title">
                    <i class="fas fa-credit-card me-2"></i>
                    Payment Settings
                </h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Payment Settings</li>
                    </ol>
                </nav>
                <p class="content-description">Manage your payment methods, subscriptions, and licenses</p>
            </div>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <div class="alert-content">
                <div class="alert-title">Error</div>
                <div class="alert-message"><?php echo $error; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle"></i>
            <div class="alert-content">
                <div class="alert-title">Success!</div>
                <div class="alert-message"><?php echo $success; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Payment Type Tabs -->
    <div class="payment-type-tabs">
        <a href="?type=clan" class="payment-type-tab <?php echo $paymentType === 'clan' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Estate Subscription</span>
        </a>
        <a href="?type=license" class="payment-type-tab <?php echo $paymentType === 'license' ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i>
            <span>Purchase Licenses</span>
        </a>
    </div>

    <!-- Payment Statistics -->
    <div class="payment-stats-grid">
        <div class="payment-stat-card">
            <div class="payment-stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="payment-stat-content">
                <div class="payment-stat-label">Completed Payments</div>
                <div class="payment-stat-value"><?php echo number_format($paymentStats['completed_count'] ?? 0); ?></div>
                <div class="payment-stat-detail">₦<?php echo number_format($paymentStats['completed_amount'] ?? 0, 2); ?> total</div>
            </div>
        </div>

        <div class="payment-stat-card">
            <div class="payment-stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="payment-stat-content">
                <div class="payment-stat-label">Pending Payments</div>
                <div class="payment-stat-value"><?php echo number_format($paymentStats['pending_count'] ?? 0); ?></div>
                <div class="payment-stat-detail">Awaiting completion</div>
            </div>
        </div>

        <div class="payment-stat-card">
            <div class="payment-stat-icon primary">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="payment-stat-content">
                <div class="payment-stat-label">Total Payments</div>
                <div class="payment-stat-value"><?php echo number_format($paymentStats['total_payments'] ?? 0); ?></div>
                <div class="payment-stat-detail">All transactions</div>
            </div>
        </div>

        <div class="payment-stat-card">
            <div class="payment-stat-icon info">
                <i class="fas fa-home"></i>
            </div>
            <div class="payment-stat-content">
                <div class="payment-stat-label">Number of Households</div>
                <div class="payment-stat-value"><?php echo number_format($totalHouseholdsCount); ?></div>
                <div class="payment-stat-detail">In your clan</div>
            </div>
        </div>
    </div>

    <?php if ($paymentType === 'clan'): ?>
        <!-- CLAN SUBSCRIPTION CONTENT -->
        <div class="payment-layout-grid">
            <div>
                <!-- Current Plan Status -->
                <div class="payment-section-card">
                    <div class="payment-section-header">
                        <div class="payment-section-icon primary">
                            <i class="fas fa-crown"></i>
                        </div>
                        <div class="payment-section-title">
                            <h5>Current Plan</h5>
                            <p>Your active subscription details</p>
                        </div>
                    </div>
                    <div class="payment-section-body">
                        <?php if ($currentPlan): ?>
                            <div class="info-banner testing success">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <strong><?php echo htmlspecialchars($currentPlan['name']); ?></strong>
                                    <?php if ($currentPlan['is_free']): ?>
                                        <span class="status-badge completed ms-2">Free</span>
                                    <?php elseif ($currentPlan['is_per_user']): ?>
                                        <span class="status-badge pending ms-2">₦<?php echo number_format($currentPlan['price_per_user'], 2); ?> / Household</span>
                                    <?php else: ?>
                                        <span class="status-badge pending ms-2">₦<?php echo number_format($currentPlan['price'], 2); ?></span>
                                    <?php endif; ?>
                                    <p class="mb-0 mt-2"><?php echo htmlspecialchars($currentPlan['description'] ?? 'No description'); ?></p>
                                </div>
                            </div>

                            <?php if ($currentPlan['is_per_user']): ?>
    <div class="info-banner warning mt-3">
        <i class="fas fa-home"></i>
        <div>
            <strong>Per-Household Plan:</strong> Your estate has <strong><?php echo $householdCount; ?> billable household(s)</strong>
            <br>
            <span class="text-primary">
                ₦<?php echo number_format($currentPlan['price_per_user'], 2); ?> × <?php echo $householdCount; ?> =
                ₦<?php echo number_format($currentPlan['price_per_user'] * $householdCount, 2); ?> total
            </span>
            <br>
            <small class="text-muted mt-2 d-block">
                <i class="fas fa-info-circle"></i> 
                Active: <?php echo $householdBreakdown['active']; ?> | 
                Recently Inactive (less than 15 days): <?php echo $householdBreakdown['recently_inactive']; ?> | 
                Fully Inactive (greater than 15 days): <?php echo $householdBreakdown['fully_inactive']; ?> (not billed)
            </small>
            <small class="text-muted d-block mt-1">
                Households remain billable for 15 days after being marked inactive.
            </small>
        </div>
    </div>
<?php endif; ?>

                            <!-- Make Payment Button - Prominent Placement -->
                            <?php if ($clan->getPaymentStatus() !== 'free' && !$currentPlan['is_free']): ?>
                                <div class="mt-4 d-flex gap-3 flex-wrap">
                                    <button type="button" class="btn btn-primary flex-fill" data-bs-toggle="modal" data-bs-target="#makePaymentModal">
                                        <i class="fas fa-credit-card me-2"></i>Make Payment
                                    </button>
                                    <!-- <a href="?type=license" class="btn btn-outline-primary flex-fill">
                                        <i class="fas fa-user-plus me-2"></i>Buy Licenses
                                    </a> -->
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="info-banner warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>No Plan Selected</strong>
                                    <p class="mb-0">Please select a pricing plan to continue using the system.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Available Plans -->
                <?php if (!empty($assignedPlans)): ?>
                    <div class="payment-section-card">
                        <div class="payment-section-header">
                            <div class="payment-section-icon success">
                                <i class="fas fa-tags"></i>
                            </div>
                            <div class="payment-section-title">
                                <h5>Available Plans</h5>
                                <p>Choose the plan that fits your needs</p>
                            </div>
                        </div>
                        <div class="payment-section-body">
                            <div class="plans-grid">
                                <?php foreach ($assignedPlans as $plan): ?>
                                    <div class="plan-card <?php echo ($currentPlan && $currentPlan['id'] == $plan['id']) ? 'current-plan' : ''; ?>">
                                        <div class="plan-card-header">
                                            <div class="plan-name"><?php echo htmlspecialchars($plan['name']); ?></div>
                                            <div class="plan-price">
                                                <?php if ($plan['is_free']): ?>
                                                    Free
                                                <?php elseif ($plan['is_per_user']): ?>
                                                    ₦<?php echo number_format($plan['price_per_user'], 2); ?>
                                                <?php else: ?>
                                                    ₦<?php echo number_format($plan['price'], 2); ?>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($plan['is_per_user']): ?>
                                                <div class="plan-price-detail">per user / <?php echo $plan['duration_days']; ?> days</div>
                                            <?php elseif (!$plan['is_free']): ?>
                                                <div class="plan-price-detail">per <?php echo $plan['duration_days']; ?> days</div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="plan-description">
                                            <?php echo htmlspecialchars($plan['description'] ?? 'No description available.'); ?>
                                        </div>

                                        <?php if (!empty($plan['features'])): ?>
                                            <ul class="plan-features">
                                                <?php
                                                $features = explode("\n", $plan['features']);
                                                foreach ($features as $feature):
                                                    if (trim($feature)):
                                                ?>
                                                        <li>
                                                            <i class="fas fa-check"></i>
                                                            <?php echo htmlspecialchars(trim($feature)); ?>
                                                        </li>
                                                <?php
                                                    endif;
                                                endforeach;
                                                ?>
                                            </ul>
                                        <?php endif; ?>

                                        <?php if ($currentPlan && $currentPlan['id'] == $plan['id']): ?>
                                            <button class="btn btn-outline-primary w-100" disabled>Current Plan</button>
                                        <?php elseif ($currentUser->isSuperAdmin()): ?>
                                            <a href="<?php echo BASE_URL; ?>clans/edit.php?id=<?php echo $clanId; ?>&plan=<?php echo $plan['id']; ?>"
                                                class="btn btn-primary w-100">
                                                Select Plan
                                            </a>
                                        <?php elseif (!$plan['is_free']): ?>
                                            <button type="button"
                                                class="btn btn-primary w-100"
                                                onclick="PaymentSettings.openPaymentModal('<?php echo $plan['id']; ?>', '<?php echo htmlspecialchars($plan['name']); ?>', '<?php echo $plan['is_per_user'] ? number_format($plan['price_per_user'] * $userCount, 2) : number_format($plan['price'], 2); ?>')">
                                                Subscribe
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar - Desktop Only -->
            <div class="desktop-sidebar">
                <!-- Payment Settings -->
                <div class="payment-sidebar-card">
                    <div class="sidebar-card-header">
                        <i class="fas fa-cog"></i>
                        <h6>Payment Settings</h6>
                    </div>
                    <div class="sidebar-card-body">
                        <form method="post" action="">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="paystack_enabled"
                                        name="paystack_enabled" <?php echo $paymentSettings['paystack_enabled'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="paystack_enabled">
                                        <i class="fas fa-credit-card me-2"></i>Enable Paystack
                                    </label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="auto_renew"
                                        name="auto_renew" <?php echo $paymentSettings['auto_renew'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_renew">
                                        <i class="fas fa-sync-alt me-2"></i>Auto-Renew
                                    </label>
                                </div>
                            </div>

                            <button type="submit" name="update_settings" class="btn btn-primary w-100">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="payment-sidebar-card">
                    <div class="sidebar-card-header">
                        <i class="fas fa-bolt"></i>
                        <h6>Quick Actions</h6>
                    </div>
                    <div class="sidebar-card-body">
                        <div class="d-grid gap-2">
                            <a href="?type=license" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i>Buy Licenses
                            </a>
                            <?php if ($clan->getPaymentStatus() !== 'free'): ?>
                                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#makePaymentModal">
                                    <i class="fas fa-credit-card me-2"></i>Make Payment
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- LICENSE PURCHASE CONTENT -->
        <div class="payment-layout-grid">
            <div>
                <div class="payment-section-card">
                    <div class="payment-section-header">
                        <div class="payment-section-icon success">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="payment-section-title">
                            <h5>Purchase Household Licenses</h5>
                            <p>Add more member slots to your households</p>
                        </div>
                    </div>
                    <div class="payment-section-body">
                        <?php if (!$currentPlan || !$currentPlan['is_per_user']): ?>
                            <div class="info-banner warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div>
                                    <strong>Per-User Plan Required</strong>
                                    <p class="mb-0">Your clan is not on a per-user plan. Please contact your administrator.</p>
                                </div>
                            </div>
                        <?php else: 
                            // Get households for this clan that can still purchase licenses
                            $eligibleHouseholds = $db->fetchAll(
                                "SELECT 
                                    id, 
                                    name, 
                                    address,
                                    max_members, 
                                    extra_member_licenses,
                                    current_members,
                                    (max_members + extra_member_licenses) as total_capacity
                                 FROM households 
                                 WHERE clan_id = ? 
                                 AND status = 'active'
                                 AND (max_members + extra_member_licenses) < 5
                                 ORDER BY name ASC",
                                [$clanId]
                            );
                        ?>
                            <?php if (empty($eligibleHouseholds)): ?>
                                <div class="info-banner warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <strong>No Households Available</strong>
                                        <p class="mb-0">All households have reached the maximum capacity of 5 members. You cannot purchase additional licenses at this time.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="info-banner success mb-4">
                                    <i class="fas fa-info-circle"></i>
                                    <div>
                                        Purchase additional member slots for your households. 
                                        Each license costs <strong>₦<?php echo number_format($currentPlan['price_per_user'], 2); ?></strong>
                                        <br><small class="text-muted">Maximum capacity per household: 5 members (3 default + up to 2 additional)</small>
                                    </div>
                                </div>

                                <form method="post" id="householdLicenseForm">
                                    <!-- Household Selection -->
                                    <div class="license-form-group">
                                        <label class="form-label">Select Household</label>
                                        <select class="form-control" id="household_id" name="household_id" required>
                                            <option value="">-- Select a Household --</option>
                                            <?php foreach ($eligibleHouseholds as $house): 
                                                $availableLicenses = 5 - ($house['max_members'] + $house['extra_member_licenses']);
                                                $currentCapacity = $house['max_members'] + $house['extra_member_licenses'];
                                            ?>
                                                <option value="<?php echo $house['id']; ?>" 
                                                        data-current-capacity="<?php echo $currentCapacity; ?>"
                                                        data-current-members="<?php echo $house['current_members']; ?>"
                                                        data-available-licenses="<?php echo $availableLicenses; ?>">
                                                    <?php echo htmlspecialchars($house['name']); ?> 
                                                    (<?php echo $house['current_members']; ?>/<?php echo $currentCapacity; ?> members, 
                                                    can add <?php echo $availableLicenses; ?> more license<?php echo $availableLicenses > 1 ? 's' : ''; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Choose which household to purchase additional member slots for</div>
                                    </div>

                                    <!-- License Count Selection -->
                                    <div class="license-form-group" id="licenseCountContainer" style="display: none;">
                                        <label class="form-label">Number of Licenses</label>
                                        <select class="form-control" id="license_count" name="license_count">
                                            <option value="1">1 License</option>
                                            <option value="2" id="twoLicensesOption">2 Licenses</option>
                                        </select>
                                        <div class="form-text" id="licenseHelperText"></div>
                                    </div>

                                    <!-- Order Summary -->
                                    <div class="order-summary" id="orderSummary" style="display: none;">
                                        <h6 class="mb-3">Order Summary</h6>
                                        <div class="summary-row">
                                            <span>Household:</span>
                                            <span id="selectedHouseholdName">-</span>
                                        </div>
                                        <div class="summary-row">
                                            <span>Current Capacity:</span>
                                            <span id="currentCapacityDisplay">-</span>
                                        </div>
                                        <div class="summary-row">
                                            <span>Price per license:</span>
                                            <span>₦<?php echo number_format($currentPlan['price_per_user'], 2); ?></span>
                                        </div>
                                        <div class="summary-row">
                                            <span>Number of licenses:</span>
                                            <span id="licenseCountDisplay">1</span>
                                        </div>
                                        <div class="summary-row">
                                            <span>New Total Capacity:</span>
                                            <span id="newCapacityDisplay">-</span>
                                        </div>
                                        <div class="summary-row total">
                                            <span>Total:</span>
                                            <span id="totalCost">₦<?php echo number_format($currentPlan['price_per_user'], 2); ?></span>
                                        </div>
                                    </div>

                                    <h6 class="mb-3">Payment Method</h6>
                                    <div class="mb-4">
                                        <?php if ($paymentSettings['paystack_enabled']): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="payment_method"
                                                    id="paystack_license" value="paystack" checked>
                                                <label class="form-check-label" for="paystack_license">
                                                    <i class="fas fa-credit-card me-2"></i>Pay with Paystack
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex gap-3">
                                        <a href="?type=clan" class="btn btn-secondary flex-fill">
                                            <i class="fas fa-arrow-left me-2"></i>Back
                                        </a>
                                        <button type="submit" class="btn btn-primary flex-fill" id="purchaseBtn" disabled>
                                            <i class="fas fa-shopping-cart me-2"></i>Purchase
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar - Desktop Only -->
            <div class="desktop-sidebar">
                <div class="payment-sidebar-card">
                    <div class="sidebar-card-header">
                        <i class="fas fa-lightbulb"></i>
                        <h6>License Information</h6>
                    </div>
                    <div class="sidebar-card-body">
                        <p class="small text-muted mb-3">Each license adds one member slot to a household.</p>
                        <div class="summary-row">
                            <span class="small">Total Households:</span>
                            <strong><?php echo $db->fetchOne("SELECT COUNT(*) as count FROM households WHERE clan_id = ?", [$clanId])['count'] ?? 0; ?></strong>
                        </div>
                        <div class="summary-row">
                            <span class="small">Households at Capacity:</span>
                            <strong><?php echo $db->fetchOne("SELECT COUNT(*) as count FROM households WHERE clan_id = ? AND (max_members + extra_member_licenses) >= 5", [$clanId])['count'] ?? 0; ?></strong>
                        </div>
                        <div class="summary-row">
                            <span class="small">Can Purchase For:</span>
                            <strong><?php echo count($eligibleHouseholds ?? []); ?></strong>
                        </div>
                        <hr>
                        <div class="mt-3">
                            <small class="text-muted d-block mb-2"><strong>Capacity Rules:</strong></small>
                            <small class="text-muted d-block">• Default: 3 members</small>
                            <small class="text-muted d-block">• Maximum: 5 members</small>
                            <small class="text-muted d-block">• Purchasable: Up to 2 licenses</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
       

    <!-- Payment History -->
    <div class="ios-section-card">
        <div class="ios-section-header">
            <div class="ios-section-icon purple">
                <i class="fas fa-history"></i>
            </div>
            <div class="ios-section-title">
                <h5>Payment History</h5>
                <p>Showing <?php echo count($paymentHistory); ?> of <?php echo number_format($totalHistoryCount); ?> transactions</p>
            </div>
            <button class="ios-options-btn" onclick="openIosMenu()" aria-label="Open menu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>

        <!-- Search Box -->
        <div class="ios-search-box">
            <form action="" method="get" id="quickSearchForm">
                <input type="hidden" name="type" value="<?php echo $paymentType; ?>">
                <div class="ios-search-input-wrapper">
                    <i class="fas fa-search ios-search-icon"></i>
                    <input
                        type="text"
                        name="search"
                        id="paymentSearch"
                        class="ios-search-input"
                        placeholder="Search payments..."
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                        autocomplete="off"
                    >
                    <button type="button" class="ios-search-clear" id="clearSearch" onclick="clearSearchField()">
                        <i class="fas fa-times"></i>
                    </button>
                    <?php if ($statusFilter): ?>
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                    <?php endif; ?>
                    <?php if ($purposeFilter): ?>
                        <input type="hidden" name="purpose" value="<?php echo htmlspecialchars($purposeFilter); ?>">
                    <?php endif; ?>
                    <?php if ($dateFrom): ?>
                        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    <?php endif; ?>
                    <?php if ($dateTo): ?>
                        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Filter Pills -->
        <div class="ios-filter-pills">
            <?php
            $baseUrl = "?type={$paymentType}";
            if ($purposeFilter) $baseUrl .= "&purpose={$purposeFilter}";
            if ($dateFrom) $baseUrl .= "&date_from={$dateFrom}";
            if ($dateTo) $baseUrl .= "&date_to={$dateTo}";
            ?>
            <a href="<?php echo $baseUrl; ?>"
               class="ios-filter-pill <?php echo empty($statusFilter) ? 'active' : ''; ?>">
                All
            </a>
            <a href="<?php echo $baseUrl; ?>&status=completed"
               class="ios-filter-pill completed <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                Completed
            </a>
            <a href="<?php echo $baseUrl; ?>&status=pending"
               class="ios-filter-pill pending <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i>
                Pending
            </a>
            <a href="<?php echo $baseUrl; ?>&status=failed"
               class="ios-filter-pill failed <?php echo $statusFilter === 'failed' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i>
                Failed
            </a>
        </div>

        <!-- Filters - Desktop Only -->
        <div class="payment-section-body desktop-filter-section" style="border-bottom: 1px solid var(--border-color);">
            <form method="get" id="filterForm">
                <input type="hidden" name="type" value="<?php echo $paymentType; ?>">
                <div class="filter-section">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="filter-input">
                                <option value="">All Status</option>
                                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                <option value="refunded" <?php echo $statusFilter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">Purpose</label>
                            <select name="purpose" class="filter-input">
                                <option value="">All Purposes</option>
                                <option value="clan_subscription" <?php echo $purposeFilter === 'clan_subscription' ? 'selected' : ''; ?>>Estate Subscription</option>
                                <option value="license_purchase" <?php echo $purposeFilter === 'license_purchase' ? 'selected' : ''; ?>>User License</option>
                                <option value="household_license_purchase" <?php echo $purposeFilter === 'household_license_purchase' ? 'selected' : ''; ?>>Household License</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">From Date</label>
                            <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label">To Date</label>
                            <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <a href="?type=<?php echo $paymentType; ?>" class="btn btn-sm btn-secondary">
                            <i class="fas fa-times me-2"></i>Clear
                        </a>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-filter me-2"></i>Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (empty($paymentHistory)): ?>
            <!-- Empty State -->
            <div class="ios-empty-state">
                <div class="ios-empty-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <h3 class="ios-empty-title">No Payments Found</h3>
                <p class="ios-empty-description">
                    Try adjusting your filters or check back later.
                </p>
                <a href="?type=<?php echo $paymentType; ?>" class="ios-btn ios-btn-primary">
                    <i class="fas fa-undo"></i>
                    Reset Filters
                </a>
            </div>
        <?php else: ?>
            <!-- Desktop Table -->
            <div class="dasher-table-container">
                <table class="dasher-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Plan</th>
                            <th>Purpose</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentHistory as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td><strong>₦<?php echo number_format($payment['amount'], 2); ?></strong></td>
                                <td><?php echo htmlspecialchars($payment['plan_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $purpose = $payment['purpose'] ?? 'clan_subscription';
                                    if ($purpose === 'license_purchase' || $purpose === 'household_license_purchase'):
                                    ?>
                                        <span class="purpose-badge license">
                                            <?php echo $purpose === 'household_license_purchase' ? 'Household License' : 'License Purchase'; ?>
                                        </span>
                                        <?php if ($payment['user_count']): ?>
                                            <small>(<?php echo $payment['user_count']; ?> license<?php echo $payment['user_count'] > 1 ? 's' : ''; ?>)</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="purpose-badge subscription">Estate Subscription</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($payment['payment_method'] === 'paystack'): ?>
                                        <i class="fas fa-credit-card me-1"></i>Paystack
                                    <?php elseif ($payment['payment_method'] === 'bank_transfer'): ?>
                                        <i class="fas fa-university me-1"></i>Bank Transfer
                                    <?php else: ?>
                                        <?php echo ucfirst($payment['payment_method']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo substr($payment['transaction_id'], 0, 15); ?>...</small>
                                </td>
                                <td>
                                    <a href="<?php echo BASE_URL; ?>payments/receipt.php?id=<?php echo encryptId($payment['id']); ?>"
                                        class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-receipt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Payment List - iOS Style -->
            <div class="ios-section-body" id="paymentsList" style="padding: 0;">
                <?php foreach ($paymentHistory as $payment):
                    $purpose = $payment['purpose'] ?? 'clan_subscription';
                    $purposeLabel = ($purpose === 'household_license_purchase') ? 'Household License' :
                                   (($purpose === 'license_purchase') ? 'License Purchase' : 'Estate Subscription');
                ?>
                    <a href="<?php echo BASE_URL; ?>payments/receipt.php?id=<?php echo encryptId($payment['id']); ?>"
                       class="ios-payment-log-item"
                       data-status="<?php echo htmlspecialchars($payment['status']); ?>">
                        <span class="ios-payment-dot <?php echo $payment['status']; ?>"></span>
                        <div class="ios-payment-content">
                            <p class="ios-payment-amount-title">₦<?php echo number_format($payment['amount'], 2); ?></p>
                            <p class="ios-payment-plan-info">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($payment['plan_name'] ?? 'N/A'); ?>
                            </p>
                            <p class="ios-payment-plan-info">
                                <i class="fas fa-file-invoice"></i>
                                <?php echo $purposeLabel; ?>
                                <?php if ($payment['user_count']): ?>
                                    (<?php echo $payment['user_count']; ?> license<?php echo $payment['user_count'] > 1 ? 's' : ''; ?>)
                                <?php endif; ?>
                            </p>
                            <p class="ios-payment-plan-info">
                                <?php if ($payment['payment_method'] === 'paystack'): ?>
                                    <i class="fas fa-credit-card"></i>Paystack
                                <?php elseif ($payment['payment_method'] === 'bank_transfer'): ?>
                                    <i class="fas fa-university"></i>Bank Transfer
                                <?php else: ?>
                                    <i class="fas fa-money-bill"></i><?php echo ucfirst($payment['payment_method']); ?>
                                <?php endif; ?>
                            </p>
                            <p class="ios-payment-time">
                                <i class="fas fa-clock"></i>
                                <?php echo date('M d, Y \a\t g:i A', strtotime($payment['payment_date'])); ?>
                            </p>
                        </div>
                        <div class="ios-payment-meta">
                            <span class="ios-status-badge <?php echo $payment['status']; ?>">
                                <?php echo ucfirst($payment['status']); ?>
                            </span>
                            <span class="ios-payment-txn-badge"><?php echo substr($payment['transaction_id'], 0, 10); ?>...</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- iOS Pagination -->
            <?php if ($totalHistoryPages > 1): ?>
                <div class="ios-pagination">
                    <!-- Previous -->
                    <a href="?type=<?php echo $paymentType; ?>&history_page=<?php echo $historyPage - 1; ?><?php echo $statusFilter ? '&status=' . $statusFilter : ''; ?><?php echo $purposeFilter ? '&purpose=' . $purposeFilter : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>"
                       class="ios-page-btn <?php echo ($historyPage <= 1) ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>

                    <!-- Page Numbers -->
                    <?php
                    $startPage = max(1, $historyPage - 2);
                    $endPage = min($totalHistoryPages, $startPage + 4);
                    if ($endPage - $startPage < 4) {
                        $startPage = max(1, $endPage - 4);
                    }

                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="?type=<?php echo $paymentType; ?>&history_page=<?php echo $i; ?><?php echo $statusFilter ? '&status=' . $statusFilter : ''; ?><?php echo $purposeFilter ? '&purpose=' . $purposeFilter : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>"
                           class="ios-page-btn <?php echo ($i == $historyPage) ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <!-- Next -->
                    <a href="?type=<?php echo $paymentType; ?>&history_page=<?php echo $historyPage + 1; ?><?php echo $statusFilter ? '&status=' . $statusFilter : ''; ?><?php echo $purposeFilter ? '&purpose=' . $purposeFilter : ''; ?><?php echo $dateFrom ? '&date_from=' . $dateFrom : ''; ?><?php echo $dateTo ? '&date_to=' . $dateTo : ''; ?>"
                       class="ios-page-btn <?php echo ($historyPage >= $totalHistoryPages) ? 'disabled' : ''; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- iOS-Style Mobile Menu Modal -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Payment Settings</h3>
        <button class="ios-menu-close" id="iosMenuClose">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <div class="ios-menu-content">
        <!-- Filters -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter History</div>
            <div class="ios-menu-card" style="padding: 16px;">
                <form method="get" class="ios-filter-form" id="mobileFilterForm">
                    <input type="hidden" name="type" value="<?php echo $paymentType; ?>">

                    <div class="ios-filter-group">
                        <label class="ios-filter-label">Status</label>
                        <select name="status" class="ios-filter-input">
                            <option value="">All Status</option>
                            <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="failed" <?php echo $statusFilter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo $statusFilter === 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>

                    <div class="ios-filter-group">
                        <label class="ios-filter-label">Purpose</label>
                        <select name="purpose" class="ios-filter-input">
                            <option value="">All Purposes</option>
                            <option value="clan_subscription" <?php echo $purposeFilter === 'clan_subscription' ? 'selected' : ''; ?>>Estate Subscription</option>
                            <option value="license_purchase" <?php echo $purposeFilter === 'license_purchase' ? 'selected' : ''; ?>>User License</option>
                            <option value="household_license_purchase" <?php echo $purposeFilter === 'household_license_purchase' ? 'selected' : ''; ?>>Household License</option>
                        </select>
                    </div>

                    <div class="ios-filter-group">
                        <label class="ios-filter-label">From Date</label>
                        <input type="date" name="date_from" class="ios-filter-input" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>

                    <div class="ios-filter-group">
                        <label class="ios-filter-label">To Date</label>
                        <input type="date" name="date_to" class="ios-filter-input" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>

                    <div class="ios-filter-actions">
                        <button type="submit" class="ios-btn ios-btn-primary">
                            <i class="fas fa-filter"></i>
                            Apply
                        </button>
                        <a href="?type=<?php echo $paymentType; ?>" class="ios-btn ios-btn-secondary">
                            <i class="fas fa-undo"></i>
                            Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <a href="?type=clan" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue">
                            <i class="fas fa-building"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Estate Subscription</span>
                            <span class="ios-menu-item-desc">Manage your plan</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="?type=license" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Purchase Licenses</span>
                            <span class="ios-menu-item-desc">Add more member slots</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php if ($clan->getPaymentStatus() !== 'free'): ?>
                <button type="button" class="ios-menu-item" data-bs-toggle="modal" data-bs-target="#makePaymentModal" onclick="closeIosMenu()">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Make Payment</span>
                            <span class="ios-menu-item-desc">Pay for subscription</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistics -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Statistics</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Completed Payments</span>
                    <span class="ios-menu-stat-value success"><?php echo number_format($paymentStats['completed_count'] ?? 0); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Pending Payments</span>
                    <span class="ios-menu-stat-value warning"><?php echo number_format($paymentStats['pending_count'] ?? 0); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Payments</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($paymentStats['total_payments'] ?? 0); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Number of Households</span>
                    <span class="ios-menu-stat-value info"><?php echo number_format($totalHouseholdsCount); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Make Payment Modal -->
<div class="modal fade" id="makePaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Make Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="pricing_plan_id" id="modal_plan_id" value="<?php echo $currentPlan ? $currentPlan['id'] : ''; ?>">

                    <div class="mb-3">
                        <label class="form-label">Selected Plan</label>
                        <input type="text" class="form-control" id="modal_plan_name" readonly value="<?php echo $currentPlan ? htmlspecialchars($currentPlan['name']) : ''; ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="text" class="form-control" id="modal_amount" readonly value="<?php echo $currentPlan ? ($currentPlan['is_per_user'] ? number_format($currentPlan['price_per_user'] * $householdCount, 2) : number_format($currentPlan['price'], 2)) : '0.00'; ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <?php if ($paymentSettings['paystack_enabled']): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="payment_method" value="paystack" checked>
                                <label class="form-check-label">
                                    <i class="fas fa-credit-card me-2"></i>Paystack
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Proceed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Namespaced JavaScript -->
<script>
    // iOS Mobile Menu Functions
    const iosMenuBackdrop = document.getElementById('iosMenuBackdrop');
    const iosMenuModal = document.getElementById('iosMenuModal');
    const iosMenuClose = document.getElementById('iosMenuClose');

    function openIosMenu() {
        if (iosMenuBackdrop && iosMenuModal) {
            iosMenuBackdrop.classList.add('active');
            iosMenuModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }

    function closeIosMenu() {
        if (iosMenuBackdrop && iosMenuModal) {
            iosMenuBackdrop.classList.remove('active');
            iosMenuModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    if (iosMenuClose) {
        iosMenuClose.addEventListener('click', closeIosMenu);
    }

    if (iosMenuBackdrop) {
        iosMenuBackdrop.addEventListener('click', closeIosMenu);
    }

    // Swipe down to close menu
    let menuStartY = 0;
    let menuCurrentY = 0;

    if (iosMenuModal) {
        iosMenuModal.addEventListener('touchstart', (e) => {
            menuStartY = e.touches[0].clientY;
        });

        iosMenuModal.addEventListener('touchmove', (e) => {
            menuCurrentY = e.touches[0].clientY;
            const diff = menuCurrentY - menuStartY;
            if (diff > 0) {
                iosMenuModal.style.transform = `translateY(${diff}px)`;
            }
        });

        iosMenuModal.addEventListener('touchend', () => {
            const diff = menuCurrentY - menuStartY;
            if (diff > 100) {
                closeIosMenu();
            }
            iosMenuModal.style.transform = '';
            menuStartY = 0;
            menuCurrentY = 0;
        });
    }

    // Search functionality
    const searchInput = document.getElementById('paymentSearch');
    const clearBtn = document.getElementById('clearSearch');
    const quickSearchForm = document.getElementById('quickSearchForm');

    if (searchInput) {
        // Show/hide clear button based on input
        searchInput.addEventListener('input', function() {
            if (clearBtn) {
                clearBtn.classList.toggle('visible', this.value.length > 0);
            }
        });

        // Initialize clear button visibility
        if (clearBtn && searchInput.value.length > 0) {
            clearBtn.classList.add('visible');
        }

        // Submit on Enter key
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                quickSearchForm.submit();
            }
        });
    }

    function clearSearchField() {
        if (searchInput) {
            searchInput.value = '';
            if (clearBtn) clearBtn.classList.remove('visible');
            searchInput.focus();
        }
    }

    // Namespace to avoid conflicts
    const PaymentSettings = {
        init() {
            console.log('🎨 Initializing Payment Settings...');
            this.initializeHouseholdLicenseForm();
            this.initializePaymentModal();
        },

        initializeHouseholdLicenseForm() {
            const householdSelect = document.getElementById('household_id');
            const licenseCountSelect = document.getElementById('license_count');
            const licenseCountContainer = document.getElementById('licenseCountContainer');
            const orderSummary = document.getElementById('orderSummary');
            const purchaseBtn = document.getElementById('purchaseBtn');
            
            if (!householdSelect) return;
            
            const pricePerLicense = <?php echo $currentPlan && $currentPlan['is_per_user'] ? $currentPlan['price_per_user'] : 0; ?>;
            
            // When household is selected
            householdSelect.addEventListener('change', () => {
                const selectedOption = householdSelect.options[householdSelect.selectedIndex];
                
                if (selectedOption.value) {
                    const availableLicenses = parseInt(selectedOption.dataset.availableLicenses);
                    const currentCapacity = parseInt(selectedOption.dataset.currentCapacity);
                    const currentMembers = parseInt(selectedOption.dataset.currentMembers);
                    
                    // Show license count selection
                    licenseCountContainer.style.display = 'block';
                    
                    // Update license count options
                    const twoLicensesOption = document.getElementById('twoLicensesOption');
                    if (availableLicenses < 2) {
                        twoLicensesOption.disabled = true;
                        twoLicensesOption.textContent = '2 Licenses (Not Available)';
                        licenseCountSelect.value = '1';
                    } else {
                        twoLicensesOption.disabled = false;
                        twoLicensesOption.textContent = '2 Licenses';
                    }
                    
                    // Update helper text
                    document.getElementById('licenseHelperText').textContent = 
                        `This household can add up to ${availableLicenses} more license${availableLicenses > 1 ? 's' : ''}`;
                    
                    // Show order summary
                    document.getElementById('selectedHouseholdName').textContent = selectedOption.text.split('(')[0].trim();
                    document.getElementById('currentCapacityDisplay').textContent = 
                        `${currentMembers}/${currentCapacity} members`;
                    
                    this.updateOrderSummary(currentCapacity, 1, pricePerLicense);
                    orderSummary.style.display = 'block';
                    purchaseBtn.disabled = false;
                } else {
                    licenseCountContainer.style.display = 'none';
                    orderSummary.style.display = 'none';
                    purchaseBtn.disabled = true;
                }
            });
            
            // When license count changes
            if (licenseCountSelect) {
                licenseCountSelect.addEventListener('change', () => {
                    const householdSelect = document.getElementById('household_id');
                    const selectedOption = householdSelect.options[householdSelect.selectedIndex];
                    
                    if (selectedOption.value) {
                        const currentCapacity = parseInt(selectedOption.dataset.currentCapacity);
                        const licenseCount = parseInt(licenseCountSelect.value);
                        this.updateOrderSummary(currentCapacity, licenseCount, pricePerLicense);
                    }
                });
            }
        },
        
        updateOrderSummary(currentCapacity, licenseCount, pricePerLicense) {
            const newCapacity = currentCapacity + licenseCount;
            const totalCost = pricePerLicense * licenseCount;
            
            document.getElementById('licenseCountDisplay').textContent = licenseCount;
            document.getElementById('newCapacityDisplay').textContent = `${newCapacity} members`;
            document.getElementById('totalCost').textContent = 
                '₦' + totalCost.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        },

        initializePaymentModal() {
            const modal = document.getElementById('makePaymentModal');
            if (!modal) return;

            // No need to manually handle modal, Bootstrap handles it
            // Just ensure button clicks pass through properly
        },

        openPaymentModal(planId, planName, planPrice) {
            document.getElementById('modal_plan_id').value = planId;
            document.getElementById('modal_plan_name').value = planName;
            document.getElementById('modal_amount').value = planPrice;

            // Use Bootstrap's modal
            const modalElement = document.getElementById('makePaymentModal');
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => PaymentSettings.init());
    } else {
        PaymentSettings.init();
    }
</script>

<?php include_once '../includes/footer.php'; ?>