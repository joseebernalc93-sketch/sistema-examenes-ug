<?php
session_start();
include "db.php";

// 0. CONFIGURACIÓN REGIONAL
date_default_timezone_set('America/Guayaquil');
setlocale(LC_TIME, 'es_ES.UTF-8', 'esp');

// 1. SEGURIDAD
if (!isset($_SESSION["usuario"]) || $_SESSION["usuario"]["rol"] != "estudiante") {
    die("Acceso denegado. <a href='index.php'>Volver</a>");
}

$est_id = (int)$_SESSION["usuario"]["id"];
$examen_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 2. CONSULTA DE DATOS
$sql = "
    SELECT 
        e.id, e.titulo, e.permitir_revision, e.mostrar_nota, e.duracion_minutos,
        re.respuestas_json, re.puntaje_total, re.distribucion_puntajes_json,
        re.fecha_inicio_real,
        re.fecha_intento,  
        re.orden_preguntas
    FROM examenes e 
    JOIN resultados_examen re ON e.id = re.examen_id 
    WHERE e.id = $examen_id AND re.estudiante_id = $est_id
    LIMIT 1
";

$res = $conn->query($sql);

if (!$res || $res->num_rows == 0) {
    die("<div style='text-align:center;padding:50px;font-family:sans-serif;'>⚠️ No se encontró ningún intento registrado.</div>");
}

$examen = $res->fetch_assoc();

// 3. VERIFICAR SI EL EXAMEN AÚN ESTÁ EN PROGRESO
if ($examen['puntaje_total'] == -1) {
    // Si no ha terminado, redirigir a tomarlo (o mostrar error)
    header("Location: tomar_examen.php?id=$examen_id");
    exit;
}

// 4. VERIFICAR PERMISO DE REVISIÓN (CRÍTICO)
// Si el docente deshabilitó la revisión, bloqueamos el acceso aquí.
if ($examen['permitir_revision'] == 0) {
    die("<!DOCTYPE html><html><head><link rel='icon' href='favicon.ico'><link rel='icon' type='image/png' sizes='32x32' href='favicon-32.png'><link rel='apple-touch-icon' sizes='180x180' href='apple-touch-icon.png'><title>Acceso Restringido</title></head><body style='text-align:center;padding:50px;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;background:#f1f5f9;'><div style='background:white;padding:30px;border-radius:10px;box-shadow:0 4px 6px rgba(0,0,0,0.1);max-width:500px;margin:0 auto;'><h2 style='color:#991b1b;'>🔒 Revisión no disponible</h2><p style='color:#374151;'>El docente ha configurado este examen para que no muestre la revisión detallada.</p><button onclick='window.close()' style='padding:10px 20px;background:#333;color:white;border:none;border-radius:5px;cursor:pointer;font-weight:bold;'>Cerrar ventana</button></div></body></html>");
}

// 5. HELPERS DE FORMATO
function fechaEs($fechaSql) {
    if (!$fechaSql) return " - ";
    $ts = strtotime($fechaSql);
    $dias = ["Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado"];
    $meses = ["","Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
    
    $diaSemana = $dias[date('w', $ts)];
    $dia = date('j', $ts);
    $mes = $meses[date('n', $ts)];
    $anio = date('Y', $ts);
    $hora = date('H:i:s', $ts);
    
    return "$diaSemana, $dia de $mes de $anio, $hora";
}

// 6. CÁLCULOS DE TIEMPO
$inicio_str = $examen['fecha_inicio_real'];
$fin_str = $examen['fecha_intento'];
$inicio_fmt = fechaEs($inicio_str);
$fin_fmt = fechaEs($fin_str);
$duracion_str = "No calculado";

if ($inicio_str && $fin_str) {
    try {
        $dt_inicio = new DateTime($inicio_str);
        $dt_fin = new DateTime($fin_str);
        $intervalo = $dt_inicio->diff($dt_fin);
        
        $partes = [];
        $horas_totales = $intervalo->h + ($intervalo->days * 24);
        
        if ($horas_totales > 0) $partes[] = $horas_totales . ($horas_totales == 1 ? " hora" : " horas");
        if ($intervalo->i > 0) $partes[] = $intervalo->i . ($intervalo->i == 1 ? " minuto" : " minutos");
        if ($intervalo->s > 0 || empty($partes)) $partes[] = $intervalo->s . ($intervalo->s == 1 ? " segundo" : " segundos");
        
        $duracion_str = implode(" ", $partes);
    } catch (Exception $e) { $duracion_str = "--"; }
}

// 7. CARGAR PREGUNTAS Y OPCIONES
$respuestas_alumno = json_decode($examen['respuestas_json'], true) ?? [];
$distribucion_puntajes = [];
if (!empty($examen['distribucion_puntajes_json'])) {
    $dist_tmp = json_decode($examen['distribucion_puntajes_json'], true);
    if (is_array($dist_tmp)) { $distribucion_puntajes = $dist_tmp; }
}
$ids_str = !empty($examen['orden_preguntas']) ? $examen['orden_preguntas'] : (empty($respuestas_alumno) ? "0" : implode(',', array_keys($respuestas_alumno)));

// Preguntas
$preguntas = $conn->query("SELECT * FROM preguntas WHERE id IN ($ids_str) ORDER BY FIELD(id, $ids_str)")->fetch_all(MYSQLI_ASSOC);

// Opciones
$opciones = [];
$res_op = $conn->query("SELECT op.* FROM opciones_pregunta op WHERE op.pregunta_id IN ($ids_str) ORDER BY op.id ASC");
while($r = $res_op->fetch_assoc()) { $opciones[$r['pregunta_id']][] = $r; }

// LOGICA MOSTRAR NOTA: Si mostrar_nota es 0, no mostramos correcciones
$mostrar_correcciones = ($examen['mostrar_nota'] == 1); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Revisión: <?= htmlspecialchars($examen['titulo']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
<style>
    :root {
        --primary: #0f6cb6;
        --success-bg: #dcfce7; 
        --success-text: #166534;
        --error-bg: #fee2e2; 
        --error-text: #991b1b;
        --info-box-bg: #f8fafc;
        --border-color: #e2e8f0;
    }
    body {
        font-family: 'Inter', -apple-system, sans-serif;
        background-color: #f1f5f9;
        color: #334155;
        margin: 0;
        padding: 20px;
    }
    .container {
        max-width: 900px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }
    .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 0.95rem; border-radius: 8px; overflow: hidden; border: 1px solid var(--border-color); }
    .summary-table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); }
    .label-col { text-align: right; font-weight: 600; width: 200px; background-color: #f8fafc; color: #64748b; }
    .value-col { font-weight: 500; color: #0f172a; }
    .question-container { display: flex; margin-bottom: 25px; gap: 15px; }
    .q-info-box { width: 110px; background: var(--info-box-bg); border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; font-size: 0.85rem; color: #64748b; flex-shrink: 0; height: fit-content; }
    .q-number { color: var(--primary); font-weight: 700; font-size: 1.1rem; display: block; margin-bottom: 6px; }
    .q-state { display: block; margin-bottom: 8px; font-weight: 600; padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; text-align: center; }
    .state-correct { background: var(--success-bg); color: var(--success-text); }
    .state-incorrect { background: var(--error-bg); color: var(--error-text); }
    .state-neutral { background: #e2e8f0; color: #475569; }
    .q-mark { display: block; font-size: 0.8rem; border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 8px; }
    .q-content-box { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; }
    .q-text { font-size: 1rem; margin-bottom: 20px; font-weight: 500; color: #1e293b; line-height: 1.5; }
    .q-image { max-width: 100%; border: 1px solid #e2e8f0; margin: 10px 0 20px 0; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
    .option-row { margin-bottom: 10px; padding: 8px 10px; display: flex; align-items: baseline; border-radius: 6px; transition: background 0.2s; }
    .option-radio input { margin-right: 12px; transform: scale(1.2); }
    .option-text { flex: 1; line-height: 1.5; }
    .icon-check { color: var(--success-text); font-weight: bold; font-size: 1.2rem; margin-left: 8px; }
    .icon-cross { color: var(--error-text); font-weight: bold; font-size: 1.2rem; margin-left: 8px; }
    .feedback-box { margin-top: 20px; padding: 15px; border-radius: 6px; font-size: 0.95rem; border: 1px solid transparent; }
    .feedback-box.correct { background-color: var(--success-bg); color: var(--success-text); border-color: #bbf7d0; }
    .feedback-box.incorrect { background-color: var(--error-bg); color: var(--error-text); border-color: #fecaca; }
    .btn-close { display: block; width: 200px; margin: 40px auto 0; padding: 12px; background: var(--primary); color: white; text-align: center; border: none; border-radius: 6px; cursor: pointer; font-size: 1rem; font-weight: 600; transition: background 0.2s; }
    .btn-close:hover { background: #1e40af; }
</style>
</head>
<body>

<div class="container">
    <h2 style="color:#1e293b; margin-top:0; border-bottom:2px solid var(--border-color); padding-bottom:15px;">
        <?= htmlspecialchars($examen['titulo']) ?>
    </h2>
    
    <table class="summary-table">
        <tr>
            <td class="label-col">Estado</td>
            <td class="value-col"><span style="color:var(--success-text); font-weight:bold;">Finalizado</span></td>
        </tr>
        <tr>
            <td class="label-col">Comenzado el</td>
            <td class="value-col"><?= $inicio_fmt ?></td>
        </tr>
        <tr>
            <td class="label-col">Completado el</td>
            <td class="value-col"><?= $fin_fmt ?></td>
        </tr>
        <tr>
            <td class="label-col">Tiempo empleado</td>
            <td class="value-col" style="font-weight:bold; color:var(--primary);"><?= $duracion_str ?></td>
        </tr>
        
        <tr>
            <td class="label-col">Calificación</td>
            <td class="value-col">
                <?php if($mostrar_correcciones): ?>
                    <span style="font-size:1.1rem; font-weight:bold;"><?= str_replace('.', ',', $examen['puntaje_total']) ?></span> de 10,00 
                    <span style="color:#64748b; font-size:0.9rem;">(<?= number_format((float)$examen['puntaje_total'] * 10, 0) ?>%)</span>
                <?php else: ?>
                    <span style="color:#64748b; font-style:italic;">🔒 Oculta por el docente</span>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- LISTADO DE PREGUNTAS -->
    <?php 
    $contador = 1;
    if($preguntas):
    foreach($preguntas as $p): 
        $pid = $p['id'];
        $resp_alumno = isset($respuestas_alumno[$pid]) ? $respuestas_alumno[$pid] : null;
        
        $es_correcta_pregunta = false;
        $opcion_correcta_texto = "";
        $puntaje_asignado = null;
        $puntaje_obtenido = 0;
        
        if(isset($opciones[$pid])) {
            foreach($opciones[$pid] as $opt) {
                if($opt['es_correcta']) {
                    $opcion_correcta_texto = $opt['contenido'];
                    if($opt['id'] == $resp_alumno) {
                        $es_correcta_pregunta = true;
                    }
                }
            }
        }

        // Si no se muestran correcciones, no decimos si es Correcta/Incorrecta
        if ($mostrar_correcciones) {
            $texto_estado = $es_correcta_pregunta ? "Correcta" : "Incorrecta";
            $clase_estado = $es_correcta_pregunta ? "state-correct" : "state-incorrect";
            $puntaje_txt  = "Puntúa " . ($es_correcta_pregunta ? number_format($p['valor_puntos'],2) : '0,00') . " sobre " . number_format($p['valor_puntos'],2);
        } else {
            $texto_estado = "Respondida";
            $clase_estado = "state-neutral";
            $puntaje_txt  = "Puntúa ? sobre " . number_format($p['valor_puntos'],2);
        }

        if($resp_alumno === null) {
            $texto_estado = "Sin responder";
            $clase_estado = "state-neutral";
            $puntaje_txt  = "Sin puntaje";
        }

        // Ajustar puntaje mostrado usando la distribucion real del intento
        if ($mostrar_correcciones && $resp_alumno !== null) {
            if ($puntaje_asignado === null) {
                if (isset($distribucion_puntajes[(string)$pid])) { $puntaje_asignado = (float)$distribucion_puntajes[(string)$pid]; }
                else if (isset($distribucion_puntajes[$pid])) { $puntaje_asignado = (float)$distribucion_puntajes[$pid]; }
                else { $puntaje_asignado = (float)$p['valor_puntos']; }
            }
            $puntaje_obtenido = ($es_correcta_pregunta) ? $puntaje_asignado : 0;
            $fmt_asig = str_replace('.', ',', number_format($puntaje_asignado, 2));
            $fmt_obt  = str_replace('.', ',', number_format($puntaje_obtenido, 2));
            $puntaje_txt  = "Puntua $fmt_obt sobre $fmt_asig";
        }
    ?>

    <div class="question-container">
        <div class="q-info-box">
            <span class="q-number">Pregunta <?= $contador ?></span>
            <span class="q-state <?= $clase_estado ?>"><?= $texto_estado ?></span>
            <span class="q-mark"><?= $puntaje_txt ?></span>
        </div>
        <div class="q-content-box">
            <div class="q-text"><?= nl2br(htmlspecialchars($p['enunciado'])) ?></div>
            <?php if (!empty($p['imagen'])): ?><img src="<?= $p['imagen'] ?>" class="q-image"><?php endif; ?>
            <div class="q-options">
                <?php if(isset($opciones[$pid])): $letras = ['a','b','c','d','e','f']; $idx = 0; foreach($opciones[$pid] as $opt):
                        $letra = $letras[$idx++] ?? '-';
                        $selected = ($opt['id'] == $resp_alumno);
                        $is_correct_opt = ($opt['es_correcta'] == 1);
                        $icon = "";
                        
                        // Lógica visual de opciones
                        if ($mostrar_correcciones) {
                            if ($selected) {
                                if ($is_correct_opt) { $icon = "<span class='icon-check'>✔</span>"; } 
                                else { $icon = "<span class='icon-cross'>✖</span>"; }
                            } else {
                                if ($is_correct_opt && !$es_correcta_pregunta && $resp_alumno !== null) {
                                     $icon = "<span style='color:#166534; font-size:0.9rem; margin-left:10px;'>← Correcta</span>";
                                }
                            }
                        } else {
                            // Si NO mostramos correcciones, solo marcamos la seleccionada sin decir si está bien o mal
                            if ($selected) { $icon = "<span style='color:#0f6cb6; font-weight:bold;'>● Seleccionada</span>"; }
                        }
                ?>
                    <div class="option-row" style="<?= $selected ? 'background:#f1f5f9;' : '' ?>">
                        <div class="option-radio"><input type="radio" disabled <?= $selected ? 'checked' : '' ?>></div>
                        <div class="option-text"><b><?= $letra ?>.</b> <?= htmlspecialchars($opt['contenido']) ?> <?= $icon ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            
            <?php if($mostrar_correcciones): ?>
                <?php if(!$es_correcta_pregunta && $resp_alumno !== null): ?>
                    <div class="feedback-box incorrect"><strong>Respuesta incorrecta</strong><br>La respuesta correcta es: <b><?= htmlspecialchars($opcion_correcta_texto) ?></b></div>
                <?php elseif($es_correcta_pregunta): ?>
                     <div class="feedback-box correct"><strong>¡Respuesta correcta!</strong></div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php $contador++; endforeach; endif; ?>
    <button class="btn-close" onclick="window.close()">Finalizar revisión</button>
</div>
</body>
</html>
