<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Permisos: solo RRHH (id_permisos = 3)
$permiso = isset($_SESSION['permiso']) ? (int)$_SESSION['permiso'] : null;
$currentUserId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);
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
    // Validación de PDFs por asistente si existen
    if (isset($_FILES['asistentes']) && is_array($_FILES['asistentes'])) {
        $files = $_FILES['asistentes'];
        if (isset($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $idx => $names) {
                if (is_array($names) && array_key_exists('pdf', $names)) {
                    $name = (string)$names['pdf'];
                    if ($name !== '') {
                        $type = (string)($files['type'][$idx]['pdf'] ?? '');
                        if (stripos($type, 'pdf') === false) {
                            $messages[] = ['type' => 'danger', 'text' => 'El archivo del asistente #' . ((int)$idx + 1) . ' debe ser PDF.'];
                        }
                    }
                }
            }
        }
    }

    // Recolectar asistentes enviados
    $enviados = isset($_POST['asistentes']) && is_array($_POST['asistentes']) ? $_POST['asistentes'] : [];
    $cantidadRecibida = count($enviados);
    if ($cantidadRecibida === 0) {
        $messages[] = ['type' => 'warning', 'text' => 'No se recibieron datos de asistentes.'];
    } else {
        // Intentar guardar en BD si existen tablas (solicitudes_certificacion, trabajadores, solicitudes_certificacion_asistentes)
        try {
            $pdo = getPdo();
            $pdo->beginTransaction();

            // Crear solicitud
            $stInsSol = $pdo->prepare('INSERT INTO solicitudes_certificacion (pedido_id, curso_id, creado_por) VALUES (?, ?, ?)');
            $stInsSol->execute([(int)$pedidoId, (int)$cursoId, ($currentUserId > 0 ? $currentUserId : null)]);
            $solicitudId = (int)$pdo->lastInsertId();

            // Preparar consultas trabajador
            $stFindTrabDni = $pdo->prepare('SELECT id_trabajador FROM trabajadores WHERE dni = ? LIMIT 1');
            $stFindTrabEmail = $pdo->prepare('SELECT id_trabajador FROM trabajadores WHERE email = ? LIMIT 1');
            $stInsTrab = $pdo->prepare('INSERT INTO trabajadores (nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais, creado_por) VALUES (:nombre, :apellido, :email, :telefono, :dni, :direccion, :ciudad, :provincia, :pais, :creado_por)');

            $uploadBaseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'asistentes';
            if (!is_dir($uploadBaseDir)) {
                @mkdir($uploadBaseDir, 0775, true);
            }

            $stInsSolAsis = $pdo->prepare('INSERT INTO solicitudes_certificacion_asistentes (id_solicitud, id_trabajador, pdf_path, pdf_nombre) VALUES (?, ?, ?, ?)');

            foreach ($enviados as $idx => $a) {
                $nombre    = trim((string)($a['nombre'] ?? ''));
                $apellido  = trim((string)($a['apellido'] ?? ''));
                $dni       = trim((string)($a['dni'] ?? ''));
                $pais      = trim((string)($a['pais'] ?? ''));
                $email     = trim((string)($a['email'] ?? ''));
                $telefono  = trim((string)($a['telefono'] ?? ''));
                $direccion = trim((string)($a['direccion'] ?? ''));
                $ciudad    = trim((string)($a['ciudad'] ?? ''));
                $provincia = trim((string)($a['provincia'] ?? ''));

                // Buscar trabajador existente por DNI o Email
                $trabId = 0;
                if ($dni !== '') {
                    $stFindTrabDni->execute([$dni]);
                    $trabId = (int)($stFindTrabDni->fetchColumn() ?: 0);
                }
                if ($trabId <= 0 && $email !== '') {
                    $stFindTrabEmail->execute([$email]);
                    $trabId = (int)($stFindTrabEmail->fetchColumn() ?: 0);
                }

                if ($trabId <= 0) {
                    $stInsTrab->execute([
                        ':nombre'   => $nombre !== '' ? $nombre : null,
                        ':apellido' => $apellido !== '' ? $apellido : null,
                        ':email'    => $email !== '' ? $email : null,
                        ':telefono' => $telefono !== '' ? $telefono : null,
                        ':dni'      => $dni !== '' ? $dni : null,
                        ':direccion'=> $direccion !== '' ? $direccion : null,
                        ':ciudad'   => $ciudad !== '' ? $ciudad : null,
                        ':provincia'=> $provincia !== '' ? $provincia : null,
                        ':pais'     => $pais !== '' ? $pais : null,
                        ':creado_por' => $currentUserId > 0 ? $currentUserId : null,
                    ]);
                    $trabId = (int)$pdo->lastInsertId();
                }

                // Guardar PDF si se subió
                $pdfPath = null; $pdfNombre = null;
                if (isset($_FILES['asistentes']['name'][$idx]['pdf'])) {
                    $origName = (string)($_FILES['asistentes']['name'][$idx]['pdf'] ?? '');
                    $tmpName  = (string)($_FILES['asistentes']['tmp_name'][$idx]['pdf'] ?? '');
                    $error    = (int)($_FILES['asistentes']['error'][$idx]['pdf'] ?? UPLOAD_ERR_NO_FILE);
                    $type     = (string)($_FILES['asistentes']['type'][$idx]['pdf'] ?? '');
                    if ($error === UPLOAD_ERR_OK && $tmpName !== '' && stripos($type, 'pdf') !== false) {
                        $safeBase = preg_replace('/[^A-Za-z0-9_\.-]+/', '_', pathinfo($origName, PATHINFO_FILENAME));
                        $destName = sprintf('sol_%d_asist_%d_%s.pdf', $solicitudId, (int)$idx + 1, $safeBase !== '' ? $safeBase : 'documento');
                        $destPath = $uploadBaseDir . DIRECTORY_SEPARATOR . $destName;
                        if (@move_uploaded_file($tmpName, $destPath)) {
                            $pdfPath = 'uploads/asistentes/' . $destName;
                            $pdfNombre = $origName;
                        }
                    }
                }

                $stInsSolAsis->execute([$solicitudId, $trabId, $pdfPath, $pdfNombre]);
            }

            $pdo->commit();
            $messages[] = ['type' => 'success', 'text' => 'Solicitud registrada y asistentes guardados correctamente.'];
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO) {
                try { $pdo->rollBack(); } catch (Throwable $e2) {}
            }
            $messages[] = ['type' => 'danger', 'text' => 'No se pudo guardar en la base de datos: ' . $e->getMessage()];
        }
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
                <div class="col-md-6 col-lg-4">
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
                <div class="col-md-6 col-lg-4">
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

                <!-- El PDF se sube por asistente en cada pestaña -->
            </div>
        </div>

        <div class="config-card shadow mb-4 text-start">
            <h5 class="mb-3">Datos de asistentes</h5>
            <p class="text-muted small mb-4">Cantidad de asistentes esperada: <?php echo (int)$asistentes; ?>. Completá los datos por cada asistente y subí su PDF correspondiente.</p>

            <?php $totalAsist = max(1, $asistentes); ?>
            <ul class="nav nav-pills config-tabs flex-column flex-md-row gap-2" id="asistentesTabs" role="tablist">
                <?php for ($i = 0; $i < $totalAsist; $i++): $active = ($i === 0); ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active ? 'active' : ''; ?>" id="tab-asistente-<?php echo $i; ?>" data-bs-toggle="tab" data-bs-target="#panel-asistente-<?php echo $i; ?>" type="button" role="tab" aria-controls="panel-asistente-<?php echo $i; ?>" aria-selected="<?php echo $active ? 'true' : 'false'; ?>">
                            Asistente <?php echo $i + 1; ?>
                        </button>
                    </li>
                <?php endfor; ?>
            </ul>

            <div class="tab-content mt-4" id="asistentesTabsContent">
                <?php for ($i = 0; $i < $totalAsist; $i++): $active = ($i === 0); ?>
                    <div class="tab-pane fade <?php echo $active ? 'show active' : ''; ?>" id="panel-asistente-<?php echo $i; ?>" role="tabpanel" aria-labelledby="tab-asistente-<?php echo $i; ?>">
                        <div class="row g-3">
                            <!-- Información personal -->
                            <div class="col-12">
                                <div class="p-3 rounded-3" style="background: rgba(37, 99, 235, 0.03); border-left: 3px solid #2563eb;">
                                    <h6 class="mb-3" style="color: #2563eb; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-id-card me-2"></i>Información Personal
                                    </h6>
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
                                            <label class="form-label small fw-semibold mb-1">DNI / Documento</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][dni]" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">País</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][pais]" style="border-radius: 8px;"/>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Información de contacto -->
                            <div class="col-12">
                                <div class="p-3 rounded-3" style="background: rgba(6, 182, 212, 0.03); border-left: 3px solid #06b6d4;">
                                    <h6 class="mb-3" style="color: #06b6d4; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-envelope me-2"></i>Información de Contacto
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">Email</label>
                                            <input type="email" class="form-control" name="asistentes[<?php echo $i; ?>][email]" autocomplete="email" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">Teléfono</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][telefono]" autocomplete="tel" style="border-radius: 8px;"/>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dirección -->
                            <div class="col-12">
                                <div class="p-3 rounded-3" style="background: rgba(139, 92, 246, 0.03); border-left: 3px solid #8b5cf6;">
                                    <h6 class="mb-3" style="color: #8b5cf6; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-map-marker-alt me-2"></i>Dirección
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold mb-1">Calle y número</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][direccion]" autocomplete="address-line1" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">Ciudad</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][ciudad]" autocomplete="address-level2" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">Provincia / Estado</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][provincia]" autocomplete="address-level1" style="border-radius: 8px;"/>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PDF del asistente -->
                            <div class="col-12">
                                <label class="form-label small fw-semibold mb-1"><i class="fas fa-cloud-upload-alt me-2" style="color:#2563eb;"></i>Subir PDF del asistente <?php echo $i + 1; ?></label>
                                <input type="file" class="form-control" name="asistentes[<?php echo $i; ?>][pdf]" accept="application/pdf" style="border-radius: 8px;"/>
                                <div class="small text-muted mt-2"><i class="fas fa-info-circle me-1"></i>Se valida el tipo de archivo al enviar.</div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <input type="hidden" name="pedido_id" value="<?php echo (int)$pedidoId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo (int)$cursoId; ?>">

            <div class="d-flex gap-2 mt-3">
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
