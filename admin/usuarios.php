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

$usuarios = [];
$errorMensaje = null;

try {
    $stmtUsuarios = $con->query(
        'SELECT u.id_usuario, u.email, u.nombre, u.apellido, u.telefono, u.verificado, u.id_estado, u.id_permiso,
                p.nombre_permiso, est.nombre_estado
           FROM usuarios u
      LEFT JOIN permisos p ON p.id_permiso = u.id_permiso
      LEFT JOIN estado est ON est.id_estado = u.id_estado
       ORDER BY u.id_usuario'
    );
    if ($stmtUsuarios !== false) {
        $usuarios = $stmtUsuarios->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $exception) {
    $usuarios = [];
    $errorMensaje = $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de usuarios | Panel Administrativo</title>
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
                                    <?php if ($errorMensaje !== null): ?>
                                        <div class="alert alert-danger" role="alert">
                                            <?php echo h($errorMensaje); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="table-responsive">
                                        <table id="tablaUsuarios" class="table table-striped table-bordered table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width: 80px;">ID</th>
                                                    <th>Usuario</th>
                                                    <th>Email</th>
                                                    <th>Permiso</th>
                                                    <th>Estado</th>
                                                    <th style="width: 160px;">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($usuarios as $usuario): ?>
                                                    <?php
                                                    $id = isset($usuario['id_usuario']) ? (int)$usuario['id_usuario'] : 0;
                                                    $nombre = trim(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? ''));
                                                    if ($nombre === '') {
                                                        $nombre = 'Sin nombre';
                                                    }
                                                    $permiso = $usuario['nombre_permiso'] ?? 'Sin permiso';
                                                    $estado = $usuario['nombre_estado'] ?? 'Sin estado';
                                                    $verificado = (int)($usuario['verificado'] ?? 0) === 1;
                                                    ?>
                                                    <tr>
                                                        <td><strong>#<?php echo $id; ?></strong></td>
                                                        <td>
                                                            <div class="fw-semibold"><?php echo h($nombre); ?></div>
                                                            <?php if ($verificado): ?>
                                                                <span class="badge badge-success">Verificado</span>
                                                            <?php else: ?>
                                                                <span class="badge badge-secondary">Sin verificar</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="mailto:<?php echo h($usuario['email'] ?? ''); ?>"><?php echo h($usuario['email'] ?? ''); ?></a>
                                                            <?php if (!empty($usuario['telefono'])): ?>
                                                                <div class="small text-muted"><?php echo h($usuario['telefono']); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?php echo h($permiso); ?></td>
                                                        <td><?php echo h($estado); ?></td>
                                                        <td>
                                                            <a class="btn btn-primary btn-sm" href="usuario_detalle.php?id=<?php echo $id; ?>">
                                                                <i class="fas fa-eye mr-1"></i>Ver información
                                                            </a>
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
        document.addEventListener('DOMContentLoaded', function () {
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
