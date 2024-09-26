<?php
session_start();

// Verificar si no hay una sesión iniciada
if (!isset($_SESSION['usuario'])) {
  // Redirigir al usuario a una página de inicio de sesión o mostrar un mensaje de error
  header("Location: ../index.php");
  exit;
} ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="../admin/css/fontawesome-free/css/all.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="../admin/css/bootstrap-4.min.css">
  <!-- Toastr -->
  <link rel="stylesheet" href="../admin/css/toastr.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="../admin/css/adminlte.min.css">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Panel administrado</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">

  <!-- Font Awesome Icons -->
  <link href="../admin/css//fontawesome-free/css/all.min.css" rel="stylesheet">
  <link href="../admin/css//fontawesome-free/css/all.css" rel="stylesheet">

  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="../admin/css/OverlayScrollbars.min.css">

  <!-- Theme style -->
  <link rel="stylesheet" href="../admin/css/adminlte.min.css">

  <!-- DataTables -->
  <link rel="stylesheet" href="../admin/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="../admin/css/responsive.bootstrap4.min.css">
  <link rel="stylesheet" href="../admin/css/buttons.bootstrap4.min.css">

  <!-- Bootstrap 3.3.7 -->
  <!-- <link rel="stylesheet" href="../admin/css/bootstrap.min.css"> -->
</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed layout-footer-fixed">
  <div class="wrapper">

    <!-- Preloader -->
    <div class="preloader flex-column justify-content-center align-items-center">
      <img class="animation__wobble" src="../logos/LOGO PNG-04.png" alt="WembleyLogo" height="60" width="60">
    </div>

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-dark">
      <!-- Left navbar links -->
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
          <a href="admin.php" class="nav-link">inicio</a>
        </li>
        <li class="nav-item d-none d-sm-inline-block">
          <a href="../index.php" class="nav-link">Pagina principal</a>
        </li>
      </ul>

      <!-- Right navbar links -->
      <ul class="navbar-nav ml-auto">
        <!-- Navbar Search -->
        <li class="dropdown user user-menu">
          <a href="#" class="dropdown-toggle" data-toggle="dropdown">
            <span class="hidden-xs"><?php echo $_SESSION["email"]; ?></span>
          </a>
          <ul class="dropdown-menu">
            <!-- User image -->
            <li class="user-header">
              <img src="../logos/LOGO PNG-04.png" class="img-circle" alt="User Image">

              <p>
                <?php echo ("nombre del usuario"); ?>
              </p>
            </li>
            <!-- Menu Footer-->
            <li class="user-footer">
              <div class="pull-right">
                <a href="cerrar_sesion.php" class="btn btn-default btn-flat">Cerrar Sesión</a>
              </div>
            </li>
          </ul>
        </li>
      </ul>
    </nav>

  </div>
  <!-- ./wrapper -->
</body>

</html>