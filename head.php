<?php $asset_base_path = $asset_base_path ?? ''; ?>
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo isset($page_title) ? $page_title : "Instituto de Formación"; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : "Formación profesional en capacitaciones y certificaciones."; ?>">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo $asset_base_path; ?>assets/styles/style.css">
    <?= isset($page_styles) ? $page_styles : '' ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link rel="shortcut icon" href="<?php echo $asset_base_path; ?>assets/iconos/icono.png" type="image/png">


</head>
