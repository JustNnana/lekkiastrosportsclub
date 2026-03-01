<?php
/**
 * Poll actions — POST handler
 * Actions: close, reopen, delete
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Poll.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('polls/manage.php'); }
verifyCsrf();

$action = sanitize($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? 0);

if (!$id) { flashError('Invalid poll.'); redirect('polls/manage.php'); }

$pollObj = new Poll();
$poll    = $pollObj->getById($id);

if (!$poll) { flashError('Poll not found.'); redirect('polls/manage.php'); }

switch ($action) {
    case 'close':
        $pollObj->close($id);
        flashSuccess('Poll closed.');
        break;

    case 'reopen':
        // Only reopen if deadline hasn't passed — otherwise update deadline via edit
        if (strtotime($poll['deadline']) < time()) {
            flashError('Cannot reopen: the deadline has already passed. Edit the poll to set a new deadline.');
        } else {
            $pollObj->reopen($id);
            flashSuccess('Poll reopened.');
        }
        break;

    case 'delete':
        $pollObj->delete($id);
        flashSuccess('Poll deleted.');
        break;

    default:
        flashError('Unknown action.');
}

redirect('polls/manage.php');
