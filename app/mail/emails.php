<?php
/**
 * Lekki Astro Sports Club — Reusable email templates
 * Call these functions from anywhere in the app.
 */

function sendWelcomeEmail(string $email, string $name, string $memberId, string $tempPassword): bool
{
    require_once __DIR__ . '/Mailer.php';

    $loginUrl   = BASE_URL;
    $isAdmin    = str_starts_with($memberId, 'N/A');
    $idLabel    = $isAdmin ? 'Account Type' : 'Member ID';
    $idValue    = $isAdmin ? 'Administrator' : htmlspecialchars($memberId);
    $intro      = $isAdmin
        ? 'An administrator account has been created for you on <strong>' . SITE_NAME . '</strong>.'
        : 'Your member account has been created on <strong>' . SITE_NAME . '</strong>. Welcome aboard!';

    $body = "
        <h2 style='color:#1c252e;margin-top:0'>Welcome to " . SITE_NAME . "!</h2>
        <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>{$intro}</p>
        <p>Here are your login credentials:</p>
        <table style='background:#f4f6f8;border-radius:8px;padding:20px;width:100%;margin:20px 0;border-collapse:collapse;'>
            <tr><td style='padding:8px 0;color:#637381;width:140px;'>{$idLabel}</td>
                <td style='padding:8px 0;font-weight:700;color:#1c252e;'>{$idValue}</td></tr>
            <tr><td style='padding:8px 0;color:#637381;'>Email</td>
                <td style='padding:8px 0;font-weight:700;color:#1c252e;'>" . htmlspecialchars($email) . "</td></tr>
            <tr><td style='padding:8px 0;color:#637381;'>Password</td>
                <td style='padding:8px 0;font-weight:700;font-family:monospace;color:#1c252e;'>{$tempPassword}</td></tr>
        </table>
        <p style='background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px 16px;color:#856404;font-size:13px;margin:0 0 24px;'>
            ⚠ This is a temporary password. You will be required to change it on your first login.
        </p>
        <p style='text-align:center;margin:32px 0;'>
            <a href='{$loginUrl}' style='display:inline-block;background:#00a76f;color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-weight:600;font-size:16px;'>
                Sign In Now →
            </a>
        </p>
        <p style='color:#637381;font-size:13px;'>If you did not expect this email, please ignore it or contact the club administrator.</p>
    ";

    $mailer = new Mailer();
    return $mailer->send($email, $name, 'Welcome to ' . SITE_NAME, $body);
}

function sendPasswordResetEmail(string $email, string $name, string $resetLink): bool
{
    require_once __DIR__ . '/Mailer.php';

    $body = "
        <h2 style='color:#1c252e;margin-top:0'>Password Reset Request</h2>
        <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>We received a request to reset your password. Click the button below to set a new password. This link expires in <strong>1 hour</strong>.</p>
        <p style='text-align:center;margin:32px 0;'>
            <a href='{$resetLink}' style='display:inline-block;background:#00a76f;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;'>
                Reset Password
            </a>
        </p>
        <p style='color:#637381;font-size:13px;'>If you did not request a password reset, you can safely ignore this email. Your password will not change.</p>
        <p style='color:#637381;font-size:13px;'>Or copy this link: <a href='{$resetLink}' style='color:#00a76f;'>{$resetLink}</a></p>
    ";

    $mailer = new Mailer();
    return $mailer->send($email, $name, 'Reset Your Password — ' . SITE_NAME, $body);
}

function sendPaymentReminderEmail(string $email, string $name, string $dueTitle, string $amount, string $dueDate, string $payLink): bool
{
    require_once __DIR__ . '/Mailer.php';

    $body = "
        <h2 style='color:#1c252e;margin-top:0'>Payment Reminder</h2>
        <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>This is a reminder that the following due is coming up:</p>
        <table style='background:#f4f6f8;border-radius:8px;padding:20px;width:100%;margin:20px 0;'>
            <tr><td style='padding:6px 0;color:#637381;'>Due</td>
                <td style='padding:6px 0;font-weight:700;color:#1c252e;'>" . htmlspecialchars($dueTitle) . "</td></tr>
            <tr><td style='padding:6px 0;color:#637381;'>Amount</td>
                <td style='padding:6px 0;font-weight:700;color:#00a76f;'>{$amount}</td></tr>
            <tr><td style='padding:6px 0;color:#637381;'>Due Date</td>
                <td style='padding:6px 0;font-weight:700;color:#ff5630;'>{$dueDate}</td></tr>
        </table>
        <p style='text-align:center;margin:32px 0;'>
            <a href='{$payLink}' style='display:inline-block;background:#00a76f;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;'>
                Pay Now
            </a>
        </p>
    ";

    $mailer = new Mailer();
    return $mailer->send($email, $name, 'Payment Reminder — ' . SITE_NAME, $body);
}

/**
 * Send a general notification email to one recipient.
 * Used for broadcasting new dues, events, polls, announcements, fixtures, etc.
 *
 * @param string $email      Recipient email
 * @param string $name       Recipient name
 * @param string $subject    Email subject line
 * @param string $heading    Bold heading inside the email
 * @param string $message    Main body text (HTML allowed)
 * @param string $url        CTA button URL (optional)
 * @param string $urlLabel   CTA button label (default: "View")
 */
function sendNotificationEmail(
    string $email,
    string $name,
    string $subject,
    string $heading,
    string $message,
    string $url = '',
    string $urlLabel = 'View'
): bool {
    require_once __DIR__ . '/Mailer.php';

    $ctaBlock = $url ? "
        <p style='text-align:center;margin:32px 0;'>
            <a href='{$url}' style='display:inline-block;background:#00a76f;color:#fff;padding:14px 36px;border-radius:8px;text-decoration:none;font-weight:600;font-size:16px;'>
                {$urlLabel} →
            </a>
        </p>" : '';

    $body = "
        <h2 style='color:#1c252e;margin-top:0'>" . htmlspecialchars($heading) . "</h2>
        <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
        {$message}
        {$ctaBlock}
        <p style='color:#637381;font-size:13px;'>This is an automated notification from " . SITE_NAME . ". Please do not reply to this email.</p>
    ";

    $mailer = new Mailer();
    return $mailer->send($email, $name, $subject . ' — ' . SITE_NAME, $body);
}
