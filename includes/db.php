<?php
/**
 * Conexión PDO y migraciones.
 *
 * Las migraciones están en sql/NNN_nombre.sql, escritas en MySQL sencillo, y se aplican solas en orden.
 * Para SQLite (local y pruebas) se traducen las pocas diferencias (AUTO_INCREMENT, ENGINE=...).
 * Para cambiar la base de datos: crea un archivo nuevo sql/NNN_...sql. Nunca edites uno existente.
 * Reglas para que sirva en los dos: sin ENUM, sin DEFAULT en columnas TEXT, sin DEFAULT CURRENT_TIMESTAMP
 * (las fechas las pone PHP con ahora()), un comando por línea terminado en ';' y sin ';' dentro de los textos.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = config('db');
    $dsn = (string) $c['dsn'];
    if (str_starts_with($dsn, 'sqlite:')) {
        $ruta = substr($dsn, 7);
        if ($ruta !== ':memory:' && !is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }
    }
    $pdo = new PDO($dsn, ($c['usuario'] ?? '') ?: null, ($c['clave'] ?? '') ?: null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    if (es_sqlite($pdo)) {
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET time_zone = '-05:00'");
    }
    migrar($pdo);
    return $pdo;
}

function es_sqlite(?PDO $pdo = null): bool
{
    return ($pdo ?? db())->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
}

function migrar(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS migraciones (nombre VARCHAR(100) NOT NULL PRIMARY KEY, fecha DATETIME NOT NULL)');
    $hechas = $pdo->query('SELECT nombre FROM migraciones')->fetchAll(PDO::FETCH_COLUMN);
    $archivos = glob(RAIZ . '/sql/*.sql') ?: [];
    sort($archivos, SORT_STRING);
    foreach ($archivos as $archivo) {
        $nombre = basename($archivo);
        if (in_array($nombre, $hechas, true)) {
            continue;
        }
        foreach (sql_comandos((string) file_get_contents($archivo), es_sqlite($pdo)) as $comando) {
            $pdo->exec($comando);
        }
        $pdo->prepare('INSERT INTO migraciones (nombre, fecha) VALUES (?, ?)')->execute([$nombre, ahora()]);
    }
}

/** Separa un archivo .sql en comandos y, si es SQLite, traduce lo propio de MySQL. */
function sql_comandos(string $sql, bool $sqlite): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    if ($sqlite) {
        $sql = preg_replace('/\)\s*ENGINE\s*=[^;]*;/i', ');', $sql);
        $sql = str_ireplace('AUTO_INCREMENT', 'AUTOINCREMENT', $sql);
        $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);
    }
    $partes = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    return array_values(array_filter(array_map('trim', $partes), fn($p) => $p !== ''));
}
