<h3 style="margin:0 0 20px; color:#1f2937; font-size:1.4rem;">Gestion de Usuarios Registrados</h3>

<?php
// Mapa robusto para la cascada de filtros (Ciclo -> Facultad -> Carrera -> ...),
// basado en la oferta academica (no en los usuarios visibles en la tabla).
$ofertas_cascada = [];
try {
    if (isset($conn) && $conn) {
        $sql_ofertas = "
            SELECT DISTINCT
                co.ciclo_id AS ciclo_id,
                f.nombre AS facultad,
                ca.nombre AS carrera,
                s.numero AS semestre,
                m.nombre AS materia,
                p.nombre AS paralelo
            FROM cursos_oferta co
            JOIN paralelos p ON co.paralelo_id = p.id
            JOIN materias m ON p.materia_id = m.id
            JOIN semestres s ON m.semestre_id = s.id
            JOIN carreras ca ON s.carrera_id = ca.id
            JOIN facultades f ON ca.facultad_id = f.id
        ";
        $res_ofertas = $conn->query($sql_ofertas);
        if ($res_ofertas) {
            while ($r = $res_ofertas->fetch_assoc()) {
                $ofertas_cascada[] = $r;
            }
        }
    }
} catch (Throwable $e) {
    $ofertas_cascada = [];
}
?>

<?php
// Persistencia tipo "Notas": guardar la seleccion en la URL y restaurarla al refrescar.
$u_sel_ciclo = isset($_GET['u_ciclo']) ? trim((string)$_GET['u_ciclo']) : '';
$u_sel_fac = isset($_GET['u_facultad']) ? trim((string)$_GET['u_facultad']) : '';
$u_sel_car = isset($_GET['u_carrera']) ? trim((string)$_GET['u_carrera']) : '';
$u_sel_sem = isset($_GET['u_semestre']) ? trim((string)$_GET['u_semestre']) : '';
$u_sel_mat = isset($_GET['u_materia']) ? trim((string)$_GET['u_materia']) : '';
$u_sel_par = isset($_GET['u_paralelo']) ? trim((string)$_GET['u_paralelo']) : '';
$u_sel_tipo = isset($_GET['u_tipo']) ? trim((string)$_GET['u_tipo']) : '';
$u_sel_texto = isset($_GET['u_texto']) ? trim((string)$_GET['u_texto']) : '';
?>

<?php if (!empty($import_msg)): ?>
  <div class="import-box" style="border-color:#93c5fd;background:#eff6ff;margin:0 0 14px;"><strong><?= htmlspecialchars($import_msg) ?></strong></div>
<?php endif; ?>

<!-- BLOQUE DE IMPORTACION COLAPSABLE -->
<div class="section-title" data-target="filtroImportacionContent" id="toggleFiltroImportacion">
  <span style="display:flex; align-items:center; gap:8px;">
    <span style="background:linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color:white; padding:6px 10px; border-radius:8px; font-size:0.85rem;">IMPORTAR</span>
    Importacion de Docentes y Areas
  </span>
  <span class="toggle-btn">&#9654;</span>
</div>

<div id="filtroImportacionContent" class="content-hidden" style="padding: 20px;">

    <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
        <h4 style="margin-top:0; color:#0369a1; font-size: 1rem; margin-bottom: 12px; display:flex; align-items:center; gap:8px;">
            <span style="font-size:1.2rem;">&#128193;</span> Importar Docentes desde Excel
        </h4>
        <p style="font-size:13px; color:#64748b; margin-bottom:15px;">
            Seleccione un ciclo y suba el archivo Excel con columnas: <b>Rol, Area, Apellidos y nombres, Facultad, Carrera, Cedula, Correo institucional, Materia, Cursos</b>
        </p>

        <form method="POST" enctype="multipart/form-data" style="display:flex; gap:15px; align-items:end; flex-wrap:wrap;">
            <div style="flex: 1; min-width: 250px;">
                <label style="font-size:12px; font-weight:700; color:#0c4a6e; display: block; margin-bottom: 6px;">Ciclo de Trabajo:</label>
                <select name="ciclo_id_import_docentes" style="padding:12px; border-radius:8px; border:2px solid #7dd3fc; width: 100%; font-weight:600; color:#0c4a6e; background:#fff; font-size:14px;" required>
                    <option value="">-- Seleccione Ciclo --</option>
                    <?php foreach($todos_los_ciclos as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: 1; min-width: 250px;">
                <label style="font-size:12px; font-weight:700; color:#64748b; display: block; margin-bottom: 6px;">Archivo Docentes (.xlsx, .csv):</label>
                <input type="file" name="archivo_docentes" accept=".xlsx,.csv" style="padding:10px; border: 2px solid #e2e8f0; border-radius: 8px; width: 100%; box-sizing: border-box; background:#fff;">
            </div>

            <button type="submit" name="importar_docentes" style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color:#fff; border:none; padding:12px 24px; border-radius:8px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px; font-size:14px;">
                <span>&#9729;</span> Subir Docentes
            </button>
        </form>
    </div>

    <!-- IMPORTAR AREAS DE MATERIAS -->
    <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 1px solid #86efac; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
        <h4 style="margin-top:0; color:#166534; font-size: 1rem; margin-bottom: 12px; display:flex; align-items:center; gap:8px;">
            <span style="font-size:1.2rem;">&#128202;</span> Importar Areas de Materias
        </h4>
        <p style="font-size:13px; color:#64748b; margin-bottom:15px;">
            Archivo con columnas: <b>MATERIA, AREA</b> (para asociar materias con sus areas)
        </p>

        <form method="POST" enctype="multipart/form-data" style="display:flex; gap:15px; align-items:end; flex-wrap:wrap;">
            <div style="flex: 1; min-width: 250px;">
                <input type="file" name="archivo_areas" accept=".xlsx,.csv" style="padding:10px; border: 2px solid #bbf7d0; border-radius: 8px; width: 100%; box-sizing: border-box; background:#fff;" required>
            </div>
            <button type="submit" name="importar_areas_materias" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color:#fff; border:none; padding:12px 24px; border-radius:8px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px; font-size:14px;">
                <span>&#128228;</span> Subir Areas
            </button>
        </form>
    </div>

    <!-- HISTORIAL DE ARCHIVOS -->
    <?php if (!empty($lista_archivos_docentes) || !empty($lista_archivos_areas)): ?>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap:20px;">
        <?php if (!empty($lista_archivos_docentes)): ?>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
            <div style="background:linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color:white; padding:12px 16px; font-weight:700; font-size:0.9rem;">
                Historial de Archivos de Docentes
            </div>
            <div style="max-height: 200px; overflow-y: auto;">
                <table style="width:100%; border-collapse: collapse; font-size: 12px;">
                    <thead style="background:#f8fafc; color:#475569; position:sticky; top:0;">
                        <tr>
                            <th style="padding:10px; text-align:left; font-weight:600;">Archivo</th>
                            <th style="padding:10px; text-align:left; font-weight:600;">Ciclo</th>
                            <th style="padding:10px; text-align:left; font-weight:600;">Fecha</th>
                            <th style="padding:10px; text-align:center; font-weight:600;">Acc.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lista_archivos_docentes as $ad): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding:10px; color:#334155;"><?= htmlspecialchars($ad['nombre_archivo']) ?></td>
                                <td style="padding:10px;"><span style="background:#dbeafe; color:#1e40af; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600;"><?= htmlspecialchars($ad['nombre_ciclo'] ?? 'N/A') ?></span></td>
                                <td style="padding:10px; color:#64748b;"><?= date('d/m/Y', strtotime($ad['fecha_subida'])) ?></td>
                                <td style="padding:10px; text-align:center;">
                                    <div style="display:flex; gap:4px; justify-content:center;">
                                        <a href="admin.php?accion=descargar_archivo&id=<?= $ad['id'] ?>" style="background:#3b82f6; color:white; padding:4px 8px; border-radius:4px; text-decoration:none; font-size:11px;" title="Descargar">&#11015;</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminar este archivo y sus usuarios asociados?');">
                                            <input type="hidden" name="id_archivo_docentes" value="<?= $ad['id'] ?>">
                                            <button type="submit" name="eliminar_archivo_docentes" style="background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:11px;" title="Eliminar">&#128465;</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($lista_archivos_areas)): ?>
        <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
            <div style="background:linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color:white; padding:12px 16px; font-weight:700; font-size:0.9rem;">
                Historial de Archivos de Areas
            </div>
            <div style="max-height: 200px; overflow-y: auto;">
                <table style="width:100%; border-collapse: collapse; font-size: 12px;">
                    <thead style="background:#faf5ff; color:#6b21a8; position:sticky; top:0;">
                        <tr>
                            <th style="padding:10px; text-align:left; font-weight:600;">Archivo</th>
                            <th style="padding:10px; text-align:center; font-weight:600;">Registros</th>
                            <th style="padding:10px; text-align:left; font-weight:600;">Fecha</th>
                            <th style="padding:10px; text-align:center; font-weight:600;">Acc.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lista_archivos_areas as $aa): ?>
                            <tr style="border-bottom: 1px solid #f3e8ff;">
                                <td style="padding:10px; color:#334155;"><?= htmlspecialchars($aa['nombre_archivo']) ?></td>
                                <td style="padding:10px; text-align:center;"><span style="background:#e9d5ff; color:#7c3aed; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700;"><?= $aa['total_registros'] ?></span></td>
                                <td style="padding:10px; color:#64748b;"><?= date('d/m/Y', strtotime($aa['fecha_subida'])) ?></td>
                                <td style="padding:10px; text-align:center;">
                                    <div style="display:flex; gap:4px; justify-content:center;">
                                        <a href="admin.php?seccion=usuarios&descargar_archivo_areas=<?= $aa['id'] ?>" style="background:#8b5cf6; color:white; padding:4px 8px; border-radius:4px; text-decoration:none; font-size:11px;" title="Descargar">&#11015;</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Eliminar archivo y vaciar tabla de areas?');">
                                            <input type="hidden" name="id_archivo_areas" value="<?= $aa['id'] ?>">
                                            <button type="submit" name="eliminar_archivo_areas" style="background:#ef4444; color:white; border:none; padding:4px 8px; border-radius:4px; cursor:pointer; font-size:11px;" title="Eliminar">&#128465;</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ========================================= -->
<!-- PANEL DE FILTROS Y TABLA UNIFICADA -->
<!-- ========================================= -->

<div class="section-title" data-target="consultaUsuariosContent" id="toggleConsultaUsuarios">
  <span style="display:flex; align-items:center; gap:8px;">
    <span style="background:linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%); color:white; padding:6px 10px; border-radius:8px; font-size:0.85rem;">CONSULTA</span>
    Consulta de Usuarios
  </span>
  <span class="toggle-btn">&#9654;</span>
</div>

<div id="consultaUsuariosContent" class="content-hidden" style="margin-top:12px;">
<div style="background:#fff; border:1px solid #e5e7eb; border-radius:16px; margin-top:24px; box-shadow:0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06); overflow:hidden;">

    <!-- CABECERA -->
    <div style="background:linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%); padding:20px 24px; color:white;">
        <h4 style="margin:0 0 4px; font-size:1.15rem; font-weight:700; display:flex; align-items:center; gap:10px;">
            <span style="background:rgba(255,255,255,0.15); padding:8px; border-radius:10px; font-size:1.3rem;">&#128101;</span>
            Consulta de Usuarios
        </h4>
        <p style="margin:0; font-size:0.85rem; color:#94a3b8;">Filtre por criterios jerarquicos para encontrar usuarios especificos</p>
    </div>

    <!-- FILTROS EN CASCADA -->
    <div style="padding:24px; background:linear-gradient(180deg, #f8fafc 0%, #fff 100%); border-bottom:1px solid #e5e7eb;">
        <div style="display:flex; flex-direction:column; gap:16px;">
            <div class="filtros-cascada" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:12px 16px; width:100%;">
                <!-- CICLO -->
                <div class="filtro-grupo">
                    <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Ciclo</label>
                    <select id="filtroCicloUsuarios" class="filtro-select" data-selected="<?= htmlspecialchars($u_sel_ciclo) ?>" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:700; color:#334155; background:#fff; cursor:pointer; transition:border-color 0.2s;">
                        <option value="">Seleccionar ciclo</option>
                        <?php foreach($todos_los_ciclos as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- FACULTAD -->
                <div class="filtro-grupo">
                    <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Facultad</label>
                    <select id="filtroFacultadUsuarios" class="filtro-select" data-selected="<?= htmlspecialchars($u_sel_fac) ?>" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:700; color:#334155; background:#fff; cursor:pointer; transition:border-color 0.2s;" disabled>
                        <option value="">Seleccionar facultad</option>
                    </select>
                </div>

                <!-- CARRERA -->
                <div class="filtro-grupo">
                    <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Carrera</label>
                    <select id="filtroCarreraUsuarios" class="filtro-select" data-selected="<?= htmlspecialchars($u_sel_car) ?>" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:700; color:#334155; background:#fff; cursor:pointer; transition:border-color 0.2s;" disabled>
                        <option value="">Seleccionar carrera</option>
                    </select>
                </div>

                <!-- SEMESTRE -->
                <div class="filtro-grupo">
                    <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Semestre</label>
                    <select id="filtroSemestreUsuarios" class="filtro-select" data-selected="<?= htmlspecialchars($u_sel_sem) ?>" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:700; color:#334155; background:#fff; cursor:pointer; transition:border-color 0.2s;" disabled>
                        <option value="">Seleccionar semestre</option>
                    </select>
                </div>

                <!-- MATERIA -->
                <div class="filtro-grupo">
                    <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Materia</label>
                    <select id="filtroMateriaUsuarios" class="filtro-select" data-selected="<?= htmlspecialchars($u_sel_mat) ?>" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:700; color:#334155; background:#fff; cursor:pointer; transition:border-color 0.2s;" disabled>
                        <option value="">Seleccionar materia</option>
                    </select>
                </div>

                <!-- CURSO / PARALELO -->
                <div class="filtro-grupo">
                    <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Curso / Paralelo</label>
                    <select id="filtroParaleloUsuarios" class="filtro-select" data-selected="<?= htmlspecialchars($u_sel_par) ?>" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:700; color:#334155; background:#fff; cursor:pointer; transition:border-color 0.2s;" disabled>
                        <option value="">Seleccionar curso</option>
                    </select>
                </div>

                <!-- TIPO DE USUARIO -->
                <div class="filtro-grupo" style="grid-column:1 / -1;">
                    <label style="display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px;">Tipo de Usuario</label>
                    <select id="filtroTipoUsuario" class="filtro-select" data-selected="<?= htmlspecialchars($u_sel_tipo) ?>" style="width:100%; padding:12px; border:2px solid #e2e8f0; border-radius:10px; font-size:13px; font-weight:700; color:#334155; background:#fff; cursor:pointer; transition:border-color 0.2s;">
                        <option value="">Seleccionar tipo</option>
                        <option value="todos">Todos</option>
                        <option value="director_carrera">Director de Carrera</option>
                        <option value="director_area">Director de Area</option>
                        <option value="docente">Docente</option>
                        <option value="estudiante">Estudiante</option>
                    </select>
                </div>
            </div>

            <!-- BUSCADOR Y ACCIONES -->
            <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <div style="flex:1; min-width:280px; position:relative;">
                    <span style="position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:16px;">&#128269;</span>
                    <input type="text" id="inputBuscadorUsuariosNuevo" value="<?= htmlspecialchars($u_sel_texto) ?>" placeholder="Buscar por nombre o cedula..." style="width:100%; padding:12px 12px 12px 42px; border:2px solid #e2e8f0; border-radius:10px; font-size:14px; transition:border-color 0.2s, box-shadow 0.2s;">
                </div>
                <button type="button" id="btnBuscarUsuariosNuevo" style="background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color:#fff; border:none; padding:12px 24px; border-radius:10px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px; font-size:14px;">
                    <span>&#128269;</span> Buscar
                </button>
                <button type="button" id="btnLimpiarFiltrosNuevo" style="background:#64748b; color:#fff; border:none; padding:12px 24px; border-radius:10px; font-weight:700; cursor:pointer; font-size:14px;">
                    Limpiar
                </button>
            </div>
        </div>
    </div>

    <!-- CONTADOR Y ACCIONES MASIVAS -->
    <div style="padding:16px 24px; background:#f8fafc; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <div id="contadorResultados" style="display:flex; align-items:center; gap:10px;">
            <span style="background:linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color:white; padding:6px 14px; border-radius:8px; font-weight:700; font-size:0.9rem;">
                <span id="totalUsuariosEncontrados">0</span> usuarios
            </span>
            <span style="color:#64748b; font-size:0.85rem;" id="descripcionFiltro">Mostrando todos los usuarios</span>
        </div>
        <div style="display:flex; gap:8px;">
            <button type="button" id="btnAceptarSeleccionados" style="background:linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px; display:flex; align-items:center; gap:6px;">
                <span>&#10003;</span> Aceptar Seleccionados
            </button>
            <button type="button" id="btnEliminarSeleccionados" style="background:linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:700; cursor:pointer; font-size:13px; display:flex; align-items:center; gap:6px;">
                <span>&#128465;</span> Eliminar Seleccionados
            </button>
        </div>
    </div>

    <!-- TABLA UNIFICADA DE RESULTADOS -->
    <form method="POST" id="formUsuariosUnificado">
        <div id="usuariosTablaWrap" data-admin-scroll="1" style="overflow-x:auto; max-height:600px; overflow-y:auto;">
            <table id="tablaUsuariosUnificada" style="width:100%; border-collapse:collapse; min-width:900px;">
                <thead style="position:sticky; top:0; z-index:10;">
                    <tr style="background:linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);">
                        <th style="padding:14px 12px; color:#fff; font-weight:700; text-align:center; width:40px;">
                            <input type="checkbox" id="checkAllUsuariosUnificado" style="width:18px; height:18px; cursor:pointer;">
                        </th>
                        <th style="padding:14px 16px; color:#fff; font-weight:700; text-align:left; font-size:0.85rem;">#</th>
                        <th style="padding:14px 16px; color:#fff; font-weight:700; text-align:left; font-size:0.85rem;">Nombre</th>
                        <th style="padding:14px 16px; color:#fff; font-weight:700; text-align:left; font-size:0.85rem;">Cedula</th>
                        <th style="padding:14px 16px; color:#fff; font-weight:700; text-align:left; font-size:0.85rem;">Rol</th>
                        <th style="padding:14px 16px; color:#fff; font-weight:700; text-align:left; font-size:0.85rem;">Facultad</th>
                        <th style="padding:14px 16px; color:#fff; font-weight:700; text-align:left; font-size:0.85rem;">Carrera</th>
                        <th style="padding:14px 16px; color:#fff; font-weight:700; text-align:left; font-size:0.85rem;">Info Adicional</th>
                        <th style="padding:14px 16px; color:#fff; font-weight:700; text-align:left; font-size:0.85rem;">Estado</th>
                        <th style="padding:14px 16px; color:#fff; font-weight:700; text-align:center; font-size:0.85rem;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbodyUsuariosUnificado">
                    <?php
                    // DEBUG temporal - verificar datos
                    if (!isset($usuarios_tabla)) {
                        echo '<tr><td colspan="10" style="text-align:center; padding:40px; color:#dc2626; background:#fef2f2;">ERROR: Variable $usuarios_tabla no definida</td></tr>';
                    } elseif (empty($usuarios_tabla)) {
                        // Verificar conexion y ejecutar query simple para debug
                        $debug_count = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE rol <> 'administrador'");
                        $debug_total = $debug_count ? $debug_count->fetch_assoc()['total'] : 'ERROR_QUERY';
                        echo '<tr><td colspan="10" style="text-align:center; padding:40px; color:#64748b; background:#fef9c3;">';
                        echo '<strong>DEBUG:</strong> Array usuarios_tabla esta vacio.<br>';
                        echo 'Total usuarios en BD (query directo): ' . $debug_total . '<br>';
                        echo 'Si muestra un numero > 0, el problema esta en la consulta compleja de admin_logic.php';
                        echo '</td></tr>';
                    }

                    $contador = 0;
                    foreach ($usuarios_tabla as $row):
                        $contador++;
                        $rol_display = '';
                        $rol_color = '';
                        $info_adicional = '';

                        switch($row['rol']) {
                            case 'director_carrera':
                                $rol_display = 'Dir. Carrera';
                                $rol_color = 'background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color:white;';
                                $info_adicional = '<span style="color:#92400e; font-size:12px;">Director de: <strong>' . htmlspecialchars($row['carrera']) . '</strong></span>';
                                break;
                            case 'director_area':
                                $rol_display = 'Dir. Area';
                                $rol_color = 'background:linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color:white;';
                                $area_txt = !empty($row['area']) ? $row['area'] : 'Sin area asignada';
                                $info_adicional = '<span style="background:#ede9fe; color:#6d28d9; padding:3px 10px; border-radius:6px; font-size:11px; font-weight:600;">' . htmlspecialchars($area_txt) . '</span>';
                                break;
                            case 'docente':
                                $rol_display = 'Docente';
                                $rol_color = 'background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color:white;';
                                $materias = $row['materia_registrada'] ?? '';
                                if ($materias) {
                                    $arr_mat = explode("\n", $materias);
                                    $primera_mat = htmlspecialchars(strlen($arr_mat[0]) > 30 ? substr($arr_mat[0], 0, 30).'...' : $arr_mat[0]);
                                    $total_mat = count($arr_mat);
                                    $info_adicional = '<span style="color:#1e40af; font-size:12px;" title="'.htmlspecialchars($materias).'">' . $primera_mat . ($total_mat > 1 ? ' <em>(+'.($total_mat-1).')</em>' : '') . '</span>';
                                }
                                break;
                            case 'estudiante':
                                $rol_display = 'Estudiante';
                                $rol_color = 'background:linear-gradient(135deg, #10b981 0%, #059669 100%); color:white;';
                                $paralelos = $row['paralelo_registrado'] ?? '';
                                if ($paralelos) {
                                    $arr_par = explode("\n", $paralelos);
                                    $info_adicional = '<span style="color:#047857; font-size:12px;">Paralelo: <strong>' . htmlspecialchars($arr_par[0]) . '</strong>' . (count($arr_par) > 1 ? ' (+' . (count($arr_par)-1) . ')' : '') . '</span>';
                                }
                                break;
                            default:
                                $rol_display = ucfirst($row['rol']);
                                $rol_color = 'background:#6b7280; color:white;';
                        }

                        $estado_color = $row['estado'] == 'aceptado' ? 'background:#dcfce7; color:#166534;' : 'background:#fef3c7; color:#92400e;';

                        // Data attributes para filtrado - usar datos del usuario y registrados
                        $facultad_usuario = $row['facultad'] ?? '';
                        $carrera_usuario = $row['carrera'] ?? '';
                        $facultad_reg = $row['facultad_registrada'] ?? '';
                        $carrera_reg = $row['carrera_registrada'] ?? '';
                        $semestre_reg = $row['semestre_registrado'] ?? '';
                        $materia_reg = $row['materia_registrada'] ?? '';
                        $paralelo_reg = $row['paralelo_registrado'] ?? '';
                        $ciclos_ids = $row['ciclos_ids'] ?? '';

                        // Combinar facultad/carrera del usuario con las registradas en cursos
                        $facultad_combined = $facultad_usuario . ' ' . $facultad_reg;
                        $carrera_combined = $carrera_usuario . ' ' . $carrera_reg;
                    ?>
                    <tr class="fila-usuario"
                        data-id="<?= (int)$row['id'] ?>"
                        data-nombre="<?= htmlspecialchars(strtoupper($row['nombres'])) ?>"
                        data-cedula="<?= htmlspecialchars($row['cedula']) ?>"
                        data-rol="<?= htmlspecialchars($row['rol']) ?>"
                        data-facultad="<?= htmlspecialchars(strtoupper($facultad_combined)) ?>"
                        data-carrera="<?= htmlspecialchars(strtoupper($carrera_combined)) ?>"
                        data-semestre="<?= htmlspecialchars($semestre_reg) ?>"
                        data-materia="<?= htmlspecialchars(strtoupper($materia_reg)) ?>"
                        data-paralelo="<?= htmlspecialchars(strtoupper($paralelo_reg)) ?>"
                        data-ciclos="<?= htmlspecialchars($ciclos_ids) ?>"
                        data-facultad-registrada="<?= htmlspecialchars(strtoupper($facultad_reg)) ?>"
                        data-carrera-registrada="<?= htmlspecialchars(strtoupper($carrera_reg)) ?>"
                        data-semestres-registrados="<?= htmlspecialchars($semestre_reg) ?>"
                        data-materias-registradas="<?= htmlspecialchars(strtoupper($materia_reg)) ?>"
                        data-paralelos-registrados="<?= htmlspecialchars(strtoupper($paralelo_reg)) ?>"
                        data-area="<?= htmlspecialchars(strtoupper($row['area'] ?? '')) ?>"
                        data-estado="<?= htmlspecialchars($row['estado']) ?>"
                        style="border-bottom:1px solid #f1f5f9; transition:background 0.15s;">
                        <td style="padding:12px; text-align:center;">
                            <input type="checkbox" name="sel_usuarios[]" value="<?= (int)$row['id'] ?>" class="check-usuario" style="width:16px; height:16px; cursor:pointer;">
                        </td>
                        <td style="padding:12px 16px; color:#64748b; font-weight:600; font-size:0.85rem;"><?= $contador ?></td>
                        <td style="padding:12px 16px; font-weight:600; color:#1e293b;"><?= htmlspecialchars($row['nombres']) ?></td>
                        <td style="padding:12px 16px; color:#475569; font-family:monospace; font-size:0.9rem;"><?= htmlspecialchars($row['cedula']) ?></td>
                        <td style="padding:12px 16px;">
                            <span style="<?= $rol_color ?> padding:4px 12px; border-radius:6px; font-size:11px; font-weight:700; display:inline-block;"><?= $rol_display ?></span>
                        </td>
                        <td style="padding:12px 16px; color:#475569; font-size:0.85rem; max-width:150px; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($row['facultad']) ?>"><?= htmlspecialchars(strlen($row['facultad']) > 25 ? substr($row['facultad'], 0, 25).'...' : $row['facultad']) ?></td>
                        <td style="padding:12px 16px; color:#475569; font-size:0.85rem; max-width:150px; overflow:hidden; text-overflow:ellipsis;" title="<?= htmlspecialchars($row['carrera']) ?>"><?= htmlspecialchars(strlen($row['carrera']) > 25 ? substr($row['carrera'], 0, 25).'...' : $row['carrera']) ?></td>
                        <td style="padding:12px 16px;"><?= $info_adicional ?></td>
                        <td style="padding:12px 16px;">
                            <span style="<?= $estado_color ?> padding:4px 10px; border-radius:6px; font-size:11px; font-weight:600;"><?= ucfirst(htmlspecialchars($row['estado'])) ?></span>
                        </td>
                        <td style="padding:12px 16px; text-align:center;">
                            <div style="display:flex; gap:6px; justify-content:center;">
                                <?php if ($row['estado'] == 'pendiente'): ?>
                                    <a href="admin.php?accion=aceptar&id=<?= (int)$row['id'] ?>" style="background:#22c55e; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:11px; font-weight:600;" title="Aceptar">&#10003;</a>
                                    <a href="admin.php?accion=rechazar&id=<?= (int)$row['id'] ?>" onclick="return confirm('Rechazar y eliminar este usuario?')" style="background:#ef4444; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:11px; font-weight:600;" title="Rechazar">&#10005;</a>
                                <?php else: ?>
                                    <a href="editar_usuario.php?id=<?= (int)$row['id'] ?>" style="background:#3b82f6; color:white; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:11px; font-weight:600;">Editar</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Mensaje cuando no hay resultados -->
        <div id="sinResultados" style="display:none; padding:60px 24px; text-align:center; background:#f8fafc;">
            <div id="sinResultadosIcono" style="font-size:4rem; margin-bottom:16px;">&#128533;</div>
            <h4 id="sinResultadosTitulo" style="color:#475569; margin:0 0 8px;">No se encontraron usuarios</h4>
            <p id="sinResultadosTexto" style="color:#94a3b8; margin:0;">Intente ajustar los filtros de busqueda</p>
        </div>
    </form>
</div>

</div>
<style>
    .filtro-select:focus, #inputBuscadorUsuariosNuevo:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .fila-usuario:hover {
        background: #f8fafc !important;
    }
    .fila-usuario:nth-child(even) {
        background: #fafbfc;
    }
    #tablaUsuariosUnificada tbody tr.oculto {
        display: none;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filtroCiclo = document.getElementById('filtroCicloUsuarios');
    const filtroFacultad = document.getElementById('filtroFacultadUsuarios');
    const filtroCarrera = document.getElementById('filtroCarreraUsuarios');
    const filtroSemestre = document.getElementById('filtroSemestreUsuarios');
    const filtroMateria = document.getElementById('filtroMateriaUsuarios');
    const filtroParalelo = document.getElementById('filtroParaleloUsuarios');
    const filtroTipo = document.getElementById('filtroTipoUsuario');
    const inputBuscador = document.getElementById('inputBuscadorUsuariosNuevo');
    const btnBuscar = document.getElementById('btnBuscarUsuariosNuevo');
    const btnLimpiar = document.getElementById('btnLimpiarFiltrosNuevo');
    const checkAll = document.getElementById('checkAllUsuariosUnificado');
    const btnAceptar = document.getElementById('btnAceptarSeleccionados');
    const btnEliminar = document.getElementById('btnEliminarSeleccionados');
    const totalSpan = document.getElementById('totalUsuariosEncontrados');
    const descripcionFiltro = document.getElementById('descripcionFiltro');
    const sinResultados = document.getElementById('sinResultados');
    const sinResultadosTitulo = document.getElementById('sinResultadosTitulo');
    const sinResultadosTexto = document.getElementById('sinResultadosTexto');
    const sinResultadosIcono = document.getElementById('sinResultadosIcono');
    const tabla = document.getElementById('tablaUsuariosUnificada');
    const filas = Array.from(document.querySelectorAll('.fila-usuario'));
    const thFacultad = document.querySelector('#tablaUsuariosUnificada thead th:nth-child(6)');
    const thCarrera = document.querySelector('#tablaUsuariosUnificada thead th:nth-child(7)');

    const ciclosCatalogo = <?php echo json_encode(array_column($todos_los_ciclos, 'nombre', 'id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const usuarios = filas.map(normalizarFila);
    const cascadaUsuarios = construirCascada(usuarios);
    const ofertasCursos = <?php echo json_encode($ofertas_cascada, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const cascadaOfertas = construirCascadaOfertas(ofertasCursos);
    const cascadaBase = (cascadaOfertas && cascadaOfertas.ciclos && cascadaOfertas.ciclos.length)
        ? cascadaOfertas
        : cascadaUsuarios;

    // ================================
    // Persistencia de filtros (URL, estilo Notas)
    // ================================
    // Guardamos la seleccion en la querystring para que al refrescar (F5) no se pierda nada.
    const urlParamMap = {
        ciclo: 'u_ciclo',
        facultad: 'u_facultad',
        carrera: 'u_carrera',
        semestre: 'u_semestre',
        materia: 'u_materia',
        paralelo: 'u_paralelo',
        tipo: 'u_tipo',
        texto: 'u_texto'
    };
    let urlSyncTimer = null;

    function normTxt(val) {
        const s = (val === undefined || val === null) ? '' : String(val);
        let out = s.trim().toUpperCase();
        // Comparar/rehidratar aun si hay diferencias de tildes (NFC/NFD).
        try {
            out = out.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        } catch (e) {}
        // Normalizar espacios (incluye NBSP) para que coincidan aunque haya dobles espacios.
        out = out.replace(/\s+/g, ' ').trim();
        return out;
    }

    function valoresEquivalentes(a, b) {
        if (a === b) return true;
        return normTxt(a) === normTxt(b);
    }

    function setSelectValueSmart(select, desired) {
        if (!select) return false;
        const want = (desired === undefined || desired === null) ? '' : String(desired);

        // Intento directo (exact match por value).
        select.value = want;
        if (select.value === want) return true;

        const wantNorm = normTxt(want);
        if (!wantNorm) return false;

        // Fallback: match por normalizacion (value o texto).
        const opts = Array.from(select.options || []);
        for (const opt of opts) {
            if (!opt) continue;
            if (normTxt(opt.value) === wantNorm || normTxt(opt.textContent) === wantNorm) {
                select.value = opt.value;
                return select.value === opt.value;
            }
        }
        return false;
    }

    function estadoFiltrosActual() {
        return {
            ciclo: filtroCiclo ? filtroCiclo.value : '',
            facultad: filtroFacultad ? filtroFacultad.value : '',
            carrera: filtroCarrera ? filtroCarrera.value : '',
            semestre: filtroSemestre ? filtroSemestre.value : '',
            materia: filtroMateria ? filtroMateria.value : '',
            paralelo: filtroParalelo ? filtroParalelo.value : '',
            tipo: filtroTipo ? filtroTipo.value : '',
            texto: inputBuscador ? inputBuscador.value : ''
        };
    }

    function leerDataSelected(el) {
        if (!el) return '';
        return (el.getAttribute('data-selected') || '').toString();
    }

    function limpiarDataSelected(el) {
        if (!el) return;
        el.setAttribute('data-selected', '');
    }

    function actualizarUrlDesdeFiltros(debounceMs = 0) {
        const run = () => {
            const st = estadoFiltrosActual();
            const url = new URL(window.location.href);

            // Asegurar que estamos en la seccion correcta y evitar params peligrosos de acciones.
            url.searchParams.set('seccion', 'usuarios');
            url.searchParams.delete('accion');
            url.searchParams.delete('id');

            Object.entries(urlParamMap).forEach(([k, param]) => {
                const v = (st[k] ?? '').toString().trim();
                if (v) url.searchParams.set(param, v);
                else url.searchParams.delete(param);
            });

            history.replaceState({}, '', url.toString());

            // Mantener el valor guardado de seccion coherente.
            try { sessionStorage.setItem('admin_seccion_actual', window.location.search); } catch (e) {}

            // Importante: las keys de scroll/toggles en admin.php dependen de location.search.
            // Guardamos el estado inmediatamente para que si el usuario refresca al instante,
            // vuelva exactamente al mismo lugar.
            // Ojo: si se ejecuta durante el primer DOMContentLoaded de la pagina, los plegables
            // aun estan en su estado por defecto (CSS `.content-hidden` => cerrado) y esto
            // puede pisar el estado guardado. Lo diferimos al siguiente tick.
            setTimeout(() => {
                try { if (typeof window.adminUI_storeNow === 'function') window.adminUI_storeNow(); } catch (e) {}
            }, 0);
        };

        if (debounceMs > 0) {
            if (urlSyncTimer) clearTimeout(urlSyncTimer);
            urlSyncTimer = setTimeout(() => {
                urlSyncTimer = null;
                run();
            }, debounceMs);
        } else {
            run();
        }
    }

    function inicializarFiltrosDesdeURL() {
        // Preseleccion inyectada por PHP en data-selected (patron igual a Notas).
        const preCiclo = leerDataSelected(filtroCiclo);
        if (preCiclo) setSelectValueSmart(filtroCiclo, preCiclo);

        const preTipo = leerDataSelected(filtroTipo);
        if (preTipo) setSelectValueSmart(filtroTipo, preTipo);

        // Hidratar cascada segun ciclo (las funciones actualizar* leeran data-selected de cada nivel).
        if (filtroCiclo && filtroCiclo.value) {
            actualizarFacultades();
        } else {
            setOptions(filtroFacultad, [], 'Seleccionar facultad');
            setOptions(filtroCarrera, [], 'Seleccionar carrera');
            setOptions(filtroSemestre, [], 'Seleccionar semestre');
            setOptions(filtroMateria, [], 'Seleccionar materia');
            setOptions(filtroParalelo, [], 'Seleccionar curso');
        }

        filtrar();
        actualizarUrlDesdeFiltros(0);
    }

    // ------------------------
    // Listeners (cascada + URL)
    // ------------------------

    if (filtroCiclo) filtroCiclo.addEventListener('change', () => {
        // Reset aguas abajo
        limpiarDataSelected(filtroFacultad);
        limpiarDataSelected(filtroCarrera);
        limpiarDataSelected(filtroSemestre);
        limpiarDataSelected(filtroMateria);
        limpiarDataSelected(filtroParalelo);

        if (filtroFacultad) filtroFacultad.value = '';
        if (filtroCarrera) filtroCarrera.value = '';
        if (filtroSemestre) filtroSemestre.value = '';
        if (filtroMateria) filtroMateria.value = '';
        if (filtroParalelo) filtroParalelo.value = '';

        actualizarFacultades();
        filtrar();
        actualizarUrlDesdeFiltros(0);
    });

    if (filtroFacultad) filtroFacultad.addEventListener('change', () => {
        limpiarDataSelected(filtroCarrera);
        limpiarDataSelected(filtroSemestre);
        limpiarDataSelected(filtroMateria);
        limpiarDataSelected(filtroParalelo);

        if (filtroCarrera) filtroCarrera.value = '';
        if (filtroSemestre) filtroSemestre.value = '';
        if (filtroMateria) filtroMateria.value = '';
        if (filtroParalelo) filtroParalelo.value = '';

        actualizarCarreras();
        filtrar();
        actualizarUrlDesdeFiltros(0);
    });

    if (filtroCarrera) filtroCarrera.addEventListener('change', () => {
        limpiarDataSelected(filtroSemestre);
        limpiarDataSelected(filtroMateria);
        limpiarDataSelected(filtroParalelo);

        if (filtroSemestre) filtroSemestre.value = '';
        if (filtroMateria) filtroMateria.value = '';
        if (filtroParalelo) filtroParalelo.value = '';

        actualizarSemestres();
        filtrar();
        actualizarUrlDesdeFiltros(0);
    });

    if (filtroSemestre) filtroSemestre.addEventListener('change', () => {
        limpiarDataSelected(filtroMateria);
        limpiarDataSelected(filtroParalelo);

        if (filtroMateria) filtroMateria.value = '';
        if (filtroParalelo) filtroParalelo.value = '';

        actualizarMaterias();
        filtrar();
        actualizarUrlDesdeFiltros(0);
    });

    if (filtroMateria) filtroMateria.addEventListener('change', () => {
        limpiarDataSelected(filtroParalelo);

        if (filtroParalelo) filtroParalelo.value = '';

        actualizarParalelos();
        filtrar();
        actualizarUrlDesdeFiltros(0);
    });

    if (filtroParalelo) filtroParalelo.addEventListener('change', () => {
        filtrar();
        actualizarUrlDesdeFiltros(0);
    });

    if (filtroTipo) filtroTipo.addEventListener('change', () => {
        filtrar();
        actualizarUrlDesdeFiltros(0);
    });

    if (inputBuscador) {
        inputBuscador.addEventListener('input', () => {
            filtrar();
            actualizarUrlDesdeFiltros(250);
        });
        inputBuscador.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                filtrar();
                actualizarUrlDesdeFiltros(0);
            }
        });
    }

    if (btnBuscar) btnBuscar.addEventListener('click', () => {
        filtrar();
        actualizarUrlDesdeFiltros(0);
    });

    if (btnLimpiar) btnLimpiar.addEventListener('click', () => {
        limpiarDataSelected(filtroCiclo);
        limpiarDataSelected(filtroFacultad);
        limpiarDataSelected(filtroCarrera);
        limpiarDataSelected(filtroSemestre);
        limpiarDataSelected(filtroMateria);
        limpiarDataSelected(filtroParalelo);
        limpiarDataSelected(filtroTipo);

        limpiarFiltros();
        actualizarUrlDesdeFiltros(0);
    });

    if (checkAll) {
        checkAll.addEventListener('change', function() {
            usuarios.filter(u => u.row.style.display !== 'none').forEach(u => {
                const check = u.row.querySelector('.check-usuario');
                if (check) check.checked = checkAll.checked;
            });
        });
    }

    if (btnAceptar) btnAceptar.addEventListener('click', manejarAccionMasiva('aceptar_usuarios_seleccionados', 'Aceptar'));
    if (btnEliminar) btnEliminar.addEventListener('click', manejarAccionMasiva('eliminar_usuarios_seleccionados', 'Eliminar', true));

    // Inicializar desde la URL al cargar (igual que Notas).
    inicializarFiltrosDesdeURL();


    function normalizarFila(row) {
        const parseLista = (txt) => (txt || '').split(/\n|,/).map(t => t.trim().toUpperCase()).filter(Boolean);
        const parseCiclos = (txt) => (txt || '').split(',').map(t => t.trim()).filter(Boolean);
        const parseSemestres = (txt) => (txt || '').split(/\n|,/).map(t => t.trim()).filter(Boolean);
        return {
            row,
            ciclos: parseCiclos(row.dataset.ciclos || ''),
            facultades: parseLista(row.dataset.facultadRegistrada || row.dataset.facultad),
            carreras: parseLista(row.dataset.carreraRegistrada || row.dataset.carrera),
            semestres: parseSemestres(row.dataset.semestresRegistrados || row.dataset.semestre),
            materias: parseLista(row.dataset.materiasRegistradas || row.dataset.materia),
            paralelos: parseLista(row.dataset.paralelosRegistrados || row.dataset.paralelo),
            rol: (row.dataset.rol || '').trim(),
            nombre: (row.dataset.nombre || '').trim().toUpperCase(),
            cedula: (row.dataset.cedula || '').trim().toUpperCase()
        };
    }

    function addTo(map, key, value) {
        if (!value && value !== 0) return;
        if (!map[key]) map[key] = new Set();
        map[key].add(value);
    }

    function ordenar(arr) {
        return (arr || []).filter(v => v !== undefined && v !== null).map(v => v.toString()).filter(Boolean).sort((a, b) => a.localeCompare(b, 'es', { numeric: true, sensitivity: 'base' }));
    }

    function construirCascada(lista) {
        const sets = {
            ciclos: new Set(),
            facultadPorCiclo: {},
            carreraPorFac: {},
            semestrePorCarrera: {},
            materiaPorSemestre: {},
            cursoPorMateria: {}
        };

        lista.forEach(u => {
            u.ciclos.forEach(ciclo => {
                sets.ciclos.add(ciclo);
                u.facultades.forEach(fac => {
                    addTo(sets.facultadPorCiclo, ciclo, fac);
                    u.carreras.forEach(car => {
                        const keyCar = `${ciclo}|${fac}|${car}`;
                        addTo(sets.carreraPorFac, `${ciclo}|${fac}`, car);

                        u.semestres.forEach(sem => {
                            addTo(sets.semestrePorCarrera, keyCar, sem);

                            u.materias.forEach(mat => {
                                const keySem = `${keyCar}|${sem}`;
                                addTo(sets.materiaPorSemestre, keySem, mat);

                                const keyMat = `${keySem}|${mat}`;
                                u.paralelos.forEach(par => addTo(sets.cursoPorMateria, keyMat, par));
                            });
                        });
                    });
                });
            });
        });

        const mapToArray = (obj) => Object.keys(obj).reduce((acc, key) => {
            acc[key] = ordenar(Array.from(obj[key]));
            return acc;
        }, {});

        return {
            ciclos: ordenar(Array.from(sets.ciclos)),
            facultadPorCiclo: mapToArray(sets.facultadPorCiclo),
            carreraPorFac: mapToArray(sets.carreraPorFac),
            semestrePorCarrera: mapToArray(sets.semestrePorCarrera),
            materiaPorSemestre: mapToArray(sets.materiaPorSemestre),
            cursoPorMateria: mapToArray(sets.cursoPorMateria)
        };
    }

    function construirCascadaOfertas(ofertas) {
        // Usa la oferta academica (cursos_oferta) como fuente para la cascada.
        // Esto evita que los combos queden vacios/bloqueados si la tabla de usuarios
        // no refleja todas las combinaciones existentes.
        const sets = {
            ciclos: new Set(),
            facultadPorCiclo: {},
            carreraPorFac: {},
            semestrePorCarrera: {},
            materiaPorSemestre: {},
            cursoPorMateria: {}
        };

        (ofertas || []).forEach(r => {
            if (!r) return;
            const ciclo = (r.ciclo_id === undefined || r.ciclo_id === null) ? '' : String(r.ciclo_id).trim();
            if (!ciclo) return;
            const fac = (r.facultad || '').toString().trim().toUpperCase();
            const car = (r.carrera || '').toString().trim().toUpperCase();
            const sem = (r.semestre === undefined || r.semestre === null) ? '' : String(r.semestre).trim();
            const mat = (r.materia || '').toString().trim().toUpperCase();
            const par = (r.paralelo || '').toString().trim().toUpperCase();

            sets.ciclos.add(ciclo);
            if (fac) addTo(sets.facultadPorCiclo, ciclo, fac);
            if (fac && car) addTo(sets.carreraPorFac, `${ciclo}|${fac}`, car);
            if (fac && car && sem) addTo(sets.semestrePorCarrera, `${ciclo}|${fac}|${car}`, sem);
            if (fac && car && sem && mat) addTo(sets.materiaPorSemestre, `${ciclo}|${fac}|${car}|${sem}`, mat);
            if (fac && car && sem && mat && par) addTo(sets.cursoPorMateria, `${ciclo}|${fac}|${car}|${sem}|${mat}`, par);
        });

        const mapToArray = (obj) => Object.keys(obj).reduce((acc, key) => {
            acc[key] = ordenar(Array.from(obj[key]));
            return acc;
        }, {});

        return {
            ciclos: ordenar(Array.from(sets.ciclos)),
            facultadPorCiclo: mapToArray(sets.facultadPorCiclo),
            carreraPorFac: mapToArray(sets.carreraPorFac),
            semestrePorCarrera: mapToArray(sets.semestrePorCarrera),
            materiaPorSemestre: mapToArray(sets.materiaPorSemestre),
            cursoPorMateria: mapToArray(sets.cursoPorMateria)
        };
    }

    function setOptions(select, options, placeholder, formatter) {
        if (!select) return;
        const current = (select.value || '').toString();
        // Si estamos restaurando por URL, el valor puede vivir en data-selected
        // aunque el <select> aun no tenga opciones.
        const preselected = (select.getAttribute('data-selected') || '').toString();
        const desired = current || preselected;
        const optsArr = Array.isArray(options) ? options : (options ? Array.from(options) : []);

        select.innerHTML = '';
        const base = document.createElement('option');
        base.value = '';
        base.textContent = placeholder;
        select.appendChild(base);

        // Si no hay opciones pero el navegador restauró un value "tardío",
        // lo agregamos para evitar que el select quede bloqueado o pierda el valor.
        if (optsArr.length === 0 && desired) {
            const keep = document.createElement('option');
            keep.value = desired;
            keep.textContent = formatter ? formatter(desired) : desired;
            select.appendChild(keep);
        }

        optsArr.forEach(val => {
            const opt = document.createElement('option');
            opt.value = val;
            opt.textContent = formatter ? formatter(val) : val;
            select.appendChild(opt);
        });

        // Mantener el valor actual si existe (exact match o match normalizado)
        let kept = false;
        if (optsArr.includes(desired)) {
            select.value = desired;
            kept = true;
        } else if (desired) {
            const curNorm = normTxt(desired);
            const list = Array.from(select.options || []);
            for (const opt of list) {
                if (!opt) continue;
                if (normTxt(opt.value) === curNorm || normTxt(opt.textContent) === curNorm) {
                    select.value = opt.value;
                    kept = true;
                    break;
                }
            }
        }
        if (!kept) select.value = '';

        // Deshabilitar solo si no hay nada que mostrar (ni opciones ni valor actual)
        const hasRealOptions = (optsArr.length > 0) || !!desired;
        select.disabled = !hasRealOptions;
    }

    const buildKey = (...parts) => parts.filter(Boolean).join('|');

    function actualizarFacultades() {
        // Prioridad: oferta academica (cursos_oferta). Esto evita que se bloqueen por
        // inconsistencias en los data-* de la tabla.
        const ciclo = (filtroCiclo ? filtroCiclo.value : '').toString().trim();
        const currentFac = filtroFacultad ? filtroFacultad.value : '';

        let opciones = [];
        if (ciclo) {
            const set = new Set();
            (ofertasCursos || []).forEach(r => {
                if (!r) return;
                const cid = (r.ciclo_id === undefined || r.ciclo_id === null) ? '' : String(r.ciclo_id).trim();
                if (!cid || cid !== ciclo) return;
                const fac = (r.facultad || '').toString().trim().toUpperCase();
                if (fac) set.add(fac);
            });
            opciones = ordenar(Array.from(set));
        }

        // Fallback: derivar desde filas si no hay oferta disponible.
        if (opciones.length === 0 && ciclo) {
            const set = new Set();
            usuarios.forEach(u => {
                if (ciclo && u.ciclos.length && !u.ciclos.includes(ciclo)) return;
                (u.facultades || []).forEach(f => { if (f) set.add(f); });
            });
            opciones = ordenar(Array.from(set));
        }

        setOptions(filtroFacultad, opciones, 'Seleccionar facultad');

        // Mantener facultad restaurada aunque el setOptions no la haya podido preservar
        // (p.ej. diferencia por tildes o espacios invisibles).
        if (currentFac && filtroFacultad && !valoresEquivalentes(filtroFacultad.value, currentFac)) {
            setSelectValueSmart(filtroFacultad, currentFac);
        }

        // Restaurar desde data-selected (patron igual a Notas) al refrescar.
        const preFac = leerDataSelected(filtroFacultad);
        if (preFac && filtroFacultad && !valoresEquivalentes(filtroFacultad.value, preFac)) {
            setSelectValueSmart(filtroFacultad, preFac);
        }

        actualizarCarreras();
    }

    function actualizarCarreras() {
        const ciclo = (filtroCiclo ? filtroCiclo.value : '').toString().trim();
        const facRaw = (filtroFacultad ? filtroFacultad.value : '').toString();
        const fac = facRaw.trim().toUpperCase();

        let opciones = [];
        if (ciclo && fac) {
            const facNorm = normTxt(fac);
            const set = new Set();
            (ofertasCursos || []).forEach(r => {
                if (!r) return;
                const cid = (r.ciclo_id === undefined || r.ciclo_id === null) ? '' : String(r.ciclo_id).trim();
                if (!cid || cid !== ciclo) return;
                const facR = (r.facultad || '').toString().trim().toUpperCase();
                if (!facR) return;
                if (facNorm && normTxt(facR) !== facNorm) return;
                const car = (r.carrera || '').toString().trim().toUpperCase();
                if (car) set.add(car);
            });
            opciones = ordenar(Array.from(set));
        }

        // Fallback: derivar desde filas si no hay oferta disponible.
        if (opciones.length === 0 && ciclo && fac) {
            const facNorm = normTxt(fac);
            const set = new Set();
            usuarios.forEach(u => {
                if (ciclo && u.ciclos.length && !u.ciclos.includes(ciclo)) return;
                if (facNorm) {
                    const okFac = (u.facultades || []).some(f => normTxt(f) === facNorm);
                    if (!okFac) return;
                }
                (u.carreras || []).forEach(c => { if (c) set.add(c); });
            });
            opciones = ordenar(Array.from(set));
        }

        setOptions(filtroCarrera, opciones, 'Seleccionar carrera');

        const preCar = leerDataSelected(filtroCarrera);
        if (preCar && filtroCarrera && !valoresEquivalentes(filtroCarrera.value, preCar)) {
            setSelectValueSmart(filtroCarrera, preCar);
        }

        actualizarSemestres();
    }

    function actualizarSemestres() {
        const ciclo = (filtroCiclo ? filtroCiclo.value : '').toString().trim();
        const fac = (filtroFacultad ? filtroFacultad.value : '').toString().trim().toUpperCase();
        const car = (filtroCarrera ? filtroCarrera.value : '').toString().trim().toUpperCase();

        let opciones = [];
        if (ciclo && fac && car) {
            const facNorm = normTxt(fac);
            const carNorm = normTxt(car);
            const set = new Set();
            (ofertasCursos || []).forEach(r => {
                if (!r) return;
                const cid = (r.ciclo_id === undefined || r.ciclo_id === null) ? '' : String(r.ciclo_id).trim();
                if (!cid || cid !== ciclo) return;
                const facR = (r.facultad || '').toString().trim().toUpperCase();
                const carR = (r.carrera || '').toString().trim().toUpperCase();
                if (!facR || !carR) return;
                if (facNorm && normTxt(facR) !== facNorm) return;
                if (carNorm && normTxt(carR) !== carNorm) return;
                const sem = (r.semestre === undefined || r.semestre === null) ? '' : String(r.semestre).trim();
                if (sem) set.add(sem);
            });
            opciones = ordenar(Array.from(set));
        }

        // Fallback: derivar desde filas si no hay oferta disponible.
        if (opciones.length === 0 && ciclo && fac && car) {
            const facNorm = normTxt(fac);
            const carNorm = normTxt(car);
            const set = new Set();
            usuarios.forEach(u => {
                if (ciclo && u.ciclos.length && !u.ciclos.includes(ciclo)) return;
                if (facNorm) {
                    const okFac = (u.facultades || []).some(f => normTxt(f) === facNorm);
                    if (!okFac) return;
                }
                if (carNorm) {
                    const okCar = (u.carreras || []).some(c => normTxt(c) === carNorm);
                    if (!okCar) return;
                }
                (u.semestres || []).forEach(s => { if (s) set.add(String(s).trim()); });
            });
            opciones = ordenar(Array.from(set));
        }

        setOptions(filtroSemestre, opciones, 'Seleccionar semestre');

        const preSem = leerDataSelected(filtroSemestre);
        if (preSem && filtroSemestre && !valoresEquivalentes(filtroSemestre.value, preSem)) {
            setSelectValueSmart(filtroSemestre, preSem);
        }

        actualizarMaterias();
    }

    function actualizarMaterias() {
        const ciclo = (filtroCiclo ? filtroCiclo.value : '').toString().trim();
        const fac = (filtroFacultad ? filtroFacultad.value : '').toString().trim().toUpperCase();
        const car = (filtroCarrera ? filtroCarrera.value : '').toString().trim().toUpperCase();
        const sem = (filtroSemestre ? filtroSemestre.value : '').toString().trim();

        let opciones = [];
        if (ciclo && fac && car && sem) {
            const facNorm = normTxt(fac);
            const carNorm = normTxt(car);
            const set = new Set();
            (ofertasCursos || []).forEach(r => {
                if (!r) return;
                const cid = (r.ciclo_id === undefined || r.ciclo_id === null) ? '' : String(r.ciclo_id).trim();
                if (!cid || cid !== ciclo) return;
                const facR = (r.facultad || '').toString().trim().toUpperCase();
                const carR = (r.carrera || '').toString().trim().toUpperCase();
                const semR = (r.semestre === undefined || r.semestre === null) ? '' : String(r.semestre).trim();
                if (!facR || !carR || !semR) return;
                if (facNorm && normTxt(facR) !== facNorm) return;
                if (carNorm && normTxt(carR) !== carNorm) return;
                if (semR !== sem) return;
                const mat = (r.materia || '').toString().trim().toUpperCase();
                if (mat) set.add(mat);
            });
            opciones = ordenar(Array.from(set));
        }

        // Fallback: derivar desde filas si no hay oferta disponible.
        if (opciones.length === 0 && ciclo && fac && car && sem) {
            const facNorm = normTxt(fac);
            const carNorm = normTxt(car);
            const set = new Set();
            usuarios.forEach(u => {
                if (ciclo && u.ciclos.length && !u.ciclos.includes(ciclo)) return;
                if (facNorm) {
                    const okFac = (u.facultades || []).some(f => normTxt(f) === facNorm);
                    if (!okFac) return;
                }
                if (carNorm) {
                    const okCar = (u.carreras || []).some(c => normTxt(c) === carNorm);
                    if (!okCar) return;
                }
                if (sem) {
                    const okSem = (u.semestres || []).some(s => String(s).trim() === sem);
                    if (!okSem) return;
                }
                (u.materias || []).forEach(m => { if (m) set.add(m); });
            });
            opciones = ordenar(Array.from(set));
        }

        setOptions(filtroMateria, opciones, 'Seleccionar materia');

        const preMat = leerDataSelected(filtroMateria);
        if (preMat && filtroMateria && !valoresEquivalentes(filtroMateria.value, preMat)) {
            setSelectValueSmart(filtroMateria, preMat);
        }

        actualizarParalelos();
    }

    function actualizarParalelos() {
        const ciclo = (filtroCiclo ? filtroCiclo.value : '').toString().trim();
        const fac = (filtroFacultad ? filtroFacultad.value : '').toString().trim().toUpperCase();
        const car = (filtroCarrera ? filtroCarrera.value : '').toString().trim().toUpperCase();
        const sem = (filtroSemestre ? filtroSemestre.value : '').toString().trim();
        const mat = (filtroMateria ? filtroMateria.value : '').toString().trim().toUpperCase();

        let opciones = [];
        if (ciclo && fac && car && sem && mat) {
            const facNorm = normTxt(fac);
            const carNorm = normTxt(car);
            const matNorm = normTxt(mat);
            const set = new Set();
            (ofertasCursos || []).forEach(r => {
                if (!r) return;
                const cid = (r.ciclo_id === undefined || r.ciclo_id === null) ? '' : String(r.ciclo_id).trim();
                if (!cid || cid !== ciclo) return;
                const facR = (r.facultad || '').toString().trim().toUpperCase();
                const carR = (r.carrera || '').toString().trim().toUpperCase();
                const semR = (r.semestre === undefined || r.semestre === null) ? '' : String(r.semestre).trim();
                const matR = (r.materia || '').toString().trim().toUpperCase();
                if (!facR || !carR || !semR || !matR) return;
                if (facNorm && normTxt(facR) !== facNorm) return;
                if (carNorm && normTxt(carR) !== carNorm) return;
                if (semR !== sem) return;
                if (matNorm && normTxt(matR) !== matNorm) return;
                const par = (r.paralelo || '').toString().trim().toUpperCase();
                if (par) set.add(par);
            });
            opciones = ordenar(Array.from(set));
        }

        // Fallback: derivar desde filas si no hay oferta disponible.
        if (opciones.length === 0 && ciclo && fac && car && sem && mat) {
            const facNorm = normTxt(fac);
            const carNorm = normTxt(car);
            const matNorm = normTxt(mat);
            const set = new Set();
            usuarios.forEach(u => {
                if (ciclo && u.ciclos.length && !u.ciclos.includes(ciclo)) return;
                if (facNorm) {
                    const okFac = (u.facultades || []).some(f => normTxt(f) === facNorm);
                    if (!okFac) return;
                }
                if (carNorm) {
                    const okCar = (u.carreras || []).some(c => normTxt(c) === carNorm);
                    if (!okCar) return;
                }
                if (sem) {
                    const okSem = (u.semestres || []).some(s => String(s).trim() === sem);
                    if (!okSem) return;
                }
                if (matNorm) {
                    const okMat = (u.materias || []).some(m => normTxt(m) === matNorm);
                    if (!okMat) return;
                }
                (u.paralelos || []).forEach(p => { if (p) set.add(p); });
            });
            opciones = ordenar(Array.from(set));
        }

        setOptions(filtroParalelo, (opciones || []).filter(v => v !== ''), 'Seleccionar curso');

        const prePar = leerDataSelected(filtroParalelo);
        if (prePar && filtroParalelo && !valoresEquivalentes(filtroParalelo.value, prePar)) {
            setSelectValueSmart(filtroParalelo, prePar);
        }
    }

    function filtrar() {
        const cicloVal = filtroCiclo ? filtroCiclo.value : '';
        const facultadVal = (filtroFacultad ? filtroFacultad.value : '').toUpperCase();
        const carreraVal = (filtroCarrera ? filtroCarrera.value : '').toUpperCase();
        const semestreVal = filtroSemestre ? filtroSemestre.value : '';
        const materiaVal = (filtroMateria ? filtroMateria.value : '').toUpperCase();
        const paraleloVal = (filtroParalelo ? filtroParalelo.value : '').toUpperCase();
        const tipoVal = filtroTipo ? filtroTipo.value : '';
        const textoVal = inputBuscador ? inputBuscador.value.toUpperCase().trim() : '';

        const tieneCurso = !!paraleloVal;
        // Solo exigir curso cuando NO se ha seleccionado un tipo.
        // Cuando el tipo esta seleccionado, se filtra por tipo/ciclo/(facultad) y no debe bloquearse.
        const requiereCurso = !tipoVal;
        const ocultarFacCarr = (tipoVal === 'estudiante');
        const esBusquedaTexto = !!textoVal;

        if (!esBusquedaTexto && !cicloVal) {
            mostrarEstadoInicial();
            return;
        }

        // Requerir facultad + carrera solo cuando no hay búsqueda textual
        if (!esBusquedaTexto && (!facultadVal || !carreraVal)) {
            mostrarEsperandoFacCarr();
            return;
        }

        if (!esBusquedaTexto && requiereCurso && !tieneCurso) {
            mostrarEsperandoCurso();
            return;
        }

        let visibles = 0;
        usuarios.forEach(u => {
            let mostrar = true;
            if (cicloVal && u.ciclos.length && !u.ciclos.includes(cicloVal)) mostrar = false;
            if (mostrar && facultadVal && (!u.facultades.length || !u.facultades.includes(facultadVal))) mostrar = false;
            if (mostrar && carreraVal && (!u.carreras.length || !u.carreras.includes(carreraVal))) mostrar = false;
            if (mostrar && semestreVal && (!u.semestres.length || !u.semestres.includes(semestreVal))) mostrar = false;
            if (mostrar && materiaVal && (!u.materias.length || !u.materias.includes(materiaVal))) mostrar = false;
            if (mostrar && paraleloVal && (!u.paralelos.length || !u.paralelos.includes(paraleloVal))) mostrar = false;
            if (mostrar && tipoVal && tipoVal !== 'todos' && u.rol !== tipoVal) mostrar = false;
            if (mostrar && textoVal && !u.nombre.includes(textoVal) && !u.cedula.includes(textoVal)) mostrar = false;

            u.row.style.display = mostrar ? '' : 'none';
            if (mostrar) {
                visibles++;
                const numCell = u.row.querySelector('td:nth-child(2)');
                if (numCell) numCell.textContent = visibles;
            }

            // Mostrar/ocultar columnas de facultad/carrera segun tipo estudiante
            const tdFac = u.row.querySelector('td:nth-child(6)');
            const tdCar = u.row.querySelector('td:nth-child(7)');
            if (tdFac) tdFac.style.display = ocultarFacCarr ? 'none' : '';
            if (tdCar) tdCar.style.display = ocultarFacCarr ? 'none' : '';
        });

        totalSpan.textContent = visibles;
        descripcionFiltro.textContent = construirDescripcion({ cicloVal, facultadVal, carreraVal, semestreVal, materiaVal, paraleloVal, tipoVal, textoVal });

        if (thFacultad) thFacultad.style.display = ocultarFacCarr ? 'none' : '';
        if (thCarrera) thCarrera.style.display = ocultarFacCarr ? 'none' : '';

        if (visibles === 0) {
            sinResultados.style.display = 'block';
            sinResultadosTitulo.textContent = 'No se encontraron usuarios';
            sinResultadosTexto.textContent = 'Ajusta los filtros en cascada o el texto de busqueda.';
            sinResultadosIcono.innerHTML = '&#128533;';
            tabla.style.display = 'none';
        } else {
            sinResultados.style.display = 'none';
            tabla.style.display = 'table';
        }

        if (checkAll) checkAll.checked = false;
    }

    function mostrarEstadoInicial() {
        usuarios.forEach(u => { u.row.style.display = 'none'; });
        if (totalSpan) totalSpan.textContent = '0';
        if (descripcionFiltro) descripcionFiltro.textContent = 'Selecciona un ciclo para iniciar la busqueda';
        if (sinResultados) {
            sinResultados.style.display = 'block';
            sinResultadosTitulo.textContent = 'Seleccione un ciclo para comenzar';
            sinResultadosTexto.textContent = 'Sigue la cascada de filtros para ver los usuarios registrados.';
            sinResultadosIcono.innerHTML = '&#128101;';
        }
        if (tabla) tabla.style.display = 'none';
        if (checkAll) checkAll.checked = false;
        if (thFacultad) thFacultad.style.display = '';
        if (thCarrera) thCarrera.style.display = '';
        filas.forEach(u => {
            const tdFac = u.querySelector('td:nth-child(6)');
            const tdCar = u.querySelector('td:nth-child(7)');
            if (tdFac) tdFac.style.display = '';
            if (tdCar) tdCar.style.display = '';
        });
    }

    function mostrarEsperandoFacCarr() {
        usuarios.forEach(u => { u.row.style.display = 'none'; });
        if (totalSpan) totalSpan.textContent = '0';
        if (descripcionFiltro) descripcionFiltro.textContent = 'Seleccione facultad y carrera dentro del ciclo para ver usuarios';
        if (sinResultados) {
            sinResultados.style.display = 'block';
            sinResultadosTitulo.textContent = 'Seleccione facultad y carrera';
            sinResultadosTexto.textContent = 'Elija la facultad y carrera para listar usuarios de ese ciclo.';
            sinResultadosIcono.innerHTML = '&#128101;';
        }
        if (tabla) tabla.style.display = 'none';
        if (checkAll) checkAll.checked = false;
        if (thFacultad) thFacultad.style.display = '';
        if (thCarrera) thCarrera.style.display = '';
        filas.forEach(u => {
            const tdFac = u.querySelector('td:nth-child(6)');
            const tdCar = u.querySelector('td:nth-child(7)');
            if (tdFac) tdFac.style.display = '';
            if (tdCar) tdCar.style.display = '';
        });
    }

    function mostrarEsperandoCurso() {
        usuarios.forEach(u => { u.row.style.display = 'none'; });
        if (totalSpan) totalSpan.textContent = '0';
        if (descripcionFiltro) descripcionFiltro.textContent = 'Selecciona un curso/paralelo para ver usuarios';
        if (sinResultados) {
            sinResultados.style.display = 'block';
            sinResultadosTitulo.textContent = 'Seleccione un curso';
            sinResultadosTexto.textContent = 'Elija un paralelo luego de materia para mostrar la tabla.';
            sinResultadosIcono.innerHTML = '&#128101;';
        }
        if (tabla) tabla.style.display = 'none';
        if (checkAll) checkAll.checked = false;
        if (thFacultad) thFacultad.style.display = '';
        if (thCarrera) thCarrera.style.display = '';
        filas.forEach(u => {
            const tdFac = u.querySelector('td:nth-child(6)');
            const tdCar = u.querySelector('td:nth-child(7)');
            if (tdFac) tdFac.style.display = '';
            if (tdCar) tdCar.style.display = '';
        });
    }

    function construirDescripcion(f) {
        const partes = [];
        if (f.cicloVal) partes.push('Ciclo ' + (ciclosCatalogo[f.cicloVal] || f.cicloVal));
        if (f.facultadVal) partes.push('Facultad ' + f.facultadVal);
        if (f.carreraVal) partes.push('Carrera ' + f.carreraVal);
        if (f.semestreVal) partes.push('Semestre ' + f.semestreVal);
        if (f.materiaVal) partes.push('Materia ' + f.materiaVal);
        if (f.paraleloVal) partes.push('Curso ' + f.paraleloVal);
        if (f.tipoVal) partes.push('Tipo ' + f.tipoVal.replace('_', ' '));
        if (f.textoVal) partes.push('Busqueda por texto');
        return partes.length ? 'Filtrado por: ' + partes.join(' | ') : 'Mostrando todos los usuarios';
    }

    function limpiarFiltros() {
        if (filtroCiclo) filtroCiclo.value = '';
        if (filtroFacultad) filtroFacultad.value = '';
        if (filtroCarrera) filtroCarrera.value = '';
        if (filtroSemestre) filtroSemestre.value = '';
        if (filtroMateria) filtroMateria.value = '';
        if (filtroParalelo) filtroParalelo.value = '';
        setOptions(filtroFacultad, [], 'Seleccionar facultad');
        setOptions(filtroCarrera, [], 'Seleccionar carrera');
        setOptions(filtroSemestre, [], 'Seleccionar semestre');
        setOptions(filtroMateria, [], 'Seleccionar materia');
        setOptions(filtroParalelo, [], 'Seleccionar curso');
        if (filtroTipo) filtroTipo.value = '';
        if (inputBuscador) inputBuscador.value = '';
        if (checkAll) checkAll.checked = false;
        filtrar();
    }

    function manejarAccionMasiva(inputName, texto, esEliminar) {
        return function() {
            const seleccionados = document.querySelectorAll('.check-usuario:checked');
            if (seleccionados.length === 0) {
                alert('Seleccione al menos un usuario');
                return;
            }
            if (esEliminar) {
                if (!confirm('ATENCION: Esta accion eliminara permanentemente ' + seleccionados.length + ' usuario(s). Continuar?')) return;
            } else {
                if (!confirm(texto + ' ' + seleccionados.length + ' usuario(s) seleccionado(s)?')) return;
            }
            const form = document.getElementById('formUsuariosUnificado');
            if (!form) return;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = inputName;
            input.value = '1';
            form.appendChild(input);
            form.submit();
        };
    }

    function inicializarCiclos() {
        const opciones = (cascadaBase && cascadaBase.ciclos) ? cascadaBase.ciclos : [];
        setOptions(filtroCiclo, opciones, 'Seleccionar ciclo', val => ciclosCatalogo[val] || val);
    }
});
</script>
