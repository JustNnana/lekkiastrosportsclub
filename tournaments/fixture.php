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

// Player stats
$fixtureStats = $isEdit ? $tourObj->getFixtureStats($fixtureId) : [];
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

                // Notify all members (push + in-app + email)
                try {
                    require_once dirname(__DIR__) . '/classes/PushService.php';
                    require_once dirname(__DIR__) . '/app/mail/emails.php';

                    // Resolve team names from already-loaded $allTeams
                    $teamMap  = array_column($allTeams, 'name', 'id');
                    $homeName = $teamMap[$data['home_team_id']] ?? 'Home';
                    $awayName = $teamMap[$data['away_team_id']] ?? 'Away';
                    $dateStr  = $data['match_date'] ? date('d M Y, g:i A', strtotime($data['match_date'])) : 'TBC';
                    $matchTitle = "{$homeName} vs {$awayName}";
                    $pushBody   = "New fixture in {$tour['name']}: {$matchTitle} — {$dateStr}.";
                    $notifUrl   = BASE_URL . "tournaments/view.php?id={$tourId}";
                    $push = new PushService();
                    $push->notifyAll('fixture', 'New Fixture: ' . $matchTitle, $pushBody, $notifUrl);

                    $db      = Database::getInstance();
                    $members = $db->fetchAll("SELECT full_name, email FROM users WHERE status = 'active' AND role = 'user'");
                    $emailMsg = "<p>A new fixture has been scheduled:</p>
                        <table style='background:#f4f6f8;border-radius:8px;padding:20px;width:100%;margin:16px 0;border-collapse:collapse;'>
                            <tr><td style='padding:8px 0;color:#637381;width:120px;'>Tournament</td>
                                <td style='padding:8px 0;font-weight:700;color:#1c252e;'>" . htmlspecialchars($tour['name']) . "</td></tr>
                            <tr><td style='padding:8px 0;color:#637381;'>Match</td>
                                <td style='padding:8px 0;font-weight:700;color:#1c252e;'>{$matchTitle}</td></tr>
                            <tr><td style='padding:8px 0;color:#637381;'>Date</td>
                                <td style='padding:8px 0;font-weight:700;color:#1c252e;'>{$dateStr}</td></tr>"
                        . ($data['location'] ? "<tr><td style='padding:8px 0;color:#637381;'>Location</td>
                                <td style='padding:8px 0;color:#1c252e;'>" . htmlspecialchars($data['location']) . "</td></tr>" : '')
                        . "</table>";
                    foreach ($members as $m) {
                        sendNotificationEmail($m['email'], $m['full_name'], 'New Fixture: ' . $matchTitle, 'New Fixture Scheduled', $emailMsg, $notifUrl, 'View Tournament');
                    }
                } catch (Throwable $e) {
                    error_log('Fixture notification failed: ' . $e->getMessage());
                }
            }
            redirect('tournaments/view.php?id=' . $tourId);
        }
    }

    // ── Save score + stats ───────────────────────────────────────────────────
    if ($postAction === 'save_score') {
        $homeScore = max(0, (int)($_POST['home_score'] ?? 0));
        $awayScore = max(0, (int)($_POST['away_score'] ?? 0));
        $tourObj->updateFixtureScore($fixtureId, $homeScore, $awayScore);

        $members = array_merge($homeMembers, $awayMembers);
        foreach ($members as $m) {
            $mid = $m['id'];
            $g   = max(0, (int)($_POST['goals'][$mid]   ?? 0));
            $a   = max(0, (int)($_POST['assists'][$mid] ?? 0));
            $y   = max(0, (int)($_POST['yellow'][$mid]  ?? 0));
            $r   = max(0, (int)($_POST['red'][$mid]     ?? 0));
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
.form-container {
    display: grid;
    grid-template-columns: 1fr 280px;
    gap: var(--spacing-5);
    max-width: 1100px;
    margin: 0 auto;
}
@media (max-width: 992px) { .form-container { grid-template-columns: 1fr; } }

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
.ios-section-icon.teal   { background: rgba(100,210,255,.15);color: var(--ios-teal); }

.ios-section-title { flex: 1; min-width: 0; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

/* ── Form body ── */
.ios-section-body { padding: var(--spacing-5); }
.ios-form-group { margin-bottom: var(--spacing-4); }
.ios-form-group:last-child { margin-bottom: 0; }
.ios-form-label {
    display: block; font-size: 13px; font-weight: 600;
    color: var(--text-secondary); margin-bottom: 6px;
    text-transform: uppercase; letter-spacing: .3px;
}
.ios-form-label .req { color: var(--ios-red); margin-left: 2px; }
.ios-form-label .opt { color: var(--text-muted); font-weight: 400; text-transform: none; letter-spacing: 0; }
.ios-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-4); }
@media (max-width: 600px) { .ios-form-grid { grid-template-columns: 1fr; } }

/* ── Sidebar ── */
.ios-sidebar { display: flex; flex-direction: column; }
.ios-sidebar-sticky { position: sticky; top: var(--spacing-4); }
.ios-admin-actions { padding: var(--spacing-4); display: flex; flex-direction: column; gap: var(--spacing-3); }

.ios-tip-list { padding: var(--spacing-4) var(--spacing-5); display: flex; flex-direction: column; gap: var(--spacing-3); }
.ios-tip-item { display: flex; gap: 10px; font-size: 13px; }
.ios-tip-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.ios-tip-text { color: var(--text-secondary); line-height: 1.5; }
.ios-tip-text strong { color: var(--text-primary); }

/* ── Score board ── */
.ios-score-board {
    display: flex; align-items: center; justify-content: center;
    gap: 20px; padding: var(--spacing-5); background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}
.ios-score-team-col { text-align: center; flex: 1; min-width: 0; }
.ios-score-team-name {
    font-size: 13px; font-weight: 600; color: var(--text-secondary);
    margin-bottom: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ios-score-input {
    width: 74px; height: 74px; font-size: 34px; font-weight: 800;
    text-align: center; color: var(--text-primary); background: var(--bg-primary);
    border: 2px solid var(--border-color); border-radius: 14px; outline: none;
    transition: border-color .2s, box-shadow .2s; display: inline-block;
}
.ios-score-input:focus { border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10,132,255,.15); }
.ios-score-sep {
    font-size: 30px; font-weight: 300; color: var(--text-muted); flex-shrink: 0;
}

/* ── Stats table ── */
.ios-stats-group-label {
    display: flex; align-items: center; gap: 8px;
    font-size: 12px; font-weight: 700; color: var(--text-secondary);
    text-transform: uppercase; letter-spacing: .5px;
    padding: 10px var(--spacing-5); background: var(--bg-subtle);
    border-top: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
}
.ios-stats-group-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ios-stats-group-dot.home { background: var(--ios-blue); }
.ios-stats-group-dot.away { background: var(--ios-orange); }

.ios-stats-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.ios-stats-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 360px; }
.ios-stats-table thead th {
    padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;
    letter-spacing: .4px; color: var(--text-muted); text-align: center;
    background: var(--bg-subtle); border-bottom: 1px solid var(--border-color);
}
.ios-stats-table thead th:first-child { text-align: left; padding-left: var(--spacing-5); }
.ios-stats-table tbody tr { border-bottom: 1px solid var(--border-color); transition: background .15s; }
.ios-stats-table tbody tr:last-child { border-bottom: none; }
.ios-stats-table tbody tr:hover { background: rgba(255,255,255,.02); }
.ios-stats-table td { padding: 10px 12px; text-align: center; color: var(--text-primary); }
.ios-stats-table td:first-child { text-align: left; padding-left: var(--spacing-5); font-weight: 500; }
.ios-stat-num-input {
    width: 54px; height: 36px; font-size: 14px; font-weight: 600;
    text-align: center; color: var(--text-primary); background: var(--bg-secondary);
    border: 1px solid var(--border-color); border-radius: 8px; outline: none;
    transition: border-color .2s, box-shadow .2s;
}
.ios-stat-num-input:focus { border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10,132,255,.12); }
.icon-goal   { color: var(--ios-blue);   }
.icon-assist { color: var(--ios-green);  }
.icon-yellow { color: var(--ios-orange); }
.icon-red    { color: var(--ios-red);    }

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
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-menu-item-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-menu-item-label   { font-size: 15px; font-weight: 500; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .ios-sidebar { display: none; }
    .form-container { grid-template-columns: 1fr; }
    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 14px; }
    .ios-section-body { padding: var(--spacing-4); }
}
</style>

<!-- ===== DESKTOP HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-start">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $tourId; ?>" class="breadcrumb-link">
                        <?php echo e($tour['name']); ?>
                    </a>
                </li>
                <li class="breadcrumb-item active"><?php echo $isEdit ? 'Edit Fixture' : 'Add Fixture'; ?></li>
            </ol>
        </nav>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Fixture' : 'Add Fixture'; ?></h1>
        <p class="content-subtitle"><?php echo $isEdit ? 'Update fixture details or enter match score and player stats.' : 'Schedule a new match for this tournament.'; ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $tourId; ?>" class="btn btn-secondary flex-shrink-0">
        <i class="fas fa-arrow-left me-2"></i>Back to Tournament
    </a>
</div>

<!-- ===== MOBILE HEADER ===== -->
<div class="ios-section-header d-md-none mb-3" style="border-radius:14px;border:1px solid var(--border-color)">
    <div class="ios-section-icon blue">
        <i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus-circle'; ?>"></i>
    </div>
    <div class="ios-section-title">
        <h5><?php echo $isEdit ? 'Edit Fixture' : 'Add Fixture'; ?></h5>
        <p><?php echo e($tour['name']); ?></p>
    </div>
    <button onclick="openIosMenu()" style="width:36px;height:36px;border-radius:50%;background:var(--bg-secondary);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0">
        <i class="fas fa-ellipsis-v" style="color:var(--text-primary);font-size:16px"></i>
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4" style="max-width:1100px;margin-left:auto;margin-right:auto;border-radius:12px">
    <i class="fas fa-exclamation-circle me-2"></i>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<!-- ===== FIXTURE DETAILS FORM ===== -->
<form method="POST" id="fixtureForm">
    <?php echo csrfField(); ?>
    <input type="hidden" name="post_action" value="save_fixture">

    <div class="form-container">

        <!-- LEFT -->
        <div>
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Fixture Details</h5>
                        <p>Teams, round, date and venue</p>
                    </div>
                </div>
                <div class="ios-section-body">

                    <div class="ios-form-group">
                        <label class="ios-form-label">Round / Stage <span class="opt">(optional)</span></label>
                        <input type="text" name="round" class="form-control" style="border-radius:10px"
                               value="<?php echo e($fixture['round'] ?? ($_POST['round'] ?? '')); ?>"
                               placeholder="e.g. Group Stage, Quarter-Final">
                    </div>

                    <div class="ios-form-grid">
                        <div class="ios-form-group">
                            <label class="ios-form-label">Home Team <span class="req">*</span></label>
                            <select name="home_team_id" class="form-control" style="border-radius:10px" required>
                                <option value="">— Select team —</option>
                                <?php foreach ($allTeams as $t): ?>
                                <option value="<?php echo $t['id']; ?>"
                                    <?php echo (($fixture['home_team_id'] ?? 0) == $t['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($t['team_name']); ?> (<?php echo e($t['group_name']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ios-form-group">
                            <label class="ios-form-label">Away Team <span class="req">*</span></label>
                            <select name="away_team_id" class="form-control" style="border-radius:10px" required>
                                <option value="">— Select team —</option>
                                <?php foreach ($allTeams as $t): ?>
                                <option value="<?php echo $t['id']; ?>"
                                    <?php echo (($fixture['away_team_id'] ?? 0) == $t['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($t['team_name']); ?> (<?php echo e($t['group_name']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="ios-form-grid">
                        <div class="ios-form-group">
                            <label class="ios-form-label">Match Date &amp; Time <span class="opt">(optional)</span></label>
                            <input type="datetime-local" name="match_date" class="form-control" style="border-radius:10px"
                                   value="<?php echo ($fixture && $fixture['match_date']) ? date('Y-m-d\TH:i', strtotime($fixture['match_date'])) : ''; ?>">
                        </div>
                        <div class="ios-form-group">
                            <label class="ios-form-label">Venue / Location <span class="opt">(optional)</span></label>
                            <div style="position:relative">
                                <i class="fas fa-map-marker-alt" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;pointer-events:none"></i>
                                <input type="text" name="location" class="form-control" style="border-radius:10px;padding-left:34px"
                                       value="<?php echo e($fixture['location'] ?? ''); ?>" placeholder="e.g. Main Pitch A">
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Mobile submit -->
            <div class="d-md-none" style="display:flex;flex-direction:column;gap:10px;margin-bottom:var(--spacing-4)">
                <button type="submit" class="btn btn-primary w-100" style="border-radius:12px;padding:14px">
                    <i class="fas fa-<?php echo $isEdit ? 'save' : 'plus'; ?> me-2"></i>
                    <?php echo $isEdit ? 'Update Fixture' : 'Add Fixture'; ?>
                </button>
                <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $tourId; ?>" class="btn btn-secondary w-100" style="border-radius:12px;padding:14px">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>
        </div><!-- /left -->

        <!-- RIGHT: SIDEBAR -->
        <div class="ios-sidebar">
            <div class="ios-sidebar-sticky">

                <!-- Actions -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon <?php echo $isEdit ? 'orange' : 'green'; ?>">
                            <i class="fas fa-<?php echo $isEdit ? 'save' : 'check'; ?>"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5><?php echo $isEdit ? 'Save Changes' : 'Add Fixture'; ?></h5>
                        </div>
                    </div>
                    <div class="ios-admin-actions">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-<?php echo $isEdit ? 'save' : 'plus'; ?> me-2"></i>
                            <?php echo $isEdit ? 'Update Fixture' : 'Add Fixture'; ?>
                        </button>
                        <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $tourId; ?>" class="btn btn-secondary w-100">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                        <?php if ($isEdit): ?>
                        <a href="?fixture_id=<?php echo $fixtureId; ?>&tournament_id=<?php echo $tourId; ?>&score=1"
                           class="btn btn-outline-primary w-100">
                            <i class="fas fa-futbol me-2"></i>Enter Score
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tips / Info -->
                <?php if (!$isEdit): ?>
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue"><i class="fas fa-lightbulb"></i></div>
                        <div class="ios-section-title"><h5>Quick Tips</h5></div>
                    </div>
                    <div class="ios-tip-list">
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">📅</span>
                            <span class="ios-tip-text"><strong>Set a date</strong> so members know when the match is scheduled.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">📍</span>
                            <span class="ios-tip-text"><strong>Add a venue</strong> so players know exactly where to show up.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">⚽</span>
                            <span class="ios-tip-text"><strong>Enter scores</strong> after the match to update standings automatically.</span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue"><i class="fas fa-info-circle"></i></div>
                        <div class="ios-section-title"><h5>Fixture Info</h5></div>
                    </div>
                    <div style="padding:var(--spacing-4) var(--spacing-5)">
                        <div style="font-size:13px;color:var(--text-secondary);line-height:1.6">
                            <p class="mb-2">Update the fixture details then save, or use <strong>Enter Score</strong> to record match results and player stats.</p>
                            <p class="mb-0">Scores update standings and top scorers automatically.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div><!-- /sidebar -->

    </div><!-- /form-container -->
</form>

<!-- ===== SCORE & PLAYER STATS (edit mode only) ===== -->
<?php if ($isEdit): ?>
<form method="POST" id="scoreForm" style="max-width:1100px;margin:0 auto">
    <?php echo csrfField(); ?>
    <input type="hidden" name="post_action" value="save_score">

    <div class="ios-section-card">
        <div class="ios-section-header">
            <div class="ios-section-icon green">
                <i class="fas fa-futbol"></i>
            </div>
            <div class="ios-section-title">
                <h5>Score &amp; Player Stats</h5>
                <p>Record goals, assists and cards for each player</p>
            </div>
        </div>

        <!-- Score board -->
        <div class="ios-score-board">
            <div class="ios-score-team-col">
                <div class="ios-score-team-name"><?php echo e($fixture['home_team']); ?></div>
                <input type="number" name="home_score" min="0" max="99" class="ios-score-input"
                       value="<?php echo (int)($fixture['home_score'] ?? 0); ?>">
            </div>
            <div class="ios-score-sep">–</div>
            <div class="ios-score-team-col">
                <div class="ios-score-team-name"><?php echo e($fixture['away_team']); ?></div>
                <input type="number" name="away_score" min="0" max="99" class="ios-score-input"
                       value="<?php echo (int)($fixture['away_score'] ?? 0); ?>">
            </div>
        </div>

        <?php
        function iosStatsGroup(array $members, string $teamName, array $statMap, string $side): void {
            if (empty($members)) return;
            $dot   = $side === 'home' ? 'home' : 'away';
            $label = $side === 'home' ? 'Home' : 'Away';
            echo "<div class='ios-stats-group-label'>";
            echo   "<span class='ios-stats-group-dot {$dot}'></span>";
            echo   "{$label} — " . e($teamName);
            echo "</div>";
            echo "<div class='ios-stats-wrap'><table class='ios-stats-table'>";
            echo "<thead><tr>";
            echo   "<th>Player</th>";
            echo   "<th><i class='fas fa-circle icon-goal'></i>&nbsp;Goals</th>";
            echo   "<th><i class='fas fa-shoe-prints icon-assist'></i>&nbsp;Assists</th>";
            echo   "<th><i class='fas fa-square icon-yellow'></i>&nbsp;Yellow</th>";
            echo   "<th><i class='fas fa-square icon-red'></i>&nbsp;Red</th>";
            echo "</tr></thead><tbody>";
            foreach ($members as $m) {
                $mid = $m['id'];
                $s   = $statMap[$mid] ?? ['goals'=>0,'assists'=>0,'yellow_cards'=>0,'red_cards'=>0];
                echo "<tr><td>" . e($m['full_name']) . "</td>";
                foreach (['goals'=>'goals','assists'=>'assists','yellow_cards'=>'yellow','red_cards'=>'red'] as $col => $inp) {
                    echo "<td><input type='number' name='{$inp}[{$mid}]' min='0' max='99' value='{$s[$col]}' class='ios-stat-num-input'></td>";
                }
                echo "</tr>";
            }
            echo "</tbody></table></div>";
        }

        iosStatsGroup($homeMembers, $fixture['home_team'], $statMap, 'home');
        iosStatsGroup($awayMembers, $fixture['away_team'], $statMap, 'away');
        ?>

        <div style="padding:var(--spacing-4) var(--spacing-5)">
            <button type="submit" class="btn btn-success w-100" style="border-radius:12px;padding:14px">
                <i class="fas fa-check me-2"></i>Save Score &amp; Stats
            </button>
        </div>
    </div>
</form>
<?php endif; ?>

<!-- ===== iOS MENU MODAL (mobile) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeIosMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h5 class="ios-menu-title"><?php echo $isEdit ? 'Edit Fixture' : 'Add Fixture'; ?></h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <?php if ($isEdit): ?>
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Actions</p>
            <div class="ios-menu-card">
                <button type="button" class="ios-menu-item" onclick="document.getElementById('fixtureForm').submit();closeIosMenu()">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-save"></i></div>
                        <div class="ios-menu-item-label">Update Fixture</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
                <button type="button" class="ios-menu-item" onclick="document.getElementById('scoreForm').submit();closeIosMenu()">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-futbol"></i></div>
                        <div class="ios-menu-item-label">Save Score &amp; Stats</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>tournaments/view.php?id=<?php echo $tourId; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-trophy"></i></div>
                        <div class="ios-menu-item-label"><?php echo e($tour['name']); ?></div>
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
    var modal  = document.getElementById('iosMenuModal');
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
