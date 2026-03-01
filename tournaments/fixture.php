<?php
/**
 * Fixture — add / edit + score entry + player stats
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Tournament.php';

requireAdmin();

$tourObj   = new Tournament();
$fixtureId = (int)($_GET['fixture_id'] ?? 0);
$tourId    = (int)($_GET['tournament_id'] ?? 0);

// Editing existing fixture
$fixture = $fixtureId ? $tourObj->getFixtureById($fixtureId) : null;
if ($fixtureId && !$fixture) { flashError('Fixture not found.'); redirect('tournaments/manage.php'); }

if (!$tourId && $fixture) $tourId = (int)$fixture['tournament_id'];
$tour = $tourObj->getById($tourId);
if (!$tour) { flashError('Tournament not found.'); redirect('tournaments/manage.php'); }

$allTeams   = $tourObj->getAllTeams($tourId);
$isEdit     = (bool)$fixture;
$pageTitle  = $isEdit ? 'Edit Fixture' : 'Add Fixture';

// Player stats (score entry mode)
$showScoreForm = $isEdit && ($fixture['status'] === 'completed' || isset($_GET['score']));
$fixtureStats  = $isEdit ? $tourObj->getFixtureStats($fixtureId) : [];

// Build stat map by member_id for quick lookup
$statMap = [];
foreach ($fixtureStats as $s) { $statMap[$s['member_id']] = $s; }

// Teams for player listing
$homeMembers = $fixture ? $tourObj->getTeamMembers($fixture['home_team_id']) : [];
$awayMembers = $fixture ? $tourObj->getTeamMembers($fixture['away_team_id']) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $postAction = sanitize($_POST['post_action'] ?? 'save_fixture');

    // ── Save fixture details ─────────────────────────────────────────────────
    if ($postAction === 'save_fixture') {
        $data = [
            'tournament_id' => $tourId,
            'home_team_id'  => (int)($_POST['home_team_id'] ?? 0),
            'away_team_id'  => (int)($_POST['away_team_id'] ?? 0),
            'round'         => sanitize($_POST['round']      ?? ''),
            'location'      => sanitize($_POST['location']   ?? ''),
            'match_date'    => sanitize($_POST['match_date'] ?? ''),
        ];
        $errors = [];
        if (!$data['home_team_id'] || !$data['away_team_id']) $errors[] = 'Select both teams.';
        if ($data['home_team_id'] === $data['away_team_id'])  $errors[] = 'Teams must be different.';
        if ($data['match_date']) $data['match_date'] = date('Y-m-d H:i:s', strtotime($data['match_date']));
        else $data['match_date'] = null;

        if (empty($errors)) {
            if ($isEdit) {
                $tourObj->updateFixture($fixtureId, $data);
                flashSuccess('Fixture updated.');
            } else {
                $fixtureId = $tourObj->createFixture($data);
                flashSuccess('Fixture added.');
            }
            redirect('tournaments/view.php?id=' . $tourId);
        }
    }

    // ── Save score + stats ───────────────────────────────────────────────────
    if ($postAction === 'save_score') {
        $homeScore = max(0, (int)($_POST['home_score'] ?? 0));
        $awayScore = max(0, (int)($_POST['away_score'] ?? 0));
        $tourObj->updateFixtureScore($fixtureId, $homeScore, $awayScore);

        // Player stats
        $members = array_merge($homeMembers, $awayMembers);
        foreach ($members as $m) {
            $mid = $m['id'];
            $g   = max(0, (int)($_POST['goals'][$mid]        ?? 0));
            $a   = max(0, (int)($_POST['assists'][$mid]      ?? 0));
            $y   = max(0, (int)($_POST['yellow'][$mid]       ?? 0));
            $r   = max(0, (int)($_POST['red'][$mid]          ?? 0));
            if ($g || $a || $y || $r) {
                $tourObj->saveStat($fixtureId, $mid, $g, $a, $y, $r);
            }
        }

        flashSuccess('Score and stats saved.');
        redirect('tournaments/view.php?id=' . $tourId);
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <nav class="mb-1"><a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $tourId; ?>" class="text-muted small">← <?php echo e($tour['name']); ?></a></nav>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Fixture' : 'Add Fixture'; ?></h1>
    </div>
    <?php if ($isEdit): ?>
    <a href="?fixture_id=<?php echo $fixtureId; ?>&tournament_id=<?php echo $tourId; ?>&score=1"
       class="btn btn-primary btn-sm"><i class="fas fa-futbol me-1"></i>Enter Score</a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <!-- Fixture details form -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="card-title mb-0">Fixture Details</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="post_action" value="save_fixture">
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">Round / Stage</label>
                        <input type="text" name="round" class="form-control"
                               value="<?php echo e($fixture['round'] ?? ($_POST['round'] ?? '')); ?>"
                               placeholder="e.g. Group Stage, Quarter-Final">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Home Team <span class="text-danger">*</span></label>
                        <select name="home_team_id" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach ($allTeams as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($fixture['home_team_id']??0)==$t['id']?'selected':''; ?>>
                                <?php echo e($t['team_name']); ?> <small>(<?php echo e($t['group_name']); ?>)</small>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Away Team <span class="text-danger">*</span></label>
                        <select name="away_team_id" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach ($allTeams as $t): ?>
                            <option value="<?php echo $t['id']; ?>" <?php echo ($fixture['away_team_id']??0)==$t['id']?'selected':''; ?>>
                                <?php echo e($t['team_name']); ?> <small>(<?php echo e($t['group_name']); ?>)</small>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Match Date & Time</label>
                        <input type="datetime-local" name="match_date" class="form-control"
                               value="<?php echo ($fixture && $fixture['match_date']) ? date('Y-m-d\TH:i', strtotime($fixture['match_date'])) : ''; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" name="location" class="form-control"
                               value="<?php echo e($fixture['location'] ?? ''); ?>" placeholder="Venue">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i><?php echo $isEdit ? 'Update Fixture' : 'Add Fixture'; ?>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Score entry (only if editing) -->
    <?php if ($isEdit): ?>
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header"><h6 class="card-title mb-0"><i class="fas fa-futbol me-2"></i>Score & Player Stats</h6></div>
            <div class="card-body">
                <form method="POST">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="post_action" value="save_score">

                    <!-- Score -->
                    <div class="d-flex align-items-center gap-3 mb-4 justify-content-center">
                        <div class="text-center">
                            <div class="fw-bold mb-1"><?php echo e($fixture['home_team']); ?></div>
                            <input type="number" name="home_score" min="0" max="99" class="form-control text-center fw-bold"
                                   style="width:70px;font-size:28px;padding:8px"
                                   value="<?php echo $fixture['home_score'] ?? 0; ?>">
                        </div>
                        <div class="fw-bold text-muted" style="font-size:24px;margin-top:24px">–</div>
                        <div class="text-center">
                            <div class="fw-bold mb-1"><?php echo e($fixture['away_team']); ?></div>
                            <input type="number" name="away_score" min="0" max="99" class="form-control text-center fw-bold"
                                   style="width:70px;font-size:28px;padding:8px"
                                   value="<?php echo $fixture['away_score'] ?? 0; ?>">
                        </div>
                    </div>

                    <!-- Player stats table -->
                    <?php
                    function statsRow(array $members, string $teamName, array $statMap, string $label): void {
                        if (empty($members)) return;
                        echo "<h6 class='fw-semibold mb-2 mt-3'>{$label} — " . e($teamName) . "</h6>";
                        echo '<div class="table-responsive"><table class="table table-sm mb-2">';
                        echo '<thead><tr><th>Player</th><th class="text-center" title="Goals">⚽</th><th class="text-center" title="Assists">🅰</th><th class="text-center" title="Yellow cards">🟨</th><th class="text-center" title="Red cards">🟥</th></tr></thead><tbody>';
                        foreach ($members as $m) {
                            $mid = $m['id'];
                            $s   = $statMap[$mid] ?? ['goals'=>0,'assists'=>0,'yellow_cards'=>0,'red_cards'=>0];
                            echo "<tr><td class='small'>" . e($m['full_name']) . "</td>";
                            foreach (['goals'=>'goals','assists'=>'assists','yellow_cards'=>'yellow','red_cards'=>'red'] as $col=>$inp) {
                                echo "<td><input type='number' name='{$inp}[{$mid}]' min='0' max='99' value='{$s[$col]}' class='form-control form-control-sm text-center' style='width:55px'></td>";
                            }
                            echo "</tr>";
                        }
                        echo '</tbody></table></div>';
                    }
                    statsRow($homeMembers, $fixture['home_team'], $statMap, '🏠 Home');
                    statsRow($awayMembers, $fixture['away_team'], $statMap, '✈ Away');
                    ?>

                    <button type="submit" class="btn btn-success w-100 mt-2">
                        <i class="fas fa-save me-2"></i>Save Score & Stats
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
