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
        .password-wrapper {
            position: relative;
            width: 100%;
            margin: 8px 0;
        }
        .password-wrapper input {
            margin: 0 !important;
            padding-right: 42px !important;
            box-sizing: border-box;
        }
        .toggle-password {
            position: absolute !important;
            right: 12px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: 24px !important;
            height: 24px !important;
            min-width: 24px !important;
            max-width: 24px !important;
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            box-shadow: none !important;
            color: #64748b !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            outline: none !important;
            transition: color 0.2s !important;
            z-index: 5;
        }
        .toggle-password:hover {
            color: #004aad !important;
            background: transparent !important;
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
                <div class="password-wrapper">
                    <input type="password" id="nueva_password" name="nueva_password" placeholder="Nueva contraseña" required>
                    <button type="button" class="toggle-password" onclick="togglePass('nueva_password', this)" title="Mostrar/ocultar contraseña">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <div class="password-wrapper">
                    <input type="password" id="confirmar_password" name="confirmar_password" placeholder="Confirmar contraseña" required>
                    <button type="button" class="toggle-password" onclick="togglePass('confirmar_password', this)" title="Mostrar/ocultar contraseña">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
                <button type="submit" name="actualizar_password">Actualizar Contraseña</button>
            </form>
        <?php endif; ?>

        <div class="mensaje"><?= $mensaje ?></div>
        <a href="index.php">🔙 Volver al login</a>
    </div>
    <script>
    function togglePass(inputId, btn) {
        const input = document.getElementById(inputId);
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
    </script>
</body>
</html>
