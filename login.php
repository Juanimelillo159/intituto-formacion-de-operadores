<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Asegurate de cargar config.php ANTES de usar GOOGLE_CLIENT_ID
require_once __DIR__ . '/config.php';

// Si tu sbd.php ya incluye config.php, igual no molesta tener ambos como require_once
require_once __DIR__ . '/sbd.php';

$page_title = "Login | Instituto de Formación";
$page_description = "Pagina de inicio de sesión del Instituto de Formación de Operadores";

$login_mensaje = $_SESSION['login_mensaje'] ?? null;
$login_tipo    = $_SESSION['login_tipo'] ?? 'info';

// Tomamos el Client ID desde la constante definida en config.php
$googleClientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : (getenv('GOOGLE_CLIENT_ID') ?: 'TU_CLIENT_ID_DE_GOOGLE');

// Pedimos que head.php cargue la librería de Google
$include_google_auth = true;

// Limpiamos el mensaje para que no quede pegado entre refrescos
if ($login_mensaje !== null) {
    unset($_SESSION['login_mensaje'], $_SESSION['login_tipo']);
}
?>
<!DOCTYPE html>
<html lang="es">
<?php include __DIR__ . "/head.php"; ?>
<body>
<?php include __DIR__ . "/nav.php"; ?>

<section class="content-wrapper">
  <div class="container">
    <div class="login-container">
      <div class="login-logo">
        <img src="logos/LOGO PNG_Mesa de trabajo 1.png" alt="Instituto de Formación de Operadores">
      </div>

      <?php if ($login_mensaje !== null): ?>
        <div class="alert alert-<?php echo htmlspecialchars($login_tipo); ?> text-center" role="alert">
          <?php echo $login_mensaje; ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="admin/sesion.php" id="form-login">
        <div class="mb-3">
          <label for="email" class="form-label">Correo electrónico</label>
          <input type="email" class="form-control" name="email" id="email" required>
        </div>
        <div class="mb-3">
          <label for="clave" class="form-label">Contraseña</label>
          <input type="password" class="form-control" name="clave" id="clave" required>
        </div>
        <button type="submit" name="iniciar_sesion" class="btn btn-primary w-100">Iniciar sesión</button>
        <div class="text-center mt-2">
          <a href="recuperar.php">Olvidaste tu contraseña?</a>
        </div>
        <p class="text-center mt-3">No tienes cuenta? <a href="registro.php">Crear cuenta</a></p>
      </form>

      <div class="text-center mt-3"><span class="text-muted"> o </span></div>
      <div id="googleSignInMessage" role="alert" style="display:none;"></div>
      <div id="googleSignInButton" class="mt-3 w-100"></div>
    </div>
  </div>
</section>

<?php include __DIR__ . "/footer.php"; ?>

<script src="/AdminLTE-3.2.0/plugins/jquery/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="/AdminLTE-3.2.0/plugins/sweetalert2/sweetalert2.min.js"></script>

<script>
(function () {
  var form = document.getElementById('form-login');
  var emailInput = document.getElementById('email');

  function showModal(type, message, title) {
    var text = message == null ? '' : String(message);
    if (typeof Swal === 'undefined') { alert(text); return; }
    var icon = 'info', defaultTitle = 'Aviso';
    if (type === 'success') { icon = 'success'; defaultTitle = 'Correo reenviado'; }
    else if (type === 'error' || type === 'danger') { icon = 'error'; defaultTitle = 'No pudimos completar la acción'; }
    else if (type === 'warning') { icon = 'warning'; defaultTitle = 'Atención'; }
    Swal.fire({ icon, title: title || defaultTitle, text, confirmButtonText: 'Aceptar',
      customClass: { confirmButton: 'btn btn-primary' }, buttonsStyling: false });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('.reenviar-verificacion');
    if (!link) return;
    event.preventDefault();
    if (link.dataset.loading === '1') return;

    var email = (link.getAttribute('data-email') || '').trim();
    if (!email && emailInput) { email = emailInput.value.trim(); }
    if (!email) {
      showModal('error', 'Ingresa tu correo para reenviar la verificación.', 'Falta correo');
      if (emailInput) emailInput.focus();
      return;
    }

    link.dataset.loading = '1';
    link.classList.add('disabled');

    fetch('reenviar_verificacion.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams({ email: email }).toString()
    })
    .then(function (response) {
      return response.json().catch(function () {
        return { ok: false, message: 'Respuesta inesperada del servidor.' };
      });
    })
    .then(function (data) {
      var message = (data && data.message) || 'No pudimos reenviar el correo.';
      if (data && data.ok) showModal('success', message, 'Revisa tu correo');
      else showModal('error', message, 'No pudimos reenviar');
    })
    .catch(function () {
      showModal('error', 'No pudimos reenviar el correo. Intenta nuevamente.', 'Error de red');
    })
    .finally(function () {
      delete link.dataset.loading;
      link.classList.remove('disabled');
    });
  });

  if (form) form.addEventListener('submit', function () {
    if (emailInput && !emailInput.value) emailInput.focus();
  });
})();
</script>

<script>window.googleAuthEndpoint = 'admin/google_auth.php';</script>
<script src="assets/js/google-auth.js"></script>
</body>
</html>
