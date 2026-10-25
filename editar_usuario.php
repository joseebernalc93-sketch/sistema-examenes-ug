<?php
// --- LÍNEAS DE DEPURACIÓN: MUESTRAN EL ERROR REAL ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --------------------------------------------------

session_start();
include "db.php"; // Asegúrate de que este archivo contiene la conexión $conn

/* ===================================================================
 * 1. VERIFICAR ADMIN Y OBTENER ID DE USUARIO
 * =================================================================== */

if (!isset($_SESSION["usuario"]) || $_SESSION["usuario"]["rol"] != "administrador") {
    if (isset($_GET['api'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'No autorizado']);
        exit;
    }
    header("Location: index.php");
    exit;
}

/* ================= API embebida (para los combos) =================== */
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $api = $_GET['api'];

    if ($api === 'facultades') {
        $q = $conn->query("SELECT id, nombre FROM facultades ORDER BY nombre ASC");
        $rows = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($api === 'carreras') {
        $fid = (int)($_GET['facultad_id'] ?? 0);
        if (!$fid) { echo json_encode(['ok' => false, 'error' => 'facultad_id requerido']); exit; }
        $st = $conn->prepare("SELECT id, nombre FROM carreras WHERE facultad_id=? ORDER BY nombre ASC");
        $st->bind_param('i', $fid);
        $st->execute();
        $res = $st->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($api === 'materias') {
        $cid = (int)($_GET['carrera_id'] ?? 0);
        if (!$cid) { echo json_encode(['ok' => false, 'error' => 'carrera_id requerido']); exit; }
        $st = $conn->prepare("
          SELECT DISTINCT m.id, m.nombre
          FROM materias m
          JOIN semestres s ON s.id = m.semestre_id
          WHERE s.carrera_id = ?
          ORDER BY m.nombre ASC
        ");
        $st->bind_param('i', $cid);
        $st->execute();
        $res = $st->get_result();
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'acción no soportada']);
    exit;
}

if (!isset($_GET["id"])) {
    header("Location: admin.php?seccion=usuarios");
    exit;
}

$id = intval($_GET["id"]);

/* ===================================================================
 * 2. CARGAR DATOS (PARA POBLAR FORMULARIO)
 * =================================================================== */

// --- 2.1. Datos básicos del Usuario ---
$res_usuario = $conn->query("SELECT * FROM usuarios WHERE id = $id");
if (!$res_usuario || $res_usuario->num_rows == 0) {
    die("Error: Usuario con ID $id no encontrado.");
}
$usuario = $res_usuario->fetch_assoc();
$es_estudiante = ($usuario['rol'] === 'estudiante');
$es_docente_rol = in_array($usuario['rol'], ['docente','director_carrera','director_area']);

// --- 2.2. IDs de Facultad/Carrera (necesarios para los <select>) ---
$facultad_id = 0;
$carrera_id  = 0;

if (!empty($usuario['facultad']) && $usuario['facultad'] != 'N/A') {
    $st = $conn->prepare("SELECT id FROM facultades WHERE nombre = ? LIMIT 1");
    $st->bind_param("s", $usuario['facultad']);
    $st->execute();
    $res = $st->get_result();
    if ($res && $res->num_rows) $facultad_id = (int)$res->fetch_assoc()['id'];
}

if (!empty($usuario['carrera']) && $usuario['carrera'] != 'N/A' && $facultad_id > 0) {
    $st = $conn->prepare("SELECT id FROM carreras WHERE nombre = ? AND facultad_id = ? LIMIT 1");
    $st->bind_param("si", $usuario['carrera'], $facultad_id);
    $st->execute();
    $res = $st->get_result();
    if ($res && $res->num_rows) $carrera_id = (int)$res->fetch_assoc()['id'];
}

// --- 2.3. Cursos actuales del usuario (para la vista previa) ---
$user_selections = [];
$query_user_cursos = "
    SELECT co.id AS id_oferta, m.nombre AS materia, p.nombre AS paralelo_nombre
    FROM usuario_cursos uc
    JOIN cursos_oferta co ON uc.curso_id = co.id
    JOIN paralelos p ON co.paralelo_id = p.id
    JOIN materias m ON p.materia_id = m.id
    WHERE uc.usuario_id = $id
";
$res_user_cursos = $conn->query($query_user_cursos);
if ($res_user_cursos) {
    while ($row = $res_user_cursos->fetch_assoc()) {
        $user_selections[] = $row;
    }
}


/* ===================================================================
 * 3. DATOS GLOBALES (IDÉNTICO A REGISTRO.PHP)
 * =================================================================== */

/* ========= Precarga desde BD para fallback ========= */
$facultades_init = [];
$q = $conn->query("SELECT id, nombre FROM facultades ORDER BY nombre ASC");
if ($q) $facultades_init = $q->fetch_all(MYSQLI_ASSOC);

/* Carreras por facultad */
$carreras_by_fac = [];
$q = $conn->query("SELECT id, nombre, facultad_id FROM carreras ORDER BY nombre ASC");
if ($q) while ($r = $q->fetch_assoc()) {
  $fid = (int)$r['facultad_id'];
  $carreras_by_fac[$fid][] = ['id' => (int)$r['id'], 'nombre' => $r['nombre']];
}

/* Materias por carrera (vía semestres) */
$materias_by_car = [];
$q = $conn->query("
  SELECT DISTINCT m.id, m.nombre, s.carrera_id
  FROM materias m
  JOIN semestres s ON s.id = m.semestre_id
  ORDER BY m.nombre ASC
");
if ($q) while ($r = $q->fetch_assoc()) {
  $cid = (int)$r['carrera_id'];
  $materias_by_car[$cid][] = ['id' => (int)$r['id'], 'nombre' => $r['nombre']];
}



/* =========== Ofertas para el múltiple + paralelos ocupados =========== */

/* 3.1 Paralelos ya ocupados por OTRO docente/director (pend/aceptado) */
$sql_ocupados = "
  SELECT uc.curso_id
  FROM usuario_cursos uc
  JOIN usuarios u ON u.id = uc.usuario_id
  WHERE u.rol IN ('docente','director_carrera','director_area')
    AND u.estado IN ('pendiente','aceptado')
    AND u.id <> $id
";
$resOcc = $conn->query($sql_ocupados);
$ocupados_docente = [];
if ($resOcc) {
  while ($r = $resOcc->fetch_assoc()) {
    $ocupados_docente[(int)$r['curso_id']] = true;
  }
}

/* 3.2 Todas las ofertas */
$sql_ofertas = "
SELECT 
  co.id AS id_oferta, ci.nombre AS ciclo, s.numero AS semestre,
  m.nombre AS materia, p.nombre AS paralelo,
  ca.nombre AS carrera, f.nombre AS facultad
FROM cursos_oferta co
JOIN paralelos   p  ON co.paralelo_id = p.id
JOIN materias    m  ON p.materia_id   = m.id
JOIN semestres   s  ON m.semestre_id  = s.id
JOIN carreras    ca ON s.carrera_id   = ca.id
JOIN facultades  f  ON ca.facultad_id = f.id
JOIN ciclos      ci ON co.ciclo_id    = ci.id
ORDER BY s.numero, m.nombre, p.nombre
";
$res = $conn->query($sql_ofertas);
$todas_las_ofertas = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

/* Mapas para validación “1 paralelo por materia” */
$oferta_id_a_materia = [];
foreach ($todas_las_ofertas as $o) $oferta_id_a_materia[(int)$o['id_oferta']] = $o['materia'];

/* ===================================================================
 * 4. LÓGICA DE GUARDADO (POST)
 * =================================================================== */
$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  /* === NUEVO: rol base + cargo_docente => rol_final === */
  $rol_base    = $_POST['rol'] ?? 'estudiante';   // 'estudiante' o 'docente'
  $cargo_extra = $_POST['cargo_docente'] ?? 'ninguno';

  $rol_final = $rol_base;
  if ($rol_base === 'docente') {
      if ($cargo_extra === 'director_carrera')       $rol_final = 'director_carrera';
      elseif ($cargo_extra === 'director_area')      $rol_final = 'director_area';
      else                                           $rol_final = 'docente'; // ninguno
  }

  $rol        = $rol_final; // para no cambiar el resto del código
  $nombres    = $conn->real_escape_string($_POST['nombres'] ?? '');
  $cedula     = $conn->real_escape_string($_POST['cedula'] ?? '');
  $email      = $conn->real_escape_string($_POST['email'] ?? '');
  $password_raw = $_POST['password'] ?? '';
  $ofertas    = isset($_POST['cursos_ids']) ? array_map('intval', $_POST['cursos_ids']) : [];

  $err = "";
  if (!preg_match('/^[0-9]{10}$/', $cedula)) $err = "❌ Cédula de 10 dígitos.";
  elseif (!preg_match('/^[A-Za-z0-9._%+-]+@ug\.edu\.ec$/', $email)) $err = "❌ Use su correo @ug.edu.ec.";
  elseif (!$ofertas) $err = "❌ Seleccione al menos un paralelo.";

  /* Tomar facultad/carrera según rol base (estudiante vs docente/director) */
  if ($rol_base === 'estudiante') {
    $facultad_id_post = (int)($_POST['facultad'] ?? 0);
    $carrera_id_post  = (int)($_POST['carrera']  ?? 0);
  } else {
    $facultad_id_post = (int)($_POST['facultad_doc'] ?? 0);
    $carrera_id_post  = (int)($_POST['carrera_doc']  ?? 0);
  }

  $facultad = 'N/A'; $carrera = 'N/A';
  if ($facultad_id_post) {
    $r = $conn->query("SELECT nombre FROM facultades WHERE id=".$facultad_id_post);
    if ($r && $r->num_rows) $facultad = $conn->real_escape_string($r->fetch_assoc()['nombre']);
  }
  if ($carrera_id_post) {
    $r = $conn->query("SELECT nombre FROM carreras WHERE id=".$carrera_id_post);
    if ($r && $r->num_rows) $carrera = $conn->real_escape_string($r->fetch_assoc()['nombre']);
  }

  /* 1 paralelo por materia (servidor) - SOLO APLICA A ESTUDIANTES */
  if ($err === "" && $rol === 'estudiante') {
    $vistas = [];
    foreach ($ofertas as $oid) {
      $m = $oferta_id_a_materia[$oid] ?? null;
      if ($m) {
        if (in_array($m, $vistas)) { $err = "❌ No puede elegir dos paralelos de la misma materia ($m)."; break; }
        $vistas[] = $m;
      }
    }
  }

  /* DUPLICADOS POR ROL (Excluyendo al usuario actual) */
  if ($err === "") {
    $rolEsc = $conn->real_escape_string($rol);
    if ($conn->query("SELECT id FROM usuarios WHERE cedula='$cedula' AND rol='$rolEsc' AND id != $id")->num_rows) $err = "❌ Cédula ya registrada para este rol en otro usuario.";
    elseif ($conn->query("SELECT id FROM usuarios WHERE email='$email' AND rol='$rolEsc' AND id != $id")->num_rows) $err = "❌ Correo ya registrado para este rol en otro usuario.";
  }

  /* VALIDACIÓN DOCENTE/DIRECTOR: paralelos únicos para cada docente */
  if ($err === "" && $rol !== 'estudiante' && $ofertas) {
    $ofertas_str = implode(',', $ofertas);
    $sql_check_docente = "
        SELECT u.nombres, u.cedula, m.nombre AS materia, p.nombre AS paralelo
        FROM usuario_cursos uc
        JOIN usuarios u       ON u.id = uc.usuario_id
        JOIN cursos_oferta co ON co.id = uc.curso_id
        JOIN paralelos p      ON p.id = co.paralelo_id
        JOIN materias m       ON m.id = p.materia_id
        WHERE uc.curso_id IN ($ofertas_str)
          AND u.rol IN ('docente','director_carrera','director_area')
          AND u.estado IN ('pendiente','aceptado')
          AND u.id <> $id
    ";
    $res_check = $conn->query($sql_check_docente);
    if ($res_check && $res_check->num_rows > 0) {
        $taken = $res_check->fetch_assoc();
        $err = "❌ El paralelo <strong>{$taken['paralelo']}</strong> ({$taken['materia']}) ya está asignado al docente {$taken['nombres']} ({$taken['cedula']}).";
    }
  }

  if ($err !== "") {
    $mensaje = "<p style='color:#b3261e;'>$err</p>";
  } else {
    
    // --- LÓGICA DE UPDATE ---
    $conn->begin_transaction();
    try {
        // 1. Actualizar Password (si se proporcionó uno nuevo)
        $set_password = "";
        if (!empty($password_raw)) {
            $set_password = ", password='" . md5($password_raw) . "'";
        }

        // 2. Actualizar datos de usuario
        $sql = "UPDATE usuarios SET
                    nombres='$nombres',
                    cedula='$cedula',
                    facultad='$facultad',
                    carrera='$carrera',
                    email='$email',
                    rol='$rol'
                    $set_password
                WHERE id = $id";
        
        if (!$conn->query($sql)) {
            throw new Exception("Error al actualizar usuario: " . $conn->error);
        }

        // 3. Borrar cursos antiguos
        $conn->query("DELETE FROM usuario_cursos WHERE usuario_id = $id");

        // 4. Insertar cursos nuevos (si hay)
        if ($ofertas) {
            $vals = [];
            foreach ($ofertas as $oid) $vals[] = "($id,$oid)";
            $conn->query("INSERT INTO usuario_cursos(usuario_id,curso_id) VALUES ".implode(',', $vals));
        }
        
        $conn->commit();
        header("Location: admin.php?seccion=usuarios&editado=1"); // Redirigir con éxito
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $mensaje = "<p style='color:#b3261e;'>❌ Error: ".$e->getMessage()."</p>";
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><title>Editar Usuario</title>
<link rel="icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body { font-family: 'Open Sans', Arial, sans-serif; background: #f2f2f2; display: flex; justify-content: center; align-items: flex-start; min-height: 100vh; margin: 0; padding: 30px 20px; color: #373a3c; }
.container { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); width: 100%; max-width: 650px; border: 1px solid #e1e1e1; margin-top: 20px; border-top: 5px solid #0f6cb6; }
.container img { width: 80px; display: block; margin: 0 auto 15px; }
h2 { margin-bottom: 25px; font-size: 1.35rem; color: #0f6cb6; text-align: center; font-weight: 700; letter-spacing: -0.5px; }

input[type=text], input[type=email], input[type=password], select {
    width: 100%; padding: 10px 12px; margin: 8px 0; border: 1px solid #ced4da; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #fff; transition: border-color 0.2s;
}
input:focus, select:focus { border-color: #0f6cb6; outline: none; box-shadow: 0 0 0 3px rgba(15,108,182,0.1); }

select[multiple] { height: 180px; background: #fcfcfc; border: 1px solid #ced4da; padding: 8px; }

button { width: 100%; padding: 12px; background: #0f6cb6; color: #fff; border: none; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; transition: 0.2s; margin-top: 20px; }
button:hover { background: #0b5591; }

label { font-size: 13px; font-weight: 700; color: #334155; display: block; margin-top: 15px; margin-bottom: 4px; }

.form-section { margin-top: 20px; border-top: 1px solid #edf2f7; padding-top: 20px; }

.rol-selector { display: flex; gap: 10px; margin-bottom: 20px; }
.rol-button { flex: 1; padding: 10px; border: 1px solid #dcebf7; border-radius: 8px; cursor: pointer; font-weight: 600; color: #556; text-align: center; background: #f8fafc; transition: all 0.2s; }
.rol-button:hover { background: #eef6fc; }
.rol-button.active { background: #0f6cb6; border-color: #0f6cb6; color: #fff; box-shadow: 0 2px 4px rgba(15,108,182,0.25); }

.help { font-size: 12px; color: #64748b; margin-top: 2px; }
.warn { color: #dc3545; font-size: 12px; margin-top: 2px; font-weight: 600; }

/* === PREVIEW MEJORADA (ESTÉTICA) === */
.preview {
    margin-top: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    overflow: hidden;
}
.preview h4 {
    margin: 0;
    padding: 10px 15px;
    font-size: 13px;
    background: #eff6ff;
    color: #1e3a8a;
    font-weight: 700;
    border-bottom: 1px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.preview ul {
    list-style: none;
    margin: 0;
    padding: 0;
}
.preview li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    border-bottom: 1px solid #f1f5f9;
    background: #fff;
    transition: background 0.15s;
}
.preview li:last-child { border-bottom: none; }
.preview li:hover { background: #fcfcfc; }

.preview .tag {
    font-weight: 700;
    color: #0f6cb6;
    margin-right: 6px;
}
.preview .details {
    color: #475569;
    font-size: 13px;
}

.preview button.remove {
    background: transparent;
    color: #ef4444;
    border: 1px solid #fecaca;
    border-radius: 6px;
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    margin: 0;
    width: auto;
    transition: all 0.2s;
}
.preview button.remove:hover {
    background: #fee2e2;
    border-color: #ef4444;
    color: #b91c1c;
}
.preview .empty {
    color: #94a3b8;
    font-style: italic;
    text-align: center;
    padding: 20px;
    font-size: 13px;
    display: block;
}
/* ================================== */

.footer-links { margin-top: 25px; text-align: center; }
.footer-links a { color: #64748b; text-decoration: none; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px; }
.footer-links a:hover { color: #0f6cb6; }
</style>
</head>
<body>
<div class="container">
  <img src="https://upload.wikimedia.org/wikipedia/commons/6/6d/LogoUGcolor.png" alt="UG Logo">
  <h2>✏️ Editar Usuario</h2>

  <div class="rol-selector">
    <div class="rol-button <?= $es_estudiante ? 'active' : '' ?>" id="btnEstudiante" <?= $es_docente_rol ? 'style="display:none"' : '' ?>>Estudiante</div>
    <div class="rol-button <?= $es_docente_rol ? 'active' : '' ?>" id="btnDocente" <?= $es_estudiante ? 'style="display:none"' : '' ?>>Docente / Director</div>
  </div>

  <form method="POST" id="registroForm">
    <!-- rol base: estudiante / docente -->
    <input type="hidden" name="rol" id="rolInput" value="<?= $es_estudiante ? 'estudiante' : 'docente' ?>">

    <div style="display:flex; gap:15px;">
        <div style="flex:1;">
            <label style="margin-top:0;">Apellidos y Nombres:</label>
            <input type="text" name="nombres" value="<?= htmlspecialchars($usuario['nombres']) ?>" required>
        </div>
        <div style="flex:1;">
            <label style="margin-top:0;">Cédula:</label>
            <input type="text" id="cedula" name="cedula" value="<?= htmlspecialchars($usuario['cedula']) ?>" required>
            <div id="cedulaError" class="warn"></div>
        </div>
    </div>

    <label>Correo Institucional (@ug.edu.ec):</label>
    <input type="email" id="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required>
    <div id="emailError" class="warn"></div>

    <label>Contraseña (Opcional):</label>
    <div style="position:relative; width:100%;">
        <input type="password" id="edit_password" name="password" placeholder="Dejar vacío para mantener la actual" style="padding-right:42px;">
        <button type="button" onclick="toggleEditPass(this)" title="Mostrar/ocultar contraseña" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:#64748b; cursor:pointer; padding:0; display:flex; align-items:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
        </button>
    </div>

    <!-- SECCIÓN ESTUDIANTE -->
    <div id="form-estudiante" class="form-section" style="display:<?= $es_estudiante ? 'block' : 'none' ?>">
      <label>Facultad:</label>
      <select name="facultad" id="facultadEstudiante" required>
        <option value="">— Seleccione Facultad —</option>
        <?php foreach ($facultades_init as $f): ?>
          <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['nombre']) ?></option>
        <?php endforeach; ?>
      </select>

      <label>Carrera:</label>
      <select name="carrera" id="carreraEstudiante" required disabled>
        <option value="">— Seleccione Carrera —</option>
      </select>

      <label>Materia (Filtro):</label>
      <select id="materiaEstudiante" disabled>
        <option value="">— Seleccione Materia —</option>
      </select>
    </div>

    <!-- SECCIÓN DOCENTE -->
    <div id="form-docente" class="form-section" style="display:<?= $es_estudiante ? 'none' : 'block' ?>">
      <label style="color:#0f6cb6;">Cargo Directivo (Opcional):</label>
      <select name="cargo_docente" id="cargoDocente" style="background:#f0f9ff; border-color:#b8d4ea;">
        <option value="ninguno">Ninguno (Solo Docente)</option>
        <option value="director_carrera">Director de Carrera</option>
        <option value="director_area">Director de Área</option>
      </select>

      <label>Facultad (Filtro):</label>
      <select name="facultad_doc" id="facultadDocente">
        <option value="">— Seleccione Facultad —</option>
        <?php foreach ($facultades_init as $f): ?>
          <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['nombre']) ?></option>
        <?php endforeach; ?>
      </select>

      <label>Carrera (Filtro):</label>
      <select name="carrera_doc" id="carreraDocente" disabled>
        <option value="">— Seleccione Carrera —</option>
      </select>

      <label>Materia (Filtro):</label>
      <select id="materiaDocente" disabled>
        <option value="">— Seleccione Materia —</option>
      </select>
    </div>

    <!-- SECCIÓN MATERIAS -->
    <div class="form-section">
      <label>Seleccionar Paralelos:</label>
      <select id="cursosSelect" multiple></select>
      <div class="help" style="margin-bottom:10px;">💡 Use <b>Ctrl</b> + <b>Click</b> para seleccionar múltiples opciones.</div>

      <div class="preview" id="previewBox">
        <h4>📋 Materias Seleccionadas</h4>
        <ul id="previewList">
            <li class="empty">No hay materias asignadas aún.</li>
        </ul>
      </div>
      
      <div id="hiddenSelected"></div>
      <div id="apiWarn" class="warn" style="display:none"></div>
    </div>

    <button type="submit">💾 Guardar Cambios</button>
  </form>

  <div class="mensaje"><?= $mensaje ?></div>
  
  <div class="footer-links">
    <a href="admin.php?seccion=usuarios">⬅️ Volver al listado de usuarios</a>
  </div>
</div>

<script>
/* ====== Datos embebidos para fallback ====== */
window.DB_MAPS = {
  carrerasByFacultad: <?= json_encode($carreras_by_fac, JSON_UNESCAPED_UNICODE) ?>,
  materiasByCarrera:  <?= json_encode($materias_by_car, JSON_UNESCAPED_UNICODE) ?>
};

/* ====== DATOS INICIALES DEL USUARIO (PARA PRE-POBLAR) ====== */
window.INITIAL_SELECTIONS = <?= json_encode($user_selections, JSON_UNESCAPED_UNICODE) ?>;
window.INITIAL_USER = <?= json_encode([
    'rol' => $usuario['rol'], 
    'facultad_id' => $facultad_id, 
    'carrera_id' => $carrera_id
], JSON_UNESCAPED_UNICODE) ?>;


/* ====== Validaciones rápidas ====== */
const email = document.getElementById('email'), ced = document.getElementById('cedula');
document.getElementById('emailError').textContent=''; document.getElementById('cedulaError').textContent='';
email.addEventListener('input',()=>{ const ok=/^[A-Za-z0-9._%+-]+@ug\.edu\.ec$/.test(email.value||""); document.getElementById('emailError').textContent=(email.value&&!ok)?'❌ Use su correo @ug.edu.ec':''; });
ced.addEventListener('input',()=>{ const ok=/^[0-9]{10}$/.test(ced.value||""); document.getElementById('cedulaError').textContent=(ced.value&&!ok)?'❌ Cédula de 10 dígitos':''; });

/* ====== Toggle de rol ====== */
const btnEst = document.getElementById('btnEstudiante');
const btnDoc = document.getElementById('btnDocente');
const rolInput = document.getElementById('rolInput');
const secEst = document.getElementById('form-estudiante');
const secDoc = document.getElementById('form-docente');

const estFac = document.getElementById('facultadEstudiante');
const estCar = document.getElementById('carreraEstudiante');
const estMat = document.getElementById('materiaEstudiante');

const docFac = document.getElementById('facultadDocente');
const docCar = document.getElementById('carreraDocente');
const docMat = document.getElementById('materiaDocente');
const cargoDocente = document.getElementById('cargoDocente');
const initialBaseRole = (window.INITIAL_USER && window.INITIAL_USER.rol === 'estudiante') ? 'estudiante' : 'docente';
const roleLocked = true; // en edición no se permite cambiar de rol

if (roleLocked){
  if (initialBaseRole === 'estudiante') { btnDoc.style.display = 'none'; }
  else { btnEst.style.display = 'none'; }
}

function clearSelect(sel, placeholder){
  if(!sel) return;
  sel.innerHTML='';
  const o0=document.createElement('option'); o0.value=''; o0.textContent=placeholder||'— Seleccione —';
  sel.appendChild(o0);
  sel.value='';
  sel.disabled = true;
}

function setRol(newRolBase){
  if (roleLocked && newRolBase !== initialBaseRole) return;
  const isEst = (newRolBase === 'estudiante');
  rolInput.value = newRolBase; // rol base: estudiante / docente

  btnEst.classList.toggle('active', isEst);
  btnDoc.classList.toggle('active', !isEst);
  secEst.style.display = isEst ? 'block' : 'none';
  secDoc.style.display = isEst ? 'none'  : 'block';

  estFac.disabled = !isEst; estCar.disabled = !isEst; estMat.disabled = !isEst;
  estFac.toggleAttribute('required', isEst);
  estCar.toggleAttribute('required', isEst);

  docFac.disabled = isEst;  docCar.disabled = isEst;  docMat.disabled = isEst;

  renderMultiple();
  updatePreview();
}
btnEst.addEventListener('click',()=>setRol('estudiante'));
btnDoc.addEventListener('click',()=>setRol('docente'));

/* ====== API helper con aviso y fallback ====== */
const API_BASE = (()=>{const u=new URL(location.href); u.search=''; u.hash=''; return u.toString();})();
const apiWarn = document.getElementById('apiWarn');
async function api(q){
  try{
    const r=await fetch(API_BASE+'?'+q,{cache:'no-store'});
    const j=await r.json();
    if(!j.ok) throw new Error(j.error||'No OK');
    if(apiWarn) apiWarn.style.display='none';
    return j.data;
  }catch(e){
    console.warn('API fallback',e);
    if(apiWarn) apiWarn.style.display='none';
    return null;
  }
}
function fillSelect(sel, rows, placeholder){
  sel.innerHTML='';
  const o0=document.createElement('option'); o0.value=''; o0.textContent=placeholder; sel.appendChild(o0);
  (rows||[]).forEach(r=>{const o=document.createElement('option'); o.value=r.id; o.textContent=r.nombre; sel.appendChild(o);});
  sel.disabled = !(rows && rows.length);
}

/* ====== Cascada para cada rol ====== */
function bindCascade(facId, carId, matId){
  const fac=document.getElementById(facId), car=document.getElementById(carId), mat=document.getElementById(matId);

  fac.addEventListener('change', async ()=>{
    const fid = fac.value;
    let rows = fid ? await api('api=carreras&facultad_id='+encodeURIComponent(fid)) : [];
    if (rows===null) rows = (window.DB_MAPS.carrerasByFacultad[fid]||[]);
    fillSelect(car, rows, '— Seleccione Carrera —');
    fillSelect(mat, [], '— Seleccione Materia —');
    renderMultiple(); updatePreview();
  });

  car.addEventListener('change', async ()=>{
    const cid = car.value;
    let rows = cid ? await api('api=materias&carrera_id='+encodeURIComponent(cid)) : [];
    if (rows===null) rows = (window.DB_MAPS.materiasByCarrera[cid]||[]);
    fillSelect(mat, rows, '— Seleccione Materia —');
    renderMultiple(); updatePreview();
  });

  mat.addEventListener('change', ()=>{ renderMultiple(); updatePreview(); });
}
bindCascade('facultadEstudiante','carreraEstudiante','materiaEstudiante');
bindCascade('facultadDocente','carreraDocente','materiaDocente');

/* ====== CACHE de todas las opciones (para filtrar por materia) ====== */
const ALL = <?php
  $rows = [];
  foreach ($todas_las_ofertas as $o) {
    $id_oferta = (int)$o['id_oferta'];
    $rows[] = [
      'value'           => (string)$id_oferta,
      'text'            => $o['paralelo'],
      'materia'         => $o['materia'],
      'carrera'         => $o['carrera'],
      'facultad'        => $o['facultad'],
      'sem'             => (int)$o['semestre'],
      'ciclo'           => $o['ciclo'],
      'group'           => $o['materia']." (Semestre: ".$o['semestre']." | Ciclo: ".$o['ciclo'].")",
      'ocupado_docente' => !empty($ocupados_docente[$id_oferta])
    ];
  }
  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
?>;

/* ====== Estado persistente de selecciones ====== */
const selectedMap = new Map(); // Clave ahora es ID del curso, no nombre de materia
const hiddenBox   = document.getElementById('hiddenSelected');
const previewList = document.getElementById('previewList');
const multi       = document.getElementById('cursosSelect');

function syncHiddenInputs(){
  hiddenBox.innerHTML = '';
  for (const {id} of selectedMap.values()){
    const inp = document.createElement('input');
    inp.type='hidden'; inp.name='cursos_ids[]'; inp.value=id;
    hiddenBox.appendChild(inp);
  }
}

function updatePreview(){
  previewList.innerHTML='';
  if (selectedMap.size===0){
    const li=document.createElement('li'); li.className='empty'; li.textContent='No hay materias asignadas aún.';
    previewList.appendChild(li); return;
  }
  for (const [idKey, info] of selectedMap.entries()){
    const li=document.createElement('li');
    
    // Contenedor de info
    const infoDiv = document.createElement('div');
    infoDiv.innerHTML = `<span class="tag">${info.materia}</span> <span class="details">${info.text}</span>`;
    
    const btn=document.createElement('button');
    btn.type='button'; btn.className='remove'; btn.textContent='Quitar';
    btn.addEventListener('click', ()=>{
      selectedMap.delete(idKey);
      syncHiddenInputs();
      updatePreview();
      // Actualizar visualmente si la opción está visible en el select actual
      const opt = multi.querySelector(`option[value="${idKey}"]`);
      if (opt) opt.selected = false;
    });
    
    li.appendChild(infoDiv); 
    li.appendChild(btn);
    previewList.appendChild(li);
  }
}

function getCurrentMateriaText(){
  const roleBase = document.getElementById('rolInput').value;
  const matSel = (roleBase==='estudiante') ? document.getElementById('materiaEstudiante') : document.getElementById('materiaDocente');
  if (!matSel || !matSel.value) return '';
  return (matSel.options[matSel.selectedIndex]?.text||'').trim();
}

function renderMultiple(){
  const roleBase = document.getElementById('rolInput').value;
  const facSel = (roleBase==='estudiante') ? document.getElementById('facultadEstudiante') : document.getElementById('facultadDocente');
  const carSel = (roleBase==='estudiante') ? document.getElementById('carreraEstudiante')  : document.getElementById('carreraDocente');
  const matSel = (roleBase==='estudiante') ? document.getElementById('materiaEstudiante')  : document.getElementById('materiaDocente');

  if (!facSel.value || !carSel.value || !matSel.value){ multi.innerHTML=''; return; }

  const fTxt=(facSel.options[facSel.selectedIndex]?.text||'').toUpperCase();
  const cTxt=(carSel.options[carSel.selectedIndex]?.text||'').toUpperCase();
  const mTxt=(matSel.options[matSel.selectedIndex]?.text||'').toUpperCase();

  const filtered = ALL.filter(it =>
    (it.facultad||'').toUpperCase()===fTxt &&
    (it.carrera||'').toUpperCase()===cTxt &&
    (it.materia||'').toUpperCase()===mTxt
  );

  multi.innerHTML='';
  if (!filtered.length){
    const o=document.createElement('option'); o.disabled=true; o.textContent='— No hay paralelos para esta materia —'; multi.appendChild(o);
    return;
  }

  const og=document.createElement('optgroup');
  og.setAttribute('label', filtered[0].group);
  
  filtered.forEach(it=>{
    const o=document.createElement('option');
    o.value=it.value; 
    o.textContent=it.text;
    o.setAttribute('data-materia', it.materia); // Guardamos nombre para el mapa

    // Check by ID now
    if (selectedMap.has(it.value)) o.selected=true;

    // Si el rol base NO es estudiante y el paralelo está ocupado, bloquear
    if (roleBase !== 'estudiante' && it.ocupado_docente) {
      // PERO si el usuario actual YA tiene ese curso asignado, NO bloquearlo (es suyo)
      if (!selectedMap.has(it.value)) {
          o.disabled = true;
          o.textContent = it.text + ' (ocupado por otro docente)';
      }
    }

    og.appendChild(o);
  });
  multi.appendChild(og);
}

multi.addEventListener('change', ()=>{
  const role = document.getElementById('rolInput').value;
  const opts = [...multi.options];
  const selectedOpts = opts.filter(o => o.selected);

  // Si es estudiante, forzar selección única
  if (role === 'estudiante') {
     if (selectedOpts.length > 1) {
         const keep = selectedOpts[selectedOpts.length - 1];
         opts.forEach(o => {
             if (o !== keep) o.selected = false;
         });
     }
  }

  // Sincronizar UI -> Mapa
  // Solo afectamos las opciones visibles en este select (que pertenecen a la materia actual)
  opts.forEach(o => {
      if (o.disabled) return;
      if (o.selected) {
          // Agregar al mapa usando ID como clave
          selectedMap.set(o.value, {
              id: o.value,
              materia: o.getAttribute('data-materia'),
              text: o.textContent.replace(' (ocupado por otro docente)','').trim()
          });
      } else {
          // Si está desmarcado en la UI, borrar del mapa
          selectedMap.delete(o.value);
      }
  });

  syncHiddenInputs();
  updatePreview();
});

/* ====== INICIALIZACIÓN (MODIFICADO PARA EDITAR) ====== */

async function preselectCascade(rolBase, fid, cid){
    const facSel = (rolBase === 'estudiante') ? estFac : docFac;
    const carSel = (rolBase === 'estudiante') ? estCar : docCar;
    const matSel = (rolBase === 'estudiante') ? estMat : docMat;

    if (fid) {
      facSel.value = fid;
      let rows = await api('api=carreras&facultad_id=' + fid);
      if (rows === null) rows = (window.DB_MAPS.carrerasByFacultad[fid] || []);
      fillSelect(carSel, rows, '— Seleccione Carrera —');
    }
    
    if (cid) {
      carSel.value = cid;
      let rows = await api('api=materias&carrera_id=' + cid);
      if (rows === null) rows = (window.DB_MAPS.materiasByCarrera[cid] || []);
      fillSelect(matSel, rows, '— Seleccione Materia —');
    }
}

async function init(){
  const initialSelections = window.INITIAL_SELECTIONS || [];
  const initialUser = window.INITIAL_USER || { rol: 'estudiante', facultad_id: 0, carrera_id: 0 };

  // 1. Pre-poblar el mapa de selecciones usando ID como clave
  initialSelections.forEach(sel => {
    // Convertir ID a string para consistencia con valores de option
    const idKey = String(sel.id_oferta);
    selectedMap.set(idKey, {
        id: idKey, 
        materia: sel.materia,
        text: sel.paralelo_nombre
    });
  });

  // 2. Rol base: si es estudiante queda igual, si es docente/director se trata como "docente"
  const baseRol = (initialUser.rol === 'estudiante') ? 'estudiante' : 'docente';
  setRol(baseRol);

  // 3. Preseleccionar cargo directivo según el rol actual
  if (initialUser.rol === 'director_carrera') {
      cargoDocente.value = 'director_carrera';
  } else if (initialUser.rol === 'director_area') {
      cargoDocente.value = 'director_area';
  } else {
      cargoDocente.value = 'ninguno'; 
  }

  // 4. Cargar y preseleccionar los menús de facultad y carrera
  await preselectCascade(baseRol, initialUser.facultad_id, initialUser.carrera_id);
   
  // 5. Renderizar todo
  renderMultiple();
  updatePreview();
  syncHiddenInputs();
}

function toggleEditPass(btn) {
    const input = document.getElementById('edit_password');
    const svg = btn.querySelector('svg');
    if (!input || !svg) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    if (isPassword) {
        svg.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
    } else {
        svg.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
    }
}

// Ejecutar la inicialización
init();
</script>
</body>
</html>
