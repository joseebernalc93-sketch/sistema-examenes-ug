<?php
$sel_ciclo = $notas_seleccion['ciclo'] ?? '';
$sel_fac = $notas_seleccion['facultad'] ?? '';
$sel_car = $notas_seleccion['carrera'] ?? '';
$sel_sem = $notas_seleccion['semestre'] ?? '';
$sel_mat = $notas_seleccion['materia'] ?? '';
$sel_par = $notas_seleccion['paralelo'] ?? '';
?>

<style>
  .notas-card { background:#f8fbff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; margin-bottom:16px; }
  .notas-filtro { display:flex; flex-direction:column; gap:10px; align-items:flex-start; }
  .notas-field { display:flex; flex-direction:row; align-items:center; gap:12px; width:100%; max-width:520px; }
  .notas-field label { font-size:12px; font-weight:800; color:#334155; width:120px; flex-shrink:0; }
  .notas-field select { flex:1; min-width:0; }
  .notas-actions { display:flex; gap:10px; align-items:center; width:100%; max-width:520px; }
  .notas-actions button { height:38px; }
  .notas-hint { margin-top:8px; font-size:12px; color:#6b7280; }

  .notas-resumen { background:#ffffff; border:1px solid #e6e9ee; border-radius:12px; padding:16px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; }
  .notas-title { font-size:1.05rem; font-weight:800; color:var(--primary); }
  .notas-sub { font-size:12px; color:#6b7280; margin-top:4px; }
  .notas-chip { background:#f1f5f9; border:1px solid #e2e8f0; border-radius:999px; padding:6px 10px; font-size:12px; font-weight:700; color:#1f3b57; }

  .notas-table {
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    border:1px solid #e1e1e1;
    border-radius:8px;
    overflow:hidden;
  }
  .notas-table th {
    position:static;
    top:auto;
    background:#f8f9fa;
    color:#444;
    border-bottom:2px solid #e1e1e1;
    border-right:1px solid #f1f1f1;
    padding:12px;
    text-align:center;
    font-size:0.85rem;
    font-weight:700;
    white-space:nowrap;
  }
  .notas-table td {
    border-bottom:1px solid #f1f1f1;
    border-right:1px solid #f1f1f1;
    padding:10px 12px;
    text-align:center;
    font-size:0.9rem;
    white-space:nowrap;
  }
  .notas-table tr:last-child td { border-bottom:none; }
  .notas-table tbody tr:hover { background:#f1f5f9; }
  .notas-table .col-p1-header { background:#eff6ff; color:#1e3a8a; border-bottom-color:#bfdbfe; }
  .notas-table .col-p2-header { background:#f0fdf4; color:#14532d; border-bottom-color:#bbf7d0; }
  .notas-table .col-final-header { background:#fff7ed; color:#9a3412; border-bottom-color:#fed7aa; }
  .notas-table .col-p1 { background:#eff6ff; }
  .notas-table .col-p2 { background:#f0fdf4; }
  .notas-table .col-final { background:#fff7ed; font-weight:700; }
  .nota-low { color:#dc2626; font-weight:700; }
  .nota-p1-ok { color:#1e3a8a; font-weight:700; }
  .nota-p2-ok { color:#14532d; font-weight:700; }
  .nota-final-ok { color:#9a3412; font-weight:700; }
  .nota-progreso { color:#f59e0b; font-weight:700; }
</style>

<h3 style="margin:0 0 14px;">Notas de Estudiantes</h3>

<div class="notas-card">
  <form method="GET" action="admin.php" id="formNotasFiltro">
    <input type="hidden" name="seccion" value="notas">
    <div class="notas-filtro">
      <div class="notas-field">
        <label>PERIODOS:</label>
        <select name="n_ciclo" id="notasCiclo" data-selected="<?= htmlspecialchars($sel_ciclo) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>
      <div class="notas-field">
        <label>FACULTADES:</label>
        <select name="n_facultad" id="notasFacultad" data-selected="<?= htmlspecialchars($sel_fac) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>
      <div class="notas-field">
        <label>CARRERAS:</label>
        <select name="n_carrera" id="notasCarrera" data-selected="<?= htmlspecialchars($sel_car) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>
      <div class="notas-field">
        <label>Semestre</label>
        <select name="n_semestre" id="notasSemestre" data-selected="<?= htmlspecialchars($sel_sem) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>
      <div class="notas-field">
        <label>Materia</label>
        <select name="n_materia" id="notasMateria" data-selected="<?= htmlspecialchars($sel_mat) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>
      <div class="notas-field">
        <label>Paralelo</label>
        <select name="n_paralelo" id="notasParalelo" data-selected="<?= htmlspecialchars($sel_par) ?>" disabled>
          <option value="">-- Seleccionar --</option>
        </select>
      </div>
      <div class="notas-actions">
        <button type="submit" class="action-blue" id="btnVerNotas">Ver notas</button>
        <button type="button" class="btn-save" style="background:#64748b;" onclick="limpiarNotasFiltro()">Limpiar</button>
        <?php if ($notas_curso_id > 0 && (!empty($notas_examenes_p1) || !empty($notas_examenes_p2)) && !empty($notas_estudiantes)): ?>
          <button type="button" class="btn-save" onclick="exportarNotasExcel()">Descargar Excel</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
  <div class="notas-hint">Selecciona ciclo, facultad, carrera, semestre, materia y paralelo para ver las notas del curso.</div>
</div>

<?php if ($notas_curso_id > 0 && $notas_curso_info): ?>
  <div class="notas-resumen" id="infoNotasExport">
    <div>
      <div class="notas-title"><?= htmlspecialchars($notas_curso_info['materia'] ?? '') ?></div>
      <div class="notas-sub">
        Ciclo: <?= htmlspecialchars($notas_curso_info['ciclo'] ?? '') ?> |
        Facultad: <?= htmlspecialchars($notas_curso_info['facultad'] ?? '') ?> |
        Carrera: <?= htmlspecialchars($notas_curso_info['carrera'] ?? '') ?> |
        Semestre: <?= htmlspecialchars($notas_curso_info['semestre'] ?? '') ?> |
        Paralelo: <?= htmlspecialchars($notas_curso_info['paralelo'] ?? '') ?>
      </div>
    </div>
    <div class="notas-chip">
      Docente: <?= htmlspecialchars($notas_docentes_txt !== '' ? $notas_docentes_txt : 'No asignado') ?>
    </div>
  </div>
<?php endif; ?>

<?php if ($notas_curso_id > 0): ?>
  <?php if (empty($notas_examenes_p1) && empty($notas_examenes_p2)): ?>
    <div class="alert-select-ciclo">No hay examenes registrados para este curso.</div>
  <?php elseif (empty($notas_estudiantes)): ?>
    <div class="alert-select-ciclo">No hay estudiantes registrados en este curso.</div>
  <?php else: ?>
    <div id="notasTablaWrap" class="tabla-wrap notas-tabla-wrap">
      <table class="notas-table" id="tablaNotas">
        <thead>
          <tr>
            <th>#</th>
            <th style="text-align:left;">Estudiante</th>
            <th>Cedula</th>
            <th class="col-p1-header">PRIMER PARCIAL</th>
            <th class="col-p2-header">SEGUNDO PARCIAL</th>
            <th class="col-final-header">PROMEDIO FINAL</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($notas_estudiantes as $idx => $est): ?>
            <?php
              $est_id = (int)$est['estudiante_id'];
              $sumP1 = 0.0; $countP1 = 0; $rindiendoP1 = false;
              $sumP2 = 0.0; $countP2 = 0; $rindiendoP2 = false;

              foreach ($notas_examenes_p1 as $ex) {
                $val = $notas_resultados_map[$est_id][$ex['id']] ?? null;
                if ($val === null) continue;
                $num = (float)$val;
                if ($num === -1.0) {
                  if (($ex['estado'] ?? '') === 'activo') { $rindiendoP1 = true; }
                  continue;
                }
                $sumP1 += $num;
                $countP1++;
              }

              foreach ($notas_examenes_p2 as $ex) {
                $val = $notas_resultados_map[$est_id][$ex['id']] ?? null;
                if ($val === null) continue;
                $num = (float)$val;
                if ($num === -1.0) {
                  if (($ex['estado'] ?? '') === 'activo') { $rindiendoP2 = true; }
                  continue;
                }
                $sumP2 += $num;
                $countP2++;
              }

              $promP1 = null; $txtP1 = '-'; $clsP1 = '';
              if ($countP1 > 0) {
                $promP1 = $sumP1 / $countP1;
                $txtP1 = number_format($promP1, 2);
                $clsP1 = ($promP1 < 7) ? 'nota-low' : 'nota-p1-ok';
              } elseif ($rindiendoP1) {
                $txtP1 = '&#9203;';
                $clsP1 = 'nota-progreso';
              }

              $promP2 = null; $txtP2 = '-'; $clsP2 = '';
              if ($countP2 > 0) {
                $promP2 = $sumP2 / $countP2;
                $txtP2 = number_format($promP2, 2);
                $clsP2 = ($promP2 < 7) ? 'nota-low' : 'nota-p2-ok';
              } elseif ($rindiendoP2) {
                $txtP2 = '&#9203;';
                $clsP2 = 'nota-progreso';
              }

              $txtFinal = '-'; $clsFinal = '';
              if ($promP1 !== null || $promP2 !== null) {
                $arrProm = [];
                if ($promP1 !== null) $arrProm[] = $promP1;
                if ($promP2 !== null) $arrProm[] = $promP2;
                $promFinal = array_sum($arrProm) / count($arrProm);
                $txtFinal = number_format($promFinal, 2);
                $clsFinal = ($promFinal < 7) ? 'nota-low' : 'nota-final-ok';
              } elseif ($rindiendoP1 || $rindiendoP2) {
                $txtFinal = '&#9203;';
                $clsFinal = 'nota-progreso';
              }
            ?>
            <tr>
              <td><?= $idx + 1 ?></td>
              <td style="text-align:left; font-weight:700;"><?= htmlspecialchars($est['nombres']) ?></td>
              <td><?= htmlspecialchars($est['cedula']) ?></td>
              <td class="col-p1 <?= $clsP1 ?>"><?= $txtP1 ?></td>
              <td class="col-p2 <?= $clsP2 ?>"><?= $txtP2 ?></td>
              <td class="col-final <?= $clsFinal ?>"><?= $txtFinal ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
<?php elseif ($sel_ciclo !== '' && $sel_fac !== '' && $sel_car !== '' && $sel_sem !== '' && $sel_mat !== '' && $sel_par !== ''): ?>
  <div class="alert-select-ciclo">No se encontro el curso seleccionado.</div>
<?php endif; ?>

<script>
  const notasMap = <?php echo json_encode($notas_combo_map ?? [], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;

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
    const selCiclo = document.getElementById('notasCiclo');
    const selFac = document.getElementById('notasFacultad');
    const selCar = document.getElementById('notasCarrera');
    const selSem = document.getElementById('notasSemestre');
    const selMat = document.getElementById('notasMateria');
    const selPar = document.getElementById('notasParalelo');

    resetSelect(selCiclo);
    resetSelect(selFac);
    resetSelect(selCar);
    resetSelect(selSem);
    resetSelect(selMat);
    resetSelect(selPar);

    selFac.disabled = true;
    selCar.disabled = true;
    selSem.disabled = true;
    selMat.disabled = true;
    selPar.disabled = true;

    const ciclosMap = new Map();
    Object.entries(notasMap || {}).forEach(([fac, cars]) => {
      Object.entries(cars || {}).forEach(([car, ciclos]) => {
        Object.entries(ciclos || {}).forEach(([cid, data]) => {
          if (!ciclosMap.has(cid)) ciclosMap.set(cid, (data && data.nombre) ? data.nombre : cid);
        });
      });
    });

    if (ciclosMap.size === 0) { selCiclo.disabled = true; return; }

    selCiclo.disabled = false;
    Array.from(ciclosMap.entries())
      .sort((a, b) => a[1].localeCompare(b[1], 'es', { numeric: true, sensitivity: 'base' }))
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
    const selFac = document.getElementById('notasFacultad');
    const selCar = document.getElementById('notasCarrera');
    const selCiclo = document.getElementById('notasCiclo');
    const selSem = document.getElementById('notasSemestre');
    const selMat = document.getElementById('notasMateria');
    const selPar = document.getElementById('notasParalelo');

    resetSelect(selFac);
    resetSelect(selCar);
    resetSelect(selSem);
    resetSelect(selMat);
    resetSelect(selPar);

    selFac.disabled = true;
    selCar.disabled = true;
    selSem.disabled = true;
    selMat.disabled = true;
    selPar.disabled = true;

    const cicloVal = selCiclo.value;
    if (!cicloVal || !notasMap || Object.keys(notasMap).length === 0) return;

    const facSet = new Set();
    Object.entries(notasMap).forEach(([fac, cars]) => {
      Object.values(cars || {}).forEach(ciclos => {
        if (ciclos && ciclos[cicloVal]) facSet.add(fac);
      });
    });

    if (facSet.size === 0) return;

    selFac.disabled = false;
    Array.from(facSet).sort((a, b) => a.localeCompare(b)).forEach(f => {
      const opt = document.createElement('option');
      opt.value = f;
      opt.textContent = f;
      selFac.appendChild(opt);
    });

    const preFac = selFac.getAttribute('data-selected') || '';
    if (preFac) selFac.value = preFac;
    cargarCarreras();
  }

  function cargarCarreras() {
    const selFac = document.getElementById('notasFacultad');
    const selCar = document.getElementById('notasCarrera');
    const selCiclo = document.getElementById('notasCiclo');
    const selSem = document.getElementById('notasSemestre');
    const selMat = document.getElementById('notasMateria');
    const selPar = document.getElementById('notasParalelo');

    resetSelect(selCar);
    resetSelect(selSem);
    resetSelect(selMat);
    resetSelect(selPar);

    selSem.disabled = true;
    selMat.disabled = true;
    selPar.disabled = true;

    const facVal = selFac.value;
    const cicloVal = selCiclo.value;
    if (!facVal || !cicloVal || !notasMap[facVal]) {
      selCar.disabled = true;
      return;
    }

    const carSet = new Set();
    Object.entries(notasMap[facVal]).forEach(([car, ciclos]) => {
      if (ciclos && ciclos[cicloVal]) carSet.add(car);
    });

    if (carSet.size === 0) { selCar.disabled = true; return; }

    selCar.disabled = false;
    Array.from(carSet).sort((a, b) => a.localeCompare(b)).forEach(c => {
      const opt = document.createElement('option');
      opt.value = c;
      opt.textContent = c;
      selCar.appendChild(opt);
    });

    const preCar = selCar.getAttribute('data-selected') || '';
    if (preCar) selCar.value = preCar;
    cargarSemestres();
  }

  function cargarSemestres() {
    const selFac = document.getElementById('notasFacultad');
    const selCar = document.getElementById('notasCarrera');
    const selCiclo = document.getElementById('notasCiclo');
    const selSem = document.getElementById('notasSemestre');
    const selMat = document.getElementById('notasMateria');
    const selPar = document.getElementById('notasParalelo');

    resetSelect(selSem);
    resetSelect(selMat);
    resetSelect(selPar);

    selMat.disabled = true;
    selPar.disabled = true;

    const facVal = selFac.value;
    const carVal = selCar.value;
    const cicloVal = selCiclo.value;
    if (!facVal || !carVal || !cicloVal || !notasMap[facVal] || !notasMap[facVal][carVal] || !notasMap[facVal][carVal][cicloVal]) {
      selSem.disabled = true;
      return;
    }

    selSem.disabled = false;
    const semestres = notasMap[facVal][carVal][cicloVal].semestres || {};
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
    const selFac = document.getElementById('notasFacultad');
    const selCar = document.getElementById('notasCarrera');
    const selCiclo = document.getElementById('notasCiclo');
    const selSem = document.getElementById('notasSemestre');
    const selMat = document.getElementById('notasMateria');
    const selPar = document.getElementById('notasParalelo');

    resetSelect(selMat);
    resetSelect(selPar);
    selPar.disabled = true;

    const facVal = selFac.value;
    const carVal = selCar.value;
    const semVal = selSem.value;
    const cicloVal = selCiclo.value;
    if (!facVal || !carVal || !cicloVal || !semVal || !notasMap[facVal] || !notasMap[facVal][carVal] || !notasMap[facVal][carVal][cicloVal]) {
      selMat.disabled = true;
      return;
    }

    selMat.disabled = false;
    const semestres = notasMap[facVal][carVal][cicloVal].semestres || {};
    const materias = semestres[semVal] || {};
    Object.keys(materias).sort((a, b) => a.localeCompare(b)).forEach(m => {
      const opt = document.createElement('option');
      opt.value = m;
      opt.textContent = m;
      selMat.appendChild(opt);
    });

    const preMat = selMat.getAttribute('data-selected') || '';
    if (preMat) selMat.value = preMat;
    cargarParalelos();
  }

  function cargarParalelos() {
    const selFac = document.getElementById('notasFacultad');
    const selCar = document.getElementById('notasCarrera');
    const selCiclo = document.getElementById('notasCiclo');
    const selSem = document.getElementById('notasSemestre');
    const selMat = document.getElementById('notasMateria');
    const selPar = document.getElementById('notasParalelo');

    resetSelect(selPar);

    const facVal = selFac.value;
    const carVal = selCar.value;
    const semVal = selSem.value;
    const matVal = selMat.value;
    const cicloVal = selCiclo.value;
    if (!facVal || !carVal || !cicloVal || !semVal || !matVal || !notasMap[facVal] || !notasMap[facVal][carVal] || !notasMap[facVal][carVal][cicloVal]) {
      selPar.disabled = true;
      return;
    }

    selPar.disabled = false;
    const semestres = notasMap[facVal][carVal][cicloVal].semestres || {};
    const materias = semestres[semVal] || {};
    const paralelos = materias[matVal] || [];
    paralelos.forEach(p => {
      const opt = document.createElement('option');
      opt.value = p;
      opt.textContent = p;
      selPar.appendChild(opt);
    });

    const prePar = selPar.getAttribute('data-selected') || '';
    if (prePar) selPar.value = prePar;
  }

  function exportarNotasExcel() {
    const table = document.getElementById('tablaNotas');
    if (!table) { alert('No hay datos para exportar.'); return; }

    const info = document.getElementById('infoNotasExport');
    const clone = table.cloneNode(true);

    if (info) {
      const infoText = info.innerText.replace(/\n+/g, ' | ').replace(/\s+/g, ' ').trim();
      const thead = clone.querySelector('thead');
      const bodyRow = clone.querySelector('tbody tr');
      const headRow = clone.querySelector('thead tr:last-child');
      const refRow = bodyRow || headRow || clone.querySelector('tr');
      const colCount = refRow ? refRow.children.length : 1;
      if (thead) {
        const newRow = document.createElement('tr');
        const newCell = document.createElement('th');
        newCell.colSpan = colCount;
        newCell.style.background = '#f1f5f9';
        newCell.style.color = '#111827';
        newCell.style.fontSize = '13px';
        newCell.style.textAlign = 'center';
        newCell.innerText = infoText;
        newRow.appendChild(newCell);
        thead.insertBefore(newRow, thead.firstChild);
      }
    }

    const html = clone.outerHTML;
    const blob = new Blob(['\ufeff', '<meta charset="UTF-8">' + html], { type: 'application/vnd.ms-excel' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'notas_' + new Date().toISOString().slice(0,10) + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  function limpiarNotasFiltro() {
    const selCiclo = document.getElementById('notasCiclo');
    const selFac = document.getElementById('notasFacultad');
    const selCar = document.getElementById('notasCarrera');
    const selSem = document.getElementById('notasSemestre');
    const selMat = document.getElementById('notasMateria');
    const selPar = document.getElementById('notasParalelo');

    [selCiclo, selFac, selCar, selSem, selMat, selPar].forEach(s => {
      if (!s) return;
      s.setAttribute('data-selected', '');
      s.value = '';
    });
    cargarCiclos();
    const form = document.getElementById('formNotasFiltro');
    if (form) form.submit(); // recarga y limpia resultados en tabla
  }

  document.addEventListener('DOMContentLoaded', function() {
    const selFac = document.getElementById('notasFacultad');
    const selCar = document.getElementById('notasCarrera');
    const selCiclo = document.getElementById('notasCiclo');
    const selSem = document.getElementById('notasSemestre');
    const selMat = document.getElementById('notasMateria');
    const selPar = document.getElementById('notasParalelo');

    if (!selFac || !selCar || !selCiclo || !selSem || !selMat || !selPar) return;

    selCiclo.addEventListener('change', function() {
      selFac.setAttribute('data-selected', '');
      selCar.setAttribute('data-selected', '');
      selSem.setAttribute('data-selected', '');
      selMat.setAttribute('data-selected', '');
      selPar.setAttribute('data-selected', '');
      cargarFacultades();
    });

    selFac.addEventListener('change', function() {
      selCar.setAttribute('data-selected', '');
      selSem.setAttribute('data-selected', '');
      selMat.setAttribute('data-selected', '');
      selPar.setAttribute('data-selected', '');
      cargarCarreras();
    });

    selCar.addEventListener('change', function() {
      selSem.setAttribute('data-selected', '');
      selMat.setAttribute('data-selected', '');
      selPar.setAttribute('data-selected', '');
      cargarSemestres();
    });

    selSem.addEventListener('change', function() {
      selMat.setAttribute('data-selected', '');
      selPar.setAttribute('data-selected', '');
      cargarMaterias();
    });

    selMat.addEventListener('change', function() {
      selPar.setAttribute('data-selected', '');
      cargarParalelos();
    });

    selPar.addEventListener('change', function() {
      if (selFac.value && selCar.value && selCiclo.value && selSem.value && selMat.value && selPar.value) {
        document.getElementById('formNotasFiltro').submit();
      }
    });

    cargarCiclos();
  });
</script>
