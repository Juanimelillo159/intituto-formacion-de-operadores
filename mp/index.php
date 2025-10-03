<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Checkout Mercado Pago</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; color: #333; }
        .container { max-width: 480px; margin: 0 auto; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); text-align: center; }
        h1 { font-size: 1.75rem; margin-bottom: 1rem; }
        p { margin-bottom: 1.5rem; }
        button { background-color: #009ee3; color: #fff; border: none; border-radius: 8px; padding: 0.85rem 1.5rem; font-size: 1rem; cursor: pointer; transition: background-color 0.2s ease-in-out; }
        button:hover { background-color: #007bbf; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Inscripción al curso</h1>
        <p>Para finalizar tu inscripción hacé clic en el botón y completá el pago mediante Mercado Pago.</p>
        <form action="create_preference.php" method="post">
            <button type="submit">Pagar con Mercado Pago</button>
        </form>
    </div>
</body>
</html>
