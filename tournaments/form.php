<?php
/**
 * Tournament create / edit form (admin only)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Tournament.php';

requireAdmin();

$tourObj = new Tournament();
$id      = (int)($_GET['id'] ?? 0);
$tour    = $id ? $tourObj->getById($id) : null;
$isEdit  = (bool)$tour;

if ($id && !$tour) { flashError('Tournament not found.'); redirect('tournaments/manage.php'); }
if ($isEdit && $tour['status'] !== 'setup') { flashError('Only tournaments in setup status can be edited.'); redirect('tournaments/manage.php'); }

$pageTitle = $isEdit ? 'Edit Tournament' : 'New Tournament';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $name        = sanitize($_POST['name']        ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $format      = sanitize($_POST['format']      ?? 'group_knockout');
    $num_groups  = max(1, (int)($_POST['num_groups'] ?? 2));
    $start_date  = sanitize($_POST['start_date']  ?? '');

    $validFormats = ['league','knockout','group_knockout'];
    if (!in_array($format, $validFormats)) $format = 'group_knockout';

    $errors = [];
    if (!$name) $errors[] = 'Tournament name is required.';

    if (empty($errors)) {
        $data = [
            'name'        => $name,
            'description' => $description ?: null,
            'format'      => $format,
            'num_groups'  => $num_groups,
            'start_date'  => $start_date ?: null,
            'created_by'  => $_SESSION['user_id'],
        ];
        if ($isEdit) {
            $tourObj->update($id, $data);
            flashSuccess('Tournament updated.');
            redirect('tournaments/setup.php?id=' . $id);
        } else {
            $newId = $tourObj->create($data);
            flashSuccess('Tournament created. Now set up your groups and teams.');

            // Notify all members (push + in-app + email)
            try {
                require_once dirname(__DIR__) . '/classes/PushService.php';
                require_once dirname(__DIR__) . '/app/mail/emails.php';
                $dateStr  = $start_date ? date('d M Y', strtotime($start_date)) : 'TBC';
                $pushBody = "A new tournament has been created: {$name}. Starting: {$dateStr}.";
                $notifUrl = BASE_URL . "tournaments/view.php?id={$newId}";
                $push = new PushService();
                $push->notifyAll('tournament', 'New Tournament: ' . $name, $pushBody, $notifUrl);

                $db      = Database::getInstance();
                $members = $db->fetchAll("SELECT full_name, email FROM users WHERE status = 'active' AND role = 'user'");
                $emailMsg = "<p>A new tournament has been created. Get ready to compete!</p>
                    <table style='background:#f4f6f8;border-radius:8px;padding:20px;width:100%;margin:16px 0;border-collapse:collapse;'>
                        <tr><td style='padding:8px 0;color:#637381;width:120px;'>Tournament</td>
                            <td style='padding:8px 0;font-weight:700;color:#1c252e;'>" . htmlspecialchars($name) . "</td></tr>
                        <tr><td style='padding:8px 0;color:#637381;'>Start Date</td>
                            <td style='padding:8px 0;font-weight:700;color:#1c252e;'>{$dateStr}</td></tr>"
                    . ($description ? "<tr><td style='padding:8px 0;color:#637381;'>Details</td>
                            <td style='padding:8px 0;color:#1c252e;'>" . htmlspecialchars($description) . "</td></tr>" : '')
                    . "</table>";
                foreach ($members as $m) {
                    sendNotificationEmail($m['email'], $m['full_name'], 'New Tournament: ' . $name, 'New Tournament', $emailMsg, $notifUrl, 'View Tournament');
                }
            } catch (Throwable $e) {
                error_log('Tournament notification failed: ' . $e->getMessage());
            }

            redirect('tournaments/setup.php?id=' . $newId);
        }
    }
}

$currentFormat = $tour['format'] ?? ($_POST['format'] ?? 'group_knockout');

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

/* ── Format chips ── */
.ios-format-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.ios-format-chip {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 8px; padding: 16px 8px; border-radius: 12px;
    border: 1.5px solid var(--border-color); background: var(--bg-secondary);
    cursor: pointer; user-select: none; transition: all .15s;
    text-align: center;
}
.ios-format-chip:hover { border-color: var(--primary); background: rgba(var(--primary-rgb),.04); }
.ios-format-chip.selected { border-color: var(--primary); background: rgba(var(--primary-rgb),.08); }
.ios-format-chip i { font-size: 20px; color: var(--text-muted); transition: color .15s; }
.ios-format-chip .chip-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); transition: color .15s; }
.ios-format-chip .chip-sub   { font-size: 11px; color: var(--text-muted); line-height: 1.3; }
.ios-format-chip.selected i            { color: var(--primary); }
.ios-format-chip.selected .chip-label  { color: var(--primary); }

/* ── Groups reveal ── */
.ios-groups-panel {
    overflow: hidden;
    max-height: 0;
    opacity: 0;
    transition: max-height .3s ease, opacity .3s ease;
}
.ios-groups-panel.open {
    max-height: 120px;
    opacity: 1;
}
.ios-groups-inner {
    padding: var(--spacing-4) var(--spacing-5);
    border-top: 1px solid var(--border-color);
}

/* ── Sidebar ── */
.ios-sidebar { display: flex; flex-direction: column; }
.ios-sidebar-sticky { position: sticky; top: var(--spacing-4); }
.ios-admin-actions { padding: var(--spacing-4); display: flex; flex-direction: column; gap: var(--spacing-3); }

.ios-tip-list { padding: var(--spacing-4) var(--spacing-5); display: flex; flex-direction: column; gap: var(--spacing-3); }
.ios-tip-item { display: flex; gap: 10px; font-size: 13px; }
.ios-tip-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.ios-tip-text { color: var(--text-secondary); line-height: 1.5; }
.ios-tip-text strong { color: var(--text-primary); }

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
    .ios-groups-inner { padding: var(--spacing-4); }
    .ios-format-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>

<!-- ===== DESKTOP HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-start">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="breadcrumb-link">Tournaments</a>
                </li>
                <li class="breadcrumb-item active"><?php echo $isEdit ? 'Edit' : 'New Tournament'; ?></li>
            </ol>
        </nav>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Tournament' : 'New Tournament'; ?></h1>
        <p class="content-subtitle"><?php echo $isEdit ? 'Update the tournament details below.' : 'Fill in the details, then set up groups and teams.'; ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="btn btn-secondary flex-shrink-0">
        <i class="fas fa-arrow-left me-2"></i>Back to Tournaments
    </a>
</div>

<!-- ===== MOBILE HEADER ===== -->
<div class="ios-section-header d-md-none mb-3" style="border-radius:14px;border:1px solid var(--border-color)">
    <div class="ios-section-icon blue">
        <i class="fas fa-<?php echo $isEdit ? 'edit' : 'trophy'; ?>"></i>
    </div>
    <div class="ios-section-title">
        <h5><?php echo $isEdit ? 'Edit Tournament' : 'New Tournament'; ?></h5>
        <p><?php echo $isEdit ? 'Update tournament details' : 'Create a new tournament'; ?></p>
    </div>
    <button onclick="openIosMenu()" style="width:36px;height:36px;border-radius:50%;background:var(--bg-secondary);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0">
        <i class="fas fa-ellipsis-v" style="color:var(--text-primary);font-size:16px"></i>
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4" style="max-width:1100px;margin-left:auto;margin-right:auto;border-radius:12px">
    <i class="fas fa-exclamation-circle me-2"></i>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="tournamentForm">
    <?php echo csrfField(); ?>
    <input type="hidden" name="format" id="formatInput" value="<?php echo e($currentFormat); ?>">

    <div class="form-container">

        <!-- ===== LEFT ===== -->
        <div>

            <!-- Tournament Details card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Tournament Details</h5>
                        <p>Name, description and start date</p>
                    </div>
                </div>
                <div class="ios-section-body">

                    <div class="ios-form-group">
                        <label class="ios-form-label">Name <span class="req">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="200"
                               style="border-radius:10px"
                               value="<?php echo e($tour['name'] ?? ($_POST['name'] ?? '')); ?>"
                               placeholder="e.g. LASC Spring Cup 2026">
                    </div>

                    <div class="ios-form-group">
                        <label class="ios-form-label">Description <span class="opt">(optional)</span></label>
                        <textarea name="description" class="form-control" rows="3"
                                  style="border-radius:10px;resize:none"
                                  placeholder="Tournament rules, format details, prizes…"><?php echo e($tour['description'] ?? ($_POST['description'] ?? '')); ?></textarea>
                    </div>

                    <div class="ios-form-group">
                        <label class="ios-form-label">Start Date <span class="opt">(optional)</span></label>
                        <input type="date" name="start_date" class="form-control"
                               style="border-radius:10px;max-width:220px"
                               value="<?php echo $tour['start_date'] ?? ($_POST['start_date'] ?? ''); ?>">
                    </div>

                </div>
            </div>

            <!-- Format card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon orange">
                        <i class="fas fa-sitemap"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Format</h5>
                        <p>How the tournament is structured</p>
                    </div>
                </div>
                <div class="ios-section-body">

                    <div class="ios-form-group">
                        <label class="ios-form-label">Tournament Format <span class="req">*</span></label>
                        <div class="ios-format-grid">

                            <div class="ios-format-chip <?php echo $currentFormat === 'league' ? 'selected' : ''; ?>" data-format="league">
                                <i class="fas fa-sync-alt"></i>
                                <span class="chip-label">League</span>
                                <span class="chip-sub">Round Robin</span>
                            </div>

                            <div class="ios-format-chip <?php echo $currentFormat === 'knockout' ? 'selected' : ''; ?>" data-format="knockout">
                                <i class="fas fa-random"></i>
                                <span class="chip-label">Knockout</span>
                                <span class="chip-sub">Single elim.</span>
                            </div>

                            <div class="ios-format-chip <?php echo $currentFormat === 'group_knockout' ? 'selected' : ''; ?>" data-format="group_knockout">
                                <i class="fas fa-layer-group"></i>
                                <span class="chip-label">Groups + KO</span>
                                <span class="chip-sub">Group stage first</span>
                            </div>

                        </div>
                    </div>

                </div>

                <!-- Number of groups (slides open) -->
                <div class="ios-groups-panel <?php echo $currentFormat !== 'knockout' ? 'open' : ''; ?>" id="groupsPanel">
                    <div class="ios-groups-inner">
                        <label class="ios-form-label">Number of Groups</label>
                        <input type="number" name="num_groups" class="form-control" min="1" max="16"
                               style="border-radius:10px;max-width:120px"
                               value="<?php echo $tour['num_groups'] ?? ($_POST['num_groups'] ?? 2); ?>">
                        <small style="font-size:12px;color:var(--text-muted);display:block;margin-top:4px">Used for league and group stage formats.</small>
                    </div>
                </div>
            </div>

            <!-- Mobile submit -->
            <div class="d-md-none" style="display:flex;flex-direction:column;gap:10px;margin-bottom:var(--spacing-4)">
                <button type="submit" class="btn btn-primary w-100" style="border-radius:12px;padding:14px">
                    <i class="fas fa-<?php echo $isEdit ? 'save' : 'arrow-right'; ?> me-2"></i>
                    <?php echo $isEdit ? 'Update & Continue' : 'Create & Set Up Groups'; ?>
                </button>
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="btn btn-secondary w-100" style="border-radius:12px;padding:14px">
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
                            <h5><?php echo $isEdit ? 'Save Changes' : 'Create Tournament'; ?></h5>
                        </div>
                    </div>
                    <div class="ios-admin-actions">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-<?php echo $isEdit ? 'save' : 'arrow-right'; ?> me-2"></i>
                            <?php echo $isEdit ? 'Update & Continue' : 'Create & Set Up Groups'; ?>
                        </button>
                        <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="btn btn-secondary w-100">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </div>

                <!-- Tips / Info card -->
                <?php if (!$isEdit): ?>
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue"><i class="fas fa-lightbulb"></i></div>
                        <div class="ios-section-title"><h5>Getting Started</h5></div>
                    </div>
                    <div class="ios-tip-list">
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">🏆</span>
                            <span class="ios-tip-text"><strong>Choose a format</strong> that suits your tournament size and schedule.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">👥</span>
                            <span class="ios-tip-text"><strong>After creating</strong>, you'll add groups, teams and players in the setup step.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">📅</span>
                            <span class="ios-tip-text"><strong>Fixtures</strong> are scheduled from the tournament view once setup is complete.</span>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue"><i class="fas fa-info-circle"></i></div>
                        <div class="ios-section-title"><h5>Tournament Info</h5></div>
                    </div>
                    <div style="padding:var(--spacing-4) var(--spacing-5)">
                        <div style="font-size:13px;color:var(--text-secondary);line-height:1.6">
                            <p class="mb-2">Only tournaments in <strong>Setup</strong> status can be edited.</p>
                            <p class="mb-0">Saving will return you to the setup page to manage groups and teams.</p>
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
        <h5 class="ios-menu-title"><?php echo $isEdit ? 'Edit Tournament' : 'New Tournament'; ?></h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-trophy"></i></div>
                        <div class="ios-menu-item-label">Manage Tournaments</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>tournaments/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon orange"><i class="fas fa-list"></i></div>
                        <div class="ios-menu-item-label">All Tournaments</div>
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
    var modal  = document.getElementById('iosMenuModal');
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

/* ── Format chips ── */
var groupsPanel = document.getElementById('groupsPanel');

document.querySelectorAll('.ios-format-chip').forEach(function(chip) {
    chip.addEventListener('click', function() {
        document.querySelectorAll('.ios-format-chip').forEach(function(c) {
            c.classList.remove('selected');
            c.querySelector('i').style.color = '';
            c.querySelector('.chip-label').style.color = '';
        });
        this.classList.add('selected');
        this.querySelector('i').style.color = 'var(--primary)';
        this.querySelector('.chip-label').style.color = 'var(--primary)';
        var fmt = this.dataset.format;
        document.getElementById('formatInput').value = fmt;
        if (fmt === 'knockout') {
            groupsPanel.classList.remove('open');
        } else {
            groupsPanel.classList.add('open');
        }
    });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
