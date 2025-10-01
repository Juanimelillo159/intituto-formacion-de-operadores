<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mercadopago_service.php';

$page_title = 'Gracias por tu compra | Instituto de Formación';
$page_description = 'Confirmación del pago realizado a través de Mercado Pago.';
$asset_base_path = '../';
$base_path = '../';

$estadoPago = null;
$mpStatus = null;
$message = null;
$error = null;
$errorDetail = null;
$orderData = null;

$manualFlash = $_SESSION['checkout_success'] ?? null;
if ($manualFlash !== null) {
    unset($_SESSION['checkout_success']);
}

$tipoParam = strtolower(trim((string)($_GET['tipo'] ?? ($manualFlash['tipo'] ?? ''))));
if ($tipoParam === 'capacitaciones') {
    $tipoParam = 'capacitacion';
} elseif ($tipoParam === 'certificaciones') {
    $tipoParam = 'certificacion';
}
if (!in_array($tipoParam, ['curso', 'capacitacion', 'certificacion'], true)) {
    $tipoParam = 'curso';
}

$backHref = $base_path . 'index.php#cursos';
$backText = 'Volver a los cursos';
if ($tipoParam === 'capacitacion') {
    $backHref = $base_path . 'index.php#servicios-capacitacion';
    $backText = 'Volver a las capacitaciones';
} elseif ($tipoParam === 'certificacion') {
    $backText = 'Volver a las certificaciones';
}

$preferenceId = isset($_GET['preference_id']) ? (string) $_GET['preference_id'] : null;
$paymentId = isset($_GET['payment_id']) ? (string) $_GET['payment_id'] : null;
if (!$paymentId && isset($_GET['collection_id'])) {
    $paymentId = (string) $_GET['collection_id'];
}
$externalRef = isset($_GET['external_reference']) ? (string) $_GET['external_reference'] : null;

$manualOrderId = isset($_GET['orden']) ? (int) $_GET['orden'] : (int)($manualFlash['orden'] ?? 0);
$manualMetodo = isset($_GET['metodo']) ? (string) $_GET['metodo'] : (string)($manualFlash['metodo'] ?? '');
$manualMetodo = strtolower(trim($manualMetodo));

$mpParamsProvided = ($preferenceId || $paymentId || $externalRef);

if (!$mpParamsProvided && $manualOrderId > 0) {
    try {
        if (!isset($con) || !($con instanceof PDO)) {
            throw new RuntimeException('No se pudo conectar con la base de datos.');
        }
        $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sqlDetalle = <<<SQL
SELECT
    p.id_pago,
    p.metodo,
    p.estado,
    p.monto,
    p.moneda AS pago_moneda,
    p.id_capacitacion,
    p.id_certificacion,
    COALESCE(cap.id_capacitacion, cert.id_certificacion) AS id_registro,
    CASE
        WHEN p.id_capacitacion IS NOT NULL THEN 'capacitacion'
        WHEN p.id_certificacion IS NOT NULL THEN 'certificacion'
        ELSE NULL
    END AS tipo_checkout,
    COALESCE(cap.nombre, cert.nombre) AS nombre,
    COALESCE(cap.apellido, cert.apellido) AS apellido,
    COALESCE(cap.email, cert.email) AS email,
    COALESCE(cap.telefono, cert.telefono) AS telefono,
    COALESCE(cap.precio_total, cert.precio_total, p.monto) AS precio_total,
    COALESCE(cap.moneda, cert.moneda, p.moneda) AS moneda_registro,
    COALESCE(cur_cap.nombre_curso, cur_cert.nombre_curso, '') AS nombre_curso
FROM checkout_pagos p
LEFT JOIN checkout_capacitaciones cap ON p.id_capacitacion = cap.id_capacitacion
LEFT JOIN checkout_certificaciones cert ON p.id_certificacion = cert.id_certificacion
LEFT JOIN cursos cur_cap ON cap.id_curso = cur_cap.id_curso
LEFT JOIN cursos cur_cert ON cert.id_curso = cur_cert.id_curso
WHERE (
        (p.id_capacitacion IS NOT NULL AND p.id_capacitacion = :id_cap_pago)
        OR (p.id_certificacion IS NOT NULL AND p.id_certificacion = :id_cert_pago)
        OR (cap.id_capacitacion IS NOT NULL AND cap.id_capacitacion = :id_cap_registro)
        OR (cert.id_certificacion IS NOT NULL AND cert.id_certificacion = :id_cert_registro)
    )
ORDER BY p.id_pago DESC
LIMIT 1
SQL;

        $st = $con->prepare($sqlDetalle);
        $st->execute([
            ':id_cap_pago' => $manualOrderId,
            ':id_cert_pago' => $manualOrderId,
            ':id_cap_registro' => $manualOrderId,
            ':id_cert_registro' => $manualOrderId,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('No encontramos la inscripción generada.');
        }

        $orderData = [
            'id_inscripcion' => (int) ($row['id_registro'] ?? 0),
            'tipo_checkout' => $row['tipo_checkout'] ?? null,
            'nombre_curso' => $row['nombre_curso'] ?? '',
            'nombre' => $row['nombre'] ?? '',
            'apellido' => $row['apellido'] ?? '',
            'email' => $row['email'] ?? '',
            'telefono' => $row['telefono'] ?? '',
            'monto' => isset($row['precio_total']) ? (float) $row['precio_total'] : (isset($row['monto']) ? (float) $row['monto'] : 0.0),
            'moneda' => $row['moneda_registro'] ?? ($row['pago_moneda'] ?? 'ARS'),
            'payment_type' => $row['metodo'] ?? $manualMetodo,
            'payment_id' => null,
        ];

        if (!empty($orderData['tipo_checkout']) && in_array($orderData['tipo_checkout'], ['curso', 'capacitacion', 'certificacion'], true)) {
            $tipoParam = $orderData['tipo_checkout'];
        }

        $estadoPago = isset($row['estado']) && $row['estado'] !== '' ? (string) $row['estado'] : 'pendiente';

        if ($manualMetodo === 'transferencia') {
            $message = '¡Gracias! Recibimos tu comprobante y en breve nos pondremos en contacto.';
        } elseif ($manualMetodo === 'mercado_pago') {
            $message = '¡Gracias! Registramos tu solicitud de pago. Te avisaremos apenas tengamos novedades.';
        } else {
            $message = '¡Gracias! Registramos tu inscripción y continuaremos el proceso junto a vos.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
} else {
    try {
        if (!isset($con) || !($con instanceof PDO)) {
            throw new RuntimeException('No se pudo conectar con la base de datos.');
        }
        $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (!$preferenceId && !$paymentId && !$externalRef) {
            throw new RuntimeException('No recibimos la información del pago.');
        }

        $mpRow = checkout_fetch_mp_order($con, [
            'preference_id' => $preferenceId,
            'payment_id' => $paymentId,
            'external_reference' => $externalRef,
        ]);

        if (!$mpRow) {
            throw new RuntimeException('No encontramos la orden asociada al pago.');
        }

        $paymentData = null;
        try {
            $paymentLookupId = $paymentId ?: ($mpRow['payment_id'] ?? null);
            if ($paymentLookupId) {
                $paymentData = checkout_fetch_payment_from_mp((string) $paymentLookupId);
            }
        } catch (Throwable $apiError) {
            checkout_log_event('checkout_mp_return_payment_error', ['payment_id' => $paymentId], $apiError);
        }

        $sync = checkout_sync_mp_payment($con, $mpRow, $paymentData, 'return', true);
        $orderData = $sync['row'];
        $estadoPago = $sync['estado_pago'];
        $mpStatus = $sync['mp_status'];
        $statusDetail = isset($orderData['status_detail']) ? (string) $orderData['status_detail'] : '';

        if (!empty($orderData['tipo_checkout']) && in_array($orderData['tipo_checkout'], ['curso', 'capacitacion', 'certificacion'], true)) {
            $tipoParam = $orderData['tipo_checkout'];
        }

        if ($estadoPago === 'pagado') {
            $message = '¡Pago acreditado! Reservamos tu lugar y te contactaremos a la brevedad.';
        } elseif ($estadoPago === 'pendiente') {
            $message = 'Tu pago está en proceso. Te avisaremos por correo en cuanto tengamos la confirmación.';
        } elseif ($estadoPago === 'rechazado') {
            $error = 'El pago fue rechazado. Intentalo nuevamente o comunicate con nosotros para ayudarte.';
            $errorDetail = checkout_mp_status_detail_message($statusDetail !== '' ? $statusDetail : $mpStatus);
        } elseif ($estadoPago === 'cancelado') {
            $error = 'El pago se canceló antes de completarse.';
            $errorDetail = checkout_mp_status_detail_message($statusDetail !== '' ? $statusDetail : $mpStatus);
        } else {
            $message = 'Recibimos la actualización del estado de tu pago.';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
        checkout_log_event('checkout_mp_return_error', ['error' => $exception->getMessage()], $exception);
    }
}

function checkout_estado_label(?string $estado): string
{
    return match ($estado) {
        'pagado' => 'Pagado',
        'pendiente' => 'Pendiente',
        'rechazado' => 'Rechazado',
        'cancelado' => 'Cancelado',
        'autorizado' => 'Autorizado',
        'reembolsado' => 'Reembolsado',
        'reversado' => 'Reversado',
        'vencido' => 'Vencido',
        default => ucfirst((string) $estado),
    };
}

function checkout_mp_status_detail_message(?string $statusDetail): ?string
{
    if ($statusDetail === null) {
        return null;
    }

    $detail = strtolower(trim($statusDetail));
    if ($detail === '') {
        return null;
    }

    $map = [
        'cc_rejected_bad_filled_card_number' => 'Revisá el número de la tarjeta ingresado.',
        'cc_rejected_bad_filled_date' => 'Revisá la fecha de vencimiento de la tarjeta.',
        'cc_rejected_bad_filled_security_code' => 'Revisá el código de seguridad (CVV).',
        'cc_rejected_bad_filled_other' => 'Revisá los datos de la tarjeta antes de volver a intentar.',
        'cc_rejected_blacklist' => 'Mercado Pago bloqueó el pago por seguridad.',
        'cc_rejected_call_for_authorize' => 'Tenés que comunicarte con el banco para autorizar el pago y volver a intentarlo.',
        'cc_rejected_card_disabled' => 'La tarjeta no está habilitada para compras en línea o en el exterior.',
        'cc_rejected_card_error' => 'El emisor no pudo procesar la tarjeta en este momento.',
        'cc_rejected_duplicated_payment' => 'Ya existe un pago con la misma información.',
        'cc_rejected_high_risk' => 'Mercado Pago rechazó el pago por medidas de seguridad.',
        'cc_rejected_insufficient_amount' => 'No hay fondos suficientes en la tarjeta o medio de pago.',
        'cc_rejected_invalid_installments' => 'La tarjeta no acepta la cantidad de cuotas seleccionadas.',
        'cc_rejected_max_attempts' => 'Se alcanzó el número máximo de intentos con esta tarjeta.',
        'cc_rejected_other_reason' => 'El pago fue rechazado por la entidad emisora.',
        'cc_rejected_partial_payment' => 'La tarjeta no permite pagar el monto total.',
        'rejected_by_bank' => 'El banco rechazó el pago. Podés comunicarte con ellos para más información.',
        'rejected_high_risk' => 'Mercado Pago rechazó el pago por medidas de seguridad.',
        'rejected_insufficient_amount' => 'No hay fondos suficientes para completar el pago.',
        'rejected_other_reason' => 'Mercado Pago rechazó el pago. Probá con otro medio.',
        'rejected_by_collector' => 'El comercio canceló el pago.',
        'rejected_by_meli' => 'Mercado Pago canceló el pago por seguridad.',
        'cancelled' => 'El pago fue cancelado antes de completarse.',
        'pending_contingency' => 'Mercado Pago está procesando el pago. Te avisaremos cuando se acredite.',
        'pending_review_manual' => 'Mercado Pago está revisando el pago manualmente.',
        'pending_waiting_payment' => 'Estamos esperando la acreditación del pago.',
    ];

    if (isset($map[$detail])) {
        return $map[$detail] . ' (código: ' . strtoupper($detail) . ')';
    }

    if (function_exists('str_starts_with') && str_starts_with($detail, 'cc_rejected_')) {
        return 'Mercado Pago rechazó la tarjeta. Probá con otro medio de pago. (código: ' . strtoupper($detail) . ')';
    }

    return 'Código informado por Mercado Pago: ' . strtoupper($detail);
}

?>
<!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/../head.php'; ?>
<body class="checkout-body">
<?php include __DIR__ . '/../nav.php'; ?>

<main class="checkout-main">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="checkout-card">
                    <div class="checkout-header">
                        <h1>¡Gracias por tu compra!</h1>
                        <p>Te compartimos el detalle de tu inscripción.</p>
                    </div>
                    <div class="checkout-content">
                        <?php if ($error): ?>
                            <div class="alert alert-danger checkout-alert" role="alert">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fas fa-triangle-exclamation mt-1"></i>
                                    <div>
                                        <strong>No pudimos confirmar el pago.</strong>
                                        <div class="small mt-1"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if ($errorDetail): ?>
                                            <div class="small mt-1 text-muted">Detalle informado por Mercado Pago: <?php echo htmlspecialchars($errorDetail, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <div class="small mt-1">Si abonaste correctamente, escribinos a <a href="mailto:<?php echo htmlspecialchars(checkout_mail_config()['admin_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(checkout_mail_config()['admin_email'], ENT_QUOTES, 'UTF-8'); ?></a>.</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if ($message): ?>
                                <div class="alert alert-success checkout-alert" role="alert">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="fas fa-circle-check mt-1"></i>
                                        <div>
                                            <strong><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if (!empty($orderData['id_inscripcion'])): ?>
                                                <div class="small mt-1">Número de orden: #<?php echo str_pad((string) $orderData['id_inscripcion'], 6, '0', STR_PAD_LEFT); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($orderData): ?>
                                <div class="summary-card mb-4">
                                    <h5>Resumen de la inscripción</h5>
                                    <div class="summary-item">
                                        <strong>Curso</strong>
                                        <span><?php echo htmlspecialchars($orderData['nombre_curso'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <strong>Alumno</strong>
                                        <span><?php echo htmlspecialchars(trim(($orderData['nombre'] ?? '') . ' ' . ($orderData['apellido'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <strong>Email</strong>
                                        <span><?php echo htmlspecialchars($orderData['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <strong>Teléfono</strong>
                                        <span><?php echo htmlspecialchars($orderData['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <strong>Monto</strong>
                                        <span><?php echo checkout_format_currency((float) ($orderData['monto'] ?? 0), (string) ($orderData['moneda'] ?? 'ARS')); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <strong>Método</strong>
                                        <span><?php echo htmlspecialchars($orderData['payment_type'] ? strtoupper($orderData['payment_type']) : 'Mercado Pago', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="summary-item">
                                        <strong>Estado</strong>
                                        <span><?php echo htmlspecialchars(checkout_estado_label($estadoPago), ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <?php if (!empty($orderData['payment_id'])): ?>
                                        <div class="summary-item">
                                            <strong>ID de pago</strong>
                                            <span><?php echo htmlspecialchars($orderData['payment_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="checkout-footer text-center">
                                <a class="btn btn-gradient btn-rounded" href="<?php echo htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($backText, ENT_QUOTES, 'UTF-8'); ?>
                                    <i class="fas fa-arrow-right ms-2"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
