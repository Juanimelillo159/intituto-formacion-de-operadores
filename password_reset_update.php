<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/password_reset_helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo no permitido.']);
    exit;
}

$token = trim((string)($_POST['token'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm'] ?? '');

if ($token === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'El token de recuperacion es obligatorio.']);
    exit;
}

if ($password === '' || $confirm === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Ingresa y confirma tu nueva contrasena.']);
    exit;
}

if ($password !== $confirm) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Las contrasenas no coinciden.']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'La nueva contrasena debe tener al menos 8 caracteres.']);
    exit;
}

$pdo = getPdo();

try {
    ensurePasswordResetTable($pdo);
    purgeExpiredPasswordResets($pdo);

    $pdo->beginTransaction();

    $select = $pdo->prepare('SELECT r.id_reset, r.id_usuario FROM recuperaciones_contrasena r WHERE r.token = ? AND r.utilizado = 0 AND r.expiracion > NOW() LIMIT 1');
    $select->execute([$token]);
    $reset = $select->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'El enlace ya no es valido. Solicita una nueva recuperacion.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $updateUser = $pdo->prepare('UPDATE usuarios SET clave = ? WHERE id_usuario = ?');
    $updateUser->execute([$hash, (int)$reset['id_usuario']]);

    $updateReset = $pdo->prepare('UPDATE recuperaciones_contrasena SET utilizado = 1, usado_en = NOW() WHERE id_reset = ?');
    $updateReset->execute([(int)$reset['id_reset']]);

    $pdo->commit();

    echo json_encode(['ok' => true, 'message' => 'Tu contrasena se actualizo correctamente.']);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'No pudimos actualizar la contrasena. Intenta nuevamente.']);
}
