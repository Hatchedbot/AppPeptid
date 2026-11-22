<?php
/**
 * Ficha de un producto: lotes con vencimiento y unidades, movimientos recientes,
 * ajuste de inventario y edición del precio y el stock mínimo.
 *
 * Las funciones del módulo están en includes/inventario.php.
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

$id = (int) ($_GET['id'] ?? 0);
$producto = inventario_producto_obtener($id);

if (!$producto) {
    http_response_code(404);
    $titulo = 'Producto no encontrado';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="encabezado">
        <div>
            <h1>No encontramos ese producto</h1>
            <p class="suave">Busca el producto por nombre en el inventario.</p>
        </div>
        <a class="boton" href="inventario.php">Volver al inventario</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    return;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');
    try {
        if ($accion === 'ajuste') {
            $lote_id = (int) ($_POST['lote_id'] ?? 0);
            $lote = inventario_lote_obtener($lote_id);
            if (!$lote || (int) $lote['producto_id'] !== $id) {
                throw new InvalidArgumentException('Elige un lote de este producto.');
            }
            $cantidad = numero_entrada($_POST['cantidad'] ?? null);
            if ($cantidad === null || $cantidad === 0 || floor($cantidad) != $cantidad) {
                throw new InvalidArgumentException('La cantidad del ajuste debe ser un número entero distinto de cero.');
            }
            inventario_ajustar($lote_id, (int) $cantidad, (string) ($_POST['motivo'] ?? ''));
            avisar('ok', 'Ajuste guardado.');
        } elseif ($accion === 'guardar') {
            inventario_actualizar_producto($id, [
                'stock_minimo' => $_POST['stock_minimo'] ?? '',
                'precio'       => $_POST['precio'] ?? '',
            ]);
            avisar('ok', 'Guardaste el precio y el stock mínimo.');
        } else {
            throw new InvalidArgumentException('Acción no reconocida.');
        }
    } catch (InvalidArgumentException $ex) {
        $error = $ex->getMessage();
    }
    if ($error !== '') {
        avisar('error', $error);
    }
    redirigir('producto.php?id=' . $id);
}

$lotes = inventario_lotes($id);
$movimientos = inventario_movimientos($id, 30);
$titulo = $producto['nombre'] . ' · ' . $producto['presentacion'];
$activo = 'inventario';
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <div>
        <h1><?= e($producto['nombre']) ?> · <?= e($producto['presentacion']) ?></h1>
        <p class="suave">Categoría: <?= e($producto['categoria']) ?> · Precio: <?= e(dinero($producto['precio'])) ?></p>
    </div>
    <a class="boton boton-sec" href="inventario.php">Volver al inventario</a>
</div>

<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('inventario') ?> Lotes</h2>
    <?php if (!$lotes): ?>
        <p class="suave">Este producto todavía no tiene lotes. Registra una entrada desde el inventario.</p>
        <a class="boton" href="inventario.php#entrada">Registrar entrada</a>
    <?php else: ?>
        <table class="tabla tabla-movil">
            <thead>
                <tr><th>Código</th><th>Vencimiento</th><th>Unidades</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($lotes as $l): ?>
                <?php
                    $venc = $l['vencimiento'] ?? null;
                    $clase = 'etiqueta';
                    $texto = 'Sin fecha';
                    if ($venc !== null) {
                        $limite = date('Y-m-d', strtotime('+' . DIAS_POR_VENCER . ' days'));
                        if ($venc < hoy()) {
                            $clase = 'etiqueta-error';
                            $texto = 'Vencido';
                        } elseif ($venc <= $limite) {
                            $clase = 'etiqueta-alerta';
                            $texto = 'Por vencer';
                        } else {
                            $clase = 'etiqueta-ok';
                            $texto = 'Vigente';
                        }
                    }
                ?>
                <tr>
                    <td><strong><?= e($l['codigo']) ?></strong></td>
                    <td><span class="solo-movil">Vencimiento</span> <?= $venc !== null ? e(fecha_corta((string) $venc)) : 'Sin fecha' ?></td>
                    <td><?= (int) $l['stock'] ?> unidades</td>
                    <td><span class="etiqueta <?= $clase ?>"><?= $texto ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('peso') ?> Ajuste de inventario</h2>
    <p class="suave">Suma o resta unidades de un lote. El motivo es obligatorio.</p>
    <form method="post" class="formulario" autocomplete="off">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="ajuste">
        <div class="campo">
            <label for="lote_id">Lote *</label>
            <select id="lote_id" name="lote_id" required>
                <option value="">Elige un lote</option>
                <?php foreach ($lotes as $l): ?>
                    <option value="<?= (int) $l['id'] ?>">
                        <?= e($l['codigo']
                            . ($l['vencimiento'] ? ' · vence ' . fecha_corta((string) $l['vencimiento']) : '')
                            . ' · ' . (int) $l['stock'] . ' uds') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="cantidad">Cantidad *</label>
                <input type="number" id="cantidad" name="cantidad" step="1" required aria-describedby="ayuda-ajuste">
                <span class="ayuda" id="ayuda-ajuste">Usa el signo menos para restar unidades.</span>
            </div>
            <div class="campo">
                <label for="motivo">Motivo *</label>
                <input type="text" id="motivo" name="motivo" maxlength="255" required
                       placeholder="Por ejemplo: dos viales dañados">
            </div>
        </div>
        <div><button type="submit" class="boton">Guardar ajuste</button></div>
    </form>
</section>

<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('llave') ?> Precio y stock mínimo</h2>
    <form method="post" class="formulario" autocomplete="off">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar">
        <div class="fila">
            <div class="campo">
                <label for="stock_minimo">Stock mínimo *</label>
                <input type="number" id="stock_minimo" name="stock_minimo" min="0" step="1" inputmode="numeric"
                       required value="<?= (int) $producto['stock_minimo'] ?>">
            </div>
            <div class="campo">
                <label for="precio">Precio (pesos) *</label>
                <input type="number" id="precio" name="precio" min="0" step="1" inputmode="numeric"
                       required value="<?= (int) $producto['precio'] ?>">
            </div>
        </div>
        <div><button type="submit" class="boton">Guardar cambios</button></div>
    </form>
</section>

<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('calendario') ?> Movimientos recientes</h2>
    <?php if (!$movimientos): ?>
        <p class="suave">Todavía no hay movimientos. Registra una entrada para empezar.</p>
        <a class="boton boton-sec" href="inventario.php#entrada">Registrar entrada</a>
    <?php else: ?>
        <table class="tabla tabla-movil">
            <thead>
                <tr><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>Lote</th><th>Pedido</th><th>Usuario</th><th>Nota</th></tr>
            </thead>
            <tbody>
            <?php foreach ($movimientos as $m): ?>
                <tr>
                    <td><span class="solo-movil">Fecha</span> <?= e(fecha_corta((string) $m['fecha'])) ?></td>
                    <td><span class="etiqueta"><?= e(TIPOS_MOVIMIENTO[$m['tipo']] ?? $m['tipo']) ?></span></td>
                    <td><?= (int) $m['cantidad'] > 0 ? '+' : '' ?><?= (int) $m['cantidad'] ?> unidades</td>
                    <td><span class="suave">Lote</span> <?= e($m['lote_codigo']) ?></td>
                    <td>
                        <?php if ($m['pedido_id'] && is_file(RAIZ . '/pedido.php')): ?>
                            <a class="boton boton-sec" href="pedido.php?id=<?= (int) $m['pedido_id'] ?>">Pedido #<?= (int) $m['pedido_id'] ?></a>
                        <?php elseif ($m['pedido_id']): ?>
                            Pedido #<?= (int) $m['pedido_id'] ?>
                        <?php else: ?>
                            <span class="suave">Sin pedido</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="suave">Usuario</span> <?= e($m['usuario_nombre'] ?? '') ?: 'Sin registro' ?></td>
                    <td><span class="suave">Nota</span> <?= e($m['nota'] ?? '') ?: 'Sin nota' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
