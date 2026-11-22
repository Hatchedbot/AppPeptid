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
    $titulo = 'Nuevo proceso';
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
    $catalogo = db()->prepare('SELECT id, nombre, presentacion, categoria FROM productos WHERE activo = ? ORDER BY categoria, nombre, presentacion, id');
    $catalogo->execute([1]);
    $productos_por_categoria = [];
    foreach ($catalogo->fetchAll() as $producto) {
        $categoria = trim($producto['categoria']) ?: 'Sin categoría';
        $productos_por_categoria[$categoria][] = $producto;
    }
}
$activo = 'clientes';
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <div>
        <h1><?= e($titulo) ?></h1>
        <p class="suave">Cliente: <?= e($nombre_cliente) ?></p>
    </div>
    <div class="fila">
        <a class="boton boton-sec" href="cliente.php?id=<?= e($nuevo ? $cliente_id : $caso['cliente_id']) ?>">Volver a la ficha</a>
        <?php if ($nuevo): ?>
            <button type="submit" class="boton" form="formulario-caso">Crear proceso</button>
        <?php else: ?>
            <a class="boton" href="#nuevo-seguimiento">Registrar seguimiento</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($nuevo): ?>
<form method="post" id="formulario-caso" class="tarjeta formulario" autocomplete="off">
    <h2 class="titulo-icono"><?= icono('plan') ?> Datos del proceso</h2>
    <?= campo_csrf() ?>
    <div class="campo">
        <label for="titulo">Título</label>
        <input id="titulo" type="text" name="titulo" required>
    </div>
    <div class="campo">
        <label for="objetivo">Objetivo</label>
        <textarea id="objetivo" name="objetivo" rows="3"></textarea>
    </div>
    <div class="campo">
        <label for="fecha-inicio">Fecha de inicio</label>
        <input id="fecha-inicio" type="date" name="fecha_inicio" value="<?= e(hoy()) ?>" required>
    </div>
    <div class="fila">
        <div class="campo">
            <label for="peso-inicial">Peso inicial (kg)</label>
            <input id="peso-inicial" type="text" name="peso_inicial" inputmode="decimal">
        </div>
        <div class="campo">
            <label for="peso-meta">Peso meta (kg)</label>
            <input id="peso-meta" type="text" name="peso_meta" inputmode="decimal">
        </div>
    </div>
    <div class="campo">
        <label for="proximo-contacto">Próximo contacto</label>
        <input id="proximo-contacto" type="date" name="proximo_contacto">
    </div>
    <button class="boton">Crear proceso</button>
</form>
<?php else: ?>
<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('peso') ?> Resumen del proceso</h2>
    <?php $clases_estado = ['activo' => 'etiqueta-ok', 'pausado' => 'etiqueta-alerta']; ?>
    <p><span class="etiqueta <?= e($clases_estado[$caso['estado']] ?? '') ?>"><?= e($estados[$caso['estado']] ?? $caso['estado']) ?></span></p>
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
    <form method="post" class="formulario" autocomplete="off">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="actualizar">
        <div class="fila">
            <div class="campo">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <?php foreach ($estados as $valor => $etiqueta): ?>
                        <option value="<?= e($valor) ?>" <?= $caso['estado'] === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="contacto-caso">Próximo contacto</label>
                <input id="contacto-caso" type="date" name="proximo_contacto" value="<?= e($caso['proximo_contacto']) ?>">
            </div>
        </div>
        <button class="boton">Guardar cambios</button>
    </form>
</section>

<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('plan') ?> Plan actual e historial</h2>
    <?php if (!$planes): ?>
        <p class="suave">Crea el primer plan para este proceso.</p>
        <a class="boton boton-sec" href="#nueva-version">Ir al formulario del plan</a>
    <?php endif; ?>
    <?php foreach ($planes as $indice => $plan): ?>
        <details <?= $indice === 0 ? 'open' : '' ?>>
            <summary class="boton boton-sec"><?= $indice === 0 ? 'Actual · ' : '' ?>Versión <?= e($plan['version']) ?> · <?= e(fecha_corta($plan['creado'])) ?></summary>
            <p>Indicaciones: <?= nl2br(e($plan['indicaciones'])) ?></p>
            <p>Motivo: <?= nl2br(e($plan['motivo_cambio'])) ?></p>
            <ul>
                <?php foreach (plan_items_del_plan((int) $plan['id']) as $item): ?>
                    <li><?= e(implode(' · ', array_filter($item, static fn ($valor) => $valor !== null && $valor !== ''))) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($plan['enviado']): ?><p>Marcado como enviado el <?= e(fecha_corta($plan['enviado'])) ?>.</p><?php endif; ?>
            <?php if ($indice === 0): ?>
                <form method="post" target="_blank" class="formulario">
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
    <h2 id="nueva-version" class="titulo-icono"><?= icono('plan') ?> <?= e($planes ? 'Nueva versión del plan' : 'Primer plan') ?></h2>
    <form method="post" class="formulario" autocomplete="off">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="nueva_version">
        <div class="campo">
            <label for="indicaciones">Indicaciones generales</label>
            <textarea id="indicaciones" name="indicaciones" rows="3"></textarea>
        </div>
        <div class="campo">
            <label for="motivo">Motivo del cambio</label>
            <textarea id="motivo" name="motivo" rows="2"></textarea>
        </div>
        <div id="items-plan"></div>
        <div class="fila">
            <button type="button" class="boton boton-sec" id="agregar-item">Agregar ítem</button>
            <button class="boton"><?= $planes ? 'Guardar nueva versión' : 'Guardar plan' ?></button>
        </div>
    </form>
    <template id="plantilla-item">
        <fieldset class="tarjeta formulario">
            <legend>Ítem del plan</legend>
            <div class="fila">
                <div class="campo">
                    <label for="plantilla-producto_id">Producto del catálogo</label>
                    <select id="plantilla-producto_id" data-campo="producto_id">
                        <option value="">Selecciona un producto</option>
                        <?php foreach ($productos_por_categoria as $categoria => $productos): ?>
                            <optgroup label="<?= e($categoria) ?>">
                                <?php foreach ($productos as $producto): ?>
                                    <option value="<?= e($producto['id']) ?>"><?= e($producto['nombre'] . ' · ' . $producto['presentacion']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="plantilla-producto_nombre">Producto en texto libre</label>
                    <input type="text" id="plantilla-producto_nombre" data-campo="producto_nombre">
                </div>
            </div>
            <div class="fila">
                <div class="campo">
                    <label for="plantilla-dosis">Dosis</label>
                    <input type="text" id="plantilla-dosis" data-campo="dosis">
                </div>
                <div class="campo">
                    <label for="plantilla-frecuencia">Frecuencia</label>
                    <input type="text" id="plantilla-frecuencia" data-campo="frecuencia">
                </div>
                <div class="campo">
                    <label for="plantilla-duracion">Duración</label>
                    <input type="text" id="plantilla-duracion" data-campo="duracion">
                </div>
            </div>
            <div class="campo">
                <label for="plantilla-notas">Notas internas</label>
                <textarea id="plantilla-notas" data-campo="notas"></textarea>
            </div>
            <button type="button" class="boton boton-peligro">Quitar ítem</button>
        </fieldset>
    </template>
    <script>
        const contenedorItems = document.querySelector('#items-plan');
        const plantillaItem = document.querySelector('#plantilla-item');
        let indiceItem = 0;
        function agregarItem() {
            const item = plantillaItem.content.firstElementChild.cloneNode(true);
            for (const campo of item.querySelectorAll('[data-campo]')) {
                campo.id = 'item-' + indiceItem + '-' + campo.dataset.campo;
                campo.previousElementSibling.htmlFor = campo.id;
                campo.name = 'items[' + indiceItem + '][' + campo.dataset.campo + ']';
            }
            indiceItem++;
            item.querySelector('button').addEventListener('click', () => {
                if (confirm('¿Quieres quitar este ítem del plan?')) {
                    item.remove();
                    document.querySelector('#agregar-item').focus();
                }
            });
            contenedorItems.append(item);
        }
        document.querySelector('#agregar-item').addEventListener('click', agregarItem);
        agregarItem();
    </script>
</section>

<section class="tarjeta">
    <h2 class="titulo-icono"><?= icono('calendario') ?> Seguimientos</h2>
    <?php if (!$seguimientos): ?>
        <p class="suave">Registra el primer seguimiento para ver el avance del cliente.</p>
        <a class="boton boton-sec" href="#nuevo-seguimiento">Ir al formulario de seguimiento</a>
    <?php endif; ?>
    <?php foreach ($seguimientos as $seguimiento): ?>
        <details>
            <summary class="boton boton-sec"><?= e(fecha_corta($seguimiento['fecha'])) ?> · <?= e($tipos[$seguimiento['tipo']] ?? $seguimiento['tipo']) ?>
                <?php if ($seguimiento['peso'] !== null): ?> · <?= e(numero_corto($seguimiento['peso'])) ?> kg<?php endif; ?>
            </summary>
            <p>Cintura: <?= e($seguimiento['cintura_cm'] === null ? '—' : numero_corto($seguimiento['cintura_cm']) . ' cm') ?></p>
            <p>Adherencia: <?= e($adherencias[$seguimiento['adherencia']] ?? '—') ?></p>
            <p>Efectos: <?= nl2br(e($seguimiento['efectos'])) ?></p>
            <p>Nota: <?= nl2br(e($seguimiento['nota'])) ?></p>
        </details>
    <?php endforeach; ?>
</section>

<section class="tarjeta">
    <h2 id="nuevo-seguimiento" class="titulo-icono"><?= icono('calendario') ?> Nuevo seguimiento</h2>
    <form method="post" class="formulario" autocomplete="off">
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="seguimiento">
        <div class="fila">
            <div class="campo">
                <label for="fecha">Fecha</label>
                <input id="fecha" type="date" name="fecha" value="<?= e(hoy()) ?>" required>
            </div>
            <div class="campo">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <?php foreach ($tipos as $valor => $etiqueta): ?>
                        <option value="<?= e($valor) ?>"><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="peso">Peso (kg)</label>
                <input id="peso" type="text" name="peso" inputmode="decimal">
            </div>
            <div class="campo">
                <label for="cintura">Cintura (cm)</label>
                <input id="cintura" type="text" name="cintura" inputmode="decimal">
            </div>
            <div class="campo">
                <label for="adherencia">Adherencia</label>
                <select id="adherencia" name="adherencia">
                    <option value="">Sin registrar</option>
                    <?php foreach ($adherencias as $valor => $etiqueta): ?>
                        <option value="<?= e($valor) ?>"><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="campo">
            <label for="efectos">Efectos</label>
            <textarea id="efectos" name="efectos"></textarea>
        </div>
        <div class="campo">
            <label for="nota">Nota</label>
            <textarea id="nota" name="nota"></textarea>
        </div>
        <div class="campo">
            <label for="contacto-seguimiento">Nuevo próximo contacto (opcional)</label>
            <input id="contacto-seguimiento" type="date" name="proximo_contacto">
        </div>
        <button class="boton">Agregar seguimiento</button>
    </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
