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

// All active members for team assignment dropdowns
$allMembers = $db->fetchAll(
    "SELECT m.id, m.member_id AS member_code, CONCAT(m.first_name,' ',m.last_name) AS full_name
     FROM members m WHERE m.status='active' ORDER BY m.first_name",
    []
);

// Active tab: which team are we viewing members for?
$activeTeamId = (int)($_GET['team'] ?? 0);

$pageTitle = 'Setup: ' . $tour['name'];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <nav class="mb-1">
            <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $id; ?>" class="text-muted small">← <?php echo e($tour['name']); ?></a>
        </nav>
        <h1 class="content-title">Setup Groups & Teams</h1>
        <p class="content-subtitle">
            <span class="badge badge-<?php echo $tour['status']==='setup'?'warning':($tour['status']==='active'?'success':'secondary'); ?>">
                <?php echo ucfirst($tour['status']); ?>
            </span>
            · <?php echo $tour['format']; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="btn btn-secondary">
            <i class="fas fa-futbol me-2"></i>Add Fixture
        </a>
        <?php if ($tour['status'] === 'setup'): ?>
        <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-inline">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="activate"><input type="hidden" name="id" value="<?php echo $id; ?>">
            <button type="submit" class="btn btn-success"><i class="fas fa-play me-2"></i>Activate Tournament</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Add Group -->
<?php if ($tour['status'] === 'setup'): ?>
<div class="card mb-4">
    <div class="card-header"><h6 class="card-title mb-0"><i class="fas fa-layer-group me-2"></i>Add Group</h6></div>
    <div class="card-body">
        <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-flex gap-2 align-items-end">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="add_group">
            <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
            <div>
                <label class="form-label mb-1">Group Name</label>
                <input type="text" name="group_name" class="form-control" placeholder="e.g. Group A" required maxlength="50" style="width:220px">
            </div>
            <button type="submit" class="btn btn-primary">Add Group</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Groups + Teams -->
<?php if (empty($groups)): ?>
<div class="card text-center py-5">
    <div style="font-size:36px;opacity:.3">🏆</div>
    <p class="text-muted mt-3">No groups yet. Add a group to get started.</p>
</div>
<?php else: ?>
<div class="row g-4">
    <?php foreach ($groups as $group):
        $teams = $tourObj->getTeamsByGroup($group['id']);
    ?>
    <div class="col-12 col-md-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0"><i class="fas fa-layer-group text-primary me-2"></i><?php echo e($group['group_name']); ?></h6>
                <?php if ($tour['status'] === 'setup'): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-inline">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete_group">
                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                    <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                    <button type="submit" class="btn btn-secondary btn-sm"
                            onclick="return confirm('Delete this group and all its teams?')">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <!-- Add team form -->
                <?php if ($tour['status'] === 'setup'): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-flex gap-2 mb-3">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="add_team">
                    <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                    <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                    <input type="text" name="team_name" class="form-control form-control-sm" placeholder="Team name" required maxlength="100">
                    <button type="submit" class="btn btn-primary btn-sm flex-shrink-0">Add</button>
                </form>
                <?php endif; ?>

                <!-- Teams -->
                <?php if (empty($teams)): ?>
                <p class="text-muted small">No teams yet.</p>
                <?php else: ?>
                <?php foreach ($teams as $team):
                    $teamMembers = $tourObj->getTeamMembers($team['id']);
                    $isActive    = ($activeTeamId === (int)$team['id']);
                ?>
                <div class="team-block mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="fw-semibold small"><i class="fas fa-users text-muted me-1"></i><?php echo e($team['team_name']); ?> <span class="text-muted">(<?php echo count($teamMembers); ?>)</span></div>
                        <div class="d-flex gap-1">
                            <a href="?id=<?php echo $id; ?>&team=<?php echo $isActive ? 0 : $team['id']; ?>"
                               class="btn btn-secondary btn-sm" title="Manage members">
                                <i class="fas fa-<?php echo $isActive ? 'chevron-up' : 'user-plus'; ?>"></i>
                            </a>
                            <?php if ($tour['status'] === 'setup'): ?>
                            <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="delete_team">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                                <button type="submit" class="btn btn-secondary btn-sm"
                                        onclick="return confirm('Delete this team?')"><i class="fas fa-times"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Members list -->
                    <?php if (!empty($teamMembers)): ?>
                    <ul class="list-unstyled mb-1">
                        <?php foreach ($teamMembers as $tm): ?>
                        <li class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid var(--border-color);font-size:13px">
                            <span><?php echo e($tm['full_name']); ?> <small class="text-muted"><?php echo e($tm['member_code']); ?></small></span>
                            <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-inline">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="remove_member">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                <input type="hidden" name="member_id" value="<?php echo $tm['id']; ?>">
                                <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                                <button type="submit" class="btn btn-secondary" style="padding:1px 6px;font-size:11px">✕</button>
                            </form>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <!-- Add member dropdown (visible when team is active) -->
                    <?php if ($isActive): ?>
                    <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-flex gap-2 mt-2">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="add_member">
                        <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                        <input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                        <select name="member_id" class="form-control form-control-sm" required>
                            <option value="">— Select member —</option>
                            <?php
                            $assignedIds = array_column($teamMembers, 'id');
                            foreach ($allMembers as $am):
                                if (in_array($am['id'], $assignedIds)) continue;
                            ?>
                            <option value="<?php echo $am['id']; ?>"><?php echo e($am['full_name']); ?> (<?php echo e($am['member_code']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-success btn-sm flex-shrink-0">Add</button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
