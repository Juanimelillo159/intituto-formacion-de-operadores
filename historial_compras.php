<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'Historial de compras | Instituto de Formacion';
$page_description = 'Compras realizadas con tu cuenta del Instituto.';
?>
<!DOCTYPE html>
<html lang="es">
<?php include 'head.php'; ?>
<body class="d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<main class="content-wrapper py-5 flex-grow-1">
    <div class="container text-center">
        <h1 class="mb-4">Historial de compras</h1>
        <div class="alert alert-info" role="alert">
            Todavia no registramos compras en tu cuenta.
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
