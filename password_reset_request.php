<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/enviar.php';
require_once __DIR__ . '/password_reset_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo no permitido.']);
    exit;
}

$email = trim((string)($_POST['email'] ?? ''));

if ($email === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ingresa tu correo electronico.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'El correo electronico no es valido.']);
    exit;
}

$pdo = getPdo();

try {
    ensurePasswordResetTable($pdo);
    purgeExpiredPasswordResets($pdo);

    $sql = $pdo->prepare('SELECT id_usuario, email, nombre, verificado FROM usuarios WHERE email = ? LIMIT 1');
    $sql->execute([$email]);
    $usuario = $sql->fetch(PDO::FETCH_ASSOC);

    // Respondemos exito generico para evitar enumeracion de correos.
    $genericResponse = json_encode([
        'ok' => true,
        'message' => 'Si el correo corresponde a una cuenta registrada, vas a recibir un email con instrucciones en los proximos minutos.'
    ]);

    if (!$usuario || (int)$usuario['verificado'] !== 1) {
        echo $genericResponse;
        exit;
    }

    $pdo->beginTransaction();
    deleteExistingPasswordResetTokens($pdo, (int)$usuario['id_usuario']);

    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');

    $insert = $pdo->prepare('INSERT INTO recuperaciones_contrasena (id_usuario, token, expiracion) VALUES (?, ?, ?)');
    $insert->execute([(int)$usuario['id_usuario'], $token, $expiresAt]);

    $pdo->commit();

    $resetLink = APP_URL . '/restablecer.php?token=' . urlencode($token);
    $nombre = (string)($usuario['nombre'] ?? '');

    try {
        enviarCorreoRecuperacion((string)$usuario['email'], $nombre, $resetLink);
    } catch (Throwable $exception) {
        $cleanup = $pdo->prepare('DELETE FROM recuperaciones_contrasena WHERE token = ?');
        $cleanup->execute([$token]);
        throw $exception;
    }

    echo $genericResponse;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No pudimos procesar la solicitud. Intenta mas tarde.']);
}
