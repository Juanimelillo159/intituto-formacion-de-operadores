<?php
declare(strict_types=1);

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

$config = [];
$configPath = __DIR__ . '/../config/config.php';
if (is_file($configPath)) {
    $configData = require $configPath;
    if (is_array($configData)) {
        $config = $configData;
    }
}

$mpConfig = $config['mercadopago'] ?? [];
$mailConfig = $config['mailer'] ?? [];

function checkout_log(string $accion, array $data = [], ?Throwable $ex = null): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $file = $logDir . '/cursos.log';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $now = (new DateTime('now'))->format('Y-m-d H:i:s');
    $row = ['ts' => $now, 'user' => 'anon', 'ip' => $ip, 'accion' => $accion, 'data' => $data];
    if ($ex) {
        $row['error'] = [
            'type' => get_class($ex),
            'message' => $ex->getMessage(),
            'code' => (string)$ex->getCode(),
        ];
    }
    @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

function h(?string $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function checkout_mp_is_configured_local(array $cfg): bool
{
    $token = trim((string)($cfg['access_token'] ?? ''));
    $publicKey = trim((string)($cfg['public_key'] ?? ''));
    if ($token === '' || $publicKey === '') {
        return false;
    }
    if (stripos($token, 'TU_ACCESS_TOKEN') !== false || stripos($publicKey, 'TU_PUBLIC_KEY') !== false) {
        return false;
    }
    return true;
}

function checkout_merge_metadata_local(?string $existingJson, array $newData): string
{
    $existing = [];
    if ($existingJson) {
        $decoded = json_decode($existingJson, true);
        if (is_array($decoded)) {
            $existing = $decoded;
        }
    }
    $merged = array_replace_recursive($existing, $newData);
    return json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function checkout_format_currency(float $amount, string $currency): string
{
    return strtoupper($currency) . ' ' . number_format($amount, 2, ',', '.');
}

function checkout_send_email(array $mailConfig, string $toEmail, string $toName, string $subject, string $bodyHtml, array &$errors): void
{
    $host = trim((string)($mailConfig['host'] ?? ''));
    $username = trim((string)($mailConfig['username'] ?? ''));
    $password = (string)($mailConfig['password'] ?? '');
    $fromEmail = trim((string)($mailConfig['from_email'] ?? ''));
    if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
        $errors[] = 'La configuración de correo no está completa. No se envió el email a ' . $toEmail . '.';
        return;
    }

    $fromName = (string)($mailConfig['from_name'] ?? 'Instituto de Formación');
    $port = (int)($mailConfig['port'] ?? 465);
    $encryption = strtolower((string)($mailConfig['encryption'] ?? 'ssl'));

    $mailer = new PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->SMTPAuth = true;
        $mailer->Host = $host;
        $mailer->Username = $username;
        $mailer->Password = $password;
        if ($encryption === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'none' || $encryption === '') {
            $mailer->SMTPSecure = false;
        } else {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        $mailer->Port = $port > 0 ? $port : 465;

        $mailer->setFrom($fromEmail, $fromName);
        $mailer->addAddress($toEmail, $toName);
        if (!empty($mailConfig['reply_to'])) {
            $mailer->addReplyTo((string)$mailConfig['reply_to']);
        }

        $mailer->isHTML(true);
        $mailer->Subject = $subject;
        $mailer->Body = $bodyHtml;
        $mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyHtml));

        $mailer->send();
    } catch (PHPMailerException $e) {
        $errors[] = 'No se pudo enviar el email a ' . $toEmail . ': ' . $e->getMessage();
    } catch (Throwable $e) {
        $errors[] = 'No se pudo enviar el email a ' . $toEmail . ': ' . $e->getMessage();
    }
}

$statusParam = strtolower(trim((string)($_GET['status'] ?? '')));
$paymentIdParam = $_GET['payment_id'] ?? ($_GET['collection_id'] ?? null);
$paymentId = null;
if ($paymentIdParam !== null && $paymentIdParam !== '') {
    $paymentIdNumeric = (int)$paymentIdParam;
    if ($paymentIdNumeric > 0) {
        $paymentId = $paymentIdNumeric;
    }
}
$externalReference = trim((string)($_GET['external_reference'] ?? ''));
$preferenceId = trim((string)($_GET['preference_id'] ?? ''));
$idInscripcion = (int)($_GET['orden'] ?? $_GET['id_inscripcion'] ?? 0);
$idPago = (int)($_GET['pago'] ?? 0);

if ($idPago <= 0 && $externalReference !== '') {
    if (preg_match('/pago-(\d+)/', $externalReference, $m)) {
        $idPago = (int)$m[1];
    }
}

$pagoRow = null;
$errorMessage = null;
$emailErrors = [];
$mpConfigured = checkout_mp_is_configured_local($mpConfig);
$paymentData = null;
$paymentError = null;
$estadoAnterior = 'pendiente';
$mpStatus = $statusParam;
$mpStatusDetail = null;
$payerEmail = null;
$paymentMethodId = null;
$paymentTypeId = null;
$transactionAmount = null;
$paymentInstallments = null;
$statementDescriptor = null;

try {
    if ($idPago <= 0 && $preferenceId !== '') {
        $prefStmt = $con->prepare('SELECT id_pago, id_inscripcion FROM checkout_pagos WHERE mp_preference_id = :pref LIMIT 1');
        $prefStmt->execute([':pref' => $preferenceId]);
        $prefRow = $prefStmt->fetch(PDO::FETCH_ASSOC);
        if ($prefRow) {
            $idPago = (int)$prefRow['id_pago'];
            if ($idInscripcion <= 0) {
                $idInscripcion = (int)$prefRow['id_inscripcion'];
            }
        }
    }

    if ($idPago <= 0) {
        throw new RuntimeException('No se pudo identificar la orden asociada al pago.');
    }

    $stmt = $con->prepare('
        SELECT p.*, i.*, c.nombre_curso
          FROM checkout_pagos p
          JOIN checkout_inscripciones i ON p.id_inscripcion = i.id_inscripcion
          LEFT JOIN cursos c ON i.id_curso = c.id_curso
         WHERE p.id_pago = :pago
         LIMIT 1
    ');
    $stmt->execute([':pago' => $idPago]);
    $pagoRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pagoRow) {
        throw new RuntimeException('No encontramos el detalle del pago en nuestra base de datos.');
    }

    if ($idInscripcion <= 0) {
        $idInscripcion = (int)$pagoRow['id_inscripcion'];
    }

    $estadoAnterior = (string)($pagoRow['estado'] ?? 'pendiente');
    $mpStatus = $statusParam;
    $mpStatusDetail = null;
    $payerEmail = null;
    $paymentMethodId = null;
    $paymentTypeId = null;
    $transactionAmount = null;
    $paymentInstallments = null;
    $statementDescriptor = null;

    if ($mpConfigured && $paymentId !== null) {
        try {
            $accessToken = trim((string)($mpConfig['access_token'] ?? ''));
            MercadoPagoConfig::setAccessToken($accessToken);
            $paymentClient = new PaymentClient();
            $paymentData = $paymentClient->get($paymentId);
            if ($paymentData) {
                if (isset($paymentData->status)) {
                    $mpStatus = strtolower((string)$paymentData->status);
                }
                if (isset($paymentData->status_detail)) {
                    $mpStatusDetail = (string)$paymentData->status_detail;
                }
                if (isset($paymentData->payer)) {
                    $payerInfo = $paymentData->payer;
                    if (is_object($payerInfo) && isset($payerInfo->email)) {
                        $payerEmail = (string)$payerInfo->email;
                    } elseif (is_array($payerInfo) && isset($payerInfo['email'])) {
                        $payerEmail = (string)$payerInfo['email'];
                    }
                }
                if (isset($paymentData->payment_method_id)) {
                    $paymentMethodId = (string)$paymentData->payment_method_id;
                }
                if (isset($paymentData->payment_type_id)) {
                    $paymentTypeId = (string)$paymentData->payment_type_id;
                }
                if (isset($paymentData->transaction_amount)) {
                    $transactionAmount = (float)$paymentData->transaction_amount;
                }
                if (isset($paymentData->installments)) {
                    $paymentInstallments = (int)$paymentData->installments;
                }
                if (isset($paymentData->statement_descriptor)) {
                    $statementDescriptor = (string)$paymentData->statement_descriptor;
                }
            }
        } catch (Throwable $e) {
            $paymentError = $e->getMessage();
            checkout_log('checkout_mp_payment_error', ['id_pago' => $idPago, 'payment_id' => $paymentId], $e);
        }
    } elseif ($paymentId !== null && !$mpConfigured) {
        $paymentError = 'Mercado Pago no está configurado. No se pudo verificar el estado del pago.';
    }

    if ($mpStatus === '' && in_array($statusParam, ['approved', 'pending', 'failure'], true)) {
        $mpStatus = $statusParam;
    }

    $estadoInterno = 'pendiente';
    if ($mpStatus === 'approved') {
        $estadoInterno = 'aprobado';
    } elseif (in_array($mpStatus, ['pending', 'in_process', 'authorized'], true)) {
        $estadoInterno = 'pendiente';
    } elseif ($mpStatus !== '') {
        $estadoInterno = 'rechazado';
    } elseif ($statusParam === 'failure') {
        $estadoInterno = 'rechazado';
    }

    $prefToStore = $pagoRow['mp_preference_id'] ?? null;
    if ($preferenceId !== '') {
        $prefToStore = $preferenceId;
    }

    $paymentIdToStore = $pagoRow['mp_payment_id'] ?? null;
    if ($paymentId !== null) {
        $paymentIdToStore = (string)$paymentId;
    }
    if ($paymentIdToStore !== null && $paymentIdToStore === '') {
        $paymentIdToStore = null;
    }

    $payerEmailToStore = $pagoRow['mp_payer_email'] ?? null;
    if ($payerEmail) {
        $payerEmailToStore = $payerEmail;
    }

    $metadataUpdate = [
        'payment' => [
            'id' => $paymentIdToStore,
            'status' => $mpStatus ?: null,
            'status_detail' => $mpStatusDetail ?: null,
            'payer_email' => $payerEmailToStore,
            'payment_method_id' => $paymentMethodId,
            'payment_type_id' => $paymentTypeId,
            'transaction_amount' => $transactionAmount,
            'installments' => $paymentInstallments,
            'statement_descriptor' => $statementDescriptor,
        ],
        'query' => $_GET,
    ];

    $metadataJson = checkout_merge_metadata_local($pagoRow['mp_metadata'] ?? null, $metadataUpdate);

    $update = $con->prepare('
        UPDATE checkout_pagos
           SET estado = :estado,
               mp_payment_id = :pid,
               mp_payment_status = :pstatus,
               mp_payment_status_detail = :pdetail,
               mp_payer_email = :payer,
               mp_metadata = :metadata,
               mp_preference_id = :pref
         WHERE id_pago = :id
    ');
    $update->execute([
        ':estado' => $estadoInterno,
        ':pid' => $paymentIdToStore,
        ':pstatus' => $mpStatus ?: null,
        ':pdetail' => $mpStatusDetail ?: null,
        ':payer' => $payerEmailToStore,
        ':metadata' => $metadataJson,
        ':pref' => $prefToStore,
        ':id' => $idPago,
    ]);

    $pagoRow['estado'] = $estadoInterno;
    $pagoRow['mp_payment_id'] = $paymentIdToStore;
    $pagoRow['mp_payment_status'] = $mpStatus;
    $pagoRow['mp_payment_status_detail'] = $mpStatusDetail;
    $pagoRow['mp_payer_email'] = $payerEmailToStore;
    $pagoRow['mp_metadata'] = $metadataJson;
    $pagoRow['mp_preference_id'] = $prefToStore;

    $estadoCambioAprobado = ($estadoAnterior !== 'aprobado' && $estadoInterno === 'aprobado');

    if ($estadoCambioAprobado) {
        $cursoNombre = $pagoRow['nombre_curso'] ?? 'Curso';
        $nombreCompleto = trim(($pagoRow['nombre'] ?? '') . ' ' . ($pagoRow['apellido'] ?? ''));
        $monto = checkout_format_currency((float)$pagoRow['monto'], (string)($pagoRow['moneda'] ?? 'ARS'));
        $ordenNumero = str_pad((string)$pagoRow['id_inscripcion'], 6, '0', STR_PAD_LEFT);
        $paymentMethodText = $paymentMethodId ? strtoupper($paymentMethodId) : 'Mercado Pago';
        $fechaResumen = (new DateTime())->format('d/m/Y H:i');

        $userBody = '<p>Hola ' . h($nombreCompleto) . ',</p>' .
            '<p>Gracias por tu compra del curso <strong>' . h($cursoNombre) . '</strong>.</p>' .
            '<p>Detalle del pago:</p>' .
            '<ul>' .
            '<li><strong>Orden:</strong> #' . h($ordenNumero) . '</li>' .
            '<li><strong>ID de pago:</strong> ' . h((string)$paymentIdToStore) . '</li>' .
            '<li><strong>Monto:</strong> ' . h($monto) . '</li>' .
            '<li><strong>Método:</strong> ' . h($paymentMethodText) . '</li>' .
            '<li><strong>Fecha:</strong> ' . h($fechaResumen) . '</li>' .
            '</ul>' .
            '<p>En breve nuestro equipo se pondrá en contacto para continuar con tu proceso de certificación.</p>' .
            '<p>¡Gracias por confiar en nosotros!</p>';

        $userEmail = trim((string)($pagoRow['email'] ?? ''));
        if ($userEmail !== '') {
            checkout_send_email(
                $mailConfig,
                $userEmail,
                $nombreCompleto !== '' ? $nombreCompleto : $userEmail,
                'Resumen de tu inscripción - ' . $cursoNombre,
                $userBody,
                $emailErrors
            );
        } else {
            $emailErrors[] = 'No se pudo enviar el email al alumno porque no hay una dirección de correo registrada.';
        }

        $adminEmail = trim((string)($mailConfig['admin_email'] ?? ''));
        if ($adminEmail !== '' && stripos($adminEmail, 'tu-dominio.com') === false) {
            $adminBody = '<p>Se registró un pago aprobado en el sitio.</p>' .
                '<ul>' .
                '<li><strong>Orden:</strong> #' . h($ordenNumero) . '</li>' .
                '<li><strong>Curso:</strong> ' . h($cursoNombre) . '</li>' .
                '<li><strong>Alumno:</strong> ' . h($nombreCompleto) . ' (' . h((string)($pagoRow['email'] ?? '')) . ')</li>' .
                '<li><strong>Monto:</strong> ' . h($monto) . '</li>' .
                '<li><strong>ID de pago:</strong> ' . h((string)$paymentIdToStore) . '</li>' .
                '<li><strong>Método:</strong> ' . h($paymentMethodText) . '</li>' .
                '</ul>';

            checkout_send_email(
                $mailConfig,
                $adminEmail,
                'Administración',
                'Nueva compra aprobada - ' . $cursoNombre,
                $adminBody,
                $emailErrors
            );
        }
    }

    checkout_log('checkout_mp_return', [
        'orden' => $idInscripcion,
        'pago' => $idPago,
        'payment_id' => $paymentId,
        'status' => $pagoRow['estado'],
    ]);
} catch (Throwable $e) {
    $errorMessage = $e->getMessage();
    checkout_log('checkout_mp_return_error', ['pago' => $idPago, 'query' => $_GET], $e);
}

if (!is_array($pagoRow)) {
    $pagoRow = [];
}

$estadoActual = $pagoRow['estado'] ?? 'pendiente';
$estadoBadge = 'status-warning';
$estadoTitulo = 'Pago en revisión';
$estadoMensaje = 'Estamos revisando la información de tu pago. Te enviaremos un correo con la confirmación.';
if ($estadoActual === 'aprobado') {
    $estadoBadge = 'status-success';
    $estadoTitulo = '¡Pago confirmado!';
    $estadoMensaje = 'Recibimos tu pago correctamente. Te contactaremos para continuar con tu capacitación.';
} elseif ($estadoActual === 'rechazado') {
    $estadoBadge = 'status-danger';
    $estadoTitulo = 'No pudimos acreditar el pago';
    $estadoMensaje = 'El pago no se completó. Verificá los datos ingresados o intentá nuevamente.';
}

$cursoNombre = $pagoRow['nombre_curso'] ?? 'Curso / Certificación';
$nombreCompleto = trim(($pagoRow['nombre'] ?? '') . ' ' . ($pagoRow['apellido'] ?? ''));
$montoFormateado = isset($pagoRow['monto']) ? checkout_format_currency((float)$pagoRow['monto'], (string)($pagoRow['moneda'] ?? 'ARS')) : '---';
$ordenNumero = isset($pagoRow['id_inscripcion']) ? str_pad((string)$pagoRow['id_inscripcion'], 6, '0', STR_PAD_LEFT) : '---';
$paymentIdMostrar = $pagoRow['mp_payment_id'] ?? ($paymentId !== null ? (string)$paymentId : '---');
$paymentStatusMostrar = $pagoRow['mp_payment_status'] ?? $statusParam;
$paymentDetalleMostrar = $pagoRow['mp_payment_status_detail'] ?? $mpStatusDetail;
$paymentMethodMostrar = $paymentMethodId ?: 'Mercado Pago';
$correoAlumno = $pagoRow['email'] ?? '';

$page_title = 'Gracias por tu compra | Instituto de Formación';
$page_description = 'Confirmación del pago de tu curso/certificación.';
$asset_base_path = '../';
$base_path = '../';
$page_styles = '<link rel="stylesheet" href="../checkout/css/style.css">';
include __DIR__ . '/../head.php';
?>
<body class="checkout-body">
<?php include __DIR__ . '/../nav.php'; ?>
<main class="checkout-main thankyou-main">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="thankyou-card">
                    <?php if ($errorMessage): ?>
                        <div class="alert alert-danger" role="alert">
                            <strong>Ocurrió un problema.</strong>
                            <div class="small mt-1"><?php echo h($errorMessage); ?></div>
                        </div>
                    <?php else: ?>
                        <div class="thankyou-header">
                            <div class="status-badge <?php echo h($estadoBadge); ?>">
                                <?php if ($estadoBadge === 'status-success'): ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php elseif ($estadoBadge === 'status-danger'): ?>
                                    <i class="fas fa-xmark-circle"></i>
                                <?php else: ?>
                                    <i class="fas fa-hourglass-half"></i>
                                <?php endif; ?>
                            </div>
                            <h1><?php echo h($estadoTitulo); ?></h1>
                            <p><?php echo h($estadoMensaje); ?></p>
                        </div>

                        <div class="thankyou-summary">
                            <div class="summary-row">
                                <span>Número de orden</span>
                                <strong>#<?php echo h($ordenNumero); ?></strong>
                            </div>
                            <div class="summary-row">
                                <span>Curso / certificación</span>
                                <strong><?php echo h($cursoNombre); ?></strong>
                            </div>
                            <div class="summary-row">
                                <span>Alumno</span>
                                <strong><?php echo h($nombreCompleto !== '' ? $nombreCompleto : $correoAlumno); ?></strong>
                            </div>
                            <div class="summary-row">
                                <span>Monto abonado</span>
                                <strong><?php echo h($montoFormateado); ?></strong>
                            </div>
                            <div class="summary-row">
                                <span>ID de pago</span>
                                <strong><?php echo h((string)$paymentIdMostrar); ?></strong>
                            </div>
                            <div class="summary-row">
                                <span>Estado en Mercado Pago</span>
                                <strong><?php echo h($paymentStatusMostrar ?: 'Pendiente'); ?></strong>
                            </div>
                            <?php if ($paymentDetalleMostrar): ?>
                                <div class="summary-row">
                                    <span>Detalle</span>
                                    <strong><?php echo h($paymentDetalleMostrar); ?></strong>
                                </div>
                            <?php endif; ?>
                            <div class="summary-row">
                                <span>Método</span>
                                <strong><?php echo h($paymentMethodMostrar); ?></strong>
                            </div>
                        </div>

                        <?php if ($paymentError): ?>
                            <div class="alert alert-warning mt-3" role="alert">
                                <strong>No se pudo verificar el pago automáticamente.</strong>
                                <div class="small mt-1"><?php echo h($paymentError); ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($emailErrors)): ?>
                            <div class="alert alert-warning mt-3" role="alert">
                                <strong>No pudimos enviar todas las notificaciones.</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($emailErrors as $err): ?>
                                        <li><?php echo h($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="thankyou-actions">
                            <a class="btn btn-gradient btn-rounded" href="<?php echo $base_path; ?>index.php#cursos">
                                Ver más cursos
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
