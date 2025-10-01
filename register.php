<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/enviar.php';

/**
 * Detects if the current request expects a JSON payload.
 */
function requestWantsJson(): bool
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

/**
 * Sends a response as JSON when requested, otherwise sets a flash message and redirects.
 */
function respondAndExit(bool $ok, string $message, string $type = 'info', int $status = 200, ?string $redirect = null): void
{
    if (requestWantsJson()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['registro_mensaje'] = $message;
    $_SESSION['registro_tipo'] = $type;

    $target = $redirect ?? 'registro.php';
    header('Location: ' . $target);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondAndExit(false, 'Metodo no permitido.', 'danger', 405);
}

$email    = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? $_POST['clave'] ?? '');
$nombre   = trim((string)($_POST['nombre'] ?? ''));
$apellido = trim((string)($_POST['apellido'] ?? ''));
$telefono = trim((string)($_POST['telefono'] ?? ''));

// Validaciones basicas
if ($email === '' || $password === '' || $nombre === '' || $apellido === '' || $telefono === '') {
    respondAndExit(false, 'Email, contrasena, nombre, apellido y telefono son obligatorios.', 'danger', 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondAndExit(false, 'Email invalido.', 'danger', 400);
}
if (strlen($password) < 8) {
    respondAndExit(false, 'La contrasena debe tener al menos 8 caracteres.', 'danger', 400);
}
if (!preg_match('/^[0-9+()\\s-]{6,}$/', $telefono)) {
    respondAndExit(false, 'El numero de telefono no es valido.', 'danger', 400);
}

$pdo = getPdo();

try {
    $pdo->beginTransaction();

    // Existe el email?
    $check = $pdo->prepare('SELECT id_usuario, verificado FROM usuarios WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    $existing = $check->fetch();

    $token     = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

    // Construimos la base URL de forma segura si no tenes APP_URL
    if (defined('APP_URL') && APP_URL) {
        $baseUrl = rtrim((string)APP_URL, '/');
    } else {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }
    $verificationLink = $baseUrl . '/verificar.php?token=' . urlencode($token);

    if ($existing) {
        // Si ya esta verificado, corto
        if ((int)$existing['verificado'] === 1) {
            $pdo->rollBack();
            respondAndExit(false, 'Ya hay una cuenta registrada con ese email.', 'warning', 409);
        }

        // Usuario no verificado: renuevo token y actualizo datos
        $userId = (int)$existing['id_usuario'];
        $upd = $pdo->prepare('
            UPDATE usuarios
               SET token_verificacion = ?,
                   token_expiracion   = ?,
                   nombre             = ?,
                   apellido           = ?,
                   telefono           = ?
             WHERE id_usuario = ?
        ');
        $upd->execute([$token, $expiresAt, $nombre, $apellido, $telefono, $userId]);

        $pdo->commit();

        // Envio mail (chequeando retorno)
        [$sent, $err] = enviarCorreoVerificacion($email, $verificationLink);
        if (!$sent) {
            // NO borramos el usuario: queda pendiente
            error_log('[register] Falla SMTP (existing user): ' . ($err ?? 'desconocido'));
            respondAndExit(false, 'Tu cuenta existe pero aun no pudimos enviar el correo de verificacion. Proba mas tarde o revisa SPAM.', 'warning', 500);
        }

        respondAndExit(true, 'Revisa tu correo para activar tu cuenta.', 'success');
    }

    // Usuario nuevo
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // id_estado=1 (activo/pendiente) | id_permiso=2 (si ese es tu "usuario base")
    $ins = $pdo->prepare('
        INSERT INTO usuarios (email, clave, id_estado, id_permiso, verificado, nombre, apellido, telefono, token_verificacion, token_expiracion)
        VALUES (?, ?, 1, 2, 0, ?, ?, ?, ?, ?)
    ');
    $ins->execute([$email, $passwordHash, $nombre, $apellido, $telefono, $token, $expiresAt]);
    $userId = (int)$pdo->lastInsertId();

    $pdo->commit();

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[register] DB error: ' . $e->getMessage());
    respondAndExit(false, 'Ocurrio un error al crear la cuenta.', 'danger', 500);
}

// Envio mail de verificacion (para usuario nuevo)
[$sent, $err] = enviarCorreoVerificacion($email, $verificationLink);
if (!$sent) {
    // NO borramos al usuario: que quede pendiente y pueda reintentar
    error_log('[register] Falla SMTP (new user): ' . ($err ?? 'desconocido'));
    respondAndExit(false, 'Tu cuenta fue creada, pero no pudimos enviar el correo de verificacion. Proba mas tarde o revisa SPAM.', 'warning', 500);
}

respondAndExit(true, 'Revisa tu correo para activar tu cuenta.', 'success');
