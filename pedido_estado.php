<?php
require __DIR__ . '/includes/app.php';
require_once __DIR__ . '/includes/pedidos.php';

header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex');
$token = $_GET['t'] ?? '';
$pedido = is_string($token) ? pedido_por_token($token) : null;
if (!$pedido) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pedido ? 'Estado de tu pedido' : 'Pedido no encontrado' ?></title>
    <link rel="stylesheet" href="assets/estilos.css">
</head>
<body>
<main class="contenido">
    <?php if (!$pedido): ?>
        <div class="encabezado">
            <div>
                <h1>Pedido no encontrado</h1>
                <p class="suave">Revisa el enlace o pídele a tu asesor que te lo comparta de nuevo.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="encabezado">
            <div>
                <h1>Pedido de <?= e($pedido['primer_nombre']) ?></h1>
                <p class="suave"><?= e(config('app.negocio')) ?></p>
            </div>
            <span class="etiqueta <?= $pedido['estado'] === 'entregado' ? 'etiqueta-ok' : '' ?>"><?= e(PEDIDO_ESTADOS[$pedido['estado']]) ?></span>
        </div>
        <section class="tarjeta">
            <h2>Productos</h2>
            <ul class="lista">
                <?php foreach ($pedido['items'] as $item): ?>
                    <li><?= e($item['producto_nombre']) ?> · <?= e($item['cantidad']) ?> unidades</li>
                <?php endforeach; ?>
            </ul>
            <p>Total <strong><?= e(dinero($pedido['total'])) ?></strong></p>
            <p><span class="etiqueta <?= $pedido['estado_pago'] === 'pagado' ? 'etiqueta-ok' : 'etiqueta-alerta' ?>">Pago <?= e(mb_strtolower(PEDIDO_ESTADOS_PAGO[$pedido['estado_pago']])) ?></span></p>
        </section>
        <?php if ($pedido['guia'] !== null): ?>
            <section class="tarjeta">
                <h2>Envío</h2>
                <p>Transportadora <strong><?= e($pedido['transportadora']) ?></strong></p>
                <p>Número de guía <strong><?= e($pedido['guia']) ?></strong></p>
            </section>
        <?php endif; ?>
        <section class="tarjeta">
            <h2>Historial</h2>
            <ol class="lista">
                <?php foreach ($pedido['historial'] as $paso): ?>
                    <li><?= e(PEDIDO_ESTADOS[$paso['estado']]) ?> · <?= e(fecha_corta($paso['fecha'])) ?></li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
