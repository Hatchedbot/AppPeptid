<?php
/**
 * POST JSON {texto} con la cabecera X-CSRF.
 * Responde {respuesta, accion, url}. La URL siempre la arma el servidor.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/app.php';
require dirname(__DIR__) . '/includes/ia.php';

$usuario = requerir_permiso('lia');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_respuesta(['respuesta' => 'Escríbeme lo que quieres saber.', 'accion' => 'ninguna', 'url' => '']);
}

$cuerpo = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($cuerpo)) {
    $cuerpo = $_POST;
}

$texto = trim((string) ($cuerpo['texto'] ?? ''));
if ($texto === '') {
    json_respuesta([
        'respuesta' => 'Escríbeme qué quieres saber de un cliente o de sus casos.',
        'accion' => 'ninguna',
        'url' => '',
    ]);
}

try {
    $respuesta = lia_responder($usuario, $texto, $_SESSION['lia_historial'] ?? []);
} catch (RuntimeException $ex) {
    $mensaje = $ex->getMessage() === 'Se agotó por hoy el uso de la IA'
        ? 'Se agotó por hoy el uso de la IA. Inténtalo mañana.'
        : 'No pude responder ahora. Inténtalo de nuevo en un momento.';
    json_respuesta(['respuesta' => $mensaje, 'accion' => 'ninguna', 'url' => '']);
} catch (Throwable $ex) {
    json_respuesta([
        'respuesta' => 'No pude responder ahora. Inténtalo de nuevo en un momento.',
        'accion' => 'ninguna',
        'url' => '',
    ]);
}

json_respuesta($respuesta);
