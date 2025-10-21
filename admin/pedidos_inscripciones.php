<?php
require_once '../sbd.php';

// Asegurar columna estado (1=Pendiente, 2=Aprobado, 3=Rechazado)
try {
    $con->query('SELECT estado FROM inscripcion_pedidos LIMIT 1');
} catch (Throwable $e) {
    try {
        $con->exec('ALTER TABLE inscripcion_pedidos ADD COLUMN estado INT NOT NULL DEFAULT 1');
    } catch (Throwable $e2) {
        // ignorar si falla
    }
}

// Manejo de cambios de estado (antes de cualquier salida)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $pedidoId = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;
        $estado   = isset($_POST['estado']) ? (int)$_POST['estado'] : 0;
        if ($pedidoId <= 0 || !in_array($estado, [1,2,3], true)) {
            throw new InvalidArgumentException('Datos inválidos.');
        }
        $st = $con->prepare('UPDATE inscripcion_pedidos SET estado = :e WHERE id = :id');
        $st->execute([':e' => $estado, ':id' => $pedidoId]);
        $_SESSION['pedidos_admin_ok'] = 'Estado actualizado correctamente';
    } catch (Throwable $ex) {
        $_SESSION['pedidos_admin_err'] = $ex->getMessage();
    }
    header('Location: pedidos_inscripciones.php');
    exit;
}

include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

// Utilidad simple para escapar HTML
function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

$flashOk = $_SESSION['pedidos_admin_ok'] ?? null;
$flashErr = $_SESSION['pedidos_admin_err'] ?? null;
unset($_SESSION['pedidos_admin_ok'], $_SESSION['pedidos_admin_err']);

// Cargar pedidos + detalles
$estadoMap = [
    1 => ['label' => 'Pendiente', 'class' => 'badge bg-warning'],
    2 => ['label' => 'Aprobado',  'class' => 'badge bg-success'],
    3 => ['label' => 'Rechazado', 'class' => 'badge bg-danger'],
];

$pedidos = [];
try {
    $con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $q = $con->query('SELECT p.id, p.usuario_id, u.email AS usuario_email, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido, p.created_at, COALESCE(p.estado,1) AS estado FROM inscripcion_pedidos p LEFT JOIN usuarios u ON u.id_usuario = p.usuario_id ORDER BY p.created_at DESC, p.id DESC');
    while ($row = $q->fetch()) {
        $row['id'] = (int)$row['id'];
        $row['usuario_id'] = (int)($row['usuario_id'] ?? 0);
        $row['estado'] = (int)$row['estado'];
        $pedidos[$row['id']] = $row;
    }
    if ($pedidos) {
        $ids = implode(',', array_map('intval', array_keys($pedidos)));
        $dq = $con->query("SELECT d.id, d.pedido_id, d.curso_id, c.nombre_curso AS curso_nombre, d.tipo, d.turno, d.asistentes, d.ubicacion, d.created_at FROM inscripcion_pedidos_detalle d LEFT JOIN cursos c ON c.id_curso = d.curso_id WHERE d.pedido_id IN ($ids) ORDER BY d.pedido_id, d.id");
        while ($d = $dq->fetch()) {
            $pid = (int)($d['pedido_id'] ?? 0);
            if (!isset($pedidos[$pid]['detalles'])) {
                $pedidos[$pid]['detalles'] = [];
            }
            $pedidos[$pid]['detalles'][] = $d;
        }
    }
} catch (Throwable $e) {
    $flashErr = $flashErr ?: $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos de Inscripción | Panel Administrativo</title>
    <style>
        .detalle-item { font-size: 0.9rem; }
        .acciones .btn { margin-right: .25rem; }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h3 class="card-title">Pedidos de inscripción</h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($flashOk)): ?>
                                    <div class="alert alert-success"><?php echo h($flashOk); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($flashErr)): ?>
                                    <div class="alert alert-danger"><?php echo h($flashErr); ?></div>
                                <?php endif; ?>

                                <div class="table-responsive">
                                    <table id="tablaPedidosInscripcion" class="table table-striped table-bordered table-sm">
                                        <thead class="table-light">
                                        <tr>
                                            <th style="width: 70px;">ID</th>
                                            <th>Solicitante</th>
                                            <th>Email</th>
                                            <th>Estado</th>
                                            <th>Enviado</th>
                                            <th>Detalles</th>
                                            <th style="width: 160px;">Acciones</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($pedidos as $p): ?>
                                            <?php
                                            $estado = (int)($p['estado'] ?? 1);
                                            $estadoInfo = $estadoMap[$estado] ?? ['label' => 'Pendiente', 'class' => 'badge bg-secondary'];
                                            $usuario = (int)($p['usuario_id'] ?? 0);
                                            $fecha = (string)($p['created_at'] ?? '');
                                            ?>
                                            <tr>
                                                <td><?php echo (int)$p['id']; ?></td>
                                                <td>
                                                    <?php
                                                    $nombre = trim((string)($p['usuario_nombre'] ?? ''));
                                                    $apellido = trim((string)($p['usuario_apellido'] ?? ''));
                                                    $full = trim($nombre . ' ' . $apellido);
                                                    if ($full !== '') {
                                                        echo h($full);
                                                    } elseif ($usuario > 0) {
                                                        echo 'Usuario #' . (int)$usuario;
                                                    } else {
                                                        echo '<span class=\'text-muted\'>—</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $email = trim((string)($p['usuario_email'] ?? ''));
                                                    if ($email !== '') {
                                                        echo h($email);
                                                    } elseif ($usuario > 0) {
                                                        echo 'Usuario #' . (int)$usuario;
                                                    } else {
                                                        echo '<span class="text-muted">—</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td><span class="<?php echo h($estadoInfo['class']); ?>"><?php echo h($estadoInfo['label']); ?></span></td>
                                                <td><?php echo h($fecha); ?></td>
                                                <td>
                                                    <?php if (!empty($p['detalles'])): ?>
                                                        <?php foreach ($p['detalles'] as $d): ?>
                                                            <div class="detalle-item">
                                                                <?php $cursoNombre = trim((string)($d['curso_nombre'] ?? '')); $cursoId = (string)($d['curso_id'] ?? ''); ?>
                                                                <strong>Curso:</strong> <?php echo $cursoNombre !== '' ? h($cursoNombre) : h($cursoId); ?>
                                                                <span class="mx-2">|</span>
                                                                <strong>Tipo:</strong> <?php echo h((string)($d['tipo'] ?? '')); ?>
                                                                <span class="mx-2">|</span>
                                                                <strong>Turno:</strong> <?php echo h((string)($d['turno'] ?? '')); ?>
                                                                <span class="mx-2">|</span>
                                                                <strong>Asistentes:</strong> <?php echo h((string)($d['asistentes'] ?? '')); ?>
                                                                <span class="mx-2">|</span>
                                                                <strong>Ubicación:</strong> <?php echo h((string)($d['ubicacion'] ?? '')); ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin detalles</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php $puedeAprobar = in_array($estado, [1,3], true); $puedeRechazar = in_array($estado, [1,2], true); ?>
                                                    <div class="d-flex flex-column gap-2">
                                                        <?php if ($puedeAprobar): ?>
                                                        <button type="button" class="btn btn-success btn-sm btn-aprobar" data-id="<?php echo (int)$p['id']; ?>">
                                                            <i class="fas fa-check me-1"></i>Aprobar
                                                        </button>
                                                        <?php endif; ?>
                                                        <?php if ($puedeRechazar): ?>
                                                        <button type="button" class="btn btn-danger btn-sm btn-rechazar" data-id="<?php echo (int)$p['id']; ?>">
                                                            <i class="fas fa-times me-1"></i>Rechazar
                                                        </button>
                                                        <?php endif; ?>
                                                        <?php if (!$puedeAprobar && !$puedeRechazar): ?>
                                                            <span class="text-muted small">Sin acciones disponibles</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <form id="formPedido<?php echo (int)$p['id']; ?>" action="pedidos_inscripciones.php" method="POST" class="d-none">
                                                        <input type="hidden" name="pedido_id" value="<?php echo (int)$p['id']; ?>">
                                                        <input type="hidden" name="estado" value="1">
                                                        <input type="hidden" name="motivo" value="">
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        const table = $("#tablaPedidosInscripcion").DataTable({
            responsive: true,
            lengthChange: true,
            autoWidth: false,
            order: [[0, 'desc']],
            language: { url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json" },
            buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"]
        });
        table.buttons().container().appendTo('#tablaPedidosInscripcion_wrapper .col-md-6:eq(0)');
    } catch (e) { /* no-op */ }
    // Acciones estilo certificaciones
    document.querySelectorAll('.btn-aprobar').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const form = document.getElementById('formPedido' + id);
            if (!form) return;
            if (!confirm('¿Confirmás la aprobación de este pedido?')) return;
            form.querySelector('input[name="estado"]').value = '2';
            form.submit();
        });
    });
    document.querySelectorAll('.btn-rechazar').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const form = document.getElementById('formPedido' + id);
            if (!form) return;
            const reason = prompt('Indicá el motivo del rechazo (opcional):', '');
            if (reason === null) return;
            if (!confirm('¿Confirmás el rechazo de este pedido?')) return;
            form.querySelector('input[name="estado"]').value = '3';
            const motivo = form.querySelector('input[name="motivo"]');
            if (motivo) motivo.value = (reason || '').trim();
            form.submit();
        });
    });
});
</script>
</body>
</html>
