<?php
session_start();
include "db.php";

if (!isset($_SESSION["usuario"]) || !in_array($_SESSION["usuario"]["rol"], ['docente','director_carrera','director_area'])) {
    header("Location: index.php");
    exit;
}

$doc_id   = (int)$_SESSION["usuario"]["id"];
$doc_nombre = htmlspecialchars($_SESSION["usuario"]["nombres"]);
$email_doc  = htmlspecialchars($_SESSION["usuario"]["email"] ?? '');

$q_user = $conn->query("SELECT cedula, password, email FROM usuarios WHERE id = $doc_id LIMIT 1");
$datos_user = $q_user ? $q_user->fetch_assoc() : ['cedula'=>'','password'=>'','email'=>''];
$cedula_real   = $datos_user['cedula'] ?? '';
$pass_actual_db = $datos_user['password'] ?? '';
$email_db       = htmlspecialchars($datos_user['email'] ?? $email_doc);

$mensaje_pass = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_clave'])) {
    $actual = $_POST['clave_actual'] ?? '';
    $nueva  = $_POST['nueva_clave'] ?? '';
    $conf   = $_POST['conf_clave'] ?? '';

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
        $conn->query("UPDATE usuarios SET password = '$nueva_hash' WHERE id = $doc_id");
        $pass_actual_db = $nueva_hash;
        $mensaje_pass = "<div class='alert-success'>✅ ¡Contraseña actualizada correctamente!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
<title>Perfil Docente</title>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
    body{margin:0; font-family:'Open Sans', Arial, sans-serif; background:#f5f7fb; color:#1f2937;}
    .navbar{height:60px; background:#fff; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; padding:0 16px; box-shadow:0 1px 3px rgba(0,0,0,0.08);}
    .brand{display:flex; align-items:center; gap:10px; text-decoration:none;}
    .brand img{height:32px;}
    .brand span{font-weight:700; color:#0f6cb6;}
    .container{max-width:1100px; margin:30px auto; padding:0 18px;}
    .welcome-title{margin:0 0 4px; font-size:1.6rem; font-weight:700;}
    .welcome-sub{color:#0f6cb6; font-size:0.95rem; margin:0 0 18px;}
    .card{background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 2px 6px rgba(0,0,0,0.04);}
    .card h3{margin:0 0 12px; font-weight:700; font-size:1.05rem; color:#111827;}
    .field{margin-bottom:12px;}
    .field label{display:block; font-weight:700; font-size:0.9rem; color:#374151; margin-bottom:4px;}
    .field p{margin:0; color:#4b5563;}
    .alert-error{background:#fee2e2; color:#991b1b; padding:10px; border-radius:8px; margin-bottom:12px;}
    .alert-success{background:#dcfce7; color:#166534; padding:10px; border-radius:8px; margin-bottom:12px;}
    input{width:100%; max-width:420px; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; margin-bottom:10px; box-sizing:border-box;}
    button{padding:10px 16px; background:#0f6cb6; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;}
    button:hover{background:#0b5591;}
</style>
</head>
<body>
  <div class="navbar">
    <a class="brand" href="docente.php">
        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6d/LogoUGcolor.png" alt="UG">
        <span>Panel Docente</span>
    </a>
    <a href="logout.php" style="text-decoration:none; color:#d9534f; font-weight:800;">Cerrar sesión</a>
  </div>

  <div class="container">
      <h1 class="welcome-title"><?= $doc_nombre ?></h1>
      <p class="welcome-sub">Perfil de usuario</p>

      <?= $mensaje_pass ?>

      <div class="card">
        <h3>Detalles de usuario</h3>
        <div class="field">
            <label>Dirección de correo</label>
            <p><a href="mailto:<?= $email_db ?>"><?= $email_db ?></a></p>
        </div>
        <div class="field">
            <label>País</label>
            <p>Ecuador</p>
        </div>
        <div class="field">
            <label>Zona horaria</label>
            <p>America/Guayaquil</p>
        </div>
      </div>

      <div class="card">
        <h3>Seguridad</h3>
        <p style="color:#6b7280; font-size:0.92rem;">Cambiar contraseña</p>
        <form method="POST">
            <input type="password" name="clave_actual" placeholder="Contraseña actual" required>
            <input type="password" name="nueva_clave" placeholder="Nueva contraseña" required>
            <input type="password" name="conf_clave" placeholder="Confirmar nueva contraseña" required>
            <button type="submit" name="cambiar_clave">Guardar cambios</button>
        </form>
      </div>
  </div>
</body>
</html>
