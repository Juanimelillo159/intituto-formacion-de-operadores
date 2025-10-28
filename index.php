<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'sbd.php';

$page_title = "Inicio | Instituto de Formación";
$page_description = "Pagina principal del Instituto de Formación de Operadores";

$sql_carrusel = $con->prepare("SELECT * FROM banner");
$sql_carrusel->execute();
$banners = $sql_carrusel->fetchAll(PDO::FETCH_ASSOC);

$registro_mensaje = isset($_SESSION['registro_mensaje']) ? $_SESSION['registro_mensaje'] : null;
$registro_tipo = isset($_SESSION['registro_tipo']) ? $_SESSION['registro_tipo'] : 'info';
if ($registro_mensaje !== null) {
    unset($_SESSION['registro_mensaje'], $_SESSION['registro_tipo']);
}

$contacto_mensaje = isset($_SESSION['contacto_mensaje']) ? $_SESSION['contacto_mensaje'] : null;
$contacto_tipo = isset($_SESSION['contacto_tipo']) ? $_SESSION['contacto_tipo'] : 'info';
if ($contacto_mensaje !== null) {
    unset($_SESSION['contacto_mensaje'], $_SESSION['contacto_tipo']);
}
?>


<!DOCTYPE html>
<html lang="es">


<?php include("head.php") ?>

<body>
  <?php include("nav.php") ?>
  <div class="content-wrapper">
    <?php if ($registro_mensaje !== null) : ?>
      <div class="container mt-3">
        <div class="alert alert-<?php echo htmlspecialchars($registro_tipo); ?> text-center" role="alert">
          <?php echo htmlspecialchars($registro_mensaje); ?>
        </div>
      </div>
    <?php endif; ?>
    <header class="hero-section py-5">
      <div class="container h-100">
        <div class="row h-100 align-items-center">
          <div class="col-md-6 d-flex justify-content-center align-items-center logos-container">
            <!-- Aquí los logos estarán lado a lado en desktop y uno debajo del otro en móvil -->
            <img src="logos/LOGO PNG-04.png" alt="Instituto de Formación de Operadores logo" class="hero-logo img-fluid logo-small me-3">
            <img src="logos/LOGO PNG_Mesa de trabajo 1.svg" alt="Instituto de Formación de Operadores letras" class="hero-logo img-fluid logo-small">
          </div>
          <div class="col-md-6 d-flex justify-content-center align-items-center">
            <div class="hero-text text-center">
              <h1 class="display-4 display-md-3">Capacitación profesional para empresas y particulares</h1>
            </div>
          </div>
        </div>
      </div>
    </header>


    <section id="carrusel" class="py-5 section-transition">
      <div class="container">
        <div id="imageCarousel" class="carousel slide" data-bs-ride="carousel">
          <div class="carousel-inner">
            <?php if (!empty($banners)) { ?>
              <?php foreach ($banners as $index => $banner) { ?>
                <?php $hasImage = !empty($banner['imagen_banner']); ?>
                <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                  <?php if ($hasImage) { ?>
                    <img class="d-block w-100" src="assets/imagenes/banners/<?php echo htmlspecialchars($banner['imagen_banner'], ENT_QUOTES, 'UTF-8'); ?>" alt="Slide <?php echo $index + 1; ?>">
                  <?php } else { ?>
                    <div class="d-flex align-items-center justify-content-center" style="height: 400px; background-color: #f8f9fa; color: #6c757d; font-size: 2rem;">
                      Noticias pronto
                    </div>
                  <?php } ?>
                </div>
              <?php } ?>
            <?php } else { ?>
              <div class="carousel-item active">
                <div class="d-flex align-items-center justify-content-center" style="height: 400px; background-color: #f8f9fa; color: #6c757d; font-size: 2rem;">
                  Noticias pronto
                </div>
              </div>
            <?php } ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#imageCarousel" data-bs-slide="prev">
              <span class="carousel-control-prev-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Anterior</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#imageCarousel" data-bs-slide="next">
              <span class="carousel-control-next-icon" aria-hidden="true"></span>
              <span class="visually-hidden">Siguiente</span>
            </button>
          </div>
        </div>
    </section>

    <section id="quienes-somos" class="bg-nosotros section-padding">
      <div class="container">
        <h2 class="display-2 text-center mb-4">¿Quiénes Somos?</h2>
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <p class="text-center">
              EN EL INSTITUTO DE FORMACIÓN DE OPERADORES, NOS ENORGULLECE SER LÍDERES EN EL ÁREA DE CAPACITACIÓN DEL PERSONAL. DESDE NUESTRA ORGANIZACIÓN NOS HEMOS DEDICADO A LA ARDUA Y FUNDAMENTAL TAREA DE DARLE A LAS PERSONAS LAS HERRAMIENTAS PARA DESARROLLAR DE MANERA SEGURA Y EFICIENTE, LAS TAREAS QUE DESEMPEÑA.
            </p>
          </div>
        </div>
      </div>
      <div class="container p-5">
        <div class="row">
          <div class="col-md-4 mb-4">
            <h3 class="text-secondary"><i class="fas fa-bullseye me-2"></i>MISIÓN</h3>
            <p>EN EL INSTITUTO DE FORMACIÓN DE OPERADORES CREEMOS FIRMEMENTE EN EL CRECIMIENTO CONJUNTO ENTRE CLIENTE Y PROVEEDOR, PARA ELLO TENEMOS LA MISIÓN CLARA DE BUSCAR EL LIDERAZGO EN LAS DIFERENTES ÁREAS EN QUE NOS DESARROLLAMOS APLICANDO CALIDAD, SEGURIDAD Y COMPROMISO PLENO EN NUESTRO ACCIONAR.</p>
          </div>
          <div class="col-md-4 mb-4">
            <h3 class="text-secondary"><i class="fas fa-eye me-2"></i>VISIÓN</h3>
            <p>SER UNA ORGANIZACIÓN DONDE EMPRESAS Y PERSONAS ENCUENTREN SOLUCIONES AGILES Y CONFIABLES PARA SUS NECESIDADES</p>
          </div>
          <div class="col-md-4 mb-4">
            <h3 class="text-secondary"><i class="fas fa-hands-helping me-2"></i>VALORES</h3>
            <p>LA ORGANIZACIÓN ESTÁ FUNDADA SOBRE FUERTES VALORES SOCIALES, DE DESARROLLO Y PROFESIONALES, COMO LO SON LA ÉTICA, LA HONESTIDAD, EL RESPETO POR EL MEDIO AMBIENTE, LA BÚSQUEDA DE LA EXCELENCIA Y LA MEJORA CONTINUA, TODO ELLO PUESTO AL SERVICIO DE NUESTROS CLIENTES.</p>
          </div>
        </div>
      </div>
    </section>
    <section id="servicios-capacitacion" class="py-5 bg-light">
      <div class="container">
        <h2 class="display-2 text-center mb-4 subrayado">SERVICIOS</h2>
        <!-- Equipos de izaje -->
        <div class="row align-items-center mb-5">
          <div class="col-md-6">
            <img src="assets/imagenes/equipos de izaje/1.jpg" alt="Equipos de izaje" class="img-fluid rounded">
          </div>
          <div class="col-md-6">
            <p class="service-subtitle ">Formación en:</p>
            <h2 class="service-title text-primary ">Equipos de izaje</h2>
            <ul class="list-unstyled service-list">
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador de Grúa Móvil</span>
                <img class="icono" src="assets/iconos/Iconossvg/1.svg" alt="">
              </li>
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador de Grúa Móvil de Pluma Articulada</span>
                <img class="icono" src="assets/iconos/Iconossvg/2.svg" alt="">
              </li>
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador de Hidroelevador</span>
                <img class="icono" src="assets/iconos/Iconossvg/3.svg" alt="">
              </li>
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador de Autoelevador</span>
                <img class="icono" src="assets/iconos/Iconossvg/4.svg" alt="">
              </li>
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador Rigger</span>
                <img class="icono" src="assets/iconos/Iconossvg/5.svg" alt="">
              </li>
            </ul>
          </div>
        </div>

        <!-- Maquinaria Vial -->
        <div class="row align-items-center">
          <div class="col-md-6 order-md-2">
            <img src="assets/imagenes/maquinaria vial/2.jpg" alt="Maquinaria Vial" class="img-fluid rounded">
          </div>
          <div class="col-md-6 order-md-1">
            <p class="service-subtitle ">Formación en:</p>
            <h2 class="service-title text-primary">Maquinaria Vial</h2>
            <ul class="list-unstyled service-list">
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador de Motoniveladora</span>
                <img class="icono" src="assets/iconos/Iconossvg/6.svg" alt="">
              </li>
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador de Cargadora</span>
                <img class="icono" src="assets/iconos/Iconossvg/7.svg" alt="">
              </li>
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador de Retroexcavadora</span>
                <img class="icono" src="assets/iconos/Iconossvg/8.svg" alt="">
              </li>
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador de Excavadora</span>
                <img class="icono" src="assets/iconos/Iconossvg/9.svg" alt="">
              </li>
              <li class="d-flex justify-content-between align-items-center">
                <span>Operador de Topador</span>
                <img class="icono" src="assets/iconos/Iconossvg/10.svg" alt="">
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>
    <section id="cursos" class="section-padding">
      <div class="container">
        <h2 class="display-2 text-center mb-4">Inscripciones</h2>
        <div id="cursos-list"> </div>
      </div>
    </section>

    <section id="contacto" class="bg-light section-padding contact-section">
      <div class="container">
        <h2 class="display-4 text-center mb-4">Asesoramiento gratuito</h2>
        <div class="row">
          <div class="col-md-6 p-5">
            <?php if ($contacto_mensaje !== null): ?>
              <div class="alert alert-<?php echo htmlspecialchars($contacto_tipo, ENT_QUOTES, 'UTF-8'); ?> text-center" role="alert">
                <?php echo htmlspecialchars($contacto_mensaje, ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>
            <form form action="enviar.php" method="POST">
              <p class="text-muted mb-3">Enviar un mail para preguntas generales o solicitar cuenta de Empresas para gestionar Pedidos de Inscripciones a Cursos</p>
              <div class="mb-3">
                <input type="text" class="form-control" name="nombre" placeholder="Nombre" required>
              </div>
              <div class="mb-3">
                <input type="email" class="form-control" name="email" placeholder="Correo electrónico" required>
              </div>
              <div class="mb-3">
                <input type="tel" class="form-control" name="telefono" placeholder="Teléfono" required>
              </div>
              <div class="mb-3">
                <textarea class="form-control" rows="4" placeholder="Mensaje" name="mensaje" required></textarea>
              </div>
              <input type="submit" class="btn btn-primary w-100"></input>
              <input type="hidden" name="-next" value="http://localhost/p/">

            </form>
          </div>
          <div class="col-md-6 p-5">
            <img src="assets/imagenes/fondo/asesoramiento-empresas.jpg" alt="Asesoramiento y cuenta de empresas" class="img-fluid contact-image">
          </div>
        </div>
      </div>
    </section>

    <button class="button" onclick="scrollToTop()">
      <svg class="svgIcon" viewBox="0 0 384 512">
        <path
          d="M214.6 41.4c-12.5-12.5-32.8-12.5-45.3 0l-160 160c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L160 141.2V448c0 17.7 14.3 32 32 32s32-14.3 32-32V141.2L329.4 246.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3l-160-160z"></path>
      </svg>
    </button>


    <script>
      function scrollToTop() {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
      }
    </script>

    <?php include("footer.php") ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="app.js"></script>
  </div>

</body>

</html>

