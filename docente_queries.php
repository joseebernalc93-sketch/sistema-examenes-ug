<?php
// ==========================================
// 2. PREPARACIÓN DE DATOS (QUERIES)
// ==========================================

$mis_cursos = [];
$examenes_por_curso    = [];
$estudiantes_por_curso = [];
$intentos_por_examen   = [];
$notificaciones_seguridad_por_curso = [];
$estadisticas_cursos   = [];
$datos_carrera         = [];
$datos_area            = [];
$mis_areas             = [];
$aplicacion_actual     = null;

// 1. OBTENER APLICACIÓN ACTIVA
$res_app = $conn->query("SELECT * FROM aplicaciones_docente WHERE docente_id = $usuario_id AND activo = 1 ORDER BY fecha_subida DESC LIMIT 1");
if ($res_app && $res_app->num_rows > 0) $aplicacion_actual = $res_app->fetch_assoc();

if ($es_docente) {
    // 2. OBTENER CURSOS
    $res = $conn->query("SELECT co.id AS id_oferta, m.nombre AS materia, p.nombre AS paralelo, ci.nombre AS ciclo FROM usuario_cursos uc JOIN cursos_oferta co ON uc.curso_id = co.id JOIN paralelos p ON co.paralelo_id = p.id JOIN materias m ON p.materia_id = m.id JOIN ciclos ci ON co.ciclo_id = ci.id WHERE uc.usuario_id=$usuario_id ORDER BY ci.nombre DESC, m.nombre ASC, p.nombre ASC, co.id ASC");
    if($res) $mis_cursos = $res->fetch_all(MYSQLI_ASSOC);

    if(!empty($mis_cursos)){
        $ids = implode(',', array_column($mis_cursos, 'id_oferta'));
        if (!empty($ids)) {
            // 3. EXÁMENES
            $res_ex = $conn->query("SELECT e.*, m.nombre AS materia, p.nombre AS paralelo FROM examenes e JOIN cursos_oferta co ON e.curso_id = co.id JOIN paralelos p ON co.paralelo_id = p.id JOIN materias m ON p.materia_id = m.id WHERE e.curso_id IN ($ids) AND e.estado != 'eliminado' ORDER BY e.created_at DESC");
            if($res_ex) while($r = $res_ex->fetch_assoc()) $examenes_por_curso[$r['curso_id']][] = $r;

            // 4. ESTUDIANTES
            $sql_est = "SELECT u.id AS estudiante_id, u.nombres, u.cedula, u.rol, uc.curso_id FROM usuarios u JOIN usuario_cursos uc ON u.id=uc.usuario_id WHERE uc.curso_id IN ($ids) AND u.rol NOT IN ('administrador', 'docente', 'director_carrera', 'director_area') ORDER BY u.nombres";
            $res_est = $conn->query($sql_est);
            if($res_est) while($r = $res_est->fetch_assoc()) $estudiantes_por_curso[$r['curso_id']][] = $r;

            // 5. INTENTOS/NOTAS
            $sql_int = "SELECT re.id as resultado_id, re.examen_id, re.estudiante_id, re.puntaje_total, re.fecha_inicio_real, re.fecha_intento, u.nombres, u.nombres AS estudiante_nombre, u.cedula FROM resultados_examen re JOIN usuarios u ON re.estudiante_id = u.id JOIN examenes e ON re.examen_id = e.id WHERE e.curso_id IN ($ids) ORDER BY re.examen_id ASC, TRIM(SUBSTRING_INDEX(u.nombres, ' ', 1)) ASC, u.nombres ASC";
            $res_int = $conn->query($sql_int);
            if ($res_int) {
                while($row = $res_int->fetch_assoc()) {
                    $inicio = !empty($row['fecha_inicio_real']) ? new DateTime($row['fecha_inicio_real']) : null;
                    $fin    = !empty($row['fecha_intento']) ? new DateTime($row['fecha_intento']) : null;
                    $duracion_str = "---";
                    if ($inicio && $fin && $row['puntaje_total'] != -1) {
                        $diff = $inicio->diff($fin); $duracion_str = sprintf("%02dh %02dm %02ds", $diff->h + ($diff->days * 24), $diff->i, $diff->s);
                    } elseif ($row['puntaje_total'] == -1) { $duracion_str = "En progreso"; }
                    $row['hora_inicio'] = $inicio ? $inicio->format('H:i:s') : '--';
                    $row['hora_fin']    = $fin ? $fin->format('H:i:s') : '--';
                    $row['tiempo_total']= $duracion_str;
                    $intentos_por_examen[$row['examen_id']][] = $row;
                }
            }

            // 5.1 NOTIFICACIONES DE SEGURIDAD
            $sql_notif = "SELECT ns.id, ns.curso_id, ns.examen_id, ns.estudiante_id, ns.tipo, ns.motivo, ns.fecha_creacion,
                                 u.nombres AS estudiante_nombre, u.cedula AS estudiante_cedula,
                                 e.titulo AS examen_titulo
                          FROM notificaciones_seguridad_examen ns
                          LEFT JOIN usuarios u ON ns.estudiante_id = u.id
                          LEFT JOIN examenes e ON ns.examen_id = e.id
                          WHERE ns.curso_id IN ($ids)
                          AND ns.docente_id = $usuario_id
                          AND IFNULL(ns.oculto_docente, 0) = 0
                          ORDER BY ns.fecha_creacion DESC
                          LIMIT 500";
            $res_notif = $conn->query($sql_notif);
            if ($res_notif) {
                while ($row = $res_notif->fetch_assoc()) {
                    $curso_notif = (int)($row['curso_id'] ?? 0);
                    if (!isset($notificaciones_seguridad_por_curso[$curso_notif])) {
                        $notificaciones_seguridad_por_curso[$curso_notif] = [];
                    }
                    $notificaciones_seguridad_por_curso[$curso_notif][] = $row;
                }
            }
        }

        // 6. ESTADÍSTICAS POR CURSO
        foreach ($mis_cursos as $curso) {
            $cid = $curso['id_oferta'];
            $tot = $conn->query("SELECT COUNT(*) as t FROM usuario_cursos uc JOIN usuarios u ON uc.usuario_id=u.id WHERE uc.curso_id=$cid AND u.rol NOT IN ('administrador','docente','director_carrera','director_area')")->fetch_assoc()['t'];
            $p1 = $conn->query("SELECT AVG(re.puntaje_total) as p FROM resultados_examen re JOIN examenes e ON re.examen_id=e.id WHERE e.curso_id=$cid AND (e.parcial='primer_parcial' OR e.parcial IS NULL) AND re.puntaje_total!=-1")->fetch_assoc()['p'];
            $p2 = $conn->query("SELECT AVG(re.puntaje_total) as p FROM resultados_examen re JOIN examenes e ON re.examen_id=e.id WHERE e.curso_id=$cid AND e.parcial='segundo_parcial' AND re.puntaje_total!=-1")->fetch_assoc()['p'];
            $estadisticas_cursos[$cid] = ['total_estudiantes' => $tot, 'promedio_p1' => $p1 ? round($p1,2) : null, 'promedio_p2' => $p2 ? round($p2,2) : null];
        }
    }
}

// 7. DATOS PARA DIRECTOR DE CARRERA
if ($es_director_carrera) {
    $car = esc($_SESSION["usuario"]["carrera"] ?? '');
    if($car) {
        $sql = "SELECT u.id, u.nombres, u.email, u.rol, u.cedula, u.area, m.nombre AS materia, p.nombre AS paralelo, co.id AS curso_id, (SELECT COUNT(*) FROM usuario_cursos uc2 JOIN usuarios u2 ON uc2.usuario_id=u2.id WHERE uc2.curso_id=co.id AND u2.rol='estudiante') AS total_est, (SELECT COUNT(DISTINCT re.estudiante_id) FROM resultados_examen re JOIN examenes e ON re.examen_id=e.id WHERE e.curso_id=co.id AND re.puntaje_total!=-1) AS total_eval FROM usuarios u JOIN usuario_cursos uc ON u.id=uc.usuario_id JOIN cursos_oferta co ON uc.curso_id=co.id JOIN paralelos p ON co.paralelo_id=p.id JOIN materias m ON p.materia_id=m.id JOIN semestres s ON m.semestre_id=s.id JOIN carreras ca ON s.carrera_id=ca.id WHERE u.rol IN ('docente','director_area') AND ca.nombre='$car' ORDER BY u.nombres";
        $res = $conn->query($sql);
        if($res) while($r=$res->fetch_assoc()) {
            $datos_carrera[$r['id']]['nombres']=$r['nombres']; $datos_carrera[$r['id']]['rol']=$r['rol']; $datos_carrera[$r['id']]['email']=$r['email']; $datos_carrera[$r['id']]['cedula']=$r['cedula']; $datos_carrera[$r['id']]['area']=$r['area']??'';
            $datos_carrera[$r['id']]['cursos'][]=['materia'=>$r['materia'],'paralelo'=>$r['paralelo'],'num_estudiantes'=>$r['total_est'],'evaluados'=>$r['total_eval']];
        }
    }
}

// 8. DATOS PARA DIRECTOR DE ÁREA
if ($es_director_area) {
    $mis_areas = obtener_area_cargo_director($usuario_id);
    if (!empty($mis_areas)) {
        $mats_area = []; foreach ($mis_areas as $a) { $ae = esc($a); $rq = $conn->query("SELECT nombre_materia FROM areas_materias WHERE nombre_area='$ae'"); while($rm=$rq->fetch_assoc()) $mats_area[] = "UPPER(m.nombre) = '" . esc($rm['nombre_materia']) . "'"; }
        if (!empty($mats_area)) {
            $where_m = implode(' OR ', $mats_area);
            $sql = "SELECT u.id, u.nombres, u.email, u.rol, u.cedula, m.nombre AS materia, p.nombre AS paralelo, co.id AS curso_id, (SELECT COUNT(*) FROM usuario_cursos uc2 JOIN usuarios u2 ON uc2.usuario_id=u2.id WHERE uc2.curso_id=co.id AND u2.rol='estudiante') AS total_est, (SELECT COUNT(DISTINCT re.estudiante_id) FROM resultados_examen re JOIN examenes e ON re.examen_id=e.id WHERE e.curso_id=co.id AND re.puntaje_total!=-1) AS total_eval FROM usuarios u JOIN usuario_cursos uc ON u.id=uc.usuario_id JOIN cursos_oferta co ON uc.curso_id=co.id JOIN paralelos p ON co.paralelo_id=p.id JOIN materias m ON p.materia_id=m.id WHERE u.rol IN ('docente','director_area') AND u.id != $usuario_id AND ($where_m) ORDER BY u.nombres";
            $res = $conn->query($sql);
            if($res) while($r=$res->fetch_assoc()) {
                $am = obtener_area_materia($r['materia']);
                if ($am && in_array($am, $mis_areas)) {
                    $datos_area[$r['id']]['nombres']=$r['nombres']; $datos_area[$r['id']]['rol']=$r['rol']; $datos_area[$r['id']]['email']=$r['email']; $datos_area[$r['id']]['cedula']=$r['cedula'];
                    $datos_area[$r['id']]['cursos'][]=['materia'=>$r['materia'],'paralelo'=>$r['paralelo'],'num_estudiantes'=>$r['total_est'],'evaluados'=>$r['total_eval'],'area'=>$am];
                }
            }
        }
    }
}
?>
