<?php
/**
 * Reports & Analytics dashboard — admin only
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireAdmin();

$pageTitle = 'Reports & Analytics';
$db = Database::getInstance();

// ─── DATE RANGE ──────────────────────────────────────────────────────────────
$now   = new DateTime();
$year  = (int)($now->format('Y'));
$month = (int)($now->format('m'));

// ─── SUMMARY STATS ───────────────────────────────────────────────────────────
$totalMembers = (int)($db->fetchOne("SELECT COUNT(*) AS n FROM members")['n'] ?? 0);
$activeMembers = (int)($db->fetchOne("SELECT COUNT(*) AS n FROM members WHERE status='active'")['n'] ?? 0);

// Revenue this month
$revenueMonth = (float)($db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE status='paid' AND YEAR(paid_at)=? AND MONTH(paid_at)=?",
    [$year, $month]
)['s'] ?? 0);

// Revenue this year
$revenueYear = (float)($db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE status='paid' AND YEAR(paid_at)=?",
    [$year]
)['s'] ?? 0);

// Payment compliance (members who paid at least one due this cycle)
$totalDues = (int)($db->fetchOne("SELECT COUNT(*) AS n FROM dues WHERE is_mandatory=1")['n'] ?? 0);
$paidDues  = (int)($db->fetchOne(
    "SELECT COUNT(DISTINCT member_dues.member_id) AS n FROM member_dues WHERE status='paid'"
)['n'] ?? 0);
$compliance = $totalMembers > 0 ? round(($paidDues / $totalMembers) * 100) : 0;

// ─── MONTHLY REVENUE — last 12 months ────────────────────────────────────────
$monthlyRevenue = $db->fetchAll(
    "SELECT DATE_FORMAT(paid_at,'%Y-%m') AS ym, COALESCE(SUM(amount),0) AS total
     FROM payments WHERE status='paid' AND paid_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY ym ORDER BY ym ASC"
);

// Fill gaps so we always have 12 labels
$revenueLabels = [];
$revenueData   = [];
$revenueMap    = array_column($monthlyRevenue, 'total', 'ym');
for ($i = 11; $i >= 0; $i--) {
    $d  = new DateTime("first day of -$i month");
    $ym = $d->format('Y-m');
    $revenueLabels[] = $d->format('M Y');
    $revenueData[]   = (float)($revenueMap[$ym] ?? 0);
}

// ─── MEMBER GROWTH — last 12 months (cumulative) ─────────────────────────────
$memberGrowth = $db->fetchAll(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(*) AS cnt
     FROM members WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY ym ORDER BY ym ASC"
);
$growthMap  = array_column($memberGrowth, 'cnt', 'ym');
$growthData = [];
for ($i = 11; $i >= 0; $i--) {
    $ym = (new DateTime("first day of -$i month"))->format('Y-m');
    $growthData[] = (int)($growthMap[$ym] ?? 0);
}

// ─── PAYMENT STATUS DISTRIBUTION ─────────────────────────────────────────────
$paymentStatus = $db->fetchAll(
    "SELECT status, COUNT(*) AS cnt FROM member_dues GROUP BY status"
);
$statusLabels = [];
$statusData   = [];
$statusColors = ['paid' => '#00a76f', 'pending' => '#f59e0b', 'overdue' => '#ef4444', 'waived' => '#6366f1'];
foreach ($paymentStatus as $row) {
    $statusLabels[] = ucfirst($row['status']);
    $statusData[]   = (int)$row['cnt'];
}

// ─── MEMBERSHIP TYPE BREAKDOWN ────────────────────────────────────────────────
$memberTypes = $db->fetchAll(
    "SELECT membership_type, COUNT(*) AS cnt FROM members GROUP BY membership_type ORDER BY cnt DESC"
);

// ─── RECENT PAYMENTS ─────────────────────────────────────────────────────────
$recentPayments = $db->fetchAll(
    "SELECT p.amount, p.paid_at, p.method,
            CONCAT(m.first_name,' ',m.last_name) AS member_name,
            d.name AS due_name
     FROM payments p
     JOIN member_dues md ON md.id = p.member_due_id
     JOIN members m ON m.id = md.member_id
     JOIN dues d ON d.id = md.due_id
     WHERE p.status = 'paid'
     ORDER BY p.paid_at DESC LIMIT 10"
);

// ─── TOP CONTRIBUTORS ────────────────────────────────────────────────────────
$topContributors = $db->fetchAll(
    "SELECT CONCAT(m.first_name,' ',m.last_name) AS member_name,
            m.member_code, SUM(p.amount) AS total_paid
     FROM payments p
     JOIN member_dues md ON md.id = p.member_due_id
     JOIN members m ON m.id = md.member_id
     WHERE p.status = 'paid'
     GROUP BY m.id ORDER BY total_paid DESC LIMIT 5"
);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="content-header">
    <div>
        <h1 class="content-title">Reports & Analytics</h1>
        <p class="content-subtitle">Club financial and membership overview.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo BASE_URL; ?>reports/export.php?type=members" class="btn btn-secondary btn-sm">
            <i class="fas fa-file-csv me-1"></i>Export Members
        </a>
        <a href="<?php echo BASE_URL; ?>reports/export.php?type=payments" class="btn btn-secondary btn-sm">
            <i class="fas fa-file-csv me-1"></i>Export Payments
        </a>
        <a href="<?php echo BASE_URL; ?>reports/export.php?type=financial" class="btn btn-secondary btn-sm">
            <i class="fas fa-file-csv me-1"></i>Financial Summary
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fas fa-print me-1"></i>Print Report
        </button>
    </div>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(var(--primary-rgb),.1);color:var(--primary)">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Members</span>
                <span class="stat-value"><?php echo $totalMembers; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,163,74,.1);color:#10a34a">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Active Members</span>
                <span class="stat-value"><?php echo $activeMembers; ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(234,179,8,.1);color:#ca8a04">
                <i class="fas fa-naira-sign"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Revenue This Month</span>
                <span class="stat-value">₦<?php echo number_format($revenueMonth); ?></span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(99,102,241,.1);color:#6366f1">
                <i class="fas fa-percent"></i>
            </div>
            <div class="stat-info">
                <span class="stat-label">Payment Compliance</span>
                <span class="stat-value"><?php echo $compliance; ?>%</span>
            </div>
        </div>
    </div>
</div>

<!-- Charts row -->
<div class="row g-4 mb-4">

    <!-- Monthly Revenue Chart -->
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0"><i class="fas fa-chart-line me-2 text-primary"></i>Monthly Revenue (Last 12 Months)</h6>
                <span class="text-muted small">Year total: ₦<?php echo number_format($revenueYear); ?></span>
            </div>
            <div class="card-body" style="position:relative;height:280px">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Payment Status Pie -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="fas fa-chart-pie me-2 text-primary"></i>Due Payment Status</h6>
            </div>
            <div class="card-body" style="position:relative;height:280px;display:flex;align-items:center;justify-content:center">
                <?php if (empty($statusData)): ?>
                <p class="text-muted">No payment data yet.</p>
                <?php else: ?>
                <canvas id="statusChart"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">

    <!-- Member Growth Chart -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="fas fa-chart-bar me-2 text-primary"></i>New Members (Last 12 Months)</h6>
            </div>
            <div class="card-body" style="position:relative;height:240px">
                <canvas id="growthChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Membership Type -->
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="fas fa-id-card me-2 text-primary"></i>Membership Types</h6>
            </div>
            <div class="card-body">
                <?php if (empty($memberTypes)): ?>
                <p class="text-muted text-center py-4">No data.</p>
                <?php else: ?>
                <?php
                $typeTotal = array_sum(array_column($memberTypes, 'cnt'));
                $typeColors = ['#00a76f','#3b82f6','#f59e0b','#ef4444','#6366f1','#ec4899'];
                foreach ($memberTypes as $i => $mt):
                    $pct = $typeTotal > 0 ? round(($mt['cnt'] / $typeTotal) * 100) : 0;
                    $col = $typeColors[$i % count($typeColors)];
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1 small">
                        <span class="fw-medium"><?php echo e(ucwords(str_replace('_',' ',$mt['membership_type']))); ?></span>
                        <span class="text-muted"><?php echo $mt['cnt']; ?> (<?php echo $pct; ?>%)</span>
                    </div>
                    <div class="progress" style="height:8px;border-radius:4px;background:var(--bg-tertiary)">
                        <div class="progress-bar" style="width:<?php echo $pct; ?>%;background:<?php echo $col; ?>;border-radius:4px"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tables row -->
<div class="row g-4">

    <!-- Recent Payments -->
    <div class="col-12 col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0"><i class="fas fa-receipt me-2 text-primary"></i>Recent Payments</h6>
                <a href="<?php echo BASE_URL; ?>payments/" class="btn btn-secondary btn-sm">View All</a>
            </div>
            <?php if (empty($recentPayments)): ?>
            <div class="card-body text-center text-muted py-4">No payments recorded yet.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Due</th>
                            <th class="text-end">Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td class="fw-semibold small"><?php echo e($p['member_name']); ?></td>
                            <td class="text-muted small"><?php echo e($p['due_name']); ?></td>
                            <td class="text-end fw-semibold text-success">₦<?php echo number_format((float)$p['amount']); ?></td>
                            <td><span class="badge badge-info"><?php echo e(ucfirst($p['method'])); ?></span></td>
                            <td class="text-muted small"><?php echo formatDate($p['paid_at'],'d M Y'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Contributors -->
    <div class="col-12 col-lg-5">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="fas fa-star me-2 text-warning"></i>Top Contributors</h6>
            </div>
            <?php if (empty($topContributors)): ?>
            <div class="card-body text-center text-muted py-4">No payment data yet.</div>
            <?php else: ?>
            <div class="card-body p-0">
                <?php foreach ($topContributors as $i => $tc): ?>
                <div class="d-flex align-items-center gap-3 px-4 py-3 <?php echo $i < count($topContributors)-1 ? 'border-bottom' : ''; ?>">
                    <div class="fw-bold text-muted" style="width:24px;font-size:13px">#<?php echo $i+1; ?></div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold small"><?php echo e($tc['member_name']); ?></div>
                        <div class="text-muted" style="font-size:11px"><?php echo e($tc['member_code']); ?></div>
                    </div>
                    <div class="fw-bold text-success">₦<?php echo number_format((float)$tc['total_paid']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
(function () {
    // Detect dark mode
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
    var textColor = isDark ? '#9ca3af' : '#6b7280';

    Chart.defaults.color = textColor;
    Chart.defaults.font.family = "'Public Sans', sans-serif";
    Chart.defaults.font.size = 12;

    // ── Monthly Revenue ──
    var revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($revenueLabels); ?>,
                datasets: [{
                    label: 'Revenue (₦)',
                    data: <?php echo json_encode($revenueData); ?>,
                    borderColor: '#00a76f',
                    backgroundColor: 'rgba(0,167,111,0.08)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#00a76f',
                    fill: true,
                    tension: 0.35
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, maxRotation: 45 } },
                    y: {
                        grid: { color: gridColor },
                        ticks: {
                            color: textColor,
                            callback: function(v) { return '₦' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v); }
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // ── Payment Status Pie ──
    var statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        var statusColors = {
            'Paid': '#00a76f', 'Pending': '#f59e0b', 'Overdue': '#ef4444', 'Waived': '#6366f1'
        };
        var sliceColors = <?php echo json_encode($statusLabels); ?>.map(function(l){ return statusColors[l] || '#9ca3af'; });
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($statusLabels); ?>,
                datasets: [{
                    data: <?php echo json_encode($statusData); ?>,
                    backgroundColor: sliceColors,
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true } }
                }
            }
        });
    }

    // ── Member Growth Bar ──
    var growthCtx = document.getElementById('growthChart');
    if (growthCtx) {
        new Chart(growthCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($revenueLabels); ?>,
                datasets: [{
                    label: 'New Members',
                    data: <?php echo json_encode($growthData); ?>,
                    backgroundColor: 'rgba(59,130,246,0.7)',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: textColor, maxRotation: 45 } },
                    y: { grid: { color: gridColor }, ticks: { color: textColor, precision: 0 }, beginAtZero: true }
                }
            }
        });
    }
})();
</script>

<style>
@media print {
    .sidebar, .navbar, .content-header .d-flex, .btn { display: none !important; }
    .content { margin: 0 !important; padding: 0 !important; }
    .card { break-inside: avoid; }
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
