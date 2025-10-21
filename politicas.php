<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$page_title = 'Políticas Institucionales | Instituto de Formación';
$page_description = 'Conoce las políticas y manuales que respaldan la certificación de personas del Instituto de Formación de Operadores.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_politicas.css">';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php include 'head.php'; ?>
</head>

<body class="politicas-page">
    <?php include 'nav.php'; ?>
    <main>
        <section class="policy-hero">
            <div class="container">
                <span class="hero-badge"><i class="fa-solid fa-shield-heart"></i> Cultura de cumplimiento</span>
                <h1>Políticas Institucionales</h1>
                <p>
                    Acompañamos cada certificación con lineamientos claros que garantizan la seguridad operacional,
                    la ética profesional y la transparencia en todos nuestros procesos.
                </p>
            </div>
        </section>

        <section class="policy-highlight section-spacing">
            <div class="container">
                <div class="policy-showcase">
                    <div class="policy-copy">
                        <h2>Certificación de personas</h2>
                        <p>
                            La certificación consiste en el reconocimiento emitido por una tercera parte sobre la
                            competencia de un individuo para llevar a cabo una tarea o trabajo. Este reconocimiento se
                            logra a partir de un proceso de evaluación de la persona bajo un conjunto de requisitos
                            contenidos en un esquema técnico.
                        </p>
                        <ul>
                            <li><i class="fa-solid fa-circle-check"></i> Evaluación objetiva de competencias.</li>
                            <li><i class="fa-solid fa-circle-check"></i> Requisitos y procesos transparentes.</li>
                            <li><i class="fa-solid fa-circle-check"></i> Seguimiento continuo del desempeño.</li>
                        </ul>
                        <div class="policy-actions">
                            <a class="policy-btn primary" href="assets/pdf/manual_certificacion.pdf" target="_blank" rel="noopener">
                                <i class="fa-solid fa-book"></i> Manual de certificación de personas
                            </a>
                            <a class="policy-btn secondary" href="assets/pdf/politica_conducta.pdf" target="_blank" rel="noopener">
                                <i class="fa-solid fa-scale-balanced"></i> Política de conducta
                            </a>
                        </div>
                    </div>
                    <div class="policy-visual">
                        <img src="assets/imagenes/fondo/img1.jpg" alt="Equipo técnico revisando documentación" loading="lazy">
                    </div>
                </div>
            </div>
        </section>

        <section class="policy-commitments section-spacing">
            <div class="container">
                <div class="section-title">
                    <span>Compromisos clave</span>
                    <h3>Políticas que guían nuestro trabajo</h3>
                    <p class="mt-3 mb-0 text-muted">
                        Cada política respalda los estándares de calidad, seguridad y conducta que ofrecemos a nuestros
                        clientes y participantes.
                    </p>
                </div>
                <div class="row g-4">
                    <div class="col-md-4">
                        <article class="commitment-card">
                            <span class="commitment-icon primary"><i class="fa-solid fa-helmet-safety"></i></span>
                            <h4>Seguridad operativa</h4>
                            <p>
                                Establece pautas para la gestión de riesgos, el uso de equipos certificados y la
                                protección de las personas durante las prácticas.
                            </p>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="commitment-card">
                            <span class="commitment-icon secondary"><i class="fa-solid fa-people-group"></i></span>
                            <h4>Ética profesional</h4>
                            <p>
                                Define normas de trato responsable, confidencialidad y respeto mutuo entre instructores,
                                evaluadores y participantes.
                            </p>
                        </article>
                    </div>
                    <div class="col-md-4">
                        <article class="commitment-card">
                            <span class="commitment-icon tertiary"><i class="fa-solid fa-seedling"></i></span>
                            <h4>Mejora continua</h4>
                            <p>
                                Describe los mecanismos de auditoría y retroalimentación que permiten actualizar nuestros
                                programas y procedimientos.
                            </p>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-spacing">
            <div class="container">
                <div class="policy-detail-banner">
                    <div>
                        <h3>Política integral de seguridad y ambiente</h3>
                        <p>
                            Resume los controles preventivos, las responsabilidades del personal y los protocolos de
                            respuesta ante incidentes, asegurando la continuidad de nuestras operaciones formativas.
                        </p>
                        <ul class="policy-detail-list">
                            <li><i class="fa-solid fa-circle-exclamation"></i> Identificación y evaluación de riesgos críticos.</li>
                            <li><i class="fa-solid fa-kit-medical"></i> Planes de contingencia y primeros auxilios certificados.</li>
                            <li><i class="fa-solid fa-recycle"></i> Gestión responsable de recursos y residuos.</li>
                        </ul>
                    </div>
                    <div class="d-flex flex-column gap-3 align-items-start align-items-md-end">
                        <a class="policy-btn tertiary" href="assets/pdf/politica_seguridad.pdf" target="_blank" rel="noopener">
                            <i class="fa-solid fa-file-pdf"></i> Descargar política de seguridad
                        </a>
                        <small class="text-white-50">Formato PDF - última actualización <?php echo date('Y'); ?></small>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="app.js"></script>
</body>

</html>
