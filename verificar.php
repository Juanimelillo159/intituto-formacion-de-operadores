<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: text/html; charset=UTF-8');

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

function renderMessage(string $title, string $message, bool $ok): void
{
    $color = $ok ? '#16a34a' : '#dc2626';
    $svg = $ok
        ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="44" height="44"><circle cx="12" cy="12" r="12" fill="#16a34a"/><path d="M17.5 9l-6.22 6.24L6.5 10.5" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'
        : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="44" height="44"><circle cx="12" cy="12" r="12" fill="#dc2626"/><path d="M15 9l-6 6M9 9l6 6" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>{$title}</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg,#0d6efd 0%,#1d4ed8 100%); margin:0; padding:0; min-height:100vh; display:flex; align-items:center; justify-content:center; color:#0f172a; }
            .card { background:#ffffff; border-radius:18px; padding:44px 36px; width:100%; max-width:480px; box-shadow:0 25px 45px rgba(14, 23, 61, 0.25); text-align:center; }
            .icon { width:88px; height:88px; margin:0 auto 18px; display:flex; align-items:center; justify-content:center; }
            h1 { margin:0 0 16px; font-size:28px; color:#0f172a; }
            p { margin:0 0 20px; font-size:16px; line-height:1.6; color:#475569; }
            a.button { display:inline-block; margin-top:12px; padding:14px 26px; background:#0d6efd; color:#ffffff; text-decoration:none; border-radius:999px; font-weight:600; box-shadow:0 12px 25px rgba(13,110,253,0.28); transition:transform .2s ease, box-shadow .2s ease; }
            a.button:hover { transform:translateY(-2px); box-shadow:0 16px 30px rgba(13,110,253,0.34); }
            .footer { margin-top:30px; font-size:13px; color:#94a3b8; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon">{$svg}</div>
            <h1>{$title}</h1>
            <p>{$message}</p>
            <a class="button" href="/login.php">Ir al inicio de sesion</a>
            <div class="footer">Instituto de Formacion de Operadores</div>
        </div>
    </body>
    </html>
    HTML;
}

if ($token === '') {
    renderMessage('Token invalido', 'El enlace de verificacion no es valido. Solicita uno nuevo o revisa tu correo mas reciente.', false);
    exit;
}

$pdo = getPdo();

try {
    $stmt = $pdo->prepare('SELECT id_usuario FROM usuarios WHERE token_verificacion = ? AND token_expiracion > NOW() AND verificado = 0 LIMIT 1');
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        renderMessage('Token invalido', 'El enlace es invalido o ya expiro. Solicita uno nuevo desde la pagina de inicio de sesion.', false);
        exit;
    }

    $update = $pdo->prepare('UPDATE usuarios SET verificado = 1, token_verificacion = NULL, token_expiracion = NULL WHERE id_usuario = ?');
    $update->execute([(int)$user['id_usuario']]);

    renderMessage('Cuenta verificada', 'Tu cuenta fue activada correctamente. Ya puedes iniciar sesion y acceder a todos los recursos.', true);
} catch (Throwable $exception) {
    renderMessage('Error inesperado', 'No pudimos completar la verificacion. Intenta mas tarde o contacta al soporte.', false);
}