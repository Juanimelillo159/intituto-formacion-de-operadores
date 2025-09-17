<?php
declare(strict_types=1);

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mercadopago_helpers.php';

$paymentId = null;

try {
    if (!($con instanceof PDO)) {
        throw new RuntimeException('Conexión inválida');
    }

    $config = mp_load_config();

    $rawBody = file_get_contents('php://input');
    $bodyData = null;
    if ($rawBody !== false && $rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $bodyData = $decoded;
        }
    }

    if (isset($_GET['data_id'])) {
        $paymentId = (string)$_GET['data_id'];
    }
    if (isset($_GET['id'], $_GET['type']) && $_GET['type'] === 'payment') {
        $paymentId = (string)$_GET['id'];
    }
    if (isset($_GET['id'], $_GET['topic']) && $_GET['topic'] === 'payment') {
        $paymentId = (string)$_GET['id'];
    }
    if ($bodyData && isset($bodyData['data']['id'])) {
        $paymentId = (string)$bodyData['data']['id'];
    }
    if (!$paymentId && isset($_POST['data_id'])) {
        $paymentId = (string)$_POST['data_id'];
    }
    if (!$paymentId && isset($_POST['id'])) {
        $paymentId = (string)$_POST['id'];
    }

    if (!$paymentId) {
        mp_log('notification_sin_id', ['get' => $_GET, 'post' => $_POST, 'body' => $bodyData]);
        http_response_code(400);
        echo 'missing id';
        return;
    }

    $result = mp_sync_payment($con, $config, $paymentId);
    if (!$result) {
        mp_log('notification_pago_no_encontrado', ['payment_id' => $paymentId, 'body' => $bodyData]);
        http_response_code(404);
        echo 'not found';
        return;
    }

    mp_log('notification_ok', ['payment_id' => $paymentId, 'estado' => $result['estado'], 'parsed' => $result['parsed']]);
    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    mp_log('notification_error', ['payment_id' => $paymentId], $e);
    http_response_code(500);
    echo 'error';
}
