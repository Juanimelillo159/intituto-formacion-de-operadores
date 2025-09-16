<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../sbd.php");

function establecerMensajeLogin($mensaje, $tipo = 'danger')
{
    $_SESSION['login_mensaje'] = $mensaje;
    $_SESSION['login_tipo'] = $tipo;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["iniciar_sesion"])) {
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $password = isset($_POST["clave"]) ? $_POST["clave"] : "";

    if ($email === "" || $password === "") {
        establecerMensajeLogin('Debe ingresar correo y contrasena.', 'danger');
        header("Location: ../login.php");
        exit;
    }

    try {
        $sql_usuario = $con->prepare("SELECT id_usuario, email, clave, id_permiso FROM usuarios WHERE email = :email LIMIT 1");
        $sql_usuario->bindParam(":email", $email);
        $sql_usuario->execute();
        $usuario = $sql_usuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            establecerMensajeLogin('No existe una cuenta registrada con ese correo.', 'warning');
            header("Location: ../login.php");
            exit;
        }

        if (!password_verify($password, $usuario["clave"])) {
            establecerMensajeLogin('La contrasena ingresada es incorrecta.', 'danger');
            header("Location: ../login.php");
            exit;
        }

        $_SESSION["usuario"] = $usuario["id_usuario"];
        $_SESSION["email"] = $usuario["email"];
        $_SESSION["permiso"] = $usuario["id_permiso"];

        header("Location: admin.php");
        exit;
    } catch (PDOException $e) {
        establecerMensajeLogin('Ocurrio un error al iniciar sesion.', 'danger');
        header("Location: ../login.php");
        exit;
    }
}

header("Location: ../login.php");
exit;
?>
