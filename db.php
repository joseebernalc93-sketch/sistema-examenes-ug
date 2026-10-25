<?php
// db.php — Conexión a Base de Datos Híbrida Inteligente (MySQL nativo / PostgreSQL Supabase)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// 1. Cargar archivo local de credenciales si existe (desarrollo local)
$local_config = __DIR__ . '/db.local.php';
if (file_exists($local_config)) {
    include $local_config;
}

// 2. Si no están definidas, leer variables de entorno (Vercel / Cloud) o valores predeterminados
$DB_TYPE = $DB_TYPE ?? (getenv('DB_TYPE') ?: 'mysql');
$DB_HOST = $DB_HOST ?? (getenv('DB_HOST') ?: 'localhost');
$DB_USER = $DB_USER ?? (getenv('DB_USER') ?: 'root');
$DB_PASS = $DB_PASS ?? (getenv('DB_PASS') ?: '');
$DB_NAME = $DB_NAME ?? (getenv('DB_NAME') ?: 'sistema_examenes_ug');
$DB_PORT = (int)($DB_PORT ?? (getenv('DB_PORT') ?: 3306));
$DB_SSL  = isset($DB_SSL) ? (bool)$DB_SSL : (getenv('DB_SSL') === 'true' || getenv('DB_SSL') === '1');

// Detección automática de Supabase / PostgreSQL
$es_supabase = (
    strtolower($DB_TYPE) === 'pgsql' ||
    strtolower($DB_TYPE) === 'postgres' ||
    strtolower($DB_TYPE) === 'supabase' ||
    $DB_PORT === 5432 ||
    $DB_PORT === 6543 ||
    stripos($DB_HOST, 'supabase.co') !== false
);

if ($es_supabase) {
    require_once __DIR__ . '/db_supabase_adapter.php';
    try {
        $conn = new SupabasePdoAdapter($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT ?: 5432);
    } catch (Throwable $e) {
        ini_set('display_errors', 1);
        error_log('SUPABASE DB ERROR: ' . $e->getMessage());
        die('⚠ Error al conectar a Supabase (PostgreSQL): ' . $e->getMessage() . '<br>Revisa las variables de entorno DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT en Vercel.');
    }
} else {
    try {
        $conn = mysqli_init();
        if ($DB_SSL) {
            $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        }
        $conn->real_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT, NULL, $DB_SSL ? MYSQLI_CLIENT_SSL : 0);
        $conn->set_charset('utf8mb4');
    } catch (Throwable $e) {
        ini_set('display_errors', 1);
        error_log('MYSQL DB ERROR: ' . $e->getMessage());
        die('⚠ Error al conectar a la base de datos MySQL: ' . $e->getMessage() . '<br>Si estás en Vercel, asegúrate de configurar las variables de entorno (DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT).');
    }
}
