<?php 
session_start();
include '../sbd.php';
if (!isset($_SESSION['usuario'])) {
    // Redirigir al usuario a una página de inicio de sesión o mostrar un mensaje de error
    header("Location: ../index.php");
    exit;
}
elseif(isset($_GET['id_curso'])) {
    // Recibir el id del curso
    $id_curso = $_GET['id_curso'];

    // Eliminar registros de la tabla intermedia curso_modalidad
    $sql_eliminar_relaciones = $con->prepare("DELETE FROM curso_modalidad WHERE id_curso = :id_curso");
    $sql_eliminar_relaciones->bindParam(':id_curso', $id_curso);
    $sql_eliminar_relaciones->execute();

    // Eliminar el curso de la tabla cursos
    $sql_eliminar_curso = $con->prepare("DELETE FROM cursos WHERE id_curso = :id_curso");
    $sql_eliminar_curso->bindParam(':id_curso', $id_curso);
    $sql_eliminar_curso->execute();

    header('Location: cursos.php');
    exit;
}
?>