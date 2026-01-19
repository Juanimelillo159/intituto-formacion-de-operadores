<?php
declare(strict_types=1);

use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\MercadoPagoConfig;

require_once __DIR__ . '/mp_config.php';

/**
 * Configura el SDK con el access token de pruebas.
 */
function mp_configure_sdk(): void
{
    static $configured = false;
    if ($configured) {
        return;
    }

    MercadoPagoConfig::setAccessToken(MP_ACCESS_TOKEN);
    $configured = true;
}

if (!function_exists('checkout_env')) {
    function checkout_env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value !== false && $value !== null) {
            $trimmed = trim((string) $value);
            return $trimmed !== '' ? $trimmed : $default;
        }

        if (isset($_SERVER[$key])) {
            $trimmed = trim((string) $_SERVER[$key]);
            return $trimmed !== '' ? $trimmed : $default;
        }

        return $default;
    }
}

/**
 * Obtiene la URL base publicada para Mercado Pago.
 */
function mp_base_url(): string
{
    $base = MP_BASE_URL;
    if ($base !== '' && $base !== null) {
        return rtrim($base, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    $host = trim((string)$host);
    if ($host === '') {
        $host = 'localhost';
    }

    return $scheme . '://' . $host;
}

function mp_url_success(): string
{
    return MP_URL_SUCCESS ?: (mp_base_url() . '/checkout/gracias.php');
}

function mp_url_failure(): string
{
    return MP_URL_FAILURE ?: mp_url_success();
}

function mp_url_pending(): string
{
    return MP_URL_PENDING ?: mp_url_success();
}

function mp_notification_url(): string
{
    return MP_URL_WEBHOOK ?: (mp_base_url() . '/checkout/mercadopago_webhook.php');
}

/**
 * Devuelve la configuración de cuotas para la preferencia.
 */
function mp_preference_payment_methods(): array
{
    $maxInstallments = defined('MP_MAX_INSTALLMENTS') ? (int) MP_MAX_INSTALLMENTS : 0;
    $defaultInstallments = defined('MP_DEFAULT_INSTALLMENTS') ? (int) MP_DEFAULT_INSTALLMENTS : 0;

    if ($maxInstallments > 0 && $defaultInstallments > $maxInstallments) {
        $defaultInstallments = $maxInstallments;
    }

    $methods = [];
    if ($maxInstallments > 0) {
        $methods['installments'] = $maxInstallments;
    }
    if ($defaultInstallments > 0) {
        $methods['default_installments'] = $defaultInstallments;
    }

    return $methods;
}

/**
 * Obtiene un identificador numérico del usuario autenticado en sesión.
 */
function mp_current_user_id(): int
{
    if (!isset($_SESSION)) {
        return 0;
    }
    $candidates = [
        $_SESSION['id_usuario'] ?? null,
        $_SESSION['usuario']['id_usuario'] ?? null,
        $_SESSION['usuario'] ?? null,
    ];
    foreach ($candidates as $value) {
        if ($value !== null && $value !== '' && is_numeric($value)) {
            $id = (int) $value;
            if ($id > 0) {
                return $id;
            }
        }
    }
    return 0;
}

/**
 * Registra un evento simple en el log de checkout.
 */
function mp_log(string $event, array $context = [], ?Throwable $error = null): void
{
    try {
        $dir = __DIR__ . '/../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $row = [
            'ts' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'event' => $event,
            'context' => $context,
        ];
        if ($error) {
            $row['error'] = [
                'type' => get_class($error),
                'message' => $error->getMessage(),
            ];
        }
        @file_put_contents($dir . '/mercadopago.log', json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    } catch (Throwable $logError) {
        // Ignorar errores de logueo.
    }
}

/**
 * Recupera el precio vigente del curso solicitado.
 */
function mp_fetch_course_price(PDO $con, int $courseId, string $tipoCurso = 'capacitacion'): array
{
    $tipoCurso = strtolower(trim($tipoCurso));
    if (!in_array($tipoCurso, ['capacitacion', 'certificacion'], true)) {
        $tipoCurso = 'capacitacion';
    }

    $tiposConsulta = [$tipoCurso];
    if ($tipoCurso !== 'capacitacion') {
        $tiposConsulta[] = 'capacitacion';
    }

    foreach ($tiposConsulta as $tipo) {
        $sql = <<<SQL
            SELECT precio, moneda
              FROM curso_precio_hist
             WHERE id_curso = :curso
               AND tipo_curso = :tipo
               AND vigente_desde <= NOW()
               AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
          ORDER BY vigente_desde DESC
             LIMIT 1
        SQL;
        $st = $con->prepare($sql);
        $st->execute([
            ':curso' => $courseId,
            ':tipo' => $tipo,
        ]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        if ($row && isset($row['precio'])) {
            $price = (float) $row['precio'];
            if ($price > 0) {
                return [
                    'amount' => $price,
                    'currency' => strtoupper((string) ($row['moneda'] ?? 'ARS')),
                    'source' => 'hist',
                ];
            }
        }
    }

    return [
        'amount' => 0.0,
        'currency' => 'ARS',
        'source' => 'none',
    ];
}

/**
 * Guarda la preferencia generada para correlacionar datos.
 */
function mp_store_preference(PDO $con, int $paymentId, string $preferenceId, string $initPoint, string $externalReference, array $payload): void
{
    $sql = <<<SQL
        INSERT INTO checkout_mercadopago (
            id_pago, preference_id, init_point, sandbox_init_point, external_reference, payload
        ) VALUES (
            :pago, :preference, :init_point, NULL, :external, :payload
        )
        ON DUPLICATE KEY UPDATE
            init_point = VALUES(init_point),
            payload = VALUES(payload),
            actualizado_en = CURRENT_TIMESTAMP
    SQL;

    $st = $con->prepare($sql);
    $st->execute([
        ':pago' => $paymentId,
        ':preference' => $preferenceId,
        ':init_point' => $initPoint,
        ':external' => $externalReference,
        ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

/**
 * Consulta un pago directamente en Mercado Pago.
 */
function mp_fetch_payment(string $paymentId): array
{
    $numericId = preg_replace('/\D+/', '', $paymentId);
    if ($numericId === '' || !is_numeric($numericId)) {
        throw new \InvalidArgumentException('El identificador de pago no es válido.');
    }

    mp_configure_sdk();
    $client = new PaymentClient();
    $payment = $client->get((int) $numericId);

    return json_decode(json_encode($payment), true) ?: [];
}

if (!function_exists('checkout_format_currency')) {
    function checkout_format_currency(float $amount, string $currency): string
    {
        $currency = strtoupper(trim($currency));
        $symbol = '$';
        if ($currency === 'USD') {
            $symbol = 'US$';
        } elseif ($currency === 'EUR') {
            $symbol = '€';
        }

        return sprintf('%s %s', $symbol, number_format($amount, 2, ',', '.'));
    }
}

/**
 * Traduce el detalle de estado de Mercado Pago a un texto amigable.
 */
function mp_status_detail_message(string $statusDetail): string
{
    $map = [
        'accredited' => 'El pago se acreditó correctamente.',
        'pending_contingency' => 'Estamos procesando el pago. Podría demorar unos minutos.',
        'pending_review_manual' => 'Mercado Pago está revisando la información del pago.',
        'cc_rejected_bad_filled_card_number' => 'Revisá el número de la tarjeta.',
        'cc_rejected_bad_filled_date' => 'Revisá la fecha de vencimiento de la tarjeta.',
        'cc_rejected_bad_filled_other' => 'Revisá los datos de tu tarjeta.',
        'cc_rejected_bad_filled_security_code' => 'Revisá el código de seguridad.',
        'cc_rejected_blacklist' => 'No pudimos procesar el pago.',
        'cc_rejected_call_for_authorize' => 'Necesitamos la autorización del banco para continuar.',
        'cc_rejected_card_disabled' => 'La tarjeta está inactiva. Comunicate con el banco.',
        'cc_rejected_card_error' => 'Ocurrió un error procesando la tarjeta.',
        'cc_rejected_insufficient_amount' => 'La tarjeta no tiene fondos suficientes.',
        'cc_rejected_invalid_installments' => 'El banco no admite la cantidad de cuotas seleccionada.',
        'cc_rejected_other_reason' => 'No pudimos procesar el pago. Intentá nuevamente o con otro medio.',
    ];

    return $map[$statusDetail] ?? 'No recibimos información adicional del emisor.';
}
