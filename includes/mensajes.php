<?php
/**
 * Plantillas, mensajes a un cliente y campañas (T6).
 *
 * Filtros de campana_destinatarios():
 *   activos       todos los clientes activos
 *   proceso       clientes activos con al menos un proceso activo
 *   atrasados     proceso activo y próximo contacto anterior a hoy
 *   producto      compraron ese producto (pedido_items); opciones['producto_id']
 *                 Cuenta confirmado, despachado, entregado y devuelto.
 *   sin_pedidos   sin ningún pedido desde hace N días; opciones['dias']
 *
 * Los marcadores los rellena mensaje_rellenar(): {nombre} {producto} {guia}
 * {transportadora} {total} {enlace} {fecha}. Lo que no se pueda saber se quita.
 */

require_once __DIR__ . '/clientes.php';
require_once __DIR__ . '/inventario.php';

/** Claves de filtro y el texto que ve la persona. */
function filtros_campana(): array
{
    return [
        'activos' => 'Todos los clientes activos',
        'proceso' => 'Con un proceso activo',
        'atrasados' => 'Con el contacto atrasado',
        'producto' => 'Que compraron un producto',
        'sin_pedidos' => 'Sin pedidos en los últimos días',
    ];
}

/** Celular de Colombia que WhatsApp puede abrir: 10 dígitos que empiezan por 3, con o sin 57. */
function telefono_sirve_whatsapp(string $telefono): bool
{
    return preg_match('/^\+?[0-9\s().-]+$/', trim($telefono))
        && preg_match('/^573\d{9}$/', telefono_whatsapp($telefono));
}

/* ---------- Plantillas ---------- */

function plantilla_obtener(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = db()->prepare('SELECT id, nombre, texto, activa, creado FROM plantillas_mensaje WHERE id = ?');
    $st->execute([$id]);
    $fila = $st->fetch();
    return $fila ?: null;
}

/** Todas las plantillas, activas primero y luego por nombre. */
function plantillas_listar(): array
{
    return db()->query('SELECT id, nombre, texto, activa, creado FROM plantillas_mensaje
                        ORDER BY activa DESC, nombre, id')->fetchAll();
}

function plantilla_crear(string $nombre, string $texto): int
{
    [$nombre, $texto] = plantilla_validar($nombre, $texto, 0);
    db()->prepare('INSERT INTO plantillas_mensaje (nombre, texto, activa, creado) VALUES (?, ?, 1, ?)')
        ->execute([$nombre, $texto, ahora()]);
    return (int) db()->lastInsertId();
}

function plantilla_actualizar(int $id, string $nombre, string $texto): void
{
    if (!plantilla_obtener($id)) {
        throw new InvalidArgumentException('No encontré esa plantilla.');
    }
    [$nombre, $texto] = plantilla_validar($nombre, $texto, $id);
    db()->prepare('UPDATE plantillas_mensaje SET nombre = ?, texto = ? WHERE id = ?')
        ->execute([$nombre, $texto, $id]);
}

function plantilla_cambiar_activa(int $id, bool $activa): void
{
    if (!plantilla_obtener($id)) {
        throw new InvalidArgumentException('No encontré esa plantilla.');
    }
    db()->prepare('UPDATE plantillas_mensaje SET activa = ? WHERE id = ?')
        ->execute([$activa ? 1 : 0, $id]);
}

/** @return array{0: string, 1: string} */
function plantilla_validar(string $nombre, string $texto, int $excepto_id): array
{
    $nombre = trim($nombre);
    $texto = trim($texto);
    if ($nombre === '') {
        throw new InvalidArgumentException('Escribe el nombre de la plantilla.');
    }
    if (mb_strlen($nombre) > 80) {
        throw new InvalidArgumentException('El nombre de la plantilla no puede tener más de 80 caracteres.');
    }
    if ($texto === '') {
        throw new InvalidArgumentException('Escribe el texto del mensaje.');
    }
    if (mb_strlen($texto) > 4000) {
        throw new InvalidArgumentException('El texto del mensaje no puede tener más de 4.000 caracteres.');
    }
    $st = db()->prepare('SELECT id FROM plantillas_mensaje WHERE nombre = ? AND id <> ?');
    $st->execute([$nombre, $excepto_id]);
    if ($st->fetchColumn()) {
        throw new InvalidArgumentException('Ya existe una plantilla con ese nombre.');
    }
    return [$nombre, $texto];
}

/* ---------- Rellenar un mensaje para un cliente ---------- */

/**
 * Rellena {nombre} con el primer nombre y los demás marcadores que se puedan
 * saber de ese cliente (pedido más reciente no cancelado, o si no, su plan).
 */
function mensaje_para_cliente(int $cliente_id, string $texto): string
{
    $cliente = cliente_obtener($cliente_id);
    if (!$cliente) {
        throw new InvalidArgumentException('No encontré ese cliente.');
    }

    $valores = [
        'nombre' => primer_nombre((string) $cliente['nombre']),
        'fecha' => fecha_corta(hoy()),
    ];

    if (!preg_match('/\{(?:producto|guia|transportadora|total|enlace)\}/', $texto)) {
        return mensaje_rellenar($texto, $valores);
    }

    $st = db()->prepare("SELECT id, transportadora, guia, total, token
                         FROM pedidos
                         WHERE cliente_id = ? AND estado <> 'cancelado'
                         ORDER BY actualizado DESC, id DESC
                         LIMIT 1");
    $st->execute([$cliente_id]);
    $pedido = $st->fetch();
    if ($pedido) {
        $valores['transportadora'] = trim((string) ($pedido['transportadora'] ?? ''));
        $valores['guia'] = trim((string) ($pedido['guia'] ?? ''));
        $valores['total'] = dinero($pedido['total']);
        $valores['enlace'] = url_base() . '/pedido_estado.php?t=' . rawurlencode((string) $pedido['token']);
        $items = db()->prepare('SELECT producto_nombre FROM pedido_items WHERE pedido_id = ? ORDER BY id');
        $items->execute([(int) $pedido['id']]);
        $nombres = [];
        foreach ($items->fetchAll(PDO::FETCH_COLUMN) as $nombre_producto) {
            $nombre_producto = trim((string) $nombre_producto);
            if ($nombre_producto !== '') {
                $nombres[] = $nombre_producto;
            }
        }
        $valores['producto'] = implode(', ', $nombres);
    }

    $falta_producto = str_contains($texto, '{producto}') && ($valores['producto'] ?? '') === '';
    $falta_enlace = str_contains($texto, '{enlace}') && ($valores['enlace'] ?? '') === '';
    if ($falta_producto || $falta_enlace) {
        $st = db()->prepare("SELECT p.id, p.token
                             FROM planes p
                             JOIN casos c ON c.id = p.caso_id
                             WHERE c.cliente_id = ? AND c.estado = 'activo'
                             ORDER BY p.id DESC
                             LIMIT 1");
        $st->execute([$cliente_id]);
        $plan = $st->fetch();
        if ($plan && $falta_enlace) {
            $valores['enlace'] = url_base() . '/plan.php?t=' . rawurlencode((string) $plan['token']);
        }
        if ($plan && $falta_producto) {
            $items = db()->prepare('SELECT producto_nombre FROM plan_items WHERE plan_id = ? ORDER BY orden, id');
            $items->execute([(int) $plan['id']]);
            $nombres = [];
            foreach ($items->fetchAll(PDO::FETCH_COLUMN) as $nombre_producto) {
                $nombre_producto = trim((string) $nombre_producto);
                if ($nombre_producto !== '') {
                    $nombres[] = $nombre_producto;
                }
            }
            $valores['producto'] = implode(', ', $nombres);
        }
    }

    $valores = array_filter($valores, static fn($valor) => $valor !== null && $valor !== '');
    return mensaje_rellenar($texto, $valores);
}

/* ---------- Destinatarios ---------- */

/**
 * Clientes activos que cumplen el filtro, ordenados por nombre.
 * Cada fila trae id, nombre, telefono y ciudad.
 */
function campana_destinatarios(string $filtro, array $opciones = []): array
{
    if (!isset(filtros_campana()[$filtro])) {
        throw new InvalidArgumentException('Ese filtro no existe.');
    }

    $where = '1 = 1';
    $params = [];
    if ($filtro === 'proceso') {
        $where = "EXISTS (SELECT 1 FROM casos k WHERE k.cliente_id = c.id AND k.estado = 'activo')";
    } elseif ($filtro === 'atrasados') {
        $where = "EXISTS (SELECT 1 FROM casos k
                          WHERE k.cliente_id = c.id AND k.estado = 'activo'
                            AND k.proximo_contacto IS NOT NULL AND k.proximo_contacto < ?)";
        $params[] = hoy();
    } elseif ($filtro === 'producto') {
        $where = "EXISTS (SELECT 1 FROM pedidos p
                          JOIN pedido_items i ON i.pedido_id = p.id
                          WHERE p.cliente_id = c.id AND i.producto_id = ?
                            AND p.estado IN ('confirmado', 'despachado', 'entregado', 'devuelto'))";
        $params[] = mensajes_producto_id($opciones);
    } elseif ($filtro === 'sin_pedidos') {
        $dias = mensajes_dias($opciones);
        $where = 'NOT EXISTS (SELECT 1 FROM pedidos p WHERE p.cliente_id = c.id AND p.creado >= ?)';
        $params[] = date('Y-m-d H:i:s', strtotime(hoy() . ' 00:00:00 -' . $dias . ' days'));
    }

    $st = db()->prepare("SELECT c.id, c.nombre, c.telefono, c.ciudad
                         FROM clientes c
                         WHERE c.activo = 1 AND ($where)");
    $st->execute($params);
    $filas = $st->fetchAll();
    foreach ($filas as &$fila) {
        $fila['id'] = (int) $fila['id'];
    }
    unset($fila);
    usort($filas, static function (array $a, array $b): int {
        $cmp = strnatcasecmp((string) $a['nombre'], (string) $b['nombre']);
        return $cmp !== 0 ? $cmp : ($a['id'] <=> $b['id']);
    });
    return $filas;
}

/** Texto corto para guardar en campanas.filtro y mostrarlo en la lista. */
function campana_texto_filtro(string $filtro, array $opciones = []): string
{
    if (!isset(filtros_campana()[$filtro])) {
        throw new InvalidArgumentException('Ese filtro no existe.');
    }
    if ($filtro === 'producto') {
        $id = mensajes_producto_id($opciones);
        $st = db()->prepare('SELECT nombre, presentacion FROM productos WHERE id = ?');
        $st->execute([$id]);
        $producto = $st->fetch();
        $nombre = trim((string) $producto['nombre'] . ' ' . (string) $producto['presentacion']);
        return 'Compraron ' . $nombre;
    }
    if ($filtro === 'sin_pedidos') {
        return 'Sin pedidos en ' . mensajes_dias($opciones) . ' días';
    }
    return filtros_campana()[$filtro];
}

function mensajes_producto_id(array $opciones): int
{
    $id = (int) ($opciones['producto_id'] ?? 0);
    if ($id <= 0) {
        throw new InvalidArgumentException('Elige el producto.');
    }
    $st = db()->prepare('SELECT id FROM productos WHERE id = ?');
    $st->execute([$id]);
    if (!$st->fetchColumn()) {
        throw new InvalidArgumentException('No encontré ese producto.');
    }
    return $id;
}

function mensajes_dias(array $opciones): int
{
    if (!array_key_exists('dias', $opciones)) {
        throw new InvalidArgumentException('Escribe cuántos días, un número entero mayor que cero.');
    }
    $dias = $opciones['dias'];
    if (is_string($dias)) {
        $numero = numero_entrada($dias);
        if ($numero === null || floor($numero) != $numero) {
            throw new InvalidArgumentException('Escribe cuántos días, un número entero mayor que cero.');
        }
        $dias = (int) $numero;
    } elseif (is_float($dias) && floor($dias) == $dias) {
        $dias = (int) $dias;
    }
    if (!is_int($dias) || $dias < 1) {
        throw new InvalidArgumentException('Escribe cuántos días, un número entero mayor que cero.');
    }
    if ($dias > 3650) {
        throw new InvalidArgumentException('El número de días es demasiado grande.');
    }
    return $dias;
}

/* ---------- Campañas ---------- */

/**
 * Crea la campaña y un mensaje pendiente por cliente (canal whatsapp_manual).
 * El texto de cada mensaje ya queda personalizado.
 */
function campana_crear(string $nombre, string $texto, array $cliente_ids, string $filtro): int
{
    $nombre = trim($nombre);
    $texto = trim($texto);
    $filtro = trim($filtro);
    if ($nombre === '') {
        throw new InvalidArgumentException('Escribe el nombre de la campaña.');
    }
    if (mb_strlen($nombre) > 120) {
        throw new InvalidArgumentException('El nombre de la campaña no puede tener más de 120 caracteres.');
    }
    if ($texto === '') {
        throw new InvalidArgumentException('Escribe el texto del mensaje.');
    }
    if (mb_strlen($texto) > 4000) {
        throw new InvalidArgumentException('El texto del mensaje no puede tener más de 4.000 caracteres.');
    }
    if ($filtro === '' || mb_strlen($filtro) > 255) {
        throw new InvalidArgumentException('El filtro de la campaña no es válido.');
    }

    $ids = [];
    foreach ($cliente_ids as $cliente_id) {
        $cliente_id = (int) $cliente_id;
        if ($cliente_id > 0) {
            $ids[$cliente_id] = $cliente_id;
        }
    }
    if (!$ids) {
        throw new InvalidArgumentException('Elige al menos un cliente.');
    }

    $usuario_id = usuario_actual()['id'] ?? null;
    $momento = ahora();
    return en_transaccion(function (PDO $pdo) use ($nombre, $texto, $filtro, $usuario_id, $momento, $ids): int {
        $pdo->prepare('INSERT INTO campanas (nombre, texto, filtro, creado_por, creado) VALUES (?, ?, ?, ?, ?)')
            ->execute([$nombre, $texto, $filtro, $usuario_id, $momento]);
        $campana_id = (int) $pdo->lastInsertId();
        $insertar = $pdo->prepare('INSERT INTO mensajes (cliente_id, campana_id, texto, canal, estado, usuario_id, creado)
                                   VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($ids as $cliente_id) {
            $personalizado = mensaje_para_cliente($cliente_id, $texto);
            if ($personalizado === '') {
                throw new InvalidArgumentException('El mensaje quedó vacío para uno de los destinatarios. Agrega texto junto a los marcadores.');
            }
            $insertar->execute([
                $cliente_id,
                $campana_id,
                $personalizado,
                'whatsapp_manual',
                'pendiente',
                $usuario_id,
                $momento,
            ]);
        }
        return $campana_id;
    });
}

function campana_obtener(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = db()->prepare('SELECT id, nombre, texto, filtro, creado_por, creado FROM campanas WHERE id = ?');
    $st->execute([$id]);
    $fila = $st->fetch();
    return $fila ?: null;
}

/** Campañas de la más nueva a la más vieja, con cuántos mensajes van en cada estado. */
function campanas_listar(): array
{
    return db()->query("SELECT c.id, c.nombre, c.filtro, c.creado,
                               COUNT(CASE WHEN m.estado = 'enviado' THEN 1 END) AS enviados,
                               COUNT(CASE WHEN m.estado = 'pendiente' THEN 1 END) AS pendientes,
                               COUNT(CASE WHEN m.estado = 'omitido' THEN 1 END) AS omitidos
                        FROM campanas c LEFT JOIN mensajes m ON m.campana_id = c.id
                        GROUP BY c.id, c.nombre, c.filtro, c.creado ORDER BY c.id DESC")->fetchAll();
}

/** @return array{enviados: int, pendientes: int, omitidos: int, total: int} */
function campana_avance(int $campana_id): array
{
    $st = db()->prepare('SELECT estado, COUNT(*) AS n FROM mensajes WHERE campana_id = ? GROUP BY estado');
    $st->execute([$campana_id]);
    $conteo = ['enviados' => 0, 'pendientes' => 0, 'omitidos' => 0];
    foreach ($st->fetchAll() as $fila) {
        if ($fila['estado'] === 'enviado') {
            $conteo['enviados'] = (int) $fila['n'];
        } elseif ($fila['estado'] === 'pendiente') {
            $conteo['pendientes'] = (int) $fila['n'];
        } elseif ($fila['estado'] === 'omitido') {
            $conteo['omitidos'] = (int) $fila['n'];
        }
    }
    $conteo['total'] = $conteo['enviados'] + $conteo['pendientes'] + $conteo['omitidos'];
    return $conteo;
}

/**
 * Pasa a omitido los mensajes pendientes cuyo cliente no tiene celular válido.
 * Devuelve cuántos omitió ahora.
 */
function campana_omitir_sin_telefono(int $campana_id): int
{
    $st = db()->prepare("SELECT m.id, c.telefono
                         FROM mensajes m
                         JOIN clientes c ON c.id = m.cliente_id
                         WHERE m.campana_id = ? AND m.estado = 'pendiente'");
    $st->execute([$campana_id]);
    $n = 0;
    foreach ($st->fetchAll() as $fila) {
        if (telefono_sirve_whatsapp((string) $fila['telefono'])) {
            continue;
        }
        if (mensaje_marcar((int) $fila['id'], 'omitido', 'Teléfono inválido para WhatsApp.', true)) {
            $n++;
        }
    }
    return $n;
}

function mensaje_obtener(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $st = db()->prepare('SELECT id, cliente_id, campana_id, texto, canal, estado, usuario_id, creado, enviado
                         FROM mensajes WHERE id = ?');
    $st->execute([$id]);
    $fila = $st->fetch();
    return $fila ?: null;
}

/** Siguiente mensaje pendiente de la campaña, con el nombre y el teléfono del cliente. */
function mensaje_siguiente(int $campana_id): ?array
{
    $st = db()->prepare("SELECT m.id, m.cliente_id, m.campana_id, m.texto, m.canal, m.estado, m.creado,
                                c.nombre, c.telefono
                         FROM mensajes m
                         JOIN clientes c ON c.id = m.cliente_id
                         WHERE m.campana_id = ? AND m.estado = 'pendiente'
                         ORDER BY m.id
                         LIMIT 1");
    $st->execute([$campana_id]);
    $fila = $st->fetch();
    return $fila ?: null;
}

/** Marca un mensaje pendiente como enviado u omitido y lo anota en el historial del cliente. */
function mensaje_marcar(int $id, string $estado, string $motivo = '', bool $automatico = false): bool
{
    if (!in_array($estado, ['enviado', 'omitido'], true)) {
        throw new InvalidArgumentException('El estado tiene que ser enviado u omitido.');
    }

    return en_transaccion(function (PDO $pdo) use ($id, $estado, $motivo, $automatico): bool {
        $st = $pdo->prepare("UPDATE mensajes SET estado = ?, enviado = ? WHERE id = ? AND estado = 'pendiente'");
        $st->execute([$estado, $estado === 'enviado' ? ahora() : null, $id]);
        if (!$st->rowCount()) {
            if ($automatico || (mensaje_obtener($id)['estado'] ?? null) === $estado) {
                return false;
            }
            throw new InvalidArgumentException('Ese mensaje ya no está pendiente.');
        }

        $mensaje = mensaje_obtener($id);
        $campana = campana_obtener((int) $mensaje['campana_id']);
        $nota = $estado === 'enviado' ? 'Mensaje marcado como enviado' : 'Mensaje omitido';
        $nota .= $campana ? ' en la campaña «' . $campana['nombre'] . '».' : '.';
        registrar_evento((int) $mensaje['cliente_id'], 'mensaje', trim($nota . ' ' . $motivo));
        return true;
    });
}

/** Guarda un mensaje individual y lo marca dentro de la misma transacción. */
function mensaje_individual_enviar(int $cliente_id, string $texto): string
{
    $texto = trim($texto);
    if ($texto === '' || mb_strlen($texto) > 4000) {
        throw new InvalidArgumentException('Escribe un mensaje de hasta 4.000 caracteres.');
    }
    return en_transaccion(function (PDO $pdo) use ($cliente_id, $texto): string {
        $cliente = cliente_obtener($cliente_id);
        if (!$cliente || !telefono_sirve_whatsapp($cliente['telefono'])) {
            throw new InvalidArgumentException('El cliente necesita un celular válido para WhatsApp.');
        }
        $texto = mensaje_para_cliente($cliente_id, $texto);
        if ($texto === '') {
            throw new InvalidArgumentException('El mensaje personalizado está vacío.');
        }
        $pdo->prepare("INSERT INTO mensajes (cliente_id, texto, canal, estado, usuario_id, creado)
                       VALUES (?, ?, 'whatsapp_manual', 'pendiente', ?, ?)")
            ->execute([$cliente_id, $texto, usuario_actual()['id'] ?? null, ahora()]);
        mensaje_marcar((int) $pdo->lastInsertId(), 'enviado');
        return enlace_whatsapp($cliente['telefono'], $texto);
    });
}
