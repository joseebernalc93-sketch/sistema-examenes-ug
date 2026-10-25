<h3 style="margin:0 0 10px;">Historial de Acciones (Auditoría)</h3>
<p style="font-size:14px; color:#6b7280; margin-bottom:16px;">Aqui puedes ver acciones importantes de Docentes, Administradores y alertas automaticas de seguridad.</p>

<?php if (!empty($_SESSION['historial_msg'])): ?>
    <div class="import-box" style="border-color:#bbf7d0; background:#ecfdf3; margin:0 0 14px;">
        <strong><?= htmlspecialchars($_SESSION['historial_msg']) ?></strong>
    </div>
    <?php unset($_SESSION['historial_msg']); ?>
<?php endif; ?>

<?php
$actor_sel_form = trim((string)($_GET['h_actor'] ?? ''));
$accion_sel_form = strtoupper(trim((string)($_GET['h_accion'] ?? '')));
$fecha_desde_form = trim((string)($_GET['h_desde'] ?? ''));
$fecha_hasta_form = trim((string)($_GET['h_hasta'] ?? ''));
$acciones_historial = [];
$res_acciones = $conn->query("SELECT DISTINCT UPPER(accion) AS accion
                              FROM auditoria_log
                              WHERE UPPER(accion) <> 'OCULTAR_ALERTAS_SEG'
                              ORDER BY accion ASC");
if ($res_acciones) {
    while ($r_acc = $res_acciones->fetch_assoc()) {
        $acc = trim((string)($r_acc['accion'] ?? ''));
        if ($acc !== '') $acciones_historial[] = $acc;
    }
}
?>

<form id="historialFiltrosForm" onsubmit="return false;" style="background:#fbfbfd; padding:15px; border-radius:12px; border:1px solid #e6e9ee; margin-bottom:16px;">
    <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:end;">
        <div style="display:flex; flex-direction:column; gap:5px;">
            <label style="font-size:13px; font-weight:800; color:#334155;">Buscar por Actor (Docente/Admin/Estudiante):</label>
            <input type="text" id="historialFiltroActor" value="<?= htmlspecialchars($actor_sel_form) ?>" placeholder="Ej: Nombre del docente">
        </div>
        <div style="display:flex; flex-direction:column; gap:5px; min-width:220px;">
            <label style="font-size:13px; font-weight:800; color:#334155;">Tipo de Acción:</label>
            <select id="historialFiltroAccion">
                <option value="">Todas</option>
                <?php foreach ($acciones_historial as $acc_opt): ?>
                    <option value="<?= htmlspecialchars($acc_opt) ?>" <?= ($accion_sel_form === $acc_opt) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($acc_opt) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex; flex-direction:column; gap:5px;">
            <label style="font-size:13px; font-weight:800; color:#334155;">Fecha Desde:</label>
            <input type="date" id="historialFiltroDesde" value="<?= htmlspecialchars($fecha_desde_form) ?>">
        </div>
        <div style="display:flex; flex-direction:column; gap:5px;">
            <label style="font-size:13px; font-weight:800; color:#334155;">Fecha Hasta:</label>
            <input type="date" id="historialFiltroHasta" value="<?= htmlspecialchars($fecha_hasta_form) ?>">
        </div>
        <button type="button" class="action-blue" onclick="limpiarFiltrosHistorial()">Limpiar filtros</button>
    </div>
</form>

<?php
$query_logs = "SELECT l.*, u.nombres AS afectado_nombre
    FROM auditoria_log l
    LEFT JOIN usuarios u
        ON u.id = l.id_afectado
        AND (
            UPPER(l.entidad_afectada) IN ('USUARIO', 'INTENTO')
            OR UPPER(l.accion) = 'REHABILITAR'
            OR UPPER(l.accion) LIKE '%USUARIO%'
            OR UPPER(l.accion) LIKE '%ESTUDIANTE%'
        )
    WHERE UPPER(l.accion) <> 'OCULTAR_ALERTAS_SEG'
    ORDER BY l.fecha_registro DESC
    LIMIT 200";
$res_logs = $conn->query($query_logs);
$hay_logs = ($res_logs && $res_logs->num_rows > 0);

// Cache local para evitar consultas repetidas por fila
$cache_examen_curso = [];
$cache_curso_mp = [];
$cache_estudiantes_alertas = [];

$resolver_curso_por_examen = function($examen_id) use ($conn, &$cache_examen_curso) {
    $examen_id = (int)$examen_id;
    if ($examen_id <= 0) return 0;
    if (array_key_exists($examen_id, $cache_examen_curso)) return (int)$cache_examen_curso[$examen_id];

    $curso_id = 0;
    $qEx = $conn->query("SELECT curso_id FROM examenes WHERE id = $examen_id LIMIT 1");
    if ($qEx && $rEx = $qEx->fetch_assoc()) { $curso_id = (int)($rEx['curso_id'] ?? 0); }
    $cache_examen_curso[$examen_id] = $curso_id;
    return $curso_id;
};

$resolver_materia_paralelo_por_curso = function($curso_id) use ($conn, &$cache_curso_mp) {
    $curso_id = (int)$curso_id;
    if ($curso_id <= 0) return ['-', '-'];
    if (array_key_exists($curso_id, $cache_curso_mp)) return $cache_curso_mp[$curso_id];

    $materia = '-';
    $paralelo = '-';
    $qMp = $conn->query("SELECT m.nombre AS materia, p.nombre AS paralelo
        FROM cursos_oferta co
        JOIN paralelos p ON p.id = co.paralelo_id
        JOIN materias m ON m.id = p.materia_id
        WHERE co.id = $curso_id
        LIMIT 1");
    if ($qMp && $rMp = $qMp->fetch_assoc()) {
        $materia = trim((string)($rMp['materia'] ?? '')) ?: '-';
        $paralelo = trim((string)($rMp['paralelo'] ?? '')) ?: '-';
    }

    $cache_curso_mp[$curso_id] = [$materia, $paralelo];
    return $cache_curso_mp[$curso_id];
};

$resolver_estudiantes_alerta_por_curso_docente = function($curso_id, $docente_id) use ($conn, &$cache_estudiantes_alertas) {
    $curso_id = (int)$curso_id;
    $docente_id = (int)$docente_id;
    if ($curso_id <= 0 || $docente_id <= 0) return '';

    $cache_key = $curso_id . '|' . $docente_id;
    if (array_key_exists($cache_key, $cache_estudiantes_alertas)) {
        return $cache_estudiantes_alertas[$cache_key];
    }

    $nombres = [];
    $sql = "SELECT u.nombres, MAX(ns.id) AS max_id
            FROM notificaciones_seguridad_examen ns
            LEFT JOIN usuarios u ON u.id = ns.estudiante_id
            WHERE ns.curso_id = $curso_id
            AND ns.docente_id = $docente_id
            AND IFNULL(ns.oculto_docente, 0) = 1
            GROUP BY u.nombres
            ORDER BY max_id DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $nom = trim((string)($row['nombres'] ?? ''));
            if ($nom !== '') $nombres[] = $nom;
        }
    }

    if (empty($nombres)) {
        $sql2 = "SELECT u.nombres, MAX(ns.id) AS max_id
                 FROM notificaciones_seguridad_examen ns
                 LEFT JOIN usuarios u ON u.id = ns.estudiante_id
                 WHERE ns.curso_id = $curso_id
                 AND ns.docente_id = $docente_id
                 GROUP BY u.nombres
                 ORDER BY max_id DESC";
        $res2 = $conn->query($sql2);
        if ($res2) {
            while ($row = $res2->fetch_assoc()) {
                $nom = trim((string)($row['nombres'] ?? ''));
                if ($nom !== '') $nombres[] = $nom;
            }
        }
    }

    if (empty($nombres)) {
        $cache_estudiantes_alertas[$cache_key] = '';
        return '';
    }

    $vista = array_slice($nombres, 0, 4);
    $extra = count($nombres) - count($vista);
    $txt = implode(', ', $vista);
    if ($extra > 0) $txt .= " y $extra mas";

    $cache_estudiantes_alertas[$cache_key] = $txt;
    return $txt;
};
?>

<form method="POST" action="admin.php?seccion=historial" onsubmit="return confirmarEliminarLogsSeleccionados();" style="margin:0 0 12px;">
    <button type="submit" name="eliminar_logs_seleccionados" class="btn-delete" style="padding:9px 14px; background:#b91c1c; border-color:#991b1b;">
        Eliminar seleccionadas
    </button>
    <div id="historialTablaWrap" class="tabla-wrap" style="height:600px; margin:10px 0 0;">
    <table class="tabla-usuarios">
        <thead>
            <tr>
                <th style="width:40px; text-align:center;"><input type="checkbox" id="historialCheckAll" onchange="toggleHistorialChecks(this)"></th>
                <th style="width:160px;">Fecha y Hora</th>
                <th>Actor (Quién realizó)</th>
                <th>Rol</th>
                <th>Acción</th>
                <th>Entidad Afectada</th>
                <th>Materia</th>
                <th>Paralelo</th>
                <th style="min-width:420px;">Detalles</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($hay_logs): ?>
                <?php while($log = $res_logs->fetch_assoc()): ?>
                    <?php
                    $fecha_fmt = date("d/m/Y H:i:s", strtotime($log['fecha_registro']));
                    $fecha_iso = date("Y-m-d", strtotime($log['fecha_registro']));
                    $detalle_raw = trim((string)($log['detalles'] ?? ''));
                    $detalle_mostrar = $detalle_raw;
                    $accion_norm = strtoupper(trim((string)($log['accion'] ?? '')));
                    $entidad_norm = strtoupper(trim((string)($log['entidad_afectada'] ?? '')));
                    $afectado_nombre = trim((string)($log['afectado_nombre'] ?? ''));
                    $es_accion_usuario = (
                        $entidad_norm === 'USUARIO' ||
                        $entidad_norm === 'INTENTO' ||
                        $accion_norm === 'REHABILITAR' ||
                        strpos($accion_norm, 'USUARIO') !== false ||
                        strpos($accion_norm, 'ESTUDIANTE') !== false
                    );

                    if ($es_accion_usuario && $afectado_nombre !== '') {
                        if ($accion_norm === 'REHABILITAR') {
                            $detalle_mostrar = $afectado_nombre;
                        } elseif ($detalle_mostrar === '') {
                            $detalle_mostrar = $afectado_nombre;
                        } elseif (stripos($detalle_mostrar, $afectado_nombre) === false) {
                            $detalle_mostrar = $afectado_nombre . " | " . $detalle_mostrar;
                        }
                    }
                    // Si REHABILITAR viene con detalle compuesto "Nombre | Examen X", mostrar solo el nombre.
                    if ($accion_norm === 'REHABILITAR' && strpos($detalle_mostrar, '|') !== false) {
                        $partes_det = explode('|', $detalle_mostrar, 2);
                        $nombre_det = trim((string)($partes_det[0] ?? ''));
                        if ($nombre_det !== '') { $detalle_mostrar = $nombre_det; }
                    }
                    if ($accion_norm === 'ALERTA_SEGURIDAD') {
                        $nombre_est = trim((string)($log['actor_nombre'] ?? ''));
                        if ($nombre_est === '') $nombre_est = 'Estudiante';

                        $tipo_alerta = '-';
                        if (preg_match('/Tipo:\s*([^\.]+)\./i', $detalle_raw, $mTipo)) {
                            $tipo_alerta = trim((string)$mTipo[1]);
                        }

                        $motivo_alerta = '';
                        if (preg_match('/Motivo:\s*(.+)$/i', $detalle_raw, $mMotivo)) {
                            $motivo_alerta = trim((string)$mMotivo[1]);
                        }
                        if ($motivo_alerta === '') $motivo_alerta = $detalle_raw;

                        $detalle_mostrar = "Alerta de seguridad detectada. Estudiante: $nombre_est. Tipo: $tipo_alerta. Motivo: $motivo_alerta";
                    }
                    if ($accion_norm === 'OCULTAR_ALERTAS_SEG') {
                        $cant_ocultadas = 0;
                        if (preg_match('/Oculto\s+(\d+)\s+alerta/i', $detalle_raw, $mCant)) {
                            $cant_ocultadas = (int)$mCant[1];
                        }

                        $curso_det = (int)($log['id_afectado'] ?? 0);
                        if ($curso_det <= 0 && preg_match('/\bcurso\s+(\d+)\b/i', $detalle_raw, $mCursoDet)) {
                            $curso_det = (int)$mCursoDet[1];
                        }

                        $estudiantes_txt = '';
                        if (preg_match('/Estudiante\(s\):\s*(.+)$/i', $detalle_raw, $mEstTxt)) {
                            $estudiantes_txt = trim((string)$mEstTxt[1]);
                        }
                        if ($estudiantes_txt === '' && $curso_det > 0) {
                            $estudiantes_txt = $resolver_estudiantes_alerta_por_curso_docente($curso_det, (int)($log['actor_id'] ?? 0));
                        }

                        $cant_txt = ($cant_ocultadas > 0) ? (string)$cant_ocultadas : '?';
                        $detalle_mostrar = "Oculto $cant_txt alerta(s) de seguridad.";
                        if ($estudiantes_txt !== '') {
                            $detalle_mostrar .= " Estudiante(s): $estudiantes_txt";
                        }
                    }
                    if ($detalle_mostrar === '') { $detalle_mostrar = '-'; }

                    // Resolver Materia/Paralelo según el contexto del log
                    $curso_log_id = 0;
                    if ($entidad_norm === 'CURSO') {
                        $curso_log_id = (int)($log['id_afectado'] ?? 0);
                    }

                    $examen_log_id = 0;
                    if ($entidad_norm === 'EXAMEN') {
                        $examen_log_id = (int)($log['id_afectado'] ?? 0);
                    }
                    // Soporte para detalles antiguos/nuevos que traen "Examen N"
                    if ($examen_log_id <= 0 && preg_match('/\bExamen\s+(\d+)\b/i', (string)($log['detalles'] ?? ''), $mEx)) {
                        $examen_log_id = (int)$mEx[1];
                    }
                    if ($examen_log_id > 0 && $curso_log_id <= 0) {
                        $curso_log_id = $resolver_curso_por_examen($examen_log_id);
                    }
                    // Soporte para detalles que traen "Curso N"
                    if ($curso_log_id <= 0 && preg_match('/\bCurso\s+(\d+)\b/i', (string)($log['detalles'] ?? ''), $mCurso)) {
                        $curso_log_id = (int)$mCurso[1];
                    }
                    if ($curso_log_id <= 0 && $accion_norm === 'OCULTAR_ALERTAS_SEG') {
                        $curso_log_id = (int)($log['id_afectado'] ?? 0);
                    }

                    [$materia_mostrar, $paralelo_mostrar] = $resolver_materia_paralelo_por_curso($curso_log_id);
                    ?>
                    <tr class="historial-row"
                        data-actor="<?= htmlspecialchars((string)($log['actor_nombre'] ?? '')) ?>"
                        data-accion="<?= htmlspecialchars((string)($log['accion'] ?? '')) ?>"
                        data-fecha="<?= htmlspecialchars($fecha_iso) ?>">
                        <td style="text-align:center;"><input type="checkbox" class="chk-log" name="logs_ids[]" value="<?= (int)$log['id'] ?>"></td>
                        <td style="font-weight:800; color:#1f3b57;"><?= $fecha_fmt ?></td>
                        <td><?= htmlspecialchars($log['actor_nombre']) ?></td>
                        <td><?= htmlspecialchars($log['rol_actor']) ?></td>
                        <td>
                            <span style="background:#e7f2ff; color:#0b5591; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:800; border:1px solid #cfe3ff;">
                                <?= htmlspecialchars($log['accion']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['entidad_afectada']) ?></td>
                        <td><?= htmlspecialchars($materia_mostrar) ?></td>
                        <td><?= htmlspecialchars($paralelo_mostrar) ?></td>
                        <td style="font-size:13px; color:#556; vertical-align:top;">
                            <div style="white-space:normal; overflow-wrap:anywhere; word-break:break-word; line-height:1.35; max-width:620px;">
                                <?= htmlspecialchars($detalle_mostrar) ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
            <tr id="historialNoResultados" style="<?= $hay_logs ? 'display:none;' : '' ?>">
                <td colspan="9" style="text-align:center; padding:20px; color:#777;">
                    No se encontraron registros en el historial con los filtros aplicados.
                </td>
            </tr>
        </tbody>
    </table>
</div>
</form>

<script>
function toggleHistorialChecks(master) {
    const filas = document.querySelectorAll('#historialTablaWrap tbody tr.historial-row');
    filas.forEach(fila => {
        if (fila.style.display === 'none') return;
        const chk = fila.querySelector('.chk-log');
        if (chk) chk.checked = master.checked;
    });
}
function confirmarEliminarLogsSeleccionados() {
    const total = document.querySelectorAll('.chk-log:checked').length;
    if (total === 0) {
        alert('Selecciona al menos un registro.');
        return false;
    }
    return confirm(`Eliminar ${total} registro(s) seleccionados del historial?`);
}
function normalizarTextoHistorial(txt) {
    return (txt || '')
        .toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}
function filtrarHistorialTiempoReal() {
    const filtroActorEl = document.getElementById('historialFiltroActor');
    const filtroAccionEl = document.getElementById('historialFiltroAccion');
    const filtroDesdeEl = document.getElementById('historialFiltroDesde');
    const filtroHastaEl = document.getElementById('historialFiltroHasta');
    const chkMaster = document.getElementById('historialCheckAll');

    const actorFiltro = normalizarTextoHistorial(filtroActorEl ? filtroActorEl.value : '');
    const accionFiltro = ((filtroAccionEl ? filtroAccionEl.value : '') || '').toUpperCase().trim();
    const fechaDesde = (filtroDesdeEl ? filtroDesdeEl.value : '') || '';
    const fechaHasta = (filtroHastaEl ? filtroHastaEl.value : '') || '';

    let visibles = 0;
    document.querySelectorAll('#historialTablaWrap tbody tr.historial-row').forEach(fila => {
        const actorFila = normalizarTextoHistorial(fila.getAttribute('data-actor') || '');
        const accionFila = ((fila.getAttribute('data-accion') || '') + '').toUpperCase().trim();
        const fechaFila = (fila.getAttribute('data-fecha') || '') + '';

        let mostrar = true;
        if (actorFiltro && !actorFila.includes(actorFiltro)) mostrar = false;
        if (accionFiltro && accionFila !== accionFiltro) mostrar = false;
        if (fechaDesde && fechaFila < fechaDesde) mostrar = false;
        if (fechaHasta && fechaFila > fechaHasta) mostrar = false;

        fila.style.display = mostrar ? '' : 'none';
        if (!mostrar) {
            const chk = fila.querySelector('.chk-log');
            if (chk) chk.checked = false;
        } else {
            visibles++;
        }
    });

    const filaSinResultados = document.getElementById('historialNoResultados');
    if (filaSinResultados) filaSinResultados.style.display = (visibles === 0) ? '' : 'none';
    if (chkMaster) chkMaster.checked = false;
}
function limpiarFiltrosHistorial() {
    const actor = document.getElementById('historialFiltroActor');
    const accion = document.getElementById('historialFiltroAccion');
    const desde = document.getElementById('historialFiltroDesde');
    const hasta = document.getElementById('historialFiltroHasta');

    if (actor) actor.value = '';
    if (accion) accion.value = '';
    if (desde) desde.value = '';
    if (hasta) hasta.value = '';

    filtrarHistorialTiempoReal();
}
document.addEventListener('DOMContentLoaded', function() {
    const actor = document.getElementById('historialFiltroActor');
    const accion = document.getElementById('historialFiltroAccion');
    const desde = document.getElementById('historialFiltroDesde');
    const hasta = document.getElementById('historialFiltroHasta');

    if (actor) actor.addEventListener('input', filtrarHistorialTiempoReal);
    if (accion) accion.addEventListener('change', filtrarHistorialTiempoReal);
    if (desde) desde.addEventListener('change', filtrarHistorialTiempoReal);
    if (desde) desde.addEventListener('input', filtrarHistorialTiempoReal);
    if (hasta) hasta.addEventListener('change', filtrarHistorialTiempoReal);
    if (hasta) hasta.addEventListener('input', filtrarHistorialTiempoReal);

    filtrarHistorialTiempoReal();
});
</script>
