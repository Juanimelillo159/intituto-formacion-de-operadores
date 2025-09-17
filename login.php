<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("sbd.php");
include("nav.php");

$login_mensaje = isset($_SESSION['login_mensaje']) ? $_SESSION['login_mensaje'] : null;
$login_tipo = isset($_SESSION['login_tipo']) ? $_SESSION['login_tipo'] : 'info';
$googleClientId = getenv('GOOGLE_CLIENT_ID') ?: 'TU_CLIENT_ID_DE_GOOGLE';
if ($login_mensaje !== null) {
    unset($_SESSION['login_mensaje'], $_SESSION['login_tipo']);
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesi&oacute;n</title>
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
                    <img src="logos/LOGO PNG_Mesa de trabajo 1.png" alt="Instituto de Formaci&oacute;n de Operadores">
                </div>
                <?php if ($login_mensaje !== null) : ?>
                    <div class="alert alert-<?php echo htmlspecialchars($login_tipo); ?> text-center" role="alert">
                        <?php echo $login_mensaje; ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="admin/sesion.php" id="form-login">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electr&oacute;nico</label>
                        <input type="email" class="form-control" name="email" id="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="clave" class="form-label">Contrase&ntilde;a</label>
                        <input type="password" class="form-control" name="clave" id="clave" required>
                    </div>
                    <button type="submit" name="iniciar_sesion" class="btn btn-primary w-100">Iniciar sesi&oacute;n</button>
                    <p class="text-center mt-3">&iquest;No tienes cuenta? <a href="registro.php">Crear cuenta</a></p>
                </form>
                <div class="text-center mt-3">
                    <span class="text-muted"> o </span>
                </div>
                <div id="googleSignInMessage" role="alert" style="display:none;"></div>
                <div id="googleSignInButton" class="mt-3 w-100"></div>
            </div>
        </div>
    </section>
    <?php include("footer.php"); ?>
    <script src="/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/AdminLTE-3.2.0/plugins/toastr/toastr.min.js"></script>
    <script>
        (function () {
            var form = document.getElementById('form-login');
            var emailInput = document.getElementById('email');
            var configureToastr = function () {
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

            document.addEventListener('click', function (event) {
                var target = event.target;
                if (!target.classList || !target.classList.contains('reenviar-verificacion')) {
                    return;
                }
                event.preventDefault();

                configureToastr();

                if (target.dataset.loading === '1') {
                    return;
                }

                var email = (target.getAttribute('data-email') || '').trim();
                if (!email && emailInput) {
                    email = emailInput.value.trim();
                }

                if (!email) {
                    if (typeof toastr !== 'undefined') {
                        toastr.error('Ingresa tu correo para reenviar la verificacion.');
                    } else {
                        alert('Ingresa tu correo para reenviar la verificacion.');
                    }
                    if (emailInput) {
                        emailInput.focus();
                    }
                    return;
                }

                target.dataset.loading = '1';
                target.classList.add('disabled');

                fetch('reenviar_verificacion.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({ email: email }).toString()
                }).then(function (response) {
                    return response.json().catch(function () {
                        return { ok: false, message: 'Respuesta inesperada del servidor.' };
                    });
                }).then(function (data) {
                    var message = (data && data.message) || 'No pudimos reenviar el correo.';
                    if (data && data.ok) {
                        if (typeof toastr !== 'undefined') {
                            toastr.success(message || 'Correo reenviado correctamente.');
                        } else {
                            alert(message || 'Correo reenviado correctamente.');
                        }
                    } else {
                        if (typeof toastr !== 'undefined') {
                            toastr.error(message);
                        } else {
                            alert(message);
                        }
                    }
                }).catch(function () {
                    if (typeof toastr !== 'undefined') {
                        toastr.error('No pudimos reenviar el correo. Intenta nuevamente.');
                    } else {
                        alert('No pudimos reenviar el correo. Intenta nuevamente.');
                    }
                }).finally(function () {
                    delete target.dataset.loading;
                    target.classList.remove('disabled');
                });
            });

            if (form) {
                form.addEventListener('submit', function () {
                    if (emailInput && !emailInput.value) {
                        emailInput.focus();
                    }
                });
            }
        })();
    </script>

    <script>window.googleAuthEndpoint = 'admin/google_auth.php';</script>
    <script src="assets/js/google-auth.js"></script>
</body>

</html>