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

if ($email === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Email es obligatorio.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Email invalido.']);
    exit;
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

        $verificationLink = APP_URL . '/verificar.php?token=' . urlencode($token);

        try {
            enviarCorreoVerificacion($email, $verificationLink);
        } catch (Throwable $exception) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'message' => 'No pudimos enviar el correo de verificacion.']);
            exit;
        }
    }
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Ocurrio un error al procesar la solicitud.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Si el email existe, te enviamos el enlace de verificacion.']);