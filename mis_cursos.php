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

$misCursosAlert = $_SESSION['mis_cursos_alert'] ?? null;
if ($misCursosAlert !== null) {
    unset($_SESSION['mis_cursos_alert']);
}

$page_title = 'Mis cursos | Instituto de Formacion';
$page_description = 'Cursos disponibles para tu cuenta.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';

$statusLabels = [
    'inscripto' => 'Inscripto',
    'en_curso' => 'En curso',
    'completado' => 'Completado',
    'vencido' => 'Vencido',
    'cancelado' => 'Cancelado',
];

$statusClasses = [
    'inscripto' => 'bg-primary',
    'en_curso' => 'bg-info text-dark',
    'completado' => 'bg-success',
    'vencido' => 'bg-warning text-dark',
    'cancelado' => 'bg-danger',
];

$cursosComprados = [];
$errorMessage = null;

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare(
        'SELECT
            c.id_compra,
            c.pagado_en,
            c.moneda,
            ci.id_item,
            ci.cantidad,
            ci.precio_unitario,
            ci.titulo_snapshot,
            cursos.nombre_curso,
            modalidades.nombre_modalidad,
            i.id_inscripcion,
            i.estado AS inscripcion_estado,
            i.progreso AS inscripcion_progreso
        FROM compras c
        INNER JOIN compra_items ci ON ci.id_compra = c.id_compra
        INNER JOIN cursos ON cursos.id_curso = ci.id_curso
        LEFT JOIN modalidades ON modalidades.id_modalidad = ci.id_modalidad
        LEFT JOIN inscripciones i ON i.id_item_compra = ci.id_item AND i.id_usuario = c.id_usuario
        WHERE c.id_usuario = :usuario
          AND c.estado = :estado
        ORDER BY c.pagado_en DESC, c.id_compra DESC, ci.id_item ASC'
    );
    $stmt->bindValue(':usuario', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':estado', 'pagada', PDO::PARAM_STR);
    $stmt->execute();

    $items = [];

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $itemId = (int)$row['id_item'];

        if (!isset($items[$itemId])) {
            $formattedDate = null;
            if (!empty($row['pagado_en'])) {
                try {
                    $formattedDate = (new DateTimeImmutable($row['pagado_en']))->format('d/m/Y H:i');
                } catch (Throwable $exception) {
                    $formattedDate = $row['pagado_en'];
                }
            }

            $items[$itemId] = [
                'id_item' => $itemId,
                'nombre_curso' => $row['nombre_curso'] ?: $row['titulo_snapshot'],
                'nombre_modalidad' => $row['nombre_modalidad'],
                'pagado_en' => $row['pagado_en'],
                'pagado_en_formatted' => $formattedDate,
                'moneda' => $row['moneda'],
                'precio_unitario' => (float)$row['precio_unitario'],
                'cantidad' => (int)$row['cantidad'],
                'inscripcion' => null,
            ];
        }

        if ($items[$itemId]['inscripcion'] === null && !empty($row['inscripcion_estado'])) {
            $stateKey = strtolower((string)$row['inscripcion_estado']);
            $stateLabel = $statusLabels[$stateKey] ?? ucwords(str_replace('_', ' ', (string)$row['inscripcion_estado']));
            $stateClass = $statusClasses[$stateKey] ?? 'bg-secondary';

            $progress = null;
            if ($row['inscripcion_progreso'] !== null) {
                $progress = max(0, min(100, (int)$row['inscripcion_progreso']));
            }

            $items[$itemId]['inscripcion'] = [
                'id_inscripcion' => (int)$row['id_inscripcion'],
                'estado' => $stateLabel,
                'clase' => $stateClass,
                'progreso' => $progress,
            ];
        }
    }

    $cursosComprados = array_values($items);
} catch (Throwable $exception) {
    $errorMessage = 'No pudimos cargar tus cursos en este momento.';
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
                    <h1>Mis cursos</h1>
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
                <?php elseif (empty($cursosComprados)): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-4">Todav&iacute;a no ten&eacute;s cursos adquiridos.</p>
                        <a class="btn btn-gradient" href="index.php#cursos">Explorar cursos disponibles</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cursosComprados as $curso): ?>
                        <?php $inscripcion = $curso['inscripcion']; ?>
                        <div class="config-card shadow mb-4 text-start">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                <div>
                                    <h2 class="h5 mb-1"><?php echo htmlspecialchars($curso['nombre_curso'] ?? 'Curso', ENT_QUOTES, 'UTF-8'); ?></h2>
                                    <?php if (!empty($curso['nombre_modalidad'])): ?>
                                        <div class="text-muted small">Modalidad: <?php echo htmlspecialchars($curso['nombre_modalidad'], ENT_QUOTES, 'UTF-8'); ?></div>
                                    <?php endif; ?>
                                    <?php if ($curso['cantidad'] > 1): ?>
                                        <div class="text-muted small">Cantidad: <?php echo (int)$curso['cantidad']; ?></div>
                                    <?php endif; ?>
                                    <div class="text-muted small">Comprado el <?php echo htmlspecialchars($curso['pagado_en_formatted'] ?? 'Sin fecha', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="text-md-end">
                                    <?php if ($inscripcion !== null): ?>
                                        <span class="badge <?php echo htmlspecialchars($inscripcion['clase'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($inscripcion['estado'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php if ($inscripcion['progreso'] !== null): ?>
                                            <div class="text-muted small mt-2">Progreso: <?php echo (int)$inscripcion['progreso']; ?>%</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-success">Acceso disponible</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($inscripcion !== null && $inscripcion['progreso'] !== null): ?>
                                <div class="progress mt-3" style="height: 6px;">
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo (int)$inscripcion['progreso']; ?>%;" aria-valuenow="<?php echo (int)$inscripcion['progreso']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($misCursosAlert !== null): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var data = <?php echo json_encode($misCursosAlert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

            if (!data) {
                return;
            }

            var title = data.title || 'Sesion iniciada';
            var message = data.message || '';
            var text = (message && message.trim()) ? message : title;

            var styleId = 'mis-cursos-toast-style';
            if (!document.getElementById(styleId)) {
                var style = document.createElement('style');
                style.id = styleId;
                style.textContent = '\n                    .floating-login-alert {\n                        position: fixed;\n                        top: 1rem;\n                        right: 1rem;\n                        z-index: 2000;\n                        min-width: 220px;\n                        max-width: 320px;\n                        padding: 0.75rem 1rem;\n                        border-radius: 0.5rem;\n                        background-color: #198754;\n                        border: 1px solid #146c43;\n                        color: #fff;\n                        box-shadow: 0 0.5rem 1rem rgba(25, 135, 84, 0.35);\n                        opacity: 0;\n                        transform: translateY(-10px);\n                        transition: opacity 200ms ease, transform 200ms ease;\n                    }\n                    .floating-login-alert.show {\n                        opacity: 1;\n                        transform: translateY(0);\n                    }\n                    .floating-login-alert.hide {\n                        opacity: 0;\n                        transform: translateY(-10px);\n                    }\n                ';
                document.head.appendChild(style);
            }

            var alertNode = document.createElement('div');
            alertNode.className = 'floating-login-alert alert alert-success';
            alertNode.setAttribute('role', 'alert');
            alertNode.textContent = text;

            var offsetTop = 16;
            var stickyNav = document.querySelector('.navbar.sticky-top');
            if (stickyNav) {
                offsetTop = stickyNav.getBoundingClientRect().height + 16;
            }
            alertNode.style.top = offsetTop + 'px';

            alertNode.addEventListener('click', function () {
                alertNode.classList.add('hide');
            });

            alertNode.addEventListener('transitionend', function (event) {
                if (event.propertyName === 'opacity' && alertNode.classList.contains('hide')) {
                    alertNode.remove();
                }
            });

            document.body.appendChild(alertNode);

            requestAnimationFrame(function () {
                alertNode.classList.add('show');
            });

            setTimeout(function () {
                alertNode.classList.add('hide');
            }, 5000);
        });
    </script>
<?php endif; ?>
</body>

</html>
