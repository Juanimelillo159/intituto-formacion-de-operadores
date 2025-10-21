<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mercadopago_common.php';
require_once __DIR__ . '/mercadopago_service.php';

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

$responseCode = 200;
$response = [
    'success' => false,
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $responseCode = 405;
        throw new RuntimeException('Método no permitido.');
    }

    if (!isset($con) || !($con instanceof PDO)) {
        throw new RuntimeException('No se pudo conectar con la base de datos.');
    }

    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $usuarioId = mp_current_user_id();
    if ($usuarioId <= 0) {
        $responseCode = 401;
        throw new RuntimeException('Debés iniciar sesión para continuar.');
    }

    $tipo = strtolower(trim((string)($_POST['tipo_checkout'] ?? ($_POST['tipo'] ?? 'curso'))));
    if ($tipo === 'certificaciones') {
        $tipo = 'certificacion';
    } elseif ($tipo === 'capacitaciones') {
        $tipo = 'capacitacion';
    }
    if (!in_array($tipo, ['curso', 'capacitacion', 'certificacion'], true)) {
        $tipo = 'curso';
    }

    $cursoId = (int)($_POST['id_curso'] ?? 0);
    $certificacionId = (int)($_POST['id_certificacion'] ?? 0);
    $nombre = trim((string)($_POST['nombre_insc'] ?? ''));
    $apellido = trim((string)($_POST['apellido_insc'] ?? ''));
    $email = trim((string)($_POST['email_insc'] ?? ''));
    $telefono = trim((string)($_POST['tel_insc'] ?? ''));
    $dni = trim((string)($_POST['dni_insc'] ?? ''));
    $direccion = trim((string)($_POST['dir_insc'] ?? ''));
    $ciudad = trim((string)($_POST['ciu_insc'] ?? ''));
    $provincia = trim((string)($_POST['prov_insc'] ?? ''));
    $pais = trim((string)($_POST['pais_insc'] ?? 'Argentina'));
    $aceptaTyC = isset($_POST['acepta_tyc']);

    if ($cursoId <= 0) {
        throw new InvalidArgumentException('Seleccioná un curso válido.');
    }
    if ($nombre === '' || $apellido === '' || $email === '' || $telefono === '') {
        throw new InvalidArgumentException('Completá todos los datos obligatorios.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Ingresá un correo electrónico válido.');
    }
    if (!$aceptaTyC) {
        throw new InvalidArgumentException('Debés aceptar los Términos y Condiciones para continuar.');
    }

    $cursoStmt = $con->prepare('SELECT id_curso, nombre_curso FROM cursos WHERE id_curso = :id LIMIT 1');
    $cursoStmt->execute([':id' => $cursoId]);
    $curso = $cursoStmt->fetch();
    if (!$curso) {
        throw new RuntimeException('No encontramos el curso seleccionado.');
    }

    $tipoPrecio = $tipo === 'certificacion' ? 'certificacion' : 'capacitacion';
    $precio = mp_fetch_course_price($con, $cursoId, $tipoPrecio);
    if ($precio['amount'] <= 0) {
        $precio['amount'] = (float)($_POST['precio_checkout'] ?? 0);
        $precio['currency'] = strtoupper((string)($_POST['moneda_checkout'] ?? 'ARS'));
        $precio['source'] = 'manual';
    }

    if ($precio['amount'] <= 0) {
        throw new RuntimeException('Todavía no definimos un precio vigente para este curso.');
    }

    $con->beginTransaction();

    $capacitacionId = null;
    $pagoId = null;

    if ($tipo === 'certificacion') {
        if ($certificacionId <= 0) {
            throw new RuntimeException('Necesitamos la certificación aprobada para continuar.');
        }

        $certStmt = $con->prepare('SELECT id_certificacion, id_estado, creado_por FROM checkout_certificaciones WHERE id_certificacion = :id LIMIT 1');
        $certStmt->execute([':id' => $certificacionId]);
        $certificacion = $certStmt->fetch();
        if (!$certificacion) {
            throw new RuntimeException('No encontramos la certificación para generar el pago.');
        }
        if ((int)$certificacion['creado_por'] !== $usuarioId) {
            throw new RuntimeException('No tenés permisos para pagar esta certificación.');
        }
        if ((int)$certificacion['id_estado'] === 3) {
            throw new RuntimeException('La certificación ya tiene un pago registrado.');
        }
        if ((int)$certificacion['id_estado'] !== 2) {
            throw new RuntimeException('La certificación todavía no fue aprobada para el pago.');
        }

        $pagoStmt = $con->prepare('INSERT INTO checkout_pagos (id_certificacion, metodo, estado, monto, moneda) VALUES (:certificacion, :metodo, :estado, :monto, :moneda)');
        $pagoStmt->execute([
            ':certificacion' => $certificacionId,
            ':metodo' => 'mercado_pago',
            ':estado' => 'pendiente',
            ':monto' => $precio['amount'],
            ':moneda' => $precio['currency'],
        ]);
        $pagoId = (int)$con->lastInsertId();

        $updateCert = $con->prepare('UPDATE checkout_certificaciones SET id_estado = 3, precio_total = :precio, moneda = :moneda, nombre = :nombre, apellido = :apellido, email = :email, telefono = :telefono, acepta_tyc = 1 WHERE id_certificacion = :id');
        $updateCert->execute([
            ':precio' => $precio['amount'],
            ':moneda' => $precio['currency'],
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':email' => $email,
            ':telefono' => $telefono,
            ':id' => $certificacionId,
        ]);

        $hist = $con->prepare('INSERT INTO historico_estado_certificaciones (id_certificacion, id_estado) VALUES (:id, 3)');
        $hist->execute([':id' => $certificacionId]);

        $capacitacionId = null;
        $registroId = $certificacionId;
    } else {
        $insertCap = $con->prepare('INSERT INTO checkout_capacitaciones (creado_por, id_curso, nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais, acepta_tyc, precio_total, moneda) VALUES (:usuario, :curso, :nombre, :apellido, :email, :telefono, :dni, :direccion, :ciudad, :provincia, :pais, 1, :precio, :moneda)');
        $insertCap->execute([
            ':usuario' => $usuarioId,
            ':curso' => $cursoId,
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':email' => $email,
            ':telefono' => $telefono,
            ':dni' => $dni !== '' ? $dni : null,
            ':direccion' => $direccion !== '' ? $direccion : null,
            ':ciudad' => $ciudad !== '' ? $ciudad : null,
            ':provincia' => $provincia !== '' ? $provincia : null,
            ':pais' => $pais !== '' ? $pais : 'Argentina',
            ':precio' => $precio['amount'],
            ':moneda' => $precio['currency'],
        ]);

        $capacitacionId = (int)$con->lastInsertId();
        $registroId = $capacitacionId;

        $insertPago = $con->prepare('INSERT INTO checkout_pagos (id_capacitacion, metodo, estado, monto, moneda) VALUES (:capacitacion, :metodo, :estado, :monto, :moneda)');
        $insertPago->execute([
            ':capacitacion' => $capacitacionId,
            ':metodo' => 'mercado_pago',
            ':estado' => 'pendiente',
            ':monto' => $precio['amount'],
            ':moneda' => $precio['currency'],
        ]);
        $pagoId = (int)$con->lastInsertId();
    }

    if (!$pagoId) {
        throw new RuntimeException('No pudimos registrar el pago.');
    }

    mp_configure_sdk();
    $client = new PreferenceClient();

    $externalReference = sprintf('curso-%d-%s', $cursoId, bin2hex(random_bytes(4)));

    $preferenceRequest = [
        'items' => [[
            'id' => (string)$curso['id_curso'],
            'title' => $curso['nombre_curso'],
            'description' => 'Inscripción al curso ' . $curso['nombre_curso'],
            'quantity' => 1,
            'unit_price' => round($precio['amount'], 2),
            'currency_id' => $precio['currency'],
        ]],
        'external_reference' => $externalReference,
        'payer' => [
            'email' => $email,
            'first_name' => $nombre,
            'last_name' => $apellido,
        ],
        'back_urls' => [
            'success' => mp_url_success(),
            'failure' => mp_url_failure(),
            'pending' => mp_url_pending(),
        ],
        'auto_return' => 'approved',
        'notification_url' => mp_notification_url(),
        'metadata' => [
            'id_pago' => $pagoId,
            'id_inscripcion' => $registroId,
            'id_capacitacion' => $capacitacionId,
            'id_certificacion' => $tipo === 'certificacion' ? $certificacionId : null,
            'id_curso' => $cursoId,
            'tipo_checkout' => $tipo,
            'email' => $email,
        ],
    ];

    try {
        $preference = $client->create($preferenceRequest);
    } catch (MPApiException $exception) {
        $con->rollBack();
        $debugInfo = mp_api_exception_debug($exception);
        mp_log('mp_preference_error', [
            'curso' => $cursoId,
            'pago' => $pagoId,
            'debug' => $debugInfo,
        ], $exception);

        $errorMessage = 'No pudimos crear la preferencia de pago. Intentalo nuevamente.';
        if (mp_is_debug()) {
            $status = $debugInfo['status_code'] ?? 'desconocido';
            $detail = $debugInfo['message'] ?? 'sin descripción';
            $errorMessage = sprintf('Mercado Pago devolvió un error (%s): %s', (string) $status, $detail);
        }

        throw new RuntimeException($errorMessage, 0, $exception);
    }

    $preferenceId = (string)($preference->id ?? '');
    $initPoint = (string)($preference->init_point ?? '');
    if ($preferenceId === '' || $initPoint === '') {
        $con->rollBack();
        throw new RuntimeException('Mercado Pago no devolvió la información necesaria.');
    }

    mp_store_preference($con, $pagoId, $preferenceId, $initPoint, $externalReference, [
        'request' => $preferenceRequest,
        'preference' => [
            'id' => $preferenceId,
            'init_point' => $initPoint,
        ],
    ]);

    $con->commit();

    mp_log('mp_preference_created', [
        'pago' => $pagoId,
        'registro' => $registroId,
        'preference' => $preferenceId,
        'monto' => $precio['amount'],
        'moneda' => $precio['currency'],
    ]);

    $response['success'] = true;
    $response['init_point'] = $initPoint;
    $response['preference_id'] = $preferenceId;
    $response['orden'] = $registroId;
} catch (Throwable $exception) {
    if ($responseCode === 200) {
        $responseCode = $exception instanceof InvalidArgumentException ? 400 : 500;
    }
    $response['message'] = $exception->getMessage();
    if (mp_is_debug()) {
        $mpException = $exception instanceof MPApiException ? $exception : ($exception->getPrevious() instanceof MPApiException ? $exception->getPrevious() : null);
        if ($mpException) {
            $response['debug'] = mp_api_exception_debug($mpException);
        }
    }
    mp_log('mp_preference_failed', [
        'error' => $exception->getMessage(),
        'debug' => $response['debug'] ?? null,
    ], $exception);
}

http_response_code($responseCode);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
