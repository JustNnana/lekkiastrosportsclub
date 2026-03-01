<?php
/**
 * Due Actions — POST handler (toggle active, delete, assign_all)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Due.php';
require_once dirname(__DIR__) . '/classes/Payment.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('payments/dues.php'); }
verifyCsrf();

$action = sanitize($_POST['action'] ?? '');
$id     = (int)($_POST['id'] ?? 0);

if (!$id) { flashError('Invalid request.'); redirect('payments/dues.php'); }

$dueObj = new Due();
$due    = $dueObj->getById($id);

if (!$due) { flashError('Due not found.'); redirect('payments/dues.php'); }

switch ($action) {

    case 'toggle':
        $dueObj->toggleActive($id);
        $state = $due['is_active'] ? 'deactivated' : 'activated';
        flashSuccess("Due <strong>{$due['title']}</strong> has been {$state}.");
        break;

    case 'delete':
        if (!$dueObj->delete($id)) {
            flashError('Cannot delete this due — payments already exist. Deactivate it instead.');
        } else {
            flashSuccess("Due <strong>{$due['title']}</strong> deleted.");
        }
        break;

    case 'assign_all':
        $dueDate = sanitize($_POST['due_date'] ?? '');
        if (!$dueDate) {
            flashError('Due date is required for assignment.');
            break;
        }
        $payObj = new Payment();
        $count  = $payObj->assignToAll($id, (float)$due['amount'], $dueDate);
        flashSuccess("Assigned <strong>{$due['title']}</strong> to {$count} active member(s) for " . formatDate($dueDate) . '.');
        break;

    default:
        flashError('Unknown action.');
}

redirect('payments/dues.php');
