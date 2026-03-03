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
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

$monthEvents  = $eventObj->getByMonth($year, $month);
$upcomingList = $eventObj->getUpcoming(20);
$userId       = (int)$_SESSION['user_id'];

// Group calendar events by day
$byDay = [];
foreach ($monthEvents as $ev) {
    $day = (int)date('j', strtotime($ev['start_date']));
    $byDay[$day][] = $ev;
}

$typeColors = [
    'training' => ['bg' => 'rgba(59,130,246,.12)',  'hex' => '#3b82f6'],
    'match'    => ['bg' => 'rgba(239,68,68,.12)',   'hex' => '#ef4444'],
    'meeting'  => ['bg' => 'rgba(245,158,11,.12)',  'hex' => '#f59e0b'],
    'social'   => ['bg' => 'rgba(139,92,246,.12)',  'hex' => '#8b5cf6'],
    'other'    => ['bg' => 'rgba(107,114,128,.12)', 'hex' => '#6b7280'],
];
$typeLabels = ['training'=>'Training','match'=>'Match','meeting'=>'Meeting','social'=>'Social','other'=>'Other'];

$monthName   = date('F Y', mktime(0,0,0,$month,1,$year));
$firstDay    = (int)date('w', mktime(0,0,0,$month,1,$year)); // 0=Sun
$daysInMonth = (int)date('t', mktime(0,0,0,$month,1,$year));
$prevMonth   = $month === 1  ? ['year'=>$year-1,'month'=>12] : ['year'=>$year,'month'=>$month-1];
$nextMonth   = $month === 12 ? ['year'=>$year+1,'month'=>1]  : ['year'=>$year,'month'=>$month+1];

// Count upcoming per type for stats
$typeCounts = array_fill_keys(array_keys($typeColors), 0);
foreach ($upcomingList as $ev) {
    $t = $ev['event_type'] ?? 'other';
    if (isset($typeCounts[$t])) $typeCounts[$t]++;
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
/* ── iOS Events Page ──────────────────────────────── */
:root {
    --ios-red:    #FF453A;
    --ios-orange: #FF9F0A;
    --ios-green:  #30D158;
    --ios-blue:   #0A84FF;
    --ios-purple: #BF5AF2;
}

/* Section card (consistent with other pages) */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
}

.ios-section-header {
    display: flex; align-items: center; gap: 14px;
    padding: 18px 20px;
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-section-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; flex-shrink: 0;
}
.ios-section-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-section-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-section-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-section-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }

.ios-section-title-wrap { flex: 1; min-width: 0; }
.ios-section-title-wrap h5 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title-wrap p  { font-size: 12px; color: var(--text-secondary); margin: 0; }

.ios-link-btn {
    font-size: 13px; font-weight: 600; color: var(--primary);
    text-decoration: none; flex-shrink: 0;
    display: flex; align-items: center; gap: 4px;
}
.ios-link-btn:hover { text-decoration: underline; }

/* ── Mobile page header ── */
.ios-mobile-header {
    display: none;
    align-items: center; justify-content: space-between;
    margin-bottom: 16px;
}
.ios-mobile-header-text h1 { font-size: 22px; font-weight: 700; color: var(--text-primary); margin: 0 0 2px; }
.ios-mobile-header-text p  { font-size: 13px; color: var(--text-muted); margin: 0; }
.ios-dots-btn {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    color: var(--text-primary); font-size: 15px; cursor: pointer;
    flex-shrink: 0; transition: background .15s;
}
.ios-dots-btn:hover { background: var(--bg-hover); }

/* Events grid layout */
.ios-events-layout {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
    align-items: start;
}

/* Type legend */
.ios-type-legend {
    display: flex; flex-wrap: wrap; gap: 8px;
    margin-bottom: 16px;
}
.ios-type-pill {
    display: flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 600;
    border: 1px solid transparent;
}

/* Calendar */
.ios-cal-nav {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 20px; border-bottom: 1px solid var(--border-color);
}
.ios-cal-month { font-size: 16px; font-weight: 700; color: var(--text-primary); }
.ios-cal-nav-btn {
    width: 32px; height: 32px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    color: var(--text-primary); text-decoration: none; font-size: 14px;
    transition: background .2s;
}
.ios-cal-nav-btn:hover { background: var(--border-color); }

.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border-left: 1px solid var(--border-color);
    border-top: 1px solid var(--border-color);
}
.cal-header {
    text-align: center;
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .5px;
    color: var(--text-muted); padding: 8px 2px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    background: var(--surface-2);
}
.cal-cell {
    min-height: 80px; padding: 5px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
}
.cal-empty  { background: var(--surface-2); }
.cal-today  { background: rgba(var(--primary-rgb),.04); }
.cal-day-num {
    font-size: 12px; font-weight: 600; color: var(--text-secondary);
    margin-bottom: 3px; display: inline-block;
}
.cal-today .cal-day-num {
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--primary); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px;
}
.cal-event {
    display: block; font-size: 10px; font-weight: 500;
    padding: 2px 5px; border-radius: 4px;
    margin-bottom: 2px; text-decoration: none;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cal-more { font-size: 10px; color: var(--text-muted); }

/* Colored dots (shown on small mobile instead of text events) */
.cal-dots {
    display: none;
    flex-direction: row; gap: 3px; flex-wrap: wrap;
    margin-top: 2px;
}
.cal-dot {
    width: 7px; height: 7px; border-radius: 50%;
    display: block; flex-shrink: 0;
    text-decoration: none;
    transition: opacity .15s;
}
.cal-dot:hover { opacity: .7; }

/* Upcoming list */
.ios-event-list-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--border-color);
    transition: background .15s;
}
.ios-event-list-item:last-child { border-bottom: none; }
.ios-event-list-item:hover { background: var(--bg-subtle); }

.ios-event-dot {
    width: 10px; height: 10px; border-radius: 50%;
    flex-shrink: 0; margin-top: 5px;
}
.ios-event-content { flex: 1; min-width: 0; }
.ios-event-title {
    font-size: 14px; font-weight: 600; color: var(--text-primary);
    text-decoration: none; display: block; margin-bottom: 2px;
}
.ios-event-title:hover { color: var(--primary); }
.ios-event-meta { font-size: 11px; color: var(--text-muted); }

.ios-event-rsvp {
    font-size: 10px; font-weight: 600; padding: 2px 8px;
    border-radius: 20px; flex-shrink: 0; align-self: center;
}
.rsvp-attending     { background: rgba(48,209,88,.12);  color: #16a34a; }
.rsvp-not_attending { background: rgba(255,69,58,.12);  color: #ef4444; }
.rsvp-maybe         { background: rgba(245,158,11,.12); color: #ca8a04; }

/* Stats row */
.ios-type-stats {
    display: grid; grid-template-columns: repeat(5,1fr); gap: 8px;
    padding: 16px 20px; border-bottom: 1px solid var(--border-color);
}
.ios-type-stat { text-align: center; }
.ios-type-stat-value { font-size: 18px; font-weight: 700; color: var(--text-primary); }
.ios-type-stat-label { font-size: 10px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }

.ios-empty-state {
    text-align: center; padding: 40px 20px; color: var(--text-secondary);
}
.ios-empty-state i { font-size: 40px; opacity: .4; display: block; margin-bottom: 12px; }
.ios-empty-state p { font-size: 14px; margin: 0; }

/* ── iOS Bottom Sheet (page menu) ── */
.ios-menu-backdrop {
    display: none; position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,.4); backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}
.ios-menu-backdrop.active { display: block; }
.ios-page-menu {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 10000;
    background: var(--bg-card); border-radius: 20px 20px 0 0;
    padding: 0 0 max(20px, env(safe-area-inset-bottom));
    transform: translateY(100%); transition: transform .35s cubic-bezier(.32,1,.23,1);
    touch-action: none;
}
.ios-page-menu.open { transform: translateY(0); }
.ios-sheet-handle {
    width: 36px; height: 4px; border-radius: 2px;
    background: var(--border-color); margin: 10px auto 6px; cursor: grab;
}
.ios-sheet-title {
    font-size: 13px; font-weight: 600; color: var(--text-muted);
    text-align: center; padding: 4px 20px 12px; border-bottom: 1px solid var(--border-color);
}
.ios-sheet-link {
    display: flex; align-items: center; gap: 14px;
    padding: 15px 20px; border-bottom: 1px solid var(--border-color);
    text-decoration: none; color: var(--text-primary);
    font-size: 15px; font-weight: 500; transition: background .15s;
}
.ios-sheet-link:last-child { border-bottom: none; }
.ios-sheet-link:hover { background: var(--bg-hover); text-decoration: none; color: var(--text-primary); }
.ios-sheet-link-icon {
    width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 15px;
}

/* ── Responsive ─────────────────────── */
@media (max-width: 1100px) {
    .ios-events-layout { grid-template-columns: 1fr 300px; }
}
@media (max-width: 992px) {
    .ios-events-layout { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .content-header      { display: none !important; }
    .ios-mobile-header   { display: flex; }

    /* Calendar — smaller cells, keep visible */
    .cal-cell       { min-height: 52px; padding: 3px; }
    .cal-header     { padding: 6px 2px; font-size: 9px; }
    .cal-day-num    { font-size: 11px; margin-bottom: 2px; }
    .cal-today .cal-day-num { width: 20px; height: 20px; font-size: 10px; }
    .cal-event      { font-size: 9px; padding: 1px 3px; border-left-width: 2px !important; }
    .cal-more       { font-size: 9px; }

    .ios-type-stats { grid-template-columns: repeat(5,1fr); gap: 6px; padding: 12px; }
    .ios-type-stat-value { font-size: 15px; }
    .ios-section-header  { padding: 14px 16px; gap: 12px; }
}
@media (max-width: 480px) {
    .cal-cell       { min-height: 44px; padding: 2px; }
    .cal-header     { font-size: 8px; padding: 5px 1px; }
    .cal-day-num    { font-size: 10px; margin-bottom: 1px; }
    .cal-today .cal-day-num { width: 18px; height: 18px; font-size: 9px; }

    /* Hide text labels, show colored clickable dots */
    .cal-event { display: none; }
    .cal-more  { display: none; }
    .cal-dots  { display: flex; }

    .ios-type-stats { grid-template-columns: repeat(5,1fr); gap: 4px; padding: 10px; }
    .ios-type-stat-value { font-size: 14px; }
    .ios-type-stat-label { font-size: 9px; }
    .ios-event-list-item { padding: 11px 14px; }
}
</style>

<!-- ===== MOBILE PAGE HEADER (hidden on desktop) ===== -->
<div class="ios-mobile-header">
    <div class="ios-mobile-header-text">
        <h1>Events</h1>
        <p>Club activities & calendar</p>
    </div>
    <?php if (isAdmin()): ?>
    <button class="ios-dots-btn" onclick="openPageMenu()" aria-label="More options">
        <i class="fas fa-ellipsis-h"></i>
    </button>
    <?php endif; ?>
</div>

<!-- Desktop Page Header -->
<div class="content-header d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        <h1 class="content-title">Events & Calendar</h1>
        <p class="content-subtitle">View upcoming club activities and schedule.</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>events/manage.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-list me-1"></i>Manage
        </a>
        <a href="<?php echo BASE_URL; ?>events/form.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>New Event
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Event Type Legend -->
<div class="ios-type-legend mb-3">
    <?php foreach ($typeColors as $key => $clr): ?>
    <span class="ios-type-pill"
          style="background:<?php echo $clr['bg']; ?>;color:<?php echo $clr['hex']; ?>;border-color:<?php echo $clr['hex']; ?>20">
        <span style="width:8px;height:8px;border-radius:50%;background:<?php echo $clr['hex']; ?>;display:inline-block;flex-shrink:0"></span>
        <?php echo $typeLabels[$key]; ?>
    </span>
    <?php endforeach; ?>
</div>

<!-- Main Layout -->
<div class="ios-events-layout">

    <!-- ───── Calendar ───── -->
    <div>
        <div class="ios-section-card">

            <!-- Nav bar -->
            <div class="ios-cal-nav">
                <a href="?year=<?php echo $prevMonth['year']; ?>&month=<?php echo $prevMonth['month']; ?>" class="ios-cal-nav-btn">‹</a>
                <span class="ios-cal-month"><?php echo $monthName; ?></span>
                <a href="?year=<?php echo $nextMonth['year']; ?>&month=<?php echo $nextMonth['month']; ?>" class="ios-cal-nav-btn">›</a>
            </div>

            <!-- Grid -->
            <div class="cal-grid">
                <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dh): ?>
                <div class="cal-header"><?php echo $dh; ?></div>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < $firstDay; $i++): ?>
                <div class="cal-cell cal-empty"></div>
                <?php endfor; ?>

                <?php for ($d = 1; $d <= $daysInMonth; $d++):
                    $isToday  = $d === (int)date('j') && $month === (int)date('n') && $year === (int)date('Y');
                    $dayEvs   = $byDay[$d] ?? [];
                ?>
                <div class="cal-cell <?php echo $isToday ? 'cal-today' : ''; ?> <?php echo !empty($dayEvs) ? 'cal-has-events' : ''; ?>">
                    <div class="cal-day-num"><?php echo $d; ?></div>

                    <?php if (!empty($dayEvs)): ?>

                    <!-- Desktop: text event labels (up to 2) -->
                    <?php foreach (array_slice($dayEvs, 0, 2) as $ev):
                        $clr = $typeColors[$ev['event_type'] ?? 'other'] ?? $typeColors['other'];
                    ?>
                    <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $ev['id']; ?>"
                       class="cal-event"
                       style="background:<?php echo $clr['bg']; ?>;color:<?php echo $clr['hex']; ?>;border-left:3px solid <?php echo $clr['hex']; ?>"
                       title="<?php echo e($ev['title']); ?>">
                        <?php echo e(mb_substr($ev['title'], 0, 16)); ?>
                    </a>
                    <?php endforeach; ?>
                    <?php if (count($dayEvs) > 2): ?>
                    <span class="cal-more">+<?php echo count($dayEvs)-2; ?> more</span>
                    <?php endif; ?>

                    <!-- Mobile: colored clickable dots (up to 3) -->
                    <div class="cal-dots">
                        <?php foreach (array_slice($dayEvs, 0, 3) as $ev):
                            $clr = $typeColors[$ev['event_type'] ?? 'other'] ?? $typeColors['other'];
                        ?>
                        <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $ev['id']; ?>"
                           class="cal-dot"
                           style="background:<?php echo $clr['hex']; ?>"
                           title="<?php echo e($ev['title']); ?>"></a>
                        <?php endforeach; ?>
                    </div>

                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>

        </div><!-- /ios-section-card -->
    </div>

    <!-- ───── Upcoming List ───── -->
    <div>
        <div class="ios-section-card">

            <!-- Header -->
            <div class="ios-section-header">
                <div class="ios-section-icon green"><i class="fas fa-calendar-alt"></i></div>
                <div class="ios-section-title-wrap">
                    <h5>Upcoming Events</h5>
                    <p><?php echo count($upcomingList); ?> event<?php echo count($upcomingList) !== 1 ? 's' : ''; ?> ahead</p>
                </div>
            </div>

            <!-- Type counts -->
            <?php $totalUpcoming = array_sum($typeCounts); ?>
            <?php if ($totalUpcoming > 0): ?>
            <div class="ios-type-stats">
                <?php foreach ($typeColors as $key => $clr): ?>
                <div class="ios-type-stat">
                    <div class="ios-type-stat-value" style="color:<?php echo $clr['hex']; ?>"><?php echo $typeCounts[$key]; ?></div>
                    <div class="ios-type-stat-label"><?php echo $typeLabels[$key]; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- List -->
            <?php if (empty($upcomingList)): ?>
            <div class="ios-empty-state">
                <i class="fas fa-calendar-times"></i>
                <p>No upcoming events scheduled.</p>
            </div>
            <?php else: ?>
            <?php foreach ($upcomingList as $ev):
                $clr      = $typeColors[$ev['event_type'] ?? 'other'] ?? $typeColors['other'];
                $userRsvp = $eventObj->getUserRsvp($ev['id'], $userId);
            ?>
            <div class="ios-event-list-item">
                <div class="ios-event-dot" style="background:<?php echo $clr['hex']; ?>"></div>
                <div class="ios-event-content">
                    <a href="<?php echo BASE_URL; ?>events/view.php?id=<?php echo $ev['id']; ?>" class="ios-event-title">
                        <?php echo e($ev['title']); ?>
                    </a>
                    <div class="ios-event-meta">
                        <i class="fas fa-clock" style="margin-right:3px"></i><?php echo formatDate($ev['start_date'], 'd M, g:i A'); ?>
                        <?php if ($ev['location']): ?>
                        &nbsp;·&nbsp;<i class="fas fa-map-marker-alt" style="margin-right:3px"></i><?php echo e($ev['location']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($userRsvp): ?>
                <span class="ios-event-rsvp rsvp-<?php echo e($userRsvp); ?>">
                    <?php echo ucfirst(str_replace('_',' ',$userRsvp)); ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

        </div><!-- /ios-section-card -->
    </div>

</div><!-- /ios-events-layout -->

<?php if (isAdmin()): ?>
<!-- ===== ADMIN MOBILE PAGE MENU ===== -->
<div class="ios-menu-backdrop" id="pageMenuBackdrop" onclick="closePageMenu()"></div>
<div class="ios-page-menu" id="pageMenu">
    <div class="ios-sheet-handle"></div>
    <div class="ios-sheet-title">Events</div>
    <a href="<?php echo BASE_URL; ?>events/form.php" class="ios-sheet-link">
        <div class="ios-sheet-link-icon" style="background:rgba(10,132,255,.15);color:#0a84ff">
            <i class="fas fa-plus"></i>
        </div>
        New Event
    </a>
    <a href="<?php echo BASE_URL; ?>events/manage.php" class="ios-sheet-link">
        <div class="ios-sheet-link-icon" style="background:rgba(107,114,128,.15);color:#6b7280">
            <i class="fas fa-list"></i>
        </div>
        Manage Events
    </a>
</div>

<script>
(function () {
    var backdrop = document.getElementById('pageMenuBackdrop');
    var menu     = document.getElementById('pageMenu');

    window.openPageMenu = function () {
        backdrop.classList.add('active');
        requestAnimationFrame(function () { menu.classList.add('open'); });
    };
    window.closePageMenu = function () {
        menu.classList.remove('open');
        setTimeout(function () { backdrop.classList.remove('active'); }, 350);
    };

    // Swipe down to close
    var startY = 0;
    menu.addEventListener('touchstart', function (e) { startY = e.touches[0].clientY; }, { passive: true });
    menu.addEventListener('touchend', function (e) {
        if (e.changedTouches[0].clientY - startY > 60) closePageMenu();
    }, { passive: true });
})();
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
