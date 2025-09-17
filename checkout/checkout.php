<?php
// finalizar_compra.php
include '../sbd.php';
include '../admin/header.php';
include '../admin/aside.php';
include '../admin/footer.php';

$page_title = "Checkout | Instituto de Formación";
$page_description = "checkout - página de capacitación del Instituto de Formación de Operadores";


$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;

// Curso
$st = $con->prepare("SELECT * FROM cursos WHERE id_curso = :id");
$st->execute([':id'=>$id_curso]);
$curso = $st->fetch(PDO::FETCH_ASSOC);
if(!$curso){
  echo "<div class='content-wrapper'><section class='content'><div class='container-fluid'><div class='alert alert-danger'>Curso no encontrado.</div></div></section></div>";
  exit;
}

// Precio vigente (ARS)
$pv = $con->prepare("
  SELECT precio, moneda, vigente_desde
  FROM curso_precio_hist
  WHERE id_curso = :c
    AND vigente_desde <= NOW()
    AND (vigente_hasta IS NULL OR vigente_hasta > NOW())
  ORDER BY vigente_desde DESC
  LIMIT 1
");
$pv->execute([':c'=>$id_curso]);
$precio_vigente = $pv->fetch(PDO::FETCH_ASSOC);

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$flash_success = $_SESSION['checkout_success'] ?? null;
$flash_error   = $_SESSION['checkout_error'] ?? null;
unset($_SESSION['checkout_success'], $_SESSION['checkout_error']);
?>
<!DOCTYPE html>
<html lang="es">


<?php $page_styles = '<link rel="stylesheet" href="css/style.css">'; ?>
<?php include("head.php"); ?>

<body class="hold-transition sidebar-mini">
  <div class="wrapper">
    <div class="content-wrapper">

      <section class="content-header">
        <div class="container-fluid">
          <div class="row mb-2">
            <div class="col-sm-6"><h1><i class="fas fa-shopping-cart"></i> Finalizar compra</h1></div>
            <div class="col-sm-6">
              <ol class="breadcrumb float-sm-right">
                <li class="breadcrumb-item"><a href="../admin/dashboard.php"><i class="fas fa-home"></i> Inicio</a></li>
                <li class="breadcrumb-item"><a href="cursos.php"><i class="fas fa-list"></i> Cursos</a></li>
                <li class="breadcrumb-item active"><i class="fas fa-cash-register"></i> Checkout</li>
              </ol>
            </div>
          </div>
        </div>
      </section>

      <section class="content">
        <div class="container-fluid">

          <div class="card card-outline card-primary">
            <div class="card-header card-header-gradient">
              <h3 class="card-title mb-0"><i class="fas fa-book"></i> <?php echo h($curso['nombre_curso']); ?></h3>
            </div>

            <!-- UN SOLO FORM -->
            <form id="checkoutForm" action="../admin/procesarsbd.php" method="POST" enctype="multipart/form-data" novalidate>
              <input type="hidden" name="__accion" id="__accion" value="">
              <input type="hidden" name="crear_orden" value="1">
              <input type="hidden" name="id_curso" value="<?php echo (int)$id_curso; ?>">

              <div class="card-body p-0">
                <?php if ($flash_success): ?>
                  <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                    <i class="fas fa-check-circle mr-1"></i>
                    <strong>¡Inscripción enviada!</strong>
                    <?php if (!empty($flash_success['orden'])): ?>
                      Número de orden: #<?php echo str_pad((string)(int)$flash_success['orden'], 6, '0', STR_PAD_LEFT); ?>.
                    <?php endif; ?>
                    <br>
                    Revisaremos tu solicitud y nos pondremos en contacto por correo.
                    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                <?php endif; ?>
                <?php if ($flash_error): ?>
                  <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <strong>No pudimos procesar tu inscripción.</strong>
                    <br>
                    <?php echo h($flash_error); ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                <?php endif; ?>
                <ul class="nav nav-tabs px-3 pt-3" role="tablist">
                  <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#paso1" role="tab">
                      <i class="fas fa-receipt"></i> 1) Resumen
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#paso2" role="tab">
                      <i class="fas fa-id-card"></i> 2) Inscripción
                    </a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#paso3" role="tab">
                      <i class="fas fa-credit-card"></i> 3) Pago
                    </a>
                  </li>
                </ul>

                <div class="tab-content p-3">
                  <!-- PASO 1 -->
                  <div class="tab-pane fade show active" id="paso1" role="tabpanel">
                    <div class="row">
                      <div class="col-md-7">
                        <h5>Resumen del curso</h5>
                        <p class="mb-2"><strong>Nombre:</strong> <?php echo h($curso['nombre_curso']); ?></p>
                        <p class="mb-2"><strong>Duración:</strong> <?php echo h($curso['duracion']); ?></p>
                        <p class="mb-2"><strong>Complejidad:</strong> <?php echo h($curso['complejidad']); ?></p>
                        <p><strong>Descripción:</strong><br><?php echo nl2br(h($curso['descripcion_curso'])); ?></p>
                      </div>
                      <div class="col-md-5">
                        <div class="alert alert-info">
                          <h5 class="mb-1"><i class="fas fa-tag"></i> Precio</h5>
                          <?php if ($precio_vigente): ?>
                            <div class="display-4" style="font-size:2rem;">
                              ARS <?php echo number_format((float)$precio_vigente['precio'], 2, ',', '.'); ?>
                            </div>
                            <small>Vigente desde: <?php echo date('d/m/Y H:i', strtotime($precio_vigente['vigente_desde'])); ?></small>
                          <?php else: ?>
                            <div class="text-muted">No hay precio vigente configurado.</div>
                          <?php endif; ?>
                          <input type="hidden" name="precio_checkout" value="<?php echo $precio_vigente ? (float)$precio_vigente['precio'] : 0; ?>">
                        </div>
                      </div>
                    </div>
                    <div class="d-flex justify-content-end">
                      <button type="button" class="btn btn-primary" id="btnToPaso2">Continuar <i class="fas fa-arrow-right ml-1"></i></button>
                    </div>
                  </div>

                  <!-- PASO 2 -->
                  <div class="tab-pane fade" id="paso2" role="tabpanel">
                    <h5 class="mb-3">Datos del inscripto</h5>
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="nombre" class="required-field">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre_insc" placeholder="Nombre">
                      </div>
                      <div class="form-group col-md-6">
                        <label for="apellido" class="required-field">Apellido</label>
                        <input type="text" class="form-control" id="apellido" name="apellido_insc" placeholder="Apellido">
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group col-md-6">
                        <label for="email" class="required-field">Email</label>
                        <input type="email" class="form-control" id="email" name="email_insc" placeholder="correo@dominio.com">
                      </div>
                      <div class="form-group col-md-6">
                        <label for="telefono" class="required-field">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="tel_insc" placeholder="+54 11 5555-5555">
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group col-md-4">
                        <label for="dni">DNI</label>
                        <input type="text" class="form-control" id="dni" name="dni_insc" placeholder="Documento">
                      </div>
                      <div class="form-group col-md-8">
                        <label for="direccion">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="dir_insc" placeholder="Calle y número">
                      </div>
                    </div>
                    <div class="form-row">
                      <div class="form-group col-md-4">
                        <label for="ciudad">Ciudad</label>
                        <input type="text" class="form-control" id="ciudad" name="ciu_insc">
                      </div>
                      <div class="form-group col-md-4">
                        <label for="provincia">Provincia</label>
                        <input type="text" class="form-control" id="provincia" name="prov_insc">
                      </div>
                      <div class="form-group col-md-4">
                        <label for="pais">País</label>
                        <input type="text" class="form-control" id="pais" name="pais_insc" value="Argentina">
                      </div>
                    </div>

                    <div class="form-group form-check">
                      <input type="checkbox" class="form-check-input" id="acepta" name="acepta_tyc" value="1">
                      <label class="form-check-label required-field" for="acepta">
                        Acepto los Términos y Condiciones del sitio
                      </label>
                    </div>

                    <div class="d-flex justify-content-between">
                      <button type="button" class="btn btn-secondary" id="btnBackPaso1"><i class="fas fa-arrow-left mr-1"></i> Volver</button>
                      <button type="button" class="btn btn-primary" id="btnToPaso3">Continuar <i class="fas fa-arrow-right ml-1"></i></button>
                    </div>
                  </div>

                  <!-- PASO 3 -->
                  <div class="tab-pane fade" id="paso3" role="tabpanel">
                    <h5 class="mb-3">Pago</h5>

                    <div class="form-group">
                      <div class="custom-control custom-radio">
                        <input type="radio" id="mp" name="metodo_pago" class="custom-control-input" value="mercado_pago">
                        <label class="custom-control-label" for="mp">
                          Mercado Pago (próximamente) — mostrará botón de MP
                        </label>
                      </div>
                      <div class="custom-control custom-radio">
                        <input type="radio" id="transf" name="metodo_pago" class="custom-control-input" value="transferencia" checked>
                        <label class="custom-control-label" for="transf">
                          Transferencia bancaria (subí el comprobante)
                        </label>
                      </div>
                    </div>

                    <div id="box-transferencia" class="border rounded p-3 mb-3">
                      <p class="mb-2"><strong>Datos bancarios</strong></p>
                      <ul class="mb-3">
                        <li>Banco: Tu Banco</li>
                        <li>CBU: 0000000000000000000000</li>
                        <li>Alias: tuempresa.cursos</li>
                      </ul>
                      <div class="form-row">
                        <div class="form-group col-md-8">
                          <label for="comprobante" class="required-field">Archivo de comprobante (JPG/PNG/PDF, máx 5MB)</label>
                          <input type="file" class="form-control-file" id="comprobante" name="comprobante" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <div class="form-group col-md-4">
                          <label for="obs_pago">Observaciones</label>
                          <input type="text" class="form-control" id="obs_pago" name="obs_pago" placeholder="Opcional">
                        </div>
                      </div>
                    </div>

                    <div id="box-mp" class="border rounded p-3 mb-3" style="display:none;">
                      <p class="mb-2"><strong>Mercado Pago</strong></p>
                      <p class="text-muted">Aquí irá el botón/checkout de MP (aún no implementado).</p>
                    </div>

                    <div class="d-flex justify-content-between">
                      <button type="button" class="btn btn-secondary" id="btnBackPaso2"><i class="fas fa-arrow-left mr-1"></i> Volver</button>
                      <button type="button" class="btn btn-success" id="btnConfirmar">
                        <i class="fas fa-check"></i> Confirmar y enviar
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>

        </div>
      </section>
    </div>
  </div>

<script>
  // Helpers SweetAlert con look Bootstrap
  function swalConfirm({title, text, icon='question', confirmText='Sí', cancelText='Cancelar'}){
    return Swal.fire({
      title, text, icon, showCancelButton:true, confirmButtonText:confirmText, cancelButtonText:cancelText,
      reverseButtons:true, buttonsStyling:false,
      customClass:{ confirmButton:'btn btn-primary mx-1', cancelButton:'btn btn-outline-secondary mx-1' }
    });
  }
  function showTab(sel){ $('a[href="'+sel+'"]').tab('show'); }

  function validarPaso2(){
    const req = [
      {id:'nombre', label:'Nombre'},
      {id:'apellido', label:'Apellido'},
      {id:'email', label:'Email'},
      {id:'telefono', label:'Teléfono'}
    ];
    for(const r of req){
      const el = document.getElementById(r.id);
      if(!el || !el.value || el.value.trim()===''){
        showTab('#paso2');
        el && el.focus();
        Swal.fire({icon:'error', title:'Faltan datos', html:'<div style="text-align:left;">• '+r.label+'</div>'});
        return false;
      }
    }
    const email = document.getElementById('email').value.trim();
    const okMail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    if(!okMail){
      showTab('#paso2');
      document.getElementById('email').focus();
      Swal.fire({icon:'error', title:'Email inválido', text:'Ingresá un correo válido.'});
      return false;
    }
    if(!document.getElementById('acepta').checked){
      showTab('#paso2');
      Swal.fire({icon:'warning', title:'Términos y Condiciones', text:'Debés aceptar los T&C para continuar.'});
      return false;
    }
    return true;
  }

  function validarPaso3(){
    const mp = document.getElementById('mp').checked;
    const tf = document.getElementById('transf').checked;
    if(!mp && !tf){
      showTab('#paso3');
      Swal.fire({icon:'error', title:'Seleccioná un método de pago'});
      return false;
    }
    if(tf){
      const f = document.getElementById('comprobante').files[0];
      if(!f){
        showTab('#paso3');
        Swal.fire({icon:'error', title:'Falta comprobante', text:'Adjuntá el archivo de la transferencia.'});
        return false;
      }
      const okType = ['image/jpeg','image/png','application/pdf'].includes(f.type);
      if(!okType){ Swal.fire({icon:'error', title:'Tipo no permitido', text:'JPG, PNG o PDF.'}); return false; }
      const max5 = 5 * 1024 * 1024;
      if(f.size > max5){ Swal.fire({icon:'error', title:'Archivo muy grande', text:'Máximo 5 MB.'}); return false; }
    }
    return true;
  }

  // Navegación
  document.getElementById('btnToPaso2').addEventListener('click',()=>showTab('#paso2'));
  document.getElementById('btnBackPaso1').addEventListener('click',()=>showTab('#paso1'));
  document.getElementById('btnToPaso3').addEventListener('click',()=>{
    if(validarPaso2()) showTab('#paso3');
  });
  document.getElementById('btnBackPaso2').addEventListener('click',()=>showTab('#paso2'));

  // Toggle boxes de pago
  document.getElementById('mp').addEventListener('change', function(){
    document.getElementById('box-mp').style.display = this.checked ? 'block' : 'none';
    document.getElementById('box-transferencia').style.display = this.checked ? 'none' : 'block';
  });
  document.getElementById('transf').addEventListener('change', function(){
    document.getElementById('box-transferencia').style.display = this.checked ? 'block' : 'none';
    document.getElementById('box-mp').style.display = this.checked ? 'none' : 'block';
  });

  // Confirmar
  document.getElementById('btnConfirmar').addEventListener('click', async function(){
    if(!validarPaso2() || !validarPaso3()) return;
    const res = await swalConfirm({title:'Confirmar compra', text:'¿Deseás enviar la inscripción y el pago?', confirmText:'Sí, confirmar'});
    if(!res.isConfirmed) return;
    document.getElementById('__accion').value = 'crear_orden';
    document.getElementById('checkoutForm').submit();
  });
</script>
</body>
</html>
