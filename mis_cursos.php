<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$misCursosAlert = $_SESSION['mis_cursos_alert'] ?? null;
if ($misCursosAlert !== null) {
    unset($_SESSION['mis_cursos_alert']);
}

$page_title = 'Mis cursos | Instituto de Formacion';
$page_description = 'Cursos disponibles para tu cuenta.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';
?>
<!DOCTYPE html>
<html lang="es">
<?php include 'head.php'; ?>
<body class="config-page d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="index.php" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver al inicio</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Mis cursos</h1>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-card shadow text-center">
                    <p class="mb-4">Todavia no hay cursos para mostrar en tu cuenta.</p>
                    <a class="btn btn-gradient" href="index.php#cursos">Explorar cursos disponibles</a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($misCursosAlert !== null): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var data = <?php echo json_encode($misCursosAlert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

            if (!data) {
                return;
            }

            var title = data.title || 'Sesion iniciada';
            var message = data.message || '';
            var text = (message && message.trim()) ? message : title;

            var styleId = 'mis-cursos-toast-style';
            if (!document.getElementById(styleId)) {
                var style = document.createElement('style');
                style.id = styleId;
                style.textContent = '\n                    .floating-login-alert {\n                        position: fixed;\n                        top: 1rem;\n                        right: 1rem;\n                        z-index: 2000;\n                        min-width: 220px;\n                        max-width: 320px;\n                        padding: 0.75rem 1rem;\n                        border-radius: 0.5rem;\n                        background-color: #198754;\n                        border: 1px solid #146c43;\n                        color: #fff;\n                        box-shadow: 0 0.5rem 1rem rgba(25, 135, 84, 0.35);\n                        opacity: 0;\n                        transform: translateY(-10px);\n                        transition: opacity 200ms ease, transform 200ms ease;\n                    }\n                    .floating-login-alert.show {\n                        opacity: 1;\n                        transform: translateY(0);\n                    }\n                    .floating-login-alert.hide {\n                        opacity: 0;\n                        transform: translateY(-10px);\n                    }\n                ';
                document.head.appendChild(style);
            }

            var alertNode = document.createElement('div');
            alertNode.className = 'floating-login-alert alert alert-success';
            alertNode.setAttribute('role', 'alert');
            alertNode.textContent = text;

            var offsetTop = 16;
            var stickyNav = document.querySelector('.navbar.sticky-top');
            if (stickyNav) {
                offsetTop = stickyNav.getBoundingClientRect().height + 16;
            }
            alertNode.style.top = offsetTop + 'px';

            alertNode.addEventListener('click', function () {
                alertNode.classList.add('hide');
            });

            alertNode.addEventListener('transitionend', function (event) {
                if (event.propertyName === 'opacity' && alertNode.classList.contains('hide')) {
                    alertNode.remove();
                }
            });

            document.body.appendChild(alertNode);

            requestAnimationFrame(function () {
                alertNode.classList.add('show');
            });

            setTimeout(function () {
                alertNode.classList.add('hide');
            }, 5000);
        });
    </script>
<?php endif; ?>
</body>
</html>

