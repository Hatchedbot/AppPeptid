<?php
/**
 * Cabecera común. Antes de incluirla, la página define $titulo (y opcionalmente
 * $activo = 'inicio' | 'clientes' | 'pedidos' | 'inventario' | 'mensajes' | 'usuarios').
 * El menú solo muestra las pantallas que ya existen y que el usuario puede ver.
 */
$u = usuario_actual();
$titulo = $titulo ?? config('app.nombre');
$activo = $activo ?? '';
$menu = [
    'inicio'     => ['index.php', 'Inicio', null],
    'clientes'   => ['clientes.php', 'Clientes', 'clientes'],
    'pedidos'    => ['pedidos.php', 'Pedidos', 'pedidos'],
    'inventario' => ['inventario.php', 'Inventario', 'inventario'],
    'mensajes'   => ['mensajes.php', 'Mensajes', 'mensajes'],
    'usuarios'   => ['usuarios.php', 'Usuarios', 'usuarios'],
];
// Íconos de línea (estilo Lucide) para el menú. Son decorativos: el texto va al lado.
$iconos = [
    'inicio'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
    'clientes'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18 14a6.5 6.5 0 0 1 3.5 6"/>',
    'pedidos'    => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'inventario' => '<path d="m21 8-9-5-9 5v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
    'mensajes'   => '<path d="M21 12a8.5 8.5 0 0 1-12.6 7.4L3 21l1.6-5.2A8.5 8.5 0 1 1 21 12Z"/>',
    'usuarios'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
];
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e($titulo) ?> · <?= e(config('app.nombre')) ?></title>
<meta name="theme-color" content="#ffffff">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600&family=Inter:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="assets/estilos.css?v=<?= @filemtime(RAIZ . '/assets/estilos.css') ?>">
</head>
<body>
<div class="fondo-liquido" aria-hidden="true"><span></span><span></span><span></span></div>
<?php if ($u): ?>
<svg class="bisel-recursos" aria-hidden="true" width="0" height="0" focusable="false"><defs></defs></svg>
<template id="bisel-plantilla">
    <svg><filter x="0%" y="0%" width="100%" height="100%" color-interpolation-filters="sRGB">
        <feImage x="0" y="0" preserveAspectRatio="none" result="mapa"/>
        <?php foreach (['r' => '32', 'g' => '33.28', 'b' => '34.56'] as $canal => $escala): ?>
            <feDisplacementMap in="SourceGraphic" in2="mapa" scale="<?= e($escala) ?>"
                xChannelSelector="R" yChannelSelector="G" result="desplazado"/>
            <feColorMatrix in="desplazado" type="matrix" result="<?= e($canal) ?>"
                values="<?= e(($canal === 'r' ? '1' : '0') . ' 0 0 0 0 0 ' . ($canal === 'g' ? '1' : '0')
                    . ' 0 0 0 0 0 ' . ($canal === 'b' ? '1' : '0') . ' 0 0 0 0 0 1 0') ?>"/>
        <?php endforeach; ?>
        <feBlend in="r" in2="g" mode="screen" result="rg"/>
        <feBlend in="rg" in2="b" mode="screen" result="rgb"/>
        <feColorMatrix in="mapa" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 1 0 0" result="canto"/>
        <feComposite in="rgb" in2="canto" operator="in" result="borde"/>
        <feComposite in="borde" in2="SourceGraphic" operator="over"/>
    </filter></svg>
</template>
<header class="barra">
    <a class="marca" href="index.php"><span class="marca-punto" aria-hidden="true"></span><?= e(config('app.nombre')) ?></a>
    <form method="post" action="logout.php" class="salir">
        <?= campo_csrf() ?>
        <?php $nombre_corto = primer_nombre($u['nombre']); ?>
        <span class="quien">
            <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($nombre_corto, 0, 1))) ?></span>
            <span class="quien-nombre"><?= e($nombre_corto) ?></span>
        </span>
        <button type="submit" class="boton-texto">Salir</button>
    </form>
</header>
<?php /* El menú va fuera de la barra: el vidrio solo refracta lo que tiene detrás si no está dentro de otra capa. */ ?>
<nav class="menu" aria-label="Menú principal">
        <?php foreach ($menu as $clave => [$archivo, $texto, $permiso]): ?>
            <?php if (is_file(RAIZ . '/' . $archivo) && ($permiso === null || puede($permiso))): ?>
                <a href="<?= e($archivo) ?>" aria-label="<?= e($texto) ?>" class="<?= $activo === $clave ? 'activo' : '' ?>"<?= $activo === $clave ? ' aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconos[$clave] ?></svg>
                    <span><?= e($texto) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
</nav>
<?php endif; ?>
<main class="contenido">
<?php foreach (tomar_avisos() as $a): ?>
    <div class="aviso aviso-<?= e($a['tipo']) ?>"><?= e($a['mensaje']) ?></div>
<?php endforeach; ?>
