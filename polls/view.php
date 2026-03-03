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

$userId     = (int)$_SESSION['user_id'];
$options    = $pollObj->getOptions($id);
$userVote   = $pollObj->getUserVote($id, $userId);
$isClosed   = $pollObj->isClosed($poll);
$totalVotes = (int)$poll['total_votes'];

// Show results if: voted, closed, or admin
$showResults = ($userVote !== null) || $isClosed || isAdmin();

$pageTitle = e($poll['question']);

// Countdown
$secondsLeft = max(0, strtotime($poll['deadline']) - time());

// Creator initial + colour
$creatorInitial = strtoupper(substr($poll['creator_name'] ?? 'A', 0, 1));
$avatarColors   = ['#0A84FF','#30D158','#FF9F0A','#BF5AF2','#FF453A','#64D2FF'];
$creatorColor   = $avatarColors[ord($creatorInitial) % count($avatarColors)];

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
.ios-view-layout {
    display: grid;
    grid-template-columns: 1fr 290px;
    gap: var(--spacing-5);
    max-width: 1100px;
    margin: 0 auto;
}
@media (max-width: 992px) { .ios-view-layout { grid-template-columns: 1fr; } }

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

/* ── 3-dot button ── */
.ios-options-btn {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background .2s ease, transform .15s ease; flex-shrink: 0;
}
.ios-options-btn:hover  { background: var(--border-color); }
.ios-options-btn:active { transform: scale(.95); }
.ios-options-btn i { color: var(--text-primary); font-size: 16px; }

/* ── Poll question header ── */
.ios-poll-question {
    padding: 20px;
    border-bottom: 1px solid var(--border-color);
}
.ios-poll-q-text {
    font-size: 18px; font-weight: 700; color: var(--text-primary); line-height: 1.4;
    margin: 0 0 10px;
}
.ios-poll-meta {
    display: flex; align-items: center; gap: 6px;
    flex-wrap: wrap; font-size: 13px; color: var(--text-secondary);
}
.ios-poll-meta-avatar {
    width: 22px; height: 22px; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: 10px; color: #fff; flex-shrink: 0;
}
.ios-status-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 8px;
    font-size: 11px; font-weight: 600;
}
.ios-status-badge.active   { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-status-badge.closed   { background: rgba(142,142,147,.15); color: var(--text-muted); }
.ios-status-badge.voted    { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-status-badge.pending  { background: rgba(255,159,10,.15); color: var(--ios-orange); }

/* ── Vote options ── */
.ios-vote-options { padding: 16px 20px; display: flex; flex-direction: column; gap: 10px; }
.ios-vote-option {
    display: flex; align-items: center; gap: 14px;
    padding: 14px 16px;
    border: 1.5px solid var(--border-color);
    border-radius: 12px;
    cursor: pointer; user-select: none;
    transition: border-color .15s, background .15s;
    background: var(--bg-secondary);
}
.ios-vote-option:hover    { border-color: var(--primary); background: rgba(var(--primary-rgb),.04); }
.ios-vote-option.selected { border-color: var(--primary); background: rgba(var(--primary-rgb),.08); }
.ios-vote-radio {
    width: 22px; height: 22px; border-radius: 50%;
    border: 2px solid var(--border-color); flex-shrink: 0;
    position: relative; transition: .15s;
}
.ios-vote-option.selected .ios-vote-radio {
    border-color: var(--primary); background: var(--primary);
}
.ios-vote-option.selected .ios-vote-radio::after {
    content: ''; position: absolute;
    width: 8px; height: 8px; border-radius: 50%; background: #fff;
    top: 50%; left: 50%; transform: translate(-50%,-50%);
}
.ios-vote-label { font-size: 15px; font-weight: 500; color: var(--text-primary); }

.ios-vote-actions {
    padding: 14px 20px;
    border-top: 1px solid var(--border-color);
    display: flex; align-items: center; gap: 10px;
}
.ios-vote-msg { padding: 0 20px 14px; }

/* ── Results ── */
.ios-results { padding: 16px 20px; display: flex; flex-direction: column; gap: 18px; }
.ios-result-item {}
.ios-result-label-row {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 7px;
}
.ios-result-label {
    display: flex; align-items: center; gap: 8px;
    font-size: 14px; font-weight: 500; color: var(--text-primary);
}
.ios-result-pct { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
.ios-result-bar-track {
    height: 10px; border-radius: 6px;
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    overflow: hidden;
}
.ios-result-bar-fill {
    height: 100%; border-radius: 6px;
    background: var(--text-muted);
    transition: width .6s cubic-bezier(.4,0,.2,1);
}
.ios-result-bar-fill.user-choice { background: var(--primary); }
.ios-result-votes {
    font-size: 11px; color: var(--text-muted); margin-top: 4px;
    text-align: right;
}

.ios-results-footer {
    padding: 12px 20px;
    border-top: 1px solid var(--border-color);
    font-size: 13px; color: var(--text-muted);
    text-align: center;
}

/* ── Hidden results placeholder ── */
.ios-hidden-results {
    padding: 48px 20px; text-align: center;
    color: var(--text-muted);
}
.ios-hidden-results i { font-size: 36px; display: block; margin-bottom: 12px; opacity: .3; }
.ios-hidden-results p { font-size: 14px; margin: 0; }

/* ── Right sidebar ── */
.ios-sidebar { display: flex; flex-direction: column; }
.ios-sidebar-sticky { position: sticky; top: var(--spacing-4); }

/* Detail rows */
.ios-detail-rows { padding: 0; }
.ios-detail-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px; border-bottom: 1px solid var(--border-color);
    font-size: 14px;
}
.ios-detail-row:last-child { border-bottom: none; }
.ios-detail-row-label { color: var(--text-muted); font-size: 13px; }
.ios-detail-row-value { color: var(--text-primary); font-weight: 500; text-align: right; }

/* Countdown */
.ios-countdown {
    font-size: 15px; font-weight: 700; font-variant-numeric: tabular-nums;
    color: var(--text-primary);
}
.ios-countdown.urgent { color: var(--ios-red); }

/* Admin actions */
.ios-admin-actions { padding: var(--spacing-4); display: flex; flex-direction: column; gap: var(--spacing-3); }

/* ── iOS bottom-sheet menu ── */
.ios-menu-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.4);
    backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
    z-index: 9998; opacity: 0; visibility: hidden;
    transition: opacity .3s ease, visibility .3s ease;
}
.ios-menu-backdrop.active { opacity: 1; visibility: visible; }
.ios-menu-modal {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: var(--bg-primary);
    border-radius: 16px 16px 0 0;
    z-index: 9999; transform: translateY(100%);
    transition: transform .3s cubic-bezier(.32,.72,0,1);
    max-height: 85vh; overflow: hidden;
    display: flex; flex-direction: column;
}
.ios-menu-modal.active { transform: translateY(0); }
.ios-menu-handle { width: 36px; height: 5px; background: var(--border-color); border-radius: 3px; margin: 8px auto 4px; flex-shrink: 0; }
.ios-menu-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 20px 16px; border-bottom: 1px solid var(--border-color); flex-shrink: 0; }
.ios-menu-title  { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0; }
.ios-menu-close  { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-secondary); border: none; display: flex; align-items: center; justify-content: center; color: var(--text-secondary); cursor: pointer; transition: background .2s; }
.ios-menu-close:hover { background: var(--border-color); }
.ios-menu-content { padding: 16px; overflow-y: auto; flex: 1; -webkit-overflow-scrolling: touch; }
.ios-menu-section { margin-bottom: 20px; }
.ios-menu-section:last-child { margin-bottom: 0; }
.ios-menu-section-title { font-size: 13px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; padding-left: 4px; }
.ios-menu-card { background: var(--bg-secondary); border-radius: 12px; overflow: hidden; }
.ios-menu-item { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border-color); text-decoration: none; color: var(--text-primary); transition: background .15s; cursor: pointer; width: 100%; background: transparent; border-left: none; border-right: none; border-top: none; font-family: inherit; font-size: inherit; text-align: left; }
button.ios-menu-item { border: none; border-bottom: 1px solid var(--border-color); }
.ios-menu-item:last-child { border-bottom: none; }
.ios-menu-item:active { background: var(--bg-subtle); }
.ios-menu-item-left { display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0; }
.ios-menu-item-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.ios-menu-item-icon.green  { background: rgba(48,209,88,.15);  color: var(--ios-green); }
.ios-menu-item-icon.blue   { background: rgba(10,132,255,.15); color: var(--ios-blue); }
.ios-menu-item-icon.orange { background: rgba(255,159,10,.15); color: var(--ios-orange); }
.ios-menu-item-icon.purple { background: rgba(191,90,242,.15); color: var(--ios-purple); }
.ios-menu-item-icon.red    { background: rgba(255,69,58,.15);  color: var(--ios-red); }
.ios-menu-item-label   { font-size: 15px; font-weight: 500; }
.ios-menu-item-chevron { color: var(--text-muted); font-size: 12px; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .ios-sidebar { display: none; }
    .ios-view-layout { grid-template-columns: 1fr; }
    .ios-poll-q-text { font-size: 16px; }
    .ios-section-card { border-radius: 12px; }
    .ios-section-header { padding: 14px; }
    .ios-vote-options { padding: 12px 14px; }
    .ios-results { padding: 12px 14px; }
    .ios-vote-actions { padding: 12px 14px; }
}
</style>

<!-- ===== DESKTOP PAGE HEADER ===== -->
<div class="content-header d-flex justify-content-between align-items-start">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item">
                    <a href="<?php echo BASE_URL; ?>polls/index.php" class="breadcrumb-link">Polls</a>
                </li>
                <li class="breadcrumb-item active">View</li>
            </ol>
        </nav>
        <h1 class="content-title"><?php echo e($poll['question']); ?></h1>
        <p class="content-subtitle">
            <?php if ($isClosed): ?>
            <span class="ios-status-badge closed"><i class="fas fa-lock" style="font-size:10px"></i> Closed</span>
            <?php else: ?>
            <span class="ios-status-badge active"><i class="fas fa-circle" style="font-size:8px"></i> Active</span>
            <?php endif; ?>
            &nbsp;By <?php echo e($poll['creator_name']); ?> ·
            <?php echo number_format($totalVotes); ?> vote<?php echo $totalVotes !== 1 ? 's' : ''; ?>
            <?php if ($poll['allow_change'] && !$isClosed): ?>
            · <small class="text-muted"><i class="fas fa-redo" style="font-size:11px"></i> Changes allowed</small>
            <?php endif; ?>
        </p>
    </div>
    <?php if (isAdmin() && !$isClosed): ?>
    <div class="d-flex gap-2 flex-shrink-0">
        <a href="<?php echo BASE_URL; ?>polls/form.php?id=<?php echo $id; ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-edit me-1"></i>Edit
        </a>
        <form method="POST" action="<?php echo BASE_URL; ?>polls/actions.php" class="d-inline">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="close">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <button type="submit" class="btn btn-warning btn-sm">
                <i class="fas fa-lock me-1"></i>Close Poll
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- ===== MOBILE HEADER ===== -->
<div class="ios-section-header d-md-none mb-3" style="border-radius:14px;border:1px solid var(--border-color)">
    <div class="ios-section-icon <?php echo $isClosed ? 'orange' : 'green'; ?>">
        <i class="fas fa-<?php echo $isClosed ? 'lock' : 'poll'; ?>"></i>
    </div>
    <div class="ios-section-title">
        <h5><?php echo e($poll['question']); ?></h5>
        <p>
            <?php echo $isClosed ? 'Closed' : 'Active'; ?> ·
            <?php echo number_format($totalVotes); ?> vote<?php echo $totalVotes !== 1 ? 's' : ''; ?>
        </p>
    </div>
    <button class="ios-options-btn" onclick="openIosMenu()"><i class="fas fa-ellipsis-v"></i></button>
</div>

<div class="ios-view-layout">

    <!-- ===== LEFT: MAIN CONTENT ===== -->
    <div>

        <!-- Vote form card -->
        <?php if (!$isClosed && ($userVote === null || $poll['allow_change'])): ?>
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-icon <?php echo $userVote !== null ? 'orange' : 'green'; ?>">
                    <i class="fas fa-<?php echo $userVote !== null ? 'redo' : 'vote-yea'; ?>"></i>
                </div>
                <div class="ios-section-title">
                    <h5><?php echo $userVote !== null ? 'Change Your Vote' : 'Cast Your Vote'; ?></h5>
                    <p><?php echo $userVote !== null ? 'Select a different option below' : 'Select one option and submit'; ?></p>
                </div>
            </div>

            <div class="ios-vote-options" id="voteOptions">
                <?php foreach ($options as $opt): ?>
                <div class="ios-vote-option <?php echo (int)$opt['id'] === $userVote ? 'selected' : ''; ?>"
                     data-id="<?php echo $opt['id']; ?>">
                    <div class="ios-vote-radio"></div>
                    <span class="ios-vote-label"><?php echo e($opt['option_text']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="ios-vote-actions">
                <button class="btn btn-primary" id="submitVote"
                        data-poll="<?php echo $id; ?>"
                        data-current="<?php echo $userVote ?? 0; ?>">
                    <i class="fas fa-check me-2"></i><?php echo $userVote !== null ? 'Update Vote' : 'Submit Vote'; ?>
                </button>
                <?php if ($userVote !== null): ?>
                <a href="<?php echo BASE_URL; ?>polls/view.php?id=<?php echo $id; ?>" class="btn btn-secondary">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="ios-vote-msg" id="voteMsg" style="display:none"></div>
        </div>
        <?php endif; ?>

        <!-- Results card -->
        <?php if ($showResults): ?>
        <div class="ios-section-card">
            <div class="ios-section-header">
                <div class="ios-section-icon blue">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="ios-section-title">
                    <h5>Results</h5>
                    <p><?php echo number_format($totalVotes); ?> vote<?php echo $totalVotes !== 1 ? 's' : ''; ?> cast</p>
                </div>
            </div>

            <?php if ($totalVotes === 0): ?>
            <div class="ios-hidden-results">
                <i class="fas fa-chart-bar"></i>
                <p>No votes have been cast yet.</p>
            </div>
            <?php else: ?>
            <div class="ios-results">
                <?php foreach ($options as $opt):
                    $pct = $totalVotes > 0 ? round($opt['votes'] / $totalVotes * 100) : 0;
                    $isUserChoice = (int)$opt['id'] === $userVote;
                ?>
                <div class="ios-result-item">
                    <div class="ios-result-label-row">
                        <div class="ios-result-label">
                            <?php if ($isUserChoice): ?>
                            <span class="ios-status-badge voted" style="font-size:10px"><i class="fas fa-check" style="font-size:9px"></i> Your vote</span>
                            <?php endif; ?>
                            <?php echo e($opt['option_text']); ?>
                        </div>
                        <span class="ios-result-pct"><?php echo $pct; ?>%</span>
                    </div>
                    <div class="ios-result-bar-track">
                        <div class="ios-result-bar-fill <?php echo $isUserChoice ? 'user-choice' : ''; ?>"
                             style="width:<?php echo $pct; ?>%"></div>
                    </div>
                    <div class="ios-result-votes"><?php echo number_format($opt['votes']); ?> vote<?php echo $opt['votes'] !== 1 ? 's' : ''; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="ios-results-footer">
                <strong><?php echo number_format($totalVotes); ?></strong> total vote<?php echo $totalVotes !== 1 ? 's' : ''; ?>
                <?php if ($isClosed): ?> · Poll closed<?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php elseif ($userVote === null && !$isClosed): ?>
        <!-- Results hidden until voted -->
        <div class="ios-section-card">
            <div class="ios-hidden-results">
                <i class="fas fa-eye-slash"></i>
                <p>Results are revealed after you vote.</p>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /left -->

    <!-- ===== RIGHT: SIDEBAR ===== -->
    <div class="ios-sidebar">
        <div class="ios-sidebar-sticky">

            <!-- Poll Info card -->
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon blue"><i class="fas fa-info-circle"></i></div>
                    <div class="ios-section-title"><h5>Poll Info</h5></div>
                </div>
                <div class="ios-detail-rows">
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Status</span>
                        <span class="ios-detail-row-value">
                            <?php if ($isClosed): ?>
                            <span class="ios-status-badge closed"><i class="fas fa-lock" style="font-size:9px"></i> Closed</span>
                            <?php else: ?>
                            <span class="ios-status-badge active"><i class="fas fa-circle" style="font-size:7px"></i> Active</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Created by</span>
                        <span class="ios-detail-row-value" style="display:flex;align-items:center;gap:6px">
                            <span class="ios-poll-meta-avatar" style="background:<?php echo $creatorColor; ?>"><?php echo $creatorInitial; ?></span>
                            <?php echo e($poll['creator_name']); ?>
                        </span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Deadline</span>
                        <span class="ios-detail-row-value" style="font-size:12px"><?php echo formatDate($poll['deadline'], 'd M Y, g:i A'); ?></span>
                    </div>
                    <?php if (!$isClosed && $secondsLeft > 0): ?>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Time left</span>
                        <span class="ios-detail-row-value">
                            <span class="ios-countdown <?php echo $secondsLeft < 3600 ? 'urgent' : ''; ?>" id="countdown">
                                <?php
                                $d = floor($secondsLeft / 86400);
                                $h = floor(($secondsLeft % 86400) / 3600);
                                $m = floor(($secondsLeft % 3600) / 60);
                                echo $d > 0 ? "{$d}d {$h}h {$m}m" : ($h > 0 ? "{$h}h {$m}m" : "{$m}m");
                                ?>
                            </span>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Total votes</span>
                        <span class="ios-detail-row-value"><?php echo number_format($totalVotes); ?></span>
                    </div>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Your status</span>
                        <span class="ios-detail-row-value">
                            <?php if ($userVote !== null): ?>
                            <span class="ios-status-badge voted"><i class="fas fa-check" style="font-size:9px"></i> Voted</span>
                            <?php elseif ($isClosed): ?>
                            <span class="ios-status-badge closed">Did not vote</span>
                            <?php else: ?>
                            <span class="ios-status-badge pending"><i class="fas fa-clock" style="font-size:9px"></i> Not yet voted</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php if ($poll['allow_change'] && !$isClosed): ?>
                    <div class="ios-detail-row">
                        <span class="ios-detail-row-label">Vote changes</span>
                        <span class="ios-detail-row-value" style="color:var(--ios-green);font-size:12px"><i class="fas fa-check-circle"></i> Allowed</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Admin actions card -->
            <?php if (isAdmin()): ?>
            <div class="ios-section-card">
                <div class="ios-section-header">
                    <div class="ios-section-icon purple"><i class="fas fa-cog"></i></div>
                    <div class="ios-section-title"><h5>Admin Actions</h5></div>
                </div>
                <div class="ios-admin-actions">
                    <?php if (!$isClosed): ?>
                    <a href="<?php echo BASE_URL; ?>polls/form.php?id=<?php echo $id; ?>" class="btn btn-secondary w-100">
                        <i class="fas fa-edit me-2"></i>Edit Poll
                    </a>
                    <form method="POST" action="<?php echo BASE_URL; ?>polls/actions.php">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="close">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <button type="submit" class="btn btn-warning w-100">
                            <i class="fas fa-lock me-2"></i>Close Poll
                        </button>
                    </form>
                    <?php else: ?>
                    <p class="text-muted text-center mb-0" style="font-size:13px"><i class="fas fa-lock me-1"></i>This poll is closed</p>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>polls/index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-list me-2"></i>All Polls
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Back link -->
            <div class="ios-section-card">
                <div class="ios-admin-actions">
                    <a href="<?php echo BASE_URL; ?>polls/index.php" class="btn btn-secondary w-100">
                        <i class="fas fa-arrow-left me-2"></i>Back to Polls
                    </a>
                </div>
            </div>

        </div>
    </div><!-- /sidebar -->

</div><!-- /ios-view-layout -->

<!-- ===== iOS MENU MODAL (mobile) ===== -->
<div class="ios-menu-backdrop" id="iosMenuBackdrop" onclick="closeIosMenu()"></div>
<div class="ios-menu-modal" id="iosMenuModal">
    <div class="ios-menu-handle"></div>
    <div class="ios-menu-header">
        <h5 class="ios-menu-title">Poll Options</h5>
        <button class="ios-menu-close" onclick="closeIosMenu()"><i class="fas fa-times"></i></button>
    </div>
    <div class="ios-menu-content">

        <!-- Poll info (mobile) -->
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Poll Info</p>
            <div class="ios-menu-card">
                <div class="ios-menu-item" style="cursor:default">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-calendar-alt"></i></div>
                        <div>
                            <div class="ios-menu-item-label" style="font-size:13px">Deadline</div>
                            <div style="font-size:12px;color:var(--text-secondary)"><?php echo formatDate($poll['deadline'], 'd M Y, g:i A'); ?></div>
                        </div>
                    </div>
                </div>
                <div class="ios-menu-item" style="cursor:default">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon <?php echo $userVote !== null ? 'green' : 'orange'; ?>">
                            <i class="fas fa-<?php echo $userVote !== null ? 'check' : 'clock'; ?>"></i>
                        </div>
                        <div>
                            <div class="ios-menu-item-label" style="font-size:13px">Your Status</div>
                            <div style="font-size:12px;color:var(--text-secondary)">
                                <?php echo $userVote !== null ? 'Voted' : ($isClosed ? 'Did not vote' : 'Not yet voted'); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isAdmin() && !$isClosed): ?>
        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Admin Actions</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>polls/form.php?id=<?php echo $id; ?>" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon blue"><i class="fas fa-edit"></i></div>
                        <div class="ios-menu-item-label">Edit Poll</div>
                    </div>
                    <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                </a>
                <form method="POST" action="<?php echo BASE_URL; ?>polls/actions.php">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="close">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" class="ios-menu-item">
                        <div class="ios-menu-item-left">
                            <div class="ios-menu-item-icon orange"><i class="fas fa-lock"></i></div>
                            <div class="ios-menu-item-label">Close Poll</div>
                        </div>
                        <i class="fas fa-chevron-right ios-menu-item-chevron"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="ios-menu-section">
            <p class="ios-menu-section-title">Navigation</p>
            <div class="ios-menu-card">
                <a href="<?php echo BASE_URL; ?>polls/index.php" class="ios-menu-item">
                    <div class="ios-menu-item-left">
                        <div class="ios-menu-item-icon purple"><i class="fas fa-poll"></i></div>
                        <div class="ios-menu-item-label">All Polls</div>
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
        var dy = e.changedTouches[0].clientY - startY;
        modal.style.transform = '';
        if (dy > 80) closeIosMenu();
    });
})();

/* ── Vote selection ── */
let selectedOption = <?php echo $userVote ?? 'null'; ?>;

document.querySelectorAll('.ios-vote-option').forEach(function(el) {
    el.addEventListener('click', function() {
        document.querySelectorAll('.ios-vote-option').forEach(function(o) {
            o.classList.remove('selected');
        });
        this.classList.add('selected');
        selectedOption = parseInt(this.dataset.id);
    });
});

/* ── Submit vote ── */
var submitBtn = document.getElementById('submitVote');
if (submitBtn) {
    submitBtn.addEventListener('click', function() {
        if (!selectedOption) { showVoteMsg('Please select an option.', 'warning'); return; }
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting…';

        fetch('<?php echo BASE_URL; ?>api/poll-vote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ poll_id: <?php echo $id; ?>, option_id: selectedOption })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showVoteMsg(data.message, 'success');
                setTimeout(function() { location.reload(); }, 800);
            } else {
                showVoteMsg(data.message, 'danger');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<?php echo $userVote !== null ? '<i class="fas fa-check me-2"></i>Update Vote' : '<i class="fas fa-check me-2"></i>Submit Vote'; ?>';
            }
        })
        .catch(function() {
            showVoteMsg('Something went wrong. Please try again.', 'danger');
            submitBtn.disabled = false;
        });
    });
}

function showVoteMsg(msg, type) {
    var el = document.getElementById('voteMsg');
    el.className = 'ios-vote-msg alert alert-' + type + ' py-2';
    el.textContent = msg;
    el.style.display = 'block';
}

/* ── Countdown timer ── */
<?php if (!$isClosed && $secondsLeft > 0): ?>
var secs  = <?php echo $secondsLeft; ?>;
var cdEl  = document.getElementById('countdown');
function updateCountdown() {
    if (!cdEl) return;
    if (secs <= 0) { cdEl.textContent = 'Closed'; cdEl.classList.add('urgent'); return; }
    secs--;
    var d = Math.floor(secs / 86400);
    var h = Math.floor((secs % 86400) / 3600);
    var m = Math.floor((secs % 3600) / 60);
    var s = secs % 60;
    cdEl.textContent = d > 0 ? (d + 'd ' + h + 'h ' + m + 'm')
                     : h > 0 ? (h + 'h ' + m + 'm ' + s + 's')
                     : (m + 'm ' + s + 's');
    if (secs < 3600) cdEl.classList.add('urgent');
}
setInterval(updateCountdown, 1000);
<?php endif; ?>
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
