<?php
// db.php — Conexión a Base de Datos Híbrida Inteligente (MySQL nativo / PostgreSQL Supabase)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Helper para leer variables de entorno en cualquier contexto (Vercel, CLI, Apache)
function get_db_env($key, $default = null) {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    return $default;
}

$is_vercel = !empty(get_db_env('VERCEL'));

// 1. Cargar archivo local de credenciales si existe (únicamente en desarrollo local, nunca en Vercel)
if (!$is_vercel) {
    $local_config = __DIR__ . '/db.local.php';
    if (file_exists($local_config)) {
        include $local_config;
    }
}

// 2. Leer variables de entorno (Vercel / Cloud) o valores predeterminados
$DB_TYPE = $DB_TYPE ?? get_db_env('DB_TYPE', 'mysql');
$DB_HOST = $DB_HOST ?? get_db_env('DB_HOST', 'localhost');
$DB_USER = $DB_USER ?? get_db_env('DB_USER', 'root');
$DB_PASS = $DB_PASS ?? get_db_env('DB_PASS', '');
$DB_NAME = $DB_NAME ?? get_db_env('DB_NAME', 'sistema_examenes_ug');
$DB_PORT = (int)($DB_PORT ?? get_db_env('DB_PORT', 3306));
$DB_SSL  = isset($DB_SSL) ? (bool)$DB_SSL : (get_db_env('DB_SSL') === 'true' || get_db_env('DB_SSL') === '1');

// Detección automática de Supabase / PostgreSQL
$es_supabase = (
    strtolower($DB_TYPE) === 'pgsql' ||
    strtolower($DB_TYPE) === 'postgres' ||
    strtolower($DB_TYPE) === 'supabase' ||
    $DB_PORT === 5432 ||
    $DB_PORT === 6543 ||
    stripos($DB_HOST, 'supabase.co') !== false ||
    stripos($DB_HOST, 'supabase.com') !== false
);

if ($es_supabase) {
    require_once __DIR__ . '/db_supabase_adapter.php';
    try {
        $conn = new SupabasePdoAdapter($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT ?: 5432);
    } catch (Throwable $e) {
        ini_set('display_errors', 1);
        error_log('SUPABASE DB ERROR: ' . $e->getMessage());
        die('⚠ Error al conectar a Supabase (PostgreSQL): ' . $e->getMessage() . '<br>Host: ' . htmlspecialchars($DB_HOST) . ':' . $DB_PORT . '<br>Revisa las variables de entorno DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT en Vercel.');
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

// ==========================================
// PERSISTENCIA INTELIGENTE DE SESIÓN (SERVERLESS / VERCEL)
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('generarTokenAuthUG')) {
    function generarTokenAuthUG($usuario_id, $password_hash) {
        $secret = 'UG_SECRET_KEY_EXAMENES_2026_SEGURIDAD';
        $payload = $usuario_id . '|' . substr($password_hash, 0, 10);
        $signature = hash_hmac('sha256', $payload, $secret);
        return base64_encode($payload . '|' . $signature);
    }
}

if (!function_exists('verificarTokenAuthUG')) {
    function verificarTokenAuthUG($token) {
        $secret = 'UG_SECRET_KEY_EXAMENES_2026_SEGURIDAD';
        $decoded = base64_decode($token, true);
        if (!$decoded) return null;
        $parts = explode('|', $decoded);
        if (count($parts) !== 3) return null;
        list($uid, $pass_sub, $sig) = $parts;
        $payload = $uid . '|' . $pass_sub;
        $expected_sig = hash_hmac('sha256', $payload, $secret);
        if (!hash_equals($expected_sig, $sig)) return null;
        return ['uid' => (int)$uid, 'pass_sub' => $pass_sub];
    }
}

if (!function_exists('asegurarSesionRestaurada')) {
    function asegurarSesionRestaurada($conn) {
        if (!$conn) return;

        // Si la sesión está vacía en este contenedor serverless, restaurar desde cookie firmada
        if (empty($_SESSION['usuario']) && !empty($_COOKIE['auth_ug'])) {
            $data = verificarTokenAuthUG($_COOKIE['auth_ug']);
            if ($data && $data['uid'] > 0) {
                $uid = (int)$data['uid'];
                $res = $conn->query("SELECT * FROM usuarios WHERE id = $uid AND estado = 'aceptado' LIMIT 1");
                if ($res && $res->num_rows > 0) {
                    $user = $res->fetch_assoc();
                    if (substr($user['password'], 0, 10) === $data['pass_sub']) {
                        $_SESSION['usuario'] = $user;
                    }
                }
            }
        }

        // Si el usuario ya tiene sesión activa pero la cookie no está o está por expirar, renovarla
        if (!empty($_SESSION['usuario']['id']) && !empty($_SESSION['usuario']['password'])) {
            if (empty($_COOKIE['auth_ug']) && !headers_sent()) {
                $token = generarTokenAuthUG($_SESSION['usuario']['id'], $_SESSION['usuario']['password']);
                $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
                @setcookie('auth_ug', $token, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'secure' => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
        }
    }
}

if (!function_exists('asegurarColumnasProgreso')) {
    function asegurarColumnasProgreso($conn) {
        static $checked = false;
        if ($checked || !$conn) return;
        $checked = true;
        try {
            $check1 = $conn->query("SHOW COLUMNS FROM resultados_examen LIKE 'tiempos_respuestas_json'");
            if (!$check1 || $check1->num_rows == 0) {
                @$conn->query("ALTER TABLE resultados_examen ADD COLUMN tiempos_respuestas_json TEXT DEFAULT NULL");
            }
            $check2 = $conn->query("SHOW COLUMNS FROM resultados_examen LIKE 'pregunta_actual'");
            if (!$check2 || $check2->num_rows == 0) {
                @$conn->query("ALTER TABLE resultados_examen ADD COLUMN pregunta_actual INT DEFAULT 1");
            }
        } catch (Throwable $e) {
            // Silencioso si ya existen o no soportado
        }
    }
}

if (isset($conn)) {
    asegurarSesionRestaurada($conn);
    asegurarColumnasProgreso($conn);
}

