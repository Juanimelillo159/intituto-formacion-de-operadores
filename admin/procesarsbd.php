<?php
session_start();
include("../sbd.php");
if (!isset($_SESSION['usuario'])) {
    // Redirigir al usuario a una página de inicio de sesión o mostrar un mensaje de error
    header("Location: ../index.php");
    exit;
} else {

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





    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["agregar_banner"])) {
        // Recibir los datos del formulario
        $nombre_banner = $_POST['nombre_banner'];
        $imagen = $_FILES['imagen_banner'];

        // Verificar si se subió un archivo
        if ($imagen['error'] === UPLOAD_ERR_OK) {
            // Verificar si es una imagen válida (tipo MIME)
            $mime = mime_content_type($imagen['tmp_name']);
            $permitidos = ['image/jpeg', 'image/png', 'image/gif'];

            if (in_array($mime, $permitidos)) {
                // Obtener la extensión del archivo
                $extension = pathinfo($imagen['name'], PATHINFO_EXTENSION);
                // Generar un nombre único para el archivo
                $nombre_imagen = uniqid('imagen_') . ".$extension";

                // Definir la ruta de destino
                $ruta_destino = "../imagenes/banners/$nombre_imagen";

                // Verificar si la carpeta de destino existe, si no, crearla
                if (!is_dir("../imagenes/banners")) {
                    mkdir("../imagenes/banners", 0755, true);
                }

                // Mover el archivo a la carpeta de imágenes
                if (move_uploaded_file($imagen['tmp_name'], $ruta_destino)) {
                    // Insertar el nombre de la imagen en la base de datos
                    $sql_insertar_imagen = $con->prepare("INSERT INTO banner (nombre_banner, imagen_banner) VALUES (:nombre_banner, :imagen_banner)");
                    $sql_insertar_imagen->bindParam(':nombre_banner', $nombre_banner);
                    $sql_insertar_imagen->bindParam(':imagen_banner', $nombre_imagen);

                    if ($sql_insertar_imagen->execute()) {
                        echo
                        "<script>
                        alert('imagen agregada correctamente');
                        window.location.href = '/p/admin/carrusel.php'; // Redirigir al login o a la página actual
                    </script>";
                    } else {
                        echo "Error al insertar en la base de datos.";
                    }
                } else {
                    echo "Error al mover el archivo a la carpeta de destino.";
                }
            } else {
                echo "El archivo no es una imagen válida. Sólo se permiten JPG, PNG y GIF.";
            }
        } else {
            echo "Error al subir la imagen: " . $imagen['error'];
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["editar_banner"])) {
        // Recibir los datos del formulario
        $id_banner = $_POST["id_banner"];
        $nombre_banner = $_POST["nombre_banner"];
        $imagen = $_FILES["imagen_banner"];

        // Verificar si se subió una imagen
        if ($imagen["error"] === UPLOAD_ERR_OK) {
            // Verificar si es una imagen válida (tipo MIME)
            $mime = mime_content_type($imagen["tmp_name"]);
            $permitidos = ["image/jpeg", "image/png", "image/gif"];

            if (in_array($mime, $permitidos)) {
                // Obtener la extensión del archivo
                $extension = pathinfo($imagen["name"], PATHINFO_EXTENSION);
                // Generar un nombre único para el archivo
                $nombre_imagen = uniqid("imagen_") . ".$extension";

                // Definir la ruta de destino
                $ruta_destino = "../imagenes/banners/$nombre_imagen";

                // Verificar si la carpeta de destino existe, si no, crearla
                if (!is_dir("../imagenes/banners")) {
                    mkdir("../imagenes/banners", 0755, true);
                }

                // Mover el archivo a la carpeta de imágenes
                if (move_uploaded_file($imagen["tmp_name"], $ruta_destino)) {
                    // Obtener el nombre de la imagen actual
                    $sql_nombre_imagen = $con->prepare("SELECT imagen_banner FROM banner WHERE id_banner = :id_banner");
                    $sql_nombre_imagen->bindParam(":id_banner", $id_banner);
                    $sql_nombre_imagen->execute();
                    $nombre_imagen_actual = $sql_nombre_imagen->fetchColumn();

                    // Actualizar el registro en la base de datos, incluyendo el nuevo nombre e imagen
                    $sql_editar_banner = $con->prepare("UPDATE banner SET nombre_banner = :nombre_banner, imagen_banner = :imagen_banner WHERE id_banner = :id_banner");
                    $sql_editar_banner->bindParam(":nombre_banner", $nombre_banner);
                    $sql_editar_banner->bindParam(":imagen_banner", $nombre_imagen);
                    $sql_editar_banner->bindParam(":id_banner", $id_banner);

                    if ($sql_editar_banner->execute()) {
                        // Eliminar la imagen anterior
                        if (unlink("../imagenes/banners/$nombre_imagen_actual")) {
                            echo
                            "<script>
                            alert('Imagen y nombre actualizados correctamente.');
                            window.location.href = '/p/admin/carrusel.php';
                        </script>";
                        } else {
                            echo "Error al eliminar la imagen anterior.";
                        }
                    } else {
                        echo "Error al actualizar en la base de datos.";
                    }
                } else {
                    echo "Error al mover el archivo a la carpeta de destino.";
                }
            } else {
                echo "El archivo no es una imagen válida. Sólo se permiten JPG, PNG y GIF.";
            }
        } else {
            // Si no se sube imagen, solo actualizar el nombre del banner
            if ($imagen["error"] === UPLOAD_ERR_NO_FILE) {
                // Actualizar solo el nombre en la base de datos
                $sql_editar_banner = $con->prepare("UPDATE banner SET nombre_banner = :nombre_banner WHERE id_banner = :id_banner");
                $sql_editar_banner->bindParam(":nombre_banner", $nombre_banner);
                $sql_editar_banner->bindParam(":id_banner", $id_banner);

                if ($sql_editar_banner->execute()) {
                    echo
                    "<script>
                    alert('Nombre del banner actualizado correctamente.');
                    window.location.href = '/p/admin/carrusel.php';
                </script>";
                } else {
                    echo "Error al actualizar el nombre en la base de datos.";
                }
            } else {
                echo "Error al subir la imagen: " . $imagen["error"];
            }
        }
    }
}
