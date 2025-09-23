<?php
require_once __DIR__ . '/../vendor/autoload.php';

use MercadoPago\MercadoPagoConfig;

// Configurá tus credenciales de prueba o producción.
MercadoPagoConfig::setAccessToken('APP_USR-7854455794715802-090817-16873ed9cd7e95bdeeb28ccdd3c7a47d-1578491289');

return [
    'public_key' => 'APP_USR-f6bee8bf-0ec6-4ce2-810e-c0011329fc31',
    'access_token' => 'APP_USR-7854455794715802-090817-16873ed9cd7e95bdeeb28ccdd3c7a47d-1578491289',
    'integrator_id' => null,
];
