<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mercadopago_common.php';

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

if (!isset($con) || !($con instanceof PDO)) {
    http_response_code(500);
    echo 'No se pudo conectar con la base de datos.';
    exit;
}

$con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$usuarioId = mp_current_user_id();
if ($usuarioId <= 0) {
    $_SESSION['login_mensaje'] = 'Debés iniciar sesión para continuar.';
    $_SESSION['login_tipo'] = 'warning';
    header('Location: ../login.php');
    exit;
}

$tipo = strtolower(trim((string)($_GET['tipo'] ?? '')));
if ($tipo === 'capacitaciones') {
    $tipo = 'capacitacion';
} elseif ($tipo === 'certificaciones') {
    $tipo = 'certificacion';
}

$registroId = (int)($_GET['id'] ?? 0);

$redirectMisCursos = static function (string $message, string $type = 'danger'): void {
    $_SESSION['mis_cursos_feedback'] = [
        'type' => $type,
        'message' => $message,
    ];
    header('Location: ../mis_cursos.php');
    exit;
};

$redirectGracias = static function (string $tipoCheckout, int $registroId, ?int $pagoId = null, ?string $message = null): void {
    if ($message !== null && $message !== '') {
        $_SESSION['checkout_success'] = $message;
    }

    if ($tipoCheckout === 'certificacion') {
        header('Location: gracias_certificacion.php?' . http_build_query(['certificacion' => $registroId]));
    } else {
        $params = [
            'tipo' => 'capacitacion',
            'orden' => $registroId,
        ];
        if ($pagoId !== null && $pagoId > 0) {
            $params['pago'] = $pagoId;
        }
        header('Location: gracias.php?' . http_build_query($params));
    }
    exit;
};

if ($registroId <= 0 || !in_array($tipo, ['capacitacion', 'certificacion'], true)) {
    $redirectMisCursos('No encontramos la inscripción seleccionada.');
}

try {
    if ($tipo === 'capacitacion') {
        $stmt = $con->prepare('SELECT * FROM checkout_capacitaciones WHERE id_capacitacion = :id LIMIT 1');
        $stmt->execute([':id' => $registroId]);
        $capacitacion = $stmt->fetch();
        if (!$capacitacion || (int)($capacitacion['creado_por'] ?? 0) !== $usuarioId) {
            $redirectMisCursos('No tenés permisos para retomar este pago.');
        }

        $cursoStmt = $con->prepare('SELECT nombre_curso FROM cursos WHERE id_curso = :id LIMIT 1');
        $cursoStmt->execute([':id' => (int)$capacitacion['id_curso']]);
        $cursoNombre = (string)$cursoStmt->fetchColumn();
        if ($cursoNombre === '') {
            $cursoNombre = 'Curso #' . (int)$capacitacion['id_curso'];
        }

        $precio = (float)($capacitacion['precio_total'] ?? 0);
        $moneda = (string)($capacitacion['moneda'] ?? 'ARS');
        if ($precio <= 0) {
            $precioInfo = mp_fetch_course_price($con, (int)$capacitacion['id_curso'], 'capacitacion');
            $precio = $precioInfo['amount'];
            $moneda = $precioInfo['currency'];
        }
        if ($precio <= 0) {
            $redirectMisCursos('Todavía no definimos un precio vigente para esta capacitación.');
        }

        $pagoStmt = $con->prepare('SELECT * FROM checkout_pagos WHERE id_capacitacion = :id ORDER BY id_pago DESC LIMIT 1');
        $pagoStmt->execute([':id' => $registroId]);
        $pagoActual = $pagoStmt->fetch();

        $ultimoMetodo = strtolower((string)($pagoActual['metodo'] ?? 'mercado_pago'));
        if ($pagoActual && $ultimoMetodo !== '' && $ultimoMetodo !== 'mercado_pago') {
            $redirectMisCursos('Este pago se gestiona manualmente. Comunicate con nuestro equipo para continuar.', 'info');
        }

        if ($pagoActual && in_array($pagoActual['estado'], ['pagado', 'aprobado'], true)) {
            $redirectGracias('capacitacion', $registroId, (int)$pagoActual['id_pago'], 'El pago ya se encuentra acreditado.');
        }

        if ($pagoActual && $pagoActual['estado'] === 'pendiente') {
            $prefStmt = $con->prepare('SELECT init_point FROM checkout_mercadopago WHERE id_pago = :pago ORDER BY id_mp DESC LIMIT 1');
            $prefStmt->execute([':pago' => $pagoActual['id_pago']]);
            $prefRow = $prefStmt->fetch();
            if ($prefRow && !empty($prefRow['init_point'])) {
                header('Location: ' . $prefRow['init_point']);
                exit;
            }
        }

        $con->beginTransaction();

        $updateCap = $con->prepare('UPDATE checkout_capacitaciones SET precio_total = :precio, moneda = :moneda WHERE id_capacitacion = :id');
        $updateCap->execute([
            ':precio' => $precio,
            ':moneda' => $moneda,
            ':id' => $registroId,
        ]);

        $insertPago = $con->prepare('INSERT INTO checkout_pagos (id_capacitacion, metodo, estado, monto, moneda) VALUES (:capacitacion, :metodo, :estado, :monto, :moneda)');
        $insertPago->execute([
            ':capacitacion' => $registroId,
            ':metodo' => 'mercado_pago',
            ':estado' => 'pendiente',
            ':monto' => $precio,
            ':moneda' => $moneda,
        ]);
        $pagoId = (int)$con->lastInsertId();

        mp_configure_sdk();
        $client = new PreferenceClient();

        $externalReference = sprintf('capacitacion-%d-%s', (int)$capacitacion['id_curso'], bin2hex(random_bytes(4)));

        $preferenceRequest = [
            'items' => [[
                'id' => (string)$capacitacion['id_curso'],
                'title' => $cursoNombre,
                'description' => 'Inscripción a la capacitación ' . $cursoNombre,
                'quantity' => 1,
                'unit_price' => round($precio, 2),
                'currency_id' => $moneda,
            ]],
            'external_reference' => $externalReference,
            'payer' => [
                'email' => (string)($capacitacion['email'] ?? ''),
                'first_name' => (string)($capacitacion['nombre'] ?? ''),
                'last_name' => (string)($capacitacion['apellido'] ?? ''),
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
                'id_capacitacion' => $registroId,
                'id_curso' => (int)$capacitacion['id_curso'],
                'tipo_checkout' => 'capacitacion',
                'email' => (string)($capacitacion['email'] ?? ''),
            ],
        ];

        try {
            $preference = $client->create($preferenceRequest);
        } catch (MPApiException $exception) {
            $con->rollBack();
            mp_log('mp_retry_preference_error', ['capacitacion' => $registroId, 'pago' => $pagoId], $exception);
            throw new RuntimeException('No pudimos crear la preferencia de pago. Intentalo nuevamente.');
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

        mp_log('mp_retry_preference_created', [
            'pago' => $pagoId,
            'registro' => $registroId,
            'preference' => $preferenceId,
            'monto' => $precio,
            'moneda' => $moneda,
        ]);

        header('Location: ' . $initPoint);
        exit;
    }

    // Certificaciones
    $stmt = $con->prepare('SELECT * FROM checkout_certificaciones WHERE id_certificacion = :id LIMIT 1');
    $stmt->execute([':id' => $registroId]);
    $certificacion = $stmt->fetch();
    if (!$certificacion || (int)($certificacion['creado_por'] ?? 0) !== $usuarioId) {
        $redirectMisCursos('No tenés permisos para retomar este pago.');
    }

    $estadoCert = (int)($certificacion['id_estado'] ?? 0);
    if ($estadoCert === 3) {
        $redirectGracias('certificacion', $registroId, null, 'El pago ya se encuentra registrado.');
    }
    if ($estadoCert !== 2) {
        $redirectMisCursos('La certificación todavía no está aprobada para realizar el pago.');
    }

    $cursoStmt = $con->prepare('SELECT nombre_curso FROM cursos WHERE id_curso = :id LIMIT 1');
    $cursoStmt->execute([':id' => (int)$certificacion['id_curso']]);
    $cursoNombre = (string)$cursoStmt->fetchColumn();
    if ($cursoNombre === '') {
        $cursoNombre = 'Curso #' . (int)$certificacion['id_curso'];
    }

    $precio = (float)($certificacion['precio_total'] ?? 0);
    $moneda = (string)($certificacion['moneda'] ?? 'ARS');
    if ($precio <= 0) {
        $precioInfo = mp_fetch_course_price($con, (int)$certificacion['id_curso'], 'certificacion');
        $precio = $precioInfo['amount'];
        $moneda = $precioInfo['currency'];
    }
    if ($precio <= 0) {
        $redirectMisCursos('Todavía no definimos un precio vigente para esta certificación.');
    }

    $pagoStmt = $con->prepare('SELECT * FROM checkout_pagos WHERE id_certificacion = :id ORDER BY id_pago DESC LIMIT 1');
    $pagoStmt->execute([':id' => $registroId]);
    $pagoActual = $pagoStmt->fetch();
    $ultimoMetodo = strtolower((string)($pagoActual['metodo'] ?? 'mercado_pago'));
    if ($pagoActual && $ultimoMetodo !== '' && $ultimoMetodo !== 'mercado_pago') {
        $redirectMisCursos('Este pago se gestiona manualmente. Comunicate con nuestro equipo para continuar.', 'info');
    }
    if ($pagoActual && in_array($pagoActual['estado'], ['pagado', 'aprobado'], true)) {
        $redirectGracias('certificacion', $registroId, (int)$pagoActual['id_pago'], 'El pago ya se encuentra acreditado.');
    }

    if ($pagoActual && $pagoActual['estado'] === 'pendiente') {
        $prefStmt = $con->prepare('SELECT init_point FROM checkout_mercadopago WHERE id_pago = :pago ORDER BY id_mp DESC LIMIT 1');
        $prefStmt->execute([':pago' => $pagoActual['id_pago']]);
        $prefRow = $prefStmt->fetch();
        if ($prefRow && !empty($prefRow['init_point'])) {
            header('Location: ' . $prefRow['init_point']);
            exit;
        }
    }

    $con->beginTransaction();

    $insertPago = $con->prepare('INSERT INTO checkout_pagos (id_certificacion, metodo, estado, monto, moneda) VALUES (:certificacion, :metodo, :estado, :monto, :moneda)');
    $insertPago->execute([
        ':certificacion' => $registroId,
        ':metodo' => 'mercado_pago',
        ':estado' => 'pendiente',
        ':monto' => $precio,
        ':moneda' => $moneda,
    ]);
    $pagoId = (int)$con->lastInsertId();

    $updateCert = $con->prepare('UPDATE checkout_certificaciones SET id_estado = 3, precio_total = :precio, moneda = :moneda WHERE id_certificacion = :id');
    $updateCert->execute([
        ':precio' => $precio,
        ':moneda' => $moneda,
        ':id' => $registroId,
    ]);

    $hist = $con->prepare('INSERT INTO historico_estado_certificaciones (id_certificacion, id_estado) VALUES (:id, 3)');
    $hist->execute([
        ':id' => $registroId,
        ':estado' => 3,
    ]);

    mp_configure_sdk();
    $client = new PreferenceClient();

    $externalReference = sprintf('certificacion-%d-%s', (int)$certificacion['id_curso'], bin2hex(random_bytes(4)));

    $preferenceRequest = [
        'items' => [[
            'id' => (string)$certificacion['id_curso'],
            'title' => $cursoNombre,
            'description' => 'Pago de certificación ' . $cursoNombre,
            'quantity' => 1,
            'unit_price' => round($precio, 2),
            'currency_id' => $moneda,
        ]],
        'external_reference' => $externalReference,
        'payer' => [
            'email' => (string)($certificacion['email'] ?? ''),
            'first_name' => (string)($certificacion['nombre'] ?? ''),
            'last_name' => (string)($certificacion['apellido'] ?? ''),
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
            'id_certificacion' => $registroId,
            'id_curso' => (int)$certificacion['id_curso'],
            'tipo_checkout' => 'certificacion',
            'email' => (string)($certificacion['email'] ?? ''),
        ],
    ];

    try {
        $preference = $client->create($preferenceRequest);
    } catch (MPApiException $exception) {
        $con->rollBack();
        mp_log('mp_retry_preference_error', ['certificacion' => $registroId, 'pago' => $pagoId], $exception);
        throw new RuntimeException('No pudimos crear la preferencia de pago. Intentalo nuevamente.');
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

    mp_log('mp_retry_preference_created', [
        'pago' => $pagoId,
        'registro' => $registroId,
        'preference' => $preferenceId,
        'monto' => $precio,
        'moneda' => $moneda,
    ]);

    header('Location: ' . $initPoint);
    exit;
} catch (Throwable $exception) {
    mp_log('mp_retry_preference_failed', ['tipo' => $tipo, 'registro' => $registroId, 'error' => $exception->getMessage()]);
    $redirectMisCursos($exception->getMessage());
}

