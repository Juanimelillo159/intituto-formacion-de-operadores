<?php
include '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar curso</title>

</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">


        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h2>Agregar imagen</h2>
                        </div>
                    </div>
                </div><!-- /.container-fluid -->
            </section>

            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <!-- left column -->
                        <div class="col-md-12">
                            <!-- general form elements -->
                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">Detalle imagen</h3>
                                </div>
                                <!-- /.card-header -->
                                <!-- form start -->
                                <form action="procesarsbd.php" method="POST" enctype="multipart/form-data">
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="nombre_banner">Nombre</label>
                                            <input required type="text" class="form-control" id="nombre_banner" name="nombre_banner" placeholder="Ingrese nombre del banner">
                                        </div>
                                        <div class="form-group">
                                            <label for="imagen_banner">Subir Imagen</label>
                                            <input required type="file" class="form-control-file" id="imagen_banner" name="imagen_banner" accept="image/*">
                                        </div>

                                        <div class="card-footer">
                                            <button type="button" onclick="volver()" class="btn btn-primary">Volver</button>
                                            <button type="submit" name="agregar_banner" class="btn btn-success">Guardar</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <!-- /.card -->
                        </div>
                        <!--/.col (left) -->
                    </div>
                    <!-- /.row -->
                </div><!-- /.container-fluid -->
            </section>
            <!-- /.content -->
        </div>
        <!-- /.content-wrapper -->

    </div>
    <!-- ./wrapper -->
</body>
<script>
    function volver() {
        window.location.href = 'carrusel.php';
    }
</script>

</html>