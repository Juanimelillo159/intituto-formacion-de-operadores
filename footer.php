<?php
if (!isset($base_path)) {
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

    $base_path = $relativeProjectPath === '' ? '' : '/' . $relativeProjectPath;
}

$normalized_base = rtrim($base_path, '/');
?>
<footer class="bg-black text-white py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <img src="<?php echo $normalized_base; ?>/logos/LOGO PNG-07.png" alt="Logo Instituto de Formación de Operadores" class="footer-logo mb-3">
                <p>&copy; 2023 Instituto de Formación de Operadores. Todos los derechos reservados.</p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h5 class="mb-3">Contacto</h5>
                <!-- <p><i class="fas fa-map-marker-alt me-2"></i>Sarmiento 1385, Comodoro Rivadavia, Argentina</p> -->
                <p><i class="fas fa-phone me-2"></i>297-5305505</p>
                <p><i class="fas fa-envelope me-2"></i>bbs.oil.mining@gmail.com</p>
            </div>
            <div class="col-md-4">
                <h5 class="mb-3">Síguenos</h5>
                <div class="social-icons">
                    <a href="#" target="_blank" aria-label="Facebook"><i class="fab fa-facebook"></i></a>
                    <a href="#" target="_blank" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                    <a href="#" target="_blank" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" target="_blank" aria-label="LinkedIn"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
        </div>
    </div>
</footer>
