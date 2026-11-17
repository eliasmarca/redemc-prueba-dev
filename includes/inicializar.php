<?php
declare(strict_types=1);

require_once __DIR__ . '/guardia.php';
negar_acceso_directo(__FILE__);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('default_charset', 'UTF-8');
date_default_timezone_set('America/Montevideo');

function e(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_web(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(dirname($script), '/');
    if ($dir === '' || $dir === '.' || $dir === '/') {
        return '';
    }

    return $dir;
}

function cabeceras_seguridad(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Cache-Control: no-store');
    header(
        "Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
        . "img-src 'self' data:; font-src 'self'; base-uri 'self'; form-action 'self'; "
        . "frame-ancestors 'self'; object-src 'none'"
    );
    header_remove('X-Powered-By');
}

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('REDSESION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
}

function token_csrf(): string
{
    return (string) $_SESSION['csrf'];
}

function responder_json(int $codigo, array $datos): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    // Sin JSON_NUMERIC_CHECK: el teléfono tiene que seguir siendo texto ("097...").
    $json = json_encode(
        $datos,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if ($json === false) {
        $json = '{"ok":false,"mensaje":"No se pudo preparar la respuesta."}';
    }

    echo $json;
    exit;
}

function modo_api(): void
{
    set_exception_handler(static function (Throwable $e): void {
        error_log('Error API: ' . $e->getMessage());
        if (!headers_sent()) {
            responder_json(500, [
                'ok' => false,
                'mensaje' => 'Ocurrió un error al procesar la solicitud. Intentá de nuevo.',
            ]);
        }
        exit;
    });
}

function exigir_csrf(string $token): void
{
    $esperado = $_SESSION['csrf'] ?? '';
    if (!is_string($esperado) || $esperado === '' || $token === '' || !hash_equals($esperado, $token)) {
        responder_json(403, [
            'ok' => false,
            'mensaje' => 'No se pudo validar la sesión. Recargá la página e intentá de nuevo.',
        ]);
    }
}

function token_desde_pedido(array $cuerpo): string
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    if (isset($cuerpo['csrf']) && is_string($cuerpo['csrf'])) {
        return $cuerpo['csrf'];
    }

    return '';
}

function leer_cuerpo_json(): array
{
    $crudo = file_get_contents('php://input');
    if ($crudo === false || $crudo === '') {
        responder_json(400, [
            'ok' => false,
            'mensaje' => 'El cuerpo de la solicitud no es válido.',
        ]);
    }
    if (strlen($crudo) > 20000) {
        responder_json(413, [
            'ok' => false,
            'mensaje' => 'La solicitud es demasiado grande.',
        ]);
    }

    $datos = json_decode($crudo, true);
    if (!is_array($datos)) {
        responder_json(400, [
            'ok' => false,
            'mensaje' => 'El cuerpo de la solicitud no es válido.',
        ]);
    }

    return $datos;
}

function texto_de(array $datos, string $clave): string
{
    if (!array_key_exists($clave, $datos) || is_array($datos[$clave]) || is_object($datos[$clave])) {
        return '';
    }

    return (string) $datos[$clave];
}

cabeceras_seguridad();
iniciar_sesion();
