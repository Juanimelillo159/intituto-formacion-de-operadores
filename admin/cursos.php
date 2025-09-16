<?php
require_once '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

// Traemos todos los cursos (DataTables hará paginación/orden/búsqueda en el cliente)
$sql_curso = $con->prepare(
    "SELECT c.*, comp.nombre_complejidad
     FROM cursos c
     LEFT JOIN complejidad comp ON c.id_complejidad = comp.id_complejidad
     ORDER BY c.id_curso DESC"
);
$sql_curso->execute();
$cursos = $sql_curso->fetchAll(PDO::FETCH_ASSOC);
$totalCursos = is_array($cursos) ? count($cursos) : 0;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- AdminLTE DataTables CSS -->
    <link rel="stylesheet" href="../adminlte/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="../adminlte/plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="../adminlte/plugins/datatables-buttons/css/buttons.bootstrap4.min.css">

    <!-- Font Awesome (si tu header no lo incluye ya) -->
    <link rel="stylesheet" href="../adminlte/plugins/fontawesome-free/css/all.min.css">
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">

                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Lista de Cursos</h3>
                                    <div class="card-tools">
                                        <a href="agregar_curso.php" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Nuevo Curso
                                        </a>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <small class="text-muted">
                                                Total cursos: <?php echo (int)$totalCursos; ?>
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Tabla de cursos (DataTable) -->
                                    <div class="table-responsive">
                                        <table id="tablaCursos" class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th style="width: 10px">#</th>
                                                    <th>Título</th>
                                                    <th>Descripción</th>
                                                    <th>Duración</th>
                                                    <th>Complejidad</th>
                                                    <th>Fecha creación</th>
                                                    <th style="width: 150px">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($cursos)): ?>
                                                    <?php foreach ($cursos as $curso): ?>
                                                        <tr>
                                                            <td><?php echo (int)$curso['id_curso']; ?></td>
                                                            <td>
                                                                <strong><?php echo htmlspecialchars($curso['nombre_curso'] ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                                                            </td>
                                                            <td>
                                                                <?php
                                                                $descripcionCompleta = $curso['descripcion_curso'] ?? '';
                                                                if (function_exists('mb_strlen')) {
                                                                    $descripcionRecortada = mb_strlen($descripcionCompleta, 'UTF-8') > 100
                                                                        ? mb_substr($descripcionCompleta, 0, 100, 'UTF-8') . '...'
                                                                        : $descripcionCompleta;
                                                                } else {
                                                                    $descripcionRecortada = strlen($descripcionCompleta) > 100
                                                                        ? substr($descripcionCompleta, 0, 100) . '...'
                                                                        : $descripcionCompleta;
                                                                }
                                                                echo htmlspecialchars($descripcionRecortada, ENT_QUOTES, 'UTF-8');
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($curso['duracion'])): ?>
                                                                    <span class="badge badge-info"><?php echo htmlspecialchars($curso['duracion'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-secondary">No especificada</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($curso['nombre_complejidad'])): ?>
                                                                    <span class="badge badge-success"><?php echo htmlspecialchars($curso['nombre_complejidad'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <?php else: ?>
                                                                    <span class="badge badge-secondary">Sin definir</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td
                                                                <?php
                                                                // Para orden correcto por fecha en DataTables, añadimos data-order en formato ISO.
                                                                $iso = !empty($curso['fecha_creacion']) ? date('Y-m-d', strtotime($curso['fecha_creacion'])) : '';
                                                                echo $iso ? 'data-order="' . htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') . '"' : '';
                                                                ?>>
                                                                <?php
                                                                if (!empty($curso['fecha_creacion'])) {
                                                                    $timestamp = strtotime($curso['fecha_creacion']);
                                                                    echo $timestamp ? date('d/m/Y', $timestamp) : htmlspecialchars($curso['fecha_creacion'], ENT_QUOTES, 'UTF-8');
                                                                } else {
                                                                    echo 'N/A';
                                                                }
                                                                ?>
                                                            </td>
                                                            <td>
                                                                <div class="btn-group" role="group">
                                                                    <a
                                                                        href="curso.php?id_curso=<?php echo urlencode((string)$curso['id_curso']); ?>"
                                                                        class="btn btn-info btn-sm" title="Ver curso">
                                                                        <i class="fas fa-eye"></i>
                                                                    </a>
                                                                    <a
                                                                        href="curso.php?id_curso=<?php echo urlencode((string)$curso['id_curso']); ?>&mode=edit"
                                                                        class="btn btn-warning btn-sm" title="Editar curso">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
                                                                    <button
                                                                        type="button"
                                                                        class="btn btn-danger btn-sm"
                                                                        title="Eliminar curso"
                                                                        onclick="confirmarEliminacion(<?php echo (int)$curso['id_curso']; ?>)">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="7" class="text-center">
                                                            <div class="py-4">
                                                                <i class="fas fa-graduation-cap fa-3x text-muted mb-3"></i>
                                                                <h5 class="text-muted">No se encontraron cursos</h5>
                                                                <p class="text-muted">Comienza creando tu primer curso</p>
                                                                <a href="agregar_curso.php" class="btn btn-primary">Crear Curso</a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- Eliminado: paginación/buscador del servidor (DataTables lo reemplaza) -->
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar -->
    <div class="modal fade" id="modalEliminar" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Eliminación</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    ¿Estás seguro de que deseas eliminar este curso? Esta acción no se puede deshacer.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <a href="#" id="btnConfirmarEliminar" class="btn btn-danger">Eliminar</a>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery y Bootstrap (normalmente ya vienen con AdminLTE/header.php) -->
    <script src="../adminlte/plugins/jquery/jquery.min.js"></script>
    <script src="../adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables & Plugins -->
    <script src="../adminlte/plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="../adminlte/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
    <script src="../adminlte/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
    <script src="../adminlte/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
    <script src="../adminlte/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
    <script src="../adminlte/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
    <script src="../adminlte/plugins/jszip/jszip.min.js"></script>
    <script src="../adminlte/plugins/pdfmake/pdfmake.min.js"></script>
    <script src="../adminlte/plugins/pdfmake/vfs_fonts.js"></script>
    <script src="../adminlte/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
    <script src="../adminlte/plugins/datatables-buttons/js/buttons.print.min.js"></script>
    <script src="../adminlte/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

    <script>
        // Confirmación de eliminación
        function confirmarEliminacion(cursoId) {
            $('#btnConfirmarEliminar').attr('href', 'eliminar_curso.php?id_curso=' + cursoId);
            $('#modalEliminar').modal('show');
        }

        // Inicialización DataTable estilo AdminLTE (features por defecto + Buttons)
        $(function() {
            const table = $("#tablaCursos").DataTable({
                responsive: true,
                lengthChange: true,
                autoWidth: false,
                // Order por ID desc (col 0) por defecto; si prefieres por fecha usa [5, 'desc']
                order: [
                    [0, 'desc']
                ],
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
                },
                // Conjunto de botones "default" de los ejemplos de AdminLTE
                buttons: ["copy", "csv", "excel", "pdf", "print", "colvis"]
            });

            table.buttons().container().appendTo('#tablaCursos_wrapper .col-md-6:eq(0)');
        });
    </script>

    <?php include '../admin/footer.php'; ?>
</body>

</html>