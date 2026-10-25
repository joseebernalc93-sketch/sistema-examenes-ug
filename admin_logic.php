<?php
session_start();
include "db.php";

if (!isset($_SESSION["usuario"]) || $_SESSION["usuario"]["rol"] != "administrador") {
    header("Location: index.php");
    exit;
}

/* =========================================
   ACCIONES GET ESPECIALES (DESCARGAR ARCHIVO)
   ========================================= */
if (isset($_GET['accion']) && $_GET['accion'] == 'descargar_archivo' && isset($_GET['id'])) {
    $fid = intval($_GET['id']);
    $q = $conn->query("SELECT nombre_archivo, contenido FROM archivos_importados WHERE id=$fid LIMIT 1");
    if ($q && $q->num_rows > 0) {
        $f = $q->fetch_assoc();
        $ext = strtolower(pathinfo($f['nombre_archivo'], PATHINFO_EXTENSION));
        $ctype = "application/octet-stream";
        if ($ext == 'csv') $ctype = "text/csv";
        elseif ($ext == 'xlsx') $ctype = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
        header("Content-Description: File Transfer");
        header("Content-Type: $ctype");
        header("Content-Disposition: attachment; filename=\"".$f['nombre_archivo']."\"");
        header("Content-Transfer-Encoding: binary");
        header("Expires: 0");
        header("Cache-Control: must-revalidate");
        header("Pragma: public");
        echo $f['contenido'];
        exit;
    }
}

// --- DESCARGAR ARCHIVO DE ÁREAS ---
if (isset($_GET['descargar_archivo_areas'])) {
    $id = intval($_GET['descargar_archivo_areas']);
    $res = $conn->query("SELECT nombre_archivo, contenido FROM archivos_areas WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) {
        $nombre = $row['nombre_archivo'];
        $contenido = $row['contenido'];
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $ctype = ($ext === 'xlsx') ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv';
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $ctype);
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $contenido;
        exit;
    }
}

/* =========================================
   INICIALIZACIÓN DE TABLAS (SQL AUTOMÁTICO)
   ========================================= */
$conn->query("CREATE TABLE IF NOT EXISTS `restricciones_materias` (`id` int(11) NOT NULL AUTO_INCREMENT, `materia_a_id` int(11) NOT NULL, `materia_b_id` int(11) NOT NULL, PRIMARY KEY (`id`), KEY `idx_mat_a` (`materia_a_id`), KEY `idx_mat_b` (`materia_b_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
$conn->query("CREATE TABLE IF NOT EXISTS `auditoria_log` (`id` int(11) NOT NULL AUTO_INCREMENT, `actor_id` int(11) NOT NULL, `actor_nombre` varchar(150) DEFAULT NULL, `rol_actor` varchar(50) NOT NULL, `accion` varchar(50) NOT NULL, `entidad_afectada` varchar(50) DEFAULT NULL, `id_afectado` int(11) DEFAULT NULL, `detalles` text DEFAULT NULL, `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), KEY `idx_actor` (`actor_id`), KEY `idx_fecha` (`fecha_registro`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS `notificaciones_seguridad_examen` (`id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, `docente_id` int(11) NOT NULL, `curso_id` int(11) NOT NULL, `examen_id` int(11) NOT NULL, `estudiante_id` int(11) NOT NULL, `tipo` varchar(80) NOT NULL DEFAULT 'salida_video_externa', `motivo` text NOT NULL, `oculto_docente` tinyint(1) NOT NULL DEFAULT 0, `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), KEY `idx_notif_docente_fecha` (`docente_id`,`fecha_creacion`), KEY `idx_notif_curso_fecha` (`curso_id`,`fecha_creacion`), KEY `idx_notif_examen` (`examen_id`), KEY `idx_notif_estudiante` (`estudiante_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
$conn->query("CREATE TABLE IF NOT EXISTS `archivos_importados` (`id` int(11) NOT NULL AUTO_INCREMENT, `ciclo_id` int(11) NOT NULL, `nombre_archivo` varchar(255) NOT NULL, `contenido` LONGBLOB, `fecha_subida` datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), KEY `idx_ciclo_archivo` (`ciclo_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
$conn->query("CREATE TABLE IF NOT EXISTS `areas_materias` (`id` int(11) NOT NULL AUTO_INCREMENT, `nombre_materia` varchar(255) NOT NULL, `nombre_area` varchar(255) NOT NULL, `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(), PRIMARY KEY (`id`), UNIQUE KEY `idx_materia_unica` (`nombre_materia`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

try {
    $chk = $conn->query("SHOW COLUMNS FROM archivos_importados LIKE 'tipo'");
    if($chk->num_rows == 0) { $conn->query("ALTER TABLE archivos_importados ADD COLUMN tipo VARCHAR(50) DEFAULT 'planificacion' AFTER ciclo_id"); }
    $chk2 = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'archivo_origen_id'");
    if($chk2->num_rows == 0) { $conn->query("ALTER TABLE usuarios ADD COLUMN archivo_origen_id INT(11) NULL DEFAULT NULL"); $conn->query("ALTER TABLE usuarios ADD INDEX (archivo_origen_id)"); }
    $chk3 = $conn->query("SHOW COLUMNS FROM cursos_oferta LIKE 'archivo_importacion_id'");
    if($chk3->num_rows == 0) { $conn->query("ALTER TABLE cursos_oferta ADD COLUMN archivo_importacion_id INT(11) NULL DEFAULT NULL"); $conn->query("ALTER TABLE cursos_oferta ADD INDEX (archivo_importacion_id)"); }
    $chk4 = $conn->query("SHOW COLUMNS FROM usuarios LIKE 'area'");
    if($chk4->num_rows == 0) { $conn->query("ALTER TABLE usuarios ADD COLUMN area VARCHAR(255) NULL DEFAULT NULL AFTER carrera"); }
    $chk5 = $conn->query("SHOW COLUMNS FROM notificaciones_seguridad_examen LIKE 'oculto_docente'");
    if($chk5 && $chk5->num_rows == 0) { $conn->query("ALTER TABLE notificaciones_seguridad_examen ADD COLUMN oculto_docente TINYINT(1) NOT NULL DEFAULT 0 AFTER motivo"); }
    $conn->query("ALTER TABLE `restricciones_materias` ADD CONSTRAINT `fk_rest_mata` FOREIGN KEY (`materia_a_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE");
    $conn->query("ALTER TABLE `restricciones_materias` ADD CONSTRAINT `fk_rest_matb` FOREIGN KEY (`materia_b_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE");
    $conn->query("ALTER TABLE `archivos_importados` ADD CONSTRAINT `fk_archivo_ciclo` FOREIGN KEY (`ciclo_id`) REFERENCES `ciclos` (`id`) ON DELETE CASCADE");
} catch (Exception $e) { /* Ignorar */ }

/* =======================
   HELPERS
   ======================= */
function esc($s){ global $conn; return $conn->real_escape_string(trim($s)); }
function qx($sql){ global $conn; $r = $conn->query($sql); if ($r === false) { throw new Exception("SQL Error: ".$conn->error." | Query: ".$sql); } return $r; }
function tablaExiste($tabla){ global $conn; $t = $conn->real_escape_string($tabla); $q = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$t' LIMIT 1"); return ($q && $q->num_rows > 0); }
function limpiar_datos_huerfanos() { global $conn; $conn->query("DELETE p FROM paralelos p LEFT JOIN cursos_oferta co ON p.id = co.paralelo_id WHERE co.id IS NULL"); $conn->query("DELETE m FROM materias m LEFT JOIN paralelos p ON m.id = p.materia_id WHERE p.id IS NULL"); $conn->query("DELETE s FROM semestres s LEFT JOIN materias m ON s.id = m.semestre_id WHERE m.id IS NULL"); $conn->query("DELETE c FROM carreras c LEFT JOIN semestres s ON c.id = s.carrera_id WHERE s.id IS NULL"); $conn->query("DELETE f FROM facultades f LEFT JOIN carreras c ON f.id = c.facultad_id WHERE c.id IS NULL"); $conn->query("DELETE FROM restricciones_materias WHERE materia_a_id NOT IN (SELECT id FROM materias) OR materia_b_id NOT IN (SELECT id FROM materias)"); }
function registrar_log($actor_id, $actor_nombre, $rol, $accion, $entidad, $id_afectado, $detalles){ global $conn; $actor_nombre = esc($actor_nombre); $rol = esc($rol); $accion = esc($accion); $entidad = esc($entidad); $detalles = esc($detalles); $id_afectado = (int)$id_afectado; $actor_id = (int)$actor_id; $sql = "INSERT INTO auditoria_log (actor_id, actor_nombre, rol_actor, accion, entidad_afectada, id_afectado, detalles) VALUES ($actor_id, '$actor_nombre', '$rol', '$accion', '$entidad', $id_afectado, '$detalles')"; $conn->query($sql); }
function asegurar_facultad($nombre){ global $conn; $nombre = esc($nombre); $q = $conn->query("SELECT id FROM facultades WHERE nombre='$nombre' LIMIT 1"); if ($q && $q->num_rows){ return (int)$q->fetch_assoc()['id']; } $conn->query("INSERT INTO facultades(nombre) VALUES('$nombre')"); return (int)$conn->insert_id; }
function asegurar_ciclo($nombre,$ini=null,$fin=null,$activo=1){ global $conn; $nombre = esc($nombre); $q = $conn->query("SELECT id FROM ciclos WHERE nombre='$nombre' LIMIT 1"); if ($q && $q->num_rows){ return (int)$q->fetch_assoc()['id']; } $fi = $ini ? "'".esc($ini)."'" : "NULL"; $ff = $fin ? "'".esc($fin)."'" : "NULL"; $ac = $activo?1:0; $conn->query("INSERT INTO ciclos(nombre,fecha_inicio,fecha_fin,activo) VALUES('$nombre',$fi,$ff,$ac)"); return (int)$conn->insert_id; }
function asegurar_carrera($facultad_id,$nombre){ global $conn; $nombre = esc($nombre); $q = $conn->query("SELECT id FROM carreras WHERE facultad_id=$facultad_id AND nombre='$nombre' LIMIT 1"); if ($q && $q->num_rows){ return (int)$q->fetch_assoc()['id']; } $conn->query("INSERT INTO carreras(facultad_id,nombre) VALUES($facultad_id,'$nombre')"); return (int)$conn->insert_id; }
function asegurar_semestre($carrera_id,$numero){ global $conn; $numero = (int)$numero; $q = $conn->query("SELECT id FROM semestres WHERE carrera_id=$carrera_id AND numero=$numero LIMIT 1"); if ($q && $q->num_rows){ return (int)$q->fetch_assoc()['id']; } $conn->query("INSERT INTO semestres(carrera_id,numero) VALUES($carrera_id,$numero)"); return (int)$conn->insert_id; }
function asegurar_materia($semestre_id,$nombre,$codigo=null){ global $conn; $nombre = esc($nombre); $q = $conn->query("SELECT id FROM materias WHERE semestre_id=$semestre_id AND nombre='$nombre' LIMIT 1"); if ($q && $q->num_rows){ return (int)$q->fetch_assoc()['id']; } $cod = $codigo ? "'".esc($codigo)."'" : "NULL"; $conn->query("INSERT INTO materias(semestre_id,codigo,nombre) VALUES($semestre_id,$cod,'$nombre')"); return (int)$conn->insert_id; }
function asegurar_paralelo($materia_id,$nombre){ global $conn; $nombre = esc($nombre); $q = $conn->query("SELECT id FROM paralelos WHERE materia_id=$materia_id AND nombre='$nombre' LIMIT 1"); if ($q && $q->num_rows){ return (int)$q->fetch_assoc()['id']; } $conn->query("INSERT INTO paralelos(materia_id,nombre) VALUES($materia_id,'$nombre')"); return (int)$conn->insert_id; }
function asegurar_oferta($ciclo_id, $paralelo_id, $archivo_id=null){ global $conn; $q = $conn->query("SELECT id FROM cursos_oferta WHERE ciclo_id=$ciclo_id AND paralelo_id=$paralelo_id LIMIT 1"); if ($q && $q->num_rows){ return (int)$q->fetch_assoc()['id']; } $archVal = $archivo_id ? (int)$archivo_id : "NULL"; $conn->query("INSERT INTO cursos_oferta(ciclo_id,paralelo_id, archivo_importacion_id) VALUES($ciclo_id, $paralelo_id, $archVal)"); return (int)$conn->insert_id; }
function get_materia_id_by_name($nombre) { global $conn; $nombre = esc($nombre); $q = $conn->query("SELECT id FROM materias WHERE nombre LIKE '$nombre' LIMIT 1"); if ($q && $q->num_rows) return (int)$q->fetch_assoc()['id']; return null; }
function registrar_restriccion_pair($idA, $idB) { global $conn; if ($idA == $idB) return false; $min = min($idA, $idB); $max = max($idA, $idB); $check = $conn->query("SELECT id FROM restricciones_materias WHERE materia_a_id=$min AND materia_b_id=$max"); if ($check && $check->num_rows > 0) return false; $conn->query("INSERT INTO restricciones_materias (materia_a_id, materia_b_id) VALUES ($min, $max)"); return true; }
function normalize_header($h){ $h = trim($h); $h = mb_strtolower($h, 'UTF-8'); $h = strtr($h, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']); $h = preg_replace('/[\s\-_]+/', '', $h); return $h; }
function detect_delimiter($line){ $c = [','=>0,';'=>0,"\t"=>0,'|'=>0]; foreach ($c as $d=>$_) { $c[$d] = substr_count($line, $d); } arsort($c); $best = array_key_first($c); return ($c[$best] > 0) ? $best : ','; }
function parse_semestre_from_paralelo($code){ if (preg_match('/-(\d+)-/', $code, $m)) return (int)$m[1]; if (preg_match('/(\d+)/', $code, $m)) return (int)$m[1]; return 1; }
function limpiarTexto($texto) { $texto = str_replace(["\r\n", "\r", "\n"], '', $texto); $texto = preg_replace('/\s+/', ' ', $texto); return trim($texto); }
function read_csv_rows($path){ $rows = []; $fp = fopen($path, 'r'); if (!$fp) return $rows; $first = fgets($fp); if ($first === false) { fclose($fp); return $rows; } $bom = pack('H*','EFBBBF'); $first = preg_replace("/^$bom/", '', $first); $delim = detect_delimiter($first); $headers = array_map('normalize_header', str_getcsv($first, $delim)); while(($line = fgets($fp)) !== false){ $cells = str_getcsv($line, $delim); if (count($cells) == 1 && trim($cells[0])==='') continue; $row = []; foreach ($headers as $i=>$key){ $row[$key] = isset($cells[$i]) ? trim($cells[$i]) : ''; } $rows[] = $row; } fclose($fp); return $rows; }
function read_xlsx_rows($path){ if (!class_exists('ZipArchive')) throw new Exception("ZipArchive no está instalado en este servidor."); $rows = []; $zip = new ZipArchive(); if ($zip->open($path) !== true) return $rows; $shared = []; $ssIdx = $zip->locateName('xl/sharedStrings.xml'); if ($ssIdx !== false) { $xml = $zip->getFromIndex($ssIdx); if ($xml) { $doc = new DOMDocument(); $doc->loadXML($xml); foreach ($doc->getElementsByTagName('si') as $si) { $txt = ''; foreach ($si->getElementsByTagName('t') as $t){ $txt .= $t->nodeValue; } $shared[] = trim($txt); } } } $sheetNames = ['xl/worksheets/sheet1.xml','xl/worksheets/worksheet1.xml']; $sheetXml = null; foreach ($sheetNames as $name){ $idx = $zip->locateName($name); if ($idx !== false){ $sheetXml = $zip->getFromIndex($idx); break; } } if (!$sheetXml){ $zip->close(); return $rows; } $sx = new DOMDocument(); $sx->loadXML($sheetXml); $tmpRows = []; foreach ($sx->getElementsByTagName('row') as $r){ $cells = []; foreach ($r->getElementsByTagName('c') as $c){ $t = $c->getAttribute('t'); $vNode = $c->getElementsByTagName('v')->item(0); $val = $vNode ? $vNode->nodeValue : ''; if ($t === 's'){ $idx = (int)$val; $val = isset($shared[$idx]) ? $shared[$idx] : ''; } elseif ($t === 'inlineStr'){ $is = $c->getElementsByTagName('is')->item(0); if ($is){ $tn = $is->getElementsByTagName('t')->item(0); if ($tn) $val = $tn->nodeValue; } } $cells[] = trim($val); } $allEmpty = true; foreach ($cells as $cv){ if ($cv!==''){ $allEmpty=false; break; } } if ($allEmpty) continue; $tmpRows[] = $cells; } $zip->close(); if (empty($tmpRows)) return $rows; $headers = array_map('normalize_header', $tmpRows[0]); for ($i=1; $i<count($tmpRows); $i++){ $row = []; foreach ($headers as $j=>$key){ $row[$key] = isset($tmpRows[$i][$j]) ? trim($tmpRows[$i][$j]) : ''; } $rows[] = $row; } return $rows; }

/* =======================
   ACCIONES GET
   ======================= */
if (isset($_GET["accion"]) && isset($_GET["id"]) && $_GET["accion"] != 'descargar_archivo') {
    $id = intval($_GET["id"]);
    if ($_GET["accion"] == "aceptar") { $conn->query("UPDATE usuarios SET estado='aceptado' WHERE id=$id"); }
    elseif ($_GET["accion"] == "rechazar" || $_GET["accion"] == "eliminar") { $conn->begin_transaction(); try { $conn->query("DELETE FROM usuario_cursos WHERE usuario_id=$id"); $conn->query("DELETE FROM usuarios WHERE id=$id"); $conn->commit(); } catch (Throwable $e) { $conn->rollback(); } }
    header("Location: admin.php?seccion=usuarios"); exit;
}

/* =======================
   ACCIONES POST
   ======================= */
$import_msg = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- ELIMINAR REGISTROS SELECCIONADOS DEL HISTORIAL ---
    if (isset($_POST['eliminar_logs_seleccionados'])) {
        $ids = isset($_POST['logs_ids']) ? array_map('intval', (array)$_POST['logs_ids']) : [];
        $ids = array_values(array_filter($ids, function($v){ return $v > 0; }));

        if (empty($ids)) {
            $_SESSION['historial_msg'] = "Selecciona al menos un registro para eliminar.";
            header("Location: admin.php?seccion=historial");
            exit;
        }

        $admin_id = (int)($_SESSION['usuario']['id'] ?? 0);
        $admin_nombre = trim((string)($_SESSION['usuario']['nombres'] ?? 'Administrador'));
        $ids_str = implode(',', $ids);

        $conn->begin_transaction();
        try {
            $conn->query("DELETE FROM auditoria_log WHERE id IN ($ids_str)");
            $eliminados = (int)$conn->affected_rows;

            registrar_log(
                $admin_id,
                $admin_nombre,
                'administrador',
                'ELIMINAR_LOGS',
                'Auditoria',
                0,
                "Elimino $eliminados registro(s) seleccionados del historial."
            );

            $conn->commit();
            $_SESSION['historial_msg'] = "Se eliminaron $eliminados registro(s) del historial.";
        } catch (Throwable $e) {
            $conn->rollback();
            $_SESSION['historial_msg'] = "Error al eliminar registros: " . $e->getMessage();
        }

        header("Location: admin.php?seccion=historial");
        exit;
    }

    // --- CREAR NUEVO CICLO ---
    if (isset($_POST['crear_ciclo'])) { $nombre_ciclo = trim($_POST['nuevo_ciclo_nombre'] ?? ''); if (!empty($nombre_ciclo)) { asegurar_ciclo($nombre_ciclo); header("Location: admin.php?seccion=cursos&msg=ciclo_creado"); exit; } }

    // --- ELIMINAR CICLO (BORRADO TOTAL EN CASCADA) ---
    if (isset($_POST['eliminar_ciclo'])) {
        $id_ciclo = intval($_POST['id_ciclo_eliminar']);
        if ($id_ciclo > 0) {
            $conn->begin_transaction();
            try {
                // =====================================================
                // PASO 1: Recopilar todos los IDs relacionados
                // =====================================================
                
                // 1.1 Archivos importados de este ciclo
                $ids_archivos = [];
                $res = $conn->query("SELECT id FROM archivos_importados WHERE ciclo_id = $id_ciclo");
                while ($r = $res->fetch_assoc()) { $ids_archivos[] = (int)$r['id']; }
                
                // 1.2 Cursos del ciclo
                $ids_cursos = [];
                $res = $conn->query("SELECT id FROM cursos_oferta WHERE ciclo_id = $id_ciclo");
                while ($r = $res->fetch_assoc()) { $ids_cursos[] = (int)$r['id']; }
                
                // 1.3 Paralelos de los cursos
                $ids_paralelos = [];
                if (!empty($ids_cursos)) {
                    $res = $conn->query("SELECT DISTINCT paralelo_id FROM cursos_oferta WHERE ciclo_id = $id_ciclo");
                    while ($r = $res->fetch_assoc()) { $ids_paralelos[] = (int)$r['paralelo_id']; }
                }
                
                // 1.4 Materias de los paralelos
                $ids_materias = [];
                if (!empty($ids_paralelos)) {
                    $pStr = implode(',', $ids_paralelos);
                    $res = $conn->query("SELECT DISTINCT materia_id FROM paralelos WHERE id IN ($pStr)");
                    while ($r = $res->fetch_assoc()) { $ids_materias[] = (int)$r['materia_id']; }
                }
                
                // 1.5 Semestres de las materias
                $ids_semestres = [];
                if (!empty($ids_materias)) {
                    $mStr = implode(',', $ids_materias);
                    $res = $conn->query("SELECT DISTINCT semestre_id FROM materias WHERE id IN ($mStr)");
                    while ($r = $res->fetch_assoc()) { $ids_semestres[] = (int)$r['semestre_id']; }
                }
                
                // 1.6 Carreras de los semestres
                $ids_carreras = [];
                if (!empty($ids_semestres)) {
                    $sStr = implode(',', $ids_semestres);
                    $res = $conn->query("SELECT DISTINCT carrera_id FROM semestres WHERE id IN ($sStr)");
                    while ($r = $res->fetch_assoc()) { $ids_carreras[] = (int)$r['carrera_id']; }
                }
                
                // 1.7 Facultades de las carreras
                $ids_facultades = [];
                if (!empty($ids_carreras)) {
                    $cStr = implode(',', $ids_carreras);
                    $res = $conn->query("SELECT DISTINCT facultad_id FROM carreras WHERE id IN ($cStr)");
                    while ($r = $res->fetch_assoc()) { $ids_facultades[] = (int)$r['facultad_id']; }
                }
                
                // 1.8 Usuarios importados en este ciclo (excepto admin id=1)
                $ids_usuarios = [];
                if (!empty($ids_archivos)) {
                    $aStr = implode(',', $ids_archivos);
                    $res = $conn->query("SELECT id FROM usuarios WHERE archivo_origen_id IN ($aStr) AND id <> 1");
                    while ($r = $res->fetch_assoc()) { $ids_usuarios[] = (int)$r['id']; }
                }
                
                // 1.9 Exámenes de los cursos
                $ids_examenes = [];
                if (!empty($ids_cursos)) {
                    $coStr = implode(',', $ids_cursos);
                    $res = $conn->query("SELECT id FROM examenes WHERE curso_id IN ($coStr)");
                    while ($r = $res->fetch_assoc()) { $ids_examenes[] = (int)$r['id']; }
                }
                
                // 1.10 Preguntas de los exámenes
                $ids_preguntas = [];
                if (!empty($ids_examenes)) {
                    $eStr = implode(',', $ids_examenes);
                    $res = $conn->query("SELECT id FROM preguntas WHERE examen_id IN ($eStr)");
                    while ($r = $res->fetch_assoc()) { $ids_preguntas[] = (int)$r['id']; }
                }
                
                // =====================================================
                // PASO 2: Eliminar en orden (de más dependiente a menos)
                // =====================================================
                
                // 2.1 Opciones de preguntas
                if (!empty($ids_preguntas)) {
                    $pqStr = implode(',', $ids_preguntas);
                    $conn->query("DELETE FROM opciones_pregunta WHERE pregunta_id IN ($pqStr)");
                }
                
                // 2.2 Preguntas
                if (!empty($ids_examenes)) {
                    $eStr = implode(',', $ids_examenes);
                    $conn->query("DELETE FROM preguntas WHERE examen_id IN ($eStr)");
                }
                
                // 2.3 Resultados de exámenes
                if (!empty($ids_examenes)) {
                    $eStr = implode(',', $ids_examenes);
                    $conn->query("DELETE FROM resultados_examen WHERE examen_id IN ($eStr)");
                }
                
                // 2.4 Exámenes
                if (!empty($ids_cursos)) {
                    $coStr = implode(',', $ids_cursos);
                    $conn->query("DELETE FROM examenes WHERE curso_id IN ($coStr)");
                }
                
                // 2.5 Obtener estudiantes inscritos en estos cursos ANTES de eliminar inscripciones
                $ids_estudiantes_cursos = [];
                if (!empty($ids_cursos)) {
                    $coStr = implode(',', $ids_cursos);
                    $res = $conn->query("SELECT DISTINCT uc.usuario_id 
                                         FROM usuario_cursos uc 
                                         JOIN usuarios u ON uc.usuario_id = u.id 
                                         WHERE uc.curso_id IN ($coStr) 
                                         AND u.rol = 'estudiante'");
                    while ($r = $res->fetch_assoc()) { 
                        $ids_estudiantes_cursos[] = (int)$r['usuario_id']; 
                    }
                }
                
                // 2.6 Usuario_cursos (inscripciones)
                if (!empty($ids_cursos)) {
                    $coStr = implode(',', $ids_cursos);
                    $conn->query("DELETE FROM usuario_cursos WHERE curso_id IN ($coStr)");
                }
                
                // 2.7 Eliminar estudiantes que ya no tienen inscripciones en ningún curso
                if (!empty($ids_estudiantes_cursos)) {
                    foreach ($ids_estudiantes_cursos as $est_id) {
                        // Verificar si el estudiante tiene otras inscripciones
                        $check = $conn->query("SELECT COUNT(*) as total FROM usuario_cursos WHERE usuario_id = $est_id");
                        $row = $check->fetch_assoc();
                        if ((int)$row['total'] === 0) {
                            // No tiene más inscripciones, eliminar estudiante
                            $conn->query("DELETE FROM auditoria_log WHERE actor_id = $est_id");
                            $conn->query("DELETE FROM resultados_examen WHERE estudiante_id = $est_id");
                            $conn->query("DELETE FROM usuarios WHERE id = $est_id AND rol = 'estudiante'");
                        }
                    }
                }
                
                // 2.8 Auditoría de usuarios importados a eliminar
                if (!empty($ids_usuarios)) {
                    $uStr = implode(',', $ids_usuarios);
                    $conn->query("DELETE FROM auditoria_log WHERE actor_id IN ($uStr)");
                }
                
                // 2.9 Usuarios importados (excepto admin)
                if (!empty($ids_usuarios)) {
                    $uStr = implode(',', $ids_usuarios);
                    $conn->query("DELETE FROM usuarios WHERE id IN ($uStr) AND id <> 1");
                }
                
                // 2.10 Cursos oferta
                $conn->query("DELETE FROM cursos_oferta WHERE ciclo_id = $id_ciclo");
                
                // 2.11 Archivos importados
                $conn->query("DELETE FROM archivos_importados WHERE ciclo_id = $id_ciclo");
                
                // 2.12 Restricciones de materias
                if (!empty($ids_materias)) {
                    $mStr = implode(',', $ids_materias);
                    $conn->query("DELETE FROM restricciones_materias WHERE materia_a_id IN ($mStr) OR materia_b_id IN ($mStr)");
                }
                
                // 2.13 Paralelos
                if (!empty($ids_paralelos)) {
                    $pStr = implode(',', $ids_paralelos);
                    $conn->query("DELETE FROM paralelos WHERE id IN ($pStr)");
                }
                
                // 2.14 Materias
                if (!empty($ids_materias)) {
                    $mStr = implode(',', $ids_materias);
                    $conn->query("DELETE FROM materias WHERE id IN ($mStr)");
                }
                
                // 2.15 Semestres
                if (!empty($ids_semestres)) {
                    $sStr = implode(',', $ids_semestres);
                    $conn->query("DELETE FROM semestres WHERE id IN ($sStr)");
                }
                
                // 2.16 Carreras
                if (!empty($ids_carreras)) {
                    $cStr = implode(',', $ids_carreras);
                    $conn->query("DELETE FROM carreras WHERE id IN ($cStr)");
                }
                
                // 2.17 Facultades
                if (!empty($ids_facultades)) {
                    $fStr = implode(',', $ids_facultades);
                    $conn->query("DELETE FROM facultades WHERE id IN ($fStr)");
                }
                
                // 2.18 Limpiar v_cursos (si existe y tiene datos del ciclo)
                if (tablaExiste('v_cursos')) {
                    $conn->query("DELETE FROM v_cursos WHERE 1=1"); // Limpiar tabla auxiliar
                }
                
                // 2.19 Finalmente eliminar el ciclo
                $conn->query("DELETE FROM ciclos WHERE id = $id_ciclo");
                
                $conn->commit();
                $msg = "ciclo_eliminado_completo";
            } catch (Throwable $e) {
                $conn->rollback();
                $msg = "error_eliminar_ciclo";
            }
            header("Location: admin.php?seccion=cursos&msg=$msg");
            exit;
        }
    }

    // --- ELIMINAR ARCHIVO PLANIFICACIÓN ---
    if (isset($_POST['eliminar_archivo_planificacion'])) {
        $id_archivo_del = intval($_POST['id_archivo_planificacion']); $ciclo_redirect = intval($_POST['ciclo_redirect'] ?? 0);
        if ($id_archivo_del > 0) { $conn->begin_transaction(); try { $resCursos = $conn->query("SELECT id FROM cursos_oferta WHERE archivo_importacion_id = $id_archivo_del"); $ids_cursos = []; while ($c = $resCursos->fetch_assoc()) { $ids_cursos[] = (int)$c['id']; } if (!empty($ids_cursos)) { $c_str = implode(',', $ids_cursos); $conn->query("DELETE FROM usuario_cursos WHERE curso_id IN ($c_str)"); if (tablaExiste('examenes')) { if (tablaExiste('resultados_examen')) { $conn->query("DELETE FROM resultados_examen WHERE examen_id IN (SELECT id FROM examenes WHERE curso_id IN ($c_str))"); } $conn->query("DELETE FROM examenes WHERE curso_id IN ($c_str)"); } $conn->query("DELETE FROM cursos_oferta WHERE id IN ($c_str)"); } $resCiclo = $conn->query("SELECT ciclo_id FROM archivos_importados WHERE id = $id_archivo_del"); if ($resCiclo && $rowCiclo = $resCiclo->fetch_assoc()) { $ciclo_id_target = $rowCiclo['ciclo_id']; $resArchivosDoc = $conn->query("SELECT id FROM archivos_importados WHERE ciclo_id = $ciclo_id_target AND tipo='docentes'"); $ids_archivos_doc = []; while($ad = $resArchivosDoc->fetch_assoc()) { $ids_archivos_doc[] = $ad['id']; } if(!empty($ids_archivos_doc)) { $ids_arch_doc_str = implode(',', $ids_archivos_doc); $resUsers = $conn->query("SELECT id FROM usuarios WHERE archivo_origen_id IN ($ids_arch_doc_str) AND id <> 1"); $ids_users_del = []; while($u = $resUsers->fetch_assoc()){ $ids_users_del[] = $u['id']; } if(!empty($ids_users_del)) { $users_str = implode(',', $ids_users_del); $conn->query("DELETE FROM usuario_cursos WHERE usuario_id IN ($users_str)"); $conn->query("DELETE FROM auditoria_log WHERE actor_id IN ($users_str)"); $conn->query("DELETE FROM usuarios WHERE id IN ($users_str)"); } } } $conn->query("DELETE FROM archivos_importados WHERE id = $id_archivo_del"); limpiar_datos_huerfanos(); $conn->commit(); $import_msg = "✅ Archivo eliminado correctamente."; $_SESSION['import_preview'] = ['msg' => $import_msg]; } catch (Throwable $e) { $conn->rollback(); $_SESSION['import_preview'] = ['msg' => "❌ Error al eliminar archivo: " . $e->getMessage()]; } }
        header("Location: admin.php?seccion=cursos&ciclo_filtro=$ciclo_redirect&import=1"); exit;
    }

    // --- ELIMINAR USUARIOS SELECCIONADOS ---
    if (isset($_POST['eliminar_usuarios_seleccionados'])) { $seleccion = isset($_POST['sel_usuarios']) ? array_map('intval', $_POST['sel_usuarios']) : []; if (!empty($seleccion)) { $ids_str = implode(',', $seleccion); $conn->begin_transaction(); try { $conn->query("DELETE FROM usuario_cursos WHERE usuario_id IN ($ids_str)"); if (tablaExiste('resultados_examen')) { $conn->query("DELETE FROM resultados_examen WHERE estudiante_id IN ($ids_str)"); } $conn->query("DELETE FROM auditoria_log WHERE actor_id IN ($ids_str)"); $conn->query("DELETE FROM usuarios WHERE id IN ($ids_str) AND id <> 1"); $conn->commit(); } catch (Throwable $e) { $conn->rollback(); } } header("Location: admin.php?seccion=usuarios&msg=usuarios_eliminados"); exit; }

    // --- ACEPTAR USUARIOS ---
    if (isset($_POST['aceptar_usuarios_seleccionados'])) { $seleccion = isset($_POST['sel_usuarios']) ? array_map('intval', $_POST['sel_usuarios']) : []; if (!empty($seleccion)) { $ids_str = implode(',', $seleccion); $conn->query("UPDATE usuarios SET estado='aceptado' WHERE id IN ($ids_str)"); } header("Location: admin.php?seccion=usuarios"); exit; }

    // --- ELIMINAR ARCHIVO DOCENTES ---
    if (isset($_POST['eliminar_archivo_docentes'])) { $id_archivo_del = intval($_POST['id_archivo_docentes']); if ($id_archivo_del > 0) { $conn->begin_transaction(); try { $resUsers = qx("SELECT id FROM usuarios WHERE archivo_origen_id = $id_archivo_del"); $ids_usuarios = []; while ($u = $resUsers->fetch_assoc()) { $ids_usuarios[] = (int)$u['id']; } if (!empty($ids_usuarios)) { $u_str = implode(',', $ids_usuarios); if (tablaExiste('resultados_examen')) { qx("DELETE FROM resultados_examen WHERE estudiante_id IN ($u_str)"); } qx("DELETE FROM usuario_cursos WHERE usuario_id IN ($u_str)"); qx("DELETE FROM auditoria_log WHERE actor_id IN ($u_str)"); qx("DELETE FROM usuarios WHERE id IN ($u_str) AND id <> 1"); } qx("DELETE FROM archivos_importados WHERE id = $id_archivo_del"); $conn->commit(); $_SESSION['import_preview']['msg'] = "✅ Archivo y " . count($ids_usuarios) . " usuarios eliminados."; } catch (Throwable $e) { $conn->rollback(); $_SESSION['import_preview']['msg'] = "❌ Error: " . $e->getMessage(); } } header("Location: admin.php?seccion=usuarios"); exit; }

    // --- IMPORTAR DOCENTES (MEJORADO) ---
    if (isset($_POST['importar_docentes'])) {
        $ciclo_imp_id = isset($_POST['ciclo_id_import_docentes']) ? intval($_POST['ciclo_id_import_docentes']) : 0;
        if ($ciclo_imp_id <= 0) { $import_msg = "❌ Debes seleccionar un Ciclo."; $_SESSION['import_preview']['msg'] = $import_msg; }
        elseif (!isset($_FILES['archivo_docentes']) || $_FILES['archivo_docentes']['error'] !== UPLOAD_ERR_OK) { $import_msg = "❌ Error en la subida del archivo."; $_SESSION['import_preview']['msg'] = $import_msg; }
        else {
            $conn->begin_transaction();
            try {
                $tmp = $_FILES['archivo_docentes']['tmp_name']; $name = $_FILES['archivo_docentes']['name']; $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $fileData = file_get_contents($tmp); $fileDataEscaped = $conn->real_escape_string($fileData); $fileNameEscaped = esc($name);
                $conn->query("INSERT INTO archivos_importados (ciclo_id, nombre_archivo, contenido, tipo) VALUES ($ciclo_imp_id, '$fileNameEscaped', '$fileDataEscaped', 'docentes')");
                $archivo_actual_id = $conn->insert_id;
                if ($ext === 'csv') $rows = read_csv_rows($tmp); elseif ($ext === 'xlsx') $rows = read_xlsx_rows($tmp); else throw new Exception("Formato no soportado");
                $count_users = 0; $count_courses = 0;
                foreach ($rows as $r) {
                    $rol_raw = limpiarTexto($r['rol'] ?? ''); $area_raw = limpiarTexto($r['area'] ?? ''); $nombres = limpiarTexto($r['apellidosynombres'] ?? ''); $facultad = limpiarTexto($r['facultad'] ?? ''); $carrera = limpiarTexto($r['carrera'] ?? ''); $cedula = limpiarTexto($r['cedula'] ?? ''); $email = limpiarTexto($r['correoinstitucional'] ?? ''); $materias_raw = limpiarTexto($r['materia'] ?? ''); $cursos_raw = limpiarTexto($r['cursos'] ?? '');
                    if (!$cedula || !$nombres) continue;
                    $rol = 'docente'; $area_usuario = null; $rol_upper = strtoupper($rol_raw); $area_upper = strtoupper($area_raw);
                    if (strpos($rol_upper, 'DIRECTOR') !== false && strpos($rol_upper, 'CARRERA') !== false) { if (strpos($area_upper, 'DIRECTOR') !== false && strpos($area_upper, 'CARRERA') !== false) { $rol = 'director_carrera'; $area_usuario = null; } }
                    elseif (strpos($rol_upper, 'DIRECTOR') !== false && (strpos($rol_upper, 'AREA') !== false || strpos($rol_upper, 'ÁREA') !== false)) { $rol = 'director_area'; if (!empty($area_raw) && $area_upper !== 'NINGUNA' && $area_upper !== 'NINGUNO' && strpos($area_upper, 'DIRECTOR') === false) { $area_usuario = $area_raw; } }
                    elseif (strpos($rol_upper, 'DOCENTE') !== false || $rol_upper === '') { $rol = 'docente'; $area_usuario = null; }
                    $passHash = md5($cedula); $nombresEsc = esc($nombres); $facEsc = esc($facultad); $carEsc = esc($carrera); $cedEsc = esc($cedula); $emailEsc = esc($email); $rolEsc = esc($rol); $areaEsc = $area_usuario ? "'" . esc($area_usuario) . "'" : "NULL";
                    $sql_user = "INSERT INTO usuarios (nombres, cedula, email, password, rol, facultad, carrera, area, estado, archivo_origen_id) VALUES ('$nombresEsc', '$cedEsc', '$emailEsc', '$passHash', '$rolEsc', '$facEsc', '$carEsc', $areaEsc, 'aceptado', $archivo_actual_id) ON DUPLICATE KEY UPDATE nombres='$nombresEsc', rol='$rolEsc', facultad='$facEsc', carrera='$carEsc', area=$areaEsc, estado='aceptado', archivo_origen_id=$archivo_actual_id";
                    $conn->query($sql_user);
                    $user_id = $conn->insert_id; if ($user_id == 0) { $res = $conn->query("SELECT id FROM usuarios WHERE cedula='$cedEsc'"); if ($res && $rowU = $res->fetch_assoc()) $user_id = $rowU['id']; } if ($user_id) $count_users++;
                    // ============================================
                    // PROCESAR MATERIAS Y CURSOS CON CORRESPONDENCIA POSICIONAL
                    // Materia[0] corresponde a Cursos[0], Materia[1] a Cursos[1], etc.
                    // ============================================
                    if ($user_id && $cursos_raw && $materias_raw) {
                        // Separar materias y grupos de cursos por "/"
                        $lista_materias = array_map('trim', explode('/', $materias_raw));
                        $lista_cursos_grupos = array_map('trim', explode('/', $cursos_raw));
                        
                        // Iterar por índice para mantener correspondencia
                        $num_materias = count($lista_materias);
                        $num_grupos_cursos = count($lista_cursos_grupos);
                        
                        for ($idx = 0; $idx < $num_grupos_cursos; $idx++) {
                            $grupo_cursos = trim($lista_cursos_grupos[$idx]);
                            if (!$grupo_cursos) continue;
                            
                            // Obtener la materia correspondiente (si existe)
                            $materia_correspondiente = ($idx < $num_materias) ? trim($lista_materias[$idx]) : '';
                            
                            // Los cursos individuales dentro del grupo se separan por ","
                            $cursos_individuales = array_map('trim', explode(',', $grupo_cursos));
                            
                            foreach ($cursos_individuales as $codigo_paralelo) {
                                $codigo_paralelo = trim($codigo_paralelo);
                                if (!$codigo_paralelo) continue;
                                
                                $cpEsc = esc($codigo_paralelo);
                                $curso_encontrado = false;
                                
                                // PASO 1: Buscar el curso validando materia (si tenemos materia)
                                if (!empty($materia_correspondiente)) {
                                    $matEsc = esc($materia_correspondiente);
                                    $sql_curso = "SELECT co.id FROM cursos_oferta co 
                                                  JOIN paralelos p ON co.paralelo_id = p.id 
                                                  JOIN materias m ON p.materia_id = m.id
                                                  WHERE TRIM(p.nombre) = '$cpEsc' 
                                                  AND UPPER(TRIM(m.nombre)) = UPPER('$matEsc')
                                                  AND co.ciclo_id = $ciclo_imp_id LIMIT 1";
                                    $resC = $conn->query($sql_curso);
                                    if ($resC && $rowC = $resC->fetch_assoc()) {
                                        $curso_id = $rowC['id'];
                                        $conn->query("INSERT IGNORE INTO usuario_cursos (usuario_id, curso_id) VALUES ($user_id, $curso_id)");
                                        $count_courses++;
                                        $curso_encontrado = true;
                                    }
                                }
                                
                                // PASO 2: Si no encontró con materia, buscar solo por paralelo (fallback)
                                if (!$curso_encontrado) {
                                    $sql_curso = "SELECT co.id FROM cursos_oferta co 
                                                  JOIN paralelos p ON co.paralelo_id = p.id 
                                                  WHERE TRIM(p.nombre) = '$cpEsc' 
                                                  AND co.ciclo_id = $ciclo_imp_id LIMIT 1";
                                    $resC = $conn->query($sql_curso);
                                    if ($resC && $rowC = $resC->fetch_assoc()) {
                                        $curso_id = $rowC['id'];
                                        $conn->query("INSERT IGNORE INTO usuario_cursos (usuario_id, curso_id) VALUES ($user_id, $curso_id)");
                                        $count_courses++;
                                    }
                                }
                            }
                        }
                    }
                }
                $conn->commit(); $import_msg = "✅ Importación exitosa: $count_users usuarios procesados y $count_courses asignaciones de cursos."; $_SESSION['import_preview']['msg'] = $import_msg;
            } catch (Throwable $e) { $conn->rollback(); $import_msg = "❌ Error: " . $e->getMessage(); $_SESSION['import_preview']['msg'] = $import_msg; }
        }
        header("Location: admin.php?seccion=usuarios&import=1"); exit;
    }

    // --- IMPORTAR EXCEL CURSOS ---
    if (isset($_POST['importar_excel'])) {
        $target_ciclo_id = isset($_POST['ciclo_id']) ? intval($_POST['ciclo_id']) : 0;
        if ($target_ciclo_id <= 0) { $import_msg = "❌ Selecciona un Ciclo."; }
        else { $chk_dup = $conn->query("SELECT id FROM cursos_oferta WHERE ciclo_id = $target_ciclo_id LIMIT 1"); if ($chk_dup && $chk_dup->num_rows > 0) { $import_msg = "❌ El ciclo ya tiene cursos cargados."; } elseif (!isset($_FILES['archivos_cursos']) || !isset($_FILES['archivos_cursos']['name'])) { $import_msg = "❌ No se recibieron archivos."; } else { $names = $_FILES['archivos_cursos']['name']; $tmps = $_FILES['archivos_cursos']['tmp_name']; $errs = $_FILES['archivos_cursos']['error']; $total_ins=0; $total_skip=0; $total_err=0; $conn->begin_transaction(); try { $cres = $conn->query("SELECT nombre FROM ciclos WHERE id=$target_ciclo_id"); $ciclo_nombre_fijo = ($cres && $cres->num_rows) ? $cres->fetch_assoc()['nombre'] : "ID:$target_ciclo_id"; for ($i=0; $i<count((array)$names); $i++){ if (!isset($errs[$i]) || $errs[$i] !== UPLOAD_ERR_OK) { $total_err++; continue; } $tmp = $tmps[$i]; $name = $names[$i]; $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)); $fileData = file_get_contents($tmp); $file_insert_id = null; if ($ext === 'csv' || $ext === 'xlsx') { $fileDataEscaped = $conn->real_escape_string($fileData); $fileNameEscaped = esc($name); $conn->query("INSERT INTO archivos_importados (ciclo_id, nombre_archivo, contenido, tipo) VALUES ($target_ciclo_id, '$fileNameEscaped', '$fileDataEscaped', 'planificacion')"); $file_insert_id = $conn->insert_id; $rows = ($ext === 'csv') ? read_csv_rows($tmp) : read_xlsx_rows($tmp); foreach ($rows as $row) { $raw_fac = $row['facultad'] ?? ''; $raw_carr = $row['carrera'] ?? ''; $raw_mat = $row['asignatura'] ?? ($row['materia'] ?? ''); $raw_par = $row['paralelo'] ?? ''; if (!$raw_par || !$raw_mat) { $total_skip++; continue; } $sem_num = parse_semestre_from_paralelo($raw_par); $fac_id = asegurar_facultad($raw_fac); $car_id = asegurar_carrera($fac_id, $raw_carr); $sem_id = asegurar_semestre($car_id, $sem_num); $mat_id = asegurar_materia($sem_id, $raw_mat); $par_id = asegurar_paralelo($mat_id, $raw_par); asegurar_oferta($target_ciclo_id, $par_id, $file_insert_id); $total_ins++; } } else { $total_err++; } } $conn->commit(); $import_msg = "✅ $total_ins cursos creados. Filas vacías: $total_skip. Errores: $total_err."; } catch (Throwable $e) { $conn->rollback(); $import_msg = "❌ Error: " . $e->getMessage(); } } }
        $_SESSION['import_preview'] = ['msg' => $import_msg]; header("Location: admin.php?seccion=cursos&ciclo_filtro=$target_ciclo_id&import=1"); exit;
    }

    // --- IMPORTAR ÁREAS DE MATERIAS ---
    // --- IMPORTAR ÁREAS DE MATERIAS ---
    if (isset($_POST['importar_areas_materias'])) {
        if (!isset($_FILES['archivo_areas']) || $_FILES['archivo_areas']['error'] !== UPLOAD_ERR_OK) { 
            $import_msg = "❌ Error en archivo de áreas."; 
            $_SESSION['import_preview']['msg'] = $import_msg; 
        } else { 
            $conn->begin_transaction(); 
            try { 
                $tmp = $_FILES['archivo_areas']['tmp_name']; 
                $name = $_FILES['archivo_areas']['name']; 
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION)); 
                
                if ($ext === 'csv') $rows = read_csv_rows($tmp); 
                elseif ($ext === 'xlsx') $rows = read_xlsx_rows($tmp); 
                else throw new Exception("Formato no soportado"); 
                
                $count_areas = 0; 
                foreach ($rows as $r) { 
                    $materia = limpiarTexto($r['materia'] ?? ''); 
                    $area = limpiarTexto($r['area'] ?? ''); 
                    if (!$materia || !$area) continue; 
                    $materiaEsc = esc($materia); 
                    $areaEsc = esc($area); 
                    $conn->query("INSERT INTO areas_materias (nombre_materia, nombre_area) VALUES ('$materiaEsc', '$areaEsc') ON DUPLICATE KEY UPDATE nombre_area = '$areaEsc'"); 
                    $count_areas++; 
                }
                
                // Guardar archivo en archivos_areas
                $contenido = file_get_contents($tmp);
                $contenidoEsc = $conn->real_escape_string($contenido);
                $nameEsc = esc($name);
                $conn->query("INSERT INTO archivos_areas (nombre_archivo, contenido, total_registros) VALUES ('$nameEsc', '$contenidoEsc', $count_areas)");
                
                $conn->commit(); 
                $import_msg = "✅ $count_areas áreas importadas correctamente."; 
                $_SESSION['import_preview']['msg'] = $import_msg; 
            } catch (Throwable $e) { 
                $conn->rollback(); 
                $import_msg = "❌ Error: " . $e->getMessage(); 
                $_SESSION['import_preview']['msg'] = $import_msg; 
            } 
        }
        header("Location: admin.php?seccion=usuarios&import=1"); exit;
    }
    
    // --- ELIMINAR ARCHIVO DE ÁREAS (borra también areas_materias) ---
    if (isset($_POST['eliminar_archivo_areas'])) {
        $id_archivo = intval($_POST['id_archivo_areas']);
        if ($id_archivo > 0) {
            $conn->begin_transaction();
            try {
                // Eliminar todos los registros de areas_materias
                $conn->query("DELETE FROM areas_materias WHERE 1=1");
                
                // Eliminar el archivo de archivos_areas
                $conn->query("DELETE FROM archivos_areas WHERE id = $id_archivo");
                
                $conn->commit();
                $_SESSION['import_preview']['msg'] = "✅ Archivo de áreas eliminado. Tabla areas_materias vaciada.";
            } catch (Throwable $e) {
                $conn->rollback();
                $_SESSION['import_preview']['msg'] = "❌ Error al eliminar: " . $e->getMessage();
            }
        }
        header("Location: admin.php?seccion=usuarios&import=1"); exit;
    }

    // --- CREAR MATERIA + PARALELOS ---
    if (isset($_POST['crear_materia_semestre'])) { $ciclo_id = intval($_POST['ciclo_id']); $fac_nom = $_POST['facultad_semestre']; $carr_nom = $_POST['carrera_semestre']; $sem_num = $_POST['nivel']; $mat_nom = $_POST['materia_semestre']; $pars_raw = $_POST['paralelos_semestre']; if ($ciclo_id > 0) { $fac_id = asegurar_facultad($fac_nom); $car_id = asegurar_carrera($fac_id, $carr_nom); $sem_id = asegurar_semestre($car_id, $sem_num); $mat_id = asegurar_materia($sem_id, $mat_nom); $lista_p = explode(',', $pars_raw); foreach($lista_p as $p){ $p = trim($p); if($p === '') continue; $par_id = asegurar_paralelo($mat_id, $p); asegurar_oferta($ciclo_id, $par_id); } } header("Location: admin.php?seccion=cursos&agregado_semestre=1&ciclo_filtro=$ciclo_id"); exit; }
}

/* =======================
   CONSULTAS Y DATOS PARA LA VISTA
   ======================= */
$sql_pendientes = "SELECT COUNT(*) as total FROM usuarios WHERE estado='pendiente'";
$res_pendientes = $conn->query($sql_pendientes);
$total_pendientes = ($res_pendientes) ? (int)$res_pendientes->fetch_assoc()['total'] : 0;

$campos_usuario_base = "u.id, u.nombres, u.cedula, u.facultad, u.carrera, u.area, u.rol, u.estado, u.email";
$orden_nombre = "TRIM(SUBSTRING_INDEX(u.nombres, ' ', 1)) ASC, u.nombres ASC";
$sql_join_cursos = "LEFT JOIN usuario_cursos uc ON u.id = uc.usuario_id LEFT JOIN cursos_oferta co ON uc.curso_id = co.id LEFT JOIN paralelos p ON co.paralelo_id = p.id LEFT JOIN materias m ON p.materia_id = m.id LEFT JOIN semestres s ON m.semestre_id = s.id LEFT JOIN carreras ca ON s.carrera_id = ca.id LEFT JOIN ciclos ci ON co.ciclo_id = ci.id LEFT JOIN facultades f ON ca.facultad_id = f.id";
$select_cursos_concatenados = "GROUP_CONCAT(DISTINCT f.nombre ORDER BY f.nombre SEPARATOR '\n') AS facultad_registrada, "
    . "GROUP_CONCAT(DISTINCT ca.nombre ORDER BY ca.nombre SEPARATOR '\n') AS carrera_registrada, "
    . "GROUP_CONCAT(DISTINCT s.numero ORDER BY s.numero SEPARATOR '\n') AS semestre_registrado, "
    . "GROUP_CONCAT(DISTINCT m.nombre ORDER BY s.numero ASC, m.nombre ASC SEPARATOR '\n') AS materia_registrada, "
    . "GROUP_CONCAT(DISTINCT p.nombre ORDER BY s.numero ASC, m.nombre ASC SEPARATOR '\n') AS paralelo_registrado, "
    . "GROUP_CONCAT(DISTINCT co.ciclo_id) AS ciclos_ids";

$usuarios_directivos_y_docentes = $conn->query("SELECT $campos_usuario_base, $select_cursos_concatenados FROM usuarios u $sql_join_cursos WHERE u.rol IN ('docente', 'director_carrera', 'director_area') GROUP BY u.id ORDER BY $orden_nombre");
$estudiantes = $conn->query("SELECT $campos_usuario_base, $select_cursos_concatenados FROM usuarios u $sql_join_cursos WHERE u.rol='estudiante' GROUP BY u.id ORDER BY $orden_nombre");

$docentes_aceptados = []; $directores_carrera = []; $directores_area = [];
if ($usuarios_directivos_y_docentes) { while ($row = $usuarios_directivos_y_docentes->fetch_assoc()) { if ($row['estado'] == 'aceptado') { if ($row['rol'] == 'director_carrera') $directores_carrera[] = $row; elseif ($row['rol'] == 'director_area') $directores_area[] = $row; } $docentes_aceptados[] = $row; } }
$docentes = $docentes_aceptados;

$usuarios_tabla = [];

// Consulta basica primero - garantizada de funcionar
try {
    $res_basico = $conn->query("SELECT id, nombres, cedula, facultad, carrera, area, rol, estado, email FROM usuarios WHERE rol <> 'administrador' ORDER BY nombres ASC");
    if ($res_basico) {
        while ($row = $res_basico->fetch_assoc()) {
            // Agregar campos vacios para compatibilidad
            $row['facultad_registrada'] = '';
            $row['carrera_registrada'] = '';
            $row['semestre_registrado'] = '';
            $row['materia_registrada'] = '';
            $row['paralelo_registrado'] = '';
            $row['ciclos_ids'] = '';
            $usuarios_tabla[] = $row;
        }
    }
} catch (Throwable $e) {
    // Si falla, la tabla estara vacia
}

// Si tenemos usuarios, intentar enriquecer con datos de cursos
if (!empty($usuarios_tabla)) {
    $usuarios_enriquecido = [];

    foreach ($usuarios_tabla as $usuario) {
        $uid = (int)$usuario['id'];

        try {
            // Obtener cursos del usuario
            $sql_cursos = "SELECT
                GROUP_CONCAT(DISTINCT f.nombre ORDER BY f.nombre SEPARATOR '\n') AS facultad_reg,
                GROUP_CONCAT(DISTINCT ca.nombre ORDER BY ca.nombre SEPARATOR '\n') AS carrera_reg,
                GROUP_CONCAT(DISTINCT s.numero ORDER BY s.numero SEPARATOR '\n') AS semestre_reg,
                GROUP_CONCAT(DISTINCT m.nombre ORDER BY m.nombre SEPARATOR '\n') AS materia_reg,
                GROUP_CONCAT(DISTINCT p.nombre ORDER BY p.nombre SEPARATOR '\n') AS paralelo_reg,
                GROUP_CONCAT(DISTINCT co.ciclo_id) AS ciclos
            FROM usuario_cursos uc
            LEFT JOIN cursos_oferta co ON uc.curso_id = co.id
            LEFT JOIN paralelos p ON co.paralelo_id = p.id
            LEFT JOIN materias m ON p.materia_id = m.id
            LEFT JOIN semestres s ON m.semestre_id = s.id
            LEFT JOIN carreras ca ON s.carrera_id = ca.id
            LEFT JOIN facultades f ON ca.facultad_id = f.id
            WHERE uc.usuario_id = $uid";

            $res_cursos = $conn->query($sql_cursos);
            if ($res_cursos && $cursos_data = $res_cursos->fetch_assoc()) {
                $usuario['facultad_registrada'] = $cursos_data['facultad_reg'] ?? '';
                $usuario['carrera_registrada'] = $cursos_data['carrera_reg'] ?? '';
                $usuario['semestre_registrado'] = $cursos_data['semestre_reg'] ?? '';
                $usuario['materia_registrada'] = $cursos_data['materia_reg'] ?? '';
                $usuario['paralelo_registrado'] = $cursos_data['paralelo_reg'] ?? '';
                $usuario['ciclos_ids'] = $cursos_data['ciclos'] ?? '';
            }
        } catch (Throwable $e) {
            // Mantener campos vacios si falla
        }

        $usuarios_enriquecido[] = $usuario;
    }

    $usuarios_tabla = $usuarios_enriquecido;
}

$materias_paralelos_map = []; $materias_unicas_list = [];
$consulta_filtro_usuarios = $conn->query("SELECT DISTINCT m.nombre AS materia, p.nombre AS paralelo FROM usuario_cursos uc JOIN cursos_oferta co ON uc.curso_id = co.id JOIN paralelos p ON co.paralelo_id = p.id JOIN materias m ON p.materia_id = m.id ORDER BY m.nombre ASC, p.nombre ASC");
if ($consulta_filtro_usuarios) { while ($row = $consulta_filtro_usuarios->fetch_assoc()) { $materia = $row['materia']; if (!isset($materias_paralelos_map[$materia])) { $materias_paralelos_map[$materia] = []; $materias_unicas_list[] = $materia; } $materias_paralelos_map[$materia][] = $row['paralelo']; } }

$facultades_filtro = [];
$res_fac = $conn->query("SELECT DISTINCT nombre FROM facultades ORDER BY nombre ASC");
if ($res_fac) { while ($r = $res_fac->fetch_assoc()) { $facultades_filtro[] = $r['nombre']; } }

$carreras_filtro = [];
$res_car = $conn->query("SELECT DISTINCT nombre FROM carreras ORDER BY nombre ASC");
if ($res_car) { while ($r = $res_car->fetch_assoc()) { $carreras_filtro[] = $r['nombre']; } }

$semestres_filtro = [];
$res_sem = $conn->query("SELECT DISTINCT numero FROM semestres ORDER BY numero ASC");
if ($res_sem) { while ($r = $res_sem->fetch_assoc()) { $semestres_filtro[] = $r['numero']; } }

$materias_filtro = [];
$res_mat = $conn->query("SELECT DISTINCT nombre FROM materias ORDER BY nombre ASC");
if ($res_mat) { while ($r = $res_mat->fetch_assoc()) { $materias_filtro[] = $r['nombre']; } }

$paralelos_filtro = [];
$res_par = $conn->query("SELECT DISTINCT nombre FROM paralelos ORDER BY nombre ASC");
if ($res_par) { while ($r = $res_par->fetch_assoc()) { $paralelos_filtro[] = $r['nombre']; } }

$sql_ciclos = "SELECT c.id, c.nombre, c.fecha_inicio, c.fecha_fin, (SELECT id FROM archivos_importados ai WHERE ai.ciclo_id = c.id AND tipo='planificacion' ORDER BY ai.fecha_subida DESC LIMIT 1) as ultimo_archivo_id FROM ciclos c ORDER BY COALESCE(c.fecha_inicio,'1900-01-01') DESC, c.nombre DESC";
$lista_ciclos_filtro = $conn->query($sql_ciclos);
$todos_los_ciclos = [];
if($lista_ciclos_filtro){ while($row = $lista_ciclos_filtro->fetch_assoc()) $todos_los_ciclos[] = $row; }

$ciclo_filtro_id = isset($_GET['ciclo_filtro']) ? intval($_GET['ciclo_filtro']) : 0;
if ($ciclo_filtro_id === 0 && count($todos_los_ciclos) > 0) { $ciclo_filtro_id = (int)$todos_los_ciclos[0]['id']; }

$archivos_ciclo = [];
if ($ciclo_filtro_id > 0) { $res_archs = $conn->query("SELECT id, nombre_archivo, fecha_subida FROM archivos_importados WHERE ciclo_id = $ciclo_filtro_id AND tipo='planificacion' ORDER BY fecha_subida DESC"); if ($res_archs) { while ($r = $res_archs->fetch_assoc()) { $archivos_ciclo[] = $r; } } }

$lista_archivos_docentes = [];
$res_docs = $conn->query("SELECT ai.id, ai.nombre_archivo, ai.fecha_subida, c.nombre as nombre_ciclo FROM archivos_importados ai LEFT JOIN ciclos c ON ai.ciclo_id = c.id WHERE ai.tipo = 'docentes' ORDER BY ai.fecha_subida DESC");
if ($res_docs) { while($r = $res_docs->fetch_assoc()){ $lista_archivos_docentes[] = $r; } }

$areas_unicas = [];
$res_areas = $conn->query("SELECT DISTINCT nombre_area FROM areas_materias ORDER BY nombre_area ASC");
if ($res_areas) { while($r = $res_areas->fetch_assoc()){ $areas_unicas[] = $r['nombre_area']; } }

$lista_archivos_areas = [];
$res_arch_areas = $conn->query("SELECT id, nombre_archivo, total_registros, fecha_subida FROM archivos_areas ORDER BY fecha_subida DESC");
if ($res_arch_areas) { while($r = $res_arch_areas->fetch_assoc()){ $lista_archivos_areas[] = $r; } }

$cursos = $conn->query("SELECT co.id AS id_oferta, co.ciclo_id, ci.nombre AS ciclo, s.numero AS semestre, m.nombre AS materia, p.nombre AS paralelo, ca.nombre AS carrera, f.nombre AS facultad FROM cursos_oferta co JOIN paralelos p ON p.id = co.paralelo_id JOIN materias m ON m.id = p.materia_id JOIN semestres s ON s.id = m.semestre_id JOIN carreras ca ON ca.id = s.carrera_id JOIN facultades f ON f.id = ca.facultad_id JOIN ciclos ci ON ci.id = co.ciclo_id ORDER BY ci.nombre DESC, ca.nombre ASC, s.numero ASC, m.nombre ASC, p.nombre ASC");

$lista_facultades = $conn->query("SELECT nombre FROM facultades ORDER BY nombre ASC");
$lista_restricciones = $conn->query("SELECT r.id, ma.nombre as materia_a, mb.nombre as materia_b FROM restricciones_materias r JOIN materias ma ON r.materia_a_id = ma.id JOIN materias mb ON r.materia_b_id = mb.id ORDER BY ma.nombre ASC, mb.nombre ASC");
$lista_materias_global = $conn->query("SELECT DISTINCT nombre FROM materias ORDER BY nombre ASC");
$arr_materias = []; if($lista_materias_global) { while($r=$lista_materias_global->fetch_assoc()) $arr_materias[] = $r['nombre']; }

$seccion = $_GET["seccion"] ?? "cursos";
$import_msg = null;
if (isset($_GET['import']) && isset($_SESSION['import_preview'])) { $import_msg = $_SESSION['import_preview']['msg'] ?? null; }

// =======================
// DATOS PARA SECCION NOTAS
// =======================
$notas_combo_map = [];
$notas_cursos_lookup = [];
$notas_seleccion = [
    'ciclo' => isset($_GET['n_ciclo']) ? trim($_GET['n_ciclo']) : '',
    'facultad' => isset($_GET['n_facultad']) ? trim($_GET['n_facultad']) : '',
    'carrera' => isset($_GET['n_carrera']) ? trim($_GET['n_carrera']) : '',
    'semestre' => isset($_GET['n_semestre']) ? trim($_GET['n_semestre']) : '',
    'materia' => isset($_GET['n_materia']) ? trim($_GET['n_materia']) : '',
    'paralelo' => isset($_GET['n_paralelo']) ? trim($_GET['n_paralelo']) : ''
];
$notas_curso_id = 0;
$notas_curso_info = null;
$notas_docentes_txt = '';
$notas_examenes_p1 = [];
$notas_examenes_p2 = [];
$notas_estudiantes = [];
$notas_resultados_map = [];

if ($seccion === 'notas') {
    $sql_notas_cursos = "SELECT co.id AS curso_id, ci.id AS ciclo_id, s.numero AS semestre, m.nombre AS materia, p.nombre AS paralelo, ci.nombre AS ciclo, ca.nombre AS carrera, f.nombre AS facultad
        FROM cursos_oferta co
        JOIN paralelos p ON p.id = co.paralelo_id
        JOIN materias m ON m.id = p.materia_id
        JOIN semestres s ON s.id = m.semestre_id
        JOIN carreras ca ON ca.id = s.carrera_id
        JOIN facultades f ON f.id = ca.facultad_id
        JOIN ciclos ci ON ci.id = co.ciclo_id
        ORDER BY s.numero ASC, m.nombre ASC, p.nombre ASC, co.ciclo_id DESC";
    $res_notas_cursos = $conn->query($sql_notas_cursos);
    if ($res_notas_cursos) {
        while ($row = $res_notas_cursos->fetch_assoc()) {
            $ciclo_id = (string)$row['ciclo_id'];
            $fac = $row['facultad'];
            $car = $row['carrera'];
            $sem = (string)$row['semestre'];
            $mat = $row['materia'];
            $par = $row['paralelo'];

            if (!isset($notas_combo_map[$fac])) { $notas_combo_map[$fac] = []; }
            if (!isset($notas_combo_map[$fac][$car])) { $notas_combo_map[$fac][$car] = []; }
            if (!isset($notas_combo_map[$fac][$car][$ciclo_id])) { $notas_combo_map[$fac][$car][$ciclo_id] = ['nombre' => $row['ciclo'], 'semestres' => []]; }
            if (!isset($notas_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem])) { $notas_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem] = []; }
            if (!isset($notas_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem][$mat])) { $notas_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem][$mat] = []; }
            if (!in_array($par, $notas_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem][$mat], true)) { $notas_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem][$mat][] = $par; }

            if (!isset($notas_cursos_lookup[$fac][$car][$ciclo_id][$sem][$mat][$par])) {
                $notas_cursos_lookup[$fac][$car][$ciclo_id][$sem][$mat][$par] = [
                    'curso_id' => (int)$row['curso_id'],
                    'ciclo_id' => (int)$row['ciclo_id'],
                    'ciclo' => $row['ciclo'],
                    'carrera' => $row['carrera'],
                    'facultad' => $row['facultad'],
                    'semestre' => (int)$row['semestre'],
                    'materia' => $row['materia'],
                    'paralelo' => $row['paralelo']
                ];
            }
        }
    }
    $sel_ciclo = $notas_seleccion['ciclo'];
    $sel_fac = $notas_seleccion['facultad'];
    $sel_car = $notas_seleccion['carrera'];
    $sel_sem = $notas_seleccion['semestre'];
    $sel_mat = $notas_seleccion['materia'];
    $sel_par = $notas_seleccion['paralelo'];
    if ($sel_ciclo !== '' && $sel_fac !== '' && $sel_car !== '' && $sel_sem !== '' && $sel_mat !== '' && $sel_par !== '' && isset($notas_cursos_lookup[$sel_fac][$sel_car][$sel_ciclo][$sel_sem][$sel_mat][$sel_par])) {
        $notas_curso_info = $notas_cursos_lookup[$sel_fac][$sel_car][$sel_ciclo][$sel_sem][$sel_mat][$sel_par];
        $notas_curso_id = (int)$notas_curso_info['curso_id'];
    }

    if ($notas_curso_id > 0) {
        $docentes = [];
        $res_doc = $conn->query("SELECT u.nombres FROM usuario_cursos uc JOIN usuarios u ON uc.usuario_id = u.id WHERE uc.curso_id = $notas_curso_id AND u.rol IN ('docente','director_carrera','director_area') ORDER BY TRIM(SUBSTRING_INDEX(u.nombres, ' ', 1)) ASC, u.nombres ASC");
        if ($res_doc) { while ($r = $res_doc->fetch_assoc()) { $docentes[] = $r['nombres']; } }
        if (!empty($docentes)) { $notas_docentes_txt = implode(', ', $docentes); }

        $examen_ids = [];
        $res_ex = $conn->query("SELECT id, titulo, parcial FROM examenes WHERE curso_id = $notas_curso_id AND estado != 'eliminado' ORDER BY created_at ASC, id ASC");
        if ($res_ex) {
            while ($r = $res_ex->fetch_assoc()) {
                $examen_ids[] = (int)$r['id'];
                if ($r['parcial'] === 'segundo_parcial') { $notas_examenes_p2[] = $r; }
                else { $notas_examenes_p1[] = $r; }
            }
        }

        if (!empty($examen_ids)) {
            $ids = implode(',', $examen_ids);
            $res_res = $conn->query("SELECT examen_id, estudiante_id, puntaje_total FROM resultados_examen WHERE examen_id IN ($ids)");
            if ($res_res) {
                while ($r = $res_res->fetch_assoc()) {
                    $eid = (int)$r['examen_id'];
                    $sid = (int)$r['estudiante_id'];
                    $notas_resultados_map[$sid][$eid] = $r['puntaje_total'];
                }
            }
        }

        $res_est = $conn->query("SELECT u.id AS estudiante_id, u.nombres, u.cedula FROM usuarios u JOIN usuario_cursos uc ON u.id = uc.usuario_id WHERE uc.curso_id = $notas_curso_id AND u.rol NOT IN ('administrador','docente','director_carrera','director_area') ORDER BY TRIM(SUBSTRING_INDEX(u.nombres, ' ', 1)) ASC, u.nombres ASC");
        if ($res_est) { while ($r = $res_est->fetch_assoc()) { $notas_estudiantes[] = $r; } }
    }
}

// =======================
// DOCENTES CON EVALUACIONES
// =======================
$doc_eval_ciclos = [];
$doc_eval_combo_map = [];
$doc_eval_cursos_by_filter = [];
$doc_eval_seleccion = [
    'ciclo' => isset($_GET['de_ciclo']) ? trim($_GET['de_ciclo']) : '',
    'facultad' => isset($_GET['de_facultad']) ? trim($_GET['de_facultad']) : '',
    'carrera' => isset($_GET['de_carrera']) ? trim($_GET['de_carrera']) : '',
    'semestre' => isset($_GET['de_semestre']) ? trim($_GET['de_semestre']) : '',
    'materia' => isset($_GET['de_materia']) ? trim($_GET['de_materia']) : ''
];
$doc_eval_resultados = [];

if ($seccion === 'docentes_evaluaciones') {
    $sql_doc_eval = "SELECT co.id AS curso_id, ci.id AS ciclo_id, ci.nombre AS ciclo, f.nombre AS facultad, ca.nombre AS carrera, s.numero AS semestre, m.nombre AS materia, p.nombre AS paralelo
        FROM cursos_oferta co
        JOIN paralelos p ON p.id = co.paralelo_id
        JOIN materias m ON m.id = p.materia_id
        JOIN semestres s ON s.id = m.semestre_id
        JOIN carreras ca ON ca.id = s.carrera_id
        JOIN facultades f ON f.id = ca.facultad_id
        JOIN ciclos ci ON ci.id = co.ciclo_id
        ORDER BY ci.nombre DESC, f.nombre ASC, ca.nombre ASC, s.numero ASC, m.nombre ASC, p.nombre ASC";
    $res_doc_eval = $conn->query($sql_doc_eval);
    if ($res_doc_eval) {
        while ($row = $res_doc_eval->fetch_assoc()) {
            $ciclo_id = (string)$row['ciclo_id'];
            $fac = $row['facultad'];
            $car = $row['carrera'];
            $sem = (string)$row['semestre'];
            $mat = $row['materia'];

            if (!isset($doc_eval_ciclos[$ciclo_id])) { $doc_eval_ciclos[$ciclo_id] = $row['ciclo']; }
            if (!isset($doc_eval_combo_map[$fac])) { $doc_eval_combo_map[$fac] = []; }
            if (!isset($doc_eval_combo_map[$fac][$car])) { $doc_eval_combo_map[$fac][$car] = []; }
            if (!isset($doc_eval_combo_map[$fac][$car][$ciclo_id])) { $doc_eval_combo_map[$fac][$car][$ciclo_id] = ['nombre' => $row['ciclo'], 'semestres' => []]; }
            if (!isset($doc_eval_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem])) { $doc_eval_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem] = []; }
            if (!in_array($mat, $doc_eval_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem], true)) { $doc_eval_combo_map[$fac][$car][$ciclo_id]['semestres'][$sem][] = $mat; }

            if (!isset($doc_eval_cursos_by_filter[$fac][$car][$ciclo_id][$sem][$mat])) { $doc_eval_cursos_by_filter[$fac][$car][$ciclo_id][$sem][$mat] = []; }
            $doc_eval_cursos_by_filter[$fac][$car][$ciclo_id][$sem][$mat][] = [
                'curso_id' => (int)$row['curso_id'],
                'ciclo_id' => (int)$row['ciclo_id'],
                'ciclo' => $row['ciclo'],
                'facultad' => $row['facultad'],
                'carrera' => $row['carrera'],
                'semestre' => (int)$row['semestre'],
                'materia' => $row['materia'],
                'paralelo' => $row['paralelo']
            ];
        }
    }
    if (!empty($doc_eval_ciclos)) { krsort($doc_eval_ciclos, SORT_NUMERIC); }

    $sel_ciclo = $doc_eval_seleccion['ciclo'];
    $sel_fac = $doc_eval_seleccion['facultad'];
    $sel_car = $doc_eval_seleccion['carrera'];
    $sel_sem = $doc_eval_seleccion['semestre'];
    $sel_mat = $doc_eval_seleccion['materia'];

    if ($sel_ciclo !== '' && $sel_fac !== '' && $sel_car !== '' && $sel_sem !== '' && $sel_mat !== '') {
        $doc_eval_cursos = $doc_eval_cursos_by_filter[$sel_fac][$sel_car][$sel_ciclo][$sel_sem][$sel_mat] ?? [];
        if (!empty($doc_eval_cursos)) {
            $curso_ids = array_unique(array_column($doc_eval_cursos, 'curso_id'));
            $ids_sql = implode(',', array_map('intval', $curso_ids));

            $docentes_por_curso = [];
            $res_docentes = $conn->query("SELECT uc.curso_id, u.id AS docente_id, u.nombres, u.rol
                FROM usuario_cursos uc
                JOIN usuarios u ON uc.usuario_id = u.id
                WHERE uc.curso_id IN ($ids_sql) AND u.rol IN ('docente','director_carrera','director_area')
                ORDER BY TRIM(SUBSTRING_INDEX(u.nombres, ' ', 1)) ASC, u.nombres ASC");
            if ($res_docentes) {
                while ($r = $res_docentes->fetch_assoc()) {
                    $cid = (int)$r['curso_id'];
                    $docentes_por_curso[$cid][] = $r;
                }
            }

            $estudiantes_por_curso = [];
            $res_est = $conn->query("SELECT uc.curso_id, u.id AS estudiante_id, u.nombres, u.cedula
                FROM usuario_cursos uc
                JOIN usuarios u ON uc.usuario_id = u.id
                WHERE uc.curso_id IN ($ids_sql) AND u.rol NOT IN ('administrador','docente','director_carrera','director_area')
                ORDER BY TRIM(SUBSTRING_INDEX(u.nombres, ' ', 1)) ASC, u.nombres ASC");
            if ($res_est) {
                while ($r = $res_est->fetch_assoc()) {
                    $cid = (int)$r['curso_id'];
                    $estudiantes_por_curso[$cid][] = $r;
                }
            }

            $evaluados_por_curso = [];
            $res_eval = $conn->query("SELECT DISTINCT e.curso_id, re.estudiante_id
                FROM resultados_examen re
                JOIN examenes e ON re.examen_id = e.id
                WHERE e.curso_id IN ($ids_sql) AND re.puntaje_total != -1");
            if ($res_eval) {
                while ($r = $res_eval->fetch_assoc()) {
                    $cid = (int)$r['curso_id'];
                    $sid = (int)$r['estudiante_id'];
                    $evaluados_por_curso[$cid][$sid] = true;
                }
            }

            foreach ($doc_eval_cursos as $curso) {
                $cid = (int)$curso['curso_id'];
                $estudiantes = $estudiantes_por_curso[$cid] ?? [];
                $evaluados_map = $evaluados_por_curso[$cid] ?? [];

                $evaluados = [];
                $no_evaluados = [];
                foreach ($estudiantes as $est) {
                    $sid = (int)$est['estudiante_id'];
                    $est_info = ['nombres' => $est['nombres'], 'cedula' => $est['cedula']];
                    if (isset($evaluados_map[$sid])) { $evaluados[] = $est_info; }
                    else { $no_evaluados[] = $est_info; }
                }

                $docentes = $docentes_por_curso[$cid] ?? [];
                if (empty($docentes)) {
                    $doc_eval_resultados[] = [
                        'curso' => $curso,
                        'docente' => null,
                        'total_estudiantes' => count($estudiantes),
                        'evaluados' => $evaluados,
                        'no_evaluados' => $no_evaluados
                    ];
                    continue;
                }

                foreach ($docentes as $doc) {
                    $doc_eval_resultados[] = [
                        'curso' => $curso,
                        'docente' => $doc,
                        'total_estudiantes' => count($estudiantes),
                        'evaluados' => $evaluados,
                        'no_evaluados' => $no_evaluados
                    ];
                }
            }
        }
    }
}
?>
