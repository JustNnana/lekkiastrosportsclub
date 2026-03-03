<?php
/**
 * Poll create / edit form (admin only)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Poll.php';

requireAdmin();

$pollObj = new Poll();
$id      = (int)($_GET['id'] ?? 0);
$poll    = $id ? $pollObj->getById($id) : null;
$isEdit  = (bool)$poll;

if ($id && !$poll) {
    flashError('Poll not found.');
    redirect('polls/manage.php');
}

// Cannot edit a closed poll
if ($isEdit && $pollObj->isClosed($poll)) {
    flashError('Closed polls cannot be edited.');
    redirect('polls/manage.php');
}

$existingOptions = $isEdit ? $pollObj->getOptions($id) : [];
$pageTitle       = $isEdit ? 'Edit Poll' : 'New Poll';

// ─── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $question     = sanitize($_POST['question']     ?? '');
    $description  = sanitize($_POST['description']  ?? '');
    $allow_change = isset($_POST['allow_change']) ? 1 : 0;
    $deadline     = sanitize($_POST['deadline']     ?? '');
    $options      = $_POST['options'] ?? [];

    // Filter empty options
    $options = array_filter(array_map('trim', $options), fn($o) => $o !== '');

    $errors = [];
    if (!$question)            $errors[] = 'Question is required.';
    if (!$deadline)            $errors[] = 'Deadline is required.';
    if (count($options) < 2)   $errors[] = 'At least 2 options are required.';
    if ($deadline && strtotime($deadline) <= time() && !$isEdit) {
        $errors[] = 'Deadline must be in the future.';
    }

    if (empty($errors)) {
        $data = [
            'question'     => $question,
            'description'  => $description ?: null,
            'allow_change' => $allow_change,
            'deadline'     => date('Y-m-d H:i:s', strtotime($deadline)),
            'created_by'   => $_SESSION['user_id'],
        ];

        if ($isEdit) {
            $pollObj->update($id, $data, array_values($options));
            flashSuccess('Poll updated.');
            redirect("polls/view.php?id=$id");
        } else {
            $newId = $pollObj->create($data, array_values($options));
            flashSuccess('Poll created and is now active.');
            redirect("polls/view.php?id=$newId");
        }
    }
}

// Pre-fill options
$prefillOptions = [];
if ($isEdit && !empty($existingOptions)) {
    $prefillOptions = array_column($existingOptions, 'option_text');
} elseif (!empty($_POST['options'])) {
    $prefillOptions = $_POST['options'];
} else {
    $prefillOptions = ['', ''];
}

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

.ios-section-title { flex: 1; min-width: 0; }
.ios-section-title h5 { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-section-title p  { font-size: 13px; color: var(--text-secondary); margin: 4px 0 0; }

/* ── Form body ── */
.ios-section-body { padding: var(--spacing-5); }
.ios-form-group { margin-bottom: var(--spacing-4); }
.ios-form-group:last-child { margin-bottom: 0; }
.ios-form-label {
    display: block; font-size: 13px; font-weight: 600;
    color: var(--text-secondary); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .3px;
}
.ios-form-label .req { color: var(--ios-red); margin-left: 2px; }
.ios-form-label .opt { color: var(--text-muted); font-weight: 400; text-transform: none; letter-spacing: 0; }

/* ── Options list ── */
.ios-options-body { padding: var(--spacing-4) var(--spacing-5); }
.ios-option-row {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}
.ios-option-row:first-child { padding-top: 0; }
.ios-option-row:last-child  { border-bottom: none; padding-bottom: 0; }
.ios-option-num {
    width: 24px; height: 24px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 700; color: var(--text-secondary);
    flex-shrink: 0;
}
.ios-option-row input.form-control { border-radius: 10px; font-size: 14px; }
.ios-remove-btn {
    width: 30px; height: 30px; border-radius: 50%;
    border: none; background: rgba(255,69,58,.1); color: var(--ios-red);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; flex-shrink: 0; font-size: 13px;
    transition: background .15s;
}
.ios-remove-btn:hover { background: rgba(255,69,58,.2); }

.ios-add-option-btn {
    display: flex; align-items: center; gap: 8px;
    padding: 12px var(--spacing-5);
    border-top: 1px solid var(--border-color);
    border: none; background: transparent; width: 100%;
    color: var(--primary); font-size: 14px; font-weight: 600;
    font-family: inherit; cursor: pointer; text-align: left;
    transition: background .15s;
    border-top: 1px solid var(--border-color);
}
.ios-add-option-btn:hover { background: rgba(var(--primary-rgb),.05); }
.ios-add-option-btn i { font-size: 16px; }
.ios-options-hint { padding: 8px var(--spacing-5) 14px; font-size: 12px; color: var(--text-muted); }

/* ── iOS Toggle ── */
.ios-toggle-row {
    display: flex; align-items: flex-start;
    justify-content: space-between;
    padding: var(--spacing-4) var(--spacing-5);
    gap: var(--spacing-4);
}
.ios-toggle-info { flex: 1; }
.ios-toggle-info strong { display: block; font-size: 14px; font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
.ios-toggle-info small  { font-size: 12px; color: var(--text-secondary); }
.ios-toggle { position: relative; flex-shrink: 0; }
.ios-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.ios-toggle-track {
    width: 51px; height: 31px;
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
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
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,.2);
    transition: transform .25s cubic-bezier(.4,0,.2,1);
}
.ios-toggle input:checked ~ .ios-toggle-track .ios-toggle-thumb {
    transform: translateX(20px);
}

/* ── Warning banner ── */
.ios-warning-banner {
    margin: 0 var(--spacing-5) var(--spacing-4);
    padding: 12px 14px;
    background: rgba(255,159,10,.1); border: 1px solid rgba(255,159,10,.3);
    border-radius: 12px;
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 13px; color: var(--text-primary);
}
.ios-warning-banner i { color: var(--ios-orange); font-size: 15px; margin-top: 1px; flex-shrink: 0; }

/* ── Sidebar ── */
.ios-sidebar { display: flex; flex-direction: column; }
.ios-sidebar-sticky { position: sticky; top: var(--spacing-4); }

/* Tip rows */
.ios-tip-list { padding: var(--spacing-4) var(--spacing-5); display: flex; flex-direction: column; gap: var(--spacing-3); }
.ios-tip-item { display: flex; gap: 10px; font-size: 13px; }
.ios-tip-icon { font-size: 15px; flex-shrink: 0; margin-top: 1px; }
.ios-tip-text { color: var(--text-secondary); line-height: 1.5; }
.ios-tip-text strong { color: var(--text-primary); }

/* Admin actions */
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

/* ── Mobile settings panel in sheet ── */
.ios-sheet-settings { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; margin-bottom: 12px; }
.ios-sheet-field { padding: 14px 16px; border-bottom: 1px solid var(--border-color); }
.ios-sheet-field:last-child { border-bottom: none; }
.ios-sheet-field label { font-size: 12px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .3px; display: block; margin-bottom: 6px; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .ios-sidebar { display: none; }
    .form-container { grid-template-columns: 1fr; }
    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 14px; }
    .ios-section-body { padding: var(--spacing-4); }
    .ios-options-body { padding: var(--spacing-3) var(--spacing-4); }
    .ios-add-option-btn { padding: 12px var(--spacing-4); }
    .ios-options-hint { padding: 8px var(--spacing-4) 12px; }
    .ios-toggle-row { padding: var(--spacing-4); }
    .ios-warning-banner { margin: 0 var(--spacing-4) var(--spacing-4); }
}
</style>

<!-- ===== DESKTOP HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-start">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>polls/manage.php" class="breadcrumb-link">Polls</a>
                </li>
                <li class="breadcrumb-item active"><?php echo $isEdit ? 'Edit' : 'New Poll'; ?></li>
            </ol>
        </nav>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Poll' : 'New Poll'; ?></h1>
        <p class="content-subtitle"><?php echo $isEdit ? 'Update the poll question, options and settings.' : 'Create a new poll for members to vote on.'; ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>polls/manage.php" class="btn btn-secondary flex-shrink-0">
        <i class="fas fa-arrow-left me-2"></i>Back to Polls
    </a>
</div>

<!-- ===== MOBILE HEADER ===== -->
<div class="ios-section-header d-md-none mb-3" style="border-radius:14px;border:1px solid var(--border-color)">
    <div class="ios-section-icon purple">
        <i class="fas fa-<?php echo $isEdit ? 'edit' : 'poll'; ?>"></i>
    </div>
    <div class="ios-section-title">
        <h5><?php echo $isEdit ? 'Edit Poll' : 'New Poll'; ?></h5>
        <p><?php echo $isEdit ? 'Update poll details' : 'Create a poll for members'; ?></p>
    </div>
    <button class="ios-options-btn" onclick="openIosMenu()" style="width:36px;height:36px;border-radius:50%;background:var(--bg-secondary);border:1px solid var(--border-color);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0">
        <i class="fas fa-ellipsis-v" style="color:var(--text-primary);font-size:16px"></i>
    </button>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4" style="max-width:1100px;margin-left:auto;margin-right:auto;border-radius:12px">
    <i class="fas fa-exclamation-circle me-2"></i>
    <ul class="mb-0 mt-1"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="pollForm">
    <?php echo csrfField(); ?>

    <div class="form-container">

        <!-- ===== LEFT: QUESTION + OPTIONS ===== -->
        <div>

            <!-- Question card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon purple">
                        <i class="fas fa-poll"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Poll Question</h5>
                        <p>Ask a clear, specific question</p>
                    </div>
                </div>
                <div class="ios-section-body">
                    <div class="ios-form-group">
                        <label class="ios-form-label">Question <span class="req">*</span></label>
                        <input type="text" name="question" class="form-control" style="border-radius:10px"
                               value="<?php echo e($poll['question'] ?? ($_POST['question'] ?? '')); ?>"
                               placeholder="What would you like to ask members?" required maxlength="300">
                    </div>
                    <div class="ios-form-group">
                        <label class="ios-form-label">Description <span class="opt">(optional)</span></label>
                        <textarea name="description" class="form-control" rows="3" style="border-radius:10px;resize:none"
                                  placeholder="Add context or details to help members understand the poll…"><?php echo e($poll['description'] ?? ($_POST['description'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Options card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon green">
                        <i class="fas fa-list-ul"></i>
                    </div>
                    <div class="ios-section-title">
                        <h5>Answer Options</h5>
                        <p>Minimum 2 options required</p>
                    </div>
                </div>

                <div class="ios-options-body" id="optionsList">
                    <?php foreach ($prefillOptions as $i => $optText): ?>
                    <div class="ios-option-row" data-option>
                        <div class="ios-option-num"><?php echo $i + 1; ?></div>
                        <input type="text" name="options[]" class="form-control"
                               value="<?php echo e($optText); ?>"
                               placeholder="Option <?php echo $i + 1; ?>" maxlength="200">
                        <button type="button" class="ios-remove-btn remove-option" title="Remove option">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="ios-add-option-btn" id="addOption">
                    <i class="fas fa-plus-circle"></i> Add Another Option
                </button>
                <p class="ios-options-hint">Enter one choice per field. At least 2 options are required.</p>
            </div>

            <!-- ===== MOBILE-ONLY: Settings + Submit ===== -->
            <div class="d-md-none">

                <!-- Deadline + toggle card (mobile) -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue">
                            <i class="fas fa-sliders-h"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5>Poll Settings</h5>
                        </div>
                    </div>
                    <div class="ios-section-body">
                        <div class="ios-form-group" style="margin-bottom:0">
                            <label class="ios-form-label">Voting Deadline <span class="req">*</span></label>
                            <input type="datetime-local" id="deadlineMobile" class="form-control" style="border-radius:10px">
                            <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0">Poll auto-closes at this date &amp; time.</p>
                        </div>
                    </div>
                    <div class="ios-toggle-row" style="border-top:1px solid var(--border-color)">
                        <div class="ios-toggle-info">
                            <strong>Allow vote changes</strong>
                            <small>Members can update their vote before the deadline</small>
                        </div>
                        <label class="ios-toggle">
                            <input type="checkbox" id="allowChangeMobile">
                            <span class="ios-toggle-track">
                                <span class="ios-toggle-thumb"></span>
                            </span>
                        </label>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                <div class="ios-section-card">
                    <div class="ios-warning-banner" style="margin:var(--spacing-4)">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Editing this poll will <strong>reset all existing votes</strong>. Members will need to vote again.</span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Submit button (mobile) -->
                <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:var(--spacing-4)">
                    <button type="submit" class="btn btn-primary w-100" style="border-radius:12px;padding:14px">
                        <i class="fas fa-<?php echo $isEdit ? 'save' : 'paper-plane'; ?> me-2"></i>
                        <?php echo $isEdit ? 'Update Poll' : 'Create Poll'; ?>
                    </button>
                    <a href="<?php echo BASE_URL; ?>polls/manage.php" class="btn btn-secondary w-100" style="border-radius:12px;padding:14px">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>

            </div><!-- /mobile-only -->

        </div><!-- /left -->

        <!-- ===== RIGHT: SIDEBAR ===== -->
        <div class="ios-sidebar">
            <div class="ios-sidebar-sticky">

                <!-- Settings card -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon blue">
                            <i class="fas fa-sliders-h"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5>Poll Settings</h5>
                        </div>
                    </div>
                    <div class="ios-section-body">
                        <div class="ios-form-group">
                            <label class="ios-form-label">Voting Deadline <span class="req">*</span></label>
                            <input type="datetime-local" name="deadline" id="deadlineMain" class="form-control" required
                                   style="border-radius:10px"
                                   value="<?php echo $poll ? date('Y-m-d\TH:i', strtotime($poll['deadline'])) : ($_POST['deadline'] ?? ''); ?>">
                            <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0">Poll auto-closes at this date &amp; time.</p>
                        </div>
                    </div>
                    <div class="ios-toggle-row" style="border-top:1px solid var(--border-color)">
                        <div class="ios-toggle-info">
                            <strong>Allow vote changes</strong>
                            <small>Members can update their vote before the deadline</small>
                        </div>
                        <label class="ios-toggle">
                            <input type="checkbox" name="allow_change" id="allowChangeMain" value="1"
                                   <?php echo ($poll['allow_change'] ?? 0) ? 'checked' : ''; ?>>
                            <span class="ios-toggle-track">
                                <span class="ios-toggle-thumb"></span>
                            </span>
                        </label>
                    </div>
                </div>

                <!-- Edit warning -->
                <?php if ($isEdit): ?>
                <div class="ios-section-card">
                    <div class="ios-warning-banner" style="margin:var(--spacing-4)">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Editing this poll will <strong>reset all existing votes</strong>. Members will need to vote again.</span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Actions card -->
                <div class="ios-section-card">
                    <div class="ios-section-header">
                        <div class="ios-section-icon <?php echo $isEdit ? 'orange' : 'green'; ?>">
                            <i class="fas fa-<?php echo $isEdit ? 'save' : 'check'; ?>"></i>
                        </div>
                        <div class="ios-section-title">
                            <h5><?php echo $isEdit ? 'Save Changes' : 'Publish Poll'; ?></h5>
                        </div>
                    </div>
                    <div class="ios-admin-actions">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-<?php echo $isEdit ? 'save' : 'paper-plane'; ?> me-2"></i>
                            <?php echo $isEdit ? 'Update Poll' : 'Create Poll'; ?>
                        </button>
                        <a href="<?php echo BASE_URL; ?>polls/manage.php" class="btn btn-secondary w-100">
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
                            <h5>Poll Tips</h5>
                        </div>
                    </div>
                    <div class="ios-tip-list">
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">📝</span>
                            <span class="ios-tip-text"><strong>Be specific.</strong> A clear question gets clearer answers from members.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">⚖️</span>
                            <span class="ios-tip-text"><strong>Balance options.</strong> Cover all likely answers to avoid bias.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">📅</span>
                            <span class="ios-tip-text"><strong>Set a fair deadline.</strong> Give members enough time to see and vote.</span>
                        </div>
                        <div class="ios-tip-item">
                            <span class="ios-tip-icon">🔒</span>
                            <span class="ios-tip-text"><strong>Results hidden</strong> until members vote, so votes aren't influenced.</span>
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
        <h5 class="ios-menu-title"><?php echo $isEdit ? 'Edit Poll' : 'New Poll'; ?></h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <!-- Navigation -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>polls/manage.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-poll"></i></div>
                        <div class="ios-menu-item-label">Manage Polls</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <a href="<?php echo BASE_URL; ?>dashboard/" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-home"></i></div>
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

/* ── Sync mobile ↔ main settings ── */
var deadlineMain    = document.getElementById('deadlineMain');
var deadlineMobile  = document.getElementById('deadlineMobile');
var allowChangeMain = document.getElementById('allowChangeMain');
var allowChangeMob  = document.getElementById('allowChangeMobile');

// Init mobile from main
deadlineMobile.value   = deadlineMain.value;
allowChangeMob.checked = allowChangeMain.checked;

deadlineMain.addEventListener('change', function()    { deadlineMobile.value   = this.value; });
deadlineMobile.addEventListener('change', function()  { deadlineMain.value     = this.value; });
allowChangeMain.addEventListener('change', function() { allowChangeMob.checked = this.checked; });
allowChangeMob.addEventListener('change', function()  { allowChangeMain.checked = this.checked; });

/* ── Options ── */
var optionCount = document.querySelectorAll('[data-option]').length;

document.getElementById('addOption').addEventListener('click', function() {
    optionCount++;
    var row = document.createElement('div');
    row.className = 'ios-option-row';
    row.setAttribute('data-option', '');
    row.innerHTML =
        '<div class="ios-option-num">' + optionCount + '</div>' +
        '<input type="text" name="options[]" class="form-control" placeholder="Option ' + optionCount + '" maxlength="200">' +
        '<button type="button" class="ios-remove-btn remove-option" title="Remove option"><i class="fas fa-times"></i></button>';
    document.getElementById('optionsList').appendChild(row);
    row.querySelector('input').focus();
    renumber();
});

document.getElementById('optionsList').addEventListener('click', function(e) {
    if (!e.target.closest('.remove-option')) return;
    var rows = document.querySelectorAll('[data-option]');
    if (rows.length <= 2) { alert('A poll must have at least 2 options.'); return; }
    e.target.closest('[data-option]').remove();
    renumber();
});

function renumber() {
    document.querySelectorAll('[data-option] .ios-option-num').forEach(function(el, i) {
        el.textContent = i + 1;
    });
    document.querySelectorAll('[data-option] input').forEach(function(el, i) {
        el.placeholder = 'Option ' + (i + 1);
    });
    optionCount = document.querySelectorAll('[data-option]').length;
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
