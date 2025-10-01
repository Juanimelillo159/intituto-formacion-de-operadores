<?php
class Database
{
/*     private $hostname = "localhost";
    private $database = "formacionoperadores";
    private $username = "root";
    private $password = "";
    private $charset = "utf8"; */

        private $hostname = "127.0.0.1:3306";
        private $database = "u910416176_formacionopera";
        private $username = "u910416176_formacionopera";
        private $password = "8wO;T@NIyT";
        private $charset = "utf8";  

    function conectar()
    {
        try {
            $conexion = "mysql:host=" . $this->hostname . "; dbname=" . $this->database . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => FALSE
            ];
            $pdo = new PDO($conexion, $this->username, $this->password, $options);
            return $pdo;
        } catch (PDOException $e) {
            echo 'Error conexion: ' . $e->getMessage();
            exit;
        }
    }
}
$db = new Database();
$con = $db->conectar();
