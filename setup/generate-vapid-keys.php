<?php
/**
 * VAPID Key Generator — CLI only
 *
 * Usage: php setup/generate-vapid-keys.php
 *
 * Generates a VAPID key pair using the minishlink/web-push library.
 * Copy the output values into your .env file.
 *
 * Requires: composer require minishlink/web-push
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line only.');
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "ERROR: vendor/autoload.php not found.\n";
    echo "Run: composer install\n";
    exit(1);
}

require_once $autoload;

use Minishlink\WebPush\VAPID;

try {
    $keys = VAPID::createVapidKeys();

    echo "\n";
    echo "=== VAPID Keys Generated ===\n\n";
    echo "Add these lines to your .env file:\n\n";
    echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . "\n";
    echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . "\n";
    echo "\n";
    echo "IMPORTANT:\n";
    echo "  - Keep the private key secret — never commit it to version control.\n";
    echo "  - The public key is also used in push-notifications.js via the API endpoint.\n";
    echo "  - Regenerating keys will unsubscribe all existing push subscribers.\n\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
