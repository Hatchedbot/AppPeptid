<?php
require_once dirname(__DIR__) . '/includes/casos.php';

function cliente_prueba_casos(): int
{
    db()->prepare('INSERT INTO clientes (nombre, telefono, creado, actualizado) VALUES (?, ?, ?, ?)')
        ->execute(['Cliente Ficticio', '0000000000', ahora(), ahora()]);
    return (int) db()->lastInsertId();
}

function comprobar_eventos_caso(int $cliente, int $caso, int $usuario, array $tipos): void
{
    $st = db()->prepare('SELECT cliente_id, caso_id, usuario_id, tipo FROM eventos WHERE cliente_id = ? OR caso_id = ? ORDER BY id');
    $st->execute([$cliente, $caso]);
    $eventos = $st->fetchAll();
    igual($tipos, array_column($eventos, 'tipo'));
    foreach ($eventos as $evento) {
        igual($cliente, (int) $evento['cliente_id']);
        igual($caso, (int) $evento['caso_id']);
        igual($usuario, (int) $evento['usuario_id']);
    }
}

prueba('crea y actualiza un caso y registra ambas acciones', function () {
    $usuario = usuario_prueba();
    $cliente = cliente_prueba_casos();
    $caso = caso_crear(['cliente_id' => $cliente, 'titulo' => 'Caso ficticio', 'peso_inicial' => 85.5]);
    igual('Caso ficticio', caso_obtener($caso)['titulo']);
    igual('activo', caso_obtener($caso)['estado']);
    caso_actualizar($caso, ['estado' => 'pausado', 'proximo_contacto' => '2026-11-25']);
    igual('pausado', caso_obtener($caso)['estado']);
    igual('2026-11-25', caso_obtener($caso)['proximo_contacto']);
    comprobar_eventos_caso($cliente, $caso, $usuario['id'], ['crear caso', 'actualizar caso']);
});

prueba('rechaza un caso sin cliente válido o sin título', function () {
    usuario_prueba();
    $cliente = cliente_prueba_casos();
    foreach ([
        ['cliente_id' => -1, 'titulo' => 'Caso ficticio'],
        ['cliente_id' => $cliente, 'titulo' => '  '],
    ] as $datos) {
        try {
            caso_crear($datos);
            throw new RuntimeException('Se guardó un caso inválido.');
        } catch (InvalidArgumentException $ex) {
            verdadero(str_contains($ex->getMessage(), $datos['cliente_id'] === -1 ? 'cliente' : 'título'), $ex->getMessage());
        }
    }
    $st = db()->prepare('SELECT COUNT(*) FROM casos WHERE cliente_id IN (?, ?)');
    $st->execute([$cliente, -1]);
    igual(0, (int) $st->fetchColumn());
});

prueba('conserva los ítems y el token de cada versión y registra el envío', function () {
    $usuario = usuario_prueba();
    $cliente = cliente_prueba_casos();
    $caso = caso_crear(['cliente_id' => $cliente, 'titulo' => 'Caso ficticio']);
    igual(null, plan_actual($caso));
    db()->prepare('INSERT INTO productos (nombre, presentacion, categoria) VALUES (?, ?, ?)')
        ->execute(['Ítem B', 'Presentación ficticia', 'Prueba']);
    $producto = (int) db()->lastInsertId();
    $primero = plan_nueva_version($caso, [
        ['producto_nombre' => 'Ítem A', 'notas' => 'Nota interna'],
        ['producto_id' => $producto],
    ], 'Indicaciones iniciales', 'Inicio');
    try {
        plan_nueva_version($caso, [['producto_nombre' => 'Ítem válido'], ['producto_id' => -1]], 'Indicaciones', 'Cambio');
        throw new RuntimeException('Se guardó un plan con un producto inexistente.');
    } catch (InvalidArgumentException $ex) {
        verdadero(str_contains($ex->getMessage(), 'catálogo'), $ex->getMessage());
    }
    igual($primero, (int) plan_actual($caso)['id']);
    $segundo = plan_nueva_version($caso, [['producto_nombre' => 'Ítem C']], 'Nuevas indicaciones', 'Cambio');
    $planes = planes_del_caso($caso);
    igual([2, 1], array_map('intval', array_column($planes, 'version')));
    igual($segundo, (int) plan_actual($caso)['id']);
    verdadero($planes[0]['token'] !== $planes[1]['token']);
    $publico = plan_por_token($planes[1]['token']);
    igual($primero, (int) $publico['id']);
    igual('Cliente', $publico['primer_nombre']);
    igual(['Ítem A', 'Ítem B'], array_column($publico['items'], 'producto_nombre'));
    igual('Nota interna', plan_items_del_plan($primero)[0]['notas']);
    igual(['producto_nombre', 'dosis', 'frecuencia', 'duracion'], array_keys($publico['items'][0]));
    igual(['Ítem C'], array_column(plan_por_token($planes[0]['token'])['items'], 'producto_nombre'));
    igual(null, plan_por_token('token-inexistente'));
    igual(null, $planes[0]['enviado']);
    plan_marcar_enviado($segundo);
    verdadero(plan_actual($caso)['enviado']);
    igual(null, planes_del_caso($caso)[1]['enviado']);
    comprobar_eventos_caso($cliente, $caso, $usuario['id'], ['crear caso', 'nueva versión plan', 'nueva versión plan', 'enviar plan']);
});

prueba('calcula el progreso con el último peso y guarda el próximo contacto del seguimiento', function () {
    $usuario = usuario_prueba();
    $cliente = cliente_prueba_casos();
    $caso = caso_crear(['cliente_id' => $cliente, 'titulo' => 'Caso ficticio', 'peso_inicial' => 85.5]);
    $datos = ['caso_id' => $caso, 'tipo' => 'control'];
    $ultimo = seguimiento_agregar($datos + ['fecha' => '2026-11-21', 'peso' => '82,2', 'proximo_contacto' => '2026-11-30']);
    seguimiento_agregar($datos + ['fecha' => '2026-11-20', 'peso' => 84]);
    seguimiento_agregar($datos + ['fecha' => '2026-11-22']);
    igual($ultimo, (int) seguimientos_del_caso($caso)[1]['id']);
    igual(['inicial' => 85.5, 'ultimo' => 82.2, 'diferencia' => -3.3], progreso_peso($caso));
    igual('2026-11-30', caso_obtener($caso)['proximo_contacto']);
    comprobar_eventos_caso($cliente, $caso, $usuario['id'], ['crear caso', 'nuevo seguimiento', 'nuevo seguimiento', 'nuevo seguimiento']);
});
