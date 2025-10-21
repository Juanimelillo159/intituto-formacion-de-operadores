<?php
declare(strict_types=1);

require_once __DIR__ . '/mercadopago_common.php';

/**
 * Obtiene la inscripción asociada a una preferencia o pago.
 */
function mp_find_order(PDO $con, array $lookup): ?array
{
    $conditions = [];
    $params = [];

    foreach (['preference_id', 'payment_id', 'external_reference', 'id_pago'] as $field) {
        if (!empty($lookup[$field])) {
            $conditions[] = "mp.{$field} = :{$field}";
            $params[":{$field}"] = $lookup[$field];
        }
    }

    if (!$conditions) {
        return null;
    }

    $sql = <<<SQL
        SELECT mp.*, p.metodo AS metodo_checkout, p.estado AS pago_estado, p.monto, p.moneda,
               p.id_capacitacion, p.id_certificacion,
               COALESCE(cap.id_capacitacion, cert.id_certificacion) AS id_inscripcion,
               cap.id_curso AS id_curso_cap,
               cert.id_curso AS id_curso_cert,
               CASE
                   WHEN p.id_capacitacion IS NOT NULL THEN 'capacitacion'
                   WHEN p.id_certificacion IS NOT NULL THEN 'certificacion'
                   ELSE 'curso'
               END AS tipo_checkout,
               COALESCE(cap.nombre, cert.nombre) AS nombre,
               COALESCE(cap.apellido, cert.apellido) AS apellido,
               COALESCE(cap.email, cert.email) AS email,
               COALESCE(cap.telefono, cert.telefono) AS telefono,
               COALESCE(cur_cap.nombre_curso, cur_cert.nombre_curso, '') AS nombre_curso
          FROM checkout_mercadopago mp
          JOIN checkout_pagos p ON mp.id_pago = p.id_pago
          LEFT JOIN checkout_capacitaciones cap ON p.id_capacitacion = cap.id_capacitacion
          LEFT JOIN checkout_certificaciones cert ON p.id_certificacion = cert.id_certificacion
          LEFT JOIN cursos cur_cap ON cap.id_curso = cur_cap.id_curso
          LEFT JOIN cursos cur_cert ON cert.id_curso = cur_cert.id_curso
         WHERE %s
      ORDER BY mp.id_mp DESC
         LIMIT 1
    SQL;

    $st = $con->prepare(sprintf($sql, implode(' OR ', $conditions)));
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Actualiza la tabla checkout_pagos según el estado del pago recibido.
 */
function mp_update_payment_status(PDO $con, array $orderRow, array $paymentData): array
{
    $paymentId = (string)($paymentData['id'] ?? $orderRow['payment_id'] ?? '');
    $status = (string)($paymentData['status'] ?? '');
    $statusDetail = (string)($paymentData['status_detail'] ?? '');
    $payerEmail = (string)($paymentData['payer']['email'] ?? '');
    $paymentType = (string)($paymentData['payment_type_id'] ?? '');

    $estadoPago = 'pendiente';
    if ($status === 'approved') {
        $estadoPago = 'pagado';
    } elseif (in_array($status, ['cancelled', 'rejected'], true)) {
        $estadoPago = $status === 'cancelled' ? 'cancelado' : 'rechazado';
    }

    $sql = <<<SQL
        UPDATE checkout_mercadopago
           SET status = :status,
               status_detail = :status_detail,
               payment_id = :payment_id,
               payment_type = :payment_type,
               payer_email = :payer_email,
               actualizado_en = CURRENT_TIMESTAMP
         WHERE id_mp = :id
    SQL;
    $con->prepare($sql)->execute([
        ':status' => $status ?: null,
        ':status_detail' => $statusDetail ?: null,
        ':payment_id' => $paymentId ?: null,
        ':payment_type' => $paymentType ?: null,
        ':payer_email' => $payerEmail ?: null,
        ':id' => $orderRow['id_mp'],
    ]);

    $sqlPago = <<<SQL
        UPDATE checkout_pagos
           SET estado = :estado,
               actualizado_en = CURRENT_TIMESTAMP
         WHERE id_pago = :pago
    SQL;
    $con->prepare($sqlPago)->execute([
        ':estado' => $estadoPago,
        ':pago' => $orderRow['id_pago'],
    ]);

    if ($orderRow['id_certificacion']) {
        mp_sync_certificacion($con, (int)$orderRow['id_certificacion'], $estadoPago, $status, $statusDetail, $paymentId);
    }

    $orderRow['payment_id'] = $paymentId;
    $orderRow['status'] = $status;
    $orderRow['status_detail'] = $statusDetail;
    $orderRow['payment_type'] = $paymentType;
    $orderRow['payer_email'] = $payerEmail;
    $orderRow['pago_estado'] = $estadoPago;

    return $orderRow;
}

function mp_sync_certificacion(PDO $con, int $certificacionId, string $estadoPago, string $mpStatus, string $statusDetail, string $paymentId): void
{
    if ($certificacionId <= 0) {
        return;
    }

    $st = $con->prepare('SELECT id_estado, observaciones FROM checkout_certificaciones WHERE id_certificacion = :id LIMIT 1');
    $st->execute([':id' => $certificacionId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    $estadoActual = (int)($row['id_estado'] ?? 0);
    $obsActual = trim((string)($row['observaciones'] ?? ''));

    $nuevoEstado = $estadoActual;
    $mensaje = '';

    if ($estadoPago === 'pagado') {
        $nuevoEstado = 3;
        $mensaje = 'Pago acreditado por Mercado Pago';
    } elseif ($estadoPago === 'rechazado') {
        $nuevoEstado = $estadoActual === 3 ? 2 : $estadoActual;
        $mensaje = 'Pago rechazado por Mercado Pago';
    } elseif ($estadoPago === 'cancelado') {
        $nuevoEstado = $estadoActual === 3 ? 2 : $estadoActual;
        $mensaje = 'Pago cancelado en Mercado Pago';
    }

    if ($mensaje === '') {
        return;
    }

    if ($paymentId !== '') {
        $mensaje .= ' (#' . $paymentId . ')';
    }

    $detalle = $statusDetail ?: $mpStatus;
    if ($detalle !== '') {
        $mensaje .= ' - ' . $detalle;
    }

    $mensaje .= ' el ' . (new DateTimeImmutable())->format('d/m/Y H:i');

    if ($obsActual !== '') {
        $mensaje .= ' | ' . $obsActual;
    }

    $sql = 'UPDATE checkout_certificaciones SET observaciones = :obs WHERE id_certificacion = :id';
    $params = [
        ':obs' => $mensaje,
        ':id' => $certificacionId,
    ];

    if ($nuevoEstado !== $estadoActual) {
        $sql = 'UPDATE checkout_certificaciones SET observaciones = :obs, id_estado = :estado WHERE id_certificacion = :id';
        $params[':estado'] = $nuevoEstado;
    }

    $con->prepare($sql)->execute($params);

    if ($nuevoEstado !== $estadoActual) {
        $hist = $con->prepare('INSERT INTO historico_estado_certificaciones (id_certificacion, id_estado) VALUES (:id, :estado)');
        $hist->execute([
            ':id' => $certificacionId,
            ':estado' => $nuevoEstado,
        ]);
    }
}
