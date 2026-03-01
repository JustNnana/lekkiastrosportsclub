<?php
/**
 * Cron — Mark overdue dues
 *
 * Run daily via cron:
 *   0 2 * * * php /var/www/html/lekkiastrosportsclub/cron/overdue.php >> /var/log/lasc-cron.log 2>&1
 *
 * This script:
 *   1. Marks `pending` member_dues as `overdue` where the due date has passed.
 *   2. Sends a notification to each affected member.
 *   3. Logs a summary to stdout (captured by cron).
 *
 * IMPORTANT: This script must only be called from the CLI, never via HTTP.
 */

// ─── CLI GUARD ────────────────────────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Access denied.');
}

define('RUNNING_CRON', true);

require_once dirname(__DIR__) . '/app/config.php';

$db    = Database::getInstance();
$now   = date('Y-m-d H:i:s');
$today = date('Y-m-d');

echo "[" . date('Y-m-d H:i:s') . "] LASC Overdue Cron Started\n";

// ─── 1. MARK OVERDUE ─────────────────────────────────────────────────────────
$overdueSql = "UPDATE member_dues
               SET status = 'overdue', updated_at = NOW()
               WHERE status = 'pending'
                 AND due_date IS NOT NULL
                 AND due_date < ?";

try {
    $marked = $db->execute($overdueSql, [$today]);
    echo "[" . date('Y-m-d H:i:s') . "] Marked $marked dues as overdue.\n";
} catch (Throwable $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR marking overdue: " . $e->getMessage() . "\n";
    exit(1);
}

// ─── 2. NOTIFY AFFECTED MEMBERS ──────────────────────────────────────────────
if ($marked > 0) {
    // Fetch members with newly overdue dues (updated in the last minute to catch this run)
    $affected = $db->fetchAll(
        "SELECT md.id AS member_due_id, md.member_id, md.due_id,
                m.user_id, d.name AS due_name, d.amount,
                md.due_date
         FROM member_dues md
         JOIN members m ON m.id = md.member_id
         JOIN dues d ON d.id = md.due_id
         WHERE md.status = 'overdue'
           AND md.updated_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)"
    );

    $notified = 0;
    foreach ($affected as $row) {
        try {
            $message = "Your payment of ₦" . number_format((float)$row['amount']) .
                       " for \"" . $row['due_name'] . "\" was due on " .
                       date('d M Y', strtotime($row['due_date'])) .
                       " and is now overdue. Please settle it as soon as possible.";

            $db->insert(
                "INSERT INTO notifications (user_id, type, title, message, created_at)
                 VALUES (?, 'payment', 'Payment Overdue', ?, NOW())",
                [$row['user_id'], $message]
            );
            $notified++;
        } catch (Throwable $e) {
            echo "[" . date('Y-m-d H:i:s') . "] WARNING: Could not notify user_id={$row['user_id']}: " . $e->getMessage() . "\n";
        }
    }

    echo "[" . date('Y-m-d H:i:s') . "] Sent $notified overdue notifications.\n";
}

// ─── 3. OPTIONAL: SEND UPCOMING DUE REMINDERS (7 days before) ────────────────
$upcoming = $db->fetchAll(
    "SELECT md.id, md.member_id, m.user_id, d.name AS due_name, d.amount, md.due_date
     FROM member_dues md
     JOIN members m ON m.id = md.member_id
     JOIN dues d ON d.id = md.due_id
     WHERE md.status = 'pending'
       AND md.due_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
);

$reminders = 0;
foreach ($upcoming as $row) {
    try {
        $message = "Reminder: Your payment of ₦" . number_format((float)$row['amount']) .
                   " for \"" . $row['due_name'] . "\" is due in 7 days (" .
                   date('d M Y', strtotime($row['due_date'])) . "). Please pay before the deadline.";

        $db->insert(
            "INSERT INTO notifications (user_id, type, title, message, created_at)
             VALUES (?, 'payment', 'Payment Due Soon', ?, NOW())",
            [$row['user_id'], $message]
        );
        $reminders++;
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] WARNING: Could not send reminder to user_id={$row['user_id']}: " . $e->getMessage() . "\n";
    }
}

if ($reminders > 0) {
    echo "[" . date('Y-m-d H:i:s') . "] Sent $reminders upcoming due reminders.\n";
}

echo "[" . date('Y-m-d H:i:s') . "] LASC Overdue Cron Finished\n";
exit(0);
