<?php
// 1. CARGAR LÓGICA Y BASE DE DATOS
require_once "admin_logic.php"; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<link rel="icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
<link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
<title>Panel Administrador</title>
<?php include "estilos.php"; ?>
</head>
<body>

<div id="mensaje"></div>

<!-- NAVBAR -->
<nav class="navbar">
  <a class="brand" href="javascript:void(0)" onclick="irSeccionAdmin('cursos')">
    <img src="https://upload.wikimedia.org/wikipedia/commons/6/6d/LogoUGcolor.png" alt="Logo UG" class="brand-logo">
    <span class="sub">Panel Administrador</span>
  </a>
  <div class="navbar-right">
    <div class="user-chip">
      <div class="user-chip-text">
        <span class="user-chip-name"><?= htmlspecialchars($_SESSION["usuario"]["nombres"] ?? "Administrador") ?></span>
        <span class="user-chip-role">Administrador</span>
      </div>
      <div class="chip-avatar"><?php $nm=$_SESSION["usuario"]["nombres"]??"AD"; echo strtoupper(substr($nm,0,2)); ?></div>
      <span class="chip-caret"> </span>
    </div>
    <span style="font-weight:700; color:#455; font-size:.9rem;"><?= htmlspecialchars($_SESSION["usuario"]["nombres"] ?? "Administrador") ?></span>
    <div class="avatar"><?php $nm=$_SESSION["usuario"]["nombres"]??"AD"; echo strtoupper(substr($nm,0,1)); ?></div>
    <a href="logout.php" class="logout" onclick="try { limpiarEstadoAdminUI(); } catch (e) {}">Cerrar sesión</a>
  </div>
</nav>

<div class="main-wrapper">
  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="nav-title">Navegación</div>
    <a class="nav-link <?= $seccion=='cursos'?'active':'' ?>" href="javascript:void(0)" onclick="irSeccionAdmin('cursos')">📚 Gestión de Cursos</a>
    <a class="nav-link <?= $seccion=='usuarios'?'active':'' ?>" href="javascript:void(0)" onclick="irSeccionAdmin('usuarios')">👥 Gestión de Usuarios <?php if($total_pendientes>0): ?><span class="badge-alerta"><?=$total_pendientes?></span><?php endif; ?></a>
    <a class="nav-link <?= $seccion=='notas'?'active':'' ?>" href="javascript:void(0)" onclick="irSeccionAdmin('notas')">📊 Notas de Estudiantes</a>
    <a class="nav-link <?= $seccion=='docentes_evaluaciones'?'active':'' ?>" href="javascript:void(0)" onclick="irSeccionAdmin('docentes_evaluaciones')">📋 Evaluacion Docentes</a>
    <a class="nav-link <?= $seccion=='historial'?'active':'' ?>" href="javascript:void(0)" onclick="irSeccionAdmin('historial')">🧾 Historial de Acciones</a>
  </aside>

  <!-- CONTENIDO PRINCIPAL DINÁMICO -->
  <main class="page-content">
    <div class="container">

      <?php 
      // Router simple para incluir la vista correcta
      if ($seccion == "cursos"):
          include "secciones/cursos.php";
      elseif ($seccion == "usuarios"):
          include "secciones/usuarios.php";
      elseif ($seccion == "notas"):
          include "secciones/notas.php";
      elseif ($seccion == "docentes_evaluaciones"):
          include "secciones/docentes_evaluaciones.php";
      elseif ($seccion == "historial"):
          include "secciones/historial.php";
      else:
          echo "<p>Sección no encontrada.</p>";
      endif; 
      ?>

    </div>
  </main>
</div>

<!-- JAVASCRIPT -->
<?php include "scripts.php"; ?>

<script>
(function() {
  // =============================================
  // Persistencia de UI (sección, toggles y scroll)
  // =============================================

  function adminSearch() {
    return window.location.search || '?seccion=cursos';
  }

  function keyFor(prefix, id) {
    const base = encodeURIComponent(adminSearch());
    return id ? `${prefix}_${base}_${id}` : `${prefix}_${base}`;
  }

  function winScrollKey() { return keyFor('admin_scroll'); }
  function toggleKey(toggleId) { return keyFor('admin_toggle', toggleId); }
  function elScrollKey(elId) { return keyFor('admin_scroll_el', elId); }

  function safeJsonParse(s) {
    try { return JSON.parse(s); } catch (_) { return null; }
  }

  function isVisible(el) {
    return !!el && window.getComputedStyle(el).display !== 'none';
  }

  // Limpia la persistencia de UI del panel admin (usar en logout)
  window.limpiarEstadoAdminUI = function limpiarEstadoAdminUI() {
    try {
      for (let i = sessionStorage.length - 1; i >= 0; i--) {
        const k = sessionStorage.key(i);
        if (!k) continue;
        if (k === 'admin_seccion_actual' || k.startsWith('admin_scroll_') || k.startsWith('admin_toggle_') || k.startsWith('admin_filters_')) {
          sessionStorage.removeItem(k);
        }
      }
    } catch (_) {}
  };

  // Función global usada por el sidebar
  window.irSeccionAdmin = function irSeccionAdmin(seccion, params = '') {
    guardarEstadoAdmin();
    let search = '?seccion=' + seccion;
    if (params) search += '&' + params;
    sessionStorage.setItem('admin_seccion_actual', search);
    window.location.href = 'admin.php' + search;
  };

  // -------------------------
  // Toggles (acordeones)
  // -------------------------

  function getTogglePairs() {
    return Array.from(document.querySelectorAll('.section-title[id][data-target]'))
      .map(btn => {
        const targetId = btn.getAttribute('data-target');
        const content = targetId ? document.getElementById(targetId) : null;
        const icon = btn.querySelector('.toggle-btn');
        return { btn, content, icon };
      })
      .filter(p => p.content);
  }

  function aplicarEstadoToggle(pair, open) {
    pair.content.style.display = open ? 'block' : 'none';
    pair.btn.classList.toggle('open', open);
    if (pair.icon) pair.icon.classList.toggle('rotated', open);
  }

  function guardarEstadoToggles() {
    getTogglePairs().forEach(({ btn, content }) => {
      sessionStorage.setItem(toggleKey(btn.id), isVisible(content) ? '1' : '0');
    });
  }

  function restaurarEstadoToggles() {
    getTogglePairs().forEach(pair => {
      const raw = sessionStorage.getItem(toggleKey(pair.btn.id));
      if (raw === null) return;
      aplicarEstadoToggle(pair, raw === '1');
    });
  }

  // Guardar el estado del toggle después de hacer click (ya con el DOM actualizado)
  document.addEventListener('click', (e) => {
    const btn = e.target && e.target.closest ? e.target.closest('.section-title[id][data-target]') : null;
    if (!btn) return;
    const targetId = btn.getAttribute('data-target');
    const content = targetId ? document.getElementById(targetId) : null;
    if (!content) return;
    sessionStorage.setItem(toggleKey(btn.id), isVisible(content) ? '1' : '0');
  });

  // -------------------------
  // Scroll (window + contenedores internos)
  // -------------------------

  function getScrollContainers() {
    const selectors = [
      '.accordion-scroll[id]',
      '.tabla-wrap[id]',
      '[data-admin-scroll][id]'
    ];
    const els = Array.from(document.querySelectorAll(selectors.join(',')));
    const seen = new Set();
    return els.filter(el => {
      if (!el.id || seen.has(el.id)) return false;
      seen.add(el.id);
      return true;
    });
  }

  const scrollTimers = new Map();

  function debounceByKey(k, fn, wait) {
    if (scrollTimers.has(k)) clearTimeout(scrollTimers.get(k));
    scrollTimers.set(k, setTimeout(() => {
      scrollTimers.delete(k);
      fn();
    }, wait));
  }

  function guardarScrollVentana() {
    const y = window.scrollY || document.documentElement.scrollTop || 0;
    sessionStorage.setItem(winScrollKey(), String(y));
  }

  function guardarScrollElementos() {
    getScrollContainers().forEach(el => {
      const pos = { top: el.scrollTop || 0, left: el.scrollLeft || 0 };
      sessionStorage.setItem(elScrollKey(el.id), JSON.stringify(pos));
    });
  }

  function restaurarScrollVentana() {
    const raw = sessionStorage.getItem(winScrollKey());
    if (!raw) return;
    const y = parseInt(raw, 10);
    if (!Number.isFinite(y)) return;

    let attempts = 0;
    const max = 10;
    const tick = () => {
      window.scrollTo(0, y);
      if (Math.abs((window.scrollY || 0) - y) <= 1) return;
      if (++attempts < max) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }

  function restaurarScrollElementos() {
    getScrollContainers().forEach(el => {
      const raw = sessionStorage.getItem(elScrollKey(el.id));
      if (!raw) return;
      const pos = safeJsonParse(raw);
      if (!pos) return;

      const top = Number(pos.top);
      const left = Number(pos.left);

      let attempts = 0;
      const max = 10;
      const tick = () => {
        if (Number.isFinite(top)) el.scrollTop = top;
        if (Number.isFinite(left)) el.scrollLeft = left;

        const okTop = !Number.isFinite(top) || Math.abs((el.scrollTop || 0) - top) <= 1;
        const okLeft = !Number.isFinite(left) || Math.abs((el.scrollLeft || 0) - left) <= 1;
        if (okTop && okLeft) return;
        if (++attempts < max) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  }

  function guardarEstadoAdmin() {
    guardarScrollVentana();
    guardarScrollElementos();
    guardarEstadoToggles();
  }

  // Exponer un helper para que otros scripts (p.ej. cambio de ciclo) puedan
  // guardar el estado inmediatamente después de modificar la URL (replaceState).
  window.adminUI_storeNow = guardarEstadoAdmin;

  function initScrollListeners() {
    window.addEventListener('scroll', () => debounceByKey('win', guardarScrollVentana, 120), { passive: true });

    getScrollContainers().forEach(el => {
      const k = 'el:' + el.id;
      el.addEventListener('scroll', () => {
        debounceByKey(k, () => {
          const pos = { top: el.scrollTop || 0, left: el.scrollLeft || 0 };
          sessionStorage.setItem(elScrollKey(el.id), JSON.stringify(pos));
        }, 120);
      }, { passive: true });
    });
  }

  // -------------------------
  // Sección actual
  // -------------------------

  (function restoreSectionFromSession() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentSeccion = urlParams.get('seccion');

    if (currentSeccion) {
      sessionStorage.setItem('admin_seccion_actual', window.location.search);
    } else {
      const savedUrl = sessionStorage.getItem('admin_seccion_actual');
      if (savedUrl) {
        // Compat: antes se guardaba como "admin.php?seccion=..."; ahora solo "?seccion=..."
        const normalized = savedUrl.startsWith('admin.php') ? savedUrl.replace(/^admin\\.php/, '') : savedUrl;
        window.location.href = 'admin.php' + normalized;
      }
    }
  })();

  // -------------------------
  // Boot / Restore
  // -------------------------

  let inited = false;
  function boot() {
    if (inited) return;
    inited = true;
    initScrollListeners();
  }

  function restoreAll() {
    restaurarEstadoToggles();
    restaurarScrollElementos();
    restaurarScrollVentana();
  }

  document.addEventListener('DOMContentLoaded', () => {
    boot();
    restoreAll();
    setTimeout(restoreAll, 150);
  });

  window.addEventListener('load', () => {
    setTimeout(restoreAll, 50);
    setTimeout(restoreAll, 300);
  });

  window.addEventListener('beforeunload', () => guardarEstadoAdmin());
  window.addEventListener('pagehide', () => guardarEstadoAdmin());
})();
</script>

</body>
</html>
