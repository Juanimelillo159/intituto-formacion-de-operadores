<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$currentUserId = (int)($_SESSION['usuario'] ?? 0);
$currentPermiso = (int)($_SESSION['permiso'] ?? 0);

if ($currentPermiso !== 3) {
    http_response_code(403);
    $page_title = 'Acceso denegado | Instituto de Formacion';
    $page_description = 'No tienes permisos para acceder a esta seccion.';
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <?php include 'head.php'; ?>
    <body class="config-page d-flex flex-column min-vh-100">
    <?php include 'nav.php'; ?>
    <main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">
            <div class="alert alert-danger" role="alert">
                No tienes permiso para acceder al panel de trabajadores.
            </div>
            <a class="btn btn-primary" href="mis_cursos.php">Volver a mis cursos</a>
        </div>
    </main>
    <?php include 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

$page_title = 'Gestion de trabajadores | Instituto de Formacion';
$page_description = 'Panel para asignar y administrar trabajadores.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';

$pdo = getPdo();
$managerPermiso = 3;
$workerPermiso = 4;

$profileLabels = [
    1 => 'Administrador',
    2 => 'Usuario',
    3 => 'Gestor de trabajadores',
    4 => 'Trabajador',
];

$allowedTargetProfiles = [2];

$feedback = $_SESSION['trabajadores_feedback'] ?? null;
$activeTab = $_SESSION['trabajadores_active_tab'] ?? 'assign';
$assignValues = $_SESSION['trabajadores_form_values'] ?? [
    'nombre' => '', 'apellido' => '', 'dni' => '', 'email' => '', 'telefono' => '',
    'direccion' => '', 'ciudad' => '', 'provincia' => '', 'pais' => ''
];
unset($_SESSION['trabajadores_feedback'], $_SESSION['trabajadores_active_tab'], $_SESSION['trabajadores_form_values']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'assign_worker') {
        $nombre    = trim((string)($_POST['nombre'] ?? ''));
        $apellido  = trim((string)($_POST['apellido'] ?? ''));
        $dni       = trim((string)($_POST['dni'] ?? ''));
        $emailInput= trim((string)($_POST['email'] ?? ''));
        $telefono  = trim((string)($_POST['telefono'] ?? ''));
        $direccion = trim((string)($_POST['direccion'] ?? ''));
        $ciudad    = trim((string)($_POST['ciudad'] ?? ''));
        $provincia = trim((string)($_POST['provincia'] ?? ''));
        $pais      = trim((string)($_POST['pais'] ?? ''));
        $assignValues = [
            'nombre' => $nombre, 'apellido' => $apellido, 'dni' => $dni, 'email' => $emailInput,
            'telefono' => $telefono, 'direccion' => $direccion, 'ciudad' => $ciudad,
            'provincia' => $provincia, 'pais' => $pais
        ];

        $response = ['type' => 'danger', 'message' => 'No pudimos crear al trabajador. Revisa los datos e intenta nuevamente.'];
        $nextTab = 'assign';

        if ($nombre === '' || $apellido === '' || $dni === '' || $emailInput === '') {
            $response['message'] = 'Completá Nombre, Apellido, DNI y Correo electrónico.';
        } elseif (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Ingresá un correo electrónico válido.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO trabajadores (nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$nombre, $apellido, $emailInput !== '' ? $emailInput : null, $telefono !== '' ? $telefono : null, $dni, $direccion !== '' ? $direccion : null, $ciudad !== '' ? $ciudad : null, $provincia !== '' ? $provincia : null, $pais !== '' ? $pais : null, $currentUserId]);
                $newId = (int)$pdo->lastInsertId();
                $response = ['type' => 'success', 'message' => 'Trabajador creado correctamente.'];
                $assignValues = ['nombre' => '', 'apellido' => '', 'dni' => '', 'email' => '', 'telefono' => '', 'direccion' => '', 'ciudad' => '', 'provincia' => '', 'pais' => ''];
                $nextTab = 'worker_' . $newId;
            } catch (Throwable $exception) {
                $response['message'] = 'No pudimos crear al trabajador: ' . $exception->getMessage();
            }
        }

        $_SESSION['trabajadores_feedback'] = $response;
        $_SESSION['trabajadores_active_tab'] = $nextTab;
        $_SESSION['trabajadores_form_values'] = $assignValues;
        header('Location: trabajadores.php');
        exit;
    }
    if ($action === 'delete_worker') {
        $workerId = isset($_POST['worker_id']) ? (int)$_POST['worker_id'] : 0;
        $response = ['type' => 'danger', 'message' => 'No se pudo eliminar el trabajador.'];
        $nextTab = 'assign';
        if ($workerId > 0) {
            try {
                // Verificar pertenencia y referencias
                $stOwn = $pdo->prepare('SELECT COUNT(*) FROM trabajadores WHERE id_trabajador = ? AND creado_por = ?');
                $stOwn->execute([$workerId, $currentUserId]);
                $own = (int)$stOwn->fetchColumn() > 0;

                if ($own) {
                    $stRef = $pdo->prepare('SELECT COUNT(*) FROM solicitudes_certificacion_asistentes WHERE id_trabajador = ?');
                    $stRef->execute([$workerId]);
                    $refs = (int)$stRef->fetchColumn();
                    if ($refs === 0) {
                        $del = $pdo->prepare('DELETE FROM trabajadores WHERE id_trabajador = ? AND creado_por = ?');
                        $del->execute([$workerId, $currentUserId]);
                        $response = ['type' => 'success', 'message' => 'Trabajador eliminado.'];
                    } else {
                        $response = ['type' => 'warning', 'message' => 'No se puede eliminar: está vinculado a solicitudes.'];
                        $nextTab = 'worker_' . $workerId;
                    }
                } else {
                    $response = ['type' => 'danger', 'message' => 'No encontré el trabajador o no te pertenece.'];
                }
            } catch (Throwable $e) {
                $response = ['type' => 'danger', 'message' => 'Error al eliminar: ' . $e->getMessage()];
            }
        }
        $_SESSION['trabajadores_feedback'] = $response;
        $_SESSION['trabajadores_active_tab'] = $nextTab;
        header('Location: trabajadores.php');
        exit;
    }

    // Acciones no reconocidas
    $_SESSION['trabajadores_feedback'] = ['type' => 'danger', 'message' => 'Acción no reconocida.'];
    $_SESSION['trabajadores_active_tab'] = 'assign';
    header('Location: trabajadores.php');
    exit;
}

$workers = [];
$loadError = null;

try {
    $stmt = $pdo->prepare('SELECT id_trabajador, nombre, apellido, email, telefono, dni, direccion, ciudad, provincia, pais FROM trabajadores WHERE creado_por = ? ORDER BY nombre ASC, apellido ASC, email ASC');
    $stmt->execute([$currentUserId]);
    $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $exception) {
    error_log('trabajadores.php workers query: ' . $exception->getMessage());
    $loadError = 'No pudimos cargar la lista de trabajadores. Detalle: ' . $exception->getMessage();
}

$allowedAlertTypes = ['success', 'info', 'warning', 'danger'];
?>
<!DOCTYPE html>
<html lang="es">

<?php include 'head.php'; ?>

<body class="config-page d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="mis_cursos.php" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver a mis cursos</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Gesti&oacute;n de trabajadores</h1>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-xl-10">


        <?php if ($feedback !== null): ?>
            <?php $alertType = in_array($feedback['type'], $allowedAlertTypes, true) ? $feedback['type'] : 'info'; ?>
            <div class="alert alert-<?php echo htmlspecialchars($alertType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($feedback['message'], ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if ($loadError !== null): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-body">
                    <ul class="nav nav-pills flex-column flex-md-row gap-2" id="trabajadoresTabs" role="tablist">
                        <?php $assignActive = $activeTab === 'assign'; ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $assignActive ? 'active' : ''; ?>" id="tab-asignar" data-bs-toggle="tab" data-bs-target="#panel-asignar" type="button" role="tab" aria-controls="panel-asignar" aria-selected="<?php echo $assignActive ? 'true' : 'false'; ?>">
                                + Asignar trabajador
                            </button>
                        </li>
                        <?php foreach ($workers as $worker): ?>
                            <?php
                                $workerId = (int)($worker['id_trabajador'] ?? 0);
                                $tabKey = 'worker_' . $workerId;
                                $isActive = $activeTab === $tabKey;
                                $fullName = trim((string)($worker['nombre'] ?? '') . ' ' . (string)($worker['apellido'] ?? ''));
                                if ($fullName === '') {
                                    $fullName = (string)($worker['email'] ?? 'Trabajador');
                                }
                            ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $isActive ? 'active' : ''; ?>" id="tab-worker-<?php echo $workerId; ?>" data-bs-toggle="tab" data-bs-target="#panel-worker-<?php echo $workerId; ?>" type="button" role="tab" aria-controls="panel-worker-<?php echo $workerId; ?>" aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>">
                                    <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="tab-content mt-4" id="trabajadoresTabsContent">
                        <div class="tab-pane fade <?php echo $assignActive ? 'show active' : ''; ?>" id="panel-asignar" role="tabpanel" aria-labelledby="tab-asignar">
                            <div class="row g-4">
                                <div class="col-12 col-lg-8">
                                    <form method="POST" class="config-form">
                                        <input type="hidden" name="action" value="assign_worker">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="worker-nombre" class="form-label">Nombre</label>
                                                <input type="text" class="form-control" id="worker-nombre" name="nombre" value="<?php echo htmlspecialchars((string)$assignValues['nombre'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="worker-apellido" class="form-label">Apellido</label>
                                                <input type="text" class="form-control" id="worker-apellido" name="apellido" value="<?php echo htmlspecialchars((string)$assignValues['apellido'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="worker-dni" class="form-label">DNI</label>
                                                <input type="text" class="form-control" id="worker-dni" name="dni" value="<?php echo htmlspecialchars((string)$assignValues['dni'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="worker-pais" class="form-label">País</label>
                                                <input type="text" class="form-control" id="worker-pais" name="pais" value="<?php echo htmlspecialchars((string)$assignValues['pais'], ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="worker-email" class="form-label">Correo electrónico</label>
                                                <input type="email" class="form-control" id="worker-email" name="email" value="<?php echo htmlspecialchars((string)$assignValues['email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="worker-telefono" class="form-label">Teléfono</label>
                                                <input type="text" class="form-control" id="worker-telefono" name="telefono" value="<?php echo htmlspecialchars((string)$assignValues['telefono'], ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                            <div class="col-12">
                                                <label for="worker-direccion" class="form-label">Dirección</label>
                                                <input type="text" class="form-control" id="worker-direccion" name="direccion" value="<?php echo htmlspecialchars((string)$assignValues['direccion'], ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="worker-ciudad" class="form-label">Ciudad</label>
                                                <input type="text" class="form-control" id="worker-ciudad" name="ciudad" value="<?php echo htmlspecialchars((string)$assignValues['ciudad'], ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="worker-provincia" class="form-label">Provincia</label>
                                                <input type="text" class="form-control" id="worker-provincia" name="provincia" value="<?php echo htmlspecialchars((string)$assignValues['provincia'], ENT_QUOTES, 'UTF-8'); ?>">
                                            </div>
                                        </div>
                                        <div class="text-end mt-4">
                                            <button type="submit" class="btn btn-primary">Asignar trabajador</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="col-12 col-lg-4">
                                    <div class="alert alert-info" role="alert">
                                        Completa los datos del trabajador. Si el correo no corresponde a una cuenta existente se mostrara un aviso; pronto enviaremos invitaciones automaticas.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php foreach ($workers as $worker): ?>
                            <?php
                                $workerId = (int)($worker['id_trabajador'] ?? 0);
                                $tabKey = 'worker_' . $workerId;
                                $isActive = $activeTab === $tabKey;
                                $fullName = trim((string)($worker['nombre'] ?? '') . ' ' . (string)($worker['apellido'] ?? ''));
                                if ($fullName === '') {
                                    $fullName = (string)($worker['email'] ?? 'Trabajador');
                                }
                                $email = (string)($worker['email'] ?? '');
                                $telefono = (string)($worker['telefono'] ?? '');
                            ?>
                            <div class="tab-pane fade <?php echo $isActive ? 'show active' : ''; ?>" id="panel-worker-<?php echo $workerId; ?>" role="tabpanel" aria-labelledby="tab-worker-<?php echo $workerId; ?>">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <h2 class="h5 mb-3">Detalle del trabajador</h2>
                                        <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php if ($email !== ''): ?>
                                            <p class="mb-1"><strong>Correo:</strong> <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                        <div class="row g-2 mt-2">
                                            <?php if (!empty($worker['dni'])): ?><div class="col-md-6"><strong>DNI:</strong> <?php echo htmlspecialchars((string)$worker['dni'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                                            <?php if (!empty($worker['direccion'])): ?><div class="col-md-6"><strong>Dirección:</strong> <?php echo htmlspecialchars((string)$worker['direccion'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                                            <?php if (!empty($worker['ciudad'])): ?><div class="col-md-6"><strong>Ciudad:</strong> <?php echo htmlspecialchars((string)$worker['ciudad'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                                            <?php if (!empty($worker['provincia'])): ?><div class="col-md-6"><strong>Provincia:</strong> <?php echo htmlspecialchars((string)$worker['provincia'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                                            <?php if (!empty($worker['pais'])): ?><div class="col-md-6"><strong>País:</strong> <?php echo htmlspecialchars((string)$worker['pais'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                                        </div>
                                        <div class="text-end mt-3">
                                            <form method="post" onsubmit="return confirm('¿Eliminar este trabajador? Esta acción no se puede deshacer.');">
                                                <input type="hidden" name="action" value="delete_worker">
                                                <input type="hidden" name="worker_id" value="<?php echo $workerId; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                            </form>
                                        </div>
                                        <?php if ($telefono !== ''): ?>
                                            <p class="mb-1"><strong>Teléfono:</strong> <?php echo htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


