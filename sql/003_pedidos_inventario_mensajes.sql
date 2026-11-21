-- Ronda 2: pedidos con pagos y estados, inventario por lote y mensajes/campañas.

ALTER TABLE productos ADD COLUMN stock_minimo INTEGER NOT NULL DEFAULT 3;

-- Un lote es un grupo de viales con el mismo código de lote (el del COA) y vencimiento.
CREATE TABLE lotes (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    producto_id INTEGER NOT NULL REFERENCES productos(id),
    codigo VARCHAR(60) NOT NULL,
    vencimiento DATE NULL,
    notas TEXT NULL,
    creado_por INTEGER NULL REFERENCES usuarios(id),
    creado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE UNIQUE INDEX lotes_producto_codigo ON lotes (producto_id, codigo);

-- Cada entrada o salida de viales. cantidad: positiva entra, negativa sale.
-- tipo: entrada | salida | ajuste | devolucion
CREATE TABLE movimientos_inventario (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    lote_id INTEGER NOT NULL REFERENCES lotes(id),
    tipo VARCHAR(20) NOT NULL,
    cantidad INTEGER NOT NULL,
    pedido_id INTEGER NULL,
    nota VARCHAR(255) NULL,
    usuario_id INTEGER NULL REFERENCES usuarios(id),
    fecha DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX movimientos_lote ON movimientos_inventario (lote_id);
CREATE INDEX movimientos_pedido ON movimientos_inventario (pedido_id);

-- estado (logística): cotizado | confirmado | despachado | entregado | cancelado | devuelto
-- El estado del pago se calcula con la tabla pagos (pendiente | parcial | pagado).
-- contraentrega = 1: se cobra en efectivo al entregar, así que se puede despachar sin pago previo.
-- Valores en pesos, enteros.
CREATE TABLE pedidos (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    caso_id INTEGER NULL REFERENCES casos(id),
    estado VARCHAR(20) NOT NULL DEFAULT 'cotizado',
    contraentrega TINYINT NOT NULL DEFAULT 0,
    subtotal INTEGER NOT NULL DEFAULT 0,
    envio INTEGER NOT NULL DEFAULT 0,
    descuento INTEGER NOT NULL DEFAULT 0,
    total INTEGER NOT NULL DEFAULT 0,
    direccion VARCHAR(255) NULL,
    ciudad VARCHAR(80) NULL,
    transportadora VARCHAR(80) NULL,
    guia VARCHAR(80) NULL,
    notas TEXT NULL,
    token VARCHAR(40) NOT NULL UNIQUE,
    asesor_id INTEGER NULL REFERENCES usuarios(id),
    creado DATETIME NOT NULL,
    actualizado DATETIME NOT NULL,
    despachado DATETIME NULL,
    entregado DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX pedidos_cliente ON pedidos (cliente_id);
CREATE INDEX pedidos_estado ON pedidos (estado);

CREATE TABLE pedido_items (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    pedido_id INTEGER NOT NULL REFERENCES pedidos(id),
    producto_id INTEGER NOT NULL REFERENCES productos(id),
    producto_nombre VARCHAR(120) NOT NULL,
    cantidad INTEGER NOT NULL,
    precio_unitario INTEGER NOT NULL,
    subtotal INTEGER NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX pedido_items_pedido ON pedido_items (pedido_id);

-- metodo: nequi | efectivo | bre_b.  valor negativo = devolución de dinero.
CREATE TABLE pagos (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    pedido_id INTEGER NOT NULL REFERENCES pedidos(id),
    fecha DATE NOT NULL,
    metodo VARCHAR(20) NOT NULL,
    valor INTEGER NOT NULL,
    referencia VARCHAR(80) NULL,
    nota VARCHAR(255) NULL,
    usuario_id INTEGER NULL REFERENCES usuarios(id),
    creado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX pagos_pedido ON pagos (pedido_id);

-- Línea de tiempo del pedido (la ve el cliente en su enlace).
CREATE TABLE pedido_historial (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    pedido_id INTEGER NOT NULL REFERENCES pedidos(id),
    estado VARCHAR(20) NOT NULL,
    nota VARCHAR(255) NULL,
    usuario_id INTEGER NULL REFERENCES usuarios(id),
    fecha DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX pedido_historial_pedido ON pedido_historial (pedido_id);

-- Plantillas con marcadores: {nombre} {producto} {guia} {transportadora} {total} {enlace} {fecha}
CREATE TABLE plantillas_mensaje (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(80) NOT NULL,
    texto TEXT NOT NULL,
    activa TINYINT NOT NULL DEFAULT 1,
    creado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Una campaña es un mensaje para varios clientes a la vez.
CREATE TABLE campanas (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    texto TEXT NOT NULL,
    filtro VARCHAR(255) NULL,
    creado_por INTEGER NULL REFERENCES usuarios(id),
    creado DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cada mensaje a un cliente. canal: whatsapp_manual (wa.me, la persona toca Enviar) | whatsapp_api (futuro)
-- estado: pendiente | enviado | omitido
CREATE TABLE mensajes (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    cliente_id INTEGER NOT NULL REFERENCES clientes(id),
    campana_id INTEGER NULL REFERENCES campanas(id),
    texto TEXT NOT NULL,
    canal VARCHAR(20) NOT NULL,
    estado VARCHAR(20) NOT NULL,
    usuario_id INTEGER NULL REFERENCES usuarios(id),
    creado DATETIME NOT NULL,
    enviado DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX mensajes_cliente ON mensajes (cliente_id);
CREATE INDEX mensajes_campana ON mensajes (campana_id, estado);
