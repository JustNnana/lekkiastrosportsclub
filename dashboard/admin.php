<?php
/**
 * Lekki Astro Sports Club
 * Admin Dashboard — iOS Styled
 */

requireAdmin();

$pageTitle    = 'Admin Dashboard';
$includeCharts = true;

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
    "SELECT m.member_id, u.full_name, u.email, m.status, m.created_at
     FROM members m
     JOIN users u ON u.id = m.user_id
     ORDER BY m.created_at DESC LIMIT 5"
);

// Recent payments
$recentPayments = $db->fetchAll(
    "SELECT p.amount, p.status, p.created_at, u.full_name, d.title AS due_title
     FROM payments p
     JOIN members m ON m.id = p.member_id
     JOIN users u ON u.id = m.user_id
     JOIN dues d ON d.id = p.due_id
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

$activeRate = $totalMembers > 0 ? round(($activeMembers / $totalMembers) * 100) : 0;

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<!-- ===== PAGE HEADER (desktop only) ===== -->
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
            <i class="fas fa-user-plus me-1"></i> Add Member
        </a>
        <a href="<?php echo BASE_URL; ?>payments/due-form.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-plus me-1"></i> Create Due
        </a>
    </div>
</div>

<!-- ===== MOBILE GREETING CARD (mobile only) ===== -->
<div class="ios-mobile-greeting">
    <div class="ios-mobile-greeting-card">
        <div class="ios-mobile-greeting-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        <div class="ios-mobile-greeting-text">
            <h2><?php echo timeGreeting(); ?>, <?php echo e(explode(' ', $currentUser->getFullName())[0]); ?>!</h2>
            <p>Admin <span>·</span> <?php echo date('d M Y'); ?></p>
        </div>
        <button class="ios-mobile-greeting-dots"
                id="adminMenuBtn"
                aria-label="Open menu">
            <i class="fas fa-ellipsis-v"></i>
        </button>
    </div>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="stats-overview-grid mb-4">

    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon blue"><i class="fas fa-users"></i></div>
            <span class="stat-label">Total Members</span>
        </div>
        <p class="stat-value"><?php echo number_format($totalMembers); ?></p>
        <div class="stat-progress">
            <div class="stat-progress-bar">
                <div class="stat-progress-fill blue" style="width:100%"></div>
            </div>
            <div class="stat-progress-label">
                <span>New this month</span>
                <span style="color:#007aff;font-weight:600">+<?php echo $newThisMonth; ?></span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
            <span class="stat-label">Active Members</span>
        </div>
        <p class="stat-value"><?php echo number_format($activeMembers); ?></p>
        <div class="stat-progress">
            <div class="stat-progress-bar">
                <div class="stat-progress-fill green" style="width:<?php echo $activeRate; ?>%"></div>
            </div>
            <div class="stat-progress-label">
                <span>Active rate</span>
                <span><?php echo $activeRate; ?>%</span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon orange"><i class="fas fa-naira-sign"></i></div>
            <span class="stat-label">Revenue Collected</span>
        </div>
        <p class="stat-value" style="font-size:22px!important"><?php echo formatCurrency($totalCollected); ?></p>
        <div class="stat-progress">
            <div class="stat-progress-bar">
                <div class="stat-progress-fill orange" style="width:100%"></div>
            </div>
            <div class="stat-progress-label">
                <span>Pending dues</span>
                <span style="color:#ff9500;font-weight:600"><?php echo $pendingDues; ?></span>
            </div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon purple"><i class="fas fa-calendar-check"></i></div>
            <span class="stat-label">Upcoming Events</span>
        </div>
        <p class="stat-value"><?php echo $upcomingEvents; ?></p>
        <div class="stat-progress">
            <div class="stat-progress-bar">
                <div class="stat-progress-fill purple" style="width:100%"></div>
            </div>
            <div class="stat-progress-label">
                <span>Active polls</span>
                <span style="color:#af52de;font-weight:600"><?php echo $activePolls; ?></span>
            </div>
        </div>
    </div>

</div>

<!-- ===== QUICK ACTIONS (desktop 3-column) ===== -->
<div class="ios-quick-actions">
    <a href="<?php echo BASE_URL; ?>members/create.php" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(0,122,255,.15);color:#007aff">
            <i class="fas fa-user-plus"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">Add Member</p>
            <p class="ios-quick-action-desc">Register a new club member</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
    <a href="<?php echo BASE_URL; ?>payments/" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(52,199,89,.15);color:#34c759">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">Payment Management</p>
            <p class="ios-quick-action-desc">Dues, payments & overdue</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
    <a href="<?php echo BASE_URL; ?>announcements/create.php" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(255,149,0,.15);color:#ff9500">
            <i class="fas fa-bullhorn"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">Post Announcement</p>
            <p class="ios-quick-action-desc">Broadcast to all members</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
    <a href="<?php echo BASE_URL; ?>events/form.php" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(52,199,89,.15);color:#34c759">
            <i class="fas fa-calendar-plus"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">Schedule Event</p>
            <p class="ios-quick-action-desc">Add a new club event</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
    <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(255,59,48,.15);color:#ff3b30">
            <i class="fas fa-trophy"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">Tournaments</p>
            <p class="ios-quick-action-desc">Manage competitions</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
    <a href="<?php echo BASE_URL; ?>reports/index.php" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(175,82,222,.15);color:#af52de">
            <i class="fas fa-chart-bar"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">Reports & Analytics</p>
            <p class="ios-quick-action-desc">Insights & exports</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
</div>

<!-- ===== MOBILE QUICK ACTIONS (icon grid, mobile only) ===== -->
<div class="ios-mobile-actions">
    <p class="ios-mobile-actions-title">Quick Actions</p>
    <div class="ios-mobile-actions-grid">
        <a href="<?php echo BASE_URL; ?>members/create.php" class="ios-mobile-action-btn">
            <div class="ios-mobile-action-icon" style="background:#007aff"><i class="fas fa-user-plus"></i></div>
            <span class="ios-mobile-action-label">Add Member</span>
        </a>
        <a href="<?php echo BASE_URL; ?>payments/" class="ios-mobile-action-btn">
            <div class="ios-mobile-action-icon" style="background:#34c759"><i class="fas fa-wallet"></i></div>
            <span class="ios-mobile-action-label">Payments</span>
        </a>
        <a href="<?php echo BASE_URL; ?>announcements/create.php" class="ios-mobile-action-btn">
            <div class="ios-mobile-action-icon" style="background:#ff9500"><i class="fas fa-bullhorn"></i></div>
            <span class="ios-mobile-action-label">Announce</span>
        </a>
        <a href="<?php echo BASE_URL; ?>reports/index.php" class="ios-mobile-action-btn">
            <div class="ios-mobile-action-icon" style="background:#af52de"><i class="fas fa-chart-bar"></i></div>
            <span class="ios-mobile-action-label">Reports</span>
        </a>
    </div>
</div>

<!-- ===== CHART + ADMIN LINKS ===== -->
<div class="ios-charts-grid">

    <!-- Revenue Chart -->
    <div class="ios-section-card" style="margin-bottom:0">
        <div class="ios-section-header">
            <div class="ios-section-header-left">
                <div class="ios-section-icon" style="background:rgba(52,199,89,.15);color:#34c759">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="ios-section-title">
                    <h5>Revenue Trend</h5>
                    <p>Last 6 months</p>
                </div>
            </div>
            <a href="<?php echo BASE_URL; ?>reports/index.php" class="ios-link-btn">View Report</a>
        </div>
        <div class="ios-chart-body">
            <?php if (empty($monthlyRevenue)): ?>
            <div class="ios-chart-empty">
                <i class="fas fa-chart-line"></i>
                <p>No payment data yet. Revenue will appear here once dues are collected.</p>
            </div>
            <?php else: ?>
            <canvas id="revenueChart" class="ios-chart-canvas"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Admin Quick Links -->
    <div class="ios-section-card" style="margin-bottom:0">
        <div class="ios-section-header">
            <div class="ios-section-header-left">
                <div class="ios-section-icon" style="background:rgba(0,122,255,.15);color:#007aff">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="ios-section-title"><h5>Quick Links</h5></div>
            </div>
        </div>
        <div class="ios-section-body">
            <a href="<?php echo BASE_URL; ?>members/" class="ios-admin-link">
                <div class="ios-admin-link-icon" style="background:rgba(0,122,255,.12);color:#007aff">
                    <i class="fas fa-users"></i>
                </div>
                <span class="ios-admin-link-label">View All Members</span>
                <i class="fas fa-chevron-right ios-admin-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>payments/" class="ios-admin-link">
                <div class="ios-admin-link-icon" style="background:rgba(52,199,89,.12);color:#34c759">
                    <i class="fas fa-wallet"></i>
                </div>
                <span class="ios-admin-link-label">Payment Management</span>
                <i class="fas fa-chevron-right ios-admin-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="ios-admin-link">
                <div class="ios-admin-link-icon" style="background:rgba(255,149,0,.12);color:#ff9500">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <span class="ios-admin-link-label">Announcements</span>
                <i class="fas fa-chevron-right ios-admin-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>events/manage.php" class="ios-admin-link">
                <div class="ios-admin-link-icon" style="background:rgba(52,199,89,.12);color:#34c759">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <span class="ios-admin-link-label">Manage Events</span>
                <i class="fas fa-chevron-right ios-admin-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="ios-admin-link">
                <div class="ios-admin-link-icon" style="background:rgba(255,59,48,.12);color:#ff3b30">
                    <i class="fas fa-trophy"></i>
                </div>
                <span class="ios-admin-link-label">Tournaments</span>
                <i class="fas fa-chevron-right ios-admin-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>polls/manage.php" class="ios-admin-link">
                <div class="ios-admin-link-icon" style="background:rgba(88,86,214,.12);color:#5856d6">
                    <i class="fas fa-poll"></i>
                </div>
                <span class="ios-admin-link-label">Polls & Voting</span>
                <i class="fas fa-chevron-right ios-admin-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>documents/manage.php" class="ios-admin-link">
                <div class="ios-admin-link-icon" style="background:rgba(142,142,147,.12);color:#8e8e93">
                    <i class="fas fa-folder-open"></i>
                </div>
                <span class="ios-admin-link-label">Documents</span>
                <i class="fas fa-chevron-right ios-admin-link-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>reports/index.php" class="ios-admin-link" style="border-bottom:none">
                <div class="ios-admin-link-icon" style="background:rgba(175,82,222,.12);color:#af52de">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <span class="ios-admin-link-label">Reports & Analytics</span>
                <i class="fas fa-chevron-right ios-admin-link-chevron"></i>
            </a>
        </div>
    </div>

</div>

<!-- ===== RECENT MEMBERS + RECENT PAYMENTS ===== -->
<div class="ios-tables-grid">

    <!-- Recent Members -->
    <div class="ios-section-card" style="margin-bottom:0">
        <div class="ios-section-header">
            <div class="ios-section-header-left">
                <div class="ios-section-icon" style="background:rgba(0,122,255,.15);color:#007aff">
                    <i class="fas fa-users"></i>
                </div>
                <div class="ios-section-title"><h5>Recent Members</h5></div>
            </div>
            <a href="<?php echo BASE_URL; ?>members/" class="ios-link-btn">View All</a>
        </div>
        <div class="ios-section-body">
            <?php if (empty($recentMembers)): ?>
            <div class="ios-empty-state">
                <i class="fas fa-users"></i>
                <p>No members yet.</p>
                <a href="<?php echo BASE_URL; ?>members/create.php" class="ios-empty-btn">
                    <i class="fas fa-user-plus"></i> Add First Member
                </a>
            </div>
            <?php else: ?>
            <?php foreach ($recentMembers as $m): ?>
            <div class="ios-list-item">
                <div class="ios-mini-avatar"><?php echo e(getInitials($m['full_name'])); ?></div>
                <div class="ios-list-content">
                    <p class="ios-list-primary"><?php echo e($m['full_name']); ?></p>
                    <p class="ios-list-secondary"><?php echo e($m['email']); ?></p>
                </div>
                <div class="ios-list-meta">
                    <?php echo statusBadge($m['status']); ?>
                    <span class="ios-list-date"><?php echo e($m['member_id']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Payments -->
    <div class="ios-section-card" style="margin-bottom:0">
        <div class="ios-section-header">
            <div class="ios-section-header-left">
                <div class="ios-section-icon" style="background:rgba(52,199,89,.15);color:#34c759">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="ios-section-title"><h5>Recent Payments</h5></div>
            </div>
            <a href="<?php echo BASE_URL; ?>payments/" class="ios-link-btn">View All</a>
        </div>
        <div class="ios-section-body">
            <?php if (empty($recentPayments)): ?>
            <div class="ios-empty-state">
                <i class="fas fa-receipt"></i>
                <p>No payments recorded yet.</p>
            </div>
            <?php else: ?>
            <?php foreach ($recentPayments as $p):
                $dotClass = match($p['status']) {
                    'paid'    => 'paid',
                    'overdue' => 'expired',
                    default   => 'pending',
                };
                $badgeClass = match($p['status']) {
                    'paid'    => 'green',
                    'overdue' => 'red',
                    default   => 'orange',
                };
            ?>
            <div class="ios-list-item">
                <div class="ios-list-dot <?php echo $dotClass; ?>"></div>
                <div class="ios-list-content">
                    <p class="ios-list-primary"><?php echo e($p['full_name']); ?></p>
                    <p class="ios-list-secondary"><?php echo e($p['due_title']); ?></p>
                </div>
                <div class="ios-list-meta">
                    <span class="ios-list-badge <?php echo $badgeClass; ?>"><?php echo formatCurrency($p['amount']); ?></span>
                    <span class="ios-list-date"><?php echo formatDate($p['created_at'], 'd M'); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
/* ── Stat cards — iOS horizontal layout ── */
.stat-card {
    text-align: left !important;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 20px;
    transition: all 0.2s ease;
}
.stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.stat-header { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.stat-icon { margin: 0 !important; flex-shrink: 0; }
.stat-icon.blue   { background: rgba(0,122,255,.15);  color: #007aff; }
.stat-icon.green  { background: rgba(52,199,89,.15);  color: #34c759; }
.stat-icon.orange { background: rgba(255,149,0,.15);  color: #ff9500; }
.stat-icon.purple { background: rgba(175,82,222,.15); color: #af52de; }
.stat-label { font-size: 13px; color: var(--text-muted); margin: 0; font-weight: 500; }
.stat-value { font-size: 28px !important; font-weight: 700 !important; color: var(--text-primary) !important; margin: 0 !important; line-height: 1; }
.stat-progress { margin-top: 12px; }
.stat-progress-bar { height: 4px; background: var(--bg-secondary); border-radius: 2px; overflow: hidden; }
.stat-progress-fill { height: 100%; border-radius: 2px; transition: width 0.5s ease; }
.stat-progress-fill.blue   { background: #007aff; }
.stat-progress-fill.green  { background: #34c759; }
.stat-progress-fill.orange { background: #ff9500; }
.stat-progress-fill.purple { background: #af52de; }
.stat-progress-label {
    display: flex; justify-content: space-between;
    font-size: 11px; color: var(--text-muted); margin-top: 6px;
}

/* ── Stat grid ── */
.stats-overview-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;
}

/* ── Quick Actions (desktop 3-column) ── */
.ios-quick-actions {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; margin-bottom: 24px;
}
.ios-quick-action {
    display: flex; align-items: center; gap: 14px;
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 14px; padding: 18px;
    text-decoration: none; transition: all 0.2s ease;
}
.ios-quick-action:hover {
    border-color: var(--primary); box-shadow: 0 4px 16px rgba(0,0,0,.08);
    transform: translateY(-1px); text-decoration: none;
}
.ios-quick-action-icon {
    width: 44px; height: 44px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.ios-quick-action-text { flex: 1; min-width: 0; }
.ios-quick-action-title { font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px; }
.ios-quick-action-desc  { font-size: 11px; color: var(--text-muted); margin: 0; }
.ios-quick-action-arrow { color: var(--text-muted); font-size: 12px; opacity: .5; transition: all .2s ease; flex-shrink: 0; }
.ios-quick-action:hover .ios-quick-action-arrow { opacity: 1; transform: translateX(3px); }

/* ── Mobile quick actions ── */
.ios-mobile-actions {
    display: none; margin-bottom: 20px;
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 14px; padding: 16px;
}
.ios-mobile-actions-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 14px; }
.ios-mobile-actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
.ios-mobile-action-btn {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    text-decoration: none; padding: 4px;
}
.ios-mobile-action-icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; color: #fff;
}
.ios-mobile-action-label { font-size: 11px; font-weight: 500; color: var(--text-primary); text-align: center; line-height: 1.3; }

/* ── Mobile greeting (hidden by default) ── */
.ios-mobile-greeting { display: none; margin-bottom: 20px; }
.ios-mobile-greeting-card {
    display: flex; align-items: center; gap: 14px;
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 14px; padding: 16px 18px;
}
.ios-mobile-greeting-icon {
    width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    background: rgba(0,122,255,.12); color: #007aff;
}
.ios-mobile-greeting-text { flex: 1; min-width: 0; }
.ios-mobile-greeting-text h2 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0 0 2px; }
.ios-mobile-greeting-text p { font-size: 13px; color: var(--text-muted); margin: 0; }
.ios-mobile-greeting-text p span { margin: 0 3px; opacity: .4; }
.ios-mobile-greeting-dots {
    width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary); font-size: 16px;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    cursor: pointer; transition: all .2s ease;
}
.ios-mobile-greeting-dots:hover { background: var(--bg-hover); }

/* ── iOS Section cards ── */
.ios-section-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: 0;
}
.ios-section-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px; border-bottom: 1px solid var(--border-color);
}
.ios-section-header-left { display: flex; align-items: center; gap: 12px; }
.ios-section-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
.ios-section-title h5 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
.ios-link-btn {
    font-size: 13px; font-weight: 500; color: #007aff;
    text-decoration: none; transition: opacity .2s ease; white-space: nowrap;
}
.ios-link-btn:hover { opacity: .7; color: #007aff; text-decoration: none; }

/* ── Chart ── */
.ios-charts-grid {
    display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;
}
.ios-chart-body { padding: 20px; }
.ios-chart-canvas { height: 260px !important; }
.ios-chart-empty {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 48px 20px; color: var(--text-muted); text-align: center;
}
.ios-chart-empty i { font-size: 40px; margin-bottom: 12px; opacity: .4; }
.ios-chart-empty p { font-size: 13px; margin: 0; max-width: 260px; }

/* ── Admin quick links ── */
.ios-admin-link {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 20px; border-bottom: 1px solid var(--border-color);
    text-decoration: none; color: var(--text-primary);
    transition: background .15s ease;
}
.ios-admin-link:hover { background: var(--bg-hover); text-decoration: none; }
.ios-admin-link-icon {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
}
.ios-admin-link-label { flex: 1; font-size: 14px; font-weight: 500; }
.ios-admin-link-chevron { font-size: 10px; color: var(--text-muted); opacity: .5; }

/* ── Tables grid ── */
.ios-tables-grid {
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px;
}

/* ── iOS List items ── */
.ios-list-item {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 20px; border-bottom: 1px solid var(--border-color);
    transition: background .15s ease;
}
.ios-list-item:last-child { border-bottom: none; }
.ios-list-item:hover { background: var(--bg-hover); }
.ios-list-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.ios-list-dot.paid    { background: #34c759; }
.ios-list-dot.expired { background: #ff3b30; }
.ios-list-dot.pending { background: #ff9500; }
.ios-list-content { flex: 1; min-width: 0; }
.ios-list-primary {
    font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ios-list-secondary { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ios-list-meta { display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; gap: 3px; }
.ios-list-badge {
    display: inline-flex; align-items: center;
    padding: 3px 8px; border-radius: 6px;
    font-size: 11px; font-weight: 600;
}
.ios-list-badge.green  { background: rgba(52,199,89,.15);  color: #34c759; }
.ios-list-badge.orange { background: rgba(255,149,0,.15);  color: #ff9500; }
.ios-list-badge.red    { background: rgba(255,59,48,.15);  color: #ff3b30; }
.ios-list-date { font-size: 10px; color: var(--text-muted); }

/* ── Mini avatar ── */
.ios-mini-avatar {
    width: 34px; height: 34px; flex-shrink: 0;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary), var(--primary-700));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700;
}

/* ── Empty state ── */
.ios-empty-state {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 36px 20px; text-align: center; color: var(--text-muted);
}
.ios-empty-state i { font-size: 32px; margin-bottom: 10px; opacity: .4; }
.ios-empty-state p { font-size: 13px; margin: 0 0 12px; }
.ios-empty-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 10px;
    background: #007aff; color: #fff;
    font-size: 13px; font-weight: 600;
    text-decoration: none; transition: opacity .2s ease;
}
.ios-empty-btn:hover { opacity: .85; color: #fff; }

/* ── Admin Mobile Menu modal ── */
.ios-menu-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.45); backdrop-filter: blur(4px);
    z-index: 9998; opacity: 0; visibility: hidden; transition: .3s;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-primary); border-radius: 16px 16px 0 0;
    z-index: 9999; transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 88vh; overflow: hidden;
    display: flex; flex-direction: column;
}
.ios-menu-modal.active { transform: translateY(0); }
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 10px auto 4px; flex-shrink: 0; }
.ios-menu-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px 14px; border-bottom: 1px solid var(--border-color); flex-shrink: 0;
}
.ios-menu-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close {
    width: 30px; height: 30px; border-radius: 50%;
    background: var(--bg-secondary); border: none;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-secondary); cursor: pointer;
}
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 16px; }
.ios-menu-section-title { font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .6px; margin-bottom: 8px; padding-left: 4px; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-stat-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 14px; color: var(--text-secondary); }
.ios-menu-stat-value { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.ios-menu-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 13px 16px; border-bottom: 1px solid var(--border-color);
    text-decoration: none; color: var(--text-primary); transition: background .15s; cursor: pointer;
}
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item:hover { text-decoration: none; color: var(--text-primary); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; color: #fff; }
.ios-menu-item-icon.blue   { background: #007aff; }
.ios-menu-item-icon.green  { background: #34c759; }
.ios-menu-item-icon.orange { background: #ff9500; }
.ios-menu-item-icon.purple { background: #af52de; }
.ios-menu-item-icon.red    { background: #ff3b30; }
.ios-menu-item-icon.gray   { background: #8e8e93; }
.ios-menu-item-label { font-size: 14px; font-weight: 500; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 11px; }

/* ── Responsive ── */
@media (max-width: 1200px) {
    .ios-quick-actions { grid-template-columns: repeat(3, 1fr); }
    .ios-quick-action-desc { display: none; }
}
@media (max-width: 992px) {
    .stats-overview-grid { grid-template-columns: repeat(2, 1fr); }
    .ios-quick-actions   { grid-template-columns: repeat(2, 1fr); }
    .ios-charts-grid     { grid-template-columns: 1fr; }
    .ios-tables-grid     { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .content-header      { display: none !important; }
    .ios-mobile-greeting { display: block; }
    .ios-quick-actions   { display: none; }
    .ios-mobile-actions  { display: block; }

    .stats-overview-grid {
        display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch;
        gap: 12px; padding-bottom: 4px; scrollbar-width: none;
    }
    .stats-overview-grid::-webkit-scrollbar { display: none; }
    .stat-card { min-width: 150px; flex: 0 0 auto; padding: 14px; }
    .stat-header { margin-bottom: 8px; }
    .stat-icon   { width: 32px !important; height: 32px !important; font-size: 14px !important; border-radius: 9px !important; }
    .stat-value  { font-size: 22px !important; }
    .stat-progress { display: none; }

    /* Hide chart card only, keep Quick Links visible */
    .ios-charts-grid { grid-template-columns: 1fr; }
    .ios-charts-grid > .ios-section-card:first-child { display: none; }

    .ios-section-header { padding: 14px 16px; }
    .ios-section-icon   { width: 34px; height: 34px; font-size: 14px; }
    .ios-section-title h5 { font-size: 15px; }
    .ios-list-item      { padding: 12px 16px; }
}
@media (max-width: 480px) {
    .stat-card  { min-width: 130px; padding: 12px; }
    .stat-value { font-size: 20px !important; }
    .ios-mobile-actions     { border-radius: 12px; }
    .ios-mobile-action-icon { width: 48px; height: 48px; border-radius: 12px; font-size: 20px; }
}
@media (max-width: 390px) {
    .stat-card  { min-width: 120px; padding: 10px; }
    .stat-value { font-size: 18px !important; }
    .ios-mobile-action-icon { width: 44px; height: 44px; font-size: 18px; }
}
</style>

<!-- ===== ADMIN MOBILE MENU ===== -->
<div class="ios-menu-backdrop" id="adminMenuBackdrop"></div>
<div class="ios-menu-modal" id="adminMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Admin Dashboard</h3>
        <button class="ios-menu-close" id="adminMenuClose"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <!-- Stats summary -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Overview</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total Members</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($totalMembers); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active Members</span>
                    <span class="ios-menu-stat-value" style="color:#34c759"><?php echo number_format($activeMembers); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Pending Dues</span>
                    <span class="ios-menu-stat-value" style="color:#ff9500"><?php echo $pendingDues; ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Upcoming Events</span>
                    <span class="ios-menu-stat-value"><?php echo $upcomingEvents; ?></span>
                </div>
            </div>
        </div>

        <!-- Member actions -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Members</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>members/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-users"></i></div>
                        <span class="ios-menu-item-label">All Members</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>members/create.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-user-plus"></i></div>
                        <span class="ios-menu-item-label">Add Member</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <!-- Payments actions -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Payments</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>payments/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-wallet"></i></div>
                        <span class="ios-menu-item-label">Payment Management</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>payments/due-form.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-plus"></i></div>
                        <span class="ios-menu-item-label">Create Due</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

        <!-- Club actions -->
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Club</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>announcements/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-bullhorn"></i></div>
                        <span class="ios-menu-item-label">Announcements</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>events/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-calendar-alt"></i></div>
                        <span class="ios-menu-item-label">Events & Calendar</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon red"><i class="fas fa-trophy"></i></div>
                        <span class="ios-menu-item-label">Tournaments</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>polls/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-poll"></i></div>
                        <span class="ios-menu-item-label">Polls & Voting</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>documents/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon gray"><i class="fas fa-folder-open"></i></div>
                        <span class="ios-menu-item-label">Documents</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>reports/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-chart-bar"></i></div>
                        <span class="ios-menu-item-label">Reports & Analytics</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<?php if ($includeCharts): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    var labels = <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>;
    var data   = <?php echo json_encode(array_map('floatval', array_column($monthlyRevenue, 'total'))); ?>;

    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor  = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.05)';
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

    // Update chart colors on theme toggle
    document.querySelectorAll('.theme-toggle-trigger').forEach(function (btn) {
        btn.addEventListener('click', function () {
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
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    var backdrop = document.getElementById('adminMenuBackdrop');
    var modal    = document.getElementById('adminMenuModal');
    var openBtn  = document.getElementById('adminMenuBtn');
    var closeBtn = document.getElementById('adminMenuClose');

    function openMenu()  { backdrop.classList.add('active'); modal.classList.add('active'); }
    function closeMenu() { backdrop.classList.remove('active'); modal.classList.remove('active'); }

    if (openBtn)  openBtn.addEventListener('click', openMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    if (backdrop) backdrop.addEventListener('click', closeMenu);

    // Swipe down to close
    var startY = 0;
    if (modal) {
        modal.addEventListener('touchstart', function (e) { startY = e.touches[0].clientY; }, {passive:true});
        modal.addEventListener('touchend',   function (e) { if (e.changedTouches[0].clientY - startY > 60) closeMenu(); }, {passive:true});
    }
})();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
