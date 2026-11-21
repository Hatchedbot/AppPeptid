<?php
/**
 * Pruebas de T1 — Clientes: crear, validar obligatorios, buscar y teléfono duplicado.
 */
require_once RAIZ . '/includes/clientes.php';

/** Teléfono ficticio de 10 dígitos que empieza por 3 (nunca es un número real). */
function cliente_tel_prueba(): string
{
    return '3' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
}

/** "3001234567" → "300 123 4567". */
function cliente_tel_formateado(string $telefono): string
{
    return substr($telefono, 0, 3) . ' ' . substr($telefono, 3, 3) . ' ' . substr($telefono, 6);
}

/** Devuelve el mensaje del error que lanza la función (y falla si no lanza). */
function cliente_error(callable $funcion): string
{
    try {
        $funcion();
    } catch (InvalidArgumentException $ex) {
        return $ex->getMessage();
    }
    throw new RuntimeException('debía lanzar InvalidArgumentException');
}

prueba('crear un cliente guarda sus datos, el consentimiento y el evento', function () {
    $u = usuario_prueba();
    $telefono = cliente_tel_prueba();

    $id = cliente_crear([
        'nombre' => 'Cliente Alfa de Prueba',
        'telefono' => $telefono,
        'correo' => 'alfa@example.com',
        'ciudad' => 'Bogotá',
        'fecha_nacimiento' => '1990-05-10',
        'sexo' => 'Femenino',
        'estatura_cm' => '170',
        'objetivo' => 'Bajar de peso',
        'condiciones' => 'Ninguna',
        'notas' => 'Prefiere que le escriban por WhatsApp',
        'consentimiento' => true,
    ]);

    verdadero($id > 0, 'debía devolver el id del cliente');
    $c = cliente_obtener($id);
    verdadero($c !== null, 'el cliente debía existir');
    igual('Cliente Alfa de Prueba', $c['nombre']);
    igual($telefono, $c['telefono']);
    igual('Bogotá', $c['ciudad']);
    igual('Femenino', $c['sexo']);
    igual('170', numero_corto($c['estatura_cm']));
    verdadero(!empty($c['consentimiento_fecha']), 'debía guardar la fecha del consentimiento');
    igual(1, (int) $c['activo']);

    $st = db()->prepare('SELECT texto, usuario_id FROM eventos WHERE cliente_id = ?');
    $st->execute([$id]);
    $eventos = $st->fetchAll();
    igual(1, count($eventos), 'debía registrar un evento al crear');
    igual($u['id'], (int) $eventos[0]['usuario_id']);
});

prueba('crear exige nombre, teléfono y consentimiento', function () {
    usuario_prueba();

    $sin_nombre = cliente_error(fn() => cliente_crear([
        'telefono' => cliente_tel_prueba(),
        'consentimiento' => true,
    ]));
    contiene('nombre', mb_strtolower($sin_nombre));

    $sin_telefono = cliente_error(fn() => cliente_crear([
        'nombre' => 'Cliente Sin Teléfono',
        'consentimiento' => true,
    ]));
    contiene('teléfono', mb_strtolower($sin_telefono));

    $sin_consentimiento = cliente_error(fn() => cliente_crear([
        'nombre' => 'Cliente Sin Autorización',
        'telefono' => cliente_tel_prueba(),
    ]));
    contiene('autorizó', mb_strtolower($sin_consentimiento));
});

prueba('buscar clientes por nombre, sin importar mayúsculas', function () {
    usuario_prueba();
    $marca = token_aleatorio(4);
    $id = cliente_crear([
        'nombre' => "Cliente $marca Búsqueda",
        'telefono' => cliente_tel_prueba(),
        'consentimiento' => true,
    ]);

    $resultados = clientes_buscar(mb_strtolower($marca));
    igual(1, count($resultados), 'debía encontrar solo el cliente de esta prueba');
    igual($id, (int) $resultados[0]['id']);
    igual(0, (int) $resultados[0]['casos_activos'], 'sin procesos activos');
    $ids = array_map('intval', array_column(clientes_buscar('busqueda'), 'id'));
    verdadero(in_array($id, $ids, true), 'debía encontrar el nombre aunque se busque sin tilde');

    // Un cliente desactivado no aparece en la lista.
    db()->prepare('UPDATE clientes SET activo = 0 WHERE id = ?')->execute([$id]);
    igual(0, count(clientes_buscar($marca)), 'no debía mostrar clientes inactivos');
});

prueba('buscar por teléfono escrito con otro formato', function () {
    usuario_prueba();
    $telefono = cliente_tel_prueba();
    $id = cliente_crear([
        'nombre' => 'Cliente Teléfono de Prueba',
        'telefono' => cliente_tel_formateado($telefono),
        'consentimiento' => true,
    ]);

    // Se guardó con espacios y se busca con el indicativo, con guiones y sin nada.
    foreach (['+57 ' . $telefono, substr($telefono, 0, 3) . '-' . substr($telefono, 3, 3) . '-' . substr($telefono, 6), $telefono] as $consulta) {
        $resultados = clientes_buscar($consulta);
        igual(1, count($resultados), 'debía encontrar el teléfono buscado como «' . $consulta . '»');
        igual($id, (int) $resultados[0]['id']);
    }
});

prueba('teléfono duplicado avisa a quién pertenece y no crea otro cliente', function () {
    usuario_prueba();
    $telefono = cliente_tel_prueba();
    $id_dueno = cliente_crear([
        'nombre' => 'Dueño del Teléfono de Prueba',
        'telefono' => $telefono,
        'consentimiento' => true,
    ]);

    $mensaje = cliente_error(fn() => cliente_crear([
        'nombre' => 'Otro Cliente de Prueba',
        'telefono' => cliente_tel_formateado($telefono),
        'consentimiento' => true,
    ]));
    contiene('ya está registrado', $mensaje);
    contiene('Dueño del Teléfono de Prueba', $mensaje, 'el error debía decir a quién pertenece el teléfono');

    $st = db()->prepare('SELECT COUNT(*) FROM clientes WHERE telefono = ?');
    $st->execute([$telefono]);
    igual(1, (int) $st->fetchColumn(), 'no debía crear un segundo cliente');

    // Tampoco se puede editar otro cliente para dejarle el mismo teléfono.
    $otro = cliente_crear([
        'nombre' => 'Cliente Para Editar',
        'telefono' => cliente_tel_prueba(),
        'consentimiento' => true,
    ]);
    $mensaje = cliente_error(fn() => cliente_actualizar($otro, [
        'nombre' => 'Cliente Para Editar',
        'telefono' => $telefono,
    ]));
    contiene('ya está registrado', $mensaje);
    contiene('Dueño del Teléfono de Prueba', $mensaje);

    $st = db()->prepare('SELECT telefono FROM clientes WHERE id = ?');
    $st->execute([$otro]);
    verdadero($st->fetchColumn() !== $telefono, 'no debía cambiar el teléfono');
    verdadero(cliente_obtener($id_dueno) !== null);
});

prueba('editar un cliente guarda los cambios y deja historial', function () {
    $u = usuario_prueba();
    $telefono = cliente_tel_prueba();
    $id = cliente_crear([
        'nombre' => 'Cliente Editable',
        'telefono' => $telefono,
        'consentimiento' => true,
        'ciudad' => 'Medellín',
    ]);
    $consentimiento = cliente_obtener($id)['consentimiento_fecha'];

    cliente_actualizar($id, [
        'nombre' => 'Cliente Editado',
        'telefono' => $telefono,
        'ciudad' => 'Cali',
        'notas' => 'Cambió de ciudad',
    ]);

    $c = cliente_obtener($id);
    igual('Cliente Editado', $c['nombre']);
    igual('Cali', $c['ciudad']);
    igual('Cambió de ciudad', $c['notas']);
    igual($consentimiento, $c['consentimiento_fecha'], 'no debía borrar el consentimiento');

    $st = db()->prepare('SELECT texto FROM eventos WHERE cliente_id = ? ORDER BY id');
    $st->execute([$id]);
    $textos = $st->fetchAll(PDO::FETCH_COLUMN);
    igual(2, count($textos), 'debía registrar el evento de la edición');
    contiene('actualizaron', mb_strtolower($textos[1]));
    contiene('ciudad', mb_strtolower($textos[1]));

    $sin_datos = cliente_error(fn() => cliente_actualizar($id, ['nombre' => '', 'telefono' => $telefono]));
    contiene('nombre', mb_strtolower($sin_datos));
});

prueba('obtener un cliente que no existe devuelve null', function () {
    igual(null, cliente_obtener(0));
    igual(null, cliente_obtener(999999));
});
