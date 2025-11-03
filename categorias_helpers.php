<?php
declare(strict_types=1);

function categorias_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS categorias (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(150) NOT NULL,
            descripcion TEXT NULL,
            imagen VARCHAR(255) NULL,
            es_destacada TINYINT(1) NOT NULL DEFAULT 0,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function categorias_normalizar_imagen(?string $ruta): string
{
    if ($ruta === null) {
        return '';
    }

    $rutaNormalizada = trim(str_replace('\\', '/', $ruta));
    $rutaNormalizada = ltrim($rutaNormalizada, '/');

    return $rutaNormalizada;
}

function categorias_imagen_absoluta(string $rutaRelativa): string
{
    return __DIR__ . '/' . ltrim($rutaRelativa, '/');
}

function categorias_normalizar_fila(array $row): array
{
    $id = isset($row['id']) ? (int)$row['id'] : 0;
    $nombre = trim((string)($row['nombre'] ?? ''));
    $descripcion = trim((string)($row['descripcion'] ?? ''));
    $imagen = categorias_normalizar_imagen($row['imagen'] ?? '');

    $imagenCompleta = $imagen !== '' ? categorias_imagen_absoluta($imagen) : '';
    $tieneImagen = $imagen !== '' && is_file($imagenCompleta);

    return [
        'id' => $id,
        'nombre' => $nombre,
        'descripcion' => $descripcion,
        'imagen' => $imagen,
        'tiene_imagen' => $tieneImagen,
        'es_destacada' => !empty($row['es_destacada']),
        'actualizado_en' => $row['actualizado_en'] ?? null,
    ];
}

function categorias_obtener_todas(PDO $pdo): array
{
    categorias_ensure_schema($pdo);

    $stmt = $pdo->query('SELECT id, nombre, descripcion, imagen, es_destacada, actualizado_en FROM categorias ORDER BY nombre ASC');
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map('categorias_normalizar_fila', $filas);
}

function categorias_obtener_destacadas(PDO $pdo, int $limite = 8): array
{
    categorias_ensure_schema($pdo);

    $stmt = $pdo->prepare('SELECT id, nombre, descripcion, imagen FROM categorias WHERE es_destacada = 1 ORDER BY nombre ASC LIMIT :limite');
    $stmt->bindValue(':limite', max($limite, 1), PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $destacadas = [];
    foreach ($filas as $fila) {
        $normalizada = categorias_normalizar_fila($fila);
        if ($normalizada['tiene_imagen']) {
            $destacadas[] = [
                'id' => $normalizada['id'],
                'nombre' => $normalizada['nombre'],
                'descripcion' => $normalizada['descripcion'],
                'imagen' => $normalizada['imagen'],
            ];
        }
    }

    return $destacadas;
}

function categorias_crear(PDO $pdo, string $nombre, ?string $descripcion, string $imagen): int
{
    categorias_ensure_schema($pdo);

    $stmt = $pdo->prepare('INSERT INTO categorias (nombre, descripcion, imagen) VALUES (:nombre, :descripcion, :imagen)');
    $stmt->execute([
        ':nombre' => $nombre,
        ':descripcion' => $descripcion !== null && $descripcion !== '' ? $descripcion : null,
        ':imagen' => $imagen,
    ]);

    return (int)$pdo->lastInsertId();
}

function categorias_actualizar(PDO $pdo, int $id, string $nombre, ?string $descripcion, ?string $imagen = null): void
{
    categorias_ensure_schema($pdo);

    $sql = 'UPDATE categorias SET nombre = :nombre, descripcion = :descripcion';
    $params = [
        ':nombre' => $nombre,
        ':descripcion' => $descripcion !== null && $descripcion !== '' ? $descripcion : null,
        ':id' => $id,
    ];

    if ($imagen !== null) {
        $sql .= ', imagen = :imagen';
        $params[':imagen'] = $imagen;
    }

    $sql .= ' WHERE id = :id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function categorias_obtener_nombres(PDO $pdo, array $ids): array
{
    $limpios = [];
    foreach ($ids as $valor) {
        if (is_numeric($valor)) {
            $entero = (int)$valor;
            if ($entero > 0) {
                $limpios[$entero] = $entero;
            }
        }
    }

    if (empty($limpios)) {
        return [];
    }

    categorias_ensure_schema($pdo);
    $placeholders = implode(',', array_fill(0, count($limpios), '?'));
    $stmt = $pdo->prepare("SELECT id, nombre FROM categorias WHERE id IN ($placeholders)");
    $stmt->execute(array_values($limpios));

    $nombres = [];
    while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nombres[(int)$fila['id']] = trim((string)$fila['nombre']);
    }

    return $nombres;
}

function categorias_set_featured(PDO $pdo, array $ids): array
{
    categorias_ensure_schema($pdo);

    $normalizados = [];
    foreach ($ids as $valor) {
        if (is_numeric($valor)) {
            $entero = (int)$valor;
            if ($entero > 0) {
                $normalizados[$entero] = $entero;
            }
        }
    }

    $permitidos = [];
    $omitidos = [];

    if (!empty($normalizados)) {
        $placeholders = implode(',', array_fill(0, count($normalizados), '?'));
        $stmt = $pdo->prepare("SELECT id, imagen FROM categorias WHERE id IN ($placeholders)");
        $stmt->execute(array_values($normalizados));

        $encontrados = [];
        while ($fila = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$fila['id'];
            $encontrados[$id] = true;
            $imagen = categorias_normalizar_imagen($fila['imagen'] ?? '');
            $tieneImagen = $imagen !== '' && is_file(categorias_imagen_absoluta($imagen));
            if ($tieneImagen) {
                $permitidos[] = $id;
            } else {
                $omitidos[$id] = 'missing_image';
            }
        }

        foreach ($normalizados as $id) {
            if (!isset($encontrados[$id])) {
                $omitidos[$id] = 'not_found';
            }
        }
    }

    $pdo->exec('UPDATE categorias SET es_destacada = 0');

    if (!empty($permitidos)) {
        $placeholders = implode(',', array_fill(0, count($permitidos), '?'));
        $stmt = $pdo->prepare("UPDATE categorias SET es_destacada = 1 WHERE id IN ($placeholders)");
        $stmt->execute($permitidos);
    }

    return [
        'applied' => $permitidos,
        'skipped' => $omitidos,
    ];
}
