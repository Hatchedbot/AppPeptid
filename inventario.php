<?php
/**
 * Pantalla de inventario: alertas, existencias por producto, entrada de mercancía
 * y acceso a la ficha de cada producto (lotes, movimientos y ajustes).
 *
 * Las funciones del módulo viven aquí, al principio, para poder probarlas sin
 * ejecutar la página. El cuerpo de la página solo corre cuando este archivo es
 * el script pedido (así producto.php puede incluirlo para reusar las funciones).
 */

if (!function_exists('inventario_pagina_es_actual')) {
    /** Verdadero solo cuando $archivo es el script que se está ejecutando. */
    function inventario_pagina_es_actual(string $archivo): bool
    {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
        return $script !== '' && realpath($script) === realpath($archivo);
    }
}

if (!function_exists('inventario_productos')) {
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
}

/* El cuerpo de la página solo corre cuando se pide este archivo. */
if (!inventario_pagina_es_actual(__FILE__)) {
    return;
}

if (!defined('RAIZ')) {
    require __DIR__ . '/includes/app.php';
}
require_once __DIR__ . '/includes/inventario.php';
requerir_permiso('inventario');

$q_raw = trim((string) ($_GET['q'] ?? ''));
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $producto_id = (int) ($_POST['producto_id'] ?? 0);
        $codigo = trim((string) ($_POST['codigo'] ?? ''));
        $vencimiento = trim((string) ($_POST['vencimiento'] ?? ''));
        $cantidad = numero_entrada($_POST['cantidad'] ?? null);
        $nota = trim((string) ($_POST['nota'] ?? ''));
        if ($producto_id <= 0) {
            throw new InvalidArgumentException('Elige el producto al que le entra mercancía.');
        }
        if ($cantidad === null || $cantidad <= 0 || floor($cantidad) != $cantidad) {
            throw new InvalidArgumentException('La cantidad debe ser un número entero mayor que cero.');
        }
        inventario_entrada(
            $producto_id,
            $codigo,
            $vencimiento !== '' ? $vencimiento : null,
            (int) $cantidad,
            $nota
        );
        avisar('ok', 'Entrada guardada. El stock ya está actualizado.');
        redirigir('inventario.php' . ($q_raw !== '' ? '?q=' . rawurlencode($q_raw) : ''));
    } catch (InvalidArgumentException $ex) {
        $error = $ex->getMessage();
    }
}

$alertas = inventario_alertas();
$productos = inventario_productos($q_raw);
$productos_activos = db()->query(
    'SELECT id, nombre, presentacion FROM productos WHERE activo = 1 ORDER BY nombre, id'
)->fetchAll();

$titulo = 'Inventario';
$activo = 'inventario';
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <div>
        <h1>Inventario</h1>
        <p class="suave">Existencias por lote, entradas y ajustes. El stock se descuenta del lote que vence primero.</p>
    </div>
</div>

<section class="tarjeta">
    <h2>Alertas</h2>
    <?php if (!$alertas['stock_bajo'] && !$alertas['por_vencer'] && !$alertas['vencidos']): ?>
        <p class="suave">Todo en orden: no hay stock bajo, lotes por vencer ni vencidos con unidades.</p>
    <?php else: ?>
        <?php if ($alertas['stock_bajo']): ?>
            <h3>Stock bajo</h3>
            <ul class="lista">
                <?php foreach ($alertas['stock_bajo'] as $p): ?>
                    <li>
                        <a href="producto.php?id=<?= (int) $p['id'] ?>"><strong><?= e($p['nombre']) ?> · <?= e($p['presentacion']) ?></strong></a>
                        <span class="etiqueta <?= (int) $p['stock'] <= 0 ? 'etiqueta-error' : 'etiqueta-alerta' ?>">
                            <?= (int) $p['stock'] <= 0 ? 'Agotado' : 'Quedan ' . (int) $p['stock'] ?>
                        </span>
                        <div class="suave">Mínimo: <?= (int) $p['stock_minimo'] ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($alertas['por_vencer']): ?>
            <h3>Por vencer (30 días)</h3>
            <ul class="lista">
                <?php foreach ($alertas['por_vencer'] as $l): ?>
                    <li>
                        <strong><?= e($l['codigo']) ?></strong> · <?= e($l['nombre']) ?> · <?= e($l['presentacion']) ?>
                        <span class="etiqueta etiqueta-alerta">Vence <?= e(fecha_corta((string) $l['vencimiento'])) ?></span>
                        <div class="suave"><?= (int) $l['stock'] ?> unidades</div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?php if ($alertas['vencidos']): ?>
            <h3>Vencidos con unidades</h3>
            <ul class="lista">
                <?php foreach ($alertas['vencidos'] as $l): ?>
                    <li>
                        <strong><?= e($l['codigo']) ?></strong> · <?= e($l['nombre']) ?> · <?= e($l['presentacion']) ?>
                        <span class="etiqueta etiqueta-error">Venció <?= e(fecha_corta((string) $l['vencimiento'])) ?></span>
                        <div class="suave"><?= (int) $l['stock'] ?> unidades</div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>

<form method="get" action="inventario.php" class="tarjeta">
    <div class="campo">
        <label for="q">Buscar</label>
        <div class="fila">
            <input type="search" id="q" name="q" value="<?= e($q_raw) ?>"
                   placeholder="Nombre, presentación o categoría" autocomplete="off">
            <button type="submit" class="boton">Buscar</button>
        </div>
    </div>
    <?php if ($q_raw !== ''): ?>
        <p class="suave">Resultados para «<?= e($q_raw) ?>». <a href="inventario.php">Ver todos</a></p>
    <?php endif; ?>
</form>

<section class="tarjeta">
    <h2>Entrada de mercancía</h2>
    <p class="suave">Suma unidades a un lote. Si el código no existe, se crea el lote.</p>
    <?php if ($error): ?>
        <div class="aviso aviso-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <div class="campo">
            <label for="producto_id">Producto *</label>
            <select id="producto_id" name="producto_id" required>
                <option value="">Elige un producto</option>
                <?php foreach ($productos_activos as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) ($_POST['producto_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= e($p['nombre'] . ' · ' . $p['presentacion']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="codigo">Código del lote *</label>
                <input type="text" id="codigo" name="codigo" maxlength="60" required
                       value="<?= e($_POST['codigo'] ?? '') ?>" placeholder="El del vial o del COA">
            </div>
            <div class="campo">
                <label for="vencimiento">Vencimiento</label>
                <input type="date" id="vencimiento" name="vencimiento" value="<?= e($_POST['vencimiento'] ?? '') ?>">
            </div>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="cantidad">Cantidad *</label>
                <input type="number" id="cantidad" name="cantidad" min="1" step="1" inputmode="numeric" required
                       value="<?= e($_POST['cantidad'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="nota">Nota</label>
                <input type="text" id="nota" name="nota" maxlength="255" value="<?= e($_POST['nota'] ?? '') ?>"
                       placeholder="Opcional">
            </div>
        </div>
        <div><button type="submit" class="boton">Guardar entrada</button></div>
    </form>
</section>

<section class="tarjeta">
    <h2>Productos</h2>
    <?php if (!$productos): ?>
        <p class="suave">
            <?= $q_raw !== ''
                ? 'No encontramos productos con «' . e($q_raw) . '». Prueba con otro nombre.'
                : 'Todavía no hay productos. Agrega los del catálogo.' ?>
        </p>
    <?php else: ?>
        <table class="tabla tabla-movil">
            <thead>
                <tr><th>Producto</th><th>Categoría</th><th>Stock vigente</th><th>Mínimo</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($productos as $p): ?>
                <tr>
                    <td>
                        <a href="producto.php?id=<?= (int) $p['id'] ?>"><strong><?= e($p['nombre']) ?></strong> · <?= e($p['presentacion']) ?></a>
                    </td>
                    <td><?= e($p['categoria']) ?></td>
                    <td><?= (int) $p['stock'] ?></td>
                    <td><?= (int) $p['stock_minimo'] ?></td>
                    <td>
                        <?php if ($p['estado'] === 'ok'): ?>
                            <span class="etiqueta etiqueta-ok">Ok</span>
                        <?php elseif ($p['estado'] === 'bajo'): ?>
                            <span class="etiqueta etiqueta-alerta">Bajo</span>
                        <?php else: ?>
                            <span class="etiqueta etiqueta-error">Agotado</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="suave"><?= count($productos) ?> productos.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
