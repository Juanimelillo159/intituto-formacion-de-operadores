<?php
include("sbd.php");
include("nav.php");

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión</title>
    
</head>

<body>
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
</body>

</html>