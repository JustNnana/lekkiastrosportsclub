<?php
/**
 * Lekki Astro Sports Club
 * Dashboard router — sends each role to the right view
 */

require_once dirname(__DIR__) . '/app/config.php';
require_once dirname(__DIR__) . '/classes/User.php';

requireLogin();
updateLastActivity();

switch ($_SESSION['role'] ?? '') {
    case 'super_admin':
    case 'admin':
        include __DIR__ . '/admin.php';
        break;

    case 'user':
        include __DIR__ . '/user.php';
        break;

    default:
        logoutUser();
        redirect('');
}
