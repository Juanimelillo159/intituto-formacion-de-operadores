<?php
    include("sbd.php");
    include("nav.php");

?>

<!DOCTYPE html>
<html lang="es">
<head>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-logo">
                <img src="logos/LOGO PNG_Mesa de trabajo 1.png" alt="Instituto de Formación de Operadores">
            </div>
            <form method="POST" action="admin/procesarsbd.php">
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
    <?php include("footer.php") ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>