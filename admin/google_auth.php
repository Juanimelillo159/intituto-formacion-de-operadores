<?php
/**
 * admin/google_auth.php
 * Valida el ID token de Google (GIS), crea o inicia sesión de usuario y responde JSON.
 * Requisitos:
 *  - config.php define GOOGLE_CLIENT_ID (o existe variable de entorno GOOGLE_CLIENT_ID)
 *  - sbd.php provee $con (PDO) y NO imprime nada
 *  - Tabla usuarios tiene columnas: email, clave, id_usuario, id_estado, id_permiso, verificado, nombre, apellido, google_sub (NULL UNIQUE)
 */

// ===== Logging básico (útil en hosting) =====
ini_set('display_errors', '0'); // no mostrar al usuario
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php-google-auth.log'); // genera /admin/php-google-auth.log
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json; charset=UTF-8');

// ===== Utilidad para responder error como JSON + log =====
function jerr($msg, $extra = []) {
    error_log('[GOOGLE_AUTH_ERROR] ' . $msg . ' ' . json_encode($extra));
    echo json_encode(['success' => false, 'message' => $msg] + $extra);
    exit;
}

// ===== Cargar config y conexión =====
$root = dirname(__DIR__);
require_once $root . '/config.php'; // asegura GOOGLE_CLIENT_ID
require_once $root . '/sbd.php';    // debe inicializar $con (PDO) sin hacer echo

// ===== Leer input (ID token) =====
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$idToken = $data['credential'] ?? '';
if (!$idToken) { jerr('Token faltante'); }

// ===== Resolver Client ID (backend) =====
$clientId = getenv('GOOGLE_CLIENT_ID') ?: (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '');
if (!$clientId || $clientId === 'TU_CLIENT_ID_DE_GOOGLE') {
    jerr('Client ID no configurado');
}

// ===== Verificación del ID token =====

// 1) Con librería oficial (si está instalada por Composer: google/apiclient)
function verifyWithLibrary($idToken, $clientId) {
    if (class_exists('\Google_Client')) {
        try {
            $client = new \Google_Client(['client_id' => $clientId]);
            $payload = $client->verifyIdToken($idToken);
            if ($payload && ($payload['aud'] ?? null) === $clientId) {
                return $payload;
            }
        } catch (\Throwable $e) {
            error_log('[GOOGLE_AUTH_LIB] ' . $e->getMessage());
        }
    }
    return null;
}

// 2) Via tokeninfo (requiere salida a internet). Primero cURL; si falla, file_get_contents.
function verifyWithTokenInfo($idToken, $clientId) {
    $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

    // cURL
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
        ]);
        $out  = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($http === 200 && $out) {
            $payload = json_decode($out, true);
            if (is_array($payload)) {
                if (($payload['aud'] ?? null) !== $clientId) return null;
                if (!in_array($payload['iss'] ?? '', ['https://accounts.google.com','accounts.google.com'], true)) return null;
                if (($payload['exp'] ?? 0) < time()) return null;
                return $payload;
            }
        } else {
            error_log('[GOOGLE_AUTH_CURL] HTTP=' . $http . ' err=' . $err);
        }
    }

    // file_get_contents
    if (ini_get('allow_url_fopen')) {
        $out = @file_get_contents($url);
        if ($out !== false) {
            $payload = json_decode($out, true);
            if (is_array($payload)) {
                if (($payload['aud'] ?? null) !== $clientId) return null;
                if (!in_array($payload['iss'] ?? '', ['https://accounts.google.com','accounts.google.com'], true)) return null;
                if (($payload['exp'] ?? 0) < time()) return null;
                return $payload;
            }
        } else {
            error_log('[GOOGLE_AUTH_FOPEN] No se pudo abrir tokeninfo');
        }
    } else {
        error_log('[GOOGLE_AUTH_FOPEN] allow_url_fopen deshabilitado');
    }

    return null;
}

$payload = verifyWithLibrary($idToken, $clientId) ?: verifyWithTokenInfo($idToken, $clientId);
if (!$payload) { jerr('Fallo verificación token (payload vacío)'); }

// ===== Validaciones mínimas del token =====
$aud = $payload['aud'] ?? null;
$iss = $payload['iss'] ?? null;
$exp = (int)($payload['exp'] ?? 0);
if ($aud !== $clientId) { jerr('aud no coincide', ['aud' => $aud]); }
if (!in_array($iss, ['https://accounts.google.com', 'accounts.google.com'], true)) { jerr('iss inválido', ['iss' => $iss]); }
if ($exp < time()) { jerr('token expirado', ['exp' => $exp, 'now' => time()]); }

$email         = strtolower(trim($payload['email'] ?? ''));
$emailVerified = filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
$googleSub     = $payload['sub'] ?? null;
$given         = $payload['given_name'] ?? null;
$family        = $payload['family_name'] ?? null;
$name          = $payload['name'] ?? null;

if (!$email || !$emailVerified) { jerr('email no verificado o vacío'); }
if (!$googleSub) { jerr('sub faltante'); }

// ===== Login / Registro =====
try {
    $con->beginTransaction();

    $stmt = $con->prepare('SELECT id_usuario, email, id_permiso FROM usuarios WHERE email = :email LIMIT 1');
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
        // crear usuario
        $permiso = 2; // default
        $rand    = bin2hex(random_bytes(24));
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
    }

    $_SESSION['mis_cursos_alert'] = [
        'icon' => 'success',
        'title' => 'Sesión iniciada con Google',
        'message' => 'Acceso completado correctamente.'
    ];

    $con->commit();
    echo json_encode(['success' => true, 'redirect' => '../intituto-formacion-de-operadores/mis_cursos.php']);
} catch (Throwable $e) {
    if ($con->inTransaction()) $con->rollBack();
    jerr('Error de servidor al guardar sesión', ['ex' => $e->getMessage()]);
}
