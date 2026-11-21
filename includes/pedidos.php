<?php
declare(strict_types=1);

require_once __DIR__ . '/inventario.php';

const PEDIDO_ESTADOS = [
    'cotizado' => 'Cotizado', 'confirmado' => 'Confirmado', 'despachado' => 'Despachado',
    'entregado' => 'Entregado', 'cancelado' => 'Cancelado', 'devuelto' => 'Devuelto',
];
const PEDIDO_TRANSICIONES = [
    'cotizado' => ['confirmado', 'cancelado'],
    'confirmado' => ['despachado', 'cancelado'],
    'despachado' => ['entregado', 'devuelto'],
];
const PEDIDO_ESTADOS_PAGO = ['pendiente' => 'Pendiente', 'parcial' => 'Parcial', 'pagado' => 'Pagado'];
const PEDIDO_METODOS_PAGO = ['nequi' => 'Nequi', 'efectivo' => 'Efectivo', 'bre_b' => 'Llave Bre-B'];

function pedido_consulta(string $sql, array $params): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function pedido_obtener(int $id): ?array
{
    return pedido_consulta(
        'SELECT p.*, c.nombre AS cliente, c.telefono FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.id = ?',
        [$id]
    )->fetch() ?: null;
}

/** Serializa los cambios del mismo pedido en MySQL antes de leer sus pagos o ítems. */
function pedido_para_cambio(int $id): array
{
    $sqlite = es_sqlite();
    if ($sqlite) {
        pedido_consulta('UPDATE pedidos SET actualizado = actualizado WHERE id = ?', [$id]);
    }
    $bloqueo = $sqlite ? '' : ' FOR UPDATE';
    $pedido = pedido_consulta('SELECT * FROM pedidos WHERE id = ?' . $bloqueo, [$id])->fetch();
    if (!$pedido) {
        throw new InvalidArgumentException('El pedido no existe.');
    }
    return $pedido;
}

function pedido_items(int $id): array
{
    return pedido_consulta('SELECT * FROM pedido_items WHERE pedido_id = ? ORDER BY id', [$id])->fetchAll();
}

function pedido_pagos(int $id): array
{
    return pedido_consulta('SELECT * FROM pagos WHERE pedido_id = ? ORDER BY fecha DESC, id DESC', [$id])->fetchAll();
}

function pedido_historial(int $id): array
{
    return pedido_consulta('SELECT * FROM pedido_historial WHERE pedido_id = ? ORDER BY id', [$id])->fetchAll();
}

function pedido_entero(mixed $valor, string $campo, int $minimo = 0): int
{
    // numero_entrada reconoce miles con punto cuando el número no lleva signo.
    $texto = is_scalar($valor) ? trim(str_replace([' ', '$'], '', (string) $valor)) : '';
    if (preg_match('/^-\d{1,3}(\.\d{3})+$/D', $texto)) {
        $texto = str_replace('.', '', $texto);
    }
    $numero = numero_entrada($texto);
    if ($numero === null || $numero < $minimo || $numero > 2147483647 || floor($numero) !== $numero) {
        throw new InvalidArgumentException("Revisa el campo «{$campo}». Escribe un número entero válido.");
    }
    return (int) $numero;
}

function pedido_texto(array $datos, string $campo, int $maximo): ?string
{
    $valor = $datos[$campo] ?? '';
    $texto = is_string($valor) ? trim($valor) : '';
    if (mb_strlen($texto) > $maximo) {
        throw new InvalidArgumentException("El campo «{$campo}» admite hasta $maximo caracteres.");
    }
    return $texto === '' ? null : $texto;
}

function pedido_preparar_items(array $items): array
{
    if (!$items) {
        throw new InvalidArgumentException('Agrega al menos un producto al pedido.');
    }
    $st = db()->prepare('SELECT id, nombre, presentacion, precio FROM productos WHERE id = ?');
    $filas = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Revisa los productos del pedido.');
        }
        $st->execute([pedido_entero($item['producto_id'] ?? 0, 'producto del catálogo')]);
        $producto = $st->fetch();
        if (!$producto) {
            throw new InvalidArgumentException('Selecciona un producto del catálogo para cada ítem.');
        }
        $cantidad = pedido_entero($item['cantidad'] ?? '', 'cantidad', 1);
        $precio = $item['precio_unitario'] ?? '';
        $precio = pedido_entero($precio === '' ? $producto['precio'] : $precio, 'precio unitario');
        $filas[] = [
            'producto_id' => $producto['id'],
            'producto_nombre' => mb_substr(trim($producto['nombre'] . ' ' . $producto['presentacion']), 0, 120),
            'cantidad' => $cantidad,
            'precio_unitario' => $precio, 'subtotal' => pedido_entero($cantidad * $precio, 'subtotal del ítem'),
        ];
    }
    return $filas;
}

function pedido_preparar_datos(int $cliente, array $datos, array $items): array
{
    $caso = null;
    if (!empty($datos['caso_id'])) {
        $caso = pedido_entero($datos['caso_id'], 'proceso', 1);
        if (!pedido_consulta('SELECT id FROM casos WHERE id = ? AND cliente_id = ?', [$caso, $cliente])->fetchColumn()) {
            throw new InvalidArgumentException('El proceso debe pertenecer al cliente del pedido.');
        }
    }
    $subtotal = pedido_entero(array_sum(array_column($items, 'subtotal')), 'subtotal');
    $envio = pedido_entero(($datos['envio'] ?? '') === '' ? 25000 : $datos['envio'], 'envío');
    $descuento = pedido_entero(($datos['descuento'] ?? '') === '' ? 0 : $datos['descuento'], 'descuento');
    $total = pedido_entero($subtotal + $envio - $descuento, 'total');
    return [
        $caso, empty($datos['contraentrega']) ? 0 : 1, $subtotal, $envio, $descuento, $total,
        pedido_texto($datos, 'direccion', 255), pedido_texto($datos, 'ciudad', 80), pedido_texto($datos, 'notas', 16383),
    ];
}

function pedido_guardar_items(int $id, array $items): void
{
    $st = db()->prepare('INSERT INTO pedido_items (pedido_id, producto_id, producto_nombre, cantidad, precio_unitario, subtotal)
                         VALUES (?, ?, ?, ?, ?, ?)');
    foreach ($items as $item) {
        $st->execute([$id, ...array_values($item)]);
    }
}

function pedido_anotar_estado(int $id, string $estado, ?string $nota = null): void
{
    pedido_consulta('INSERT INTO pedido_historial (pedido_id, estado, nota, usuario_id, fecha) VALUES (?, ?, ?, ?, ?)',
        [$id, $estado, $nota, usuario_actual()['id'] ?? null, ahora()]);
}

function pedido_crear(array $datos, array $items): int
{
    return en_transaccion(function (PDO $pdo) use ($datos, $items): int {
        $cliente = pedido_entero($datos['cliente_id'] ?? 0, 'cliente');
        if (!pedido_consulta('SELECT id FROM clientes WHERE id = ?', [$cliente])->fetchColumn()) {
            throw new InvalidArgumentException('Selecciona un cliente válido.');
        }
        $items = pedido_preparar_items($items);
        $campos = pedido_preparar_datos($cliente, $datos, $items);
        pedido_consulta('INSERT INTO pedidos (cliente_id, caso_id, contraentrega, subtotal, envio, descuento, total,
                         direccion, ciudad, notas, token, asesor_id, creado, actualizado)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$cliente, ...$campos, token_aleatorio(20), usuario_actual()['id'] ?? null, ahora(), ahora()]);
        $id = (int) $pdo->lastInsertId();
        pedido_guardar_items($id, $items);
        pedido_anotar_estado($id, 'cotizado');
        registrar_evento($cliente, 'pedido', "Creó el pedido #$id.", $campos[0]);
        return $id;
    });
}

function pedido_actualizar(int $id, array $datos, ?array $items = null): void
{
    en_transaccion(function () use ($id, $datos, $items): void {
        $pedido = pedido_para_cambio($id);
        if (!in_array($pedido['estado'], ['cotizado', 'confirmado'], true)) {
            throw new InvalidArgumentException('Solo puedes editar pedidos cotizados o confirmados.');
        }
        $filas = $items === null ? pedido_items($id) : pedido_preparar_items($items);
        $campos = pedido_preparar_datos((int) $pedido['cliente_id'], $datos + $pedido, $filas);
        pedido_consulta('UPDATE pedidos SET caso_id = ?, contraentrega = ?, subtotal = ?, envio = ?, descuento = ?,
                         total = ?, direccion = ?, ciudad = ?, notas = ?, actualizado = ? WHERE id = ?',
            [...$campos, ahora(), $id]);
        if ($items !== null) {
            pedido_consulta('DELETE FROM pedido_items WHERE pedido_id = ?', [$id]);
            pedido_guardar_items($id, $filas);
        }
        registrar_evento((int) $pedido['cliente_id'], 'pedido', "Editó el pedido #$id.", $campos[0]);
    });
}

function pago_agregar(int $pedido_id, array $datos): int
{
    return en_transaccion(function (PDO $pdo) use ($pedido_id, $datos): int {
        $pedido = pedido_para_cambio($pedido_id);
        $metodo = $datos['metodo'] ?? '';
        if (!is_string($metodo) || !isset(PEDIDO_METODOS_PAGO[$metodo])) {
            throw new InvalidArgumentException('Selecciona un método de pago válido.');
        }
        $valor = pedido_entero($datos['valor'] ?? '', 'valor', -2147483648);
        if ($valor === 0) {
            throw new InvalidArgumentException('El valor del pago debe ser distinto de cero.');
        }
        $fecha = $datos['fecha'] ?? hoy();
        $dia = is_string($fecha) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $fecha)
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) : false;
        if (!$dia || $dia->format('Y-m-d') !== $fecha || $fecha < '1000-01-01') {
            throw new InvalidArgumentException('Escribe una fecha de pago válida.');
        }
        pedido_consulta('INSERT INTO pagos (pedido_id, fecha, metodo, valor, referencia, nota, usuario_id, creado)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$pedido_id, $fecha, $metodo, $valor, pedido_texto($datos, 'referencia', 80),
                pedido_texto($datos, 'nota', 255), usuario_actual()['id'] ?? null, ahora()]);
        $id = (int) $pdo->lastInsertId();
        $accion = $valor < 0 ? 'Registró una devolución de dinero' : 'Registró un pago';
        registrar_evento((int) $pedido['cliente_id'], 'pedido', "$accion para el pedido #$pedido_id.",
            $pedido['caso_id'] === null ? null : (int) $pedido['caso_id']);
        return $id;
    });
}

function pedido_pagado(int $id): int
{
    return (int) pedido_consulta('SELECT COALESCE(SUM(valor), 0) FROM pagos WHERE pedido_id = ?', [$id])->fetchColumn();
}


function pedido_estado_pago(int $id): string
{
    $total = pedido_consulta('SELECT total FROM pedidos WHERE id = ?', [$id])->fetchColumn();
    if ($total === false) {
        throw new InvalidArgumentException('El pedido no existe.');
    }
    $pagado = pedido_pagado($id);
    return $pagado >= (int) $total ? 'pagado' : ($pagado > 0 ? 'parcial' : 'pendiente');
}

function pedido_cambiar_estado(int $id, string $nuevo, array $datos = []): void
{
    en_transaccion(function (PDO $pdo) use ($id, $nuevo, $datos): void {
        $pedido = pedido_para_cambio($id);
        if (!in_array($nuevo, PEDIDO_TRANSICIONES[$pedido['estado']] ?? [], true)) {
            throw new InvalidArgumentException('No puedes hacer ese cambio desde el estado actual del pedido.');
        }
        $nota = pedido_texto($datos, 'nota', 255);
        if (in_array($nuevo, ['despachado', 'devuelto'], true) && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            // Serializa movimientos de pedidos que comparten productos antes de consultar existencias.
            pedido_consulta('SELECT id FROM productos WHERE id IN
                             (SELECT producto_id FROM pedido_items WHERE pedido_id = ?) ORDER BY id FOR UPDATE',
                [$id])->fetchAll();
        }

        $fecha = ahora();
        if ($nuevo === 'despachado') {
            if (!$pedido['contraentrega'] && pedido_pagado($id) < (int) $pedido['total']) {
                throw new InvalidArgumentException('Registra el pago completo o marca el pedido como contraentrega antes de despachar.');
            }
            $transportadora = pedido_texto($datos, 'transportadora', 80);
            $guia = pedido_texto($datos, 'guia', 80);
            if ($transportadora === null || $guia === null) {
                throw new InvalidArgumentException('Escribe la transportadora y el número de guía para despachar.');
            }
            inventario_descontar_pedido($id);
            pedido_consulta('UPDATE pedidos SET transportadora = ?, guia = ?, despachado = ? WHERE id = ?',
                [$transportadora, $guia, $fecha, $id]);
        } elseif ($nuevo === 'entregado') {
            if ($pedido['contraentrega'] && pedido_pagado($id) < (int) $pedido['total']) {
                throw new InvalidArgumentException('Registra el pago completo de la contraentrega antes de marcar el pedido como entregado.');
            }
            pedido_consulta('UPDATE pedidos SET entregado = ? WHERE id = ?', [$fecha, $id]);
        } elseif ($nuevo === 'devuelto') {
            inventario_reintegrar_pedido($id);
        }

        pedido_consulta('UPDATE pedidos SET estado = ?, actualizado = ? WHERE id = ?', [$nuevo, $fecha, $id]);
        pedido_anotar_estado($id, $nuevo, $nota);
        registrar_evento((int) $pedido['cliente_id'], 'pedido', "Pedido #$id cambió a " . PEDIDO_ESTADOS[$nuevo] . '.',
            $pedido['caso_id'] === null ? null : (int) $pedido['caso_id']);
    });
}

function pedidos_listar(array $filtros = []): array
{
    $sql = "SELECT * FROM (
                SELECT p.*, c.nombre AS cliente,
                    CASE WHEN COALESCE(a.pagado, 0) >= p.total THEN 'pagado'
                         WHEN COALESCE(a.pagado, 0) > 0 THEN 'parcial' ELSE 'pendiente' END AS estado_pago
                FROM pedidos p JOIN clientes c ON c.id = p.cliente_id
                LEFT JOIN (SELECT pedido_id, SUM(valor) AS pagado FROM pagos GROUP BY pedido_id) a ON a.pedido_id = p.id
            ) listado WHERE 1 = 1";
    $params = [];
    foreach (['estado', 'estado_pago', 'cliente_id'] as $campo) {
        if (!empty($filtros[$campo])) {
            $sql .= " AND $campo = ?";
            $params[] = $filtros[$campo];
        }
    }
    $q = trim((string) ($filtros['q'] ?? ''));
    if ($q !== '') {
        $texto = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        $sql .= " AND (LOWER(cliente) LIKE LOWER(?) ESCAPE '!' OR LOWER(guia) LIKE LOWER(?) ESCAPE '!'";
        array_push($params, $texto, $texto);
        if (preg_match('/^#?(\d{1,10})$/D', $q, $partes)) {
            $sql .= ' OR id = ?';
            $params[] = (int) $partes[1];
        }
        $sql .= ')';
    }
    return pedido_consulta($sql . ' ORDER BY creado DESC, id DESC', $params)->fetchAll();
}

function pedido_por_token(string $token): ?array
{
    if (!preg_match('/\A[a-f0-9]{32,40}\z/', $token)) {
        return null;
    }
    $pedido = pedido_consulta('SELECT p.id, p.total, p.estado, p.transportadora, p.guia, c.nombre
                              FROM pedidos p JOIN clientes c ON c.id = p.cliente_id WHERE p.token = ?', [$token])->fetch();
    if (!$pedido) {
        return null;
    }
    $id = (int) $pedido['id'];
    $pedido['primer_nombre'] = primer_nombre($pedido['nombre']);
    unset($pedido['id'], $pedido['nombre']);
    $pedido['estado_pago'] = pedido_estado_pago($id);
    $pedido['items'] = pedido_consulta('SELECT producto_nombre, cantidad FROM pedido_items WHERE pedido_id = ? ORDER BY id',
        [$id])->fetchAll();
    $pedido['historial'] = pedido_consulta('SELECT estado, fecha FROM pedido_historial WHERE pedido_id = ? ORDER BY id',
        [$id])->fetchAll();
    return $pedido;
}
