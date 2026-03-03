<?php
/**
 * Tournament view — full detail (iOS-styled)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Tournament.php';

requireLogin();

$id      = (int)($_GET['id'] ?? 0);
$tourObj = new Tournament();
$tour    = $tourObj->getById($id);

if (!$tour) { flashError('Tournament not found.'); redirect('tournaments/index.php'); }

$groups     = $tourObj->getGroups($id);
$fixtures   = $tourObj->getFixtures($id);
$topScorers = $tourObj->getTournamentTopScorers($id, 10);
$allTeams   = $tourObj->getAllTeams($id);
$pageTitle  = e($tour['name']);

$rounds = [];
foreach ($fixtures as $f) {
    $round = $f['round'] ?: 'Unscheduled';
    $rounds[$round][] = $f;
}

$formatLabels = ['league' => 'League', 'knockout' => 'Knockout', 'group_knockout' => 'Group + KO'];
$backUrl      = BASE_URL . (isAdmin() ? 'tournaments/manage.php' : 'tournaments/index.php');
$firstTab     = !empty($groups) ? 'standings' : 'fixtures';

$st         = $tour['status'];
$statusLabel = match($st) { 'active' => 'Active', 'setup' => 'Setup', default => 'Done' };
$statusCls   = $st; // active | setup | completed
$tourIcon    = match($st) { 'active' => 'fa-trophy', 'setup' => 'fa-cog', default => 'fa-flag-checkered' };
$fmt         = $formatLabels[$tour['format']] ?? ucfirst(str_replace('_', ' ', $tour['format']));

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
:root {
    --ios-red:#FF453A; --ios-orange:#FF9F0A; --ios-green:#30D158;
    --ios-teal:#64D2FF; --ios-blue:#0A84FF; --ios-purple:#BF5AF2; --ios-gray:#8E8E93;
}

/* Content header — desktop only */
@media (max-width: 768px) { .content-header { display: none !important; } }

/* ── Tournament Detail Header ──────────────────────────────── */
.ios-detail-header {
    background: var(--bg-primary); border: 1px solid var(--border-color);
    border-radius: 16px; padding: 20px; margin-bottom: 20px;
}
.ios-detail-nav {
    display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
}
.ios-back-link {
    display: inline-flex; align-items: center; gap: 6px; font-size: 14px;
    font-weight: 500; color: var(--ios-blue); text-decoration: none;
}
.ios-back-link:hover { opacity: 0.75; }
.ios-back-link i { font-size: 12px; }
.ios-detail-admin-btns { display: flex; gap: 8px; }

.ios-detail-body { display: flex; align-items: flex-start; gap: 16px; }
.ios-detail-icon-wrap {
    width: 60px; height: 60px; border-radius: 16px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 26px;
}
.ios-detail-icon-wrap.active    { background: rgba(48,209,88,0.12);  color: var(--ios-green);  }
.ios-detail-icon-wrap.setup     { background: rgba(255,159,10,0.12); color: var(--ios-orange); }
.ios-detail-icon-wrap.completed { background: rgba(142,142,147,0.12);color: var(--ios-gray);   }
.ios-detail-info { flex: 1; min-width: 0; }
.ios-detail-title { font-size: 22px; font-weight: 700; color: var(--text-primary); margin: 0 0 8px; line-height: 1.25; }
.ios-detail-chips { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
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
}
.ios-detail-date { font-size: 12px; color: var(--text-muted); margin-top: 6px; }
.ios-detail-desc {
    margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border-color);
    font-size: 14px; color: var(--text-secondary); line-height: 1.6;
}

/* Mobile 3-dot */
.ios-detail-menu-btn {
    display: none; width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    align-items: center; justify-content: center; cursor: pointer;
    transition: background 0.2s, transform 0.15s; flex-shrink: 0;
}
.ios-detail-menu-btn:hover  { background: var(--border-color); }
.ios-detail-menu-btn:active { transform: scale(0.95); }
.ios-detail-menu-btn i { color: var(--text-primary); font-size: 16px; }

/* ── Stat Strip ────────────────────────────────────────────── */
.ios-stat-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
.ios-stat-tile {
    background: var(--bg-primary); border: 1px solid var(--border-color);
    border-radius: 12px; padding: 14px 12px; text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
}
.ios-stat-tile:hover { transform: translateY(-2px); box-shadow: var(--shadow); }
.ios-stat-tile-icon { font-size: 18px; margin-bottom: 6px; }
.ios-stat-tile-icon.blue   { color: var(--ios-blue);   }
.ios-stat-tile-icon.green  { color: var(--ios-green);  }
.ios-stat-tile-icon.orange { color: var(--ios-orange); }
.ios-stat-tile-icon.purple { color: var(--ios-purple); }
.ios-stat-tile-value { font-size: 20px; font-weight: 700; color: var(--text-primary); line-height: 1; }
.ios-stat-tile-label { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }

/* ── Tab Pills ─────────────────────────────────────────────── */
.ios-tab-bar {
    display: flex; gap: 8px; margin-bottom: 20px;
    overflow-x: auto; -webkit-overflow-scrolling: touch; padding-bottom: 4px;
}
.ios-tab-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: 20px; font-size: 13px; font-weight: 600;
    text-decoration: none; white-space: nowrap; cursor: pointer;
    border: 1px solid var(--border-color); background: var(--bg-secondary);
    color: var(--text-secondary); transition: all 0.2s;
}
.ios-tab-btn:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-tab-btn.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }

/* Tab panels */
.ios-tab-panel { display: none; }
.ios-tab-panel.active { display: block; }

/* ── iOS Section Card (shared) ─────────────────────────────── */
.ios-section-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; overflow: hidden; margin-bottom: 16px; }
.ios-section-header { display: flex; align-items: center; gap: 12px; padding: 14px 16px; background: var(--bg-subtle); border-bottom: 1px solid var(--border-color); }
.ios-section-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.ios-section-icon.blue   { background: rgba(10,132,255,0.15);  color: var(--ios-blue);   }
.ios-section-icon.green  { background: rgba(48,209,88,0.15);   color: var(--ios-green);  }
.ios-section-icon.orange { background: rgba(255,159,10,0.15);  color: var(--ios-orange); }
.ios-section-icon.purple { background: rgba(191,90,242,0.15);  color: var(--ios-purple); }
.ios-section-icon.gray   { background: rgba(142,142,147,0.15); color: var(--ios-gray);   }
.ios-section-title { flex: 1; }
.ios-section-title h5 { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 12px; color: var(--text-secondary); margin: 3px 0 0; }

/* ── Standings Table ───────────────────────────────────────── */
.ios-standings-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.ios-standings-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 520px; }
.ios-standings-table thead th {
    padding: 10px 8px; text-align: center; font-size: 11px; font-weight: 600;
    color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.4px;
    border-bottom: 1px solid var(--border-color); background: var(--bg-subtle);
}
.ios-standings-table thead th:nth-child(2) { text-align: left; padding-left: 16px; }
.ios-standings-table tbody tr { border-bottom: 1px solid var(--border-color); transition: background 0.15s; }
.ios-standings-table tbody tr:last-child { border-bottom: none; }
.ios-standings-table tbody tr:hover { background: rgba(255,255,255,0.03); }
.ios-standings-table tbody tr.leader { background: rgba(48,209,88,0.05); }
.ios-standings-table td { padding: 12px 8px; text-align: center; color: var(--text-primary); }
.ios-standings-table td:nth-child(2) { text-align: left; padding-left: 16px; font-weight: 600; }
.ios-standings-table td.pos    { color: var(--text-muted); font-weight: 500; }
.ios-standings-table td.pts    { font-weight: 700; font-size: 14px; }
.ios-standings-table td.pos-up { color: var(--ios-green); }
.ios-standings-table td.pos-dn { color: var(--ios-red); }
.ios-standings-medal { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 12px; font-weight: 700; }
.ios-standings-medal.gold   { background: rgba(255,159,10,0.2); color: #ca8a04; }
.ios-standings-medal.silver { background: rgba(142,142,147,0.2); color: var(--ios-gray); }
.ios-standings-medal.bronze { background: rgba(255,99,71,0.2); color: #c85a3a; }

/* ── Fixture Item ──────────────────────────────────────────── */
.ios-fixture-item {
    padding: 14px 16px; border-bottom: 1px solid var(--border-color);
    transition: background 0.15s;
}
.ios-fixture-item:last-child { border-bottom: none; }
.ios-fixture-item:hover { background: rgba(255,255,255,0.02); }

.ios-fixture-teams {
    display: flex; align-items: center; justify-content: space-between;
    gap: 8px; margin-bottom: 8px;
}
.ios-fixture-team {
    flex: 1; font-size: 14px; font-weight: 600; color: var(--text-primary);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ios-fixture-team.home { text-align: right; }
.ios-fixture-team.away { text-align: left; }

.ios-fixture-score-col { flex-shrink: 0; text-align: center; padding: 0 10px; }
.ios-fixture-score {
    font-size: 20px; font-weight: 800; color: var(--text-primary);
    letter-spacing: -0.5px; line-height: 1;
}
.ios-fixture-score span { display: inline-block; min-width: 22px; text-align: center; }
.ios-fixture-score .dash { font-size: 16px; font-weight: 400; color: var(--text-muted); margin: 0 2px; }
.ios-fixture-vs {
    font-size: 12px; font-weight: 600; color: var(--text-muted); padding: 5px 8px;
    background: var(--bg-secondary); border-radius: 8px; white-space: nowrap;
}
.ios-fixture-status-chip {
    display: block; font-size: 10px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.3px; margin-top: 4px; text-align: center; color: var(--text-muted);
}
.ios-fixture-status-chip.done { color: var(--ios-green); }

.ios-fixture-footer {
    display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap;
}
.ios-fixture-meta {
    display: flex; align-items: center; gap: 12px; font-size: 12px; color: var(--text-muted); flex-wrap: wrap;
}
.ios-fixture-meta i { font-size: 10px; }
.ios-fixture-actions { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
.ios-fixture-edit-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px; background: var(--bg-secondary);
    border: 1px solid var(--border-color); color: var(--text-secondary);
    font-size: 13px; text-decoration: none; transition: all 0.2s;
}
.ios-fixture-edit-btn:hover { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-fixture-del-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px; background: var(--bg-secondary);
    border: 1px solid var(--border-color); color: var(--text-secondary);
    font-size: 13px; cursor: pointer; transition: all 0.2s;
}
.ios-fixture-del-btn:hover { background: var(--ios-red); border-color: var(--ios-red); color: white; }

/* ── Top Scorers ───────────────────────────────────────────── */
.ios-scorer-item {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; border-bottom: 1px solid var(--border-color); transition: background 0.15s;
}
.ios-scorer-item:last-child { border-bottom: none; }
.ios-scorer-item:hover { background: rgba(255,255,255,0.02); }
.ios-scorer-rank {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; background: var(--bg-secondary);
    border: 1px solid var(--border-color); color: var(--text-muted);
}
.ios-scorer-rank.rank-1 { background: rgba(255,159,10,0.15); border-color: transparent; color: #ca8a04; }
.ios-scorer-rank.rank-2 { background: rgba(142,142,147,0.15);border-color: transparent; color: var(--ios-gray); }
.ios-scorer-rank.rank-3 { background: rgba(205,127,50,0.15); border-color: transparent; color: #a07033; }
.ios-scorer-content { flex: 1; min-width: 0; }
.ios-scorer-name { font-size: 14px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ios-scorer-sub  { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.ios-scorer-stats { display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; }
.ios-scorer-chip {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 12px; font-weight: 600; padding: 3px 8px; border-radius: 8px;
}
.ios-scorer-chip.goals   { background: rgba(10,132,255,0.12);  color: var(--ios-blue);   }
.ios-scorer-chip.assists { background: rgba(48,209,88,0.12);   color: var(--ios-green);  }
.ios-scorer-chip.yellow  { background: rgba(255,159,10,0.12);  color: var(--ios-orange); }
.ios-scorer-chip.red     { background: rgba(255,69,58,0.12);   color: var(--ios-red);    }

/* ── Teams ─────────────────────────────────────────────────── */
.ios-team-item {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 16px; border-bottom: 1px solid var(--border-color);
}
.ios-team-item:last-child { border-bottom: none; }
.ios-team-icon-wrap {
    width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 16px;
    background: rgba(10,132,255,0.12); color: var(--ios-blue);
}
.ios-team-content { flex: 1; min-width: 0; }
.ios-team-name { font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
.ios-team-members { display: flex; flex-direction: column; gap: 3px; }
.ios-team-member-row {
    display: flex; align-items: center; justify-content: space-between;
    font-size: 13px; color: var(--text-secondary); padding: 3px 0;
    border-bottom: 1px solid var(--border-color);
}
.ios-team-member-row:last-child { border-bottom: none; }
.ios-team-member-code { font-size: 11px; color: var(--text-muted); font-family: monospace; }
.ios-team-count-chip {
    font-size: 11px; font-weight: 600; color: var(--ios-blue);
    background: rgba(10,132,255,0.1); padding: 3px 8px; border-radius: 8px;
    white-space: nowrap; flex-shrink: 0; margin-top: 2px;
}
.ios-team-empty { font-size: 13px; color: var(--text-muted); font-style: italic; }

/* Empty states */
.ios-empty-state { text-align: center; padding: 40px 24px; }
.ios-empty-icon  { font-size: 48px; opacity: 0.3; margin-bottom: 12px; }
.ios-empty-title { font-size: 16px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; }
.ios-empty-sub   { font-size: 13px; color: var(--text-secondary); margin: 0; }

/* Backdrop & sheets */
.ios-menu-backdrop { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 9998; opacity: 0; visibility: hidden; transition: opacity 0.3s, visibility 0.3s; }
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal, .ios-confirm-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; background: var(--bg-primary);
    border-radius: 16px 16px 0 0; transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1); overflow: hidden;
}
.ios-menu-modal    { z-index: 9999; max-height: 80vh; display: flex; flex-direction: column; }
.ios-confirm-sheet { z-index: 10000; max-height: 55vh; }
.ios-menu-modal.active, .ios-confirm-sheet.active { transform: translateY(0); }
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ios-menu-title  { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close  { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; transition: background 0.2s; }
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background 0.15s; }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.primary{ background: rgba(34,197,94,0.15);  color: var(--ios-green);  }
.ios-menu-item-icon.orange { background: rgba(255,159,10,0.15); color: var(--ios-orange); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,0.15); color: var(--ios-blue);   }
.ios-menu-item-icon.red    { background: rgba(255,69,58,0.15);  color: var(--ios-red);    }
.ios-menu-item-content  { flex: 1; }
.ios-menu-item-label    { font-size: 15px; font-weight: 500; }
.ios-menu-item-desc     { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
.ios-menu-item-chevron  { color: var(--text-muted); font-size: 12px; }
.ios-confirm-body { padding: 20px 16px 8px; }
.ios-confirm-card { background: var(--bg-secondary); border-radius: 12px; padding: 14px 16px; margin-bottom: 16px; }
.ios-confirm-card-title { font-size: 14px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px; }
.ios-confirm-card-desc  { font-size: 13px; color: var(--text-secondary); margin: 0; }
.ios-form-btn { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; border: none; border-radius: 12px; font-size: 17px; font-weight: 600; text-align: center; cursor: pointer; font-family: inherit; color: white; transition: opacity 0.2s; }
.ios-form-btn:active { opacity: 0.8; }
.ios-form-btn.danger  { background: var(--ios-red); }
.ios-action-cancel { display: block; width: calc(100% - 16px); margin: 8px; padding: 14px; background: var(--bg-secondary); border: none; border-radius: 12px; font-size: 17px; font-weight: 600; color: var(--ios-blue); text-align: center; cursor: pointer; transition: background 0.15s; font-family: inherit; }
.ios-action-cancel:active { background: var(--border-color); }

/* Responsive */
@media (max-width: 768px) {
    .ios-detail-admin-btns   { display: none !important; }
    .ios-detail-menu-btn     { display: flex; }
    .ios-stat-strip { grid-template-columns: repeat(2, 1fr); }
    .ios-detail-title  { font-size: 18px; }
    .ios-fixture-team  { font-size: 13px; }
    .ios-fixture-score { font-size: 18px; }
    .ios-scorer-stats  { gap: 4px; }
    .ios-scorer-chip   { font-size: 11px; padding: 2px 6px; }
}
@media (max-width: 480px) {
    .ios-stat-strip { gap: 8px; }
    .ios-stat-tile  { padding: 12px 8px; }
    .ios-stat-tile-value { font-size: 18px; }
}
</style>

<!-- Content Header (desktop) -->
<div class="content-header">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <nav class="content-breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>dashboard/" class="breadcrumb-link">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo $backUrl; ?>" class="breadcrumb-link">Tournaments</a></li>
                    <li class="breadcrumb-item active"><?php echo e($tour['name']); ?></li>
                </ol>
            </nav>
            <h1 class="content-title"><?php echo e($tour['name']); ?></h1>
        </div>
        <?php if (isAdmin()): ?>
        <div class="content-actions">
            <?php if ($st === 'setup'): ?>
            <a href="<?php echo BASE_URL; ?>tournaments/setup.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-layer-group me-2"></i>Setup Groups
            </a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add Fixture
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tournament Detail Header -->
<div class="ios-detail-header">
    <div class="ios-detail-nav">
        <a href="<?php echo $backUrl; ?>" class="ios-back-link">
            <i class="fas fa-chevron-left"></i>Tournaments
        </a>
        <?php if (isAdmin()): ?>
        <div class="ios-detail-admin-btns">
            <?php if ($st === 'setup'): ?>
            <a href="<?php echo BASE_URL; ?>tournaments/setup.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-layer-group me-1"></i>Setup Groups
            </a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i>Add Fixture
            </a>
        </div>
        <button class="ios-detail-menu-btn" onclick="openDetailMenu()" aria-label="More options">
            <i class="fas fa-ellipsis-v"></i>
        </button>
        <?php endif; ?>
    </div>
    <div class="ios-detail-body">
        <div class="ios-detail-icon-wrap <?php echo $statusCls; ?>">
            <i class="fas <?php echo $tourIcon; ?>"></i>
        </div>
        <div class="ios-detail-info">
            <h1 class="ios-detail-title"><?php echo e($tour['name']); ?></h1>
            <div class="ios-detail-chips">
                <span class="ios-tour-status-chip <?php echo $statusCls; ?>"><?php echo $statusLabel; ?></span>
                <span class="ios-tour-format-chip"><?php echo e($fmt); ?></span>
            </div>
            <?php if ($tour['start_date']): ?>
            <div class="ios-detail-date">
                <i class="fas fa-calendar-alt" style="font-size:10px;margin-right:4px"></i>
                Started <?php echo formatDate($tour['start_date'], 'd M Y'); ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($tour['description']): ?>
    <div class="ios-detail-desc">
        <i class="fas fa-info-circle" style="color:var(--ios-blue);margin-right:6px;font-size:12px"></i><?php echo e($tour['description']); ?>
    </div>
    <?php endif; ?>
</div>

<!-- Stat Strip -->
<div class="ios-stat-strip">
    <div class="ios-stat-tile">
        <div class="ios-stat-tile-icon purple"><i class="fas fa-layer-group"></i></div>
        <div class="ios-stat-tile-value"><?php echo count($groups); ?></div>
        <div class="ios-stat-tile-label">Groups</div>
    </div>
    <div class="ios-stat-tile">
        <div class="ios-stat-tile-icon blue"><i class="fas fa-shield-alt"></i></div>
        <div class="ios-stat-tile-value"><?php echo count($allTeams); ?></div>
        <div class="ios-stat-tile-label">Teams</div>
    </div>
    <div class="ios-stat-tile">
        <div class="ios-stat-tile-icon green"><i class="fas fa-futbol"></i></div>
        <div class="ios-stat-tile-value"><?php echo count($fixtures); ?></div>
        <div class="ios-stat-tile-label">Fixtures</div>
    </div>
    <div class="ios-stat-tile">
        <div class="ios-stat-tile-icon orange"><i class="fas fa-check-circle"></i></div>
        <div class="ios-stat-tile-value"><?php echo $tour['completed_fixtures']; ?><span style="font-size:13px;font-weight:400;color:var(--text-muted)"> /<?php echo $tour['total_fixtures']; ?></span></div>
        <div class="ios-stat-tile-label">Played</div>
    </div>
</div>

<!-- Tab Bar -->
<div class="ios-tab-bar">
    <?php if (!empty($groups)): ?>
    <button class="ios-tab-btn <?php echo $firstTab === 'standings' ? 'active' : ''; ?>" data-tab="standings" onclick="switchTab('standings')">
        <i class="fas fa-list-ol"></i> Standings
    </button>
    <?php endif; ?>
    <button class="ios-tab-btn <?php echo $firstTab === 'fixtures' ? 'active' : ''; ?>" data-tab="fixtures" onclick="switchTab('fixtures')">
        <i class="fas fa-futbol"></i> Fixtures
    </button>
    <?php if (!empty($topScorers)): ?>
    <button class="ios-tab-btn" data-tab="scorers" onclick="switchTab('scorers')">
        <i class="fas fa-star"></i> Top Scorers
    </button>
    <?php endif; ?>
    <?php if (!empty($allTeams)): ?>
    <button class="ios-tab-btn" data-tab="teams" onclick="switchTab('teams')">
        <i class="fas fa-shield-alt"></i> Teams
    </button>
    <?php endif; ?>
</div>

<!-- ═══ STANDINGS ══════════════════════════════════════════════ -->
<?php if (!empty($groups)): ?>
<div class="ios-tab-panel <?php echo $firstTab === 'standings' ? 'active' : ''; ?>" id="standings">
    <?php foreach ($groups as $group):
        $standings = $tourObj->getGroupStandings($group['id']);
        $iconCls   = match($group['team_count']) {
            default => 'blue'
        };
    ?>
    <div class="ios-section-card">
        <div class="ios-section-header">
            <div class="ios-section-icon blue"><i class="fas fa-layer-group"></i></div>
            <div class="ios-section-title">
                <h5><?php echo e($group['group_name']); ?></h5>
                <p><?php echo $group['team_count']; ?> team<?php echo $group['team_count'] != 1 ? 's' : ''; ?></p>
            </div>
        </div>
        <?php if (empty($standings)): ?>
        <div class="ios-empty-state">
            <div class="ios-empty-icon"><i class="fas fa-shield-alt"></i></div>
            <p class="ios-empty-title">No teams yet</p>
            <p class="ios-empty-sub">Add teams via Setup Groups &amp; Teams.</p>
        </div>
        <?php else: ?>
        <div class="ios-standings-wrap">
            <table class="ios-standings-table">
                <thead>
                    <tr>
                        <th>#</th><th>Team</th>
                        <th title="Played">P</th>
                        <th title="Won">W</th>
                        <th title="Draw">D</th>
                        <th title="Lost">L</th>
                        <th title="Goals For">GF</th>
                        <th title="Goals Against">GA</th>
                        <th title="Goal Difference">GD</th>
                        <th title="Points">Pts</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($standings as $pos => $s): ?>
                    <tr <?php echo $pos === 0 ? 'class="leader"' : ''; ?>>
                        <td class="pos">
                            <?php if ($pos === 0): ?>
                            <span class="ios-standings-medal gold">1</span>
                            <?php elseif ($pos === 1): ?>
                            <span class="ios-standings-medal silver">2</span>
                            <?php elseif ($pos === 2): ?>
                            <span class="ios-standings-medal bronze">3</span>
                            <?php else: ?>
                            <?php echo $pos + 1; ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($s['team_name']); ?></td>
                        <td><?php echo $s['P']; ?></td>
                        <td style="color:var(--ios-green);font-weight:600"><?php echo $s['W']; ?></td>
                        <td style="color:var(--text-muted)"><?php echo $s['D']; ?></td>
                        <td style="color:var(--ios-red)"><?php echo $s['L']; ?></td>
                        <td><?php echo $s['GF']; ?></td>
                        <td><?php echo $s['GA']; ?></td>
                        <td class="<?php echo $s['GD'] > 0 ? 'pos-up' : ($s['GD'] < 0 ? 'pos-dn' : ''); ?>">
                            <?php echo ($s['GD'] > 0 ? '+' : '') . $s['GD']; ?>
                        </td>
                        <td class="pts"><?php echo $s['Pts']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ═══ FIXTURES ═══════════════════════════════════════════════ -->
<div class="ios-tab-panel <?php echo $firstTab === 'fixtures' ? 'active' : ''; ?>" id="fixtures">
    <?php if (empty($fixtures)): ?>
    <div class="ios-section-card">
        <div class="ios-empty-state">
            <div class="ios-empty-icon"><i class="fas fa-futbol"></i></div>
            <p class="ios-empty-title">No fixtures yet</p>
            <p class="ios-empty-sub">
                <?php if (isAdmin()): ?>
                <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="btn btn-primary btn-sm mt-2">
                    <i class="fas fa-plus me-1"></i>Add First Fixture
                </a>
                <?php else: ?>
                Fixtures will appear here once scheduled.
                <?php endif; ?>
            </p>
        </div>
    </div>
    <?php else: ?>
    <?php foreach ($rounds as $roundName => $roundFixtures): ?>
    <div class="ios-section-card">
        <div class="ios-section-header">
            <div class="ios-section-icon orange"><i class="fas fa-flag"></i></div>
            <div class="ios-section-title">
                <h5><?php echo e($roundName); ?></h5>
                <p><?php echo count($roundFixtures); ?> fixture<?php echo count($roundFixtures) != 1 ? 's' : ''; ?></p>
            </div>
        </div>
        <?php foreach ($roundFixtures as $f):
            $isDone = $f['status'] === 'completed' && $f['home_score'] !== null;
        ?>
        <div class="ios-fixture-item">
            <div class="ios-fixture-teams">
                <span class="ios-fixture-team home"><?php echo e($f['home_team']); ?></span>
                <div class="ios-fixture-score-col">
                    <?php if ($isDone): ?>
                    <div class="ios-fixture-score">
                        <span><?php echo $f['home_score']; ?></span>
                        <span class="dash"> – </span>
                        <span><?php echo $f['away_score']; ?></span>
                    </div>
                    <span class="ios-fixture-status-chip done">Full Time</span>
                    <?php else: ?>
                    <div class="ios-fixture-vs">vs</div>
                    <?php if ($f['match_date'] && strtotime($f['match_date']) < time()): ?>
                    <span class="ios-fixture-status-chip" style="color:var(--ios-orange)">Pending</span>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <span class="ios-fixture-team away"><?php echo e($f['away_team']); ?></span>
            </div>
            <div class="ios-fixture-footer">
                <div class="ios-fixture-meta">
                    <?php if ($f['match_date']): ?>
                    <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($f['match_date'], 'd M Y, g:i A'); ?></span>
                    <?php endif; ?>
                    <?php if ($f['location']): ?>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo e($f['location']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (isAdmin()): ?>
                <div class="ios-fixture-actions">
                    <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?fixture_id=<?php echo $f['id']; ?>&tournament_id=<?php echo $id; ?>"
                       class="ios-fixture-edit-btn" title="Edit fixture">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button class="ios-fixture-del-btn" title="Delete fixture"
                            onclick="openFixtureConfirm(<?php echo $f['id']; ?>, '<?php echo e($f['home_team']); ?>', '<?php echo e($f['away_team']); ?>')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ═══ TOP SCORERS ════════════════════════════════════════════ -->
<?php if (!empty($topScorers)): ?>
<div class="ios-tab-panel" id="scorers">
    <div class="ios-section-card">
        <div class="ios-section-header">
            <div class="ios-section-icon orange"><i class="fas fa-star"></i></div>
            <div class="ios-section-title">
                <h5>Top Scorers</h5>
                <p><?php echo count($topScorers); ?> players</p>
            </div>
        </div>
        <?php foreach ($topScorers as $pos => $s): ?>
        <div class="ios-scorer-item">
            <div class="ios-scorer-rank rank-<?php echo $pos + 1 <= 3 ? $pos + 1 : ''; ?>">
                <?php echo $pos === 0 ? '🥇' : ($pos === 1 ? '🥈' : ($pos === 2 ? '🥉' : $pos + 1)); ?>
            </div>
            <div class="ios-scorer-content">
                <div class="ios-scorer-name"><?php echo e($s['player_name']); ?></div>
                <div class="ios-scorer-sub"><?php echo e($s['team_name']); ?> · <span style="font-family:monospace"><?php echo e($s['member_code']); ?></span></div>
            </div>
            <div class="ios-scorer-stats">
                <span class="ios-scorer-chip goals">⚽ <?php echo $s['total_goals']; ?></span>
                <?php if ($s['total_assists'] > 0): ?>
                <span class="ios-scorer-chip assists">🅰 <?php echo $s['total_assists']; ?></span>
                <?php endif; ?>
                <?php if ($s['yellow_cards'] > 0): ?>
                <span class="ios-scorer-chip yellow">🟨 <?php echo $s['yellow_cards']; ?></span>
                <?php endif; ?>
                <?php if ($s['red_cards'] > 0): ?>
                <span class="ios-scorer-chip red">🟥 <?php echo $s['red_cards']; ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ═══ TEAMS ══════════════════════════════════════════════════ -->
<?php if (!empty($allTeams)): ?>
<div class="ios-tab-panel" id="teams">
    <?php
    $teamsByGroup = [];
    foreach ($allTeams as $t) { $teamsByGroup[$t['group_name']][] = $t; }
    foreach ($teamsByGroup as $gName => $gTeams):
    ?>
    <div class="ios-section-card">
        <div class="ios-section-header">
            <div class="ios-section-icon purple"><i class="fas fa-layer-group"></i></div>
            <div class="ios-section-title">
                <h5><?php echo e($gName); ?></h5>
                <p><?php echo count($gTeams); ?> team<?php echo count($gTeams) != 1 ? 's' : ''; ?></p>
            </div>
        </div>
        <?php foreach ($gTeams as $t):
            $members = $tourObj->getTeamMembers($t['id']);
        ?>
        <div class="ios-team-item">
            <div class="ios-team-icon-wrap"><i class="fas fa-shield-alt"></i></div>
            <div class="ios-team-content">
                <div class="ios-team-name"><?php echo e($t['team_name']); ?></div>
                <?php if (empty($members)): ?>
                <p class="ios-team-empty">No members assigned yet.</p>
                <?php else: ?>
                <div class="ios-team-members">
                    <?php foreach ($members as $m): ?>
                    <div class="ios-team-member-row">
                        <span><?php echo e($m['full_name']); ?></span>
                        <span class="ios-team-member-code"><?php echo e($m['member_code']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <span class="ios-tour-stat-chip ios-team-count-chip"><?php echo count($members); ?> member<?php echo count($members) != 1 ? 's' : ''; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (isAdmin()): ?>
<!-- ===== MOBILE ADMIN MENU ===== -->
<div class="ios-menu-backdrop" id="detailMenuBackdrop" onclick="closeDetailMenu()"></div>
<div class="ios-menu-modal" id="detailMenuSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title"><?php echo e(mb_substr($tour['name'], 0, 30)); ?></h3>
        <button class="ios-menu-close" onclick="closeDetailMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">
        <div class="ios-menu-card">
            <?php if ($st === 'setup'): ?>
            <a href="<?php echo BASE_URL; ?>tournaments/setup.php?id=<?php echo $id; ?>" class="ios-menu-item">
                <div class="ios-menu-item-left">
                    <div class="ios-menu-item-icon orange"><i class="fas fa-layer-group"></i></div>
                    <div class="ios-menu-item-content">
                        <span class="ios-menu-item-label">Setup Groups &amp; Teams</span>
                        <span class="ios-menu-item-desc">Organise teams and groups</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
            </a>
            <?php endif; ?>
            <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="ios-menu-item">
                <div class="ios-menu-item-left">
                    <div class="ios-menu-item-icon primary"><i class="fas fa-plus"></i></div>
                    <div class="ios-menu-item-content">
                        <span class="ios-menu-item-label">Add Fixture</span>
                        <span class="ios-menu-item-desc">Schedule a new match</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
            </a>
            <a href="<?php echo BASE_URL; ?>tournaments/form.php?id=<?php echo $id; ?>" class="ios-menu-item">
                <div class="ios-menu-item-left">
                    <div class="ios-menu-item-icon blue"><i class="fas fa-edit"></i></div>
                    <div class="ios-menu-item-content">
                        <span class="ios-menu-item-label">Edit Tournament</span>
                        <span class="ios-menu-item-desc">Change name, format, dates</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
            </a>
            <a href="<?php echo $backUrl; ?>" class="ios-menu-item">
                <div class="ios-menu-item-left">
                    <div class="ios-menu-item-icon blue"><i class="fas fa-list"></i></div>
                    <div class="ios-menu-item-content">
                        <span class="ios-menu-item-label">All Tournaments</span>
                        <span class="ios-menu-item-desc">Back to the list</span>
                    </div>
                </div>
                <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
            </a>
        </div>
    </div>
</div>

<!-- ===== FIXTURE DELETE CONFIRM ===== -->
<div class="ios-menu-backdrop" id="fixtureConfirmBackdrop" onclick="closeFixtureConfirm()"></div>
<div class="ios-confirm-sheet" id="fixtureConfirmSheet">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">Delete Fixture</h3>
        <button class="ios-menu-close" onclick="closeFixtureConfirm()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-confirm-body">
        <div class="ios-confirm-card">
            <p class="ios-confirm-card-title" id="fixtureConfirmLabel">Home vs Away</p>
            <p class="ios-confirm-card-desc">This will permanently remove the fixture and all associated player stats.</p>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" id="fixtureConfirmForm">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action"        value="delete_fixture">
            <input type="hidden" name="fixture_id"    id="fixtureConfirmId">
            <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
            <button type="submit" class="ios-form-btn danger">Delete Fixture</button>
        </form>
    </div>
    <button class="ios-action-cancel" onclick="closeFixtureConfirm()">Cancel</button>
</div>
<?php endif; ?>

<script>
(function () {
    // ─── Tab switching ──────────────────────────────────────────
    window.switchTab = function(tabId) {
        document.querySelectorAll('.ios-tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.ios-tab-panel').forEach(function(p) { p.classList.remove('active'); });
        var btn = document.querySelector('[data-tab="' + tabId + '"]');
        var panel = document.getElementById(tabId);
        if (btn)   btn.classList.add('active');
        if (panel) panel.classList.add('active');
    };

    // ─── Swipe-to-close ─────────────────────────────────────────
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

    <?php if (isAdmin()): ?>
    // ─── Admin detail menu ───────────────────────────────────────
    var detailMenuBackdrop = document.getElementById('detailMenuBackdrop');
    var detailMenuSheet    = document.getElementById('detailMenuSheet');

    window.openDetailMenu = function() {
        detailMenuBackdrop.classList.add('active');
        detailMenuSheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    window.closeDetailMenu = function() {
        detailMenuBackdrop.classList.remove('active');
        detailMenuSheet.classList.remove('active');
        document.body.style.overflow = '';
    };
    addSwipeClose(detailMenuSheet, closeDetailMenu);

    // ─── Fixture delete confirm ──────────────────────────────────
    var fixConfirmBackdrop = document.getElementById('fixtureConfirmBackdrop');
    var fixConfirmSheet    = document.getElementById('fixtureConfirmSheet');

    window.openFixtureConfirm = function(fixtureId, home, away) {
        document.getElementById('fixtureConfirmId').value    = fixtureId;
        document.getElementById('fixtureConfirmLabel').textContent = home + ' vs ' + away;
        fixConfirmBackdrop.classList.add('active');
        fixConfirmSheet.classList.add('active');
        document.body.style.overflow = 'hidden';
    };
    window.closeFixtureConfirm = function() {
        fixConfirmBackdrop.classList.remove('active');
        fixConfirmSheet.classList.remove('active');
        document.body.style.overflow = '';
    };
    addSwipeClose(fixConfirmSheet, closeFixtureConfirm);
    <?php endif; ?>

}());
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
