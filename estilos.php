<link href="[https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap](https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap)" rel="stylesheet">
<style>
  :root{
    --primary:#0f6cb6;
    --primary-dark:#0b5591;
    --bg:#f2f2f2;
    --text:#373a3c;
    --muted:#6c757d;
    --border:#e1e1e1;
    --sidebar:#f4f4f4;
    --card:#ffffff;
  }

  body{ font-family:'Open Sans', Arial, sans-serif; background:var(--bg); margin:0; padding:0; color:var(--text); font-size:0.92rem; }

  .navbar{ position:fixed; top:0; left:0; right:0; height:60px; background:#fff; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; padding:0 18px; z-index:1200; box-sizing:border-box; }
  .brand{ display:flex; align-items:center; gap:10px; text-decoration:none; }
  .brand-logo{ height:32px; width:auto; display:block; }
  .brand .sub{ color:#666; font-weight:600; font-size:1rem; }
  .navbar-right{ display:flex; align-items:center; gap:12px; }
  .navbar-right > span:not(.chip-caret), .navbar-right > .avatar { display:none; }

  /* Chip de usuario (avatar + nombre + rol) */
  .user-chip{ display:flex; align-items:center; gap:10px; padding:6px 10px; border:1px solid #e5e7eb; border-radius:999px; background:#fff; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
  .user-chip-text{ display:flex; flex-direction:column; line-height:1.15; }
  .user-chip-name{ font-weight:800; color:#0f172a; font-size:0.92rem; }
  .user-chip-role{ font-size:0.78rem; color:#6b7280; }
  .chip-avatar{ width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg, #0f6cb6 0%, #0b5591 100%); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.9rem; }
  .chip-caret{ font-size:0.85rem; color:#6b7280; }

  .avatar{ width:32px;height:32px; border-radius:50%; background:#eef2f6; display:flex;align-items:center;justify-content:center; font-weight:800; color:#556; border:1px solid #dde3ea; }
  a.logout{ text-decoration:none; background:#d9534f; color:#fff; padding:8px 12px; border-radius:8px; font-size:13px; font-weight:800; }
  a.logout:hover{ background:#b52b27; }

  .main-wrapper{ display:flex; margin-top:60px; min-height:calc(100vh - 60px); }
  .sidebar{ width:300px; background:var(--sidebar); border-right:1px solid #ddd; padding:14px 0; box-sizing:border-box; position:fixed; top:60px; bottom:0; overflow:auto; }
  .page-content{ flex:1; margin-left:300px; padding:22px 22px 28px; box-sizing:border-box; min-width: 0; }

  .nav-title{ padding:10px 18px 6px; font-size:.78rem; font-weight:800; color:#6a6a6a; text-transform:uppercase; letter-spacing:.5px; }
  .nav-link{ display:block; padding:10px 18px; text-decoration:none; color:#333; border-left:4px solid transparent; position:relative; font-weight:600; }
  .nav-link:hover{ background:#e9e9e9; }
  .nav-link.active{ background:#fff; border-left-color:var(--primary); box-shadow:0 1px 2px rgba(0,0,0,0.05); }

  .badge-alerta{ position:absolute; top:8px; right:14px; background-color:#dc3545; color:#fff; border-radius:999px; padding:2px 8px; font-size:12px; font-weight:800; box-shadow:0 2px 4px rgba(0,0,0,0.18); animation:pulse 2s infinite; }
  @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.08); } 100% { transform: scale(1); } }

  .container{ width:100%; max-width:none; margin:0; background:var(--card); padding:18px 18px 22px; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.08); border:1px solid var(--border); }
  .nav{ display:none; }

  #mensaje{ position:fixed; top:80px; right:20px; background:#28a745; color:white; padding:12px 18px; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,0.2); display:none; font-weight:800; z-index:2000; }

  .section-title{ display:flex; justify-content:space-between; align-items:center; background:#fff; padding:18px 24px; margin-top:20px; border-radius:12px; cursor:pointer; color:#1f2937; font-size:1.1rem; font-weight:700; border:1px solid #e5e7eb; box-shadow: 0 2px 4px rgba(0,0,0,0.03); transition: all 0.2s ease; }
  .section-title:hover{ background:#f9fafb; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transform: translateY(-1px); }
  .section-title.open { border-radius: 12px 12px 0 0; border-bottom: 1px solid transparent; }

  .toggle-btn{ background:none; border:none; font-size:18px; line-height:1; cursor:pointer; color:#6b7280; transition:transform 0.3s ease; font-weight:800; }
  .toggle-btn.rotated{ transform: rotate(90deg); color: var(--primary); }

  .content-hidden{ display:none; border:1px solid #e5e7eb; border-top:none; padding:0; background:#fff; border-radius:0 0 12px 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.03); margin-bottom: 20px; border-top: 1px solid #f3f4f6; }

  .group-actions{ display:flex; gap:14px; margin:0 15px 10px 15px; }
  .group-actions .action-btn{ flex:1 1 0; display:flex; align-items:center; justify-content:center; gap:8px; height:48px; border-radius:12px; font-weight:800; font-size:14px; border:1px solid transparent; cursor:pointer; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.1s, box-shadow 0.2s; }
  .group-actions .action-btn:active { transform: scale(0.98); box-shadow: none; }
  .action-blue{ background:var(--primary); color:#fff; }
  .action-blue:hover{ background:var(--primary-dark); }

  .form-semestre, .form-curso { display: none; background: #fff; padding: 20px 25px; border-radius: 12px; border: 1px solid #e5e7eb; margin-bottom: 20px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); flex-direction: column; gap: 15px; animation: slideDown 0.3s ease-out; }
  @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

  .form-semestre h4, .form-curso h4 { margin-top: 0; margin-bottom: 15px; color: var(--primary); font-size: 1.1rem; border-bottom: 1px solid #f3f4f6; padding-bottom: 10px; }
  .form-semestre label, .form-curso label { font-weight: 600; font-size: 0.9rem; color: #4b5563; margin-bottom: 4px; display: block; }
  .form-semestre input, .form-semestre textarea, .form-semestre select, .form-curso input, .form-curso textarea, .form-curso select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; transition: border-color 0.15s; }
  .form-semestre input:focus, .form-curso input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(15, 108, 182, 0.1); }
  .input-group { display: flex; gap: 15px; margin-bottom: 5px; } .input-group > * { flex: 1; }
  .form-semestre button[type="submit"], .form-curso button[type="submit"] { background: var(--primary); color: white; font-weight: 700; padding: 12px; margin-top: 10px; width: 100%; border-radius: 8px; transition: background 0.2s; }
  .form-semestre button[type="submit"]:hover, .form-curso button[type="submit"]:hover { background: var(--primary-dark); }

  .ciclo-selector-wrapper { background: #eef6fc; padding: 20px; border-radius: 12px 12px 0 0; border-bottom: 1px solid #dcebf7; margin-bottom: 0; }
  .ciclo-label { font-size: 0.95rem; font-weight: 700; color: #1f3b57; margin-bottom: 8px; display: block; }
  .ciclo-select-main { width: 100%; max-width: 400px; padding: 10px; font-size: 1rem; border: 2px solid #b8d4ea; border-radius: 8px; color: #334; background: #fff; }
  .alert-select-ciclo { background: #fff3cd; color: #856404; padding: 12px; border-radius: 8px; border: 1px solid #ffeeba; margin-bottom: 15px; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; }

  input, select, textarea { padding:9px 10px; border:1px solid #cfd6dd; border-radius:10px; min-width:200px; font-size:14px; box-sizing:border-box; background:#fff; }
  button { background:var(--primary); color:#fff; border:none; padding:9px 14px; border-radius:10px; cursor:pointer; font-weight:800; }
  .tabla-wrap { height:460px; overflow:auto; border:1px solid var(--border); border-radius:12px; background:#fff; margin:0 15px 15px 15px; max-width:100%; width:100%; display:block; position:relative; }
  table { border-collapse:separate; border-spacing:0; width:100%; min-width:max-content; }
  th, td { border-bottom:1px solid #e9edf2; padding:10px 15px; text-align:left; font-size:14px; background:#fff; white-space:nowrap; }
  th { position:sticky; top:0; background:var(--primary); color:#fff; z-index:2; box-shadow:0 2px 0 rgba(0,0,0,0.06); font-weight:800; }
  tr:nth-child(even) td { background:#fcfdff; }
  .table-actions { display:flex; gap:10px; align-items:center; justify-content:flex-start; margin:10px 15px 8px 15px; flex-wrap:wrap; }
  .btn-save { background:#198754; } .btn-delete { background:#dc3545; } .btn-accept-all { background:#0d6efd; }
  .buscador-container { margin:0 15px 12px 15px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
  .buscador-container input { flex-grow:1; }
  .buscador-container select{ padding: 10px; font-size: 15px; border: 1px solid #cfd6dd; border-radius: 10px; background: #fff; font-weight: 600; color: #4b5563; cursor: pointer; }

  .import-box { display:flex; gap:10px; align-items:center; padding:12px; background:#f7fafc; border:1px dashed #c7d2e3; border-radius:12px; margin:0 15px 14px 15px; flex-wrap:wrap; }
  .import-hint{ font-size:13px; color:#334155; line-height:1.35; }
  @media (max-width: 980px) { .sidebar { width:240px; } .page-content { margin-left:240px; } }
  @media (max-width: 760px) { .sidebar { position:static; width:100%; } .page-content { margin-left:0; } .main-wrapper { flex-direction:column; } }
  .accordion-scroll { max-height: calc(100vh - 170px); overflow: auto; padding: 0 !important; }
  .accordion-inner { padding: 16px; box-sizing: border-box; }
</style>
