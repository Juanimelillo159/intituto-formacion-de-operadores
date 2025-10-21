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

        $mpRow = mp_find_order($con, [
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
                $paymentData = mp_fetch_payment((string) $paymentLookupId);
            }
        } catch (Throwable $apiError) {
            mp_log('mp_return_payment_error', ['payment_id' => $paymentId], $apiError);
        }

        if ($paymentData) {
            $mpRow = mp_update_payment_status($con, $mpRow, $paymentData);
        }

        $orderData = $mpRow;
        $estadoPago = (string) ($mpRow['pago_estado'] ?? 'pendiente');
        $mpStatus = (string) ($mpRow['status'] ?? '');
        $statusDetail = (string) ($mpRow['status_detail'] ?? '');

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
        mp_log('mp_return_error', ['error' => $exception->getMessage()], $exception);
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

    $fallback = mp_status_detail_message($detail);
    if ($fallback) {
        return $fallback . ' (código: ' . strtoupper($detail) . ')';
    }

    if (function_exists('str_starts_with') && str_starts_with($detail, 'cc_rejected_')) {
        return 'Mercado Pago rechazó la tarjeta. Probá con otro medio de pago. (código: ' . strtoupper($detail) . ')';
    }

    return 'Código informado por Mercado Pago: ' . strtoupper($detail);
}

$canResumePayment = false;
$resumePaymentLabel = null;
$resumePaymentUrl = null;
if (!$error && $orderData) {
    $tipoOrden = strtolower((string)($orderData['tipo_checkout'] ?? $tipoParam));
    $registroId = (int)($orderData['id_inscripcion'] ?? 0);
    if ($registroId <= 0 && isset($orderData['id_capacitacion'])) {
        $registroId = (int)$orderData['id_capacitacion'];
    }
    if ($registroId <= 0 && isset($orderData['id_certificacion'])) {
        $registroId = (int)$orderData['id_certificacion'];
    }

    $estadoPagoLower = strtolower((string)$estadoPago);
    if ($registroId > 0 && in_array($tipoOrden, ['capacitacion', 'certificacion'], true) && in_array($estadoPagoLower, ['pendiente', 'cancelado', 'rechazado'], true)) {
        $canResumePayment = true;
        $resumePaymentLabel = $estadoPagoLower === 'pendiente' ? 'Continuar pago' : 'Retomar pago';
        $resumePaymentUrl = 'retomar_pago.php?tipo=' . $tipoOrden . '&id=' . $registroId;
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/../head.php'; ?>
<head>
    <!-- Añadiendo estilos modernos consistentes con curso-detalle.php -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --bg-light: #f9fafb;
            --border-color: #e5e7eb;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .checkout-main {
            padding: 4rem 0;
            min-height: calc(100vh - 200px);
        }

        .thank-you-container {
            max-width: 800px;
            margin: 0 auto;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            margin-bottom: 2rem;
            text-align: center;
        }

        .status-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            animation: scaleIn 0.5s ease-out 0.3s both;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        .status-icon.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .status-icon.pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .status-icon.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .status-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .status-message {
            font-size: 1.1rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .order-number {
            display: inline-block;
            background: var(--bg-light);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            color: var(--primary-color);
            margin-top: 1rem;
        }

        .details-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .details-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .details-header i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .details-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .detail-label i {
            color: var(--primary-color);
            width: 20px;
        }

        .detail-value {
            font-weight: 600;
            color: var(--text-dark);
            text-align: right;
        }

        .detail-value.highlight {
            font-size: 1.25rem;
            color: var(--primary-color);
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-badge.success {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .error-detail {
            background: #fef2f2;
            border-left: 4px solid var(--danger-color);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .error-detail p {
            margin: 0.5rem 0;
            color: var(--text-dark);
        }

        .error-detail a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
        }

        .error-detail a:hover {
            text-decoration: underline;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
            color: white;
        }

        .btn-secondary {
            background: white;
            color: var(--primary-color);
            padding: 1rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .info-box {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border-radius: 12px;
            padding: 1.5rem;
            margin-top: 2rem;
            display: flex;
            align-items: start;
            gap: 1rem;
        }

        .info-box i {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin-top: 0.2rem;
        }

        .info-box-content h3 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .info-box-content p {
            color: var(--text-muted);
            margin: 0;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .checkout-main {
                padding: 2rem 0;
            }

            .status-card {
                padding: 2rem 1.5rem;
            }

            .details-card {
                padding: 1.5rem;
            }

            .status-title {
                font-size: 1.5rem;
            }

            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .detail-value {
                text-align: left;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-primary,
            .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../nav.php'; ?>

<main class="checkout-main">
    <div class="container">
        <div class="thank-you-container">
            <!-- Card de estado principal con iconos grandes y diseño moderno -->
            <div class="status-card">
                <?php if ($error): ?>
                    <div class="status-icon error">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h1 class="status-title">Hubo un problema</h1>
                    <p class="status-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    
                    <?php if ($errorDetail): ?>
                        <div class="error-detail">
                            <p><strong>Detalle:</strong> <?php echo htmlspecialchars($errorDetail, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p>Si realizaste el pago correctamente, por favor contactanos a 
                                <a href="mailto:<?php echo htmlspecialchars(checkout_mail_config()['admin_email'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars(checkout_mail_config()['admin_email'], ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($estadoPago === 'pagado'): ?>
                        <div class="status-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h1 class="status-title">¡Pago confirmado!</h1>
                    <?php elseif ($estadoPago === 'pendiente'): ?>
                        <div class="status-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h1 class="status-title">Pago en proceso</h1>
                    <?php else: ?>
                        <div class="status-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h1 class="status-title">¡Gracias por tu inscripción!</h1>
                    <?php endif; ?>
                    
                    <p class="status-message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                    
                    <?php if (!empty($orderData['id_inscripcion'])): ?>
                        <div class="order-number">
                            <i class="fas fa-hashtag"></i> Orden: <?php echo str_pad((string) $orderData['id_inscripcion'], 6, '0', STR_PAD_LEFT); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($canResumePayment && $resumePaymentUrl): ?>
                        <div class="mt-4">
                            <a class="btn btn-primary btn-lg" href="<?php echo htmlspecialchars($resumePaymentUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-rotate-right me-2"></i><?php echo htmlspecialchars($resumePaymentLabel ?? 'Retomar pago', ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Card de detalles con diseño mejorado y iconos -->
            <?php if ($orderData && !$error): ?>
                <div class="details-card">
                    <div class="details-header">
                        <i class="fas fa-file-invoice"></i>
                        <h2>Detalles de tu inscripción</h2>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-graduation-cap"></i>
                            Curso
                        </span>
                        <span class="detail-value"><?php echo htmlspecialchars($orderData['nombre_curso'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-user"></i>
                            Alumno
                        </span>
                        <span class="detail-value"><?php echo htmlspecialchars(trim(($orderData['nombre'] ?? '') . ' ' . ($orderData['apellido'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-envelope"></i>
                            Email
                        </span>
                        <span class="detail-value"><?php echo htmlspecialchars($orderData['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-phone"></i>
                            Teléfono
                        </span>
                        <span class="detail-value"><?php echo htmlspecialchars($orderData['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-credit-card"></i>
                            Método de pago
                        </span>
                        <span class="detail-value"><?php echo htmlspecialchars($orderData['payment_type'] ? strtoupper($orderData['payment_type']) : 'Mercado Pago', ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-info-circle"></i>
                            Estado
                        </span>
                        <span class="detail-value">
                            <span class="status-badge <?php echo $estadoPago === 'pagado' ? 'success' : ($estadoPago === 'pendiente' ? 'pending' : 'error'); ?>">
                                <?php echo htmlspecialchars(checkout_estado_label($estadoPago), ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </span>
                    </div>

                    <?php if (!empty($orderData['payment_id'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">
                                <i class="fas fa-receipt"></i>
                                ID de pago
                            </span>
                            <span class="detail-value"><?php echo htmlspecialchars($orderData['payment_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-dollar-sign"></i>
                            Monto total
                        </span>
                        <span class="detail-value highlight"><?php echo checkout_format_currency((float) ($orderData['monto'] ?? 0), (string) ($orderData['moneda'] ?? 'ARS')); ?></span>
                    </div>
                </div>

                <!-- Caja informativa con próximos pasos -->
                <div class="info-box">
                    <i class="fas fa-lightbulb"></i>
                    <div class="info-box-content">
                        <h3>¿Qué sigue ahora?</h3>
                        <p>Te enviaremos un correo electrónico con toda la información del curso y los próximos pasos. Si tenés alguna consulta, no dudes en contactarnos.</p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Botones de acción con diseño moderno -->
            <div class="action-buttons" style="margin-top: 2rem;">
                <a href="<?php echo htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8'); ?>" class="btn-primary">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo htmlspecialchars($backText, ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <?php if (!$error): ?>
                    <a href="mailto:<?php echo htmlspecialchars(checkout_mail_config()['admin_email'], ENT_QUOTES, 'UTF-8'); ?>" class="btn-secondary">
                        <i class="fas fa-envelope"></i>
                        Contactar soporte
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
