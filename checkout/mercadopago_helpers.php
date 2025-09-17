<?php
declare(strict_types=1);

use MercadoPago\Payment;
use MercadoPago\SDK;

function mp_log(string $accion, array $data = [], ?Throwable $ex = null): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $file = $logDir . '/mercadopago.log';
    $row = [
        'ts' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        'accion' => $accion,
        'data' => $data,
    ];
    if ($ex) {
        $row['error'] = [
            'type' => get_class($ex),
            'message' => $ex->getMessage(),
            'code' => (string)$ex->getCode(),
        ];
    }
    @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

function mp_load_config(?string $configPath = null): array
{
    $path = $configPath ?? (__DIR__ . '/../config/config.php');
    if (!is_file($path)) {
        throw new RuntimeException('No se encontró el archivo de configuración de la aplicación.');
    }
    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('El archivo de configuración debe retornar un array.');
    }
    return $config;
}

function mp_configure_sdk(array $config): void
{
    $token = trim((string)($config['mercadopago']['access_token'] ?? ''));
    if ($token === '') {
        throw new RuntimeException('Configurá el access token de Mercado Pago en config/config.php.');
    }
    SDK::setAccessToken($token);
}

function mp_parse_external_reference(?string $external): ?array
{
    if ($external === null || $external === '') {
        return null;
    }
    if (preg_match('/^checkout:(\d+):(\d+)$/', $external, $matches)) {
        return [
            'inscripcion_id' => (int)$matches[1],
            'pago_id' => (int)$matches[2],
        ];
    }
    return null;
}

function mp_map_status(string $status): string
{
    $normalized = strtolower(trim($status));
    switch ($normalized) {
        case 'approved':
            return 'aprobado';
        case 'pending':
            return 'pendiente';
        case 'in_process':
            return 'procesando';
        case 'authorized':
            return 'autorizado';
        case 'in_mediation':
            return 'en_mediacion';
        case 'rejected':
            return 'rechazado';
        case 'cancelled':
            return 'cancelado';
        case 'refunded':
            return 'reintegrado';
        case 'charged_back':
            return 'contracargo';
        default:
            return $normalized !== '' ? $normalized : 'pendiente';
    }
}

function mp_ensure_table(PDO $con): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `checkout_mercadopago` (
  `id_mp` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_pago` INT UNSIGNED NOT NULL,
  `preference_id` VARCHAR(80) NOT NULL,
  `init_point` VARCHAR(255) NOT NULL,
  `sandbox_init_point` VARCHAR(255) DEFAULT NULL,
  `external_reference` VARCHAR(120) DEFAULT NULL,
  `status` VARCHAR(60) NOT NULL DEFAULT 'pendiente',
  `status_detail` VARCHAR(120) DEFAULT NULL,
  `payment_id` VARCHAR(60) DEFAULT NULL,
  `payment_type` VARCHAR(80) DEFAULT NULL,
  `payer_email` VARCHAR(150) DEFAULT NULL,
  `payload` LONGTEXT,
  `creado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_mp`),
  UNIQUE KEY `ux_checkout_mp_pago` (`id_pago`),
  UNIQUE KEY `ux_checkout_mp_pref` (`preference_id`),
  CONSTRAINT `fk_checkout_mp_pago` FOREIGN KEY (`id_pago`) REFERENCES `checkout_pagos` (`id_pago`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    $con->exec($sql);
    $ensured = true;
}

function mp_update_payment_records(PDO $con, array $parsed, array $paymentData): void
{
    mp_ensure_table($con);
    $estadoInterno = mp_map_status($paymentData['status'] ?? '');
    $payload = $paymentData['raw'] ?? null;

    $con->beginTransaction();
    try {
        $updatePago = $con->prepare('UPDATE checkout_pagos SET estado = :estado, actualizado_en = NOW() WHERE id_pago = :pago LIMIT 1');
        $updatePago->execute([
            ':estado' => $estadoInterno,
            ':pago' => $parsed['pago_id'],
        ]);

        $updateMp = $con->prepare('UPDATE checkout_mercadopago SET status = :status, status_detail = :detail, payment_id = :payment_id, payment_type = :payment_type, payer_email = :payer_email, external_reference = :external_reference, payload = :payload, actualizado_en = NOW() WHERE id_pago = :pago LIMIT 1');
        $updateMp->execute([
            ':status' => $paymentData['status'] ?? 'pendiente',
            ':detail' => $paymentData['status_detail'] ?? null,
            ':payment_id' => $paymentData['payment_id'] ?? null,
            ':payment_type' => $paymentData['payment_type'] ?? null,
            ':payer_email' => $paymentData['payer_email'] ?? null,
            ':external_reference' => $paymentData['external_reference'] ?? null,
            ':payload' => $payload,
            ':pago' => $parsed['pago_id'],
        ]);

        if ($updateMp->rowCount() === 0) {
            $existsMp = $con->prepare('SELECT 1 FROM checkout_mercadopago WHERE id_pago = :pago LIMIT 1');
            $existsMp->execute([':pago' => $parsed['pago_id']]);
            $rowExists = $existsMp->fetchColumn();

            if (!$rowExists) {
                $insertMp = $con->prepare('INSERT INTO checkout_mercadopago (id_pago, preference_id, init_point, sandbox_init_point, external_reference, status, status_detail, payment_id, payment_type, payer_email, payload) VALUES (:pago, :pref, :init, :sandbox, :ref, :status, :detail, :payment, :type, :payer, :payload)');
                $insertMp->execute([
                    ':pago' => $parsed['pago_id'],
                    ':pref' => $paymentData['preference_id'] ?? '',
                    ':init' => $paymentData['init_point'] ?? '',
                    ':sandbox' => $paymentData['sandbox_init_point'] ?? null,
                    ':ref' => $paymentData['external_reference'] ?? null,
                    ':status' => $paymentData['status'] ?? 'pendiente',
                    ':detail' => $paymentData['status_detail'] ?? null,
                    ':payment' => $paymentData['payment_id'] ?? null,
                    ':type' => $paymentData['payment_type'] ?? null,
                    ':payer' => $paymentData['payer_email'] ?? null,
                    ':payload' => $payload,
                ]);
            }
        }

        $con->commit();
    } catch (Throwable $e) {
        if ($con->inTransaction()) {
            $con->rollBack();
        }
        throw $e;
    }
}

function mp_sync_payment(PDO $con, array $config, string $paymentId): ?array
{
    mp_configure_sdk($config);

    $payment = Payment::find_by_id($paymentId);
    if (!$payment) {
        return null;
    }

    $externalReference = (string)$payment->external_reference;
    $parsed = mp_parse_external_reference($externalReference);
    if (!$parsed) {
        return null;
    }

    $paymentArray = json_decode(json_encode($payment), true);
    $data = [
        'status' => $paymentArray['status'] ?? '',
        'status_detail' => $paymentArray['status_detail'] ?? null,
        'payment_id' => $paymentArray['id'] ?? $paymentId,
        'payment_type' => $paymentArray['payment_type_id'] ?? null,
        'payer_email' => $paymentArray['payer']['email'] ?? null,
        'external_reference' => $externalReference,
        'raw' => json_encode($paymentArray, JSON_UNESCAPED_UNICODE),
    ];

    mp_update_payment_records($con, $parsed, $data);

    return [
        'parsed' => $parsed,
        'data' => $data,
        'estado' => mp_map_status($data['status'] ?? ''),
    ];
}
