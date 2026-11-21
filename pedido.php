<?php
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/pedidos.php';

requerir_permiso('pedidos');
$nuevo = isset($_GET['nuevo']);
$id = (int) ($_GET['id'] ?? 0);
$cliente = (int) ($_GET['cliente'] ?? 0);
$pedido = $nuevo ? null : pedido_obtener($id);
$editar = $pedido && isset($_GET['editar']) && in_array($pedido['estado'], ['cotizado', 'confirmado'], true);
$url = $nuevo ? 'pedido.php?nuevo=1&cliente=' . $cliente : 'pedido.php?id=' . $id;
$url_formulario = $url . ($editar ? '&editar=1' : '');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $entrada = array_filter($_POST, 'is_string');
    unset($entrada['csrf']);
    $items = $_POST['items'] ?? [];
    if (is_array($items)) {
        $entrada['items'] = array_map(
            fn($item) => is_array($item) ? array_filter($item, 'is_string') : [],
            array_values($items)
        );
    }
    try {
        switch ($entrada['accion'] ?? '') {
            case 'guardar':
                $entrada['contraentrega'] = $entrada['contraentrega'] ?? 0;
                $items = $entrada['items'] ?? [];
                if ($nuevo) {
                    $id = pedido_crear($entrada, $items);
                    $url = 'pedido.php?id=' . $id;
                } else {
                    pedido_actualizar($id, $entrada, $items);
                }
                avisar('ok', 'Guardaste el pedido.');
                break;
            case 'pago':
                pago_agregar($id, $entrada);
                avisar('ok', 'Registraste el pago.');
                break;
            case 'estado':
                pedido_cambiar_estado($id, $entrada['estado'] ?? '', $entrada);
                avisar('ok', 'Actualizaste el estado del pedido.');
                break;
            default:
                throw new InvalidArgumentException('Selecciona una acción válida.');
        }
        unset($_SESSION['pedido_borrador']);
        redirigir($url);
    } catch (InvalidArgumentException $ex) {
        $_SESSION['pedido_borrador'] = ['url' => $url_formulario, 'datos' => $entrada];
        avisar('error', $ex->getMessage());
        redirigir($url_formulario);
    }
}

$borrador = $_SESSION['pedido_borrador'] ?? [];
$entrada = [];
if (($borrador['url'] ?? '') === $url_formulario) {
    $entrada = $borrador['datos'];
    unset($_SESSION['pedido_borrador']);
}
$titulo = $nuevo ? 'Nuevo pedido' : ($editar ? 'Editar pedido' : 'Pedido #' . $id);
$activo = 'pedidos';
if (!$nuevo && !$pedido) {
    http_response_code(404);
    $titulo = 'Pedido no encontrado';
}
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <h1><?= e($titulo) ?></h1>
    <a class="boton boton-sec" href="<?= e($editar ? $url : 'pedidos.php') ?>">Volver</a>
</div>
<?php if (!$nuevo && !$pedido): ?>
    <p class="tarjeta">Revisa el número del pedido.</p>
<?php elseif ($nuevo || $editar):
    $datos = ($entrada['accion'] ?? '') === 'guardar' ? $entrada : [];
    $datos += $pedido ?? ['cliente_id' => $cliente, 'envio' => 25000, 'descuento' => 0];
    $filas = $datos['items'] ?? ($editar ? pedido_items($id) : []);
    $clientes = $nuevo ? pedido_consulta('SELECT id, nombre FROM clientes WHERE activo = 1 OR id = ? ORDER BY nombre, id',
        [(int) $datos['cliente_id']])->fetchAll() : [];
    $procesos = $nuevo
        ? pedido_consulta('SELECT id, cliente_id, titulo FROM casos ORDER BY titulo, id', [])->fetchAll()
        : pedido_consulta('SELECT id, cliente_id, titulo FROM casos WHERE cliente_id = ? ORDER BY titulo, id',
            [$pedido['cliente_id']])->fetchAll();
    $productos = pedido_consulta('SELECT id, nombre, presentacion, precio, activo FROM productos ORDER BY nombre, id', [])->fetchAll();
    $campos = [
        'envio' => ['Envío (pesos)', 'numeric', 14],
        'descuento' => ['Descuento (pesos)', 'numeric', 14],
        'direccion' => ['Dirección de entrega', 'text', 255],
        'ciudad' => ['Ciudad', 'text', 80],
    ];
?>
    <form method="post" class="tarjeta formulario">
        <?= campo_csrf() ?>
        <?php if ($nuevo): ?>
            <div class="campo">
                <label for="cliente_id">Cliente</label>
                <select id="cliente_id" name="cliente_id" required>
                    <option value="">Elige un cliente</option>
                    <?php foreach ($clientes as $opcion): ?>
                        <option value="<?= e($opcion['id']) ?>" <?= (int) $opcion['id'] === (int) $datos['cliente_id'] ? 'selected' : '' ?>>
                            <?= e($opcion['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <p>Cliente <strong><?= e($pedido['cliente']) ?></strong></p>
        <?php endif; ?>
        <div class="campo">
            <label for="caso_id">Proceso (opcional)</label>
            <select id="caso_id" name="caso_id">
                <option value="">Sin proceso asociado</option>
                <?php foreach ($procesos as $proceso): ?>
                    <option value="<?= e($proceso['id']) ?>" data-cliente="<?= e($proceso['cliente_id']) ?>"
                        <?= (int) $proceso['id'] === (int) ($datos['caso_id'] ?? 0) ? 'selected' : '' ?>>
                        <?= e($proceso['titulo']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <h2>Productos</h2>
        <div id="pedido-items" class="formulario">
            <?php foreach ([...($filas ?: [[]]), null] as $i => $item): ?>
                <?php if ($item === null): ?></div><template id="plantilla-item"><?php endif; ?>
                <fieldset class="formulario">
                    <legend>Producto</legend>
                    <div class="campo">
                        <label for="producto-<?= e($i) ?>" data-campo="producto_id">Producto del catálogo</label>
                        <select id="producto-<?= e($i) ?>" name="items[<?= e($i) ?>][producto_id]" data-campo="producto_id" required>
                            <option value="">Elige un producto</option>
                            <?php foreach ($productos as $producto):
                                $seleccionado = (int) $producto['id'] === (int) ($item['producto_id'] ?? 0);
                                if (!$producto['activo'] && !$seleccionado) {
                                    continue;
                                }
                            ?>
                                <option value="<?= e($producto['id']) ?>" <?= $seleccionado ? 'selected' : '' ?>>
                                    <?= e($producto['nombre'] . ' · ' . $producto['presentacion'] . ' · ' . dinero($producto['precio'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fila">
                        <div class="campo">
                            <label for="cantidad-<?= e($i) ?>" data-campo="cantidad">Cantidad</label>
                            <input id="cantidad-<?= e($i) ?>" name="items[<?= e($i) ?>][cantidad]"
                                   data-campo="cantidad" inputmode="numeric" value="<?= e($item['cantidad'] ?? 1) ?>" required>
                        </div>
                        <div class="campo">
                            <label for="precio-<?= e($i) ?>" data-campo="precio_unitario">Precio unitario (pesos)</label>
                            <input id="precio-<?= e($i) ?>" name="items[<?= e($i) ?>][precio_unitario]"
                                   data-campo="precio_unitario" inputmode="numeric" value="<?= e($item['precio_unitario'] ?? '') ?>">
                            <span class="ayuda">Déjalo vacío para usar el precio del catálogo.</span>
                        </div>
                    </div>
                    <button type="button" class="boton boton-sec" data-quitar>Quitar producto</button>
                </fieldset>
                <?php if ($item === null): ?></template><?php endif; ?>
            <?php endforeach; ?>
        <button type="button" id="agregar-item" class="boton boton-sec">Agregar producto</button>
        <div class="fila">
            <?php foreach ($campos as $campo => [$etiqueta, $modo, $maximo]): ?>
                <div class="campo">
                    <label for="<?= e($campo) ?>"><?= e($etiqueta) ?></label>
                    <input id="<?= e($campo) ?>" name="<?= e($campo) ?>" inputmode="<?= e($modo) ?>" maxlength="<?= e($maximo) ?>"
                           value="<?= e($datos[$campo] ?? '') ?>" <?= $modo === 'numeric' ? 'required' : '' ?>>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="campo">
            <label for="notas">Notas internas</label>
            <textarea id="notas" name="notas" maxlength="16383"><?= e($datos['notas'] ?? '') ?></textarea>
        </div>
        <div>
            <input id="contraentrega" name="contraentrega" type="checkbox" value="1" <?= !empty($datos['contraentrega']) ? 'checked' : '' ?>>
            <label for="contraentrega">Cobrar contraentrega</label>
        </div>
        <button class="boton" name="accion" value="guardar">Guardar pedido</button>
    </form>
    <script>
        (() => {
            const cliente = document.querySelector('#cliente_id');
            const proceso = document.querySelector('#caso_id');
            const items = document.querySelector('#pedido-items');
            const plantilla = document.querySelector('#plantilla-item');
            let indice = items.children.length;
            function filtrarProcesos() {
                for (const opcion of proceso.querySelectorAll('[data-cliente]')) {
                    opcion.disabled = opcion.hidden = opcion.dataset.cliente !== cliente.value;
                }
                if (proceso.selectedOptions[0]?.disabled) proceso.value = '';
            }
            if (cliente) {
                cliente.addEventListener('change', filtrarProcesos);
                filtrarProcesos();
            }
            function actualizarBotones() {
                for (const boton of items.querySelectorAll('[data-quitar]')) {
                    boton.disabled = items.children.length === 1;
                }
            }
            document.querySelector('#agregar-item').addEventListener('click', () => {
                const fila = plantilla.content.firstElementChild.cloneNode(true);
                for (const campo of fila.querySelectorAll('[data-campo]')) {
                    const nombre = campo.dataset.campo;
                    const id = 'item-' + indice + '-' + nombre;
                    if (campo.tagName === 'LABEL') {
                        campo.htmlFor = id;
                    } else {
                        campo.id = id;
                        campo.name = 'items[' + indice + '][' + nombre + ']';
                    }
                }
                indice++;
                items.append(fila);
                actualizarBotones();
                fila.querySelector('select').focus();
            });
            items.addEventListener('click', (evento) => {
                if (evento.target.matches('[data-quitar]') && items.children.length > 1) {
                    evento.target.closest('fieldset').remove();
                    actualizarBotones();
                    document.querySelector('#agregar-item').focus();
                }
            });
            actualizarBotones();
        })();
    </script>
<?php else:
    $items = pedido_items($id);
    $pagos = pedido_pagos($id);
    $pendientes = PEDIDO_TRANSICIONES[$pedido['estado']] ?? [];
    $estado_pago = pedido_estado_pago($id);
    $enlace = url_base() . '/pedido_estado.php?t=' . rawurlencode($pedido['token']);
    $plantilla = $pedido['estado'] === 'despachado' ? plantilla_texto('Pedido despachado') : '';
    $whatsapp = '';
    if ($plantilla !== '' && telefono_whatsapp($pedido['telefono']) !== '') {
        $whatsapp = enlace_whatsapp($pedido['telefono'], mensaje_rellenar($plantilla, [
            'nombre' => primer_nombre($pedido['cliente']),
            'producto' => implode(', ', array_unique(array_column($items, 'producto_nombre'))),
            'transportadora' => $pedido['transportadora'],
            'guia' => $pedido['guia'],
            'total' => dinero($pedido['total']),
            'enlace' => $enlace,
            'fecha' => fecha_corta($pedido['despachado']),
        ]));
    }
    $pago = ($entrada['accion'] ?? '') === 'pago' ? $entrada : [];
    $despacho = ($entrada['accion'] ?? '') === 'estado' ? $entrada : [];
?>
    <section class="tarjeta">
        <div class="encabezado">
            <div>
                <h2><?= e($pedido['cliente']) ?></h2>
                <p><?= e(PEDIDO_ESTADOS[$pedido['estado']]) ?> · <?= e(fecha_corta($pedido['creado'])) ?></p>
            </div>
            <?php if (in_array($pedido['estado'], ['cotizado', 'confirmado'], true)): ?>
                <a class="boton boton-sec" href="<?= e($url) ?>&amp;editar=1">Editar pedido</a>
            <?php endif; ?>
        </div>
        <ul class="lista">
            <?php foreach ($items as $item): ?>
                <li>
                    <strong><?= e($item['producto_nombre']) ?></strong>
                    <div><?= e($item['cantidad']) ?> × <?= e(dinero($item['precio_unitario'])) ?> · <?= e(dinero($item['subtotal'])) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p>Subtotal <?= e(dinero($pedido['subtotal'])) ?> · Envío <?= e(dinero($pedido['envio'])) ?>
           · Descuento <?= e(dinero($pedido['descuento'])) ?></p>
        <p><strong>Total <?= e(dinero($pedido['total'])) ?></strong></p>
    </section>
    <section class="tarjeta">
        <h2>Envío y estado</h2>
        <p><?= $pedido['contraentrega'] ? 'Cobro contraentrega' : 'Pago antes del despacho' ?></p>
        <?php if ($pedido['direccion'] || $pedido['ciudad']): ?>
            <p><?= e(implode(' · ', array_filter([$pedido['direccion'], $pedido['ciudad']]))) ?></p>
        <?php endif; ?>
        <?php if ($pedido['guia'] !== null): ?>
            <p><?= e($pedido['transportadora']) ?> · Guía <?= e($pedido['guia']) ?></p>
        <?php endif; ?>
        <?php if ($pedido['notas'] !== null): ?>
            <p>Notas internas<br><?= nl2br(e($pedido['notas'])) ?></p>
        <?php endif; ?>
        <div class="fila">
            <a class="boton boton-sec" href="<?= e($enlace) ?>" target="_blank" rel="noopener noreferrer">Abrir enlace público</a>
            <?php if ($whatsapp): ?>
                <a class="boton boton-sec" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">Avisar por WhatsApp</a>
            <?php endif; ?>
        </div>
        <?php if ($pendientes): ?>
            <?php $acciones = ['confirmado' => 'Confirmar pedido', 'cancelado' => 'Cancelar pedido',
                'entregado' => 'Marcar como entregado', 'devuelto' => 'Registrar devolución', 'despachado' => 'Despachar pedido']; ?>
            <div class="fila">
                <?php foreach ($pendientes as $paso): ?>
                    <form method="post" class="formulario">
                        <?= campo_csrf() ?>
                        <input type="hidden" name="accion" value="estado">
                        <?php if ($paso === 'despachado'): ?>
                            <div class="campo">
                                <label for="transportadora">Transportadora</label>
                                <input id="transportadora" name="transportadora" maxlength="80" required value="<?= e($despacho['transportadora'] ?? '') ?>">
                            </div>
                            <div class="campo">
                                <label for="guia">Número de guía</label>
                                <input id="guia" name="guia" maxlength="80" required value="<?= e($despacho['guia'] ?? '') ?>">
                            </div>
                        <?php endif; ?>
                        <button class="boton boton-sec" name="estado" value="<?= e($paso) ?>"><?= e($acciones[$paso]) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
    <section class="tarjeta">
        <h2>Pagos</h2>
        <p><strong><?= e(PEDIDO_ESTADOS_PAGO[$estado_pago]) ?></strong> · Total registrado <?= e(dinero(array_sum(array_column($pagos, 'valor')))) ?></p>
        <?php if ($pagos): ?>
            <ul class="lista">
                <?php foreach ($pagos as $registro): ?>
                    <li>
                        <strong><?= e(dinero($registro['valor'])) ?></strong>
                        · <?= e(PEDIDO_METODOS_PAGO[$registro['metodo']]) ?> · <?= e(fecha_corta($registro['fecha'])) ?>
                        <?php if ($registro['referencia'] !== null): ?> · Ref. <?= e($registro['referencia']) ?><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" class="formulario">
            <?= campo_csrf() ?>
            <div class="fila">
                <div class="campo">
                    <label for="metodo">Método de pago</label>
                    <select id="metodo" name="metodo" required>
                        <option value="">Elige un método</option>
                        <?php foreach (PEDIDO_METODOS_PAGO as $metodo => $nombre): ?>
                            <option value="<?= e($metodo) ?>" <?= ($pago['metodo'] ?? '') === $metodo ? 'selected' : '' ?>>
                                <?= e($nombre) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="campo">
                    <label for="valor">Valor (pesos)</label>
                    <input id="valor" name="valor" maxlength="15" required value="<?= e($pago['valor'] ?? '') ?>">
                    <span class="ayuda">Para una devolución de dinero, escribe un valor negativo.</span>
                </div>
                <div class="campo">
                    <label for="fecha">Fecha del pago</label>
                    <input type="date" id="fecha" name="fecha" required value="<?= e($pago['fecha'] ?? hoy()) ?>">
                </div>
                <div class="campo">
                    <label for="referencia">Referencia (opcional)</label>
                    <input id="referencia" name="referencia" maxlength="80" value="<?= e($pago['referencia'] ?? '') ?>">
                </div>
            </div>
            <button class="boton" name="accion" value="pago">Registrar pago</button>
        </form>
    </section>
    <section class="tarjeta">
        <h2>Historial</h2>
        <ol class="lista">
            <?php foreach (pedido_historial($id) as $paso): ?>
                <li><?= e(PEDIDO_ESTADOS[$paso['estado']]) ?> · <?= e(fecha_corta($paso['fecha'])) ?>
                    <?php if ($paso['nota'] !== null): ?> · <?= e($paso['nota']) ?><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
