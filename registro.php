<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("sbd.php");
include("nav.php");

$registro_mensaje = isset($_SESSION['registro_mensaje']) ? $_SESSION['registro_mensaje'] : null;
$registro_tipo = isset($_SESSION['registro_tipo']) ? $_SESSION['registro_tipo'] : 'info';
$googleClientId = getenv('GOOGLE_CLIENT_ID') ?: 'TU_CLIENT_ID_DE_GOOGLE';
if ($registro_mensaje !== null) {
    unset($_SESSION['registro_mensaje'], $_SESSION['registro_tipo']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta</title>
    <link rel="icon" href="/logos/LOGO PNG-04.png" type="image/png">
    <script>window.googleClientId = '<?php echo htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8'); ?>';</script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <section class="content-wrapper">
        <div class="container">
            <div class="login-container">
                <div class="login-logo">
                    <img src="logos/LOGO PNG_Mesa de trabajo 1.png" alt="Instituto de Formacion de Operadores">
                </div>
                <?php if ($registro_mensaje !== null) : ?>
                    <div class="alert alert-<?php echo htmlspecialchars($registro_tipo); ?> text-center" role="alert">
                        <?php echo htmlspecialchars($registro_mensaje); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="admin/registro.php" id="form-registro">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electronico</label>
                        <input type="email" class="form-control" name="email" id="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="clave" class="form-label">Contrasena</label>
                        <input type="password" class="form-control" name="clave" id="clave" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirmar_clave" class="form-label">Repetir contrasena</label>
                        <input type="password" class="form-control" name="confirmar_clave" id="confirmar_clave" required>
                    </div>
                    <button type="submit" name="registrar_usuario" class="btn btn-primary w-100">Crear cuenta</button>
                </form>
                <div class="text-center mt-3">
                    <span class="text-muted">o reg&amp;iacute;strate con</span>
                </div>
                <div id="googleSignInMessage" role="alert" style="display:none;"></div>
                <div id="googleSignInButton" class="mt-3 w-100"></div>
            </div>
        </div>
    </section>
    <?php include("footer.php"); ?>
    <script>
        (function () {
            var form = document.getElementById('form-registro');
            if (!form) {
                return;
            }
            var password = form.querySelector('#clave');
            var confirm = form.querySelector('#confirmar_clave');
            var validate = function () {
                if (!confirm || !password) {
                    return;
                }
                if (confirm.value !== password.value) {
                    confirm.setCustomValidity('Las contrasenas no coinciden');
                } else {
                    confirm.setCustomValidity('');
                }
            };
            if (password) {
                password.addEventListener('input', validate);
            }
            if (confirm) {
                confirm.addEventListener('input', validate);
            }
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>window.googleAuthEndpoint = 'admin/google_auth.php';</script>
    <script src="assets/js/google-auth.js"></script>
</body>
</html>
