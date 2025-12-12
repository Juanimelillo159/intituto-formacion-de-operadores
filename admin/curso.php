<?php
include '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$id_curso = isset($_GET["id_curso"]) ? (int)$_GET["id_curso"] : 0;

// Traer curso
$sql_curso_id = $con->prepare("SELECT * FROM cursos WHERE id_curso = :id_curso");
$sql_curso_id->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
$sql_curso_id->execute();
$curso = $sql_curso_id->fetch(PDO::FETCH_ASSOC);

if (!$curso) {
  echo "<div class='content-wrapper'><section class='content'><div class='container-fluid'><div class='alert alert-danger'>Curso no encontrado.</div></div></section></div>";
  exit;
}

// Mapear campos
$nombre        = $curso["nombre_curso"] ?? '';
$descripcion   = $curso["descripcion_curso"] ?? '';
$duracion      = $curso["duracion"] ?? '';
$objetivos     = $curso["objetivos"] ?? '';
$programa      = $curso["programa"] ?? '';
$publico       = $curso["publico"] ?? '';
$cronograma    = $curso["cronograma"] ?? '';
$prerrequisitos = $curso["prerrequisitos"] ?? '';
$observaciones = $curso["observaciones"] ?? '';

// Información específica para certificación
$descripcionCertificacion    = $curso["descripcion_certificacion"] ?? $descripcion;
$requisitosEvaluacionCert    = $curso["requisitos_evaluacion_certificacion"] ?? '';
$procesoCertificacion        = $curso["proceso_certificacion"] ?? '';
$alcanceCertificacion        = $curso["alcance_certificacion"] ?? '';
$prerrequisitosCertificacion = $curso["prerrequisitos_certificacion"] ?? $prerrequisitos;
$vigenciaCertificacion       = $curso["vigencia_certificacion"] ?? '';
$documentacionCertificacion  = $curso["documentacion_certificacion"] ?? ($curso["documentacion"] ?? '');
$plazoCertificacion          = $curso["plazo_certificacion"] ?? '';

// Modalidades
$sql_curso_modalidades = $con->prepare("SELECT id_modalidad FROM curso_modalidad WHERE id_curso = :id_curso");
$sql_curso_modalidades->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
$sql_curso_modalidades->execute();
$curso_modalidades = $sql_curso_modalidades->fetchAll(PDO::FETCH_COLUMN);

$sql_modalidades = $con->prepare("SELECT * FROM modalidades ORDER BY id_modalidad");
$sql_modalidades->execute();
$modalidades = $sql_modalidades->fetchAll(PDO::FETCH_ASSOC);

// ====== PRECIOS ======
$tiposPrecio = [
  'capacitacion' => 'Capacitaciones',
  'certificacion' => 'Certificaciones',
];

$sql_hist = $con->prepare("
  SELECT id, precio, moneda, vigente_desde, vigente_hasta, comentario
    FROM curso_precio_hist
   WHERE id_curso = :id
     AND tipo_curso = :tipo
ORDER BY vigente_desde DESC
");
$sql_vig = $con->prepare("
  SELECT precio, moneda, vigente_desde, vigente_hasta
    FROM curso_precio_hist
   WHERE id_curso = :id
     AND tipo_curso = :tipo
     AND vigente_desde <= NOW()
     AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
ORDER BY vigente_desde DESC
   LIMIT 1
");
$sql_next = $con->prepare("
  SELECT precio, moneda, vigente_desde
    FROM curso_precio_hist
   WHERE id_curso = :id
     AND tipo_curso = :tipo
     AND vigente_desde > NOW()
ORDER BY vigente_desde ASC
   LIMIT 1
");

$preciosPorTipo = [];
foreach ($tiposPrecio as $tipoClave => $tipoLabel) {
  $sql_hist->execute([':id' => $id_curso, ':tipo' => $tipoClave]);
  $historialTipo = $sql_hist->fetchAll(PDO::FETCH_ASSOC);

  $sql_vig->execute([':id' => $id_curso, ':tipo' => $tipoClave]);
  $precioVigenteTipo = $sql_vig->fetch(PDO::FETCH_ASSOC);

  $sql_next->execute([':id' => $id_curso, ':tipo' => $tipoClave]);
  $precioProximoTipo = $sql_next->fetch(PDO::FETCH_ASSOC);

  $preciosPorTipo[$tipoClave] = [
    'label' => $tipoLabel,
    'historial' => $historialTipo,
    'vigente' => $precioVigenteTipo,
    'proximo' => $precioProximoTipo,
  ];
}

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function fmt_fecha($s)
{
  return $s ? date('Y-m-d H:i:s', strtotime($s)) : '';
}
function estado_precio($vd, $vh)
{
  $now = new DateTime('now');
  $d = new DateTime($vd);
  $h = $vh ? new DateTime($vh) : null;
  if ($d > $now) return ['Programado', 'badge-info'];
  if ($h === null || $h > $now) return ['Vigente', 'badge-success'];
  return ['Vencido', 'badge-secondary'];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Detalle del Curso - <?php echo h($nombre) ?></title>

  <style>
    .course-header {
      background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
      color: #fff;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
      position: relative;
    }

    .nav-tabs .nav-link.active {
      background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
      color: #fff;
      border-radius: 8px 8px 0 0;
    }

    .tab-content {
      background: #fff;
      border-radius: 0 8px 8px 8px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    }

    .edit-mode .form-control {
      border-color: #007bff !important;
      box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .15) !important;
    }

    .complexity-badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: .85rem;
      font-weight: 500;
      margin-left: 10px;
      background: #e9ecef;
      color: #343a40;
    }

    .status-indicator {
      position: absolute;
      top: 15px;
      right: 15px;
      padding: 5px 10px;
      border-radius: 15px;
      font-size: .8rem;
      font-weight: 500;
    }

    .status-view {
      background-color: #e7f1ff;
      color: #0c63e4;
    }

    .status-edit {
      background-color: #fff3cd;
      color: #856404;
    }

    .table-sm td,
    .table-sm th {
      padding: .35rem .5rem;
    }

    .required-field::after {
      content: " *";
      color: #dc3545;
    }

    .course-header h1 {
      font-weight: 700;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .edit-name-btn {
      border: none;
      background: transparent;
      color: #fff;
      padding: 6px;
      border-radius: 6px;
      transition: background-color 0.2s ease;
    }

    .edit-name-btn:hover,
    .edit-name-btn:focus {
      background-color: rgba(255, 255, 255, 0.15);
      outline: none;
      color: #fff;
    }
  </style>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>

<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <div class="content-wrapper">
      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6">
              <h1><i class="fas fa-graduation-cap"></i> Gestión de Cursos</h1>
            </div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="../admin/dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
                <li class="breadcrumb-item"><a href="cursos.php"><i class="fas fa-list"></i> Cursos</a></li>
                <li class="breadcrumb-item active"><i class="fas fa-eye"></i> Detalle</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <section class="content">
        <div class="container-fluid">
          <div class="course-header">
            <div class="status-indicator status-view" id="statusIndicator">
              <i class="fas fa-eye"></i> Modo Vista
            </div>
            <div class="course-id">Curso ID: #<?php echo $id_curso ?></div>
            <h1 class="mb-1 d-flex align-items-center flex-wrap" style="gap: 10px;">
              <span id="courseNameText"><?php echo h($nombre) ?></span>
              <input required disabled form="form" value="<?php echo h($nombre) ?>" type="text" class="form-control form-control-sm d-none" id="courseName" name="nombre">
              <button type="button" class="edit-name-btn" id="btnEditarNombre" aria-label="Editar nombre del curso">
                <i class="fas fa-pencil-alt"></i>
              </button>
            </h1>
            <p class="mb-0">
              <i class="fas fa-clock"></i> <?php echo h($duracion) ?>
            </p>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="card card-outline card-primary">
                <div class="card-header p-0 border-bottom-0">
                  <ul class="nav nav-tabs" id="custom-tabs" role="tablist">
                    <li class="nav-item">
                      <a class="nav-link active" id="cap-tab" data-toggle="pill" href="#cap-info" role="tab" aria-label="Información de Capacitación">
                        <i class="fas fa-chalkboard-teacher"></i> Capacitación
                      </a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" id="cert-tab" data-toggle="pill" href="#cert-info" role="tab" aria-label="Información de Certificación">
                        <i class="fas fa-certificate"></i> Certificación
                      </a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" id="config-tab" data-toggle="pill" href="#course-config" role="tab" aria-label="Configuración">
                        <i class="fas fa-cogs"></i> Configuración
                      </a>
                    </li>
                    <li class="nav-item">
                      <a class="nav-link" id="prices-tab" data-toggle="pill" href="#course-prices" role="tab" aria-label="Precios">
                        <i class="fas fa-dollar-sign"></i> Precios
                      </a>
                    </li>
                  </ul>
                </div>

                <!-- UN SOLO FORM con novalidate -->
                <form id="form" action="procesarsbd.php" method="POST" novalidate>
                  <input type="hidden" name="id_curso" value="<?php echo (int)$id_curso ?>">
                  <!-- lo uso cuando envío por JS para indicar la acción -->
                  <input type="hidden" id="__accion" name="__accion" value="">

                  <div class="tab-content" id="custom-tabsContent">
                    <!-- Información de capacitación -->
                    <div class="tab-pane fade show active" id="cap-info" role="tabpanel">
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="courseDuration" class="required-field"><i class="fas fa-clock"></i> Duración (Capacitación)</label>
                              <input required disabled value="<?php echo h($duracion) ?>" type="text" class="form-control" id="courseDuration" name="duracion">
                            </div>
                          </div>
                        </div>

                        <div class="form-group">
                          <label for="courseDescription" class="required-field"><i class="fas fa-align-left"></i> Descripción (Capacitación)</label>
                          <textarea required disabled class="form-control" id="courseDescription" rows="4" name="descripcion"><?php echo h($descripcion) ?></textarea>
                        </div>

                        <div class="form-group">
                          <label for="publico"><i class="fas fa-users"></i> Público Objetivo</label>
                          <textarea disabled class="form-control" id="publico" rows="3" name="publico"><?php echo h($publico) ?></textarea>
                        </div>

                        <div class="form-group">
                          <label for="courseObjectives" class="required-field"><i class="fas fa-bullseye"></i> Objetivos de Aprendizaje</label>
                          <textarea required disabled class="form-control" id="courseObjectives" rows="4" name="objetivos"><?php echo h($objetivos) ?></textarea>
                        </div>

                        <div class="form-group">
                          <label for="programa"><i class="fas fa-list-ol"></i> Programa del Curso</label>
                          <textarea disabled class="form-control" id="programa" rows="6" name="programa"><?php echo h($programa) ?></textarea>
                        </div>

                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="cronograma"><i class="fas fa-calendar-alt"></i> Cronograma</label>
                              <textarea disabled class="form-control" id="cronograma" rows="4" name="cronograma"><?php echo h($cronograma) ?></textarea>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="prerrequisitos"><i class="fas fa-check-circle"></i> Prerrequisitos</label>
                              <textarea disabled class="form-control" id="prerrequisitos" rows="4" name="prerrequisitos"><?php echo h($prerrequisitos) ?></textarea>
                            </div>
                          </div>
                        </div>

                        <div class="form-group">
                          <label for="observaciones"><i class="fas fa-sticky-note"></i> Observaciones Adicionales</label>
                          <textarea disabled class="form-control" id="observaciones" rows="4" name="observaciones"><?php echo h($observaciones) ?></textarea>
                        </div>
                      </div>
                    </div>

                    <!-- Información de certificación -->
                    <div class="tab-pane fade" id="cert-info" role="tabpanel">
                      <div class="card-body">
                        <div class="form-group">
                          <label for="certDescription" class="required-field"><i class="fas fa-align-left"></i> Descripción (Certificación)</label>
                          <textarea required disabled class="form-control" id="certDescription" rows="4" name="descripcion_certificacion"><?php echo h($descripcionCertificacion) ?></textarea>
                        </div>

                        <div class="form-group">
                          <label for="certEvaluation"><i class="fas fa-clipboard-check"></i> Requisitos de evaluación</label>
                          <textarea disabled class="form-control" id="certEvaluation" rows="4" name="requisitos_evaluacion_certificacion"><?php echo h($requisitosEvaluacionCert) ?></textarea>
                        </div>

                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="certProcess"><i class="fas fa-sitemap"></i> Proceso de certificación</label>
                              <textarea disabled class="form-control" id="certProcess" rows="3" name="proceso_certificacion"><?php echo h($procesoCertificacion) ?></textarea>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="certScope"><i class="fas fa-bullseye"></i> Alcance</label>
                              <textarea disabled class="form-control" id="certScope" rows="3" name="alcance_certificacion"><?php echo h($alcanceCertificacion) ?></textarea>
                            </div>
                          </div>
                        </div>

                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="certPrerrequisitos"><i class="fas fa-check-circle"></i> Prerrequisitos</label>
                              <textarea disabled class="form-control" id="certPrerrequisitos" rows="3" name="prerrequisitos_certificacion"><?php echo h($prerrequisitosCertificacion) ?></textarea>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="certVigencia"><i class="fas fa-hourglass-half"></i> Vigencia y renovación</label>
                              <textarea disabled class="form-control" id="certVigencia" rows="3" name="vigencia_certificacion"><?php echo h($vigenciaCertificacion) ?></textarea>
                            </div>
                          </div>
                        </div>

                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="certDocs"><i class="fas fa-file-alt"></i> Documentación</label>
                              <textarea disabled class="form-control" id="certDocs" rows="3" name="documentacion_certificacion"><?php echo h($documentacionCertificacion) ?></textarea>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="certPlazo"><i class="fas fa-clock"></i> Plazos</label>
                              <input disabled type="text" class="form-control" id="certPlazo" name="plazo_certificacion" value="<?php echo h($plazoCertificacion) ?>">
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Configuración -->
                    <div class="tab-pane fade" id="course-config" role="tabpanel">
                      <div class="card-body">
                        <div class="form-group">
                          <label><i class="fas fa-desktop"></i> Modalidades de Enseñanza</label>
                          <div class="row">
                            <?php foreach ($modalidades as $modalidad): ?>
                              <?php
                              $id_m  = (int)$modalidad['id_modalidad'];
                              $nom_m = $modalidad['nombre_modalidad'] ?? ('Modalidad ' . $id_m);
                              $checked = in_array($id_m, $curso_modalidades) ? 'checked' : '';
                              ?>
                              <div class="col-md-4 mb-2">
                                <div class="custom-control custom-checkbox">
                                  <input disabled class="custom-control-input" type="checkbox" id="mod_<?php echo $id_m; ?>"
                                    name="modalidades[]" value="<?php echo $id_m; ?>" <?php echo $checked; ?>>
                                  <label class="custom-control-label" for="mod_<?php echo $id_m; ?>"><?php echo h($nom_m); ?></label>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- PRECIOS -->
                    <div class="tab-pane fade" id="course-prices" role="tabpanel">
                      <div class="card-body">
                        <?php foreach ($preciosPorTipo as $tipoClave => $infoPrecio): ?>
                          <div class="mb-5">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                              <h5 class="mb-0"><i class="fas fa-tags"></i> <?php echo h($infoPrecio['label']); ?></h5>
                              <span class="badge badge-pill badge-primary text-uppercase"><?php echo h($tipoClave); ?></span>
                            </div>
                            <div class="row mb-3">
                              <div class="col-md-6">
                                <div class="alert alert-success mb-2">
                                  <strong><i class="fas fa-check-circle"></i> Precio vigente:</strong>
                                  <?php $pv = $infoPrecio['vigente']; ?>
                                  <?php if ($pv): ?>
                                    <?php echo 'ARS ' . number_format((float)$pv['precio'], 2, ',', '.'); ?>
                                    <span class="d-block small text-muted">Desde <?php echo h(fmt_fecha($pv['vigente_desde'])); ?></span>
                                  <?php else: ?>
                                    —
                                  <?php endif; ?>
                                </div>
                              </div>
                              <div class="col-md-6">
                                <div class="alert alert-info mb-2">
                                  <strong><i class="fas fa-clock"></i> Próximo programado:</strong>
                                  <?php $pp = $infoPrecio['proximo']; ?>
                                  <?php if ($pp): ?>
                                    <?php echo 'ARS ' . number_format((float)$pp['precio'], 2, ',', '.'); ?>
                                    <span class="d-block small text-muted">Desde <?php echo h(fmt_fecha($pp['vigente_desde'])); ?></span>
                                  <?php else: ?>
                                    —
                                  <?php endif; ?>
                                </div>
                              </div>
                            </div>

                            <div class="table-responsive">
                              <table class="table table-sm table-hover">
                                <thead>
                                  <tr>
                                    <th>Estado</th>
                                    <th>Precio</th>
                                    <th>Moneda</th>
                                    <th>Vigente desde</th>
                                    <th>Vigente hasta</th>
                                    <th>Comentario</th>
                                  </tr>
                                </thead>
                                <tbody>
                                  <?php if (empty($infoPrecio['historial'])): ?>
                                    <tr>
                                      <td colspan="6" class="text-center text-muted">Sin registros de precio</td>
                                    </tr>
                                  <?php else: ?>
                                    <?php foreach ($infoPrecio['historial'] as $p): [$est, $badge] = estado_precio($p['vigente_desde'], $p['vigente_hasta']); ?>
                                      <tr>
                                        <td><span class="badge <?php echo $badge; ?>"><?php echo $est; ?></span></td>
                                        <td><?php echo 'ARS ' . number_format((float)$p['precio'], 2, ',', '.'); ?></td>
                                        <td><?php echo h($p['moneda'] ?: 'ARS'); ?></td>
                                        <td><?php echo h(fmt_fecha($p['vigente_desde'])); ?></td>
                                        <td><?php echo h($p['vigente_hasta'] ? fmt_fecha($p['vigente_hasta']) : '—'); ?></td>
                                        <td><?php echo h($p['comentario'] ?? ''); ?></td>
                                      </tr>
                                    <?php endforeach; ?>
                                  <?php endif; ?>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        <?php endforeach; ?>

                        <!-- NUEVO PRECIO (estos inputs van dentro del MISMO form) -->
                        <hr>
                        <h5 class="mb-3"><i class="fas fa-plus-circle"></i> Nuevo precio</h5>
                        <div class="form-row">
                          <div class="form-group col-md-3">
                            <label for="tipo_precio_nuevo" class="required-field">Tipo</label>
                            <select disabled class="form-control" id="tipo_precio_nuevo" name="tipo_precio">
                              <?php foreach ($tiposPrecio as $tipoClave => $tipoLabel): ?>
                                <option value="<?php echo h($tipoClave); ?>"><?php echo h($tipoLabel); ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                          <div class="form-group col-md-3">
                            <label for="precio_nuevo" class="required-field">Precio (ARS)</label>
                            <input disabled type="text" inputmode="decimal" class="form-control" id="precio_nuevo" name="precio" placeholder="Ej: 120000,00">
                          </div>
                          <div class="form-group col-md-3">
                            <label for="desde_nuevo" class="required-field">Vigente desde</label>
                            <input disabled type="datetime-local" class="form-control" id="desde_nuevo" name="desde" required>
                          </div>
                          <div class="form-group col-md-3">
                            <label for="comentario_nuevo">Comentario (opcional)</label>
                            <input disabled type="text" class="form-control" id="comentario_nuevo" name="comentario" maxlength="255" placeholder="Motivo / nota interna">
                          </div>
                        </div>
                        <div>
                          <!-- este botón envía el MISMO form, pero marcamos la acción por JS -->
                          <button type="button" class="btn btn-success d-none" id="btnGuardarPrecio">
                            <i class="fas fa-save"></i> Guardar nuevo precio
                          </button>
                        </div>
                      </div>
                    </div>
                    <!-- /PRECIOS -->
                  </div>

                  <div class="card-footer">
                    <div class="d-flex flex-wrap" style="gap:10px;">
                      <button type="button" class="btn btn-warning btn-lg" id="btnEditar"><i class="fas fa-edit"></i> Editar Curso</button>
                      <button type="button" class="btn btn-success btn-lg d-none" id="btnGuardar"><i class="fas fa-save"></i> Guardar Cambios</button>
                      <button type="button" class="btn btn-secondary btn-lg d-none" id="btnCancelar"><i class="fas fa-times"></i> Cancelar</button>
                      <button type="button" class="btn btn-primary btn-lg" id="btnVolver"><i class="fas fa-arrow-left"></i> Volver a Lista</button>
                    </div>
                  </div>
                </form><!-- FIN ÚNICO FORM -->

              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>

  <script>
    // Datos para validación rápida (opcional)
    const rangosPrecio = <?php
                          $js = [];
                          foreach ($preciosPorTipo as $tipoClave => $infoPrecio) {
                            $rangos = [];
                            foreach ($infoPrecio['historial'] as $p) {
                              $rangos[] = ['desde' => $p['vigente_desde'], 'hasta' => $p['vigente_hasta']];
                            }
                            $js[$tipoClave] = $rangos;
                          }
                          echo json_encode($js, JSON_UNESCAPED_UNICODE);
                          ?>;
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('form');
      const btnEditar = document.getElementById('btnEditar');
      const btnGuardar = document.getElementById('btnGuardar');
      const btnCancelar = document.getElementById('btnCancelar');
      const btnVolver = document.getElementById('btnVolver');
      const btnGuardarPrecio = document.getElementById('btnGuardarPrecio');
      const btnEditarNombre = document.getElementById('btnEditarNombre');
      const courseNameInput = document.getElementById('courseName');
      const courseNameText = document.getElementById('courseNameText');
      const originalName = courseNameInput ? courseNameInput.value : '';
      const accion = document.getElementById('__accion');

      // ---- NUEVOS FLAGS ----
      let isEditing = false; // estoy en modo edición?
      let isDirty = false; // hubo cambios en los campos?
      let isSubmitting = false; // se está guardando? (evita el beforeunload)

      // marcá sucios los cambios en cualquier input/textarea/select del form
      function attachDirtyListeners() {
        const sel = '#form input, #form textarea, #form select';
        document.querySelectorAll(sel).forEach(el => {
          el.addEventListener('input', () => {
            if (isEditing) isDirty = true;
          }, {
            passive: true
          });
          el.addEventListener('change', () => {
            if (isEditing) isDirty = true;
          }, {
            passive: true
          });
        });
      }
      attachDirtyListeners();

      // helpers existentes tuyos...
      function setDisabledAll(disabled) {
        const ids = [
          // Curso
          'courseName', 'courseDescription', 'courseDuration', 'courseObjectives',
          'programa', 'publico', 'cronograma', 'prerrequisitos', 'observaciones',
          // Certificación (solo los campos activos en el front)
          'certDescription', 'certEvaluation', 'certProcess', 'certScope',
          'certPrerrequisitos', 'certVigencia', 'certDocs', 'certPlazo',
          // Precios
          'tipo_precio_nuevo', 'precio_nuevo', 'desde_nuevo', 'comentario_nuevo'
        ];
        ids.forEach(id => {
          const el = document.getElementById(id);
          if (el) el.disabled = disabled;
        });
        document.querySelectorAll('input[type="checkbox"][name="modalidades[]"]').forEach(cb => cb.disabled = disabled);
        form.classList.toggle('edit-mode', !disabled);
      }

      function toggleCourseNameDisplay(editing) {
        if (!courseNameInput || !courseNameText) return;
        if (editing) {
          courseNameText.classList.add('d-none');
          courseNameInput.classList.remove('d-none');
        } else {
          courseNameInput.classList.add('d-none');
          courseNameText.classList.remove('d-none');
          courseNameText.textContent = courseNameInput.value;
        }
      }

      function setButtons(editMode) {
        btnCancelar?.classList.toggle('d-none', !editMode);
        btnGuardar?.classList.toggle('d-none', !editMode);
        btnEditar?.classList.toggle('d-none', editMode);
        btnVolver?.classList.toggle('d-none', editMode);
        btnGuardarPrecio?.classList.toggle('d-none', !editMode);
      }

      function setStatus(editMode) {
        const si = document.getElementById('statusIndicator');
        if (!si) return;
        if (editMode) {
          si.innerHTML = '<i class="fas fa-edit"></i> Modo Edición';
          si.className = 'status-indicator status-edit';
        } else {
          si.innerHTML = '<i class="fas fa-eye"></i> Modo Vista';
          si.className = 'status-indicator status-view';
        }
      }

      function markFieldValidity(field, isValid) {
        if (!field) return;
        if (isValid) {
          field.classList.remove('is-invalid');
          field.removeAttribute('aria-invalid');
        } else {
          field.classList.add('is-invalid');
          field.setAttribute('aria-invalid', 'true');
        }
      }

      function clearPriceValidation() {
        ['tipo_precio_nuevo', 'precio_nuevo', 'desde_nuevo'].forEach(id => {
          markFieldValidity(document.getElementById(id), true);
        });
      }

      function validarPrecio() {
        const tipo = document.getElementById('tipo_precio_nuevo');
        const precio = document.getElementById('precio_nuevo');
        const desde = document.getElementById('desde_nuevo');

        const errores = [];
        const invalidFields = [];

        if (!tipo || !tipo.value) {
          errores.push('Debe seleccionar el tipo de precio.');
          invalidFields.push(tipo);
          markFieldValidity(tipo, false);
        } else {
          markFieldValidity(tipo, true);
        }

        if (!precio || !precio.value.trim()) {
          errores.push('El precio es obligatorio.');
          invalidFields.push(precio);
          markFieldValidity(precio, false);
        } else {
          markFieldValidity(precio, true);
        }

        if (!desde || !desde.value.trim()) {
          errores.push('Debe indicar la fecha y hora de vigencia.');
          invalidFields.push(desde);
          markFieldValidity(desde, false);
        } else {
          markFieldValidity(desde, true);
        }

        if (errores.length) {
          Swal.fire({
            icon: 'warning',
            title: 'Completar datos del nuevo precio',
            html: '<ul class="text-left mb-0"><li>' + errores.join('</li><li>') + '</li></ul>'
          });
          if (invalidFields[0]) {
            invalidFields[0].focus();
          }
          return false;
        }

        return true;
      }

      function swalConfirm({
        title,
        text,
        icon = 'question',
        confirmText = 'Sí',
        cancelText = 'Cancelar'
      }) {
        return Swal.fire({
          title,
          text,
          icon,
          showCancelButton: true,
          confirmButtonText: confirmText,
          cancelButtonText: cancelText,
          reverseButtons: true,
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-primary mx-1',
            cancelButton: 'btn btn-outline-secondary mx-1'
          }
        });
      }

      // ---- ENTRAR / SALIR DE EDICIÓN ----
      btnEditar?.addEventListener('click', async () => {
        const r = await swalConfirm({
          title: 'Editar curso',
          text: '¿Desea habilitar la edición de este curso?',
          confirmText: 'Sí, editar'
        });
        if (!r.isConfirmed) return;
        isEditing = true;
        isDirty = false; // recién entro, aún sin cambios
        setDisabledAll(false);
        setButtons(true);
        setStatus(true);
        toggleCourseNameDisplay(true);
        courseNameInput?.focus();
      });

      btnCancelar?.addEventListener('click', async () => {
        const r = await swalConfirm({
          title: 'Cancelar cambios',
          text: 'Se perderán los cambios no guardados. ¿Confirmás?',
          icon: 'warning',
          confirmText: 'Sí, cancelar'
        });
        if (!r.isConfirmed) return;
        form.reset();
        isEditing = false;
        isDirty = false;
        clearPriceValidation();
        setDisabledAll(true);
        setButtons(false);
        setStatus(false);
        if (courseNameInput) courseNameInput.value = originalName;
        toggleCourseNameDisplay(false);
        Swal.fire({
          toast: true,
          position: 'top-end',
          timer: 1400,
          showConfirmButton: false,
          icon: 'success',
          title: 'Cambios descartados'
        });
      });

      btnEditarNombre?.addEventListener('click', () => {
        btnEditar?.click();
      });

      toggleCourseNameDisplay(false);

      // ---- GUARDAR CURSO ----
      btnGuardar?.addEventListener('click', async () => {
        // acá mantené tu validación custom (abre pestaña, focus, etc.)
        // if (!validarGeneral()) return;  // <- si ya la tenés definida
        const r = await swalConfirm({
          title: 'Guardar cambios',
          text: '¿Confirmás que querés guardar los cambios?',
          icon: 'question',
          confirmText: 'Sí, guardar'
        });
        if (!r.isConfirmed) return;
        isSubmitting = true; // evita beforeunload
        accion.value = 'editar_curso'; // para tu backend
        // por compatibilidad si tu backend usa name=editar_curso:
        const h = document.createElement('input');
        h.type = 'hidden';
        h.name = 'editar_curso';
        h.value = '1';
        form.appendChild(h);
        form.submit();
      });

      // ---- GUARDAR NUEVO PRECIO ----
      btnGuardarPrecio?.addEventListener('click', async () => {
        if (!validarPrecio()) return;
        const r = await swalConfirm({
          title: 'Guardar nuevo precio',
          text: '¿Confirmás el alta del nuevo precio?',
          icon: 'question',
          confirmText: 'Sí, guardar'
        });
        if (!r.isConfirmed) return;
        isSubmitting = true; // evita beforeunload
        accion.value = 'agregar_precio';
        const h = document.createElement('input');
        h.type = 'hidden';
        h.name = 'agregar_precio';
        h.value = '1';
        form.appendChild(h);
        form.submit();
      });

      // ---- VOLVER ----
      btnVolver?.addEventListener('click', async () => {
        if (!isEditing || !isDirty) {
          window.location.href = 'cursos.php';
          return;
        }
        const r = await swalConfirm({
          title: 'Volver a la lista',
          text: 'Hay cambios sin guardar. ¿Desea salir igualmente?',
          icon: 'warning',
          confirmText: 'Sí, salir'
        });
        if (r.isConfirmed) window.location.href = 'cursos.php';
      });

      // ---- SOLO AVISAR AL CERRAR/RECARGAR CUANDO HAY CAMBIOS ----
      window.addEventListener('beforeunload', function(e) {
        if (!isEditing || !isDirty || isSubmitting) return; // no mostrar si no hay cambios o se está guardando
        e.preventDefault();
        e.returnValue = ''; // texto ignorado por navegadores modernos
      });

      // Abrir tab desde URL (opcional)
      const url = new URL(window.location.href);
      if (url.searchParams.get('tab') === 'precios') {
        $('a[href="#course-prices"]').tab('show');
      }
      if (url.searchParams.get('saved') === '1') {
        Swal.fire({
          toast: true,
          position: 'top-end',
          timer: 1800,
          showConfirmButton: false,
          icon: 'success',
          title: 'Cambios guardados'
        });
      }
    });
  </script>

</body>

</html>