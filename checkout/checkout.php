<?php
session_start();
require_once '../sbd.php';

$page_title = "Checkout | Instituto de Formación";
$page_description = "Completá tu inscripción en tres pasos.";

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;

$st = $con->prepare("SELECT * FROM cursos WHERE id_curso = :id");
$st->execute([':id' => $id_curso]);
$curso = $st->fetch(PDO::FETCH_ASSOC);

$precio_vigente = null;
if ($curso) {
    $pv = $con->prepare("
        SELECT precio, moneda, vigente_desde
        FROM curso_precio_hist
        WHERE id_curso = :c
          AND vigente_desde <= NOW()
          AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
        ORDER BY vigente_desde DESC
        LIMIT 1
    ");
    $pv->execute([':c' => $id_curso]);
    $precio_vigente = $pv->fetch(PDO::FETCH_ASSOC);
}

function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$flash_success = $_SESSION['checkout_success'] ?? null;
$flash_error   = $_SESSION['checkout_error'] ?? null;
unset($_SESSION['checkout_success'], $_SESSION['checkout_error']);
?>
<!DOCTYPE html>
<html lang="es">
<?php
$asset_base_path = '../';
$base_path = '../';
$page_styles = '<link rel="stylesheet" href="../checkout/css/style.css">';
include '../head.php';
?>
<body class="checkout-body">
    <?php include '../nav.php'; ?>

    <main class="checkout-main">
        <div class="container">
            <div class="mb-4">
                <a class="back-link" href="<?php echo $base_path; ?>index.php#cursos">
                    <i class="fas fa-arrow-left"></i>
                    Volver al listado de cursos
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-xl-10">
                    <div class="checkout-card">
                        <div class="checkout-header">
                            <h1>Finalizá tu inscripción</h1>
                            <p>Seguí los pasos para reservar tu lugar en la capacitación elegida.</p>
                            <?php if ($curso): ?>
                                <div class="checkout-course-name">
                                    <i class="fas fa-graduation-cap"></i>
                                    <?php echo h($curso['nombre_curso']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$curso): ?>
                            <div class="checkout-content">
                                <div class="alert alert-danger checkout-alert mb-0" role="alert">
                                    No pudimos encontrar la capacitación seleccionada. Volvé al listado e intentá nuevamente.
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="checkout-stepper">
                                <div class="checkout-step is-active" data-step="1">
                                    <div class="step-index">1</div>
                                    <div class="step-label">
                                        Resumen
                                        <span class="step-helper">Detalles del curso</span>
                                    </div>
                                </div>
                                <div class="checkout-step" data-step="2">
                                    <div class="step-index">2</div>
                                    <div class="step-label">
                                        Datos personales
                                        <span class="step-helper">Completá tu información</span>
                                    </div>
                                </div>
                                <div class="checkout-step" data-step="3">
                                    <div class="step-index">3</div>
                                    <div class="step-label">
                                        Pago
                                        <span class="step-helper">Elegí el método</span>
                                    </div>
                                </div>
                            </div>

                            <div class="checkout-content">
                                <?php if ($flash_success): ?>
                                    <div class="alert alert-success checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-circle-check mt-1"></i>
                                            <div>
                                                <strong>¡Inscripción enviada!</strong>
                                                <?php if (!empty($flash_success['orden'])): ?>
                                                    <div>Número de orden: #<?php echo str_pad((string)(int)$flash_success['orden'], 6, '0', STR_PAD_LEFT); ?>.</div>
                                                <?php endif; ?>
                                                <div class="small mt-1">Te contactaremos por correo para completar el proceso.</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($flash_error): ?>
                                    <div class="alert alert-danger checkout-alert" role="alert">
                                        <div class="d-flex align-items-start gap-2">
                                            <i class="fas fa-triangle-exclamation mt-1"></i>
                                            <div>
                                                <strong>No pudimos procesar tu inscripción.</strong>
                                                <div class="small mt-1"><?php echo h($flash_error); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <form id="checkoutForm" action="../admin/procesarsbd.php" method="POST" enctype="multipart/form-data" novalidate>
                                    <input type="hidden" name="__accion" id="__accion" value="">
                                    <input type="hidden" name="crear_orden" value="1">
                                    <input type="hidden" name="id_curso" value="<?php echo (int)$id_curso; ?>">
                                    <input type="hidden" name="precio_checkout" value="<?php echo $precio_vigente ? (float)$precio_vigente['precio'] : 0; ?>">

                                    <div class="step-panel active" data-step="1">
                                        <div class="row g-4 align-items-stretch">
                                            <div class="col-lg-7">
                                                <div class="summary-card h-100">
                                                    <h5>Resumen del curso</h5>
                                                    <div class="summary-item">
                                                        <strong>Nombre</strong>
                                                        <span><?php echo h($curso['nombre_curso']); ?></span>
                                                    </div>
                                                    <div class="summary-item">
                                                        <strong>Duración</strong>
                                                        <span><?php echo h($curso['duracion'] ?? 'A definir'); ?></span>
                                                    </div>
                                                    <div class="summary-item">
                                                        <strong>Nivel</strong>
                                                        <span><?php echo h($curso['complejidad'] ?? 'Intermedio'); ?></span>
                                                    </div>
                                                    <div class="summary-description mt-3">
                                                        <?php echo nl2br(h($curso['descripcion_curso'] ?? '')); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-lg-5">
                                                <div class="summary-card h-100 d-flex flex-column justify-content-between">
                                                    <h5>Inversión</h5>
                                                    <div class="price-highlight">
                                                        <?php if ($precio_vigente): ?>
                                                            <div class="price-value">
                                                                <?php echo strtoupper($precio_vigente['moneda'] ?? 'ARS'); ?> <?php echo number_format((float)$precio_vigente['precio'], 2, ',', '.'); ?>
                                                            </div>
                                                            <span class="price-note">Vigente desde <?php echo date('d/m/Y H:i', strtotime($precio_vigente['vigente_desde'])); ?></span>
                                                        <?php else: ?>
                                                            <div class="text-muted">Precio a confirmar por el equipo comercial.</div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="small text-muted mt-3">
                                                        El equipo se pondrá en contacto para coordinar disponibilidad, medios de pago y comenzar tu proceso.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="nav-actions">
                                            <span></span>
                                            <button type="button" class="btn btn-gradient btn-rounded" data-next="2">
                                                Continuar al paso 2
                                                <i class="fas fa-arrow-right ms-2"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="step-panel" data-step="2">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="nombre" class="form-label required-field">Nombre</label>
                                                <input type="text" class="form-control" id="nombre" name="nombre_insc" placeholder="Nombre" autocomplete="given-name">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="apellido" class="form-label required-field">Apellido</label>
                                                <input type="text" class="form-control" id="apellido" name="apellido_insc" placeholder="Apellido" autocomplete="family-name">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="email" class="form-label required-field">Email</label>
                                                <input type="email" class="form-control" id="email" name="email_insc" placeholder="correo@dominio.com" autocomplete="email">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="telefono" class="form-label required-field">Teléfono</label>
                                                <input type="text" class="form-control" id="telefono" name="tel_insc" placeholder="+54 11 5555-5555" autocomplete="tel">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="dni" class="form-label">DNI</label>
                                                <input type="text" class="form-control" id="dni" name="dni_insc" placeholder="Documento">
                                            </div>
                                            <div class="col-md-8">
                                                <label for="direccion" class="form-label">Dirección</label>
                                                <input type="text" class="form-control" id="direccion" name="dir_insc" placeholder="Calle y número" autocomplete="address-line1">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="ciudad" class="form-label">Ciudad</label>
                                                <input type="text" class="form-control" id="ciudad" name="ciu_insc" autocomplete="address-level2">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="provincia" class="form-label">Provincia</label>
                                                <input type="text" class="form-control" id="provincia" name="prov_insc" autocomplete="address-level1">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="pais" class="form-label">País</label>
                                                <input type="text" class="form-control" id="pais" name="pais_insc" value="Argentina" autocomplete="country-name">
                                            </div>
                                        </div>
                                        <div class="terms-check mt-4">
                                            <input type="checkbox" class="form-check-input mt-1" id="acepta" name="acepta_tyc" value="1">
                                            <label class="form-check-label" for="acepta">
                                                Confirmo que los datos ingresados son correctos y acepto los <a href="#" target="_blank" rel="noopener">Términos y Condiciones</a>.
                                            </label>
                                        </div>
                                        <div class="nav-actions">
                                            <button type="button" class="btn btn-outline-light btn-rounded" data-prev="1">
                                                <i class="fas fa-arrow-left me-2"></i>
                                                Volver
                                            </button>
                                            <button type="button" class="btn btn-gradient btn-rounded" data-next="3">
                                                Continuar al paso 3
                                                <i class="fas fa-arrow-right ms-2"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="step-panel" data-step="3">
                                        <div class="payment-box">
                                            <h5>Método de pago</h5>
                                            <label class="payment-option">
                                                <input type="radio" id="metodo_transfer" name="metodo_pago" value="transferencia" checked>
                                                <div class="payment-info">
                                                    <strong>Transferencia bancaria</strong>
                                                    <span>Subí el comprobante de tu transferencia.</span>
                                                </div>
                                            </label>
                                            <label class="payment-option mt-3">
                                                <input type="radio" id="metodo_mp" name="metodo_pago" value="mercado_pago" <?php echo $precio_vigente ? '' : 'disabled'; ?>>
                                                <div class="payment-info">
                                                    <strong>Mercado Pago</strong>
                                                    <?php if ($precio_vigente): ?>
                                                        <span>Pagá de forma segura con tarjetas, efectivo o saldo en Mercado Pago.</span>
                                                    <?php else: ?>
                                                        <span>Disponible cuando haya un precio vigente para esta capacitación.</span>
                                                    <?php endif; ?>
                                                </div>
                                            </label>

                                            <div class="payment-details" id="transferDetails">
                                                <div class="bank-data">
                                                    <strong>Datos bancarios</strong>
                                                    <ul class="mb-0 mt-2 ps-3">
                                                        <li>Banco: Tu Banco</li>
                                                        <li>CBU: 0000000000000000000000</li>
                                                        <li>Alias: tuempresa.cursos</li>
                                                    </ul>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-lg-8">
                                                        <label for="comprobante" class="form-label required-field">Comprobante de pago</label>
                                                        <input type="file" class="form-control" id="comprobante" name="comprobante" accept=".jpg,.jpeg,.png,.pdf">
                                                        <div class="upload-label">Formatos aceptados: JPG, PNG o PDF. Tamaño máximo 5 MB.</div>
                                                    </div>
                                                    <div class="col-lg-4">
                                                        <label for="obs_pago" class="form-label">Observaciones</label>
                                                        <input type="text" class="form-control" id="obs_pago" name="obs_pago" placeholder="Opcional">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="payment-details hidden" id="mpDetails">
                                                <div class="summary-card">
                                                    <h6 class="mb-3">Pagar con Mercado Pago</h6>
                                                    <p class="mb-2">Al confirmar, crearemos tu orden y te redirigiremos a Mercado Pago para completar el pago en un entorno seguro.</p>
                                                    <?php if ($precio_vigente): ?>
                                                        <?php $mpMontoTexto = sprintf('%s %s', strtoupper($precio_vigente['moneda'] ?? 'ARS'), number_format((float) $precio_vigente['precio'], 2, ',', '.')); ?>
                                                        <p class="mb-2 fw-semibold">Monto a abonar: <?php echo $mpMontoTexto; ?></p>
                                                    <?php endif; ?>
                                                    <ul class="mb-0 small text-muted list-unstyled">
                                                        <li class="mb-1"><i class="fas fa-lock me-2"></i>Usá tu cuenta de Mercado Pago o tus medios de pago habituales.</li>
                                                        <li><i class="fas fa-envelope me-2"></i>Te enviaremos un correo con la confirmación apenas se acredite.</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="nav-actions">
                                            <button type="button" class="btn btn-outline-light btn-rounded" data-prev="2">
                                                <i class="fas fa-arrow-left me-2"></i>
                                                Volver
                                            </button>
                                            <button type="button" class="btn btn-gradient btn-rounded" id="btnConfirmar">
                                                <span class="btn-label">Confirmar inscripción</span>
                                                <i class="fas fa-paper-plane ms-2"></i>
                                            </button>
                                        </div>
                                        <div class="checkout-footer text-center mt-4">
                                            Al confirmar, enviaremos los datos a nuestro equipo para validar tu lugar y nos comunicaremos por correo electrónico.
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include '../footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function () {
            const card = document.querySelector('.checkout-card');
            const steps = Array.from(document.querySelectorAll('.checkout-step'));
            const panels = Array.from(document.querySelectorAll('.step-panel'));
            if (!steps.length || !panels.length) {
                return;
            }

            const mpAvailable = <?php echo $precio_vigente ? 'true' : 'false'; ?>;
            const mpEndpoint = '../checkout/mercadopago_init.php';
            let currentStep = 1;
            let mpProcessing = false;

            const goToStep = (target) => {
                currentStep = target;
                steps.forEach(step => {
                    const stepIndex = parseInt(step.dataset.step, 10);
                    step.classList.toggle('is-active', stepIndex === target);
                    step.classList.toggle('is-complete', stepIndex < target);
                });
                panels.forEach(panel => {
                    const panelIndex = parseInt(panel.dataset.step, 10);
                    panel.classList.toggle('active', panelIndex === target);
                });
                if (card) {
                    window.scrollTo({ top: card.offsetTop - 80, behavior: 'smooth' });
                }
            };

            const showAlert = (icon, title, message) => {
                Swal.fire({
                    icon,
                    title,
                    html: message,
                    confirmButtonText: 'Entendido',
                    customClass: {
                        confirmButton: 'btn btn-gradient btn-rounded'
                    },
                    buttonsStyling: false
                });
            };

            const validateStep = (step) => {
                if (step === 2) {
                    const required = [
                        { id: 'nombre', label: 'Nombre' },
                        { id: 'apellido', label: 'Apellido' },
                        { id: 'email', label: 'Email' },
                        { id: 'telefono', label: 'Teléfono' }
                    ];
                    const missing = required.find(field => {
                        const el = document.getElementById(field.id);
                        return !el || !el.value || el.value.trim() === '';
                    });
                    if (missing) {
                        goToStep(2);
                        showAlert('error', 'Faltan datos', `Completá el campo <strong>${missing.label}</strong> para continuar.`);
                        return false;
                    }
                    const email = document.getElementById('email').value.trim();
                    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailPattern.test(email)) {
                        goToStep(2);
                        showAlert('error', 'Email inválido', 'Ingresá un correo electrónico válido.');
                        return false;
                    }
                    const terms = document.getElementById('acepta');
                    if (!terms.checked) {
                        goToStep(2);
                        showAlert('warning', 'Términos y Condiciones', 'Debés aceptar los Términos y Condiciones para continuar.');
                        return false;
                    }
                }
                if (step === 3) {
                    const mp = document.getElementById('metodo_mp').checked;
                    const transfer = document.getElementById('metodo_transfer').checked;
                    if (!mp && !transfer) {
                        goToStep(3);
                        showAlert('error', 'Seleccioná un método de pago', 'Elegí una forma de pago para continuar.');
                        return false;
                    }
                    if (mp && !mpAvailable) {
                        goToStep(3);
                        showAlert('warning', 'Mercado Pago no disponible', 'Este curso todavía no tiene un precio vigente para pagar online.');
                        return false;
                    }
                    if (transfer) {
                        const fileInput = document.getElementById('comprobante');
                        const file = fileInput.files[0];
                        if (!file) {
                            goToStep(3);
                            showAlert('error', 'Falta el comprobante', 'Adjuntá el comprobante de la transferencia.');
                            return false;
                        }
                        const allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
                        if (!allowedTypes.includes(file.type)) {
                            goToStep(3);
                            showAlert('error', 'Archivo no permitido', 'Solo se aceptan archivos JPG, PNG o PDF.');
                            return false;
                        }
                        const maxSize = 5 * 1024 * 1024;
                        if (file.size > maxSize) {
                            goToStep(3);
                            showAlert('error', 'Archivo demasiado grande', 'El archivo debe pesar hasta 5 MB.');
                            return false;
                        }
                    }
                }
                return true;
            };

            document.querySelectorAll('[data-next]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const next = parseInt(btn.dataset.next, 10);
                    if (Number.isNaN(next)) {
                        return;
                    }
                    if (currentStep === 2 && !validateStep(2)) {
                        return;
                    }
                    goToStep(next);
                });
            });

            document.querySelectorAll('[data-prev]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const prev = parseInt(btn.dataset.prev, 10);
                    if (Number.isNaN(prev)) {
                        return;
                    }
                    goToStep(prev);
                });
            });

            const mpRadio = document.getElementById('metodo_mp');
            const transferRadio = document.getElementById('metodo_transfer');
            const transferDetails = document.getElementById('transferDetails');
            const mpDetails = document.getElementById('mpDetails');
            const form = document.getElementById('checkoutForm');
            const confirmButton = document.getElementById('btnConfirmar');
            let confirmLabel = confirmButton.querySelector('.btn-label');
            let confirmIcon = confirmButton.querySelector('i');
            const confirmDefault = {
                label: 'Confirmar inscripción',
                icon: 'fas fa-paper-plane ms-2'
            };
            const confirmDefaultMarkup = confirmButton.innerHTML;

            const refreshConfirmElements = () => {
                confirmLabel = confirmButton.querySelector('.btn-label');
                confirmIcon = confirmButton.querySelector('i');
            };

            const updateConfirmButton = () => {
                refreshConfirmElements();
                if (!confirmLabel || !confirmIcon) {
                    return;
                }
                if (mpRadio.checked) {
                    confirmLabel.textContent = 'Ir a Mercado Pago';
                    confirmIcon.className = 'fas fa-credit-card ms-2';
                } else {
                    confirmLabel.textContent = confirmDefault.label;
                    confirmIcon.className = confirmDefault.icon;
                }
            };

            const togglePaymentDetails = () => {
                if (transferRadio.checked) {
                    transferDetails.classList.remove('hidden');
                    mpDetails.classList.add('hidden');
                } else if (mpRadio.checked) {
                    mpDetails.classList.remove('hidden');
                    transferDetails.classList.add('hidden');
                }
                updateConfirmButton();
            };

            mpRadio.addEventListener('change', togglePaymentDetails);
            transferRadio.addEventListener('change', togglePaymentDetails);
            togglePaymentDetails();

            const setConfirmLoading = (isLoading) => {
                if (isLoading) {
                    confirmButton.disabled = true;
                    confirmButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Redirigiendo a Mercado Pago...';
                } else {
                    confirmButton.disabled = false;
                    confirmButton.innerHTML = confirmDefaultMarkup;
                    updateConfirmButton();
                }
            };

            const iniciarMercadoPago = async () => {
                if (mpProcessing) {
                    return;
                }
                mpProcessing = true;
                setConfirmLoading(true);
                try {
                    const formData = new FormData(form);
                    formData.set('metodo_pago', 'mercado_pago');
                    formData.set('__accion', 'crear_orden');
                    const response = await fetch(mpEndpoint, {
                        method: 'POST',
                        body: formData,
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || !data || !data.success || !data.init_point) {
                        const message = data && data.message ? data.message : 'No se pudo iniciar el pago en Mercado Pago.';
                        throw new Error(message);
                    }
                    window.location.href = data.init_point;
                } catch (error) {
                    mpProcessing = false;
                    setConfirmLoading(false);
                    showAlert('error', 'No se pudo iniciar el pago', error && error.message ? error.message : 'Intentá nuevamente en unos minutos.');
                }
            };

            confirmButton.addEventListener('click', () => {
                if (!validateStep(2) || !validateStep(3)) {
                    return;
                }
                const mpSelected = mpRadio.checked;
                const title = mpSelected ? 'Ir a Mercado Pago' : 'Confirmar inscripción';
                const text = mpSelected
                    ? 'Vamos a generar tu orden y redirigirte a Mercado Pago para que completes el pago.'
                    : '¿Deseás enviar la inscripción con los datos cargados?';
                const confirmText = mpSelected ? 'Sí, continuar' : 'Sí, enviar';

                Swal.fire({
                    icon: 'question',
                    title,
                    text,
                    showCancelButton: true,
                    confirmButtonText: confirmText,
                    cancelButtonText: 'Cancelar',
                    customClass: {
                        confirmButton: 'btn btn-gradient btn-rounded me-2',
                        cancelButton: 'btn btn-outline-light btn-rounded'
                    },
                    buttonsStyling: false,
                    reverseButtons: true
                }).then(result => {
                    if (!result.isConfirmed) {
                        return;
                    }
                    if (mpSelected) {
                        iniciarMercadoPago();
                    } else {
                        document.getElementById('__accion').value = 'crear_orden';
                        form.submit();
                    }
                });
            });
        })();
    </script>
</body>
</html>