<?php
/**
 * Pruebas sin dependencias.   php tests/run.php            → revisa la sintaxis de todo y corre todas las pruebas
 *                             php tests/run.php clientes   → solo tests/test_clientes.php
 * Usa una base SQLite en memoria (nunca toca datos reales).
 *
 * Para escribir pruebas: crea tests/test_<modulo>.php con
 *     prueba('lo que se comprueba', function () { igual(2, 1 + 1); });
 */
putenv('APP_DB_DSN=sqlite::memory:');
require dirname(__DIR__) . '/includes/app.php';

$filtro = $argv[1] ?? '';
$fallos = 0;

// 1. Sintaxis de todos los .php
if ($filtro === '') {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(RAIZ, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $ruta = str_replace('\\', '/', $f->getPathname());
        if ($f->getExtension() !== 'php' || preg_match('#/(\.git|\.claude|datos|node_modules)/#', $ruta)) {
            continue;
        }
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($ruta) . ' 2>&1', $salida, $codigo);
        if ($codigo !== 0) {
            $fallos++;
            echo "ERROR DE SINTAXIS: $ruta\n" . implode("\n", $salida) . "\n";
        }
        $salida = [];
    }
}

// 2. Pruebas
$GLOBALS['PRUEBAS'] = [];

function prueba(string $nombre, callable $f): void
{
    $GLOBALS['PRUEBAS'][] = [$GLOBALS['ARCHIVO_PRUEBA'], $nombre, $f];
}

function igual($esperado, $real, string $mensaje = ''): void
{
    if ($esperado !== $real) {
        throw new RuntimeException(($mensaje ? "$mensaje: " : '') . 'se esperaba ' . var_export($esperado, true)
            . ' y llegó ' . var_export($real, true));
    }
}

function verdadero($valor, string $mensaje = 'debía ser verdadero'): void
{
    if (!$valor) {
        throw new RuntimeException($mensaje);
    }
}

function contiene(string $aguja, string $pajar, string $mensaje = ''): void
{
    if (!str_contains($pajar, $aguja)) {
        throw new RuntimeException(($mensaje ? "$mensaje: " : '') . "no contiene «$aguja»");
    }
}

/** Crea un usuario y lo deja como el usuario actual. */
function usuario_prueba(string $rol = 'asesor'): array
{
    $n = count($GLOBALS['PRUEBAS']) . '_' . token_aleatorio(3);
    $id = crear_usuario("Prueba $rol", "prueba_$n", 'clave-de-prueba', $rol);
    $GLOBALS['USUARIO_FORZADO'] = ['id' => $id, 'nombre' => "Prueba $rol", 'usuario' => "prueba_$n", 'rol' => $rol, 'activo' => 1];
    return $GLOBALS['USUARIO_FORZADO'];
}

foreach (glob(__DIR__ . '/test_*.php') as $archivo) {
    if ($filtro !== '' && basename($archivo) !== "test_$filtro.php") {
        continue;
    }
    $GLOBALS['ARCHIVO_PRUEBA'] = basename($archivo);
    require $archivo;
}

$bien = 0;
foreach ($GLOBALS['PRUEBAS'] as [$archivo, $nombre, $f]) {
    unset($GLOBALS['USUARIO_FORZADO']);
    try {
        $f();
        $bien++;
    } catch (Throwable $ex) {
        $fallos++;
        echo "FALLA  $archivo › $nombre\n       " . $ex->getMessage() . ' (' . basename($ex->getFile()) . ':' . $ex->getLine() . ")\n";
    }
}

echo "\n$bien pruebas bien, $fallos con error.\n";
exit($fallos ? 1 : 0);
