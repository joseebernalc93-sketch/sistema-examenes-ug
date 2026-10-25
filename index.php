<?php
session_start();
include "db.php";

$error = "";
$mostrarRecuperar = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Limpiamos y escapamos la cédula
    $cedula_raw = trim($_POST["cedula"] ?? "");
    $cedula = $conn->real_escape_string($cedula_raw);

    // MD5 porque tu BD actual guarda así las contraseñas
    $password_raw = $_POST["password"] ?? "";
    $password = md5($password_raw); // ⚠️ En producción usar password_hash

    $sql = "SELECT * FROM usuarios 
            WHERE cedula='$cedula' 
              AND password='$password' 
              AND estado='aceptado'
            LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $_SESSION["usuario"] = $row;

        // Redirigir según rol
        if ($row["rol"] == "administrador") {
            header("Location: admin.php");
        } elseif (in_array($row["rol"], ['docente', 'director_carrera', 'director_area'], true)) {
            // ✅ Los 3 roles pasan por docente.php
            header("Location: docente.php");
        } else {
            // Por ejemplo estudiantes
            header("Location: estudiante.php");
        }
        exit;
    } else {
        $error = "❌ Credenciales inválidas o usuario no aceptado.";
        $mostrarRecuperar = true;
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
    <title>Login Exámenes</title>
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
        .login-container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0px 4px 12px rgba(0,0,0,0.1);
            width: 350px;
            text-align: center;
        }
        .login-container img {
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
            box-sizing: border-box;
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
            display: block;
            margin: 12px 0 0;
            box-sizing: border-box;
        }
        button:hover {
            background: #003580;
        }
        .error {
            color: red;
            font-size: 14px;
            margin-top: 10px;
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
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .password-wrapper input {
            padding-right: 42px;
        }
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            width: auto !important;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: #004aad;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Logo de la Universidad -->
        <img src="https://upload.wikimedia.org/wikipedia/commons/6/6d/LogoUGcolor.png" alt="Logo UG">
        <h2>Sistema de Exámenes</h2>
        <form method="POST">
            <input type="text" name="cedula" placeholder="Número de cédula" required>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" placeholder="Contraseña" required>
                <button type="button" class="toggle-password" id="togglePassword" title="Mostrar/ocultar contraseña" aria-label="Mostrar/ocultar contraseña">
                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
            <button type="submit">Iniciar Sesión</button>
        </form>

        <?php if (!empty($error)) : ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
            <?php if ($mostrarRecuperar): ?>
                <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <script>
      // Ojito para ver/ocultar contraseña
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');
      const eyeIcon = document.getElementById('eyeIcon');

      if (togglePassword && passwordInput && eyeIcon) {
          togglePassword.addEventListener('click', function(e) {
              e.preventDefault();
              const isPassword = passwordInput.type === 'password';
              passwordInput.type = isPassword ? 'text' : 'password';
              if (isPassword) {
                  // Ojo tachado (ocultar)
                  eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
              } else {
                  // Ojo abierto (mostrar)
                  eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
              }
          });
      }
      // Al cargar la pantalla de login, reiniciar la UI persistida del panel admin
      // para que (por ejemplo) el acordeón de "Gestión de Usuarios" inicie replegado.
      (function() {
        try {
          for (let i = sessionStorage.length - 1; i >= 0; i--) {
            const k = sessionStorage.key(i);
            if (!k) continue;
            if (k === 'admin_seccion_actual' || k.startsWith('admin_scroll_') || k.startsWith('admin_toggle_') || k.startsWith('admin_filters_')) {
              sessionStorage.removeItem(k);
            }
          }
        } catch (e) {}
      })();
    </script>
</body>
</html>
