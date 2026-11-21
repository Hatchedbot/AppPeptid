<?php
/** Inicio: cifras del día y a quién hay que contactar hoy. */
require __DIR__ . '/includes/app.php';
$u = requerir_login();

$clientes = (int) db()->query('SELECT COUNT(*) FROM clientes WHERE activo = 1')->fetchColumn();
$casos = (int) db()->query("SELECT COUNT(*) FROM casos WHERE estado = 'activo'")->fetchColumn();
$st = db()->prepare("SELECT c.id, c.titulo, c.proximo_contacto, cl.id AS cliente_id, cl.nombre
                     FROM casos c JOIN clientes cl ON cl.id = c.cliente_id
                     WHERE c.estado = 'activo' AND c.proximo_contacto IS NOT NULL AND c.proximo_contacto <= ?
                     ORDER BY c.proximo_contacto ASC LIMIT 50");
$st->execute([hoy()]);
$por_contactar = $st->fetchAll();

$titulo = 'Inicio';
$activo = 'inicio';
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <div>
        <h1>Hola, <?= e(primer_nombre($u['nombre'])) ?></h1>
        <p class="suave"><?= e(fecha_corta(hoy())) ?></p>
    </div>
    <?php if (is_file(RAIZ . '/cliente.php')): ?>
        <a class="boton" href="cliente.php?nuevo=1">Nuevo cliente</a>
    <?php endif; ?>
</div>

<div class="rejilla">
    <div class="tarjeta"><div class="suave">Clientes activos</div><div class="cifra"><?= $clientes ?></div></div>
    <div class="tarjeta"><div class="suave">Procesos en curso</div><div class="cifra"><?= $casos ?></div></div>
    <div class="tarjeta"><div class="suave">Por contactar hoy</div><div class="cifra"><?= count($por_contactar) ?></div></div>
</div>

<section class="tarjeta">
    <h2>Por contactar</h2>
    <?php if (!$por_contactar): ?>
        <p class="suave">No hay contactos pendientes para hoy.</p>
    <?php else: ?>
        <ul class="lista">
        <?php foreach ($por_contactar as $c): ?>
            <li>
                <a href="caso.php?id=<?= (int) $c['id'] ?>"><strong><?= e($c['nombre']) ?></strong></a>
                · <?= e($c['titulo']) ?>
                <?php if ($c['proximo_contacto'] < hoy()): ?>
                    <span class="etiqueta etiqueta-alerta">Atrasado desde <?= e(fecha_corta($c['proximo_contacto'])) ?></span>
                <?php else: ?>
                    <span class="etiqueta">Hoy</span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
