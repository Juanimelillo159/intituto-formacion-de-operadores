<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago pendiente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #fffaf0; color: #8a621c; }
        .container { max-width: 540px; margin: 0 auto; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); }
        h1 { font-size: 1.8rem; margin-bottom: 1rem; }
        p { margin-bottom: 1rem; }
        a { display: inline-block; margin-top: 1.5rem; color: #009ee3; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Pago pendiente de revisión</h1>
        <p>El pago está siendo procesado por Mercado Pago. Te notificaremos cuando se acredite.</p>
        <a href="index.php">Volver al inicio</a>
    </div>
</body>
</html>
