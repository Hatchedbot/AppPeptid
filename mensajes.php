<?php
require __DIR__ . '/includes/app.php';
requerir_permiso('mensajes');
require_once __DIR__ . '/includes/mensajes.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';
    $ruta = 'mensajes.php';
    try {
        if ($accion === 'guardar') {
            $nombre = (string) ($_POST['nombre'] ?? '');
            $texto = (string) ($_POST['texto'] ?? '');
            if ($id) {
                $ruta = 'mensajes.php?editar=' . $id . '#plantilla';
                plantilla_actualizar($id, $nombre, $texto);
                $ruta = 'mensajes.php#plantilla';
            } else {
                plantilla_crear($nombre, $texto);
            }
            avisar('ok', 'Plantilla guardada.');
        } elseif (in_array($accion, ['activar', 'desactivar'], true)) {
            plantilla_cambiar_activa($id, $accion === 'activar');
            avisar('ok', 'Estado de la plantilla actualizado.');
        } else {
            throw new InvalidArgumentException('Acción no reconocida.');
        }
    } catch (InvalidArgumentException $ex) {
        avisar('error', $ex->getMessage());
    }
    redirigir($ruta);
}

$plantilla = plantilla_obtener((int) ($_GET['editar'] ?? 0));
$plantillas = plantillas_listar();
$campanas = campanas_listar();
$titulo = 'Mensajes';
$activo = 'mensajes';
require __DIR__ . '/includes/header.php';
?>
<h1>Mensajes</h1>
<div class="fila">
    <a class="boton" href="campana.php?nuevo=1">Nueva campaña</a>
    <a class="boton boton-sec" href="campana.php?nuevo=1&amp;individual=1">Escribir a un cliente</a>
</div>

<h2>Campañas anteriores</h2>
<div class="tarjeta">
    <?php if (!$campanas): ?>
        <p class="suave">Todavía no hay campañas.</p>
    <?php else: ?>
        <table class="tabla tabla-movil">
            <thead><tr><th>Campaña</th><th>Fecha</th><th>Avance</th></tr></thead>
            <tbody>
            <?php foreach ($campanas as $campana): ?>
                <tr>
                    <td>
                        <a href="campana.php?id=<?= e($campana['id']) ?>"><?= e($campana['nombre']) ?></a>
                        <div class="suave"><?= e($campana['filtro']) ?></div>
                    </td>
                    <td><?= e(fecha_corta($campana['creado'])) ?></td>
                    <td><?= e($campana['enviados']) ?> enviados, <?= e($campana['pendientes']) ?> pendientes,
                        <?= e($campana['omitidos']) ?> omitidos</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<h2>Plantillas</h2>
<?php foreach ($plantillas as $fila): ?>
    <article class="tarjeta">
        <h3><?= e($fila['nombre']) ?></h3>
        <p><?= nl2br(e($fila['texto'])) ?></p>
        <p class="suave"><?= $fila['activa'] ? 'Activa' : 'Inactiva' ?></p>
        <form method="post" class="formulario" action="mensajes.php">
            <?= campo_csrf() ?>
            <input type="hidden" name="id" value="<?= e($fila['id']) ?>">
            <div class="fila">
                <a class="boton boton-sec" href="mensajes.php?editar=<?= e($fila['id']) ?>#plantilla">Editar</a>
                <button class="boton boton-sec" name="accion" value="<?= $fila['activa'] ? 'desactivar' : 'activar' ?>">
                    <?= $fila['activa'] ? 'Desactivar' : 'Activar' ?>
                </button>
            </div>
        </form>
    </article>
<?php endforeach; ?>

<form method="post" class="tarjeta formulario" action="mensajes.php" id="plantilla">
    <?= campo_csrf() ?>
    <input type="hidden" name="id" value="<?= e($plantilla['id'] ?? 0) ?>">
    <h3><?= $plantilla ? 'Editar plantilla' : 'Nueva plantilla' ?></h3>
    <div class="campo">
        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" maxlength="80" required value="<?= e($plantilla['nombre'] ?? '') ?>">
    </div>
    <div class="campo">
        <label for="texto">Texto</label>
        <textarea id="texto" name="texto" rows="4" maxlength="4000" required><?= e($plantilla['texto'] ?? '') ?></textarea>
        <small>Marcadores disponibles: {nombre}, {producto}, {guia}, {transportadora}, {total}, {enlace} y {fecha}.</small>
    </div>
    <div class="fila">
        <button class="boton" name="accion" value="guardar">Guardar plantilla</button>
        <?php if ($plantilla): ?><a class="boton boton-sec" href="mensajes.php#plantilla">Cancelar edición</a><?php endif; ?>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php';
