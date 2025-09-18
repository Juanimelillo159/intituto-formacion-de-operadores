<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo isset($page_title) ? $page_title : "Instituto de Formación"; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : "Formación profesional en capacitaciones y certificaciones."; ?>">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Tus estilos -->
    <link rel="stylesheet" href="assets/styles/style.css">
    <?= isset($page_styles) ? $page_styles : '' ?>

    <!-- Iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Condicional: solo carga si se pide -->
    <?php if (!empty($include_google_auth)): ?>
        <script>
            window.googleClientId = '<?php echo htmlspecialchars($googleClientId, ENT_QUOTES, 'UTF-8'); ?>';
        </script>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>

    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/iconos/icono.png" type="image/png">
</head>
