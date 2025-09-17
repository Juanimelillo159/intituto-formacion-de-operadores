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

$statusParam = isset($_GET['status']) ? strtolower((string)$_GET['status']) : 'pending';
$externalRef = isset($_GET['external_reference']) ? (string)$_GET['external_reference'] : '';
$paymentId = isset($_GET['payment_id']) ? (string)$_GET['payment_id'] : '';
if ($paymentId === '' && isset($_GET['collection_id'])) {
    $paymentId = (string)$_GET['collection_id'];
}

$orderData = null;
$resultSync = null;
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
            mp_log('retorno_sync_error', ['payment_id' => $paymentId], $syncEx);
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
                    mp.status AS mp_status, mp.status_detail, mp.payment_id AS mp_payment_id
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
} catch (Throwable $e) {
    mp_log('retorno_error', ['status' => $statusParam, 'external_reference' => $externalRef, 'payment_id' => $paymentId], $e);
}

$estadoInterno = $statusParam;
if ($resultSync && isset($resultSync['estado'])) {
    $estadoInterno = $resultSync['estado'];
}
if ($orderData && !empty($orderData['mp_status'])) {
    $estadoInterno = mp_map_status((string)$orderData['mp_status']);
}

$ordenNumero = $orderData ? str_pad((string)$orderData['id_inscripcion'], 6, '0', STR_PAD_LEFT) : null;
$cursoNombre = $orderData['nombre_curso'] ?? '';
$cursoId = $orderData['id_curso'] ?? null;
$montoTotal = $orderData ? format_currency((float)($orderData['monto'] ?? 0), (string)($orderData['moneda'] ?? 'ARS')) : null;
$metodoPago = $orderData['metodo'] ?? '';
$metodoLabel = $metodoPago === 'mercado_pago' ? 'Mercado Pago' : ($metodoPago === 'transferencia' ? 'Transferencia bancaria' : ucfirst(str_replace('_', ' ', $metodoPago)));
$contactEmailSetting = $config['mail']['admin_email'] ?? 'info@tu-dominio.com';
if (is_array($contactEmailSetting)) {
    $contactEmailSetting = $contactEmailSetting[0] ?? 'info@tu-dominio.com';
}
$contactEmail = (string)$contactEmailSetting;

$estadoNormalized = strtolower($estadoInterno);
$statusTitle = 'Estamos revisando tu pago';
$statusDescription = 'El estado del pago todavía no fue confirmado. En breve nos pondremos en contacto con vos.';
$badgeClass = 'bg-warning text-dark';

if ($estadoNormalized === 'rechazado' || $estadoNormalized === 'cancelado' || $estadoNormalized === 'contracargo') {
    $statusTitle = 'El pago no se concretó';
    $statusDescription = 'Tu operación no se completó correctamente. Podés intentar nuevamente desde el checkout o escribirnos para que te ayudemos con otra forma de pago.';
    $badgeClass = 'bg-danger';
} elseif ($estadoNormalized === 'aprobado' || $estadoNormalized === 'pagado') {
    header('Location: gracias.php?external_reference=' . urlencode((string)$externalRef) . ($paymentId !== '' ? '&payment_id=' . urlencode($paymentId) : ''));
    exit;
}

$asset_base_path = '../';
$page_styles = '<link rel="stylesheet" href="../checkout/css/style.css">';
$page_title = 'Estado del pago | Instituto de Formación';
$page_description = 'Seguimiento del estado del pago.';

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
                        <h1>Seguimiento de tu pago</h1>
                        <p>Te mostramos el estado actual de la operación registrada.</p>
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
                                No encontramos la orden indicada. Si necesitás ayuda escribinos a <a href="mailto:<?php echo esc_html($contactEmail); ?>"><?php echo esc_html($contactEmail); ?></a>.
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
                                <h5>Detalle de la inscripción</h5>
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
                                    <strong>Correo</strong>
                                    <span><?php echo esc_html($orderData['email'] ?? ''); ?></span>
                                </div>
                                <?php if (!empty($orderData['mp_payment_id'])): ?>
                                    <div class="summary-item">
                                        <strong>ID de pago</strong>
                                        <span><?php echo esc_html($orderData['mp_payment_id']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="mt-4">
                                <p class="mb-2"><i class="fas fa-headset me-2 text-primary"></i>Si querés que te ayudemos a finalizar el proceso, escribinos a <a href="mailto:<?php echo esc_html($contactEmail); ?>"><?php echo esc_html($contactEmail); ?></a>.</p>
                                <p class="mb-0"><i class="fas fa-repeat me-2 text-primary"></i>Podés volver al checkout para completar el pago cuando lo desees.</p>
                            </div>

                            <div class="nav-actions mt-4">
                                <a class="btn btn-outline-light btn-rounded" href="../index.php#cursos">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Volver a los cursos
                                </a>
                                <?php if ($cursoId): ?>
                                    <a class="btn btn-gradient btn-rounded" href="../checkout/checkout.php?id_curso=<?php echo (int)$cursoId; ?>">
                                        Reintentar pago
                                        <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                <?php endif; ?>
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
