<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} elseif (is_dir(__DIR__ . '/PHPMailer')) {
    require_once __DIR__ . '/PHPMailer/src/Exception.php';
    require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/src/SMTP.php';
    // TODO: Instalar PHPMailer via Composer y eliminar este fallback manual.
} else {
    http_response_code(500);
    die('No se encontro PHPMailer. Instala la dependencia.');
}

require_once __DIR__ . '/config.php';

function createConfiguredMailer(): PHPMailer
{
    $smtp = getSmtpConfig();

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $smtp['username'];
    $mail->Password = $smtp['password'];
    $mail->SMTPSecure = $smtp['encryption'];
    $mail->Port = $smtp['port'];
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($smtp['from_email'], $smtp['from_name']);

    return $mail;
}

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

function enviarCorreoVerificacion(string $to, string $verificationLink): void
{
    $mail = createConfiguredMailer();
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
    <head>
        <meta charset="UTF-8">
        <title>Verificacion de cuenta</title>
    </head>
    <body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial, Helvetica, sans-serif;color:#1f2933;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f6fa;padding:24px 0;">
            <tr>
                <td align="center">
                    <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.1);">
                        <tr>
                            <td style="padding:36px 32px 12px 32px;text-align:center;background:#0d6efd;">
                                {$logoSecondaryBlock}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:32px 32px 16px 32px;">
                                <h1 style="margin:0;font-size:24px;color:#0f172a;text-align:center;">Confirma tu cuenta</h1>
                                <p style="margin:16px 0;font-size:16px;line-height:1.6;text-align:center;">
                                    ¡Hola! Gracias por registrarte en el Instituto de Formacion de Operadores.<br>
                                    Para activar tu cuenta haz clic en el siguiente boton.
                                </p>
                                <div style="text-align:center;margin:24px 0;">
                                    <a href="{$verificationLink}" style="display:inline-block;padding:14px 28px;background:#0d6efd;color:#ffffff;font-weight:bold;text-decoration:none;border-radius:30px;">Activar cuenta</a>
                                </div>
                                <p style="margin:0;font-size:14px;line-height:1.6;color:#475569;text-align:center;">
                                    Si el boton no funciona, copia y pega este enlace en tu navegador:<br>
                                    <span style="word-break:break-all;color:#2563eb;">{$verificationLink}</span>
                                </p>
                                <p style="margin:24px 0 0 0;font-size:13px;line-height:1.6;color:#64748b;text-align:center;">
                                    Este enlace caduca en 24 horas. Si no solicitaste esta cuenta puedes ignorar este mensaje.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="background:#f1f5f9;padding:20px;text-align:center;color:#94a3b8;font-size:12px;">
                                Instituto de Formacion de Operadores<br>
                                Todos los derechos reservados.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    HTML;

    $mail->AltBody = "Activa tu cuenta visitando: {$verificationLink}";
    $mail->send();
}


function enviarCorreoRecuperacion(string $to, string $nombre, string $resetLink): void
{
    $mail = createConfiguredMailer();
    $mail->addAddress($to, $nombre);
    $mail->isHTML(true);
    $mail->Subject = 'Restablece tu contrasena - Instituto de Operadores';

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
    <head>
        <meta charset="UTF-8">
        <title>Recupera tu contrasena</title>
    </head>
    <body style="margin:0;padding:0;background:#f5f6fa;font-family:Arial, Helvetica, sans-serif;color:#1f2933;">
        <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f5f6fa;padding:24px 0;">
            <tr>
                <td align="center">
                    <table width="560" cellpadding="0" cellspacing="0" role="presentation" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.1);">
                        <tr>
                            <td style="padding:36px 32px 12px 32px;text-align:center;background:#0d6efd;">
                                {$logoSecondaryBlock}
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:32px 32px 16px 32px;">
                                <h1 style="margin:0;font-size:24px;color:#0f172a;text-align:center;">Restablece tu contrasena</h1>
                                <p style="margin:16px 0;font-size:16px;line-height:1.6;text-align:center;">
                                    {$nombreSeguro}, recibimos una solicitud para restablecer tu contrasena.
                                </p>
                                <div style="text-align:center;margin:24px 0;">
                                    <a href="{$resetLink}" style="display:inline-block;padding:14px 28px;background:#0d6efd;color:#ffffff;font-weight:bold;text-decoration:none;border-radius:30px;">Crear nueva contrasena</a>
                                </div>
                                <p style="margin:0;font-size:14px;line-height:1.6;color:#475569;text-align:center;">
                                    Si el boton no funciona, copia y pega este enlace en tu navegador:<br>
                                    <span style="word-break:break-all;color:#2563eb;">{$resetLink}</span>
                                </p>
                                <p style="margin:24px 0 0 0;font-size:13px;line-height:1.6;color:#64748b;text-align:center;">
                                    Este enlace caduca en 1 hora. Si no solicitaste el cambio, puedes ignorar este mensaje.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="background:#f1f5f9;padding:20px;text-align:center;color:#94a3b8;font-size:12px;">
                                Instituto de Formacion de Operadores<br>
                                Todos los derechos reservados.
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>
    HTML;

    $mail->AltBody = "Utiliza el siguiente enlace para restablecer tu contrasena: {$resetLink}";
    $mail->send();
}

function enviarCorreoContacto(string $nombre, string $email, string $telefono, string $mensaje): void
{
    $mail = createConfiguredMailer();
    $mail->addAddress(SMTP_FROM_EMAIL, 'Contacto');
    $mail->addReplyTo($email, $nombre);
    $mail->isHTML(true);
    $mail->Subject = 'Nuevo mensaje de contacto';
    $mail->Body = sprintf(
        'Nombre: %s<br>Email: %s<br>Telefono: %s<br>Mensaje: %s',
        htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'),
        nl2br(htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'))
    );
    $mail->AltBody = sprintf(
        "Nombre: %s\nEmail: %s\nTelefono: %s\nMensaje: %s",
        $nombre,
        $email,
        $telefono,
        $mensaje
    );
    $mail->send();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'], $_POST['email'], $_POST['telefono'], $_POST['mensaje'])) {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));
    $mensaje = trim((string)($_POST['mensaje'] ?? ''));

    if ($nombre === '' || $email === '' || $mensaje === '') {
        http_response_code(400);
        echo 'Faltan datos requeridos.';
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo 'El correo proporcionado no es valido.';
        exit;
    }

    try {
        enviarCorreoContacto($nombre, $email, $telefono, $mensaje);
        echo "<script>window.location.href = '/confirmacion.php';</script>";
    } catch (Exception $exception) {
        http_response_code(500);
        echo 'El mensaje no pudo ser enviado.';
    }
}