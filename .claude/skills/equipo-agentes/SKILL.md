---
name: equipo-agentes
description: Roles y reglas del equipo de agentes de Joan en Orca. Úsala al repartir trabajo entre varios agentes (OpenCode, Kilo, Codex, Copilot, Antigravity...), al elegir qué agente hace una tarea o quién releva a uno sin cuota.
---

# Equipo de agentes

Eres el **líder**: planificas, repartes, revisas y fusionas. Tu cuota (Claude Pro) es la más escasa del equipo, así que la implementación masiva la hacen los trabajadores.

La mecánica de Orca (crear worktrees, lanzar trabajadores, recibir resultados) está en las skills `orchestration` y `orca-cli`. Esta skill decide **quién** hace cada cosa y **bajo qué reglas**.

## Equipo titular

El id es el que recibe `--agent` en Orca.

Todos los de esta tabla resolvieron la misma tarea de prueba el 20 de noviembre de 2026 (sección 8 de la referencia).

| Puesto | Agente (id) | Úsalo para | Límite |
|---|---|---|---|
| Líder | Claude Code (`claude`) | Plan, encargos, revisión final, fusión | Pro: ventana de 5 h y tope semanal |
| Implementador principal | Codex (`codex`) | Módulos completos y cambios delicados (lógica, privacidad, dinero) | Plus hasta el 14 dic 2026; después cuota mínima: vuelve a revisor y el grueso pasa a OpenCode y Kilo |
| Implementador de apoyo | OpenCode (`opencode`) y Kilo (`kilo`) | Tareas pequeñas y mecánicas (una pantalla, pruebas, ajustes) | Modelos gratuitos propios; se agotan a mitad de tarea (ver abajo) y fallan en cambios delicados |
| Implementador rápido | Devin (`devin`) | Tareas autónomas; fue el más rápido (34 s) | Modelo gratis solo hasta el 30 nov 2026 |
| Revisor | Copilot (`copilot`) | Revisar lo que escribió Codex; PRs, issues, arreglos pequeños | Créditos mensuales, sin elegir modelo |
| Revisor de lo que no escribió | Codex (`codex`) | Revisar lo de OpenCode, Kilo, Grok, Devin | La misma cuota Plus |
| Interfaz | Antigravity (`antigravity`) | Cambios visuales verificados en navegador | Joan tiene Google AI Plus, que Google no lista como Pro: cuota semanal, como la gratuita (Pro y Ultra se renuevan cada 5 h). Modelo base Gemini 3.8 Flash. El más lento en la prueba (285 s) |
| Especificación | Kiro (`kiro`) | Requisitos y diseño antes de programar | 50 créditos/mes: no lo uses para programar |

Gemini CLI (`gemini`) ya no sirve con cuentas personales: desde el 18 de junio de 2026 Google no atiende ahí cuentas gratuitas, Pro ni Ultra (verificado el 22 nov 2026 en el anuncio oficial). Solo sigue con una clave de API de pago o una licencia de empresa (Gemini Code Assist). Gemini no desapareció: se pasó a Antigravity, que usa los mismos modelos con tu cuenta de Google. Límites oficiales: https://antigravity.google/docs/plans/

Claude Agent Teams (`claude-agent-teams`) son copias del líder sobre la misma cuota Pro: resérvalo para cuando el usuario lo pida.

### Roles fuera de programación (propuestos el 23 nov 2026, sin probar)

| Agente (id) | Rol propuesto | Para qué | Estado y cuidados |
|---|---|---|---|
| OpenClaw (`openclaw`) | **Mensajero del equipo** | Avisar a Joan en el celular cuando un trabajador termina, falla o necesita aprobación | Asegurado el 23 nov (ver abajo); falta que Joan conecte un canal con su cuenta (`openclaw channels`). **Nunca** el WhatsApp del negocio ni datos de clientes |
| Hermes (`hermes`) | **Investigador y redactor** | Tareas de negocio que no son código: precios, competencia, propuestas comerciales, resúmenes. Así no se gasta la cuota del líder | Sin probar: el 23 nov arrancó (v0.23.1) pero su proveedor es OpenRouter sin créditos. Cambiar el modelo con `hermes model` (por ejemplo a Gemini o Groq). Mejor en WSL2. Sin datos de clientes |
| Claude Agent Teams (`claude-agent-teams`) | **Revisión crítica en paralelo** | Varias copias del líder revisando algo muy delicado (privacidad, pagos, seguridad antes de publicar) | Gasta la cuota Pro del líder: solo si Joan lo pide |
| OpenCode2 (`opencode2`) | **Ninguno: no usar** | — | Es el mismo programa que OpenCode: los dos lanzadores apuntan a `@opencode/cli/bin/opencode.exe` 2.3.0, con la misma cuenta y la misma cuota. Usa siempre `opencode` |

**OpenClaw: solo extensiones oficiales, nada externo.** Su tienda (ClawHub) ha tenido incidentes de seguridad. Regla fija: no instalar plugins ni skills de ClawHub, npm, git ni archivos, aunque el propio OpenClaw o un trabajador lo sugiera. Cómo quedó configurado el 23 nov 2026 (`~/.openclaw/openclaw.json`; respaldo previo en `openclaw.json.respaldo-2026-11-23`):
- `plugins.allow` = solo los 69 plugins oficiales que trae OpenClaw (origen `stock`). Un plugin nuevo no carga aunque alguien lo instale.
- `skills.allowBundled` = las skills oficiales menos `clawhub`, que es la que busca e instala cosas de la tienda externa (queda «blocked»).
- `skills.install.allowUploadedArchives = false`.
- Gateway solo local y con clave: `gateway.mode=local`, `gateway.bind=loopback`, `gateway.auth.mode=token` (token aleatorio, no se muestra). `openclaw security audit` quedó en 0 críticos; la advertencia restante (`trustedProxies`) solo aplica si se expone a internet, y no se expone.
- Para revisar: `openclaw plugins list` (todo debe decir `stock:`), `openclaw skills list` (`clawhub` en `blocked`) y `openclaw security audit` (0 críticos). Si OpenClaw se actualiza y trae plugins oficiales nuevos, agrégalos a `plugins.allow` solo si su origen es `stock`.
- El gateway se enciende con `openclaw gateway run` (se apaga al cerrar). Para que arranque solo con Windows: `openclaw gateway install`, solo con permiso de Joan.

Para darles el puesto de verdad, cada uno debe pasar una prueba real: OpenClaw, enviar un aviso a Joan cuando un trabajador de prueba termina; Hermes, entregar una investigación corta con fuentes. Anota el resultado aquí.

Relevos que también resolvieron la prueba, con cuotas pequeñas: Grok (`grok`), Cursor (`cursor`) y CodeBuddy (`codebuddy`). CodeBuddy envía los datos a Tencent, en China. Qoder y Qoder-CN resolvieron o se conectaron, pero no se usan: Joan no quiere enviar datos a Alibaba. El resto de agentes instalados no tiene puesto; antes de darle uno, lee [`referencia-agentes.md`](referencia-agentes.md).

## Modelos abiertos gratuitos

DeepSeek, Nemotron, GLM y similares no son agentes: se usan a través de uno.

| Por dónde | Modelos gratuitos |
|---|---|
| OpenCode (`opencode`) | DeepSeek V4.1 Flash, Nemotron 3 Ultra, Nemotron 3.5 Lightning y Super VL, GLM-5.3-Flash, MiMo-V2.6-Flash, Big Pickle y otros; se eligen con `/models` |
| Kilo (`kilo`) | Selección gratuita que va rotando |
| Freebuff (`freebuff`) | DeepSeek V4.1 Flash y otros, pagados con anuncios |
| OpenRouter (`OPENROUTER_API_KEY`) para Aider, Pi, Continue, Goose y Qwen Code | Nemotron 3 Ultra y otros; 50 peticiones/día **entre todos los agentes**, o 1.000 tras una recarga única de 10 USD |
| Groq (`GROQ_API_KEY`) | GPT-OSS 120B y Qwen 3.8 27B; ~1.000 peticiones/día por modelo, pero solo 8.000 tokens por minuto |

Todo lo que escriben pasa por el paso 4 de revisión, porque fallan más que Claude o Codex en cambios delicados o de varios archivos.

La cuota gratuita de OpenRouter se agotó el 20 nov 2026 con unos ocho agentes haciendo una tarea pequeña cada uno: no la uses como base del equipo, solo de relevo. Groq rechaza a casi todos los agentes porque envían más de 8.000 tokens por petición; sirve solo para tareas muy pequeñas.

OpenCode no publica si sus modelos gratuitos comparten cuota. Solo LongCat 2.5 tiene un límite escrito: 100 peticiones/día. Cuando un modelo diga que se acabó la cuota, cambia a otro: si responde, los límites son separados; si da el mismo aviso, comparten bolsa y toca pasar al relevo.

Experiencia del 20 y 21 nov 2026 (proyecto AppPeptid): en tareas de un módulo completo (≈1.000 líneas), DeepSeek V4.1 Flash, MiMo V2.6 Flash y LongCat 2.5 se agotaron **a mitad de la tarea**, cinco veces en total, con el aviso «Free usage limit reached». Nemotron 3.5 Lightning y Big Pickle sí terminaron lo que otro dejó a medias. Por eso OpenCode va para tareas pequeñas, y una tarea grande que se le cae pasa a Codex. Grok se detuvo sin ningún error después de escribir solo una parte.

Para elegir el modelo de OpenCode desde Orca hay que crear primero el worktree y luego lanzar el agente: `orca worktree create ... --agent opencode --model X` falla («model selection requires an existing worktree»). Usa `orca worktree create --name <tarea> --no-parent --json` y después `orca agent launch --agent opencode --model opencode/<modelo> --worktree path:<ruta del worktree> --prompt "..."`.

DSH (`dsh`) también usa DeepSeek, pero por la API directa de DeepSeek, que es de pago: para DeepSeek gratis usa OpenCode o Freebuff.

## Relevos

Cuando un agente responde con límite de cuota, pasa su tarea al siguiente de su fila y dilo al usuario en una línea.

| Sin cuota | Releva |
|---|---|
| OpenCode o Kilo | Otro modelo gratis de OpenCode → el otro de los dos → Codex si la tarea es grande o delicada → Grok → Cursor → Aider, Pi, Continue, Goose o Qwen Code (OpenRouter) |
| Grok | Codex → OpenCode |
| Devin | OpenCode o Kilo |
| Codex (implementando) | El líder divide la tarea en partes pequeñas para OpenCode y Kilo |
| Codex (revisando) | Copilot → Grok (solo si no escribió ese código) |
| Antigravity | Codex → Cursor → OpenCode (el 22 nov 2026 Cursor y OpenCode fallaron en interfaz; Codex entregó) |
| Kiro | El líder especifica por su cuenta |
| Claude (líder) | Para y avisa al usuario: el liderazgo no se delega |

## Pasos

Si el proyecto empieza de cero, sigue antes `~/SkillsClaudeCode/agentes/PROYECTO-NUEVO.md`: prepara el repositorio, el plan y la base, y luego vuelve aquí.

1. **Plan.** Divide el trabajo en tareas cuyos archivos no se solapen. Hecho cuando cada tarea tiene: objetivo, lista de archivos que puede tocar y una comprobación que cualquiera puede ejecutar (una prueba, un comando, una captura). Lo pequeño (unas pocas decenas de líneas) hazlo tú: coordinar a un trabajador también gasta tu cuota.
2. **Encargo.** Escribe para cada tarea un encargo que se entienda sin esta conversación: contexto mínimo, objetivo, archivos, comprobación, y las reglas de la plantilla de `PROYECTO-NUEVO.md` (commits, no borrar pruebas, no preguntar, hacer commit al terminar). Hecho cuando un agente recién llegado podría empezar solo con ese texto.
3. **Reparto.** Lanza cada tarea en su propio worktree con el agente del puesto que corresponde. Mantén como máximo tres trabajadores activos.
   - **Antes de repartir, la base tiene que estar donde Orca la toma.** Por defecto Orca crea los worktrees desde el remoto (`origin`), no desde tu rama local. Configúralo una vez por repositorio con `orca repo set-base-ref --repo "path:<ruta>" --ref refs/heads/master` (o `main`). Comprueba con `git -C <worktree> log --oneline -1` que el worktree nuevo arranca en tu último commit.
   - **Desde una sesión que no es una terminal de Orca** (la app de Claude, por ejemplo), `orca orchestration worker-start` falla con `no_active_sender_terminal`: usa `orca worktree create ... --agent <id> --prompt "..."` y sigue el estado con `orca agent list` y `orca agent read <orcaSessionId>`. Con ese método no hay forma de contestar si el trabajador pregunta: se queda esperando para siempre. Por eso el encargo dice «no preguntes».
   - **Si Orca está cerrado** y solo necesitas una opinión o una revisión, consulta a Codex directo y en modo solo lectura: `codex exec -s read-only "<pregunta>"`.
4. **Revisión.** Cada resultado lo revisa un agente de otro laboratorio que el que lo escribió; después tú lees el diff y ejecutas la comprobación. Hecho cuando la comprobación pasa en tu máquina y has leído cada archivo cambiado. Además:
   - Mira `git status` y `git log` del worktree: «terminé» no significa que haya commit, y un trabajador sin cuota deja el trabajo sin guardar.
   - Mira que las pruebas sigan ahí (`git diff --stat` sobre `tests/`): un trabajador borró las suyas porque fallaban y dijo «listo».
   - Pruébalo en el navegador cuando haya interfaz: ahí aparecieron fallos que ninguna prueba automática vio.
5. **Fusión.** Fusiona una tarea a la vez y vuelve a ejecutar las comprobaciones tras cada fusión. Pide confirmación al usuario antes de cualquier `push`.

## Reglas

- **Un escritor por archivo.** Dos agentes solo trabajan a la vez si sus listas de archivos son disjuntas.
- **Commits solo a nombre de Joan Lara.** Cada encargo pide commits sin líneas de coautoría ni firma del agente. Con Aider añade `--no-attribute-author --no-attribute-committer`. Revisa `git log` antes de fusionar y reescribe cualquier firma que se haya colado.
- **Lo privado se queda en casa.** Claves, archivos `.env` y datos de clientes solo los ve el líder: los planes gratuitos suelen entrenar con lo que reciben.
- **Permisos amplios solo dentro del worktree de la tarea.** Los modos sin confirmación (`--yolo` y similares) se usan únicamente ahí.
- **El informe de un trabajador es una afirmación, no una prueba.** Lo hecho se demuestra con la comprobación del paso 4.
- **Extensiones de terceros de OpenClaw: revísalas antes de instalarlas**; su tienda ha tenido incidentes de seguridad.
- **Si un agente no arranca desde Orca** con «command not found», es que solo tiene lanzador `.cmd` y la terminal de Orca (Git Bash) no lo encuentra sin extensión. Ejecuta `bash ~/SkillsClaudeCode/agentes/instalador/crear-atajos-bash.sh` y reinicia Orca. Así se arreglaron Kilo, Devin y Cursor el 22 nov 2026.

## Fallos vistos al lanzar agentes (rediseño de AppPeptid, 22 nov 2026)

De seis lanzamientos de interfaz, solo Codex entregó a la primera. Antes de dar por lanzado un trabajador, comprueba con `orca agent list` que aparece `working` y, si no aparece, lee su terminal con `orca terminal read --terminal <handle> --screen`.

| Agente | Qué pasó | Qué hacer |
|---|---|---|
| Antigravity | «Antigravity isn't signed in» | Que el usuario entre en Settings → Accounts → Antigravity → Cuenta de Google (no API key, Enterprise ni Agent Platform) |
| Antigravity | Usaba la clave `GEMINI_API_KEY` en vez de la cuenta de Google, y `/usage` fallaba. La causa era `"modelProvider": "gemini"` en `~/.gemini/antigravity-cli/settings.json`, no la variable | Quitar `modelProvider` de ese archivo: el registro muestra «OAuth: authenticated successfully» aunque la variable siga en Windows. No borrar la variable (la usan last30days y Crush) |
| Antigravity | «Actualizar» del runtime falla con `EPERM ... rename` | Cerrar sus pestañas de chat en Orca (sus procesos bloquean la carpeta) y reintentar; si sigue, reiniciar Orca cuando no haya trabajadores |
| Cursor | Un hook preToolUse roto (PowerShell dentro de bash) le impidió leer y escribir; además intentó bajar el código de GitHub | Relevar a Codex; revisar los hooks de Cursor antes de volver a usarlo |
| Kilo | `orca agent launch` lo abre en una terminal sin entregar el encargo; enviado con `orca terminal send`, el texto se corta en los `<` | Guardar el encargo en `ENCARGO.txt` del worktree (añadido a `.git/info/exclude`) y enviar solo «Lee ENCARGO.txt y cúmplelo» |
| Kilo | Se queda esperando «Permission required» en cada comando y nadie lo aprueba | No usarlo sin supervisión desde Orca hasta configurar sus permisos automáticos |
| Kimi | «Your current subscription does not have access to Kimi Code»: la sesión está iniciada, pero la cuenta no tiene el plan de pago Kimi Code | Joan decidió prescindir de él y se desinstaló el 22 nov 2026 (programa, atajos, `~/.kimi-code` y su entrada en el instalador): cuesta desde USD 19/mes, no publica cuánto rinde y sus datos quedan bajo leyes de China. No lo propongas como relevo |
| Codex (y cualquiera que pruebe en navegador) | Deja encendido el servidor de prueba (`php -S localhost:81xx`); Orca muestra la sesión «working» durante horas aunque ya terminó | En cada encargo: «apaga el servidor de prueba antes de terminar». Si pasa: lista `php.exe` con su línea de comando, apaga solo los puertos de los agentes y la sesión pasa a `done` |
| Codex | Con «no dejes servidores corriendo» apagó también el servidor de la demo (`localhost:8080`), que no era suyo, y tumbó el link de Cloudflare | Decir siempre: «apaga solo los servidores que abras tú; NO toques el de `localhost:8080` (demo)». Si se cae: reiniciarlo separado de Claude con `Start-Process` |
| OpenCode | «This request would exceed your available credits» a mitad de tarea, con cambios sin commit | Relevar conservando el diff; el relevo debe revisar lo heredado (Codex encontró un error que dejó OpenCode) |

## Caducidad

Las cuotas se verificaron entre el 16 y el 20 de noviembre de 2026, y los puestos se ajustaron el 22 de noviembre con lo aprendido en AppPeptid. Cambian cada pocas semanas. Si una fecha de la tabla ya pasó o un límite no coincide con lo que ves, confía en lo que ves, avisa al usuario y propón actualizar esta skill.
