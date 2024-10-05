<?php 
    include "nav.php"

?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmación de Envío</title>
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .confirmation-card {
            max-width: 500px;
            margin: 50px auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card confirmation-card shadow">
            <div class="card-body text-center">
                <h2 class="card-title mb-4">¡Gracias por tu consulta!</h2>
                <div class="mb-4">
                    <i class="bi bi-check-circle text-success" style="font-size: 4rem;"></i>
                </div>
                <p class="card-text mb-4">
                    Tu consulta ha sido enviada correctamente. Nos pondremos en contacto contigo pronto.
                </p>
                <a href="/" class="btn btn-primary">Volver a la página principal</a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS (opcional, solo si necesitas componentes interactivos de Bootstrap) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>