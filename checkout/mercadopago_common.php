<?php
declare(strict_types=1);

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;

if (!function_exists('checkout_log_event')) {
    /**
     * Registra eventos del checkout en un archivo de log compartido.
     */
    function checkout_log_event(string $accion, array $data = [], ?Throwable $ex = null): void
    {
        try {
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $file = $logDir . '/cursos.log';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $now = (new DateTime('now'))->format('Y-m-d H:i:s');
            $row = [
                'ts' => $now,
                'user' => 'checkout',
                'ip' => $ip,
                'accion' => $accion,
                'data' => $data,
            ];
            if ($ex) {
                $row['error'] = [
                    'type' => get_class($ex),
                    'message' => $ex->getMessage(),
                    'code' => (string) $ex->getCode(),
                ];
            }
            @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
        } catch (Throwable $loggingError) {
            // Silenciar errores de logging
        }
    }
}

if (!function_exists('checkout_env')) {
    /**
     * Obtiene un valor del entorno o devuelve el valor por defecto provisto.
     */
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

if (!function_exists('checkout_mp_config')) {
    /**
     * Carga las credenciales configuradas para Mercado Pago.
     *
     * @return array{public_key: ?string, access_token: ?string, integrator_id: ?string}
     */
    function checkout_mp_config(): array
    {
        static $config = null;
        if ($config !== null) {
            return $config;
        }

        $defaults = [
            'public_key' => null,
            'access_token' => null,
            'integrator_id' => null,
        ];

        $config = $defaults;
        $file = __DIR__ . '/mp_config.php';

        if (is_file($file) && is_readable($file)) {
            try {
                $loaded = require $file;
                if (is_array($loaded)) {
                    foreach ($defaults as $key => $_) {
                        if (!array_key_exists($key, $loaded)) {
                            continue;
                        }
                        $value = $loaded[$key];
                        if (is_string($value)) {
                            $value = trim($value);
                            $config[$key] = $value !== '' ? $value : null;
                        } elseif ($value !== null) {
                            $config[$key] = $value;
                        }
                    }
                } else {
                    checkout_log_event('checkout_mp_config_error', ['reason' => 'invalid_config']);
                }
            } catch (Throwable $exception) {
                checkout_log_event('checkout_mp_config_error', ['reason' => $exception->getMessage()], $exception);
            }
        }

        return $config;
    }
}

if (!function_exists('checkout_get_mp_public_key')) {
    /**
     * Obtiene la public key configurada para Mercado Pago.
     */
    function checkout_get_mp_public_key(): ?string
    {
        $config = checkout_mp_config();
        $publicKey = $config['public_key'] ?? null;
        if (!$publicKey) {
            $publicKey = checkout_env('MP_PUBLIC_KEY') ?? checkout_env('MERCADOPAGO_PUBLIC_KEY');
        }
        if ($publicKey === null) {
            return null;
        }
        $trimmed = trim((string) $publicKey);
        return $trimmed !== '' ? $trimmed : null;
    }
}

if (!function_exists('checkout_get_base_url')) {
    /**
     * Determina la URL base de la aplicación para construir callbacks.
     */
    function checkout_get_base_url(): string
    {
        $configured = checkout_env('APP_BASE_URL') ?? checkout_env('BASE_URL');
        if ($configured) {
            $configured = trim($configured);
            if ($configured !== '') {
                if (!preg_match('#^https?://#i', $configured)) {
                    $configured = 'https://' . ltrim($configured, '/');
                }
                return rtrim($configured, '/');
            }
        }

        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if ($host === '') {
            $host = 'localhost';
        }
        if (!preg_match('#^https?://#i', $host)) {
            $host = ltrim($host, '/');
            $host = $scheme . '://' . $host;
        }
        return rtrim($host, '/');
    }
}

if (!function_exists('checkout_configure_mp')) {
    /**
     * Configura el SDK de Mercado Pago con los datos necesarios.
     */
    function checkout_configure_mp(): void
    {
        $config = checkout_mp_config();
        $token = $config['access_token'] ?? null;
        if (!$token) {
            $token = checkout_env('MP_ACCESS_TOKEN') ?? checkout_env('MERCADOPAGO_ACCESS_TOKEN');
        }
        if (!$token) {
            throw new RuntimeException('No se configuró el token de acceso de Mercado Pago. Definí MP_ACCESS_TOKEN.');
        }
        MercadoPagoConfig::setAccessToken($token);
        $integratorId = $config['integrator_id'] ?? null;
        if (!$integratorId) {
            $integratorId = checkout_env('MP_INTEGRATOR_ID') ?? checkout_env('MERCADOPAGO_INTEGRATOR_ID');
        }
        if ($integratorId) {
            MercadoPagoConfig::setIntegratorId($integratorId);
        }
    }
}

if (!function_exists('checkout_fetch_payment_from_mp')) {
    /**
     * Obtiene información del pago desde la API de Mercado Pago.
     */
    function checkout_fetch_payment_from_mp(string $paymentId): ?array
    {
        if ($paymentId === '') {
            return null;
        }
        checkout_configure_mp();
        $client = new PaymentClient();
        try {
            $payment = $client->get($paymentId);
            return json_decode(json_encode($payment, JSON_UNESCAPED_UNICODE), true);
        } catch (MPApiException $apiException) {
            checkout_log_event('checkout_mp_api_error', ['payment_id' => $paymentId], $apiException);
            throw $apiException;
        }
    }
}

if (!function_exists('checkout_map_mp_status_to_estado')) {
    /**
     * Traduce el estado de MP al estado interno del pago.
     */
    function checkout_map_mp_status_to_estado(string $status): string
    {
        $status = strtolower($status);
        return match ($status) {
            'approved', 'accredited', 'captured' => 'pagado',
            'authorized' => 'autorizado',
            'pending', 'in_process', 'in_mediation', 'in_review' => 'pendiente',
            'rejected', 'refused' => 'rechazado',
            'cancelled', 'cancelled_by_collector', 'cancelled_by_user' => 'cancelado',
            'refunded', 'partially_refunded' => 'reembolsado',
            'charged_back' => 'reversado',
            'expired' => 'vencido',
            default => 'pendiente',
        };
    }
}

if (!function_exists('checkout_decode_payload')) {
    function checkout_decode_payload(?string $payload): array
    {
        if ($payload === null || $payload === '') {
            return [];
        }
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('checkout_encode_payload')) {
    function checkout_encode_payload(array $payload): string
    {
        try {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            return '{}';
        }
    }
}

if (!function_exists('checkout_format_currency')) {
    function checkout_format_currency(float $amount, string $currency): string
    {
        $currency = strtoupper($currency);
        return sprintf('%s %s', $currency, number_format($amount, 2, ',', '.'));
    }
}
