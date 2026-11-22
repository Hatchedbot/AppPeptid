<?php
/**
 * Núcleo del inventario (lo usa la pantalla de inventario y los pedidos).
 *
 * El stock no se guarda: es la suma de movimientos_inventario de cada lote.
 * Al despachar un pedido se descuenta de los lotes que vencen primero (y nunca de lotes vencidos).
 * Al devolverlo, se reintegra a los mismos lotes de donde salió.
 */

const TIPOS_MOVIMIENTO = ['entrada' => 'Entrada', 'salida' => 'Salida', 'ajuste' => 'Ajuste', 'devolucion' => 'Devolución'];
const DIAS_POR_VENCER = 30;

/** Ejecuta $f dentro de una transacción, salvo que ya haya una abierta (entonces solo la ejecuta). */
function en_transaccion(callable $f): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $f($pdo);
    }
    $pdo->beginTransaction();
    try {
        $r = $f($pdo);
        $pdo->commit();
        return $r;
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }
}

function inventario_stock_lote(int $lote_id): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_inventario WHERE lote_id = ?');
    $st->execute([$lote_id]);
    return (int) $st->fetchColumn();
}

/** Stock de un producto. Con $solo_vigentes no cuenta los lotes ya vencidos. */
function inventario_stock_producto(int $producto_id, bool $solo_vigentes = false): int
{
    $sql = 'SELECT COALESCE(SUM(m.cantidad), 0) FROM movimientos_inventario m JOIN lotes l ON l.id = m.lote_id
            WHERE l.producto_id = ?';
    $params = [$producto_id];
    if ($solo_vigentes) {
        $sql .= ' AND (l.vencimiento IS NULL OR l.vencimiento >= ?)';
        $params[] = hoy();
    }
    $st = db()->prepare($sql);
    $st->execute($params);
    return (int) $st->fetchColumn();
}

/** Lotes de un producto con su stock, del que vence primero al último (sin fecha al final). */
function inventario_lotes(int $producto_id, bool $con_stock = false): array
{
    $st = db()->prepare(
        'SELECT l.*, COALESCE(SUM(m.cantidad), 0) AS stock
         FROM lotes l LEFT JOIN movimientos_inventario m ON m.lote_id = l.id
         WHERE l.producto_id = ?
         GROUP BY l.id, l.producto_id, l.codigo, l.vencimiento, l.notas, l.creado_por, l.creado
         ORDER BY CASE WHEN l.vencimiento IS NULL THEN 1 ELSE 0 END, l.vencimiento, l.id'
    );
    $st->execute([$producto_id]);
    $lotes = $st->fetchAll();
    foreach ($lotes as &$l) {
        $l['stock'] = (int) $l['stock'];
    }
    unset($l);
    return $con_stock ? array_values(array_filter($lotes, fn($l) => $l['stock'] > 0)) : $lotes;
}

/** Registra un movimiento. $cantidad positiva entra, negativa sale. */
function inventario_movimiento(int $lote_id, string $tipo, int $cantidad, ?int $pedido_id = null, string $nota = ''): int
{
    if (!isset(TIPOS_MOVIMIENTO[$tipo])) {
        throw new InvalidArgumentException('Tipo de movimiento no válido.');
    }
    if ($cantidad === 0) {
        throw new InvalidArgumentException('La cantidad no puede ser cero.');
    }
    return en_transaccion(function (PDO $pdo) use ($lote_id, $tipo, $cantidad, $pedido_id, $nota): int {
        if ($cantidad < 0 && inventario_stock_lote($lote_id) + $cantidad < 0) {
            throw new InvalidArgumentException('No hay suficientes unidades en ese lote.');
        }
        $u = usuario_actual();
        $pdo->prepare('INSERT INTO movimientos_inventario (lote_id, tipo, cantidad, pedido_id, nota, usuario_id, fecha)
                       VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$lote_id, $tipo, $cantidad, $pedido_id, mb_substr($nota, 0, 255) ?: null, $u['id'] ?? null, ahora()]);
        return (int) $pdo->lastInsertId();
    });
}

/**
 * Entrada de mercancía: crea el lote si no existe (mismo producto y código) y suma las unidades.
 * Devuelve el id del lote.
 */
function inventario_entrada(int $producto_id, string $codigo, ?string $vencimiento, int $cantidad, string $nota = ''): int
{
    $codigo = trim($codigo);
    if ($codigo === '') {
        throw new InvalidArgumentException('Escribe el código del lote (el que aparece en el vial o en el COA).');
    }
    if ($cantidad <= 0) {
        throw new InvalidArgumentException('La cantidad que entra debe ser mayor que cero.');
    }
    $vencimiento = $vencimiento !== null && trim($vencimiento) !== '' ? trim($vencimiento) : null;
    if ($vencimiento !== null) {
        $f = DateTime::createFromFormat('!Y-m-d', $vencimiento);
        if (!$f || $f->format('Y-m-d') !== $vencimiento) {
            throw new InvalidArgumentException('La fecha de vencimiento no es válida.');
        }
    }
    $st = db()->prepare('SELECT id FROM productos WHERE id = ?');
    $st->execute([$producto_id]);
    if (!$st->fetchColumn()) {
        throw new InvalidArgumentException('El producto no existe.');
    }

    return en_transaccion(function (PDO $pdo) use ($producto_id, $codigo, $vencimiento, $cantidad, $nota): int {
        $st = $pdo->prepare('SELECT id, vencimiento FROM lotes WHERE producto_id = ? AND codigo = ?');
        $st->execute([$producto_id, $codigo]);
        $lote = $st->fetch();
        if ($lote) {
            if ($vencimiento !== null && $lote['vencimiento'] !== null && $lote['vencimiento'] !== $vencimiento) {
                throw new InvalidArgumentException('Ese lote ya existe con otra fecha de vencimiento (' . fecha_corta($lote['vencimiento']) . '). Revisa el código.');
            }
            $lote_id = (int) $lote['id'];
            if ($lote['vencimiento'] === null && $vencimiento !== null) {
                $pdo->prepare('UPDATE lotes SET vencimiento = ? WHERE id = ?')->execute([$vencimiento, $lote_id]);
            }
        } else {
            $u = usuario_actual();
            $pdo->prepare('INSERT INTO lotes (producto_id, codigo, vencimiento, creado_por, creado) VALUES (?, ?, ?, ?, ?)')
                ->execute([$producto_id, $codigo, $vencimiento, $u['id'] ?? null, ahora()]);
            $lote_id = (int) $pdo->lastInsertId();
        }
        inventario_movimiento($lote_id, 'entrada', $cantidad, null, $nota);
        return $lote_id;
    });
}

/**
 * Descuenta del inventario los ítems de un pedido, de los lotes que vencen primero.
 * Nunca usa lotes vencidos. Si falta stock de algún producto no descuenta nada y lanza
 * InvalidArgumentException diciendo qué falta. Si el pedido ya tenía salidas, no hace nada.
 */
function inventario_descontar_pedido(int $pedido_id): void
{
    en_transaccion(function (PDO $pdo) use ($pedido_id): void {
        $st = $pdo->prepare("SELECT COALESCE(SUM(cantidad), 0) FROM movimientos_inventario WHERE pedido_id = ? AND tipo = 'salida'");
        $st->execute([$pedido_id]);
        if ((int) $st->fetchColumn() !== 0) {
            return;
        }
        $st = $pdo->prepare('SELECT producto_id, MAX(producto_nombre) AS nombre, SUM(cantidad) AS cantidad
                             FROM pedido_items WHERE pedido_id = ? GROUP BY producto_id');
        $st->execute([$pedido_id]);
        $faltan = [];
        $plan = [];
        foreach ($st->fetchAll() as $item) {
            $pendiente = (int) $item['cantidad'];
            foreach (inventario_lotes((int) $item['producto_id'], true) as $lote) {
                if ($pendiente <= 0) {
                    break;
                }
                if ($lote['vencimiento'] !== null && $lote['vencimiento'] < hoy()) {
                    continue;
                }
                $toma = min($pendiente, $lote['stock']);
                $plan[] = [(int) $lote['id'], $toma];
                $pendiente -= $toma;
            }
            if ($pendiente > 0) {
                $faltan[] = $item['nombre'] . " (faltan $pendiente)";
            }
        }
        if ($faltan) {
            throw new InvalidArgumentException('No hay suficiente inventario vigente de: ' . implode(', ', $faltan) . '.');
        }
        foreach ($plan as [$lote_id, $cantidad]) {
            inventario_movimiento($lote_id, 'salida', -$cantidad, $pedido_id, "Pedido #$pedido_id");
        }
    });
}

/** Devuelve al inventario lo que salió por un pedido (a los mismos lotes). Solo una vez. */
function inventario_reintegrar_pedido(int $pedido_id): void
{
    en_transaccion(function (PDO $pdo) use ($pedido_id): void {
        $st = $pdo->prepare('SELECT lote_id, SUM(cantidad) AS neto FROM movimientos_inventario
                             WHERE pedido_id = ? GROUP BY lote_id');
        $st->execute([$pedido_id]);
        foreach ($st->fetchAll() as $fila) {
            $neto = (int) $fila['neto'];
            if ($neto < 0) {
                inventario_movimiento((int) $fila['lote_id'], 'devolucion', -$neto, $pedido_id, "Devolución pedido #$pedido_id");
            }
        }
    });
}

/**
 * Lo que hay que mirar: productos con poco stock vigente, lotes por vencer (con stock) y lotes vencidos con stock.
 * Devuelve ['stock_bajo' => [...], 'por_vencer' => [...], 'vencidos' => [...]].
 */
function inventario_alertas(): array
{
    $stock_bajo = [];
    foreach (db()->query('SELECT id, nombre, presentacion, stock_minimo FROM productos WHERE activo = 1 ORDER BY nombre, id')->fetchAll() as $p) {
        $stock = inventario_stock_producto((int) $p['id'], true);
        if ($stock <= (int) $p['stock_minimo']) {
            $stock_bajo[] = $p + ['stock' => $stock];
        }
    }
    $limite = date('Y-m-d', strtotime('+' . DIAS_POR_VENCER . ' days'));
    $st = db()->prepare(
        'SELECT l.id, l.codigo, l.vencimiento, p.nombre, p.presentacion, COALESCE(SUM(m.cantidad), 0) AS stock
         FROM lotes l JOIN productos p ON p.id = l.producto_id LEFT JOIN movimientos_inventario m ON m.lote_id = l.id
         WHERE l.vencimiento IS NOT NULL AND l.vencimiento <= ?
         GROUP BY l.id, l.codigo, l.vencimiento, p.nombre, p.presentacion
         HAVING COALESCE(SUM(m.cantidad), 0) > 0
         ORDER BY l.vencimiento'
    );
    $st->execute([$limite]);
    $por_vencer = [];
    $vencidos = [];
    foreach ($st->fetchAll() as $l) {
        $l['stock'] = (int) $l['stock'];
        if ($l['vencimiento'] < hoy()) {
            $vencidos[] = $l;
        } else {
            $por_vencer[] = $l;
        }
    }
    return ['stock_bajo' => $stock_bajo, 'por_vencer' => $por_vencer, 'vencidos' => $vencidos];
}

/** Verdadero solo cuando $archivo es el script que se está ejecutando. */
function inventario_pagina_es_actual(string $archivo): bool
{
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    return $script !== '' && realpath($script) === realpath($archivo);
}

/** Texto comparable (minúsculas y sin tildes) para buscar. */
function inventario_texto_comparable(string $texto): string
{
    $texto = (string) preg_replace('/\s+/u', ' ', trim($texto));
    $texto = mb_strtolower($texto, 'UTF-8');
    return strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ]);
}

/**
 * Productos activos con su stock vigente, stock mínimo y estado.
 * Con $q filtra por nombre, presentación o categoría (sin tildes).
 * Devuelve cada fila con 'stock' y 'estado' (ok | bajo | agotado).
 */
function inventario_productos(string $q = ''): array
{
    $filas = db()->query(
        'SELECT id, nombre, presentacion, categoria, precio, activo, stock_minimo
         FROM productos WHERE activo = 1
         ORDER BY categoria, nombre, id'
    )->fetchAll();

    $q = inventario_texto_comparable($q);
    $resultado = [];
    foreach ($filas as $fila) {
        if ($q !== '') {
            $texto = inventario_texto_comparable(
                $fila['nombre'] . ' ' . $fila['presentacion'] . ' ' . $fila['categoria']
            );
            if (!str_contains($texto, $q)) {
                continue;
            }
        }
        $stock = inventario_stock_producto((int) $fila['id'], true);
        $fila['stock'] = $stock;
        $fila['estado'] = $stock <= 0
            ? 'agotado'
            : ($stock <= (int) $fila['stock_minimo'] ? 'bajo' : 'ok');
        $resultado[] = $fila;
    }
    return $resultado;
}

/** Un producto por id, o null si no existe. */
function inventario_producto_obtener(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM productos WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/** Un lote por id, o null si no existe. */
function inventario_lote_obtener(int $lote_id): ?array
{
    $st = db()->prepare('SELECT * FROM lotes WHERE id = ?');
    $st->execute([$lote_id]);
    return $st->fetch() ?: null;
}

/**
 * Ajusta el stock de un lote. $cantidad positiva suma y negativa resta.
 * El motivo es obligatorio y queda en la nota del movimiento.
 */
function inventario_ajustar(int $lote_id, int $cantidad, string $motivo): int
{
    $motivo = trim($motivo);
    if ($motivo === '') {
        throw new InvalidArgumentException('Escribe el motivo del ajuste.');
    }
    if ($cantidad === 0) {
        throw new InvalidArgumentException('La cantidad del ajuste no puede ser cero.');
    }
    if (!inventario_lote_obtener($lote_id)) {
        throw new InvalidArgumentException('El lote no existe.');
    }
    return inventario_movimiento($lote_id, 'ajuste', $cantidad, null, 'Ajuste: ' . $motivo);
}

/** Movimientos recientes de un producto, con lote, pedido y usuario. */
function inventario_movimientos(int $producto_id, int $limite = 50): array
{
    $st = db()->prepare(
        'SELECT m.id, m.tipo, m.cantidad, m.pedido_id, m.nota, m.fecha,
                l.codigo AS lote_codigo, l.vencimiento AS lote_vencimiento,
                u.nombre AS usuario_nombre
         FROM movimientos_inventario m
         JOIN lotes l ON l.id = m.lote_id
         LEFT JOIN usuarios u ON u.id = m.usuario_id
         WHERE l.producto_id = ?
         ORDER BY m.fecha DESC, m.id DESC
         LIMIT ?'
    );
    $st->bindValue(1, $producto_id, PDO::PARAM_INT);
    $st->bindValue(2, $limite, PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

/** Cambia el stock mínimo y el precio de un producto. Valida ambos. */
function inventario_actualizar_producto(int $id, array $datos): void
{
    if (!inventario_producto_obtener($id)) {
        throw new InvalidArgumentException('El producto no existe.');
    }
    $minimo = numero_entrada($datos['stock_minimo'] ?? null);
    if ($minimo === null || $minimo < 0 || floor($minimo) != $minimo) {
        throw new InvalidArgumentException('El stock mínimo debe ser un número entero mayor o igual que cero.');
    }
    $precio = numero_entrada($datos['precio'] ?? null);
    if ($precio === null || $precio < 0) {
        throw new InvalidArgumentException('El precio debe ser un número mayor o igual que cero.');
    }
    db()->prepare('UPDATE productos SET stock_minimo = ?, precio = ? WHERE id = ?')
        ->execute([(int) $minimo, (int) round($precio), $id]);
}
