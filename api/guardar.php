<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/inicializar.php';
require_once __DIR__ . '/../includes/validacion.php';
require_once __DIR__ . '/../includes/contactos.php';

modo_api();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responder_json(405, [
        'ok' => false,
        'mensaje' => 'Método no permitido.',
    ]);
}

$cuerpo = leer_cuerpo_json();
exigir_csrf(token_desde_pedido($cuerpo));

$validacion = validar_contacto(
    texto_de($cuerpo, 'nombre'),
    texto_de($cuerpo, 'email'),
    texto_de($cuerpo, 'telefono')
);

if (!$validacion['ok']) {
    responder_json(422, [
        'ok' => false,
        'mensaje' => 'Revisá los campos marcados.',
        'errores' => $validacion['errores'],
    ]);
}

try {
    $guardado = guardar_contacto(conexion(), $validacion['datos']);
} catch (Throwable $e) {
    error_log('Guardar: ' . $e->getMessage());
    responder_json(500, [
        'ok' => false,
        'mensaje' => 'No se pudo guardar el contacto. Intentá de nuevo en unos segundos.',
    ]);
}

if (!$guardado['ok']) {
    responder_json(422, [
        'ok' => false,
        'mensaje' => 'Revisá los campos marcados.',
        'errores' => $guardado['errores'],
    ]);
}

responder_json(201, [
    'ok' => true,
    'mensaje' => 'Contacto guardado. Ya aparece primero en el listado.',
    'contacto' => $guardado['contacto'],
]);
