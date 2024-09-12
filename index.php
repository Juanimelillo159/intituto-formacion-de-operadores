<?php
include 'sbd.php';
$sql_carrusel = $con->prepare("SELECT * FROM banner");
$sql_carrusel->execute();
$banners = $sql_carrusel->fetchAll(PDO::FETCH_ASSOC);
?>


<!DOCTYPE html>
<html lang="es">


<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instituto de Formación de Operadores</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      background-color: #1a1a1a;
      color: white;
    }
    .navbar {
      background-color: #000;
    }
    .product-card {
      margin-bottom: 30px;
    }
    .product-image {
      height: 200px;
      object-fit: cover;
    }
    .card-title {
      color: #ff1493;
    }
    .footer {
      background-color: #000;
      padding: 20px 0;
      text-align: center;
    }
    .price {
      color: #00ff00;
      font-size: 1.5rem;
    }
    .btn-custom {
      background-color: #ff1493;
      color: white;
    }
  </style>

</head>

<body>
  <div class="content-wrapper">
    <?php include("nav.php") ?>
    <header class="hero-section py-5">
      <div class="container h-100">
        <div class="row h-100 align-items-center">
          <div class="col-md-6 d-flex justify-content-center align-items-center">
            <img src="logos/LOGO PNG-04.png" alt="Instituto de Formación de Operadores logo" class="hero-logo img-fluid logo-small">
            <img src="logos/LOGO PNG_Mesa de trabajo 1.svg" alt="Instituto de Formación de Operadores letras" class="hero-logo img-fluid logo-small">
          </div>
          <div class="col-md-6 d-flex justify-content-center align-items-center">
            <div class="hero-text text-center">
              <h1>Capacitación profesional para personas mediante nuestros diversos cursos</h1>
            </div>
          </div>
        </div>
    </header>
    <section id="carrusel" class="py-5 section-transition">
      <div class="container">
        <div id="imageCarousel" class="carousel slide" data-bs-ride="carousel">
          <div class="carousel-inner">
            <?php foreach ($banners as $index => $banner) { ?>
              <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                <img class="d-block w-100" src="imagenes/banners/<?php echo $banner['imagen_banner']; ?>" alt="Slide <?php echo $index + 1; ?>">
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

    <section id="quienes-somos" class=" bg-light section-padding ">
      <div class="container">
        <h2 class="display-4 text-center mb-4">¿Quiénes Somos?</h2>
        <div class="row">
          <div class="col-lg-8 mx-auto">
            <p class="text-center">
              EN EL INSTITUTO DE FORMACIÓN DE OPERADORES, NOS ENORGULLECE SER LÍDERES EN EL ÁREA DE CAPACITACIÓN DEL PERSONAL. DESDE NUESTRA FUNDACIÓN NOS HEMOS DEDICADO A LA ARDUA Y FUNDAMENTAL TAREA DE DARLE A LAS PERSONAS LAS HERRAMIENTAS PARA DESARROLLAR DE MANERA SEGURA Y EFICIENTE, LAS TAREAS QUE DESEMPEÑA.
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
        <h2 class="display-4 text-center mb-4">SERVICIOS</h2>
        <!-- Equipos de izaje -->
        <div class="row align-items-center mb-5">
          <div class="col-md-6">
            <img src="assets/imagenes/equipos de izaje/1.jpg" alt="Equipos de izaje" class="img-fluid rounded">
          </div>
          <div class="col-md-6">
            <p class="service-subtitle">Formacion en:</p>
            <h2 class="service-title">Equipos de izaje</h2>
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
            <div class="mt-4">
              <a href="#contacto" class="btn btn-primary btn-lg">CONTACTO</a>
            </div>
          </div>
        </div>

        <!-- Maquinaria Vial -->
        <div class="row align-items-center">
          <div class="col-md-6 order-md-2">
            <img src="assets/imagenes/maquinaria vial/2.jpg" alt="Maquinaria Vial" class="img-fluid rounded">
          </div>
          <div class="col-md-6 order-md-1">
            <p class="service-subtitle">Formacion en:</p>
            <h2 class="service-title">Maquinaria Vial</h2>
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
            <div class="mt-4">
              <a href="#contacto" class="btn btn-primary btn-lg">CONTACTO</a>
            </div>
          </div>
        </div>
      </div>
    </section>
    <section id="cursos" class="section-padding">
      <div class="container">
        <h2 class="dispaly-2 text-center mb-4">Cursos Disponibles</h2>
        <div class="mb-4 text-center">
          <input type="text" id="search-input" placeholder="Buscar cursos..." class="form-control w-50 d-inline-block">
        </div>
        <div id="cursos-list"> </div>
      </div>
    </section>

    <section id="contacto" class="bg-light section-padding contact-section">
      <div class="container">
        <h2 class="text-center mb-4">Asesoramiento gratuito</h2>
        <div class="row">
          <div class="col-md-6 p-5">
            <form form action="https://formsubmit.co/juanimelillo@gmail.com" method="POST">
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
            <img src="assets/imagenes/fondo/contactanos.webp" alt="Asesoramiento" class="img-fluid contact-image">
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