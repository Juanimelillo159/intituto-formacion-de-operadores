<?php
require_once '../sbd.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: ../index.php');
    exit;
}

if (!isset($_SESSION['permiso']) || (int)$_SESSION['permiso'] !== 1) {
    header('Location: ../index.php');
    exit;
}

include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatDate(?string $value): string
{
    if (empty($value)) {
        return '';
    }
    try {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }
        return date('d/m/Y H:i', $timestamp);
    } catch (Throwable $exception) {
        return '';
    }
}

function renderEstadoBadge(?int $estadoId, ?string $label): string
{
    $label = $label !== null && $label !== '' ? $label : 'Sin estado';
    $classMap = [
        1 => 'badge badge-warning',
        2 => 'badge badge-info',
        3 => 'badge badge-success',
        4 => 'badge badge-danger',
    ];
    $class = $classMap[$estadoId ?? 0] ?? 'badge badge-secondary';
    return '<span class="' . $class . '">' . h($label) . '</span>';
}

function pagoEstadoBadge(string $estado): string
{
    $estado = strtolower($estado);
    return match ($estado) {
        'pagado' => '<span class="badge badge-success">Aprobado</span>',
        'rechazado' => '<span class="badge badge-danger">Rechazado</span>',
        'cancelado' => '<span class="badge badge-secondary">Cancelado</span>',
        default => '<span class="badge badge-warning text-dark">Pendiente</span>',
    };
}

function formatCurrency(?float $amount, ?string $currency): string
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

$usuarioId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedId = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;
    if ($postedId > 0) {
        $usuarioId = $postedId;
    }
}

if ($usuarioId <= 0) {
    http_response_code(400);
    echo '<div class="alert alert-danger m-4">Usuario inválido.</div>';
    exit;
}

$redirectSelf = 'usuario_detalle.php?id=' . $usuarioId;

$permisos = [];
$permisosMapa = [];
try {
    $stmtPermisos = $con->query('SELECT id_permiso, nombre_permiso FROM permisos ORDER BY id_permiso');
    if ($stmtPermisos !== false) {
        $permisos = $stmtPermisos->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($permisos as $permiso) {
            $permisoId = isset($permiso['id_permiso']) ? (int)$permiso['id_permiso'] : 0;
            if ($permisoId > 0) {
                $permisosMapa[$permisoId] = $permiso['nombre_permiso'] ?? '';
            }
        }
    }
} catch (Throwable $permisoException) {
    $permisos = [];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accion = $_POST['accion'] ?? '';
    if ($accion === 'actualizar_permiso') {
        try {
            $permisoId = isset($_POST['permiso_id']) ? (int)$_POST['permiso_id'] : 0;
            if (!isset($permisosMapa[$permisoId])) {
                throw new InvalidArgumentException('Seleccioná un permiso válido.');
            }

            $stmtUpdate = $con->prepare('UPDATE usuarios SET id_permiso = :permiso WHERE id_usuario = :usuario');
            if ($stmtUpdate === false) {
                throw new RuntimeException('No se pudo preparar la actualización de permisos.');
            }
            $stmtUpdate->execute([
                ':permiso' => $permisoId,
                ':usuario' => $usuarioId,
            ]);
            $_SESSION['usuario_detalle_ok'] = 'Permisos actualizados correctamente.';
        } catch (Throwable $exception) {
            $_SESSION['usuario_detalle_err'] = $exception->getMessage();
        }
        header('Location: ' . $redirectSelf);
        exit;
    }
}

$flashSuccess = $_SESSION['usuario_detalle_ok'] ?? null;
$flashError = $_SESSION['usuario_detalle_err'] ?? null;
$pagosSuccess = $_SESSION['pagos_admin_success'] ?? null;
$pagosWarning = $_SESSION['pagos_admin_warning'] ?? null;
$pagosError = $_SESSION['pagos_admin_error'] ?? null;
$certSuccess = $_SESSION['certificacion_admin_success'] ?? null;
$certError = $_SESSION['certificacion_admin_error'] ?? null;
unset(
    $_SESSION['usuario_detalle_ok'],
    $_SESSION['usuario_detalle_err'],
    $_SESSION['pagos_admin_success'],
    $_SESSION['pagos_admin_warning'],
    $_SESSION['pagos_admin_error'],
    $_SESSION['certificacion_admin_success'],
    $_SESSION['certificacion_admin_error']
);

$usuario = null;
try {
    $stmtUsuario = $con->prepare(
        'SELECT u.*, p.nombre_permiso, est.nombre_estado
           FROM usuarios u
      LEFT JOIN permisos p ON p.id_permiso = u.id_permiso
      LEFT JOIN estado est ON est.id_estado = u.id_estado
          WHERE u.id_usuario = :usuario
          LIMIT 1'
    );
    if ($stmtUsuario !== false) {
        $stmtUsuario->execute([':usuario' => $usuarioId]);
        $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC) ?: null;
    }
} catch (Throwable $exception) {
    $flashError = $flashError ?: $exception->getMessage();
}

if ($usuario === null) {
    echo '<div class="alert alert-danger m-4">No encontramos la información del usuario solicitado.</div>';
    exit;
}

$capacitaciones = [];
$capacitacionIds = [];
try {
    $stmtCapacitaciones = $con->prepare(
        'SELECT cc.id_capacitacion, cc.creado_en, cc.id_estado, cc.precio_total, cc.moneda, cc.nombre, cc.apellido, cc.email, cc.telefono,
                cursos.nombre_curso, est.nombre_estado
           FROM checkout_capacitaciones cc
      LEFT JOIN cursos ON cursos.id_curso = cc.id_curso
      LEFT JOIN estados_inscripciones est ON est.id_estado = cc.id_estado
          WHERE cc.creado_por = :usuario
       ORDER BY cc.creado_en DESC, cc.id_capacitacion DESC'
    );
    if ($stmtCapacitaciones !== false) {
        $stmtCapacitaciones->execute([':usuario' => $usuarioId]);
        while ($row = $stmtCapacitaciones->fetch(PDO::FETCH_ASSOC)) {
            $id = isset($row['id_capacitacion']) ? (int)$row['id_capacitacion'] : 0;
            if ($id <= 0) {
                continue;
            }
            $capacitaciones[] = [
                'id' => $id,
                'curso' => $row['nombre_curso'] ?? 'Capacitación',
                'estado_id' => isset($row['id_estado']) ? (int)$row['id_estado'] : null,
                'estado' => $row['nombre_estado'] ?? null,
                'fecha' => $row['creado_en'] ?? null,
                'precio' => isset($row['precio_total']) ? (float)$row['precio_total'] : null,
                'moneda' => $row['moneda'] ?? 'ARS',
            ];
            $capacitacionIds[] = $id;
        }
    }
} catch (Throwable $exception) {
    $flashError = $flashError ?: $exception->getMessage();
}

$certificaciones = [];
$certificacionIds = [];
try {
    $stmtCertificaciones = $con->prepare(
        'SELECT cc.id_certificacion, cc.creado_en, cc.id_estado, cc.precio_total, cc.moneda, cc.pdf_path, cc.pdf_nombre,
                cc.nombre, cc.apellido, cc.email, cc.telefono,
                cursos.nombre_curso, est.nombre_estado
           FROM checkout_certificaciones cc
      LEFT JOIN cursos ON cursos.id_curso = cc.id_curso
      LEFT JOIN estados_inscripciones est ON est.id_estado = cc.id_estado
          WHERE cc.creado_por = :usuario
       ORDER BY cc.creado_en DESC, cc.id_certificacion DESC'
    );
    if ($stmtCertificaciones !== false) {
        $stmtCertificaciones->execute([':usuario' => $usuarioId]);
        while ($row = $stmtCertificaciones->fetch(PDO::FETCH_ASSOC)) {
            $id = isset($row['id_certificacion']) ? (int)$row['id_certificacion'] : 0;
            if ($id <= 0) {
                continue;
            }
            $certificaciones[] = [
                'id' => $id,
                'curso' => $row['nombre_curso'] ?? 'Certificación',
                'estado_id' => isset($row['id_estado']) ? (int)$row['id_estado'] : null,
                'estado' => $row['nombre_estado'] ?? null,
                'fecha' => $row['creado_en'] ?? null,
                'precio' => isset($row['precio_total']) ? (float)$row['precio_total'] : null,
                'moneda' => $row['moneda'] ?? 'ARS',
                'pdf_path' => $row['pdf_path'] ?? null,
                'pdf_nombre' => $row['pdf_nombre'] ?? null,
            ];
            $certificacionIds[] = $id;
        }
    }
} catch (Throwable $exception) {
    $flashError = $flashError ?: $exception->getMessage();
}

$pagosPorCapacitacion = [];
if (!empty($capacitacionIds)) {
    $placeholders = implode(',', array_fill(0, count($capacitacionIds), '?'));
    try {
        $stmtPagosCap = $con->prepare(
            'SELECT * FROM checkout_pagos WHERE id_capacitacion IN (' . $placeholders . ') ORDER BY creado_en DESC'
        );
        if ($stmtPagosCap !== false) {
            $stmtPagosCap->execute($capacitacionIds);
            while ($row = $stmtPagosCap->fetch(PDO::FETCH_ASSOC)) {
                $idCap = isset($row['id_capacitacion']) ? (int)$row['id_capacitacion'] : 0;
                if ($idCap <= 0) {
                    continue;
                }
                if (!isset($pagosPorCapacitacion[$idCap])) {
                    $pagosPorCapacitacion[$idCap] = [];
                }
                $pagosPorCapacitacion[$idCap][] = $row;
            }
        }
    } catch (Throwable $exception) {
        $pagosPorCapacitacion = [];
        $pagosError = $pagosError ?: $exception->getMessage();
    }
}

$pagosPorCertificacion = [];
if (!empty($certificacionIds)) {
    $placeholders = implode(',', array_fill(0, count($certificacionIds), '?'));
    try {
        $stmtPagosCert = $con->prepare(
            'SELECT * FROM checkout_pagos WHERE id_certificacion IN (' . $placeholders . ') ORDER BY creado_en DESC'
        );
        if ($stmtPagosCert !== false) {
            $stmtPagosCert->execute($certificacionIds);
            while ($row = $stmtPagosCert->fetch(PDO::FETCH_ASSOC)) {
                $idCert = isset($row['id_certificacion']) ? (int)$row['id_certificacion'] : 0;
                if ($idCert <= 0) {
                    continue;
                }
                if (!isset($pagosPorCertificacion[$idCert])) {
                    $pagosPorCertificacion[$idCert] = [];
                }
                $pagosPorCertificacion[$idCert][] = $row;
            }
        }
    } catch (Throwable $exception) {
        $pagosPorCertificacion = [];
        $pagosError = $pagosError ?: $exception->getMessage();
    }
}

foreach ($capacitaciones as &$cap) {
    $capId = $cap['id'];
    $cap['pagos'] = $pagosPorCapacitacion[$capId] ?? [];
}
unset($cap);

foreach ($certificaciones as &$cert) {
    $certId = $cert['id'];
    $cert['pagos'] = $pagosPorCertificacion[$certId] ?? [];
}
unset($cert);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de usuario | Panel Administrativo</title>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <div class="content-wrapper">
            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <div class="card card-outline card-primary">
                                <div class="card-header">
                                    <h3 class="card-title mb-0">Usuario #<?php echo (int)$usuario['id_usuario']; ?> - <?php echo h(trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''))); ?></h3>
                                </div>
                                <div class="card-body">
                                    <?php if ($flashSuccess): ?>
                                        <div class="alert alert-success" role="alert"><?php echo h($flashSuccess); ?></div>
                                    <?php endif; ?>
                                    <?php if ($flashError): ?>
                                        <div class="alert alert-danger" role="alert"><?php echo h($flashError); ?></div>
                                    <?php endif; ?>
                                    <?php if ($pagosSuccess): ?>
                                        <div class="alert alert-success" role="alert"><?php echo h($pagosSuccess); ?></div>
                                    <?php endif; ?>
                                    <?php if ($pagosWarning): ?>
                                        <div class="alert alert-warning" role="alert"><?php echo h($pagosWarning); ?></div>
                                    <?php endif; ?>
                                    <?php if ($pagosError): ?>
                                        <div class="alert alert-danger" role="alert"><?php echo h($pagosError); ?></div>
                                    <?php endif; ?>
                                    <?php if ($certSuccess): ?>
                                        <div class="alert alert-success" role="alert"><?php echo h($certSuccess); ?></div>
                                    <?php endif; ?>
                                    <?php if ($certError): ?>
                                        <div class="alert alert-danger" role="alert"><?php echo h($certError); ?></div>
                                    <?php endif; ?>

                                    <div class="card card-outline card-primary mb-3">
                                        <div class="card-header p-0 border-bottom-0">
                                            <ul class="nav nav-tabs" id="usuarioTabs" role="tablist">
                                                <li class="nav-item">
                                                    <a class="nav-link active" id="info-tab" data-toggle="pill" href="#tab-info" role="tab">Información personal</a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link" id="permisos-tab" data-toggle="pill" href="#tab-permisos" role="tab">Permisos</a>
                                                </li>
                                                <li class="nav-item">
                                                    <a class="nav-link" id="inscripciones-tab" data-toggle="pill" href="#tab-inscripciones" role="tab">Inscripciones</a>
                                                </li>
                                            </ul>
                                        </div>
                                        <div class="card-body">
                                            <div class="tab-content" id="usuarioTabsContent">
                                                <div class="tab-pane fade show active" id="tab-info" role="tabpanel">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <dl class="row mb-0">
                                                                <dt class="col-sm-4">Nombre</dt>
                                                                <dd class="col-sm-8"><?php echo h(trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''))); ?></dd>
                                                                <dt class="col-sm-4">Email</dt>
                                                                <dd class="col-sm-8"><a href="mailto:<?php echo h($usuario['email'] ?? ''); ?>"><?php echo h($usuario['email'] ?? ''); ?></a></dd>
                                                                <dt class="col-sm-4">Teléfono</dt>
                                                                <dd class="col-sm-8"><?php echo h($usuario['telefono'] ?? '—'); ?></dd>
                                                                <dt class="col-sm-4">Estado</dt>
                                                                <dd class="col-sm-8"><?php echo h($usuario['nombre_estado'] ?? 'Sin estado'); ?></dd>
                                                            </dl>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <dl class="row mb-0">
                                                                <dt class="col-sm-4">Permiso</dt>
                                                                <dd class="col-sm-8"><?php echo h($usuario['nombre_permiso'] ?? 'Sin permiso'); ?></dd>
                                                                <dt class="col-sm-4">Verificación</dt>
                                                                <dd class="col-sm-8">
                                                                    <?php if ((int)($usuario['verificado'] ?? 0) === 1): ?>
                                                                        <span class="badge badge-success">Verificado</span>
                                                                    <?php else: ?>
                                                                        <span class="badge badge-secondary">Sin verificar</span>
                                                                    <?php endif; ?>
                                                                </dd>
                                                                <dt class="col-sm-4">ID interno</dt>
                                                                <dd class="col-sm-8">#<?php echo (int)$usuario['id_usuario']; ?></dd>
                                                            </dl>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="tab-pane fade" id="tab-permisos" role="tabpanel">
                                                    <form method="post" class="mt-2">
                                                        <input type="hidden" name="accion" value="actualizar_permiso">
                                                        <input type="hidden" name="usuario_id" value="<?php echo (int)$usuario['id_usuario']; ?>">
                                                        <div class="form-group">
                                                            <label for="permisoSelect">Seleccioná el permiso</label>
                                                            <select class="form-control" id="permisoSelect" name="permiso_id" required>
                                                                <?php foreach ($permisos as $permiso): ?>
                                                                    <?php $permisoId = isset($permiso['id_permiso']) ? (int)$permiso['id_permiso'] : 0; ?>
                                                                    <option value="<?php echo $permisoId; ?>" <?php echo $permisoId === (int)($usuario['id_permiso'] ?? 0) ? 'selected' : ''; ?>>
                                                                        <?php echo h($permiso['nombre_permiso'] ?? ''); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <button type="submit" class="btn btn-primary">Actualizar permisos</button>
                                                    </form>
                                                </div>

                                                <div class="tab-pane fade" id="tab-inscripciones" role="tabpanel">
                                                    <h4 class="mt-2">Capacitaciones</h4>
                                                    <?php if (empty($capacitaciones)): ?>
                                                        <p class="text-muted">El usuario no registra capacitaciones.</p>
                                                    <?php endif; ?>
                                                    <?php foreach ($capacitaciones as $cap): ?>
                                                        <div class="card mb-3">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between flex-wrap">
                                                                    <div>
                                                                        <h5 class="mb-1">Capacitación: <?php echo h($cap['curso']); ?></h5>
                                                                        <p class="mb-1">Estado: <?php echo renderEstadoBadge($cap['estado_id'], $cap['estado']); ?></p>
                                                                        <?php $fechaCap = formatDate($cap['fecha']); ?>
                                                                        <?php if ($fechaCap !== ''): ?>
                                                                            <p class="mb-1 text-muted">Registrada el <?php echo h($fechaCap); ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <?php if ($cap['precio'] !== null): ?>
                                                                            <span class="badge badge-light">Monto: <?php echo h(formatCurrency((float)$cap['precio'], $cap['moneda'] ?? 'ARS')); ?></span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <div class="table-responsive mt-3">
                                                                    <table class="table table-sm table-bordered mb-0">
                                                                        <thead class="table-light">
                                                                            <tr>
                                                                                <th style="width: 70px;">#</th>
                                                                                <th>Fecha</th>
                                                                                <th>Método</th>
                                                                                <th>Monto</th>
                                                                                <th>Estado</th>
                                                                                <th>Comprobante</th>
                                                                                <th style="width: 200px;">Acciones</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php if (empty($cap['pagos'])): ?>
                                                                                <tr>
                                                                                    <td colspan="7" class="text-muted text-center">Sin pagos registrados.</td>
                                                                                </tr>
                                                                            <?php else: ?>
                                                                                <?php foreach ($cap['pagos'] as $pago): ?>
                                                                                    <?php
                                                                                    $pagoId = (int)($pago['id_pago'] ?? 0);
                                                                                    $fechaPago = formatDate($pago['creado_en'] ?? null);
                                                                                    $estadoPago = strtolower((string)($pago['estado'] ?? 'pendiente'));
                                                                                    $metodo = strtoupper($pago['metodo'] ?? '');
                                                                                    $comprobantePath = trim((string)($pago['comprobante_path'] ?? ''));
                                                                                    $comprobanteNombre = trim((string)($pago['comprobante_nombre'] ?? 'Ver archivo'));
                                                                                    $puedeGestionar = $estadoPago === 'pendiente' && in_array(strtolower((string)($pago['metodo'] ?? '')), ['transferencia', 'mercado_pago'], true);
                                                                                    ?>
                                                                                    <tr>
                                                                                        <td><strong>#<?php echo h(str_pad((string)$pagoId, 5, '0', STR_PAD_LEFT)); ?></strong></td>
                                                                                        <td><?php echo h($fechaPago); ?></td>
                                                                                        <td><?php echo h(ucfirst(str_replace('_', ' ', strtolower((string)$pago['metodo'] ?? 'Desconocido')))); ?></td>
                                                                                        <td><?php echo h(formatCurrency(isset($pago['monto']) ? (float)$pago['monto'] : 0.0, $pago['moneda'] ?? 'ARS')); ?></td>
                                                                                        <td><?php echo pagoEstadoBadge($estadoPago); ?></td>
                                                                                        <td>
                                                                                            <?php if ($comprobantePath !== ''): ?>
                                                                                                <a class="btn btn-outline-primary btn-sm" href="../<?php echo h(ltrim($comprobantePath, '/')); ?>" target="_blank" rel="noopener">
                                                                                                    <i class="fas fa-file-alt mr-1"></i><?php echo h($comprobanteNombre); ?>
                                                                                                </a>
                                                                                            <?php else: ?>
                                                                                                <span class="text-muted">Sin comprobante</span>
                                                                                            <?php endif; ?>
                                                                                        </td>
                                                                                        <td>
                                                                                            <?php if ($puedeGestionar): ?>
                                                                                                <div class="d-flex flex-wrap gap-2">
                                                                                                    <form action="procesarsbd.php" method="post" class="mr-1" onsubmit="return confirm('¿Confirmás la aprobación de este pago?');">
                                                                                                        <input type="hidden" name="__accion" value="aprobar_pago_transferencia">
                                                                                                        <input type="hidden" name="id_pago" value="<?php echo $pagoId; ?>">
                                                                                                        <input type="hidden" name="redirect_to" value="<?php echo h($redirectSelf); ?>">
                                                                                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i>Aprobar</button>
                                                                                                    </form>
                                                                                                    <form action="procesarsbd.php" method="post" onsubmit="return confirm('¿Confirmás el rechazo de este pago?');">
                                                                                                        <input type="hidden" name="__accion" value="rechazar_pago_transferencia">
                                                                                                        <input type="hidden" name="id_pago" value="<?php echo $pagoId; ?>">
                                                                                                        <input type="hidden" name="redirect_to" value="<?php echo h($redirectSelf); ?>">
                                                                                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times mr-1"></i>Rechazar</button>
                                                                                                    </form>
                                                                                                </div>
                                                                                            <?php else: ?>
                                                                                                <span class="text-muted">Sin acciones disponibles</span>
                                                                                            <?php endif; ?>
                                                                                        </td>
                                                                                    </tr>
                                                                                <?php endforeach; ?>
                                                                            <?php endif; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>

                                                    <h4 class="mt-4">Certificaciones</h4>
                                                    <?php if (empty($certificaciones)): ?>
                                                        <p class="text-muted">El usuario no registra certificaciones.</p>
                                                    <?php endif; ?>
                                                    <?php foreach ($certificaciones as $cert): ?>
                                                        <div class="card mb-3">
                                                            <div class="card-body">
                                                                <div class="d-flex justify-content-between flex-wrap">
                                                                    <div>
                                                                        <h5 class="mb-1">Certificación: <?php echo h($cert['curso']); ?></h5>
                                                                        <p class="mb-1">Estado: <?php echo renderEstadoBadge($cert['estado_id'], $cert['estado']); ?></p>
                                                                        <?php $fechaCert = formatDate($cert['fecha']); ?>
                                                                        <?php if ($fechaCert !== ''): ?>
                                                                            <p class="mb-1 text-muted">Solicitada el <?php echo h($fechaCert); ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="text-right">
                                                                        <?php if ($cert['precio'] !== null): ?>
                                                                            <span class="badge badge-light">Monto: <?php echo h(formatCurrency((float)$cert['precio'], $cert['moneda'] ?? 'ARS')); ?></span>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($cert['pdf_path'])): ?>
                                                                            <div class="mt-2">
                                                                                <a class="btn btn-outline-primary btn-sm" href="../<?php echo h(ltrim((string)$cert['pdf_path'], '/')); ?>" target="_blank" rel="noopener">
                                                                                    <i class="fas fa-file-pdf mr-1"></i><?php echo h($cert['pdf_nombre'] ?? 'Formulario'); ?>
                                                                                </a>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                                <?php if ((int)($cert['estado_id'] ?? 0) === 1): ?>
                                                                    <div class="mt-3">
                                                                        <form action="procesarsbd.php" method="post" onsubmit="return confirm('¿Confirmás la inscripción de esta certificación?');">
                                                                            <input type="hidden" name="__accion" value="aprobar_certificacion">
                                                                            <input type="hidden" name="id_certificacion" value="<?php echo (int)$cert['id']; ?>">
                                                                            <input type="hidden" name="redirect_to" value="<?php echo h($redirectSelf); ?>">
                                                                            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i>Confirmar inscripción</button>
                                                                        </form>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div class="table-responsive mt-3">
                                                                    <table class="table table-sm table-bordered mb-0">
                                                                        <thead class="table-light">
                                                                            <tr>
                                                                                <th style="width: 70px;">#</th>
                                                                                <th>Fecha</th>
                                                                                <th>Método</th>
                                                                                <th>Monto</th>
                                                                                <th>Estado</th>
                                                                                <th>Comprobante</th>
                                                                                <th style="width: 200px;">Acciones</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php if (empty($cert['pagos'])): ?>
                                                                                <tr>
                                                                                    <td colspan="7" class="text-muted text-center">Sin pagos registrados.</td>
                                                                                </tr>
                                                                            <?php else: ?>
                                                                                <?php foreach ($cert['pagos'] as $pago): ?>
                                                                                    <?php
                                                                                    $pagoId = (int)($pago['id_pago'] ?? 0);
                                                                                    $fechaPago = formatDate($pago['creado_en'] ?? null);
                                                                                    $estadoPago = strtolower((string)($pago['estado'] ?? 'pendiente'));
                                                                                    $comprobantePath = trim((string)($pago['comprobante_path'] ?? ''));
                                                                                    $comprobanteNombre = trim((string)($pago['comprobante_nombre'] ?? 'Ver archivo'));
                                                                                    $puedeGestionar = $estadoPago === 'pendiente' && in_array(strtolower((string)($pago['metodo'] ?? '')), ['transferencia', 'mercado_pago'], true);
                                                                                    ?>
                                                                                    <tr>
                                                                                        <td><strong>#<?php echo h(str_pad((string)$pagoId, 5, '0', STR_PAD_LEFT)); ?></strong></td>
                                                                                        <td><?php echo h($fechaPago); ?></td>
                                                                                        <td><?php echo h(ucfirst(str_replace('_', ' ', strtolower((string)$pago['metodo'] ?? 'Desconocido')))); ?></td>
                                                                                        <td><?php echo h(formatCurrency(isset($pago['monto']) ? (float)$pago['monto'] : 0.0, $pago['moneda'] ?? 'ARS')); ?></td>
                                                                                        <td><?php echo pagoEstadoBadge($estadoPago); ?></td>
                                                                                        <td>
                                                                                            <?php if ($comprobantePath !== ''): ?>
                                                                                                <a class="btn btn-outline-primary btn-sm" href="../<?php echo h(ltrim($comprobantePath, '/')); ?>" target="_blank" rel="noopener">
                                                                                                    <i class="fas fa-file-alt mr-1"></i><?php echo h($comprobanteNombre); ?>
                                                                                                </a>
                                                                                            <?php else: ?>
                                                                                                <span class="text-muted">Sin comprobante</span>
                                                                                            <?php endif; ?>
                                                                                        </td>
                                                                                        <td>
                                                                                            <?php if ($puedeGestionar): ?>
                                                                                                <div class="d-flex flex-wrap gap-2">
                                                                                                    <form action="procesarsbd.php" method="post" class="mr-1" onsubmit="return confirm('¿Confirmás la aprobación de este pago?');">
                                                                                                        <input type="hidden" name="__accion" value="aprobar_pago_transferencia">
                                                                                                        <input type="hidden" name="id_pago" value="<?php echo $pagoId; ?>">
                                                                                                        <input type="hidden" name="redirect_to" value="<?php echo h($redirectSelf); ?>">
                                                                                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check mr-1"></i>Aprobar</button>
                                                                                                    </form>
                                                                                                    <form action="procesarsbd.php" method="post" onsubmit="return confirm('¿Confirmás el rechazo de este pago?');">
                                                                                                        <input type="hidden" name="__accion" value="rechazar_pago_transferencia">
                                                                                                        <input type="hidden" name="id_pago" value="<?php echo $pagoId; ?>">
                                                                                                        <input type="hidden" name="redirect_to" value="<?php echo h($redirectSelf); ?>">
                                                                                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times mr-1"></i>Rechazar</button>
                                                                                                    </form>
                                                                                                </div>
                                                                                            <?php else: ?>
                                                                                                <span class="text-muted">Sin acciones disponibles</span>
                                                                                            <?php endif; ?>
                                                                                        </td>
                                                                                    </tr>
                                                                                <?php endforeach; ?>
                                                                            <?php endif; ?>
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
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
