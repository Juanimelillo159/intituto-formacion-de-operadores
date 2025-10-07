<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

require_once __DIR__ . '/mercadopago_common.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

if (!function_exists('checkout_mail_config')) {
    function checkout_mail_config(): array
    {
        return [
            'host' => checkout_env('SMTP_HOST') ?? 'smtp.hostinger.com',
            'port' => (int) (checkout_env('SMTP_PORT') ?? '465'),
            'username' => checkout_env('SMTP_USERNAME') ?? checkout_env('SMTP_USER') ?? 'pruebas@institutodeoperadores.com',
            'password' => checkout_env('SMTP_PASSWORD') ?? 'Ju4ni159@',
            'secure' => checkout_env('SMTP_SECURE') ?? 'ssl',
            'from_email' => checkout_env('SMTP_FROM_EMAIL') ?? 'pruebas@institutodeoperadores.com',
            'from_name' => checkout_env('SMTP_FROM_NAME') ?? 'Instituto de Operadores',
            'admin_email' => checkout_env('ADMIN_EMAIL') ?? 'administracion@institutodeoperadores.com',
            'admin_name' => checkout_env('ADMIN_NAME') ?? 'Administración Instituto',
        ];
    }
}

if (!function_exists('checkout_create_mailer')) {
    function checkout_create_mailer(): PHPMailer
    {
        $config = checkout_mail_config();
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = $config['host'];
        $mail->Username = $config['username'];
        $mail->Password = $config['password'];
        $mail->SMTPSecure = $config['secure'];
        $mail->Port = $config['port'];
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->setFrom($config['from_email'], $config['from_name']);
        return $mail;
    }
}

if (!function_exists('checkout_send_purchase_emails')) {
    /**
     * Envía los correos de confirmación al alumno y al administrador.
     */
    function checkout_send_purchase_emails(array $order, string $source, array &$errors = []): bool
    {
        $config = checkout_mail_config();
        $sentAll = true;
        $orderNumber = str_pad((string) ($order['orden'] ?? ''), 6, '0', STR_PAD_LEFT);
        $courseName = (string) ($order['curso'] ?? 'Curso seleccionado');
        $studentName = trim(($order['nombre'] ?? '') . ' ' . ($order['apellido'] ?? ''));
        $paymentSummary = checkout_format_currency((float) ($order['monto'] ?? 0), (string) ($order['moneda'] ?? 'ARS'));
        $paymentType = (string) ($order['payment_type'] ?? 'Mercado Pago');
        $paymentId = (string) ($order['payment_id'] ?? '');
        $statusDetail = (string) ($order['status_detail'] ?? '');
        $preferenceId = (string) ($order['preference_id'] ?? '');
        $fecha = (new DateTimeImmutable('now'))->format('d/m/Y H:i');

        $studentBody = <<<HTML
        <p>Hola {$studentName},</p>
        <p>¡Gracias por tu compra! Registramos el pago de tu inscripción al curso <strong>{$courseName}</strong>.</p>
        <p><strong>Resumen de tu orden</strong></p>
        <ul>
            <li>Número de orden: <strong>#{$orderNumber}</strong></li>
            <li>Monto abonado: <strong>{$paymentSummary}</strong></li>
            <li>Método de pago: <strong>{$paymentType}</strong></li>
            <li>ID de pago: <strong>{$paymentId}</strong></li>
            <li>Fecha: {$fecha}</li>
        </ul>
        <p>Nuestro equipo se comunicará con vos para coordinar los próximos pasos del curso.</p>
        <p>¡Gracias por confiar en el Instituto de Formación de Operadores!</p>
        HTML;

        try {
            $studentMail = checkout_create_mailer();
            $studentMail->addAddress((string) $order['email'], $studentName ?: $order['email']);
            $studentMail->Subject = 'Confirmación de pago - ' . $courseName;
            $studentMail->Body = $studentBody;
            $studentMail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $studentBody));
            $studentMail->send();
        } catch (MailException $studentException) {
            $sentAll = false;
            $errors[] = 'No se pudo enviar el correo al alumno: ' . $studentException->getMessage();
            mp_log('mp_mail_error', ['target' => 'alumno', 'source' => $source], $studentException);
        }

        $adminBody = <<<HTML
        <p>Hola {$config['admin_name']},</p>
        <p>Se registró un nuevo pago aprobado a través de Mercado Pago.</p>
        <p><strong>Detalles del pago</strong></p>
        <ul>
            <li>Curso: <strong>{$courseName}</strong></li>
            <li>Orden: <strong>#{$orderNumber}</strong></li>
            <li>Alumno: <strong>{$studentName}</strong></li>
            <li>Correo: <a href="mailto:{$order['email']}">{$order['email']}</a></li>
            <li>Teléfono: {$order['telefono']}</li>
            <li>Monto: <strong>{$paymentSummary}</strong></li>
            <li>Método de pago: <strong>{$paymentType}</strong></li>
            <li>ID de pago: <strong>{$paymentId}</strong></li>
            <li>Preference ID: {$preferenceId}</li>
            <li>Detalle de estado: {$statusDetail}</li>
            <li>Fecha y hora: {$fecha}</li>
        </ul>
        <p>Fuente de notificación: {$source}.</p>
        HTML;

        try {
            $adminMail = checkout_create_mailer();
            $adminMail->addAddress($config['admin_email'], $config['admin_name']);
            $adminMail->Subject = 'Nueva inscripción abonada - ' . $courseName;
            $adminMail->Body = $adminBody;
            $adminMail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $adminBody));
            $adminMail->send();
        } catch (MailException $adminException) {
            $sentAll = false;
            $errors[] = 'No se pudo notificar al administrador: ' . $adminException->getMessage();
            mp_log('mp_mail_error', ['target' => 'admin', 'source' => $source], $adminException);
        }

        return $sentAll;
    }
}
