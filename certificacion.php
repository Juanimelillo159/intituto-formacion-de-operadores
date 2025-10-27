<?php
session_start();
require_once 'sbd.php';

// ===== Helpers de salida segura =====
function h(?string $v, string $fallback = ''): string
{
    $v = $v ?? '';
    $v = trim($v);
    return $v !== '' ? htmlspecialchars($v) : $fallback;
}
function p(?string $v, string $fallback = ''): string
{
    $v = $v ?? '';
    $v = trim($v);
    return $v !== '' ? nl2br(htmlspecialchars($v)) : $fallback;
}

function obtener_precio_vigente(PDO $con, int $cursoId, string $tipoCurso): ?array
{
    $sql = $con->prepare(
        "SELECT precio, moneda, vigente_desde
           FROM curso_precio_hist
          WHERE id_curso = :curso
            AND tipo_curso = :tipo
            AND vigente_desde <= NOW()
            AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
       ORDER BY vigente_desde DESC
          LIMIT 1"
    );
    $sql->execute([
        ':curso' => $cursoId,
        ':tipo' => $tipoCurso,
    ]);
    $row = $sql->fetch(PDO::FETCH_ASSOC) ?: null;
    $sql->closeCursor();

    return $row ?: null;
}

// ===== Parámetros =====
$id_certificacion = filter_input(INPUT_GET, 'id_certificacion', FILTER_VALIDATE_INT) ?: 0;
if ($id_certificacion <= 0 && isset($_GET['id_curso'])) {
    $id_certificacion = (int) $_GET['id_curso'];
}

$cert = null;            // fila de certificaciones (si existe)
$curso_fallback = null;  // fila de cursos para fallback
$modalidades = [];

// ===== 1) Intentar cargar desde tabla certificaciones =====
try {
    $sql_cert = $con->prepare(
        "SELECT 
        id_certificacion,
        nombre_certificacion,
        descripcion,
        requisitos_evaluacion,
        plazo,
        proceso,          -- opcional si existe
        alcance,          -- opcional si existe
        requisitos,       -- opcional si existe
        vigencia,         -- opcional si existe
        documentacion     -- opcional si existe
     FROM certificaciones
     WHERE id_certificacion = :id"
    );
    $sql_cert->execute([':id' => $id_certificacion]);
    $cert = $sql_cert->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    // Si no existe la tabla/columnas, seguimos con fallback a cursos
}

// ===== 2) Fallback: usar tabla cursos cuando no haya certificación específica =====
if (!$cert) {
    $sql_curso = $con->prepare(
        "SELECT 
        id_curso,
        nombre_curso,
        descripcion_curso,
        duracion,
        objetivos,
        cronograma,
        publico,
        programa,
        requisitos,
        observaciones,
        documentacion
     FROM cursos
     WHERE id_curso = :id"
    );
    $sql_curso->execute([':id' => $id_certificacion]);
    $curso_fallback = $sql_curso->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ===== Modalidades (vinculadas a curso) =====
if ($cert || $curso_fallback) {
    $id_ref = $curso_fallback['id_curso'] ?? $cert['id_certificacion'] ?? $id_certificacion;
    $sql_mods = $con->prepare(
        "SELECT m.id_modalidad AS modalidad_id, m.nombre_modalidad AS modalidad_nombre
     FROM curso_modalidad cm
     JOIN modalidades m ON cm.id_modalidad = m.id_modalidad
     WHERE cm.id_curso = :id"
    );
    $sql_mods->execute([':id' => $id_ref]);
    $modalidades = $sql_mods->fetchAll(PDO::FETCH_ASSOC);
}
$modalidad_nombres = array_map(fn($v) => htmlspecialchars($v['modalidad_nombre']), $modalidades);
$modalidad_nombres_str = implode(' - ', $modalidad_nombres);

// ===== Meta dinámicos =====
$nombre_base = $cert['nombre_certificacion'] ?? $curso_fallback['nombre_curso'] ?? '';
$page_title = (h($nombre_base) ?: 'Certificación') . ' | Instituto de Formación';
$page_description = h($cert['descripcion'] ?? $curso_fallback['descripcion_curso'] ?? '') ?: 'Página de certificación del Instituto de Formación de Operadores';

// ===== Campos a mostrar =====
$certNombre      = h($cert['nombre_certificacion'] ?? $curso_fallback['nombre_curso'] ?? 'Certificación');
$certDescripcion = p($cert['descripcion'] ?? $curso_fallback['descripcion_curso'] ?? 'Pronto publicaremos la información detallada de esta certificación.');
$certRequisitos  = p($cert['requisitos_evaluacion'] ?? $cert['requisitos'] ?? $curso_fallback['requisitos'] ?? 'Revisaremos tu perfil y la documentación para confirmar los requisitos.');
$certDuracion    = h($cert['plazo'] ?? $curso_fallback['duracion'] ?? 'A definir');

// Acordeón dinámico: intenta usar campos específicos de certificaciones y sino mapea a los de cursos
$accProceso = p($cert['proceso'] ?? $curso_fallback['cronograma'] ?? 'Información no disponible.');
$accAlcance = p($cert['alcance'] ?? $curso_fallback['publico'] ?? 'Información no disponible.');
$accReqs    = p($cert['requisitos'] ?? $curso_fallback['requisitos'] ?? 'Información no disponible.');
$accVig     = p($cert['vigencia'] ?? $curso_fallback['observaciones'] ?? 'Información no disponible.');
$accDocs    = p($cert['documentacion'] ?? $curso_fallback['documentacion'] ?? 'Información no disponible.');

$enlaceCheckoutId = (int)($curso_fallback['id_curso'] ?? $cert['id_certificacion'] ?? $id_certificacion);

$precio_certificacion = null;
if ($enlaceCheckoutId > 0) {
    $precio_certificacion = obtener_precio_vigente($con, $enlaceCheckoutId, 'certificacion');
}
$solicitud_certificacion_disponible = $precio_certificacion !== null && $enlaceCheckoutId > 0;

if (!$cert && !$curso_fallback) {
    http_response_code(404);
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php $page_styles = '<link rel="stylesheet" href="assets/styles/style_certificacion.css">'; ?>
    <?php include 'head.php'; ?>
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $page_description; ?>">
</head>

<body class="certificaciones">
    <?php include 'nav.php'; ?>

    <div class="container py-3">
        <a href="index.php" class="back-button"><i class="fas fa-arrow-left"></i><span>Volver al inicio</span></a>
    </div>

    <div class="container my-4">
        <div class="row">
            <div class="col-lg-8">
                <div class="content-wrapper">
                    <div class="course-header">
                        <h1 class="course-title"><i class="fa-solid fa-award me-2"></i><?php echo $certNombre; ?></h1>
                        <p class="course-subtitle">Información clave de esta certificación</p>
                    </div>
                    <div class="course-content">
                        <h2 class="section-title"><i class="fa-solid fa-shield-check"></i>Descripción de la Certificación</h2>
                        <div class="course-description">
                            <p class="mb-0"><?php echo $certDescripcion; ?></p>
                        </div>

                        <h3 class="section-title"><i class="fa-solid fa-list-check"></i>Requisitos de Evaluación</h3>
                        <div class="objectives-list">
                            <p class="mb-0"><?php echo $certRequisitos; ?></p>
                        </div>

                        <!-- Acordeón (uno a la vez) -->
                        <h3 class="section-title mt-4"><i class="fas fa-folder-tree"></i>Más información</h3>
                        <div class="accordion curso-accordion" id="certAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hProceso">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#cProceso" aria-expanded="true" aria-controls="cProceso">Proceso de Certificación</button>
                                </h2>
                                <div id="cProceso" class="accordion-collapse collapse show" aria-labelledby="hProceso" data-bs-parent="#certAccordion">
                                    <div class="accordion-body"><?php echo $accProceso; ?></div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hAlcance">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cAlcance" aria-expanded="false" aria-controls="cAlcance">Alcance</button>
                                </h2>
                                <div id="cAlcance" class="accordion-collapse collapse" aria-labelledby="hAlcance" data-bs-parent="#certAccordion">
                                    <div class="accordion-body"><?php echo $accAlcance; ?></div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hReqs">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cReqs" aria-expanded="false" aria-controls="cReqs">Requisitos</button>
                                </h2>
                                <div id="cReqs" class="accordion-collapse collapse" aria-labelledby="hReqs" data-bs-parent="#certAccordion">
                                    <div class="accordion-body"><?php echo $accReqs; ?></div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hVigencia">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cVigencia" aria-expanded="false" aria-controls="cVigencia">Vigencia y Renovación</button>
                                </h2>
                                <div id="cVigencia" class="accordion-collapse collapse" aria-labelledby="hVigencia" data-bs-parent="#certAccordion">
                                    <div class="accordion-body"><?php echo $accVig; ?></div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="hDocs">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cDocs" aria-expanded="false" aria-controls="cDocs">Documentación</button>
                                </h2>
                                <div id="cDocs" class="accordion-collapse collapse" aria-labelledby="hDocs" data-bs-parent="#certAccordion">
                                    <div class="accordion-body"><?php echo $accDocs; ?></div>
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
                        <h3 class="mb-0"><i class="fa-solid fa-certificate me-2"></i>Información de la Certificación</h3>
                    </div>
                    <div class="details-body">
                        <div class="price-summary">
                            <div class="price-summary-title"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Inversión</div>
                            <div class="price-summary-list">
                                <div class="price-summary-item">
                                    <div>
                                        <div class="price-summary-label">Certificación</div>
                                        <div class="price-summary-note">
                                            <?php if ($precio_certificacion): ?>
                                                <?php if (!empty($precio_certificacion['vigente_desde'])): ?>
                                                    Vigente desde <?php echo date('d/m/Y H:i', strtotime($precio_certificacion['vigente_desde'])); ?>
                                                <?php else: ?>
                                                    Precio vigente disponible en el sistema.
                                                <?php endif; ?>
                                            <?php else: ?>
                                                Precio a confirmar con el equipo comercial.
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="price-summary-value">
                                        <?php if ($precio_certificacion): ?>
                                            <?php echo strtoupper($precio_certificacion['moneda'] ?? 'ARS'); ?> <?php echo number_format((float)$precio_certificacion['precio'], 2, ',', '.'); ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fa-regular fa-clock"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Duración / Plazo</div>
                                <div class="detail-value"><?php echo $certDuracion; ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fa-solid fa-laptop-file"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Modalidad</div>
                                <div class="detail-value"><?php echo $modalidad_nombres_str ?: 'Online / Presencial'; ?></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon"><i class="fa-solid fa-shield"></i></div>
                            <div class="detail-content">
                                <div class="detail-label">Emisión</div>
                                <div class="detail-value"><span class="text-success"><i class="fas fa-check-circle me-1"></i>Certificado oficial</span></div>
                            </div>
                        </div>

                        <?php if ($cert || $curso_fallback): ?>
                            <?php if ($solicitud_certificacion_disponible): ?>
                                <a class="enroll-button" href="checkout/checkout.php?id_certificacion=<?php echo $enlaceCheckoutId; ?>&tipo=certificacion">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Solicitar certificación
                                </a>
                            <?php else: ?>
                                <button class="enroll-button" type="button" disabled aria-disabled="true">
                                    <i class="fa-solid fa-paper-plane me-2"></i> Solicitar certificación
                                </button>
                                <p class="enroll-button-note">Contactá al equipo comercial para obtener el precio y continuar con la solicitud.</p>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning mt-3" role="alert">
                                <div class="d-flex align-items-start gap-2">
                                    <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                                    <div>
                                        <strong>No encontramos la certificación seleccionada.</strong>
                                        <div class="small mt-1">Volvé al catálogo e intentá nuevamente.</div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <?php include 'footer.php'; ?>

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
        });
    </script>
</body>

</html>