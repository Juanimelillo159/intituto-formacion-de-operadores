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
    <a class="policy-btn primary" href="assets/pdf/PE%2001-Anexo%20IV.%20Diagrama%20de%20flujo%20del%20proceso%20Rev.00.pdf" target="_blank" rel="noopener">
        <i class="fa-solid fa-book"></i> Proceso de certificacion de personas
    </a>
    <a class="policy-btn secondary" href="assets/pdf/Pol%C3%ADtica%20de%20%C3%A9tica%20y%20conducta.%20Rev.00..pdf" target="_blank" rel="noopener">
        <i class="fa-solid fa-scale-balanced"></i> Politica de conducta
    </a>
    <a class="policy-btn secondary" href="assets/pdf/Pol%C3%ADtica%20de%20Imparcialidad%20Rev.00.pdf" target="_blank" rel="noopener">
        <i class="fa-solid fa-handshake"></i> Politica de imparcialidad
    </a>
    <a class="policy-btn secondary" href="assets/pdf/PG-06%20Apelaciones%20y%20quejas%20Rev.01.pdf" target="_blank" rel="noopener">
        <i class="fa-solid fa-gavel"></i> Apelaciones y quejas
    </a>
</div>
                    </div>
                    <div class="policy-visual">
                        <img src="assets/imagenes/fondo/img1.jpg" alt="Equipo técnico revisando documentación" loading="lazy">
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
