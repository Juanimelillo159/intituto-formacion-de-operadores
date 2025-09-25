<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configActive = $configActive ?? '';
$currentPermisoSidebar = (int)($_SESSION['permiso'] ?? 0);

$sidebarItems = [
    [
        'id' => 'mis_cursos',
        'label' => 'Mis cursos',
        'icon' => 'fa-graduation-cap',
        'href' => 'mis_cursos.php',
    ],
    [
        'id' => 'historial_compras',
        'label' => 'Mis compras',
        'icon' => 'fa-receipt',
        'href' => 'historial_compras.php',
    ],
    [
        'id' => 'configuracion',
        'label' => 'Configuracion',
        'icon' => 'fa-gear',
        'href' => 'configuracion.php',
    ],
];

if ($currentPermisoSidebar === 3) {
    $sidebarItems[] = [
        'id' => 'trabajadores',
        'label' => 'Trabajadores',
        'icon' => 'fa-users',
        'href' => 'trabajadores.php',
    ];
    $sidebarItems[] = [
        'id' => 'inscripciones',
        'label' => 'Inscripciones',
        'icon' => 'fa-clipboard-list',
        'href' => 'inscripciones.php',
    ];
}
?>
<div class="config-sidebar card shadow-sm">
    <div class="card-body p-0">
        <nav class="list-group list-group-flush">
            <?php foreach ($sidebarItems as $item): ?>
                <?php
                $isCurrent = $item['id'] === $configActive;
                $classes = 'list-group-item list-group-item-action d-flex align-items-center gap-2';
                if ($isCurrent) {
                    $classes .= ' active';
                }
                ?>
                <a class="<?php echo $classes; ?>" href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8'); ?>"
                   <?php if ($isCurrent): ?>aria-current="page"<?php endif; ?>>
                    <i class="fa-solid <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                    <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

