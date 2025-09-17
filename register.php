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

$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Email y contrasena son obligatorios.']);
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

$pdo = getPdo();

try {
    $pdo->beginTransaction();

    $check = $pdo->prepare('SELECT id_usuario, verificado FROM usuarios WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    $existing = $check->fetch();

    $token = bin2hex(random_bytes(32));
    $expiresAt = (new DateTimeImmutable('+24 hours'))->format('Y-m-d H:i:s');

    if ($existing) {
        if ((int)$existing['verificado'] === 1) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'message' => 'Ya hay una cuenta registrada con ese email.']);
            exit;
        }

        $userId = (int)$existing['id_usuario'];
        $updateToken = $pdo->prepare('UPDATE usuarios SET token_verificacion = ?, token_expiracion = ?, id_permiso = 2 WHERE id_usuario = ?');
        $updateToken->execute([$token, $expiresAt, $userId]);

        $pdo->commit();

        $verificationLink = APP_URL . '/verificar.php?token=' . urlencode($token);

        try {
            enviarCorreoVerificacion($email, $verificationLink);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'No pudimos enviar el correo de verificacion.']);
            exit;
        }

        echo json_encode(['ok' => true, 'message' => 'Revisa tu correo para activar tu cuenta.']);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare('INSERT INTO usuarios (email, clave, id_estado, id_permiso, verificado) VALUES (?, ?, 1, 2, 0)');
    $insert->execute([$email, $passwordHash]);
    $userId = (int)$pdo->lastInsertId();

    $updateToken = $pdo->prepare('UPDATE usuarios SET token_verificacion = ?, token_expiracion = ?, id_permiso = 2 WHERE id_usuario = ?');
    $updateToken->execute([$token, $expiresAt, $userId]);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ocurrio un error al crear la cuenta.']);
    exit;
}

$verificationLink = APP_URL . '/verificar.php?token=' . urlencode($token);

try {
    enviarCorreoVerificacion($email, $verificationLink);
} catch (Throwable $exception) {
    try {
        $cleanup = $pdo->prepare('DELETE FROM usuarios WHERE id_usuario = ?');
        $cleanup->execute([$userId]);
    } catch (Throwable $cleanupException) {
        // No hacemos nada adicional; si falla el borrado, simplemente dejamos el registro pendiente.
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No pudimos enviar el correo de verificacion.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Revisa tu correo para activar tu cuenta.']);

