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
    $st = db()->prepare('SELECT COUNT(*) FROM ia_uso WHERE ok = 1 AND fecha >= ?');
    $st->execute([hoy() . ' 00:00:00']);
    if ((int) $st->fetchColumn() >= $limite) {
        throw new RuntimeException('Se agotó por hoy el uso de la IA');
    }

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
                $payload = ia_payload($modelo, $sistema, $texto, $esquema, $modo);
                $r = ia_peticion((string) $nombre, $modelo, $url, $clave, $payload, $falsa);

                $datos = null;
                if ($r['ok']) {
                    $datos = ia_decodificar($r['contenido']);
                }

                if ($datos !== null) {
                    ia_registrar($funcion, $etiqueta, true, $r['tokens_entrada'], $r['tokens_salida'], '');
                    return $datos;
                }

                $detalle = $r['ok'] ? 'La respuesta no vino en JSON válido' : $r['detalle'];
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

/** Decodifica la respuesta JSON de la IA. Acepta el objeto directo o envuelto en texto. */
function ia_decodificar(string $contenido): ?array
{
    $contenido = trim($contenido);
    if ($contenido === '') {
        return null;
    }
    $datos = json_decode($contenido, true);
    if (is_array($datos)) {
        return $datos;
    }
    // A veces el modelo envuelve el JSON en ```json ... ``` o agrega texto alrededor.
    if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/si', $contenido, $m)) {
        $datos = json_decode($m[1], true);
        if (is_array($datos)) {
            return $datos;
        }
    }
    if (preg_match('/(\{.*\})/s', $contenido, $m)) {
        $datos = json_decode($m[1], true);
        if (is_array($datos)) {
            return $datos;
        }
    }
    return null;
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
    $clientes = $busqueda['completos'];
    $ids = array_map(fn($c) => (int) $c['id'], $clientes);
    $parciales = array_values(array_filter($busqueda['parciales'], fn($c) => !in_array((int) $c['id'], $ids, true)));
    $contexto = lia_contexto($clientes);
    $resumen = lia_resumen();

    // La pregunta también viaja a la IA: los nombres se cambian por el alias.
    $preguntaVisible = lia_ocultar_nombres($texto, $clientes);
    if ($parciales !== []) {
        $preguntaVisible = lia_ocultar_nombres($preguntaVisible, $parciales, false);
    }
    $pregunta = "Pregunta del equipo: {$preguntaVisible}\n\n"
        . 'Resumen general (sin nombres): ' . json_encode($resumen, JSON_UNESCAPED_UNICODE) . "\n\n"
        . 'Clientes encontrados en la base local: ' . json_encode($contexto, JSON_UNESCAPED_UNICODE);
    if ($clientes === []) {
        $pregunta .= "\nNo se encontró ningún cliente con ese nombre en la base de datos.";
    }

    $charla = lia_historial_texto($historial);
    if ($charla !== '') {
        $charla = lia_ocultar_nombres($charla, $clientes);
        $otras = lia_buscar_clientes($charla);
        $ajenos = array_merge($otras['completos'], $otras['parciales']);
        $idsTexto = array_map(fn($c) => (int) $c['id'], $clientes);
        $ajenos = array_values(array_filter($ajenos, fn($c) => !in_array((int) $c['id'], $idsTexto, true)));
        if ($ajenos !== []) {
            $charla = lia_ocultar_nombres($charla, $ajenos, false);
        }
        $pregunta .= "\n\nConversación reciente:\n" . $charla;
    }

    $datos = ia_consultar('lia', lia_sistema(), $pregunta, lia_esquema());
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
    return lia_buscar_clientes($texto)['completos'];
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
        foreach (preg_split('/\s+/u', lia_normalizar($nombre)) ?: [] as $palabra) {
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
            if (count($completos) >= 5) {
                break;
            }
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
    foreach (array_values($clientes) as $i => $cliente) {
        $alias = 'Cliente ' . ($i + 1);
        $casos = [];

        $st = db()->prepare('SELECT id, titulo, estado, fecha_inicio, peso_inicial, peso_meta, proximo_contacto
                             FROM casos WHERE cliente_id = ? ORDER BY id');
        $st->execute([(int) $cliente['id']]);
        foreach ($st->fetchAll() as $caso) {
            $plan = null;
            $stp = db()->prepare('SELECT id, version, indicaciones FROM planes WHERE caso_id = ?
                                  ORDER BY version DESC, id DESC LIMIT 1');
            $stp->execute([(int) $caso['id']]);
            $filaPlan = $stp->fetch();
            if ($filaPlan) {
                $items = [];
                $sti = db()->prepare('SELECT producto_nombre, dosis, frecuencia, duracion
                                      FROM plan_items WHERE plan_id = ? ORDER BY orden, id');
                $sti->execute([(int) $filaPlan['id']]);
                foreach ($sti->fetchAll() as $item) {
                    $items[] = [
                        'producto' => lia_limpiar($item['producto_nombre'], $cliente),
                        'dosis' => lia_limpiar($item['dosis'], $cliente),
                        'frecuencia' => lia_limpiar($item['frecuencia'], $cliente),
                        'duracion' => lia_limpiar($item['duracion'], $cliente),
                    ];
                }
                $plan = [
                    'version' => (int) $filaPlan['version'],
                    'indicaciones' => lia_limpiar($filaPlan['indicaciones'], $cliente),
                    'items' => $items,
                ];
            }

            $casos[] = [
                'id' => (int) $caso['id'],
                'titulo' => lia_limpiar($caso['titulo'], $cliente),
                'estado' => (string) $caso['estado'],
                'fecha_inicio' => (string) $caso['fecha_inicio'],
                'peso_inicial' => $caso['peso_inicial'] === null ? null : (float) $caso['peso_inicial'],
                'peso_meta' => $caso['peso_meta'] === null ? null : (float) $caso['peso_meta'],
                'proximo_contacto' => $caso['proximo_contacto'],
                'plan_actual' => $plan,
            ];
        }

        $seguimientos = [];
        $sts = db()->prepare('SELECT s.fecha, s.tipo, s.peso, s.cintura_cm, s.adherencia, s.efectos, s.nota
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
                'efectos' => lia_limpiar($s['efectos'], $cliente),
                'nota' => lia_limpiar($s['nota'], $cliente),
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

/** Quita de un texto libre cualquier dato que identifique al cliente. */
function lia_limpiar(?string $texto, array $cliente): string
{
    $texto = (string) $texto;
    if ($texto === '') {
        return '';
    }
    $nombre = trim((string) ($cliente['nombre'] ?? ''));
    if ($nombre !== '') {
        $texto = (string) preg_replace('/' . preg_quote($nombre, '/') . '/iu', 'el cliente', $texto);
        foreach (preg_split('/\s+/u', $nombre) ?: [] as $palabra) {
            if (mb_strlen($palabra) >= 3) {
                $texto = (string) preg_replace('/\b' . preg_quote($palabra, '/') . '\b/iu', '', $texto);
            }
        }
    }
    $telefono = trim((string) ($cliente['telefono'] ?? ''));
    if ($telefono !== '') {
        $texto = str_replace($telefono, '', $texto);
        $digitos = preg_replace('/\D/', '', $telefono);
        if (strlen((string) $digitos) >= 7) {
            $texto = (string) preg_replace('/(?<!\d)' . preg_quote((string) $digitos, '/') . '(?!\d)/', '', $texto);
        }
    }
    return trim((string) preg_replace('/\s+/u', ' ', $texto));
}

/**
 * Cambia en un texto libre las palabras que forman el nombre del cliente por un alias
 * (o por «un cliente»). También quita su teléfono.
 */
function lia_ocultar_nombres(string $texto, array $clientes, bool $conAlias = true): string
{
    foreach (array_values($clientes) as $i => $cliente) {
        $reemplazo = $conAlias ? 'Cliente ' . ($i + 1) : 'un cliente';
        $palabras = [];
        foreach (preg_split('/\s+/u', (string) ($cliente['nombre'] ?? '')) ?: [] as $palabra) {
            $normal = lia_normalizar($palabra);
            if (mb_strlen($normal) >= 3) {
                $palabras[$normal] = true;
            }
        }
        if ($palabras !== []) {
            $texto = (string) preg_replace_callback('/[\p{L}\p{N}]+/u', function ($m) use ($palabras, $reemplazo) {
                return isset($palabras[lia_normalizar($m[0])]) ? $reemplazo : $m[0];
            }, $texto);
        }
        $telefono = trim((string) ($cliente['telefono'] ?? ''));
        if ($telefono !== '') {
            $texto = str_replace($telefono, '', $texto);
            $digitos = (string) preg_replace('/\D/', '', $telefono);
            if (strlen($digitos) >= 7) {
                $texto = (string) preg_replace('/(?<!\d)' . preg_quote($digitos, '/') . '(?!\d)/', '', $texto);
            }
        }
    }
    return trim((string) preg_replace('/(Cliente \d+)(?:\s+\1)+/u', '$1', $texto));
}

/** Las últimas líneas de la conversación, recortadas. */
function lia_historial_texto(array $historial): string
{
    $lineas = [];
    foreach (array_slice($historial, -6) as $linea) {
        if (is_array($linea)) {
            $linea = (string) ($linea['texto'] ?? $linea['contenido'] ?? '');
        }
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
    foreach (array_values($clientes) as $i => $cliente) {
        $porAlias[mb_strtolower('Cliente ' . ($i + 1))] = $cliente;
    }

    // Primero los alias largos, para no romper «Cliente 1» dentro de «Cliente 10».
    $alias = array_keys($porAlias);
    usort($alias, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
    foreach ($alias as $textoAlias) {
        $cliente = $porAlias[$textoAlias];
        $respuesta = str_ireplace($textoAlias, primer_nombre((string) $cliente['nombre']), $respuesta);
    }

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
