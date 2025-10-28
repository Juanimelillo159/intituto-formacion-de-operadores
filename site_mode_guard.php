<?php
declare(strict_types=1);

if (defined('SKIP_SITE_MODE_GUARD') && SKIP_SITE_MODE_GUARD) {
    return;
}

if (!function_exists('site_settings_defaults')) {
    require_once __DIR__ . '/site_settings.php';
}

if (!isset($site_settings) || !is_array($site_settings)) {
    if (isset($con) && $con instanceof PDO) {
        try {
            $site_settings = get_site_settings($con);
        } catch (Throwable $ignored) {
            $site_settings = site_settings_defaults();
        }
    } else {
        $site_settings = site_settings_defaults();
    }
}

$mode = site_settings_get_mode($site_settings);
if ($mode === 'normal') {
    return;
}

$isAdmin = isset($_SESSION['permiso']) && (int)$_SESSION['permiso'] === 1;
if ($isAdmin) {
    return;
}

$title = $mode === 'construction'
    ? 'Estamos trabajando en el sitio'
    : 'Sitio en mantenimiento';
$description = $mode === 'construction'
    ? 'Estamos preparando una nueva versión de la página. Volvé a visitarnos en unas horas.'
    : 'Estamos realizando tareas de soporte. Retomaremos la actividad normal en breve.';
$customNotice = site_settings_get_notice($site_settings);

http_response_code(503);
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, #0a2342, #1b73d1);
            color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            text-align: center;
        }
        .maintenance-card {
            background: rgba(13, 25, 47, 0.85);
            border-radius: 1.5rem;
            padding: 2.5rem 2rem;
            max-width: 520px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);
        }
        .maintenance-card h1 {
            font-size: clamp(1.8rem, 2.5vw, 2.6rem);
            margin-bottom: 1.25rem;
        }
        .maintenance-card p {
            font-size: 1.05rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            color: rgba(248, 249, 250, 0.85);
        }
        .maintenance-card .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            padding: 0.6rem 1.25rem;
            border-radius: 999px;
            background: rgba(27, 115, 209, 0.2);
            color: #aad4ff;
            font-weight: 600;
            letter-spacing: 0.03em;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
<div class="maintenance-card">
    <div class="badge">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
            <path d="M8 0a2 2 0 0 1 1.789 1.106l6 12A2 2 0 0 1 14 16H2a2 2 0 0 1-1.789-2.894l6-12A2 2 0 0 1 8 0Zm0 4a.75.75 0 0 0-.743.648L7.25 4.75v3.5l.007.102A.75.75 0 0 0 8.75 8.25v-3.5l-.007-.102A.75.75 0 0 0 8 4Zm0 6a1 1 0 1 0 .001 2.001A1 1 0 0 0 8 10Z" />
        </svg>
        <?php echo $mode === 'construction' ? 'Modo construcción' : 'Modo soporte'; ?>
    </div>
    <h1><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php if ($customNotice !== ''): ?>
        <p><?php echo nl2br(htmlspecialchars($customNotice, ENT_QUOTES, 'UTF-8')); ?></p>
    <?php endif; ?>
</div>
</body>
</html>
<?php
exit;
