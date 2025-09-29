<?php
session_start();
require_once '../sbd.php';

if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['usuario'])) {
    $_SESSION['login_mensaje'] = 'Debés iniciar sesión para completar tu inscripción.';
    $_SESSION['login_tipo'] = 'warning';
    header('Location: ../login.php');
    exit;
}

$page_title = "Checkout | Instituto de Formación";
$page_description = "Completá tu inscripción en tres pasos.";

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
if ($id_curso <= 0 && isset($_GET['id_capacitacion'])) {
    $id_curso = (int)$_GET['id_capacitacion'];
}
if ($id_curso <= 0 && isset($_GET['id_certificacion'])) {
    $id_curso = (int)$_GET['id_certificacion'];
}

$tipo_checkout = isset($_GET['tipo']) ? strtolower(trim((string)$_GET['tipo'])) : '';
if ($tipo_checkout === '' && isset($_GET['id_capacitacion'])) {
    $tipo_checkout = 'capacitacion';
} elseif ($tipo_checkout === '' && isset($_GET['id_certificacion'])) {
    $tipo_checkout = 'certificacion';
}
if (!in_array($tipo_checkout, ['curso', 'capacitacion', 'certificacion'], true)) {
    $tipo_checkout = 'curso';
}

$back_link_anchor = '#cursos';
$back_link_text = 'Volver al listado de cursos';
if ($tipo_checkout === 'capacitacion') {
    $back_link_anchor = '#servicios-capacitacion';
    $back_link_text = 'Volver al listado de capacitaciones';
} elseif ($tipo_checkout === 'certificacion') {
    $back_link_text = 'Volver al listado de certificaciones';
}

$st = $con->prepare("SELECT * FROM cursos WHERE id_curso = :id");
$st->execute([':id' => $id_curso]);
$curso = $st->fetch(PDO::FETCH_ASSOC);

$precio_vigente = null;
if ($curso) {
    $pv = $con->prepare("
        SELECT precio, moneda, vigente_desde
        FROM curso_precio_hist
        WHERE id_curso = :c
          AND vigente_desde <= NOW()
          AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
        ORDER BY vigente_desde DESC
        LIMIT 1
    ");
    $pv->execute([':c' => $id_curso]);
    $precio_vigente = $pv->fetch(PDO::FETCH_ASSOC);
}

$user_id = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);
$user_data = null;
if ($user_id > 0) {
    try {
        $usr = $con->prepare("SELECT nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais FROM usuarios WHERE id_usuario = :id LIMIT 1");
        $usr->execute([':id' => $user_id]);
        $user_data = $usr->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $exception) {
        $user_data = null;
    }
}

$user_defaults = [
    'nombre' => (string)($user_data['nombre'] ?? ''),
    'apellido' => (string)($user_data['apellido'] ?? ''),
    'email' => (string)($user_data['email'] ?? ($_SESSION['email'] ?? '')),
    'telefono' => (string)($user_data['telefono'] ?? ''),
    'dni' => (string)($user_data['dni'] ?? ''),
    'direccion' => (string)($user_data['direccion'] ?? ''),
    'ciudad' => (string)($user_data['ciudad'] ?? ''),
    'provincia' => (string)($user_data['provincia'] ?? ''),
    'pais' => (string)($user_data['pais'] ?? 'Argentina'),
];
$user_defaults = array_map(static fn($v) => trim((string)$v), $user_defaults);


$is_certification = ($tipo_checkout === 'certificacion');
$certificado_actual = null;
$certificado_estado = 'sin_envio';
if ($is_certification && $user_id > 0 && $curso) {
    try {
        $certStmt = $con->prepare("SELECT id_certificado, estado, pdf_nombre, pdf_path, pdf_mime, pdf_tamano, pago_estado, pago_metodo, pago_monto, pago_moneda, pago_referencia, pago_comprobante_path, pago_comprobante_nombre, pago_comprobante_mime, pago_comprobante_tamano, creado_en, actualizado_en FROM certificados WHERE id_usuario = :usuario AND id_curso = :curso ORDER BY id_certificado DESC LIMIT 1");
        $certStmt->execute([':usuario' => $user_id, ':curso' => $id_curso]);
        $certificado_actual = $certStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($certificado_actual && !empty($certificado_actual['estado'])) {
            $certificado_estado = (string)$certificado_actual['estado'];
        } else {
            $certificado_estado = 'pendiente_revision';
        }
    } catch (Throwable $exception) {
        $certificado_actual = null;
        $certificado_estado = 'sin_envio';
    }
}
if (!$certificado_actual) {
    $certificado_estado = 'sin_envio';
}

$certificado_plantilla = '../assets/docs/plantilla-certificacion.pdf';

$certificado_status_map = [
    'sin_envio' => ['label' => 'Sin documentación', 'class' => 'bg-secondary'],
    'pendiente_revision' => ['label' => 'Pendiente de revisión', 'class' => 'bg-warning text-dark'],
    'rechazado' => ['label' => 'Requiere correcciones', 'class' => 'bg-danger'],
    'aprobado' => ['label' => 'Aprobado', 'class' => 'bg-success'],
    'pago_pendiente_confirmacion' => ['label' => 'Pago en revisión', 'class' => 'bg-info text-dark'],
    'pagado' => ['label' => 'Completado', 'class' => 'bg-primary'],
];

$certificado_mensajes = [
    'sin_envio' => 'Descargá el formulario, completalo y subilo para iniciar la revisión.',
    'pendiente_revision' => 'Tu formulario fue enviado y nuestro equipo lo está revisando. Te avisaremos por email cuando se apruebe.',
    'rechazado' => 'El formulario necesita ajustes. Revisá los comentarios del equipo y subí una nueva versión en PDF.',
    'aprobado' => 'El formulario fue aprobado. Ya podés avanzar con el pago para finalizar la certificación.',
    'pago_pendiente_confirmacion' => 'Registramos tu pago y está en proceso de verificación.',
    'pagado' => 'Tu certificación fue abonada. Nos contactaremos para los pasos finales.',
];

$certificado_estado_info = $certificado_status_map[$certificado_estado] ?? ['label' => ucfirst(str_replace('_', ' ', $certificado_estado)), 'class' => 'bg-secondary'];
$certificado_mensaje_actual = $certificado_mensajes[$certificado_estado] ?? '';

$certificado_puede_pagar = !$is_certification || in_array($certificado_estado, ['aprobado', 'pago_pendiente_confirmacion', 'pagado'], true);
$certificado_pago_registrado = $is_certification && in_array($certificado_estado, ['pago_pendiente_confirmacion', 'pagado'], true);

$certificado_pdf_url = null;
if ($certificado_actual && !empty($certificado_actual['pdf_path'])) {
    $rel = ltrim((string)$certificado_actual['pdf_path'], '/');
    $certificado_pdf_url = '../' . $rel;
}
$certificado_pago_metodo = $certificado_actual['pago_metodo'] ?? null;
$certificado_pago_estado = $certificado_actual['pago_estado'] ?? null;
$certificado_pago_monto = $certificado_actual['pago_monto'] ?? null;
$certificado_pago_moneda = $certificado_actual['pago_moneda'] ?? null;
$certificado_pago_referencia = $certificado_actual['pago_referencia'] ?? null;
$certificado_pago_comprobante = $certificado_actual['pago_comprobante_path'] ?? null;
$certificado_alert_class = 'alert alert-info';
if (!$certificado_puede_pagar) {
    if ($certificado_estado === 'rechazado') {
        $certificado_alert_class = 'alert alert-danger';
    } elseif ($certificado_estado === 'pendiente_revision') {
        $certificado_alert_class = 'alert alert-warning';
    } elseif ($certificado_estado === 'sin_envio') {
        $certificado_alert_class = 'alert alert-secondary';
    }
} elseif ($certificado_pago_registrado) {
    $certificado_alert_class = 'alert alert-success';
}
$certificado_pago_bloqueado = $is_certification && (!$certificado_puede_pagar || $certificado_pago_registrado);
$certificado_js_data = [
    'id' => $certificado_actual ? (int)$certificado_actual['id_certificado'] : 0,
    'estado' => $certificado_estado,
    'pdfNombre' => $certificado_actual['pdf_nombre'] ?? null,
    'pdfUrl' => $certificado_pdf_url,
    'mensaje' => $certificado_mensaje_actual,
    'puedePagar' => $certificado_puede_pagar,
    'pagoRegistrado' => $certificado_pago_registrado,
    'alertClass' => $certificado_alert_class,
    'pagoEstado' => $certificado_pago_estado,
    'pagoMetodo' => $certificado_pago_metodo,
    'pagoMonto' => $certificado_pago_monto,
    'pagoMoneda' => $certificado_pago_moneda,
    'pagoBloqueado' => $certificado_pago_bloqueado,
    'plantilla' => $certificado_plantilla,
    'comprobanteUrl' => $certificado_pago_comprobante ? '../' . ltrim((string)$certificado_pago_comprobante, '/') : null,
];

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$flash_success = $_SESSION['checkout_success'] ?? null;
$flash_error   = $_SESSION['checkout_error'] ?? null;
unset($_SESSION['checkout_success'], $_SESSION['checkout_error']);
?>
<!DOCTYPE html>
<html lang="es">
<?php
$asset_base_path = '../';
$base_path = '../';
$page_styles = '<link rel="stylesheet" href="../checkout/css/style.css">';
include '../head.php';
?>
<body class="checkout-body">
    <?php include '../nav.php'; ?>

    <main class="checkout-main">
        <div class="container">
            <div class="mb-4">
                <a class="back-link" href="<?php echo htmlspecialchars($base_path . 'index.php' . $back_link_anchor, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo htmlspecialchars($back_link_text, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="checkout-card">
                        <div class="checkout-header">
                            <h1>Finalizá tu inscripción</h1>
                            <p>Seguí los pasos para reservar tu lugar en la capacitación elegida.</p>
                            <?php if ($curso): ?>
                                <div class="checkout-course-name">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?php echo h($curso['nombre_curso']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$curso): ?>
                            <div class="checkout-content">
                                <div class="alert alert-danger checkout-alert mb-0" role="alert">
                                    No pudimos encontrar la capacitación seleccionada. Volvé al listado e intentá nuevamente.
                                </div>
                            </div>
                        <?php else: ?>
                            <?php
                            $step2Label = $is_certification ? 'Formulario' : 'Datos personales';
                            $step2Helper = $is_certification ? 'Descargá y cargá el PDF' : 'Completá tu información';
                            $step3Helper = $is_certification ? 'Finalizá la certificación' : 'Elegí el método';
                            ?>
                            <div class="checkout-stepper">
                                <div class="checkout-step is-active" data-step="1">
                                    <div class="step-index">1</div>
                                    <div class="step-label">
                                        Resumen
                                        <span class="step-helper">Detalles del curso</span>
                                    </div>
                                </div>
                                <div class="checkout-step" data-step="2">
                                    <div class="step-index">2</div>
                                    <div class="step-label">
                                        <?php echo htmlspecialchars($step2Label, ENT_QUOTES, 'UTF-8'); ?>
                                        <span class="step-helper"><?php echo htmlspecialchars($step2Helper, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                                <div class="checkout-step" data-step="3">
                                    <div class="step-index">3</div>
                                    <div class="step-label">
                                        Pago
                                        <span class="step-helper"><?php echo htmlspecialchars($step3Helper, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="checkout-content">
                                <?php if ($flash_success): ?>
                                    <div class="alert alert-success checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-circle-check mt-1"></i>
                                            <div>
                                                <strong>¡Inscripción enviada!</strong>
                                                <?php if (!empty($flash_success['orden'])): ?>
                                                    <div>Número de orden: #<?php echo str_pad((string)(int)$flash_success['orden'], 6, '0', STR_PAD_LEFT); ?>.</div>
                                                <?php endif; ?>
                                                <div class="small mt-1">Te contactaremos por correo para completar el proceso.</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($flash_error): ?>
                                    <div class="alert alert-danger checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-triangle-exclamation mt-1"></i>
                                            <div>
                                                <strong>No pudimos procesar tu inscripción.</strong>
                                                <div class="small mt-1"><?php echo h($flash_error); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <form id="checkoutForm" action="../admin/procesarsbd.php" method="POST" enctype="multipart/form-data" novalidate>
                                    <input type="hidden" name="__accion" id="__accion" value="">
                                    <input type="hidden" name="crear_orden" value="1">
                                    <input type="hidden" name="id_curso" value="<?php echo (int)$id_curso; ?>">
                                    <input type="hidden" name="precio_checkout" value="<?php echo $precio_vigente ? (float)$precio_vigente['precio'] : 0; ?>">
                                    <input type="hidden" name="tipo_checkout" value="<?php echo htmlspecialchars($tipo_checkout, ENT_QUOTES, 'UTF-8'); ?>">

                                    <div class="step-panel active" data-step="1">
                                        <div class="row g-4 align-items-stretch">
                                            <div class="col-lg-7">
                                                <div class="summary-card h-100">
                                                    <h5>Resumen del curso</h5>
                                                    <div class="summary-item">
                                                        <strong>Nombre</strong>
                                                        <span><?php echo h($curso['nombre_curso']); ?></span>
                                                    </div>
                                                    <div class="summary-item">
                                                        <strong>Duración</strong>
                                                        <span><?php echo h($curso['duracion'] ?? 'A definir'); ?></span>
                                                    </div>
                                                    <div class="summary-item">
                                                        <strong>Nivel</strong>
                                                        <span><?php echo h($curso['complejidad'] ?? 'Intermedio'); ?></span>
                                                    </div>
                                                    <div class="summary-description mt-3">
                                                        <?php echo nl2br(h($curso['descripcion_curso'] ?? '')); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-5">
                                                <div class="summary-card h-100 d-flex flex-column justify-content-between">
                                                    <h5>Inversión</h5>
                                                    <div class="price-highlight">
                                                        <?php if ($precio_vigente): ?>
                                                            <div class="price-value">
                                                                <?php echo strtoupper($precio_vigente['moneda'] ?? 'ARS'); ?> <?php echo number_format((float)$precio_vigente['precio'], 2, ',', '.'); ?>
                                                            </div>
                                                            <span class="price-note">Vigente desde <?php echo date('d/m/Y H:i', strtotime($precio_vigente['vigente_desde'])); ?></span>
                                                        <?php else: ?>
                                                            <div class="text-muted">Precio a confirmar por el equipo comercial.</div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="small text-muted mt-3">
                                                        El equipo se pondrá en contacto para coordinar disponibilidad, medios de pago y comenzar tu proceso.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="nav-actions">
                                            <span></span>
                                            <button type="button" class="btn btn-gradient btn-rounded" data-next="2">
                                                Continuar al paso 2
                                                <i class="fas fa-arrow-right ms-2"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="step-panel" data-step="2">
                                        <?php if ($is_certification): ?>
                                            <input type="hidden" id="nombre" name="nombre_insc" value="<?php echo htmlspecialchars($user_defaults['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" id="apellido" name="apellido_insc" value="<?php echo htmlspecialchars($user_defaults['apellido'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" id="email" name="email_insc" value="<?php echo htmlspecialchars($user_defaults['email'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" id="telefono" name="tel_insc" value="<?php echo htmlspecialchars($user_defaults['telefono'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" id="dni" name="dni_insc" value="<?php echo htmlspecialchars($user_defaults['dni'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" id="direccion" name="dir_insc" value="<?php echo htmlspecialchars($user_defaults['direccion'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" id="ciudad" name="ciu_insc" value="<?php echo htmlspecialchars($user_defaults['ciudad'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" id="provincia" name="prov_insc" value="<?php echo htmlspecialchars($user_defaults['provincia'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" id="pais" name="pais_insc" value="<?php echo htmlspecialchars($user_defaults['pais'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="id_certificado" id="id_certificado" value="<?php echo $certificado_actual ? (int)$certificado_actual['id_certificado'] : 0; ?>">
                                            <input type="hidden" name="certificado_estado" id="certificado_estado" value="<?php echo htmlspecialchars($certificado_estado, ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="document-step">
                                                <div class="summary-card mb-4">
                                                    <h5 class="mb-2">Formulario de certificación</h5>
                                                    <p class="small text-muted mb-3">Descargá el PDF, completalo con tus datos y subilo firmado para que nuestro equipo lo revise.</p>
                                                    <a class="btn btn-outline-light btn-rounded" id="certificadoDescargarBtn" href="<?php echo htmlspecialchars($certificado_plantilla, ENT_QUOTES, 'UTF-8'); ?>" download>
                                                        <i class="fas fa-file-download me-2"></i>Descargar formulario
                                                    </a>
                                                </div>
                                                <div class="summary-card mb-4">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0">Estado de tu solicitud</h6>
                                                        <span class="badge <?php echo htmlspecialchars($certificado_estado_info['class'], ENT_QUOTES, 'UTF-8'); ?>" id="certificadoEstadoBadge"><?php echo htmlspecialchars($certificado_estado_info['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                    <p class="small text-muted mt-2" id="certificadoEstadoText"><?php echo htmlspecialchars($certificado_mensaje_actual, ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <div class="small mt-2" id="certificadoPdfLinkWrapper" <?php echo $certificado_pdf_url ? '' : 'style="display:none;"'; ?>>
                                                        <a href="<?php echo htmlspecialchars((string)$certificado_pdf_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" id="certificadoPdfLink">
                                                            Ver último PDF enviado
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="summary-card mb-4">
                                                    <h6 class="mb-3">Subí el formulario completo</h6>
                                                    <div class="mb-3">
                                                        <label for="certificadoPdf" class="form-label required-field">Archivo PDF</label>
                                                        <input type="file" class="form-control" id="certificadoPdf" accept="application/pdf">
                                                        <div class="upload-label">Formato permitido: PDF. Tamaño máximo 10 MB.</div>
                                                    </div>
                                                    <div class="d-flex flex-column flex-sm-row gap-2">
                                                        <button type="button" class="btn btn-gradient btn-rounded" id="btnEnviarPdf">
                                                            <i class="fas fa-upload me-2"></i>Enviar para revisión
                                                        </button>
                                                        <button type="button" class="btn btn-outline-light btn-rounded" id="btnReintentarPdf" style="display: none;" data-force-show="0">
                                                            <i class="fas fa-rotate-right me-2"></i>Intentar nuevamente
                                                        </button>
                                                    </div>
                                                    <div class="mt-3 small text-muted" id="certificadoArchivoInfo">
                                                        <?php if ($certificado_actual && !empty($certificado_actual['pdf_nombre'])): ?>
                                                            Último archivo enviado: <strong><?php echo htmlspecialchars((string)$certificado_actual['pdf_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="summary-card">
                                                    <h6 class="mb-2">Tus datos registrados</h6>
                                                    <p class="small text-muted mb-3">Estos datos se tomarán de tu cuenta. Podés actualizarlos en el <a class="link-light" href="<?php echo $base_path; ?>configuracion.php" target="_blank" rel="noopener">panel de configuración</a>.</p>
                                                    <ul class="list-unstyled mb-0 small" id="certificadoDatosPersonales">
                                                        <li><strong>Nombre:</strong> <?php echo htmlspecialchars(trim($user_defaults['nombre'] . ' ' . $user_defaults['apellido']), ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <li><strong>Email:</strong> <?php echo htmlspecialchars($user_defaults['email'], ENT_QUOTES, 'UTF-8'); ?></li>
                                                        <?php if ($user_defaults['telefono'] !== ''): ?><li><strong>Teléfono:</strong> <?php echo htmlspecialchars($user_defaults['telefono'], ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
                                                        <?php if ($user_defaults['dni'] !== ''): ?><li><strong>DNI:</strong> <?php echo htmlspecialchars($user_defaults['dni'], ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
                                                        <?php if ($user_defaults['direccion'] !== ''): ?><li><strong>Dirección:</strong> <?php echo htmlspecialchars($user_defaults['direccion'], ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
                                                        <?php if ($user_defaults['ciudad'] !== ''): ?><li><strong>Ciudad:</strong> <?php echo htmlspecialchars($user_defaults['ciudad'], ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
                                                        <?php if ($user_defaults['provincia'] !== ''): ?><li><strong>Provincia:</strong> <?php echo htmlspecialchars($user_defaults['provincia'], ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
                                                        <li><strong>País:</strong> <?php echo htmlspecialchars($user_defaults['pais'], ENT_QUOTES, 'UTF-8'); ?></li>
                                                    </ul>
                                                </div>
                                            </div>
                                            <div class="nav-actions">
                                                <button type="button" class="btn btn-outline-light btn-rounded" data-prev="1">
                                                    <i class="fas fa-arrow-left me-2"></i>
                                                    Volver
                                                </button>
                                                <button type="button" class="btn btn-gradient btn-rounded" data-next="3">
                                                    Ir al paso 3
                                                    <i class="fas fa-arrow-right ms-2"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="nombre" class="form-label required-field">Nombre</label>
                                                    <input type="text" class="form-control" id="nombre" name="nombre_insc" placeholder="Nombre" autocomplete="given-name">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="apellido" class="form-label required-field">Apellido</label>
                                                    <input type="text" class="form-control" id="apellido" name="apellido_insc" placeholder="Apellido" autocomplete="family-name">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="email" class="form-label required-field">Email</label>
                                                    <input type="email" class="form-control" id="email" name="email_insc" placeholder="correo@dominio.com" autocomplete="email">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="telefono" class="form-label required-field">Teléfono</label>
                                                    <input type="text" class="form-control" id="telefono" name="tel_insc" placeholder="+54 11 5555-5555" autocomplete="tel">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="dni" class="form-label">DNI</label>
                                                    <input type="text" class="form-control" id="dni" name="dni_insc" placeholder="Documento">
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="direccion" class="form-label">Dirección</label>
                                                    <input type="text" class="form-control" id="direccion" name="dir_insc" placeholder="Calle y número" autocomplete="address-line1">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="ciudad" class="form-label">Ciudad</label>
                                                    <input type="text" class="form-control" id="ciudad" name="ciu_insc" autocomplete="address-level2">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="provincia" class="form-label">Provincia</label>
                                                    <input type="text" class="form-control" id="provincia" name="prov_insc" autocomplete="address-level1">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="pais" class="form-label">País</label>
                                                    <input type="text" class="form-control" id="pais" name="pais_insc" value="Argentina" autocomplete="country-name">
                                                </div>
                                            </div>
                                            <div class="terms-check mt-4">
                                                <input type="checkbox" class="form-check-input mt-1" id="acepta" name="acepta_tyc" value="1">
                                                <label class="form-check-label" for="acepta">
                                                    Confirmo que los datos ingresados son correctos y acepto los <a href="#" target="_blank" rel="noopener">Términos y Condiciones</a>.
                                                </label>
                                            </div>
                                            <div class="nav-actions">
                                                <button type="button" class="btn btn-outline-light btn-rounded" data-prev="1">
                                                    <i class="fas fa-arrow-left me-2"></i>
                                                    Volver
                                                </button>
                                                <button type="button" class="btn btn-gradient btn-rounded" data-next="3">
                                                    Continuar al paso 3
                                                    <i class="fas fa-arrow-right ms-2"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="step-panel" data-step="3">
                                        <?php if ($is_certification): ?>
                                            <div class="<?php echo htmlspecialchars($certificado_alert_class, ENT_QUOTES, 'UTF-8'); ?> certification-alert" id="certificadoPagoEstado">
                                                <?php echo htmlspecialchars($certificado_mensaje_actual, ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if ($certificado_pago_registrado && $certificado_pago_metodo): ?>
                                                    <div class="small mt-2">
                                                        Método: <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$certificado_pago_metodo)), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                        <?php if ($certificado_pago_monto !== null): ?>
                                                            - Monto: <strong><?php echo htmlspecialchars(strtoupper((string)($certificado_pago_moneda ?: 'ARS')), ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format((float)$certificado_pago_monto, 2, ',', '.'); ?></strong>
                                                        <?php endif; ?>
                                                        <?php if ($certificado_pago_estado): ?>
                                                            <span class="d-block">Estado: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$certificado_pago_estado)), ENT_QUOTES, 'UTF-8'); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($certificado_pago_comprobante): ?>
                                                    <div class="small mt-2">
                                                        <a href="<?php echo htmlspecialchars('../' . ltrim((string)$certificado_pago_comprobante, '/'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" id="certificadoComprobanteLink">Ver comprobante enviado</a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="payment-box">
                                            <h5>Método de pago</h5>
                                            <label class="payment-option">
                                                <input type="radio" id="metodo_transfer" name="metodo_pago" value="transferencia" <?php echo $certificado_pago_bloqueado ? 'disabled' : 'checked'; ?>>
                                                <div class="payment-info">
                                                    <strong>Transferencia bancaria</strong>
                                                    <span>Subí el comprobante de tu transferencia.</span>
                                                </div>
                                            </label>
                                            <label class="payment-option mt-3">
                                                <input type="radio" id="metodo_mp" name="metodo_pago" value="mercado_pago" <?php echo ($precio_vigente ? '' : 'disabled') . ($certificado_pago_bloqueado ? ' disabled' : ''); ?>>
                                                <div class="payment-info">
                                                    <strong>Mercado Pago</strong>
                                                    <?php if ($precio_vigente): ?>
                                                        <span>Pagá de forma segura con tarjetas, efectivo o saldo en Mercado Pago.</span>
                                                    <?php else: ?>
                                                        <span>Disponible cuando haya un precio vigente para esta capacitación.</span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>

                                            <div class="payment-details<?php echo $certificado_pago_bloqueado ? ' hidden' : ''; ?>" id="transferDetails">
                                                <div class="bank-data">
                                                    <strong>Datos bancarios</strong>
                                                    <ul class="mb-0 mt-2 ps-3">
                                                        <li>Banco: Tu Banco</li>
                                                        <li>CBU: 0000000000000000000000</li>
                                                        <li>Alias: tuempresa.cursos</li>
                                                    </ul>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-lg-8">
                                                        <label for="comprobante" class="form-label required-field">Comprobante de pago</label>
                                                        <input type="file" class="form-control" id="comprobante" name="comprobante" accept=".jpg,.jpeg,.png,.pdf">
                                                        <div class="upload-label">Formatos aceptados: JPG, PNG o PDF. Tamaño máximo 5 MB.</div>
                                                    </div>
                                                    <div class="col-lg-4">
                                                        <label for="obs_pago" class="form-label">Observaciones</label>
                                                        <input type="text" class="form-control" id="obs_pago" name="obs_pago" placeholder="Opcional">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="payment-details hidden" id="mpDetails">
                                                <div class="summary-card">
                                                    <h6 class="mb-3">Pagar con Mercado Pago</h6>
                                                    <p class="mb-2">Al confirmar, crearemos tu orden y te redirigiremos a Mercado Pago para completar el pago en un entorno seguro.</p>
                                                    <?php if ($precio_vigente): ?>
                                                        <?php $mpMontoTexto = sprintf('%s %s', strtoupper($precio_vigente['moneda'] ?? 'ARS'), number_format((float) $precio_vigente['precio'], 2, ',', '.')); ?>
                                                        <p class="mb-2 fw-semibold">Monto a abonar: <?php echo $mpMontoTexto; ?></p>
                                                    <?php endif; ?>
                                                    <ul class="mb-0 small text-muted list-unstyled">
                                                        <li class="mb-1"><i class="fas fa-lock me-2"></i>Usá tu cuenta de Mercado Pago o tus medios de pago habituales.</li>
                                                        <li><i class="fas fa-envelope me-2"></i>Te enviaremos un correo con la confirmación apenas se acredite.</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>

                                        <?php if ($is_certification): ?>
                                            <div class="terms-check mt-4">
                                                <input type="checkbox" class="form-check-input mt-1" id="acepta" name="acepta_tyc" value="1" <?php echo $certificado_pago_registrado ? 'checked disabled' : ''; ?>>
                                                <label class="form-check-label" for="acepta">
                                                    Confirmo que la documentación y la información son correctas y acepto los <a href="#" target="_blank" rel="noopener">Términos y Condiciones</a>.
                                                </label>
                                            </div>
                                        <?php endif; ?>
                                        <div class="nav-actions">
                                            <button type="button" class="btn btn-outline-light btn-rounded" data-prev="2">
                                                <i class="fas fa-arrow-left me-2"></i>
                                                Volver
                                            </button>
                                            <button type="button" class="btn btn-gradient btn-rounded" id="btnConfirmar">
                                                <span class="btn-label">Confirmar inscripción</span>
                                                <i class="fas fa-paper-plane ms-2"></i>
                                            </button>
                                        </div>
                                        <div class="checkout-footer text-center mt-4">
                                            Al confirmar, enviaremos los datos a nuestro equipo para validar tu lugar y nos comunicaremos por correo electrónico.
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            const card = document.querySelector('.checkout-card');
            const steps = Array.from(document.querySelectorAll('.checkout-step'));
            const panels = Array.from(document.querySelectorAll('.step-panel'));
            if (!steps.length || !panels.length) {
                return;
            }

            const mpAvailable = <?php echo $precio_vigente ? 'true' : 'false'; ?>;
            const mpEndpoint = '../checkout/mercadopago_init.php';
            const isCertification = <?php echo $is_certification ? 'true' : 'false'; ?>;
            let certificationData = <?php echo json_encode($certificado_js_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            const certificationStatusMap = <?php echo json_encode($certificado_status_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
            let currentStep = 1;
            let mpProcessing = false;

            const goToStep = (target) => {
                currentStep = target;
                steps.forEach(step => {
                    const stepIndex = parseInt(step.dataset.step, 10);
                    step.classList.toggle('is-active', stepIndex === target);
                    step.classList.toggle('is-complete', stepIndex < target);
                });
                panels.forEach(panel => {
                    const panelIndex = parseInt(panel.dataset.step, 10);
                    panel.classList.toggle('active', panelIndex === target);
                });
                if (card) {
                    window.scrollTo({ top: card.offsetTop - 80, behavior: 'smooth' });
                }
            };

            const showAlert = (icon, title, message) => {
                Swal.fire({
                    icon,
                    title,
                    html: message,
                    confirmButtonText: 'Entendido',
                    customClass: {
                        confirmButton: 'btn btn-gradient btn-rounded'
                    },
                    buttonsStyling: false
                });
            };

            const validateStep = (step) => {
                if (step === 2) {
                    if (isCertification) {
                        if (!certificationData || !certificationData.id) {
                            goToStep(2);
                            showAlert('error', 'Formulario pendiente', 'Descargá, completá y subí el PDF para continuar con tu certificación.');
                            return false;
                        }
                        return true;
                    }
                    const required = [
                        { id: 'nombre', label: 'Nombre' },
                        { id: 'apellido', label: 'Apellido' },
                        { id: 'email', label: 'Email' },
                        { id: 'telefono', label: 'Teléfono' }
                    ];
                    const missing = required.find(field => {
                        const el = document.getElementById(field.id);
                        return !el || !el.value || el.value.trim() === '';
                    });
                    if (missing) {
                        goToStep(2);
                        showAlert('error', 'Faltan datos', `Completá el campo <strong>${missing.label}</strong> para continuar.`);
                        return false;
                    }
                    const emailField = document.getElementById('email');
                    const email = emailField ? emailField.value.trim() : '';
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(email)) {
                        goToStep(2);
                        showAlert('error', 'Email inválido', 'Ingresá un correo electrónico válido.');
                        return false;
                    }
                    const terms = document.getElementById('acepta');
                    if (terms && !terms.checked) {
                        goToStep(2);
                        showAlert('warning', 'Términos y Condiciones', 'Debés aceptar los Términos y Condiciones para continuar.');
                        return false;
                    }
                }
                if (step === 3) {
                    if (isCertification) {
                        if (!certificationData || !certificationData.puedePagar) {
                            goToStep(3);
                            showAlert('info', 'Formulario en revisión', 'Aguardá la validación del PDF para habilitar el pago.');
                            return false;
                        }
                        if (certificationData.pagoRegistrado) {
                            goToStep(3);
                            showAlert('info', 'Pago registrado', 'Ya registramos tu pago para esta certificación.');
                            return false;
                        }
                        const terms = document.getElementById('acepta');
                        if (!terms || !terms.checked) {
                            goToStep(3);
                            showAlert('warning', 'Términos y Condiciones', 'Debés aceptar los Términos y Condiciones para continuar.');
                            return false;
                        }
                    }
                    const mp = document.getElementById('metodo_mp').checked;
                    const transfer = document.getElementById('metodo_transfer').checked;
                    if (!mp && !transfer) {
                        goToStep(3);
                        showAlert('error', 'Seleccioná un método de pago', 'Elegí una forma de pago para continuar.');
                        return false;
                    }
                    if (mp && !mpAvailable) {
                        goToStep(3);
                        showAlert('warning', 'Mercado Pago no disponible', 'Este curso todavía no tiene un precio vigente para pagar online.');
                        return false;
                    }
                    if (transfer) {
                        const fileInput = document.getElementById('comprobante');
                        const file = fileInput.files[0];
                        if (!file) {
                            goToStep(3);
                            showAlert('error', 'Falta el comprobante', 'Adjuntá el comprobante de la transferencia.');
                            return false;
                        }
                        const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
                        if (!allowedTypes.includes(file.type)) {
                            goToStep(3);
                            showAlert('error', 'Archivo no permitido', 'Solo se aceptan archivos JPG, PNG o PDF.');
                            return false;
                        }
                        const maxSize = 5 * 1024 * 1024;
                        if (file.size > maxSize) {
                            goToStep(3);
                            showAlert('error', 'Archivo demasiado grande', 'El archivo debe pesar hasta 5 MB.');
                            return false;
                        }
                    }
                }
                return true;
            };

            document.querySelectorAll('[data-next]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const next = parseInt(btn.dataset.next, 10);
                    if (Number.isNaN(next)) {
                        return;
                    }
                    if (currentStep === 2 && !validateStep(2)) {
                        return;
                    }
                    goToStep(next);
                });
            });

            document.querySelectorAll('[data-prev]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const prev = parseInt(btn.dataset.prev, 10);
                    if (Number.isNaN(prev)) {
                        return;
                    }
                    goToStep(prev);
                });
            });

            const mpRadio = document.getElementById('metodo_mp');
            const transferRadio = document.getElementById('metodo_transfer');
            const transferDetails = document.getElementById('transferDetails');
            const mpDetails = document.getElementById('mpDetails');
            const form = document.getElementById('checkoutForm');
            const confirmButton = document.getElementById('btnConfirmar');
            let confirmLabel = confirmButton.querySelector('.btn-label');
            let confirmIcon = confirmButton.querySelector('i');
            const confirmDefault = {
                label: 'Confirmar inscripción',
                icon: 'fas fa-paper-plane ms-2'
            };
            const confirmDefaultMarkup = confirmButton.innerHTML;
            const certificationElements = {
                estadoBadge: document.getElementById('certificadoEstadoBadge'),
                estadoText: document.getElementById('certificadoEstadoText'),
                archivoInfo: document.getElementById('certificadoArchivoInfo'),
                pdfLinkWrapper: document.getElementById('certificadoPdfLinkWrapper'),
                pdfLink: document.getElementById('certificadoPdfLink'),
                hiddenId: document.getElementById('id_certificado'),
                hiddenEstado: document.getElementById('certificado_estado'),
                pagoAlert: document.getElementById('certificadoPagoEstado'),
                comprobanteLink: document.getElementById('certificadoComprobanteLink'),
                uploadButton: document.getElementById('btnEnviarPdf'),
                retryButton: document.getElementById('btnReintentarPdf'),
                pdfInput: document.getElementById('certificadoPdf'),
                terms: document.getElementById('acepta')
            };
            const comprobanteInput = document.getElementById('comprobante');
            const obsPagoInput = document.getElementById('obs_pago');

            const escapeHtml = (value) => {
                const div = document.createElement('div');
                div.textContent = value ?? '';
                return div.innerHTML;
            };

            const formatLabel = (value) => {
                if (!value) {
                    return '';
                }
                return value.toString().replace(/_/g, ' ').replace(/\b\w/g, (match) => match.toUpperCase());
            };

            const isPaymentBlocked = () => {
                if (!isCertification) {
                    return false;
                }
                if (!certificationData) {
                    return true;
                }
                return Boolean(certificationData.pagoBloqueado || !certificationData.puedePagar || certificationData.pagoRegistrado);
            };

            const refreshCertificationUI = () => {
                if (!isCertification) {
                    return;
                }
                const estado = certificationData && certificationData.estado ? certificationData.estado : 'sin_envio';
                const statusInfo = certificationStatusMap && certificationStatusMap[estado] ? certificationStatusMap[estado] : null;
                if (certificationElements.estadoBadge) {
                    certificationElements.estadoBadge.textContent = statusInfo && statusInfo.label ? statusInfo.label : formatLabel(estado);
                    certificationElements.estadoBadge.className = `badge ${(statusInfo && statusInfo.class) ? statusInfo.class : 'bg-secondary'}`;
                }
                if (certificationElements.estadoText) {
                    certificationElements.estadoText.textContent = certificationData && certificationData.mensaje ? certificationData.mensaje : '';
                }
                if (certificationElements.archivoInfo) {
                    if (certificationData && certificationData.pdfNombre) {
                        certificationElements.archivoInfo.innerHTML = `Último archivo enviado: <strong>${escapeHtml(certificationData.pdfNombre)}</strong>`;
                    } else {
                        certificationElements.archivoInfo.textContent = '';
                    }
                }
                if (certificationElements.pdfLinkWrapper) {
                    if (certificationData && certificationData.pdfUrl) {
                        certificationElements.pdfLinkWrapper.style.display = '';
                        if (certificationElements.pdfLink) {
                            certificationElements.pdfLink.href = certificationData.pdfUrl;
                        }
                    } else {
                        certificationElements.pdfLinkWrapper.style.display = 'none';
                    }
                }
                if (certificationElements.hiddenId) {
                    certificationElements.hiddenId.value = certificationData && certificationData.id ? certificationData.id : 0;
                }
                if (certificationElements.hiddenEstado) {
                    certificationElements.hiddenEstado.value = estado;
                }
                if (certificationElements.pagoAlert) {
                    const alertClass = certificationData && certificationData.alertClass ? certificationData.alertClass : 'alert-info';
                    certificationElements.pagoAlert.className = `${alertClass} certification-alert`;
                    certificationElements.pagoAlert.innerHTML = '';
                    if (certificationData && certificationData.mensaje) {
                        const span = document.createElement('span');
                        span.textContent = certificationData.mensaje;
                        certificationElements.pagoAlert.appendChild(span);
                    }
                    if (certificationData && certificationData.pagoRegistrado && certificationData.pagoMetodo) {
                        const wrap = document.createElement('div');
                        wrap.className = 'small mt-2';
                        let summary = `Método: ${formatLabel(certificationData.pagoMetodo)}`;
                        if (typeof certificationData.pagoMonto === 'number' && certificationData.pagoMoneda) {
                            const amount = Number(certificationData.pagoMonto).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            summary += ` - Monto: ${String(certificationData.pagoMoneda).toUpperCase()} ${amount}`;
                        }
                        wrap.textContent = summary;
                        if (certificationData.pagoEstado) {
                            const state = document.createElement('span');
                            state.className = 'd-block';
                            state.textContent = `Estado: ${formatLabel(certificationData.pagoEstado)}`;
                            wrap.appendChild(state);
                        }
                        certificationElements.pagoAlert.appendChild(wrap);
                    }
                    if (certificationData && certificationData.comprobanteUrl) {
                        const linkWrap = document.createElement('div');
                        linkWrap.className = 'small mt-2';
                        const link = document.createElement('a');
                        link.href = certificationData.comprobanteUrl;
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.textContent = 'Ver comprobante enviado';
                        linkWrap.appendChild(link);
                        certificationElements.pagoAlert.appendChild(linkWrap);
                    }
                }
                if (certificationElements.terms) {
                    const alreadyPaid = Boolean(certificationData && certificationData.pagoRegistrado);
                    certificationElements.terms.disabled = alreadyPaid;
                    if (alreadyPaid) {
                        certificationElements.terms.checked = true;
                    }
                }
                const blocked = isPaymentBlocked();
                if (transferRadio) {
                    transferRadio.disabled = blocked;
                    if (blocked) {
                        transferRadio.checked = false;
                    }
                }
                if (mpRadio) {
                    mpRadio.disabled = blocked || !mpAvailable;
                    if (mpRadio.disabled) {
                        mpRadio.checked = false;
                    }
                }
                if (comprobanteInput) {
                    comprobanteInput.disabled = blocked;
                    if (blocked) {
                        comprobanteInput.value = '';
                    }
                }
                if (obsPagoInput) {
                    obsPagoInput.disabled = blocked;
                    if (blocked) {
                        obsPagoInput.value = '';
                    }
                }
                if (transferDetails) {
                    if (blocked) {
                        transferDetails.classList.add('hidden');
                    }
                }
                if (!blocked) {
                    togglePaymentDetails();
                } else if (mpDetails) {
                    mpDetails.classList.add('hidden');
                }
                updateConfirmButton();
            };

            const setCertificationData = (data) => {
                if (!isCertification) {
                    return;
                }
                certificationData = Object.assign({}, certificationData || {}, data || {});
                refreshCertificationUI();
            };

            if (isCertification) {
                refreshCertificationUI();
            }

            const refreshConfirmElements = () => {
                confirmLabel = confirmButton.querySelector('.btn-label');
                confirmIcon = confirmButton.querySelector('i');
            };

            const updateConfirmButton = () => {
                refreshConfirmElements();
                if (!confirmLabel || !confirmIcon) {
                    return;
                }
                if (isPaymentBlocked()) {
                    confirmButton.disabled = true;
                    if (isCertification && certificationData) {
                        if (certificationData.pagoRegistrado) {
                            confirmLabel.textContent = 'Pago registrado';
                        } else if (!certificationData.puedePagar) {
                            confirmLabel.textContent = 'Esperando validación';
                        } else {
                            confirmLabel.textContent = confirmDefault.label;
                        }
                    }
                    confirmIcon.className = confirmDefault.icon;
                    return;
                }
                confirmButton.disabled = false;
                if (mpRadio.checked) {
                    confirmLabel.textContent = 'Ir a Mercado Pago';
                    confirmIcon.className = 'fas fa-credit-card ms-2';
                } else {
                    confirmLabel.textContent = confirmDefault.label;
                    confirmIcon.className = confirmDefault.icon;
                }
            };

            const togglePaymentDetails = () => {
                if (isPaymentBlocked()) {
                    transferDetails.classList.add('hidden');
                    mpDetails.classList.add('hidden');
                    updateConfirmButton();
                    return;
                }
                if (transferRadio.checked) {
                    transferDetails.classList.remove('hidden');
                    mpDetails.classList.add('hidden');
                } else if (mpRadio.checked) {
                    mpDetails.classList.remove('hidden');
                    transferDetails.classList.add('hidden');
                }
                updateConfirmButton();
            };

            mpRadio.addEventListener('change', togglePaymentDetails);
            transferRadio.addEventListener('change', togglePaymentDetails);
            togglePaymentDetails();

            const setConfirmLoading = (isLoading) => {
                if (isLoading) {
                    confirmButton.disabled = true;
                    confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Redirigiendo a Mercado Pago...';
                } else {
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = confirmDefaultMarkup;
                    updateConfirmButton();
                    if (isCertification) {
                        refreshCertificationUI();
                    }
                }
            };

            const setUploadLoading = (isLoading) => {
                if (!isCertification) {
                    return;
                }
                if (certificationElements.uploadButton) {
                    certificationElements.uploadButton.disabled = isLoading;
                    certificationElements.uploadButton.innerHTML = isLoading
                        ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Enviando...'
                        : '<i class="fas fa-upload me-2"></i>Enviar para revisión';
                }
                if (certificationElements.retryButton) {
                    certificationElements.retryButton.style.display = isLoading ? 'none' : certificationElements.retryButton.dataset.forceShow === '1' ? '' : 'none';
                }
            };

            const handleCertificationUpload = () => {
                if (!isCertification) {
                    return;
                }
                if (!certificationElements.pdfInput || !form) {
                    return;
                }
                const file = certificationElements.pdfInput.files && certificationElements.pdfInput.files[0] ? certificationElements.pdfInput.files[0] : null;
                if (!file) {
                    showAlert('error', 'Seleccioná un archivo', 'Elegí el PDF completo de tu formulario para enviarlo.');
                    return;
                }
                const lowerName = file.name ? file.name.toLowerCase() : '';
                if (file.type !== 'application/pdf' && !lowerName.endsWith('.pdf')) {
                    showAlert('error', 'Formato inválido', 'El formulario debe enviarse en formato PDF.');
                    return;
                }
                const maxSize = 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    showAlert('error', 'Archivo demasiado grande', 'El archivo no puede superar los 10 MB.');
                    return;
                }
                setUploadLoading(true);
                const formData = new FormData();
                formData.append('__accion', 'guardar_certificado');
                formData.append('id_curso', document.querySelector('input[name="id_curso"]').value);
                if (certificationData && certificationData.id) {
                    formData.append('id_certificado', certificationData.id);
                }
                formData.append('certificado_pdf', file);

                fetch('../admin/procesarsbd.php', {
                    method: 'POST',
                    body: formData,
                }).then(async (response) => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data || !data.success) {
                        const message = data && data.message ? data.message : 'No pudimos guardar el formulario.';
                        throw new Error(message);
                    }
                    certificationElements.pdfInput.value = '';
                    if (certificationElements.retryButton) {
                        certificationElements.retryButton.dataset.forceShow = '0';
                        certificationElements.retryButton.style.display = 'none';
                    }
                    setCertificationData(data.certificado || {});
                    showAlert('success', 'Formulario enviado', data.message || 'Recibimos tu PDF. Lo revisaremos a la brevedad.');
                }).catch((error) => {
                    if (certificationElements.retryButton) {
                        certificationElements.retryButton.dataset.forceShow = '1';
                        certificationElements.retryButton.style.display = '';
                    }
                    showAlert('error', 'No pudimos subir el formulario', error && error.message ? error.message : 'Intentá nuevamente en unos minutos.');
                }).finally(() => {
                    setUploadLoading(false);
                });
            };

            if (isCertification && certificationElements.uploadButton) {
                certificationElements.uploadButton.addEventListener('click', handleCertificationUpload);
            }
            if (isCertification && certificationElements.retryButton) {
                certificationElements.retryButton.addEventListener('click', handleCertificationUpload);
            }

            const iniciarMercadoPago = async () => {
                if (mpProcessing) {
                    return;
                }
                mpProcessing = true;
                setConfirmLoading(true);
                try {
                    const formData = new FormData(form);
                    formData.set('metodo_pago', 'mercado_pago');
                    formData.set('__accion', 'crear_orden');
                    const response = await fetch(mpEndpoint, {
                        method: 'POST',
                        body: formData,
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || !data || !data.success || !data.init_point) {
                        const message = data && data.message ? data.message : 'No se pudo iniciar el pago en Mercado Pago.';
                        throw new Error(message);
                    }
                    window.location.href = data.init_point;
                } catch (error) {
                    mpProcessing = false;
                    setConfirmLoading(false);
                    showAlert('error', 'No se pudo iniciar el pago', error && error.message ? error.message : 'Intentá nuevamente en unos minutos.');
                }
            };

            confirmButton.addEventListener('click', () => {
                if (!validateStep(2) || !validateStep(3)) {
                    return;
                }
                const mpSelected = mpRadio.checked;
                const title = mpSelected ? 'Ir a Mercado Pago' : 'Confirmar inscripción';
                const text = mpSelected
                    ? 'Vamos a generar tu orden y redirigirte a Mercado Pago para que completes el pago.'
                    : '¿Deseás enviar la inscripción con los datos cargados?';
                const confirmText = mpSelected ? 'Sí, continuar' : 'Sí, enviar';

                Swal.fire({
                    icon: 'question',
                    title,
                    text,
                    showCancelButton: true,
                    confirmButtonText: confirmText,
                    cancelButtonText: 'Cancelar',
                    customClass: {
                        confirmButton: 'btn btn-gradient btn-rounded me-2',
                        cancelButton: 'btn btn-outline-light btn-rounded'
                    },
                    buttonsStyling: false,
                    reverseButtons: true
                }).then(result => {
                    if (!result.isConfirmed) {
                        return;
                    }
                    if (mpSelected) {
                        iniciarMercadoPago();
                    } else {
                        document.getElementById('__accion').value = 'crear_orden';
                        form.submit();
                    }
                });
            });
        })();
    </script>
</body>
</html>