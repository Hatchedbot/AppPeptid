<?php
/**
 * Pruebas de Lía (T3). Usan IA_FALSA: nunca llaman a internet ni usan claves reales.
 */
require_once dirname(__DIR__) . '/includes/ia.php';

function t3_config_ia(int $limite = 50): void
{
    $GLOBALS['CONFIG']['ia'] = [
        'limite_diario' => $limite,
        'orden' => ['gemini', 'groq'],
        'proveedores' => [
            'gemini' => [
                'url' => 'https://ia.invalida/gemini',
                'clave' => 'clave-de-prueba-gemini',
                'modelos' => ['modelo-gemini'],
            ],
            'groq' => [
                'url' => 'https://ia.invalida/groq',
                'clave' => 'clave-de-prueba-groq',
                'modelos' => ['modelo-groq'],
            ],
        ],
    ];
}

function t3_limpiar_uso(): void
{
    db()->exec('DELETE FROM ia_uso');
}

function t3_crear_cliente(string $nombre, string $telefono, string $ciudad = 'Bogotá', string $condiciones = ''): array
{
    db()->prepare('INSERT INTO clientes (nombre, telefono, correo, ciudad, fecha_nacimiento, condiciones, creado, actualizado)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$nombre, $telefono, 'prueba@ejemplo.test', $ciudad, '1990-05-04', $condiciones, ahora(), ahora()]);
    return ['id' => (int) db()->lastInsertId(), 'nombre' => $nombre, 'telefono' => $telefono];
}

function t3_crear_caso(int $clienteId, string $titulo, string $estado = 'activo'): int
{
    db()->prepare('INSERT INTO casos (cliente_id, titulo, estado, fecha_inicio, peso_inicial, peso_meta,
                   proximo_contacto, creado, actualizado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$clienteId, $titulo, $estado, '2026-10-01', 88.5, 76.0, hoy(), ahora(), ahora()]);
    return (int) db()->lastInsertId();
}

function t3_crear_plan(int $casoId, array $cliente): int
{
    db()->prepare('INSERT INTO planes (caso_id, version, indicaciones, motivo_cambio, token, creado)
                   VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$casoId, 1, 'Tomar con agua. Proceso de ' . $cliente['nombre'], 'Primera versión',
                   token_aleatorio(12), ahora()]);
    $planId = (int) db()->lastInsertId();
    db()->prepare('INSERT INTO plan_items (plan_id, producto_nombre, dosis, frecuencia, duracion, notas, orden)
                   VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$planId, 'Retatrutide', '2 mg', 'Semanal', '8 semanas', 'Llamar al ' . $cliente['telefono'], 0]);
    return $planId;
}

function t3_crear_seguimiento(int $casoId, array $cliente): int
{
    db()->prepare('INSERT INTO seguimientos (caso_id, fecha, tipo, peso, cintura_cm, adherencia, efectos, nota, creado)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$casoId, hoy(), 'control', 85.2, 90.0, 'completa', 'Sin novedad',
                   'Hablé con ' . $cliente['nombre'] . ' al ' . $cliente['telefono'], ahora()]);
    return (int) db()->lastInsertId();
}

/** Respuesta JSON de la IA falsa. */
function t3_json(array $campos = []): string
{
    return (string) json_encode(array_merge([
        'respuesta' => 'Listo.',
        'accion' => 'ninguna',
        'cliente_alias' => '',
        'caso_id' => 0,
        'busqueda' => '',
        'destino' => '',
    ], $campos), JSON_UNESCAPED_UNICODE);
}

prueba('ia_disponible: sin claves es falso; con IA falsa es verdadero', function () {
    unset($GLOBALS['IA_FALSA']);
    $GLOBALS['CONFIG']['ia'] = [
        'orden' => ['gemini'],
        'proveedores' => ['gemini' => ['url' => 'https://ia.invalida', 'clave' => '', 'modelos' => ['m']]],
    ];
    verdadero(!ia_disponible(), 'sin clave no está disponible');

    $GLOBALS['IA_FALSA'] = fn() => ['ok' => true, 'contenido' => t3_json(), 'codigo' => 200];
    verdadero(ia_disponible(), 'la IA falsa sí está disponible');
    unset($GLOBALS['IA_FALSA']);
});

prueba('lo enviado a la IA no lleva nombre, teléfono, ciudad ni condiciones: solo el alias', function () {
    t3_config_ia();
    t3_limpiar_uso();
    usuario_prueba();
    $cliente = t3_crear_cliente('Julián Gómez', '3001234567', 'Medellín', 'hipertensión');
    $caso = t3_crear_caso($cliente['id'], 'Proceso de Julián Gómez');
    t3_crear_plan($caso, $cliente);
    t3_crear_seguimiento($caso, $cliente);

    $enviado = [];
    $GLOBALS['IA_FALSA'] = function ($proveedor, $modelo, $payload) use (&$enviado) {
        $enviado[] = $payload;
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json(['respuesta' => 'Cliente 1 va bien.'])];
    };

    lia_responder(usuario_actual() ?? [], '¿Cómo va Julián Gómez con su proceso?', []);
    lia_responder(usuario_actual() ?? [], '¿Y Julián?', []);
    unset($GLOBALS['IA_FALSA']);

    verdadero($enviado !== [], 'debía llamar a la IA');
    $todo = (string) json_encode($enviado, JSON_UNESCAPED_UNICODE);
    contiene('Cliente 1', $todo, 'debía ir el alias');
    verdadero(!str_contains($todo, 'Julián'), 'no debe ir el nombre');
    verdadero(!str_contains($todo, 'Gómez'), 'no debe ir el apellido');
    verdadero(!str_contains($todo, '3001234567'), 'no debe ir el teléfono');
    verdadero(!str_contains($todo, 'Medellín'), 'no debe ir la ciudad');
    verdadero(!str_contains($todo, 'hipertensión'), 'no deben ir las condiciones');
    verdadero(!str_contains($todo, '1990-05-04'), 'no debe ir la fecha de nacimiento');
    contiene('Retatrutide', $todo, 'sí deben ir los ítems del plan');
    contiene('85.2', $todo, 'sí deben ir los seguimientos');
});

prueba('el alias se cambia por el primer nombre en la respuesta', function () {
    t3_config_ia();
    t3_limpiar_uso();
    usuario_prueba();
    $cliente = t3_crear_cliente('Mariana Restrepo', '3019876543');
    t3_crear_caso($cliente['id'], 'Proceso de bienestar');

    $GLOBALS['IA_FALSA'] = function () use ($cliente) {
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json([
            'respuesta' => 'Cliente 1 va estable y tiene control pendiente.',
            'accion' => 'abrir_cliente',
            'cliente_alias' => 'Cliente 1',
            'url' => 'https://sitio-inventado.example/cliente',
        ])];
    };
    $r = lia_responder(usuario_actual() ?? [], '¿Cómo va Mariana Restrepo?', []);
    unset($GLOBALS['IA_FALSA']);

    igual('Mariana va estable y tiene control pendiente.', $r['respuesta']);
    igual('abrir_cliente', $r['accion']);
    igual('cliente.php?id=' . $cliente['id'], $r['url'], 'la URL la arma el servidor');
    verdadero(!str_contains((string) json_encode($r), 'sitio-inventado'), 'la URL de la IA se ignora');
});

prueba('la URL la arma el servidor: buscar y abrir caso; una URL inventada se ignora', function () {
    t3_config_ia();
    t3_limpiar_uso();
    usuario_prueba();
    $cliente = t3_crear_cliente('Camila Ospina', '3025557788');
    $caso = t3_crear_caso($cliente['id'], 'Proceso de fuerza');

    $GLOBALS['IA_FALSA'] = function () {
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json([
            'respuesta' => 'Te muestro los clientes que coinciden.',
            'accion' => 'buscar',
            'busqueda' => 'Gómez',
            'url' => 'https://malo.example/robo',
        ])];
    };
    $r = lia_responder(usuario_actual() ?? [], 'Busca Gómez', []);
    igual('buscar', $r['accion']);
    igual('clientes.php?q=G%C3%B3mez', $r['url']);
    verdadero(!str_contains((string) json_encode($r), 'malo.example'), 'la URL inventada no se usa');

    $GLOBALS['IA_FALSA'] = function () use ($caso) {
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json([
            'respuesta' => 'Abrí el caso.',
            'accion' => 'abrir_caso',
            'cliente_alias' => 'Cliente 1',
            'caso_id' => $caso,
            'url' => 'https://otro-inventado.example/x',
        ])];
    };
    $r = lia_responder(usuario_actual() ?? [], 'Abre el caso de Camila Ospina', []);
    igual('abrir_caso', $r['accion']);
    igual('caso.php?id=' . $caso, $r['url']);
    verdadero(!str_contains((string) json_encode($r), 'otro-inventado'), 'la URL inventada no se usa');

    $GLOBALS['IA_FALSA'] = function () {
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json([
            'respuesta' => 'Ese caso no es de los clientes encontrados.',
            'accion' => 'abrir_caso',
            'cliente_alias' => 'Cliente 1',
            'caso_id' => 999999,
        ])];
    };
    $r = lia_responder(usuario_actual() ?? [], 'Abre un caso de Camila Ospina', []);
    igual('ninguna', $r['accion'], 'un caso ajeno no se abre');
    igual('', $r['url']);
    unset($GLOBALS['IA_FALSA']);
});

prueba('si el primer proveedor falla se usa el segundo', function () {
    t3_config_ia();
    t3_limpiar_uso();
    usuario_prueba();

    $vistos = [];
    $GLOBALS['IA_FALSA'] = function ($proveedor, $modelo, $payload) use (&$vistos) {
        $vistos[] = $proveedor . '/' . $modelo;
        if ($proveedor === 'gemini') {
            return ['ok' => false, 'contenido' => '', 'codigo' => 500, 'detalle' => 'sin servicio'];
        }
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json(['respuesta' => 'Respondió el respaldo.'])];
    };
    $r = lia_responder(usuario_actual() ?? [], 'Hola', []);
    unset($GLOBALS['IA_FALSA']);

    igual('Respondió el respaldo.', $r['respuesta']);
    igual(['gemini/modelo-gemini', 'groq/modelo-groq'], $vistos, 'debía pasar al segundo proveedor');

    $filas = db()->query('SELECT modelo, ok FROM ia_uso ORDER BY id')->fetchAll();
    igual(2, count($filas), 'cada intento se registra en ia_uso');
    igual('gemini/modelo-gemini', $filas[0]['modelo']);
    igual(0, (int) $filas[0]['ok'], 'el intento fallido queda con ok = 0');
    igual('groq/modelo-groq', $filas[1]['modelo']);
    igual(1, (int) $filas[1]['ok'], 'el intento bueno queda con ok = 1');
});

prueba('si el modelo rechaza el esquema se reintenta con json_object', function () {
    t3_config_ia();
    t3_limpiar_uso();
    usuario_prueba();

    $formatos = [];
    $GLOBALS['IA_FALSA'] = function ($proveedor, $modelo, $payload) use (&$formatos) {
        $tipo = (string) ($payload['response_format']['type'] ?? '');
        $formatos[] = $tipo;
        if ($tipo === 'json_schema') {
            return ['ok' => false, 'contenido' => '', 'codigo' => 400, 'detalle' => 'esquema no soportado'];
        }
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json(['respuesta' => 'Con json_object.'])];
    };
    $r = lia_responder(usuario_actual() ?? [], 'Hola', []);
    unset($GLOBALS['IA_FALSA']);

    igual('Con json_object.', $r['respuesta']);
    igual(['json_schema', 'json_object'], $formatos, 'debía reintentar sin esquema');
});

prueba('el límite diario corta antes de llamar a la IA', function () {
    t3_config_ia(1);
    t3_limpiar_uso();
    usuario_prueba();
    db()->prepare('INSERT INTO ia_uso (usuario_id, funcion, modelo, ok, tokens_entrada, tokens_salida, detalle, fecha)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([null, 'prueba', 'gemini/modelo-gemini', 1, 1, 1, null, ahora()]);

    $llamada = false;
    $GLOBALS['IA_FALSA'] = function () use (&$llamada) {
        $llamada = true;
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json()];
    };

    $mensaje = '';
    try {
        ia_consultar('prueba', 'sistema', 'texto', ['type' => 'object']);
    } catch (RuntimeException $ex) {
        $mensaje = $ex->getMessage();
    }
    unset($GLOBALS['IA_FALSA']);

    igual('Se agotó por hoy el uso de la IA', $mensaje);
    verdadero(!$llamada, 'no debía llamar a la IA al llegar al límite');
});
