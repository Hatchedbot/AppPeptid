<?php
/**
 * Ficha de un cliente. También sirve para crearlo (?nuevo=1) y para editarlo (?id=N&editar=1).
 */
require __DIR__ . '/includes/app.php';
requerir_permiso('clientes');

require __DIR__ . '/includes/clientes.php';

$activo = 'clientes';

$id = (int) ($_GET['id'] ?? 0);
$es_nuevo = isset($_GET['nuevo']);
$es_edicion = !$es_nuevo && $id > 0 && isset($_GET['editar']);

if ($es_nuevo) {
    $cliente = [
        'id' => 0, 'nombre' => '', 'telefono' => '', 'correo' => '', 'ciudad' => '',
        'fecha_nacimiento' => '', 'sexo' => '', 'estatura_cm' => '', 'objetivo' => '',
        'condiciones' => '', 'notas' => '', 'consentimiento_fecha' => null,
    ];
} elseif ($id > 0) {
    $cliente = cliente_obtener($id);
} else {
    redirigir('clientes.php');
}

if (!$cliente) {
    http_response_code(404);
    $titulo = 'Cliente no encontrado';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="encabezado">
        <div>
            <h1>No encontramos ese cliente</h1>
            <p class="suave">Busca al cliente en la lista para abrir su ficha.</p>
        </div>
        <a class="boton" href="clientes.php">Volver a clientes</a>
    </div>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$error = '';
$existente = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($es_nuevo || $es_edicion)) {
    $datos = [];
    foreach (['nombre', 'telefono', 'correo', 'ciudad', 'fecha_nacimiento', 'sexo', 'estatura_cm',
              'objetivo', 'condiciones', 'notas'] as $campo) {
        $valor = $_POST[$campo] ?? '';
        $datos[$campo] = is_string($valor) ? trim($valor) : '';
    }
    $datos['consentimiento'] = ($_POST['consentimiento'] ?? '') === '1';

    try {
        if ($es_nuevo) {
            $nuevo_id = cliente_crear($datos);
            avisar('ok', 'Cliente creado. Ya puedes agregarle un proceso.');
            redirigir('cliente.php?id=' . $nuevo_id);
        }
        cliente_actualizar($id, $datos);
        avisar('ok', 'Guardaste los cambios.');
        redirigir('cliente.php?id=' . $id);
    } catch (InvalidArgumentException $ex) {
        $error = $ex->getMessage();
        if (str_contains($error, 'ya está registrado')) {
            $existente = cliente_por_telefono($datos['telefono'], $es_edicion ? $id : null);
        }
        $cliente = array_merge($cliente, $datos);
    }
}

if ($es_nuevo || $es_edicion) {
    $titulo = $es_nuevo ? 'Nuevo cliente' : 'Editar cliente';
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="encabezado">
        <div>
            <h1><?= $es_nuevo ? 'Nuevo cliente' : 'Editar cliente' ?></h1>
            <p class="suave">Registra los datos de contacto y del seguimiento.</p>
        </div>
        <div class="fila">
            <a class="boton boton-sec" href="<?= $es_nuevo ? 'clientes.php' : 'cliente.php?id=' . (int) $id ?>">
                <?= $es_nuevo ? 'Volver a clientes' : 'Volver a la ficha' ?>
            </a>
            <button type="submit" form="formulario-cliente" class="boton"><?= $es_nuevo ? 'Crear cliente' : 'Guardar cambios' ?></button>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="aviso aviso-error" role="alert">
            <?= e($error) ?>
            <?php if ($existente): ?>
                <div><a class="boton boton-sec" href="cliente.php?id=<?= (int) $existente['id'] ?>">Abrir la ficha de <?= e($existente['nombre']) ?></a></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" id="formulario-cliente" class="tarjeta formulario" autocomplete="off"
          action="<?= $es_nuevo ? 'cliente.php?nuevo=1' : 'cliente.php?id=' . (int) $id . '&amp;editar=1' ?>">
        <h2>Datos del cliente</h2>
        <p class="suave">Los campos con asterisco son obligatorios.</p>
        <?= campo_csrf() ?>

        <div class="campo">
            <label for="nombre">Nombre completo *</label>
            <input type="text" id="nombre" name="nombre" maxlength="150" required autocomplete="name"
                   value="<?= e($cliente['nombre'] ?? '') ?>">
        </div>

        <div class="fila">
            <div class="campo">
                <label for="telefono">Teléfono *</label>
                <input type="tel" id="telefono" name="telefono" maxlength="20" required inputmode="tel" autocomplete="tel"
                       placeholder="311 503 4356" value="<?= e($cliente['telefono'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="ciudad">Ciudad</label>
                <input type="text" id="ciudad" name="ciudad" maxlength="80" autocomplete="address-level2"
                       value="<?= e($cliente['ciudad'] ?? '') ?>">
            </div>
        </div>

        <div class="campo">
            <label for="correo">Correo</label>
            <input type="email" id="correo" name="correo" maxlength="150" autocomplete="email"
                   value="<?= e($cliente['correo'] ?? '') ?>">
        </div>

        <div class="fila">
            <div class="campo">
                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" autocomplete="bday"
                       value="<?= e($cliente['fecha_nacimiento'] ?? '') ?>">
            </div>
            <div class="campo">
                <label for="sexo">Sexo</label>
                <select id="sexo" name="sexo" autocomplete="sex">
                    <option value="">Sin especificar</option>
                    <?php foreach (['Femenino', 'Masculino', 'Otro'] as $opcion): ?>
                        <option value="<?= e($opcion) ?>" <?= ($cliente['sexo'] ?? '') === $opcion ? 'selected' : '' ?>>
                            <?= e($opcion) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="estatura_cm">Estatura (cm)</label>
                <input type="number" id="estatura_cm" name="estatura_cm" min="30" max="300" step="0.1"
                       inputmode="decimal" value="<?= e($cliente['estatura_cm'] ?? '') ?>">
            </div>
        </div>

        <div class="campo">
            <label for="objetivo">Objetivo</label>
            <textarea id="objetivo" name="objetivo" placeholder="Qué quiere lograr con su proceso"><?= e($cliente['objetivo'] ?? '') ?></textarea>
        </div>

        <div class="campo">
            <label for="condiciones">Condiciones de salud</label>
            <textarea id="condiciones" name="condiciones" aria-describedby="ayuda-condiciones"
                      placeholder="Alergias, medicamentos, enfermedades o cirugías"><?= e($cliente['condiciones'] ?? '') ?></textarea>
            <span class="ayuda" id="ayuda-condiciones">Dato sensible. Solo para el seguimiento del cliente.</span>
        </div>

        <div class="campo">
            <label for="notas">Notas</label>
            <textarea id="notas" name="notas" placeholder="Cualquier otro detalle útil"><?= e($cliente['notas'] ?? '') ?></textarea>
        </div>

        <?php if ($es_edicion && !empty($cliente['consentimiento_fecha'])): ?>
            <p class="suave">Autorización registrada el <?= e(fecha_corta($cliente['consentimiento_fecha'])) ?>.</p>
        <?php else: ?>
            <label class="casilla" for="consentimiento">
                <input type="checkbox" id="consentimiento" name="consentimiento" value="1"
                       <?= !empty($cliente['consentimiento']) ? 'checked' : '' ?>
                       <?= $es_nuevo ? 'required' : '' ?>>
                <span>El cliente autorizó el tratamiento de sus datos personales y de salud para hacer seguimiento de su proceso.<br>
                    <span class="suave">Ley 1581 de 2012</span>
                </span>
            </label>
        <?php endif; ?>

        <div>
            <button type="submit" class="boton"><?= $es_nuevo ? 'Crear cliente' : 'Guardar cambios' ?></button>
        </div>
    </form>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

/* ---------- Ficha del cliente ---------- */

$casos = cliente_casos($id);
$eventos = cliente_eventos($id);
$pedidos = [];
if (puede('pedidos') && is_file(RAIZ . '/includes/pedidos.php')) {
    require_once RAIZ . '/includes/inventario.php';
    require_once RAIZ . '/includes/pedidos.php';
    $pedidos = pedidos_listar(['cliente_id' => $id]);
}
$estados = ['activo' => 'etiqueta-ok', 'pausado' => 'etiqueta-alerta', 'terminado' => 'etiqueta'];
$whatsapp = enlace_whatsapp((string) $cliente['telefono'], 'Hola ' . primer_nombre((string) $cliente['nombre']));

$titulo = $cliente['nombre'];
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <div>
        <h1><?= e($cliente['nombre']) ?></h1>
        <p class="suave">Cliente desde <?= e(fecha_corta($cliente['creado'])) ?></p>
    </div>
    <div class="fila">
        <?php if (puede('pedidos')): ?>
            <a class="boton" href="pedido.php?nuevo=1&amp;cliente=<?= (int) $id ?>"><?= icono('pedidos') ?> Crear pedido</a>
        <?php endif; ?>
        <?php if (puede('casos')): ?>
            <a class="boton boton-sec" href="caso.php?nuevo=1&amp;cliente=<?= (int) $id ?>"><?= icono('plan') ?> Crear proceso</a>
        <?php endif; ?>
        <a class="boton boton-sec" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener"><?= icono('mensajes') ?> Escribir por WhatsApp</a>
        <a class="boton boton-sec" href="cliente.php?id=<?= (int) $id ?>&amp;editar=1"><?= icono('usuario') ?> Editar ficha</a>
    </div>
</div>

<section class="tarjeta">
    <h2>Datos</h2>
    <table class="tabla tabla-movil">
        <tbody>
            <tr><th scope="row">Teléfono</th><td><?= e($cliente['telefono']) ?></td></tr>
            <tr><th scope="row">Ciudad</th><td><?= e($cliente['ciudad'] ?? '') ?: '<span class="suave">—</span>' ?></td></tr>
            <tr><th scope="row">Correo</th><td><?= e($cliente['correo'] ?? '') ?: '<span class="suave">—</span>' ?></td></tr>
            <tr><th scope="row">Fecha de nacimiento</th><td><?= e(fecha_corta($cliente['fecha_nacimiento'] ?? '')) ?: '<span class="suave">—</span>' ?></td></tr>
            <tr><th scope="row">Sexo</th><td><?= e($cliente['sexo'] ?? '') ?: '<span class="suave">—</span>' ?></td></tr>
            <tr><th scope="row">Estatura</th><td><?= $cliente['estatura_cm'] !== null ? e(numero_corto($cliente['estatura_cm'])) . ' cm' : '<span class="suave">—</span>' ?></td></tr>
            <tr><th scope="row">Objetivo</th><td><?= nl2br(e($cliente['objetivo'] ?? '')) ?: '<span class="suave">—</span>' ?></td></tr>
            <tr><th scope="row">Condiciones de salud</th><td><?= nl2br(e($cliente['condiciones'] ?? '')) ?: '<span class="suave">—</span>' ?></td></tr>
            <tr><th scope="row">Notas</th><td><?= nl2br(e($cliente['notas'] ?? '')) ?: '<span class="suave">—</span>' ?></td></tr>
            <tr>
                <th scope="row">Consentimiento</th>
                <td>
                    <?php if (!empty($cliente['consentimiento_fecha'])): ?>
                        <span class="etiqueta etiqueta-ok">Autorizado</span>
                        el <?= e(fecha_corta($cliente['consentimiento_fecha'])) ?>
                    <?php else: ?>
                        <span class="etiqueta etiqueta-alerta">Sin autorización registrada</span>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
</section>

<section class="tarjeta">
    <div class="encabezado">
        <h2>Procesos</h2>
        <a class="boton boton-sec" href="caso.php?nuevo=1&amp;cliente=<?= (int) $id ?>">Nuevo proceso</a>
    </div>
    <?php if (!$casos): ?>
        <p class="suave">Crea el primer proceso para hacer seguimiento al cliente.</p>
    <?php else: ?>
        <ul class="lista">
            <?php foreach ($casos as $caso): ?>
                <li>
                    <a class="boton boton-texto" href="caso.php?id=<?= (int) $caso['id'] ?>"><strong><?= e($caso['titulo']) ?></strong></a>
                    <span class="etiqueta <?= e($estados[$caso['estado']] ?? 'etiqueta') ?>"><?= e($caso['estado']) ?></span>
                    <div class="suave">
                        Inició el <?= e(fecha_corta($caso['fecha_inicio'])) ?>
                        <?php if (!empty($caso['proximo_contacto'])): ?>
                            · Próximo contacto: <?= e(fecha_corta($caso['proximo_contacto'])) ?>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php if (puede('pedidos') && defined('PEDIDO_ESTADOS')): ?>
<section class="tarjeta">
    <div class="encabezado">
        <h2>Pedidos</h2>
        <a class="boton boton-sec" href="pedido.php?nuevo=1&amp;cliente=<?= (int) $id ?>">Nuevo pedido</a>
    </div>
    <?php if (!$pedidos): ?>
        <p class="suave">Crea el primer pedido cuando el cliente haga una compra.</p>
    <?php else: ?>
        <ul class="lista">
            <?php foreach ($pedidos as $p): ?>
                <li>
                    <a class="boton boton-texto" href="pedido.php?id=<?= (int) $p['id'] ?>"><strong>Pedido #<?= (int) $p['id'] ?></strong></a>
                    · <?= e(dinero($p['total'])) ?>
                    <span class="etiqueta"><?= e(PEDIDO_ESTADOS[$p['estado']] ?? $p['estado']) ?></span>
                    <span class="etiqueta <?= $p['estado_pago'] === 'pagado' ? 'etiqueta-ok' : 'etiqueta-alerta' ?>">
                        <?= e(PEDIDO_ESTADOS_PAGO[$p['estado_pago']] ?? $p['estado_pago']) ?></span>
                    <div class="suave"><?= e(fecha_corta($p['creado'])) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="tarjeta">
    <h2>Historial</h2>
    <?php if (!$eventos): ?>
        <p class="suave">Aquí verás los cambios y seguimientos que registres.</p>
    <?php else: ?>
        <ul class="lista">
            <?php foreach ($eventos as $evento): ?>
                <li>
                    <div><strong><?= e(fecha_corta($evento['fecha'])) ?></strong> · <?= e($evento['texto']) ?></div>
                    <?php if (!empty($evento['nombre_usuario'])): ?>
                        <div class="suave"><?= e($evento['nombre_usuario']) ?></div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php';
