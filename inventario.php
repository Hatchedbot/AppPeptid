<?php
/**
 * Pantalla de inventario: alertas, existencias por producto, entrada de mercancía
 * y acceso a la ficha de cada producto (lotes, movimientos y ajustes).
 *
 * Las funciones del módulo viven en includes/inventario.php.
 */

require_once __DIR__ . '/includes/inventario.php';

/* El cuerpo de la página solo corre cuando se pide este archivo. */
if (!inventario_pagina_es_actual(__FILE__)) {
    return;
}

if (!defined('RAIZ')) {
    require __DIR__ . '/includes/app.php';
}
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
    <a class="boton" href="#entrada">Registrar entrada</a>
</div>

<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('alerta') ?> Alertas</h2>
    <?php if (!$alertas['stock_bajo'] && !$alertas['por_vencer'] && !$alertas['vencidos']): ?>
        <p class="suave">Todo en orden: no hay stock bajo, lotes por vencer ni vencidos con unidades.</p>
    <?php else: ?>
        <?php
        /* Cada grupo muestra las primeras 5; el resto queda plegado en un details. */
        $alerta_item_stock = function (array $p): void { ?>
            <li>
                <a class="boton boton-sec" href="producto.php?id=<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?> · <?= e($p['presentacion']) ?></a>
                <span class="etiqueta <?= (int) $p['stock'] <= 0 ? 'etiqueta-error' : 'etiqueta-alerta' ?>">
                    <?= (int) $p['stock'] <= 0 ? 'Agotado' : 'Quedan ' . (int) $p['stock'] ?>
                </span>
                <div class="suave">Mínimo: <?= (int) $p['stock_minimo'] ?></div>
            </li>
        <?php };
        $alerta_item_lote = function (string $clase, string $prefijo): callable {
            return function (array $l) use ($clase, $prefijo): void { ?>
                <li>
                    <strong><?= e($l['codigo']) ?></strong> · <?= e($l['nombre']) ?> · <?= e($l['presentacion']) ?>
                    <span class="etiqueta <?= $clase ?>"><?= $prefijo ?> <?= e(fecha_corta((string) $l['vencimiento'])) ?></span>
                    <div class="suave"><?= (int) $l['stock'] ?> unidades</div>
                </li>
            <?php };
        };
        $alerta_grupo = function (callable $item, array $items): void {
            $visibles = array_slice($items, 0, 5);
            $resto = array_slice($items, 5);
            ?>
            <ul class="lista">
                <?php foreach ($visibles as $it): $item($it); endforeach; ?>
            </ul>
            <?php if ($resto): ?>
                <details>
                    <summary class="boton boton-sec">Ver las <?= count($resto) ?> restantes</summary>
                    <ul class="lista">
                        <?php foreach ($resto as $it): $item($it); endforeach; ?>
                    </ul>
                </details>
            <?php endif;
        };
        ?>
        <?php if ($alertas['stock_bajo']): ?>
            <h3>Stock bajo</h3>
            <?php $alerta_grupo($alerta_item_stock, $alertas['stock_bajo']); ?>
        <?php endif; ?>
        <?php if ($alertas['por_vencer']): ?>
            <h3>Por vencer (30 días)</h3>
            <?php $alerta_grupo($alerta_item_lote('etiqueta-alerta', 'Vence'), $alertas['por_vencer']); ?>
        <?php endif; ?>
        <?php if ($alertas['vencidos']): ?>
            <h3>Vencidos con unidades</h3>
            <?php $alerta_grupo($alerta_item_lote('etiqueta-error', 'Venció'), $alertas['vencidos']); ?>
        <?php endif; ?>
    <?php endif; ?>
</section>

<form method="get" action="inventario.php" class="tarjeta formulario">
    <h2 class="titulo-icono"><?= icono('buscar') ?> Buscar productos</h2>
    <div class="campo">
        <label for="q">Nombre, presentación o categoría</label>
        <input type="search" id="q" name="q" value="<?= e($q_raw) ?>" autocomplete="off">
    </div>
    <div class="fila">
        <button type="submit" class="boton">Buscar</button>
        <?php if ($q_raw !== ''): ?><a class="boton boton-sec" href="inventario.php">Ver todos los productos</a><?php endif; ?>
    </div>
</form>

<section id="entrada" class="tarjeta">
    <h2 class="titulo-icono"><?= icono('camion') ?> Entrada de mercancía</h2>
    <p class="suave">Suma unidades a un lote. Si el código no existe, se crea el lote.</p>
    <?php if ($error): ?>
        <div class="aviso aviso-error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" class="formulario" autocomplete="off">
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
                       value="<?= e($_POST['codigo'] ?? '') ?>" aria-describedby="ayuda-codigo">
                <span class="ayuda" id="ayuda-codigo">Escribe el código del vial o del certificado de análisis.</span>
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
                <label for="nota">Nota (opcional)</label>
                <input type="text" id="nota" name="nota" maxlength="255" value="<?= e($_POST['nota'] ?? '') ?>">
            </div>
        </div>
        <div><button type="submit" class="boton">Guardar entrada</button></div>
    </form>
</section>

<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('inventario') ?> Productos</h2>
    <?php if (!$productos): ?>
        <p class="suave">
            <?= $q_raw !== ''
                ? 'No encontramos productos con «' . e($q_raw) . '». Prueba con otro nombre.'
                : 'Todavía no hay productos. Agrega los del catálogo.' ?>
        </p>
        <?php if ($q_raw !== ''): ?>
            <a class="boton boton-sec" href="inventario.php">Quitar búsqueda</a>
        <?php endif; ?>
    <?php else: ?>
        <table class="tabla tabla-movil">
            <thead>
                <tr><th>Producto</th><th>Categoría</th><th>Stock vigente</th><th>Mínimo</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($productos as $p): ?>
                <tr>
                    <td>
                        <a class="boton boton-sec" href="producto.php?id=<?= (int) $p['id'] ?>"><?= e($p['nombre']) ?> · <?= e($p['presentacion']) ?></a>
                    </td>
                    <td><span class="suave">Categoría</span> <?= e($p['categoria']) ?></td>
                    <td><span class="suave">Stock vigente</span> <?= (int) $p['stock'] ?></td>
                    <td><span class="suave">Mínimo</span> <?= (int) $p['stock_minimo'] ?></td>
                    <td>
                        <?php if ($p['estado'] === 'ok'): ?>
                            <span class="etiqueta etiqueta-ok">Disponible</span>
                        <?php elseif ($p['estado'] === 'bajo'): ?>
                            <span class="etiqueta etiqueta-alerta">Stock bajo</span>
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
