<?php
require __DIR__ . '/includes/app.php';
requerir_permiso('mensajes');
require_once __DIR__ . '/includes/mensajes.php';

$nuevo = ($_GET['nuevo'] ?? '') === '1';
$individual = $nuevo && ($_GET['individual'] ?? '') === '1';
$id = (int) ($_GET['id'] ?? 0);
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$activo = 'mensajes';

if (!$nuevo) {
    $ruta = 'campana.php?id=' . $id;
    $campana = campana_obtener($id);
    if (!$campana) {
        avisar('error', 'No encontré esa campaña.');
        redirigir('mensajes.php');
    }

    if ($post) {
        $json = str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
        $datos = $json ? json_decode(file_get_contents('php://input'), true) : $_POST;
        try {
            $actual = mensaje_siguiente($id);
            $accion = $datos['accion'] ?? '';
            if (!$actual || (int) $actual['id'] !== (int) ($datos['mensaje_id'] ?? 0)) {
                throw new InvalidArgumentException('La campaña avanzó. Revisa el mensaje actual.');
            }
            if (!in_array($accion, ['enviado', 'omitido'], true)) {
                throw new InvalidArgumentException('Acción no reconocida.');
            }
            $destino = $ruta;
            if ($accion === 'enviado' && !telefono_sirve_whatsapp($actual['telefono'])) {
                $accion = 'omitido';
            }
            if (!mensaje_marcar((int) $actual['id'], $accion)) {
                throw new InvalidArgumentException('La campaña avanzó. Revisa el mensaje actual.');
            }
            if ($accion === 'enviado') {
                $destino = enlace_whatsapp($actual['telefono'], $actual['texto']);
                avisar('ok', 'Mensaje marcado como enviado. Toca Enviar en WhatsApp.');
            } else {
                avisar('info', 'Mensaje omitido.');
            }
        } catch (InvalidArgumentException $ex) {
            avisar('error', $ex->getMessage());
            $destino = $ruta;
        }
        if ($json) {
            json_respuesta(['whatsapp' => $destino === $ruta ? null : $destino]);
        }
        redirigir($destino);
    }

    campana_omitir_sin_telefono($id);
    $siguiente = mensaje_siguiente($id);
    $avance = campana_avance($id);
    $titulo = $campana['nombre'];
    require __DIR__ . '/includes/header.php';
    ?>
    <div class="encabezado">
        <div>
            <h1><?= e($titulo) ?></h1>
            <p class="suave">Avance de <?= e($avance['total']) ?> mensajes</p>
        </div>
        <a class="boton boton-sec" href="mensajes.php">Volver a mensajes</a>
    </div>
    <section class="tarjeta">
        <h2>Avance</h2>
        <span class="etiqueta etiqueta-ok"><?= e($avance['enviados']) ?> enviados</span>
        <span class="etiqueta etiqueta-alerta"><?= e($avance['pendientes']) ?> pendientes</span>
        <span class="etiqueta"><?= e($avance['omitidos']) ?> omitidos</span>
    </section>
    <?php if ($siguiente): ?>
        <article class="tarjeta">
            <h2>Mensaje para <?= e($siguiente['nombre']) ?></h2>
            <p><?= nl2br(e($siguiente['texto'])) ?></p>
            <p class="suave">Toca Enviar en WhatsApp. Al abrirlo, la campaña avanza al siguiente mensaje.</p>
            <form method="post" class="formulario" id="envio" action="<?= e($ruta) ?>">
                <?= campo_csrf() ?>
                <input type="hidden" name="mensaje_id" value="<?= e($siguiente['id']) ?>">
                <div class="fila">
                    <button class="boton" name="accion" value="enviado" formtarget="_blank">Abrir WhatsApp</button>
                    <button class="boton boton-sec" name="accion" value="omitido">Omitir mensaje</button>
                </div>
            </form>
            <noscript><p>Al volver de WhatsApp, recarga esta página para ver el siguiente mensaje.</p></noscript>
        </article>
        <script>
        document.getElementById('envio').addEventListener('submit', async function (evento) {
            if (evento.submitter?.value !== 'enviado' || !window.enviarJSON) return;
            evento.preventDefault();
            const pestana = window.open('about:blank', '_blank');
            if (!pestana) {
                window.alert('Permite las ventanas emergentes para abrir WhatsApp.');
                return;
            }
            pestana.opener = null;
            const botones = this.querySelectorAll('button');
            botones.forEach(boton => boton.disabled = true);
            try {
                const respuesta = await enviarJSON(this.action, {
                    accion: 'enviado',
                    mensaje_id: this.elements.mensaje_id.value
                });
                if (respuesta.whatsapp) {
                    pestana.location.replace(respuesta.whatsapp);
                } else {
                    pestana.close();
                }
                window.location.reload();
            } catch {
                pestana.close();
                botones.forEach(boton => boton.disabled = false);
                window.alert('No pude confirmar la apertura. Recarga la página para revisar el avance.');
            }
        });
        </script>
    <?php else: ?>
        <section class="tarjeta">
            <h2>Campaña terminada</h2>
            <p class="suave">Ya revisaste todos los mensajes. Puedes crear una nueva campaña.</p>
            <a class="boton" href="campana.php?nuevo=1">Nueva campaña</a>
        </section>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    return;
}

$filtros = filtros_campana();
$filtro = (string) ($_GET['filtro'] ?? ($individual ? 'activos' : ''));
$opciones = ['producto_id' => (string) ($_GET['producto_id'] ?? ''), 'dias' => (string) ($_GET['dias'] ?? '30')];
$ruta = 'campana.php?' . http_build_query(array_merge(
    ['nuevo' => 1, 'filtro' => $filtro],
    $individual ? ['individual' => 1] : $opciones
));
$nombre = (string) ($_POST['nombre'] ?? '');
$texto = (string) ($_POST['texto'] ?? '');
$plantilla_id = (int) ($_POST['plantilla_id'] ?? 0);
$plantillas = array_filter(plantillas_listar(), static fn(array $fila) => (bool) $fila['activa']);
$productos = $individual ? [] : db()->query('SELECT id, nombre, presentacion FROM productos ORDER BY nombre, id')->fetchAll();
$destinatarios = [];
$seleccion = [];
$previa = null;
$error = '';

try {
    if ($filtro !== '') {
        $destinatarios = campana_destinatarios($individual ? 'activos' : $filtro, $opciones);
    }
    if (!$post) {
        $seleccion = $individual ? [] : array_column($destinatarios, 'id');
    } else {
        $ids = $_POST['clientes'] ?? [];
        if (isset($_POST['seleccion']) && is_string($_POST['seleccion'])) {
            $ids = explode(',', $_POST['seleccion']);
        }
        $solicitados = array_fill_keys(array_map('intval', (array) $ids), true);
        $seleccion = array_values(array_filter(
            array_column($destinatarios, 'id'),
            static fn(int $id): bool => isset($solicitados[$id])
        ));

        $accion = $_POST['accion'] ?? '';
        if ($accion === 'usar_plantilla') {
            $plantilla = plantilla_obtener($plantilla_id);
            if (!$plantilla || !$plantilla['activa']) {
                throw new InvalidArgumentException('Elige una plantilla activa.');
            }
            $texto = $plantilla['texto'];
        } elseif ($accion === 'previa' || $accion === 'crear' || $accion === 'abrir') {
            if (!$seleccion || ($individual && count($seleccion) !== 1)) {
                throw new InvalidArgumentException($individual ? 'Elige un cliente.' : 'Elige al menos un cliente.');
            }
            if ($accion === 'crear' && !$individual) {
                $id = campana_crear($nombre, $texto, $seleccion, campana_texto_filtro($filtro, $opciones));
                avisar('ok', 'Campaña creada.');
                redirigir('campana.php?id=' . $id);
            }
            if ($accion === 'abrir' && $individual) {
                $destino = mensaje_individual_enviar($seleccion[0], $texto);
                avisar('ok', 'Mensaje marcado como enviado. Toca Enviar en WhatsApp.');
                redirigir($destino);
            }
            if (trim($texto) === '') {
                throw new InvalidArgumentException('Escribe el texto del mensaje.');
            }
            $cliente = cliente_obtener($seleccion[0]);
            $previa = ['nombre' => $cliente['nombre'], 'texto' => mensaje_para_cliente($seleccion[0], $texto)];
        } else {
            throw new InvalidArgumentException('Acción no reconocida.');
        }
    }
} catch (InvalidArgumentException $ex) {
    $error = $ex->getMessage();
}
$titulo = $individual ? 'Escribir a un cliente' : 'Nueva campaña';
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <div>
        <h1><?= e($titulo) ?></h1>
        <p class="suave"><?= $individual ? 'Elige un cliente y prepara su mensaje de WhatsApp.' : 'Elige los destinatarios y prepara el mensaje de WhatsApp.' ?></p>
    </div>
    <a class="boton boton-sec" href="mensajes.php">Volver a mensajes</a>
</div>
<?php if (!$individual): ?>
    <form method="get" class="tarjeta formulario" action="campana.php" id="filtros" autocomplete="off">
        <input type="hidden" name="nuevo" value="1">
        <h2>Buscar destinatarios</h2>
        <div class="fila">
            <div class="campo">
                <label for="filtro">Filtro de destinatarios</label>
                <select name="filtro" id="filtro" required>
                    <option value="">Elige un filtro</option>
                    <?php foreach ($filtros as $clave => $etiqueta): ?>
                        <option value="<?= e($clave) ?>" <?= $filtro === $clave ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="filtro-producto">
                <div class="campo">
                    <label for="producto_id">Producto</label>
                    <select name="producto_id" id="producto_id">
                        <option value="">Elige un producto</option>
                        <?php foreach ($productos as $producto): ?>
                            <option value="<?= e($producto['id']) ?>" <?= (int) $opciones['producto_id'] === (int) $producto['id'] ? 'selected' : '' ?>>
                                <?= e(trim($producto['nombre'] . ' ' . $producto['presentacion'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div id="filtro-dias">
                <div class="campo">
                    <label for="dias">Días sin pedidos</label>
                    <input type="text" id="dias" name="dias" inputmode="numeric" value="<?= e($opciones['dias']) ?>">
                </div>
            </div>
        </div>
        <div><button class="boton" type="submit">Buscar destinatarios</button></div>
    </form>
    <script>
    const filtroCampana = document.getElementById('filtro');
    function mostrarOpciones() {
        for (const [id, tipo] of [['producto_id', 'producto'], ['dias', 'sin_pedidos']]) {
            const campo = document.getElementById(id);
            const mostrar = filtroCampana.value === tipo;
            campo.closest('[id^="filtro-"]').hidden = !mostrar;
            campo.disabled = !mostrar;
            campo.required = mostrar;
        }
    }
    filtroCampana.addEventListener('change', mostrarOpciones);
    mostrarOpciones();
    </script>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <p class="aviso aviso-error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
<?php if ($destinatarios): ?>
    <?php $marcados = array_fill_keys($seleccion, true); ?>
    <form method="post" class="tarjeta formulario" action="<?= e($ruta) ?>" id="redactar" autocomplete="off">
        <?= campo_csrf() ?>
        <h2>Redactar mensaje</h2>
        <?php if ($individual): ?>
            <div class="campo">
                <label for="cliente">Cliente</label>
                <select name="clientes[]" id="cliente" required>
                    <option value="">Elige un cliente</option>
                    <?php foreach ($destinatarios as $cliente): ?>
                        <option value="<?= e($cliente['id']) ?>" <?= isset($marcados[$cliente['id']]) ? 'selected' : '' ?>>
                            <?= e($cliente['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php else: ?>
            <fieldset class="formulario">
                <legend>Destinatarios</legend>
                <div class="fila">
                    <?php foreach ($destinatarios as $cliente): ?>
                        <div class="campo">
                            <label class="casilla boton-texto" for="cliente-<?= e($cliente['id']) ?>">
                                <input type="checkbox" name="clientes[]" id="cliente-<?= e($cliente['id']) ?>"
                                       value="<?= e($cliente['id']) ?>" <?= isset($marcados[$cliente['id']]) ? 'checked' : '' ?>>
                                <span>
                                    <?= e($cliente['nombre']) ?>
                                    <?php if (!telefono_sirve_whatsapp($cliente['telefono'])): ?>
                                        (sin celular válido)
                                    <?php endif; ?>
                                </span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <div class="campo">
                <label for="nombre">Nombre de la campaña</label>
                <input type="text" id="nombre" name="nombre" value="<?= e($nombre) ?>" maxlength="120" required>
            </div>
        <?php endif; ?>
        <div class="fila">
            <div class="campo">
                <label for="plantilla_id">Plantilla</label>
                <select name="plantilla_id" id="plantilla_id">
                    <option value="">Escribir texto libre</option>
                    <?php foreach ($plantillas as $plantilla): ?>
                        <option value="<?= e($plantilla['id']) ?>" <?= $plantilla_id === (int) $plantilla['id'] ? 'selected' : '' ?>>
                            <?= e($plantilla['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="boton boton-sec" name="accion" value="usar_plantilla" formnovalidate>Cargar plantilla</button>
        </div>
        <div class="campo">
            <label for="texto">Texto del mensaje</label>
            <textarea id="texto" name="texto" rows="5" maxlength="4000" required
                      aria-describedby="ayuda-texto"><?= e($texto) ?></textarea>
            <span class="ayuda" id="ayuda-texto">Marcadores disponibles: {nombre}, {producto}, {guia}, {transportadora}, {total}, {enlace} y {fecha}.</span>
        </div>
        <?php if ($previa): ?>
            <div class="tarjeta" aria-live="polite">
                <h2>Vista previa para <?= e($previa['nombre']) ?></h2>
                <p><?= nl2br(e($previa['texto'])) ?></p>
            </div>
        <?php endif; ?>
        <div class="fila">
            <button class="boton boton-sec" name="accion" value="previa" formnovalidate>Ver vista previa</button>
            <?php if ($individual): ?>
                <button class="boton" name="accion" value="abrir" formtarget="_blank">Abrir WhatsApp</button>
            <?php else: ?>
                <button class="boton" name="accion" value="crear">Crear campaña</button>
            <?php endif; ?>
        </div>
    </form>
    <?php if (!$individual): ?>
        <script>
        document.getElementById('redactar').addEventListener('formdata', function (evento) {
            const datos = evento.formData;
            datos.set('seleccion', datos.getAll('clientes[]').join(','));
            datos.delete('clientes[]');
        });
        </script>
    <?php endif; ?>
<?php elseif ($filtro !== '' && $error === ''): ?>
    <section class="tarjeta">
        <h2>Sin destinatarios</h2>
        <?php if ($individual): ?>
            <p class="suave">Cuando haya clientes activos podrás elegir a quién escribirle.</p>
            <a class="boton boton-sec" href="mensajes.php">Volver a mensajes</a>
        <?php else: ?>
            <p class="suave">Prueba otro filtro para encontrar destinatarios.</p>
            <a class="boton boton-sec" href="#filtro">Cambiar filtro</a>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
