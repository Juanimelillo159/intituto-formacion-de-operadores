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

$orderSuccess = false;
$orderError = null;
$successMessage = null;
$formCapValues = [];
$formCertTurnos = [];
$formCertAssistants = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $coursesError === null) {
    foreach ($courses as $course) {
        $courseId = (int)($course['id_curso'] ?? 0);
        $capKey = 'cap_' . $courseId;
        $certKey = 'cert_' . $courseId;

        $capQty = isset($_POST[$capKey]) ? max(0, (int)$_POST[$capKey]) : 0;
        $formCapValues[$courseId] = $capQty;

        $turnos = isset($_POST[$certKey]) ? max(0, min(10, (int)$_POST[$certKey])) : 0;
        $formCertTurnos[$courseId] = $turnos;

        if ($turnos > 0) {
            for ($turno = 1; $turno <= $turnos; $turno++) {
                $assistKey = 'asistentes_' . $courseId . '_' . $turno;
                $assist = isset($_POST[$assistKey]) ? max(1, min(10, (int)$_POST[$assistKey])) : 1;
                if (!isset($formCertAssistants[$courseId])) {
                    $formCertAssistants[$courseId] = [];
                }
                $formCertAssistants[$courseId][$turno] = $assist;
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $stmtPedido = $pdo->prepare('INSERT INTO inscripcion_pedidos (usuario_id, created_at) VALUES (?, NOW())');
        $stmtPedido->execute([$currentUserId]);
        $pedidoId = (int)$pdo->lastInsertId();

        $stmtDetalle = $pdo->prepare('INSERT INTO inscripcion_pedidos_detalle (pedido_id, curso_id, tipo, turno, asistentes) VALUES (?, ?, ?, ?, ?)');

        $hasDetails = false;

        foreach ($courses as $course) {
            $courseId = (int)($course['id_curso'] ?? 0);
            $capQty = $formCapValues[$courseId] ?? 0;
            if ($capQty > 0) {
                $stmtDetalle->execute([$pedidoId, $courseId, 'capacitacion', null, $capQty]);
                $hasDetails = true;
            }

            $turnos = $formCertTurnos[$courseId] ?? 0;
            if ($turnos > 0) {
                for ($turno = 1; $turno <= $turnos; $turno++) {
                    $assist = $formCertAssistants[$courseId][$turno] ?? 1;
                    $stmtDetalle->execute([$pedidoId, $courseId, 'certificacion', $turno, $assist]);
                    $hasDetails = true;
                }
            }
        }

        if (!$hasDetails) {
            throw new RuntimeException('Debes seleccionar al menos una capacitacion o turno de certificacion.');
        }

        $pdo->commit();
        $orderSuccess = true;
        $successMessage = 'Pedido registrado correctamente. Numero de pedido #' . $pedidoId . '.';

        $formCapValues = [];
        $formCertTurnos = [];
        $formCertAssistants = [];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $orderError = 'No pudimos guardar el pedido. Intentalo nuevamente.';
        error_log('inscripciones.php pedido: ' . $exception->getMessage());
    }
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
                    <p class="text-white-50 mb-0">Selecciona la cantidad de capacitaciones y certificaciones que necesita tu equipo y genera el pedido desde aqui.</p>
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
                    <?php if ($orderSuccess && $successMessage !== null): ?>
                        <div class="alert alert-success" role="alert">
                            <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php elseif ($orderError !== null): ?>
                        <div class="alert alert-danger" role="alert">
                            <?php echo htmlspecialchars($orderError, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" class="inscripciones-form">
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
                                $initialCapValue = $formCapValues[$courseId] ?? 0;
                                $initialTurnos = $formCertTurnos[$courseId] ?? 0;
                                $assistantsByTurno = $formCertAssistants[$courseId] ?? [];
                                $assistantsJson = json_encode($assistantsByTurno);
                                if ($assistantsJson === false) {
                                    $assistantsJson = '{}';
                                }
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
                                                <label class="text-muted small mt-2 mb-1" for="cap_<?php echo $courseId; ?>">Cantidad de asistentes</label>
                                                <input type="number" class="form-control" min="0" step="1" value="<?php echo htmlspecialchars((string)$initialCapValue, ENT_QUOTES, 'UTF-8'); ?>" name="cap_<?php echo $courseId; ?>" id="cap_<?php echo $courseId; ?>">
                                                <a class="btn btn-primary btn-pill d-inline-flex align-items-center mt-2 text-nowrap" href="capacitacion.php?id_curso=<?php echo urlencode((string)$courseId); ?>">
                                                    <i class="bi bi-activity me-2"></i>Capacitaci&oacute;n
                                                </a>
                                            </div>
                                            <div class="d-flex flex-column flex-fill certification-section" data-course-id="<?php echo $courseId; ?>" data-initial-assistants='<?php echo htmlspecialchars($assistantsJson, ENT_QUOTES, 'UTF-8'); ?>'>
                                                <span class="text-muted text-uppercase fw-semibold small">Certificaciones</span>
                                                <label class="text-muted small mt-1 mb-1" for="turnos_<?php echo $courseId; ?>">Cantidad de turnos</label>
                                                <input type="number" class="form-control js-turn-count" id="turnos_<?php echo $courseId; ?>" min="0" max="10" step="1" value="<?php echo htmlspecialchars((string)$initialTurnos, ENT_QUOTES, 'UTF-8'); ?>" name="cert_<?php echo $courseId; ?>" data-course-id="<?php echo $courseId; ?>">
                                                <div class="turnos-wrapper mt-3 d-none js-turnos-wrapper">
                                                    <p class="text-muted small mb-2">Selecciona la cantidad de asistentes para cada turno (1 a 10).</p>
                                                    <div class="js-turnos-container"></div>
                                                </div>
                                                <a class="btn btn-secondary btn-pill d-inline-flex align-items-center mt-3 text-nowrap" href="certificacion.php?id_curso=<?php echo urlencode((string)$courseId); ?>">
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
                            <button type="submit" class="btn btn-primary btn-pill" name="submit_pedido">Crear pedido</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sections = document.querySelectorAll('.certification-section');

        sections.forEach(function (section) {
            const turnInput = section.querySelector('.js-turn-count');
            const wrapper = section.querySelector('.js-turnos-wrapper');
            const container = section.querySelector('.js-turnos-container');
            const courseId = section.getAttribute('data-course-id') || '';

            let preloadAssistants = {};
            const assistantsAttr = section.getAttribute('data-initial-assistants');
            if (assistantsAttr) {
                try {
                    const parsed = JSON.parse(assistantsAttr);
                    if (parsed && typeof parsed === 'object') {
                        preloadAssistants = parsed;
                    }
                } catch (error) {
                    preloadAssistants = {};
                }
            }

            const renderTurnos = function () {
                const existingValues = {};
                container.querySelectorAll('.js-turno-field input').forEach(function (input) {
                    const turnoAttr = input.getAttribute('data-turno-index');
                    const turnoParsed = parseInt(turnoAttr, 10);
                    const valueParsed = parseInt(input.value, 10);
                    if (!Number.isNaN(turnoParsed) && !Number.isNaN(valueParsed) && valueParsed >= 1 && valueParsed <= 10) {
                        existingValues[String(turnoParsed)] = valueParsed;
                    }
                });

                const turnos = Math.max(0, Math.min(10, parseInt(turnInput.value, 10) || 0));
                turnInput.value = String(turnos);
                container.innerHTML = '';

                if (turnos > 0) {
                    wrapper.classList.remove('d-none');
                    for (let index = 1; index <= turnos; index += 1) {
                        container.appendChild(buildAsistentesField(courseId, index, preloadAssistants, existingValues));
                    }
                } else {
                    wrapper.classList.add('d-none');
                }
            };

            turnInput.addEventListener('input', renderTurnos);
            renderTurnos();
        });

        function buildAsistentesField(courseId, turnoIndex, preload, existing) {
            const group = document.createElement('div');
            group.className = 'mb-3 js-turno-field';

            const label = document.createElement('label');
            label.className = 'form-label fw-semibold small';
            label.htmlFor = 'asistentes_' + courseId + '_' + turnoIndex;
            label.textContent = 'Asistentes turno ' + turnoIndex;

            const input = document.createElement('input');
            input.type = 'number';
            input.className = 'form-control';
            input.id = 'asistentes_' + courseId + '_' + turnoIndex;
            input.name = 'asistentes_' + courseId + '_' + turnoIndex;
            input.min = '1';
            input.max = '10';
            input.step = '1';
            input.setAttribute('data-turno-index', String(turnoIndex));

            let preset = 1;
            if (existing && Object.prototype.hasOwnProperty.call(existing, String(turnoIndex))) {
                preset = existing[String(turnoIndex)];
            } else if (preload && typeof preload === 'object') {
                let loaded = null;
                if (Object.prototype.hasOwnProperty.call(preload, String(turnoIndex))) {
                    loaded = preload[String(turnoIndex)];
                } else if (Object.prototype.hasOwnProperty.call(preload, turnoIndex)) {
                    loaded = preload[turnoIndex];
                }

                const parsed = parseInt(loaded, 10);
                if (!Number.isNaN(parsed) && parsed >= 1 && parsed <= 10) {
                    preset = parsed;
                }
            }

            input.value = String(preset);

            group.appendChild(label);
            group.appendChild(input);
            return group;
        }
    });
</script>
</body>
</html>

