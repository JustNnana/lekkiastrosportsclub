<?php
/**
 * Gate Wey Access Management System
 * Payment History Page - Enhanced with Dasher UI and Top Performing Clans
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/Payment.php';
require_once 'payment_constants.php';

// Set page title
$pageTitle = 'Payment History';
$includeCharts = true;

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
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

// Check if user has appropriate permissions
// Super admin can see all clans' payment history
// Clan admin can only see their clan's payment history
// Regular users cannot access this page
if (!($currentUser->isSuperAdmin() || $currentUser->isClanAdmin())) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Get database instance
$db = Database::getInstance();

// Get clan ID based on user role
$clanId = null;

if ($currentUser->isClanAdmin()) {
    $clanId = $currentUser->getClanId();
} elseif ($currentUser->isSuperAdmin() && isset($_GET['clan_id'])) {
    $clanId = decryptId($_GET['clan_id']);
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get filters
$filters = [];

if (isset($_GET['year']) && !empty($_GET['year'])) {
    $filters['year'] = (int)$_GET['year'];
}

if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'completed', 'failed', 'refunded'])) {
    $filters['status'] = $_GET['status'];
}

// If clan ID is set, add it to filters
if ($clanId) {
    $filters['clan_id'] = $clanId;
}

// Set default date range for top performing clans (last 30 days) - or get from filters
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));

// If year filter is set, use that for the date range
if (isset($filters['year'])) {
    $startDate = $filters['year'] . '-01-01';
    $endDate = $filters['year'] . '-12-31';
}

// Initialize variables for top performing clans
$showActions = true;
$totalRevenue = 0;
$topClans = [];

// === TOP PERFORMING CLANS QUERY ===
// Top 5 clans by payment amount (super admin only, or current clan if clan admin)
if ($currentUser->isSuperAdmin() && !isset($clanId)) {
    // Updated query to include admin information and proper aggregation
    $topClansQuery = "SELECT 
        c.id, 
        c.name, 
        SUM(p.amount) as total_amount, 
        COUNT(p.id) as payment_count,
        u.full_name as admin_name,
        AVG(CASE WHEN p.status = 'completed' THEN p.amount ELSE NULL END) as avg_payment,
        COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN p.status = 'failed' THEN 1 END) as failed_count,
        COUNT(CASE WHEN p.status = 'refunded' THEN 1 END) as refunded_count,
        MIN(CASE WHEN p.status = 'completed' THEN p.amount ELSE NULL END) as min_payment,
        MAX(CASE WHEN p.status = 'completed' THEN p.amount ELSE NULL END) as max_payment
    FROM payments p
    JOIN clans c ON p.clan_id = c.id
    LEFT JOIN users u ON c.admin_id = u.id
    WHERE p.status = 'completed' 
    AND DATE(p.payment_date) BETWEEN ? AND ?";
    
    $topClansParams = [$startDate, $endDate];
    
    // Apply additional filters if set
    if (isset($filters['year'])) {
        $topClansQuery .= " AND YEAR(p.payment_date) = ?";
        $topClansParams[] = $filters['year'];
    }
    
    $topClansQuery .= " GROUP BY c.id, c.name, u.full_name
    ORDER BY total_amount DESC
    LIMIT 5";
    
    $topClans = $db->fetchAll($topClansQuery, $topClansParams);
    
    // Ensure all array keys have default values
    foreach ($topClans as &$clan) {
        $clan['admin_name'] = $clan['admin_name'] ?? 'No admin assigned';
        $clan['avg_payment'] = $clan['avg_payment'] ?? 0;
        $clan['completed_count'] = $clan['completed_count'] ?? 0;
        $clan['pending_count'] = $clan['pending_count'] ?? 0;
        $clan['failed_count'] = $clan['failed_count'] ?? 0;
        $clan['refunded_count'] = $clan['refunded_count'] ?? 0;
        $clan['min_payment'] = $clan['min_payment'] ?? 0;
        $clan['max_payment'] = $clan['max_payment'] ?? 0;
        $clan['total_amount'] = $clan['total_amount'] ?? 0;
        $clan['payment_count'] = $clan['payment_count'] ?? 0;
    }
    unset($clan); // Break the reference
} elseif ($currentUser->isClanAdmin() && $clanId) {
    // For clan admin, show their clan's performance data
    $topClansQuery = "SELECT 
        c.id, 
        c.name, 
        SUM(p.amount) as total_amount, 
        COUNT(p.id) as payment_count,
        u.full_name as admin_name,
        AVG(CASE WHEN p.status = 'completed' THEN p.amount ELSE NULL END) as avg_payment,
        COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN p.status = 'failed' THEN 1 END) as failed_count,
        COUNT(CASE WHEN p.status = 'refunded' THEN 1 END) as refunded_count,
        MIN(CASE WHEN p.status = 'completed' THEN p.amount ELSE NULL END) as min_payment,
        MAX(CASE WHEN p.status = 'completed' THEN p.amount ELSE NULL END) as max_payment
    FROM payments p
    JOIN clans c ON p.clan_id = c.id
    LEFT JOIN users u ON c.admin_id = u.id
    WHERE p.clan_id = ? 
    AND DATE(p.payment_date) BETWEEN ? AND ?";
    
    $topClansParams = [$clanId, $startDate, $endDate];
    
    // Apply additional filters if set
    if (isset($filters['year'])) {
        $topClansQuery .= " AND YEAR(p.payment_date) = ?";
        $topClansParams[] = $filters['year'];
    }
    
    $topClansQuery .= " GROUP BY c.id, c.name, u.full_name";
    
    $topClansResult = $db->fetchOne($topClansQuery, $topClansParams);
    
    if ($topClansResult) {
        $topClansResult['admin_name'] = $topClansResult['admin_name'] ?? 'No admin assigned';
        $topClansResult['avg_payment'] = $topClansResult['avg_payment'] ?? 0;
        $topClansResult['completed_count'] = $topClansResult['completed_count'] ?? 0;
        $topClansResult['pending_count'] = $topClansResult['pending_count'] ?? 0;
        $topClansResult['failed_count'] = $topClansResult['failed_count'] ?? 0;
        $topClansResult['refunded_count'] = $topClansResult['refunded_count'] ?? 0;
        $topClansResult['min_payment'] = $topClansResult['min_payment'] ?? 0;
        $topClansResult['max_payment'] = $topClansResult['max_payment'] ?? 0;
        $topClansResult['total_amount'] = $topClansResult['total_amount'] ?? 0;
        $topClansResult['payment_count'] = $topClansResult['payment_count'] ?? 0;
        
        $topClans = [$topClansResult];
    }
}

// Calculate total revenue for percentage calculations
if (!empty($topClans)) {
    $revenueQuery = "SELECT SUM(amount) as total FROM payments 
                     WHERE status = 'completed' 
                     AND DATE(payment_date) BETWEEN ? AND ?";
    $revenueParams = [$startDate, $endDate];
    
    if (isset($filters['year'])) {
        $revenueQuery .= " AND YEAR(payment_date) = ?";
        $revenueParams[] = $filters['year'];
    }
    
    if ($clanId && $currentUser->isClanAdmin()) {
        $revenueQuery .= " AND clan_id = ?";
        $revenueParams[] = $clanId;
    }
    
    $revenueResult = $db->fetchOne($revenueQuery, $revenueParams);
    $totalRevenue = $revenueResult['total'] ?? 0;
}

// Build query for payments with monthly grouping
$query = "SELECT 
            YEAR(p.payment_date) as year, 
            MONTH(p.payment_date) as month, 
            COUNT(*) as payment_count,
            SUM(p.amount) as total_amount,
            SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as completed_amount,
            SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN p.status = 'failed' THEN p.amount ELSE 0 END) as failed_amount,
            SUM(CASE WHEN p.status = 'refunded' THEN p.amount ELSE 0 END) as refunded_amount,
            COUNT(CASE WHEN p.status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN p.status = 'failed' THEN 1 END) as failed_count,
            COUNT(CASE WHEN p.status = 'refunded' THEN 1 END) as refunded_count,
            SUM(CASE WHEN p.is_per_user = 1 THEN 1 ELSE 0 END) as per_user_count
          FROM payments p
          WHERE 1=1";
$params = [];

// Apply filters to query
if (isset($filters['clan_id'])) {
    $query .= " AND p.clan_id = ?";
    $params[] = $filters['clan_id'];
}

if (isset($filters['year'])) {
    $query .= " AND YEAR(p.payment_date) = ?";
    $params[] = $filters['year'];
}

if (isset($filters['status'])) {
    $query .= " AND p.status = ?";
    $params[] = $filters['status'];
}

// Group by year and month, order by most recent first
$query .= " GROUP BY YEAR(p.payment_date), MONTH(p.payment_date)
            ORDER BY YEAR(p.payment_date) DESC, MONTH(p.payment_date) DESC
            LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Get monthly payment summaries
$monthlySummaries = $db->fetchAll($query, $params);

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as count FROM 
               (SELECT YEAR(p.payment_date) as yr, MONTH(p.payment_date) as mth 
                FROM payments p 
                WHERE 1=1";
$countParams = [];

if (isset($filters['clan_id'])) {
    $countQuery .= " AND p.clan_id = ?";
    $countParams[] = $filters['clan_id'];
}

if (isset($filters['year'])) {
    $countQuery .= " AND YEAR(p.payment_date) = ?";
    $countParams[] = $filters['year'];
}

if (isset($filters['status'])) {
    $countQuery .= " AND p.status = ?";
    $countParams[] = $filters['status'];
}

$countQuery .= " GROUP BY yr, mth) as counts";

$totalCount = $db->fetchOne($countQuery, $countParams)['count'] ?? 0;
$totalPages = ceil($totalCount / $limit);

// Get available years for filter
$years = $db->fetchAll(
    "SELECT DISTINCT YEAR(payment_date) as year FROM payments" . 
    (isset($filters['clan_id']) ? " WHERE clan_id = " . $filters['clan_id'] : "") . 
    " ORDER BY year DESC"
);

// Get yearly payment summaries for main chart 
$yearlyQuery = "SELECT 
                 YEAR(p.payment_date) as year,
                 SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as completed_amount,
                 SUM(CASE WHEN p.status = 'pending' THEN p.amount ELSE 0 END) as pending_amount,
                 SUM(CASE WHEN p.status = 'failed' THEN p.amount ELSE 0 END) as failed_amount,
                 SUM(CASE WHEN p.status = 'refunded' THEN p.amount ELSE 0 END) as refunded_amount
               FROM payments p
               WHERE 1=1";
$yearlyParams = [];

if (isset($filters['clan_id'])) {
    $yearlyQuery .= " AND p.clan_id = ?";
    $yearlyParams[] = $filters['clan_id'];
}

$yearlyQuery .= " GROUP BY YEAR(p.payment_date) ORDER BY year ASC";
$yearlyData = $db->fetchAll($yearlyQuery, $yearlyParams);

// Get monthly payment summaries for detailed trends chart
$monthlyTrendQuery = "SELECT 
                 YEAR(p.payment_date) as year,
                 MONTH(p.payment_date) as month,
                 SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as completed_amount
               FROM payments p
               WHERE 1=1";
$monthlyTrendParams = [];

if (isset($filters['clan_id'])) {
    $monthlyTrendQuery .= " AND p.clan_id = ?";
    $monthlyTrendParams[] = $filters['clan_id'];
}

// Get last 12 months if no year filter
if (isset($filters['year'])) {
    $monthlyTrendQuery .= " AND YEAR(p.payment_date) = ?";
    $monthlyTrendParams[] = $filters['year'];
} else {
    $monthlyTrendQuery .= " AND p.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)";
}

$monthlyTrendQuery .= " GROUP BY YEAR(p.payment_date), MONTH(p.payment_date) ORDER BY year ASC, month ASC";
$monthlyTrendData = $db->fetchAll($monthlyTrendQuery, $monthlyTrendParams);

// Get payment method distribution (Pie chart)
$methodQuery = "SELECT 
                 payment_method, 
                 COUNT(*) as count,
                 SUM(amount) as total_amount
               FROM payments p
               WHERE status = 'completed'";
$methodParams = [];

if (isset($filters['clan_id'])) {
    $methodQuery .= " AND p.clan_id = ?";
    $methodParams[] = $filters['clan_id'];
}

if (isset($filters['year'])) {
    $methodQuery .= " AND YEAR(p.payment_date) = ?";
    $methodParams[] = $filters['year'];
}

$methodQuery .= " GROUP BY payment_method ORDER BY count DESC";
$paymentMethodData = $db->fetchAll($methodQuery, $methodParams);

// Get clan details if clan_id is set
$clanDetails = null;
if ($clanId) {
    $clan = new Clan();
    if ($clan->loadById($clanId)) {
        $clanDetails = [
            'id' => $clan->getId(),
            'name' => $clan->getName(),
            'payment_status' => $clan->getPaymentStatus(),
            'next_payment_date' => $clan->getNextPaymentDate()
        ];
        
        // Get more detailed payment stats for this clan
        $clanPaymentStats = $db->fetchOne(
            "SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_paid,
                AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_payment,
                MAX(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as max_payment,
                MIN(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as min_payment
            FROM payments 
            WHERE clan_id = ?",
            [$clanId]
        );
        
        if ($clanPaymentStats) {
            $clanDetails = array_merge($clanDetails, $clanPaymentStats);
        }
    }
}

// Get clans for filter (for super admin only)
$clans = [];
if ($currentUser->isSuperAdmin()) {
    $clans = $db->fetchAll("SELECT id, name FROM clans ORDER BY name");
}

// Include header
include_once '../includes/header.php';

// Include sidebar
include_once '../includes/sidebar.php';
?>

<!-- Enhanced Dasher UI Styles with Top Performing Clans -->
<style>
/* Filter Styling */
.filter-form {
    width: 100%;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-4);
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-2);
}

.filter-label {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    color: var(--text-primary);
}

.filter-select {
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: var(--spacing-2) var(--spacing-3);
    color: var(--text-primary);
    font-size: var(--font-size-sm);
    transition: var(--theme-transition);
}

.filter-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
    outline: none;
}

/* Month indicator */
.month-indicator {
    width: 32px;
    height: 32px;
    border-radius: var(--border-radius);
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
    margin-right: var(--spacing-3);
    flex-shrink: 0;
}

/* Top Performing Clans Enhanced Styles */
.top-performing-clans-container {
    margin-bottom: var(--spacing-6);
}

.rank-indicator {
    width: 32px;
    height: 32px;
    border-radius: var(--border-radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: var(--font-weight-bold);
    font-size: var(--font-size-sm);
}

.rank-1 {
    background: linear-gradient(135deg, #ffd700, #ffed4e);
    color: #8b7500;
}

.rank-2 {
    background: linear-gradient(135deg, #c0c0c0, #e5e5e5);
    color: #666;
}

.rank-3 {
    background: linear-gradient(135deg, #cd7f32, #daa520);
    color: #fff;
}

.rank-indicator:not(.rank-1):not(.rank-2):not(.rank-3) {
    background: var(--bg-subtle);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.clan-avatar {
    width: 40px;
    height: 40px;
    border-radius: var(--border-radius);
    background: linear-gradient(135deg, var(--primary), var(--primary-600));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: var(--font-weight-bold);
    font-size: var(--font-size-sm);
    margin-right: var(--spacing-3);
    flex-shrink: 0;
}

.clan-info {
    flex: 1;
    min-width: 0;
}

.clan-name {
    font-weight: var(--font-weight-semibold);
    margin-bottom: var(--spacing-1);
}

.clan-link {
    color: var(--text-primary);
    text-decoration: none;
    transition: var(--theme-transition);
}

.clan-link:hover {
    color: var(--primary);
    text-decoration: none;
}

.clan-admin {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    display: flex;
    align-items: center;
    gap: var(--spacing-1);
}

.clan-admin i {
    font-size: var(--font-size-xs);
}

.revenue-display {
    text-align: left;
}

.revenue-amount {
    font-weight: var(--font-weight-bold);
    color: var(--primary);
    font-size: var(--font-size-lg);
    display: block;
}

.revenue-trend {
    font-size: var(--font-size-sm);
    margin-top: var(--spacing-1);
    display: flex;
    align-items: center;
    gap: var(--spacing-1);
}

.transaction-stats {
    text-align: left;
}

.transaction-count {
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
    font-size: var(--font-size-lg);
    display: block;
}

.transaction-breakdown {
    font-size: var(--font-size-sm);
    margin-top: var(--spacing-1);
    display: flex;
    flex-direction: column;
    gap: var(--spacing-05);
}

.average-payment {
    text-align: left;
}

.avg-amount {
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
    display: block;
}

.avg-range {
    margin-top: var(--spacing-1);
    font-size: var(--font-size-xs);
}

.revenue-share {
    min-width: 120px;
}

.share-visual {
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
}

.progress-mini-enhanced {
    flex: 1;
    height: 6px;
    background: var(--bg-subtle);
    border-radius: var(--border-radius-full);
    overflow: hidden;
}

.progress-mini-enhanced .progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-600));
    border-radius: var(--border-radius-full);
    transition: width 0.6s ease;
}

.share-percentage {
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
    min-width: 40px;
    text-align: right;
}

.success-rate {
    min-width: 80px;
}

.rate-indicator {
    text-align: center;
}

.rate-value {
    font-weight: var(--font-weight-semibold);
    display: block;
    margin-bottom: var(--spacing-1);
}

.rate-indicator.excellent .rate-value {
    color: var(--success);
}

.rate-indicator.good .rate-value {
    color: var(--primary);
}

.rate-indicator.average .rate-value {
    color: var(--warning);
}

.rate-bar {
    width: 100%;
    height: 4px;
    background: var(--bg-subtle);
    border-radius: var(--border-radius-full);
    overflow: hidden;
}

.rate-fill {
    height: 100%;
    border-radius: var(--border-radius-full);
    transition: width 0.6s ease;
}

.rate-indicator.excellent .rate-fill {
    background: var(--success);
}

.rate-indicator.good .rate-fill {
    background: var(--primary);
}

.rate-indicator.average .rate-fill {
    background: var(--warning);
}

.action-buttons {
    display: flex;
    gap: var(--spacing-2);
    align-items: center;
}

.clan-row {
    transition: var(--theme-transition);
}

.clan-row:hover {
    background: var(--bg-hover);
}

.empty-state-mini {
    text-align: center;
    padding: var(--spacing-6) var(--spacing-4);
}

/* Payment Cards Grid */
.payment-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--spacing-4);
}

.payment-summary-card {
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
    transition: var(--theme-transition);
}

.payment-summary-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.payment-card-header {
    padding: var(--spacing-4);
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: between;
    align-items: center;
}

.payment-card-title {
    margin: 0;
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
    flex: 1;
}

.payment-card-amount {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
    color: var(--primary);
}

.payment-card-body {
    padding: var(--spacing-4);
}

.payment-summary-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--spacing-3);
    margin-bottom: var(--spacing-4);
}

.stat-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
}

.stat-icon {
    width: 24px;
    height: 24px;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-sm);
    flex-shrink: 0;
}

.stat-icon.success {
    background: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.stat-icon.warning {
    background: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.stat-icon.danger {
    background: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.stat-content {
    flex: 1;
    min-width: 0;
}

.stat-value {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-semibold);
    color: var(--text-primary);
    line-height: 1;
}

.stat-label {
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
    margin-top: var(--spacing-1);
}

.payment-card-note {
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
    padding: var(--spacing-2) var(--spacing-3);
    background: rgba(var(--info-rgb), 0.1);
    border: 1px solid rgba(var(--info-rgb), 0.2);
    border-radius: var(--border-radius);
    color: var(--info);
    font-size: var(--font-size-sm);
}

.payment-card-footer {
    padding: var(--spacing-3) var(--spacing-4);
    background: var(--bg-subtle);
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: var(--spacing-2);
    justify-content: space-between;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: var(--spacing-8) var(--spacing-4);
    color: var(--text-muted);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: var(--spacing-4);
    opacity: 0.5;
}

.empty-state h3 {
    margin-bottom: var(--spacing-2);
    color: var(--text-secondary);
}

.empty-state p {
    margin: 0;
    font-size: var(--font-size-sm);
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .chart-grid {
        display: flex !important;
        flex-wrap: nowrap !important;
        overflow-x: auto !important;
        gap: 0.75rem !important;
        padding-bottom: 0.5rem !important;
    }
    
    .improved-stats-card {
        flex: 0 0 auto !important;
        min-width: 200px !important;
    }
    .filter-grid {
        grid-template-columns: 1fr;
        gap: var(--spacing-3);
    }
    
    .payment-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .payment-summary-stats {
        grid-template-columns: 1fr;
    }
    
    .payment-card-footer {
        flex-direction: column;
    }
    
    .charts-column-layout {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-4);
    }
    
    .clan-info {
        min-width: 120px;
    }
    
    .revenue-display,
    .transaction-stats,
    .average-payment {
        text-align: center;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: var(--spacing-1);
    }
    
    .action-buttons .btn {
        font-size: var(--font-size-xs);
        padding: var(--spacing-1) var(--spacing-2);
    }
}

@media (max-width: 576px) {
    .chart-header .chart-controls {
        margin-top: var(--spacing-2);
    }
    
    .btn-group .btn {
        font-size: var(--font-size-xs);
        padding: var(--spacing-1) var(--spacing-2);
    }
    
    .top-performing-clans-container .table-responsive {
        font-size: var(--font-size-sm);
    }
    
    .clan-avatar {
        width: 32px;
        height: 32px;
        font-size: var(--font-size-xs);
    }
    
    .revenue-amount {
        font-size: var(--font-size-base);
    }
    
    .transaction-count {
        font-size: var(--font-size-base);
    }
}
</style>
<!-- Dasher UI Content Area -->
<div class="content">
    <!-- Content Header -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="content-title">Payment History</h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>payments/" class="breadcrumb-link">Payments</a>
                        </li>
                        <li class="breadcrumb-item active">History</li>
                    </ol>
                </nav>
            </div>
            <div class="content-actions">
                <?php if ($currentUser->isClanAdmin() || ($currentUser->isSuperAdmin() && $clanId)): ?>
                    <a href="<?php echo BASE_URL; ?>payments/process.php<?php echo $clanId ? '?clan_id=' . $clanId : ''; ?>" class="btn btn-primary">
                        <i class="fas fa-credit-card"></i>
                        <span>Make Payment</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
        
    <!-- Enhanced Statistics Cards -->
    <div class="chart-grid">
        <?php if ($clanDetails): ?>
            <!-- Clan-specific Stats -->
            <div class="improved-stats-card">
                <div class="improved-stats-header">
                    <div class="improved-stats-icon info">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <div class="improved-stats-content">
                        <h3 class="improved-stats-title">Clan Status</h3>
                        <p class="improved-stats-value">
                            <?php echo htmlspecialchars($clanDetails['name']); ?>
                        </p>
                    </div>
                </div>
                <div class="improved-stats-change">
                    <div class="table-status">
                        <?php if ($clanDetails['payment_status'] === 'active'): ?>
                            <div class="table-status-dot status-active"></div>
                            <span style="color: var(--success);">Active</span>
                        <?php elseif ($clanDetails['payment_status'] === 'free'): ?>
                            <div class="table-status-dot" style="background-color: var(--info);"></div>
                            <span style="color: var(--info);">Free</span>
                        <?php else: ?>
                            <div class="table-status-dot status-inactive"></div>
                            <span style="color: var(--danger);">Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="improved-stats-card">
                <div class="improved-stats-header">
                    <div class="improved-stats-icon success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="improved-stats-content">
                        <h3 class="improved-stats-title">Total Paid</h3>
                        <p class="improved-stats-value">
                            <i class="fas fa-naira-sign"></i><?php echo number_format($clanDetails['total_paid'] ?? 0, 2); ?>
                        </p>
                    </div>
                </div>
                <div class="improved-stats-change positive">
                    <span><?php echo $clanDetails['completed_payments'] ?? 0; ?> completed payments</span>
                </div>
            </div>

            <div class="improved-stats-card">
                <div class="improved-stats-header">
                    <div class="improved-stats-icon warning">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="improved-stats-content">
                        <h3 class="improved-stats-title">Total Payments</h3>
                        <p class="improved-stats-value"><?php echo $clanDetails['total_payments'] ?? 0; ?></p>
                    </div>
                </div>
                <div class="improved-stats-change">
                    <span><?php echo ($clanDetails['pending_payments'] ?? 0); ?> pending</span>
                </div>
            </div>

            <?php if ($clanDetails['payment_status'] === 'active' && $clanDetails['next_payment_date']): ?>
                <div class="improved-stats-card">
                    <div class="improved-stats-header">
                        <div class="improved-stats-icon primary">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="improved-stats-content">
                            <h3 class="improved-stats-title">Next Payment</h3>
                            <p class="improved-stats-value">
                                <?php echo date('M j', strtotime($clanDetails['next_payment_date'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="improved-stats-change">
                        <?php 
                        $nextPaymentDate = strtotime($clanDetails['next_payment_date']);
                        $now = time();
                        $daysLeft = ceil(($nextPaymentDate - $now) / 86400);
                        
                        if ($daysLeft > 0 && $daysLeft <= 7): ?>
                            <span style="color: var(--warning);"><?php echo $daysLeft; ?> days left</span>
                        <?php elseif ($daysLeft <= 0): ?>
                            <span style="color: var(--danger);">Overdue</span>
                        <?php else: ?>
                            <span style="color: var(--success);"><?php echo $daysLeft; ?> days</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- System-wide Stats Cards -->
            <?php 
                $overallStats = $db->fetchOne(
                    "SELECT 
                        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                        COUNT(*) as total_payments,
                        COUNT(DISTINCT clan_id) as total_paying_clans,
                        AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_payment
                    FROM payments"
                );
            
                $currentMonthStats = $db->fetchOne(
                    "SELECT 
                        SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as month_revenue,
                        COUNT(*) as month_payments
                    FROM payments 
                    WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())"
                );
            ?>
            <div class="improved-stats-card">
                <div class="improved-stats-header">
                    <div class="improved-stats-icon success">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="improved-stats-content">
                        <h3 class="improved-stats-title">Total Revenue</h3>
                        <p class="improved-stats-value">
                            <i class="fas fa-naira-sign"></i><?php echo number_format($overallStats['total_revenue'] ?? 0, 2); ?>
                        </p>
                    </div>
                </div>
                <div class="improved-stats-change positive">
                    <span>All time completed payments</span>
                </div>
            </div>

            <div class="improved-stats-card">
                <div class="improved-stats-header">
                    <div class="improved-stats-icon primary">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="improved-stats-content">
                        <h3 class="improved-stats-title">This Month</h3>
                        <p class="improved-stats-value">
                            <i class="fas fa-naira-sign"></i><?php echo number_format($currentMonthStats['month_revenue'] ?? 0, 2); ?>
                        </p>
                    </div>
                </div>
                <div class="improved-stats-change">
                    <span><?php echo $currentMonthStats['month_payments'] ?? 0; ?> payments</span>
                </div>
            </div>

            <div class="improved-stats-card">
                <div class="improved-stats-header">
                    <div class="improved-stats-icon info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="improved-stats-content">
                        <h3 class="improved-stats-title">Paying Clans</h3>
                        <p class="improved-stats-value"><?php echo $overallStats['total_paying_clans'] ?? 0; ?></p>
                    </div>
                </div>
                <div class="improved-stats-change">
                    <span>Clans with payments</span>
                </div>
            </div>

            <div class="improved-stats-card">
                <div class="improved-stats-header">
                    <div class="improved-stats-icon warning">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="improved-stats-content">
                        <h3 class="improved-stats-title">Avg Payment</h3>
                        <p class="improved-stats-value">
                            <i class="fas fa-naira-sign"></i><?php echo number_format($overallStats['avg_payment'] ?? 0, 2); ?>
                        </p>
                    </div>
                </div>
                <div class="improved-stats-change">
                    <span>Average amount</span>
                </div>
            </div>
        <?php endif; ?>
    </div>
        
    <!-- Enhanced Filters -->
    <div class="table-container">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="card-title">Filter Payments</h2>
            </div>
        </div>
        <div class="card-body">
            <form action="" method="get" class="filter-form">
                <div class="filter-grid">
                    <?php if ($currentUser->isSuperAdmin() && !empty($clans)): ?>
                        <div class="filter-group">
                            <label for="clan_id" class="filter-label">Clan</label>
                            <select class="filter-select" id="clan_id" name="clan_id">
                                <option value="">All Clans</option>
                                <?php foreach ($clans as $clan): ?>
                                    <option value="<?php echo $clan['id']; ?>" <?php echo (isset($_GET['clan_id']) && $_GET['clan_id'] == $clan['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($clan['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <label for="year" class="filter-label">Year</label>
                        <select class="filter-select" id="year" name="year">
                            <option value="">All Years</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo $year['year']; ?>" <?php echo (isset($_GET['year']) && $_GET['year'] == $year['year']) ? 'selected' : ''; ?>>
                                    <?php echo $year['year']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="status" class="filter-label">Status</label>
                        <select class="filter-select" id="status" name="status" >
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'failed') ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo (isset($_GET['status']) && $_GET['status'] === 'refunded') ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        <span>Apply Filters</span>
                    </button>
                        <a href="<?php echo BASE_URL; ?>payments/history.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i>
                        <span>Reset</span>
                    </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
        
    <!-- Charts Column Layout - Matching Super Admin Dashboard -->
    <div class="charts-column-layout">
        <!-- Revenue Overview Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <div>
                    <h2 class="chart-title">Revenue Overview</h2>
                    <p class="chart-subtitle">Annual revenue breakdown by payment status</p>
                </div>
                <div class="btn-group btn-group-sm" role="group" id="chartTypeToggle">
                    <button type="button" class="btn btn-outline-primary active" data-chart-type="bar">Bar</button>
                    <button type="button" class="btn btn-outline-primary" data-chart-type="line">Line</button>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="yearlyRevenueChart" class="chart-canvas"></canvas>
            </div>
        </div>
        
        <!-- Payment Method Distribution -->
        <div class="chart-container">
            <div class="chart-header">
                <div>
                    <h2 class="chart-title">Payment Methods</h2>
                    <p class="chart-subtitle">Distribution breakdown</p>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="paymentMethodChart" class="chart-canvas"></canvas>
            </div>
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: var(--primary);"></div>
                    <span>Primary Method</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: var(--success);"></div>
                    <span>Secondary</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: var(--warning);"></div>
                    <span>Others</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Performing Clans Section -->
    <?php if (!empty($topClans) || $currentUser->isSuperAdmin()): ?>
        <div class="table-container top-performing-clans-container">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="card-title">
                        <i class="fas fa-trophy me-2"></i>
                        <?php echo $currentUser->isClanAdmin() ? 'Clan Performance' : 'Top Performing Clans'; ?>
                    </h2>
                    <div class="d-flex align-items-center gap-2">
                        <small class="text-muted">
                            Period: <?php echo date('M d', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                            <?php if (isset($filters['year'])): ?>
                                (Year: <?php echo $filters['year']; ?>)
                            <?php endif; ?>
                        </small>
                        <?php if ($showActions && $currentUser->isSuperAdmin()): ?>
                            <button class="btn btn-outline-secondary btn-sm" onclick="exportTopClans()">
                                <i class="fas fa-download"></i>
                                <span>Export</span>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sortable" id="topPerformingClansTable">
                    <thead>
                        <tr>
                            <?php if ($currentUser->isSuperAdmin()): ?>
                                <th>
                                    <i class="fas fa-hashtag me-1"></i>
                                    Rank
                                </th>
                            <?php endif; ?>
                            <th>
                                <i class="fas fa-sitemap me-1"></i>
                                Clan
                            </th>
                            <th>
                                <i class="fas fa-money-bill-wave me-1"></i>
                                Total Revenue
                            </th>
                            <th>
                                <i class="fas fa-receipt me-1"></i>
                                Transactions
                            </th>
                            <th>
                                <i class="fas fa-calculator me-1"></i>
                                Average
                            </th>
                            <!-- <?php if ($currentUser->isSuperAdmin() && !$clanId): ?>
                                <th>
                                    <i class="fas fa-chart-pie me-1"></i>
                                    Revenue Share
                                </th>
                            <?php endif; ?> -->
                            <th>
                                <i class="fas fa-check-circle me-1"></i>
                                Success Rate
                            </th>
                            <?php if ($showActions): ?>
                                <th class="text-end">
                                    <i class="fas fa-cog me-1"></i>
                                    Actions
                                </th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topClans)): ?>
                            <tr>
                                <td colspan="<?php echo $showActions ? ($currentUser->isSuperAdmin() && !$clanId ? '8' : '7') : ($currentUser->isSuperAdmin() && !$clanId ? '7' : '6'); ?>" class="text-center py-4">
                                    <div class="empty-state-mini">
                                        <i class="fas fa-trophy" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5; color: var(--text-muted);"></i>
                                        <p style="color: var(--text-muted); margin: 0;">No clan performance data available for the selected period</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($topClans as $index => $clan): ?>
                                <tr class="clan-row" data-clan-id="<?php echo $clan['id']; ?>">
                                    <?php if ($currentUser->isSuperAdmin()): ?>
                                        <td>
                                            <div class="rank-indicator rank-<?php echo $index + 1; ?>">
                                                <?php if ($index === 0 && count($topClans) > 1): ?>
                                                    <i class="fas fa-crown"></i>
                                                <?php elseif ($index === 1): ?>
                                                    <i class="fas fa-medal"></i>
                                                <?php elseif ($index === 2): ?>
                                                    <i class="fas fa-award"></i>
                                                <?php else: ?>
                                                    <span><?php echo $index + 1; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <!-- <div class="clan-avatar">
                                                <?php echo strtoupper(substr($clan['name'], 0, 2)); ?>
                                            </div> -->
                                            <div class="clan-info">
                                                <div class="clan-name">
                                                    <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($clan['id']); ?>"
                                                       class="clan-link">
                                                        <?php echo htmlspecialchars($clan['name']); ?>
                                                    </a>
                                                </div>
                                                <div class="clan-admin">
                                                    <?php if ($clan['admin_name']): ?>
                                                        <i class="fas fa-user-tie"></i>
                                                        <span><?php echo htmlspecialchars($clan['admin_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">No admin assigned</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="revenue-display">
                                            <span class="revenue-amount">₦<?php echo number_format($clan['total_amount'], 2); ?></span>
                                            <div class="revenue-trend">
                                                <?php if ($clan['total_amount'] > $clan['avg_payment'] * $clan['payment_count'] * 0.8): ?>
                                                    <i class="fas fa-arrow-up text-success"></i>
                                                    <span class="text-success">Strong</span>
                                                <?php else: ?>
                                                    <i class="fas fa-arrow-right text-warning"></i>
                                                    <span class="text-warning">Stable</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="transaction-stats">
                                            <span class="transaction-count"><?php echo $clan['payment_count']; ?></span>
                                            <div class="transaction-breakdown">
                                                <span class="text-success"><?php echo $clan['completed_count']; ?> completed</span>
                                                <?php if ($clan['pending_count'] > 0): ?>
                                                    <span class="text-warning"><?php echo $clan['pending_count']; ?> pending</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div class="average-payment">
                                            <span class="avg-amount">₦<?php echo number_format($clan['payment_count'] > 0 ? $clan['total_amount'] / $clan['payment_count'] : 0, 2); ?></span>
                                            <div class="avg-range">
                                                <small class="text-muted">
                                                    ₦<?php echo number_format($clan['min_payment'], 2); ?> - ₦<?php echo number_format($clan['max_payment'], 2); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- <?php if ($currentUser->isSuperAdmin() && !$clanId): ?>
                                        <td>
                                            <div class="revenue-share">
                                                <div class="share-visual">
                                                    <div class="progress-mini-enhanced">
                                                        <div class="progress-bar" 
                                                             style="width: <?php echo $totalRevenue > 0 ? ($clan['total_amount'] / $totalRevenue * 100) : 0; ?>%"></div>
                                                    </div>
                                                    <span class="share-percentage">
                                                        <?php echo number_format($totalRevenue > 0 ? ($clan['total_amount'] / $totalRevenue * 100) : 0, 1); ?>%
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                    <?php endif; ?> -->
                                    
                                    <td>
                                        <div class="success-rate">
                                            <?php $successRate = $clan['payment_count'] > 0 ? ($clan['completed_count'] / $clan['payment_count']) * 100 : 0; ?>
                                            <div class="rate-indicator <?php echo $successRate >= 90 ? 'excellent' : ($successRate >= 75 ? 'good' : 'average'); ?>">
                                                <span class="rate-value"><?php echo number_format($successRate, 1); ?>%</span>
                                                <div class="rate-bar">
                                                    <div class="rate-fill" style="width: <?php echo $successRate; ?>%"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <?php if ($showActions): ?>
                                        <td class="text-end">
                                            <div class="action-buttons">
                                                <!-- <?php if ($currentUser->isSuperAdmin()): ?>
                                                    <a href="<?php echo BASE_URL; ?>payments/analytics.php?clan_id=<?php echo $clan['id']; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       title="View detailed analytics">
                                                        <i class="fas fa-chart-line"></i>
                                                        <span>Analytics</span>
                                                    </a>
                                                <?php endif; ?> -->
                                                
                                                <div class="dropdown d-inline-block">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($clan['id']); ?>">
                                                                <i class="fas fa-eye"></i> View Clan
                                                            </a>
                                                        </li>
                                                        <?php if ($currentUser->isSuperAdmin()): ?>
                                                            <!-- <li>
                                                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>payments/settings.php?clan_id=<?php echo $clan['id']; ?>">
                                                                    <i class="fas fa-credit-card"></i> Payment Settings
                                                                </a>
                                                            </li> -->
                                                            <li>
                                                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>users/index.php?clan_id=<?php echo $clan['id']; ?>">
                                                                    <i class="fas fa-users"></i> Members
                                                                </a>
                                                            </li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($topClans)): ?>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <?php if ($currentUser->isClanAdmin()): ?>
                                Showing clan performance data
                            <?php else: ?>
                                Showing top <?php echo count($topClans); ?> performing clans
                            <?php endif; ?>
                        </small>
                        <div class="total-summary">
                            <strong>Total Combined Revenue: ₦<?php echo number_format(array_sum(array_column($topClans, 'total_amount')), 2); ?></strong>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Monthly Trend Chart -->
    <div class="chart-container">
        <div class="chart-header">
            <div>
                <h2 class="chart-title">Monthly Revenue Trend</h2>
                <p class="chart-subtitle">Monthly revenue for the last 12 months</p>
            </div>
        </div>
        <div class="chart-body">
            <canvas id="monthlyTrendChart" class="chart-canvas"></canvas>
        </div>
    </div>
     
    <!-- Enhanced Payment Summaries Table -->
    <div class="table-container">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="card-title">Monthly Payment Summaries</h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" id="toggleTableView">
                        <i class="fas fa-th-large" id="viewIcon"></i>
                        <span id="viewText">Card View</span>
                    </button>
                </div>
            </div>
        </div>
            
        <!-- Table View -->
        <div class="table-responsive" id="tableView">
            <table class="table table-sortable">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Payments</th>
                        <th>Total Amount</th>
                        <th>Completed</th>
                        <th>Pending</th>
                        <th>Failed</th>
                        <th>Refunded</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monthlySummaries)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div style="color: var(--text-muted);">
                                    <i class="fas fa-chart-bar" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No payment history found</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($monthlySummaries as $summary): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="month-indicator">
                                            <?php echo date("M", mktime(0, 0, 0, $summary['month'], 1, $summary['year'])); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: var(--font-weight-medium); color: var(--text-primary);">
                                                <?php echo date("F Y", mktime(0, 0, 0, $summary['month'], 1, $summary['year'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                
                                <td>
                                    <div style="font-weight: var(--font-weight-medium); color: var(--text-primary);">
                                        <?php echo $summary['payment_count']; ?>
                                    </div>
                                    <?php if ($summary['per_user_count'] > 0): ?>
                                        <div class="table-status" style="margin-top: 0.25rem;">
                                            <div class="table-status-dot" style="background-color: var(--info);"></div>
                                            <span style="color: var(--info); font-size: var(--font-size-sm);">
                                                <?php echo $summary['per_user_count']; ?> per-user
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <span style="font-weight: var(--font-weight-semibold); color: var(--primary);">
                                        <i class="fas fa-naira-sign"></i><?php echo number_format($summary['total_amount'], 2); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <?php if ($summary['completed_count'] > 0): ?>
                                        <div class="table-status">
                                            <div class="table-status-dot status-active"></div>
                                            <span style="color: var(--success); font-weight: var(--font-weight-medium);">
                                                <?php echo $summary['completed_count']; ?>
                                            </span>
                                        </div>
                                        <div style="font-size: var(--font-size-sm); color: var(--text-secondary);">
                                            <i class="fas fa-naira-sign"></i><?php echo number_format($summary['completed_amount'], 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if ($summary['pending_count'] > 0): ?>
                                        <div class="table-status">
                                            <div class="table-status-dot status-pending"></div>
                                            <span style="color: var(--warning); font-weight: var(--font-weight-medium);">
                                                <?php echo $summary['pending_count']; ?>
                                            </span>
                                        </div>
                                        <div style="font-size: var(--font-size-sm); color: var(--text-secondary);">
                                            <i class="fas fa-naira-sign"></i><?php echo number_format($summary['pending_amount'], 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if ($summary['failed_count'] > 0): ?>
                                        <div class="table-status">
                                            <div class="table-status-dot status-inactive"></div>
                                            <span style="color: var(--danger); font-weight: var(--font-weight-medium);">
                                                <?php echo $summary['failed_count']; ?>
                                            </span>
                                        </div>
                                        <div style="font-size: var(--font-size-sm); color: var(--text-secondary);">
                                            <i class="fas fa-naira-sign"></i><?php echo number_format($summary['failed_amount'], 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if ($summary['refunded_count'] > 0): ?>
                                        <div class="table-status">
                                            <div class="table-status-dot" style="background-color: var(--info);"></div>
                                            <span style="color: var(--info); font-weight: var(--font-weight-medium);">
                                                <?php echo $summary['refunded_count']; ?>
                                            </span>
                                        </div>
                                        <div style="font-size: var(--font-size-sm); color: var(--text-secondary);">
                                            <i class="fas fa-naira-sign"></i><?php echo number_format($summary['refunded_amount'], 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <a href="<?php echo BASE_URL; ?>payments/?<?php echo http_build_query(array_filter([
                                            'clan_id' => $clanId ?? null,
                                            'date_from' => date('Y-m-01', mktime(0, 0, 0, $summary['month'], 1, $summary['year'])),
                                            'date_to' => date('Y-m-t', mktime(0, 0, 0, $summary['month'], 1, $summary['year']))
                                        ])); ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-list"></i>
                                            <span>Details</span>
                                        </a>
                                        
                                        <?php if ($currentUser->isSuperAdmin()): ?>
                                            <a href="<?php echo BASE_URL; ?>payments/export.php?year=<?php echo $summary['year']; ?>&month=<?php echo $summary['month']; ?><?php echo $clanId ? '&clan_id=' . $clanId : ''; ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-download"></i>
                                                <span>Export</span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
            
        <!-- Card View (hidden by default) -->
        <div class="card-body" id="cardView" style="display: none;">
            <div class="payment-cards-grid">
                <?php if (empty($monthlySummaries)): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-chart-bar"></i>
                            <h3>No payment history found</h3>
                            <p>No payments match your current filter criteria</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($monthlySummaries as $summary): ?>
                        <div class="payment-summary-card">
                            <div class="payment-card-header">
                                <h5 class="payment-card-title">
                                    <?php echo date("F Y", mktime(0, 0, 0, $summary['month'], 1, $summary['year'])); ?>
                                </h5>
                                <div class="payment-card-amount">
                                    <i class="fas fa-naira-sign"></i><?php echo number_format($summary['total_amount'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="payment-card-body">
                                <div class="payment-summary-stats">
                                    <div class="stat-item">
                                        <div class="stat-icon success">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-value"><?php echo $summary['completed_count']; ?></div>
                                            <div class="stat-label">Completed</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-item">
                                        <div class="stat-icon warning">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-value"><?php echo $summary['pending_count']; ?></div>
                                            <div class="stat-label">Pending</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-item">
                                        <div class="stat-icon danger">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                        <div class="stat-content">
                                            <div class="stat-value"><?php echo $summary['failed_count']; ?></div>
                                            <div class="stat-label">Failed</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($summary['per_user_count'] > 0): ?>
                                    <div class="payment-card-note">
                                        <i class="fas fa-users"></i>
                                        <span><?php echo $summary['per_user_count']; ?> per-user payment<?php echo $summary['per_user_count'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="payment-card-footer">
                                <a href="<?php echo BASE_URL; ?>payments/?<?php echo http_build_query(array_filter([
                                    'clan_id' => $clanId ?? null,
                                    'date_from' => date('Y-m-01', mktime(0, 0, 0, $summary['month'], 1, $summary['year'])),
                                    'date_to' => date('Y-m-t', mktime(0, 0, 0, $summary['month'], 1, $summary['year']))
                                ])); ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-list"></i>
                                    <span>View Details</span>
                                </a>
                                
                                <?php if ($currentUser->isSuperAdmin()): ?>
                                    <a href="<?php echo BASE_URL; ?>payments/export.php?year=<?php echo $summary['year']; ?>&month=<?php echo $summary['month']; ?><?php echo $clanId ? '&clan_id=' . $clanId : ''; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-download"></i>
                                        <span>Export</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
            
        <!-- Enhanced Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="table-pagination">
                <div class="pagination-info">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> to <?php echo min($page * $limit, $totalCount); ?> of <?php echo $totalCount; ?> entries
                </div>
                <nav class="pagination-nav">
                    <ul class="pagination">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['clan_id']) ? '&clan_id=' . htmlspecialchars($_GET['clan_id']) : ''; ?><?php echo isset($_GET['year']) ? '&year=' . htmlspecialchars($_GET['year']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php 
                        $startPage = max(1, min($page - 2, $totalPages - 4));
                        $endPage = min($totalPages, max($page + 2, 5));
                        
                        if ($startPage > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo isset($_GET['clan_id']) ? '&clan_id=' . htmlspecialchars($_GET['clan_id']) : ''; ?><?php echo isset($_GET['year']) ? '&year=' . htmlspecialchars($_GET['year']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?>">1</a>
                            </li>
                            <?php if ($startPage > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['clan_id']) ? '&clan_id=' . htmlspecialchars($_GET['clan_id']) : ''; ?><?php echo isset($_GET['year']) ? '&year=' . htmlspecialchars($_GET['year']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo isset($_GET['clan_id']) ? '&clan_id=' . htmlspecialchars($_GET['clan_id']) : ''; ?><?php echo isset($_GET['year']) ? '&year=' . htmlspecialchars($_GET['year']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?>"><?php echo $totalPages; ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['clan_id']) ? '&clan_id=' . htmlspecialchars($_GET['clan_id']) : ''; ?><?php echo isset($_GET['year']) ? '&year=' . htmlspecialchars($_GET['year']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>


<!-- Dasher Chart Configuration and Initialization - Matching Super Admin Dashboard -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎨 Initializing Dasher UI Payment History...');
    
    // Dasher theme-aware chart configuration
    function getDasherChartConfig() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        
        return {
            colors: {
                primary: getComputedStyle(document.documentElement).getPropertyValue('--primary'),
                success: getComputedStyle(document.documentElement).getPropertyValue('--success'),
                warning: getComputedStyle(document.documentElement).getPropertyValue('--warning'),
                danger: getComputedStyle(document.documentElement).getPropertyValue('--danger'),
                info: getComputedStyle(document.documentElement).getPropertyValue('--info'),
                text: getComputedStyle(document.documentElement).getPropertyValue('--text-primary'),
                textSecondary: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary'),
                border: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
            },
            font: {
                family: getComputedStyle(document.documentElement).getPropertyValue('--font-family-base'),
                size: 12,
                weight: '400'
            }
        };
    }
    
    const chartConfig = getDasherChartConfig();

    
    // Revenue Chart (Enhanced Bar Chart) - Matching Super Admin Dashboard
    const revenueCtx = document.getElementById('yearlyRevenueChart').getContext('2d');
    const yearlyRevenueData = <?php echo json_encode($yearlyData); ?>;
    
    if (yearlyRevenueData.length > 0) {
        const years = yearlyRevenueData.map(item => item.year);
        const completedAmounts = yearlyRevenueData.map(item => parseFloat(item.completed_amount) || 0);
        const pendingAmounts = yearlyRevenueData.map(item => parseFloat(item.pending_amount) || 0);
        const failedAmounts = yearlyRevenueData.map(item => parseFloat(item.failed_amount) || 0);
        const refundedAmounts = yearlyRevenueData.map(item => parseFloat(item.refunded_amount) || 0);
        
        const revenueChart = new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: years,
                datasets: [
                    {
                        label: 'Completed',
                        data: completedAmounts,
                        backgroundColor: chartConfig.colors.success + '20',
                        borderColor: chartConfig.colors.success,
                        borderWidth: 2,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'Pending',
                        data: pendingAmounts,
                        backgroundColor: chartConfig.colors.warning + '20',
                        borderColor: chartConfig.colors.warning,
                        borderWidth: 2,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'Failed',
                        data: failedAmounts,
                        backgroundColor: chartConfig.colors.danger + '20',
                        borderColor: chartConfig.colors.danger,
                        borderWidth: 2,
                        borderRadius: 4,
                        borderSkipped: false,
                    },
                    {
                        label: 'Refunded',
                        data: refundedAmounts,
                        backgroundColor: chartConfig.colors.info + '20',
                        borderColor: chartConfig.colors.info,
                        borderWidth: 2,
                        borderRadius: 4,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            pointStyle: 'circle',
                            padding: 20,
                            font: {
                                family: chartConfig.font.family,
                                size: 12,
                                weight: '500'
                            },
                            color: chartConfig.colors.text
                        }
                    },
                    tooltip: {
                        backgroundColor: chartConfig.colors.text + '10',
                        titleColor: chartConfig.colors.text,
                        bodyColor: chartConfig.colors.text,
                        borderColor: chartConfig.colors.border,
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 12,
                        titleFont: {
                            family: chartConfig.font.family,
                            size: 14,
                            weight: '600'
                        },
                        bodyFont: {
                            family: chartConfig.font.family,
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: chartConfig.colors.textSecondary,
                            font: {
                                family: chartConfig.font.family,
                                size: 12
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: chartConfig.colors.border + '40',
                            drawBorder: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: chartConfig.colors.textSecondary,
                            font: {
                                family: chartConfig.font.family,
                                size: 12
                            },
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
        
        // Toggle chart type (bar/line) - Matching Super Admin Dashboard
        if (document.getElementById('chartTypeToggle')) {
            document.getElementById('chartTypeToggle').addEventListener('click', function(e) {
                if (e.target.tagName === 'BUTTON') {
                    // Remove active class from all buttons
                    this.querySelectorAll('.btn').forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    e.target.classList.add('active');
                    
                    // Get chart type
                    const chartType = e.target.getAttribute('data-chart-type');
                    
                    // Update chart type
                    revenueChart.config.type = chartType;
                    revenueChart.update();
                }
            });
        }
    } else {
        document.getElementById('yearlyRevenueChart').innerHTML = '<div class="text-center py-5 text-muted">No revenue data available</div>';
    }
    
    // Payment Method Distribution Chart (Enhanced Doughnut) - Matching Super Admin Dashboard
    const distributionCtx = document.getElementById('paymentMethodChart').getContext('2d');
    const paymentMethodData = <?php echo json_encode($paymentMethodData); ?>;
    
    if (paymentMethodData.length > 0) {
        const methodNames = paymentMethodData.map(item => item.payment_method.charAt(0).toUpperCase() + item.payment_method.slice(1));
        const methodAmounts = paymentMethodData.map(item => parseFloat(item.total_amount) || 0);
        
        const distributionChart = new Chart(distributionCtx, {
            type: 'doughnut',
            data: {
                labels: methodNames,
                datasets: [{
                    data: methodAmounts,
                    backgroundColor: [
                        chartConfig.colors.primary,
                        chartConfig.colors.success,
                        chartConfig.colors.warning,
                        chartConfig.colors.danger,
                        chartConfig.colors.info
                    ],
                    borderColor: [
                        chartConfig.colors.primary,
                        chartConfig.colors.success,
                        chartConfig.colors.warning,
                        chartConfig.colors.danger,
                        chartConfig.colors.info
                    ],
                    borderWidth: 0,
                    hoverBorderWidth: 2,
                    cutout: '70%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: chartConfig.colors.text + '10',
                        titleColor: chartConfig.colors.text,
                        bodyColor: chartConfig.colors.text,
                        borderColor: chartConfig.colors.border,
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 12,
                        titleFont: {
                            family: chartConfig.font.family,
                            size: 14,
                            weight: '600'
                        },
                        bodyFont: {
                            family: chartConfig.font.family,
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ₦' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                elements: {
                    arc: {
                        borderWidth: 0
                    }
                }
            }
        });
    } else {
        document.getElementById('paymentMethodChart').innerHTML = '<div class="text-center py-5 text-muted">No payment method data available</div>';
    }
    
    // Monthly Trend Chart - Matching Super Admin Dashboard
    const monthlyTrendData = <?php echo json_encode($monthlyTrendData); ?>;
    
    if (monthlyTrendData.length > 0) {
        const monthlyLabels = monthlyTrendData.map(item => {
            const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            return monthNames[parseInt(item.month) - 1] + ' ' + item.year;
        });
        
        const monthlyAmounts = monthlyTrendData.map(item => parseFloat(item.completed_amount) || 0);
        
        // Create monthly trend chart using Chart.js to match dashboard
        const monthlyCtx = document.getElementById('monthlyTrendChart').getContext('2d');
        const monthlyTrendChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Revenue',
                    data: monthlyAmounts,
                    borderColor: chartConfig.colors.primary,
                    backgroundColor: chartConfig.colors.primary + '20',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: chartConfig.colors.primary,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: chartConfig.colors.text + '10',
                        titleColor: chartConfig.colors.text,
                        bodyColor: chartConfig.colors.text,
                        borderColor: chartConfig.colors.border,
                        borderWidth: 1,
                        cornerRadius: 8,
                        padding: 12,
                        titleFont: {
                            family: chartConfig.font.family,
                            size: 14,
                            weight: '600'
                        },
                        bodyFont: {
                            family: chartConfig.font.family,
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₦' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: chartConfig.colors.textSecondary,
                            font: {
                                family: chartConfig.font.family,
                                size: 11
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: chartConfig.colors.border + '40',
                            drawBorder: false
                        },
                        border: {
                            display: false
                        },
                        ticks: {
                            color: chartConfig.colors.textSecondary,
                            font: {
                                family: chartConfig.font.family,
                                size: 11
                            },
                            callback: function(value) {
                                return '₦' + value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });
    } else {
        document.getElementById('monthlyTrendChart').innerHTML = '<div class="text-center py-5 text-muted">No monthly trend data available</div>';
    }
    
    // Toggle between table and card view
    const toggleTableView = document.getElementById('toggleTableView');
    const tableView = document.getElementById('tableView');
    const cardView = document.getElementById('cardView');
    const viewIcon = document.getElementById('viewIcon');
    const viewText = document.getElementById('viewText');
    
    if (toggleTableView) {
        toggleTableView.addEventListener('click', function() {
            if (tableView.style.display === 'none') {
                tableView.style.display = 'block';
                cardView.style.display = 'none';
                viewIcon.className = 'fas fa-th-large';
                viewText.textContent = 'Card View';
            } else {
                tableView.style.display = 'none';
                cardView.style.display = 'block';
                viewIcon.className = 'fas fa-table';
                viewText.textContent = 'Table View';
            }
        });
    }
    
    // Update charts when theme changes - Matching Super Admin Dashboard
    document.addEventListener('themeChanged', function(event) {
        console.log('🎨 Updating payment history charts for theme:', event.detail.theme);
        
        const newConfig = getDasherChartConfig();
        
        // Update charts if they exist
        Chart.helpers.each(Chart.instances, function(instance) {
            if (instance.canvas.id === 'yearlyRevenueChart' || 
                instance.canvas.id === 'paymentMethodChart' || 
                instance.canvas.id === 'monthlyTrendChart') {
                
                // Update chart colors and fonts
                if (instance.options.plugins && instance.options.plugins.legend) {
                    instance.options.plugins.legend.labels.color = newConfig.colors.text;
                }
                
                if (instance.options.scales) {
                    Object.keys(instance.options.scales).forEach(scale => {
                        if (instance.options.scales[scale].ticks) {
                            instance.options.scales[scale].ticks.color = newConfig.colors.textSecondary;
                        }
                        if (instance.options.scales[scale].grid) {
                            instance.options.scales[scale].grid.color = newConfig.colors.border + '40';
                        }
                    });
                }
                
                instance.update('none');
            }
        });
    });
    
    console.log('✅ Dasher UI Payment History initialized successfully');
});

// Export Top Clans function
function exportTopClans() {
    // Get current filters
    const params = new URLSearchParams(window.location.search);
    const exportParams = new URLSearchParams();
    
    // Add current filters to export
    if (params.get('clan_id')) exportParams.set('clan_id', params.get('clan_id'));
    if (params.get('year')) exportParams.set('year', params.get('year'));
    if (params.get('status')) exportParams.set('status', params.get('status'));
    
    // Add export type
    exportParams.set('export', 'top_clans');
    exportParams.set('format', 'csv');
    
    // Create download link
    const exportUrl = '<?php echo BASE_URL; ?>payments/export.php?' + exportParams.toString();
    
    // Trigger download
    window.open(exportUrl, '_blank');
}
// Enhanced dropdown feedback
const dropdownItems = document.querySelectorAll('.dropdown-item[href]');
dropdownItems.forEach(item => {
    if (!item.href.includes('#') && !item.href.includes('javascript:')) {
        item.addEventListener('click', function() {
            // Add subtle loading indication
            const icon = item.querySelector('i');
            if (icon && !icon.classList.contains('fa-spinner')) {
                icon.style.opacity = '0.6';
                
                // Reset after 2 seconds
                setTimeout(() => {
                    icon.style.opacity = '';
                }, 2000);
            }
        });
    }
});
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>