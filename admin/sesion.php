<?php
session_start();
include("../sbd.php");
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
            $_SESSION["usuario"] = $usuario["id_usuario"];
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
?>