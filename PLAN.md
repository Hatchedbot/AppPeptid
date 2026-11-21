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

**Queda fuera (por ahora)**: pedidos, ventas, inventario de viales, pagos, envío automático por WhatsApp,
portal del cliente con usuario, recordatorios automáticos.

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
