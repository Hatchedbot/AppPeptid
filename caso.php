<?php
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/casos.php';

requerir_permiso('casos');

$nuevo = ($_GET['nuevo'] ?? '') === '1';
$caso_id = (int) ($_GET['id'] ?? 0);
$estados = ['activo' => 'Activo', 'pausado' => 'Pausado', 'terminado' => 'Terminado'];
$tipos = ['control' => 'Control', 'llamada' => 'Llamada', 'mensaje' => 'Mensaje', 'visita' => 'Visita'];
$adherencias = ['completa' => 'Completa', 'parcial' => 'Parcial', 'no_cumple' => 'No cumple'];

if ($nuevo) {
    $cliente_id = (int) ($_GET['cliente'] ?? 0);
    $st = db()->prepare('SELECT nombre FROM clientes WHERE id = ?');
    $st->execute([$cliente_id]);
    $nombre_cliente = $st->fetchColumn();
    $ruta = 'caso.php?nuevo=1&cliente=' . $cliente_id;
    $titulo = 'Nuevo caso';
} else {
    $caso = caso_obtener($caso_id);
    $nombre_cliente = $caso['nombre_cliente'] ?? null;
    $ruta = 'caso.php?id=' . $caso_id;
    $titulo = $caso['titulo'] ?? 'Caso';
}
if (!$nombre_cliente) {
    avisar('error', $nuevo ? 'Cliente no encontrado.' : 'Caso no encontrado.');
    redirigir('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destino = $ruta;
    try {
        if ($nuevo) {
            $id = caso_crear([
                'cliente_id' => $cliente_id,
                'titulo' => $_POST['titulo'] ?? '',
                'objetivo' => $_POST['objetivo'] ?? '',
                'fecha_inicio' => $_POST['fecha_inicio'] ?? '',
                'peso_inicial' => $_POST['peso_inicial'] ?? '',
                'peso_meta' => $_POST['peso_meta'] ?? '',
                'proximo_contacto' => $_POST['proximo_contacto'] ?? '',
            ]);
            $destino = 'caso.php?id=' . $id;
            $mensaje = 'Caso creado.';
        } else {
            switch ($_POST['accion'] ?? '') {
                case 'actualizar':
                    caso_actualizar($caso_id, [
                        'estado' => $_POST['estado'] ?? '',
                        'proximo_contacto' => $_POST['proximo_contacto'] ?? '',
                    ]);
                    $mensaje = 'Caso actualizado.';
                    break;
                case 'nueva_version':
                    $items = [];
                    foreach ($_POST['items'] ?? [] as $item) {
                        if (array_filter($item, static fn ($valor) => trim($valor) !== '')) {
                            $items[] = $item;
                        }
                    }
                    plan_nueva_version($caso_id, $items, $_POST['indicaciones'] ?? '', $_POST['motivo'] ?? '');
                    $mensaje = 'Nueva versión guardada.';
                    break;
                case 'seguimiento':
                    seguimiento_agregar([
                        'caso_id' => $caso_id,
                        'fecha' => $_POST['fecha'] ?? '',
                        'tipo' => $_POST['tipo'] ?? '',
                        'peso' => $_POST['peso'] ?? '',
                        'cintura' => $_POST['cintura'] ?? '',
                        'adherencia' => $_POST['adherencia'] ?? '',
                        'efectos' => $_POST['efectos'] ?? '',
                        'nota' => $_POST['nota'] ?? '',
                        'proximo_contacto' => $_POST['proximo_contacto'] ?? '',
                    ]);
                    $mensaje = 'Seguimiento guardado.';
                    break;
                case 'enviar_plan':
                    $plan = plan_actual($caso_id);
                    if (!$plan || (int) $plan['id'] !== (int) ($_POST['plan_id'] ?? 0)) {
                        throw new InvalidArgumentException('El plan cambió. Revisa la versión actual antes de compartirla.');
                    }
                    if (telefono_whatsapp($caso['telefono']) === '') {
                        throw new InvalidArgumentException('El cliente no tiene un teléfono para WhatsApp.');
                    }
                    $url = url_base() . '/plan.php?t=' . rawurlencode($plan['token']);
                    $destino = enlace_whatsapp($caso['telefono'], 'Hola ' . primer_nombre($nombre_cliente) . ', te comparto tu plan: ' . $url);
                    plan_marcar_enviado((int) $plan['id']);
                    $mensaje = 'Plan marcado como enviado. Confirma el envío en WhatsApp.';
                    break;
                default:
                    throw new InvalidArgumentException('Acción no reconocida.');
            }
        }
        avisar('ok', $mensaje);
    } catch (InvalidArgumentException $ex) {
        avisar('error', $ex->getMessage());
        $destino = $ruta;
    }
    redirigir($destino);
}

if (!$nuevo) {
    $planes = planes_del_caso($caso_id);
    $progreso = progreso_peso($caso_id);
    $seguimientos = seguimientos_del_caso($caso_id);
    $productos = db()->query('SELECT id, nombre, presentacion FROM productos WHERE activo = 1 ORDER BY nombre')->fetchAll();
}
$activo = 'clientes';
require __DIR__ . '/includes/header.php';
?>
<h1><?= e($titulo) ?></h1>
<p>Cliente: <a href="cliente.php?id=<?= e($nuevo ? $cliente_id : $caso['cliente_id']) ?>"><?= e($nombre_cliente) ?></a></p>

<?php if ($nuevo): ?>
<form method="post" class="tarjeta formulario">
    <?= campo_csrf() ?>
    <label class="campo">Título <input name="titulo" required autofocus></label>
    <label class="campo">Objetivo <textarea name="objetivo" rows="3"></textarea></label>
    <label class="campo">Fecha de inicio <input type="date" name="fecha_inicio" value="<?= e(hoy()) ?>" required></label>
    <div class="fila">
        <label class="campo">Peso inicial (kg) <input name="peso_inicial" inputmode="decimal"></label>
        <label class="campo">Peso meta (kg) <input name="peso_meta" inputmode="decimal"></label>
    </div>
    <label class="campo">Próximo contacto <input type="date" name="proximo_contacto"></label>
    <button class="boton">Crear caso</button>
</form>
<?php else: ?>
<section class="tarjeta">
    <p>Fecha de inicio: <?= e(fecha_corta($caso['fecha_inicio'])) ?></p>
    <?php if ($caso['objetivo']): ?><p>Objetivo: <?= nl2br(e($caso['objetivo'])) ?></p><?php endif; ?>
    <p>Peso meta: <?= e($caso['peso_meta'] === null ? '—' : numero_corto($caso['peso_meta']) . ' kg') ?></p>
    <p>Progreso de peso:
        <?= e($progreso['inicial'] === null ? '—' : numero_corto($progreso['inicial']) . ' kg') ?>
        →
        <?= e($progreso['ultimo'] === null ? '—' : numero_corto($progreso['ultimo']) . ' kg') ?>
        <?php if ($progreso['diferencia'] !== null): ?>
            (<?= e(numero_corto($progreso['diferencia'])) ?> kg)
        <?php endif; ?>
    </p>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="actualizar">
        <div class="fila">
            <label class="campo">Estado
                <select name="estado">
                    <?php foreach ($estados as $valor => $etiqueta): ?>
                        <option value="<?= e($valor) ?>" <?= $caso['estado'] === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="campo">Próximo contacto <input type="date" name="proximo_contacto" value="<?= e($caso['proximo_contacto']) ?>"></label>
        </div>
        <button class="boton">Actualizar caso</button>
    </form>
</section>

<section class="tarjeta">
    <h2>Plan actual e historial</h2>
    <?php if (!$planes): ?><p class="suave">Todavía no hay un plan para este caso.</p><?php endif; ?>
    <?php foreach ($planes as $indice => $plan): ?>
        <details <?= $indice === 0 ? 'open' : '' ?>>
            <summary><?= $indice === 0 ? 'Actual · ' : '' ?>Versión <?= e($plan['version']) ?> · <?= e(fecha_corta($plan['creado'])) ?></summary>
            <p>Indicaciones: <?= nl2br(e($plan['indicaciones'])) ?></p>
            <p>Motivo: <?= nl2br(e($plan['motivo_cambio'])) ?></p>
            <ul>
                <?php foreach (plan_items_del_plan((int) $plan['id']) as $item): ?>
                    <li><?= e(implode(' · ', array_filter($item, static fn ($valor) => $valor !== null && $valor !== ''))) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($plan['enviado']): ?><p>Marcado como enviado el <?= e(fecha_corta($plan['enviado'])) ?>.</p><?php endif; ?>
            <?php if ($indice === 0): ?>
                <form method="post" target="_blank">
                    <?= campo_csrf() ?>
                    <input type="hidden" name="accion" value="enviar_plan">
                    <input type="hidden" name="plan_id" value="<?= e($plan['id']) ?>">
                    <button class="boton">Enviar por WhatsApp</button>
                </form>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>
</section>

<section class="tarjeta">
    <h2>Nueva versión del plan</h2>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="nueva_version">
        <label class="campo">Indicaciones generales <textarea name="indicaciones" rows="3"></textarea></label>
        <label class="campo">Motivo del cambio <textarea name="motivo" rows="2"></textarea></label>
        <div id="items-plan"></div>
        <button type="button" class="boton boton-sec" id="agregar-item">Agregar ítem</button>
        <button class="boton">Guardar nueva versión</button>
    </form>
    <template id="plantilla-item">
        <fieldset class="formulario">
            <legend>Ítem del plan</legend>
            <div class="fila">
                <label class="campo">Producto del catálogo
                    <select data-campo="producto_id">
                        <option value="">Selecciona un producto</option>
                        <?php foreach ($productos as $producto): ?>
                            <option value="<?= e($producto['id']) ?>"><?= e($producto['nombre'] . ' · ' . $producto['presentacion']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="campo">Producto en texto libre <input data-campo="producto_nombre"></label>
            </div>
            <div class="fila">
                <label class="campo">Dosis <input data-campo="dosis"></label>
                <label class="campo">Frecuencia <input data-campo="frecuencia"></label>
                <label class="campo">Duración <input data-campo="duracion"></label>
            </div>
            <label class="campo">Notas internas <textarea data-campo="notas"></textarea></label>
            <button type="button" class="boton boton-sec">Quitar ítem</button>
        </fieldset>
    </template>
    <script>
        const contenedorItems = document.querySelector('#items-plan');
        const plantillaItem = document.querySelector('#plantilla-item');
        let indiceItem = 0;
        function agregarItem() {
            const item = plantillaItem.content.firstElementChild.cloneNode(true);
            for (const campo of item.querySelectorAll('[data-campo]')) {
                campo.name = 'items[' + indiceItem + '][' + campo.dataset.campo + ']';
            }
            indiceItem++;
            item.querySelector('button').addEventListener('click', () => item.remove());
            contenedorItems.append(item);
        }
        document.querySelector('#agregar-item').addEventListener('click', agregarItem);
        agregarItem();
    </script>
</section>

<section class="tarjeta">
    <h2>Seguimientos</h2>
    <?php if (!$seguimientos): ?><p class="suave">Todavía no hay seguimientos.</p><?php endif; ?>
    <?php foreach ($seguimientos as $seguimiento): ?>
        <details>
            <summary><?= e(fecha_corta($seguimiento['fecha'])) ?> · <?= e($tipos[$seguimiento['tipo']] ?? $seguimiento['tipo']) ?>
                <?php if ($seguimiento['peso'] !== null): ?> · <?= e(numero_corto($seguimiento['peso'])) ?> kg<?php endif; ?>
            </summary>
            <p>Cintura: <?= e($seguimiento['cintura_cm'] === null ? '—' : numero_corto($seguimiento['cintura_cm']) . ' cm') ?></p>
            <p>Adherencia: <?= e($adherencias[$seguimiento['adherencia']] ?? '—') ?></p>
            <p>Efectos: <?= nl2br(e($seguimiento['efectos'])) ?></p>
            <p>Nota: <?= nl2br(e($seguimiento['nota'])) ?></p>
        </details>
    <?php endforeach; ?>
    <h3>Nuevo seguimiento</h3>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="seguimiento">
        <div class="fila">
            <label class="campo">Fecha <input type="date" name="fecha" value="<?= e(hoy()) ?>" required></label>
            <label class="campo">Tipo
                <select name="tipo">
                    <?php foreach ($tipos as $valor => $etiqueta): ?>
                        <option value="<?= e($valor) ?>"><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="fila">
            <label class="campo">Peso (kg) <input name="peso" inputmode="decimal"></label>
            <label class="campo">Cintura (cm) <input name="cintura" inputmode="decimal"></label>
            <label class="campo">Adherencia
                <select name="adherencia">
                    <option value="">Sin registrar</option>
                    <?php foreach ($adherencias as $valor => $etiqueta): ?>
                        <option value="<?= e($valor) ?>"><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="campo">Efectos <textarea name="efectos"></textarea></label>
        <label class="campo">Nota <textarea name="nota"></textarea></label>
        <label class="campo">Nuevo próximo contacto (opcional) <input type="date" name="proximo_contacto"></label>
        <button class="boton">Agregar seguimiento</button>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
