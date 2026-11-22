<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . '/includes/app.php';
require_once RAIZ . '/includes/clientes.php';
require_once RAIZ . '/includes/casos.php';
require_once RAIZ . '/includes/pedidos.php';
require_once RAIZ . '/includes/mensajes.php';
require_once RAIZ . '/includes/usuarios.php';

function demo_validar_dsn(): void
{
    if (!str_starts_with((string) config('db.dsn'), 'sqlite:')) {
        throw new InvalidArgumentException('La demo requiere un DSN SQLite.');
    }
}

/** Devuelve las credenciales generadas, para que la consola las guarde sin mostrarlas. */
function llenar_demo(): array
{
    demo_validar_dsn();
    if (!es_sqlite()) {
        throw new InvalidArgumentException('La demo requiere una conexión SQLite.');
    }
    // La búsqueda de clientes de la app solo muestra activos.
    $consulta = db()->prepare('SELECT 1 FROM clientes LIMIT 1');
    $consulta->execute();
    $hay_clientes = $consulta->fetchColumn() !== false;
    $consulta->closeCursor();
    if ($hay_clientes) {
        throw new InvalidArgumentException('La base ya tiene clientes. Para rehacer la demo, borra el archivo demo.sqlite.');
    }

    $productos = inventario_productos();
    if (count($productos) < 12) {
        throw new InvalidArgumentException('El catálogo necesita al menos 12 productos activos para la demo.');
    }

    $usuarios = array_column(usuarios_listar(), 'usuario', 'usuario');
    $claves = [];
    $asesores = [];
    foreach ([
        ['demo', 'Equipo BRDG', 'admin'],
        ['andrea.demo', 'Andrea Rojas', 'asesor'],
        ['camilo.demo', 'Camilo Pérez', 'asesor'],
    ] as [$usuario, $nombre, $rol]) {
        $base = $usuario;
        for ($sufijo = 2; isset($usuarios[$usuario]); $sufijo++) {
            $usuario = $base . '.' . $sufijo;
        }
        $clave = strtr(base64_encode(random_bytes(12)), '+/', '-_');
        $clave = substr($clave, 0, 14);
        $id = crear_usuario($nombre, $usuario, $clave, $rol);
        $claves[$usuario] = $clave;
        $asesores[] = usuario_obtener($id);
    }
    usuario_guardar_permisos((int) $asesores[2]['id'], true, ['pedidos', 'inventario']);

    $hoy = new DateTimeImmutable(hoy());
    $fecha = static fn(int $dias): string => $hoy->modify(sprintf('%+d days', $dias))->format('Y-m-d');
    $usuario_anterior = $GLOBALS['USUARIO_FORZADO'] ?? null;
    try {
        $GLOBALS['USUARIO_FORZADO'] = $asesores[0];
        foreach ($productos as $i => $producto) {
            $id = (int) $producto['id'];
            inventario_actualizar_producto($id, ['stock_minimo' => 4, 'precio' => $producto['precio']]);
            $cantidad = match ($i) {
                0, 1 => 0,
                2, 3, 4 => 2,
                default => 12 + $i % 9,
            };
            if ($cantidad > 0) {
                inventario_entrada($id, 'DEMO-' . token_aleatorio(4), $fecha(90 + $i * 7), $cantidad, 'Ingreso de muestra');
            }
        }

        $clientes = [];
        $casos = [];
        $numero = 1;
        $contactos = [-5, -3, -1, 0, 0, 0, 1, 2, 4, 7];
        foreach ([
            ['Lucía Arbeláez Niebla', 'Bogotá'], ['Nicolás Múnera Prado', 'Medellín'],
            ['Valeria Caicedo Alba', 'Cali'], ['Mateo Villamizar Pino', 'Bucaramanga'],
            ['Isabela Ospina Linde', 'Pereira'], ['Santiago Borrero Risco', 'Barranquilla'],
            ['Mariana Patiño Cumbre', 'Manizales'], ['Tomás Beltrán Sauce', 'Ibagué'],
            ['Paula Cárdenas Brisa', 'Cartagena'], ['Emilio Fajardo Cedro', 'Tunja'],
            ['Elena Lozano Monte', 'Neiva'], ['Simón Cifuentes Vega', 'Pasto'],
            ['Gabriela Narváez Olmo', 'Santa Marta'], ['Julián Zuluaga Orilla', 'Armenia'],
        ] as $i => [$nombre, $ciudad]) {
            do {
                if ($numero > 99) {
                    throw new InvalidArgumentException('Se agotaron los teléfonos reservados para la demo.');
                }
                $telefono = sprintf('300 000 00%02d', $numero++);
            } while (cliente_por_telefono($telefono));
            $GLOBALS['USUARIO_FORZADO'] = $asesores[$i % 2];
            $cliente = cliente_crear([
                'nombre' => $nombre, 'ciudad' => $ciudad, 'telefono' => $telefono,
                'consentimiento' => true, 'notas' => 'Perfil ficticio para demostración.',
            ]);
            $clientes[] = $cliente;
            if (!isset($contactos[$i])) {
                continue;
            }
            $peso = 72 + $i * 2.7;
            $caso = caso_crear([
                'cliente_id' => $cliente, 'titulo' => 'Seguimiento de peso',
                'objetivo' => 'Llevar un registro de peso y revisar los avances en cada contacto.',
                'fecha_inicio' => $fecha(-49), 'peso_inicial' => $peso, 'peso_meta' => $peso - 7,
                'proximo_contacto' => $fecha($contactos[$i]),
            ]);
            $casos[$i] = $caso;
            $items = [];
            foreach (array_slice($productos, 5 + $i % 5, 1 + $i % 3) as $producto) {
                $items[] = [
                    'producto_id' => (int) $producto['id'],
                    'dosis' => 'Dosis de muestra',
                    'frecuencia' => 'Frecuencia de muestra',
                ];
            }
            plan_nueva_version($caso, $items, 'Plan ficticio para demostración.', 'Plan inicial');
            $controles = 3 + $i % 4;
            for ($control = 1; $control <= $controles; $control++) {
                seguimiento_agregar([
                    'caso_id' => $caso, 'tipo' => 'control',
                    'fecha' => $fecha(-7 * ($controles - $control + 1)),
                    'peso' => round($peso - $control * (0.45 + $i * 0.03), 1),
                    'nota' => 'Registro de peso de muestra.',
                ]);
            }
        }

        $GLOBALS['USUARIO_FORZADO'] = $asesores[2];
        foreach ([
            'cotizado', 'confirmado', 'despachado', 'entregado', 'cancelado', 'confirmado',
            'cotizado', 'despachado', 'entregado', 'confirmado', 'devuelto', 'cancelado',
        ] as $i => $estado) {
            $pedido = pedido_crear([
                'cliente_id' => $clientes[$i], 'caso_id' => $casos[$i] ?? null,
                'direccion' => 'Punto de entrega ficticio ' . ($i + 1),
            ], [['producto_id' => (int) $productos[5 + $i % 7]['id'], 'cantidad' => 1 + $i % 2]]);
            if ($estado === 'cotizado') {
                continue;
            }
            if ($estado === 'cancelado') {
                pedido_cambiar_estado($pedido, $estado, ['nota' => 'El cliente aplazó la compra.']);
                continue;
            }
            pedido_cambiar_estado($pedido, 'confirmado');
            $total = (int) pedido_obtener($pedido)['total'];
            pago_agregar($pedido, [
                'metodo' => $i % 2 ? 'nequi' : 'bre_b',
                'valor' => $estado === 'confirmado' && $i !== 9 ? intdiv($total, 2) : $total,
                'fecha' => hoy(),
            ]);
            if ($estado !== 'confirmado') {
                pedido_cambiar_estado($pedido, 'despachado', [
                    'transportadora' => 'Envíos Cordillera (demo)',
                    'guia' => 'DEMO-' . str_pad((string) $pedido, 6, '0', STR_PAD_LEFT),
                ]);
                if ($estado !== 'despachado') {
                    pedido_cambiar_estado($pedido, $estado);
                }
            }
        }

        $GLOBALS['USUARIO_FORZADO'] = $asesores[1];
        $campana = campana_crear('Seguimiento de la semana',
            'Hola {nombre}, ¿cómo vas? Cuéntanos cuándo te queda bien revisar tus avances. Mensaje de demostración.',
            array_slice($clientes, 0, 8), 'Procesos activos de demo');
        for ($i = 0; $i < 3; $i++) {
            $mensaje = mensaje_siguiente($campana);
            mensaje_marcar((int) $mensaje['id'], 'enviado');
        }
    } finally {
        if ($usuario_anterior === null) {
            unset($GLOBALS['USUARIO_FORZADO']);
        } else {
            $GLOBALS['USUARIO_FORZADO'] = $usuario_anterior;
        }
    }
    return $claves;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    try {
        if (count($argv) !== 1) {
            throw new InvalidArgumentException('Uso: php herramientas/datos_demo.php');
        }
        demo_validar_dsn();
        $ruta = RAIZ . '/datos/LEEME-DEMO.txt';
        $carpeta = dirname($ruta);
        if (!is_dir($carpeta) && !mkdir($carpeta, 0700, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo crear la carpeta datos.');
        }
        $mascara = umask(0077);
        try {
            $archivo = @fopen($ruta, 'cb');
        } finally {
            umask($mascara);
        }
        if ($archivo === false) {
            throw new RuntimeException('No se pudo abrir datos/LEEME-DEMO.txt.');
        }
        try {
            if (!flock($archivo, LOCK_EX)) {
                throw new RuntimeException('No se pudo bloquear el archivo de credenciales.');
            }
            if (PHP_OS_FAMILY !== 'Windows') {
                @chmod($ruta, 0600);
            }
            $claves = llenar_demo();
            $contenido = "Este archivo es solo para la demo. No lo compartas.\n";
            $contenido .= "BRDG Seguimiento | Accesos de demostración | " . ahora() . "\n";
            foreach ($claves as $usuario => $clave) {
                $contenido .= "$usuario: $clave\n";
            }
            if (!@ftruncate($archivo, 0) || @fwrite($archivo, $contenido) !== strlen($contenido) || !@fflush($archivo)) {
                throw new RuntimeException('No se pudieron guardar las credenciales completas.');
            }
        } finally {
            fclose($archivo);
        }
        echo e('Demo lista. Las credenciales quedaron en datos/LEEME-DEMO.txt.') . PHP_EOL;
    } catch (InvalidArgumentException | RuntimeException $ex) {
        fwrite(STDERR, e($ex instanceof PDOException ? 'No se pudo llenar la base SQLite.' : $ex->getMessage()) . PHP_EOL);
        exit(1);
    }
}
