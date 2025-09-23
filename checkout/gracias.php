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
$orderData = null;

try {
    if (!isset($con) || !($con instanceof PDO)) {
        throw new RuntimeException('No se pudo conectar con la base de datos.');
    }
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $preferenceId = isset($_GET['preference_id']) ? (string) $_GET['preference_id'] : null;
    $paymentId = isset($_GET['payment_id']) ? (string) $_GET['payment_id'] : null;
    if (!$paymentId && isset($_GET['collection_id'])) {
        $paymentId = (string) $_GET['collection_id'];
    }
    $externalRef = isset($_GET['external_reference']) ? (string) $_GET['external_reference'] : null;

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

    if ($estadoPago === 'pagado') {
        $message = '¡Pago acreditado! Reservamos tu lugar en el curso.';
    } elseif ($estadoPago === 'pendiente') {
        $message = 'Tu pago está en proceso. Te avisaremos por correo en cuanto tengamos la confirmación.';
    } elseif ($estadoPago === 'rechazado') {
        $error = 'El pago fue rechazado. Intentalo nuevamente o comunicate con nosotros para ayudarte.';
    } elseif ($estadoPago === 'cancelado') {
        $error = 'El pago se canceló antes de completarse.';
    } else {
        $message = 'Recibimos la actualización del estado de tu pago.';
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
    checkout_log_event('checkout_mp_return_error', ['error' => $exception->getMessage()], $exception);
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
                                <a class="btn btn-gradient btn-rounded" href="<?php echo $base_path; ?>index.php#cursos">
                                    Volver a los cursos
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
