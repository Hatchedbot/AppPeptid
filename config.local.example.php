<?php
/**
 * Copia este archivo como config.local.php y llena solo lo que cambie.
 * config.local.php NO se sube a git: aquí van contraseñas y claves.
 */
return [
    'app' => [
        'url' => 'https://seguimiento.tudominio.com',
    ],
    // MySQL de Hostinger (hPanel → Bases de datos)
    'db' => [
        'dsn'     => 'mysql:host=localhost;dbname=u123456789_brdg;charset=utf8mb4',
        'usuario' => 'u123456789_brdg',
        'clave'   => 'CONTRASEÑA_DE_LA_BASE',
    ],
    // IA gratuita: https://aistudio.google.com/apikey y https://console.groq.com/keys
    'ia' => [
        'proveedores' => [
            'gemini' => ['clave' => 'TU_CLAVE_DE_GEMINI'],
            'groq'   => ['clave' => 'TU_CLAVE_DE_GROQ'],
        ],
    ],
];
