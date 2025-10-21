<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (!isset($asset_base_path)) {
  $documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
    : '';

  $currentDir = str_replace('\\', '/', realpath(__DIR__));

  if ($documentRoot !== '' && strpos($currentDir, $documentRoot) === 0) {
    $relativeProjectPath = trim(substr($currentDir, strlen($documentRoot)), '/');
  } else {
    $relativeProjectPath = isset($_SERVER['SCRIPT_NAME'])
      ? trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/')
      : '';
  }

  $calculatedBase = $relativeProjectPath === '' ? '' : '/' . $relativeProjectPath;
  $asset_base_path = $calculatedBase;
}

$normalized_asset_base = rtrim($asset_base_path, '/');
?>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title><?php echo isset($page_title) ? $page_title : "Instituto de Formación"; ?></title>
  <meta name="description" content="<?php echo isset($page_description) ? $page_description : "Formación profesional en capacitaciones y certificaciones."; ?>">

  <!-- Bootstrap -->
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" crossorigin="anonymous">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" referrerpolicy="no-referrer" />

  <link rel="stylesheet" href="<?php echo $normalized_asset_base; ?>/assets/styles/style.css">

  <?= isset($page_styles) ? $page_styles : '' ?>

  <!-- Google Identity Services (solo si se pide) -->
  <?php if (!empty($include_google_auth)): ?>
    <script>
      window.googleClientId = '<?php echo htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php endif; ?>


  <!-- Condicional: solo carga si se pide -->
  <?php if (!empty($include_google_auth)): ?>
    <script>
      window.googleClientId = '<?php echo htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8'); ?>';
    </script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
  <?php endif; ?>

  <!-- Favicon -->
  <link rel="shortcut icon" href="<?php echo $normalized_asset_base; ?>/assets/iconos/icono.png" type="image/png">

</head>



