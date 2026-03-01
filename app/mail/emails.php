<?php
/**
 * Lekki Astro Sports Club — Reusable email templates
 * Call these functions from anywhere in the app.
 */

function sendWelcomeEmail(string $email, string $name, string $memberId, string $tempPassword): bool
{
    require_once __DIR__ . '/Mailer.php';

    $loginUrl = BASE_URL . 'public/index.php';
    $body = "
        <h2 style='color:#1c252e;margin-top:0'>Welcome to " . SITE_NAME . "!</h2>
        <p>Dear <strong>" . htmlspecialchars($name) . "</strong>,</p>
        <p>Your member account has been created. Here are your login credentials:</p>
        <table style='background:#f4f6f8;border-radius:8px;padding:20px;width:100%;margin:20px 0;'>
            <tr><td style='padding:6px 0;color:#637381;'>Member ID</td>
                <td style='padding:6px 0;font-weight:700;color:#1c252e;'>{$memberId}</td></tr>
            <tr><td style='padding:6px 0;color:#637381;'>Email</td>
                <td style='padding:6px 0;font-weight:700;color:#1c252e;'>{$email}</td></tr>
            <tr><td style='padding:6px 0;color:#637381;'>Temp Password</td>
                <td style='padding:6px 0;font-weight:700;color:#1c252e;'>{$tempPassword}</td></tr>
        </table>
        <p style='color:#ff5630;font-size:13px;'>⚠ You will be asked to change your password on first login.</p>
        <p style='text-align:center;margin:32px 0;'>
            <a href='{$loginUrl}' style='display:inline-block;background:#00a76f;color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:600;'>
                Sign In Now
            </a>
        </p>
        <p style='color:#637381;font-size:13px;'>If you did not expect this email, please contact your administrator.</p>
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
