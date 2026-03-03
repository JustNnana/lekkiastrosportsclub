<?php
/**
 * Tournaments — member-facing card grid (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Tournament.php';

requireLogin();

$pageTitle    = 'Tournaments';
$tourObj      = new Tournament();
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 9; // 3 per row × 3 rows
$status       = sanitize($_GET['status'] ?? '');

$total = $tourObj->countAll($status);
$paged = paginate($total, $perPage, $page);
$items = $tourObj->getAll($page, $perPage, $status);
$stats = $tourObj->getStats();

$formatLabels = [
    'league'         => 'League',
    'knockout'       => 'Knockout',
    'group_knockout' => 'Group + KO',
];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
:root {
    --ios-red:#FF453A; --ios-orange:#FF9F0A; --ios-green:#30D158;
    --ios-teal:#64D2FF; --ios-blue:#0A84FF; --ios-purple:#BF5AF2; --ios-gray:#8E8E93;
}

/* ── Page Header ───────────────────────────────────────────── */
.tours-page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 24px;
}
.tours-page-title { font-size: 24px; font-weight: 700; color: var(--text-primary); margin: 0 0 4px; }
.tours-page-sub   { font-size: 14px; color: var(--text-secondary); margin: 0; }
.tours-page-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.tours-header-menu-btn {
    display: none; width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    align-items: center; justify-content: center; cursor: pointer;
    transition: background 0.2s, transform 0.15s; flex-shrink: 0;
}
.tours-header-menu-btn:hover  { background: var(--border-color); }
.tours-header-menu-btn:active { transform: scale(0.95); }
.tours-header-menu-btn i { color: var(--text-primary); font-size: 16px; }

/* ── Filter Pills ──────────────────────────────────────────── */
.ios-filter-pills {
    display: flex; gap: 8px; margin-bottom: 24px;
    overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px;
}
.ios-filter-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px; border-radius: 20px; font-size: 13px; font-weight: 500;
    text-decoration: none; white-space: nowrap; transition: all 0.2s;
    border: 1px solid var(--border-color); background: var(--bg-secondary); color: var(--text-secondary);
}
.ios-filter-pill:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-filter-pill.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-filter-pill.pill-active.active    { background: var(--ios-green);  border-color: var(--ios-green);  }
.ios-filter-pill.pill-setup.active     { background: var(--ios-orange); border-color: var(--ios-orange); }
.ios-filter-pill.pill-completed.active { background: var(--ios-gray);   border-color: var(--ios-gray);   }
.ios-filter-pill .count { font-size: 11px; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.2); }
.ios-filter-pill:not(.active) .count   { background: var(--border-color); color: var(--text-muted); }

/* ── Tournament Card Grid ──────────────────────────────────── */
.ios-tours-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}
@media (max-width: 1100px) { .ios-tours-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 580px)  { .ios-tours-grid { grid-template-columns: 1fr; gap: 14px; } }

.ios-tour-card {
    background: var(--bg-primary); border: 1px solid var(--border-color);
    border-radius: 16px; overflow: hidden; display: flex; flex-direction: column;
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
}
.ios-tour-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    border-color: var(--primary);
}

/* Card top chips row */
.ios-tour-card-top {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px 0;
}
.ios-tour-status-chip {
    font-size: 11px; font-weight: 700; padding: 4px 10px;
    border-radius: 20px; text-transform: uppercase; letter-spacing: 0.4px;
}
.ios-tour-status-chip.active    { background: rgba(48,209,88,0.15);  color: var(--ios-green);  }
.ios-tour-status-chip.setup     { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-tour-status-chip.completed { background: rgba(142,142,147,0.15);color: var(--ios-gray);   }
.ios-tour-format-chip {
    font-size: 11px; font-weight: 500; padding: 4px 10px; border-radius: 20px;
    background: var(--bg-secondary); border: 1px solid var(--border-color); color: var(--text-muted);
    white-space: nowrap;
}

/* Card body */
.ios-tour-card-body { padding: 14px 16px 16px; flex: 1; display: flex; flex-direction: column; gap: 10px; }

.ios-tour-icon-wrap {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
}
.ios-tour-icon-wrap.active    { background: rgba(48,209,88,0.12);  color: var(--ios-green);  }
.ios-tour-icon-wrap.setup     { background: rgba(255,159,10,0.12); color: var(--ios-orange); }
.ios-tour-icon-wrap.completed { background: rgba(142,142,147,0.12);color: var(--ios-gray);   }

.ios-tour-name {
    font-size: 16px; font-weight: 700; color: var(--text-primary); margin: 0; line-height: 1.35;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.ios-tour-name a { color: inherit; text-decoration: none; }
.ios-tour-name a:hover { color: var(--primary); }

.ios-tour-desc {
    font-size: 13px; color: var(--text-secondary); margin: 0; line-height: 1.5;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}

/* Stat chips */
.ios-tour-stat-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: auto; padding-top: 2px; }
.ios-tour-stat-chip {
    display: inline-flex; align-items: center; gap: 5px; font-size: 12px; font-weight: 500;
    color: var(--text-secondary); background: var(--bg-secondary);
    border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 10px; white-space: nowrap;
}
.ios-tour-stat-chip i { font-size: 10px; }

/* Date */
.ios-tour-date { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--text-muted); }
.ios-tour-date i { font-size: 10px; }

/* CTA Button */
.ios-tour-btn {
    display: block; text-align: center; padding: 11px 16px;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    border-radius: 12px; font-size: 14px; font-weight: 600;
    color: var(--text-primary); text-decoration: none; transition: all 0.2s; margin-top: auto;
}
.ios-tour-btn:hover { background: var(--primary); border-color: var(--primary); color: white; }
.ios-tour-btn.active-btn { background: var(--ios-green); border-color: var(--ios-green); color: white; }
.ios-tour-btn.active-btn:hover { opacity: 0.88; }

/* Empty state */
.ios-tours-empty {
    text-align: center; padding: 64px 24px;
    background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px;
}
.ios-tours-empty-icon { font-size: 64px; opacity: 0.3; margin-bottom: 16px; }
.ios-tours-empty h3   { font-size: 20px; font-weight: 700; color: var(--text-primary); margin: 0 0 8px; }
.ios-tours-empty p    { font-size: 14px; color: var(--text-secondary); margin: 0; }

/* Pagination */
.ios-pagination-info { font-size: 12px; color: var(--text-muted); text-align: center; margin-bottom: 10px; }
.ios-pagination { display: flex; align-items: center; justify-content: center; gap: 6px; margin-bottom: 32px; }
.ios-page-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px;
    font-size: 14px; font-weight: 500; text-decoration: none;
    color: var(--text-secondary); background: var(--bg-secondary);
    border: 1px solid var(--border-color); transition: all 0.2s;
}
.ios-page-btn:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-page-btn.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }

/* Bottom sheet */
.ios-menu-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal { position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary); border-radius: 16px 16px 0 0; z-index: 9999; max-height: 80vh; transform: translateY(100%); transition: transform 0.3s cubic-bezier(0.32,0.72,0,1); overflow: hidden; display: flex; flex-direction: column; }
.ios-menu-modal.active { transform: translateY(0); }
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ios-menu-title  { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close  { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; transition: background 0.2s; }
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; padding-left: 4px; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background 0.15s; cursor: pointer; }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left   { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon   { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.primary{ background: rgba(34,197,94,0.15);  color: var(--ios-green);  }
.ios-menu-item-icon.orange { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,0.15); color: var(--ios-blue);   }
.ios-menu-item-icon.purple { background: rgba(191,90,242,0.15); color: var(--ios-purple); }
.ios-menu-item-content  { flex: 1; min-width: 0; }
.ios-menu-item-label    { font-size: 15px; font-weight: 500; }
.ios-menu-item-desc     { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron  { color: var(--text-muted); font-size: 12px; }
.ios-menu-stat-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 15px; color: var(--text-primary); }
.ios-menu-stat-value { font-size: 15px; font-weight: 600; color: var(--text-primary); }
.ios-menu-stat-value.success { color: var(--ios-green);  }
.ios-menu-stat-value.warning { color: var(--ios-orange); }

/* Responsive */
@media (max-width: 768px) {
    .tours-page-actions    { display: none !important; }
    .tours-header-menu-btn { display: flex; }
    .tours-page-header     { align-items: center; }
}
</style>

<!-- Page Header -->
<div class="tours-page-header">
    <div>
        <h1 class="tours-page-title"><i class="fas fa-trophy" style="color:var(--ios-orange);margin-right:10px"></i>Tournaments</h1>
        <p class="tours-page-sub">Club competitions, standings &amp; fixtures.</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="tours-page-actions">
        <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-cog me-1"></i>Manage
        </a>
        <a href="<?php echo BASE_URL; ?>tournaments/form.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>New Tournament
        </a>
    </div>
    <button class="tours-header-menu-btn" onclick="openToursMenu()" aria-label="More options">
        <i class="fas fa-ellipsis-v"></i>
    </button>
    <?php endif; ?>
</div>

<!-- Filter Pills -->
<div class="ios-filter-pills">
    <a href="<?php echo BASE_URL; ?>tournaments/index.php" class="ios-filter-pill <?php echo !$status ? 'active' : ''; ?>">
        All <span class="count"><?php echo number_format($stats['total']); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>tournaments/index.php?status=active" class="ios-filter-pill pill-active <?php echo $status === 'active' ? 'active' : ''; ?>">
        Active <span class="count"><?php echo number_format($stats['active']); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>tournaments/index.php?status=setup" class="ios-filter-pill pill-setup <?php echo $status === 'setup' ? 'active' : ''; ?>">
        Setup <span class="count"><?php echo number_format($stats['setup']); ?></span>
    </a>
    <a href="<?php echo BASE_URL; ?>tournaments/index.php?status=completed" class="ios-filter-pill pill-completed <?php echo $status === 'completed' ? 'active' : ''; ?>">
        Completed <span class="count"><?php echo number_format($stats['completed']); ?></span>
    </a>
</div>

<?php if (empty($items)): ?>
<!-- Empty State -->
<div class="ios-tours-empty">
    <div class="ios-tours-empty-icon">🏆</div>
    <h3>No <?php echo $status ? ucfirst($status) . ' t' : 'T'; ?>ournaments</h3>
    <p>
        <?php if ($status): ?>
            No <?php echo $status; ?> tournaments at the moment.
        <?php else: ?>
            No tournaments have been created yet.
        <?php endif; ?>
    </p>
    <?php if (isAdmin()): ?>
    <a href="<?php echo BASE_URL; ?>tournaments/form.php" class="btn btn-primary mt-3">
        <i class="fas fa-plus me-1"></i>Create First Tournament
    </a>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- Tournament Cards -->
<div class="ios-tours-grid">
    <?php foreach ($items as $t):
        $st          = $t['status'];
        $statusLabel = match($st) {
            'active'    => 'Active',
            'setup'     => 'Setup',
            default     => 'Done',
        };
        $icon = match($st) {
            'active'    => 'fa-trophy',
            'setup'     => 'fa-cog',
            default     => 'fa-flag-checkered',
        };
        $fmt = $formatLabels[$t['format']] ?? ucfirst(str_replace('_', ' ', $t['format']));
    ?>
    <div class="ios-tour-card">
        <div class="ios-tour-card-top">
            <span class="ios-tour-status-chip <?php echo $st; ?>"><?php echo $statusLabel; ?></span>
            <span class="ios-tour-format-chip"><?php echo e($fmt); ?></span>
        </div>
        <div class="ios-tour-card-body">
            <div class="ios-tour-icon-wrap <?php echo $st; ?>">
                <i class="fas <?php echo $icon; ?>"></i>
            </div>
            <h3 class="ios-tour-name">
                <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $t['id']; ?>">
                    <?php echo e($t['name']); ?>
                </a>
            </h3>
            <?php if ($t['description']): ?>
            <p class="ios-tour-desc"><?php echo e(mb_substr($t['description'], 0, 120)); ?></p>
            <?php endif; ?>
            <div class="ios-tour-stat-row">
                <span class="ios-tour-stat-chip">
                    <i class="fas fa-shield-alt"></i>
                    <?php echo number_format($t['team_count']); ?> team<?php echo $t['team_count'] != 1 ? 's' : ''; ?>
                </span>
                <span class="ios-tour-stat-chip">
                    <i class="fas fa-futbol"></i>
                    <?php echo number_format($t['fixture_count']); ?> fixture<?php echo $t['fixture_count'] != 1 ? 's' : ''; ?>
                </span>
            </div>
            <?php if ($t['start_date']): ?>
            <div class="ios-tour-date">
                <i class="fas fa-calendar-alt"></i>
                <?php echo formatDate($t['start_date'], 'd M Y'); ?>
            </div>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $t['id']; ?>"
               class="ios-tour-btn <?php echo $st === 'active' ? 'active-btn' : ''; ?>">
                <?php echo $st === 'active' ? 'View & Follow →' : 'View Tournament →'; ?>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($paged['total_pages'] > 1): ?>
<div class="ios-pagination-info">
    Showing <?php echo number_format(($page - 1) * $perPage + 1); ?>–<?php echo number_format(min($page * $perPage, $total)); ?>
    of <?php echo number_format($total); ?> tournaments
</div>
<div class="ios-pagination">
    <?php if ($paged['has_prev']): ?>
    <a href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status); ?>" class="ios-page-btn">
        <i class="fas fa-chevron-left"></i>
    </a>
    <?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($paged['total_pages'], $page + 2); $i++): ?>
    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status); ?>" class="ios-page-btn <?php echo $i === $page ? 'active' : ''; ?>">
        <?php echo $i; ?>
    </a>
    <?php endfor; ?>
    <?php if ($paged['has_next']): ?>
    <a href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status); ?>" class="ios-page-btn">
        <i class="fas fa-chevron-right"></i>
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

<?php if (isAdmin()): ?>
<!-- ===== MOBILE ADMIN MENU ===== -->
<div class="ios-menu-backdrop" id="toursMenuBackdrop" onclick="closeToursMenu()"></div>
<div class="ios-menu-modal" id="toursMenuSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Tournaments</h3>
        <button class="ios-menu-close" onclick="closeToursMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Admin Actions</div>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>tournaments/form.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon primary"><i class="fas fa-plus"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">New Tournament</span>
                            <span class="ios-menu-item-desc">Create a new competition</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-cog"></i></div>
                        <div class="ios-menu-item-content">
                            <span class="ios-menu-item-label">Manage Tournaments</span>
                            <span class="ios-menu-item-desc">Admin controls &amp; settings</span>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>
        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Overview</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Total</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['total']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Active</span>
                    <span class="ios-menu-stat-value success"><?php echo number_format($stats['active']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Setup</span>
                    <span class="ios-menu-stat-value warning"><?php echo number_format($stats['setup']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Completed</span>
                    <span class="ios-menu-stat-value"><?php echo number_format($stats['completed']); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function addSwipeClose(el, closeFn) {
        var startY = 0, curY = 0;
        el.addEventListener('touchstart', function(e){ startY = e.touches[0].clientY; }, { passive: true });
        el.addEventListener('touchmove', function(e){
            curY = e.touches[0].clientY;
            var diff = curY - startY;
            if (diff > 0) el.style.transform = 'translateY(' + diff + 'px)';
        }, { passive: true });
        el.addEventListener('touchend', function(){
            var diff = curY - startY;
            el.style.transform = '';
            if (diff > 100) closeFn();
            startY = curY = 0;
        });
    }

    var menuBackdrop = document.getElementById('toursMenuBackdrop');
    var menuSheet    = document.getElementById('toursMenuSheet');

    window.openToursMenu = function() {
        menuBackdrop.classList.add('active');
        menuSheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    window.closeToursMenu = function() {
        menuBackdrop.classList.remove('active');
        menuSheet.classList.remove('active');
        document.body.style.overflow = '';
    };
    addSwipeClose(menuSheet, closeToursMenu);
}());
</script>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
