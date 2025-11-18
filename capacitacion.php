<?php
session_start();
require_once 'sbd.php';

// Sanitizar y validar el parámetro id_curso
$id_curso = filter_input(INPUT_GET, 'id_curso', FILTER_VALIDATE_INT);

// Preparar consulta del curso con todos los campos relevantes
$sql_cursos = $con->prepare(
    "SELECT 
        id_curso,
        nombre_curso,
        descripcion_curso,
        duracion,
        objetivos,
        id_complejidad,
        cronograma,
        publico,
        programa,
        requisitos,
        observaciones,
        documentacion
     FROM cursos
     WHERE id_curso = :id_curso"
);
$sql_cursos->bindValue(':id_curso', $id_curso, PDO::PARAM_INT);
$sql_cursos->execute();
$curso = $sql_cursos->fetch(PDO::FETCH_ASSOC) ?: [];

// Modalidades (si corresponde)
$sql_modalidades = $con->prepare(
    "SELECT m.id_modalidad AS modalidad_id,
            m.nombre_modalidad AS modalidad_nombre
     FROM curso_modalidad cm
     JOIN modalidades m ON cm.id_modalidad = m.id_modalidad
     WHERE cm.id_curso = :id_curso"
);
$sql_modalidades->bindValue(':id_curso', $id_curso, PDO::PARAM_INT);
$sql_modalidades->execute();
$modalidades = $sql_modalidades->fetchAll(PDO::FETCH_ASSOC);
$modalidad_nombres = array_map(fn($v) => htmlspecialchars($v['modalidad_nombre']), $modalidades);
$modalidad_nombres_str = implode(' - ', $modalidad_nombres);
$modalidadesDisponibles = array_map(static fn($m) => (int)($m['modalidad_id'] ?? 0), $modalidades);
$modalidadesDisponibles = array_values(array_filter($modalidadesDisponibles, static fn($v) => $v > 0));

$selectedModalidad = filter_input(INPUT_GET, 'modalidad', FILTER_VALIDATE_INT);
if ($selectedModalidad === false) {
    $selectedModalidad = null;
}
if ($selectedModalidad !== null && !in_array($selectedModalidad, $modalidadesDisponibles, true)) {
    $selectedModalidad = null;
}
if ($selectedModalidad === null && !empty($modalidadesDisponibles)) {
    $selectedModalidad = $modalidadesDisponibles[0];
}
$modalidadSeleccionadaNombre = '';
if ($selectedModalidad !== null) {
    foreach ($modalidades as $modalidad) {
        if ((int)($modalidad['modalidad_id'] ?? 0) === $selectedModalidad) {
            $modalidadSeleccionadaNombre = (string)($modalidad['modalidad_nombre'] ?? '');
            break;
        }
    }
}

$precio_capacitacion = null;
$precio_capacitacion_general = null;
$modalidadPriceMap = [];
$enlaceCheckoutCapacitacion = 0;
if (!empty($curso['id_curso'])) {
    $cursoId = (int)$curso['id_curso'];
    $enlaceCheckoutCapacitacion = $cursoId;
    $precio_capacitacion_general = obtener_precio_vigente($con, $cursoId, 'capacitacion');
    foreach ($modalidadesDisponibles as $modalidadId) {
        $modalidadPriceMap[$modalidadId] = obtener_precio_vigente($con, $cursoId, 'capacitacion', $modalidadId);
    }
    if ($selectedModalidad !== null) {
        $precio_capacitacion = $modalidadPriceMap[$selectedModalidad] ?? null;
    } else {
        $precio_capacitacion = $precio_capacitacion_general;
    }
}
$precio_capacitacion_fuente = 'ninguno';
if ($precio_capacitacion !== null) {
    if ($selectedModalidad !== null && isset($modalidadPriceMap[$selectedModalidad]) && $modalidadPriceMap[$selectedModalidad] !== null) {
        $precio_capacitacion_fuente = 'modalidad';
    } elseif ($selectedModalidad === null && $precio_capacitacion_general !== null) {
        $precio_capacitacion_fuente = 'general';
    }
}
$inscripcion_capacitacion_disponible = $precio_capacitacion !== null && $enlaceCheckoutCapacitacion > 0;
$modalidadPricePayload = [];
foreach ($modalidades as $modalidad) {
    $mid = (int)($modalidad['modalidad_id'] ?? 0);
    if ($mid <= 0) {
        continue;
    }
    $modalidadPricePayload[(string)$mid] = [
        'nombre' => (string)($modalidad['modalidad_nombre'] ?? ''),
        'precio' => isset($modalidadPriceMap[$mid]['precio']) ? (float)$modalidadPriceMap[$mid]['precio'] : null,
        'moneda' => $modalidadPriceMap[$mid]['moneda'] ?? ($precio_capacitacion_general['moneda'] ?? 'ARS'),
        'vigente_desde' => $modalidadPriceMap[$mid]['vigente_desde'] ?? null,
    ];
}
$generalPricePayload = $precio_capacitacion_general
    ? [
        'precio' => (float)$precio_capacitacion_general['precio'],
        'moneda' => $precio_capacitacion_general['moneda'] ?? 'ARS',
        'vigente_desde' => $precio_capacitacion_general['vigente_desde'] ?? null,
    ]
    : null;
$precioSinDefinirNote = 'Precio a confirmar con el equipo comercial.';
$precio_capacitacion_display = $precio_capacitacion
    ? strtoupper($precio_capacitacion['moneda'] ?? 'ARS') . ' ' . number_format((float)$precio_capacitacion['precio'], 2, ',', '.')
    : '—';
if ($precio_capacitacion && !empty($precio_capacitacion['vigente_desde'])) {
    $precio_capacitacion_note = 'Vigente desde ' . date('d/m/Y H:i', strtotime($precio_capacitacion['vigente_desde']));
} elseif ($precio_capacitacion) {
    $precio_capacitacion_note = 'Precio vigente disponible en el sistema.';
} else {
    $precio_capacitacion_note = $precioSinDefinirNote;
}
$capacitacion_inscripcion_motivo = 'Contactá al equipo comercial para conocer el valor y completar tu inscripción.';
$modalidadSelectionFallback = 'Seleccioná una modalidad para ver los detalles disponibles.';
$modalidadSeleccionadaTexto = $modalidadSeleccionadaNombre !== ''
    ? 'Seleccionaste: ' . $modalidadSeleccionadaNombre
    : $modalidadSelectionFallback;

if (!empty($curso['id_curso'])) {
    $ventaHabilitada = site_settings_sales_enabled($site_settings, 'capacitacion', (int)$curso['id_curso']);
    if (!$ventaHabilitada) {
        $inscripcion_capacitacion_disponible = false;
        $capacitacion_inscripcion_motivo = 'La inscripción online está temporalmente deshabilitada por el administrador del sitio.';
    } elseif ($precio_capacitacion === null) {
        $capacitacion_inscripcion_motivo = 'Contactá al equipo comercial para conocer el valor y completar tu inscripción.';
    }
}

// Helpers de salida segura
function h(?string $v, string $fallback = ''): string
{
    $v = $v ?? '';
    $v = trim($v);
    return $v !== '' ? htmlspecialchars($v) : $fallback;
}
function p(?string $v, string $fallback = ''): string
{
    // paragraph-safe: con nl2br
    $v = $v ?? '';
    $v = trim($v);
    return $v !== '' ? nl2br(htmlspecialchars($v)) : $fallback;
}

function obtener_precio_vigente(PDO $con, int $cursoId, string $tipoCurso, ?int $modalidadId = null): ?array
{
    static $stmtPorModalidad = null;
    static $stmtGeneral = null;

    if ($modalidadId !== null) {
        if ($stmtPorModalidad === null) {
            $stmtPorModalidad = $con->prepare(
                "SELECT precio, moneda, vigente_desde, id_modalidad
                   FROM curso_precio_hist
                  WHERE id_curso = :curso
                    AND tipo_curso = :tipo
                    AND id_modalidad = :modalidad
                    AND vigente_desde <= NOW()
                    AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
              ORDER BY vigente_desde DESC
                 LIMIT 1"
            );
        }

        $stmtPorModalidad->bindValue(':curso', $cursoId, PDO::PARAM_INT);
        $stmtPorModalidad->bindValue(':tipo', $tipoCurso, PDO::PARAM_STR);
        $stmtPorModalidad->bindValue(':modalidad', $modalidadId, PDO::PARAM_INT);
        $stmtPorModalidad->execute();
        $modalidadRow = $stmtPorModalidad->fetch(PDO::FETCH_ASSOC) ?: null;
        $stmtPorModalidad->closeCursor();
        if ($modalidadRow) {
            return $modalidadRow;
        }
    }

    if ($stmtGeneral === null) {
        $stmtGeneral = $con->prepare(
            "SELECT precio, moneda, vigente_desde, id_modalidad
               FROM curso_precio_hist
              WHERE id_curso = :curso
                AND tipo_curso = :tipo
                AND id_modalidad IS NULL
                AND vigente_desde <= NOW()
                AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
          ORDER BY vigente_desde DESC
             LIMIT 1"
        );
    }

    $stmtGeneral->bindValue(':curso', $cursoId, PDO::PARAM_INT);
    $stmtGeneral->bindValue(':tipo', $tipoCurso, PDO::PARAM_STR);
    $stmtGeneral->execute();
    $row = $stmtGeneral->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmtGeneral->closeCursor();

    return $row ?: null;
}

// Meta dinámicos
$page_title = (h($curso['nombre_curso']) ?: 'Capacitación') . ' | Instituto de Formación';
$page_description = h($curso['descripcion_curso']) ?: 'Página de capacitación del Instituto de Formación de Operadores';
?>
<!DOCTYPE html>
<html lang="es">
<?php $page_styles = '<link rel="stylesheet" href="assets/styles/style_capacitacion.css">'; ?>
<?php include('head.php'); ?>

<body class="capacitaciones">
    <?php include('nav.php'); ?>

    <div class="container py-3">
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i><span>Volver al inicio</span></a>
    </div>

    <div class="container my-4">
        <?php if (!$curso) : ?>
            <div class="alert alert-warning" role="alert">
                No se encontró la capacitación solicitada.
            </div>
        <?php else : ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="content-wrapper">
                        <div class="course-header">
                            <h1 class="course-title"><?php echo h($curso['nombre_curso'], 'Capacitación'); ?></h1>
                            <p class="course-subtitle">Desarrolla tus habilidades con esta capacitación</p>
                        </div>

                        <div class="course-content">
                            <h2 class="section-title"><i class="fas fa-info-circle"></i>Descripción</h2>
                            <div class="course-description">
                                <p class="mb-0"><?php echo p($curso['descripcion_curso'], 'Información no disponible.'); ?></p>
                            </div>

                            <h3 class="section-title"><i class="fas fa-bullseye"></i>Objetivos</h3>
                            <div class="objectives-list">
                                <p class="mb-0"><?php echo p($curso['objetivos'], 'Información no disponible.'); ?></p>
                            </div>

                            <!-- Acordeón (uno a la vez) -->
                            <h3 class="section-title mt-4"><i class="fas fa-list-ul"></i>Más información</h3>
                            <div class="accordion curso-accordion" id="cursoAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hCrono">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#cCrono" aria-expanded="true" aria-controls="cCrono">Cronograma</button>
                                    </h2>
                                    <div id="cCrono" class="accordion-collapse collapse show" aria-labelledby="hCrono" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['cronograma'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hPublico">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cPublico" aria-expanded="false" aria-controls="cPublico">Público</button>
                                    </h2>
                                    <div id="cPublico" class="accordion-collapse collapse" aria-labelledby="hPublico" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['publico'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hPrograma">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cPrograma" aria-expanded="false" aria-controls="cPrograma">Programa</button>
                                    </h2>
                                    <div id="cPrograma" class="accordion-collapse collapse" aria-labelledby="hPrograma" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['programa'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hReqs">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cReqs" aria-expanded="false" aria-controls="cReqs">Requisitos</button>
                                    </h2>
                                    <div id="cReqs" class="accordion-collapse collapse" aria-labelledby="hReqs" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['requisitos'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hObs">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cObs" aria-expanded="false" aria-controls="cObs">Observaciones</button>
                                    </h2>
                                    <div id="cObs" class="accordion-collapse collapse" aria-labelledby="hObs" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['observaciones'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>

                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="hDocs">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cDocs" aria-expanded="false" aria-controls="cDocs">Documentación</button>
                                    </h2>
                                    <div id="cDocs" class="accordion-collapse collapse" aria-labelledby="hDocs" data-bs-parent="#cursoAccordion">
                                        <div class="accordion-body"><?php echo p($curso['documentacion'], 'Información no disponible.'); ?></div>
                                    </div>
                                </div>
                            </div>
                            <!-- /Acordeón -->
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="details-card">
                        <div class="details-header">
                            <h3 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Información de la Capacitación</h3>
                        </div>
                        <div class="details-body">
                            <div class="price-summary">
                                <div class="price-summary-title"><i class="fas fa-hand-holding-usd me-2"></i>Inversión</div>
                                <div class="price-summary-list">
                                    <div class="price-summary-item">
                                        <div>
                                            <div class="price-summary-label">Capacitación</div>
                                            <div class="price-summary-note"><span data-role="price-note"><?php echo htmlspecialchars($precio_capacitacion_note, ENT_QUOTES, 'UTF-8'); ?></span></div>
                                        </div>
                                        <div class="price-summary-value" data-role="price-value">
                                            <?php echo htmlspecialchars($precio_capacitacion_display, ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-clock"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Duración</div>
                                    <div class="detail-value"><?php echo h($curso['duracion'], 'A definir'); ?></div>
                                </div>
                            </div>

                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-laptop"></i></div>
                                <div class="detail-content">
                                    <div class="detail-label">Modalidad</div>
                                    <?php if (!empty($modalidadesDisponibles)): ?>
                                        <div class="detail-value">
                                            <label for="modalidadSelect" class="form-label mb-2">Elegí cómo querés cursar</label>
                                            <select class="form-select" id="modalidadSelect">
                                                <?php foreach ($modalidades as $modalidad): ?>
                                                    <?php $modId = (int)($modalidad['modalidad_id'] ?? 0); ?>
                                                    <?php if ($modId <= 0) { continue; } ?>
                                                    <option value="<?php echo $modId; ?>" <?php echo ($selectedModalidad === $modId) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($modalidad['modalidad_nombre'] ?? ('Modalidad ' . $modId), ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="text-muted small mt-2" data-role="modalidad-selection">
                                                <?php echo htmlspecialchars($modalidadSeleccionadaTexto, ENT_QUOTES, 'UTF-8'); ?>
                                            </div>
                                            <div class="text-muted small mt-2">Disponibles: <?php echo htmlspecialchars($modalidad_nombres_str ?: 'Presencial', ENT_QUOTES, 'UTF-8'); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <div class="detail-value"><?php echo $modalidad_nombres_str ?: 'Presencial'; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Si deseas mostrar complejidad -->
                            <?php if (!empty($curso['id_complejidad'])): ?>
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-layer-group"></i></div>
                                    <div class="detail-content">
                                        <div class="detail-label">Complejidad</div>
                                        <div class="detail-value">Nivel <?php echo (int)$curso['id_complejidad']; ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php $ctaBaseUrl = $enlaceCheckoutCapacitacion > 0
                                ? 'checkout/checkout.php?id_curso=' . urlencode((string)$enlaceCheckoutCapacitacion) . '&tipo=capacitacion'
                                : ''; ?>
                            <?php $ctaHref = $ctaBaseUrl;
                            if ($ctaBaseUrl !== '' && $selectedModalidad !== null) {
                                $ctaHref .= '&modalidad=' . urlencode((string)$selectedModalidad);
                            }
                            ?>
                            <a id="enrollButton"
                               class="enroll-button<?php echo $inscripcion_capacitacion_disponible ? '' : ' disabled'; ?>"
                               <?php if ($ctaHref !== '' && $inscripcion_capacitacion_disponible): ?>
                                   href="<?php echo htmlspecialchars($ctaHref, ENT_QUOTES, 'UTF-8'); ?>"
                               <?php else: ?>
                                   role="button" aria-disabled="true"
                               <?php endif; ?>
                               data-base-url="<?php echo htmlspecialchars($ctaBaseUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                <i class="fas fa-user-plus me-2"></i>Inscribirse Ahora
                            </a>
                            <p class="enroll-button-note<?php echo $inscripcion_capacitacion_disponible ? ' d-none' : ''; ?>" id="enrollButtonNote">
                                <?php echo htmlspecialchars($capacitacion_inscripcion_motivo, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include('footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.content-wrapper, .details-card').forEach((el, i) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    el.style.transition = 'all .6s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, i * 200);
            });

            const modalidadSelect = document.getElementById('modalidadSelect');
            const priceValueEl = document.querySelector('[data-role="price-value"]');
            const priceNoteEl = document.querySelector('[data-role="price-note"]');
            const enrollButton = document.getElementById('enrollButton');
            const enrollNote = document.getElementById('enrollButtonNote');
            const modalidadSelectionEl = document.querySelector('[data-role="modalidad-selection"]');
            const modalidadPriceData = <?php echo json_encode($modalidadPricePayload, JSON_UNESCAPED_UNICODE); ?>;
            const generalPriceData = <?php echo json_encode($generalPricePayload, JSON_UNESCAPED_UNICODE); ?>;
            const noPriceNote = <?php echo json_encode($precioSinDefinirNote, JSON_UNESCAPED_UNICODE); ?>;
            const enrollBaseUrl = enrollButton ? enrollButton.dataset.baseUrl : '';
            const fallbackEnrollMessage = <?php echo json_encode($capacitacion_inscripcion_motivo, JSON_UNESCAPED_UNICODE); ?>;
            const modalidadSelectionFallback = <?php echo json_encode($modalidadSelectionFallback, JSON_UNESCAPED_UNICODE); ?>;
            const modalidadSelectionPrefix = <?php echo json_encode('Seleccionaste: ', JSON_UNESCAPED_UNICODE); ?>;

            const formatCurrency = (value, currency) => {
                if (typeof value !== 'number' || !isFinite(value)) {
                    return '—';
                }
                const formatter = new Intl.NumberFormat('es-AR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                return `${(currency || 'ARS').toUpperCase()} ${formatter.format(value)}`;
            };

            const formatNote = (data) => {
                if (!data) {
                    return noPriceNote;
                }
                if (data.vigente_desde) {
                    const parsed = new Date(data.vigente_desde.replace(' ', 'T'));
                    if (!Number.isNaN(parsed.getTime())) {
                        return `Vigente desde ${parsed.toLocaleString('es-AR', { hour12: false })}`;
                    }
                }
                return 'Precio vigente disponible en el sistema.';
            };

            const toggleEnrollButton = (enabled, href) => {
                if (!enrollButton) {
                    return;
                }
                if (enabled && href) {
                    enrollButton.classList.remove('disabled');
                    enrollButton.removeAttribute('aria-disabled');
                    enrollButton.setAttribute('href', href);
                    if (enrollNote) {
                        enrollNote.classList.add('d-none');
                    }
                } else {
                    enrollButton.classList.add('disabled');
                    enrollButton.removeAttribute('href');
                    enrollButton.setAttribute('aria-disabled', 'true');
                    if (enrollNote) {
                        enrollNote.classList.remove('d-none');
                        enrollNote.textContent = fallbackEnrollMessage;
                    }
                }
            };

            const updatePriceSummary = () => {
                let data = null;
                let selectedValue = '';

                if (modalidadSelect) {
                    selectedValue = modalidadSelect.value;
                    if (modalidadPriceData && modalidadPriceData[selectedValue] && modalidadPriceData[selectedValue].precio !== null) {
                        data = modalidadPriceData[selectedValue];
                    }
                    if (modalidadSelectionEl) {
                        let selectionLabel = modalidadSelectionFallback;
                        if (modalidadPriceData && modalidadPriceData[selectedValue] && modalidadPriceData[selectedValue].nombre) {
                            selectionLabel = modalidadSelectionPrefix + modalidadPriceData[selectedValue].nombre;
                        }
                        modalidadSelectionEl.textContent = selectionLabel;
                    }
                } else if (generalPriceData) {
                    data = generalPriceData;
                }

                if (priceValueEl) {
                    const value = data ? formatCurrency(data.precio, data.moneda) : '—';
                    priceValueEl.textContent = value;
                }
                if (priceNoteEl) {
                    priceNoteEl.textContent = formatNote(data);
                }

                if (!enrollBaseUrl) {
                    return;
                }
                if (modalidadSelect) {
                    if (data && data.precio !== null) {
                        const href = `${enrollBaseUrl}&modalidad=${encodeURIComponent(selectedValue)}`;
                        toggleEnrollButton(true, href);
                    } else {
                        toggleEnrollButton(false, null);
                    }
                } else if (data && data.precio !== null) {
                    toggleEnrollButton(true, enrollBaseUrl);
                } else {
                    toggleEnrollButton(false, null);
                }
            };

            if (modalidadSelect) {
                modalidadSelect.addEventListener('change', updatePriceSummary);
            }
            updatePriceSummary();
        });
    </script>
</body>

</html>