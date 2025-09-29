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
    $usuarioId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);

    // Fallback para acciones cuando el submit se hace por JS
    $accion = $_POST['__accion'] ?? '';

    if ($accion === 'guardar_certificado') {
        header('Content-Type: application/json; charset=utf-8');
        $responseCode = 200;
        $payload = ['success' => false, 'message' => ''];
        try {
            if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['usuario'])) {
                $responseCode = 401;
                throw new RuntimeException('Debés iniciar sesión para continuar.');
            }
            $usuarioId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);
            if ($usuarioId <= 0) {
                $responseCode = 401;
                throw new RuntimeException('Sesión no válida.');
            }
            $cursoId = (int)($_POST['id_curso'] ?? 0);
            if ($cursoId <= 0) {
                throw new InvalidArgumentException('Certificación inválida.');
            }
            $cursoStmt = $con->prepare('SELECT id_curso FROM cursos WHERE id_curso = :id LIMIT 1');
            $cursoStmt->execute([':id' => $cursoId]);
            if (!$cursoStmt->fetch()) {
                throw new InvalidArgumentException('No encontramos la certificación seleccionada.');
            }

            $archivo = $_FILES['certificado_pdf'] ?? null;
            if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Necesitamos el PDF completo para continuar.');
            }
            $tmpName = (string)$archivo['tmp_name'];
            if (!is_uploaded_file($tmpName)) {
                throw new RuntimeException('Archivo inválido.');
            }
            $maxSize = 10 * 1024 * 1024;
            $fileSize = (int)$archivo['size'];
            if ($fileSize > $maxSize) {
                throw new InvalidArgumentException('El archivo supera el tamaño máximo permitido (10 MB).');
            }
            $mime = '';
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime = (string)$finfo->file($tmpName);
                }
            }
            if ($mime === '' && function_exists('mime_content_type')) {
                $mime = (string)@mime_content_type($tmpName);
            }
            $filenameLower = strtolower((string)($archivo['name'] ?? ''));
            $hasPdfExtension = substr($filenameLower, -4) === '.pdf';
            if ($mime !== 'application/pdf' && !$hasPdfExtension) {
                throw new InvalidArgumentException('Solo se permiten archivos PDF.');
            }

            $uploadDir = __DIR__ . '/../uploads/certificados';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    throw new RuntimeException('No pudimos preparar la carpeta de certificados.');
                }
            }

            $nombreArchivo = 'cert_' . date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.pdf';
            $destinoAbs = $uploadDir . '/' . $nombreArchivo;
            $destinoRel = 'uploads/certificados/' . $nombreArchivo;
            if (!move_uploaded_file($tmpName, $destinoAbs)) {
                throw new RuntimeException('No pudimos guardar el archivo en el servidor.');
            }

            $certId = (int)($_POST['id_certificado'] ?? 0);
            $certRow = null;
            if ($certId > 0) {
                $certStmt = $con->prepare('SELECT id_certificado, pdf_path FROM certificados WHERE id_certificado = :id AND id_usuario = :usuario LIMIT 1');
                $certStmt->execute([':id' => $certId, ':usuario' => $usuarioId]);
                $certRow = $certStmt->fetch();
                if (!$certRow) {
                    $certId = 0;
                    $certRow = null;
                }
            }

            $estadoNuevo = 'pendiente_revision';
            $mensajeNuevo = 'Tu formulario fue enviado y nuestro equipo lo está revisando. Te avisaremos por email cuando se apruebe.';
            $alertaNueva = 'alert alert-warning';

            $con->beginTransaction();
            if ($certId > 0) {
                $update = $con->prepare('UPDATE certificados SET pdf_nombre = :nombre, pdf_path = :ruta, pdf_mime = :mime, pdf_tamano = :tamano, estado = :estado, observaciones = NULL, pago_metodo = NULL, pago_estado = NULL, pago_monto = NULL, pago_moneda = NULL, pago_referencia = NULL, pago_comprobante_path = NULL, pago_comprobante_nombre = NULL, pago_comprobante_mime = NULL, pago_comprobante_tamano = NULL, actualizado_en = NOW() WHERE id_certificado = :id');
                $update->execute([
                    ':nombre' => (string)($archivo['name'] ?? $nombreArchivo),
                    ':ruta' => $destinoRel,
                    ':mime' => 'application/pdf',
                    ':tamano' => $fileSize,
                    ':estado' => $estadoNuevo,
                    ':id' => $certId,
                ]);
            } else {
                $insert = $con->prepare('INSERT INTO certificados (id_usuario, id_curso, pdf_nombre, pdf_path, pdf_mime, pdf_tamano, estado, creado_en) VALUES (:usuario, :curso, :nombre, :ruta, :mime, :tamano, :estado, NOW())');
                $insert->execute([
                    ':usuario' => $usuarioId,
                    ':curso' => $cursoId,
                    ':nombre' => (string)($archivo['name'] ?? $nombreArchivo),
                    ':ruta' => $destinoRel,
                    ':mime' => 'application/pdf',
                    ':tamano' => $fileSize,
                    ':estado' => $estadoNuevo,
                ]);
                $certId = (int)$con->lastInsertId();
            }
            $con->commit();

            if ($certRow && !empty($certRow['pdf_path'])) {
                $oldPath = __DIR__ . '/../' . ltrim((string)$certRow['pdf_path'], '/');
                if (is_file($oldPath) && realpath($oldPath) !== realpath($destinoAbs)) {
                    @unlink($oldPath);
                }
            }

            $payload['success'] = true;
            $payload['message'] = '¡Formulario recibido! Lo revisaremos a la brevedad.';
            $payload['certificado'] = [
                'id' => $certId,
                'estado' => $estadoNuevo,
                'pdfNombre' => (string)($archivo['name'] ?? $nombreArchivo),
                'pdfUrl' => '../' . $destinoRel,
                'mensaje' => $mensajeNuevo,
                'puedePagar' => false,
                'pagoRegistrado' => false,
                'alertClass' => $alertaNueva,
                'pagoEstado' => null,
                'pagoMetodo' => null,
                'pagoMonto' => null,
                'pagoMoneda' => null,
                'pagoBloqueado' => true,
                'plantilla' => '../assets/docs/plantilla-certificacion.pdf',
                'comprobanteUrl' => null,
            ];
        } catch (Throwable $exception) {
            if (isset($destinoAbs) && isset($destinoRel) && is_file($destinoAbs)) {
                @unlink($destinoAbs);
            }
            if (!isset($responseCode) || $responseCode === 200) {
                $responseCode = $exception instanceof InvalidArgumentException ? 422 : 500;
            }
            $payload['success'] = false;
            $payload['message'] = $exception->getMessage();
        }
        http_response_code($responseCode);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $checkoutIsCrearOrden = isset($_POST['crear_orden']) || ($accion === 'crear_orden');

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

        if ($checkoutCursoId <= 0) {
            throw new InvalidArgumentException('Curso inválido.');
        }
        if ($checkoutTipo === 'certificacion' && $usuarioId > 0) {
            try {
                $usrStmt = $con->prepare('SELECT nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais FROM usuarios WHERE id_usuario = :id LIMIT 1');
                $usrStmt->execute([':id' => $usuarioId]);
                $usrRow = $usrStmt->fetch();
                if ($usrRow) {
                    if ($nombreInscrito === '') { $nombreInscrito = (string)$usrRow['nombre']; }
                    if ($apellidoInscrito === '') { $apellidoInscrito = (string)$usrRow['apellido']; }
                    if ($emailInscrito === '') { $emailInscrito = (string)$usrRow['email']; }
                    if ($telefonoInscrito === '') { $telefonoInscrito = (string)$usrRow['telefono']; }
                    if ($dniInscrito === '') { $dniInscrito = (string)$usrRow['dni']; }
                    if ($direccionInsc === '') { $direccionInsc = (string)$usrRow['direccion']; }
                    if ($ciudadInsc === '') { $ciudadInsc = (string)$usrRow['ciudad']; }
                    if ($provinciaInsc === '') { $provinciaInsc = (string)$usrRow['provincia']; }
                    if ($paisInsc === '') { $paisInsc = (string)$usrRow['pais']; }
                }
            } catch (Throwable $ignored) {
            }
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

        $certificadoRow = null;
        if ($checkoutTipo === 'certificacion') {
            if ($usuarioId <= 0) {
                throw new RuntimeException('Sesión inválida para la certificación.');
            }
            $certificadoId = (int)($_POST['id_certificado'] ?? 0);
            if ($certificadoId <= 0) {
                throw new InvalidArgumentException('Primero debés enviar el formulario de certificación.');
            }
            $certificadoStmt = $con->prepare('SELECT * FROM certificados WHERE id_certificado = :id AND id_usuario = :usuario LIMIT 1');
            $certificadoStmt->execute([':id' => $certificadoId, ':usuario' => $usuarioId]);
            $certificadoRow = $certificadoStmt->fetch();
            if (!$certificadoRow) {
                throw new RuntimeException('No encontramos tu solicitud de certificación.');
            }
            $estadoCert = strtolower((string)$certificadoRow['estado']);
            if (!in_array($estadoCert, ['aprobado', 'pago_pendiente_confirmacion', 'pagado'], true)) {
                throw new InvalidArgumentException('Tu formulario todavía no fue aprobado para realizar el pago.');
            }
            if ($estadoCert === 'pagado' || strtolower((string)($certificadoRow['pago_estado'] ?? '')) === 'pagado') {
                throw new InvalidArgumentException('Ya registramos el pago de esta certificación.');
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

        if ($metodoPago === 'transferencia' && $checkoutUploadAbs !== null) {
            if (!move_uploaded_file($checkoutUploadTmp, $checkoutUploadAbs)) {
                throw new RuntimeException('No se pudo guardar el comprobante de la transferencia.');
            }
            $checkoutUploadMoved = true;
        }

        if ($checkoutTipo === 'certificacion' && $certificadoRow) {
            $estadoDespuesPago = 'pago_pendiente_confirmacion';
            $pagoEstado = 'pendiente';
            $updateCertificado = $con->prepare('UPDATE certificados SET estado = :estado, pago_metodo = :metodo, pago_estado = :pago_estado, pago_monto = :monto, pago_moneda = :moneda, pago_referencia = :referencia, pago_comprobante_path = :comprobante_path, pago_comprobante_nombre = :comprobante_nombre, pago_comprobante_mime = :comprobante_mime, pago_comprobante_tamano = :comprobante_tamano, actualizado_en = NOW() WHERE id_certificado = :id');
            $updateCertificado->execute([
                ':estado' => $estadoDespuesPago,
                ':metodo' => $metodoPago,
                ':pago_estado' => $pagoEstado,
                ':monto' => $precioFinal,
                ':moneda' => $monedaPrecio,
                ':referencia' => 'insc-' . $idInscripcion,
                ':comprobante_path' => $checkoutUploadRel,
                ':comprobante_nombre' => $comprobanteNombreOriginal,
                ':comprobante_mime' => $comprobanteMime,
                ':comprobante_tamano' => $comprobanteTamano,
                ':id' => (int)$certificadoRow['id_certificado'],
            ]);
        }

        $con->commit();

        $_SESSION['checkout_success'] = [
            'orden' => $idInscripcion,
            'metodo' => $metodoPago,
            'tipo' => $checkoutTipo,
            'id_curso' => $checkoutCursoId,
            'certificado' => $checkoutTipo === 'certificacion' && $certificadoRow ? (int)$certificadoRow['id_certificado'] : null,
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

    log_cursos('error', ['post_keys' => array_keys($_POST ?? [])], $e);
    http_response_code(400);
    echo "Ocurrió un error al procesar la solicitud. Revisa el log para más detalles.";
    exit;
}
