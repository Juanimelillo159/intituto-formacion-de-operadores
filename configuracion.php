<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$pdo = getPdo();
$userId = (int)($_SESSION['usuario'] ?? 0);
$feedback = $_SESSION['config_feedback'] ?? null;
$activeTab = $_SESSION['config_active_tab'] ?? 'perfil';
unset($_SESSION['config_feedback'], $_SESSION['config_active_tab']);

try {
    $stmt = $pdo->prepare('SELECT email, nombre, apellido, telefono FROM usuarios WHERE id_usuario = ? LIMIT 1');
    $stmt->execute([$userId]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        header('Location: login.php');
        exit;
    }
} catch (Throwable $exception) {
    http_response_code(500);
    echo 'No pudimos cargar los datos de tu cuenta.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';
    $activeTab = $formType === 'password' ? 'password' : 'perfil';

    if ($formType === 'perfil') {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $apellido = trim((string)($_POST['apellido'] ?? ''));
        $telefono = trim((string)($_POST['telefono'] ?? ''));

        if ($nombre === '' || $apellido === '' || $telefono === '') {
            $_SESSION['config_feedback'] = ['type' => 'error', 'message' => 'Todos los campos son obligatorios.'];
            $_SESSION['config_active_tab'] = $activeTab;
            header('Location: configuracion.php');
            exit;
        }

        if (!preg_match('/^[0-9+()\\s-]{6,}$/', $telefono)) {
            $_SESSION['config_feedback'] = ['type' => 'error', 'message' => 'Ingresa un numero de telefono valido.'];
            $_SESSION['config_active_tab'] = $activeTab;
            header('Location: configuracion.php');
            exit;
        }

        try {
            $update = $pdo->prepare('UPDATE usuarios SET nombre = ?, apellido = ?, telefono = ? WHERE id_usuario = ?');
            $update->execute([$nombre, $apellido, $telefono, $userId]);

            $_SESSION['config_feedback'] = ['type' => 'success', 'message' => 'Datos personales actualizados correctamente.'];
            $usuario['nombre'] = $nombre;
            $usuario['apellido'] = $apellido;
            $usuario['telefono'] = $telefono;
        } catch (Throwable $exception) {
            $_SESSION['config_feedback'] = ['type' => 'error', 'message' => 'No pudimos actualizar tus datos. Intenta nuevamente.'];
        }

        $_SESSION['config_active_tab'] = $activeTab;
        header('Location: configuracion.php');
        exit;
    }

    if ($formType === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if ($current === '' || $new === '' || $confirm === '') {
            $_SESSION['config_feedback'] = ['type' => 'error', 'message' => 'Completa todos los campos para cambiar tu contraseña.'];
            $_SESSION['config_active_tab'] = $activeTab;
            header('Location: configuracion.php');
            exit;
        }

        if (strlen($new) < 8) {
            $_SESSION['config_feedback'] = ['type' => 'error', 'message' => 'La nueva contraseña debe tener al menos 8 caracteres.'];
            $_SESSION['config_active_tab'] = $activeTab;
            header('Location: configuracion.php');
            exit;
        }

        if ($new !== $confirm) {
            $_SESSION['config_feedback'] = ['type' => 'error', 'message' => 'Las contraseñas no coinciden.'];
            $_SESSION['config_active_tab'] = $activeTab;
            header('Location: configuracion.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare('SELECT clave FROM usuarios WHERE id_usuario = ? LIMIT 1');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || !password_verify($current, (string)$row['clave'])) {
                $_SESSION['config_feedback'] = ['type' => 'error', 'message' => 'La contraseña actual no es correcta.'];
                $_SESSION['config_active_tab'] = $activeTab;
                header('Location: configuracion.php');
                exit;
            }

            $hash = password_hash($new, PASSWORD_DEFAULT);
            $update = $pdo->prepare('UPDATE usuarios SET clave = ? WHERE id_usuario = ?');
            $update->execute([$hash, $userId]);

            $_SESSION['config_feedback'] = ['type' => 'success', 'message' => 'Tu contraseña se actualizó correctamente.'];
        } catch (Throwable $exception) {
            $_SESSION['config_feedback'] = ['type' => 'error', 'message' => 'No pudimos actualizar tu contraseña. Intenta nuevamente.'];
        }

        $_SESSION['config_active_tab'] = $activeTab;
        header('Location: configuracion.php');
        exit;
    }

    $_SESSION['config_feedback'] = ['type' => 'error', 'message' => 'Solicitud desconocida.'];
    $_SESSION['config_active_tab'] = $activeTab;
    header('Location: configuracion.php');
    exit;
}

$page_title = 'Panel de configuración | Instituto de Formacion';
$page_description = 'Administra los datos y la seguridad de tu cuenta.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';
?>
<!DOCTYPE html>
<html lang="es">
<?php include 'head.php'; ?>
<body class="config-page d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="javascript:history.back();" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100">
                    <h1>Panel de configuración</h1>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-card shadow">
                    <?php if ($feedback !== null): ?>
                        <div class="alert alert-<?php echo $feedback['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($feedback['message'], ENT_QUOTES, 'UTF-8'); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <ul class="nav nav-pills config-tabs" id="configTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'perfil' ? 'active' : ''; ?>" id="tab-perfil" data-bs-toggle="tab" data-bs-target="#panel-perfil" type="button" role="tab" aria-controls="panel-perfil" aria-selected="<?php echo $activeTab === 'perfil' ? 'true' : 'false'; ?>">
                                <i class="fas fa-user-circle me-2"></i>Datos personales
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $activeTab === 'password' ? 'active' : ''; ?>" id="tab-password" data-bs-toggle="tab" data-bs-target="#panel-password" type="button" role="tab" aria-controls="panel-password" aria-selected="<?php echo $activeTab === 'password' ? 'true' : 'false'; ?>">
                                <i class="fas fa-lock me-2"></i>Contraseña
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content mt-4" id="configTabsContent">
                        <div class="tab-pane fade <?php echo $activeTab === 'perfil' ? 'show active' : ''; ?>" id="panel-perfil" role="tabpanel" aria-labelledby="tab-perfil">
                            <form method="POST" class="config-form">
                                <input type="hidden" name="form_type" value="perfil">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="nombre" class="form-label">Nombre</label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars((string)$usuario['nombre'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="apellido" class="form-label">Apellido</label>
                                        <input type="text" class="form-control" id="apellido" name="apellido" value="<?php echo htmlspecialchars((string)$usuario['apellido'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="telefono" class="form-label">Número de teléfono</label>
                                        <input type="tel" class="form-control" id="telefono" name="telefono" value="<?php echo htmlspecialchars((string)$usuario['telefono'], ENT_QUOTES, 'UTF-8'); ?>" pattern="[0-9+()\s-]{6,}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Correo electrónico</label>
                                        <div class="config-email-box">
                                            <input type="email" class="form-control" value="<?php echo htmlspecialchars((string)$usuario['email'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                            <small class="text-muted">El correo se usa para acceder y no puede modificarse desde aquí.</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-gradient">Guardar cambios</button>
                                </div>
                            </form>
                        </div>

                        <div class="tab-pane fade <?php echo $activeTab === 'password' ? 'show active' : ''; ?>" id="panel-password" role="tabpanel" aria-labelledby="tab-password">
                            <form method="POST" class="config-form">
                                <input type="hidden" name="form_type" value="password">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="current_password" class="form-label">Contraseña actual</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="new_password" class="form-label">Nueva contraseña</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="8" required>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn btn-gradient">Actualizar contraseña</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


