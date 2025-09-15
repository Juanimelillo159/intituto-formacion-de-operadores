<?php
require_once 'sbd.php';
include("nav.php");

$id_curso = $_GET['id_curso'];

$sql_cursos = $con->prepare("SELECT * FROM cursos c JOIN complejidad n ON c.id_complejidad = n.id_complejidad WHERE c.id_curso = :id_curso");
$sql_cursos->bindParam(':id_curso', $id_curso);
$sql_cursos->execute();
$curso = $sql_cursos->fetch(PDO::FETCH_ASSOC);

$sql_complejidad = $con->prepare("SELECT m.id_modalidad AS modalidad_id, m.nombre_modalidad AS modalidad_nombre FROM curso_modalidad cm JOIN modalidades m ON cm.id_modalidad = m.id_modalidad WHERE cm.id_curso = :id_curso");
$sql_complejidad->bindParam(':id_curso', $id_curso);
$sql_complejidad->execute();
$modalidades = $sql_complejidad->fetchAll(PDO::FETCH_ASSOC);

$modalidad_nombres = array();
foreach ($modalidades as $value) {
    $modalidad_nombres[] = htmlspecialchars($value['modalidad_nombre']);
}
$modalidad_nombres_str = implode(' - ', $modalidad_nombres);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <title><?php echo htmlspecialchars($curso["nombre_curso"]); ?></title>
    <style>
        :root {
            --primary-color: var(--color-secondary);
            /* Azul */
            --accent-color: #28a745;
            /* puedes mantener este verde para botones */
            --text-dark: var(--color-gray-dark);
            --border-radius: 12px;
            --shadow: 0 4px 20px rgba(0, 20, 174, 0.1);
        }


    

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
        }

        /* Botón volver */
        .back-button {
            background: rgba(0, 0, 0, .04);
            border: 2px solid rgba(0, 0, 0, .06);
            color: #0d6efd;
            padding: 10px 18px;
            border-radius: 50px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all .25s ease;
        }

        .back-button:hover {
            background: rgba(13, 110, 253, .1);
            color: #0d6efd;
            transform: translateY(-1px);
        }

        .content-wrapper {
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .course-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 40px;
            border-bottom: 1px solid #dee2e6;
        }

        .course-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 2.2rem;
        }

        .course-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        .course-content {
            padding: 40px;
        }

        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--accent-color);
        }

        .course-description {
            background: #f8f9fa;
            padding: 25px;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
            margin-bottom: 30px;
        }

        .details-card {
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            position: sticky;
            top: 20px;
        }

        .details-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
            color: #fff;
            padding: 25px;
            text-align: center;
        }

        .details-body {
            padding: 30px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .detail-item:last-child {
            border-bottom: none;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .detail-content {
            flex: 1;
        }

        .detail-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2px;
        }

        .detail-value {
            color: #6c757d;
            font-size: .95rem;
        }

        .enroll-button {
            background: linear-gradient(135deg, var(--accent-color) 0%, #20c997 100%);
            border: none;
            color: #fff;
            padding: 15px 30px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all .3s ease;
            margin-top: 25px;
        }

        .enroll-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, .3);
            color: #fff;
        }

        .objectives-list {
            background: #fff;
            border-radius: var(--border-radius);
            padding: 25px;
            border: 1px solid #e9ecef;
        }

        .badge-level {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: .85rem;
            font-weight: 600;
        }

        .badge-beginner {
            background: #d4edda;
            color: #155724;
        }

        .badge-intermediate {
            background: #fff3cd;
            color: #856404;
        }

        .badge-advanced {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width:768px) {
            .course-title {
                font-size: 1.8rem;
            }

            .course-header,
            .course-content {
                padding: 25px;
            }

            .details-card {
                position: static;
                margin-top: 30px;
            }
        }

        /* Acordeón estilizado para que combine con tu UI */
        .accordion.curso-accordion .accordion-item {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
            background: #fff;
        }

        .accordion.curso-accordion .accordion-item+.accordion-item {
            margin-top: 14px;
        }

        .accordion.curso-accordion .accordion-button {
            padding: 16px 18px;
            font-weight: 600;
            color: var(--text-dark);
            background: #fff;
        }

        .accordion.curso-accordion .accordion-button:not(.collapsed) {
            color: var(--primary-color);
            background: #f8f9fa;
            box-shadow: none;
        }

        .accordion.curso-accordion .accordion-body {
            background: #fff;
            color: #495057;
        }
    </style>
</head>

<body>
    <!-- Solo botón de volver -->
    <div class="container py-3">
        <a href="index.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            <span>Volver al inicio</span>
        </a>
    </div>

    <div class="container my-4">
        <div class="row">
            <!-- Columna principal -->
            <div class="col-lg-8">
                <div class="content-wrapper">
                    <div class="course-header">
                        <h1 class="course-title"><?php echo htmlspecialchars($curso["nombre_curso"]); ?></h1>
                        <p class="course-subtitle">Desarrolla tus habilidades con este curso especializado</p>
                    </div>
                    <div class="course-content">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Descripción del Curso
                        </h2>
                        <div class="course-description">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($curso['descripcion_curso'])); ?></p>
                        </div>

                        <h3 class="section-title">
                            <i class="fas fa-bullseye"></i>
                            Objetivos del Curso
                        </h3>
                        <div class="objectives-list">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($curso['objetivos'])); ?></p>
                        </div>
                        <!-- Acordeón: solo un panel abierto a la vez -->
                        <h3 class="section-title mt-4">
                            <i class="fas fa-list-ul"></i>
                            Más información
                        </h3>

                        <div class="accordion curso-accordion" id="cursoAccordion">
                            <!-- Cronograma -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingCronograma">
                                    <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseCronograma"
                                        aria-expanded="false" aria-controls="collapseCronograma">
                                        Cronograma
                                    </button>
                                </h2>
                                <div id="collapseCronograma" class="accordion-collapse collapse"
                                    aria-labelledby="headingCronograma" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">
                                        Texto de prueba para el cronograma. Aquí puedes listar fechas, módulos y horarios.
                                    </div>
                                </div>
                            </div>

                            <!-- Público -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingPublico">
                                    <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapsePublico"
                                        aria-expanded="false" aria-controls="collapsePublico">
                                        Público
                                    </button>
                                </h2>
                                <div id="collapsePublico" class="accordion-collapse collapse"
                                    aria-labelledby="headingPublico" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">
                                        Texto de prueba para el público objetivo. Perfiles recomendados y conocimientos previos.
                                    </div>
                                </div>
                            </div>

                            <!-- Programa -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingPrograma">
                                    <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapsePrograma"
                                        aria-expanded="false" aria-controls="collapsePrograma">
                                        Programa
                                    </button>
                                </h2>
                                <div id="collapsePrograma" class="accordion-collapse collapse"
                                    aria-labelledby="headingPrograma" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">
                                        Texto de prueba del temario/programa. Unidades, contenidos, prácticas y evaluación.
                                    </div>
                                </div>
                            </div>

                            <!-- Requisitos -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingRequisitos">
                                    <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseRequisitos"
                                        aria-expanded="false" aria-controls="collapseRequisitos">
                                        Requisitos
                                    </button>
                                </h2>
                                <div id="collapseRequisitos" class="accordion-collapse collapse"
                                    aria-labelledby="headingRequisitos" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">
                                        Texto de prueba de requisitos técnicos y/o académicos para tomar el curso.
                                    </div>
                                </div>
                            </div>

                            <!-- Observaciones -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingObservaciones">
                                    <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseObservaciones"
                                        aria-expanded="false" aria-controls="collapseObservaciones">
                                        Observaciones
                                    </button>
                                </h2>
                                <div id="collapseObservaciones" class="accordion-collapse collapse"
                                    aria-labelledby="headingObservaciones" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">
                                        Texto de prueba con notas adicionales, políticas y consideraciones importantes.
                                    </div>
                                </div>
                            </div>

                            <!-- Documentación -->
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingDocumentacion">
                                    <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#collapseDocumentacion"
                                        aria-expanded="false" aria-controls="collapseDocumentacion">
                                        Documentación
                                    </button>
                                </h2>
                                <div id="collapseDocumentacion" class="accordion-collapse collapse"
                                    aria-labelledby="headingDocumentacion" data-bs-parent="#cursoAccordion">
                                    <div class="accordion-body">
                                        Texto de prueba para enlaces o archivos de apoyo (PDFs, guías, plantillas).
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Sidebar de detalles -->
            <div class="col-lg-4">
                <div class="details-card">
                    <div class="details-header">
                        <h3 class="mb-0">
                            <i class="fas fa-graduation-cap me-2"></i>
                            Información del Curso
                        </h3>
                    </div>
                    <div class="details-body">
                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-clock"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Duración</div>
                                <div class="detail-value"><?php echo htmlspecialchars($curso["duracion"]); ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-layer-group"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Nivel de Dificultad</div>
                                <div class="detail-value">
                                    <?php
                                    $nivel = strtolower($curso["nombre_complejidad"]);
                                    $badge_class = 'badge-intermediate';
                                    if (strpos($nivel, 'principiante') !== false || strpos($nivel, 'básico') !== false || strpos($nivel, 'basico') !== false) {
                                        $badge_class = 'badge-beginner';
                                    } elseif (strpos($nivel, 'avanzado') !== false || strpos($nivel, 'experto') !== false) {
                                        $badge_class = 'badge-advanced';
                                    }
                                    ?>
                                    <span class="badge-level <?php echo $badge_class; ?>">
                                        <?php echo htmlspecialchars($curso["nombre_complejidad"]); ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-laptop"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Modalidad</div>
                                <div class="detail-value"><?php echo $modalidad_nombres_str; ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fas fa-certificate"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Certificación</div>
                                <div class="detail-value">
                                    <span class="text-success">
                                        <i class="fas fa-check-circle me-1"></i> Certificado incluido
                                    </span>
                                </div>
                            </div>
                        </div>

                        <button class="enroll-button" onclick="inscribirse()">
                            <i class="fas fa-user-plus me-2"></i> Inscribirse Ahora
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include("footer.php"); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function inscribirse() {
            alert('Funcionalidad de inscripción - Conectar con tu sistema de inscripciones');
        }

        // Animación de entrada
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.content-wrapper, .details-card');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    el.style.transition = 'all 0.6s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
    <script src="app.js"></script>
</body>

</html>