<?php

$config = [
    'public_key' => null,
    'access_token' => null,
    'integrator_id' => null,
];

if (defined('MP_PUBLIC_KEY')) {
    $config['public_key'] = MP_PUBLIC_KEY;
}

if (defined('MP_ACCESS_TOKEN')) {
    $config['access_token'] = MP_ACCESS_TOKEN;
}

if (defined('MP_INTEGRATOR_ID')) {
    $config['integrator_id'] = MP_INTEGRATOR_ID;
}

if (!$config['public_key']) {
    $envPublic = getenv('MP_PUBLIC_KEY') ?: getenv('MERCADOPAGO_PUBLIC_KEY');
    if ($envPublic) {
        $config['public_key'] = trim((string) $envPublic) ?: null;
    }
}

if (!$config['access_token']) {
    $envToken = getenv('MP_ACCESS_TOKEN') ?: getenv('MERCADOPAGO_ACCESS_TOKEN');
    if ($envToken) {
        $config['access_token'] = trim((string) $envToken) ?: null;
    }
}

if (!$config['integrator_id']) {
    $envIntegrator = getenv('MP_INTEGRATOR_ID') ?: getenv('MERCADOPAGO_INTEGRATOR_ID');
    if ($envIntegrator) {
        $config['integrator_id'] = trim((string) $envIntegrator) ?: null;
    }
}

return $config;
