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
            redirect('tournaments/setup.php?id=' . $newId);
        }
    }
}

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <h1 class="content-title"><?php echo $isEdit ? 'Edit Tournament' : 'New Tournament'; ?></h1>
        <p class="content-subtitle">Fill in the details, then set up groups and teams.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>tournaments/manage.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger mb-4"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?php echo e($e); ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="row g-4 justify-content-center">
    <div class="col-12 col-lg-7">
        <form method="POST">
            <?php echo csrfField(); ?>
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Tournament Details</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="200"
                               value="<?php echo e($tour['name'] ?? ($_POST['name'] ?? '')); ?>" placeholder="e.g. LASC Spring Cup 2026">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Tournament details, rules…"><?php echo e($tour['description'] ?? ($_POST['description'] ?? '')); ?></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Format <span class="text-danger">*</span></label>
                            <select name="format" class="form-control" id="formatSelect">
                                <?php foreach (['league'=>'League (Round Robin)','knockout'=>'Knockout','group_knockout'=>'Group Stage + Knockout'] as $v=>$l): ?>
                                <option value="<?php echo $v; ?>" <?php echo ($tour['format']??$_POST['format']??'group_knockout')===$v?'selected':''; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6" id="numGroupsField">
                            <label class="form-label">Number of Groups</label>
                            <input type="number" name="num_groups" class="form-control" min="1" max="16"
                                   value="<?php echo $tour['num_groups'] ?? ($_POST['num_groups'] ?? 2); ?>">
                            <small class="text-muted">For group stage formats.</small>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" style="max-width:200px"
                               value="<?php echo $tour['start_date'] ?? ($_POST['start_date'] ?? ''); ?>">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-arrow-right me-2"></i><?php echo $isEdit ? 'Update & Continue to Setup' : 'Create & Set Up Groups'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const formatSelect = document.getElementById('formatSelect');
const numGroupsField = document.getElementById('numGroupsField');
function toggleGroups() {
    numGroupsField.style.display = formatSelect.value === 'knockout' ? 'none' : '';
}
formatSelect.addEventListener('change', toggleGroups);
toggleGroups();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
