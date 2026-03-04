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
            redirect("events/view.php?id=$id");
        } else {
            $newId = $eventObj->create($data);
            flashSuccess('Event created.');

            // Notify all members (push + in-app + email)
            try {
                require_once dirname(__DIR__) . '/classes/PushService.php';
                require_once dirname(__DIR__) . '/app/mail/emails.php';
                $dateStr  = date('d M Y', strtotime($start_date));
                $pushBody = "A new event has been scheduled: {$title} on {$dateStr}" . ($location ? " at {$location}" : '') . '.';
                $notifUrl = BASE_URL . "events/view.php?id={$newId}";
                $push = new PushService();
                $push->notifyAll('event', 'New Event: ' . $title, $pushBody, $notifUrl);

                $db      = Database::getInstance();
                $members = $db->fetchAll("SELECT full_name, email FROM users WHERE status = 'active' AND role = 'user'");
                $emailMsg = "<p>A new club event has been scheduled:</p>
                    <table style='background:#f4f6f8;border-radius:8px;padding:20px;width:100%;margin:16px 0;border-collapse:collapse;'>
                        <tr><td style='padding:8px 0;color:#637381;width:120px;'>Event</td>
                            <td style='padding:8px 0;font-weight:700;color:#1c252e;'>" . htmlspecialchars($title) . "</td></tr>
                        <tr><td style='padding:8px 0;color:#637381;'>Date</td>
                            <td style='padding:8px 0;font-weight:700;color:#1c252e;'>{$dateStr}</td></tr>"
                    . ($location ? "<tr><td style='padding:8px 0;color:#637381;'>Location</td>
                            <td style='padding:8px 0;font-weight:700;color:#1c252e;'>" . htmlspecialchars($location) . "</td></tr>" : '')
                    . "</table>";
                foreach ($members as $m) {
                    sendNotificationEmail($m['email'], $m['full_name'], 'New Event: ' . $title, 'New Event Scheduled', $emailMsg, $notifUrl, 'View Event');
                }
            } catch (Throwable $e) {
                error_log('Event notification failed: ' . $e->getMessage());
            }

            redirect("events/view.php?id=$newId");
        }
    }
}

$currentType  = $event['event_type'] ?? ($_POST['event_type'] ?? 'other');
$isRecurring  = (bool)($event['is_recurring'] ?? 0);

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

/* ── Event type selector ── */
.ios-type-grid {
    display: grid; grid-template-columns: repeat(5, 1fr);
    gap: 8px;
}
@media (max-width: 600px) { .ios-type-grid { grid-template-columns: repeat(3, 1fr); } }
.ios-type-chip {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 6px; padding: 12px 6px; border-radius: 12px;
    border: 1.5px solid var(--border-color); background: var(--bg-secondary);
    cursor: pointer; user-select: none; transition: all .15s;
    text-align: center;
}
.ios-type-chip:hover { border-color: var(--primary); background: rgba(var(--primary-rgb),.04); }
.ios-type-chip.selected { border-color: var(--primary); background: rgba(var(--primary-rgb),.08); }
.ios-type-chip i { font-size: 18px; }
.ios-type-chip span { font-size: 11px; font-weight: 600; color: var(--text-secondary); }
.ios-type-chip.selected span { color: var(--primary); }
.ios-type-chip.selected i { color: var(--primary); }
.ios-type-chip input { display: none; }

/* ── iOS Toggle ── */
.ios-toggle-row {
    display: flex; align-items: flex-start;
    justify-content: space-between;
    padding: var(--spacing-4) var(--spacing-5);
    gap: var(--spacing-4);
    border-top: 1px solid var(--border-color);
}
.ios-toggle-info { flex: 1; }
.ios-toggle-info strong { display: block; font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
.ios-toggle-info small  { font-size: 12px; color: var(--text-secondary); }
.ios-toggle { position: relative; flex-shrink: 0; }
.ios-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.ios-toggle-track {
    width: 51px; height: 31px;
    background: var(--bg-secondary); border: 2px solid var(--border-color);
    border-radius: 100px; cursor: pointer;
    display: block; position: relative;
    transition: background .25s ease, border-color .25s ease;
}
.ios-toggle input:checked ~ .ios-toggle-track {
    background: var(--ios-green); border-color: var(--ios-green);
}
.ios-toggle-thumb {
    position: absolute; top: 2px; left: 2px;
    width: 23px; height: 23px; border-radius: 50%;
    background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,.2);
    transition: transform .25s cubic-bezier(.4,0,.2,1);
}
.ios-toggle input:checked ~ .ios-toggle-track .ios-toggle-thumb {
    transform: translateX(20px);
}

/* ── Recurrence panel (animated) ── */
.ios-recurrence-panel {
    overflow: hidden;
    max-height: 0;
    transition: max-height .3s ease, opacity .3s ease;
    opacity: 0;
}
.ios-recurrence-panel.open {
    max-height: 120px;
    opacity: 1;
}
.ios-recurrence-inner {
    padding: var(--spacing-4) var(--spacing-5);
    border-top: 1px solid var(--border-color);
}

/* ── Sidebar ── */
.ios-sidebar { display: flex; flex-direction: column; }
.ios-sidebar-sticky { position: sticky; top: var(--spacing-4); }

.ios-tip-list { padding: var(--spacing-4) var(--spacing-5); display: flex; flex-direction: column; gap: var(--spacing-3); }
.ios-tip-item { display: flex; gap: 10px; font-size: 13px; }
.ios-tip-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.ios-tip-text { color: var(--text-secondary); line-height: 1.5; }
.ios-tip-text strong { color: var(--text-primary); }

.ios-admin-actions { padding: var(--spacing-4); display: flex; flex-direction: column; gap: var(--spacing-3); }

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
    .ios-toggle-row { padding: var(--spacing-4); }
    .ios-recurrence-inner { padding: var(--spacing-4); }
}
</style>

<!-- ===== DESKTOP HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-start">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>events/manage.php" class="breadcrumb-link">Events</a>
                </li>
                <li class="breadcrumb-item active"><?php echo $isEdit ? 'Edit' : 'New Event'; ?></li>
            </ol>
        </nav>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Event' : 'New Event'; ?></h1>
        <p class="content-subtitle"><?php echo $isEdit ? 'Update the event details below.' : 'Fill in the details to create a new club event.'; ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>events/manage.php" class="btn btn-secondary flex-shrink-0">
        <i class="fas fa-arrow-left me-2"></i>Back to Events
    </a>
</div>

<!-- ===== MOBILE HEADER ===== -->
<div class="ios-section-header d-md-none mb-3" style="border-radius:14px;border:1px solid var(--border-color)">
    <div class="ios-section-icon blue">
        <i class="fas fa-<?php echo $isEdit ? 'edit' : 'calendar-plus'; ?>"></i>
    </div>
    <div class="ios-section-title">
        <h5><?php echo $isEdit ? 'Edit Event' : 'New Event'; ?></h5>
        <p><?php echo $isEdit ? 'Update event details' : 'Create a club event'; ?></p>
    </div>
    <button class="ios-menu-open-btn" onclick="openIosMenu()" style="width:36px;height:36px;border-radius:50%;background:var(--bg-secondary);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0">
        <i class="fas fa-ellipsis-v" style="color:var(--text-primary);font-size:16px"></i>
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4" style="max-width:1100px;margin-left:auto;margin-right:auto;border-radius:12px">
    <i class="fas fa-exclamation-circle me-2"></i>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="eventForm">
    <?php echo csrfField(); ?>
    <!-- hidden field for event_type driven by chip selection -->
    <input type="hidden" name="event_type" id="eventTypeInput" value="<?php echo e($currentType); ?>">

    <div class="form-container">

        <!-- ===== LEFT ===== -->
        <div>

            <!-- Event Details card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Event Details</h5>
                        <p>Title, type and location</p>
                    </div>
                </div>
                <div class="ios-section-body">
                    <div class="ios-form-group">
                        <label class="ios-form-label">Title <span class="req">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="200"
                               style="border-radius:10px"
                               value="<?php echo e($event['title'] ?? ($_POST['title'] ?? '')); ?>"
                               placeholder="e.g. Sunday Training Session">
                    </div>

                    <div class="ios-form-group">
                        <label class="ios-form-label">Description <span class="opt">(optional)</span></label>
                        <textarea name="description" class="form-control" rows="3"
                                  style="border-radius:10px;resize:none"
                                  placeholder="Agenda, instructions, notes for members…"><?php echo e($event['description'] ?? ($_POST['description'] ?? '')); ?></textarea>
                    </div>

                    <div class="ios-form-group">
                        <label class="ios-form-label">Event Type <span class="req">*</span></label>
                        <?php
                        $typeMap = [
                            'training' => ['icon' => 'fa-dumbbell',   'label' => 'Training', 'color' => 'var(--ios-green)'],
                            'match'    => ['icon' => 'fa-futbol',     'label' => 'Match',    'color' => 'var(--ios-blue)'],
                            'meeting'  => ['icon' => 'fa-handshake',  'label' => 'Meeting',  'color' => 'var(--ios-purple)'],
                            'social'   => ['icon' => 'fa-users',      'label' => 'Social',   'color' => 'var(--ios-orange)'],
                            'other'    => ['icon' => 'fa-calendar',   'label' => 'Other',    'color' => 'var(--text-muted)'],
                        ];
                        ?>
                        <div class="ios-type-grid">
                            <?php foreach ($typeMap as $val => $meta): ?>
                            <div class="ios-type-chip <?php echo $currentType === $val ? 'selected' : ''; ?>"
                                 data-type="<?php echo $val; ?>"
                                 style="<?php echo $currentType === $val ? 'border-color:var(--primary)' : ''; ?>">
                                <i class="fas <?php echo $meta['icon']; ?>"
                                   style="color:<?php echo $currentType === $val ? 'var(--primary)' : $meta['color']; ?>"></i>
                                <span><?php echo $meta['label']; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="ios-form-group">
                        <label class="ios-form-label">Location <span class="opt">(optional)</span></label>
                        <div style="position:relative">
                            <i class="fas fa-map-marker-alt" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:14px;pointer-events:none"></i>
                            <input type="text" name="location" class="form-control" maxlength="200"
                                   style="border-radius:10px;padding-left:34px"
                                   value="<?php echo e($event['location'] ?? ($_POST['location'] ?? '')); ?>"
                                   placeholder="Venue or address">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Schedule card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Schedule</h5>
                        <p>Start time, end time and recurrence</p>
                    </div>
                </div>
                <div class="ios-section-body">
                    <div class="ios-form-grid">
                        <div class="ios-form-group">
                            <label class="ios-form-label">Start Date &amp; Time <span class="req">*</span></label>
                            <input type="datetime-local" name="start_date" class="form-control" required
                                   style="border-radius:10px"
                                   value="<?php echo $event ? date('Y-m-d\TH:i', strtotime($event['start_date'])) : ($_POST['start_date'] ?? ''); ?>">
                        </div>
                        <div class="ios-form-group">
                            <label class="ios-form-label">End Date &amp; Time <span class="opt">(optional)</span></label>
                            <input type="datetime-local" name="end_date" class="form-control"
                                   style="border-radius:10px"
                                   value="<?php echo ($event && $event['end_date']) ? date('Y-m-d\TH:i', strtotime($event['end_date'])) : ($_POST['end_date'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <!-- Recurring toggle -->
                <div class="ios-toggle-row">
                    <div class="ios-toggle-info">
                        <strong>Recurring event</strong>
                        <small>This event repeats on a regular schedule</small>
                    </div>
                    <label class="ios-toggle">
                        <input type="checkbox" name="is_recurring" id="recurringCheck" value="1"
                               <?php echo $isRecurring ? 'checked' : ''; ?>>
                        <span class="ios-toggle-track">
                            <span class="ios-toggle-thumb"></span>
                        </span>
                    </label>
                </div>

                <!-- Recurrence select (slides open) -->
                <div class="ios-recurrence-panel <?php echo $isRecurring ? 'open' : ''; ?>" id="recurrencePanel">
                    <div class="ios-recurrence-inner">
                        <label class="ios-form-label">Recurrence Pattern</label>
                        <select name="recurrence" class="form-control" style="border-radius:10px;max-width:240px">
                            <option value="">— Select pattern —</option>
                            <?php foreach (['weekly' => 'Weekly', 'bi_weekly' => 'Bi-weekly', 'monthly' => 'Monthly'] as $v => $l): ?>
                            <option value="<?php echo $v; ?>" <?php echo ($event['recurrence'] ?? '') === $v ? 'selected' : ''; ?>>
                                <?php echo $l; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ===== MOBILE-ONLY: Submit ===== -->
            <div class="d-md-none" style="display:flex;flex-direction:column;gap:10px;margin-bottom:var(--spacing-4)">
                <button type="submit" class="btn btn-primary w-100" style="border-radius:12px;padding:14px">
                    <i class="fas fa-<?php echo $isEdit ? 'save' : 'paper-plane'; ?> me-2"></i>
                    <?php echo $isEdit ? 'Update Event' : 'Create Event'; ?>
                </button>
                <a href="<?php echo BASE_URL; ?>events/manage.php" class="btn btn-secondary w-100" style="border-radius:12px;padding:14px">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
            </div>

        </div><!-- /left -->

        <!-- ===== RIGHT: SIDEBAR ===== -->
        <div class="ios-sidebar">
            <div class="ios-sidebar-sticky">

                <!-- Actions card -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon <?php echo $isEdit ? 'orange' : 'green'; ?>">
                            <i class="fas fa-<?php echo $isEdit ? 'save' : 'check'; ?>"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5><?php echo $isEdit ? 'Save Changes' : 'Publish Event'; ?></h5>
                        </div>
                    </div>
                    <div class="ios-admin-actions">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-<?php echo $isEdit ? 'save' : 'paper-plane'; ?> me-2"></i>
                            <?php echo $isEdit ? 'Update Event' : 'Create Event'; ?>
                        </button>
                        <a href="<?php echo BASE_URL; ?>events/manage.php" class="btn btn-secondary w-100">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </div>

                <!-- Tips card (create only) -->
                <?php if (!$isEdit): ?>
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5>Event Tips</h5>
                        </div>
                    </div>
                    <div class="ios-tip-list">
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">📍</span>
                            <span class="ios-tip-text"><strong>Add a location</strong> so members know exactly where to go.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">⏰</span>
                            <span class="ios-tip-text"><strong>Set an end time</strong> to help members plan their schedule.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">🔁</span>
                            <span class="ios-tip-text"><strong>Use recurring</strong> for regular sessions like weekly training.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">📋</span>
                            <span class="ios-tip-text"><strong>Add a description</strong> with the agenda or any special instructions.</span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <!-- Edit info card -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue"><i class="fas fa-info-circle"></i></div>
                        <div class="ios-section-title"><h5>Event Info</h5></div>
                    </div>
                    <div style="padding:var(--spacing-4) var(--spacing-5)">
                        <div style="font-size:13px;color:var(--text-secondary);line-height:1.6">
                            <p class="mb-2">Changes will be visible to all members immediately.</p>
                            <p class="mb-0">RSVP responses from members will be preserved.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div><!-- /sidebar -->

    </div><!-- /form-container -->
</form>

<!-- ===== iOS MENU MODAL (mobile) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeIosMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h5 class="ios-menu-title"><?php echo $isEdit ? 'Edit Event' : 'New Event'; ?></h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>events/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-calendar-alt"></i></div>
                        <div class="ios-menu-item-label">Manage Events</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>events/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-label">Events Calendar</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-home"></i></div>
                        <div class="ios-menu-item-label">Dashboard</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
/* ── iOS menu ── */
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
    var modal = document.getElementById('iosMenuModal');
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

/* ── Event type chips ── */
document.querySelectorAll('.ios-type-chip').forEach(function(chip) {
    chip.addEventListener('click', function() {
        document.querySelectorAll('.ios-type-chip').forEach(function(c) {
            c.classList.remove('selected');
            c.style.borderColor = '';
            c.querySelector('i').style.color = '';
            c.querySelector('span').style.color = '';
        });
        this.classList.add('selected');
        this.style.borderColor = 'var(--primary)';
        this.querySelector('i').style.color = 'var(--primary)';
        this.querySelector('span').style.color = 'var(--primary)';
        document.getElementById('eventTypeInput').value = this.dataset.type;
    });
});

/* ── Recurring toggle ── */
var recurringCheck  = document.getElementById('recurringCheck');
var recurrencePanel = document.getElementById('recurrencePanel');

recurringCheck.addEventListener('change', function() {
    if (this.checked) {
        recurrencePanel.classList.add('open');
    } else {
        recurrencePanel.classList.remove('open');
    }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
