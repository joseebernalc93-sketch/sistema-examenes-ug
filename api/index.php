<?php
// Enrutador para Vercel Serverless PHP
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = trim($path, '/');

// Si la ruta está vacía, cargar index.php
if ($path === '' || $path === 'index' || $path === 'index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

// Comprobar si el archivo PHP existe en la raíz o subdirectorios
$target = realpath(__DIR__ . '/../' . $path);
$base_dir = realpath(__DIR__ . '/../');

if ($target && strpos($target, $base_dir) === 0 && file_exists($target) && !is_dir($target) && pathinfo($target, PATHINFO_EXTENSION) === 'php') {
    require $target;
    exit;
}

// Si se solicita sin la extensión .php
$target_php = realpath(__DIR__ . '/../' . $path . '.php');
if ($target_php && strpos($target_php, $base_dir) === 0 && file_exists($target_php)) {
    require $target_php;
    exit;
}

// Por defecto, cargar index.php
if (file_exists(__DIR__ . '/../index.php')) {
    require __DIR__ . '/../index.php';
    exit;
}

http_response_code(404);
echo "404 Not Found";
