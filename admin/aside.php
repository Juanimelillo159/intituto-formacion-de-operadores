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
            <!-- imagenes del carrusel -->
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="fas fa-solid fa-table"></i>
                <p>
                  Carrusel
                  <i class="right fas fa-angle-left"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="carrusel.php" class="nav-link">
                    <i class="fas fa-solid fa-list"></i>
                    <p>Mostrar imagenes</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="agregar_imagen.php" class="nav-link">
                    <i class="fas fa-solid fa-plus"></i>
                    <p>Añadir imagen</p>
                  </a>
                </li>
              </ul>
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