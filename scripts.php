<script>
function mostrarMensaje(texto, error=false) {
  const msg = document.getElementById('mensaje');
  if(!msg) return;
  msg.innerText = texto;
  msg.style.backgroundColor = error ? '#dc3545' : '#28a745';
  msg.style.display = 'block';
  requestAnimationFrame(() => { setTimeout(() => { msg.style.display = 'none'; }, 5000); });
}

// Botones para alternar formularios de creación de cursos
const btnSem = document.getElementById('btnToggleSemestre');
if (btnSem) {
  btnSem.addEventListener('click', function() {
    const f = document.getElementById('formSemestre');
    if (!f) return;
    const isHidden = f.style.display === 'none' || f.style.display === '';
    f.style.display = isHidden ? 'flex' : 'none';
  });
}
const btnInd = document.getElementById('btnToggleIndividual');
if (btnInd) {
  btnInd.addEventListener('click', function() {
    const f = document.getElementById('formIndividual');
    if (!f) return;
    const isHidden = f.style.display === 'none' || f.style.display === '';
    f.style.display = isHidden ? 'flex' : 'none';
  });
}

function celdaValor(celda) {
  if (!celda) return '';
  const ctrl = celda.querySelector('input, select, textarea');
  const v = (ctrl ? (ctrl.value ?? ctrl.textContent) : celda.textContent) || '';
  return v.toString().toUpperCase().trim();
}
function cicloKey(s){
  s = (s || '').toUpperCase();
  const m = s.match(/(20\d{2}).*?(20\d{2})(?:.*?([CIVX]+))?/i);
  if (!m) return '';
  const roman = (m[3] || '').replace(/[^CIVX]/g,'');
  return (m[1] + m[2] + roman);
}
function filtroTieneRomano(key){ return /[CIVX]+$/.test(key); }
function esConsultaDeCiclo(q){ return /20\d{2}[^0-9]*20\d{2}/.test((q||'').toUpperCase()); }

// --- Lógica para la tabla de EDICIÓN DE CURSOS (Pestaña 1) ---
function filtrarTabla() {
  const input = document.getElementById('inputBuscador');
  if (!input) return;
  const filtroTxt = (input && input.value ? input.value : '').toUpperCase().trim();

  const tabla = document.getElementById('tablaCursosEdicion');
  if (!tabla) return;

  const filtroEsCiclo = esConsultaDeCiclo(filtroTxt);
  const filas = tabla.querySelectorAll('tbody > tr');

  const selectGlobal = document.getElementById('cicloSelectorGlobal');
  const cicloIdSeleccionado = selectGlobal ? selectGlobal.value : '0';

  for (let i = 0; i < filas.length; i++) {
    const tr = filas[i];
    const tds = tr.getElementsByTagName('td');
    if (!tds || tds.length < 7) {
        tr.style.display = '';
        continue;
    }

    const rowCicloId = tr.getAttribute('data-ciclo-id');
    if (cicloIdSeleccionado !== '0' && rowCicloId !== cicloIdSeleccionado) {
        tr.style.display = 'none';
        continue;
    }

    const ciclo     = celdaValor(tds[1]);
    const semestre = celdaValor(tds[2]);
    const paralelo = celdaValor(tds[3]);
    const materia   = celdaValor(tds[4]);
    const carrera   = celdaValor(tds[5]);
    const facultad = celdaValor(tds[6]);

    let coincideTexto = false;
    if (filtroTxt === '') {
      coincideTexto = true;
    } else if (filtroEsCiclo) {
      const keyFiltro = cicloKey(filtroTxt);
      const keyCiclo  = cicloKey(ciclo);
      if (!keyFiltro) {
        coincideTexto = true;
      } else if (filtroTieneRomano(keyFiltro)) {
        coincideTexto = (keyCiclo === keyFiltro);
      } else {
        coincideTexto = keyCiclo.startsWith(keyFiltro);
      }
    } else if (/^[0-9]+$/.test(filtroTxt)) {
      const sem = semestre.toString();
      coincideTexto = (sem === filtroTxt) || sem.startsWith(filtroTxt);
    } else {
      const f = filtroTxt;
      coincideTexto = (ciclo.includes(f) || paralelo.includes(f) || materia.includes(f) || carrera.includes(f) || facultad.includes(f));
    }

    tr.style.display = coincideTexto ? '' : 'none';
  }
}

function cambiarCicloGlobal(id) {
    const contentDiv = document.getElementById('contentForCycle');
    const alertDiv = document.getElementById('alertNoCycle');

    if (id > 0) {
        if(contentDiv) contentDiv.style.display = 'block';
        if(alertDiv) alertDiv.style.display = 'none';
    } else {
        if(contentDiv) contentDiv.style.display = 'none';
        if(alertDiv) alertDiv.style.display = 'block';
    }

    const hiddenInputs = document.querySelectorAll('input[name="ciclo_id"], input[name="ciclo_redirect"]');
    hiddenInputs.forEach(input => input.value = id);

    filtrarTabla();

    // Persistir el ciclo seleccionado en la URL para que al refrescar se mantenga el mismo ciclo
    // (admin_logic.php lee el parámetro GET "ciclo_filtro")
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('seccion', 'cursos');
        if (String(id) && String(id) !== '0') {
            url.searchParams.set('ciclo_filtro', String(id));
        } else {
            url.searchParams.delete('ciclo_filtro');
        }

        history.replaceState(null, '', url.pathname + '?' + url.searchParams.toString() + url.hash);
        sessionStorage.setItem('admin_seccion_actual', url.search);

        // Guardar inmediatamente el estado (scroll/toggles) bajo la nueva key de URL.
        if (typeof window.adminUI_storeNow === 'function') {
            window.adminUI_storeNow();
        }
    } catch (e) {}
}

// Inicialización de lógica de cursos
(function initBuscadorCursos(){
  const i = document.getElementById('inputBuscador');
  const b = document.getElementById('btnBuscar');
  const selCiclo = document.getElementById('cicloSelectorGlobal');
  if (!i) return;
  i.addEventListener('input', filtrarTabla);
  i.addEventListener('keyup', (e)=>{ if (e.key === 'Enter') { e.preventDefault(); filtrarTabla(); }});
  if (b) b.addEventListener('click', filtrarTabla);

  // Si la página se cargó sin "ciclo_filtro" en la URL, sincronizarlo con el selector actual
  // para que al refrescar se mantenga el mismo ciclo.
  try {
      // Si no hay parámetros pero existe una sección guardada, dejar que admin.php redirija primero.
      if (!window.location.search && sessionStorage.getItem('admin_seccion_actual')) {
          return;
      }
      if (selCiclo && selCiclo.value && selCiclo.value !== '0') {
          const params = new URLSearchParams(window.location.search || '');
          const seccion = params.get('seccion') || 'cursos';
          const current = params.get('ciclo_filtro');
          if (seccion === 'cursos' && current !== selCiclo.value) {
              cambiarCicloGlobal(selCiclo.value);
          }
      }
  } catch (e) {}
})();

const checkAllTop = document.getElementById('checkAllTop');
const checkAll    = document.getElementById('checkAll');
function setAllRows(checked){
    const visibles = Array.from(document.querySelectorAll('#tablaCursosEdicion tbody tr')).filter(tr => tr.style.display !== 'none');
    visibles.forEach(tr => {
        const ch = tr.querySelector('.row-check');
        if(ch) ch.checked = checked;
    });
    if (checkAllTop) checkAllTop.checked = checked;
    if (checkAll)    checkAll.checked    = checked;
}
if (checkAllTop) checkAllTop.addEventListener('change', e => setAllRows(e.target.checked));
if (checkAll)    checkAll.addEventListener('change', e => setAllRows(e.target.checked));

function confirmarEliminarSeleccion(){
    const alguno = Array.from(document.querySelectorAll('#tablaCursosEdicion tbody .row-check:checked')).length > 0;
    if (!alguno) { alert('Seleccione al menos un curso para eliminar.'); return false; }
    return confirm('¿Seguro que deseas eliminar los cursos seleccionados? Esta acción no se puede deshacer.');
}

// ============================================
// FILTROS DE USUARIOS - GESTIÓN DE USUARIOS
// ============================================
const materiasParalelosMap = <?php echo json_encode($materias_paralelos_map ?? [], JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS); ?>;

function filtrarOpcionesMateria(cicloId) {
    const selMateria = document.getElementById('filtroMateria');
    if (!selMateria) return;
    const opciones = selMateria.querySelectorAll('option');
    opciones.forEach(opt => {
        if (opt.value === 'todos' || opt.value === 'sin_curso') {
            opt.style.display = '';
        } else {
            opt.style.display = '';
        }
    });
}

function actualizarParalelosFiltro() {
  const selMateria = document.getElementById('filtroMateria');
  const selParalelo = document.getElementById('filtroParalelo');
  if (!selMateria || !selParalelo) return;

  const mat = selMateria.value;
  selParalelo.innerHTML = '<option value="todos">-- Todos --</option>';

  if (mat === 'todos' || mat === 'sin_curso' || !materiasParalelosMap[mat]) {
    selParalelo.disabled = true;
  } else {
    const arr = materiasParalelosMap[mat] || [];
    arr.forEach(p => {
        const option = document.createElement('option');
        option.value = p;
        option.textContent = p;
        selParalelo.appendChild(option);
    });
    selParalelo.disabled = false;
  }
  aplicarFiltrosUsuarios();
}

// Esta variable global nos ayuda a saber si estamos en estado "limpio" forzado
let estadoLimpiezaActivo = false;

function aplicarFiltrosUsuarios() {
  try {
      // 1. Obtener elementos
      const selCicloGlobal = document.getElementById('filtroCicloGlobal'); 
      const selMateria = document.getElementById('filtroMateria');
      const selParalelo = document.getElementById('filtroParalelo');
      const selArea = document.getElementById('filtroArea');
      const inputTxt = document.getElementById('inputBuscadorUsuarios');
      
      if (!selMateria || !selParalelo) return;

      const cicloId = selCicloGlobal ? selCicloGlobal.value : 'todos';
      const materia = selMateria.value;
      const paralelo = selParalelo.value;
      const areaFiltro = selArea ? selArea.value : 'todos';
      const texto = (inputTxt && inputTxt.value ? inputTxt.value : '').toUpperCase().trim();

      const tablaMappings = [
        { id: 'tablaDirCarrera',   contentId: 'tablaDirCarreraContent',   toggleId: 'toggleDirCarrera',   isDirector: true },
        { id: 'tablaDirArea',      contentId: 'tablaDirAreaContent',      toggleId: 'toggleDirArea',      isDirector: true },
        { id: 'tablaDocentes',     contentId: 'tablaDocentesContent',     toggleId: 'toggleDocentes',     isDirector: false },
        { id: 'tablaEstudiantes',  contentId: 'tablaEstudiantesContent',  toggleId: 'toggleEstudiantes',  isDirector: false }
      ];

      const cedulasConCursoVisible = {};
      const tablasConResultados = [];
      
      const esFiltroPorCurso = (materia !== 'todos' && materia !== 'sin_curso');
      const hayFiltroTexto   = texto !== '';
      const esFiltroSinCurso = materia === 'sin_curso';
      const esFiltroCiclo    = (cicloId !== 'todos' && cicloId !== '0' && cicloId !== '');
      const esFiltroPorArea  = (areaFiltro !== 'todos');
      
      let totalUsuariosVisibles = 0;
      
      tablaMappings.forEach(mapping => {
        const tabla = document.getElementById(mapping.id);
        let filasVisiblesEnTabla = 0;
        
        if (!tabla) return;
        
        const filas = tabla.querySelectorAll('tbody tr');
        filas.forEach(tr => {
          let visible = true;
          
          const nombreCedula = (tr.dataset.nombreCedula || '').toUpperCase();
          const cedula = tr.querySelector('td:nth-child(4)') ? tr.querySelector('td:nth-child(4)').textContent.trim() : '';
          const materiasStr  = (tr.dataset.cursoMateria  || '').split('\n').filter(m => m.length > 0);
          const ciclosData = tr.getAttribute('data-ciclos-ids') || '';
          const ciclosArray = ciclosData.split(',').map(c => c.trim()).filter(c => c !== '');
          const areaUsuario = (tr.dataset.area || '').toUpperCase();

          // --- 1. FILTRO DE CICLO (Crítico: si hay ciclo seleccionado, SOLO mostrar usuarios de ese ciclo) ---
          if (esFiltroCiclo) {
              // Si el usuario no tiene ningún ciclo registrado, ocultarlo
              if (ciclosArray.length === 0 || !ciclosArray.includes(cicloId)) {
                  visible = false;
              }
          }

          // --- 2. FILTRO DE ÁREA ---
          if (visible && esFiltroPorArea) {
              // Solo aplicar a docentes y directores de área
              if (mapping.id === 'tablaDirArea' || mapping.id === 'tablaDocentes') {
                  if (areaUsuario !== areaFiltro.toUpperCase()) {
                      visible = false;
                  }
              }
          }

          // --- 3. FILTRO DE MATERIA/PARALELO ---
          if (visible) {
              if (esFiltroSinCurso) {
                if (mapping.isDirector) { visible = false; } else { visible = (materiasStr.length === 0); }
              } else if (esFiltroPorCurso) {
                if (mapping.isDirector) {
                  visible = false;
                } else {
                  let coincideMateria = false;
                  materiasStr.forEach(m => { if (m === materia) coincideMateria = true; });
                  if (!coincideMateria) visible = false;

                  if (visible && paralelo && paralelo !== 'todos') {
                    const paralelosStr = (tr.dataset.cursoParalelo || '').split('\n').filter(p => p.length > 0);
                    let coincideParalelo = false;
                    paralelosStr.forEach(p => { if (p === paralelo) coincideParalelo = true; });
                    if (!coincideParalelo) visible = false;
                  }
                }
              }
          }

          // --- 4. FILTRO DE TEXTO ---
          if (visible && hayFiltroTexto) {
            if (nombreCedula.indexOf(texto) === -1) visible = false;
          }

          tr.style.display = visible ? '' : 'none';
          
          if (visible) {
              filasVisiblesEnTabla++;
              totalUsuariosVisibles++;
              if (!mapping.isDirector) cedulasConCursoVisible[cedula] = true;
          }
        });

        if (filasVisiblesEnTabla > 0) tablasConResultados.push(mapping);
      });

      // Mostrar contador de usuarios visibles si hay filtro activo
      const contadorDiv = document.getElementById('contadorUsuariosVisibles');
      const totalSpan = document.getElementById('totalUsuariosVisibles');
      const hayFiltroActivo = esFiltroCiclo || esFiltroPorCurso || hayFiltroTexto || esFiltroSinCurso || esFiltroPorArea;
      
      if (contadorDiv && totalSpan) {
          if (hayFiltroActivo) {
              contadorDiv.style.display = 'block';
              totalSpan.textContent = totalUsuariosVisibles;
          } else {
              contadorDiv.style.display = 'none';
          }
      }

      // GESTIÓN DE ACORDEONES
      if (!estadoLimpiezaActivo) {
          if (hayFiltroActivo) {
            tablaMappings.forEach(mapping => {
                const content = document.getElementById(mapping.contentId);
                const btn = document.getElementById(mapping.toggleId);
                const tieneResultados = tablasConResultados.find(t => t.id === mapping.id);
                if (content && btn) {
                    if (tieneResultados) {
                        content.style.display = 'block';
                        if (!btn.classList.contains('open')) {
                            btn.querySelector('.toggle-btn').classList.add('rotated');
                            btn.classList.add('open');
                        }
                    } else {
                        content.style.display = 'none';
                        btn.querySelector('.toggle-btn').classList.remove('rotated');
                        btn.classList.remove('open');
                    }
                }
            });
          } else {
            // Estado por defecto (todos) - mostrar todas las tablas cerradas
            tablaMappings.forEach(mapping => {
                const content = document.getElementById(mapping.contentId);
                const btn = document.getElementById(mapping.toggleId);
                if (content && btn) {
                    content.style.display = 'block';
                    btn.querySelector('.toggle-btn').classList.add('rotated');
                    btn.classList.add('open');
                }
            });
          }
      }

  } catch (e) {
      console.error("Error en filtros de usuarios", e);
      document.querySelectorAll('.content-hidden').forEach(c => c.style.display = 'block');
  }
}

// INICIALIZACIÓN
document.addEventListener('DOMContentLoaded', function() {
    filtrarTabla(); // Tabla cursos
    aplicarFiltrosUsuarios(); // Tabla usuarios
    
    // Listeners adicionales
    const inp = document.getElementById('inputBuscadorUsuarios');
    const btn = document.getElementById('btnBuscarUsuarios');
    const selCiclo = document.getElementById('filtroCicloGlobal');
    const selArea = document.getElementById('filtroArea');

    if (inp) {
        inp.addEventListener('input', () => { estadoLimpiezaActivo = false; aplicarFiltrosUsuarios(); });
        inp.addEventListener('keyup', e => { if (e.key === 'Enter') { e.preventDefault(); estadoLimpiezaActivo = false; aplicarFiltrosUsuarios(); } });
    }
    if (btn) btn.addEventListener('click', () => { estadoLimpiezaActivo = false; aplicarFiltrosUsuarios(); });
    
    // CAMBIO IMPORTANTE: Al cambiar ciclo, filtramos
    if (selCiclo) {
        selCiclo.addEventListener('change', () => { 
            estadoLimpiezaActivo = false; 
            filtrarOpcionesMateria(selCiclo.value);
            if (typeof actualizarParalelosFiltro === 'function') {
                actualizarParalelosFiltro();
            } else {
                aplicarFiltrosUsuarios();
            }
        });
    }

    // Listener para filtro de área
    if (selArea) {
        selArea.addEventListener('change', () => { 
            estadoLimpiezaActivo = false; 
            aplicarFiltrosUsuarios();
        });
    }

    // Ejecutar filtro de opciones al inicio
    if (selCiclo) {
       filtrarOpcionesMateria(selCiclo.value);
    }
});

const btnLimpiar = document.getElementById('btnLimpiarFiltros');
if (btnLimpiar) {
  btnLimpiar.addEventListener('click', function() {
    // 1. Activar bandera de limpieza
    estadoLimpiezaActivo = true;

    const inp = document.getElementById('inputBuscadorUsuarios'); if (inp) inp.value = '';
    const selMat = document.getElementById('filtroMateria');
    const selPar = document.getElementById('filtroParalelo');
    const selCic = document.getElementById('filtroCicloGlobal');
    const selArea = document.getElementById('filtroArea');
    
    if (selMat) {
        selMat.value = 'todos';
    }
    if (selCic) {
        selCic.value = 'todos';
        filtrarOpcionesMateria('todos');
    }
    if (selArea) {
        selArea.value = 'todos';
    }
    if (selPar) {
        selPar.value = 'todos';
        selPar.disabled = true;
    }
    
    // Ocultar contador
    const contadorDiv = document.getElementById('contadorUsuariosVisibles');
    if (contadorDiv) contadorDiv.style.display = 'none';
    
    // 2. Aplicamos filtros
    if (typeof actualizarParalelosFiltro === 'function') {
        actualizarParalelosFiltro();
    } else {
        aplicarFiltrosUsuarios();
    }

    // 3. FORZAR CIERRE DE TABLAS
    const seccionesTablas = [
        { btn: 'toggleDirCarrera', content: 'tablaDirCarreraContent' },
        { btn: 'toggleDirArea',    content: 'tablaDirAreaContent' },
        { btn: 'toggleDocentes',   content: 'tablaDocentesContent' },
        { btn: 'toggleEstudiantes',content: 'tablaEstudiantesContent' }
    ];

    seccionesTablas.forEach(item => {
        const content = document.getElementById(item.content);
        const btn = document.getElementById(item.btn);
        
        if (content) content.style.display = 'none';
        
        if (btn) {
            btn.classList.remove('open');
            const icon = btn.querySelector('.toggle-btn');
            if (icon) icon.classList.remove('rotated');
        }
    });

    // 4. Asegurar que el desplegable de Filtros/Importación se mantenga ABIERTO
    const btnFiltro = document.getElementById('toggleFiltroImportacion');
    const contentFiltro = document.getElementById('filtroImportacionContent');
    if (contentFiltro) contentFiltro.style.display = 'block';
    if (btnFiltro) {
        btnFiltro.classList.add('open');
        const icon = btnFiltro.querySelector('.toggle-btn');
        if (icon) icon.classList.add('rotated');
    }
  });
}

function confirmarEliminarUsuarios(){
  const alguno = Array.from(document.querySelectorAll('.row-check-usuario')).some(ch => ch.checked);
  if (!alguno){
    alert('Seleccione al menos un usuario para eliminar.');
    return false;
  }
  return confirm('¿Seguro que deseas eliminar los usuarios seleccionados? Esta acción no se puede deshacer.');
}
function setAllDocentes(checked)   { document.querySelectorAll('.row-check-docente')  .forEach(ch => ch.checked = checked); }
function setAllEstudiantes(checked){ document.querySelectorAll('.row-check-estudiante').forEach(ch => ch.checked = checked); }
function setAllDirCarrera(checked){ document.querySelectorAll('.row-check-dir-carrera').forEach(ch => ch.checked = checked); }
function setAllDirArea(checked)   { document.querySelectorAll('.row-check-dir-area')  .forEach(ch => ch.checked = checked); }

const chkAllDoc    = document.getElementById('checkAllDocentes');
const chkAllEst    = document.getElementById('checkAllEstudiantes');
const chkAllDirCar = document.getElementById('checkAllDirCarrera');
const chkAllDirArea= document.getElementById('checkAllDirArea');
if (chkAllDoc)    chkAllDoc.addEventListener('change', e => setAllDocentes(e.target.checked));
if (chkAllEst)    chkAllEst.addEventListener('change', e => setAllEstudiantes(e.target.checked));
if (chkAllDirCar) chkAllDirCar.addEventListener('change', e => setAllDirCarrera(e.target.checked));
if (chkAllDirArea)chkAllDirArea.addEventListener('change', e => setAllDirArea(e.target.checked));

function setupToggle(buttonId, contentId) {
    const btn = document.getElementById(buttonId);
    const content = document.getElementById(contentId);
    if (btn && content) {
      btn.addEventListener('click', function() {
        const isHidden = content.style.display === 'none' || content.style.display === '';
        content.style.display = isHidden ? 'block' : 'none';
        btn.querySelector('.toggle-btn').classList.toggle('rotated', isHidden);
        btn.classList.toggle('open', isHidden);
      });
    }
}
setupToggle('toggleGestionCiclosCursos', 'gestionCiclosCursosContent');
setupToggle('toggleFiltroImportacion', 'filtroImportacionContent');
setupToggle('toggleConsultaUsuarios', 'consultaUsuariosContent');
setupToggle('toggleDirCarrera', 'tablaDirCarreraContent');
setupToggle('toggleDirArea', 'tablaDirAreaContent');
setupToggle('toggleDocentes', 'tablaDocentesContent');
setupToggle('toggleEstudiantes', 'tablaEstudiantesContent');
setupToggle('toggleRestricciones', 'tablaRestriccionesContent');

const btnAddMateria = document.getElementById('btnAddMateriaInput');
if (btnAddMateria) {
    btnAddMateria.addEventListener('click', function() {
        const container = document.getElementById('listaMateriasInputs');
        const count = container.children.length;
        const nextLetter = String.fromCharCode(65 + count);

        const div = document.createElement('div');
        div.className = 'input-group-dynamic';
        div.style.marginBottom = '10px';
        div.innerHTML = `
            <label>Materia ${nextLetter}:</label>
            <div style="display:flex; gap:5px;">
                <input list="lista_materias_dl" name="materias_grupo[]" class="input-full" style="width:100%;" required placeholder="Buscar materia...">
                <button type="button" onclick="this.parentElement.parentElement.remove()" style="background:#dc3545; width:40px; padding:0; height: 38px; border-radius:10px; color:white; font-weight:bold; border:none; cursor:pointer;">X</button>
            </div>
        `;
        container.appendChild(div);
    });
}
</script>
