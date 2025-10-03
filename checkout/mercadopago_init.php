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

function mp_get_session_user_id(): int
{
    if (isset($_SESSION['id_usuario']) && is_numeric($_SESSION['id_usuario'])) {
        $id = (int)$_SESSION['id_usuario'];
        if ($id > 0) {
            return $id;
        }
    }

    if (!isset($_SESSION['usuario'])) {
        return 0;
    }

    $sessionUsuario = $_SESSION['usuario'];

    if (is_numeric($sessionUsuario)) {
        $id = (int)$sessionUsuario;
        return $id > 0 ? $id : 0;
    }

    if (is_array($sessionUsuario) && isset($sessionUsuario['id_usuario']) && is_numeric($sessionUsuario['id_usuario'])) {
        $id = (int)$sessionUsuario['id_usuario'];
        return $id > 0 ? $id : 0;
    }

    return 0;
}

$registrarHistoricoCert = function (PDO $con, int $idCertificacion, int $estado): void {
    $hist = $con->prepare('
        INSERT INTO historico_estado_certificaciones (id_certificacion, id_estado)
        VALUES (:id, :estado)
    ');
    $hist->execute([
        ':id' => $idCertificacion,
        ':estado' => $estado,
    ]);
};

$responseCode = 200;
$response = ['success' => false, 'message' => ''];

try {
    $currentUserId = mp_get_session_user_id();
    if ($currentUserId <= 0) {
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

    $tipoCheckoutRaw = strtolower(trim((string)($_POST['tipo_checkout'] ?? ($_POST['tipo'] ?? ''))));
    if ($tipoCheckoutRaw === 'certificaciones') {
        $tipoCheckoutRaw = 'certificacion';
    } elseif ($tipoCheckoutRaw === 'capacitaciones') {
        $tipoCheckoutRaw = 'capacitacion';
    }
    if (!in_array($tipoCheckoutRaw, ['curso', 'capacitacion', 'certificacion'], true)) {
        $tipoCheckoutRaw = 'curso';
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

    $certificacionId = (int)($_POST['id_certificacion'] ?? 0);
    $certificacionRow = null;
    if ($tipoCheckoutRaw === 'certificacion') {
        if ($certificacionId <= 0) {
            throw new RuntimeException('Necesitamos la certificación aprobada para continuar.');
        }
        $certStmt = $con->prepare('SELECT * FROM checkout_certificaciones WHERE id_certificacion = :id LIMIT 1');
        $certStmt->execute([':id' => $certificacionId]);
        $certificacionRow = $certStmt->fetch();
        if (!$certificacionRow) {
            throw new RuntimeException('No encontramos la certificación registrada.');
        }
        if ($currentUserId > 0 && (int)$certificacionRow['creado_por'] !== $currentUserId) {
            throw new RuntimeException('No tenés autorización para pagar esta certificación.');
        }
        $estadoCert = (int)$certificacionRow['id_estado'];
        if ($estadoCert === 3) {
            throw new RuntimeException('La certificación ya registra un pago.');
        }
        if ($estadoCert !== 2) {
            throw new RuntimeException('Debés esperar la aprobación de la certificación antes de continuar.');
        }
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

    $con->beginTransaction();

    $capacitacionId = null;
    $pagoId = 0;
    $registroId = 0;

    if ($tipoCheckoutRaw !== 'certificacion') {
        $capacitacionStmt = $con->prepare(
            "INSERT INTO checkout_capacitaciones (
                creado_por, id_curso, nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais, acepta_tyc, precio_total, moneda
            ) VALUES (
                :creado_por, :curso, :nombre, :apellido, :email, :telefono, :dni, :direccion, :ciudad, :provincia, :pais, 1, :precio, :moneda
            )"
        );
        $capacitacionStmt->execute([
            ':creado_por' => $currentUserId > 0 ? $currentUserId : null,
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

        $capacitacionId = (int) $con->lastInsertId();
        $registroId = $capacitacionId;

        $pagoStmt = $con->prepare(
            "INSERT INTO checkout_pagos (
                id_capacitacion, metodo, estado, monto, moneda
            ) VALUES (
                :capacitacion, 'mercado_pago', 'pendiente', :monto, :moneda
            )"
        );
        $pagoStmt->execute([
            ':capacitacion' => $capacitacionId,
            ':monto' => $precioFinal,
            ':moneda' => strtoupper($moneda),
        ]);

        $pagoId = (int) $con->lastInsertId();
    }

    if ($tipoCheckoutRaw === 'certificacion' && $certificacionRow) {
        $registroId = (int) $certificacionRow['id_certificacion'];

        $pagoStmt = $con->prepare(
            "INSERT INTO checkout_pagos (
                id_certificacion, metodo, estado, monto, moneda
            ) VALUES (
                :certificacion, 'mercado_pago', 'pendiente', :monto, :moneda
            )"
        );
        $pagoStmt->execute([
            ':certificacion' => $registroId,
            ':monto' => $precioFinal,
            ':moneda' => strtoupper($moneda),
        ]);

        $pagoId = (int) $con->lastInsertId();

    }

    if ($tipoCheckoutRaw === 'certificacion' && $certificacionRow) {
        $observacionesCert = 'Pago iniciado por Mercado Pago el ' . date('d/m/Y H:i');
        $observacionesPrevias = trim((string)($certificacionRow['observaciones'] ?? ''));
        if ($observacionesPrevias !== '') {
            $observacionesCert .= ' | ' . $observacionesPrevias;
        }
        $upCert = $con->prepare('
            UPDATE checkout_certificaciones
               SET id_estado = 3,
                   precio_total = :precio,
                   moneda = :moneda,
                   observaciones = :obs,
                   nombre = :nombre,
                   apellido = :apellido,
                   email = :email,
                   telefono = :telefono,
                   acepta_tyc = 1
             WHERE id_certificacion = :id
        ');
        $upCert->execute([
            ':precio' => $precioFinal,
            ':moneda' => strtoupper($moneda),
            ':obs' => $observacionesCert,
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':email' => $email,
            ':telefono' => $telefono,
            ':id' => (int)$certificacionRow['id_certificacion'],
        ]);
        $registrarHistoricoCert($con, (int)$certificacionRow['id_certificacion'], 3);
    }

    checkout_configure_mp();
    $preferenceClient = new PreferenceClient();

    $baseUrl = checkout_get_base_url();
    if ($registroId <= 0) {
        throw new RuntimeException('No pudimos generar la orden de pago.');
    }

    $externalReference = 'insc-' . $registroId;

    $successUrl = defined('URL_SUCCESS') ? URL_SUCCESS : $baseUrl . '/checkout/gracias.php';
    $pendingUrl = defined('URL_PENDING') ? URL_PENDING : $baseUrl . '/checkout/gracias.php';
    $failureUrl = defined('URL_FAILURE') ? URL_FAILURE : $baseUrl . '/checkout/gracias.php';

    $preferenceRequest = [
        'items' => [[
            'id' => 'curso-' . $cursoId,
            'title' => $curso['nombre_curso'],
            'description' => 'Inscripción al curso ' . $curso['nombre_curso'],
            'quantity' => 1,
            'unit_price' => round($precioFinal, 2),
            'currency_id' => strtoupper($moneda),
        ]],
        'back_urls' => [
            'success' => $successUrl,
            'pending' => $pendingUrl,
            'failure' => $failureUrl,
        ],
        'auto_return' => 'approved',
        'external_reference' => $externalReference,
        'metadata' => [
            'id_pago' => $pagoId,
            'id_inscripcion' => $registroId,
            'id_capacitacion' => $capacitacionId,
            'id_curso' => $cursoId,
            'email' => $email,
            'id_certificacion' => $certificacionRow ? (int) $certificacionRow['id_certificacion'] : null,
            'tipo_checkout' => $tipoCheckoutRaw,
        ],
    ];

    if ($email !== '') {
        $preferenceRequest['payer'] = ['email' => $email];
    }

    $notificationUrl = checkout_env('MP_NOTIFICATION_URL');
    if (!$notificationUrl) {
        $notificationUrl = $baseUrl . '/checkout/mercadopago_webhook.php';
    }
    if (defined('URL_WEBHOOK')) {
        $notificationUrl = URL_WEBHOOK;
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
        checkout_log_event('checkout_mp_preference_error', ['curso' => $cursoId, 'registro' => $registroId], $mpException);
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

    $con->commit();

    checkout_log_event('checkout_mp_preference_creada', [
        'registro' => $registroId,
        'pago' => $pagoId,
        'preference_id' => $preferenceId,
        'monto' => $precioFinal,
        'moneda' => $moneda,
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
    checkout_log_event('checkout_mp_preference_fail', ['error' => $exception->getMessage()], $exception);
}

http_response_code($responseCode);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
