<?php
/**
 * Lekki Astro Sports Club
 * Admin Dashboard
 */

requireAdmin();

$pageTitle    = 'Admin Dashboard';
$includeCharts = true;

// Stats
$db = Database::getInstance();

$totalMembers  = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM members")['c'] ?? 0);
$activeMembers = (int)($db->fetchOne("SELECT COUNT(*) AS c FROM members WHERE status = 'active'")['c'] ?? 0);
$newThisMonth  = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM members WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
)['c'] ?? 0);

$totalCollected = (float)($db->fetchOne(
    "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE status = 'paid'"
)['s'] ?? 0);
$pendingDues = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM payments WHERE status IN ('pending','overdue')"
)['c'] ?? 0);

$upcomingEvents = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM events WHERE start_date >= NOW() AND status = 'active'"
)['c'] ?? 0);

$activePolls = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM polls WHERE deadline > NOW() AND status = 'active'"
)['c'] ?? 0);

// Recent members
$recentMembers = $db->fetchAll(
    "SELECT member_id, full_name, email, status, created_at
     FROM members ORDER BY created_at DESC LIMIT 5"
);

// Recent payments
$recentPayments = $db->fetchAll(
    "SELECT p.*, m.full_name, d.title AS due_title
     FROM payments p
     JOIN members m ON p.member_id = m.id
     JOIN dues d ON p.due_id = d.id
     ORDER BY p.created_at DESC LIMIT 5"
);

// Monthly revenue (last 6 months) for chart
$monthlyRevenue = $db->fetchAll(
    "SELECT DATE_FORMAT(payment_date,'%b %Y') AS month,
            COALESCE(SUM(amount),0) AS total
     FROM payments
     WHERE status = 'paid'
       AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY YEAR(payment_date), MONTH(payment_date)
     ORDER BY payment_date ASC"
);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<!-- ===== PAGE HEADER ===== -->
<div class="content-header d-flex align-items-start justify-content-between flex-wrap gap-3">
    <div>
        <h1 class="content-title">Dashboard</h1>
        <p class="content-subtitle">
            <?php echo timeGreeting(); ?>, <?php echo e(explode(' ', $currentUser->getFullName())[0]); ?>!
            Here's what's happening today.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo BASE_URL; ?>members/create.php" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> Add Member
        </a>
        <a href="<?php echo BASE_URL; ?>payments/create-due.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-plus"></i> Create Due
        </a>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-4 mb-6">

    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon stat-icon-primary">
                    <i class="fas fa-users"></i>
                </div>
                <p class="stat-label">Total Members</p>
                <h3 class="stat-value"><?php echo number_format($totalMembers); ?></h3>
                <p class="stat-sub">
                    <span class="text-success"><i class="fas fa-arrow-up"></i> <?php echo $newThisMonth; ?> this month</span>
                </p>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon stat-icon-success">
                    <i class="fas fa-user-check"></i>
                </div>
                <p class="stat-label">Active Members</p>
                <h3 class="stat-value"><?php echo number_format($activeMembers); ?></h3>
                <p class="stat-sub text-muted">
                    <?php echo $totalMembers > 0 ? round(($activeMembers / $totalMembers) * 100) : 0; ?>% of total
                </p>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon stat-icon-info">
                    <i class="fas fa-naira-sign"></i>
                </div>
                <p class="stat-label">Revenue Collected</p>
                <h3 class="stat-value"><?php echo formatCurrency($totalCollected); ?></h3>
                <p class="stat-sub">
                    <span class="text-warning"><?php echo $pendingDues; ?> pending</span>
                </p>
            </div>
        </div>
    </div>

    <div class="col-6 col-lg-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon stat-icon-warning">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <p class="stat-label">Upcoming Events</p>
                <h3 class="stat-value"><?php echo $upcomingEvents; ?></h3>
                <p class="stat-sub">
                    <span class="text-primary"><?php echo $activePolls; ?> active polls</span>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ===== REVENUE CHART + QUICK LINKS ===== -->
<div class="row g-4 mb-6">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title">Revenue Trend (Last 6 Months)</h6>
                <a href="<?php echo BASE_URL; ?>reports/" class="btn btn-sm btn-outline-primary">View Report</a>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="260"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="card-title">Quick Actions</h6>
            </div>
            <div class="card-body p-3">
                <div class="d-flex flex-column gap-2">
                    <a href="<?php echo BASE_URL; ?>members/" class="btn btn-secondary text-start">
                        <i class="fas fa-users fa-fw me-2 text-primary"></i> View All Members
                    </a>
                    <a href="<?php echo BASE_URL; ?>payments/" class="btn btn-secondary text-start">
                        <i class="fas fa-wallet fa-fw me-2 text-success"></i> Payment Management
                    </a>
                    <a href="<?php echo BASE_URL; ?>announcements/create.php" class="btn btn-secondary text-start">
                        <i class="fas fa-bullhorn fa-fw me-2 text-info"></i> Post Announcement
                    </a>
                    <a href="<?php echo BASE_URL; ?>events/create.php" class="btn btn-secondary text-start">
                        <i class="fas fa-calendar-plus fa-fw me-2 text-warning"></i> Schedule Event
                    </a>
                    <a href="<?php echo BASE_URL; ?>tournaments/create.php" class="btn btn-secondary text-start">
                        <i class="fas fa-trophy fa-fw me-2 text-danger"></i> Create Tournament
                    </a>
                    <a href="<?php echo BASE_URL; ?>reports/" class="btn btn-secondary text-start">
                        <i class="fas fa-chart-bar fa-fw me-2 text-secondary"></i> Reports & Analytics
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ===== RECENT MEMBERS + RECENT PAYMENTS ===== -->
<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title">Recent Members</h6>
                <a href="<?php echo BASE_URL; ?>members/" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentMembers)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No members yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentMembers as $m): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="mini-avatar"><?php echo e(getInitials($m['full_name'])); ?></div>
                                    <div>
                                        <div class="fw-medium"><?php echo e($m['full_name']); ?></div>
                                        <small class="text-muted"><?php echo e($m['email']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><code><?php echo e($m['member_id']); ?></code></td>
                            <td><?php echo statusBadge($m['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title">Recent Payments</h6>
                <a href="<?php echo BASE_URL; ?>payments/" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Due</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No payments yet.</td></tr>
                        <?php else: ?>
                        <?php foreach ($recentPayments as $p): ?>
                        <tr>
                            <td class="fw-medium"><?php echo e($p['full_name']); ?></td>
                            <td class="text-muted"><?php echo e($p['due_title']); ?></td>
                            <td class="fw-semibold"><?php echo formatCurrency($p['amount']); ?></td>
                            <td><?php echo statusBadge($p['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== STAT CARDS ===== */
.stat-card .card-body { padding: var(--spacing-5); }
.stat-icon {
    width: 48px; height: 48px;
    border-radius: var(--border-radius-lg);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.25rem;
    margin-bottom: var(--spacing-4);
}
.stat-icon-primary { background: var(--primary-light); color: var(--primary); }
.stat-icon-success { background: var(--success-light); color: var(--success); }
.stat-icon-info    { background: var(--info-light);    color: var(--info); }
.stat-icon-warning { background: var(--warning-light); color: var(--warning-700); }

.stat-label { font-size: var(--font-size-xs); color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; margin: 0 0 var(--spacing-1); }
.stat-value { font-size: var(--font-size-4xl); font-weight: var(--font-weight-bold); color: var(--text-primary); margin: 0 0 var(--spacing-2); }
.stat-sub   { font-size: var(--font-size-xs); margin: 0; }

/* Mini avatar in table */
.mini-avatar {
    width: 32px; height: 32px; flex-shrink: 0;
    border-radius: var(--border-radius-full);
    background: linear-gradient(135deg, var(--primary), var(--primary-700));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: var(--font-weight-semibold);
}
</style>

<?php if ($includeCharts): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    var labels = <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>;
    var data   = <?php echo json_encode(array_map('floatval', array_column($monthlyRevenue, 'total'))); ?>;

    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.05)';
    var labelColor = isDark ? '#919eab' : '#637381';

    var ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenue (₦)',
                data: data,
                borderColor: '#00a76f',
                backgroundColor: 'rgba(0,167,111,0.08)',
                borderWidth: 2.5,
                pointRadius: 4,
                pointBackgroundColor: '#00a76f',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { color: gridColor }, ticks: { color: labelColor } },
                y: { grid: { color: gridColor }, ticks: { color: labelColor,
                    callback: function(v) { return '₦' + Number(v).toLocaleString(); }
                }}
            }
        }
    });

    // Update chart colors on theme change
    document.getElementById('theme-toggle-btn') &&
    document.getElementById('theme-toggle-btn').addEventListener('click', function () {
        setTimeout(function () {
            var dark = document.documentElement.getAttribute('data-theme') === 'dark';
            var gc = dark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.05)';
            var lc = dark ? '#919eab' : '#637381';
            chart.options.scales.x.grid.color = gc;
            chart.options.scales.x.ticks.color = lc;
            chart.options.scales.y.grid.color = gc;
            chart.options.scales.y.ticks.color = lc;
            chart.update();
        }, 50);
    });
})();
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
