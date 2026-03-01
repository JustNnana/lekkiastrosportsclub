<?php
/**
 * API — RSVP to an event
 * POST: event_id, response (attending|not_attending|maybe)
 * Returns: JSON { success, status, message }
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Event.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'Method not allowed']); exit; }

$eventId  = (int)($_POST['event_id'] ?? 0);
$response = sanitize($_POST['response'] ?? '');
$allowed  = ['attending','not_attending','maybe'];

if (!$eventId || !in_array($response, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit;
}

$eventObj = new Event();
$event    = $eventObj->getById($eventId);

if (!$event || $event['status'] !== 'active') {
    echo json_encode(['success'=>false,'message'=>'Event not found or no longer active.']); exit;
}

$userId = (int)$_SESSION['user_id'];
$status = $eventObj->rsvp($eventId, $userId, $response);

$msgs = [
    'attending'     => 'You are attending.',
    'maybe'         => 'Marked as maybe.',
    'not_attending' => 'Marked as not attending.',
    'removed'       => 'RSVP removed.',
];

echo json_encode([
    'success' => true,
    'status'  => $status,
    'message' => $msgs[$status] ?? 'RSVP updated.',
]);
