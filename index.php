<?php
/** Inicio: cifras y pendientes del día. */
require __DIR__ . '/includes/app.php';
$u = requerir_login();

$fecha_hoy = hoy();
$ver_controles = puede('casos');
$ver_pedidos = puede('pedidos');
$ver_inventario = puede('inventario');

$st = db()->prepare('SELECT COUNT(*) FROM clientes WHERE activo = 1');
$st->execute();
$clientes = (int) $st->fetchColumn();
$st = db()->prepare('SELECT COUNT(*) FROM casos WHERE estado = ?');
$st->execute(['activo']);
$casos = (int) $st->fetchColumn();

$total_controles = '—';
if ($ver_controles) {
    $controles_pendientes = "FROM casos c JOIN clientes cl ON cl.id = c.cliente_id
                             WHERE c.estado = 'activo' AND c.proximo_contacto <= ?";
    $st = db()->prepare('SELECT COUNT(*) ' . $controles_pendientes);
    $st->execute([$fecha_hoy]);
    $total_controles = (int) $st->fetchColumn();
    $st = db()->prepare('SELECT c.id, c.titulo, c.proximo_contacto, cl.nombre ' . $controles_pendientes
        . ' ORDER BY c.proximo_contacto ASC, c.id ASC LIMIT 5');
    $st->execute([$fecha_hoy]);
    $por_contactar = $st->fetchAll();
}

if ($ver_pedidos) {
    require_once __DIR__ . '/includes/pedidos.php';
    $por_despachar = array_slice(pedidos_listar(['estado' => 'confirmado']), 0, 5);
    $filtro_despacho = ['estado' => 'confirmado'];
    if (count($por_despachar) < 5) {
        $filtro_cotizados = ['estado' => 'cotizado', 'estado_pago' => 'pagado'];
        $cotizados_pagados = pedidos_listar($filtro_cotizados);
        if (!$por_despachar && $cotizados_pagados) {
            $filtro_despacho = $filtro_cotizados;
        }
        array_push($por_despachar, ...array_slice($cotizados_pagados, 0, 5 - count($por_despachar)));
    }
}

if ($ver_inventario) {
    require_once __DIR__ . '/includes/inventario.php';
    $stock_bajo = inventario_alertas()['stock_bajo'];
    usort($stock_bajo, fn($a, $b) => $a['stock'] <=> $b['stock']);
    $stock_bajo = array_slice($stock_bajo, 0, 5);
}

$titulo = 'Inicio';
$activo = 'inicio';
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado portada">
    <div>
        <h1>Hola, <?= e(primer_nombre($u['nombre'])) ?></h1>
        <p class="suave"><?= e(fecha_corta($fecha_hoy)) ?></p>
    </div>
    <?php if (puede('clientes') && is_file(RAIZ . '/cliente.php')): ?>
        <a class="boton" href="cliente.php?nuevo=1">Nuevo cliente</a>
    <?php endif; ?>
</div>

<div class="rejilla rejilla-cifras">
    <div class="tarjeta"><div class="suave">Clientes activos</div><div class="cifra"><?= e($clientes) ?></div></div>
    <div class="tarjeta"><div class="suave">Procesos en curso</div><div class="cifra"><?= e($casos) ?></div></div>
    <div class="tarjeta"><div class="suave">Controles pendientes</div><div class="cifra"><?= e($total_controles) ?></div></div>
</div>

<div class="rejilla">
<?php if ($ver_controles): ?>
<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('calendario') ?> Controles de hoy</h2>
    <?php if (!$por_contactar): ?>
        <p class="suave">No hay contactos pendientes para hoy.</p>
    <?php else: ?>
        <ul class="lista">
        <?php foreach ($por_contactar as $c): ?>
            <li>
                <a class="boton" href="caso.php?id=<?= e($c['id']) ?>"><strong><?= e($c['nombre']) ?></strong></a>
                · <?= e($c['titulo']) ?>
                <?php if ($c['proximo_contacto'] < $fecha_hoy): ?>
                    <span class="etiqueta etiqueta-alerta">Atrasado</span>
                <?php else: ?>
                    <span class="etiqueta">Hoy</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <a class="boton boton-sec" href="clientes.php">Ver clientes</a>
</section>
<?php endif; ?>

<?php if ($ver_pedidos): ?>
<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('camion') ?> Pedidos por despachar</h2>
    <?php if (!$por_despachar): ?>
        <p class="suave">No hay pedidos por despachar.</p>
    <?php else: ?>
        <ul class="lista">
        <?php foreach ($por_despachar as $pedido): ?>
            <li>
                <a class="boton" href="pedido.php?id=<?= e($pedido['id']) ?>">Pedido #<?= e($pedido['id']) ?></a>
                · <?= e($pedido['cliente']) ?> · <?= e(dinero($pedido['total'])) ?>
                <span class="etiqueta"><?= e(PEDIDO_ESTADOS[$pedido['estado']]) ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <a class="boton boton-sec" href="pedidos.php?<?= e(http_build_query($filtro_despacho)) ?>">Ver pedidos</a>
</section>
<?php endif; ?>

<?php if ($ver_inventario): ?>
<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('alerta') ?> Productos agotados o con stock bajo</h2>
    <?php if (!$stock_bajo): ?>
        <p class="suave">No hay productos agotados ni con stock bajo.</p>
    <?php else: ?>
        <ul class="lista">
        <?php foreach ($stock_bajo as $producto): ?>
            <li>
                <strong><?= e(trim($producto['nombre'] . ' ' . $producto['presentacion'])) ?></strong>
                · <?= e($producto['stock']) ?> <?= $producto['stock'] === 1 ? 'unidad' : 'unidades' ?>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <a class="boton boton-sec" href="inventario.php">Ver inventario</a>
</section>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
