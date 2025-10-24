<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Permisos: solo RRHH (id_permisos = 3)
$permiso = isset($_SESSION['permiso']) ? (int)$_SESSION['permiso'] : null;
if ($permiso !== 3) {
    http_response_code(403);
    echo 'Acceso no autorizado.';
    exit;
}

// Parámetros básicos del detalle
$pedidoId   = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;
$cursoId    = isset($_GET['curso_id']) ? (int)$_GET['curso_id'] : 0;
$asistentes = isset($_GET['asistentes']) ? max(0, (int)$_GET['asistentes']) : 0;

// Inicializar valores de POST (aún sin persistir en DB)
$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Por ahora no guardamos en base, solo validamos mínimamente y dejamos listo para integrar
    if (!empty($_FILES['form_pdf']['name'])) {
        // Validación básica de tipo sin mover aún el archivo
        $okMime = isset($_FILES['form_pdf']['type']) && stripos((string)$_FILES['form_pdf']['type'], 'pdf') !== false;
        if (!$okMime) {
            $messages[] = ['type' => 'danger', 'text' => 'El archivo debe ser un PDF.'];
        }
    }

    // Recolectar asistentes enviados
    $enviados = isset($_POST['asistentes']) && is_array($_POST['asistentes']) ? $_POST['asistentes'] : [];
    $cantidadRecibida = count($enviados);
    if ($cantidadRecibida === 0) {
        $messages[] = ['type' => 'warning', 'text' => 'No se recibieron datos de asistentes.'];
    } else {
        $messages[] = ['type' => 'info', 'text' => 'Datos cargados temporalmente. Luego definimos dónde guardarlos.'];
    }
}

// Título y descripción
$page_title = 'Validar Asistentes | RRHH';
$page_description = 'Cargar y validar documentación y datos de asistentes para la certificación.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';
?>
<!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/head.php'; ?>
<body class="config-page d-flex flex-column min-vh-100">
<?php include __DIR__ . '/nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="historial_compras.php?pedido=<?php echo (int)$pedidoId; ?>#pedido" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Validar Asistentes</h1>
                    <p class="mb-0">Pedido #<?php echo (int)$pedidoId; ?> — Curso ID: <?php echo (int)$cursoId; ?></p>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container py-4">
        <?php foreach ($messages as $msg): ?>
            <div class="alert alert-<?php echo htmlspecialchars($msg['type'] ?? 'info', ENT_QUOTES, 'UTF-8'); ?> mb-3" role="alert">
                <?php echo htmlspecialchars($msg['text'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endforeach; ?>

        <form method="post" enctype="multipart/form-data">
        <div class="config-card shadow mb-4 text-start">
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%); border-radius: 12px;">
                    <i class="fas fa-file-pdf text-white" style="font-size: 24px;"></i>
                </div>
                <div>
                    <h5 class="mb-1">Documentación requerida</h5>
                    <p class="mb-0 small text-muted">Descargá, completá y subí el formulario firmado</p>
                </div>
            </div>

            <div class="row g-4">
                <!-- Paso 1: Descargar -->
                <div class="col-md-4">
                    <div class="p-4 rounded-3" style="background: linear-gradient(135deg, rgba(37,99,235,.05) 0%, rgba(6,182,212,.05) 100%); border: 1px solid rgba(37,99,235,.1);">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #2563eb; border-radius: 8px; color: #fff; font-weight: 600; font-size: 14px;">1</div>
                            <h6 class="mb-0">Descargar formulario</h6>
                        </div>
                        <p class="small text-muted mb-3">Descargá el PDF oficial con los campos a completar</p>
                        <a class="btn btn-sm w-100" href="assets/pdf/solicitud_certificacion.pdf" target="_blank" rel="noopener" style="background: #2563eb; color: white; border-radius: 8px; padding: 10px; font-weight: 500;">
                            <i class="fas fa-download me-2"></i>Descargar PDF
                        </a>
                    </div>
                </div>

                <!-- Paso 2: Completar -->
                <div class="col-md-4">
                    <div class="p-4 rounded-3" style="background: linear-gradient(135deg, rgba(37,99,235,.05) 0%, rgba(6,182,212,.05) 100%); border: 1px solid rgba(37,99,235,.1);">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #2563eb; border-radius: 8px; color: #fff; font-weight: 600; font-size: 14px;">2</div>
                            <h6 class="mb-0">Completar y firmar</h6>
                        </div>
                        <p class="small text-muted mb-3">Completá todos los campos requeridos y firmá el documento</p>
                        <div class="d-flex align-items-center gap-2 p-2 rounded" style="background: rgba(37,99,235,0.1);">
                            <i class="fas fa-info-circle" style="color: #2563eb;"></i>
                            <span class="small" style="color: #2563eb;">Guardá como PDF</span>
                        </div>
                    </div>
                </div>

                <!-- Paso 3: Subir -->
                <div class="col-md-4">
                    <div class="p-4 rounded-3" style="background: linear-gradient(135deg, rgba(37,99,235,.05) 0%, rgba(6,182,212,.05) 100%); border: 1px solid rgba(37,99,235,.1);">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #2563eb; border-radius: 8px; color: #fff; font-weight: 600; font-size: 14px;">3</div>
                            <h6 class="mb-0">Subir PDF firmado</h6>
                        </div>
                        <p class="small text-muted mb-3">Subí el formulario ya firmado en formato PDF</p>
                            <input type="hidden" name="pedido_id" value="<?php echo (int)$pedidoId; ?>">
                            <input type="hidden" name="curso_id" value="<?php echo (int)$cursoId; ?>">
                            <div class="mb-2">
                                <input type="file" class="form-control" id="form_pdf" name="form_pdf" accept="application/pdf" style="border-radius: 8px;">
                            </div>
                            <p class="small text-muted mb-0"><i class="fas fa-shield-alt me-1"></i>El archivo se validará y se guardará al confirmar</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="config-card shadow mb-4 text-start">
            <h5 class="mb-3">Datos de asistentes</h5>
            <p class="text-muted small mb-4">Cantidad de asistentes esperada: <?php echo (int)$asistentes; ?>. Completá los datos por cada asistente de la inscripción de certificación.</p>

            <?php
            $totalAsist = max(1, $asistentes);
            for ($i = 0; $i < $totalAsist; $i++):
            ?>
                <div class="border rounded-3 p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Asistente <?php echo $i + 1; ?></strong>
                        <span class="badge bg-light text-dark">#<?php echo $i + 1; ?></span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold mb-1">Nombre</label>
                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][nombre]" style="border-radius: 8px;"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold mb-1">Apellido</label>
                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][apellido]" style="border-radius: 8px;"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold mb-1">DNI</label>
                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][dni]" style="border-radius: 8px;"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold mb-1">País</label>
                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][pais]" style="border-radius: 8px;"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold mb-1">Email</label>
                            <input type="email" class="form-control" name="asistentes[<?php echo $i; ?>][email]" style="border-radius: 8px;"/>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold mb-1">Teléfono</label>
                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][telefono]" style="border-radius: 8px;"/>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

            <div class="d-flex gap-2">
                <a href="historial_compras.php?pedido=<?php echo (int)$pedidoId; ?>#pedido" class="btn btn-outline-secondary">Volver</a>
                <button type="submit" class="btn btn-gradient">Guardar datos (temporal)</button>
            </div>
            </form>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
