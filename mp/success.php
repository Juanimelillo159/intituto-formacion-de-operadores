<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$paymentId = $_GET['payment_id'] ?? '';
$externalReference = $_GET['external_reference'] ?? '';
$collectionStatus = $_GET['collection_status'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago aprobado</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; background: #f5f5f5; color: #2d572c; }
        .container { max-width: 540px; margin: 0 auto; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); }
        h1 { font-size: 1.8rem; margin-bottom: 1rem; }
        p { margin-bottom: 0.75rem; }
        .details { background: #f0f9f0; padding: 1rem; border-radius: 8px; }
        a { display: inline-block; margin-top: 1.5rem; color: #009ee3; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>¡Pago aprobado!</h1>
        <p>Recibimos tu pago correctamente. En breve nos pondremos en contacto con vos.</p>
        <div class="details">
            <p><strong>ID de pago:</strong> <?= htmlspecialchars((string)$paymentId, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Referencia:</strong> <?= htmlspecialchars((string)$externalReference, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Estado:</strong> <?= htmlspecialchars((string)$collectionStatus, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
        <a href="index.php">Volver al inicio</a>
    </div>
</body>
</html>
