<?php
/**
 * My Profile — view & edit own profile
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireLogin();

$db     = Database::getInstance();
$userId = (int)$_SESSION['user_id'];

// Load user + member row
$profile = $db->fetchOne(
    "SELECT u.id, u.full_name, u.email, u.role, u.status, u.created_at, u.last_login_at,
            m.id AS member_db_id, m.member_id AS member_code, m.phone,
            m.date_of_birth, m.address, m.emergency_contact, m.position,
            m.joined_at, m.status AS member_status
     FROM users u
     LEFT JOIN members m ON m.user_id = u.id
     WHERE u.id = ?",
    [$userId]
);

$isMember  = !empty($profile['member_db_id']);
$pageTitle = 'My Profile';
$error     = '';
$pwError   = '';
$pwSuccess = '';

// ─── PROFILE SAVE ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    verifyCsrf();

    $fullName  = sanitize($_POST['full_name'] ?? '');
    $phone     = sanitize($_POST['phone'] ?? '');
    $dob       = sanitize($_POST['date_of_birth'] ?? '');
    $address   = sanitize($_POST['address'] ?? '');
    $emergency = sanitize($_POST['emergency_contact'] ?? '');
    $position  = sanitize($_POST['position'] ?? '');

    if (empty($fullName)) {
        $error = 'Full name is required.';
    } else {
        $db->execute("UPDATE users SET full_name = ?, updated_at = NOW() WHERE id = ?", [$fullName, $userId]);

        if ($isMember) {
            $db->execute(
                "UPDATE members SET phone=?,date_of_birth=?,address=?,emergency_contact=?,position=?,updated_at=NOW() WHERE id=?",
                [$phone ?: null, $dob ?: null, $address ?: null, $emergency ?: null, $position ?: null, (int)$profile['member_db_id']]
            );
        }

        $_SESSION['full_name'] = $fullName;
        flashSuccess('Profile updated successfully.');
        redirect('profile/');
    }

    // On error: prefill
    $profile['full_name']         = $fullName;
    $profile['phone']             = $phone;
    $profile['date_of_birth']     = $dob;
    $profile['address']           = $address;
    $profile['emergency_contact'] = $emergency;
    $profile['position']          = $position;
}

// ─── PASSWORD CHANGE ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verifyCsrf();

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $hash = $db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$userId])['password_hash'] ?? '';

    if (!password_verify($current, $hash)) {
        $pwError = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $pwError = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $pwError = 'New passwords do not match.';
    } else {
        $db->execute("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?",
            [password_hash($new, PASSWORD_DEFAULT), $userId]);
        $pwSuccess = 'Password changed successfully.';
    }
}

// ─── PAYMENT SUMMARY ─────────────────────────────────────────────────────────
$paymentSummary = null;
$recentPayments = [];
if ($isMember) {
    $paymentSummary = $db->fetchOne(
        "SELECT COUNT(*) AS total,
                SUM(status='paid')    AS paid,
                SUM(status='pending') AS pending,
                SUM(status='overdue') AS overdue,
                COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END),0) AS total_paid
         FROM payments WHERE member_id = ?",
        [(int)$profile['member_db_id']]
    );

    $recentPayments = $db->fetchAll(
        "SELECT p.amount, p.status, p.payment_date, d.title AS due_name
         FROM payments p JOIN dues d ON d.id = p.due_id
         WHERE p.member_id = ?
         ORDER BY p.created_at DESC LIMIT 5",
        [(int)$profile['member_db_id']]
    );
}

$positions = ['Goalkeeper','Defender','Midfielder','Forward','Winger','Striker','Captain','Vice-Captain','Other'];

// Auto-open tab on error
$activeTab = 'profile-info';
if ($pwError || $pwSuccess) $activeTab = 'change-password';
if ($isMember) $paymentTab = 'payments';

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
/* ── iOS Profile Page ─────────────────────────────── */
:root {
    --ios-red:    #FF453A;
    --ios-orange: #FF9F0A;
    --ios-green:  #30D158;
    --ios-blue:   #0A84FF;
    --ios-purple: #BF5AF2;
    --ios-teal:   #64D2FF;
}

/* Grid */
.ios-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: 20px;
    align-items: start;
}

/* Section card */
.ios-section-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    margin-bottom: 20px;
    overflow: hidden;
}

.ios-section-header {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 20px;
    background: var(--bg-subtle);
    border-bottom: 1px solid var(--border-color);
}

.ios-section-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; flex-shrink: 0;
}
.ios-section-icon.blue   { background: rgba(10,132,255,.15);  color: var(--ios-blue); }
.ios-section-icon.green  { background: rgba(48,209,88,.15);   color: var(--ios-green); }
.ios-section-icon.orange { background: rgba(255,159,10,.15);  color: var(--ios-orange); }
.ios-section-icon.purple { background: rgba(191,90,242,.15);  color: var(--ios-purple); }
.ios-section-icon.red    { background: rgba(255,69,58,.15);   color: var(--ios-red); }

.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0 0 4px; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 0; }

.ios-section-body          { padding: 20px; }
.ios-section-body.no-pad   { padding: 0; }
.ios-section-body.border-t { border-top: 1px solid var(--border-color); }

/* 3-dot button (mobile only) */
.ios-options-btn {
    display: none;
    width: 36px; height: 36px;
    border-radius: 50%;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    align-items: center; justify-content: center;
    cursor: pointer; transition: background .2s;
    margin-left: auto; flex-shrink: 0;
}
.ios-options-btn:hover { background: var(--border-color); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* Avatar */
.ios-profile-avatar {
    width: 90px; height: 90px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, var(--primary), var(--primary-700));
    color: #fff; font-size: 2.2rem; font-weight: 700;
    margin: 0 auto 14px;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
}

.ios-profile-name     { font-size: 20px; font-weight: 700; color: var(--text-primary); text-align: center; margin: 0 0 4px; }
.ios-profile-sub      { font-size: 13px; color: var(--text-secondary); text-align: center; margin: 0 0 14px; }
.ios-profile-badges   { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }

.ios-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 20px;
    font-size: 12px; font-weight: 600;
}
.ios-badge.role   { background: rgba(10,132,255,.12); color: var(--ios-blue); }
.ios-badge.active { background: rgba(48,209,88,.12);  color: var(--ios-green); }
.ios-badge.inactive,.ios-badge.suspended { background: rgba(255,69,58,.12); color: var(--ios-red); }
.ios-badge.pending  { background: rgba(255,159,10,.12); color: var(--ios-orange); }
.ios-badge-dot { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }

/* Info list */
.ios-info-list { list-style: none; padding: 0; margin: 0; }
.ios-info-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 13px 0; border-bottom: 1px solid var(--border-color);
}
.ios-info-item:last-child { border-bottom: none; }
.ios-info-label {
    font-size: 14px; color: var(--text-secondary);
    display: flex; align-items: center; gap: 10px;
}
.ios-info-label i { width: 18px; text-align: center; }
.icon-blue   { color: var(--ios-blue) !important; }
.icon-green  { color: var(--ios-green) !important; }
.icon-orange { color: var(--ios-orange) !important; }
.icon-purple { color: var(--ios-purple) !important; }
.icon-teal   { color: var(--ios-teal) !important; }
.icon-red    { color: var(--ios-red) !important; }

.ios-info-value {
    font-size: 14px; font-weight: 500; color: var(--text-primary);
    text-align: right; max-width: 58%; word-break: break-word;
}

/* Tabs */
.ios-tabs {
    display: flex; background: var(--bg-secondary);
    border-radius: 10px; padding: 4px;
    margin-bottom: 20px; overflow-x: auto;
    -webkit-overflow-scrolling: touch; gap: 2px;
}
.ios-tab-btn {
    flex: 1; min-width: max-content;
    padding: 9px 14px; border: none;
    background: transparent; border-radius: 8px;
    font-size: 13px; font-weight: 600;
    color: var(--text-secondary); cursor: pointer; transition: all .2s;
    white-space: nowrap;
}
.ios-tab-btn.active {
    background: var(--bg-primary); color: var(--text-primary);
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.ios-tab-content        { display: none; }
.ios-tab-content.active { display: block; animation: fadeIn .25s ease; }
@keyframes fadeIn { from { opacity:0; transform: translateY(-4px); } to { opacity:1; transform: translateY(0); } }

/* Flash */
.ios-flash {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px; border-radius: 12px; margin-bottom: 18px;
}
.ios-flash.success { background: rgba(48,209,88,.1); border: 1px solid rgba(48,209,88,.25); }
.ios-flash.error   { background: rgba(255,69,58,.1); border: 1px solid rgba(255,69,58,.25); }
.ios-flash-icon {
    width: 24px; height: 24px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 11px; color: #fff;
}
.ios-flash.success .ios-flash-icon { background: var(--ios-green); }
.ios-flash.error   .ios-flash-icon { background: var(--ios-red); }
.ios-flash-title { font-size: 13px; font-weight: 600; margin: 0 0 2px; }
.ios-flash.success .ios-flash-title { color: var(--ios-green); }
.ios-flash.error   .ios-flash-title { color: var(--ios-red); }
.ios-flash-text  { font-size: 13px; color: var(--text-secondary); margin: 0; }

/* Form */
.ios-form-group { margin-bottom: 18px; }
.ios-form-label {
    display: block; font-size: 12px; font-weight: 600;
    color: var(--text-secondary); text-transform: uppercase;
    letter-spacing: .5px; margin-bottom: 7px;
}
.ios-form-input {
    width: 100%; padding: 13px 15px;
    border: 1px solid var(--border-color); border-radius: 12px;
    background: var(--bg-secondary); font-size: 15px;
    color: var(--text-primary); transition: all .2s;
    font-family: inherit; box-sizing: border-box;
}
.ios-form-input:focus { outline: none; border-color: var(--ios-blue); box-shadow: 0 0 0 3px rgba(10,132,255,.12); }
.ios-form-input:read-only,
.ios-form-input:disabled { opacity: .6; cursor: not-allowed; }
.ios-form-hint { font-size: 12px; color: var(--text-muted); margin-top: 5px; }
.ios-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

/* Buttons */
.ios-btn {
    display: inline-flex; align-items: center; justify-content: center;
    gap: 8px; padding: 13px 22px; border-radius: 12px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    transition: all .2s; border: none; font-family: inherit;
}
.ios-btn.primary { background: var(--primary); color: #fff; }
.ios-btn.primary:hover { filter: brightness(1.08); }
.ios-btn:active { transform: scale(.98); }
.ios-btn.full   { width: 100%; }

/* Payment stats */
.ios-pay-stats {
    display: grid; grid-template-columns: repeat(3,1fr);
    gap: 12px; margin-bottom: 16px;
}
.ios-pay-stat {
    background: var(--bg-secondary); border-radius: 12px;
    padding: 14px 10px; text-align: center;
}
.ios-pay-stat-value { font-size: 20px; font-weight: 700; margin-bottom: 2px; }
.ios-pay-stat-value.green  { color: var(--ios-green); }
.ios-pay-stat-value.orange { color: var(--ios-orange); }
.ios-pay-stat-value.red    { color: var(--ios-red); }
.ios-pay-stat-label { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .5px; }

.ios-pay-total {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 14px; background: var(--bg-secondary); border-radius: 12px; margin-bottom: 16px;
}
.ios-pay-total-label { font-size: 13px; color: var(--text-secondary); }
.ios-pay-total-value { font-size: 16px; font-weight: 700; color: var(--ios-green); }

/* Payment list */
.ios-pay-list { list-style: none; padding: 0; margin: 0; }
.ios-pay-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 0; border-bottom: 1px solid var(--border-color);
}
.ios-pay-row:last-child { border-bottom: none; }
.ios-pay-name { font-size: 14px; font-weight: 500; color: var(--text-primary); }
.ios-pay-date { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
.ios-pay-right { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.ios-pay-amount { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.ios-status-pill {
    font-size: 10px; font-weight: 600; padding: 3px 8px; border-radius: 20px; text-transform: capitalize;
}
.pill-paid    { background: rgba(48,209,88,.12);  color: var(--ios-green); }
.pill-pending { background: rgba(255,159,10,.12); color: var(--ios-orange); }
.pill-overdue { background: rgba(255,69,58,.12);  color: var(--ios-red); }

/* Mobile bottom-sheet menu */
.ios-menu-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.4); backdrop-filter: blur(4px);
    z-index: 9998; opacity: 0; visibility: hidden; transition: .3s;
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
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 10px auto 4px; flex-shrink: 0; }
.ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 14px; border-bottom: 1px solid var(--border-color); }
.ios-menu-title  { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close  { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; }
.ios-menu-section { margin-bottom: 18px; }
.ios-menu-section-title { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; padding-left: 4px; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-stat-row { display: flex; justify-content: space-between; padding: 12px 16px; border-bottom: 1px solid var(--border-color); }
.ios-menu-stat-row:last-child { border-bottom: none; }
.ios-menu-stat-label { font-size: 14px; color: var(--text-secondary); }
.ios-menu-stat-value { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.ios-menu-item {
    display: flex; align-items: center; justify-content: space-between;
    padding: 13px 16px; border-bottom: 1px solid var(--border-color);
    text-decoration: none; color: var(--text-primary); transition: background .15s; cursor: pointer;
}
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; }
.ios-menu-item-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; color: #fff;
}
.ios-menu-item-icon.blue   { background: var(--ios-blue); }
.ios-menu-item-icon.green  { background: var(--ios-green); }
.ios-menu-item-icon.orange { background: var(--ios-orange); }
.ios-menu-item-icon.purple { background: var(--ios-purple); }
.ios-menu-item-icon.red    { background: var(--ios-red); }
.ios-menu-item-label { font-size: 14px; font-weight: 500; }
.ios-menu-item-chevron { color: var(--text-secondary); font-size: 11px; }

/* ── iOS Toggle Switch ──────────────── */
.ios-switch { position: relative; display: inline-block; width: 51px; height: 31px; }
.ios-switch input { opacity: 0; width: 0; height: 0; }
.ios-switch-track {
    position: absolute; inset: 0;
    background: var(--border-color); border-radius: 31px;
    cursor: pointer; transition: background .3s;
}
.ios-switch input:checked + .ios-switch-track { background: var(--ios-green); }
.ios-switch-track::before {
    content: ''; position: absolute;
    width: 27px; height: 27px; border-radius: 50%;
    background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,.22);
    left: 2px; top: 2px;
    transition: transform .3s cubic-bezier(.35,.82,.29,.97);
}
.ios-switch input:checked + .ios-switch-track::before { transform: translateX(20px); }
.ios-switch input:disabled + .ios-switch-track { opacity: .5; cursor: not-allowed; }

/* ── Notification settings rows ─────── */
.ios-notif-list { background: var(--bg-secondary); border-radius: 14px; overflow: hidden; margin-bottom: 16px; }
.ios-notif-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px; border-bottom: 1px solid var(--border-color);
}
.ios-notif-row:last-child { border-bottom: none; }
.ios-notif-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-notif-icon {
    width: 36px; height: 36px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.ios-notif-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-notif-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-notif-text-title  { font-size: 15px; font-weight: 500; color: var(--text-primary); margin: 0; line-height: 1.3; }
.ios-notif-text-sub    { font-size: 12px; color: var(--text-muted); margin: 2px 0 0; }

/* ── Status indicator ───────────────── */
#notification-status {
    padding: 11px 14px; border-radius: 12px;
    font-size: 13px; margin-bottom: 14px;
    line-height: 1.4;
}

/* ── Responsive ─────────────────────── */
@media (max-width: 1100px) {
    .ios-grid { grid-template-columns: 300px 1fr; }
}
@media (max-width: 992px) {
    .ios-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .ios-options-btn { display: flex; }
    .ios-form-row { grid-template-columns: 1fr; }
    .ios-info-item { flex-direction: column; align-items: flex-start; gap: 3px; }
    .ios-info-value { text-align: left; max-width: 100%; }
}
@media (max-width: 480px) {
    .ios-section-header { padding: 14px; gap: 12px; }
    .ios-section-icon { width: 38px; height: 38px; font-size: 15px; }
    .ios-section-body { padding: 14px; }
    .ios-profile-avatar { width: 72px; height: 72px; font-size: 1.8rem; }
    .ios-profile-name { font-size: 17px; }
    .ios-btn.full { width: 100%; }
    .ios-pay-stats { grid-template-columns: repeat(3,1fr); gap: 8px; }
}
@media (max-width: 390px) {
    .ios-section-header { padding: 12px; gap: 10px; }
    .ios-section-icon { width: 34px; height: 34px; font-size: 14px; border-radius: 10px; }
    .ios-tab-btn { padding: 8px 10px; font-size: 11px; }
    .ios-tab-btn i { display: none; }
}
</style>

<!-- Desktop Page Header -->
<div class="content-header">
    <div>
        <h1 class="content-title">My Profile</h1>
        <p class="content-subtitle">View and update your personal information.</p>
    </div>
</div>

<?php foreach (getFlashMessages() as $f): ?>
<div class="alert alert-<?php echo $f['type'] === 'success' ? 'success' : ($f['type'] === 'error' ? 'danger' : $f['type']); ?> alert-dismissible">
    <?php echo e($f['message']); ?>
    <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
</div>
<?php endforeach; ?>

<!-- iOS Grid -->
<div class="ios-grid">

    <!-- ───── LEFT: Profile Info Card ───── -->
    <div>
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-icon purple">
                    <i class="fas fa-user"></i>
                </div>
                <div class="ios-section-title">
                    <h5>Profile</h5>
                    <p>Your account information</p>
                </div>
                <button class="ios-options-btn" id="iosOptionsBtn" aria-label="Options">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>

            <!-- Avatar + Name -->
            <div class="ios-section-body" style="text-align:center">
                <div class="ios-profile-avatar">
                    <?php echo e(getInitials($profile['full_name'])); ?>
                </div>
                <h3 class="ios-profile-name"><?php echo e($profile['full_name']); ?></h3>
                <p class="ios-profile-sub"><?php echo e($profile['email']); ?></p>
                <div class="ios-profile-badges">
                    <span class="ios-badge role">
                        <span class="ios-badge-dot"></span>
                        <?php echo e(ucwords(str_replace('_', ' ', $profile['role']))); ?>
                    </span>
                    <?php if ($isMember): ?>
                    <span class="ios-badge <?php echo e($profile['member_status'] ?? 'active'); ?>">
                        <span class="ios-badge-dot"></span>
                        <?php echo e(ucfirst($profile['member_status'] ?? 'active')); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info List -->
            <div class="ios-section-body border-t">
                <ul class="ios-info-list">
                    <li class="ios-info-item">
                        <span class="ios-info-label"><i class="fas fa-envelope icon-blue"></i>Email</span>
                        <span class="ios-info-value"><?php echo e($profile['email']); ?></span>
                    </li>
                    <?php if ($isMember && !empty($profile['phone'])): ?>
                    <li class="ios-info-item">
                        <span class="ios-info-label"><i class="fas fa-phone icon-green"></i>Phone</span>
                        <span class="ios-info-value"><?php echo e($profile['phone']); ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ($isMember): ?>
                    <li class="ios-info-item">
                        <span class="ios-info-label"><i class="fas fa-id-badge icon-purple"></i>Member ID</span>
                        <span class="ios-info-value" style="font-family:monospace;font-size:12px"><?php echo e($profile['member_code']); ?></span>
                    </li>
                    <?php if (!empty($profile['position'])): ?>
                    <li class="ios-info-item">
                        <span class="ios-info-label"><i class="fas fa-running icon-orange"></i>Position</span>
                        <span class="ios-info-value"><?php echo e($profile['position']); ?></span>
                    </li>
                    <?php endif; ?>
                    <li class="ios-info-item">
                        <span class="ios-info-label"><i class="fas fa-calendar-check icon-teal"></i>Joined</span>
                        <span class="ios-info-value"><?php echo $profile['joined_at'] ? formatDate($profile['joined_at'], 'd M Y') : '—'; ?></span>
                    </li>
                    <?php endif; ?>
                    <li class="ios-info-item">
                        <span class="ios-info-label"><i class="fas fa-clock icon-teal"></i>Last Login</span>
                        <span class="ios-info-value"><?php echo $profile['last_login_at'] ? formatDate($profile['last_login_at'], 'd M Y') : 'Now'; ?></span>
                    </li>
                    <li class="ios-info-item">
                        <span class="ios-info-label"><i class="fas fa-user-plus icon-orange"></i>Account Since</span>
                        <span class="ios-info-value"><?php echo formatDate($profile['created_at'], 'd M Y'); ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Quick Links (desktop only) -->
        <div class="ios-section-card d-none d-lg-block">
            <div class="ios-section-header">
                <div class="ios-section-icon green"><i class="fas fa-link"></i></div>
                <div class="ios-section-title">
                    <h5>Quick Links</h5>
                    <p>Shortcuts to key areas</p>
                </div>
            </div>
            <div class="ios-section-body no-pad">
                <?php if ($isMember): ?>
                <a href="<?php echo BASE_URL; ?>payments/my-payments.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-receipt"></i></div>
                        <span class="ios-menu-item-label">My Payments</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>notifications/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-bell"></i></div>
                        <span class="ios-menu-item-label">Notifications</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-th-large"></i></div>
                        <span class="ios-menu-item-label">Dashboard</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>logout.php" class="ios-menu-item" style="color: var(--ios-red)">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon red"><i class="fas fa-sign-out-alt"></i></div>
                        <span class="ios-menu-item-label" style="color:var(--ios-red)">Log Out</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- ───── RIGHT: Settings + Payments ───── -->
    <div>
        <!-- Settings Card -->
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-icon blue"><i class="fas fa-sliders-h"></i></div>
                <div class="ios-section-title">
                    <h5>Settings</h5>
                    <p>Manage your account</p>
                </div>
            </div>

            <div class="ios-section-body">
                <!-- Tabs -->
                <div class="ios-tabs">
                    <button class="ios-tab-btn <?php echo $activeTab === 'profile-info' ? 'active' : ''; ?>" data-tab="profile-info">
                        <i class="fas fa-user me-1"></i>Profile
                    </button>
                    <button class="ios-tab-btn <?php echo $activeTab === 'change-password' ? 'active' : ''; ?>" data-tab="change-password">
                        <i class="fas fa-key me-1"></i>Password
                    </button>
                    <?php if ($isMember): ?>
                    <button class="ios-tab-btn" data-tab="payments">
                        <i class="fas fa-receipt me-1"></i>Payments
                    </button>
                    <?php endif; ?>
                    <button class="ios-tab-btn" data-tab="notifications">
                        <i class="fas fa-bell me-1"></i>Notifications
                    </button>
                </div>

                <!-- ── Tab: Profile Info ── -->
                <div class="ios-tab-content <?php echo $activeTab === 'profile-info' ? 'active' : ''; ?>" id="profile-info">

                    <?php if ($error): ?>
                    <div class="ios-flash error">
                        <div class="ios-flash-icon"><i class="fas fa-times"></i></div>
                        <div>
                            <p class="ios-flash-title">Error</p>
                            <p class="ios-flash-text"><?php echo e($error); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="update_profile" value="1">

                        <div class="ios-form-row">
                            <div class="ios-form-group">
                                <label class="ios-form-label">Full Name <span style="color:var(--ios-red)">*</span></label>
                                <input type="text" name="full_name" class="ios-form-input"
                                       value="<?php echo e($profile['full_name']); ?>" required maxlength="150">
                            </div>
                            <div class="ios-form-group">
                                <label class="ios-form-label">Email Address</label>
                                <input type="email" class="ios-form-input" value="<?php echo e($profile['email']); ?>" readonly>
                                <p class="ios-form-hint"><i class="fas fa-lock me-1"></i>Contact admin to change email</p>
                            </div>
                        </div>

                        <?php if ($isMember): ?>
                        <div class="ios-form-row">
                            <div class="ios-form-group">
                                <label class="ios-form-label">Phone</label>
                                <input type="tel" name="phone" class="ios-form-input"
                                       value="<?php echo e($profile['phone'] ?? ''); ?>" maxlength="30"
                                       placeholder="+234 800 000 0000">
                            </div>
                            <div class="ios-form-group">
                                <label class="ios-form-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="ios-form-input"
                                       value="<?php echo e($profile['date_of_birth'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="ios-form-row">
                            <div class="ios-form-group">
                                <label class="ios-form-label">Playing Position</label>
                                <select name="position" class="ios-form-input">
                                    <option value="">— Not specified —</option>
                                    <?php foreach ($positions as $pos): ?>
                                    <option value="<?php echo e($pos); ?>" <?php echo ($profile['position'] ?? '') === $pos ? 'selected' : ''; ?>>
                                        <?php echo e($pos); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ios-form-group">
                                <label class="ios-form-label">Emergency Contact</label>
                                <input type="text" name="emergency_contact" class="ios-form-input"
                                       value="<?php echo e($profile['emergency_contact'] ?? ''); ?>" maxlength="200"
                                       placeholder="Name and phone number">
                            </div>
                        </div>

                        <div class="ios-form-group">
                            <label class="ios-form-label">Address</label>
                            <textarea name="address" class="ios-form-input" rows="2"
                                      maxlength="500" placeholder="Your home or mailing address"
                                      style="resize:vertical"><?php echo e($profile['address'] ?? ''); ?></textarea>
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="ios-btn primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>

                <!-- ── Tab: Change Password ── -->
                <div class="ios-tab-content <?php echo $activeTab === 'change-password' ? 'active' : ''; ?>" id="change-password">

                    <?php if ($pwError): ?>
                    <div class="ios-flash error">
                        <div class="ios-flash-icon"><i class="fas fa-times"></i></div>
                        <div>
                            <p class="ios-flash-title">Error</p>
                            <p class="ios-flash-text"><?php echo e($pwError); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($pwSuccess): ?>
                    <div class="ios-flash success">
                        <div class="ios-flash-icon"><i class="fas fa-check"></i></div>
                        <div>
                            <p class="ios-flash-title">Success</p>
                            <p class="ios-flash-text"><?php echo e($pwSuccess); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="change_password" value="1">

                        <div class="ios-form-group">
                            <label class="ios-form-label">Current Password <span style="color:var(--ios-red)">*</span></label>
                            <input type="password" name="current_password" class="ios-form-input" required autocomplete="current-password">
                        </div>
                        <div class="ios-form-group">
                            <label class="ios-form-label">New Password <span style="color:var(--ios-red)">*</span></label>
                            <input type="password" name="new_password" class="ios-form-input" required autocomplete="new-password">
                            <p class="ios-form-hint"><i class="fas fa-info-circle me-1"></i>Minimum 8 characters</p>
                        </div>
                        <div class="ios-form-group">
                            <label class="ios-form-label">Confirm New Password <span style="color:var(--ios-red)">*</span></label>
                            <input type="password" name="confirm_password" class="ios-form-input" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="ios-btn primary">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </form>
                </div>

                <!-- ── Tab: Payments (members only) ── -->
                <?php if ($isMember): ?>
                <div class="ios-tab-content" id="payments">
                    <?php if ($paymentSummary): ?>

                    <!-- Stats -->
                    <div class="ios-pay-stats">
                        <div class="ios-pay-stat">
                            <div class="ios-pay-stat-value green"><?php echo (int)$paymentSummary['paid']; ?></div>
                            <div class="ios-pay-stat-label">Paid</div>
                        </div>
                        <div class="ios-pay-stat">
                            <div class="ios-pay-stat-value orange"><?php echo (int)$paymentSummary['pending']; ?></div>
                            <div class="ios-pay-stat-label">Pending</div>
                        </div>
                        <div class="ios-pay-stat">
                            <div class="ios-pay-stat-value red"><?php echo (int)$paymentSummary['overdue']; ?></div>
                            <div class="ios-pay-stat-label">Overdue</div>
                        </div>
                    </div>

                    <div class="ios-pay-total">
                        <span class="ios-pay-total-label">Total Paid</span>
                        <span class="ios-pay-total-value">₦<?php echo number_format((float)$paymentSummary['total_paid']); ?></span>
                    </div>

                    <?php if ($recentPayments): ?>
                    <p style="font-size:12px;font-weight:600;color:var(--text-secondary);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">Recent Payments</p>
                    <ul class="ios-pay-list">
                        <?php foreach ($recentPayments as $p): ?>
                        <li class="ios-pay-row">
                            <div>
                                <div class="ios-pay-name"><?php echo e($p['due_name']); ?></div>
                                <div class="ios-pay-date"><?php echo $p['payment_date'] ? formatDate($p['payment_date'], 'd M Y') : '—'; ?></div>
                            </div>
                            <div class="ios-pay-right">
                                <span class="ios-status-pill pill-<?php echo e($p['status']); ?>"><?php echo e($p['status']); ?></span>
                                <span class="ios-pay-amount">₦<?php echo number_format((float)$p['amount']); ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <div style="margin-top:16px">
                        <a href="<?php echo BASE_URL; ?>payments/my-payments.php" class="ios-btn primary full">
                            <i class="fas fa-receipt"></i> View All Payments
                        </a>
                    </div>

                    <?php else: ?>
                    <div style="text-align:center;padding:32px 20px;color:var(--text-secondary)">
                        <i class="fas fa-receipt" style="font-size:40px;opacity:.4;margin-bottom:12px;display:block"></i>
                        <p>No payment records yet.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- ── Tab: Notifications ── -->
                <div class="ios-tab-content" id="notifications">

                    <!-- Status indicator -->
                    <div id="notification-status" style="display:none"></div>

                    <!-- Push notifications toggle row -->
                    <div class="ios-notif-list">
                        <div class="ios-notif-row">
                            <div class="ios-notif-left">
                                <div class="ios-notif-icon green">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <div>
                                    <p class="ios-notif-text-title">Push Notifications</p>
                                    <p class="ios-notif-text-sub">Dues, events &amp; announcements on this device</p>
                                </div>
                            </div>
                            <label class="ios-switch" style="flex-shrink:0;margin-left:14px" aria-label="Toggle push notifications">
                                <input type="checkbox" id="push-notification-toggle"
                                       data-user-id="<?php echo $userId; ?>">
                                <span class="ios-switch-track"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Test button -->
                    <button id="test-push-notification" class="ios-btn primary full" disabled>
                        <i class="fas fa-paper-plane"></i> Send Test Notification
                    </button>

                    <!-- Info note -->
                    <div style="margin-top:14px;padding:12px 14px;background:rgba(10,132,255,.06);border:1px solid rgba(10,132,255,.12);border-radius:12px;font-size:12px;color:var(--text-secondary);line-height:1.6">
                        <i class="fas fa-info-circle me-1" style="color:var(--ios-blue)"></i>
                        Requires browser permission and HTTPS. You can revoke access anytime via your browser settings.
                    </div>

                </div>

            </div><!-- /ios-section-body -->
        </div><!-- /settings card -->

    </div><!-- /right col -->
</div><!-- /ios-grid -->

<!-- ── Mobile Bottom-Sheet Menu ─────────────────────── -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h3 class="ios-menu-title">My Profile</h3>
        <button class="ios-menu-close" id="iosMenuClose"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Account</div>
            <div class="ios-menu-card">
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Name</span>
                    <span class="ios-menu-stat-value"><?php echo e($profile['full_name']); ?></span>
                </div>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Role</span>
                    <span class="ios-menu-stat-value"><?php echo e(ucwords(str_replace('_',' ',$profile['role']))); ?></span>
                </div>
                <?php if ($isMember): ?>
                <div class="ios-menu-stat-row">
                    <span class="ios-menu-stat-label">Member ID</span>
                    <span class="ios-menu-stat-value" style="font-family:monospace;font-size:12px"><?php echo e($profile['member_code']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="ios-menu-section">
            <div class="ios-menu-section-title">Quick Actions</div>
            <div class="ios-menu-card">
                <div class="ios-menu-item" onclick="switchTab('profile-info')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-user-edit"></i></div>
                        <span class="ios-menu-item-label">Edit Profile</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <div class="ios-menu-item" onclick="switchTab('change-password')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-key"></i></div>
                        <span class="ios-menu-item-label">Change Password</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <?php if ($isMember): ?>
                <div class="ios-menu-item" onclick="switchTab('payments')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-receipt"></i></div>
                        <span class="ios-menu-item-label">My Payments</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>notifications/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-bell"></i></div>
                        <span class="ios-menu-item-label">Notifications</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <div class="ios-menu-item" onclick="switchTab('notifications')">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon green"><i class="fas fa-bell"></i></div>
                        <span class="ios-menu-item-label">Notification Settings</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </div>
                <a href="<?php echo BASE_URL; ?>logout.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon red"><i class="fas fa-sign-out-alt"></i></div>
                        <span class="ios-menu-item-label" style="color:var(--ios-red)">Log Out</span>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    // ── Tabs ──────────────────────────────────────────
    document.querySelectorAll('.ios-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tabId = this.getAttribute('data-tab');
            document.querySelectorAll('.ios-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.ios-tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            var el = document.getElementById(tabId);
            if (el) el.classList.add('active');
            history.replaceState(null, null, '#' + tabId);
        });
    });

    // Hash-based tab activation on load
    var hash = location.hash.replace('#', '');
    if (hash) {
        var btn = document.querySelector('[data-tab="' + hash + '"]');
        if (btn) btn.click();
    }

    // ── Mobile Menu ───────────────────────────────────
    var backdrop  = document.getElementById('iosMenuBackdrop');
    var modal     = document.getElementById('iosMenuModal');
    var optBtn    = document.getElementById('iosOptionsBtn');
    var closeBtn  = document.getElementById('iosMenuClose');

    function openMenu()  { backdrop.classList.add('active'); modal.classList.add('active'); }
    function closeMenu() { backdrop.classList.remove('active'); modal.classList.remove('active'); }

    if (optBtn)   optBtn.addEventListener('click', openMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);
    if (backdrop) backdrop.addEventListener('click', closeMenu);

    // Swipe down to close
    var startY = 0;
    modal.addEventListener('touchstart', function (e) { startY = e.touches[0].clientY; }, {passive:true});
    modal.addEventListener('touchend',   function (e) { if (e.changedTouches[0].clientY - startY > 60) closeMenu(); }, {passive:true});
})();

function switchTab(id) {
    var btn = document.querySelector('[data-tab="' + id + '"]');
    if (btn) {
        document.getElementById('iosMenuBackdrop').classList.remove('active');
        document.getElementById('iosMenuModal').classList.remove('active');
        setTimeout(function () { btn.click(); }, 280);
    }
}

// ── Test push notification button ────────────────────
document.addEventListener('DOMContentLoaded', function () {
    var testBtn = document.getElementById('test-push-notification');
    if (!testBtn) return;

    testBtn.addEventListener('click', async function () {
        testBtn.disabled = true;
        var status = document.getElementById('notification-status');
        if (status) {
            status.style.display = 'block';
            status.style.background = 'var(--bg-secondary)';
            status.style.color = 'var(--text-secondary)';
            status.style.border = '1px solid var(--border-color)';
            status.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending test notification…';
        }

        try {
            var base = (window.LASC_BASE_URL || '/').replace(/\/?$/, '/');
            var resp = await fetch(base + 'api/send-test-push.php', { method: 'POST' });
            var data = await resp.json();

            if (status) {
                status.style.display = 'block';
                if (data.success) {
                    status.style.background = 'rgba(48,209,88,.08)';
                    status.style.color = 'var(--ios-green)';
                    status.style.border = '1px solid rgba(48,209,88,.2)';
                    status.innerHTML = '<i class="fas fa-check-circle me-2"></i>Test notification sent — check your device!';
                } else {
                    status.style.background = 'rgba(255,69,58,.08)';
                    status.style.color = 'var(--ios-red)';
                    status.style.border = '1px solid rgba(255,69,58,.2)';
                    status.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + (data.message || 'Failed to send test notification.');
                }
                setTimeout(function () { status.style.display = 'none'; }, 6000);
            }
        } catch (err) {
            if (status) {
                status.style.display = 'block';
                status.style.background = 'rgba(255,69,58,.08)';
                status.style.color = 'var(--ios-red)';
                status.style.border = '1px solid rgba(255,69,58,.2)';
                status.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Error: ' + err.message;
            }
        }

        // Re-enable only if still subscribed
        if (typeof pushNotifications !== 'undefined') {
            testBtn.disabled = !pushNotifications.isSubscribed;
        }
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
