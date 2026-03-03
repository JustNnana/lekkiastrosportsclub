<?php
/**
 * Polls — Member view (iOS card grid, newest first)
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';
require_once dirname(__DIR__) . '/classes/Poll.php';

requireLogin();

$pageTitle  = 'Polls & Voting';
$pollObj    = new Poll();
$userId     = (int)$_SESSION['user_id'];
$perPage    = 9; // 3 per row × 3 rows

// Active polls
$activePage  = max(1, (int)($_GET['page']  ?? 1));
$activeTotal = $pollObj->countActive();
$activePaged = paginate($activeTotal, $perPage, $activePage);
$activePolls = $pollObj->getActive($activePage, $perPage);

// Closed polls
$closedPage  = max(1, (int)($_GET['cpage'] ?? 1));
$closedTotal = $pollObj->countClosed();
$closedPaged = paginate($closedTotal, $perPage, $closedPage);
$closedPolls = $pollObj->getClosed($closedPage, $perPage);

// Preload options + user vote for every poll we'll display
foreach ($activePolls as &$p) {
    $p['options']   = $pollObj->getOptions($p['id']);
    $p['user_vote'] = $pollObj->getUserVote($p['id'], $userId);
}
unset($p);
foreach ($closedPolls as &$p) {
    $p['options']   = $pollObj->getOptions($p['id']);
    $p['user_vote'] = $pollObj->getUserVote($p['id'], $userId);
}
unset($p);

include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/sidebar.php';
?>

<style>
:root {
    --ios-red:#FF453A; --ios-orange:#FF9F0A; --ios-green:#30D158;
    --ios-teal:#64D2FF; --ios-blue:#0A84FF; --ios-purple:#BF5AF2; --ios-gray:#8E8E93;
}

/* ── Page header ──────────────────────────────────────────── */
.polls-page-header {
    display: flex; align-items: flex-start; justify-content: space-between;
    flex-wrap: wrap; gap: 12px; margin-bottom: 28px;
}
.polls-page-title { font-size: 24px; font-weight: 700; color: var(--text-primary); margin: 0 0 4px; }
.polls-page-sub   { font-size: 14px; color: var(--text-secondary); margin: 0; }
.polls-page-actions { display: flex; gap: 8px; flex-wrap: wrap; }

/* ── Section label ────────────────────────────────────────── */
.polls-section-label {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 16px; margin-top: 4px;
}
.polls-section-label-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.polls-section-label-icon.active { background: rgba(48,209,88,0.15); color: var(--ios-green);  }
.polls-section-label-icon.closed { background: rgba(142,142,147,0.15); color: var(--ios-gray); }
.polls-section-label-text { font-size: 18px; font-weight: 700; color: var(--text-primary); }
.polls-section-label-count {
    font-size: 13px; font-weight: 600; color: var(--text-secondary);
    background: var(--bg-secondary); border: 1px solid var(--border-color);
    padding: 2px 10px; border-radius: 20px;
}

/* ── Poll Card Grid ───────────────────────────────────────── */
.ios-polls-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

/* ── Poll Card ────────────────────────────────────────────── */
.ios-poll-card {
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.ios-poll-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.12);
}

.ios-poll-card-body { padding: 20px; flex: 1; }

.ios-poll-card-top {
    display: flex; align-items: flex-start; justify-content: space-between;
    gap: 8px; margin-bottom: 14px;
}
.ios-poll-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.ios-poll-status-chip {
    font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 8px;
}
.ios-poll-status-chip.active { background: rgba(48,209,88,0.12); color: var(--ios-green); }
.ios-poll-status-chip.closed { background: rgba(142,142,147,0.12); color: var(--ios-gray); }
.ios-poll-voted-chip {
    font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 8px;
    background: rgba(10,132,255,0.12); color: var(--ios-blue);
}

.ios-poll-question {
    font-size: 15px; font-weight: 700; color: var(--text-primary);
    margin: 0 0 16px; line-height: 1.45;
}

/* ── Options & Bars ───────────────────────────────────────── */
.ios-poll-options { display: flex; flex-direction: column; gap: 10px; }
.ios-poll-option { }
.ios-poll-option.my-vote .ios-poll-option-label { color: var(--ios-blue); font-weight: 600; }

.ios-poll-option-row {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 5px; gap: 8px;
}
.ios-poll-option-label {
    font-size: 13px; color: var(--text-primary); font-weight: 500;
    flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ios-poll-option-stat {
    font-size: 12px; color: var(--text-muted); flex-shrink: 0; white-space: nowrap;
}

.ios-poll-bar-track {
    height: 6px; background: var(--bg-secondary); border-radius: 3px; overflow: hidden;
}
.ios-poll-bar-fill {
    height: 100%; border-radius: 3px;
    background: var(--ios-blue);
    transition: width 0.6s ease;
    min-width: 2px;
}
.ios-poll-bar-fill.my-vote { background: var(--ios-green); }
.ios-poll-bar-fill.closed  { background: var(--ios-gray);  }

/* show max 5 options on card, rest hidden */
.ios-poll-option:nth-child(n+6) { display: none; }
.ios-poll-more-opts {
    font-size: 12px; color: var(--text-muted); margin-top: 4px; padding-left: 2px;
}

/* ── Card Footer ──────────────────────────────────────────── */
.ios-poll-card-footer {
    padding: 14px 20px;
    border-top: 1px solid var(--border-color);
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px;
}
.ios-poll-footer-left {
    display: flex; flex-direction: column; gap: 2px;
}
.ios-poll-vote-count {
    font-size: 13px; font-weight: 600; color: var(--text-primary);
}
.ios-poll-time-left {
    font-size: 11px; color: var(--ios-orange); font-weight: 500;
}
.ios-poll-ended-label {
    font-size: 11px; color: var(--ios-red); font-weight: 500;
}

.ios-poll-cta-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: 10px;
    background: var(--text-primary); color: var(--bg-primary);
    font-size: 13px; font-weight: 600; text-decoration: none;
    transition: opacity 0.2s ease; white-space: nowrap; flex-shrink: 0;
}
.ios-poll-cta-btn:hover  { opacity: 0.8; color: var(--bg-primary); }
.ios-poll-cta-btn.results {
    background: var(--bg-secondary); color: var(--text-secondary);
    border: 1px solid var(--border-color);
}
.ios-poll-cta-btn.results:hover { color: var(--text-primary); }

/* ── Empty State ──────────────────────────────────────────── */
.ios-polls-empty {
    text-align: center; padding: 48px 24px;
    background: var(--bg-primary); border: 1px solid var(--border-color);
    border-radius: 16px; margin-bottom: 24px;
}
.ios-polls-empty-icon { font-size: 52px; opacity: 0.3; margin-bottom: 16px; }
.ios-polls-empty-title { font-size: 17px; font-weight: 600; color: var(--text-primary); margin: 0 0 6px; }
.ios-polls-empty-sub   { font-size: 14px; color: var(--text-secondary); margin: 0; }

/* ── iOS Pagination ───────────────────────────────────────── */
.ios-pagination-wrap { display: flex; flex-direction: column; align-items: center; gap: 6px; margin-bottom: 32px; }
.ios-pagination { display: flex; align-items: center; justify-content: center; gap: 6px; }
.ios-page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 14px; font-weight: 500; text-decoration: none; color: var(--text-secondary); background: var(--bg-secondary); border: 1px solid var(--border-color); transition: all 0.2s; }
.ios-page-btn:hover  { background: var(--border-color); color: var(--text-primary); }
.ios-page-btn.active { background: var(--ios-blue); border-color: var(--ios-blue); color: white; }
.ios-pagination-info { font-size: 12px; color: var(--text-muted); }

/* ── Responsive ───────────────────────────────────────────── */
@media (max-width: 1024px) {
    .ios-polls-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 768px) {
    .content-header { display: none !important; }
    .polls-page-header { margin-bottom: 20px; }
    .polls-page-title { font-size: 20px; }
    .ios-polls-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
    .ios-poll-card-body { padding: 16px; }
    .ios-poll-question { font-size: 14px; }
    .ios-poll-card-footer { padding: 12px 16px; }
}
@media (max-width: 480px) {
    .ios-polls-grid { grid-template-columns: 1fr; }
}
</style>

<!-- Page Header -->
<div class="polls-page-header">
    <div>
        <h1 class="polls-page-title"><i class="fas fa-poll" style="color:var(--ios-purple);margin-right:10px"></i>Polls & Voting</h1>
        <p class="polls-page-sub">Cast your vote and see what the club thinks.</p>
    </div>
    <?php if (isAdmin()): ?>
    <div class="polls-page-actions">
        <a href="<?php echo BASE_URL; ?>polls/manage.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-cog me-1"></i> Manage
        </a>
        <a href="<?php echo BASE_URL; ?>polls/form.php" class="btn btn-primary btn-sm">
            <i class="fas fa-plus me-1"></i> New Poll
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ═══ ACTIVE POLLS ═══════════════════════════════════════════ -->
<div class="polls-section-label">
    <div class="polls-section-label-icon active"><i class="fas fa-vote-yea"></i></div>
    <span class="polls-section-label-text">Active Polls</span>
    <span class="polls-section-label-count"><?php echo number_format($activeTotal); ?></span>
</div>

<?php if (empty($activePolls)): ?>
<div class="ios-polls-empty">
    <div class="ios-polls-empty-icon">🗳️</div>
    <p class="ios-polls-empty-title">No active polls right now</p>
    <p class="ios-polls-empty-sub">Check back soon — polls will appear here when created.</p>
</div>

<?php else: ?>
<div class="ios-polls-grid">
    <?php foreach ($activePolls as $p):
        $voted    = $p['user_vote'];
        $options  = $p['options'];
        $total    = (int)$p['total_votes'];
        $timeLeft = strtotime($p['deadline']) - time();
        $days     = max(0, floor($timeLeft / 86400));
        $hours    = max(0, floor(($timeLeft % 86400) / 3600));
        $mins     = max(0, floor(($timeLeft % 3600) / 60));
        if ($days > 0)         $timeStr = "{$days}d {$hours}h left";
        elseif ($hours > 0)    $timeStr = "{$hours}h {$mins}m left";
        elseif ($timeLeft > 0) $timeStr = "{$mins}m left";
        else                   $timeStr = 'Closing soon';
        $extraCount = max(0, count($options) - 5);
    ?>
    <div class="ios-poll-card">
        <div class="ios-poll-card-body">
            <div class="ios-poll-card-top">
                <div class="ios-poll-chips">
                    <span class="ios-poll-status-chip active">Active</span>
                    <?php if ($voted !== null): ?>
                    <span class="ios-poll-voted-chip"><i class="fas fa-check" style="font-size:9px;margin-right:3px"></i>Voted</span>
                    <?php endif; ?>
                </div>
            </div>
            <h3 class="ios-poll-question"><?php echo e($p['question']); ?></h3>
            <div class="ios-poll-options">
                <?php foreach ($options as $opt):
                    $pct      = $total > 0 ? round($opt['votes'] / $total * 100) : 0;
                    $isMyVote = (int)$opt['id'] === (int)$voted;
                ?>
                <div class="ios-poll-option <?php echo $isMyVote ? 'my-vote' : ''; ?>">
                    <div class="ios-poll-option-row">
                        <span class="ios-poll-option-label">
                            <?php if ($isMyVote): ?><i class="fas fa-check-circle" style="color:var(--ios-blue);font-size:11px;margin-right:4px"></i><?php endif; ?>
                            <?php echo e($opt['option_text']); ?>
                        </span>
                        <span class="ios-poll-option-stat"><?php echo number_format($opt['votes']); ?> &middot; <?php echo $pct; ?>%</span>
                    </div>
                    <div class="ios-poll-bar-track">
                        <div class="ios-poll-bar-fill <?php echo $isMyVote ? 'my-vote' : ''; ?>" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($extraCount > 0): ?>
                <p class="ios-poll-more-opts">+<?php echo $extraCount; ?> more option<?php echo $extraCount > 1 ? 's' : ''; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="ios-poll-card-footer">
            <div class="ios-poll-footer-left">
                <span class="ios-poll-vote-count"><?php echo number_format($total); ?> vote<?php echo $total != 1 ? 's' : ''; ?></span>
                <span class="ios-poll-time-left"><i class="fas fa-clock" style="font-size:10px;margin-right:3px"></i><?php echo $timeStr; ?></span>
            </div>
            <a href="<?php echo BASE_URL; ?>polls/view.php?id=<?php echo $p['id']; ?>" class="ios-poll-cta-btn">
                <?php echo $voted !== null ? 'View Results' : 'Vote Now'; ?>
                <i class="fas fa-arrow-right" style="font-size:11px"></i>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($activePaged['total_pages'] > 1): ?>
<div class="ios-pagination-wrap">
    <div class="ios-pagination">
        <?php if ($activePaged['has_prev']): ?>
        <a href="?page=<?php echo $activePage - 1; ?>" class="ios-page-btn"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = max(1, $activePage - 2); $i <= min($activePaged['total_pages'], $activePage + 2); $i++): ?>
        <a href="?page=<?php echo $i; ?>" class="ios-page-btn <?php echo $i === $activePage ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($activePaged['has_next']): ?>
        <a href="?page=<?php echo $activePage + 1; ?>" class="ios-page-btn"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <span class="ios-pagination-info">
        Page <?php echo $activePage; ?> of <?php echo $activePaged['total_pages']; ?>
    </span>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ═══ PAST POLLS ════════════════════════════════════════════ -->
<?php if ($closedTotal > 0): ?>
<div class="polls-section-label" style="margin-top:12px">
    <div class="polls-section-label-icon closed"><i class="fas fa-lock"></i></div>
    <span class="polls-section-label-text">Past Polls</span>
    <span class="polls-section-label-count"><?php echo number_format($closedTotal); ?></span>
</div>

<?php if (!empty($closedPolls)): ?>
<div class="ios-polls-grid">
    <?php foreach ($closedPolls as $p):
        $voted      = $p['user_vote'];
        $options    = $p['options'];
        $total      = (int)$p['total_votes'];
        $extraCount = max(0, count($options) - 5);
        // Find voted option text
        $votedText  = null;
        if ($voted !== null) {
            foreach ($options as $opt) {
                if ((int)$opt['id'] === (int)$voted) { $votedText = $opt['option_text']; break; }
            }
        }
    ?>
    <div class="ios-poll-card" style="opacity:0.88">
        <div class="ios-poll-card-body">
            <div class="ios-poll-card-top">
                <div class="ios-poll-chips">
                    <span class="ios-poll-status-chip closed">Ended</span>
                    <?php if ($votedText !== null): ?>
                    <span class="ios-poll-voted-chip"><i class="fas fa-check" style="font-size:9px;margin-right:3px"></i>Voted</span>
                    <?php elseif ($voted === null): ?>
                    <span style="font-size:11px;color:var(--text-muted);padding:3px 0">Did not vote</span>
                    <?php endif; ?>
                </div>
            </div>
            <h3 class="ios-poll-question"><?php echo e($p['question']); ?></h3>
            <div class="ios-poll-options">
                <?php foreach ($options as $opt):
                    $pct      = $total > 0 ? round($opt['votes'] / $total * 100) : 0;
                    $isMyVote = (int)$opt['id'] === (int)$voted;
                    // Determine winner (highest votes)
                    $maxVotes = max(array_column($options, 'votes'));
                    $isWinner = $opt['votes'] == $maxVotes && $maxVotes > 0;
                ?>
                <div class="ios-poll-option <?php echo $isMyVote ? 'my-vote' : ''; ?>">
                    <div class="ios-poll-option-row">
                        <span class="ios-poll-option-label">
                            <?php if ($isWinner): ?><i class="fas fa-trophy" style="color:var(--ios-orange);font-size:10px;margin-right:4px"></i><?php endif; ?>
                            <?php if ($isMyVote): ?><i class="fas fa-check-circle" style="color:var(--ios-blue);font-size:11px;margin-right:4px"></i><?php endif; ?>
                            <?php echo e($opt['option_text']); ?>
                        </span>
                        <span class="ios-poll-option-stat"><?php echo number_format($opt['votes']); ?> &middot; <?php echo $pct; ?>%</span>
                    </div>
                    <div class="ios-poll-bar-track">
                        <div class="ios-poll-bar-fill closed <?php echo $isMyVote ? 'my-vote' : ''; ?>" style="width:<?php echo $pct; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if ($extraCount > 0): ?>
                <p class="ios-poll-more-opts">+<?php echo $extraCount; ?> more option<?php echo $extraCount > 1 ? 's' : ''; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="ios-poll-card-footer">
            <div class="ios-poll-footer-left">
                <span class="ios-poll-vote-count"><?php echo number_format($total); ?> vote<?php echo $total != 1 ? 's' : ''; ?></span>
                <span class="ios-poll-ended-label">Closed <?php echo formatDate($p['deadline'], 'd M Y'); ?></span>
            </div>
            <a href="<?php echo BASE_URL; ?>polls/view.php?id=<?php echo $p['id']; ?>" class="ios-poll-cta-btn results">
                Results <i class="fas fa-chart-bar" style="font-size:11px"></i>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($closedPaged['total_pages'] > 1): ?>
<div class="ios-pagination-wrap">
    <div class="ios-pagination">
        <?php if ($closedPaged['has_prev']): ?>
        <a href="?cpage=<?php echo $closedPage - 1; ?>" class="ios-page-btn"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($i = max(1, $closedPage - 2); $i <= min($closedPaged['total_pages'], $closedPage + 2); $i++): ?>
        <a href="?cpage=<?php echo $i; ?>" class="ios-page-btn <?php echo $i === $closedPage ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($closedPaged['has_next']): ?>
        <a href="?cpage=<?php echo $closedPage + 1; ?>" class="ios-page-btn"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
    <span class="ios-pagination-info">
        Page <?php echo $closedPage; ?> of <?php echo $closedPaged['total_pages']; ?>
    </span>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
