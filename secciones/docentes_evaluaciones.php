<?php
$sel_ciclo = $doc_eval_seleccion['ciclo'] ?? '';
$sel_fac = $doc_eval_seleccion['facultad'] ?? '';
$sel_car = $doc_eval_seleccion['carrera'] ?? '';
$sel_sem = $doc_eval_seleccion['semestre'] ?? '';
$sel_mat = $doc_eval_seleccion['materia'] ?? '';
?>

<style>
  .doc-eval-card { background:#f8fbff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:16px; }
  .doc-eval-grid { display:flex; flex-direction:column; gap:10px; align-items:flex-start; }
  .doc-eval-field { display:flex; flex-direction:row; align-items:center; gap:12px; width:100%; max-width:520px; }
  .doc-eval-field label { font-size:12px; font-weight:800; color:#334155; width:120px; flex-shrink:0; }
  .doc-eval-field select { flex:1; min-width:0; }
  .doc-eval-actions { display:flex; gap:10px; align-items:center; width:100%; max-width:520px; }
  .doc-eval-actions button { height:38px; }
  .doc-eval-hint { margin-top:8px; font-size:12px; color:#6b7280; }
  .doc-eval-pill { display:inline-block; background:#f1f5f9; border:1px solid #e2e8f0; padding:3px 8px; border-radius:999px; font-size:11px; font-weight:700; }
  .doc-eval-role { font-weight:800; font-size:12px; }
  .doc-eval-empty { color:#9ca3af; font-style:italic; }
  .doc-eval-btn { background:none; border:none; color:#0f6cb6; font-weight:800; cursor:pointer; padding:0; }
  .doc-eval-btn:hover { text-decoration:underline; }

  .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:2000; justify-content:center; align-items:center; }
  .modal-content { background:#fff; padding:20px; border-radius:10px; width:500px; max-height:90vh; overflow-y:auto; }
  .modal-wide { width:90%; max-width:1000px; }
  .modal-header { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:10px; margin-bottom:15px; gap:10px; }
  .close-btn { background:none; border:none; font-size:1.5rem; cursor:pointer; color:#777; }
  .doc-eval-meta { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:12px; }
  .doc-eval-meta span { background:#f8fafc; border:1px solid #e2e8f0; padding:6px 10px; border-radius:10px; font-size:12px; font-weight:700; color:#1f2937; }
</style>

<h3 style="margin:0 0 14px;">Docentes y Evaluaciones</h3>

<div class="doc-eval-card">
  <form method="GET" action="admin.php" id="formDocEvalFiltro">
    <input type="hidden" name="seccion" value="docentes_evaluaciones">
    <div class="doc-eval-grid">
      <div class="doc-eval-field">
        <label>PERIODOS:</label>
        <select name="de_ciclo" id="docEvalCiclo" data-selected="<?= htmlspecialchars($sel_ciclo) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>

      <div class="doc-eval-field">
        <label>FACULTADES:</label>
        <select name="de_facultad" id="docEvalFacultad" data-selected="<?= htmlspecialchars($sel_fac) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>

      <div class="doc-eval-field">
        <label>CARRERAS:</label>
        <select name="de_carrera" id="docEvalCarrera" data-selected="<?= htmlspecialchars($sel_car) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>

      <div class="doc-eval-field">
        <label>Semestre</label>
        <select name="de_semestre" id="docEvalSemestre" data-selected="<?= htmlspecialchars($sel_sem) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>

      <div class="doc-eval-field">
        <label>Materia</label>
        <select name="de_materia" id="docEvalMateria" data-selected="<?= htmlspecialchars($sel_mat) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>

      <div class="doc-eval-actions">
        <button type="submit" class="action-blue">Ver docentes</button>
        <button type="button" class="btn-save" style="background:#64748b;" onclick="limpiarDocEvalFiltro()">Limpiar</button>
      </div>
    </div>
  </form>
  <div class="doc-eval-hint">Selecciona ciclo, facultad, carrera, semestre y materia para ver los docentes y sus evaluaciones.</div>
</div>

<?php if ($sel_ciclo !== '' && $sel_fac !== '' && $sel_car !== '' && $sel_sem !== '' && $sel_mat !== ''): ?>
  <?php if (empty($doc_eval_resultados)): ?>
    <div class="alert-select-ciclo">No se encontraron cursos o docentes para los filtros seleccionados.</div>
  <?php else: ?>
    <div id="docEvalTablaWrap" class="tabla-wrap" style="height:560px;">
      <table class="tabla-usuarios">
        <thead>
          <tr>
            <th>Materia</th>
            <th>Paralelo</th>
            <th>Semestre</th>
            <th>Docente</th>
            <th>Rol</th>
            <th>Total Estudiantes</th>
            <th>Evaluados</th>
            <th>No Evaluados</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($doc_eval_resultados as $idx => $row): ?>
            <?php
              $curso = $row['curso'];
              $doc = $row['docente'];
              $evaluados = $row['evaluados'];
              $no_eval = $row['no_evaluados'];
              $rol = $doc['rol'] ?? 'docente';
              $rol_label = ($rol === 'director_carrera') ? 'Director de Carrera' : (($rol === 'director_area') ? 'Director de Area' : 'Docente');
              $count_eval = count($evaluados);
              $count_no_eval = count($no_eval);
            ?>
            <tr>
              <td><?= htmlspecialchars($curso['materia']) ?></td>
              <td><?= htmlspecialchars($curso['paralelo']) ?></td>
              <td style="text-align:center;"><?= htmlspecialchars($curso['semestre']) ?></td>
              <td><?= $doc ? htmlspecialchars($doc['nombres']) : '<span class="doc-eval-empty">Sin docente asignado</span>' ?></td>
              <td><span class="doc-eval-role"><?= htmlspecialchars($rol_label) ?></span></td>
              <td style="text-align:center; font-weight:800;"><?= (int)$row['total_estudiantes'] ?></td>
              <td>
                <?php if ($count_eval === 0): ?>
                  <span class="doc-eval-empty">Ninguno</span>
                <?php else: ?>
                  <button type="button" class="doc-eval-btn" onclick="abrirDocEvalDetalle(<?= (int)$idx ?>, 'evaluados')">Ver (<?= $count_eval ?>)</button>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($count_no_eval === 0): ?>
                  <span class="doc-eval-empty">Ninguno</span>
                <?php else: ?>
                  <button type="button" class="doc-eval-btn" onclick="abrirDocEvalDetalle(<?= (int)$idx ?>, 'no_evaluados')">Ver (<?= $count_no_eval ?>)</button>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div id="modalDocEval" class="modal">
  <div class="modal-content modal-wide">
    <div class="modal-header">
      <h3 id="docEvalModalTitle" style="margin:0;">Detalle de Estudiantes</h3>
      <div style="display:flex; gap:8px; align-items:center;">
        <button type="button" class="btn-save" style="padding:6px 12px; height:34px;" onclick="exportarDocEvalExcel()">Descargar Excel</button>
        <button class="close-btn" type="button" onclick="cerrarDocEval()">&times;</button>
      </div>
    </div>
    <div id="docEvalMeta" class="doc-eval-meta"></div>
    <div id="docEvalTabla"></div>
  </div>
</div>

<script>
  const docEvalMap = <?php echo json_encode($doc_eval_combo_map ?? [], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;
  const docEvalData = <?php echo json_encode($doc_eval_resultados ?? [], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
  let docEvalCurrent = null;

  function rolLabel(rol) {
    if (rol === 'director_carrera') return 'Director de Carrera';
    if (rol === 'director_area') return 'Director de Area';
    return 'Docente';
  }

  function resetSelect(sel) {
    sel.innerHTML = '<option value="">-- Seleccionar --</option>';
  }

  function ordenarNumeros(a, b) {
    const na = parseInt(a, 10);
    const nb = parseInt(b, 10);
    if (isNaN(na) || isNaN(nb)) return a.localeCompare(b);
    return na - nb;
  }

  function cargarCiclos() {
    const selCiclo = document.getElementById('docEvalCiclo');
    const selFac = document.getElementById('docEvalFacultad');
    const selCar = document.getElementById('docEvalCarrera');
    const selSem = document.getElementById('docEvalSemestre');
    const selMat = document.getElementById('docEvalMateria');

    resetSelect(selCiclo);
    resetSelect(selFac);
    resetSelect(selCar);
    resetSelect(selSem);
    resetSelect(selMat);

    selFac.disabled = true;
    selCar.disabled = true;
    selSem.disabled = true;
    selMat.disabled = true;

    const ciclosMap = new Map();
    Object.entries(docEvalMap || {}).forEach(([fac, cars]) => {
      Object.entries(cars || {}).forEach(([car, ciclos]) => {
        Object.entries(ciclos || {}).forEach(([cid, data]) => {
          if (!ciclosMap.has(cid)) ciclosMap.set(cid, (data && data.nombre) ? data.nombre : cid);
        });
      });
    });

    if (ciclosMap.size === 0) { selCiclo.disabled = true; return; }

    selCiclo.disabled = false;
    Array.from(ciclosMap.entries())
      .sort((a, b) => a[1].localeCompare(b[1], 'es', { numeric:true, sensitivity:'base' }))
      .forEach(([cid, name]) => {
        const opt = document.createElement('option');
        opt.value = cid;
        opt.textContent = name;
        selCiclo.appendChild(opt);
      });

    const preCiclo = selCiclo.getAttribute('data-selected') || '';
    if (preCiclo) selCiclo.value = preCiclo;
    cargarFacultades();
  }

  function cargarFacultades() {
    const selFac = document.getElementById('docEvalFacultad');
    const selCar = document.getElementById('docEvalCarrera');
    const selSem = document.getElementById('docEvalSemestre');
    const selCiclo = document.getElementById('docEvalCiclo');
    const selMat = document.getElementById('docEvalMateria');

    resetSelect(selFac);
    resetSelect(selCar);
    resetSelect(selSem);
    resetSelect(selMat);

    selFac.disabled = true;
    selCar.disabled = true;
    selSem.disabled = true;
    selMat.disabled = true;

    const cicloVal = selCiclo.value;
    if (!cicloVal || !docEvalMap || Object.keys(docEvalMap).length === 0) return;

    const facSet = new Set();
    Object.entries(docEvalMap).forEach(([fac, cars]) => {
      Object.values(cars || {}).forEach(ciclos => { if (ciclos && ciclos[cicloVal]) facSet.add(fac); });
    });

    if (facSet.size === 0) return;

    selFac.disabled = false;
    Array.from(facSet).sort((a,b)=>a.localeCompare(b)).forEach(f=>{
      const opt=document.createElement('option');
      opt.value=f; opt.textContent=f; selFac.appendChild(opt);
    });

    const preFac = selFac.getAttribute('data-selected') || '';
    if (preFac) selFac.value = preFac;
    cargarCarreras();
  }

  function cargarCarreras() {
    const selFac = document.getElementById('docEvalFacultad');
    const selCar = document.getElementById('docEvalCarrera');
    const selSem = document.getElementById('docEvalSemestre');
    const selCiclo = document.getElementById('docEvalCiclo');
    const selMat = document.getElementById('docEvalMateria');

    resetSelect(selCar);
    resetSelect(selSem);
    resetSelect(selMat);

    selSem.disabled = true;
    selMat.disabled = true;

    const facVal = selFac.value;
    const cicloVal = selCiclo.value;
    if (!facVal || !cicloVal || !docEvalMap[facVal]) { selCar.disabled = true; return; }

    const carSet = new Set();
    Object.entries(docEvalMap[facVal]).forEach(([car,ciclos])=>{ if(ciclos && ciclos[cicloVal]) carSet.add(car); });

    if (carSet.size === 0) { selCar.disabled = true; return; }

    selCar.disabled = false;
    Array.from(carSet).sort((a,b)=>a.localeCompare(b)).forEach(c=>{
      const opt=document.createElement('option'); opt.value=c; opt.textContent=c; selCar.appendChild(opt);
    });

    const preCar = selCar.getAttribute('data-selected') || '';
    if (preCar) selCar.value = preCar;
    cargarSemestres();
  }

  function cargarSemestres() {
    const selFac = document.getElementById('docEvalFacultad');
    const selCar = document.getElementById('docEvalCarrera');
    const selSem = document.getElementById('docEvalSemestre');
    const selCiclo = document.getElementById('docEvalCiclo');
    const selMat = document.getElementById('docEvalMateria');

    resetSelect(selSem);
    resetSelect(selMat);
    selMat.disabled = true;

    const facVal = selFac.value;
    const carVal = selCar.value;
    const cicloVal = selCiclo.value;
    if (!facVal || !carVal || !cicloVal || !docEvalMap[facVal] || !docEvalMap[facVal][carVal] || !docEvalMap[facVal][carVal][cicloVal]) {
      selSem.disabled = true;
      return;
    }

    selSem.disabled = false;
    const semestres = docEvalMap[facVal][carVal][cicloVal].semestres || {};
    Object.keys(semestres).sort(ordenarNumeros).forEach(s => {
      const opt = document.createElement('option');
      opt.value = s;
      opt.textContent = s;
      selSem.appendChild(opt);
    });

    const preSem = selSem.getAttribute('data-selected') || '';
    if (preSem) selSem.value = preSem;
    cargarMaterias();
  }

  function cargarMaterias() {
    const selFac = document.getElementById('docEvalFacultad');
    const selCar = document.getElementById('docEvalCarrera');
    const selSem = document.getElementById('docEvalSemestre');
    const selCiclo = document.getElementById('docEvalCiclo');
    const selMat = document.getElementById('docEvalMateria');

    resetSelect(selMat);

    const facVal = selFac.value;
    const carVal = selCar.value;
    const semVal = selSem.value;
    const cicloVal = selCiclo.value;
    if (!facVal || !carVal || !cicloVal || !semVal || !docEvalMap[facVal] || !docEvalMap[facVal][carVal] || !docEvalMap[facVal][carVal][cicloVal]) {
      selMat.disabled = true;
      return;
    }

    selMat.disabled = false;
    const semestres = docEvalMap[facVal][carVal][cicloVal].semestres || {};
    const materias = semestres[semVal] || [];
    materias.sort((a, b) => a.localeCompare(b)).forEach(m => {
      const opt = document.createElement('option');
      opt.value = m;
      opt.textContent = m;
      selMat.appendChild(opt);
    });

    const preMat = selMat.getAttribute('data-selected') || '';
    if (preMat) selMat.value = preMat;
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function abrirDocEvalDetalle(idx, tipo) {
    const row = docEvalData[idx];
    if (!row) return;

    const curso = row.curso || {};
    const docente = row.docente || null;
    const estudiantes = (tipo === 'evaluados') ? (row.evaluados || []) : (row.no_evaluados || []);
    const tipoLabel = (tipo === 'evaluados') ? 'Evaluados' : 'No Evaluados';
    const docenteNombre = docente ? docente.nombres : 'Sin docente asignado';
    const rol = docente ? docente.rol : 'docente';
    const rolTxt = rolLabel(rol);

    docEvalCurrent = {
      tipo: tipo,
      tipoLabel: tipoLabel,
      curso: curso,
      docente: docenteNombre,
      rol: rolTxt,
      total: row.total_estudiantes || 0,
      evaluados: row.evaluados || [],
      no_evaluados: row.no_evaluados || [],
      estudiantes: estudiantes
    };

    document.getElementById('docEvalModalTitle').textContent = tipoLabel + ' - ' + (curso.materia || '') + ' | ' + (curso.paralelo || '');

    const metaHtml = [
      '<span>Ciclo: ' + escapeHtml(curso.ciclo || '') + '</span>',
      '<span>Facultad: ' + escapeHtml(curso.facultad || '') + '</span>',
      '<span>Carrera: ' + escapeHtml(curso.carrera || '') + '</span>',
      '<span>Semestre: ' + escapeHtml(curso.semestre || '') + '</span>',
      '<span>Materia: ' + escapeHtml(curso.materia || '') + '</span>',
      '<span>Paralelo: ' + escapeHtml(curso.paralelo || '') + '</span>',
      '<span>Docente: ' + escapeHtml(docenteNombre) + '</span>',
      '<span>Rol: ' + escapeHtml(rolTxt) + '</span>'
    ].join('');
    document.getElementById('docEvalMeta').innerHTML = metaHtml;

    let tableHtml = '';
    if (!estudiantes.length) {
      tableHtml = '<div class="doc-eval-empty">Sin estudiantes en este estado.</div>';
    } else {
      tableHtml += '<div class="tabla-wrap" style="height:360px; margin:0;">';
      tableHtml += '<table class="tabla-usuarios">';
      tableHtml += '<thead><tr><th>#</th><th>Estudiante</th><th>Cedula</th></tr></thead><tbody>';
      estudiantes.forEach((est, i) => {
        tableHtml += '<tr>';
        tableHtml += '<td>' + (i + 1) + '</td>';
        tableHtml += '<td>' + escapeHtml(est.nombres || '') + '</td>';
        tableHtml += '<td>' + escapeHtml(est.cedula || '') + '</td>';
        tableHtml += '</tr>';
      });
      tableHtml += '</tbody></table></div>';
    }
    document.getElementById('docEvalTabla').innerHTML = tableHtml;
    document.getElementById('modalDocEval').style.display = 'flex';
  }

  function cerrarDocEval() {
    document.getElementById('modalDocEval').style.display = 'none';
  }

  function exportarDocEvalExcel() {
    if (!docEvalCurrent) { alert('No hay datos para exportar.'); return; }
    const estudiantes = docEvalCurrent.estudiantes || [];
    if (!estudiantes.length) { alert('No hay datos para exportar.'); return; }

    const curso = docEvalCurrent.curso || {};
    const estado = docEvalCurrent.tipoLabel || '';

    let html = '<table>';
    html += '<thead><tr>';
    html += '<th>#</th><th>Estudiante</th><th>Cedula</th><th>Ciclo</th><th>Facultad</th><th>Carrera</th><th>Semestre</th><th>Materia</th><th>Paralelo</th><th>Docente</th><th>Rol</th><th>Estado</th>';
    html += '</tr></thead><tbody>';

    estudiantes.forEach((est, idx) => {
      html += '<tr>';
      html += '<td>' + (idx + 1) + '</td>';
      html += '<td>' + escapeHtml(est.nombres || '') + '</td>';
      html += '<td>' + escapeHtml(est.cedula || '') + '</td>';
      html += '<td>' + escapeHtml(curso.ciclo || '') + '</td>';
      html += '<td>' + escapeHtml(curso.facultad || '') + '</td>';
      html += '<td>' + escapeHtml(curso.carrera || '') + '</td>';
      html += '<td>' + escapeHtml(curso.semestre || '') + '</td>';
      html += '<td>' + escapeHtml(curso.materia || '') + '</td>';
      html += '<td>' + escapeHtml(curso.paralelo || '') + '</td>';
      html += '<td>' + escapeHtml(docEvalCurrent.docente || '') + '</td>';
      html += '<td>' + escapeHtml(docEvalCurrent.rol || '') + '</td>';
      html += '<td>' + escapeHtml(estado) + '</td>';
      html += '</tr>';
    });

    html += '</tbody></table>';
    const blob = new Blob(['\ufeff', '<meta charset="UTF-8">' + html], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'docentes_evaluacion_' + new Date().toISOString().slice(0,10) + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function limpiarDocEvalFiltro() {
    const selCiclo = document.getElementById('docEvalCiclo');
    const selFac = document.getElementById('docEvalFacultad');
    const selCar = document.getElementById('docEvalCarrera');
    const selSem = document.getElementById('docEvalSemestre');
    const selMat = document.getElementById('docEvalMateria');

    [selCiclo, selFac, selCar, selSem, selMat].forEach(s => {
      if (!s) return;
      s.setAttribute('data-selected', '');
      s.value = '';
    });
    cargarCiclos();
    const form = document.getElementById('formDocEvalFiltro');
    if (form) form.submit(); // recarga y limpia la tabla de resultados
  }

  document.addEventListener('DOMContentLoaded', function() {
    const selFac = document.getElementById('docEvalFacultad');
    const selCar = document.getElementById('docEvalCarrera');
    const selSem = document.getElementById('docEvalSemestre');
    const selCiclo = document.getElementById('docEvalCiclo');
    const selMat = document.getElementById('docEvalMateria');
    const modal = document.getElementById('modalDocEval');

    if (!selFac || !selCar || !selSem || !selCiclo || !selMat) return;

    selCiclo.addEventListener('change', function() {
      selFac.setAttribute('data-selected', '');
      selCar.setAttribute('data-selected', '');
      selSem.setAttribute('data-selected', '');
      selMat.setAttribute('data-selected', '');
      cargarFacultades();
    });

    selFac.addEventListener('change', function() {
      selCar.setAttribute('data-selected', '');
      selSem.setAttribute('data-selected', '');
      selMat.setAttribute('data-selected', '');
      cargarCarreras();
    });

    selCar.addEventListener('change', function() {
      selSem.setAttribute('data-selected', '');
      selMat.setAttribute('data-selected', '');
      cargarSemestres();
    });

    selSem.addEventListener('change', function() {
      selMat.setAttribute('data-selected', '');
      cargarMaterias();
    });

    selMat.addEventListener('change', function() {
      if (selFac.value && selCar.value && selCiclo.value && selSem.value && selMat.value) {
        document.getElementById('formDocEvalFiltro').submit();
      }
    });

    if (modal) {
      modal.addEventListener('click', function(e) {
        if (e.target === modal) cerrarDocEval();
      });
    }

    cargarCiclos();
  });
</script>
