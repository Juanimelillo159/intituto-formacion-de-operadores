<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Metodo no permitido.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['credential'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Falta el token de Google.'
    ]);
    exit;
}

$googleClientId = getenv('GOOGLE_CLIENT_ID') ?: 'TU_CLIENT_ID_DE_GOOGLE';
if (!$googleClientId || $googleClientId === 'TU_CLIENT_ID_DE_GOOGLE') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Configura el Client ID de Google en el servidor.'
    ]);
    exit;
}

$credential = $input['credential'];
$verifyUrl = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential);
$tokenResponse = false;
$httpStatus = 0;

if (function_exists('curl_init')) {
    $ch = curl_init($verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $tokenResponse = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5
        ]
    ]);
    $tokenResponse = @file_get_contents($verifyUrl, false, $context);
    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (stripos($headerLine, 'HTTP/') === 0) {
                $parts = explode(' ', $headerLine);
                if (isset($parts[1])) {
                    $httpStatus = (int) $parts[1];
                }
                break;
            }
        }
    }
}

if ($tokenResponse === false || $httpStatus !== 200) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo validar el token de Google.'
    ]);
    exit;
}

$tokenData = json_decode($tokenResponse, true);
if (!is_array($tokenData) || empty($tokenData['aud']) || $tokenData['aud'] !== $googleClientId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'El token recibido no corresponde a este proyecto.'
    ]);
    exit;
}

$issuer = isset($tokenData['iss']) ? $tokenData['iss'] : '';
if ($issuer !== 'https://accounts.google.com' && $issuer !== 'accounts.google.com') {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'El emisor del token de Google no es valido.'
    ]);
    exit;
}

$email = isset($tokenData['email']) ? $tokenData['email'] : null;
$emailVerified = isset($tokenData['email_verified']) ? $tokenData['email_verified'] : null;
if (!$email || !in_array($emailVerified, ['true', true], true)) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Google no confirmo el correo electronico.'
    ]);
    exit;
}

require_once '../sbd.php';

try {
    $sqlUsuario = $con->prepare('SELECT id_usuario, email, id_permiso FROM usuarios WHERE email = :email LIMIT 1');
    $sqlUsuario->bindParam(':email', $email);
    $sqlUsuario->execute();
    $usuario = $sqlUsuario->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        $randomPassword = bin2hex(random_bytes(16));
        $hash = password_hash($randomPassword, PASSWORD_DEFAULT);
        $estado = 1;
        $permiso = 2;
        $sqlInsertar = $con->prepare('INSERT INTO usuarios (email, clave, id_estado, id_permiso) VALUES (:email, :clave, :estado, :permiso)');
        $sqlInsertar->bindParam(':email', $email);
        $sqlInsertar->bindParam(':clave', $hash);
        $sqlInsertar->bindParam(':estado', $estado, PDO::PARAM_INT);
        $sqlInsertar->bindParam(':permiso', $permiso, PDO::PARAM_INT);
        $sqlInsertar->execute();
        $usuarioId = (int) $con->lastInsertId();
        $permisoAsignado = $permiso;
    } else {
        $usuarioId = (int) $usuario['id_usuario'];
        $permisoAsignado = (int) $usuario['id_permiso'];
    }

    $estadoLogueado = 2;
    $sqlEstado = $con->prepare('UPDATE usuarios SET id_estado = :estado WHERE id_usuario = :id');
    $sqlEstado->bindParam(':estado', $estadoLogueado, PDO::PARAM_INT);
    $sqlEstado->bindParam(':id', $usuarioId, PDO::PARAM_INT);
    $sqlEstado->execute();

    $_SESSION['usuario'] = $usuarioId;
    $_SESSION['email'] = $email;
    $_SESSION['permiso'] = $permisoAsignado;

    echo json_encode([
        'success' => true,
        'redirect' => '../index.php'
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'No se pudo completar el acceso con Google.'
    ]);
    exit;
}
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../sbd.php'; // Debe exponer $con (PDO)

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$idToken = $data['credential'] ?? '';

if (!$idToken) {
    echo json_encode(['success' => false, 'message' => 'Token faltante.']); exit;
}

$clientId = getenv('GOOGLE_CLIENT_ID') ?: (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
if (!$clientId || $clientId === 'TU_CLIENT_ID_DE_GOOGLE') {
    echo json_encode(['success' => false, 'message' => 'Client ID no configurado.']); exit;
}

// --- Verificación del ID token ---
function verifyWithLibrary($idToken, $clientId) {
    if (class_exists('\Google_Client')) {
        $client = new \Google_Client(['client_id' => $clientId]);
        $payload = $client->verifyIdToken($idToken);
        if ($payload && ($payload['aud'] ?? null) === $clientId) {
            return $payload;
        }
    }
    return null;
}

function verifyWithTokenInfo($idToken, $clientId) {
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $out  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http !== 200) return null;
    $payload = json_decode($out, true);
    if (!is_array($payload)) return null;

    if (($payload['aud'] ?? null) !== $clientId) return null;
    if (!in_array($payload['iss'] ?? '', ['https://accounts.google.com','accounts.google.com'], true)) return null;
    if (($payload['exp'] ?? 0) < time()) return null;

    return $payload;
}

$payload = verifyWithLibrary($idToken, $clientId) ?: verifyWithTokenInfo($idToken, $clientId);
if (!$payload) {
    echo json_encode(['success' => false, 'message' => 'No pudimos validar el token de Google.']); exit;
}

$email         = strtolower(trim($payload['email'] ?? ''));
$emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
$googleSub     = $payload['sub'] ?? null;
$given         = $payload['given_name'] ?? null;
$family        = $payload['family_name'] ?? null;
$name          = $payload['name'] ?? null;

if (!$email || !$emailVerified) {
    echo json_encode(['success' => false, 'message' => 'Tu cuenta de Google no tiene un correo verificado.']); exit;
}

try {
    $con->beginTransaction();

    $stmt = $con->prepare('SELECT id_usuario, email, id_permiso, verificado FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $id = (int)$user['id_usuario'];

        $upd = $con->prepare('UPDATE usuarios
            SET google_sub = COALESCE(google_sub, :sub),
                verificado = 1,
                nombre = COALESCE(NULLIF(nombre, \'\'), :nombre),
                apellido = COALESCE(NULLIF(apellido, \'\'), :apellido),
                id_estado = :estado
            WHERE id_usuario = :id');
        $upd->bindValue(':sub', $googleSub);
        $upd->bindValue(':nombre', $given ?: ($name ?: null));
        $upd->bindValue(':apellido', $family ?: null);
        $upd->bindValue(':estado', 2, PDO::PARAM_INT); // 2 = logueado
        $upd->bindValue(':id', $id, PDO::PARAM_INT);
        $upd->execute();

        $_SESSION['usuario'] = $id;
        $_SESSION['email']   = $email;
        $_SESSION['permiso'] = (int)$user['id_permiso'];
    } else {
        // Crear usuario nuevo (registro con Google)
        $permiso = 2; // default
        $rand    = bin2hex(random_bytes(24)); // password inutilizable
        $hash    = password_hash($rand, PASSWORD_DEFAULT);

        $ins = $con->prepare('INSERT INTO usuarios (email, clave, id_estado, id_permiso, verificado, google_sub, nombre, apellido)
                              VALUES (:email, :clave, :estado, :permiso, 1, :sub, :nombre, :apellido)');
        $ins->bindValue(':email', $email);
        $ins->bindValue(':clave', $hash);
        $ins->bindValue(':estado', 2, PDO::PARAM_INT);
        $ins->bindValue(':permiso', $permiso, PDO::PARAM_INT);
        $ins->bindValue(':sub', $googleSub);
        $ins->bindValue(':nombre', $given ?: ($name ?: null));
        $ins->bindValue(':apellido', $family ?: null);
        $ins->execute();

        $id = (int)$con->lastInsertId();
        $_SESSION['usuario'] = $id;
        $_SESSION['email']   = $email;
        $_SESSION['permiso'] = $permiso;

        // Si querés forzar aceptar TyC o completar datos, podés setear un flag:
        // $_SESSION['first_google_login'] = 1;
    }

    $_SESSION['mis_cursos_alert'] = [
        'icon' => 'success',
        'title' => 'Sesión iniciada con Google',
        'message' => 'Acceso completado correctamente.'
    ];

    $con->commit();

    echo json_encode(['success' => true, 'redirect' => '../mis_cursos.php']);
} catch (Throwable $e) {
    if ($con->inTransaction()) $con->rollBack();
    error_log('Google Auth error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'No pudimos iniciar sesión con Google.']);
}
