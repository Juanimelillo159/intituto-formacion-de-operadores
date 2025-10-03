<?php
// config.php
// Reemplazá estas constantes con los datos de tu cuenta o entorno de pruebas.
const MP_ACCESS_TOKEN = 'APP_USR-697544312345765-091622-213b5d03eba54b706beafeeaf8e3f2ba-1578491289';
const MP_CLIENT_ID    = '697544312345765'; // Opcional, a mano si lo necesitás

// URL base donde subís estos archivos (sin la parte de success.php, etc.).
// Ejemplo: 'https://tu-dominio.com/mp' o la URL pública de tu túnel ngrok.
const MP_BASE_URL = 'https://TU-DOMINIO-O-URL-PUBLICA/mp';

const URL_SUCCESS = MP_BASE_URL . '/success.php';
const URL_FAILURE = MP_BASE_URL . '/failure.php';
const URL_PENDING = MP_BASE_URL . '/pending.php';
const URL_WEBHOOK = MP_BASE_URL . '/webhook.php';
