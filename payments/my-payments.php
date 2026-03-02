<?php
/**
 * My Payments — Member view — iOS styled
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Member.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireLogin();

$pageTitle = 'My Payments';
$db        = Database::getInstance();

$member = $db->fetchOne(
    'SELECT m.*, u.email FROM members m JOIN users u ON u.id = m.user_id WHERE m.user_id = ?',
    [$_SESSION['user_id']]
);

if (!$member) {
    flashError('Member profile not found. Please contact an administrator.');
    redirect('dashboard/');
}

$payObj = new Payment();
$payObj->processOverdue();

// Load all records for client-side filter/search (member totals are small)
$allHistory = $payObj->getByMember((int)$member['id'], 1, 9999);
$pending    = $payObj->getPendingByMember((int)$member['id']);

$paidCount = $pendingCount = $overdueCount = 0;
$totalPaid = 0.0;
foreach ($allHistory as $h) {
    if ($h['status'] === 'paid')    { $paidCount++;    $totalPaid += (float)$h['amount'] + (float)$h['penalty_applied']; }
    if ($h['status'] === 'pending') $pendingCount++;
    if ($h['status'] === 'overdue') $overdueCount++;
}
$total = count($allHistory);

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

/* ── Stats Grid ─────────────────────────────────────────── */
.stats-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}
.stat-card {
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg);
    padding: var(--spacing-5);
    display: flex; align-items: center; gap: var(--spacing-4);
    transition: var(--theme-transition);
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow); border-color: var(--primary); }
.stat-icon {
    width: 56px; height: 56px; border-radius: var(--border-radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem; color: white; flex-shrink: 0;
}
.stat-success .stat-icon { background: var(--success); }
.stat-warning .stat-icon { background: var(--warning); }
.stat-danger  .stat-icon { background: var(--danger); }
.stat-primary .stat-icon { background: var(--primary); }
.stat-content { flex: 1; }
.stat-label  { font-size: var(--font-size-sm); color: var(--text-secondary); margin-bottom: var(--spacing-1); }
.stat-value  { font-size: 1.75rem; font-weight: var(--font-weight-bold); color: var(--text-primary); line-height: 1; margin-bottom: var(--spacing-2); }
.stat-detail { font-size: var(--font-size-xs); color: var(--text-secondary); }

/* ── iOS Section Card ───────────────────────────────────── */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px; overflow: hidden;
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
.ios-section-icon.primary { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-section-icon.orange  { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-section-icon.blue    { background: rgba(10,132,255,.15);  color: var(--ios-blue); }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }
.ios-section-body { padding: 0; }

/* ── 3-Dot Options Button ───────────────────────────────── */
.ios-options-btn {
    display: none;
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    align-items: center; justify-content: center;
    cursor: pointer; transition: background .2s; flex-shrink: 0;
}
.ios-options-btn:hover { background: var(--border-color); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* ── Filter Pills ───────────────────────────────────────── */
.ios-filter-pills {
    display: flex; gap: 8px; padding: 12px 16px;
    overflow-x: auto; -webkit-overflow-scrolling: touch;
    border-bottom: 1px solid var(--border-color);
    scrollbar-width: none;
}
.ios-filter-pills::-webkit-scrollbar { display: none; }
.ios-filter-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 20px;
    font-size: 13px; font-weight: 500;
    text-decoration: none; white-space: nowrap; cursor: pointer;
    border: 1px solid var(--border-color);
    background: var(--bg-secondary); color: var(--text-secondary);
    transition: all .2s ease;
}
.ios-filter-pill:hover { background: var(--border-color); color: var(--text-primary); text-decoration: none; }
.ios-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-filter-pill .count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,.2); }
.ios-filter-pill:not(.active) .count { background: var(--border-color); color: var(--text-muted); }

/* ── Search Box ─────────────────────────────────────────── */
.ios-search-box {
    padding: 12px 16px; background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-search-input-wrapper { position: relative; display: flex; align-items: center; }
.ios-search-icon { position: absolute; left: 12px; color: var(--text-muted); font-size: 14px; pointer-events: none; }
.ios-search-input {
    width: 100%; padding: 10px 36px;
    border: 1px solid var(--border-color); border-radius: 10px;
    background: var(--bg-primary); color: var(--text-primary);
    font-size: 15px; transition: border-color .2s, box-shadow .2s;
}
.ios-search-input:focus { outline: none; border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10,132,255,.1); }
.ios-search-input::placeholder { color: var(--text-muted); }
.ios-search-clear {
    position: absolute; right: 10px; width: 20px; height: 20px; border-radius: 50%;
    background: var(--border-color); border: none; display: none;
    align-items: center; justify-content: center;
    cursor: pointer; color: var(--text-secondary); font-size: 10px;
}
.ios-search-clear.visible { display: flex; }

/* ── Payment List Item ──────────────────────────────────── */
.ios-pay-item {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; background: var(--bg-primary);
    border-bottom: 1px solid var(--border-color);
    transition: background .15s ease;
}
.ios-pay-item:last-child { border-bottom: none; }
.ios-pay-item:hover { background: var(--bg-secondary); }

.ios-pay-icon {
    width: 40px; height: 40px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.ios-pay-icon.paid    { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-pay-icon.pending { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-pay-icon.overdue { background: rgba(255,69,58,.15);  color: var(--ios-red); }

.ios-pay-content { flex: 1; min-width: 0; }
.ios-pay-title {
    font-size: 15px; font-weight: 600; color: var(--text-primary);
    margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ios-pay-sub     { font-size: 12px; color: var(--text-muted); margin: 0; }
.ios-pay-penalty { font-size: 11px; color: var(--ios-red); margin: 2px 0 0; }

.ios-pay-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.ios-pay-amount { font-size: 15px; font-weight: 700; color: var(--text-primary); white-space: nowrap; }
.ios-pay-date   { font-size: 11px; color: var(--text-muted); }

.ios-status-badge {
    font-size: 11px; font-weight: 600; padding: 3px 8px;
    border-radius: 6px; text-transform: capitalize; white-space: nowrap;
}
.ios-status-badge.paid    { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-status-badge.pending { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-status-badge.overdue { background: rgba(255,69,58,.15);  color: var(--ios-red); }

.ios-actions-btn {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--text-secondary); font-size: 14px;
    text-decoration: none; flex-shrink: 0; transition: background .2s;
}
.ios-actions-btn:hover { background: var(--border-color); color: var(--text-primary); }

.ios-pay-btn, .ios-pay-now-btn {
    padding: 7px 14px; border-radius: 10px;
    background: var(--ios-blue); color: #fff;
    font-size: 13px; font-weight: 600;
    border: none; cursor: pointer; white-space: nowrap;
    flex-shrink: 0; transition: opacity .2s;
}
.ios-pay-btn:hover, .ios-pay-now-btn:hover { opacity: .85; }

/* ── Empty State ────────────────────────────────────────── */
.ios-empty-state { text-align: center; padding: 48px 24px; }
.ios-empty-icon  { font-size: 56px; color: var(--text-secondary); margin-bottom: 16px; opacity: .5; }
.ios-empty-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px; }
.ios-empty-desc  { font-size: 14px; color: var(--text-secondary); margin: 0; line-height: 1.5; }

/* ── iOS Pagination ─────────────────────────────────────── */
.ios-pagination {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    padding: 16px; border-top: 1px solid var(--border-color);
}
.ios-page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px;
    font-size: 14px; font-weight: 500; cursor: pointer;
    color: var(--text-secondary); background: var(--bg-secondary);
    border: 1px solid var(--border-color); transition: all .2s;
}
.ios-page-btn:hover:not(:disabled) { background: var(--border-color); color: var(--text-primary); }
.ios-page-btn.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-page-btn:disabled { opacity: .4; cursor: default; }
.ios-pagination-info { font-size: 12px; color: var(--text-muted); text-align: center; padding: 12px 16px 0; }

/* ── Mobile Menu Modal ──────────────────────────────────── */
.ios-menu-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.4);
    backdrop-filter: blur(4px); z-index: 9998;
    opacity: 0; visibility: hidden; transition: .3s;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-primary); border-radius: 16px 16px 0 0;
    z-index: 9999; transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 85vh; overflow: hidden; display: flex; flex-direction: column;
}
.ios-menu-modal.active { transform: translateY(0); }
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-menu-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0;
}
.ios-menu-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--bg-secondary); border: none;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary); cursor: pointer; transition: background .2s;
}
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title {
    font-size: 13px; font-weight: 600; color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-left: 4px;
}
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; border-bottom: 1px solid var(--border-color);
    text-decoration: none; color: var(--text-primary); transition: background .15s;
    cursor: pointer; width: 100%; background: transparent;
    border-left: none; border-right: none; border-top: none;
    font-family: inherit; font-size: inherit; text-align: left;
}
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item:hover { text-decoration: none; color: var(--text-primary); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.ios-menu-item-icon.primary { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-menu-item-icon.orange  { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-menu-item-icon.blue    { background: rgba(10,132,255,.15);  color: var(--ios-blue); }
.ios-menu-item-icon.red     { background: rgba(255,69,58,.15);   color: var(--ios-red); }
.ios-menu-item-icon.purple  { background: rgba(191,90,242,.15);  color: var(--ios-purple); }
.ios-menu-item-content { flex: 1; min-width: 0; }
.ios-menu-item-label { font-size: 15px; font-weight: 500; }
.ios-menu-item-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }
.ios-menu-stat-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px; border-bottom: 1px solid var(--border-color);
}
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 15px; color: var(--text-primary); }
.ios-menu-stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); }
.ios-menu-stat-value.success { color: var(--ios-green); }
.ios-menu-stat-value.warning { color: var(--ios-orange); }
.ios-menu-stat-value.danger  { color: var(--ios-red); }

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width: 992px) {
    .ios-options-btn { display: flex; }
}
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .stats-overview-grid {
        display: flex !important; flex-wrap: nowrap !important;
        overflow-x: auto !important; gap: .75rem !important;
        padding-bottom: .5rem !important; -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .stats-overview-grid::-webkit-scrollbar { display: none; }
    .stat-card { flex: 0 0 auto !important; min-width: 150px !important; padding: var(--spacing-4); }
    .stat-icon { width: 40px !important; height: 40px !important; font-size: 1.1rem; }
    .stat-value { font-size: 1.4rem; }
    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 14px; }
    .ios-pay-item { padding: 12px 14px; }
    .ios-pay-icon { width: 36px; height: 36px; font-size: 14px; }
    .ios-pay-title { font-size: 14px; }
}
@media (max-width: 480px) {
    .stat-card { min-width: 130px; }
    .stat-value { font-size: 1.25rem; }
    .ios-pay-date { display: none; }
}
</style>

<!-- Desktop Page Header -->
<div class="content-header d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h1 class="content-title">My Payments</h1>
        <p class="content-subtitle">View and pay your membership dues.</p>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-overview-grid mb-4">
    <div class="stat-card stat-success">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Paid</div>
            <div class="stat-value"><?php echo formatCurrency($totalPaid); ?></div>
            <div class="stat-detail"><?php echo $paidCount; ?> payment<?php echo $paidCount != 1 ? 's' : ''; ?></div>
        </div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?php echo $pendingCount; ?></div>
            <div class="stat-detail">Awaiting payment</div>
        </div>
    </div>
    <div class="stat-card stat-danger">
        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-content">
            <div class="stat-label">Overdue</div>
            <div class="stat-value"><?php echo $overdueCount; ?></div>
            <div class="stat-detail">Requires immediate action</div>
        </div>
    </div>
    <div class="stat-card stat-primary">
        <div class="stat-icon"><i class="fas fa-receipt"></i></div>
        <div class="stat-content">
            <div class="stat-label">Total Records</div>
            <div class="stat-value"><?php echo number_format($total); ?></div>
            <div class="stat-detail">All time</div>
        </div>
    </div>
</div>

<!-- ===== OUTSTANDING DUES ===== -->
<?php if (!empty($pending)): ?>
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-icon orange"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="ios-section-title">
            <h5>Outstanding Dues</h5>
            <p><?php echo count($pending); ?> unpaid</p>
        </div>
    </div>
    <div class="ios-section-body">
        <?php foreach ($pending as $p):
            $total_due = (float)$p['amount'] + (float)$p['penalty_applied'];
        ?>
        <div class="ios-pay-item">
            <div class="ios-pay-icon <?php echo $p['status']; ?>">
                <i class="fas fa-<?php echo $p['status'] === 'overdue' ? 'exclamation' : 'clock'; ?>"></i>
            </div>
            <div class="ios-pay-content">
                <p class="ios-pay-title"><?php echo e($p['due_title']); ?></p>
                <p class="ios-pay-sub"><?php echo e(str_replace('_', ' ', ucfirst($p['frequency']))); ?> · Due <?php echo formatDate($p['due_date']); ?></p>
                <?php if ($p['penalty_applied'] > 0): ?>
                <p class="ios-pay-penalty">+<?php echo formatCurrency((float)$p['penalty_applied']); ?> penalty</p>
                <?php endif; ?>
            </div>
            <div class="ios-pay-meta">
                <span class="ios-pay-amount"><?php echo formatCurrency($total_due); ?></span>
                <span class="ios-status-badge <?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>payments/pay.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                <button type="submit" class="ios-pay-btn">Pay</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ===== PAYMENT HISTORY ===== -->
<div class="ios-section-card">
    <div class="ios-section-header">
        <div class="ios-section-icon blue"><i class="fas fa-wallet"></i></div>
        <div class="ios-section-title">
            <h5>Payment History</h5>
            <p><?php echo number_format($total); ?> record<?php echo $total != 1 ? 's' : ''; ?></p>
        </div>
        <button class="ios-options-btn" onclick="openIosMenu()" aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <!-- Filter Pills -->
    <div class="ios-filter-pills">
        <a class="ios-filter-pill active" data-filter="all" href="#">
            All <span class="count"><?php echo $total; ?></span>
        </a>
        <a class="ios-filter-pill" data-filter="paid" href="#">
            Paid <span class="count"><?php echo $paidCount; ?></span>
        </a>
        <a class="ios-filter-pill" data-filter="pending" href="#">
            Pending <span class="count"><?php echo $pendingCount; ?></span>
        </a>
        <a class="ios-filter-pill" data-filter="overdue" href="#">
            Overdue <span class="count"><?php echo $overdueCount; ?></span>
        </a>
    </div>

    <!-- Search Box -->
    <div class="ios-search-box">
        <div class="ios-search-input-wrapper">
            <i class="fas fa-search ios-search-icon"></i>
            <input type="text" id="paySearch" class="ios-search-input" placeholder="Search payments..." autocomplete="off">
            <button class="ios-search-clear" id="clearSearch"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <!-- List / Empty State -->
    <?php if (empty($allHistory)): ?>
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-receipt"></i></div>
        <h3 class="ios-empty-title">No payments yet</h3>
        <p class="ios-empty-desc">Your payment history will appear here once dues are assigned.</p>
    </div>
    <?php else: ?>
    <div id="paymentsList" class="ios-section-body">
        <?php foreach ($allHistory as $p): ?>
        <div class="ios-pay-item pay-row"
             data-status="<?php echo $p['status']; ?>"
             data-title="<?php echo htmlspecialchars(strtolower($p['due_title']), ENT_QUOTES); ?>">
            <div class="ios-pay-icon <?php echo $p['status']; ?>">
                <i class="fas fa-<?php echo $p['status'] === 'paid' ? 'check' : ($p['status'] === 'overdue' ? 'exclamation' : 'clock'); ?>"></i>
            </div>
            <div class="ios-pay-content">
                <p class="ios-pay-title"><?php echo e($p['due_title']); ?></p>
                <p class="ios-pay-sub"><?php echo e(str_replace('_', ' ', ucfirst($p['frequency']))); ?> · <?php echo formatDate($p['due_date']); ?></p>
                <?php if ($p['penalty_applied'] > 0): ?>
                <p class="ios-pay-penalty">+<?php echo formatCurrency((float)$p['penalty_applied']); ?> penalty</p>
                <?php endif; ?>
            </div>
            <div class="ios-pay-meta">
                <span class="ios-pay-amount"><?php echo formatCurrency((float)$p['amount']); ?></span>
                <span class="ios-status-badge <?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span>
                <?php if ($p['payment_date']): ?>
                <span class="ios-pay-date"><?php echo formatDate($p['payment_date'], 'd M Y'); ?></span>
                <?php endif; ?>
            </div>
            <?php if ($p['status'] === 'paid'): ?>
            <a href="<?php echo BASE_URL; ?>payments/receipt.php?id=<?php echo $p['id']; ?>"
               target="_blank" class="ios-actions-btn" title="View receipt">
                <i class="fas fa-receipt"></i>
            </a>
            <?php elseif (in_array($p['status'], ['pending', 'overdue'])): ?>
            <form method="POST" action="<?php echo BASE_URL; ?>payments/pay.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                <button type="submit" class="ios-pay-now-btn">Pay</button>
            </form>
            <?php else: ?>
            <div style="width:32px;flex-shrink:0"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- No Results (JS) -->
    <div id="noResults" class="ios-empty-state" style="display:none">
        <div class="ios-empty-icon"><i class="fas fa-search"></i></div>
        <h3 class="ios-empty-title">No payments found</h3>
        <p class="ios-empty-desc">No payments match your current filter or search.</p>
    </div>

    <!-- Pagination -->
    <div class="ios-pagination-info" id="paginationInfo"></div>
    <div class="ios-pagination" id="paginationBtns"></div>
    <?php endif; ?>
</div>

<!-- ===== MOBILE BOTTOM SHEET ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">My Payments</h3>
        <button class="ios-menu-close" id="iosMenuClose"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <!-- Stats Summary -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Overview</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Paid</span>
                    <span class="ios-menu-stat-value success"><?php echo formatCurrency($totalPaid); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Pending</span>
                    <span class="ios-menu-stat-value warning"><?php echo $pendingCount; ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Overdue</span>
                    <span class="ios-menu-stat-value danger"><?php echo $overdueCount; ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Records</span>
                    <span class="ios-menu-stat-value"><?php echo $total; ?></span>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Filter Payments</div>
            <div class="ios-menu-card">
                <button class="ios-menu-item" onclick="filterFromMenu('all')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">All Payments</span>
                            <span class="ios-menu-item-desc"><?php echo $total; ?> total</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <button class="ios-menu-item" onclick="filterFromMenu('paid')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-check"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Paid</span>
                            <span class="ios-menu-item-desc"><?php echo $paidCount; ?> payment<?php echo $paidCount != 1 ? 's' : ''; ?></span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <button class="ios-menu-item" onclick="filterFromMenu('pending')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-clock"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Pending</span>
                            <span class="ios-menu-item-desc"><?php echo $pendingCount; ?> awaiting</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <button class="ios-menu-item" onclick="filterFromMenu('overdue')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon red"><i class="fas fa-exclamation"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Overdue</span>
                            <span class="ios-menu-item-desc"><?php echo $overdueCount; ?> overdue</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
            </div>
        </div>

        <!-- Navigation -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Navigate</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-home"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Dashboard</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>profile/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-user"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">My Profile</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    // ── Bottom Sheet ────────────────────────────────────────────────
    var backdrop = document.getElementById('iosMenuBackdrop');
    var modal    = document.getElementById('iosMenuModal');
    var closeBtn = document.getElementById('iosMenuClose');
    var startY   = 0;

    function openIosMenu()  { backdrop.classList.add('active'); modal.classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeIosMenu() { backdrop.classList.remove('active'); modal.classList.remove('active'); document.body.style.overflow = ''; }

    window.openIosMenu  = openIosMenu;
    window.closeIosMenu = closeIosMenu;

    if (closeBtn) closeBtn.addEventListener('click', closeIosMenu);
    if (backdrop) backdrop.addEventListener('click', closeIosMenu);
    if (modal) {
        modal.addEventListener('touchstart', function(e){ startY = e.touches[0].clientY; }, {passive:true});
        modal.addEventListener('touchend',   function(e){ if (e.changedTouches[0].clientY - startY > 80) closeIosMenu(); }, {passive:true});
    }

    // ── Filter + Search + Pagination ───────────────────────────────
    var currentFilter = 'all';
    var currentSearch = '';
    var currentPage   = 1;
    var perPage       = 15;

    var allRows      = Array.from(document.querySelectorAll('.pay-row'));
    var listEl       = document.getElementById('paymentsList');
    var noResultsEl  = document.getElementById('noResults');
    var infoEl       = document.getElementById('paginationInfo');
    var paginationEl = document.getElementById('paginationBtns');

    function getVisible() {
        return allRows.filter(function(row) {
            var statusOk = currentFilter === 'all' || row.dataset.status === currentFilter;
            var searchOk = !currentSearch || row.dataset.title.includes(currentSearch);
            return statusOk && searchOk;
        });
    }

    function render() {
        var visible    = getVisible();
        var total      = visible.length;
        var totalPages = Math.ceil(total / perPage) || 1;
        if (currentPage > totalPages) currentPage = 1;

        allRows.forEach(function(r) { r.style.display = 'none'; });
        var start = (currentPage - 1) * perPage;
        visible.slice(start, start + perPage).forEach(function(r) { r.style.display = ''; });

        if (noResultsEl) noResultsEl.style.display = total === 0 ? 'block' : 'none';
        if (listEl)      listEl.style.display      = total === 0 ? 'none'  : '';

        if (infoEl) {
            infoEl.textContent = total === 0 ? '' :
                'Showing ' + (start + 1) + '–' + Math.min(start + perPage, total) + ' of ' + total + ' record' + (total !== 1 ? 's' : '');
        }

        if (paginationEl) {
            paginationEl.innerHTML = '';
            if (totalPages <= 1) return;

            function btn(label, page, disabled, active) {
                var b = document.createElement('button');
                b.className = 'ios-page-btn' + (active ? ' active' : '');
                b.innerHTML = label;
                b.disabled  = disabled;
                if (!disabled) b.onclick = function() { currentPage = page; render(); };
                paginationEl.appendChild(b);
            }

            btn('<i class="fas fa-chevron-left"></i>', currentPage - 1, currentPage === 1, false);
            var s = Math.max(1, currentPage - 2), e = Math.min(totalPages, currentPage + 2);
            for (var i = s; i <= e; i++) btn(i, i, false, i === currentPage);
            btn('<i class="fas fa-chevron-right"></i>', currentPage + 1, currentPage === totalPages, false);
        }
    }

    // Filter pills
    document.querySelectorAll('.ios-filter-pill').forEach(function(pill) {
        pill.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('.ios-filter-pill').forEach(function(p) { p.classList.remove('active'); });
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            currentPage   = 1;
            render();
        });
    });

    // Search
    var searchInput = document.getElementById('paySearch');
    var clearBtn    = document.getElementById('clearSearch');
    if (searchInput) {
        var t;
        searchInput.addEventListener('input', function() {
            currentSearch = this.value.toLowerCase().trim();
            if (clearBtn) clearBtn.classList.toggle('visible', currentSearch.length > 0);
            clearTimeout(t);
            t = setTimeout(function() { currentPage = 1; render(); }, 300);
        });
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                currentSearch = '';
                this.classList.remove('visible');
                currentPage = 1;
                render();
                searchInput.focus();
            });
        }
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                this.value = ''; currentSearch = '';
                if (clearBtn) clearBtn.classList.remove('visible');
                currentPage = 1; render();
            }
        });
    }

    // Filter from mobile menu
    window.filterFromMenu = function(filter) {
        currentFilter = filter;
        currentPage   = 1;
        document.querySelectorAll('.ios-filter-pill').forEach(function(p) {
            p.classList.toggle('active', p.dataset.filter === filter);
        });
        closeIosMenu();
        render();
    };

    render();
})();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
