<?php

/**
 * Gate Wey Access Management System
 * Payment Status Update Page
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/Payment.php';
require_once 'payment_constants.php';

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

// Only super admin can change payment statuses
if (!$currentUser->isSuperAdmin()) {
    header('Location: ' . BASE_URL . 'dashboard/');
    exit;
}

// Get database instance
$db = Database::getInstance();

// Check if payment ID and action are provided
if (!isset($_GET['id']) || !isset($_GET['action'])) {
    header('Location: ' . BASE_URL . 'payments/');
    exit;
}

// Get payment ID and action
$paymentId = (int) $_GET['id'];
$action = $_GET['action'];

// Valid actions
$validActions = ['approve', 'reject', 'refund'];

if (!in_array($action, $validActions)) {
    header('Location: ' . BASE_URL . 'payments/');
    exit;
}

// Initialize variables
$error = '';
$success = '';

// Get payment details - using error reporting to debug
try {
    $payment = $db->fetchOne(
        "SELECT p.*, c.id as clan_id, c.name as clan_name, c.admin_id, pp.name as plan_name, 
        pp.duration_days, pp.is_free, pp.is_per_user, pp.price_per_user, p.user_count,
        (SELECT COUNT(*) FROM users WHERE clan_id = c.id) as current_user_count
        FROM payments p
        JOIN clans c ON p.clan_id = c.id
        JOIN pricing_plans pp ON p.pricing_plan_id = pp.id
        WHERE p.id = ?",
        [$paymentId]
    );
    
    // If payment not found, set error
    if (!$payment) {
        $error = 'Payment not found. ID: ' . $paymentId;
    }
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_action']) && !$error) {
    // Start a database transaction
    $db->beginTransaction();

    try {
        // Process based on action
        if ($action === 'approve') {
            // Check if payment is pending
            if ($payment['status'] !== 'pending') {
                throw new Exception('This payment cannot be approved because it is not in pending status.');
            }

            // Update payment status to completed
            $db->query(
                "UPDATE payments SET status = 'completed', updated_at = NOW() WHERE id = ?",
                [$paymentId]
            );

            // Calculate next payment date based on the plan duration
            $nextPaymentDate = date('Y-m-d', strtotime('+' . $payment['duration_days'] . ' days'));

            // Get license count from payment
            $licenseCount = $payment['license_count'] ?? 1;

            // Update clan payment status and increment available licenses (fix here - removed the array for parameters)
            $db->query(
                "UPDATE clans SET 
                payment_status = 'active', 
                pricing_plan_id = ?, 
                next_payment_date = ?, 
                available_licenses = available_licenses + ?,
                updated_at = NOW() 
                WHERE id = ?",
                [$payment['pricing_plan_id'], $nextPaymentDate, $licenseCount, $payment['clan_id']]
            );

            // Create success notification for clan admin
            if ($payment['admin_id']) {
                // Get the notification template
                $template = $db->fetchOne(
                    "SELECT * FROM notification_templates WHERE type = 'payment_success' AND is_active = 1"
                );

                if ($template) {
                    // Replace placeholders in template
                    $title = $template['title'];
                    $message = str_replace(
                        ['{amount}', '{plan_name}'],
                        [CURRENCY_SYMBOL . number_format($payment['amount'], 2), $payment['plan_name']],
                        $template['message']
                    );

                    // Create notification for clan admin
                    $db->query(
                        "INSERT INTO notifications (user_id, clan_id, title, message, type, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [
                            $payment['admin_id'],
                            $payment['clan_id'],
                            $title,
                            $message,
                            'payment_success',
                            $paymentId,
                            'payment'
                        ]
                    );
                }
            }

            $success = 'Payment #' . $paymentId . ' has been successfully approved.';
        } elseif ($action === 'reject') {
            // Check if payment is pending
            if ($payment['status'] !== 'pending') {
                throw new Exception('This payment cannot be rejected because it is not in pending status.');
            }

            // Update payment status to failed
            $db->query(
                "UPDATE payments SET status = 'failed', updated_at = NOW() WHERE id = ?",
                [$paymentId]
            );

            // Create failed notification for clan admin
            if ($payment['admin_id']) {
                // Get the notification template
                $template = $db->fetchOne(
                    "SELECT * FROM notification_templates WHERE type = 'payment_failed' AND is_active = 1"
                );

                if ($template) {
                    // Replace placeholders in template
                    $title = $template['title'];
                    $message = str_replace(
                        ['{amount}', '{plan_name}'],
                        [CURRENCY_SYMBOL . number_format($payment['amount'], 2), $payment['plan_name']],
                        $template['message']
                    );

                    // Create notification for clan admin
                    $db->query(
                        "INSERT INTO notifications (user_id, clan_id, title, message, type, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [
                            $payment['admin_id'],
                            $payment['clan_id'],
                            $title,
                            $message,
                            'payment_failed',
                            $paymentId,
                            'payment'
                        ]
                    );
                }
            }

            $success = 'Payment #' . $paymentId . ' has been rejected.';
        } elseif ($action === 'refund') {
            // Check if payment is completed
            if ($payment['status'] !== 'completed') {
                throw new Exception('This payment cannot be refunded because it is not in completed status.');
            }

            // Update payment status to refunded
            $db->query(
                "UPDATE payments SET status = 'refunded', updated_at = NOW() WHERE id = ?",
                [$paymentId]
            );

            // Check if this was the clan's latest payment
            $latestPayment = $db->fetchOne(
                "SELECT id FROM payments WHERE clan_id = ? AND status = 'completed' ORDER BY payment_date DESC LIMIT 1",
                [$payment['clan_id']]
            );

            // If this was the latest payment, update clan payment status
            if (!$latestPayment || $latestPayment['id'] == $paymentId) {
                // Update clan payment status
                $newStatus = $payment['is_free'] ? 'free' : 'inactive';

                $db->query(
                    "UPDATE clans SET payment_status = ?, next_payment_date = NULL, updated_at = NOW() WHERE id = ?",
                    [$newStatus, $payment['clan_id']]
                );
            }

            // Create notification for clan admin about the refund
            if ($payment['admin_id']) {
                // Create custom notification (no template for refunds)
                $db->query(
                    "INSERT INTO notifications (user_id, clan_id, title, message, type, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $payment['admin_id'],
                        $payment['clan_id'],
                        'Payment Refunded',
                        'Your payment of ' . CURRENCY_SYMBOL . number_format($payment['amount'], 2) . ' for the ' . $payment['plan_name'] . ' plan has been refunded.',
                        'payment_refund',
                        $paymentId,
                        'payment'
                    ]
                );
            }

            $success = 'Payment #' . $paymentId . ' has been refunded.';
        }

        // Commit the transaction
        $db->commit();
    } catch (Exception $e) {
        // Roll back the transaction on error
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// Set page title based on action
$pageTitle = ucfirst($action) . ' Payment';

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
                    <h3 class="page-title"><?php echo $pageTitle; ?></h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>payments/">Payments</a></li>
                        <li class="breadcrumb-item active"><?php echo ucfirst($action); ?> Payment</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="<?php echo BASE_URL; ?>payments/" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i> Back to Payments
                    </a>
                </div>
            </div>
        </div>

        <!-- Result Message -->
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">Error!</h4>
                <p><?php echo $error; ?></p>
                <hr>
                <p class="mb-0">
                    <a href="<?php echo BASE_URL; ?>payments/" class="alert-link">Return to payment management</a>
                </p>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                <h4 class="alert-heading">Success!</h4>
                <p><?php echo $success; ?></p>
                <hr>
                <p class="mb-0">
                    <a href="<?php echo BASE_URL; ?>payments/" class="alert-link">Return to payment management</a>
                </p>
            </div>
        <?php endif; ?>

        <!-- Payment Details -->
        <?php if (isset($payment) && is_array($payment) && !$success): ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Payment ID:</th>
                                    <td>#<?php echo $payment['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Clan:</th>
                                    <td><?php echo htmlspecialchars($payment['clan_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Amount:</th>
                                    <td><?php echo CURRENCY_SYMBOL; ?><?php echo number_format($payment['amount'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Plan:</th>
                                    <td>
                                        <?php echo htmlspecialchars($payment['plan_name']); ?>
                                        <?php if ($payment['is_per_user']): ?>
                                            <span class="badge bg-info">Per-User Plan</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Duration:</th>
                                    <td><?php echo $payment['duration_days']; ?> days</td>
                                </tr>
                                <?php if ($payment['is_per_user']): ?>
                                    <tr>
                                        <th>Users:</th>
                                        <td>
                                            <?php echo $payment['user_count']; ?> users ×
                                            <?php echo CURRENCY_SYMBOL; ?><?php echo number_format($payment['price_per_user'], 2); ?> per user
                                            <br>
                                            <small class="text-muted">Current user count: <?php echo $payment['current_user_count']; ?></small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <?php if ($payment['status'] === 'pending'): ?>
                                            <span class="badge bg-warning">Pending</span>
                                        <?php elseif ($payment['status'] === 'completed'): ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php elseif ($payment['status'] === 'failed'): ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php elseif ($payment['status'] === 'refunded'): ?>
                                            <span class="badge bg-info">Refunded</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Payment Method:</th>
                                    <td>
                                        <?php if ($payment['payment_method'] === 'paystack'): ?>
                                            <i class="fas fa-credit-card me-1"></i> Paystack
                                        <?php elseif ($payment['payment_method'] === 'bank_transfer'): ?>
                                            <i class="fas fa-university me-1"></i> Bank Transfer
                                        <?php elseif ($payment['payment_method'] === 'ussd'): ?>
                                            <i class="fas fa-mobile-alt me-1"></i> USSD
                                        <?php else: ?>
                                            <?php echo ucfirst(htmlspecialchars($payment['payment_method'])); ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Transaction ID:</th>
                                    <td>
                                        <?php echo $payment['transaction_id'] ? htmlspecialchars($payment['transaction_id']) : '<span class="text-muted">N/A</span>'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Payment Date:</th>
                                    <td><?php echo date('F j, Y, g:i a', strtotime($payment['payment_date'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Period:</th>
                                    <td>
                                        <?php
                                        if (isset($payment['period_start']) && isset($payment['period_end']) && $payment['period_start'] && $payment['period_end']) {
                                            echo date('M d, Y', strtotime($payment['period_start'])) . ' - ' .
                                                date('M d, Y', strtotime($payment['period_end']));
                                        } else {
                                            echo '<span class="text-muted">N/A</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Form -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Confirm <?php echo ucfirst($action); ?></h5>
                </div>
                <div class="card-body">
                    <?php if ($action === 'approve'): ?>
                        <p>Are you sure you want to approve this payment? This will:</p>
                        <ul>
                            <li>Change the payment status to "Completed"</li>
                            <li>Set the clan's payment status to "Active"</li>
                            <li>Update the clan's next payment date</li>
                            <li>Send a notification to the clan administrator</li>
                        </ul>
                    <?php elseif ($action === 'reject'): ?>
                        <p>Are you sure you want to reject this payment? This will:</p>
                        <ul>
                            <li>Change the payment status to "Failed"</li>
                            <li>Send a notification to the clan administrator</li>
                        </ul>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> This action will not change the clan's current payment status.
                        </div>
                    <?php elseif ($action === 'refund'): ?>
                        <p>Are you sure you want to refund this payment? This will:</p>
                        <ul>
                            <li>Change the payment status to "Refunded"</li>
                            <?php if (isset($payment['is_free']) && $payment['is_free']): ?>
                                <li>Set the clan's payment status to "Free"</li>
                            <?php else: ?>
                                <li>Set the clan's payment status to "Inactive" (if this was their latest payment)</li>
                            <?php endif; ?>
                            <li>Remove the clan's next payment date (if this was their latest payment)</li>
                            <li>Send a notification to the clan administrator</li>
                        </ul>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i> This action may affect the clan's access to paid features. Please ensure you have processed the actual refund through your payment processor before confirming.
                        </div>
                    <?php endif; ?>

                    <form method="post" action="" class="mt-4">
                        <div class="row">
                            <div class="col-md-6 offset-md-3">
                                <div class="d-grid gap-2">
                                    <a href="<?php echo BASE_URL; ?>payments/" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-2"></i> Cancel
                                    </a>
                                    <button type="submit" name="confirm_action" class="btn btn-<?php echo $action === 'approve' ? 'success' : ($action === 'reject' ? 'danger' : 'warning'); ?>">
                                        <i class="fas fa-<?php echo $action === 'approve' ? 'check' : ($action === 'reject' ? 'times' : 'undo'); ?> me-2"></i>
                                        Confirm <?php echo ucfirst($action); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>