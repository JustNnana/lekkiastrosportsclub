<?php
/**
 * Lekki Astro Sports Club
 * Member Dashboard — iOS Styled
 */

requireLogin();

$pageTitle = 'My Dashboard';
$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Load member record
$member   = $db->fetchOne("SELECT * FROM members WHERE user_id = ?", [$userId]);
$memberId = $member['id'] ?? 0; // members.id (not users.id)

// Payment summary — use members.id, not users.id
$paidCount = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM payments WHERE member_id = ? AND status = 'paid'", [$memberId]
)['c'] ?? 0);

$pendingCount = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM payments WHERE member_id = ? AND status IN ('pending','overdue')", [$memberId]
)['c'] ?? 0);

$overdueCount = (int)($db->fetchOne(
    "SELECT COUNT(*) AS c FROM payments WHERE member_id = ? AND status = 'overdue'", [$memberId]
)['c'] ?? 0);

// Upcoming events (4 for 2×2 grid)
$upcomingEvents = $db->fetchAll(
    "SELECT * FROM events WHERE start_date >= NOW() AND status = 'active' ORDER BY start_date ASC LIMIT 4"
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

// My pending payments — use members.id
$myDues = $db->fetchAll(
    "SELECT p.*, d.title AS due_title, d.amount AS due_amount, d.due_date
     FROM payments p JOIN dues d ON p.due_id = d.id
     WHERE p.member_id = ? AND p.status IN ('pending','overdue')
     ORDER BY d.due_date ASC LIMIT 5",
    [$memberId]
);

// Event type color map
$eventTypeColors = [
    'training' => ['bg' => 'rgba(0,122,255,.15)',   'color' => '#007aff'],
    'match'    => ['bg' => 'rgba(255,59,48,.15)',    'color' => '#ff3b30'],
    'meeting'  => ['bg' => 'rgba(255,149,0,.15)',    'color' => '#ff9500'],
    'social'   => ['bg' => 'rgba(175,82,222,.15)',   'color' => '#af52de'],
    'other'    => ['bg' => 'rgba(142,142,147,.15)',  'color' => '#8e8e93'],
];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<!-- ===== PAGE HEADER (desktop only) ===== -->
<div class="content-header">
    <div>
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
</div>

<!-- ===== MOBILE GREETING (hidden on desktop) ===== -->
<div class="ios-mobile-greeting">
    <h2><?php echo timeGreeting(); ?>, <?php echo e(explode(' ', $currentUser->getFullName())[0]); ?>!</h2>
    <p>Member ID: <?php echo e($member['member_id'] ?? '—'); ?> · <?php echo date('d F Y'); ?></p>
</div>

<!-- ===== STAT CARDS ===== -->
<div class="stats-overview-grid mb-4">
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <span class="stat-label">Dues Paid</span>
        </div>
        <p class="stat-value"><?php echo $paidCount; ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
            <span class="stat-label">Pending</span>
        </div>
        <p class="stat-value"><?php echo $pendingCount; ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
            <span class="stat-label">Overdue</span>
        </div>
        <p class="stat-value" <?php echo $overdueCount > 0 ? 'style="color:#ff3b30"' : ''; ?>><?php echo $overdueCount; ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-header">
            <div class="stat-icon blue"><i class="fas fa-calendar-check"></i></div>
            <span class="stat-label">Events Upcoming</span>
        </div>
        <p class="stat-value"><?php echo count($upcomingEvents); ?></p>
    </div>
</div>

<!-- ===== QUICK ACTIONS (desktop, 4-column) ===== -->
<div class="ios-quick-actions">
    <a href="<?php echo BASE_URL; ?>payments/my-payments.php" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(255,149,0,.15);color:#ff9500">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">My Dues</p>
            <p class="ios-quick-action-desc">View & pay outstanding dues</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
    <a href="<?php echo BASE_URL; ?>events/index.php" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(52,199,89,.15);color:#34c759">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">Events</p>
            <p class="ios-quick-action-desc">Upcoming club activities</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
    <a href="<?php echo BASE_URL; ?>polls/index.php" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(88,86,214,.15);color:#5856d6">
            <i class="fas fa-poll"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">Polls</p>
            <p class="ios-quick-action-desc">Cast your vote</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
    <a href="<?php echo BASE_URL; ?>announcements/" class="ios-quick-action">
        <div class="ios-quick-action-icon" style="background:rgba(0,122,255,.15);color:#007aff">
            <i class="fas fa-bullhorn"></i>
        </div>
        <div class="ios-quick-action-text">
            <p class="ios-quick-action-title">Announcements</p>
            <p class="ios-quick-action-desc">Club news & updates</p>
        </div>
        <i class="fas fa-chevron-right ios-quick-action-arrow"></i>
    </a>
</div>

<!-- ===== MOBILE QUICK ACTIONS (icon grid, mobile only) ===== -->
<div class="ios-mobile-actions">
    <p class="ios-mobile-actions-title">Quick Actions</p>
    <div class="ios-mobile-actions-grid">
        <a href="<?php echo BASE_URL; ?>payments/my-payments.php" class="ios-mobile-action-btn">
            <div class="ios-mobile-action-icon" style="background:#ff9500"><i class="fas fa-wallet"></i></div>
            <span class="ios-mobile-action-label">My Dues</span>
        </a>
        <a href="<?php echo BASE_URL; ?>events/index.php" class="ios-mobile-action-btn">
            <div class="ios-mobile-action-icon" style="background:#34c759"><i class="fas fa-calendar-alt"></i></div>
            <span class="ios-mobile-action-label">Events</span>
        </a>
        <a href="<?php echo BASE_URL; ?>polls/index.php" class="ios-mobile-action-btn">
            <div class="ios-mobile-action-icon" style="background:#5856d6"><i class="fas fa-poll"></i></div>
            <span class="ios-mobile-action-label">Polls</span>
        </a>
        <a href="<?php echo BASE_URL; ?>announcements/" class="ios-mobile-action-btn">
            <div class="ios-mobile-action-icon" style="background:#007aff"><i class="fas fa-bullhorn"></i></div>
            <span class="ios-mobile-action-label">News</span>
        </a>
    </div>
</div>

<!-- ===== MAIN CONTENT GRID ===== -->
<div class="row g-4">

    <!-- ── PENDING DUES ── -->
    <div class="col-12 col-lg-6">
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-header-left">
                    <div class="ios-section-icon" style="background:rgba(255,149,0,.15);color:#ff9500">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <div class="ios-section-title"><h5>My Pending Dues</h5></div>
                </div>
                <a href="<?php echo BASE_URL; ?>payments/my-payments.php" class="ios-link-btn">View All</a>
            </div>
            <div class="ios-section-body">
                <?php if (empty($myDues)): ?>
                <div class="ios-empty-state">
                    <i class="fas fa-check-circle" style="color:#34c759;opacity:1"></i>
                    <p>You're all caught up — no pending dues!</p>
                </div>
                <?php else: ?>
                <?php foreach ($myDues as $due): ?>
                <div class="ios-list-item">
                    <div class="ios-list-dot <?php echo $due['status'] === 'overdue' ? 'overdue' : 'pending'; ?>"></div>
                    <div class="ios-list-content">
                        <p class="ios-list-primary"><?php echo e($due['due_title']); ?></p>
                        <p class="ios-list-secondary">Due: <?php echo formatDate($due['due_date']); ?></p>
                    </div>
                    <div class="ios-list-meta">
                        <span class="ios-list-badge <?php echo $due['status'] === 'overdue' ? 'red' : 'orange'; ?>">
                            <?php echo formatCurrency($due['due_amount']); ?>
                        </span>
                        <a href="<?php echo BASE_URL; ?>payments/pay.php?id=<?php echo $due['id']; ?>"
                           class="ios-pay-btn">Pay Now</a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── UPCOMING EVENTS ── -->
    <div class="col-12 col-lg-6">
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-header-left">
                    <div class="ios-section-icon" style="background:rgba(52,199,89,.15);color:#34c759">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="ios-section-title"><h5>Upcoming Events</h5></div>
                </div>
                <a href="<?php echo BASE_URL; ?>events/index.php" class="ios-link-btn">View All</a>
            </div>
            <div class="ios-section-body">
                <?php if (empty($upcomingEvents)): ?>
                <div class="ios-empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No upcoming events scheduled.</p>
                </div>
                <?php else: ?>
                <div class="ios-events-grid">
                    <?php foreach ($upcomingEvents as $ev):
                        $tc = $eventTypeColors[$ev['event_type']] ?? $eventTypeColors['other'];
                    ?>
                    <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $ev['id']; ?>" class="ios-event-card">
                        <div class="ios-event-date-box"
                             style="background:<?php echo $tc['bg']; ?>;color:<?php echo $tc['color']; ?>">
                            <span class="month"><?php echo date('M', strtotime($ev['start_date'])); ?></span>
                            <span class="day"><?php echo date('j', strtotime($ev['start_date'])); ?></span>
                        </div>
                        <div class="ios-event-info">
                            <p class="ios-event-title"><?php echo e($ev['title']); ?></p>
                            <p class="ios-event-meta">
                                <i class="fas fa-clock"></i><?php echo date('g:i A', strtotime($ev['start_date'])); ?>
                            </p>
                            <?php if ($ev['location']): ?>
                            <p class="ios-event-meta">
                                <i class="fas fa-map-marker-alt"></i><?php echo e($ev['location']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── ACTIVE POLLS ── -->
    <div class="col-12 col-lg-6">
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-header-left">
                    <div class="ios-section-icon" style="background:rgba(88,86,214,.15);color:#5856d6">
                        <i class="fas fa-poll"></i>
                    </div>
                    <div class="ios-section-title"><h5>Active Polls</h5></div>
                </div>
                <a href="<?php echo BASE_URL; ?>polls/index.php" class="ios-link-btn">View All</a>
            </div>
            <div class="ios-section-body">
                <?php if (empty($activePolls)): ?>
                <div class="ios-empty-state">
                    <i class="fas fa-poll"></i>
                    <p>No active polls right now.</p>
                </div>
                <?php else: ?>
                <?php foreach ($activePolls as $poll): ?>
                <div class="ios-list-item">
                    <div class="ios-poll-icon"><i class="fas fa-poll"></i></div>
                    <div class="ios-list-content">
                        <p class="ios-list-primary"><?php echo e($poll['question']); ?></p>
                        <p class="ios-list-secondary">
                            <i class="fas fa-clock" style="font-size:9px"></i>
                            Closes <?php echo formatDate($poll['deadline'], 'd M Y'); ?>
                        </p>
                    </div>
                    <div class="ios-list-meta">
                        <?php if ($poll['has_voted']): ?>
                        <span class="ios-list-badge green">Voted</span>
                        <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>polls/vote.php?id=<?php echo $poll['id']; ?>"
                           class="ios-vote-btn">Vote</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── ANNOUNCEMENTS ── -->
    <div class="col-12 col-lg-6">
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-header-left">
                    <div class="ios-section-icon" style="background:rgba(0,122,255,.15);color:#007aff">
                        <i class="fas fa-bullhorn"></i>
                    </div>
                    <div class="ios-section-title"><h5>Latest Announcements</h5></div>
                </div>
                <a href="<?php echo BASE_URL; ?>announcements/" class="ios-link-btn">View All</a>
            </div>
            <div class="ios-section-body">
                <?php if (empty($recentAnnouncements)): ?>
                <div class="ios-empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <p>No announcements yet.</p>
                </div>
                <?php else: ?>
                <?php foreach ($recentAnnouncements as $ann): ?>
                <div class="ios-list-item">
                    <div class="ios-ann-dot"></div>
                    <div class="ios-list-content">
                        <p class="ios-list-primary">
                            <?php echo e($ann['title']); ?>
                            <?php if ($ann['is_pinned'] ?? false): ?>
                            <i class="fas fa-thumbtack" style="font-size:9px;color:#007aff;margin-left:4px"></i>
                            <?php endif; ?>
                        </p>
                        <p class="ios-list-secondary"><?php echo e(substr(strip_tags($ann['content']), 0, 70)); ?>…</p>
                        <p class="ios-list-tertiary"><?php echo formatDate($ann['created_at'], 'd M Y'); ?></p>
                    </div>
                    <div class="ios-list-meta">
                        <a href="<?php echo BASE_URL; ?>announcements/view.php?id=<?php echo $ann['id']; ?>"
                           class="ios-chevron-btn"><i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<style>
/* ── Stat cards — iOS left-aligned layout ── */
.stat-card {
    text-align: left !important;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 18px;
    transition: all 0.2s ease;
}
.stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.08); }
.stat-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.stat-icon { margin: 0 !important; flex-shrink: 0; }
.stat-icon.blue   { background: rgba(0,122,255,.15);  color: #007aff; }
.stat-icon.green  { background: rgba(52,199,89,.15);  color: #34c759; }
.stat-icon.orange { background: rgba(255,149,0,.15);  color: #ff9500; }
.stat-icon.red    { background: rgba(255,59,48,.15);  color: #ff3b30; }
.stat-label { font-size: 13px; color: var(--text-muted); margin: 0; font-weight: 500; }
.stat-value { font-size: 28px !important; font-weight: 700 !important; color: var(--text-primary) !important; margin: 0 !important; }

/* ── Stat grid ── */
.stats-overview-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

/* ── Quick Actions (desktop) ── */
.ios-quick-actions {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 24px;
}
.ios-quick-action {
    display: flex; align-items: center; gap: 14px;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 16px;
    text-decoration: none;
    transition: all 0.2s ease;
}
.ios-quick-action:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 16px rgba(0,0,0,.08);
    transform: translateY(-1px);
    text-decoration: none;
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

/* ── Mobile quick actions (hidden by default) ── */
.ios-mobile-actions {
    display: none;
    margin-bottom: 20px;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 16px;
}
.ios-mobile-actions-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin: 0 0 14px; }
.ios-mobile-actions-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;
}
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
.ios-mobile-greeting h2 { font-size: 22px; font-weight: 700; color: var(--text-primary); margin: 0 0 2px; }
.ios-mobile-greeting p  { font-size: 13px; color: var(--text-muted); margin: 0; }

/* ── iOS Section cards ── */
.ios-section-card {
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
}
.ios-section-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px;
    border-bottom: 1px solid var(--border-color);
}
.ios-section-header-left { display: flex; align-items: center; gap: 12px; }
.ios-section-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; flex-shrink: 0;
}
.ios-section-title h5 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-link-btn {
    font-size: 13px; font-weight: 500; color: #007aff;
    text-decoration: none; transition: opacity .2s ease; white-space: nowrap;
}
.ios-link-btn:hover { opacity: .7; color: #007aff; text-decoration: none; }

/* ── iOS List items ── */
.ios-list-item {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 20px;
    border-bottom: 1px solid var(--border-color);
    transition: background .15s ease;
}
.ios-list-item:last-child { border-bottom: none; }
.ios-list-item:hover { background: var(--bg-hover); }

.ios-list-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
}
.ios-list-dot.overdue { background: #ff3b30; }
.ios-list-dot.pending { background: #ff9500; }

.ios-list-content { flex: 1; min-width: 0; }
.ios-list-primary {
    font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ios-list-secondary { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }
.ios-list-tertiary  { font-size: 11px; color: var(--text-muted); margin: 2px 0 0; }

.ios-list-meta {
    display: flex; flex-direction: column; align-items: flex-end; flex-shrink: 0; gap: 4px;
}
.ios-list-badge {
    display: inline-flex; align-items: center;
    padding: 3px 8px; border-radius: 6px;
    font-size: 11px; font-weight: 600;
}
.ios-list-badge.green  { background: rgba(52,199,89,.15);  color: #34c759; }
.ios-list-badge.orange { background: rgba(255,149,0,.15);  color: #ff9500; }
.ios-list-badge.red    { background: rgba(255,59,48,.15);  color: #ff3b30; }
.ios-list-badge.blue   { background: rgba(0,122,255,.15);  color: #007aff; }

/* Pay Now button */
.ios-pay-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 4px 10px; border-radius: 8px;
    background: var(--primary); color: #fff;
    font-size: 11px; font-weight: 600;
    text-decoration: none; transition: opacity .2s ease;
}
.ios-pay-btn:hover { opacity: .85; color: #fff; text-decoration: none; }

/* Vote button */
.ios-vote-btn {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 4px 10px; border-radius: 8px;
    background: rgba(88,86,214,.12); color: #5856d6;
    font-size: 11px; font-weight: 600;
    text-decoration: none; transition: background .2s ease;
}
.ios-vote-btn:hover { background: rgba(88,86,214,.22); text-decoration: none; }

/* Poll icon chip */
.ios-poll-icon {
    width: 32px; height: 32px; border-radius: 8px; flex-shrink: 0;
    background: rgba(88,86,214,.12); color: #5856d6;
    display: flex; align-items: center; justify-content: center; font-size: 13px;
}

/* Announcement dot */
.ios-ann-dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
    background: #007aff; margin-top: 3px;
}

/* Announcement chevron */
.ios-chevron-btn {
    width: 28px; height: 28px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-muted); background: var(--bg-secondary);
    font-size: 11px; text-decoration: none;
    transition: all .2s ease;
}
.ios-chevron-btn:hover { background: var(--bg-hover); color: var(--primary); }

/* ── Events grid ── */
.ios-events-grid {
    display: grid; grid-template-columns: repeat(2, 1fr);
    gap: 10px; padding: 14px 16px;
}
.ios-event-card {
    display: flex; gap: 12px; padding: 12px;
    background: var(--bg-secondary); border-radius: 12px;
    text-decoration: none; transition: all .2s ease;
}
.ios-event-card:hover { background: var(--bg-hover); transform: translateY(-1px); text-decoration: none; }
.ios-event-date-box {
    width: 44px; min-width: 44px; height: 44px; border-radius: 10px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.ios-event-date-box .month { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; line-height: 1; }
.ios-event-date-box .day   { font-size: 17px; font-weight: 700; line-height: 1.1; }
.ios-event-info { flex: 1; min-width: 0; }
.ios-event-title {
    font-size: 13px; font-weight: 600; color: var(--text-primary);
    margin: 0 0 3px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ios-event-meta {
    font-size: 11px; color: var(--text-muted); margin: 0 0 2px;
    display: flex; align-items: center; gap: 4px;
}
.ios-event-meta i { font-size: 9px; width: 10px; flex-shrink: 0; }

/* ── Empty state ── */
.ios-empty-state {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 36px 20px; text-align: center; color: var(--text-muted);
}
.ios-empty-state i { font-size: 32px; margin-bottom: 10px; opacity: .4; }
.ios-empty-state p { font-size: 13px; margin: 0; }

/* ── Responsive ── */
@media (max-width: 1200px) {
    .ios-quick-action-title { font-size: 13px; }
    .ios-quick-action-desc  { display: none; }
    .ios-quick-action-icon  { width: 38px; height: 38px; font-size: 16px; }
}
@media (max-width: 992px) {
    .stats-overview-grid    { grid-template-columns: repeat(2, 1fr); }
    .ios-quick-actions      { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .content-header         { display: none !important; }
    .ios-mobile-greeting    { display: block; }
    .ios-quick-actions      { display: none; }
    .ios-mobile-actions     { display: block; }

    /* Horizontal scroll for stat cards on mobile */
    .stats-overview-grid {
        display: flex; overflow-x: auto; -webkit-overflow-scrolling: touch;
        gap: 12px; padding-bottom: 4px; scrollbar-width: none;
    }
    .stats-overview-grid::-webkit-scrollbar { display: none; }
    .stat-card { min-width: 140px; flex: 0 0 auto; padding: 14px; }
    .stat-header { margin-bottom: 8px; }
    .stat-icon   { width: 32px !important; height: 32px !important; font-size: 14px !important; border-radius: 9px !important; }
    .stat-value  { font-size: 22px !important; }

    .ios-events-grid { grid-template-columns: 1fr; padding: 12px 14px; gap: 8px; }
    .ios-section-header { padding: 14px 16px; }
    .ios-list-item { padding: 12px 16px; }
}
@media (max-width: 480px) {
    .ios-mobile-greeting h2 { font-size: 20px; }
    .stat-card  { min-width: 130px; padding: 12px; }
    .stat-value { font-size: 20px !important; }
    .ios-mobile-actions      { border-radius: 12px; }
    .ios-mobile-action-icon  { width: 48px; height: 48px; border-radius: 12px; font-size: 20px; }
}
@media (max-width: 390px) {
    .stat-card  { min-width: 120px; padding: 10px; }
    .stat-value { font-size: 18px !important; }
    .ios-mobile-action-icon { width: 44px; height: 44px; font-size: 18px; }
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
