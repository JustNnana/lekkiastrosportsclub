<?php
/**
 * Single Event — detail + RSVP
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Event.php';

requireLogin();

$id       = (int)($_GET['id'] ?? 0);
$eventObj = new Event();
$event    = $eventObj->getById($id);

if (!$event) { flashError('Event not found.'); redirect('events/index.php'); }

$userId   = (int)$_SESSION['user_id'];
$userRsvp = $eventObj->getUserRsvp($id, $userId);
$rsvpList = $eventObj->getRsvpList($id);
$isActive = $event['status'] === 'active';

$pageTitle  = e($event['title']);
$typeColors = ['training'=>'#3b82f6','match'=>'#ef4444','meeting'=>'#f59e0b','social'=>'#8b5cf6','other'=>'#6b7280'];
$typeLabels = ['training'=>'Training','match'=>'Match','meeting'=>'Meeting','social'=>'Social','other'=>'Other'];
$color      = $typeColors[$event['event_type']] ?? '#6b7280';

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <nav class="mb-1"><a href="<?php echo BASE_URL; ?>events/index.php" class="text-muted small">← Events</a></nav>
        <h1 class="content-title"><?php echo e($event['title']); ?></h1>
        <p class="content-subtitle">
            <span class="badge me-1" style="background:<?php echo $color; ?>20;color:<?php echo $color; ?>"><?php echo $typeLabels[$event['event_type']] ?? $event['event_type']; ?></span>
            <?php echo statusBadge($event['status']); ?>
            <?php if ($event['is_recurring']): ?><span class="badge badge-info ms-1"><i class="fas fa-redo me-1"></i>Recurring</span><?php endif; ?>
        </p>
    </div>
    <?php if (isAdmin() && $isActive): ?>
    <div class="d-flex gap-2">
        <a href="<?php echo BASE_URL; ?>events/form.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
        <form method="POST" action="<?php echo BASE_URL; ?>events/actions.php" class="d-inline">
            <?php echo csrfField(); ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?php echo $id; ?>">
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-ban me-1"></i>Cancel</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-8">
        <!-- Event info card -->
        <div class="card mb-4" style="border-top:4px solid <?php echo $color; ?>">
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="text-muted small mb-1">Start</div>
                        <div class="fw-semibold"><?php echo formatDate($event['start_date'], 'd M Y'); ?></div>
                        <div class="text-muted small"><?php echo formatDate($event['start_date'], 'g:i A'); ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small mb-1">End</div>
                        <?php if ($event['end_date']): ?>
                        <div class="fw-semibold"><?php echo formatDate($event['end_date'], 'd M Y'); ?></div>
                        <div class="text-muted small"><?php echo formatDate($event['end_date'], 'g:i A'); ?></div>
                        <?php else: ?><div class="text-muted">—</div><?php endif; ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small mb-1">Location</div>
                        <div class="fw-semibold"><?php echo $event['location'] ? e($event['location']) : '—'; ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted small mb-1">Organised by</div>
                        <div class="fw-semibold"><?php echo e($event['creator_name']); ?></div>
                    </div>
                </div>
                <?php if ($event['description']): ?>
                <hr>
                <div style="line-height:1.7;white-space:pre-line"><?php echo e($event['description']); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RSVP section -->
        <?php if ($isActive): ?>
        <div class="card mb-4">
            <div class="card-header"><h6 class="card-title mb-0"><i class="fas fa-user-check me-2"></i>Your RSVP</h6></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2" id="rsvpBar">
                    <?php foreach (['attending'=>['✓ Attending','#16a34a'],'maybe'=>['? Maybe','#ca8a04'],'not_attending'=>['✗ Not Attending','#dc2626']] as $resp=>[$label,$clr]): ?>
                    <button class="btn-rsvp <?php echo $userRsvp===$resp?'active':''; ?>"
                            style="--rsvp-color:<?php echo $clr; ?>"
                            data-response="<?php echo $resp; ?>"
                            data-event="<?php echo $id; ?>">
                        <?php echo $label; ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <div id="rsvpMsg" class="mt-2 small text-muted"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- RSVP list -->
        <?php if (!empty($rsvpList)): ?>
        <div class="card">
            <div class="card-header"><h6 class="card-title mb-0"><i class="fas fa-users me-2"></i>Responses (<?php echo count($rsvpList); ?>)</h6></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Member</th><th>Response</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($rsvpList as $r):
                            $rc = ['attending'=>['#16a34a','✓ Attending'],'maybe'=>['#ca8a04','? Maybe'],'not_attending'=>['#dc2626','✗ Not Attending']][$r['response']] ?? ['#6b7280',$r['response']];
                        ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e($r['member_name'] ?? '—'); ?></td>
                            <td><span style="color:<?php echo $rc[0]; ?>;font-weight:600"><?php echo $rc[1]; ?></span></td>
                            <td class="text-muted small"><?php echo formatDate($r['created_at'], 'd M Y'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar summary -->
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header"><h6 class="card-title mb-0">Attendance</h6></div>
            <div class="card-body">
                <?php
                $total  = $event['attending_count'] + $event['maybe_count'] + $event['not_attending_count'];
                foreach ([['attending','Going','#16a34a'],['maybe','Maybe','#ca8a04'],['not_attending','Not Going','#dc2626']] as [$key,$lbl,$clr]):
                    $cnt = (int)$event[$key.'_count'];
                    $pct = $total > 0 ? round($cnt/$total*100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span style="color:<?php echo $clr; ?>;font-weight:600;font-size:13px"><?php echo $lbl; ?></span>
                        <span class="fw-semibold"><?php echo $cnt; ?></span>
                    </div>
                    <div class="progress" style="height:6px;border-radius:4px;background:var(--surface-2)">
                        <div style="width:<?php echo $pct; ?>%;height:100%;border-radius:4px;background:<?php echo $clr; ?>"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
.btn-rsvp {
    padding: 10px 18px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500;
    border: 1.5px solid var(--border-color); background: var(--surface-2); color: var(--text-secondary);
    transition: all .15s;
}
.btn-rsvp:hover  { border-color: var(--rsvp-color); color: var(--rsvp-color); }
.btn-rsvp.active { border-color: var(--rsvp-color); background: color-mix(in srgb, var(--rsvp-color) 12%, transparent); color: var(--rsvp-color); font-weight: 700; }
</style>

<script>
document.querySelectorAll('.btn-rsvp').forEach(btn => {
    btn.addEventListener('click', function() {
        this.disabled = true;
        fetch('<?php echo BASE_URL; ?>api/event-rsvp.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({event_id: this.dataset.event, response: this.dataset.response})
        })
        .then(r => r.json())
        .then(data => {
            this.disabled = false;
            if (!data.success) { document.getElementById('rsvpMsg').textContent = data.message; return; }
            document.querySelectorAll('.btn-rsvp').forEach(b => b.classList.remove('active'));
            if (data.status !== 'removed') this.classList.add('active');
            document.getElementById('rsvpMsg').textContent = data.message;
            setTimeout(() => { document.getElementById('rsvpMsg').textContent = ''; }, 3000);
        });
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
