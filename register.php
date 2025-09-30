<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/enviar.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo no permitido.']);
    exit;
}

$email    = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? $_POST['clave'] ?? '');
$nombre   = trim((string)($_POST['nombre'] ?? ''));
$apellido = trim((string)($_POST['apellido'] ?? ''));
$telefono = trim((string)($_POST['telefono'] ?? ''));

// Validaciones básicas
if ($email === '' || $password === '' || $nombre === '' || $apellido === '' || $telefono === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Email, contrasena, nombre, apellido y telefono son obligatorios.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Email invalido.']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'La contrasena debe tener al menos 8 caracteres.']);
    exit;
}
if (!preg_match('/^[0-9+()\s-]{6,}$/', $telefono)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'El numero de telefono no es valido.']);
    exit;
}

$pdo = getPdo();

try {
    $pdo->beginTransaction();

    // ¿Existe el email?
    $check = $pdo->prepare('SELECT id_usuario, verificado FROM usuarios WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    $existing = $check->fetch();

    $token     = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

    // Construimos la base URL de forma segura si no tenés APP_URL
    if (defined('APP_URL') && APP_URL) {
        $baseUrl = rtrim((string)APP_URL, '/');
    } else {
        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $scheme . '://' . $host;
    }
    $verificationLink = $baseUrl . '/verificar.php?token=' . urlencode($token);

    if ($existing) {
        // Si ya está verificado, corto
        if ((int)$existing['verificado'] === 1) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'message' => 'Ya hay una cuenta registrada con ese email.']);
            exit;
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

        // Envío mail (chequeando retorno)
        [$sent, $err] = enviarCorreoVerificacion($email, $verificationLink);
        if (!$sent) {
            // NO borramos el usuario: queda pendiente
            error_log('[register] Falla SMTP (existing user): ' . ($err ?? 'desconocido'));
            echo json_encode([
                'ok' => false,
                'message' => 'Tu cuenta existe pero aún no pudimos enviar el correo de verificación. Probá más tarde o revisá SPAM.'
            ]);
            exit;
        }

        echo json_encode(['ok' => true, 'message' => 'Revisa tu correo para activar tu cuenta.']);
        exit;
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
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ocurrio un error al crear la cuenta.']);
    exit;
}

// Envío mail de verificación (para usuario nuevo)
[$sent, $err] = enviarCorreoVerificacion($email, $verificationLink);
if (!$sent) {
    // NO borramos al usuario: que quede pendiente y pueda reintentar
    error_log('[register] Falla SMTP (new user): ' . ($err ?? 'desconocido'));
    echo json_encode([
        'ok' => false,
        'message' => 'Tu cuenta fue creada, pero no pudimos enviar el correo de verificacion. Probá más tarde o revisá SPAM.'
    ]);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Revisa tu correo para activar tu cuenta.']);
