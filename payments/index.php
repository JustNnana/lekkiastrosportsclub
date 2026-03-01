<?php
/**
 * All Payments — Admin view
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Due.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireAdmin();

// Auto-mark overdue on every page load (lightweight)
$payObj = new Payment();
$payObj->processOverdue();

$pageTitle = 'Payments';

$filters = [
    'status'  => in_array($_GET['status'] ?? '', ['pending','paid','overdue','reversed']) ? $_GET['status'] : '',
    'due_id'  => (int)($_GET['due_id'] ?? 0),
    'search'  => sanitize($_GET['search'] ?? ''),
    'month'   => sanitize($_GET['month']  ?? ''),
];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$total    = $payObj->countAll($filters);
$paged    = paginate($total, $perPage, $page);
$payments = $payObj->getAll($page, $perPage, $filters);
$stats    = $payObj->getStats();
$dueObj   = new Due();
$allDues  = $dueObj->getActive();

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header d-flex justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="content-title">Payments</h1>
        <p class="content-subtitle">Track all member payments and dues.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>payments/dues.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-file-invoice-dollar me-2"></i> Manage Dues
    </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(34,197,94,.12);color:#16a34a"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <span class="stat-label">Collected</span>
                <span class="stat-value"><?php echo formatCurrency((float)$stats['total_collected']); ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(234,179,8,.12);color:#ca8a04"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <span class="stat-label">Pending</span>
                <span class="stat-value"><?php echo $stats['pending_count']; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--danger-rgb),.12);color:var(--danger)"><i class="fas fa-exclamation-circle"></i></div>
            <div class="stat-info">
                <span class="stat-label">Overdue</span>
                <span class="stat-value"><?php echo $stats['overdue_count']; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--primary-rgb),.12);color:var(--primary)"><i class="fas fa-money-bill-wave"></i></div>
            <div class="stat-info">
                <span class="stat-label">Pending Amount</span>
                <span class="stat-value"><?php echo formatCurrency((float)$stats['total_pending']); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
            <div class="flex-grow-1" style="min-width:200px">
                <label class="form-label mb-1" style="font-size:var(--font-size-sm)">Search</label>
                <input type="text" class="form-control form-control-sm" name="search"
                       placeholder="Name, member ID or reference…"
                       value="<?php echo e($filters['search']); ?>">
            </div>
            <div>
                <label class="form-label mb-1" style="font-size:var(--font-size-sm)">Status</label>
                <select class="form-control form-select form-control-sm" name="status" style="min-width:120px">
                    <option value="">All Status</option>
                    <?php foreach (['pending'=>'Pending','paid'=>'Paid','overdue'=>'Overdue','reversed'=>'Reversed'] as $v => $l): ?>
                    <option value="<?php echo $v; ?>" <?php echo $filters['status'] === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label mb-1" style="font-size:var(--font-size-sm)">Due</label>
                <select class="form-control form-select form-control-sm" name="due_id" style="min-width:160px">
                    <option value="">All Dues</option>
                    <?php foreach ($allDues as $d): ?>
                    <option value="<?php echo $d['id']; ?>" <?php echo $filters['due_id'] === $d['id'] ? 'selected' : ''; ?>>
                        <?php echo e($d['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label mb-1" style="font-size:var(--font-size-sm)">Month</label>
                <input type="month" class="form-control form-control-sm" name="month"
                       value="<?php echo e($filters['month']); ?>" style="min-width:140px">
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                <a href="<?php echo BASE_URL; ?>payments/" class="btn btn-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Table -->
<div class="card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Member</th>
                    <th>Due</th>
                    <th>Amount</th>
                    <th>Penalty</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Method</th>
                    <th>Paid On</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr><td colspan="9" class="text-center text-muted py-5">No payments found.</td></tr>
                <?php else: ?>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?php echo e($p['full_name']); ?></div>
                        <small class="text-muted"><?php echo e($p['member_code']); ?></small>
                    </td>
                    <td>
                        <div><?php echo e($p['due_title']); ?></div>
                        <small class="text-muted"><?php echo e(str_replace('_',' ', ucfirst($p['frequency']))); ?></small>
                    </td>
                    <td class="fw-semibold"><?php echo formatCurrency((float)$p['amount']); ?></td>
                    <td class="text-muted"><?php echo $p['penalty_applied'] > 0 ? formatCurrency((float)$p['penalty_applied']) : '—'; ?></td>
                    <td class="text-muted"><?php echo formatDate($p['due_date']); ?></td>
                    <td><?php echo statusBadge($p['status']); ?></td>
                    <td class="text-muted">
                        <?php if ($p['payment_date']): ?>
                            <?php echo $p['payment_method'] === 'paystack' ? '<i class="fas fa-credit-card me-1 text-primary"></i>Paystack' : '<i class="fas fa-hand-holding-usd me-1 text-warning"></i>Manual'; ?>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="text-muted">
                        <?php echo $p['payment_date'] ? formatDate($p['payment_date'], 'd M Y') : '—'; ?>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <?php if (in_array($p['status'], ['pending','overdue'])): ?>
                            <button class="btn btn-success btn-sm"
                                    onclick="markPaidModal(<?php echo $p['id']; ?>, '<?php echo e($p['full_name']); ?>', '<?php echo e($p['due_title']); ?>', <?php echo $p['amount'] + $p['penalty_applied']; ?>)">
                                <i class="fas fa-check"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($p['status'] === 'paid'): ?>
                            <a href="<?php echo BASE_URL; ?>payments/receipt.php?id=<?php echo $p['id']; ?>"
                               class="btn btn-secondary btn-sm" target="_blank" title="Receipt">
                                <i class="fas fa-receipt"></i>
                            </a>
                            <?php if (isSuperAdmin()): ?>
                            <button class="btn btn-warning btn-sm"
                                    onclick="reverseModal(<?php echo $p['id']; ?>, '<?php echo e($p['full_name']); ?>')">
                                <i class="fas fa-undo"></i>
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
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
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo urlencode($filters['status']); ?>&due_id=<?php echo $filters['due_id']; ?>&search=<?php echo urlencode($filters['search']); ?>&month=<?php echo urlencode($filters['month']); ?>">‹</a></li>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($paged['total_pages'],$page+2); $i++): ?>
            <li class="page-item <?php echo $i===$page?'active':''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filters['status']); ?>&due_id=<?php echo $filters['due_id']; ?>&search=<?php echo urlencode($filters['search']); ?>&month=<?php echo urlencode($filters['month']); ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($paged['has_next']): ?>
            <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo urlencode($filters['status']); ?>&due_id=<?php echo $filters['due_id']; ?>&search=<?php echo urlencode($filters['search']); ?>&month=<?php echo urlencode($filters['month']); ?>">›</a></li>
            <?php endif; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>

<!-- Mark Paid Modal -->
<div class="modal fade" id="markPaidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="fas fa-check-circle me-2 text-success"></i>Mark Payment as Paid</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>payments/mark-paid.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="id" id="markPaidId">
                <div class="modal-body">
                    <p class="text-muted mb-1">Marking payment as manually paid for:</p>
                    <p class="fw-semibold mb-0" id="markPaidMember"></p>
                    <p class="text-muted" id="markPaidDue"></p>
                    <div class="alert alert-info">Total: <strong id="markPaidAmount"></strong></div>
                    <div class="form-group">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" name="notes" rows="2"
                                  placeholder="e.g. Cash received at office, receipt #1234"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-check me-2"></i>Mark as Paid</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reverse Modal -->
<div class="modal fade" id="reverseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-warning"><i class="fas fa-undo me-2"></i>Reverse Payment</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>payments/reverse.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="id" id="reverseId">
                <div class="modal-body">
                    <p>Reverse payment for <strong id="reverseMember"></strong>?</p>
                    <p class="text-muted">This will change status back to <em>reversed</em>. Use only for refunds or errors.</p>
                    <div class="form-group">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="notes" rows="2" required
                                  placeholder="Reason for reversal…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-undo me-2"></i>Reverse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function markPaidModal(id, member, due, amount) {
    document.getElementById('markPaidId').value = id;
    document.getElementById('markPaidMember').textContent = member;
    document.getElementById('markPaidDue').textContent = due;
    document.getElementById('markPaidAmount').textContent = '₦' + parseFloat(amount).toLocaleString('en-NG', {minimumFractionDigits:2});
    new bootstrap.Modal(document.getElementById('markPaidModal')).show();
}
function reverseModal(id, member) {
    document.getElementById('reverseId').value = id;
    document.getElementById('reverseMember').textContent = member;
    new bootstrap.Modal(document.getElementById('reverseModal')).show();
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
