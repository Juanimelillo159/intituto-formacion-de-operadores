<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mp_config.php';
require_once __DIR__ . '/mercadopago_common.php';

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

$responseCode = 200;
$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['usuario'])) {
        $responseCode = 401;
        throw new RuntimeException('Debés iniciar sesión para continuar.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }
    if (!isset($con) || !($con instanceof PDO)) {
        throw new RuntimeException('Conexión a la base de datos no disponible.');
    }

    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $metodoPago = (string) ($_POST['metodo_pago'] ?? '');
    if ($metodoPago !== 'mercado_pago') {
        throw new InvalidArgumentException('El método de pago seleccionado es inválido para esta operación.');
    }

    $usuarioId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);
    $tipoCheckoutRaw = strtolower(trim((string)($_POST['tipo_checkout'] ?? ($_POST['tipo'] ?? 'curso'))));
    if ($tipoCheckoutRaw === 'capacitaciones') {
        $tipoCheckoutRaw = 'capacitacion';
    } elseif ($tipoCheckoutRaw === 'certificaciones') {
        $tipoCheckoutRaw = 'certificacion';
    }
    if (!in_array($tipoCheckoutRaw, ['curso', 'capacitacion', 'certificacion'], true)) {
        $tipoCheckoutRaw = 'curso';
    }
    $esCertificacion = ($tipoCheckoutRaw === 'certificacion');
    if ($esCertificacion && $usuarioId <= 0) {
        throw new RuntimeException('Sesión no válida. Volvé a iniciar sesión.');
    }

    $cursoId = (int) ($_POST['id_curso'] ?? 0);
    $nombre = trim((string) ($_POST['nombre_insc'] ?? ''));
    $apellido = trim((string) ($_POST['apellido_insc'] ?? ''));
    $email = trim((string) ($_POST['email_insc'] ?? ''));
    $telefono = trim((string) ($_POST['tel_insc'] ?? ''));
    $dni = trim((string) ($_POST['dni_insc'] ?? ''));
    $direccion = trim((string) ($_POST['dir_insc'] ?? ''));
    $ciudad = trim((string) ($_POST['ciu_insc'] ?? ''));
    $provincia = trim((string) ($_POST['prov_insc'] ?? ''));
    $pais = trim((string) ($_POST['pais_insc'] ?? 'Argentina'));
    $aceptaTyC = isset($_POST['acepta_tyc']);

    if ($esCertificacion && $usuarioId > 0) {
        try {
            $usrStmt = $con->prepare('SELECT nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais FROM usuarios WHERE id_usuario = :id LIMIT 1');
            $usrStmt->execute([':id' => $usuarioId]);
            $usrRow = $usrStmt->fetch();
            if ($usrRow) {
                if ($nombre === '') { $nombre = (string)$usrRow['nombre']; }
                if ($apellido === '') { $apellido = (string)$usrRow['apellido']; }
                if ($email === '') { $email = (string)$usrRow['email']; }
                if ($telefono === '') { $telefono = (string)$usrRow['telefono']; }
                if ($dni === '') { $dni = (string)$usrRow['dni']; }
                if ($direccion === '') { $direccion = (string)$usrRow['direccion']; }
                if ($ciudad === '') { $ciudad = (string)$usrRow['ciudad']; }
                if ($provincia === '') { $provincia = (string)$usrRow['provincia']; }
                if ($pais === '') { $pais = (string)$usrRow['pais']; }
            }
        } catch (Throwable $ignored) {
        }
    }

    if ($cursoId <= 0) {
        throw new InvalidArgumentException('Curso inválido.');
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
        throw new RuntimeException('No pudimos encontrar el curso seleccionado.');
    }

    $precioStmt = $con->prepare(
        "SELECT precio, moneda
           FROM curso_precio_hist
          WHERE id_curso = :curso
            AND vigente_desde <= NOW()
            AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
       ORDER BY vigente_desde DESC
          LIMIT 1"
    );
    $precioStmt->execute([':curso' => $cursoId]);
    $precioRow = $precioStmt->fetch();
    $precioFinal = $precioRow ? (float) $precioRow['precio'] : (float) ($_POST['precio_checkout'] ?? 0);
    $moneda = $precioRow && !empty($precioRow['moneda']) ? (string) $precioRow['moneda'] : 'ARS';

    if ($precioFinal <= 0) {
        throw new RuntimeException('Aún no hay un precio vigente para este curso.');
    }

    $certificadoRow = null;
    if ($esCertificacion) {
        $certificadoId = (int)($_POST['id_certificado'] ?? 0);
        if ($certificadoId <= 0) {
            throw new InvalidArgumentException('Necesitamos validar tu formulario antes de iniciar el pago.');
        }
        $certificadoStmt = $con->prepare('SELECT * FROM certificados WHERE id_certificado = :id AND id_usuario = :usuario LIMIT 1');
        $certificadoStmt->execute([':id' => $certificadoId, ':usuario' => $usuarioId]);
        $certificadoRow = $certificadoStmt->fetch();
        if (!$certificadoRow) {
            throw new RuntimeException('No encontramos tu solicitud de certificación.');
        }
        $estadoCert = strtolower((string)$certificadoRow['estado']);
        if (!in_array($estadoCert, ['aprobado', 'pago_pendiente_confirmacion', 'pagado'], true)) {
            throw new InvalidArgumentException('Tu formulario aún no fue aprobado para iniciar el pago.');
        }
        if ($estadoCert === 'pagado' || strtolower((string)($certificadoRow['pago_estado'] ?? '')) === 'pagado') {
            throw new InvalidArgumentException('Esta certificación ya registra un pago.');
        }
    }

    $con->beginTransaction();

    $inscripcionStmt = $con->prepare(
        "INSERT INTO checkout_inscripciones (
            id_curso, nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais, acepta_tyc, precio_total, moneda
        ) VALUES (
            :curso, :nombre, :apellido, :email, :telefono, :dni, :direccion, :ciudad, :provincia, :pais, 1, :precio, :moneda
        )"
    );
    $inscripcionStmt->execute([
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
        ':precio' => $precioFinal,
        ':moneda' => strtoupper($moneda),
    ]);

    $inscripcionId = (int) $con->lastInsertId();

    $pagoStmt = $con->prepare(
        "INSERT INTO checkout_pagos (
            id_inscripcion, metodo, estado, monto, moneda
        ) VALUES (
            :inscripcion, 'mercado_pago', 'pendiente', :monto, :moneda
        )"
    );
    $pagoStmt->execute([
        ':inscripcion' => $inscripcionId,
        ':monto' => $precioFinal,
        ':moneda' => strtoupper($moneda),
    ]);

    $pagoId = (int) $con->lastInsertId();

    checkout_configure_mp();
    $preferenceClient = new PreferenceClient();

    $baseUrl = checkout_get_base_url();
    $externalReference = 'insc-' . $inscripcionId;

    $preferenceRequest = [
        'items' => [[
            'title' => $curso['nombre_curso'],
            'description' => 'Inscripción al curso ' . $curso['nombre_curso'],
            'quantity' => 1,
            'unit_price' => round($precioFinal, 2),
            'currency_id' => strtoupper($moneda),
        ]],
        'back_urls' => [
            'success' => $baseUrl . '/checkout/gracias.php',
            'pending' => $baseUrl . '/checkout/gracias.php',
            'failure' => $baseUrl . '/checkout/gracias.php',
        ],
        'auto_return' => 'approved',
        'external_reference' => $externalReference,
        'metadata' => [
            'id_pago' => $pagoId,
            'id_inscripcion' => $inscripcionId,
            'id_curso' => $cursoId,
            'email' => $email,
            'tipo_checkout' => $tipoCheckoutRaw,
        ],
    ];

    if ($esCertificacion && $certificadoRow) {
        $preferenceRequest['metadata']['id_certificado'] = (int)$certificadoRow['id_certificado'];
    }

    if ($email !== '') {
        $preferenceRequest['payer'] = ['email' => $email];
    }

    $notificationUrl = checkout_env('MP_NOTIFICATION_URL');
    if (!$notificationUrl) {
        $notificationUrl = $baseUrl . '/checkout/mercadopago_webhook.php';
    }
    if ($notificationUrl) {
        $preferenceRequest['notification_url'] = $notificationUrl;
    }

    try {
        $preference = $preferenceClient->create($preferenceRequest);
    } catch (MPApiException $mpException) {
        $con->rollBack();
        $apiMessage = $mpException->getMessage();
        $apiResponse = method_exists($mpException, 'getApiResponse') ? $mpException->getApiResponse() : null;
        if ($apiResponse) {
            $details = [];
            if (method_exists($apiResponse, 'getStatusCode')) {
                $statusCode = $apiResponse->getStatusCode();
                if ($statusCode) {
                    $details[] = 'HTTP ' . $statusCode;
                }
            }
            if (method_exists($apiResponse, 'getContent')) {
                $body = $apiResponse->getContent();
                if (is_string($body) && $body !== '') {
                    $details[] = $body;
                } elseif (is_array($body) && !empty($body)) {
                    $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
                    if ($encoded !== false) {
                        $details[] = $encoded;
                    }
                }
            }
            if ($details) {
                $apiMessage = 'Mercado Pago API error: ' . implode(' - ', $details);
            }
        }
        checkout_log_event('checkout_mp_preference_error', ['curso' => $cursoId, 'inscripcion' => $inscripcionId], $mpException);
        throw new RuntimeException($apiMessage ?: 'No pudimos iniciar el pago con Mercado Pago.');
    }

    $preferenceId = (string) ($preference->id ?? '');
    $initPoint = (string) ($preference->init_point ?? '');
    $sandboxInitPoint = (string) ($preference->sandbox_init_point ?? '');

    if ($preferenceId === '' || $initPoint === '') {
        $con->rollBack();
        throw new RuntimeException('No recibimos la información necesaria de Mercado Pago.');
    }

    $payloadData = [
        'preference' => [
            'id' => $preferenceId,
            'init_point' => $initPoint,
            'sandbox_init_point' => $sandboxInitPoint,
            'external_reference' => $externalReference,
            'created_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
        ],
        'request' => $preferenceRequest,
    ];

    $mpStmt = $con->prepare(
        "INSERT INTO checkout_mercadopago (
            id_pago, preference_id, init_point, sandbox_init_point, external_reference, payload
        ) VALUES (
            :pago, :pref, :init, :sandbox, :external, :payload
        )"
    );
    $mpStmt->execute([
        ':pago' => $pagoId,
        ':pref' => $preferenceId,
        ':init' => $initPoint,
        ':sandbox' => $sandboxInitPoint !== '' ? $sandboxInitPoint : null,
        ':external' => $externalReference,
        ':payload' => checkout_encode_payload($payloadData),
    ]);

    if ($esCertificacion && $certificadoRow) {
        $updateCertificado = $con->prepare('UPDATE certificados SET estado = :estado, pago_metodo = :metodo, pago_estado = :pago_estado, pago_monto = :monto, pago_moneda = :moneda, pago_referencia = :referencia, pago_comprobante_path = NULL, pago_comprobante_nombre = NULL, pago_comprobante_mime = NULL, pago_comprobante_tamano = NULL, actualizado_en = NOW() WHERE id_certificado = :id');
        $updateCertificado->execute([
            ':estado' => 'pago_pendiente_confirmacion',
            ':metodo' => 'mercado_pago',
            ':pago_estado' => 'pendiente',
            ':monto' => $precioFinal,
            ':moneda' => strtoupper($moneda),
            ':referencia' => $externalReference,
            ':id' => (int)$certificadoRow['id_certificado'],
        ]);
    }

    $con->commit();

    checkout_log_event('checkout_mp_preference_creada', [
        'inscripcion' => $inscripcionId,
        'pago' => $pagoId,
        'preference_id' => $preferenceId,
        'monto' => $precioFinal,
        'moneda' => $moneda,
    ]);

    $response['success'] = true;
    $response['init_point'] = $initPoint;
    $response['preference_id'] = $preferenceId;
    $response['orden'] = $inscripcionId;
} catch (Throwable $exception) {
    if ($responseCode === 200) {
        $responseCode = $exception instanceof InvalidArgumentException ? 400 : 500;
    }
    $response['message'] = $exception->getMessage();
    checkout_log_event('checkout_mp_preference_fail', ['error' => $exception->getMessage()], $exception);
}

http_response_code($responseCode);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
