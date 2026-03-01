<?php
/**
 * Events — Member calendar + list view
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Event.php';

requireLogin();

$pageTitle = 'Events & Calendar';
$eventObj  = new Event();

// Current month for calendar
$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('n'));
if ($month < 1) { $month = 12; $year--; }
if ($month > 12) { $month = 1; $year++; }

$monthEvents   = $eventObj->getByMonth($year, $month);
$upcomingList  = $eventObj->getUpcoming(20);
$userId        = (int)$_SESSION['user_id'];

// Group calendar events by day
$byDay = [];
foreach ($monthEvents as $ev) {
    $day = (int)date('j', strtotime($ev['start_date']));
    $byDay[$day][] = $ev;
}

$typeColors = [
    'training' => '#3b82f6',
    'match'    => '#ef4444',
    'meeting'  => '#f59e0b',
    'social'   => '#8b5cf6',
    'other'    => '#6b7280',
];

$monthName  = date('F Y', mktime(0,0,0,$month,1,$year));
$firstDay   = (int)date('w', mktime(0,0,0,$month,1,$year)); // 0=Sun
$daysInMonth= (int)date('t', mktime(0,0,0,$month,1,$year));
$prevMonth  = $month === 1 ? ['year'=>$year-1,'month'=>12] : ['year'=>$year,'month'=>$month-1];
$nextMonth  = $month === 12 ? ['year'=>$year+1,'month'=>1] : ['year'=>$year,'month'=>$month+1];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title">Events & Calendar</h1>
        <p class="content-subtitle">View upcoming club activities.</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>events/manage.php" class="btn btn-secondary"><i class="fas fa-list me-2"></i>Manage</a>
        <a href="<?php echo BASE_URL; ?>events/form.php" class="btn btn-primary"><i class="fas fa-plus me-2"></i>New Event</a>
    </div>
    <?php endif; ?>
</div>

<!-- Event Type Legend -->
<div class="d-flex flex-wrap gap-3 mb-4">
    <?php foreach (['training'=>'Training','match'=>'Match','meeting'=>'Meeting','social'=>'Social','other'=>'Other'] as $k=>$lbl): ?>
    <span class="d-flex align-items-center gap-1 small">
        <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $typeColors[$k]; ?>;display:inline-block"></span>
        <?php echo $lbl; ?>
    </span>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Calendar -->
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <a href="?year=<?php echo $prevMonth['year']; ?>&month=<?php echo $prevMonth['month']; ?>" class="btn btn-secondary btn-sm">‹</a>
                <h6 class="card-title mb-0"><?php echo $monthName; ?></h6>
                <a href="?year=<?php echo $nextMonth['year']; ?>&month=<?php echo $nextMonth['month']; ?>" class="btn btn-secondary btn-sm">›</a>
            </div>
            <div class="card-body p-0">
                <div class="cal-grid">
                    <!-- Day headers -->
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dh): ?>
                    <div class="cal-header"><?php echo $dh; ?></div>
                    <?php endforeach; ?>
                    <!-- Empty cells before month starts -->
                    <?php for ($i = 0; $i < $firstDay; $i++): ?>
                    <div class="cal-cell cal-empty"></div>
                    <?php endfor; ?>
                    <!-- Days -->
                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $isToday = ($d === (int)date('j') && $month === (int)date('n') && $year === (int)date('Y'));
                    ?>
                    <div class="cal-cell <?php echo $isToday ? 'cal-today' : ''; ?>">
                        <div class="cal-day-num"><?php echo $d; ?></div>
                        <?php if (!empty($byDay[$d])): ?>
                        <?php foreach (array_slice($byDay[$d], 0, 3) as $ev): ?>
                        <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $ev['id']; ?>"
                           class="cal-event" style="background:<?php echo $typeColors[$ev['event_type']]; ?>20;color:<?php echo $typeColors[$ev['event_type']]; ?>;border-left:3px solid <?php echo $typeColors[$ev['event_type']]; ?>"
                           title="<?php echo e($ev['title']); ?>">
                            <?php echo e(mb_substr($ev['title'], 0, 18)); ?>
                        </a>
                        <?php endforeach; ?>
                        <?php if (count($byDay[$d]) > 3): ?>
                        <span class="cal-more">+<?php echo count($byDay[$d])-3; ?> more</span>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming list -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="card-title mb-0"><i class="fas fa-list-ul me-2"></i>Upcoming Events</h6></div>
            <div class="card-body p-0">
                <?php if (empty($upcomingList)): ?>
                <div class="text-center text-muted py-5">No upcoming events.</div>
                <?php else: ?>
                <?php foreach ($upcomingList as $ev):
                    $userRsvp = $eventObj->getUserRsvp($ev['id'], $userId);
                ?>
                <div class="event-list-item">
                    <div class="event-dot" style="background:<?php echo $typeColors[$ev['event_type']]; ?>"></div>
                    <div class="flex-fill">
                        <div class="fw-semibold small">
                            <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $ev['id']; ?>"
                               class="text-body text-decoration-none"><?php echo e($ev['title']); ?></a>
                        </div>
                        <div class="text-muted" style="font-size:11px">
                            <?php echo formatDate($ev['start_date'], 'd M, g:i A'); ?>
                            <?php if ($ev['location']): ?> · <?php echo e($ev['location']); ?><?php endif; ?>
                        </div>
                        <?php if ($userRsvp): ?>
                        <span class="badge mt-1" style="font-size:9px;background:<?php echo $userRsvp==='attending'?'#16a34a20':'#f59e0b20'; ?>;color:<?php echo $userRsvp==='attending'?'#16a34a':'#ca8a04'; ?>">
                            <?php echo ucfirst(str_replace('_',' ',$userRsvp)); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Calendar grid */
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border-left: 1px solid var(--border-color);
    border-top: 1px solid var(--border-color);
}
.cal-header {
    text-align: center;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--text-muted);
    padding: 8px 4px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    background: var(--surface-2);
}
.cal-cell {
    min-height: 90px;
    padding: 6px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
}
.cal-empty { background: var(--surface-2); }
.cal-today { background: rgba(var(--primary-rgb),.05); }
.cal-day-num {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    margin-bottom: 4px;
}
.cal-today .cal-day-num {
    color: var(--primary);
    background: var(--primary);
    color: #fff;
    width: 22px; height: 22px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px;
}
.cal-event {
    display: block;
    font-size: 10px;
    padding: 2px 4px;
    border-radius: 3px;
    margin-bottom: 2px;
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    font-weight: 500;
}
.cal-more { font-size: 10px; color: var(--text-muted); }

/* Upcoming list */
.event-list-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color);
}
.event-list-item:last-child { border-bottom: none; }
.event-dot {
    width: 8px; height: 8px; border-radius: 50%;
    flex-shrink: 0; margin-top: 5px;
}
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
