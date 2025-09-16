<?php
session_start();
require_once 'sbd.php';

$page_title = "Certificación | Instituto de Formación";
$page_description = "Página de certificación del Instituto de Formación de Operadores";

$id_certificacion = $_GET['id_certificacion'] ?? null;

/* Ajusta nombres de tabla/campos si difiere tu esquema */
$sql_item = $con->prepare("
  SELECT * 
  FROM cursos c 
  JOIN complejidad n ON c.id_complejidad = n.id_complejidad 
  WHERE c.id_curso = :id
");
$sql_item->bindParam(':id', $id_certificacion);
$sql_item->execute();
$cert = $sql_item->fetch(PDO::FETCH_ASSOC);

$sql_mods = $con->prepare("
  SELECT m.id_modalidad AS modalidad_id, m.nombre_modalidad AS modalidad_nombre
  FROM curso_modalidad cm 
  JOIN modalidades m ON cm.id_modalidad = m.id_modalidad 
  WHERE cm.id_curso = :id
");
$sql_mods->bindParam(':id', $id_certificacion);
$sql_mods->execute();
$modalidades = $sql_mods->fetchAll(PDO::FETCH_ASSOC);

$modalidad_nombres = array_map(fn($v) => htmlspecialchars($v['modalidad_nombre']), $modalidades);
$modalidad_nombres_str = implode(' - ', $modalidad_nombres);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php $page_styles = '<link rel="stylesheet" href="assets/styles/style_certificacion.css">';?>
    <?php include("head.php"); ?>
</head>

<body class="certificaciones">

    <?php include("nav.php"); ?>

    <div class="container py-3">
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i><span>Volver al inicio</span></a>
    </div>

    <div class="container my-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="content-wrapper">
                    <div class="course-header">
                        <h1 class="course-title"><i class="fa-solid fa-award me-2"></i><?php echo htmlspecialchars($cert["nombre_certificacion"] ?? "Certificación"); ?></h1>
                        <p class="course-subtitle">Información clave de esta certificación</p>
                    </div>
                    <div class="course-content">
                        <h2 class="section-title"><i class="fa-solid fa-shield-check"></i>Descripción de la Certificación</h2>
                        <div class="course-description">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($cert['descripcion'] ?? 'Texto de prueba de la certificación.')); ?></p>
                        </div>

                        <h3 class="section-title"><i class="fa-solid fa-list-check"></i>Requisitos de Evaluación</h3>
                        <div class="objectives-list">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($cert['requisitos_evaluacion'] ?? 'Criterios de evaluación de ejemplo.')); ?></p>
                        </div>

                        <!-- Acordeón (uno a la vez) -->
                        <h3 class="section-title mt-4"><i class="fas fa-folder-tree"></i>Más información</h3>
                        <div class="accordion curso-accordion" id="certAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hProceso">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#cProceso" aria-expanded="true" aria-controls="cProceso">Proceso de Certificación</button>
                                </h2>
                                <div id="cProceso" class="accordion-collapse collapse show" aria-labelledby="hProceso" data-bs-parent="#certAccordion">
                                    <div class="accordion-body">Texto de prueba: etapas del proceso, auditorías, plazos y entregables.</div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hAlcance">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cAlcance" aria-expanded="false" aria-controls="cAlcance">Alcance</button>
                                </h2>
                                <div id="cAlcance" class="accordion-collapse collapse" aria-labelledby="hAlcance" data-bs-parent="#certAccordion">
                                    <div class="accordion-body">Texto de prueba: normas aplicables, categorías y límites del esquema.</div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hReqs">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cReqs" aria-expanded="false" aria-controls="cReqs">Requisitos</button>
                                </h2>
                                <div id="cReqs" class="accordion-collapse collapse" aria-labelledby="hReqs" data-bs-parent="#certAccordion">
                                    <div class="accordion-body">Texto de prueba: documentación obligatoria, elegibilidad, vigencia y mantenimiento.</div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hVigencia">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cVigencia" aria-expanded="false" aria-controls="cVigencia">Vigencia y Renovación</button>
                                </h2>
                                <div id="cVigencia" class="accordion-collapse collapse" aria-labelledby="hVigencia" data-bs-parent="#certAccordion">
                                    <div class="accordion-body">Texto de prueba: validez, seguimiento y recertificación.</div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hDocs">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cDocs" aria-expanded="false" aria-controls="cDocs">Documentación</button>
                                </h2>
                                <div id="cDocs" class="accordion-collapse collapse" aria-labelledby="hDocs" data-bs-parent="#certAccordion">
                                    <div class="accordion-body">Texto de prueba: guías, plantillas y enlaces relevantes.</div>
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
                        <h3 class="mb-0"><i class="fa-solid fa-certificate me-2"></i>Información de la Certificación</h3>
                    </div>
                    <div class="details-body">
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fa-regular fa-clock"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Duración / Plazo</div>
                                <div class="detail-value"><?php echo htmlspecialchars($cert["plazo"] ?? "A definir"); ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fa-solid fa-signal"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Nivel</div>
                                <div class="detail-value">
                                    <?php
                                    $nivel = strtolower($cert["nombre_complejidad"] ?? '');
                                    $badge_class = 'badge-intermediate';
                                    if (strpos($nivel, 'principiante') !== false || strpos($nivel, 'básico') !== false || strpos($nivel, 'basico') !== false) {
                                        $badge_class = 'badge-beginner';
                                    } elseif (strpos($nivel, 'avanzado') !== false || strpos($nivel, 'experto') !== false) {
                                        $badge_class = 'badge-advanced';
                                    }
                                    ?>
                                    <span class="badge-level <?php echo $badge_class; ?>"><?php echo htmlspecialchars($cert["nombre_complejidad"] ?? "Intermedio"); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fa-solid fa-laptop-file"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Modalidad</div>
                                <div class="detail-value"><?php echo $modalidad_nombres_str ?: "Online / Presencial"; ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fa-solid fa-shield"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Emisión</div>
                                <div class="detail-value"><span class="text-success"><i class="fas fa-check-circle me-1"></i>Certificado oficial</span></div>
                            </div>
                        </div>

                        <button class="enroll-button" onclick="solicitarCertificacion()">
                            <i class="fa-solid fa-paper-plane me-2"></i> Solicitar certificación
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include("footer.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function solicitarCertificacion() {
            alert('Flujo de solicitud de certificación - Conectar con tu backend.');
        }
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