<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("../sbd.php");

if (isset($_SESSION['usuario'])) {
    $sql_estado = $con->prepare("UPDATE usuarios SET id_estado = :estado WHERE id_usuario = :id");
    $estado_registrado = 1;
    $sql_estado->bindParam(":estado", $estado_registrado, PDO::PARAM_INT);
    $sql_estado->bindParam(":id", $_SESSION['usuario'], PDO::PARAM_INT);
    $sql_estado->execute();
}

session_unset();
session_destroy();
header("Location: ../login.php");
exit;
?>
