<?php
/** Funciones de Casos, Planes y Seguimientos (T2).
 *  Cada acción llama a registrar_evento() con cliente_id y caso_id.
 *  Lanza InvalidArgumentException con mensaje claro en error.
 */

/** Mantiene cada cambio y su evento en la misma transacción. */
function guardar_cambio_caso(callable $guardar): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $resultado = $guardar($pdo);
        $pdo->commit();
        return $resultado;
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
}

/* ---------- Casos ---------- */

function caso_crear(array $datos): int
{
    $cliente_id = (int) ($datos['cliente_id'] ?? 0);
    $titulo = trim($datos['titulo'] ?? '');
    if ($titulo === '') {
        throw new InvalidArgumentException('Escribe el título del caso.');
    }
    $st = db()->prepare('SELECT id FROM clientes WHERE id = ?');
    $st->execute([$cliente_id]);
    if (!$st->fetchColumn()) {
        throw new InvalidArgumentException('El cliente no existe.');
    }
    $objetivo = trim($datos['objetivo'] ?? '');
    $fecha_inicio = trim($datos['fecha_inicio'] ?? '') ?: hoy();
    $peso_inicial = numero_entrada($datos['peso_inicial'] ?? null);
    $peso_meta = numero_entrada($datos['peso_meta'] ?? null);
    $proximo_contacto = trim($datos['proximo_contacto'] ?? '') ?: null;

    return guardar_cambio_caso(function (PDO $pdo) use ($cliente_id, $titulo, $objetivo, $fecha_inicio, $peso_inicial, $peso_meta, $proximo_contacto): int {
        $pdo->prepare(
            'INSERT INTO casos (cliente_id, titulo, objetivo, fecha_inicio, peso_inicial, peso_meta, proximo_contacto, creado, actualizado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$cliente_id, $titulo, $objetivo, $fecha_inicio, $peso_inicial, $peso_meta,
            $proximo_contacto, ahora(), ahora()]);

        $caso_id = (int) $pdo->lastInsertId();

        registrar_evento($cliente_id, 'crear caso', 'Caso creado: ' . $titulo, $caso_id);

        return $caso_id;
    });
}

function caso_obtener(int $id): ?array
{
    $st = db()->prepare(
        'SELECT c.id, c.cliente_id, c.titulo, c.objetivo, c.estado, c.fecha_inicio,
                c.fecha_fin, c.peso_inicial, c.peso_meta, c.proximo_contacto,
                cl.nombre AS nombre_cliente, cl.telefono
         FROM casos c
         JOIN clientes cl ON cl.id = c.cliente_id
         WHERE c.id = ?'
    );
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function caso_actualizar(int $id, array $datos): void
{
    $permitidos = ['estado', 'proximo_contacto'];
    $actual = [];
    foreach ($permitidos as $campo) {
        if (array_key_exists($campo, $datos)) {
            $actual[$campo] = trim($datos[$campo] ?? '') ?: null;
        }
    }
    if (empty($actual)) {
        return; // nada que actualizar
    }

    if (array_key_exists('estado', $actual) && !in_array($actual['estado'], ['activo', 'pausado', 'terminado'], true)) {
        throw new InvalidArgumentException('Elige un estado válido.');
    }

    $sql = 'UPDATE casos SET actualizado = ?';
    $params = [ahora()];
    foreach ($actual as $campo => $valor) {
        $sql .= ', ' . $campo . ' = ?';
        $params[] = $valor;
    }
    $sql .= ' WHERE id = ?';
    $params[] = $id;

    guardar_cambio_caso(function (PDO $pdo) use ($sql, $params, $id): void {
        $pdo->prepare($sql)->execute($params);

        // Obtener cliente_id para el evento
        $caso = caso_obtener($id);
        if ($caso) {
            registrar_evento((int) $caso['cliente_id'], 'actualizar caso', 'Datos del caso actualizados.', $id);
        }
    });
}

/* ---------- Planes ---------- */

function plan_actual(int $caso_id): ?array
{
    $st = db()->prepare(
        'SELECT p.id, p.caso_id, p.version, p.indicaciones, p.motivo_cambio, p.token,
                p.enviado, p.creado_por, p.creado,
                (SELECT COUNT(*) FROM plan_items pi WHERE pi.plan_id = p.id) AS items_count
         FROM planes p
         WHERE p.caso_id = ?
         ORDER BY p.version DESC LIMIT 1'
    );
    $st->execute([$caso_id]);
    return $st->fetch() ?: null;
}

function planes_del_caso(int $caso_id): array
{
    $st = db()->prepare(
        'SELECT p.id, p.version, p.indicaciones, p.motivo_cambio, p.token, p.enviado, p.creado,
                (SELECT COUNT(*) FROM plan_items pi WHERE pi.plan_id = p.id) AS items_count
         FROM planes p
         WHERE p.caso_id = ?
         ORDER BY p.version DESC'
    );
    $st->execute([$caso_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function plan_nueva_version(int $caso_id, array $items, string $indicaciones, string $motivo): int
{
    return guardar_cambio_caso(function (PDO $pdo) use ($caso_id, $items, $indicaciones, $motivo): int {
        // Serializa la asignación de versiones para este caso.
        $pdo->prepare('UPDATE casos SET actualizado = actualizado WHERE id = ?')->execute([$caso_id]);
        $caso = caso_obtener($caso_id);
        if (!$caso) {
            throw new InvalidArgumentException('El caso no existe.');
        }

        // Obtener la versión actual máxima
        $actual = plan_actual($caso_id);
        $nueva_version = ($actual ? (int) $actual['version'] : 0) + 1;

        // Generar token único
        $token = token_aleatorio();

        // Insertar nueva versión del plan
        $st = db()->prepare(
            'INSERT INTO planes (caso_id, version, indicaciones, motivo_cambio, token, creado_por, creado, enviado)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL)'
        );
        $st->execute([$caso_id, $nueva_version, $indicaciones, $motivo, $token, usuario_actual()['id'] ?? null, ahora()]);
        $plan_id = (int) $pdo->lastInsertId();

        $insertar = $pdo->prepare(
            'INSERT INTO plan_items (plan_id, producto_id, producto_nombre, dosis, frecuencia, duracion, notas, orden)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        // "Retatrutide 10 mg": el nombre lleva la presentación (sin el "-" de los que no tienen).
        $producto = $pdo->prepare('SELECT nombre, presentacion FROM productos WHERE id = ?');
        foreach (array_values($items) as $indice => $item) {
            $producto_nombre = trim($item['producto_nombre'] ?? '');

            $producto_id = (int) ($item['producto_id'] ?? 0) ?: null;
            if ($producto_id !== null) {
                $producto->execute([$producto_id]);
                $fila = $producto->fetch();
                if (!$fila) {
                    throw new InvalidArgumentException('El producto del catálogo no existe.');
                }
                $producto_nombre = trim($fila['nombre'] . ' ' . str_replace('-', '', (string) $fila['presentacion']));
            }
            if ($producto_nombre === '') {
                throw new InvalidArgumentException('Escribe o selecciona el producto del ítem.');
            }

            $insertar->execute([$plan_id, $producto_id, $producto_nombre,
                trim($item['dosis'] ?? ''), trim($item['frecuencia'] ?? ''),
                trim($item['duracion'] ?? ''), trim($item['notas'] ?? ''), $indice + 1]);
        }

        registrar_evento((int) $caso['cliente_id'], 'nueva versión plan', 'Nueva versión ' . $nueva_version . ' del plan.', $caso_id);
        return $plan_id;
    });
}

function plan_items_del_plan(int $plan_id): array
{
    $st = db()->prepare('SELECT producto_nombre, dosis, frecuencia, duracion, notas FROM plan_items WHERE plan_id = ? ORDER BY orden, id');
    $st->execute([$plan_id]);
    return $st->fetchAll();
}

function plan_por_token(string $token): ?array
{
    $st = db()->prepare(
        'SELECT p.id, p.token, p.version, p.indicaciones, p.creado, cl.nombre,
                (SELECT MAX(p2.version) FROM planes p2 WHERE p2.caso_id = p.caso_id) AS ultima_version
         FROM planes p JOIN casos c ON c.id = p.caso_id JOIN clientes cl ON cl.id = c.cliente_id
         WHERE p.token = ?'
    );
    $st->execute([$token]);
    $plan = $st->fetch();
    if (!$plan || !hash_equals($plan['token'], $token)) {
        return null;
    }
    $plan['primer_nombre'] = primer_nombre($plan['nombre']);
    unset($plan['nombre'], $plan['token']);
    $items = db()->prepare('SELECT producto_nombre, dosis, frecuencia, duracion FROM plan_items WHERE plan_id = ? ORDER BY orden, id');
    $items->execute([$plan['id']]);
    $plan['items'] = $items->fetchAll();
    return $plan;
}

function plan_marcar_enviado(int $plan_id): void
{
    guardar_cambio_caso(function (PDO $pdo) use ($plan_id): void {
        $st = $pdo->prepare('SELECT p.caso_id, p.version, c.cliente_id FROM planes p JOIN casos c ON c.id = p.caso_id WHERE p.id = ?');
        $st->execute([$plan_id]);
        $plan = $st->fetch();
        if (!$plan) {
            throw new InvalidArgumentException('El plan no existe.');
        }
        $pdo->prepare('UPDATE planes SET enviado = ? WHERE id = ?')->execute([ahora(), $plan_id]);
        registrar_evento((int) $plan['cliente_id'], 'enviar plan',
            'Plan versión ' . $plan['version'] . ' marcado como enviado.', (int) $plan['caso_id']);
    });
}

/* ---------- Seguimientos ---------- */

function seguimiento_agregar(array $datos): int
{
    $necesarios = ['caso_id', 'fecha', 'tipo'];
    foreach ($necesarios as $campo) {
        if (trim($datos[$campo] ?? '') === '') {
            throw new InvalidArgumentException('Falta el campo obligatorio: ' . $campo);
        }
    }

    $caso_id = (int) $datos['caso_id'];
    $fecha = trim($datos['fecha']);
    $tipo = trim($datos['tipo']);
    $peso = numero_entrada($datos['peso'] ?? null);
    $cintura = numero_entrada($datos['cintura'] ?? null);
    $adherencia = trim($datos['adherencia'] ?? '');
    $efectos = trim($datos['efectos'] ?? '');
    $nota = trim($datos['nota'] ?? '');
    $proximo_contacto = array_key_exists('proximo_contacto', $datos) && trim($datos['proximo_contacto']) !== ''
        ? trim($datos['proximo_contacto'])
        : null;

    // Validar tipo
    $tipos_validos = ['control', 'llamada', 'mensaje', 'visita'];
    if (!in_array($tipo, $tipos_validos, true)) {
        throw new InvalidArgumentException('Tipo de seguimiento inválido: ' . $tipo);
    }

    // Validar adherencia
    $adherencia_validos = ['completa', 'parcial', 'no_cumple'];
    if ($adherencia !== '' && !in_array($adherencia, $adherencia_validos, true)) {
        throw new InvalidArgumentException('Adherencia inválida: ' . $adherencia);
    }

    $caso = caso_obtener($caso_id);
    if (!$caso) {
        throw new InvalidArgumentException('El caso no existe.');
    }

    return guardar_cambio_caso(function (PDO $pdo) use ($caso_id, $fecha, $tipo, $peso, $cintura, $adherencia, $efectos, $nota, $proximo_contacto, $caso): int {
        $pdo->prepare(
            'INSERT INTO seguimientos (caso_id, fecha, tipo, peso, cintura_cm, adherencia, efectos, nota, usuario_id, creado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$caso_id, $fecha, $tipo, $peso, $cintura, $adherencia, $efectos, $nota,
            usuario_actual()['id'] ?? null, ahora()]);

        $id = (int) $pdo->lastInsertId();
        if ($proximo_contacto !== null) {
            $pdo->prepare('UPDATE casos SET proximo_contacto = ?, actualizado = ? WHERE id = ?')
                ->execute([$proximo_contacto, ahora(), $caso_id]);
        }
        registrar_evento((int) $caso['cliente_id'], 'nuevo seguimiento', 'Nuevo seguimiento: ' . $tipo, $caso_id);
        return $id;
    });
}

function seguimientos_del_caso(int $caso_id): array
{
    $st = db()->prepare(
        'SELECT s.id, s.fecha, s.tipo, s.peso, s.cintura_cm, s.adherencia, s.efectos, s.nota,
                s.creado, u.nombre AS usuario_nombre
         FROM seguimientos s
         LEFT JOIN usuarios u ON u.id = s.usuario_id
         WHERE s.caso_id = ?
         ORDER BY s.fecha DESC, s.id DESC'
    );
    $st->execute([$caso_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function progreso_peso(int $caso_id): array
{
    // Obtener peso inicial del caso
    $caso = caso_obtener($caso_id);
    $peso_inicial = null;
    if ($caso) {
        if ($caso['peso_inicial'] !== null) {
            $peso_inicial = (float) $caso['peso_inicial'];
        }
    }

    // Obtener último peso de los seguimientos
    $st = db()->prepare(
        'SELECT peso FROM seguimientos WHERE caso_id = ? AND peso IS NOT NULL ORDER BY fecha DESC, id DESC LIMIT 1'
    );
    $st->execute([$caso_id]);
    $ultimo = null;
    $fila = $st->fetch();
    if ($fila && $fila['peso'] !== null) {
        $ultimo = (float) $fila['peso'];
    }

    // Calcular diferencia
    $diferencia = null;
    if ($peso_inicial !== null && $ultimo !== null) {
        $diferencia = round($ultimo - $peso_inicial, 1);
    }

    return [
        'inicial' => $peso_inicial,
        'ultimo' => $ultimo,
        'diferencia' => $diferencia,
    ];
}