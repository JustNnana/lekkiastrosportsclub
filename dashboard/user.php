<?php
/**
 * Lekki Astro Sports Club
 * Member Dashboard
 */

requireLogin();

$pageTitle = 'My Dashboard';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Load member record
$member = $db->fetchOne(
    "SELECT * FROM members WHERE user_id = ?", [$userId]
);

// Payment summary
$paidCount = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM payments WHERE member_id = ? AND status = 'paid'", [$userId]
)['c'] ?? 0);

$pendingCount = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM payments WHERE member_id = ? AND status IN ('pending','overdue')", [$userId]
)['c'] ?? 0);

$overdueCount = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM payments WHERE member_id = ? AND status = 'overdue'", [$userId]
)['c'] ?? 0);

// Upcoming events
$upcomingEvents = $db->fetchAll(
    "SELECT * FROM events WHERE start_date >= NOW() AND status = 'active' ORDER BY start_date ASC LIMIT 3"
);

// Active polls
$activePolls = $db->fetchAll(
    "SELECT p.*, (SELECT COUNT(*) FROM poll_votes WHERE poll_id = p.id AND user_id = ?) AS has_voted
     FROM polls p WHERE p.deadline > NOW() AND p.status = 'active' ORDER BY p.created_at DESC LIMIT 3",
    [$userId]
);

// Recent announcements
$recentAnnouncements = $db->fetchAll(
    "SELECT * FROM announcements WHERE is_published = 1 ORDER BY created_at DESC LIMIT 3"
);

// My pending payments
$myDues = $db->fetchAll(
    "SELECT p.*, d.title AS due_title, d.amount AS due_amount, d.due_date
     FROM payments p JOIN dues d ON p.due_id = d.id
     WHERE p.member_id = ? AND p.status IN ('pending','overdue')
     ORDER BY d.due_date ASC LIMIT 5",
    [$userId]
);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<!-- ===== PAGE HEADER ===== -->
<div class="content-header">
    <h1 class="content-title">
        <?php echo timeGreeting(); ?>,
        <?php echo e(explode(' ', $currentUser->getFullName())[0]); ?>!
    </h1>
    <p class="content-subtitle">
        Member ID: <strong><?php echo e($member['member_id'] ?? '—'); ?></strong>
        &nbsp;·&nbsp;
        <?php echo date('l, d F Y'); ?>
    </p>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="row g-4 mb-6">
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon stat-icon-success"><i class="fas fa-check-circle"></i></div>
                <p class="stat-label">Dues Paid</p>
                <h3 class="stat-value"><?php echo $paidCount; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon stat-icon-warning"><i class="fas fa-clock"></i></div>
                <p class="stat-label">Pending</p>
                <h3 class="stat-value"><?php echo $pendingCount; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon stat-icon-danger"><i class="fas fa-exclamation-circle"></i></div>
                <p class="stat-label">Overdue</p>
                <h3 class="stat-value text-danger"><?php echo $overdueCount; ?></h3>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="stat-icon stat-icon-primary"><i class="fas fa-calendar-check"></i></div>
                <p class="stat-label">Events Upcoming</p>
                <h3 class="stat-value"><?php echo count($upcomingEvents); ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">

    <!-- Pending dues -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title">My Pending Dues</h6>
                <a href="<?php echo BASE_URL; ?>payments/my-dues.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($myDues)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-check-circle fa-2x text-success mb-3 d-block"></i>
                    You're all caught up — no pending dues!
                </div>
                <?php else: ?>
                <ul class="due-list">
                    <?php foreach ($myDues as $due): ?>
                    <li class="due-item <?php echo $due['status'] === 'overdue' ? 'overdue' : ''; ?>">
                        <div class="due-info">
                            <p class="due-title"><?php echo e($due['due_title']); ?></p>
                            <p class="due-date">Due: <?php echo formatDate($due['due_date']); ?></p>
                        </div>
                        <div class="due-actions">
                            <span class="due-amount"><?php echo formatCurrency($due['due_amount']); ?></span>
                            <a href="<?php echo BASE_URL; ?>payments/pay.php?id=<?php echo $due['id']; ?>"
                               class="btn btn-primary btn-sm">Pay</a>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upcoming events -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title">Upcoming Events</h6>
                <a href="<?php echo BASE_URL; ?>events/" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($upcomingEvents)): ?>
                <div class="text-center text-muted py-5">No upcoming events scheduled.</div>
                <?php else: ?>
                <ul class="event-list">
                    <?php foreach ($upcomingEvents as $ev): ?>
                    <li class="event-item">
                        <div class="event-date-badge">
                            <span class="event-day"><?php echo date('d', strtotime($ev['start_date'])); ?></span>
                            <span class="event-mon"><?php echo date('M', strtotime($ev['start_date'])); ?></span>
                        </div>
                        <div class="event-info">
                            <p class="event-title"><?php echo e($ev['title']); ?></p>
                            <p class="event-meta">
                                <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($ev['start_date'])); ?>
                                &nbsp;·&nbsp;
                                <i class="fas fa-map-marker-alt"></i> <?php echo e($ev['location'] ?? 'TBD'); ?>
                            </p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Active polls -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title">Active Polls</h6>
                <a href="<?php echo BASE_URL; ?>polls/" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($activePolls)): ?>
                <div class="text-center text-muted py-4">No active polls right now.</div>
                <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($activePolls as $poll): ?>
                    <div class="poll-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <p class="poll-question"><?php echo e($poll['question']); ?></p>
                            <?php if ($poll['has_voted']): ?>
                            <span class="badge badge-success">Voted</span>
                            <?php else: ?>
                            <span class="badge badge-warning">Pending</span>
                            <?php endif; ?>
                        </div>
                        <p class="poll-meta text-muted">
                            <i class="fas fa-clock"></i>
                            Closes <?php echo formatDate($poll['deadline'], 'd M Y, g:i A'); ?>
                        </p>
                        <?php if (!$poll['has_voted']): ?>
                        <a href="<?php echo BASE_URL; ?>polls/vote.php?id=<?php echo $poll['id']; ?>"
                           class="btn btn-primary btn-sm mt-2">Cast Vote</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent announcements -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title">Latest Announcements</h6>
                <a href="<?php echo BASE_URL; ?>announcements/" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentAnnouncements)): ?>
                <div class="text-center text-muted py-4">No announcements yet.</div>
                <?php else: ?>
                <div class="d-flex flex-column gap-4">
                    <?php foreach ($recentAnnouncements as $ann): ?>
                    <div class="announcement-item">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h6 class="announcement-title"><?php echo e($ann['title']); ?></h6>
                            <?php if ($ann['is_pinned'] ?? false): ?>
                            <span class="badge badge-primary"><i class="fas fa-thumbtack"></i> Pinned</span>
                            <?php endif; ?>
                        </div>
                        <p class="announcement-excerpt text-muted">
                            <?php echo e(substr(strip_tags($ann['content']), 0, 100)); ?>…
                        </p>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted"><?php echo formatDate($ann['created_at'], 'd M Y'); ?></small>
                            <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $ann['id']; ?>"
                               class="btn btn-outline-primary btn-sm">Read more</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<style>
/* Stat cards */
.stat-icon-danger { background: var(--danger-light); color: var(--danger); }

/* Due list */
.due-list { list-style: none; padding: 0; margin: 0; }
.due-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: var(--spacing-4) var(--spacing-5);
    border-bottom: 1px solid var(--border-light);
    gap: var(--spacing-4);
}
.due-item:last-child { border-bottom: none; }
.due-item.overdue { background: var(--danger-light); }
.due-title { font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--text-primary); margin: 0 0 2px; }
.due-date  { font-size: var(--font-size-xs); color: var(--text-muted); margin: 0; }
.due-actions { display: flex; align-items: center; gap: var(--spacing-3); flex-shrink: 0; }
.due-amount { font-size: var(--font-size-sm); font-weight: var(--font-weight-bold); color: var(--text-primary); }

/* Event list */
.event-list { list-style: none; padding: 0; margin: 0; }
.event-item {
    display: flex; align-items: center; gap: var(--spacing-4);
    padding: var(--spacing-4) var(--spacing-5);
    border-bottom: 1px solid var(--border-light);
}
.event-item:last-child { border-bottom: none; }
.event-date-badge {
    width: 44px; flex-shrink: 0;
    background: var(--primary-light); border-radius: var(--border-radius);
    display: flex; flex-direction: column; align-items: center; padding: var(--spacing-2);
}
.event-day { font-size: var(--font-size-2xl); font-weight: var(--font-weight-bold); color: var(--primary); line-height: 1; }
.event-mon { font-size: 10px; text-transform: uppercase; color: var(--primary); font-weight: var(--font-weight-semibold); }
.event-title { font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--text-primary); margin: 0 0 2px; }
.event-meta  { font-size: var(--font-size-xs); color: var(--text-muted); margin: 0; }

/* Poll item */
.poll-item { padding: var(--spacing-4); background: var(--bg-secondary); border-radius: var(--border-radius); }
.poll-question { font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--text-primary); margin: 0 0 var(--spacing-1); }
.poll-meta { font-size: var(--font-size-xs); margin: 0; }

/* Announcement item */
.announcement-title { font-size: var(--font-size-sm); font-weight: var(--font-weight-semibold); color: var(--text-primary); margin: 0; }
.announcement-excerpt { font-size: var(--font-size-sm); margin: var(--spacing-1) 0 var(--spacing-2); }
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
