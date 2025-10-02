<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sbd.php';

$page_title = "Registro | Instituto de Formación";
$page_description = "Crea tu cuenta en el Instituto de Formación de Operadores";

// Mensajes flash (del handler admin/registro.php)
$registro_mensaje = $_SESSION['registro_mensaje'] ?? null;
$registro_tipo    = $_SESSION['registro_tipo'] ?? 'info';
if ($registro_mensaje !== null) {
    unset($_SESSION['registro_mensaje'], $_SESSION['registro_tipo']);
}

// Para que head.php cargue GIS y exponga window.googleClientId
$googleClientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : (getenv('GOOGLE_CLIENT_ID') ?: 'TU_CLIENT_ID_DE_GOOGLE');
$include_google_auth = true;
?>
<!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . '/head.php'; ?>
<body>

<?php include __DIR__ . '/nav.php'; ?>

<section class="content-wrapper">
  <div class="container py-5">
    <div class="row justify-content-center">
      <!-- Ancho responsivo y algo más amplio en desktop -->
      <div class="col-12 col-sm-10 col-md-8 col-lg-7 col-xl-6">
        <div class="card shadow-sm border-0">
          <div class="card-body p-4 p-md-5">

            <div class="text-center mb-4">
              <img class="img-fluid" style="max-width: 220px; height: auto;"
                   src="logos/LOGO PNG_Mesa de trabajo 1.png"
                   alt="Instituto de Formacion de Operadores">
            </div>

            <?php if ($registro_mensaje !== null): ?>
              <div class="alert alert-<?php echo htmlspecialchars($registro_tipo, ENT_QUOTES, 'UTF-8'); ?> text-center" role="alert">
                <?php echo htmlspecialchars($registro_mensaje, ENT_QUOTES, 'UTF-8'); ?>
              </div>
            <?php endif; ?>

            <!-- Formulario clásico: names alineados con admin/registro.php -->
            <form method="POST" action="register.php" id="form-registro" novalidate>
              <div class="row g-3">
                <div class="col-12 col-sm-6">
                  <label for="nombre" class="form-label">Nombre</label>
                  <input type="text" class="form-control" name="nombre" id="nombre" required autocomplete="given-name">
                </div>

                <div class="col-12 col-sm-6">
                  <label for="apellido" class="form-label">Apellido</label>
                  <input type="text" class="form-control" name="apellido" id="apellido" required autocomplete="family-name">
                </div>

                <div class="col-12">
                  <label for="telefono" class="form-label">Número de teléfono</label>
                  <input type="tel" class="form-control" name="telefono" id="telefono" required
                         autocomplete="tel" inputmode="tel"
                         pattern="[0-9+()\s-]{6,}"
                         title="Ingresa un número de teléfono válido.">
                </div>

                <div class="col-12">
                  <label for="email" class="form-label">Correo electrónico</label>
                  <input type="email" class="form-control" name="email" id="email" required autocomplete="email">
                </div>

                <div class="col-12 col-sm-6">
                  <label for="clave" class="form-label">Contraseña</label>
                  <input type="password" class="form-control" name="clave" id="clave" required autocomplete="new-password">
                </div>

                <div class="col-12 col-sm-6">
                  <label for="confirmar_clave" class="form-label">Repetir contraseña</label>
                  <input type="password" class="form-control" name="confirmar_clave" id="confirmar_clave" required autocomplete="new-password">
                </div>

                <div class="col-12">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="aceptar_terminos" id="aceptar_terminos" required>
                    <label class="form-check-label" for="aceptar_terminos">
                      Acepto los <a href="index.php">Términos y condiciones</a>
                    </label>
                  </div>
                </div>

                <div class="col-12">
                  <button type="submit" name="registrar_usuario" class="btn btn-primary w-100" id="btn-registrar">
                    Crear cuenta
                  </button>
                </div>
              </div>
            </form>

           <!-- Google: mensaje + botón centrado -->
            <div class="text-center mt-3"><span class="text-muted"> o </span></div>
            <div id="googleSignInMessage" role="alert" style="display:none;"></div>
            <div class="d-flex justify-content-center mt-3">
            <div id="googleSignInButton"></div>

          </div><!--/card-body-->
        </div><!--/card-->
      </div><!--/col-->
    </div><!--/row-->
  </div><!--/container-->
</section>

<?php include __DIR__ . '/footer.php'; ?>

<!-- Libs -->
<script src="/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>

<!-- Validaciones simples en cliente (opcional) -->
<script>
(function() {
  var form          = document.getElementById('form-registro');
  var clave         = document.getElementById('clave');
  var confirmar     = document.getElementById('confirmar_clave');
  var submitButton  = document.getElementById('btn-registrar');
  var nombre        = document.getElementById('nombre');
  var apellido      = document.getElementById('apellido');
  var telefono      = document.getElementById('telefono');
  var emailInput    = document.getElementById('email');

  function showModal(type, message, title) {
    var text = message == null ? '' : String(message);
    if (typeof Swal === 'undefined') { alert(text); return; }
    var icon = 'info', defaultTitle = 'Aviso';
    if (type === 'success') { icon = 'success'; defaultTitle = 'Cuenta creada'; }
    else if (type === 'error' || type === 'danger') { icon = 'error'; defaultTitle = 'Hubo un problema'; }
    else if (type === 'warning') { icon = 'warning'; defaultTitle = 'Atención'; }
    Swal.fire({
      icon: icon,
      title: title || defaultTitle,
      text: text,
      confirmButtonText: 'Aceptar',
      customClass: { confirmButton: 'btn btn-primary' },
      buttonsStyling: false
    });
  }

  function validatePasswords() {
    if (!clave || !confirmar) return;
    confirmar.setCustomValidity(confirmar.value !== clave.value ? 'Las contraseñas no coinciden' : '');
  }
  if (clave)    clave.addEventListener('input', validatePasswords);
  if (confirmar) confirmar.addEventListener('input', validatePasswords);

  if (!form) return;

  form.addEventListener('submit', function (e) {
    // Validaciones mínimas en cliente (el backend valida de verdad)
    validatePasswords();
    if (confirmar && confirmar.validationMessage) {
      e.preventDefault();
      showModal('error', confirmar.validationMessage, 'Datos incompletos');
      return;
    }
    if (telefono && !/^[0-9+()\s-]{6,}$/.test(telefono.value.trim())) {
      e.preventDefault();
      showModal('error', 'Ingresa un número de teléfono válido.', 'Datos incompletos');
      telefono.focus();
      return;
    }
  });
})();
</script>

<!-- Google Auth (mismo endpoint que login.php) -->
<script>window.googleAuthEndpoint = 'admin/google_auth.php';</script>
<script src="assets/js/google-auth.js"></script>

</body>
</html>
