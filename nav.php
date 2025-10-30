
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

if (!function_exists('site_settings_get_notice')) {
    require_once __DIR__ . '/site_settings.php';
}

if (!isset($site_settings) || !is_array($site_settings)) {
    if (isset($con) && $con instanceof PDO) {
        try {
            $site_settings = get_site_settings($con);
        } catch (Throwable $ignored) {
            $site_settings = site_settings_defaults();
        }
    } else {
        $site_settings = site_settings_defaults();
    }
}

$site_notice_banner = '';
if (site_settings_get_mode($site_settings) === 'normal') {
    $noticeText = site_settings_get_notice($site_settings);
    if ($noticeText !== '') {
        $site_notice_banner = '<div class="site-notice-banner"><div class="container">'
            . '<span class="site-notice-label">Aviso</span>'
            . '<span class="site-notice-text">' . htmlspecialchars($noticeText, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</div></div>';
    }
}
?>

<style>
    .site-notice-banner {
        background: linear-gradient(90deg, #0f3c7a, #1f78ff);
        color: #fff;
        padding: 0.65rem 0;
        font-size: 0.95rem;
        box-shadow: 0 4px 14px rgba(15, 60, 122, 0.25);
    }

    .site-notice-banner .container {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
    }

    .site-notice-label {
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.8rem;
        background: rgba(255, 255, 255, 0.2);
        padding: 0.2rem 0.75rem;
        border-radius: 999px;
    }

    .site-notice-text {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
    }

    .user-menu {
        position: relative;
    }

    .user-menu-toggle {
        cursor: pointer;
    }

    .user-menu-dropdown {
        position: absolute;
        top: calc(100% + 0.5rem);
        right: 0;
        min-width: 14rem;
        padding: 0.5rem 0;
        margin: 0;
        border: 1px solid rgba(0, 0, 0, 0.1);
        border-radius: 0.75rem;
        background-color: #fff;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.1);
        display: none;
        list-style: none;
        z-index: 1050;
    }

    .user-menu.show > .user-menu-dropdown,
    .user-menu-dropdown.show {
        display: block;
    }

    .user-menu-dropdown .dropdown-item {
        padding: 0.65rem 1.25rem;
        font-size: 0.95rem;
    }

    .user-menu-dropdown .dropdown-item:hover,
    .user-menu-dropdown .dropdown-item:focus {
        background-color: #f8f9fa;
        color: #212529;
    }

    .user-menu-dropdown .dropdown-divider {
        margin: 0.5rem 0;
        border-top-color: rgba(0, 0, 0, 0.08);
    }

    .mobile-nav-buttons {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }

    .mobile-nav-btn {
        background: none;
        border: none;
        padding: 0.5rem;
        border-radius: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s ease;
    }

    .mobile-nav-btn:focus,
    .mobile-nav-btn:hover {
        background: rgba(0, 0, 0, 0.05);
        outline: none;
    }

    .mobile-nav-btn svg {
        width: 24px;
        height: 24px;
        stroke: #212529;
    }

    .mobile-menu-open {
        overflow: hidden;
    }

    .mobile-menu-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.5);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease;
        z-index: 1039;
    }

    .mobile-menu-overlay.active {
        opacity: 1;
        visibility: visible;
    }

    .mobile-menu {
        position: fixed;
        top: 0;
        right: -100%;
        width: min(80%, 320px);
        height: 100vh;
        background: #fff;
        box-shadow: -2px 0 12px rgba(0, 0, 0, 0.1);
        transition: right 0.3s ease;
        z-index: 1040;
        display: flex;
        flex-direction: column;
    }

    .mobile-menu.active {
        right: 0;
    }

    .mobile-menu-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .mobile-menu-title {
        font-size: 1rem;
        font-weight: 600;
        color: #212529;
    }

    .mobile-menu-close {
        background: none;
        border: none;
        padding: 0.25rem;
        cursor: pointer;
    }

    .mobile-menu-close svg {
        width: 24px;
        height: 24px;
        stroke: #495057;
    }

    .mobile-menu-items {
        padding: 0.5rem 0;
        overflow-y: auto;
        flex: 1;
    }

    .mobile-menu-item,
    .mobile-menu-item button {
        width: 100%;
        text-align: left;
        padding: 0.85rem 1.5rem;
        border: none;
        background: none;
        color: #212529;
        text-decoration: none;
        display: block;
        font-size: 1rem;
        transition: background 0.2s ease;
    }

    .mobile-menu-item:hover,
    .mobile-menu-item:focus,
    .mobile-menu-item button:hover,
    .mobile-menu-item button:focus {
        background: #f1f3f5;
        color: #212529;
        outline: none;
    }

    .mobile-menu-divider {
        height: 1px;
        background: #e9ecef;
        margin: 0.5rem 0;
    }

    @media (min-width: 992px) {
        .mobile-nav-buttons,
        .mobile-menu,
        .mobile-menu-overlay {
            display: none !important;
        }
    }
</style>

<?php echo $site_notice_banner; ?>
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="<?php echo $normalized_base; ?>/index.php">
            <img src="<?php echo $normalized_base; ?>/logos/LOGO PNG-03.png" alt="Logo">
        </a>
        <div class="mobile-nav-buttons d-lg-none">
            <button class="mobile-nav-btn" type="button" data-menu="pages" aria-label="Abrir menú de navegación">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <button class="mobile-nav-btn" type="button" data-menu="user" aria-label="Abrir menú de usuario">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z" />
                    <path d="M4 20a8 8 0 0 1 16 0" />
                </svg>
            </button>
        </div>
        <div class="collapse navbar-collapse justify-content-end d-none d-lg-flex" id="navbarNav">
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
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $normalized_base; ?>/politicas.php">Políticas</a>
                </li>
                <?php
                $permiso = isset($_SESSION["permiso"]) ? (int)$_SESSION["permiso"] : null;
                if (isset($_SESSION["usuario"])) {
                    ?>
                    <li class="nav-item dropdown user-menu">
                        <a class="nav-link dropdown-toggle d-flex align-items-center justify-content-center user-menu-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="true" aria-label="Abrir menú de usuario">
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
                                <li><a class="dropdown-item" href="solicitudes_inscripciones.php">Solicitudes Inscripciones</a></li>
                            <?php } ?>
                            <?php if ($permiso !== 3) { ?>
                                <li><a class="dropdown-item" href="<?php echo $normalized_base; ?>/mis_cursos.php">Mis cursos</a></li>
                            <?php } ?>
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

<div class="mobile-menu-overlay" id="mobileNavOverlay"></div>

<div class="mobile-menu" id="mobilePagesMenu" aria-hidden="true" role="dialog" aria-label="Menú de navegación">
    <div class="mobile-menu-header">
        <span class="mobile-menu-title">Navegación</span>
        <button class="mobile-menu-close" type="button" data-close-mobile-menu aria-label="Cerrar menú">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 6l12 12M6 18 18 6" />
            </svg>
        </button>
    </div>
    <div class="mobile-menu-items">
        <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/index.php#quienes-somos">Nosotros</a>
        <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/index.php#servicios-capacitacion">Servicios</a>
        <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/index.php#cursos">Cursos</a>
        <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/index.php#contacto">Contactanos</a>
        <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/politicas.php">Políticas</a>
    </div>
</div>

<div class="mobile-menu" id="mobileUserMenu" aria-hidden="true" role="dialog" aria-label="Menú de usuario">
    <div class="mobile-menu-header">
        <span class="mobile-menu-title">Cuenta</span>
        <button class="mobile-menu-close" type="button" data-close-mobile-menu aria-label="Cerrar menú">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 6l12 12M6 18 18 6" />
            </svg>
        </button>
    </div>
    <div class="mobile-menu-items">
        <?php if (isset($_SESSION["usuario"])) { ?>
            <?php if ($permiso === 1) { ?>
                <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/admin/admin.php">Panel administrador</a>
                <div class="mobile-menu-divider" role="separator"></div>
            <?php } ?>
            <?php if ($permiso === 3) { ?>
                <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/trabajadores.php">Trabajadores</a>
                <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/inscripciones.php">Inscripciones</a>
                <div class="mobile-menu-divider" role="separator"></div>
            <?php } ?>
            <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/mis_cursos.php">Mis cursos</a>
            <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/historial_compras.php">Historial de compras</a>
            <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/configuracion.php">Panel de configuración</a>
            <div class="mobile-menu-divider" role="separator"></div>
            <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/admin/cerrar_sesion.php">Cerrar sesión</a>
        <?php } else { ?>
            <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/login.php">Iniciar sesión</a>
            <a class="mobile-menu-item" href="<?php echo $normalized_base; ?>/register.php">Crear cuenta</a>
        <?php } ?>
    </div>
</div>
<script>
    (function () {
        const script = document.currentScript;
        let nav = script ? script.previousElementSibling : null;

        if (!nav || !(nav instanceof HTMLElement) || !nav.matches('nav.navbar')) {
            nav = document.querySelector('nav.navbar');
        }

        const toggle = nav ? nav.querySelector('#userMenu') : null;
        const menu = nav ? nav.querySelector('.user-menu .dropdown-menu') : null;
        const parentItem = nav ? nav.querySelector('.user-menu') : null;

        if (!toggle || !menu || !parentItem) {
            return;
        }

        const closeFallbackMenu = () => {
            if (!menu.classList.contains('show')) {
                return;
            }
            menu.classList.remove('show');
            parentItem.classList.remove('show');
            toggle.setAttribute('aria-expanded', 'false');
        };

        const handleDocumentClick = (event) => {
            if (window.bootstrap && typeof window.bootstrap.Dropdown === 'function') {
                return;
            }
            if (!menu.contains(event.target) && !toggle.contains(event.target)) {
                closeFallbackMenu();
            }
        };

        document.addEventListener('click', handleDocumentClick);

        toggle.addEventListener('click', (event) => {
            if (window.bootstrap && typeof window.bootstrap.Dropdown === 'function') {
                window.bootstrap.Dropdown.getOrCreateInstance(toggle, { autoClose: 'outside' });
                return;
            }
            event.preventDefault();
            const isOpen = menu.classList.toggle('show');
            parentItem.classList.toggle('show', isOpen);
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        toggle.addEventListener('keydown', (event) => {
            if (window.bootstrap && typeof window.bootstrap.Dropdown === 'function') {
                return;
            }
            if (event.key === 'Escape') {
                closeFallbackMenu();
            }
        });
    })();

    (function () {
        const overlay = document.getElementById('mobileNavOverlay');
        const pagesMenu = document.getElementById('mobilePagesMenu');
        const userMenu = document.getElementById('mobileUserMenu');
        const nav = document.querySelector('nav.navbar');

        if (!overlay || !pagesMenu || !userMenu || !nav) {
            return;
        }

        const menus = {
            pages: pagesMenu,
            user: userMenu,
        };

        const closeMenus = () => {
            Object.values(menus).forEach((menu) => {
                menu.classList.remove('active');
                menu.setAttribute('aria-hidden', 'true');
            });
            overlay.classList.remove('active');
            document.body.classList.remove('mobile-menu-open');
        };

        const toggleMenu = (type) => {
            const targetMenu = menus[type];
            if (!targetMenu) {
                return;
            }

            const isActive = targetMenu.classList.contains('active');

            closeMenus();

            if (!isActive) {
                targetMenu.classList.add('active');
                targetMenu.setAttribute('aria-hidden', 'false');
                overlay.classList.add('active');
                document.body.classList.add('mobile-menu-open');
            }
        };

        overlay.addEventListener('click', closeMenus);

        nav.querySelectorAll('.mobile-nav-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const type = button.getAttribute('data-menu');
                toggleMenu(type);
            });
        });

        document.querySelectorAll('[data-close-mobile-menu]').forEach((button) => {
            button.addEventListener('click', closeMenus);
        });

        document.querySelectorAll('.mobile-menu-item').forEach((item) => {
            item.addEventListener('click', closeMenus);
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992) {
                closeMenus();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenus();
            }
        });
    })();
</script>

