<?php
declare(strict_types=1);

// Credenciales principales (por defecto las del hosting)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1:3306');
define('DB_NAME', getenv('DB_NAME') ?: 'u910416176_formacionopera');
define('DB_USER', getenv('DB_USER') ?: 'u910416176_formacionopera');
define('DB_PASS', getenv('DB_PASS') ?: '8wO;T@NIyT');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// Credenciales de respaldo (útiles para desarrollo local, se usan si las principales fallan)
define('DB_FALLBACK_HOST', getenv('DB_FALLBACK_HOST') ?: 'localhost');
define('DB_FALLBACK_NAME', getenv('DB_FALLBACK_NAME') ?: 'formacionoperadores');
define('DB_FALLBACK_USER', getenv('DB_FALLBACK_USER') ?: 'root');
define('DB_FALLBACK_PASS', getenv('DB_FALLBACK_PASS') ?: '');
define('DB_FALLBACK_CHARSET', getenv('DB_FALLBACK_CHARSET') ?: 'utf8mb4');

// TODO: Cambiar APP_URL cuando publiques el sitio.
$detectedAppUrl = '';

$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
if ($host !== "") {
    $https = isset($_SERVER['HTTPS']) ? $_SERVER['HTTPS'] : '';
    $scheme = (!empty($https) && $https !== 'off') ? 'https' : 'http';
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
    $scriptDir = dirname($scriptName);
    $scriptDir = str_replace(chr(92), '/', $scriptDir);
    if ($scriptDir === '/' || $scriptDir === '.') {
        $scriptDir = '';
    }
    $detectedAppUrl = $scheme . '://' . $host . $scriptDir;
}

define('APP_URL', rtrim(getenv('APP_URL') ?: ($detectedAppUrl !== '' ? $detectedAppUrl : 'http://localhost/intituto-formacion-de-operadores'), '/'));

// TODO: Cambiar estas credenciales SMTP por las reales o cargarlas desde variables de entorno.
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.hostinger.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'ssl');
define('SMTP_USER', getenv('SMTP_USER') ?: 'pruebas@institutodeoperadores.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'Ju4ni159@');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'pruebas@institutodeoperadores.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Instituto de Operadores');

// Google OAuth
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '949222209259-qtbapafr0vd1isvc0op1oo4tg6rmdmu0.apps.googleusercontent.com');



function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configs = [
        [
            'host' => DB_HOST,
            'name' => DB_NAME,
            'user' => DB_USER,
            'pass' => DB_PASS,
            'charset' => DB_CHARSET,
        ],
    ];

    $fallbackKey = DB_FALLBACK_HOST . '|' . DB_FALLBACK_NAME . '|' . DB_FALLBACK_USER;
    $primaryKey = DB_HOST . '|' . DB_NAME . '|' . DB_USER;

    if ($fallbackKey !== $primaryKey) {
        $configs[] = [
            'host' => DB_FALLBACK_HOST,
            'name' => DB_FALLBACK_NAME,
            'user' => DB_FALLBACK_USER,
            'pass' => DB_FALLBACK_PASS,
            'charset' => DB_FALLBACK_CHARSET,
        ];
    }

    $lastException = null;

    foreach ($configs as $cfg) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['name'], $cfg['charset']);

        try {
            $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            return $pdo;
        } catch (PDOException $exception) {
            $lastException = $exception;
        }
    }

    if ($lastException !== null) {
        error_log('[DB] Error al conectar: ' . $lastException->getMessage());
    }

    http_response_code(500);
    die('Error de conexion a la base de datos.');
}
function getSmtpConfig(): array
{
    return [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'encryption' => SMTP_ENCRYPTION,
        'username' => SMTP_USER,
        'password' => SMTP_PASS,
        'from_email' => SMTP_FROM_EMAIL,
        'from_name' => SMTP_FROM_NAME,
    ];
}

