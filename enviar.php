<?php
declare(strict_types=1);

/**
 * enviar.php — PHPMailer sin Composer (instalación manual)
 * Asegurate de que existan estos archivos:
 *   ./PHPMailer/src/PHPMailer.php
 *   ./PHPMailer/src/SMTP.php
 *   ./PHPMailer/src/Exception.php
 */

// ==== INCLUDES MANUALES ====
$manualBase = __DIR__ . '/PHPMailer/src'; // <-- AJUSTÁ ESTA RUTA SI ES NECESARIO
if (
    !is_readable($manualBase . '/PHPMailer.php') ||
    !is_readable($manualBase . '/SMTP.php') ||
    !is_readable($manualBase . '/Exception.php')
) {
    http_response_code(500);
    die('No se encontraron los archivos de PHPMailer en ' . $manualBase);
}

require_once $manualBase . '/Exception.php';
require_once $manualBase . '/PHPMailer.php';
require_once $manualBase . '/SMTP.php';

// Si vas a usar OAuth o POP3, también podés incluir:
// require_once $manualBase . '/OAuth.php';
// require_once $manualBase . '/POP3.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/config.php'; // Debe exponer getSmtpConfig()

/**
 * Crea y configura PHPMailer con tus credenciales SMTP
 */
function createConfiguredMailer(): PHPMailer
{
    $smtp = getSmtpConfig();

    $mail = new PHPMailer(true);

    // Sin logs ni salida de debug
    $mail->SMTPDebug = 0;

    // SMTP
    $mail->isSMTP();
    $mail->Host       = (string)$smtp['host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = (string)$smtp['username'];
    $mail->Password   = (string)$smtp['password'];
    $mail->SMTPSecure = (string)$smtp['encryption']; // 'tls' o 'ssl'
    $mail->Port       = (int)$smtp['port'];
    $mail->CharSet    = 'UTF-8';

    // Útil en hostings con certificados auto-firmados (podés quitar si no lo necesitás)
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];

    // Remitente: MISMO dominio que el SMTP
    $mail->setFrom((string)$smtp['from_email'], (string)$smtp['from_name']);
    // Return-Path coherente
    $mail->Sender = (string)$smtp['from_email'];

    return $mail;
}

/**
 * Embebe una imagen si existe; si no, devuelve null (no interrumpe el envío).
 */
function embedImage(PHPMailer $mail, string $relativePath, string $cid, string $name = ''): ?string
{
    $absolutePath = __DIR__ . '/' . ltrim($relativePath, '/');
    if (!is_readable($absolutePath)) {
        return null;
    }
    try {
        $mail->addEmbeddedImage($absolutePath, $cid, $name === '' ? basename($absolutePath) : $name);
        return 'cid:' . $cid;
    } catch (Exception $exception) {
        return null;
    }
}

/**
 * Envía correo de verificación.
 * @return array{0:bool,1:?string} [ok, error]
 */
function enviarCorreoVerificacion(string $to, string $verificationLink): array
{
    $mail = createConfiguredMailer();

    try {
        $mail->clearAllRecipients();
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'Confirma tu cuenta - Instituto de Operadores';

        $logoSecondary = embedImage($mail, 'logos/LOGO PNG_Mesa de trabajo 1.png', 'logo_secondary');
        if (!$logoSecondary) {
            $logoSecondary = embedImage($mail, 'logos/LOGO PNG_Mesa de trabajo 1.svg', 'logo_secondary_fallback');
        }

        $logoSecondaryBlock = $logoSecondary
            ? '<div style="margin:0 auto 8px; width:260px; height:120px; background-image:url(' . htmlspecialchars($logoSecondary, ENT_QUOTES, 'UTF-8') . '); background-repeat:no-repeat; background-position:center; background-size:contain;"></div>'
            : '<div style="color:#ffffff;font-weight:600;font-size:20px;">Instituto de Operadores</div>';

        $mail->Body = <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"><title>Verificación de cuenta</title></head>
        <body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial, Helvetica, sans-serif;color:#1f2933;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f6fa;padding:24px 0;">
                <tr><td align="center">
                    <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.1);">
                        <tr><td style="padding:36px 32px 12px 32px;text-align:center;background:#0d6efd;">{$logoSecondaryBlock}</td></tr>
                        <tr><td style="padding:32px 32px 16px 32px;">
                            <h1 style="margin:0;font-size:24px;color:#0f172a;text-align:center;">Confirma tu cuenta</h1>
                            <p style="margin:16px 0;font-size:16px;line-height:1.6;text-align:center;">
                                ¡Hola! Gracias por registrarte en el Instituto de Formación de Operadores.<br>
                                Para activar tu cuenta hacé clic en el siguiente botón.
                            </p>
                            <div style="text-align:center;margin:24px 0;">
                                <a href="{$verificationLink}" style="display:inline-block;padding:14px 28px;background:#0d6efd;color:#ffffff;font-weight:bold;text-decoration:none;border-radius:30px;">Activar cuenta</a>
                            </div>
                            <p style="margin:0;font-size:14px;line-height:1.6;color:#475569;text-align:center;">
                                Si el botón no funciona, copiá y pegá este enlace en tu navegador:<br>
                                <span style="word-break:break-all;color:#2563eb;">{$verificationLink}</span>
                            </p>
                            <p style="margin:24px 0 0 0;font-size:13px;line-height:1.6;color:#64748b;text-align:center;">
                                Este enlace caduca en 24 horas. Si no solicitaste esta cuenta podés ignorar este mensaje.
                            </p>
                        </td></tr>
                        <tr><td style="background:#f1f5f9;padding:20px;text-align:center;color:#94a3b8;font-size:12px;">
                            Instituto de Formación de Operadores<br>Todos los derechos reservados.
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>
        HTML;

        $mail->AltBody = "Activá tu cuenta visitando: {$verificationLink}";

        $mail->send();
        return [true, null];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        return [false, $err];
    }
}

/**
 * Envía correo de recuperación.
 * @return array{0:bool,1:?string} [ok, error]
 */
function enviarCorreoRecuperacion(string $to, string $nombre, string $resetLink): array
{
    $mail = createConfiguredMailer();

    try {
        $mail->clearAllRecipients();
        $mail->addAddress($to, $nombre);
        $mail->isHTML(true);
        $mail->Subject = 'Restablecé tu contraseña - Instituto de Operadores';

        $logoSecondary = embedImage($mail, 'logos/LOGO PNG_Mesa de trabajo 1.png', 'logo_reset_secondary');
        if (!$logoSecondary) {
            $logoSecondary = embedImage($mail, 'logos/LOGO PNG_Mesa de trabajo 1.svg', 'logo_reset_secondary_fallback');
        }

        $logoSecondaryBlock = $logoSecondary
            ? '<div style="margin:0 auto 8px; width:260px; height:120px; background-image:url(' . htmlspecialchars($logoSecondary, ENT_QUOTES, 'UTF-8') . '); background-repeat:no-repeat; background-position:center; background-size:contain;"></div>'
            : '<div style="color:#ffffff;font-weight:600;font-size:20px;">Instituto de Operadores</div>';

        $nombreSeguro = $nombre === '' ? 'Hola' : htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');

        $mail->Body = <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head><meta charset="UTF-8"><title>Recuperar contraseña</title></head>
        <body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial, Helvetica, sans-serif;color:#1f2933;">
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f6fa;padding:24px 0;">
                <tr><td align="center">
                    <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.1);">
                        <tr><td style="padding:36px 32px 12px 32px;text-align:center;background:#0d6efd;">{$logoSecondaryBlock}</td></tr>
                        <tr><td style="padding:32px 32px 16px 32px;">
                            <h1 style="margin:0;font-size:24px;color:#0f172a;text-align:center;">Restablecé tu contraseña</h1>
                            <p style="margin:16px 0;font-size:16px;line-height:1.6;text-align:center;">
                                {$nombreSeguro}, recibimos una solicitud para restablecer tu contraseña.
                            </p>
                            <div style="text-align:center;margin:24px 0;">
                                <a href="{$resetLink}" style="display:inline-block;padding:14px 28px;background:#0d6efd;color:#ffffff;font-weight:bold;text-decoration:none;border-radius:30px;">Crear nueva contraseña</a>
                            </div>
                            <p style="margin:0;font-size:14px;line-height:1.6;color:#475569;text-align:center;">
                                Si el botón no funciona, copiá y pegá este enlace en tu navegador:<br>
                                <span style="word-break:break-all;color:#2563eb;">{$resetLink}</span>
                            </p>
                            <p style="margin:24px 0 0 0;font-size:13px;line-height:1.6;color:#64748b;text-align:center;">
                                Este enlace caduca en 1 hora. Si no solicitaste el cambio, podés ignorar este mensaje.
                            </p>
                        </td></tr>
                        <tr><td style="background:#f1f5f9;padding:20px;text-align:center;color:#94a3b8;font-size:12px;">
                            Instituto de Formación de Operadores<br>Todos los derechos reservados.
                        </td></tr>
                    </table>
                </td></tr>
            </table>
        </body>
        </html>
        HTML;

        $mail->AltBody = "Usá este enlace para restablecer tu contraseña: {$resetLink}";

        $mail->send();
        return [true, null];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        return [false, $err];
    }
}

/**
 * Envía correo del formulario de contacto.
 * @return array{0:bool,1:?string} [ok, error]
 */
function enviarCorreoContacto(string $nombre, string $email, string $telefono, string $mensaje): array
{
    $mail = createConfiguredMailer();

    try {
        $mail->clearAllRecipients();
        $mail->addAddress(SMTP_FROM_EMAIL, 'Contacto');
        $mail->addReplyTo($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = 'Nuevo mensaje de contacto';

        $mail->Body = sprintf(
            'Nombre: %s<br>Email: %s<br>Teléfono: %s<br>Mensaje: %s',
            htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'),
            nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'))
        );
        $mail->AltBody = sprintf(
            "Nombre: %s\nEmail: %s\nTeléfono: %s\nMensaje: %s",
            $nombre,
            $email,
            $telefono,
            $mensaje
        );

        $mail->send();
        return [true, null];
    } catch (Exception $e) {
        $err = $mail->ErrorInfo ?: $e->getMessage();
        return [false, $err];
    }
}

// --- Handler opcional para probar directamente este archivo con POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'], $_POST['email'], $_POST['telefono'], $_POST['mensaje'])) {
    $nombre   = trim((string)($_POST['nombre'] ?? ''));
    $email    = trim((string)($_POST['email'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));
    $mensaje  = trim((string)($_POST['mensaje'] ?? ''));

    $contactWantsJson = static function (): bool {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if ($accept !== '' && stripos($accept, 'application/json') !== false) {
            return true;
        }

        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if ($requestedWith !== '' && strcasecmp($requestedWith, 'xmlhttprequest') === 0) {
            return true;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        return $contentType !== '' && stripos($contentType, 'application/json') !== false;
    };

    $contactRespond = static function (bool $ok, string $message, string $type = 'info', int $status = 200, ?string $redirect = null) use ($contactWantsJson): void {
        if ($contactWantsJson()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['contacto_mensaje'] = $message;
        $_SESSION['contacto_tipo'] = $type;

        $target = $redirect ?? 'index.php#contacto';
        header('Location: ' . $target);
        exit;
    };

    if ($nombre === '' || $email === '' || $mensaje === '') {
        $contactRespond(false, 'Faltan datos requeridos.', 'danger', 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $contactRespond(false, 'El correo proporcionado no es valido.', 'danger', 400);
    }

    [$ok, $err] = enviarCorreoContacto($nombre, $email, $telefono, $mensaje);
    if ($ok) {
        $contactRespond(true, 'Tu consulta fue enviada correctamente. Te contactaremos a la brevedad.', 'success', 200, 'confirmacion.php');
    }

    error_log('[contacto] Falla SMTP: ' . ($err ?? 'desconocido'));
    $contactRespond(false, 'El mensaje no pudo ser enviado. Intentalo mas tarde.', 'danger', 500);
}

