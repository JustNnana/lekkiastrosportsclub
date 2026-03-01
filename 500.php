<?php
/**
 * 500 — Internal Server Error
 */
http_response_code(500);

$baseUrl  = '/lekkiastrosportsclub/';
$siteName = 'Lekki Astro Sports Club';
try {
    require_once __DIR__ . '/app/config.php';
    $baseUrl  = BASE_URL;
    $siteName = SITE_NAME;
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Server Error | <?php echo htmlspecialchars($siteName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>assets/css/dasher-variables.css">
    <link rel="stylesheet" href="<?php echo $baseUrl; ?>assets/css/error-pages.css">
    <script>
        try {
            var t = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', t);
        } catch(e) {}
    </script>
</head>
<body>
<div class="error-container">
    <div class="error-card">
        <p class="error-number">500</p>
        <h1 class="error-title">Server Error</h1>
        <p class="error-description">
            Something went wrong on our end. Our team has been notified.
            Please try again in a moment or contact the administrator.
        </p>
        <div class="error-actions">
            <a href="<?php echo $baseUrl; ?>dashboard/" class="btn-error-primary">
                Go to Dashboard
            </a>
            <a href="javascript:location.reload()" class="btn-error-secondary">
                Try Again
            </a>
        </div>
        <div class="error-footer">
            <span><?php echo htmlspecialchars($siteName); ?></span>
        </div>
    </div>
</div>
</body>
</html>
