<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/mercadopago_mailer.php';

$page_title = 'Solicitud de certificación enviada | Instituto de Formación';
$page_description = 'Confirmación del envío de la documentación para certificaciones.';
$base_path = '../';

$flashData = $_SESSION['certificacion_gracias'] ?? null;
if ($flashData !== null) {
    unset($_SESSION['certificacion_gracias']);
}

$certificacionId = isset($_GET['certificacion']) ? (int) $_GET['certificacion'] : 0;
$data = null;
$error = null;

if (is_array($flashData) && !empty($flashData)) {
    $data = $flashData;
}

if (!$data && $certificacionId > 0) {
    try {
        if (!isset($con) || !($con instanceof PDO)) {
            throw new RuntimeException('No se pudo conectar con la base de datos.');
        }
        $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $st = $con->prepare('
            SELECT cc.id_certificacion, cc.id_curso, cc.nombre, cc.apellido, cc.email, cc.telefono,
                   cc.precio_total, cc.moneda, cc.id_estado, c.nombre_curso
              FROM checkout_certificaciones cc
         LEFT JOIN cursos c ON c.id_curso = cc.id_curso
             WHERE cc.id_certificacion = :id
             LIMIT 1
        ');
        $st->execute([':id' => $certificacionId]);
        $row = $st->fetch();
        if (!$row) {
            throw new RuntimeException('No encontramos la solicitud de certificación.');
        }
        $data = [
            'id_certificacion' => (int) $row['id_certificacion'],
            'id_curso' => (int) $row['id_curso'],
            'curso_nombre' => (string) ($row['nombre_certificacion'] ?? $row['nombre_curso'] ?? ''),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'apellido' => (string) ($row['apellido'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'telefono' => (string) ($row['telefono'] ?? ''),
            'precio' => isset($row['precio_total']) ? (float) $row['precio_total'] : null,
            'moneda' => (string) ($row['moneda'] ?? 'ARS'),
            'estado' => isset($row['id_estado']) ? (int) $row['id_estado'] : null,
        ];
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

function cert_estado_label(?int $estado): string
{
    switch ($estado) {
        case 2:
            return 'Documentación aprobada';
        case 3:
            return 'Pago registrado';
        case 4:
            return 'Solicitud rechazada';
        default:
            return 'En revisión';
    }
}

$viewData = is_array($data) ? $data : [];
if (!$viewData && !$error) {
    $error = 'No encontramos la solicitud de certificación para mostrar.';
}

$nombreCurso = $viewData['curso_nombre'] ?? '';
$nombreSolicitante = trim(($viewData['nombre'] ?? '') . ' ' . ($viewData['apellido'] ?? ''));
$estadoActual = cert_estado_label($viewData['estado'] ?? null);
$precioCert = $viewData['precio'] ?? null;
$monedaCert = $viewData['moneda'] ?? 'ARS';
$checkoutLink = null;
if (!empty($viewData['id_curso'])) {
    $checkoutLink = sprintf('checkout.php?id_certificacion=%d&tipo=certificacion', (int) $viewData['id_curso']);
}
$backLink = '../index.php#certificaciones';

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
                        <h1>¡Gracias por enviar tu solicitud!</h1>
                        <p>Recibimos tu documentación y comenzaremos la revisión.</p>
                    </div>
                    <div class="checkout-content">
                        <?php if ($error): ?>
                            <div class="alert alert-danger checkout-alert" role="alert">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fas fa-triangle-exclamation mt-1"></i>
                                    <div>
                                        <strong>No pudimos recuperar tu solicitud.</strong>
                                        <div class="small mt-1"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div class="small mt-1">Si necesitás ayuda, escribinos a <a href="mailto:<?php echo htmlspecialchars(checkout_mail_config()['admin_email'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(checkout_mail_config()['admin_email'], ENT_QUOTES, 'UTF-8'); ?></a>.</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success checkout-alert" role="alert">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fas fa-circle-check mt-1"></i>
                                    <div>
                                        <strong>Tu documentación está en revisión.</strong>
                                        <div class="small mt-1">Nuestro equipo verificará el formulario y te notificará por correo apenas tengamos novedades.</div>
                                    </div>
                                </div>
                            </div>

                            <section class="mb-4">
                                <h2 class="h5 fw-bold mb-3">Resumen de tu solicitud</h2>
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <span class="text-muted d-block small">Certificación</span>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($nombreCurso !== '' ? $nombreCurso : 'Certificación solicitada', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <span class="text-muted d-block small">Solicitante</span>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($nombreSolicitante !== '' ? $nombreSolicitante : 'Datos registrados en tu perfil', ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                        <div class="mb-2">
                                            <span class="text-muted d-block small">Contacto</span>
                                            <span class="fw-semibold"><?php echo htmlspecialchars($viewData['email'] ?? 'Te escribiremos al correo registrado', ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (!empty($viewData['telefono'])): ?>
                                                <div class="small text-muted">Teléfono: <?php echo htmlspecialchars($viewData['telefono'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mb-0">
                                            <span class="text-muted d-block small">Estado actual</span>
                                            <span class="badge bg-primary-subtle text-primary-emphasis px-3 py-2"><?php echo htmlspecialchars($estadoActual, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if ($precioCert !== null): ?>
                                                <div class="small text-muted mt-2">Inversión estimada: <?php echo htmlspecialchars(number_format((float) $precioCert, 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($monedaCert, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="mb-4">
                                <h2 class="h5 fw-bold mb-3">¿Qué sucede ahora?</h2>
                                <ul class="list-unstyled timeline-list">
                                    <li class="d-flex mb-3">
                                        <i class="fas fa-file-circle-check text-primary me-3 mt-1"></i>
                                        <div>
                                            <strong>Revisión de tu formulario</strong>
                                            <p class="mb-0 small text-muted">Verificaremos que la documentación esté completa y cumpla con los requisitos.</p>
                                        </div>
                                    </li>
                                    <li class="d-flex mb-3">
                                        <i class="fas fa-envelope-open-text text-primary me-3 mt-1"></i>
                                        <div>
                                            <strong>Notificación por correo</strong>
                                            <p class="mb-0 small text-muted">Te avisaremos si necesitamos ajustes o cuando la certificación quede aprobada para avanzar al pago.</p>
                                        </div>
                                    </li>
                                    <li class="d-flex">
                                        <i class="fas fa-credit-card text-primary me-3 mt-1"></i>
                                        <div>
                                            <strong>Habilitación del pago</strong>
                                            <p class="mb-0 small text-muted">Una vez aprobada la documentación, podrás completar el último paso desde el checkout para finalizar tu certificación.</p>
                                        </div>
                                    </li>
                                </ul>
                            </section>

                            <div class="d-flex flex-column flex-md-row gap-2">
                                <?php if ($checkoutLink): ?>
                                    <a class="btn btn-gradient btn-rounded d-inline-flex align-items-center justify-content-center" href="<?php echo htmlspecialchars($checkoutLink, ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-magnifying-glass me-2"></i>
                                        Ver estado de mi solicitud
                                    </a>
                                <?php endif; ?>
                                <a class="btn btn-outline-light btn-rounded d-inline-flex align-items-center justify-content-center" href="<?php echo htmlspecialchars($backLink, ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Volver a las certificaciones
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

</body>
</html>
