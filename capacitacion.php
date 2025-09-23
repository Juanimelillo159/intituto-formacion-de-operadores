<?php

session_start();

require_once 'sbd.php';


$page_title = "Capacitación | Instituto de Formación";
$page_description = "Página de capacitación del Instituto de Formación de Operadores";

$id_curso = $_GET['id_curso'] ?? null;

$sql_cursos = $con->prepare("
    SELECT * 
    FROM cursos c 
    WHERE c.id_curso = :id_curso
");
$sql_cursos->bindParam(':id_curso', $id_curso);
$sql_cursos->execute();
$curso = $sql_cursos->fetch(PDO::FETCH_ASSOC);

$sql_complejidad = $con->prepare("
    SELECT m.id_modalidad AS modalidad_id, m.nombre_modalidad AS modalidad_nombre 
    FROM curso_modalidad cm 
    JOIN modalidades m ON cm.id_modalidad = m.id_modalidad 
    WHERE cm.id_curso = :id_curso
");
$sql_complejidad->bindParam(':id_curso', $id_curso);
$sql_complejidad->execute();
$modalidades = $sql_complejidad->fetchAll(PDO::FETCH_ASSOC);

$modalidad_nombres = array_map(fn($v) => htmlspecialchars($v['modalidad_nombre']), $modalidades);
$modalidad_nombres_str = implode(' - ', $modalidad_nombres);
?>
<!DOCTYPE html>
<html lang="es">

<?php $page_styles = '<link rel="stylesheet" href="assets/styles/style_capacitacion.css">'; ?>
<?php include("head.php"); ?>


<body class="capacitaciones">
    <?php include("nav.php"); ?>

    <div class="container py-3">
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i><span>Volver al inicio</span></a>
    </div>

    <div class="container my-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="content-wrapper">
                    <div class="course-header">
                        <h1 class="course-title"><?php echo htmlspecialchars($curso["nombre_curso"] ?? "Capacitación"); ?></h1>
                        <p class="course-subtitle">Desarrolla tus habilidades con esta capacitación</p>
                    </div>
                    <div class="course-content">
                        <h2 class="section-title"><i class="fas fa-info-circle"></i>Descripción</h2>
                        <div class="course-description">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($curso['descripcion_curso'] ?? 'Texto de prueba de la descripción.')); ?></p>
                        </div>

                        <h3 class="section-title"><i class="fas fa-bullseye"></i>Objetivos</h3>
                        <div class="objectives-list">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($curso['objetivos'] ?? 'Objetivos de ejemplo.')); ?></p>
                        </div>

                        <!-- Acordeón (uno a la vez) -->
                        <h3 class="section-title mt-4"><i class="fas fa-list-ul"></i>Más información</h3>
                        <div class="accordion curso-accordion" id="cursoAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hCrono">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#cCrono" aria-expanded="true" aria-controls="cCrono">Cronograma</button>
                                </h2>
                                <div id="cCrono" class="accordion-collapse collapse show" aria-labelledby="hCrono" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">Texto de prueba para el cronograma.</div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hPublico">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cPublico" aria-expanded="false" aria-controls="cPublico">Público</button>
                                </h2>
                                <div id="cPublico" class="accordion-collapse collapse" aria-labelledby="hPublico" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">Texto de prueba para el público objetivo.</div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hPrograma">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cPrograma" aria-expanded="false" aria-controls="cPrograma">Programa</button>
                                </h2>
                                <div id="cPrograma" class="accordion-collapse collapse" aria-labelledby="hPrograma" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">Texto de prueba del temario/programa.</div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hReqs">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cReqs" aria-expanded="false" aria-controls="cReqs">Requisitos</button>
                                </h2>
                                <div id="cReqs" class="accordion-collapse collapse" aria-labelledby="hReqs" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">Texto de prueba de requisitos.</div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hObs">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cObs" aria-expanded="false" aria-controls="cObs">Observaciones</button>
                                </h2>
                                <div id="cObs" class="accordion-collapse collapse" aria-labelledby="hObs" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">Texto de prueba de observaciones.</div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hDocs">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cDocs" aria-expanded="false" aria-controls="cDocs">Documentación</button>
                                </h2>
                                <div id="cDocs" class="accordion-collapse collapse" aria-labelledby="hDocs" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">Texto de prueba para documentación.</div>
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
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-clock"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Duración</div>
                                <div class="detail-value"><?php echo htmlspecialchars($curso["duracion"] ?? "A definir"); ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-layer-group"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Nivel</div>
                                <div class="detail-value">
                                    <?php
                                    $nivel = strtolower($curso["complejidad"] ?? '');
                                    $badge_class = 'badge-intermediate';
                                    if (strpos($nivel, 'principiante') !== false || strpos($nivel, 'básico') !== false || strpos($nivel, 'basico') !== false) {
                                        $badge_class = 'badge-beginner';
                                    } elseif (strpos($nivel, 'avanzado') !== false || strpos($nivel, 'experto') !== false) {
                                        $badge_class = 'badge-advanced';
                                    }
                                    ?>
                                    <span class="badge-level <?php echo $badge_class; ?>"><?php echo htmlspecialchars($curso["complejidad"] ?? "Intermedio"); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-laptop"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Modalidad</div>
                                <div class="detail-value"><?php echo $modalidad_nombres_str ?: "Online / Presencial"; ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-certificate"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Certificación</div>
                                <div class="detail-value"><span class="text-success"><i class="fas fa-check-circle me-1"></i>Certificado incluido</span></div>
                            </div>
                        </div>

                        <a class="enroll-button" href="checkout/checkout.php?id_curso=<?php echo isset($curso['id_curso']) ? (int)$curso['id_curso'] : 0; ?>"><i class="fas fa-user-plus me-2"></i>Inscribirse Ahora</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include("footer.php"); ?>

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