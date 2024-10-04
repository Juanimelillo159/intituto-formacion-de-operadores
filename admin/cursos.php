<?php

require_once '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

// Parámetros de paginación
$limit = 12; // Cursos por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $limit) - $limit : 0;

// Obtener el número total de cursos
$sql_count = $con->prepare("SELECT COUNT(*) as total FROM cursos");
$sql_count->execute();
$totalCursos = $sql_count->fetch(PDO::FETCH_ASSOC)['total'];

// Calcular el número total de páginas
$totalPages = ceil($totalCursos / $limit);

// Obtener los cursos de la página actual
$sql_curso = $con->prepare("SELECT * FROM cursos LIMIT :start, :limit");
$sql_curso->bindValue(':start', $start, PDO::PARAM_INT);
$sql_curso->bindValue(':limit', $limit, PDO::PARAM_INT);
$sql_curso->execute();
$cursos = $sql_curso->fetchAll(PDO::FETCH_ASSOC);
?>
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
            <section class="content">
                <div class="container-fluid">
                    <div id="cursos" class="section-padding">
                        <div class="container">
                            <h2 class="dispaly-2 text-center mb-4">Cursos Disponibles</h2>
                            <div class="mb-4 text-center">
                                <input type="text" id="search-input" placeholder="Buscar cursos..." class="form-control w-50 d-inline-block">
                            </div>
                            <div id="lista_curso"> </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <script src="js/app.js"></script>
</body>

</html>