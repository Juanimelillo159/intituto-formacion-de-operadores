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
    </main><?php include 'footer.php'; ?>
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

$formCapLocations = [];

$formCertLocations = [];

$formSharedLocation = '';

$formUseSharedLocation = false;



if ($_SERVER['REQUEST_METHOD'] === 'POST' && $coursesError === null) {

    $formUseSharedLocation = isset($_POST['use_shared_location']) && $_POST['use_shared_location'] === '1';

    $formSharedLocation = trim((string)($_POST['shared_location_all'] ?? ''));



    if (!$formUseSharedLocation) {

        $formSharedLocation = '';

    }



    $orderDetails = [];

    $validationErrors = [];



    if ($formUseSharedLocation && $formSharedLocation === '') {

        $validationErrors[] = 'Debes indicar la ubicacion general para todas las actividades.';

    }



    foreach ($courses as $course) {

        $courseId = (int)($course['id_curso'] ?? 0);

        $capKey = 'cap_' . $courseId;

        $certKey = 'cert_' . $courseId;



        $capQty = isset($_POST[$capKey]) ? max(0, (int)$_POST[$capKey]) : 0;

        $formCapValues[$courseId] = $capQty;



        $turnos = isset($_POST[$certKey]) ? max(0, min(10, (int)$_POST[$certKey])) : 0;

        $formCertTurnos[$courseId] = $turnos;



        if (!isset($formCertAssistants[$courseId])) {

            $formCertAssistants[$courseId] = [];

        }



        if (!isset($formCertLocations[$courseId])) {

            $formCertLocations[$courseId] = [];

        }



        $capLocationKey = 'cap_location_' . $courseId;

        $capLocation = $formUseSharedLocation ? $formSharedLocation : trim((string)($_POST[$capLocationKey] ?? ''));

        $formCapLocations[$courseId] = $capLocation;



        if ($capQty > 0) {

            if ($capLocation === '') {

                $validationErrors[] = 'Debes indicar la ubicacion para las capacitaciones seleccionadas.';

            } else {

                $orderDetails[] = [

                    'curso_id' => $courseId,

                    'tipo' => 'capacitacion',

                    'turno' => null,

                    'asistentes' => $capQty,

                    'ubicacion' => $capLocation,

                ];

            }

        }



        if ($turnos > 0) {

            for ($turno = 1; $turno <= $turnos; $turno++) {

                $assistKey = 'asistentes_' . $courseId . '_' . $turno;

                $assist = isset($_POST[$assistKey]) ? max(1, min(10, (int)$_POST[$assistKey])) : 1;

                $formCertAssistants[$courseId][$turno] = $assist;



                $certLocationKey = 'cert_location_' . $courseId . '_' . $turno;

                $certLocation = $formUseSharedLocation ? $formSharedLocation : trim((string)($_POST[$certLocationKey] ?? ''));

                $formCertLocations[$courseId][$turno] = $certLocation;



                if ($certLocation === '') {

                    $validationErrors[] = 'Debes indicar la ubicacion para cada turno de certificacion seleccionado.';

                } else {

                    $orderDetails[] = [

                        'curso_id' => $courseId,

                        'tipo' => 'certificacion',

                        'turno' => $turno,

                        'asistentes' => $assist,

                        'ubicacion' => $certLocation,

                    ];

                }

            }

        } else {

            $formCertAssistants[$courseId] = [];

            $formCertLocations[$courseId] = [];

        }

    }



    if (!empty($validationErrors)) {

        $orderError = implode(' ', array_unique($validationErrors));

    } elseif (empty($orderDetails)) {

        $orderError = 'Debes seleccionar al menos una capacitacion o turno de certificacion.';

    } else {

        try {

            $pdo->beginTransaction();



            $stmtPedido = $pdo->prepare('INSERT INTO inscripcion_pedidos (usuario_id, created_at) VALUES (?, NOW())');

            $stmtPedido->execute([$currentUserId]);

            $pedidoId = (int)$pdo->lastInsertId();



            $stmtDetalle = $pdo->prepare('INSERT INTO inscripcion_pedidos_detalle (pedido_id, curso_id, tipo, turno, asistentes, ubicacion) VALUES (?, ?, ?, ?, ?, ?)');



            foreach ($orderDetails as $detail) {

                $stmtDetalle->execute([

                    $pedidoId,

                    $detail['curso_id'],

                    $detail['tipo'],

                    $detail['turno'],

                    $detail['asistentes'],

                    $detail['ubicacion'],

                ]);

            }



            $pdo->commit();

            $orderSuccess = true;

            $successMessage = 'Pedido registrado correctamente. Numero de pedido #' . $pedidoId . '.';



            $formCapValues = [];

            $formCertTurnos = [];

            $formCertAssistants = [];

            $formCapLocations = [];

            $formCertLocations = [];

            $formSharedLocation = '';

            $formUseSharedLocation = false;

        } catch (Throwable $exception) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }

            $orderError = 'No pudimos guardar el pedido. Intentalo nuevamente.';

            error_log('inscripciones.php pedido: ' . $exception->getMessage());

        }

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
                    <form method="post" class="inscripciones-form" data-use-shared="<?php echo $formUseSharedLocation ? "1" : "0"; ?>" data-shared-location="<?php echo htmlspecialchars($formSharedLocation, ENT_QUOTES, "UTF-8"); ?>">
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
                                $assistantsJson = json_encode($assistantsByTurno, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                                if ($assistantsJson === false) {
                                    $assistantsJson = '{}';
                                }
                                $certLocationsByTurno = $formCertLocations[$courseId] ?? [];
                                $certLocationsJson = json_encode($certLocationsByTurno, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
                                if ($certLocationsJson === false) {
                                    $certLocationsJson = '{}';
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
                                                <input type="number" class="form-control" min="0" step="1" value="<?php echo htmlspecialchars((string)$initialCapValue, ENT_QUOTES, 'UTF-8'); ?>" name="cap_<?php echo $courseId; ?>" id="cap_<?php echo $courseId; ?>" data-initial-location="<?php echo htmlspecialchars($formCapLocations[$courseId] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                                <a class="btn btn-primary btn-pill d-inline-flex align-items-center mt-2 text-nowrap" href="capacitacion.php?id_curso=<?php echo urlencode((string)$courseId); ?>">
                                                    <i class="bi bi-activity me-2"></i>Capacitaci&oacute;n
                                                </a>
                                            </div>
                                            <div class="d-flex flex-column flex-fill certification-section" data-course-id="<?php echo $courseId; ?>" data-initial-assistants='<?php echo htmlspecialchars($assistantsJson, ENT_QUOTES, 'UTF-8'); ?>' data-initial-locations='<?php echo htmlspecialchars($certLocationsJson, ENT_QUOTES, 'UTF-8'); ?>'>
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
                        <div id="location-hidden-fields"></div>
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-pill" id="openOrderSummary">Revisar y crear pedido</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="orderSummaryModal" tabindex="-1" aria-labelledby="orderSummaryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderSummaryModalLabel">Revisar pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">Verifica la informacion y completa las ubicaciones antes de crear el pedido.</p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="modalUseSharedLocation">
                    <label class="form-check-label" for="modalUseSharedLocation">Usar la misma ubicacion para todas las actividades</label>
                </div>
                <div class="mb-3 d-none" id="modalSharedLocationWrapper">
                    <label class="form-label" for="modalSharedLocation">Ubicacion general</label>
                    <input type="text" class="form-control" id="modalSharedLocation" placeholder="Ej. Río Gallegos / Caleta Olivia">
                </div>
                <div id="modalSummaryContainer" class="d-flex flex-column gap-3"></div>
            </div>
            <div class="modal-footer">
                <div class="text-danger small me-auto d-none" id="modalValidationMessage"></div>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
                <button type="button" class="btn btn-primary" id="modalConfirmOrder">Confirmar pedido</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>

    document.addEventListener('DOMContentLoaded', function () {

        const parseJsonObject = function (raw) {

            if (!raw) {

                return {};

            }

            try {

                const parsed = JSON.parse(raw);

                if (parsed && typeof parsed === 'object') {

                    return parsed;

                }

            } catch (error) {

                return {};

            }

            return {};

        };



        const sections = document.querySelectorAll('.certification-section');



        sections.forEach(function (section) {

            const turnInput = section.querySelector('.js-turn-count');

            const wrapper = section.querySelector('.js-turnos-wrapper');

            const container = section.querySelector('.js-turnos-container');



            if (!turnInput || !wrapper || !container) {

                return;

            }



            const courseId = section.getAttribute('data-course-id') || '';

            const preloadAssistants = parseJsonObject(section.getAttribute('data-initial-assistants'));



            const renderTurnos = function () {

                const existingValues = {};

                container.querySelectorAll('.js-turno-field input[data-turno-index]').forEach(function (input) {

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



        const form = document.querySelector('.inscripciones-form');

        if (!form) {

            return;

        }



        const bootstrapAvailable = typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function';

        if (!bootstrapAvailable) {

            return;

        }



        const summaryButton = document.getElementById('openOrderSummary');

        const modalElement = document.getElementById('orderSummaryModal');

        const summaryContainer = modalElement ? modalElement.querySelector('#modalSummaryContainer') : null;

        const sharedCheckbox = modalElement ? modalElement.querySelector('#modalUseSharedLocation') : null;

        const sharedWrapper = modalElement ? modalElement.querySelector('#modalSharedLocationWrapper') : null;

        const sharedInput = modalElement ? modalElement.querySelector('#modalSharedLocation') : null;

        const validationMessage = modalElement ? modalElement.querySelector('#modalValidationMessage') : null;

        const confirmButton = modalElement ? modalElement.querySelector('#modalConfirmOrder') : null;

        const hiddenFieldsContainer = form.querySelector('#location-hidden-fields');



        if (!summaryButton || !modalElement || !summaryContainer || !sharedCheckbox || !sharedWrapper || !sharedInput || !validationMessage || !confirmButton || !hiddenFieldsContainer) {

            return;

        }



        const modalInstance = new bootstrap.Modal(modalElement);

        const locationCache = new Map();

        let allowDirectSubmit = false;

        let useSharedCache = form.dataset.useShared === '1';

        let sharedLocationCache = form.dataset.sharedLocation || '';



        if (useSharedCache) {

            sharedCheckbox.checked = true;

            sharedWrapper.classList.remove('d-none');

            if (sharedLocationCache) {

                sharedInput.value = sharedLocationCache;

            }

        }



        summaryButton.addEventListener('click', function (event) {

            event.preventDefault();

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(summaryButton);
            } else {
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }

        });

        form.addEventListener('submit', function (event) {

            if (allowDirectSubmit) {
                allowDirectSubmit = false;
                return;
            }

            event.preventDefault();

            buildSummary();

            applySharedState();

            validationMessage.classList.add('d-none');

            validationMessage.textContent = '';

            sharedInput.classList.remove('is-invalid');

            modalInstance.show();

        });

        sharedCheckbox.addEventListener('change', function () {

            useSharedCache = sharedCheckbox.checked;

            applySharedState();

        });



        sharedInput.addEventListener('input', function () {

            sharedLocationCache = sharedInput.value.trim();

            sharedInput.classList.remove('is-invalid');

        });



        confirmButton.addEventListener('click', function () {

            const summaryItems = summaryContainer.querySelectorAll('.summary-item input[data-item-name]');

            const useShared = sharedCheckbox.checked;

            const sharedValue = sharedInput.value.trim();

            let hasError = false;



            validationMessage.classList.add('d-none');

            validationMessage.textContent = '';

            sharedInput.classList.remove('is-invalid');



            if (summaryItems.length === 0) {

                validationMessage.textContent = 'Debes seleccionar al menos una capacitacion o turno de certificacion.';

                validationMessage.classList.remove('d-none');

                return;

            }



            if (useShared && sharedValue === '') {

                hasError = true;

                sharedInput.classList.add('is-invalid');

                validationMessage.textContent = 'Debes indicar la ubicacion general.';

            }



            const itemValues = [];

            summaryItems.forEach(function (input) {

                const name = input.dataset.itemName;

                if (!name) {

                    return;

                }

                const value = useShared ? sharedValue : input.value.trim();

                if (!useShared && value === '') {

                    hasError = true;

                    input.classList.add('is-invalid');

                    if (!validationMessage.textContent) {

                        validationMessage.textContent = 'Completa la ubicacion para todas las actividades seleccionadas.';

                    }

                } else {

                    input.classList.remove('is-invalid');

                }

                itemValues.push({ name: name, value: value });

            });



            if (hasError) {

                validationMessage.classList.remove('d-none');

                return;

            }



            hiddenFieldsContainer.innerHTML = '';



            const useSharedField = document.createElement('input');

            useSharedField.type = 'hidden';

            useSharedField.name = 'use_shared_location';

            useSharedField.value = useShared ? '1' : '0';

            hiddenFieldsContainer.appendChild(useSharedField);



            const sharedLocationField = document.createElement('input');

            sharedLocationField.type = 'hidden';

            sharedLocationField.name = 'shared_location_all';

            sharedLocationField.value = useShared ? sharedValue : '';

            hiddenFieldsContainer.appendChild(sharedLocationField);



            const submitMarker = document.createElement('input');

            submitMarker.type = 'hidden';

            submitMarker.name = 'submit_pedido';

            submitMarker.value = '1';

            hiddenFieldsContainer.appendChild(submitMarker);



            itemValues.forEach(function (item) {

                const hidden = document.createElement('input');

                hidden.type = 'hidden';

                hidden.name = item.name;

                hidden.value = item.value;

                hiddenFieldsContainer.appendChild(hidden);

                locationCache.set(item.name, item.value);

            });



            useSharedCache = useShared;

            sharedLocationCache = useShared ? sharedValue : '';

            form.dataset.useShared = useShared ? '1' : '0';

            form.dataset.sharedLocation = sharedLocationCache;



            modalInstance.hide();

            allowDirectSubmit = true;
            form.submit();
            allowDirectSubmit = false;

        });



        function collectSummaryItems() {

            const items = [];

            const courseCards = form.querySelectorAll('.config-card');



            courseCards.forEach(function (card) {

                const titleElement = card.querySelector('h2');

                const courseName = titleElement ? titleElement.textContent.trim() : 'Curso';

                const certSection = card.querySelector('.certification-section');

                if (!certSection) {

                    return;

                }

                const courseId = certSection.getAttribute('data-course-id') || '';



                const capInput = card.querySelector('input[name="cap_' + courseId + '"]');

                const capQty = capInput ? parseInt(capInput.value, 10) || 0 : 0;

                if (capInput && capQty > 0) {

                    const itemName = 'cap_location_' + courseId;

                    const datasetLocation = capInput.getAttribute('data-initial-location') || '';

                    const defaultValue = locationCache.get(itemName) ?? (useSharedCache ? sharedLocationCache : datasetLocation);

                    items.push({

                        label: courseName + ' - Capacitaciones (' + capQty + ' asistentes)',

                        name: itemName,

                        defaultValue: defaultValue || ''

                    });

                }



                const turnInput = certSection.querySelector('.js-turn-count');

                const turnCount = turnInput ? parseInt(turnInput.value, 10) || 0 : 0;

                if (turnCount > 0) {

                    const locationData = parseJsonObject(certSection.getAttribute('data-initial-locations'));

                    const asistentesInputs = certSection.querySelectorAll('.js-turnos-container input[name^="asistentes_' + courseId + '_"]');

                    asistentesInputs.forEach(function (assistantInput) {

                        const turnoAttr = assistantInput.getAttribute('data-turno-index') || '';

                        const turnoIndex = parseInt(turnoAttr, 10);

                        if (Number.isNaN(turnoIndex)) {

                            return;

                        }

                        const asistentesQty = Math.max(1, parseInt(assistantInput.value, 10) || 0);

                        const itemName = 'cert_location_' + courseId + '_' + turnoIndex;

                        const datasetLocation = locationData[String(turnoIndex)] ?? locationData[turnoIndex] ?? '';

                        const defaultValue = locationCache.get(itemName) ?? (useSharedCache ? sharedLocationCache : datasetLocation);

                        items.push({

                            label: courseName + ' - Certificacion turno ' + turnoIndex + ' (' + asistentesQty + ' asistentes)',

                            name: itemName,

                            defaultValue: defaultValue || ''

                        });

                    });

                }

            });



            return items;

        }



        function buildSummary() {

            summaryContainer.innerHTML = '';

            const items = collectSummaryItems();



            if (items.length === 0) {

                summaryContainer.innerHTML = '<p class="text-muted mb-0">No seleccionaste ninguna capacitacion ni turno de certificacion.</p>';

                confirmButton.disabled = true;

                sharedCheckbox.checked = false;

                sharedCheckbox.disabled = true;

                sharedWrapper.classList.add('d-none');

                return;

            }



            confirmButton.disabled = false;

            sharedCheckbox.disabled = false;



            items.forEach(function (item) {

                const wrapper = document.createElement('div');

                wrapper.className = 'p-3 border rounded summary-item';



                const label = document.createElement('label');

                label.className = 'form-label fw-semibold small mb-1';

                label.textContent = item.label;



                const input = document.createElement('input');

                input.type = 'text';

                input.className = 'form-control';

                input.placeholder = 'Ej. Rio Gallegos / Caleta Olivia';

                input.dataset.itemName = item.name;

                input.value = item.defaultValue;

                input.addEventListener('input', function () {

                    locationCache.set(item.name, input.value.trim());

                    input.classList.remove('is-invalid');

                });



                wrapper.appendChild(label);

                wrapper.appendChild(input);

                summaryContainer.appendChild(wrapper);

            });



            sharedCheckbox.checked = useSharedCache;

            sharedWrapper.classList.toggle('d-none', !useSharedCache);

            if (useSharedCache && sharedLocationCache) {

                sharedInput.value = sharedLocationCache;

            } else if (!useSharedCache) {

                sharedInput.value = sharedLocationCache;

            }

        }



        function applySharedState() {

            const useShared = sharedCheckbox.checked;

            const summaryInputs = summaryContainer.querySelectorAll('.summary-item input');



            if (summaryInputs.length === 0) {

                sharedWrapper.classList.add('d-none');

                return;

            }



            summaryInputs.forEach(function (input) {

                input.disabled = useShared;

                input.classList.remove('is-invalid');

                if (useShared) {

                    input.classList.add('bg-light', 'text-muted');

                } else {

                    input.classList.remove('bg-light', 'text-muted');

                }

            });



            if (useShared) {

                sharedWrapper.classList.remove('d-none');

            } else {

                sharedWrapper.classList.add('d-none');

            }

        }

    });

</script>
</body>
</html>

