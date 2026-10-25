<?php
// ==========================================
// 1. CONFIGURACIÓN, SEGURIDAD Y FUNCIONES (CORE)
// ==========================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include "db.php"; // Asegúrate de que db.php exista en el mismo directorio

date_default_timezone_set('America/Guayaquil');

// --- SEGURIDAD DE SESIÓN ---
if (!isset($_SESSION["usuario"])) { header("Location: index.php"); exit; }

$rol_sesion     = $_SESSION["usuario"]["rol"] ?? '';
$usuario_id     = (int)$_SESSION["usuario"]["id"];
$usuario_nombre = htmlspecialchars($_SESSION["usuario"]["nombres"]);
$es_admin       = ($rol_sesion === 'administrador');
$es_docente     = ($rol_sesion === 'docente' || $rol_sesion === 'director_carrera' || $rol_sesion === 'director_area');
$es_director_carrera = ($rol_sesion === 'director_carrera');
$es_director_area    = ($rol_sesion === 'director_area');

if (!$es_docente && !$es_admin) { header("Location: index.php"); exit; }

// --- VERIFICAR CAMBIO DE CONTRASEÑA ---
$q_user = $conn->query("SELECT cedula, password FROM usuarios WHERE id = $usuario_id LIMIT 1");
$datos_user = $q_user->fetch_assoc();
$cedula_real = $datos_user['cedula'];
$pass_actual_db = $datos_user['password'];

$es_clave_temporal = ($pass_actual_db === md5($cedula_real));
$mensaje_pass = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_clave_docente'])) {
    $actual = $_POST['clave_actual'];
    $nueva  = $_POST['nueva_clave'];
    $conf   = $_POST['conf_clave'];

    if (md5($actual) !== $pass_actual_db) {
        $mensaje_pass = "<div class='alert-error'>❌ La contraseña actual es incorrecta.</div>";
    } elseif ($nueva !== $conf) {
        $mensaje_pass = "<div class='alert-error'>❌ Las nuevas contraseñas no coinciden.</div>";
    } elseif (strlen($nueva) < 4) {
        $mensaje_pass = "<div class='alert-error'>❌ La contraseña es muy corta (mínimo 4 caracteres).</div>";
    } elseif ($nueva === $cedula_real) {
        $mensaje_pass = "<div class='alert-error'>❌ No puedes usar tu cédula nuevamente.</div>";
    } else {
        $nueva_hash = md5($nueva);
        $conn->query("UPDATE usuarios SET password = '$nueva_hash' WHERE id = $usuario_id");
        $pass_actual_db = $nueva_hash; 
        $es_clave_temporal = false; 
        $mensaje_pass = "<div class='alert-success'>✅ ¡Contraseña actualizada correctamente!</div>";
    }
}

// PANTALLA DE BLOQUEO (CAMBIO DE CLAVE)
if ($es_clave_temporal) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="icon" href="favicon.ico"><link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png"><link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png"><title>Seguridad - Docente</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f3f4f6;height:100vh;display:flex;align-items:center;justify-content:center;margin:0}.card{background:white;padding:40px;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);width:100%;max-width:400px}input{width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:4px;box-sizing:border-box}button{width:100%;padding:10px;background:#0f6cb6;color:white;border:none;border-radius:4px;cursor:pointer;font-weight:bold}button:hover{background:#0d5a9e}.alert-error{background:#fee2e2;color:#991b1b;padding:10px;border-radius:4px;margin-bottom:10px;font-size:0.9rem}.alert-success{background:#d1fae5;color:#065f46;padding:10px;border-radius:4px;margin-bottom:10px;font-size:0.9rem}</style></head><body><div class="card"><h2 style="margin-top:0;text-align:center;color:#333">🔐 Cambio Obligatorio</h2><p style="color:#666;font-size:0.9rem;text-align:center">Por seguridad, actualiza tu contraseña.</p>'.$mensaje_pass.'<form method="POST"><input type="password" name="clave_actual" placeholder="Contraseña actual (Cédula)" required><input type="password" name="nueva_clave" placeholder="Nueva contraseña" required><input type="password" name="conf_clave" placeholder="Confirmar nueva contraseña" required><button type="submit" name="cambiar_clave_docente">Guardar y Continuar</button></form><a href="logout.php" style="display:block;text-align:center;margin-top:15px;color:#d9534f;text-decoration:none;font-size:0.9rem">Salir</a></div></body></html>';
    exit;
}

// --- VARIABLES GLOBALES VISUALES ---
$nombre_partes = explode(' ', $usuario_nombre);
$primer_nombre = $nombre_partes[0];
$iniciales     = strtoupper(substr($nombre_partes[0], 0, 1));
if (isset($nombre_partes[1])) $iniciales .= strtoupper(substr($nombre_partes[1], 0, 1));

$rol_display = 'Docente';
if ($es_director_carrera) $rol_display = 'Director de Carrera';
elseif ($es_director_area) $rol_display = 'Director de Área';

$mensaje_sistema = "";
if (isset($_SESSION['mensaje_flash'])) {
    $mensaje_sistema = $_SESSION['mensaje_flash'];
    unset($_SESSION['mensaje_flash']);
}

// --- FUNCIONES Y MIGRACIONES ---
function verificarColumnasDB($conn) {
    $checkP = $conn->query("SHOW COLUMNS FROM preguntas LIKE 'imagen'");
    if ($checkP && $checkP->num_rows == 0) { $conn->query("ALTER TABLE preguntas ADD COLUMN imagen LONGTEXT DEFAULT NULL"); }
    $checkO = $conn->query("SHOW COLUMNS FROM opciones_pregunta LIKE 'imagen'");
    if ($checkO && $checkO->num_rows == 0) { $conn->query("ALTER TABLE opciones_pregunta ADD COLUMN imagen LONGTEXT DEFAULT NULL"); }
    $checkA = $conn->query("SHOW TABLES LIKE 'areas_materias'");
    if ($checkA && $checkA->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS `areas_materias` (`id` int(11) NOT NULL AUTO_INCREMENT, `nombre_materia` varchar(255) NOT NULL, `nombre_area` varchar(255) NOT NULL, `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `idx_materia_unica` (`nombre_materia`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
    $checkParcial = $conn->query("SHOW COLUMNS FROM examenes LIKE 'parcial'");
    if ($checkParcial && $checkParcial->num_rows == 0) { $conn->query("ALTER TABLE examenes ADD COLUMN parcial ENUM('primer_parcial','segundo_parcial') DEFAULT 'primer_parcial' AFTER titulo"); }
    $checkInicioProg = $conn->query("SHOW COLUMNS FROM examenes LIKE 'inicio_programado'");
    if ($checkInicioProg && $checkInicioProg->num_rows == 0) { $conn->query("ALTER TABLE examenes ADD COLUMN inicio_programado DATETIME DEFAULT NULL AFTER fecha_fin"); }
    $checkSecuencial = $conn->query("SHOW COLUMNS FROM examenes LIKE 'preguntas_secuenciales'");
    if ($checkSecuencial && $checkSecuencial->num_rows == 0) { $conn->query("ALTER TABLE examenes ADD COLUMN preguntas_secuenciales TINYINT(1) DEFAULT 0 AFTER inicio_programado"); }
    $checkPuntaje = $conn->query("SHOW COLUMNS FROM preguntas LIKE 'valor_puntos'");
    if ($checkPuntaje && $checkPuntaje->num_rows == 0) { $conn->query("ALTER TABLE preguntas ADD COLUMN valor_puntos DECIMAL(5,2) DEFAULT 1.00 AFTER imagen"); }
    $checkMN = $conn->query("SHOW COLUMNS FROM examenes LIKE 'mostrar_nota'");
    if ($checkMN && $checkMN->num_rows == 0) { $conn->query("ALTER TABLE examenes ADD COLUMN mostrar_nota TINYINT(1) DEFAULT 1"); }
    $checkPR = $conn->query("SHOW COLUMNS FROM examenes LIKE 'permitir_revision'");
    if ($checkPR && $checkPR->num_rows == 0) { $conn->query("ALTER TABLE examenes ADD COLUMN permitir_revision TINYINT(1) DEFAULT 1"); }
}
verificarColumnasDB($conn);

function verificarTablaAplicaciones($conn) {
    $checkAppTable = $conn->query("SHOW TABLES LIKE 'aplicaciones_docente'");
    if ($checkAppTable && $checkAppTable->num_rows == 0) {
        $conn->query("CREATE TABLE `aplicaciones_docente` (`id` INT(11) NOT NULL AUTO_INCREMENT, `docente_id` INT(11) NOT NULL, `nombre_archivo` VARCHAR(255) NOT NULL, `nombre_original` VARCHAR(255) NOT NULL, `fecha_subida` DATETIME DEFAULT CURRENT_TIMESTAMP, `version` VARCHAR(50) DEFAULT '1.0', `activo` TINYINT(1) DEFAULT 1, PRIMARY KEY (`id`), KEY `idx_docente` (`docente_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}
verificarTablaAplicaciones($conn);

function verificarTablaMensajes($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'mensajes_chat'");
    if ($check && $check->num_rows == 0) {
        $conn->query("CREATE TABLE `mensajes_chat` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `curso_id` int(11) NOT NULL,
            `examen_id` int(11) DEFAULT NULL,
            `docente_id` int(11) NOT NULL,
            `estudiante_id` int(11) NOT NULL,
            `emisor` enum('docente','estudiante') NOT NULL,
            `mensaje` text NOT NULL,
            `es_temporal` tinyint(1) DEFAULT 0,
            `fecha_creacion` datetime DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_mensajes_curso` (`curso_id`),
            KEY `idx_mensajes_docente` (`docente_id`),
            KEY `idx_mensajes_estudiante` (`estudiante_id`),
            KEY `idx_mensajes_examen` (`examen_id`),
            KEY `idx_mensajes_fecha` (`fecha_creacion`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}
verificarTablaMensajes($conn);

function verificarTablaNotificacionesSeguridad($conn) {
    $check = $conn->query("SHOW TABLES LIKE 'notificaciones_seguridad_examen'");
    if ($check && $check->num_rows == 0) {
        $conn->query("CREATE TABLE `notificaciones_seguridad_examen` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `docente_id` int(11) NOT NULL,
            `curso_id` int(11) NOT NULL,
            `examen_id` int(11) NOT NULL,
            `estudiante_id` int(11) NOT NULL,
            `tipo` varchar(80) NOT NULL DEFAULT 'salida_video_externa',
            `motivo` text NOT NULL,
            `oculto_docente` tinyint(1) NOT NULL DEFAULT 0,
            `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (`id`),
            KEY `idx_notif_docente_fecha` (`docente_id`,`fecha_creacion`),
            KEY `idx_notif_curso_fecha` (`curso_id`,`fecha_creacion`),
            KEY `idx_notif_examen` (`examen_id`),
            KEY `idx_notif_estudiante` (`estudiante_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    $checkOculto = $conn->query("SHOW COLUMNS FROM notificaciones_seguridad_examen LIKE 'oculto_docente'");
    if ($checkOculto && $checkOculto->num_rows == 0) {
        $conn->query("ALTER TABLE notificaciones_seguridad_examen ADD COLUMN oculto_docente TINYINT(1) NOT NULL DEFAULT 0 AFTER motivo");
    }
}
verificarTablaNotificacionesSeguridad($conn);

function verificarExamenesProgramados($conn) {
    $ahora = date('Y-m-d H:i:s');
    $sql = "SELECT id, inicio_programado, fecha_fin FROM examenes WHERE estado IN ('pendiente', 'programado') AND inicio_programado IS NOT NULL AND inicio_programado <= '$ahora'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($ex = $result->fetch_assoc()) {
            $fecha_inicio = $ex['inicio_programado'];
            $conn->query("UPDATE examenes SET estado='activo', fecha_inicio='$fecha_inicio' WHERE id={$ex['id']}");
        }
    }
}
function cerrarExamenesCaducados($conn) {
    $ahora = date('Y-m-d H:i:s');
    $conn->query("UPDATE examenes SET estado='cerrado' WHERE estado = 'activo' AND fecha_fin IS NOT NULL AND fecha_fin < '$ahora'");
}
verificarExamenesProgramados($conn);
cerrarExamenesCaducados($conn);

function redirigirConMensaje($msg, $curso_id = null) {
    $_SESSION['mensaje_flash'] = $msg;
    $url = basename($_SERVER['PHP_SELF']);
    if ($curso_id) { $url .= "?view_curso=" . intval($curso_id); }
    header("Location: " . $url);
    exit;
}

function esc($s){ global $conn; return $conn->real_escape_string(trim($s)); }

function normalizar_token_nombre($t) {
    $map = [
        "\xC3\xA1" => 'a', "\xC3\xA9" => 'e', "\xC3\xAD" => 'i', "\xC3\xB3" => 'o', "\xC3\xBA" => 'u',
        "\xC3\xB1" => 'n', "\xC3\xBC" => 'u', "\xC3\xA4" => 'a', "\xC3\xAB" => 'e', "\xC3\xAF" => 'i', "\xC3\xB6" => 'o'
    ];
    $t = mb_strtolower(trim((string)$t), 'UTF-8');
    $t = strtr($t, $map);
    return preg_replace('/[^a-z]/', '', $t);
}

// Reordena a "Apellidos Apellidos Nombres..." cuando el archivo viene como "Nombres Nombres Apellidos"
function ordenar_apellidos_primero($nombreCompleto, $email = '') {
    $nombre = preg_replace('/\s+/', ' ', trim((string)$nombreCompleto));
    if ($nombre === '') return '';

    $partes = explode(' ', $nombre);
    if (count($partes) < 3) return $nombre;

    $correo_local = '';
    if (!empty($email)) {
        $email = trim($email);
        $correo_local = strtolower(strtok($email, '@'));
    }
    $correo_token  = $correo_local ? normalizar_token_nombre(explode('.', $correo_local)[0] ?? $correo_local) : '';
    $primer_token  = normalizar_token_nombre($partes[0]);
    $tercer_token  = normalizar_token_nombre($partes[2] ?? '');
    $es_nombres_primero = ($correo_token && $primer_token === $correo_token && $primer_token !== $tercer_token);

    if ($es_nombres_primero) {
        $num_apellidos = (count($partes) >= 4) ? 2 : 1;
        $apellidos = array_slice($partes, -$num_apellidos);
        $nombres   = array_slice($partes, 0, count($partes) - $num_apellidos);
        return implode(' ', array_merge($apellidos, $nombres));
    }

    return $nombre;
}

function obtener_area_materia($nombre_materia) {
    global $conn;
    $nombre = esc(strtoupper(trim($nombre_materia)));
    $q = $conn->query("SELECT nombre_area FROM areas_materias WHERE UPPER(nombre_materia) = '$nombre' LIMIT 1");
    if ($q && $q->num_rows > 0) return $q->fetch_assoc()['nombre_area'];
    return null;
}

function obtener_areas_usuario($usuario_id) {
    global $conn; $usuario_id = (int)$usuario_id; $areas = [];
    $sql = "SELECT DISTINCT m.nombre AS materia FROM usuario_cursos uc JOIN cursos_oferta co ON uc.curso_id = co.id JOIN paralelos p ON co.paralelo_id = p.id JOIN materias m ON p.materia_id = m.id WHERE uc.usuario_id = $usuario_id";
    $res = $conn->query($sql);
    if ($res) { while ($row = $res->fetch_assoc()) { $area = obtener_area_materia($row['materia']); if ($area && !in_array($area, $areas)) $areas[] = $area; } }
    return $areas;
}

function obtener_area_cargo_director($usuario_id) {
    global $conn; $usuario_id = (int)$usuario_id; $areas = [];
    $sql = "SELECT area FROM usuarios WHERE id = $usuario_id AND area IS NOT NULL AND area != ''";
    $res = $conn->query($sql);
    if ($res && $row = $res->fetch_assoc()) { if (!empty($row['area'])) $areas[] = $row['area']; }
    return $areas;
}

function registrar_log($actor_id, $actor_nombre, $rol, $accion, $entidad, $id_afectado, $detalles){
    global $conn;
    $actor_nombre = esc($actor_nombre); $rol = esc($rol); $accion = esc($accion); $entidad = esc($entidad); $detalles = esc($detalles);
    $id_afectado = (int)$id_afectado; $actor_id = (int)$actor_id; $fecha_local = date('Y-m-d H:i:s');
    $conn->query("INSERT INTO auditoria_log (actor_id, actor_nombre, rol_actor, accion, entidad_afectada, id_afectado, detalles, fecha_registro) VALUES ($actor_id, '$actor_nombre', '$rol', '$accion', '$entidad', $id_afectado, '$detalles', '$fecha_local')");
}

function obtenerCursoPorExamen($examen_id) {
    global $conn; $eid = (int)$examen_id;
    $q = $conn->query("SELECT curso_id FROM examenes WHERE id = $eid LIMIT 1");
    if ($q && $row = $q->fetch_assoc()) return $row['curso_id'];
    return null;
}

// --- DESCARGAR EXAMEN WORD ---
if (isset($_GET['download_doc'])) {
    $eid = (int)$_GET['download_doc'];
    $sql_permiso = "SELECT e.archivo_origen FROM examenes e JOIN usuario_cursos uc ON e.curso_id = uc.curso_id WHERE e.id = $eid AND uc.usuario_id = $usuario_id";
    $res = $conn->query($sql_permiso);

    if ($res && $row = $res->fetch_assoc()) {
        $file_path = __DIR__ . '/uploads/examen_' . $eid . '.docx';
        $original_name = $row['archivo_origen'] ?: 'examen.docx';
        if (file_exists($file_path)) {
            if (ob_get_level()) ob_end_clean();
            header('Content-Description: File Transfer');
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment; filename="'.basename($original_name).'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Content-Length: ' . filesize($file_path));
            readfile($file_path);
            exit;
        } else { $_SESSION['mensaje_flash'] = "<div class='alert-error'>El archivo físico no se encuentra.</div>"; }
    } else { $_SESSION['mensaje_flash'] = "<div class='alert-error'>No tienes permiso.</div>"; }
    header("Location: " . basename($_SERVER['PHP_SELF'])); exit;
}

// --- AJAX HANDLERS ---
if (isset($_POST['action'])) {
    ini_set('display_errors', 0);
    error_reporting(0);
    header('Content-Type: application/json');
    
    // =========================================
    // MODIFICAR TIEMPO EN TIEMPO REAL
    // =========================================
    if ($_POST['action'] === 'contar_preguntas_docx') {
        if (!isset($_FILES['docx_temp']) || $_FILES['docx_temp']['error'] !== 0) {
            echo json_encode(['success' => false, 'error' => 'Archivo no recibido']);
            exit;
        }
        $tmp = $_FILES['docx_temp']['tmp_name'];
        $total = contarPreguntasDocx($tmp);
        echo json_encode(['success' => true, 'total_preguntas' => $total]);
        exit;
    }

    if ($_POST['action'] === 'modificar_tiempo_real') {
        $examen_id = (int)($_POST['examen_id'] ?? 0);
        $minutos = (int)($_POST['minutos'] ?? 0);
        
        if ($examen_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de examen requerido']);
            exit;
        }
        
        if ($minutos == 0) {
            echo json_encode(['success' => false, 'error' => 'Debe especificar minutos a agregar o reducir']);
            exit;
        }
        
        // Verificar que el docente tenga permiso sobre este examen
        $sql_permiso = "SELECT e.id, e.fecha_fin, e.duracion_minutos, e.estado, e.titulo
                        FROM examenes e 
                        JOIN usuario_cursos uc ON e.curso_id = uc.curso_id 
                        WHERE e.id = $examen_id AND uc.usuario_id = $usuario_id";
        $res_permiso = $conn->query($sql_permiso);
        
        if (!$res_permiso || $res_permiso->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'No tienes permiso para modificar este examen']);
            exit;
        }
        
        $examen = $res_permiso->fetch_assoc();
        
        // Verificar que el examen esté activo
        if ($examen['estado'] !== 'activo') {
            echo json_encode(['success' => false, 'error' => 'El examen no está activo. Solo se puede modificar el tiempo de exámenes activos.']);
            exit;
        }
        
        // Calcular nueva fecha_fin
        $fecha_fin_actual = new DateTime($examen['fecha_fin']);
        $fecha_fin_actual->modify("{$minutos} minutes");
        $nueva_fecha_fin = $fecha_fin_actual->format('Y-m-d H:i:s');
        
        // Calcular nueva duración
        $duracion_actual = (int)$examen['duracion_minutos'];
        $nueva_duracion = max(1, $duracion_actual + $minutos); // Mínimo 1 minuto
        
        // Validar que la nueva fecha no sea en el pasado
        $ahora = new DateTime();
        if ($fecha_fin_actual < $ahora && $minutos < 0) {
            echo json_encode(['success' => false, 'error' => 'No se puede reducir el tiempo a una fecha pasada. El examen finalizaría inmediatamente.']);
            exit;
        }
        
        // Actualizar en la base de datos
        $sql_update = "UPDATE examenes SET fecha_fin = '$nueva_fecha_fin', duracion_minutos = $nueva_duracion WHERE id = $examen_id";
        
        if ($conn->query($sql_update)) {
            // Contar estudiantes afectados (los que están rindiendo)
            $sql_rindiendo = "SELECT COUNT(*) as cantidad FROM resultados_examen WHERE examen_id = $examen_id AND puntaje_total = -1";
            $res_rindiendo = $conn->query($sql_rindiendo);
            $estudiantes_afectados = $res_rindiendo->fetch_assoc()['cantidad'] ?? 0;
            
            // Registrar en el log
            $accion = $minutos > 0 ? 'AGREGAR_TIEMPO' : 'REDUCIR_TIEMPO';
            $detalle = ($minutos > 0 ? "Agregó" : "Redujo") . " " . abs($minutos) . " minuto(s). Nueva fecha fin: $nueva_fecha_fin. Estudiantes rindiendo: $estudiantes_afectados";
            registrar_log($usuario_id, $usuario_nombre, 'docente', $accion, 'Examen', $examen_id, $detalle);
            
            echo json_encode([
                'success' => true,
                'nueva_fecha_fin' => $nueva_fecha_fin,
                'nueva_duracion' => $nueva_duracion,
                'estudiantes_afectados' => $estudiantes_afectados,
                'mensaje' => ($minutos > 0 ? "Se agregaron" : "Se redujeron") . " " . abs($minutos) . " minuto(s) al examen"
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al actualizar la base de datos: ' . $conn->error]);
        }
        exit;
    }
    
    // =========================================
    // CONTAR ESTUDIANTES RINDIENDO
    // =========================================
    if ($_POST['action'] === 'contar_estudiantes_rindiendo') {
        $examen_id = (int)($_POST['examen_id'] ?? 0);
        
        if ($examen_id <= 0) {
            echo json_encode(['cantidad' => 0]);
            exit;
        }
        
        $sql = "SELECT COUNT(*) as cantidad FROM resultados_examen WHERE examen_id = $examen_id AND puntaje_total = -1";
        $res = $conn->query($sql);
        $cantidad = $res->fetch_assoc()['cantidad'] ?? 0;
        
        echo json_encode(['cantidad' => $cantidad]);
        exit;
    }

    // =========================================
    // NOTIFICACIONES DE SEGURIDAD (DOCENTE)
    // =========================================
    if ($_POST['action'] === 'notificaciones_seguridad_docente') {
        $curso_id = (int)($_POST['curso_id'] ?? 0);
        if ($curso_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Curso requerido']);
            exit;
        }

        $perm = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $usuario_id AND curso_id = $curso_id LIMIT 1");
        if (!$perm || $perm->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este curso']);
            exit;
        }

        $items = [];
        $sql = "SELECT ns.id, ns.curso_id, ns.examen_id, ns.estudiante_id, ns.tipo, ns.motivo, ns.fecha_creacion,
                       u.nombres AS estudiante_nombre, u.cedula AS estudiante_cedula,
                       e.titulo AS examen_titulo
                FROM notificaciones_seguridad_examen ns
                LEFT JOIN usuarios u ON ns.estudiante_id = u.id
                LEFT JOIN examenes e ON ns.examen_id = e.id
                WHERE ns.curso_id = $curso_id
                AND ns.docente_id = $usuario_id
                AND IFNULL(ns.oculto_docente, 0) = 0
                ORDER BY ns.fecha_creacion DESC
                LIMIT 500";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
        }

        echo json_encode(['success' => true, 'items' => $items]);
        exit;
    }

    if ($_POST['action'] === 'ocultar_notificaciones_seguridad_docente') {
        $curso_id = (int)($_POST['curso_id'] ?? 0);
        if ($curso_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Curso requerido']);
            exit;
        }

        $perm = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $usuario_id AND curso_id = $curso_id LIMIT 1");
        if (!$perm || $perm->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este curso']);
            exit;
        }

        $nombres_estudiantes = [];
        $sql_est = "SELECT DISTINCT u.nombres
                    FROM notificaciones_seguridad_examen ns
                    LEFT JOIN usuarios u ON ns.estudiante_id = u.id
                    WHERE ns.curso_id = $curso_id
                    AND ns.docente_id = $usuario_id
                    AND IFNULL(ns.oculto_docente, 0) = 0
                    ORDER BY u.nombres ASC";
        $res_est = $conn->query($sql_est);
        if ($res_est) {
            while ($row_est = $res_est->fetch_assoc()) {
                $nom = trim((string)($row_est['nombres'] ?? ''));
                if ($nom !== '') $nombres_estudiantes[] = $nom;
            }
        }

        $sql = "UPDATE notificaciones_seguridad_examen
                SET oculto_docente = 1
                WHERE curso_id = $curso_id
                AND docente_id = $usuario_id
                AND IFNULL(oculto_docente, 0) = 0";
        $conn->query($sql);
        $afectadas = (int)$conn->affected_rows;

        $detalle = "Oculto $afectadas alerta(s) de seguridad.";
        if (!empty($nombres_estudiantes)) {
            $vista = array_slice($nombres_estudiantes, 0, 4);
            $extra = count($nombres_estudiantes) - count($vista);
            $detalle .= " Estudiante(s): " . implode(', ', $vista);
            if ($extra > 0) $detalle .= " y $extra mas";
        }

        registrar_log(
            $usuario_id,
            $usuario_nombre,
            'docente',
            'OCULTAR_ALERTAS_SEG',
            'Seguridad',
            $curso_id,
            $detalle
        );

        echo json_encode(['success' => true, 'ocultadas' => $afectadas]);
        exit;
    }

    // =========================================
    // MENSAJERIA DOCENTE
    // =========================================
    if ($_POST['action'] === 'mensajeria_resumen_docente') {
        $curso_id = (int)($_POST['curso_id'] ?? 0);
        if ($curso_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Curso requerido']);
            exit;
        }

        $perm = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $usuario_id AND curso_id = $curso_id LIMIT 1");
        if (!$perm || $perm->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este curso']);
            exit;
        }

        $sql = "SELECT u.id AS estudiante_id,
                       last_msg.id AS ultimo_id,
                       last_msg.fecha_creacion AS ultima_fecha,
                       last_msg.emisor AS ultimo_emisor
                FROM usuario_cursos uc
                JOIN usuarios u ON u.id = uc.usuario_id AND u.rol = 'estudiante'
                LEFT JOIN (
                    SELECT mc.estudiante_id, mc.id, mc.fecha_creacion, mc.emisor
                    FROM mensajes_chat mc
                    JOIN (
                        SELECT estudiante_id, MAX(id) AS max_id
                        FROM mensajes_chat
                        WHERE curso_id = $curso_id
                        AND docente_id = $usuario_id
                        GROUP BY estudiante_id
                    ) lm ON lm.estudiante_id = mc.estudiante_id AND lm.max_id = mc.id
                ) last_msg ON last_msg.estudiante_id = u.id
                WHERE uc.curso_id = $curso_id";
        $res = $conn->query($sql);
        $resumen = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $resumen[] = $row;
            }
        }

        echo json_encode(['success' => true, 'resumen' => $resumen]);
        exit;
    }

    if ($_POST['action'] === 'mensajeria_listar_docente') {
        $curso_id = (int)($_POST['curso_id'] ?? 0);
        $estudiante_id = (int)($_POST['estudiante_id'] ?? 0);
        
        if ($curso_id <= 0 || $estudiante_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }
        
        $perm = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $usuario_id AND curso_id = $curso_id LIMIT 1");
        if (!$perm || $perm->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este curso']);
            exit;
        }
        
        $est = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $estudiante_id AND curso_id = $curso_id LIMIT 1");
        if (!$est || $est->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Estudiante no pertenece al curso']);
            exit;
        }
        
        $sql = "SELECT id, emisor, mensaje, fecha_creacion 
                FROM mensajes_chat 
                WHERE curso_id = $curso_id 
                AND docente_id = $usuario_id 
                AND estudiante_id = $estudiante_id
                ORDER BY fecha_creacion ASC, id ASC
                LIMIT 200";
        $res = $conn->query($sql);
        $mensajes = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $mensajes[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'mensajes' => $mensajes]);
        exit;
    }
    
    if ($_POST['action'] === 'mensajeria_enviar_docente') {
        $curso_id = (int)($_POST['curso_id'] ?? 0);
        $estudiante_id = (int)($_POST['estudiante_id'] ?? 0);
        $mensaje_raw = trim($_POST['mensaje'] ?? '');
        
        if ($curso_id <= 0 || $estudiante_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }
        if ($mensaje_raw === '') {
            echo json_encode(['success' => false, 'error' => 'Mensaje vacio']);
            exit;
        }
        if (strlen($mensaje_raw) > 500) {
            $mensaje_raw = substr($mensaje_raw, 0, 500);
        }
        
        $perm = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $usuario_id AND curso_id = $curso_id LIMIT 1");
        if (!$perm || $perm->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este curso']);
            exit;
        }
        
        $mensaje_safe = $conn->real_escape_string($mensaje_raw);
        $fecha = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO mensajes_chat 
                (curso_id, examen_id, docente_id, estudiante_id, emisor, mensaje, es_temporal, fecha_creacion) 
                VALUES ($curso_id, NULL, $usuario_id, $estudiante_id, 'docente', '$mensaje_safe', 0, '$fecha')";
        
        if ($conn->query($sql)) {
            echo json_encode([
                'success' => true,
                'mensaje' => [
                    'id' => $conn->insert_id,
                    'emisor' => 'docente',
                    'mensaje' => $mensaje_raw,
                    'fecha_creacion' => $fecha
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo enviar el mensaje']);
        }
        exit;
    }

    if ($_POST['action'] === 'mensajeria_broadcast_docente') {
        $curso_id = (int)($_POST['curso_id'] ?? 0);
        $mensaje_raw = trim($_POST['mensaje'] ?? '');

        if ($curso_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Curso requerido']);
            exit;
        }
        if ($mensaje_raw === '') {
            echo json_encode(['success' => false, 'error' => 'Mensaje vacio']);
            exit;
        }
        if (strlen($mensaje_raw) > 500) {
            $mensaje_raw = substr($mensaje_raw, 0, 500);
        }

        $perm = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $usuario_id AND curso_id = $curso_id LIMIT 1");
        if (!$perm || $perm->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este curso']);
            exit;
        }

        $res = $conn->query("SELECT u.id AS estudiante_id
                             FROM usuario_cursos uc
                             JOIN usuarios u ON u.id = uc.usuario_id AND u.rol = 'estudiante'
                             WHERE uc.curso_id = $curso_id");
        $estudiantes = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $estudiantes[] = (int)$row['estudiante_id'];
            }
        }
        if (empty($estudiantes)) {
            echo json_encode(['success' => false, 'error' => 'No hay estudiantes en el curso']);
            exit;
        }

        $mensaje_safe = $conn->real_escape_string($mensaje_raw);
        $fecha = date('Y-m-d H:i:s');
        $conn->begin_transaction();
        $ok = true;
        foreach ($estudiantes as $estudiante_id) {
            $sql = "INSERT INTO mensajes_chat
                    (curso_id, examen_id, docente_id, estudiante_id, emisor, mensaje, es_temporal, fecha_creacion)
                    VALUES ($curso_id, NULL, $usuario_id, $estudiante_id, 'docente', '$mensaje_safe', 0, '$fecha')";
            if (!$conn->query($sql)) {
                $ok = false;
                break;
            }
        }
        if ($ok) {
            $conn->commit();
            echo json_encode(['success' => true, 'enviados' => count($estudiantes)]);
        } else {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => 'No se pudo enviar el mensaje masivo']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'mensajeria_eliminar_docente') {
        $mensaje_id = (int)($_POST['mensaje_id'] ?? 0);
        if ($mensaje_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID de mensaje requerido']);
            exit;
        }
        
        $sql = "DELETE FROM mensajes_chat WHERE id = $mensaje_id AND docente_id = $usuario_id AND emisor = 'docente'";
        $conn->query($sql);
        $eliminados = $conn->affected_rows;
        
        if ($eliminados > 0) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo eliminar el mensaje']);
        }
        exit;
    }

    if ($_POST['action'] === 'mensajeria_eliminar_chat_docente') {
        $curso_id = (int)($_POST['curso_id'] ?? 0);
        $estudiante_id = (int)($_POST['estudiante_id'] ?? 0);
        if ($curso_id <= 0 || $estudiante_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
            exit;
        }

        $perm = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $usuario_id AND curso_id = $curso_id LIMIT 1");
        if (!$perm || $perm->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este curso']);
            exit;
        }

        $est = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $estudiante_id AND curso_id = $curso_id LIMIT 1");
        if (!$est || $est->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Estudiante no pertenece al curso']);
            exit;
        }

        $sql = "DELETE FROM mensajes_chat 
                WHERE curso_id = $curso_id 
                AND docente_id = $usuario_id 
                AND estudiante_id = $estudiante_id";
        $conn->query($sql);

        echo json_encode(['success' => true, 'eliminados' => $conn->affected_rows]);
        exit;
    }
    
    if ($_POST['action'] === 'mensajeria_eliminar_todos_docente') {
        $curso_id = (int)($_POST['curso_id'] ?? 0);
        if ($curso_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Curso requerido']);
            exit;
        }

        $perm = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id = $usuario_id AND curso_id = $curso_id LIMIT 1");
        if (!$perm || $perm->num_rows == 0) {
            echo json_encode(['success' => false, 'error' => 'Sin permiso para este curso']);
            exit;
        }

        $sql = "DELETE FROM mensajes_chat WHERE curso_id = $curso_id AND docente_id = $usuario_id";
        $conn->query($sql);
        echo json_encode(['success' => true, 'eliminados' => $conn->affected_rows]);
        exit;
    }
    
    if ($_POST['action'] === 'ver_banco_preguntas') {
        $ex_id = (int)$_POST['examen_id'];
        $sql_preg = "SELECT p.id as pid, p.enunciado, p.imagen as p_img, p.valor_puntos, op.id as oid, op.contenido, op.imagen as op_img, op.es_correcta FROM preguntas p JOIN opciones_pregunta op ON p.id = op.pregunta_id WHERE p.examen_id = $ex_id ORDER BY p.id, op.id";
        $res_p = $conn->query($sql_preg);
        $examen_data = [];
        while($row = $res_p->fetch_assoc()){
            $pid = $row['pid'];
            if(!isset($examen_data[$pid])) { $examen_data[$pid] = ['id' => $pid, 'enunciado' => $row['enunciado'], 'imagen' => $row['p_img'], 'valor' => $row['valor_puntos'], 'opciones' => []]; }
            $examen_data[$pid]['opciones'][] = ['id' => $row['oid'], 'contenido' => $row['contenido'], 'imagen' => $row['op_img'], 'es_correcta' => $row['es_correcta']];
        }
        echo json_encode(array_values($examen_data)); exit;
    }
    if ($_POST['action'] === 'actualizar_puntaje_pregunta') {
        $pregunta_id = (int)$_POST['pregunta_id']; $nuevo_puntaje = floatval($_POST['puntaje']);
        if ($pregunta_id > 0 && $nuevo_puntaje >= 0) {
            $conn->query("UPDATE preguntas SET valor_puntos = $nuevo_puntaje WHERE id = $pregunta_id");
            echo json_encode(['success' => true]);
        } else { echo json_encode(['success' => false]); }
        exit;
    }
    if ($_POST['action'] === 'cargar_revision_examen') {
        $ex_id = (int)$_POST['examen_id']; $est_id = (int)$_POST['estudiante_id'];
        $respuestas_est = []; $orden_ids = []; $dist_puntajes = [];
        $sql_res = "SELECT respuestas_json, orden_preguntas, distribucion_puntajes_json FROM resultados_examen WHERE examen_id = $ex_id AND estudiante_id = $est_id LIMIT 1";
        $res_r = $conn->query($sql_res);
        if($res_r && $row = $res_r->fetch_assoc()){
            if (!empty($row['respuestas_json'])) $respuestas_est = json_decode($row['respuestas_json'], true);
            if (!empty($row['orden_preguntas'])) {
                $json_orden = json_decode($row['orden_preguntas'], true);
                $orden_ids = is_array($json_orden) ? $json_orden : explode(',', $row['orden_preguntas']);
                $orden_ids = array_filter($orden_ids, 'is_numeric');
            }
            if (!empty($row['distribucion_puntajes_json'])) {
                $dist_decoded = json_decode($row['distribucion_puntajes_json'], true);
                if (is_array($dist_decoded)) { $dist_puntajes = $dist_decoded; }
            }
        }
        $where = "p.examen_id = $ex_id"; $order = "p.id, op.id";
        if (!empty($orden_ids)) { $ids_str = implode(',', array_map('intval', $orden_ids)); if(!empty($ids_str)){ $where = "p.id IN ($ids_str)"; $order = "FIELD(p.id, $ids_str), op.id"; } }
        $sql_preg = "SELECT p.id as pid, p.enunciado, p.imagen as p_img, p.valor_puntos, op.id as oid, op.contenido, op.imagen as op_img, op.es_correcta FROM preguntas p JOIN opciones_pregunta op ON p.id = op.pregunta_id WHERE $where ORDER BY $order";
        $res_p = $conn->query($sql_preg);
        $examen_data = [];
        while($row = $res_p->fetch_assoc()){
            $pid = $row['pid'];
            if(!isset($examen_data[$pid])) { 
                $examen_data[$pid] = [
                    'id' => $pid, 
                    'enunciado' => $row['enunciado'], 
                    'imagen' => $row['p_img'], 
                    'valor' => $row['valor_puntos'], 
                    'puntaje_asignado' => null,
                    'correcta_id' => null,
                    'opciones' => []
                ]; 
            }
            if ($examen_data[$pid]['puntaje_asignado'] === null) {
                $clave_pid = null;
                if (isset($dist_puntajes[(string)$pid])) { $clave_pid = (string)$pid; }
                else if (isset($dist_puntajes[$pid])) { $clave_pid = $pid; }
                if ($clave_pid !== null) { $examen_data[$pid]['puntaje_asignado'] = (float)$dist_puntajes[$clave_pid]; }
            }
            if($row['es_correcta']) { $examen_data[$pid]['correcta_id'] = $row['oid']; }
            $examen_data[$pid]['opciones'][] = ['id' => $row['oid'], 'contenido' => $row['contenido'], 'imagen' => $row['op_img'], 'es_correcta' => $row['es_correcta']];
        }

        foreach ($examen_data as $pid => &$info) {
            $puntaje_asignado = (isset($info['puntaje_asignado']) && $info['puntaje_asignado'] !== null) ? floatval($info['puntaje_asignado']) : floatval($info['valor']);
            $resp = isset($respuestas_est[$pid]) ? $respuestas_est[$pid] : null;
            $correcta = isset($info['correcta_id']) ? $info['correcta_id'] : null;
            $info['puntaje_asignado'] = $puntaje_asignado;
            $info['puntaje_obtenido'] = ($resp !== null && $correcta !== null && $resp == $correcta) ? $puntaje_asignado : 0;
        }
        unset($info);

        echo json_encode(['preguntas' => array_values($examen_data), 'respuestas' => $respuestas_est]); exit;
    }
    if ($_POST['action'] === 'corregir_respuesta_global') {
        $pid = (int)$_POST['pregunta_id']; $oid = (int)$_POST['opcion_id']; $eid = (int)$_POST['examen_id'];
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE opciones_pregunta SET es_correcta = 0 WHERE pregunta_id = $pid");
            $conn->query("UPDATE opciones_pregunta SET es_correcta = 1 WHERE id = $oid");
            $puntos_map = []; $res_pts = $conn->query("SELECT id, valor_puntos FROM preguntas WHERE examen_id = $eid");
            while($r = $res_pts->fetch_assoc()){ $puntos_map[$r['id']] = floatval($r['valor_puntos']); }
            $correctas_map = []; $res_corr = $conn->query("SELECT pregunta_id, id FROM opciones_pregunta WHERE pregunta_id IN (SELECT id FROM preguntas WHERE examen_id = $eid) AND es_correcta = 1");
            while($r = $res_corr->fetch_assoc()){ $correctas_map[$r['pregunta_id']] = $r['id']; }
            $res_est = $conn->query("SELECT id, orden_preguntas, respuestas_json FROM resultados_examen WHERE examen_id = $eid");
            while($row = $res_est->fetch_assoc()){
                $respuestas = json_decode($row['respuestas_json'], true) ?? [];
                $orden = !empty($row['orden_preguntas']) ? (json_decode($row['orden_preguntas'], true) ?? explode(',', $row['orden_preguntas'])) : array_keys($puntos_map);
                $max_score = 0; foreach ($orden as $p_id) { if (isset($puntos_map[$p_id])) $max_score += $puntos_map[$p_id]; }
                $puntaje = 0;
                foreach ($respuestas as $p_id => $o_id) {
                    if (in_array($p_id, $orden) && isset($correctas_map[$p_id]) && $correctas_map[$p_id] == $o_id) {
                        $puntaje += isset($puntos_map[$p_id]) ? $puntos_map[$p_id] : 0;
                    }
                }
                $nota = ($max_score > 0) ? ($puntaje / $max_score) * 10 : 0;
                $nota_fmt = number_format($nota, 2);
                $conn->query("UPDATE resultados_examen SET puntaje_total = '$nota_fmt' WHERE id = {$row['id']}");
            }
            $conn->commit();
            echo json_encode(['success' => true, 'msg' => 'Corregido y recalculado.']);
        } catch (Exception $e) { $conn->rollback(); echo json_encode(['success' => false, 'msg' => $e->getMessage()]); }
        exit;
    }
}

function procesarDocx($archivoTmp, $examenId, $conn) {
    $zip = new ZipArchive;
    if ($zip->open($archivoTmp) === TRUE) {
        // Cargar relaciones para imágenes
        $relations = []; 
        $xmlRels = $zip->getFromName('word/_rels/document.xml.rels');
        if ($xmlRels) { 
            $domRels = new DOMDocument(); 
            $domRels->loadXML($xmlRels); 
            foreach ($domRels->getElementsByTagName('Relationship') as $rel) { 
                $relations[$rel->getAttribute('Id')] = $rel->getAttribute('Target'); 
            } 
        }
        
        $xmlContent = $zip->getFromName('word/document.xml');
        if (!$xmlContent) { $zip->close(); return "Error: Archivo dañado."; }
        
        $dom = new DOMDocument(); 
        libxml_use_internal_errors(true); 
        $dom->loadXML($xmlContent); 
        libxml_clear_errors();
        
        // Registrar namespaces para XPath
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        
        $paragraphs = $dom->getElementsByTagName('p'); 
        if ($paragraphs->length == 0) $paragraphs = $dom->getElementsByTagNameNS('*', 'p');
        
        // Recolectar todos los párrafos con su texto e imágenes
        $contenidoParagrafos = [];
        foreach ($paragraphs as $p) {
            $texto = $p->textContent;
            $texto = preg_replace('/[\x{00AD}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $texto);
            $texto = preg_replace('/[\x00-\x1F\x7F\xA0]/u', ' ', $texto);
            $texto = trim(preg_replace('/\s+/', ' ', $texto));
            
            // Buscar imágenes con namespace (a:blip)
            $currentImg = null;
            $blips = $xpath->query('.//a:blip', $p);
            /** @var DOMElement $b */
            foreach ($blips as $b) { 
                if (!($b instanceof DOMElement)) { continue; }
                // Intentar obtener r:embed de múltiples formas
                $rId = $b->getAttribute('r:embed');
                if (empty($rId)) {
                    $rId = $b->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
                }
                if (!empty($rId) && isset($relations[$rId])) { 
                    $imgPath = 'word/' . $relations[$rId]; 
                    $imgData = $zip->getFromName($imgPath); 
                    if ($imgData) { 
                        $base64 = base64_encode($imgData); 
                        $ext = strtolower(pathinfo($relations[$rId], PATHINFO_EXTENSION));
                        $mimeType = ($ext === 'png') ? 'image/png' : (($ext === 'gif') ? 'image/gif' : 'image/jpeg');
                        $currentImg = "data:$mimeType;base64,$base64"; 
                        break; // Solo tomar la primera imagen del párrafo
                    } 
                } 
            }
            
            if (!empty($texto) || $currentImg) {
                $contenidoParagrafos[] = ['texto' => $texto, 'imagen' => $currentImg];
            }
        }
        
        $zip->close();
        
        // Procesar contenido para extraer preguntas
        $preguntas = [];
        $preguntaActual = null;
        
        foreach ($contenidoParagrafos as $contenido) {
            $texto = $contenido['texto'];
            $currentImg = $contenido['imagen'];
            
            // CASO 1: Pregunta con número y opciones concatenadas
            // Formato: "1. Pregunta?A) op1B) op2C) op3D) op4RESPUESTA: X"
            if (preg_match('/^(\d+)\.\s*(.+?)(?<!\p{L})([A-Ea-e]\)\s*.+(?:(?<!\p{L})[A-Ea-e]\)|RESPUESTA))/su', $texto, $matchCompleto)) {
                // Guardar pregunta anterior
                if ($preguntaActual && !empty($preguntaActual['enunciado']) && count($preguntaActual['opciones']) > 0) {
                    $preguntas[] = $preguntaActual;
                }
                
                $enunciado = trim($matchCompleto[2]);
                $parteOpciones = $matchCompleto[3];
                
                $preguntaActual = ['enunciado' => $enunciado, 'opciones' => [], 'correcta' => '', 'imagen' => $currentImg];
                
                // Extraer opciones del texto concatenado
                // Patrón mejorado para separar opciones
                preg_match_all('/(?<!\p{L})([A-Ea-e])\)\s*(.+?)(?=(?:[A-Ea-e]\)|RESPUESTA|$))/iu', $parteOpciones, $matchesOpciones, PREG_SET_ORDER);
                foreach ($matchesOpciones as $mo) {
                    $textoOpcion = trim($mo[2]);
                    // Limpiar RESPUESTA si está pegado
                    $textoOpcion = preg_replace('/RESPUESTA.*$/i', '', $textoOpcion);
                    $textoOpcion = trim($textoOpcion);
                    if (!empty($textoOpcion)) {
                        $preguntaActual['opciones'][] = ['indice' => strtolower($mo[1]), 'texto' => $textoOpcion, 'imagen' => null];
                    }
                }
                
                // Extraer respuesta correcta
                if (preg_match('/RESPUESTA[:\s]*([A-Ea-e])/i', $parteOpciones, $matchResp)) {
                    $preguntaActual['correcta'] = strtolower($matchResp[1]);
                }
                continue;
            }
            
            // CASO 2: Pregunta con número al inicio (formato tradicional)
            if (preg_match('/^(\d+)\.\s*(.+)/', $texto, $m)) {
                if ($preguntaActual && !empty($preguntaActual['enunciado']) && count($preguntaActual['opciones']) > 0) {
                    $preguntas[] = $preguntaActual;
                }
                $preguntaActual = ['enunciado' => trim($m[2]), 'opciones' => [], 'correcta' => '', 'imagen' => $currentImg];
                continue;
            }
            
            // CASO 3: Opción individual (A), B), etc.)
            if (preg_match('/^([A-Ea-e])\)\s*(.*)/', $texto, $m)) {
                if ($preguntaActual) {
                    $preguntaActual['opciones'][] = ['indice' => strtolower($m[1]), 'texto' => trim($m[2]), 'imagen' => $currentImg];
                }
                continue;
            }
            
            // CASO 4: Respuesta correcta
            if (preg_match('/^RESPUESTA[:\s]*([A-Ea-e])/i', $texto, $m)) {
                if ($preguntaActual) {
                    $preguntaActual['correcta'] = strtolower($m[1]);
                }
                continue;
            }
            
            // CASO 5: Pregunta sin número pero con opciones concatenadas
            if (preg_match('/^(.+?)(?<!\p{L})([A-Ea-e]\)\s*.+(?:(?<!\p{L})[A-Ea-e]\)|RESPUESTA))/su', $texto, $matchMixto)) {
                $partePregunta = trim($matchMixto[1]);
                $parteOpciones = $matchMixto[2];
                
                if (strlen($partePregunta) > 15 || strpos($partePregunta, '?') !== false) {
                    if ($preguntaActual && !empty($preguntaActual['enunciado']) && count($preguntaActual['opciones']) > 0) {
                        $preguntas[] = $preguntaActual;
                    }
                    $preguntaActual = ['enunciado' => $partePregunta, 'opciones' => [], 'correcta' => '', 'imagen' => $currentImg];
                    
                    preg_match_all('/(?<!\p{L})([A-Ea-e])\)\s*([^A-Ea-e\)]+?)(?=(?:[A-Ea-e]\)|RESPUESTA|$))/iu', $parteOpciones, $matchesOpciones, PREG_SET_ORDER);
                    foreach ($matchesOpciones as $mo) {
                        $textoOpcion = preg_replace('/RESPUESTA.*$/i', '', trim($mo[2]));
                        if (!empty($textoOpcion)) {
                            $preguntaActual['opciones'][] = ['indice' => strtolower($mo[1]), 'texto' => $textoOpcion, 'imagen' => null];
                        }
                    }
                    
                    if (preg_match('/RESPUESTA[:\s]*([A-Ea-e])/i', $parteOpciones, $matchResp)) {
                        $preguntaActual['correcta'] = strtolower($matchResp[1]);
                    }
                }
                continue;
            }
            
            // CASO 6: Solo imagen (asignar a pregunta o última opción)
            if ($currentImg && $preguntaActual) {
                if (count($preguntaActual['opciones']) > 0) {
                    $lastIdx = count($preguntaActual['opciones']) - 1;
                    if (empty($preguntaActual['opciones'][$lastIdx]['imagen'])) {
                        $preguntaActual['opciones'][$lastIdx]['imagen'] = $currentImg;
                    }
                } elseif (empty($preguntaActual['imagen'])) {
                    $preguntaActual['imagen'] = $currentImg;
                }
            }
        }
        
        // Guardar última pregunta
        if ($preguntaActual && !empty($preguntaActual['enunciado']) && count($preguntaActual['opciones']) > 0) {
            $preguntas[] = $preguntaActual;
        }
        
        // Guardar en base de datos
        $preguntasGuardadas = 0;
        foreach ($preguntas as $preg) {
            guardarPregunta($examenId, $preg, $preg['correcta'], $conn);
            $preguntasGuardadas++;
        }
        
        return ($preguntasGuardadas > 0) ? true : "Error: No se detectaron preguntas. Formato esperado: 1. Pregunta A) Opción B) Opción RESPUESTA: A";
    } 
    return "Error: No se pudo abrir el archivo Word.";
}
function guardarPregunta($eid, $d, $correcta, $conn) {
    $enum = $conn->real_escape_string($d['enunciado']); $img = $d['imagen'] ? "'" . $conn->real_escape_string($d['imagen']) . "'" : "NULL";
    $conn->query("INSERT INTO preguntas (examen_id, enunciado, valor_puntos, imagen) VALUES ($eid, '$enum', 1.00, $img)");
    $pid = $conn->insert_id;
    foreach ($d['opciones'] as $op) {
        $txt = $conn->real_escape_string($op['texto']); $idx = $conn->real_escape_string($op['indice']); $imgOp = !empty($op['imagen']) ? "'" . $conn->real_escape_string($op['imagen']) . "'" : "NULL";
        $esC = (strcasecmp(trim($op['indice']), trim($correcta)) == 0) ? 1 : 0;
        $conn->query("INSERT INTO opciones_pregunta (pregunta_id, contenido, es_correcta, indice, imagen) VALUES ($pid, '$txt', $esC, '$idx', $imgOp)");
    }
}
function normalize_header($h){ $h = trim($h); $h = mb_strtolower($h, 'UTF-8'); $h = strtr($h, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']); return preg_replace('/[\s\-_]+/', '', $h); }
function detect_delimiter($l){ $c = [','=>0,';'=>0,"\t"=>0,'|'=>0]; foreach ($c as $d=>$_) $c[$d] = substr_count($l, $d); arsort($c); return (array_key_first($c)); }
function read_csv_rows($p){ $r=[]; $fp=fopen($p,'r'); if(!$fp)return $r; $f=fgets($fp); if(!$f){fclose($fp);return $r;} $d=detect_delimiter($f); $h=array_map('normalize_header',str_getcsv($f,$d)); while(($l=fgets($fp))!==false){ $c=str_getcsv($l,$d); if(count($c)==1&&trim($c[0])==='')continue; $rw=[]; foreach($h as $i=>$k){ $rw[$k]=$c[$i]??''; } $r[]=$rw; } fclose($fp); return $r; }
function read_xlsx_rows($p){ $r=[]; $z=new ZipArchive(); if($z->open($p)!==true)return $r; $s=[]; $idx=$z->locateName('xl/sharedStrings.xml'); if($idx!==false){ $x=$z->getFromIndex($idx); $d=new DOMDocument(); $d->loadXML($x); foreach($d->getElementsByTagName('si') as $si){ $t=''; foreach($si->getElementsByTagName('t') as $tx)$t.=$tx->nodeValue; $s[]=trim($t); } } $sn=['xl/worksheets/sheet1.xml','xl/worksheets/worksheet1.xml']; $sx=null; foreach($sn as $n){ $i=$z->locateName($n); if($i!==false){ $sx=$z->getFromIndex($i); break; } } if(!$sx){ $z->close(); return $r; } $dx=new DOMDocument(); $dx->loadXML($sx); $tr=[]; foreach($dx->getElementsByTagName('row') as $rw){ $cl=[]; foreach($rw->getElementsByTagName('c') as $c){ $t=$c->getAttribute('t'); $v=$c->getElementsByTagName('v')->item(0)?->nodeValue??''; if($t==='s') $v=$s[(int)$v]??''; elseif($t==='inlineStr') $v=$c->getElementsByTagName('t')->item(0)?->nodeValue??''; $cl[]=trim($v); } if(array_filter($cl)) $tr[]=$cl; } $z->close(); if(empty($tr))return $r; $h=array_map('normalize_header',$tr[0]); for($i=1;$i<count($tr);$i++){ $rw=[]; foreach($h as $j=>$k) $rw[$k]=$tr[$i][$j]??''; $r[]=$rw; } return $r; }

// Contar preguntas en un DOCX (sin guardar en DB)
function contarPreguntasDocx($archivoTmp) {
    $zip = new ZipArchive;
    if ($zip->open($archivoTmp) !== TRUE) return 0;

    $relations = [];
    $xmlRels = $zip->getFromName('word/_rels/document.xml.rels');
    if ($xmlRels) {
        $domRels = new DOMDocument();
        $domRels->loadXML($xmlRels);
        foreach ($domRels->getElementsByTagName('Relationship') as $rel) {
            $relations[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
    }

    $xmlContent = $zip->getFromName('word/document.xml');
    if (!$xmlContent) { $zip->close(); return 0; }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadXML($xmlContent);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

    $paragraphs = $dom->getElementsByTagName('p');
    if ($paragraphs->length == 0) $paragraphs = $dom->getElementsByTagNameNS('*', 'p');

    $contenidoParagrafos = [];
    foreach ($paragraphs as $p) {
        $texto = $p->textContent;
        $texto = preg_replace('/[\x{00AD}\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $texto);
        $texto = preg_replace('/[\x00-\x1F\x7F\xA0]/u', ' ', $texto);
        $texto = trim(preg_replace('/\s+/', ' ', $texto));

        $currentImg = null;
        $blips = $xpath->query('.//a:blip', $p);
        foreach ($blips as $b) {
            /** @var DOMElement $b */
            $rId = $b->getAttribute('r:embed');
            if (empty($rId)) {
                $rId = $b->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed');
            }
            if (!empty($rId) && isset($relations[$rId])) {
                $imgPath = 'word/' . $relations[$rId];
                if ($zip->getFromName($imgPath)) { $currentImg = true; break; }
            }
        }

        if (!empty($texto) || $currentImg) {
            $contenidoParagrafos[] = ['texto' => $texto, 'imagen' => $currentImg];
        }
    }
    $zip->close();

    $preguntas = [];
    $preguntaActual = null;

    foreach ($contenidoParagrafos as $contenido) {
        $texto = $contenido['texto'];

        if (preg_match('/^(\d+)\.\s*(.+?)(?<!\p{L})([A-Ea-e]\)\s*.+(?:(?<!\p{L})[A-Ea-e]\)|RESPUESTA))/su', $texto, $matchCompleto)) {
            if ($preguntaActual && !empty($preguntaActual['enunciado']) && count($preguntaActual['opciones']) > 0) {
                $preguntas[] = $preguntaActual;
            }
            $enunciado = trim($matchCompleto[2]);
            $parteOpciones = $matchCompleto[3];
            $preguntaActual = ['enunciado' => $enunciado, 'opciones' => [], 'correcta' => ''];
            preg_match_all('/(?<!\p{L})([A-Ea-e])\)\s*(.+?)(?=(?:[A-Ea-e]\)|RESPUESTA|$))/iu', $parteOpciones, $matchesOpciones, PREG_SET_ORDER);
            foreach ($matchesOpciones as $mo) {
                $textoOpcion = trim(preg_replace('/RESPUESTA.*$/i', '', $mo[2]));
                if (!empty($textoOpcion)) $preguntaActual['opciones'][] = ['indice' => strtolower($mo[1]), 'texto' => $textoOpcion];
            }
            if (preg_match('/RESPUESTA[:\s]*([A-Ea-e])/i', $parteOpciones, $matchResp)) {
                $preguntaActual['correcta'] = strtolower($matchResp[1]);
            }
            continue;
        }

        if (preg_match('/^(\d+)\.\s*(.+)/', $texto, $m)) {
            if ($preguntaActual && !empty($preguntaActual['enunciado']) && count($preguntaActual['opciones']) > 0) {
                $preguntas[] = $preguntaActual;
            }
            $preguntaActual = ['enunciado' => trim($m[2]), 'opciones' => [], 'correcta' => ''];
            continue;
        }

        if (preg_match('/^([A-Ea-e])\)\s*(.*)/', $texto, $m)) {
            if ($preguntaActual) {
                $preguntaActual['opciones'][] = ['indice' => strtolower($m[1]), 'texto' => trim($m[2])];
            }
            continue;
        }

        if (preg_match('/^RESPUESTA[:\s]*([A-Ea-e])/i', $texto, $m)) {
            if ($preguntaActual) { $preguntaActual['correcta'] = strtolower($m[1]); }
            continue;
        }

        if (preg_match('/^(.+?)(?<!\p{L})([A-Ea-e]\)\s*.+(?:(?<!\p{L})[A-Ea-e]\)|RESPUESTA))/su', $texto, $matchMixto)) {
            $partePregunta = trim($matchMixto[1]);
            $parteOpciones = $matchMixto[2];
            if (strlen($partePregunta) > 15 || strpos($partePregunta, '?') !== false) {
                if ($preguntaActual && !empty($preguntaActual['enunciado']) && count($preguntaActual['opciones']) > 0) {
                    $preguntas[] = $preguntaActual;
                }
                $preguntaActual = ['enunciado' => $partePregunta, 'opciones' => [], 'correcta' => ''];
                preg_match_all('/(?<!\p{L})([A-Ea-e])\)\s*([^A-Ea-e\)]+?)(?=(?:[A-Ea-e]\)|RESPUESTA|$))/iu', $parteOpciones, $matchesOpciones, PREG_SET_ORDER);
                foreach ($matchesOpciones as $mo) {
                    $textoOpcion = preg_replace('/RESPUESTA.*$/i', '', trim($mo[2]));
                    if (!empty($textoOpcion)) $preguntaActual['opciones'][] = ['indice' => strtolower($mo[1]), 'texto' => $textoOpcion];
                }
                if (preg_match('/RESPUESTA[:\s]*([A-Ea-e])/i', $parteOpciones, $matchResp)) {
                    $preguntaActual['correcta'] = strtolower($matchResp[1]);
                }
            }
            continue;
        }
    }

    if ($preguntaActual && !empty($preguntaActual['enunciado']) && count($preguntaActual['opciones']) > 0) {
        $preguntas[] = $preguntaActual;
    }

    return count($preguntas);
}

// Helper: eliminar estudiantes de un curso y borrar usuarios que queden sin cursos
function eliminar_estudiantes_curso($ids, $idcurso) {
    global $conn;
    $ids = array_filter(array_map('intval', (array)$ids));
    $idcurso = (int)$idcurso;
    if (empty($ids) || !$idcurso) return ['eliminados' => 0, 'usuarios_borrados' => 0];

    $ids_str = implode(',', $ids);
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM usuario_cursos WHERE curso_id=$idcurso AND usuario_id IN ($ids_str)");
        $conn->query("DELETE FROM resultados_examen WHERE estudiante_id IN ($ids_str) AND examen_id IN (SELECT id FROM examenes WHERE curso_id=$idcurso)");
        $conn->query("DELETE FROM mensajes_chat WHERE curso_id=$idcurso AND estudiante_id IN ($ids_str)");

        $usuarios_borrados = 0;
        foreach ($ids as $uid) {
            $q = $conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id=$uid LIMIT 1");
            if ($q && $q->num_rows === 0) {
                $conn->query("DELETE FROM auditoria_log WHERE actor_id=$uid");
                $conn->query("DELETE FROM usuarios WHERE id=$uid AND rol='estudiante'");
                if ($conn->affected_rows > 0) $usuarios_borrados++;
            }
        }

        $conn->commit();
        return ['eliminados' => count($ids), 'usuarios_borrados' => $usuarios_borrados];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['eliminados' => 0, 'usuarios_borrados' => 0];
    }
}

// --- PROCESAMIENTO POST (FORMULARIOS) ---
if ($es_docente && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['crear_examen'])) {
        $curso = (int)$_POST['curso_id']; $tit = esc($_POST['titulo']); $dur = (int)$_POST['duracion_minutos']; $cant = (int)$_POST['cant_preguntas'];
        $parcial = ($_POST['parcial'] === 'segundo_parcial') ? 'segundo_parcial' : 'primer_parcial';
        $sec = isset($_POST['preguntas_secuenciales']) ? 1 : 0;
        $modo = $_POST['modo_examen'] ?? 'manual';
        
        if ($modo === 'programado') {
            $ini_prog = esc($_POST['inicio_programado']); $ini_sql = "'$ini_prog'";
            $estado = 'programado'; $ini = $ini_prog; $fin = esc($_POST['fecha_fin']);
        } else {
            $ini = $_POST['fecha_inicio'] ?? date('Y-m-d H:i:s');
            $fin = $_POST['fecha_fin'] ?? date('Y-m-d H:i:s', time() + ($dur * 60));
            $ini_sql = "NULL"; $estado = 'pendiente';
        }

        if (isset($_FILES['archivo_docx']) && $_FILES['archivo_docx']['error'] === 0) {
            $fname = $_FILES['archivo_docx']['name']; $tmp = $_FILES['archivo_docx']['tmp_name'];
            $conn->query("INSERT INTO examenes (curso_id, titulo, parcial, fecha_inicio, fecha_fin, inicio_programado, preguntas_secuenciales, duracion_minutos, cant_preguntas_mostrar, mostrar_nota, permitir_revision, archivo_origen, estado) VALUES ($curso, '$tit', '$parcial', '$ini', '$fin', $ini_sql, $sec, $dur, $cant, 1, 1, '$fname', '$estado')");
            $eid = $conn->insert_id;
            $dir = __DIR__ . '/uploads/'; if (!is_dir($dir)) mkdir($dir, 0755, true);
            move_uploaded_file($tmp, $dir . 'examen_' . $eid . '.docx');
            $res = procesarDocx($dir . 'examen_' . $eid . '.docx', $eid, $conn);
            if ($res === true) {
                registrar_log($usuario_id, $usuario_nombre, 'docente', 'CREAR_EXAMEN', 'Examen', $eid, "$tit ($modo)");
                redirigirConMensaje("<div class='alert-success'>Examen creado.</div>", $curso);
            } else { redirigirConMensaje("<div class='alert-warning'>Creado con advertencia: $res</div>", $curso); }
        }
    }

    if (isset($_POST['eliminar_estudiante'])) {
        $idest = (int)$_POST['id_estudiante_del']; $idcurso = (int)$_POST['id_curso_del'];
        $chk = $conn->query("SELECT co.id FROM usuario_cursos uc JOIN cursos_oferta co ON uc.curso_id=co.id WHERE uc.usuario_id=$usuario_id AND co.id=$idcurso");
        $puedeGestionar = ($chk->num_rows > 0) || $es_admin || $es_director_carrera || $es_director_area;
        if ($puedeGestionar) {
            $nombre_estudiante_log = '';
            $resNomEst = $conn->query("SELECT nombres FROM usuarios WHERE id=$idest LIMIT 1");
            if ($resNomEst && $rowNomEst = $resNomEst->fetch_assoc()) { $nombre_estudiante_log = trim((string)$rowNomEst['nombres']); }
            $resDel = eliminar_estudiantes_curso([$idest], $idcurso);
            $detalleLog = "Curso $idcurso (Retirado)";
            if ($nombre_estudiante_log !== '') { $detalleLog = $nombre_estudiante_log . " | " . $detalleLog; }
            registrar_log($usuario_id, $usuario_nombre, 'docente', 'ELIMINAR_ESTUDIANTE', 'Usuario', $idest, $detalleLog);
            $msg = "<div class='alert-success'>Estudiante retirado del curso.";
            if ($resDel['usuarios_borrados'] > 0) $msg .= " Usuario eliminado de la base de datos.";
            $msg .= "</div>";
            redirigirConMensaje($msg, $idcurso);
        }
    }

    if (isset($_POST['eliminar_masivo'])) {
        $ids = array_filter(array_map('intval', (array)($_POST['ids_estudiantes_masivo'] ?? []))); $idcurso = (int)$_POST['id_curso_masivo'];
        $chk = $conn->query("SELECT co.id FROM usuario_cursos uc JOIN cursos_oferta co ON uc.curso_id=co.id WHERE uc.usuario_id=$usuario_id AND co.id=$idcurso");
        $puedeGestionar = ($chk->num_rows > 0) || $es_admin || $es_director_carrera || $es_director_area;
        if ($puedeGestionar && !empty($ids)) {
            $nombres_retirados = [];
            $ids_str = implode(',', $ids);
            $resNomMasivo = $conn->query("SELECT nombres FROM usuarios WHERE id IN ($ids_str) ORDER BY TRIM(SUBSTRING_INDEX(nombres, ' ', 1)) ASC, nombres ASC");
            if ($resNomMasivo) {
                while ($rowNom = $resNomMasivo->fetch_assoc()) {
                    $n = trim((string)$rowNom['nombres']);
                    if ($n !== '') { $nombres_retirados[] = $n; }
                }
            }
            $resDel = eliminar_estudiantes_curso($ids, $idcurso);
            $detalleMasivo = "Curso $idcurso (Retirados)";
            if (!empty($nombres_retirados)) {
                $detalleMasivo .= ": " . implode(', ', array_slice($nombres_retirados, 0, 8));
                if (count($nombres_retirados) > 8) { $detalleMasivo .= " ..."; }
            }
            registrar_log($usuario_id, $usuario_nombre, 'docente', 'ELIMINAR_MASIVO', 'Varios', 0, $detalleMasivo);
            $msg = "<div class='alert-success'>Estudiantes retirados del curso. Usuarios eliminados: {$resDel['usuarios_borrados']}.</div>";
            redirigirConMensaje($msg, $idcurso);
        }
    }

    if (isset($_POST['importar_estudiantes'])) {
        $idcurso = (int)$_POST['curso_id_estudiantes'];
        $chk = $conn->query("SELECT co.id FROM usuario_cursos uc JOIN cursos_oferta co ON uc.curso_id=co.id WHERE uc.usuario_id=$usuario_id AND co.id=$idcurso");
        if ($chk->num_rows > 0 && isset($_FILES['archivo_estudiantes'])) {
            $ext = strtolower(pathinfo($_FILES['archivo_estudiantes']['name'], PATHINFO_EXTENSION));
            $rows = ($ext === 'csv') ? read_csv_rows($_FILES['archivo_estudiantes']['tmp_name']) : (($ext === 'xlsx') ? read_xlsx_rows($_FILES['archivo_estudiantes']['tmp_name']) : []);
            if (!empty($rows)) {
                $conn->begin_transaction(); $nuevos=0; $inscritos=0; $nombres_nuevos = [];
                foreach($rows as $r) {
                    $nom_raw = $r['nombrescompletos']??$r['nombres']??$r['nombre']??'';
                    $email_raw = $r['emailinstitucional']??$r['email']??'';
                    $nom_ordenado = ordenar_apellidos_primero($nom_raw, $email_raw);
                    $nom = esc($nom_ordenado); $ced = esc($r['cedula']??$r['ci']??$r['identificacion']??''); $email = esc($email_raw);
                    if(!$ced) continue;
                    $res = $conn->query("SELECT id FROM usuarios WHERE cedula='$ced' LIMIT 1");
                    if ($res->num_rows > 0) { 
                        $uid = (int)$res->fetch_assoc()['id']; 
                        $updates = [];
                        if ($nom !== '') $updates[] = "nombres='$nom'";
                        if ($email !== '') $updates[] = "email='$email'";
                        if (!empty($updates)) { $conn->query("UPDATE usuarios SET " . implode(', ', $updates) . " WHERE id=$uid"); }
                    } 
                    else { 
                        $pass = md5($ced); $conn->query("INSERT INTO usuarios (nombres, cedula, email, password, rol, estado) VALUES ('$nom', '$ced', '$email', '$pass', 'estudiante', 'aceptado')"); 
                        $uid = $conn->insert_id; $nuevos++;
                        $nombre_nuevo = trim((string)$nom_ordenado);
                        if ($nombre_nuevo !== '') { $nombres_nuevos[] = $nombre_nuevo; }
                    }
                    if ($conn->query("SELECT 1 FROM usuario_cursos WHERE usuario_id=$uid AND curso_id=$idcurso")->num_rows == 0) {
                        $conn->query("INSERT INTO usuario_cursos (usuario_id, curso_id) VALUES ($uid, $idcurso)"); $inscritos++;
                    }
                }
                $conn->commit();
                $detalleImport = "Nuevos: $nuevos, Inscritos: $inscritos";
                $nombres_nuevos = array_values(array_unique($nombres_nuevos));
                if (!empty($nombres_nuevos)) {
                    $detalleImport .= " | Agregados: " . implode(', ', array_slice($nombres_nuevos, 0, 8));
                    if (count($nombres_nuevos) > 8) { $detalleImport .= " ..."; }
                }
                registrar_log($usuario_id, $usuario_nombre, 'docente', 'IMPORTAR_ESTUDIANTES', 'Curso', $idcurso, $detalleImport);
                redirigirConMensaje("<div class='alert-success'>Importación: $nuevos nuevos, $inscritos inscritos.</div>", $idcurso);
            }
        }
    }

    if (isset($_POST['iniciar_examen'])) {
        $id = (int)$_POST['id_examen']; $ahora = date('Y-m-d H:i:s'); $fin = date('Y-m-d H:i:s', strtotime("+60 minutes"));
        $conn->query("UPDATE examenes SET estado='activo', fecha_inicio='$ahora', fecha_fin='$fin' WHERE id=$id");
        registrar_log($usuario_id, $usuario_nombre, 'docente', 'INICIAR_EXAMEN', 'Examen', $id, "Iniciado");
        redirigirConMensaje("<div class='alert-success'>Examen iniciado.</div>", obtenerCursoPorExamen($id));
    }
    if (isset($_POST['detener_examen'])) {
        $id = (int)$_POST['id_examen']; $ahora = date('Y-m-d H:i:s');
        $conn->query("UPDATE examenes SET estado='cerrado', fecha_fin='$ahora' WHERE id=$id");
        registrar_log($usuario_id, $usuario_nombre, 'docente', 'DETENER_EXAMEN', 'Examen', $id, "Detenido");
        redirigirConMensaje("<div class='alert-success'>Examen cerrado.</div>", obtenerCursoPorExamen($id));
    }
    if (isset($_POST['actualizar_tiempo'])) {
        $id = (int)$_POST['id_examen_time']; $fin = $_POST['nuevo_fin']; $dur = (int)$_POST['nueva_duracion'];
        $conn->query("UPDATE examenes SET fecha_fin='$fin', duracion_minutos=$dur WHERE id=$id");
        registrar_log($usuario_id, $usuario_nombre, 'docente', 'ACTUALIZAR_TIEMPO', 'Examen', $id, "Fin: $fin, Dur: $dur");
        redirigirConMensaje("<div class='alert-success'>Tiempo actualizado.</div>", obtenerCursoPorExamen($id));
    }
    if (isset($_POST['rehabilitar_estudiante'])) {
        $ide = (int)$_POST['id_examen_rehab']; $idu = (int)$_POST['id_estudiante_rehab'];
        $nombre_rehabilitado = '';
        $resNomRehab = $conn->query("SELECT nombres FROM usuarios WHERE id=$idu LIMIT 1");
        if ($resNomRehab && $rowNomRehab = $resNomRehab->fetch_assoc()) { $nombre_rehabilitado = trim((string)$rowNomRehab['nombres']); }
        $conn->query("DELETE FROM resultados_examen WHERE examen_id=$ide AND estudiante_id=$idu");
        $detalleRehab = ($nombre_rehabilitado !== '') ? ($nombre_rehabilitado . " | Examen $ide") : "Examen $ide";
        registrar_log($usuario_id, $usuario_nombre, 'docente', 'REHABILITAR', 'Intento', $idu, $detalleRehab);
        redirigirConMensaje("<div class='alert-success'>Rehabilitado.</div>", obtenerCursoPorExamen($ide));
    }
    if (isset($_POST['eliminar_examen'])) {
        $id = (int)$_POST['id_examen']; 
        $cid = obtenerCursoPorExamen($id);
        
        $f = __DIR__ . '/uploads/examen_' . $id . '.docx'; 
        if (file_exists($f)) unlink($f);
        
        $conn->query("DELETE FROM resultados_examen WHERE examen_id=$id");
        $conn->query("DELETE op FROM opciones_pregunta op INNER JOIN preguntas p ON op.pregunta_id=p.id WHERE p.examen_id=$id");
        $conn->query("DELETE FROM preguntas WHERE examen_id=$id");
        $conn->query("DELETE FROM examenes WHERE id=$id");
        
        registrar_log($usuario_id, $usuario_nombre, 'docente', 'ELIMINAR_EXAMEN', 'Examen', $id, "Examen eliminado");
        redirigirConMensaje("<div class='alert-success'>Examen eliminado correctamente.</div>", $cid);
    }
    if (isset($_POST['toggle_secuencial'])) {
        $id = (int)$_POST['id_examen'];
        $val = ($_POST['estado_actual'] == 1) ? 0 : 1;
        $conn->query("UPDATE examenes SET preguntas_secuenciales=$val WHERE id=$id");
        $accion = $val ? "Modo secuencial ACTIVADO" : "Modo libre ACTIVADO";
        registrar_log($usuario_id, $usuario_nombre, 'docente', 'TOGGLE_SECUENCIAL', 'Examen', $id, $accion);
        redirigirConMensaje("<div class='alert-success'>Modo de navegación actualizado.</div>", obtenerCursoPorExamen($id));
    }
    if (isset($_POST['toggle_nota']) || isset($_POST['toggle_revision'])) {
        $id = (int)$_POST['id_examen']; $col = isset($_POST['toggle_nota']) ? 'mostrar_nota' : 'permitir_revision';
        $val = ($_POST['estado_actual'] == 1) ? 0 : 1;
        $conn->query("UPDATE examenes SET $col=$val WHERE id=$id");
        $accion = $val ? "HABILITADO" : "DESHABILITADO";
        $tipo = isset($_POST['toggle_nota']) ? "Visualización de Nota" : "Revisión de Examen";
        redirigirConMensaje("<div class='alert-success'>Configuración actualizada: $tipo $accion.</div>", obtenerCursoPorExamen($id));
    }
    if (isset($_POST['subir_aplicacion'])) {
        if (isset($_FILES['archivo_aplicacion']) && $_FILES['archivo_aplicacion']['error'] === 0) {
            $ext = strtolower(pathinfo($_FILES['archivo_aplicacion']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['exe','zip','msi','rar','7z'])) {
                $dir = __DIR__ . '/uploads/apps/'; if (!is_dir($dir)) mkdir($dir, 0755, true);
                $new = 'app_docente_' . $usuario_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['archivo_aplicacion']['tmp_name'], $dir.$new)) {
                    $conn->query("UPDATE aplicaciones_docente SET activo=0 WHERE docente_id=$usuario_id");
                    $ver = esc($_POST['version_app']); $orig = esc($_FILES['archivo_aplicacion']['name']);
                    $conn->query("INSERT INTO aplicaciones_docente (docente_id, nombre_archivo, nombre_original, version, activo) VALUES ($usuario_id, '$new', '$orig', '$ver', 1)");
                    redirigirConMensaje("<div class='alert-success'>Aplicación subida.</div>");
                }
            }
        }
    }
    if (isset($_POST['eliminar_aplicacion'])) {
        $aid = (int)$_POST['app_id'];
        $res = $conn->query("SELECT * FROM aplicaciones_docente WHERE id=$aid AND docente_id=$usuario_id");
        if ($row = $res->fetch_assoc()) {
            $f = __DIR__ . '/uploads/apps/' . $row['nombre_archivo']; if (file_exists($f)) unlink($f);
            $conn->query("DELETE FROM aplicaciones_docente WHERE id=$aid");
            redirigirConMensaje("<div class='alert-success'>Aplicación eliminada.</div>");
        }
    }
}
?>
