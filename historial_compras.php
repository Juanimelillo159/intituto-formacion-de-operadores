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
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page > 0 ? $page : 1;
$perPage = 5;

$page_title = 'Historial de compras | Instituto de Formacion';
$page_description = 'Compras realizadas con tu cuenta del Instituto.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';

$formatMoney = static function (float $amount, ?string $currency = null): string {
    $formatted = number_format($amount, 2, ',', '.');
    $currencyCode = trim((string)$currency);
    return $currencyCode !== '' ? $currencyCode . ' ' . $formatted : $formatted;
};

$capacitaciones = [];
$certificaciones = [];
$combinado = [];
$pedidos = [];
$permiso = null;
$errorMessage = null;

try {
    $pdo = getPdo();

    // Permiso del usuario (2 = común, 3 = empresa)
    try {
        $stPerm = $pdo->prepare('SELECT id_permiso FROM usuarios WHERE id_usuario = :id LIMIT 1');
        $stPerm->bindValue(':id', $userId, PDO::PARAM_INT);
        $stPerm->execute();
        $permiso = (int)($stPerm->fetchColumn() ?: 0);
    } catch (Throwable $ePerm) {
        $permiso = null;
    }

    // Si es empresa (permiso = 3), mostrar solo pedidos de inscripciones
    if ($permiso === 3) {
        // Pedidos de inscripciones
        $stP = $pdo->prepare('SELECT id, created_at, COALESCE(estado,1) AS estado FROM inscripcion_pedidos WHERE usuario_id = :u ORDER BY created_at DESC, id DESC');
        $stP->bindValue(':u', $userId, PDO::PARAM_INT);
        $stP->execute();
        $rows = $stP->fetchAll(PDO::FETCH_ASSOC);

        if ($rows) {
            foreach ($rows as $r) {
                $pid = (int)($r['id'] ?? 0);
                $pedidos[$pid] = [
                    'id' => $pid,
                    'fecha' => $r['created_at'] ?? null,
                    'estado' => isset($r['estado']) ? (int)$r['estado'] : 1,
                    'detalles' => [],
                ];
            }

            $ids = implode(',', array_map('intval', array_keys($pedidos)));
            if ($ids !== '') {
                $sqlD = "SELECT d.pedido_id, d.curso_id, c.nombre_curso, d.tipo, d.turno, d.asistentes, d.ubicacion, d.created_at FROM inscripcion_pedidos_detalle d LEFT JOIN cursos c ON c.id_curso = d.curso_id WHERE d.pedido_id IN ($ids) ORDER BY d.pedido_id, d.id";
                $stD = $pdo->query($sqlD);
                while ($d = $stD->fetch(PDO::FETCH_ASSOC)) {
                    $pid = (int)($d['pedido_id'] ?? 0);
                    if (isset($pedidos[$pid])) {
                        $pedidos[$pid]['detalles'][] = $d;
                    }
                }
            }
        }
    } else {
        // CAPACITACIONES
        $sqlCap = <<<SQL
        SELECT
            cc.id_capacitacion            AS id_registro,
            cc.creado_en                  AS fecha,
            cc.precio_total               AS total,
            cc.moneda                     AS moneda,
            cc.id_curso                   AS id_curso,
            cc.id_estado                  AS id_estado,
            c.nombre_curso                AS nombre_curso,
            ei.nombre_estado              AS estado_label
        FROM checkout_capacitaciones cc
        LEFT JOIN cursos c ON c.id_curso = cc.id_curso
        LEFT JOIN estados_inscripciones ei ON ei.id_estado = cc.id_estado
        WHERE cc.creado_por = :user
        ORDER BY cc.creado_en DESC, cc.id_capacitacion DESC
    SQL;

    $stmtCap = $pdo->prepare($sqlCap);
    $stmtCap->bindValue(':user', $userId, PDO::PARAM_INT);
    $stmtCap->execute();

    while ($row = ($permiso === 3 ? false : $stmtCap->fetch(PDO::FETCH_ASSOC))) {
        $fechaRaw = $row['fecha'] ?? null;
        $fechaFmt = $fechaRaw;
        if (!empty($fechaRaw)) {
            try { $fechaFmt = (new DateTimeImmutable($fechaRaw))->format('d/m/Y H:i'); } catch (Throwable $e) {}
        }

        $item = [
            'tipo'            => 'capacitacion',
            'id'              => (int)$row['id_registro'],
            'fecha'           => $fechaRaw,
            'fecha_formatted' => $fechaFmt,
            'total'           => (float)($row['total'] ?? 0),
            'moneda'          => $row['moneda'] ?? '',
            'curso'           => $row['nombre_curso'] ?? 'Curso',
            'estado_label'    => $row['estado_label'] ?? null,
            'id_estado'       => $row['id_estado'] ?? null,
        ];
        $capacitaciones[] = $item;
        $combinado[] = $item;
    }

    // CERTIFICACIONES
        $sqlCert = <<<SQL
        SELECT
            ct.id_certificacion           AS id_registro,
            ct.creado_en                  AS fecha,
            ct.precio_total               AS total,
            ct.moneda                     AS moneda,
            ct.id_curso                   AS id_curso,
            ct.id_estado                  AS id_estado,
            ct.pdf_path                   AS pdf_path,
            ct.pdf_nombre                 AS pdf_nombre,
            c.nombre_curso                AS nombre_curso,
            ei.nombre_estado              AS estado_label
        FROM checkout_certificaciones ct
        LEFT JOIN cursos c ON c.id_curso = ct.id_curso
        LEFT JOIN estados_inscripciones ei ON ei.id_estado = ct.id_estado
        WHERE ct.creado_por = :user
        ORDER BY ct.creado_en DESC, ct.id_certificacion DESC
    SQL;

    $stmtCert = $pdo->prepare($sqlCert);
    $stmtCert->bindValue(':user', $userId, PDO::PARAM_INT);
    $stmtCert->execute();

    while ($row = ($permiso === 3 ? false : $stmtCert->fetch(PDO::FETCH_ASSOC))) {
        $fechaRaw = $row['fecha'] ?? null;
        $fechaFmt = $fechaRaw;
        if (!empty($fechaRaw)) {
            try { $fechaFmt = (new DateTimeImmutable($fechaRaw))->format('d/m/Y H:i'); } catch (Throwable $e) {}
        }

        $certId = (int)($row['id_registro'] ?? 0);
        $item = [
            'tipo'            => 'certificacion',
            'id'              => $certId,
            'fecha'           => $fechaRaw,
            'fecha_formatted' => $fechaFmt,
            'total'           => (float)($row['total'] ?? 0),
            'moneda'          => $row['moneda'] ?? '',
            'curso'           => $row['nombre_curso'] ?? 'Curso',
            'estado_label'    => $row['estado_label'] ?? null,
            'id_estado'       => $row['id_estado'] ?? null,
            'pdf_path'        => $row['pdf_path'] ?? null,
            'pdf_nombre'      => $row['pdf_nombre'] ?? null,
            'certificacion_registro' => $certId,
        ];
        $certificaciones[] = $item;
        $combinado[] = $item;
    }
    }

    // Orden combinado por fecha DESC
    usort($combinado, static function($a, $b) {
        $fa = $a['fecha'] ?? '';
        $fb = $b['fecha'] ?? '';
        if ($fa === $fb) return 0;
        if ($fa === '' || $fa === null) return 1;
        if ($fb === '' || $fb === null) return -1;
        return strcmp($fb, $fa);
    });

} catch (Throwable $exception) {
    $errorMessage = 'No pudimos cargar tu historial en este momento.';
}

$compras = $combinado;
$totalCompras = count($compras);
$totalPages = (int)ceil($totalCompras / $perPage);
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$paginatedCompras = $totalCompras > 0 ? array_slice($compras, $offset, $perPage) : [];
$scriptName = basename((string)($_SERVER['PHP_SELF'] ?? 'historial_compras.php'));
?>
<!DOCTYPE html>
<html lang="es">
<?php $page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">'; include 'head.php'; ?>
<body class="config-page d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="index.php" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver al inicio</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Historial de compras</h1>
                    <p class="mb-0">Capacitaciones y certificaciones adquiridas con tu cuenta.</p>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-8">

                <?php if ($errorMessage !== null): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-0"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>

                <?php elseif ($permiso === 3): ?>
                    <?php
                        $estadoMap = [1 => 'Pendiente', 2 => 'Aprobado', 3 => 'Rechazado'];
                        $pedidoDetalleId = isset($_GET['pedido']) ? (int)$_GET['pedido'] : 0;
                        $pedidoDetalle = ($pedidoDetalleId > 0 && isset($pedidos[$pedidoDetalleId])) ? $pedidos[$pedidoDetalleId] : null;
                    ?>

                    <?php if ($pedidoDetalle): ?>
                        <div class="config-card shadow mb-4 text-start" id="pedido">
                            <h5 class="mb-3">Detalle del pedido #<?php echo $pedidoDetalleId; ?></h5>
                            <p class="mb-1"><strong>Fecha:</strong> <?php echo htmlspecialchars((string)($pedidoDetalle['fecha'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mb-3"><strong>Estado:</strong> <?php echo htmlspecialchars($estadoMap[$pedidoDetalle['estado']] ?? 'Pendiente', ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Curso</th>
                                            <th>Tipo</th>
                                            <th>Turno</th>
                                            <th>Asistentes</th>
                                            <th>Ubicación</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach (($pedidoDetalle['detalles'] ?? []) as $d): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($d['nombre_curso'] ?? ($d['curso_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($d['tipo'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($d['turno'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($d['asistentes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($d['ubicacion'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="historial_compras.php" class="btn btn-outline-secondary btn-sm">Volver al listado</a>
                        </div>
                    <?php endif; ?>

                    <div class="config-card shadow mb-4 text-start">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-2 mb-3 gap-2">
                            <div>
                                <h5 class="mb-0">Pedidos de Inscripciones</h5>
                                <span class="text-muted small"><?php echo count($pedidos); ?> registro(s)</span>
                            </div>
                        </div>
                        <?php if (empty($pedidos)): ?>
                            <p class="mb-0 text-muted">No hay pedidos realizados.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light"><tr><th class="text-start">Fecha</th><th class="text-start">Pedido</th><th class="text-start">Estado</th><th class="text-start">Acciones</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($pedidos as $p): ?>
                                        <tr>
                                            <td class="text-start"><?php echo htmlspecialchars($p['fecha'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start">#<?php echo (int)$p['id']; ?></td>
                                            <td class="text-start"><?php echo htmlspecialchars($estadoMap[$p['estado']] ?? 'Pendiente', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start"><a class="btn btn-sm btn-outline-primary" href="historial_compras.php?pedido=<?php echo (int)$p['id']; ?>#pedido">Ver detalle</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif (empty($combinado)): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-4">Todav&iacute;a no registramos compras.</p>
                        <a class="btn btn-gradient" href="index.php#cursos">Explorar cursos disponibles</a>
                    </div>

                <?php else: ?>


                    <!-- Vista combinada -->
                    <div class="config-card shadow mb-4 text-start">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-2 mb-3 gap-2">
                            <div>
                                <h5 class="mb-0">Movimientos recientes</h5>
                                <span class="text-muted small">Ordenado por fecha</span>

                            </div>
                            <div class="w-100 w-md-auto">
                                <input id="recentSearch" type="text" class="form-control form-control-sm" placeholder="Buscar curso...">
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="recentTable" class="table table-sm align-middle">
                                <thead class="table-light">
                                <tr>
                                    <th class="text-start">Fecha</th>
                                    <th class="text-start">Tipo</th>
                                    <th class="text-start">Curso</th>
                                    <th class="text-start">Estado</th>
                                    <th class="text-center">Acciones</th>
                                    <th class="text-end">Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($combinado as $row): ?>
                                    <?php $totalLabel = $formatMoney($row['total'], $row['moneda']); ?>
                                    <tr>
                                        <td class="text-start"><?php echo htmlspecialchars($row['fecha_formatted'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-start"><?php echo $row['tipo'] === 'capacitacion' ? 'Capacitaci&oacute;n' : 'Certificaci&oacute;n'; ?></td>
                                        <td class="text-start"><?php echo htmlspecialchars($row['curso'] ?? 'Curso', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-start"><?php echo htmlspecialchars($row['estado_label'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-center">
                                            <?php if (($row['tipo'] ?? '') === 'certificacion'): ?>
                                                <?php
                                                    $estadoCert = isset($row['id_estado']) ? (int)$row['id_estado'] : 0;
                                                    $certRegistro = isset($row['certificacion_registro']) ? (int)$row['certificacion_registro'] : 0;
                                                    $cursoId = isset($row['id_curso']) ? (int)$row['id_curso'] : 0;
                                                ?>
                                                <div class="d-flex flex-column flex-md-row gap-2 justify-content-center">
                                                    <?php if ($certRegistro > 0): ?>
                                                        <a class="btn btn-sm btn-outline-primary" href="checkout/gracias_certificacion.php?certificacion=<?php echo $certRegistro; ?>">
                                                            Ver estado solicitud
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($estadoCert === 2 && $cursoId > 0 && $certRegistro > 0): ?>
                                                        <a class="btn btn-sm btn-gradient" href="checkout/checkout.php?tipo=certificacion&amp;id_curso=<?php echo $cursoId; ?>&amp;certificacion_registro=<?php echo $certRegistro; ?>">
                                                            Pagar certificación
                                                        </a>
                                                    <?php elseif ($estadoCert === 3): ?>
                                                        <span class="badge bg-success align-self-center">Pagado</span>
                                                    <?php else: ?>
                                                        <span class="text-muted align-self-center">En revisión</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif (($row['tipo'] ?? '') === 'capacitacion'): ?>
                                                <?php $ordenId = isset($row['id']) ? (int)$row['id'] : 0; ?>
                                                <?php if ($ordenId > 0): ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="checkout/gracias.php?<?php echo http_build_query(['tipo' => 'capacitacion', 'orden' => $ordenId]); ?>">
                                                        Ver estado
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo htmlspecialchars($totalLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div id="recentPager" class="d-flex justify-content-between align-items-center mt-2"></div>
                    </div>

                    <!-- Capacitaciones -->
                    <div class="config-card shadow mb-4 text-start">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-2 mb-3 gap-2">
                            <div>
                                <h5 class="mb-0">Capacitaciones</h5>
                                <span class="text-muted small"><?php echo count($capacitaciones); ?> registro(s)</span>
                            </div>
                            <div class="w-100 w-md-auto">
                                <input id="capSearch" type="text" class="form-control form-control-sm" placeholder="Buscar curso...">
                            </div>
                        </div>

                        <?php if (empty($capacitaciones)): ?>
                            <p class="mb-0 text-muted">No hay compras de capacitaciones.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="capTable" class="table table-sm align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-start">Fecha</th>
                                        <th class="text-start">Curso</th>
                                        <th class="text-start">Estado</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($capacitaciones as $cap): ?>
                                        <?php $totalLabel = $formatMoney($cap['total'], $cap['moneda']); ?>
                                        <tr>
                                            <td class="text-start"><?php echo htmlspecialchars($cap['fecha_formatted'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start"><?php echo htmlspecialchars($cap['curso'] ?? 'Curso', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start"><?php echo htmlspecialchars($cap['estado_label'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-end"><?php echo htmlspecialchars($totalLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div id="capPager" class="d-flex justify-content-between align-items-center mt-2"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Certificaciones -->
                    <div class="config-card shadow mb-4 text-start">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center border-bottom pb-2 mb-3 gap-2">
                            <div>
                                <h5 class="mb-0">Certificaciones</h5>
                                <span class="text-muted small"><?php echo count($certificaciones); ?> registro(s)</span>
                            </div>
                            <div class="w-100 w-md-auto">
                                <input id="certSearch" type="text" class="form-control form-control-sm" placeholder="Buscar curso...">
                            </div>
                        </div>

                        <?php if (empty($certificaciones)): ?>
                            <p class="mb-0 text-muted">No hay compras de certificaciones.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="certTable" class="table table-sm align-middle">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-start">Fecha</th>
                                        <th class="text-start">Curso</th>
                                        <th class="text-start">Estado</th>
                                        <th class="text-start">PDF</th>
                                        <th class="text-start">Acciones</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($certificaciones as $cert): ?>
                                        <?php $totalLabel = $formatMoney($cert['total'], $cert['moneda']); ?>
                                        <tr>
                                            <td class="text-start"><?php echo htmlspecialchars($cert['fecha_formatted'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start"><?php echo htmlspecialchars($cert['curso'] ?? 'Curso', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start"><?php echo htmlspecialchars($cert['estado_label'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-start">
                                                <?php if (!empty($cert['pdf_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($cert['pdf_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                                        <?php echo htmlspecialchars($cert['pdf_nombre'] ?? 'Archivo', ENT_QUOTES, 'UTF-8'); ?>
                                                    </a>
                                                <?php else: ?>
                                                    —
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-start">
                                                <?php
                                                    $estadoCert = isset($cert['id_estado']) ? (int)$cert['id_estado'] : 0;
                                                    $certRegistro = isset($cert['certificacion_registro']) ? (int)$cert['certificacion_registro'] : 0;
                                                    $cursoId = isset($cert['id_curso']) ? (int)$cert['id_curso'] : 0;
                                                ?>
                                                <div class="d-flex flex-column flex-md-row gap-2">
                                                    <?php if ($certRegistro > 0): ?>
                                                        <a class="btn btn-sm btn-outline-primary" href="checkout/gracias_certificacion.php?certificacion=<?php echo $certRegistro; ?>">
                                                            Ver estado solicitud
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($estadoCert === 2 && $cursoId > 0 && $certRegistro > 0): ?>
                                                        <a class="btn btn-sm btn-gradient" href="checkout/checkout.php?tipo=certificacion&amp;id_curso=<?php echo $cursoId; ?>&amp;certificacion_registro=<?php echo $certRegistro; ?>">
                                                            Pagar certificación
                                                        </a>
                                                    <?php elseif ($estadoCert === 3): ?>
                                                        <span class="badge bg-success align-self-center">Pagado</span>
                                                    <?php else: ?>
                                                        <span class="text-muted align-self-center">En revisión</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-end"><?php echo htmlspecialchars($totalLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div id="certPager" class="d-flex justify-content-between align-items-center mt-2"></div>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Paginación + filtro por curso (en vivo) sin recargar
(function() {
    function setupPagerWithFilter(opts) {
        var table = document.getElementById(opts.tableId);
        var pager = document.getElementById(opts.pagerId);
        var input = document.getElementById(opts.searchInputId);
        var pageSize = opts.pageSize || 10;
        var courseColIndex = opts.courseColIndex; // índice de columna "Curso"

        if (!table || !pager || courseColIndex == null) return;

        var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));
        var page = 1;

        // Fila "sin resultados"
        var noRow = document.createElement('tr');
        noRow.className = 'no-results';
        var thCount = table.querySelectorAll('thead th').length || 1;
        var td = document.createElement('td');
        td.colSpan = thCount;
        td.className = 'text-center text-muted';
        td.textContent = 'Sin resultados';
        noRow.appendChild(td);
        noRow.style.display = 'none';
        table.querySelector('tbody').appendChild(noRow);

        function getMatchedRows() {
            var term = (input && input.value ? input.value.trim().toLowerCase() : '');
            if (!term) return rows; // sin filtro
            return rows.filter(function(tr) {
                if (tr.classList.contains('no-results')) return false;
                var cells = tr.cells;
                if (!cells || !cells[courseColIndex]) return false;
                var txt = (cells[courseColIndex].textContent || '').toLowerCase();
                return txt.indexOf(term) !== -1;
            });
        }

        function render() {
            var matched = getMatchedRows();
            var total = matched.length;
            var totalPages = Math.max(1, Math.ceil(total / pageSize));

            // Ajustar página si el filtro cambió y quedó fuera de rango
            if (page > totalPages) page = totalPages;

            // Ocultar todo
            rows.forEach(function(tr) { tr.style.display = 'none'; });
            noRow.style.display = (total === 0) ? '' : 'none';

            // Mostrar página actual del subconjunto filtrado
            if (total > 0) {
                var start = (page - 1) * pageSize;
                var end = Math.min(start + pageSize, total);
                for (var i = start; i < end; i++) {
                    matched[i].style.display = '';
                }
            }

            // Paginador
            if (total <= pageSize) {
                pager.style.display = 'none';
                pager.innerHTML = '';
                return;
            } else {
                pager.style.display = 'flex';
            }

            var prev = document.createElement('button');
            prev.className = 'btn btn-sm btn-outline-secondary';
            prev.textContent = 'Anterior';
            prev.disabled = page <= 1;
            prev.addEventListener('click', function() {
                if (page > 1) { page--; render(); }
            });

            var info = document.createElement('span');
            info.className = 'text-muted small';
            var baseInfo = 'Página ' + page + ' de ' + totalPages + ' · ' + total + ' resultado(s)';
            if (input && input.value) {
                info.textContent = baseInfo + ' para "' + input.value + '"';
            } else {
                info.textContent = baseInfo;
            }

            var next = document.createElement('button');
            next.className = 'btn btn-sm btn-outline-secondary';
            next.textContent = 'Siguiente';
            next.disabled = page >= totalPages;
            next.addEventListener('click', function() {
                if (page < totalPages) { page++; render(); }
            });

            pager.className = 'd-flex justify-content-between align-items-center mt-2';
            pager.innerHTML = '';
            var left = document.createElement('div'); left.appendChild(prev);
            var right = document.createElement('div'); right.appendChild(next);
            pager.appendChild(left);
            pager.appendChild(info);
            pager.appendChild(right);
        }

        if (input) {
            input.addEventListener('input', function() {
                page = 1; // reset al cambiar el filtro
                render();
            });
        }

        render();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Índices de columna "Curso":
        // recentTable: Fecha(0), Tipo(1), Curso(2), Estado(3), Acciones(4), Total(5) => curso = 2
        // capTable:    Fecha(0), Curso(1), Estado(2), Total(3)                     => curso = 1
        // certTable:   Fecha(0), Curso(1), Estado(2), PDF(3), Acciones(4), Total(5) => curso = 1
        setupPagerWithFilter({ tableId: 'recentTable', pagerId: 'recentPager', searchInputId: 'recentSearch', pageSize: 10, courseColIndex: 2 });
        setupPagerWithFilter({ tableId: 'capTable',    pagerId: 'capPager',    searchInputId: 'capSearch',    pageSize: 10, courseColIndex: 1 });
        setupPagerWithFilter({ tableId: 'certTable',   pagerId: 'certPager',   searchInputId: 'certSearch',   pageSize: 10, courseColIndex: 1 });
    });
})();
</script>
</body>
</html>
