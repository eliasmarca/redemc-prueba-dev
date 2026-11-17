<?php
declare(strict_types=1);

require_once __DIR__ . '/guardia.php';
negar_acceso_directo(__FILE__);

/**
 * Lee la conexión de las variables que Railway inyecta al referenciar el MySQL.
 * Si no están, usa un valor local: en Railway ese respaldo no llega a la base.
 */
function valor_env(string $clave, string $defecto): string
{
    $candidatos = [];
    $deEntorno = getenv($clave);
    if ($deEntorno !== false) {
        $candidatos[] = $deEntorno;
    }
    if (array_key_exists($clave, $_SERVER) && is_string($_SERVER[$clave])) {
        $candidatos[] = $_SERVER[$clave];
    }
    if (array_key_exists($clave, $_ENV) && is_string($_ENV[$clave])) {
        $candidatos[] = $_ENV[$clave];
    }

    foreach ($candidatos as $valor) {
        if ($valor !== '') {
            return $valor;
        }
    }

    return $defecto;
}

function abrir_conexion(): PDO
{
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('Falta la extensión pdo_mysql.');
    }

    $host = valor_env('MYSQLHOST', '127.0.0.1');
    $puerto = valor_env('MYSQLPORT', '3306');
    $usuario = valor_env('MYSQLUSER', 'root');
    $clave = valor_env('MYSQLPASSWORD', '');
    $base = valor_env('MYSQLDATABASE', 'redemc');

    // La contraseña va aparte del DSN. Host, puerto, usuario y base no pueden romper el DSN.
    if (preg_match('/^[A-Za-z0-9._-]+$/', $host) !== 1) {
        throw new RuntimeException('MYSQLHOST no es válido.');
    }
    if (preg_match('/^[0-9]+$/', $puerto) !== 1) {
        throw new RuntimeException('MYSQLPORT no es válido.');
    }
    if (preg_match('/^[A-Za-z0-9._-]+$/', $usuario) !== 1 || preg_match('/^[A-Za-z0-9._-]+$/', $base) !== 1) {
        throw new RuntimeException('El usuario o la base de MySQL no son válidos.');
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $puerto, $base);

    // Algunas builds de PHP 8.3 no definen MYSQL_ATTR_CONNECT_TIMEOUT.
    // El tope de espera queda en el socket, que sí existe en todas.
    ini_set('default_socket_timeout', '5');
    $opciones = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Las prepara MySQL. No emulamos concatenando en PHP.
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
        $opciones[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 5;
    }

    $pdo = new PDO($dsn, $usuario, $clave, $opciones);

    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    // Uruguay está en UTC-3 todo el año. NOW() queda en esa hora.
    $pdo->exec("SET time_zone = '-03:00'");

    return $pdo;
}

function crear_tabla(PDO $pdo): void
{
    $ruta = dirname(__DIR__) . '/sql/contactos.sql';
    $sql = file_get_contents($ruta);
    if ($sql === false) {
        throw new RuntimeException('No está el archivo sql/contactos.sql.');
    }

    $lineas = preg_split('/\R/', $sql) ?: [];
    $utiles = [];
    foreach ($lineas as $linea) {
        if (preg_match('/^\s*--/', $linea) === 1) {
            continue;
        }
        $utiles[] = $linea;
    }

    $sentencia = trim(implode("\n", $utiles));
    if ($sentencia === '') {
        throw new RuntimeException('El archivo sql/contactos.sql está vacío.');
    }

    // Una sola sentencia. CREATE TABLE IF NOT EXISTS se puede correr en cada arranque.
    $pdo->exec($sentencia);
}

function conexion(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $nueva = abrir_conexion();
    crear_tabla($nueva);
    $pdo = $nueva;

    return $pdo;
}
