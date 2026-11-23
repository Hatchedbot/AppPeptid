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
                'clave' => '',
                'modelos' => ['modelo-gemini'],
            ],
            'groq' => [
                'url' => 'https://ia.invalida/groq',
                'clave' => '',
                'modelos' => ['modelo-groq'],
            ],
        ],
    ];
}

function t3_limpiar_uso(): void
{
    db()->exec('DELETE FROM ia_uso');
    unset($_SESSION['lia_historial'], $_SESSION['lia_aliases']);
}

function t3_crear_cliente(string $nombre, string $telefono, string $ciudad = 'Bogotá', string $condiciones = ''): array
{
    $correo = 'persona@ejemplo.test';
    db()->prepare('INSERT INTO clientes (nombre, telefono, correo, ciudad, fecha_nacimiento, condiciones, creado, actualizado)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$nombre, $telefono, $correo, $ciudad, '1990-05-04', $condiciones, ahora(), ahora()]);
    return ['id' => (int) db()->lastInsertId(), 'nombre' => $nombre, 'telefono' => $telefono, 'correo' => $correo];
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
    $contacto = $cliente['correo'] . ' ' . $cliente['telefono'];
    db()->prepare('INSERT INTO planes (caso_id, version, indicaciones, motivo_cambio, token, creado)
                   VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$casoId, 1, 'Indicaciones privadas ' . $contacto, 'Motivo privado ' . $contacto,
                   token_aleatorio(12), ahora()]);
    $planId = (int) db()->lastInsertId();
    db()->exec("INSERT INTO productos (nombre, presentacion, categoria)
                VALUES ('Producto de catálogo', 'Prueba', 'Prueba')");
    $productoId = (int) db()->lastInsertId();
    $item = db()->prepare('INSERT INTO plan_items (plan_id, producto_id, producto_nombre, dosis, frecuencia, duracion, notas, orden)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $item->execute([$planId, $productoId, 'Copia del nombre', 'Dosis de prueba', 'Semanal', '8 semanas',
                    'Nota de ítem privada ' . $contacto, 0]);
    $item->execute([$planId, null, 'Producto libre ' . $contacto, null, null, null, null, 1]);
    return $planId;
}

function t3_crear_seguimiento(int $casoId, array $cliente): int
{
    $contacto = $cliente['correo'] . ' ' . $cliente['telefono'];
    db()->prepare('INSERT INTO seguimientos (caso_id, fecha, tipo, peso, cintura_cm, adherencia, efectos, nota, creado)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$casoId, hoy(), 'control', 85.2, 90.0, 'completa', 'Efectos privados ' . $contacto,
                   'Nota de seguimiento privada ' . $contacto, ahora()]);
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

prueba('la pregunta, el contexto y el historial excluyen datos personales y notas', function () {
    t3_config_ia();
    t3_limpiar_uso();
    $usuario = usuario_prueba();
    $cliente = t3_crear_cliente('Julián Gómez', '0001234567', 'Ciudad de prueba', 'Condición de prueba');
    $caso = t3_crear_caso($cliente['id'], 'Proceso de Julián GOMEZ');
    db()->prepare('UPDATE casos SET objetivo = ? WHERE id = ?')->execute(['Objetivo privado', $caso]);
    t3_crear_plan($caso, $cliente);
    t3_crear_seguimiento($caso, $cliente);

    $enviado = [];
    $GLOBALS['IA_FALSA'] = function ($proveedor, $modelo, $payload) use (&$enviado) {
        $enviado[] = $payload;
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json(['respuesta' => 'Cliente 1 va bien.'])];
    };

    $primera = lia_responder($usuario, '¿Cómo va Julián Gómez? Contacto: julian.gomez@ejemplo.test, 0007654321.', []);
    igual('Julián va bien.', $primera['respuesta']);
    igual([
        'Tú: ¿Cómo va Cliente 1? Contacto: [correo], [número].',
        'Lía: Cliente 1 va bien.',
    ], $_SESSION['lia_historial']);
    lia_responder($usuario, '¿Y su último control?', $_SESSION['lia_historial']);
    unset($GLOBALS['IA_FALSA']);

    igual(2, count($enviado));
    foreach ($enviado as $payload) {
        $todo = json_encode($payload, JSON_UNESCAPED_UNICODE);
        contiene('Cliente 1', $todo);
        foreach (['Julián', 'Gómez', 'GOMEZ', $cliente['telefono'], $cliente['correo'], 'Ciudad de prueba',
                  'Condición de prueba', '1990-05-04', 'julian.gomez@ejemplo.test', '0007654321',
                  'Objetivo privado', 'Indicaciones privadas', 'Motivo privado', 'Nota de ítem privada',
                  'Efectos privados', 'Nota de seguimiento privada', 'Producto libre', 'Copia del nombre'] as $privado) {
            verdadero(!str_contains($todo, $privado), 'el payload no debe incluir ' . $privado);
        }
    }
    $primeraPregunta = $enviado[0]['messages'][1]['content'];
    contiene('Proceso de Cliente 1', $primeraPregunta);
    contiene('Producto de catálogo', $primeraPregunta);
    contiene('"plan_actual":{"version":1,"fecha":"', $primeraPregunta);
    contiene('85.2', $primeraPregunta);
    contiene('[correo], [número]', $primeraPregunta);
    $segundaPregunta = $enviado[1]['messages'][1]['content'];
    contiene('Conversación reciente:', $segundaPregunta);
    contiene('Tú: ¿Cómo va Cliente 1? Contacto: [correo], [número].', $segundaPregunta);
    contiene('Lía: Cliente 1 va bien.', $segundaPregunta);
});

prueba('el alias se cambia por el primer nombre en la respuesta', function () {
    t3_config_ia();
    t3_limpiar_uso();
    usuario_prueba();
    $cliente = t3_crear_cliente('Mariana Restrepo', '0009876543');
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
    $cliente = t3_crear_cliente('Camila Ospina', '0005557788');
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

prueba('un JSON sin respuesta se registra como fallo y pasa al siguiente proveedor', function () {
    t3_config_ia();
    t3_limpiar_uso();
    $usuario = usuario_prueba();
    $GLOBALS['IA_FALSA'] = function ($proveedor) {
        $datos = json_decode(t3_json(), true);
        if ($proveedor === 'gemini') {
            unset($datos['respuesta']);
        }
        return ['ok' => true, 'codigo' => 200, 'contenido' => json_encode($datos)];
    };
    $r = lia_responder($usuario, 'Hola', []);
    unset($GLOBALS['IA_FALSA']);
    igual('Listo.', $r['respuesta']);
    igual([0, 1], array_map('intval', db()->query('SELECT ok FROM ia_uso ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
});

prueba('el límite diario cuenta fallos e impide reintentar y llamar otra vez', function () {
    t3_config_ia(1);
    t3_limpiar_uso();
    usuario_prueba();
    $intentos = 0;
    $GLOBALS['IA_FALSA'] = function () use (&$intentos) {
        $intentos++;
        return ['ok' => false, 'codigo' => 400, 'detalle' => 'esquema no soportado'];
    };

    for ($i = 0; $i < 2; $i++) {
        $mensaje = '';
        try {
            ia_consultar('prueba', 'sistema', 'texto', lia_esquema());
        } catch (RuntimeException $ex) {
            $mensaje = $ex->getMessage();
        }
        igual('Se agotó por hoy el uso de la IA', $mensaje);
    }
    unset($GLOBALS['IA_FALSA']);

    igual(1, $intentos);
    igual([0], array_map('intval', db()->query('SELECT ok FROM ia_uso ORDER BY id')->fetchAll(PDO::FETCH_COLUMN)));
});

prueba('Lía reconoce al cliente con solo nombre y apellido', function () {
    db()->prepare('INSERT INTO clientes (nombre, telefono, creado, actualizado) VALUES (?, ?, ?, ?)')
        ->execute(['Mariana Ortiz Valencia', '3009998877', ahora(), ahora()]);
    $r = lia_buscar_clientes('como va mariana ortiz con su proceso');
    igual(['Mariana Ortiz Valencia'], array_column($r['completos'], 'nombre'));
    $r = lia_buscar_clientes('como va mariana');
    igual([], array_column($r['completos'], 'nombre'), 'solo el nombre sigue siendo una coincidencia parcial');
});

prueba('la ficha y el proceso se abren con datos locales sin esperar a la IA', function () {
    t3_config_ia();
    t3_limpiar_uso();
    $usuario = usuario_prueba();
    $cliente = t3_crear_cliente('Salomé Giraldo Prueba', '3009871142');
    $caso = t3_crear_caso($cliente['id'], 'Proceso actual');
    $llamadas = 0;
    $GLOBALS['IA_FALSA'] = function () use (&$llamadas) {
        $llamadas++;
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json([
            'respuesta' => 'Claro, aquí tienes la ficha de ese cliente.',
        ])];
    };

    $ficha = lia_responder($usuario, 'Abre la ficha de Salomé', []);
    $proceso = lia_responder($usuario, 'Quiero ir al proceso de Salomé', []);
    t3_crear_cliente('Salomé Pardo Prueba', '3009871143');
    $ambiguo = lia_responder($usuario, 'Quiero ver la ficha de Salomé', []);
    unset($GLOBALS['IA_FALSA']);

    igual('abrir_cliente', $ficha['accion']);
    igual('cliente.php?id=' . $cliente['id'], $ficha['url']);
    igual('Aquí tienes la ficha de Salomé.', $ficha['respuesta']);
    igual('abrir_caso', $proceso['accion']);
    igual('caso.php?id=' . $caso, $proceso['url']);
    igual('buscar', $ambiguo['accion']);
    igual('clientes.php?q=Salom%C3%A9', $ambiguo['url']);
    igual(0, $llamadas);
});

prueba('Lía consulta y navega por un nombre único, con permisos y sin enviar datos personales', function () {
    t3_config_ia();
    t3_limpiar_uso();
    $usuario = usuario_prueba('admin');
    $cliente = t3_crear_cliente('Nicolás Múnera Prado', '3009871145', 'Medellín');
    $caso = t3_crear_caso($cliente['id'], 'Proceso de Nicolás');
    t3_crear_caso($cliente['id'], 'Proceso cerrado', 'cerrado');
    $otro = t3_crear_cliente('Alicia Pardo', '3009871146');
    $insertar = db()->prepare('INSERT INTO pedidos (cliente_id, token, creado, actualizado) VALUES (?, ?, ?, ?)');
    $insertar->execute([$cliente['id'], token_aleatorio(12), '2026-10-03 10:00:00', ahora()]);
    $reciente = (int) db()->lastInsertId();
    $insertar->execute([$cliente['id'], token_aleatorio(12), '2026-10-02 10:00:00', ahora()]);
    $insertar->execute([$otro['id'], token_aleatorio(12), '2026-10-04 10:00:00', ahora()]);

    $enviados = [];
    $GLOBALS['IA_FALSA'] = function ($proveedor, $modelo, $payload) use (&$enviados) {
        $enviados[] = $payload['messages'][1]['content'];
        return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json(['respuesta' => 'Cliente 1 va bien.'])];
    };
    try {
        $pedido = lia_responder($usuario, 'llévame al pedido de nicolas', []);
        igual('pedido.php?id=' . $reciente, $pedido['url']);
        igual('Aquí tienes el pedido de Nicolás.', $pedido['respuesta']);
        $nuevo = lia_responder($usuario, 'quiero registrar un pedido para nicolas', []);
        igual('pedido.php?nuevo=1&cliente=' . $cliente['id'], $nuevo['url']);
        igual('Listo, te abrí un pedido nuevo para Nicolás. Revisa y guarda.', $nuevo['respuesta']);
        igual('cliente.php?id=' . $cliente['id'], lia_responder($usuario, 'abre la ficha de nicolas', [])['url']);
        igual('pedidos.php?cliente_id=' . $cliente['id'], lia_responder($usuario, 'LLEVAME a los pedidos de NICOLÁS', [])['url']);
        igual('envios.php?q=Nicol%C3%A1s%20M%C3%BAnera%20Prado', lia_responder($usuario, 'enséñame envíos de Nicolás', [])['url']);
        igual('caso.php?id=' . $caso, lia_responder($usuario, 'quiero ver el plan de nicolas', [])['url']);
        igual(0, count($enviados));

        igual('Nicolás va bien.', lia_responder($usuario, '¿cómo va nicolas?', [])['respuesta']);
        igual(1, count($enviados));
        contiene('¿cómo va Cliente 1?', $enviados[0]);
        contiene('"alias":"Cliente 1"', $enviados[0]);
        foreach (['nicolas', 'Nicolás', 'Múnera', 'Prado', $cliente['telefono'], $cliente['correo'], 'Medellín'] as $privado) {
            verdadero(!str_contains($enviados[0], $privado), 'el payload no debe incluir ' . $privado);
        }

        $homonimo = t3_crear_cliente('Nicolás', '3009871147');
        foreach (['llévame al pedido de nicolas', '¿cómo va nicolas?'] as $texto) {
            $ambiguo = lia_responder($usuario, $texto, []);
            igual('buscar', $ambiguo['accion']);
            igual('clientes.php?q=nicolas', $ambiguo['url']);
            contiene('varias coincidencias', $ambiguo['respuesta']);
        }
        $sinPedido = lia_responder($usuario, 'abre el pedido de Nicolás Múnera Prado', []);
        igual('pedido.php?id=' . $reciente, $sinPedido['url']);
        db()->prepare('UPDATE clientes SET activo = 0 WHERE id = ?')->execute([$cliente['id']]);
        $sinPedido = lia_responder($usuario, 'abre el pedido de Nicolás', []);
        igual('', $sinPedido['url']);
        contiene('no tiene pedidos', $sinPedido['respuesta']);
        db()->prepare('UPDATE clientes SET activo = 0 WHERE id = ?')->execute([$homonimo['id']]);

        $GLOBALS['USUARIO_FORZADO']['permisos_propios'] = 1;
        $GLOBALS['USUARIO_FORZADO']['rol'] = 'asesor';
        foreach (['pedido', 'pedidos', 'envíos'] as $destino) {
            $restringido = lia_responder(usuario_actual(), "abre $destino de Alicia", []);
            igual('', $restringido['url']);
            contiene('No tienes permiso', $restringido['respuesta']);
        }
        igual(1, count($enviados));
    } finally {
        unset($GLOBALS['IA_FALSA']);
        $GLOBALS['USUARIO_FORZADO'] = $usuario;
    }
});

prueba('Lía abre secciones y formularios precargados sin consultar a la IA ni guardar', function () {
    $usuario = usuario_prueba('admin');
    $cliente = t3_crear_cliente('Nicolás Herrera Prueba', '3105559876');
    $id = $cliente['id'];
    $rutas = [
        ['muéstrame a Nicolás Herrera', "cliente.php?id=$id", 'clientes'],
        ['el cliente Nicolás Herrera', "cliente.php?id=$id", 'clientes'],
        ['los pedidos de Nicolás Herrera', "pedidos.php?cliente_id=$id", 'pedidos'],
        ['quiero registrar un pedido para nicolas herrera', "pedido.php?nuevo=1&cliente=$id", 'pedidos'],
        ['nuevo pedido de Nicolás Herrera', "pedido.php?nuevo=1&cliente=$id", 'pedidos'],
        ['ayúdame a hacer un pedido', 'pedido.php?nuevo=1', 'pedidos'],
        ['nuevo proceso para Nicolás Herrera', "caso.php?nuevo=1&cliente=$id", 'casos'],
        ['necesito crear un plan para Nicolas Herrera', "caso.php?nuevo=1&cliente=$id", 'casos'],
        ['crear un cliente', 'cliente.php?nuevo=1', 'clientes'],
        ['registrar un cliente nuevo', 'cliente.php?nuevo=1', 'clientes'],
        ['¿Qué falta por enviar?', 'envios.php', 'pedidos'],
        ['llévame a envíos', 'envios.php', 'pedidos'],
        ['guias pendientes', 'envios.php', 'pedidos'],
        ['registrar una entrada', 'inventario.php?q=#entrada', 'inventario'],
        ['entrada de mercancía', 'inventario.php?q=#entrada', 'inventario'],
        ['stock de BPC', 'inventario.php?q=BPC', 'inventario'],
        ['nueva campaña', 'campana.php?nuevo=1', 'mensajes'],
        ['mandar mensaje a todos', 'campana.php?nuevo=1', 'mensajes'],
        ['escribirle a Nicolás Herrera', 'campana.php?nuevo=1&individual=1', 'mensajes'],
        ['ir a inicio', 'index.php', null],
        ['abre clientes', 'clientes.php', 'clientes'],
        ['pedidos', 'pedidos.php', 'pedidos'],
        ['inventario', 'inventario.php', 'inventario'],
        ['mensajes', 'mensajes.php', 'mensajes'],
        ['usuarios', 'usuarios.php', 'usuarios'],
    ];
    $cambios = db()->query('SELECT total_changes()')->fetchColumn();
    $GLOBALS['IA_FALSA'] = function () {
        throw new RuntimeException('La navegación no debe enviar datos a la IA.');
    };
    try {
        foreach ($rutas as [$texto, $url, $permiso]) {
            igual($url, lia_responder($usuario, $texto, [])['url'], $texto);
        }
        igual('clientes.php?q=Persona%20Inexistente',
            lia_responder($usuario, 'nuevo pedido para Persona Inexistente', [])['url']);
        t3_crear_cliente('Nicolás Suárez Prueba', '3105559877');
        igual('clientes.php?q=nicolas',
            lia_responder($usuario, 'quiero registrar un pedido para nicolas', [])['url']);
        $GLOBALS['USUARIO_FORZADO']['rol'] = 'asesor';
        $GLOBALS['USUARIO_FORZADO']['permisos_propios'] = 1;
        foreach ($rutas as [$texto, $url, $permiso]) {
            $resultado = lia_responder(usuario_actual(), $texto, []);
            igual($permiso === null ? $url : '', $resultado['url'], $texto);
            if ($permiso !== null) {
                contiene('No tienes permiso', $resultado['respuesta'], $texto);
            }
        }
        db()->prepare('INSERT INTO usuario_permisos (usuario_id, permiso) VALUES (?, ?)')
            ->execute([$usuario['id'], 'pedidos']);
        igual("pedido.php?nuevo=1&cliente=$id",
            lia_responder(usuario_actual(), 'nuevo pedido de Nicolás Herrera', [])['url']);
        igual('', lia_responder(usuario_actual(), 'nuevo pedido de nicolas', [])['url']);
        igual($cambios + 2, db()->query('SELECT total_changes()')->fetchColumn());
    } finally {
        unset($GLOBALS['IA_FALSA']);
    }
});

prueba('la búsqueda ignora términos de la IA y elimina aliases de todo el texto visible', function () {
    t3_config_ia();
    t3_limpiar_uso();
    $usuario = usuario_prueba();
    t3_crear_cliente('Natalia Ceballos', '3009871144');
    $GLOBALS['IA_FALSA'] = fn() => ['ok' => true, 'codigo' => 200, 'contenido' => t3_json([
        'respuesta' => 'Encontré a Cliente 1. Cliente 1 tiene ficha.',
        'accion' => 'buscar',
        'busqueda' => 'Cliente 1',
        'url' => 'clientes.php?q=Cliente%201',
    ])];
    $resuelta = lia_responder($usuario, 'Busca Natalia', []);

    $GLOBALS['IA_FALSA'] = fn() => ['ok' => true, 'codigo' => 200, 'contenido' => t3_json([
        'respuesta' => 'Cliente 42 tiene ficha. Abre ese cliente.',
        'accion' => 'buscar',
        'busqueda' => 'Cliente 42',
    ])];
    $desconocida = lia_responder($usuario, 'Busca Cliente%2042', []);
    unset($GLOBALS['IA_FALSA']);

    igual('clientes.php?q=Natalia', $resuelta['url']);
    igual('Aquí tienes los resultados de búsqueda para «Natalia».', $resuelta['respuesta']);
    igual('clientes.php', $desconocida['url']);
    igual('No pude identificar a quién se refiere la respuesta. Escribe el nombre para buscarlo.', $desconocida['respuesta']);
});

prueba('la agenda incluye solo diez casos activos de hoy o vencidos y protege datos personales', function () {
    db()->beginTransaction();
    try {
        t3_config_ia();
        t3_limpiar_uso();
        $usuario = usuario_prueba();
        db()->exec('UPDATE casos SET proximo_contacto = NULL');
        $actual = t3_crear_cliente('Leonor Salazar', '3175557654', 'Santa Rosa de Cabal');
        $vencido = t3_crear_cliente('Efraín Torres', '3175558765', 'Zipaquirá');
        $hoy = hoy();
        $ayer = (new DateTimeImmutable($hoy))->modify('-1 day')->format('Y-m-d');
        $manana = (new DateTimeImmutable($hoy))->modify('+1 day')->format('Y-m-d');
        t3_crear_caso($actual['id'], 'Control actual');
        $fecha = db()->prepare('UPDATE casos SET proximo_contacto = ? WHERE id = ?');
        $fecha->execute([$ayer, t3_crear_caso($vencido['id'], 'Control vencido')]);
        $fecha->execute([$manana, t3_crear_caso($actual['id'], 'Control futuro')]);
        t3_crear_caso($actual['id'], 'Control cerrado', 'cerrado');
        for ($i = 2; $i <= 12; $i++) {
            $dia = (new DateTimeImmutable($hoy))->modify("-$i days")->format('Y-m-d');
            $fecha->execute([$dia, t3_crear_caso($vencido['id'], 'Otro control vencido')]);
        }

        $preguntas = [];
        $GLOBALS['IA_FALSA'] = function ($proveedor, $modelo, $payload) use (&$preguntas) {
            $preguntas[] = $payload['messages'][1]['content'];
            return ['ok' => true, 'codigo' => 200, 'contenido' => t3_json([
                'respuesta' => 'Cliente 1 tiene control hoy y Cliente 2 está atrasado.',
            ])];
        };
        $respuesta = lia_responder($usuario, '¿Qué clientes tienen controles pendientes hoy?', []);
        $atrasados = lia_responder($usuario, '¿Quién está atrasado?', $_SESSION['lia_historial']);
        $privada = '¿Hay pendientes? Leonor Salazar, 317 555 7654, persona@ejemplo.test, SANTA ROSA DE CABAL y Zipaquira.';
        lia_responder($usuario, $privada, $_SESSION['lia_historial']);
        unset($GLOBALS['IA_FALSA']);

        igual(3, count($preguntas));
        foreach ($preguntas as $pregunta) {
            preg_match('/Agenda de casos activos para hoy y vencidos .*?: (\[.*\])/', $pregunta, $linea);
            $agenda = json_decode($linea[1] ?? 'null', true);
            igual(10, count($agenda ?? []));
            igual(['alias' => 'Cliente 1', 'proximo_contacto' => $hoy], $agenda[0]);
            igual(['alias' => 'Cliente 2', 'proximo_contacto' => $ayer], $agenda[1]);
            foreach (['Leonor', 'Salazar', 'Efraín', 'Torres', '3175557654', '317 555 7654', '3175558765',
                      'persona@ejemplo.test', 'Santa Rosa de Cabal', 'SANTA ROSA DE CABAL', 'Zipaquirá', 'Zipaquira'] as $dato) {
                verdadero(!str_contains($pregunta, $dato), 'no debe enviarse ' . $dato);
            }
        }
        igual('Leonor tiene control hoy y Efraín está atrasado.', $respuesta['respuesta']);
        igual($respuesta['respuesta'], $atrasados['respuesta']);
    } finally {
        unset($GLOBALS['IA_FALSA']);
        db()->rollBack();
    }
});
