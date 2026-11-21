<?php
require_once RAIZ . '/includes/pedidos.php';

function cliente_para_pedido(string $nombre = 'Nombre Apellido Ficticio'): int
{
    db()->prepare('INSERT INTO clientes (nombre, telefono, correo, creado, actualizado) VALUES (?, ?, ?, ?, ?)')
        ->execute([$nombre, '000' . token_aleatorio(4), 'cliente@example.invalid', ahora(), ahora()]);
    return (int) db()->lastInsertId();
}

function producto_para_pedido(int $precio = 50000): int
{
    db()->prepare('INSERT INTO productos (nombre, presentacion, categoria, precio) VALUES (?, ?, ?, ?)')
        ->execute(['Artículo de prueba', 'Unidad', 'Prueba', $precio]);
    return (int) db()->lastInsertId();
}

function crear_pedido_de_prueba(array $datos = [], int $stock = 4): array
{
    $producto = producto_para_pedido();
    $lote = $stock > 0 ? inventario_entrada($producto, 'LOTE-PRUEBA', null, $stock) : null;
    $id = pedido_crear($datos + ['cliente_id' => cliente_para_pedido()], [
        ['producto_id' => $producto, 'cantidad' => 2],
    ]);
    return [$id, $producto, $lote];
}

function pedido_debe_fallar(callable $accion, string $mensaje): void
{
    try {
        $accion();
    } catch (InvalidArgumentException $ex) {
        contiene($mensaje, $ex->getMessage());
        return;
    }
    throw new RuntimeException('La operación debía fallar.');
}

prueba('pedido calcula totales, conserva los precios registrados y permite editarlos antes del despacho', function () {
    usuario_prueba();
    $cliente = cliente_para_pedido();
    $producto = producto_para_pedido();
    $otro = producto_para_pedido(10000);
    $id = pedido_crear(['cliente_id' => $cliente, 'descuento' => '5.000'], [
        ['producto_id' => $producto, 'cantidad' => '2'],
        ['producto_id' => $otro, 'cantidad' => 1, 'precio_unitario' => '9.000'],
    ]);
    $pedido = pedido_obtener($id);
    igual(109000, (int) $pedido['subtotal']);
    igual(25000, (int) $pedido['envio']);
    igual(129000, (int) $pedido['total']);
    igual([50000, 9000], array_map('intval', array_column(pedido_items($id), 'precio_unitario')));
    igual(['cotizado'], array_column(pedido_historial($id), 'estado'));

    db()->prepare('UPDATE productos SET precio = 75000 WHERE id = ?')->execute([$producto]);
    pedido_actualizar($id, ['envio' => '10.000']);
    igual(114000, (int) pedido_obtener($id)['total']);
    pedido_cambiar_estado($id, 'confirmado');
    pedido_actualizar($id, ['descuento' => 1000], [['producto_id' => $producto, 'cantidad' => 1]]);
    igual(84000, (int) pedido_obtener($id)['total']);
    igual($pedido['token'], pedido_obtener($id)['token']);
    igual(4, (int) pedido_consulta('SELECT COUNT(*) FROM eventos WHERE cliente_id = ? AND tipo = ?', [$cliente, 'pedido'])->fetchColumn());
});

prueba('pedido valida el cliente, el proceso, el catálogo y los enteros antes de guardar', function () {
    $cliente = cliente_para_pedido();
    $otro = cliente_para_pedido();
    db()->prepare('INSERT INTO casos (cliente_id, titulo, fecha_inicio, creado, actualizado) VALUES (?, ?, ?, ?, ?)')
        ->execute([$cliente, 'Proceso ficticio', hoy(), ahora(), ahora()]);
    $caso = (int) db()->lastInsertId();
    $producto = producto_para_pedido();
    $item = ['producto_id' => $producto, 'cantidad' => 1];
    pedido_debe_fallar(fn() => pedido_crear(['cliente_id' => 0], [$item]), 'cliente válido');
    pedido_debe_fallar(fn() => pedido_crear(['cliente_id' => $otro, 'caso_id' => $caso], [$item]), 'debe pertenecer');
    pedido_debe_fallar(fn() => pedido_crear(['cliente_id' => $cliente], []), 'al menos un producto');
    pedido_debe_fallar(fn() => pedido_crear(['cliente_id' => $cliente], [['producto_id' => 0, 'cantidad' => 1]]), 'catálogo');
    pedido_debe_fallar(fn() => pedido_crear(['cliente_id' => $cliente], [['cantidad' => '1,5'] + $item]), 'cantidad');
    pedido_debe_fallar(fn() => pedido_crear(['cliente_id' => $cliente, 'descuento' => 999999], [$item]), 'total');
    igual([], pedidos_listar(['cliente_id' => $cliente]));
    $id = pedido_crear(['cliente_id' => $cliente, 'caso_id' => $caso], [$item]);
    igual($caso, (int) pedido_obtener($id)['caso_id']);
});

prueba('pagos suma abonos y devoluciones, valida métodos y filtra por estado de pago', function () {
    [$id] = crear_pedido_de_prueba();
    $cliente = (int) pedido_obtener($id)['cliente_id'];
    igual('pendiente', pedido_estado_pago($id));
    igual(0, pedido_pagado($id));
    pedido_debe_fallar(fn() => pago_agregar($id, ['metodo' => 'otro', 'valor' => 1]), 'método');
    pedido_debe_fallar(fn() => pago_agregar($id, ['metodo' => 'nequi', 'valor' => 0]), 'distinto de cero');
    pago_agregar($id, ['metodo' => 'nequi', 'valor' => '25.000', 'referencia' => 'REF-PRUEBA']);
    igual(25000, pedido_pagado($id));
    igual('parcial', pedido_estado_pago($id));
    pago_agregar($id, ['metodo' => 'bre_b', 'valor' => 100000]);
    igual('pagado', pedido_estado_pago($id));
    igual([$id], array_map('intval', array_column(pedidos_listar(['cliente_id' => $cliente, 'estado_pago' => 'pagado']), 'id')));
    igual([], pedidos_listar(['cliente_id' => $cliente, 'estado_pago' => 'parcial']));
    igual([$id], array_map('intval', array_column(pedidos_listar(['q' => "#$id", 'estado' => 'cotizado']), 'id')));
    igual([], pedidos_listar(['q' => '%', 'cliente_id' => $cliente]));
    pago_agregar($id, ['metodo' => 'efectivo', 'valor' => '-50.000']);
    igual(75000, pedido_pagado($id));
    igual('parcial', pedido_estado_pago($id));
    pago_agregar($id, ['metodo' => 'efectivo', 'valor' => -75000]);
    igual('pendiente', pedido_estado_pago($id));
    igual('REF-PRUEBA', pedido_pagos($id)[3]['referencia']);
});

prueba('matriz de pedidos acepta únicamente las seis transiciones previstas', function () {
    $rutas = [
        'cotizado' => [],
        'confirmado' => ['confirmado'],
        'despachado' => ['confirmado', 'despachado'],
        'entregado' => ['confirmado', 'despachado', 'entregado'],
        'cancelado' => ['cancelado'],
        'devuelto' => ['confirmado', 'despachado', 'devuelto'],
    ];
    $permitidas = [
        'cotizado' => ['confirmado', 'cancelado'],
        'confirmado' => ['despachado', 'cancelado'],
        'despachado' => ['entregado', 'devuelto'],
    ];
    $envio = ['transportadora' => 'Transportadora ficticia', 'guia' => 'GUIA-TEST'];
    foreach ($rutas as $origen => $ruta) {
        foreach ([...array_keys($rutas), 'desconocido'] as $destino) {
            [$id] = crear_pedido_de_prueba();
            pago_agregar($id, ['metodo' => 'nequi', 'valor' => 125000]);
            foreach ($ruta as $paso) {
                pedido_cambiar_estado($id, $paso, $envio);
            }
            $historial = ['cotizado', ...$ruta];
            $final = $origen;
            if (in_array($destino, $permitidas[$origen] ?? [], true)) {
                pedido_cambiar_estado($id, $destino, $envio);
                $historial[] = $destino;
                $final = $destino;
            } else {
                pedido_debe_fallar(fn() => pedido_cambiar_estado($id, $destino, $envio), 'estado actual');
            }
            igual($final, pedido_obtener($id)['estado']);
            igual($historial, array_column(pedido_historial($id), 'estado'));
        }
    }
});

prueba('despacho exige pago completo, guía, transportadora y existencias vigentes', function () {
    [$id, $producto, $lote] = crear_pedido_de_prueba([], 1);
    pedido_cambiar_estado($id, 'confirmado');
    $envio = ['transportadora' => 'Transportadora ficticia', 'guia' => 'G-123'];
    pedido_debe_fallar(fn() => pedido_cambiar_estado($id, 'despachado', $envio), 'pago completo');
    pago_agregar($id, ['metodo' => 'nequi', 'valor' => 50000]);
    pedido_debe_fallar(fn() => pedido_cambiar_estado($id, 'despachado', $envio), 'pago completo');
    pago_agregar($id, ['metodo' => 'nequi', 'valor' => 75000]);
    foreach (array_keys($envio) as $campo) {
        pedido_debe_fallar(fn() => pedido_cambiar_estado($id, 'despachado', [$campo => ' '] + $envio), 'transportadora y el número de guía');
    }

    inventario_entrada($producto, 'VENCIDO', date('Y-m-d', strtotime('-1 day')), 5);
    pedido_debe_fallar(fn() => pedido_cambiar_estado($id, 'despachado', $envio), 'faltan 1');
    igual(1, inventario_stock_lote($lote));
    igual('confirmado', pedido_obtener($id)['estado']);
    igual(null, pedido_obtener($id)['guia']);
    igual(['cotizado', 'confirmado'], array_column(pedido_historial($id), 'estado'));

    inventario_entrada($producto, 'LOTE-PRUEBA', null, 3);
    pedido_cambiar_estado($id, 'despachado', $envio);
    igual(2, inventario_stock_lote($lote));
    igual('G-123', pedido_obtener($id)['guia']);
    verdadero(pedido_obtener($id)['despachado']);
    pedido_debe_fallar(fn() => pedido_actualizar($id, ['envio' => 0]), 'cotizados o confirmados');
    pedido_cambiar_estado($id, 'devuelto');
    igual(4, inventario_stock_lote($lote));
    igual(125000, pedido_pagado($id));
    pedido_debe_fallar(fn() => pedido_cambiar_estado($id, 'devuelto'), 'estado actual');
    igual(4, inventario_stock_lote($lote));
});

prueba('contraentrega despacha sin pago y solo se entrega al completar el pago', function () {
    [$id, , $lote] = crear_pedido_de_prueba(['contraentrega' => 1]);
    pedido_cambiar_estado($id, 'confirmado');
    pedido_cambiar_estado($id, 'despachado', ['transportadora' => 'Reparto ficticio', 'guia' => 'G-TEST']);
    igual(2, inventario_stock_lote($lote));
    pedido_debe_fallar(fn() => pedido_cambiar_estado($id, 'entregado'), 'pago completo');
    pago_agregar($id, ['metodo' => 'efectivo', 'valor' => 125000]);
    pedido_cambiar_estado($id, 'entregado');
    verdadero(pedido_obtener($id)['entregado']);
    igual(2, inventario_stock_lote($lote));
});

prueba('token público identifica solo el pedido y devuelve únicamente datos públicos', function () {
    [$id] = crear_pedido_de_prueba(['contraentrega' => 1, 'direccion' => 'Dirección ficticia', 'notas' => 'Nota interna']);
    [$otro] = crear_pedido_de_prueba();
    $token = pedido_obtener($id)['token'];
    igual(40, strlen($token));
    verdadero($token !== pedido_obtener($otro)['token']);
    igual(null, pedido_por_token(''));
    igual(null, pedido_por_token(str_repeat('0', 40)));

    pedido_cambiar_estado($id, 'confirmado', ['nota' => 'Nota privada del historial']);
    pedido_cambiar_estado($id, 'despachado', ['transportadora' => 'Reparto ficticio', 'guia' => 'GUIA-PUBLICA']);
    $publico = pedido_por_token($token);
    igual(['total', 'estado', 'transportadora', 'guia', 'primer_nombre', 'estado_pago', 'items', 'historial'], array_keys($publico));
    igual('Nombre', $publico['primer_nombre']);
    igual(125000, (int) $publico['total']);
    igual('despachado', $publico['estado']);
    igual('pendiente', $publico['estado_pago']);
    igual('GUIA-PUBLICA', $publico['guia']);
    igual(['producto_nombre', 'cantidad'], array_keys($publico['items'][0]));
    igual(['cotizado', 'confirmado', 'despachado'], array_column($publico['historial'], 'estado'));
    igual(['estado', 'fecha'], array_keys($publico['historial'][1]));
});
