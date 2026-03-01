<?php
/**
 * Event create / edit form (admin only)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Event.php';

requireAdmin();

$eventObj = new Event();
$id       = (int)($_GET['id'] ?? 0);
$event    = $id ? $eventObj->getById($id) : null;
$isEdit   = (bool)$event;

if ($id && !$event) { flashError('Event not found.'); redirect('events/manage.php'); }

$pageTitle = $isEdit ? 'Edit Event' : 'New Event';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $title        = sanitize($_POST['title']        ?? '');
    $description  = sanitize($_POST['description']  ?? '');
    $event_type   = sanitize($_POST['event_type']   ?? 'other');
    $location     = sanitize($_POST['location']     ?? '');
    $start_date   = sanitize($_POST['start_date']   ?? '');
    $end_date     = sanitize($_POST['end_date']     ?? '');
    $is_recurring = isset($_POST['is_recurring']) ? 1 : 0;
    $recurrence   = sanitize($_POST['recurrence']   ?? '');

    $errors = [];
    if (!$title)      $errors[] = 'Title is required.';
    if (!$start_date) $errors[] = 'Start date is required.';
    if ($end_date && $end_date < $start_date) $errors[] = 'End date must be after start date.';

    $validTypes = ['training','match','meeting','social','other'];
    if (!in_array($event_type, $validTypes)) $event_type = 'other';

    if (empty($errors)) {
        $data = [
            'title'        => $title,
            'description'  => $description ?: null,
            'event_type'   => $event_type,
            'location'     => $location ?: null,
            'start_date'   => date('Y-m-d H:i:s', strtotime($start_date)),
            'end_date'     => $end_date ? date('Y-m-d H:i:s', strtotime($end_date)) : null,
            'is_recurring' => $is_recurring,
            'recurrence'   => ($is_recurring && $recurrence) ? $recurrence : null,
            'created_by'   => $_SESSION['user_id'],
        ];

        if ($isEdit) {
            $eventObj->update($id, $data);
            flashSuccess('Event updated.');
            redirect('events/view.php?id=' . $id);
        } else {
            $newId = $eventObj->create($data);
            flashSuccess('Event created.');
            redirect('events/view.php?id=' . $newId);
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Event' : 'New Event'; ?></h1>
        <p class="content-subtitle">Fill in the event details below.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>events/manage.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="POST">
    <?php echo csrfField(); ?>
    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h6 class="card-title mb-0">Event Details</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="200"
                               value="<?php echo e($event['title'] ?? ($_POST['title'] ?? '')); ?>" placeholder="Event title">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="Event description, agenda, notes…"><?php echo e($event['description'] ?? ($_POST['description'] ?? '')); ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Event Type <span class="text-danger">*</span></label>
                            <select name="event_type" class="form-control">
                                <?php foreach (['training'=>'Training','match'=>'Match','meeting'=>'Meeting','social'=>'Social Event','other'=>'Other'] as $val=>$lbl): ?>
                                <option value="<?php echo $val; ?>" <?php echo ($event['event_type']??$_POST['event_type']??'other')===$val?'selected':''; ?>><?php echo $lbl; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" class="form-control" maxlength="200"
                                   value="<?php echo e($event['location'] ?? ($_POST['location'] ?? '')); ?>" placeholder="Venue or address">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Schedule</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Start Date & Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="start_date" class="form-control" required
                                   value="<?php echo $event ? date('Y-m-d\TH:i', strtotime($event['start_date'])) : ($_POST['start_date'] ?? ''); ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">End Date & Time</label>
                            <input type="datetime-local" name="end_date" class="form-control"
                                   value="<?php echo ($event && $event['end_date']) ? date('Y-m-d\TH:i', strtotime($event['end_date'])) : ($_POST['end_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <label class="d-flex align-items-center gap-3" style="cursor:pointer">
                            <input type="checkbox" name="is_recurring" value="1" id="recurringCheck"
                                   <?php echo ($event['is_recurring'] ?? 0) ? 'checked' : ''; ?>>
                            <div><div class="fw-semibold">Recurring event</div><small class="text-muted">This event repeats on a schedule</small></div>
                        </label>
                    </div>
                    <div id="recurrenceField" style="display:<?php echo ($event['is_recurring'] ?? 0) ? 'block' : 'none'; ?>">
                        <label class="form-label">Recurrence</label>
                        <select name="recurrence" class="form-control" style="max-width:200px">
                            <option value="">— Select —</option>
                            <?php foreach (['weekly'=>'Weekly','bi_weekly'=>'Bi-weekly','monthly'=>'Monthly'] as $v=>$l): ?>
                            <option value="<?php echo $v; ?>" <?php echo ($event['recurrence']??'')===$v?'selected':''; ?>><?php echo $l; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-body"><div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i><?php echo $isEdit ? 'Update Event' : 'Create Event'; ?>
                    </button>
                </div></div>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('recurringCheck').addEventListener('change', function() {
    document.getElementById('recurrenceField').style.display = this.checked ? 'block' : 'none';
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
