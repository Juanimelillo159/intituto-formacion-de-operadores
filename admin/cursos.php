<?php
require_once '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$sql_curso = $con->prepare("SELECT * FROM cursos");
$sql_curso->execute();
$cursos = $sql_curso->fetchall(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cursos</title>
</head>

<body>
    <div class="wrapper">
        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h2>Cursos</h2>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active">Cursos</li>
                            </ol>
                        </div>
                    </div>
                </div><!-- /.container-fluid -->
            </section>

            <section class="content">
                <div class="container-fluid">
                    <h5 class="mb-2">info boxes</h5>

                    <!-- Input de búsqueda -->
                    <div class="mb-4">
                        <input type="text" id="search-input" class="form-control" placeholder="Buscar curso...">
                    </div>

                    <div class="row d-flex" id="course-container">
                        <?php foreach ($cursos as $curso) { ?>
                            <div class="col-md-3 d-flex align-items-stretch course-card">
                                <div class="card card-primary">
                                    <div class="card-header">
                                        <h3 class="card-title text-center" style="min-height: 50px; display: flex; align-items: center; justify-content: center;">
                                            <?php echo $curso["nombre_curso"] ?>
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <?php echo $curso["descripcion_curso"] ?>
                                    </div>
                                    <div class="card-footer text-center">
                                        <a href="eliminar_curso.php?id_curso=<?php echo $curso['id_curso']; ?>"><button class="btn btn-danger mx-2">Eliminar</button></a>
                                        <a href="curso.php?id_curso=<?php echo $curso['id_curso']; ?>"><button class="btn btn-primary mx-2">ver</button></a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </section>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.getElementById('search-input').addEventListener('input', function() {
                        var searchValue = this.value.toLowerCase();
                        var courseCards = document.querySelectorAll('.course-card');

                        console.log('Valor de búsqueda:', searchValue);

                        courseCards.forEach(function(card) {
                            var courseTitleElement = card.querySelector('.card-title');
                            if (courseTitleElement) {
                                var courseTitle = courseTitleElement.textContent.toLowerCase().trim();
                                console.log('Título del curso:', courseTitle);

                                if (courseTitle.includes(searchValue)) {
                                    card.style.display = ''; // Mostrar tarjeta
                                } else {
                                    card.style.display = 'none'; // Ocultar tarjeta
                                }
                            }
                        });
                    });
                });
            </script>


</body>

</html>