<?php
/**
 * 404 — Page Not Found
 */
http_response_code(404);

// Try to load config for BASE_URL; if it fails just use relative path
$baseUrl = '/lekkiastrosportsclub/';
try {
    require_once __DIR__ . '/app/config.php';
    $baseUrl = BASE_URL;
} catch (Throwable $e) {}

$siteName = defined('SITE_NAME') ? SITE_NAME : 'Lekki Astro Sports Club';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Page Not Found | <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>assets/css/dasher-variables.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>assets/css/error-pages.css">
    <script>
        // Instant theme apply
        try {
            var t = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        } catch(e) {}
    </script>
</head>
<body>
<div class="error-container">
    <div class="error-card">
        <p class="error-number">404</p>
        <h1 class="error-title">Page Not Found</h1>
        <p class="error-description">
            The page you're looking for doesn't exist or has been moved.
            Check the URL or navigate back to safety.
        </p>
        <div class="error-actions">
            <a href="<?php echo $baseUrl; ?>dashboard/" class="btn-error-primary">
                Go to Dashboard
            </a>
            <a href="javascript:history.back()" class="btn-error-secondary">
                Go Back
            </a>
        </div>
        <div class="error-footer">
            <span><?php echo htmlspecialchars($siteName); ?></span>
        </div>
    </div>
</div>
</body>
</html>
