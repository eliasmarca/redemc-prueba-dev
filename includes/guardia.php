<?php
declare(strict_types=1);

// Estos archivos solo se incluyen. Si alguien los pide por URL, no hay nada que mostrar.
if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

function negar_acceso_directo(string $archivo): void
{
    $entrada = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $actual = realpath($archivo);
    if ($entrada !== false && $actual !== false && $entrada === $actual) {
        http_response_code(404);
        exit;
    }
}
