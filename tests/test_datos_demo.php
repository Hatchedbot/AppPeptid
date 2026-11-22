<?php
require_once RAIZ . '/herramientas/datos_demo.php';

prueba('llena una SQLite vacía con datos actuales y se niega si ya tiene clientes', function () {
    // El corredor comparte una conexión entre módulos. Corre este caso en una conexión limpia.
    if (($GLOBALS['argv'][1] ?? '') !== 'datos_demo') {
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/run.php') . ' datos_demo 2>&1', $salida, $codigo);
        igual(0, $codigo, implode("\n", $salida));
        return;
    }

    $claves = llenar_demo();
    igual(['demo', 'andrea.demo', 'camilo.demo'], array_keys($claves));
    igual([14, 14, 14], array_map('strlen', array_values($claves)));
    igual('admin', entrar('demo', $claves['demo'])['rol']);
    $camilo = entrar('camilo.demo', $claves['camilo.demo']);
    igual(['inventario', 'pedidos'], permisos_de_usuario((int) $camilo['id']));
    foreach ([
        'usuarios' => 3, 'clientes' => 14, 'casos' => 10, 'planes' => 10,
        'seguimientos' => 43, 'pedidos' => 12, 'campanas' => 1,
    ] as $tabla => $esperado) {
        igual($esperado, (int) db()->query("SELECT COUNT(*) FROM $tabla")->fetchColumn(), $tabla);
    }
    igual(14, (int) db()->query('SELECT COUNT(*) FROM clientes WHERE consentimiento_fecha IS NOT NULL')->fetchColumn());
    $contactos = db()->prepare("SELECT
        SUM(CASE WHEN proximo_contacto < ? THEN 1 ELSE 0 END) AS atrasados,
        SUM(CASE WHEN proximo_contacto = ? THEN 1 ELSE 0 END) AS para_hoy,
        SUM(CASE WHEN proximo_contacto > ? THEN 1 ELSE 0 END) AS futuros
        FROM casos WHERE estado = 'activo'");
    $contactos->execute([hoy(), hoy(), hoy()]);
    igual([3, 3, 4], array_map('intval', array_values($contactos->fetch())));
    $estados = array_count_values(array_column(inventario_productos(), 'estado'));
    igual(2, $estados['agotado']);
    igual(3, $estados['bajo']);
    igual(3, count(pedidos_listar(['estado' => 'confirmado'])));
    igual([], array_diff(array_keys(PEDIDO_ESTADOS), array_column(pedidos_listar(), 'estado')));
    $campana = campanas_listar()[0];
    igual(['enviados' => 3, 'pendientes' => 5, 'omitidos' => 0, 'total' => 8], campana_avance((int) $campana['id']));

    try {
        llenar_demo();
        throw new RuntimeException('Se aceptó una base con clientes.');
    } catch (InvalidArgumentException $ex) {
        contiene('ya tiene clientes', $ex->getMessage());
    }
    igual(14, (int) db()->query('SELECT COUNT(*) FROM clientes')->fetchColumn());
    igual(3, (int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
});

prueba('rechaza un DSN MySQL', function () {
    $dsn = $GLOBALS['CONFIG']['db']['dsn'];
    $GLOBALS['CONFIG']['db']['dsn'] = 'mysql:host=invalid;dbname=demo';
    try {
        llenar_demo();
        throw new RuntimeException('Se aceptó un DSN MySQL.');
    } catch (InvalidArgumentException $ex) {
        contiene('DSN SQLite', $ex->getMessage());
    } finally {
        $GLOBALS['CONFIG']['db']['dsn'] = $dsn;
    }
});
