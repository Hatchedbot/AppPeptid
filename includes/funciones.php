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
            $st = db()->prepare('SELECT id, nombre, usuario, rol, activo FROM usuarios WHERE id = ?');
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
    return $u['rol'] === 'admin' || in_array($u['rol'], PERMISOS[$permiso] ?? [], true);
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
