<?php
/**
 * API para Sistema de Exámenes - Universidad de Guayaquil
 * Versión 3.5 - Con verificación de tiempo en tiempo real
 * 
 * CAMBIOS v3.5:
 * - NUEVO: Endpoint 'verificar_tiempo' para polling desde la app
 * - Permite que la app del estudiante detecte cambios de tiempo en tiempo real
 * 
 * CAMBIOS v3.4:
 * - CORREGIDO: La distribución ahora se guarda como diccionario {pregunta_id: puntaje}
 * - CORREGIDO: Las preguntas con imagen SIEMPRE tienen mayor puntaje
 * - El puntaje se busca por ID de pregunta, no por posición
 * 
 * Las preguntas con IMAGEN valen más que las preguntas sin imagen.
 * 
 * Endpoints disponibles:
 * - POST /api_examen.php?action=login
 * - POST /api_examen.php?action=examenes
 * - POST /api_examen.php?action=examen_detalle
 * - POST /api_examen.php?action=preguntas
 * - POST /api_examen.php?action=iniciar_intento
 * - POST /api_examen.php?action=guardar_respuesta
 * - POST /api_examen.php?action=finalizar_examen
 * - POST /api_examen.php?action=verificar_tiempo (NUEVO)
 * - POST /api_examen.php?action=mensajeria_listar
 * - POST /api_examen.php?action=mensajeria_enviar
 * - POST /api_examen.php?action=mensajeria_limpiar
 */

// Configuración de headers para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Incluir conexión a base de datos
require_once 'db.php';

// Zona horaria
date_default_timezone_set('America/Guayaquil');

// Función para responder JSON
function responder($data, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

// Función para obtener datos POST (JSON o form-data)
function obtenerDatos() {
    $contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';
    
    if (strpos($contentType, 'application/json') !== false) {
        $json = file_get_contents('php://input');
        return json_decode($json, true) ?? [];
    }
    
    return $_POST;
}

// Obtener acción
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$datos = obtenerDatos();

// ============================================
// ACTUALIZAR ESTADOS DE EXÁMENES
// ============================================
function actualizarEstadosExamenes($conn) {
    $ahora = date('Y-m-d H:i:s');
    
    // Activar exámenes programados
    $conn->query("UPDATE examenes 
                  SET estado='activo', fecha_inicio=inicio_programado 
                  WHERE estado IN ('pendiente', 'programado') 
                  AND inicio_programado IS NOT NULL 
                  AND inicio_programado <= '$ahora'");
    
    // Cerrar exámenes vencidos
    $conn->query("UPDATE examenes 
                  SET estado='cerrado' 
                  WHERE estado='activo' 
                  AND fecha_fin IS NOT NULL 
                  AND fecha_fin < '$ahora'");
}

// ============================================
// FUNCIÓN: Generar orden aleatorio de opciones
// ============================================
function generarOrdenOpciones($conn, $pregunta_ids) {
    $orden_opciones = [];
    
    foreach ($pregunta_ids as $pid) {
        $pid = (int)$pid;
        // Obtener IDs de opciones de esta pregunta
        $sql = "SELECT id FROM opciones_pregunta WHERE pregunta_id = $pid";
        $res = $conn->query($sql);
        $opciones_ids = [];
        
        while ($row = $res->fetch_assoc()) {
            $opciones_ids[] = (int)$row['id'];
        }
        
        // Mezclar aleatoriamente
        shuffle($opciones_ids);
        
        // Guardar el orden para esta pregunta
        $orden_opciones[$pid] = $opciones_ids;
    }
    
    return $orden_opciones;
}

// ============================================
// FUNCIÓN: Reordenar opciones según orden guardado
// ============================================
function reordenarOpciones($opciones, $orden_ids) {
    if (empty($orden_ids)) {
        return $opciones;
    }
    
    // Crear mapa de opciones por ID
    $mapa = [];
    foreach ($opciones as $op) {
        $mapa[$op['id']] = $op;
    }
    
    // Reordenar según el orden guardado
    $opciones_ordenadas = [];
    foreach ($orden_ids as $id) {
        if (isset($mapa[$id])) {
            $opciones_ordenadas[] = $mapa[$id];
        }
    }
    
    // Agregar cualquier opción que no estuviera en el orden (por seguridad)
    foreach ($opciones as $op) {
        $encontrada = false;
        foreach ($opciones_ordenadas as $oo) {
            if ($oo['id'] == $op['id']) {
                $encontrada = true;
                break;
            }
        }
        if (!$encontrada) {
            $opciones_ordenadas[] = $op;
        }
    }
    
    return $opciones_ordenadas;
}

// ============================================
// FUNCIÓN: Calcular distribución de puntajes (INTELIGENTE)
// Considera preguntas con imagen (valen más) y sin imagen
// Siempre suma exactamente 10 puntos
// ============================================
function calcularDistribucionPuntajes($total_preguntas, $preguntas_con_imagen = 0) {
    if ($total_preguntas <= 0) {
        return ['distribucion' => [], 'tipo' => 'error'];
    }
    
    $preguntas_sin_imagen = $total_preguntas - $preguntas_con_imagen;
    
    // Si no hay preguntas con imagen, distribución simple
    if ($preguntas_con_imagen == 0) {
        return calcularDistribucionSimple($total_preguntas);
    }
    
    // Valores "bonitos" permitidos (ordenados de menor a mayor)
    $valores = [0.25, 0.30, 0.35, 0.40, 0.45, 0.50, 0.55, 0.60, 0.65, 0.70, 0.75, 0.80, 0.85, 0.90, 0.95,
                1.00, 1.05, 1.10, 1.15, 1.20, 1.25, 1.30, 1.35, 1.40, 1.45, 1.50, 1.55, 1.60, 1.65, 1.70, 
                1.75, 1.80, 1.85, 1.90, 1.95, 2.00, 2.25, 2.50, 2.75, 3.00, 3.25, 3.50, 3.75, 4.00, 4.50, 5.00];
    
    $mejor_distribucion = null;
    $mejor_score = PHP_INT_MAX;
    
    // Buscar combinación donde valor_imagen > valor_normal
    foreach ($valores as $v_normal) {
        foreach ($valores as $v_imagen) {
            // Las preguntas con imagen deben valer más
            if ($v_imagen <= $v_normal) {
                continue;
            }
            
            $suma = round($preguntas_sin_imagen * $v_normal + $preguntas_con_imagen * $v_imagen, 2);
            
            if ($suma == 10.00) {
                // Calcular score: preferir valores más "redondos" y diferencia razonable
                $redondez_normal = (fmod($v_normal * 100, 25) == 0) ? 0 : 1;
                $redondez_imagen = (fmod($v_imagen * 100, 25) == 0) ? 0 : 1;
                $diferencia_ratio = $v_imagen / $v_normal;
                
                // Preferir que la imagen valga entre 1.5x y 3x más
                $score_ratio = abs($diferencia_ratio - 2.0) * 10;
                $score = $redondez_normal + $redondez_imagen + $score_ratio;
                
                if ($score < $mejor_score) {
                    $mejor_score = $score;
                    $mejor_distribucion = [
                        'v_normal' => $v_normal,
                        'v_imagen' => $v_imagen,
                        'n_normal' => $preguntas_sin_imagen,
                        'n_imagen' => $preguntas_con_imagen
                    ];
                }
            }
        }
    }
    
    // Si no encontramos con 2 valores, intentar con 3 valores (normal, medio, imagen)
    if ($mejor_distribucion === null && $preguntas_sin_imagen >= 2) {
        foreach ($valores as $v_bajo) {
            foreach ($valores as $v_medio) {
                if ($v_medio <= $v_bajo) continue;
                
                foreach ($valores as $v_alto) {
                    if ($v_alto <= $v_medio) continue;
                    
                    // Distribuir las preguntas sin imagen entre bajo y medio
                    for ($n_bajo = 0; $n_bajo <= $preguntas_sin_imagen; $n_bajo++) {
                        $n_medio = $preguntas_sin_imagen - $n_bajo;
                        
                        $suma = round($n_bajo * $v_bajo + $n_medio * $v_medio + $preguntas_con_imagen * $v_alto, 2);
                        
                        if ($suma == 10.00) {
                            $score = abs($n_bajo - $n_medio) + abs($v_alto / $v_medio - 1.5) * 5;
                            
                            if ($score < $mejor_score) {
                                $mejor_score = $score;
                                $mejor_distribucion = [
                                    'tipo_3_valores' => true,
                                    'v_bajo' => $v_bajo,
                                    'v_medio' => $v_medio,
                                    'v_alto' => $v_alto,
                                    'n_bajo' => $n_bajo,
                                    'n_medio' => $n_medio,
                                    'n_alto' => $preguntas_con_imagen
                                ];
                            }
                        }
                    }
                }
            }
        }
    }
    
    if ($mejor_distribucion !== null) {
        if (isset($mejor_distribucion['tipo_3_valores'])) {
            // Distribución con 3 valores
            $v_bajo = $mejor_distribucion['v_bajo'];
            $v_medio = $mejor_distribucion['v_medio'];
            $v_alto = $mejor_distribucion['v_alto'];
            $n_bajo = $mejor_distribucion['n_bajo'];
            $n_medio = $mejor_distribucion['n_medio'];
            $n_alto = $mejor_distribucion['n_alto'];
            
            $descripcion = "{$n_alto}×{$v_alto} (img) + {$n_medio}×{$v_medio} + {$n_bajo}×{$v_bajo} = 10 pts";
            
            return [
                'distribucion' => [], // Se llenará después con los IDs
                'tipo' => 'mixto_3',
                'valor_imagen' => $v_alto,
                'valor_medio' => $v_medio,
                'valor_normal' => $v_bajo,
                'cant_imagen' => $n_alto,
                'cant_medio' => $n_medio,
                'cant_normal' => $n_bajo,
                'descripcion' => $descripcion
            ];
        } else {
            // Distribución con 2 valores
            $v_normal = $mejor_distribucion['v_normal'];
            $v_imagen = $mejor_distribucion['v_imagen'];
            $n_normal = $mejor_distribucion['n_normal'];
            $n_imagen = $mejor_distribucion['n_imagen'];
            
            $descripcion = "{$n_imagen}×{$v_imagen} (img) + {$n_normal}×{$v_normal} = 10 pts";
            
            return [
                'distribucion' => [], // Se llenará después con los IDs
                'tipo' => 'mixto_imagen',
                'valor_imagen' => $v_imagen,
                'valor_normal' => $v_normal,
                'cant_imagen' => $n_imagen,
                'cant_normal' => $n_normal,
                'descripcion' => $descripcion
            ];
        }
    }
    
    // Fallback: calcular valores exactos
    return calcularDistribucionExacta($total_preguntas, $preguntas_con_imagen);
}

// ============================================
// FUNCIÓN: Distribución simple (sin considerar imágenes)
// ============================================
function calcularDistribucionSimple($total_preguntas) {
    // Casos especiales donde todas las preguntas valen igual
    $casos_uniformes = [
        1 => 10.00, 2 => 5.00, 4 => 2.50, 5 => 2.00, 
        8 => 1.25, 10 => 1.00, 20 => 0.50, 40 => 0.25
    ];
    
    if (isset($casos_uniformes[$total_preguntas])) {
        $valor = $casos_uniformes[$total_preguntas];
        return [
            'distribucion' => array_fill(0, $total_preguntas, $valor),
            'tipo' => 'uniforme',
            'valor_unico' => $valor,
            'descripcion' => "Todas las preguntas valen $valor pts"
        ];
    }
    
    // Valores "bonitos" permitidos
    $valores = [0.25, 0.50, 0.75, 1.00, 1.25, 1.50, 1.75, 2.00, 2.50, 3.00, 3.50, 4.00, 5.00];
    
    $mejor_distribucion = null;
    $mejor_score = PHP_INT_MAX;
    
    for ($i = 0; $i < count($valores); $i++) {
        for ($j = $i; $j < count($valores); $j++) {
            $v1 = $valores[$i];
            $v2 = $valores[$j];
            
            for ($n1 = 0; $n1 <= $total_preguntas; $n1++) {
                $n2 = $total_preguntas - $n1;
                $suma = round($n1 * $v1 + $n2 * $v2, 2);
                
                if ($suma == 10.00) {
                    $score = abs($n1 - $n2) + abs($v2 - $v1) * 10;
                    
                    if ($score < $mejor_score) {
                        $mejor_score = $score;
                        $mejor_distribucion = ['n1' => $n1, 'v1' => $v1, 'n2' => $n2, 'v2' => $v2];
                    }
                }
            }
        }
    }
    
    if ($mejor_distribucion !== null) {
        $n1 = $mejor_distribucion['n1'];
        $v1 = $mejor_distribucion['v1'];
        $n2 = $mejor_distribucion['n2'];
        $v2 = $mejor_distribucion['v2'];
        
        $distribucion = array_merge(array_fill(0, $n1, $v1), array_fill(0, $n2, $v2));
        shuffle($distribucion);
        
        $desc = ($v1 == $v2) ? "Todas valen $v1 pts" : "{$n1}×{$v1} + {$n2}×{$v2} = 10 pts";
        
        return [
            'distribucion' => $distribucion,
            'tipo' => 'mixto',
            'descripcion' => $desc
        ];
    }
    
    // Fallback
    $valor = round(10.0 / $total_preguntas, 2);
    return [
        'distribucion' => array_fill(0, $total_preguntas, $valor),
        'tipo' => 'calculado',
        'descripcion' => "Valor base: $valor pts"
    ];
}

// ============================================
// FUNCIÓN: Distribución exacta (fallback matemático)
// ============================================
function calcularDistribucionExacta($total, $con_imagen) {
    $sin_imagen = $total - $con_imagen;
    
    // Las preguntas con imagen valen el doble
    // n_sin * v + n_img * 2v = 10
    // v * (n_sin + 2*n_img) = 10
    $divisor = $sin_imagen + (2 * $con_imagen);
    $v_normal = round(10.0 / $divisor, 2);
    $v_imagen = round($v_normal * 2, 2);
    
    // Ajustar para que sume exactamente 10
    $suma = ($sin_imagen * $v_normal) + ($con_imagen * $v_imagen);
    $diferencia = round(10.00 - $suma, 2);
    
    if ($diferencia != 0 && $con_imagen > 0) {
        $v_imagen = round($v_imagen + ($diferencia / $con_imagen), 2);
    }
    
    return [
        'distribucion' => [],
        'tipo' => 'exacto',
        'valor_imagen' => $v_imagen,
        'valor_normal' => $v_normal,
        'cant_imagen' => $con_imagen,
        'cant_normal' => $sin_imagen,
        'descripcion' => "{$con_imagen}×{$v_imagen} (img) + {$sin_imagen}×{$v_normal} = 10 pts"
    ];
}

// ============================================
// FUNCIÓN: Generar array de distribución con IDs de preguntas
// Retorna un diccionario {pregunta_id => puntaje} para evitar desalineación
// ============================================
function generarDistribucionConIds($resultado_dist, $preguntas_ids, $preguntas_con_imagen_ids) {
    $distribucion = [];
    $preguntas_sin_imagen_ids = array_diff($preguntas_ids, $preguntas_con_imagen_ids);
    
    // Convertir a arrays indexados
    $con_img = array_values($preguntas_con_imagen_ids);
    $sin_img = array_values($preguntas_sin_imagen_ids);
    
    if ($resultado_dist['tipo'] === 'uniforme' || $resultado_dist['tipo'] === 'calculado') {
        // Todas las preguntas tienen el mismo valor
        $valor = $resultado_dist['valor_unico'] ?? round(10.0 / count($preguntas_ids), 2);
        foreach ($preguntas_ids as $id) {
            $distribucion[strval($id)] = $valor;
        }
        return $distribucion;
    }
    
    if ($resultado_dist['tipo'] === 'mixto' && empty($preguntas_con_imagen_ids)) {
        // Distribución mixta sin imágenes - ya tiene la distribución lista
        if (!empty($resultado_dist['distribucion'])) {
            // Convertir array indexado a diccionario por ID
            foreach ($preguntas_ids as $idx => $id) {
                $distribucion[strval($id)] = $resultado_dist['distribucion'][$idx] ?? round(10.0 / count($preguntas_ids), 2);
            }
            return $distribucion;
        }
    }
    
    // Para distribuciones que consideran imágenes
    $v_imagen = $resultado_dist['valor_imagen'] ?? 1.5;
    $v_normal = $resultado_dist['valor_normal'] ?? 0.5;
    $v_medio = $resultado_dist['valor_medio'] ?? $v_normal;
    
    // Asignar valor ALTO a preguntas CON imagen
    foreach ($con_img as $id) {
        $distribucion[strval($id)] = $v_imagen;
    }
    
    // Asignar valores a preguntas SIN imagen
    if (isset($resultado_dist['tipo']) && $resultado_dist['tipo'] === 'mixto_3') {
        $cant_medio = $resultado_dist['cant_medio'] ?? 0;
        
        shuffle($sin_img); // Mezclar para distribución aleatoria
        
        for ($i = 0; $i < count($sin_img); $i++) {
            if ($i < $cant_medio) {
                $distribucion[strval($sin_img[$i])] = $v_medio;
            } else {
                $distribucion[strval($sin_img[$i])] = $v_normal;
            }
        }
    } else {
        // Distribución con 2 valores: alto (imagen) y normal (sin imagen)
        foreach ($sin_img as $id) {
            $distribucion[strval($id)] = $v_normal;
        }
    }
    
    return $distribucion;
}

// ============================================
// MENSAJERIA: TABLA Y HELPERS
// ============================================
function asegurarTablaMensajes($conn) {
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

function obtenerCursoPorExamen($conn, $examen_id) {
    $examen_id = (int)$examen_id;
    $res = $conn->query("SELECT curso_id FROM examenes WHERE id = $examen_id LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['curso_id'];
    }
    return 0;
}

function obtenerDocentePorCurso($conn, $curso_id) {
    $curso_id = (int)$curso_id;
    $sql = "SELECT u.id, u.nombres, u.rol
            FROM usuario_cursos uc
            JOIN usuarios u ON uc.usuario_id = u.id
            WHERE uc.curso_id = $curso_id
            AND u.rol IN ('docente','director_carrera','director_area')
            ORDER BY u.id ASC
            LIMIT 1";
    $res = $conn->query($sql);
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    return null;
}

function asegurarTablaNotificacionesSeguridad($conn) {
    $check = $conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'notificaciones_seguridad_examen' LIMIT 1");
    if (!$check || $check->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS notificaciones_seguridad_examen (
            id SERIAL PRIMARY KEY,
            docente_id INTEGER NOT NULL,
            curso_id INTEGER NOT NULL,
            examen_id INTEGER NOT NULL,
            estudiante_id INTEGER NOT NULL,
            tipo VARCHAR(80) NOT NULL DEFAULT 'salida_video_externa',
            motivo TEXT NOT NULL,
            oculto_docente SMALLINT NOT NULL DEFAULT 0,
            fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    }
}

function asegurarTablaAuditoriaLogApi($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS auditoria_log (
        id SERIAL PRIMARY KEY,
        actor_id INTEGER NOT NULL,
        actor_nombre VARCHAR(150) DEFAULT NULL,
        rol_actor VARCHAR(50) NOT NULL,
        accion VARCHAR(50) NOT NULL,
        entidad_afectada VARCHAR(50) DEFAULT NULL,
        id_afectado INTEGER DEFAULT NULL,
        detalles TEXT DEFAULT NULL,
        fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
}

function registrarEventoSeguridadAuditoria($conn, $estudiante_id, $examen_id, $tipo, $motivo) {
    $estudiante_id = (int)$estudiante_id;
    $examen_id = (int)$examen_id;
    if ($estudiante_id <= 0 || $examen_id <= 0) return;

    asegurarTablaAuditoriaLogApi($conn);

    $actor_nombre = "Estudiante #$estudiante_id";
    $rol_actor = 'estudiante';
    $res_est = $conn->query("SELECT nombres, rol FROM usuarios WHERE id = $estudiante_id LIMIT 1");
    if ($res_est && $res_est->num_rows > 0) {
        $row_est = $res_est->fetch_assoc();
        $actor_nombre = trim((string)($row_est['nombres'] ?? '')) ?: $actor_nombre;
        $rol_actor = trim((string)($row_est['rol'] ?? '')) ?: $rol_actor;
    }

    $tipo = trim((string)$tipo);
    if ($tipo === '') $tipo = 'salida_video_externa';
    $motivo = trim((string)$motivo);
    if ($motivo === '') {
        $motivo = 'Cierre automatico por seguridad: dispositivo de salida de video externo detectado.';
    }

    $detalle = "Alerta de seguridad detectada. Estudiante: $actor_nombre. Tipo: $tipo. Motivo: $motivo";
    if (function_exists('mb_substr')) {
        $detalle = mb_substr($detalle, 0, 1000, 'UTF-8');
    } else {
        $detalle = substr($detalle, 0, 1000);
    }

    $actor_nombre_safe = $conn->real_escape_string($actor_nombre);
    $rol_actor_safe = $conn->real_escape_string($rol_actor);
    $accion_safe = $conn->real_escape_string('ALERTA_SEGURIDAD');
    $entidad_safe = $conn->real_escape_string('EXAMEN');
    $detalle_safe = $conn->real_escape_string($detalle);
    $fecha_local = date('Y-m-d H:i:s');

    $conn->query("INSERT INTO auditoria_log
        (actor_id, actor_nombre, rol_actor, accion, entidad_afectada, id_afectado, detalles, fecha_registro)
        VALUES
        ($estudiante_id, '$actor_nombre_safe', '$rol_actor_safe', '$accion_safe', '$entidad_safe', $examen_id, '$detalle_safe', '$fecha_local')");
}

function registrarNotificacionSeguridadExamen($conn, $examen_id, $estudiante_id, $motivo, $tipo = 'salida_video_externa') {
    $examen_id = (int)$examen_id;
    $estudiante_id = (int)$estudiante_id;
    if ($examen_id <= 0 || $estudiante_id <= 0) {
        $GLOBALS['last_notif_error'] = "examen_id ($examen_id) o estudiante_id ($estudiante_id) inválido";
        return false;
    }

    asegurarTablaNotificacionesSeguridad($conn);

    $curso_id = obtenerCursoPorExamen($conn, $examen_id);
    if ($curso_id <= 0) {
        $GLOBALS['last_notif_error'] = "curso no encontrado para examen $examen_id";
        return false;
    }

    $docente = obtenerDocentePorCurso($conn, $curso_id);
    $docente_id = (int)($docente['id'] ?? 0);
    if ($docente_id <= 0) {
        $res_doc = $conn->query("SELECT usuario_id FROM usuario_cursos WHERE curso_id = $curso_id LIMIT 1");
        if ($res_doc && $r_doc = $res_doc->fetch_assoc()) {
            $docente_id = (int)$r_doc['usuario_id'];
        }
    }

    $motivo = trim((string)$motivo);
    if ($motivo === '') {
        $motivo = 'Cierre automatico por seguridad: dispositivo de salida de video externo detectado.';
    }

    $tipo = trim((string)$tipo);
    if ($tipo === '') $tipo = 'salida_video_externa';

    $motivo_safe = $conn->real_escape_string($motivo);
    $tipo_safe = $conn->real_escape_string($tipo);

    // Evitar duplicados por reintentos en una ventana corta (1 minuto)
    $sql_dup = "SELECT id
                FROM notificaciones_seguridad_examen
                WHERE examen_id = $examen_id
                  AND estudiante_id = $estudiante_id
                  AND tipo = '$tipo_safe'
                  AND fecha_creacion >= (NOW() - INTERVAL '1 MINUTE')
                ORDER BY id DESC
                LIMIT 1";
    $res_dup = $conn->query($sql_dup);
    if ($res_dup && $res_dup->num_rows > 0) return true;

    $sql_ins = "INSERT INTO notificaciones_seguridad_examen
        (docente_id, curso_id, examen_id, estudiante_id, tipo, motivo)
        VALUES
        ($docente_id, $curso_id, $examen_id, $estudiante_id, '$tipo_safe', '$motivo_safe')";

    $res = $conn->query($sql_ins);
    if (!$res) {
        $GLOBALS['last_notif_error'] = "Insert failed: " . $conn->error . " | SQL: " . $sql_ins;
        return false;
    }

    registrarEventoSeguridadAuditoria($conn, $estudiante_id, $examen_id, $tipo, $motivo);
    return true;
}

// ============================================
// RUTAS DE LA API
// ============================================

switch ($action) {
    
    // ----------------------------------------
    // MENSAJERIA: LISTAR (ESTUDIANTE)
    // ----------------------------------------
    case 'mensajeria_listar':
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        $examen_id = (int)($datos['examen_id'] ?? 0);
        
        if ($estudiante_id <= 0 || $examen_id <= 0) {
            responder(['success' => false, 'error' => 'IDs requeridos'], 400);
        }
        
        asegurarTablaMensajes($conn);
        $curso_id = obtenerCursoPorExamen($conn, $examen_id);
        if ($curso_id <= 0) {
            responder(['success' => false, 'error' => 'Curso no encontrado'], 404);
        }
        
        $docente = obtenerDocentePorCurso($conn, $curso_id);
        if (!$docente) {
            responder(['success' => false, 'error' => 'Docente no encontrado'], 404);
        }
        
        $docente_id = (int)$docente['id'];
        $sql = "SELECT id, emisor, mensaje, fecha_creacion 
                FROM mensajes_chat 
                WHERE curso_id = $curso_id 
                AND docente_id = $docente_id 
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
        
        responder([
            'success' => true,
            'docente' => ['id' => $docente_id, 'nombres' => $docente['nombres']],
            'mensajes' => $mensajes
        ]);
        break;
    
    // ----------------------------------------
    // MENSAJERIA: ENVIAR (ESTUDIANTE)
    // ----------------------------------------
    case 'mensajeria_enviar':
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        $examen_id = (int)($datos['examen_id'] ?? 0);
        $mensaje_raw = trim($datos['mensaje'] ?? '');
        
        if ($estudiante_id <= 0 || $examen_id <= 0) {
            responder(['success' => false, 'error' => 'IDs requeridos'], 400);
        }
        if ($mensaje_raw === '') {
            responder(['success' => false, 'error' => 'Mensaje vacÇ­o'], 400);
        }
        
        if (strlen($mensaje_raw) > 500) {
            $mensaje_raw = substr($mensaje_raw, 0, 500);
        }
        
        asegurarTablaMensajes($conn);
        $curso_id = obtenerCursoPorExamen($conn, $examen_id);
        if ($curso_id <= 0) {
            responder(['success' => false, 'error' => 'Curso no encontrado'], 404);
        }
        
        $docente = obtenerDocentePorCurso($conn, $curso_id);
        if (!$docente) {
            responder(['success' => false, 'error' => 'Docente no encontrado'], 404);
        }
        
        $docente_id = (int)$docente['id'];
        $mensaje_safe = $conn->real_escape_string($mensaje_raw);
        $fecha = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO mensajes_chat 
                (curso_id, examen_id, docente_id, estudiante_id, emisor, mensaje, es_temporal, fecha_creacion) 
                VALUES ($curso_id, $examen_id, $docente_id, $estudiante_id, 'estudiante', '$mensaje_safe', 0, '$fecha')";
        
        if ($conn->query($sql)) {
            responder([
                'success' => true,
                'mensaje' => [
                    'id' => $conn->insert_id,
                    'emisor' => 'estudiante',
                    'mensaje' => $mensaje_raw,
                    'fecha_creacion' => $fecha
                ]
            ]);
        } else {
            responder(['success' => false, 'error' => 'Error al enviar mensaje'], 500);
        }
        break;
    
    // ----------------------------------------
    // MENSAJERIA: LIMPIAR (ESTUDIANTE)
    // ----------------------------------------
    case 'mensajeria_limpiar':
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        
        if ($estudiante_id <= 0) {
            responder(['success' => false, 'error' => 'ID requerido'], 400);
        }
        
        asegurarTablaMensajes($conn);
        responder(['success' => true, 'eliminados' => 0]);
        break;
    
    // ----------------------------------------
    // LOGIN
    // ----------------------------------------
    case 'login':
        $cedula = $conn->real_escape_string($datos['cedula'] ?? '');
        $password = $datos['password'] ?? '';
        
        if (empty($cedula) || empty($password)) {
            responder(['success' => false, 'error' => 'Cédula y contraseña requeridos'], 400);
        }
        
        $password_hash = md5($password);
        
        $sql = "SELECT id, nombres, cedula, email, rol, estado 
                FROM usuarios 
                WHERE cedula = '$cedula' 
                AND password = '$password_hash' 
                AND estado = 'aceptado' 
                AND rol = 'estudiante'
                LIMIT 1";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $usuario = $result->fetch_assoc();
            responder([
                'success' => true,
                'usuario' => $usuario
            ]);
        } else {
            responder(['success' => false, 'error' => 'Credenciales inválidas o usuario no aceptado'], 401);
        }
        break;
    
    // ----------------------------------------
    // OBTENER EXÁMENES DEL ESTUDIANTE
    // ----------------------------------------
    case 'examenes':
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        
        if ($estudiante_id <= 0) {
            responder(['success' => false, 'error' => 'ID de estudiante requerido'], 400);
        }
        
        actualizarEstadosExamenes($conn);
        
        $sql = "SELECT 
                    e.id, e.titulo, e.descripcion, e.duracion_minutos,
                    e.fecha_inicio, e.fecha_fin, e.inicio_programado, e.estado,
                    e.mostrar_nota, e.permitir_revision, e.preguntas_secuenciales,
                    e.cant_preguntas_mostrar,
                    m.nombre as materia, p.nombre as paralelo,
                    re.puntaje_total as nota_registrada, 
                    re.fecha_intento,
                    re.orden_preguntas,
                    re.respuestas_json,
                    re.pregunta_actual,
                    re.fecha_inicio_real,
                    (SELECT ns.motivo FROM notificaciones_seguridad_examen ns 
                     WHERE ns.examen_id = e.id AND ns.estudiante_id = $estudiante_id AND COALESCE(ns.oculto_docente, 0) = 0 
                     ORDER BY ns.fecha_creacion DESC, ns.id DESC LIMIT 1) AS seguridad_motivo,
                    (SELECT ns.tipo FROM notificaciones_seguridad_examen ns 
                     WHERE ns.examen_id = e.id AND ns.estudiante_id = $estudiante_id AND COALESCE(ns.oculto_docente, 0) = 0 
                     ORDER BY ns.fecha_creacion DESC, ns.id DESC LIMIT 1) AS seguridad_tipo
                FROM examenes e
                JOIN cursos_oferta co ON e.curso_id = co.id
                JOIN usuario_cursos uc ON uc.curso_id = co.id
                JOIN paralelos p ON co.paralelo_id = p.id
                JOIN materias m ON p.materia_id = m.id
                LEFT JOIN resultados_examen re ON re.examen_id = e.id AND re.estudiante_id = $estudiante_id
                WHERE uc.usuario_id = $estudiante_id 
                AND e.estado IN ('activo', 'programado', 'cerrado')
                ORDER BY 
                    CASE e.estado 
                        WHEN 'activo' THEN 1 
                        WHEN 'programado' THEN 2 
                        ELSE 3 
                    END,
                    e.fecha_fin ASC";
        
        $result = $conn->query($sql);
        $examenes = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $orden_arr = !empty($row['orden_preguntas']) ? array_filter(explode(',', $row['orden_preguntas'])) : [];
                $resp_arr = !empty($row['respuestas_json']) ? json_decode($row['respuestas_json'], true) : [];
                $total_p = count($orden_arr) > 0 ? count($orden_arr) : (int)($row['cant_preguntas_mostrar'] ?: 0);
                $resp_p = is_array($resp_arr) ? count($resp_arr) : 0;
                $act_p = (int)($row['pregunta_actual'] ?? 0);
                if ($act_p <= 0) {
                    $act_p = $total_p > 0 ? min($total_p, $resp_p + 1) : 1;
                }

                $nota_num = ($row['nota_registrada'] !== null && $row['nota_registrada'] !== '') ? floatval($row['nota_registrada']) : null;
                $en_progreso = ($nota_num === -1.0);
                $ya_rendido = ($nota_num !== null && $nota_num >= 0.0);
                $esta_pausado = !empty($row['seguridad_motivo']) && $en_progreso;

                // Si el examen está cerrado por el docente:
                // Solo si el estudiante lo completó (ya_rendido) se muestra; si no lo completó o estaba rindiendo,
                // NO se debe reflejar en el aplicativo del estudiante.
                if ($row['estado'] === 'cerrado' && !$ya_rendido) {
                    continue;
                }

                $row['total_preguntas'] = $total_p;
                $row['respondidas_count'] = $resp_p;
                $row['pregunta_actual'] = $act_p;
                $row['en_progreso'] = $en_progreso;
                $row['ya_rendido'] = $ya_rendido;
                $row['esta_pausado'] = $esta_pausado;
                $row['motivo_pausa'] = $row['seguridad_motivo'] ?? '';
                $row['nota_float'] = $nota_num;

                $examenes[] = $row;
            }
        }
        
        responder(['success' => true, 'examenes' => $examenes]);
        break;
    
    // ----------------------------------------
    // DETALLE DE UN EXAMEN
    // ----------------------------------------
    case 'examen_detalle':
        $examen_id = (int)($datos['examen_id'] ?? 0);
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        
        if ($examen_id <= 0) {
            responder(['success' => false, 'error' => 'ID de examen requerido'], 400);
        }
        
        actualizarEstadosExamenes($conn);
        
        $sql = "SELECT e.*, m.nombre as materia, p.nombre as paralelo
                FROM examenes e
                JOIN cursos_oferta co ON e.curso_id = co.id
                JOIN paralelos p ON co.paralelo_id = p.id
                JOIN materias m ON p.materia_id = m.id
                WHERE e.id = $examen_id";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $examen = $result->fetch_assoc();
            
            if ($estudiante_id > 0) {
                $sql_intento = "SELECT * FROM resultados_examen 
                                WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id LIMIT 1";
                $res_intento = $conn->query($sql_intento);
                $examen['intento'] = $res_intento->fetch_assoc();
            }
            
            responder(['success' => true, 'examen' => $examen]);
        } else {
            responder(['success' => false, 'error' => 'Examen no encontrado'], 404);
        }
        break;
    
    // ----------------------------------------
    // VERIFICAR TIEMPO ACTUALIZADO (POLLING DESDE LA APP)
    // Este endpoint permite que la app del estudiante verifique
    // si el tiempo del examen ha sido modificado por el docente
    // ----------------------------------------
    case 'verificar_tiempo':
        $examen_id = (int)($datos['examen_id'] ?? 0);
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        
        if ($examen_id <= 0 || $estudiante_id <= 0) {
            responder(['success' => false, 'error' => 'IDs requeridos'], 400);
        }
        
        // Obtener datos actuales del examen
        $sql_examen = "SELECT id, titulo, duracion_minutos, fecha_fin, estado, fecha_inicio, preguntas_secuenciales
                       FROM examenes WHERE id = $examen_id";
        $res_examen = $conn->query($sql_examen);
        
        if (!$res_examen || $res_examen->num_rows == 0) {
            responder(['success' => false, 'error' => 'Examen no encontrado'], 404);
        }
        
        $examen = $res_examen->fetch_assoc();
        
        // Obtener datos del intento del estudiante
        $sql_intento = "SELECT id, fecha_inicio_real, puntaje_total 
                        FROM resultados_examen 
                        WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id";
        $res_intento = $conn->query($sql_intento);
        
        if (!$res_intento || $res_intento->num_rows == 0) {
            responder(['success' => false, 'error' => 'Intento no encontrado'], 404);
        }
        
        $intento = $res_intento->fetch_assoc();
        
        // Verificar si el examen ya fue completado
        if ($intento['puntaje_total'] != -1) {
            responder([
                'success' => true,
                'examen_finalizado' => true,
                'debe_finalizar' => false,
                'mensaje' => 'El examen ya fue completado'
            ]);
        }
        
        // Calcular tiempo restante basado en:
        // 1. La fecha_inicio_real del intento del estudiante
        // 2. La duración configurada del examen
        // 3. La fecha_fin del examen (límite máximo)
        
        $fecha_inicio_intento = new DateTime($intento['fecha_inicio_real']);
        $duracion_minutos = (int)$examen['duracion_minutos'];
        
        // Fecha límite basada en duración del intento
        $fecha_limite_intento = clone $fecha_inicio_intento;
        $fecha_limite_intento->modify("+{$duracion_minutos} minutes");
        
        // Fecha límite del examen
        $fecha_fin_examen = new DateTime($examen['fecha_fin']);
        
        // La fecha final es la menor de las dos
        $fecha_fin_final = ($fecha_limite_intento < $fecha_fin_examen) ? $fecha_limite_intento : $fecha_fin_examen;
        
        // Calcular tiempo restante
        $ahora = new DateTime();
        $diff = $fecha_fin_final->getTimestamp() - $ahora->getTimestamp();
        $tiempo_restante = max(0, $diff);
        
        // Verificar si el examen está pausado por alerta de seguridad activa
        $sql_sec = "SELECT motivo FROM notificaciones_seguridad_examen 
                    WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id AND COALESCE(oculto_docente, 0) = 0 
                    ORDER BY id DESC LIMIT 1";
        $res_sec = $conn->query($sql_sec);
        $esta_pausado = false;
        $motivo_pausa = '';
        if ($res_sec && $res_sec->num_rows > 0) {
            $row_sec = $res_sec->fetch_assoc();
            $esta_pausado = true;
            $motivo_pausa = $row_sec['motivo'] ?: 'Examen pausado por motivos de seguridad o imprevisto.';
        }

        if ($esta_pausado) {
            responder([
                'success' => true,
                'pausado' => true,
                'motivo_pausa' => $motivo_pausa,
                'tiempo_restante' => max(0, $tiempo_restante),
                'duracion_minutos' => $duracion_minutos,
                'fecha_fin' => $examen['fecha_fin'],
                'fecha_fin_efectiva' => $fecha_fin_final->format('Y-m-d H:i:s'),
                'examen_activo' => false,
                'debe_finalizar' => false,
                'preguntas_secuenciales' => (int)$examen['preguntas_secuenciales'],
                'motivo' => $motivo_pausa
            ]);
        }

        // Si el examen está cerrado o el tiempo se acabó
        $examen_cerrado = ($examen['estado'] !== 'activo');
        $tiempo_agotado = ($tiempo_restante <= 0);
        
        if ($examen_cerrado || $tiempo_agotado) {
            responder([
                'success' => true,
                'pausado' => false,
                'motivo_pausa' => '',
                'tiempo_restante' => 0,
                'duracion_minutos' => $duracion_minutos,
                'fecha_fin' => $examen['fecha_fin'],
                'fecha_fin_efectiva' => $fecha_fin_final->format('Y-m-d H:i:s'),
                'examen_activo' => false,
                'debe_finalizar' => true,
                'preguntas_secuenciales' => (int)$examen['preguntas_secuenciales'],
                'motivo' => $examen_cerrado ? 'Examen cerrado por el docente' : 'Tiempo agotado'
            ]);
        }
        
        // El examen sigue activo (o pausado)
        responder([
            'success' => true,
            'pausado' => $esta_pausado,
            'motivo_pausa' => $motivo_pausa,
            'tiempo_restante' => $tiempo_restante,
            'tiempo_restante_formato' => sprintf('%02d:%02d:%02d', 
                floor($tiempo_restante / 3600), 
                floor(($tiempo_restante % 3600) / 60), 
                $tiempo_restante % 60
            ),
            'duracion_minutos' => $duracion_minutos,
            'fecha_fin' => $examen['fecha_fin'],
            'fecha_fin_efectiva' => $fecha_fin_final->format('Y-m-d H:i:s'),
            'examen_activo' => true,
            'debe_finalizar' => false,
            'preguntas_secuenciales' => (int)$examen['preguntas_secuenciales']
        ]);
        break;
    
    // ----------------------------------------
    // OBTENER PREGUNTAS DE UN EXAMEN (CON PUNTAJE CALCULADO)
    // ----------------------------------------
    case 'preguntas':
        $examen_id = (int)($datos['examen_id'] ?? 0);
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        $orden_ids = $datos['orden_ids'] ?? '';
        
        if ($examen_id <= 0) {
            responder(['success' => false, 'error' => 'ID de examen requerido'], 400);
        }
        
        // Obtener el orden de opciones y distribución de puntajes guardados
        $orden_opciones = [];
        $distribucion_puntajes = [];
        $total_preguntas_asignadas = 0;
        $orden_preguntas_ids = [];
        
        if ($estudiante_id > 0) {
            $sql_orden = "SELECT orden_opciones_json, orden_preguntas, distribucion_puntajes_json FROM resultados_examen 
                          WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id";
            $res_orden = $conn->query($sql_orden);
            if ($res_orden && $res_orden->num_rows > 0) {
                $row_orden = $res_orden->fetch_assoc();
                if (!empty($row_orden['orden_opciones_json'])) {
                    $orden_opciones = json_decode($row_orden['orden_opciones_json'], true) ?? [];
                }
                if (!empty($row_orden['distribucion_puntajes_json'])) {
                    $distribucion_puntajes = json_decode($row_orden['distribucion_puntajes_json'], true) ?? [];
                }
                if (!empty($row_orden['orden_preguntas'])) {
                    $orden_preguntas_ids = explode(',', $row_orden['orden_preguntas']);
                    $total_preguntas_asignadas = count($orden_preguntas_ids);
                }
            }
        }
        
        // Si no tenemos el total, contar desde orden_ids
        if ($total_preguntas_asignadas == 0 && !empty($orden_ids)) {
            $orden_preguntas_ids = explode(',', $orden_ids);
            $total_preguntas_asignadas = count($orden_preguntas_ids);
        }
        
        // Si no hay distribución guardada, calcularla considerando imágenes
        if (empty($distribucion_puntajes) && !empty($orden_preguntas_ids)) {
            $ids_str = implode(',', array_map('intval', $orden_preguntas_ids));
            
            // Identificar preguntas con imagen
            $sql_img = "SELECT id FROM preguntas WHERE id IN ($ids_str) AND imagen IS NOT NULL AND imagen != ''";
            $res_img = $conn->query($sql_img);
            $preguntas_con_imagen_ids = [];
            while ($row = $res_img->fetch_assoc()) {
                $preguntas_con_imagen_ids[] = (int)$row['id'];
            }
            
            $resultado_dist = calcularDistribucionPuntajes($total_preguntas_asignadas, count($preguntas_con_imagen_ids));
            $distribucion_puntajes = generarDistribucionConIds($resultado_dist, $orden_preguntas_ids, $preguntas_con_imagen_ids);
        }
        
        // Obtener preguntas
        if (!empty($orden_ids)) {
            $orden_ids_safe = $conn->real_escape_string($orden_ids);
            $sql = "SELECT id, examen_id, enunciado, imagen, valor_puntos 
                    FROM preguntas 
                    WHERE id IN ($orden_ids_safe)";
        } else {
            $sql = "SELECT id, examen_id, enunciado, imagen, valor_puntos 
                    FROM preguntas 
                    WHERE examen_id = $examen_id 
                    ORDER BY id ASC";
        }
        
        $result = $conn->query($sql);
        $preguntas_map = [];
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $pid = $row['id'];
                $pid_str = strval($pid);
                
                // Asignar puntaje usando el ID de la pregunta como clave
                if (isset($distribucion_puntajes[$pid_str])) {
                    $row['valor_puntos'] = $distribucion_puntajes[$pid_str];
                } elseif (isset($distribucion_puntajes[$pid])) {
                    $row['valor_puntos'] = $distribucion_puntajes[$pid];
                } else {
                    $row['valor_puntos'] = round(10.0 / max(1, $total_preguntas_asignadas), 2);
                }
                $row['puntaje_calculado'] = true;
                $row['tiene_imagen'] = !empty($row['imagen']);
                
                // Obtener opciones
                $sql_op = "SELECT id, pregunta_id, contenido, imagen, es_correcta, indice 
                           FROM opciones_pregunta 
                           WHERE pregunta_id = $pid 
                           ORDER BY indice ASC, id ASC";
                $res_op = $conn->query($sql_op);
                $opciones = [];
                
                while ($op = $res_op->fetch_assoc()) {
                    unset($op['es_correcta']); // No enviar cuál es correcta
                    $opciones[] = $op;
                }
                
                // Reordenar opciones según el orden guardado
                if (isset($orden_opciones[$pid]) && !empty($orden_opciones[$pid])) {
                    $opciones = reordenarOpciones($opciones, $orden_opciones[$pid]);
                }
                
                $row['opciones'] = $opciones;
                $preguntas_map[(int)$pid] = $row;
            }
        }

        // Ordenar en PHP según el orden asignado
        $preguntas = [];
        if (!empty($orden_preguntas_ids)) {
            foreach ($orden_preguntas_ids as $pid) {
                $pid = (int)$pid;
                if (isset($preguntas_map[$pid])) {
                    $preguntas[] = $preguntas_map[$pid];
                    unset($preguntas_map[$pid]);
                }
            }
            foreach ($preguntas_map as $p) {
                $preguntas[] = $p;
            }
        } else {
            $preguntas = array_values($preguntas_map);
        }
        
        // Calcular suma total para verificación
        $suma_total = 0;
        foreach ($distribucion_puntajes as $pts) {
            $suma_total += $pts;
        }
        
        responder([
            'success' => true, 
            'preguntas' => $preguntas,
            'total_preguntas' => $total_preguntas_asignadas,
            'distribucion_puntajes' => $distribucion_puntajes,
            'suma_verificacion' => round($suma_total, 2)
        ]);
        break;
    
    // ----------------------------------------
    // INICIAR/OBTENER INTENTO (CON DISTRIBUCIÓN DE PUNTAJES)
    // ----------------------------------------
    case 'iniciar_intento':
        $examen_id = (int)($datos['examen_id'] ?? 0);
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        
        if ($examen_id <= 0 || $estudiante_id <= 0) {
            responder(['success' => false, 'error' => 'IDs requeridos'], 400);
        }
        
        // Verificar si el examen está pausado por alerta de seguridad activa
        $sql_sec = "SELECT motivo FROM notificaciones_seguridad_examen 
                    WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id AND COALESCE(oculto_docente, 0) = 0 
                    ORDER BY id DESC LIMIT 1";
        $res_sec = $conn->query($sql_sec);
        if ($res_sec && $res_sec->num_rows > 0) {
            $row_sec = $res_sec->fetch_assoc();
            $motivo_pausa = $row_sec['motivo'] ?: 'Examen pausado por motivos de seguridad o imprevisto.';
            responder([
                'success' => false,
                'pausado' => true,
                'error' => "⚠️ Examen en PAUSA:\n" . $motivo_pausa . "\n\nPor favor comunícate con tu docente para que reanude tu examen."
            ], 200);
        }
        
        // Verificar si ya existe intento
        $sql_check = "SELECT * FROM resultados_examen 
                      WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id LIMIT 1";
        $res_check = $conn->query($sql_check);
        
        if ($res_check && $res_check->num_rows > 0) {
            $intento = $res_check->fetch_assoc();
            
            // Recuperar distribución de puntajes (diccionario {pregunta_id: puntaje})
            $distribucion_guardada = [];
            if (!empty($intento['distribucion_puntajes_json'])) {
                $distribucion_guardada = json_decode($intento['distribucion_puntajes_json'], true) ?? [];
            }
            
            $orden_ids = explode(',', $intento['orden_preguntas']);
            $ids_str = implode(',', array_map('intval', $orden_ids));
            
            // Verificar si la distribución es formato viejo (array indexado) o nuevo (diccionario por ID)
            $es_formato_viejo = !empty($distribucion_guardada) && isset($distribucion_guardada[0]);
            
            if (empty($distribucion_guardada) || $es_formato_viejo) {
                // Recalcular distribución en formato nuevo (diccionario por ID)
                $sql_img = "SELECT id FROM preguntas WHERE id IN ($ids_str) AND imagen IS NOT NULL AND imagen != ''";
                $res_img = $conn->query($sql_img);
                $preguntas_con_imagen_ids = [];
                while ($row = $res_img->fetch_assoc()) {
                    $preguntas_con_imagen_ids[] = (int)$row['id'];
                }
                
                $resultado_dist = calcularDistribucionPuntajes(count($orden_ids), count($preguntas_con_imagen_ids));
                $intento['distribucion_puntajes'] = generarDistribucionConIds($resultado_dist, $orden_ids, $preguntas_con_imagen_ids);
                
                // Actualizar en la BD con el nuevo formato
                $nueva_dist_json = json_encode($intento['distribucion_puntajes']);
                $nueva_dist_safe = $conn->real_escape_string($nueva_dist_json);
                $conn->query("UPDATE resultados_examen SET distribucion_puntajes_json = '$nueva_dist_safe' 
                              WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id");
            } else {
                $intento['distribucion_puntajes'] = $distribucion_guardada;
            }
            
            responder(['success' => true, 'intento' => $intento, 'nuevo' => false]);
        }
        
        // Crear nuevo intento
        // Obtener configuración del examen
        $sql_exam = "SELECT cant_preguntas_mostrar FROM examenes WHERE id = $examen_id";
        $res_exam = $conn->query($sql_exam);
        $exam_data = $res_exam->fetch_assoc();
        $cant = (int)($exam_data['cant_preguntas_mostrar'] ?? 0);
        
        // Obtener preguntas aleatorias CON información de imagen
        if ($cant > 0) {
            $sql_preg = "SELECT id, (imagen IS NOT NULL AND imagen != '') as tiene_imagen 
                         FROM preguntas WHERE examen_id = $examen_id ORDER BY RAND() LIMIT $cant";
        } else {
            $sql_preg = "SELECT id, (imagen IS NOT NULL AND imagen != '') as tiene_imagen 
                         FROM preguntas WHERE examen_id = $examen_id ORDER BY RAND()";
        }
        
        $res_preg = $conn->query($sql_preg);
        $ids = [];
        $ids_con_imagen = [];
        
        while ($p = $res_preg->fetch_assoc()) {
            $ids[] = (int)$p['id'];
            if ($p['tiene_imagen']) {
                $ids_con_imagen[] = (int)$p['id'];
            }
        }
        
        shuffle($ids); // Orden aleatorio de preguntas
        $orden_str = implode(',', $ids);
        
        // Calcular distribución de puntajes considerando imágenes
        $num_preguntas = count($ids);
        $num_con_imagen = count($ids_con_imagen);
        
        $resultado_distribucion = calcularDistribucionPuntajes($num_preguntas, $num_con_imagen);
        $distribucion_puntajes = generarDistribucionConIds($resultado_distribucion, $ids, $ids_con_imagen);
        
        $distribucion_json = json_encode($distribucion_puntajes);
        $distribucion_safe = $conn->real_escape_string($distribucion_json);
        
        // Generar orden aleatorio de opciones para cada pregunta
        $orden_opciones = generarOrdenOpciones($conn, $ids);
        $orden_opciones_json = json_encode($orden_opciones);
        $orden_opciones_safe = $conn->real_escape_string($orden_opciones_json);
        
        $fecha_inicio = date('Y-m-d H:i:s');
        
        // Verificar si existe la columna distribucion_puntajes_json
        $check_col = $conn->query("SHOW COLUMNS FROM resultados_examen LIKE 'distribucion_puntajes_json'");
        if ($check_col && $check_col->num_rows == 0) {
            // Crear la columna si no existe
            $conn->query("ALTER TABLE resultados_examen ADD COLUMN distribucion_puntajes_json TEXT DEFAULT NULL AFTER orden_opciones_json");
        }
        
        // Insertar con la distribución de puntajes
        $sql_insert = "INSERT INTO resultados_examen 
                       (examen_id, estudiante_id, fecha_inicio_real, orden_preguntas, puntaje_total, respuestas_json, orden_opciones_json, distribucion_puntajes_json)
                       VALUES ($examen_id, $estudiante_id, '$fecha_inicio', '$orden_str', -1, '{}', '$orden_opciones_safe', '$distribucion_safe')";
        
        if ($conn->query($sql_insert)) {
            $intento = [
                'id' => $conn->insert_id,
                'examen_id' => $examen_id,
                'estudiante_id' => $estudiante_id,
                'fecha_inicio_real' => $fecha_inicio,
                'orden_preguntas' => $orden_str,
                'puntaje_total' => -1,
                'respuestas_json' => '{}',
                'orden_opciones_json' => $orden_opciones_json,
                'distribucion_puntajes' => $distribucion_puntajes,
                'distribucion_info' => [
                    'total_preguntas' => $num_preguntas,
                    'con_imagen' => $num_con_imagen,
                    'sin_imagen' => $num_preguntas - $num_con_imagen,
                    'descripcion' => $resultado_distribucion['descripcion'] ?? ''
                ]
            ];
            responder(['success' => true, 'intento' => $intento, 'nuevo' => true]);
        } else {
            responder(['success' => false, 'error' => 'Error al crear intento: ' . $conn->error], 500);
        }
        break;
    
    // ----------------------------------------
    // GUARDAR RESPUESTA
    // ----------------------------------------
    case 'guardar_respuesta':
        $examen_id = (int)($datos['examen_id'] ?? 0);
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        $pregunta_id = (int)($datos['pregunta_id'] ?? 0);
        $opcion_id = (int)($datos['opcion_id'] ?? 0);
        $pregunta_num_in = (int)($datos['pregunta_numero'] ?? 0);
        
        if ($examen_id <= 0 || $estudiante_id <= 0 || $pregunta_id <= 0) {
            responder(['success' => false, 'error' => 'Datos incompletos'], 400);
        }
        
        asegurarColumnasProgreso($conn);

        // Obtener JSON actual, orden de preguntas y tiempos de respuesta
        $sql = "SELECT respuestas_json, tiempos_respuestas_json, orden_preguntas, pregunta_actual FROM resultados_examen 
                WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id LIMIT 1";
        $res = $conn->query($sql);
        
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $respuestas = json_decode($row['respuestas_json'] ?? '{}', true) ?? [];
            $tiempos = json_decode($row['tiempos_respuestas_json'] ?? '{}', true) ?? [];
            $ahora_str = date('Y-m-d H:i:s');
            
            if ($opcion_id > 0) {
                $respuestas[$pregunta_id] = $opcion_id;
                $tiempos[$pregunta_id] = $ahora_str; // Registrar hora exacta con minutos y segundos
            } else {
                unset($respuestas[$pregunta_id]);
                unset($tiempos[$pregunta_id]);
            }

            // Calcular número de pregunta actual si no se envió explícitamente
            $pregunta_actual_val = $pregunta_num_in;
            if ($pregunta_actual_val <= 0) {
                $orden_ids = !empty($row['orden_preguntas']) ? explode(',', $row['orden_preguntas']) : [];
                $pos = array_search($pregunta_id, $orden_ids);
                if ($pos !== false) {
                    $pregunta_actual_val = $pos + 1;
                } else {
                    $pregunta_actual_val = (int)($row['pregunta_actual'] ?? 1);
                }
            }
            
            $nuevo_json = json_encode($respuestas);
            $nuevo_tiempos = json_encode($tiempos);
            $nuevo_json_safe = $conn->real_escape_string($nuevo_json);
            $nuevo_tiempos_safe = $conn->real_escape_string($nuevo_tiempos);
            
            $sql_update = "UPDATE resultados_examen 
                           SET respuestas_json = '$nuevo_json_safe',
                               tiempos_respuestas_json = '$nuevo_tiempos_safe',
                               pregunta_actual = $pregunta_actual_val
                           WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id";
            
            if ($conn->query($sql_update)) {
                responder([
                    'success' => true,
                    'respuestas' => $respuestas,
                    'timestamp' => $ahora_str,
                    'hora_exacta' => date('H:i:s', strtotime($ahora_str)),
                    'pregunta_actual' => $pregunta_actual_val
                ]);
            } else {
                responder(['success' => false, 'error' => 'Error al guardar respuesta'], 500);
            }
        } else {
            responder(['success' => false, 'error' => 'Intento no encontrado'], 404);
        }
        break;

    // ----------------------------------------
    // ACTUALIZAR PROGRESO / PREGUNTA ACTUAL EN TIEMPO REAL
    // ----------------------------------------
    case 'actualizar_progreso_estudiante':
        $examen_id = (int)($datos['examen_id'] ?? 0);
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        $preg_actual = (int)($datos['pregunta_actual'] ?? 0);
        
        if ($examen_id > 0 && $estudiante_id > 0 && $preg_actual > 0) {
            asegurarColumnasProgreso($conn);
            $conn->query("UPDATE resultados_examen 
                          SET pregunta_actual = $preg_actual 
                          WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id");
        }
        responder(['success' => true]);
        break;
    
    // ----------------------------------------
    // FINALIZAR EXAMEN (CON DISTRIBUCIÓN DE PUNTAJES)
    // ----------------------------------------
    case 'finalizar_examen':
        $examen_id = (int)($datos['examen_id'] ?? 0);
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        $forzar_cero = (int)($datos['forzar_cero'] ?? 0);
        $motivo_raw = trim((string)($datos['motivo'] ?? ''));
        $motivo = $conn->real_escape_string($motivo_raw);
        $tipo_evento = trim((string)($datos['tipo_evento'] ?? ''));
        $notificar_seguridad = (int)($datos['notificar_seguridad'] ?? 0);
        $es_cierre_salida_video = false;
        if ($notificar_seguridad === 1) $es_cierre_salida_video = true;
        if (!$es_cierre_salida_video && $tipo_evento !== '' && stripos($tipo_evento, 'salida_video') !== false) {
            $es_cierre_salida_video = true;
        }
        if (!$es_cierre_salida_video && $motivo_raw !== '' && preg_match('/(salida de video|pantalla|display externo)/i', $motivo_raw)) {
            $es_cierre_salida_video = true;
        }
        
        if ($examen_id <= 0 || $estudiante_id <= 0) {
            responder(['success' => false, 'error' => 'IDs requeridos'], 400);
        }
        
        // Si se fuerza nota 0 (por violación de seguridad)
        if ($forzar_cero == 1) {
            $fecha_fin = date('Y-m-d H:i:s');
            $sql_update = "UPDATE resultados_examen 
                           SET puntaje_total = 0, fecha_intento = '$fecha_fin' 
                           WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id";
            
            if ($conn->query($sql_update)) {
                if ($es_cierre_salida_video) {
                    $tipo_notif = ($tipo_evento !== '') ? $tipo_evento : 'salida_video_externa';
                    registrarNotificacionSeguridadExamen($conn, $examen_id, $estudiante_id, $motivo_raw, $tipo_notif);
                }
                responder([
                    'success' => true,
                    'nota' => 0,
                    'correctas' => 0,
                    'total' => 0,
                    'mostrar_nota' => true,
                    'motivo' => $motivo
                ]);
            } else {
                responder(['success' => false, 'error' => 'Error al finalizar'], 500);
            }
        }
        
        // Obtener intento, orden de preguntas y distribución de puntajes
        $sql_intento = "SELECT orden_preguntas, respuestas_json, distribucion_puntajes_json FROM resultados_examen 
                        WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id";
        $res_intento = $conn->query($sql_intento);
        
        if (!$res_intento || $res_intento->num_rows == 0) {
            responder(['success' => false, 'error' => 'Intento no encontrado'], 404);
        }
        
        $intento = $res_intento->fetch_assoc();
        $orden_ids = explode(',', $intento['orden_preguntas']);
        $respuestas = json_decode($intento['respuestas_json'], true) ?? [];
        
        // Obtener distribución de puntajes (diccionario {pregunta_id: puntaje})
        $distribucion_puntajes = [];
        if (!empty($intento['distribucion_puntajes_json'])) {
            $distribucion_puntajes = json_decode($intento['distribucion_puntajes_json'], true) ?? [];
        }
        
        // Si no hay distribución guardada, calcularla
        if (empty($distribucion_puntajes)) {
            $ids_str = implode(',', array_map('intval', $orden_ids));
            $sql_img = "SELECT id FROM preguntas WHERE id IN ($ids_str) AND imagen IS NOT NULL AND imagen != ''";
            $res_img = $conn->query($sql_img);
            $preguntas_con_imagen_ids = [];
            while ($row = $res_img->fetch_assoc()) {
                $preguntas_con_imagen_ids[] = (int)$row['id'];
            }
            
            $resultado_dist = calcularDistribucionPuntajes(count($orden_ids), count($preguntas_con_imagen_ids));
            $distribucion_puntajes = generarDistribucionConIds($resultado_dist, $orden_ids, $preguntas_con_imagen_ids);
        }
        
        // Obtener respuestas correctas (por ID de opción, no por posición)
        $ids_str = implode(',', array_map('intval', $orden_ids));
        $sql_correctas = "SELECT p.id, op.id as op_correcta 
                          FROM preguntas p 
                          JOIN opciones_pregunta op ON p.id = op.pregunta_id 
                          WHERE p.id IN ($ids_str) AND op.es_correcta = 1";
        $res_correctas = $conn->query($sql_correctas);
        
        $mapa_correctas = [];
        while ($c = $res_correctas->fetch_assoc()) {
            $mapa_correctas[$c['id']] = $c['op_correcta'];
        }
        
        // Calcular nota usando la distribución de puntajes (diccionario por ID)
        $base = count($orden_ids);
        $puntos_obtenidos = 0;
        $correctas_count = 0;
        
        foreach ($orden_ids as $pid) {
            if (isset($respuestas[$pid]) && isset($mapa_correctas[$pid])) {
                if ($respuestas[$pid] == $mapa_correctas[$pid]) {
                    // Sumar el puntaje específico de esta pregunta usando el ID como clave
                    $pid_str = strval($pid);
                    if (isset($distribucion_puntajes[$pid_str])) {
                        $puntaje_pregunta = $distribucion_puntajes[$pid_str];
                    } elseif (isset($distribucion_puntajes[$pid])) {
                        $puntaje_pregunta = $distribucion_puntajes[$pid];
                    } else {
                        $puntaje_pregunta = 10.0 / $base;
                    }
                    $puntos_obtenidos += $puntaje_pregunta;
                    $correctas_count++;
                }
            }
        }
        
        // Redondear la nota final
        $nota = round($puntos_obtenidos, 2);
        
        // Guardar nota
        $fecha_fin = date('Y-m-d H:i:s');
        $sql_update = "UPDATE resultados_examen 
                       SET puntaje_total = $nota, fecha_intento = '$fecha_fin' 
                       WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id";
        
        if ($conn->query($sql_update)) {
            if ($es_cierre_salida_video) {
                $tipo_notif = ($tipo_evento !== '') ? $tipo_evento : 'salida_video_externa';
                registrarNotificacionSeguridadExamen($conn, $examen_id, $estudiante_id, $motivo_raw, $tipo_notif);
            }
            $sql_ex = "SELECT mostrar_nota, permitir_revision FROM examenes WHERE id = $examen_id";
            $res_ex = $conn->query($sql_ex);
            $config = ($res_ex && $res_ex->num_rows > 0) ? $res_ex->fetch_assoc() : [];
            
            responder([
                'success' => true,
                'nota' => $nota,
                'correctas' => $correctas_count,
                'total' => $base,
                'distribucion_usada' => $distribucion_puntajes,
                'mostrar_nota' => !empty($config['mostrar_nota']),
                'permitir_revision' => !empty($config['permitir_revision'])
            ]);
        } else {
            responder(['success' => false, 'error' => 'Error al finalizar'], 500);
        }
        break;

    // ----------------------------------------
    // NOTIFICAR SEGURIDAD (APLICATIVO)
    // ----------------------------------------
    case 'notificar_seguridad':
        $examen_id = (int)($datos['examen_id'] ?? 0);
        $estudiante_id = (int)($datos['estudiante_id'] ?? 0);
        $motivo = trim((string)($datos['motivo'] ?? ''));
        $tipo = trim((string)($datos['tipo'] ?? 'salida_video_externa'));

        if ($examen_id <= 0 || $estudiante_id <= 0) {
            responder(['success' => false, 'error' => 'IDs requeridos'], 400);
        }

        $ok = registrarNotificacionSeguridadExamen($conn, $examen_id, $estudiante_id, $motivo, $tipo);
        
        // Guardar respuestas si fueron enviadas y mantener puntaje_total = -1 (pausado, no calificado)
        if (!empty($datos['respuestas_json'])) {
            $resp_json = is_string($datos['respuestas_json']) ? $datos['respuestas_json'] : json_encode($datos['respuestas_json']);
            $resp_safe = $conn->real_escape_string($resp_json);
            $conn->query("UPDATE resultados_examen SET respuestas_json = '$resp_safe', puntaje_total = -1 WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id");
        } else {
            $conn->query("UPDATE resultados_examen SET puntaje_total = -1 WHERE examen_id = $examen_id AND estudiante_id = $estudiante_id AND puntaje_total != -1");
        }

        responder([
            'success' => true,
            'pausado' => true,
            'registrado' => $ok,
            'motivo' => $motivo,
            'tipo' => $tipo,
            'error' => $GLOBALS['last_notif_error'] ?? null,
            'curso_id' => obtenerCursoPorExamen($conn, $examen_id)
        ]);
        break;
    
    // ----------------------------------------
    // VERIFICAR CONEXIÓN (PING)
    // ----------------------------------------
    case 'ping':
        responder([
            'success' => true, 
            'message' => 'API funcionando',
            'timestamp' => date('Y-m-d H:i:s'),
            'timezone' => 'America/Guayaquil',
            'version' => '3.5',
            'features' => [
                'opciones_aleatorias' => true,
                'preguntas_aleatorias' => true,
                'puntaje_automatico' => true,
                'distribucion_inteligente' => true,
                'prioridad_preguntas_imagen' => true,
                'distribucion_por_id' => true,
                'verificar_tiempo_real' => true
            ]
        ]);
        break;
    
    // ----------------------------------------
    // ACCIÓN NO VÁLIDA
    // ----------------------------------------
    default:
        responder([
            'success' => false, 
            'error' => 'Acción no válida',
            'acciones_disponibles' => [
                'ping', 'login', 'examenes', 'examen_detalle', 
                'preguntas', 'iniciar_intento', 'guardar_respuesta', 
                'finalizar_examen', 'verificar_tiempo',
                'mensajeria_listar', 'mensajeria_enviar', 'mensajeria_limpiar'
            ]
        ], 400);
}
