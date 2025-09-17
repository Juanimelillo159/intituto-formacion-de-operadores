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
                    <a class="nav-link" href="index.php#contacto">Contáctanos</a>
                </li>
                <?php
                if (isset($_SESSION["usuario"])) { ?>
                    <li class="nav-item">
                        <a class="text-decoration-none" href="admin/admin.php">
                            <button class="button-nav">
                                panel adm
                                <div class="arrow-wrapper">
                                    <div class="arrow"></div>
                                </div>
                            </button>
                        </a>
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