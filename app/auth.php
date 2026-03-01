<?php
/**
 * Lekki Astro Sports Club
 * Authentication guard functions
 */

/** Require any logged-in user — redirect to login if not */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('');
    }
}

/** Require admin role (admin or super_admin) */
function requireAdmin(): void
{
    requireLogin();
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true)) {
        redirect('dashboard/');
    }
}

/** Require super_admin role only */
function requireSuperAdmin(): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'super_admin') {
        redirect('dashboard/');
    }
}

/** Check if current user is logged in */
function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/** Check if current user is an admin or super admin */
function isAdmin(): bool
{
    return in_array($_SESSION['role'] ?? '', ['admin', 'super_admin'], true);
}

/** Check if current user is a super admin */
function isSuperAdmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'super_admin';
}

/** Log the user in — call after verifying credentials */
function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
}

/** Destroy the session and log out */
function logoutUser(): void
{
    session_unset();
    session_destroy();

    // Expire the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
}

/** Record last activity — call on every page load to detect session timeout */
function updateLastActivity(): void
{
    if (!empty($_SESSION['user_id'])) {
        if (!empty($_SESSION['last_activity']) &&
            (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
            logoutUser();
            redirect('');
        }
        $_SESSION['last_activity'] = time();
    }
}
