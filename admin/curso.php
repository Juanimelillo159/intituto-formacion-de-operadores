<?php

include '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';
$id_curso = $_GET["id_curso"];


$sql_curso_id = $con->prepare("SELECT * FROM cursos WHERE id_curso = :id_curso");
$sql_curso_id->bindParam(':id_curso', $id_curso);
$sql_curso_id->execute();
$curso = $sql_curso_id->fetch(PDO::FETCH_ASSOC);


$nombre = $curso["nombre_curso"];
$descripcion = $curso["descripcion_curso"];
$duracion = $curso["duracion"];
$objetivos = $curso["objetivos"];
$dificultad = $curso["id_complejidad"];

$sql_modalidades_curso = $con->prepare("SELECT m.*
FROM modalidades m
JOIN curso_modalidad cm ON m.id_modalidad = cm.id_modalidad
WHERE cm.id_curso = :id_curso");
$sql_modalidades_curso->bindParam(':id_curso', $id_curso);
$sql_modalidades_curso->execute();
$modalidades_curso = $sql_modalidades_curso->fetchall(PDO::FETCH_ASSOC);


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
              <h2>Curso: <?php echo $nombre ?></h2>
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
                  <h3 class="card-title">Detalle del curso</h3>
                </div>
                <!-- /.card-header -->
                <!-- form start -->
                <form id="form" action="procesarsbd.php" method="POST">
                  <input type="hidden" name="id_curso" value="<?php echo $id_curso ?>">
                  <div class="card-body">
                    <div class="form-group">
                      <label for="courseName">Nombre</label>
                      <input required disabled value="<?php echo $nombre ?>" type="text" class="form-control" id="courseName" name="nombre" placeholder="Enter course name">
                    </div>
                    <div class="form-group">
                      <label for="courseDescription">Descripción</label>
                      <textarea required disabled class="form-control" id="courseDescription" rows="3" placeholder="Enter course description" name="descripcion"><?php echo $descripcion ?></textarea>
                    </div>
                    <div class="form-group">
                      <label for="courseDuration">Duración</label>
                      <input required disabled value="<?php echo $duracion ?>" type="text" class="form-control" id="courseDuration" placeholder="Enter course duration" name="duracion">
                    </div>
                    <div class="form-group">
                      <label for="courseObjectives">Objetivos</label>
                      <textarea required disabled class="form-control" id="courseObjectives" rows="3" placeholder="Enter course objectives" name="objetivos"><?php echo $objetivos ?></textarea>
                    </div>

                    <div class="form-group">
                      <label for="Modalidad">Modalidad</label>
                      <?php
                      // Obtener todas las modalidades
                      $sql_modalidades = $con->prepare("SELECT * FROM modalidades");
                      $sql_modalidades->execute();
                      $modalidades = $sql_modalidades->fetchAll(PDO::FETCH_ASSOC);

                      $sql_curso_modalidades = $con->prepare("SELECT id_modalidad FROM curso_modalidad WHERE id_curso = :id_curso");
                      $sql_curso_modalidades->bindParam(':id_curso', $id_curso, PDO::PARAM_INT);
                      $sql_curso_modalidades->execute();
                      $curso_modalidades = $sql_curso_modalidades->fetchAll(PDO::FETCH_COLUMN);
                      ?>

                      <?php foreach ($modalidades as $modalidad) { ?>
                        <div>
                          <input
                            disabled
                            class=""
                            type='checkbox'
                            name='modalidades[]'
                            value="<?php echo $modalidad['id_modalidad']; ?>"
                            <?php if (in_array($modalidad['id_modalidad'], $curso_modalidades)) echo 'checked'; ?>>
                          <?php echo $modalidad["nombre_modalidad"]; ?>
                        </div>
                      <?php } ?>
                    </div>
                    <div class="form-group">
                      <?php
                      $sql_dificultad = $con->prepare("SELECT * FROM complejidad");
                      $sql_dificultad->execute();
                      $niveles = $sql_dificultad->fetchall(PDO::FETCH_ASSOC);
                      ?>
                      <label for="dificultad">Dificultad</label>
                      <select disabled class="form-control" id="dificultad" name="dificultad">
                        <?php
                        foreach ($niveles as $nivel) {
                          $selected = $nivel["id_complejidad"] == $dificultad ? "selected" : "";
                          echo "<option value='" . $nivel["id_complejidad"] . "' $selected>" . $nivel["nombre_complejidad"] . "</option>";
                        }
                        ?>
                      </select>
                    </div>
                  </div>

                  <div class="card-footer">
                    <button type="button" onclick="editarCampos()" class="btn btn-warning">Editar</button>
                    <button type="submit" name="editar_curso" class="btn btn-success d-none">Guardar</button>
                    <button type="button" onclick="cancelar()" class="btn btn-danger d-none">Cancelar</button>
                    <button type="button" onclick="volver()" class="btn btn-primary">Volver</button>
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
  <script>
    function editarCampos() {
      document.getElementById('courseName').disabled = false;
      document.getElementById('courseDescription').disabled = false;
      document.getElementById('courseDuration').disabled = false;
      document.getElementById('courseObjectives').disabled = false;
      document.getElementById('dificultad').disabled = false;
      var checkboxes = document.querySelectorAll('input[type="checkbox"]');
      checkboxes.forEach(function(checkbox) {
        checkbox.disabled = false;
      });
      document.getElementById('courseName').focus();
      document.querySelector('.btn-danger').classList.remove('d-none');
      document.querySelector('.btn-success').classList.remove('d-none');
      document.querySelector('.btn-warning').classList.add('d-none');
      document.querySelector('.btn-primary').classList.add('d-none');

    }

    function cancelar() {
      form.reset();
      document.getElementById('courseName').disabled = true;
      document.getElementById('courseDescription').disabled = true;
      document.getElementById('courseDuration').disabled = true;
      document.getElementById('courseObjectives').disabled = true;
      document.getElementById('dificultad').disabled = true;
      var checkboxes = document.querySelectorAll('input[type="checkbox"]');
      checkboxes.forEach(function(checkbox) {
        checkbox.disabled = true;
      });
      document.querySelector('.btn-danger').classList.add('d-none');
      document.querySelector('.btn-success').classList.add('d-none');
      document.querySelector('.btn-warning').classList.remove('d-none');
      document.querySelector('.btn-primary').classList.remove('d-none');

      document.getElementById('form').reset();
    }

    function volver() {
      window.location.href = 'cursos.php';
    }
  </script>
</body>

</html>