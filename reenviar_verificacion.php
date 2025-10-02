<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/enviar.php';

function resendRequestWantsJson(): bool
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

function resendRespond(bool $ok, string $message, int $status = 200, string $type = 'info', ?string $title = null): void
{
    if (resendRequestWantsJson()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'ok' => $ok,
            'message' => $message,
            'title' => $title,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['login_mensaje'] = $message;
    $_SESSION['login_tipo'] = $type;
    if ($title !== null) {
        $_SESSION['login_title'] = $title;
    } else {
        unset($_SESSION['login_title']);
    }

    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resendRespond(false, 'Metodo no permitido.', 405, 'danger');
}

$email = trim((string)($_POST['email'] ?? ''));

if ($email === '') {
    resendRespond(false, 'Email es obligatorio.', 400, 'danger');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    resendRespond(false, 'Email invalido.', 400, 'danger');
}

$pdo = getPdo();

try {
    $stmt = $pdo->prepare('SELECT id_usuario, verificado FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && (int)$user['verificado'] === 0) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

        $update = $pdo->prepare('UPDATE usuarios SET token_verificacion = ?, token_expiracion = ? WHERE id_usuario = ?');
        $update->execute([$token, $expiresAt, (int)$user['id_usuario']]);

        if (defined('APP_URL') && APP_URL) {
            $baseUrl = rtrim((string)APP_URL, '/');
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $baseUrl = $scheme . '://' . $host;
        }

        $verificationLink = $baseUrl . '/verificar.php?token=' . urlencode($token);

        [$sent, $err] = enviarCorreoVerificacion($email, $verificationLink);
        if (!$sent) {
            error_log('[reenviar_verificacion] Falla SMTP: ' . ($err ?? 'desconocido'));
            resendRespond(false, 'No pudimos enviar el correo de verificacion. Intentalo nuevamente.', 500, 'danger');
        }

        resendRespond(true, 'Te enviamos un nuevo correo de verificacion. Revisalo o revisa la carpeta de SPAM.', 200, 'success', 'Revisa tu correo');
    }
} catch (Throwable $exception) {
    error_log('[reenviar_verificacion] Error: ' . $exception->getMessage());
    resendRespond(false, 'Ocurrio un error al procesar la solicitud.', 500, 'danger');
}

// Para evitar enumerar usuarios devolvemos mensaje neutro cuando no correspondia enviar correo
resendRespond(true, 'Si el email existe, te enviamos el enlace de verificacion.', 200, 'info', 'Aviso');


