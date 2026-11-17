<?php
declare(strict_types=1);

require_once __DIR__ . '/guardia.php';
negar_acceso_directo(__FILE__);

/**
 * Reglas del enunciado. assets/js/app.js repite los mismos regex y los mismos
 * textos: el navegador avisa antes, y esta función es la que decide.
 */

const POR_PAGINA = 8;
const NOMBRE_MAX = 120;
const EMAIL_MAX = 254;

// Letras de cualquier idioma (tildes y ñ entran en \p{L}) y espacios comunes.
const RX_NOMBRE = '/^[\p{L} ]+$/u';

// Estricto: sin espacios, sin puntos seguidos, con dominio y TLD de 2+ letras.
const RX_EMAIL = '/^[A-Za-z0-9_%+-]+(?:\.[A-Za-z0-9_%+-]+)*@[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*\.[A-Za-z]{2,}$/';

// Celular uruguayo: exactamente 9 dígitos y empieza con 09. Nada de espacios, guiones ni +598.
const RX_TELEFONO = '/^09[0-9]{7}$/';

const MSG_NOMBRE_OBLIGATORIO = 'El nombre es obligatorio.';
const MSG_NOMBRE_LARGO = 'El nombre no puede superar los 120 caracteres.';
const MSG_NOMBRE_FORMATO = 'El nombre solo puede contener letras y espacios (se permiten tildes y ñ).';
const MSG_EMAIL_OBLIGATORIO = 'El email es obligatorio.';
const MSG_EMAIL_FORMATO = 'Ingresá un email válido. Ejemplo: nombre@dominio.com.';
const MSG_EMAIL_UNICO = 'Ya hay un contacto registrado con ese email.';
const MSG_TELEFONO_FORMATO = 'Si completás el teléfono, tiene que ser un celular uruguayo: 9 dígitos, empezar con 09, sin espacios, guiones ni +598. Ejemplo: 097680102.';
const MSG_TELEFONO_UNICO = 'Ya hay un contacto registrado con ese teléfono.';

function utf8_valido(string $texto): bool
{
    return preg_match('//u', $texto) === 1;
}

function longitud(string $texto): int
{
    $cantidad = preg_match_all('/./u', $texto, $coincidencias);
    return $cantidad === false ? 0 : $cantidad;
}

/**
 * Recorta igual que trim() de PHP (espacios en blanco ASCII).
 * Los espacios de adentro no se tocan: si no son letras, falla el regex.
 *
 * @return array{ok: bool, errores: array<string, string>, datos: array{nombre: string, email: string, telefono: ?string}}
 */
function validar_contacto(string $nombre, string $email, string $telefono): array
{
    $errores = [];

    $nombreLimpio = trim($nombre);
    if ($nombreLimpio === '') {
        $errores['nombre'] = MSG_NOMBRE_OBLIGATORIO;
    } elseif (!utf8_valido($nombreLimpio) || preg_match(RX_NOMBRE, $nombreLimpio) !== 1) {
        $errores['nombre'] = MSG_NOMBRE_FORMATO;
    } elseif (longitud($nombreLimpio) > NOMBRE_MAX) {
        $errores['nombre'] = MSG_NOMBRE_LARGO;
    }

    $emailLimpio = trim($email);
    if ($emailLimpio === '') {
        $errores['email'] = MSG_EMAIL_OBLIGATORIO;
    } elseif (
        !utf8_valido($emailLimpio)
        || strlen($emailLimpio) > EMAIL_MAX
        || preg_match(RX_EMAIL, $emailLimpio) !== 1
    ) {
        $errores['email'] = MSG_EMAIL_FORMATO;
    }

    $telefonoLimpio = trim($telefono);
    $telefonoFinal = null;
    if ($telefonoLimpio !== '') {
        // Tope holgado para rechazar rápido un pegado enorme; el formato lo decide el regex.
        if (strlen($telefonoLimpio) > 32 || preg_match(RX_TELEFONO, $telefonoLimpio) !== 1) {
            $errores['telefono'] = MSG_TELEFONO_FORMATO;
        } else {
            $telefonoFinal = $telefonoLimpio;
        }
    }

    $nombreFinal = $nombreLimpio;
    if (!isset($errores['nombre'])) {
        $colapsado = preg_replace('/ {2,}/', ' ', $nombreLimpio);
        $nombreFinal = is_string($colapsado) ? $colapsado : $nombreLimpio;
    }

    $emailFinal = isset($errores['email']) ? $emailLimpio : strtolower($emailLimpio);

    return [
        'ok' => $errores === [],
        'errores' => $errores,
        'datos' => [
            'nombre' => $nombreFinal,
            'email' => $emailFinal,
            'telefono' => $telefonoFinal,
        ],
    ];
}
