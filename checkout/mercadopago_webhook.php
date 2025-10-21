<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mercadopago_common.php';
require_once __DIR__ . '/mercadopago_service.php';

use MercadoPago\Exceptions\MPApiException;

$response = ['success' => false];
$statusCode = 200;

try {
    if (!isset($con) || !($con instanceof PDO)) {
        throw new RuntimeException('No se pudo conectar con la base de datos.');
    }

    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true) ?: [];

    $paymentId = null;
    if (isset($_GET['id']) && isset($_GET['type']) && $_GET['type'] === 'payment') {
        $paymentId = (string)$_GET['id'];
    } elseif (isset($_GET['data_id'])) {
        $paymentId = (string)$_GET['data_id'];
    } elseif (isset($body['data']['id'])) {
        $paymentId = (string)$body['data']['id'];
    }

    if (!$paymentId) {
        mp_log('mp_webhook_skip', ['reason' => 'sin_payment_id', 'query' => $_GET, 'body' => $body]);
        $response['success'] = true;
        $response['message'] = 'Sin identificador de pago.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }

    mp_log('mp_webhook_received', ['payment_id' => $paymentId]);

    $paymentData = mp_fetch_payment($paymentId);

    $lookup = [
        'payment_id' => $paymentId,
        'preference_id' => $paymentData['preference_id'] ?? null,
        'external_reference' => $paymentData['external_reference'] ?? null,
        'id_pago' => $paymentData['metadata']['id_pago'] ?? null,
    ];

    $orderRow = mp_find_order($con, $lookup);
    if (!$orderRow) {
        mp_log('mp_webhook_not_found', ['payment_id' => $paymentId, 'lookup' => $lookup]);
        throw new RuntimeException('No encontramos la orden asociada al pago.');
    }

    $updated = mp_update_payment_status($con, $orderRow, $paymentData);

    $response['success'] = true;
    $response['status'] = $updated['status'] ?? null;
    $response['estado_pago'] = $updated['pago_estado'] ?? null;
} catch (Throwable $exception) {
    $statusCode = $exception instanceof InvalidArgumentException ? 400 : 500;
    $response['message'] = $exception->getMessage();
    if (mp_is_debug()) {
        $mpException = $exception instanceof MPApiException
            ? $exception
            : ($exception->getPrevious() instanceof MPApiException ? $exception->getPrevious() : null);
        if ($mpException) {
            $response['debug'] = mp_api_exception_debug($mpException);
        }
    }
    mp_log('mp_webhook_error', ['error' => $exception->getMessage()], $exception);
}

http_response_code($statusCode);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
