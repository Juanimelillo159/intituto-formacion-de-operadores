<?php
require_once '../sbd.php';
require_once '../categorias_helpers.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$mensajeExito = null;
$mensajeError = null;
$mensajesAdvertencia = [];

try {
    categorias_ensure_schema($con);
} catch (Throwable $schemaException) {
    $mensajeError = 'No se pudo preparar la tabla de categorías: ' . $schemaException->getMessage();
}

function categorias_procesar_imagen_upload(string $campo, ?string $imagenAnterior = null): ?string
{
    if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo])) {
        return null;
    }

    $archivo = $_FILES[$campo];
    $error = (int)($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen.');
    }

    $tmpName = $archivo['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpName)) {
        throw new RuntimeException('El archivo subido no es válido.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $tmpName) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    $permitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    if ($mimeType === false || $mimeType === null || !isset($permitidos[$mimeType])) {
        throw new RuntimeException('El formato de la imagen no está permitido.');
    }

    $directorio = __DIR__ . '/../uploads/categorias';
    if (!is_dir($directorio)) {
        if (!mkdir($directorio, 0755, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio para las imágenes.');
        }
    }

    try {
        $nombreArchivo = 'cat_' . bin2hex(random_bytes(8)) . '.' . $permitidos[$mimeType];
    } catch (Throwable $randomException) {
        throw new RuntimeException('No se pudo generar el nombre del archivo de imagen.');
    }

    $destino = $directorio . '/' . $nombreArchivo;
    if (!move_uploaded_file($tmpName, $destino)) {
        throw new RuntimeException('No se pudo guardar la imagen subida.');
    }

    if ($imagenAnterior !== null && $imagenAnterior !== '') {
        $anteriorNormalizada = categorias_normalizar_imagen($imagenAnterior);
        $rutaAnterior = __DIR__ . '/../' . $anteriorNormalizada;
        if (is_file($rutaAnterior)) {
            @unlink($rutaAnterior);
        }
    }

    return 'uploads/categorias/' . $nombreArchivo;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $mensajeError === null) {
    $accion = $_POST['action'] ?? '';

    try {
        switch ($accion) {
            case 'crear_categoria':
                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $descripcion = trim((string)($_POST['descripcion'] ?? ''));

                if ($nombre === '') {
                    throw new InvalidArgumentException('El nombre de la categoría es obligatorio.');
                }

                $imagenNueva = categorias_procesar_imagen_upload('imagen');
                if ($imagenNueva === null) {
                    throw new RuntimeException('Debes subir una imagen para la categoría.');
                }

                categorias_crear($con, $nombre, $descripcion, $imagenNueva);
                $mensajeExito = 'La categoría se creó correctamente.';
                break;

            case 'actualizar_categoria':
                $categoriaId = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
                $nombre = trim((string)($_POST['nombre'] ?? ''));
                $descripcion = trim((string)($_POST['descripcion'] ?? ''));
                $imagenActual = categorias_normalizar_imagen($_POST['imagen_actual'] ?? '');

                if ($categoriaId <= 0) {
                    throw new InvalidArgumentException('La categoría seleccionada no es válida.');
                }

                if ($nombre === '') {
                    throw new InvalidArgumentException('El nombre de la categoría es obligatorio.');
                }

                $imagenNueva = categorias_procesar_imagen_upload('imagen', $imagenActual !== '' ? $imagenActual : null);
                categorias_actualizar($con, $categoriaId, $nombre, $descripcion, $imagenNueva);
                $mensajeExito = 'La categoría se actualizó correctamente.';
                break;

            case 'actualizar_destacadas':
                $seleccionadas = isset($_POST['featured_ids']) && is_array($_POST['featured_ids']) ? $_POST['featured_ids'] : [];
                $resultadoDestacadas = categorias_set_featured($con, $seleccionadas);

                $cantidadAplicadas = count($resultadoDestacadas['applied']);
                if ($cantidadAplicadas === 0) {
                    $mensajeExito = 'No hay categorías destacadas activas en este momento.';
                } elseif ($cantidadAplicadas === 1) {
                    $mensajeExito = 'Se destacó una categoría.';
                } else {
                    $mensajeExito = 'Se destacaron ' . $cantidadAplicadas . ' categorías.';
                }

                if (!empty($resultadoDestacadas['skipped'])) {
                    $nombresCategorias = categorias_obtener_nombres($con, array_keys($resultadoDestacadas['skipped']));
                    foreach ($resultadoDestacadas['skipped'] as $idCategoria => $motivo) {
                        $nombreCategoria = $nombresCategorias[$idCategoria] ?? ('ID ' . (int)$idCategoria);
                        if ($motivo === 'missing_image') {
                            $mensajesAdvertencia[] = sprintf('La categoría "%s" no tiene una imagen cargada y no se pudo destacar.', $nombreCategoria);
                        } else {
                            $mensajesAdvertencia[] = sprintf('No se encontró la categoría con identificador %s.', (int)$idCategoria);
                        }
                    }
                }
                break;

            default:
                $mensajeError = 'No se reconoció la acción solicitada.';
                break;
        }
    } catch (Throwable $categoriaException) {
        $mensajeError = $categoriaException->getMessage();
    }
}

try {
    $categorias = categorias_obtener_todas($con);
} catch (Throwable $listException) {
    $categorias = [];
    if ($mensajeError === null) {
        $mensajeError = 'No se pudo obtener el listado de categorías: ' . $listException->getMessage();
    }
}

$idsDestacadas = [];
foreach ($categorias as $categoria) {
    if (!empty($categoria['es_destacada'])) {
        $idsDestacadas[] = (int)$categoria['id'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Categorías</title>
    <style>
        .categoria-card-img {
            width: 100%;
            border-radius: 0.5rem;
            object-fit: cover;
            max-height: 160px;
        }

        .categoria-card-img--empty {
            background: repeating-linear-gradient(135deg, #f5f5f5, #f5f5f5 10px, #e9ecef 10px, #e9ecef 20px);
            height: 160px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-weight: 600;
        }

        .categoria-form label {
            font-weight: 600;
        }

        .categoria-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(0, 189, 163, 0.1);
            color: #008f7d;
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <div class="content-wrapper">
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0">Categorías</h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="admin.php">Inicio</a></li>
                                <li class="breadcrumb-item active">Categorías</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </section>

            <section class="content">
                <div class="container-fluid">
                    <?php if ($mensajeExito !== null): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-circle-check mr-2"></i><?php echo htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($mensajeError !== null): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-circle-xmark mr-2"></i><?php echo htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($mensajesAdvertencia)): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <i class="fas fa-triangle-exclamation mr-2"></i>
                            <ul class="mb-0 pl-3">
                                <?php foreach ($mensajesAdvertencia as $mensaje): ?>
                                    <li><?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-lg-5">
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title mb-0">Nueva categoría</h3>
                                </div>
                                <form class="categoria-form" method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="crear_categoria">
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="categoriaNombre">Nombre</label>
                                            <input type="text" id="categoriaNombre" name="nombre" class="form-control" placeholder="Ej: Seguridad industrial" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="categoriaDescripcion">Descripción</label>
                                            <textarea id="categoriaDescripcion" name="descripcion" class="form-control" rows="3" placeholder="Descripción breve (opcional)"></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="categoriaImagen">Imagen destacada</label>
                                            <input type="file" id="categoriaImagen" name="imagen" class="form-control-file" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
                                            <small class="form-text text-muted">Formatos admitidos: JPG, PNG, WEBP o SVG.</small>
                                        </div>
                                    </div>
                                    <div class="card-footer d-flex justify-content-end">
                                        <button type="submit" class="btn btn-primary">Guardar categoría</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
                                    <h3 class="card-title mb-0">Categorías registradas</h3>
                                    <?php if (count($categorias) > 0): ?>
                                        <span class="badge badge-secondary">Total: <?php echo count($categorias); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($categorias)): ?>
                                        <p class="text-muted mb-0">Aún no se registraron categorías. Crea la primera para comenzar.</p>
                                    <?php else: ?>
                                        <div class="mb-4">
                                            <h5 class="mb-3">Seleccionar categorías destacadas</h5>
                                            <form method="post">
                                                <input type="hidden" name="action" value="actualizar_destacadas">
                                                <div class="row">
                                                    <?php foreach ($categorias as $categoria): ?>
                                                        <div class="col-md-6">
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="checkbox" id="destacada_<?php echo (int)$categoria['id']; ?>" name="featured_ids[]" value="<?php echo (int)$categoria['id']; ?>" <?php echo in_array((int)$categoria['id'], $idsDestacadas, true) ? 'checked' : ''; ?> <?php echo $categoria['tiene_imagen'] ? '' : 'disabled'; ?>>
                                                                <label class="form-check-label" for="destacada_<?php echo (int)$categoria['id']; ?>">
                                                                    <?php echo htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                                                    <?php if (!$categoria['tiene_imagen']): ?>
                                                                        <span class="badge badge-warning ml-1">Sin imagen</span>
                                                                    <?php endif; ?>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-sm mt-2">Guardar destacadas</button>
                                            </form>
                                        </div>

                                        <div class="accordion" id="accordionCategorias">
                                            <?php foreach ($categorias as $index => $categoria): ?>
                                                <div class="card mb-3">
                                                    <div class="card-header" id="heading-<?php echo (int)$categoria['id']; ?>">
                                                        <h5 class="mb-0 d-flex align-items-center justify-content-between">
                                                            <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapse-<?php echo (int)$categoria['id']; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse-<?php echo (int)$categoria['id']; ?>">
                                                                <?php echo htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                                            </button>
                                                            <?php if (!empty($categoria['es_destacada'])): ?>
                                                                <span class="categoria-chip"><i class="fas fa-star"></i> Destacada</span>
                                                            <?php endif; ?>
                                                        </h5>
                                                    </div>
                                                    <div id="collapse-<?php echo (int)$categoria['id']; ?>" class="collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading-<?php echo (int)$categoria['id']; ?>" data-parent="#accordionCategorias">
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-5 mb-3 mb-md-0">
                                                                    <?php if ($categoria['tiene_imagen']): ?>
                                                                        <img src="../<?php echo htmlspecialchars($categoria['imagen'], ENT_QUOTES, 'UTF-8'); ?>" alt="Imagen de la categoría <?php echo htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8'); ?>" class="categoria-card-img">
                                                                    <?php else: ?>
                                                                        <div class="categoria-card-img--empty">Sin imagen</div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="col-md-7">
                                                                    <form class="categoria-form" method="post" enctype="multipart/form-data">
                                                                        <input type="hidden" name="action" value="actualizar_categoria">
                                                                        <input type="hidden" name="categoria_id" value="<?php echo (int)$categoria['id']; ?>">
                                                                        <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($categoria['imagen'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                        <div class="form-group">
                                                                            <label for="nombre_<?php echo (int)$categoria['id']; ?>">Nombre</label>
                                                                            <input type="text" id="nombre_<?php echo (int)$categoria['id']; ?>" name="nombre" class="form-control" value="<?php echo htmlspecialchars($categoria['nombre'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label for="descripcion_<?php echo (int)$categoria['id']; ?>">Descripción</label>
                                                                            <textarea id="descripcion_<?php echo (int)$categoria['id']; ?>" name="descripcion" class="form-control" rows="3" placeholder="Descripción breve"><?php echo htmlspecialchars($categoria['descripcion'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label for="imagen_<?php echo (int)$categoria['id']; ?>">Actualizar imagen</label>
                                                                            <input type="file" id="imagen_<?php echo (int)$categoria['id']; ?>" name="imagen" class="form-control-file" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                                                                            <small class="form-text text-muted">Sube una imagen para reemplazar la actual.</small>
                                                                        </div>
                                                                        <div class="d-flex justify-content-end">
                                                                            <button type="submit" class="btn btn-outline-primary btn-sm">Guardar cambios</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</body>

</html>
