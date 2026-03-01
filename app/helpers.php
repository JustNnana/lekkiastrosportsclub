<?php
/**
 * Lekki Astro Sports Club
 * Global helper functions
 */

// ===== OUTPUT SANITIZATION =====

/** Escape output to prevent XSS — use on every echoed variable */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Sanitize a string for database input (trim + strip tags) */
function sanitize(string $input): string
{
    return trim(strip_tags($input));
}

// ===== FLASH MESSAGES =====

/** Store a flash message in the session */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Retrieve and clear all flash messages */
function getFlashMessages(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Shorthand flash helpers */
function flashSuccess(string $msg): void { setFlash('success', $msg); }
function flashError(string $msg): void   { setFlash('error',   $msg); }
function flashWarning(string $msg): void { setFlash('warning', $msg); }
function flashInfo(string $msg): void    { setFlash('info',    $msg); }

// ===== CSRF PROTECTION =====

/** Generate (or retrieve existing) CSRF token */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Render a hidden CSRF input field */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Validate the submitted CSRF token — call at top of every POST handler */
function verifyCsrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $submitted)) {
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
}

// ===== MEMBER ID GENERATION =====

/** Generate next member ID: SC/YYYY/000001 */
function generateMemberId(): string
{
    $db   = Database::getInstance();
    $year = date('Y');

    $row = $db->fetchOne(
        "SELECT member_id FROM members WHERE member_id LIKE ? ORDER BY id DESC LIMIT 1",
        [MEMBER_ID_PREFIX . '/' . $year . '/%']
    );

    if ($row) {
        $last   = (int) substr($row['member_id'], -6);
        $next   = $last + 1;
    } else {
        $next = 1;
    }

    return MEMBER_ID_PREFIX . '/' . $year . '/' . str_pad($next, 6, '0', STR_PAD_LEFT);
}

// ===== URL & REDIRECT =====

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

function currentUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// ===== ACTIVE NAV HELPER =====

function isActive(string $path): string
{
    return str_contains($_SERVER['REQUEST_URI'], $path) ? 'active' : '';
}

// ===== DATE / TIME =====

function timeGreeting(): string
{
    $hour = (int) date('H');
    if ($hour < 12) return 'Good Morning';
    if ($hour < 18) return 'Good Afternoon';
    return 'Good Evening';
}

function formatDate(string $date, string $format = 'd M Y'): string
{
    return date($format, strtotime($date));
}

function formatCurrency(float $amount, string $symbol = '₦'): string
{
    return $symbol . number_format($amount, 2);
}

// ===== PAGINATION =====

function paginate(int $total, int $perPage, int $currentPage): array
{
    $totalPages = (int) ceil($total / $perPage);
    $offset     = ($currentPage - 1) * $perPage;

    return [
        'total'        => $total,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'offset'       => $offset,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
    ];
}

// ===== FILE UPLOAD =====

function uploadFile(array $file, string $subDir = ''): string|false
{
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;
    if (!in_array($file['type'], ALLOWED_IMAGE_TYPES, true)) return false;

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('lasc_', true) . '.' . strtolower($ext);
    $dir      = UPLOAD_PATH . ($subDir ? rtrim($subDir, '/') . '/' : '');

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return ($subDir ? $subDir . '/' : '') . $filename;
    }

    return false;
}

// ===== INITIALS AVATAR =====

function getInitials(string $name): string
{
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper($part[0] ?? '');
    }
    return $initials ?: '?';
}

// ===== STATUS BADGE =====

function statusBadge(string $status): string
{
    $map = [
        'active'    => ['badge-success',  'Active'],
        'inactive'  => ['badge-danger',   'Inactive'],
        'suspended' => ['badge-warning',  'Suspended'],
        'pending'   => ['badge-warning',  'Pending'],
        'paid'      => ['badge-success',  'Paid'],
        'overdue'   => ['badge-danger',   'Overdue'],
        'reversed'  => ['badge-secondary','Reversed'],
    ];
    [$class, $label] = $map[$status] ?? ['badge-secondary', ucfirst($status)];
    return '<span class="badge ' . $class . '">' . $label . '</span>';
}
