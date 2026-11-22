<?php
/** Clientes: lista de clientes activos con buscador por nombre o teléfono. */
require __DIR__ . '/includes/app.php';
requerir_permiso('clientes');

require __DIR__ . '/includes/clientes.php';

$q = trim((string) ($_GET['q'] ?? ''));
$clientes = clientes_buscar($q);

$titulo = 'Clientes';
$activo = 'clientes';
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <div>
        <h1>Clientes</h1>
        <p class="suave">Consulta las fichas y los procesos de tus clientes.</p>
    </div>
    <a class="boton" href="cliente.php?nuevo=1">Nuevo cliente</a>
</div>

<form method="get" action="clientes.php" class="tarjeta formulario">
    <h2>Busca un cliente</h2>
    <div class="campo">
        <label for="q">Nombre o teléfono</label>
        <div class="fila">
            <input type="search" id="q" name="q" value="<?= e($q) ?>"
                   autocomplete="off" enterkeyhint="search">
            <button type="submit" class="boton">Buscar</button>
        </div>
    </div>
    <?php if ($q !== ''): ?>
        <p class="suave">Resultados para «<?= e($q) ?>».</p>
        <div><a class="boton boton-sec" href="clientes.php">Ver todos los clientes</a></div>
    <?php endif; ?>
</form>

<section class="tarjeta">
    <h2>Lista de clientes</h2>
    <?php if (!$clientes): ?>
        <p class="suave">
            <?= $q !== ''
                ? 'No encontramos clientes con «' . e($q) . '». Prueba con otro nombre o con el teléfono.'
                : 'Crea tu primer cliente para empezar el seguimiento.' ?>
        </p>
        <?php if ($q === ''): ?>
            <a class="boton boton-sec" href="cliente.php?nuevo=1">Crear el primer cliente</a>
        <?php endif; ?>
    <?php else: ?>
        <table class="tabla tabla-movil">
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Teléfono</th>
                    <th scope="col">Ciudad</th>
                    <th scope="col">Casos activos</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
                <tr>
                    <td>
                        <a class="boton boton-texto" href="cliente.php?id=<?= (int) $c['id'] ?>"><strong><?= e($c['nombre']) ?></strong></a>
                        <div class="acciones-fila">
                            <?php if (puede('pedidos')): ?>
                                <a class="boton boton-sec" href="pedido.php?nuevo=1&amp;cliente=<?= (int) $c['id'] ?>"
                                   aria-label="Crear pedido para <?= e($c['nombre']) ?>"><?= icono('pedidos') ?> Crear pedido</a>
                            <?php endif; ?>
                            <?php if (puede('casos')): ?>
                                <a class="boton boton-sec" href="caso.php?nuevo=1&amp;cliente=<?= (int) $c['id'] ?>"
                                   aria-label="Crear proceso para <?= e($c['nombre']) ?>"><?= icono('plan') ?> Crear proceso</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><span class="solo-movil">Teléfono</span> <?= e($c['telefono']) ?></td>
                    <td><span class="solo-movil">Ciudad</span> <?= e($c['ciudad'] ?? '') ?: '<span class="suave">sin registrar</span>' ?></td>
                    <td>
                        <?php $n = (int) $c['casos_activos']; ?>
                        <?php if ($n > 0): ?>
                            <span class="etiqueta etiqueta-ok"><?= $n ?> <?= $n === 1 ? 'caso activo' : 'casos activos' ?></span>
                        <?php else: ?>
                            <span class="etiqueta">Sin casos activos</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="suave"><?= count($clientes) ?> <?= count($clientes) === 1 ? 'cliente' : 'clientes' ?>.</p>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
