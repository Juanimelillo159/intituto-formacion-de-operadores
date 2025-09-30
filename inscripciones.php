<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)($_SESSION['usuario'] ?? 0);
$currentPermiso = (int)($_SESSION['permiso'] ?? 0);

$page_title = 'Inscripciones | Instituto de Formacion';
$page_description = 'Gestiona las inscripciones masivas para tus trabajadores.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';

if ($currentPermiso !== 3) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <?php include 'head.php'; ?>
    <body class="config-page d-flex flex-column min-vh-100">
    <?php include 'nav.php'; ?>
    <main class="config-main flex-grow-1 py-5">
        <div class="container">
            <div class="alert alert-danger" role="alert">
                No tienes permiso para acceder a esta seccion.
            </div>
            <a class="btn btn-primary" href="mis_cursos.php">Volver a mis cursos</a>
        </div>
    </main>
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

$pdo = getPdo();

$courses = [];
$coursesError = null;

try {
    $sqlCourses = <<<SQL
SELECT id_curso, nombre_curso, descripcion_curso, duracion, '' AS categoria
FROM cursos
ORDER BY nombre_curso ASC
SQL;
    $stmtCourses = $pdo->query($sqlCourses);
    $courses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('inscripciones.php courses query: ' . $exception->getMessage());
    $coursesError = 'No pudimos cargar la lista de cursos. Detalle: ' . $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">

<?php include 'head.php'; ?>

<body class="config-page d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="mis_cursos.php" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver a mis cursos</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Inscripciones</h1>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
                <div class="mb-4">
                    <p class="text-white-50 mb-0">Selecciona la cantidad de capacitaciones y certificaciones que necesitas comprar para tu equipo. Pr&oacute;ximamente podr&aacute;s generar el pedido desde aqu&iacute;.</p>
                </div>

                <?php if ($coursesError !== null): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($coursesError, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php elseif (empty($courses)): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-0">Por el momento no hay cursos disponibles.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($courses as $course): ?>
                            <?php
                                $courseId = (int)($course['id_curso'] ?? 0);
                                $courseName = $course['nombre_curso'] ?? 'Curso';
                                $courseDesc = (string)($course['descripcion_curso'] ?? '');
                                if (function_exists('mb_strlen')) {
                                    $courseDescShort = mb_strlen($courseDesc, 'UTF-8') > 200
                                        ? mb_substr($courseDesc, 0, 200, 'UTF-8') . '...'
                                        : $courseDesc;
                                } else {
                                    $courseDescShort = strlen($courseDesc) > 200
                                        ? substr($courseDesc, 0, 200) . '...'
                                        : $courseDesc;
                                }
                                $courseDuration = $course['duracion'] ?? '';
                                $courseCategory = $course['categoria'] ?? '';
                            ?>
                            <div class="col-12 col-lg-6">
                                <div class="config-card shadow mb-4 text-start h-100 d-flex flex-column">
                                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                                        <div class="flex-grow-1">
                                            <h2 class="h5 mb-1"><?php echo htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8'); ?></h2>
                                            <div class="text-muted small mb-2">
                                                <?php if ($courseDuration !== ''): ?>
                                                    <span class="me-3"><i class="bi bi-clock me-1"></i><?php echo htmlspecialchars((string)$courseDuration, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                                <?php if ($courseCategory !== ''): ?>
                                                    <span><i class="bi bi-journal me-1"></i><?php echo htmlspecialchars((string)$courseCategory, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($courseDescShort, ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <div class="d-flex flex-column flex-md-row gap-3 align-items-stretch" style="min-width: 320px;">
                                            <div class="d-flex flex-column flex-fill">
                                                <span class="text-muted text-uppercase fw-semibold small">Capacitaciones</span>
                                                <input type="number" class="form-control mt-1" min="0" step="1" value="0" name="cap_<?php echo $courseId; ?>">
                                                <a class="btn btn-primary btn-pill d-inline-flex align-items-center mt-2 text-nowrap" href="capacitacion.php?id_curso=<?php echo urlencode((string)$courseId); ?>">
                                                    <i class="bi bi-activity me-2"></i>Capacitaci&oacute;n
                                                </a>
                                            </div>
                                            <div class="d-flex flex-column flex-fill">
                                                <span class="text-muted text-uppercase fw-semibold small">Certificaciones</span>
                                                <input type="number" class="form-control mt-1" min="0" step="1" value="0" name="cert_<?php echo $courseId; ?>">
                                                <a class="btn btn-secondary btn-pill d-inline-flex align-items-center mt-2 text-nowrap" href="certificacion.php?id_curso=<?php echo urlencode((string)$courseId); ?>">
                                                    <i class="bi bi-award me-2"></i>Certificaci&oacute;n
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-primary" disabled>Ir al checkout (pr&oacute;ximamente)</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
