<?php

/**
 * Gate Wey Access Management System
 * Pricing Plans Management Page - ENHANCED WITH DASHER UI
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once 'payment_constants.php'; // Adjust the path if needed

// Set page title
$pageTitle = 'Pricing Plans';

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

// Initialize variables
$error = '';
$success = '';
$formData = [
    'name' => '',
    'description' => '',
    'price' => '',
    'price_per_user' => '',
    'is_free' => 0,
    'is_per_user' => 0,
    'duration_days' => 30,
    'features' => ''
];

// Process add/edit plan form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_plan'])) {
    // Get form data
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'price' => floatval($_POST['price'] ?? 0),
        'price_per_user' => floatval($_POST['price_per_user'] ?? 0),
        'is_free' => isset($_POST['is_free']) ? 1 : 0,
        'is_per_user' => isset($_POST['is_per_user']) ? 1 : 0,
        'duration_days' => intval($_POST['duration_days'] ?? 30),
        'features' => trim($_POST['features'] ?? '')
    ];

    $planId = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : null;

    // Validate inputs
    $errors = [];

    if (empty($formData['name'])) {
        $errors[] = 'Plan name is required.';
    }

    if ($formData['is_free'] == 0 && $formData['is_per_user'] == 0 && $formData['price'] <= 0) {
        $errors[] = 'Please enter a valid price for paid plans.';
    }

    if ($formData['is_free'] == 0 && $formData['is_per_user'] == 1 && $formData['price_per_user'] <= 0) {
        $errors[] = 'Please enter a valid price per user for per-user plans.';
    }

    if ($formData['is_free'] == 1 && $formData['is_per_user'] == 1) {
        $errors[] = 'A plan cannot be both free and per-user at the same time.';
    }

    if ($formData['duration_days'] <= 0) {
        $errors[] = 'Duration must be at least 1 day.';
    }

    // If there are no errors, save the plan
    if (empty($errors)) {
        try {
            if ($planId) {
                // Update existing plan
                $db->query(
                    "UPDATE pricing_plans SET 
                     name = ?, 
                     description = ?, 
                     price = ?, 
                     price_per_user = ?, 
                     is_free = ?, 
                     is_per_user = ?, 
                     duration_days = ?, 
                     features = ?, 
                     updated_at = NOW()
                     WHERE id = ?",
                    [
                        $formData['name'],
                        $formData['description'],
                        $formData['price'],
                        $formData['price_per_user'],
                        $formData['is_free'],
                        $formData['is_per_user'],
                        $formData['duration_days'],
                        $formData['features'],
                        $planId
                    ]
                );
                $success = 'Pricing plan updated successfully.';
            } else {
                // Create new plan
                $db->query(
                    "INSERT INTO pricing_plans (name, description, price, price_per_user, is_free, is_per_user, duration_days, features) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $formData['name'],
                        $formData['description'],
                        $formData['price'],
                        $formData['price_per_user'],
                        $formData['is_free'],
                        $formData['is_per_user'],
                        $formData['duration_days'],
                        $formData['features']
                    ]
                );
                $success = 'Pricing plan created successfully.';
            }

            // Clear form data after successful submission
            $formData = [
                'name' => '',
                'description' => '',
                'price' => '',
                'price_per_user' => '',
                'is_free' => 0,
                'is_per_user' => 0,
                'duration_days' => 30,
                'features' => ''
            ];
        } catch (Exception $e) {
            $error = 'An error occurred while saving the pricing plan. Please try again.';
            error_log("Pricing plan save error: " . $e->getMessage());
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// Check for migration success message
if (isset($_GET['migrate_success']) && $_GET['migrate_success'] == 1) {
    $sourcePlanId = isset($_GET['source_plan']) ? (int)$_GET['source_plan'] : 0;
    $targetPlanId = isset($_GET['target_plan']) ? (int)$_GET['target_plan'] : 0;

    // Get plan names for the message
    $sourcePlanName = 'the selected plan';
    $targetPlanName = 'the target plan';

    if ($sourcePlanId) {
        $sourcePlan = $db->fetchOne("SELECT name FROM pricing_plans WHERE id = ?", [$sourcePlanId]);
        if ($sourcePlan) {
            $sourcePlanName = '"' . htmlspecialchars($sourcePlan['name']) . '"';
        }
    }

    if ($targetPlanId) {
        $targetPlan = $db->fetchOne("SELECT name FROM pricing_plans WHERE id = ?", [$targetPlanId]);
        if ($targetPlan) {
            $targetPlanName = '"' . htmlspecialchars($targetPlan['name']) . '"';
        }
    }

    $success = 'Records successfully migrated from ' . $sourcePlanName . ' to ' . $targetPlanName . '. You can now safely delete the plan.';
}

// Process delete plan form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plan'])) {
    $planId = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : null;

    try {
        // First check if any clans are using this plan as their current plan
        $clanCount = $db->fetchOne(
            "SELECT COUNT(*) as count FROM clans WHERE pricing_plan_id = ?",
            [$planId]
        )['count'];

        if ($clanCount > 0) {
            $error = 'Cannot delete this pricing plan because it is assigned to ' . $clanCount . ' clan(s). Please reassign the clans to another plan first.';
        } else {
            // Check for payments or payment_transactions using this plan
            $paymentCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM payments WHERE pricing_plan_id = ?",
                [$planId]
            )['count'];

            $transactionCount = $db->fetchOne(
                "SELECT COUNT(*) as count FROM payment_transactions WHERE pricing_plan_id = ?",
                [$planId]
            )['count'] ?? 0;

            if ($paymentCount > 0 || $transactionCount > 0) {
                // Redirect to the migration page instead of trying to delete
                header("Location: " . BASE_URL . "payments/migrate-plan.php?plan_id=" . $planId);
                exit;
            }

            // Start a transaction for consistent deletion
            $db->beginTransaction();

            // Remove any assignments from clan_pricing_plans
            $db->query(
                "DELETE FROM clan_pricing_plans WHERE pricing_plan_id = ?",
                [$planId]
            );

            // Finally, delete the plan itself
            $db->query("DELETE FROM pricing_plans WHERE id = ?", [$planId]);

            // Commit the transaction
            $db->commit();
            $success = 'Pricing plan deleted successfully.';
        }
    } catch (Exception $e) {
        // Rollback the transaction on error
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'An error occurred while deleting the pricing plan: ' . $e->getMessage();
        error_log("Plan delete error: " . $e->getMessage());
    }
}

// Process assigning plans to clans
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_to_clan'])) {
    $planId = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : null;
    $clanId = isset($_POST['clan_id']) ? intval($_POST['clan_id']) : null;

    if (!$planId || !$clanId) {
        $error = 'Plan ID and Clan ID are required.';
    } else {
        try {
            // Start a transaction to ensure data consistency
            $db->beginTransaction();

            // First, remove all existing plan assignments for this clan
            $db->query(
                "DELETE FROM clan_pricing_plans WHERE clan_id = ?",
                [$clanId]
            );

            // Then create the new assignment
            $db->query(
                "INSERT INTO clan_pricing_plans (clan_id, pricing_plan_id) VALUES (?, ?)",
                [$clanId, $planId]
            );

            // Update the clan's current plan if requested
            if (isset($_POST['set_as_current']) && $_POST['set_as_current'] == 1) {
                // Get the plan details
                $plan = $db->fetchOne(
                    "SELECT * FROM pricing_plans WHERE id = ?",
                    [$planId]
                );

                if ($plan) {
                    // If it's a free plan, set status to 'free', otherwise 'inactive'
                    $status = $plan['is_free'] ? 'free' : 'inactive';

                    // Update the clan's pricing plan
                    $db->query(
                        "UPDATE clans SET pricing_plan_id = ?, payment_status = ?, next_payment_date = NULL, updated_at = NOW() WHERE id = ?",
                        [$planId, $status, $clanId]
                    );
                }
            }

            // Commit the transaction
            $db->commit();

            $success = 'Plan assigned to clan successfully.';
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $db->rollBack();
            $error = 'An error occurred while assigning the plan to clan. Please try again.';
            error_log("Plan assignment error: " . $e->getMessage());
        }
    }
}

// Process removing plans from clans
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_clan'])) {
    $planId = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : null;
    $clanId = isset($_POST['clan_id']) ? intval($_POST['clan_id']) : null;

    if (!$planId || !$clanId) {
        $error = 'Plan ID and Clan ID are required.';
    } else {
        try {
            // Check if the clan is currently using this plan
            $currentPlan = $db->fetchOne(
                "SELECT pricing_plan_id FROM clans WHERE id = ?",
                [$clanId]
            )['pricing_plan_id'];

            if ($currentPlan == $planId) {
                $error = 'Cannot remove this plan because the clan is currently using it. Please change the clan\'s plan first.';
            } else {
                // Remove assignment
                $db->query(
                    "DELETE FROM clan_pricing_plans WHERE clan_id = ? AND pricing_plan_id = ?",
                    [$clanId, $planId]
                );
                $success = 'Plan removed from clan successfully.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred while removing the plan from clan. Please try again.';
            error_log("Plan removal error: " . $e->getMessage());
        }
    }
}

// Load plan data for editing
$editingPlan = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $planId = intval($_GET['edit']);
    $editingPlan = $db->fetchOne("SELECT * FROM pricing_plans WHERE id = ?", [$planId]);
    if ($editingPlan) {
        $formData = [
            'name' => $editingPlan['name'],
            'description' => $editingPlan['description'],
            'price' => $editingPlan['price'],
            'price_per_user' => $editingPlan['price_per_user'],
            'is_free' => $editingPlan['is_free'],
            'is_per_user' => $editingPlan['is_per_user'],
            'duration_days' => $editingPlan['duration_days'],
            'features' => $editingPlan['features']
        ];
    }
}

// Get all pricing plans
$pricingPlans = $db->fetchAll("SELECT * FROM pricing_plans ORDER BY is_free DESC, price ASC");

// Get all clans for plan assignment
$clans = $db->fetchAll("SELECT id, name FROM clans ORDER BY name");

// Get summary statistics
$totalPlans = count($pricingPlans);
$freePlans = count(array_filter($pricingPlans, function($plan) { return $plan['is_free']; }));
$paidPlans = $totalPlans - $freePlans;
$perUserPlans = count(array_filter($pricingPlans, function($plan) { return $plan['is_per_user']; }));

// Get total clans using plans
$totalClanAssignments = $db->fetchOne("SELECT COUNT(*) as count FROM clan_pricing_plans")['count'] ?? 0;

// Include header
include_once '../includes/header.php';

// Include sidebar
include_once '../includes/sidebar.php';
?>

<!-- ENHANCED PRICING PLANS STYLING -->
<style>
/* Pricing Plans Grid Layout */
.pricing-plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

/* Individual Pricing Plan Card */
.pricing-plan-card {
    background: var(--bg-card);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    transition: var(--theme-transition);
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.pricing-plan-card:hover {
    box-shadow: var(--shadow);
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.12);
}

/* Plan Header */
.pricing-plan-header {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: var(--bg-card);
}

.pricing-plan-title h5 {
    margin: 0 0 0.5rem 0;
    color: var(--text-primary);
    font-weight: var(--font-weight-semibold);
    font-size: 1.25rem;
}

.pricing-plan-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    font-weight: var(--font-weight-medium);
    border-radius: var(--border-radius-full);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.pricing-plan-badge.free {
    background: rgba(var(--success-rgb), 0.1);
    color: var(--success);
}

.pricing-plan-badge.per-user {
    background: rgba(var(--warning-rgb), 0.1);
    color: var(--warning);
}

.pricing-plan-badge.fixed {
    background: rgba(var(--primary-rgb), 0.1);
    color: var(--primary);
}

/* Plan Price Display */
.pricing-plan-price {
    padding: 1.5rem;
    text-align: center;
    background: var(--bg-subtle);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.price-display {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 0.25rem;
}

.price-currency {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: var(--font-weight-medium);
}

.price-amount {
    font-size: 2.5rem;
    font-weight: var(--font-weight-bold);
    color: var(--text-primary);
    line-height: 1;
}

.price-period {
    font-size: 0.875rem;
    color: var(--text-secondary);
    font-weight: var(--font-weight-medium);
}

/* Plan Description */
.pricing-plan-description {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.pricing-plan-description p {
    margin: 0;
    color: var(--text-secondary);
    font-size: 0.875rem;
    line-height: 1.5;
}

/* Plan Features */
.pricing-plan-features {
    padding: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    flex: 1;
}

.pricing-plan-features h6 {
    margin: 0 0 1rem 0;
    color: var(--text-primary);
    font-weight: var(--font-weight-semibold);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.features-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.features-list li {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 0.875rem;
    line-height: 1.5;
}

.features-list li:last-child {
    margin-bottom: 0;
}

.features-list li i {
    color: var(--success);
    font-size: 0.75rem;
    margin-top: 0.25rem;
    flex-shrink: 0;
}

.features-list li span {
    color: var(--text-primary);
    flex: 1;
}

/* Plan Stats */
.pricing-plan-stats {
    padding: 1rem 1.5rem;
    background: var(--bg-subtle);
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-secondary);
    font-weight: var(--font-weight-medium);
}

.stat-item i {
    color: var(--text-muted);
    font-size: 0.75rem;
    width: 12px;
    text-align: center;
}

/* Enhanced Dropdown Styling for Pricing Plans */
.pricing-plan-actions .dropdown {
    position: relative;
}

.pricing-plan-actions .dropdown-toggle {
    border: none !important;
    background: transparent !important;
    color: var(--text-secondary) !important;
    padding: 0.5rem !important;
    border-radius: var(--border-radius) !important;
    transition: all 0.3s ease;
}

.pricing-plan-actions .dropdown-toggle:hover {
    background: var(--bg-hover) !important;
    color: var(--text-primary) !important;
}

.pricing-plan-actions .dropdown-toggle:focus {
    background: var(--primary) !important;
    color: white !important;
    box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25) !important;
}

.pricing-plan-actions .dropdown-toggle::after {
    display: none !important;
}

/* Enhanced Dropdown Menu Positioning */
.pricing-plan-actions .dropdown-menu {
    position: absolute !important;
    top: 100% !important;
    right: 0 !important;
    left: auto !important;
    z-index: 1050 !important;
    min-width: 180px !important;
    padding: 0.5rem 0 !important;
    margin: 0.125rem 0 0 !important;
    background: var(--bg-card) !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: var(--border-radius) !important;
    box-shadow: var(--shadow-lg) !important;
    transform: translateY(0) !important;
}

/* Ensure dropdown stays within viewport */
.pricing-plan-actions .dropdown-menu[data-bs-popper] {
    right: 0 !important;
    left: auto !important;
}

/* Enhanced dropdown items */
.pricing-plan-actions .dropdown-item {
    display: flex !important;
    align-items: center !important;
    width: 100% !important;
    padding: 0.5rem 0.75rem !important;
    clear: both !important;
    font-weight: 400 !important;
    color: var(--text-primary) !important;
    text-align: inherit !important;
    white-space: nowrap !important;
    background-color: transparent !important;
    border: 0 !important;
    text-decoration: none !important;
    transition: all 0.3s ease !important;
    cursor: pointer !important;
    font-size: 0.875rem !important;
}

.pricing-plan-actions .dropdown-item i {
    width: 16px !important;
    margin-right: 0.75rem !important;
    font-size: 0.875rem !important;
    color: var(--text-secondary) !important;
    transition: color 0.3s ease !important;
}

.pricing-plan-actions .dropdown-item:hover,
.pricing-plan-actions .dropdown-item:focus {
    background-color: var(--bg-hover) !important;
    color: var(--text-primary) !important;
    text-decoration: none !important;
}

.pricing-plan-actions .dropdown-item:hover i,
.pricing-plan-actions .dropdown-item:focus i {
    color: var(--primary) !important;
}

.pricing-plan-actions .dropdown-item-danger {
    color: var(--danger) !important;
}

.pricing-plan-actions .dropdown-item-danger:hover {
    background-color: rgba(var(--danger-rgb), 0.1) !important;
    color: var(--danger) !important;
}

.pricing-plan-actions .dropdown-item-danger i {
    color: var(--danger) !important;
}

.pricing-plan-actions .dropdown-divider {
    height: 0 !important;
    margin: 0.5rem 0 !important;
    overflow: hidden !important;
    border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
}

/* Empty State Styling */
.empty-state {
    background: var(--bg-card);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: var(--border-radius);
    margin-top: 1rem;
}

/* Mobile Responsive Design */
@media (max-width: 768px) {
    .pricing-plans-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .pricing-plan-card {
        margin: 0;
    }
    
    .pricing-plan-header {
        padding: 1rem;
    }
    
    .pricing-plan-price {
        padding: 1rem;
    }
    
    .pricing-plan-features {
        padding: 1rem;
    }
    
    .pricing-plan-stats {
        padding: 0.75rem 1rem;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .stat-item {
        justify-content: center;
    }
    
    .price-amount {
        font-size: 2rem;
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
}

@media (max-width: 576px) {
    .pricing-plan-title h5 {
        font-size: 1.125rem;
    }
    
    .price-amount {
        font-size: 1.75rem;
    }
    
    .features-list li {
        font-size: 0.8125rem;
    }
    
    .stat-item {
        font-size: 0.6875rem;
    }
}

/* Animation for plan cards */
.pricing-plan-card {
    animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Focus states for accessibility */
.pricing-plan-card:focus-within {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
}

/* Loading state for actions */
.pricing-plan-actions .loading {
    opacity: 0.6;
    pointer-events: none;
}

.pricing-plan-actions .loading i {
    animation: spin 1s linear infinite;
}

/* Status Display Styling for View Assignments Modal */
.status-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-dot.active {
    background-color: #00a76f; /* Green for active */
}

.status-dot.available {
    background-color: #6b7280; /* Gray for available */
}

.status-text {
    color: #00a76f;
    font-size: 0.875rem;
    font-weight: var(--font-weight-medium);
}
.table-status span {
    color: var(--success);
}
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>
<!-- ENHANCED DASHER UI CONTENT AREA -->
<div class="content">
    <!-- Enhanced Content Header -->
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="content-title">Pricing Plans</h1>
                <nav class="content-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Pricing Plans</li>
                    </ol>
                </nav>
            </div>
            <div class="content-actions">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                    <i class="fas fa-plus-circle"></i>
                    <span>Add New Plan</span>
                </button>
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
                    <i class="fas fa-tags"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Total Plans</h3>
                    <p class="improved-stats-value"><?php echo number_format($totalPlans); ?></p>
                </div>
            </div>
            <div class="improved-stats-change">
                <span>Active pricing plans</span>
            </div>
        </div>

        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon success">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Free Plans</h3>
                    <p class="improved-stats-value"><?php echo number_format($freePlans); ?></p>
                </div>
            </div>
            <div class="improved-stats-change positive">
                <span><?php echo $totalPlans > 0 ? round(($freePlans / $totalPlans) * 100) : 0; ?>% of total</span>
            </div>
        </div>

        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon warning">
                    <i class="fas fa-naira-sign"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Paid Plans</h3>
                    <p class="improved-stats-value"><?php echo number_format($paidPlans); ?></p>
                </div>
            </div>
            <div class="improved-stats-change">
                <span>Revenue generating</span>
            </div>
        </div>

        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon info">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Assignments</h3>
                    <p class="improved-stats-value"><?php echo number_format($totalClanAssignments); ?></p>
                </div>
            </div>
            <div class="improved-stats-change">
                <span>Clan plan assignments</span>
            </div>
        </div>
    </div>

    <!-- Enhanced Pricing Plans Grid -->
    <div class="table-container">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="card-title">All Pricing Plans</h2>
            </div>
        </div>

        <?php if (empty($pricingPlans)): ?>
            <div class="empty-state">
                <div style="text-align: center; padding: 4rem 2rem; color: var(--text-muted);">
                    <i class="fas fa-tags" style="font-size: 3rem; margin-bottom: 1.5rem; opacity: 0.5;"></i>
                    <h3 style="color: var(--text-secondary); margin-bottom: 1rem;">No Pricing Plans Found</h3>
                    <p style="margin-bottom: 2rem;">Create your first pricing plan to get started with monetization.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
                        <i class="fas fa-plus-circle"></i>
                        Create First Plan
                    </button>
                </div>
            </div>
        <?php else: ?>
            <!-- Enhanced Pricing Plans Cards Grid -->
            <div class="pricing-plans-grid">
                <?php foreach ($pricingPlans as $plan): ?>
                    <div class="pricing-plan-card">
                        <!-- Plan Header -->
                        <div class="pricing-plan-header">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="pricing-plan-title">
                                    <h5><?php echo htmlspecialchars($plan['name']); ?></h5>
                                    <?php if ($plan['is_free']): ?>
                                        <span class="pricing-plan-badge free">Free Plan</span>
                                    <?php elseif ($plan['is_per_user']): ?>
                                        <span class="pricing-plan-badge per-user">Per User</span>
                                    <?php else: ?>
                                        <span class="pricing-plan-badge fixed">Fixed Price</span>
                                    <?php endif; ?>
                                </div>
                                <div class="pricing-plan-actions">
                                    <!-- Enhanced Dropdown Action Menu -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="actionsDropdown<?php echo $plan['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actionsDropdown<?php echo $plan['id']; ?>">
                                            <li>
                                                <a class="dropdown-item" href="?edit=<?php echo $plan['id']; ?>">
                                                    <i class="fas fa-edit"></i>
                                                    <span>Edit Plan</span>
                                                </a>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#assignPlanModal<?php echo $plan['id']; ?>">
                                                    <i class="fas fa-link"></i>
                                                    <span>Assign to Clan</span>
                                                </button>
                                            </li>
                                            <li>
                                                <button type="button" class="dropdown-item"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewAssignmentsModal<?php echo $plan['id']; ?>">
                                                    <i class="fas fa-eye"></i>
                                                    <span>View Assignments</span>
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="button" 
                                                        class="dropdown-item dropdown-item-danger" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deletePlanModal<?php echo $plan['id']; ?>">
                                                    <i class="fas fa-trash-alt"></i>
                                                    <span>Delete Plan</span>
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Plan Price -->
                        <div class="pricing-plan-price">
                            <?php if ($plan['is_free']): ?>
                                <div class="price-display">
                                    <span class="price-amount">Free</span>
                                </div>
                            <?php elseif ($plan['is_per_user']): ?>
                                <div class="price-display">
                                    <span class="price-currency">₦</span>
                                    <span class="price-amount"><?php echo number_format($plan['price_per_user'], 2); ?></span>
                                    <span class="price-period">/ user / <?php echo $plan['duration_days']; ?> days</span>
                                </div>
                            <?php else: ?>
                                <div class="price-display">
                                    <span class="price-currency">₦</span>
                                    <span class="price-amount"><?php echo number_format($plan['price'], 2); ?></span>
                                    <span class="price-period">/ <?php echo $plan['duration_days']; ?> days</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Plan Description -->
                        <?php if (!empty($plan['description'])): ?>
                            <div class="pricing-plan-description">
                                <p><?php echo htmlspecialchars($plan['description']); ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Plan Features -->
                        <?php if (!empty($plan['features'])): ?>
                            <div class="pricing-plan-features">
                                <h6>Features</h6>
                                <ul class="features-list">
                                    <?php
                                    $features = explode("\n", $plan['features']);
                                    foreach ($features as $feature):
                                        if (trim($feature)):
                                    ?>
                                        <li>
                                            <i class="fas fa-check"></i>
                                            <span><?php echo htmlspecialchars(trim($feature)); ?></span>
                                        </li>
                                    <?php
                                        endif;
                                    endforeach;
                                    ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Plan Stats -->
                        <div class="pricing-plan-stats">
                            <?php
                            // Get count of clans using this plan
                            $clanCount = $db->fetchOne(
                                "SELECT COUNT(*) as count FROM clans WHERE pricing_plan_id = ?",
                                [$plan['id']]
                            )['count'];

                            // Get count of clans this plan is assigned to
                            $assignedCount = $db->fetchOne(
                                "SELECT COUNT(*) as count FROM clan_pricing_plans WHERE pricing_plan_id = ?",
                                [$plan['id']]
                            )['count'];
                            ?>
                            <div class="stat-item">
                                <i class="fas fa-users"></i>
                                <span><?php echo $clanCount; ?> clan<?php echo $clanCount != 1 ? 's' : ''; ?> using</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-link"></i>
                                <span><?php echo $assignedCount; ?> assignment<?php echo $assignedCount != 1 ? 's' : ''; ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-clock"></i>
                                <span><?php echo $plan['duration_days']; ?> days</span>
                            </div>
                        </div>


                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ALL PLAN MODALS - MOVED OUTSIDE CARDS TO PREVENT POSITIONING ISSUES -->
<?php foreach ($pricingPlans as $plan): ?>
    <!-- Delete Plan Modal -->
    <div class="modal fade" id="deletePlanModal<?php echo $plan['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Plan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the plan <strong><?php echo htmlspecialchars($plan['name']); ?></strong>?</p>
                    <?php
                    // Check if any clans are using this plan
                    $clanCount = $db->fetchOne(
                        "SELECT COUNT(*) as count FROM clans WHERE pricing_plan_id = ?",
                        [$plan['id']]
                    )['count'];

                    // Check if any payments or transactions reference this plan
                    $paymentCount = $db->fetchOne(
                        "SELECT COUNT(*) as count FROM payments WHERE pricing_plan_id = ?",
                        [$plan['id']]
                    )['count'];

                    $transactionCount = $db->fetchOne(
                        "SELECT COUNT(*) as count FROM payment_transactions WHERE pricing_plan_id = ?",
                        [$plan['id']]
                    )['count'] ?? 0;

                    $totalRecords = $paymentCount + $transactionCount;

                    if ($clanCount > 0):
                    ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This plan is currently used by <?php echo $clanCount; ?> clan<?php echo $clanCount != 1 ? 's' : ''; ?>.
                            You need to reassign these clans to another plan before deleting this one.
                        </div>
                    <?php elseif ($totalRecords > 0): ?>
                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            This plan has <?php echo $totalRecords; ?> record<?php echo $totalRecords != 1 ? 's' : ''; ?> associated with it.
                            You'll need to migrate these records before deleting.
                        </div>
                    <?php else: ?>
                        <p class="mb-0">This action cannot be undone.</p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php if ($clanCount > 0): ?>
                        <button type="button" class="btn btn-danger" disabled>
                            Delete Plan
                        </button>
                    <?php elseif ($totalRecords > 0): ?>
                        <a href="<?php echo BASE_URL; ?>payments/migrate-plan.php?plan_id=<?php echo $plan['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-exchange-alt me-1"></i> Migrate Records
                        </a>
                    <?php else: ?>
                        <form method="post" action="">
                            <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                            <button type="submit" name="delete_plan" class="btn btn-danger">
                                Delete Plan
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Plan Modal -->
    <div class="modal fade" id="assignPlanModal<?php echo $plan['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign Plan to Clan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" action="">
                    <div class="modal-body">
                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">

                        <div class="mb-3">
                            <label for="clan_id_<?php echo $plan['id']; ?>" class="form-label">Select Clan</label>
                            <select class="form-select" id="clan_id_<?php echo $plan['id']; ?>" name="clan_id" required>
                                <option value="">-- Select Clan --</option>
                                <?php foreach ($clans as $clan): ?>
                                    <option value="<?php echo $clan['id']; ?>"><?php echo htmlspecialchars($clan['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="set_as_current_modal_<?php echo $plan['id']; ?>" name="set_as_current" value="1">
                            <label class="form-check-label" for="set_as_current_modal_<?php echo $plan['id']; ?>">
                                Set as current plan for this clan
                            </label>
                            <div class="form-text">
                                If checked, this plan will become the clan's current plan.
                                <?php if ($plan['is_free']): ?>
                                    The clan will be set to 'free' status.
                                <?php else: ?>
                                    The clan will need to make a payment to activate the plan.
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will make the plan "<?php echo htmlspecialchars($plan['name']); ?>" available as an option for the selected clan. Any previously assigned plans will be removed.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_to_clan" class="btn btn-primary">Assign Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Assignments Modal -->
    <div class="modal fade" id="viewAssignmentsModal<?php echo $plan['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Plan Assignments</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6>Clans assigned to "<?php echo htmlspecialchars($plan['name']); ?>"</h6>

                    <?php
                    // Get clans this plan is assigned to
                    $assignedClans = $db->fetchAll(
                        "SELECT c.id, c.name, c.pricing_plan_id 
                         FROM clans c
                         JOIN clan_pricing_plans cpp ON c.id = cpp.clan_id
                         WHERE cpp.pricing_plan_id = ?
                         ORDER BY c.name",
                        [$plan['id']]
                    );
                    ?>

                    <?php if (empty($assignedClans)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This plan is not assigned to any clan yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Clan Name</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignedClans as $assignedClan): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assignedClan['name']); ?></td>
                                            <td>
                                                <?php if ($assignedClan['pricing_plan_id'] == $plan['id']): ?>
                                                    <div class="table-status">
                                                        <div class="table-status-dot status-active"></div>
                                                        <span>Currently Using</span>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="table-status">
                                                        <div class="table-status-dot status-active"></div>
                                                        <span>Available</span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <form method="post" action="" class="d-inline">
                                                    <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                                                    <input type="hidden" name="clan_id" value="<?php echo $assignedClan['id']; ?>">
                                                    <button type="submit" name="remove_from_clan" class="btn btn-sm btn-outline-danger" <?php echo $assignedClan['pricing_plan_id'] == $plan['id'] ? 'disabled' : ''; ?>>
                                                        <i class="fas fa-unlink me-1"></i> Remove
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<!-- Add/Edit Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo $editingPlan ? 'Edit Plan' : 'Add New Plan'; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="">
                <?php if ($editingPlan): ?>
                    <input type="hidden" name="plan_id" value="<?php echo $editingPlan['id']; ?>">
                <?php endif; ?>

                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Plan Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="duration_days" class="form-label">Duration (days) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="duration_days" name="duration_days" min="1" value="<?php echo $formData['duration_days']; ?>" required>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="is_free" name="is_free" <?php echo $formData['is_free'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_free">
                            Free Plan
                        </label>
                        <div class="form-text">Enabling this option makes the plan free for clans.</div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="is_per_user" name="is_per_user" <?php echo $formData['is_per_user'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_per_user">
                            Per-User Pricing
                        </label>
                        <div class="form-text">Enabling this charges clans based on their number of users.</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div id="price-container">
                                <label for="price" class="form-label">Plan Price (₦) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price" name="price" min="0" step="0.01" value="<?php echo $formData['price']; ?>" <?php echo $formData['is_free'] || $formData['is_per_user'] ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div id="price-per-user-container">
                                <label for="price_per_user" class="form-label">Price Per User (₦) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="price_per_user" name="price_per_user" min="0" step="0.01" value="<?php echo $formData['price_per_user']; ?>" <?php echo $formData['is_free'] || !$formData['is_per_user'] ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($formData['description']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="features" class="form-label">Features (one per line)</label>
                        <textarea class="form-control" id="features" name="features" rows="5" placeholder="Access to basic features
Unlimited access codes
Email support"><?php echo htmlspecialchars($formData['features']); ?></textarea>
                        <div class="form-text">Enter one feature per line. These will be displayed as bullet points.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_plan" class="btn btn-primary">Save Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing Enhanced Pricing Plans with Dasher UI...');
    
    // Auto-open edit modal if editing
    <?php if ($editingPlan): ?>
        const addPlanModal = new bootstrap.Modal(document.getElementById('addPlanModal'));
        addPlanModal.show();
    <?php endif; ?>

    // Initialize pricing form controls
    initializePricingControls();
    
    // Initialize Bootstrap dropdowns properly
    initializeBootstrapDropdowns();
    
    // Initialize modal handling
    initializeModalHandling();
    
    // Initialize responsive features
    initializeResponsiveFeatures();
    
    console.log('Enhanced Pricing Plans initialized successfully');
});

function initializePricingControls() {
    const isFreeCheckbox = document.getElementById('is_free');
    const isPerUserCheckbox = document.getElementById('is_per_user');
    const priceInput = document.getElementById('price');
    const pricePerUserInput = document.getElementById('price_per_user');

    function updatePriceFields() {
        if (isFreeCheckbox.checked) {
            priceInput.value = '0.00';
            priceInput.disabled = true;
            pricePerUserInput.value = '0.00';
            pricePerUserInput.disabled = true;
            isPerUserCheckbox.checked = false;
            isPerUserCheckbox.disabled = true;
        } else {
            isPerUserCheckbox.disabled = false;

            if (isPerUserCheckbox.checked) {
                priceInput.value = '0.00';
                priceInput.disabled = true;
                pricePerUserInput.disabled = false;
            } else {
                priceInput.disabled = false;
                pricePerUserInput.value = '0.00';
                pricePerUserInput.disabled = true;
            }
        }
    }

    if (isFreeCheckbox && isPerUserCheckbox) {
        isFreeCheckbox.addEventListener('change', updatePriceFields);
        isPerUserCheckbox.addEventListener('change', updatePriceFields);

        // Initial setup
        updatePriceFields();
    }
}

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
        // Create Bootstrap dropdown instance
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

function initializeModalHandling() {
    // Enhanced modal handling with better UX
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            // Focus first input when modal opens
            const firstInput = modal.querySelector('input, select, textarea');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 100);
            }
        });
        
        modal.addEventListener('hidden.bs.modal', function() {
            // Clear any custom positioning
            const dropdowns = modal.querySelectorAll('.dropdown-menu');
            dropdowns.forEach(dropdown => {
                dropdown.style.position = '';
                dropdown.style.left = '';
                dropdown.style.top = '';
                dropdown.style.zIndex = '';
                dropdown.classList.remove('show');
            });
        });
    });
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

// Helper function for enhanced user feedback
function showActionFeedback(element, success = true) {
    const icon = element.querySelector('i');
    if (icon) {
        const originalClass = icon.className;
        icon.className = success ? 'fas fa-check' : 'fas fa-times';
        icon.style.color = success ? 'var(--success)' : 'var(--danger)';
        
        setTimeout(() => {
            icon.className = originalClass;
            icon.style.color = '';
        }, 1500);
    }
}

// Form validation enhancement
function enhanceFormValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                // Focus first invalid field
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });
}

// Initialize form validation
enhanceFormValidation();
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>