<?php
declare(strict_types=1);

require_once __DIR__ . '/mercadopago_common.php';
require_once __DIR__ . '/mercadopago_mailer.php';

/**
 * Obtiene la inscripción relacionada a Mercado Pago a partir de distintos identificadores.
 */
function checkout_fetch_mp_order(PDO $con, array $lookup): ?array
{
    $baseSql = "SELECT mp.*, p.estado AS pago_estado, p.monto, p.moneda, p.id_inscripcion,
                       i.nombre, i.apellido, i.email, i.telefono, i.dni, i.direccion, i.ciudad, i.provincia, i.pais,
                       c.nombre_curso
                  FROM checkout_mercadopago mp
            INNER JOIN checkout_pagos p ON mp.id_pago = p.id_pago
            INNER JOIN checkout_inscripciones i ON p.id_inscripcion = i.id_inscripcion
            INNER JOIN cursos c ON i.id_curso = c.id_curso
                 WHERE %s
              ORDER BY mp.id_mp DESC
                 LIMIT 1";

    $candidates = [
        'preference_id' => $lookup['preference_id'] ?? null,
        'payment_id' => $lookup['payment_id'] ?? null,
        'external_reference' => $lookup['external_reference'] ?? null,
        'id_pago' => $lookup['id_pago'] ?? null,
    ];

    foreach ($candidates as $field => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $sql = sprintf($baseSql, "mp.{$field} = :value");
        $st = $con->prepare($sql);
        $st->execute([':value' => $value]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
    }

    return null;
}

/**
 * Actualiza el estado del pago a partir de la respuesta de Mercado Pago.
 */
function checkout_sync_mp_payment(PDO $con, array $mpRow, ?array $paymentData, string $source, bool $sendEmails = true): array
{
    $mpStatus = (string) ($paymentData['status'] ?? $mpRow['status'] ?? '');
    $statusDetail = (string) ($paymentData['status_detail'] ?? $mpRow['status_detail'] ?? '');
    $paymentId = (string) ($paymentData['id'] ?? $mpRow['payment_id'] ?? ($paymentData['payment_id'] ?? ''));
    $paymentType = (string) ($paymentData['payment_type_id'] ?? $mpRow['payment_type'] ?? '');
    $payerEmail = (string) ($paymentData['payer']['email'] ?? $mpRow['payer_email'] ?? '');
    $transactionAmount = isset($paymentData['transaction_amount']) ? (float) $paymentData['transaction_amount'] : (float) $mpRow['monto'];
    $currency = (string) ($paymentData['currency_id'] ?? $mpRow['moneda'] ?? 'ARS');

    if ($mpStatus === '') {
        $mpStatus = 'pending';
    }
    $estadoPago = checkout_map_mp_status_to_estado($mpStatus);

    $payloadData = checkout_decode_payload($mpRow['payload'] ?? null);
    $payloadData['history'] = $payloadData['history'] ?? [];
    $payloadData['history'][] = [
        'source' => $source,
        'status' => $mpStatus,
        'status_detail' => $statusDetail,
        'payment_id' => $paymentId,
        'synced_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
    ];
    if ($paymentData !== null) {
        $payloadData['last_payment'] = [
            'id' => $paymentId,
            'status' => $mpStatus,
            'status_detail' => $statusDetail,
            'payment_type' => $paymentType,
            'payer_email' => $payerEmail,
            'transaction_amount' => $transactionAmount,
            'currency' => $currency,
        ];
    }

    $payloadJson = checkout_encode_payload($payloadData);

    $con->beginTransaction();
    try {
        $upMp = $con->prepare(
            "UPDATE checkout_mercadopago
                SET status = :status,
                    status_detail = :detail,
                    payment_id = :payment,
                    payment_type = :type,
                    payer_email = :payer,
                    payload = :payload,
                    actualizado_en = NOW()
              WHERE id_mp = :id"
        );
        $upMp->execute([
            ':status' => $mpStatus,
            ':detail' => $statusDetail !== '' ? $statusDetail : null,
            ':payment' => $paymentId !== '' ? $paymentId : null,
            ':type' => $paymentType !== '' ? $paymentType : null,
            ':payer' => $payerEmail !== '' ? $payerEmail : null,
            ':payload' => $payloadJson,
            ':id' => $mpRow['id_mp'],
        ]);

        $upPago = $con->prepare(
            "UPDATE checkout_pagos
                SET estado = :estado,
                    monto = :monto,
                    moneda = :moneda,
                    actualizado_en = NOW()
              WHERE id_pago = :id"
        );
        $upPago->execute([
            ':estado' => $estadoPago,
            ':monto' => $transactionAmount,
            ':moneda' => strtoupper($currency),
            ':id' => $mpRow['id_pago'],
        ]);

        $con->commit();
    } catch (Throwable $dbException) {
        $con->rollBack();
        checkout_log_event('checkout_mp_sync_error', ['id_pago' => $mpRow['id_pago'], 'payment_id' => $paymentId], $dbException);
        throw $dbException;
    }

    $mpRow['status'] = $mpStatus;
    $mpRow['status_detail'] = $statusDetail;
    $mpRow['payment_id'] = $paymentId;
    $mpRow['payment_type'] = $paymentType;
    $mpRow['payer_email'] = $payerEmail;
    $mpRow['payload'] = $payloadJson;
    $mpRow['pago_estado'] = $estadoPago;
    $mpRow['monto'] = $transactionAmount;
    $mpRow['moneda'] = strtoupper($currency);

    $emailsSent = false;
    $emailErrors = [];
    if ($sendEmails && $estadoPago === 'pagado') {
        $alreadySent = (bool) ($payloadData['notifications']['emails_sent'] ?? false);
        if (!$alreadySent) {
            $orderData = [
                'orden' => $mpRow['id_inscripcion'],
                'curso' => $mpRow['nombre_curso'],
                'nombre' => $mpRow['nombre'],
                'apellido' => $mpRow['apellido'],
                'email' => $mpRow['email'],
                'telefono' => $mpRow['telefono'],
                'monto' => $mpRow['monto'],
                'moneda' => $mpRow['moneda'],
                'payment_type' => $paymentType !== '' ? $paymentType : 'Mercado Pago',
                'payment_id' => $paymentId,
                'preference_id' => $mpRow['preference_id'],
                'status_detail' => $statusDetail,
            ];
            $emailsSent = checkout_send_purchase_emails($orderData, $source, $emailErrors);
            if ($emailsSent) {
                $payloadData['notifications']['emails_sent'] = true;
                $payloadData['notifications']['sent_at'] = (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM);
                $payloadData['notifications']['source'] = $source;
                $payloadJson = checkout_encode_payload($payloadData);
                $st = $con->prepare('UPDATE checkout_mercadopago SET payload = :payload WHERE id_mp = :id');
                $st->execute([':payload' => $payloadJson, ':id' => $mpRow['id_mp']]);
                $mpRow['payload'] = $payloadJson;
            }
        } else {
            $emailsSent = true;
        }
    }

    checkout_log_event('checkout_mp_sync', [
        'id_pago' => $mpRow['id_pago'],
        'mp_status' => $mpStatus,
        'estado_pago' => $estadoPago,
        'source' => $source,
        'emails_sent' => $emailsSent,
        'email_errors' => $emailErrors,
    ]);

    return [
        'row' => $mpRow,
        'mp_status' => $mpStatus,
        'estado_pago' => $estadoPago,
        'emails_sent' => $emailsSent,
        'email_errors' => $emailErrors,
    ];
}
