<?php
session_start();
include("../sbd.php");

function establecerMensajeRegistro($mensaje, $tipo = 'info')
{
    $_SESSION['registro_mensaje'] = $mensaje;
    $_SESSION['registro_tipo'] = $tipo;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["registrar_usuario"])) {
    $nombre = isset($_POST["nombre_completo"]) ? trim($_POST["nombre_completo"]) : "";
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $password = isset($_POST["clave"]) ? $_POST["clave"] : "";
    $confirm = isset($_POST["confirmar_clave"]) ? $_POST["confirmar_clave"] : "";

    if ($password !== $confirm) {
        establecerMensajeRegistro('Las contrasenas no coinciden.', 'danger');
        header("Location: ../registro.php");
        exit;
    }

    if ($nombre === "" || $email === "" || $password === "") {
        establecerMensajeRegistro('Todos los campos son obligatorios.', 'danger');
        header("Location: ../registro.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        establecerMensajeRegistro('El correo electronico no es valido.', 'danger');
        header("Location: ../registro.php");
        exit;
    }

    try {
        $sql_verificar = $con->prepare("SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1");
        $sql_verificar->bindParam(":email", $email);
        $sql_verificar->execute();

        if ($sql_verificar->fetch(PDO::FETCH_ASSOC)) {
            establecerMensajeRegistro('Ya existe una cuenta con ese correo electronico.', 'warning');
            header("Location: ../registro.php");
            exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $sql_insertar = $con->prepare("INSERT INTO usuarios (nombre_completo, email, clave, id_permiso) VALUES (:nombre, :email, :clave, :permiso)");
        $sql_insertar->bindParam(":nombre", $nombre);
        $sql_insertar->bindParam(":email", $email);
        $sql_insertar->bindParam(":clave", $hash);
        $permiso = 2;
        $sql_insertar->bindParam(":permiso", $permiso, PDO::PARAM_INT);
        $sql_insertar->execute();

        establecerMensajeRegistro('Cuenta creada correctamente.', 'success');
        header("Location: ../index.php");
        exit;
    } catch (PDOException $e) {
        establecerMensajeRegistro('Ocurrio un error al crear la cuenta.', 'danger');
        header("Location: ../registro.php");
        exit;
    }
} else {
    header("Location: ../registro.php");
    exit;
}
?>
