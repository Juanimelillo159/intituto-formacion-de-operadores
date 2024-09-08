<?php

include '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$sql_banner = $con->prepare("SELECT * FROM banner");
$sql_banner->execute();
$banners = $sql_banner->fetchAll(PDO::FETCH_ASSOC);


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar curso</title>
    <style>
        #imageCarousel {
            max-width: 800px;
            margin: 0 auto;
        }

        .carousel-item img {
            height: 400px;
            object-fit: cover;
        }

        .carousel-control-prev,
        .carousel-control-next {
            width: 5%;
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
                    <div class="row">
                        <!-- left column -->
                        <div class="col-md-12">
                            <!-- general form elements -->
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">Carrusel</h3>
                                </div>
                                <!-- /.card-header -->
                                <div id="carruselbanner" class="carousel slide" data-ride="carousel">
                                    <ol class="carousel-indicators">
                                        <?php foreach ($banners as $index => $banner) { ?>
                                            <li data-target="#carruselbanner" data-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active"' : ''; ?>></li>
                                        <?php } ?>
                                    </ol>
                                    <div class="carousel-inner">
                                        <?php foreach ($banners as $index => $banner) { ?>
                                            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                                                <img class="d-block w-100" src="../imagenes/banners/<?php echo $banner['imagen_banner']; ?>" alt="Slide <?php echo $index + 1; ?>">
                                                <div class="carousel-caption d-none d-md-block">
                                                    <h5><?php echo $banner['nombre_banner']; ?></h5>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <a class="carousel-control-prev" href="#carruselbanner" role="button" data-slide="prev">
                                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                        <span class="sr-only">Previous</span>
                                    </a>
                                    <a class="carousel-control-next" href="#carruselbanner" role="button" data-slide="next">
                                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                        <span class="sr-only">Next</span>
                                    </a>
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
                                    <h3 class="card-title">Imagenes</h3>
                                </div>
                                <!-- /.card-header -->
                                <div class="card-body">
                                    <table id="registros" class="table table-bordered table-striped">
                                        <thead>
                                            <tr>
                                                <th>nombre</th>
                                                <th>imagen</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($banners as $banner) { ?>
                                                <tr>
                                                    <td> <?php echo $banner['nombre_banner'] ?> </td>
                                                    <td> <?php echo $banner['imagen_banner'] ?> </td>
                                                    <td>
                                                        <a href="editar_banner.php?id_banner=<?php echo $banner['id_banner']; ?>" type="button" class="btn bg-orange btn-flat margin"> <i class="fas fa-user-edit"></i></a>

                                                        <a href="eliminar_banner.php?id_banner=<?php echo $banner['id_banner']; ?>" type="button" class="btn bg-maroon btn-flat margin"><i class="fa fa-trash" aria-hidden="true"></i></a>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>imagen</th>
                                                <th>Acciones</th>

                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <!-- /.card-body -->
                            </div>
                            <!-- /.card -->
                        </div>
                        <!-- /.col -->
                    </div>
                    <!-- /.row -->
                </div><!-- /.container-fluid -->
            </section>
        </div>
        <!-- /.content-wrapper -->

    </div>
    <!-- ./wrapper -->
</body>

</html>