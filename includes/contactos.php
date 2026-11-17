<?php
declare(strict_types=1);

require_once __DIR__ . '/validacion.php';
require_once __DIR__ . '/db.php';
negar_acceso_directo(__FILE__);

function fecha_legible(string $fecha): string
{
    try {
        $dt = new DateTimeImmutable($fecha);
    } catch (Exception $e) {
        return $fecha;
    }

    $meses = [
        1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr',
        5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago',
        9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
    ];
    $mes = $meses[(int) $dt->format('n')] ?? $dt->format('m');

    return $dt->format('j') . ' ' . $mes . ' ' . $dt->format('Y, H:i');
}

function presentar_contacto(array $fila): array
{
    $telefono = $fila['telefono'];

    return [
        'id' => (int) $fila['id'],
        'nombre' => (string) $fila['nombre'],
        'email' => (string) $fila['email'],
        'telefono' => ($telefono === null || $telefono === '') ? null : (string) $telefono,
        'creado_en' => (string) $fila['creado_en'],
        'creado_en_legible' => fecha_legible((string) $fila['creado_en']),
    ];
}

/**
 * @return array<string, string>
 */
function buscar_duplicados(PDO $pdo, string $email, ?string $telefono): array
{
    $errores = [];

    $stmt = $pdo->prepare('SELECT id FROM contactos WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $errores['email'] = MSG_EMAIL_UNICO;
    }

    if ($telefono !== null) {
        $stmt = $pdo->prepare('SELECT id FROM contactos WHERE telefono = ? LIMIT 1');
        $stmt->execute([$telefono]);
        if ($stmt->fetch()) {
            $errores['telefono'] = MSG_TELEFONO_UNICO;
        }
    }

    return $errores;
}

function campo_duplicado(PDOException $e): ?string
{
    $info = $e->errorInfo ?? [];
    if (!isset($info[1]) || (int) $info[1] !== 1062) {
        return null;
    }

    $mensaje = (string) ($info[2] ?? '');
    if (str_contains($mensaje, 'uq_contactos_email')) {
        return 'email';
    }
    if (str_contains($mensaje, 'uq_contactos_telefono')) {
        return 'telefono';
    }

    return null;
}

function obtener_contacto(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, telefono, creado_en
         FROM contactos
         WHERE id = ?'
    );
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    if (!$fila) {
        throw new RuntimeException('No se pudo leer el contacto recién guardado.');
    }

    return presentar_contacto($fila);
}

/**
 * El índice UNIQUE es el cierre de la unicidad si dos altas llegan juntas.
 *
 * @param array{nombre: string, email: string, telefono: ?string} $datos
 * @return array{ok: true, contacto: array<string, mixed>}|array{ok: false, errores: array<string, string>}
 */
function guardar_contacto(PDO $pdo, array $datos): array
{
    $pdo->beginTransaction();
    try {
        $errores = buscar_duplicados($pdo, $datos['email'], $datos['telefono']);
        if ($errores !== []) {
            $pdo->rollBack();
            return ['ok' => false, 'errores' => $errores];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO contactos (nombre, email, telefono, creado_en)
             VALUES (:nombre, :email, :telefono, NOW())'
        );
        $stmt->bindValue(':nombre', $datos['nombre'], PDO::PARAM_STR);
        $stmt->bindValue(':email', $datos['email'], PDO::PARAM_STR);
        // execute([null]) a veces manda '' y el segundo teléfono vacío choca con el UNIQUE.
        if ($datos['telefono'] === null) {
            $stmt->bindValue(':telefono', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':telefono', $datos['telefono'], PDO::PARAM_STR);
        }
        $stmt->execute();

        $contacto = obtener_contacto($pdo, (int) $pdo->lastInsertId());
        $pdo->commit();

        return ['ok' => true, 'contacto' => $contacto];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $campo = campo_duplicado($e);
        if ($campo === 'email') {
            return ['ok' => false, 'errores' => ['email' => MSG_EMAIL_UNICO]];
        }
        if ($campo === 'telefono') {
            return ['ok' => false, 'errores' => ['telefono' => MSG_TELEFONO_UNICO]];
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Ocho por página. El resto se pide por Ajax, sin recargar.
 *
 * @return array{contactos: list<array<string, mixed>>, pagina: int, por_pagina: int, total: int, paginas: int}
 */
function listar_contactos(PDO $pdo, int $pagina): array
{
    $total = (int) $pdo->query('SELECT COUNT(*) FROM contactos')->fetchColumn();
    $paginas = $total === 0 ? 1 : (int) ceil($total / POR_PAGINA);
    if ($pagina > $paginas) {
        $pagina = $paginas;
    }
    if ($pagina < 1) {
        $pagina = 1;
    }

    $desplazamiento = ($pagina - 1) * POR_PAGINA;
    $stmt = $pdo->prepare(
        'SELECT id, nombre, email, telefono, creado_en
         FROM contactos
         ORDER BY creado_en DESC, id DESC
         LIMIT ? OFFSET ?'
    );
    $stmt->bindValue(1, POR_PAGINA, PDO::PARAM_INT);
    $stmt->bindValue(2, $desplazamiento, PDO::PARAM_INT);
    $stmt->execute();

    $contactos = [];
    foreach ($stmt->fetchAll() as $fila) {
        $contactos[] = presentar_contacto($fila);
    }

    return [
        'contactos' => $contactos,
        'pagina' => $pagina,
        'por_pagina' => POR_PAGINA,
        'total' => $total,
        'paginas' => $paginas,
    ];
}
