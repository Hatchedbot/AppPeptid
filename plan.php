<?php
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/casos.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');
$token = $_GET['t'] ?? '';
$plan = is_string($token) && $token !== '' ? plan_por_token($token) : null;
if (!$plan) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($plan ? 'Plan de ' . $plan['primer_nombre'] : 'Plan no encontrado') ?></title>
    <link rel="stylesheet" href="assets/estilos.css">
</head>
<body>
<main class="contenido">
    <article class="tarjeta">
        <?php if (!$plan): ?>
            <h1>Plan no encontrado</h1>
            <p>Revisa el enlace o pídele a tu asesor que te lo comparta de nuevo.</p>
        <?php else: ?>
            <h1>Plan de <?= e($plan['primer_nombre']) ?></h1>
            <p class="suave">Versión <?= e($plan['version']) ?> · <?= e(fecha_corta($plan['creado'])) ?></p>
            <?php if ($plan['items']): ?>
                <h2>Ítems del plan</h2>
                <ul>
                    <?php foreach ($plan['items'] as $item): ?>
                        <li>
                            <strong><?= e($item['producto_nombre']) ?></strong>
                            <?php if ($item['dosis'] !== '' && $item['dosis'] !== null): ?> · Dosis: <?= e($item['dosis']) ?><?php endif; ?>
                            <?php if ($item['frecuencia'] !== '' && $item['frecuencia'] !== null): ?> · Frecuencia: <?= e($item['frecuencia']) ?><?php endif; ?>
                            <?php if ($item['duracion'] !== '' && $item['duracion'] !== null): ?> · Duración: <?= e($item['duracion']) ?><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <h2>Indicaciones</h2>
            <p><?= nl2br(e($plan['indicaciones'])) ?></p>
        <?php endif; ?>
    </article>
</main>
</body>
</html>
