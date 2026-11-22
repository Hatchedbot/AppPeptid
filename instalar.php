<?php
/** Crea el primer administrador. Solo funciona mientras no exista ningún usuario. */
require __DIR__ . '/includes/app.php';

if (db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()) {
    redirigir('login.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');
    if ($nombre === '' || $usuario === '') {
        $error = 'Escribe el nombre y el usuario.';
    } elseif (mb_strlen($clave) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($clave !== ($_POST['clave2'] ?? '')) {
        $error = 'Las dos contraseñas no coinciden.';
    } else {
        crear_usuario($nombre, $usuario, $clave, 'admin');
        entrar($usuario, $clave);
        avisar('ok', 'Listo. Ya eres el administrador.');
        redirigir('index.php');
    }
}

$titulo = 'Primer uso';
require __DIR__ . '/includes/header.php';
?>
<div class="caja-entrada tarjeta">
    <div class="encabezado">
        <div>
            <h1>Configura tu cuenta</h1>
            <p class="suave">Desde esta cuenta podrás crear las de los asesores.</p>
        </div>
    </div>
    <?php if ($error): ?><div class="aviso aviso-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <h2>Cuenta de administrador</h2>
        <div class="campo">
            <label for="nombre">Tu nombre</label>
            <input type="text" id="nombre" name="nombre" autocomplete="name" required value="<?= e($_POST['nombre'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="usuario">Usuario para entrar</label>
            <input type="text" id="usuario" name="usuario" required autocomplete="username" autocapitalize="none"
                   value="<?= e($_POST['usuario'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="clave">Contraseña</label>
            <input type="password" id="clave" name="clave" required minlength="8" autocomplete="new-password"
                   aria-describedby="ayuda-clave">
            <span class="ayuda" id="ayuda-clave">Usa al menos 8 caracteres.</span>
        </div>
        <div class="campo">
            <label for="clave2">Repite la contraseña</label>
            <input type="password" id="clave2" name="clave2" required minlength="8" autocomplete="new-password">
        </div>
        <div class="fila"><button type="submit" class="boton">Crear administrador</button></div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php';
