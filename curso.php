<?php 
    require_once 'sbd.php';
    include ("nav.php");
    $id_curso = $_GET['id_curso'];


    $sql_cursos = $con->prepare("SELECT * FROM cursos c JOIN niveles n ON c.id_complejidad = n.id_complejidad WHERE c.id = :id");
    $sql_cursos->bindParam(':id', $id_curso);
    $sql_cursos->execute();
    $curso = $sql_cursos->fetch(PDO::FETCH_ASSOC);

    

    $sql_complejidad = $con->prepare("SELECT m.id AS modalidad_id, m.nombre AS modalidad_nombre FROM curso_modalidad cm JOIN modalidades m ON cm.id_modalidad = m.id WHERE cm.id_curso = :id");
    $sql_complejidad->bindParam(':id', $id_curso);
    $sql_complejidad->execute();
    $modalidades = $sql_complejidad->fetchAll(PDO::FETCH_ASSOC);

    $modalidad_nombres = array();

    foreach ($modalidades as $value) {
        // Accede al nombre de la modalidad y lo agrega al array
        $modalidad_nombres[] = htmlspecialchars($value['modalidad_nombre']);
    }
    $modalidad_nombres_str = implode(' - ', $modalidad_nombres);




    /* foreach ($modalidades as $key => $value) {
        echo "<p><strong>$key:</strong> $value</p>";
    } imprime todas los valores con clave valor de lo que devuelve una query  */
     
   

    
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title><?php echo $curso["nombre"]?></title>
</head>
<body>
<div class="jumbotron jumbotron-fluid py-5">
        <div class="container">
            <h1 class="display-4"><?php echo $curso["nombre"]?></h1>
            <p class="lead">subtitulo o explicacion rapida del curso</p>
        </div>
    </div>

    <div class="container my-5">
        <div class="row">
            <div class="col-md-8">
                <h2>Descripción del Curso</h2>
                <p><?php echo $curso['descripcion']?></p>
                
                <h3 class="mt-4">Objetivos del Curso</h3>
                <ul>
                    <li>objetivo 1</li>
                    <li>objetivo 2</li>
                    <li>objetivo 3</li>
                    <li>objetivo 4</li>
                </ul>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Detalles del Curso</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Duración:</strong> <?php echo $curso["duracion"]?></p>
                        <p><strong>Nivel:</strong> <?php echo $curso["nombre_nivel"]?></p>
                        <p><strong>Modalidad:</strong> <?php echo $modalidad_nombres_str ?></p>
                        <p><strong>Certificación:</strong> Sí</p>
                        <a href="#" class="btn btn-primary btn-block">Inscribirse Ahora</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="app.js"></script>
</body>
</html>