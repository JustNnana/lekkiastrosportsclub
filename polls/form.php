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
            redirect('polls/view.php?id=' . $id);
        } else {
            $newId = $pollObj->create($data, array_values($options));
            flashSuccess('Poll created and is now active.');
            redirect('polls/view.php?id=' . $newId);
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Poll' : 'New Poll'; ?></h1>
        <p class="content-subtitle"><?php echo $isEdit ? 'Update the poll details.' : 'Create a poll for members to vote on.'; ?></p>
    </div>
    <a href="<?php echo BASE_URL; ?>polls/manage.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left me-2"></i>Back
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST">
    <?php echo csrfField(); ?>

    <div class="row g-4">
        <!-- Left: question + options -->
        <div class="col-12 col-lg-8">
            <div class="card mb-4">
                <div class="card-header"><h6 class="card-title mb-0">Poll Question</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Question <span class="text-danger">*</span></label>
                        <input type="text" name="question" class="form-control"
                               value="<?php echo e($poll['question'] ?? ($_POST['question'] ?? '')); ?>"
                               placeholder="What would you like to ask?" required maxlength="300">
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Description <span class="text-muted">(optional)</span></label>
                        <textarea name="description" class="form-control" rows="3"
                                  placeholder="Add context or details about this poll…"><?php echo e($poll['description'] ?? ($_POST['description'] ?? '')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Options -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Answer Options <span class="text-muted">(min. 2)</span></h6>
                    <button type="button" class="btn btn-secondary btn-sm" id="addOption">
                        <i class="fas fa-plus me-1"></i>Add Option
                    </button>
                </div>
                <div class="card-body">
                    <div id="optionsList">
                        <?php
                        // Pre-fill from existing poll or POST data
                        $prefillOptions = [];
                        if ($isEdit && !empty($existingOptions)) {
                            $prefillOptions = array_column($existingOptions, 'option_text');
                        } elseif (!empty($_POST['options'])) {
                            $prefillOptions = $_POST['options'];
                        } else {
                            $prefillOptions = ['', '']; // two blank defaults
                        }
                        foreach ($prefillOptions as $i => $optText):
                        ?>
                        <div class="option-row d-flex gap-2 mb-2 align-items-center">
                            <span class="text-muted option-num" style="width:22px;text-align:right;flex-shrink:0"><?php echo $i+1; ?>.</span>
                            <input type="text" name="options[]" class="form-control"
                                   value="<?php echo e($optText); ?>"
                                   placeholder="Option <?php echo $i+1; ?>" maxlength="200">
                            <button type="button" class="btn btn-secondary btn-sm remove-option" title="Remove">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted">Enter one option per field. Minimum 2.</small>
                </div>
            </div>
        </div>

        <!-- Right: settings -->
        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-header"><h6 class="card-title mb-0">Poll Settings</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Voting Deadline <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="deadline" class="form-control" required
                               value="<?php echo $poll ? date('Y-m-d\TH:i', strtotime($poll['deadline'])) : (isset($_POST['deadline']) ? $_POST['deadline'] : ''); ?>">
                        <small class="text-muted">Poll auto-closes at this date & time.</small>
                    </div>
                    <hr>
                    <label class="d-flex align-items-start gap-3" style="cursor:pointer">
                        <input type="checkbox" name="allow_change" value="1" class="mt-1"
                               <?php echo ($poll['allow_change'] ?? 0) ? 'checked' : ''; ?>>
                        <div>
                            <div class="fw-semibold">Allow vote changes</div>
                            <small class="text-muted">Members can update their vote before the deadline</small>
                        </div>
                    </label>
                </div>
            </div>

            <?php if ($isEdit): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="alert alert-warning mb-0" style="font-size:13px">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Editing this poll will <strong>reset all existing votes</strong>.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?php echo $isEdit ? 'Update Poll' : 'Create Poll'; ?>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
let optionCount = document.querySelectorAll('.option-row').length;

document.getElementById('addOption').addEventListener('click', function() {
    optionCount++;
    const row = document.createElement('div');
    row.className = 'option-row d-flex gap-2 mb-2 align-items-center';
    row.innerHTML = `
        <span class="text-muted option-num" style="width:22px;text-align:right;flex-shrink:0">${optionCount}.</span>
        <input type="text" name="options[]" class="form-control" placeholder="Option ${optionCount}" maxlength="200">
        <button type="button" class="btn btn-secondary btn-sm remove-option" title="Remove">
            <i class="fas fa-times"></i>
        </button>`;
    document.getElementById('optionsList').appendChild(row);
    row.querySelector('input').focus();
    renumber();
});

document.getElementById('optionsList').addEventListener('click', function(e) {
    if (!e.target.closest('.remove-option')) return;
    const rows = document.querySelectorAll('.option-row');
    if (rows.length <= 2) { alert('A poll must have at least 2 options.'); return; }
    e.target.closest('.option-row').remove();
    renumber();
});

function renumber() {
    document.querySelectorAll('.option-num').forEach((el, i) => { el.textContent = (i+1) + '.'; });
}
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
