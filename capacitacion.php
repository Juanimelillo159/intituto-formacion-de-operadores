<?php
session_start();
require_once 'sbd.php';

// Sanitizar y validar el parámetro id_curso
$id_curso = filter_input(INPUT_GET, 'id_curso', FILTER_VALIDATE_INT);

// Preparar consulta del curso con todos los campos relevantes
$sql_cursos = $con->prepare(
    "SELECT 
        id_curso,
        nombre_curso,
        descripcion_curso,
        duracion,
        objetivos,
        id_complejidad,
        cronograma,
        publico,
        programa,
        requisitos,
        observaciones,
        documentacion
     FROM cursos
     WHERE id_curso = :id_curso"
);
$sql_cursos->bindValue(':id_curso', $id_curso, PDO::PARAM_INT);
$sql_cursos->execute();
$curso = $sql_cursos->fetch(PDO::FETCH_ASSOC) ?: [];

// Modalidades (si corresponde)
$sql_modalidades = $con->prepare(
    "SELECT m.id_modalidad AS modalidad_id,
            m.nombre_modalidad AS modalidad_nombre
     FROM curso_modalidad cm
     JOIN modalidades m ON cm.id_modalidad = m.id_modalidad
     WHERE cm.id_curso = :id_curso"
);
$sql_modalidades->bindValue(':id_curso', $id_curso, PDO::PARAM_INT);
$sql_modalidades->execute();
$modalidades = $sql_modalidades->fetchAll(PDO::FETCH_ASSOC);
$modalidad_nombres = array_map(fn($v) => htmlspecialchars($v['modalidad_nombre']), $modalidades);
$modalidad_nombres_str = implode(' - ', $modalidad_nombres);

$precio_capacitacion = null;
if (!empty($curso['id_curso'])) {
    $cursoId = (int)$curso['id_curso'];
    $precio_capacitacion = obtener_precio_vigente($con, $cursoId, 'capacitacion');
}

// Helpers de salida segura
function h(?string $v, string $fallback = ''): string
{
    $v = $v ?? '';
    $v = trim($v);
    return $v !== '' ? htmlspecialchars($v) : $fallback;
}
function p(?string $v, string $fallback = ''): string
{
    // paragraph-safe: con nl2br
    $v = $v ?? '';
    $v = trim($v);
    return $v !== '' ? nl2br(htmlspecialchars($v)) : $fallback;
}

function obtener_precio_vigente(PDO $con, int $cursoId, string $tipoCurso): ?array
{
    $sql = $con->prepare(
        "SELECT precio, moneda, vigente_desde
           FROM curso_precio_hist
          WHERE id_curso = :curso
            AND tipo_curso = :tipo
            AND vigente_desde <= NOW()
            AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
       ORDER BY vigente_desde DESC
          LIMIT 1"
    );
    $sql->execute([
        ':curso' => $cursoId,
        ':tipo' => $tipoCurso,
    ]);
    $row = $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    $sql->closeCursor();

    return $row ?: null;
}

// Meta dinámicos
$page_title = (h($curso['nombre_curso']) ?: 'Capacitación') . ' | Instituto de Formación';
$page_description = h($curso['descripcion_curso']) ?: 'Página de capacitación del Instituto de Formación de Operadores';
?>
<!DOCTYPE html>
<html lang="es">
<?php $page_styles = '<link rel="stylesheet" href="assets/styles/style_capacitacion.css">'; ?>
<?php include('head.php'); ?>

<body class="capacitaciones">
    <?php include('nav.php'); ?>

    <div class="container py-3">
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i><span>Volver al inicio</span></a>
    </div>

    <div class="container my-4">
        <?php if (!$curso) : ?>
            <div class="alert alert-warning" role="alert">
                No se encontró la capacitación solicitada.
            </div>
        <?php else : ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="content-wrapper">
                        <div class="course-header">
                            <h1 class="course-title"><?php echo h($curso['nombre_curso'], 'Capacitación'); ?></h1>
                            <p class="course-subtitle">Desarrolla tus habilidades con esta capacitación</p>
                        </div>

                        <div class="course-content">
                            <h2 class="section-title"><i class="fas fa-info-circle"></i>Descripción</h2>
                            <div class="course-description">
                                <p class="mb-0"><?php echo p($curso['descripcion_curso'], 'Información no disponible.'); ?></p>
                            </div>

                            <h3 class="section-title"><i class="fas fa-bullseye"></i>Objetivos</h3>
                            <div class="objectives-list">
                                <p class="mb-0"><?php echo p($curso['objetivos'], 'Información no disponible.'); ?></p>
                            </div>

                            <!-- Acordeón (uno a la vez) -->
                            <h3 class="section-title mt-4"><i class="fas fa-list-ul"></i>Más información</h3>
                            <div class="accordion curso-accordion" id="cursoAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hCrono">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#cCrono" aria-expanded="true" aria-controls="cCrono">Cronograma</button>
                                    </h2>
                                    <div id="cCrono" class="accordion-collapse collapse show" aria-labelledby="hCrono" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['cronograma'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hPublico">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cPublico" aria-expanded="false" aria-controls="cPublico">Público</button>
                                    </h2>
                                    <div id="cPublico" class="accordion-collapse collapse" aria-labelledby="hPublico" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['publico'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hPrograma">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cPrograma" aria-expanded="false" aria-controls="cPrograma">Programa</button>
                                    </h2>
                                    <div id="cPrograma" class="accordion-collapse collapse" aria-labelledby="hPrograma" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['programa'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hReqs">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cReqs" aria-expanded="false" aria-controls="cReqs">Requisitos</button>
                                    </h2>
                                    <div id="cReqs" class="accordion-collapse collapse" aria-labelledby="hReqs" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['requisitos'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hObs">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cObs" aria-expanded="false" aria-controls="cObs">Observaciones</button>
                                    </h2>
                                    <div id="cObs" class="accordion-collapse collapse" aria-labelledby="hObs" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['observaciones'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hDocs">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cDocs" aria-expanded="false" aria-controls="cDocs">Documentación</button>
                                    </h2>
                                    <div id="cDocs" class="accordion-collapse collapse" aria-labelledby="hDocs" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['documentacion'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <!-- /Acordeón -->
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="details-card">
                        <div class="details-header">
                            <h3 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Información de la Capacitación</h3>
                        </div>
                        <div class="details-body">
                            <div class="price-summary">
                                <div class="price-summary-title"><i class="fas fa-hand-holding-usd me-2"></i>Inversión</div>
                                <div class="price-summary-list">
                                    <div class="price-summary-item">
                                        <div>
                                            <div class="price-summary-label">Capacitación</div>
                                            <div class="price-summary-note">
                                                <?php if ($precio_capacitacion): ?>
                                                    <?php if (!empty($precio_capacitacion['vigente_desde'])): ?>
                                                        Vigente desde <?php echo date('d/m/Y H:i', strtotime($precio_capacitacion['vigente_desde'])); ?>
                                                    <?php else: ?>
                                                        Precio vigente disponible en el sistema.
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    Precio a confirmar con el equipo comercial.
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="price-summary-value">
                                            <?php if ($precio_capacitacion): ?>
                                                <?php echo strtoupper($precio_capacitacion['moneda'] ?? 'ARS'); ?> <?php echo number_format((float)$precio_capacitacion['precio'], 2, ',', '.'); ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-clock"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Duración</div>
                                    <div class="detail-value"><?php echo h($curso['duracion'], 'A definir'); ?></div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-laptop"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Modalidad</div>
                                    <div class="detail-value"><?php echo $modalidad_nombres_str ?: 'Presencial'; ?></div>
                                </div>
                            </div>

                            <!-- Si deseas mostrar complejidad -->
                            <?php if (!empty($curso['id_complejidad'])): ?>
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-layer-group"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-label">Complejidad</div>
                                        <div class="detail-value">Nivel <?php echo (int)$curso['id_complejidad']; ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <a class="enroll-button" href="checkout/checkout.php?id_curso=<?php echo isset($curso['id_curso']) ? (int)$curso['id_curso'] : 0; ?>&amp;tipo=capacitacion">
                                <i class="fas fa-user-plus me-2"></i>Inscribirse Ahora
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include('footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.content-wrapper, .details-card').forEach((el, i) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    el.style.transition = 'all .6s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, i * 200);
            });
        });
    </script>
</body>

</html>