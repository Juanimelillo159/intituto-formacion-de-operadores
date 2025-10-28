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

$permisos = [];
$permisosMapa = [];
try {
    $stmtPermisos = $con->query('SELECT id_permiso, nombre_permiso, descripcion_permiso FROM permisos ORDER BY id_permiso');
    if ($stmtPermisos !== false) {
        $permisos = $stmtPermisos->fetchAll(PDO::FETCH_ASSOC);
        if (is_array($permisos)) {
            foreach ($permisos as $permiso) {
                $permisoId = isset($permiso['id_permiso']) ? (int)$permiso['id_permiso'] : 0;
                if ($permisoId > 0) {
                    $permisosMapa[$permisoId] = $permiso;
                }
            }
        } else {
            $permisos = [];
        }
    }
} catch (Throwable $permisoEx) {
    $permisos = [];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['actualizar_permiso'])) {
    try {
        $usuarioId = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : 0;
        $permisoId = isset($_POST['permiso_id']) ? (int)$_POST['permiso_id'] : 0;

        if ($usuarioId <= 0) {
            throw new InvalidArgumentException('Usuario inválido.');
        }

        if ($permisoId <= 0 || !isset($permisosMapa[$permisoId])) {
            throw new InvalidArgumentException('Seleccioná un permiso válido.');
        }

        $stmtActualizar = $con->prepare('UPDATE usuarios SET id_permiso = :permiso WHERE id_usuario = :usuario');
        if ($stmtActualizar === false) {
            throw new RuntimeException('No se pudo preparar la consulta.');
        }

        $stmtActualizar->execute([
            ':permiso' => $permisoId,
            ':usuario' => $usuarioId,
        ]);

        $_SESSION['usuarios_admin_ok'] = 'Permisos del usuario actualizados correctamente.';
    } catch (Throwable $ex) {
        $_SESSION['usuarios_admin_err'] = $ex->getMessage();
    }

    header('Location: usuarios.php');
    exit;
}

include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$flashOk = $_SESSION['usuarios_admin_ok'] ?? null;
$flashErr = $_SESSION['usuarios_admin_err'] ?? null;
unset($_SESSION['usuarios_admin_ok'], $_SESSION['usuarios_admin_err']);

$estadoUsuarios = [];
try {
    $stmtEstados = $con->query('SELECT id_estado, nombre_estado FROM estado');
    if ($stmtEstados !== false) {
        while ($row = $stmtEstados->fetch(PDO::FETCH_ASSOC)) {
            $idEstado = isset($row['id_estado']) ? (int)$row['id_estado'] : 0;
            if ($idEstado > 0) {
                $estadoUsuarios[$idEstado] = $row['nombre_estado'] ?? '';
            }
        }
    }
} catch (Throwable $estadoEx) {
    $estadoUsuarios = [];
}

$usuarios = [];
try {
    $stmtUsuarios = $con->query('SELECT u.id_usuario, u.email, u.nombre, u.apellido, u.telefono, u.id_permiso, u.id_estado, u.verificado, p.nombre_permiso FROM usuarios u LEFT JOIN permisos p ON p.id_permiso = u.id_permiso ORDER BY u.id_usuario');
    if ($stmtUsuarios !== false) {
        $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($usuarios)) {
            $usuarios = [];
        }
    }
} catch (Throwable $usuarioEx) {
    $usuarios = [];
    $flashErr = $flashErr ?: $usuarioEx->getMessage();
}

$idsUsuarios = [];
foreach ($usuarios as $userRow) {
    $uid = isset($userRow['id_usuario']) ? (int)$userRow['id_usuario'] : 0;
    if ($uid > 0) {
        $idsUsuarios[] = $uid;
    }
}

$capacitacionesPorUsuario = [];
$certificacionesPorUsuario = [];

if (!empty($idsUsuarios)) {
    $placeholders = implode(',', array_fill(0, count($idsUsuarios), '?'));

    try {
        $sqlCapacitaciones = 'SELECT cc.id_capacitacion, cc.creado_por AS usuario_id, cc.creado_en, cc.id_estado, cursos.nombre_curso, est.nombre_estado FROM checkout_capacitaciones cc LEFT JOIN cursos ON cursos.id_curso = cc.id_curso LEFT JOIN estados_inscripciones est ON est.id_estado = cc.id_estado WHERE cc.creado_por IN (' . $placeholders . ') ORDER BY cc.creado_en DESC, cc.id_capacitacion DESC';
        $stmtCapacitaciones = $con->prepare($sqlCapacitaciones);
        if ($stmtCapacitaciones !== false) {
            $stmtCapacitaciones->execute($idsUsuarios);
            while ($row = $stmtCapacitaciones->fetch(PDO::FETCH_ASSOC)) {
                $uid = isset($row['usuario_id']) ? (int)$row['usuario_id'] : 0;
                if ($uid <= 0) {
                    continue;
                }
                if (!isset($capacitacionesPorUsuario[$uid])) {
                    $capacitacionesPorUsuario[$uid] = [];
                }
                $capacitacionesPorUsuario[$uid][] = [
                    'id' => isset($row['id_capacitacion']) ? (int)$row['id_capacitacion'] : 0,
                    'curso' => $row['nombre_curso'] ?? 'Sin curso',
                    'estado' => $row['nombre_estado'] ?? 'Sin estado',
                    'fecha' => $row['creado_en'] ?? null,
                ];
            }
        }
    } catch (Throwable $capEx) {
        $flashErr = $flashErr ?: $capEx->getMessage();
    }

    try {
        $sqlCertificaciones = 'SELECT cc.id_certificacion, cc.creado_por AS usuario_id, cc.creado_en, cc.id_estado, cursos.nombre_curso, est.nombre_estado FROM checkout_certificaciones cc LEFT JOIN cursos ON cursos.id_curso = cc.id_curso LEFT JOIN estados_inscripciones est ON est.id_estado = cc.id_estado WHERE cc.creado_por IN (' . $placeholders . ') ORDER BY cc.creado_en DESC, cc.id_certificacion DESC';
        $stmtCertificaciones = $con->prepare($sqlCertificaciones);
        if ($stmtCertificaciones !== false) {
            $stmtCertificaciones->execute($idsUsuarios);
            while ($row = $stmtCertificaciones->fetch(PDO::FETCH_ASSOC)) {
                $uid = isset($row['usuario_id']) ? (int)$row['usuario_id'] : 0;
                if ($uid <= 0) {
                    continue;
                }
                if (!isset($certificacionesPorUsuario[$uid])) {
                    $certificacionesPorUsuario[$uid] = [];
                }
                $certificacionesPorUsuario[$uid][] = [
                    'id' => isset($row['id_certificacion']) ? (int)$row['id_certificacion'] : 0,
                    'curso' => $row['nombre_curso'] ?? 'Sin curso',
                    'estado' => $row['nombre_estado'] ?? 'Sin estado',
                    'fecha' => $row['creado_en'] ?? null,
                ];
            }
        }
    } catch (Throwable $certEx) {
        $flashErr = $flashErr ?: $certEx->getMessage();
    }
}

function formatearFecha(?string $fecha): string
{
    if (empty($fecha)) {
        return '';
    }
    try {
        $timestamp = strtotime($fecha);
        if ($timestamp === false) {
            return '';
        }
        return date('d/m/Y H:i', $timestamp);
    } catch (Throwable $e) {
        return '';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de usuarios | Panel Administrativo</title>
    <style>
        .tabla-usuarios td {
            vertical-align: top;
        }
        .inscripciones-lista {
            margin: 0;
            padding-left: 18px;
        }
        .inscripciones-lista li {
            margin-bottom: .35rem;
        }
        .badge-estado {
            font-size: .85rem;
        }
        .datos-contacto {
            font-size: .9rem;
        }
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
                                <h3 class="card-title mb-0">Gestión de usuarios</h3>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($flashOk)): ?>
                                    <div class="alert alert-success" role="alert"><?php echo h($flashOk); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($flashErr)): ?>
                                    <div class="alert alert-danger" role="alert"><?php echo h($flashErr); ?></div>
                                <?php endif; ?>

                                <div class="table-responsive">
                                    <table id="tablaUsuarios" class="table table-striped table-bordered table-sm tabla-usuarios">
                                        <thead class="table-light">
                                        <tr>
                                            <th style="width: 80px;">ID</th>
                                            <th>Datos del usuario</th>
                                            <th style="width: 260px;">Permisos</th>
                                            <th>Capacitaciones</th>
                                            <th>Certificaciones</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($usuarios as $usuario): ?>
                                            <?php
                                            $uid = isset($usuario['id_usuario']) ? (int)$usuario['id_usuario'] : 0;
                                            $nombreCompleto = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));
                                            $nombreCompleto = $nombreCompleto !== '' ? $nombreCompleto : 'Sin nombre';
                                            $estadoUsuario = '';
                                            $estadoId = isset($usuario['id_estado']) ? (int)$usuario['id_estado'] : 0;
                                            if ($estadoId > 0 && isset($estadoUsuarios[$estadoId])) {
                                                $estadoUsuario = $estadoUsuarios[$estadoId];
                                            }
                                            $verificado = (int)($usuario['verificado'] ?? 0) === 1;
                                            $capacitaciones = $capacitacionesPorUsuario[$uid] ?? [];
                                            $certificaciones = $certificacionesPorUsuario[$uid] ?? [];
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?php echo $uid; ?></strong>
                                                    <?php if ($verificado): ?>
                                                        <div><span class="badge badge-success">Verificado</span></div>
                                                    <?php else: ?>
                                                        <div><span class="badge badge-secondary">Sin verificar</span></div>
                                                    <?php endif; ?>
                                                    <?php if ($estadoUsuario !== ''): ?>
                                                        <div><span class="badge badge-info badge-estado"><?php echo h($estadoUsuario); ?></span></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><strong><?php echo h($nombreCompleto); ?></strong></div>
                                                    <div class="datos-contacto"><i class="fas fa-envelope"></i> <?php echo h($usuario['email'] ?? ''); ?></div>
                                                    <?php if (!empty($usuario['telefono'])): ?>
                                                        <div class="datos-contacto"><i class="fas fa-phone"></i> <?php echo h($usuario['telefono']); ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="mb-2"><span class="badge badge-primary">Actual: <?php echo h($usuario['nombre_permiso'] ?? 'Sin permiso'); ?></span></div>
                                                    <form method="post" class="form-inline">
                                                        <input type="hidden" name="usuario_id" value="<?php echo $uid; ?>">
                                                        <div class="form-group mb-2">
                                                            <label class="sr-only" for="permiso-<?php echo $uid; ?>">Permiso</label>
                                                            <select class="form-control form-control-sm" name="permiso_id" id="permiso-<?php echo $uid; ?>">
                                                                <?php foreach ($permisos as $permiso): ?>
                                                                    <?php $permisoId = isset($permiso['id_permiso']) ? (int)$permiso['id_permiso'] : 0; ?>
                                                                    <option value="<?php echo $permisoId; ?>" <?php echo $permisoId === (int)($usuario['id_permiso'] ?? 0) ? 'selected' : ''; ?>>
                                                                        <?php echo h($permiso['nombre_permiso'] ?? ''); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="form-group mb-2 mt-2">
                                                            <button type="submit" name="actualizar_permiso" value="1" class="btn btn-sm btn-primary">
                                                                Actualizar
                                                            </button>
                                                        </div>
                                                    </form>
                                                </td>
                                                <td>
                                                    <?php if (!empty($capacitaciones)): ?>
                                                        <ul class="inscripciones-lista">
                                                            <?php foreach ($capacitaciones as $cap): ?>
                                                                <li>
                                                                    <strong><?php echo h($cap['curso']); ?></strong><br>
                                                                    <small class="text-muted">Estado: <?php echo h($cap['estado']); ?></small><br>
                                                                    <?php $fechaCap = formatearFecha($cap['fecha'] ?? null); ?>
                                                                    <?php if ($fechaCap !== ''): ?>
                                                                        <small class="text-muted">Registrado: <?php echo h($fechaCap); ?></small>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin capacitaciones registradas</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($certificaciones)): ?>
                                                        <ul class="inscripciones-lista">
                                                            <?php foreach ($certificaciones as $cert): ?>
                                                                <li>
                                                                    <strong><?php echo h($cert['curso']); ?></strong><br>
                                                                    <small class="text-muted">Estado: <?php echo h($cert['estado']); ?></small><br>
                                                                    <?php $fechaCert = formatearFecha($cert['fecha'] ?? null); ?>
                                                                    <?php if ($fechaCert !== ''): ?>
                                                                        <small class="text-muted">Registrado: <?php echo h($fechaCert); ?></small>
                                                                    <?php endif; ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <span class="text-muted">Sin certificaciones registradas</span>
                                                    <?php endif; ?>
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
    $(function () {
        $('#tablaUsuarios').DataTable({
            responsive: true,
            lengthChange: true,
            autoWidth: false,
            order: [[0, 'asc']],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
            },
            buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"]
        }).buttons().container().appendTo('#tablaUsuarios_wrapper .col-md-6:eq(0)');
    });
</script>

</body>
</html>
