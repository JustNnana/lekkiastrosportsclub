<?php
/**
 * Tournament view — full detail: groups, standings, fixtures, top scorers
 * Accessible to all logged-in users.
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

// Group fixtures by round for tab display
$rounds = [];
foreach ($fixtures as $f) {
    $round = $f['round'] ?: 'Unscheduled';
    $rounds[$round][] = $f;
}

$statusColors = ['setup'=>'warning','active'=>'success','completed'=>'secondary'];
$formatLabels = ['league'=>'League','knockout'=>'Knockout','group_knockout'=>'Group + Knockout'];

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <nav class="mb-1"><a href="<?php echo BASE_URL; ?>tournaments/<?php echo isAdmin()?'manage':'index'; ?>.php" class="text-muted small">← Tournaments</a></nav>
        <h1 class="content-title"><?php echo e($tour['name']); ?></h1>
        <p class="content-subtitle">
            <span class="badge badge-<?php echo $statusColors[$tour['status']] ?? 'secondary'; ?>"><?php echo ucfirst($tour['status']); ?></span>
            <span class="badge badge-info ms-1"><?php echo $formatLabels[$tour['format']] ?? $tour['format']; ?></span>
            <?php if ($tour['start_date']): ?> · Started <?php echo formatDate($tour['start_date'],'d M Y'); ?><?php endif; ?>
        </p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($tour['status'] === 'setup'): ?>
        <a href="<?php echo BASE_URL; ?>tournaments/setup.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-cog me-1"></i>Setup
        </a>
        <?php endif; ?>
        <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i>Add Fixture
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($tour['description']): ?>
<div class="alert alert-info mb-4 small"><i class="fas fa-info-circle me-2"></i><?php echo e($tour['description']); ?></div>
<?php endif; ?>

<!-- Summary stats row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:rgba(var(--primary-rgb),.1);color:var(--primary)"><i class="fas fa-layer-group"></i></div>
        <div class="stat-info"><span class="stat-label">Groups</span><span class="stat-value"><?php echo count($groups); ?></span></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:rgba(59,130,246,.1);color:#3b82f6"><i class="fas fa-shield-alt"></i></div>
        <div class="stat-info"><span class="stat-label">Teams</span><span class="stat-value"><?php echo count($allTeams); ?></span></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:rgba(16,163,74,.1);color:#10a34a"><i class="fas fa-futbol"></i></div>
        <div class="stat-info"><span class="stat-label">Fixtures</span><span class="stat-value"><?php echo count($fixtures); ?></span></div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card"><div class="stat-icon" style="background:rgba(234,179,8,.1);color:#ca8a04"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info"><span class="stat-label">Played</span><span class="stat-value"><?php echo $tour['completed_fixtures']; ?> / <?php echo $tour['total_fixtures']; ?></span></div></div>
    </div>
</div>

<!-- Tab nav -->
<ul class="nav nav-tabs mb-4" id="tourTabs">
    <?php if (!empty($groups)): ?><li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#standings">Standings</a></li><?php endif; ?>
    <li class="nav-item"><a class="nav-link <?php echo empty($groups)?'active':''; ?>" data-bs-toggle="tab" href="#fixtures">Fixtures</a></li>
    <?php if (!empty($topScorers)): ?><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#scorers">Top Scorers</a></li><?php endif; ?>
    <?php if (!empty($allTeams)): ?><li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#teams">Teams</a></li><?php endif; ?>
</ul>

<div class="tab-content">

    <!-- Standings -->
    <?php if (!empty($groups)): ?>
    <div class="tab-pane fade show active" id="standings">
        <?php foreach ($groups as $group):
            $standings = $tourObj->getGroupStandings($group['id']);
        ?>
        <div class="card mb-4">
            <div class="card-header"><h6 class="card-title mb-0"><i class="fas fa-layer-group me-2 text-primary"></i><?php echo e($group['group_name']); ?></h6></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>#</th><th>Team</th>
                            <th class="text-center" title="Played">P</th>
                            <th class="text-center" title="Won">W</th>
                            <th class="text-center" title="Draw">D</th>
                            <th class="text-center" title="Lost">L</th>
                            <th class="text-center" title="Goals For">GF</th>
                            <th class="text-center" title="Goals Against">GA</th>
                            <th class="text-center" title="Goal Difference">GD</th>
                            <th class="text-center" title="Points"><strong>Pts</strong></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($standings)): ?>
                        <tr><td colspan="10" class="text-center text-muted py-3">No teams in this group.</td></tr>
                        <?php else: ?>
                        <?php foreach ($standings as $pos => $s): ?>
                        <tr <?php echo $pos === 0 ? 'class="table-active"' : ''; ?>>
                            <td class="text-muted"><?php echo $pos + 1; ?></td>
                            <td class="fw-semibold"><?php echo e($s['team_name']); ?></td>
                            <td class="text-center"><?php echo $s['P']; ?></td>
                            <td class="text-center text-success"><?php echo $s['W']; ?></td>
                            <td class="text-center text-muted"><?php echo $s['D']; ?></td>
                            <td class="text-center text-danger"><?php echo $s['L']; ?></td>
                            <td class="text-center"><?php echo $s['GF']; ?></td>
                            <td class="text-center"><?php echo $s['GA']; ?></td>
                            <td class="text-center <?php echo $s['GD'] > 0 ? 'text-success' : ($s['GD'] < 0 ? 'text-danger' : ''); ?>">
                                <?php echo ($s['GD'] > 0 ? '+' : '') . $s['GD']; ?>
                            </td>
                            <td class="text-center"><strong><?php echo $s['Pts']; ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Fixtures -->
    <div class="tab-pane fade <?php echo empty($groups)?'show active':''; ?>" id="fixtures">
        <?php if (empty($fixtures)): ?>
        <div class="card text-center py-5">
            <p class="text-muted">No fixtures scheduled yet.</p>
            <?php if (isAdmin()): ?>
            <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?tournament_id=<?php echo $id; ?>" class="btn btn-primary d-inline-block mx-auto" style="width:fit-content">Add First Fixture</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php foreach ($rounds as $roundName => $roundFixtures): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0"><i class="fas fa-flag me-2 text-primary"></i><?php echo e($roundName); ?></h6>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Home</th><th class="text-center">Score</th><th>Away</th><th>Date</th><th>Location</th><?php if(isAdmin()): ?><th class="text-end">Actions</th><?php endif; ?></tr></thead>
                    <tbody>
                        <?php foreach ($roundFixtures as $f): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($f['home_team']); ?></td>
                            <td class="text-center">
                                <?php if ($f['status'] === 'completed' && $f['home_score'] !== null): ?>
                                <span class="fw-bold" style="font-size:16px"><?php echo $f['home_score']; ?> – <?php echo $f['away_score']; ?></span>
                                <?php else: ?>
                                <span class="text-muted">vs</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-semibold"><?php echo e($f['away_team']); ?></td>
                            <td class="text-muted small"><?php echo $f['match_date'] ? formatDate($f['match_date'],'d M Y, g:i A') : '—'; ?></td>
                            <td class="text-muted small"><?php echo $f['location'] ? e($f['location']) : '—'; ?></td>
                            <?php if (isAdmin()): ?>
                            <td class="text-end">
                                <a href="<?php echo BASE_URL; ?>tournaments/fixture.php?fixture_id=<?php echo $f['id']; ?>&tournament_id=<?php echo $id; ?>"
                                   class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="<?php echo BASE_URL; ?>tournaments/actions.php" class="d-inline">
                                    <?php echo csrfField(); ?><input type="hidden" name="action" value="delete_fixture"><input type="hidden" name="fixture_id" value="<?php echo $f['id']; ?>"><input type="hidden" name="tournament_id" value="<?php echo $id; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete fixture?')"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Top Scorers -->
    <?php if (!empty($topScorers)): ?>
    <div class="tab-pane fade" id="scorers">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>#</th><th>Player</th><th>Team</th><th class="text-center">⚽ Goals</th><th class="text-center">🅰 Assists</th><th class="text-center">🟨</th><th class="text-center">🟥</th></tr></thead>
                    <tbody>
                        <?php foreach ($topScorers as $pos => $s): ?>
                        <tr>
                            <td class="text-muted"><?php echo $pos + 1; ?></td>
                            <td>
                                <div class="fw-semibold"><?php echo e($s['player_name']); ?></div>
                                <small class="text-muted"><?php echo e($s['member_code']); ?></small>
                            </td>
                            <td class="text-muted"><?php echo e($s['team_name']); ?></td>
                            <td class="text-center fw-bold text-primary"><?php echo $s['total_goals']; ?></td>
                            <td class="text-center"><?php echo $s['total_assists']; ?></td>
                            <td class="text-center text-warning"><?php echo $s['yellow_cards']; ?></td>
                            <td class="text-center text-danger"><?php echo $s['red_cards']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Teams -->
    <?php if (!empty($allTeams)): ?>
    <div class="tab-pane fade" id="teams">
        <div class="row g-3">
            <?php
            // Group teams by group_name
            $teamsByGroup = [];
            foreach ($allTeams as $t) { $teamsByGroup[$t['group_name']][] = $t; }
            foreach ($teamsByGroup as $gName => $gTeams):
            ?>
            <div class="col-12 col-md-6">
                <div class="card">
                    <div class="card-header"><h6 class="card-title mb-0"><?php echo e($gName); ?></h6></div>
                    <div class="card-body p-0">
                        <?php foreach ($gTeams as $t):
                            $members = $tourObj->getTeamMembers($t['id']);
                        ?>
                        <div class="p-3 border-bottom">
                            <div class="fw-bold mb-2"><i class="fas fa-shield-alt text-primary me-2"></i><?php echo e($t['team_name']); ?></div>
                            <?php if (empty($members)): ?>
                            <p class="text-muted small mb-0">No members assigned.</p>
                            <?php else: ?>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($members as $m): ?>
                                <li class="small text-muted py-1" style="border-bottom:1px solid var(--border-color)">
                                    <?php echo e($m['full_name']); ?> <span class="text-muted"><?php echo e($m['member_code']); ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /tab-content -->

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
