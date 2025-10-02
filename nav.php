
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($base_path)) {
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
        ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
        : '';

    $currentDir = str_replace('\\', '/', realpath(__DIR__));

    if ($documentRoot !== '' && strpos($currentDir, $documentRoot) === 0) {
        $relativeProjectPath = trim(substr($currentDir, strlen($documentRoot)), '/');
    } else {
        $relativeProjectPath = isset($_SERVER['SCRIPT_NAME'])
            ? trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/')
            : '';
    }

    $base_path = $relativeProjectPath === '' ? '' : '/' . $relativeProjectPath;
}

$normalized_base = rtrim($base_path, '/');
?>

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $normalized_base; ?>/index.php">
            <img src="<?php echo $normalized_base; ?>/logos/LOGO PNG-03.png" alt="Logo">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $normalized_base; ?>/index.php#quienes-somos">Nosotros</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $normalized_base; ?>/index.php#servicios-capacitacion">Servicios</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $normalized_base; ?>/index.php#cursos">Cursos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $normalized_base; ?>/index.php#contacto">Contactanos</a>
                </li>
                <?php
                $permiso = isset($_SESSION["permiso"]) ? (int)$_SESSION["permiso"] : null;
                if (isset($_SESSION["usuario"])) {
                    ?>
                    <li class="nav-item dropdown user-menu">
                        <a class="nav-link dropdown-toggle d-flex align-items-center justify-content-center user-menu-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="user-menu-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" focusable="false" role="img" aria-hidden="true">
                                    <path fill="currentColor" d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-3.3 0-10 1.65-10 5v3h20v-3c0-3.35-6.7-5-10-5Z" />
                                </svg>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end user-menu-dropdown" aria-labelledby="userMenu">
                            <?php if ($permiso === 1) { ?>
                                <li><a class="dropdown-item" href="<?php echo $normalized_base; ?>/admin/admin.php">Panel administrador</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php } ?>
                            <?php if ($permiso === 3) { ?>
                                <li><a class="dropdown-item" href="trabajadores.php">Trabajadores</a></li>
                                <li><a class="dropdown-item" href="inscripciones.php">Inscripciones</a></li>
                            <?php } ?>
                            <li><a class="dropdown-item" href="<?php echo $normalized_base; ?>/mis_cursos.php">Mis cursos</a></li>
                            <li><a class="dropdown-item" href="<?php echo $normalized_base; ?>/historial_compras.php">Historial de compras</a></li>
                            <li><a class="dropdown-item" href="<?php echo $normalized_base; ?>/configuracion.php">Panel de configuracion</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $normalized_base; ?>/admin/cerrar_sesion.php">Cerrar sesion</a></li>
                        </ul>
                    </li>
                <?php } else { ?>
                    <li class="nav-item">
                        <a class="text-decoration-none" href="<?php echo $normalized_base; ?>/login.php">
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

