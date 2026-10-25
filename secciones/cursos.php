<div class="section-title" data-target="gestionCiclosCursosContent" id="toggleGestionCiclosCursos">
  <span style="display:flex; align-items:center; gap:8px;">
    <span style="background:linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color:white; padding:6px 10px; border-radius:8px; font-size:0.85rem;">IMPORTAR</span>
    Gestión de Ciclos y Oferta Académica
  </span>
  <span class="toggle-btn">▶</span>
</div>

<div id="gestionCiclosCursosContent" class="content-hidden accordion-scroll">
    <div class="accordion-inner">

        <div style="background: #fdfdfe; border: 1px solid #e1e4e8; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
            <h3 style="margin-top:0; color:#1f3b57; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">1. Administrar Ciclos</h3>

            <div style="display:flex; gap:20px; flex-wrap:wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <h4 style="margin-top:0; font-size: 0.95rem; color:#667;">Crear Nuevo Ciclo</h4>
                    <form method="POST" style="display:flex; gap:10px; align-items:end;">
                        <div style="flex:1;">
                            <label style="display:block; font-weight:700; margin-bottom:5px; color:#556; font-size:12px;">Nombre:</label>
                            <input type="text" name="nuevo_ciclo_nombre" placeholder="Ej: 2025-2026 CI" required style="width:100%;">
                        </div>
                        <button type="submit" name="crear_ciclo" class="action-blue" style="height:38px; white-space:nowrap;">➕ Crear</button>
                    </form>
                </div>

                <div style="flex: 1; min-width: 300px;">
                    <h4 style="margin-top:0; font-size: 0.95rem; color:#667;">Ciclos Existentes</h4>
                    <div style="max-height:150px; overflow:auto; border:1px solid #e1e1e1; border-radius:8px;">
                        <table class="tabla-usuarios" style="margin:0;">
                            <thead>
                                <tr>
                                    <th style="padding: 8px;">Nombre</th>
                                    <th style="width:120px; text-align:center; padding: 8px;">Planificación</th>
                                    <th style="width:80px; text-align:center; padding: 8px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($todos_los_ciclos as $c): ?>
                                <tr>
                                    <td style="padding: 8px;"><?= htmlspecialchars($c['nombre']) ?></td>
                                    <td style="text-align:center; padding: 8px;">
                                        <?php if($c['ultimo_archivo_id']): ?>
                                            <a href="admin.php?accion=descargar_archivo&id=<?= $c['ultimo_archivo_id'] ?>" class="action-blue" style="padding: 3px 8px; font-size: 11px; text-decoration: none;" title="Descargar último Excel subido">📥 Excel</a>
                                        <?php else: ?>
                                            <span style="color:#ccc; font-size:11px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center; padding: 8px;">
                                        <form method="POST" style="margin:0;" onsubmit="return confirm('⚠️ ¿ESTÁS SEGURO?\n\nAl eliminar este ciclo, se BORRARÁN PERMANENTEMENTE:\n- Todos los CURSOS de este ciclo.\n- Todos los USUARIOS (Docentes/Estudiantes) creados para este ciclo.\n- Todas las NOTAS y archivos.\n\nEsta acción NO se puede deshacer.');">
                                            <input type="hidden" name="id_ciclo_eliminar" value="<?= $c['id'] ?>">
                                            <button type="submit" name="eliminar_ciclo" class="btn-delete" style="padding:3px 8px; font-size:11px;">🗑️</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($todos_los_ciclos)): ?>
                                    <tr><td colspan="3" style="text-align:center; padding:15px; color:#777;">No hay ciclos registrados.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <h3 style="margin-top:0; color:#1f3b57; border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">2. Gestión de Cursos (Oferta)</h3>

        <div class="ciclo-selector-wrapper" style="background: #eef6fc; padding: 15px; border-radius: 8px; border: 1px solid #dcebf7; margin-bottom: 20px;">
            <label class="ciclo-label" style="margin-bottom: 5px;">Selecciona el Ciclo de Trabajo para ver/editar cursos:</label>
            <select id="cicloSelectorGlobal" class="ciclo-select-main" onchange="cambiarCicloGlobal(this.value)">
                <option value="0">-- Selecciona un ciclo --</option>
                <?php foreach($todos_los_ciclos as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $ciclo_filtro_id == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="contentForCycle" style="<?= $ciclo_filtro_id > 0 ? '' : 'display:none;' ?>">

            <?php if (!empty($import_msg)): ?>
              <div class="import-box" style="border-color:#93c5fd;background:#eff6ff;margin:0 0 14px;"><strong><?= htmlspecialchars($import_msg) ?></strong></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="import-box" style="margin:0 0 14px;">
              <input type="hidden" name="ciclo_id" value="<?= $ciclo_filtro_id ?>">
              <input type="file" name="archivos_cursos[]" accept=".xlsx,.csv" multiple required>
              <button type="submit" name="importar_excel" class="action-blue">⤴️ Importar Excel al Ciclo Seleccionado</button>
              <div class="import-hint">Columnas requeridas: <b>FACULTAD | CARRERA | SEMESTRE | MATERIA | PARALELO</b>.</div>
            </form>

            <?php if (!empty($archivos_ciclo)): ?>
                <div style="margin-bottom: 20px; background: #fff; border: 1px solid #e1e4e8; border-radius: 8px; padding: 15px;">
                    <h4 style="margin-top:0; margin-bottom:10px; font-size: 0.95rem; color:#667;">Archivos de Planificación Subidos</h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach($archivos_ciclo as $arch): ?>
                            <li style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #eee;">
                                <span style="font-size: 13px; color: #333;">
                                    📄 <b><?= htmlspecialchars($arch['nombre_archivo']) ?></b>
                                    <small style="color:#999; margin-left: 5px;">(<?= date('d/m/Y H:i', strtotime($arch['fecha_subida'])) ?>)</small>
                                </span>
                                <div style="display: flex; gap: 5px;">
                                    <!-- Botón Descargar -->
                                    <a href="admin.php?accion=descargar_archivo&id=<?= $arch['id'] ?>" class="action-blue" style="padding: 4px 10px; font-size: 11px; text-decoration: none; height: auto; border-radius: 6px;">⬇️ Descargar</a>
                                    
                                    <!-- Botón Borrar (Nuevo) -->
                                    <form method="POST" style="margin:0;" onsubmit="return confirm('⚠️ ALERTA DE BORRADO\n\nEstás a punto de borrar el archivo de planificación: \'<?= htmlspecialchars($arch['nombre_archivo']) ?>\'.\n\nESTO PROVOCARÁ:\n1. La eliminación de TODOS los cursos creados por este archivo.\n2. La desvinculación de docentes y estudiantes de estos cursos.\n3. La eliminación de exámenes y notas vinculadas.\n\n¿Estás seguro de proceder?');">
                                        <input type="hidden" name="id_archivo_planificacion" value="<?= $arch['id'] ?>">
                                        <input type="hidden" name="ciclo_redirect" value="<?= $ciclo_filtro_id ?>">
                                        <button type="submit" name="eliminar_archivo_planificacion" class="btn-delete" style="padding: 4px 10px; font-size: 11px; height: auto; border-radius: 6px; cursor: pointer;">🗑️ Borrar</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div id="gestionCursosContainer">
              <div class="group-actions" style="margin:0 0 10px;">
                <button id="btnToggleSemestre" class="action-blue action-btn" type="button">➕ Agregar Semestre Completo</button>
                <button id="btnToggleIndividual" class="action-blue action-btn" type="button">➕ Añadir Curso Individual</button>
              </div>

              <form method="POST" id="formSemestre" class="form-semestre">
                <h4>Añadir Materia a Múltiples Paralelos (Ciclo Actual)</h4>
                <input type="hidden" name="ciclo_id" value="<?= $ciclo_filtro_id ?>">
                <label>Facultad:</label>
                <input list="facultades" name="facultad_semestre" placeholder="Ej: CIENCIAS MATEMATICAS" required>
                <datalist id="facultades"><?php if($lista_facultades) while($r=$lista_facultades->fetch_assoc()) echo "<option value=\"".htmlspecialchars($r['nombre'])."\">"; ?></datalist>
                <div class="input-group">
                    <div style="flex:2"><label>Carrera:</label><input type="text" name="carrera_semestre" required></div>
                    <div style="flex:1"><label>Nivel:</label><input type="number" name="nivel" min="1" max="12" required></div>
                </div>
                <label>Materia:</label>
                <input type="text" name="materia_semestre" required>
                <label>Códigos de Paralelo (separados por coma):</label>
                <textarea name="paralelos_semestre" placeholder="Ej: MA-1-1, MA-1-2" required></textarea>
                <button type="submit" name="agregar_semestre">Crear Cursos</button>
              </form>

              <form method="POST" id="formIndividual" class="form-curso">
                <h4>Añadir Curso Individual (Ciclo Actual)</h4>
                <input type="hidden" name="ciclo_id" value="<?= $ciclo_filtro_id ?>">
                <div class="input-group">
                  <input list="facultades2" name="facultad_individual" placeholder="Facultad" required>
                  <datalist id="facultades2"><?php $lista_facultades->data_seek(0); while($r=$lista_facultades->fetch_assoc()) echo "<option value=\"".htmlspecialchars($r['nombre'])."\">"; ?></datalist>
                </div>
                <div class="input-group">
                  <input type="text" name="carrera" placeholder="Carrera" required>
                  <input type="number" name="semestre_individual" min="1" placeholder="Semestre" required>
                </div>
                <div class="input-group">
                  <input type="text" name="materia" placeholder="Materia" required>
                  <input type="text" name="paralelo" placeholder="Paralelo" required>
                </div>
                <button type="submit" name="agregar">Agregar Curso</button>
              </form>
            </div>

        </div>

        <div id="alertNoCycle" class="alert-select-ciclo" style="<?= $ciclo_filtro_id > 0 ? 'display:none;' : '' ?>">
            ⚠️ <b>Atención:</b> Selecciona un ciclo arriba para gestionar sus cursos.
        </div>

        <form method="POST" id="formTablaCursos" style="padding: 10px 0;">
            <div class="table-actions">
              <button type="submit" name="guardar_todos" class="btn-save">💾 Guardar cambios</button>
              <button type="submit" name="eliminar_seleccion" class="btn-delete" onclick="return confirmarEliminarSeleccion();">🗑️ Eliminar seleccionados</button>
              <label class="select-all"><input type="checkbox" id="checkAllTop"> Seleccionar todo</label>
            </div>
            <input type="hidden" name="eliminar_todo" id="eliminar_todo" value="0">

            <div class="buscador-container">
              <input type="text" id="inputBuscador" placeholder="Buscar por Semestre, Materia, Paralelo, Carrera o Facultad">
              <button id="btnBuscar" type="button">Buscar</button>
            </div>

            <div id="cursosTablaWrap" class="tabla-wrap">
              <table id="tablaCursosEdicion">
                <thead>
                  <tr>
                    <th><input type="checkbox" id="checkAll"></th>
                    <th>Ciclo</th>
                    <th>Semestre</th>
                    <th>Paralelo</th>
                    <th>Materia</th>
                    <th>Carrera</th>
                    <th>Facultad</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while($c = $cursos->fetch_assoc()): $id = (int)$c["id_oferta"]; ?>
                  <tr data-ciclo-id="<?= $c['ciclo_id'] ?>" style="<?= ($ciclo_filtro_id > 0 && $c['ciclo_id'] != $ciclo_filtro_id) ? 'display:none;' : '' ?>">
                    <td><input type="checkbox" class="row-check" name="sel[]" value="<?= $id ?>"></td>
                    <td><input type="text"  name="ciclo[<?= $id ?>]"     value="<?= htmlspecialchars($c["ciclo"]) ?>" required readonly style="background:#eee; color:#666;"></td>
                    <td><input type="number" name="semestre[<?= $id ?>]"  value="<?= htmlspecialchars($c["semestre"]) ?>" min="1" max="12" required></td>
                    <td><input type="text"  name="paralelo[<?= $id ?>]"   value="<?= htmlspecialchars($c["paralelo"]) ?>" required></td>
                    <td><input type="text"  name="materia[<?= $id ?>]"    value="<?= htmlspecialchars($c["materia"]) ?>" required></td>
                    <td><input type="text"  name="carrera[<?= $id ?>]"    value="<?= htmlspecialchars($c["carrera"]) ?>" required></td>
                    <td><input type="text"  name="facultad[<?= $id ?>]"   value="<?= htmlspecialchars($c["facultad"]) ?>" required></td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
        </form>

    </div>
</div>

<?php if (false): ?>
<div class="section-title" data-target="tablaRestriccionesContent" id="toggleRestricciones">
  Gestión de Restricciones (Flujo de Materias) <span class="toggle-btn">▶</span>
</div>

<div id="tablaRestriccionesContent" class="content-hidden accordion-scroll">
  <div class="accordion-inner">

    <div class="restriccion-info-card" style="background:#f8fbff; padding:15px; border-radius:12px; margin:0 0 15px; border:1px solid #d6dde5;">
      <h4 style="margin-top:0; color:#2b2f33;">¿Qué hace esta sección?</h4>
      <p style="font-size:14px; margin-bottom:10px; color:#4b5563;">
        Aquí defines pares de materias que son <b>mutuamente excluyentes</b>.
        Si un estudiante escoge la <b>Materia A</b>, el sistema no le permitirá inscribirse en la <b>Materia B</b> (y viceversa).
      </p>

      <form method="POST" style="display:inline-block; margin-right:10px;">
        <button type="submit" name="eliminar_todas_restricciones" class="action-blue" style="background:#dc3545;" onclick="return confirm('⚠️ ¿Estás seguro de ELIMINAR TODAS las restricciones de materias? Esta acción no se puede deshacer.');">
          🗑️ Borrar Todas las Reglas
        </button>
      </form>

      <form method="POST" enctype="multipart/form-data" style="display:block; margin-top:12px;">
        <label style="font-size:13px; font-weight:800; color:#334155; display:block; margin-bottom:4px;">
          📥 Importar flujo de materias desde Excel / CSV
        </label>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
          <input type="file" name="archivo_flujo_materias" accept=".xlsx,.csv" required>
          <button type="submit" name="importar_flujo_materias" class="action-blue" style="background:#198754;">
            ⤴️ Cargar archivo de flujo
          </button>
        </div>
        <div style="font-size:12px; color:#6b7280; margin-top:6px; line-height:1.4;">
          <b>Formato del archivo (Excel o CSV):</b><br>
          ▪ Primera fila son los encabezados.<br>
          ▪ Columnas obligatorias: <code>Materia A</code> y <code>Materia B</code>.<br>
          ▪ Los nombres deben coincidir con los de la tabla <code>materias</code>.<br>
        </div>
        <table style="margin-top:6px; border-collapse:collapse; font-size:11px;">
          <thead>
            <tr>
              <th style="border:1px solid #d6dde5; padding:4px 6px; background:#eef5ff;">Materia A</th>
              <th style="border:1px solid #d6dde5; padding:4px 6px; background:#eef5ff;">Materia B</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td style="border:1px solid #d6dde5; padding:4px 6px;">BASE DE DATOS</td>
              <td style="border:1px solid #d6dde5; padding:4px 6px;">FUNDAMENTOS DE TI</td>
            </tr>
          </tbody>
        </table>
      </form>
    </div>

    <div class="restriccion-grid" style="display:flex; gap:20px; margin:0; flex-wrap:wrap;">
      <div style="flex:1; min-width:320px; background:#fbfbfd; padding:15px; border-radius:12px; border:1px solid #e6e9ee;">
        <h4 style="margin-top:0;">Agregar Grupo de Restricciones Manual</h4>
        <p style="font-size:12px; color:#6b7280;">Agregue 2 o más materias. Todas serán mutuamente excluyentes entre sí.</p>

        <form method="POST" id="formGrupoRestricciones">
          <div id="listaMateriasInputs">
            <div class="input-group-dynamic" style="margin-bottom:10px;">
              <label>Materia A:</label>
              <input list="lista_materias_dl" name="materias_grupo[]" class="input-full" style="width:100%;" required placeholder="Buscar materia...">
            </div>
            <div class="input-group-dynamic" style="margin-bottom:10px;">
              <label>Materia B:</label>
              <input list="lista_materias_dl" name="materias_grupo[]" class="input-full" style="width:100%;" required placeholder="Buscar materia...">
            </div>
          </div>

          <button type="button" id="btnAddMateriaInput" style="background:#6c757d; margin-bottom:10px; padding:9px; font-size:13px;">+ Agregar otra materia</button>

          <button type="submit" name="agregar_grupo_restriccion" style="width:100%;">🔗 Crear Restricciones (Grupo)</button>
        </form>

        <datalist id="lista_materias_dl">
          <?php foreach($arr_materias as $nm): ?>
            <option value="<?= htmlspecialchars($nm) ?>">
          <?php endforeach; ?>
        </datalist>
      </div>

      <div style="flex:2; min-width:360px; max-height:420px; overflow:auto; border:1px solid #e6e9ee; border-radius:12px; background:#fff;">
        <table class="tabla-restricciones">
          <thead>
            <tr>
              <th>Materia A</th>
              <th><-></th>
              <th>Materia B</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <?php if($lista_restricciones && $lista_restricciones->num_rows > 0): ?>
              <?php while($row = $lista_restricciones->fetch_assoc()): ?>
                <tr>
                  <td><?= htmlspecialchars($row['materia_a']) ?></td>
                  <td style="text-align:center; font-weight:800; color:#dc3545;">X</td>
                  <td><?= htmlspecialchars($row['materia_b']) ?></td>
                  <td>
                    <form method="POST" style="margin:0;">
                      <input type="hidden" name="id_restriccion" value="<?= $row['id'] ?>">
                      <button type="submit" name="eliminar_restriccion" style="padding:6px 10px; font-size:12px; background:#dc3545;">Eliminar</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="4" style="text-align:center; padding:20px; color:#777;">No hay restricciones definidas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
<?php endif; ?>
