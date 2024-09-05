<?php 
require_once 'sbd.php';

$search = isset($_POST['search']) ? $_POST['search'] : '';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$items_per_page = 6;
$offset = ($page - 1) * $items_per_page;

$sql = $con->prepare("SELECT * FROM cursos WHERE nombre_curso LIKE :search LIMIT :offset, :items_per_page");
$sql->bindValue(':search', "%$search%", PDO::PARAM_STR);
$sql->bindValue(':offset', $offset, PDO::PARAM_INT);
$sql->bindValue(':items_per_page', $items_per_page, PDO::PARAM_INT);
$sql->execute();
$cursos = $sql->fetchAll(PDO::FETCH_ASSOC);

// Obteniendo el total de cursos para calcular la paginación
$total_sql = $con->prepare("SELECT COUNT(*) FROM cursos WHERE nombre_curso LIKE :search");
$total_sql->bindValue(':search', "%$search%", PDO::PARAM_STR);
$total_sql->execute();
$total_cursos = $total_sql->fetchColumn();
$total_pages = ceil($total_cursos / $items_per_page);
?>

<div class="row row-cols-1 row-cols-md-3 g-4">
    <?php foreach($cursos as $curso) { ?>
        <div class="col">
            <div class="card h-100 d-flex flex-column">
                <div class="card-body">
                    <h5 class="card-title"><?php echo $curso["nombre_curso"]?></h5>
                    <p class="card-text"><?php echo $curso["descripcion_curso"]?></p>
                </div>
                <div class="card-footer bg-transparent border-0 mt-auto">
                    <div class="text-center">
                        <a href="curso.php?id_curso=<?php echo $curso["id_curso"]?>" class="btn btn-primary">Más Información</a>
                    </div>
                </div>
            </div>
        </div>  
    <?php } ?>
</div>

<nav aria-label="Page navigation">
  <ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
      <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
        <a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
      </li>
    <?php } ?>
  </ul>
</nav>
