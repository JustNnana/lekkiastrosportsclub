<?php
/**
 * My Payments — Member view
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Member.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireLogin();

$pageTitle = 'My Payments';
$db        = Database::getInstance();

// Get current member record
$member = $db->fetchOne(
    'SELECT m.*, u.email FROM members m JOIN users u ON u.id = m.user_id WHERE m.user_id = ?',
    [$_SESSION['user_id']]
);

if (!$member) {
    flashError('Member profile not found. Please contact an administrator.');
    redirect('dashboard/');
}

$payObj  = new Payment();
$payObj->processOverdue(); // Update overdue statuses

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$total   = $payObj->countByMember((int)$member['id']);
$paged   = paginate($total, $perPage, $page);
$history = $payObj->getByMember((int)$member['id'], $page, $perPage);
$pending = $payObj->getPendingByMember((int)$member['id']);

// Payment stats for this member
$paidCount    = 0; $pendingCount = 0; $overdueCount = 0; $totalPaid = 0.0;
foreach ($history as $h) {
    if ($h['status'] === 'paid')    { $paidCount++;    $totalPaid += (float)$h['amount'] + (float)$h['penalty_applied']; }
    if ($h['status'] === 'pending') $pendingCount++;
    if ($h['status'] === 'overdue') $overdueCount++;
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <h1 class="content-title">My Payments</h1>
    <p class="content-subtitle">View and pay your membership dues.</p>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#16a34a"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Paid</span>
                <span class="stat-value"><?php echo formatCurrency($totalPaid); ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(234,179,8,.12);color:#ca8a04"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <span class="stat-label">Pending</span>
                <span class="stat-value"><?php echo $pendingCount; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--danger-rgb),.12);color:var(--danger)"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-info">
                <span class="stat-label">Overdue</span>
                <span class="stat-value"><?php echo $overdueCount; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--primary-rgb),.12);color:var(--primary)"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <span class="stat-label">Total Records</span>
                <span class="stat-value"><?php echo $total; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Outstanding Dues -->
<?php if (!empty($pending)): ?>
<div class="card mb-4" style="border-left:4px solid var(--warning)">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0"><i class="fas fa-exclamation-triangle me-2 text-warning"></i>Outstanding Dues</h6>
        <span class="badge badge-warning"><?php echo count($pending); ?> unpaid</span>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Due</th>
                    <th>Amount</th>
                    <th>Penalty</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending as $p): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?php echo e($p['due_title']); ?></div>
                        <small class="text-muted"><?php echo e(str_replace('_',' ', ucfirst($p['frequency']))); ?></small>
                    </td>
                    <td class="fw-semibold"><?php echo formatCurrency((float)$p['amount']); ?></td>
                    <td class="text-<?php echo $p['penalty_applied'] > 0 ? 'danger' : 'muted'; ?>">
                        <?php echo $p['penalty_applied'] > 0 ? '+' . formatCurrency((float)$p['penalty_applied']) : '—'; ?>
                    </td>
                    <td class="text-muted"><?php echo formatDate($p['due_date']); ?></td>
                    <td><?php echo statusBadge($p['status']); ?></td>
                    <td class="text-end">
                        <?php
                        $total_due = (float)$p['amount'] + (float)$p['penalty_applied'];
                        ?>
                        <form method="POST" action="<?php echo BASE_URL; ?>payments/pay.php">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-credit-card me-1"></i>
                                Pay <?php echo formatCurrency($total_due); ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Payment History -->
<div class="card">
    <div class="card-header">
        <h6 class="card-title mb-0"><i class="fas fa-history me-2"></i>Payment History</h6>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Due</th>
                    <th>Amount</th>
                    <th>Penalty</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Paid On</th>
                    <th class="text-end">Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($history)): ?>
                <tr><td colspan="7" class="text-center text-muted py-5">No payment records yet.</td></tr>
                <?php else: ?>
                <?php foreach ($history as $p): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?php echo e($p['due_title']); ?></div>
                        <small class="text-muted"><?php echo e(str_replace('_',' ', ucfirst($p['frequency']))); ?></small>
                    </td>
                    <td><?php echo formatCurrency((float)$p['amount']); ?></td>
                    <td class="text-muted">
                        <?php echo $p['penalty_applied'] > 0 ? formatCurrency((float)$p['penalty_applied']) : '—'; ?>
                    </td>
                    <td class="text-muted"><?php echo formatDate($p['due_date']); ?></td>
                    <td><?php echo statusBadge($p['status']); ?></td>
                    <td class="text-muted">
                        <?php echo $p['payment_date'] ? formatDate($p['payment_date'], 'd M Y') : '—'; ?>
                    </td>
                    <td class="text-end">
                        <?php if ($p['status'] === 'paid'): ?>
                        <a href="<?php echo BASE_URL; ?>payments/receipt.php?id=<?php echo $p['id']; ?>"
                           target="_blank" class="btn btn-secondary btn-sm">
                            <i class="fas fa-receipt"></i>
                        </a>
                        <?php elseif (in_array($p['status'], ['pending','overdue'])): ?>
                        <form method="POST" action="<?php echo BASE_URL; ?>payments/pay.php" class="d-inline">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-credit-card"></i>
                            </button>
                        </form>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($paged['total_pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing <?php echo min($total, ($page-1)*$perPage+1); ?>–<?php echo min($total, $page*$perPage); ?> of <?php echo $total; ?></small>
        <nav><ul class="pagination pagination-sm mb-0">
            <?php if ($paged['has_prev']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>">‹</a></li>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $paged['total_pages']; $i++): ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($paged['has_next']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>">›</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
