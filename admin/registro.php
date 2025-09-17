<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../sbd.php");

function establecerMensajeRegistro($mensaje, $tipo = 'info')
{
    $_SESSION['registro_mensaje'] = $mensaje;
    $_SESSION['registro_tipo'] = $tipo;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["registrar_usuario"])) {
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $password = isset($_POST["clave"]) ? $_POST["clave"] : "";
    $confirm = isset($_POST["confirmar_clave"]) ? $_POST["confirmar_clave"] : "";
    $acepta_terminos = isset($_POST["aceptar_terminos"]) && $_POST["aceptar_terminos"] === "on";

    if (!$acepta_terminos) {
        establecerMensajeRegistro('Debes aceptar los terminos y condiciones.', 'danger');
        header("Location: ../registro.php");
        exit;
    }

    if ($password !== $confirm) {
        establecerMensajeRegistro('Las contrasenas no coinciden.', 'danger');
        header("Location: ../registro.php");
        exit;
    }

    if ($email === "" || $password === "") {
        establecerMensajeRegistro('El correo y la contrasena son obligatorios.', 'danger');
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
        $estado = 1;
        $permiso = 2;

        $sql_insertar = $con->prepare("INSERT INTO usuarios (email, clave, id_estado, id_permiso) VALUES (:email, :clave, :estado, :permiso)");
        $sql_insertar->bindParam(":email", $email);
        $sql_insertar->bindParam(":clave", $hash);
        $sql_insertar->bindParam(":estado", $estado, PDO::PARAM_INT);
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
}

header("Location: ../registro.php");
exit;
?>

