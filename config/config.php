<?php
return [
    'mercadopago' => [
        // Clave pública del checkout de Mercado Pago (comienza con "APP_USR-...").
        'public_key' => 'TU_PUBLIC_KEY_AQUI',
        // Token privado de la cuenta (comienza con "APP_USR-...").
        'access_token' => 'TU_ACCESS_TOKEN_AQUI',
        // URL completas a las que Mercado Pago redirigirá después del pago.
        // Actualizá estos valores con la ruta correcta de tu sitio.
        'success_url' => 'https://tu-dominio.com/checkout/gracias.php?status=approved',
        'failure_url' => 'https://tu-dominio.com/checkout/gracias.php?status=failure',
        'pending_url' => 'https://tu-dominio.com/checkout/gracias.php?status=pending',
        // URL opcional para recibir notificaciones IPN/Webhook.
        'notification_url' => 'https://tu-dominio.com/checkout/notificaciones.php',
    ],
    'mailer' => [
        'host' => 'smtp.tu-dominio.com',
        'port' => 465,
        'encryption' => 'ssl',
        'username' => 'usuario@tu-dominio.com',
        'password' => 'CONTRASEÑA_SMTP',
        'from_email' => 'no-reply@tu-dominio.com',
        'from_name' => 'Instituto de Formación',
        'admin_email' => 'admin@tu-dominio.com',
    ],
];
