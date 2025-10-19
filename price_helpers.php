<?php
declare(strict_types=1);

/**
 * Lista de tipos de precio admitidos para los cursos.
 *
 * @return string[]
 */
function course_price_valid_types(): array
{
    return ['capacitacion', 'certificacion'];
}

/**
 * Normaliza el identificador de tipo de precio recibido desde formularios o URL.
 */
function course_price_normalize_type(?string $type): string
{
    $type = strtolower(trim((string) $type));
    return in_array($type, course_price_valid_types(), true) ? $type : 'capacitacion';
}

/**
 * Devuelve la etiqueta legible para un tipo de precio.
 */
function course_price_label(string $type): string
{
    return $type === 'certificacion' ? 'Certificación' : 'Capacitación';
}
