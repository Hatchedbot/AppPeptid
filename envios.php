<?php
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/pedidos.php';

requerir_permiso('pedidos');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $id = (int) filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    try {
        switch ($_POST['accion'] ?? '') {
            case 'despachar':
                pedido_cambiar_estado($id, 'despachado', $_POST);
                avisar('ok', 'Guardaste la guía y despachaste el pedido.');
                break;
            case 'entregar':
                pedido_cambiar_estado($id, 'entregado');
                avisar('ok', 'Marcaste el pedido como entregado.');
                break;
            default:
                throw new InvalidArgumentException('Selecciona una acción válida.');
        }
    } catch (InvalidArgumentException $ex) {
        avisar('error', $ex->getMessage());
    }
    redirigir('envios.php');
}

$q = trim(filter_input(INPUT_GET, 'q') ?? '');
$grupos = [
    'sin-guia' => ['Sin guía', 'camion', envios_sin_guia($q), 'Por ahora no tienes pedidos listos para enviar.'],
    'esperando-pago' => ['Esperando pago', 'alerta', envios_esperando_pago($q), 'No tienes pedidos esperando pago para salir.'],
    'en-camino' => ['En camino', 'camion', envios_en_camino($q), 'Por ahora no tienes pedidos en camino.'],
];
$titulo = 'Envíos';
$activo = 'pedidos';
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <h1>Envíos</h1>
    <a class="boton boton-sec" href="pedidos.php">Volver a pedidos</a>
</div>
<form method="get" action="envios.php" class="tarjeta formulario">
    <div class="campo">
        <label for="q">Cliente o número de guía</label>
        <input id="q" name="q" type="search" autocomplete="off" value="<?= e($q) ?>">
    </div>
    <div class="acciones-fila">
        <button class="boton"><?= icono('buscar') ?> Buscar</button>
        <?php if ($q !== ''): ?>
            <a class="boton boton-sec" href="envios.php">Limpiar búsqueda</a>
        <?php endif; ?>
    </div>
</form>
<?php foreach ($grupos as $grupo => [$nombre, $icono, $pedidos, $vacio]): ?>
    <section class="tarjeta">
        <h2 class="titulo-icono"><?= icono($icono) ?> <?= e($nombre) ?> (<?= e(count($pedidos)) ?>)<?= $grupo === 'sin-guia' ? ' — listos para enviar' : '' ?></h2>
        <?php if (!$pedidos): ?>
            <p class="suave"><?= e($q === '' ? $vacio : 'No encontramos pedidos de este grupo con esa búsqueda.') ?></p>
            <?php if ($q === ''): ?>
                <a class="boton boton-sec" href="pedidos.php">Ver pedidos</a>
            <?php else: ?>
                <a class="boton boton-sec" href="envios.php">Ver todos los envíos</a>
            <?php endif; ?>
        <?php else: ?>
            <table class="tabla tabla-movil">
                <thead>
                    <tr><th>Pedido y cliente</th><th>Total</th><th>Destino</th><th><?= e($grupo === 'esperando-pago' ? 'Pago pendiente' : 'Guía y despacho') ?></th></tr>
                </thead>
                <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                    <?php
                    $id = (int) $pedido['id'];
                    $destino = implode(', ', array_filter([$pedido['direccion'], $pedido['ciudad']],
                        fn($parte) => $parte !== null && $parte !== ''));
                    $saldo = max(0, (int) $pedido['total'] - (int) $pedido['pagado']);
                    ?>
                    <tr>
                        <td><a class="boton boton-sec" href="pedido.php?id=<?= e($id) ?>">Pedido #<?= e($id) ?> · <?= e($pedido['cliente']) ?></a></td>
                        <td><span class="solo-movil">Total</span> <?= e(dinero($pedido['total'])) ?></td>
                        <td><span class="solo-movil">Destino</span> <?= e($destino !== '' ? $destino : 'Sin destino registrado') ?></td>
                        <td>
                            <?php if ($grupo === 'sin-guia'): ?>
                                <form method="post" action="envios.php" class="formulario">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="id" value="<?= e($id) ?>">
                                    <div class="campo">
                                        <label for="transportadora-<?= e($id) ?>">Transportadora</label>
                                        <input id="transportadora-<?= e($id) ?>" name="transportadora" maxlength="80" required>
                                    </div>
                                    <div class="campo">
                                        <label for="guia-<?= e($id) ?>">Número de guía</label>
                                        <input id="guia-<?= e($id) ?>" name="guia" maxlength="80" required>
                                    </div>
                                    <button class="boton" name="accion" value="despachar">Guardar guía y despachar</button>
                                </form>
                            <?php elseif ($grupo === 'esperando-pago'): ?>
                                <span class="etiqueta etiqueta-alerta">Faltan <?= e(dinero($saldo)) ?></span>
                                <div class="acciones-fila">
                                    <a class="boton boton-sec" href="pedido.php?id=<?= e($id) ?>#pagos">Registrar pago</a>
                                </div>
                            <?php else: ?>
                                <div><?= e($pedido['transportadora']) ?> · Guía <?= e($pedido['guia']) ?></div>
                                <form method="post" action="envios.php" class="acciones-fila">
                                    <?= campo_csrf() ?>
                                    <input type="hidden" name="id" value="<?= e($id) ?>">
                                    <button class="boton" name="accion" value="entregar">Marcar entregado</button>
                                    <?php if ($pedido['contraentrega'] && $saldo > 0): ?>
                                        <a class="boton boton-sec" href="pedido.php?id=<?= e($id) ?>#pagos">Registrar pago de contraentrega</a>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
