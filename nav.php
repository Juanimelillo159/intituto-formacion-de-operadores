<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*  */
?>

<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="icono.png">
</head>
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="logos/LOGO PNG-03.png" alt="Logo">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="index.php#quienes-somos">Nosotros</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#servicios-capacitacion">Servicios</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#cursos">Cursos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#contacto">Contactanos</a>
                </li>
                <?php
                $permiso = isset($_SESSION["permiso"]) ? (int)$_SESSION["permiso"] : null;
                if (isset($_SESSION["usuario"])) {
                    ?>
                    <li class="nav-item dropdown user-menu">
                        <a class="nav-link dropdown-toggle d-flex align-items-center justify-content-center user-menu-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa-solid fa-user"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end user-menu-dropdown" aria-labelledby="userMenu">
                            <?php if ($permiso === 1) { ?>
                                <li><a class="dropdown-item" href="admin/admin.php">Panel administrador</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php } ?>
                            <li><a class="dropdown-item" href="mis_cursos.php">Mis cursos</a></li>
                            <li><a class="dropdown-item" href="historial_compras.php">Historial de compras</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="admin/cerrar_sesion.php">Cerrar sesion</a></li>
                        </ul>
                    </li>
                <?php } else { ?>
                    <li class="nav-item">
                        <a class="text-decoration-none" href="login.php">
                            <button class="button-nav">
                                iniciar sesion
                                <div class="arrow-wrapper">
                                    <div class="arrow"></div>
                                </div>
                            </button>
                        </a>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>
</nav>
