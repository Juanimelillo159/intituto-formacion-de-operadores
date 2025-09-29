<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$page_title = 'Mis certificaciones | Instituto de Formación';
$page_description = 'Seguimiento de tus solicitudes de certificación.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';

$statusMap = [
    'pendiente_revision' => ['label' => 'Pendiente de revisión', 'class' => 'bg-warning text-dark', 'hint' => 'Nuestro equipo está revisando el formulario.'],
    'rechazado' => ['label' => 'Requiere correcciones', 'class' => 'bg-danger', 'hint' => 'Actualizá el PDF y reenviálo desde el checkout.'],
    'aprobado' => ['label' => 'Aprobado', 'class' => 'bg-success', 'hint' => 'Ya podés realizar el pago desde el checkout.'],
    'pago_pendiente_confirmacion' => ['label' => 'Pago en validación', 'class' => 'bg-info text-dark', 'hint' => 'Registramos el pago y está en proceso de verificación.'],
    'pagado' => ['label' => 'Completado', 'class' => 'bg-primary', 'hint' => 'El proceso de certificación se completó correctamente.'],
];

$certificaciones = [];
$errorMessage = null;

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare('
        SELECT
            c.id_certificado,
            c.id_curso,
            c.estado,
            c.pdf_nombre,
            c.pdf_path,
            c.pago_metodo,
            c.pago_estado,
            c.pago_monto,
            c.pago_moneda,
            c.pago_referencia,
            c.creado_en,
            c.actualizado_en,
            cursos.nombre_curso
        FROM certificados c
        INNER JOIN cursos ON cursos.id_curso = c.id_curso
        WHERE c.id_usuario = :usuario
        ORDER BY c.creado_en DESC, c.id_certificado DESC
    ');
    $stmt->execute([':usuario' => $userId]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $estado = strtolower((string)($row['estado'] ?? 'pendiente_revision'));
        $info = $statusMap[$estado] ?? ['label' => ucfirst(str_replace('_', ' ', $estado)), 'class' => 'bg-secondary', 'hint' => ''];
        $createdAt = $row['creado_en'] ? new DateTimeImmutable($row['creado_en']) : null;
        $updatedAt = $row['actualizado_en'] ? new DateTimeImmutable($row['actualizado_en']) : null;
        $certificaciones[] = [
            'id' => (int)$row['id_certificado'],
            'curso' => $row['nombre_curso'] ?? 'Certificación',
            'estado' => $estado,
            'estado_label' => $info['label'],
            'estado_class' => $info['class'],
            'estado_hint' => $info['hint'],
            'pdf_nombre' => $row['pdf_nombre'],
            'pdf_url' => !empty($row['pdf_path']) ? ('/' . ltrim((string)$row['pdf_path'], '/')) : null,
            'pago_metodo' => $row['pago_metodo'],
            'pago_estado' => $row['pago_estado'],
            'pago_monto' => $row['pago_monto'],
            'pago_moneda' => $row['pago_moneda'],
            'pago_referencia' => $row['pago_referencia'],
            'creado_en' => $createdAt ? $createdAt->format('d/m/Y H:i') : '—',
            'actualizado_en' => $updatedAt ? $updatedAt->format('d/m/Y H:i') : null,
        ];
    }
} catch (Throwable $exception) {
    $errorMessage = 'No pudimos cargar tus certificaciones en este momento.';
}

?>
<!DOCTYPE html>
<html lang="es">
<?php include 'head.php'; ?>
<body class="config-page d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="index.php" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver al inicio</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Mis certificaciones</h1>
                    <p class="mb-0 text-muted">Revisá el estado de tus solicitudes y pagos.</p>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <?php if ($errorMessage !== null): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-0"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php elseif (empty($certificaciones)): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-4">Todavía no registraste solicitudes de certificación.</p>
                        <a class="btn btn-gradient" href="index.php#servicios-capacitacion">Explorar certificaciones</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($certificaciones as $cert): ?>
                        <div class="config-card shadow mb-4 text-start">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                <div>
                                    <h2 class="h5 mb-1"><?php echo htmlspecialchars($cert['curso'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                    <div class="small text-muted">Creada: <?php echo htmlspecialchars($cert['creado_en'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php if ($cert['actualizado_en'] !== null): ?>
                                        <div class="small text-muted">Última actualización: <?php echo htmlspecialchars($cert['actualizado_en'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-md-end">
                                    <span class="badge <?php echo htmlspecialchars($cert['estado_class'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($cert['estado_label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <?php if (!empty($cert['estado_hint'])): ?>
                                        <div class="small text-muted mt-2"><?php echo htmlspecialchars($cert['estado_hint'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <hr>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <h6 class="text-uppercase text-muted small mb-1">Formulario enviado</h6>
                                    <?php if ($cert['pdf_url']): ?>
                                        <a class="btn btn-outline-light btn-sm" href="<?php echo htmlspecialchars($cert['pdf_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                            <i class="fas fa-file-pdf me-2"></i><?php echo htmlspecialchars($cert['pdf_nombre'] ?? 'Ver PDF', ENT_QUOTES, 'UTF-8'); ?>
                                        </a>
                                    <?php else: ?>
                                        <p class="small text-muted mb-0">Sin archivo disponible.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-uppercase text-muted small mb-1">Pago</h6>
                                    <?php if ($cert['pago_metodo']): ?>
                                        <p class="mb-0 small">
                                            Método: <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$cert['pago_metodo'])), ENT_QUOTES, 'UTF-8'); ?></strong><br>
                                            Estado: <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)($cert['pago_estado'] ?? 'pendiente'))), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            <?php if ($cert['pago_monto'] !== null): ?>
                                                <br>Monto: <strong><?php echo htmlspecialchars(strtoupper((string)($cert['pago_moneda'] ?? 'ARS')), ENT_QUOTES, 'UTF-8'); ?> <?php echo number_format((float)$cert['pago_monto'], 2, ',', '.'); ?></strong>
                                            <?php endif; ?>
                                            <?php if (!empty($cert['pago_referencia'])): ?>
                                                <br>Referencia: <?php echo htmlspecialchars($cert['pago_referencia'], ENT_QUOTES, 'UTF-8'); ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="small text-muted mb-0">Sin pago registrado.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-3 small text-muted">
                                ¿Necesitás actualizar tu formulario o realizar un pago? Ingresá nuevamente al checkout de la certificación desde la página del curso.
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
