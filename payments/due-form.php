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
$fAssignAll = !empty($_POST['assign_all']) ? 'checked' : '';

$frequencies = [
    'one_off'   => 'One-off',
    'weekly'    => 'Weekly',
    'monthly'   => 'Monthly',
    'quarterly' => 'Quarterly',
    'yearly'    => 'Yearly',
];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
:root {
    --ios-red:    #FF453A;
    --ios-orange: #FF9F0A;
    --ios-green:  #30D158;
    --ios-blue:   #0A84FF;
    --ios-purple: #BF5AF2;
}

/* ── Layout ── */
.form-container {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: var(--spacing-6);
    max-width: 1100px;
    margin: 0 auto;
}
@media (max-width: 992px) { .form-container { grid-template-columns: 1fr; } }

/* ── iOS Section Card ── */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: var(--spacing-4);
}
.ios-section-header {
    display: flex; align-items: center; gap: var(--spacing-3);
    padding: var(--spacing-4);
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-section-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.ios-section-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-section-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-section-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-section-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-section-icon.red    { background: rgba(255,69,58,.15);  color: var(--ios-red); }

.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }
.ios-section-body { padding: var(--spacing-5); }

/* ── 3-dot button (mobile only) ── */
.ios-options-btn {
    display: none;
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    align-items: center; justify-content: center;
    cursor: pointer;
    transition: background .2s ease, transform .15s ease;
    flex-shrink: 0;
}
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* ── Form elements ── */
.form-row { margin-bottom: var(--spacing-5); }
.form-row:last-child { margin-bottom: 0; }
.form-row-2-cols {
    display: grid; grid-template-columns: 1fr 1fr;
    gap: var(--spacing-4); margin-bottom: var(--spacing-5);
}
@media (max-width: 640px) { .form-row-2-cols { grid-template-columns: 1fr; } }

.form-label {
    display: block; margin-bottom: var(--spacing-2);
    font-weight: var(--font-weight-medium);
    color: var(--text-primary); font-size: var(--font-size-sm);
}
.form-label.required::after { content: ' *'; color: var(--danger); }
.form-text { margin-top: var(--spacing-2); font-size: var(--font-size-xs); color: var(--text-secondary); }

/* ── iOS Toggle ── */
.ios-toggle-row {
    display: flex; align-items: flex-start;
    justify-content: space-between;
    padding: var(--spacing-4) var(--spacing-5);
    border-top: 1px solid var(--border-color);
    gap: var(--spacing-4);
}
.ios-toggle-info { flex: 1; min-width: 0; }
.ios-toggle-info-title { font-size: 15px; font-weight: 500; color: var(--text-primary); margin: 0 0 2px; }
.ios-toggle-info-desc  { font-size: 12px; color: var(--text-secondary); margin: 0; line-height: 1.4; }

.ios-toggle { position: relative; display: inline-flex; flex-shrink: 0; margin-top: 2px; }
.ios-toggle input { position: absolute; opacity: 0; width: 0; height: 0; pointer-events: none; }
.ios-toggle-track {
    width: 51px; height: 31px;
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
    border-radius: 100px; cursor: pointer;
    transition: background .25s ease, border-color .25s ease;
    position: relative;
}
.ios-toggle-thumb {
    position: absolute; top: 2px; left: 2px;
    width: 23px; height: 23px;
    background: #fff; border-radius: 50%;
    box-shadow: 0 1px 4px rgba(0,0,0,.25);
    transition: transform .25s cubic-bezier(.4,0,.2,1);
}
.ios-toggle input:checked ~ .ios-toggle-track {
    background: var(--ios-green); border-color: var(--ios-green);
}
.ios-toggle input:checked ~ .ios-toggle-track .ios-toggle-thumb {
    transform: translateX(20px);
}

/* ── Form actions ── */
.form-actions {
    margin-top: var(--spacing-6);
    padding-top: var(--spacing-5);
    border-top: 1px solid var(--border-color);
    display: flex; gap: var(--spacing-3); justify-content: flex-end;
}
@media (max-width: 480px) { .form-actions { flex-direction: column; } }

/* ── Tips sidebar ── */
.tips-card {
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: 16px; overflow: hidden;
    height: fit-content;
    position: sticky; top: var(--spacing-4);
}
.tips-header {
    padding: var(--spacing-4); background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
    display: flex; align-items: center; gap: var(--spacing-3);
}
.tips-icon  { color: var(--ios-orange); font-size: var(--font-size-lg); }
.tips-title { margin: 0; font-size: var(--font-size-base); font-weight: var(--font-weight-semibold); color: var(--text-primary); }
.tips-content { padding: var(--spacing-4); }
.tip-item { display: flex; align-items: flex-start; gap: var(--spacing-3); margin-bottom: var(--spacing-4); }
.tip-item:last-child { margin-bottom: 0; }
.tip-icon { font-size: var(--font-size-sm); margin-top: 2px; flex-shrink: 0; }
.tip-text { font-size: var(--font-size-sm); color: var(--text-secondary); line-height: 1.5; }
.tip-text strong { color: var(--text-primary); }

/* ── iOS bottom-sheet menu ── */
.ios-menu-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.4);
    backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    z-index: 9998; opacity: 0; visibility: hidden;
    transition: opacity .3s ease, visibility .3s ease;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-primary);
    border-radius: 16px 16px 0 0;
    z-index: 9999; transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 85vh; overflow: hidden;
    display: flex; flex-direction: column;
}
.ios-menu-modal.active { transform: translateY(0); }
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ios-menu-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; transition: background .2s ease; }
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-left: 4px; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background .15s ease; cursor: pointer; width: 100%; background: transparent; border-left: none; border-right: none; border-top: none; font-family: inherit; font-size: inherit; text-align: left; }
button.ios-menu-item { border: none; border-bottom: 1px solid var(--border-color); }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-menu-item-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-menu-item-label { font-size: 15px; font-weight: 500; }
.ios-menu-item-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }

/* In-menu toggle */
.ios-menu-toggle-row { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; }
.ios-menu-toggle-label { font-size: 15px; font-weight: 500; color: var(--text-primary); }
.ios-menu-toggle-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 1px; }

/* ── Responsive ── */
@media (max-width: 992px) { .ios-options-btn { display: flex; } }
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .tips-card { display: none !important; }
    .form-container { grid-template-columns: 1fr; }
    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 14px; }
    .ios-section-icon { width: 36px; height: 36px; font-size: 16px; }
    .ios-section-title h5 { font-size: 15px; }
    .ios-section-body { padding: var(--spacing-4); }
}
@media (max-width: 480px) {
    .ios-options-btn { width: 32px; height: 32px; }
    .ios-options-btn i { font-size: 14px; }
}
</style>

<!-- ===== DESKTOP PAGE HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>payments/dues.php" class="breadcrumb-link">Manage Dues</a>
                </li>
                <li class="breadcrumb-item active"><?php echo $isEdit ? 'Edit' : 'Create'; ?></li>
            </ol>
        </nav>
        <h1 class="content-title"><?php echo $pageTitle; ?></h1>
        <p class="content-subtitle"><?php echo $isEdit ? 'Update due details below.' : 'Set up a new membership due.'; ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>payments/dues.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Back
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger mb-4">
    <i class="fas fa-exclamation-circle me-2"></i><?php echo e($error); ?>
</div>
<?php endif; ?>

<form method="POST" id="due-form" novalidate>
    <?php echo csrfField(); ?>

    <div class="form-container">

        <!-- ===== LEFT: FORM ===== -->
        <div>
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon green">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5><?php echo $isEdit ? 'Edit Due' : 'Due Details'; ?></h5>
                        <p><?php echo $isEdit ? 'Update the due configuration' : 'Configure the new membership due'; ?></p>
                    </div>
                    <button type="button" class="ios-options-btn" onclick="openIosMenu()" aria-label="Options">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
                <div class="ios-section-body">

                    <!-- Title -->
                    <div class="form-row">
                        <label class="form-label required" for="due-title">Title</label>
                        <input type="text" class="form-control" id="due-title" name="title"
                               value="<?php echo e($f['title']); ?>"
                               placeholder="e.g. Monthly Membership Fee" required>
                    </div>

                    <!-- Description -->
                    <div class="form-row">
                        <label class="form-label" for="due-desc">Description</label>
                        <textarea class="form-control" id="due-desc" name="description" rows="3"
                                  placeholder="Optional notes about this due…"><?php echo e($f['description']); ?></textarea>
                    </div>

                    <!-- Amount + Penalty -->
                    <div class="form-row-2-cols">
                        <div class="form-group">
                            <label class="form-label required" for="due-amount">Amount (₦)</label>
                            <input type="number" class="form-control" id="due-amount" name="amount"
                                   step="0.01" min="1"
                                   value="<?php echo e($f['amount']); ?>"
                                   placeholder="0.00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="due-penalty">Penalty Fee (₦)</label>
                            <input type="number" class="form-control" id="due-penalty" name="penalty_fee"
                                   step="0.01" min="0"
                                   value="<?php echo e($f['penalty_fee']); ?>"
                                   placeholder="0.00">
                            <p class="form-text">Added to overdue payments.</p>
                        </div>
                    </div>

                    <!-- Frequency + Due Date -->
                    <div class="form-row-2-cols">
                        <div class="form-group">
                            <label class="form-label required" for="due-freq">Frequency</label>
                            <select class="form-control form-select" id="due-freq" name="frequency">
                                <?php foreach ($frequencies as $val => $label): ?>
                                <option value="<?php echo $val; ?>" <?php echo $f['frequency'] === $val ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="due-date">Due Date</label>
                            <input type="date" class="form-control" id="due-date" name="due_date"
                                   value="<?php echo e($f['due_date']); ?>">
                            <p class="form-text">When payment is expected.</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="<?php echo BASE_URL; ?>payments/dues.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="submit-btn">
                            <i class="fas fa-<?php echo $isEdit ? 'save' : 'plus-circle'; ?> me-2"></i>
                            <?php echo $isEdit ? 'Update Due' : 'Create Due'; ?>
                        </button>
                    </div>

                </div>

                <!-- Assign to all toggle (below form body, inside card) -->
                <div class="ios-toggle-row">
                    <div class="ios-toggle-info">
                        <p class="ios-toggle-info-title">
                            <i class="fas fa-users" style="color:var(--ios-blue);margin-right:6px;font-size:12px"></i>
                            Assign to all active members
                        </p>
                        <p class="ios-toggle-info-desc">Creates a pending payment record for every active member (requires a due date).</p>
                    </div>
                    <label class="ios-toggle">
                        <input type="checkbox" name="assign_all" id="toggle-assign" value="1" <?php echo $fAssignAll; ?>>
                        <span class="ios-toggle-track"><span class="ios-toggle-thumb"></span></span>
                    </label>
                </div>

            </div>
        </div><!-- /left -->

        <!-- ===== RIGHT: TIPS (desktop only) ===== -->
        <div>
            <div class="tips-card">
                <div class="tips-header">
                    <i class="fas fa-lightbulb tips-icon"></i>
                    <h6 class="tips-title"><?php echo $isEdit ? 'About Editing' : 'How Dues Work'; ?></h6>
                </div>
                <div class="tips-content">
                    <?php if ($isEdit): ?>
                    <div class="tip-item">
                        <i class="fas fa-sync-alt tip-icon" style="color:var(--ios-blue)"></i>
                        <p class="tip-text">Editing updates the due definition. <strong>Existing payments</strong> won't change automatically.</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-users tip-icon" style="color:var(--ios-green)"></i>
                        <p class="tip-text">Toggle <strong>Assign to all</strong> to push new payment records to every active member.</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-exclamation-triangle tip-icon" style="color:var(--ios-orange)"></i>
                        <p class="tip-text">A <strong>due date</strong> is required for the assign-all option to work.</p>
                    </div>
                    <?php else: ?>
                    <div class="tip-item">
                        <i class="fas fa-file-invoice-dollar tip-icon" style="color:var(--ios-green)"></i>
                        <p class="tip-text">A <strong>due</strong> defines a payment obligation — title, amount, and frequency.</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-users tip-icon" style="color:var(--ios-blue)"></i>
                        <p class="tip-text">Enable <strong>Assign to all</strong> to automatically create pending payment records for every active member.</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-clock tip-icon" style="color:var(--ios-orange)"></i>
                        <p class="tip-text">The <strong>Penalty Fee</strong> is added when a payment becomes overdue.</p>
                    </div>
                    <div class="tip-item">
                        <i class="fas fa-redo tip-icon" style="color:var(--ios-purple)"></i>
                        <p class="tip-text">For <strong>recurring dues</strong> (monthly, yearly, etc.), set the frequency and due date for the next cycle.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /right -->

    </div><!-- /form-container -->
</form>

<!-- ===== iOS MENU MODAL (mobile 3-dot) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeIosMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h5 class="ios-menu-title"><?php echo $pageTitle; ?></h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <!-- Quick Actions -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Quick Actions</p>
            <div class="ios-menu-card">
                <button type="button" class="ios-menu-item" onclick="closeIosMenu(); document.getElementById('submit-btn').click()">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-<?php echo $isEdit ? 'save' : 'plus-circle'; ?>"></i></div>
                        <div>
                            <div class="ios-menu-item-label"><?php echo $isEdit ? 'Update Due' : 'Create Due'; ?></div>
                            <div class="ios-menu-item-desc">Submit the form</div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <a href="<?php echo BASE_URL; ?>payments/dues.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                        <div>
                            <div class="ios-menu-item-label">Manage Dues</div>
                            <div class="ios-menu-item-desc">Back to dues list</div>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <!-- Assign toggle -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Options</p>
            <div class="ios-menu-card">
                <div class="ios-menu-toggle-row">
                    <div>
                        <div class="ios-menu-toggle-label">
                            <i class="fas fa-users" style="color:var(--ios-blue);margin-right:6px;font-size:11px"></i>
                            Assign to all members
                        </div>
                        <div class="ios-menu-toggle-desc">Creates pending records for every active member</div>
                    </div>
                    <label class="ios-toggle" style="margin-left:12px">
                        <input type="checkbox" id="toggle-assign-m"
                               onchange="document.getElementById('toggle-assign').checked=this.checked"
                               <?php echo $fAssignAll; ?>>
                        <span class="ios-toggle-track"><span class="ios-toggle-thumb"></span></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>payments/dues.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-wallet"></i></div>
                        <div class="ios-menu-item-label">Payments</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-home"></i></div>
                        <div class="ios-menu-item-label">Dashboard</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
function openIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.add('active');
    document.getElementById('iosMenuModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.remove('active');
    document.getElementById('iosMenuModal').classList.remove('active');
    document.body.style.overflow = '';
}

/* Swipe to close */
(function() {
    var modal = document.getElementById('iosMenuModal');
    var startY = 0, isDragging = false;
    modal.addEventListener('touchstart', function(e) { startY = e.touches[0].clientY; isDragging = true; }, { passive: true });
    modal.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        var dy = e.touches[0].clientY - startY;
        if (dy > 0) modal.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    modal.addEventListener('touchend', function(e) {
        if (!isDragging) return;
        isDragging = false;
        var dy = e.changedTouches[0].clientY - startY;
        modal.style.transform = '';
        if (dy > 80) closeIosMenu();
    });
})();

/* Sync desktop toggle → mobile */
document.getElementById('toggle-assign').addEventListener('change', function() {
    document.getElementById('toggle-assign-m').checked = this.checked;
});

/* Submit loading state */
document.getElementById('due-form').addEventListener('submit', function() {
    var btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving…';
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
