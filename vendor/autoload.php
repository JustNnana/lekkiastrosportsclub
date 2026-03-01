<?php
/**
 * Minimal autoloader for PHPMailer (manual install fallback).
 * Replace this file by running: composer install
 */
spl_autoload_register(function ($class) {
    $map = [
        'PHPMailer\\PHPMailer\\PHPMailer'  => __DIR__ . '/phpmailer/phpmailer/src/PHPMailer.php',
        'PHPMailer\\PHPMailer\\SMTP'       => __DIR__ . '/phpmailer/phpmailer/src/SMTP.php',
        'PHPMailer\\PHPMailer\\Exception'  => __DIR__ . '/phpmailer/phpmailer/src/Exception.php',
    ];
    if (isset($map[$class])) {
        require_once $map[$class];
    }
});
