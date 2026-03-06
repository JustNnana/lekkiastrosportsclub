<?php
/**
 * Gate Wey Access Management System
 * Payment Management Page - ENHANCED WITH DASHER UI
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/Payment.php';
require_once 'payment_constants.php';

// Set page title
$pageTitle = 'Payment Management';

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
// Only super_admin and clan_admin can access payment management
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
    $clanId = $_GET['clan_id'];
}

// Initialize variables
$error = '';
$success = '';

// Process payment reminder if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reminder']) && isset($_POST['clan_id'])) {
    $reminderClanId = $_POST['clan_id'];
    
    // Check if user has permission to send reminders
    if ($currentUser->isSuperAdmin() || ($currentUser->isClanAdmin() && $currentUser->getClanId() == $reminderClanId)) {
        $clan = new Clan();
        
        if ($clan->loadById($reminderClanId)) {
            // Get clan admin users
            $clanAdmins = $db->fetchAll(
                "SELECT id FROM users WHERE clan_id = ? AND role = 'clan_admin'",
                [$reminderClanId]
            );
            
            // Create notifications for clan admins
            foreach ($clanAdmins as $admin) {
                // Get the notification template
                $template = $db->fetchOne(
                    "SELECT * FROM notification_templates WHERE type = 'payment_reminder' AND is_active = 1"
                );
                
                if ($template) {
                    // Replace placeholders in template
                    $title = $template['title'];
                    $message = str_replace(
                        ['{clan_name}', '{due_date}'],
                        [$clan->getName(), date('F j, Y', strtotime($clan->getNextPaymentDate()))],
                        $template['message']
                    );
                    
                    // Create notification
                    $db->query(
                        "INSERT INTO notifications (user_id, clan_id, title, message, type, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$admin['id'], $reminderClanId, $title, $message, 'payment_reminder', $reminderClanId, 'clan']
                    );
                }
            }
            
            $success = 'Payment reminder sent successfully to clan administrators.';
        } else {
            $error = 'Clan not found.';
        }
    } else {
        $error = 'You do not have permission to send payment reminders for this clan.';
    }
}

// Get pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get filters
$filters = [];

if (isset($_GET['status']) && in_array($_GET['status'], ['pending', 'completed', 'failed', 'refunded'])) {
    $filters['status'] = $_GET['status'];
}

if (isset($_GET['payment_method']) && !empty($_GET['payment_method'])) {
    $filters['payment_method'] = $_GET['payment_method'];
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $filters['date_from'] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $filters['date_to'] = $_GET['date_to'];
}

// If clan ID is set, add it to filters
if ($clanId) {
    $filters['clan_id'] = $clanId;
}

// Build query for payments
$query = "SELECT p.*, c.name as clan_name, pp.name as plan_name, pp.is_per_user, 
          (SELECT COUNT(*) FROM users WHERE clan_id = c.id) as user_count
          FROM payments p 
          JOIN clans c ON p.clan_id = c.id 
          JOIN pricing_plans pp ON p.pricing_plan_id = pp.id 
          WHERE 1=1";
$params = [];

// Apply filters to query
if (isset($filters['clan_id'])) {
    $query .= " AND p.clan_id = ?";
    $params[] = $filters['clan_id'];
}

if (isset($filters['status'])) {
    $query .= " AND p.status = ?";
    $params[] = $filters['status'];
}

if (isset($filters['payment_method'])) {
    $query .= " AND p.payment_method = ?";
    $params[] = $filters['payment_method'];
}

if (isset($filters['date_from'])) {
    $query .= " AND DATE(p.payment_date) >= ?";
    $params[] = $filters['date_from'];
}

if (isset($filters['date_to'])) {
    $query .= " AND DATE(p.payment_date) <= ?";
    $params[] = $filters['date_to'];
}

// Get total count for pagination
$countQuery = str_replace("SELECT p.*, c.name as clan_name, pp.name as plan_name, pp.is_per_user", "SELECT COUNT(*) as count", $query);
$totalCount = $db->fetchOne($countQuery, $params)['count'];
$totalPages = ceil($totalCount / $limit);

// Add ordering and pagination
$query .= " ORDER BY p.payment_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Fetch payments
$payments = $db->fetchAll($query, $params);

// Get payment methods for filter
$paymentMethods = $db->fetchAll("SELECT DISTINCT payment_method FROM payments ORDER BY payment_method");

// Get clans for filter (for super admin only)
$clans = [];
if ($currentUser->isSuperAdmin()) {
    $clans = $db->fetchAll("SELECT id, name FROM clans ORDER BY name");
}

// Get enhanced statistics
$totalRevenue = $db->fetchOne(
    "SELECT SUM(amount) as total FROM payments WHERE status = 'completed'" . 
    (isset($filters['clan_id']) ? " AND clan_id = " . $filters['clan_id'] : "")
);

$activeClans = $db->fetchOne(
    "SELECT COUNT(*) as count FROM clans WHERE payment_status = 'active'" . 
    (isset($filters['clan_id']) ? " AND id = " . $filters['clan_id'] : "")
);

$pendingPayments = $db->fetchOne(
    "SELECT COUNT(*) as count FROM payments WHERE status = 'pending'" . 
    (isset($filters['clan_id']) ? " AND clan_id = " . $filters['clan_id'] : "")
);

$overdueClans = $db->fetchOne(
    "SELECT COUNT(*) as count FROM clans 
    WHERE payment_status = 'active' 
    AND next_payment_date < CURDATE()" . 
    (isset($filters['clan_id']) ? " AND id = " . $filters['clan_id'] : "")
);

// Include header
include_once '../includes/header.php';

// Include sidebar
include_once '../includes/sidebar.php';
?>

<!-- ENHANCED PAYMENT MANAGEMENT STYLING -->
<style>
/* Payment Badge Styling */
.payment-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: var(--font-weight-medium);
    border-radius: var(--border-radius);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.payment-badge.per-user {
    background: rgba(var(--info-rgb), 0.1);
    color: var(--info);
}

.payment-badge.fixed {
    background: rgba(var(--secondary-rgb), 0.1);
    color: var(--secondary);
}

/* Enhanced Filters Section */
.enhanced-filters {
    padding: 1.5rem;
}

.filters-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-label {
    color: var(--text-primary);
    font-weight: var(--font-weight-medium);
    font-size: var(--font-size-sm);
}

.filter-select,
.filter-input {
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 0.5rem 0.75rem;
    color: var(--text-primary);
    font-size: var(--font-size-sm);
    transition: var(--theme-transition);
}

.filter-select:focus,
.filter-input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
    outline: none;
}

.filters-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-start;
}

/* Enhanced Dropdown Styling */
.dropdown-toggle {
    border: none !important;
    background: transparent !important;
    color: var(--text-secondary) !important;
    height: 36px;
    border-radius: var(--border-radius) !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    box-shadow: none !important;
}

.dropdown-toggle:hover {
    background: var(--bg-hover) !important;
    color: var(--text-primary) !important;
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm) !important;
}

.dropdown-toggle:focus {
    background: var(--primary) !important;
    color: white !important;
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25) !important;
}

.dropdown-toggle::after {
    display: none !important;
}

.dropdown-menu {
    min-width: 200px;
    padding: 0.5rem 0;
    background: var(--bg-card);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
}

.dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.dropdown-item {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 0.5rem 0.75rem;
    clear: both;
    font-weight: 400;
    color: var(--text-primary);
    text-align: inherit;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
}

.dropdown-item i {
    width: 16px;
    margin-right: 0.75rem;
    font-size: 0.875rem;
    color: var(--text-secondary);
    transition: color 0.3s ease;
}

.dropdown-item:hover,
.dropdown-item:focus {
    background-color: var(--bg-hover);
    color: var(--text-primary);
    text-decoration: none;
}

.dropdown-item:hover i,
.dropdown-item:focus i {
    color: var(--primary);
}

.dropdown-item-danger {
    color: var(--danger);
}

.dropdown-item-danger:hover {
    background-color: rgba(var(--danger-rgb), 0.1);
    color: var(--danger);
}

.dropdown-item-danger i {
    color: var(--danger);
}

.dropdown-divider {
    height: 0;
    margin: 0.5rem 0;
    overflow: hidden;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

/* Status text color for table */
.table-status span {
    color: var(--success);
}

/* Mobile Responsive Design */
/* Mobile Responsive Design */
@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-actions {
        flex-direction: column;
    }
    
    /* .content-header .d-flex {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 1rem !important;
    } */
    
    .content-actions {
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-end !important;
        gap: 0.5rem !important;
        align-self: flex-end !important;
        width: auto !important;
    }
    
    .content-actions .btn {
        flex-shrink: 0;
        white-space: nowrap;
        width: auto !important;
    }
    
    /* Mobile stats cards adjustments */
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
    
    /* Table responsive improvements */
    .table-responsive {
        font-size: 0.875rem;
    }
    
    .user-avatar {
        width: 28px !important;
        height: 28px !important;
        font-size: 0.75rem !important;
    }
}

@media (max-width: 576px) {
    .enhanced-filters {
        padding: 1rem;
    }
    
    .filter-group {
        gap: 0.25rem;
    }
    
    .dropdown-menu {
        min-width: 180px;
        right: -10px;
    }
    
    .dropdown-item {
        padding: 0.75rem;
        font-size: 0.875rem;
    }
}

/* Dark theme specific adjustments */
[data-theme="dark"] .dropdown-toggle {
    background: var(--bg-card-dark);
    border-color: var(--border-color-dark);
    color: var(--text-secondary-dark);
}

[data-theme="dark"] .dropdown-toggle:hover {
    background: var(--bg-hover-dark);
    color: var(--text-primary-dark);
    border-color: var(--primary);
}

[data-theme="dark"] .dropdown-item {
    color: var(--text-primary-dark);
}

[data-theme="dark"] .dropdown-item:hover {
    background-color: var(--bg-hover-dark);
}

[data-theme="dark"] .dropdown-divider {
    border-color: var(--border-color-dark);
}

/* Enhanced focus states for accessibility */
.dropdown-toggle:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

.dropdown-item:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: -2px;
}

/* Loading state for dropdown actions */
.dropdown-item.loading {
    opacity: 0.6;
    pointer-events: none;
}

.dropdown-item.loading i {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.pagination-info{
        display: flex;
        justify-content: center;
    }
    .pagination-nav{
        display: flex;
        justify-content: center;
    }
</style>
<!-- ENHANCED DASHER UI CONTENT AREA -->
<div class="content">
    <!-- Enhanced Content Header -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="content-title">Payment Management</h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Payments</li>
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
                
                <a href="<?php echo BASE_URL; ?>payments/analytics.php" class="btn btn-primary">
                    <i class="fas fa-chart-line"></i>
                    <span>Payment Analytics</span>
                </a>
                
                <?php if ($currentUser->isSuperAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>payments/plans.php" class="btn btn-primary">
                        <i class="fas fa-tags"></i>
                        <span>Pricing Plans</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Enhanced Alert Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle"></i>
            <span><?php echo $error; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle"></i>
            <span><?php echo $success; ?></span>
        </div>
    <?php endif; ?>

    <!-- Enhanced Statistics Summary Cards -->
    <div class="chart-grid">
        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon primary">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Total Revenue</h3>
                    <p class="improved-stats-value">₦<?php echo number_format($totalRevenue['total'] ?? 0, 2); ?></p>
                </div>
            </div>
            <div class="improved-stats-change">
                <span>All time completed payments</span>
            </div>
        </div>

        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon success">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Active Subscriptions</h3>
                    <p class="improved-stats-value"><?php echo $activeClans['count'] ?? 0; ?></p>
                </div>
            </div>
            <div class="improved-stats-change positive">
                <span>Active paying clans</span>
            </div>
        </div>

        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon warning">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Pending Payments</h3>
                    <p class="improved-stats-value"><?php echo $pendingPayments['count'] ?? 0; ?></p>
                </div>
            </div>
            <div class="improved-stats-change">
                <span>Awaiting processing</span>
            </div>
        </div>

        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Overdue Payments</h3>
                    <p class="improved-stats-value"><?php echo $overdueClans['count'] ?? 0; ?></p>
                </div>
            </div>
            <div class="improved-stats-change">
                <span>Clans with overdue payments</span>
            </div>
        </div>
    </div>

    <!-- Enhanced Filters Section -->
    <div class="table-container">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="card-title">Payment Filters</h2>
            </div>
        </div>

        <div class="enhanced-filters">
            <form action="" method="get" class="filters-form">
                <div class="filters-grid">
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
                        <label for="status" class="filter-label">Status</label>
                        <select class="filter-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="failed" <?php echo (isset($_GET['status']) && $_GET['status'] === 'failed') ? 'selected' : ''; ?>>Failed</option>
                            <option value="refunded" <?php echo (isset($_GET['status']) && $_GET['status'] === 'refunded') ? 'selected' : ''; ?>>Refunded</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="payment_method" class="filter-label">Payment Method</label>
                        <select class="filter-select" id="payment_method" name="payment_method">
                            <option value="">All Methods</option>
                            <?php foreach ($paymentMethods as $method): ?>
                                <option value="<?php echo htmlspecialchars($method['payment_method']); ?>" <?php echo (isset($_GET['payment_method']) && $_GET['payment_method'] === $method['payment_method']) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(htmlspecialchars($method['payment_method'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from" class="filter-label">From Date</label>
                        <input type="date" class="filter-input" id="date_from" name="date_from" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to" class="filter-label">To Date</label>
                        <input type="date" class="filter-input" id="date_to" name="date_to" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                    </div>
                </div>
                
                <div class="filters-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        <span>Apply Filters</span>
                    </button>
                    <a href="<?php echo BASE_URL; ?>payments/" class="btn btn-outline-secondary">
                        <i class="fas fa-redo"></i>
                        <span>Reset</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Enhanced Payments Table -->
    <div class="table-container">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="card-title">All Payments</h2>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sortable">
                <thead>
                    <tr>
                        <th>Payment ID</th>
                        <?php if ($currentUser->isSuperAdmin() && !isset($filters['clan_id'])): ?>
                            <th>Clan Details</th>
                        <?php endif; ?>
                        <th>Amount</th>
                        <th>Plan</th>
                        <th>Type</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Period</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="<?php echo $currentUser->isSuperAdmin() && !isset($filters['clan_id']) ? '10' : '9'; ?>" class="text-center py-4">
                                <div style="color: var(--text-muted);">
                                    <i class="fas fa-credit-card" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No payments found</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="payment-id">
                                            <span style="color: var(--text-primary); font-weight: var(--font-weight-medium);">
                                                #<?php echo $payment['id']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                
                                <?php if ($currentUser->isSuperAdmin() && !isset($filters['clan_id'])): ?>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar" style="width: 32px; height: 32px; margin-right: 0.75rem;">
                                                <?php echo strtoupper(substr($payment['clan_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo $payment['clan_id']; ?>" 
                                                   style="color: var(--text-primary); text-decoration: none; font-weight: var(--font-weight-medium);">
                                                    <?php echo htmlspecialchars($payment['clan_name']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                <?php endif; ?>
                                
                                <td>
                                    <span style="color: var(--text-primary); font-weight: var(--font-weight-semibold);">
                                        ₦<?php echo number_format($payment['amount'], 2); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <span style="color: var(--text-primary); font-weight: var(--font-weight-medium);">
                                        <?php echo htmlspecialchars($payment['plan_name']); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <?php if ($payment['is_per_user']): ?>
                                        <div>
                                            <span class="payment-badge per-user">Per-User</span>
                                            <div style="color: var(--text-secondary); font-size: var(--font-size-sm); margin-top: 0.25rem;">
                                                <?php echo $payment['user_count']; ?> users
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="payment-badge fixed">Fixed</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <span style="color: var(--text-primary); text-transform: capitalize;">
                                        <?php echo htmlspecialchars($payment['payment_method']); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <div class="table-status">
                                        <?php if ($payment['status'] === 'pending'): ?>
                                            <div class="table-status-dot" style="background-color: var(--warning);"></div>
                                            <span style="color: var(--warning);">Pending</span>
                                        <?php elseif ($payment['status'] === 'completed'): ?>
                                            <div class="table-status-dot status-active"></div>
                                            <span style="color: var(--success);">Completed</span>
                                        <?php elseif ($payment['status'] === 'failed'): ?>
                                            <div class="table-status-dot status-inactive"></div>
                                            <span style="color: var(--danger);">Failed</span>
                                        <?php elseif ($payment['status'] === 'refunded'): ?>
                                            <div class="table-status-dot" style="background-color: var(--info);"></div>
                                            <span style="color: var(--info);">Refunded</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td>
                                    <span style="color: var(--text-secondary);">
                                        <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                    </span>
                                </td>
                                
                                <td>
                                    <?php if ($payment['period_start'] && $payment['period_end']): ?>
                                        <div style="color: var(--text-secondary); font-size: var(--font-size-sm);">
                                            <?php 
                                            echo date('M d, Y', strtotime($payment['period_start'])) . ' - ' . 
                                                 date('M d, Y', strtotime($payment['period_end']));
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <!-- Enhanced Dropdown Action Menu -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="actionsDropdown<?php echo $payment['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionsDropdown<?php echo $payment['id']; ?>">
                                            <li>
                                                <a class="dropdown-item" href="<?php echo BASE_URL; ?>payments/receipt.php?id=<?php echo encryptId($payment['id']); ?>" target="_blank">
                                                    <i class="fas fa-receipt"></i>
                                                    <span>View Receipt</span>
                                                </a>
                                            </li>
                                            
                                            <?php if ($currentUser->isSuperAdmin() && $payment['status'] === 'pending'): ?>
                                                <li>
                                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>payments/status.php?id=<?php echo $payment['id']; ?>&action=approve">
                                                        <i class="fas fa-check"></i>
                                                        <span>Approve Payment</span>
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item dropdown-item-danger" href="<?php echo BASE_URL; ?>payments/status.php?id=<?php echo $payment['id']; ?>&action=reject">
                                                        <i class="fas fa-times"></i>
                                                        <span>Reject Payment</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php if ($currentUser->isSuperAdmin() && $payment['status'] === 'completed'): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>payments/status.php?id=<?php echo $payment['id']; ?>&action=refund">
                                                        <i class="fas fa-undo"></i>
                                                        <span>Refund Payment</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Enhanced Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="table-pagination">
                <div class="pagination-info">
                    Showing <?php echo (($page - 1) * $limit) + 1; ?> to <?php echo min($page * $limit, $totalCount); ?> of <?php echo $totalCount; ?> payments
                </div>
                <nav class="pagination-nav">
                    <ul class="pagination">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['clan_id']) ? '&clan_id=' . htmlspecialchars($_GET['clan_id']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?><?php echo isset($_GET['payment_method']) ? '&payment_method=' . htmlspecialchars($_GET['payment_method']) : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . htmlspecialchars($_GET['date_from']) : ''; ?><?php echo isset($_GET['date_to']) ? '&date_to=' . htmlspecialchars($_GET['date_to']) : ''; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['clan_id']) ? '&clan_id=' . htmlspecialchars($_GET['clan_id']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?><?php echo isset($_GET['payment_method']) ? '&payment_method=' . htmlspecialchars($_GET['payment_method']) : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . htmlspecialchars($_GET['date_from']) : ''; ?><?php echo isset($_GET['date_to']) ? '&date_to=' . htmlspecialchars($_GET['date_to']) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['clan_id']) ? '&clan_id=' . htmlspecialchars($_GET['clan_id']) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . htmlspecialchars($_GET['status']) : ''; ?><?php echo isset($_GET['payment_method']) ? '&payment_method=' . htmlspecialchars($_GET['payment_method']) : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . htmlspecialchars($_GET['date_from']) : ''; ?><?php echo isset($_GET['date_to']) ? '&date_to=' . htmlspecialchars($_GET['date_to']) : ''; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

    <!-- Enhanced Overdue Payments Section (Only for Super Admin) -->
    <?php if ($currentUser->isSuperAdmin() && !isset($filters['clan_id'])): ?>
        <div class="table-container">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="card-title">Clans with Overdue Payments</h2>
                </div>
            </div>

            <?php
            $overdueClansDetails = $db->fetchAll(
                "SELECT c.id, c.name, c.admin_id, c.next_payment_date, c.payment_status, 
                        pp.name as plan_name, pp.price as plan_price, pp.is_per_user, pp.price_per_user,
                        u.full_name as admin_name, u.email as admin_email,
                        (SELECT COUNT(*) FROM users WHERE clan_id = c.id) as user_count
                 FROM clans c
                 LEFT JOIN pricing_plans pp ON c.pricing_plan_id = pp.id
                 LEFT JOIN users u ON c.admin_id = u.id
                 WHERE c.payment_status = 'active' 
                 AND c.next_payment_date < CURDATE()
                 ORDER BY c.next_payment_date ASC"
            );
            ?>

            <div class="table-responsive">
                <table class="table table-sortable">
                    <thead>
                        <tr>
                            <th>Clan Details</th>
                            <th>Administrator</th>
                            <th>Plan</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Overdue</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($overdueClansDetails)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <div style="color: var(--text-muted);">
                                        <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5; color: var(--success);"></i>
                                        <p>No clans with overdue payments</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($overdueClansDetails as $clan): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar" style="width: 32px; height: 32px; margin-right: 0.75rem;">
                                                <?php echo strtoupper(substr($clan['name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo $clan['id']; ?>" 
                                                   style="color: var(--text-primary); text-decoration: none; font-weight: var(--font-weight-medium);">
                                                    <?php echo htmlspecialchars($clan['name']); ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <?php if ($clan['admin_id']): ?>
                                            <div>
                                                <a href="<?php echo BASE_URL; ?>users/view.php?id=<?php echo $clan['admin_id']; ?>" 
                                                   style="color: var(--text-primary); text-decoration: none;">
                                                    <?php echo htmlspecialchars($clan['admin_name']); ?>
                                                </a>
                                                <div style="color: var(--text-secondary); font-size: var(--font-size-sm);">
                                                    <?php echo htmlspecialchars($clan['admin_email']); ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted);">Not assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td>
                                        <div>
                                            <span style="color: var(--text-primary); font-weight: var(--font-weight-medium);">
                                                <?php echo htmlspecialchars($clan['plan_name']); ?>
                                            </span>
                                            <?php if ($clan['is_per_user']): ?>
                                                <div>
                                                    <span class="payment-badge per-user">Per-User</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <?php 
                                        if ($clan['is_per_user']): 
                                            $amount = $clan['price_per_user'] * $clan['user_count'];
                                            echo '<span style="color: var(--text-primary); font-weight: var(--font-weight-semibold);">₦' . number_format($amount, 2) . '</span>';
                                            echo '<div style="color: var(--text-secondary); font-size: var(--font-size-sm);">' . $clan['user_count'] . ' users × ₦' . number_format($clan['price_per_user'], 2) . '</div>';
                                        else: 
                                            echo '<span style="color: var(--text-primary); font-weight: var(--font-weight-semibold);">₦' . number_format($clan['plan_price'], 2) . '</span>';
                                        endif;
                                        ?>
                                    </td>
                                    
                                    <td>
                                        <span style="color: var(--text-secondary);">
                                            <?php echo date('M d, Y', strtotime($clan['next_payment_date'])); ?>
                                        </span>
                                    </td>
                                    
                                    <td>
                                        <?php 
                                        $dueDate = new DateTime($clan['next_payment_date']);
                                        $today = new DateTime();
                                        $diff = $today->diff($dueDate);
                                        ?>
                                        <div class="table-status">
                                            <div class="table-status-dot status-inactive"></div>
                                            <span style="color: var(--danger);"><?php echo $diff->days; ?> days</span>
                                        </div>
                                    </td>
                                    
                                    <td class="text-end">
                                        <form method="post" action="" class="d-inline">
                                            <input type="hidden" name="clan_id" value="<?php echo $clan['id']; ?>">
                                            <button type="submit" name="send_reminder" class="btn btn-sm btn-warning">
                                                <i class="fas fa-bell"></i>
                                                <span>Send Reminder</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing Enhanced Payment Management with Dasher UI...');
    
    // Initialize Bootstrap dropdowns
    initializeBootstrapDropdowns();
    
    // Initialize date range validation
    initializeDateValidation();
    
    // Initialize responsive features
    initializeResponsiveFeatures();
    
    console.log('Enhanced Payment Management initialized successfully');
});

function initializeBootstrapDropdowns() {
    // Check if Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.warn('Bootstrap is not loaded yet, retrying dropdown initialization...');
        setTimeout(initializeBootstrapDropdowns, 100);
        return;
    }

    // Initialize Bootstrap dropdowns manually
    const dropdownElementList = document.querySelectorAll('.dropdown-toggle');
    
    dropdownElementList.forEach(dropdownToggleEl => {
        try {
            const dropdown = new bootstrap.Dropdown(dropdownToggleEl);
        } catch (error) {
            console.warn('Failed to initialize dropdown:', error);
        }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
                const toggle = menu.previousElementSibling;
                if (toggle) toggle.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Close dropdowns on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
                menu.classList.remove('show');
                const toggle = menu.previousElementSibling;
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    toggle.focus();
                }
            });
        }
    });
}

function initializeDateValidation() {
    // Date range validation
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    if (dateFrom && dateTo) {
        dateFrom.addEventListener('change', function() {
            dateTo.min = dateFrom.value;
        });
        
        dateTo.addEventListener('change', function() {
            dateFrom.max = dateTo.value;
        });
        
        // Set initial min/max if values exist
        if (dateFrom.value) {
            dateTo.min = dateFrom.value;
        }
        
        if (dateTo.value) {
            dateFrom.max = dateTo.value;
        }
    }
}

function initializeResponsiveFeatures() {
    // Handle responsive stats cards on mobile
    function handleStatsCardsScroll() {
        const statsGrid = document.querySelector('.chart-grid');
        if (statsGrid && window.innerWidth <= 768) {
            // Add scroll indicators for mobile stats
            statsGrid.addEventListener('scroll', function() {
                const scrollLeft = this.scrollLeft;
                const scrollWidth = this.scrollWidth;
                const clientWidth = this.clientWidth;
                
                // Could add visual indicators here if needed
            });
        }
    }
    
    // Initialize responsive features
    handleStatsCardsScroll();
    
    // Re-initialize on window resize
    window.addEventListener('resize', function() {
        handleStatsCardsScroll();
    });
}

// Enhanced user feedback for form submissions
const forms = document.querySelectorAll('form');
forms.forEach(form => {
    form.addEventListener('submit', function(e) {
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitButton.disabled = true;
            
            // Re-enable after 5 seconds as fallback
            setTimeout(() => {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            }, 5000);
        }
    });
});

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