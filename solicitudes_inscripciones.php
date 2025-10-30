<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Solo RRHH
$permiso = isset($_SESSION['permiso']) ? (int)$_SESSION['permiso'] : null;
if ($permiso !== 3) {
    header('Location: index.php');
    exit;
}

$userId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$page_title = 'Solicitudes de Inscripciones | RRHH';
$page_description = 'Listado de solicitudes enviadas y sus asistentes';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';

$error = null;
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$pageS = isset($_GET['page_s']) ? max(1, (int)$_GET['page_s']) : 1; // solicitudes
$pageP = isset($_GET['page_p']) ? max(1, (int)$_GET['page_p']) : 1; // pedidos
$pageC = isset($_GET['page_c']) ? max(1, (int)$_GET['page_c']) : 1; // pedidos cerrados
$perPage = 15;
$solicitudId = isset($_GET['solicitud']) ? (int)$_GET['solicitud'] : 0;
$solicitud = null;
$asistentes = [];
$solicitudes = [];
$pedidos = [];

try {
    $pdo = getPdo();
    if ($solicitudId > 0) {
        // Detalle de una solicitud
        $st = $pdo->prepare('SELECT s.id_solicitud, s.pedido_id, s.curso_id, s.creado_en, c.nombre_curso
                              FROM solicitudes_certificacion s
                              LEFT JOIN cursos c ON c.id_curso = s.curso_id
                              WHERE s.id_solicitud = :id AND s.creado_por = :u');
        $st->bindValue(':id', $solicitudId, PDO::PARAM_INT);
        $st->bindValue(':u', $userId, PDO::PARAM_INT);
        $st->execute();
        $solicitud = $st->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($solicitud) {
            $stA = $pdo->prepare('SELECT a.id, t.nombre, t.apellido, t.dni, t.email, a.pdf_path, a.pdf_nombre, a.creado_en
                                   FROM solicitudes_certificacion_asistentes a
                                   LEFT JOIN trabajadores t ON t.id_trabajador = a.id_trabajador
                                   WHERE a.id_solicitud = :id
                                   ORDER BY a.id ASC');
            $stA->bindValue(':id', $solicitudId, PDO::PARAM_INT);
            $stA->execute();
            $asistentes = $stA->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Listado de solicitudes previas: solo pendientes (p.estado = 1), solo certificaciones
        $sql = 'SELECT s.id_solicitud, s.pedido_id, s.curso_id, s.creado_en, c.nombre_curso,
                       d.turno AS turno,
                       p.estado AS estado,
                       COUNT(a.id) AS cant_asistentes
                FROM solicitudes_certificacion s
                INNER JOIN inscripcion_pedidos p ON p.id = s.pedido_id AND COALESCE(p.estado,1) = 1
                LEFT JOIN inscripcion_pedidos_detalle d ON d.pedido_id = s.pedido_id AND d.curso_id = s.curso_id
                LEFT JOIN cursos c ON c.id_curso = s.curso_id
                LEFT JOIN solicitudes_certificacion_asistentes a ON a.id_solicitud = s.id_solicitud
                WHERE s.creado_por = :u
                  AND (LOWER(d.tipo) = "certificacion" OR LOWER(d.tipo) = "certificación")
                  ' . ($q !== '' ? ' AND (c.nombre_curso LIKE :q1 OR CAST(s.pedido_id AS CHAR) LIKE :q2 OR CAST(s.curso_id AS CHAR) LIKE :q3 OR d.turno LIKE :q4) ' : '') . '
                GROUP BY s.id_solicitud, s.pedido_id, s.curso_id, s.creado_en, c.nombre_curso, d.turno, p.estado
                ORDER BY s.creado_en DESC, s.id_solicitud DESC';
        $st = $pdo->prepare($sql);
        $st->bindValue(':u', $userId, PDO::PARAM_INT);
        if ($q !== '') { $like = '%' . $q . '%'; $st->bindValue(':q1', $like, PDO::PARAM_STR); $st->bindValue(':q2', $like, PDO::PARAM_STR); $st->bindValue(':q3', $like, PDO::PARAM_STR); $st->bindValue(':q4', $like, PDO::PARAM_STR); }
        $st->execute();
        $solicitudesAll = $st->fetchAll(PDO::FETCH_ASSOC);
        $totalS = count($solicitudesAll);
        $offsetS = ($pageS - 1) * $perPage;
        $solicitudes = array_slice($solicitudesAll, $offsetS, $perPage);

        // Listado de pedidos/detalles para iniciar validación (RRHH)
        // Pedidos para validación: solo pendientes, solo certificaciones y sin solicitud enviada aún
        $sqlP = 'SELECT p.id AS pedido_id, p.created_at, COALESCE(p.estado,1) AS estado,
                        d.curso_id, c.nombre_curso, d.tipo, d.turno, d.asistentes, d.ubicacion
                 FROM inscripcion_pedidos p
                 INNER JOIN inscripcion_pedidos_detalle d ON d.pedido_id = p.id
                 LEFT JOIN cursos c ON c.id_curso = d.curso_id
                 WHERE p.usuario_id = :u
                   AND COALESCE(p.estado,1) = 1
                   AND (LOWER(d.tipo) = "certificacion" OR LOWER(d.tipo) = "certificación")
                   AND NOT EXISTS (
                       SELECT 1 FROM solicitudes_certificacion s
                        WHERE s.creado_por = :u2
                          AND s.pedido_id = p.id
                          AND s.curso_id = d.curso_id
                   )
                   ' . ($q !== '' ? ' AND (c.nombre_curso LIKE :q1 OR d.turno LIKE :q2 OR CAST(p.id AS CHAR) LIKE :q3 OR CAST(d.curso_id AS CHAR) LIKE :q4) ' : '') . '
                 ORDER BY p.created_at DESC, p.id DESC, d.id ASC';
        $stP = $pdo->prepare($sqlP);
        $stP->bindValue(':u', $userId, PDO::PARAM_INT);
        $stP->bindValue(':u2', $userId, PDO::PARAM_INT);
        if ($q !== '') { $like = '%' . $q . '%'; $stP->bindValue(':q1', $like, PDO::PARAM_STR); $stP->bindValue(':q2', $like, PDO::PARAM_STR); $stP->bindValue(':q3', $like, PDO::PARAM_STR); $stP->bindValue(':q4', $like, PDO::PARAM_STR); }
        $stP->execute();
        $pedidosAll = $stP->fetchAll(PDO::FETCH_ASSOC);
        $totalP = count($pedidosAll);
        $offsetP = ($pageP - 1) * $perPage;
        $pedidos = array_slice($pedidosAll, $offsetP, $perPage);

        // Pedidos cerrados (Aprobado o Rechazado)
        $sqlPC = 'SELECT p.id AS pedido_id, p.created_at, COALESCE(p.estado,1) AS estado,
                         d.curso_id, c.nombre_curso, d.tipo, d.turno, d.asistentes, d.ubicacion
                  FROM inscripcion_pedidos p
                  INNER JOIN inscripcion_pedidos_detalle d ON d.pedido_id = p.id
                  LEFT JOIN cursos c ON c.id_curso = d.curso_id
                  WHERE p.usuario_id = :u
                    AND COALESCE(p.estado,1) IN (2,3)
                    AND (LOWER(d.tipo) = "certificacion" OR LOWER(d.tipo) = "certificaci��n")
                    ' . ($q !== '' ? ' AND (c.nombre_curso LIKE :q1 OR d.turno LIKE :q2 OR CAST(p.id AS CHAR) LIKE :q3 OR CAST(d.curso_id AS CHAR) LIKE :q4) ' : '') . '
                  ORDER BY p.created_at DESC, p.id DESC, d.id ASC';
        $stPC = $pdo->prepare($sqlPC);
        $stPC->bindValue(':u', $userId, PDO::PARAM_INT);
        if ($q !== '') { $like = '%' . $q . '%'; $stPC->bindValue(':q1', $like, PDO::PARAM_STR); $stPC->bindValue(':q2', $like, PDO::PARAM_STR); $stPC->bindValue(':q3', $like, PDO::PARAM_STR); $stPC->bindValue(':q4', $like, PDO::PARAM_STR); }
        $stPC->execute();
        $pedidosCerradosAll = $stPC->fetchAll(PDO::FETCH_ASSOC);
        $totalPC = count($pedidosCerradosAll);
        $offsetPC = ($pageC - 1) * $perPage;
        $pedidosCerrados = array_slice($pedidosCerradosAll, $offsetPC, $perPage);
    }
} catch (Throwable $e) {
    $error = 'No pudimos cargar las solicitudes: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/head.php'; ?>
<body class="config-page d-flex flex-column min-vh-100">
<?php include __DIR__ . '/nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="javascript:history.back();" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Solicitudes de Inscripciones</h1>
                    <p class="mb-0">Vista para RRHH</p>
                </div>
            </div>
        </div>
    </div>
    
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10">
                <?php if ($error !== null): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if ($solicitud !== null): ?>
                    <div class="config-card shadow mb-4 text-start">
                        <h5 class="mb-3">Detalle de solicitud #<?php echo (int)$solicitudId; ?></h5>
                        <p class="mb-1"><strong>Fecha:</strong> <?php echo htmlspecialchars((string)($solicitud['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mb-1"><strong>Pedido:</strong> #<?php echo (int)($solicitud['pedido_id'] ?? 0); ?></p>
                        <p class="mb-3"><strong>Curso:</strong> <?php echo htmlspecialchars((string)($solicitud['nombre_curso'] ?? $solicitud['curso_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Asistente</th>
                                        <th>DNI</th>
                                        <th>Email</th>
                                        <th>PDF</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($asistentes)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">Sin asistentes</td></tr>
                                <?php else: ?>
                                    <?php foreach ($asistentes as $a): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(trim(($a['nombre'] ?? '') . ' ' . ($a['apellido'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($a['dni'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($a['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php $pdf = (string)($a['pdf_path'] ?? ''); if ($pdf !== ''): ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($pdf, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                                        <i class="fas fa-file-pdf me-1"></i>Ver PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars((string)($a['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <a href="solicitudes_inscripciones.php" class="btn btn-outline-secondary btn-sm">Volver al listado</a>
                    </div>
                <?php else: ?>

                    <div class="config-card shadow mb-4 text-start">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-2 mb-3 gap-2">
                            <div>
                                <h5 class="mb-0">Solicitudes enviadas</h5>
                                <span class="text-muted small"><?php echo count($solicitudes); ?> registro(s)</span>
                            </div>
                        </div>

                        <?php if (empty($solicitudes)): ?>
                            <p class="mb-0 text-muted">No hay solicitudes realizadas.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-start">Fecha</th>
                                            <th class="text-start">Pedido</th>
                                            <th class="text-start">Turno</th>
                                            <th class="text-start">Curso</th>
                                            <th class="text-start">Estado</th>
                                            <th class="text-start">Asistentes</th>
                                            <th class="text-start">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($solicitudes as $s): ?>
                                        <tr>
                                            <td class="text-start"><?php echo htmlspecialchars((string)($s['creado_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start">#<?php echo (int)($s['pedido_id'] ?? 0); ?></td>
                                            <td class="text-start"><?php echo htmlspecialchars((string)($s['turno'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start"><?php echo htmlspecialchars((string)($s['nombre_curso'] ?? ($s['curso_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start"><?php $est=(int)($s['estado']??1); echo $est===1?'Pendiente':($est===2?'Aprobado':($est===3?'Rechazado':'Pendiente')); ?></td>
                                            <td class="text-start"><?php echo (int)($s['cant_asistentes'] ?? 0); ?></td>
                                            <td class="text-start">
                                                <a class="btn btn-sm btn-outline-primary me-1" href="solicitudes_inscripciones.php?solicitud=<?php echo (int)$s['id_solicitud']; ?>">Ver</a>
                                                <?php $solId = (int)($s['id_solicitud'] ?? 0); $pedidoId = (int)($s['pedido_id'] ?? 0); $cursoId = (int)($s['curso_id'] ?? 0); $cant = (int)($s['cant_asistentes'] ?? 0); $cant = $cant > 0 ? $cant : 1; ?>
                                                <a class="btn btn-sm btn-outline-success" href="validar_asistentes.php?solicitud_id=<?php echo $solId; ?>&pedido_id=<?php echo $pedidoId; ?>&curso_id=<?php echo $cursoId; ?>&asistentes=<?php echo $cant; ?>">Modificar</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="config-card shadow mb-4 text-start">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-2 mb-3 gap-2">
                            <div>
                                <h5 class="mb-0">Pedidos para Validación</h5>
                                <span class="text-muted small"><?php echo count($pedidos); ?> registro(s)</span>
                            </div>
                        </div>

                        <?php if (empty($pedidos)): ?>
                            <p class="mb-0 text-muted">No hay pedidos para validar.</p>
                        <?php else: ?>
                            <div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th class="text-start">Fecha</th>
                <th class="text-start">Pedido</th>
                <th class="text-start">Turno</th>
                <th class="text-start">Curso</th>
                <th class="text-start">Tipo</th>
                <th class="text-start">Estado</th>
                <th class="text-start">Asistentes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pedidos as $p): ?>
                <?php
                    $fecha = htmlspecialchars((string)($p['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $estado = (int)($p['estado'] ?? 1);
                    $estadoLabel = $estado === 2 ? 'Aprobado' : ($estado === 3 ? 'Rechazado' : 'Pendiente');
                    $pedidoId = (int)($p['pedido_id'] ?? 0);
                    $cursoId = (int)($p['curso_id'] ?? 0);
                    $asist = (int)($p['asistentes'] ?? 0);
                ?>
                <tr>
                    <td class="text-start"><?php echo $fecha; ?></td>
                    <td class="text-start">#<?php echo $pedidoId; ?></td>
                    <td class="text-start"><?php echo htmlspecialchars((string)($p['turno'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars((string)($p['nombre_curso'] ?? $cursoId), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars((string)($p['tipo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-start"><?php echo $estadoLabel; ?></td>
                    <td class="text-start"><?php echo $asist; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
                        <?php endif; ?>
                    </div>

                    <div class="config-card shadow mb-4 text-start">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-2 mb-3 gap-2">
                            <div>
                                <h5 class="mb-0">Pedidos Cerrados</h5>
                                <span class="text-muted small"><?php echo isset($pedidosCerrados) ? count($pedidosCerrados) : 0; ?> registro(s)</span>
                            </div>
                        </div>

                        <?php if (empty($pedidosCerrados)): ?>
                            <p class="mb-0 text-muted">No hay pedidos cerrados.</p>
                        <?php else: ?>
                            <div class="table-responsive">
    <table class="table table-sm align-middle">
        <thead class="table-light">
            <tr>
                <th class="text-start">Fecha</th>
                <th class="text-start">Pedido</th>
                <th class="text-start">Turno</th>
                <th class="text-start">Curso</th>
                <th class="text-start">Tipo</th>
                <th class="text-start">Estado</th>
                <th class="text-start">Asistentes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pedidosCerrados as $pc): ?>
                <?php
                    $fecha = htmlspecialchars((string)($pc['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $estado = (int)($pc['estado'] ?? 1);
                    $estadoLabel = $estado === 2 ? 'Aprobado' : ($estado === 3 ? 'Rechazado' : 'Pendiente');
                    $pedidoId = (int)($pc['pedido_id'] ?? 0);
                    $cursoId = (int)($pc['curso_id'] ?? 0);
                    $asist = (int)($pc['asistentes'] ?? 0);
                ?>
                <tr>
                    <td class="text-start"><?php echo $fecha; ?></td>
                    <td class="text-start">#<?php echo $pedidoId; ?></td>
                    <td class="text-start"><?php echo htmlspecialchars((string)($pc['turno'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars((string)($pc['nombre_curso'] ?? $cursoId), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-start"><?php echo htmlspecialchars((string)($pc['tipo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-start"><?php echo $estadoLabel; ?></td>
                    <td class="text-start"><?php echo $asist; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

                        <?php endif; ?>
                    </div>

                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
