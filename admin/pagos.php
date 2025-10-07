<?php
require_once '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$pagosStmt = $con->prepare(
    "SELECT p.*,\n"
    . "       CASE\n"
    . "           WHEN p.id_capacitacion IS NOT NULL THEN 'capacitacion'\n"
    . "           WHEN p.id_certificacion IS NOT NULL THEN 'certificacion'\n"
    . "           ELSE 'curso'\n"
    . "       END AS tipo_checkout,\n"
    . "       COALESCE(cap.nombre, cert.nombre) AS alumno_nombre,\n"
    . "       COALESCE(cap.apellido, cert.apellido) AS alumno_apellido,\n"
    . "       COALESCE(cap.email, cert.email) AS alumno_email,\n"
    . "       COALESCE(cap.telefono, cert.telefono) AS alumno_telefono,\n"
    . "       COALESCE(cur_cap.nombre_curso, cur_cert.nombre_curso, '') AS curso_nombre\n"
    . "  FROM checkout_pagos p\n"
    . "  LEFT JOIN checkout_capacitaciones cap ON p.id_capacitacion = cap.id_capacitacion\n"
    . "  LEFT JOIN checkout_certificaciones cert ON p.id_certificacion = cert.id_certificacion\n"
    . "  LEFT JOIN cursos cur_cap ON cap.id_curso = cur_cap.id_curso\n"
    . "  LEFT JOIN cursos cur_cert ON cert.id_curso = cur_cert.id_curso\n"
    . " WHERE p.metodo = 'transferencia'\n"
    . " ORDER BY (p.estado = 'pendiente') DESC, p.creado_en DESC"
);
$pagosStmt->execute();
$pagos = $pagosStmt->fetchAll(PDO::FETCH_ASSOC);

$successMessage = $_SESSION['pagos_admin_success'] ?? null;
$warningMessage = $_SESSION['pagos_admin_warning'] ?? null;
$errorMessage = $_SESSION['pagos_admin_error'] ?? null;
unset($_SESSION['pagos_admin_success'], $_SESSION['pagos_admin_warning'], $_SESSION['pagos_admin_error']);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pago_estado_badge(string $estado): string
{
    $estado = strtolower($estado);
    return match ($estado) {
        'pagado' => '<span class="badge bg-success">Aprobado</span>',
        'rechazado' => '<span class="badge bg-danger">Rechazado</span>',
        'cancelado' => '<span class="badge bg-secondary">Cancelado</span>',
        default => '<span class="badge bg-warning text-dark">Pendiente</span>',
    };
}

function pago_tipo_label(string $tipo): string
{
    return $tipo === 'certificacion'
        ? 'Certificación'
        : ($tipo === 'capacitacion' ? 'Capacitación' : 'Curso');
}

function pago_format_currency(?float $amount, ?string $currency): string
{
    $amount = $amount ?? 0.0;
    $currency = strtoupper(trim((string)$currency));
    $symbol = '$';
    if ($currency === 'USD') {
        $symbol = 'US$';
    } elseif ($currency === 'EUR') {
        $symbol = '€';
    }
    return sprintf('%s %s', $symbol, number_format($amount, 2, ',', '.'));
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos por transferencia | Panel Administrativo</title>
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
                                    <h3 class="card-title">Pagos por transferencia</h3>
                                </div>
                                <div class="card-body">
                                    <?php if ($successMessage): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="fas fa-circle-check me-2"></i><?php echo h($successMessage); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($warningMessage): ?>
                                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                            <i class="fas fa-triangle-exclamation me-2"></i><?php echo h($warningMessage); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($errorMessage): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="fas fa-circle-xmark me-2"></i><?php echo h($errorMessage); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                        <table id="tablaPagos" class="table table-bordered table-striped align-middle">
                                            <thead>
                                                <tr>
                                                    <th style="width: 70px">#</th>
                                                    <th>Fecha</th>
                                                    <th>Tipo</th>
                                                    <th>Curso</th>
                                                    <th>Alumno</th>
                                                    <th>Monto</th>
                                                    <th>Estado</th>
                                                    <th>Observaciones</th>
                                                    <th>Comprobante</th>
                                                    <th style="width: 180px">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pagos as $pago): ?>
                                                    <?php
                                                    $pagoId = (int)$pago['id_pago'];
                                                    $fecha = '';
                                                    if (!empty($pago['creado_en'])) {
                                                        $timestamp = strtotime((string)$pago['creado_en']);
                                                        if ($timestamp) {
                                                            $fecha = date('d/m/Y H:i', $timestamp);
                                                        }
                                                    }
                                                    $alumno = trim((string)($pago['alumno_nombre'] ?? ''));
                                                    $apellido = trim((string)($pago['alumno_apellido'] ?? ''));
                                                    if ($apellido !== '') {
                                                        $alumno = $alumno !== '' ? $alumno . ' ' . $apellido : $apellido;
                                                    }
                                                    if ($alumno === '') {
                                                        $alumno = $pago['alumno_email'] ?? '—';
                                                    }
                                                    $estado = strtolower((string)($pago['estado'] ?? 'pendiente'));
                                                    $comprobantePath = trim((string)($pago['comprobante_path'] ?? ''));
                                                    $comprobanteLabel = trim((string)($pago['comprobante_nombre'] ?? 'Ver archivo'));
                                                    $tipo = (string)($pago['tipo_checkout'] ?? 'curso');
                                                    $puedeGestionar = ($estado === 'pendiente');
                                                    $observaciones = trim((string)($pago['observaciones'] ?? ''));
                                                    $observacionesResumen = $observaciones;
                                                    if ($observaciones !== '') {
                                                        if (function_exists('mb_strlen')) {
                                                            $observacionesResumen = mb_strlen($observaciones, 'UTF-8') > 60
                                                                ? mb_substr($observaciones, 0, 60, 'UTF-8') . '…'
                                                                : $observaciones;
                                                        } elseif (strlen($observaciones) > 60) {
                                                            $observacionesResumen = substr($observaciones, 0, 60) . '…';
                                                        }
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><strong>#<?php echo h(str_pad((string)$pagoId, 5, '0', STR_PAD_LEFT)); ?></strong></td>
                                                        <td><?php echo h($fecha); ?></td>
                                                        <td><?php echo h(pago_tipo_label($tipo)); ?></td>
                                                        <td><?php echo h($pago['curso_nombre'] ?? ''); ?></td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo h($alumno); ?></div>
                                                            <?php if (!empty($pago['alumno_email'])): ?>
                                                                <div class="small text-muted"><a href="mailto:<?php echo h($pago['alumno_email']); ?>"><?php echo h($pago['alumno_email']); ?></a></div>
                                                            <?php endif; ?>
                                                            <?php if (!empty($pago['alumno_telefono'])): ?>
                                                                <div class="small text-muted"><?php echo h($pago['alumno_telefono']); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo h(pago_format_currency(isset($pago['monto']) ? (float)$pago['monto'] : 0.0, $pago['moneda'] ?? 'ARS')); ?></td>
                                                        <td><?php echo pago_estado_badge($estado); ?></td>
                                                        <td>
                                                            <?php if ($observaciones !== ''): ?>
                                                                <span title="<?php echo h($observaciones); ?>"><?php echo h($observacionesResumen); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">—</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($comprobantePath !== ''): ?>
                                                                <a class="btn btn-outline-primary btn-sm" href="../<?php echo h(ltrim($comprobantePath, '/')); ?>" target="_blank" rel="noopener">
                                                                    <i class="fas fa-file-arrow-down me-1"></i><?php echo h($comprobanteLabel); ?>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted">Sin archivo</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column gap-2">
                                                                <?php if ($puedeGestionar): ?>
                                                                    <button type="button" class="btn btn-success btn-sm btn-aprobar-pago" data-id="<?php echo $pagoId; ?>">
                                                                        <i class="fas fa-check me-1"></i>Aprobar
                                                                    </button>
                                                                    <button type="button" class="btn btn-danger btn-sm btn-rechazar-pago" data-id="<?php echo $pagoId; ?>">
                                                                        <i class="fas fa-times me-1"></i>Rechazar
                                                                    </button>
                                                                <?php else: ?>
                                                                    <span class="text-muted small">Sin acciones</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <form id="formPago<?php echo $pagoId; ?>" action="procesarsbd.php" method="POST" class="d-none">
                                                                <input type="hidden" name="__accion" value="">
                                                                <input type="hidden" name="id_pago" value="<?php echo $pagoId; ?>">
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
        document.addEventListener('DOMContentLoaded', () => {
            const table = $("#tablaPagos").DataTable({
                responsive: true,
                lengthChange: true,
                autoWidth: false,
                order: [[0, 'desc']],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
                },
                buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"]
            });
            table.buttons().container().appendTo('#tablaPagos_wrapper .col-md-6:eq(0)');

            const confirmar = (mensaje) => window.confirm(mensaje);

            document.querySelectorAll('.btn-aprobar-pago').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!confirmar('¿Confirmás la aprobación de este pago?')) {
                        return;
                    }
                    const id = btn.dataset.id;
                    const form = document.getElementById('formPago' + id);
                    if (!form) {
                        return;
                    }
                    form.querySelector('input[name="__accion"]').value = 'aprobar_pago_transferencia';
                    form.submit();
                });
            });

            document.querySelectorAll('.btn-rechazar-pago').forEach(btn => {
                btn.addEventListener('click', () => {
                    if (!confirmar('¿Confirmás el rechazo de este pago?')) {
                        return;
                    }
                    const id = btn.dataset.id;
                    const form = document.getElementById('formPago' + id);
                    if (!form) {
                        return;
                    }
                    form.querySelector('input[name="__accion"]').value = 'rechazar_pago_transferencia';
                    form.submit();
                });
            });
        });
    </script>
</body>

</html>
