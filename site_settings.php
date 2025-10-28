<?php
declare(strict_types=1);

/**
 * Gestión centralizada de la configuración editable desde el panel.
 */

const SITE_SETTINGS_ALLOWED_MODES = ['normal', 'construction', 'support'];

function site_settings_defaults(): array
{
    return [
        'mercado_pago_habilitado' => true,
        'site_mode' => 'normal',
        'capacitaciones_habilitadas' => true,
        'certificaciones_habilitadas' => true,
        'cursos_deshabilitados' => [],
        'certificaciones_deshabilitadas' => [],
        'site_notice' => '',
    ];
}

function site_settings_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS site_settings (
            id INT PRIMARY KEY,
            mercado_pago_habilitado TINYINT(1) NOT NULL DEFAULT 1,
            site_mode VARCHAR(20) NOT NULL DEFAULT 'normal',
            capacitaciones_habilitadas TINYINT(1) NOT NULL DEFAULT 1,
            certificaciones_habilitadas TINYINT(1) NOT NULL DEFAULT 1,
            cursos_deshabilitados TEXT NULL,
            certificaciones_deshabilitadas TEXT NULL,
            site_notice TEXT NULL,
            actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $stmt = $pdo->query('SELECT COUNT(*) FROM site_settings');
    $count = (int)($stmt->fetchColumn() ?: 0);
    if ($count === 0) {
        $defaults = site_settings_defaults();
        $insert = $pdo->prepare(
            'INSERT INTO site_settings (
                id,
                mercado_pago_habilitado,
                site_mode,
                capacitaciones_habilitadas,
                certificaciones_habilitadas,
                cursos_deshabilitados,
                certificaciones_deshabilitadas,
                site_notice
            ) VALUES (1, :mp, :mode, :cap, :cert, :cursos, :certs, :notice)'
        );
        $insert->execute([
            ':mp' => $defaults['mercado_pago_habilitado'] ? 1 : 0,
            ':mode' => $defaults['site_mode'],
            ':cap' => $defaults['capacitaciones_habilitadas'] ? 1 : 0,
            ':cert' => $defaults['certificaciones_habilitadas'] ? 1 : 0,
            ':cursos' => json_encode($defaults['cursos_deshabilitados']),
            ':certs' => json_encode($defaults['certificaciones_deshabilitadas']),
            ':notice' => $defaults['site_notice'],
        ]);
    }
}

function site_settings_normalize_ids(array $ids): array
{
    $normalized = [];
    foreach ($ids as $value) {
        if (is_numeric($value)) {
            $int = (int)$value;
            if ($int > 0) {
                $normalized[$int] = $int;
            }
        }
    }

    return array_values($normalized);
}

function site_settings_decode_ids(?string $raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return site_settings_normalize_ids($decoded);
    }

    $parts = array_filter(array_map('trim', explode(',', $raw)), static fn($v) => $v !== '');
    return site_settings_normalize_ids($parts);
}

function get_site_settings(PDO $pdo): array
{
    site_settings_ensure_schema($pdo);

    $stmt = $pdo->prepare('SELECT * FROM site_settings WHERE id = 1 LIMIT 1');
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $defaults = site_settings_defaults();

    if (!$row) {
        return $defaults;
    }

    $settings = [
        'mercado_pago_habilitado' => isset($row['mercado_pago_habilitado']) ? ((int)$row['mercado_pago_habilitado'] === 1) : $defaults['mercado_pago_habilitado'],
        'site_mode' => in_array(($row['site_mode'] ?? ''), SITE_SETTINGS_ALLOWED_MODES, true) ? strtolower((string)$row['site_mode']) : $defaults['site_mode'],
        'capacitaciones_habilitadas' => isset($row['capacitaciones_habilitadas']) ? ((int)$row['capacitaciones_habilitadas'] === 1) : $defaults['capacitaciones_habilitadas'],
        'certificaciones_habilitadas' => isset($row['certificaciones_habilitadas']) ? ((int)$row['certificaciones_habilitadas'] === 1) : $defaults['certificaciones_habilitadas'],
        'cursos_deshabilitados' => site_settings_decode_ids($row['cursos_deshabilitados'] ?? null),
        'certificaciones_deshabilitadas' => site_settings_decode_ids($row['certificaciones_deshabilitadas'] ?? null),
        'site_notice' => trim((string)($row['site_notice'] ?? '')),
    ];

    return array_merge($defaults, $settings);
}

function update_site_settings(PDO $pdo, array $data): void
{
    site_settings_ensure_schema($pdo);

    $mode = strtolower((string)($data['site_mode'] ?? 'normal'));
    if (!in_array($mode, SITE_SETTINGS_ALLOWED_MODES, true)) {
        $mode = 'normal';
    }

    $mpEnabled = !empty($data['mercado_pago_habilitado']);
    $capacitacionesEnabled = !empty($data['capacitaciones_habilitadas']);
    $certificacionesEnabled = !empty($data['certificaciones_habilitadas']);

    $disabledCursos = isset($data['cursos_deshabilitados']) && is_array($data['cursos_deshabilitados'])
        ? site_settings_normalize_ids($data['cursos_deshabilitados'])
        : [];
    $disabledCertificaciones = isset($data['certificaciones_deshabilitadas']) && is_array($data['certificaciones_deshabilitadas'])
        ? site_settings_normalize_ids($data['certificaciones_deshabilitadas'])
        : [];

    $notice = trim((string)($data['site_notice'] ?? ''));
    if (function_exists('mb_substr')) {
        $notice = mb_substr($notice, 0, 1000, 'UTF-8');
    } else {
        $notice = substr($notice, 0, 1000);
    }

    $stmt = $pdo->prepare(
        'UPDATE site_settings
            SET mercado_pago_habilitado = :mp,
                site_mode = :mode,
                capacitaciones_habilitadas = :cap,
                certificaciones_habilitadas = :cert,
                cursos_deshabilitados = :cursos,
                certificaciones_deshabilitadas = :certs,
                site_notice = :notice
          WHERE id = 1'
    );

    $stmt->execute([
        ':mp' => $mpEnabled ? 1 : 0,
        ':mode' => $mode,
        ':cap' => $capacitacionesEnabled ? 1 : 0,
        ':cert' => $certificacionesEnabled ? 1 : 0,
        ':cursos' => json_encode($disabledCursos),
        ':certs' => json_encode($disabledCertificaciones),
        ':notice' => $notice,
    ]);
}

function site_settings_is_mp_enabled(array $settings): bool
{
    return !empty($settings['mercado_pago_habilitado']);
}

function site_settings_get_mode(array $settings): string
{
    $mode = strtolower((string)($settings['site_mode'] ?? 'normal'));
    return in_array($mode, SITE_SETTINGS_ALLOWED_MODES, true) ? $mode : 'normal';
}

function site_settings_get_notice(array $settings): string
{
    return trim((string)($settings['site_notice'] ?? ''));
}

function site_settings_sales_enabled(array $settings, string $tipo, int $itemId = 0): bool
{
    $tipo = strtolower($tipo);
    $disabledList = [];
    $enabled = true;

    if (in_array($tipo, ['curso', 'capacitacion', 'capacitaciones'], true)) {
        $enabled = !empty($settings['capacitaciones_habilitadas']);
        $disabledList = $settings['cursos_deshabilitados'] ?? [];
    } elseif (in_array($tipo, ['certificacion', 'certificaciones'], true)) {
        $enabled = !empty($settings['certificaciones_habilitadas']);
        $disabledList = $settings['certificaciones_deshabilitadas'] ?? [];
    }

    if (!$enabled) {
        return false;
    }

    if ($itemId > 0) {
        $itemId = (int)$itemId;
        return !in_array($itemId, array_map('intval', $disabledList), true);
    }

    return true;
}

