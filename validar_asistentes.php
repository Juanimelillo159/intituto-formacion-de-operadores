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
$solicitudIdParam = isset($_GET['solicitud_id']) ? (int)$_GET['solicitud_id'] : 0;

// Inicializar valores de POST (aún sin persistir en DB)
$messages = [];
$existingPrefill = [];
$existingPdfs = [];
$userWorkers = [];

// Cargar solicitud guardada (por id si viene) o la última para este pedido/curso/usuario y usarla como prellenado
try {
    if ($pedidoId > 0 && $cursoId > 0 && $currentUserId > 0) {
        $pdo = getPdo();
        $lastId = 0;
        if ($solicitudIdParam > 0) {
            $stChk = $pdo->prepare('SELECT id_solicitud FROM solicitudes_certificacion WHERE id_solicitud = :id AND creado_por = :u LIMIT 1');
            $stChk->bindValue(':id', $solicitudIdParam, PDO::PARAM_INT);
            $stChk->bindValue(':u', $currentUserId, PDO::PARAM_INT);
            $stChk->execute();
            $lastId = (int)($stChk->fetchColumn() ?: 0);
        }
        if ($lastId <= 0) {
            $stLast = $pdo->prepare('SELECT id_solicitud FROM solicitudes_certificacion WHERE pedido_id = :p AND curso_id = :c AND creado_por = :u ORDER BY creado_en DESC, id_solicitud DESC LIMIT 1');
            $stLast->bindValue(':p', $pedidoId, PDO::PARAM_INT);
            $stLast->bindValue(':c', $cursoId, PDO::PARAM_INT);
            $stLast->bindValue(':u', $currentUserId, PDO::PARAM_INT);
            $stLast->execute();
            $lastId = (int)($stLast->fetchColumn() ?: 0);
        }
        if ($lastId > 0) {
            $stA = $pdo->prepare('SELECT a.id, a.id_trabajador, a.pdf_path, a.pdf_nombre, t.nombre, t.apellido, t.email, t.telefono, t.dni, t.direccion, t.ciudad, t.provincia, t.pais
                                  FROM solicitudes_certificacion_asistentes a
                                  LEFT JOIN trabajadores t ON t.id_trabajador = a.id_trabajador
                                  WHERE a.id_solicitud = :id
                                  ORDER BY a.id ASC');
            $stA->bindValue(':id', $lastId, PDO::PARAM_INT);
            $stA->execute();
            $rows = $stA->fetchAll(PDO::FETCH_ASSOC);
            $i = 0;
            foreach ($rows as $r) {
                $existingPrefill[$i] = [
                    'trabajador_id' => isset($r['id_trabajador']) ? (int)$r['id_trabajador'] : 0,
                    'nombre'    => (string)($r['nombre'] ?? ''),
                    'apellido'  => (string)($r['apellido'] ?? ''),
                    'dni'       => (string)($r['dni'] ?? ''),
                    'email'     => (string)($r['email'] ?? ''),
                    'telefono'  => (string)($r['telefono'] ?? ''),
                    'direccion' => (string)($r['direccion'] ?? ''),
                    'ciudad'    => (string)($r['ciudad'] ?? ''),
                    'provincia' => (string)($r['provincia'] ?? ''),
                    'pais'      => (string)($r['pais'] ?? ''),
                ];
                $existingPdfs[$i] = [
                    'path'   => (string)($r['pdf_path'] ?? ''),
                    'nombre' => (string)($r['pdf_nombre'] ?? ''),
                ];
                $i++;
            }
        }
        // Cargar trabajadores del usuario para selector
        $stW = $pdo->prepare('SELECT id_trabajador, nombre, apellido, dni, email, telefono, direccion, ciudad, provincia, pais FROM trabajadores WHERE creado_por = :u ORDER BY nombre ASC, apellido ASC, email ASC');
        $stW->bindValue(':u', $currentUserId, PDO::PARAM_INT);
        $stW->execute();
        while ($w = $stW->fetch(PDO::FETCH_ASSOC)) {
            $wid = (int)($w['id_trabajador'] ?? 0);
            if ($wid > 0) { $userWorkers[$wid] = $w; }
        }
    }
} catch (Throwable $eLoad) {
    // No interrumpir si falla el prellenado
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Requerir que todos los asistentes esperados tengan Nombre y Apellido
    $totalAsist = max(1, $asistentes);
    $enviados = isset($_POST['asistentes']) && is_array($_POST['asistentes']) ? $_POST['asistentes'] : [];
    $incompletos = [];
    $seenTrabIds = [];
    $duplicateWorkers = [];
    for ($i = 0; $i < $totalAsist; $i++) {
        $a = $enviados[$i] ?? [];
        $trabSel = isset($a['trabajador_id']) ? (int)$a['trabajador_id'] : 0;
        $nombre    = trim((string)($a['nombre'] ?? ''));
        $apellido  = trim((string)($a['apellido'] ?? ''));
        $dni       = trim((string)($a['dni'] ?? ''));
        $email     = trim((string)($a['email'] ?? ''));
        if ($trabSel > 0) {
            if (in_array($trabSel, $seenTrabIds, true)) { $duplicateWorkers[] = $trabSel; }
            $seenTrabIds[] = $trabSel;
            continue;
        }
        if ($nombre === '' || $apellido === '' || $dni === '' || $email === '') {
            $incompletos[] = $i + 1; // 1-based
        }
    }

    if (!empty($duplicateWorkers)) {
        $messages[] = ['type' => 'danger', 'text' => 'No puedes asignar el mismo trabajador más de una vez en la misma solicitud.'];
    } elseif (!empty($incompletos)) {
        $messages[] = ['type' => 'danger', 'text' => 'Debes completar todos los asistentes con Nombre, Apellido, DNI y Email. Faltan datos en: ' . implode(', ', $incompletos) . '.'];
    } else {
        // Guardar todos los asistentes
        try {
            $pdo = getPdo();
            $pdo->beginTransaction();

            // Usar solicitud existente si viene por parámetro y pertenece a este usuario; si no, buscar la última; si no hay, crearla
            $solicitudId = 0;
            if ($solicitudIdParam > 0) {
                $stSolChk = $pdo->prepare('SELECT id_solicitud FROM solicitudes_certificacion WHERE id_solicitud = ? AND creado_por = ? LIMIT 1');
                $stSolChk->execute([$solicitudIdParam, ($currentUserId > 0 ? $currentUserId : null)]);
                $solicitudId = (int)($stSolChk->fetchColumn() ?: 0);
            }
            if ($solicitudId <= 0) {
                $stFindSol = $pdo->prepare('SELECT id_solicitud FROM solicitudes_certificacion WHERE pedido_id = ? AND curso_id = ? AND creado_por = ? ORDER BY creado_en DESC, id_solicitud DESC LIMIT 1');
                $stFindSol->execute([(int)$pedidoId, (int)$cursoId, ($currentUserId > 0 ? $currentUserId : null)]);
                $solicitudId = (int)($stFindSol->fetchColumn() ?: 0);
            }
            if ($solicitudId <= 0) {
                $stInsSol = $pdo->prepare('INSERT INTO solicitudes_certificacion (pedido_id, curso_id, creado_por) VALUES (?, ?, ?)');
                $stInsSol->execute([(int)$pedidoId, (int)$cursoId, ($currentUserId > 0 ? $currentUserId : null)]);
                $solicitudId = (int)$pdo->lastInsertId();
            }

            $stFindTrabDni = $pdo->prepare('SELECT id_trabajador, creado_por FROM trabajadores WHERE dni = ? LIMIT 1');
            $stFindTrabEmail = null;
            $stInsTrab = $pdo->prepare('INSERT INTO trabajadores (nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais, creado_por) VALUES (:nombre, :apellido, :email, :telefono, :dni, :direccion, :ciudad, :provincia, :pais, :creado_por)');
            $stClaimTrab = $pdo->prepare('UPDATE trabajadores SET creado_por = :uid WHERE id_trabajador = :id AND (creado_por IS NULL OR creado_por = 0)');

            $uploadBaseDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'asistentes';
            if (!is_dir($uploadBaseDir)) { @mkdir($uploadBaseDir, 0775, true); }

            // Cargar asistentes existentes para conservar PDF si no se reemplaza y actualizar en su lugar
            $stGetAsis = $pdo->prepare('SELECT id, pdf_path, pdf_nombre FROM solicitudes_certificacion_asistentes WHERE id_solicitud = ? ORDER BY id ASC');
            $stGetAsis->execute([$solicitudId]);
            $existingRows = $stGetAsis->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stInsSolAsis = $pdo->prepare('INSERT INTO solicitudes_certificacion_asistentes (id_solicitud, id_trabajador, pdf_path, pdf_nombre) VALUES (?, ?, ?, ?)');
            $stUpdSolAsis = $pdo->prepare('UPDATE solicitudes_certificacion_asistentes SET id_trabajador = ?, pdf_path = ?, pdf_nombre = ? WHERE id = ?');
            $stDelSolAsis = $pdo->prepare('DELETE FROM solicitudes_certificacion_asistentes WHERE id = ?');

            for ($i = 0; $i < $totalAsist; $i++) {
                $a = $enviados[$i] ?? [];
                $trabSel = isset($a['trabajador_id']) ? (int)$a['trabajador_id'] : 0;
                $nombre    = trim((string)($a['nombre'] ?? ''));
                $apellido  = trim((string)($a['apellido'] ?? ''));
                $dni       = trim((string)($a['dni'] ?? ''));
                $email     = trim((string)($a['email'] ?? ''));
                $telefono  = trim((string)($a['telefono'] ?? ''));
                $direccion = trim((string)($a['direccion'] ?? ''));
                $ciudad    = trim((string)($a['ciudad'] ?? ''));
                $provincia = trim((string)($a['provincia'] ?? ''));
                $pais      = trim((string)($a['pais'] ?? ''));

                $trabId = 0; $trabCreadoPor = null;
                if ($trabSel > 0) {
                    $stOwn = $pdo->prepare('SELECT COUNT(*) FROM trabajadores WHERE id_trabajador = ? AND creado_por = ?');
                    $stOwn->execute([$trabSel, $currentUserId]);
                    if ((int)$stOwn->fetchColumn() > 0) {
                        $trabId = $trabSel;
                    } else {
                        throw new RuntimeException('Trabajador seleccionado inválido.');
                    }
                }
                if ($trabId <= 0 && $dni !== '') { $stFindTrabDni->execute([$dni]); $trabId = (int)($stFindTrabDni->fetchColumn() ?: 0); }
                if ($trabId <= 0 && $email !== '') { }

                // Si existe trabajador pero sin propietario, reclamarlo para esta cuenta
                if ($trabId <= 0 && $dni !== '') {
                    $stFindTrabDni->execute([$dni]);
                    $row = $stFindTrabDni->fetch(PDO::FETCH_ASSOC);
                    if ($row) { $trabId = (int)$row['id_trabajador']; $trabCreadoPor = (int)($row['creado_por'] ?? 0); }
                }
                if ($trabId <= 0 && $email !== '') { }

                if ($trabId <= 0) {
                    $stInsTrab->execute([
                        ':nombre'   => $nombre,
                        ':apellido' => $apellido,
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
                } else {
                    if ($currentUserId > 0 && ($trabCreadoPor === null || $trabCreadoPor === 0)) {
                        $stClaimTrab->execute([':uid' => $currentUserId, ':id' => $trabId]);
                    }
                }

                // PDF por asistente
                $pdfPath = null; $pdfNombre = null;
                if (isset($_FILES['asistentes']['name'][$i]['pdf'])) {
                    $origName = (string)($_FILES['asistentes']['name'][$i]['pdf'] ?? '');
                    $tmpName  = (string)($_FILES['asistentes']['tmp_name'][$i]['pdf'] ?? '');
                    $error    = (int)($_FILES['asistentes']['error'][$i]['pdf'] ?? UPLOAD_ERR_NO_FILE);
                    $type     = (string)($_FILES['asistentes']['type'][$i]['pdf'] ?? '');
                    if ($error === UPLOAD_ERR_OK && $tmpName !== '' && stripos($type, 'pdf') !== false) {
                        $safeBase = preg_replace('/[^A-Za-z0-9_\.-]+/', '_', pathinfo($origName, PATHINFO_FILENAME));
                        $destName = sprintf('sol_%d_asist_%d_%s.pdf', $solicitudId, (int)$i + 1, $safeBase !== '' ? $safeBase : 'documento');
                        $destPath = $uploadBaseDir . DIRECTORY_SEPARATOR . $destName;
                        if (@move_uploaded_file($tmpName, $destPath)) { $pdfPath = 'uploads/asistentes/' . $destName; $pdfNombre = $origName; }
                    }
                }

                // Si no subieron PDF nuevo y existe uno previo en misma posición, conservarlo
                if ($pdfPath === null && isset($existingRows[$i])) {
                    $pdfPath = $existingRows[$i]['pdf_path'] ?? null;
                    $pdfNombre = $existingRows[$i]['pdf_nombre'] ?? null;
                }

                if (isset($existingRows[$i])) {
                    $stUpdSolAsis->execute([$trabId, $pdfPath, $pdfNombre, (int)$existingRows[$i]['id']]);
                } else {
                    $stInsSolAsis->execute([$solicitudId, $trabId, $pdfPath, $pdfNombre]);
                }
            }

            // Eliminar asistentes sobrantes si antes había más que ahora
            $existingCount = count($existingRows);
            if ($existingCount > $totalAsist) {
                for ($j = $totalAsist; $j < $existingCount; $j++) {
                    $stDelSolAsis->execute([(int)$existingRows[$j]['id']]);
                }
            }

            $pdo->commit();
            $messages[] = ['type' => 'success', 'text' => 'Solicitud registrada y asistentes guardados correctamente.'];
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo instanceof PDO) { try { $pdo->rollBack(); } catch (Throwable $e2) {} }
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

            <?php $totalAsist = max(1, $asistentes); $prefill = $_POST['asistentes'] ?? $existingPrefill; ?>
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
                            <!-- Seleccionar trabajador existente -->
                            <div class="col-12">
                                <div class="p-3 rounded-3" style="background: rgba(99,102,241,0.05); border-left: 3px solid #6366f1;">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0" style="color:#4f46e5; font-size:14px; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Cargar trabajador</h6>
                                    </div>
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-8">
                                            <label class="form-label small fw-semibold mb-1">Seleccionar de mis trabajadores</label>
                                            <select class="form-select" name="asistentes[<?php echo $i; ?>][trabajador_id]" id="sel-trab-<?php echo $i; ?>">
                                                <option value="">-- Sin seleccionar --</option>
                                                <?php foreach ($userWorkers as $wid => $w): ?>
                                                    <?php $selected = (int)($prefill[$i]['trabajador_id'] ?? 0) === (int)$wid ? 'selected' : ''; ?>
                                                    <option value="<?php echo (int)$wid; ?>" <?php echo $selected; ?>><?php echo htmlspecialchars(trim(($w['nombre'] ?? '').' '.($w['apellido'] ?? '').' - '.($w['dni'] ?? '').' - '.($w['email'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <button type="button" class="btn btn-outline-primary" onclick="fillWorker(<?php echo $i; ?>)">Cargar datos</button>
                                        </div>
                                    </div>
                                    <div class="small text-muted mt-2">Si seleccionás un trabajador, se completarán automáticamente los datos. No podés usar el mismo trabajador más de una vez por solicitud.</div>
                                </div>
                            </div>

                            <!-- Información personal -->
                            <div class="col-12">
                                <div class="p-3 rounded-3" style="background: rgba(37, 99, 235, 0.03); border-left: 3px solid #2563eb;">
                                    <h6 class="mb-3" style="color: #2563eb; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-id-card me-2"></i>Información Personal
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">Nombre</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][nombre]" value="<?php echo htmlspecialchars((string)($prefill[$i]['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">Apellido</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][apellido]" value="<?php echo htmlspecialchars((string)($prefill[$i]['apellido'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">DNI / Documento</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][dni]" value="<?php echo htmlspecialchars((string)($prefill[$i]['dni'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">País</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][pais]" value="<?php echo htmlspecialchars((string)($prefill[$i]['pais'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" style="border-radius: 8px;"/>
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
                                            <input type="email" class="form-control" name="asistentes[<?php echo $i; ?>][email]" value="<?php echo htmlspecialchars((string)($prefill[$i]['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="email" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">Teléfono</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][telefono]" value="<?php echo htmlspecialchars((string)($prefill[$i]['telefono'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="tel" style="border-radius: 8px;"/>
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
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][direccion]" value="<?php echo htmlspecialchars((string)($prefill[$i]['direccion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="address-line1" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">Ciudad</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][ciudad]" value="<?php echo htmlspecialchars((string)($prefill[$i]['ciudad'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="address-level2" style="border-radius: 8px;"/>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small fw-semibold mb-1">Provincia / Estado</label>
                                            <input type="text" class="form-control" name="asistentes[<?php echo $i; ?>][provincia]" value="<?php echo htmlspecialchars((string)($prefill[$i]['provincia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="address-level1" style="border-radius: 8px;"/>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- PDF del asistente -->
                                <div class="col-12">
                                    <label class="form-label small fw-semibold mb-1"><i class="fas fa-cloud-upload-alt me-2" style="color:#2563eb;"></i>Subir PDF del asistente <?php echo $i + 1; ?></label>
                                    <input type="file" class="form-control" name="asistentes[<?php echo $i; ?>][pdf]" accept="application/pdf" style="border-radius: 8px;"/>
                                    <?php $pdfInfo = $existingPdfs[$i] ?? null; if ($pdfInfo && !empty($pdfInfo['path'])): ?>
                                        <div class="mt-2">
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars((string)$pdfInfo['path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                                <i class="fas fa-file-pdf me-1"></i>Ver PDF actual
                                            </a>
                                            <?php if (!empty($pdfInfo['nombre'])): ?>
                                                <span class="small text-muted ms-2"><?php echo htmlspecialchars((string)$pdfInfo['nombre'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="small text-muted mt-2"><i class="fas fa-info-circle me-1"></i>Se valida el tipo de archivo al enviar.</div>
                                    <?php endif; ?>
                                </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <input type="hidden" name="pedido_id" value="<?php echo (int)$pedidoId; ?>">
            <input type="hidden" name="curso_id" value="<?php echo (int)$cursoId; ?>">

            <div class="d-flex gap-2 mt-3">
                <a href="historial_compras.php?pedido=<?php echo (int)$pedidoId; ?>#pedido" class="btn btn-outline-secondary">Volver</a>
                <button type="submit" class="btn btn-gradient">Completar solicitud</button>
            </div>
            </form>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  (function(){ return;
    const form = document.querySelector('form[method="post"]');
    if (!form) return;
    form.addEventListener('submit', function(e){
      const total = <?php echo (int)max(1, $asistentes); ?>;
      for (let i = 0; i < total; i++) {
        const nombre = form.querySelector(`[name="asistentes[${i}][nombre]"]`);
        const apellido = form.querySelector(`[name="asistentes[${i}][apellido]"]`);
        const dni = form.querySelector(`[name="asistentes[${i}][dni]"]`);
        const email = form.querySelector(`[name="asistentes[${i}][email]"]`);
        const emailOk = email && email.value.trim() !== '' && /.+@.+\..+/.test(email.value.trim());
        const ok = nombre && nombre.value.trim() !== '' && apellido && apellido.value.trim() !== '' && dni && dni.value.trim() !== '' && emailOk;
        if (!ok) {
          e.preventDefault();
          const tabBtn = document.getElementById(`tab-asistente-${i}`);
          if (tabBtn) {
            try { new bootstrap.Tab(tabBtn).show(); } catch(_e){}
          }
          alert('Completá Nombre, Apellido, DNI y Email válido para todos los asistentes. Faltan datos en el asistente ' + (i+1));
          return false;
        }
      }
    });
  })();
</script>
<script>
  (function(){
    const form = document.querySelector('form[method="post"]');
    if (!form) return;
    form.addEventListener('submit', function(e){
      const total = <?php echo (int)max(1, $asistentes); ?>;
      // Mapa de PDFs existentes por asistente (true si ya hay PDF guardado)
      const existing = <?php echo json_encode(array_values(array_map(function($x){ return !empty($x['path']); }, $existingPdfs)), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
      let missing = 0;
      for (let i = 0; i < total; i++) {
        const input = form.querySelector(`[name="asistentes[${i}][pdf]"]`);
        const hasNew = input && input.files && input.files.length > 0;
        const hasExisting = Array.isArray(existing) && typeof existing[i] !== 'undefined' ? !!existing[i] : false;
        if (!hasNew && !hasExisting) missing++;
      }
      if (missing > 0) {
        const msg = 'Faltan PDF(s) por cargar. Si continuás, la solicitud puede ser rechazada si no se envían los PDFs correspondientes. ¿Deseás continuar?';
        if (!confirm(msg)) {
          e.preventDefault();
          return false;
        }
      }
    }, {capture: true});
  })();
</script>
<script>
  const workersMap = <?php echo json_encode($userWorkers, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
  function fillWorker(idx){
    const form = document.querySelector('form[method="post"]');
    if (!form) return;
    const sel = document.getElementById('sel-trab-' + idx);
    if (!sel) return;
    const wid = sel.value ? parseInt(sel.value, 10) : 0;
    const setVal = (name, val) => {
      const el = form.querySelector(`[name="asistentes[${idx}][${name}]"]`);
      if (el) el.value = val || '';
    };
    if (wid && workersMap[wid]){
      const w = workersMap[wid];
      setVal('nombre', w.nombre || '');
      setVal('apellido', w.apellido || '');
      setVal('dni', w.dni || '');
      setVal('email', w.email || '');
      setVal('telefono', w.telefono || '');
      setVal('direccion', w.direccion || '');
      setVal('ciudad', w.ciudad || '');
      setVal('provincia', w.provincia || '');
      setVal('pais', w.pais || '');
    }
  }
  (function(){
    const form = document.querySelector('form[method="post"]');
    if (!form) return;
    form.addEventListener('submit', function(e){
      const total = <?php echo (int)max(1, $asistentes); ?>;
      const selected = new Set();
      for (let i = 0; i < total; i++) {
        const sel = document.getElementById('sel-trab-' + i);
        const wid = sel && sel.value ? parseInt(sel.value, 10) : 0;
        if (wid){
          if (selected.has(wid)){
            e.preventDefault();
            try { new bootstrap.Tab(document.getElementById(`tab-asistente-${i}`)).show(); } catch(_e){}
            alert('No podés asignar el mismo trabajador más de una vez.');
            return false;
          }
          selected.add(wid);
          continue;
        }
        const nombre = form.querySelector(`[name="asistentes[${i}][nombre]"]`);
        const apellido = form.querySelector(`[name="asistentes[${i}][apellido]"]`);
        const dni = form.querySelector(`[name="asistentes[${i}][dni]"]`);
        const email = form.querySelector(`[name="asistentes[${i}][email]"]`);
        const emailOk = email && email.value.trim() !== '' && /.+@.+\..+/.test(email.value.trim());
        const ok = nombre && nombre.value.trim() !== '' && apellido && apellido.value.trim() !== '' && dni && dni.value.trim() !== '' && emailOk;
        if (!ok) {
          e.preventDefault();
          const tabBtn = document.getElementById(`tab-asistente-${i}`);
          if (tabBtn) { try { new bootstrap.Tab(tabBtn).show(); } catch(_e){} }
          alert('Completá Nombre, Apellido, DNI y Email válido o seleccioná un trabajador. Faltan datos en el asistente ' + (i+1));
          return false;
        }
      }
    });
  })();
</script>
</body>
</html>
