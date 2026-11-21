<?php
/**
 * Arranque común: cada página empieza con   require __DIR__ . '/includes/app.php';
 * Carga la configuración, la base de datos (con migraciones automáticas) y las funciones.
 * En el navegador además abre la sesión y exige el token CSRF en todo POST.
 */
declare(strict_types=1);

define('RAIZ', dirname(__DIR__));
$GLOBALS['CONFIG'] = require RAIZ . '/config.php';

require __DIR__ . '/funciones.php';
require __DIR__ . '/db.php';

date_default_timezone_set((string) config('app.zona', 'America/Bogota'));

if (PHP_SAPI !== 'cli') {
    iniciar_sesion();
    verificar_csrf();
}
