<?php
require_once 'sbd.php';
include("nav.php");

$id_curso = $_GET['id_curso'];

$sql_cursos = $con->prepare("SELECT * FROM cursos c JOIN complejidad n ON c.id_complejidad = n.id_complejidad WHERE c.id_curso = :id_curso");
$sql_cursos->bindParam(':id_curso', $id_curso);
$sql_cursos->execute();
$curso = $sql_cursos->fetch(PDO::FETCH_ASSOC);

$sql_complejidad = $con->prepare("SELECT m.id_modalidad AS modalidad_id, m.nombre_modalidad AS modalidad_nombre FROM curso_modalidad cm JOIN modalidades m ON cm.id_modalidad = m.id_modalidad WHERE cm.id_curso = :id_curso");
$sql_complejidad->bindParam(':id_curso', $id_curso);
$sql_complejidad->execute();
$modalidades = $sql_complejidad->fetchAll(PDO::FETCH_ASSOC);

$modalidad_nombres = array();
foreach ($modalidades as $value) {
    $modalidad_nombres[] = htmlspecialchars($value['modalidad_nombre']);
}
$modalidad_nombres_str = implode(' - ', $modalidad_nombres);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <title><?php echo $curso["nombre_curso"] ?></title>
    <style>
        .content-wrapper {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        h1,
        h2,
        h3,
        h4 {
            color: #0014AE;
            /* Azul */
        }

    </style>
</head>

<body>
    <div class="py-2 text-white">
        <div class="container">
            <a id="back" href="index.php" class="text-white d-inline-flex align-items-center" style="text-decoration:none;">
                <button>
                    <svg height="16" width="16" xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 1024 1024">
                        <path d="M874.690416 495.52477c0 11.2973-9.168824 20.466124-20.466124 20.466124l-604.773963 0 188.083679 188.083679c7.992021 7.992021 7.992021 20.947078 0 28.939099-4.001127 3.990894-9.240455 5.996574-14.46955 5.996574-5.239328 0-10.478655-1.995447-14.479783-5.996574l-223.00912-223.00912c-3.837398-3.837398-5.996574-9.046027-5.996574-14.46955 0-5.433756 2.159176-10.632151 5.996574-14.46955l223.019353-223.029586c7.992021-7.992021 20.957311-7.992021 28.949332 0 7.992021 8.002254 7.992021 20.957311 0 28.949332l-188.073446 188.073446 604.753497 0C865.521592 475.058646 874.690416 484.217237 874.690416 495.52477z"></path>
                    </svg>
                    <span>Atras</span>
                </button>
            </a>


        </div>
    </div>

    <div class="container my-5">
        <div class="content-wrapper">
            <div class="row">
                <div class="col-md-8">
                    <h2 class="display-2"><?php echo $curso["nombre_curso"] ?></h2>
                    <p class="lead">subtitulo o explicacion rapida del curso</p>
                    <h4 class="display-4">Descripción del Curso</h4>
                    <p><?php echo $curso['descripcion_curso'] ?></p>

                    <h3 class="mt-4">Objetivos del Curso</h3>
                    <p><?php echo $curso['objetivos'] ?></p>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Detalles del Curso</h3>
                        </div>
                        <div class="card-body">
                            <p><strong>Duración:</strong> <?php echo $curso["duracion"] ?></p>
                            <p><strong>Nivel:</strong> <?php echo $curso["nombre_complejidad"] ?></p>
                            <p><strong>Modalidad:</strong> <?php echo $modalidad_nombres_str ?></p>
                            <p><strong>Certificación:</strong> Sí</p>
                            <a href="#" class="btn btn-primary btn-block">Inscribirse Ahora</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include("footer.php") ?>
    <script src="app.js"></script>
</body>

</html>