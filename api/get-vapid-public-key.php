<?php
/**
 * API — Return VAPID public key
 * Called by push-notifications.js during init.
 */
require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireLogin();

header('Content-Type: text/plain; charset=utf-8');
echo VAPID_PUBLIC_KEY;
