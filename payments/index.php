<?php
/**
 * All Payments — Admin view — iOS styled
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Due.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireAdmin();

$payObj = new Payment();
$payObj->processOverdue();

$pageTitle = 'Payments';
$db = Database::getInstance();

$filters = [
    'status' => in_array($_GET['status'] ?? '', ['pending','paid','overdue','reversed']) ? $_GET['status'] : '',
    'due_id' => (int)($_GET['due_id'] ?? 0),
    'search' => sanitize($_GET['search'] ?? ''),
    'month'  => sanitize($_GET['month']  ?? ''),
];

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$total    = $payObj->countAll($filters);
$paged    = paginate($total, $perPage, $page);
$payments = $payObj->getAll($page, $perPage, $filters);
$stats    = $payObj->getStats();
$dueObj   = new Due();
$allDues  = $dueObj->getActive();

// Status counts for filter pills
$rawCounts = $db->fetchAll("SELECT status, COUNT(*) as n FROM payments GROUP BY status");
$statusCounts = ['paid' => 0, 'pending' => 0, 'overdue' => 0, 'reversed' => 0];
foreach ($rawCounts as $rc) {
    if (isset($statusCounts[$rc['status']])) $statusCounts[$rc['status']] = (int)$rc['n'];
}
$totalAll = array_sum($statusCounts);

// Build query string helper for pagination links
function buildPayQS(array $f, int $page): string {
    $parts = ["page=$page"];
    if ($f['status'])  $parts[] = 'status='  . urlencode($f['status']);
    if ($f['due_id'])  $parts[] = 'due_id='  . $f['due_id'];
    if ($f['search'])  $parts[] = 'search='  . urlencode($f['search']);
    if ($f['month'])   $parts[] = 'month='   . urlencode($f['month']);
    return '?' . implode('&', $parts);
}

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
    --ios-gray:   #8E8E93;
}

/* ── Stats Grid ─────────────────────────────────────────── */
.stats-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-4); margin-bottom: var(--spacing-6);
}
.stat-card {
    background: transparent; border: 1px solid var(--border-color);
    border-radius: var(--border-radius-lg); padding: var(--spacing-5);
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
    background: var(--bg-primary); border: 1px solid var(--border-color);
    border-radius: 16px; overflow: hidden; margin-bottom: var(--spacing-4);
}
.ios-section-header {
    display: flex; align-items: center; gap: var(--spacing-3);
    padding: var(--spacing-4); background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-section-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.ios-section-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-section-icon.blue   { background: rgba(10,132,255,.15);  color: var(--ios-blue); }
.ios-section-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

/* ── 3-Dot Options Button ───────────────────────────────── */
.ios-options-btn {
    display: none; width: 36px; height: 36px; border-radius: 50%;
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
    padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 500;
    text-decoration: none; white-space: nowrap; cursor: pointer;
    border: 1px solid var(--border-color);
    background: var(--bg-secondary); color: var(--text-secondary);
    transition: all .2s ease;
}
.ios-filter-pill:hover { background: var(--border-color); color: var(--text-primary); text-decoration: none; }
.ios-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-filter-pill .count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,.2); }
.ios-filter-pill:not(.active) .count { background: var(--border-color); color: var(--text-muted); }

/* ── Filter Bar (search + dropdowns + btns) ─────────────── */
.ios-filter-bar {
    padding: 12px 16px; background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
    display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end;
}
.ios-filter-group { display: flex; flex-direction: column; gap: 4px; }
.ios-filter-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: var(--text-muted); }
.ios-filter-input, .ios-filter-select {
    padding: 8px 12px; border-radius: 10px;
    border: 1px solid var(--border-color);
    background: var(--bg-primary); color: var(--text-primary);
    font-size: 14px; transition: border-color .2s, box-shadow .2s;
    font-family: inherit;
}
.ios-filter-input:focus, .ios-filter-select:focus {
    outline: none; border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10,132,255,.1);
}
.ios-filter-input::placeholder { color: var(--text-muted); }
.ios-filter-input { min-width: 200px; }
.ios-filter-select { min-width: 140px; cursor: pointer; }
.ios-filter-btns { display: flex; gap: 8px; align-items: flex-end; }
.ios-filter-submit {
    padding: 9px 18px; border-radius: 10px;
    background: var(--ios-blue); color: #fff;
    font-size: 14px; font-weight: 600; border: none; cursor: pointer; transition: opacity .2s;
}
.ios-filter-submit:hover { opacity: .85; }
.ios-filter-clear {
    padding: 9px 14px; border-radius: 10px;
    background: var(--bg-secondary); color: var(--text-secondary);
    font-size: 14px; font-weight: 500; border: 1px solid var(--border-color);
    text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
    transition: background .2s;
}
.ios-filter-clear:hover { background: var(--border-color); color: var(--text-primary); text-decoration: none; }

/* ── Payment List Item ──────────────────────────────────── */
.ios-pay-item {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px; background: var(--bg-primary);
    border-bottom: 1px solid var(--border-color); transition: background .15s;
}
.ios-pay-item:last-child { border-bottom: none; }
.ios-pay-item:hover { background: var(--bg-secondary); }

/* Member avatar (initials circle) */
.ios-member-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; font-weight: 700; color: #fff; flex-shrink: 0;
    background: linear-gradient(135deg, var(--ios-blue), #0062cc);
}

.ios-pay-content { flex: 1; min-width: 0; }
.ios-pay-member  { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-pay-code    { font-size: 12px; color: var(--text-muted); margin: 0 0 2px; }
.ios-pay-sub     { font-size: 12px; color: var(--text-secondary); margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-pay-method  { font-size: 11px; color: var(--text-muted); margin: 2px 0 0; }

.ios-pay-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.ios-pay-amount  { font-size: 15px; font-weight: 700; color: var(--text-primary); white-space: nowrap; }
.ios-pay-penalty { font-size: 11px; color: var(--ios-red); white-space: nowrap; }

.ios-status-badge {
    font-size: 11px; font-weight: 600; padding: 3px 8px;
    border-radius: 6px; text-transform: capitalize; white-space: nowrap;
}
.ios-status-badge.paid     { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-status-badge.pending  { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-status-badge.overdue  { background: rgba(255,69,58,.15);  color: var(--ios-red); }
.ios-status-badge.reversed { background: rgba(142,142,147,.15); color: var(--ios-gray); }

.ios-actions-btn {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; color: var(--text-secondary); font-size: 14px;
    flex-shrink: 0; transition: background .2s;
}
.ios-actions-btn:hover { background: var(--border-color); color: var(--text-primary); }

/* ── Empty State ────────────────────────────────────────── */
.ios-empty-state { text-align: center; padding: 48px 24px; }
.ios-empty-icon  { font-size: 56px; color: var(--text-secondary); margin-bottom: 16px; opacity: .5; }
.ios-empty-title { font-size: 18px; font-weight: 600; color: var(--text-primary); margin: 0 0 8px; }
.ios-empty-desc  { font-size: 14px; color: var(--text-secondary); margin: 0; line-height: 1.5; }

/* ── Pagination ─────────────────────────────────────────── */
.ios-pagination {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    padding: 16px; border-top: 1px solid var(--border-color);
}
.ios-page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px;
    font-size: 14px; font-weight: 500; text-decoration: none;
    color: var(--text-secondary); background: var(--bg-secondary);
    border: 1px solid var(--border-color); transition: all .2s;
}
.ios-page-btn:hover { background: var(--border-color); color: var(--text-primary); text-decoration: none; }
.ios-page-btn.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-pagination-info { font-size: 12px; color: var(--text-muted); text-align: center; padding: 12px 16px 0; }

/* ── Bottom Sheet (shared styles) ───────────────────────── */
.ios-menu-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.4);
    backdrop-filter: blur(4px); z-index: 9998;
    opacity: 0; visibility: hidden; transition: .3s;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-bottom-sheet {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-primary); border-radius: 16px 16px 0 0;
    z-index: 9999; transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 88vh; overflow: hidden; display: flex; flex-direction: column;
}
.ios-bottom-sheet.active { transform: translateY(0); }
.ios-sheet-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-sheet-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0;
}
.ios-sheet-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-sheet-subtitle { font-size: 13px; color: var(--text-secondary); margin: 2px 0 0; }
.ios-sheet-close {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--bg-secondary); border: none;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary); cursor: pointer; flex-shrink: 0;
}
.ios-sheet-close:hover { background: var(--border-color); }
.ios-sheet-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-sheet-section { margin-bottom: 20px; }
.ios-sheet-section:last-child { margin-bottom: 0; }
.ios-sheet-section-title {
    font-size: 13px; font-weight: 600; color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-left: 4px;
}
.ios-sheet-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }

/* ── Sheet List Items ───────────────────────────────────── */
.ios-sheet-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; border-bottom: 1px solid var(--border-color);
    text-decoration: none; color: var(--text-primary); transition: background .15s;
    cursor: pointer; width: 100%; background: transparent;
    border-left: none; border-right: none; border-top: none;
    font-family: inherit; font-size: inherit; text-align: left;
}
.ios-sheet-item:last-child { border-bottom: none; }
.ios-sheet-item:active { background: var(--bg-subtle); }
.ios-sheet-item:hover { text-decoration: none; color: var(--text-primary); }
.ios-sheet-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-sheet-item-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.ios-sheet-item-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-sheet-item-icon.blue   { background: rgba(10,132,255,.15);  color: var(--ios-blue); }
.ios-sheet-item-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-sheet-item-icon.red    { background: rgba(255,69,58,.15);   color: var(--ios-red); }
.ios-sheet-item-icon.purple { background: rgba(191,90,242,.15);  color: var(--ios-purple); }
.ios-sheet-item-icon.gray   { background: rgba(142,142,147,.15); color: var(--ios-gray); }
.ios-sheet-item-content { flex: 1; min-width: 0; }
.ios-sheet-item-label { font-size: 15px; font-weight: 500; }
.ios-sheet-item-desc  { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-sheet-item-chevron { color: var(--text-muted); font-size: 12px; }
.ios-sheet-stat-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px; border-bottom: 1px solid var(--border-color);
}
.ios-sheet-stat-row:last-child { border-bottom: none; }
.ios-sheet-stat-label { font-size: 14px; color: var(--text-primary); }
.ios-sheet-stat-value { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.ios-sheet-stat-value.success { color: var(--ios-green); }
.ios-sheet-stat-value.warning { color: var(--ios-orange); }
.ios-sheet-stat-value.danger  { color: var(--ios-red); }

/* ── Action Items (green/orange/red variants) ───────────── */
.ios-action-item {
    display: flex; align-items: center; gap: 14px;
    padding: 16px; border-bottom: 1px solid var(--border-color);
    cursor: pointer; width: 100%; background: transparent;
    border-left: none; border-right: none; border-top: none;
    font-family: inherit; text-align: left; transition: background .15s;
    text-decoration: none; color: var(--text-primary);
}
.ios-action-item:last-child { border-bottom: none; }
.ios-action-item:active { background: var(--bg-subtle); }
.ios-action-item:hover  { text-decoration: none; }
.ios-action-item i      { width: 22px; font-size: 18px; text-align: center; flex-shrink: 0; }
.ios-action-item span   { font-size: 17px; font-weight: 400; }
.ios-action-item.success i, .ios-action-item.success span { color: var(--ios-green); }
.ios-action-item.primary i, .ios-action-item.primary span { color: var(--ios-blue); }
.ios-action-item.warning i, .ios-action-item.warning span { color: var(--ios-orange); }
.ios-action-item.danger  i, .ios-action-item.danger  span { color: var(--ios-red); }
.ios-action-cancel {
    display: block; width: calc(100% - 32px); margin: 8px 16px 16px;
    padding: 14px; background: var(--bg-secondary); border: none; border-radius: 12px;
    font-size: 17px; font-weight: 600; color: var(--ios-blue);
    text-align: center; cursor: pointer; font-family: inherit;
}
.ios-action-cancel:active { background: var(--border-color); }

/* ── Form inside sheet ──────────────────────────────────── */
.ios-sheet-form { padding: 16px; }
.ios-form-group { margin-bottom: 16px; }
.ios-form-label {
    display: block; font-size: 11px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .6px;
    color: var(--text-muted); margin-bottom: 8px;
}
.ios-form-input, .ios-form-textarea {
    width: 100%; padding: 12px 14px; border-radius: 12px;
    border: 1px solid var(--border-color);
    background: var(--bg-secondary); color: var(--text-primary);
    font-size: 16px; font-family: inherit; box-sizing: border-box;
    transition: border-color .2s, box-shadow .2s;
}
.ios-form-input:focus, .ios-form-textarea:focus {
    outline: none; border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10,132,255,.1);
}
.ios-form-textarea { resize: vertical; min-height: 80px; }
.ios-form-hint { font-size: 12px; color: var(--text-muted); margin-top: 6px; }
.ios-form-info-card {
    background: var(--bg-secondary); border-radius: 12px; padding: 14px;
    margin-bottom: 16px;
}
.ios-form-info-name  { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px; }
.ios-form-info-due   { font-size: 13px; color: var(--text-secondary); margin: 0 0 10px; }
.ios-form-info-total {
    display: flex; align-items: center; justify-content: space-between;
    padding-top: 10px; border-top: 1px solid var(--border-color);
}
.ios-form-info-total span { font-size: 14px; color: var(--text-muted); }
.ios-form-info-total strong { font-size: 18px; font-weight: 700; color: var(--ios-green); }
.ios-form-btn {
    width: 100%; padding: 14px; border-radius: 12px; border: none;
    font-size: 17px; font-weight: 600; cursor: pointer; font-family: inherit;
    transition: opacity .2s;
}
.ios-form-btn.success { background: var(--ios-green); color: #fff; }
.ios-form-btn.warning { background: var(--ios-orange); color: #fff; }
.ios-form-btn:hover { opacity: .9; }

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
    .ios-filter-bar { gap: 8px; }
    .ios-filter-input { min-width: 0; flex: 1; }
    .ios-filter-select { min-width: 0; }
    .ios-pay-item { padding: 12px 14px; }
    .ios-member-avatar { width: 38px; height: 38px; font-size: 14px; }
    .ios-pay-member { font-size: 14px; }
}
@media (max-width: 480px) {
    .stat-card { min-width: 130px; }
    .stat-value { font-size: 1.25rem; }
    .ios-pay-code   { display: none; }
    .ios-pay-method { display: none; }
    .ios-filter-label { display: none; }
}
</style>

<!-- Desktop Page Header -->
<div class="content-header d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h1 class="content-title">Payments</h1>
        <p class="content-subtitle">Track all member payments and dues.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>payments/dues.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-file-invoice-dollar me-2"></i>Manage Dues
    </a>
</div>

<!-- Stats Grid -->
<div class="stats-overview-grid mb-4">
    <div class="stat-card stat-success">
        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
        <div class="stat-content">
            <div class="stat-label">Collected</div>
            <div class="stat-value"><?php echo formatCurrency((float)$stats['total_collected']); ?></div>
            <div class="stat-detail"><?php echo number_format($statusCounts['paid']); ?> paid records</div>
        </div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-icon"><i class="fas fa-clock"></i></div>
        <div class="stat-content">
            <div class="stat-label">Pending</div>
            <div class="stat-value"><?php echo $stats['pending_count']; ?></div>
            <div class="stat-detail">Awaiting payment</div>
        </div>
    </div>
    <div class="stat-card stat-danger">
        <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="stat-content">
            <div class="stat-label">Overdue</div>
            <div class="stat-value"><?php echo $stats['overdue_count']; ?></div>
            <div class="stat-detail">Requires action</div>
        </div>
    </div>
    <div class="stat-card stat-primary">
        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        <div class="stat-content">
            <div class="stat-label">Pending Amount</div>
            <div class="stat-value"><?php echo formatCurrency((float)$stats['total_pending']); ?></div>
            <div class="stat-detail">Uncollected dues</div>
        </div>
    </div>
</div>

<!-- ===== PAYMENTS SECTION ===== -->
<div class="ios-section-card">

    <!-- Header -->
    <div class="ios-section-header">
        <div class="ios-section-icon green"><i class="fas fa-wallet"></i></div>
        <div class="ios-section-title">
            <h5>All Payments</h5>
            <p><?php echo number_format($total); ?> record<?php echo $total != 1 ? 's' : ''; ?> found</p>
        </div>
        <button class="ios-options-btn" onclick="openPageMenu()" aria-label="Options">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>

    <!-- Status Filter Pills -->
    <div class="ios-filter-pills">
        <?php
        $activeStatus = $filters['status'];
        $pills = [
            '' => ['label' => 'All', 'count' => $totalAll],
            'paid'     => ['label' => 'Paid',     'count' => $statusCounts['paid']],
            'pending'  => ['label' => 'Pending',  'count' => $statusCounts['pending']],
            'overdue'  => ['label' => 'Overdue',  'count' => $statusCounts['overdue']],
            'reversed' => ['label' => 'Reversed', 'count' => $statusCounts['reversed']],
        ];
        foreach ($pills as $val => $pill):
            $isActive = $activeStatus === $val;
            $qs = http_build_query(array_filter([
                'status' => $val,
                'due_id' => $filters['due_id'] ?: null,
                'search' => $filters['search'] ?: null,
                'month'  => $filters['month']  ?: null,
            ]));
        ?>
        <a href="?<?php echo $qs; ?>" class="ios-filter-pill <?php echo $isActive ? 'active' : ''; ?>">
            <?php echo $pill['label']; ?>
            <span class="count"><?php echo number_format($pill['count']); ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search + Filters Form -->
    <form method="GET" action="">
        <?php if ($filters['status']): ?>
        <input type="hidden" name="status" value="<?php echo e($filters['status']); ?>">
        <?php endif; ?>
        <div class="ios-filter-bar">
            <div class="ios-filter-group" style="flex:1;min-width:180px">
                <label class="ios-filter-label">Search</label>
                <input type="text" name="search" class="ios-filter-input"
                       placeholder="Name, member ID or reference…"
                       value="<?php echo e($filters['search']); ?>">
            </div>
            <div class="ios-filter-group">
                <label class="ios-filter-label">Due</label>
                <select name="due_id" class="ios-filter-select">
                    <option value="">All Dues</option>
                    <?php foreach ($allDues as $d): ?>
                    <option value="<?php echo $d['id']; ?>" <?php echo $filters['due_id'] == $d['id'] ? 'selected' : ''; ?>>
                        <?php echo e($d['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ios-filter-group">
                <label class="ios-filter-label">Month</label>
                <input type="month" name="month" class="ios-filter-input"
                       style="min-width:140px" value="<?php echo e($filters['month']); ?>">
            </div>
            <div class="ios-filter-btns">
                <button type="submit" class="ios-filter-submit">Filter</button>
                <a href="<?php echo BASE_URL; ?>payments/" class="ios-filter-clear">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </div>
    </form>

    <!-- Payment List -->
    <?php if (empty($payments)): ?>
    <div class="ios-empty-state">
        <div class="ios-empty-icon"><i class="fas fa-receipt"></i></div>
        <h3 class="ios-empty-title">No payments found</h3>
        <p class="ios-empty-desc">No payments match your current filters. Try adjusting them.</p>
    </div>
    <?php else: ?>
    <div class="ios-section-body">
        <?php foreach ($payments as $p):
            $initials  = strtoupper(substr($p['full_name'], 0, 1));
            $totalDue  = (float)$p['amount'] + (float)$p['penalty_applied'];
            $amountFmt = formatCurrency((float)$p['amount']);
            $totalFmt  = formatCurrency($totalDue);
        ?>
        <div class="ios-pay-item"
             data-id="<?php echo $p['id']; ?>"
             data-member="<?php echo htmlspecialchars($p['full_name'], ENT_QUOTES); ?>"
             data-due="<?php echo htmlspecialchars($p['due_title'], ENT_QUOTES); ?>"
             data-status="<?php echo $p['status']; ?>"
             data-amount="<?php echo htmlspecialchars($totalFmt, ENT_QUOTES); ?>"
             data-code="<?php echo htmlspecialchars($p['member_code'], ENT_QUOTES); ?>">
            <div class="ios-member-avatar"><?php echo $initials; ?></div>
            <div class="ios-pay-content">
                <p class="ios-pay-member"><?php echo e($p['full_name']); ?></p>
                <p class="ios-pay-code"><?php echo e($p['member_code']); ?></p>
                <p class="ios-pay-sub">
                    <?php echo e($p['due_title']); ?> &middot;
                    <?php echo e(str_replace('_', ' ', ucfirst($p['frequency']))); ?> &middot;
                    Due <?php echo formatDate($p['due_date']); ?>
                </p>
                <?php if ($p['payment_date']): ?>
                <p class="ios-pay-method">
                    <?php if ($p['payment_method'] === 'paystack'): ?>
                    <i class="fas fa-credit-card" style="color:var(--ios-blue)"></i> Paystack &middot; <?php echo formatDate($p['payment_date'], 'd M Y'); ?>
                    <?php else: ?>
                    <i class="fas fa-hand-holding-usd" style="color:var(--ios-orange)"></i> Manual &middot; <?php echo formatDate($p['payment_date'], 'd M Y'); ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="ios-pay-meta">
                <span class="ios-pay-amount"><?php echo $amountFmt; ?></span>
                <span class="ios-status-badge <?php echo $p['status']; ?>"><?php echo ucfirst($p['status']); ?></span>
                <?php if ($p['penalty_applied'] > 0): ?>
                <span class="ios-pay-penalty">+<?php echo formatCurrency((float)$p['penalty_applied']); ?></span>
                <?php endif; ?>
            </div>
            <button class="ios-actions-btn" onclick="openActionSheet(this.closest('.ios-pay-item'))" aria-label="Actions">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($paged['total_pages'] > 1): ?>
    <div class="ios-pagination-info">
        Showing <?php echo min($total, ($page-1)*$perPage+1); ?>–<?php echo min($total, $page*$perPage); ?> of <?php echo number_format($total); ?> records
    </div>
    <div class="ios-pagination">
        <?php if ($paged['has_prev']): ?>
        <a href="<?php echo buildPayQS($filters, $page-1); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php endif; ?>
        <?php for ($i = max(1,$page-2); $i <= min($paged['total_pages'],$page+2); $i++): ?>
        <a href="<?php echo buildPayQS($filters, $i); ?>" class="ios-page-btn <?php echo $i===$page ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
        <?php if ($paged['has_next']): ?>
        <a href="<?php echo buildPayQS($filters, $page+1); ?>" class="ios-page-btn">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- ===== PAGE MENU (bottom sheet) ===== -->
<div class="ios-menu-backdrop" id="pageMenuBackdrop"></div>
<div class="ios-bottom-sheet" id="pageMenuSheet">
    <div class="ios-sheet-handle"></div>
    <div class="ios-sheet-header">
        <div>
            <h3 class="ios-sheet-title">Payments</h3>
        </div>
        <button class="ios-sheet-close" onclick="closePageMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-sheet-content">

        <!-- Stats -->
        <div class="ios-sheet-section">
            <div class="ios-sheet-section-title">Overview</div>
            <div class="ios-sheet-card">
                <div class="ios-sheet-stat-row">
                    <span class="ios-sheet-stat-label">Total Collected</span>
                    <span class="ios-sheet-stat-value success"><?php echo formatCurrency((float)$stats['total_collected']); ?></span>
                </div>
                <div class="ios-sheet-stat-row">
                    <span class="ios-sheet-stat-label">Pending Count</span>
                    <span class="ios-sheet-stat-value warning"><?php echo $stats['pending_count']; ?></span>
                </div>
                <div class="ios-sheet-stat-row">
                    <span class="ios-sheet-stat-label">Overdue Count</span>
                    <span class="ios-sheet-stat-value danger"><?php echo $stats['overdue_count']; ?></span>
                </div>
                <div class="ios-sheet-stat-row">
                    <span class="ios-sheet-stat-label">Pending Amount</span>
                    <span class="ios-sheet-stat-value warning"><?php echo formatCurrency((float)$stats['total_pending']); ?></span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="ios-sheet-section">
            <div class="ios-sheet-section-title">Quick Actions</div>
            <div class="ios-sheet-card">
                <a href="<?php echo BASE_URL; ?>payments/dues.php" class="ios-sheet-item">
                    <div class="ios-sheet-item-left">
                        <div class="ios-sheet-item-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
                        <div class="ios-sheet-item-content">
                            <span class="ios-sheet-item-label">Manage Dues</span>
                            <span class="ios-sheet-item-desc">View and edit due schedules</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-sheet-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>payments/due-form.php" class="ios-sheet-item">
                    <div class="ios-sheet-item-left">
                        <div class="ios-sheet-item-icon green"><i class="fas fa-plus"></i></div>
                        <div class="ios-sheet-item-content">
                            <span class="ios-sheet-item-label">Create New Due</span>
                            <span class="ios-sheet-item-desc">Add a new payment due</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-sheet-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>reports/index.php" class="ios-sheet-item">
                    <div class="ios-sheet-item-left">
                        <div class="ios-sheet-item-icon purple"><i class="fas fa-chart-bar"></i></div>
                        <div class="ios-sheet-item-content">
                            <span class="ios-sheet-item-label">Reports & Analytics</span>
                            <span class="ios-sheet-item-desc">Payment reports and exports</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-sheet-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-sheet-item">
                    <div class="ios-sheet-item-left">
                        <div class="ios-sheet-item-icon gray"><i class="fas fa-home"></i></div>
                        <div class="ios-sheet-item-content">
                            <span class="ios-sheet-item-label">Dashboard</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-sheet-item-chevron"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<!-- ===== ACTION SHEET (per payment) ===== -->
<div class="ios-menu-backdrop" id="actionBackdrop"></div>
<div class="ios-bottom-sheet" id="actionSheet" style="max-height:60vh">
    <div class="ios-sheet-handle"></div>
    <div class="ios-sheet-header">
        <div>
            <p class="ios-sheet-title" id="actionMemberName">Payment</p>
            <p class="ios-sheet-subtitle" id="actionDueName"></p>
        </div>
        <button class="ios-sheet-close" onclick="closeActionSheet()"><i class="fas fa-times"></i></button>
    </div>
    <div id="actionItems">
        <!-- Populated by JS -->
    </div>
    <button class="ios-action-cancel" onclick="closeActionSheet()">Cancel</button>
</div>

<!-- ===== MARK PAID SHEET ===== -->
<div class="ios-menu-backdrop" id="markPaidBackdrop"></div>
<div class="ios-bottom-sheet" id="markPaidSheet">
    <div class="ios-sheet-handle"></div>
    <div class="ios-sheet-header">
        <div>
            <h3 class="ios-sheet-title">Mark as Paid</h3>
            <p class="ios-sheet-subtitle">Manual payment confirmation</p>
        </div>
        <button class="ios-sheet-close" onclick="closeMarkPaid()"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?php echo BASE_URL; ?>payments/mark-paid.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="id" id="markPaidId">
        <div class="ios-sheet-form">
            <div class="ios-form-info-card">
                <p class="ios-form-info-name" id="markPaidMember"></p>
                <p class="ios-form-info-due"  id="markPaidDue"></p>
                <div class="ios-form-info-total">
                    <span>Total due</span>
                    <strong id="markPaidTotal"></strong>
                </div>
            </div>
            <div class="ios-form-group">
                <label class="ios-form-label" for="markPaidNotes">Notes (optional)</label>
                <textarea class="ios-form-textarea" id="markPaidNotes" name="notes" rows="3"
                          placeholder="e.g. Cash received at office, receipt #1234"></textarea>
            </div>
            <button type="submit" class="ios-form-btn success">
                <i class="fas fa-check me-2"></i>Mark as Paid
            </button>
        </div>
    </form>
</div>

<?php if (isSuperAdmin()): ?>
<!-- ===== REVERSE SHEET (super admin only) ===== -->
<div class="ios-menu-backdrop" id="reverseBackdrop"></div>
<div class="ios-bottom-sheet" id="reverseSheet">
    <div class="ios-sheet-handle"></div>
    <div class="ios-sheet-header">
        <div>
            <h3 class="ios-sheet-title">Reverse Payment</h3>
            <p class="ios-sheet-subtitle" id="reverseSubtitle"></p>
        </div>
        <button class="ios-sheet-close" onclick="closeReverse()"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" action="<?php echo BASE_URL; ?>payments/reverse.php">
        <?php echo csrfField(); ?>
        <input type="hidden" name="id" id="reverseId">
        <div class="ios-sheet-form">
            <div class="ios-form-group">
                <label class="ios-form-label" for="reverseNotes">Reason <span style="color:var(--ios-red)">*</span></label>
                <textarea class="ios-form-textarea" id="reverseNotes" name="notes" rows="3" required
                          placeholder="Reason for reversal (refund, error, etc.)…"></textarea>
                <p class="ios-form-hint">This will change the payment status to <em>reversed</em>.</p>
            </div>
            <button type="submit" class="ios-form-btn warning">
                <i class="fas fa-undo me-2"></i>Reverse Payment
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
(function () {
    var isSuperAdmin = <?php echo isSuperAdmin() ? 'true' : 'false'; ?>;
    var currentData  = null;

    // ── Page Menu ───────────────────────────────────────────────────
    var pageBackdrop = document.getElementById('pageMenuBackdrop');
    var pageSheet    = document.getElementById('pageMenuSheet');
    var startY = 0;

    window.openPageMenu  = function() { pageBackdrop.classList.add('active'); pageSheet.classList.add('active'); document.body.style.overflow='hidden'; };
    window.closePageMenu = function() { pageBackdrop.classList.remove('active'); pageSheet.classList.remove('active'); document.body.style.overflow=''; };

    if (pageBackdrop) pageBackdrop.addEventListener('click', closePageMenu);
    if (pageSheet) {
        pageSheet.addEventListener('touchstart', function(e){ startY=e.touches[0].clientY; }, {passive:true});
        pageSheet.addEventListener('touchend',   function(e){ if(e.changedTouches[0].clientY-startY>80) closePageMenu(); }, {passive:true});
    }

    // ── Action Sheet ────────────────────────────────────────────────
    var actionBackdrop = document.getElementById('actionBackdrop');
    var actionSheet    = document.getElementById('actionSheet');

    window.openActionSheet = function(row) {
        currentData = row.dataset;
        document.getElementById('actionMemberName').textContent = currentData.member;
        document.getElementById('actionDueName').textContent    = currentData.due + ' · ' + currentData.amount;

        var items = document.getElementById('actionItems');
        items.innerHTML = '';

        if (currentData.status === 'pending' || currentData.status === 'overdue') {
            items.innerHTML += '<button class="ios-action-item success" onclick="openMarkPaid()">' +
                '<i class="fas fa-check-circle"></i><span>Mark as Paid</span></button>';
        }
        if (currentData.status === 'paid') {
            items.innerHTML += '<a href="<?php echo BASE_URL; ?>payments/receipt.php?id=' + currentData.id +
                '" target="_blank" class="ios-action-item primary" onclick="closeActionSheet()">' +
                '<i class="fas fa-receipt"></i><span>View Receipt</span></a>';
            if (isSuperAdmin) {
                items.innerHTML += '<button class="ios-action-item warning" onclick="openReverse()">' +
                    '<i class="fas fa-undo"></i><span>Reverse Payment</span></button>';
            }
        }
        if (currentData.status === 'reversed') {
            items.innerHTML += '<span style="display:block;padding:20px;text-align:center;font-size:14px;color:var(--text-muted)">No actions available for reversed payments.</span>';
        }

        actionBackdrop.classList.add('active');
        actionSheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeActionSheet = function() {
        actionBackdrop.classList.remove('active');
        actionSheet.classList.remove('active');
        document.body.style.overflow = '';
    };

    if (actionBackdrop) actionBackdrop.addEventListener('click', closeActionSheet);
    if (actionSheet) {
        actionSheet.addEventListener('touchstart', function(e){ startY=e.touches[0].clientY; }, {passive:true});
        actionSheet.addEventListener('touchend',   function(e){ if(e.changedTouches[0].clientY-startY>80) closeActionSheet(); }, {passive:true});
    }

    // ── Mark Paid Sheet ─────────────────────────────────────────────
    var mpBackdrop = document.getElementById('markPaidBackdrop');
    var mpSheet    = document.getElementById('markPaidSheet');

    window.openMarkPaid = function() {
        closeActionSheet();
        document.getElementById('markPaidId').value          = currentData.id;
        document.getElementById('markPaidMember').textContent = currentData.member;
        document.getElementById('markPaidDue').textContent    = currentData.due + ' · ' + currentData.code;
        document.getElementById('markPaidTotal').textContent  = currentData.amount;
        mpBackdrop.classList.add('active');
        mpSheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeMarkPaid = function() {
        mpBackdrop.classList.remove('active');
        mpSheet.classList.remove('active');
        document.body.style.overflow = '';
    };

    if (mpBackdrop) mpBackdrop.addEventListener('click', closeMarkPaid);
    if (mpSheet) {
        mpSheet.addEventListener('touchstart', function(e){ startY=e.touches[0].clientY; }, {passive:true});
        mpSheet.addEventListener('touchend',   function(e){ if(e.changedTouches[0].clientY-startY>80) closeMarkPaid(); }, {passive:true});
    }

    // ── Reverse Sheet ───────────────────────────────────────────────
    var rvBackdrop = document.getElementById('reverseBackdrop');
    var rvSheet    = document.getElementById('reverseSheet');

    window.openReverse = function() {
        if (!rvSheet) return;
        closeActionSheet();
        document.getElementById('reverseId').value = currentData.id;
        document.getElementById('reverseSubtitle').textContent = currentData.member + ' — ' + currentData.due;
        rvBackdrop.classList.add('active');
        rvSheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeReverse = function() {
        if (!rvSheet) return;
        rvBackdrop.classList.remove('active');
        rvSheet.classList.remove('active');
        document.body.style.overflow = '';
    };

    if (rvBackdrop) rvBackdrop.addEventListener('click', closeReverse);
    if (rvSheet) {
        rvSheet.addEventListener('touchstart', function(e){ startY=e.touches[0].clientY; }, {passive:true});
        rvSheet.addEventListener('touchend',   function(e){ if(e.changedTouches[0].clientY-startY>80) closeReverse(); }, {passive:true});
    }
})();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
