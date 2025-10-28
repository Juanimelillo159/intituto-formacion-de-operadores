<?php
class Database
{
    private array $local = [
        'host' => '127.0.0.1', 'port' => '3306',
        'name' => 'formacionoperadores', 'user' => 'root', 'pass' => '', 'charset' => 'utf8'
    ];

    private array $prod = [
        'host' => '127.0.0.1', 'port' => '3306',
        'name' => 'u910416176_formacionopera', 'user' => 'u910416176_formacionopera',
        'pass' => '8wO;T@NIyT', 'charset' => 'utf8'
    ];

    private function isLocal(): bool
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'cli';
        return in_array($host, ['localhost', '127.0.0.1']) || str_ends_with($host, '.test');
    }

    public function conectar(): PDO
    {
        $c = $this->isLocal() ? $this->local : $this->prod;

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $c['host'], $c['port'], $c['name'], $c['charset']
        );

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        try {
            return new PDO($dsn, $c['user'], $c['pass'], $options);
        } catch (PDOException $e) {
            die('Error de conexión: ' . $e->getMessage());
        }
    }
}

$db  = new Database();
$con = $db->conectar();

require_once __DIR__ . '/site_settings.php';

try {
    $site_settings = get_site_settings($con);
} catch (Throwable $settingsException) {
    error_log('[site_settings] ' . $settingsException->getMessage());
    $site_settings = site_settings_defaults();
}
