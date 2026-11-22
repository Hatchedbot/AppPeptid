# Referencia: los 44 agentes instalados en Orca

Fecha de la investigación: 16 de noviembre de 2026.

**Cómo leerlo.** Cada dato lleva una marca de confianza:

- **[oficial]** lo leí en la documentación o página de precios del propio producto.
- **[reseña]** viene de reseñas, foros o comparativas de terceros.
- **[poco dato]** casi no hay información independiente; es lo que dice el propio proyecto.

Los precios y cuotas de estas herramientas cambian cada pocas semanas. No comprobé en cuáles tienes sesión iniciada ni probé ninguno en tu equipo.

---

## 1. Datos clave

1. **Qwen Code ya no es gratis.** Alibaba eliminó el acceso gratuito por cuenta el 15 de abril de 2026 [oficial]. Ahora necesita clave de pago.
2. **Gemini CLI ya no funciona gratis.** Desde el 18 de junio de 2026 Google no atiende en Gemini CLI cuentas gratuitas ni Google AI Pro o Ultra; pide pasarse a Antigravity [oficial]. La cifra de 1.000 peticiones al día de la página de cuotas estaba desactualizada. Confirmado en la prueba del 20 nov 2026 (`IneligibleTierError`).
3. **Copilot para estudiantes existe pero es limitado.** Es gratis, incluye el CLI, pero solo con selección automática de modelo y una asignación mensual de créditos [oficial]. Sirve de ayudante, no de sustituto completo de Codex.
4. **Devin CLI tiene su modelo SWE-2 gratis hasta el 30 de noviembre de 2026** [oficial]. Vale la pena usarlo estas dos semanas.
5. **OpenCode2 es un duplicado exacto**: en tu equipo `opencode` y `opencode2` son el mismo programa, versión 2.3.0.

---

## 2. Agentes con cerebro propio (traen su modelo y su cuota)

### Los que pagas o tienen cuota grande

| Agente | Punto fuerte | Punto débil | Coste real para ti |
|---|---|---|---|
| **Claude Code** | El mejor razonando sobre arquitectura, refactors de varios archivos y decisiones con compromisos. Ecosistema más maduro (skills, subagentes, hooks). | La cuota Pro es pequeña: ventana de 5 horas más tope semanal, y Anthropic no publica las cifras [reseña]. Se agota rápido si programa en masa. | Pro, ya pagado. |
| **Codex** | Muy bueno depurando y revisando; segunda opinión de otro laboratorio. Comparte cuota entre CLI, web y editor. | En plan gratis la cuota es mínima: usuarios reportan agotarla en media hora [reseña]. Usa tus conversaciones para entrenar salvo que lo desactives [oficial]. | Plus hasta el 14 de diciembre; después casi inservible. |
| **Gemini CLI** | Código abierto; contexto enorme. | **Retirado para cuentas gratuitas y Pro desde el 18 jun 2026** [oficial]. Solo funciona con una clave de API (la gratuita da 20 peticiones/día). | No usable sin pagar. |
| **Antigravity** | Trabajo con varios agentes y verificación en navegador; bueno para interfaz. | Google no publica las cifras. Gratis se renueva solo cada semana [oficial]. Consume cuota mucho más rápido que Gemini CLI [reseña]. | Gratis con tope semanal. |

### Gratis con límite pequeño

| Agente | Punto fuerte | Punto débil | Cuota gratuita |
|---|---|---|---|
| **Copilot** | Integración nativa con GitHub: PRs, issues, revisión. CLI incluido en todos los planes [oficial]. | En gratis y estudiante no eliges modelo; estudiante excluye agentes de terceros [oficial]. | Asignación mensual de créditos; estudiantes gratis. |
| **Kiro** | Desarrollo guiado por especificación: primero requisitos y diseño, luego código. Incluye Claude Sonnet 4.5 con límites [oficial]. | 50 créditos al mes se van en pocas sesiones; los modelos caros gastan hasta 11 veces más rápido [reseña]. | 50 créditos/mes, sin tarjeta [reseña de AWS]. |
| **Devin** | Agente muy autónomo; SWE-2 gratis en el CLI hasta el 30 de noviembre [oficial]. | Fuera de esa promoción, el plan gratis es una cuota ligera con pocos modelos [oficial]. | Ligera; promoción temporal. |
| **Cursor** | Agente rápido y pulido; mismo motor que el editor. | La cuota gratis se agota en minutos con el agente en modo automático, según su propio equipo en el foro [oficial]. | Muy pequeña. |
| **Freebuff** | Gratis sin clave ni suscripción, financiado con anuncios. Varias sesiones de una hora al día [reseña]. | Modelos de gama media. Las condiciones ya cambiaron una vez y dependen de tu región y de la capacidad del momento [oficial]. | Sesiones diarias con anuncios. |
| **Mistral Vibe** | Código abierto; Devstral 2 es sólido (72,2 % en SWE-bench Verified según Mistral) con contexto de 256.000 tokens [reseña]. | Gratis solo da sesiones de código limitadas; el uso real pide Pro, 14,99 € al mes [reseña]. | Limitada. |
| **Grok** | Agente de terminal de xAI; rápido. | Acceso gratis limitado y sin el modelo rápido; xAI cambia los planes muy a menudo [reseña]. | Limitada; Plus 8 USD/mes. |
| **Zcode** | Modelos GLM de Z.ai, con cuota diaria gratuita según reseñas en vídeo [reseña]. | GLM es decente pero por debajo de Claude y OpenAI en razonamiento [reseña]. | Diaria, sin cifra oficial que yo haya visto. |
| **Qoder** | Editor y CLI de Alibaba con wiki automática del repo. | Gratis solo modelos básicos y pocos mensajes; los créditos caducan y la indexación consume mucha memoria [reseña]. | Prueba de 14 días con 300 créditos [oficial]. |
| **CodeBuddy** | Agente de Tencent, interfaz tipo Claude Code. | Poca información independiente fuera de China. | 100 créditos/mes [oficial]. |
| **Muse** | Agente de Meta sobre su modelo Muse Spark, lanzado en agosto de 2026. Solo terminal. | Demasiado nuevo: no encontré reseñas independientes ni precios claros [poco dato]. | Sin confirmar. |
| **MiMo Code** | Agente de Xiaomi pensado para tareas largas. | Solo hay información del propio fabricante [poco dato]. | Sin confirmar. |

### De pago: sin cuota gratis útil

| Agente | Punto fuerte | Punto débil | Precio de entrada |
|---|---|---|---|
| **Auggie** | El mejor entendiendo repos muy grandes gracias a su motor de contexto; lideraba SWE-Bench Pro a inicios de 2026 [reseña]. No entrena con tu código en planes de pago [oficial]. | Sin plan gratis: prueba de 30.000 créditos que pide tarjeta. Gasto de créditos impredecible [reseña]. | 20 USD/mes. |
| **Droid** | Un solo agente con muchos modelos; modo especificación; al agotar la cuota puedes seguir con modelos abiertos [reseña]. | Las fuentes se contradicen sobre si hay plan gratis con clave propia. Factory cambió los planes tres veces en un año [reseña]. | 20 USD/mes. |
| **Kimi** | Su modelo K3 está entre los mejores abiertos en programación [reseña]. | El CLI es gratis pero el uso del modelo no; es lento y verboso [reseña]. | Kimi Code desde USD 19/mes (Plus), USD 39 (Pro), 99 (Max), 199 (Ultra); con pago anual, 15/31/79/159. Sin cifras de uso publicadas [oficial, 22 nov 2026]. |
| **Rovo** | Integración con Jira y Confluence. | El plan gratis de Jira no incluye Rovo; usuarios reportan que los créditos duran muy poco [reseña]. | Depende del plan de Atlassian. |
| **Codebuff** | Versión de pago de Freebuff, con mejores modelos. | Pagas por lo mismo que Freebuff da con anuncios. | De pago. |
| **Qwen Code** | Buen agente, derivado de Gemini CLI. | Perdió su plan gratuito en abril de 2026 [oficial]. | Clave de pago, o una clave de OpenRouter como servicio compatible con OpenAI. |
| **Command Code** | Aprende tu estilo de programar; pensado para modelos abiertos [oficial]. | Poca reseña independiente. | Plan desde 1 USD/mes; dice tener nivel gratis. |

---

## 3. Agentes sin cerebro propio (necesitan una clave o un modelo local)

Todos son gratis como programa. Lo que cuesta es el modelo que les conectes. Su calidad depende casi por completo de ese modelo.

| Agente | Punto fuerte | Punto débil |
|---|---|---|
| **OpenCode** | El más popular; más de 75 proveedores; trae un modelo gratuito de respaldo; suscripción opcional de 10 USD/mes [reseña]. | Con modelos gratis o baratos falla en refactors complejos; algunos usuarios reportan mal resultado con Gemini [reseña]. |
| **Kilo** | Arranca con modelos gratis sin tarjeta; más de 500 modelos a precio de proveedor; modos arquitecto, código y depuración [reseña]. | Los modelos gratis rotan y son en parte experimentales; más configuración [reseña]. |
| **Cline** | Aprobación humana paso a paso, modos planificar y actuar; promociones rotativas de modelos gratis [reseña]. | Los bucles de aprobación frenan; el coste por uso es difícil de prever [reseña]. |
| **Aider** | El más maduro trabajando con git: cada cambio es un commit. | Menos autónomo. Por defecto marca los commits con "(aider)": hay que desactivarlo para que la autoría sea solo tuya. |
| **Goose** | Muy extensible mediante conectores; bueno para automatizar más allá del código. | Requiere configuración; depende mucho del modelo. |
| **Crush** | Interfaz de terminal muy cuidada. | Menos funciones de agente que los líderes. |
| **Continue** | Bueno para ejecuciones sin interfaz y automatización. | Su fuerte es el editor, no el terminal. |
| **Pi** | Mínimo y muy personalizable. | Hay que montarlo casi todo a mano. |
| **OMP** | Variante de Pi con más funciones incluidas. | Proyecto pequeño; no pude verificar detalles en esta investigación. |
| **OpenClaude** | Interfaz de Claude Code con cualquier proveedor o modelo local [oficial]. | Con un modelo flojo, la experiencia es muy inferior al original. |
| **Amp** | Agente de primer nivel; ahora es gratis si traes tus propias claves, y tiene un nivel gratuito de aficionado [oficial]. | Usuarios reportan que el nivel gratis se redujo [reseña]. |
| **Autohand** | Código abierto, más de 15 proveedores [oficial]. | Poca reseña independiente [poco dato]. |
| **Jcode** | Escrito en Rust, muy rápido, pensado para muchos agentes a la vez [oficial]. | Proyecto de una sola persona [poco dato]. |
| **Ante** | Agente autocontenido que se organiza solo [oficial]. | Casi sin información independiente [poco dato]. |
| **Prime Agent** | Proyecto de investigación con modo autónomo que se detiene cuando pasa una prueba que tú defines [oficial]. | Es una herramienta de laboratorio, no un producto pulido [poco dato]. |
| **DSH** | Interfaz para los modelos de DeepSeek, que son muy baratos. | Barato no es gratis; instalación enrevesada [poco dato]. |

**De dónde sacar un modelo gratis para estos:** OpenRouter da 50 peticiones al día en modelos gratuitos, que suben a 1.000 al día tras una única recarga de 10 USD [oficial]. Una clave gratuita de Gemini da, según la documentación, 250 peticiones al día con el modelo Flash [oficial]; en la prueba real con tu clave el límite fue de **20 peticiones al día** para Gemini 3.8 Flash, y un solo agente las agotó en una tarea pequeña (ver sección 6).

---

## 4. Los que no son programadores, y los duplicados

- **Hermes**: asistente general con memoria persistente y habilidades que mejora solo. Soporta Linux, macOS y WSL2 [reseña]; en Windows nativo puede dar problemas.
- **OpenClaw**: asistente general conectado a apps de mensajería. Ha tenido incidentes de seguridad en su tienda de extensiones [reseña]: no le instales extensiones de terceros sin revisarlas.
- **Claude Agent Teams**: varias copias de Claude sobre tu misma cuota Pro.
- **Qoder-CN**: versión china de Qoder. **OpenCode2**: idéntico a OpenCode.

---

## 5. Equipo titular

Está en [`SKILL.md`](SKILL.md), que es la única fuente de los puestos y relevos.

---

## 6. Prueba real de los ocho agentes poco conocidos (16 nov 2026)

Tarea: corregir tres errores en un archivo Python de 20 líneas hasta que pasara una prueba automática, sin modificar la prueba. Ninguno tenía sesión iniciada; se usó la clave gratuita de Gemini del entorno con Gemini 3.8 Flash.

| Agente | Resultado | Detalle |
|---|---|---|
| **Prime Agent** | Tarea resuelta | Corrigió los tres errores y no tocó la prueba. Luego chocó con el límite de Gemini y no cerró solo. Instala paquetes propios en el primer arranque. |
| **MiMo Code** | Sin resultado | Necesita la clave con otro nombre de variable (`GOOGLE_GENERATIVE_AI_API_KEY`). Cuando por fin conectó, la cuota diaria ya estaba agotada. |
| **Jcode** | Falla con clave de Gemini | Gemini rechaza la petición (error 400) por cómo Jcode describe sus herramientas. Es un fallo del agente. No se probó con inicio de sesión de Gemini ni con Claude u OpenAI. |
| **Ante** | No arranca | No acepta la clave de Gemini por variable de entorno; pide iniciar sesión o claves de otros proveedores. |
| **Autohand** | No arranca | Exige cuenta propia (`autohand login`) incluso para usar tu propia clave. |
| **Muse** | No arranca | Exige cuenta de Meta (`muse login`). |
| **Command Code** | No arranca | Sin sesión, ni la ayuda respondió en 20 segundos. |
| **DSH** | No arranca | La ayuda del modo sin interfaz no respondió; necesita clave de DeepSeek. |

Conclusión: la clave gratuita de Gemini no alcanza para alimentar agentes (20 peticiones al día). Para evaluar de verdad a estos ocho hace falta iniciar sesión en cada uno o una fuente de modelo con más cuota.

---

## 7. Origen de cada agente (revisado el 20 nov 2026)

Se comprobó de dónde instaló cada agente el kit de `agentes/instalador` y quién publica cada paquete (npm, PyPI o el dominio de descarga). Esto dice **quién lo publica**, no que el código sea seguro: no se auditó el código de ninguno.

**De terceros: no los publica la empresa del producto**

| Agente | Qué se instaló | Riesgo |
|---|---|---|
| ZCode (`zcode`) | `zcode-app-cli`, paquete comunitario de una persona (kingsword09). La app oficial de Z.ai también está en `D:\Agentes-IA\ZCode`, pero Orca usa el comando comunitario | Alto |
| DSH (`dsh-tui`) | Interfaz de un tercero (`ccch1mneyyy/dsh-TUI`) sobre el motor oficial de DeepSeek (`@deepseek-ai/dsh`) | Alto |
| OpenClaude (`openclaude`) | Copia modificada de Claude Code, publicada por una sola cuenta (`Twigpine`) | Alto |
| OMP (`omp`) | Extensión comunitaria de Pi hecha por una persona (`can1357/oh-my-pi`) | Medio |

**Oficiales pero de una sola persona**

- Jcode (`1jehuang/jcode`): es el proyecto original, pero sin empresa detrás.

**Oficiales: los publica la empresa o el proyecto dueño**

Claude Code, Claude Agent Teams (Orca), Codex (OpenAI), Gemini CLI (Google), Copilot (GitHub), Grok (xAI), OpenCode y OpenCode2 (Anomaly), Kilo, Crush (Charm), Cline, Continue, Auggie (Augment), Command Code, Codebuff y Freebuff (CodebuffAI), Kimi (Moonshot; desinstalado el 22 nov 2026), Qwen Code (Alibaba), CodeBuddy (Tencent), Qoder y Qoder-CN (Alibaba), Pi (Earendil), Goose, Aider, Mistral Vibe, Cursor, Kiro, Devin, Amp, Droid (Factory), Rovo (Atlassian), Autohand, Ante (Antigma Labs, dentro de la distribución WSL `Orca-Agents`), MiMo (Xiaomi), Prime Agent (Prime Intellect), Hermes (Nous Research), OpenClaw y Muse (Meta).

Antigravity no se instaló con el kit; se instaló aparte desde Google y su origen no se volvió a comprobar.

**Datos bajo leyes de China:** Qoder, Qoder-CN y Qwen Code (Alibaba), CodeBuddy (Tencent), Kimi (Moonshot), MiMo (Xiaomi), ZCode (Z.ai) y los modelos DeepSeek. Qoder-CN es el servicio para el mercado chino.

**Descartado:** Rovo, por pedir cuenta de Atlassian y token propio en vez de entrar con Google.

**Desinstalados el 20 nov 2026:** Rovo, Command Code, Jcode, ZCode (comando comunitario y app de escritorio), DSH, OpenClaude y Codebuff. Su configuración de usuario se movió a `D:\Agentes-IA\respaldos\descartados-2026-11-20`. Freebuff se conserva y comparte con Codebuff la carpeta `~/.config/manicode`, que no se tocó.

---

## 8. Prueba de rendimiento (20 nov 2026)

Tarea: corregir tres errores de un archivo Python de 20 líneas hasta que pase su prueba automática, sin tocar la prueba. Cada agente en su propia carpeta, sin interfaz, con 5 minutos de límite.

**Resolvieron la tarea (16)**

| Agente | Tiempo | Cuota usada |
|---|---|---|
| Devin | 34 s | Propia (SWE-2 gratis hasta el 30 nov) |
| Continue | 35 s | OpenRouter |
| Grok | 42 s | Propia |
| CodeBuddy | 52 s | Propia |
| Kilo | 54 s | Propia: modelo gratuito de su servicio (`kilo/nvidia/nemotron-3-ultra-550b-a55b:free`) |
| Codex | 54 s | ChatGPT Plus |
| Pi | 62 s | OpenRouter |
| Kiro | 70 s | Propia |
| Aider | 75 s | OpenRouter |
| Goose | 82 s | OpenRouter |
| Qoder | 82 s | Propia |
| OpenCode | 125 s | Propia: modelos gratuitos |
| Cursor | 129 s | Propia |
| Qwen Code | 134 s | OpenRouter |
| Copilot | 146 s | Propia |
| Antigravity | 285 s | Propia, tope semanal |

**Exigen pago:** Amp (sin créditos), Crush (sin créditos en Charm Hyper), Muse (error 402), Autohand (su modelo exige Pro), Auggie (sin sesión válida; su prueba pide tarjeta), Kimi (suscripción a Kimi Code).

**Fallaron la tarea:** Mistral Vibe arregló dos errores y metió uno nuevo. Gemini CLI está retirado.

**Sin resultado:** Ante, Droid y Prime Agent toparon con el límite diario de OpenRouter antes de trabajar; MiMo no terminó en 5 minutos; Cline no encontró el modelo gratuito pedido; Qoder-CN perdió la sesión. Hay que repetirlos.

**Cómo ejecutar cada uno sin interfaz** (lo que funcionó en la prueba):

| Agente | Comando |
|---|---|
| Codex | `codex exec -s workspace-write "<tarea>"` (fuera de git, añadir `--skip-git-repo-check`) |
| Antigravity | `agy -p "<tarea>" --dangerously-skip-permissions` |
| Copilot | `copilot -p "<tarea>" --allow-all` |
| OpenCode | `opencode run --auto "<tarea>"` |
| Kilo | `kilo run --auto -m kilo/nvidia/nemotron-3-ultra-550b-a55b:free "<tarea>"` |
| Devin | `devin -p "<tarea>" --permission-mode accept-edits --respect-workspace-trust false` |
| Kiro | `kiro-cli chat --no-interactive --trust-all-tools "<tarea>"` |
| Cursor | `cursor-agent -p --force "<tarea>"` |
| Grok | `grok -p "<tarea>" --always-approve` |
| CodeBuddy, Qoder | `codebuddy -p "<tarea>" --dangerously-skip-permissions` (igual con `qodercli`) |
| Aider | `aider --message "<tarea>" --yes-always <archivos>` |
| Pi | `pi -p "<tarea>"` |
| Continue | `cn -p --auto "<tarea>"` |
| Goose | `goose run -t "<tarea>"` |
| Qwen Code | `qwen -p "<tarea>" --yolo` |

---

## Fuentes principales

- Gemini CLI, cuotas: https://geminicli.com/docs/resources/quota-and-pricing/
- Antigravity, planes: https://antigravity.google/docs/plans/
- Copilot, planes: https://docs.github.com/en/copilot/get-started/plans
- Qwen Code, fin del plan gratis: https://github.com/QwenLM/qwen-code/issues/3203
- Codex y planes de ChatGPT: https://help.openai.com/en/articles/11369540-using-codex-with-your-chatgpt-plan
- Kiro: https://kiro.dev/pricing/ y https://dev.to/aws/pushing-kiros-free-tier-to-its-limits-5e34
- Devin: https://devin.ai/pricing y https://docs.devin.ai/desktop/models
- Cursor, plan gratis: https://forum.cursor.com/t/cursor-limits-on-free-plan/176500
- Freebuff: https://github.com/CodebuffAI/freebuff
- Factory Droid: https://academy.techpresso.co/reviews/is-factory-worth-it
- Augment: https://www.augmentcode.com/pricing
- Qoder: http://docs.qoder.com/account/pricing
- Mistral Vibe: https://swanbase.co/en/blog/what-is-mistral-vibe/
- Grok Build: https://www.codeagentswarm.com/en/guides/grok-build-pricing
- Amp: https://ampcode.com/news/free-agent
- OpenCode Go: https://thomas-wiegold.com/blog/opencode-go-review/
- Kilo: https://standardcompute.com/best-ai-agent/kilo-code
- Cline: https://vibecoding.gallery/en/tools/cline/
- Hermes y OpenClaw: https://innfactory.ai/en/blog/openclaw-vs-hermes-agent-comparison/
- OpenRouter, modelos gratis: https://openrouter.ai/collections/free-models
