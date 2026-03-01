<?php
/**
 * Tournament actions — POST handler
 * Actions: activate, complete, delete
 *          add_group, delete_group
 *          add_team, delete_team
 *          add_member, remove_member
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Tournament.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('tournaments/manage.php'); }
verifyCsrf();

$action = sanitize($_POST['action'] ?? '');
$back   = sanitize($_POST['back']   ?? '');

function safeRedirect(string $back, string $fallback): void {
    redirect($back ?: $fallback);
}

$tourObj = new Tournament();

switch ($action) {

    // ── Tournament status ────────────────────────────────────────────────────
    case 'activate':
        $id = (int)($_POST['id'] ?? 0);
        $t  = $tourObj->getById($id);
        if (!$t) { flashError('Not found.'); redirect('tournaments/manage.php'); }
        $tourObj->setStatus($id, 'active');
        flashSuccess('Tournament activated.');
        redirect('tournaments/manage.php');

    case 'complete':
        $id = (int)($_POST['id'] ?? 0);
        $tourObj->setStatus($id, 'completed');
        flashSuccess('Tournament marked completed.');
        redirect('tournaments/manage.php');

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        $tourObj->delete($id);
        flashSuccess('Tournament deleted.');
        redirect('tournaments/manage.php');

    // ── Groups ───────────────────────────────────────────────────────────────
    case 'add_group':
        $tourId = (int)($_POST['tournament_id'] ?? 0);
        $name   = sanitize($_POST['group_name'] ?? '');
        if ($tourId && $name) { $tourObj->createGroup($tourId, $name); flashSuccess('Group added.'); }
        else { flashError('Group name is required.'); }
        redirect('tournaments/setup.php?id=' . $tourId);

    case 'delete_group':
        $groupId = (int)($_POST['group_id']       ?? 0);
        $tourId  = (int)($_POST['tournament_id']  ?? 0);
        $tourObj->deleteGroup($groupId);
        flashSuccess('Group deleted.');
        redirect('tournaments/setup.php?id=' . $tourId);

    // ── Teams ────────────────────────────────────────────────────────────────
    case 'add_team':
        $groupId  = (int)($_POST['group_id']       ?? 0);
        $tourId   = (int)($_POST['tournament_id']  ?? 0);
        $teamName = sanitize($_POST['team_name']   ?? '');
        if ($groupId && $teamName) { $tourObj->createTeam($groupId, $teamName); flashSuccess('Team added.'); }
        else { flashError('Team name is required.'); }
        redirect('tournaments/setup.php?id=' . $tourId);

    case 'delete_team':
        $teamId = (int)($_POST['team_id']       ?? 0);
        $tourId = (int)($_POST['tournament_id'] ?? 0);
        $tourObj->deleteTeam($teamId);
        flashSuccess('Team deleted.');
        redirect('tournaments/setup.php?id=' . $tourId);

    // ── Team members ─────────────────────────────────────────────────────────
    case 'add_member':
        $teamId   = (int)($_POST['team_id']       ?? 0);
        $memberId = (int)($_POST['member_id']      ?? 0);
        $tourId   = (int)($_POST['tournament_id']  ?? 0);
        if (!$tourObj->addTeamMember($teamId, $memberId)) {
            flashError('Member is already assigned to a team in this tournament.');
        } else {
            flashSuccess('Member added to team.');
        }
        redirect('tournaments/setup.php?id=' . $tourId . '&team=' . $teamId);

    case 'remove_member':
        $teamId   = (int)($_POST['team_id']       ?? 0);
        $memberId = (int)($_POST['member_id']      ?? 0);
        $tourId   = (int)($_POST['tournament_id']  ?? 0);
        $tourObj->removeTeamMember($teamId, $memberId);
        flashSuccess('Member removed from team.');
        redirect('tournaments/setup.php?id=' . $tourId . '&team=' . $teamId);

    // ── Fixtures ─────────────────────────────────────────────────────────────
    case 'delete_fixture':
        $fixtureId = (int)($_POST['fixture_id']    ?? 0);
        $tourId    = (int)($_POST['tournament_id'] ?? 0);
        $tourObj->deleteFixture($fixtureId);
        flashSuccess('Fixture deleted.');
        redirect('tournaments/view.php?id=' . $tourId);

    default:
        flashError('Unknown action.');
        redirect('tournaments/manage.php');
}
