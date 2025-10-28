<?php

include '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$sql_banner = $con->prepare("SELECT * FROM banner");
$sql_banner->execute();
$banners = $sql_banner->fetchAll(PDO::FETCH_ASSOC);

$bannerSuccess = $_SESSION['banner_success'] ?? null;
$bannerError = $_SESSION['banner_error'] ?? null;
unset($_SESSION['banner_success'], $_SESSION['banner_error']);


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar curso</title>
    <style>
        #carruselbanner {
            max-width: 900px;
            margin: 0 auto;
        }

        #carruselbanner .carousel-item,
        .banner-preview-box {
            position: relative;
            padding-top: 42.5%;
            border-radius: 0.75rem;
            overflow: hidden;
            background: #f1f1f1;
        }

        #carruselbanner .carousel-item img,
        .banner-preview-box img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .carousel-control-prev,
        .carousel-control-next {
            width: 5%;
        }

        .banner-card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08);
            border: 0;
            border-radius: 0.75rem;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .banner-card .card-body {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .banner-card .card-title {
            font-size: 1rem;
            margin-bottom: 0;
            word-break: break-word;
        }

        .badge-dimension {
            font-size: 0.85rem;
            background: #f0f0f0;
            color: #555;
        }

        .banner-empty-state {
            text-align: center;
            padding: 2.5rem 1rem;
            border: 2px dashed #d9d9d9;
            border-radius: 0.75rem;
            color: #777;
        }

        .banner-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .carousel-header {
            gap: 0.75rem;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">


        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <?php if ($bannerSuccess) { ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa fa-check-circle mr-2"></i><?php echo htmlspecialchars($bannerSuccess, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php } elseif ($bannerError) { ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa fa-exclamation-triangle mr-2"></i><?php echo htmlspecialchars($bannerError, ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php } ?>
                    <div class="row">
                        <!-- left column -->
                        <div class="col-md-12">
                            <!-- general form elements -->
                                <div class="card card-primary">
                                <div class="card-header d-flex align-items-center justify-content-between flex-wrap carousel-header">
                                    <div>
                                        <h3 class="card-title mb-0">Carrusel</h3>
                                        <small class="d-block text-muted">Las imágenes se muestran en un formato panorámico. Tamaño sugerido: 1600×680&nbsp;px.</small>
                                    </div>
                                    <button type="button" class="btn btn-light" data-toggle="modal" data-target="#modalAgregarBanner">
                                        <i class="fa fa-plus mr-1"></i> Agregar imagen
                                    </button>
                                </div>
                                <!-- /.card-header -->
                                <div class="card-body">
                                    <?php if (count($banners) > 0) { ?>
                                        <div id="carruselbanner" class="carousel slide mb-3" data-ride="carousel">
                                            <ol class="carousel-indicators">
                                                <?php foreach ($banners as $index => $banner) { ?>
                                                    <li data-target="#carruselbanner" data-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active"' : ''; ?>></li>
                                                <?php } ?>
                                            </ol>
                                            <div class="carousel-inner">
                                                <?php foreach ($banners as $index => $banner) { ?>
                                                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                                        <img class="d-block w-100" src="../assets/imagenes/banners/<?php echo htmlspecialchars($banner['imagen_banner'], ENT_QUOTES, 'UTF-8'); ?>" alt="Slide <?php echo $index + 1; ?>">
                                                        <div class="carousel-caption d-none d-md-block">
                                                            <h5><?php echo htmlspecialchars($banner['nombre_banner'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                            <a class="carousel-control-prev" href="#carruselbanner" role="button" data-slide="prev">
                                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                <span class="sr-only">Anterior</span>
                                            </a>
                                            <a class="carousel-control-next" href="#carruselbanner" role="button" data-slide="next">
                                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                <span class="sr-only">Siguiente</span>
                                            </a>
                                        </div>
                                        <p class="mb-0 text-muted">Así se mostrará el carrusel en la página principal.</p>
                                    <?php } else { ?>
                                        <div class="banner-empty-state mb-0">
                                            <p class="mb-2">Todavía no hay imágenes para previsualizar.</p>
                                            <p class="mb-0">Agrega una imagen para ver cómo lucirá el carrusel en la página principal.</p>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <!-- /.card -->
                        </div>
                        <!--/.col (left) -->
                    </div>
                    <!-- /.row -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title mb-0">Gestionar imágenes</h3>
                                </div>
                                <div class="card-body">
                                    <?php if (count($banners) === 0) { ?>
                                        <div class="banner-empty-state">
                                            <p class="mb-2">Todavía no hay imágenes en el carrusel.</p>
                                            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#modalAgregarBanner">
                                                <i class="fa fa-plus mr-1"></i> Agregar la primera imagen
                                            </button>
                                        </div>
                                    <?php } else { ?>
                                        <div class="row">
                                            <?php foreach ($banners as $banner) { ?>
                                                <div class="col-md-6 col-lg-4 mb-4 d-flex">
                                                    <div class="card banner-card w-100" data-banner-id="<?php echo (int) $banner['id_banner']; ?>">
                                                        <div class="banner-preview-box">
                                                            <img src="../assets/imagenes/banners/<?php echo htmlspecialchars($banner['imagen_banner'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($banner['nombre_banner'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        </div>
                                                        <div class="card-body">
                                                            <div>
                                                                <span class="badge badge-dimension mb-2">Formato sugerido 1600×680 px</span>
                                                                <h5 class="card-title"><?php echo htmlspecialchars($banner['nombre_banner'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                                            </div>
                                                            <div class="banner-actions">
                                                                <a href="editar_banner.php?id_banner=<?php echo (int) $banner['id_banner']; ?>" class="btn btn-outline-secondary flex-fill">
                                                                    <i class="fas fa-user-edit mr-1"></i> Editar
                                                                </a>
                                                                <button type="button" class="btn btn-outline-danger flex-fill js-eliminar-banner" data-id="<?php echo (int) $banner['id_banner']; ?>" data-nombre="<?php echo htmlspecialchars($banner['nombre_banner'], ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <i class="fa fa-trash mr-1"></i> Eliminar
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /.container-fluid -->
            </section>
        </div>
        <!-- /.content-wrapper -->

    </div>
    <!-- ./wrapper -->
<div class="modal fade" id="modalAgregarBanner" tabindex="-1" role="dialog" aria-labelledby="modalAgregarBannerLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form class="modal-content" action="procesarsbd.php" method="POST" enctype="multipart/form-data">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAgregarBannerLabel">Agregar imagen al carrusel</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="agregar_banner" value="1">
                <input type="hidden" name="redirect_to" value="carrusel.php">
                <div class="form-group">
                    <label for="nombre_banner">Nombre de la imagen</label>
                    <input type="text" class="form-control" id="nombre_banner" name="nombre_banner" maxlength="120" required>
                </div>
                <div class="form-group">
                    <label for="imagen_banner">Archivo (formato JPG o PNG, tamaño sugerido 1600×680 px)</label>
                    <input type="file" class="form-control" id="imagen_banner" name="imagen_banner" accept="image/*" required>
                    <div id="preview_banner" class="banner-preview-box mt-3 d-none">
                        <img src="" alt="Previsualización de la imagen seleccionada" id="preview_banner_img">
                    </div>
                    <small class="form-text text-muted">La vista previa respeta el alto y ancho con los que se mostrará la imagen en el carrusel.</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-upload mr-1"></i> Guardar imagen
                </button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalConfirmarEliminacion" tabindex="-1" role="dialog" aria-labelledby="modalConfirmarEliminacionLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalConfirmarEliminacionLabel">Eliminar imagen</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>¿Quieres eliminar la imagen <strong class="js-banner-nombre">seleccionada</strong> del carrusel? Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger js-confirmar-eliminacion">
                    <i class="fa fa-trash mr-1"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        const previewBox = document.getElementById('preview_banner');
        const previewImg = document.getElementById('preview_banner_img');
        const inputBanner = document.getElementById('imagen_banner');

        if (inputBanner && previewBox && previewImg) {
            inputBanner.addEventListener('change', function () {
                if (!this.files || this.files.length === 0) {
                    previewBox.classList.add('d-none');
                    previewImg.removeAttribute('src');
                    previewImg.removeAttribute('alt');
                    return;
                }

                const file = this.files[0];
                if (!file || !file.type.startsWith('image/')) {
                    previewBox.classList.add('d-none');
                    previewImg.removeAttribute('src');
                    previewImg.removeAttribute('alt');
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    const result = event && event.target ? event.target.result : null;
                    if (result) {
                        previewImg.src = result;
                        previewImg.alt = file.name;
                        previewBox.classList.remove('d-none');
                    }
                };
                reader.readAsDataURL(file);
            });
        }

        const deleteButtons = document.querySelectorAll('.js-eliminar-banner');
        const confirmModal = document.getElementById('modalConfirmarEliminacion');
        const confirmButton = confirmModal ? confirmModal.querySelector('.js-confirmar-eliminacion') : null;
        const confirmName = confirmModal ? confirmModal.querySelector('.js-banner-nombre') : null;
        let bannerPendingDeletion = null;

        function ejecutarEliminacion(button, bannerId) {
            const formData = new FormData();
            formData.append('id_banner', bannerId);
            formData.append('ajax', '1');

            button.disabled = true;

            fetch('eliminar_banner.php', {
                method: 'POST',
                body: formData,
            })
                .then(async (response) => {
                    const contentType = response.headers.get('Content-Type') || '';
                    let payload = null;

                    if (contentType.includes('application/json')) {
                        try {
                            payload = await response.json();
                        } catch (error) {
                            payload = null;
                        }
                    }

                    if (!response.ok) {
                        const message = payload && payload.message ? payload.message : 'No se pudo eliminar la imagen.';
                        throw new Error(message);
                    }

                    if (!payload) {
                        payload = { ok: true };
                    }

                    if (!payload.ok) {
                        const message = payload.message ? payload.message : 'No se pudo eliminar la imagen.';
                        throw new Error(message);
                    }

                    return payload;
                })
                .then(() => {
                    if (confirmModal && typeof window.$ === 'function') {
                        window.$(confirmModal).modal('hide');
                    }

                    const card = button.closest('[data-banner-id]');
                    if (card) {
                        const col = card.parentElement;
                        if (col) {
                            col.remove();
                        } else {
                            card.remove();
                        }
                        if (document.querySelectorAll('.banner-card').length === 0) {
                            window.location.reload();
                        }
                    } else {
                        window.location.reload();
                    }
                })
                .catch((error) => {
                    const message = error && error.message ? error.message : 'Ocurrió un error inesperado. Intente nuevamente.';
                    window.alert(message);
                })
                .finally(() => {
                    button.disabled = false;
                    bannerPendingDeletion = null;
                });
        }

        if (deleteButtons.length > 0) {
            deleteButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    const bannerId = this.getAttribute('data-id');
                    const bannerName = this.getAttribute('data-nombre') || 'la imagen seleccionada';

                    if (!bannerId) {
                        return;
                    }

                    if (confirmModal && typeof window.$ === 'function') {
                        bannerPendingDeletion = { button: this, id: bannerId };
                        if (confirmName) {
                            confirmName.textContent = bannerName;
                        }
                        window.$(confirmModal).modal('show');
                    } else {
                        const confirmado = window.confirm('¿Eliminar "' + bannerName + '" del carrusel? Esta acción no se puede deshacer.');
                        if (!confirmado) {
                            return;
                        }
                        ejecutarEliminacion(this, bannerId);
                    }
                });
            });
        }

        if (confirmModal && confirmButton) {
            confirmButton.addEventListener('click', function () {
                if (!bannerPendingDeletion || !bannerPendingDeletion.id) {
                    return;
                }
                ejecutarEliminacion(bannerPendingDeletion.button, bannerPendingDeletion.id);
            });

            if (typeof window.$ === 'function') {
                window.$(confirmModal).on('hidden.bs.modal', function () {
                    bannerPendingDeletion = null;
                });
            }
        }
    })();
</script>

</body>

</html>
