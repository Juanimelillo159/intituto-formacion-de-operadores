(function () {
    var container = document.getElementById('googleSignInButton');
    if (!container) {
        return;
    }

    var messageBox = document.getElementById('googleSignInMessage');
    var clientId = window.googleClientId || '';
    var endpoint = window.googleAuthEndpoint || 'admin/google_auth.php';
    var placeholderId = 'TU_CLIENT_ID_DE_GOOGLE';

    var showMessage = function (text, type) {
        if (!messageBox) {
            return;
        }
        var alertType = type || 'danger';
        messageBox.className = 'alert mt-3 alert-' + alertType;
        messageBox.textContent = text;
        messageBox.style.display = 'block';
    };

    var hideMessage = function () {
        if (messageBox) {
            messageBox.style.display = 'none';
        }
    };

    if (!clientId || clientId === placeholderId) {
        showMessage('Configura el Client ID de Google para habilitar el acceso con Google.', 'warning');
        return;
    }

    var sendCredential = function (credential) {
        if (!credential) {
            showMessage('No se recibio el token de Google.', 'danger');
            return;
        }
        showMessage('Verificando credenciales de Google...', 'info');
        fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ credential: credential })
        })
        .then(function (response) {
        if (!response.ok) {
            return response.text().then(function (txt) {
            // Muestra el status y el texto (por si el servidor devolvió HTML de error o JSON con error)
            var msg = 'HTTP ' + response.status + (txt ? ' · ' + txt : '');
            throw new Error(msg);
            });
        }
        return response.json().catch(function (e) {
            // El servidor respondió 200 pero no es JSON válido
            throw new Error('Respuesta no JSON del servidor');
        });
        })
        .then(function (payload) {
        if (payload && payload.success) {
            hideMessage();
            window.location.href = payload.redirect || 'index.php';
            return;
        }
        var message = (payload && payload.message) ? payload.message : 'No se pudo completar el acceso con Google.';
        showMessage(message, 'danger');
        })
.catch(function (err) {
  console.error('[GOOGLE AUTH FETCH ERROR]', err);
  showMessage('No se pudo verificar la cuenta de Google. ' + (err && err.message ? err.message : 'Intentalo nuevamente.'), 'danger');
});


    var renderButton = function () {
        google.accounts.id.initialize({
            client_id: clientId,
            callback: function (response) {
                sendCredential(response && response.credential);
            },
            auto_select: false,
            context: 'signin'
        });
        google.accounts.id.renderButton(container, {
            theme: 'outline',
            size: 'large',
            width: '100%'
        });
        google.accounts.id.prompt();
    };

    var attempts = 0;
    var maxAttempts = 50;
    var waitForLibrary = function () {
        if (window.google && window.google.accounts && window.google.accounts.id) {
            renderButton();
            return;
        }
        attempts += 1;
        if (attempts > maxAttempts) {
            showMessage('No se pudo cargar Google Identity Services. Revisa tu conexion.', 'danger');
            return;
        }
        setTimeout(waitForLibrary, 200);
    };

    waitForLibrary();
})();
