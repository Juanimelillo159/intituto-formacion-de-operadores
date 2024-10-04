<?php
include "../sbd.php";

$search = isset($_POST['search']) ? $_POST['search'] : '';
$page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
$items_per_page = 8;
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

<div class="row d-flex" id="course-container">
    <?php foreach ($cursos as $curso) { ?>
        <div class="col-md-3 d-flex align-items-stretch course-card">
            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title text-center curso-titulo" style="min-height: 50px; display: flex; align-items: center; justify-content: center;">
                        <?php echo $curso["nombre_curso"] ?>
                    </h3>
                </div>
                <div class="card-body">
                    <p class="card-text flex-grow-1"><?php echo $curso["descripcion_curso"]; ?></p>
                </div>
                <div class="card-footer text-center">
                    <a href="eliminar_curso.php?id_curso=<?php echo $curso['id_curso']; ?>"><button class="btn btn-danger mx-2">Eliminar</button></a>
                    <a href="curso.php?id_curso=<?php echo $curso['id_curso']; ?>"><button class="btn btn-primary mx-2">Ver</button></a>
                </div>
            </div>
        </div>
    <?php } ?>
</div>


<div class="mt-5" aria-label="Page navigation">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $total_pages; $i++) { ?>
            <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                <a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
        <?php } ?>
    </ul>
</div>
