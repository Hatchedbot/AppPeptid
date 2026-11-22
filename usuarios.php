<?php
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/usuarios.php';

$administrador = requerir_permiso('usuarios');
$id = (int) ($_GET['id'] ?? 0);
$nuevo = ($_GET['nuevo'] ?? '') === '1';
$cuenta = $id ? usuario_obtener($id) : null;
if ($id && !$cuenta) {
    avisar('error', 'Usuario no encontrado.');
    redirigir('usuarios.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destino = $id ? 'usuarios.php?id=' . $id : 'usuarios.php?nuevo=1';
    try {
        switch ($_POST['accion'] ?? '') {
            case 'guardar':
                $id = usuario_guardar([
                    'id' => $id,
                    'nombre' => $_POST['nombre'] ?? '',
                    'usuario' => $_POST['usuario'] ?? '',
                    'rol' => $_POST['rol'] ?? '',
                    'activo' => ($_POST['activo'] ?? '') === '1',
                    'clave' => $_POST['clave'] ?? '',
                ]);
                $destino = 'usuarios.php?id=' . $id;
                avisar('ok', $cuenta ? 'Usuario actualizado.' : 'Usuario creado.');
                break;
            case 'permisos':
                usuario_guardar_permisos($id, ($_POST['modo'] ?? '') === 'propios', $_POST['permisos'] ?? []);
                avisar('ok', 'Permisos guardados.');
                break;
            case 'clave':
                usuario_cambiar_clave($id, $_POST['clave'] ?? '');
                avisar('ok', 'Contraseña actualizada.');
                break;
            default:
                throw new InvalidArgumentException('Acción no reconocida.');
        }
    } catch (InvalidArgumentException $ex) {
        avisar('error', $ex->getMessage());
    }
    redirigir($destino);
}

$activo = 'usuarios';
$titulo = $cuenta ? 'Editar usuario' : ($nuevo ? 'Nuevo usuario' : 'Usuarios');
require __DIR__ . '/includes/header.php';
?>
<div class="encabezado">
    <h1><?= e($titulo) ?></h1>
    <?php if ($nuevo || $cuenta): ?>
        <a class="boton boton-sec" href="usuarios.php">Volver a usuarios</a>
    <?php else: ?>
        <a class="boton" href="usuarios.php?nuevo=1"><?= icono('mas') ?> Nuevo usuario</a>
    <?php endif; ?>
</div>

<?php if ($nuevo || $cuenta): ?>
    <form method="post" class="tarjeta formulario" id="formulario-usuario">
        <h2 class="titulo-icono"><?= icono('usuario') ?> Datos de la cuenta</h2>
        <?= campo_csrf() ?>
        <input type="hidden" name="accion" value="guardar">
        <div class="campo">
            <label for="nombre">Nombre</label>
            <input id="nombre" type="text" name="nombre" value="<?= e($cuenta['nombre'] ?? '') ?>" maxlength="100" required>
        </div>
        <div class="campo">
            <label for="usuario">Usuario</label>
            <input id="usuario" type="text" name="usuario" value="<?= e($cuenta['usuario'] ?? '') ?>" maxlength="60" autocomplete="off" autocapitalize="none" spellcheck="false" required>
        </div>
        <div class="fila">
            <div class="campo">
                <label for="rol">Rol</label>
                <select id="rol" name="rol">
                    <?php foreach (ROLES as $valor => $etiqueta): ?>
                        <option value="<?= e($valor) ?>" <?= ($cuenta['rol'] ?? 'asesor') === $valor ? 'selected' : '' ?>><?= e($etiqueta) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php $estado = (int) ($cuenta['activo'] ?? 1); ?>
            <?php if ($id === (int) $administrador['id']): ?>
                <input type="hidden" name="activo" value="1">
            <?php else: ?>
                <div class="campo">
                    <label for="estado">Estado</label>
                    <select id="estado" name="activo" data-original="<?= e($estado) ?>">
                        <option value="1" <?= $estado ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= !$estado ? 'selected' : '' ?>>Inactivo</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$cuenta): ?>
            <div class="campo">
                <label for="clave-nueva-cuenta">Contraseña (mínimo 8 caracteres)</label>
                <input id="clave-nueva-cuenta" type="password" name="clave" autocomplete="new-password" minlength="8" required>
            </div>
        <?php endif; ?>
        <button class="boton"><?= e($cuenta ? 'Guardar cambios' : 'Crear usuario') ?></button>
    </form>

    <?php if ($cuenta): ?>
        <?php $permisos = permisos_de_usuario($id); ?>
        <form method="post" class="tarjeta formulario">
            <h2 class="titulo-icono"><?= icono('usuarios') ?> Permisos</h2>
            <?= campo_csrf() ?>
            <input type="hidden" name="accion" value="permisos">
            <?php if ($cuenta['rol'] === 'admin'): ?>
                <p class="suave">El rol de administrador tiene acceso a todos los módulos. La selección personalizada se aplica si la cuenta pasa a ser de asesor.</p>
            <?php endif; ?>
            <div class="campo">
                <label for="modo-permisos">Modo de permisos</label>
                <select id="modo-permisos" name="modo">
                    <option value="rol" <?= !$cuenta['permisos_propios'] ? 'selected' : '' ?>>Usa los permisos de su rol</option>
                    <option value="propios" <?= $cuenta['permisos_propios'] ? 'selected' : '' ?>>Permisos personalizados</option>
                </select>
            </div>
            <fieldset id="permisos-personalizados">
                <legend>Módulos permitidos</legend>
                <?php foreach (PERMISOS_NOMBRES as $permiso => $etiqueta): ?>
                    <div>
                        <input id="permiso-<?= e($permiso) ?>" type="checkbox" name="permisos[]" value="<?= e($permiso) ?>" <?= in_array($permiso, $permisos, true) ? 'checked' : '' ?>>
                        <label for="permiso-<?= e($permiso) ?>"><?= e($etiqueta) ?></label>
                    </div>
                <?php endforeach; ?>
            </fieldset>
            <button class="boton">Guardar permisos</button>
        </form>

        <details class="tarjeta">
            <summary class="boton boton-sec"><?= icono('llave') ?> Cambiar contraseña</summary>
            <form method="post" class="formulario">
                <?= campo_csrf() ?>
                <input type="hidden" name="accion" value="clave">
                <div class="campo">
                    <label for="clave-reemplazo">Nueva contraseña (mínimo 8 caracteres)</label>
                    <input id="clave-reemplazo" type="password" name="clave" autocomplete="new-password" minlength="8" required>
                </div>
                <button class="boton">Guardar contraseña</button>
            </form>
        </details>

        <script>
            const estado = document.querySelector('#estado');
            document.querySelector('#formulario-usuario').addEventListener('submit', (evento) => {
                if (estado && estado.value !== estado.dataset.original) {
                    const mensaje = estado.value === '1' ? '¿Quieres activar esta cuenta?' : '¿Quieres desactivar esta cuenta?';
                    if (!confirm(mensaje)) evento.preventDefault();
                }
            });

            const modo = document.querySelector('#modo-permisos');
            const casillas = document.querySelector('#permisos-personalizados');
            function mostrarPermisos() {
                casillas.hidden = casillas.disabled = modo.value !== 'propios';
            }
            modo.addEventListener('change', mostrarPermisos);
            mostrarPermisos();
        </script>
    <?php endif; ?>
<?php else: ?>
    <?php $usuarios = usuarios_listar(); ?>
    <section class="tarjeta">
        <?php if (!$usuarios): ?>
            <p class="suave">Todavía no hay usuarios registrados.</p>
            <a class="boton" href="usuarios.php?nuevo=1">Nuevo usuario</a>
        <?php else: ?>
            <table class="tabla tabla-movil">
                <thead>
                    <tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Permisos</th><th>Acción</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $fila): ?>
                        <?php
                        $resumen = 'Permisos del rol';
                        if ($fila['rol'] === 'admin') {
                            $resumen = 'Todos los módulos';
                        } elseif ($fila['permisos_propios']) {
                            $nombres = array_intersect_key(PERMISOS_NOMBRES, array_flip(permisos_de_usuario((int) $fila['id'])));
                            $resumen = $nombres ? implode(', ', $nombres) : 'Sin permisos';
                        }
                        ?>
                        <tr>
                            <td><strong><?= e($fila['nombre']) ?></strong></td>
                            <td><?= e($fila['usuario']) ?></td>
                            <td><?= e(ROLES[$fila['rol']] ?? $fila['rol']) ?></td>
                            <td><span class="etiqueta <?= e($fila['activo'] ? 'etiqueta-ok' : 'etiqueta-alerta') ?>"><?= e($fila['activo'] ? 'Activo' : 'Inactivo') ?></span></td>
                            <td><?= e($resumen) ?></td>
                            <td><div class="fila"><a class="boton boton-sec" href="usuarios.php?id=<?= e($fila['id']) ?>" aria-label="<?= e('Editar a ' . $fila['nombre']) ?>">Editar</a></div></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
