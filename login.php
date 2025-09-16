<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("sbd.php");
include("nav.php");

$login_mensaje = isset($_SESSION['login_mensaje']) ? $_SESSION['login_mensaje'] : null;
$login_tipo = isset($_SESSION['login_tipo']) ? $_SESSION['login_tipo'] : 'info';
if ($login_mensaje !== null) {
    unset($_SESSION['login_mensaje'], $_SESSION['login_tipo']);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesi&oacute;n</title>
    <link rel="icon" href="/logos/LOGO PNG-04.png" type="image/png">
    
</head>

<body>
    <section class="content-wrapper">
        <div class="container">
            <div class="login-container">
                <div class="login-logo">
                    <img src="logos/LOGO PNG_Mesa de trabajo 1.png" alt="Instituto de Formaci&oacute;n de Operadores">
                </div>
                <?php if ($login_mensaje !== null) : ?>
                    <div class="alert alert-<?php echo htmlspecialchars($login_tipo); ?> text-center" role="alert">
                        <?php echo htmlspecialchars($login_mensaje); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="admin/sesion.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electr&oacute;nico</label>
                        <input type="email" class="form-control" name="email" id="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="clave" class="form-label">Contrase&ntilde;a</label>
                        <input type="password" class="form-control" name="clave" id="clave" required>
                    </div>
                    <button type="submit" name="iniciar_sesion" class="btn btn-primary w-100">Iniciar sesi&oacute;n</button>
                    <p class="text-center mt-3">&iquest;No tienes cuenta? <a href="registro.php">Crear cuenta</a></p>
                </form>
            </div>
        </div>
    </section>
    <?php include("footer.php"); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
