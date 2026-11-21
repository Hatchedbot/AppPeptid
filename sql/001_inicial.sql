-- Esquema inicial. Fechas en texto 'AAAA-MM-DD HH:MM:SS' (las pone PHP con ahora()).

CREATE TABLE usuarios (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    usuario VARCHAR(60) NOT NULL UNIQUE,
    clave_hash VARCHAR(255) NOT NULL,
    rol VARCHAR(20) NOT NULL,
    activo TINYINT NOT NULL DEFAULT 1,
    creado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- consentimiento_fecha: cuándo el cliente autorizó el tratamiento de sus datos de salud (Ley 1581 de 2012)
CREATE TABLE clientes (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    correo VARCHAR(150) NULL,
    ciudad VARCHAR(80) NULL,
    fecha_nacimiento DATE NULL,
    sexo VARCHAR(10) NULL,
    estatura_cm DECIMAL(5,1) NULL,
    objetivo TEXT NULL,
    condiciones TEXT NULL,
    notas TEXT NULL,
    consentimiento_fecha DATETIME NULL,
    asesor_id INTEGER NULL REFERENCES usuarios(id),
    activo TINYINT NOT NULL DEFAULT 1,
    creado DATETIME NOT NULL,
    actualizado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX clientes_telefono ON clientes (telefono);
CREATE INDEX clientes_nombre ON clientes (nombre);

CREATE TABLE productos (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    presentacion VARCHAR(40) NOT NULL,
    categoria VARCHAR(40) NOT NULL,
    precio INTEGER NOT NULL DEFAULT 0,
    activo TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Un caso es un proceso de un cliente (ej. "Bajar de peso con Retatrutide"). Un cliente puede tener varios.
-- estado: activo | pausado | terminado
CREATE TABLE casos (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    titulo VARCHAR(150) NOT NULL,
    objetivo TEXT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'activo',
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    peso_inicial DECIMAL(5,1) NULL,
    peso_meta DECIMAL(5,1) NULL,
    proximo_contacto DATE NULL,
    asesor_id INTEGER NULL REFERENCES usuarios(id),
    creado DATETIME NOT NULL,
    actualizado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX casos_cliente ON casos (cliente_id);
CREATE INDEX casos_contacto ON casos (estado, proximo_contacto);

-- Cada cambio del plan crea una versión nueva: el historial de planes nunca se sobrescribe.
-- token: enlace público para que el cliente vea su plan sin usuario (plan.php?t=...)
CREATE TABLE planes (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    caso_id INTEGER NOT NULL REFERENCES casos(id),
    version INTEGER NOT NULL,
    indicaciones TEXT NULL,
    motivo_cambio TEXT NULL,
    token VARCHAR(40) NOT NULL UNIQUE,
    creado_por INTEGER NULL REFERENCES usuarios(id),
    creado DATETIME NOT NULL,
    enviado DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX planes_caso ON planes (caso_id, version);

CREATE TABLE plan_items (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    plan_id INTEGER NOT NULL REFERENCES planes(id),
    producto_id INTEGER NULL REFERENCES productos(id),
    producto_nombre VARCHAR(120) NOT NULL,
    dosis VARCHAR(60) NULL,
    frecuencia VARCHAR(80) NULL,
    duracion VARCHAR(60) NULL,
    notas TEXT NULL,
    orden INTEGER NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX plan_items_plan ON plan_items (plan_id);

-- tipo: control | llamada | mensaje | visita.  adherencia: completa | parcial | no_cumple
CREATE TABLE seguimientos (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    caso_id INTEGER NOT NULL REFERENCES casos(id),
    fecha DATE NOT NULL,
    tipo VARCHAR(20) NOT NULL,
    peso DECIMAL(5,1) NULL,
    cintura_cm DECIMAL(5,1) NULL,
    adherencia VARCHAR(20) NULL,
    efectos TEXT NULL,
    nota TEXT NULL,
    usuario_id INTEGER NULL REFERENCES usuarios(id),
    creado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX seguimientos_caso ON seguimientos (caso_id, fecha);

-- Línea de tiempo del cliente (lo escribe registrar_evento()).
CREATE TABLE eventos (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    caso_id INTEGER NULL REFERENCES casos(id),
    usuario_id INTEGER NULL REFERENCES usuarios(id),
    tipo VARCHAR(30) NOT NULL,
    texto TEXT NOT NULL,
    fecha DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX eventos_cliente ON eventos (cliente_id, fecha);

CREATE TABLE ia_uso (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    usuario_id INTEGER NULL REFERENCES usuarios(id),
    funcion VARCHAR(40) NOT NULL,
    modelo VARCHAR(80) NOT NULL,
    ok TINYINT NOT NULL,
    tokens_entrada INTEGER NOT NULL DEFAULT 0,
    tokens_salida INTEGER NOT NULL DEFAULT 0,
    detalle VARCHAR(200) NULL,
    fecha DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX ia_uso_fecha ON ia_uso (fecha);
