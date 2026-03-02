<?php
/**
 * Gate Wey Access Management System
 * Super Admin Dashboard - Dasher UI Enhanced
 */

// Include necessary files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../classes/User.php';
require_once '../classes/Clan.php';
require_once '../classes/Payment.php';

// Set page title and enable charts
$pageTitle = 'Super Admin Dashboard';
$includeCharts = true;

// Check if user is logged in and is a super admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: ' . BASE_URL);
    exit;
}

// Get database instance
$db = Database::getInstance();

// Get dashboard statistics
$totalUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'] ?? 0;
$totalClans = $db->fetchOne("SELECT COUNT(*) as count FROM clans")['count'] ?? 0;
$activeClans = $db->fetchOne("SELECT COUNT(*) as count FROM clans WHERE payment_status IN ('active', 'free')")['count'] ?? 0;
$totalCodes = $db->fetchOne("SELECT COUNT(*) as count FROM access_codes")['count'] ?? 0;
$totalPayments = $db->fetchOne("SELECT COUNT(*) as count FROM payments WHERE status = 'completed'")['count'] ?? 0;
$totalRevenue = $db->fetchOne("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")['total'] ?? 0;

// Calculate growth percentages
$lastMonthUsers = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")['count'] ?? 0;
$lastMonthClans = $db->fetchOne("SELECT COUNT(*) as count FROM clans WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")['count'] ?? 0;
$lastMonthRevenue = $db->fetchOne("SELECT SUM(amount) as total FROM payments WHERE status = 'completed' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")['total'] ?? 0;

// Format revenue (Naira)
$formattedRevenue = '₦' . number_format($totalRevenue, 2);
$formattedMonthlyRevenue = '₦' . number_format($lastMonthRevenue, 2);

// Get recent clans
$recentClans = $db->fetchAll(
    "SELECT c.*, u.username as admin_username, u.full_name as admin_name, p.name as plan_name
     FROM clans c
     LEFT JOIN users u ON c.admin_id = u.id
     LEFT JOIN pricing_plans p ON c.pricing_plan_id = p.id
     ORDER BY c.created_at DESC
     LIMIT 5"
);

// Get recent access codes
$recentCodes = $db->fetchAll(
    "SELECT ac.*, u.username as creator_username, c.name as clan_name
     FROM access_codes ac
     JOIN users u ON ac.created_by = u.id
     JOIN clans c ON ac.clan_id = c.id
     ORDER BY ac.created_at DESC
     LIMIT 5"
);

// Get recent payments
$recentPayments = $db->fetchAll(
    "SELECT p.*, c.name as clan_name, pp.name as plan_name
     FROM payments p
     JOIN clans c ON p.clan_id = c.id
     LEFT JOIN pricing_plans pp ON p.pricing_plan_id = pp.id
     ORDER BY p.payment_date DESC
     LIMIT 5"
);

// Get monthly revenue data for chart (last 12 months)
$monthlyRevenue = $db->fetchAll(
    "SELECT DATE_FORMAT(payment_date, '%b') as month, 
     DATE_FORMAT(payment_date, '%Y-%m') as date_key,
     SUM(amount) as revenue
     FROM payments 
     WHERE status = 'completed' 
     AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
     ORDER BY payment_date ASC"
);

// Get clan growth data for chart
$clanGrowth = $db->fetchAll(
    "SELECT DATE_FORMAT(created_at, '%b') as month, 
     DATE_FORMAT(created_at, '%Y-%m') as date_key,
     COUNT(*) as count
     FROM clans 
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
     ORDER BY created_at ASC"
);

// Get clan distribution data
$activeClansCount = $db->fetchOne("SELECT COUNT(*) as count FROM clans WHERE payment_status = 'active'")['count'] ?? 0;
$inactiveClansCount = $db->fetchOne("SELECT COUNT(*) as count FROM clans WHERE payment_status = 'inactive'")['count'] ?? 0;
$freeClansCount = $db->fetchOne("SELECT COUNT(*) as count FROM clans WHERE payment_status = 'free'")['count'] ?? 0;

// Include header
include_once '../includes/header.php';

// Include sidebar
include_once '../includes/sidebar.php';
?>
<!-- Improved Dashboard Styles -->
<style>
/* Improved Stats Cards */
.improved-stats-card {
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: var(--spacing-6);
    transition: var(--theme-transition);
}

.improved-stats-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.improved-stats-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
    margin-bottom: var(--spacing-4);
}

.improved-stats-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-xl);
    color: white;
    flex-shrink: 0;
}

.improved-stats-icon.primary { background-color: var(--primary); }
.improved-stats-icon.success { background-color: var(--success); }
.improved-stats-icon.warning { background-color: var(--warning); }
.improved-stats-icon.info { background-color: var(--info); }

.improved-stats-content {
    flex: 1;
}

.improved-stats-title {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    margin: 0 0 var(--spacing-1) 0;
    font-weight: var(--font-weight-medium);
}

.improved-stats-value {
    font-size: var(--font-size-4xl);
    font-weight: var(--font-weight-bold);
    color: var(--text-primary);
    margin: 0;
    line-height: 1;
}

.improved-stats-change {
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    margin-bottom: var(--spacing-3);
}

.improved-stats-change.positive {
    color: var(--success);
}

/* Improved Metric Cards */
.improved-metric-card {
    background: transparent;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: var(--spacing-5);
    transition: var(--theme-transition);
}

.improved-metric-card:hover {
    border-color: var(--primary);
    box-shadow: var(--shadow-sm);
}

.improved-metric-header {
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
}

.improved-metric-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--border-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--font-size-lg);
    color: white;
    flex-shrink: 0;
}

.improved-metric-icon.primary { background-color: var(--primary); }
.improved-metric-icon.success { background-color: var(--success); }
.improved-metric-icon.warning { background-color: var(--warning); }
.improved-metric-icon.info { background-color: var(--info); }

.improved-metric-content {
    flex: 1;
}

.improved-metric-value {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    color: var(--text-primary);
    margin-bottom: var(--spacing-1);
    line-height: 1;
}

.improved-metric-label {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    margin: 0;
}

/* Charts Column Layout */
.charts-column-layout {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-6);
    margin-bottom: var(--spacing-6);
}

@media (min-width: 1200px) {
    .charts-column-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: var(--spacing-6);
    }
}
@media (max-width: 768px) {
        .chart-grid {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            gap: 0.75rem !important;
            padding-bottom: 0.5rem !important;
        }

        .improved-stats-card,
        .quick-action-content {
            flex: 0 0 auto !important;
            min-width: 200px !important;
        }

        .improved-stats-icon {
            width: 35px !important;
            height: 35px !important;
        }

        .improved-stats-card {
            padding: var(--spacing-4) !important;
        }  
        /* Mobile Tables - Stack Vertically */
        .recent-tables-grid {
            display: flex !important;
            flex-direction: column !important;
            gap: var(--spacing-4) !important;
        } 
    }

    /* Recent Tables Grid - Desktop */
    .recent-tables-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--spacing-6);
        margin-bottom: var(--spacing-6);
    }
</style>
<!-- Dasher UI Content Area -->
<div class="content">
    <!-- Content Header -->
    <div class="content-header">
        <h1 class="content-title"><?php echo $greeting; ?>, <?php echo $currentUser->getFullName(); ?></h1>
        <p class="content-subtitle">Here's your system overview for today</p>
    </div>
    
    <!-- Improved Statistics Cards Grid -->
    <div class="chart-grid">
        <!-- Total Clans Card -->
        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon primary">
                    <i class="fas fa-sitemap"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Total Clans</h3>
                    <p class="improved-stats-value"><?php echo number_format($totalClans); ?></p>
                </div>
            </div>
            <div class="improved-stats-change <?php echo $lastMonthClans > 0 ? 'positive' : ''; ?>">
                <i class="fas fa-arrow-up"></i>
                <span><?php echo $lastMonthClans; ?> this month</span>
            </div>
            <div class="progress-wrapper">
                <div class="progress-label">
                    <span>Active Clans</span>
                    <span><?php echo $totalClans > 0 ? round(($activeClans / $totalClans) * 100) : 0; ?>%</span>
                </div>
                <div class="progress">
                    <div class="progress-bar" style="width: <?php echo $totalClans > 0 ? ($activeClans / $totalClans) * 100 : 0; ?>%"></div>
                </div>
            </div>
        </div>

        <!-- Total Users Card -->
        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon success">
                    <i class="fas fa-users"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Total Users</h3>
                    <p class="improved-stats-value"><?php echo number_format($totalUsers); ?></p>
                </div>
            </div>
            <div class="improved-stats-change <?php echo $lastMonthUsers > 0 ? 'positive' : ''; ?>">
                <i class="fas fa-arrow-up"></i>
                <span><?php echo $lastMonthUsers; ?> this month</span>
            </div>
        </div>

        <!-- Access Codes Card -->
        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon warning">
                    <i class="fas fa-key"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Access Codes</h3>
                    <p class="improved-stats-value"><?php echo number_format($totalCodes); ?></p>
                </div>
            </div>
            <div class="improved-stats-change">
                <a href="<?php echo BASE_URL; ?>access-codes/" class="btn btn-primary btn-sm">
                    Manage Codes
                </a>
            </div>
        </div>

        <!-- Revenue Card -->
        <div class="improved-stats-card">
            <div class="improved-stats-header">
                <div class="improved-stats-icon info">
                    <i class="fas fa-money-bill"></i>
                </div>
                <div class="improved-stats-content">
                    <h3 class="improved-stats-title">Total Revenue</h3>
                    <p class="improved-stats-value"><?php echo $formattedRevenue; ?></p>
                </div>
            </div>
            <div class="improved-stats-change positive">
                <i class="fas fa-arrow-up"></i>
                <span><?php echo $formattedMonthlyRevenue; ?> this month</span>
            </div>
        </div>
    </div>
    
    <!-- Charts Column Layout -->
    <div class="charts-column-layout">
        <!-- Revenue Overview Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <div>
                    <h2 class="chart-title">Revenue Overview</h2>
                    <p class="chart-subtitle">Monthly revenue for the last 12 months</p>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="revenueChart" class="chart-canvas"></canvas>
            </div>
        </div>
        
        <!-- Clan Distribution Chart -->
        <div class="chart-container">
            <div class="chart-header">
                <div>
                    <h2 class="chart-title">Clan Distribution</h2>
                    <p class="chart-subtitle">Status breakdown</p>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="clanDistributionChart" class="chart-canvas"></canvas>
            </div>
            <div class="chart-legend">
                <div class="legend-item">
                    <div class="legend-color" style="background-color: var(--success);"></div>
                    <span>Active (<?php echo $activeClansCount; ?>)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: var(--danger);"></div>
                    <span>Inactive (<?php echo $inactiveClansCount; ?>)</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: var(--info);"></div>
                    <span>Free (<?php echo $freeClansCount; ?>)</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity Tables -->
    <div class="recent-tables-grid">
        <!-- Recent Clans Table -->
        <div class="table-container">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="card-title">Recent Clans</h2>
                    <a href="<?php echo BASE_URL; ?>clans/" class="btn btn-primary">View All</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sortable">
                    <thead>
                        <tr>
                            <th>Clan Name</th>
                            <th>Admin</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentClans)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div style="color: var(--text-muted);">
                                        <i class="fas fa-sitemap" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                        <p>No clans found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentClans as $clan): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar" style="width: 32px; height: 32px; margin-right: 0.75rem;">
                                                <?php echo strtoupper(substr($clan['name'], 0, 1)); ?>
                                            </div>
                                            <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($clan['id']); ?>" 
                                               style="color: var(--text-primary); text-decoration: none; font-weight: var(--font-weight-medium);">
                                                <?php echo htmlspecialchars($clan['name']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td style="color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($clan['admin_name'] ?? $clan['admin_username'] ?? 'N/A'); ?>
                                    </td>
                                    <td style="color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($clan['plan_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <div class="table-status">
                                            <?php if ($clan['payment_status'] === 'active'): ?>
                                                <div class="table-status-dot status-active"></div>
                                                <span style="color: var(--success);">Active</span>
                                            <?php elseif ($clan['payment_status'] === 'free'): ?>
                                                <div class="table-status-dot" style="background-color: var(--info);"></div>
                                                <span style="color: var(--info);">Free</span>
                                            <?php else: ?>
                                                <div class="table-status-dot status-inactive"></div>
                                                <span style="color: var(--danger);">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: var(--font-size-sm);">
                                        <?php echo date('M j, Y', strtotime($clan['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Payments Table -->
        <div class="table-container">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="card-title">Recent Payments</h2>
                    <a href="<?php echo BASE_URL; ?>payments/" class="btn btn-primary">View All</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sortable">
                    <thead>
                        <tr>
                            <th>Clan</th>
                            <th>Amount</th>
                            <th>Plan</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <div style="color: var(--text-muted);">
                                        <i class="fas fa-money-bill" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                        <p>No payments found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $payment): ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo BASE_URL; ?>clans/view.php?id=<?php echo encryptId($payment['clan_id']); ?>" 
                                           style="color: var(--text-primary); text-decoration: none; font-weight: var(--font-weight-medium);">
                                            <?php echo htmlspecialchars($payment['clan_name']); ?>
                                        </a>
                                    </td>
                                    <td style="font-weight: var(--font-weight-semibold); color: var(--primary);">
                                        ₦<?php echo number_format($payment['amount'], 2); ?>
                                    </td>
                                    <td style="color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($payment['plan_name'] ?? 'N/A'); ?>
                                    </td>
                                    <td>
                                        <div class="table-status">
                                            <?php if ($payment['status'] === 'completed'): ?>
                                                <div class="table-status-dot status-active"></div>
                                                <span style="color: var(--success);">Completed</span>
                                            <?php elseif ($payment['status'] === 'pending'): ?>
                                                <div class="table-status-dot status-pending"></div>
                                                <span style="color: var(--warning);">Pending</span>
                                            <?php else: ?>
                                                <div class="table-status-dot status-inactive"></div>
                                                <span style="color: var(--danger);"><?php echo ucfirst($payment['status']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: var(--font-size-sm);">
                                        <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Additional Dashboard Metrics -->
    <div class="chart-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
        <!-- Quick Action Cards -->
        <div class="improved-metric-card">
            <div class="improved-metric-header">
                <div class="improved-metric-icon primary">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="improved-metric-content">
                    <div class="improved-metric-value"><?php echo $totalPayments; ?></div>
                    <div class="improved-metric-label">Total Transactions</div>
                </div>
            </div>
        </div>

        <div class="improved-metric-card">
            <div class="improved-metric-header">
                <div class="improved-metric-icon success">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="improved-metric-content">
                    <div class="improved-metric-value"><?php echo number_format(($activeClans / max($totalClans, 1)) * 100, 1); ?>%</div>
                    <div class="improved-metric-label">Active Rate</div>
                </div>
            </div>
        </div>

        <div class="improved-metric-card">
            <div class="improved-metric-header">
                <div class="improved-metric-icon warning">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="improved-metric-content">
                    <div class="improved-metric-value"><?php echo $lastMonthUsers; ?></div>
                    <div class="improved-metric-label">New Users (30d)</div>
                </div>
            </div>
        </div>

        <div class="improved-metric-card">
            <div class="improved-metric-header">
                <div class="improved-metric-icon info">
                    <i class="fas fa-server"></i>
                </div>
                <div class="improved-metric-content">
                    <div class="improved-metric-value"><?php echo number_format($totalCodes / max($totalClans, 1), 1); ?></div>
                    <div class="improved-metric-label">Avg Codes/Clan</div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Dasher Chart Configuration and Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎨 Initializing Dasher UI Dashboard...');
    
    // Dasher theme-aware chart configuration
    function getDasherChartConfig() {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        
        return {
            colors: {
                primary: getComputedStyle(document.documentElement).getPropertyValue('--primary'),
                success: getComputedStyle(document.documentElement).getPropertyValue('--success'),
                warning: getComputedStyle(document.documentElement).getPropertyValue('--warning'),
                danger: getComputedStyle(document.documentElement).getPropertyValue('--danger'),
                info: getComputedStyle(document.documentElement).getPropertyValue('--info'),
                text: getComputedStyle(document.documentElement).getPropertyValue('--text-primary'),
                textSecondary: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary'),
                border: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
            },
            font: {
                family: getComputedStyle(document.documentElement).getPropertyValue('--font-family-base'),
                size: 12,
                weight: '400'
            }
        };
    }
    
    const chartConfig = getDasherChartConfig();
    
    // Revenue Chart (Enhanced Bar Chart)
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($monthlyRevenue, 'month')); ?>,
            datasets: [{
                label: 'Revenue',
                data: <?php echo json_encode(array_column($monthlyRevenue, 'revenue')); ?>,
                backgroundColor: chartConfig.colors.primary + '20',
                borderColor: chartConfig.colors.primary,
                borderWidth: 2,
                borderRadius: 4,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: chartConfig.colors.text + '10',
                    titleColor: chartConfig.colors.text,
                    bodyColor: chartConfig.colors.text,
                    borderColor: chartConfig.colors.border,
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    titleFont: {
                        family: chartConfig.font.family,
                        size: 14,
                        weight: '600'
                    },
                    bodyFont: {
                        family: chartConfig.font.family,
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            return 'Revenue: ₦' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        color: chartConfig.colors.textSecondary,
                        font: {
                            family: chartConfig.font.family,
                            size: 12
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    grid: {
                        color: chartConfig.colors.border + '40',
                        drawBorder: false
                    },
                    border: {
                        display: false
                    },
                    ticks: {
                        color: chartConfig.colors.textSecondary,
                        font: {
                            family: chartConfig.font.family,
                            size: 12
                        },
                        callback: function(value) {
                            return '₦' + value.toLocaleString();
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
    
    // Clan Distribution Chart (Enhanced Doughnut)
    const distributionCtx = document.getElementById('clanDistributionChart').getContext('2d');
    const distributionChart = new Chart(distributionCtx, {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Inactive', 'Free'],
            datasets: [{
                data: [
                    <?php echo $activeClansCount; ?>,
                    <?php echo $inactiveClansCount; ?>,
                    <?php echo $freeClansCount; ?>
                ],
                backgroundColor: [
                    chartConfig.colors.success,
                    chartConfig.colors.danger,
                    chartConfig.colors.info
                ],
                borderColor: [
                    chartConfig.colors.success,
                    chartConfig.colors.danger,
                    chartConfig.colors.info
                ],
                borderWidth: 0,
                hoverBorderWidth: 2,
                cutout: '70%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: chartConfig.colors.text + '10',
                    titleColor: chartConfig.colors.text,
                    bodyColor: chartConfig.colors.text,
                    borderColor: chartConfig.colors.border,
                    borderWidth: 1,
                    cornerRadius: 8,
                    padding: 12,
                    titleFont: {
                        family: chartConfig.font.family,
                        size: 14,
                        weight: '600'
                    },
                    bodyFont: {
                        family: chartConfig.font.family,
                        size: 13
                    },
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            },
            elements: {
                arc: {
                    borderWidth: 0
                }
            }
        }
    });
    
    // Export chart functionality - REMOVED per user request
    
    // Update charts when theme changes
    document.addEventListener('themeChanged', function(event) {
        console.log('🎨 Updating dashboard charts for theme:', event.detail.theme);
        
        const newConfig = getDasherChartConfig();
        
        // Update revenue chart
        revenueChart.data.datasets[0].backgroundColor = newConfig.colors.primary + '20';
        revenueChart.data.datasets[0].borderColor = newConfig.colors.primary;
        revenueChart.options.scales.x.ticks.color = newConfig.colors.textSecondary;
        revenueChart.options.scales.y.ticks.color = newConfig.colors.textSecondary;
        revenueChart.options.scales.y.grid.color = newConfig.colors.border + '40';
        revenueChart.update('none');
        
        // Update distribution chart
        distributionChart.data.datasets[0].backgroundColor = [
            newConfig.colors.success,
            newConfig.colors.danger,
            newConfig.colors.info
        ];
        distributionChart.update('none');
    });
    
    // Real-time data updates (optional)
    function updateDashboardData() {
        fetch('<?php echo BASE_URL; ?>api/dashboard-stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update statistic cards
                    console.log('📊 Dashboard data updated');
                }
            })
            .catch(error => {
                console.log('Dashboard update failed:', error);
            });
    }
    
    // Update dashboard data every 5 minutes
    setInterval(updateDashboardData, 5 * 60 * 1000);
    
    // Initialize sortable tables
    const sortableTables = document.querySelectorAll('.table-sortable');
    sortableTables.forEach(table => {
        const headers = table.querySelectorAll('th');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', () => {
                console.log('🔄 Sorting table by column:', index);
                // Add sorting logic here if needed
            });
        });
    });
    
    console.log('✅ Dasher UI Dashboard initialized successfully');
});

// Performance monitoring
if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
    window.testDashboard = function() {
        console.log('🧪 Testing Dashboard Components...');
        console.log('Charts loaded:', document.querySelectorAll('canvas').length);
        console.log('Stats cards:', document.querySelectorAll('.improved-stats-card').length);
        console.log('Tables:', document.querySelectorAll('.table').length);
        console.log('Metric cards:', document.querySelectorAll('.improved-metric-card').length);
        console.log('Theme system:', !!window.DasherTheme);
    };
    console.log('🔧 Debug function available: testDashboard()');
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>