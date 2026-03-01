<?php
/**
 * Create / Edit Due — Admin only
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Due.php';

requireAdmin();

$dueObj = new Due();
$id     = (int)($_GET['id'] ?? 0);
$due    = $id ? $dueObj->getById($id) : null;
$isEdit = (bool)$due;

if ($id && !$due) {
    flashError('Due not found.');
    redirect('payments/dues.php');
}

$pageTitle = $isEdit ? 'Edit Due' : 'Create Due';
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $data = [
        'title'       => sanitize($_POST['title']       ?? ''),
        'description' => sanitize($_POST['description'] ?? ''),
        'amount'      => (float)($_POST['amount']       ?? 0),
        'frequency'   => in_array($_POST['frequency'] ?? '', ['one_off','weekly','monthly','quarterly','yearly'])
                            ? $_POST['frequency'] : 'monthly',
        'due_date'    => sanitize($_POST['due_date']    ?? ''),
        'penalty_fee' => (float)($_POST['penalty_fee']  ?? 0),
    ];
    $assignAll = !empty($_POST['assign_all']);

    if (empty($data['title'])) {
        $error = 'Due title is required.';
    } elseif ($data['amount'] <= 0) {
        $error = 'Amount must be greater than zero.';
    } elseif ($data['penalty_fee'] < 0) {
        $error = 'Penalty fee cannot be negative.';
    } else {
        if ($isEdit) {
            $dueObj->update($id, $data);
            flashSuccess("Due <strong>{$data['title']}</strong> updated.");

            if ($assignAll && $data['due_date']) {
                require_once dirname(__DIR__) . '/classes/Payment.php';
                $payObj = new Payment();
                $count  = $payObj->assignToAll($id, $data['amount'], $data['due_date']);
                if ($count > 0) flashInfo("Assigned to {$count} member(s).");
            }
            redirect('payments/dues.php');
        } else {
            $newId = $dueObj->create($data, $_SESSION['user_id']);
            flashSuccess("Due <strong>{$data['title']}</strong> created.");

            if ($assignAll && $data['due_date']) {
                require_once dirname(__DIR__) . '/classes/Payment.php';
                $payObj = new Payment();
                $count  = $payObj->assignToAll($newId, $data['amount'], $data['due_date']);
                if ($count > 0) flashInfo("Auto-assigned to {$count} active member(s).");
            }
            redirect('payments/dues.php');
        }
    }
}

// Pre-fill from existing due or from POST
$f = [
    'title'       => $_POST['title']       ?? ($due['title']       ?? ''),
    'description' => $_POST['description'] ?? ($due['description'] ?? ''),
    'amount'      => $_POST['amount']      ?? ($due['amount']      ?? ''),
    'frequency'   => $_POST['frequency']   ?? ($due['frequency']   ?? 'monthly'),
    'due_date'    => $_POST['due_date']    ?? ($due['due_date']    ?? ''),
    'penalty_fee' => $_POST['penalty_fee'] ?? ($due['penalty_fee'] ?? '0'),
];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header d-flex justify-content-between flex-wrap gap-3">
    <div>
        <div class="breadcrumb-trail mb-1 text-muted" style="font-size:var(--font-size-sm)">
            <a href="<?php echo BASE_URL; ?>payments/dues.php" class="text-muted text-decoration-none">Manage Dues</a>
            <span class="mx-2">/</span> <?php echo $isEdit ? 'Edit' : 'Create'; ?>
        </div>
        <h1 class="content-title"><?php echo $pageTitle; ?></h1>
        <p class="content-subtitle"><?php echo $isEdit ? 'Update due details.' : 'Set up a new membership due.'; ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>payments/dues.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-7">
        <?php if ($error): ?>
        <div class="alert alert-danger mb-4"><i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h6 class="card-title">
                    <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Due Details
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" novalidate>
                    <?php echo csrfField(); ?>

                    <div class="form-group">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title"
                               value="<?php echo e($f['title']); ?>"
                               placeholder="e.g. Monthly Membership Fee" required>
                    </div>

                    <div class="form-group mt-4">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"
                                  placeholder="Optional description or notes about this due…"><?php echo e($f['description']); ?></textarea>
                    </div>

                    <div class="row mt-4">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Amount (₦) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="amount" step="0.01" min="1"
                                       value="<?php echo e($f['amount']); ?>" placeholder="0.00" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Penalty Fee (₦)</label>
                                <input type="number" class="form-control" name="penalty_fee" step="0.01" min="0"
                                       value="<?php echo e($f['penalty_fee']); ?>" placeholder="0.00">
                                <small class="text-muted">Added to overdue payments.</small>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Frequency <span class="text-danger">*</span></label>
                                <select class="form-control form-select" name="frequency">
                                    <?php foreach (['one_off' => 'One-off','weekly' => 'Weekly','monthly' => 'Monthly','quarterly' => 'Quarterly','yearly' => 'Yearly'] as $val => $label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo $f['frequency'] === $val ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group">
                                <label class="form-label">Due Date</label>
                                <input type="date" class="form-control" name="due_date"
                                       value="<?php echo e($f['due_date']); ?>">
                                <small class="text-muted">When payment is expected.</small>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="assign_all" id="assign_all"
                                   value="1" <?php echo !empty($_POST['assign_all']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="assign_all">
                                Automatically assign to all active members
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Creates a pending payment record for every active member (requires a due date).
                        </small>
                    </div>

                    <div class="mt-4 d-flex gap-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> <?php echo $isEdit ? 'Update Due' : 'Create Due'; ?>
                        </button>
                        <a href="<?php echo BASE_URL; ?>payments/dues.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
