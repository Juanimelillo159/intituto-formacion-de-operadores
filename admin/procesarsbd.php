<?php
session_start();
include("../sbd.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_curso'])) {
    // Recibir los datos del formulario
    $id_curso = $_POST['id_curso'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $duracion = $_POST['duracion'];
    $objetivos = $_POST['objetivos'];
    $modalidades = isset($_POST['modalidades']) ? $_POST['modalidades'] : []; // Es un array
    $dificultad = $_POST['dificultad'];

    // Iniciar transacción
    $con->beginTransaction();

    try {
        // Actualizar el curso en la base de datos
        $sql_editar_curso = $con->prepare("UPDATE cursos SET nombre_curso = :nombre, descripcion_curso = :descripcion, duracion = :duracion, objetivos = :objetivos, id_complejidad = :dificultad WHERE id_curso = :id_curso");
        $sql_editar_curso->bindParam(':nombre', $nombre);
        $sql_editar_curso->bindParam(':descripcion', $descripcion);
        $sql_editar_curso->bindParam(':duracion', $duracion);
        $sql_editar_curso->bindParam(':objetivos', $objetivos);
        $sql_editar_curso->bindParam(':dificultad', $dificultad);
        $sql_editar_curso->bindParam(':id_curso', $id_curso);
        $sql_editar_curso->execute();

        // Borrar las modalidades actuales del curso
        $sql_borrar_modalidades = $con->prepare("DELETE FROM curso_modalidad WHERE id_curso = :id_curso");
        $sql_borrar_modalidades->bindParam(':id_curso', $id_curso);
        $sql_borrar_modalidades->execute();

        // Insertar las nuevas modalidades seleccionadas
        $sql_insertar_modalidad = $con->prepare("INSERT INTO curso_modalidad (id_curso, id_modalidad) VALUES (:id_curso, :id_modalidad)");
        foreach ($modalidades as $id_modalidad) {
            $sql_insertar_modalidad->bindParam(':id_curso', $id_curso);
            $sql_insertar_modalidad->bindParam(':id_modalidad', $id_modalidad);
            $sql_insertar_modalidad->execute();
        }

        // Confirmar transacción
        $con->commit();

        // Redirigir a la página del curso
        header('Location: curso.php?id_curso=' . $id_curso);
        exit;

    } catch (Exception $e) {
        // Si hay un error, revertir transacción
        $con->rollBack();
        echo "Error al editar el curso: " . $e->getMessage();
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_curso'])) {
    // Recibir el id del curso
    $id_curso = $_POST['id_curso'];

    // Eliminar el curso de la base de datos
    $sql_eliminar_curso = $con->prepare("DELETE FROM cursos WHERE id = :id_curso");
    $sql_eliminar_curso->bindParam(':id_curso', $id_curso);
    $sql_eliminar_curso->execute();
    header('Location: cursos.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_curso'])) {
    // Recibir los datos del formulario
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    $duracion = $_POST['duracion'];
    $objetivos = $_POST['objetivos'];
    $modalidades = $_POST['modalidades']; // Es un array con las modalidades seleccionadas
    $complejidad = $_POST['complejidad'];
    
    // Iniciar una transacción para asegurar que ambas inserciones sean atómicas
    $con->beginTransaction();

    try {
        // Insertar el curso en la base de datos
        $sql_insertar_curso = $con->prepare("
            INSERT INTO cursos (nombre_curso, descripcion_curso, duracion, objetivos, id_complejidad)
            VALUES (:nombre, :descripcion, :duracion, :objetivos, :complejidad)
        ");
        $sql_insertar_curso->bindParam(':nombre', $nombre);
        $sql_insertar_curso->bindParam(':descripcion', $descripcion);
        $sql_insertar_curso->bindParam(':duracion', $duracion);
        $sql_insertar_curso->bindParam(':objetivos', $objetivos);
        $sql_insertar_curso->bindParam(':complejidad', $complejidad);
        $sql_insertar_curso->execute();

        // Obtener el último ID insertado (id_curso)
        $id_curso = $con->lastInsertId();

        // Insertar cada modalidad seleccionada en la tabla curso_modalidad
        $sql_insertar_modalidad = $con->prepare("
            INSERT INTO curso_modalidad (id_curso, id_modalidad) VALUES (:id_curso, :id_modalidad)
        ");

        foreach ($modalidades as $id_modalidad) {
            $sql_insertar_modalidad->bindParam(':id_curso', $id_curso);
            $sql_insertar_modalidad->bindParam(':id_modalidad', $id_modalidad);
            $sql_insertar_modalidad->execute();
        }

        // Confirmar la transacción
        $con->commit();

        // Redirigir después de la inserción
        header('Location: cursos.php');
        exit;

    } catch (Exception $e) {
        // Si hay un error, revertir la transacción
        $con->rollBack();
        echo "Error al insertar el curso: " . $e->getMessage();
    }
}


/* if($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["añadir_admin"])){

    $email = "admin@admin.com";
    $password = "123456";
    $id_permiso = 1;
    $id_estado= 2;
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Almacenar $password_hash en lugar de la contraseña sin encriptar
    $sql_insert = $con->prepare("INSERT INTO usuarios (email, clave,id_estado, id_permiso) VALUES (:email, :password_hash, :id_estado, :id_permiso)");
    $sql_insert->bindParam(":email", $email);
    $sql_insert->bindParam(":password_hash", $password_hash);
    $sql_insert->bindParam(":id_estado", $id_estado);
    $sql_insert->bindParam(":id_permiso", $id_permiso);
    $sql_insert->execute();
}

codigo para añadir el usuario unico de administracion
 */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["iniciar_sesion"])) {
    //recibir los datos del usuario
    $email = $_POST["email"];
    $password = $_POST["clave"];
    //verificar si el usuario existe
    $sql_usuario = $con->prepare("SELECT * FROM usuarios WHERE email = :email");
    $sql_usuario->bindParam(":email", $email);
    $sql_usuario->execute();
    $usuario = $sql_usuario->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        //verificar si la contraseña es correcta
        if (password_verify($password, $usuario["clave"])) {
            //iniciar sesión
            session_start();
            $_SESSION["id_usuario"] = $usuario["id_usuario"];
            $_SESSION["email"] = $usuario["email"];
            $_SESSION["permiso"] = $usuario["id_permiso"];
            header("Location: /p/admin/admin.php");
            exit;
        } else {
            echo
            "<script>
                alert('La contraseña ingresada es incorrecta');
                window.location.href = '/p/login.php'; // Redirigir al login o a la página actual
            </script>";
        }
    }
}
