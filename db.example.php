<?php
// db.example.php — Plantilla de configuración de base de datos
// Renombra o copia este archivo como 'db.php' y coloca tus credenciales reales.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'if0_40427719_examenes_sistem';
$DB_PORT = 3306;

try {
    $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    ini_set('display_errors', 1);
    error_log('DB ERROR: ' . $e->getMessage());
    die('⚠ Error al conectar a MySQL: ' . $e->getMessage());
}
