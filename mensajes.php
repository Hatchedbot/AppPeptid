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
<div class="encabezado">
    <div>
        <h1>Mensajes</h1>
        <p class="suave">Crea plantillas y envía mensajes por WhatsApp.</p>
    </div>
    <div class="fila">
        <a class="boton boton-sec" href="campana.php?nuevo=1&amp;individual=1">Escribir a un cliente</a>
        <a class="boton" href="campana.php?nuevo=1">Nueva campaña</a>
    </div>
</div>

<section class="tarjeta" aria-labelledby="campana-titulo">
    <h2 id="campana-titulo">Campañas anteriores</h2>
    <?php if (!$campanas): ?>
        <p class="suave">Todavía no hay campañas. Crea la primera para empezar.</p>
        <a class="boton" href="campana.php?nuevo=1">Nueva campaña</a>
    <?php else: ?>
        <table class="tabla tabla-movil">
            <thead>
                <tr><th>Campaña</th><th>Fecha</th><th>Avance</th></tr>
            </thead>
            <tbody>
            <?php foreach ($campanas as $campana): ?>
                <tr>
                    <td>
                        <a class="boton boton-sec" href="campana.php?id=<?= e($campana['id']) ?>"><?= e($campana['nombre']) ?></a>
                        <div class="suave"><?= e($campana['filtro']) ?></div>
                    </td>
                    <td><span class="suave">Fecha:</span> <?= e(fecha_corta($campana['creado'])) ?></td>
                    <td><?= e($campana['enviados']) ?> enviados · <?= e($campana['pendientes']) ?> pendientes · <?= e($campana['omitidos']) ?> omitidos</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="tarjeta" aria-labelledby="plantillas-titulo">
    <h2 id="plantillas-titulo">Plantillas</h2>
    <?php if (!$plantillas): ?>
        <p class="suave">Todavía no hay plantillas. Crea la primera más abajo.</p>
        <a class="boton boton-sec" href="#plantilla">Ir al formulario de plantillas</a>
    <?php else: ?>
        <ul class="lista">
            <?php foreach ($plantillas as $fila): ?>
                <li>
                    <strong><?= e($fila['nombre']) ?></strong>
                    <?= $fila['activa']
                        ? '<span class="etiqueta etiqueta-ok">Activa</span>'
                        : '<span class="etiqueta etiqueta-alerta">Inactiva</span>' ?>
                    <p><?= nl2br(e($fila['texto'])) ?></p>
                    <form method="post" action="mensajes.php" class="formulario">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="id" value="<?= e($fila['id']) ?>">
                        <div class="fila">
                            <a class="boton boton-sec" href="mensajes.php?editar=<?= e($fila['id']) ?>#plantilla"
                               aria-label="Editar plantilla <?= e($fila['nombre']) ?>">Editar</a>
                            <?php if ($fila['activa']): ?>
                                <button type="submit" class="boton boton-peligro" name="accion" value="desactivar"
                                        onclick="return confirm('¿Desactivar esta plantilla?');">Desactivar</button>
                            <?php else: ?>
                                <button type="submit" class="boton boton-sec" name="accion" value="activar">Activar</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<form method="post" class="tarjeta formulario" action="mensajes.php" id="plantilla" aria-labelledby="plantilla-titulo">
    <?= campo_csrf() ?>
    <input type="hidden" name="id" value="<?= e($plantilla['id'] ?? 0) ?>">
    <h2 id="plantilla-titulo"><?= $plantilla ? 'Editar plantilla' : 'Nueva plantilla' ?></h2>
    <div class="campo">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" maxlength="80" required autocomplete="off"
               aria-describedby="ayuda-nombre" value="<?= e($plantilla['nombre'] ?? '') ?>">
        <span class="ayuda" id="ayuda-nombre">Un nombre corto para identificarla.</span>
    </div>
    <div class="campo">
        <label for="texto">Texto</label>
        <textarea id="texto" name="texto" rows="4" maxlength="4000" required
                  aria-describedby="ayuda-texto"><?= e($plantilla['texto'] ?? '') ?></textarea>
        <span class="ayuda" id="ayuda-texto">Marcadores disponibles: {nombre}, {producto}, {guia}, {transportadora}, {total}, {enlace} y {fecha}.</span>
    </div>
    <div class="fila">
        <button type="submit" class="boton" name="accion" value="guardar">Guardar plantilla</button>
        <?php if ($plantilla): ?><a class="boton boton-sec" href="mensajes.php#plantilla">Cancelar edición</a><?php endif; ?>
    </div>
</form>
<?php require __DIR__ . '/includes/footer.php';
