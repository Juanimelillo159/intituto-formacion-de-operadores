

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
          <h2>Agregar curso</h2>
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
              <form action="procesarsbd.php" method="POST" >
                <input type="hidden" name="id_curso">
                <div class="card-body">
                  <div class="form-group">
                    <label for="courseName">Nombre</label>
                    <input required " type="text" class="form-control" id="courseName" name="nombre" placeholder="Enter course name">
                  </div>
                  <div class="form-group">
                    <label for="courseDescription">Descripción</label>
                    <textarea required  class="form-control" id="courseDescription" rows="3" placeholder="Enter course description" name="descripcion"></textarea>
                  </div>
                  <div class="form-group">
                    <label for="courseDuration">Duración</label>
                    <input required type="text" class="form-control" id="courseDuration" placeholder="Enter course duration" name="duracion">
                  </div>
                  <div class="form-group">
                    <label for="courseObjectives">Objetivos</label>
                    <textarea required class="form-control" id="courseObjectives" rows="3" placeholder="Enter course objectives" name="objetivos"></textarea>
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

                      <?php foreach($modalidades as $modalidad) { ?>
                          <div>
                              <input 
                                
                                  class="" 
                                  type='checkbox' 
                                  name='modalidades[]' 
                                  value="<?php echo $modalidad['id_modalidad']; ?>" 
                                  <?php if (in_array($modalidad['id_modalidad'], $curso_modalidades)) echo 'checked'; ?>
                              >  
                              <?php echo $modalidad["nombre_modalidad"]; ?>
                          </div>
                      <?php } ?>
                  </div>
                  <div class="form-group">
                    <?php 
                      $sql_dificultad = $con-> prepare("SELECT * FROM complejidad");
                      $sql_dificultad->execute();
                      $niveles = $sql_dificultad->fetchall(PDO::FETCH_ASSOC);
                    ?>
                    <label for="dificultad">Dificultad</label>
                    <select class="form-control" id="dificultad" name=dificultad>
                      <?php 
                        foreach($niveles as $nivel) {
                          $selected = $nivel["id_complejidad"] == $dificultadic;
                          echo "<option value='" . $nivel["id_complejidad"] . "' $selected>" . $nivel["nombre_complejidad"] . "</option>";
                        }
                      ?>
                    </select>
                  </div>
                </div>
                <!-- /.card-body -->

                <div class="card-footer">
                  <button type="button" onclick="volver()" class="btn btn-primary">Volver</button>
                  <button type="submit" class="btn btn-success">Guardar</button>

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
        window.location.href = 'cursos.php';
    }
</script>
</html>