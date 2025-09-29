<?php
// procesarsbd.php (sin sesiones)
declare(strict_types=1);
require_once __DIR__ . '/../sbd.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/* ===================== LOG ===================== */
function log_cursos(string $accion, array $data = [], ?Throwable $ex = null): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $file = $logDir . '/cursos.log';
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $now = (new DateTime('now'))->format('Y-m-d H:i:s');
    $row = ['ts' => $now, 'user' => 'anon', 'ip' => $ip, 'accion' => $accion, 'data' => $data];
    if ($ex) {
        $row['error'] = [
            'type'    => get_class($ex),
            'message' => $ex->getMessage(),
            'code'    => (string)$ex->getCode(),
        ];
    }
    @file_put_contents($file, json_encode($row, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

/* ==================== HELPERS =================== */
// Normaliza "120.000,50", "120000.50", "$ 120000" -> "120000.50" (string numérica con punto)
function normalizar_precio(?string $raw): ?string
{
    if ($raw === null) return null;
    $s = trim($raw);
    if ($s === '') return null;
    $s = preg_replace('/[^\d\.,]/', '', $s); // deja sólo dígitos, punto, coma
    if ($s === '') return null;
    $comma = strrpos($s, ',');
    $dot = strrpos($s, '.');
    $decSep = ($comma !== false && $dot !== false)
        ? (($comma > $dot) ? ',' : '.')
        : (($comma !== false) ? ',' : '.');
    if ($decSep === ',') {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } else {
        $s = str_replace(',', '', $s);
    }
    if (!is_numeric($s)) return null;
    return $s;
}

// Convierte input "YYYY-MM-DDTHH:MM" a "Y-m-d H:i:s"
function parse_dt_local(string $v): string
{
    $v = trim($v);
    if ($v === '') throw new InvalidArgumentException('Fecha inválida');
    $v = str_replace('T', ' ', $v);
    if (strlen($v) === 16) $v .= ':00';
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $v);
    if (!$dt) throw new InvalidArgumentException('Fecha inválida');
    return $dt->format('Y-m-d H:i:s');
}

function registrar_historico_certificacion(PDO $con, int $idCertificacion, int $estado): void
{
    $hist = $con->prepare('
        INSERT INTO historico_estado_certificaciones (id_certificacion, id_estado)
        VALUES (:id, :estado)
    ');
    $hist->execute([
        ':id' => $idCertificacion,
        ':estado' => $estado,
    ]);
}

/* =================== MAIN FLOW ================== */
$checkoutUploadAbs = null;
$checkoutUploadTmp = null;
$checkoutUploadMoved = false;
$checkoutIsCrearOrden = false;
$checkoutCursoId = 0;
$checkoutTipo = 'curso';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido');
    }
    if (!($con instanceof PDO)) {
        throw new RuntimeException('Conexión PDO inválida.');
    }

    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $con->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Fallback para acciones cuando el submit se hace por JS
    $accion = $_POST['__accion'] ?? '';

    $isCrearCertificacion = ($accion === 'crear_certificacion');
    $isAprobarCertificacion = ($accion === 'aprobar_certificacion');
    $isRechazarCertificacion = ($accion === 'rechazar_certificacion');

    $checkoutIsCrearOrden = isset($_POST['crear_orden']) || ($accion === 'crear_orden');

    if ($isCrearCertificacion) {
        $certUploadTmp = null;
        $certUploadAbs = null;
        $certUploadRel = null;
        $certUploadMoved = false;
        $certUploadOld = null;
        $checkoutCursoId = (int)($_POST['id_curso'] ?? 0);

        try {
            $usuarioId = (int)($_SESSION['id_usuario'] ?? 0);
            if ($usuarioId <= 0) {
                throw new RuntimeException('Debés iniciar sesión para completar la solicitud.');
            }

            if ($checkoutCursoId <= 0) {
                throw new InvalidArgumentException('Curso inválido.');
            }

            $certificacionId = (int)($_POST['id_certificacion'] ?? 0);
            $nombreInscrito  = trim((string)($_POST['nombre_insc'] ?? ''));
            $apellidoInscrito = trim((string)($_POST['apellido_insc'] ?? ''));
            $emailInscrito   = trim((string)($_POST['email_insc'] ?? ''));
            $telefonoInscrito = trim((string)($_POST['tel_insc'] ?? ''));
            $dniInscrito     = trim((string)($_POST['dni_insc'] ?? ''));
            $direccionInsc   = trim((string)($_POST['dir_insc'] ?? ''));
            $ciudadInsc      = trim((string)($_POST['ciu_insc'] ?? ''));
            $provinciaInsc   = trim((string)($_POST['prov_insc'] ?? ''));
            $paisInsc        = trim((string)($_POST['pais_insc'] ?? ''));
            if ($paisInsc === '') {
                $paisInsc = 'Argentina';
            }
            $aceptaTyC = isset($_POST['acepta_tyc']) ? 1 : 0;

            if ($nombreInscrito === '' || $apellidoInscrito === '' || $emailInscrito === '' || $telefonoInscrito === '') {
                throw new InvalidArgumentException('Completá los datos obligatorios de contacto.');
            }
            if (!filter_var($emailInscrito, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Ingresá un correo electrónico válido.');
            }
            if ($aceptaTyC !== 1) {
                throw new InvalidArgumentException('Debés aceptar los Términos y Condiciones.');
            }

            $cursoStmt = $con->prepare('SELECT id_curso, nombre_curso FROM cursos WHERE id_curso = :id LIMIT 1');
            $cursoStmt->execute([':id' => $checkoutCursoId]);
            $cursoRow = $cursoStmt->fetch();
            if (!$cursoRow) {
                throw new RuntimeException('No encontramos la certificación seleccionada.');
            }

            $precioStmt = $con->prepare('
              SELECT precio, moneda
                FROM curso_precio_hist
               WHERE id_curso = :c
                 AND vigente_desde <= NOW()
                 AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
            ORDER BY vigente_desde DESC
               LIMIT 1
            ');
            $precioStmt->execute([':c' => $checkoutCursoId]);
            $precioRow = $precioStmt->fetch();
            $precioFinal = $precioRow ? (float)$precioRow['precio'] : (float)($_POST['precio_checkout'] ?? 0.0);
            if ($precioFinal < 0) {
                $precioFinal = 0.0;
            }
            $monedaPrecio = ($precioRow && !empty($precioRow['moneda'])) ? (string)$precioRow['moneda'] : 'ARS';

            $certificacionRow = null;
            if ($certificacionId > 0) {
                $certStmt = $con->prepare('SELECT * FROM checkout_certificaciones WHERE id_certificacion = :id LIMIT 1');
                $certStmt->execute([':id' => $certificacionId]);
                $certificacionRow = $certStmt->fetch();
                if (!$certificacionRow || (int)$certificacionRow['creado_por'] !== $usuarioId || (int)$certificacionRow['id_curso'] !== $checkoutCursoId) {
                    throw new RuntimeException('No encontramos la solicitud de certificación para actualizar.');
                }
                $estadoActual = (int)$certificacionRow['id_estado'];
                if (!in_array($estadoActual, [1, 4], true)) {
                    throw new RuntimeException('Esta certificación ya fue procesada y no puede modificarse.');
                }
                $certUploadOld = $certificacionRow['pdf_path'] ?? null;
            }

            $archivoPdf = $_FILES['cert_pdf'] ?? null;
            if (!is_array($archivoPdf) || ($archivoPdf['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Debés adjuntar el formulario firmado en PDF.');
            }

            $certUploadTmp = (string)$archivoPdf['tmp_name'];
            if ($certUploadTmp === '' || !is_uploaded_file($certUploadTmp)) {
                throw new RuntimeException('No pudimos procesar el archivo subido.');
            }
            $certTamano = (int)$archivoPdf['size'];
            $certMax = 10 * 1024 * 1024;
            if ($certTamano > $certMax) {
                throw new InvalidArgumentException('El PDF supera el tamaño máximo permitido (10 MB).');
            }

            $mimeDetectado = '';
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mimeDetectado = (string)$finfo->file($certUploadTmp);
                }
            }
            if ($mimeDetectado === '' && function_exists('mime_content_type')) {
                $mimeDetectado = (string)@mime_content_type($certUploadTmp);
            }
            if ($mimeDetectado === '') {
                $mimeDetectado = 'application/pdf';
            }
            if ($mimeDetectado !== 'application/pdf') {
                throw new InvalidArgumentException('El formulario debe estar en formato PDF.');
            }

            $uploadsBase = __DIR__ . '/../uploads/certificaciones';
            if (!is_dir($uploadsBase)) {
                if (!mkdir($uploadsBase, 0755, true) && !is_dir($uploadsBase)) {
                    throw new RuntimeException('No se pudo crear la carpeta para almacenar los formularios.');
                }
            }

            $nombreArchivo = 'cert_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.pdf';
            $certUploadAbs = $uploadsBase . '/' . $nombreArchivo;
            $certUploadRel = 'uploads/certificaciones/' . $nombreArchivo;
            $pdfNombreOriginal = (string)$archivoPdf['name'];

            $con->beginTransaction();

            if ($certificacionRow) {
                $observacionesBase = 'Formulario reenviado el ' . date('d/m/Y H:i');
                $observacionesExistentes = trim((string)($certificacionRow['observaciones'] ?? ''));
                if ($observacionesExistentes !== '') {
                    $observacionesBase .= ' | ' . $observacionesExistentes;
                }
                $update = $con->prepare('
                    UPDATE checkout_certificaciones
                       SET nombre = :nombre,
                           apellido = :apellido,
                           email = :email,
                           telefono = :telefono,
                           acepta_tyc = :acepta,
                           precio_total = :precio,
                           moneda = :moneda,
                           pdf_path = :pdf_path,
                           pdf_nombre = :pdf_nombre,
                           pdf_mime = :pdf_mime,
                           observaciones = :obs,
                           id_estado = 1,
                           dni = :dni,
                           direccion = :direccion,
                           ciudad = :ciudad,
                           provincia = :provincia,
                           pais = :pais
                     WHERE id_certificacion = :id
                ');
                $update->execute([
                    ':nombre' => $nombreInscrito,
                    ':apellido' => $apellidoInscrito,
                    ':email' => $emailInscrito,
                    ':telefono' => $telefonoInscrito,
                    ':acepta' => $aceptaTyC,
                    ':precio' => $precioFinal,
                    ':moneda' => strtoupper($monedaPrecio),
                    ':pdf_path' => $certUploadRel,
                    ':pdf_nombre' => $pdfNombreOriginal,
                    ':pdf_mime' => 'application/pdf',
                    ':obs' => $observacionesBase,
                    ':dni' => $dniInscrito !== '' ? $dniInscrito : null,
                    ':direccion' => $direccionInsc !== '' ? $direccionInsc : null,
                    ':ciudad' => $ciudadInsc !== '' ? $ciudadInsc : null,
                    ':provincia' => $provinciaInsc !== '' ? $provinciaInsc : null,
                    ':pais' => $paisInsc,
                    ':id' => $certificacionId,
                ]);
            } else {
                $insert = $con->prepare('
                    INSERT INTO checkout_certificaciones (
                        creado_por, acepta_tyc, precio_total, moneda, id_curso,
                        pdf_path, pdf_nombre, pdf_mime, observaciones, id_estado,
                        nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais
                    ) VALUES (
                        :usuario, :acepta, :precio, :moneda, :curso,
                        :pdf_path, :pdf_nombre, :pdf_mime, NULL, 1,
                        :nombre, :apellido, :email, :telefono, :dni, :direccion, :ciudad, :provincia, :pais
                    )
                ');
                $insert->execute([
                    ':usuario' => $usuarioId,
                    ':acepta' => $aceptaTyC,
                    ':precio' => $precioFinal,
                    ':moneda' => strtoupper($monedaPrecio),
                    ':curso' => $checkoutCursoId,
                    ':pdf_path' => $certUploadRel,
                    ':pdf_nombre' => $pdfNombreOriginal,
                    ':pdf_mime' => 'application/pdf',
                    ':nombre' => $nombreInscrito,
                    ':apellido' => $apellidoInscrito,
                    ':email' => $emailInscrito,
                    ':telefono' => $telefonoInscrito,
                    ':dni' => $dniInscrito !== '' ? $dniInscrito : null,
                    ':direccion' => $direccionInsc !== '' ? $direccionInsc : null,
                    ':ciudad' => $ciudadInsc !== '' ? $ciudadInsc : null,
                    ':provincia' => $provinciaInsc !== '' ? $provinciaInsc : null,
                    ':pais' => $paisInsc,
                ]);
                $certificacionId = (int)$con->lastInsertId();
            }

            registrar_historico_certificacion($con, $certificacionId, 1);

            if (!move_uploaded_file($certUploadTmp, $certUploadAbs)) {
                throw new RuntimeException('No se pudo guardar el formulario de certificación.');
            }
            $certUploadMoved = true;

            $con->commit();

            if ($certUploadOld) {
                $oldAbs = __DIR__ . '/../' . ltrim((string)$certUploadOld, '/');
                if (is_file($oldAbs)) {
                    @unlink($oldAbs);
                }
            }

            $_SESSION['certificacion_success'] = [
                'message' => '¡Solicitud enviada! Revisaremos tu documentación y te avisaremos por correo.',
                'estado' => 1,
                'id' => $certificacionId,
            ];

            log_cursos('checkout_crear_certificacion', [
                'id_certificacion' => $certificacionId,
                'id_curso' => $checkoutCursoId,
                'usuario' => $usuarioId,
            ]);

            header('Location: ../checkout/checkout.php?id_certificacion=' . $checkoutCursoId . '&tipo=certificacion');
            exit;
        } catch (Throwable $certException) {
            if ($con->inTransaction()) {
                $con->rollBack();
            }
            if ($certUploadMoved && $certUploadAbs && is_file($certUploadAbs)) {
                @unlink($certUploadAbs);
            }
            $_SESSION['certificacion_error'] = ['message' => $certException->getMessage()];
            $fallbackCurso = $checkoutCursoId > 0 ? $checkoutCursoId : 0;
            header('Location: ../checkout/checkout.php?id_certificacion=' . $fallbackCurso . '&tipo=certificacion');
            exit;
        }
    }

    if ($checkoutIsCrearOrden) {
        $checkoutCursoId = (int)($_POST['id_curso'] ?? 0);
        $nombreInscrito  = trim((string)($_POST['nombre_insc'] ?? ''));
        $apellidoInscrito = trim((string)($_POST['apellido_insc'] ?? ''));
        $emailInscrito   = trim((string)($_POST['email_insc'] ?? ''));
        $telefonoInscrito = trim((string)($_POST['tel_insc'] ?? ''));
        $dniInscrito     = trim((string)($_POST['dni_insc'] ?? ''));
        $direccionInsc   = trim((string)($_POST['dir_insc'] ?? ''));
        $ciudadInsc      = trim((string)($_POST['ciu_insc'] ?? ''));
        $provinciaInsc   = trim((string)($_POST['prov_insc'] ?? ''));
        $paisInsc        = trim((string)($_POST['pais_insc'] ?? ''));
        if ($paisInsc === '') {
            $paisInsc = 'Argentina';
        }
        $aceptaTyC = isset($_POST['acepta_tyc']) ? 1 : 0;
        $metodoPago = (string)($_POST['metodo_pago'] ?? '');
        if ($metodoPago === 'mp') {
            $metodoPago = 'mercado_pago';
        }
        $tipoCheckoutRaw = strtolower(trim((string)($_POST['tipo_checkout'] ?? ($_POST['tipo'] ?? ''))));
        if ($tipoCheckoutRaw === 'capacitaciones') {
            $tipoCheckoutRaw = 'capacitacion';
        } elseif ($tipoCheckoutRaw === 'certificaciones') {
            $tipoCheckoutRaw = 'certificacion';
        }
        if (!in_array($tipoCheckoutRaw, ['curso', 'capacitacion', 'certificacion'], true)) {
            $tipoCheckoutRaw = 'curso';
        }
        $checkoutTipo = $tipoCheckoutRaw;
        $obsPagoRaw = trim((string)($_POST['obs_pago'] ?? ''));
        if (function_exists('mb_substr')) {
            $observacionesPago = mb_substr($obsPagoRaw, 0, 250, 'UTF-8');
        } else {
            $observacionesPago = substr($obsPagoRaw, 0, 250);
        }
        $precioInput = isset($_POST['precio_checkout']) ? (float)$_POST['precio_checkout'] : 0.0;
        $certificacionId = (int)($_POST['id_certificacion'] ?? 0);
        $certificacionRow = null;

        if ($checkoutCursoId <= 0) {
            throw new InvalidArgumentException('Curso inválido.');
        }
        if ($nombreInscrito === '' || $apellidoInscrito === '' || $emailInscrito === '' || $telefonoInscrito === '') {
            throw new InvalidArgumentException('Completá los datos obligatorios del inscripto.');
        }
        if (!filter_var($emailInscrito, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('El correo electrónico no es válido.');
        }
        if ($aceptaTyC !== 1) {
            throw new InvalidArgumentException('Debés aceptar los Términos y Condiciones.');
        }
        $metodosPermitidos = ['transferencia', 'mercado_pago'];
        if (!in_array($metodoPago, $metodosPermitidos, true)) {
            throw new InvalidArgumentException('Método de pago inválido.');
        }

        if ($checkoutTipo === 'certificacion') {
            if ($certificacionId <= 0) {
                throw new RuntimeException('Necesitamos una certificación aprobada para continuar con el pago.');
            }
            $certStmt = $con->prepare('SELECT * FROM checkout_certificaciones WHERE id_certificacion = :id LIMIT 1');
            $certStmt->execute([':id' => $certificacionId]);
            $certificacionRow = $certStmt->fetch();
            if (!$certificacionRow) {
                throw new RuntimeException('No encontramos la solicitud de certificación.');
            }
            $usuarioId = (int)($_SESSION['id_usuario'] ?? 0);
            if ($usuarioId > 0 && (int)$certificacionRow['creado_por'] !== $usuarioId) {
                throw new RuntimeException('No tenés autorización para pagar esta certificación.');
            }
            $estadoCert = (int)$certificacionRow['id_estado'];
            if ($estadoCert === 3) {
                throw new RuntimeException('La certificación ya registra un pago.');
            }
            if ($estadoCert !== 2) {
                throw new RuntimeException('Debés esperar la aprobación de la certificación antes de continuar con el pago.');
            }
        }

        $cursoStmt = $con->prepare("SELECT id_curso, nombre_curso FROM cursos WHERE id_curso = :id LIMIT 1");
        $cursoStmt->execute([':id' => $checkoutCursoId]);
        $cursoRow = $cursoStmt->fetch();
        if (!$cursoRow) {
            throw new RuntimeException('El curso seleccionado no existe.');
        }

        $precioStmt = $con->prepare("
          SELECT precio, moneda
            FROM curso_precio_hist
           WHERE id_curso = :c
             AND vigente_desde <= NOW()
             AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
        ORDER BY vigente_desde DESC
           LIMIT 1
        ");
        $precioStmt->execute([':c' => $checkoutCursoId]);
        $precioRow = $precioStmt->fetch();
        $precioFinal = $precioRow ? (float)$precioRow['precio'] : (float)$precioInput;
        $monedaPrecio = ($precioRow && !empty($precioRow['moneda'])) ? (string)$precioRow['moneda'] : 'ARS';
        if ($precioFinal < 0) {
            $precioFinal = 0.0;
        }

        $comprobanteNombreOriginal = null;
        $comprobanteMime = null;
        $comprobanteTamano = null;
        $checkoutUploadRel = null;

        if ($metodoPago === 'transferencia') {
            $archivo = $_FILES['comprobante'] ?? null;
            if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Debés adjuntar el comprobante de la transferencia.');
            }
            $checkoutUploadTmp = (string)$archivo['tmp_name'];
            $comprobanteTamano = (int)$archivo['size'];
            $maxBytes = 5 * 1024 * 1024;
            if ($comprobanteTamano > $maxBytes) {
                throw new InvalidArgumentException('El comprobante supera el tamaño máximo permitido (5 MB).');
            }

            $mimeDetectado = '';
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mimeDetectado = (string)$finfo->file($checkoutUploadTmp);
                }
            }
            if ($mimeDetectado === '' && function_exists('mime_content_type')) {
                $mimeDetectado = (string)@mime_content_type($checkoutUploadTmp);
            }
            if ($mimeDetectado === '' && isset($archivo['type'])) {
                $mimeDetectado = (string)$archivo['type'];
            }

            $mimePermitidos = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf',
            ];
            $extension = $mimePermitidos[$mimeDetectado] ?? null;
            if ($extension === null) {
                $extensionTmp = strtolower((string)pathinfo((string)$archivo['name'], PATHINFO_EXTENSION));
                if ($extensionTmp === 'jpeg') {
                    $extensionTmp = 'jpg';
                }
                if (in_array($extensionTmp, ['jpg', 'png', 'pdf'], true)) {
                    $extension = $extensionTmp;
                }
            }
            if ($extension === null) {
                throw new InvalidArgumentException('Formato de comprobante inválido. Permitido: JPG, PNG o PDF.');
            }

            $uploadsBase = __DIR__ . '/../uploads';
            $uploadsTarget = $uploadsBase . '/comprobantes';
            if (!is_dir($uploadsTarget)) {
                if (!mkdir($uploadsTarget, 0755, true) && !is_dir($uploadsTarget)) {
                    throw new RuntimeException('No se pudo crear la carpeta para comprobantes.');
                }
            }

            $nombreArchivo = 'comp_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $checkoutUploadAbs = $uploadsTarget . '/' . $nombreArchivo;
            $checkoutUploadRel = 'uploads/comprobantes/' . $nombreArchivo;
            $comprobanteNombreOriginal = (string)$archivo['name'];
            $comprobanteMime = $mimeDetectado !== '' ? $mimeDetectado : ($extension === 'pdf' ? 'application/pdf' : 'image/' . $extension);
        }

        $con->beginTransaction();

        $inscripcionStmt = $con->prepare("
          INSERT INTO checkout_inscripciones (
            id_curso, nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais, acepta_tyc, precio_total, moneda
          ) VALUES (
            :curso, :nombre, :apellido, :email, :telefono, :dni, :direccion, :ciudad, :provincia, :pais, :acepta, :precio, :moneda
          )
        ");
        $inscripcionStmt->execute([
            ':curso' => $checkoutCursoId,
            ':nombre' => $nombreInscrito,
            ':apellido' => $apellidoInscrito,
            ':email' => $emailInscrito,
            ':telefono' => $telefonoInscrito,
            ':dni' => $dniInscrito !== '' ? $dniInscrito : null,
            ':direccion' => $direccionInsc !== '' ? $direccionInsc : null,
            ':ciudad' => $ciudadInsc !== '' ? $ciudadInsc : null,
            ':provincia' => $provinciaInsc !== '' ? $provinciaInsc : null,
            ':pais' => $paisInsc,
            ':acepta' => $aceptaTyC,
            ':precio' => $precioFinal,
            ':moneda' => $monedaPrecio,
        ]);

        $idInscripcion = (int)$con->lastInsertId();

        $pagoStmt = $con->prepare("
          INSERT INTO checkout_pagos (
            id_inscripcion, metodo, estado, monto, moneda, comprobante_path, comprobante_nombre, comprobante_mime, comprobante_tamano, observaciones
          ) VALUES (
            :inscripcion, :metodo, 'pendiente', :monto, :moneda, :ruta, :nombre, :mime, :tamano, :obs
          )
        ");
        $pagoStmt->execute([
            ':inscripcion' => $idInscripcion,
            ':metodo' => $metodoPago,
            ':monto' => $precioFinal,
            ':moneda' => $monedaPrecio,
            ':ruta' => $checkoutUploadRel,
            ':nombre' => $comprobanteNombreOriginal,
            ':mime' => $comprobanteMime,
            ':tamano' => $comprobanteTamano,
            ':obs' => $observacionesPago !== '' ? $observacionesPago : null,
        ]);

        if ($checkoutTipo === 'certificacion' && $certificacionRow) {
            $observacionesCert = 'Pago iniciado por ' . ($metodoPago === 'mercado_pago' ? 'Mercado Pago' : 'transferencia bancaria') . ' el ' . date('d/m/Y H:i');
            $observacionesExistentes = trim((string)($certificacionRow['observaciones'] ?? ''));
            if ($observacionesExistentes !== '') {
                $observacionesCert .= ' | ' . $observacionesExistentes;
            }
            $upCert = $con->prepare('
                UPDATE checkout_certificaciones
                   SET id_estado = 3,
                       precio_total = :precio,
                       moneda = :moneda,
                       observaciones = :obs,
                       nombre = :nombre,
                       apellido = :apellido,
                       email = :email,
                       telefono = :telefono,
                       dni = :dni,
                       direccion = :direccion,
                       ciudad = :ciudad,
                       provincia = :provincia,
                       pais = :pais,
                       acepta_tyc = 1
                 WHERE id_certificacion = :id
            ');
            $upCert->execute([
                ':precio' => $precioFinal,
                ':moneda' => $monedaPrecio,
                ':obs' => $observacionesCert,
                ':nombre' => $nombreInscrito,
                ':apellido' => $apellidoInscrito,
                ':email' => $emailInscrito,
                ':telefono' => $telefonoInscrito,
                ':dni' => $dniInscrito !== '' ? $dniInscrito : null,
                ':direccion' => $direccionInsc !== '' ? $direccionInsc : null,
                ':ciudad' => $ciudadInsc !== '' ? $ciudadInsc : null,
                ':provincia' => $provinciaInsc !== '' ? $provinciaInsc : null,
                ':pais' => $paisInsc,
                ':id' => (int)$certificacionRow['id_certificacion'],
            ]);
            registrar_historico_certificacion($con, (int)$certificacionRow['id_certificacion'], 3);
        }

        if ($metodoPago === 'transferencia' && $checkoutUploadAbs !== null) {
            if (!move_uploaded_file($checkoutUploadTmp, $checkoutUploadAbs)) {
                throw new RuntimeException('No se pudo guardar el comprobante de la transferencia.');
            }
            $checkoutUploadMoved = true;
        }

        $con->commit();

        $_SESSION['checkout_success'] = [
            'orden' => $idInscripcion,
            'metodo' => $metodoPago,
            'tipo' => $checkoutTipo,
            'id_curso' => $checkoutCursoId,
        ];

        log_cursos('checkout_crear_orden_ok', [
            'orden' => $idInscripcion,
            'id_curso' => $checkoutCursoId,
            'metodo' => $metodoPago,
            'monto' => $precioFinal,
            'moneda' => $monedaPrecio,
        ]);

        $redirectQuery = [
            'orden' => $idInscripcion,
            'metodo' => $metodoPago,
        ];
        if ($checkoutTipo !== 'curso') {
            $redirectQuery['tipo'] = $checkoutTipo;
        }
        $redirectUrl = '../checkout/gracias.php?' . http_build_query($redirectQuery);

        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($isAprobarCertificacion || $isRechazarCertificacion) {
        $certificacionId = (int)($_POST['id_certificacion'] ?? 0);
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        if ($certificacionId <= 0) {
            throw new InvalidArgumentException('Certificación inválida.');
        }

        $certStmt = $con->prepare('SELECT * FROM checkout_certificaciones WHERE id_certificacion = :id LIMIT 1');
        $certStmt->execute([':id' => $certificacionId]);
        $certRow = $certStmt->fetch();
        if (!$certRow) {
            throw new RuntimeException('No encontramos la certificación seleccionada.');
        }

        $nuevoEstado = $isAprobarCertificacion ? 2 : 4;
        $notaBase = $isAprobarCertificacion ? 'Solicitud aprobada el ' : 'Solicitud rechazada el ';
        $observaciones = $notaBase . date('d/m/Y H:i');
        if ($motivo !== '') {
            $observaciones .= ' - ' . $motivo;
        }
        $observacionesPrevias = trim((string)($certRow['observaciones'] ?? ''));
        if ($observacionesPrevias !== '') {
            $observaciones .= ' | ' . $observacionesPrevias;
        }

        $con->beginTransaction();
        $upCert = $con->prepare('UPDATE checkout_certificaciones SET id_estado = :estado, observaciones = :obs WHERE id_certificacion = :id');
        $upCert->execute([
            ':estado' => $nuevoEstado,
            ':obs' => $observaciones,
            ':id' => $certificacionId,
        ]);
        registrar_historico_certificacion($con, $certificacionId, $nuevoEstado);
        $con->commit();

        $_SESSION['certificacion_admin_success'] = $isAprobarCertificacion
            ? 'La certificación fue aprobada correctamente.'
            : 'La certificación fue rechazada correctamente.';

        header('Location: certificaciones.php');
        exit;
    }

    $isAgregarPrecio = isset($_POST['agregar_precio']) || ($accion === 'agregar_precio');
    $isEditarCurso   = isset($_POST['editar_curso'])   || ($accion === 'editar_curso');
    $isAgregarCurso  = isset($_POST['agregar_curso'])  || ($accion === 'agregar_curso');

    /* =============== AGREGAR PRECIO (HISTÓRICO) =============== */
    if ($isAgregarPrecio) {
        $id_curso   = (int)($_POST['id_curso'] ?? 0);
        $precioRaw  = $_POST['precio'] ?? null;
        $desdeRaw   = $_POST['desde'] ?? null;
        $comentario = trim($_POST['comentario'] ?? '') ?: 'Alta manual en curso';

        if ($id_curso <= 0) throw new InvalidArgumentException('Curso inválido.');
        $precio = normalizar_precio($precioRaw);
        if ($precio === null) throw new InvalidArgumentException('Precio inválido.');
        $desde  = parse_dt_local((string)$desdeRaw);

        $con->beginTransaction();

        // (0) no duplicar exacto mismo vigente_desde
        $st = $con->prepare("SELECT 1 FROM curso_precio_hist WHERE id_curso = :c AND vigente_desde = :d LIMIT 1");
        $st->execute([':c' => $id_curso, ':d' => $desde]);
        if ($st->fetchColumn()) {
            throw new RuntimeException('Ya existe un precio con esa fecha de vigencia.');
        }

        // (1) próximo precio (si lo hay) -> tope del nuevo
        $stNext = $con->prepare("
          SELECT id, vigente_desde
            FROM curso_precio_hist
           WHERE id_curso = :c
             AND vigente_desde > :d
        ORDER BY vigente_desde ASC
           LIMIT 1
        ");
        $stNext->execute([':c' => $id_curso, ':d' => $desde]);
        $next = $stNext->fetch();
        $nuevoHasta = null;
        if ($next) {
            $dt = new DateTime($next['vigente_desde']);
            $dt->modify('-1 second');
            $nuevoHasta = $dt->format('Y-m-d H:i:s');
        }

        // (2) cerrar precio(s) que cubran el instante "desde"
        // FIX HY093: no repetir el mismo placeholder varias veces
        $up = $con->prepare("
          UPDATE curso_precio_hist
             SET vigente_hasta = DATE_SUB(:d0, INTERVAL 1 SECOND)
           WHERE id_curso = :c
             AND vigente_desde < :d1
             AND (vigente_hasta IS NULL OR vigente_hasta >= :d2)
        ");
        $up->execute([':d0' => $desde, ':c' => $id_curso, ':d1' => $desde, ':d2' => $desde]);

        // (3) insertar nuevo
        $ins = $con->prepare("
          INSERT INTO curso_precio_hist (id_curso, precio, moneda, vigente_desde, vigente_hasta, comentario)
          VALUES (:c, :p, 'ARS', :d, :h, :com)
        ");
        $ins->execute([
            ':c' => $id_curso,
            ':p' => $precio,
            ':d' => $desde,
            ':h' => $nuevoHasta,
            ':com' => $comentario
        ]);

        $con->commit();
        log_cursos('agregar_precio_ok', ['id_curso' => $id_curso, 'precio' => $precio, 'desde' => $desde, 'hasta' => $nuevoHasta]);

        header('Location: curso.php?id_curso=' . $id_curso . '&tab=precios&saved=1');
        exit;
    }

    /* ======================== EDITAR CURSO ======================== */
    if ($isEditarCurso) {
        $id_curso     = (int)($_POST['id_curso'] ?? 0);
        $nombre       = trim($_POST['nombre'] ?? '');
        $descripcion  = trim($_POST['descripcion'] ?? '');
        $duracion     = trim($_POST['duracion'] ?? '');
        $objetivos    = trim($_POST['objetivos'] ?? '');
        $complejidad  = trim($_POST['complejidad'] ?? ''); // VARCHAR
        $programa     = trim($_POST['programa'] ?? '');
        $publico      = trim($_POST['publico'] ?? '');
        $cronograma   = trim($_POST['cronograma'] ?? '');
        $requisitos   = trim($_POST['requisitos'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $modalidades  = (isset($_POST['modalidades']) && is_array($_POST['modalidades'])) ? $_POST['modalidades'] : [];

        if ($id_curso <= 0 || $nombre === '' || $descripcion === '' || $duracion === '' || $objetivos === '' || $complejidad === '') {
            throw new InvalidArgumentException('Campos obligatorios faltantes para edición.');
        }

        $con->beginTransaction();

        $sql = $con->prepare("
            UPDATE cursos
               SET nombre_curso     = :nombre,
                   descripcion_curso = :descripcion,
                   duracion          = :duracion,
                   objetivos         = :objetivos,
                   complejidad       = :complejidad,
                   programa          = :programa,
                   publico           = :publico,
                   cronograma        = :cronograma,
                   requisitos        = :requisitos,
                   observaciones     = :observaciones
             WHERE id_curso         = :id
        ");
        $sql->execute([
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':duracion' => $duracion,
            ':objetivos' => $objetivos,
            ':complejidad' => $complejidad,
            ':programa' => $programa,
            ':publico' => $publico,
            ':cronograma' => $cronograma,
            ':requisitos' => $requisitos,
            ':observaciones' => $observaciones,
            ':id' => $id_curso,
        ]);

        // Reemplazar modalidades
        $del = $con->prepare("DELETE FROM curso_modalidad WHERE id_curso = :id");
        $del->execute([':id' => $id_curso]);
        if (!empty($modalidades)) {
            $ins = $con->prepare("INSERT INTO curso_modalidad (id_curso, id_modalidad) VALUES (:c, :m)");
            foreach ($modalidades as $m) {
                $ins->execute([':c' => $id_curso, ':m' => (int)$m]);
            }
        }

        $con->commit();
        log_cursos('editar_curso_ok', ['id_curso' => $id_curso, 'modalidades' => $modalidades]);

        header('Location: curso.php?id_curso=' . $id_curso . '&saved=1');
        exit;
    }

    /* ======================== AGREGAR CURSO ======================= */
    if ($isAgregarCurso) {
        $nombre        = trim($_POST['nombre'] ?? '');
        $descripcion   = trim($_POST['descripcion'] ?? '');
        $duracion      = trim($_POST['duracion'] ?? '');
        $complejidad   = trim($_POST['complejidad'] ?? ''); // VARCHAR
        $objetivos     = trim($_POST['objetivos'] ?? '');
        $programa      = trim($_POST['programa'] ?? '');
        $publico       = trim($_POST['publico'] ?? '');
        $cronograma    = trim($_POST['cronograma'] ?? '');
        $requisitos    = trim($_POST['requisitos'] ?? '');
        $observaciones = trim($_POST['observaciones'] ?? '');
        $modalidades   = (isset($_POST['modalidades']) && is_array($_POST['modalidades'])) ? $_POST['modalidades'] : [];

        // Precio inicial opcional (si el form manda "precio")
        $precioInicialRaw = $_POST['precio'] ?? null;
        $precioInicial    = normalizar_precio($precioInicialRaw);

        if ($nombre === '' || $descripcion === '' || $duracion === '' || $objetivos === '' || $complejidad === '') {
            throw new InvalidArgumentException('Faltan campos obligatorios.');
        }

        $con->beginTransaction();

        $insCurso = $con->prepare("
            INSERT INTO cursos (
                nombre_curso, descripcion_curso, duracion, objetivos,
                cronograma, publico, programa, requisitos, observaciones,
                complejidad
            ) VALUES (
                :nombre, :descripcion, :duracion, :objetivos,
                :cronograma, :publico, :programa, :requisitos, :observaciones,
                :complejidad
            )
        ");
        $insCurso->execute([
            ':nombre'        => $nombre,
            ':descripcion'   => $descripcion,
            ':duracion'      => $duracion,
            ':objetivos'     => $objetivos,
            ':cronograma'    => $cronograma,
            ':publico'       => $publico,
            ':programa'      => $programa,
            ':requisitos'    => $requisitos,
            ':observaciones' => $observaciones,
            ':complejidad'   => $complejidad,
        ]);

        $id_curso = (int)$con->lastInsertId();

        if (!empty($modalidades)) {
            $insMod = $con->prepare("INSERT INTO curso_modalidad (id_curso, id_modalidad) VALUES (:c, :m)");
            foreach ($modalidades as $m) {
                $insMod->execute([':c' => $id_curso, ':m' => (int)$m]);
            }
        }

        // Precio inicial (si vino). Fecha = NOW()
        if ($precioInicial !== null) {
            $nuevoHasta = null;
            $stNext = $con->prepare("
              SELECT vigente_desde
                FROM curso_precio_hist
               WHERE id_curso = :c AND vigente_desde > NOW()
            ORDER BY vigente_desde ASC
               LIMIT 1
            ");
            $stNext->execute([':c' => $id_curso]);
            $next = $stNext->fetchColumn();
            if ($next) {
                $dt = new DateTime($next);
                $dt->modify('-1 second');
                $nuevoHasta = $dt->format('Y-m-d H:i:s');
            }

            $con->prepare("
              UPDATE curso_precio_hist
                 SET vigente_hasta = DATE_SUB(NOW(), INTERVAL 1 SECOND)
               WHERE id_curso = :c
                 AND vigente_desde <  NOW()
                 AND (vigente_hasta IS NULL OR vigente_hasta >= NOW())
            ")->execute([':c' => $id_curso]);

            $con->prepare("
              INSERT INTO curso_precio_hist (id_curso, precio, moneda, vigente_desde, vigente_hasta, comentario)
              VALUES (:c, :p, 'ARS', NOW(), :h, 'Alta inicial de curso')
            ")->execute([':c' => $id_curso, ':p' => $precioInicial, ':h' => $nuevoHasta]);
        }

        $con->commit();
        log_cursos('agregar_curso_ok', [
            'id_curso'    => $id_curso,
            'nombre'      => $nombre,
            'complejidad' => $complejidad,
            'modalidades' => $modalidades,
            'precio_inicial' => $precioInicial,
        ]);

        header('Location: cursos.php');
        exit;
    }

    /* ========================= BANNERS ========================= */
    if (isset($_POST['agregar_banner'])) {
        log_cursos('agregar_banner_inicio', ['post' => array_keys($_POST), 'files' => array_keys($_FILES)]);

        $nombre_banner = $_POST['nombre_banner'] ?? '';
        $imagen        = $_FILES['imagen_banner'] ?? null;

        if (!$imagen || $imagen['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error al subir la imagen.');
        }

        $mime = mime_content_type($imagen['tmp_name']);
        $permitidos = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mime, $permitidos, true)) {
            throw new InvalidArgumentException('Tipo de imagen no permitido.');
        }

        $ext = pathinfo($imagen['name'], PATHINFO_EXTENSION);
        $nombre_imagen = uniqid('imagen_') . ".$ext";
        $destDir = __DIR__ . '/../imagenes/banners';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $dest = $destDir . '/' . $nombre_imagen;

        if (!move_uploaded_file($imagen['tmp_name'], $dest)) {
            throw new RuntimeException('No se pudo mover la imagen.');
        }

        $sql = $con->prepare("INSERT INTO banner (nombre_banner, imagen_banner) VALUES (:n, :img)");
        $sql->execute([':n' => $nombre_banner, ':img' => $nombre_imagen]);

        log_cursos('agregar_banner_ok', ['nombre_banner' => $nombre_banner, 'imagen' => $nombre_imagen]);
        header('Location: /p/admin/carrusel.php');
        exit;
    }

    if (isset($_POST['editar_banner'])) {
        log_cursos('editar_banner_inicio', ['post' => array_keys($_POST), 'files' => array_keys($_FILES)]);

        $id_banner     = (int)($_POST['id_banner'] ?? 0);
        $nombre_banner = $_POST['nombre_banner'] ?? '';
        $imagen        = $_FILES['imagen_banner'] ?? null;

        if ($id_banner <= 0) throw new InvalidArgumentException('id_banner inválido.');

        if ($imagen && $imagen['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($imagen['tmp_name']);
            $permitidos = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($mime, $permitidos, true)) {
                throw new InvalidArgumentException('Tipo de imagen no permitido.');
            }
            $ext = pathinfo($imagen['name'], PATHINFO_EXTENSION);
            $nombre_imagen = uniqid('imagen_') . ".$ext";
            $destDir = __DIR__ . '/../imagenes/banners';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }
            $dest = $destDir . '/' . $nombre_imagen;
            if (!move_uploaded_file($imagen['tmp_name'], $dest)) {
                throw new RuntimeException('No se pudo mover la imagen.');
            }

            // nombre anterior
            $st = $con->prepare("SELECT imagen_banner FROM banner WHERE id_banner = :id");
            $st->execute([':id' => $id_banner]);
            $anterior = $st->fetchColumn();

            $up = $con->prepare("UPDATE banner SET nombre_banner = :n, imagen_banner = :img WHERE id_banner = :id");
            $up->execute([':n' => $nombre_banner, ':img' => $nombre_imagen, ':id' => $id_banner]);

            if ($anterior && is_file($destDir . '/' . $anterior)) {
                @unlink($destDir . '/' . $anterior);
            }

            log_cursos('editar_banner_ok', ['id_banner' => $id_banner, 'imagen' => $nombre_imagen]);
            header('Location: /p/admin/carrusel.php');
            exit;
        } else {
            // solo nombre
            $up = $con->prepare("UPDATE banner SET nombre_banner = :n WHERE id_banner = :id");
            $up->execute([':n' => $nombre_banner, ':id' => $id_banner]);

            log_cursos('editar_banner_ok', ['id_banner' => $id_banner, 'solo_nombre' => true]);
            header('Location: /p/admin/carrusel.php');
            exit;
        }
    }

    // Si llegó acá, no coincidió ninguna acción
    throw new RuntimeException('Acción no reconocida.');
} catch (Throwable $e) {
    if (isset($con) && $con instanceof PDO && $con->inTransaction()) {
        $con->rollBack();
    }

    if ($checkoutIsCrearOrden) {
        if ($checkoutUploadMoved && $checkoutUploadAbs && is_file($checkoutUploadAbs)) {
            @unlink($checkoutUploadAbs);
        }

        log_cursos('checkout_crear_orden_error', [
            'id_curso' => $checkoutCursoId,
            'post_keys' => array_keys($_POST ?? []),
        ], $e);

        $_SESSION['checkout_error'] = $e->getMessage();
        $redirect = '../checkout/checkout.php';
        $params = [];
        if ($checkoutCursoId > 0) {
            $params['id_curso'] = $checkoutCursoId;
        }
        if ($checkoutTipo !== 'curso') {
            $params['tipo'] = $checkoutTipo;
        }
        if (!empty($params)) {
            $redirect .= '?' . http_build_query($params);
        }
        header('Location: ' . $redirect);
        exit;
    }

    if ($isAprobarCertificacion || $isRechazarCertificacion) {
        $_SESSION['certificacion_admin_error'] = $e->getMessage();
        header('Location: certificaciones.php');
        exit;
    }

    log_cursos('error', ['post_keys' => array_keys($_POST ?? [])], $e);
    http_response_code(400);
    echo "Ocurrió un error al procesar la solicitud. Revisa el log para más detalles.";
    exit;
}
