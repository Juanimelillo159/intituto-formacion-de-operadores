<?php
require_once '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$certStmt = $con->prepare(
    'SELECT cc.*, c.nombre_curso
       FROM checkout_certificaciones cc
 INNER JOIN cursos c ON c.id_curso = cc.id_curso
   ORDER BY cc.creado_en DESC'
);
$certStmt->execute();
$certificaciones = $certStmt->fetchAll(PDO::FETCH_ASSOC);

$estadoConfig = [
    1 => ['label' => 'En revisión', 'class' => 'badge bg-warning'],
    2 => ['label' => 'Aprobada', 'class' => 'badge bg-info'],
    3 => ['label' => 'Pago registrado', 'class' => 'badge bg-success'],
    4 => ['label' => 'Rechazada', 'class' => 'badge bg-danger'],
];

$adminSuccess = $_SESSION['certificacion_admin_success'] ?? null;
$adminError = $_SESSION['certificacion_admin_error'] ?? null;
unset($_SESSION['certificacion_admin_success'], $_SESSION['certificacion_admin_error']);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatEstado(array $config, ?int $estado): string
{
    if ($estado === null || !isset($config[$estado])) {
        return '<span class="badge bg-secondary">Sin estado</span>';
    }
    $data = $config[$estado];
    return sprintf('<span class="%s">%s</span>', h($data['class']), h($data['label']));
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificaciones | Panel Administrativo</title>
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
                                    <h3 class="card-title">Solicitudes de certificación</h3>
                                </div>
                                <div class="card-body">
                                    <?php if ($adminSuccess): ?>
                                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                                            <i class="fas fa-circle-check me-2"></i><?php echo h($adminSuccess); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($adminError): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="fas fa-circle-xmark me-2"></i><?php echo h($adminError); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                                        </div>
                                    <?php endif; ?>

                                    <div class="table-responsive">
                                        <table id="tablaCertificaciones" class="table table-bordered table-striped align-middle">
                                            <thead>
                                                <tr>
                                                    <th style="width: 70px">#</th>
                                                    <th>Curso</th>
                                                    <th>Solicitante</th>
                                                    <th>Email</th>
                                                    <th>Teléfono</th>
                                                    <th>Estado</th>
                                                    <th>Enviado</th>
                                                    <th>PDF</th>
                                                    <th>Observaciones</th>
                                                    <th style="width: 160px">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($certificaciones as $cert): ?>
                                                    <?php
                                                    $certId = (int)$cert['id_certificacion'];
                                                    $estado = isset($cert['id_estado']) ? (int)$cert['id_estado'] : null;
                                                    $puedeAprobar = in_array($estado, [1, 4], true);
                                                    $puedeRechazar = in_array($estado, [1, 2], true);
                                                    $fecha = '';
                                                    if (!empty($cert['creado_en'])) {
                                                        $timestamp = strtotime((string)$cert['creado_en']);
                                                        if ($timestamp) {
                                                            $fecha = date('d/m/Y H:i', $timestamp);
                                                        }
                                                    }
                                                    $observaciones = trim((string)($cert['observaciones'] ?? ''));
                                                    $observacionesResumen = $observaciones;
                                                    if ($observaciones !== '' && function_exists('mb_substr')) {
                                                        $observacionesResumen = mb_strlen($observaciones, 'UTF-8') > 120
                                                            ? mb_substr($observaciones, 0, 120, 'UTF-8') . '…'
                                                            : $observaciones;
                                                    } elseif (strlen($observacionesResumen) > 120) {
                                                        $observacionesResumen = substr($observacionesResumen, 0, 120) . '…';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><strong>#<?php echo h(str_pad((string)$certId, 5, '0', STR_PAD_LEFT)); ?></strong></td>
                                                        <td><?php echo h($cert['nombre_curso'] ?? ''); ?></td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo h(trim(($cert['nombre'] ?? '') . ' ' . ($cert['apellido'] ?? ''))); ?></div>
                                                        </td>
                                                        <td><a href="mailto:<?php echo h($cert['email'] ?? ''); ?>"><?php echo h($cert['email'] ?? ''); ?></a></td>
                                                        <td><?php echo h($cert['telefono'] ?? ''); ?></td>
                                                        <td><?php echo formatEstado($estadoConfig, $estado); ?></td>
                                                        <td><?php echo h($fecha); ?></td>
                                                        <td>
                                                            <?php if (!empty($cert['pdf_path'])): ?>
                                                                <a class="btn btn-outline-primary btn-sm" href="../<?php echo h(ltrim((string)$cert['pdf_path'], '/')); ?>" target="_blank" rel="noopener">
                                                                    <i class="fas fa-file-pdf me-1"></i>Ver PDF
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="text-muted">Sin archivo</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($observacionesResumen !== ''): ?>
                                                                <span title="<?php echo h($observaciones); ?>"><?php echo h($observacionesResumen); ?></span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column gap-2">
                                                                <?php if ($puedeAprobar): ?>
                                                                    <button type="button" class="btn btn-success btn-sm btn-aprobar" data-id="<?php echo $certId; ?>">
                                                                        <i class="fas fa-check me-1"></i>Aprobar
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if ($puedeRechazar): ?>
                                                                    <button type="button" class="btn btn-danger btn-sm btn-rechazar" data-id="<?php echo $certId; ?>">
                                                                        <i class="fas fa-times me-1"></i>Rechazar
                                                                    </button>
                                                                <?php endif; ?>
                                                                <?php if (!$puedeAprobar && !$puedeRechazar): ?>
                                                                    <span class="text-muted small">Sin acciones disponibles</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <form id="formCert<?php echo $certId; ?>" action="procesarsbd.php" method="POST" class="d-none">
                                                                <input type="hidden" name="__accion" value="">
                                                                <input type="hidden" name="id_certificacion" value="<?php echo $certId; ?>">
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
        document.addEventListener('DOMContentLoaded', () => {
            const table = $("#tablaCertificaciones").DataTable({
                responsive: true,
                lengthChange: true,
                autoWidth: false,
                order: [[0, 'desc']],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
                },
                buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"]
            });
            table.buttons().container().appendTo('#tablaCertificaciones_wrapper .col-md-6:eq(0)');

            document.querySelectorAll('.btn-aprobar').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    const form = document.getElementById('formCert' + id);
                    if (!form) {
                        return;
                    }
                    if (!confirm('¿Confirmás la aprobación de esta certificación?')) {
                        return;
                    }
                    form.querySelector('input[name="__accion"]').value = 'aprobar_certificacion';
                    form.submit();
                });
            });

            document.querySelectorAll('.btn-rechazar').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    const form = document.getElementById('formCert' + id);
                    if (!form) {
                        return;
                    }
                    const reason = prompt('Indicá el motivo del rechazo (opcional):', '');
                    if (reason === null) {
                        return;
                    }
                    if (!confirm('¿Confirmás el rechazo de esta certificación?')) {
                        return;
                    }
                    form.querySelector('input[name="__accion"]').value = 'rechazar_certificacion';
                    form.querySelector('input[name="motivo"]').value = reason.trim();
                    form.submit();
                });
            });
        });
    </script>
</body>

</html>
