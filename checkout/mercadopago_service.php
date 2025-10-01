<?php
declare(strict_types=1);

require_once __DIR__ . '/mercadopago_common.php';
require_once __DIR__ . '/mercadopago_mailer.php';

/**
 * Obtiene la inscripción relacionada a Mercado Pago a partir de distintos identificadores.
 */
function checkout_fetch_mp_order(PDO $con, array $lookup): ?array
{
    $baseSql = "SELECT mp.*, p.estado AS pago_estado, p.monto, p.moneda,
                       p.id_capacitacion, p.id_certificacion,
                       COALESCE(cap.id_capacitacion, cert.id_certificacion) AS id_inscripcion,
                       CASE
                           WHEN p.id_capacitacion IS NOT NULL THEN 'capacitacion'
                           WHEN p.id_certificacion IS NOT NULL THEN 'certificacion'
                           ELSE NULL
                       END AS tipo_checkout,
                       COALESCE(cap.nombre, cert.nombre) AS nombre,
                       COALESCE(cap.apellido, cert.apellido) AS apellido,
                       COALESCE(cap.email, cert.email) AS email,
                       COALESCE(cap.telefono, cert.telefono) AS telefono,
                       CASE WHEN p.id_capacitacion IS NOT NULL THEN cap.dni ELSE NULL END AS dni,
                       CASE WHEN p.id_capacitacion IS NOT NULL THEN cap.direccion ELSE NULL END AS direccion,
                       CASE WHEN p.id_capacitacion IS NOT NULL THEN cap.ciudad ELSE NULL END AS ciudad,
                       CASE WHEN p.id_capacitacion IS NOT NULL THEN cap.provincia ELSE NULL END AS provincia,
                       CASE WHEN p.id_capacitacion IS NOT NULL THEN cap.pais ELSE NULL END AS pais,
                       COALESCE(cap.id_curso, cert.id_curso) AS id_curso,
                       COALESCE(cur_cap.nombre_curso, cur_cert.nombre_certificacion, cur_cert.nombre_curso, '') AS nombre_curso
                  FROM checkout_mercadopago mp
            INNER JOIN checkout_pagos p ON mp.id_pago = p.id_pago
             LEFT JOIN checkout_capacitaciones cap ON p.id_capacitacion = cap.id_capacitacion
             LEFT JOIN checkout_certificaciones cert ON p.id_certificacion = cert.id_certificacion
             LEFT JOIN cursos cur_cap ON cap.id_curso = cur_cap.id_curso
             LEFT JOIN cursos cur_cert ON cert.id_curso = cur_cert.id_curso
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

function checkout_registrar_historico_certificacion(PDO $con, int $idCertificacion, int $estado): void
{
    $st = $con->prepare('
        INSERT INTO historico_estado_certificaciones (id_certificacion, id_estado)
        VALUES (:id, :estado)
    ');
    $st->execute([
        ':id' => $idCertificacion,
        ':estado' => $estado,
    ]);
}

function checkout_extract_certificacion_id(array $payloadData, ?array $paymentData): int
{
    $sources = [];
    if (isset($payloadData['request']['metadata']) && is_array($payloadData['request']['metadata'])) {
        $sources[] = $payloadData['request']['metadata'];
    }
    if (isset($payloadData['metadata']) && is_array($payloadData['metadata'])) {
        $sources[] = $payloadData['metadata'];
    }
    if ($paymentData !== null && isset($paymentData['metadata']) && is_array($paymentData['metadata'])) {
        $sources[] = $paymentData['metadata'];
    }

    foreach ($sources as $meta) {
        if (!isset($meta['id_certificacion'])) {
            continue;
        }
        $value = $meta['id_certificacion'];
        if (is_numeric($value)) {
            $id = (int) $value;
            if ($id > 0) {
                return $id;
            }
        }
    }

    return 0;
}

function checkout_update_certificacion_por_pago(
    PDO $con,
    int $certificacionId,
    string $estadoPago,
    string $mpStatus,
    string $statusDetail,
    string $paymentId
): ?array {
    if ($certificacionId <= 0) {
        return null;
    }

    $st = $con->prepare('SELECT id_estado, observaciones FROM checkout_certificaciones WHERE id_certificacion = :id LIMIT 1');
    $st->execute([':id' => $certificacionId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $estadoActual = (int) ($row['id_estado'] ?? 0);
    $observacionesActual = trim((string) ($row['observaciones'] ?? ''));

    $accion = null;
    $nuevoEstado = null;

    if ($estadoPago === 'pagado') {
        $accion = 'Pago acreditado por Mercado Pago';
        $nuevoEstado = 3;
    } elseif (in_array($estadoPago, ['rechazado', 'cancelado'], true)) {
        $accion = $estadoPago === 'rechazado'
            ? 'Pago rechazado por Mercado Pago'
            : 'Pago cancelado en Mercado Pago';
        if ($estadoActual === 3) {
            $nuevoEstado = 2;
        } else {
            $nuevoEstado = $estadoActual;
        }
    } else {
        return null;
    }

    $notaCabecera = $accion;
    if ($paymentId !== '') {
        $notaCabecera .= ' (#' . $paymentId . ')';
    }

    if ($observacionesActual !== '' && strpos($observacionesActual, $notaCabecera) !== false) {
        return null;
    }

    $ahora = new DateTimeImmutable('now');
    $nota = $notaCabecera . ' el ' . $ahora->format('d/m/Y H:i');
    $detalle = $statusDetail !== '' ? $statusDetail : $mpStatus;
    if ($detalle !== '') {
        $nota .= ' - ' . $detalle;
    }
    if ($observacionesActual !== '') {
        $nota .= ' | ' . $observacionesActual;
    }

    $sql = 'UPDATE checkout_certificaciones SET observaciones = :obs';
    $params = [
        ':obs' => $nota,
        ':id' => $certificacionId,
    ];
    if ($nuevoEstado !== null && $nuevoEstado !== $estadoActual) {
        $sql .= ', id_estado = :estado';
        $params[':estado'] = $nuevoEstado;
    } else {
        $nuevoEstado = $estadoActual;
    }
    $sql .= ' WHERE id_certificacion = :id';

    $up = $con->prepare($sql);
    $up->execute($params);

    if ($nuevoEstado !== $estadoActual) {
        checkout_registrar_historico_certificacion($con, $certificacionId, $nuevoEstado);
    }

    return [
        'id' => $certificacionId,
        'estado' => $nuevoEstado,
        'nota' => $nota,
    ];
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
    if (!empty($payloadData['request']['metadata']['tipo_checkout']) && empty($mpRow['tipo_checkout'])) {
        $tipoFromPayload = (string) $payloadData['request']['metadata']['tipo_checkout'];
        $tipoFromPayload = strtolower(trim($tipoFromPayload));
        if ($tipoFromPayload !== '') {
            $mpRow['tipo_checkout'] = $tipoFromPayload;
        }
    }
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

    $certificacionId = checkout_extract_certificacion_id($payloadData, $paymentData);
    $certificacionUpdate = null;
    $payloadJson = '';

    $con->beginTransaction();
    try {
        if ($certificacionId > 0) {
            $certificacionUpdate = checkout_update_certificacion_por_pago(
                $con,
                $certificacionId,
                $estadoPago,
                $mpStatus,
                $statusDetail,
                $paymentId
            );
            if ($certificacionUpdate) {
                $payloadData['certificacion'] = [
                    'id' => $certificacionUpdate['id'],
                    'estado' => $certificacionUpdate['estado'],
                    'updated_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
                ];
                if (!empty($certificacionUpdate['nota'])) {
                    $payloadData['certificacion']['nota'] = $certificacionUpdate['nota'];
                }
            }
        }

        $payloadJson = checkout_encode_payload($payloadData);

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
                'tipo_checkout' => $mpRow['tipo_checkout'] ?? null,
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
        'certificacion' => $certificacionUpdate,
    ]);

    return [
        'row' => $mpRow,
        'mp_status' => $mpStatus,
        'estado_pago' => $estadoPago,
        'emails_sent' => $emailsSent,
        'email_errors' => $emailErrors,
        'certificacion' => $certificacionUpdate,
    ];
}
