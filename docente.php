<?php
// ==========================================
// CARGA DE LÓGICA Y DATOS (MVC)
// ==========================================
require_once 'docente_core.php';
require_once 'docente_queries.php';

// ==========================================
// MIGRACIÓN AUTOMÁTICA: AGREGAR COLUMNA LINK
// ==========================================
// Verificar si existe la columna link_drive, si no, crearla
$checkLink = $conn->query("SHOW COLUMNS FROM aplicaciones_docente LIKE 'link_drive'");
if ($checkLink && $checkLink->num_rows == 0) {
    $conn->query("ALTER TABLE aplicaciones_docente ADD COLUMN link_drive TEXT DEFAULT NULL AFTER nombre_original");
    // Hacer nombre_archivo y nombre_original opcionales si antes no lo eran
    $conn->query("ALTER TABLE aplicaciones_docente MODIFY COLUMN nombre_archivo VARCHAR(255) NULL");
    $conn->query("ALTER TABLE aplicaciones_docente MODIFY COLUMN nombre_original VARCHAR(255) NULL");
}

// ==========================================
// LOGICA GUARDAR LINK (REEMPLAZA SUBIDA)
// ==========================================
if ($es_docente && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_link_app'])) {
        $link = esc($_POST['link_drive']);
        $ver  = esc($_POST['version_app']);
        
        if (filter_var($link, FILTER_VALIDATE_URL)) {
            // Desactivar link anterior
            $conn->query("UPDATE aplicaciones_docente SET activo = 0 WHERE docente_id = $usuario_id");
            
            // Insertar nuevo registro solo con el link
            $sql = "INSERT INTO aplicaciones_docente (docente_id, link_drive, version, activo, fecha_subida) 
                    VALUES ($usuario_id, '$link', '$ver', 1, NOW())";
            
            if ($conn->query($sql)) {
                registrar_log($usuario_id, $usuario_nombre, 'docente', 'ACTUALIZAR_APP', 'Aplicacion', $conn->insert_id, "Link Drive actualizado v$ver");
                redirigirConMensaje("<div class='alert-success'>✅ Link de la aplicación actualizado correctamente.</div>");
            } else {
                redirigirConMensaje("<div class='alert-error'>Error al guardar en base de datos.</div>");
            }
        } else {
            redirigirConMensaje("<div class='alert-error'>⚠️ El formato del enlace no es válido. Asegúrate de incluir https://</div>");
        }
    }
    
    if (isset($_POST['eliminar_aplicacion'])) {
        $app_id = (int)$_POST['app_id'];
        // Eliminar registro lógico
        $conn->query("DELETE FROM aplicaciones_docente WHERE id = $app_id AND docente_id = $usuario_id");
        registrar_log($usuario_id, $usuario_nombre, 'docente', 'ELIMINAR_APP', 'Aplicacion', $app_id, "Eliminó el link de la app");
        redirigirConMensaje("<div class='alert-success'>Aplicación eliminada.</div>");
    }
}

// Recargar datos de la app actual después del POST
$aplicacion_actual = null;
$res_app = $conn->query("SELECT * FROM aplicaciones_docente WHERE docente_id = $usuario_id AND activo = 1 ORDER BY fecha_subida DESC LIMIT 1");
if ($res_app && $res_app->num_rows > 0) {
    $aplicacion_actual = $res_app->fetch_assoc();
}

// Datos de perfil
$usuario_email   = '';
$perfil_pais     = 'Ecuador';
$perfil_timezone = date_default_timezone_get();
$qInfoPerfil = $conn->query("SELECT email FROM usuarios WHERE id = $usuario_id LIMIT 1");
if ($qInfoPerfil && $qInfoPerfil->num_rows > 0) {
    $usuario_email = htmlspecialchars($qInfoPerfil->fetch_assoc()['email'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión Académica - Panel Docente</title>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    /* =========================================
       ESTILOS ORIGINALES RESTAURADOS
       ========================================= */
    :root { --primary: #0f6cb6; --bg-body: #f2f2f2; --text-color: #373a3c; }
    body { font-family: 'Open Sans', sans-serif; background-color: var(--bg-body); color: var(--text-color); margin: 0; padding: 0; font-size: 0.9rem; }
    
    /* NAVBAR */
    .navbar { background-color: #fff; height: 60px; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); position: fixed; top: 0; width: 100%; z-index: 1000; box-sizing: border-box; }
    .navbar-brand { font-weight: 700; color: var(--primary); font-size: 1.4rem; text-decoration: none; }
    .navbar-right { display: flex; align-items: center; gap: 12px; }

    /* USER MENU */
    .user-menu-container { position: relative; }
    .user-menu-btn { display:flex; align-items:center; gap:10px; background: transparent; border:1px solid #e5e7eb; border-radius: 999px; padding:6px 10px; cursor:pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.08); }
    .user-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg, #0f6cb6 0%, #0b5591 100%); color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.9rem; }
    .user-name-group { display:flex; flex-direction:column; align-items:flex-start; }
    .user-name-group .user-name { font-weight:600; color:#111; font-size:0.9rem; line-height:1.2; }
    .user-name-group .user-role { font-size:0.75rem; color:#6b7280; line-height:1.1; }
    /* Caret visual ~mitad del avatar (34-35px) con mayor peso */
    .user-caret { font-size:1.35rem; width:21px; height:21px; color:#475569; margin-left:4px; line-height:1; display:inline-flex; align-items:center; justify-content:center; }
    .user-menu { position:absolute; right:0; top:115%; background:#fff; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,0.12); min-width:170px; display:none; overflow:hidden; z-index:1200; }
    .user-menu.show { display:block; }
    .user-menu-item { display:block; padding:10px 14px; color:#111827; text-decoration:none; font-size:0.9rem; }
    .user-menu-item:hover { background:#f3f4f6; }
    
    /* LAYOUT */
    .main-wrapper { display: flex; margin-top: 60px; min-height: calc(100vh - 60px); }
    
    /* SIDEBAR MODIFICADO PARA STICKY FOOTER */
    .sidebar { 
        width: 300px; 
        background-color: #f4f4f4; 
        border-right: 1px solid #ddd; 
        flex-shrink: 0; 
        height: calc(100vh - 60px); 
        position: fixed; 
        display: flex;
        flex-direction: column;
    }

    /* Contenido scrolleable (Cursos) */
    .sidebar-scrollable {
        flex: 1;
        overflow-y: auto;
        padding: 20px 0;
    }

    /* Footer fijo (Gestión) */
    .sidebar-sticky-footer {
        flex-shrink: 0;
        background-color: #e9ecef;
        border-top: 1px solid #d1d5db;
        padding: 10px 0 20px 0;
        box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.05);
        z-index: 10;
    }

    .nav-title { padding: 0 20px; font-size: 0.8rem; font-weight: 700; color: #666; text-transform: uppercase; margin-bottom: 10px; margin-top: 20px; }
    .sidebar-sticky-footer .nav-title { margin-top: 10px; color: #4b5563; }

    .nav-link { display: block; padding: 10px 20px; color: #333; text-decoration: none; border-left: 4px solid transparent; transition: 0.2s; cursor: pointer; }
    .nav-link:hover { background-color: #e9e9e9; }
    .nav-link.active { background-color: #fff; border-left-color: var(--primary); font-weight: 600; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }

    /* ESTILOS ESPECÍFICOS PARA CURSOS EN SIDEBAR */
    .sidebar .link-curso {
        margin: 8px 15px;
        padding: 12px 15px;
        border-radius: 8px;
        background: #ffffff;
        border: 1px solid #e0e0e0;
        border-left: 4px solid #adadad;
        color: #444;
        font-weight: 600;
        font-size: 0.85rem;
        box-shadow: 0 2px 5px rgba(0,0,0,0.03);
        transition: all 0.2s ease-in-out;
        display: flex; align-items: center; gap: 10px; text-decoration: none;
    }
    .sidebar .link-curso:hover {
        background: #fff; border-color: var(--primary); border-left-color: var(--primary);
        color: var(--primary); transform: translateX(4px); box-shadow: 0 4px 8px rgba(0,0,0,0.08);
    }
    .sidebar .link-curso.active {
        background: var(--primary); color: #fff; border-color: var(--primary); border-left-color: #0a4b7f;
        box-shadow: 0 4px 10px rgba(15, 108, 182, 0.4);
    }
    
    /* CONTENT */
    .page-content { flex: 1; padding: 30px; margin-left: 300px; background-color: #fff; min-height: calc(100vh - 60px); }
    .welcome-banner { margin-bottom: 30px; }
    .welcome-banner h1 { margin: 0; font-weight: 300; color: #333; font-size: 1.8rem; }
    
    /* SECTIONS */
    .course-section { border: 1px solid #e1e1e1; border-radius: 4px; margin-bottom: 25px; background: #fff; }
    .course-section-header { padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #e1e1e1; display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
    .course-section-header h3 { margin: 0; font-size: 1.1rem; color: #333; display: flex; align-items: center; gap: 10px; }
    .course-section-body { padding: 20px; }
    .course-section.closed .course-section-body { display: none; }
    
    /* TABLES */
    table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    th { text-align: left; padding: 10px; background: #f5f5f5; border-bottom: 2px solid #ddd; font-weight: 600; color: #555; }
    td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
    
    /* BUTTONS & FORMS */
    .btn { padding: 6px 12px; border-radius: 4px; border: 1px solid transparent; cursor: pointer; font-size: 0.9rem; text-decoration: none; display: inline-block; }
    .btn-primary { background-color: #0f6cb6; color: white; }
    .btn-primary:hover { background-color: #0b5591; }
    .btn-success { background-color: #10b981; color: white; }
    .btn-success:hover { background-color: #059669; }
    .btn-sm { padding: 4px 8px; font-size: 0.8rem; }
    .btn-outline { background: #fff; border-color: #ccc; color: #333; }
    .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .form-row { display: flex; gap: 15px; margin-bottom: 10px; }
    .form-group { flex: 1; }
    label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: #555; }
    
    /* ALERTS */
    .alert-success { background: #d4edda; color: #155724; padding:10px; border-radius:4px; margin-bottom:15px; }
    .alert-error { background: #f8d7da; color: #721c24; padding:10px; border-radius:4px; margin-bottom:15px; }
    .alert-warning { background: #fff3cd; color: #856404; padding:10px; border-radius:4px; margin-bottom:15px; }
    
    /* MODALS */
    .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; }
    .modal-content { background: white; padding: 20px; border-radius: 5px; width: 500px; max-height: 90vh; overflow-y: auto; }
    .modal-wide { width: 90%; max-width: 1000px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 15px; }
    .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #777; }
    
    /* BADGES */
    .status-badge { padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: bold; }
    .status-activo { background: #dcfce7; color: #166534; }
    .status-eliminado { background: #fee2e2; color: #991b1b; }
    .status-pendiente { background: #f3f4f6; color: #4b5563; }
    .notif-seguridad-badge {
        display: none;
        min-width: 20px;
        height: 20px;
        padding: 0 6px;
        border-radius: 999px;
        background: #dc2626;
        color: #fff;
        font-size: 0.72rem;
        font-weight: 700;
        line-height: 1;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 8px rgba(220, 38, 38, 0.35);
    }
    
    /* TAGS DE NAVEGACIÓN */
    .tag-secuencial { background: #f3e8ff; color: #7e22ce; border: 1px solid #d8b4fe; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; margin-left: 5px; font-weight: normal; vertical-align: middle; }
    .tag-libre { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; margin-left: 5px; font-weight: normal; vertical-align: middle; }

    /* User Role Badges */
    .rol-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
    .rol-docente { background: #e0f2fe; color: #0369a1; }
    .rol-director-carrera { background: #fef3c7; color: #92400e; }
    .rol-director-area { background: #ede9fe; color: #6d28d9; }
    
    /* User Info Header */
    .user-info-header { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
    .user-name { font-weight: 600; font-size: 0.9rem; color: #333; }
    .user-role { font-size: 0.7rem; color: #666; }
    
    /* Links Docentes */
    .link-doc { display: block; padding: 12px 15px; margin-bottom: 8px; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; text-decoration: none; color: #333; transition: all 0.2s; cursor: pointer; }
    .link-doc:hover { background: #f8f9fa; border-color: var(--primary); transform: translateX(3px); }
    .link-doc.active { background: var(--primary); color: #fff; border-color: var(--primary); }
    .link-doc.active small { color: rgba(255,255,255,0.8); }
    .link-doc.active .area-txt { color: rgba(255,255,255,0.9) !important; }
    
    /* Area Badge */
    .area-badge { display: inline-block; background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%); color: white; padding: 2px 8px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; margin-left: 5px; }

    /* PERFIL */
    .profile-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; }
    .profile-field { background:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; padding:12px; }
    .profile-field label { font-size:0.8rem; color:#6b7280; margin:0 0 4px 0; font-weight:700; }
    .profile-field p { margin:0; color:#111827; font-weight:600; }
    .profile-form .form-control { max-width:420px; }
    
    /* NUEVOS ESTILOS PARA CALIFICACIONES */
    .calificaciones-header {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        padding: 15px;
        margin-bottom: 20px;
        border: 1px solid #e1e1e1;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .calificaciones-info h4 { margin: 0 0 5px 0; color: var(--primary); font-size: 1.1rem; }
    .calificaciones-info p { margin: 0; color: #666; font-size: 0.9rem; }
    
    .calificaciones-filter select {
        padding: 8px 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 0.9rem;
        background-color: #f8f9fa;
        cursor: pointer;
    }

    .grade-table {
        width: 100%;
        border-collapse: separate; 
        border-spacing: 0;
        border: 1px solid #e1e1e1;
        border-radius: 8px;
        overflow: hidden;
    }
    .grade-table th {
        background-color: #f8f9fa;
        padding: 12px;
        text-align: center;
        border-bottom: 2px solid #e1e1e1;
        border-right: 1px solid #f1f1f1;
        font-weight: 700;
        color: #444;
        font-size: 0.85rem;
    }
    .grade-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #f1f1f1;
        border-right: 1px solid #f1f1f1;
        text-align: center;
        font-size: 0.9rem;
    }
    .grade-table tr:last-child td { border-bottom: none; }
    .grade-table tr:hover { background-color: #f1f5f9; }
    
    .col-p1-header { background-color: #eff6ff !important; color: #1e3a8a !important; border-bottom-color: #bfdbfe !important; }
    .col-p2-header { background-color: #f0fdf4 !important; color: #14532d !important; border-bottom-color: #bbf7d0 !important; }
    
    .td-promedio { font-weight: bold; }

    /* Estilos Revisión */
    .revision-q { margin-bottom: 20px; padding: 15px; border: 1px solid #eee; border-radius: 5px; background: #fff; }
    .revision-q h4 { margin: 0 0 10px 0; color: #333; font-size: 1rem; }
    .revision-opt { padding: 8px; margin-bottom: 5px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
    .opt-correcta { background-color: #d1fae5; border: 1px solid #10b981; }
    .opt-incorrecta-user { background-color: #fee2e2; border: 1px solid #ef4444; }
    .opt-user { font-weight: bold; }

    /* =========================================
       ESTILOS PARA MODIFICAR TIEMPO EN TIEMPO REAL
       ========================================= */
    .btn-tiempo-add {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
    }
    .btn-tiempo-add:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
    .btn-tiempo-reduce {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
    }
    .btn-tiempo-reduce:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
    }
    .tiempo-info-box {
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border: 1px solid #7dd3fc;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .tiempo-estudiantes-box {
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border: 1px solid #fbbf24;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 20px;
        text-align: center;
    }

    /* =========================================
       MENSAJERIA DOCENTE
       ========================================= */
    .chat-wrapper {
        display: flex;
        gap: 16px;
        align-items: stretch;
        background: linear-gradient(180deg, #f8fafc 0%, #ffffff 70%);
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 12px;
    }
    .chat-students {
        width: 280px;
        min-width: 240px;
        border-right: 1px solid #e5e7eb;
        padding-right: 12px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .chat-student-list { margin-top: 4px; max-height: 360px; overflow-y: auto; padding-right: 4px; }
    .chat-student {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: 0.2s;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .chat-student:hover { border-color: var(--primary); box-shadow: 0 2px 8px rgba(0,0,0,0.08); transform: translateY(-1px); }
    .chat-student.active { background: var(--primary); color: #fff; border-color: var(--primary); }
    .chat-student.has-new { border-color: #22c55e; box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.08); }
    .chat-student-info { display: flex; flex-direction: column; gap: 2px; }
    .chat-student-info small { color: #6b7280; }
    .chat-student.active .chat-student-info small { color: rgba(255,255,255,0.8); }
    .chat-student-meta { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
    .chat-time { font-size: 0.7rem; color: #9ca3af; }
    .chat-badge { font-size: 0.65rem; background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 999px; }
    .chat-student.active .chat-badge { background: rgba(255,255,255,0.2); color: #fff; }
    .chat-panel { flex: 1; display: flex; flex-direction: column; gap: 8px; min-width: 0; }
    .chat-panel-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .chat-header { font-weight: 700; color: #1f2937; }
    .chat-actions { display: flex; gap: 8px; align-items: center; }
    .chat-broadcast {
        border: 1px solid #e5e7eb;
        background: #f8fafc;
        border-radius: 10px;
        padding: 10px;
    }
    .chat-broadcast-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .chat-broadcast-title { font-weight: 600; color: #111827; }
    .chat-broadcast-sub { font-size: 0.8rem; color: #6b7280; }
    .chat-broadcast-body { margin-top: 8px; display: none; }
    .chat-broadcast-body textarea { width: 100%; resize: vertical; min-height: 70px; max-height: 140px; }
    .chat-broadcast-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .chat-broadcast-info { font-size: 0.75rem; color: #6b7280; }
    .chat-broadcast-status { font-size: 0.75rem; color: #16a34a; }
    .chat-broadcast-status.error { color: #b91c1c; }
    .chat-notice {
        display: none;
        padding: 8px 10px;
        border: 1px solid #7dd3fc;
        background: #e0f2fe;
        color: #0369a1;
        border-radius: 8px;
        font-size: 0.85rem;
    }
    .chat-notice.show { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .chat-log {
        flex: 1;
        min-height: 240px;
        max-height: 360px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 12px;
    }
    .chat-empty { color: #888; text-align: center; padding: 20px 0; }
    .chat-row { display: flex; margin-bottom: 10px; }
    .chat-row.docente { justify-content: flex-end; }
    .chat-bubble { max-width: 70%; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px 10px; }
    .chat-row.docente .chat-bubble { background: #dcfce7; border-color: #86efac; }
    .chat-meta { font-size: 0.7rem; color: #6b7280; margin-top: 4px; display: flex; align-items: center; gap: 6px; justify-content: space-between; }
    .chat-delete { background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 0.85rem; }
    .chat-input { margin-top: 4px; display: flex; gap: 8px; align-items: flex-start; }
    .chat-input textarea { flex: 1; resize: vertical; min-height: 44px; max-height: 100px; }
    .btn-outline-danger { background: #fff; border-color: #fca5a5; color: #b91c1c; }
    .btn-outline-danger:hover { background: #fee2e2; }
    .btn-outline-danger:disabled { opacity: 0.6; cursor: not-allowed; }
    @media (max-width: 900px) {
        .chat-wrapper { flex-direction: column; }
        .chat-students { width: 100%; border-right: none; padding-right: 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
    }
</style>
</head>
<body>

<!-- DATA JSON PARA JS -->
<script>
    const estudiantesData = <?php echo json_encode($estudiantes_por_curso ?: new stdClass(), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
    const intentosData    = <?php echo json_encode($intentos_por_examen ?: new stdClass(), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
    const examenesData    = <?php echo json_encode($examenes_por_curso ?: new stdClass(), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
    const notificacionesSeguridadData = <?php echo json_encode($notificaciones_seguridad_por_curso ?: new stdClass(), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
    const cursosDocente   = <?php echo json_encode($mis_cursos ?: [], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
    const docentesData    = <?php echo json_encode($datos_carrera ?: new stdClass(), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
    const docentesAreaData = <?php echo json_encode($datos_area ?: new stdClass(), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
    const misAreas = <?php echo json_encode($mis_areas ?: [], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
</script>

<nav class="navbar">
    <a href="#" class="navbar-brand" style="display:flex; align-items:center; gap:10px;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6d/LogoUGcolor.png" alt="Logo UG" style="height:32px; width:auto; display:block;">
        <span style="font-weight:400; font-size:0.95rem; color:#666;">Panel Docente</span>
    </a>
    <div class="navbar-right">
        <div class="user-menu-container">
            <button class="user-menu-btn" onclick="toggleUserMenu(event)">
                <div class="user-name-group">
                    <span class="user-name"><?= $usuario_nombre ?></span>
                    <span class="user-role"><?= $rol_display ?></span>
                </div>
                <div class="user-avatar"><?= $iniciales ?></div>
                <span class="user-caret">▾</span>
            </button>
            <div class="user-menu" id="userMenu">
                <a href="javascript:void(0)" class="user-menu-item" onclick="verPerfil(); toggleUserMenu();">Perfil</a>
                <a href="logout.php" class="user-menu-item">Cerrar sesión</a>
            </div>
        </div>
    </div>
</nav>

<div class="main-wrapper">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <!-- Parte SCROLLABLE: Navegación y Cursos -->
        <div class="sidebar-scrollable">
            <div class="nav-title">Navegación</div>
            <a href="javascript:void(0)" onclick="irAreaPersonal()" class="nav-link active" id="link-area-personal">🏠 Área personal</a>

            <div class="nav-title">Mis Cursos</div>
            <?php if(empty($mis_cursos)): ?>
                <div style="padding:0 20px; color:#888; font-size:0.8rem;">Sin cursos.</div>
            <?php else: foreach($mis_cursos as $c): ?>
                <?php $titulo_completo = htmlspecialchars($c['ciclo']) . " - " . htmlspecialchars($c['materia']) . " (" . htmlspecialchars($c['paralelo']) . ")"; ?>
                <a href="#" class="nav-link link-curso" data-id="<?= $c['id_oferta'] ?>" onclick="verCurso(event, <?= $c['id_oferta'] ?>, '<?= $titulo_completo ?>')">
                    <div style="display:flex; flex-direction:column; align-items:flex-start; width:100%;">
                        <small style="font-size:0.75em; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:0.5px;">
                            <?= htmlspecialchars($c['ciclo']) ?>
                        </small>
                        <span style="font-size:0.95rem; font-weight:600; color:#333; margin:2px 0;">
                            <?= htmlspecialchars($c['materia']) ?>
                        </span>
                        <small style="font-size:0.8em; color:#666;">
                            Paralelo: <?= htmlspecialchars($c['paralelo']) ?>
                        </small>
                    </div>
                </a>
            <?php endforeach; endif; ?>

            <!-- Sección de Aplicación -->
            <div class="nav-title" style="margin-top:25px;">🔗 Link Aplicación</div>
            <div style="padding: 10px 15px; background: #f8f9fa; margin: 0 10px 15px; border-radius: 8px; border: 1px solid #e9ecef;">
                <?php if($aplicacion_actual && !empty($aplicacion_actual['link_drive'])): ?>
                    <div style="margin-bottom:10px;">
                        <span style="font-size:0.85rem; font-weight:600; color:#059669;">✅ Link Configurado</span>
                        <div style="font-size:0.75rem; color:#666; margin-top:3px;">
                            Versión: <?= htmlspecialchars($aplicacion_actual['version']) ?>
                            <br>
                            <a href="<?= htmlspecialchars($aplicacion_actual['link_drive']) ?>" target="_blank" style="color:#0f6cb6; text-decoration:none;">🔗 Probar link</a>
                        </div>
                    </div>
                    <form method="POST" style="margin-bottom:8px;">
                        <input type="hidden" name="app_id" value="<?= $aplicacion_actual['id'] ?>">
                        <button type="submit" name="eliminar_aplicacion" onclick="return confirm('¿Eliminar el enlace actual?')" style="width:100%; padding:6px; font-size:0.8rem; background:#ef4444; color:white; border:none; border-radius:4px; cursor:pointer;">🗑️ Eliminar Link</button>
                    </form>
                <?php else: ?>
                    <div style="font-size:0.8rem; color:#666; margin-bottom:10px;">No has configurado el link de descarga.</div>
                <?php endif; ?>
                <button type="button" onclick="document.getElementById('modalSubirApp').style.display='flex'" style="width:100%; padding:8px; font-size:0.85rem; font-weight:600; background:linear-gradient(135deg, #059669 0%, #047857 100%); color:white; border:none; border-radius:6px; cursor:pointer;">
                    🔗 Configurar Link
                </button>
            </div>
        </div>

        <!-- Parte STICKY FOOTER: Gestión Administrativa -->
        <?php if($es_director_carrera || $es_director_area): ?>
        <div class="sidebar-sticky-footer">
            <?php if($es_director_carrera): ?>
                <div class="nav-title">Gestión de Carrera</div>
                <a href="#" class="nav-link" id="link-panel-director" onclick="verPanelDirector(event)">👥 Panel Director Carrera</a>
            <?php endif; ?>

            <?php if($es_director_area): ?>
                <div class="nav-title">Gestión de Área</div>
                <a href="#" class="nav-link" id="link-panel-director-area" onclick="verPanelDirectorArea(event)">📊 Panel Director Área</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- MAIN CONTENT -->
    <div class="page-content" id="main-container">
        <?= $mensaje_sistema ?>

        <!-- 1. VISTA INICIAL (DASHBOARD) -->
        <div id="default-view">
            <div class="welcome-banner">
                <div style="color:var(--primary); font-size:0.9rem; margin-bottom:5px;">Área personal</div>
                <h1>¡Hola, <?= $primer_nombre ?>! 👋</h1>
                <p style="color:#666; margin-top:5px;">
                    <span class="rol-badge <?= $es_director_carrera ? 'rol-director-carrera' : ($es_director_area ? 'rol-director-area' : 'rol-docente') ?>"><?= $rol_display ?></span>
                    <?php if($es_director_area && !empty($mis_areas)): foreach($mis_areas as $area): ?>
                        <span class="area-badge"><?= htmlspecialchars($area) ?></span>
                    <?php endforeach; endif; ?>
                </p>
            </div>

            <div class="course-section">
                <div class="course-section-header"><h3>📊 Resumen de Mis Cursos</h3></div>
                <div class="course-section-body">
                    <?php if(empty($mis_cursos)): ?>
                        <p style="color:#888; text-align:center;">No tienes cursos asignados.</p>
                    <?php else: ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                            <?php foreach($mis_cursos as $curso): 
                                $cid = $curso['id_oferta'];
                                $stats = $estadisticas_cursos[$cid] ?? ['total_estudiantes' => 0, 'promedio_p1' => null, 'promedio_p2' => null];
                            ?>
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 15px; color: white; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3); cursor: pointer; transition: transform 0.2s; width: 280px; flex-shrink: 0;" onclick="verCurso(null, <?= $cid ?>, '<?= htmlspecialchars($curso['materia']) ?>')" onmouseover="this.style.transform='translateY(-3px)';" onmouseout="this.style.transform='translateY(0)';">
                                <div style="margin-bottom: 10px;">
                                    <div style="font-size: 0.7rem; opacity: 0.9;"><?= htmlspecialchars($curso['ciclo']) ?></div>
                                    <h3 style="margin: 3px 0; font-size: 1rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($curso['materia']) ?></h3>
                                    <div style="font-size: 0.8rem; opacity: 0.85;">📍 <?= htmlspecialchars($curso['paralelo']) ?></div>
                                </div>
                                <div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 10px; text-align: center; margin-bottom: 10px;">
                                    <div style="font-size: 2rem; font-weight: 800; line-height: 1;"><?= $stats['total_estudiantes'] ?></div>
                                    <div style="font-size: 0.75rem; opacity: 0.9;">ESTUDIANTES</div>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <div style="flex:1; background: rgba(255,255,255,0.15); border-radius: 8px; padding: 8px; text-align: center;">
                                        <div style="font-size: 0.65rem; opacity: 0.85; margin-bottom: 3px;">Primer P.</div>
                                        <div style="font-size: 1.4rem; font-weight: 700;"><?= $stats['promedio_p1'] !== null ? number_format($stats['promedio_p1'], 2) : '---' ?></div>
                                    </div>
                                    <div style="flex:1; background: rgba(255,255,255,0.15); border-radius: 8px; padding: 8px; text-align: center;">
                                        <div style="font-size: 0.65rem; opacity: 0.85; margin-bottom: 3px;">Segundo P.</div>
                                        <div style="font-size: 1.4rem; font-weight: 700;"><?= $stats['promedio_p2'] !== null ? number_format($stats['promedio_p2'], 2) : '---' ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PERFIL DOCENTE -->
        <div id="profile-view" style="display:none;">
            <div class="welcome-banner">
                <div style="color:var(--primary); font-size:0.9rem;">Perfil de usuario</div>
                <h1><?= $usuario_nombre ?></h1>
            </div>

            <?php if(!empty($mensaje_pass)): ?>
                <?= $mensaje_pass ?>
            <?php endif; ?>

            <div class="course-section">
                <div class="course-section-header"><h3>Detalles de usuario</h3></div>
                <div class="course-section-body">
                    <div class="profile-grid">
                        <div class="profile-field"><label>Nombre completo</label><p><?= $usuario_nombre ?></p></div>
                        <?php if(!empty($usuario_email)): ?><div class="profile-field"><label>Dirección de correo</label><p><a href="mailto:<?= $usuario_email ?>"><?= $usuario_email ?></a></p></div><?php endif; ?>
                        <div class="profile-field"><label>Rol</label><p><?= $rol_display ?></p></div>
                        <div class="profile-field"><label>Zona horaria</label><p><?= $perfil_timezone ?></p></div>
                        <div class="profile-field"><label>País</label><p><?= $perfil_pais ?></p></div>
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
                        <button type="submit" name="cambiar_clave_docente" class="btn btn-primary">Guardar cambios</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 2. VISTA CURSO -->
        <div id="course-view" style="display:none;">
            <h2 id="course-title" style="color:var(--primary); border-bottom:2px solid #eee; padding-bottom:10px; margin-bottom:20px;"></h2>

            <!-- GESTIÓN DE EXÁMENES -->
            <div class="course-section closed">
                <div class="course-section-header" onclick="toggleSection(this)">
                    <h3>📝 Gestión de Exámenes</h3><span>▼</span>
                </div>
                <div class="course-section-body">
                    <div style="background:#f8f9fa; padding:15px; border:1px solid #eee; border-radius:4px; margin-bottom:20px;">
                        <h4 style="margin-top:0; color:#444;">Crear Nuevo Examen</h4>
                        <div style="display:flex; gap:0; margin-bottom:20px; border-bottom:2px solid #e5e7eb;">
                            <button type="button" id="tabManual" onclick="cambiarTabExamen('manual')" style="padding:12px 20px; border:none; background:#0f6cb6; color:white; font-weight:bold; cursor:pointer; border-radius:8px 8px 0 0; font-size:0.9rem;">🖱️ Inicio Manual</button>
                            <button type="button" id="tabProgramado" onclick="cambiarTabExamen('programado')" style="padding:12px 20px; border:none; background:#e5e7eb; color:#666; font-weight:bold; cursor:pointer; border-radius:8px 8px 0 0; font-size:0.9rem;">⏰ Inicio Programado</button>
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="curso_id" id="input_curso_id_examen">
                            <input type="hidden" name="modo_examen" id="modo_examen" value="manual">
                            
                            <div class="form-row">
                                <div class="form-group" style="flex:2;"><label>Título del Examen:</label><input type="text" name="titulo" class="form-control" placeholder="Ej: Evaluación Unidad 1" required></div>
                                <div class="form-group"><label>Parcial:</label><select name="parcial" class="form-control" required><option value="primer_parcial">📘 Primer Parcial</option><option value="segundo_parcial">📗 Segundo Parcial</option></select></div>
                                <div class="form-group"><label>Archivo (.docx):</label><input type="file" id="archivo_docx" name="archivo_docx" accept=".docx" class="form-control" required></div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group"><label>⏱️ Duración (minutos):</label><input type="number" id="duracion_minutos" name="duracion_minutos" class="form-control" value="60" min="1"></div>
                                <div class="form-group"><label title="0 = mostrar todas"># Preguntas:</label><input type="number" id="input_cant_preguntas" name="cant_preguntas" class="form-control" value="0" min="0"><small id="lbl_preguntas_detectadas" style="color:#0f6cb6;font-weight:600;"></small></div>
                            </div>

                            <div id="divFechasManual" style="background:#e0f2fe; border:1px solid #7dd3fc; padding:12px; border-radius:6px; margin-bottom:15px;">
                                <p style="margin:0 0 5px; font-size:0.85rem; color:#0369a1;"><strong>🖱️ Modo Manual:</strong> Define ventana de disponibilidad.</p>
                                <div class="form-row"><div class="form-group"><label>Desde:</label><input type="datetime-local" id="fecha_inicio" name="fecha_inicio" class="form-control"></div><div class="form-group"><label>Hasta:</label><input type="datetime-local" id="fecha_fin" name="fecha_fin" class="form-control"></div></div>
                            </div>

                            <div id="divFechasProg" style="display:none; background:#dcfce7; border:1px solid #86efac; padding:12px; border-radius:6px; margin-bottom:15px;">
                                <p style="margin:0 0 5px; font-size:0.85rem; color:#166534;"><strong>⏰ Modo Programado:</strong> Se activa automáticamente.</p>
                                <div class="form-row"><div class="form-group"><label>📅 Inicio automático:</label><input type="datetime-local" id="inicio_programado" name="inicio_programado" class="form-control"></div><div class="form-group"><label>🚫 Fecha límite:</label><input type="datetime-local" id="fecha_fin_prog" name="fecha_fin_prog" class="form-control"></div></div>
                            </div>
                            
                            <button type="submit" name="crear_examen" class="btn btn-primary" id="btnCrearExamen">💾 Guardar Examen</button>
                        </form>
                    </div>
                    <div id="exam-list-container"></div>
                </div>
            </div>

            <!-- MIS ESTUDIANTES -->
            <div class="course-section closed">
                <div class="course-section-header" onclick="toggleSection(this)">
                    <h3>👥 Mis Estudiantes</h3><span>▼</span>
                </div>
                <div class="course-section-body">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <div>
                            <form method="POST" enctype="multipart/form-data" style="display:inline-flex; gap:10px; align-items:center;">
                                <input type="hidden" name="curso_id_estudiantes" id="input_curso_id_est">
                                <input type="file" name="archivo_estudiantes" class="form-control" accept=".xlsx,.csv" style="width:auto; padding:4px;" required>
                                <button type="submit" name="importar_estudiantes" class="btn btn-outline btn-sm">📥 Importar</button>
                            </form>
                        </div>
                        <button id="btn-ver-notas" class="btn btn-primary" onclick="verNotas(currentCurso)">📊 Ver Calificaciones</button>
                    </div>
                    <div id="student-list-container"></div>
                </div>
            </div>
            <!-- MENSAJERIA -->
            <div class="course-section closed">
                <div class="course-section-header" onclick="toggleSection(this)">
                    <h3>💬 Mensajeria</h3><span>▼</span>
                </div>
                <div class="course-section-body">
                    <div class="chat-wrapper">
                        <div class="chat-students">
                            <input type="text" id="chatSearch" class="form-control" placeholder="Buscar estudiante..." onkeyup="filtrarChatEstudiantes()">
                            <div id="chatStudentList" class="chat-student-list"></div>
                        </div>
                            <div class="chat-panel">
                                <div class="chat-panel-top">
                                    <div class="chat-header" id="chatHeader">Selecciona un estudiante para iniciar</div>
                                    <div class="chat-actions">
                                        <button class="btn btn-sm btn-outline btn-outline-danger" id="chatDeleteAllBtn" type="button" onclick="borrarChatsCurso()" disabled>Borrar todos</button>
                                        <button class="btn btn-sm btn-outline btn-outline-danger" id="chatDeleteBtn" type="button" onclick="borrarChatDocente()" disabled>Borrar chat</button>
                                    </div>
                                </div>
                            <div class="chat-broadcast">
                                <div class="chat-broadcast-header">
                                    <div>
                                        <div class="chat-broadcast-title">Mensaje masivo al curso</div>
                                        <div class="chat-broadcast-sub">Se enviara a todos los estudiantes del curso actual.</div>
                                    </div>
                                    <button class="btn btn-sm btn-outline" id="chatBroadcastToggle" type="button" onclick="toggleBroadcastPanel()">Mostrar</button>
                                </div>
                                <div class="chat-broadcast-body" id="chatBroadcastBody">
                                    <textarea id="chatBroadcastInput" class="form-control" placeholder="Escribe el mensaje para todo el curso..." disabled></textarea>
                                    <div class="chat-broadcast-actions">
                                        <button class="btn btn-primary" id="chatBroadcastSend" type="button" onclick="enviarMensajeMasivoDocente()" disabled>Enviar a todos</button>
                                        <span class="chat-broadcast-info" id="chatBroadcastInfo"></span>
                                        <span class="chat-broadcast-status" id="chatBroadcastStatus"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="chat-notice" id="chatNotice"></div>
                            <div class="chat-log" id="chatLog">
                                <div class="chat-empty">Sin mensajes.</div>
                            </div>
                            <div class="chat-input">
                                <textarea id="chatInput" class="form-control" placeholder="Escribe un mensaje..." onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();enviarMensajeDocente();}" disabled></textarea>
                                <button class="btn btn-success" id="chatSendBtn" type="button" onclick="enviarMensajeDocente()" disabled>Enviar</button>
                            </div>
                            <small style="color:#888; margin-top:6px;">Solo puedes borrar los mensajes que enviaste. Borrar chat elimina el historial.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NOTIFICACIONES DE SEGURIDAD -->
            <div class="course-section closed">
                <div class="course-section-header" onclick="toggleSection(this)">
                    <h3 style="display:flex; align-items:center; gap:8px;">
                        🔔 Notificaciones de Seguridad
                        <span id="notifSeguridadBadge" class="notif-seguridad-badge">0</span>
                    </h3><span>▼</span>
                </div>
                <div class="course-section-body">
                    <div style="display:flex; justify-content:flex-end; margin-bottom:10px;">
                        <button type="button" id="btnOcultarNotifSeguridad" class="btn btn-sm btn-outline" onclick="ocultarNotificacionesSeguridadCurso()" disabled>
                            🧹 Limpiar del panel docente
                        </button>
                    </div>
                    <div id="notificaciones-list-container"></div>
                </div>
            </div>
        </div>

        <!-- 3. VISTA DIRECTOR -->
        <div id="director-view" style="display:none;">
            <div class="welcome-banner">
                <div style="color:var(--primary); font-size:0.9rem;">Gestión</div><h1>Panel de Director de Carrera</h1>
            </div>
            <div class="course-section"><div class="course-section-body" style="display:flex; gap:20px;">
                <div style="width:300px; border-right:1px solid #eee; padding-right:20px;">
                    <input type="text" id="sDoc" class="form-control" placeholder="🔍 Buscar docente..." onkeyup="filtrarDocentes('lDoc', this.value)">
                    <div id="lDoc" style="margin-top:15px; max-height:500px; overflow-y:auto;">
                        <?php foreach($datos_carrera as $idx=>$d): ?>
                            <div onclick="verDetalleDoc('lDoc', <?= $idx ?>)" class="link-doc" style="cursor:pointer;">
                                <strong><?= $d['nombres'] ?></strong><br>
                                <small style="color:#666;"><?= ucfirst(str_replace('_', ' ', $d['rol'])) ?></small>
                                <?php if(!empty($d['area'])): ?>
                                    <div class="area-txt" style="font-size:0.7rem; color:#6d28d9; font-weight:600; margin-top:2px;">
                                        <?= htmlspecialchars($d['area']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="flex:1;" id="det-doc"><div style="text-align:center; padding-top:50px; color:#888;">Selecciona un docente.</div></div>
            </div></div>
        </div>

        <!-- 4. VISTA DIRECTOR AREA -->
        <div id="director-area-view" style="display:none;">
            <div class="welcome-banner">
                <div style="color:#6d28d9; font-size:0.9rem;">Gestión de Área</div><h1>Panel de Director de Área</h1>
            </div>
            <div class="course-section"><div class="course-section-body" style="display:flex; gap:20px;">
                <div style="width:300px; border-right:1px solid #eee; padding-right:20px;">
                    <input type="text" id="sDocArea" class="form-control" placeholder="🔍 Buscar docente..." onkeyup="filtrarDocentes('lDocArea', this.value)">
                    <div id="lDocArea" style="margin-top:15px; max-height:500px; overflow-y:auto;">
                        <?php foreach($datos_area as $idx=>$d): ?>
                            <div onclick="verDetalleDoc('lDocArea', <?= $idx ?>)" class="link-doc" style="cursor:pointer;">
                                <strong><?= $d['nombres'] ?></strong><br>
                                <small style="color:#666;"><?= ucfirst(str_replace('_', ' ', $d['rol'])) ?></small>
                                <?php if(!empty($d['area'])): ?>
                                    <div class="area-txt" style="font-size:0.7rem; color:#6d28d9; font-weight:600; margin-top:2px;">
                                        <?= htmlspecialchars($d['area']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div style="flex:1;" id="det-doc-area"><div style="text-align:center; padding-top:50px; color:#888;">Selecciona un docente.</div></div>
            </div></div>
        </div>

    </div>
</div>

<!-- MODAL CONFIGURAR LINK DRIVE -->
<div id="modalSubirApp" class="modal"><div class="modal-content">
    <h3>🔗 Configurar Link de Aplicación</h3>
    
    <div style="background:#dbeafe; border:1px solid #93c5fd; padding:12px; border-radius:8px; margin-bottom:20px;">
        <p style="margin:0; font-size:0.85rem; color:#1e40af;">
            <strong>ℹ️ Información:</strong><br>
            Pega aquí el enlace público de Google Drive, OneDrive o Dropbox donde está alojada tu aplicación. Los estudiantes descargarán la app desde este link.
        </p>
    </div>
    
    <form method="POST">
        <div style="margin-bottom:15px;">
            <label style="display:block; font-weight:600; font-size:0.9rem; color:#333; margin-bottom:5px;">
                Enlace de descarga (URL):
            </label>
            <input type="url" name="link_drive" placeholder="https://drive.google.com/..." required
                   style="width:100%; padding:10px; border:2px solid #cbd5e1; border-radius:8px; background:#f8fafc; box-sizing:border-box;">
        </div>
        
        <div style="margin-bottom:20px;">
            <label style="display:block; font-weight:600; font-size:0.9rem; color:#333; margin-bottom:5px;">
                Versión:
            </label>
            <input type="text" name="version_app" placeholder="Ej: 1.0" value="1.0" required
                   style="width:100%; padding:10px; border:1px solid #cbd5e1; border-radius:6px; box-sizing:border-box;">
        </div>
        
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button type="button" onclick="document.getElementById('modalSubirApp').style.display='none'" 
                    style="padding:10px 20px; background:#e5e7eb; color:#374151; border:none; border-radius:6px; cursor:pointer; font-weight:600;">
                Cancelar
            </button>
            <button type="submit" name="guardar_link_app" 
                    style="padding:10px 20px; background:#059669; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:600;">
                💾 Guardar Link
            </button>
        </div>
    </form>
</div></div>

<div id="modalCalificaciones" class="modal"><div class="modal-content modal-wide">
    <div class="modal-header"><h3>Calificaciones</h3>
    <div style="display:flex; gap:10px;">
        <button onclick="exportarExcel()" class="btn btn-success" style="display:flex;align-items:center;gap:5px;">📊 Descargar Excel</button>
        <button class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</button>
    </div>
    </div>
    <div id="tablaCalificaciones"></div>
</div></div>

<div id="modalIntentos" class="modal"><div class="modal-content modal-wide">
    <div class="modal-header"><h3>Intentos</h3><button class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</button></div>
    <div id="listaIntentos"></div>
</div></div>

<div id="modalRevision" class="modal"><div class="modal-content" style="width:80%; height:90vh;">
    <div class="modal-header"><h3>Revisión</h3><button class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</button></div>
    <div id="contenidoRevision" style="height:calc(100% - 60px); overflow-y:auto;"></div>
</div></div>

<div id="modalVerPreguntas" class="modal"><div class="modal-content" style="width:80%; max-width:900px; height:80vh;">
    <div class="modal-header"><h3>Banco de Preguntas</h3><button class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</button></div>
    <div id="contenidoBancoPreguntas" style="padding:10px; overflow-y:auto; height:calc(100% - 60px);">Cargando...</div>
</div></div>

<!-- MODAL MODIFICAR TIEMPO EN TIEMPO REAL -->
<div id="modalModificarTiempo" class="modal">
    <div class="modal-content" style="width:550px;">
        <div class="modal-header">
            <h3>⏰ Modificar Tiempo del Examen</h3>
            <button class="close-btn" onclick="this.closest('.modal').style.display='none'">&times;</button>
        </div>
        
        <div class="tiempo-info-box">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <strong style="color:#0369a1;">📝 Examen:</strong>
                    <span id="modalTiempoExamenTitulo">--</span>
                </div>
                <div>
                    <strong style="color:#0369a1;">⏱️ Tiempo restante:</strong>
                    <span id="modalTiempoRestante" style="font-weight:bold; color:#059669;">--</span>
                </div>
            </div>
            <div style="margin-top:8px; font-size:0.85rem; color:#666;">
                <strong>Fecha fin actual:</strong> <span id="modalFechaFinActual">--</span>
            </div>
        </div>
        
        <div class="tiempo-estudiantes-box">
            <span style="font-size:1.5rem; font-weight:bold; color:#92400e;" id="modalEstudiantesRindiendo">0</span>
            <span style="color:#92400e; font-weight:600;"> estudiante(s) rindiendo actualmente</span>
        </div>
        
        <input type="hidden" id="modalTiempoExamenId">
        
        <!-- Botones rápidos para AGREGAR tiempo -->
        <div style="margin-bottom:15px;">
            <label style="font-weight:700; color:#059669; margin-bottom:8px; display:block;">➕ Agregar tiempo:</label>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <button class="btn-tiempo-add" onclick="enviarModificacionTiempo(5)">+5 min</button>
                <button class="btn-tiempo-add" onclick="enviarModificacionTiempo(10)">+10 min</button>
                <button class="btn-tiempo-add" onclick="enviarModificacionTiempo(15)">+15 min</button>
                <button class="btn-tiempo-add" onclick="enviarModificacionTiempo(30)">+30 min</button>
                <button class="btn-tiempo-add" onclick="enviarModificacionTiempo(60)">+1 hora</button>
            </div>
        </div>
        
        <!-- Botones rápidos para REDUCIR tiempo -->
        <div style="margin-bottom:15px;">
            <label style="font-weight:700; color:#d97706; margin-bottom:8px; display:block;">➖ Reducir tiempo:</label>
            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <button class="btn-tiempo-reduce" onclick="enviarModificacionTiempo(-5)">-5 min</button>
                <button class="btn-tiempo-reduce" onclick="enviarModificacionTiempo(-10)">-10 min</button>
                <button class="btn-tiempo-reduce" onclick="enviarModificacionTiempo(-15)">-15 min</button>
                <button class="btn-tiempo-reduce" onclick="enviarModificacionTiempo(-30)">-30 min</button>
            </div>
        </div>
        
        <!-- Campo personalizado -->
        <div style="background:#f8f9fa; padding:15px; border-radius:8px; border:1px solid #e5e7eb;">
            <label style="font-weight:600; color:#333; margin-bottom:8px; display:block;">🔧 Tiempo personalizado (minutos):</label>
            <div style="display:flex; gap:10px;">
                <input type="number" id="inputTiempoPersonalizado" class="form-control" placeholder="Ej: 20 (positivo para agregar, negativo para reducir)" style="flex:1;">
                <button class="btn btn-primary" onclick="enviarModificacionTiempo(parseInt(document.getElementById('inputTiempoPersonalizado').value) || 0)">
                    Aplicar
                </button>
            </div>
            <small style="color:#666; margin-top:5px; display:block;">Usa números positivos para agregar tiempo, negativos para reducir.</small>
        </div>
        
        <div style="margin-top:20px; padding:12px; background:#fef3c7; border:1px solid #fbbf24; border-radius:6px;">
            <p style="margin:0; font-size:0.85rem; color:#92400e;">
                <strong>⚠️ Importante:</strong> Los cambios se reflejarán automáticamente en la aplicación de los estudiantes que están rindiendo el examen (actualización cada 30 segundos).
            </p>
        </div>
    </div>
</div>

<script>
// --- CONTROLADOR DE VISTAS ---
let currentCurso = null;
let currentChatEstudiante = null;
let mensajesTimer = null;
let mensajesResumenTimer = null;
let mensajesCacheKey = '';
let chatResumenByStudent = {};
let chatLastSeen = {};
let chatNoticeTimer = null;
let chatBroadcastTimer = null;
let scrollSaveTimer = null;
let notificacionesTimer = null;

function toggleUserMenu(event) {
    const menu = document.getElementById('userMenu');
    if (menu) {
        menu.classList.toggle('show');
    }
    if (event) event.stopPropagation();
}

document.addEventListener('click', () => {
    const menu = document.getElementById('userMenu');
    if (menu) menu.classList.remove('show');
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text == null ? '' : text;
    return div.innerHTML;
}

function construirResumenParcialesPorEstudiante(exams) {
    const resumen = {};
    (exams || []).forEach(ex => {
        const parcial = (ex && ex.parcial === 'segundo_parcial') ? 'segundo' : 'primer';
        const intentos = (intentosData && intentosData[ex.id]) ? intentosData[ex.id] : [];

        (intentos || []).forEach(it => {
            const sid = String(it.estudiante_id || '');
            if (!sid) return;

            if (!resumen[sid]) {
                resumen[sid] = {
                    evaluado_primer: false,
                    evaluado_segundo: false,
                    rindiendo_primer: false,
                    rindiendo_segundo: false,
                    pausado_primer: false,
                    pausado_segundo: false,
                    motivo_seguridad: ''
                };
            }

            const puntaje = Number(it.puntaje_total);
            const estaRindiendo = (puntaje === -1);
            const tieneAlerta = !!(it.seguridad_motivo && String(it.seguridad_motivo).trim() !== '');

            if (tieneAlerta) {
                resumen[sid].motivo_seguridad = it.seguridad_motivo;
            }

            if (parcial === 'primer') {
                if (tieneAlerta && estaRindiendo) {
                    resumen[sid].pausado_primer = true;
                } else if (!estaRindiendo) {
                    resumen[sid].evaluado_primer = true;
                } else {
                    resumen[sid].rindiendo_primer = true;
                }
            } else {
                if (tieneAlerta && estaRindiendo) {
                    resumen[sid].pausado_segundo = true;
                } else if (!estaRindiendo) {
                    resumen[sid].evaluado_segundo = true;
                } else {
                    resumen[sid].rindiendo_segundo = true;
                }
            }
        });
    });
    return resumen;
}

function obtenerBadgeEstadoParcial(resumenParcial) {
    if (!resumenParcial) {
        return '<span class="status-badge status-pendiente">Pendiente</span>';
    }

    const pausado1 = !!resumenParcial.pausado_primer;
    const pausado2 = !!resumenParcial.pausado_segundo;
    const motivo = escapeHtml(resumenParcial.motivo_seguridad || 'Alerta de seguridad detectada');

    if (pausado1 || pausado2) {
        return `<span class="status-badge" style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; font-weight:700;" title="${motivo}">⚠️ Examen pausado (revisar notificaciones)</span>`;
    }

    const p1 = !!resumenParcial.evaluado_primer;
    const p2 = !!resumenParcial.evaluado_segundo;
    const rp1 = !!resumenParcial.rindiendo_primer;
    const rp2 = !!resumenParcial.rindiendo_segundo;

    if (p1 && p2) {
        return '<span class="status-badge status-activo">Evaluado (1er y 2do parcial)</span>';
    }
    if (p1) {
        return '<span class="status-badge" style="background:#dbeafe; color:#1d4ed8;">Evaluado (1er parcial)</span>';
    }
    if (p2) {
        return '<span class="status-badge" style="background:#dcfce7; color:#166534;">Evaluado (2do parcial)</span>';
    }
    if (rp1 && rp2) {
        return '<span class="status-badge" style="background:#fef3c7; color:#92400e">Rindiendo (1er y 2do parcial)</span>';
    }
    if (rp1) {
        return '<span class="status-badge" style="background:#fef3c7; color:#92400e">Rindiendo (1er parcial)</span>';
    }
    if (rp2) {
        return '<span class="status-badge" style="background:#fef3c7; color:#92400e">Rindiendo (2do parcial)</span>';
    }

    return '<span class="status-badge status-pendiente">Pendiente</span>';
}

function formatearFechaNotif(fechaSql) {
    if (!fechaSql) return '-';
    const txt = String(fechaSql).trim().replace('T', ' ');
    const partes = txt.split(' ');
    if (partes.length !== 2) return escapeHtml(txt);
    const f = partes[0].split('-');
    const h = partes[1].split(':');
    if (f.length !== 3 || h.length < 2) return escapeHtml(txt);
    const dd = f[2];
    const mm = f[1];
    const yyyy = f[0];
    const hh = h[0] || '00';
    const mi = h[1] || '00';
    const ss = h[2] || '00';
    return `${dd}/${mm}/${yyyy} ${hh}:${mi}:${ss}`;
}

function obtenerNotificacionesSeguridadCurso(cursoId) {
    if (!cursoId || !notificacionesSeguridadData) return [];
    const items = notificacionesSeguridadData[cursoId] || notificacionesSeguridadData[String(cursoId)] || [];
    return Array.isArray(items) ? items : [];
}

function actualizarBadgeNotificacionesSeguridad(cursoId) {
    const badge = document.getElementById('notifSeguridadBadge');
    if (!badge) return;

    const total = obtenerNotificacionesSeguridadCurso(cursoId).length;
    if (total > 0) {
        badge.textContent = total > 99 ? '99+' : String(total);
        badge.style.display = 'inline-flex';
    } else {
        badge.textContent = '0';
        badge.style.display = 'none';
    }
}

function actualizarBotonOcultarNotifSeguridad(cursoId) {
    const btn = document.getElementById('btnOcultarNotifSeguridad');
    if (!btn) return;
    const total = obtenerNotificacionesSeguridadCurso(cursoId).length;
    btn.disabled = !cursoId || total === 0;
}

function detenerNotificacionesSeguridad() {
    if (notificacionesTimer) {
        clearInterval(notificacionesTimer);
        notificacionesTimer = null;
    }
}

function cargarNotificacionesSeguridad(cursoId) {
    if (!cursoId) return;
    const fd = new FormData();
    fd.append('action', 'notificaciones_seguridad_docente');
    fd.append('curso_id', cursoId);

    fetch('docente_core.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        const prev = obtenerNotificacionesSeguridadCurso(cursoId);
        const next = Array.isArray(data.items) ? data.items : [];
        const prevTop = prev.length ? String(prev[0].id || '') : '';
        const nextTop = next.length ? String(next[0].id || '') : '';
        const cambio = (prev.length !== next.length) || (prevTop !== nextTop);

        notificacionesSeguridadData[String(cursoId)] = next;
        if (currentCurso && String(currentCurso) === String(cursoId)) {
            if (cambio) renderNotificacionesSeguridad(cursoId);
            else actualizarBadgeNotificacionesSeguridad(cursoId);
        }
    })
    .catch(() => {});
}

function iniciarNotificacionesSeguridad(cursoId) {
    detenerNotificacionesSeguridad();
    if (!cursoId) {
        actualizarBadgeNotificacionesSeguridad(0);
        return;
    }

    cargarNotificacionesSeguridad(cursoId);
    notificacionesTimer = setInterval(() => {
        if (!currentCurso || String(currentCurso) !== String(cursoId)) return;
        cargarNotificacionesSeguridad(cursoId);
    }, 6000);
}

function renderNotificacionesSeguridad(cursoId) {
    const cont = document.getElementById('notificaciones-list-container');
    actualizarBadgeNotificacionesSeguridad(cursoId);
    actualizarBotonOcultarNotifSeguridad(cursoId);
    if (!cont) return;

    const items = obtenerNotificacionesSeguridadCurso(cursoId);
    if (!Array.isArray(items) || items.length === 0) {
        cont.innerHTML = '<p style="color:#64748b; text-align:center; margin:8px 0;">Sin notificaciones de seguridad en este curso.</p>';
        return;
    }

    let html = '<div style="max-height:280px; overflow:auto; border:1px solid #e2e8f0; border-radius:8px;">';
    html += '<table><thead><tr><th style="width:160px;">Fecha</th><th style="width:260px;">Estudiante</th><th style="width:220px;">Examen</th><th>Razon</th></tr></thead><tbody>';

    items.forEach(n => {
        const fecha = formatearFechaNotif(n.fecha_creacion);
        const nombre = n.estudiante_nombre ? escapeHtml(n.estudiante_nombre) : `ID ${escapeHtml(n.estudiante_id || '-')}`;
        const ced = n.estudiante_cedula ? ` <small style="color:#64748b;">(${escapeHtml(n.estudiante_cedula)})</small>` : '';
        const examen = n.examen_titulo ? escapeHtml(n.examen_titulo) : `Examen ${escapeHtml(n.examen_id || '-')}`;
        const razon = escapeHtml(n.motivo || 'Cierre automatico por seguridad');

        html += `<tr>
            <td>${fecha}</td>
            <td><strong>${nombre}</strong>${ced}</td>
            <td>${examen}</td>
            <td>${razon}</td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    cont.innerHTML = html;
}

function ocultarNotificacionesSeguridadCurso() {
    if (!currentCurso) {
        alert('Selecciona un curso primero.');
        return;
    }

    const total = obtenerNotificacionesSeguridadCurso(currentCurso).length;
    if (!total) {
        alert('No hay alertas para ocultar.');
        return;
    }

    if (!confirm(`Ocultar ${total} alerta(s) de seguridad en tu panel docente?\n\nSeguiran disponibles para el administrador.`)) {
        return;
    }

    const fd = new FormData();
    fd.append('action', 'ocultar_notificaciones_seguridad_docente');
    fd.append('curso_id', currentCurso);

    fetch('docente_core.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert(data.error || 'No se pudo ocultar las alertas.');
            return;
        }
        notificacionesSeguridadData[String(currentCurso)] = [];
        renderNotificacionesSeguridad(currentCurso);
        alert(`Se ocultaron ${parseInt(data.ocultadas || 0)} alerta(s) en tu panel.`);
    })
    .catch(() => alert('Error de conexion al ocultar alertas.'));
}

function detenerMensajeria() {
    if (mensajesTimer) {
        clearInterval(mensajesTimer);
        mensajesTimer = null;
    }
    if (mensajesResumenTimer) {
        clearInterval(mensajesResumenTimer);
        mensajesResumenTimer = null;
    }
}

function resetMensajeria() {
    detenerMensajeria();
    detenerNotificacionesSeguridad();
    currentChatEstudiante = null;
    mensajesCacheKey = '';
    chatResumenByStudent = {};
    chatLastSeen = {};
    const header = document.getElementById('chatHeader');
    const log = document.getElementById('chatLog');
    const input = document.getElementById('chatInput');
    const sendBtn = document.getElementById('chatSendBtn');
    const deleteBtn = document.getElementById('chatDeleteBtn');
    const search = document.getElementById('chatSearch');
    const notice = document.getElementById('chatNotice');
    const broadcastInput = document.getElementById('chatBroadcastInput');
    const broadcastSend = document.getElementById('chatBroadcastSend');
    const broadcastInfo = document.getElementById('chatBroadcastInfo');
    const broadcastStatus = document.getElementById('chatBroadcastStatus');
    const broadcastBody = document.getElementById('chatBroadcastBody');
    const broadcastToggle = document.getElementById('chatBroadcastToggle');
    if (header) header.textContent = 'Selecciona un estudiante para iniciar';
    if (log) log.innerHTML = '<div class="chat-empty">Sin mensajes.</div>';
    if (input) {
        input.value = '';
        input.disabled = true;
    }
    if (sendBtn) sendBtn.disabled = true;
    if (deleteBtn) deleteBtn.disabled = true;
    if (search) search.value = '';
    if (notice) {
        notice.textContent = '';
        notice.classList.remove('show');
    }
    if (broadcastInput) {
        broadcastInput.value = '';
        broadcastInput.disabled = true;
    }
    if (broadcastSend) broadcastSend.disabled = true;
    if (broadcastInfo) broadcastInfo.textContent = '';
    if (broadcastStatus) broadcastStatus.textContent = '';
    if (broadcastBody) broadcastBody.style.display = 'none';
    if (broadcastToggle) broadcastToggle.textContent = 'Mostrar';
    if (chatNoticeTimer) {
        clearTimeout(chatNoticeTimer);
        chatNoticeTimer = null;
    }
    if (chatBroadcastTimer) {
        clearTimeout(chatBroadcastTimer);
        chatBroadcastTimer = null;
    }
    const btnDelAll = document.getElementById('chatDeleteAllBtn');
    if (btnDelAll) btnDelAll.disabled = !currentCurso;
}

function iniciarResumenMensajeria() {
    if (!currentCurso) return;
    if (mensajesResumenTimer) {
        clearInterval(mensajesResumenTimer);
        mensajesResumenTimer = null;
    }
    cargarResumenMensajeria();
    mensajesResumenTimer = setInterval(cargarResumenMensajeria, 5000);
}

function cargarResumenMensajeria() {
    if (!currentCurso) return;
    const fd = new FormData();
    fd.append('action', 'mensajeria_resumen_docente');
    fd.append('curso_id', currentCurso);
    fetch('docente_core.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (!data.success) return;
        actualizarResumenMensajeria(data.resumen || []);
    })
    .catch(() => {});
}

function actualizarResumenMensajeria(resumen) {
    if (!Array.isArray(resumen)) return;
    let ultimoNuevo = null;
    resumen.forEach(item => {
        const estudianteId = parseInt(item.estudiante_id || 0);
        if (!estudianteId) return;
        const prev = chatResumenByStudent[estudianteId];
        const prevUltimo = prev ? parseInt(prev.ultimo_id || 0) : 0;
        const nuevoUltimo = parseInt(item.ultimo_id || 0);
        chatResumenByStudent[estudianteId] = item;
        const lastSeen = parseInt(chatLastSeen[estudianteId] || 0);
        const esNuevo = nuevoUltimo &&
            item.ultimo_emisor === 'estudiante' &&
            nuevoUltimo > lastSeen &&
            nuevoUltimo > prevUltimo &&
            (!currentChatEstudiante || currentChatEstudiante.estudiante_id != estudianteId);
        if (esNuevo) {
            if (!ultimoNuevo || (item.ultima_fecha || '') > (ultimoNuevo.ultima_fecha || '')) {
                ultimoNuevo = item;
            }
        }
    });

    const ests = (estudiantesData && currentCurso && estudiantesData[currentCurso]) ? estudiantesData[currentCurso] : [];
    renderChatEstudiantes(ests);
    if (ultimoNuevo) {
        mostrarNotificacionChat(parseInt(ultimoNuevo.estudiante_id || 0));
    }
}

function obtenerEstudiantePorId(estudianteId) {
    const ests = (estudiantesData && currentCurso && estudiantesData[currentCurso]) ? estudiantesData[currentCurso] : [];
    return ests.find(e => e.estudiante_id == estudianteId) || null;
}

function mostrarNotificacionChat(estudianteId) {
    const notice = document.getElementById('chatNotice');
    if (!notice || !currentCurso || !estudianteId) return;
    const est = obtenerEstudiantePorId(estudianteId);
    const nombre = est ? est.nombres : 'Estudiante';
    notice.textContent = `Nuevo mensaje de ${nombre}`;
    notice.classList.add('show');
    if (chatNoticeTimer) {
        clearTimeout(chatNoticeTimer);
    }
    chatNoticeTimer = setTimeout(() => {
        notice.classList.remove('show');
    }, 4000);
}

function toggleBroadcastPanel() {
    const body = document.getElementById('chatBroadcastBody');
    const btn = document.getElementById('chatBroadcastToggle');
    if (!body || !btn) return;
    const visible = body.style.display !== 'none';
    body.style.display = visible ? 'none' : 'block';
    btn.textContent = visible ? 'Mostrar' : 'Ocultar';
}

function setBroadcastStatus(text, isError) {
    const status = document.getElementById('chatBroadcastStatus');
    if (!status) return;
    status.textContent = text || '';
    status.classList.toggle('error', !!isError);
    if (chatBroadcastTimer) {
        clearTimeout(chatBroadcastTimer);
        chatBroadcastTimer = null;
    }
    if (text) {
        chatBroadcastTimer = setTimeout(() => {
            status.textContent = '';
            status.classList.remove('error');
        }, 4000);
    }
}

function actualizarBroadcastInfo() {
    const info = document.getElementById('chatBroadcastInfo');
    const input = document.getElementById('chatBroadcastInput');
    const sendBtn = document.getElementById('chatBroadcastSend');
    const ests = (estudiantesData && currentCurso && estudiantesData[currentCurso]) ? estudiantesData[currentCurso] : [];
    const count = ests.length;
    const habilitar = !!currentCurso && count > 0;
    if (input) input.disabled = !habilitar;
    if (sendBtn) sendBtn.disabled = !habilitar;
    if (info) {
        info.textContent = habilitar ? `Se enviara a ${count} estudiante(s).` : 'No hay estudiantes en este curso.';
    }
    if (!habilitar) {
        setBroadcastStatus('', false);
    }
}

function irAreaPersonal() { 
    guardarScrollActual();
    resetMensajeria();
    currentCurso = null;
    actualizarBadgeNotificacionesSeguridad(0);
    actualizarBotonOcultarNotifSeguridad(0);
    sessionStorage.removeItem('docente_curso_actual');
    sessionStorage.removeItem('docente_vista_actual');
    document.getElementById('default-view').style.display='block'; 
    document.getElementById('course-view').style.display='none';
    document.getElementById('director-view').style.display='none';
    document.getElementById('director-area-view').style.display='none';
    document.getElementById('profile-view').style.display='none';
    
    document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
    document.getElementById('link-area-personal').classList.add('active');
    setTimeout(restaurarScrollActual, 50);
}

function isCourseViewActive() {
    const cv = document.getElementById('course-view');
    return !!cv && cv.style.display !== 'none';
}

function isViewVisible(el) {
    return !!el && el.style.display !== 'none';
}

function getDocenteViewKey() {
    if (isCourseViewActive() && currentCurso) return `curso_${currentCurso}`;
    if (isViewVisible(document.getElementById('director-view'))) return 'director';
    if (isViewVisible(document.getElementById('director-area-view'))) return 'director_area';
    return 'default';
}

function guardarScrollActual() {
    const key = getDocenteViewKey();
    if (!key) return;
    const y = window.scrollY || document.documentElement.scrollTop || 0;
    sessionStorage.setItem(`docente_scroll_${key}`, String(y));
}

function restaurarScrollActual() {
    const key = getDocenteViewKey();
    if (!key) return;
    const raw = sessionStorage.getItem(`docente_scroll_${key}`);
    if (!raw) return;
    const y = parseInt(raw, 10);
    if (!Number.isFinite(y)) return;
    window.scrollTo(0, y);
}

function getCourseSections() {
    return Array.from(document.querySelectorAll('#course-view .course-section'));
}

function guardarEstadoSecciones() {
    if (!currentCurso) return;
    const sections = getCourseSections();
    if (!sections.length) return;
    const abiertas = sections
        .map((el, idx) => el.classList.contains('closed') ? null : idx)
        .filter(idx => idx !== null);
    sessionStorage.setItem(`docente_sections_open_${currentCurso}`, JSON.stringify(abiertas));
}

function restaurarEstadoSecciones() {
    if (!currentCurso) return;
    const sections = getCourseSections();
    if (!sections.length) return;
    const raw = sessionStorage.getItem(`docente_sections_open_${currentCurso}`);
    if (!raw) return;
    let abiertas = [];
    try {
        const parsed = JSON.parse(raw);
        if (Array.isArray(parsed)) abiertas = parsed.map(n => parseInt(n, 10)).filter(n => Number.isInteger(n));
    } catch (e) { return; }
    sections.forEach((el, idx) => {
        if (abiertas.includes(idx)) el.classList.remove('closed');
        else el.classList.add('closed');
    });
}

function toggleSection(el) {
    el.parentElement.classList.toggle('closed');
    guardarEstadoSecciones();
}

function verPanelDirector(e) {
    if(e) e.preventDefault();
    irAreaPersonal();
    document.getElementById('default-view').style.display='none';
    document.getElementById('director-view').style.display='block';
    document.getElementById('profile-view').style.display='none';
    
    document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
    const lnk = document.getElementById('link-panel-director');
    if(lnk) lnk.classList.add('active');

    filtrarDocentes('lDoc', '');
    sessionStorage.setItem('docente_vista_actual', 'director');
    setTimeout(restaurarScrollActual, 50);
}

function verPanelDirectorArea(e) {
    if(e) e.preventDefault();
    irAreaPersonal();
    document.getElementById('default-view').style.display='none';
    document.getElementById('director-area-view').style.display='block';
    document.getElementById('profile-view').style.display='none';
    
    document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
    const lnk = document.getElementById('link-panel-director-area');
    if(lnk) lnk.classList.add('active');

    filtrarDocentes('lDocArea', '');
    sessionStorage.setItem('docente_vista_actual', 'director_area');
    setTimeout(restaurarScrollActual, 50);
}

function verPerfil() {
    guardarScrollActual();
    resetMensajeria();
    document.getElementById('default-view').style.display='none';
    document.getElementById('course-view').style.display='none';
    document.getElementById('director-view').style.display='none';
    document.getElementById('director-area-view').style.display='none';
    document.getElementById('profile-view').style.display='block';
    document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
    sessionStorage.setItem('docente_vista_actual', 'perfil');
    setTimeout(restaurarScrollActual, 50);
}

function filtrarDocentes(divId, txt) {
    const list = document.getElementById(divId);
    let dataSrc = (divId === 'lDoc') ? docentesData : docentesAreaData;
    let h = '';
    
    for(let k in dataSrc) {
        let d = dataSrc[k];
        if(!txt || d.nombres.toLowerCase().includes(txt.toLowerCase())) {
            let rolTxt = d.rol.replace('_',' ').toUpperCase();
            let areaHtml = d.area ? `<div class="area-txt" style="font-size:0.7rem; color:#6d28d9; font-weight:600; margin-top:2px;">${d.area}</div>` : '';
            
            h += `<div onclick="verDetalleDoc('${divId}', ${k})" class="link-doc" style="cursor:pointer;">
                <strong>${d.nombres}</strong><br>
                <small style="color:#666;">${rolTxt}</small>
                ${areaHtml}
            </div>`;
        }
    }
    list.innerHTML = h;
}

function verDetalleDoc(divId, idx) {
    let dataSrc = (divId === 'lDoc') ? docentesData : docentesAreaData;
    let d = dataSrc[idx];
    
    let rolClass = 'rol-docente';
    if(d.rol==='director_carrera') rolClass='rol-director-carrera';
    else if(d.rol==='director_area') rolClass='rol-director-area';

    let h = `<div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding:20px; border-radius:10px; margin-bottom:20px;">
                <h3 style="margin:0 0 10px 0; color:#0f6cb6;">${d.nombres}</h3>
                <p style="color:#666; margin:5px 0;"><strong>Email:</strong> ${d.email||'N/A'}</p>
                <p style="color:#666; margin:5px 0;"><strong>Cédula:</strong> ${d.cedula||'N/A'}</p>
                <div style="margin-top:10px;">
                    <span class="rol-badge ${rolClass}">${d.rol.replace('_',' ')}</span>
                    ${d.area ? `<span class="area-badge">${d.area}</span>` : ''}
                </div>
             </div>`;
             
    if(d.cursos && d.cursos.length > 0){
        h+=`<table><thead><tr><th>Materia</th><th>Paralelo</th><th style="text-align:center;">Estudiantes</th><th style="text-align:center;">Evaluados</th><th style="text-align:center;">Estado</th></tr></thead><tbody>`;
        d.cursos.forEach(c=>{
            let numEst = parseInt(c.num_estudiantes)||0;
            let evaluados = parseInt(c.evaluados)||0;
            let porcentaje = numEst>0 ? Math.round((evaluados/numEst)*100) : 0;
            let estadoBadge = '';
            
            if(numEst===0) estadoBadge = '<span class="status-badge" style="background:#e5e7eb; color:#6b7280;">Sin estudiantes</span>';
            else if(evaluados===0) estadoBadge = '<span class="status-badge status-pendiente">Sin evaluar</span>';
            else if(evaluados>=numEst) estadoBadge = '<span class="status-badge status-activo">Completo</span>';
            else estadoBadge = `<span class="status-badge" style="background:#fef3c7; color:#92400e;">En progreso (${porcentaje}%)</span>`;
            
            h+=`<tr><td>${c.materia}</td><td>${c.paralelo}</td><td style="text-align:center;">${numEst}</td><td style="text-align:center;">${evaluados}</td><td style="text-align:center;">${estadoBadge}</td></tr>`;
        });
        h+=`</tbody></table>`;
    } else {
        h+=`<p style="text-align:center; color:#888;">Sin carga académica registrada.</p>`;
    }
    document.getElementById(divId==='lDoc'?'det-doc':'det-doc-area').innerHTML = h;
}

function cambiarTabExamen(tipo) {
    const isProg = (tipo === 'programado');
    document.getElementById('modo_examen').value = isProg ? 'programado' : 'manual';
    document.getElementById('divFechasManual').style.display = isProg ? 'none' : 'block';
    document.getElementById('divFechasProg').style.display = isProg ? 'block' : 'none';
    
    if(isProg) {
        document.querySelector('input[name="fecha_fin_prog"]').setAttribute('name', 'fecha_fin');
        document.querySelector('#divFechasManual input[name="fecha_fin"]').setAttribute('name', 'fecha_fin_temp');
    } else {
        document.querySelector('#divFechasManual input[name="fecha_fin_temp"]')?.setAttribute('name', 'fecha_fin');
        document.querySelector('#divFechasProg input[name="fecha_fin"]')?.setAttribute('name', 'fecha_fin_prog');
    }

    const tM = document.getElementById('tabManual');
    const tP = document.getElementById('tabProgramado');
    tM.style.background = isProg ? '#e5e7eb' : '#0f6cb6'; tM.style.color = isProg ? '#666' : 'white';
    tP.style.background = isProg ? '#059669' : '#e5e7eb'; tP.style.color = isProg ? 'white' : '#666';
}

// Hacer accesible globalmente para los enlaces inline
function verCurso(e, id, nombre) {
    if(e) e.preventDefault();
    guardarScrollActual();
    currentCurso = id;
    sessionStorage.setItem('docente_curso_actual', id);
    
    document.getElementById('default-view').style.display='none';
    document.getElementById('director-view').style.display='none';
    document.getElementById('director-area-view').style.display='none';
    document.getElementById('course-view').style.display='block';
    document.getElementById('profile-view').style.display='none';
    
    document.getElementById('course-title').innerText = nombre;
    document.getElementById('input_curso_id_examen').value = id;
    document.getElementById('input_curso_id_est').value = id;

    document.querySelectorAll('.nav-link').forEach(a => a.classList.remove('active'));
    const link = document.querySelector(`.link-curso[data-id="${id}"]`);
    if(link) link.classList.add('active');

    // Activar botones de mensajería del curso
    const btnDelAll = document.getElementById('chatDeleteAllBtn');
    if (btnDelAll) btnDelAll.disabled = false;

    restaurarEstadoSecciones();

    // RENDERIZAR EXÁMENES
    const exams = examenesData[id] || [];
    let htmlEx = '';
    if (exams.length === 0) {
        htmlEx = '<p style="color:#888; text-align:center;">No hay exámenes creados.</p>';
    } else {
        const exP1 = exams.filter(e => e.parcial === 'primer_parcial' || !e.parcial);
        const exP2 = exams.filter(e => e.parcial === 'segundo_parcial');
        
        htmlEx = '<div style="display:flex; gap:15px; overflow-x:auto;">';
        htmlEx += '<div style="flex:1; min-width:300px; border:2px solid #3b82f6; border-radius:12px; overflow:hidden;">';
        htmlEx += '<div style="background:linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color:white; padding:10px; font-weight:bold;">📘 PRIMER PARCIAL</div><div style="padding:10px;">';
        exP1.forEach(ex => { htmlEx += renderCardExamen(ex); });
        htmlEx += '</div></div>';
        htmlEx += '<div style="flex:1; min-width:300px; border:2px solid #22c55e; border-radius:12px; overflow:hidden;">';
        htmlEx += '<div style="background:linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color:white; padding:10px; font-weight:bold;">📗 SEGUNDO PARCIAL</div><div style="padding:10px;">';
        exP2.forEach(ex => { htmlEx += renderCardExamen(ex); });
        htmlEx += '</div></div>';
        htmlEx += '</div>';
    }
    document.getElementById('exam-list-container').innerHTML = htmlEx;

    // RENDERIZAR ESTUDIANTES
    const ests = estudiantesData[id] || [];
    let htmlEst = '';
    if (ests.length === 0) {
        htmlEst = '<p style="color:#888; text-align:center;">Sin estudiantes.</p>';
    } else {
        const resumenParciales = construirResumenParcialesPorEstudiante(exams);
        htmlEst = `<form method="POST" onsubmit="return confirm('¿Eliminar seleccionados?')">
            <input type="hidden" name="id_curso_masivo" value="${id}">
            <button type="submit" name="eliminar_masivo" class="btn btn-sm btn-outline" style="color:red; border-color:red; margin-bottom:10px;">🗑 Eliminar Seleccionados</button>
            <table class="table"><thead><tr><th width="30"><input type="checkbox" onchange="toggleAll(this)"></th><th>Nombre</th><th>Cédula</th><th>Estado</th><th>Acción</th></tr></thead><tbody>`;
        ests.forEach(es => {
            const estado = obtenerBadgeEstadoParcial(resumenParciales[String(es.estudiante_id)]);
            htmlEst += `<tr>
                <td><input type="checkbox" name="ids_estudiantes_masivo[]" value="${es.estudiante_id}" class="chk-est"></td>
                <td>${es.nombres}</td><td>${es.cedula}</td><td>${estado}</td>
                <td><form method="POST" style="display:inline" onsubmit="return confirm('¿Retirar?')"><input type="hidden" name="id_estudiante_del" value="${es.estudiante_id}"><input type="hidden" name="id_curso_del" value="${id}"><button name="eliminar_estudiante" class="btn btn-sm btn-outline" style="color:red; border:none;">🗑</button></form></td>
            </tr>`;
        });
        htmlEst += '</tbody></table></form>';
    }
    document.getElementById('student-list-container').innerHTML = htmlEst;
    resetMensajeria();
    renderNotificacionesSeguridad(id);
    renderChatEstudiantes(ests);
    iniciarResumenMensajeria();
    actualizarBroadcastInfo();
    iniciarNotificacionesSeguridad(id);
    setTimeout(restaurarScrollActual, 50);
}

// Exponer para manejadores inline
window.verCurso = verCurso;

function renderChatEstudiantes(ests) {
    const list = document.getElementById('chatStudentList');
    if (!list) return;
    if (!Array.isArray(ests) || ests.length === 0) {
        list.innerHTML = '<div class="chat-empty">Sin estudiantes.</div>';
        return;
    }
    
    const search = document.getElementById('chatSearch');
    const filtro = search ? search.value.toLowerCase() : '';
    const filtrados = ests.filter(es => {
        const nombre = (es.nombres || '').toLowerCase();
        const cedula = (es.cedula || '').toLowerCase();
        if (filtro && !nombre.includes(filtro) && !cedula.includes(filtro)) return false;
        return true;
    });

    filtrados.sort((a, b) => {
        const metaA = chatResumenByStudent[a.estudiante_id] || {};
        const metaB = chatResumenByStudent[b.estudiante_id] || {};
        const fa = metaA.ultima_fecha || '';
        const fb = metaB.ultima_fecha || '';
        if (fa !== fb) return fb.localeCompare(fa);
        return (a.nombres || '').localeCompare(b.nombres || '');
    });

    let ultimoId = 0;
    let ultimoEmisor = '';
    let ultimaFecha = '';
    let html = '';
    filtrados.forEach(es => {
        const meta = chatResumenByStudent[es.estudiante_id] || {};
        const ultimoId = parseInt(meta.ultimo_id || 0);
        const ultimoEmisor = meta.ultimo_emisor || '';
        const lastSeen = parseInt(chatLastSeen[es.estudiante_id] || 0);
        const tieneNuevo = ultimoId && ultimoEmisor === 'estudiante' && ultimoId > lastSeen;
        const hora = (meta.ultima_fecha && String(meta.ultima_fecha).length >= 16) ? String(meta.ultima_fecha).substring(11, 16) : '';
        const activo = currentChatEstudiante && currentChatEstudiante.estudiante_id == es.estudiante_id;
        const clases = `chat-student${activo ? ' active' : ''}${tieneNuevo ? ' has-new' : ''}`;
        
        html += `<div class="${clases}" onclick="abrirChatEstudiante(${es.estudiante_id})">
            <div class="chat-student-info">
                <div><strong>${escapeHtml(es.nombres || '')}</strong></div>
                <small>${escapeHtml(es.cedula || '')}</small>
            </div>
            <div class="chat-student-meta">
                ${hora ? `<span class="chat-time">${hora}</span>` : ''}
                ${tieneNuevo ? `<span class="chat-badge">Nuevo</span>` : ''}
            </div>
        </div>`;
    });
    
    list.innerHTML = html || '<div class="chat-empty">Sin estudiantes.</div>';
}

function filtrarChatEstudiantes() {
    const ests = (estudiantesData && currentCurso && estudiantesData[currentCurso]) ? estudiantesData[currentCurso] : [];
    renderChatEstudiantes(ests);
}

function abrirChatEstudiante(estudianteId) {
    if (!currentCurso) return;
    const ests = estudiantesData[currentCurso] || [];
    const est = ests.find(e => e.estudiante_id == estudianteId);
    if (!est) return;
    
    currentChatEstudiante = est;
    mensajesCacheKey = '';
    renderChatEstudiantes(ests);
    
    const header = document.getElementById('chatHeader');
    if (header) header.textContent = `Chat con ${est.nombres} (${est.cedula})`;

    const input = document.getElementById('chatInput');
    const sendBtn = document.getElementById('chatSendBtn');
    const deleteBtn = document.getElementById('chatDeleteBtn');
    const notice = document.getElementById('chatNotice');
    if (input) {
        input.disabled = false;
        input.focus();
    }
    if (sendBtn) sendBtn.disabled = false;
    if (deleteBtn) deleteBtn.disabled = false;
    if (notice) notice.classList.remove('show');
    
    cargarMensajesDocente();
    if (mensajesTimer) {
        clearInterval(mensajesTimer);
        mensajesTimer = null;
    }
    mensajesTimer = setInterval(cargarMensajesDocente, 5000);
}

function cargarMensajesDocente() {
    if (!currentCurso || !currentChatEstudiante) return;
    
    const log = document.getElementById('chatLog');
    const fd = new FormData();
    fd.append('action', 'mensajeria_listar_docente');
    fd.append('curso_id', currentCurso);
    fd.append('estudiante_id', currentChatEstudiante.estudiante_id);
    
    fetch('docente_core.php', { method: 'POST', body: fd, credentials: 'same-origin' })
    .then(r => r.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text.replace(/^\uFEFF/, ''));
        } catch (e) {
            if (log) log.innerHTML = '<div class="chat-empty">Respuesta invalida del servidor.</div>';
            return;
        }
        if (!data.success) {
            if (log) log.innerHTML = `<div class="chat-empty">${escapeHtml(data.error || 'Error cargando mensajes')}</div>`;
            return;
        }
        renderMensajesDocente(data.mensajes || []);
    })
    .catch(() => {
        if (log) log.innerHTML = '<div class="chat-empty">Error de conexion.</div>';
    });
}

function renderMensajesDocente(mensajes) {
    const log = document.getElementById('chatLog');
    if (!log) return;
    
    if (!Array.isArray(mensajes) || mensajes.length === 0) {
        log.innerHTML = '<div class="chat-empty">Sin mensajes.</div>';
        return;
    }
    
    const ids = mensajes.map(m => m.id).join('|');
    if (ids === mensajesCacheKey) return;
    mensajesCacheKey = ids;
    
    let ultimoId = 0;
    let ultimoEmisor = '';
    let ultimaFecha = '';
    let html = '';
    mensajes.forEach(m => {
        const esDocente = m.emisor === 'docente';
        const hora = m.fecha_creacion ? m.fecha_creacion.substring(11, 16) : '';
        const btnBorrar = esDocente ? `<button class="chat-delete" onclick="eliminarMensajeDocente(${m.id})" title="Eliminar">🗑</button>` : '';
        
        const idMsg = parseInt(m.id || 0);
        if (idMsg > ultimoId) {
            ultimoId = idMsg;
            ultimoEmisor = m.emisor || '';
            ultimaFecha = m.fecha_creacion || '';
        }
        html += `<div class="chat-row ${esDocente ? 'docente' : ''}">
            <div class="chat-bubble">
                <div>${escapeHtml(m.mensaje || '')}</div>
                <div class="chat-meta"><span>${hora} ${esDocente ? 'Tú' : 'Estudiante'}</span>${btnBorrar}</div>
            </div>
        </div>`;
    });
    
    log.innerHTML = html;
    log.scrollTop = log.scrollHeight;

    if (currentChatEstudiante && ultimoId) {
        chatLastSeen[currentChatEstudiante.estudiante_id] = ultimoId;
        if (chatResumenByStudent[currentChatEstudiante.estudiante_id]) {
            chatResumenByStudent[currentChatEstudiante.estudiante_id].ultimo_id = ultimoId;
            chatResumenByStudent[currentChatEstudiante.estudiante_id].ultimo_emisor = ultimoEmisor;
            chatResumenByStudent[currentChatEstudiante.estudiante_id].ultima_fecha = ultimaFecha;
        }
        const ests = (estudiantesData && currentCurso && estudiantesData[currentCurso]) ? estudiantesData[currentCurso] : [];
        renderChatEstudiantes(ests);
    }
}

function enviarMensajeDocente() {
    if (!currentCurso || !currentChatEstudiante) {
        alert('Selecciona un estudiante primero.');
        return;
    }
    
    const input = document.getElementById('chatInput');
    const texto = input ? input.value.trim() : '';
    if (!texto) return;
    
    const fd = new FormData();
    fd.append('action', 'mensajeria_enviar_docente');
    fd.append('curso_id', currentCurso);
    fd.append('estudiante_id', currentChatEstudiante.estudiante_id);
    fd.append('mensaje', texto);
    
    fetch('docente_core.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert(data.error || 'No se pudo enviar el mensaje');
            return;
        }
        if (input) {
            input.value = '';
            input.focus();
        }
        mensajesCacheKey = '';
        if (data.mensaje && currentChatEstudiante) {
            const msgId = parseInt(data.mensaje.id || 0);
            if (msgId) {
                chatLastSeen[currentChatEstudiante.estudiante_id] = msgId;
                if (!chatResumenByStudent[currentChatEstudiante.estudiante_id]) {
                    chatResumenByStudent[currentChatEstudiante.estudiante_id] = {
                        estudiante_id: currentChatEstudiante.estudiante_id,
                        ultimo_id: msgId,
                        ultimo_emisor: 'docente',
                        ultima_fecha: data.mensaje.fecha_creacion || ''
                    };
                } else {
                    chatResumenByStudent[currentChatEstudiante.estudiante_id].ultimo_id = msgId;
                    chatResumenByStudent[currentChatEstudiante.estudiante_id].ultimo_emisor = 'docente';
                    chatResumenByStudent[currentChatEstudiante.estudiante_id].ultima_fecha = data.mensaje.fecha_creacion || '';
                }
                const ests = (estudiantesData && currentCurso && estudiantesData[currentCurso]) ? estudiantesData[currentCurso] : [];
                renderChatEstudiantes(ests);
            }
        }
        cargarMensajesDocente();
    })
    .catch(() => alert('Error de conexión al enviar el mensaje'));
}

function enviarMensajeMasivoDocente() {
    if (!currentCurso) {
        alert('Selecciona un curso primero.');
        return;
    }
    const ests = (estudiantesData && currentCurso && estudiantesData[currentCurso]) ? estudiantesData[currentCurso] : [];
    if (!ests.length) {
        alert('No hay estudiantes en este curso.');
        return;
    }
    const input = document.getElementById('chatBroadcastInput');
    const texto = input ? input.value.trim() : '';
    if (!texto) return;
    if (!confirm(`Enviar este mensaje a ${ests.length} estudiante(s)?`)) return;

    const sendBtn = document.getElementById('chatBroadcastSend');
    if (sendBtn) sendBtn.disabled = true;
    setBroadcastStatus('Enviando mensaje masivo...', false);

    const fd = new FormData();
    fd.append('action', 'mensajeria_broadcast_docente');
    fd.append('curso_id', currentCurso);
    fd.append('mensaje', texto);

    fetch('docente_core.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            setBroadcastStatus(data.error || 'No se pudo enviar el mensaje masivo', true);
            return;
        }
        if (input) input.value = '';
        mensajesCacheKey = '';
        const enviados = parseInt(data.enviados || ests.length);
        setBroadcastStatus(`Mensaje enviado a ${enviados} estudiante(s).`, false);
        cargarResumenMensajeria();
        if (currentChatEstudiante) {
            cargarMensajesDocente();
        }
    })
    .catch(() => setBroadcastStatus('Error de conexion al enviar el mensaje masivo', true))
    .finally(() => {
        actualizarBroadcastInfo();
    });
}

function borrarChatDocente() {
    if (!currentCurso || !currentChatEstudiante) {
        alert('Selecciona un estudiante primero.');
        return;
    }
    const nombre = currentChatEstudiante.nombres || 'estudiante';
    if (!confirm(`Eliminar todo el chat con ${nombre}?`)) return;

    const fd = new FormData();
    fd.append('action', 'mensajeria_eliminar_chat_docente');
    fd.append('curso_id', currentCurso);
    fd.append('estudiante_id', currentChatEstudiante.estudiante_id);

    fetch('docente_core.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert(data.error || 'No se pudo eliminar el chat');
            return;
        }
        const log = document.getElementById('chatLog');
    if (log) log.innerHTML = '<div class="chat-empty">Sin mensajes.</div>';
        mensajesCacheKey = '';
        chatLastSeen[currentChatEstudiante.estudiante_id] = 0;
        if (chatResumenByStudent[currentChatEstudiante.estudiante_id]) {
            chatResumenByStudent[currentChatEstudiante.estudiante_id].ultimo_id = null;
            chatResumenByStudent[currentChatEstudiante.estudiante_id].ultimo_emisor = null;
            chatResumenByStudent[currentChatEstudiante.estudiante_id].ultima_fecha = null;
        }
        const ests = (estudiantesData && currentCurso && estudiantesData[currentCurso]) ? estudiantesData[currentCurso] : [];
        renderChatEstudiantes(ests);
    })
    .catch(() => alert('Error de conexion al eliminar el chat'));
}

function borrarChatsCurso() {
    if (!currentCurso) {
        alert('Selecciona un curso primero.');
        return;
    }
    if (!confirm('Eliminar todos los chats de este curso? Esta acción no se puede deshacer.')) return;

    const fd = new FormData();
    fd.append('action', 'mensajeria_eliminar_todos_docente');
    fd.append('curso_id', currentCurso);

    fetch('docente_core.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert(data.error || 'No se pudo eliminar los chats');
            return;
        }
        mensajesCacheKey = '';
        chatLastSeen = {};
        chatResumenByStudent = {};
        resetMensajeria();
    })
    .catch(() => alert('Error de conexion al eliminar los chats'));
}

function eliminarMensajeDocente(mensajeId) {
    if (!confirm('¿Eliminar este mensaje?')) return;
    
    const fd = new FormData();
    fd.append('action', 'mensajeria_eliminar_docente');
    fd.append('mensaje_id', mensajeId);
    
    fetch('docente_core.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
        if (!data.success) {
            alert(data.error || 'No se pudo eliminar el mensaje');
            return;
        }
        mensajesCacheKey = '';
        cargarMensajesDocente();
    })
    .catch(() => alert('Error de conexión al eliminar el mensaje'));
}

function renderCardExamen(ex) {
    let st = '', bColor = '#ccc';
    if(ex.estado==='activo'){ st='<span style="background:#22c55e; color:white; padding:2px 6px; border-radius:4px; font-size:0.7rem;">ACTIVO</span>'; bColor='#22c55e'; }
    else if(ex.estado==='programado'){ st='<span style="background:#f59e0b; color:white; padding:2px 6px; border-radius:4px; font-size:0.7rem;">PROGRAMADO</span>'; bColor='#f59e0b'; }
    else if(ex.estado==='cerrado'){ st='<span style="background:#ef4444; color:white; padding:2px 6px; border-radius:4px; font-size:0.7rem;">CERRADO</span>'; bColor='#ef4444'; }
    else { st='<span style="background:#94a3b8; color:white; padding:2px 6px; border-radius:4px; font-size:0.7rem;">BORRADOR</span>'; }

    let btns = '';
    if(ex.estado !== 'activo') btns += `<form method="POST" style="display:inline"><input type="hidden" name="id_examen" value="${ex.id}"><button name="iniciar_examen" class="btn btn-sm btn-outline" title="Iniciar">▶</button></form>`;
    if(ex.estado === 'activo' || ex.estado === 'programado') btns += `<form method="POST" style="display:inline"><input type="hidden" name="id_examen" value="${ex.id}"><button name="detener_examen" class="btn btn-sm btn-outline" style="color:red; border-color:red;" title="Detener">⏹</button></form>`;
    
    // Lógica para el botón de Mostrar Nota
    let notaIcon = ex.mostrar_nota == 1 ? '👁️' : '🔒';
    let notaTitle = ex.mostrar_nota == 1 ? 'Nota VISIBLE para estudiantes (Click para ocultar)' : 'Nota OCULTA para estudiantes (Click para habilitar)';
    let notaStyle = ex.mostrar_nota == 1 ? 'background:#dcfce7; color:#166534; border-color:#86efac;' : 'background:#fee2e2; color:#991b1b; border-color:#fca5a5;';

    // Lógica para el botón de Permitir Revisión
    let revIcon = ex.permitir_revision == 1 ? '📖' : '🚫';
    let revTitle = ex.permitir_revision == 1 ? 'Revisión HABILITADA (Click para deshabilitar)' : 'Revisión DESHABILITADA (Click para habilitar)';
    let revStyle = ex.permitir_revision == 1 ? 'background:#dcfce7; color:#166534; border-color:#86efac;' : 'background:#fee2e2; color:#991b1b; border-color:#fca5a5;';

    // Botón para alternar navegación secuencial/libre
    let secBtnIcon = ex.preguntas_secuenciales == 1 ? '🔒' : '🔓';
    let secBtnTitle = ex.preguntas_secuenciales == 1 
        ? 'Modo secuencial ACTIVO (Click para cambiar a libre)' 
        : 'Modo libre ACTIVO (Click para activar secuencial)';
    let secBtnStyle = ex.preguntas_secuenciales == 1 
        ? 'background:#fef2f2; color:#991b1b; border-color:#fecdd3;' 
        : 'background:#e0f2fe; color:#0f6cb6; border-color:#90cdf4;';
    
    // Nueva etiqueta de tipo de navegación
    let secTag = (ex.preguntas_secuenciales == 1) 
        ? '<span class="tag-secuencial" title="Navegación bloqueada">🔒 Secuencial</span>' 
        : '<span class="tag-libre" title="Navegación libre">🔓 Libre</span>';

    // Botón modificar tiempo (solo si está activo)
    let btnTiempo = '';
    if(ex.estado === 'activo') {
        btnTiempo = `<button type="button" class="btn btn-sm" style="background:#8b5cf6; color:white; border:none;" onclick="abrirModificarTiempo(${ex.id}, '${ex.titulo.replace(/'/g, "\\'")}', '${ex.fecha_fin}', ${ex.duracion_minutos})" title="Modificar tiempo en tiempo real">⏰</button>`;
    }

    return `<div style="border:1px solid ${bColor}; border-left:4px solid ${bColor}; background:#fff; padding:10px; margin-bottom:10px; border-radius:6px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
        <div style="font-weight:bold; font-size:0.95rem; display:flex; align-items:center; gap:5px;">
            ${ex.titulo} ${secTag}
        </div>
        <div style="font-size:0.8rem; color:#666; margin:4px 0;">⏱ ${ex.duracion_minutos} min | ${st}</div>
        <div style="font-size:0.75rem; color:#888;">📅 ${ex.fecha_inicio ? ex.fecha_inicio.substring(0,16) : '--'} → ${ex.fecha_fin ? ex.fecha_fin.substring(0,16) : '--'}</div>
        <div style="margin-top:8px; display:flex; gap:5px; flex-wrap:wrap;">
            <a href="?download_doc=${ex.id}" class="btn btn-sm btn-outline" title="Descargar">📥</a>
            <button type="button" class="btn btn-sm btn-outline" onclick="verBanco(${ex.id})" title="Ver Preguntas">📋</button>
            <button class="btn btn-sm btn-outline" onclick="abrirIntentos(${ex.id})" title="Intentos">👥</button>
            
            ${btnTiempo}
            
            <!-- Botón Navegación Secuencial Toggle -->
            <form method="POST" style="display:inline">
                <input type="hidden" name="id_examen" value="${ex.id}">
                <input type="hidden" name="estado_actual" value="${ex.preguntas_secuenciales}">
                <button name="toggle_secuencial" class="btn btn-sm" style="${secBtnStyle}" title="${secBtnTitle}">
                    ${secBtnIcon} Modo
                </button>
            </form>

            <!-- Botón Nota Toggle -->
            <form method="POST" style="display:inline">
                <input type="hidden" name="id_examen" value="${ex.id}">
                <input type="hidden" name="estado_actual" value="${ex.mostrar_nota}">
                <button name="toggle_nota" class="btn btn-sm" style="${notaStyle}" title="${notaTitle}">
                    ${notaIcon} Nota
                </button>
            </form>

            <!-- Botón Revisión Toggle -->
            <form method="POST" style="display:inline">
                <input type="hidden" name="id_examen" value="${ex.id}">
                <input type="hidden" name="estado_actual" value="${ex.permitir_revision}">
                <button name="toggle_revision" class="btn btn-sm" style="${revStyle}" title="${revTitle}">
                    ${revIcon} Rev.
                </button>
            </form>

            ${btns}
            <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar examen?')"><input type="hidden" name="id_examen" value="${ex.id}"><button name="eliminar_examen" class="btn btn-sm btn-outline" style="color:red" title="Eliminar">🗑</button></form>
        </div>
    </div>`;
}

// =========================================
// FUNCIONES PARA MODIFICAR TIEMPO EN TIEMPO REAL
// =========================================
function abrirModificarTiempo(examenId, titulo, fechaFin, duracion) {
    document.getElementById('modalTiempoExamenId').value = examenId;
    document.getElementById('modalTiempoExamenTitulo').textContent = titulo;
    document.getElementById('modalFechaFinActual').textContent = fechaFin ? fechaFin.substring(0, 16).replace('T', ' ') : '--';
    
    // Calcular tiempo restante
    if(fechaFin) {
        const ahora = new Date();
        const fin = new Date(fechaFin);
        const diffMs = fin - ahora;
        if(diffMs > 0) {
            const mins = Math.floor(diffMs / 60000);
            const hrs = Math.floor(mins / 60);
            const restMins = mins % 60;
            document.getElementById('modalTiempoRestante').textContent = `${hrs}h ${restMins}m`;
        } else {
            document.getElementById('modalTiempoRestante').textContent = 'Expirado';
        }
    }
    
    // Obtener estudiantes rindiendo
    const formData = new FormData();
    formData.append('action', 'contar_estudiantes_rindiendo');
    formData.append('examen_id', examenId);
    
    fetch('docente_core.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        document.getElementById('modalEstudiantesRindiendo').textContent = data.cantidad || 0;
    })
    .catch(() => {
        document.getElementById('modalEstudiantesRindiendo').textContent = '?';
    });
    
    document.getElementById('inputTiempoPersonalizado').value = '';
    document.getElementById('modalModificarTiempo').style.display = 'flex';
}

function enviarModificacionTiempo(minutos) {
    if(minutos === 0) {
        alert('Por favor ingresa un valor diferente de 0');
        return;
    }
    
    const examenId = document.getElementById('modalTiempoExamenId').value;
    const accion = minutos > 0 ? 'agregar' : 'reducir';
    
    if(!confirm(`¿${accion.charAt(0).toUpperCase() + accion.slice(1)} ${Math.abs(minutos)} minuto(s) al examen?\n\nLos estudiantes verán el cambio automáticamente.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'modificar_tiempo_real');
    formData.append('examen_id', examenId);
    formData.append('minutos', minutos);
    
    fetch('docente_core.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            alert(`✅ Tiempo ${minutos > 0 ? 'agregado' : 'reducido'} correctamente.\n\nNueva fecha fin: ${data.nueva_fecha_fin}\nNueva duración: ${data.nueva_duracion} minutos\nEstudiantes afectados: ${data.estudiantes_afectados}`);
            
            // Actualizar datos locales
            for(let cursoId in examenesData) {
                examenesData[cursoId].forEach(ex => {
                    if(ex.id == examenId) {
                        ex.fecha_fin = data.nueva_fecha_fin;
                        ex.duracion_minutos = data.nueva_duracion;
                    }
                });
            }
            
            // Cerrar modal y refrescar vista
            document.getElementById('modalModificarTiempo').style.display = 'none';
            if(currentCurso) {
                const curso = cursosDocente.find(c => c.id_oferta == currentCurso);
                if(curso) verCurso(null, currentCurso, curso.materia);
            }
        } else {
            alert('❌ Error: ' + (data.error || 'No se pudo modificar el tiempo'));
        }
    })
    .catch(err => {
        alert('❌ Error de conexión: ' + err.message);
    });
}

function verBanco(examenId) {
    const content = document.getElementById('contenidoBancoPreguntas');
    content.innerHTML = '<div style="text-align:center; padding:20px;">Cargando preguntas...</div>';
    document.getElementById('modalVerPreguntas').style.display = 'flex';

    const formData = new FormData();
    formData.append('action', 'ver_banco_preguntas');
    formData.append('examen_id', examenId);

    fetch('docente_core.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        
        let html = `<div style="background:#e8f4fc; padding:10px; border-radius:6px; margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
            <span><strong>📊 Total:</strong> ${data.length} preguntas</span>
        </div>`;
        
        data.forEach((p, idx) => {
            let imgTag = p.imagen ? `<br><img src="${p.imagen}" style="max-width:300px; margin-top:10px; border:1px solid #ccc; padding:2px;">` : '';
            html += `<div class="revision-q" style="position:relative;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <h4 style="flex:1;">${idx+1}. ${p.enunciado} ${imgTag}</h4>
                            <div style="display:flex; align-items:center; gap:5px; background:#f0f9ff; padding:5px 10px; border-radius:6px; border:1px solid #b8d4ea;">
                                <label style="font-size:0.8rem; color:#0f6cb6; font-weight:bold;">Pts:</label>
                                <input type="number" step="0.01" min="0" value="${p.valor || 1}" 
                                       id="puntaje_${p.id}" 
                                       style="width:60px; padding:4px; border:1px solid #ccc; border-radius:4px; text-align:center;"
                                       onchange="actualizarPuntajePregunta(${p.id})">
                            </div>
                        </div>`;
            p.opciones.forEach(op => {
                let style = op.es_correcta == 1 ? "background:#d1fae5; border:1px solid #10b981;" : "background:#f9f9f9; border:1px solid #eee;";
                let label = op.es_correcta == 1 ? " <b style='color:#059669;'>✓ Correcta</b>" : "";
                let imgOpTag = op.imagen ? `<br><img src="${op.imagen}" style="max-width:150px; margin-top:5px; border:1px solid #ddd;">` : '';
                html += `<div style="padding:5px 10px; margin:5px 0; border-radius:4px; ${style}">
                            ${op.contenido}${imgOpTag}${label}
                         </div>`;
            });
            html += `</div>`;
        });
        content.innerHTML = html;
    }).catch(err => { content.innerHTML = '<p style="color:red">Error cargando preguntas.</p>'; });
}

function actualizarPuntajePregunta(preguntaId) {
    const input = document.getElementById('puntaje_' + preguntaId);
    const nuevoPuntaje = parseFloat(input.value);
    
    if (isNaN(nuevoPuntaje) || nuevoPuntaje < 0) {
        alert('Por favor ingresa un puntaje válido');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'actualizar_puntaje_pregunta');
    formData.append('pregunta_id', preguntaId);
    formData.append('puntaje', nuevoPuntaje);
    
    fetch('docente_core.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if(data.success) {
            input.style.borderColor = '#10b981';
            setTimeout(() => { input.style.borderColor = '#ccc'; }, 1000);
        }
    });
}

function abrirIntentos(examenId) {
    const intentos = intentosData[examenId] || [];
    let h = '<table><thead><tr><th>Estudiante</th><th>Inicio</th><th>Fin</th><th style="text-align:center">Puntaje</th><th>Acción</th></tr></thead><tbody>';
    if(intentos.length === 0) {
        h += '<tr><td colspan="5" style="text-align:center; color:#888;">Sin intentos.</td></tr>';
    } else {
        intentos.forEach(i => {
            let ptj = '';
            if (i.seguridad_motivo && i.puntaje_total == -1) {
                const mot = escapeHtml(i.seguridad_motivo);
                ptj = `<span style="background:#fee2e2; color:#b91c1c; font-weight:700; padding:4px 8px; border-radius:6px; border:1px solid #fca5a5; display:inline-block; font-size:12px;" title="${mot}">⚠️ Examen pausado (Seguridad)<br><small style="font-weight:600; color:#991b1b;">${mot}</small></span>`;
            } else if (i.seguridad_motivo) {
                const mot = escapeHtml(i.seguridad_motivo);
                const scoreColor = parseFloat(i.puntaje_total) < 7 ? '#ef4444' : '#10b981';
                ptj = `<span style="color:${scoreColor}; font-weight:bold;">${parseFloat(i.puntaje_total).toFixed(2)}</span> <span style="background:#fee2e2; color:#b91c1c; font-size:11px; padding:2px 6px; border-radius:4px; margin-left:4px;" title="${mot}">⚠️ Alerta: ${mot}</span>`;
            } else if (i.puntaje_total == -1) {
                ptj = '<span style="color:#f59e0b">⏳ Rindiendo</span>';
            } else {
                ptj = `<span style="color:${parseFloat(i.puntaje_total)<7?'#ef4444':'#10b981'}">${parseFloat(i.puntaje_total).toFixed(2)}</span>`;
            }
            h += `<tr>
                <td>${i.estudiante_nombre}</td>
                <td>${i.fecha_inicio_real ? i.fecha_inicio_real.substring(0,16) : '--'}</td>
                <td>${i.fecha_intento ? i.fecha_intento.substring(0,16) : '--'}</td>
                <td style="text-align:center">${ptj}</td>
                <td>
                    <button class="btn btn-sm btn-outline" onclick="verRevision(${examenId}, ${i.estudiante_id})" title="Revisar">📖</button>
                    <form method="POST" style="display:inline" onsubmit="return confirm('¿Rehabilitar intento?')">
                        <input type="hidden" name="id_examen_rehab" value="${examenId}">
                        <input type="hidden" name="id_estudiante_rehab" value="${i.estudiante_id}">
                        <button name="rehabilitar_estudiante" type="submit" class="btn btn-sm btn-outline">↻</button>
                    </form>
                </td>
            </tr>`;
        });
    }
    h += '</tbody></table>';
    document.getElementById('listaIntentos').innerHTML = h;
    document.getElementById('modalIntentos').style.display='flex';
}

function verRevision(eid, uid) {
    const div = document.getElementById('contenidoRevision');
    div.innerHTML = '<div style="padding:20px; text-align:center;">Cargando...</div>';
    document.getElementById('modalRevision').style.display = 'flex';
    
    const fd = new FormData();
    fd.append('action', 'cargar_revision_examen');
    fd.append('examen_id', eid);
    fd.append('estudiante_id', uid);
    
    fetch('docente_core.php', { method:'POST', body:fd })
    .then(r=>r.json())
    .then(d => {
        let h = '<div style="height:100%; overflow-y:auto;">';
        d.preguntas.forEach((p, idx) => {
            const respUser = (d.respuestas && (d.respuestas[p.id] ?? d.respuestas[String(p.id)])) ?? null;
            const ptsAsignados = parseFloat((p.puntaje_asignado !== undefined ? p.puntaje_asignado : p.valor)) || 0;
            const ptsObtenidos = parseFloat(p.puntaje_obtenido !== undefined ? p.puntaje_obtenido : 0) || 0;
            let imgTag = p.imagen ? `<br><img src="${p.imagen}" style="max-width:300px; margin-top:10px;">` : '';
            h += `<div class="revision-q"><h4>${idx+1}. ${p.enunciado} ${imgTag} <small>(Obtuvo ${ptsObtenidos.toFixed(2)} / ${ptsAsignados.toFixed(2)} pts)</small></h4>`;
            p.opciones.forEach(op => {
                let cls = ''; let lbl = ''; let btnCorrect = '';
                if(op.es_correcta == 1) { cls = 'opt-correcta'; lbl = ' <b style="color:green">(Correcta)</b>'; }
                if(op.id == respUser) { cls = (op.es_correcta==1) ? 'opt-correcta' : 'opt-incorrecta-user'; lbl += ' <b style="color:red">(Tu elección)</b>'; }
                if(op.es_correcta==1 && op.id==respUser) { lbl = ' <b style="color:green">(Tu elección - Correcta)</b>'; }
                
                if(op.es_correcta == 0) {
                    btnCorrect = `<button class="btn btn-sm btn-outline" style="font-size:0.7rem; float:right;" onclick="cambiarCorrecta(${p.id}, ${op.id}, ${eid})">✅ Definir como correcta</button>`;
                }
                
                let imgOp = op.imagen ? `<br><img src="${op.imagen}" style="max-width:150px;">` : '';
                h += `<div class="revision-opt ${cls}">${op.contenido}${imgOp} ${lbl} ${btnCorrect}</div>`;
            });
            h += '</div>';
        });
        h += '</div>';
        div.innerHTML = h;
    }).catch(e => div.innerHTML = 'Error cargando.');
}

function cambiarCorrecta(pid, oid, eid) {
    if(!confirm("¿Recalcular notas de TODOS los estudiantes cambiando esta respuesta?")) return;
    const fd = new FormData();
    fd.append('action', 'corregir_respuesta_global');
    fd.append('pregunta_id', pid);
    fd.append('opcion_id', oid);
    fd.append('examen_id', eid);
    
    fetch('docente_core.php', { method:'POST', body:fd })
    .then(r=>r.json())
    .then(res => {
        alert(res.msg);
        if(res.success) { document.getElementById('modalRevision').style.display='none'; }
    });
}

function verNotas(cursoId) {
    window.currentCursoIdNotas = cursoId;
    const divTabla    = document.getElementById('tablaCalificaciones');
    const estudiantes = (estudiantesData && estudiantesData[cursoId]) ? estudiantesData[cursoId] : [];
    const examenes    = (examenesData && examenesData[cursoId]) ? examenesData[cursoId] : [];
    const curso       = cursosDocente.find(c => c.id_oferta == cursoId);

    const examP1 = examenes.filter(ex => ex.parcial === 'primer_parcial' || !ex.parcial);
    const examP2 = examenes.filter(ex => ex.parcial === 'segundo_parcial');

    if (estudiantes.length === 0) {
        divTabla.innerHTML = '<div style="padding:20px; text-align:center; color:#888;">No hay estudiantes inscritos en este curso.</div>';
        document.getElementById('modalCalificaciones').style.display = 'flex';
        return;
    }

    let htmlHeader = '';
    if (curso) {
        htmlHeader = `
        <div class="calificaciones-header" id="infoCursoExport">
            <div class="calificaciones-info">
                <h4>${curso.materia}</h4>
                <p>
                    <strong>Ciclo:</strong> ${curso.ciclo} <span style="color:#ccc; margin:0 5px;">|</span> 
                    <strong>Paralelo:</strong> ${curso.paralelo}
                </p>
            </div>
            <div class="calificaciones-filter">
                <label style="font-size:0.85rem; font-weight:600; color:#555; margin-right:5px;">Ver:</label>
                <select id="selectParcialNotas" onchange="filtrarNotasParcial()">
                    <option value="todos">📋 Todos los Parciales</option>
                    <option value="primer_parcial">📘 Primer Parcial</option>
                    <option value="segundo_parcial">📗 Segundo Parcial</option>
                </select>
            </div>
        </div>`;
    }

    const obtenerResumenParcial = (listaExamenes, estudianteId) => {
        let suma = 0;
        let cantidad = 0;
        let rindiendo = false;
        let pausado = false;
        let motivoPausado = '';

        (listaExamenes || []).forEach(ex => {
            const intentosEx = intentosData[ex.id] || [];
            const intento = intentosEx.find(i => i.estudiante_id == estudianteId);
            if (!intento) return;

            const notaRaw = Number(intento.puntaje_total);
            if (intento.seguridad_motivo && notaRaw === -1) {
                pausado = true;
                motivoPausado = intento.seguridad_motivo;
                return;
            }
            if (notaRaw === -1) {
                rindiendo = true;
                return;
            }

            const nota = parseFloat(intento.puntaje_total);
            if (!Number.isNaN(nota)) {
                suma += nota;
                cantidad++;
            }
        });

        if (cantidad > 0) return { tipo: 'nota', valor: (suma / cantidad) };
        if (pausado) return { tipo: 'pausado', motivo: motivoPausado };
        if (rindiendo) return { tipo: 'rindiendo' };
        return { tipo: 'vacio' };
    };

    const renderCeldaParcial = (resumen, colorAprobado) => {
        if (resumen.tipo === 'pausado') return `<span style="color:#b91c1c; font-weight:700;" title="Pausado por seguridad: ${escapeHtml(resumen.motivo || '')}">⚠️ Pausado</span>`;
        if (resumen.tipo === 'rindiendo') return '<span style="color:#f59e0b">⏳</span>';
        if (resumen.tipo === 'nota') {
            const v = resumen.valor;
            const color = v < 7 ? '#ef4444' : colorAprobado;
            return `<span style="color:${color}; font-weight:600;">${v.toFixed(2)}</span>`;
        }
        return '-';
    };

    let htmlTable = '<div style="overflow-x:auto;"><table class="grade-table"><thead>';
    htmlTable += '<tr>';
    htmlTable += '<th style="width:40px; background:#fff;">#</th>';
    htmlTable += '<th style="text-align:left; background:#fff;">Estudiante</th>';
    htmlTable += '<th class="col-p1 col-p1-header" style="text-align:center; letter-spacing:1px;">📘 PRIMER PARCIAL</th>';
    htmlTable += '<th class="col-p2 col-p2-header" style="text-align:center; letter-spacing:1px;">📗 SEGUNDO PARCIAL</th>';
    htmlTable += '<th class="col-final" style="text-align:center; background:#fff7ed; color:#9a3412; letter-spacing:1px;">🏁 PROMEDIO FINAL</th>';
    htmlTable += '</tr></thead><tbody>';

    estudiantes.forEach((est, idx) => {
        htmlTable += `<tr>`;
        htmlTable += `<td style="font-size:0.8rem; color:#888;">${idx + 1}</td>`;
        htmlTable += `<td style="text-align:left; font-weight:600; color:#333;">${est.nombres} <br><span style="font-size:0.75rem; color:#999; font-weight:normal;">${est.cedula}</span></td>`;

        const resumenP1 = obtenerResumenParcial(examP1, est.estudiante_id);
        const resumenP2 = obtenerResumenParcial(examP2, est.estudiante_id);

        htmlTable += `<td class="col-p1" style="background:#eff6ff;">${renderCeldaParcial(resumenP1, '#1e3a8a')}</td>`;
        htmlTable += `<td class="col-p2" style="background:#f0fdf4;">${renderCeldaParcial(resumenP2, '#14532d')}</td>`;

        const notasFinales = [];
        if (resumenP1.tipo === 'nota') notasFinales.push(resumenP1.valor);
        if (resumenP2.tipo === 'nota') notasFinales.push(resumenP2.valor);

        let finalStr = '-';
        if (notasFinales.length > 0) {
            const promedioFinal = notasFinales.reduce((acc, n) => acc + n, 0) / notasFinales.length;
            const colorFinal = promedioFinal < 7 ? '#dc2626' : '#9a3412';
            finalStr = `<span style="color:${colorFinal}; font-weight:700;">${promedioFinal.toFixed(2)}</span>`;
        } else if (resumenP1.tipo === 'rindiendo' || resumenP2.tipo === 'rindiendo') {
            finalStr = '<span style="color:#f59e0b">⏳</span>';
        }
        htmlTable += `<td class="col-final td-promedio" style="background:#fff7ed;">${finalStr}</td>`;

        htmlTable += `</tr>`;
    });
    htmlTable += '</tbody></table></div>';

    divTabla.innerHTML = htmlHeader + htmlTable;
    document.getElementById('modalCalificaciones').style.display = 'flex';
}

function filtrarNotasParcial() {
    const filtro = document.getElementById('selectParcialNotas').value;
    const colsP1 = document.querySelectorAll('.col-p1');
    const colsP2 = document.querySelectorAll('.col-p2');
    const colsFinal = document.querySelectorAll('.col-final');
    
    if (filtro === 'todos') {
        colsP1.forEach(c => c.style.display = '');
        colsP2.forEach(c => c.style.display = '');
        colsFinal.forEach(c => c.style.display = '');
    } else if (filtro === 'primer_parcial') {
        colsP1.forEach(c => c.style.display = '');
        colsP2.forEach(c => c.style.display = 'none');
        colsFinal.forEach(c => c.style.display = '');
    } else if (filtro === 'segundo_parcial') {
        colsP1.forEach(c => c.style.display = 'none');
        colsP2.forEach(c => c.style.display = '');
        colsFinal.forEach(c => c.style.display = '');
    }
}

function exportarExcel() {
    var container = document.getElementById("tablaCalificaciones");
    if (!container || container.innerText.includes("Sin estudiantes")) {
        alert("No hay datos para exportar.");
        return;
    }

    var table = container.querySelector("table");
    if (!table) return;

    var cursoId = window.currentCursoIdNotas || currentCurso;
    var curso = (cursosDocente || []).find(c => String(c.id_oferta) === String(cursoId)) || {};
    var materia = (curso.materia || "Materia").toString().trim();
    var paralelo = (curso.paralelo || "Paralelo").toString().trim();
    var ciclo = (curso.ciclo || "").toString().trim();

    // Mantener solo columnas visibles según el filtro actual.
    var tempTable = table.cloneNode(true);
    var headerCells = tempTable.querySelectorAll("thead th");
    var hiddenIndexes = [];
    headerCells.forEach(function (th, idx) {
        if (th.style.display === "none") hiddenIndexes.push(idx);
    });
    if (hiddenIndexes.length > 0) {
        tempTable.querySelectorAll("tr").forEach(function (row) {
            var cells = Array.from(row.children);
            hiddenIndexes.slice().reverse().forEach(function (colIdx) {
                if (cells[colIdx]) cells[colIdx].remove();
            });
        });
    }

    // Estilos visuales para que el reporte quede ordenado.
    tempTable.style.width = "100%";
    tempTable.style.borderCollapse = "collapse";
    tempTable.style.fontFamily = "'Segoe UI', Arial, sans-serif";
    tempTable.style.fontSize = "12px";
    tempTable.style.border = "1px solid #cbd5e1";

    tempTable.querySelectorAll("thead th").forEach(function (th) {
        th.style.background = "#e2e8f0";
        th.style.color = "#1e293b";
        th.style.fontWeight = "700";
        th.style.border = "1px solid #cbd5e1";
        th.style.padding = "8px";
        th.style.textAlign = "center";
    });

    tempTable.querySelectorAll("tbody tr").forEach(function (tr, i) {
        tr.style.background = (i % 2 === 0) ? "#ffffff" : "#f8fafc";
    });

    tempTable.querySelectorAll("tbody td").forEach(function (td) {
        td.style.border = "1px solid #e2e8f0";
        td.style.padding = "7px 8px";
        td.style.verticalAlign = "middle";
    });

    var now = new Date();
    var fecha = now.toLocaleDateString("es-EC");
    var hora = now.toLocaleTimeString("es-EC");

    var htmlDoc = `
    <html>
    <head>
        <meta charset="UTF-8">
    </head>
    <body>
        <div style="font-family:'Segoe UI', Arial, sans-serif; margin-bottom:12px;">
            <div style="font-size:18px; font-weight:700; color:#0f172a; margin-bottom:4px;">Informe de Calificaciones</div>
            <div style="font-size:13px; color:#334155;"><strong>Materia:</strong> ${escapeHtml(materia)} &nbsp;|&nbsp; <strong>Paralelo:</strong> ${escapeHtml(paralelo)}</div>
            ${ciclo ? `<div style="font-size:13px; color:#334155;"><strong>Ciclo:</strong> ${escapeHtml(ciclo)}</div>` : ''}
            <div style="font-size:12px; color:#64748b; margin-top:2px;">Generado: ${fecha} ${hora}</div>
        </div>
        ${tempTable.outerHTML}
    </body>
    </html>`;

    var normalizarNombreArchivo = function (txt) {
        return (txt || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[^\w\- ]+/g, "")
            .trim()
            .replace(/\s+/g, "_");
    };

    var materiaFile = normalizarNombreArchivo(materia) || "Materia";
    var paraleloFile = normalizarNombreArchivo(paralelo) || "Paralelo";
    var fechaFile = now.toISOString().slice(0, 10);
    var fileName = `${materiaFile}_${paraleloFile}_${fechaFile}.xls`;

    var blob = new Blob(['\ufeff', htmlDoc], { type: 'application/vnd.ms-excel' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function toggleAll(chk) { document.querySelectorAll('.chk-est').forEach(c => c.checked = chk.checked); }

document.addEventListener("DOMContentLoaded", function() {
    // Autodetectar preguntas del DOCX
    const archivoDocxInput = document.getElementById('archivo_docx');
    const preguntasInput = document.getElementById('input_cant_preguntas');
    const lblDetect = document.getElementById('lbl_preguntas_detectadas');
    const durInput = document.getElementById('duracion_minutos');
    const inicioInput = document.getElementById('fecha_inicio');
    const finInput = document.getElementById('fecha_fin');
    const inicioProgInput = document.getElementById('inicio_programado');
    const finProgInput = document.getElementById('fecha_fin_prog');
    const sumaFin = () => {
        if (!inicioInput || !finInput || !durInput) return;
        const inicioVal = inicioInput.value;
        const dur = parseInt(durInput.value || '0', 10);
        if (!inicioVal || isNaN(dur) || dur <= 0) return;
        const dt = new Date(inicioVal);
        if (isNaN(dt.getTime())) return;
        dt.setMinutes(dt.getMinutes() + dur);
        const pad = (n) => String(n).padStart(2, '0');
        const localStr = `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
        finInput.value = localStr;
    };
    const sumaFinProg = () => {
        if (!inicioProgInput || !finProgInput || !durInput) return;
        const inicioVal = inicioProgInput.value;
        const dur = parseInt(durInput.value || '0', 10);
        if (!inicioVal || isNaN(dur) || dur <= 0) return;
        const dt = new Date(inicioVal);
        if (isNaN(dt.getTime())) return;
        dt.setMinutes(dt.getMinutes() + dur);
        const pad = (n) => String(n).padStart(2, '0');
        const localStr = `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
        finProgInput.value = localStr;
    };
    if (inicioInput) {
        inicioInput.addEventListener('change', sumaFin);
        inicioInput.addEventListener('input', sumaFin);
    }
    if (inicioProgInput) {
        inicioProgInput.addEventListener('change', sumaFinProg);
        inicioProgInput.addEventListener('input', sumaFinProg);
    }
    if (durInput) durInput.addEventListener('input', sumaFin);
    if (durInput) durInput.addEventListener('input', sumaFinProg);
    // Ajustar fin si ya vienen valores precargados
    sumaFin();
    sumaFinProg();
    if (archivoDocxInput) {
        archivoDocxInput.addEventListener('change', () => {
            lblDetect.textContent = '';
            if (!archivoDocxInput.files || !archivoDocxInput.files[0]) return;
            const file = archivoDocxInput.files[0];
            if (file.type !== 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' && !file.name.endsWith('.docx')) {
                lblDetect.textContent = 'Archivo no es .docx';
                return;
            }
            lblDetect.textContent = 'Detectando preguntas...';
            const fd = new FormData();
            fd.append('action', 'contar_preguntas_docx');
            fd.append('docx_temp', file);
            fetch('docente_core.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const total = parseInt(data.total_preguntas || 0);
                        preguntasInput.value = isNaN(total) ? 0 : total;
                        lblDetect.textContent = total > 0 ? `Preguntas detectadas: ${total}` : 'No se detectaron preguntas';
                    } else {
                        lblDetect.textContent = data.error || 'No se pudo detectar preguntas';
                    }
                })
                .catch(() => {
                    lblDetect.textContent = 'Error al leer el archivo';
                });
        });
    }

    window.addEventListener('scroll', () => {
        if (scrollSaveTimer) clearTimeout(scrollSaveTimer);
        scrollSaveTimer = setTimeout(guardarScrollActual, 120);
    });
    window.addEventListener('beforeunload', () => {
        guardarScrollActual();
        guardarEstadoSecciones();
    });
    const cid = sessionStorage.getItem('docente_curso_actual');
    if(cid) {
        const c = cursosDocente.find(x => x.id_oferta == cid);
        if(c) verCurso(null, cid, c.materia);
    } else {
        const vista = sessionStorage.getItem('docente_vista_actual');
        if(vista === 'director') verPanelDirector(null);
        else if(vista === 'director_area') verPanelDirectorArea(null);
        else if(vista === 'perfil') verPerfil();
        else setTimeout(restaurarScrollActual, 50);
    }
});
</script>
</body>
</html>
