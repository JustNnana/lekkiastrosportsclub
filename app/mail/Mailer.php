<?php
/**
 * Lekki Astro Sports Club — Mailer
 * Thin wrapper around PHPMailer.
 * Install: composer require phpmailer/phpmailer
 * Or download PHPMailer and require it manually.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private PHPMailer $mail;

    public function __construct()
    {
        // Auto-load PHPMailer (Composer or manual)
        $composerAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        } else {
            // Manual include path (place PHPMailer src/ in app/mail/phpmailer/)
            require_once __DIR__ . '/phpmailer/Exception.php';
            require_once __DIR__ . '/phpmailer/PHPMailer.php';
            require_once __DIR__ . '/phpmailer/SMTP.php';
        }

        $this->mail = new PHPMailer(true);

        $this->mail->isSMTP();
        $this->mail->Host       = MAIL_HOST;
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = MAIL_USERNAME;
        $this->mail->Password   = MAIL_PASSWORD;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = MAIL_PORT;
        $this->mail->CharSet    = 'UTF-8';

        $this->mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    }

    /** Send a plain-text + HTML email */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $plainText = ''): bool
    {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->Subject = $subject;
            $this->mail->isHTML(true);
            $this->mail->Body    = $this->wrapInTemplate($subject, $htmlBody);
            $this->mail->AltBody = $plainText ?: strip_tags($htmlBody);
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    /** Wrap email body in a consistent branded template */
    private function wrapInTemplate(string $title, string $body): string
    {
        $siteName = SITE_NAME;
        $year     = date('Y');
        $baseUrl  = BASE_URL;

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title}</title>
        </head>
        <body style="margin:0;padding:0;background:#f4f6f8;font-family:'Helvetica Neue',Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;padding:40px 20px;">
                <tr><td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.08);">
                        <!-- Header -->
                        <tr><td style="background:#00a76f;padding:32px 40px;text-align:center;">
                            <h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700;">{$siteName}</h1>
                        </td></tr>
                        <!-- Body -->
                        <tr><td style="padding:40px;color:#1c252e;font-size:15px;line-height:1.6;">
                            {$body}
                        </td></tr>
                        <!-- Footer -->
                        <tr><td style="background:#f9fafb;padding:24px 40px;text-align:center;border-top:1px solid #dfe3e8;">
                            <p style="margin:0;color:#919eab;font-size:13px;">
                                &copy; {$year} {$siteName}. All rights reserved.<br>
                                <a href="{$baseUrl}" style="color:#00a76f;text-decoration:none;">{$baseUrl}</a>
                            </p>
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
