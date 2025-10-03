<?php
declare(strict_types=1);

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mercadopago_service.php';

header('Content-Type: application/json; charset=utf-8');

$response = ['success' => false];
$statusCode = 200;

try {
    if (!isset($con) || !($con instanceof PDO)) {
        throw new RuntimeException('Conexión a la base de datos no disponible.');
    }
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = file_get_contents('php://input') ?: '';
    $decoded = [];
    if ($input !== '') {
        @file_put_contents(__DIR__ . '/mercadopago_webhook.log', date('c') . ' RAW: ' . $input . PHP_EOL, FILE_APPEND);
        $decoded = json_decode($input, true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
    }

    $paymentId = null;
    if (isset($_GET['type']) && $_GET['type'] === 'payment' && isset($_GET['id'])) {
        $paymentId = (string) $_GET['id'];
    }
    if (!$paymentId && isset($_GET['data_id'])) {
        $paymentId = (string) $_GET['data_id'];
    }
    if (!$paymentId && isset($_GET['payment_id'])) {
        $paymentId = (string) $_GET['payment_id'];
    }
    if (!$paymentId && isset($decoded['data']['id'])) {
        $paymentId = (string) $decoded['data']['id'];
    }

    if (!$paymentId) {
        checkout_log_event('checkout_mp_webhook_skip', ['reason' => 'sin_payment_id', 'query' => $_GET ?? [], 'body' => $decoded]);
        $response['success'] = true;
        $response['message'] = 'No se recibió un identificador de pago.';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        return;
    }

    $paymentData = checkout_fetch_payment_from_mp($paymentId);

    $lookup = [
        'payment_id' => $paymentId,
        'preference_id' => $paymentData['preference_id'] ?? null,
        'external_reference' => $paymentData['external_reference'] ?? null,
        'id_pago' => $paymentData['metadata']['id_pago'] ?? null,
    ];

    $mpRow = checkout_fetch_mp_order($con, $lookup);
    if (!$mpRow) {
        checkout_log_event('checkout_mp_webhook_not_found', ['payment_id' => $paymentId, 'lookup' => $lookup]);
        throw new RuntimeException('No se encontró la inscripción asociada al pago.');
    }

    $syncResult = checkout_sync_mp_payment($con, $mpRow, $paymentData, 'webhook', true);

    $response['success'] = true;
    $response['status'] = $syncResult['mp_status'];
    $response['estado_pago'] = $syncResult['estado_pago'];
    $response['emails_sent'] = $syncResult['emails_sent'];
} catch (Throwable $exception) {
    $statusCode = 500;
    $response['success'] = false;
    $response['message'] = $exception->getMessage();
    checkout_log_event('checkout_mp_webhook_error', ['message' => $exception->getMessage()], $exception);
}

http_response_code($statusCode);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
