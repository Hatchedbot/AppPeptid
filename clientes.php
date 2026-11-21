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
        <p class="suave">Toca un nombre para ver su ficha y su historial.</p>
    </div>
    <a class="boton" href="cliente.php?nuevo=1">Nuevo cliente</a>
</div>

<form method="get" action="clientes.php" class="tarjeta">
    <div class="campo">
        <label for="q">Buscar</label>
        <div class="fila">
            <input type="search" id="q" name="q" value="<?= e($q) ?>"
                   placeholder="Nombre o teléfono" autocomplete="off">
            <button type="submit" class="boton">Buscar</button>
        </div>
    </div>
    <?php if ($q !== ''): ?>
        <p class="suave">Resultados para «<?= e($q) ?>». <a href="clientes.php">Ver todos</a></p>
    <?php endif; ?>
</form>

<div class="tarjeta">
    <?php if (!$clientes): ?>
        <p class="suave">
            <?= $q !== ''
                ? 'No encontramos clientes con «' . e($q) . '». Prueba con otro nombre o con el teléfono.'
                : 'Todavía no hay clientes. Crea el primero con el botón «Nuevo cliente».' ?>
        </p>
    <?php else: ?>
        <table class="tabla tabla-movil">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Ciudad</th>
                    <th>Casos activos</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><a href="cliente.php?id=<?= (int) $c['id'] ?>"><strong><?= e($c['nombre']) ?></strong></a></td>
                    <td><?= e($c['telefono']) ?></td>
                    <td><?= e($c['ciudad'] ?? '') ?: '<span class="suave">—</span>' ?></td>
                    <td>
                        <?php $n = (int) $c['casos_activos']; ?>
                        <?php if ($n > 0): ?>
                            <span class="etiqueta etiqueta-ok"><?= $n ?></span>
                        <?php else: ?>
                            <span class="suave">0</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="suave"><?= count($clientes) ?> <?= count($clientes) === 1 ? 'cliente' : 'clientes' ?>.</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php';
