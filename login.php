<?php
include("sbd.php");

$page_title = "Login | Instituto de Formación";
$page_description = "Pagina de inicio de sesión del Instituto de Formación de Operadores";
?>

<!DOCTYPE html>
<html lang="es">


<?php include("head.php") ?>

<body>
<?php include("nav.php"); ?>

    <section class="content-wrapper">
        <div class="container">
            <div class="login-container">
                <div class="login-logo">
                    <img src="logos/LOGO PNG_Mesa de trabajo 1.png" alt="Instituto de Formación de Operadores">
                </div>
                <form method="POST" action="admin/sesion.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electrónico</label>
                        <input type="email" class="form-control" name="email" id="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="clave" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" name="clave" id="clave" required>
                    </div>
                    <button type="submit" name="iniciar_sesion" class="btn btn-primary w-100">Iniciar sesión</button>
                </form>
            </div>
        </div>
    </section>
    <?php include("footer.php"); ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>