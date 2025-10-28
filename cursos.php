<?php
require_once 'sbd.php';



$sql = $con->prepare("SELECT * FROM cursos");
$sql->execute();
$cursos = $sql->fetchAll(mode: PDO::FETCH_ASSOC);

$capacitacionesGlobalHabilitadas = site_settings_sales_enabled($site_settings, 'capacitacion');
$certificacionesGlobalHabilitadas = site_settings_sales_enabled($site_settings, 'certificacion');
?>
<div class="row row-cols-1 row-cols-md-3 g-4">
  <?php foreach ($cursos as $curso) { ?>
    <div class="col">
      <div class="card card-course h-100 position-relative d-flex flex-column">

        <?php
        $cursoId = (int)($curso['id_curso'] ?? 0);
        $capacitacionDisponible = $capacitacionesGlobalHabilitadas && site_settings_sales_enabled($site_settings, 'capacitacion', $cursoId);
        $certificacionDisponible = $certificacionesGlobalHabilitadas && site_settings_sales_enabled($site_settings, 'certificacion', $cursoId);
        $inscripcionesBloqueadas = !$capacitacionDisponible && !$certificacionDisponible;
        ?>


        <div class="card-body d-flex flex-column">
          <!-- Título -->
          <h5 class="card-title fw-bold mb-2 curso-titulo">
            <?php echo htmlspecialchars($curso["nombre_curso"]); ?>
          </h5>

          <!-- Meta: duración y categoría (ajusta campos si los tienes) -->
          <div class="d-flex align-items-center meta gap-12 mb-3">
            <span class="d-inline-flex align-items-center">
              <i class="bi bi-clock me-2"></i>
              <?php echo isset($curso["duracion"]) ? htmlspecialchars($curso["duracion"]) : "12 semanas"; ?>
            </span>
            <span class="d-inline-flex align-items-center">
              <i class="bi bi-journal-code me-2"></i>
              <?php echo isset($curso["categoria"]) ? htmlspecialchars($curso["categoria"]) : "Programación"; ?>
            </span>
          </div>

          <!-- Descripción -->
          <p class="card-text text-body-secondary mb-0">
            <?php echo htmlspecialchars($curso["descripcion_curso"]); ?>
          </p>
        </div>

        <!-- Botones -->
        <div class="card-footer bg-transparent border-0 mt-auto">
          <div class="d-flex gap-2">
            <?php if ($capacitacionDisponible): ?>
              <a class="btn btn-primary btn-pill d-inline-flex align-items-center"
                href="capacitacion.php?id_curso=<?php echo urlencode($curso['id_curso']); ?>">
                <i class="bi bi-activity me-2"></i> Capacitaci&oacute;n
              </a>
            <?php else: ?>
              <button class="btn btn-outline-secondary btn-pill d-inline-flex align-items-center" type="button" disabled
                title="Inscripción de capacitación temporalmente deshabilitada">
                <i class="bi bi-activity me-2"></i> Capacitaci&oacute;n
              </button>
            <?php endif; ?>

            <?php if ($certificacionDisponible): ?>
              <a class="btn btn-secondary btn-pill d-inline-flex align-items-center"
                href="certificacion.php?id_certificacion=<?php echo urlencode($curso['id_curso']); ?>">
                <i class="bi bi-circle me-2"></i> Certificaci&oacute;n
              </a>
            <?php else: ?>
              <button class="btn btn-outline-secondary btn-pill d-inline-flex align-items-center" type="button" disabled
                title="Solicitud de certificación temporalmente deshabilitada">
                <i class="bi bi-circle me-2"></i> Certificaci&oacute;n
              </button>
            <?php endif; ?>
          </div>
          <?php if ($inscripcionesBloqueadas): ?>
            <div class="alert alert-warning mt-3 mb-0 py-2 px-3" role="alert">
              <i class="bi bi-info-circle me-2"></i>
              Las inscripciones online para este curso están temporalmente deshabilitadas.
            </div>
          <?php elseif (!$capacitacionDisponible || !$certificacionDisponible): ?>
            <div class="small text-warning mt-3">
              Algunas modalidades de inscripción están temporalmente deshabilitadas.
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  <?php } ?>
</div>