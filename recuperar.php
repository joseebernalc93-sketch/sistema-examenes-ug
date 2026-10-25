<?php
session_start();
include "db.php";

$mensaje = "";
$mostrarFormPassword = false;
$cedulaIngresada = "";
$nombresUsuario = "";

// Paso 1: Verificar la cédula
if (isset($_POST["verificar_cedula"])) {
    $cedulaIngresada = $_POST["cedula"];
    $sql = "SELECT * FROM usuarios WHERE cedula='$cedulaIngresada'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $usuario = $result->fetch_assoc();
        $nombresUsuario = $usuario["nombres"];
        $mostrarFormPassword = true; // Mostrar formulario de nueva contraseña
    } else {
        $mensaje = "<p style='color:red'>❌ Usuario no registrado con esa cédula.</p>";
    }
}

// Paso 2: Actualizar contraseña
if (isset($_POST["actualizar_password"])) {
    $cedulaIngresada = $_POST["cedula"];
    $nuevaPassword = $_POST["nueva_password"];
    $confirmarPassword = $_POST["confirmar_password"];

    if ($nuevaPassword !== $confirmarPassword) {
        $mensaje = "<p style='color:red'>❌ Las contraseñas no coinciden.</p>";
        $mostrarFormPassword = true; 
    } else {
        $nuevaPasswordHash = md5($nuevaPassword); // ⚠️ Usa password_hash en producción
        $update = "UPDATE usuarios SET password='$nuevaPasswordHash' WHERE cedula='$cedulaIngresada'";

        if ($conn->query($update) === TRUE) {
            $mensaje = "<p style='color:green'>✅ Contraseña actualizada con éxito. Ahora puedes <a href='index.php'>iniciar sesión</a>.</p>";
        } else {
            $mensaje = "<p style='color:red'>❌ Error al actualizar: " . $conn->error . "</p>";
        }
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
    <title>Recuperar Contraseña</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f9ff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
            width: 380px;
            text-align: center;
        }
        .container img {
            width: 120px;
            margin-bottom: 15px;
        }
        h2 {
            margin-bottom: 20px;
            font-size: 18px;
            color: #003366;
        }
        input {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 14px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #004aad;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover {
            background: #003580;
        }
        .mensaje {
            margin-top: 15px;
            font-size: 14px;
        }
        a {
            display: block;
            margin-top: 15px;
            color: #004aad;
            text-decoration: none;
            font-size: 14px;
        }
        a:hover {
            text-decoration: underline;
        }
        .usuario-info {
            background: #eaf2ff;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            font-size: 14px;
            color: #003366;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Logo -->
        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6d/LogoUGcolor.png" alt="Logo UG">
        <h2>Recuperar Contraseña</h2>

        <?php if (!$mostrarFormPassword): ?>
            <!-- Formulario para verificar cédula -->
            <form method="POST">
                <input type="text" name="cedula" placeholder="Ingrese su cédula registrada" required>
                <button type="submit" name="verificar_cedula">Verificar</button>
            </form>
        <?php else: ?>
            <!-- Mostrar nombre del usuario -->
            <div class="usuario-info">
                👤 Usuario: <?= $nombresUsuario ?>
            </div>

            <!-- Formulario para nueva contraseña -->
            <form method="POST">
                <input type="hidden" name="cedula" value="<?= $cedulaIngresada ?>">
                <input type="password" name="nueva_password" placeholder="Nueva contraseña" required>
                <input type="password" name="confirmar_password" placeholder="Confirmar contraseña" required>
                <button type="submit" name="actualizar_password">Actualizar Contraseña</button>
            </form>
        <?php endif; ?>

        <div class="mensaje"><?= $mensaje ?></div>
        <a href="index.php">🔙 Volver al login</a>
    </div>
</body>
</html>
