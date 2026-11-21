<?php
/** Pruebas del inventario: funciones nuevas y carga de las pantallas. */
require_once RAIZ . '/includes/inventario.php';
require_once RAIZ . '/inventario.php';   // carga las funciones; el cuerpo se salta en CLI

if (!function_exists('producto_inventario_prueba')) {
    /** Crea un producto de prueba y devuelve su id. */
    function producto_inventario_prueba(int $stock_minimo = 3): int
    {
        db()->prepare('INSERT INTO productos (nombre, presentacion, categoria, precio, activo, stock_minimo)
                       VALUES (?, ?, ?, ?, 1, ?)')
            ->execute(['Producto Inv ' . token_aleatorio(4), '10 mg', 'Prueba', 100000, $stock_minimo]);
        return (int) db()->lastInsertId();
    }
}

if (!function_exists('pedido_inventario_prueba')) {
    /** Crea un cliente y un pedido con un ítem; devuelve el id del pedido. */
    function pedido_inventario_prueba(int $producto_id, int $cantidad): int
    {
        db()->prepare('INSERT INTO clientes (nombre, telefono, creado, actualizado) VALUES (?, ?, ?, ?)')
            ->execute(['Cliente Inv', '300' . random_int(1000000, 9999999), ahora(), ahora()]);
        $cliente = (int) db()->lastInsertId();
        db()->prepare('INSERT INTO pedidos (cliente_id, token, creado, actualizado) VALUES (?, ?, ?, ?)')
            ->execute([$cliente, token_aleatorio(), ahora(), ahora()]);
        $pedido = (int) db()->lastInsertId();
        db()->prepare('INSERT INTO pedido_items (pedido_id, producto_id, producto_nombre, cantidad, precio_unitario, subtotal)
                       VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$pedido, $producto_id, 'Producto', $cantidad, 1000, 1000 * $cantidad]);
        return $pedido;
    }
}

/** Incluye una página como si se ejecutara al pedirla y devuelve el HTML. */
function cargar_pagina_inventario(string $archivo, array $get = []): string
{
    $ruta = RAIZ . '/' . $archivo;
    $_GET = $get;
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_FILENAME'] = $ruta;
    $_SESSION = [];
    $GLOBALS['USUARIO_FORZADO'] = usuario_prueba();
    ob_start();
    require $ruta;
    return (string) ob_get_clean();
}

prueba('la lista de productos calcula el stock vigente y el estado', function () {
    usuario_prueba();
    $ok = producto_inventario_prueba(3);
    inventario_entrada($ok, 'OK-1', '2027-01-01', 5);
    $bajo = producto_inventario_prueba(10);
    inventario_entrada($bajo, 'BAJO-1', '2027-01-01', 4);
    $agotado = producto_inventario_prueba(2);

    $por_id = [];
    foreach (inventario_productos() as $fila) {
        $por_id[(int) $fila['id']] = $fila;
    }
    verdadero(isset($por_id[$ok], $por_id[$bajo], $por_id[$agotado]), 'los tres están en la lista');
    igual(5, (int) $por_id[$ok]['stock']);
    igual('ok', $por_id[$ok]['estado']);
    igual(4, (int) $por_id[$bajo]['stock']);
    igual('bajo', $por_id[$bajo]['estado']);
    igual(0, (int) $por_id[$agotado]['stock']);
    igual('agotado', $por_id[$agotado]['estado']);

    $buscado = inventario_productos('producto inv');
    verdadero(count($buscado) >= 3, 'el buscador encuentra los productos de prueba');
    foreach ($buscado as $fila) {
        verdadero(str_contains(inventario_texto_comparable((string) $fila['nombre']), 'producto inv'));
    }
    igual(0, count(inventario_productos('nombre que no existe')));
});

prueba('el ajuste suma y resta con motivo obligatorio', function () {
    usuario_prueba();
    $p = producto_inventario_prueba();
    $l = inventario_entrada($p, 'AJ-1', '2027-01-01', 5);

    inventario_ajustar($l, -2, 'Dos viales dañados');
    igual(3, inventario_stock_lote($l));
    inventario_ajustar($l, 4, 'Llegaron dos más');
    igual(7, inventario_stock_lote($l));

    try {
        inventario_ajustar($l, 1, '   ');
        throw new RuntimeException('debía fallar');
    } catch (InvalidArgumentException $ex) {
        contiene('motivo', $ex->getMessage());
    }
    try {
        inventario_ajustar($l, 0, 'nada');
        throw new RuntimeException('debía fallar');
    } catch (InvalidArgumentException $ex) {
        contiene('cero', $ex->getMessage());
    }
    try {
        inventario_ajustar($l, -99, 'robo');
        throw new RuntimeException('debía fallar');
    } catch (InvalidArgumentException $ex) {
        contiene('suficientes', $ex->getMessage());
    }
    try {
        inventario_ajustar(999999, 1, 'x');
        throw new RuntimeException('debía fallar');
    } catch (InvalidArgumentException $ex) {
        contiene('no existe', $ex->getMessage());
    }

    $m = inventario_movimientos($p, 10);
    $ajustes = array_values(array_filter($m, static fn ($f) => $f['tipo'] === 'ajuste'));
    igual(2, count($ajustes), 'dos movimientos de ajuste');
    verdadero((bool) array_filter($ajustes, static fn ($f) => str_contains((string) $f['nota'], 'dañados')));
});

prueba('cambiar el stock mínimo y el precio del producto', function () {
    usuario_prueba();
    $p = producto_inventario_prueba(3);

    inventario_actualizar_producto($p, ['stock_minimo' => '7', 'precio' => '125.000']);
    $f = inventario_producto_obtener($p);
    igual(7, (int) $f['stock_minimo']);
    igual(125000, (int) $f['precio']);

    foreach ([
        ['stock_minimo' => '-1', 'precio' => '100'],
        ['stock_minimo' => '2,5', 'precio' => '100'],
        ['stock_minimo' => '', 'precio' => '100'],
        ['stock_minimo' => '2', 'precio' => '-5'],
        ['stock_minimo' => '2', 'precio' => ''],
    ] as $datos) {
        try {
            inventario_actualizar_producto($p, $datos);
            throw new RuntimeException('debía fallar');
        } catch (InvalidArgumentException $ex) {
        }
    }
    try {
        inventario_actualizar_producto(999999, ['stock_minimo' => '2', 'precio' => '100']);
        throw new RuntimeException('debía fallar');
    } catch (InvalidArgumentException $ex) {
        contiene('no existe', $ex->getMessage());
    }

    $f = inventario_producto_obtener($p);
    igual(7, (int) $f['stock_minimo'], 'no cambió con los datos inválidos');
    igual(125000, (int) $f['precio'], 'no cambió con los datos inválidos');
});

prueba('los movimientos recientes traen tipo, cantidad, pedido y usuario', function () {
    $u = usuario_prueba();
    $p = producto_inventario_prueba();
    inventario_entrada($p, 'MOV-1', '2027-01-01', 2);
    $pedido = pedido_inventario_prueba($p, 1);
    inventario_descontar_pedido($pedido);

    $m = inventario_movimientos($p, 10);
    verdadero(count($m) >= 2, 'entrada y salida');
    igual('salida', $m[0]['tipo']);
    igual($pedido, (int) $m[0]['pedido_id']);
    igual($u['nombre'], $m[0]['usuario_nombre']);
    igual('MOV-1', $m[0]['lote_codigo']);
    igual(-1, (int) $m[0]['cantidad']);
});

prueba('la pantalla de inventario carga sin errores', function () {
    usuario_prueba();
    $html = cargar_pagina_inventario('inventario.php');
    contiene('Inventario', $html);
    contiene('Alertas', $html);
    contiene('Entrada de mercancía', $html);
    contiene('Código del lote', $html);
});

prueba('la ficha de producto carga sin errores con lotes, ajuste y movimientos', function () {
    usuario_prueba();
    $p = producto_inventario_prueba();
    inventario_entrada($p, 'LOT-1', '2027-05-01', 4);

    $html = cargar_pagina_inventario('producto.php', ['id' => $p]);
    contiene('LOT-1', $html);
    contiene('Ajuste de inventario', $html);
    contiene('Precio y stock mínimo', $html);
    contiene('Movimientos recientes', $html);
    contiene('Entrada', $html);
});

prueba('la ficha de un producto que no existe dice que no se encontró', function () {
    usuario_prueba();
    $html = cargar_pagina_inventario('producto.php', ['id' => 999999]);
    contiene('No encontramos ese producto', $html);
});
