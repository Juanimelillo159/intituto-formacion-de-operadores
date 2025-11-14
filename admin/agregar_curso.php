<?php
include '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Agregar curso</title>
    <style>
        .nav-tabs .nav-link {
            color: #495057
        }

        .nav-tabs .nav-link.active {
            color: #007bff;
            font-weight: 600
        }

        .form-group label {
            font-weight: 600;
            color: #495057
        }

        .required-field::after {
            content: " *";
            color: #dc3545
        }

        .card-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: #fff
        }

        .btn-spacer {
            gap: 10px
        }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1><i class="fas fa-plus-circle"></i> Agregar Nuevo Curso</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
                                <li class="breadcrumb-item"><a href="cursos.php">Cursos</a></li>
                                <li class="breadcrumb-item active">Agregar Curso</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card card-primary card-outline card-tabs">
                                <div class="card-header p-0 pt-1 border-bottom-0">
                                    <ul class="nav nav-tabs" id="tabs-curso" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" id="info-basica-tab" data-toggle="tab" href="#info-basica" role="tab" aria-controls="info-basica" aria-selected="true">
                                                <i class="fas fa-info-circle"></i> Información Básica
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="contenido-tab" data-toggle="tab" href="#contenido" role="tab" aria-controls="contenido" aria-selected="false">
                                                <i class="fas fa-book"></i> Contenido del Curso
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="configuracion-tab" data-toggle="tab" href="#configuracion" role="tab" aria-controls="configuracion" aria-selected="false">
                                                <i class="fas fa-cogs"></i> Configuración
                                            </a>
                                        </li>
                                    </ul>
                                </div>

                                <div class="card-body">
                                    <form action="procesarsbd.php" method="POST" id="courseForm">
                                        <div class="tab-content" id="tabs-curso-content">

                                            <!-- TAB 1 -->
                                            <div class="tab-pane fade show active" id="info-basica" role="tabpanel" aria-labelledby="info-basica-tab">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="courseName" class="required-field"><i class="fas fa-graduation-cap"></i> Nombre del Curso</label>
                                                            <input required type="text" class="form-control form-control-lg" id="courseName" name="nombre" placeholder="Ingrese el nombre del curso">
                                                            <small class="form-text text-muted">Nombre descriptivo y atractivo del curso</small>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="courseDuration" class="required-field"><i class="fas fa-clock"></i> Duración</label>
                                                            <input required type="text" class="form-control" id="courseDuration" name="duracion" placeholder="Ej: 20 horas, 3 semanas">
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label for="courseDescription" class="required-field"><i class="fas fa-align-left"></i> Descripción</label>
                                                    <textarea required class="form-control" id="courseDescription" rows="4" name="descripcion" placeholder="Descripción detallada del curso"></textarea>
                                                    <small class="form-text text-muted">Descripción que aparecerá en el catálogo de cursos</small>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="precio_capacitacion" class="required-field"><i class="fas fa-chalkboard-teacher"></i> Precio capacitación (ARS)</label>
                                                            <input required type="text" inputmode="decimal" class="form-control" id="precio_capacitacion" name="precio_capacitacion" placeholder="Ej: 120000,00 o 120000.00">
                                                            <small class="form-text text-muted">Usá coma o punto como separador decimal.</small>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="precio_certificacion"><i class="fas fa-certificate"></i> Precio certificación (ARS)</label>
                                                            <input type="text" inputmode="decimal" class="form-control" id="precio_certificacion" name="precio_certificacion" placeholder="Ingresá el valor si aplica">
                                                            <small class="form-text text-muted">Completalo solo si el curso tiene certificación.</small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="d-flex justify-content-end btn-spacer">
                                                    <button type="button" class="btn btn-primary" id="btnNext1">
                                                        Siguiente <i class="fas fa-arrow-right ml-1"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <!-- TAB 2 -->
                                            <div class="tab-pane fade" id="contenido" role="tabpanel" aria-labelledby="contenido-tab">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="courseObjectives" class="required-field"><i class="fas fa-bullseye"></i> Objetivos de Aprendizaje</label>
                                                            <textarea required class="form-control" id="courseObjectives" rows="4" name="objetivos" placeholder="¿Qué aprenderán los participantes al finalizar?"></textarea>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="coursePublico"><i class="fas fa-users"></i> Público Objetivo</label>
                                                            <textarea class="form-control" id="coursePublico" rows="3" name="publico" placeholder="¿A quién está dirigido este curso?"></textarea>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="coursePrograma"><i class="fas fa-list-ol"></i> Programa del Curso</label>
                                                            <textarea class="form-control" id="coursePrograma" rows="4" name="programa" placeholder="Detalle de módulos, temas y contenidos"></textarea>
                                                        </div>
                                                        <div class="form-group">
                                                            <label for="courseCronograma"><i class="fas fa-calendar-alt"></i> Cronograma</label>
                                                            <textarea class="form-control" id="courseCronograma" rows="3" name="cronograma" placeholder="Distribución temporal del curso"></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="form-group">
                                                    <label for="courseRequisitos"><i class="fas fa-check-circle"></i> Requisitos Previos</label>
                                                    <textarea class="form-control" id="courseRequisitos" rows="3" name="requisitos" placeholder="Conocimientos, experiencia o documentos requeridos"></textarea>
                                                </div>

                                                <div class="d-flex justify-content-between btn-spacer">
                                                    <button type="button" class="btn btn-secondary" id="btnPrev2"><i class="fas fa-arrow-left mr-1"></i> Anterior</button>
                                                    <button type="button" class="btn btn-primary" id="btnNext2">Siguiente <i class="fas fa-arrow-right ml-1"></i></button>
                                                </div>
                                            </div>

                                            <!-- TAB 3 -->
                                            <div class="tab-pane fade" id="configuracion" role="tabpanel" aria-labelledby="configuracion-tab">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label><i class="fas fa-desktop"></i> Modalidades Disponibles</label>
                                                            <div class="card">
                                                                <div class="card-body">
                                                                    <?php
                                                                    $sql_modalidades = $con->prepare("SELECT * FROM modalidades");
                                                                    $sql_modalidades->execute();
                                                                    $modalidades = $sql_modalidades->fetchAll(PDO::FETCH_ASSOC);
                                                                    foreach ($modalidades as $modalidad) { ?>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="checkbox" name="modalidades[]"
                                                                                value="<?php echo $modalidad['id_modalidad']; ?>"
                                                                                id="modalidad_<?php echo $modalidad['id_modalidad']; ?>">
                                                                            <label class="form-check-label" for="modalidad_<?php echo $modalidad['id_modalidad']; ?>">
                                                                                <?php echo $modalidad['nombre_modalidad']; ?>
                                                                            </label>
                                                                        </div>
                                                                    <?php } ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-md-6">
                                                        <div class="form-group">
                                                            <label for="courseObservaciones"><i class="fas fa-sticky-note"></i> Observaciones Adicionales</label>
                                                            <textarea class="form-control" id="courseObservaciones" rows="6" name="observaciones" placeholder="Notas, comentarios o información adicional"></textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="d-flex justify-content-between btn-spacer">
                                                    <button type="button" class="btn btn-secondary" id="btnPrev3"><i class="fas fa-arrow-left mr-1"></i> Anterior</button>
                                                    <div>
                                                        <button type="button" class="btn btn-warning mr-2" onclick="limpiarFormulario()"><i class="fas fa-eraser"></i> Limpiar</button>
                                                        <button type="submit" name="agregar_curso" class="btn btn-success"><i class="fas fa-save"></i> Guardar Curso</button>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </form>
                                </div><!-- /.card-body -->
                            </div><!-- /.card -->
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
        function volver() {
            if (confirm('¿Está seguro que desea salir? Los cambios no guardados se perderán.')) {
                window.location.href = 'cursos.php';
            }
        }

        function limpiarFormulario() {
            if (confirm('¿Está seguro que desea limpiar todos los campos?')) {
                document.getElementById('courseForm').reset();
                $('.is-valid, .is-invalid').removeClass('is-valid is-invalid');
            }
        }

        function showTab(targetSelector) {
            $('a[data-toggle="tab"][href="' + targetSelector + '"]').tab('show');
        }

        // Validaciones por pestaña
        function validateTab1() {
            let ok = true,
                errores = [];
            const req = [{
                    sel: '#courseName',
                    label: 'Nombre del Curso'
                },
                {
                    sel: '#courseDuration',
                    label: 'Duración'
                },
                {
                    sel: '#courseDescription',
                    label: 'Descripción'
                },
                {
                    sel: '#precio_capacitacion',
                    label: 'Precio capacitación'
                }
            ];
            req.forEach(c => {
                const $el = $(c.sel);
                if (!$el.val() || $el.val().toString().trim() === '') {
                    ok = false;
                    $el.addClass('is-invalid').removeClass('is-valid');
                    errores.push(c.label);
                } else {
                    $el.addClass('is-valid').removeClass('is-invalid');
                }
            });
            if (!ok) {
                Swal.fire({
                    icon: 'error',
                    title: 'Faltan campos',
                    html: '<div style="text-align:left;">' + errores.map(x => '• ' + x).join('<br>') + '</div>'
                });
            }
            return ok;
        }

        function validateTab2() {
            let ok = true,
                errores = [];
            const req = [{
                sel: '#courseObjectives',
                label: 'Objetivos de Aprendizaje'
            }];
            req.forEach(c => {
                const $el = $(c.sel);
                if (!$el.val() || $el.val().toString().trim() === '') {
                    ok = false;
                    $el.addClass('is-invalid').removeClass('is-valid');
                    errores.push(c.label);
                } else {
                    $el.addClass('is-valid').removeClass('is-invalid');
                }
            });
            if (!ok) {
                Swal.fire({
                    icon: 'error',
                    title: 'Faltan campos',
                    html: '<div style="text-align:left;">' + errores.map(x => '• ' + x).join('<br>') + '</div>'
                });
            }
            return ok;
        }

        function validateFinal() {
            let ok = true,
                errores = [];
            if ($('input[name="modalidades[]"]:checked').length === 0) {
                ok = false;
                errores.push('Seleccionar al menos una modalidad');
            }
            if (!ok) {
                Swal.fire({
                    icon: 'error',
                    title: 'Revisión final',
                    html: '<div style="text-align:left;">' + errores.map(x => '• ' + x).join('<br>') + '</div>'
                });
            }
            return ok;
        }

        $(function() {
            $('#btnNext1').on('click', function() {
                if (validateTab1()) showTab('#contenido');
            });
            $('#btnPrev2').on('click', function() {
                showTab('#info-basica');
            });
            $('#btnNext2').on('click', function() {
                if (validateTab2()) showTab('#configuracion');
            });
            $('#btnPrev3').on('click', function() {
                showTab('#contenido');
            });

            // Validación final antes de enviar
            $('#courseForm').on('submit', function(e) {
                const v1 = validateTab1(),
                    v2 = validateTab2(),
                    vf = validateFinal();
                if (!(v1 && v2 && vf)) e.preventDefault();
            });

            // Marca valid/invalid dinámico
            $(document).on('input change', 'input, textarea, select', function() {
                if ($(this).val() && $(this).val().toString().trim() !== '') {
                    $(this).removeClass('is-invalid').addClass('is-valid');
                } else {
                    $(this).removeClass('is-valid');
                }
            });
        });
    </script>
</body>

</html>