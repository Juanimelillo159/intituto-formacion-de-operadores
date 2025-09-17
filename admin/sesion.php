<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../sbd.php';

function establecerMensajeLogin($mensaje, $tipo = 'danger')
{
    $_SESSION['login_mensaje'] = $mensaje;
    $_SESSION['login_tipo'] = $tipo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iniciar_sesion'])) {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['clave'] ?? '';

    if ($email === '' || $password === '') {
        establecerMensajeLogin('Debe ingresar correo y contrasena.', 'danger');
        header('Location: ../login.php');
        exit;
    }

    try {
        $sql_usuario = $con->prepare('SELECT id_usuario, email, clave, id_permiso, id_estado, verificado FROM usuarios WHERE email = :email LIMIT 1');
        $sql_usuario->bindParam(':email', $email);
        $sql_usuario->execute();
        $usuario = $sql_usuario->fetch(PDO::FETCH_ASSOC);

        if (!$usuario || !password_verify($password, $usuario['clave'])) {
            establecerMensajeLogin('Credenciales invalidas.', 'danger');
            header('Location: ../login.php');
            exit;
        }

        if ((int)$usuario['verificado'] !== 1) {
            $emailSafe = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            $mensaje = 'Tu cuenta aun no esta verificada. Revisa tu correo o solicita un nuevo enlace <a href="#" class="reenviar-verificacion" data-email="' . $emailSafe . '">haciendo clic aqui</a>.';
            establecerMensajeLogin($mensaje, 'warning');
            header('Location: ../login.php');
            exit;
        }

        $sql_estado = $con->prepare('UPDATE usuarios SET id_estado = :estado WHERE id_usuario = :id');
        $estado_logueado = 2;
        $sql_estado->bindParam(':estado', $estado_logueado, PDO::PARAM_INT);
        $sql_estado->bindParam(':id', $usuario['id_usuario'], PDO::PARAM_INT);
        $sql_estado->execute();

        $_SESSION['usuario'] = $usuario['id_usuario'];
        $_SESSION['email'] = $usuario['email'];
        $_SESSION['permiso'] = $usuario['id_permiso'];

        header('Location: ../index.php');
        exit;
    } catch (PDOException $e) {
        establecerMensajeLogin('Ocurrio un error al iniciar sesion.', 'danger');
        header('Location: ../login.php');
        exit;
    }
}

header('Location: ../login.php');
exit;