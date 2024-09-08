<?php
include '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

if(isset($_GET["id_banner"])){
    $id_banner = $_GET["id_banner"];
    $sql_banner = $con->prepare("SELECT * FROM banner WHERE id_banner = :id_banner");
    $sql_banner->bindParam(':id_banner', $id_banner);
    $sql_banner->execute();
    $banner = $sql_banner->fetch(PDO::FETCH_ASSOC);
}
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
                                    <input type="hidden" name="id_banner" value="<?php echo $banner["id_banner"]?>">
                                    <div class="card-body">
                                        <div class="form-group">
                                            <label for="nombre_banner">Nombre</label>
                                            <input required type="text" class="form-control" id="nombre_banner" name="nombre_banner" placeholder="Ingrese nombre del banner"
                                            value="<?php echo $banner["nombre_banner"]?>">
                                            
                                        </div>
                                        <div class="form-group">
                                            <label for="imagen_banner">Subir Imagen</label>
                                            <input type="file" class="form-control-file" id="imagen_banner" name="imagen_banner" accept="image/*">
                                        </div>

                                        <div class="card-footer">
                                            <button type="button" onclick="volver()" class="btn btn-primary">Volver</button>
                                            <button type="submit" name="editar_banner" class="btn btn-success">Guardar</button>
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