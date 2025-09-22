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
<?php $include_google_auth = true;
include("head.php") ?>

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
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="nombre" id="nombre" required autocomplete="given-name">
                    </div>
                    <div class="mb-3">
                        <label for="apellido" class="form-label">Apellido</label>
                        <input type="text" class="form-control" name="apellido" id="apellido" required autocomplete="family-name">
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Numero de telefono</label>
                        <input type="tel" class="form-control" name="telefono" id="telefono" required autocomplete="tel" inputmode="tel" pattern="[0-9+()\s-]{6,}" title="Ingresa un numero de telefono valido.">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electronico</label>
                        <input type="email" class="form-control" name="email" id="email" required autocomplete="email">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" name="password" id="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Repetir contraseña</label>
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
    <script src="/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>
    <script>
        (function() {
            var form = document.getElementById('form-registro');
            var password = document.getElementById('password');
            var confirm = document.getElementById('confirm_password');
            var submitButton = document.getElementById('btn-registrar');
            var nombre = document.getElementById('nombre');
            var apellido = document.getElementById('apellido');
            var telefono = document.getElementById('telefono');
            var emailInput = document.getElementById('email');

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
                        defaultTitle = 'Cuenta creada';
                        break;
                    case 'error':
                    case 'danger':
                        icon = 'error';
                        defaultTitle = 'Hubo un problema';
                        break;
                    case 'warning':
                        icon = 'warning';
                        defaultTitle = 'Atencion';
                        break;
                }

                var confirmText = 'Aceptar';
                var redirectOnConfirm = null;

                if (icon === 'success') {
                    confirmText = 'Iniciar Sesion';
                    redirectOnConfirm = function() {
                        window.location.href = 'login.php';
                    };
                }

                var modalOptions = {
                    icon: icon,
                    title: title || defaultTitle,
                    text: text,
                    confirmButtonText: confirmText,
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                };

                var modal = Swal.fire(modalOptions);

                if (redirectOnConfirm) {
                    modal.then(function(result) {
                        if (result.isConfirmed) {
                            redirectOnConfirm();
                        }
                    });
                }
            };

            var isEmpty = function(value) {
                return value === null || value === undefined || value.trim() === '';
            };

            var getTrimmedValue = function(input) {
                if (!input) {
                    return '';
                }
                return input.value.trim();
            };

            var validatePasswords = function() {
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

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                validatePasswords();

                if (confirm && confirm.validationMessage) {
                    showModal('error', confirm.validationMessage, 'Datos incompletos');
                    return;
                }

                var requiredFields = [
                    { element: nombre, message: 'Ingresa tu nombre.' },
                    { element: apellido, message: 'Ingresa tu apellido.' },
                    { element: telefono, message: 'Ingresa tu numero de telefono.' },
                    { element: emailInput, message: 'Ingresa tu correo electronico.' }
                ];

                for (var i = 0; i < requiredFields.length; i++) {
                    var field = requiredFields[i];
                    if (!field.element) {
                        continue;
                    }
                    if (isEmpty(field.element.value)) {
                        showModal('error', field.message, 'Datos incompletos');
                        field.element.focus();
                        return;
                    }
                }

                if (telefono) {
                    var phoneValue = telefono.value.trim();
                    var phonePattern = /^[0-9+()\s-]{6,}$/;
                    if (!phonePattern.test(phoneValue)) {
                        showModal('error', 'Ingresa un numero de telefono valido.', 'Datos incompletos');
                        telefono.focus();
                        return;
                    }
                }

                if (submitButton) {
                    submitButton.disabled = true;
                }

                var formData = new FormData(form);

                if (nombre) {
                    formData.set('nombre', getTrimmedValue(nombre));
                }
                if (apellido) {
                    formData.set('apellido', getTrimmedValue(apellido));
                }
                if (telefono) {
                    formData.set('telefono', getTrimmedValue(telefono));
                }
                if (emailInput) {
                    formData.set('email', getTrimmedValue(emailInput));
                }

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
                        form.reset();
                        showModal('success', data.message || 'Revisa tu correo para activar tu cuenta.');
                    } else {
                        var message = data.message || 'No pudimos procesar el registro.';
                        showModal('error', message, 'No se pudo registrar');
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
    <script>
        window.googleAuthEndpoint = 'admin/google_auth.php';
    </script>
    <script src="assets/js/google-auth.js"></script>
</body>
</html>