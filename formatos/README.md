# 📄 Formatos y Plantillas de Importación

Esta carpeta contiene los archivos de plantilla en **Excel (.xlsx)** y **Word (.docx)** utilizados para cargar datos institucionales, planificaciones, usuarios y evaluaciones en la plataforma web.

---

### 📋 Detalle de Archivos

| Archivo | Formato | Rol que lo utiliza | Sección del Sistema | Descripción |
| :--- | :--- | :--- | :--- | :--- |
| **`2025-2026 CII Formato.xlsx`** | Excel | Administrador | Gestión de Cursos | Planificación académica semestral (Facultad, Carrera, Ciclo, Semestre, Materias y Paralelos). |
| **`2027-2028 CI Formato.xlsx`** | Excel | Administrador | Gestión de Cursos | Plantilla complementaria de planificación para períodos futuros. |
| **`Areas Tics Formato.xlsx`** | Excel | Administrador | Gestión de Cursos | Mapeo de materias con su respectiva área académica (Redes, Software, Administrativas, etc.). |
| **`Informacion de areas Tics.xlsx`** | Excel | Administrador | Gestión de Cursos | Catálogo detallado de materias y clasificaciones disciplinares. |
| **`Docentes Formato Tics.xlsx`** | Excel | Administrador | Gestión de Usuarios | Carga masiva de docentes, directores de área/carrera y asignación a sus paralelos. |
| **`Estudiantes Formato 1.xlsx`** | Excel | Administrador / Docente | Gestión de Usuarios | Nómina de estudiantes con cédula y correo institucional para registro y matriculación. |
| **`Flujo de materias Tics Formato.xlsx`** | Excel | Administrador | Malla Curricular | Definición de prelaciones y prerrequisitos entre asignaturas. |
| **`Test Formato 1 General.docx`** | Word | Docente | Crear Examen | Cuestionario de prueba con preguntas de cultura general y opciones múltiples. |
| **`Test Formato 2 Herramientas Digitales.docx`** | Word | Docente | Crear Examen | Banco de 15 preguntas de la asignatura Herramientas Digitales, con imágenes embebidas y claves de respuesta. |

---

### 💡 Flujo de Carga Recomendado
1. **Planificación (`2025-2026 CII Formato.xlsx`):** Crea la estructura de cursos en la base de datos.
2. **Áreas de Materias (`Areas Tics Formato.xlsx`):** Asigna las áreas para supervisión.
3. **Docentes (`Docentes Formato Tics.xlsx`):** Registra las cuentas docentes y vincula a sus cursos.
4. **Exámenes (`Test Formato 2 Herramientas Digitales.docx`):** El docente sube el Word para generar automáticamente el examen digital.
