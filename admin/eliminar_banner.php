<?php 

session_start();

include '../sbd.php';

if (!isset($_SESSION['usuario'])) {
    if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Sesión expirada. Inicie sesión nuevamente.']);
        exit;
    }

    header('Location: ../index.php');
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest' || isset($_POST['ajax']);

if ($method !== 'POST' && $method !== 'GET') {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    }
    exit;
}

$id_banner = 0;
if ($method === 'POST') {
    $id_banner = (int)($_POST['id_banner'] ?? 0);
} elseif (isset($_GET['id_banner'])) {
    $id_banner = (int)$_GET['id_banner'];
}

if ($id_banner <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Identificador de banner inválido.']);
        exit;
    }

    header('Location: carrusel.php');
    exit;
}

$sql_nombre_imagen = $con->prepare("SELECT imagen_banner FROM banner WHERE id_banner = :id_banner");
$sql_nombre_imagen->bindParam(':id_banner', $id_banner, PDO::PARAM_INT);
$sql_nombre_imagen->execute();
$nombre_imagen = $sql_nombre_imagen->fetchColumn();

if ($nombre_imagen === false) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'La imagen ya no existe.']);
        exit;
    }

    $_SESSION['banner_error'] = 'La imagen seleccionada ya no existe.';
    header('Location: carrusel.php');
    exit;
}

$con->beginTransaction();

try {
    $sql_eliminar_banner = $con->prepare("DELETE FROM banner WHERE id_banner = :id_banner");
    $sql_eliminar_banner->bindParam(':id_banner', $id_banner, PDO::PARAM_INT);
    $sql_eliminar_banner->execute();

    $con->commit();
} catch (Throwable $ex) {
    $con->rollBack();

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'No se pudo eliminar la imagen. Intente nuevamente.']);
        exit;
    }

    $_SESSION['banner_error'] = 'No se pudo eliminar la imagen. Intente nuevamente.';
    header('Location: carrusel.php?error=1');
    exit;
}

$rutaImagen = __DIR__ . '/../assets/imagenes/banners/' . $nombre_imagen;
if (is_file($rutaImagen)) {
    @unlink($rutaImagen);
}

if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

$_SESSION['banner_success'] = 'Imagen eliminada correctamente.';
header('Location: carrusel.php');
exit;
