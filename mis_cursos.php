<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);

if ($userId <= 0 && isset($_SESSION['email'])) {
    try {
        $pdo = getPdo();
        $stmtUserLookup = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE email = :email LIMIT 1');
        $stmtUserLookup->bindValue(':email', (string)$_SESSION['email'], PDO::PARAM_STR);
        $stmtUserLookup->execute();
        $fetchedUserId = (int)$stmtUserLookup->fetchColumn();
        if ($fetchedUserId > 0) {
            $userId = $fetchedUserId;
            $_SESSION['id_usuario'] = $userId;
            if (!isset($_SESSION['usuario']) || !is_numeric($_SESSION['usuario'])) {
                $_SESSION['usuario'] = $userId;
            }
        }
    } catch (Throwable $lookupException) {
        error_log('mis_cursos fallback lookup: ' . $lookupException->getMessage());
    }
}

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$currentPermiso = (int)($_SESSION['permiso'] ?? 0);
$isHrManager = $currentPermiso === 3;

$misCursosFeedback = $_SESSION['mis_cursos_feedback'] ?? null;
if ($misCursosFeedback !== null) {
    unset($_SESSION['mis_cursos_feedback']);
}

$allowedFeedbackTypes = ['success', 'info', 'warning', 'danger'];

$redirectWithFeedback = static function (array $payload) {
    $_SESSION['mis_cursos_feedback'] = $payload;
    header('Location: mis_cursos.php');
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isHrManager) {
        $redirectWithFeedback(['type' => 'danger', 'message' => 'No tenes permisos para asignar cursos a trabajadores.']);
    }

    $action = trim((string)($_POST['action'] ?? ''));
    if ($action === 'assign_worker') {
        $singleWorkerId = (int)($_POST['worker_id'] ?? 0);
        $_POST['worker_ids'] = $singleWorkerId > 0 ? [$singleWorkerId] : [];
        $action = 'assign_workers';
    }

    if ($action === 'assign_workers') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $workerIdsInput = $_POST['worker_ids'] ?? [];
        if (!is_array($workerIdsInput)) {
            $workerIdsInput = [$workerIdsInput];
        }

        $workerIds = [];
        foreach ($workerIdsInput as $workerValue) {
            $workerId = (int)$workerValue;
            if ($workerId > 0) {
                $workerIds[$workerId] = $workerId;
            }
        }
        $workerIds = array_values($workerIds);

        if ($itemId <= 0 || empty($workerIds)) {
            $redirectWithFeedback(['type' => 'danger', 'message' => 'Selecciona al menos un trabajador y un curso validos.']);
        }

        try {
            $pdo = getPdo();
            $pdo->beginTransaction();

            $stmtItem = $pdo->prepare(
                'SELECT ci.id_item, ci.id_curso, ci.id_modalidad, ci.cantidad
                 FROM compra_items ci
                 INNER JOIN compras c ON c.id_compra = ci.id_compra
                 WHERE ci.id_item = :item AND c.id_usuario = :usuario AND c.estado = :estado
                 LIMIT 1 FOR UPDATE'
            );
            $stmtItem->bindValue(':item', $itemId, PDO::PARAM_INT);
            $stmtItem->bindValue(':usuario', $userId, PDO::PARAM_INT);
            $stmtItem->bindValue(':estado', 'pagada', PDO::PARAM_STR);
            $stmtItem->execute();
            $itemData = $stmtItem->fetch(PDO::FETCH_ASSOC);

            if (!$itemData) {
                $pdo->rollBack();
                $redirectWithFeedback(['type' => 'danger', 'message' => 'No se encontro el curso seleccionado.']);
            }

            $stmtAssignments = $pdo->prepare('SELECT id_usuario FROM inscripciones WHERE id_item_compra = :item FOR UPDATE');
            $stmtAssignments->bindValue(':item', $itemId, PDO::PARAM_INT);
            $stmtAssignments->execute();
            $currentAssignments = array_map('intval', $stmtAssignments->fetchAll(PDO::FETCH_COLUMN));
            $assignedCount = count($currentAssignments);
            $available = max(0, (int)$itemData['cantidad'] - $assignedCount);

            if ($available <= 0) {
                $pdo->rollBack();
                $redirectWithFeedback(['type' => 'danger', 'message' => 'No quedan cupos disponibles para este curso.']);
            }

            if (count($workerIds) > $available) {
                $pdo->rollBack();
                $redirectWithFeedback(['type' => 'warning', 'message' => 'Seleccionaste mas trabajadores que cupos disponibles.']);
            }

            $placeholders = implode(',', array_fill(0, count($workerIds), '?'));
            $stmtMembership = $pdo->prepare('SELECT id_trabajador FROM empresa_trabajadores WHERE id_empresa = ? AND id_trabajador IN (' . $placeholders . ')');
            $membershipParams = array_merge([$userId], $workerIds);
            $stmtMembership->execute($membershipParams);
            $validMembers = array_map('intval', $stmtMembership->fetchAll(PDO::FETCH_COLUMN));
            $missingWorkers = array_diff($workerIds, $validMembers);
            if (!empty($missingWorkers)) {
                $pdo->rollBack();
                $redirectWithFeedback(['type' => 'danger', 'message' => 'Algunos trabajadores seleccionados no pertenecen a tu empresa.']);
            }

            $alreadyAssigned = array_intersect($workerIds, $currentAssignments);
            if (!empty($alreadyAssigned)) {
                $pdo->rollBack();
                $redirectWithFeedback(['type' => 'warning', 'message' => 'Alguno de los trabajadores ya tiene asignado este curso.']);
            }

            $insert = $pdo->prepare(
                'INSERT INTO inscripciones (id_usuario, id_curso, id_modalidad, id_item_compra)
                 VALUES (:usuario, :curso, :modalidad, :item)'
            );
            $insert->bindValue(':curso', (int)$itemData['id_curso'], PDO::PARAM_INT);
            if ($itemData['id_modalidad'] !== null) {
                $insert->bindValue(':modalidad', (int)$itemData['id_modalidad'], PDO::PARAM_INT);
            } else {
                $insert->bindValue(':modalidad', null, PDO::PARAM_NULL);
            }
            $insert->bindValue(':item', $itemId, PDO::PARAM_INT);

            foreach ($workerIds as $workerId) {
                $insert->bindValue(':usuario', $workerId, PDO::PARAM_INT);
                $insert->execute();
            }

            $pdo->commit();

            $assignedTotal = count($workerIds);
            $redirectWithFeedback([
                'type' => 'success',
                'message' => $assignedTotal === 1
                    ? 'Se asigno 1 trabajador al curso.'
                    : 'Se asignaron ' . $assignedTotal . ' trabajadores al curso.'
            ]);
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('mis_cursos assign_workers: ' . $exception->getMessage());
            $redirectWithFeedback(['type' => 'danger', 'message' => 'No pudimos asignar el curso. Intentalo nuevamente.']);
        }
    }

    $redirectWithFeedback(['type' => 'danger', 'message' => 'Accion no valida.']);

}

$misCursosAlert = $_SESSION['mis_cursos_alert'] ?? null;
if ($misCursosAlert !== null) {
    unset($_SESSION['mis_cursos_alert']);
}

$page_title = 'Mis cursos | Instituto de Formacion';
$page_description = 'Cursos disponibles para tu cuenta.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';

$statusLabels = [
    'inscripto' => 'Inscripto',
    'en_curso' => 'En curso',
    'completado' => 'Completado',
    'vencido' => 'Vencido',
    'cancelado' => 'Cancelado',
];

$statusClasses = [
    'inscripto' => 'bg-primary',
    'en_curso' => 'bg-info text-dark',
    'completado' => 'bg-success',
    'vencido' => 'bg-warning text-dark',
    'cancelado' => 'bg-danger',
];

$cursosComprados = [];
$workersOptions = [];
$errorMessage = null;

try {
    $pdo = getPdo();

    $items = [];
    $loadedFromHrView = false;

    if ($isHrManager) {
        $sqlHr = <<<'SQL'
SELECT
    v.id_curso,
    v.tipo_curso,
    SUM(v.cantidad) AS total_cantidad,
    COALESCE(NULLIF(c.nombre_curso, ''), CONCAT('Curso #', v.id_curso)) AS nombre_curso,
    GROUP_CONCAT(DISTINCT m.nombre_modalidad ORDER BY m.nombre_modalidad SEPARATOR ' / ') AS modalidad_resumen
FROM v_cursos_rrhh v
LEFT JOIN cursos c ON c.id_curso = v.id_curso
LEFT JOIN curso_modalidad cm ON cm.id_curso = v.id_curso
LEFT JOIN modalidades m ON m.id_modalidad = cm.id_modalidad
WHERE v.id_usuario = :usuario
GROUP BY v.id_curso, v.tipo_curso, COALESCE(NULLIF(c.nombre_curso, ''), CONCAT('Curso #', v.id_curso))
ORDER BY nombre_curso ASC, v.tipo_curso ASC
SQL;
        $stmtHr = $pdo->prepare($sqlHr);
        $stmtHr->bindValue(':usuario', $userId, PDO::PARAM_INT);
        $stmtHr->execute();

        while ($row = $stmtHr->fetch(PDO::FETCH_ASSOC)) {
            $itemKey = ($row['tipo_curso'] ?? 'curso') . '-' . (int)$row['id_curso'];
            $courseName = trim((string)($row['nombre_curso'] ?? ''));
            if ($courseName === '') {
                $courseName = 'Curso';
            }

            $cantidad = (int)($row['total_cantidad'] ?? 0);
            $modalidadResumen = $row['modalidad_resumen'] ?? null;

            if (!isset($items[$itemKey])) {
                $items[$itemKey] = [
                    'id_item' => null,
                    'id_curso' => (int)$row['id_curso'],
                    'tipo_curso' => $row['tipo_curso'] ?? null,
                    'nombre_curso' => $courseName,
                    'nombre_modalidad' => $modalidadResumen,
                    'pagado_en' => null,
                    'pagado_en_formatted' => null,
                    'moneda' => null,
                    'precio_unitario' => null,
                    'cantidad' => $cantidad,
                    'inscripcion' => null,
                    'asignaciones' => [],
                    'asignados' => 0,
                    'disponibles' => $cantidad,
                    'can_assign' => false,
                ];
            } else {
                $items[$itemKey]['cantidad'] += $cantidad;
                $items[$itemKey]['disponibles'] = max(0, (int)$items[$itemKey]['cantidad']);
            }
        }

        $loadedFromHrView = !empty($items);
    }

    if (!$isHrManager) {
        $stmt = $pdo->prepare(
            'SELECT
                c.id_compra,
                c.pagado_en,
                c.moneda,
                ci.id_item,
                ci.cantidad,
                ci.precio_unitario,
                ci.titulo_snapshot,
                cursos.nombre_curso,
                modalidades.nombre_modalidad,
                i.id_inscripcion,
                i.estado AS inscripcion_estado,
                i.progreso AS inscripcion_progreso
             FROM compras c
             INNER JOIN compra_items ci ON ci.id_compra = c.id_compra
             INNER JOIN cursos ON cursos.id_curso = ci.id_curso
             LEFT JOIN modalidades ON modalidades.id_modalidad = ci.id_modalidad
             LEFT JOIN inscripciones i ON i.id_item_compra = ci.id_item AND i.id_usuario = c.id_usuario
             WHERE c.id_usuario = :usuario
               AND c.estado = :estado
             ORDER BY c.pagado_en DESC, c.id_compra DESC, ci.id_item ASC'
        );
        $stmt->bindValue(':usuario', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':estado', 'pagada', PDO::PARAM_STR);
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $itemId = (int)$row['id_item'];

            if (!isset($items[$itemId])) {
                $formattedDate = null;
                if (!empty($row['pagado_en'])) {
                    try {
                        $formattedDate = (new DateTimeImmutable($row['pagado_en']))->format('d/m/Y H:i');
                    } catch (Throwable $exception) {
                        $formattedDate = $row['pagado_en'];
                    }
                }

                $items[$itemId] = [
                    'id_item' => $itemId,
                    'nombre_curso' => $row['nombre_curso'] ?: $row['titulo_snapshot'],
                    'nombre_modalidad' => $row['nombre_modalidad'],
                    'pagado_en' => $row['pagado_en'],
                    'pagado_en_formatted' => $formattedDate,
                    'moneda' => $row['moneda'],
                    'precio_unitario' => (float)$row['precio_unitario'],
                    'cantidad' => (int)$row['cantidad'],
                    'inscripcion' => null,
                    'can_assign' => $isHrManager,
                ];
            }

            if ($items[$itemId]['inscripcion'] === null && !empty($row['inscripcion_estado'])) {
                $stateKey = strtolower((string)$row['inscripcion_estado']);
                $stateLabel = $statusLabels[$stateKey] ?? ucwords(str_replace('_', ' ', (string)$row['inscripcion_estado']));
                $stateClass = $statusClasses[$stateKey] ?? 'bg-secondary';

                $progress = null;
                if ($row['inscripcion_progreso'] !== null) {
                    $progress = max(0, min(100, (int)$row['inscripcion_progreso']));
                }

                $items[$itemId]['inscripcion'] = [
                    'id_inscripcion' => (int)$row['id_inscripcion'],
                    'estado' => $stateLabel,
                    'clase' => $stateClass,
                    'progreso' => $progress,
                ];
            }
        }
    }

    if ($isHrManager) {
        $assignableMap = [];

        foreach ($items as $key => &$item) {
            if (!isset($item['asignaciones']) || !is_array($item['asignaciones'])) {
                $item['asignaciones'] = [];
            }
            if (!isset($item['asignados'])) {
                $item['asignados'] = 0;
            }
            if (!isset($item['disponibles'])) {
                $item['disponibles'] = max(0, (int)$item['cantidad']);
            }

            if (!empty($item['can_assign']) && isset($item['id_item']) && $item['id_item'] !== null) {
                $assignableMap[(int)$item['id_item']] = $key;
            } else {
                $item['can_assign'] = false;
                $item['disponibles'] = max(0, (int)$item['cantidad'] - (int)($item['asignados'] ?? 0));
            }
        }
        unset($item);

        if (!empty($assignableMap)) {
            $placeholders = implode(',', array_fill(0, count($assignableMap), '?'));
            $sqlAssignments = 'SELECT
                    i.id_inscripcion,
                    i.id_item_compra,
                    i.id_usuario,
                    i.estado,
                    i.progreso,
                    u.nombre,
                    u.apellido,
                    u.email,
                    u.id_permiso
                FROM inscripciones i
                INNER JOIN usuarios u ON u.id_usuario = i.id_usuario
                WHERE i.id_item_compra IN (' . $placeholders . ')';

            $stmtAssignments = $pdo->prepare($sqlAssignments);
            $stmtAssignments->execute(array_keys($assignableMap));

            while ($assignment = $stmtAssignments->fetch(PDO::FETCH_ASSOC)) {
                $assignmentItemId = (int)$assignment['id_item_compra'];
                if (!isset($assignableMap[$assignmentItemId])) {
                    continue;
                }

                $itemKey = $assignableMap[$assignmentItemId];

                $stateKey = strtolower((string)$assignment['estado']);
                $stateLabel = $statusLabels[$stateKey] ?? ucwords(str_replace('_', ' ', (string)$assignment['estado']));
                $stateClass = $statusClasses[$stateKey] ?? 'bg-secondary';

                $progress = null;
                if ($assignment['progreso'] !== null) {
                    $progress = max(0, min(100, (int)$assignment['progreso']));
                }

                $items[$itemKey]['asignaciones'][] = [
                    'id_inscripcion' => (int)$assignment['id_inscripcion'],
                    'id_usuario' => (int)$assignment['id_usuario'],
                    'nombre' => $assignment['nombre'],
                    'apellido' => $assignment['apellido'],
                    'email' => $assignment['email'],
                    'permiso' => (int)$assignment['id_permiso'],
                    'estado' => $stateLabel,
                    'clase' => $stateClass,
                    'progreso' => $progress,
                ];
            }

            foreach ($assignableMap as $itemId => $itemKey) {
                $assignedCount = count($items[$itemKey]['asignaciones']);
                $items[$itemKey]['asignados'] = $assignedCount;
                $items[$itemKey]['disponibles'] = max(0, (int)$items[$itemKey]['cantidad'] - $assignedCount);
                $items[$itemKey]['can_assign'] = true;
            }

            $stmtWorkers = $pdo->prepare(
                'SELECT u.id_usuario, u.nombre, u.apellido, u.email
                 FROM empresa_trabajadores et
                 INNER JOIN usuarios u ON u.id_usuario = et.id_trabajador
                 WHERE et.id_empresa = ? AND u.id_permiso = 4
                 ORDER BY u.nombre ASC, u.apellido ASC, u.email ASC'
            );
            $stmtWorkers->execute([$userId]);
            $workersOptions = $stmtWorkers->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $workersOptions = [];
        }
    }

    $cursosComprados = array_values($items);
} catch (Throwable $exception) {
    error_log('mis_cursos load: ' . $exception->getMessage());
    $errorMessage = 'No pudimos cargar tus cursos en este momento.';
}


$configActive = 'mis_cursos';
?>
<!DOCTYPE html>
<html lang="es">
<?php include 'head.php'; ?>
<body class="config-page d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="index.php" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver al inicio</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Mis cursos</h1>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row g-4 align-items-start position-relative">
            <!-- Sidebar dentro del flujo (mobile/tablet) -->
            <div class="col-12 d-xl-none mb-4">
                <?php include 'config_sidebar.php'; ?>
            </div>

            <!-- Contenido de cursos -->
            <div class="col-12 col-xl-10 mx-auto mis-cursos-content">
                <?php if ($misCursosFeedback !== null): ?>
                <?php $feedbackType = in_array($misCursosFeedback['type'] ?? '', $allowedFeedbackTypes, true) ? $misCursosFeedback['type'] : 'info'; ?>
                <div class="alert alert-<?php echo htmlspecialchars($feedbackType, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                    <?php echo htmlspecialchars($misCursosFeedback['message'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>

                <?php if ($errorMessage !== null): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-0"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php elseif (empty($cursosComprados)): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-4">Todav&iacute;a no ten&eacute;s cursos adquiridos.</p>
                        <a class="btn btn-gradient" href="index.php#cursos">Explorar cursos disponibles</a>
                    </div>
                <?php else: ?>
                    <div class="mis-cursos-grid row row-cols-1 row-cols-md-2 g-4">
                        <?php foreach ($cursosComprados as $curso): ?>
                            <?php
                            $inscripcion = $curso['inscripcion'];
                            $precioUnitario = isset($curso['precio_unitario']) ? (float)$curso['precio_unitario'] : null;
                            $moneda = (string)($curso['moneda'] ?? '');
                            $precioLabel = null;
                            if ($precioUnitario !== null) {
                                $formattedPrice = number_format($precioUnitario, 2, ',', '.');
                                $precioLabel = $moneda !== '' ? $moneda . ' ' . $formattedPrice : $formattedPrice;
                            }
                            ?>
                            <div class="col-12 col-md-6">
                                <div class="config-card course-card shadow text-start h-100">
                                    <div class="course-card__header">
                                        <div class="course-card__headline d-flex align-items-start justify-content-between gap-3 flex-wrap">
                                            <div class="course-card__info">
                                                <h2 class="h5 mb-1"><?php echo htmlspecialchars($curso['nombre_curso'] ?? 'Curso', ENT_QUOTES, 'UTF-8'); ?></h2>
                                                <?php if (!empty($curso['nombre_modalidad'])): ?>
                                                    <div class="text-muted small">Modalidad: <?php echo htmlspecialchars($curso['nombre_modalidad'], ENT_QUOTES, 'UTF-8'); ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="course-card__status text-md-end ms-md-auto">
                                                <?php if ($inscripcion !== null): ?>
                                                    <span class="badge <?php echo htmlspecialchars($inscripcion['clase'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($inscripcion['estado'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php if ($inscripcion['progreso'] !== null): ?>
                                                        <div class="text-muted small mt-2">Progreso: <?php echo (int)$inscripcion['progreso']; ?>%</div>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Acceso disponible</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="course-card__chips d-flex flex-wrap gap-2">
                                            <span class="course-chip">
                                                Comprado el <?php echo htmlspecialchars($curso['pagado_en_formatted'] ?? 'Sin fecha', ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <?php if ($precioLabel !== null): ?>
                                                <span class="course-chip course-chip--accent"><?php echo htmlspecialchars($precioLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <?php if ($isHrManager): ?>
                                                <span class="course-chip">Total: <?php echo (int)$curso['cantidad']; ?></span>
                                                <span class="course-chip">Asignados: <?php echo isset($curso['asignados']) ? (int)$curso['asignados'] : 0; ?></span>
                                                <span class="course-chip">Disponibles: <?php echo isset($curso['disponibles']) ? (int)$curso['disponibles'] : max(0, (int)$curso['cantidad']); ?></span>
                                            <?php elseif ($curso['cantidad'] > 1): ?>
                                                <span class="course-chip">Cantidad: <?php echo (int)$curso['cantidad']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($inscripcion !== null && $inscripcion['progreso'] !== null): ?>
                                        <div class="course-card__progress progress mt-3">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo (int)$inscripcion['progreso']; ?>%;" aria-valuenow="<?php echo (int)$inscripcion['progreso']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($isHrManager && !empty($curso['can_assign'])): ?>
                                        <?php
                                        $assignedWorkers = $curso['asignaciones'] ?? [];
                                        $assignedWorkerIds = [];
                                        foreach ($assignedWorkers as $assignmentData) {
                                            $assignedWorkerIds[] = (int)($assignmentData['id_usuario'] ?? 0);
                                        }
                                        $availableWorkers = [];
                                        foreach ($workersOptions as $workerOption) {
                                            $workerOptionId = (int)($workerOption['id_usuario'] ?? 0);
                                            if ($workerOptionId > 0 && !in_array($workerOptionId, $assignedWorkerIds, true)) {
                                                $availableWorkers[] = $workerOption;
                                            }
                                        }
                                        $panelId = 'assign-panel-' . (int)$curso['id_item'];
                                        $selectAllId = 'assign-select-all-' . (int)$curso['id_item'];
                                        $formId = 'assign-form-' . (int)$curso['id_item'];
                                        $availableSlots = isset($curso['disponibles']) ? (int)$curso['disponibles'] : max(0, (int)$curso['cantidad']);
                                        $maxSelectable = min($availableSlots, count($availableWorkers));
                                        ?>
                                        <div class="course-card__section mt-4">
                                            <h3 class="course-card__section-title h6 mb-3">Trabajadores asignados</h3>
                                            <?php if (empty($assignedWorkers)): ?>
                                                <p class="text-muted small mb-0">Todav&iacute;a no asignaste este curso.</p>
                                            <?php else: ?>
                                                <ul class="assigned-workers list-unstyled mb-0">
                                                    <?php foreach ($assignedWorkers as $assignment): ?>
                                                        <?php
                                                        $fullName = trim((string)($assignment['nombre'] ?? '') . ' ' . (string)($assignment['apellido'] ?? ''));
                                                        if ($fullName === '') {
                                                            $fullName = (string)($assignment['email'] ?? 'Trabajador');
                                                        }
                                                        ?>
                                                        <li class="assigned-workers__item">
                                                            <div class="assigned-workers__header d-flex flex-wrap align-items-center justify-content-between gap-2">
                                                                <span class="fw-semibold"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <span class="badge <?php echo htmlspecialchars($assignment['clase'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($assignment['estado'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            </div>
                                                            <?php if (!empty($assignment['email'])): ?>
                                                                <span class="text-muted small d-block"><?php echo htmlspecialchars((string)$assignment['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                            <?php endif; ?>
                                                            <?php if ($assignment['progreso'] !== null): ?>
                                                                <span class="text-muted small">Progreso: <?php echo (int)$assignment['progreso']; ?>%</span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>

                                        <div class="course-card__section mt-3">
                                            <button class="btn btn-outline-primary btn-sm<?php echo ($availableSlots <= 0 || empty($availableWorkers)) ? ' disabled' : ''; ?>" type="button" <?php if ($availableSlots > 0 && !empty($availableWorkers)): ?>data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>" aria-expanded="false" aria-controls="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                                                Asignar trabajadores
                                            </button>
                                            <?php if ($availableSlots <= 0): ?>
                                                <p class="text-danger small mb-0 mt-2">No quedan cupos disponibles para asignar.</p>
                                            <?php elseif (empty($workersOptions)): ?>
                                                <p class="text-muted small mb-0 mt-2">Todav&iacute;a no sumaste trabajadores a tu empresa.</p>
                                            <?php elseif (empty($availableWorkers)): ?>
                                                <p class="text-muted small mb-0 mt-2">Todos tus trabajadores ya tienen este curso asignado.</p>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($availableSlots > 0 && !empty($availableWorkers)): ?>
                                            <div class="collapse mt-3" id="<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>">
                                                <div class="assign-panel shadow-sm">
                                                    <form method="POST" class="assign-workers-form" id="<?php echo htmlspecialchars($formId, ENT_QUOTES, 'UTF-8'); ?>" data-available="<?php echo (int)$availableSlots; ?>">
                                                        <input type="hidden" name="action" value="assign_workers">
                                                        <input type="hidden" name="item_id" value="<?php echo (int)$curso['id_item']; ?>">
                                                        <div class="assign-panel__stats d-flex flex-wrap gap-3 align-items-center small text-muted mb-3">
                                                            <span>Cupos disponibles: <strong data-remaining-count><?php echo (int)$availableSlots; ?></strong></span>
                                                            <span>Seleccionados: <strong data-selected-count>0</strong></span>
                                                        </div>
                                                        <div class="form-check form-check-sm mb-2">
                                                            <input class="form-check-input assign-select-all" type="checkbox" id="<?php echo htmlspecialchars($selectAllId, ENT_QUOTES, 'UTF-8'); ?>" data-max-select="<?php echo (int)$maxSelectable; ?>">
                                                            <label class="form-check-label small" for="<?php echo htmlspecialchars($selectAllId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                Seleccionar todos (hasta <?php echo (int)$maxSelectable; ?>)
                                                            </label>
                                                        </div>
                                                        <div class="assign-workers-list border rounded bg-white p-2" style="max-height: 220px; overflow: auto;">
                                                            <?php foreach ($availableWorkers as $worker): ?>
                                                                <?php
                                                                $workerId = (int)($worker['id_usuario'] ?? 0);
                                                                if ($workerId <= 0) {
                                                                    continue;
                                                                }
                                                                $workerName = trim((string)($worker['nombre'] ?? '') . ' ' . (string)($worker['apellido'] ?? ''));
                                                                if ($workerName === '') {
                                                                    $workerName = (string)($worker['email'] ?? 'Trabajador');
                                                                }
                                                                $workerEmail = (string)($worker['email'] ?? '');
                                                                $workerLabel = $workerName;
                                                                if ($workerEmail !== '' && $workerName !== $workerEmail) {
                                                                    $workerLabel .= ' (' . $workerEmail . ')';
                                                                }
                                                                $inputId = 'assign-worker-' . (int)$curso['id_item'] . '-' . $workerId;
                                                                ?>
                                                                <div class="form-check form-check-sm mb-2">
                                                                    <input class="form-check-input assign-worker-checkbox" type="checkbox" name="worker_ids[]" value="<?php echo $workerId; ?>" id="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <label class="form-check-label small" for="<?php echo htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                        <?php echo htmlspecialchars($workerLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                        <p class="text-muted small mt-3 mb-3">Vas a crear una inscripci&oacute;n por cada trabajador seleccionado. Esta acci&oacute;n no se puede revertir.</p>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <button type="submit" class="btn btn-primary btn-sm" data-assign-submit disabled>
                                                                Asignar
                                                            </button>
                                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#<?php echo htmlspecialchars($panelId, ENT_QUOTES, 'UTF-8'); ?>">
                                                                Cancelar
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar fija “por fuera” (solo desktop) -->
            <div class="d-none d-xl-block position-fixed sidebar-outside">
                <?php include 'config_sidebar.php'; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<!-- CSS para la sidebar fija “por fuera” -->
<style>
@media (min-width: 1200px) {
  .sidebar-outside {
    left: 24px;      /* separación del borde izquierdo */
    top: 120px;      /* ajustá según la altura de tu navbar/header */
    width: 280px;    /* ancho de la sidebar */
    z-index: 1020;
  }
  /* Evitar que el contenido interno de la sidebar desborde */
  .sidebar-outside .config-card,
  .sidebar-outside .card,
  .sidebar-outside > * {
    max-width: 100%;
  }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var assignForms = document.querySelectorAll('.assign-workers-form');

    assignForms.forEach(function (form) {
        var available = parseInt(form.getAttribute('data-available'), 10);
        if (isNaN(available) || available < 0) {
            available = 0;
        }

        var checkboxes = Array.prototype.slice.call(form.querySelectorAll('.assign-worker-checkbox'));
        var selectAll = form.querySelector('.assign-select-all');
        var selectedCountNode = form.querySelector('[data-selected-count]');
        var remainingCountNode = form.querySelector('[data-remaining-count]');
        var submitButton = form.querySelector('[data-assign-submit]');

        var updateState = function () {
            var selected = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            if (selectedCountNode) {
                selectedCountNode.textContent = selected;
            }

            var remaining = Math.max(available - selected, 0);
            if (remainingCountNode) {
                remainingCountNode.textContent = remaining;
            }

            if (submitButton) {
                submitButton.disabled = selected === 0 || selected > available;
            }

            var limit = Math.min(available, checkboxes.length);
            if (selectAll) {
                if (limit <= 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    selectAll.disabled = true;
                } else {
                    var allSelected = selected >= limit;
                    selectAll.checked = allSelected;
                    selectAll.indeterminate = selected > 0 && !allSelected;
                    selectAll.disabled = false;
                }
            }

            if (available > 0) {
                var shouldDisable = selected >= available;
                checkboxes.forEach(function (checkbox) {
                    if (!checkbox.checked) {
                        checkbox.disabled = shouldDisable;
                    } else {
                        checkbox.disabled = false;
                    }
                });
            }
        };

        checkboxes.forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                if (checkbox.checked) {
                    var selected = checkboxes.filter(function (cb) {
                        return cb.checked;
                    }).length;
                    if (selected > available) {
                        checkbox.checked = false;
                        return;
                    }
                }
                updateState();
            });
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                if (!selectAll.checked) {
                    checkboxes.forEach(function (checkbox) {
                        checkbox.checked = false;
                        checkbox.disabled = false;
                    });
                    updateState();
                    return;
                }

                var allowed = Math.min(available, checkboxes.length);
                var selected = 0;

                checkboxes.forEach(function (checkbox) {
                    if (selected < allowed) {
                        if (!checkbox.checked) {
                            checkbox.checked = true;
                        }
                        selected += 1;
                    } else {
                        checkbox.checked = false;
                    }
                });

                updateState();
            });
        }

        form.addEventListener('submit', function (event) {
            var selected = checkboxes.filter(function (checkbox) {
                return checkbox.checked;
            }).length;

            if (selected === 0) {
                event.preventDefault();
                window.alert('Selecciona al menos un trabajador para continuar.');
                return;
            }

            if (selected > available) {
                event.preventDefault();
                window.alert('Seleccionaste mas trabajadores que cupos disponibles.');
                return;
            }

            var message = selected === 1
                ? 'Se asignara 1 trabajador al curso. Esta accion no se puede revertir. Deseas continuar?'
                : 'Se asignaran ' + selected + ' trabajadores al curso. Esta accion no se puede revertir. Deseas continuar?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });

        updateState();
    });
});
</script>
<?php if ($misCursosAlert !== null): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var data = <?php echo json_encode($misCursosAlert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

            if (!data) {
                return;
            }

            var title = data.title || 'Sesion iniciada';
            var message = data.message || '';
            var text = (message && message.trim()) ? message : title;

            var styleId = 'mis-cursos-toast-style';
            if (!document.getElementById(styleId)) {
                var style = document.createElement('style');
                style.id = styleId;
                style.textContent = 'n                    .floating-login-alert {n                        position: fixed;n                        top: 1rem;n                        right: 1rem;n                        z-index: 2000;n                        min-width: 220px;n                        max-width: 320px;n                        padding: 0.75rem 1rem;n                        border-radius: 0.5rem;n                        background-color: #198754;n                        border: 1px solid #146c43;n                        color: #fff;n                        box-shadow: 0 0.5rem 1rem rgba(25, 135, 84, 0.35);n                        opacity: 0;n                        transform: translateY(-10px);n                        transition: opacity 200ms ease, transform 200ms ease;n                    }n                    .floating-login-alert.show {n                        opacity: 1;n                        transform: translateY(0);n                    }n                    .floating-login-alert.hide {n                        opacity: 0;n                        transform: translateY(-10px);n                    }n                ';
                document.head.appendChild(style);
            }

            var alertNode = document.createElement('div');
            alertNode.className = 'floating-login-alert alert alert-success';
            alertNode.setAttribute('role', 'alert');
            alertNode.textContent = text;

            var offsetTop = 16;
            var stickyNav = document.querySelector('.navbar.sticky-top');
            if (stickyNav) {
                offsetTop = stickyNav.getBoundingClientRect().height + 16;
            }
            alertNode.style.top = offsetTop + 'px';

            alertNode.addEventListener('click', function () {
                alertNode.classList.add('hide');
            });

            alertNode.addEventListener('transitionend', function (event) {
                if (event.propertyName === 'opacity' && alertNode.classList.contains('hide')) {
                    alertNode.remove();
                }
            });

            document.body.appendChild(alertNode);

            requestAnimationFrame(function () {
                alertNode.classList.add('show');
            });

            setTimeout(function () {
                alertNode.classList.add('hide');
            }, 5000);
        });
    </script>
<?php endif; ?>
</body>
</html>
