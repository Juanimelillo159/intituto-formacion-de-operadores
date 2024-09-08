<?php 

include '../sbd.php';

if (isset($_GET["id_banner"])) {
    // Recibir el id del banner
    $id_banner = $_GET["id_banner"];

    // Obtener el nombre de la imagen
    $sql_nombre_imagen = $con->prepare("SELECT imagen_banner FROM banner WHERE id_banner = :id_banner");
    $sql_nombre_imagen->bindParam(':id_banner', $id_banner);
    $sql_nombre_imagen->execute();
    $nombre_imagen = $sql_nombre_imagen->fetchColumn();

    // Eliminar el registro de la base de datos
    $sql_eliminar_banner = $con->prepare("DELETE FROM banner WHERE id_banner = :id_banner");
    $sql_eliminar_banner->bindParam(':id_banner', $id_banner);
    $sql_eliminar_banner->execute();

    // Eliminar la imagen de la carpeta
    if (unlink("../imagenes/banners/$nombre_imagen")) {
        echo
        "<script>
            alert('imagen eliminada correctamente');
            window.location.href = '/p/admin/carrusel.php'; // Redirigir al login o a la página actual
        </script>";
    } else {
        echo "Error al eliminar la imagen.";
    }
}?>