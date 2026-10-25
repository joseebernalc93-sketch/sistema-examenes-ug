<?php
// db.php — Conexión a Base de Datos compatible con Vercel / Cloud y desarrollo local
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. Cargar archivo local de credenciales si existe (desarrollo local)
$local_config = __DIR__ . '/db.local.php';
if (file_exists($local_config)) {
    include $local_config;
}

// 2. Si no están definidas, leer variables de entorno (Vercel / Cloud) o valores predeterminados
$DB_HOST = $DB_HOST ?? (getenv('DB_HOST') ?: 'localhost');
$DB_USER = $DB_USER ?? (getenv('DB_USER') ?: 'root');
$DB_PASS = $DB_PASS ?? (getenv('DB_PASS') ?: '');
$DB_NAME = $DB_NAME ?? (getenv('DB_NAME') ?: 'if0_40427719_examenes_sistem');
$DB_PORT = (int)($DB_PORT ?? (getenv('DB_PORT') ?: 3306));

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    ini_set('display_errors', 1);
    error_log('DB ERROR: ' . $e->getMessage());
    die('⚠ Error al conectar a MySQL: ' . $e->getMessage() . '<br>Si estás en Vercel, asegúrate de configurar las variables de entorno (DB_HOST, DB_USER, DB_PASS, DB_NAME).');
}
