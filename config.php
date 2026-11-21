<?php
/**
 * Configuración por defecto. Lo propio de cada instalación (base de datos de Hostinger, claves de IA)
 * va en config.local.php, que no se sube a git. Ver config.local.example.php.
 */
$config = [
    'app' => [
        'nombre'  => 'BRDG Seguimiento',
        'negocio' => 'BRDG Peptides Colombia',
        'url'     => '',                 // ej. https://app.dominio.com (vacío = se deduce de la petición)
        'zona'    => 'America/Bogota',
    ],
    // Local y pruebas: SQLite. Producción (Hostinger): MySQL, se define en config.local.php.
    'db' => [
        'dsn'     => 'sqlite:' . __DIR__ . '/datos/app.sqlite',
        'usuario' => '',
        'clave'   => '',
    ],
    'ia' => [
        'limite_diario' => 200,
        'orden' => ['gemini', 'groq'],
        'proveedores' => [
            'gemini' => [
                'url'     => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
                'clave'   => getenv('GEMINI_API_KEY') ?: '',
                'modelos' => ['gemini-3.6-flash', 'gemini-3.5-flash-lite', 'gemini-3.8-flash'],
            ],
            'groq' => [
                'url'     => 'https://api.groq.com/openai/v1/chat/completions',
                'clave'   => getenv('GROQ_API_KEY') ?: '',
                'modelos' => ['openai/gpt-oss-120b'],
            ],
        ],
    ],
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace_recursive($config, (array) require __DIR__ . '/config.local.php');
}
if (getenv('APP_DB_DSN')) {
    $config['db']['dsn'] = getenv('APP_DB_DSN');
}
return $config;
