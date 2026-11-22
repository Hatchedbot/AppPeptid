<?php
require_once dirname(__DIR__) . '/includes/usuarios.php';

prueba('Crea y edita un usuario con contraseña protegida', function () {
    $datos = ['nombre' => 'Cuenta de prueba', 'usuario' => '  Cuenta_Nueva  ', 'rol' => 'asesor', 'activo' => true];
    $clave = token_aleatorio();
    $id = usuario_guardar($datos + ['clave' => $clave]);
    igual('cuenta_nueva', usuario_obtener($id)['usuario']);
    verdadero(in_array($id, array_column(usuarios_listar(), 'id'), true));
    $datos['nombre'] = 'Nombre actualizado';
    usuario_guardar($datos + ['id' => $id]);
    igual('Nombre actualizado', usuario_obtener($id)['nombre']);
    verdadero((bool) entrar('cuenta_nueva', $clave));
    $nueva = token_aleatorio();
    usuario_cambiar_clave($id, $nueva);
    igual(null, entrar('cuenta_nueva', $clave));
    verdadero((bool) entrar('cuenta_nueva', $nueva));
});

prueba('Rechaza un usuario duplicado y una contraseña corta', function () {
    $existente = usuario_prueba();
    $datos = ['nombre' => 'Cuenta de prueba', 'rol' => 'asesor', 'activo' => true];
    foreach ([
        ['usuario' => strtoupper($existente['usuario']), 'clave' => token_aleatorio(), 'mensaje' => 'ya existe'],
        ['usuario' => 'cuenta_clave_corta', 'clave' => token_aleatorio(3), 'mensaje' => '8 caracteres'],
    ] as $entrada) {
        try {
            usuario_guardar($datos + $entrada);
            throw new RuntimeException('Se esperaba un error de validación.');
        } catch (InvalidArgumentException $ex) {
            contiene($entrada['mensaje'], $ex->getMessage());
        }
    }
});

prueba('Los permisos personalizados reemplazan los permisos del rol', function () {
    $id = (int) usuario_prueba()['id'];
    usuario_guardar_permisos($id, true, ['clientes', 'clientes']);
    $GLOBALS['USUARIO_FORZADO'] = usuario_obtener($id);
    igual(['clientes'], permisos_de_usuario($id));
    verdadero(puede('clientes'));
    igual(false, puede('casos'));
    igual(false, puede('usuarios'));
    try {
        usuario_guardar_permisos($id, true, ['usuarios']);
        throw new RuntimeException('Se aceptó un permiso exclusivo del administrador.');
    } catch (InvalidArgumentException $ex) {
        igual(['clientes'], permisos_de_usuario($id));
    }
    usuario_guardar_permisos($id, false, []);
    $GLOBALS['USUARIO_FORZADO'] = usuario_obtener($id);
    igual([], permisos_de_usuario($id));
    verdadero(puede('casos'));
});

prueba('Protege al último administrador activo y evita la desactivación propia', function () {
    $admin = usuario_prueba('admin');
    $otros = db()->prepare('SELECT id FROM usuarios WHERE rol = ? AND activo = 1 AND id <> ?');
    $otros->execute(['admin', $admin['id']]);
    $ids = $otros->fetchAll(PDO::FETCH_COLUMN);
    $estado = db()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?');
    try {
        foreach ($ids as $id) {
            $estado->execute([0, $id]);
        }
        foreach ([
            [false, 'admin', 'propia cuenta'],
            [true, 'asesor', 'al menos un administrador activo'],
            [false, 'admin', 'al menos un administrador activo'],
        ] as $indice => [$activo, $rol, $mensaje]) {
            if ($indice === 2) {
                unset($GLOBALS['USUARIO_FORZADO']);
            }
            try {
                usuario_guardar(array_replace($admin, ['activo' => $activo, 'rol' => $rol]));
                throw new RuntimeException('Se esperaba que la cuenta siguiera activa como administrador.');
            } catch (InvalidArgumentException $ex) {
                contiene($mensaje, $ex->getMessage());
            }
        }
        igual('admin', usuario_obtener($admin['id'])['rol']);
        igual(1, usuario_obtener($admin['id'])['activo']);
    } finally {
        foreach ($ids as $id) {
            $estado->execute([1, $id]);
        }
    }
});
