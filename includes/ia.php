<?php
/**
 * Lía: la asistente de IA del equipo.
 *
 * Reglas de privacidad (Ley 1581 de 2012):
 * - El servidor busca en la base local a los clientes mencionados en el texto.
 * - A la IA solo le llegan alias («Cliente 1», «Cliente 2»…) con datos clínicos y del proceso.
 * - Nunca se envían nombre, teléfono, correo, ciudad, fecha de nacimiento ni condiciones.
 * - Al volver la respuesta, el servidor cambia cada alias por el primer nombre.
 *
 * Las llamadas reales son por cURL (formato compatible con OpenAI chat/completions). En las pruebas,
 * $GLOBALS['IA_FALSA'] es un callable y se usa en vez de internet.
 */

/** ¿Hay al menos un proveedor con clave? (o una IA falsa para las pruebas) */
function ia_disponible(): bool
{
    if (isset($GLOBALS['IA_FALSA']) && is_callable($GLOBALS['IA_FALSA'])) {
        return true;
    }
    foreach ((array) config('ia.proveedores', []) as $proveedor) {
        if (!is_array($proveedor)) {
            continue;
        }
        if (trim((string) ($proveedor['clave'] ?? '')) !== '' && trim((string) ($proveedor['url'] ?? '')) !== '') {
            return true;
        }
    }
    return false;
}

/**
 * Pide a la IA un objeto JSON. Recorre config('ia.orden') y los modelos de cada proveedor hasta que
 * uno responda JSON válido. Registra cada intento en ia_uso.
 *
 * @throws RuntimeException si se agotó el límite diario o si ningún proveedor respondió.
 */
function ia_consultar(string $funcion, string $sistema, string $texto, array $esquema): array
{
    $limite = (int) config('ia.limite_diario', 200);
    $st = db()->prepare('SELECT COUNT(*) FROM ia_uso WHERE fecha >= ? AND fecha < ?');

    $orden = config('ia.orden', []);
    $proveedores = config('ia.proveedores', []);
    $falsa = $GLOBALS['IA_FALSA'] ?? null;
    $falsa = is_callable($falsa) ? $falsa : null;

    foreach ((array) $orden as $nombre) {
        $proveedor = $proveedores[$nombre] ?? null;
        if (!is_array($proveedor)) {
            continue;
        }
        $url = trim((string) ($proveedor['url'] ?? ''));
        $clave = trim((string) ($proveedor['clave'] ?? ''));
        $modelos = (array) ($proveedor['modelos'] ?? []);
        if ($modelos === []) {
            continue;
        }
        if ($falsa === null && ($url === '' || $clave === '')) {
            continue;
        }

        $proveedor_invalido = false;
        foreach ($modelos as $modelo) {
            $modelo = (string) $modelo;
            $etiqueta = $nombre . '/' . $modelo;

            // Primero se intenta con el esquema; si el modelo lo rechaza (400), se repite con json_object.
            foreach (['esquema', 'json'] as $modo) {
                $inicio = new DateTimeImmutable(hoy());
                $st->execute([$inicio->format('Y-m-d H:i:s'), $inicio->modify('+1 day')->format('Y-m-d H:i:s')]);
                if ((int) $st->fetchColumn() >= $limite) {
                    throw new RuntimeException('Se agotó por hoy el uso de la IA');
                }

                $payload = ia_payload($modelo, $sistema, $texto, $esquema, $modo);
                $r = ia_peticion((string) $nombre, $modelo, $url, $clave, $payload, $falsa);

                $datos = null;
                if ($r['ok']) {
                    $datos = ia_decodificar($r['contenido'], $esquema);
                }

                if ($datos !== null) {
                    ia_registrar($funcion, $etiqueta, true, $r['tokens_entrada'], $r['tokens_salida'], '');
                    return $datos;
                }

                $detalle = $r['ok'] ? 'La respuesta no cumple el esquema JSON' : $r['detalle'];
                ia_registrar($funcion, $etiqueta, false, $r['tokens_entrada'], $r['tokens_salida'], $detalle);

                if ($r['codigo'] === 401 || $r['codigo'] === 403) {
                    $proveedor_invalido = true;      // clave mala: se salta al siguiente proveedor
                    break;
                }
                if ($r['codigo'] === 400 && $modo === 'esquema') {
                    continue;                        // el modelo no acepta el esquema: se reintenta con json_object
                }
                break;                               // otro error: siguiente modelo
            }
            if ($proveedor_invalido) {
                break;
            }
        }
    }

    throw new RuntimeException('La IA no respondió. Inténtalo de nuevo en un momento.');
}

/** Arma el payload de chat/completions. Con json_object el esquema va escrito en el texto. */
function ia_payload(string $modelo, string $sistema, string $texto, array $esquema, string $modo): array
{
    $mensajes = [
        ['role' => 'system', 'content' => $sistema],
        ['role' => 'user', 'content' => $texto],
    ];
    if ($modo === 'esquema') {
        $formato = [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'respuesta_lia',
                'strict' => false,
                'schema' => $esquema,
            ],
        ];
    } else {
        $formato = ['type' => 'json_object'];
        $mensajes[0]['content'] .= "\n\nResponde únicamente con un objeto JSON que cumpla este esquema: "
            . json_encode($esquema, JSON_UNESCAPED_UNICODE);
    }
    return [
        'model' => $modelo,
        'messages' => $mensajes,
        'response_format' => $formato,
        'temperature' => 0.3,
    ];
}

/**
 * Un intento: cURL o la IA falsa de las pruebas.
 * Devuelve ok, contenido, codigo, tokens y un detalle corto del error.
 */
function ia_peticion(string $proveedor, string $modelo, string $url, string $clave, array $payload, ?callable $falsa): array
{
    if ($falsa !== null) {
        $r = $falsa($proveedor, $modelo, $payload);
        if (!is_array($r)) {
            return ['ok' => false, 'contenido' => '', 'codigo' => 0, 'tokens_entrada' => 0,
                    'tokens_salida' => 0, 'detalle' => 'La IA de prueba no devolvió datos'];
        }
        $ok = (bool) ($r['ok'] ?? false);
        return [
            'ok' => $ok,
            'contenido' => (string) ($r['contenido'] ?? ''),
            'codigo' => (int) ($r['codigo'] ?? 0),
            'tokens_entrada' => (int) ($r['tokens_entrada'] ?? 0),
            'tokens_salida' => (int) ($r['tokens_salida'] ?? 0),
            'detalle' => $ok ? '' : (string) ($r['detalle'] ?? 'La IA de prueba falló'),
        ];
    }

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'contenido' => '', 'codigo' => 0, 'tokens_entrada' => 0,
                'tokens_salida' => 0, 'detalle' => 'cURL no está disponible'];
    }

    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'contenido' => '', 'codigo' => 0, 'tokens_entrada' => 0,
                'tokens_salida' => 0, 'detalle' => 'No se pudo iniciar cURL'];
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $clave],
        CURLOPT_POSTFIELDS => (string) json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $cuerpo = curl_exec($ch);
    $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = (string) curl_error($ch);
    curl_close($ch);

    if ($cuerpo === false) {
        return ['ok' => false, 'contenido' => '', 'codigo' => 0, 'tokens_entrada' => 0,
                'tokens_salida' => 0, 'detalle' => $error !== '' ? $error : 'Sin conexión'];
    }
    $cuerpo = (string) $cuerpo;
    $datos = json_decode($cuerpo, true);
    $contenido = '';
    if (is_array($datos) && is_string($datos['choices'][0]['message']['content'] ?? null)) {
        $contenido = (string) $datos['choices'][0]['message']['content'];
    }
    $ok = $codigo >= 200 && $codigo < 300 && $contenido !== '';
    $detalle = $ok ? '' : substr(trim(($error !== '' ? $error . ' ' : '') . 'HTTP ' . $codigo . ': '
        . substr($cuerpo, 0, 150)), 0, 200);

    return [
        'ok' => $ok,
        'contenido' => $contenido,
        'codigo' => $codigo,
        'tokens_entrada' => (int) ($datos['usage']['prompt_tokens'] ?? 0),
        'tokens_salida' => (int) ($datos['usage']['completion_tokens'] ?? 0),
        'detalle' => $detalle,
    ];
}

/** Valida el objeto JSON y los campos requeridos por el esquema de respuesta. */
function ia_decodificar(string $contenido, array $esquema): ?array
{
    $objeto = json_decode($contenido);
    if (!$objeto instanceof stdClass) {
        return null;
    }

    $datos = (array) $objeto;
    foreach ($esquema['required'] ?? [] as $campo) {
        $valor = $datos[$campo] ?? null;
        $regla = $esquema['properties'][$campo] ?? [];
        $tipoCorrecto = match ($regla['type'] ?? null) {
            'string' => is_string($valor),
            'integer' => is_int($valor),
            default => array_key_exists($campo, $datos),
        };
        if (!$tipoCorrecto || (isset($regla['enum']) && !in_array($valor, $regla['enum'], true))) {
            return null;
        }
    }
    if (isset($datos['respuesta']) && (!is_string($datos['respuesta']) || trim($datos['respuesta']) === '')) {
        return null;
    }
    return $datos;
}

/** Anota el intento en ia_uso (también los fallidos). */
function ia_registrar(string $funcion, string $modelo, bool $ok, int $tokens_entrada, int $tokens_salida, string $detalle): void
{
    $usuario = usuario_actual();
    $detalle = mb_substr(trim($detalle), 0, 200);
    db()->prepare('INSERT INTO ia_uso (usuario_id, funcion, modelo, ok, tokens_entrada, tokens_salida, detalle, fecha)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $usuario['id'] ?? null,
            mb_substr($funcion, 0, 40),
            mb_substr($modelo, 0, 80),
            $ok ? 1 : 0,
            $tokens_entrada,
            $tokens_salida,
            $detalle !== '' ? $detalle : null,
            ahora(),
        ]);
}

/* ---------- Lía: respuesta, privacidad y navegación ---------- */

/** Responde una pregunta del equipo. Devuelve ['respuesta' => ..., 'accion' => ..., 'url' => ...]. */
function lia_responder(array $usuario, string $texto, array $historial): array
{
    $texto = trim($texto);
    $busqueda = lia_buscar_clientes($texto);
    $aliases = $historial === [] ? [] : ($_SESSION['lia_aliases'] ?? []);
    $mencionados = array_merge($busqueda['completos'], $busqueda['parciales']);
    foreach ($mencionados as &$cliente) {
        $cliente['alias'] = $aliases[$cliente['id']] ??= 'Cliente ' . (count($aliases) + 1);
    }
    unset($cliente);
    $clientes = array_slice($mencionados, 0, min(5, count($busqueda['completos'])));
    $contexto = lia_contexto($clientes);
    $resumen = lia_resumen();

    $preguntaVisible = lia_ocultar_nombres($texto, $mencionados);
    $pregunta = "Pregunta del equipo: {$preguntaVisible}\n\n"
        . 'Resumen general (sin nombres): ' . json_encode($resumen, JSON_UNESCAPED_UNICODE) . "\n\n"
        . 'Clientes encontrados en la base local: ' . json_encode($contexto, JSON_UNESCAPED_UNICODE);
    if ($clientes === []) {
        $pregunta .= "\nNo se encontró ningún cliente con ese nombre en la base de datos.";
    }

    $charla = lia_historial_texto($historial);
    if ($charla !== '') {
        $pregunta .= "\n\nConversación reciente:\n" . $charla;
    }

    $datos = ia_consultar('lia', lia_sistema(), $pregunta, lia_esquema());
    $mencionadosRespuesta = lia_buscar_clientes($datos['respuesta']);
    $clientesRespuesta = array_merge($mencionadosRespuesta['completos'], $mencionadosRespuesta['parciales']);
    foreach ($clientesRespuesta as &$cliente) {
        $cliente['alias'] = $aliases[$cliente['id']] ?? 'un cliente';
    }
    unset($cliente);
    $respuestaVisible = lia_ocultar_nombres($datos['respuesta'], $clientesRespuesta);
    $_SESSION['lia_aliases'] = $aliases;
    $_SESSION['lia_historial'] = array_slice([
        ...$historial,
        'Tú: ' . $preguntaVisible,
        'Lía: ' . $respuestaVisible,
    ], -6);
    return lia_preparar_respuesta($datos, $clientes);
}

/** Instrucciones fijas de Lía. */
function lia_sistema(): string
{
    return 'Eres Lía, la asistente del equipo de BRDG Peptides Colombia. '
        . 'Hablas en español de Colombia, tuteando, con respuestas cortas de 1 a 3 frases, claras y sin markdown '
        . 'ni emojis porque se leen en voz alta. '
        . 'Usas solamente los datos que te entrega el servidor; si no encuentras al cliente en esos datos, lo dices '
        . 'y sugieres buscarlo. '
        . 'NUNCA recomiendas dosis, cambios de plan ni das consejos médicos: para eso, que hablen con el responsable '
        . 'del plan. Nunca cambias datos. '
        . 'Cada cliente viene con un alias como «Cliente 1»: usa siempre ese alias, nunca inventes nombres. '
        . 'Para las acciones usa abrir_cliente con el alias exacto, abrir_caso con un id de caso que aparezca en los '
        . 'datos, buscar con el texto a buscar, o ir con destino inicio o clientes. Si no hay que navegar, usa ninguna. '
        . 'Nunca escribas una URL: el servidor la arma. '
        . 'Devuelve siempre el objeto JSON del esquema indicado.';
}

/** Esquema que debe cumplir la respuesta de la IA. */
function lia_esquema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'respuesta' => ['type' => 'string'],
            'accion' => ['type' => 'string', 'enum' => ['ninguna', 'abrir_cliente', 'abrir_caso', 'buscar', 'ir']],
            'cliente_alias' => ['type' => 'string', 'description' => 'Alias exacto, por ejemplo Cliente 1'],
            'caso_id' => ['type' => 'integer', 'description' => 'Id real del caso mostrado en los datos, o 0'],
            'busqueda' => ['type' => 'string', 'description' => 'Texto para buscar clientes'],
            'destino' => ['type' => 'string', 'enum' => ['inicio', 'clientes', '']],
        ],
        'required' => ['respuesta', 'accion', 'cliente_alias', 'caso_id', 'busqueda', 'destino'],
        'additionalProperties' => false,
    ];
}

/**
 * Clientes activos cuyo nombre y apellido aparecen en el texto.
 * Compara palabras de 3 o más letras, sin tildes y en minúsculas.
 */
function lia_clientes_mencionados(string $texto): array
{
    return array_slice(lia_buscar_clientes($texto)['completos'], 0, 5);
}

/**
 * Busca clientes por nombre. «completos» son coincidencia de nombre y apellido;
 * «parciales» solo comparten alguna palabra (sirven para borrar nombres sueltos del texto).
 */
function lia_buscar_clientes(string $texto): array
{
    $normalizado = lia_normalizar($texto);
    preg_match_all('/[a-z0-9]+/', $normalizado, $partes);
    $palabras = [];
    foreach ($partes[0] ?? [] as $palabra) {
        if (mb_strlen($palabra) >= 3) {
            $palabras[$palabra] = true;
        }
    }
    if ($palabras === []) {
        return ['completos' => [], 'parciales' => []];
    }

    $completos = [];
    $parciales = [];
    $st = db()->query('SELECT id, nombre, telefono FROM clientes WHERE activo = 1 ORDER BY id');
    foreach ($st->fetchAll() as $cliente) {
        $nombre = (string) $cliente['nombre'];
        $suyas = [];
        preg_match_all('/[a-z0-9]+/', lia_normalizar($nombre), $partesNombre);
        foreach ($partesNombre[0] as $palabra) {
            if (mb_strlen($palabra) >= 3) {
                $suyas[] = $palabra;
            }
        }
        if ($suyas === []) {
            continue;
        }
        $coinciden = 0;
        foreach ($suyas as $palabra) {
            if (isset($palabras[$palabra])) {
                $coinciden++;
            }
        }
        if ($coinciden === count($suyas)) {
            $completos[] = $cliente;
        } elseif ($coinciden > 0) {
            $parciales[] = $cliente;
        }
    }
    return ['completos' => $completos, 'parciales' => $parciales];
}

/** Minúsculas y sin tildes, para comparar nombres. */
function lia_normalizar(string $texto): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    return strtr($texto, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ]);
}

/**
 * Datos de cada cliente con alias, sin nada que lo identifique.
 * Incluye casos, plan actual (ítems y dosis) y los últimos 5 seguimientos.
 */
function lia_contexto(array $clientes): array
{
    $salida = [];
    foreach ($clientes as $cliente) {
        $alias = $cliente['alias'];
        $casos = [];

        $st = db()->prepare('SELECT id, titulo, estado, fecha_inicio, peso_inicial, peso_meta, proximo_contacto
                             FROM casos WHERE cliente_id = ? ORDER BY id');
        $st->execute([(int) $cliente['id']]);
        foreach ($st->fetchAll() as $caso) {
            $plan = null;
            $stp = db()->prepare('SELECT id, version, creado FROM planes WHERE caso_id = ?
                                  ORDER BY version DESC, id DESC LIMIT 1');
            $stp->execute([(int) $caso['id']]);
            $filaPlan = $stp->fetch();
            if ($filaPlan) {
                $sti = db()->prepare('SELECT p.nombre AS producto, i.dosis, i.frecuencia, i.duracion
                                      FROM plan_items i LEFT JOIN productos p ON p.id = i.producto_id
                                      WHERE i.plan_id = ? ORDER BY i.orden, i.id');
                $sti->execute([(int) $filaPlan['id']]);
                $plan = [
                    'version' => (int) $filaPlan['version'],
                    'fecha' => $filaPlan['creado'],
                    'items' => $sti->fetchAll(),
                ];
            }

            $casos[] = [
                'id' => (int) $caso['id'],
                'titulo' => lia_ocultar_nombres($caso['titulo'], [$cliente]),
                'estado' => (string) $caso['estado'],
                'fecha_inicio' => (string) $caso['fecha_inicio'],
                'peso_inicial' => $caso['peso_inicial'] === null ? null : (float) $caso['peso_inicial'],
                'peso_meta' => $caso['peso_meta'] === null ? null : (float) $caso['peso_meta'],
                'proximo_contacto' => $caso['proximo_contacto'],
                'plan_actual' => $plan,
            ];
        }

        $seguimientos = [];
        $sts = db()->prepare('SELECT s.fecha, s.tipo, s.peso, s.cintura_cm, s.adherencia
                              FROM seguimientos s JOIN casos c ON c.id = s.caso_id
                              WHERE c.cliente_id = ? ORDER BY s.fecha DESC, s.id DESC LIMIT 5');
        $sts->execute([(int) $cliente['id']]);
        foreach ($sts->fetchAll() as $s) {
            $seguimientos[] = [
                'fecha' => (string) $s['fecha'],
                'tipo' => (string) $s['tipo'],
                'peso' => $s['peso'] === null ? null : (float) $s['peso'],
                'cintura_cm' => $s['cintura_cm'] === null ? null : (float) $s['cintura_cm'],
                'adherencia' => $s['adherencia'],
            ];
        }

        $salida[] = [
            'alias' => $alias,
            'casos' => $casos,
            'ultimos_seguimientos' => $seguimientos,
        ];
    }
    return $salida;
}

/** Conteos generales, sin nombres. */
function lia_resumen(): array
{
    $activos = (int) db()->query("SELECT COUNT(*) FROM casos WHERE estado = 'activo'")->fetchColumn();
    $st = db()->prepare("SELECT COUNT(*) FROM casos WHERE estado = 'activo' AND proximo_contacto = ?");
    $st->execute([hoy()]);
    $hoyContactos = (int) $st->fetchColumn();
    $st = db()->prepare("SELECT COUNT(*) FROM casos WHERE estado = 'activo' AND proximo_contacto IS NOT NULL
                         AND proximo_contacto < ?");
    $st->execute([hoy()]);
    $atrasados = (int) $st->fetchColumn();
    return [
        'casos_activos' => $activos,
        'contactos_para_hoy' => $hoyContactos,
        'contactos_atrasados' => $atrasados,
    ];
}

/** Enmascara correos y números largos antes de enviar o guardar texto del chat. */
function lia_ocultar_contacto(string $texto): string
{
    $texto = (string) preg_replace('/[^\s@<>(),;:]+@[^\s@<>(),;:.]+(?:\.[\p{L}\p{N}-]+)+/u', '[correo]', $texto);
    return (string) preg_replace('/\d{7,}/u', '[número]', $texto);
}

/** Sustituye los nombres mencionados y enmascara los teléfonos conocidos con formato. */
function lia_ocultar_nombres(string $texto, array $clientes): string
{
    $palabras = [];
    foreach ($clientes as $cliente) {
        preg_match_all('/\p{L}+/u', $cliente['nombre'], $partes);
        foreach ($partes[0] as $palabra) {
            $palabras[lia_normalizar($palabra)] ??= $cliente['alias'];
        }
        if ($cliente['telefono'] !== '') {
            $texto = str_replace($cliente['telefono'], '[número]', $texto);
        }
    }
    $texto = (string) preg_replace_callback(
        '/\bCliente \d+\b|\[(?:correo|número)\]|\p{L}+/iu',
        fn($m) => $palabras[lia_normalizar($m[0])] ?? $m[0],
        lia_ocultar_contacto($texto)
    );
    return trim((string) preg_replace('/(Cliente \d+)(?:\s+\1)+/u', '$1', $texto));
}

/** Las últimas líneas de la conversación, recortadas. */
function lia_historial_texto(array $historial): string
{
    $lineas = [];
    foreach (array_slice($historial, -6) as $linea) {
        $linea = trim((string) $linea);
        if ($linea !== '') {
            $lineas[] = mb_substr($linea, 0, 300);
        }
    }
    return mb_substr(implode("\n", $lineas), 0, 1500);
}

/**
 * Cambia los alias por el primer nombre y arma la URL en el servidor.
 * Cualquier URL que venga de la IA se ignora.
 */
function lia_preparar_respuesta(array $datos, array $clientes): array
{
    $respuesta = trim((string) ($datos['respuesta'] ?? ''));
    if ($respuesta === '') {
        $respuesta = 'No pude preparar una respuesta. Inténtalo de nuevo.';
    }

    $accion = (string) ($datos['accion'] ?? 'ninguna');
    if (!in_array($accion, ['ninguna', 'abrir_cliente', 'abrir_caso', 'buscar', 'ir'], true)) {
        $accion = 'ninguna';
    }

    $porAlias = [];
    foreach ($clientes as $cliente) {
        $porAlias[mb_strtolower($cliente['alias'])] = $cliente;
    }

    $respuesta = (string) preg_replace_callback('/\bCliente \d+\b/iu', function ($m) use ($porAlias) {
        $cliente = $porAlias[mb_strtolower($m[0])] ?? null;
        return $cliente === null ? $m[0] : primer_nombre($cliente['nombre']);
    }, $respuesta);

    $url = '';
    switch ($accion) {
        case 'abrir_cliente':
            $pedido = mb_strtolower(trim((string) ($datos['cliente_alias'] ?? '')));
            if (isset($porAlias[$pedido])) {
                $url = 'cliente.php?id=' . (int) $porAlias[$pedido]['id'];
            } else {
                $accion = 'ninguna';
            }
            break;

        case 'abrir_caso':
            $casoId = (int) ($datos['caso_id'] ?? 0);
            if ($casoId > 0 && $clientes !== []) {
                $st = db()->prepare('SELECT cliente_id FROM casos WHERE id = ?');
                $st->execute([$casoId]);
                $dueno = $st->fetchColumn();
                $ids = array_map(fn($c) => (int) $c['id'], $clientes);
                if ($dueno !== false && in_array((int) $dueno, $ids, true)) {
                    $url = 'caso.php?id=' . $casoId;
                }
            }
            if ($url === '') {
                $accion = 'ninguna';
            }
            break;

        case 'buscar':
            $busqueda = trim((string) ($datos['busqueda'] ?? ''));
            $url = 'clientes.php?q=' . rawurlencode($busqueda);
            break;

        case 'ir':
            $destino = mb_strtolower(trim((string) ($datos['destino'] ?? '')));
            if ($destino === 'inicio') {
                $url = 'index.php';
            } elseif ($destino === 'clientes') {
                $url = 'clientes.php';
            } else {
                $accion = 'ninguna';
            }
            break;

        default:
            $accion = 'ninguna';
            break;
    }

    if ($accion === 'ninguna') {
        $url = '';
    }

    return ['respuesta' => $respuesta, 'accion' => $accion, 'url' => $url];
}
