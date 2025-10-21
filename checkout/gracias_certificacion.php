<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../sbd.php';
require_once __DIR__ . '/mercadopago_mailer.php';

$page_title = 'Solicitud de certificación enviada | Instituto de Formación';
$page_description = 'Confirmación del envío de la documentación para certificaciones.';
$base_path = '../';

$flashData = $_SESSION['certificacion_gracias'] ?? null;
if ($flashData !== null) {
    unset($_SESSION['certificacion_gracias']);
}

$certificacionId = isset($_GET['certificacion']) ? (int) $_GET['certificacion'] : 0;
$data = null;
$error = null;

if (is_array($flashData) && !empty($flashData)) {
    $data = $flashData;
}

if (!$data && $certificacionId > 0) {
    try {
        if (!isset($con) || !($con instanceof PDO)) {
            throw new RuntimeException('No se pudo conectar con la base de datos.');
        }
        $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $st = $con->prepare('
            SELECT cc.id_certificacion, cc.id_curso, cc.nombre, cc.apellido, cc.email, cc.telefono,
                   cc.precio_total, cc.moneda, cc.id_estado, c.nombre_curso
              FROM checkout_certificaciones cc
         LEFT JOIN cursos c ON c.id_curso = cc.id_curso
             WHERE cc.id_certificacion = :id
             LIMIT 1
        ');
        $st->execute([':id' => $certificacionId]);
        $row = $st->fetch();
        if (!$row) {
            throw new RuntimeException('No encontramos la solicitud de certificación.');
        }
        $data = [
            'id_certificacion' => (int) $row['id_certificacion'],
            'id_curso' => (int) $row['id_curso'],
            'curso_nombre' => (string) ($row['nombre_certificacion'] ?? $row['nombre_curso'] ?? ''),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'apellido' => (string) ($row['apellido'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'telefono' => (string) ($row['telefono'] ?? ''),
            'precio' => isset($row['precio_total']) ? (float) $row['precio_total'] : null,
            'moneda' => (string) ($row['moneda'] ?? 'ARS'),
            'estado' => isset($row['id_estado']) ? (int) $row['id_estado'] : null,
        ];
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

function cert_estado_label(?int $estado): string
{
    return match ($estado) {
        2 => 'Documentación aprobada',
        3 => 'Pago registrado',
        4 => 'Solicitud rechazada',
        default => 'En revisión',
    };
}

function cert_estado_details(?int $estado): array
{
    return match ($estado) {
        2 => [
            'badge' => 'approved',
            'tone' => 'approved',
            'icon' => 'fas fa-circle-check',
            'title' => 'Documentación aprobada',
            'message' => 'Tu documentación fue validada. Avanzá al pago para finalizar la certificación.',
        ],
        3 => [
            'badge' => 'paid',
            'tone' => 'paid',
            'icon' => 'fas fa-shield-heart',
            'title' => 'Pago registrado',
            'message' => 'Confirmamos tu pago y completamos la certificación. Te enviaremos las próximas indicaciones por correo.',
        ],
        4 => [
            'badge' => 'rejected',
            'tone' => 'rejected',
            'icon' => 'fas fa-circle-xmark',
            'title' => 'Solicitud rechazada',
            'message' => 'Revisá el correo registrado: allí detallamos los motivos y los pasos a seguir.',
        ],
        default => [
            'badge' => 'pending',
            'tone' => 'pending',
            'icon' => 'fas fa-hourglass-half',
            'title' => 'Solicitud en revisión',
            'message' => 'Estamos evaluando la documentación recibida y te avisaremos por correo ni bien tengamos novedades.',
        ],
    };
}

$viewData = is_array($data) ? $data : [];
if (!$viewData && !$error) {
    $error = 'No encontramos la solicitud de certificación para mostrar.';
}

$nombreCurso = $viewData['curso_nombre'] ?? '';
$nombreSolicitante = trim(($viewData['nombre'] ?? '') . ' ' . ($viewData['apellido'] ?? ''));
$estadoValor = $viewData['estado'] ?? null;
$estadoActual = cert_estado_label($estadoValor);
$estadoDetalles = cert_estado_details($estadoValor);
$estadoBadgeClass = $estadoDetalles['badge'] ?? 'pending';
$estadoHighlightClass = 'status-' . ($estadoDetalles['tone'] ?? 'pending');
$estadoHighlightIcon = $estadoDetalles['icon'] ?? 'fas fa-hourglass-half';
$estadoHighlightTitle = $estadoDetalles['title'] ?? $estadoActual;
$estadoHighlightMessage = $estadoDetalles['message'] ?? '';
$estadoHeaderClass = match ($estadoBadgeClass) {
    'rejected' => 'error',
    'approved',
    'paid' => 'success',
    default => 'pending',
};
$estadoHeaderIcon = match ($estadoBadgeClass) {
    'rejected' => 'fas fa-circle-xmark',
    'paid' => 'fas fa-shield-heart',
    'approved' => 'fas fa-circle-check',
    default => 'fas fa-hourglass-half',
};
$estadoHeaderHeading = match ($estadoBadgeClass) {
    'approved' => '¡Tu certificación está aprobada!',
    'paid' => '¡Pago confirmado!',
    'rejected' => 'Necesitamos revisar tu solicitud',
    default => '¡Gracias por enviar tu solicitud!',
};
$estadoHeaderDescription = match ($estadoBadgeClass) {
    'approved' => 'Ya podés avanzar con el pago para completar el proceso.',
    'paid' => 'Registramos tu pago y finalizamos la certificación.',
    'rejected' => 'Revisá tu correo para conocer los detalles y los pasos a seguir.',
    default => 'Recibimos tu documentación y comenzaremos la revisión.',
};
$precioCert = $viewData['precio'] ?? null;
$monedaCert = $viewData['moneda'] ?? 'ARS';
$checkoutLink = null;
$checkoutCtaLabel = 'Ver estado de mi solicitud';
$checkoutCtaIcon = 'fas fa-magnifying-glass';
if (!empty($viewData['id_curso'])) {
    if (!empty($viewData['id_certificacion'])) {
        $checkoutLink = sprintf(
            'checkout.php?id_certificacion=%d&tipo=certificacion&certificacion_registro=%d',
            (int) $viewData['id_curso'],
            (int) $viewData['id_certificacion']
        );
    } else {
        $checkoutLink = sprintf('checkout.php?id_certificacion=%d&tipo=certificacion', (int) $viewData['id_curso']);
    }
}
if ((int) $estadoValor === 2) {
    $checkoutCtaLabel = 'Ir al pago de mi certificación';
    $checkoutCtaIcon = 'fas fa-credit-card';
} elseif ((int) $estadoValor === 3) {
    $checkoutCtaLabel = 'Ver detalles de mi certificación';
    $checkoutCtaIcon = 'fas fa-circle-check';
}
$backLink = '../index.php#certificaciones';

?>
<!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/../head.php'; ?>

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --bg-light: #f9fafb;
            --border-color: #e5e7eb;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .gracias-container {
            padding: 4rem 0;
            animation: fadeIn 0.6s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .gracias-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .gracias-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #06b6d4 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .gracias-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.5;
            }

            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
        }

        .gracias-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .gracias-header p {
            font-size: 1.125rem;
            opacity: 0.95;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .icon-status {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            animation: scaleIn 0.5s ease-out;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }

        .icon-status.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }

        .icon-status.error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }

        .icon-status.pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 10px 30px rgba(245, 158, 11, 0.3);
        }

        .gracias-content {
            padding: 2.5rem;
        }

        .alert-modern {
            border: none;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: start;
            gap: 1rem;
            animation: slideDown 0.4s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-modern.success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .alert-modern.error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }

        .alert-modern i {
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .status-highlight {
            display: flex;
            gap: 1rem;
            align-items: flex-start;
            padding: 1.75rem;
            border-radius: 18px;
            border: 1px solid rgba(37, 99, 235, 0.15);
            background: rgba(37, 99, 235, 0.08);
            margin-bottom: 2rem;
        }

        .status-highlight__icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #ffffff;
            background: var(--primary-color);
            flex-shrink: 0;
        }

        .status-highlight__label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-muted);
            font-weight: 600;
        }

        .status-highlight__value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-top: 0.35rem;
        }

        .status-highlight__description {
            margin: 0.75rem 0 0;
            font-size: 0.95rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .status-highlight.status-pending {
            background: rgba(245, 158, 11, 0.12);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .status-highlight.status-pending .status-highlight__icon {
            background: var(--warning-color);
        }

        .status-highlight.status-approved,
        .status-highlight.status-paid {
            background: rgba(16, 185, 129, 0.12);
            border-color: rgba(16, 185, 129, 0.3);
        }

        .status-highlight.status-approved .status-highlight__icon,
        .status-highlight.status-paid .status-highlight__icon {
            background: var(--success-color);
        }

        .status-highlight.status-rejected {
            background: rgba(239, 68, 68, 0.12);
            border-color: rgba(239, 68, 68, 0.3);
        }

        .status-highlight.status-rejected .status-highlight__icon {
            background: var(--danger-color);
        }

        .summary-card {
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            border: 2px solid var(--border-color);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.1);
        }

        .summary-item {
            display: flex;
            align-items: start;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-item i {
            color: var(--primary-color);
            font-size: 1.25rem;
            width: 24px;
            flex-shrink: 0;
            margin-top: 0.25rem;
        }

        .summary-label {
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .summary-value {
            font-size: 1.125rem;
            color: var(--text-dark);
            font-weight: 600;
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .badge-status.pending {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
        }

        .badge-status.approved {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
        }

        .badge-status.paid {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
        }

        .badge-status.rejected {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
        }

        .timeline-section {
            margin-bottom: 2rem;
        }

        .timeline-section h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
        }

        .timeline-item {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            position: relative;
            padding-left: 1rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 1.875rem;
            top: 3rem;
            bottom: -2rem;
            width: 2px;
            background: linear-gradient(180deg, var(--primary-color) 0%, transparent 100%);
        }

        .timeline-item:last-child::before {
            display: none;
        }

        .timeline-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, #06b6d4 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
            position: relative;
            z-index: 1;
        }

        .timeline-content h3 {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .timeline-content p {
            color: var(--text-muted);
            margin: 0;
            line-height: 1.6;
        }

        .btn-modern {
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #06b6d4 100%);
            color: white;
        }

        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            box-shadow: none;
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .gracias-header h1 {
                font-size: 1.75rem;
            }

            .gracias-content {
                padding: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn-modern {
                width: 100%;
                justify-content: center;
            }

            .timeline-item {
                gap: 1rem;
            }

            .timeline-icon {
                width: 48px;
                height: 48px;
                font-size: 1.25rem;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../nav.php'; ?>

    <main class="gracias-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xl-8 col-lg-10">
                    <div class="gracias-card">
                        <?php if ($error): ?>
                            <div class="gracias-header">
                                <div class="icon-status error">
                                    <i class="fas fa-triangle-exclamation"></i>
                                </div>
                                <h1>No pudimos encontrar tu solicitud</h1>
                                <p>Ocurrió un problema al recuperar la información</p>
                            </div>
                            <div class="gracias-content">
                                <div class="alert-modern error">
                                    <i class="fas fa-circle-exclamation"></i>
                                    <div>
                                        <strong>Error al cargar los datos</strong>
                                        <div style="margin-top: 0.5rem;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <div style="margin-top: 0.5rem;">Si necesitas ayuda, escribinos a <a href="mailto:<?php echo htmlspecialchars(checkout_mail_config()['admin_email'], ENT_QUOTES, 'UTF-8'); ?>" style="color: inherit; font-weight: 600;"><?php echo htmlspecialchars(checkout_mail_config()['admin_email'], ENT_QUOTES, 'UTF-8'); ?></a></div>
                                    </div>
                                </div>
                                <div class="action-buttons">
                                    <a href="<?php echo htmlspecialchars($backLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn-modern btn-primary">
                                        <i class="fas fa-arrow-left"></i>
                                        Volver a certificaciones
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="gracias-header">
                                <div class="icon-status <?php echo htmlspecialchars($estadoHeaderClass, ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="<?php echo htmlspecialchars($estadoHeaderIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                </div>
                                <h1><?php echo htmlspecialchars($estadoHeaderHeading, ENT_QUOTES, 'UTF-8'); ?></h1>
                                <p><?php echo htmlspecialchars($estadoHeaderDescription, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div class="gracias-content">
                                <div class="status-highlight <?php echo htmlspecialchars($estadoHighlightClass, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="status-highlight__icon">
                                        <i class="<?php echo htmlspecialchars($estadoHighlightIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                    </div>
                                    <div class="status-highlight__body">
                                        <div class="status-highlight__label">Estado actual</div>
                                        <div class="status-highlight__value"><?php echo htmlspecialchars($estadoHighlightTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php if ($estadoHighlightMessage !== ''): ?>
                                            <p class="status-highlight__description"><?php echo htmlspecialchars($estadoHighlightMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="summary-card">
                                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--text-dark); margin-bottom: 1.5rem;">
                                        <i class="fas fa-file-certificate" style="color: var(--primary-color); margin-right: 0.5rem;"></i>
                                        Resumen de tu solicitud
                                    </h2>

                                    <div class="summary-item">
                                        <i class="fas fa-info-circle"></i>
                                        <div style="flex: 1;">
                                            <div class="summary-label">Estado</div>
                                            <div>
                                                <span class="badge-status <?php echo htmlspecialchars($estadoBadgeClass, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="fas fa-circle" style="font-size: 0.5rem;"></i>
                                                    <?php echo htmlspecialchars($estadoActual, ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </div>
                                            <?php if ($precioCert !== null): ?>
                                                <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.75rem;">
                                                    <i class="fas fa-tag" style="margin-right: 0.5rem;"></i>
                                                    Inversión estimada: <strong><?php echo htmlspecialchars(number_format((float) $precioCert, 2, ',', '.'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($monedaCert, ENT_QUOTES, 'UTF-8'); ?></strong>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="summary-item">
                                        <i class="fas fa-certificate"></i>
                                        <div style="flex: 1;">
                                            <div class="summary-label">Certificación</div>
                                            <div class="summary-value"><?php echo htmlspecialchars($nombreCurso !== '' ? $nombreCurso : 'Certificación solicitada', ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    </div>

                                    <div class="summary-item">
                                        <i class="fas fa-user"></i>
                                        <div style="flex: 1;">
                                            <div class="summary-label">Solicitante</div>
                                            <div class="summary-value"><?php echo htmlspecialchars($nombreSolicitante !== '' ? $nombreSolicitante : 'Datos registrados en tu perfil', ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    </div>

                                    <div class="summary-item">
                                        <i class="fas fa-envelope"></i>
                                        <div style="flex: 1;">
                                            <div class="summary-label">Contacto</div>
                                            <div class="summary-value"><?php echo htmlspecialchars($viewData['email'] ?? 'Te escribiremos al correo registrado', ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php if (!empty($viewData['telefono'])): ?>
                                                <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 0.25rem;">
                                                    <i class="fas fa-phone" style="margin-right: 0.5rem;"></i>
                                                    <?php echo htmlspecialchars($viewData['telefono'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="timeline-section">
                                    <h2>¿Qué sucede ahora?</h2>

                                    <div class="timeline-item">
                                        <div class="timeline-icon">
                                            <i class="fas fa-file-circle-check"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h3>Revisión de tu formulario</h3>
                                            <p>Verificaremos que la documentación esté completa y cumpla con los requisitos establecidos para la certificación.</p>
                                        </div>
                                    </div>

                                    <div class="timeline-item">
                                        <div class="timeline-icon">
                                            <i class="fas fa-envelope-open-text"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h3>Notificación por correo</h3>
                                            <p>Te avisaremos si necesitamos ajustes o cuando la certificación quede aprobada para avanzar al siguiente paso.</p>
                                        </div>
                                    </div>

                                    <div class="timeline-item">
                                        <div class="timeline-icon">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <h3>Habilitación del pago</h3>
                                            <p>Una vez aprobada la documentación, podrás completar el pago desde el checkout para finalizar tu certificación.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="action-buttons">
                                    <?php if ($checkoutLink): ?>
                                        <a href="<?php echo htmlspecialchars($checkoutLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn-modern btn-primary">
                                            <i class="<?php echo htmlspecialchars($checkoutCtaIcon, ENT_QUOTES, 'UTF-8'); ?>"></i>
                                            <?php echo htmlspecialchars($checkoutCtaLabel, ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?php echo htmlspecialchars($backLink, ENT_QUOTES, 'UTF-8'); ?>" class="btn-modern btn-outline">
                                        <i class="fas fa-arrow-left"></i>
                                        Volver a certificaciones
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../footer.php'; ?>

</body>

</html>