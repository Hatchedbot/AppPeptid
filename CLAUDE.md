# Contexto del proyecto

**BRDG Seguimiento**: app web interna para BRDG Peptides Colombia (venta de péptidos, Bogotá). Sirve para hacer
seguimiento de clientes, sus procesos y planes, enviar el plan por WhatsApp y consultar todo con la asistente
de IA **Lía**. Cliente real de Joan Lara. El plan y el estado de las tareas están en `PLAN.md`.

Joan no es programador: explícale en español sencillo, paso a paso.

## Cómo se ejecuta y se prueba

- PHP de XAMPP: `C:\xampp\php\php.exe` (en Git Bash: `/c/xampp/php/php.exe`). No hay PHP en el PATH.
- Local: `C:\xampp\php\php.exe -S localhost:8000` en la raíz → http://localhost:8000 (la primera vez pide crear
  el administrador). Usa SQLite en `datos/app.sqlite` (no se sube a git).
- Pruebas: `C:\xampp\php\php.exe tests/run.php` (sintaxis de todos los .php + `tests/test_*.php`, SQLite en
  memoria). Una sola: `tests/run.php clientes`.
- Producción: Hostinger con MySQL, datos en `config.local.php` (copiar de `config.local.example.php`).

## Reglas del código

- PHP 8.1+ sin framework ni Composer. PDO con consultas preparadas, siempre. JS y CSS planos.
- Cada página empieza con `require __DIR__ . '/includes/app.php';` y luego `requerir_login()` o
  `requerir_permiso('...')`. Las que muestran HTML incluyen `includes/header.php` (con `$titulo` y `$activo`)
  y `includes/footer.php`.
- Todo texto que se imprime pasa por `e()`. Todo formulario POST lleva `<?= campo_csrf() ?>`; los `fetch` usan
  `enviarJSON()` de `assets/app.js` (cabecera X-CSRF). La verificación es automática en `includes/app.php`.
- Mensajes tras guardar: `avisar('ok'|'error'|'info', '...')` y `redirigir(...)` (patrón POST → redirección).
- Números escritos por personas: `numero_entrada()`. Mostrar: `numero_corto()`, `dinero()`, `fecha_corta()`.
- Fechas: texto `AAAA-MM-DD HH:MM:SS` con `ahora()` / `hoy()`. Zona America/Bogota.
- Base de datos: migraciones `sql/NNN_*.sql` en MySQL sencillo que se aplican solas (ver reglas en
  `includes/db.php`). Nunca editar una migración existente; las nuevas las crea el líder.
- Lo que hace una persona sobre un cliente se anota con `registrar_evento($cliente_id, $tipo, $texto, $caso_id)`.
- Interfaz en español de Colombia, tuteando, sin emojis. Solo modo claro. Colores solo con las variables de
  `assets/estilos.css`. Pensada para celular primero (botones de al menos 44 px).
- Funciones de cada módulo en su archivo: `includes/clientes.php`, `includes/casos.php`, `includes/ia.php`.
  Archivos comunes (`includes/app.php`, `db.php`, `funciones.php`, `header.php`, `footer.php`, `assets/estilos.css`,
  `assets/app.js`) solo los cambia el líder.

## Datos sensibles

- Peso, tratamiento y condiciones de salud son **datos sensibles** (Ley 1581 de 2012). Al crear un cliente se
  exige la casilla de consentimiento (`clientes.consentimiento_fecha`).
- A la IA gratis **nunca** se envían nombre, teléfono, correo ni ciudad: el servidor usa alias («Cliente 1») y
  pone el primer nombre al recibir la respuesta.
- La página pública del plan (`plan.php?t=`) solo muestra primer nombre y el plan.
- Lía solo consulta y navega: no cambia datos ni recomienda dosis. El plan lo escribe una persona.
- Nunca escribir claves ni contraseñas en el código ni en el chat: van en `config.local.php`.

## Git

- Commits solo a nombre de Joan Lara: sin `Co-Authored-By` ni firmas de agentes. Sin `push` sin permiso de Joan.
- Remoto: https://github.com/Hatchedbot/AppPeptid
- Trabajo repartido con Orca según la skill `equipo-agentes`.
