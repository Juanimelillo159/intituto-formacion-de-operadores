<?php
declare(strict_types=1);

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mercadopago_helpers.php';

function esc_html($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_currency(float $amount, string $currency): string
{
    $code = strtoupper($currency !== '' ? $currency : 'ARS');
    return $code . ' ' . number_format($amount, 2, ',', '.');
}

$externalRef = isset($_GET['external_reference']) ? (string)$_GET['external_reference'] : '';
$paymentId = isset($_GET['payment_id']) ? (string)$_GET['payment_id'] : '';
if ($paymentId === '' && isset($_GET['collection_id'])) {
    $paymentId = (string)$_GET['collection_id'];
}

$orderData = null;
$resultSync = null;
$statusInternal = null;
$parsedReference = null;

try {
    if (!($con instanceof PDO)) {
        throw new RuntimeException('Conexión inválida');
    }

    $config = mp_load_config();

    if ($paymentId !== '') {
        try {
            $resultSync = mp_sync_payment($con, $config, $paymentId);
        } catch (Throwable $syncEx) {
            mp_log('gracias_sync_error', ['payment_id' => $paymentId], $syncEx);
        }
    }

    if ($externalRef !== '') {
        $parsedReference = mp_parse_external_reference($externalRef);
    }
    if (!$parsedReference && $resultSync && isset($resultSync['parsed'])) {
        $parsedReference = $resultSync['parsed'];
    }

    if ($parsedReference) {
        $stmt = $con->prepare(
            "SELECT i.*, p.id_pago, p.metodo, p.estado, p.monto, p.moneda, p.observaciones, c.nombre_curso,
                    mp.status AS mp_status, mp.status_detail, mp.payment_id AS mp_payment_id, mp.payer_email
               FROM checkout_inscripciones i
               JOIN checkout_pagos p ON p.id_inscripcion = i.id_inscripcion
               JOIN cursos c ON c.id_curso = i.id_curso
          LEFT JOIN checkout_mercadopago mp ON mp.id_pago = p.id_pago
              WHERE i.id_inscripcion = :ins AND p.id_pago = :pago
              LIMIT 1"
        );
        $stmt->execute([
            ':ins' => $parsedReference['inscripcion_id'],
            ':pago' => $parsedReference['pago_id'],
        ]);
        $orderData = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($orderData) {
        $estadoPago = strtolower((string)($orderData['estado'] ?? 'pendiente'));
        if (!empty($orderData['mp_status'])) {
            $estadoPago = mp_map_status((string)$orderData['mp_status']);
        }
        if ($resultSync && isset($resultSync['estado'])) {
            $estadoPago = $resultSync['estado'];
        }
        $statusInternal = $estadoPago;
    }
} catch (Throwable $e) {
    mp_log('gracias_error', ['external_reference' => $externalRef, 'payment_id' => $paymentId], $e);
}

$ordenNumero = $orderData ? str_pad((string)$orderData['id_inscripcion'], 6, '0', STR_PAD_LEFT) : null;
$cursoNombre = $orderData['nombre_curso'] ?? '';
$metodoPago = $orderData['metodo'] ?? '';
$metodoLabel = $metodoPago === 'mercado_pago' ? 'Mercado Pago' : ($metodoPago === 'transferencia' ? 'Transferencia bancaria' : ucfirst(str_replace('_', ' ', $metodoPago)));
$montoTotal = $orderData ? format_currency((float)($orderData['monto'] ?? 0), (string)($orderData['moneda'] ?? 'ARS')) : null;
$contactEmailSetting = $config['mail']['admin_email'] ?? 'info@tu-dominio.com';
if (is_array($contactEmailSetting)) {
    $contactEmailSetting = $contactEmailSetting[0] ?? 'info@tu-dominio.com';
}
$contactEmail = (string)$contactEmailSetting;

$estadoNormalized = $statusInternal ? strtolower($statusInternal) : 'pendiente';
$statusTitle = 'Estado del pago';
$statusDescription = 'Estamos revisando la información de tu operación.';
$badgeClass = 'bg-secondary';

switch ($estadoNormalized) {
    case 'aprobado':
    case 'pagado':
        $statusTitle = '¡Gracias por tu compra!';
        $statusDescription = 'Recibimos tu pago correctamente. Nuestro equipo te contactará para coordinar los próximos pasos y garantizar tu lugar en la capacitación.';
        $badgeClass = 'bg-success';
        break;
    case 'pendiente':
    case 'procesando':
    case 'autorizado':
    case 'en_mediacion':
        $statusTitle = 'Pago en proceso de validación';
        $statusDescription = 'El pago está siendo verificado por Mercado Pago. Te avisaremos por correo cuando quede confirmado.';
        $badgeClass = 'bg-warning text-dark';
        break;
    case 'rechazado':
    case 'cancelado':
    case 'contracargo':
        $statusTitle = 'No pudimos confirmar el pago';
        $statusDescription = 'Detectamos un inconveniente con la operación. Podés intentar nuevamente o escribirnos para recibir asistencia personalizada.';
        $badgeClass = 'bg-danger';
        break;
}

$asset_base_path = '../';
$page_styles = '<link rel="stylesheet" href="../checkout/css/style.css">';
$page_title = 'Gracias por tu compra | Instituto de Formación';
$page_description = 'Confirmación de compra e inscripción.';

include __DIR__ . '/../head.php';
?>
<body class="checkout-body">
<?php include __DIR__ . '/../nav.php'; ?>
<main class="checkout-main">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="checkout-card">
                    <div class="checkout-header">
                        <h1>Gracias por tu compra</h1>
                        <p>Tu inscripción fue registrada correctamente. Revisá los detalles a continuación.</p>
                        <?php if ($cursoNombre !== ''): ?>
                            <div class="checkout-course-name">
                                <i class="fas fa-graduation-cap"></i>
                                <?php echo esc_html($cursoNombre); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="checkout-content">
                        <?php if (!$orderData): ?>
                            <div class="alert alert-warning checkout-alert" role="alert">
                                <i class="fas fa-circle-info me-2"></i>
                                No pudimos encontrar la información de la operación. Si necesitás ayuda escribinos a <a href="mailto:<?php echo esc_html($contactEmail); ?>"><?php echo esc_html($contactEmail); ?></a>.
                            </div>
                        <?php else: ?>
                            <div class="mb-4 d-flex align-items-center gap-3">
                                <span class="badge <?php echo $badgeClass; ?> px-3 py-2 fs-6 text-uppercase">Estado: <?php echo esc_html(ucfirst($estadoNormalized)); ?></span>
                                <?php if ($ordenNumero): ?>
                                    <span class="text-muted">Orden #<?php echo esc_html($ordenNumero); ?></span>
                                <?php endif; ?>
                            </div>
                            <h2 class="h4 mb-3"><?php echo esc_html($statusTitle); ?></h2>
                            <p class="text-muted"><?php echo esc_html($statusDescription); ?></p>

                            <div class="summary-card mt-4">
                                <h5>Resumen de la operación</h5>
                                <div class="summary-item">
                                    <strong>Curso</strong>
                                    <span><?php echo esc_html($cursoNombre); ?></span>
                                </div>
                                <?php if ($montoTotal): ?>
                                    <div class="summary-item">
                                        <strong>Monto</strong>
                                        <span><?php echo esc_html($montoTotal); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="summary-item">
                                    <strong>Método de pago</strong>
                                    <span><?php echo esc_html($metodoLabel); ?></span>
                                </div>
                                <div class="summary-item">
                                    <strong>Comprador</strong>
                                    <span><?php echo esc_html(trim(($orderData['nombre'] ?? '') . ' ' . ($orderData['apellido'] ?? ''))); ?></span>
                                </div>
                                <div class="summary-item">
                                    <strong>Correo</strong>
                                    <span><?php echo esc_html($orderData['email'] ?? ''); ?></span>
                                </div>
                                <?php if (!empty($orderData['mp_payment_id'])): ?>
                                    <div class="summary-item">
                                        <strong>ID de pago</strong>
                                        <span><?php echo esc_html($orderData['mp_payment_id']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($orderData['observaciones'])): ?>
                                    <div class="summary-item">
                                        <strong>Observaciones</strong>
                                        <span><?php echo esc_html($orderData['observaciones']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4">
                                <p class="mb-2"><i class="fas fa-envelope-open-text me-2 text-primary"></i>Enviamos un resumen de la compra a tu correo electrónico.</p>
                                <p class="mb-0"><i class="fas fa-headset me-2 text-primary"></i>Ante cualquier duda, escribinos a <a href="mailto:<?php echo esc_html($contactEmail); ?>"><?php echo esc_html($contactEmail); ?></a>.</p>
                            </div>

                            <div class="nav-actions mt-4">
                                <a class="btn btn-outline-light btn-rounded" href="../index.php#cursos">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Volver a los cursos
                                </a>
                                <a class="btn btn-gradient btn-rounded" href="../certificacion.php">
                                    Ver certificaciones disponibles
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
