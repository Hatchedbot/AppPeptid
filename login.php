<?php
require __DIR__ . '/includes/app.php';

if (!db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()) {
    redirigir('instalar.php');
}
if (usuario_actual()) {
    redirigir('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (entrar((string) ($_POST['usuario'] ?? ''), (string) ($_POST['clave'] ?? ''))) {
        redirigir('index.php');
    }
    $error = 'Usuario o contraseña incorrectos.';
}

$titulo = 'Entrar';
require __DIR__ . '/includes/header.php';
?>
<div class="caja-entrada tarjeta">
    <div class="encabezado">
        <div>
            <h1><?= e(config('app.nombre')) ?></h1>
            <p class="suave"><?= e(config('app.negocio')) ?></p>
        </div>
    </div>
    <?php if ($error): ?><div class="aviso aviso-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="formulario">
        <?= campo_csrf() ?>
        <h2>Inicia sesión</h2>
        <div class="campo">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" autocomplete="username" autocapitalize="none" required
                   value="<?= e($_POST['usuario'] ?? '') ?>">
        </div>
        <div class="campo">
            <label for="clave">Contraseña</label>
            <input type="password" id="clave" name="clave" autocomplete="current-password" required>
        </div>
        <div class="fila"><button type="submit" class="boton">Entrar</button></div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php';
