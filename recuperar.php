<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/sbd.php';

$page_title = 'Recuperar contrasena | Instituto de Formacion';
$page_description = 'Solicitud de restablecimiento de contrasena.';
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
                <h1 class="h4 text-center mb-3">Recuperar contrasena</h1>
                <p class="text-center text-muted mb-4">Ingresa el correo con el que te registraste y te enviaremos un enlace para crear una nueva contrasena.</p>
                <form id="form-recuperar" method="POST" action="password_reset_request.php">
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo electronico</label>
                        <input type="email" class="form-control" id="email" name="email" required autocomplete="email">
                    </div>
                    <button type="submit" class="btn btn-primary w-100" id="btn-recuperar">Enviar instrucciones</button>
                </form>
                <div class="text-center mt-3">
                    <a href="login.php">Volver al inicio de sesion</a>
                </div>
            </div>
        </div>
    </section>
    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" integrity="sha384-1H217gwSVyLSIfaLxHbE7dRb3v4mYCKbpQvzx0cegeju1MVsGrX5xXxAvs/HgeFs" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.7/dist/sweetalert2.min.js" integrity="sha384-xIU22upJvFOpmGRB8OlVXiM8Kj5s9wgkKuxGfNDb0bDGPBoxineCH0/huelSnred" crossorigin="anonymous"></script>
    <script>
        (function() {
            var form = document.getElementById('form-recuperar');
            var emailInput = document.getElementById('email');
            var submitButton = document.getElementById('btn-recuperar');

            if (!form) {
                return;
            }

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
                        defaultTitle = 'Solicitud enviada';
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

                if (!emailInput || emailInput.value.trim() === '') {
                    showModal('error', 'Ingresa un correo electronico.', 'Datos incompletos');
                    if (emailInput) {
                        emailInput.focus();
                    }
                    return;
                }

                if (submitButton) {
                    submitButton.disabled = true;
                }

                var formData = new FormData(form);
                if (emailInput) {
                    formData.set('email', emailInput.value.trim());
                }

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
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
                        showModal('success', data.message || 'Si tu correo existe en nuestra base, recibiras instrucciones en breve.');
                    } else {
                        var message = data.message || 'No pudimos procesar la solicitud.';
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
</body>
</html>

