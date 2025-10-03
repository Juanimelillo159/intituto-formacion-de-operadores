<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$message = $_GET['message'] ?? 'El pago no pudo completarse.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago rechazado</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #fdf2f2; color: #8a1c1c; }
        .container { max-width: 540px; margin: 0 auto; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); }
        h1 { font-size: 1.8rem; margin-bottom: 1rem; }
        p { margin-bottom: 1rem; }
        a { display: inline-block; margin-top: 1.5rem; color: #009ee3; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Pago rechazado</h1>
        <p><?= htmlspecialchars((string)$message, ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Revisá los datos de tu tarjeta o elegí otro medio de pago para intentarlo nuevamente.</p>
        <a href="index.php">Volver a intentar</a>
    </div>
</body>
</html>
