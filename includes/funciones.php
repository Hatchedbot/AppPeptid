<?php
/**
 * Funciones comunes: configuración, sesión, CSRF, usuarios y permisos, avisos, fechas, números y WhatsApp.
 * Las funciones de cada módulo van en su propio archivo (includes/clientes.php, includes/casos.php, includes/ia.php).
 */

const ROLES = ['admin' => 'Administrador', 'asesor' => 'Asesor'];

/** Qué puede hacer cada rol. El admin puede todo. */
const PERMISOS = [
    'clientes' => ['admin', 'asesor'],
    'casos'    => ['admin', 'asesor'],
    'lia'      => ['admin', 'asesor'],
    'pedidos'    => ['admin', 'asesor'],
    'inventario' => ['admin', 'asesor'],
    'mensajes'   => ['admin', 'asesor'],
    'usuarios' => ['admin'],
];

/** Nombre visible de cada permiso, para la pantalla de usuarios. «usuarios» es solo del administrador. */
const PERMISOS_NOMBRES = [
    'clientes'   => 'Clientes',
    'casos'      => 'Procesos y planes',
    'pedidos'    => 'Pedidos',
    'inventario' => 'Inventario',
    'mensajes'   => 'Mensajes y campañas',
    'lia'        => 'Asistente Lía',
];

/* ---------- Configuración y utilidades ---------- */

/** config('ia.limite_diario') lee $CONFIG['ia']['limite_diario']. */
function config(string $clave, $defecto = null)
{
    $valor = $GLOBALS['CONFIG'] ?? [];
    foreach (explode('.', $clave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $defecto;
        }
        $valor = $valor[$parte];
    }
    return $valor;
}

function e($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ahora(): string
{
    return date('Y-m-d H:i:s');
}

function hoy(): string
{
    return date('Y-m-d');
}

function token_aleatorio(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function redirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function json_respuesta(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Dirección base pública, para los enlaces que se mandan por WhatsApp. */
function url_base(): string
{
    $url = rtrim((string) config('app.url', ''), '/');
    if ($url !== '') {
        return $url;
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    $dir = preg_replace('#/(api|includes)$#', '', $dir);
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

/* ---------- Sesión, CSRF y avisos en pantalla ---------- */

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on';
    session_name('brdg');
    session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => true,
                               'samesite' => 'Lax', 'secure' => $https]);
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 30));
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = token_aleatorio();
    }
    return $_SESSION['csrf'];
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Todo POST lleva el token: en el formulario (campo_csrf) o en la cabecera X-CSRF (fetch). */
function verificar_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    $enviado = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    if ($enviado === '' || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(400);
        exit('La página caducó. Vuelve atrás, recarga e inténtalo de nuevo.');
    }
}

/** Mensaje que se muestra una vez en la siguiente página. $tipo: ok | error | info */
function avisar(string $tipo, string $mensaje): void
{
    $_SESSION['avisos'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function tomar_avisos(): array
{
    $a = $_SESSION['avisos'] ?? [];
    unset($_SESSION['avisos']);
    return $a;
}

/* ---------- Usuarios y permisos ---------- */

function usuario_actual(): ?array
{
    if (isset($GLOBALS['USUARIO_FORZADO'])) {      // solo en pruebas
        return $GLOBALS['USUARIO_FORZADO'];
    }
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['usuario_id'])) {
            $st = db()->prepare('SELECT id, nombre, usuario, rol, activo, permisos_propios FROM usuarios WHERE id = ?');
            $st->execute([$_SESSION['usuario_id']]);
            $fila = $st->fetch();
            $u = ($fila && (int) $fila['activo'] === 1) ? $fila : null;
        }
    }
    return $u;
}

function requerir_login(): array
{
    $u = usuario_actual();
    if (!$u) {
        if (!db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn()) {
            redirigir('instalar.php');
        }
        redirigir('login.php');
    }
    return $u;
}

function puede(string $permiso): bool
{
    $u = usuario_actual();
    if (!$u) {
        return false;
    }
    if ($u['rol'] === 'admin') {
        return true;
    }
    if ((int) ($u['permisos_propios'] ?? 0) === 1) {
        // «usuarios» nunca se da por permiso propio: solo el administrador gestiona cuentas.
        return $permiso !== 'usuarios' && in_array($permiso, permisos_de_usuario((int) $u['id']), true);
    }
    return in_array($u['rol'], PERMISOS[$permiso] ?? [], true);
}

/** Permisos propios guardados para un usuario (tabla usuario_permisos). */
function permisos_de_usuario(int $usuario_id): array
{
    $st = db()->prepare('SELECT permiso FROM usuario_permisos WHERE usuario_id = ? ORDER BY permiso');
    $st->execute([$usuario_id]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

function requerir_permiso(string $permiso): array
{
    $u = requerir_login();
    if (!puede($permiso)) {
        http_response_code(403);
        exit('No tienes permiso para ver esta página.');
    }
    return $u;
}

function crear_usuario(string $nombre, string $usuario, string $clave, string $rol): int
{
    if (!isset(ROLES[$rol])) {
        throw new InvalidArgumentException('Rol no válido');
    }
    db()->prepare('INSERT INTO usuarios (nombre, usuario, clave_hash, rol, activo, creado) VALUES (?, ?, ?, ?, 1, ?)')
        ->execute([trim($nombre), mb_strtolower(trim($usuario)), password_hash($clave, PASSWORD_DEFAULT), $rol, ahora()]);
    return (int) db()->lastInsertId();
}

/** Devuelve el usuario si la clave es correcta (y lo deja en la sesión), o null. */
function entrar(string $usuario, string $clave): ?array
{
    $st = db()->prepare('SELECT * FROM usuarios WHERE usuario = ? AND activo = 1');
    $st->execute([mb_strtolower(trim($usuario))]);
    $u = $st->fetch();
    if (!$u || !password_verify($clave, $u['clave_hash'])) {
        return null;
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $u['id'];
    }
    return $u;
}

function nombre_usuario(?int $id): string
{
    if (!$id) {
        return '';
    }
    $st = db()->prepare('SELECT nombre FROM usuarios WHERE id = ?');
    $st->execute([$id]);
    return (string) ($st->fetchColumn() ?: '');
}

/* ---------- Historial del cliente ---------- */

/** Anota algo en el historial (línea de tiempo) del cliente. */
function registrar_evento(int $cliente_id, string $tipo, string $texto, ?int $caso_id = null): void
{
    $u = usuario_actual();
    db()->prepare('INSERT INTO eventos (cliente_id, caso_id, usuario_id, tipo, texto, fecha) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$cliente_id, $caso_id, $u['id'] ?? null, $tipo, $texto, ahora()]);
}

/* ---------- Números, fechas y WhatsApp ---------- */

/** "85,5" o "85.5" → 85.5;  "1.250.000" → 1250000;  "" → null */
function numero_entrada($texto): ?float
{
    $t = trim(str_replace([' ', '$'], '', (string) $texto));
    if ($t === '') {
        return null;
    }
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $t)) {          // miles con punto
        $t = str_replace('.', '', $t);
    } else {
        $t = str_replace(',', '.', $t);
    }
    return is_numeric($t) ? (float) $t : null;
}

function dinero($valor): string
{
    return '$ ' . number_format((float) $valor, 0, ',', '.');
}

/** 85.0 → "85";  85.5 → "85,5" */
function numero_corto($valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }
    $v = round((float) $valor, 1);
    return floor($v) == $v ? (string) (int) $v : str_replace('.', ',', (string) $v);
}

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

/** "2026-11-20" → "20 nov 2026" */
function fecha_corta(?string $fecha): string
{
    if (!$fecha) {
        return '';
    }
    $t = strtotime($fecha);
    return $t ? date('j', $t) . ' ' . MESES[(int) date('n', $t) - 1] . ' ' . date('Y', $t) : '';
}

/** Celular de Colombia en formato internacional sin '+': "311 503 4356" → "573115034356". */
function telefono_whatsapp(string $telefono): string
{
    $d = preg_replace('/\D/', '', $telefono);
    if (strlen($d) === 10 && $d[0] === '3') {
        $d = '57' . $d;
    }
    return $d;
}

/** Enlace que abre WhatsApp con el mensaje ya escrito (la persona solo toca Enviar). */
function enlace_whatsapp(string $telefono, string $texto): string
{
    return 'https://wa.me/' . telefono_whatsapp($telefono) . '?text=' . rawurlencode($texto);
}

/**
 * Rellena una plantilla: mensaje_rellenar('Hola {nombre}', ['nombre' => 'Ana']) → 'Hola Ana'.
 * Los marcadores sin valor se quitan. Marcadores usados: {nombre} {producto} {guia} {transportadora}
 * {total} {enlace} {fecha}.
 */
function mensaje_rellenar(string $texto, array $valores): string
{
    $texto = preg_replace_callback('/\{([a-z_]+)\}/', fn($m) => (string) ($valores[$m[1]] ?? ''), $texto);
    return trim((string) preg_replace('/[ \t]{2,}/', ' ', $texto));
}

/** Texto de la plantilla activa con ese nombre, o '' si no existe. */
function plantilla_texto(string $nombre): string
{
    $st = db()->prepare('SELECT texto FROM plantillas_mensaje WHERE nombre = ? AND activa = 1 ORDER BY id LIMIT 1');
    $st->execute([$nombre]);
    return (string) ($st->fetchColumn() ?: '');
}

function primer_nombre(string $nombre): string
{
    return explode(' ', trim($nombre))[0] ?? '';
}

/* ---------- Íconos ---------- */

/** Trazos de los íconos de línea (estilo Lucide) que usa la app. */
const ICONOS = [
    'inicio'     => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
    'clientes'   => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7"/><path d="M18 14a6.5 6.5 0 0 1 3.5 6"/>',
    'pedidos'    => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    'inventario' => '<path d="m21 8-9-5-9 5v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
    'mensajes'   => '<path d="M21 12a8.5 8.5 0 0 1-12.6 7.4L3 21l1.6-5.2A8.5 8.5 0 1 1 21 12Z"/>',
    'usuarios'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'usuario'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'calendario' => '<rect x="3" y="4.5" width="18" height="16.5" rx="2.5"/><path d="M3 9.5h18"/><path d="M8 2.5v4"/><path d="M16 2.5v4"/>',
    'camion'     => '<path d="M2 6h11v10H2z"/><path d="M13 9h4.5l3.5 3.5V16h-8"/><circle cx="6.5" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
    'alerta'     => '<path d="M12 3 2 20h20L12 3Z"/><path d="M12 10v4.5"/><path d="M12 17.5h.01"/>',
    'mas'        => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    'buscar'     => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'plan'       => '<path d="M7 3h10l3 3v15H4V3h3"/><path d="M8 9h8"/><path d="M8 13h8"/><path d="M8 17h5"/>',
    'peso'       => '<path d="M5 8h14l2 13H3L5 8Z"/><circle cx="12" cy="5" r="2"/>',
    'llave'      => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9"/><path d="m16 7 3 3"/>',
    'flecha'     => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
];

/**
 * Ícono decorativo en SVG: <?= icono('pedidos') ?>. Va junto a un texto, así que es aria-hidden.
 * Para un botón que solo tiene ícono, ponle aria-label al botón.
 */
function icono(string $nombre, string $clase = 'icono'): string
{
    $trazos = ICONOS[$nombre] ?? ICONOS['flecha'];
    return '<svg class="' . e($clase) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $trazos . '</svg>';
}
