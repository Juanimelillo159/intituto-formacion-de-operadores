<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;

try {
    $rawInput = file_get_contents('php://input') ?: '';
    $payload = json_decode($rawInput, true);

    @file_put_contents(__DIR__ . '/webhook.log', date('c') . ' RAW: ' . $rawInput . PHP_EOL, FILE_APPEND);

    $type = $payload['type'] ?? null;
    $action = $payload['action'] ?? null;
    $paymentId = $payload['data']['id'] ?? null;

    if ($type === 'payment' && $paymentId) {
        MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
        $client = new PaymentClient();
        $payment = $client->get((string)$paymentId);

        $stmt = db()->prepare('
            INSERT INTO mp_payments
                (mp_payment_id, status, status_detail, preference_id, external_reference, payer_email, amount, currency_id, notification_type, action, raw_payload)
            VALUES
                (:id, :status, :status_detail, :preference, :external_reference, :payer_email, :amount, :currency_id, :notification_type, :action, :raw)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                status_detail = VALUES(status_detail),
                updated_at = CURRENT_TIMESTAMP,
                raw_payload = VALUES(raw_payload)
        ');

        $stmt->execute([
            ':id' => (string)$payment->id,
            ':status' => $payment->status ?? null,
            ':status_detail' => $payment->status_detail ?? null,
            ':preference' => $payment->metadata->preference_id ?? null,
            ':external_reference' => $payment->external_reference ?? null,
            ':payer_email' => $payment->payer->email ?? null,
            ':amount' => $payment->transaction_amount ?? null,
            ':currency_id' => $payment->currency_id ?? null,
            ':notification_type' => $type,
            ':action' => $action,
            ':raw' => $rawInput,
        ]);
    }

    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
