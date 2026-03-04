<?php
/**
 * Tournament Setup — groups, teams, and member assignments
 * Admin only. Tournament must be in 'setup' status.
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Tournament.php';

requireAdmin();

$id      = (int)($_GET['id'] ?? 0);
$tourObj = new Tournament();
$tour    = $tourObj->getById($id);

if (!$tour) { flashError('Tournament not found.'); redirect('tournaments/manage.php'); }

$groups  = $tourObj->getGroups($id);
$db      = Database::getInstance();

$allMembers = $db->fetchAll(
    "SELECT m.id, m.member_id AS member_code, u.full_name
     FROM members m JOIN users u ON u.id = m.user_id
     WHERE m.status='active' ORDER BY u.full_name",
    []
);

$activeTeamId = (int)($_GET['team'] ?? 0);
$isSetup      = $tour['status'] === 'setup';
$pageTitle    = 'Setup: ' . $tour['name'];

$fmtLabels = ['league' => 'League', 'knockout' => 'Knockout', 'group_knockout' => 'Groups + KO'];
$fmt       = $fmtLabels[$tour['format']] ?? ucfirst($tour['format']);
$st        = $tour['status'];
$stLabel   = match($st) { 'setup' => 'Setup', 'active' => 'Active', default => 'Completed' };

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
:root {
    --ios-red:    #FF453A;
    --ios-orange: #FF9F0A;
    --ios-green:  #30D158;
    --ios-blue:   #0A84FF;
    --ios-purple: #BF5AF2;
    --ios-teal:   #64D2FF;
}

/* ── Layout ── */
.ios-view-layout {
    display: grid;
    grid-template-columns: 1fr 290px;
    gap: var(--spacing-5);
}
@media (max-width: 992px) { .ios-view-layout { grid-template-columns: 1fr; } }

/* ── iOS Section Card ── */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    margin-bottom: var(--spacing-4);
}
.ios-section-header {
    display: flex; align-items: center; gap: var(--spacing-3);
    padding: var(--spacing-4);
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-section-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.ios-section-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-section-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-section-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-section-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-section-icon.red    { background: rgba(255,69,58,.15);  color: var(--ios-red); }

.ios-section-title { flex: 1; min-width: 0; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

/* ── 3-dot button ── */
.ios-options-btn {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background .2s, transform .15s; flex-shrink: 0;
}
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* ── Status badge ── */
.ios-status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 8px;
    font-size: 11px; font-weight: 600;
}
.ios-status-badge.setup     { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-status-badge.active    { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-status-badge.completed { background: rgba(142,142,147,.15);color: var(--text-muted); }

/* ── Add group inline form ── */
.ios-inline-form {
    display: flex; gap: 10px; align-items: flex-end;
    padding: var(--spacing-4) var(--spacing-5);
    flex-wrap: wrap;
}
.ios-inline-form-field { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 160px; }
.ios-inline-form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .3px; }

/* ── Groups grid ── */
.ios-groups-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-4);
}
@media (max-width: 760px) { .ios-groups-grid { grid-template-columns: 1fr; } }

/* ── Team block ── */
.ios-team-block { border-bottom: 1px solid var(--border-color); }
.ios-team-block:last-child { border-bottom: none; }

.ios-team-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px; gap: 10px;
}
.ios-team-name {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 600; color: var(--text-primary);
    flex: 1; min-width: 0;
}
.ios-team-name i { color: var(--text-muted); font-size: 13px; flex-shrink: 0; }
.ios-team-count {
    font-size: 11px; font-weight: 600; color: var(--ios-blue);
    background: rgba(10,132,255,.1); padding: 2px 7px; border-radius: 8px;
    flex-shrink: 0;
}
.ios-team-actions { display: flex; gap: 6px; flex-shrink: 0; }
.ios-icon-btn {
    width: 30px; height: 30px; border-radius: 8px;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; cursor: pointer; text-decoration: none;
    color: var(--text-secondary); transition: all .2s;
}
.ios-icon-btn:hover        { background: var(--border-color); color: var(--text-primary); }
.ios-icon-btn.manage:hover { background: var(--ios-blue); border-color: var(--ios-blue); color: #fff; }
.ios-icon-btn.del:hover    { background: var(--ios-red);  border-color: var(--ios-red);  color: #fff; }

/* ── Member rows ── */
.ios-member-rows { padding: 0 16px; background: var(--bg-subtle); }
.ios-member-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 0; border-bottom: 1px solid var(--border-color);
    gap: 8px;
}
.ios-member-row:last-child { border-bottom: none; }
.ios-member-name  { font-size: 13px; font-weight: 500; color: var(--text-primary); flex: 1; min-width: 0; }
.ios-member-code  { font-size: 11px; color: var(--text-muted); font-family: monospace; }
.ios-member-remove {
    width: 26px; height: 26px; border-radius: 6px; border: 1px solid var(--border-color);
    background: var(--bg-secondary); display: flex; align-items: center; justify-content: center;
    font-size: 11px; cursor: pointer; color: var(--text-muted); transition: all .2s;
    flex-shrink: 0;
}
.ios-member-remove:hover { background: var(--ios-red); border-color: var(--ios-red); color: #fff; }

/* ── Add member form ── */
.ios-add-member-form {
    display: flex; gap: 8px; padding: 12px 16px;
    background: var(--bg-subtle); border-top: 1px solid var(--border-color);
    align-items: center;
}
.ios-add-team-form {
    display: flex; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--border-color);
    align-items: center; background: var(--bg-secondary);
}

/* ── Empty state ── */
.ios-empty {
    text-align: center; padding: 40px 20px;
}
.ios-empty i   { font-size: 40px; opacity: .2; display: block; margin-bottom: 12px; }
.ios-empty p   { font-size: 14px; color: var(--text-muted); margin: 0; }

/* ── Sidebar ── */
.ios-sidebar { display: flex; flex-direction: column; }
.ios-sidebar-sticky { position: sticky; top: var(--spacing-4); }
.ios-detail-rows { padding: 0; }
.ios-detail-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px; border-bottom: 1px solid var(--border-color);
    font-size: 14px;
}
.ios-detail-row:last-child { border-bottom: none; }
.ios-detail-row-label { color: var(--text-muted); font-size: 13px; }
.ios-detail-row-value { color: var(--text-primary); font-weight: 500; text-align: right; }
.ios-admin-actions { padding: var(--spacing-4); display: flex; flex-direction: column; gap: var(--spacing-3); }

/* ── iOS bottom-sheet ── */
.ios-menu-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.4); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    z-index: 9998; opacity: 0; visibility: hidden;
    transition: opacity .3s ease, visibility .3s ease;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-primary); border-radius: 16px 16px 0 0;
    z-index: 9999; transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 85vh; overflow: hidden; display: flex; flex-direction: column;
}
.ios-menu-modal.active { transform: translateY(0); }
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ios-menu-title  { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close  { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; }
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-left: 4px; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background .15s; cursor: pointer; width: 100%; background: transparent; border-left: none; border-right: none; border-top: none; font-family: inherit; font-size: inherit; text-align: left; }
button.ios-menu-item { border: none; border-bottom: 1px solid var(--border-color); }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-menu-item-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-menu-item-icon.red    { background: rgba(255,69,58,.15);  color: var(--ios-red); }
.ios-menu-item-label   { font-size: 15px; font-weight: 500; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .ios-sidebar { display: none; }
    .ios-view-layout { grid-template-columns: 1fr; }
    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 14px; }
    .ios-inline-form { padding: var(--spacing-3) var(--spacing-4); }
}
</style>

<!-- ===== DESKTOP HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-start">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $id; ?>" class="breadcrumb-link"><?php echo e($tour['name']); ?></a>
                </li>
                <li class="breadcrumb-item active">Setup</li>
            </ol>
        </nav>
        <h1 class="content-title">Setup Groups &amp; Teams</h1>
        <p class="content-subtitle">
            <span class="ios-status-badge <?php echo $st; ?>">
                <i class="fas fa-circle" style="font-size:7px"></i> <?php echo $stLabel; ?>
            </span>
            &nbsp;· <?php echo $fmt; ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-shrink-0">
        <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="btn btn-secondary">
            <i class="fas fa-futbol me-2"></i>Add Fixture
        </a>
        <?php if ($isSetup): ?>
        <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-inline">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="activate">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-play me-2"></i>Activate Tournament
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- ===== MOBILE HEADER ===== -->
<div class="ios-section-header d-md-none mb-3" style="border-radius:14px;border:1px solid var(--border-color)">
    <div class="ios-section-icon <?php echo $isSetup ? 'orange' : 'green'; ?>">
        <i class="fas fa-<?php echo $isSetup ? 'cog' : 'trophy'; ?>"></i>
    </div>
    <div class="ios-section-title">
        <h5><?php echo e($tour['name']); ?></h5>
        <p><?php echo $stLabel; ?> · <?php echo $fmt; ?> · <?php echo count($groups); ?> group<?php echo count($groups) !== 1 ? 's' : ''; ?></p>
    </div>
    <button class="ios-options-btn" onclick="openIosMenu()"><i class="fas fa-ellipsis-v"></i></button>
</div>

<div class="ios-view-layout">

    <!-- ===== LEFT: MAIN CONTENT ===== -->
    <div>

        <!-- Add Group card -->
        <?php if ($isSetup): ?>
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-icon blue">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="ios-section-title">
                    <h5>Add Group</h5>
                    <p>Create a new group for this tournament</p>
                </div>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add_group">
                <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                <div class="ios-inline-form">
                    <div class="ios-inline-form-field">
                        <label class="ios-inline-form-label">Group Name</label>
                        <input type="text" name="group_name" class="form-control" style="border-radius:10px"
                               placeholder="e.g. Group A" required maxlength="50">
                    </div>
                    <button type="submit" class="btn btn-primary" style="border-radius:10px;white-space:nowrap">
                        <i class="fas fa-plus me-1"></i> Add Group
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Groups -->
        <?php if (empty($groups)): ?>
        <div class="ios-section-card">
            <div class="ios-empty">
                <i class="fas fa-layer-group"></i>
                <p>No groups yet. Add a group above to get started.</p>
            </div>
        </div>
        <?php else: ?>

        <div class="ios-groups-grid">
        <?php foreach ($groups as $group):
            $teams = $tourObj->getTeamsByGroup($group['id']);
            $teamCount = count($teams);
        ?>
        <div class="ios-section-card">

            <!-- Group header -->
            <div class="ios-section-header">
                <div class="ios-section-icon blue">
                    <i class="fas fa-layer-group"></i>
                </div>
                <div class="ios-section-title">
                    <h5><?php echo e($group['group_name']); ?></h5>
                    <p><?php echo $teamCount; ?> team<?php echo $teamCount !== 1 ? 's' : ''; ?></p>
                </div>
                <?php if ($isSetup): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-inline">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                    <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                    <button type="submit" class="ios-icon-btn del"
                            onclick="return confirm('Delete this group and all its teams?')"
                            title="Delete group">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Teams -->
            <?php if (empty($teams)): ?>
            <div style="padding:20px 16px;text-align:center;color:var(--text-muted);font-size:13px">
                No teams yet.
            </div>
            <?php else: ?>
            <?php foreach ($teams as $team):
                $teamMembers = $tourObj->getTeamMembers($team['id']);
                $isActive    = ($activeTeamId === (int)$team['id']);
            ?>
            <div class="ios-team-block">

                <!-- Team header row -->
                <div class="ios-team-header">
                    <div class="ios-team-name">
                        <i class="fas fa-users"></i>
                        <?php echo e($team['team_name']); ?>
                        <span class="ios-team-count"><?php echo count($teamMembers); ?></span>
                    </div>
                    <div class="ios-team-actions">
                        <a href="?id=<?php echo $id; ?>&team=<?php echo $isActive ? 0 : $team['id']; ?>"
                           class="ios-icon-btn manage" title="<?php echo $isActive ? 'Collapse' : 'Manage members'; ?>">
                            <i class="fas fa-<?php echo $isActive ? 'chevron-up' : 'user-plus'; ?>"></i>
                        </a>
                        <?php if ($isSetup): ?>
                        <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-inline">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="delete_team">
                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                            <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                            <button type="submit" class="ios-icon-btn del"
                                    onclick="return confirm('Delete this team?')" title="Delete team">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Member list -->
                <?php if (!empty($teamMembers)): ?>
                <div class="ios-member-rows">
                    <?php foreach ($teamMembers as $tm): ?>
                    <div class="ios-member-row">
                        <span class="ios-member-name"><?php echo e($tm['full_name']); ?></span>
                        <span class="ios-member-code"><?php echo e($tm['member_code']); ?></span>
                        <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-inline">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="action" value="remove_member">
                            <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                            <input type="hidden" name="member_id" value="<?php echo $tm['id']; ?>">
                            <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                            <button type="submit" class="ios-member-remove" title="Remove member">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Add member dropdown (visible when team is active) -->
                <?php if ($isActive): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="ios-add-member-form">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="add_member">
                    <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                    <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                    <select name="member_id" class="form-control form-control-sm" style="border-radius:8px" required>
                        <option value="">— Select member —</option>
                        <?php
                        $assignedIds = array_column($teamMembers, 'id');
                        foreach ($allMembers as $am):
                            if (in_array($am['id'], $assignedIds)) continue;
                        ?>
                        <option value="<?php echo $am['id']; ?>">
                            <?php echo e($am['full_name']); ?> (<?php echo e($am['member_code']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-success btn-sm flex-shrink-0" style="border-radius:8px">
                        <i class="fas fa-plus me-1"></i>Add
                    </button>
                </form>
                <?php endif; ?>

            </div><!-- /ios-team-block -->
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Add Team form -->
            <?php if ($isSetup): ?>
            <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="ios-add-team-form">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="add_team">
                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                <input type="text" name="team_name" class="form-control form-control-sm"
                       style="border-radius:8px" placeholder="New team name…" required maxlength="100">
                <button type="submit" class="btn btn-primary btn-sm flex-shrink-0" style="border-radius:8px">
                    <i class="fas fa-plus me-1"></i>Add Team
                </button>
            </form>
            <?php endif; ?>

        </div><!-- /ios-section-card -->
        <?php endforeach; ?>
        </div><!-- /ios-groups-grid -->

        <?php endif; ?>

    </div><!-- /left -->

    <!-- ===== RIGHT: SIDEBAR ===== -->
    <div class="ios-sidebar">
        <div class="ios-sidebar-sticky">

            <!-- Tournament Info card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue"><i class="fas fa-info-circle"></i></div>
                    <div class="ios-section-title"><h5>Tournament Info</h5></div>
                </div>
                <div class="ios-detail-rows">
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Status</span>
                        <span class="ios-detail-row-value">
                            <span class="ios-status-badge <?php echo $st; ?>">
                                <i class="fas fa-circle" style="font-size:7px"></i> <?php echo $stLabel; ?>
                            </span>
                        </span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Format</span>
                        <span class="ios-detail-row-value"><?php echo $fmt; ?></span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Groups</span>
                        <span class="ios-detail-row-value"><?php echo count($groups); ?></span>
                    </div>
                    <?php if ($tour['start_date']): ?>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Start Date</span>
                        <span class="ios-detail-row-value" style="font-size:12px"><?php echo formatDate($tour['start_date'], 'd M Y'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($tour['num_groups'] && $tour['format'] !== 'knockout'): ?>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Num. Groups</span>
                        <span class="ios-detail-row-value"><?php echo $tour['num_groups']; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin Actions card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon purple"><i class="fas fa-cog"></i></div>
                    <div class="ios-section-title"><h5>Actions</h5></div>
                </div>
                <div class="ios-admin-actions">
                    <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-futbol me-2"></i>Add Fixture
                    </a>
                    <?php if ($isSetup): ?>
                    <a href="<?php echo BASE_URL; ?>tournaments/form.php?id=<?php echo $id; ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-edit me-2"></i>Edit Tournament
                    </a>
                    <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="activate">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-play me-2"></i>Activate Tournament
                        </button>
                    </form>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $id; ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-trophy me-2"></i>View Tournament
                    </a>
                </div>
            </div>

        </div>
    </div><!-- /sidebar -->

</div><!-- /ios-view-layout -->

<!-- ===== iOS MENU MODAL (mobile) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeIosMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h5 class="ios-menu-title">Setup Options</h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <!-- Tournament info (mobile) -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Tournament Info</p>
            <div class="ios-menu-card">
                <div class="ios-menu-item" style="cursor:default">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-trophy"></i></div>
                        <div>
                            <div class="ios-menu-item-label" style="font-size:13px"><?php echo e($tour['name']); ?></div>
                            <div style="font-size:12px;color:var(--text-secondary)"><?php echo $fmt; ?> · <?php echo $stLabel; ?></div>
                        </div>
                    </div>
                </div>
                <div class="ios-menu-item" style="cursor:default">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <div class="ios-menu-item-label" style="font-size:13px">Groups</div>
                            <div style="font-size:12px;color:var(--text-secondary)"><?php echo count($groups); ?> group<?php echo count($groups) !== 1 ? 's' : ''; ?> set up</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions (mobile) -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Actions</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-futbol"></i></div>
                        <div class="ios-menu-item-label">Add Fixture</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php if ($isSetup): ?>
                <a href="<?php echo BASE_URL; ?>tournaments/form.php?id=<?php echo $id; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-edit"></i></div>
                        <div class="ios-menu-item-label">Edit Tournament</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="ios-menu-item">
                        <div class="ios-menu-item-left">
                            <div class="ios-menu-item-icon green"><i class="fas fa-play"></i></div>
                            <div class="ios-menu-item-label">Activate Tournament</div>
                        </div>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation (mobile) -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $id; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-trophy"></i></div>
                        <div class="ios-menu-item-label">View Tournament</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-label">All Tournaments</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-home"></i></div>
                        <div class="ios-menu-item-label">Dashboard</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
function openIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.add('active');
    document.getElementById('iosMenuModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeIosMenu() {
    document.getElementById('iosMenuBackdrop').classList.remove('active');
    document.getElementById('iosMenuModal').classList.remove('active');
    document.body.style.overflow = '';
}
(function() {
    var modal = document.getElementById('iosMenuModal');
    var startY = 0, isDragging = false;
    modal.addEventListener('touchstart', function(e) { startY = e.touches[0].clientY; isDragging = true; }, { passive: true });
    modal.addEventListener('touchmove', function(e) {
        if (!isDragging) return;
        var dy = e.touches[0].clientY - startY;
        if (dy > 0) modal.style.transform = 'translateY(' + dy + 'px)';
    }, { passive: true });
    modal.addEventListener('touchend', function(e) {
        if (!isDragging) return;
        isDragging = false;
        modal.style.transform = '';
        if (e.changedTouches[0].clientY - startY > 80) closeIosMenu();
    });
})();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
