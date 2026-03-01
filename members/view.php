<?php
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Member.php';

requireAdmin();

$id     = (int)($_GET['id'] ?? 0);
$memberObj = new Member();
$m      = $memberObj->getById($id);

if (!$m) { flashError('Member not found.'); redirect('members/'); }

$pageTitle = $m['full_name'];

// Payment history
$db       = Database::getInstance();
$payments = $db->fetchAll(
    "SELECT p.*, d.title AS due_title FROM payments p
     JOIN dues d ON d.id = p.due_id
     WHERE p.member_id = ? ORDER BY p.created_at DESC LIMIT 10",
    [$id]
);

// Event RSVPs
$rsvps = $db->fetchAll(
    "SELECT e.title, e.start_date, er.response
     FROM event_rsvps er JOIN events e ON e.id = er.event_id
     WHERE er.user_id = ? ORDER BY e.start_date DESC LIMIT 5",
    [$m['user_id']]
);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<!-- Header -->
<div class="content-header d-flex justify-content-between flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        <div class="profile-big-avatar"><?php echo e(getInitials($m['full_name'])); ?></div>
        <div>
            <div class="breadcrumb-trail mb-1 text-muted" style="font-size:var(--font-size-sm)">
                <a href="<?php echo BASE_URL; ?>members/" class="text-muted text-decoration-none">Members</a>
                <span class="mx-2">/</span> Profile
            </div>
            <h1 class="content-title mb-1"><?php echo e($m['full_name']); ?></h1>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <code class="member-id-code"><?php echo e($m['member_id']); ?></code>
                <?php echo statusBadge($m['status']); ?>
                <?php if ($m['position']): ?>
                <span class="badge badge-info"><?php echo e($m['position']); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo BASE_URL; ?>members/edit.php?id=<?php echo $id; ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-edit"></i> Edit
        </a>
        <a href="<?php echo BASE_URL; ?>members/" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="row g-4">

    <!-- Left: Details -->
    <div class="col-12 col-lg-4">

        <!-- Contact info -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title"><i class="fas fa-address-card me-2 text-primary"></i>Details</h6>
            </div>
            <div class="card-body p-0">
                <ul class="detail-list">
                    <li><span class="dl-label">Email</span>
                        <a href="mailto:<?php echo e($m['email']); ?>" class="dl-value">
                            <?php echo e($m['email']); ?>
                        </a>
                    </li>
                    <li><span class="dl-label">Phone</span>
                        <span class="dl-value"><?php echo e($m['phone'] ?: '—'); ?></span>
                    </li>
                    <li><span class="dl-label">Date of Birth</span>
                        <span class="dl-value">
                            <?php echo $m['date_of_birth']
                                ? formatDate($m['date_of_birth'], 'd M Y')
                                  . ' <small class="text-muted">(' . (date('Y') - date('Y', strtotime($m['date_of_birth']))) . ' yrs)</small>'
                                : '—';
                            ?>
                        </span>
                    </li>
                    <li><span class="dl-label">Position</span>
                        <span class="dl-value"><?php echo e($m['position'] ?: '—'); ?></span>
                    </li>
                    <li><span class="dl-label">Joined</span>
                        <span class="dl-value"><?php echo $m['joined_at'] ? formatDate($m['joined_at'], 'd M Y') : '—'; ?></span>
                    </li>
                    <li><span class="dl-label">Last Login</span>
                        <span class="dl-value"><?php echo $m['last_login_at'] ? formatDate($m['last_login_at'], 'd M Y, g:i A') : 'Never'; ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Emergency & address -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title"><i class="fas fa-heartbeat me-2 text-danger"></i>Emergency & Address</h6>
            </div>
            <div class="card-body p-0">
                <ul class="detail-list">
                    <li><span class="dl-label">Emergency</span>
                        <span class="dl-value"><?php echo e($m['emergency_contact'] ?: '—'); ?></span>
                    </li>
                    <li><span class="dl-label">Address</span>
                        <span class="dl-value"><?php echo nl2br(e($m['address'] ?: '—')); ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Actions -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title"><i class="fas fa-cog me-2 text-secondary"></i>Actions</h6>
            </div>
            <div class="card-body d-flex flex-column gap-2">
                <?php if ($m['status'] !== 'active'): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>members/actions.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button class="btn btn-success w-100"><i class="fas fa-user-check me-2"></i>Activate</button>
                </form>
                <?php endif; ?>

                <?php if ($m['status'] === 'active'): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>members/actions.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="suspend">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button class="btn btn-warning w-100"><i class="fas fa-user-lock me-2"></i>Suspend</button>
                </form>
                <?php endif; ?>

                <?php if ($m['status'] !== 'inactive'): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>members/actions.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button class="btn btn-secondary w-100"><i class="fas fa-user-times me-2"></i>Deactivate</button>
                </form>
                <?php endif; ?>

                <?php if (isSuperAdmin()): ?>
                <form method="POST" action="<?php echo BASE_URL; ?>members/actions.php"
                      onsubmit="return confirm('Delete <?php echo e($m['full_name']); ?>? This cannot be undone.')">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button class="btn btn-danger w-100"><i class="fas fa-trash me-2"></i>Delete Member</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right: Payment history + RSVPs -->
    <div class="col-12 col-lg-8">

        <!-- Payment history -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="card-title"><i class="fas fa-receipt me-2 text-success"></i>Payment History</h6>
                <a href="<?php echo BASE_URL; ?>payments/?member=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Due</th><th>Amount</th><th>Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No payment records.</td></tr>
                        <?php else: foreach ($payments as $p): ?>
                        <tr>
                            <td class="fw-medium"><?php echo e($p['due_title']); ?></td>
                            <td><?php echo formatCurrency($p['amount']); ?></td>
                            <td class="text-muted"><?php echo $p['payment_date'] ? formatDate($p['payment_date'], 'd M Y') : '—'; ?></td>
                            <td><?php echo statusBadge($p['status']); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Event RSVPs -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title"><i class="fas fa-calendar-check me-2 text-info"></i>Recent Event RSVPs</h6>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Event</th><th>Date</th><th>Response</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rsvps)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">No RSVPs yet.</td></tr>
                        <?php else: foreach ($rsvps as $r): ?>
                        <tr>
                            <td class="fw-medium"><?php echo e($r['title']); ?></td>
                            <td class="text-muted"><?php echo formatDate($r['start_date'], 'd M Y'); ?></td>
                            <td>
                                <?php
                                $rsvpBadge = [
                                    'attending'     => 'badge-success',
                                    'not_attending' => 'badge-danger',
                                    'maybe'         => 'badge-warning',
                                ];
                                $cls = $rsvpBadge[$r['response']] ?? 'badge-secondary';
                                echo '<span class="badge ' . $cls . '">' . e(str_replace('_', ' ', ucfirst($r['response']))) . '</span>';
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.profile-big-avatar {
    width:72px; height:72px; flex-shrink:0;
    border-radius:var(--border-radius-full);
    background:linear-gradient(135deg,var(--primary),var(--primary-700));
    color:#fff; display:flex; align-items:center; justify-content:center;
    font-size:1.75rem; font-weight:var(--font-weight-bold);
}
.member-id-code { background:var(--bg-secondary); color:var(--primary); padding:2px 8px; border-radius:var(--border-radius-sm); font-size:var(--font-size-xs); font-family:var(--font-family-mono); }

.detail-list { list-style:none; padding:0; margin:0; }
.detail-list li { display:flex; justify-content:space-between; align-items:flex-start; padding:var(--spacing-3) var(--spacing-5); border-bottom:1px solid var(--border-light); gap:var(--spacing-4); }
.detail-list li:last-child { border-bottom:none; }
.dl-label { font-size:var(--font-size-xs); color:var(--text-muted); font-weight:var(--font-weight-medium); white-space:nowrap; min-width:90px; }
.dl-value { font-size:var(--font-size-sm); color:var(--text-primary); text-align:right; }
</style>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
