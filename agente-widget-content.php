<?php
// agente-widget-content.php - Widget content only (para incluir en modales)
include "./class_lib/sesionSecurity.php";

$usuarioSesion = [
  'id' => $_SESSION['id_usuario'] ?? null,
  'username' => $_SESSION['nombre_de_usuario'] ?? null,
  'nombre_real' => $_SESSION['nombre_real'] ?? $_SESSION['nombre_de_usuario'] ?? null,
  'email' => $_SESSION['email'] ?? null,
  'id_cliente' => $_SESSION['id_cliente'] ?? null
];
?>

<div class="agente-stage">
  <section class="agente-pane agente-center">
    <div class="agente-hud">
      <span class="agente-chip" id="btnIniciar">Iniciar</span>
      <span class="agente-chip" id="btnReactivar"
        style="display:none;background:#22d3ee;color:#031419;font-weight:bold;box-shadow:0 0 20px rgba(34,211,238,0.5);animation:pulse 2s infinite">🔊
        Reactivar Bot</span>
      <span class="agente-chip" data-q="precio monstera">💲 precio</span>
      <span class="agente-chip" data-q="qué hay disponible">📦 disponibles</span>
      <span class="agente-chip" data-q="qué hay disponible de plantas de interior">🏷️ por tipo</span>
    </div>
    <canvas id="canvas3d"></canvas>
    <audio id="remote" autoplay playsinline></audio>
  </section>

  <section class="agente-pane agente-right">
    <div
      style="display:flex;align-items:center;padding:12px 14px;border-bottom:1px solid var(--stroke);background:linear-gradient(90deg,rgba(14,165,233,.10),transparent)">
      <span>Asistente</span>
      <div class="agente-badge"><span class="agente-dot" id="dot"></span><span id="lat">sin conexión</span></div>
    </div>

    <div class="agente-feed" id="chat"></div>
    <div class="agente-ctrl">
      <textarea id="txt" placeholder="Escribe para que el avatar responda…"></textarea>
      <button class="agente-btn" id="send">Enviar</button>
    </div>
  </section>
</div>

<div class="overlay" id="overlay" style="position:fixed;inset:0;display:none;place-items:center;z-index:9999;background:rgba(2,6,12,.7)">
  <button class="start" id="startBig"
    style="padding:14px 18px;border-radius:14px;background:linear-gradient(180deg,rgba(34,211,238,.15),rgba(0,0,0,.3));border:1px solid rgba(34,211,238,.45);color:#e6f0ff;cursor:pointer">Toca
    para iniciar y activar sonido</button>
</div>

<!-- Profile modal -->
<div class="profile-modal" id="profileModal" style="position:fixed;inset:0;display:none;place-items:center;z-index:9998;background:rgba(4,7,12,.85);backdrop-filter:blur(8px);padding:12px;overflow-y:auto">
  <div class="profile-box" style="background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,0)),var(--glass);border:1px solid var(--stroke);border-radius:12px;backdrop-filter:blur(14px);padding:24px 24px 0;width:100%;max-width:480px;max-height:85vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35);box-sizing:border-box;display:flex;flex-direction:column">
    <h2 style="margin:0 0 8px 0;color:var(--cyan);font-size:22px;flex-shrink:0">Mi Perfil</h2>
    <p class="subtitle" style="margin:0 0 20px 0;font-size:13px;color:#9cc1ff;opacity:.8;flex-shrink:0">Completa tus datos para realizar compras</p>
    <div id="profileError" class="profile-error" style="display:none;padding:10px 12px;border-radius:10px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;font-size:13px;margin-bottom:12px;flex-shrink:0"></div>
    <form class="profile-form" id="profileForm" style="display:flex;flex-direction:column;gap:14px;flex:1;overflow-y:auto;padding-right:4px;margin-bottom:16px">
      <div class="profile-group" style="display:flex;flex-direction:column;gap:5px">
        <label for="profileNombre" style="font-size:12px;color:#cfe7ff;font-weight:500">Nombre / Razón Social <span class="profile-required" style="color:#fca5a5;margin-left:4px">*</span></label>
        <input type="text" id="profileNombre" name="nombre" required style="padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;width:100%">
      </div>
      <div class="profile-group" style="display:flex;flex-direction:column;gap:5px">
        <label for="profileRut" style="font-size:12px;color:#cfe7ff;font-weight:500">RUT <span class="profile-required" style="color:#fca5a5;margin-left:4px">*</span></label>
        <input type="text" id="profileRut" name="rut" required placeholder="12345678-9" style="padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;width:100%">
      </div>
      <div class="profile-group" style="display:flex;flex-direction:column;gap:5px">
        <label for="profileEmail" style="font-size:12px;color:#cfe7ff;font-weight:500">Email <span class="profile-required" style="color:#fca5a5;margin-left:4px">*</span></label>
        <input type="email" id="profileEmail" name="mail" required style="padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;width:100%">
      </div>
      <div class="profile-group" style="display:flex;flex-direction:column;gap:5px">
        <label for="profileTelefono" style="font-size:12px;color:#cfe7ff;font-weight:500">Teléfono <span class="profile-required" style="color:#fca5a5;margin-left:4px">*</span></label>
        <input type="tel" id="profileTelefono" name="telefono" required placeholder="+56912345678" style="padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;width:100%">
      </div>
      <div class="profile-group" style="display:flex;flex-direction:column;gap:5px">
        <label for="profileDomicilio" style="font-size:12px;color:#cfe7ff;font-weight:500">Domicilio / Dirección <span class="profile-required" style="color:#fca5a5;margin-left:4px">*</span></label>
        <textarea id="profileDomicilio" name="domicilio" required placeholder="Calle, número, depto/oficina" style="padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;width:100%;resize:vertical;min-height:60px"></textarea>
      </div>
      <div class="profile-group" style="display:flex;flex-direction:column;gap:5px">
        <label for="profileDomicilio2" style="font-size:12px;color:#cfe7ff;font-weight:500">Domicilio de Envío <span class="profile-required" style="color:#fca5a5;margin-left:4px">*</span></label>
        <div style="display:flex;gap:8px;align-items:start">
          <textarea id="profileDomicilio2" name="domicilio2" required placeholder="Dirección de entrega para tus pedidos" style="flex:1;padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;resize:vertical;min-height:60px"></textarea>
          <button type="button" id="btnCopyDomicilio" title="Copiar domicilio principal" style="padding:10px;min-height:auto;background:rgba(34,211,238,.2);border:1px solid rgba(34,211,238,.4);color:#22d3ee;font-size:12px;border-radius:10px;cursor:pointer">📋</button>
        </div>
      </div>
      <div class="profile-group" style="display:flex;flex-direction:column;gap:5px">
        <label for="profileRegion" style="font-size:12px;color:#cfe7ff;font-weight:500">Región <span class="profile-required" style="color:#fca5a5;margin-left:4px">*</span></label>
        <select id="profileRegion" name="region" required style="padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;width:100%">
          <option value="">Selecciona región</option>
        </select>
      </div>
      <div class="profile-group" style="display:flex;flex-direction:column;gap:5px">
        <label for="profileProvincia" style="font-size:12px;color:#cfe7ff;font-weight:500">Provincia <span class="profile-required" style="color:#fca5a5;margin-left:4px">*</span></label>
        <input type="text" id="profileProvincia" name="provincia" required placeholder="Provincia" style="padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;width:100%">
      </div>
      <div class="profile-group" style="display:flex;flex-direction:column;gap:5px">
        <label for="profileComuna" style="font-size:12px;color:#cfe7ff;font-weight:500">Comuna <span class="profile-required" style="color:#fca5a5;margin-left:4px">*</span></label>
        <select id="profileComuna" name="comuna" required style="padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;width:100%">
          <option value="">Selecciona comuna</option>
        </select>
      </div>
      <div class="profile-actions" style="display:flex;gap:10px;padding:16px 24px;margin:0 -24px;border-top:1px solid var(--stroke);background:rgba(5,10,16,.6);flex-shrink:0">
        <button type="button" class="profile-btn profile-btn-cancel" id="profileCancelBtn" style="flex:1;padding:12px;border:none;border-radius:10px;font-weight:bold;font-size:14px;cursor:pointer;background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);color:#fca5a5">Cancelar</button>
        <button type="submit" class="profile-btn profile-btn-save" id="profileSaveBtn" style="flex:1;padding:12px;border:none;border-radius:10px;font-weight:bold;font-size:14px;cursor:pointer;background:linear-gradient(90deg,var(--teal),var(--cyan));color:#031419">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Cart floating panel inside modal -->
<div id="cartFloatPanel" class="hidden" style="position:absolute;right:16px;bottom:16px;width:300px;max-height:400px;overflow:auto;z-index:100;background:var(--glass);border:1px solid var(--stroke);border-radius:12px;backdrop-filter:blur(14px);box-shadow:0 12px 40px rgba(0,0,0,.35)">
  <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid var(--stroke);font-size:13px;color:#cfe7ff">
    <strong>Tu carrito</strong><span id="cartFloatCount" style="opacity:.75"></span>
    <button id="cartFloatClose"
      style="margin-left:auto;background:transparent;border:1px solid var(--stroke);color:#cfe2ff;border-radius:8px;padding:4px 8px;cursor:pointer">Cerrar</button>
  </div>
  <div id="cartFloatBody" style="padding:10px 12px"></div>
</div>

<script>
  window.PHP_SESSION_USER = <?php echo json_encode($usuarioSesion); ?>;
</script>

<script type="importmap">
{"imports": {"three": "/vendor/three/three.module.js"}}
</script>

<!-- Debug helper -->
<script>
(function () {
  const wrap = document.querySelector('.dbg-wrap');
  if (!wrap) {
    // Crear debug wrapper si no existe
    const dbgWrap = document.createElement('div');
    dbgWrap.className = 'dbg-wrap';
    dbgWrap.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:20;font-family:ui-monospace,Menlo,Consolas,monospace';
    dbgWrap.innerHTML = '<div id="dbgLog" style="display:none"></div>';
    document.body.appendChild(dbgWrap);
  }

  window.rpLog = function (k, msg, extra = null, level = 'log') {
    const t = new Date().toISOString();
    const line = JSON.stringify({ t, k, msg, extra, level });
    (level === 'err' ? console.error : console.log)('[DBG]', line);
  };
  window.rpLog('boot', 'prelude');
})();
</script>

<!-- Agente Widget Script -->
<script src="../agente-widget.js?v=1112221" type="module"></script>
