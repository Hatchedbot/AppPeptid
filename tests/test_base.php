<?php
prueba('las migraciones crean las tablas y el catálogo', function () {
    $n = (int) db()->query('SELECT COUNT(*) FROM productos')->fetchColumn();
    igual(23, $n, 'productos del catálogo');
    foreach (['usuarios', 'clientes', 'casos', 'planes', 'plan_items', 'seguimientos', 'eventos', 'ia_uso'] as $t) {
        db()->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    }
});

prueba('la traducción a SQLite quita lo propio de MySQL', function () {
    $c = sql_comandos("-- nota\nCREATE TABLE x (id INTEGER PRIMARY KEY AUTO_INCREMENT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;\nSELECT 1;", true);
    igual(['CREATE TABLE x (id INTEGER PRIMARY KEY AUTOINCREMENT)', 'SELECT 1'], $c);
});

prueba('crear usuario y entrar con la contraseña correcta', function () {
    crear_usuario('Ana Pérez', 'Ana', 'secreta123', 'asesor');
    verdadero(entrar('ana', 'secreta123') !== null, 'debía entrar');
    igual(null, entrar('ana', 'otra'), 'clave mala');
});

prueba('permisos por rol', function () {
    usuario_prueba('asesor');
    verdadero(puede('clientes'));
    verdadero(!puede('usuarios'), 'el asesor no administra usuarios');
    usuario_prueba('admin');
    verdadero(puede('usuarios'));
});

prueba('números escritos por personas', function () {
    igual(85.5, numero_entrada('85,5'));
    igual(1250000.0, numero_entrada('1.250.000'));
    igual(null, numero_entrada(''));
    igual('85', numero_corto(85.0));
    igual('85,5', numero_corto('85.5'));
});

prueba('enlace de WhatsApp con celular colombiano', function () {
    igual('573115034356', telefono_whatsapp('311 503 4356'));
    igual('573115034356', telefono_whatsapp('+57 311-503-4356'));
    contiene('https://wa.me/573115034356?text=Hola%20Juli%C3%A1n', enlace_whatsapp('3115034356', 'Hola Julián'));
});

prueba('registrar un evento en el historial', function () {
    $u = usuario_prueba();
    db()->prepare('INSERT INTO clientes (nombre, telefono, creado, actualizado) VALUES (?, ?, ?, ?)')
        ->execute(['Cliente Prueba', '3000000000', ahora(), ahora()]);
    $id = (int) db()->lastInsertId();
    registrar_evento($id, 'nota', 'Primera llamada');
    $st = db()->prepare('SELECT texto, usuario_id FROM eventos WHERE cliente_id = ?');
    $st->execute([$id]);
    igual(['texto' => 'Primera llamada', 'usuario_id' => $u['id']], $st->fetch());
});

prueba('plantillas: rellenar marcadores y leer la plantilla por nombre', function () {
    igual('Hola Ana, guía 123', mensaje_rellenar('Hola {nombre}, guía {guia}', ['nombre' => 'Ana', 'guia' => '123']));
    igual('Hola', mensaje_rellenar('Hola {nombre}', []));
    contiene('{guia}', plantilla_texto('Pedido despachado'));
    igual('', plantilla_texto('No existe'));
});
