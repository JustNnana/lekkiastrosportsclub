<?php
/**
 * Lekki Astro Sports Club
 * Application Configuration
 */

// Load .env file if it exists
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// ===== SITE CONFIG =====
define('SITE_NAME',    $_ENV['SITE_NAME']    ?? 'Lekki Astro Sports Club');
define('SITE_ABBR',    $_ENV['SITE_ABBR']    ?? 'LASC');
define('BASE_URL',     $_ENV['BASE_URL']     ?? 'http://localhost/lekkiastrosportsclub/');
define('MEMBER_ID_PREFIX', 'SC');

// ===== DATABASE CONFIG =====
define('DB_HOST',     $_ENV['DB_HOST']     ?? 'localhost');
define('DB_NAME',     $_ENV['DB_NAME']     ?? 'lasc_db');
define('DB_USER',     $_ENV['DB_USER']     ?? 'root');
define('DB_PASS',     $_ENV['DB_PASS']     ?? '');
define('DB_CHARSET',  'utf8mb4');

// ===== MAIL CONFIG =====
define('MAIL_HOST',       $_ENV['MAIL_HOST']       ?? 'smtp.gmail.com');
define('MAIL_PORT',       (int)($_ENV['MAIL_PORT'] ?? 587));
define('MAIL_ENCRYPTION', $_ENV['MAIL_ENCRYPTION'] ?? 'tls');
define('MAIL_USERNAME',   $_ENV['MAIL_USERNAME']    ?? '');
define('MAIL_PASSWORD',   $_ENV['MAIL_PASSWORD']    ?? '');
define('MAIL_FROM_EMAIL', $_ENV['MAIL_FROM_EMAIL']  ?? 'noreply@lekkiastro.com');
define('MAIL_FROM_NAME',  $_ENV['MAIL_FROM_NAME']   ?? 'Lekki Astro Sports Club');

// ===== PAYSTACK CONFIG =====
define('PAYSTACK_PUBLIC_KEY',  $_ENV['PAYSTACK_PUBLIC_KEY']  ?? '');
define('PAYSTACK_SECRET_KEY',  $_ENV['PAYSTACK_SECRET_KEY']  ?? '');

// ===== VAPID (Web Push) =====
// Generate with: php setup/generate-vapid-keys.php
define('VAPID_PUBLIC_KEY',   $_ENV['VAPID_PUBLIC_KEY']   ?? '');
define('VAPID_PRIVATE_KEY',  $_ENV['VAPID_PRIVATE_KEY']  ?? '');
define('VAPID_SUBJECT',      $_ENV['VAPID_SUBJECT']      ?? ('mailto:' . ($_ENV['MAIL_FROM_EMAIL'] ?? 'admin@lekkiastro.com')));

// ===== SESSION CONFIG =====
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME',     'lasc_session');

// ===== FILE UPLOAD CONFIG =====
define('UPLOAD_PATH',    dirname(__DIR__) . '/public/assets/uploads/');
define('UPLOAD_URL',     BASE_URL . 'assets/uploads/');
define('MAX_FILE_SIZE',  5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// ===== ENVIRONMENT =====
define('APP_ENV',   $_ENV['APP_ENV']   ?? 'development');
define('APP_DEBUG', $_ENV['APP_DEBUG'] ?? 'true');

// Error reporting
if (APP_DEBUG === 'true') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ===== START SESSION =====
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Autoload core classes
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
