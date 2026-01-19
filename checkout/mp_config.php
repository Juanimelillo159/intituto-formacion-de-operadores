<?php

/**
 * Archivo de configuración centralizado para Mercado Pago.
 *
 * Se inspira en el ejemplo provisto por Mercado Pago y expone constantes
 * reutilizables en el checkout. Las credenciales declaradas aquí son de
 * prueba y fueron solicitadas expresamente para el entorno de desarrollo.
 */

// Credenciales de prueba proporcionadas por el usuario.
const MP_ACCESS_TOKEN = 'APP_USR-697544312345765-091622-213b5d03eba54b706beafeeaf8e3f2ba-1578491289';
const MP_CLIENT_ID = '697544312345765';

// La public key de prueba existente se mantiene para el frontend.
const MP_PUBLIC_KEY = 'APP_USR-f6bee8bf-0ec6-4ce2-810e-c0011329fc31';

// Configuración de cuotas para Checkout Pro.
// Ajustá estos valores según el máximo de cuotas que quieras ofrecer.
const MP_MAX_INSTALLMENTS = 12;
const MP_DEFAULT_INSTALLMENTS = 1;

// URLs auxiliares empleadas durante el flujo de pago. Si necesitás utilizarlas
// en otro dominio, modificá BASE_URL por el dominio público correspondiente.
const MP_BASE_URL = 'https://c6063fb185d9.ngrok-free.app/intituto-formacion-de-operadores';
const MP_URL_SUCCESS = MP_BASE_URL . '/checkout/gracias.php';
const MP_URL_FAILURE = MP_BASE_URL . '/checkout/gracias.php';
const MP_URL_PENDING = MP_BASE_URL . '/checkout/gracias.php';
const MP_URL_WEBHOOK = MP_BASE_URL . '/checkout/mercadopago_webhook.php';

return [
    'public_key' => MP_PUBLIC_KEY,
    'access_token' => MP_ACCESS_TOKEN,
    'integrator_id' => null,
    'base_url' => MP_BASE_URL,
    'success_url' => MP_URL_SUCCESS,
    'failure_url' => MP_URL_FAILURE,
    'pending_url' => MP_URL_PENDING,
    'notification_url' => MP_URL_WEBHOOK,
    'max_installments' => MP_MAX_INSTALLMENTS,
    'default_installments' => MP_DEFAULT_INSTALLMENTS,
];
