<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../sbd.php';

function loginRequestWantsJson(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if ($accept !== '' && stripos($accept, 'application/json') !== false) {
        return true;
    }

    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if ($requestedWith !== '' && strcasecmp($requestedWith, 'xmlhttprequest') === 0) {
        return true;
    }

    $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
    return $contentType !== '' && stripos($contentType, 'application/json') !== false;
}

function loginRespond(bool $ok, string $message, string $type = 'info', int $status = 200, ?string $redirect = null): void
{
    if (loginRequestWantsJson()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        $payload = ['ok' => $ok, 'message' => $message];
        if ($redirect !== null) {
            $payload['redirect'] = $redirect;
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['login_mensaje'] = $message;
    $_SESSION['login_tipo'] = $type;

    $target = $redirect ?? '../login.php';
    header('Location: ' . $target);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['iniciar_sesion'])) {
    loginRespond(false, 'Metodo no permitido.', 'danger', 405);
}

$email = isset($_POST['email']) ? trim((string)$_POST['email']) : '';
$password = (string)($_POST['clave'] ?? '');

if ($email === '' || $password === '') {
    loginRespond(false, 'Debe ingresar correo y contrasena.', 'danger', 400);
}

try {
    $sql_usuario = $con->prepare('SELECT id_usuario, email, clave, id_permiso, id_estado, verificado FROM usuarios WHERE email = :email LIMIT 1');
    $sql_usuario->bindParam(':email', $email);
    $sql_usuario->execute();
    $usuario = $sql_usuario->fetch(PDO::FETCH_ASSOC);

    if (!$usuario || !password_verify($password, $usuario['clave'])) {
        loginRespond(false, 'Credenciales invalidas.', 'danger', 401);
    }

    if ((int)$usuario['verificado'] !== 1) {
        $emailSafe = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $mensaje = 'Tu cuenta aun no esta verificada. Revisa tu correo o solicita un nuevo enlace <a href="#" class="reenviar-verificacion" data-email="' . $emailSafe . '">haciendo clic aqui</a>.';
        loginRespond(false, $mensaje, 'warning', 403);
    }

    $sql_estado = $con->prepare('UPDATE usuarios SET id_estado = :estado WHERE id_usuario = :id');
    $estado_logueado = 2;
    $sql_estado->bindParam(':estado', $estado_logueado, PDO::PARAM_INT);
    $sql_estado->bindParam(':id', $usuario['id_usuario'], PDO::PARAM_INT);
    $sql_estado->execute();

    $_SESSION['usuario'] = $usuario['id_usuario'];
    $_SESSION['email'] = $usuario['email'];
    $_SESSION['permiso'] = $usuario['id_permiso'];

    $_SESSION['mis_cursos_alert'] = [
        'icon' => 'success',
        'title' => 'Sesion iniciada',
        'message' => 'Sesion iniciada con exito. Bienvenido de nuevo.'
    ];

    $redirectUrl = '../mis_cursos.php';
    if (loginRequestWantsJson()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => true,
            'message' => 'Sesion iniciada con exito.',
            'redirect' => $redirectUrl,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . $redirectUrl);
    exit;
} catch (Throwable $e) {
    loginRespond(false, 'Ocurrio un error al iniciar sesion.', 'danger', 500);
}
