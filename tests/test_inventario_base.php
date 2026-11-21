<?php
require_once RAIZ . '/includes/inventario.php';

/** Crea un producto de prueba y devuelve su id. */
function producto_prueba(int $stock_minimo = 2): int
{
    db()->prepare('INSERT INTO productos (nombre, presentacion, categoria, precio, activo, stock_minimo) VALUES (?, ?, ?, ?, 1, ?)')
        ->execute(['Producto ' . token_aleatorio(3), '10 mg', 'Prueba', 100000, $stock_minimo]);
    return (int) db()->lastInsertId();
}

/** Crea un cliente y un pedido con un ítem; devuelve el id del pedido. */
function pedido_prueba(int $producto_id, int $cantidad): int
{
    db()->prepare('INSERT INTO clientes (nombre, telefono, creado, actualizado) VALUES (?, ?, ?, ?)')
        ->execute(['Cliente Inventario', '300' . random_int(1000000, 9999999), ahora(), ahora()]);
    $cliente = (int) db()->lastInsertId();
    db()->prepare('INSERT INTO pedidos (cliente_id, token, creado, actualizado) VALUES (?, ?, ?, ?)')
        ->execute([$cliente, token_aleatorio(), ahora(), ahora()]);
    $pedido = (int) db()->lastInsertId();
    db()->prepare('INSERT INTO pedido_items (pedido_id, producto_id, producto_nombre, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$pedido, $producto_id, 'Producto', $cantidad, 1000, 1000 * $cantidad]);
    return $pedido;
}

prueba('la entrada crea el lote y suma; repetir el código suma al mismo lote', function () {
    usuario_prueba();
    $p = producto_prueba();
    $l1 = inventario_entrada($p, 'L-001', '2027-06-30', 5);
    $l2 = inventario_entrada($p, 'L-001', '2027-06-30', 3);
    igual($l1, $l2);
    igual(8, inventario_stock_producto($p));
});

prueba('la entrada exige código y cantidad, y no acepta otro vencimiento para el mismo lote', function () {
    $p = producto_prueba();
    foreach ([['', '2027-01-01', 1], ['L', '2027-01-01', 0], ['L', '31/12/2027', 1]] as [$c, $v, $n]) {
        try {
            inventario_entrada($p, $c, $v, $n);
            throw new RuntimeException('debía fallar');
        } catch (InvalidArgumentException $ex) {
        }
    }
    inventario_entrada($p, 'L-9', '2027-01-01', 1);
    try {
        inventario_entrada($p, 'L-9', '2027-02-01', 1);
        throw new RuntimeException('debía fallar');
    } catch (InvalidArgumentException $ex) {
        contiene('otra fecha', $ex->getMessage());
    }
});

prueba('despachar descuenta primero del lote que vence antes y nunca de uno vencido', function () {
    $p = producto_prueba();
    $vencido = inventario_entrada($p, 'VIEJO', date('Y-m-d', strtotime('-1 day')), 10);
    $tarde = inventario_entrada($p, 'TARDE', '2028-01-01', 5);
    $pronto = inventario_entrada($p, 'PRONTO', '2027-01-01', 2);
    $pedido = pedido_prueba($p, 4);
    inventario_descontar_pedido($pedido);
    igual(0, inventario_stock_lote($pronto));
    igual(3, inventario_stock_lote($tarde));
    igual(10, inventario_stock_lote($vencido));
    inventario_descontar_pedido($pedido);            // repetir no descuenta dos veces
    igual(3, inventario_stock_lote($tarde));
});

prueba('si falta stock no descuenta nada y dice qué falta', function () {
    $p = producto_prueba();
    $l = inventario_entrada($p, 'POCO', '2027-01-01', 2);
    $pedido = pedido_prueba($p, 3);
    try {
        inventario_descontar_pedido($pedido);
        throw new RuntimeException('debía fallar');
    } catch (InvalidArgumentException $ex) {
        contiene('faltan 1', $ex->getMessage());
    }
    igual(2, inventario_stock_lote($l));
});

prueba('reintegrar devuelve a los mismos lotes y solo una vez', function () {
    $p = producto_prueba();
    $a = inventario_entrada($p, 'A', '2027-01-01', 1);
    $b = inventario_entrada($p, 'B', '2027-05-01', 5);
    $pedido = pedido_prueba($p, 3);
    inventario_descontar_pedido($pedido);
    inventario_reintegrar_pedido($pedido);
    inventario_reintegrar_pedido($pedido);
    igual(1, inventario_stock_lote($a));
    igual(5, inventario_stock_lote($b));
});

prueba('alertas de stock bajo, por vencer y vencidos', function () {
    $p = producto_prueba(3);
    inventario_entrada($p, 'X', date('Y-m-d', strtotime('+10 days')), 2);
    inventario_entrada($p, 'Y', date('Y-m-d', strtotime('-2 days')), 4);
    $a = inventario_alertas();
    verdadero(in_array($p, array_map('intval', array_column($a['stock_bajo'], 'id')), true), 'stock bajo');
    verdadero(in_array('X', array_column($a['por_vencer'], 'codigo'), true), 'por vencer');
    verdadero(in_array('Y', array_column($a['vencidos'], 'codigo'), true), 'vencido');
});
