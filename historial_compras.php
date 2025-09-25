<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$userId = (int)($_SESSION['id_usuario'] ?? $_SESSION['usuario'] ?? 0);

if ($userId <= 0) {
    header('Location: login.php');
    exit;
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = $page > 0 ? $page : 1;
$perPage = 5;

$page_title = 'Historial de compras | Instituto de Formacion';
$page_description = 'Compras realizadas con tu cuenta del Instituto.';
$page_styles = '<link rel="stylesheet" href="assets/styles/style_configuracion.css">';

$formatMoney = static function (float $amount, ?string $currency = null): string {
    $formatted = number_format($amount, 2, ',', '.');
    $currencyCode = trim((string)$currency);

    return $currencyCode !== '' ? $currencyCode . ' ' . $formatted : $formatted;
};

$compras = [];
$errorMessage = null;

try {
    $pdo = getPdo();
    $stmt = $pdo->prepare(
        'SELECT
            c.id_compra,
            c.pagado_en,
            c.total,
            c.moneda,
            c.metodo_pago,
            c.referencia_externa,
            ci.id_item,
            ci.cantidad,
            ci.precio_unitario,
            ci.titulo_snapshot,
            cursos.nombre_curso,
            modalidades.nombre_modalidad
        FROM compras c
        INNER JOIN compra_items ci ON ci.id_compra = c.id_compra
        INNER JOIN cursos ON cursos.id_curso = ci.id_curso
        LEFT JOIN modalidades ON modalidades.id_modalidad = ci.id_modalidad
        WHERE c.id_usuario = :usuario
          AND c.estado = :estado
        ORDER BY c.pagado_en DESC, c.id_compra DESC, ci.id_item ASC'
    );
    $stmt->bindValue(':usuario', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':estado', 'pagada', PDO::PARAM_STR);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $compraId = (int)$row['id_compra'];

        if (!isset($compras[$compraId])) {
            $formattedDate = null;
            if (!empty($row['pagado_en'])) {
                try {
                    $formattedDate = (new DateTimeImmutable($row['pagado_en']))->format('d/m/Y H:i');
                } catch (Throwable $exception) {
                    $formattedDate = $row['pagado_en'];
                }
            }

            $compras[$compraId] = [
                'id_compra' => $compraId,
                'pagado_en' => $row['pagado_en'],
                'pagado_en_formatted' => $formattedDate,
                'total' => (float)$row['total'],
                'moneda' => $row['moneda'],
                'metodo_pago' => $row['metodo_pago'],
                'referencia_externa' => $row['referencia_externa'],
                'items' => [],
            ];
        }

        $cantidad = (int)$row['cantidad'];
        $precioUnitario = (float)$row['precio_unitario'];
        $subtotal = $cantidad * $precioUnitario;

        $compras[$compraId]['items'][] = [
            'id_item' => (int)$row['id_item'],
            'nombre_curso' => $row['nombre_curso'] ?: $row['titulo_snapshot'],
            'nombre_modalidad' => $row['nombre_modalidad'],
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'subtotal' => $subtotal,
        ];
    }

    $compras = array_values($compras);
} catch (Throwable $exception) {
    $errorMessage = 'No pudimos cargar tus compras en este momento.';
}

$totalCompras = count($compras);
$totalPages = (int)ceil($totalCompras / $perPage);
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$paginatedCompras = $totalCompras > 0 ? array_slice($compras, $offset, $perPage) : [];
$scriptName = basename((string)($_SERVER['PHP_SELF'] ?? 'historial_compras.php'));
?>
<!DOCTYPE html>
<html lang="es">
<?php include 'head.php'; ?>
<body class="config-page d-flex flex-column min-vh-100">
<?php include 'nav.php'; ?>

<header class="config-hero">
    <div class="container">
        <a href="index.php" class="config-back"><i class="fas fa-arrow-left me-2"></i>Volver al inicio</a>
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <div class="config-hero-card shadow-lg w-100 text-center">
                    <h1>Historial de compras</h1>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="config-main flex-grow-1 py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-8">
                <?php if ($errorMessage !== null): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-0"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php elseif (empty($compras)): ?>
                    <div class="config-card shadow text-center">
                        <p class="mb-4">No ten&eacute;s compras pagadas a&uacute;n.</p>
                        <a class="btn btn-gradient" href="index.php#cursos">Explorar cursos disponibles</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($paginatedCompras as $compra): ?>
                        <div class="config-card shadow mb-4 text-start">
                            <div class="row gy-3 align-items-center border-bottom pb-3 mb-3">
                                <div class="col-md-4">
                                    <div class="text-uppercase text-muted small">Pagado el</div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($compra['pagado_en_formatted'] ?? 'Sin datos', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-uppercase text-muted small">Total</div>
                                    <?php $totalLabel = $formatMoney($compra['total'], $compra['moneda']); ?>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($totalLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="text-uppercase text-muted small">M&eacute;todo de pago</div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($compra['metodo_pago'] !== null && $compra['metodo_pago'] !== '' ? $compra['metodo_pago'] : 'Sin datos', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                                <div class="col-12">
                                    <div class="text-uppercase text-muted small">Referencia externa</div>
                                    <div><?php echo htmlspecialchars($compra['referencia_externa'] !== null && $compra['referencia_externa'] !== '' ? $compra['referencia_externa'] : 'Sin datos', ENT_QUOTES, 'UTF-8'); ?></div>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col" class="text-start">Curso</th>
                                            <th scope="col" class="text-start">Modalidad</th>
                                            <th scope="col" class="text-center">Cantidad</th>
                                            <th scope="col" class="text-end">Precio unitario</th>
                                            <th scope="col" class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($compra['items'] as $item): ?>
                                            <?php
                                            $unitLabel = $formatMoney($item['precio_unitario'], $compra['moneda']);
                                            $subtotalLabel = $formatMoney($item['subtotal'], $compra['moneda']);
                                            ?>
                                            <tr>
                                                <td class="text-start"><?php echo htmlspecialchars($item['nombre_curso'] ?? 'Curso', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-start"><?php echo htmlspecialchars($item['nombre_modalidad'] ?? 'Sin modalidad', ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-center"><?php echo (int)$item['cantidad']; ?></td>
                                                <td class="text-end"><?php echo htmlspecialchars($unitLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td class="text-end"><?php echo htmlspecialchars($subtotalLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Paginaci&oacute;n del historial" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php
                                $params = $_GET;
                                $prevDisabled = $page <= 1;
                                $prevParams = $params;
                                $prevParams['page'] = max(1, $page - 1);
                                $prevUrl = $scriptName . '?' . http_build_query($prevParams);
                                ?>
                                <li class="page-item<?php echo $prevDisabled ? ' disabled' : ''; ?>">
                                    <?php if ($prevDisabled): ?>
                                        <span class="page-link">Anterior</span>
                                    <?php else: ?>
                                        <a class="page-link" href="<?php echo htmlspecialchars($prevUrl, ENT_QUOTES, 'UTF-8'); ?>">Anterior</a>
                                    <?php endif; ?>
                                </li>

                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php
                                    $pageParams = $params;
                                    $pageParams['page'] = $i;
                                    $pageUrl = $scriptName . '?' . http_build_query($pageParams);
                                    ?>
                                    <li class="page-item<?php echo $i === $page ? ' active' : ''; ?>">
                                        <a class="page-link" href="<?php echo htmlspecialchars($pageUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php
                                $nextDisabled = $page >= $totalPages;
                                $nextParams = $params;
                                $nextParams['page'] = min($totalPages, $page + 1);
                                $nextUrl = $scriptName . '?' . http_build_query($nextParams);
                                ?>
                                <li class="page-item<?php echo $nextDisabled ? ' disabled' : ''; ?>">
                                    <?php if ($nextDisabled): ?>
                                        <span class="page-link">Siguiente</span>
                                    <?php else: ?>
                                        <a class="page-link" href="<?php echo htmlspecialchars($nextUrl, ENT_QUOTES, 'UTF-8'); ?>">Siguiente</a>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
