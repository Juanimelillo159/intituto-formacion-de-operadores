<?php
require_once '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$statusOptions = [
    'pendiente_revision' => 'Pendiente de revisión',
    'aprobado' => 'Aprobado',
    'rechazado' => 'Rechazado',
    'pago_pendiente_confirmacion' => 'Pago en validación',
    'pagado' => 'Pagado',
];

$alert = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $idCertificado = (int)($_POST['id_certificado'] ?? 0);
        $nuevoEstado = trim((string)($_POST['estado'] ?? ''));
        $observaciones = trim((string)($_POST['observaciones'] ?? ''));
        if ($idCertificado <= 0 || !isset($statusOptions[$nuevoEstado])) {
            throw new InvalidArgumentException('Solicitud inválida.');
        }

        $con->beginTransaction();

        $stmtCert = $con->prepare('SELECT pago_comprobante_path FROM certificados WHERE id_certificado = :id LIMIT 1');
        $stmtCert->execute([':id' => $idCertificado]);
        $certRow = $stmtCert->fetch(PDO::FETCH_ASSOC);
        if (!$certRow) {
            throw new RuntimeException('No encontramos la solicitud indicada.');
        }

        $debeLimpiarPago = in_array($nuevoEstado, ['pendiente_revision', 'rechazado'], true);
        $sqlUpdate = 'UPDATE certificados SET estado = :estado, observaciones = :obs, actualizado_en = NOW()';
        if ($debeLimpiarPago) {
            $sqlUpdate .= ', pago_metodo = NULL, pago_estado = NULL, pago_monto = NULL, pago_moneda = NULL, pago_referencia = NULL, pago_comprobante_path = NULL, pago_comprobante_nombre = NULL, pago_comprobante_mime = NULL, pago_comprobante_tamano = NULL';
        }
        $sqlUpdate .= ' WHERE id_certificado = :id';

        $stmtUpdate = $con->prepare($sqlUpdate);
        $stmtUpdate->execute([
            ':estado' => $nuevoEstado,
            ':obs' => $observaciones !== '' ? $observaciones : null,
            ':id' => $idCertificado,
        ]);

        $con->commit();

        if ($debeLimpiarPago && !empty($certRow['pago_comprobante_path'])) {
            $oldPath = __DIR__ . '/../' . ltrim((string)$certRow['pago_comprobante_path'], '/');
            if (is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $alert = ['type' => 'success', 'message' => 'Estado actualizado correctamente.'];
    } catch (Throwable $exception) {
        if ($con instanceof PDO && $con->inTransaction()) {
            $con->rollBack();
        }
        $alert = ['type' => 'danger', 'message' => $exception->getMessage()];
    }
}

$sql = $con->prepare('
    SELECT
        c.id_certificado,
        c.id_usuario,
        c.id_curso,
        c.estado,
        c.observaciones,
        c.pdf_nombre,
        c.pdf_path,
        c.pago_metodo,
        c.pago_estado,
        c.pago_monto,
        c.pago_moneda,
        c.pago_referencia,
        c.creado_en,
        c.actualizado_en,
        cursos.nombre_curso,
        usuarios.email
    FROM certificados c
    LEFT JOIN cursos ON cursos.id_curso = c.id_curso
    LEFT JOIN usuarios ON usuarios.id_usuario = c.id_usuario
    ORDER BY c.creado_en DESC, c.id_certificado DESC
');
$sql->execute();
$rows = $sql->fetchAll(PDO::FETCH_ASSOC);
$total = is_array($rows) ? count($rows) : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificaciones</title>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title">Solicitudes de certificación</h3>
                                <div class="card-tools">
                                    <span class="badge badge-primary">Total: <?php echo (int)$total; ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($alert !== null): ?>
                                    <div class="alert alert-<?php echo htmlspecialchars($alert['type'], ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                                        <?php echo htmlspecialchars($alert['message'], ENT_QUOTES, 'UTF-8'); ?>
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped">
                                        <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Curso</th>
                                            <th>Usuario</th>
                                            <th>Estado</th>
                                            <th>Formulario</th>
                                            <th>Pago</th>
                                            <th>Acciones</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!empty($rows)): ?>
                                            <?php foreach ($rows as $row): ?>
                                                <?php
                                                $estado = strtolower((string)($row['estado'] ?? 'pendiente_revision'));
                                                $estadoLabel = $statusOptions[$estado] ?? ucfirst(str_replace('_', ' ', $estado));
                                                $estadoClass = $statusOptions[$estado] ?? '';
                                                $pdfUrl = !empty($row['pdf_path']) ? ('../' . ltrim((string)$row['pdf_path'], '/')) : null;
                                                $pagoDescripcion = '—';
                                                if (!empty($row['pago_metodo'])) {
                                                    $pagoDescripcion = sprintf(
                                                        '%s (%s)',
                                                        ucwords(str_replace('_', ' ', (string)$row['pago_metodo'])),
                                                        ucwords(str_replace('_', ' ', (string)($row['pago_estado'] ?? 'pendiente')))
                                                    );
                                                    if ($row['pago_monto'] !== null) {
                                                        $pagoDescripcion .= sprintf(' - %s %s', strtoupper((string)($row['pago_moneda'] ?? 'ARS')), number_format((float)$row['pago_monto'], 2, ',', '.'));
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <td><?php echo (int)$row['id_certificado']; ?></td>
                                                    <td><?php echo htmlspecialchars($row['nombre_curso'] ?? 'Certificación', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($row['email'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td><?php echo htmlspecialchars($estadoLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <?php if ($pdfUrl): ?>
                                                            <a href="<?php echo htmlspecialchars($pdfUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-file-pdf"></i> Ver PDF
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">Sin archivo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($pagoDescripcion, ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <td>
                                                        <form method="post" class="d-flex flex-column">
                                                            <input type="hidden" name="id_certificado" value="<?php echo (int)$row['id_certificado']; ?>">
                                                            <select class="custom-select custom-select-sm mb-2" name="estado">
                                                                <?php foreach ($statusOptions as $value => $label): ?>
                                                                    <option value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $estado === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <input type="text" name="observaciones" class="form-control form-control-sm mb-2" placeholder="Observaciones" value="<?php echo htmlspecialchars((string)($row['observaciones'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                                            <button type="submit" class="btn btn-sm btn-primary align-self-start">Actualizar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted">No hay solicitudes registradas.</td>
                                            </tr>
                                        <?php endif; ?>
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
</body>
</html>
