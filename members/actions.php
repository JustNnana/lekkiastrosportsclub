<?php
/**
 * Member Actions — POST handler for status changes and delete
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Member.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('members/'); }

verifyCsrf();

$action = sanitize($_POST['action'] ?? '');
$id     = (int)($_POST['id']     ?? 0);

if (!$id) { flashError('Invalid request.'); redirect('members/'); }

$memberObj = new Member();
$m         = $memberObj->getById($id);

if (!$m) { flashError('Member not found.'); redirect('members/'); }

switch ($action) {

    case 'activate':
        if ($memberObj->setStatus($id, 'active')) {
            flashSuccess("<strong>{$m['full_name']}</strong> has been activated.");
        } else {
            flashError('Failed to activate member.');
        }
        break;

    case 'suspend':
        if ($memberObj->setStatus($id, 'suspended')) {
            flashSuccess("<strong>{$m['full_name']}</strong> has been suspended.");
        } else {
            flashError('Failed to suspend member.');
        }
        break;

    case 'deactivate':
        if ($memberObj->setStatus($id, 'inactive')) {
            flashSuccess("<strong>{$m['full_name']}</strong> has been deactivated.");
        } else {
            flashError('Failed to deactivate member.');
        }
        break;

    case 'delete':
        requireSuperAdmin(); // Only super admin can hard-delete
        $name = $m['full_name'];
        if ($memberObj->delete($id)) {
            flashSuccess("Member <strong>{$name}</strong> has been permanently deleted.");
            redirect('members/');
        } else {
            flashError('Failed to delete member.');
        }
        break;

    default:
        flashError('Unknown action.');
}

// Return to wherever we came from
$ref = $_SERVER['HTTP_REFERER'] ?? '';
if ($ref && str_contains($ref, 'members/')) {
    header('Location: ' . $ref);
    exit;
}
redirect('members/');
