<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'sbd.php';
include 'nav.php';

$registro_mensaje = $_SESSION['registro_mensaje'] ?? null;
$registro_tipo = $_SESSION['registro_tipo'] ?? 'info';
$googleClientId = getenv('GOOGLE_CLIENT_ID') ?: 'TU_CLIENT_ID_DE_GOOGLE';
if ($registro_mensaje !== null) {
    unset($_SESSION['registro_mensaje'], $_SESSION['registro_tipo']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta</title>
    <link rel="icon" href="/logos/LOGO PNG-04.png" type="image/png">
    <link rel="stylesheet" href="/AdminLTE-3.2.0/plugins/toastr/toastr.min.css">
    <script>window.googleClientId = '<?php echo htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8'); ?>';</script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
</head>
<body>
    <section class="content-wrapper">
        <div class="container">
            <div class="login-container">
                <div class="login-logo">
                    <img src="logos/LOGO PNG_Mesa de trabajo 1.png" alt="Instituto de Formacion de Operadores">
                </div>
                <?php if ($registro_mensaje !== null) : ?>
                    <div class="alert alert-<?php echo htmlspecialchars($registro_tipo); ?> text-center" role="alert">
                        <?php echo htmlspecialchars($registro_mensaje); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="register.php" id="form-registro">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electronico</label>
                        <input type="email" class="form-control" name="email" id="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contrasena</label>
                        <input type="password" class="form-control" name="password" id="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Repetir contrasena</label>
                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="aceptar_terminos" id="aceptar_terminos" required>
                        <label class="form-check-label" for="aceptar_terminos">
                            Acepto los <a href="index.php">Terminos y condiciones</a>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="btn-registrar">Crear cuenta</button>
                </form>
                <div class="text-center mt-3">
                    <span class="text-muted"> o </span>
                </div>
                <div id="googleSignInMessage" role="alert" style="display:none;"></div>
                <div id="googleSignInButton" class="mt-3 w-100"></div>
            </div>
        </div>
    </section>
    <?php include 'footer.php'; ?>

    <script src="/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/AdminLTE-3.2.0/plugins/toastr/toastr.min.js"></script>
    <script>
        (function () {
            var form = document.getElementById('form-registro');
            var password = document.getElementById('password');
            var confirm = document.getElementById('confirm_password');
            var submitButton = document.getElementById('btn-registrar');

            var setToastOptions = function () {
                if (typeof toastr === 'undefined') {
                    return;
                }
                toastr.options = {
                    closeButton: true,
                    progressBar: true,
                    newestOnTop: true,
                    positionClass: 'toast-top-right',
                    timeOut: 6000
                };
            };

            var showToast = function (type, message) {
                if (typeof toastr === 'undefined') {
                    alert(message);
                    return;
                }
                setToastOptions();
                if (type === 'success') {
                    toastr.success(message);
                    return;
                }
                toastr.error(message);
            };

            var validatePasswords = function () {
                if (!password || !confirm) {
                    return;
                }
                if (confirm.value !== password.value) {
                    confirm.setCustomValidity('Las contrasenas no coinciden');
                } else {
                    confirm.setCustomValidity('');
                }
            };

            if (password) {
                password.addEventListener('input', validatePasswords);
            }
            if (confirm) {
                confirm.addEventListener('input', validatePasswords);
            }

            if (!form) {
                return;
            }

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                validatePasswords();
                if (confirm && confirm.validationMessage) {
                    showToast('error', confirm.validationMessage);
                    return;
                }

                if (submitButton) {
                    submitButton.disabled = true;
                }

                var formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    body: formData
                }).then(function (response) {
                    return response.json().catch(function () {
                        throw new Error('Respuesta inesperada del servidor.');
                    }).then(function (data) {
                        return { ok: response.ok, body: data };
                    });
                }).then(function (result) {
                    var data = result.body || {};
                    if (result.ok && data.ok) {
                        form.reset();
                        showToast('success', data.message || 'Revisa tu correo para activar tu cuenta.');
                    } else {
                        var message = data.message || 'No pudimos procesar el registro.';
                        showToast('error', message);
                    }
                }).catch(function (error) {
                    showToast('error', error.message || 'Ocurrio un error al enviar la solicitud.');
                }).finally(function () {
                    if (submitButton) {
                        submitButton.disabled = false;
                    }
                });
            });
        })();
    </script>
    <script>window.googleAuthEndpoint = 'admin/google_auth.php';</script>
    <script src="assets/js/google-auth.js"></script>
</body>
</html>