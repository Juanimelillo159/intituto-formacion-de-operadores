<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/password_reset_helpers.php';

$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$tokenValido = false;
$errorMensaje = '';

if ($token === '') {
    $errorMensaje = 'El enlace de recuperacion no es valido. Solicita uno nuevo.';
} else {
    $pdo = getPdo();

    try {
        ensurePasswordResetTable($pdo);
        purgeExpiredPasswordResets($pdo);

        $stmt = $pdo->prepare('SELECT r.id_reset FROM recuperaciones_contrasena r WHERE r.token = ? AND r.utilizado = 0 AND r.expiracion > NOW() LIMIT 1');
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reset) {
            $tokenValido = true;
        } else {
            $errorMensaje = 'El enlace ya expiro o fue utilizado. Solicita una nueva recuperacion.';
        }
    } catch (Throwable $exception) {
        $errorMensaje = 'No pudimos validar el enlace de recuperacion. Intenta nuevamente mas tarde.';
    }
}

$page_title = 'Restablecer contrasena | Instituto de Formacion';
$page_description = 'Define una nueva contrasena para tu cuenta.';
?>
<!DOCTYPE html>
<html lang="es">
<?php include 'head.php'; ?>
<body>
<?php include 'nav.php'; ?>
    <section class="content-wrapper">
        <div class="container">
            <div class="login-container">
                <div class="login-logo">
                    <img src="logos/LOGO PNG_Mesa de trabajo 1.png" alt="Instituto de Formacion de Operadores">
                </div>
                <?php if ($tokenValido): ?>
                    <h1 class="h4 text-center mb-3">Crear nueva contrasena</h1>
                    <p class="text-center text-muted mb-4">Ingresa una contrasena segura y confirmala para completar el proceso.</p>
                    <form id="form-restablecer" method="POST" action="password_reset_update.php" data-token="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva contrasena</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar contrasena</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>
                        <button type="submit" class="btn btn-primary w-100" id="btn-restablecer">Actualizar contrasena</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning text-center" role="alert">
                        <?php echo htmlspecialchars($errorMensaje); ?>
                    </div>
                    <div class="text-center">
                        <a class="btn btn-primary" href="recuperar.php">Solicitar un nuevo enlace</a>
                    </div>
                <?php endif; ?>
                <div class="text-center mt-3">
                    <a href="login.php">Volver al inicio de sesion</a>
                </div>
            </div>
        </div>
    </section>
    <?php include 'footer.php'; ?>

    <script src="/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
    <?php if ($tokenValido): ?>
    <script>
        (function() {
            var form = document.getElementById('form-restablecer');
            if (!form) {
                return;
            }

            var passwordInput = document.getElementById('password');
            var confirmInput = document.getElementById('confirm_password');
            var submitButton = document.getElementById('btn-restablecer');
            var token = form.getAttribute('data-token') || '';

            var showModal = function(type, message, title) {
                var text = message === undefined || message === null ? '' : String(message);

                if (typeof Swal === 'undefined') {
                    alert(text);
                    return;
                }

                var icon = 'info';
                var defaultTitle = 'Aviso';

                switch (type) {
                    case 'success':
                        icon = 'success';
                        defaultTitle = 'Contrasena actualizada';
                        break;
                    case 'error':
                        icon = 'error';
                        defaultTitle = 'Hubo un problema';
                        break;
                    case 'warning':
                        icon = 'warning';
                        defaultTitle = 'Atencion';
                        break;
                }

                Swal.fire({
                    icon: icon,
                    title: title || defaultTitle,
                    text: text,
                    confirmButtonText: 'Aceptar',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                });
            };

            form.addEventListener('submit', function(event) {
                event.preventDefault();

                if (!passwordInput || passwordInput.value.trim().length < 8) {
                    showModal('error', 'La contrasena debe tener al menos 8 caracteres.', 'Datos incompletos');
                    if (passwordInput) {
                        passwordInput.focus();
                    }
                    return;
                }

                if (!confirmInput || confirmInput.value !== passwordInput.value) {
                    showModal('error', 'Las contrasenas no coinciden.', 'Datos incompletos');
                    if (confirmInput) {
                        confirmInput.focus();
                    }
                    return;
                }

                if (submitButton) {
                    submitButton.disabled = true;
                }

                var formData = new FormData();
                formData.set('token', token);
                formData.set('password', passwordInput.value);
                formData.set('confirm', confirmInput.value);

                fetch(form.action, {
                    method: 'POST',
                    body: formData
                }).then(function(response) {
                    return response.json().catch(function() {
                        throw new Error('Respuesta inesperada del servidor.');
                    }).then(function(data) {
                        return {
                            ok: response.ok,
                            body: data
                        };
                    });
                }).then(function(result) {
                    var data = result.body || {};

                    if (result.ok && data.ok) {
                        passwordInput.value = '';
                        confirmInput.value = '';
                        showModal('success', data.message || 'Tu contrasena se actualizo correctamente.');
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 3000);
                    } else {
                        var message = data.message || 'No pudimos actualizar la contrasena.';
                        showModal('error', message, 'No se pudo completar');
                    }
                }).catch(function(error) {
                    showModal('error', error.message || 'Ocurrio un error al enviar la solicitud.', 'Error de red');
                }).finally(function() {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
            });
        })();
    </script>
    <?php endif; ?>
</body>
</html>
