<?php
/**
 * Gate Wey Access Management System
 * Payment Export
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/Payment.php';
require_once 'payment_constants.php';

// Check if user is logged in and is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
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

// Get database instance
$db = Database::getInstance();

// Check if required parameters are provided
if (!isset($_GET['year']) || !isset($_GET['month'])) {
    header('Location: ' . BASE_URL . 'payments/history.php');
    exit;
}

$year = (int)$_GET['year'];
$month = (int)$_GET['month'];
$clanId = isset($_GET['clan_id']) ? (int)$_GET['clan_id'] : null;

// Validate month and year
if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
    header('Location: ' . BASE_URL . 'payments/history.php');
    exit;
}

// Generate start and end dates for the selected month
$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate = date('Y-m-t', strtotime($startDate));

// Get month name for filename
$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));

// Build query for payments in the selected month
$query = "SELECT 
            p.id, 
            p.amount, 
            p.payment_method, 
            p.transaction_id, 
            p.status, 
            p.payment_date, 
            p.period_start, 
            p.period_end, 
            p.is_per_user,
            p.user_count,
            c.name as clan_name, 
            pp.name as plan_name,
            pp.price as plan_price,
            pp.price_per_user
          FROM payments p
          JOIN clans c ON p.clan_id = c.id
          JOIN pricing_plans pp ON p.pricing_plan_id = pp.id
          WHERE p.payment_date BETWEEN ? AND ?";
$params = [$startDate, $endDate];

// Add clan filter if provided
if ($clanId) {
    $query .= " AND p.clan_id = ?";
    $params[] = $clanId;
}

$query .= " ORDER BY p.payment_date DESC";

// Get payments
$payments = $db->fetchAll($query, $params);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="payments_' . $monthName . '_' . $year . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Write CSV header
fputcsv($output, [
    'Payment ID',
    'Clan',
    'Amount',
    'Plan',
    'Plan Type',
    'User Count',
    'Base Price',
    'Payment Method',
    'Transaction ID',
    'Status',
    'Payment Date',
    'Period Start',
    'Period End'
]);

// Write payment data rows
foreach ($payments as $payment) {
    $planType = $payment['is_per_user'] ? 'Per-User Plan' : 'Fixed Plan';
    $basePrice = $payment['is_per_user'] ? 
                CURRENCY_SYMBOL . number_format($payment['price_per_user'], 2) . ' per user' : 
                CURRENCY_SYMBOL . number_format($payment['plan_price'], 2);
    
    fputcsv($output, [
        $payment['id'],
        $payment['clan_name'],
        CURRENCY_SYMBOL . number_format($payment['amount'], 2),
        $payment['plan_name'],
        $planType,
        $payment['is_per_user'] ? $payment['user_count'] : 'N/A',
        $basePrice,
        ucfirst($payment['payment_method']),
        $payment['transaction_id'] ?: 'N/A',
        ucfirst($payment['status']),
        date('Y-m-d H:i:s', strtotime($payment['payment_date'])),
        $payment['period_start'] ? date('Y-m-d', strtotime($payment['period_start'])) : 'N/A',
        $payment['period_end'] ? date('Y-m-d', strtotime($payment['period_end'])) : 'N/A'
    ]);
}

// Close the output stream
fclose($output);
exit;
