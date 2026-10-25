<?php
session_start();
include "db.php";

// ==========================================
// 0. CONFIGURACIÓN REGIONAL
// ==========================================
date_default_timezone_set('America/Guayaquil');

// ==========================================
// 0.5. MANTENIMIENTO AUTOMÁTICO DE EXÁMENES
// ==========================================
$ahora = date('Y-m-d H:i:s');

// Activar exámenes programados
$sql_prog = "SELECT id, inicio_programado FROM examenes 
             WHERE estado IN ('pendiente', 'programado') 
             AND inicio_programado IS NOT NULL 
             AND inicio_programado <= '$ahora'";
$res_prog = $conn->query($sql_prog);
if ($res_prog && $res_prog->num_rows > 0) {
    while ($ex = $res_prog->fetch_assoc()) {
        $fecha_inicio = $ex['inicio_programado'];
        $conn->query("UPDATE examenes SET estado='activo', fecha_inicio='$fecha_inicio' WHERE id={$ex['id']}");
    }
}

// Cerrar automáticamente exámenes caducados
$conn->query("UPDATE examenes SET estado='cerrado' WHERE estado='activo' AND fecha_fin IS NOT NULL AND fecha_fin < '$ahora'");

// Verificar tabla de aplicaciones (Migración automática)
$checkAppTable = $conn->query("SHOW TABLES LIKE 'aplicaciones_docente'");
if ($checkAppTable && $checkAppTable->num_rows == 0) {
    $conn->query("CREATE TABLE `aplicaciones_docente` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `docente_id` INT(11) NOT NULL,
        `nombre_archivo` VARCHAR(255) NULL,
        `nombre_original` VARCHAR(255) NULL,
        `link_drive` TEXT NULL,
        `fecha_subida` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `version` VARCHAR(50) DEFAULT '1.0',
        `activo` TINYINT(1) DEFAULT 1,
        PRIMARY KEY (`id`),
        KEY `idx_docente` (`docente_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

// ==========================================
// 1. SEGURIDAD Y SESIÓN
// ==========================================
if (!isset($_SESSION["usuario"]) || $_SESSION["usuario"]["rol"] != "estudiante") { header("Location: index.php"); exit; }
$est_id = (int)$_SESSION["usuario"]["id"];
$est_nombre_completo = htmlspecialchars($_SESSION["usuario"]["nombres"]);
$nombre_partes = explode(' ', $est_nombre_completo);
$primer_nombre = $nombre_partes[0];

// Iniciales para avatar
$iniciales = strtoupper(substr($nombre_partes[0], 0, 1));
if (isset($nombre_partes[1])) {
    $iniciales .= strtoupper(substr($nombre_partes[1], 0, 1));
} else {
    $iniciales = strtoupper(substr($nombre_partes[0], 0, 2));
}

// ==========================================
// 2. DATOS DE USUARIO Y CAMBIO DE CLAVE
// ==========================================
$q_user = $conn->query("SELECT cedula, password, email FROM usuarios WHERE id = $est_id LIMIT 1");
$datos_user = $q_user->fetch_assoc();
$cedula_real = $datos_user['cedula'];
$pass_actual_db = $datos_user['password'];
$email_usuario = htmlspecialchars($datos_user['email']);

$es_clave_temporal = ($pass_actual_db === md5($cedula_real));
$mensaje_pass = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_clave'])) {
    $actual = $_POST['clave_actual'];
    $nueva  = $_POST['nueva_clave'];
    $conf   = $_POST['conf_clave'];

    if (md5($actual) !== $pass_actual_db) {
        $mensaje_pass = "<div class='alert-error'>❌ La contraseña actual es incorrecta.</div>";
    } elseif ($nueva !== $conf) {
        $mensaje_pass = "<div class='alert-error'>❌ Las nuevas contraseñas no coinciden.</div>";
    } elseif (strlen($nueva) < 4) {
        $mensaje_pass = "<div class='alert-error'>❌ La contraseña es muy corta.</div>";
    } elseif ($nueva === $cedula_real) {
        $mensaje_pass = "<div class='alert-error'>❌ No puedes usar tu cédula nuevamente.</div>";
    } else {
        $nueva_hash = md5($nueva);
        $conn->query("UPDATE usuarios SET password = '$nueva_hash' WHERE id = $est_id");
        $pass_actual_db = $nueva_hash; 
        $es_clave_temporal = false; 
        $mensaje_pass = "<div class='alert-success'>✅ ¡Contraseña actualizada correctamente!</div>";
    }
}

// Bloqueo de pantalla si es clave temporal
if ($es_clave_temporal) {
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="icon" href="favicon.ico"><link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png"><link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png"><title>Seguridad</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f3f4f6;height:100vh;display:flex;align-items:center;justify-content:center;margin:0}.card{background:white;padding:40px;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);width:100%;max-width:400px}input{width:100%;padding:10px;margin:10px 0;border:1px solid #ddd;border-radius:4px;box-sizing:border-box}button{width:100%;padding:10px;background:#0f6cb6;color:white;border:none;border-radius:4px;cursor:pointer;font-weight:bold}.alert-error{background:#fee2e2;color:#991b1b;padding:10px;border-radius:4px;margin-bottom:10px;font-size:0.9rem}</style></head><body><div class="card"><h2 style="margin-top:0;text-align:center;color:#333">🔐 Cambio Obligatorio</h2><p style="color:#666;font-size:0.9rem;text-align:center">Por seguridad, actualiza tu contraseña.</p>'.$mensaje_pass.'<form method="POST"><input type="password" name="clave_actual" placeholder="Contraseña actual (Cédula)" required><input type="password" name="nueva_clave" placeholder="Nueva contraseña" required><input type="password" name="conf_clave" placeholder="Confirmar nueva contraseña" required><button type="submit" name="cambiar_clave">Guardar y Continuar</button></form><a href="logout.php" style="display:block;text-align:center;margin-top:15px;color:#d9534f;text-decoration:none;font-size:0.9rem">Salir</a></div></body></html>';
    exit;
}

// ==========================================
// 3. LÓGICA DE NAVEGACIÓN Y DATOS
// ==========================================
$seccion = $_GET['section'] ?? 'dashboard';
$curso_id_sel = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Cursos para el sidebar
$sql_cursos_nav = "SELECT ci.nombre AS ciclo, m.nombre AS materia, p.nombre AS paralelo, co.id as curso_id FROM usuario_cursos uc JOIN cursos_oferta co ON uc.curso_id=co.id JOIN paralelos p ON co.paralelo_id=p.id JOIN materias m ON p.materia_id=m.id JOIN ciclos ci ON co.ciclo_id=ci.id WHERE uc.usuario_id=$est_id ORDER BY ci.nombre DESC, m.nombre ASC, p.nombre ASC, co.id ASC";
$mis_cursos = $conn->query($sql_cursos_nav)->fetch_all(MYSQLI_ASSOC);

// Aplicaciones docentes (Links)
$aplicaciones_disponibles = [];
$sql_docentes = "SELECT DISTINCT uc_doc.usuario_id as docente_id FROM usuario_cursos uc_est JOIN usuario_cursos uc_doc ON uc_est.curso_id = uc_doc.curso_id JOIN usuarios u ON uc_doc.usuario_id = u.id WHERE uc_est.usuario_id = $est_id AND u.rol IN ('docente', 'director_carrera', 'director_area')";
$res_doc = $conn->query($sql_docentes);
if ($res_doc) {
    $docentes_ids = []; while ($row = $res_doc->fetch_assoc()) { $docentes_ids[] = $row['docente_id']; }
    if (!empty($docentes_ids)) {
        $docentes_str = implode(',', $docentes_ids);
        $sql_apps = "SELECT ad.*, u.nombres as docente_nombre FROM aplicaciones_docente ad JOIN usuarios u ON ad.docente_id = u.id WHERE ad.docente_id IN ($docentes_str) AND ad.activo = 1 ORDER BY ad.fecha_subida DESC";
        $res_apps = $conn->query($sql_apps);
        if ($res_apps) while ($app = $res_apps->fetch_assoc()) $aplicaciones_disponibles[$app['docente_id']] = $app;
    }
}

// Variables para la vista
$titulo_seccion = "Línea de tiempo (Historial)";
$subtitulo_seccion = "";
$mis_calificaciones = [];
$todos_examenes_estudiante = []; // Usado en Dashboard y Curso

// Lógica de datos según sección
if ($seccion === 'calificaciones') {
    $titulo_seccion = "Mis Calificaciones";
    $sql_cal = "SELECT m.nombre as materia, p.nombre as paralelo, e.parcial, e.titulo as examen_titulo, e.mostrar_nota, re.puntaje_total as nota, re.fecha_intento as fecha FROM resultados_examen re JOIN examenes e ON re.examen_id = e.id JOIN cursos_oferta co ON e.curso_id = co.id JOIN paralelos p ON co.paralelo_id = p.id JOIN materias m ON p.materia_id = m.id JOIN usuario_cursos uc ON uc.curso_id = co.id AND uc.usuario_id = $est_id WHERE re.estudiante_id = $est_id AND re.puntaje_total != -1 ORDER BY m.nombre ASC, e.parcial ASC, re.fecha_intento DESC";
    $res_cal = $conn->query($sql_cal);
    while ($row = $res_cal->fetch_assoc()) {
        $materia = $row['materia'];
        if (!isset($mis_calificaciones[$materia])) { $mis_calificaciones[$materia] = ['paralelo' => $row['paralelo'], 'primer_parcial' => [], 'segundo_parcial' => []]; }
        $parcial_key = $row['parcial'] ?: 'primer_parcial';
        $mis_calificaciones[$materia][$parcial_key][] = [
            'titulo' => $row['examen_titulo'],
            'nota' => ($row['mostrar_nota'] == 1) ? $row['nota'] : null,
            'fecha' => $row['fecha'],
            'mostrar' => $row['mostrar_nota']
        ];
    }
} else {
    // Para Dashboard y Vista de Curso individual
    $where_curso = ($seccion === 'curso' && $curso_id_sel > 0) ? "AND co.id = $curso_id_sel" : "";
    
    // Obtener info del curso actual si aplica
    if ($seccion === 'curso' && $curso_id_sel > 0) {
        foreach($mis_cursos as $mc) { if($mc['curso_id'] == $curso_id_sel) { $titulo_seccion = $mc['materia']; $subtitulo_seccion = $mc['paralelo']; break; } }
    }

    // Consulta principal de exámenes (Traemos TODOS los estados relevantes para mostrar)
    $sql_todos = "
        SELECT e.*, m.nombre as materia, p.nombre as paralelo, co.id as curso_id,
        (SELECT puntaje_total FROM resultados_examen re WHERE re.examen_id = e.id AND re.estudiante_id = $est_id LIMIT 1) as nota_registrada,
        (SELECT fecha_intento FROM resultados_examen re WHERE re.examen_id = e.id AND re.estudiante_id = $est_id LIMIT 1) as fecha_intento
        FROM examenes e
        JOIN cursos_oferta co ON e.curso_id = co.id 
        JOIN usuario_cursos uc ON uc.curso_id = co.id
        JOIN paralelos p ON co.paralelo_id = p.id 
        JOIN materias m ON p.materia_id = m.id
        WHERE uc.usuario_id = $est_id 
        AND e.estado IN ('activo', 'programado', 'cerrado')
        $where_curso
        ORDER BY 
            CASE WHEN e.estado = 'activo' THEN 0 WHEN e.estado = 'programado' THEN 1 ELSE 2 END,
            e.fecha_fin DESC";
    
    $res_todos = $conn->query($sql_todos);
    if ($res_todos) $todos_examenes_estudiante = $res_todos->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
<title>Área personal</title>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    /* Estilos base */
    :root { --primary: #0f6cb6; --bg-body: #f2f2f2; --text-color: #373a3c; --header-height: 60px; }
    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: var(--bg-body); color: var(--text-color); margin: 0; padding: 0; font-size: 0.9375rem; }
    
    /* Navbar */
    .navbar { background-color: #fff; height: var(--header-height); display: flex; justify-content: space-between; align-items: center; padding: 0 15px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); position: fixed; top: 0; width: 100%; z-index: 1000; box-sizing: border-box; }
    .navbar-brand { font-weight: 700; color: var(--primary); font-size: 1.5rem; text-decoration: none; display: flex; align-items: center; }
    .navbar-right { display: flex; align-items: center; gap: 15px; }
    
    /* User Menu */
    .user-menu-container { position: relative; }
    .user-menu-btn { display: flex; align-items: center; gap: 10px; background: #fff; border:1px solid #e5e7eb; border-radius:999px; cursor: pointer; padding:6px 10px; box-shadow:0 2px 8px rgba(0,0,0,0.08); }
    .user-menu-btn:hover { box-shadow:0 4px 12px rgba(0,0,0,0.12); }
    .user-name { font-weight:700; color:#0f172a; font-size:0.9rem; line-height:1.1; display:block; }
    .user-name::after { content:'Estudiante'; display:block; font-size:0.78rem; color:#6b7280; font-weight:600; }
    .user-avatar { width: 35px; height: 35px; background: linear-gradient(135deg, #0f6cb6 0%, #0b5591 100%); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; box-shadow:0 2px 6px rgba(0,0,0,0.12); }
    .user-caret { font-size:1.2rem; width:16px; height:16px; position:relative; color:#6b7280; line-height:1; display:inline-flex; align-items:center; justify-content:center; }
    .user-caret::after { content:'▾'; font-size:1.2rem; color:inherit; position:absolute; top:0; left:0; }
    .user-name { font-weight: 600; color: #1d2125; font-size: 0.9rem; }
    .user-avatar { width: 35px; height: 35px; background-color: #e9ecef; color: #495057; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
    .dropdown-menu { position: absolute; top: 100%; right: 0; background: white; border: 1px solid rgba(0,0,0,.15); border-radius: 4px; box-shadow: 0 6px 12px rgba(0,0,0,.175); min-width: 200px; display: none; flex-direction: column; z-index: 1001; margin-top: 5px; }
    .dropdown-menu.show { display: flex; }
    .dropdown-item { padding: 10px 20px; color: #212529; text-decoration: none; font-size: 0.9rem; transition: background 0.2s; }
    .dropdown-item:hover { background-color: #f8f9fa; color: #16181b; }
    .dropdown-divider { height: 1px; margin: 5px 0; background-color: #e5e5e5; }

    /* Layout */
    .main-wrapper { display: flex; margin-top: var(--header-height); min-height: calc(100vh - var(--header-height)); }
    .sidebar { width: 280px; background-color: #f2f2f2; padding: 20px 15px; flex-shrink: 0; border-right: 1px solid #e6e6e6; }
    .nav-group { margin-bottom: 20px; }
    .nav-title { font-size: 0.8rem; text-transform: uppercase; color: #6a737b; font-weight: 700; margin-bottom: 10px; padding-left: 10px; }
    .nav-link { display: block; padding: 8px 10px; color: #333; text-decoration: none; border-radius: 4px; margin-bottom: 2px; font-size: 0.95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .nav-link:hover { background-color: #e4e6ea; color: #111; }
    .nav-link.active { background-color: #e4e6ea; font-weight: 600; border-left: 4px solid var(--primary); }
    
    .download-app-link { display: flex; align-items: center; gap: 8px; padding: 8px 12px; margin: 5px 10px 10px; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; text-decoration: none; border-radius: 6px; font-size: 0.8rem; font-weight: 600; transition: all 0.2s; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.3); }
    .download-app-link:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(5, 150, 105, 0.4); color: white; }

    /* Content */
    .page-content { flex: 1; padding: 30px; background-color: white; }
    .welcome-header { margin-bottom: 30px; }
    .welcome-title { font-size: 1.75rem; font-weight: 300; color: #333; margin: 0; }
    .timeline-section { background: #fff; border: 1px solid #e0e0e0; border-radius: 4px; padding: 20px; }
    .timeline-header { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
    .timeline-title { font-size: 1.1rem; font-weight: 600; color: #333; margin: 0; }
    
    /* BADGES (Nuevos colores solicitados) */
    .status-badge { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; display: inline-block; }
    
    /* Evaluado (Verde) */
    .status-evaluado { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    
    /* Disponible (Azul) */
    .status-disponible { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
    
    /* Programado (Amarillo) */
    .status-programado { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
    
    /* Cerrado (Rojo) */
    .status-cerrado { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    
    .app-info-box { background: linear-gradient(135deg, #dbeafe 0%, #e0f2fe 100%); border: 1px solid #93c5fd; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; align-items: center; gap: 15px; }
    .app-info-box .icon { font-size: 2rem; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    
    /* Banner y secciones (alineado con docente) */
    .welcome-banner { margin-bottom: 30px; }
    .welcome-banner h1 { margin: 0; font-weight: 300; color: #333; font-size: 1.8rem; }
    
    .course-section { border: 1px solid #e1e1e1; border-radius: 4px; margin-bottom: 25px; background: #fff; }
    .course-section-header { padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #e1e1e1; display: flex; justify-content: space-between; align-items: center; }
    .course-section-header h3 { margin: 0; font-size: 1.1rem; color: #333; }
    .course-section-body { padding: 20px; }

    /* Perfil */
    .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; }
    .profile-field { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; }
    .profile-field label { font-size: 0.8rem; color: #6b7280; margin: 0 0 4px 0; font-weight: 700; }
    .profile-field p { margin: 0; color: #111827; font-weight: 600; }
    .profile-form .form-control { max-width: 420px; }

    /* Formularios y botones */
    .form-row { display: flex; gap: 15px; margin-bottom: 10px; }
    .form-group { flex: 1; }
    .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .btn { padding: 6px 12px; border-radius: 4px; border: 1px solid transparent; cursor: pointer; font-size: 0.9rem; text-decoration: none; display: inline-block; }
    .btn-primary { background-color: #0f6cb6; color: white; }
    .btn-primary:hover { background-color: #0b5591; }

    /* Alerts */
    .alert-success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    .alert-error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    .alert-warning { background: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; margin-bottom: 15px; }

    /* Estilos Dashboard Cards (Recuadros) */
    .dashboard-grid { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; }
    .materia-card {
        flex: 1 1 300px;
        background: white; 
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.07);
        border: 1px solid #e5e7eb;
        overflow: hidden;
        display: flex; flex-direction: column;
        transition: transform 0.2s;
    }
    .materia-card:hover { transform: translateY(-2px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
    .materia-card-header {
        background: linear-gradient(135deg, #0f6cb6 0%, #094b7e 100%);
        padding: 15px 20px;
        color: white;
    }
    .materia-card-title { font-size: 1.1rem; font-weight: 700; margin: 0 0 5px 0; }
    .materia-card-subtitle { font-size: 0.85rem; opacity: 0.9; font-weight: 400; }
    .materia-card-body { padding: 15px 20px; flex: 1; display: flex; flex-direction: column; gap: 10px; }
    .empty-state { color: #9ca3af; font-size: 0.9rem; text-align: center; padding: 10px 0; font-style: italic; }
    
    .exam-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: 10px; border-radius: 8px;
        background-color: #f8fafc; border: 1px solid #f1f5f9;
    }
    .exam-info { display: flex; flex-direction: column; }
    .exam-name { font-weight: 600; font-size: 0.9rem; color: #334155; margin-bottom: 2px; }
    .exam-date { font-size: 0.75rem; color: #64748b; }
    
    /* Etiquetas para tarjetas (mini badges) */
    .tag { font-size: 0.7rem; padding: 3px 8px; border-radius: 999px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .tag-evaluado { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .tag-disponible { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }
    .tag-programado { background: #fef9c3; color: #854d0e; border: 1px solid #fde047; }
    .tag-cerrado { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    /* Buttons */
    .btn-sm { padding: 4px 8px; font-size: 0.8rem; border-radius: 4px; text-decoration: none; display: inline-block; cursor: pointer; border: 1px solid transparent; }
    .btn-outline-primary { border-color: var(--primary); color: var(--primary); background: transparent; }
    .btn-outline-primary:hover { background: #e0f2fe; }
    .btn-disabled { color: #ccc; border-color: #eee; cursor: not-allowed; }

    @media (max-width: 768px) {
        .main-wrapper { flex-direction: column; }
        .sidebar { width: 100%; border-right: none; border-bottom: 1px solid #e6e6e6; }
        .user-name { display: none; }
    }
</style>
</head>
<body>

<!-- Navbar -->
<header class="navbar">
    <div class="navbar-brand" style="cursor:default;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6d/LogoUGcolor.png" alt="Logo UG" style="height:32px; width:auto; display:block; margin-right:8px;">
        <span style="font-size:0.95rem; font-weight:600; color:#555;">Panel Estudiante</span>
    </div>
    
    <div class="navbar-right">
        <div class="user-menu-container">
            <button class="user-menu-btn" onclick="toggleUserMenu()">
                <span class="user-name"><?= $est_nombre_completo ?></span>
                <div class="user-avatar"><?= $iniciales ?></div>
                <span class="user-caret"></span>
            </button>
            <div class="dropdown-menu" id="userDropdown">
                <a href="?section=perfil" class="dropdown-item">Perfil</a>
                <a href="?section=calificaciones" class="dropdown-item">Calificaciones</a>
                <div class="dropdown-divider"></div>
                <a href="logout.php" class="dropdown-item">Cerrar sesión</a>
            </div>
        </div>
    </div>
</header>

<div class="main-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="nav-group">
            <a href="javascript:void(0)" onclick="irAreaPersonalEstudiante()" class="nav-link <?= $seccion=='dashboard'?'active':'' ?>">🏠 &nbsp;Área personal</a>
        </div>

        <div class="nav-group">
            <div class="nav-title">Mis cursos</div>
            <?php if(empty($mis_cursos)): ?>
                <div style="padding:0 10px; font-size:0.85rem; color:#777;">No estás matriculado en cursos.</div>
            <?php else: foreach($mis_cursos as $c): ?>
                <a href="?section=curso&id=<?= $c['curso_id'] ?>" class="nav-link <?= ($seccion=='curso' && $curso_id_sel==$c['curso_id'])?'active':'' ?>" title="<?= htmlspecialchars($c['materia']) ?>">
                    🎓 &nbsp;<?= htmlspecialchars($c['materia']) ?>
                </a>
            <?php endforeach; endif; ?>
        </div>

        <?php if(!empty($aplicaciones_disponibles)): ?>
        <div class="nav-group">
            <div class="nav-title">📥 Aplicación de Exámenes</div>
            <?php $app = reset($aplicaciones_disponibles); ?>
            <?php if (!empty($app['link_drive'])): ?>
                <a href="<?= htmlspecialchars($app['link_drive']) ?>" target="_blank" class="download-app-link">
                    <span class="icon">🔗</span><span>Abrir App (Drive)</span>
                </a>
                <div style="padding: 5px 12px; font-size: 0.75rem; color: #666;">
                    Versión <?= htmlspecialchars($app['version']) ?><br>
                    Fecha: <?= date('d/m/Y', strtotime($app['fecha_subida'])) ?>
                </div>
            <?php else: ?>
                <div style="padding: 5px 12px; font-size: 0.75rem; color: #999;">Enlace no disponible.</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </aside>

    <!-- Contenido -->
    <main class="page-content">
        
        <?php if ($seccion === 'perfil'): ?>
            <div class="welcome-banner">
                <div style="color:var(--primary); font-size:0.9rem;">Perfil de usuario</div>
                <h1><?= $est_nombre_completo ?></h1>
            </div>
            <?php if(!empty($mensaje_pass)): ?>
                <?= $mensaje_pass ?>
            <?php endif; ?>
            <div class="course-section">
                <div class="course-section-header"><h3>Detalles de usuario</h3></div>
                <div class="course-section-body">
                    <div class="profile-grid">
                        <div class="profile-field"><label>Nombre completo</label><p><?= $est_nombre_completo ?></p></div>
                        <?php if(!empty($email_usuario)): ?><div class="profile-field"><label>Dirección de correo</label><p><a href="mailto:<?= $email_usuario ?>"><?= $email_usuario ?></a></p></div><?php endif; ?>
                        <div class="profile-field"><label>Rol</label><p>Estudiante</p></div>
                        <div class="profile-field"><label>Zona horaria</label><p>America/Guayaquil</p></div>
                        <div class="profile-field"><label>País</label><p>Ecuador</p></div>
                    </div>
                </div>
            </div>
            <div class="course-section">
                <div class="course-section-header"><h3>Seguridad</h3></div>
                <div class="course-section-body">
                    <form method="POST" class="profile-form">
                        <div class="form-row" style="flex-direction:column; gap:10px; max-width:420px;">
                            <div class="form-group">
                                <label>Contraseña actual</label>
                                <input type="password" name="clave_actual" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Nueva contraseña</label>
                                <input type="password" name="nueva_clave" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Confirmar nueva contraseña</label>
                                <input type="password" name="conf_clave" class="form-control" required>
                            </div>
                        </div>
                        <button type="submit" name="cambiar_clave" class="btn btn-primary">Guardar cambios</button>
                    </form>
                </div>
            </div>

        <?php elseif ($seccion === 'calificaciones'): ?>
            <div class="welcome-header">
                <div style="color:#0f6cb6; font-size:0.9rem; margin-bottom:10px;">Área personal</div>
                <h1 class="welcome-title">📊 Mis Calificaciones</h1>
            </div>
            <div class="timeline-section">
                <?php if(empty($mis_calificaciones)): ?>
                    <div style="text-align:center; padding:40px; color:#666;"><p>No tienes calificaciones registradas aún.</p></div>
                <?php else: ?>
                    <?php foreach($mis_calificaciones as $materia => $data): ?>
                    <div style="background:white; border-radius:10px; padding:20px; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                        <h3 style="margin:0 0 5px; color:#1e293b; font-size:1.1rem;">📚 <?= htmlspecialchars($materia) ?></h3>
                        <p style="margin:0 0 15px; color:#64748b; font-size:0.85rem;"><?= htmlspecialchars($data['paralelo']) ?></p>
                        
                        <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                            <thead><tr style="background:#f1f5f9;"><th style="padding:8px; text-align:left;">Evaluación</th><th style="padding:8px; text-align:center;">Nota</th><th style="padding:8px; text-align:right;">Fecha</th></tr></thead>
                            <tbody>
                                <?php 
                                $all_notes = array_merge($data['primer_parcial'], $data['segundo_parcial']);
                                if(empty($all_notes)): echo '<tr><td colspan="3" style="text-align:center; padding:10px;">Sin registros</td></tr>'; else:
                                foreach($all_notes as $not): 
                                    $val = $not['nota'];
                                    $txtNota = ($val !== null) ? number_format($val, 2) : '<span style="color:#999; font-size:0.8rem;">🔒 Oculta</span>';
                                    $colNota = ($val !== null && $val < 7) ? 'color:#ef4444;' : 'color:#0f766e;';
                                ?>
                                <tr style="border-bottom:1px solid #eee;">
                                    <td style="padding:8px;"><?= htmlspecialchars($not['titulo']) ?></td>
                                    <td style="padding:8px; text-align:center; font-weight:bold; <?= $colNota ?>"><?= $txtNota ?></td>
                                    <td style="padding:8px; text-align:right; color:#666;"><?= date('d/m/Y', strtotime($not['fecha'])) ?></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($seccion === 'dashboard'): ?>
            <div class="welcome-header">
                <div style="color:#0f6cb6; font-size:0.9rem; margin-bottom:10px;">Área personal</div>
                <h1 class="welcome-title"><?= $titulo_seccion ?></h1>
            </div>

            <?php if(!empty($aplicaciones_disponibles)): ?>
            <div class="app-info-box">
                <div class="icon">💻</div>
                <div class="content">
                    <h4>📢 Los exámenes se rinden desde la Aplicación de Escritorio</h4>
                    <p>Descarga la aplicación desde el menú lateral para poder rendir tus evaluaciones de forma segura.</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="dashboard-grid">
            <?php 
                // Agrupar exámenes por curso para las tarjetas
                $examenes_por_materia = [];
                // Usamos $todos_examenes_estudiante que ya trae todos los exámenes activos, programados, cerrados o evaluados
                foreach ($todos_examenes_estudiante as $ex) {
                    $cid = $ex['curso_id'];
                    $ya_rendido = ($ex['nota_registrada'] !== null && $ex['nota_registrada'] != -1);
                    $es_activo = ($ex['estado'] === 'activo');
                    $es_programado = ($ex['estado'] === 'programado');
                    $es_cerrado = ($ex['estado'] === 'cerrado');
                    
                    // Lógica para mostrar en el Dashboard (Recordatorio)
                    // Mostrar si: Activo, Programado, Cerrado (sin rendir)
                    if ($es_activo || $es_programado || ($es_cerrado && !$ya_rendido)) {
                        $examenes_por_materia[$cid][] = $ex;
                    }
                }

                // Recorrer mis cursos para generar las tarjetas
                if (empty($mis_cursos)): ?>
                    <div style="width:100%; text-align:center; padding:40px; color:#666;">No estás inscrito en cursos.</div>
                <?php else: 
                    foreach ($mis_cursos as $curso): 
                        $cid = $curso['curso_id'];
                        $lista_examenes = isset($examenes_por_materia[$cid]) ? $examenes_por_materia[$cid] : [];
                ?>
                    <div class="materia-card">
                        <div class="materia-card-header">
                            <h3 class="materia-card-title"><?= htmlspecialchars($curso['materia']) ?></h3>
                            <div class="materia-card-subtitle"><?= htmlspecialchars($curso['paralelo']) ?> | <?= htmlspecialchars($curso['ciclo']) ?></div>
                        </div>
                        <div class="materia-card-body">
                            <?php if (empty($lista_examenes)): ?>
                                <div class="empty-state">Sin exámenes pendientes</div>
                            <?php else: ?>
                                <?php foreach ($lista_examenes as $ex): 
                                    $ya_rendido = ($ex['nota_registrada'] !== null && $ex['nota_registrada'] != -1);
                                    $es_activo = ($ex['estado'] === 'activo');
                                    $es_programado = ($ex['estado'] === 'programado');
                                    
                                    $tag = '';
                                    $info_extra = '';
                                    
                                    if ($ya_rendido) {
                                        // Aunque en el filtro lo excluímos, dejo la lógica por si decides incluirlo
                                        $tag = '<span class="tag tag-evaluado">Evaluado</span>';
                                        $info_extra = 'Completado';
                                    } elseif ($es_activo) {
                                        $tag = '<span class="tag tag-disponible">Disponible</span>';
                                        $info_extra = 'Cierra: ' . date('d/m H:i', strtotime($ex['fecha_fin']));
                                    } elseif ($es_programado) {
                                        $tag = '<span class="tag tag-programado">Programado</span>';
                                        $info_extra = 'Inicia: ' . date('d/m H:i', strtotime($ex['inicio_programado']));
                                    } else {
                                        $tag = '<span class="tag tag-cerrado">Cerrado</span>';
                                        $info_extra = 'No rendido';
                                    }
                                ?>
                                <div class="exam-item">
                                    <div class="exam-info">
                                        <span class="exam-name"><?= htmlspecialchars($ex['titulo']) ?></span>
                                        <span class="exam-date"><?= $info_extra ?></span>
                                    </div>
                                    <?= $tag ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; endif; ?>
            </div>

        <?php elseif ($seccion === 'curso'): ?>
            <!-- VISTA CURSO INDIVIDUAL -->
            <div class="welcome-header">
                <div style="color:#0f6cb6; font-size:0.9rem; margin-bottom:10px;">Mis cursos / <?= htmlspecialchars($subtitulo_seccion) ?></div>
                <h1 class="welcome-title"><?= htmlspecialchars($titulo_seccion) ?></h1>
            </div>

            <div class="timeline-section">
                <div class="timeline-header"><h2 class="timeline-title">Lista de Exámenes</h2></div>
                <?php if(empty($todos_examenes_estudiante)): ?>
                    <div style="text-align:center; padding:40px; color:#666;"><p>No hay exámenes disponibles en este curso.</p></div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                            <thead><tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                                <th style="padding:12px; text-align:left; color:#64748b;">Examen</th>
                                <th style="padding:12px; text-align:center; color:#64748b;">Estado</th>
                                <th style="padding:12px; text-align:center; color:#64748b;">Calificación</th>
                                <th style="padding:12px; text-align:center; color:#64748b;">Revisión</th>
                                <th style="padding:12px; text-align:center; color:#64748b;">Límite / Inicio</th>
                            </tr></thead>
                            <tbody>
                                <?php foreach($todos_examenes_estudiante as $ex): 
                                    $es_activo = ($ex['estado'] === 'activo');
                                    $es_programado = ($ex['estado'] === 'programado');
                                    $ya_rendido = ($ex['nota_registrada'] !== null && $ex['nota_registrada'] != -1);
                                    
                                    // 1. ESTADO VISUAL
                                    $estado_html = '';
                                    if ($ya_rendido) {
                                        $estado_html = '<span class="status-badge status-evaluado">Evaluado</span>';
                                    } elseif ($es_activo) {
                                        $estado_html = '<span class="status-badge status-disponible">Disponible</span>';
                                    } elseif ($es_programado) {
                                        $inicio_fmt = date('d/m H:i', strtotime($ex['inicio_programado']));
                                        $estado_html = '<span class="status-badge status-programado" title="Inicia: '.$inicio_fmt.'">Programado</span>';
                                    } else {
                                        $estado_html = '<span class="status-badge status-cerrado">Cerrado</span>';
                                    }

                                    // 2. CALIFICACIÓN
                                    $txtNota = '--';
                                    if ($ya_rendido) {
                                        if ($ex['mostrar_nota'] == 1) {
                                            $txtNota = number_format($ex['nota_registrada'], 2);
                                        } else {
                                            $txtNota = '<span title="Nota oculta por el docente" style="cursor:help;">🔒</span>';
                                        }
                                    }

                                    // 3. REVISIÓN
                                    $btnRev = '<span style="color:#ccc;">--</span>';
                                    if ($ya_rendido) {
                                        if ($ex['permitir_revision'] == 1) {
                                            $btnRev = '<button onclick="verRevision('.$ex['id'].')" class="btn-sm btn-outline-primary" title="Abrir revisión detallada">🔎 Ver</button>';
                                        } else {
                                            $btnRev = '<span style="font-size:0.8rem; color:#999;" title="Revisión deshabilitada por el docente">🚫</span>';
                                        }
                                    }

                                    // 4. FECHA MOSTRADA
                                    $fecha_mostrar = '';
                                    if ($es_programado) {
                                        $fecha_mostrar = '<span style="color:#854d0e; font-weight:bold;">Inicia: '.date('d/m/Y H:i', strtotime($ex['inicio_programado'])).'</span>';
                                    } else {
                                        $fecha_mostrar = '<span style="color:#64748b;">Fin: '.date('d/m/Y H:i', strtotime($ex['fecha_fin'])).'</span>';
                                    }
                                ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px;"><strong><?= htmlspecialchars($ex['titulo']) ?></strong></td>
                                    <td style="padding:12px; text-align:center;">
                                        <?= $estado_html ?>
                                    </td>
                                    <td style="padding:12px; text-align:center; font-weight:bold;"><?= $txtNota ?></td>
                                    <td style="padding:12px; text-align:center;"><?= $btnRev ?></td>
                                    <td style="padding:12px; text-align:center; font-size:0.85rem;"><?= $fecha_mostrar ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
    function toggleUserMenu() {
        var menu = document.getElementById('userDropdown');
        menu.classList.toggle('show');
    }
    window.onclick = function(event) {
        if (!event.target.closest('.user-menu-container')) {
            var dropdowns = document.getElementsByClassName("dropdown-menu");
            for (var i = 0; i < dropdowns.length; i++) {
                if (dropdowns[i].classList.contains('show')) {
                    dropdowns[i].classList.remove('show');
                }
            }
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const currentSection = urlParams.get('section');
        const currentId = urlParams.get('id');
        
        if (currentSection && currentSection !== 'dashboard') {
            let savedUrl = '?section=' + currentSection;
            if (currentId) savedUrl += '&id=' + currentId;
            sessionStorage.setItem('estudiante_seccion_actual', savedUrl);
        } else if (currentSection === 'dashboard') {
            sessionStorage.removeItem('estudiante_seccion_actual');
        } else {
            const savedSection = sessionStorage.getItem('estudiante_seccion_actual');
            if (savedSection) {
                window.location.href = 'estudiante.php' + savedSection;
            }
        }
    });
    
    function irAreaPersonalEstudiante() {
        sessionStorage.removeItem('estudiante_seccion_actual');
        window.location.href = 'estudiante.php?section=dashboard';
    }

    // Nueva función para abrir la revisión en ventana externa
    function verRevision(eid) {
        // Abre revisar_examen.php en una nueva pestaña
        window.open('revisar_examen.php?id=' + eid, '_blank');
    }
</script>
</body>
</html>
