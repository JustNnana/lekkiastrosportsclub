<?php
/**
 * Single Poll — vote form + live results bars
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Poll.php';

requireLogin();

$id      = (int)($_GET['id'] ?? 0);
$pollObj = new Poll();
$poll    = $pollObj->getById($id);

if (!$poll) {
    flashError('Poll not found.');
    redirect('polls/index.php');
}

$userId    = (int)$_SESSION['user_id'];
$options   = $pollObj->getOptions($id);
$userVote  = $pollObj->getUserVote($id, $userId);
$isClosed  = $pollObj->isClosed($poll);
$totalVotes = (int)$poll['total_votes'];

// Show results if: voted, closed, or admin
$showResults = ($userVote !== null) || $isClosed || isAdmin();

$pageTitle = e($poll['question']);

// Deadline countdown seconds
$secondsLeft = max(0, strtotime($poll['deadline']) - time());

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<div class="content-header">
    <div>
        <nav class="mb-1"><a href="<?php echo BASE_URL; ?>polls/index.php" class="text-muted small">← Polls</a></nav>
        <h1 class="content-title"><?php echo e($poll['question']); ?></h1>
        <p class="content-subtitle">
            <?php if ($isClosed): ?>
                <span class="badge badge-secondary me-1">Closed</span>
            <?php else: ?>
                <span class="badge badge-success me-1">Active</span>
            <?php endif; ?>
            By <?php echo e($poll['creator_name']); ?> ·
            <?php echo number_format($totalVotes); ?> vote<?php echo $totalVotes !== 1 ? 's' : ''; ?>
            <?php if ($poll['allow_change'] && !$isClosed): ?>
            · <small class="text-muted"><i class="fas fa-redo me-1"></i>Vote changes allowed</small>
            <?php endif; ?>
        </p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="d-flex gap-2">
        <?php if (!$isClosed): ?>
        <a href="<?php echo BASE_URL; ?>polls/form.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-edit me-1"></i>Edit
        </a>
        <form method="POST" action="<?php echo BASE_URL; ?>polls/actions.php" class="d-inline">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="close">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-lock me-1"></i>Close Poll</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($poll['description']): ?>
<div class="alert alert-info mb-4" style="font-size:14px">
    <i class="fas fa-info-circle me-2"></i><?php echo e($poll['description']); ?>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-lg-8">

        <!-- Vote form (shown when not yet voted and poll is open) -->
        <?php if (!$isClosed && ($userVote === null || $poll['allow_change'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <?php echo $userVote !== null ? '<i class="fas fa-redo me-2"></i>Change Your Vote' : '<i class="fas fa-vote-yea me-2"></i>Cast Your Vote'; ?>
                </h6>
            </div>
            <div class="card-body">
                <div id="voteOptions">
                    <?php foreach ($options as $opt): ?>
                    <label class="vote-option <?php echo (int)$opt['id'] === $userVote ? 'selected' : ''; ?>"
                           data-id="<?php echo $opt['id']; ?>">
                        <div class="d-flex align-items-center gap-3">
                            <div class="vote-radio <?php echo (int)$opt['id'] === $userVote ? 'checked' : ''; ?>"></div>
                            <span class="fw-semibold"><?php echo e($opt['option_text']); ?></span>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary" id="submitVote"
                            data-poll="<?php echo $id; ?>"
                            data-current="<?php echo $userVote ?? 0; ?>">
                        <i class="fas fa-check me-2"></i><?php echo $userVote !== null ? 'Update Vote' : 'Submit Vote'; ?>
                    </button>
                    <?php if ($userVote !== null): ?>
                    <a href="polls/view.php?id=<?php echo $id; ?>" class="btn btn-secondary ms-2">Cancel</a>
                    <?php endif; ?>
                </div>
                <div id="voteMsg" class="mt-2" style="display:none"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Results -->
        <?php if ($showResults): ?>
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="fas fa-chart-bar me-2"></i>Results</h6>
            </div>
            <div class="card-body">
                <?php if ($totalVotes === 0): ?>
                <p class="text-muted text-center py-3">No votes have been cast yet.</p>
                <?php else: ?>
                <?php foreach ($options as $opt):
                    $pct = $totalVotes > 0 ? round($opt['votes'] / $totalVotes * 100) : 0;
                    $isUserChoice = (int)$opt['id'] === $userVote;
                ?>
                <div class="result-option mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="fw-semibold"><?php echo e($opt['option_text']); ?></span>
                            <?php if ($isUserChoice): ?>
                            <span class="badge badge-success" style="font-size:10px">Your vote</span>
                            <?php endif; ?>
                        </div>
                        <span class="text-muted small fw-semibold"><?php echo $opt['votes']; ?> (<?php echo $pct; ?>%)</span>
                    </div>
                    <div class="progress" style="height:10px;border-radius:6px;background:var(--surface-2)">
                        <div class="progress-bar <?php echo $isUserChoice ? '' : 'bg-secondary'; ?>"
                             style="width:<?php echo $pct; ?>%;border-radius:6px;background:<?php echo $isUserChoice ? 'var(--primary)' : ''; ?>">
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <p class="text-muted small mb-0"><strong><?php echo number_format($totalVotes); ?></strong> total vote<?php echo $totalVotes !== 1 ? 's' : ''; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif ($userVote === null && !$isClosed): ?>
        <div class="card">
            <div class="card-body text-center py-4 text-muted">
                <i class="fas fa-eye-slash fa-2x mb-2 opacity-50"></i>
                <p class="mb-0">Results are revealed after voting.</p>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /col -->

    <!-- Sidebar info -->
    <div class="col-12 col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="card-title mb-0">Poll Info</h6></div>
            <div class="card-body">
                <div class="detail-row">
                    <span class="text-muted">Status</span>
                    <span><?php echo $isClosed ? '<span class="badge badge-secondary">Closed</span>' : '<span class="badge badge-success">Active</span>'; ?></span>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Created by</span>
                    <span class="fw-semibold small"><?php echo e($poll['creator_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="text-muted">Deadline</span>
                    <span class="small"><?php echo formatDate($poll['deadline'], 'd M Y, g:i A'); ?></span>
                </div>
                <?php if (!$isClosed && $secondsLeft > 0): ?>
                <div class="detail-row">
                    <span class="text-muted">Time left</span>
                    <span class="fw-semibold text-<?php echo $secondsLeft < 3600 ? 'danger' : 'body'; ?>" id="countdown">
                        <?php
                        $d = floor($secondsLeft / 86400);
                        $h = floor(($secondsLeft % 86400) / 3600);
                        $m = floor(($secondsLeft % 3600) / 60);
                        echo $d > 0 ? "{$d}d {$h}h {$m}m" : ($h > 0 ? "{$h}h {$m}m" : "{$m}m");
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="text-muted">Total votes</span>
                    <span class="fw-semibold"><?php echo number_format($totalVotes); ?></span>
                </div>
                <div class="detail-row" style="border:none">
                    <span class="text-muted">Your status</span>
                    <span>
                        <?php if ($userVote !== null): ?>
                        <span class="badge badge-success">Voted</span>
                        <?php elseif ($isClosed): ?>
                        <span class="text-muted small">Did not vote</span>
                        <?php else: ?>
                        <span class="badge badge-warning">Not yet voted</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.detail-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--border-color); font-size:14px; }
.vote-option {
    display: block; padding: 14px 16px; border: 1.5px solid var(--border-color);
    border-radius: 8px; margin-bottom: 10px; cursor: pointer; transition: all .15s;
    user-select: none;
}
.vote-option:hover  { border-color: var(--primary); background: rgba(var(--primary-rgb),.04); }
.vote-option.selected { border-color: var(--primary); background: rgba(var(--primary-rgb),.08); }
.vote-radio {
    width: 18px; height: 18px; border-radius: 50%;
    border: 2px solid var(--border-color); flex-shrink: 0;
    position: relative; transition: .15s;
}
.vote-radio.checked { border-color: var(--primary); background: var(--primary); }
.vote-radio.checked::after {
    content: ''; position: absolute; width: 6px; height: 6px;
    border-radius: 50%; background: #fff;
    top: 50%; left: 50%; transform: translate(-50%,-50%);
}
</style>

<script>
let selectedOption = <?php echo $userVote ?? 'null'; ?>;

document.querySelectorAll('.vote-option').forEach(el => {
    el.addEventListener('click', function() {
        document.querySelectorAll('.vote-option').forEach(o => {
            o.classList.remove('selected');
            o.querySelector('.vote-radio').classList.remove('checked');
        });
        this.classList.add('selected');
        this.querySelector('.vote-radio').classList.add('checked');
        selectedOption = parseInt(this.dataset.id);
    });
});

const submitBtn = document.getElementById('submitVote');
if (submitBtn) {
    submitBtn.addEventListener('click', function() {
        if (!selectedOption) { showMsg('Please select an option.', 'warning'); return; }
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting…';

        fetch('<?php echo BASE_URL; ?>api/poll-vote.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({poll_id: <?php echo $id; ?>, option_id: selectedOption})
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showMsg(data.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                showMsg(data.message, 'danger');
                this.disabled = false;
                this.innerHTML = '<?php echo $userVote !== null ? "Update Vote" : "Submit Vote"; ?>';
            }
        });
    });
}

function showMsg(msg, type) {
    const el = document.getElementById('voteMsg');
    el.className = 'alert alert-' + type + ' py-2';
    el.textContent = msg;
    el.style.display = 'block';
}

// Countdown timer
<?php if (!$isClosed && $secondsLeft > 0): ?>
let secs = <?php echo $secondsLeft; ?>;
const cdEl = document.getElementById('countdown');
function updateCountdown() {
    if (secs <= 0) { cdEl.textContent = 'Closed'; return; }
    secs--;
    const d = Math.floor(secs / 86400);
    const h = Math.floor((secs % 86400) / 3600);
    const m = Math.floor((secs % 3600) / 60);
    const s = secs % 60;
    cdEl.textContent = d > 0 ? `${d}d ${h}h ${m}m` : (h > 0 ? `${h}h ${m}m ${s}s` : `${m}m ${s}s`);
    if (secs < 3600) cdEl.className = 'fw-semibold text-danger';
}
setInterval(updateCountdown, 1000);
<?php endif; ?>
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
