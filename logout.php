<?php
require __DIR__ . '/includes/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION = [];
    session_destroy();
}
redirigir('login.php');
