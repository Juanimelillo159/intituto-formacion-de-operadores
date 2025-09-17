<?php
// procesarsbd.php (sin sesiones)
declare(strict_types=1);

use MercadoPago\Item;
use MercadoPago\Preference;
use MercadoPago\SDK;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ===================== LOG ===================== */
function log_cursos(string $accion, array $data = [], ?Throwable $ex = null): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $file = $logDir . '/cursos.log';
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $now = (new DateTime('now'))->format('Y-m-d H:i:s');
    $row = ['ts' => $now, 'user' => 'anon', 'ip' => $ip, 'accion' => $accion, 'data' => $data];
    if ($ex) {
        $row['error'] = [
            'type'    => get_class($ex),
            'message' => $ex->getMessage(),
            'code'    => (string)$ex->getCode(),
        ];
    }
    @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

/* ==================== HELPERS =================== */
// Normaliza "120.000,50", "120000.50", "$ 120000" -> "120000.50" (string numérica con punto)
function normalizar_precio(?string $raw): ?string
{
    if ($raw === null) return null;
    $s = trim($raw);
    if ($s === '') return null;
    $s = preg_replace('/[^\d\.,]/', '', $s); // deja sólo dígitos, punto, coma
    if ($s === '') return null;
    $comma = strrpos($s, ',');
    $dot = strrpos($s, '.');
    $decSep = ($comma !== false && $dot !== false)
        ? (($comma > $dot) ? ',' : '.')
        : (($comma !== false) ? ',' : '.');
    if ($decSep === ',') {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } else {
        $s = str_replace(',', '', $s);
    }
    if (!is_numeric($s)) return null;
    return $s;
}

// Convierte input "YYYY-MM-DDTHH:MM" a "Y-m-d H:i:s"
function parse_dt_local(string $v): string
{
    $v = trim($v);
    if ($v === '') throw new InvalidArgumentException('Fecha inválida');
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) $v .= ':00';
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $v);
    if (!$dt) throw new InvalidArgumentException('Fecha inválida');
    return $dt->format('Y-m-d H:i:s');
}

function esc_html($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatear_monto($monto, string $moneda): string
{
    $currency = strtoupper($moneda !== '' ? $moneda : 'ARS');
    return $currency . ' ' . number_format((float)$monto, 2, ',', '.');
}

function ensure_checkout_mp_table(PDO $con): void
{
    static $mpTableEnsured = false;
    if ($mpTableEnsured) {
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
    $mpTableEnsured = true;
}

function mapear_estado_mercadopago(string $status): string
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

function descripcion_metodo_pago(string $metodo): string
{
    $metodo = strtolower(trim($metodo));
    switch ($metodo) {
        case 'mercado_pago':
            return 'Mercado Pago';
        case 'transferencia':
            return 'Transferencia bancaria';
        default:
            $metodo = str_replace('_', ' ', $metodo);
            return $metodo !== '' ? ucfirst($metodo) : 'No especificado';
    }
}

function crear_preferencia_mercadopago(array $config, array $curso, array $inscripcion, array $pago, int $idInscripcion, int $idPago): array
{
    $mpConfig = $config['mercadopago'] ?? [];
    $accessToken = trim((string)($mpConfig['access_token'] ?? ''));
    $baseUrl = rtrim((string)($config['app']['base_url'] ?? ''), '/');
    if ($accessToken === '') {
        throw new RuntimeException('Falta configurar el access token de Mercado Pago en config/config.php.');
    }
    if ($baseUrl === '') {
        throw new RuntimeException('Falta configurar la URL base de la aplicación en config/config.php.');
    }

    SDK::setAccessToken($accessToken);

    $preference = new Preference();

    $item = new Item();
    $item->title = (string)($curso['nombre_curso'] ?? 'Curso');
    $item->quantity = 1;
    $item->unit_price = (float)($pago['monto'] ?? 0);
    $item->currency_id = strtoupper((string)($pago['moneda'] ?? 'ARS'));
    $preference->items = [$item];

    $preference->payer = [
        'name' => (string)($inscripcion['nombre'] ?? ''),
        'surname' => (string)($inscripcion['apellido'] ?? ''),
        'email' => (string)($inscripcion['email'] ?? ''),
    ];

    $externalReference = sprintf('checkout:%d:%d', $idInscripcion, $idPago);
    $preference->external_reference = $externalReference;
    $preference->notification_url = $baseUrl . '/checkout/mp_notificacion.php';
    $preference->back_urls = [
        'success' => $baseUrl . '/checkout/gracias.php',
        'pending' => $baseUrl . '/checkout/retorno.php?status=pending',
        'failure' => $baseUrl . '/checkout/retorno.php?status=failure',
    ];
    $preference->auto_return = 'approved';
    $preference->statement_descriptor = 'IF Operadores';
    $preference->metadata = [
        'id_inscripcion' => $idInscripcion,
        'id_pago' => $idPago,
        'curso' => (string)($curso['nombre_curso'] ?? ''),
        'email' => (string)($inscripcion['email'] ?? ''),
    ];

    $preference->save();

    return [
        'preference_id' => (string)$preference->id,
        'init_point' => (string)$preference->init_point,
        'sandbox_init_point' => (string)($preference->sandbox_init_point ?? ''),
        'external_reference' => $externalReference,
        'notification_url' => (string)$preference->notification_url,
    ];
}

function registrar_preferencia_mercadopago(PDO $con, int $idPago, array $datos): void
{
    ensure_checkout_mp_table($con);

    $del = $con->prepare('DELETE FROM checkout_mercadopago WHERE id_pago = :pago');
    $del->execute([':pago' => $idPago]);

    $sandbox = trim((string)($datos['sandbox_init_point'] ?? ''));
    if ($sandbox === '') {
        $sandbox = null;
    }

    $ins = $con->prepare('INSERT INTO checkout_mercadopago (id_pago, preference_id, init_point, sandbox_init_point, external_reference, status) VALUES (:pago, :pref, :init, :sandbox, :ref, :status)');
    $ins->execute([
        ':pago' => $idPago,
        ':pref' => (string)($datos['preference_id'] ?? ''),
        ':init' => (string)($datos['init_point'] ?? ''),
        ':sandbox' => $sandbox,
        ':ref' => $datos['external_reference'] ?? null,
        ':status' => 'pendiente',
    ]);
}

function normalizar_emails($value): array
{
    if (is_array($value)) {
        $lista = $value;
    } elseif (is_string($value)) {
        $lista = preg_split('/[;,]+/', $value) ?: [];
    } else {
        return [];
    }
    $emails = [];
    foreach ($lista as $email) {
        $email = trim((string)$email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $email;
        }
    }
    return array_values(array_unique($emails));
}

function enviar_resumen_compra(array $config, array $curso, array $inscripcion, array $pago, ?array $mpDatos = null): void
{
    $mailConfig = $config['mail'] ?? [];
    $host = trim((string)($mailConfig['host'] ?? ''));
    $username = trim((string)($mailConfig['username'] ?? ''));
    $password = (string)($mailConfig['password'] ?? '');
    if ($host === '' || $username === '' || $password === '') {
        log_cursos('correo_config_incompleta', ['host' => $host !== '', 'username' => $username !== '']);
        return;
    }

    $port = (int)($mailConfig['port'] ?? 587);
    $encryption = trim((string)($mailConfig['encryption'] ?? ''));
    $fromEmail = trim((string)($mailConfig['from_email'] ?? $username));
    $fromName = trim((string)($mailConfig['from_name'] ?? 'Instituto de Formación de Operadores'));

    $ordenId = isset($inscripcion['id']) ? (int)$inscripcion['id'] : 0;
    $ordenFormat = $ordenId > 0 ? str_pad((string)$ordenId, 6, '0', STR_PAD_LEFT) : 's/d';
    $metodo = descripcion_metodo_pago($pago['metodo'] ?? '');
    $monto = formatear_monto($pago['monto'] ?? 0, (string)($pago['moneda'] ?? 'ARS'));
    $estado = ucfirst($pago['estado'] ?? 'Pendiente');
    $observaciones = trim((string)($pago['observaciones'] ?? ''));
    $mpLink = isset($mpDatos['init_point']) && $mpDatos['init_point'] !== '' ? (string)$mpDatos['init_point'] : null;

    $nombre = trim((string)($inscripcion['nombre'] ?? ''));
    $apellido = trim((string)($inscripcion['apellido'] ?? ''));
    $nombreCompleto = trim($nombre . ' ' . $apellido);
    if ($nombreCompleto === '') {
        $nombreCompleto = trim((string)($inscripcion['email'] ?? ''));
    }
    $cursoNombre = (string)($curso['nombre_curso'] ?? 'Curso');

    $usuarioHtml = '<p>Hola ' . esc_html($nombreCompleto !== '' ? $nombreCompleto : 'alumno') . ',</p>';
    $usuarioHtml .= '<p>Gracias por inscribirte en <strong>' . esc_html($cursoNombre) . '</strong>. Este es el detalle de tu orden:</p>';
    $usuarioHtml .= '<ul style="padding-left:16px;">';
    $usuarioHtml .= '<li><strong>Número de orden:</strong> #' . esc_html($ordenFormat) . '</li>';
    $usuarioHtml .= '<li><strong>Método de pago:</strong> ' . esc_html($metodo) . '</li>';
    $usuarioHtml .= '<li><strong>Monto:</strong> ' . esc_html($monto) . '</li>';
    $usuarioHtml .= '<li><strong>Estado:</strong> ' . esc_html($estado) . '</li>';
    if ($observaciones !== '') {
        $usuarioHtml .= '<li><strong>Observaciones:</strong> ' . esc_html($observaciones) . '</li>';
    }
    $usuarioHtml .= '</ul>';
    if ($mpLink) {
        $usuarioHtml .= '<p>Para completar el pago ingresá al siguiente enlace seguro de Mercado Pago:</p>';
        $usuarioHtml .= '<p><a href="' . esc_html($mpLink) . '" style="color:#009ee3; font-weight:600;">Pagar ahora con Mercado Pago</a></p>';
    }
    $usuarioHtml .= '<p>En breve nuestro equipo se comunicará para finalizar tu inscripción.</p>';
    $usuarioHtml .= '<p>Saludos cordiales,<br>Instituto de Formación de Operadores</p>';

    $usuarioTexto = 'Hola ' . ($nombreCompleto !== '' ? $nombreCompleto : 'alumno') . ',' . PHP_EOL;
    $usuarioTexto .= 'Gracias por inscribirte en ' . $cursoNombre . '. Detalle de tu orden:' . PHP_EOL;
    $usuarioTexto .= '- Número de orden: #' . $ordenFormat . PHP_EOL;
    $usuarioTexto .= '- Método de pago: ' . $metodo . PHP_EOL;
    $usuarioTexto .= '- Monto: ' . $monto . PHP_EOL;
    $usuarioTexto .= '- Estado: ' . $estado . PHP_EOL;
    if ($observaciones !== '') {
        $usuarioTexto .= '- Observaciones: ' . $observaciones . PHP_EOL;
    }
    if ($mpLink) {
        $usuarioTexto .= 'Completá el pago ingresando a: ' . $mpLink . PHP_EOL;
    }
    $usuarioTexto .= PHP_EOL . 'Nos contactaremos a la brevedad para coordinar los próximos pasos.' . PHP_EOL;
    $usuarioTexto .= 'Instituto de Formación de Operadores';

    $usuarioEmail = (string)($inscripcion['email'] ?? '');
    $destinatarios = [];
    if ($usuarioEmail !== '' && filter_var($usuarioEmail, FILTER_VALIDATE_EMAIL)) {
        $destinatarios[] = [
            'email' => $usuarioEmail,
            'nombre' => $nombreCompleto !== '' ? $nombreCompleto : $usuarioEmail,
            'subject' => 'Resumen de tu inscripción #' . $ordenFormat,
            'html' => $usuarioHtml,
            'text' => $usuarioTexto,
        ];
    }

    $adminEmails = normalizar_emails($mailConfig['admin_email'] ?? []);
    if (!empty($adminEmails)) {
        $adminHtml = '<p>Hola equipo,</p>';
        $adminHtml .= '<p>Se registró una nueva compra a través del sitio web.</p>';
        $adminHtml .= '<h4 style="margin-top:18px;">Datos del curso</h4>';
        $adminHtml .= '<ul style="padding-left:16px;">';
        $adminHtml .= '<li><strong>Curso:</strong> ' . esc_html($cursoNombre) . '</li>';
        $adminHtml .= '<li><strong>Número de orden:</strong> #' . esc_html($ordenFormat) . '</li>';
        $adminHtml .= '<li><strong>Método de pago:</strong> ' . esc_html($metodo) . '</li>';
        $adminHtml .= '<li><strong>Monto:</strong> ' . esc_html($monto) . '</li>';
        $adminHtml .= '<li><strong>Estado:</strong> ' . esc_html($estado) . '</li>';
        $adminHtml .= '</ul>';
        $adminHtml .= '<h4 style="margin-top:18px;">Datos del alumno</h4>';
        $adminHtml .= '<ul style="padding-left:16px;">';
        $adminHtml .= '<li><strong>Nombre:</strong> ' . esc_html(trim($nombre . ' ' . $apellido)) . '</li>';
        $adminHtml .= '<li><strong>Email:</strong> ' . esc_html($usuarioEmail) . '</li>';
        $adminHtml .= '<li><strong>Teléfono:</strong> ' . esc_html($inscripcion['telefono'] ?? '') . '</li>';
        $adminHtml .= '<li><strong>DNI:</strong> ' . esc_html($inscripcion['dni'] ?? '') . '</li>';
        $adminHtml .= '<li><strong>Dirección:</strong> ' . esc_html($inscripcion['direccion'] ?? '') . '</li>';
        $adminHtml .= '<li><strong>Ciudad:</strong> ' . esc_html($inscripcion['ciudad'] ?? '') . '</li>';
        $adminHtml .= '<li><strong>Provincia:</strong> ' . esc_html($inscripcion['provincia'] ?? '') . '</li>';
        $adminHtml .= '<li><strong>País:</strong> ' . esc_html($inscripcion['pais'] ?? '') . '</li>';
        $adminHtml .= '</ul>';
        if ($observaciones !== '') {
            $adminHtml .= '<p><strong>Observaciones del alumno:</strong> ' . esc_html($observaciones) . '</p>';
        }
        if ($mpDatos) {
            $adminHtml .= '<p><strong>Mercado Pago:</strong> Preferencia ' . esc_html($mpDatos['preference_id'] ?? '') . '.</p>';
            if ($mpLink) {
                $adminHtml .= '<p><a href="' . esc_html($mpLink) . '" style="color:#009ee3;">Ver enlace de pago</a></p>';
            }
        }
        $adminHtml .= '<p>Este mensaje se envió automáticamente desde el checkout.</p>';

        $adminTexto = 'Nueva compra registrada.' . PHP_EOL;
        $adminTexto .= 'Curso: ' . $cursoNombre . PHP_EOL;
        $adminTexto .= 'Orden: #' . $ordenFormat . PHP_EOL;
        $adminTexto .= 'Método: ' . $metodo . PHP_EOL;
        $adminTexto .= 'Monto: ' . $monto . PHP_EOL;
        $adminTexto .= 'Estado: ' . $estado . PHP_EOL;
        $adminTexto .= 'Alumno: ' . trim($nombre . ' ' . $apellido) . PHP_EOL;
        $adminTexto .= 'Email: ' . $usuarioEmail . PHP_EOL;
        $adminTexto .= 'Teléfono: ' . ($inscripcion['telefono'] ?? '') . PHP_EOL;
        $adminTexto .= 'DNI: ' . ($inscripcion['dni'] ?? '') . PHP_EOL;
        $adminTexto .= 'Dirección: ' . ($inscripcion['direccion'] ?? '') . PHP_EOL;
        $adminTexto .= 'Ciudad: ' . ($inscripcion['ciudad'] ?? '') . PHP_EOL;
        $adminTexto .= 'Provincia: ' . ($inscripcion['provincia'] ?? '') . PHP_EOL;
        $adminTexto .= 'País: ' . ($inscripcion['pais'] ?? '') . PHP_EOL;
        if ($observaciones !== '') {
            $adminTexto .= 'Observaciones: ' . $observaciones . PHP_EOL;
        }
        if ($mpDatos) {
            $adminTexto .= 'Preferencia MP: ' . ($mpDatos['preference_id'] ?? '') . PHP_EOL;
            if ($mpLink) {
                $adminTexto .= 'Enlace de pago: ' . $mpLink . PHP_EOL;
            }
        }

        foreach ($adminEmails as $adminEmail) {
            $destinatarios[] = [
                'email' => $adminEmail,
                'nombre' => 'Equipo Administrativo',
                'subject' => 'Nueva inscripción #' . $ordenFormat . ' - ' . $cursoNombre,
                'html' => $adminHtml,
                'text' => $adminTexto,
            ];
        }
    }

    foreach ($destinatarios as $dest) {
        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $host;
            $mailer->SMTPAuth = true;
            $mailer->Username = $username;
            $mailer->Password = $password;
            $mailer->Port = $port;
            if ($encryption !== '') {
                $mailer->SMTPSecure = $encryption;
            }
            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom($fromEmail !== '' ? $fromEmail : $username, $fromName !== '' ? $fromName : 'Instituto de Formación de Operadores');
            $mailer->addAddress($dest['email'], $dest['nombre']);
            $mailer->isHTML(true);
            $mailer->Subject = $dest['subject'];
            $mailer->Body = $dest['html'];
            $mailer->AltBody = $dest['text'];
            $mailer->send();
        } catch (PHPMailerException $e) {
            log_cursos('correo_envio_error', ['destinatario' => $dest['email'], 'subject' => $dest['subject']], $e);
        } catch (Throwable $e) {
            log_cursos('correo_envio_error', ['destinatario' => $dest['email'], 'subject' => $dest['subject']], $e);
        }
    }
}

/* =================== MAIN FLOW ================== */
$checkoutUploadAbs = null;
$checkoutUploadTmp = null;
$checkoutUploadMoved = false;
$checkoutIsCrearOrden = false;
$checkoutCursoId = 0;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido');
    }
    if (!($con instanceof PDO)) {
        throw new RuntimeException('Conexión PDO inválida.');
    }

    $configFile = __DIR__ . '/../config/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('No se encontró el archivo de configuración (config/config.php).');
    }
    $configData = require $configFile;
    if (!is_array($configData)) {
        throw new RuntimeException('El archivo de configuración debe retornar un array.');
    }
    $config = $configData;

    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Fallback para acciones cuando el submit se hace por JS
    $accion = $_POST['__accion'] ?? '';

    $checkoutIsCrearOrden = isset($_POST['crear_orden']) || ($accion === 'crear_orden');

    if ($checkoutIsCrearOrden) {
        $checkoutCursoId = (int)($_POST['id_curso'] ?? 0);
        $nombreInscrito  = trim((string)($_POST['nombre_insc'] ?? ''));
        $apellidoInscrito = trim((string)($_POST['apellido_insc'] ?? ''));
        $emailInscrito   = trim((string)($_POST['email_insc'] ?? ''));
        $telefonoInscrito = trim((string)($_POST['tel_insc'] ?? ''));
        $dniInscrito     = trim((string)($_POST['dni_insc'] ?? ''));
        $direccionInsc   = trim((string)($_POST['dir_insc'] ?? ''));
        $ciudadInsc      = trim((string)($_POST['ciu_insc'] ?? ''));
        $provinciaInsc   = trim((string)($_POST['prov_insc'] ?? ''));
        $paisInsc        = trim((string)($_POST['pais_insc'] ?? ''));
        if ($paisInsc === '') {
            $paisInsc = 'Argentina';
        }
        $aceptaTyC = isset($_POST['acepta_tyc']) ? 1 : 0;
        $metodoPago = (string)($_POST['metodo_pago'] ?? '');
        if ($metodoPago === 'mp') {
            $metodoPago = 'mercado_pago';
        }
        $obsPagoRaw = trim((string)($_POST['obs_pago'] ?? ''));
        if (function_exists('mb_substr')) {
            $observacionesPago = mb_substr($obsPagoRaw, 0, 250, 'UTF-8');
        } else {
            $observacionesPago = substr($obsPagoRaw, 0, 250);
        }
        $precioInput = isset($_POST['precio_checkout']) ? (float)$_POST['precio_checkout'] : 0.0;

        if ($checkoutCursoId <= 0) {
            throw new InvalidArgumentException('Curso inválido.');
        }
        if ($nombreInscrito === '' || $apellidoInscrito === '' || $emailInscrito === '' || $telefonoInscrito === '') {
            throw new InvalidArgumentException('Completá los datos obligatorios del inscripto.');
        }
        if (!filter_var($emailInscrito, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electrónico no es válido.');
        }
        if ($aceptaTyC !== 1) {
            throw new InvalidArgumentException('Debés aceptar los Términos y Condiciones.');
        }
        $metodosPermitidos = ['transferencia', 'mercado_pago'];
        if (!in_array($metodoPago, $metodosPermitidos, true)) {
            throw new InvalidArgumentException('Método de pago inválido.');
        }

        $cursoStmt = $con->prepare("SELECT id_curso, nombre_curso FROM cursos WHERE id_curso = :id LIMIT 1");
        $cursoStmt->execute([':id' => $checkoutCursoId]);
        $cursoRow = $cursoStmt->fetch();
        if (!$cursoRow) {
            throw new RuntimeException('El curso seleccionado no existe.');
        }

        $precioStmt = $con->prepare("
          SELECT precio, moneda
            FROM curso_precio_hist
           WHERE id_curso = :c
             AND vigente_desde <= NOW()
             AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
        ORDER BY vigente_desde DESC
           LIMIT 1
        ");
        $precioStmt->execute([':c' => $checkoutCursoId]);
        $precioRow = $precioStmt->fetch();
        $precioFinal = $precioRow ? (float)$precioRow['precio'] : (float)$precioInput;
        $monedaPrecio = ($precioRow && !empty($precioRow['moneda'])) ? (string)$precioRow['moneda'] : 'ARS';
        if ($precioFinal < 0) {
            $precioFinal = 0.0;
        }

        $comprobanteNombreOriginal = null;
        $comprobanteMime = null;
        $comprobanteTamano = null;
        $checkoutUploadRel = null;

        if ($metodoPago === 'transferencia') {
            $archivo = $_FILES['comprobante'] ?? null;
            if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Debés adjuntar el comprobante de la transferencia.');
            }
            $checkoutUploadTmp = (string)$archivo['tmp_name'];
            $comprobanteTamano = (int)$archivo['size'];
            $maxBytes = 5 * 1024 * 1024;
            if ($comprobanteTamano > $maxBytes) {
                throw new InvalidArgumentException('El comprobante supera el tamaño máximo permitido (5 MB).');
            }

            $mimeDetectado = '';
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mimeDetectado = (string)$finfo->file($checkoutUploadTmp);
                }
            }
            if ($mimeDetectado === '' && function_exists('mime_content_type')) {
                $mimeDetectado = (string)@mime_content_type($checkoutUploadTmp);
            }
            if ($mimeDetectado === '' && isset($archivo['type'])) {
                $mimeDetectado = (string)$archivo['type'];
            }

            $mimePermitidos = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf',
            ];
            $extension = $mimePermitidos[$mimeDetectado] ?? null;
            if ($extension === null) {
                $extensionTmp = strtolower((string)pathinfo((string)$archivo['name'], PATHINFO_EXTENSION));
                if ($extensionTmp === 'jpeg') {
                    $extensionTmp = 'jpg';
                }
                if (in_array($extensionTmp, ['jpg', 'png', 'pdf'], true)) {
                    $extension = $extensionTmp;
                }
            }
            if ($extension === null) {
                throw new InvalidArgumentException('Formato de comprobante inválido. Permitido: JPG, PNG o PDF.');
            }

            $uploadsBase = __DIR__ . '/../uploads';
            $uploadsTarget = $uploadsBase . '/comprobantes';
            if (!is_dir($uploadsTarget)) {
                if (!mkdir($uploadsTarget, 0755, true) && !is_dir($uploadsTarget)) {
                    throw new RuntimeException('No se pudo crear la carpeta para comprobantes.');
                }
            }

            $nombreArchivo = 'comp_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $checkoutUploadAbs = $uploadsTarget . '/' . $nombreArchivo;
            $checkoutUploadRel = 'uploads/comprobantes/' . $nombreArchivo;
            $comprobanteNombreOriginal = (string)$archivo['name'];
            $comprobanteMime = $mimeDetectado !== '' ? $mimeDetectado : ($extension === 'pdf' ? 'application/pdf' : 'image/' . $extension);
        }

        $con->beginTransaction();

        $inscripcionStmt = $con->prepare("
          INSERT INTO checkout_inscripciones (
            id_curso, nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais, acepta_tyc, precio_total, moneda
          ) VALUES (
            :curso, :nombre, :apellido, :email, :telefono, :dni, :direccion, :ciudad, :provincia, :pais, :acepta, :precio, :moneda
          )
        ");
        $inscripcionStmt->execute([
            ':curso' => $checkoutCursoId,
            ':nombre' => $nombreInscrito,
            ':apellido' => $apellidoInscrito,
            ':email' => $emailInscrito,
            ':telefono' => $telefonoInscrito,
            ':dni' => $dniInscrito !== '' ? $dniInscrito : null,
            ':direccion' => $direccionInsc !== '' ? $direccionInsc : null,
            ':ciudad' => $ciudadInsc !== '' ? $ciudadInsc : null,
            ':provincia' => $provinciaInsc !== '' ? $provinciaInsc : null,
            ':pais' => $paisInsc,
            ':acepta' => $aceptaTyC,
            ':precio' => $precioFinal,
            ':moneda' => $monedaPrecio,
        ]);

        $idInscripcion = (int)$con->lastInsertId();

        $pagoStmt = $con->prepare("
          INSERT INTO checkout_pagos (
            id_inscripcion, metodo, estado, monto, moneda, comprobante_path, comprobante_nombre, comprobante_mime, comprobante_tamano, observaciones
          ) VALUES (
            :inscripcion, :metodo, 'pendiente', :monto, :moneda, :ruta, :nombre, :mime, :tamano, :obs
          )
        ");
        $pagoStmt->execute([
            ':inscripcion' => $idInscripcion,
            ':metodo' => $metodoPago,
            ':monto' => $precioFinal,
            ':moneda' => $monedaPrecio,
            ':ruta' => $checkoutUploadRel,
            ':nombre' => $comprobanteNombreOriginal,
            ':mime' => $comprobanteMime,
            ':tamano' => $comprobanteTamano,
            ':obs' => $observacionesPago !== '' ? $observacionesPago : null,
        ]);

        $idPago = (int)$con->lastInsertId();

        $inscripcionResumen = [
            'id' => $idInscripcion,
            'nombre' => $nombreInscrito,
            'apellido' => $apellidoInscrito,
            'email' => $emailInscrito,
            'telefono' => $telefonoInscrito,
            'dni' => $dniInscrito !== '' ? $dniInscrito : null,
            'direccion' => $direccionInsc !== '' ? $direccionInsc : null,
            'ciudad' => $ciudadInsc !== '' ? $ciudadInsc : null,
            'provincia' => $provinciaInsc !== '' ? $provinciaInsc : null,
            'pais' => $paisInsc,
        ];

        $pagoResumen = [
            'id_pago' => $idPago,
            'metodo' => $metodoPago,
            'estado' => 'pendiente',
            'monto' => $precioFinal,
            'moneda' => $monedaPrecio,
            'observaciones' => $observacionesPago !== '' ? $observacionesPago : null,
            'comprobante' => $checkoutUploadRel,
        ];

        $mpPreferenceData = null;

        if ($metodoPago === 'transferencia' && $checkoutUploadAbs !== null) {
            if (!move_uploaded_file($checkoutUploadTmp, $checkoutUploadAbs)) {
                throw new RuntimeException('No se pudo guardar el comprobante de la transferencia.');
            }
            $checkoutUploadMoved = true;
        }

        if ($metodoPago === 'mercado_pago') {
            $mpPreferenceData = crear_preferencia_mercadopago($config, $cursoRow, $inscripcionResumen, $pagoResumen, $idInscripcion, $idPago);
            registrar_preferencia_mercadopago($con, $idPago, $mpPreferenceData);
        }

        $con->commit();

        enviar_resumen_compra($config, $cursoRow, $inscripcionResumen, $pagoResumen, $mpPreferenceData);

        $_SESSION['checkout_success'] = [
            'orden' => $idInscripcion,
            'metodo' => $metodoPago,
        ];

        $logData = [
            'orden' => $idInscripcion,
            'id_curso' => $checkoutCursoId,
            'metodo' => $metodoPago,
            'monto' => $precioFinal,
            'moneda' => $monedaPrecio,
        ];
        if ($mpPreferenceData) {
            $logData['mp_preference'] = $mpPreferenceData['preference_id'] ?? null;
        }
        log_cursos('checkout_crear_orden_ok', $logData);

        if ($metodoPago === 'mercado_pago' && $mpPreferenceData && !empty($mpPreferenceData['init_point'])) {
            header('Location: ' . $mpPreferenceData['init_point']);
        } else {
            header('Location: ../checkout/checkout.php?id_curso=' . $checkoutCursoId);
        }
        exit;
    }

    $isAgregarPrecio = isset($_POST['agregar_precio']) || ($accion === 'agregar_precio');
    $isEditarCurso   = isset($_POST['editar_curso'])   || ($accion === 'editar_curso');
    $isAgregarCurso  = isset($_POST['agregar_curso'])  || ($accion === 'agregar_curso');

    /* =============== AGREGAR PRECIO (HISTÓRICO) =============== */
    if ($isAgregarPrecio) {
        $id_curso   = (int)($_POST['id_curso'] ?? 0);
        $precioRaw  = $_POST['precio'] ?? null;
        $desdeRaw   = $_POST['desde'] ?? null;
        $comentario = trim($_POST['comentario'] ?? '') ?: 'Alta manual en curso';

        if ($id_curso <= 0) throw new InvalidArgumentException('Curso inválido.');
        $precio = normalizar_precio($precioRaw);
        if ($precio === null) throw new InvalidArgumentException('Precio inválido.');
        $desde  = parse_dt_local((string)$desdeRaw);

        $con->beginTransaction();

        // (0) no duplicar exacto mismo vigente_desde
        $st = $con->prepare("SELECT 1 FROM curso_precio_hist WHERE id_curso = :c AND vigente_desde = :d LIMIT 1");
        $st->execute([':c' => $id_curso, ':d' => $desde]);
        if ($st->fetchColumn()) {
            throw new RuntimeException('Ya existe un precio con esa fecha de vigencia.');
        }

        // (1) próximo precio (si lo hay) -> tope del nuevo
        $stNext = $con->prepare("
          SELECT id, vigente_desde
            FROM curso_precio_hist
           WHERE id_curso = :c
             AND vigente_desde > :d
        ORDER BY vigente_desde ASC
           LIMIT 1
        ");
        $stNext->execute([':c' => $id_curso, ':d' => $desde]);
        $next = $stNext->fetch();
        $nuevoHasta = null;
        if ($next) {
            $dt = new DateTime($next['vigente_desde']);
            $dt->modify('-1 second');
            $nuevoHasta = $dt->format('Y-m-d H:i:s');
        }

        // (2) cerrar precio(s) que cubran el instante "desde"
        // FIX HY093: no repetir el mismo placeholder varias veces
        $up = $con->prepare("
          UPDATE curso_precio_hist
             SET vigente_hasta = DATE_SUB(:d0, INTERVAL 1 SECOND)
           WHERE id_curso = :c
             AND vigente_desde < :d1
             AND (vigente_hasta IS NULL OR vigente_hasta >= :d2)
        ");
        $up->execute([':d0' => $desde, ':c' => $id_curso, ':d1' => $desde, ':d2' => $desde]);

        // (3) insertar nuevo
        $ins = $con->prepare("
          INSERT INTO curso_precio_hist (id_curso, precio, moneda, vigente_desde, vigente_hasta, comentario)
          VALUES (:c, :p, 'ARS', :d, :h, :com)
        ");
        $ins->execute([
            ':c' => $id_curso,
            ':p' => $precio,
            ':d' => $desde,
            ':h' => $nuevoHasta,
            ':com' => $comentario
        ]);

        $con->commit();
        log_cursos('agregar_precio_ok', ['id_curso' => $id_curso, 'precio' => $precio, 'desde' => $desde, 'hasta' => $nuevoHasta]);

        header('Location: curso.php?id_curso=' . $id_curso . '&tab=precios&saved=1');
        exit;
    }

    /* ======================== EDITAR CURSO ======================== */
    if ($isEditarCurso) {
        $id_curso     = (int)($_POST['id_curso'] ?? 0);
        $nombre       = trim($_POST['nombre'] ?? '');
        $descripcion  = trim($_POST['descripcion'] ?? '');
        $duracion     = trim($_POST['duracion'] ?? '');
        $objetivos    = trim($_POST['objetivos'] ?? '');
        $complejidad  = trim($_POST['complejidad'] ?? ''); // VARCHAR
        $programa     = trim($_POST['programa'] ?? '');
        $publico      = trim($_POST['publico'] ?? '');
        $cronograma   = trim($_POST['cronograma'] ?? '');
        $requisitos   = trim($_POST['requisitos'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $modalidades  = (isset($_POST['modalidades']) && is_array($_POST['modalidades'])) ? $_POST['modalidades'] : [];

        if ($id_curso <= 0 || $nombre === '' || $descripcion === '' || $duracion === '' || $objetivos === '' || $complejidad === '') {
            throw new InvalidArgumentException('Campos obligatorios faltantes para edición.');
        }

        $con->beginTransaction();

        $sql = $con->prepare("
            UPDATE cursos
               SET nombre_curso     = :nombre,
                   descripcion_curso = :descripcion,
                   duracion          = :duracion,
                   objetivos         = :objetivos,
                   complejidad       = :complejidad,
                   programa          = :programa,
                   publico           = :publico,
                   cronograma        = :cronograma,
                   requisitos        = :requisitos,
                   observaciones     = :observaciones
             WHERE id_curso         = :id
        ");
        $sql->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':duracion' => $duracion,
            ':objetivos' => $objetivos,
            ':complejidad' => $complejidad,
            ':programa' => $programa,
            ':publico' => $publico,
            ':cronograma' => $cronograma,
            ':requisitos' => $requisitos,
            ':observaciones' => $observaciones,
            ':id' => $id_curso,
        ]);

        // Reemplazar modalidades
        $del = $con->prepare("DELETE FROM curso_modalidad WHERE id_curso = :id");
        $del->execute([':id' => $id_curso]);
        if (!empty($modalidades)) {
            $ins = $con->prepare("INSERT INTO curso_modalidad (id_curso, id_modalidad) VALUES (:c, :m)");
            foreach ($modalidades as $m) {
                $ins->execute([':c' => $id_curso, ':m' => (int)$m]);
            }
        }

        $con->commit();
        log_cursos('editar_curso_ok', ['id_curso' => $id_curso, 'modalidades' => $modalidades]);

        header('Location: curso.php?id_curso=' . $id_curso . '&saved=1');
        exit;
    }

    /* ======================== AGREGAR CURSO ======================= */
    if ($isAgregarCurso) {
        $nombre        = trim($_POST['nombre'] ?? '');
        $descripcion   = trim($_POST['descripcion'] ?? '');
        $duracion      = trim($_POST['duracion'] ?? '');
        $complejidad   = trim($_POST['complejidad'] ?? ''); // VARCHAR
        $objetivos     = trim($_POST['objetivos'] ?? '');
        $programa      = trim($_POST['programa'] ?? '');
        $publico       = trim($_POST['publico'] ?? '');
        $cronograma    = trim($_POST['cronograma'] ?? '');
        $requisitos    = trim($_POST['requisitos'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $modalidades   = (isset($_POST['modalidades']) && is_array($_POST['modalidades'])) ? $_POST['modalidades'] : [];

        // Precio inicial opcional (si el form manda "precio")
        $precioInicialRaw = $_POST['precio'] ?? null;
        $precioInicial    = normalizar_precio($precioInicialRaw);

        if ($nombre === '' || $descripcion === '' || $duracion === '' || $objetivos === '' || $complejidad === '') {
            throw new InvalidArgumentException('Faltan campos obligatorios.');
        }

        $con->beginTransaction();

        $insCurso = $con->prepare("
            INSERT INTO cursos (
                nombre_curso, descripcion_curso, duracion, objetivos,
                cronograma, publico, programa, requisitos, observaciones,
                complejidad
            ) VALUES (
                :nombre, :descripcion, :duracion, :objetivos,
                :cronograma, :publico, :programa, :requisitos, :observaciones,
                :complejidad
            )
        ");
        $insCurso->execute([
            ':nombre'        => $nombre,
            ':descripcion'   => $descripcion,
            ':duracion'      => $duracion,
            ':objetivos'     => $objetivos,
            ':cronograma'    => $cronograma,
            ':publico'       => $publico,
            ':programa'      => $programa,
            ':requisitos'    => $requisitos,
            ':observaciones' => $observaciones,
            ':complejidad'   => $complejidad,
        ]);

        $id_curso = (int)$con->lastInsertId();

        if (!empty($modalidades)) {
            $insMod = $con->prepare("INSERT INTO curso_modalidad (id_curso, id_modalidad) VALUES (:c, :m)");
            foreach ($modalidades as $m) {
                $insMod->execute([':c' => $id_curso, ':m' => (int)$m]);
            }
        }

        // Precio inicial (si vino). Fecha = NOW()
        if ($precioInicial !== null) {
            $nuevoHasta = null;
            $stNext = $con->prepare("
              SELECT vigente_desde
                FROM curso_precio_hist
               WHERE id_curso = :c AND vigente_desde > NOW()
            ORDER BY vigente_desde ASC
               LIMIT 1
            ");
            $stNext->execute([':c' => $id_curso]);
            $next = $stNext->fetchColumn();
            if ($next) {
                $dt = new DateTime($next);
                $dt->modify('-1 second');
                $nuevoHasta = $dt->format('Y-m-d H:i:s');
            }

            $con->prepare("
              UPDATE curso_precio_hist
                 SET vigente_hasta = DATE_SUB(NOW(), INTERVAL 1 SECOND)
               WHERE id_curso = :c
                 AND vigente_desde <  NOW()
                 AND (vigente_hasta IS NULL OR vigente_hasta >= NOW())
            ")->execute([':c' => $id_curso]);

            $con->prepare("
              INSERT INTO curso_precio_hist (id_curso, precio, moneda, vigente_desde, vigente_hasta, comentario)
              VALUES (:c, :p, 'ARS', NOW(), :h, 'Alta inicial de curso')
            ")->execute([':c' => $id_curso, ':p' => $precioInicial, ':h' => $nuevoHasta]);
        }

        $con->commit();
        log_cursos('agregar_curso_ok', [
            'id_curso'    => $id_curso,
            'nombre'      => $nombre,
            'complejidad' => $complejidad,
            'modalidades' => $modalidades,
            'precio_inicial' => $precioInicial,
        ]);

        header('Location: cursos.php');
        exit;
    }

    /* ========================= BANNERS ========================= */
    if (isset($_POST['agregar_banner'])) {
        log_cursos('agregar_banner_inicio', ['post' => array_keys($_POST), 'files' => array_keys($_FILES)]);

        $nombre_banner = $_POST['nombre_banner'] ?? '';
        $imagen        = $_FILES['imagen_banner'] ?? null;

        if (!$imagen || $imagen['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir la imagen.');
        }

        $mime = mime_content_type($imagen['tmp_name']);
        $permitidos = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mime, $permitidos, true)) {
            throw new InvalidArgumentException('Tipo de imagen no permitido.');
        }

        $ext = pathinfo($imagen['name'], PATHINFO_EXTENSION);
        $nombre_imagen = uniqid('imagen_') . ".$ext";
        $destDir = __DIR__ . '/../imagenes/banners';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $dest = $destDir . '/' . $nombre_imagen;

        if (!move_uploaded_file($imagen['tmp_name'], $dest)) {
            throw new RuntimeException('No se pudo mover la imagen.');
        }

        $sql = $con->prepare("INSERT INTO banner (nombre_banner, imagen_banner) VALUES (:n, :img)");
        $sql->execute([':n' => $nombre_banner, ':img' => $nombre_imagen]);

        log_cursos('agregar_banner_ok', ['nombre_banner' => $nombre_banner, 'imagen' => $nombre_imagen]);
        header('Location: /p/admin/carrusel.php');
        exit;
    }

    if (isset($_POST['editar_banner'])) {
        log_cursos('editar_banner_inicio', ['post' => array_keys($_POST), 'files' => array_keys($_FILES)]);

        $id_banner     = (int)($_POST['id_banner'] ?? 0);
        $nombre_banner = $_POST['nombre_banner'] ?? '';
        $imagen        = $_FILES['imagen_banner'] ?? null;

        if ($id_banner <= 0) throw new InvalidArgumentException('id_banner inválido.');

        if ($imagen && $imagen['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($imagen['tmp_name']);
            $permitidos = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mime, $permitidos, true)) {
                throw new InvalidArgumentException('Tipo de imagen no permitido.');
            }
            $ext = pathinfo($imagen['name'], PATHINFO_EXTENSION);
            $nombre_imagen = uniqid('imagen_') . ".$ext";
            $destDir = __DIR__ . '/../imagenes/banners';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $dest = $destDir . '/' . $nombre_imagen;
            if (!move_uploaded_file($imagen['tmp_name'], $dest)) {
                throw new RuntimeException('No se pudo mover la imagen.');
            }

            // nombre anterior
            $st = $con->prepare("SELECT imagen_banner FROM banner WHERE id_banner = :id");
            $st->execute([':id' => $id_banner]);
            $anterior = $st->fetchColumn();

            $up = $con->prepare("UPDATE banner SET nombre_banner = :n, imagen_banner = :img WHERE id_banner = :id");
            $up->execute([':n' => $nombre_banner, ':img' => $nombre_imagen, ':id' => $id_banner]);

            if ($anterior && is_file($destDir . '/' . $anterior)) {
                @unlink($destDir . '/' . $anterior);
            }

            log_cursos('editar_banner_ok', ['id_banner' => $id_banner, 'imagen' => $nombre_imagen]);
            header('Location: /p/admin/carrusel.php');
            exit;
        } else {
            // solo nombre
            $up = $con->prepare("UPDATE banner SET nombre_banner = :n WHERE id_banner = :id");
            $up->execute([':n' => $nombre_banner, ':id' => $id_banner]);

            log_cursos('editar_banner_ok', ['id_banner' => $id_banner, 'solo_nombre' => true]);
            header('Location: /p/admin/carrusel.php');
            exit;
        }
    }

    // Si llegó acá, no coincidió ninguna acción
    throw new RuntimeException('Acción no reconocida.');
} catch (Throwable $e) {
    if (isset($con) && $con instanceof PDO && $con->inTransaction()) {
        $con->rollBack();
    }

    if ($checkoutIsCrearOrden) {
        if ($checkoutUploadMoved && $checkoutUploadAbs && is_file($checkoutUploadAbs)) {
            @unlink($checkoutUploadAbs);
        }

        log_cursos('checkout_crear_orden_error', [
            'id_curso' => $checkoutCursoId,
            'post_keys' => array_keys($_POST ?? []),
        ], $e);

        $_SESSION['checkout_error'] = $e->getMessage();
        $redirect = '../checkout/checkout.php';
        if ($checkoutCursoId > 0) {
            $redirect .= '?id_curso=' . $checkoutCursoId;
        }
        header('Location: ' . $redirect);
        exit;
    }

    log_cursos('error', ['post_keys' => array_keys($_POST ?? [])], $e);
    http_response_code(400);
    echo "Ocurrió un error al procesar la solicitud. Revisa el log para más detalles.";
    exit;
}
