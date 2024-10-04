<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];
    $mensaje = $_POST['mensaje'];

    $mail = new PHPMailer(true);

    try {
        // Configuración del servidor SMTP
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';  // Cambia a tu servidor SMTP
        $mail->SMTPAuth = true;
        $mail->Username = 'pruebas@institutodeoperadores.com';  // Cambia al correo de tu dominio
        $mail->Password = 'Ju4ni159@';  // Cambia a la contraseña de tu correo
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        // Remitente y destinatarios
        $mail->setFrom('pruebas@institutodeoperadores.com', 'Consulta');  // Remitente válido
        $mail->addReplyTo($email, $nombre);  // El correo del usuario para respuesta
        $mail->addAddress('pruebas@institutodeoperadores.com', 'Tu Nombre');  // El correo receptor de las consultas

        // Contenido del correo
        $mail->isHTML(true);
        $mail->Subject = 'Nuevo mensaje de prueba';
        $mail->Body    = "Nombre: $nombre<br>Email: $email<br>Teléfono: $telefono<br>Mensaje: $mensaje";

        $mail->send();
        echo "<script type='text/javascript'>
            alert('El mensaje ha sido enviado exitosamente.');
            window.location.href = '/index.php';
              </script>";
    } catch (Exception $e) {
        echo "El mensaje no pudo ser enviado. Error: {$mail->ErrorInfo}";
    }
}
