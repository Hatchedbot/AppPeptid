<?php
require_once RAIZ . '/includes/mensajes.php';

function mensajes_cliente_prueba(string $nombre, bool $activo = true, string $telefono = '3000000000'): int
{
    db()->prepare('INSERT INTO clientes (nombre, telefono, activo, creado, actualizado) VALUES (?, ?, ?, ?, ?)')
        ->execute([$nombre, $telefono, (int) $activo, ahora(), ahora()]);
    return (int) db()->lastInsertId();
}

function mensajes_caso_prueba(int $cliente, string $estado, ?string $contacto): int
{
    db()->prepare('INSERT INTO casos (cliente_id, titulo, estado, fecha_inicio, proximo_contacto, creado, actualizado)
                   VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$cliente, 'Proceso de prueba', $estado, hoy(), $contacto, ahora(), ahora()]);
    return (int) db()->lastInsertId();
}

function mensajes_producto_prueba(): int
{
    db()->prepare('INSERT INTO productos (nombre, presentacion, categoria) VALUES (?, ?, ?)')->execute(['Producto de prueba', 'Presentación de prueba', 'Prueba']);
    return (int) db()->lastInsertId();
}

function mensajes_pedido_prueba(int $cliente, int $producto, string $estado, string $creado): string
{
    $token = token_aleatorio();
    db()->prepare('INSERT INTO pedidos (cliente_id, estado, token, total, transportadora, guia, creado, actualizado)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$cliente, $estado, $token, 25000, 'Transportadora de prueba', 'GUIA-PRUEBA', $creado, ahora()]);
    $id = db()->lastInsertId();
    db()->prepare('INSERT INTO pedido_items (pedido_id, producto_id, producto_nombre, cantidad, precio_unitario, subtotal)
                   VALUES (?, ?, ?, 1, 25000, 25000)')
        ->execute([$id, $producto, 'Producto de prueba']);
    return $token;
}

prueba('rellena nombre, fecha y datos del pedido de cada cliente', function () {
    $cliente = mensajes_cliente_prueba('Ámbar Ejemplo');
    $otro = mensajes_cliente_prueba('Cliente Dos');
    $producto = mensajes_producto_prueba();
    $token = mensajes_pedido_prueba($cliente, $producto, 'despachado', ahora());
    mensajes_pedido_prueba($cliente, $producto, 'cancelado', ahora());

    igual('Hola Ámbar, Producto de prueba GUIA-PRUEBA Transportadora de prueba $ 25.000 '
        . url_base() . '/pedido_estado.php?t=' . $token . ' ' . fecha_corta(hoy()),
        mensaje_para_cliente($cliente, 'Hola {nombre}, {producto} {guia} {transportadora} {total} {enlace} {fecha} {otro}'));
    igual('Hola Cliente', mensaje_para_cliente($otro, 'Hola {nombre} {guia} {producto} {enlace}'));
});

prueba('usa el plan activo para producto y enlace cuando el cliente no tiene pedido', function () {
    $cliente = mensajes_cliente_prueba('Plan Ejemplo');
    $caso = mensajes_caso_prueba($cliente, 'activo', null);
    $token = token_aleatorio();
    db()->prepare('INSERT INTO planes (caso_id, version, token, creado) VALUES (?, 1, ?, ?)')
        ->execute([$caso, $token, ahora()]);
    $plan = db()->lastInsertId();
    db()->prepare('INSERT INTO plan_items (plan_id, producto_nombre) VALUES (?, ?)')->execute([$plan, 'Ítem de prueba']);

    igual('Ítem de prueba ' . url_base() . '/plan.php?t=' . $token, mensaje_para_cliente($cliente, '{producto} {enlace}'));
});

prueba('cada filtro incluye solo los clientes activos que cumplen sus condiciones', function () {
    $alba = mensajes_cliente_prueba('Alba Filtro');
    $bruno = mensajes_cliente_prueba('Bruno Filtro');
    $cora = mensajes_cliente_prueba('Cora Filtro');
    $dario = mensajes_cliente_prueba('Darío Filtro');
    $inactivo = mensajes_cliente_prueba('Inactivo Filtro', false);
    $ayer = date('Y-m-d', strtotime('-1 day'));
    mensajes_caso_prueba($alba, 'activo', $ayer);
    mensajes_caso_prueba($alba, 'activo', null);
    mensajes_caso_prueba($bruno, 'activo', hoy());
    mensajes_caso_prueba($cora, 'pausado', $ayer);
    mensajes_caso_prueba($inactivo, 'activo', $ayer);

    $producto = mensajes_producto_prueba();
    $otro = mensajes_producto_prueba();
    mensajes_pedido_prueba($alba, $producto, 'confirmado', ahora());
    mensajes_pedido_prueba($bruno, $producto, 'cotizado', ahora());
    mensajes_pedido_prueba($cora, $producto, 'cancelado', ahora());
    mensajes_pedido_prueba($cora, $otro, 'entregado', ahora());
    mensajes_pedido_prueba($dario, $producto, 'entregado', date('Y-m-d H:i:s', strtotime('-31 days')));
    mensajes_pedido_prueba($inactivo, $producto, 'entregado', ahora());

    $grupo = [$alba, $bruno, $cora, $dario, $inactivo];
    $ids = static fn(string $filtro, array $opciones = []): array => array_values(array_intersect(
        array_column(campana_destinatarios($filtro, $opciones), 'id'), $grupo
    ));
    igual([$alba, $bruno, $cora, $dario], $ids('activos'));
    igual([$alba, $bruno], $ids('proceso'));
    igual([$alba], $ids('atrasados'));
    igual([$alba, $dario], $ids('producto', ['producto_id' => $producto]));
    igual([$dario], $ids('sin_pedidos', ['dias' => '30']));
});

prueba('crea un mensaje pendiente por cliente y registra enviado, omitido y omisión automática una sola vez', function () {
    usuario_prueba();
    $alba = mensajes_cliente_prueba('Alba Campaña');
    $bruno = mensajes_cliente_prueba('Bruno Campaña');
    $cora = mensajes_cliente_prueba('Cora Campaña', true, 'sin celular');
    $id = campana_crear('Campaña de prueba', 'Hola {nombre}', [$alba, $bruno, $alba, $cora], 'activos');
    igual(['enviados' => 0, 'pendientes' => 3, 'omitidos' => 0, 'total' => 3], campana_avance($id));
    $primero = mensaje_siguiente($id);
    igual($alba, (int) $primero['cliente_id']);
    igual('Hola Alba', $primero['texto']);
    igual('whatsapp_manual', $primero['canal']);
    mensaje_marcar((int) $primero['id'], 'enviado');
    igual(false, mensaje_marcar((int) $primero['id'], 'enviado'));
    verdadero(mensaje_obtener((int) $primero['id'])['enviado']);
    try {
        mensaje_marcar((int) $primero['id'], 'omitido');
        throw new RuntimeException('El mensaje ya enviado debía conservar su estado.');
    } catch (InvalidArgumentException $ex) {
        igual('enviado', mensaje_obtener((int) $primero['id'])['estado']);
    }

    $segundo = mensaje_siguiente($id);
    igual('Hola Bruno', $segundo['texto']);
    mensaje_marcar((int) $segundo['id'], 'omitido');
    igual(null, mensaje_obtener((int) $segundo['id'])['enviado']);
    igual(1, campana_omitir_sin_telefono($id));
    igual(0, campana_omitir_sin_telefono($id));
    igual(null, mensaje_siguiente($id));
    igual(['enviados' => 1, 'pendientes' => 0, 'omitidos' => 2, 'total' => 3], campana_avance($id));
    $consulta = db()->prepare("SELECT COUNT(*) FROM eventos WHERE tipo = 'mensaje' AND cliente_id IN (?, ?, ?)");
    $consulta->execute([$alba, $bruno, $cora]);
    igual(3, (int) $consulta->fetchColumn());
});

prueba('revierte toda la campaña cuando un destinatario no existe', function () {
    $cliente = mensajes_cliente_prueba('Cliente para reversión');
    $total = (int) db()->query('SELECT COUNT(*) FROM campanas')->fetchColumn();
    $mensajes = (int) db()->query('SELECT COUNT(*) FROM mensajes')->fetchColumn();
    try {
        campana_crear('Campaña incompleta', 'Hola {nombre}', [$cliente, PHP_INT_MAX], 'activos');
        throw new RuntimeException('Se esperaba un error por el cliente inexistente.');
    } catch (InvalidArgumentException $ex) {
        contiene('No encontré ese cliente', $ex->getMessage());
    }
    igual($total, (int) db()->query('SELECT COUNT(*) FROM campanas')->fetchColumn());
    igual($mensajes, (int) db()->query('SELECT COUNT(*) FROM mensajes')->fetchColumn());
});

prueba('crea, edita, desactiva y reactiva una plantilla', function () {
    $id = plantilla_crear('Plantilla de prueba', 'Hola {nombre}');
    igual('Hola {nombre}', plantilla_texto('Plantilla de prueba'));
    plantilla_actualizar($id, 'Plantilla editada de prueba', 'Hola de nuevo {nombre}');
    plantilla_cambiar_activa($id, false);
    igual('', plantilla_texto('Plantilla editada de prueba'));
    plantilla_cambiar_activa($id, true);
    igual('Hola de nuevo {nombre}', plantilla_texto('Plantilla editada de prueba'));
});

prueba('abre WhatsApp para un cliente y registra el mensaje individual', function () {
    $cliente = mensajes_cliente_prueba('Cliente Individual');
    igual(enlace_whatsapp('3000000000', 'Hola Cliente'), mensaje_individual_enviar($cliente, 'Hola {nombre}'));
    $consulta = db()->prepare('SELECT campana_id, estado, texto FROM mensajes WHERE cliente_id = ?');
    $consulta->execute([$cliente]);
    igual(['campana_id' => null, 'estado' => 'enviado', 'texto' => 'Hola Cliente'], $consulta->fetch());
});
