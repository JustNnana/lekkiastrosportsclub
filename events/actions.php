<?php
/**
 * Event actions — POST handler
 * Actions: complete, cancel, reactivate, delete
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Event.php';

requireAdmin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('events/manage.php'); }
verifyCsrf();

$action = sanitize($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? 0);
if (!$id) { flashError('Invalid event.'); redirect('events/manage.php'); }

$eventObj = new Event();
$event    = $eventObj->getById($id);
if (!$event) { flashError('Event not found.'); redirect('events/manage.php'); }

switch ($action) {
    case 'complete':
        $eventObj->setStatus($id, 'completed');
        flashSuccess('Event marked as completed.');
        break;
    case 'cancel':
        $eventObj->setStatus($id, 'cancelled');
        flashSuccess('Event cancelled.');
        break;
    case 'reactivate':
        $eventObj->setStatus($id, 'active');
        flashSuccess('Event reactivated.');
        break;
    case 'delete':
        $eventObj->delete($id);
        flashSuccess('Event deleted.');
        redirect('events/manage.php');
        break;
    default:
        flashError('Unknown action.');
}

redirect('events/manage.php');
