<?php
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/pedidos.php';

requerir_permiso('pedidos');
$estado = (string) ($_GET['estado'] ?? '');
$pago = (string) ($_GET['estado_pago'] ?? '');
$filtros = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'estado_pago' => isset(PEDIDO_ESTADOS_PAGO[$pago]) ? $pago : '',
    'cliente_id' => max(0, (int) ($_GET['cliente_id'] ?? 0)),
];
$pedidos = pedidos_listar($filtros);
$conteos = array_count_values(array_column($pedidos, 'estado'));
$titulo = 'Pedidos';
$activo = 'pedidos';
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <h1>Pedidos</h1>
    <a class="boton" href="pedido.php?<?= e(http_build_query(['nuevo' => 1, 'cliente' => $filtros['cliente_id']])) ?>">Nuevo pedido</a>
</div>
<form method="get" action="pedidos.php" class="tarjeta formulario">
    <input type="hidden" name="estado" value="<?= e($estado) ?>">
    <input type="hidden" name="cliente_id" value="<?= e($filtros['cliente_id']) ?>">
    <div class="fila">
        <div class="campo">
            <label for="q">Buscar por cliente, número de pedido o guía</label>
            <input id="q" name="q" type="search" value="<?= e($filtros['q']) ?>">
        </div>
        <div class="campo">
            <label for="estado_pago">Estado del pago</label>
            <select id="estado_pago" name="estado_pago">
                <option value="">Todos</option>
                <?php foreach (PEDIDO_ESTADOS_PAGO as $clave => $nombre): ?>
                    <option value="<?= e($clave) ?>" <?= $pago === $clave ? 'selected' : '' ?>><?= e($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button class="boton">Buscar</button>
    <?php if (array_filter($filtros)): ?><a href="pedidos.php">Quitar filtros</a><?php endif; ?>
</form>
<nav class="tarjeta fila" aria-label="Estados de los pedidos">
    <?php foreach (['' => 'Todos'] + PEDIDO_ESTADOS as $clave => $nombre): ?>
        <a class="boton <?= $estado === $clave ? '' : 'boton-sec' ?>"
           <?= $estado === $clave ? 'aria-current="page"' : '' ?>
           href="pedidos.php?<?= e(http_build_query(['estado' => $clave] + $filtros)) ?>">
            <?= e($nombre) ?> (<?= e($clave === '' ? count($pedidos) : ($conteos[$clave] ?? 0)) ?>)
        </a>
    <?php endforeach; ?>
</nav>
<section class="tarjeta" aria-label="Resultados">
    <?php if (isset(PEDIDO_ESTADOS[$estado])): ?>
        <?php $pedidos = array_filter($pedidos, fn($pedido) => $pedido['estado'] === $estado); ?>
    <?php endif; ?>
    <?php if (!$pedidos): ?>
        <p class="suave">No hay pedidos que coincidan.</p>
    <?php else: ?>
        <table class="tabla tabla-movil">
            <thead><tr><th>Pedido y cliente</th><th>Total</th><th>Estado</th><th>Pago</th><th>Fecha</th></tr></thead>
            <tbody>
            <?php foreach ($pedidos as $pedido): ?>
                <tr>
                    <td><a href="pedido.php?id=<?= e($pedido['id']) ?>">#<?= e($pedido['id']) ?> · <?= e($pedido['cliente']) ?></a></td>
                    <td><?= e(dinero($pedido['total'])) ?></td>
                    <td><?= e(PEDIDO_ESTADOS[$pedido['estado']]) ?></td>
                    <td>Pago <?= e(mb_strtolower(PEDIDO_ESTADOS_PAGO[$pedido['estado_pago']])) ?></td>
                    <td><?= e(fecha_corta($pedido['creado'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
