<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sbd.php';

$page_title = "Login | Instituto de Formación";
$page_description = "Pagina de inicio de sesión del Instituto de Formación de Operadores";

$session_user = $_SESSION['usuario'] ?? null;
$session_user_id = $_SESSION['id_usuario'] ?? null;
$has_active_session = false;

if ($session_user_id && is_numeric($session_user_id) && (int)$session_user_id > 0) {
    $has_active_session = true;
} elseif (is_array($session_user) && isset($session_user['id_usuario']) && is_numeric($session_user['id_usuario'])) {
    $has_active_session = (int)$session_user['id_usuario'] > 0;
} elseif (!empty($session_user) && !is_array($session_user)) {
    $has_active_session = true;
}

$login_mensaje = $_SESSION['login_mensaje'] ?? null;
$login_tipo    = $_SESSION['login_tipo'] ?? 'info';

if ($login_mensaje !== null) {
    unset($_SESSION['login_mensaje'], $_SESSION['login_tipo']);
}

$googleClientId = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : (getenv('GOOGLE_CLIENT_ID') ?: 'TU_CLIENT_ID_DE_GOOGLE');
$include_google_auth = true;
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

        <?php if ($has_active_session): ?>
          <div class="alert alert-info text-center" role="alert">
            Ya tenés una sesión iniciada. Podés continuar desde <a href="mis_cursos.php" class="alert-link">Mis cursos</a> o visitar el <a href="index.php" class="alert-link">inicio</a>.
          </div>
        <?php else: ?>
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
          <div class="d-flex justify-content-center mt-3">
            <div id="googleSignInButton"></div>
          </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . "/footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js" crossorigin="anonymous"></script>

<script>
(function () {
  var form = document.getElementById('form-login');
  var emailInput = document.getElementById('email');

  function mapIcon(type) {
    if (type === 'success') return 'success';
    if (type === 'warning') return 'warning';
    if (type === 'error' || type === 'danger') return 'error';
    return 'info';
  }

  function showModal(type, message, title) {
    if (typeof Swal === 'undefined') {
      var text = message == null ? '' : String(message);
      var header = title ? title + '\n' : '';
      window.alert((header + text.replace(/<[^>]+>/g, ' ')).trim());
      return;
    }

    var icon = mapIcon(type);
    var defaultTitle = 'Aviso';
    if (icon === 'success') defaultTitle = 'Listo';
    else if (icon === 'warning') defaultTitle = 'Atención';
    else if (icon === 'error') defaultTitle = 'Hubo un problema';

    Swal.fire({
      icon: icon,
      title: title || defaultTitle,
      html: message == null ? '' : String(message),
      confirmButtonText: 'Aceptar',
      customClass: { confirmButton: 'btn btn-primary' },
      buttonsStyling: false
    });
  }

  document.addEventListener('click', function (event) {
    var link = event.target.closest('.reenviar-verificacion');
    if (!link) return;
    event.preventDefault();
    if (link.dataset.loading === '1') return;

    var email = (link.getAttribute('data-email') || '').trim();
    if (!email && emailInput) {
      email = emailInput.value.trim();
    }
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
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: new URLSearchParams({ email: email }).toString()
    })
      .then(function (response) {
        return response.json().catch(function () {
          return { ok: false, message: 'Respuesta inesperada del servidor.' };
        });
      })
      .then(function (data) {
        var message = (data && data.message) || 'No pudimos reenviar el correo.';
        if (data && data.ok) {
          showModal('success', message, 'Revisa tu correo');
        } else {
          showModal('error', message, 'No pudimos reenviar');
        }
      })
      .catch(function () {
        showModal('error', 'No pudimos reenviar el correo. Intenta nuevamente.', 'Error de red');
      })
      .finally(function () {
        delete link.dataset.loading;
        link.classList.remove('disabled');
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


