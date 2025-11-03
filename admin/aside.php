<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

</head>

<body class="hold-transition sidebar-mini sidebar-collapse">
  <!-- Site wrapper -->
  <div class="wrapper">
    <!-- Navbar -->


    <!-- Main Sidebar Container -->F
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
      <!-- Brand Logo -->
      <a href="admin.php" class="brand-link">
        <img src="../logos/LOGO PNG-04.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8">
      </a>

      <!-- Sidebar -->
      <div class="sidebar">
        <!-- Sidebar user (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
          <div class="info">
            <a href="#" class="d-block"></a>
          </div>
        </div>



        <!-- Sidebar Menu -->
        <nav class="mt-2">
          <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false"> 
            <!-- Usuarios -->
            <li class="nav-item">
              <a href="admin.php" class="nav-link">
                <i class="fas fa-solid fa-chart-pie"></i>
                <p>
                  Dashboard
                </p>
              </a>
            </li>
            <li class="nav-item">
              <a href="usuarios.php" class="nav-link">
                <i class="fas fa-users-cog"></i>
                <p>Gestión de usuarios</p>
              </a>
            </li>
            <!-- cursos -->
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="fas fa-solid fa-table"></i>
                <p>
                  cursos
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="cursos.php" class="nav-link">
                    <i class="fas fa-solid fa-list"></i>
                    <p>Mostrar todos</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="agregar_curso.php" class="nav-link">
                    <i class="fas fa-solid fa-plus"></i>
                    <p>Añadir curso</p>
                  </a>
                </li>
              </ul>
            </li>
            <!-- imágenes del carrusel -->
            <li class="nav-item">
              <a href="carrusel.php" class="nav-link">
                <i class="fas fa-images"></i>
                <p>Carrusel</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="categorias.php" class="nav-link">
                <i class="fas fa-tags"></i>
                <p>Categorías</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="certificaciones.php" class="nav-link">
                <i class="fas fa-solid fa-file-signature"></i>
                <p>Certificaciones</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="pedidos_inscripciones.php" class="nav-link">
                <i class="fas fa-solid fa-clipboard-list"></i>
                <p>Pedidos de inscripción</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="pagos.php" class="nav-link">
                <i class="fas fa-solid fa-receipt"></i>
                <p>Pagos</p>
              </a>
            </li>

            <li class="nav-item">
              <a href="configuracion.php" class="nav-link">
                <i class="fas fa-solid fa-sliders-h"></i>
                <p>Configuración de la página</p>
              </a>
            </li>

          </ul>
        </nav>
        <!-- /.sidebar-menu -->
      </div>
      <!-- /.sidebar -->
    </aside>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
      <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->
  </div>
  <!-- ./wrapper -->
</body>

</html>
