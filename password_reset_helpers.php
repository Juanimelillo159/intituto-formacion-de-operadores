<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Crea la tabla de recuperaciones si no existe.
 */
function ensurePasswordResetTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS recuperaciones_contrasena (\r\n" .
        "    id_reset INT AUTO_INCREMENT PRIMARY KEY,\r\n" .
        "    id_usuario INT NOT NULL,\r\n" .
        "    token VARCHAR(128) NOT NULL,\r\n" .
        "    expiracion DATETIME NOT NULL,\r\n" .
        "    utilizado TINYINT(1) NOT NULL DEFAULT 0,\r\n" .
        "    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\r\n" .
        "    usado_en DATETIME NULL,\r\n" .
        "    UNIQUE KEY uq_token_recuperacion (token),\r\n" .
        "    KEY idx_usuario_recuperacion (id_usuario),\r\n" .
        "    CONSTRAINT fk_recuperacion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios (id_usuario) ON DELETE CASCADE\r\n" .
        ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    );
}

/**
 * Elimina tokens previos para un usuario.
 */
function deleteExistingPasswordResetTokens(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare('DELETE FROM recuperaciones_contrasena WHERE id_usuario = ?');
    $stmt->execute([$userId]);
}

/**
 * Limpia tokens vencidos.
 */
function purgeExpiredPasswordResets(PDO $pdo): void
{
    $stmt = $pdo->prepare('DELETE FROM recuperaciones_contrasena WHERE expiracion <= NOW() OR utilizado = 1 AND (usado_en IS NOT NULL AND usado_en <= DATE_SUB(NOW(), INTERVAL 7 DAY))');
    $stmt->execute();
}
