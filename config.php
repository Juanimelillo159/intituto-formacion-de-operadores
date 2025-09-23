<?php
declare(strict_types=1);

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'formacionoperadores');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// TODO: Cambiar APP_URL cuando publiques el sitio.
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost/intituto-formacion-de-operadores', '/'));

// TODO: Cambiar estas credenciales SMTP por las reales o cargarlas desde variables de entorno.
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.hostinger.com');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 465));
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'ssl');
define('SMTP_USER', getenv('SMTP_USER') ?: 'pruebas@institutodeoperadores.com');
define('SMTP_PASS', getenv('SMTP_PASS') ?: 'Ju4ni159@');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'pruebas@institutodeoperadores.com');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'Instituto de Operadores');

function getPdo(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $exception) {
        http_response_code(500);
        die('Error de conexion a la base de datos.');
    }

    return $pdo;
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