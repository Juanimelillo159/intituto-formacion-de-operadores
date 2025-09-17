<?php
// procesarsbd.php (sin sesiones)
declare(strict_types=1);
require_once __DIR__ . '/../sbd.php';

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
    log_cursos('error', ['post_keys' => array_keys($_POST ?? [])], $e);
    http_response_code(400);
    echo "Ocurrió un error al procesar la solicitud. Revisa el log para más detalles.";
    exit;
}
