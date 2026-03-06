<?php
/**
 * Gate Wey Access Management System
 * Pricing Plan Migration Tool
 * 
 * This script helps migrate payment records from one plan to another
 * before deleting a plan.
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';

// Check if user is logged in and is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
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

// Get database instance
$db = Database::getInstance();

// Initialize variables
$error = '';
$success = '';
$sourcePlanId = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$targetPlanId = isset($_POST['target_plan_id']) ? (int)$_POST['target_plan_id'] : 0;

// Get source plan details
$sourcePlan = null;
if ($sourcePlanId) {
    $sourcePlan = $db->fetchOne(
        "SELECT * FROM pricing_plans WHERE id = ?", 
        [$sourcePlanId]
    );
    
    if (!$sourcePlan) {
        $error = 'Source plan not found.';
    }
}

// Get available plans (excluding the source plan)
$availablePlans = $db->fetchAll(
    "SELECT * FROM pricing_plans WHERE id != ? ORDER BY is_free DESC, price ASC",
    [$sourcePlanId]
);

// Process migration form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate']) && $sourcePlan && $targetPlanId) {
    try {
        // Start a transaction for consistent updates
        $db->beginTransaction();
        
        // Update payments
        $paymentCount = $db->query(
            "UPDATE payments SET pricing_plan_id = ? WHERE pricing_plan_id = ?",
            [$targetPlanId, $sourcePlanId]
        )->rowCount();
        
        // Update payment_transactions
        $transactionCount = $db->query(
            "UPDATE payment_transactions SET pricing_plan_id = ? WHERE pricing_plan_id = ?",
            [$targetPlanId, $sourcePlanId]
        )->rowCount();
        
        // Commit the transaction
        $db->commit();
        
        $success = "Successfully migrated $paymentCount payment records and $transactionCount transaction records to the new plan.";
        
        // Redirect to the delete page after successful migration
        header("Location: plans.php?migrate_success=1&source_plan=$sourcePlanId&target_plan=$targetPlanId");
        exit;
        
    } catch (Exception $e) {
        // Rollback the transaction on error
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        $error = 'An error occurred during migration: ' . $e->getMessage();
        error_log("Plan migration error: " . $e->getMessage());
    }
}

// Page title
$pageTitle = 'Migrate Plan Records';

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
                    <h3 class="page-title">Migrate Plan Records</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>payments/plans.php">Pricing Plans</a></li>
                        <li class="breadcrumb-item active">Migrate Records</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="<?php echo BASE_URL; ?>payments/plans.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Plans
                    </a>
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
        
        <?php if ($sourcePlan): ?>
            <!-- Migration Form -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Migrate Records from "<?php echo htmlspecialchars($sourcePlan['name']); ?>"</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Count records associated with this plan
                    $paymentCount = $db->fetchOne(
                        "SELECT COUNT(*) as count FROM payments WHERE pricing_plan_id = ?",
                        [$sourcePlanId]
                    )['count'];
                    
                    $transactionCount = $db->fetchOne(
                        "SELECT COUNT(*) as count FROM payment_transactions WHERE pricing_plan_id = ?",
                        [$sourcePlanId]
                    )['count'] ?? 0;
                    
                    $totalCount = $paymentCount + $transactionCount;
                    ?>
                    
                    <?php if ($totalCount === 0): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            No payment records are associated with this plan. You can safely delete it.
                        </div>
                        <div class="text-center mt-4">
                            <a href="<?php echo BASE_URL; ?>payments/plans.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i> Return to Plans
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> This plan has <?php echo $paymentCount; ?> payment record(s) and <?php echo $transactionCount; ?> transaction record(s) associated with it.
                            Before deleting this plan, you must migrate these records to another plan.
                        </div>
                        
                        <form method="post" action="">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <h5>Source Plan</h5>
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo htmlspecialchars($sourcePlan['name']); ?></h6>
                                            <p class="card-text"><?php echo htmlspecialchars($sourcePlan['description'] ?? 'No description'); ?></p>
                                            <ul class="list-group list-group-flush mb-3">
                                                <li class="list-group-item border-0 ps-0">
                                                    <?php if ($sourcePlan['is_free']): ?>
                                                        <i class="fas fa-check text-success me-2"></i> Free Plan
                                                    <?php elseif ($sourcePlan['is_per_user']): ?>
                                                        <i class="fas fa-check text-success me-2"></i> Per-User Plan: ₦<?php echo number_format($sourcePlan['price_per_user'], 2); ?> per user
                                                    <?php else: ?>
                                                        <i class="fas fa-check text-success me-2"></i> Fixed Plan: ₦<?php echo number_format($sourcePlan['price'], 2); ?>
                                                    <?php endif; ?>
                                                </li>
                                                <li class="list-group-item border-0 ps-0">
                                                    <i class="fas fa-check text-success me-2"></i> Duration: <?php echo $sourcePlan['duration_days']; ?> days
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <h5>Target Plan</h5>
                                    <div class="form-group">
                                        <label for="target_plan_id" class="form-label">Select Target Plan</label>
                                        <select class="form-select" id="target_plan_id" name="target_plan_id" required>
                                            <option value="">-- Select a plan --</option>
                                            <?php foreach ($availablePlans as $plan): ?>
                                                <option value="<?php echo $plan['id']; ?>">
                                                    <?php echo htmlspecialchars($plan['name']); ?> - 
                                                    <?php if ($plan['is_free']): ?>
                                                        Free
                                                    <?php elseif ($plan['is_per_user']): ?>
                                                        ₦<?php echo number_format($plan['price_per_user'], 2); ?> per user
                                                    <?php else: ?>
                                                        ₦<?php echo number_format($plan['price'], 2); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">
                                            All payment and transaction records will be associated with this plan instead.
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> This action will update all database records but will not affect any financial transactions that have already occurred.
                            </div>
                            
                            <div class="text-center mt-4">
                                <a href="<?php echo BASE_URL; ?>payments/plans.php" class="btn btn-outline-secondary me-2">Cancel</a>
                                <button type="submit" name="migrate" class="btn btn-primary">
                                    <i class="fas fa-exchange-alt me-2"></i> Migrate Records
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                    <h4>Plan Not Found</h4>
                    <p class="text-muted">The pricing plan you're trying to migrate from was not found.</p>
                    <a href="<?php echo BASE_URL; ?>payments/plans.php" class="btn btn-primary mt-3">
                        <i class="fas fa-arrow-left me-2"></i> Back to Plans
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>