# 🖥️ Aplicación de Escritorio - Examen Seguro UG

Aplicación cliente de escritorio desarrollada en **Python** diseñada para que los estudiantes de la Universidad de Guayaquil rindan sus evaluaciones en un entorno controlado y seguro.

---

### 🛡️ Características Principales
* **Autenticación con el Servidor:** Conexión directa mediante la API REST (`api_examen.php`).
* **Sincronización en Tiempo Real:** Polling constante de tiempo de examen (`verificar_tiempo`) para evitar manipulaciones locales del reloj.
* **Supervisión de Hardware y Anti-Fraude:**
  * Detección activa de monitores externos / pantallas duplicadas o extendidas (`salida_video_externa`).
  * Ante la detección de más de un dispositivo de video conectado, se ejecuta el cierre forzado de la evaluación, calificación en 0.00 y notificación inmediata de seguridad al docente y administrador.
* **Guardado Progresivo:** Almacenamiento continuo de las respuestas marcadas para proteger el progreso ante eventuales interrupciones de conectividad.
* **Interfaz Estilo Moodle:** Experiencia de usuario limpia, clara e intuitiva para responder preguntas con imágenes, texto y opciones múltiples.

---

### 🚀 Requisitos e Instalación

1. **Requisitos previos:**
   * Python 3.10 o superior instalado.
   * Sistema Operativo: Windows 10/11.

2. **Instalar dependencias necesarias:**
   ```bash
   pip install requests pywin32 pillow
   ```

3. **Ejecución en modo desarrollo:**
   ```bash
   python examen_seguro.py
   ```

---

### 📦 Compilación a Ejecutable (.exe)
Para distribuir la aplicación a los estudiantes sin requerir que tengan Python instalado, compila utilizando PyInstaller:

```bash
pip install pyinstaller
pyinstaller examen_seguro.spec
```
El ejecutable resultante se generará en la carpeta `dist/`.
