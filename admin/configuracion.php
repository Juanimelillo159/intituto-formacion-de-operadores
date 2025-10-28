<?php
require_once '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$mensajeExito = null;
$mensajeError = null;

try {
    $currentSettings = $site_settings ?? get_site_settings($con);
} catch (Throwable $settingsException) {
    $currentSettings = site_settings_defaults();
    $mensajeError = 'No se pudo cargar la configuración actual: ' . $settingsException->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $mode = $_POST['site_mode'] ?? 'normal';
        $mpEnabled = isset($_POST['mp_enabled']);
        $capEnabled = isset($_POST['capacitaciones_habilitadas']);
        $certEnabled = isset($_POST['certificaciones_habilitadas']);
        $notice = trim((string)($_POST['site_notice'] ?? ''));
        $disabledCourses = isset($_POST['disabled_courses']) && is_array($_POST['disabled_courses']) ? $_POST['disabled_courses'] : [];
        $disabledCerts = isset($_POST['disabled_certifications']) && is_array($_POST['disabled_certifications']) ? $_POST['disabled_certifications'] : [];

        update_site_settings($con, [
            'site_mode' => $mode,
            'mercado_pago_habilitado' => $mpEnabled,
            'capacitaciones_habilitadas' => $capEnabled,
            'certificaciones_habilitadas' => $certEnabled,
            'site_notice' => $notice,
            'cursos_deshabilitados' => $disabledCourses,
            'certificaciones_deshabilitadas' => $disabledCerts,
        ]);

        $currentSettings = get_site_settings($con);
        $mensajeExito = 'Los cambios se guardaron correctamente.';
    } catch (Throwable $updateException) {
        $mensajeError = 'No se pudo guardar la configuración: ' . $updateException->getMessage();
    }
}

$cursosDisponibles = [];
try {
    $cursoStmt = $con->query('SELECT id_curso, nombre_curso FROM cursos ORDER BY nombre_curso ASC');
    $cursosDisponibles = $cursoStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $ignored) {
    $cursosDisponibles = [];
}

$certificacionesDisponibles = [];
try {
    $certStmt = $con->query('SELECT id_certificacion, nombre_certificacion FROM certificaciones ORDER BY nombre_certificacion ASC');
    $certificacionesDisponibles = $certStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $ignored) {
    $certificacionesDisponibles = [];
}

$disabledCourses = $currentSettings['cursos_deshabilitados'] ?? [];
$disabledCertificaciones = $currentSettings['certificaciones_deshabilitadas'] ?? [];
$siteMode = site_settings_get_mode($currentSettings);
$siteNotice = site_settings_get_notice($currentSettings);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del sitio</title>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Configuración de la página</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="admin.php">Inicio</a></li>
                                <li class="breadcrumb-item active">Configuración</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <?php if ($mensajeExito !== null): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-circle-check mr-2"></i><?php echo htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if ($mensajeError !== null): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-circle-xmark mr-2"></i><?php echo htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-8">
                            <form method="POST" class="mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Estados del sitio</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="site_mode">Modo actual</label>
                                            <select name="site_mode" id="site_mode" class="form-control">
                                                <option value="normal" <?php echo $siteMode === 'normal' ? 'selected' : ''; ?>>Modo normal</option>
                                                <option value="construction" <?php echo $siteMode === 'construction' ? 'selected' : ''; ?>>Modo construcción</option>
                                                <option value="support" <?php echo $siteMode === 'support' ? 'selected' : ''; ?>>Modo soporte</option>
                                            </select>
                                            <small class="form-text text-muted">Los modos construcción o soporte muestran una página de mantenimiento y bloquean el acceso público.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="site_notice">Aviso destacado</label>
                                            <textarea name="site_notice" id="site_notice" class="form-control" rows="3" placeholder="Mensaje opcional para mostrar en la parte superior del sitio."><?php echo htmlspecialchars($siteNotice, ENT_QUOTES, 'UTF-8'); ?></textarea>
                                            <small class="form-text text-muted">Este mensaje se mostrará mientras el sitio esté en modo normal.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Medios de pago</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="mp_enabled" name="mp_enabled" <?php echo !empty($currentSettings['mercado_pago_habilitado']) ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="mp_enabled">Habilitar Mercado Pago</label>
                                            </div>
                                            <small class="form-text text-muted">Cuando está desactivado, solo se aceptarán comprobantes de transferencia.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <h3 class="card-title">Inscripciones</h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="capacitaciones_habilitadas" name="capacitaciones_habilitadas" <?php echo !empty($currentSettings['capacitaciones_habilitadas']) ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="capacitaciones_habilitadas">Permitir inscripciones a capacitaciones</label>
                                            </div>
                                            <small class="form-text text-muted">Deshabilitá esta opción para pausar todas las compras de capacitaciones.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="disabled_courses">Capacitaciones con inscripción bloqueada</label>
                                            <select name="disabled_courses[]" id="disabled_courses" class="form-control" multiple size="6">
                                                <?php foreach ($cursosDisponibles as $curso): ?>
                                                    <?php $idCurso = (int)($curso['id_curso'] ?? 0); ?>
                                                    <option value="<?php echo $idCurso; ?>" <?php echo in_array($idCurso, $disabledCourses, true) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($curso['nombre_curso'] ?? ('Curso #' . $idCurso), ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="form-text text-muted">Seleccioná los cursos cuya inscripción online querés deshabilitar temporalmente.</small>
                                        </div>
                                        <hr>
                                        <div class="form-group">
                                            <div class="custom-control custom-switch">
                                                <input type="checkbox" class="custom-control-input" id="certificaciones_habilitadas" name="certificaciones_habilitadas" <?php echo !empty($currentSettings['certificaciones_habilitadas']) ? 'checked' : ''; ?>>
                                                <label class="custom-control-label" for="certificaciones_habilitadas">Permitir solicitudes de certificación</label>
                                            </div>
                                            <small class="form-text text-muted">Si lo desactivás, ningún usuario podrá iniciar el proceso de certificación.</small>
                                        </div>
                                        <div class="form-group">
                                            <label for="disabled_certifications">Certificaciones bloqueadas</label>
                                            <select name="disabled_certifications[]" id="disabled_certifications" class="form-control" multiple size="6">
                                                <?php if (!empty($certificacionesDisponibles)): ?>
                                                    <?php foreach ($certificacionesDisponibles as $certificacion): ?>
                                                        <?php $idCert = (int)($certificacion['id_certificacion'] ?? 0); ?>
                                                        <option value="<?php echo $idCert; ?>" <?php echo in_array($idCert, $disabledCertificaciones, true) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($certificacion['nombre_certificacion'] ?? ('Certificación #' . $idCert), ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <?php foreach ($cursosDisponibles as $curso): ?>
                                                        <?php $idCurso = (int)($curso['id_curso'] ?? 0); ?>
                                                        <option value="<?php echo $idCurso; ?>" <?php echo in_array($idCurso, $disabledCertificaciones, true) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($curso['nombre_curso'] ?? ('Curso #' . $idCurso), ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                            <small class="form-text text-muted">Si la tabla de certificaciones no está disponible se listan los cursos como alternativa.</small>
                                        </div>
                                    </div>
                                    <div class="card-footer text-right">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save mr-1"></i> Guardar cambios
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Ayuda rápida</h3>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-3">Usá estas opciones para pausar ventas durante campañas, mantenimiento o tareas de soporte.</p>
                                    <ul class="mb-0 pl-3 text-muted">
                                        <li><strong>Modo construcción/soporte:</strong> bloquea el acceso al público y muestra una página personalizada.</li>
                                        <li><strong>Aviso destacado:</strong> ideal para comunicar novedades o recordatorios.</li>
                                        <li><strong>Listas de bloqueo:</strong> permiten pausar inscripciones específicas sin afectar el resto del catálogo.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</body>

</html>
