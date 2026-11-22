<?php
/**
 * Router del servidor de PHP para local y demo: C:\xampp\php\php.exe -S localhost:8000 router.php
 * Bloquea lo privado (base de datos, contraseñas, código interno, git) para que nadie lo descargue,
 * sobre todo cuando la app se abre al público con un túnel de Cloudflare. En Hostinger hace lo mismo .htaccess.
 */
$ruta = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

$privado = preg_match('#(^|/)\.#', $ruta)                                                  // .git, .env, .claude...
    || preg_match('#^/(datos|includes|sql|tests|herramientas)(/|$)#i', $ruta)                // carpetas internas
    || preg_match('#\.(sqlite|sqlite3|db|md|log|txt|sql|ini|sh|ps1|json)$#i', $ruta)          // archivos de datos o notas
    || preg_match('#^/(config(\.local)?(\.example)?\.php|router\.php)$#i', $ruta);

if ($privado && !preg_match('#^/manifest\.webmanifest$#', $ruta)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('No encontrado');
}
return false;   // todo lo demás lo sirve PHP como siempre
