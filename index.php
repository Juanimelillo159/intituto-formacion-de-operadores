<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instituto de Formación de Operadores</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
        
</head>
<body>
    <div class="background-shape shape-1"></div>
    <div class="background-shape shape-2"></div>
    <div class="content-wrapper">
        <?php include("nav.php") ?>
        <header class="hero-section py-5">
            <div class="container h-100">
                <div class="row h-100 align-items-center">
                <div class="col-md-6 d-flex justify-content-center align-items-center">
                    <img src="logos/LOGO PNG_Mesa de trabajo 1.png" alt="Instituto de Formación de Operadores" class="hero-logo img-fluid">
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
                  <div class="carousel-item active">
                    <img src="assets/imagenes/fondo/img1.jpg" class="d-block w-100" alt="Imagen 1">
                  </div>
                  <div class="carousel-item">
                    <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/no%20imagen-lP6G9FYKBGQ6BVhP1Ps2HkZ1eXQdyb.jpg" class="d-block w-100" alt="Imagen 2">
                  </div>
                  <div class="carousel-item">
                    <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/no%20imagen-lP6G9FYKBGQ6BVhP1Ps2HkZ1eXQdyb.jpg" class="d-block w-100" alt="Imagen 3">
                  </div>
                </div>
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
        <section id="quienes-somos" class="section-padding ">
            <div class="container">
                <h2 class="text-center mb-4">¿Quiénes Somos?</h2>
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
                      <i class="fas fa-truck-moving text-primary"></i>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                      <span>Operador de Grúa Móvil de Pluma Articulada</span>
                      <i class="fas fa-truck-pickup text-primary"></i>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                      <span>Operador de Hidroelevador</span>
                      <i class="fas fa-truck-loading text-primary"></i>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                      <span>Operador de Autoelevador</span>
                      <i class="fas fa-forklift text-primary"></i>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                      <span>Operador Rigger</span>
                      <i class="fas fa-hard-hat text-primary"></i>
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
                      <i class="fas fa-road text-primary"></i>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                      <span>Operador de Cargadora</span>
                      <i class="fas fa-truck-monster text-primary"></i>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                      <span>Operador de Retroexcavadora</span>
                      <i class="fas fa-truck-pickup text-primary"></i>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                      <span>Operador de Excavadora</span>
                      <i class="fas fa-digger text-primary"></i>
                    </li>
                    <li class="d-flex justify-content-between align-items-center">
                      <span>Operador de Topador</span>
                      <i class="fas fa-tractor text-primary"></i>
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
              <h2 class="text-center mb-4">Otros Cursos Disponibles</h2>
              <div class="mb-4 text-center">
                  <input type="text" id="search-input" placeholder="Buscar cursos..." class="form-control w-50 d-inline-block">
              </div>
              <div id="cursos-list">
                  <!-- Aquí se cargará dinámicamente la lista de cursos y la paginación -->
              </div>
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
                        <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/no%20imagen-lP6G9FYKBGQ6BVhP1Ps2HkZ1eXQdyb.jpg" alt="Asesoramiento" class="img-fluid contact-image">
                    </div>
                </div>
            </div>
        </section>
        <footer class="bg-black text-white py-5">
            <div class="container">
                <div class="row">
                    <div class="col-md-4 mb-4 mb-md-0">
                        <img src="logos/LOGO PNG-07.png" alt="Logo Instituto de Formación de Operadores" class="footer-logo mb-3">
                        <p>&copy; 2023 Instituto de Formación de Operadores. Todos los derechos reservados.</p>
                    </div>
                    <div class="col-md-4 mb-4 mb-md-0">
                        <h5 class="mb-3">Contacto</h5>
                        <p><i class="fas fa-map-marker-alt me-2"></i>Sarmiento 1385, Comodoro Rivadavia, Argentina</p>
                        <p><i class="fas fa-phone me-2"></i>297-5305505</p>
                        <p><i class="fas fa-envelope me-2"></i>bbs.oil.mining@gmail.com</p>
                    </div>
                    <div class="col-md-4">
                        <h5 class="mb-3">Síguenos</h5>
                        <div class="social-icons">
                            <a href="#" target="_blank" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                            <a href="#" target="_blank" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="#" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                            <a href="#" target="_blank" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="app.js"></script>
    </div>
</body>
</html>