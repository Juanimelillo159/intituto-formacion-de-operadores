<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;

try {
    MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);

    $request = [
        'items' => [[
            'id' => 'curso-001',
            'title' => 'Curso de capacitación',
            'description' => 'Pago de curso de capacitación',
            'currency_id' => 'ARS',
            'quantity' => 1,
            'unit_price' => 1.00,
        ]],
        'external_reference' => 'ref-' . bin2hex(random_bytes(4)),
        'back_urls' => [
            'success' => URL_SUCCESS,
            'failure' => URL_FAILURE,
            'pending' => URL_PENDING,
        ],
        'auto_return' => 'approved',
        'notification_url' => URL_WEBHOOK,
    ];

    $client = new PreferenceClient();
    $preference = $client->create($request);

    $stmt = db()->prepare('
        INSERT INTO mp_preferences (preference_id, init_point, sandbox_init_point, external_reference, amount_ars)
        VALUES (:pid, :initp, :sinitp, :extref, :amt)
    ');
    $stmt->execute([
        ':pid' => $preference->id ?? null,
        ':initp' => $preference->init_point ?? null,
        ':sinitp' => $preference->sandbox_init_point ?? null,
        ':extref' => $request['external_reference'],
        ':amt' => 1.00,
    ]);

    header('Location: ' . $preference->init_point);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error creando preferencia: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
