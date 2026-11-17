<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/inicializar.php';
require_once __DIR__ . '/../includes/contactos.php';

modo_api();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    responder_json(405, [
        'ok' => false,
        'mensaje' => 'Método no permitido.',
    ]);
}

$pagina = 1;
$valor = $_GET['pagina'] ?? '1';
if (is_string($valor) && preg_match('/^[0-9]{1,6}$/', $valor) === 1) {
    $pagina = max(1, (int) $valor);
}

try {
    $listado = listar_contactos(conexion(), $pagina);
} catch (Throwable $e) {
    error_log('Listar: ' . $e->getMessage());
    responder_json(500, [
        'ok' => false,
        'mensaje' => 'No se pudo cargar el listado.',
    ]);
}

responder_json(200, [
    'ok' => true,
    'contactos' => $listado['contactos'],
    'pagina' => $listado['pagina'],
    'por_pagina' => $listado['por_pagina'],
    'total' => $listado['total'],
    'paginas' => $listado['paginas'],
]);
