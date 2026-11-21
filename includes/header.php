<?php
/**
 * Cabecera común. Antes de incluirla, la página define $titulo (y opcionalmente $activo = 'inicio' | 'clientes' | 'usuarios').
 * El menú solo muestra las pantallas que ya existen y que el usuario puede ver.
 */
$u = usuario_actual();
$titulo = $titulo ?? config('app.nombre');
$activo = $activo ?? '';
$menu = [
    'inicio'   => ['index.php', 'Inicio', null],
    'clientes' => ['clientes.php', 'Clientes', 'clientes'],
    'usuarios' => ['usuarios.php', 'Usuarios', 'usuarios'],
];
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($titulo) ?> · <?= e(config('app.nombre')) ?></title>
<link rel="stylesheet" href="assets/estilos.css?v=<?= @filemtime(RAIZ . '/assets/estilos.css') ?>">
</head>
<body>
<?php if ($u): ?>
<header class="barra">
    <a class="marca" href="index.php"><span class="marca-punto"></span><?= e(config('app.nombre')) ?></a>
    <nav class="menu">
        <?php foreach ($menu as $clave => [$archivo, $texto, $permiso]): ?>
            <?php if (is_file(RAIZ . '/' . $archivo) && ($permiso === null || puede($permiso))): ?>
                <a href="<?= e($archivo) ?>" class="<?= $activo === $clave ? 'activo' : '' ?>"><?= e($texto) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="logout.php" class="salir">
        <?= campo_csrf() ?>
        <span class="quien"><?= e(primer_nombre($u['nombre'])) ?></span>
        <button type="submit" class="boton-texto">Salir</button>
    </form>
</header>
<?php endif; ?>
<main class="contenido">
<?php foreach (tomar_avisos() as $a): ?>
    <div class="aviso aviso-<?= e($a['tipo']) ?>"><?= e($a['mensaje']) ?></div>
<?php endforeach; ?>
