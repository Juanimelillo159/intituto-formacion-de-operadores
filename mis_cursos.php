<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$page_title = 'Mis cursos | Instituto de Formacion';
$page_description = 'Cursos disponibles para tu cuenta.';
?>
<!DOCTYPE html>
<html lang="es">
<?php include 'head.php'; ?>
<body>
<?php include 'nav.php'; ?>

<main class="content-wrapper py-5">
    <div class="container">
        <h1 class="mb-4">Mis cursos</h1>
        <p class="lead text-muted">Todavia no hay cursos para mostrar. Cuando te inscribas en uno, va a aparecer aca.</p>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
