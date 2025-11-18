<?php
session_start();
require_once '../sbd.php';

function checkout_get_session_user_id(): int
{
    if (isset($_SESSION['id_usuario']) && is_numeric($_SESSION['id_usuario'])) {
        $id = (int)$_SESSION['id_usuario'];
        if ($id > 0) {
            return $id;
        }
    }

    if (!isset($_SESSION['usuario'])) {
        return 0;
    }

    $sessionUsuario = $_SESSION['usuario'];

    if (is_numeric($sessionUsuario)) {
        $id = (int)$sessionUsuario;
        return $id > 0 ? $id : 0;
    }

    if (is_array($sessionUsuario) && isset($sessionUsuario['id_usuario']) && is_numeric($sessionUsuario['id_usuario'])) {
        $id = (int)$sessionUsuario['id_usuario'];
        return $id > 0 ? $id : 0;
    }

    return 0;
}

$currentUserId = checkout_get_session_user_id();
if ($currentUserId <= 0) {
    $_SESSION['login_mensaje'] = 'Debés iniciar sesión para completar tu inscripción.';
    $_SESSION['login_tipo'] = 'warning';
    header('Location: ../login.php');
    exit;
}

$page_title = "Checkout | Instituto de Formación";
$page_description = "Completá tu inscripción en tres pasos.";

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
if ($id_curso <= 0 && isset($_GET['id_capacitacion'])) {
    $id_curso = (int)$_GET['id_capacitacion'];
}
if ($id_curso <= 0 && isset($_GET['id_certificacion'])) {
    $id_curso = (int)$_GET['id_certificacion'];
}
$selectedModalidadId = isset($_GET['modalidad']) ? max(0, (int)$_GET['modalidad']) : 0;
$tipo_checkout = isset($_GET['tipo']) ? strtolower(trim((string)$_GET['tipo'])) : '';
if ($tipo_checkout === '' && isset($_GET['id_capacitacion'])) {
    $tipo_checkout = 'capacitacion';
} elseif ($tipo_checkout === '' && isset($_GET['id_certificacion'])) {
    $tipo_checkout = 'certificacion';
}

$certificacionRegistroId = isset($_GET['certificacion_registro'])
    ? (int)$_GET['certificacion_registro']
    : 0;
$prefetchedCertificacion = null;

$soloTransferenciaRaw = strtolower(trim((string)($_GET['solo_transferencia'] ?? '')));
$soloTransferencia = in_array($soloTransferenciaRaw, ['1', 'true', 'si', 'sí', 'on', 'yes'], true);
$retomarRegistroId = isset($_GET['retomar']) ? (int)$_GET['retomar'] : 0;
$capacitacionRetryData = null;

if ($soloTransferencia && $tipo_checkout === 'capacitacion' && $retomarRegistroId > 0) {
    $retryStmt = $con->prepare('SELECT * FROM checkout_capacitaciones WHERE id_capacitacion = :id LIMIT 1');
    $retryStmt->execute([':id' => $retomarRegistroId]);
    $retryRow = $retryStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($retryRow && ($currentUserId <= 0 || (int)($retryRow['creado_por'] ?? 0) === $currentUserId)) {
        $estadoRetry = (int)($retryRow['id_estado'] ?? 0);
        if ($estadoRetry !== 3) {
            $capacitacionRetryData = $retryRow;
            if ($id_curso <= 0) {
                $id_curso = (int)($retryRow['id_curso'] ?? 0);
            }
        } else {
            $soloTransferencia = false;
            $retomarRegistroId = 0;
        }
    } else {
        $soloTransferencia = false;
        $retomarRegistroId = 0;
    }
}
if (!in_array($tipo_checkout, ['curso', 'capacitacion', 'certificacion'], true)) {
    $tipo_checkout = 'curso';
}

if ($certificacionRegistroId > 0) {
    $prefetchCertStmt = $con->prepare('SELECT * FROM checkout_certificaciones WHERE id_certificacion = :id LIMIT 1');
    $prefetchCertStmt->execute([':id' => $certificacionRegistroId]);
    $prefetchedRow = $prefetchCertStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($prefetchedRow && (int)($prefetchedRow['creado_por'] ?? 0) === $currentUserId) {
        $prefetchedCertificacion = $prefetchedRow;
        if ($id_curso <= 0) {
            $id_curso = (int)($prefetchedRow['id_curso'] ?? 0);
        }
        $tipo_checkout = 'certificacion';
    }
}

$back_link_anchor = '#cursos';
$back_link_text = 'Volver al listado de cursos';
if ($tipo_checkout === 'capacitacion') {
    $back_link_anchor = '#servicios-capacitacion';
    $back_link_text = 'Volver al listado de capacitaciones';
} elseif ($tipo_checkout === 'certificacion') {
    $back_link_text = 'Volver al listado de certificaciones';
}

$st = $con->prepare("SELECT * FROM cursos WHERE id_curso = :id");
$st->execute([':id' => $id_curso]);
$curso = $st->fetch(PDO::FETCH_ASSOC);

$cursoNombre = null;
$cursoDescripcion = null;
$modalidadesCurso = [];
$modalidadNombreSeleccionada = null;
if ($curso) {
    $cursoNombre = (string)($curso['nombre_certificacion'] ?? $curso['nombre_curso'] ?? '');
    $cursoDescripcion = (string)($curso['descripcion'] ?? $curso['descripcion_curso'] ?? '');

    try {
        $modsStmt = $con->prepare(
            'SELECT m.id_modalidad, m.nombre_modalidad
               FROM curso_modalidad cm
               JOIN modalidades m ON m.id_modalidad = cm.id_modalidad
              WHERE cm.id_curso = :curso
           ORDER BY m.nombre_modalidad ASC'
        );
        $modsStmt->execute([':curso' => $id_curso]);
        $modalidadesCurso = $modsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $modalidadException) {
        $modalidadesCurso = [];
    }

    if (!empty($modalidadesCurso)) {
        $modalidadIdsDisponibles = array_map(static fn($m) => (int)($m['id_modalidad'] ?? 0), $modalidadesCurso);
        $modalidadIdsDisponibles = array_values(array_filter($modalidadIdsDisponibles, static fn($v) => $v > 0));
        if (!empty($modalidadIdsDisponibles)) {
            if ($selectedModalidadId <= 0 || !in_array($selectedModalidadId, $modalidadIdsDisponibles, true)) {
                $selectedModalidadId = $modalidadIdsDisponibles[0];
            }
        } else {
            $selectedModalidadId = 0;
        }
    } else {
        $selectedModalidadId = 0;
    }

    if ($selectedModalidadId > 0) {
        foreach ($modalidadesCurso as $modalidadRow) {
            if ((int)($modalidadRow['id_modalidad'] ?? 0) === $selectedModalidadId) {
                $modalidadNombreSeleccionada = (string)($modalidadRow['nombre_modalidad'] ?? '');
                break;
            }
        }
    }
}

$mercadoPagoHabilitado = site_settings_is_mp_enabled($site_settings);
$soloTransferenciaConfiguracion = !$mercadoPagoHabilitado;
$capacitacionesGlobalHabilitadas = site_settings_sales_enabled($site_settings, 'capacitacion');
$certificacionesGlobalHabilitadas = site_settings_sales_enabled($site_settings, 'certificacion');
$checkoutBloqueadoPorConfiguracion = false;
$checkoutBloqueadoTitulo = '';
$checkoutBloqueadoMensaje = '';

$ventaCapacitacionPermitida = $capacitacionesGlobalHabilitadas;
$ventaCertificacionPermitida = $certificacionesGlobalHabilitadas;

if ($id_curso > 0) {
    $ventaCapacitacionPermitida = $ventaCapacitacionPermitida && site_settings_sales_enabled($site_settings, 'capacitacion', $id_curso);
    $ventaCertificacionPermitida = $ventaCertificacionPermitida && site_settings_sales_enabled($site_settings, 'certificacion', $id_curso);
}

if (in_array($tipo_checkout, ['curso', 'capacitacion'], true) && !$ventaCapacitacionPermitida) {
    $checkoutBloqueadoPorConfiguracion = true;
    $checkoutBloqueadoTitulo = 'Inscripción no disponible';
    $checkoutBloqueadoMensaje = 'Las inscripciones online para esta capacitación están temporalmente deshabilitadas.';
} elseif ($tipo_checkout === 'certificacion' && !$ventaCertificacionPermitida) {
    $checkoutBloqueadoPorConfiguracion = true;
    $checkoutBloqueadoTitulo = 'Solicitud no disponible';
    $checkoutBloqueadoMensaje = 'Las solicitudes online para esta certificación están temporalmente deshabilitadas.';
}

$capacitacionBloqueada = false;
$capacitacionBloqueadaEstado = null;
$capacitacionBloqueadaMensaje = null;
$capacitacionBloqueadaLabel = null;
$capacitacionRegistroId = 0;
$skipCapacitacionRedirect = false;

$capacitacionIntent = $tipo_checkout === 'capacitacion';
if (!$capacitacionIntent && isset($_GET['tipo'])) {
    $tipoQuery = strtolower(trim((string)$_GET['tipo']));
    $capacitacionIntent = in_array($tipoQuery, ['capacitacion', 'capacitaciones'], true);
}
if (!$capacitacionIntent && isset($_GET['id_capacitacion'])) {
    $capacitacionIntent = true;
}

if ($curso && $currentUserId > 0 && $tipo_checkout !== 'certificacion') {
    $capDuplicadaStmt = $con->prepare('
        SELECT id_capacitacion, id_estado, creado_en
          FROM checkout_capacitaciones
         WHERE id_curso = :curso
           AND creado_por = :usuario
     ORDER BY id_capacitacion DESC
         LIMIT 1
    ');
    $capDuplicadaStmt->execute([
        ':curso' => $id_curso,
        ':usuario' => $currentUserId,
    ]);
    $capDuplicadaRow = $capDuplicadaStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($capDuplicadaRow) {
        $capEstado = (int)($capDuplicadaRow['id_estado'] ?? 0);
        $capacitacionRegistroId = (int)($capDuplicadaRow['id_capacitacion'] ?? 0);
        $allowTransferRetry = $soloTransferencia
            && $retomarRegistroId > 0
            && $capacitacionRegistroId === $retomarRegistroId
            && in_array($capEstado, [1, 2, 4], true);

        if ($allowTransferRetry) {
            $skipCapacitacionRedirect = true;
            $capacitacionBloqueada = false;
            $capacitacionBloqueadaEstado = null;
            $capacitacionBloqueadaMensaje = null;
            $capacitacionBloqueadaLabel = null;
        } elseif (in_array($capEstado, [1, 2, 3], true)) {
            $capacitacionBloqueada = true;
            $capacitacionBloqueadaEstado = $capEstado;
            $capacitacionBloqueadaLabel = checkout_capacitacion_estado_label($capEstado);
            $capacitacionBloqueadaMensaje = match ($capEstado) {
                3 => 'Ya registramos y aprobamos tu pago para esta capacitación. Encontrala en la sección Mis cursos.',
                2 => 'Ya tenés una inscripción activa para esta capacitación. Consultá las novedades desde Mis cursos.',
                default => 'Ya registraste una inscripción para esta capacitación. Revisá su estado en Mis cursos antes de generar una nueva.',
            };

            if ($capacitacionRegistroId > 0) {
                $redirectParams = [
                    'tipo' => 'capacitacion',
                    'orden' => $capacitacionRegistroId,
                ];
                $pagoId = checkout_find_latest_pago_for_capacitacion($con, $capacitacionRegistroId);
                if ($pagoId !== null && $pagoId > 0) {
                    $redirectParams['pago'] = $pagoId;
                }

                if ($capacitacionIntent || $tipo_checkout !== 'curso') {
                    header('Location: gracias.php?' . http_build_query($redirectParams));
                    exit;
                }
            }
        }
    }
}

$soloTransferenciaForzado = $soloTransferenciaConfiguracion || $soloTransferencia;
$transferenciaMensajeConfiguracion = 'Mercado Pago está temporalmente deshabilitado. Completá tu inscripción adjuntando el comprobante de transferencia.';

function checkout_obtener_precio_vigente(PDO $con, int $cursoId, string $tipoCurso, ?int $modalidadId = null): ?array
{
    static $stmtPorModalidad = null;
    static $stmtGeneral = null;

    if ($modalidadId !== null) {
        if ($stmtPorModalidad === null) {
            $stmtPorModalidad = $con->prepare("
                SELECT precio, moneda, vigente_desde, id_modalidad
                  FROM curso_precio_hist
                 WHERE id_curso = :curso
                   AND tipo_curso = :tipo
                   AND id_modalidad = :modalidad
                   AND vigente_desde <= NOW()
                   AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
             ORDER BY vigente_desde DESC
                LIMIT 1
            ");
        }

        $stmtPorModalidad->bindValue(':curso', $cursoId, PDO::PARAM_INT);
        $stmtPorModalidad->bindValue(':tipo', $tipoCurso, PDO::PARAM_STR);
        $stmtPorModalidad->bindValue(':modalidad', $modalidadId, PDO::PARAM_INT);
        $stmtPorModalidad->execute();
        $rowModalidad = $stmtPorModalidad->fetch(PDO::FETCH_ASSOC) ?: null;
        $stmtPorModalidad->closeCursor();
        if ($rowModalidad) {
            return $rowModalidad;
        }
    }

    if ($stmtGeneral === null) {
        $stmtGeneral = $con->prepare("
            SELECT precio, moneda, vigente_desde, id_modalidad
              FROM curso_precio_hist
             WHERE id_curso = :curso
               AND tipo_curso = :tipo
               AND id_modalidad IS NULL
               AND vigente_desde <= NOW()
               AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
          ORDER BY vigente_desde DESC
             LIMIT 1
        ");
    }

    $stmtGeneral->bindValue(':curso', $cursoId, PDO::PARAM_INT);
    $stmtGeneral->bindValue(':tipo', $tipoCurso, PDO::PARAM_STR);
    $stmtGeneral->execute();
    $row = $stmtGeneral->fetch(PDO::FETCH_ASSOC) ?: null;
    $stmtGeneral->closeCursor();

    return $row ?: null;
}

$precio_vigente = null;
$precio_capacitacion = null;
$precio_certificacion = null;
$tipoPrecioCheckout = $tipo_checkout === 'certificacion' ? 'certificacion' : 'capacitacion';
if ($curso) {
    $modalidadIdForPrice = $selectedModalidadId > 0 ? $selectedModalidadId : null;
    $precio_capacitacion = checkout_obtener_precio_vigente($con, $id_curso, 'capacitacion', $modalidadIdForPrice);
    $precio_certificacion = checkout_obtener_precio_vigente($con, $id_curso, 'certificacion');

    $precio_vigente = $tipoPrecioCheckout === 'certificacion' ? $precio_certificacion : $precio_capacitacion;
    if (!$precio_vigente && $tipoPrecioCheckout !== 'capacitacion') {
        $precio_vigente = $precio_capacitacion;
    }
}

if ($capacitacionRetryData && $tipo_checkout === 'capacitacion') {
    $modalidadRetryId = (int)($capacitacionRetryData['id_modalidad'] ?? 0);
    if ($modalidadRetryId > 0) {
        $selectedModalidadId = $modalidadRetryId;
        $modalidadNombreSeleccionada = null;
        foreach ($modalidadesCurso as $modalidadRow) {
            if ((int)($modalidadRow['id_modalidad'] ?? 0) === $selectedModalidadId) {
                $modalidadNombreSeleccionada = (string)($modalidadRow['nombre_modalidad'] ?? '');
                break;
            }
        }
    }
    $retryPrecio = isset($capacitacionRetryData['precio_total']) ? (float)$capacitacionRetryData['precio_total'] : 0.0;
    if ($retryPrecio > 0) {
        $precio_vigente = [
            'precio' => $retryPrecio,
            'moneda' => $capacitacionRetryData['moneda'] ?? ($precio_vigente['moneda'] ?? 'ARS'),
            'vigente_desde' => $capacitacionRetryData['creado_en'] ?? null,
        ];
    }
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function checkout_certificacion_estado_label(?int $estado): string
{
    return match ($estado) {
        2 => 'Aprobada',
        3 => 'Pago registrado',
        4 => 'Rechazada',
        default => 'En revisión',
    };
}

function checkout_capacitacion_estado_label(?int $estado): string
{
    return match ($estado) {
        2 => 'Confirmada',
        3 => 'Pago aprobado',
        4 => 'Rechazada',
        default => 'Pendiente',
    };
}

function checkout_find_latest_pago_for_capacitacion(PDO $con, int $capacitacionId): ?int
{
    static $stmt = null;
    if ($stmt === null) {
        $stmt = $con->prepare('
            SELECT id_pago
              FROM checkout_pagos
             WHERE id_capacitacion = :capacitacion
          ORDER BY id_pago DESC
             LIMIT 1
        ');
    }

    $stmt->execute([':capacitacion' => $capacitacionId]);
    $pagoId = $stmt->fetchColumn();
    $stmt->closeCursor();

    return $pagoId !== false ? (int)$pagoId : null;
}

$certificacionData = null;
$certificacionEstado = null;
$certificacionId = 0;
$certificacionPuedePagar = false;
$certificacionPagado = false;
$certificacionPdfUrl = null;
$certificacionAllowSubmit = true;
$certificacionSubmitLabel = 'Enviar solicitud';

if ($tipo_checkout === 'certificacion' && $curso) {
    if ($currentUserId > 0) {
        if ($prefetchedCertificacion && (int)($prefetchedCertificacion['id_curso'] ?? 0) === $id_curso) {
            $certificacionData = $prefetchedCertificacion;
        } elseif ($certificacionRegistroId > 0) {
            $certStmt = $con->prepare('
                SELECT cc.*
                  FROM checkout_certificaciones cc
                 WHERE cc.id_certificacion = :id
                   AND cc.creado_por = :usuario
                 LIMIT 1
            ');
            $certStmt->execute([
                ':id' => $certificacionRegistroId,
                ':usuario' => $currentUserId,
            ]);
            $certificacionData = $certStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } else {
            $certStmt = $con->prepare('
                SELECT cc.*
                  FROM checkout_certificaciones cc
                 WHERE cc.id_curso = :curso
                   AND cc.creado_por = :usuario
              ORDER BY cc.id_certificacion DESC
                 LIMIT 1
            ');
            $certStmt->execute([
                ':curso' => $id_curso,
                ':usuario' => $currentUserId,
            ]);
            $certificacionData = $certStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    }

    if ($certificacionData) {
        $certificacionId = (int)$certificacionData['id_certificacion'];
        $certificacionEstado = (int)$certificacionData['id_estado'];
        $llegoDesdeRegistro = false;
        if ($certificacionRegistroId > 0 && $certificacionRegistroId === $certificacionId) {
            $llegoDesdeRegistro = true;
        } elseif ($prefetchedCertificacion && (int)($prefetchedCertificacion['id_certificacion'] ?? 0) === $certificacionId) {
            $llegoDesdeRegistro = true;
        }

        if ($certificacionId > 0) {
            $shouldRedirectGracias = false;
            if ($certificacionEstado === 3 || $certificacionEstado === 1) {
                $shouldRedirectGracias = true;
            } elseif ($certificacionEstado === 2 && !$llegoDesdeRegistro) {
                $shouldRedirectGracias = true;
            }

            if ($shouldRedirectGracias) {
                header('Location: gracias_certificacion.php?' . http_build_query(['certificacion' => $certificacionId]));
                exit;
            }
        }

        $certificacionPuedePagar = ($certificacionEstado === 2);
        $certificacionPagado = ($certificacionEstado === 3);
        $certificacionAllowSubmit = ($certificacionEstado === 4);
        if ($certificacionEstado === 4) {
            $certificacionSubmitLabel = 'Reenviar solicitud';
        } elseif ($certificacionEstado === 1) {
            $certificacionSubmitLabel = 'Solicitud enviada';
        } elseif ($certificacionEstado === 2) {
            $certificacionSubmitLabel = 'Documentación aprobada';
        } elseif ($certificacionEstado === 3) {
            $certificacionSubmitLabel = 'Solicitud completada';
        }
        if (!empty($certificacionData['pdf_path'])) {
            $certificacionPdfUrl = '../' . ltrim((string)$certificacionData['pdf_path'], '/');
        }
    } else {
        $certificacionAllowSubmit = true;
        $certificacionSubmitLabel = 'Enviar solicitud';
    }
} else {
    $certificacionAllowSubmit = false;
}

$sessionUsuario = [];
if (isset($_SESSION['usuario']) && is_array($_SESSION['usuario'])) {
    $sessionUsuario = $_SESSION['usuario'];
}

$usuarioPerfil = $sessionUsuario;
if ($currentUserId > 0) {
    $usuarioPerfil['id_usuario'] = $currentUserId;
    $camposPerfil = ['nombre', 'apellido', 'email', 'telefono'];
    $faltaPerfil = false;
    foreach ($camposPerfil as $campoPerfil) {
        if (!isset($usuarioPerfil[$campoPerfil]) || trim((string)$usuarioPerfil[$campoPerfil]) === '') {
            $faltaPerfil = true;
            break;
        }
    }

    if ($faltaPerfil) {
        $perfilStmt = $con->prepare('
            SELECT nombre, apellido, email, telefono
              FROM usuarios
             WHERE id_usuario = :id
             LIMIT 1
        ');
        $perfilStmt->execute([':id' => $currentUserId]);
        $perfilRow = $perfilStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if ($perfilRow) {
            $usuarioPerfil = array_merge($usuarioPerfil, $perfilRow);
        }
    }
}

$prefillNombre = (string)($certificacionData['nombre'] ?? ($usuarioPerfil['nombre'] ?? ($sessionUsuario['nombre'] ?? '')));
$prefillApellido = (string)($certificacionData['apellido'] ?? ($usuarioPerfil['apellido'] ?? ($sessionUsuario['apellido'] ?? '')));
$prefillEmail = (string)($certificacionData['email'] ?? ($usuarioPerfil['email'] ?? ($sessionUsuario['email'] ?? '')));
$prefillTelefono = (string)($certificacionData['telefono'] ?? ($usuarioPerfil['telefono'] ?? ($sessionUsuario['telefono'] ?? '')));
$prefillDni = (string)($certificacionData['dni'] ?? ($usuarioPerfil['dni'] ?? ''));
$prefillDireccion = (string)($certificacionData['direccion'] ?? ($usuarioPerfil['direccion'] ?? ''));
$prefillCiudad = (string)($certificacionData['ciudad'] ?? ($usuarioPerfil['ciudad'] ?? ''));
$prefillProvincia = (string)($certificacionData['provincia'] ?? ($usuarioPerfil['provincia'] ?? ''));
$prefillPais = (string)($certificacionData['pais'] ?? ($usuarioPerfil['pais'] ?? 'Argentina'));
if ($tipo_checkout !== 'certificacion') {
    if ($capacitacionRetryData) {
        $prefillNombre = (string)($capacitacionRetryData['nombre'] ?? '');
        $prefillApellido = (string)($capacitacionRetryData['apellido'] ?? '');
        $prefillEmail = (string)($capacitacionRetryData['email'] ?? '');
        $prefillTelefono = (string)($capacitacionRetryData['telefono'] ?? '');
        $prefillDni = (string)($capacitacionRetryData['dni'] ?? '');
        $prefillDireccion = (string)($capacitacionRetryData['direccion'] ?? '');
        $prefillCiudad = (string)($capacitacionRetryData['ciudad'] ?? '');
        $prefillProvincia = (string)($capacitacionRetryData['provincia'] ?? '');
        $prefillPais = (string)($capacitacionRetryData['pais'] ?? 'Argentina');
    } else {
        $prefillNombre = '';
        $prefillApellido = '';
        $prefillEmail = '';
        $prefillTelefono = '';
        $prefillDni = '';
        $prefillDireccion = '';
        $prefillCiudad = '';
        $prefillProvincia = '';
        $prefillPais = 'Argentina';
    }
}

$shouldCheckTerms = (!empty($certificacionData) && (int)$certificacionData['acepta_tyc'] === 1);
if ($capacitacionRetryData && (int)($capacitacionRetryData['acepta_tyc'] ?? 0) === 1) {
    $shouldCheckTerms = true;
}

$certificacionFlashSuccess = $_SESSION['certificacion_success'] ?? null;
$certificacionFlashError = $_SESSION['certificacion_error'] ?? null;
unset($_SESSION['certificacion_success'], $_SESSION['certificacion_error']);

$certificacionSuccessMessage = null;
$certificacionSuccessEstadoLabel = null;
if ($certificacionFlashSuccess !== null) {
    if (is_array($certificacionFlashSuccess)) {
        $certificacionSuccessMessage = (string)($certificacionFlashSuccess['message'] ?? 'Solicitud enviada correctamente.');
        $certificacionSuccessEstadoLabel = isset($certificacionFlashSuccess['estado'])
            ? checkout_certificacion_estado_label((int)$certificacionFlashSuccess['estado'])
            : null;
    } else {
        $certificacionSuccessMessage = (string)$certificacionFlashSuccess;
    }
}

$certificacionShowSuccessAlert = ($certificacionSuccessMessage !== null);
if ($certificacionShowSuccessAlert && $certificacionEstado !== null) {
    if (in_array($certificacionEstado, [2, 3], true)) {
        $certificacionShowSuccessAlert = false;
    }
}

$certificacionErrorMessage = null;
if ($certificacionFlashError !== null) {
    $certificacionErrorMessage = is_array($certificacionFlashError)
        ? (string)($certificacionFlashError['message'] ?? 'No pudimos procesar la solicitud de certificación.')
        : (string)$certificacionFlashError;
}

$flash_success = $_SESSION['checkout_success'] ?? null;
$flash_error   = $_SESSION['checkout_error'] ?? null;
unset($_SESSION['checkout_success'], $_SESSION['checkout_error']);

$checkoutSubtitle = 'Seguí los pasos para reservar tu lugar en la capacitación elegida.';
$notFoundMessage = 'No pudimos encontrar la capacitación seleccionada. Volvé al listado e intentá nuevamente.';
$stepHelper = 'Detalles del curso';
$summaryTitle = 'Resumen del curso';
$priceHelper = 'El equipo se pondrá en contacto para coordinar disponibilidad, medios de pago y comenzar tu proceso.';
if ($tipo_checkout === 'curso') {
    $checkoutSubtitle = 'Seguí los pasos para confirmar tu inscripción.';
} elseif ($tipo_checkout === 'certificacion') {
    $checkoutSubtitle = 'Completá la solicitud y el pago para finalizar tu certificación.';
    $notFoundMessage = 'No pudimos encontrar la certificación seleccionada. Volvé al listado e intentá nuevamente.';
    $stepHelper = 'Detalles de la certificación';
    $summaryTitle = 'Resumen de la certificación';
    $priceHelper = 'Revisaremos la documentación y coordinaremos los pasos para avanzar con la certificación.';
}
?>
<!DOCTYPE html>
<html lang="es">
<?php
$asset_base_path = '../';
$base_path = '../';
$page_styles = '<link rel="stylesheet" href="../checkout/css/style.css">';
include '../head.php';
?>

<body class="checkout-body">
    <?php include '../nav.php'; ?>

    <main class="checkout-main">
        <div class="container">
            <div class="mb-4">
                <a class="back-link" href="<?php echo htmlspecialchars($base_path . 'index.php' . $back_link_anchor, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo htmlspecialchars($back_link_text, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="checkout-card">
                        <div class="checkout-header">
                            <h1>Finalizá tu inscripción</h1>
                            <p><?php echo h($checkoutSubtitle); ?></p>
                            <?php if ($curso && $cursoNombre !== null && $cursoNombre !== ''): ?>
                                <div class="checkout-course-name">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?php echo h($cursoNombre); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$curso): ?>
                            <div class="checkout-content">
                                <div class="alert alert-danger checkout-alert mb-0" role="alert">
                                    <?php echo h($notFoundMessage); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php if ($checkoutBloqueadoPorConfiguracion): ?>
                                <div class="checkout-content">
                                    <div class="alert alert-warning checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-circle-info mt-1"></i>
                                            <div>
                                                <strong><?php echo h($checkoutBloqueadoTitulo !== '' ? $checkoutBloqueadoTitulo : 'Operación no disponible'); ?></strong>
                                                <div class="small mt-1"><?php echo h($checkoutBloqueadoMensaje); ?></div>
                                                <div class="small text-muted mt-2">Si necesitás asistencia, comunicate con nuestro equipo de soporte.</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php if ($tipo_checkout === 'capacitacion' && $capacitacionBloqueada && $capacitacionBloqueadaMensaje !== null): ?>
                                    <div class="checkout-content">
                                        <div class="alert alert-warning checkout-alert" role="alert">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fas fa-info-circle mt-1"></i>
                                                <div>
                                                    <strong>Ya registraste esta capacitación</strong>
                                                    <div class="small mt-1"><?php echo h($capacitacionBloqueadaMensaje); ?></div>
                                                    <?php if ($capacitacionBloqueadaLabel !== null): ?>
                                                        <div class="small text-muted mt-2">Estado: <?php echo h($capacitacionBloqueadaLabel); ?></div>
                                                    <?php endif; ?>
                                                    <a href="<?php echo htmlspecialchars($base_path . 'mis_cursos.php', ENT_QUOTES, 'UTF-8'); ?>" class="small fw-semibold d-inline-flex align-items-center gap-1 mt-2">
                                                        <i class="fas fa-arrow-right"></i>
                                                        Ir a Mis cursos
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="checkout-stepper">
                                    <div class="checkout-step is-active" data-step="1">
                                        <div class="step-index">1</div>
                                        <div class="step-label">
                                            Resumen
                                            <span class="step-helper"><?php echo h($stepHelper); ?></span>
                                    </div>
                                </div>
                                <div class="checkout-step" data-step="2">
                                    <div class="step-index">2</div>
                                    <div class="step-label">
                                        <?php if ($tipo_checkout === 'certificacion'): ?>
                                            Documentación
                                            <span class="step-helper">Descargá y subí el PDF solicitado</span>
                                        <?php else: ?>
                                            Datos personales
                                            <span class="step-helper">Completá tu información</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="checkout-step" data-step="3">
                                    <div class="step-index">3</div>
                                    <div class="step-label">
                                        Pago
                                        <span class="step-helper">
                                            <?php if ($tipo_checkout === 'certificacion' && !$certificacionPuedePagar && !$certificacionPagado): ?>
                                                Esperá la aprobación
                                            <?php elseif ($tipo_checkout === 'certificacion' && $certificacionPagado): ?>
                                                Pago registrado
                                            <?php else: ?>
                                                Elegí el método
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>

                                <div class="checkout-content">
                                    <?php if ($flash_success): ?>
                                        <div class="alert alert-success checkout-alert" role="alert">
                                            <div class="d-flex align-items-start gap-2">
                                                <i class="fas fa-circle-check mt-1"></i>
                                                <div>
                                                <strong>¡Inscripción enviada!</strong>
                                                <?php if (!empty($flash_success['orden'])): ?>
                                                    <div>Número de orden: #<?php echo str_pad((string)(int)$flash_success['orden'], 6, '0', STR_PAD_LEFT); ?>.</div>
                                                <?php endif; ?>
                                                <div class="small mt-1">Te contactaremos por correo para completar el proceso.</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($flash_error): ?>
                                    <div class="alert alert-danger checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-triangle-exclamation mt-1"></i>
                                            <div>
                                                <strong>No pudimos procesar tu inscripción.</strong>
                                                <div class="small mt-1"><?php echo h($flash_error); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($certificacionShowSuccessAlert): ?>
                                    <div class="alert alert-success checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-file-circle-check mt-1"></i>
                                            <div>
                                                <strong><?php echo h($certificacionSuccessMessage); ?></strong>
                                                <?php if ($certificacionSuccessEstadoLabel): ?>
                                                    <div class="small mt-1">Estado actual: <?php echo h($certificacionSuccessEstadoLabel); ?>.</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($certificacionErrorMessage): ?>
                                    <div class="alert alert-danger checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-circle-xmark mt-1"></i>
                                            <div>
                                                <strong>No pudimos registrar la certificación.</strong>
                                                <div class="small mt-1"><?php echo h($certificacionErrorMessage); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <form id="checkoutForm" action="../admin/procesarsbd.php" method="POST" enctype="multipart/form-data" novalidate data-certificacion-has-pdf="<?php echo $certificacionPdfUrl ? '1' : '0'; ?>">
                                    <input type="hidden" name="__accion" id="__accion" value="">
                                    <input type="hidden" name="crear_orden" value="1">
                                    <input type="hidden" name="id_curso" value="<?php echo (int)$id_curso; ?>">
                                    <input type="hidden" name="id_modalidad" value="<?php echo (int)$selectedModalidadId; ?>">
                                    <input type="hidden" name="precio_checkout" value="<?php echo $precio_vigente ? (float)$precio_vigente['precio'] : 0; ?>">
                                    <input type="hidden" name="tipo_checkout" value="<?php echo htmlspecialchars($tipo_checkout, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="id_certificacion" value="<?php echo (int)$certificacionId; ?>">
                                    <input type="hidden" name="certificacion_estado_actual" value="<?php echo $certificacionEstado !== null ? (int)$certificacionEstado : 0; ?>">
                                    <?php if ($soloTransferencia && $retomarRegistroId > 0 && $capacitacionRetryData): ?>
                                        <input type="hidden" name="retomar_capacitacion" value="<?php echo (int)$retomarRegistroId; ?>">
                                        <input type="hidden" name="solo_transferencia" value="1">
                                    <?php endif; ?>

                                    <div class="step-panel active" data-step="1">
                                        <div class="row g-4 align-items-stretch">
                                            <div class="col-lg-7">
                                                <div class="summary-card h-100">
                                                    <h5><?php echo h($summaryTitle); ?></h5>
                                                    <div class="summary-item">
                                                        <strong>Nombre</strong>
                                                        <span><?php echo h($cursoNombre ?? ($curso['nombre_curso'] ?? '')); ?></span>
                                                    </div>
                                                    <div class="summary-item">
                                                        <strong>Duración</strong>
                                                        <span><?php echo h($curso['duracion'] ?? 'A definir'); ?></span>
                                                    </div>
                                                    <?php if ($tipo_checkout !== 'certificacion' && $modalidadNombreSeleccionada): ?>
                                                        <div class="summary-item">
                                                            <strong>Modalidad</strong>
                                                            <span><?php echo h($modalidadNombreSeleccionada); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div class="summary-item">
                                                        <strong>Nivel</strong>
                                                        <span><?php echo h($curso['complejidad'] ?? 'Intermedio'); ?></span>
                                                    </div>
                                                    <div class="summary-description mt-3">
                                                        <?php echo nl2br(h($cursoDescripcion ?? ($curso['descripcion_curso'] ?? ''))); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-5">
                                                <div class="summary-card h-100 d-flex flex-column justify-content-between">
                                                    <h5>Inversión</h5>
                                                    <h5>Inversión</h5>
                                                    <div class="price-highlight">
                                                        <?php
                                                        // Usamos la misma lógica que arriba: si es certificación, mostramos certificación; sino, capacitación
                                                        $esCertificacion = ($tipo_checkout === 'certificacion');
                                                        $label = $esCertificacion ? 'Certificación' : 'Capacitación';
                                                        $precioSeleccionado = $esCertificacion ? $precio_certificacion : $precio_capacitacion;
                                                        ?>
                                                        <div class="price-entries">
                                                            <div class="price-entry price-entry-active">
                                                                <div class="price-entry-label"><?php echo h($label); ?></div>
                                                                <?php if ($precioSeleccionado): ?>
                                                                    <div class="price-entry-value">
                                                                        <?php echo strtoupper($precioSeleccionado['moneda'] ?? 'ARS'); ?>
                                                                        <?php echo number_format((float)$precioSeleccionado['precio'], 2, ',', '.'); ?>
                                                                    </div>
                                                                    <div class="price-entry-note">
                                                                        <?php if (!empty($precioSeleccionado['vigente_desde'])): ?>
                                                                            Vigente desde <?php echo date('d/m/Y H:i', strtotime($precioSeleccionado['vigente_desde'])); ?>
                                                                        <?php else: ?>
                                                                            Precio vigente disponible en el sistema.
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="price-entry-missing">Precio a confirmar.</div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php if (!$precioSeleccionado): ?>
                                                            <div class="small text-muted mt-3">
                                                                El equipo se pondrá en contacto para coordinar disponibilidad, medios de pago y comenzar tu proceso.
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="small text-muted mt-3">
                                                        <?php echo h($priceHelper); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="nav-actions">
                                            <span></span>
                                            <button type="button" class="btn btn-gradient btn-rounded" data-next="2" <?php echo ($tipo_checkout === 'capacitacion' && $capacitacionBloqueada) ? 'disabled' : ''; ?>>
                                                Continuar al paso 2
                                                <i class="fas fa-arrow-right ms-2"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="step-panel" data-step="2">
                                        <?php if ($tipo_checkout === 'certificacion'): ?>
                                            <?php
                                            $certNombreValue = $certificacionData['nombre'] ?? $prefillNombre;
                                            $certApellidoValue = $certificacionData['apellido'] ?? $prefillApellido;
                                            $certEmailValue = $certificacionData['email'] ?? $prefillEmail;
                                            $certTelefonoValue = $certificacionData['telefono'] ?? $prefillTelefono;
                                            $certDniValue = $certificacionData['dni'] ?? $prefillDni;
                                            $certDireccionValue = $certificacionData['direccion'] ?? $prefillDireccion;
                                            $certCiudadValue = $certificacionData['ciudad'] ?? $prefillCiudad;
                                            $certProvinciaValue = $certificacionData['provincia'] ?? $prefillProvincia;
                                            $certPaisValue = $certificacionData['pais'] ?? $prefillPais;
                                            $certTieneCertificacionPrevia = (int)($certificacionData['tenia_certificacion_previa'] ?? 0) === 1;
                                            $certEntidadEmisoraValue = $certificacionData['certificacion_emitida_por'] ?? '';
                                            $certInputsReadonly = $certificacionAllowSubmit ? '' : 'readonly';
                                            $certDatosHelper = $certificacionAllowSubmit
                                                ? 'Actualizá tus datos si es necesario antes de enviar la solicitud.'
                                                : 'Estos son los datos que utilizaste en tu solicitud.';
                                            ?>
                                            <?php if ($certificacionEstado !== null): ?>
                                                <div class="alert alert-info checkout-alert mb-4" role="alert">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <i class="fas fa-info-circle mt-1"></i>
                                                        <div>
                                                            <strong>Estado de tu solicitud:</strong>
                                                            <div class="small mt-1"><?php echo h(checkout_certificacion_estado_label($certificacionEstado)); ?>.</div>
                                                            <?php if (!empty($certificacionData['observaciones'])): ?>
                                                                <div class="small text-muted mt-1"><?php echo nl2br(h($certificacionData['observaciones'])); ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($certificacionEstado === 2 && !$certificacionPagado): ?>
                                                <div class="alert alert-success checkout-alert mb-4" role="alert">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <i class="fas fa-circle-check mt-1"></i>
                                                        <div>
                                                            <strong>¡Documentación aprobada!</strong>
                                                            <div class="small mt-1">Revisá que tus datos estén correctos y avanzá al paso 3 para completar el pago de la certificación.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Reorganizando la sección de certificación con mejor estructura visual -->
                                            <div class="row g-4">
                                                <!-- Documentación requerida -->
                                                <div class="col-12">
                                                    <div class="summary-card">
                                                        <div class="d-flex align-items-center gap-3 mb-4">
                                                            <div class="d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%); border-radius: 12px;">
                                                                <i class="fas fa-file-pdf text-white" style="font-size: 24px;"></i>
                                                            </div>
                                                            <div>
                                                                <h5 class="mb-1">Documentación requerida</h5>
                                                                <p class="mb-0 small text-muted">Descargá, completá y subí el formulario firmado</p>
                                                            </div>
                                                        </div>

                                                        <div class="row g-4">
                                                            <!-- Paso 1: Descargar -->
                                                            <div class="col-md-4">
                                                                <div class="p-4 rounded-3" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(6, 182, 212, 0.05) 100%); border: 1px solid rgba(37, 99, 235, 0.1);">
                                                                    <div class="d-flex align-items-center gap-2 mb-3">
                                                                        <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #2563eb; border-radius: 8px; color: white; font-weight: 600; font-size: 14px;">1</div>
                                                                        <h6 class="mb-0">Descargar formulario</h6>
                                                                    </div>
                                                                    <p class="small text-muted mb-3">Descargá el PDF oficial con los campos a completar</p>
                                                                    <a class="btn btn-sm w-100" href="../assets/pdf/solicitud_certificacion.pdf" target="_blank" rel="noopener" style="background: #2563eb; color: white; border-radius: 8px; padding: 10px; font-weight: 500;">
                                                                        <i class="fas fa-download me-2"></i>Descargar PDF
                                                                    </a>
                                                                </div>
                                                            </div>

                                                            <!-- Paso 2: Completar -->
                                                            <div class="col-md-4">
                                                                <div class="p-4 rounded-3" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(6, 182, 212, 0.05) 100%); border: 1px solid rgba(37, 99, 235, 0.1);">
                                                                    <div class="d-flex align-items-center gap-2 mb-3">
                                                                        <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #2563eb; border-radius: 8px; color: white; font-weight: 600; font-size: 14px;">2</div>
                                                                        <h6 class="mb-0">Completar y firmar</h6>
                                                                    </div>
                                                                    <p class="small text-muted mb-3">Completá todos los campos requeridos y firmá el documento</p>
                                                                <div class="d-flex align-items-center gap-2 p-2 rounded" style="background: rgba(37, 99, 235, 0.1);">
                                                                    <i class="fas fa-info-circle" style="color: #2563eb;"></i>
                                                                    <span class="small" style="color: #2563eb;">Guardá como PDF</span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                            <!-- Paso 3: Subir -->
                                                            <div class="col-md-4">
                                                                <div class="p-4 rounded-3" style="background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(6, 182, 212, 0.05) 100%); border: 1px solid rgba(37, 99, 235, 0.1);">
                                                                    <div class="d-flex align-items-center gap-2 mb-3">
                                                                        <div class="d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; background: #2563eb; border-radius: 8px; color: white; font-weight: 600; font-size: 14px;">3</div>
                                                                        <h6 class="mb-0">Subir documento</h6>
                                                                    </div>
                                                                    <p class="small text-muted mb-3">Adjuntá el formulario completado y firmado</p>
                                                                    <?php if ($certificacionPdfUrl): ?>
                                                                        <div class="d-flex align-items-center gap-2 p-2 rounded" style="background: rgba(16, 185, 129, 0.1);">
                                                                            <i class="fas fa-check-circle" style="color: #10b981;"></i>
                                                                            <span class="small" style="color: #10b981;">PDF cargado</span>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <div class="d-flex align-items-center gap-2 p-2 rounded" style="background: rgba(245, 158, 11, 0.1);">
                                                                            <i class="fas fa-clock" style="color: #f59e0b;"></i>
                                                                            <span class="small" style="color: #f59e0b;">Pendiente</span>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Área de subida de archivo -->
                                                        <div class="mt-4 p-4 rounded-3" style="background: rgba(37, 99, 235, 0.03); border: 2px dashed rgba(37, 99, 235, 0.2);">
                                                            <div class="row align-items-center g-3">
                                                                <div class="col-md-8">
                                                                    <label for="cert_pdf" class="form-label mb-2" style="font-weight: 600; color: #1e293b;">
                                                                        <i class="fas fa-cloud-upload-alt me-2" style="color: #2563eb;"></i>
                                                                        Subir formulario firmado (PDF) <?php if ($certificacionAllowSubmit): ?><span style="color: #ef4444;">*</span><?php endif; ?>
                                                                    </label>
                                                                    <input type="file" class="form-control" id="cert_pdf" name="cert_pdf" accept="application/pdf" <?php echo $certificacionAllowSubmit ? '' : 'disabled'; ?> style="border-radius: 8px;">
                                                                    <div class="small text-muted mt-2">
                                                                        <i class="fas fa-info-circle me-1"></i>
                                                                        Formato: PDF • Tamaño máximo: 10 MB
                                                                    </div>
                                                                </div>
                                                                <?php if ($certificacionPdfUrl): ?>
                                                                    <div class="col-md-4">
                                                                        <div class="p-3 rounded-3" style="background: white; border: 1px solid #e2e8f0;">
                                                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                                                <i class="fas fa-file-pdf" style="color: #ef4444; font-size: 20px;"></i>
                                                                                <span class="small fw-semibold">Archivo actual</span>
                                                                            </div>
                                                                            <a class="btn btn-sm btn-outline-primary w-100" href="<?php echo h($certificacionPdfUrl); ?>" target="_blank" rel="noopener" style="border-radius: 6px;">
                                                                                <i class="fas fa-eye me-2"></i>Ver documento
                                                                            </a>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Datos del solicitante -->
                                                <div class="col-12">
                                                    <div class="summary-card">
                                                        <div class="d-flex align-items-center gap-3 mb-4">
                                                            <div class="d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background: linear-gradient(135deg, #2563eb 0%, #06b6d4 100%); border-radius: 12px;">
                                                                <i class="fas fa-user-circle text-white" style="font-size: 24px;"></i>
                                                            </div>
                                                            <div>
                                                                <h5 class="mb-1">Datos del solicitante</h5>
                                                                <p class="mb-0 small text-muted"><?php echo h($certDatosHelper); ?></p>
                                                            </div>
                                                        </div>

                                                        <div class="row g-3">
                                                            <!-- Información personal -->
                                                            <div class="col-12">
                                                                <div class="p-3 rounded-3" style="background: rgba(37, 99, 235, 0.03); border-left: 3px solid #2563eb;">
                                                                    <h6 class="mb-3" style="color: #2563eb; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                                                        <i class="fas fa-id-card me-2"></i>Información Personal
                                                                    </h6>
                                                                    <div class="row g-3">
                                                                        <div class="col-md-6">
                                                                            <label for="cert_nombre" class="form-label small fw-semibold mb-1">Nombre <span style="color: #ef4444;">*</span></label>
                                                                            <input type="text" class="form-control" id="cert_nombre" name="nombre_insc" autocomplete="given-name" value="<?php echo h($certNombreValue); ?>" <?php echo $certInputsReadonly; ?> <?php echo $certificacionAllowSubmit ? 'required' : ''; ?> style="border-radius: 8px;">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <label for="cert_apellido" class="form-label small fw-semibold mb-1">Apellido <span style="color: #ef4444;">*</span></label>
                                                                            <input type="text" class="form-control" id="cert_apellido" name="apellido_insc" autocomplete="family-name" value="<?php echo h($certApellidoValue); ?>" <?php echo $certInputsReadonly; ?> <?php echo $certificacionAllowSubmit ? 'required' : ''; ?> style="border-radius: 8px;">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <label for="cert_dni" class="form-label small fw-semibold mb-1">DNI / Documento</label>
                                                                            <input type="text" class="form-control" id="cert_dni" name="dni_insc" value="<?php echo h($certDniValue); ?>" <?php echo $certInputsReadonly; ?> style="border-radius: 8px;">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <label for="cert_pais" class="form-label small fw-semibold mb-1">País</label>
                                                                            <input type="text" class="form-control" id="cert_pais" name="pais_insc" value="<?php echo h($certPaisValue); ?>" <?php echo $certInputsReadonly; ?> style="border-radius: 8px;">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Certificación previa -->
                                                            <div class="col-12">
                                                                <div class="p-3 rounded-3" style="background: rgba(59, 130, 246, 0.05); border-left: 3px solid #3b82f6;">
                                                                    <h6 class="mb-3" style="color: #1d4ed8; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                                                        <i class="fas fa-certificate me-2"></i>Certificación previa
                                                                    </h6>
                                                                    <input type="hidden" name="tenia_certificacion_previa" id="certificacion_previa_hidden" value="<?php echo $certTieneCertificacionPrevia ? '1' : '0'; ?>">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox" value="1" id="certificacion_previa" <?php echo $certTieneCertificacionPrevia ? 'checked' : ''; ?> <?php echo $certificacionAllowSubmit ? '' : 'disabled'; ?>>
                                                                        <label class="form-check-label small fw-semibold" for="certificacion_previa">
                                                                            Ya tengo una certificación emitida previamente
                                                                        </label>
                                                                    </div>
                                                                    <div id="certificacion_previa_wrapper" class="mt-3 <?php echo $certTieneCertificacionPrevia ? '' : 'd-none'; ?>">
                                                                        <label for="certificacion_previa_emisor" class="form-label small fw-semibold mb-1">¿Qué ente la emitió?</label>
                                                                        <input type="text" class="form-control" id="certificacion_previa_emisor" name="certificacion_emitida_por" value="<?php echo h($certEntidadEmisoraValue); ?>" placeholder="Ej.: Ministerio de Trabajo" <?php echo $certificacionAllowSubmit ? '' : 'readonly'; ?> style="border-radius: 8px;">
                                                                        <div class="form-text">Indicá el organismo u organización que emitió la certificación.</div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Información de contacto -->
                                                            <div class="col-12">
                                                                <div class="p-3 rounded-3" style="background: rgba(6, 182, 212, 0.03); border-left: 3px solid #06b6d4;">
                                                                    <h6 class="mb-3" style="color: #06b6d4; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                                                        <i class="fas fa-envelope me-2"></i>Información de Contacto
                                                                    </h6>
                                                                    <div class="row g-3">
                                                                        <div class="col-md-6">
                                                                            <label for="cert_email" class="form-label small fw-semibold mb-1">Email <span style="color: #ef4444;">*</span></label>
                                                                            <input type="email" class="form-control" id="cert_email" name="email_insc" autocomplete="email" value="<?php echo h($certEmailValue); ?>" <?php echo $certInputsReadonly; ?> <?php echo $certificacionAllowSubmit ? 'required' : ''; ?> style="border-radius: 8px;">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <label for="cert_telefono" class="form-label small fw-semibold mb-1">Teléfono</label>
                                                                            <input type="text" class="form-control" id="cert_telefono" name="tel_insc" autocomplete="tel" value="<?php echo h($certTelefonoValue); ?>" <?php echo $certInputsReadonly; ?> style="border-radius: 8px;">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Dirección -->
                                                            <div class="col-12">
                                                                <div class="p-3 rounded-3" style="background: rgba(139, 92, 246, 0.03); border-left: 3px solid #8b5cf6;">
                                                                    <h6 class="mb-3" style="color: #8b5cf6; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                                                        <i class="fas fa-map-marker-alt me-2"></i>Dirección
                                                                    </h6>
                                                                    <div class="row g-3">
                                                                        <div class="col-12">
                                                                            <label for="cert_direccion" class="form-label small fw-semibold mb-1">Calle y número</label>
                                                                            <input type="text" class="form-control" id="cert_direccion" name="dir_insc" autocomplete="address-line1" value="<?php echo h($certDireccionValue); ?>" <?php echo $certInputsReadonly; ?> style="border-radius: 8px;">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <label for="cert_ciudad" class="form-label small fw-semibold mb-1">Ciudad</label>
                                                                            <input type="text" class="form-control" id="cert_ciudad" name="ciu_insc" autocomplete="address-level2" value="<?php echo h($certCiudadValue); ?>" <?php echo $certInputsReadonly; ?> style="border-radius: 8px;">
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <label for="cert_provincia" class="form-label small fw-semibold mb-1">Provincia / Estado</label>
                                                                            <input type="text" class="form-control" id="cert_provincia" name="prov_insc" autocomplete="address-level1" value="<?php echo h($certProvinciaValue); ?>" <?php echo $certInputsReadonly; ?> style="border-radius: 8px;">
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="terms-check mt-4">
                                                <input type="checkbox" class="form-check-input mt-1" id="acepta" name="acepta_tyc" value="1" <?php echo $shouldCheckTerms ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="acepta">
                                                    Confirmo que la información es correcta y acepto los <a href="#" target="_blank" rel="noopener">Términos y Condiciones</a>.
                                                </label>
                                            </div>

                                            <div class="nav-actions">
                                                <button type="button" class="btn btn-outline-light btn-rounded" data-prev="1">
                                                    <i class="fas fa-arrow-left me-2"></i>
                                                    Volver
                                                </button>
                                                <div class="d-flex flex-column flex-sm-row gap-2">
                                                    <button type="button" class="btn btn-gradient btn-rounded" id="btnCertificacionEnviar" <?php echo $certificacionAllowSubmit ? '' : 'disabled'; ?>>
                                                        <span class="btn-label"><?php echo h($certificacionSubmitLabel); ?></span>
                                                        <i class="fas fa-paper-plane ms-2"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-light btn-rounded" data-next="3" id="btnIrPaso3" <?php echo $certificacionPuedePagar || $certificacionPagado ? '' : 'disabled'; ?>>
                                                        Ir al paso 3
                                                        <i class="fas fa-arrow-right ms-2"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label for="nombre" class="form-label required-field">Nombre</label>
                                                    <input type="text" class="form-control" id="nombre" name="nombre_insc" placeholder="Nombre" autocomplete="given-name" value="<?php echo h($prefillNombre); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="apellido" class="form-label required-field">Apellido</label>
                                                    <input type="text" class="form-control" id="apellido" name="apellido_insc" placeholder="Apellido" autocomplete="family-name" value="<?php echo h($prefillApellido); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="email" class="form-label required-field">Email</label>
                                                    <input type="email" class="form-control" id="email" name="email_insc" placeholder="correo@dominio.com" autocomplete="email" value="<?php echo h($prefillEmail); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="telefono" class="form-label required-field">Teléfono</label>
                                                    <input type="text" class="form-control" id="telefono" name="tel_insc" placeholder="+54 11 5555-5555" autocomplete="tel" value="<?php echo h($prefillTelefono); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="dni" class="form-label">DNI</label>
                                                    <input type="text" class="form-control" id="dni" name="dni_insc" placeholder="Documento" value="<?php echo h($certificacionData['dni'] ?? $prefillDni); ?>">
                                                </div>
                                                <div class="col-md-8">
                                                    <label for="direccion" class="form-label">Dirección</label>
                                                    <input type="text" class="form-control" id="direccion" name="dir_insc" placeholder="Calle y número" autocomplete="address-line1" value="<?php echo h($certificacionData['direccion'] ?? $prefillDireccion); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="ciudad" class="form-label">Ciudad</label>
                                                    <input type="text" class="form-control" id="ciudad" name="ciu_insc" autocomplete="address-level2" value="<?php echo h($certificacionData['ciudad'] ?? $prefillCiudad); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="provincia" class="form-label">Provincia</label>
                                                    <input type="text" class="form-control" id="provincia" name="prov_insc" autocomplete="address-level1" value="<?php echo h($certificacionData['provincia'] ?? $prefillProvincia); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="pais" class="form-label">País</label>
                                                    <input type="text" class="form-control" id="pais" name="pais_insc" value="<?php echo h($certificacionData['pais'] ?? $prefillPais); ?>" autocomplete="country-name">
                                                </div>
                                            </div>
                                            <div class="terms-check mt-4">
                                                <input type="checkbox" class="form-check-input mt-1" id="acepta" name="acepta_tyc" value="1" <?php echo $shouldCheckTerms ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="acepta">
                                                    Confirmo que los datos ingresados son correctos y acepto los <a href="#" target="_blank" rel="noopener">Términos y Condiciones</a>.
                                                </label>
                                            </div>
                                            <div class="nav-actions">
                                                <button type="button" class="btn btn-outline-light btn-rounded" data-prev="1">
                                                    <i class="fas fa-arrow-left me-2"></i>
                                                    Volver
                                                </button>
                                                <button type="button" class="btn btn-gradient btn-rounded" data-next="3">
                                                    Continuar al paso 3
                                                    <i class="fas fa-arrow-right ms-2"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="step-panel" data-step="3">
                                        <div class="payment-box">
                                            <?php if ($tipo_checkout === 'certificacion' && $certificacionPuedePagar && !$certificacionPagado): ?>
                                                <div class="alert alert-success checkout-alert" role="alert">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <i class="fas fa-shield-check mt-1"></i>
                                                        <div>
                                                            <strong>Tu certificación está lista para el pago.</strong>
                                                            <div class="small mt-1">Elegí cómo querés abonar: podés pagar con Mercado Pago o subir el comprobante de transferencia.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($tipo_checkout === 'certificacion' && !$certificacionPuedePagar && !$certificacionPagado): ?>
                                                <div class="alert alert-info checkout-alert" role="alert">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <i class="fas fa-hourglass-half mt-1"></i>
                                                        <div>
                                                            <strong>Estamos revisando tu documentación.</strong>
                                                            <div class="small mt-1">Te avisaremos por correo cuando podamos habilitar el pago.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php elseif ($tipo_checkout === 'certificacion' && $certificacionPagado): ?>
                                                <div class="alert alert-success checkout-alert" role="alert">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <i class="fas fa-circle-check mt-1"></i>
                                                        <div>
                                                            <strong>¡Listo! Registramos el pago de tu certificación.</strong>
                                                            <div class="small mt-1">Si necesitás actualizar algún dato, contactate con nuestro equipo.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($tipo_checkout !== 'certificacion' || $certificacionPuedePagar || $certificacionPagado): ?>
                                                <h5>Método de pago</h5>
                                                <?php if ($soloTransferenciaForzado): ?>
                                                    <div class="alert alert-warning checkout-alert" role="alert">
                                                        <div class="d-flex align-items-start gap-2">
                                                            <i class="fas fa-info-circle mt-1"></i>
                                                            <div>
                                                                <strong>Solo disponible transferencia bancaria.</strong>
                                                                <div class="small mt-1">
                                                                    <?php if ($soloTransferenciaConfiguracion): ?>
                                                                        <?php echo htmlspecialchars($transferenciaMensajeConfiguracion, ENT_QUOTES, 'UTF-8'); ?>
                                                                    <?php else: ?>
                                                                        Retomaste este pago para enviar el comprobante de tu transferencia. Mercado Pago no está habilitado en esta instancia.
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <label class="payment-option">
                                                    <input type="radio" id="metodo_transfer" name="metodo_pago" value="transferencia" checked>
                                                    <div class="payment-info">
                                                        <strong>Transferencia bancaria</strong>
                                                        <span>Subí el comprobante de tu transferencia.</span>
                                                    </div>
                                                </label>
                                                <?php if ($mercadoPagoHabilitado): ?>
                                                    <label class="payment-option mt-3">
                                                        <input type="radio" id="metodo_mp" name="metodo_pago" value="mercado_pago" <?php echo ($precio_vigente && !$soloTransferenciaForzado) ? '' : 'disabled'; ?>>
                                                        <div class="payment-info">
                                                            <strong>Mercado Pago</strong>
                                                            <?php if ($soloTransferenciaForzado): ?>
                                                                <span>Disponible nuevamente cuando se habilite el pago online.</span>
                                                            <?php elseif ($precio_vigente): ?>
                                                                <span>Pagá de forma segura con tarjetas, efectivo o saldo en Mercado Pago.</span>
                                                            <?php else: ?>
                                                                <span> Disponible cuando haya un precio vigente para esta capacitación.</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </label>
                                                <?php endif; ?>

                                                <div class="payment-details" id="transferDetails">
                                                    <div class="bank-data">
                                                        <strong>Datos bancarios</strong>
                                                        <ul class="mb-0 mt-2 ps-3">
                                                            <li>Banco: Tu Banco</li>
                                                            <li>CBU: 0000000000000000000000</li>
                                                            <li>Alias: tuempresa.cursos</li>
                                                        </ul>
                                                    </div>
                                                    <div class="row g-3">
                                                        <div class="col-lg-8">
                                                            <label for="comprobante" class="form-label required-field">Comprobante de pago</label>
                                                            <input type="file" class="form-control" id="comprobante" name="comprobante" accept=".jpg,.jpeg,.png,.pdf">
                                                            <div class="upload-label">Formatos aceptados: JPG, PNG o PDF. Tamaño máximo 5 MB.</div>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <label for="obs_pago" class="form-label">Observaciones</label>
                                                            <input type="text" class="form-control" id="obs_pago" name="obs_pago" placeholder="Opcional">
                                                        </div>
                                                    </div>
                                                </div>

                                                <?php if ($mercadoPagoHabilitado): ?>
                                                    <div class="payment-details hidden" id="mpDetails">
                                                        <div class="summary-card">
                                                            <h6 class="mb-3">Pagar con Mercado Pago</h6>
                                                            <p class="mb-2">Al confirmar, crearemos tu orden y te redirigiremos a Mercado Pago para completar el pago en un entorno seguro.</p>
                                                            <?php if ($precio_vigente): ?>
                                                                <?php $mpMontoTexto = sprintf('%s %s', strtoupper($precio_vigente['moneda'] ?? 'ARS'), number_format((float) $precio_vigente['precio'], 2, ',', '.')); ?>
                                                                <p class="mb-2 fw-semibold">Monto a abonar: <?php echo $mpMontoTexto; ?></p>
                                                            <?php endif; ?>
                                                            <ul class="mb-0 small text-muted list-unstyled">
                                                                <li class="mb-1"><i class="fas fa-lock me-2"></i>Usá tu cuenta de Mercado Pago o tus medios de pago habituales.</li>
                                                                <li><i class="fas fa-envelope me-2"></i>Te enviaremos un correo con la confirmación apenas se acredite.</li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="nav-actions">
                                            <button type="button" class="btn btn-outline-light btn-rounded" data-prev="2">
                                                <i class="fas fa-arrow-left me-2"></i>
                                                Volver
                                            </button>
                                            <button type="button" class="btn btn-gradient btn-rounded" id="btnConfirmar" <?php echo ($tipo_checkout === 'certificacion' && (!$certificacionPuedePagar || $certificacionPagado)) || ($tipo_checkout === 'capacitacion' && $capacitacionBloqueada) ? 'disabled' : ''; ?>>
                                                <span class="btn-label">Confirmar inscripción</span>
                                                <i class="fas fa-paper-plane ms-2"></i>
                                            </button>
                                        </div>
                                        <div class="checkout-footer text-center mt-4">
                                            Al confirmar, enviaremos los datos a nuestro equipo para validar tu lugar y nos comunicaremos por correo electrónico.
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function() {
            const card = document.querySelector('.checkout-card');
            const steps = Array.from(document.querySelectorAll('.checkout-step'));
            const panels = Array.from(document.querySelectorAll('.step-panel'));
            if (!steps.length || !panels.length) {
                return;
            }

            const mpAvailable = <?php echo ($mercadoPagoHabilitado && $precio_vigente && !$soloTransferenciaForzado) ? 'true' : 'false'; ?>;
            const forceTransferOnly = <?php echo $soloTransferenciaForzado ? 'true' : 'false'; ?>;
            const mercadoPagoHabilitado = <?php echo $mercadoPagoHabilitado ? 'true' : 'false'; ?>;
            const checkoutType = '<?php echo htmlspecialchars($tipo_checkout, ENT_QUOTES, 'UTF-8'); ?>';
            const capacitacionBloqueada = <?php echo ($tipo_checkout === 'capacitacion' && $capacitacionBloqueada) ? 'true' : 'false'; ?>;
            const capacitacionBloqueadaMensaje = <?php echo json_encode($capacitacionBloqueada ? $capacitacionBloqueadaMensaje : ''); ?>;
            const certificacionPuedePagar = <?php echo $certificacionPuedePagar ? 'true' : 'false'; ?>;
            const certificacionPagado = <?php echo $certificacionPagado ? 'true' : 'false'; ?>;
            const certificacionAllowSubmit = <?php echo $certificacionAllowSubmit ? 'true' : 'false'; ?>;
            const certificacionId = <?php echo (int)$certificacionId; ?>;
            const certPrevCheckbox = document.getElementById('certificacion_previa');
            const certPrevHidden = document.getElementById('certificacion_previa_hidden');
            const certPrevWrapper = document.getElementById('certificacion_previa_wrapper');
            const certPrevInput = document.getElementById('certificacion_previa_emisor');
            const mpEndpoint = '../checkout/mercadopago_init.php';
            let currentStep = 1;
            let mpProcessing = false;

            const goToStep = (target) => {
                currentStep = target;
                steps.forEach(step => {
                    const stepIndex = parseInt(step.dataset.step, 10);
                    step.classList.toggle('is-active', stepIndex === target);
                    step.classList.toggle('is-complete', stepIndex < target);
                });
                panels.forEach(panel => {
                    const panelIndex = parseInt(panel.dataset.step, 10);
                    panel.classList.toggle('active', panelIndex === target);
                });
                if (card) {
                    window.scrollTo({
                        top: card.offsetTop - 80,
                        behavior: 'smooth'
                    });
                }
            };

            const showAlert = (icon, title, message) => {
                Swal.fire({
                    icon,
                    title,
                    html: message,
                    confirmButtonText: 'Entendido',
                    customClass: {
                        confirmButton: 'btn btn-gradient btn-rounded'
                    },
                    buttonsStyling: false
                });
            };

            const validateStep = (step) => {
                if (checkoutType === 'certificacion') {
                    if (step === 3) {
                        if (certificacionPagado) {
                            showAlert('info', 'Pago registrado', 'Ya registramos el pago de tu certificación.');
                            return false;
                        }
                        if (!certificacionPuedePagar) {
                            showAlert('info', 'Aún estamos revisando tu documentación', 'Te avisaremos por correo cuando habilitemos el pago.');
                            return false;
                        }
                    }
                }
                if (step === 2) {
                    if (checkoutType === 'certificacion') {
                        const terms = document.getElementById('acepta');
                        if (!terms || !terms.checked) {
                            goToStep(2);
                            showAlert('warning', 'Términos y Condiciones', 'Debés aceptar los Términos y Condiciones para continuar.');
                            return false;
                        }
                        if (certificacionAllowSubmit) {
                            const requiredCert = [{
                                    id: 'cert_nombre',
                                    label: 'Nombre'
                                },
                                {
                                    id: 'cert_apellido',
                                    label: 'Apellido'
                                },
                                {
                                    id: 'cert_email',
                                    label: 'Email'
                                }
                            ];
                            const missingCert = requiredCert.find(field => {
                                const el = document.getElementById(field.id);
                                return !el || !el.value || el.value.trim() === '';
                            });
                            if (missingCert) {
                                goToStep(2);
                                showAlert('error', 'Faltan datos', `Completá el campo <strong>${missingCert.label}</strong> para continuar.`);
                                return false;
                            }
                            const certEmailEl = document.getElementById('cert_email');
                            const certEmail = certEmailEl ? certEmailEl.value.trim() : '';
                            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                            if (!emailPattern.test(certEmail)) {
                                goToStep(2);
                                showAlert('error', 'Email inválido', 'Ingresá un correo electrónico válido.');
                                return false;
                            }
                            if (certPrevCheckbox && certPrevCheckbox.checked) {
                                const prevValue = certPrevInput ? certPrevInput.value.trim() : '';
                                if (!prevValue) {
                                    goToStep(2);
                                    showAlert('error', 'Faltan datos', 'Indicá el ente que emitió tu certificación anterior.');
                                    return false;
                                }
                            }
                        }
                        return true;
                    }
                    const required = [{
                            id: 'nombre',
                            label: 'Nombre'
                        },
                        {
                            id: 'apellido',
                            label: 'Apellido'
                        },
                        {
                            id: 'email',
                            label: 'Email'
                        },
                        {
                            id: 'telefono',
                            label: 'Teléfono'
                        }
                    ];
                    const missing = required.find(field => {
                        const el = document.getElementById(field.id);
                        return !el || !el.value || el.value.trim() === '';
                    });
                    if (missing) {
                        goToStep(2);
                        showAlert('error', 'Faltan datos', `Completá el campo <strong>${missing.label}</strong> para continuar.`);
                        return false;
                    }
                    const emailInput = document.getElementById('email');
                    const email = emailInput ? emailInput.value.trim() : '';
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(email)) {
                        goToStep(2);
                        showAlert('error', 'Email inválido', 'Ingresá un correo electrónico válido.');
                        return false;
                    }
                    const terms = document.getElementById('acepta');
                    if (!terms || !terms.checked) {
                        goToStep(2);
                        showAlert('warning', 'Términos y Condiciones', 'Debés aceptar los Términos y Condiciones para continuar.');
                        return false;
                    }
                }
                if (step === 3) {
                    const mpEl = document.getElementById('metodo_mp');
                    const transferEl = document.getElementById('metodo_transfer');
                    const mp = mpEl ? mpEl.checked : false;
                    const transfer = transferEl ? transferEl.checked : false;
                    if (!mpEl && !transferEl) {
                        return true;
                    }
                    if (!mp && !transfer) {
                        goToStep(3);
                        showAlert('error', 'Seleccioná un método de pago', 'Elegí una forma de pago para continuar.');
                        return false;
                    }
                    if (mp && !mpAvailable) {
                        goToStep(3);
                        showAlert('warning', 'Mercado Pago no disponible', 'Este curso todavía no tiene un precio vigente para pagar online.');
                        return false;
                    }
                    if (transfer) {
                        const fileInput = document.getElementById('comprobante');
                        const file = fileInput.files[0];
                        if (!file) {
                            goToStep(3);
                            showAlert('error', 'Falta el comprobante', 'Adjuntá el comprobante de la transferencia.');
                            return false;
                        }
                        const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
                        if (!allowedTypes.includes(file.type)) {
                            goToStep(3);
                            showAlert('error', 'Archivo no permitido', 'Solo se aceptan archivos JPG, PNG o PDF.');
                            return false;
                        }
                        const maxSize = 5 * 1024 * 1024;
                        if (file.size > maxSize) {
                            goToStep(3);
                            showAlert('error', 'Archivo demasiado grande', 'El archivo debe pesar hasta 5 MB.');
                            return false;
                        }
                    }
                }
                return true;
            };

            const validateCertificacionDocumento = () => {
                if (checkoutType !== 'certificacion') {
                    return true;
                }
                if (!certificacionAllowSubmit) {
                    showAlert('info', 'Solicitud en revisión', 'Ya recibimos tu formulario y estamos revisándolo.');
                    return false;
                }
                if (!certPdfInput) {
                    return true;
                }
                const file = certPdfInput.files[0];
                if (!file) {
                    const message = certificacionHasPdf ?
                        'Subí nuevamente el formulario firmado para reenviar la solicitud.' :
                        'Adjuntá el formulario firmado en formato PDF.';
                    showAlert('error', 'Falta el formulario', message);
                    return false;
                }
                if (file.type !== 'application/pdf') {
                    showAlert('error', 'Archivo inválido', 'El formulario debe estar en formato PDF.');
                    return false;
                }
                const maxSize = 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    showAlert('error', 'Archivo demasiado grande', 'El PDF debe pesar hasta 10 MB.');
                    return false;
                }
                return true;
            };

            document.querySelectorAll('[data-next]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const next = parseInt(btn.dataset.next, 10);
                    if (Number.isNaN(next)) {
                        return;
                    }
                    if (checkoutType === 'capacitacion' && capacitacionBloqueada) {
                        showAlert('info', 'Inscripción registrada', capacitacionBloqueadaMensaje || 'Ya registraste una inscripción para esta capacitación.');
                        return;
                    }
                    if (checkoutType === 'certificacion' && currentStep === 2 && next === 3 && (!certificacionPuedePagar && !certificacionPagado)) {
                        showAlert('info', 'Aún no podés continuar', 'Necesitamos aprobar tu documentación antes de habilitar el pago.');
                        return;
                    }
                    if (currentStep === 2 && !validateStep(2)) {
                        return;
                    }
                    goToStep(next);
                });
            });

            document.querySelectorAll('[data-prev]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const prev = parseInt(btn.dataset.prev, 10);
                    if (Number.isNaN(prev)) {
                        return;
                    }
                    goToStep(prev);
                });
            });

            const mpRadio = document.getElementById('metodo_mp');
            const transferRadio = document.getElementById('metodo_transfer');
            const transferDetails = document.getElementById('transferDetails');
            const mpDetails = document.getElementById('mpDetails');
            const form = document.getElementById('checkoutForm');
            const btnCertificacionEnviar = document.getElementById('btnCertificacionEnviar');
            const btnIrPaso3 = document.getElementById('btnIrPaso3');
            const certPdfInput = document.getElementById('cert_pdf');
            const crearOrdenInput = document.querySelector('input[name="crear_orden"]');
            const accionInput = document.getElementById('__accion');
            const certificacionHasPdf = form ? form.dataset.certificacionHasPdf === '1' : false;
            const confirmButton = document.getElementById('btnConfirmar');
            if (!form || !confirmButton) {
                return;
            }

            const syncCertificacionPrevia = () => {
                if (!certPrevCheckbox) {
                    return;
                }
                const isChecked = certPrevCheckbox.checked;
                if (certPrevHidden) {
                    certPrevHidden.value = isChecked ? '1' : '0';
                }
                if (certPrevWrapper) {
                    if (isChecked) {
                        certPrevWrapper.classList.remove('d-none');
                    } else {
                        certPrevWrapper.classList.add('d-none');
                    }
                }
                if (certPrevInput) {
                    if (certificacionAllowSubmit && isChecked) {
                        certPrevInput.setAttribute('required', 'required');
                    } else {
                        certPrevInput.removeAttribute('required');
                    }
                }
            };

            if (certPrevCheckbox) {
                certPrevCheckbox.addEventListener('change', syncCertificacionPrevia);
                if (!certificacionAllowSubmit) {
                    const blockToggle = (event) => {
                        event.preventDefault();
                        event.stopPropagation();
                    };
                    certPrevCheckbox.addEventListener('click', blockToggle);
                    certPrevCheckbox.addEventListener('keydown', (event) => {
                        if (event.key === ' ' || event.key === 'Enter') {
                            blockToggle(event);
                        }
                    });
                }
                syncCertificacionPrevia();
            }
            let confirmLabel = confirmButton.querySelector('.btn-label');
            let confirmIcon = confirmButton.querySelector('i');
            const confirmDefault = {
                label: 'Confirmar inscripción',
                icon: 'fas fa-paper-plane ms-2'
            };
            const confirmDefaultMarkup = confirmButton.innerHTML;

            if (btnCertificacionEnviar && form) {
                btnCertificacionEnviar.addEventListener('click', () => {
                    if (checkoutType !== 'certificacion') {
                        return;
                    }
                    if (!validateStep(2) || !validateCertificacionDocumento()) {
                        return;
                    }
                    if (accionInput) {
                        accionInput.value = 'crear_certificacion';
                    }
                    if (crearOrdenInput) {
                        crearOrdenInput.value = '';
                    }
                    form.submit();
                });
            }

            const refreshConfirmElements = () => {
                confirmLabel = confirmButton.querySelector('.btn-label');
                confirmIcon = confirmButton.querySelector('i');
            };

            const updateConfirmButton = () => {
                refreshConfirmElements();
                if (!confirmLabel || !confirmIcon) {
                    return;
                }
                if (forceTransferOnly) {
                    confirmLabel.textContent = confirmDefault.label;
                    confirmIcon.className = confirmDefault.icon;
                    return;
                }
                if (mpRadio && mpRadio.checked) {
                    confirmLabel.textContent = 'Ir a Mercado Pago';
                    confirmIcon.className = 'fas fa-credit-card ms-2';
                } else {
                    confirmLabel.textContent = confirmDefault.label;
                    confirmIcon.className = confirmDefault.icon;
                }
            };

            const togglePaymentDetails = () => {
                if (!transferRadio || !transferDetails) {
                    return;
                }
                if (!mpRadio || !mpDetails) {
                    transferDetails.classList.remove('hidden');
                    if (mpDetails) {
                        mpDetails.classList.add('hidden');
                    }
                    updateConfirmButton();
                    return;
                }
                if (transferRadio.checked || forceTransferOnly) {
                    transferDetails.classList.remove('hidden');
                    mpDetails.classList.add('hidden');
                } else if (mpRadio.checked) {
                    mpDetails.classList.remove('hidden');
                    transferDetails.classList.add('hidden');
                }
                updateConfirmButton();
            };

            if (mpRadio) {
                mpRadio.addEventListener('change', togglePaymentDetails);
            }
            if (transferRadio) {
                transferRadio.addEventListener('change', togglePaymentDetails);
            }
            togglePaymentDetails();

            if (checkoutType === 'certificacion' && certificacionPuedePagar && !certificacionPagado) {
                const terms = document.getElementById('acepta');
                if (terms && !terms.checked) {
                    terms.checked = true;
                }
                goToStep(3);
            }

            const setConfirmLoading = (isLoading) => {
                if (isLoading) {
                    confirmButton.disabled = true;
                    confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Redirigiendo a Mercado Pago...';
                } else {
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = confirmDefaultMarkup;
                    updateConfirmButton();
                }
            };

            const iniciarMercadoPago = async () => {
                if (mpProcessing) {
                    return;
                }
                mpProcessing = true;
                setConfirmLoading(true);
                try {
                    const formData = new FormData(form);
                    formData.set('metodo_pago', 'mercado_pago');
                    formData.set('__accion', 'crear_orden');
                    const response = await fetch(mpEndpoint, {
                        method: 'POST',
                        body: formData,
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || !data || !data.success || !data.init_point) {
                        const message = data && data.message ? data.message : 'No se pudo iniciar el pago en Mercado Pago.';
                        throw new Error(message);
                    }
                    window.location.href = data.init_point;
                } catch (error) {
                    mpProcessing = false;
                    setConfirmLoading(false);
                    showAlert('error', 'No se pudo iniciar el pago', error && error.message ? error.message : 'Intentá nuevamente en unos minutos.');
                }
            };

            confirmButton.addEventListener('click', () => {
                if (checkoutType === 'capacitacion' && capacitacionBloqueada) {
                    showAlert('info', 'Inscripción registrada', capacitacionBloqueadaMensaje || 'Ya registraste una inscripción para esta capacitación.');
                    return;
                }
                if (checkoutType === 'certificacion') {
                    if (certificacionPagado) {
                        showAlert('info', 'Pago registrado', 'Ya registramos el pago de tu certificación. No es necesario volver a enviar el formulario.');
                        return;
                    }
                    if (!certificacionPuedePagar) {
                        showAlert('info', 'Documentación en revisión', 'Te avisaremos por correo cuando habilitemos el pago.');
                        return;
                    }
                }
                if (!validateStep(2) || !validateStep(3)) {
                    return;
                }
                const mpSelected = mpRadio ? mpRadio.checked : false;
                const title = mpSelected ? 'Ir a Mercado Pago' : 'Confirmar inscripción';
                const text = mpSelected ?
                    'Vamos a generar tu orden y redirigirte a Mercado Pago para que completes el pago.' :
                    '¿Deseás enviar la inscripción con los datos cargados?';
                const confirmText = mpSelected ? 'Sí, continuar' : 'Sí, enviar';

                Swal.fire({
                    icon: 'question',
                    title,
                    text,
                    showCancelButton: true,
                    confirmButtonText: confirmText,
                    cancelButtonText: 'Cancelar',
                    customClass: {
                        confirmButton: 'btn btn-gradient btn-rounded me-2',
                        cancelButton: 'btn btn-outline-light btn-rounded'
                    },
                    buttonsStyling: false,
                    reverseButtons: true
                }).then(result => {
                    if (!result.isConfirmed) {
                        return;
                    }
                    if (mpSelected) {
                        iniciarMercadoPago();
                    } else {
                        if (accionInput) {
                            accionInput.value = 'crear_orden';
                        }
                        form.submit();
                    }
                });
            });
        })();
    </script>
</body>

</html>
