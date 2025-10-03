<?php
declare(strict_types=1);

// Mercado Pago credentials (can be overridden via environment variables)
if (!defined('MP_ACCESS_TOKEN')) {
    define('MP_ACCESS_TOKEN', getenv('MP_ACCESS_TOKEN') ?: 'APP_USR-697544312345765-091622-213b5d03eba54b706beafeeaf8e3f2ba-1578491289');
}

if (!defined('MP_CLIENT_ID')) {
    define('MP_CLIENT_ID', getenv('MP_CLIENT_ID') ?: '697544312345765');
}

// Base URL where this integration is hosted.
// Update MP_BASE_URL if your deployment uses a different domain or path.
if (!defined('MP_BASE_URL')) {
    $detectedBaseUrl = (function (): string {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return '';
        }

        $https = $_SERVER['HTTPS'] ?? '';
        $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/mp/index.php';
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        return sprintf('%s://%s%s', $scheme, $host, $scriptDir === '' ? '' : $scriptDir);
    })();

    $baseUrl = getenv('MP_BASE_URL') ?: ($detectedBaseUrl !== '' ? $detectedBaseUrl : 'https://example.com/mp');
    define('MP_BASE_URL', rtrim($baseUrl, '/'));
}

define('URL_SUCCESS', MP_BASE_URL . '/success.php');
define('URL_FAILURE', MP_BASE_URL . '/failure.php');
define('URL_PENDING', MP_BASE_URL . '/pending.php');
define('URL_WEBHOOK', MP_BASE_URL . '/webhook.php');
