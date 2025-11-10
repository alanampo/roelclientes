<?php
include "./class_lib/sesionSecurity.php";
var_dump($_SESSION);die;
if (!str_contains(mb_strtolower($_SESSION['nombre_de_usuario']), "alanampo")){
    http_response_code(403);
    echo "Acceso denegado.";
    exit;
}
// Obtener datos del usuario de la sesión
$usuarioSesion = [
    'id' => $_SESSION['id_usuario'] ?? null,
    'username' => $_SESSION['nombre_de_usuario'] ?? null,
    'nombre_real' => $_SESSION['nombre_real'] ?? $_SESSION['nombre_de_usuario'] ?? null,
    'email' => $_SESSION['email'] ?? null,
    'id_cliente' => $_SESSION['id_cliente'] ?? null
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<title>Roelplant · Agente AI</title>
<style>
:root{--bg:#04070c;--glass:rgba(8,14,24,.55);--cyan:#22d3ee;--teal:#14b8a6;--text:#e6f0ff;--stroke:rgba(255,255,255,.08);--debug-h:0px;--topbar-h:60px}
html,body{height:100%;margin:0;background:radial-gradient(1200px 600px at 50% -10%, rgba(34,211,238,.08), transparent),var(--bg);color:#e6f0ff;font:14px/1.45 ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial;overflow-x:hidden}
*{box-sizing:border-box}
.stage{position:fixed;left:0;right:0;top:var(--topbar-h);bottom:var(--debug-h);display:grid;grid-template-columns:minmax(280px,22%) 1fr minmax(320px,24%);gap:16px;padding:16px}
.pane{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,0)),var(--glass);border:1px solid var(--stroke);border-radius:16px;backdrop-filter:blur(14px);overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.35),inset 0 0 0 1px rgba(255,255,255,.02)}
.pane h3{margin:0;padding:12px 14px;font-size:13px;letter-spacing:.3px;color:#cfe7ff;border-bottom:1px solid var(--stroke);background:linear-gradient(90deg,rgba(14,165,233,.10),transparent)}
.left .feed{flex:1;overflow:auto;padding:10px}
.left .card{border:1px solid rgba(34,211,238,.25);border-radius:12px;overflow:hidden;margin:8px 0;background:rgba(9,16,25,.6)}
.left .card img{width:100%;display:block;aspect-ratio:16/10;object-fit:cover}
.center{position:relative}
.hud{position:absolute;left:12px;top:12px;display:flex;gap:8px;z-index:100}
.chip{padding:6px 10px;font-size:12px;border-radius:999px;color:#dff9ff;background:rgba(34,211,238,.14);border:1px solid rgba(34,211,238,.35);cursor:pointer;user-select:none}
#canvas3d{position:absolute;inset:0;display:block;width:100%;height:100%;background:
  radial-gradient(1000px 220px at 50% 0%, rgba(34,211,238,.06), transparent),
  radial-gradient(800px 300px at 50% 100%, rgba(14,165,233,.08), transparent)}
.right .feed{flex:1;overflow:auto;padding:12px}
.msg{background:rgba(12,18,30,.7);border:1px solid var(--stroke);border-radius:12px;padding:10px 12px;margin:8px 0}
.ctrl{display:flex;gap:10px;padding:10px;border-top:1px solid var(--stroke);background:rgba(5,10,16,.4)}
.ctrl textarea{flex:1;height:56px;resize:none;border-radius:12px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;padding:10px;font:inherit}
.btn{min-height:46px;border:none;border-radius:12px;padding:0 16px;cursor:pointer;color:#031419;background:linear-gradient(90deg,var(--teal),var(--cyan))}
.topbar{display:flex;align-items:center;gap:10px;padding:16px;height:var(--topbar-h);box-sizing:border-box}
.badge{margin-left:auto;font-size:12px;color:#bcd0e6;display:flex;align-items:center;gap:8px}
.dot{width:9px;height:9px;border-radius:50%;background:#ef4444;box-shadow:0 0 10px rgba(239,68,68,.7)}
.overlay{position:fixed;inset:0;display:grid;place-items:center;z-index:9;background:rgba(2,6,12,.7)}
.start{padding:14px 18px;border-radius:14px;background:linear-gradient(180deg,rgba(34,211,238,.15),rgba(0,0,0,.3));border:1px solid rgba(34,211,238,.45);color:#e6f0ff;cursor:pointer}
.footer-links{position:fixed;left:16px;bottom:calc(64px + var(--debug-h));display:flex;gap:10px;z-index:4}
/* Tablet */
@media (max-width:1100px){.stage{grid-template-columns:26% 1fr 28%}}

/* Mobile - Diseño vertical con avatar pequeño arriba */
@media (max-width:920px){
  :root{
    --topbar-h:auto;
  }
  .stage{
    grid-template-columns:1fr;
    grid-template-rows:180px 1fr;
    gap:8px;
    padding:8px;
    left:0;
    right:0;
  }
  .left{display:none}
  .center{
    order:1;
    height:180px;
    min-height:180px;
  }
  .right{
    order:2;
    height:auto;
  }
  .topbar{
    flex-wrap:wrap;
    height:auto;
    min-height:50px;
    padding:8px;
    gap:6px;
  }
  .topbar > div:first-child{
    flex:1 1 100%;
  }
  #userDisplay{
    font-size:10px;
  }
  .chip{
    font-size:10px;
    padding:4px 7px;
  }
  .hud{
    flex-wrap:wrap;
    gap:4px;
    left:6px;
    top:6px;
  }
  .footer-links{
    position:relative;
    left:auto;
    bottom:auto;
    margin:8px;
    flex-wrap:wrap;
    font-size:11px;
  }
  .cart-panel{
    right:0;
    left:0;
    bottom:var(--debug-h);
    width:100%;
    max-width:100%;
    border-radius:12px 12px 0 0;
  }
}

/* Mobile pequeño - Avatar aún más compacto */
@media (max-width:640px){
  .stage{
    grid-template-rows:140px 1fr;
    gap:6px;
    padding:6px;
    top:50px;
  }
  .center{
    height:140px;
    min-height:140px;
  }
  .topbar{
    padding:6px 8px;
    min-height:46px;
  }
  .topbar strong{
    font-size:12px;
  }
  .msg{
    font-size:13px;
    padding:7px 9px;
    margin:5px 0;
  }
  .ctrl{
    padding:6px;
    gap:6px;
  }
  .ctrl textarea{
    height:44px;
    font-size:14px;
    padding:8px;
  }
  .btn{
    min-height:38px;
    padding:0 10px;
    font-size:13px;
  }
  .pane h3{
    font-size:11px;
    padding:8px 10px;
  }
  .pane{
    border-radius:10px;
  }
  .right .feed{
    padding:8px;
  }
}
.dbg-wrap{position:fixed;left:0;right:0;bottom:0;z-index:20;font-family:ui-monospace,Menlo,Consolas,monospace}
.dbg-head{display:flex;align-items:center;gap:10px;padding:6px 10px;background:#0b1220;color:#9cc1ff;border-top:1px solid rgba(255,255,255,.12)}
.dbg-head button{border:1px solid rgba(255,255,255,.18);background:#0e1626;color:#cfe2ff;padding:6px 10px;border-radius:8px;cursor:pointer}
.dbg{display:none;max-height:32vh;overflow:auto;background:#08111d;color:#cfe2ff;padding:8px 10px;border-top:1px solid rgba(255,255,255,.08)}
.dbg pre{margin:0;white-space:pre-wrap;word-break:break-word;font-size:12px}

/* Cart panel */
.cart-panel{position:fixed;right:16px;bottom:calc(16px + var(--debug-h));width:320px;max-height:56vh;overflow:auto;z-index:6;
  background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,0)),var(--glass);
  border:1px solid var(--stroke);border-radius:14px;backdrop-filter:blur(14px);box-shadow:0 12px 40px rgba(0,0,0,.35)}
.cart-head{display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid var(--stroke);font-size:13px;color:#cfe7ff}
.cart-body{padding:10px 12px}
.cart-item{display:grid;grid-template-columns:48px 1fr auto;gap:10px;align-items:center;margin:8px 0}
.cart-item img{width:48px;height:48px;border-radius:8px;object-fit:cover}
.cart-item .meta{font-size:12px;opacity:.9}
.cart-total{display:flex;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid var(--stroke)}
.hidden{display:none}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.7}}

/* Profile modal */
.profile-modal{position:fixed;inset:0;display:none;place-items:center;z-index:998;background:rgba(4,7,12,.85);backdrop-filter:blur(8px);padding:12px;overflow-y:auto}
.profile-modal.active{display:grid}
.profile-box{background:linear-gradient(180deg,rgba(255,255,255,.02),rgba(255,255,255,0)),var(--glass);border:1px solid var(--stroke);border-radius:12px;backdrop-filter:blur(14px);padding:24px 24px 0;width:100%;max-width:480px;max-height:85vh;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35),inset 0 0 0 1px rgba(255,255,255,.02);box-sizing:border-box;display:flex;flex-direction:column}
.profile-box h2{margin:0 0 8px 0;color:var(--cyan);font-size:22px;flex-shrink:0}
.profile-box .subtitle{margin:0 0 20px 0;font-size:13px;color:#9cc1ff;opacity:.8;flex-shrink:0}
.profile-form{display:flex;flex-direction:column;gap:14px;flex:1;overflow-y:auto;padding-right:4px;margin-bottom:16px}
.profile-group{display:flex;flex-direction:column;gap:5px}
.profile-group label{font-size:12px;color:#cfe7ff;font-weight:500}
.profile-group input,.profile-group textarea,.profile-group select{padding:10px 12px;border-radius:10px;border:1px solid var(--stroke);background:rgba(9,16,25,.7);color:#e6f0ff;font:inherit;font-size:14px;box-sizing:border-box;width:100%}
.profile-group textarea{resize:vertical;min-height:60px}
.profile-group input:focus,.profile-group textarea:focus,.profile-group select:focus{outline:none;border-color:var(--cyan);box-shadow:0 0 0 2px rgba(34,211,238,.15)}
.profile-group select option{background:#0e1626;color:#e6f0ff}
.profile-actions{display:flex;gap:10px;padding:16px 24px;margin:0 -24px;border-top:1px solid var(--stroke);background:rgba(5,10,16,.6);flex-shrink:0}
.profile-btn{flex:1;padding:12px;border:none;border-radius:10px;font-weight:bold;font-size:14px;cursor:pointer;transition:all .2s;box-sizing:border-box}
.profile-btn-save{background:linear-gradient(90deg,var(--teal),var(--cyan));color:#031419}
.profile-btn-save:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(34,211,238,.4)}
.profile-btn-cancel{background:rgba(239,68,68,.2);border:1px solid rgba(239,68,68,.4);color:#fca5a5}
.profile-btn-cancel:hover{background:rgba(239,68,68,.3)}
.profile-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
.profile-error{padding:10px 12px;border-radius:10px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#fca5a5;font-size:13px;margin-bottom:12px;flex-shrink:0}
.profile-required{color:#fca5a5;margin-left:4px}

/* Mobile - Modales responsivos */
@media (max-width:640px){
  .profile-modal{
    padding:8px;
  }
  .profile-box{
    padding:16px 16px 0;
    max-height:calc(100vh - 16px);
  }
  .profile-box h2{
    font-size:18px;
    margin:0 0 12px 0;
  }
  .profile-form{
    gap:10px;
    margin-bottom:12px;
  }
  .profile-group{
    gap:3px;
  }
  .profile-group label{
    font-size:11px;
  }
  .profile-group input,.profile-group textarea,.profile-group select{
    padding:8px 10px;
    font-size:13px;
  }
  .profile-actions{
    padding:12px 16px;
    margin:0 -16px;
    gap:8px;
  }
  .profile-btn{
    padding:10px;
    font-size:13px;
  }
  .profile-error{
    font-size:11px;
    padding:8px 10px;
  }
  .profile-box .subtitle{
    font-size:11px;
    margin:0 0 12px 0;
  }
  #btnCopyDomicilio{
    padding:8px !important;
    font-size:11px !important;
    min-width:auto !important;
  }
  .start{
    padding:12px 16px;
    font-size:15px;
  }
  .overlay{
    padding:16px;
  }
  .dbg-head{
    font-size:11px;
    padding:5px 8px;
  }
  .dbg-head button{
    padding:5px 8px;
    font-size:11px;
  }
  .footer-links a{
    font-size:11px;
  }
}
</style>
</head>
<body>
<div class="topbar">
  <a href="inicio.php" class="chip" style="background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.15);text-decoration:none;display:inline-flex;align-items:center;gap:6px">
    ← Volver
  </a>
  <div style="display:flex;align-items:center;gap:10px">
    <div style="width:18px;height:18px;border-radius:50%;background:conic-gradient(from 0deg,#22d3ee,#14b8a6,#0ea5e9,#22d3ee);box-shadow:0 0 14px #22d3ee"></div>
    <strong>Roelplant · Avatar Asesor</strong>
  </div>
  <div style="margin-left:auto;display:flex;align-items:center;gap:12px">
    <span id="userDisplay" style="font-size:13px;color:#bcd0e6"></span>
    <button id="btnProfile" class="chip" style="display:none;background:rgba(34,211,238,.14);border-color:rgba(34,211,238,.35)">👤 Mi Perfil</button>
    <button id="btnLogout" class="chip" style="display:none;background:rgba(239,68,68,.2);border-color:rgba(239,68,68,.4);color:#fca5a5">Cerrar sesión</button>
  </div>
</div>

<div class="stage">
  <section class="pane left">
    <h3>Imágenes referenciadas</h3><div class="feed" id="images"></div>
  </section>

  <section class="pane center">
    <div class="hud">
      <span class="chip" id="btnIniciar">Iniciar</span>
      <span class="chip" id="btnReactivar" style="display:none;background:#22d3ee;color:#031419;font-weight:bold;box-shadow:0 0 20px rgba(34,211,238,0.5);animation:pulse 2s infinite">🔊 Reactivar Bot</span>
      <span class="chip" id="btnCart">🛒 carrito</span>
      <span class="chip" data-q="precio monstera">💲 precio</span>
      <span class="chip" data-q="qué hay disponible">📦 disponibles</span>
      <span class="chip" data-q="qué hay disponible de plantas de interior">🏷️ por tipo</span>
    </div>
    <canvas id="canvas3d"></canvas>
    <audio id="remote" autoplay playsinline></audio>
  </section>

  <section class="pane right">
    <div style="display:flex;align-items:center;padding:12px 14px;border-bottom:1px solid var(--stroke);background:linear-gradient(90deg,rgba(14,165,233,.10),transparent)">
      <span>Asistente</span>
      <div class="badge"><span class="dot" id="dot"></span><span id="lat">sin conexión</span></div>
    </div>
    
    <div class="feed" id="chat"></div>
    <div class="ctrl">
      <textarea id="txt" placeholder="Escribe para que el avatar responda…"></textarea>
      <button class="btn" id="send">Enviar</button>
    </div>
  </section>
</div>

<div class="footer-links">
  <a href="https://clientes.roelplant.cl/catalogo_detalle/" target="_blank" rel="noopener">Catálogo detalle</a>
  <a href="https://clientes.roelplant.cl/catalogo_mayorista/" target="_blank" rel="noopener">Catálogo mayorista</a>
</div>

<div class="overlay" id="overlay"><button class="start" id="startBig">Toca para iniciar y activar sonido</button></div>

<!-- Login screen -->
<!-- Profile modal -->
<div class="profile-modal" id="profileModal">
  <div class="profile-box">
    <h2>Mi Perfil</h2>
    <p class="subtitle">Completa tus datos para realizar compras</p>
    <div id="profileError" class="profile-error" style="display:none"></div>
    <form class="profile-form" id="profileForm">
      <div class="profile-group">
        <label for="profileNombre">Nombre / Razón Social <span class="profile-required">*</span></label>
        <input type="text" id="profileNombre" name="nombre" required>
      </div>
      <div class="profile-group">
        <label for="profileRut">RUT <span class="profile-required">*</span></label>
        <input type="text" id="profileRut" name="rut" required placeholder="12345678-9">
      </div>
      <div class="profile-group">
        <label for="profileEmail">Email <span class="profile-required">*</span></label>
        <input type="email" id="profileEmail" name="mail" required>
      </div>
      <div class="profile-group">
        <label for="profileTelefono">Teléfono <span class="profile-required">*</span></label>
        <input type="tel" id="profileTelefono" name="telefono" required placeholder="+56912345678">
      </div>
      <div class="profile-group">
        <label for="profileDomicilio">Domicilio / Dirección <span class="profile-required">*</span></label>
        <textarea id="profileDomicilio" name="domicilio" required placeholder="Calle, número, depto/oficina"></textarea>
      </div>
      <div class="profile-group">
        <label for="profileDomicilio2">Domicilio de Envío <span class="profile-required">*</span></label>
        <div style="display:flex;gap:8px;align-items:start">
          <textarea id="profileDomicilio2" name="domicilio2" required placeholder="Dirección de entrega para tus pedidos" style="flex:1"></textarea>
          <button type="button" id="btnCopyDomicilio" class="profile-btn" style="padding:10px;min-height:auto;background:rgba(34,211,238,.2);border:1px solid rgba(34,211,238,.4);color:#22d3ee;font-size:12px" title="Copiar domicilio principal">📋</button>
        </div>
      </div>
      <div class="profile-group">
        <label for="profileRegion">Región <span class="profile-required">*</span></label>
        <select id="profileRegion" name="region" required>
          <option value="">Selecciona región</option>
        </select>
      </div>
      <div class="profile-group">
        <label for="profileProvincia">Provincia <span class="profile-required">*</span></label>
        <input type="text" id="profileProvincia" name="provincia" required placeholder="Provincia">
      </div>
      <div class="profile-group">
        <label for="profileComuna">Comuna <span class="profile-required">*</span></label>
        <select id="profileComuna" name="comuna" required>
          <option value="">Selecciona comuna</option>
        </select>
      </div>
      <div class="profile-actions">
        <button type="button" class="profile-btn profile-btn-cancel" id="profileCancelBtn">Cancelar</button>
        <button type="submit" class="profile-btn profile-btn-save" id="profileSaveBtn">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Cart panel -->
<div class="cart-panel hidden" id="cartPanel">
  <div class="cart-head"><strong>Tu carrito</strong><span id="cartCount" style="opacity:.75"></span>
    <button id="cartClose" style="margin-left:auto;background:transparent;border:1px solid var(--stroke);color:#cfe2ff;border-radius:8px;padding:4px 8px;cursor:pointer">Cerrar</button>
  </div>
  <div class="cart-body" id="cartBody"></div>
</div>

<div class="dbg-wrap">
  <div class="dbg-head">
    <strong>Debug</strong>
    <button id="dbgToggle">Mostrar</button>
    <button id="dbgCopy">Copiar</button>
    <span id="dbgLast" style="margin-left:auto;font-size:12px;opacity:.8"></span>
  </div>
  <div class="dbg" id="dbg"><pre id="dbgLog"></pre></div>
</div>

<script>
(function(){
  const wrap=document.querySelector('.dbg-wrap');
  const dbgEl=document.getElementById('dbg'), dbgLog=document.getElementById('dbgLog'), dbgLast=document.getElementById('dbgLast');
  function setOffset(){const headH=wrap.querySelector('.dbg-head').getBoundingClientRect().height||0;const bodyH=(dbgEl.style.display==='block')?dbgEl.getBoundingClientRect().height:0;document.documentElement.style.setProperty('--debug-h',(headH+bodyH)+'px')}
  window.rpLog=function(k,msg,extra=null,level='log'){const t=new Date().toISOString(),line=JSON.stringify({t,k,msg,extra,level});dbgLog.textContent+=(dbgLog.textContent?'\n':'')+line;dbgEl.scrollTop=dbgEl.scrollHeight;dbgLast.textContent=k+' · '+msg;(level==='err'?console.error:console.log)('[DBG]',line)}
  document.getElementById('dbgToggle').onclick=(e)=>{const open=dbgEl.style.display!=='block';dbgEl.style.display=open?'block':'none';e.target.textContent=open?'Ocultar':'Mostrar';setOffset()}
  document.getElementById('dbgCopy').onclick=()=>navigator.clipboard.writeText(dbgLog.textContent||'')
  addEventListener('resize',setOffset);setOffset();rpLog('boot','prelude')
})();
</script>

<script type="importmap">
{ "imports": { "three": "./vendor/three/three.module.js" } }
</script>

<script>
// ========== INJECT PHP SESSION DATA ==========
window.PHP_SESSION_USER = <?php echo json_encode($usuarioSesion); ?>;
</script>

<script>
// ========== AUTHENTICATION SYSTEM (PHP Session-based) ==========
(function initAuth(){
  // UI Elements
  const btnLogout = document.getElementById('btnLogout');
  const btnProfile = document.getElementById('btnProfile');
  const userDisplay = document.getElementById('userDisplay');

  // Get user from PHP session
  function getUser() {
    return window.PHP_SESSION_USER && window.PHP_SESSION_USER.id ? window.PHP_SESSION_USER : null;
  }

  // Update UI with user info
  function updateUserDisplay() {
    const user = getUser();
    if(user) {
      userDisplay.textContent = `${user.username || 'Usuario'}`;
      btnProfile.style.display = 'inline-block';
      btnLogout.style.display = 'inline-block';
    } else {
      userDisplay.textContent = '';
      btnProfile.style.display = 'none';
      btnLogout.style.display = 'none';
    }
  }

  // Logout function - redirect to system logout
  function logout() {
    if(confirm('¿Estás seguro de que deseas cerrar sesión?')) {
      // Clear cart before logout
      if(typeof cartCall === 'function') {
        try {
          cartCall({action: 'clear'}).then(() => {
            window.location.href = 'endsession.php';
          }).catch(() => {
            window.location.href = 'endsession.php';
          });
        } catch(e) {
          window.location.href = 'endsession.php';
        }
      } else {
        window.location.href = 'endsession.php';
      }
    }
  }

  // Event listeners
  if(btnLogout) {
    btnLogout.addEventListener('click', logout);
  }

  // Initialize UI
  updateUserDisplay();

  // Expose functions globally for bot to use
  window.roelAuth = {
    getAccessToken: () => null, // Not used with PHP session
    getUser,
    isAuthenticated: () => !!getUser(),
    refreshToken: () => Promise.resolve(false) // Not needed with PHP session
  };
})();
</script>

<script>
// ========== CLIENT PROFILE SYSTEM ==========
(function initClientProfile(){
  const CLIENTES_API = './server/cliente_data.php';
  const REGIONES_API = './server/regiones.php';
  const COMUNAS_API = './server/comunas.php';

  // UI Elements
  const profileModal = document.getElementById('profileModal');
  const profileForm = document.getElementById('profileForm');
  const profileError = document.getElementById('profileError');
  const profileSaveBtn = document.getElementById('profileSaveBtn');
  const profileCancelBtn = document.getElementById('profileCancelBtn');
  const btnProfile = document.getElementById('btnProfile');
  const btnCopyDomicilio = document.getElementById('btnCopyDomicilio');

  // Form inputs
  const inputs = {
    nombre: document.getElementById('profileNombre'),
    rut: document.getElementById('profileRut'),
    mail: document.getElementById('profileEmail'),
    telefono: document.getElementById('profileTelefono'),
    domicilio: document.getElementById('profileDomicilio'),
    domicilio2: document.getElementById('profileDomicilio2'),
    region: document.getElementById('profileRegion'),
    provincia: document.getElementById('profileProvincia'),
    comuna: document.getElementById('profileComuna')
  };

  let clientData = null;
  let pendingCheckout = false; // Si se abrió desde checkout
  let comunas = [];
  let regiones = [];

  // Load regiones and comunas from API
  async function loadRegionesYComunas() {
    try {
      // Load regiones
      const regionesResp = await fetch(REGIONES_API, {
        credentials: 'same-origin' // Enviar cookies de sesión
      });
      const regionesData = await regionesResp.json();
      if(regionesData.status === 'success') {
        regiones = regionesData.data.regiones; // Array de strings
        inputs.region.innerHTML = '<option value="">Selecciona región</option>' +
          regiones.map(r => `<option value="${r}">${r}</option>`).join('');
      }

      // Load comunas
      const comunasResp = await fetch(COMUNAS_API, {
        credentials: 'same-origin' // Enviar cookies de sesión
      });
      const comunasData = await comunasResp.json();
      if(comunasData.status === 'success') {
        comunas = comunasData.data.comunas;
        inputs.comuna.innerHTML = '<option value="">Selecciona comuna</option>' +
          comunas.map(c => `<option value="${c.id}">${c.nombre} (${c.region})</option>`).join('');
      }
    } catch(error) {
      console.error('Error loading regiones/comunas:', error);
    }
  }

  // Show/hide modal
  function showProfileModal(show = true, fromCheckout = false) {
    profileModal.classList.toggle('active', show);
    pendingCheckout = fromCheckout;
    if(show && !fromCheckout) {
      profileModal.querySelector('.subtitle').textContent = 'Actualiza tus datos de perfil';
    } else if(show && fromCheckout) {
      profileModal.querySelector('.subtitle').textContent = 'Completa tus datos para continuar con la compra';
    }
  }

  // Show error
  function showProfileError(message) {
    profileError.textContent = message;
    profileError.style.display = 'block';
    setTimeout(() => {
      profileError.style.display = 'none';
    }, 5000);
  }

  // Get client data from API
  async function getClientData() {
    const user = window.roelAuth?.getUser();
    if(!user) return null;

    try {
      const response = await fetch(CLIENTES_API, {
        method: 'GET',
        credentials: 'same-origin', // Enviar cookies de sesión
        headers: {
          'Content-Type': 'application/json'
        }
      });

      const data = await response.json();

      if(data.status === 'success' && data.data?.cliente) {
        clientData = data.data.cliente;
        return clientData;
      }

      return null;
    } catch(error) {
      console.error('Error fetching client data:', error);
      return null;
    }
  }

  // Save client data (create or update)
  async function saveClientData(formData) {
    const user = window.roelAuth?.getUser();

    console.log('[PROFILE] saveClientData - User:', user?.id, user?.username);
    console.log('[PROFILE] saveClientData - FormData:', formData);

    if(!user) {
      throw new Error('No autenticado. Por favor recarga la página e intenta nuevamente.');
    }

    try {
      const response = await fetch(CLIENTES_API, {
        method: 'PUT',
        credentials: 'same-origin', // Enviar cookies de sesión
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(formData)
      });

      const data = await response.json();
      console.log('[PROFILE] SAVE response status:', response.status);
      console.log('[PROFILE] SAVE response data:', data);

      if(data.status === 'success') {
        clientData = data.data?.cliente || clientData;
        return { success: true, data: clientData };
      }

      throw new Error(data.message || 'Error al guardar perfil');
    } catch(error) {
      throw error;
    }
  }

  // Fill form with client data
  function fillForm(data) {
    const user = window.roelAuth?.getUser();

    console.log('[PROFILE] fillForm called with data:', data);

    // Si no hay datos de cliente, pre-llenar con datos del usuario
    if(!data && user) {
      inputs.nombre.value = user.nombre_real || user.username || '';
      inputs.rut.value = '';
      inputs.mail.value = user.email || '';
      inputs.telefono.value = '';
      inputs.domicilio.value = '';
      inputs.domicilio2.value = '';
      inputs.region.value = '';
      inputs.provincia.value = '';
      inputs.comuna.value = '';
      return;
    }

    if(!data) return;

    inputs.nombre.value = data.nombre || '';
    inputs.rut.value = data.rut || '';
    inputs.mail.value = data.mail || data.email || '';
    inputs.telefono.value = data.telefono || '';
    inputs.domicilio.value = data.domicilio || '';
    inputs.domicilio2.value = data.domicilio2 || '';

    // Región: intentar match exacto primero, luego case-insensitive
    if(data.region) {
      console.log('[PROFILE] Setting region to:', data.region);
      inputs.region.value = data.region;

      // Si no se seleccionó (porque no coincide exactamente), intentar buscar coincidencia
      if(!inputs.region.value || inputs.region.value === '') {
        console.log('[PROFILE] Exact match failed, trying case-insensitive match');
        const regionLower = data.region.toLowerCase();
        for(let option of inputs.region.options) {
          if(option.value.toLowerCase() === regionLower) {
            inputs.region.value = option.value;
            console.log('[PROFILE] Found match:', option.value);
            break;
          }
        }
      }
    } else {
      inputs.region.value = '';
    }

    inputs.provincia.value = data.provincia || '';
    inputs.comuna.value = data.comuna || '';

    console.log('[PROFILE] Form filled. Region value is now:', inputs.region.value);
  }

  // Validate required fields for checkout
  function validateClientData(data) {
    const missing = [];

    if(!data?.nombre || !data.nombre.trim()) missing.push('nombre');
    if(!data?.rut || !data.rut.trim()) missing.push('rut');
    if(!data?.mail || !data.mail.trim()) missing.push('email');
    if(!data?.telefono || !data.telefono.trim()) missing.push('teléfono');
    if(!data?.domicilio || !data.domicilio.trim()) missing.push('domicilio');
    if(!data?.domicilio2 || !data.domicilio2.trim()) missing.push('domicilio de envío');
    if(!data?.region || !data.region.trim()) missing.push('región');
    if(!data?.provincia || !data.provincia.trim()) missing.push('provincia');
    if(!data?.comuna || !data.comuna) missing.push('comuna');

    return {
      isValid: missing.length === 0,
      missing
    };
  }

  // Check if profile is complete before checkout
  async function checkProfileBeforeCheckout() {
    // Asegurar que regiones y comunas estén cargadas
    if(regiones.length === 0 || comunas.length === 0) {
      await loadRegionesYComunas();
    }

    const data = await getClientData();
    const validation = validateClientData(data);

    if(!validation.isValid) {
      console.log('[PROFILE] Validation failed, missing:', validation.missing);

      // Abrir modal primero
      showProfileModal(true, true);
      console.log('[PROFILE] Modal opened with fromCheckout=true, pendingCheckout is now:', pendingCheckout);

      // Actualizar subtítulo según si existe o no el cliente
      if(!data) {
        profileModal.querySelector('.subtitle').textContent = 'Crea tu perfil de cliente para continuar con la compra';
      } else {
        profileModal.querySelector('.subtitle').textContent = 'Completa tus datos para continuar con la compra';
      }

      // Llenar formulario después de que el modal esté visible y las opciones cargadas
      setTimeout(() => fillForm(data), 100);

      return false;
    }

    return true;
  }

  // Event listeners
  btnProfile.addEventListener('click', async () => {
    // Asegurar que regiones y comunas estén cargadas
    if(regiones.length === 0 || comunas.length === 0) {
      await loadRegionesYComunas();
    }

    const data = await getClientData();

    // Abrir modal primero
    showProfileModal(true, false);

    // Actualizar subtítulo según si existe o no el cliente
    if(!data) {
      profileModal.querySelector('.subtitle').textContent = 'Crear tu perfil de cliente para realizar compras';
    } else {
      profileModal.querySelector('.subtitle').textContent = 'Actualiza tus datos de perfil';
    }

    // Llenar formulario después de que el modal esté visible y las opciones cargadas
    setTimeout(() => fillForm(data), 100);
  });

  profileCancelBtn.addEventListener('click', () => {
    showProfileModal(false);
    pendingCheckout = false;
  });

  profileForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = {
      nombre: inputs.nombre.value.trim(),
      rut: inputs.rut.value.trim(),
      mail: inputs.mail.value.trim(),
      telefono: inputs.telefono.value.trim(),
      domicilio: inputs.domicilio.value.trim(),
      domicilio2: inputs.domicilio2.value.trim(),
      region: inputs.region.value.trim(),
      provincia: inputs.provincia.value.trim(),
      comuna: inputs.comuna.value
    };

    // Validate
    const validation = validateClientData(formData);
    if(!validation.isValid) {
      showProfileError(`Por favor completa los siguientes campos: ${validation.missing.join(', ')}`);
      return;
    }

    try {
      profileSaveBtn.disabled = true;
      profileSaveBtn.textContent = 'Guardando...';

      await saveClientData(formData);

      console.log('[PROFILE] Data saved successfully');
      console.log('[PROFILE] pendingCheckout:', pendingCheckout);
      console.log('[PROFILE] window.triggerCheckout exists:', !!window.triggerCheckout);

      // Guardar el estado ANTES de cerrar el modal (porque showProfileModal resetea pendingCheckout)
      const wasFromCheckout = pendingCheckout;
      const checkoutCallback = window.triggerCheckout;

      // Close modal
      showProfileModal(false);

      // If opened from checkout, trigger checkout
      if(wasFromCheckout) {
        console.log('[PROFILE] Triggering checkout...');

        // Trigger checkout function from bot
        if(checkoutCallback) {
          // Execute immediately
          await checkoutCallback();
          console.log('[PROFILE] Checkout triggered successfully');
        } else {
          console.error('[PROFILE] window.triggerCheckout is not defined!');
        }
      }

    } catch(error) {
      console.error('[PROFILE] Error saving:', error);
      showProfileError(error.message || 'Error al guardar perfil');
    } finally {
      profileSaveBtn.disabled = false;
      profileSaveBtn.textContent = 'Guardar';
    }
  });

  // Copy domicilio to domicilio2
  btnCopyDomicilio.addEventListener('click', () => {
    inputs.domicilio2.value = inputs.domicilio.value;
  });

  // Load regiones y comunas on init
  loadRegionesYComunas();

  // Expose functions globally
  window.clientProfile = {
    checkProfileBeforeCheckout,
    getClientData,
    validateClientData
  };
})();
</script>

<script>
(async function bootstrap(){
  const log=window.rpLog;

  // módulos
  let THREE, GLTFLoader;
  try{ THREE=await import('three'); log('loader','three.module.js ok'); }
  catch(e){ log('loader','three.import.error',String(e),'err'); return; }
  try{ ({GLTFLoader}=await import('./vendor/examples/js/loaders/package/examples/jsm/loaders/GLTFLoader.js?v=8')); log('loader','GLTFLoader.js ok'); }
  catch(e){ log('loader','gltfloader.import.error',String(e),'err'); return; }

  // config
  const RPM_GLTF='https://models.readyplayer.me/68bf40a4c11cea25ec61c5ef.glb';
  const BASE='./server';
  const SESSION_EP=BASE+'/realtime_session.php';
  const TOOL_EP=BASE+'/tool_producto.php';
  const DISP_EP=BASE+'/disponibles_proxy.php';
  const CART_EP='./cart/cart_api.php';
  const FLOW_EP='./cart/flow_create_payment.php';
  const FLOW_STATUS_EP='./cart/flow_check_status.php';
  const SYSTEM_PROMPT=`You are a sales assistant for Roelplant (Chilean plant nursery).

LANGUAGE RULE: Always respond in the SAME language the customer uses:
- If they speak Spanish → respond in Spanish AND ONLY in Spanish
- If they speak English → respond in English AND ONLY in English
- If they speak Italian → respond in Italian AND ONLY in Italian
- If they speak Polish → respond in Polish AND ONLY in Polish
- If they speak Ukrainian → respond in Ukrainian AND ONLY in Ukrainian

SILENT MODE:
- If user says "STOP", "Basta", "Silencio", "Cállate", "Callate", "Stop", "Silenzio", "Стоп" → IMMEDIATELY call "activar_modo_silencio" tool. Say nothing else.
- This tool makes the bot stop responding until user says keywords like "price", "cart", "precio", "carrito", etc.

CART - TOOL USAGE:
1. When customer asks to add to cart ("agregar al carrito", "add to cart", "aggiungi al carrello", "додати до кошика"):
   - Use tool "carrito_operar" with action="add_by_name"
   - Include: name (product name), qty (quantity, default 1), tier ("retail" or "wholesale")
   - Example: If they asked price of "Monstera" and say "add it", call carrito_operar with {action:"add_by_name", name:"Monstera Deliciosa", qty:1, tier:"retail"}

2. To view cart: use carrito_operar with action="summary"

3. IMPORTANT: After any add/update/remove, the function auto-calls summary. DO NOT call it again yourself.

4. Never invent totals or quantities. The tool calculates everything.

5. If customer says "add it" without prior context, ask which product they want to add.

QUERIES:
- For price/stock: use consultar_precio_producto_roelplant
- To list available products: use consultar_disponibles_roelplant
- To filter by type: use consultar_disponibles_por_tipo_roelplant

If off-topic (not about plants): respond "I'm Roelplant's assistant. I only handle plant queries, prices, stock, orders and shipping." (or equivalent in customer's language)`;

  // UI
  const dot=document.getElementById('dot'), lat=document.getElementById('lat'), chat=document.getElementById('chat'), images=document.getElementById('images');
  const txt=document.getElementById('txt'), btnSend=document.getElementById('send');
  const btnIniciar=document.getElementById('btnIniciar'), btnReactivar=document.getElementById('btnReactivar'), remote=document.getElementById('remote'), overlay=document.getElementById('overlay'), startBig=document.getElementById('startBig');
  const btnCart=document.getElementById('btnCart'), cartPanel=document.getElementById('cartPanel'), cartBody=document.getElementById('cartBody'), cartClose=document.getElementById('cartClose'), cartCount=document.getElementById('cartCount');
  const addText=(role,text)=>{
    const d=document.createElement('div');
    d.className='msg';
    const prefix = role==='user'?'Tú: ':'Asesor: ';

    // Convertir URLs en links clickeables
    const urlRegex = /(https?:\/\/[^\s]+)/g;
    if(urlRegex.test(text)){
      const parts = text.split(urlRegex);
      d.innerHTML = prefix + parts.map(part => {
        if(/^https?:\/\//.test(part)){
          return `<a href="${part}" target="_blank" rel="noopener" style="color:#22d3ee;text-decoration:underline">${part}</a>`;
        }
        return part;
      }).join('');
    } else {
      d.textContent = prefix + text;
    }

    chat.appendChild(d);
    chat.scrollTop=chat.scrollHeight;
  }
  const addImage=(src,cap)=>{const c=document.createElement('div');c.className='card';c.innerHTML=`<img src="${src}" alt=""><div class="meta">${cap||''}</div>`;images.appendChild(c);images.scrollTop=images.scrollHeight;}
  const formatCLP=(n)=>'$'+Number(n||0).toLocaleString('es-CL');

  /* ====== RUT & EMAIL ====== */
  // Try to get from authenticated user first
  const authUser = window.roelAuth?.getUser();
  let clientRut = authUser?.rut || localStorage.getItem('client_rut') || null;
  let clientEmail = authUser?.email || localStorage.getItem('client_email') || null;

  const normRut = r => String(r||'').trim().toUpperCase().replace(/\./g,'');
  const isRut   = r => /^[0-9]{1,8}-[0-9K]$/.test(r||'');
  const isEmail = e => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e||'');

  /* ====== MODO SILENCIO ====== */
  let silentMode = false;

  function setSilentMode(silent) {
    silentMode = silent;
    if (silent) {
      log('mode', 'Silent mode ON - DESCONECTANDO');
      // Cancelar cualquier respuesta en progreso
      if (dc && dc.readyState === 'open') {
        dc.send(JSON.stringify({type: 'response.cancel'}));
      }
      // DESCONECTAR completamente para no consumir tokens
      setTimeout(() => {
        cleanup(); // Cierra la conexión WebRTC completamente
        btnIniciar.style.display = 'none'; // Ocultar botón Iniciar
        btnReactivar.style.display = 'inline-block'; // Mostrar botón Reactivar
        setConn('closed');
      }, 500);
    } else {
      log('mode', 'Silent mode OFF - RECONECTANDO');
      btnReactivar.style.display = 'none';
      btnIniciar.style.display = 'inline-block';
    }
  }

  // Botón de reactivación
  btnReactivar.onclick = async () => {
    silentMode = false;
    addText('assistant', '[Reactivando...]');
    await connect(); // Reconectar a OpenAI
    addText('assistant', '[Bot reactivado. ¿En qué puedo ayudarte?]');
  };

  async function ensureRut(){
    if (clientRut && isRut(clientRut)) return clientRut;
    const r = prompt('Para continuar con el carrito, ingresa tu RUT (formato 12345678-9 o 12345678-K):');
    if(!r) return null;
    const n=normRut(r);
    if(!isRut(n)){ alert('RUT inválido. Intenta nuevamente.'); return null; }
    clientRut=n; localStorage.setItem('client_rut', n);
    addText('assistant',`Gracias. Registraré tu RUT: ${n}.`);
    speak('Gracias. Registraré tu RUT.');
    return n;
  }

  async function ensureEmail(){
    if (clientEmail && isEmail(clientEmail)) return clientEmail;
    const e = prompt('Para generar el link de pago, ingresa tu email:');
    if(!e) return null;
    const trimmed = String(e).trim();
    if(!isEmail(trimmed)){ alert('Email inválido. Intenta nuevamente.'); return null; }
    clientEmail=trimmed; localStorage.setItem('client_email', trimmed);
    addText('assistant',`Gracias. Registraré tu email: ${trimmed}.`);
    speak('Gracias. Registraré tu email.');
    return trimmed;
  }

  // THREE
  const canvas=document.getElementById('canvas3d');
  const renderer=new THREE.WebGLRenderer({canvas,antialias:true,alpha:true});
  const scene=new THREE.Scene();

  const camera=new THREE.PerspectiveCamera(30,1,0.1,100);
  // Ajustar cámara para móviles (más cerca en pantallas pequeñas)
  const isMobileView = window.innerWidth <= 640;
  const isTabletView = window.innerWidth <= 920 && window.innerWidth > 640;
  const camZ = isMobileView ? 3.2 : (isTabletView ? 4.0 : 4.8);
  const camY = isMobileView ? 1.4 : (isTabletView ? 1.5 : 1.6);
  camera.position.set(0, camY, camZ);
  camera.lookAt(0, isMobileView ? 1.5 : (isTabletView ? 1.6 : 1.7), 0);

  scene.add(new THREE.HemisphereLight(0xbce7ff,0x0b1220,0.9));
  const dLight=new THREE.DirectionalLight(0xffffff,0.85); dLight.position.set(2,4,3); scene.add(dLight);

  const ground=new THREE.Mesh(new THREE.CircleGeometry(1.05,48), new THREE.MeshBasicMaterial({color:0x000000,transparent:true,opacity:0.28}));
  ground.rotation.x=-Math.PI/2; scene.add(ground);

  let avatar=null,mixer=null,jawTargets=[],blinkLT=[],blinkRT=[];
  const avatarBaseY=0.20;
  const clock=new THREE.Clock();

  const bones={}; const baseRot={};
  const NAMESETS={
    lArm:['leftarm','upperarm_l','mixamorigleftarm','shoulder_l'],
    rArm:['rightarm','upperarm_r','mixamorigrightarm','shoulder_r'],
    lFore:['leftforearm','lowerarm_l','mixamorigleftforearm','forearm_l'],
    rFore:['rightforearm','lowerarm_r','mixamorigrightforearm','forearm_r'],
    spine:['spine1','spine','mixamorigspine1','mixamorigspine'],
    neck:['neck','mixamorigneck']
  };
  const toKey=s=>s.toLowerCase();
  function matchName(n,keys){ n=toKey(n); return keys.some(k=>n.endsWith(k)||n.includes(k)); }
  function capture(b){ if(!b) return; baseRot[b.uuid]=b.rotation.clone(); }

  function fit(){
    const w=canvas.clientWidth|0,h=canvas.clientHeight|0,pr=Math.min(devicePixelRatio||1,2);
    if(renderer.getPixelRatio()!==pr) renderer.setPixelRatio(pr);
    if(canvas.width!==Math.floor(w*pr)||canvas.height!==Math.floor(h*pr)){
      renderer.setSize(w,h,false); renderer.setViewport(0,0,w,h);
      camera.aspect=(w/h)||1; camera.updateProjectionMatrix();
    }
  }

  function loadAvatar(){
    const loader=new GLTFLoader();
    loader.load(RPM_GLTF,(gltf)=>{
      avatar=gltf.scene;

      // Escalar avatar según tamaño de pantalla (más pequeño en móviles)
      const isMobile = window.innerWidth <= 640;
      const isTablet = window.innerWidth <= 920 && window.innerWidth > 640;
      const avatarScale = isMobile ? 0.55 : (isTablet ? 0.70 : 0.92);
      avatar.scale.setScalar(avatarScale);
      avatar.position.set(0,avatarBaseY,0);
      scene.add(avatar);
      ground.position.set(0,avatarBaseY-0.01,0);

      avatar.traverse(o=>{
        if(o.isMesh && o.morphTargetDictionary){
          const d=o.morphTargetDictionary;
          ['jawOpen','viseme_aa','vrc.v_aa','MouthOpen','mouthOpen'].forEach(k=>{ if(d[k]!=null) jawTargets.push({mesh:o,index:d[k]}); });
          ['eyeBlinkLeft','blink_left','Blink_L','leftEyeClosed'].forEach(k=>{ if(d[k]!=null) blinkLT.push({mesh:o,index:d[k]}); });
          ['eyeBlinkRight','blink_right','Blink_R','rightEyeClosed'].forEach(k=>{ if(d[k]!=null) blinkRT.push({mesh:o,index:d[k]}); });
        }
        if(o.isBone){
          const n=o.name||'';
          if(!bones.lArm && matchName(n,NAMESETS.lArm)) bones.lArm=o;
          if(!bones.rArm && matchName(n,NAMESETS.rArm)) bones.rArm=o;
          if(!bones.lFore&& matchName(n,NAMESETS.lFore)) bones.lFore=o;
          if(!bones.rFore&& matchName(n,NAMESETS.rFore)) bones.rFore=o;
          if(!bones.spine&& matchName(n,NAMESETS.spine)) bones.spine=o;
          if(!bones.neck && matchName(n,NAMESETS.neck )) bones.neck=o;
        }
        if(o.isMesh) o.frustumCulled=false;
      });

      ['lArm','rArm','lFore','rFore','spine','neck'].forEach(k=>capture(bones[k]));

      if(gltf.animations && gltf.animations.length){
        mixer=new THREE.AnimationMixer(avatar);
        const clip=THREE.AnimationClip.findByName(gltf.animations,'idle')||gltf.animations[0];
        mixer.clipAction(clip).play();
      }
      log('3d','bones',Object.keys(bones).filter(k=>bones[k]).join(', '));
    },undefined,(err)=>log('3d','avatar.error',String(err),'err'));
  }

  // audio -> analizador
  let audioCtx=null, analyser=null, dataArr=null, smLvl=0;
  function setupFromRemoteStream(stream){
    if(!audioCtx) audioCtx=new (window.AudioContext||window.webkitAudioContext)();

    // CRÍTICO para iOS: resume AudioContext después de interacción del usuario
    if(audioCtx.state === 'suspended') {
      audioCtx.resume().then(() => {
        log('audio','AudioContext resumed (iOS fix)');
      }).catch((err) => {
        log('audio','Failed to resume AudioContext: ' + err, null, 'err');
      });
    }

    const src=audioCtx.createMediaStreamSource(stream);
    analyser=audioCtx.createAnalyser(); analyser.fftSize=1024;
    dataArr=new Uint8Array(analyser.fftSize);
    const sink=audioCtx.createGain(); sink.gain.value=0;
    src.connect(analyser); analyser.connect(sink); sink.connect(audioCtx.destination);
    log('audio','analyser.fromRemote');
  }

  // parpadeo
  let blinkPhase=0, blinkT=0, nextBlink=performance.now()+1500+Math.random()*3500;
  function blinkUpdate(dt,now){
    let v=0;
    if(blinkPhase===0 && now>nextBlink){ blinkPhase=1; blinkT=0; }
    if(blinkPhase===1){ blinkT+=dt; v=Math.min(1,blinkT/0.09); if(blinkT>=0.09){ blinkPhase=2; blinkT=0; } }
    else if(blinkPhase===2){ blinkT+=dt; v=1-Math.min(1,blinkT/0.12); if(blinkT>=0.12){ blinkPhase=0; nextBlink=now+1200+Math.random()*3000; v=0; } }
    blinkLT.forEach(({mesh,index})=>mesh.morphTargetInfluences[index]=v);
    blinkRT.forEach(({mesh,index})=>mesh.morphTargetInfluences[index]=v);
  }

  // mirada con mouse
  const look={x:0,y:0};
  canvas.addEventListener('pointermove',e=>{
    const r=canvas.getBoundingClientRect();
    const nx=(e.clientX-r.left)/r.width*2-1;
    const ny=(e.clientY-r.top)/r.height*2-1;
    look.x=nx; look.y=ny;
  });

  function animate(){
    requestAnimationFrame(animate);
    fit();
    const dt=clock.getDelta(), now=performance.now();
    mixer&&mixer.update(dt);

    if(analyser){
      analyser.getByteTimeDomainData(dataArr);
      let sum=0; for(let i=0;i<dataArr.length;i++){ const v=(dataArr[i]-128)/128; sum+=v*v; }
      const rms=Math.sqrt(sum/dataArr.length);
      const lvl=Math.min(1,Math.max(0,(rms-0.02)*8));
      smLvl = smLvl*0.8 + lvl*0.2;
      jawTargets.forEach(({mesh,index})=>mesh.morphTargetInfluences[index]=smLvl);
    }

    blinkUpdate(dt,now);

    if(avatar){
      avatar.position.y = avatarBaseY + Math.sin(now*0.0006)*0.01;
      avatar.rotation.y = Math.sin(now*0.00025)*0.18;
      ground.position.y = avatarBaseY - 0.01;
    }

    const t=now*0.001;
    const sway = Math.sin(t*0.9)*0.18;
    const bend = Math.sin(t*1.2)*0.10;
    const spineTwist = Math.sin(t*0.5)*0.04;

    if(bones.lArm && baseRot[bones.lArm.uuid]){
      const b=baseRot[bones.lArm.uuid];
      bones.lArm.rotation.set(b.x, b.y, b.z + sway);
    }
    if(bones.rArm && baseRot[bones.rArm.uuid]){
      const b=baseRot[bones.rArm.uuid];
      bones.rArm.rotation.set(b.x, b.y, b.z - sway);
    }
    if(bones.lFore && baseRot[bones.lFore.uuid]){
      const b=baseRot[bones.lFore.uuid];
      bones.lFore.rotation.set(b.x + bend*0.5, b.y, b.z + bend*0.2);
    }
    if(bones.rFore&& baseRot[bones.rFore.uuid]){
      const b=baseRot[bones.rFore.uuid];
      bones.rFore.rotation.set(b.x + bend*0.5, b.y, b.z - bend*0.2);
    }
    if(bones.spine && baseRot[bones.spine.uuid]){
      const b=baseRot[bones.spine.uuid];
      bones.spine.rotation.set(b.x, b.y, b.z + spineTwist);
    }
    if(bones.neck && baseRot[bones.neck.uuid]){
      const b=baseRot[bones.neck.uuid];
      const yaw = look.x*0.25;
      const pitch = -look.y*0.15;
      bones.neck.rotation.set(b.x + pitch, b.y + yaw, b.z);
    }

    renderer.render(scene,camera);
  } animate();

  // Realtime
  remote.autoplay=true; remote.playsInline=true; remote.muted=false;
  function tryPlay(){
    // Resume AudioContext si está suspendido (iOS)
    if(audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume().catch((err) => {
        log('audio','tryPlay resume failed: ' + err, null, 'err');
      });
    }

    const p=remote.play();
    if(p && p.catch) {
      p.then(() => {
        log('audio','tryPlay success');
      }).catch((err) => {
        log('audio','tryPlay failed: ' + err.message, null, 'err');
        // Reintentar una vez después de 300ms
        setTimeout(() => {
          remote.play().catch(() => {});
        }, 300);
      });
    }
  }
  let pc=null,dc=null,micStream=null;
  let suppressCartText=false; // evita texto/voz del modelo en operaciones de carrito
  function setConn(state,latencyMs){const map={none:'#ef4444',connecting:'#fbbf24',connected:'#22d3ee',closed:'#ef4444'};dot.style.background=map[state]||'#ef4444';lat.textContent=latencyMs!=null?`${latencyMs} ms`:(state==='connected'?'conectado':'sin conexión');}
  async function getMic(){try{micStream=await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true,autoGainControl:true,channelCount:1},video:false});log('mic','ok');}catch(e){log('mic','denegado',String(e),'err');}return micStream;}

  async function connect(){
    if(pc){ log('rtc','already'); return; }
    setConn('connecting'); log('rtc','connect()');
    let eph='';
    try{
      const r=await fetch(SESSION_EP,{method:'POST'}); const tx=await r.text(); let js={}; try{js=JSON.parse(tx);}catch{}
      eph=js?.client_secret?.value||''; log('net','realtime_session',eph?'token ok':('payload:'+tx),eph?'log':'err');
      if(!eph){ addText('assistant','No pude abrir sesión'); setConn('none'); return; }
    }catch(e){ log('net','realtime_session.error',String(e),'err'); addText('assistant','No pude abrir sesión'); setConn('none'); return; }

    pc=new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
    pc.addTransceiver('audio',{direction:'recvonly'});
    pc.onconnectionstatechange=()=>{log('rtc','state',pc.connectionState); if(pc.connectionState==='connected') setConn('connected'); if(['disconnected','failed','closed'].includes(pc.connectionState)){ addText('assistant','Conexión finalizada'); cleanup(); }};
    pc.oniceconnectionstatechange=()=>log('rtc','ice',pc.iceConnectionState);
    pc.ontrack=(ev)=>{ remote.srcObject=ev.streams[0]; tryPlay(); setupFromRemoteStream(ev.streams[0]); log('rtc','ontrack audio'); };
    try{ const mic=await getMic(); if(mic) mic.getTracks().forEach(t=>pc.addTrack(t,mic)); }catch{}

    dc=pc.createDataChannel('oai-events');
    dc.onopen=()=>{ log('dc','open');
      dc.send(JSON.stringify({type:'session.update',session:{voice:'alloy',instructions:SYSTEM_PROMPT,tools:[
        {type:'function',name:'consultar_precio_producto_roelplant',parameters:{type:'object',properties:{nombre:{type:'string'}},required:['nombre']}},
        {type:'function',name:'consultar_disponibles_roelplant',parameters:{type:'object',properties:{}}},
        {type:'function',name:'consultar_disponibles_por_tipo_roelplant',parameters:{type:'object',properties:{tipo:{type:'string'}},required:['tipo']}},
        {type:'function',name:'carrito_operar',parameters:{type:'object',properties:{
          action:{type:'string',enum:['add','add_by_name','update_qty','remove','clear','summary','checkout']},
          code:{type:'string'},name:{type:'string'},qty:{type:'number'},
          tier:{type:'string'},unit:{type:'string'},price:{type:'number'},image:{type:'string'}
        },required:['action'],additionalProperties:true}},
        {type:'function',name:'activar_modo_silencio',description:'Activa el modo silencio cuando el usuario dice STOP, Basta, Silencio, etc',parameters:{type:'object',properties:{},additionalProperties:false}}
      ]}}));
      addText('assistant','Conectado. Escribe o usa los atajos.');
    };

    const fnAcc={};
    dc.onmessage=async(ev)=>{
      let msg; try{msg=JSON.parse(ev.data);}catch{return;}
      if(msg.type!=='response.audio.delta') log('rx',msg.type,(msg.name||msg.role||''));
      if(msg.type==='error'){ log('rx','error',JSON.stringify(msg),'err'); return; }

      if(msg.type==='response.function_call_arguments.delta'||msg.type==='function_call.arguments.delta'){
        const id=msg.call_id||msg.id; if(!fnAcc[id]) fnAcc[id]={name:'',args:''};
        if(msg.name) fnAcc[id].name=msg.name; if(msg.delta) fnAcc[id].args+=msg.delta; return;
      }
      if(msg.type==='response.function_call_arguments.done'||msg.type==='function_call.arguments.done'){
        const id=msg.call_id||msg.id; const name=msg.name||fnAcc[id]?.name||''; let args={}; try{args=JSON.parse(fnAcc[id]?.args||'{}');}catch{}
        log('fn','args.done',name+' '+JSON.stringify(args));
        if(name==='consultar_precio_producto_roelplant'){ const out=await postJSON(TOOL_EP,{nombre:String(args.nombre||'').trim()}); handleProducto(out); }
        else if(name==='consultar_disponibles_roelplant'){ const lst=await getJSON(DISP_EP); handleLista(lst,'Disponibles'); }
        else if(name==='consultar_disponibles_por_tipo_roelplant'){ const lst=await postJSON(DISP_EP,{tipo:String(args.tipo||'').trim()}); handleLista(lst,`Disponibles · ${args.tipo||''}`); }
        else if(name==='carrito_operar'){
          suppressCartText=true; // bloquea texto del modelo para esta respuesta
          if(dc&&dc.readyState==='open'){ dc.send(JSON.stringify({type:'response.cancel'})); }
          await handleCarrito(args);
        }
        else if(name==='activar_modo_silencio'){
          // Activar modo silencio inmediatamente
          if(dc&&dc.readyState==='open'){
            dc.send(JSON.stringify({type:'response.cancel'})); // Cancelar cualquier respuesta en progreso
          }
          addText('assistant','[Modo silencio. Click en "Reactivar Bot" para continuar]');
          setSilentMode(true); // Esto desconectará en 500ms
          // Responder a la function call SIN generar nueva respuesta
          if(dc&&dc.readyState==='open'){
            dc.send(JSON.stringify({type:'conversation.item.create',item:{type:'function_call_output',call_id:id,output:JSON.stringify({status:'ok'})}}));
          }
        }
        delete fnAcc[id]; return;
      }
      if(msg.type==='response.completed' && msg.output && msg.output.text){
        if(suppressCartText){ suppressCartText=false; return; } // no mezclar con nuestro resumen real
        addText('assistant',msg.output.text);
        (msg.output.text.match(/https?:\/\/\S+\.(?:jpg|jpeg|png|webp)/gi)||[]).forEach(u=>addImage(u,'referencia'));
      }
    };

    try{
      const offer=await pc.createOffer(); await pc.setLocalDescription(offer);
      const sdp=await fetch('https://api.openai.com/v1/realtime?model=gpt-realtime',{method:'POST',headers:{'Authorization':'Bearer '+eph,'Content-Type':'application/sdp'},body:offer.sdp});
      const ans=await sdp.text(); await pc.setRemoteDescription({type:'answer',sdp:ans});
      log('rtc','SDP ok');
    }catch(e){ log('rtc','SDP error',String(e),'err'); addText('assistant','No pude establecer audio'); }
  }
  function cleanup(){ try{dc&&dc.close();}catch{} try{pc&&pc.close();}catch{} pc=null; dc=null; setConn('none'); log('rtc','cleanup'); }

  // HTTP
  async function getJSON(url){const t0=performance.now();log('net','GET '+url);try{const r=await fetch(url);const tx=await r.text();let j={};try{j=JSON.parse(tx);}catch{}log('net',url+' '+r.status+' '+Math.round(performance.now()-t0)+'ms',tx.slice(0,180)+'…');return j;}catch(e){log('net',url+' error',String(e),'err');return {status:'error'};}}
  async function postJSON(url,body){const t0=performance.now();log('net','POST '+url,JSON.stringify(body));try{const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});const tx=await r.text();let j={};try{j=JSON.parse(tx);}catch{}log('net',url+' '+r.status+' '+Math.round(performance.now()-t0)+'ms',tx.slice(0,180)+'…');return j;}catch(e){log('net',url+' error',String(e),'err');return {status:'error'};}}

  // Cart helpers/UI
  function renderCart(c){
    const items=(c&&Array.isArray(c.items))?c.items:[];
    cartBody.innerHTML = items.length? items.map(it=>{
      const unitPrice = Number(it.price||0);
      const qty = Number(it.qty||0);
      const lineSub = ('subtotal'in it && it.subtotal!=null)? Number(it.subtotal) : unitPrice*qty;
      return `
      <div class="cart-item">
        <img src="${it.image||'https://placehold.co/96x96?text=%F0%9F%8C%B1'}" alt="">
        <div>
          <div><strong>${it.name||it.code||'Producto'}</strong></div>
          <div class="meta">${qty} ${it.unit||'unid.'} · ${it.tier||'detalle'} · ${formatCLP(unitPrice)} c/u</div>
        </div>
        <div class="meta">${formatCLP(lineSub)}</div>
      </div>`;
    }).join('') : `<div class="meta" style="opacity:.75">Carrito vacío.</div>`;

    const total = items.reduce((s,i)=>{
      const p=Number(i.price||0), q=Number(i.qty||0);
      return s + (('subtotal'in i && i.subtotal!=null)? Number(i.subtotal): p*q);
    },0);
    const units = items.reduce((s,i)=>s+Number(i.qty||0),0);
    const lines = items.length;

    cartBody.innerHTML += `<div class="cart-total"><span>Total</span><strong>${formatCLP(total)}</strong></div>`;
    cartCount.textContent = lines?`(${lines} ${lines===1?'línea':'líneas'} · ${units} unid.)`:''; 
  }
  function showCart(open){ cartPanel.classList[open?'remove':'add']('hidden'); }
  async function cartCall(payload){
    // Adjuntar datos del usuario autenticado
    const currentUser = window.roelAuth?.getUser();
    if (currentUser?.id && !payload.user_id) payload.user_id = currentUser.id;
    if (currentUser?.username && !payload.username) payload.username = currentUser.username;
    if (clientRut && !payload.rut) payload.rut = clientRut; // <-- adjunta RUT si lo tenemos

    const res=await postJSON(CART_EP,payload);
    if(res?.cart) renderCart(res.cart);
    return res;
  }

  // Payment polling
  let paymentPolling = null;

  async function checkPaymentStatus(orderNumber) {
    try {
      const response = await fetch(`${FLOW_STATUS_EP}?order_number=${orderNumber}`);
      const data = await response.json();
      return data;
    } catch (error) {
      log('payment','check_error',String(error),'err');
      return { status: 'error', message: error.message };
    }
  }

  function startPaymentPolling(orderNumber, amount) {
    log('payment','start_polling',orderNumber);

    // Detener polling anterior si existe
    if (paymentPolling) {
      clearInterval(paymentPolling);
    }

    let attempts = 0;
    const maxAttempts = 60; // 60 intentos * 5 segundos = 5 minutos

    paymentPolling = setInterval(async () => {
      attempts++;

      const result = await checkPaymentStatus(orderNumber);
      log('payment','poll_result',`${orderNumber} - ${result.status} (attempt ${attempts}/${maxAttempts})`);

      if (result.status === 'success') {
        // Pago exitoso!
        clearInterval(paymentPolling);
        paymentPolling = null;

        // Limpiar el carrito
        try {
          await cartCall({action:'clear'});
          log('payment','cart_cleared',orderNumber);
        } catch(e) {
          log('payment','cart_clear_error',String(e),'err');
        }

        // Reactivar bot si está en modo silencio
        if (silentMode) {
          silentMode = false;
          await connect();
        }

        // Anunciar pago exitoso
        const successMsg = `¡Excelente! Tu pago por ${formatCLP(amount)} fue exitoso. Recibirás un email con los detalles de tu compra. Orden número ${orderNumber}.`;
        addText('assistant', successMsg);
        speak(successMsg);

        log('payment','success',orderNumber);

      } else if (result.status === 'rejected' || result.status === 'cancelled') {
        // Pago rechazado o cancelado
        clearInterval(paymentPolling);
        paymentPolling = null;

        if (silentMode) {
          silentMode = false;
          await connect();
        }

        const failMsg = result.status === 'rejected' ?
          'Tu pago fue rechazado. Por favor intenta nuevamente.' :
          'El pago fue cancelado.';

        addText('assistant', failMsg);
        speak(failMsg);

        log('payment','failed',`${orderNumber} - ${result.status}`);

      } else if (attempts >= maxAttempts) {
        // Timeout
        clearInterval(paymentPolling);
        paymentPolling = null;

        log('payment','timeout',orderNumber);
      }

    }, 5000); // Verificar cada 5 segundos
  }

  // TTS
  function speak(text){
    if(!dc||dc.readyState!=='open') return;
    log('send','response.create',text.slice(0,120)+'…');
    dc.send(JSON.stringify({type:'response.create',response:{modalities:['audio','text'],instructions:text}}));
  }

  // Producto & listas
  function handleProducto(out){
    if(!out||out.status!=='ok'){ speak('No encontré el producto solicitado.'); addText('assistant','No encontré el producto solicitado.'); return; }
    const qty=(out.stock??out.disponible_para_reservar);
    const frase=`Precio de ${out.variedad||'el producto'}: mayorista ${formatCLP(out.precio)}${out.unidad?' '+out.unidad:''}. Detalle ${formatCLP(out.precio_detalle)}. Disponible ${qty??'no informado'} ${out.unidad||'plantines'}.`;
    addText('assistant',frase);
    if(out.imagen) addImage(out.imagen,out.variedad||'');
    speak(frase);
  }
  function handleLista(lst,label){
    if(!lst||!Array.isArray(lst.items)){ addText('assistant','No pude obtener la lista de disponibles.'); return; }
    const top=lst.items.slice(0,6);
    const res=top.map(x=>`${x.variedad}: ${(x.stock??x.disponible_para_reservar)??'-'} ${x.unidad||'plantines'}`).join(' · ');
    addText('assistant',`${label}: ${lst.count||top.length} variedades. Ejemplos: ${res}.`);
    top.forEach(x=>{ if(x.imagen) addImage(x.imagen,`${x.variedad} · ${x.tipo_planta||''}`); });
    speak(`Hay ${lst.count||top.length} variedades. ${res}.`);
  }

  // tier
  const mapTier=(t)=>{ t=String(t||'').toLowerCase(); if(/mayorista/.test(t)||/wholesale/.test(t)) return 'wholesale'; return 'retail'; };

  // Cart speech line
  function composeCartLine(c){
    const items=(c&&Array.isArray(c.items))?c.items:[]; if(!items.length) return 'Carrito vacío.';
    const first = items.slice(0,4).map(it => `${it.qty}× ${it.name||it.code}`).join(' · ');
    const tot = items.reduce((s,i)=>{ const p=Number(i.price||0), q=Number(i.qty||0); return s + (('subtotal'in i && i.subtotal!=null)? Number(i.subtotal): p*q); },0);
    const units = items.reduce((s,i)=>s+Number(i.qty||0),0);
    return `Tu carrito: ${first}${items.length>4?` … (${items.length} líneas)`:''}. Total ${formatCLP(tot)} · ${units} unid.`;
  }

  // Cart handler
  async function handleCarrito(args){
    const a=(args.action||'').toLowerCase();

    // Checkout: validar perfil, pedir datos y generar link de Flow
    if(a==='checkout'){
      // 0. Verificar que el perfil esté completo
      if(window.clientProfile) {
        const profileComplete = await window.clientProfile.checkProfileBeforeCheckout();
        if(!profileComplete) {
          // El modal se abrió automáticamente, el agente debe hablar
          const msg = 'Antes de proceder con el pago necesitamos tus datos completos, especialmente tu dirección de envío, para poder realizar la entrega.';
          addText('assistant', msg);
          speak(msg);

          // Guardar checkout pendiente para ejecutar después
          console.log('[CHECKOUT] Setting window.triggerCheckout callback');
          window.triggerCheckout = async () => {
            console.log('[CHECKOUT] triggerCheckout callback executed!');
            await handleCarrito({action:'checkout'});
          };
          console.log('[CHECKOUT] window.triggerCheckout is now set:', !!window.triggerCheckout);
          return;
        }
      }

      // 1. Pedir RUT (si no está en el perfil)
      const clientData = await window.clientProfile?.getClientData();
      const rut = clientData?.rut || await ensureRut();
      if(!rut){
        addText('assistant','Necesito tu RUT para proceder con la compra.');
        speak('Necesito tu RUT para proceder con la compra.');
        return;
      }

      // 2. Pedir email (si no está en el perfil)
      const email = clientData?.mail || await ensureEmail();
      if(!email){
        addText('assistant','Necesito tu email para enviarte el link de pago.');
        speak('Necesito tu email para enviarte el link de pago.');
        return;
      }

      // 3. Generar link de pago con Flow
      addText('assistant','Generando tu link de pago...');
      speak('Generando tu link de pago.');

      try {
        // Obtener user_id del usuario autenticado
        const currentUser = window.roelAuth?.getUser();
        const userId = currentUser?.id || null;

        const flowResp = await postJSON(FLOW_EP, {email, rut, user_id: userId});

        if(flowResp?.status==='ok' && flowResp?.payment_url){
          const msg = `Link de pago generado. Monto: ${formatCLP(flowResp.amount)}. Orden: ${flowResp.order_number}. Haz click aquí: ${flowResp.payment_url}`;
          addText('assistant', msg);

          // Cancelar respuesta del modelo antes de hablar
          if(dc && dc.readyState === 'open') {
            dc.send(JSON.stringify({type: 'response.cancel'}));
          }

          speak(`Link de pago generado por ${formatCLP(flowResp.amount)}. Revisa el chat para ver el enlace. Estaré verificando tu pago en segundo plano.`);
          showCart(true);

          // Iniciar polling para verificar el pago
          startPaymentPolling(flowResp.order_number, flowResp.amount);

          // Activar modo silencio después del checkout (esperar a que termine de hablar)
          setTimeout(() => {
            setSilentMode(true);
            addText('assistant', '[Modo silencio. Verificando tu pago en segundo plano...]');
          }, 8000); // Esperar 8 segundos para que termine de hablar
        } else {
          throw new Error(flowResp?.message || 'No se pudo generar el link de pago');
        }
      } catch(e) {
        const errMsg = 'No pude generar el link de pago. ' + (e.message || 'Error desconocido');
        addText('assistant', errMsg);

        // Cancelar respuesta del modelo antes de hablar
        if(dc && dc.readyState === 'open') {
          dc.send(JSON.stringify({type: 'response.cancel'}));
        }

        speak('No pude generar el link de pago. Intenta nuevamente.');
      }

      return;
    }

    let payload;
    if(a==='add_by_name'){
      let name=String(args.name||'').trim();
      let qty=Number(args.qty||1);
      let tier=mapTier(args.tier||'retail');
      let unit=String(args.unit||'').trim();
      let price=Number(args.price||0);
      let image=String(args.image||'').trim();
      let code=String(args.code||'').trim();

      if((!price || price<=0) && name){
        const p=await postJSON(TOOL_EP,{nombre:name});
        if(p && p.status==='ok'){
          const pm=parseInt(p.precio,10)||0, pd=parseInt(p.precio_detalle,10)||0;
          price=(tier==='wholesale')?pm:pd;
          unit=unit||p.unidad||p.unidad_medida||'plantines';
          image=image||p.imagen||'';
          code=code||p.referencia||'';
          name=p.variedad||name;
        }
      }
      payload={action:'add_by_name',name,qty,tier,unit,price,image,code};
    } else if(a==='add'){
      payload={action:'add',code:args.code,name:args.name,qty:args.qty,tier:mapTier(args.tier||'retail'),unit:args.unit,price:args.price,image:args.image};
    } else if(a==='update_qty'){
      payload={action:'update_qty',code:args.code,qty:args.qty};
    } else if(a==='remove'){
      payload={action:'remove',code:args.code,name:args.name};
    } else if(a==='clear'){
      payload={action:'clear'};
    } else {
      payload={action:'summary'};
    }

    // 1) ejecutar acción
    let res=await cartCall(payload);

    // 2) consolidar estado real: summary siempre que no sea summary
    if(a!=='summary'){
      const res2=await cartCall({action:'summary'});
      if(res2?.status==='ok') res=res2;
    }

    // 3) responder con datos del backend
    if(res?.status==='ok'){
      const c=res.cart||{};
      const line=composeCartLine(c);
      addText('assistant',line);
      if(dc&&dc.readyState==='open'){ dc.send(JSON.stringify({type:'response.cancel'})); }
      speak(line);
      showCart(true);
    }else{
      addText('assistant','No pude actualizar/leer el carrito.');
      if(dc&&dc.readyState==='open'){ dc.send(JSON.stringify({type:'response.cancel'})); }
      speak('No pude actualizar o leer el carrito.');
    }
  }

  // NLP + envío (texto)
  const wantsPrice=(t)=>/\b(precio|valor|cu[aá]nto|costo|stock\s+de|precio\s+de)\b/i.test(t);
  const wantsDispon=(t)=>/\b(disponible|disponibles|en\s+stock|hay\s+disponible|lista|cat[aá]logo)\b/i.test(t);
  const wantsCartSummary=(t)=>/\b(?:ver|mostrar|resumen|estado|mi|el|actualiza|actualizar|actualizado)\s+carrito\b/i.test(t);
  const extractTipo=(t)=>{ const m=t.match(/\b(plantas? de interior|plantas? de exterior|cubre\s*suelo[s]?|árboles|arboles|trepadoras|suculentas|helechos|nativas|introducidas)\b/i); return m?m[0]:null; };

  function sendText(q){
    const qn=String(q||'').trim();
    if(!dc||dc.readyState!=='open'){ addText('assistant','Conéctate primero con Iniciar.'); return; }
    addText('user',qn);

    if(wantsCartSummary(qn)){ handleCarrito({action:'summary'}); return; }

    let hint='';
    if(wantsDispon(qn)){
      const tipo=extractTipo(qn);
      hint=tipo?` (usa consultar_disponibles_por_tipo_roelplant con tipo="${tipo}")`:' (usa consultar_disponibles_roelplant)';
    } else if(wantsPrice(qn)){
      hint=' (usa consultar_precio_producto_roelplant si aplica)';
    }

    log('send','input_text',qn+hint);
    dc.send(JSON.stringify({type:'input_text',text:qn+hint}));

    if(!wantsPrice(qn) && !wantsDispon(qn)){
      dc.send(JSON.stringify({type:'response.create',response:{modalities:['audio','text']}}));
    }
  }

  // start
  function start(){
    overlay.style.display='none';
    loadAvatar();

    // CRÍTICO para iOS: crear y resumir AudioContext con gesto del usuario
    if(!audioCtx) {
      audioCtx = new (window.AudioContext||window.webkitAudioContext)();
      log('audio','AudioContext created on user gesture');
    }
    if(audioCtx.state === 'suspended') {
      audioCtx.resume().then(() => {
        log('audio','AudioContext resumed on start (iOS fix)');
      }).catch((err) => {
        log('audio','Failed to resume on start: ' + err, null, 'err');
      });
    }

    connect().then(()=>{
      // Intentar reproducir audio múltiples veces (fix para iOS)
      const attemptPlay = () => {
        const playPromise = remote.play();
        if(playPromise !== undefined) {
          playPromise.then(() => {
            log('audio','remote.play() success');
          }).catch((err) => {
            log('audio','remote.play() failed: ' + err.message + ', retrying...', null, 'err');
            // Reintentar después de 500ms
            setTimeout(attemptPlay, 500);
          });
        }
      };
      attemptPlay();
    });
  }
  btnSend.onclick=()=>{ const v=txt.value.trim(); if(!v) return; txt.value=''; sendText(v); };
  txt.addEventListener('keydown',e=>{ if(e.key==='Enter'&&!e.shiftKey){ e.preventDefault(); btnSend.click(); } });
  document.querySelectorAll('.chip[data-q]').forEach(el=>el.addEventListener('click',()=>sendText(el.getAttribute('data-q'))));
  btnIniciar.onclick=start; startBig.onclick=start;

  // Abrir carrito: toggle + refresco desde backend al abrir
  btnCart.onclick=()=>{ const open = cartPanel.classList.contains('hidden'); showCart(open); if(open) cartCall({action:'summary'}); };
  cartClose.onclick=()=>showCart(false);

  // cargar estado del carrito al inicio (sin pedir RUT aún)
  cartCall({action:'summary'}).then((res)=>{
    log('net','POST ./cart/cart_api.php','{"action":"summary"}');
    // Si hay productos en el carrito, mostrarlo automáticamente
    if(res?.cart?.items && Array.isArray(res.cart.items) && res.cart.items.length > 0){
      showCart(true);
    }
  });
  log('module','ready');
})();
</script>
</body>
</html>
