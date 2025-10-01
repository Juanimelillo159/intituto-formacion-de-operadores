<?php
session_start();
require_once '../sbd.php';

function checkout_get_session_user_id(): int
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

$currentUserId = checkout_get_session_user_id();
if ($currentUserId <= 0) {
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

$cursoNombre = null;
$cursoDescripcion = null;
if ($curso) {
    $cursoNombre = (string)($curso['nombre_certificacion'] ?? $curso['nombre_curso'] ?? '');
    $cursoDescripcion = (string)($curso['descripcion'] ?? $curso['descripcion_curso'] ?? '');
}

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

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function checkout_certificacion_estado_label(?int $estado): string
{
    return match ($estado) {
        2 => 'Aprobada',
        3 => 'Pago registrado',
        4 => 'Rechazada',
        default => 'En revisión',
    };
}

$certificacionData = null;
$certificacionEstado = null;
$certificacionId = 0;
$certificacionPuedePagar = false;
$certificacionPagado = false;
$certificacionPdfUrl = null;
$certificacionAllowSubmit = true;
$certificacionSubmitLabel = 'Enviar solicitud';

if ($tipo_checkout === 'certificacion' && $curso) {
    if ($currentUserId > 0) {
        $certStmt = $con->prepare('
            SELECT cc.*
              FROM checkout_certificaciones cc
             WHERE cc.id_curso = :curso
               AND cc.creado_por = :usuario
          ORDER BY cc.id_certificacion DESC
             LIMIT 1
        ');
        $certStmt->execute([
            ':curso' => $id_curso,
            ':usuario' => $currentUserId,
        ]);
        $certificacionData = $certStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($certificacionData) {
        $certificacionId = (int)$certificacionData['id_certificacion'];
        $certificacionEstado = (int)$certificacionData['id_estado'];
        $certificacionPuedePagar = ($certificacionEstado === 2);
        $certificacionPagado = ($certificacionEstado === 3);
        $certificacionAllowSubmit = ($certificacionEstado === 4);
        if ($certificacionEstado === 4) {
            $certificacionSubmitLabel = 'Reenviar solicitud';
        } elseif ($certificacionEstado === 1) {
            $certificacionSubmitLabel = 'Solicitud enviada';
        } elseif ($certificacionEstado === 2) {
            $certificacionSubmitLabel = 'Documentación aprobada';
        } elseif ($certificacionEstado === 3) {
            $certificacionSubmitLabel = 'Solicitud completada';
        }
        if (!empty($certificacionData['pdf_path'])) {
            $certificacionPdfUrl = '../' . ltrim((string)$certificacionData['pdf_path'], '/');
        }
    } else {
        $certificacionAllowSubmit = true;
        $certificacionSubmitLabel = 'Enviar solicitud';
    }
} else {
    $certificacionAllowSubmit = false;
}

$sessionUsuario = [];
if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
    $sessionUsuario = $_SESSION['usuario'];
}

$usuarioPerfil = $sessionUsuario;
if ($currentUserId > 0) {
    $usuarioPerfil['id_usuario'] = $currentUserId;
    $camposPerfil = ['nombre', 'apellido', 'email', 'telefono', 'dni'];
    $faltaPerfil = false;
    foreach ($camposPerfil as $campoPerfil) {
        if (!isset($usuarioPerfil[$campoPerfil]) || trim((string)$usuarioPerfil[$campoPerfil]) === '') {
            $faltaPerfil = true;
            break;
        }
    }

    if ($faltaPerfil) {
        $perfilStmt = $con->prepare('
            SELECT nombre, apellido, email, telefono, dni
              FROM usuarios
             WHERE id_usuario = :id
             LIMIT 1
        ');
        $perfilStmt->execute([':id' => $currentUserId]);
        $perfilRow = $perfilStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($perfilRow) {
            $usuarioPerfil = array_merge($usuarioPerfil, $perfilRow);
        }
    }
}

$prefillNombre = (string)($certificacionData['nombre'] ?? ($usuarioPerfil['nombre'] ?? ($sessionUsuario['nombre'] ?? '')));
$prefillApellido = (string)($certificacionData['apellido'] ?? ($usuarioPerfil['apellido'] ?? ($sessionUsuario['apellido'] ?? '')));
$prefillEmail = (string)($certificacionData['email'] ?? ($usuarioPerfil['email'] ?? ($sessionUsuario['email'] ?? '')));
$prefillTelefono = (string)($certificacionData['telefono'] ?? ($usuarioPerfil['telefono'] ?? ($sessionUsuario['telefono'] ?? '')));
$prefillDni = (string)($certificacionData['dni'] ?? ($usuarioPerfil['dni'] ?? ''));
$prefillDireccion = (string)($certificacionData['direccion'] ?? ($usuarioPerfil['direccion'] ?? ''));
$prefillCiudad = (string)($certificacionData['ciudad'] ?? ($usuarioPerfil['ciudad'] ?? ''));
$prefillProvincia = (string)($certificacionData['provincia'] ?? ($usuarioPerfil['provincia'] ?? ''));
$prefillPais = (string)($certificacionData['pais'] ?? ($usuarioPerfil['pais'] ?? 'Argentina'));
if ($tipo_checkout !== 'certificacion') {
    $prefillNombre = '';
    $prefillApellido = '';
    $prefillEmail = '';
    $prefillTelefono = '';
    $prefillDni = '';
    $prefillDireccion = '';
    $prefillCiudad = '';
    $prefillProvincia = '';
    $prefillPais = 'Argentina';
}

$certificacionFlashSuccess = $_SESSION['certificacion_success'] ?? null;
$certificacionFlashError = $_SESSION['certificacion_error'] ?? null;
unset($_SESSION['certificacion_success'], $_SESSION['certificacion_error']);

$certificacionSuccessMessage = null;
$certificacionSuccessEstadoLabel = null;
if ($certificacionFlashSuccess !== null) {
    if (is_array($certificacionFlashSuccess)) {
        $certificacionSuccessMessage = (string)($certificacionFlashSuccess['message'] ?? 'Solicitud enviada correctamente.');
        $certificacionSuccessEstadoLabel = isset($certificacionFlashSuccess['estado'])
            ? checkout_certificacion_estado_label((int)$certificacionFlashSuccess['estado'])
            : null;
    } else {
        $certificacionSuccessMessage = (string)$certificacionFlashSuccess;
    }
}

$certificacionErrorMessage = null;
if ($certificacionFlashError !== null) {
    $certificacionErrorMessage = is_array($certificacionFlashError)
        ? (string)($certificacionFlashError['message'] ?? 'No pudimos procesar la solicitud de certificación.')
        : (string)$certificacionFlashError;
}

$flash_success = $_SESSION['checkout_success'] ?? null;
$flash_error   = $_SESSION['checkout_error'] ?? null;
unset($_SESSION['checkout_success'], $_SESSION['checkout_error']);

$checkoutSubtitle = 'Seguí los pasos para reservar tu lugar en la capacitación elegida.';
$notFoundMessage = 'No pudimos encontrar la capacitación seleccionada. Volvé al listado e intentá nuevamente.';
$stepHelper = 'Detalles del curso';
$summaryTitle = 'Resumen del curso';
$priceHelper = 'El equipo se pondrá en contacto para coordinar disponibilidad, medios de pago y comenzar tu proceso.';
if ($tipo_checkout === 'curso') {
    $checkoutSubtitle = 'Seguí los pasos para confirmar tu inscripción.';
} elseif ($tipo_checkout === 'certificacion') {
    $checkoutSubtitle = 'Completá la solicitud y el pago para finalizar tu certificación.';
    $notFoundMessage = 'No pudimos encontrar la certificación seleccionada. Volvé al listado e intentá nuevamente.';
    $stepHelper = 'Detalles de la certificación';
    $summaryTitle = 'Resumen de la certificación';
    $priceHelper = 'Revisaremos la documentación y coordinaremos los pasos para avanzar con la certificación.';
}
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
                            <p><?php echo h($checkoutSubtitle); ?></p>
                            <?php if ($curso && $cursoNombre !== null && $cursoNombre !== ''): ?>
                                <div class="checkout-course-name">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?php echo h($cursoNombre); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$curso): ?>
                            <div class="checkout-content">
                                <div class="alert alert-danger checkout-alert mb-0" role="alert">
                                    <?php echo h($notFoundMessage); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="checkout-stepper">
                                <div class="checkout-step is-active" data-step="1">
                                    <div class="step-index">1</div>
                                    <div class="step-label">
                                        Resumen
                                        <span class="step-helper"><?php echo h($stepHelper); ?></span>
                                    </div>
                                </div>
                                <div class="checkout-step" data-step="2">
                                    <div class="step-index">2</div>
                                    <div class="step-label">
                                        <?php if ($tipo_checkout === 'certificacion'): ?>
                                            Documentación
                                            <span class="step-helper">Descargá y subí el PDF solicitado</span>
                                        <?php else: ?>
                                            Datos personales
                                            <span class="step-helper">Completá tu información</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="checkout-step" data-step="3">
                                    <div class="step-index">3</div>
                                    <div class="step-label">
                                        Pago
                                        <span class="step-helper">
                                            <?php if ($tipo_checkout === 'certificacion' && !$certificacionPuedePagar && !$certificacionPagado): ?>
                                                Esperá la aprobación
                                            <?php elseif ($tipo_checkout === 'certificacion' && $certificacionPagado): ?>
                                                Pago registrado
                                            <?php else: ?>
                                                Elegí el método
                                            <?php endif; ?>
                                        </span>
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
                                <?php if ($certificacionSuccessMessage): ?>
                                    <div class="alert alert-success checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-file-circle-check mt-1"></i>
                                            <div>
                                                <strong><?php echo h($certificacionSuccessMessage); ?></strong>
                                                <?php if ($certificacionSuccessEstadoLabel): ?>
                                                    <div class="small mt-1">Estado actual: <?php echo h($certificacionSuccessEstadoLabel); ?>.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($certificacionErrorMessage): ?>
                                    <div class="alert alert-danger checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-circle-xmark mt-1"></i>
                                            <div>
                                                <strong>No pudimos registrar la certificación.</strong>
                                                <div class="small mt-1"><?php echo h($certificacionErrorMessage); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <form id="checkoutForm" action="../admin/procesarsbd.php" method="POST" enctype="multipart/form-data" novalidate data-certificacion-has-pdf="<?php echo $certificacionPdfUrl ? '1' : '0'; ?>">
                                    <input type="hidden" name="__accion" id="__accion" value="">
                                    <input type="hidden" name="crear_orden" value="1">
                                    <input type="hidden" name="id_curso" value="<?php echo (int)$id_curso; ?>">
                                    <input type="hidden" name="precio_checkout" value="<?php echo $precio_vigente ? (float)$precio_vigente['precio'] : 0; ?>">
                                    <input type="hidden" name="tipo_checkout" value="<?php echo htmlspecialchars($tipo_checkout, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_certificacion" value="<?php echo (int)$certificacionId; ?>">
                                    <input type="hidden" name="certificacion_estado_actual" value="<?php echo $certificacionEstado !== null ? (int)$certificacionEstado : 0; ?>">

                                    <div class="step-panel active" data-step="1">
                                        <div class="row g-4 align-items-stretch">
                                            <div class="col-lg-7">
                                                <div class="summary-card h-100">
                                                    <h5><?php echo h($summaryTitle); ?></h5>
                                                    <div class="summary-item">
                                                        <strong>Nombre</strong>
                                                        <span><?php echo h($cursoNombre ?? ($curso['nombre_curso'] ?? '')); ?></span>
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
                                                        <?php echo nl2br(h($cursoDescripcion ?? ($curso['descripcion_curso'] ?? ''))); ?>
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
                                                        <?php echo h($priceHelper); ?>
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
                                        <?php if ($tipo_checkout === 'certificacion'): ?>
                                            <?php
                                            $certNombreValue = $certificacionData['nombre'] ?? $prefillNombre;
                                            $certApellidoValue = $certificacionData['apellido'] ?? $prefillApellido;
                                            $certEmailValue = $certificacionData['email'] ?? $prefillEmail;
                                            $certTelefonoValue = $certificacionData['telefono'] ?? $prefillTelefono;
                                            $certDniValue = $certificacionData['dni'] ?? $prefillDni;
                                            $certDireccionValue = $certificacionData['direccion'] ?? $prefillDireccion;
                                            $certCiudadValue = $certificacionData['ciudad'] ?? $prefillCiudad;
                                            $certProvinciaValue = $certificacionData['provincia'] ?? $prefillProvincia;
                                            $certPaisValue = $certificacionData['pais'] ?? $prefillPais;
                                            $certInputsReadonly = $certificacionAllowSubmit ? '' : 'readonly';
                                            $certDatosHelper = $certificacionAllowSubmit
                                                ? 'Actualizá tus datos si es necesario antes de enviar la solicitud.'
                                                : 'Estos son los datos que utilizaste en tu solicitud.';
                                            ?>
                                            <?php if ($certificacionEstado !== null): ?>
                                                <div class="alert alert-info checkout-alert mb-4" role="alert">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <i class="fas fa-info-circle mt-1"></i>
                                                        <div>
                                                            <strong>Estado de tu solicitud:</strong>
                                                            <div class="small mt-1"><?php echo h(checkout_certificacion_estado_label($certificacionEstado)); ?>.</div>
                                                            <?php if (!empty($certificacionData['observaciones'])): ?>
                                                                <div class="small text-muted mt-1"><?php echo nl2br(h($certificacionData['observaciones'])); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <div class="row g-4 align-items-stretch">
                                                <div class="col-lg-7">
                                                    <div class="summary-card h-100">
                                                        <h5>Documentación requerida</h5>
                                                        <p class="mb-3">Descargá el formulario, completalo y volvé a subirlo en formato PDF para que nuestro equipo pueda revisarlo.</p>
                                                        <a class="btn btn-outline-light btn-sm mb-3" href="../assets/pdf/solicitud_certificacion.pdf" target="_blank" rel="noopener">
                                                            <i class="fas fa-file-download me-2"></i>Descargar formulario
                                                        </a>
                                                        <?php if ($certificacionPdfUrl): ?>
                                                            <div class="mb-3">
                                                                <span class="badge bg-success"><i class="fas fa-file-pdf me-2"></i>PDF cargado</span>
                                                                <a class="ms-2" href="<?php echo h($certificacionPdfUrl); ?>" target="_blank" rel="noopener">Ver archivo enviado</a>
                                                            </div>
                                                        <?php endif; ?>
                                                        <label for="cert_pdf" class="form-label required-field">Subir formulario firmado (PDF)</label>
                                                        <input type="file" class="form-control" id="cert_pdf" name="cert_pdf" accept="application/pdf" <?php echo $certificacionAllowSubmit ? '' : 'disabled'; ?>>
                                                        <div class="upload-label">Formato requerido: PDF. Tamaño máximo 10 MB.</div>
                                                    </div>
                                                </div>
                                                <div class="col-lg-5">
                                                    <div class="summary-card h-100">
                                                        <h5>Datos del solicitante</h5>
                                                        <p class="mb-3 small text-muted"><?php echo h($certDatosHelper); ?></p>
                                                        <div class="row g-3">
                                                            <div class="col-12">
                                                                <label for="cert_nombre" class="form-label required-field">Nombre</label>
                                                                <input type="text" class="form-control" id="cert_nombre" name="nombre_insc" autocomplete="given-name" value="<?php echo h($certNombreValue); ?>" <?php echo $certInputsReadonly; ?> <?php echo $certificacionAllowSubmit ? 'required' : ''; ?>>
                                                            </div>
                                                            <div class="col-12">
                                                                <label for="cert_apellido" class="form-label required-field">Apellido</label>
                                                                <input type="text" class="form-control" id="cert_apellido" name="apellido_insc" autocomplete="family-name" value="<?php echo h($certApellidoValue); ?>" <?php echo $certInputsReadonly; ?> <?php echo $certificacionAllowSubmit ? 'required' : ''; ?>>
                                                            </div>
                                                            <div class="col-12">
                                                                <label for="cert_email" class="form-label required-field">Email</label>
                                                                <input type="email" class="form-control" id="cert_email" name="email_insc" autocomplete="email" value="<?php echo h($certEmailValue); ?>" <?php echo $certInputsReadonly; ?> <?php echo $certificacionAllowSubmit ? 'required' : ''; ?>>
                                                            </div>
                                                            <div class="col-12">
                                                                <label for="cert_telefono" class="form-label">Teléfono</label>
                                                                <input type="text" class="form-control" id="cert_telefono" name="tel_insc" autocomplete="tel" value="<?php echo h($certTelefonoValue); ?>" <?php echo $certInputsReadonly; ?>>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="cert_dni" class="form-label">DNI</label>
                                                                <input type="text" class="form-control" id="cert_dni" name="dni_insc" value="<?php echo h($certDniValue); ?>" <?php echo $certInputsReadonly; ?>>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="cert_pais" class="form-label">País</label>
                                                                <input type="text" class="form-control" id="cert_pais" name="pais_insc" value="<?php echo h($certPaisValue); ?>" <?php echo $certInputsReadonly; ?>>
                                                            </div>
                                                            <div class="col-12">
                                                                <label for="cert_direccion" class="form-label">Dirección</label>
                                                                <input type="text" class="form-control" id="cert_direccion" name="dir_insc" autocomplete="address-line1" value="<?php echo h($certDireccionValue); ?>" <?php echo $certInputsReadonly; ?>>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="cert_ciudad" class="form-label">Ciudad</label>
                                                                <input type="text" class="form-control" id="cert_ciudad" name="ciu_insc" autocomplete="address-level2" value="<?php echo h($certCiudadValue); ?>" <?php echo $certInputsReadonly; ?>>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label for="cert_provincia" class="form-label">Provincia</label>
                                                                <input type="text" class="form-control" id="cert_provincia" name="prov_insc" autocomplete="address-level1" value="<?php echo h($certProvinciaValue); ?>" <?php echo $certInputsReadonly; ?>>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="terms-check mt-4">
                                                <input type="checkbox" class="form-check-input mt-1" id="acepta" name="acepta_tyc" value="1" <?php echo (!empty($certificacionData) && (int)$certificacionData['acepta_tyc'] === 1) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="acepta">
                                                    Confirmo que la información es correcta y acepto los <a href="#" target="_blank" rel="noopener">Términos y Condiciones</a>.
                                                </label>
                                            </div>

                                            <div class="nav-actions">
                                                <button type="button" class="btn btn-outline-light btn-rounded" data-prev="1">
                                                    <i class="fas fa-arrow-left me-2"></i>
                                                    Volver
                                                </button>
                                                <div class="d-flex flex-column flex-sm-row gap-2">
                                                    <button type="button" class="btn btn-gradient btn-rounded" id="btnCertificacionEnviar" <?php echo $certificacionAllowSubmit ? '' : 'disabled'; ?>>
                                                        <span class="btn-label"><?php echo h($certificacionSubmitLabel); ?></span>
                                                        <i class="fas fa-paper-plane ms-2"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-light btn-rounded" data-next="3" id="btnIrPaso3" <?php echo $certificacionPuedePagar || $certificacionPagado ? '' : 'disabled'; ?>>
                                                        Ir al paso 3
                                                        <i class="fas fa-arrow-right ms-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="nombre" class="form-label required-field">Nombre</label>
                                                    <input type="text" class="form-control" id="nombre" name="nombre_insc" placeholder="Nombre" autocomplete="given-name" value="<?php echo h($prefillNombre); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="apellido" class="form-label required-field">Apellido</label>
                                                    <input type="text" class="form-control" id="apellido" name="apellido_insc" placeholder="Apellido" autocomplete="family-name" value="<?php echo h($prefillApellido); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="email" class="form-label required-field">Email</label>
                                                    <input type="email" class="form-control" id="email" name="email_insc" placeholder="correo@dominio.com" autocomplete="email" value="<?php echo h($prefillEmail); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="telefono" class="form-label required-field">Teléfono</label>
                                                    <input type="text" class="form-control" id="telefono" name="tel_insc" placeholder="+54 11 5555-5555" autocomplete="tel" value="<?php echo h($prefillTelefono); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="dni" class="form-label">DNI</label>
                                                    <input type="text" class="form-control" id="dni" name="dni_insc" placeholder="Documento" value="<?php echo h($certificacionData['dni'] ?? $prefillDni); ?>">
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="direccion" class="form-label">Dirección</label>
                                                    <input type="text" class="form-control" id="direccion" name="dir_insc" placeholder="Calle y número" autocomplete="address-line1" value="<?php echo h($certificacionData['direccion'] ?? $prefillDireccion); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="ciudad" class="form-label">Ciudad</label>
                                                    <input type="text" class="form-control" id="ciudad" name="ciu_insc" autocomplete="address-level2" value="<?php echo h($certificacionData['ciudad'] ?? $prefillCiudad); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="provincia" class="form-label">Provincia</label>
                                                    <input type="text" class="form-control" id="provincia" name="prov_insc" autocomplete="address-level1" value="<?php echo h($certificacionData['provincia'] ?? $prefillProvincia); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="pais" class="form-label">País</label>
                                                    <input type="text" class="form-control" id="pais" name="pais_insc" value="<?php echo h($certificacionData['pais'] ?? $prefillPais); ?>" autocomplete="country-name">
                                                </div>
                                            </div>
                                            <div class="terms-check mt-4">
                                                <input type="checkbox" class="form-check-input mt-1" id="acepta" name="acepta_tyc" value="1" <?php echo (!empty($certificacionData) && (int)$certificacionData['acepta_tyc'] === 1) ? 'checked' : ''; ?>>
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
                                        <div class="payment-box">
                                            <?php if ($tipo_checkout === 'certificacion' && !$certificacionPuedePagar && !$certificacionPagado): ?>
                                                <div class="alert alert-info checkout-alert" role="alert">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <i class="fas fa-hourglass-half mt-1"></i>
                                                        <div>
                                                            <strong>Estamos revisando tu documentación.</strong>
                                                            <div class="small mt-1">Te avisaremos por correo cuando podamos habilitar el pago.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php elseif ($tipo_checkout === 'certificacion' && $certificacionPagado): ?>
                                                <div class="alert alert-success checkout-alert" role="alert">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <i class="fas fa-circle-check mt-1"></i>
                                                        <div>
                                                            <strong>¡Listo! Registramos el pago de tu certificación.</strong>
                                                            <div class="small mt-1">Si necesitás actualizar algún dato, contactate con nuestro equipo.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($tipo_checkout !== 'certificacion' || $certificacionPuedePagar || $certificacionPagado): ?>
                                            <h5>Método de pago</h5>
                                            <label class="payment-option">
                                                <input type="radio" id="metodo_transfer" name="metodo_pago" value="transferencia" checked>
                                                <div class="payment-info">
                                                    <strong>Transferencia bancaria</strong>
                                                    <span>Subí el comprobante de tu transferencia.</span>
                                                </div>
                                            </label>
                                            <label class="payment-option mt-3">
                                                <input type="radio" id="metodo_mp" name="metodo_pago" value="mercado_pago" <?php echo $precio_vigente ? '' : 'disabled'; ?>>
                                                <div class="payment-info">
                                                    <strong>Mercado Pago</strong>
                                                    <?php if ($precio_vigente): ?>
                                                        <span>Pagá de forma segura con tarjetas, efectivo o saldo en Mercado Pago.</span>
                                                    <?php else: ?>
                                                        <span>Disponible cuando haya un precio vigente para esta capacitación.</span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>

                                            <div class="payment-details" id="transferDetails">
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
                                            <?php endif; ?>
                                        </div>

                                        <div class="nav-actions">
                                            <button type="button" class="btn btn-outline-light btn-rounded" data-prev="2">
                                                <i class="fas fa-arrow-left me-2"></i>
                                                Volver
                                            </button>
                                            <button type="button" class="btn btn-gradient btn-rounded" id="btnConfirmar" <?php echo ($tipo_checkout === 'certificacion' && (!$certificacionPuedePagar || $certificacionPagado)) ? 'disabled' : ''; ?>>
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
            const checkoutType = '<?php echo htmlspecialchars($tipo_checkout, ENT_QUOTES, 'UTF-8'); ?>';
            const certificacionPuedePagar = <?php echo $certificacionPuedePagar ? 'true' : 'false'; ?>;
            const certificacionPagado = <?php echo $certificacionPagado ? 'true' : 'false'; ?>;
            const certificacionAllowSubmit = <?php echo $certificacionAllowSubmit ? 'true' : 'false'; ?>;
            const certificacionId = <?php echo (int)$certificacionId; ?>;
            const mpEndpoint = '../checkout/mercadopago_init.php';
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
                if (checkoutType === 'certificacion') {
                    if (step === 3) {
                        if (certificacionPagado) {
                            showAlert('info', 'Pago registrado', 'Ya registramos el pago de tu certificación.');
                            return false;
                        }
                        if (!certificacionPuedePagar) {
                            showAlert('info', 'Aún estamos revisando tu documentación', 'Te avisaremos por correo cuando habilitemos el pago.');
                            return false;
                        }
                    }
                }
                if (step === 2) {
                    if (checkoutType === 'certificacion') {
                        const terms = document.getElementById('acepta');
                        if (!terms || !terms.checked) {
                            goToStep(2);
                            showAlert('warning', 'Términos y Condiciones', 'Debés aceptar los Términos y Condiciones para continuar.');
                            return false;
                        }
                        if (certificacionAllowSubmit) {
                            const requiredCert = [
                                { id: 'cert_nombre', label: 'Nombre' },
                                { id: 'cert_apellido', label: 'Apellido' },
                                { id: 'cert_email', label: 'Email' }
                            ];
                            const missingCert = requiredCert.find(field => {
                                const el = document.getElementById(field.id);
                                return !el || !el.value || el.value.trim() === '';
                            });
                            if (missingCert) {
                                goToStep(2);
                                showAlert('error', 'Faltan datos', `Completá el campo <strong>${missingCert.label}</strong> para continuar.`);
                                return false;
                            }
                            const certEmailEl = document.getElementById('cert_email');
                            const certEmail = certEmailEl ? certEmailEl.value.trim() : '';
                            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                            if (!emailPattern.test(certEmail)) {
                                goToStep(2);
                                showAlert('error', 'Email inválido', 'Ingresá un correo electrónico válido.');
                                return false;
                            }
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
                    const emailInput = document.getElementById('email');
                    const email = emailInput ? emailInput.value.trim() : '';
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(email)) {
                        goToStep(2);
                        showAlert('error', 'Email inválido', 'Ingresá un correo electrónico válido.');
                        return false;
                    }
                    const terms = document.getElementById('acepta');
                    if (!terms || !terms.checked) {
                        goToStep(2);
                        showAlert('warning', 'Términos y Condiciones', 'Debés aceptar los Términos y Condiciones para continuar.');
                        return false;
                    }
                }
                if (step === 3) {
                    const mpEl = document.getElementById('metodo_mp');
                    const transferEl = document.getElementById('metodo_transfer');
                    const mp = mpEl ? mpEl.checked : false;
                    const transfer = transferEl ? transferEl.checked : false;
                    if (!mpEl && !transferEl) {
                        return true;
                    }
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

            const validateCertificacionDocumento = () => {
                if (checkoutType !== 'certificacion') {
                    return true;
                }
                if (!certificacionAllowSubmit) {
                    showAlert('info', 'Solicitud en revisión', 'Ya recibimos tu formulario y estamos revisándolo.');
                    return false;
                }
                if (!certPdfInput) {
                    return true;
                }
                const file = certPdfInput.files[0];
                if (!file) {
                    const message = certificacionHasPdf
                        ? 'Subí nuevamente el formulario firmado para reenviar la solicitud.'
                        : 'Adjuntá el formulario firmado en formato PDF.';
                    showAlert('error', 'Falta el formulario', message);
                    return false;
                }
                if (file.type !== 'application/pdf') {
                    showAlert('error', 'Archivo inválido', 'El formulario debe estar en formato PDF.');
                    return false;
                }
                const maxSize = 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    showAlert('error', 'Archivo demasiado grande', 'El PDF debe pesar hasta 10 MB.');
                    return false;
                }
                return true;
            };

            document.querySelectorAll('[data-next]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const next = parseInt(btn.dataset.next, 10);
                    if (Number.isNaN(next)) {
                        return;
                    }
                    if (checkoutType === 'certificacion' && currentStep === 2 && next === 3 && (!certificacionPuedePagar && !certificacionPagado)) {
                        showAlert('info', 'Aún no podés continuar', 'Necesitamos aprobar tu documentación antes de habilitar el pago.');
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
            const btnCertificacionEnviar = document.getElementById('btnCertificacionEnviar');
            const btnIrPaso3 = document.getElementById('btnIrPaso3');
            const certPdfInput = document.getElementById('cert_pdf');
            const crearOrdenInput = document.querySelector('input[name="crear_orden"]');
            const accionInput = document.getElementById('__accion');
            const certificacionHasPdf = form ? form.dataset.certificacionHasPdf === '1' : false;
            const confirmButton = document.getElementById('btnConfirmar');
            if (!form || !confirmButton) {
                return;
            }
            let confirmLabel = confirmButton.querySelector('.btn-label');
            let confirmIcon = confirmButton.querySelector('i');
            const confirmDefault = {
                label: 'Confirmar inscripción',
                icon: 'fas fa-paper-plane ms-2'
            };
            const confirmDefaultMarkup = confirmButton.innerHTML;

            if (btnCertificacionEnviar && form) {
                btnCertificacionEnviar.addEventListener('click', () => {
                    if (checkoutType !== 'certificacion') {
                        return;
                    }
                    if (!validateStep(2) || !validateCertificacionDocumento()) {
                        return;
                    }
                    if (accionInput) {
                        accionInput.value = 'crear_certificacion';
                    }
                    if (crearOrdenInput) {
                        crearOrdenInput.value = '';
                    }
                    form.submit();
                });
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
                if (mpRadio && mpRadio.checked) {
                    confirmLabel.textContent = 'Ir a Mercado Pago';
                    confirmIcon.className = 'fas fa-credit-card ms-2';
                } else {
                    confirmLabel.textContent = confirmDefault.label;
                    confirmIcon.className = confirmDefault.icon;
                }
            };

            const togglePaymentDetails = () => {
                if (!transferRadio || !mpRadio || !transferDetails || !mpDetails) {
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

            if (mpRadio) {
                mpRadio.addEventListener('change', togglePaymentDetails);
            }
            if (transferRadio) {
                transferRadio.addEventListener('change', togglePaymentDetails);
            }
            togglePaymentDetails();

            const setConfirmLoading = (isLoading) => {
                if (isLoading) {
                    confirmButton.disabled = true;
                    confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Redirigiendo a Mercado Pago...';
                } else {
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = confirmDefaultMarkup;
                    updateConfirmButton();
                }
            };

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
                if (checkoutType === 'certificacion') {
                    if (certificacionPagado) {
                        showAlert('info', 'Pago registrado', 'Ya registramos el pago de tu certificación. No es necesario volver a enviar el formulario.');
                        return;
                    }
                    if (!certificacionPuedePagar) {
                        showAlert('info', 'Documentación en revisión', 'Te avisaremos por correo cuando habilitemos el pago.');
                        return;
                    }
                }
                if (!validateStep(2) || !validateStep(3)) {
                    return;
                }
                const mpSelected = mpRadio ? mpRadio.checked : false;
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
                        if (accionInput) {
                            accionInput.value = 'crear_orden';
                        }
                        form.submit();
                    }
                });
            });
        })();
    </script>
</body>
</html>