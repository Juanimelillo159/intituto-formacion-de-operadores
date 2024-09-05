<?php
include ("../sbd.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_curso'])) {
    // Recibir los datos del formulario
    $id_curso = $_POST['id_curso'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $duracion = $_POST['duracion'];
    $objetivos = $_POST['objetivos'];
    $modalidad = $_POST['modalidad'];
    $dificultad = $_POST['dificultad'];

    // Actualizar el curso en la base de datos
    $sql_editar_curso = $con->prepare("UPDATE cursos SET nombre = :nombre, descripcion = :descripcion, duracion = :duracion, objetivos = :objetivos, modalidad = :modalidad, id_complejidad = :dificultad WHERE id = :id_curso");
    $sql_editar_curso->bindParam(':nombre', $nombre);
    $sql_editar_curso->bindParam(':descripcion', $descripcion);
    $sql_editar_curso->bindParam(':duracion', $duracion);
    $sql_editar_curso->bindParam(':objetivos', $objetivos);
    $sql_editar_curso->bindParam(':modalidad', $modalidad);
    $sql_editar_curso->bindParam(':dificultad', $dificultad);
    $sql_editar_curso->execute();            
    header('Location: curso.php?id_curso=' . $id_curso);
    exit;
}



if($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["iniciar-sesion"])){
    //recibir los datos del usuario
    $email = $_POST["email"];
    $password = $_POST["password"];
    //verificar si el usuario existe
    $sql_usuario = $con->prepare("SELECT * FROM usuarios WHERE email = :email");
    $sql_usuario->bindParam(":email", $email);
    $sql_usuario->execute();
    $usuario = $sql_usuario->fetch(PDO::FETCH_ASSOC);
    if($usuario){
        //verificar si la contraseña es correcta
        if(password_verify($password, $usuario["password"])){
            //iniciar sesión
            session_start();
            $_SESSION["id_usuario"] = $usuario["id"];
            $_SESSION["email"] = $usuario["email"];
            $_SESSION["permiso"] = $usuario["id_permiso"];
            header("Location: admin/admin.php");
            exit;
        }else{
            echo "La contraseña es incorrecta";
        }
}
?>