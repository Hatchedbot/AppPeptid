# Plan — BRDG Seguimiento

App web interna para **BRDG Peptides Colombia** (brdgpeptidecolombia.com): seguimiento de clientes, sus procesos
con péptidos, sus planes y su progreso, con una asistente de IA (Lía) a la que se le habla o escribe.

Cliente real de Joan Lara. Decisiones del 2026-11-20 (Joan aprobó ejecutar con las propuestas del líder; se
ajusta después de ver la primera versión).

## Primera versión

**Incluye**
- Usuarios del negocio con rol `admin` (dueño) o `asesor`. El cliente final no tiene cuenta.
- Clientes: datos de contacto, objetivo, condiciones de salud, notas, consentimiento de datos (Ley 1581).
- Casos (procesos): un cliente puede tener varios. Estado activo / pausado / terminado, peso inicial y meta,
  próximo contacto.
- Plan por caso, **versionado**: cada cambio crea una versión nueva con su motivo; nunca se sobrescribe.
  Ítems del plan: producto del catálogo (o texto libre), dosis, frecuencia, duración, notas.
- Envío del plan por WhatsApp: botón que abre WhatsApp con el mensaje escrito y el enlace público al plan
  (`plan.php?t=token`, sin usuario). Gratis; nada de API de WhatsApp Business.
- Seguimientos: controles, llamadas y mensajes con peso, cintura, adherencia, efectos y nota.
- Historial (línea de tiempo) por cliente.
- Inicio: a quién contactar hoy y quién está atrasado.
- Lía: asistente por texto y voz. Responde preguntas como «¿cómo va Julián Gómez con su proceso?», busca y
  abre fichas. Solo consulta y navega: nunca cambia datos ni recomienda dosis.

**Queda fuera de la primera versión** (pedidos, inventario y mensajes pasaron a la ronda 2, abajo): pagos en
línea, envío automático por la API de WhatsApp Business, portal del cliente con usuario, recordatorios automáticos.

## Ronda 2 — pedidos, inventario y mensajes (pedido por el equipo el 2026-11-20)

Lo pidió el compañero de trabajo de Joan: información de pedidos, estado de los pedidos, inventario y mensajes
personalizados, también a muchos clientes a la vez. Decisiones (Joan, 2026-11-20; se ajustan después):

- Pagos por **Nequi, efectivo y llave Bre-B**. La app no cobra: se anota cada pago a mano. Un pedido puede tener
  varios pagos (abonos) y devoluciones (pago negativo). Estado del pago calculado: pendiente, parcial o pagado.
- Estado del pedido: **cotizado → confirmado → despachado → entregado**, más **cancelado** y **devuelto**.
  Para despachar: pago completo, o pedido marcado **contraentrega** (efectivo al recibir). Despachar exige
  transportadora y guía (texto libre: aún no se sabe la transportadora) y descuenta el inventario.
- Inventario por **lote y fecha de vencimiento**. Se descuenta del lote que vence primero; nunca de lotes vencidos.
  Alertas de stock bajo (`productos.stock_minimo`), lotes por vencer (30 días) y vencidos con unidades.
- Mensajes: **plantillas** con marcadores y **campañas** a muchos clientes. WhatsApp no permite que una app gratis
  envíe sola en masa: la campaña prepara un mensaje personalizado por cliente y los abre uno tras otro para tocar
  Enviar. La tabla `mensajes` tiene `canal` para conectar después la API de WhatsApp Business (de pago, con
  verificación de Meta) sin rehacer nada.

Base de la ronda (líder, hecha): migraciones `sql/003` y `sql/004`, `includes/inventario.php` (stock, entradas,
descontar y reintegrar pedidos, alertas) con `tests/test_inventario_base.php`, permisos `pedidos`, `inventario` y
`mensajes`, menú, y `mensaje_rellenar()` / `plantilla_texto()` en `funciones.php`.

| # | Tarea | Agente | Revisa | Estado |
|---|---|---|---|---|
| 4 | Pedidos, pagos, estados y enlace público del pedido | Codex | Copilot | Pendiente |
| 5 | Pantallas de inventario: existencias, lotes, entradas, ajustes y alertas | OpenCode | Codex | Pendiente |
| 6 | Mensajes: plantillas, mensaje a un cliente y campañas | Grok | Codex | Pendiente |
| 7 | Usuarios (admin) y pulido de `caso.php` | Por asignar | | Siguiente |
| 8 | Guía para publicar en Hostinger | Líder | | Siguiente |

### T4 — Pedidos (Codex)
- **Archivos:** `pedidos.php`, `pedido.php`, `pedido_estado.php`, `includes/pedidos.php`, `tests/test_pedidos.php`.
- `includes/pedidos.php`: `pedido_crear(array $datos, array $items): int` (cliente, caso opcional del mismo cliente,
  ítems con producto del catálogo, cantidad > 0 y precio unitario —por defecto el del catálogo, editable—, envío
  por defecto 25.000, descuento, dirección, ciudad, notas, contraentrega; calcula subtotal y total; token),
  `pedido_obtener`, `pedido_items`, `pedido_actualizar` (solo en cotizado/confirmado), `pago_agregar(int $pedido_id,
  array $datos)` (método nequi/efectivo/bre_b, valor ≠ 0, referencia), `pedido_pagado(int $id): int`,
  `pedido_estado_pago(int $id): string` (pendiente | parcial | pagado), `pedido_cambiar_estado(int $id,
  string $nuevo, array $datos = [])` con estas reglas:
  cotizado → confirmado | cancelado; confirmado → despachado | cancelado; despachado → entregado | devuelto;
  despachar exige pago completo o contraentrega, transportadora y guía, y llama a `inventario_descontar_pedido()`;
  devuelto llama a `inventario_reintegrar_pedido()`; entregado con contraentrega exige el pago completo registrado.
  Todo en transacción (`en_transaccion()` de `includes/inventario.php`), con fila en `pedido_historial` y
  `registrar_evento()` en el cliente. `pedidos_listar(array $filtros)` por estado, estado de pago, cliente o texto.
- `pedidos.php`: tablero con pestañas por estado (con su número) y buscador; cada fila con cliente, total, estado
  de pago y fecha. Botón «Nuevo pedido».
- `pedido.php`: `?nuevo=1&cliente=N` (o elegir cliente) crea; `?id=N` muestra ítems, totales, pagos (formulario
  para agregar), estado con botones solo para los pasos permitidos, datos de envío, historial, botón «Avisar por
  WhatsApp» con la plantilla «Pedido despachado» (`plantilla_texto()` + `mensaje_rellenar()`) y enlace público.
- `pedido_estado.php?t=TOKEN`: pública, sin login (como `plan.php`): primer nombre, ítems, total, estado del pago,
  estado del pedido con su línea de tiempo, transportadora y guía. Nunca teléfono, dirección, correo ni notas.
- `cliente.php` no se toca: el líder agrega luego el enlace a los pedidos del cliente.
- **Hecho cuando:** `tests/run.php` pasa con pruebas de totales, pagos y estado de pago, cada transición permitida
  y prohibida, despacho que descuenta inventario (y falla sin stock o sin pago), devolución que reintegra, y token.

### T5 — Inventario (OpenCode)
- **Archivos:** `inventario.php`, `producto.php`, `tests/test_inventario.php`. Usa `includes/inventario.php` sin
  cambiarlo (si le falta algo, dilo en el informe).
- `inventario.php`: arriba las alertas (`inventario_alertas()`); tabla de productos con categoría, stock vigente,
  stock mínimo y estado (ok / bajo / agotado); buscador; formulario rápido «Entrada de mercancía» (producto, código
  de lote, vencimiento, cantidad, nota).
- `producto.php?id=N`: lotes con vencimiento y unidades (marcar vencidos y por vencer), movimientos recientes con
  tipo, cantidad, pedido y usuario, formulario de ajuste (lote, cantidad + o −, motivo obligatorio) y editar
  `stock_minimo` y `precio` del producto.
- **Hecho cuando:** `tests/run.php` pasa con pruebas de las funciones nuevas que crees (por ejemplo ajuste con
  motivo, cambio de stock mínimo) y las páginas cargan sin errores.

### T6 — Mensajes (Grok)
- **Archivos:** `mensajes.php`, `campana.php`, `includes/mensajes.php`, `tests/test_mensajes.php`.
- `includes/mensajes.php`: plantillas (crear, editar, activar/desactivar), `mensaje_para_cliente(int $cliente_id,
  string $texto): string` (rellena {nombre} con el primer nombre y los demás marcadores que se puedan),
  `campana_crear(string $nombre, string $texto, array $cliente_ids, string $filtro): int` (crea una fila en
  `mensajes` por cliente, estado pendiente, canal whatsapp_manual), `campana_destinatarios(string $filtro, array
  $opciones): array` con filtros: todos los activos, con proceso activo, con contacto atrasado, que compraron un
  producto (por `pedido_items`), sin pedidos en N días; `mensaje_marcar(int $id, string $estado)`.
- `mensajes.php`: plantillas (lista y formulario), campañas anteriores con avance (enviados / pendientes) y botón
  «Nueva campaña».
- `campana.php`: `?nuevo=1` elige filtro → muestra la lista con casillas (todas marcadas) → texto (plantilla o
  libre, con vista previa con el primer cliente) → crear. `?id=N`: modo envío: un mensaje a la vez con el texto ya
  personalizado, botón «Abrir WhatsApp» (`enlace_whatsapp()`, nueva pestaña) que lo marca enviado y pasa al
  siguiente, y «Omitir». Muestra el avance. Clientes sin teléfono válido se omiten solos.
- **Hecho cuando:** `tests/run.php` pasa con pruebas de rellenar marcadores por cliente, cada filtro de
  destinatarios, crear campaña con N mensajes y marcar enviado/omitido.

## Herramientas

- PHP 8.1+ sin framework, PDO. JS y CSS planos, sin compilación.
- Base de datos: SQLite en local y en pruebas; MySQL en producción (Hostinger). Migraciones en `sql/`.
- IA gratis: Gemini con Groq de respaldo, formato compatible con OpenAI `chat/completions` por cURL.
- Pruebas: `php tests/run.php` (revisa sintaxis y corre `tests/test_*.php`).

## Estructura

```
index.php, login.php, logout.php, instalar.php     base (líder)
clientes.php, cliente.php                           T1
caso.php, plan.php                                  T2
api/lia.php, assets/lia.js                          T3
usuarios.php                                        T4
includes/app.php, db.php, funciones.php,
         header.php, footer.php                     base (líder) — los trabajadores NO los tocan
includes/clientes.php | casos.php | ia.php          funciones de T1 | T2 | T3
assets/estilos.css, assets/app.js                   base (líder)
sql/NNN_*.sql                                       migraciones (solo el líder crea nuevas)
tests/run.php + tests/test_<modulo>.php
```

## Tareas

| # | Tarea | Agente | Estado |
|---|---|---|---|
| 0 | Base: arranque, BD y migraciones, sesión/CSRF/permisos, entrada, inicio, estilos, pruebas | Claude (líder) | Hecha |
| 1 | Clientes: lista con buscador, ficha, crear/editar, consentimiento, historial | OpenCode (DeepSeek, luego Nemotron); revisó Codex | Hecha |
| 2 | Casos, planes versionados, seguimientos, enlace público y WhatsApp | OpenCode (MiMo/Nemotron) a medias; lo terminó Codex | Hecha |
| 3 | Lía: módulo de IA con respaldo y asistente por texto/voz | OpenCode (DeepSeek, luego Big Pickle); Codex corrigió la privacidad | Hecha |

Pendientes vistos al probar (para la ronda 2): en `caso.php` los campos del ítem del plan no tienen el estilo
ni las etiquetas de los demás, el selector de productos no está agrupado por categoría y los campos del
formulario de nuevo caso no tienen etiqueta accesible. Los modelos gratis de OpenCode se agotaron a mitad de
tarea (comparten cuota): para la ronda 2 conviene repartir entre agentes distintos.

Kilo y Devin no arrancan desde las terminales de Orca (Git Bash no encuentra `kilo.cmd` ni `devin.cmd`), así que
sus tareas pasaron a OpenCode el 2026-11-20.
| 4 | Usuarios (admin): crear, desactivar, cambiar contraseña | OpenCode o Kilo | Siguiente ronda |
| 5 | Pulido visual y móvil revisado en navegador | Antigravity | Siguiente ronda |
| 6 | Guía para publicar en Hostinger | Líder | Siguiente ronda |

Revisión de cada tarea: Codex (hasta el 14 dic 2026; después Copilot), y luego el líder.

### T1 — Clientes (OpenCode)
- **Archivos:** `clientes.php`, `cliente.php`, `includes/clientes.php`, `tests/test_clientes.php`.
- `clientes.php`: lista de clientes activos con buscador por nombre o teléfono (`?q=`), orden por nombre,
  muestra teléfono, ciudad y número de casos activos.
- `cliente.php?id=N`: ficha con datos, lista de sus casos (enlaces a `caso.php?id=`), botón «Nuevo proceso»
  (`caso.php?nuevo=1&cliente=N`) e historial (tabla `eventos`, lo más nuevo primero).
- `cliente.php?nuevo=1` y `cliente.php?id=N&editar=1`: formulario. Obligatorios: nombre, teléfono y la casilla
  de consentimiento (al crear). Avisar si el teléfono ya existe y enlazar al cliente existente.
- Funciones en `includes/clientes.php`: `cliente_crear(array $datos): int`, `cliente_actualizar(int $id, array $datos)`,
  `cliente_obtener(int $id): ?array`, `clientes_buscar(string $q): array`. Validan y lanzan
  `InvalidArgumentException` con un mensaje para la persona. Cada cambio llama a `registrar_evento()`.
- **Hecho cuando:** `php tests/run.php` pasa con pruebas de crear, validar, buscar y duplicado de teléfono.

### T2 — Casos, planes y seguimientos (Kilo)
- **Archivos:** `caso.php`, `plan.php`, `includes/casos.php`, `tests/test_casos.php`.
- `caso.php?nuevo=1&cliente=N`: crear caso. `caso.php?id=N`: ver caso con plan actual, versiones anteriores,
  seguimientos (lista y progreso de peso: inicial → último, diferencia), editar estado y próximo contacto.
- Plan: formulario para nueva versión (ítems con producto del catálogo `productos` o texto libre, dosis,
  frecuencia, duración, notas; indicaciones generales; motivo del cambio). Crear versión = nueva fila en
  `planes` con `version` + 1 y token nuevo.
- Botón «Enviar por WhatsApp»: `enlace_whatsapp()` con un mensaje corto + `url_base() . '/plan.php?t=' . token`;
  marca `planes.enviado` (por POST antes de abrir el enlace) y registra evento.
- `plan.php?t=TOKEN`: página pública, sin menú ni login, solo la versión de ese token: primer nombre del
  cliente, ítems e indicaciones. Si el token no existe, 404 amable. Nunca muestra teléfono, correo ni condiciones.
- Seguimiento nuevo: fecha, tipo, peso, cintura, adherencia, efectos, nota, y opcionalmente nuevo próximo contacto.
- Funciones: `caso_crear`, `caso_obtener`, `caso_actualizar`, `plan_actual`, `planes_del_caso`,
  `plan_nueva_version(int $caso_id, array $items, string $indicaciones, string $motivo): int`,
  `plan_por_token`, `seguimiento_agregar`, `seguimientos_del_caso`, `progreso_peso(int $caso_id): array`.
- **Hecho cuando:** `php tests/run.php` pasa con pruebas de versiones del plan, token, seguimiento y progreso.

### T3 — Lía (Devin)
- **Archivos:** `includes/ia.php`, `api/lia.php`, `assets/lia.js`, `tests/test_ia.php`.
- `includes/ia.php`: `ia_consultar(string $funcion, string $sistema, string $texto, array $esquema): array`
  recorre `config('ia.orden')` y los modelos de cada proveedor hasta que uno responda; JSON con esquema
  (`response_format`), reintento sin esquema si el modelo no lo acepta; límite diario `ia.limite_diario`;
  registra cada intento en `ia_uso`. Si no hay claves, `ia_disponible()` es falso. Para pruebas:
  si `$GLOBALS['IA_FALSA']` es un callable, se usa en vez de llamar a internet.
- Privacidad (obligatorio): el servidor busca a los clientes mencionados en la base de datos local y a la IA
  solo le pasa los datos del caso con un alias («Cliente 1»), **sin nombre, teléfono, correo ni ciudad**.
  Al volver la respuesta, el servidor cambia el alias por el primer nombre.
- `api/lia.php` (POST JSON `{texto, historial}` con X-CSRF, requiere permiso `lia`): devuelve
  `{respuesta, accion, url}`. Acciones: `ninguna`, `abrir_cliente`, `abrir_caso`, `buscar` (→ `clientes.php?q=`),
  `ir` (inicio, clientes). El servidor arma la URL; la IA nunca la escribe.
- `assets/lia.js`: botón flotante «Lía» que abre un panel de chat dentro de `#lia-raiz`; micrófono con Web Speech
  API (es-CO) y respuesta en voz alta (speechSynthesis); si hay `url`, ofrece abrirla. Estilos dentro del propio
  JS (no tocar `estilos.css`).
- Instrucciones de Lía: corta, clara, en español de Colombia; nunca recomienda dosis ni cambia el plan; si no
  encuentra al cliente, lo dice.
- **Hecho cuando:** `php tests/run.php` pasa con pruebas (IA falsa) de: alias sin datos personales en lo enviado,
  cambio de alias por nombre en la respuesta, URL armada por el servidor, respaldo al segundo proveedor y límite diario.
