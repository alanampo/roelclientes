// agente-widget.js - Standalone widget que usa la autenticación del catálogo
(async function initAgenteWidget() {
  'use strict';

  console.log('[AGENTE-WIDGET] Iniciando carga del módulo...');
  const log = window.rpLog || console.log;

  // ========== AUTHENTICATION (usa sesión del catálogo) ==========
  const btnLogout = document.getElementById('btnLogout');
  const btnProfile = document.getElementById('btnProfile');
  const userDisplay = document.getElementById('userDisplay');

  function getUser() {
    return window.PHP_SESSION_USER && window.PHP_SESSION_USER.id ? window.PHP_SESSION_USER : null;
  }

  function updateUserDisplay() {
    const user = getUser();
    if (user && userDisplay) {
      userDisplay.textContent = `${user.username || 'Usuario'}`;
      if (btnProfile) btnProfile.style.display = 'inline-block';
      if (btnLogout) btnLogout.style.display = 'inline-block';
    } else {
      if (userDisplay) userDisplay.textContent = '';
      if (btnProfile) btnProfile.style.display = 'none';
      if (btnLogout) btnLogout.style.display = 'none';
    }
  }

  function logout() {
    if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
      window.location.href = '/endsession.php';
    }
  }

  if (btnLogout) btnLogout.addEventListener('click', logout);
  updateUserDisplay();

  window.roelAuth = {
    getAccessToken: () => null,
    getUser,
    isAuthenticated: () => !!getUser(),
    refreshToken: () => Promise.resolve(false)
  };

  // ========== MÓDULOS THREE.JS ==========
  console.log('[AGENTE-WIDGET] Cargando módulos Three.js...');
  let THREE, GLTFLoader;
  try {
    THREE = await import('three');
    console.log('[AGENTE-WIDGET] Three.js cargado correctamente');
    log('loader', 'three.module.js ok');
  } catch (e) {
    console.error('[AGENTE-WIDGET] Error cargando Three.js:', e);
    log('loader', 'three.import.error', String(e), 'err');
    return;
  }

  try {
    // Usar ruta absoluta desde la raíz del sitio para que los imports relativos funcionen
    const gltfLoaderPath = '/vendor/examples/js/loaders/package/examples/jsm/loaders/GLTFLoader.js?v=11';
    console.log('[AGENTE-WIDGET] Cargando GLTFLoader desde:', gltfLoaderPath);
    const module = await import(gltfLoaderPath);
    GLTFLoader = module.GLTFLoader;
    console.log('[AGENTE-WIDGET] GLTFLoader cargado correctamente');
    log('loader', 'GLTFLoader.js ok');
  } catch (e) {
    console.error('[AGENTE-WIDGET] Error cargando GLTFLoader:', e);
    log('loader', 'gltfloader.import.error', String(e), 'err');
    return;
  }

  // ========== CONFIG ==========
  const RPM_GLTF = 'https://models.readyplayer.me/68bf40a4c11cea25ec61c5ef.glb';
  const SESSION_EP = '/server/realtime_session.php';
  const TOOL_EP = '/server/tool_producto.php';
  const DISP_EP = '/server/disponibles_proxy.php';

  // Usar API del catálogo para el carrito (integración completa)
  const CATALOG_CART_ADD = '/catalogo_detalle/api/cart/add.php';
  const CATALOG_CART_GET = '/catalogo_detalle/api/cart/get.php';
  const CATALOG_CART_REMOVE = '/catalogo_detalle/api/cart/remove.php';

  const SYSTEM_PROMPT = `You are a sales assistant for Roelplant (Chilean plant nursery).

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
1. When customer asks to ADD to cart ("agregar al carrito", "add to cart", "aggiungi al carrello", "додати до кошика"):
   - CRITICAL: If customer specifies a product TYPE (semilla, esqueje, etc.), you MUST pass the tipo parameter to carrito_operar
   - Include: name (product name), qty (quantity, default 1), tier ("retail" or "wholesale"), tipo (if specified)
   - Example flows:
     * Customer: "add monstera tipo semilla" → carrito_operar({action:"add_by_name", name:"Monstera Deliciosa", qty:1, tier:"retail", tipo:"SEMILLA"})
     * Customer: "add 2 plantines de monstera" → carrito_operar({action:"add_by_name", name:"Monstera Deliciosa", qty:2, tier:"retail", tipo:"SEMILLA"})
     * Customer: "add monstera" (no type specified) → carrito_operar({action:"add_by_name", name:"Monstera Deliciosa", qty:1, tier:"retail"})
   - If carrito_operar returns multiple options, it will show selector automatically
   - The system automatically handles if product already exists in cart - it will increment the quantity

2. When customer asks to CHANGE quantity of existing item:
   - Use action="update_qty"
   - Include: name or code, qty (new quantity, not increment)
   - Example: {action:"update_qty", name:"Monstera Deliciosa", qty:5}

3. To view cart: use action="summary"

4. To remove item: use action="remove" with name or code

5. To clear entire cart: use action="clear"

6. To CHECKOUT / PROCEED TO PAYMENT:
   - When customer wants to pay, go to checkout, finalize, or complete their purchase
   - ALWAYS use action="checkout"
   - Spanish triggers: "quiero pagar", "pagar", "checkout", "finalizar compra", "completar compra", "ir al pago", "proceder al pago", "llevar al checkout", "ir a pagar", etc.
   - English triggers: "I want to pay", "pay", "checkout", "complete purchase", "go to payment", "proceed to payment", "finalize order", etc.
   - Italian triggers: "voglio pagare", "pagare", "checkout", "completare l'acquisto", etc.
   - The system will validate cart has items and redirect to checkout page

7. IMPORTANT: After any add/update/remove, the function auto-calls summary. DO NOT call it again yourself.

8. Never invent totals or quantities. The tool calculates everything.

9. If customer says "add it" without prior context, ask which product they want to add.

QUERIES AND TERMINOLOGY MAPPING:
- IMPORTANT: Choose the right tool based on customer intent:
  * INFORMATIONAL queries ("tienes?", "cuánto cuesta?", "hay stock de?", "show me", "tell me about")
    → Use consultar_precio_producto_roelplant
    → This will ALWAYS show visual product card(s), even for 1 result
    → Customer can then select from the visual options

  * PURCHASE intent ("agregar al carrito", "add to cart", "quiero comprar", "dame", "I want")
    → Use carrito_operar directly with action="add_by_name"
    → This will add directly WITHOUT showing visual options (unless multiple matches, then shows selector)

- For consultar_precio_producto_roelplant:
  * nombre: product name (required)
  * tipo: product type filter (optional) - use when customer specifies type

- Product types in database (ALWAYS USE EXACT UPPERCASE):
  * SEMILLA - seeds/seedlings
  * ESQUEJE - cuttings
  * PLANTA TERMINADA - finished plants
  * PACK - product packs

- KEYWORD MAPPING (customer terms → search parameter):
  IMPORTANT: Always pass tipo parameter in UPPERCASE exactly as shown below:
  * "plantín" / "plantine" / "plantines" → IMPORTANT: This is AMBIGUOUS
    - First check if it's in the product NAME (e.g., "PACK DE 10 PLANTINES MIX CLASICO")
    - If NOT in name, then map to tipos: tipo="SEMILLA" or tipo="ESQUEJE" (try both)
    - Example: "quiero un plantín de monstera" → search nombre="monstera", tipo="SEMILLA" first, if nothing then tipo="ESQUEJE"
  * "semilla" / "seed" / "de tipo semilla" → tipo="SEMILLA" (exact uppercase)
  * "esqueje" / "cutting" / "de tipo esqueje" → tipo="ESQUEJE" (exact uppercase)
  * "planta terminada" / "finished plant" / "de tipo planta terminada" → tipo="PLANTA TERMINADA" (exact uppercase)
  * "pack" / "de tipo pack" → tipo="PACK" (exact uppercase)

- EXAMPLES OF WHEN TO USE EACH TOOL:

  INFORMATIONAL (use consultar_precio_producto_roelplant):
  * "tienes dolar variegado?" → consultar_precio_producto_roelplant(nombre="dolar variegado")
  * "cuánto cuesta monstera en semilla?" → consultar_precio_producto_roelplant(nombre="monstera", tipo="SEMILLA")
  * "hay stock de ficus?" → consultar_precio_producto_roelplant(nombre="ficus")
  * "show me the monstera options" → consultar_precio_producto_roelplant(nombre="monstera")
  * "cuánto sale?" (after previous product mentioned) → consultar_precio_producto_roelplant(nombre="[previous product]")

  PURCHASE INTENT (use carrito_operar):
  * "agregar monstera en semilla al carrito" → carrito_operar(action="add_by_name", name="monstera", tipo="SEMILLA", qty=1, tier="retail")
  * "dame 2 de ficus" → carrito_operar(action="add_by_name", name="ficus", qty=2, tier="retail")
  * "quiero comprar dolar variegado" → carrito_operar(action="add_by_name", name="dolar variegado", qty=1, tier="retail")
  * "add it to cart" (after showing product) → carrito_operar(action="add_by_name", name="[shown product]", qty=1, tier="retail")
  * "agrégalo" (after showing product) → carrito_operar(action="add_by_name", name="[shown product]", qty=1, tier="retail")

- HANDLING NO RESULTS (status='not_found'):
  * If a specific search with tipo filter returns 'not_found':
    - Automatically try again WITHOUT the tipo filter (broader search)
    - Example: "monstera en semilla" finds nothing → try just "monstera"
    - Tell customer: "No encontré monstera en semilla, pero encontré estas opciones de monstera:"
  * If even the broad search returns nothing:
    - Tell customer the product is not available in stock
    - Suggest checking consultar_disponibles_roelplant to see what's in stock

- To list available products: use consultar_disponibles_roelplant
- To filter by type: use consultar_disponibles_por_tipo_roelplant

PRODUCT SEARCH RESULTS:
- When consultar_precio_producto_roelplant is called, a visual card ALWAYS appears (even for 1 result)
- You will receive product data with these fields:
  * nombre: product name
  * tipo: product type (SEMILLA, PLANTA TERMINADA, etc.)
  * atributos: attributes like bandeja, maceta, etc.
  * stock: available quantity
  * precio_final_con_iva: FINAL PRICE WITH IVA INCLUDED - USE THIS PRICE when speaking
  * unidad: unit (plantines, etc.)

- If status='found' (1 result):
  * Describe the product briefly: name, type, key attributes, stock, price
  * Example: "Sí, tengo MONSTERA DELICIOSA tipo SEMILLA, bandeja de 72 alveolos, 2026 unidades disponibles, precio 1785 pesos. Puedes verlo en la tarjeta y agregarlo al carrito."
  * Customer can click the visual card to add to cart

- If status='multiple' (2+ results):
  * List each option with number
  * Example: "Encontré 2 opciones. Opción 1: MONSTERA DELICIOSA tipo SEMILLA, 2026 unidades, 1785 pesos. Opción 2: MONSTERA DELICIOSA tipo PLANTA TERMINADA, 95 unidades, 5950 pesos. Puedes seleccionar desde las tarjetas o decirme cuál quieres."

- DO NOT add to cart automatically - customer must select from visual cards or say which one they want
- Customer can select by clicking card button or by voice ("la primera", "la semilla", etc.)

SELECTING FROM OPTIONS:
- If customer wants ONE option: use seleccionar_producto_de_opciones
  * index: if they say "first", "second", "la primera", etc. (pass 1, 2, 3...)
  * description: if they mention "semilla", "planta terminada", "maceta", "bandeja", etc.
  * qty: the quantity they want

- If customer wants MULTIPLE options at once: use agregar_multiples_del_selector
  * Example: "1 de la primera y 2 de la segunda" → agregar_multiples_del_selector with selections: [{index: 1, qty: 1}, {index: 2, qty: 2}]
  * Example: "agrega la semilla y la planta terminada" → agregar_multiples_del_selector with selections: [{description: "semilla", qty: 1}, {description: "planta terminada", qty: 1}]
  * This tool adds all selections to cart in one operation

If off-topic (not about plants): respond "I'm Roelplant's assistant. I only handle plant queries, prices, stock, orders and shipping." (or equivalent in customer's language)`;

  // ========== UI ELEMENTS ==========
  const dot = document.getElementById('dot');
  const lat = document.getElementById('lat');
  const chat = document.getElementById('chat');
  const txt = document.getElementById('txt');
  const btnSend = document.getElementById('send');
  const btnIniciar = document.getElementById('btnIniciar');
  const btnReactivar = document.getElementById('btnReactivar');
  const remote = document.getElementById('remote');
  const overlay = document.getElementById('overlay');
  const startBig = document.getElementById('startBig');
  const cartFloatPanel = document.getElementById('cartFloatPanel');
  const cartFloatBody = document.getElementById('cartFloatBody');
  const cartFloatClose = document.getElementById('cartFloatClose');
  const cartFloatCount = document.getElementById('cartFloatCount');

  const addText = (role, text) => {
    const d = document.createElement('div');
    d.className = 'agente-msg';
    const prefix = role === 'user' ? 'Tú: ' : 'Asesor: ';

    const urlRegex = /(https?:\/\/[^\s]+)/g;
    if (urlRegex.test(text)) {
      const parts = text.split(urlRegex);
      d.innerHTML = prefix + parts.map(part => {
        if (/^https?:\/\//.test(part)) {
          return `<a href="${part}" target="_blank" rel="noopener" style="color:#22d3ee;text-decoration:underline">${part}</a>`;
        }
        return part;
      }).join('');
    } else {
      d.textContent = prefix + text;
    }

    chat.appendChild(d);
    chat.scrollTop = chat.scrollHeight;
  };

  const formatCLP = (n) => '$' + Number(n || 0).toLocaleString('es-CL');

  // ========== RUT & EMAIL ==========
  const authUser = window.roelAuth?.getUser();
  let clientRut = authUser?.rut || localStorage.getItem('client_rut') || null;
  let clientEmail = authUser?.email || localStorage.getItem('client_email') || null;

  const normRut = r => String(r || '').trim().toUpperCase().replace(/\./g, '');
  const isRut = r => /^[0-9]{1,8}-[0-9K]$/.test(r || '');
  const isEmail = e => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e || '');

  // ========== MODO SILENCIO ==========
  let silentMode = false;
  let pc = null;
  let dc = null;

  // ========== ANTI-FLOOD ==========
  const FLOOD_LIMIT = 8; // máximo mensajes por ventana
  const FLOOD_WINDOW = 60000; // 60 segundos en ms
  const FLOOD_COOLDOWN = 30000; // 30 segundos de bloqueo
  let messageTimestamps = [];
  let floodBlocked = false;
  let floodBlockedUntil = 0;

  function setSilentMode(silent) {
    silentMode = silent;
    if (silent) {
      log('mode', 'Silent mode ON - DESCONECTANDO');
      if (dc && dc.readyState === 'open') {
        dc.send(JSON.stringify({ type: 'response.cancel' }));
      }
      setTimeout(() => {
        cleanup();
        if (btnIniciar) btnIniciar.style.display = 'none';
        if (btnReactivar) btnReactivar.style.display = 'inline-block';
        setConn('closed');
      }, 500);
    } else {
      log('mode', 'Silent mode OFF - RECONECTANDO');
      if (btnReactivar) btnReactivar.style.display = 'none';
      if (btnIniciar) btnIniciar.style.display = 'inline-block';
    }
  }

  if (btnReactivar) {
    btnReactivar.onclick = async () => {
      silentMode = false;
      addText('assistant', '[Reactivando...]');
      await connect();
      addText('assistant', '[Bot reactivado. ¿En qué puedo ayudarte?]');
    };
  }

  async function ensureRut() {
    if (clientRut && isRut(clientRut)) return clientRut;
    const r = prompt('Para continuar con el carrito, ingresa tu RUT (formato 12345678-9 o 12345678-K):');
    if (!r) return null;
    const n = normRut(r);
    if (!isRut(n)) {
      alert('RUT inválido. Intenta nuevamente.');
      return null;
    }
    clientRut = n;
    localStorage.setItem('client_rut', n);
    addText('assistant', `Gracias. Registraré tu RUT: ${n}.`);
    speak('Gracias. Registraré tu RUT.');
    return n;
  }

  async function ensureEmail() {
    if (clientEmail && isEmail(clientEmail)) return clientEmail;
    const e = prompt('Para generar el link de pago, ingresa tu email:');
    if (!e) return null;
    const trimmed = String(e).trim();
    if (!isEmail(trimmed)) {
      alert('Email inválido. Intenta nuevamente.');
      return null;
    }
    clientEmail = trimmed;
    localStorage.setItem('client_email', trimmed);
    addText('assistant', `Gracias. Registraré tu email: ${trimmed}.`);
    speak('Gracias. Registraré tu email.');
    return trimmed;
  }

  // ========== THREE.JS AVATAR ==========
  const canvas = document.getElementById('canvas3d');
  if (!canvas) {
    console.error('[AGENTE] Canvas #canvas3d not found');
    return;
  }

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  const scene = new THREE.Scene();

  const camera = new THREE.PerspectiveCamera(30, 1, 0.1, 100);
  const isMobileView = window.innerWidth <= 640;
  const isTabletView = window.innerWidth <= 920 && window.innerWidth > 640;
  const camZ = isMobileView ? 3.2 : (isTabletView ? 4.0 : 4.8);
  const camY = isMobileView ? 1.4 : (isTabletView ? 1.5 : 1.6);
  camera.position.set(0, camY, camZ);
  camera.lookAt(0, isMobileView ? 1.5 : (isTabletView ? 1.6 : 1.7), 0);

  scene.add(new THREE.HemisphereLight(0xbce7ff, 0x0b1220, 0.9));
  const dLight = new THREE.DirectionalLight(0xffffff, 0.85);
  dLight.position.set(2, 4, 3);
  scene.add(dLight);

  const ground = new THREE.Mesh(
    new THREE.CircleGeometry(1.05, 48),
    new THREE.MeshBasicMaterial({ color: 0x000000, transparent: true, opacity: 0.28 })
  );
  ground.rotation.x = -Math.PI / 2;
  scene.add(ground);

  let avatar = null;
  let mixer = null;
  let jawTargets = [];
  let blinkLT = [];
  let blinkRT = [];
  const avatarBaseY = 0.20;
  const clock = new THREE.Clock();

  const bones = {};
  const baseRot = {};
  const NAMESETS = {
    lArm: ['leftarm', 'upperarm_l', 'mixamorigleftarm', 'shoulder_l'],
    rArm: ['rightarm', 'upperarm_r', 'mixamorigrightarm', 'shoulder_r'],
    lFore: ['leftforearm', 'lowerarm_l', 'mixamorigleftforearm', 'forearm_l'],
    rFore: ['rightforearm', 'lowerarm_r', 'mixamorigrightforearm', 'forearm_r'],
    spine: ['spine1', 'spine', 'mixamorigspine1', 'mixamorigspine'],
    neck: ['neck', 'mixamorigneck']
  };

  const toKey = s => s.toLowerCase();

  function matchName(n, keys) {
    n = toKey(n);
    return keys.some(k => n.endsWith(k) || n.includes(k));
  }

  function capture(b) {
    if (!b) return;
    baseRot[b.uuid] = b.rotation.clone();
  }

  function fit() {
    const w = canvas.clientWidth | 0;
    const h = canvas.clientHeight | 0;
    const pr = Math.min(devicePixelRatio || 1, 2);
    if (renderer.getPixelRatio() !== pr) renderer.setPixelRatio(pr);
    if (canvas.width !== Math.floor(w * pr) || canvas.height !== Math.floor(h * pr)) {
      renderer.setSize(w, h, false);
      renderer.setViewport(0, 0, w, h);
      camera.aspect = (w / h) || 1;
      camera.updateProjectionMatrix();
    }
  }

  function loadAvatar() {
    // Si ya existe un avatar, limpiarlo primero
    if (avatar) {
      console.log('[AGENTE-WIDGET] Avatar ya existe, limpiando antes de cargar nuevo...');
      scene.remove(avatar);
      avatar.traverse(o => {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
          if (Array.isArray(o.material)) {
            o.material.forEach(m => m.dispose());
          } else {
            o.material.dispose();
          }
        }
      });
      avatar = null;
      jawTargets.length = 0;
      blinkLT.length = 0;
      blinkRT.length = 0;
      Object.keys(bones).forEach(k => bones[k] = null);
      if (mixer) {
        mixer.stopAllAction();
        mixer = null;
      }
    }

    const loader = new GLTFLoader();
    loader.load(RPM_GLTF, (gltf) => {
      avatar = gltf.scene;

      const isMobile = window.innerWidth <= 640;
      const isTablet = window.innerWidth <= 920 && window.innerWidth > 640;
      const avatarScale = isMobile ? 0.55 : (isTablet ? 0.70 : 0.92);
      avatar.scale.setScalar(avatarScale);
      avatar.position.set(0, avatarBaseY, 0);
      scene.add(avatar);
      ground.position.set(0, avatarBaseY - 0.01, 0);

      avatar.traverse(o => {
        if (o.isMesh && o.morphTargetDictionary) {
          const d = o.morphTargetDictionary;
          ['jawOpen', 'viseme_aa', 'vrc.v_aa', 'MouthOpen', 'mouthOpen'].forEach(k => {
            if (d[k] != null) jawTargets.push({ mesh: o, index: d[k] });
          });
          ['eyeBlinkLeft', 'blink_left', 'Blink_L', 'leftEyeClosed'].forEach(k => {
            if (d[k] != null) blinkLT.push({ mesh: o, index: d[k] });
          });
          ['eyeBlinkRight', 'blink_right', 'Blink_R', 'rightEyeClosed'].forEach(k => {
            if (d[k] != null) blinkRT.push({ mesh: o, index: d[k] });
          });
        }
        if (o.isBone) {
          const n = o.name || '';
          if (!bones.lArm && matchName(n, NAMESETS.lArm)) bones.lArm = o;
          if (!bones.rArm && matchName(n, NAMESETS.rArm)) bones.rArm = o;
          if (!bones.lFore && matchName(n, NAMESETS.lFore)) bones.lFore = o;
          if (!bones.rFore && matchName(n, NAMESETS.rFore)) bones.rFore = o;
          if (!bones.spine && matchName(n, NAMESETS.spine)) bones.spine = o;
          if (!bones.neck && matchName(n, NAMESETS.neck)) bones.neck = o;
        }
        if (o.isMesh) o.frustumCulled = false;
      });

      ['lArm', 'rArm', 'lFore', 'rFore', 'spine', 'neck'].forEach(k => capture(bones[k]));

      if (gltf.animations && gltf.animations.length) {
        mixer = new THREE.AnimationMixer(avatar);
        const clip = THREE.AnimationClip.findByName(gltf.animations, 'idle') || gltf.animations[0];
        mixer.clipAction(clip).play();
      }
      log('3d', 'bones', Object.keys(bones).filter(k => bones[k]).join(', '));
    }, undefined, (err) => log('3d', 'avatar.error', String(err), 'err'));
  }

  // ========== AUDIO ==========
  let audioCtx = null;
  let analyser = null;
  let dataArr = null;
  let smLvl = 0;

  function setupFromRemoteStream(stream) {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();

    if (audioCtx.state === 'suspended') {
      audioCtx.resume().then(() => {
        log('audio', 'AudioContext resumed (iOS fix)');
      }).catch((err) => {
        log('audio', 'Failed to resume AudioContext: ' + err, null, 'err');
      });
    }

    const src = audioCtx.createMediaStreamSource(stream);
    analyser = audioCtx.createAnalyser();
    analyser.fftSize = 1024;
    dataArr = new Uint8Array(analyser.fftSize);
    const sink = audioCtx.createGain();
    sink.gain.value = 0;
    src.connect(analyser);
    analyser.connect(sink);
    sink.connect(audioCtx.destination);
    log('audio', 'analyser.fromRemote');
  }

  // ========== BLINK ==========
  let blinkPhase = 0;
  let blinkT = 0;
  let nextBlink = performance.now() + 1500 + Math.random() * 3500;

  function blinkUpdate(dt, now) {
    let v = 0;
    if (blinkPhase === 0 && now > nextBlink) {
      blinkPhase = 1;
      blinkT = 0;
    }
    if (blinkPhase === 1) {
      blinkT += dt;
      v = Math.min(1, blinkT / 0.09);
      if (blinkT >= 0.09) {
        blinkPhase = 2;
        blinkT = 0;
      }
    } else if (blinkPhase === 2) {
      blinkT += dt;
      v = 1 - Math.min(1, blinkT / 0.12);
      if (blinkT >= 0.12) {
        blinkPhase = 0;
        nextBlink = now + 1200 + Math.random() * 3000;
        v = 0;
      }
    }
    blinkLT.forEach(({ mesh, index }) => mesh.morphTargetInfluences[index] = v);
    blinkRT.forEach(({ mesh, index }) => mesh.morphTargetInfluences[index] = v);
  }

  // ========== MIRADA CON MOUSE ==========
  const look = { x: 0, y: 0 };
  canvas.addEventListener('pointermove', e => {
    const r = canvas.getBoundingClientRect();
    const nx = (e.clientX - r.left) / r.width * 2 - 1;
    const ny = (e.clientY - r.top) / r.height * 2 - 1;
    look.x = nx;
    look.y = ny;
  });

  // ========== ANIMATE ==========
  function animate() {
    requestAnimationFrame(animate);
    fit();
    const dt = clock.getDelta();
    const now = performance.now();
    if (mixer) mixer.update(dt);

    if (analyser) {
      analyser.getByteTimeDomainData(dataArr);
      let sum = 0;
      for (let i = 0; i < dataArr.length; i++) {
        const v = (dataArr[i] - 128) / 128;
        sum += v * v;
      }
      const rms = Math.sqrt(sum / dataArr.length);
      const lvl = Math.min(1, Math.max(0, (rms - 0.02) * 8));
      smLvl = smLvl * 0.8 + lvl * 0.2;
      jawTargets.forEach(({ mesh, index }) => mesh.morphTargetInfluences[index] = smLvl);
    }

    blinkUpdate(dt, now);

    if (avatar) {
      avatar.position.y = avatarBaseY + Math.sin(now * 0.0006) * 0.01;
      avatar.rotation.y = Math.sin(now * 0.00025) * 0.18;
      ground.position.y = avatarBaseY - 0.01;
    }

    const t = now * 0.001;
    const sway = Math.sin(t * 0.9) * 0.18;
    const bend = Math.sin(t * 1.2) * 0.10;
    const spineTwist = Math.sin(t * 0.5) * 0.04;

    if (bones.lArm && baseRot[bones.lArm.uuid]) {
      const b = baseRot[bones.lArm.uuid];
      bones.lArm.rotation.set(b.x, b.y, b.z + sway);
    }
    if (bones.rArm && baseRot[bones.rArm.uuid]) {
      const b = baseRot[bones.rArm.uuid];
      bones.rArm.rotation.set(b.x, b.y, b.z - sway);
    }
    if (bones.lFore && baseRot[bones.lFore.uuid]) {
      const b = baseRot[bones.lFore.uuid];
      bones.lFore.rotation.set(b.x + bend * 0.5, b.y, b.z + bend * 0.2);
    }
    if (bones.rFore && baseRot[bones.rFore.uuid]) {
      const b = baseRot[bones.rFore.uuid];
      bones.rFore.rotation.set(b.x + bend * 0.5, b.y, b.z - bend * 0.2);
    }
    if (bones.spine && baseRot[bones.spine.uuid]) {
      const b = baseRot[bones.spine.uuid];
      bones.spine.rotation.set(b.x, b.y, b.z + spineTwist);
    }
    if (bones.neck && baseRot[bones.neck.uuid]) {
      const b = baseRot[bones.neck.uuid];
      const yaw = look.x * 0.25;
      const pitch = -look.y * 0.15;
      bones.neck.rotation.set(b.x + pitch, b.y + yaw, b.z);
    }

    renderer.render(scene, camera);
  }

  animate();

  // ========== REALTIME ==========
  if (remote) {
    remote.autoplay = true;
    remote.playsInline = true;
    remote.muted = false;
  }

  function tryPlay() {
    if (audioCtx && audioCtx.state === 'suspended') {
      audioCtx.resume().catch((err) => {
        log('audio', 'tryPlay resume failed: ' + err, null, 'err');
      });
    }

    const p = remote.play();
    if (p && p.catch) {
      p.then(() => {
        log('audio', 'tryPlay success');
      }).catch((err) => {
        log('audio', 'tryPlay failed: ' + err.message, null, 'err');
        setTimeout(() => {
          remote.play().catch(() => { });
        }, 300);
      });
    }
  }

  let micStream = null;
  let suppressCartText = false;

  function setConn(state, latencyMs) {
    const map = { none: '#ef4444', connecting: '#fbbf24', connected: '#22d3ee', closed: '#ef4444' };
    if (dot) dot.style.background = map[state] || '#ef4444';
    if (lat) lat.textContent = latencyMs != null ? `${latencyMs} ms` : (state === 'connected' ? 'conectado' : 'sin conexión');
  }

  async function getMic() {
    try {
      micStream = await navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 },
        video: false
      });
      log('mic', 'ok');
    } catch (e) {
      log('mic', 'denegado', String(e), 'err');
    }
    return micStream;
  }

  async function connect() {
    console.log('[AGENTE-WIDGET] connect() llamado');
    if (pc) {
      log('rtc', 'already');
      console.log('[AGENTE-WIDGET] Ya existe conexión activa');
      return;
    }
    setConn('connecting');
    log('rtc', 'connect()');
    let eph = '';
    try {
      console.log('[AGENTE-WIDGET] Solicitando sesión a:', SESSION_EP);
      const r = await fetch(SESSION_EP, { method: 'POST' });
      const tx = await r.text();
      let js = {};
      try {
        js = JSON.parse(tx);
      } catch { }
      eph = js?.client_secret?.value || '';
      log('net', 'realtime_session', eph ? 'token ok' : ('payload:' + tx), eph ? 'log' : 'err');
      if (!eph) {
        addText('assistant', 'No pude abrir sesión');
        setConn('none');
        return;
      }
    } catch (e) {
      log('net', 'realtime_session.error', String(e), 'err');
      addText('assistant', 'No pude abrir sesión');
      setConn('none');
      return;
    }

    pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
    pc.addTransceiver('audio', { direction: 'recvonly' });
    pc.onconnectionstatechange = () => {
      log('rtc', 'state', pc.connectionState);
      if (pc.connectionState === 'connected') setConn('connected');
      if (['disconnected', 'failed', 'closed'].includes(pc.connectionState)) {
        addText('assistant', 'Conexión finalizada');
        cleanup();
      }
    };
    pc.oniceconnectionstatechange = () => log('rtc', 'ice', pc.iceConnectionState);
    pc.ontrack = (ev) => {
      remote.srcObject = ev.streams[0];
      tryPlay();
      setupFromRemoteStream(ev.streams[0]);
      log('rtc', 'ontrack audio');
    };

    try {
      const mic = await getMic();
      if (mic) mic.getTracks().forEach(t => pc.addTrack(t, mic));
    } catch { }

    dc = pc.createDataChannel('oai-events');
    dc.onopen = () => {
      log('dc', 'open');
      dc.send(JSON.stringify({
        type: 'session.update',
        session: {
          voice: 'alloy',
          instructions: SYSTEM_PROMPT,
          tools: [
            {
              type: 'function',
              name: 'consultar_precio_producto_roelplant',
              description: 'Search for product by name with optional filters for exact matching',
              parameters: {
                type: 'object',
                properties: {
                  nombre: { type: 'string', description: 'Product name to search' },
                  tipo: { type: 'string', description: 'Optional: product type filter (SEMILLA, ESQUEJE, PLANTA TERMINADA, etc.)' }
                },
                required: ['nombre']
              }
            },
            {
              type: 'function',
              name: 'consultar_disponibles_roelplant',
              parameters: { type: 'object', properties: {} }
            },
            {
              type: 'function',
              name: 'consultar_disponibles_por_tipo_roelplant',
              parameters: { type: 'object', properties: { tipo: { type: 'string' } }, required: ['tipo'] }
            },
            {
              type: 'function',
              name: 'carrito_operar',
              parameters: {
                type: 'object',
                properties: {
                  action: { type: 'string', enum: ['add', 'add_by_name', 'update_qty', 'remove', 'clear', 'summary', 'checkout'] },
                  code: { type: 'string' },
                  name: { type: 'string' },
                  qty: { type: 'number' },
                  tier: { type: 'string' },
                  tipo: { type: 'string', description: 'Optional: product type filter (SEMILLA, ESQUEJE, PLANTA TERMINADA, etc.)' },
                  unit: { type: 'string' },
                  price: { type: 'number' },
                  image: { type: 'string' }
                },
                required: ['action'],
                additionalProperties: true
              }
            },
            {
              type: 'function',
              name: 'activar_modo_silencio',
              description: 'Activa el modo silencio cuando el usuario dice STOP, Basta, Silencio, etc',
              parameters: { type: 'object', properties: {}, additionalProperties: false }
            },
            {
              type: 'function',
              name: 'seleccionar_producto_de_opciones',
              description: 'Cuando hay múltiples opciones de productos mostradas, permite seleccionar una por índice o descripción y agregarla al carrito',
              parameters: {
                type: 'object',
                properties: {
                  index: { type: 'number', description: 'Índice del producto (1, 2, 3, etc. basado en 1)' },
                  description: { type: 'string', description: 'Descripción de cuál quiere (ej: "la de maceta", "la de bandeja", "la semilla", "la planta terminada")' },
                  qty: { type: 'number', description: 'Cantidad a agregar al carrito (default: 1)' }
                },
                additionalProperties: false
              }
            },
            {
              type: 'function',
              name: 'agregar_multiples_del_selector',
              description: 'Cuando el usuario pide agregar MÚLTIPLES opciones del selector (ej: "1 de la primera y 2 de la segunda"), usa este tool para agregar todas a la vez',
              parameters: {
                type: 'object',
                properties: {
                  selections: {
                    type: 'array',
                    description: 'Array de selecciones a agregar',
                    items: {
                      type: 'object',
                      properties: {
                        index: { type: 'number', description: 'Índice del producto (1, 2, 3, etc. basado en 1)' },
                        description: { type: 'string', description: 'Descripción alternativa (ej: "semilla", "planta terminada")' },
                        qty: { type: 'number', description: 'Cantidad a agregar (default: 1)' }
                      }
                    }
                  }
                },
                required: ['selections'],
                additionalProperties: false
              }
            }
          ]
        }
      }));
      // Solo agregar mensaje de conexión una vez
      if (!window._agenteConnectedMessageShown) {
        window._agenteConnectedMessageShown = true;
        addText('assistant', 'Conectado. Escribe o usa los atajos.');
      }
    };

    const fnAcc = {};
    dc.onmessage = async (ev) => {
      let msg;
      try {
        msg = JSON.parse(ev.data);
      } catch {
        return;
      }
      if (msg.type !== 'response.audio.delta') log('rx', msg.type, (msg.name || msg.role || ''));
      if (msg.type === 'error') {
        log('rx', 'error', JSON.stringify(msg), 'err');
        return;
      }

      // Acumular transcripción de audio
      if (msg.type === 'response.audio_transcript.delta') {
        const itemId = msg.item_id || 'current';
        if (!window._transcriptAcc) window._transcriptAcc = {};
        if (!window._transcriptAcc[itemId]) window._transcriptAcc[itemId] = '';
        if (msg.delta) window._transcriptAcc[itemId] += msg.delta;
        return;
      }

      // Mostrar transcripción completa cuando termine
      if (msg.type === 'response.audio_transcript.done') {
        const itemId = msg.item_id || 'current';
        if (window._transcriptAcc && window._transcriptAcc[itemId]) {
          const text = window._transcriptAcc[itemId];
          console.log('[AGENTE] Transcripción completa:', text);
          if (!suppressCartText && text.trim()) {
            addText('assistant', text.trim());
          }
          delete window._transcriptAcc[itemId];
        }
        return;
      }

      // Contar interacciones de voz para anti-flood
      if (msg.type === 'conversation.item.created') {
        if (msg.item && msg.item.type === 'message' && msg.item.role === 'user') {
          // El usuario acaba de enviar un mensaje de voz
          console.log('[AGENTE] Mensaje de voz detectado');

          // Verificar si está bloqueado ANTES de registrar
          const now = Date.now();
          if (floodBlocked && now < floodBlockedUntil) {
            const secsLeft = Math.ceil((floodBlockedUntil - now) / 1000);
            console.log('[AGENTE] Bloqueado por flood, cancelando respuesta');
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({ type: 'response.cancel' }));
            }
            addText('assistant', `🚫 Bloqueado. Espera ${secsLeft} segundos.`);
            return;
          }

          // Limpiar mensajes antiguos y verificar límite
          cleanOldMessages();
          if (messageTimestamps.length >= FLOOD_LIMIT) {
            floodBlocked = true;
            floodBlockedUntil = now + FLOOD_COOLDOWN;
            console.log('[AGENTE] Límite alcanzado, bloqueando');
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({ type: 'response.cancel' }));
            }
            addText('assistant', `🚫 Límite alcanzado (${FLOOD_LIMIT} mensajes/minuto). Bloqueado por 30 segundos.`);
            return;
          }

          // Advertencia si está cerca del límite
          if (messageTimestamps.length === 6) {
            addText('assistant', '⚠️ Vas rápido. Solo 2 mensajes más en este minuto.');
          }

          // Registrar el mensaje
          recordMessage();
          console.log('[AGENTE] Mensaje registrado, total:', messageTimestamps.length);
        }
        return;
      }

      if (msg.type === 'response.function_call_arguments.delta' || msg.type === 'function_call.arguments.delta') {
        const id = msg.call_id || msg.id;
        if (!fnAcc[id]) fnAcc[id] = { name: '', args: '' };
        if (msg.name) fnAcc[id].name = msg.name;
        if (msg.delta) fnAcc[id].args += msg.delta;
        return;
      }

      if (msg.type === 'response.function_call_arguments.done' || msg.type === 'function_call.arguments.done') {
        const id = msg.call_id || msg.id;
        const name = msg.name || fnAcc[id]?.name || '';
        let args = {};
        try {
          args = JSON.parse(fnAcc[id]?.args || '{}');
        } catch { }
        log('fn', 'args.done', name + ' ' + JSON.stringify(args));

        if (name === 'consultar_precio_producto_roelplant') {
          const searchTerm = String(args.nombre || '').trim();
          const tipoFilter = args.tipo ? String(args.tipo).trim() : null;

          // Construir payload con tipo opcional
          const payload = { nombre: searchTerm };
          if (tipoFilter) {
            payload.tipo = tipoFilter;
            console.log('[AGENTE] ⚠️ BÚSQUEDA CON FILTRO DE TIPO:', tipoFilter);
          }

          console.log('[AGENTE] Buscando producto con payload:', JSON.stringify(payload));
          const out = await postJSON(TOOL_EP, payload);
          console.log('[AGENTE] Resultado de búsqueda:', JSON.stringify(out).substring(0, 300));

          // SIEMPRE mostrar modal visual cuando es consulta de precio/info
          // Convertir resultado único en formato de múltiples opciones para mostrar en modal
          if (out.status === 'ok') {
            // Un solo resultado → convertir a formato 'multiple' para mostrar modal
            const singleItemAsMultiple = {
              status: 'multiple',
              count: 1,
              searchTerm: searchTerm,
              items: [out]
            };

            // CANCELAR la respuesta del AI
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({ type: 'response.cancel' }));
            }

            // Mostrar modal con 1 opción
            handleProducto(singleItemAsMultiple);

            // Preparar output para el AI
            const itemForAI = {
              nombre: out.variedad,
              referencia: out.referencia,
              tipo: out.tipo_producto,
              atributos: out.attrs ? out.attrs.map(a => a.valor).join(', ') : '',
              stock: out.stock,
              precio_final_con_iva: out.precio_detalle,
              unidad: out.unidad
            };

            const aiOutput = {
              status: 'found',
              count: 1,
              message: 'Product found. Describe it briefly: name, type, price, stock. Tell customer they can select it from the visual card.',
              item: itemForAI
            };

            // Enviar resultado y crear nueva respuesta
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({
                type: 'conversation.item.create',
                item: { type: 'function_call_output', call_id: id, output: JSON.stringify(aiOutput) }
              }));
              dc.send(JSON.stringify({
                type: 'response.create',
                response: { modalities: ['audio', 'text'] }
              }));
            }
          } else if (out.status === 'multiple') {
            // Múltiples resultados
            out.searchTerm = searchTerm;

            // CANCELAR la respuesta del AI
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({ type: 'response.cancel' }));
            }

            // Mostrar modal
            handleProducto(out);

            // Preparar output para el AI con precios claros
            const itemsForAI = out.items.map(item => ({
              nombre: item.variedad,
              referencia: item.referencia,
              tipo: item.tipo_producto,
              atributos: item.attrs ? item.attrs.map(a => a.valor).join(', ') : '',
              stock: item.stock,
              precio_final_con_iva: item.precio_detalle,
              unidad: item.unidad
            }));

            const aiOutput = {
              status: 'multiple',
              count: out.count,
              message: 'Multiple options found. Describe each option.',
              items: itemsForAI
            };

            // Enviar resultado y crear nueva respuesta
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({
                type: 'conversation.item.create',
                item: { type: 'function_call_output', call_id: id, output: JSON.stringify(aiOutput) }
              }));
              dc.send(JSON.stringify({
                type: 'response.create',
                response: { modalities: ['audio', 'text'] }
              }));
            }
          } else {
            // not_found o error
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({
                type: 'conversation.item.create',
                item: { type: 'function_call_output', call_id: id, output: JSON.stringify(out) }
              }));
              dc.send(JSON.stringify({
                type: 'response.create',
                response: { modalities: ['audio', 'text'] }
              }));
            }
          }
        } else if (name === 'consultar_disponibles_roelplant') {
          const lst = await getJSON(DISP_EP);
          handleLista(lst, 'Disponibles');
          if (dc && dc.readyState === 'open') {
            dc.send(JSON.stringify({
              type: 'conversation.item.create',
              item: { type: 'function_call_output', call_id: id, output: JSON.stringify(lst) }
            }));
            dc.send(JSON.stringify({
              type: 'response.create',
              response: { modalities: ['audio', 'text'] }
            }));
          }
        } else if (name === 'consultar_disponibles_por_tipo_roelplant') {
          const lst = await postJSON(DISP_EP, { tipo: String(args.tipo || '').trim() });
          handleLista(lst, `Disponibles · ${args.tipo || ''}`);
          if (dc && dc.readyState === 'open') {
            dc.send(JSON.stringify({
              type: 'conversation.item.create',
              item: { type: 'function_call_output', call_id: id, output: JSON.stringify(lst) }
            }));
            dc.send(JSON.stringify({
              type: 'response.create',
              response: { modalities: ['audio', 'text'] }
            }));
          }
        } else if (name === 'carrito_operar') {
          suppressCartText = true;
          if (dc && dc.readyState === 'open') {
            dc.send(JSON.stringify({ type: 'response.cancel' }));
          }
          await handleCarrito(args);
        } else if (name === 'activar_modo_silencio') {
          if (dc && dc.readyState === 'open') {
            dc.send(JSON.stringify({ type: 'response.cancel' }));
          }
          addText('assistant', '[Modo silencio. Click en "Reactivar Bot" para continuar]');
          setSilentMode(true);
          if (dc && dc.readyState === 'open') {
            dc.send(JSON.stringify({
              type: 'conversation.item.create',
              item: { type: 'function_call_output', call_id: id, output: JSON.stringify({ status: 'ok' }) }
            }));
          }
        } else if (name === 'agregar_multiples_del_selector') {
          console.log('[AGENTE] ========================================');
          console.log('[AGENTE] FUNCIÓN: agregar_multiples_del_selector');
          console.log('[AGENTE] Args completos:', JSON.stringify(args, null, 2));
          console.log('[AGENTE] ========================================');

          if (!multipleProductsData || !multipleProductsData.items) {
            addText('assistant', 'No hay opciones disponibles para seleccionar.');
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({
                type: 'conversation.item.create',
                item: { type: 'function_call_output', call_id: id, output: JSON.stringify({ status: 'error', message: 'No options available' }) }
              }));
            }
            delete fnAcc[id];
            return;
          }

          const selections = args.selections || [];
          if (!Array.isArray(selections) || selections.length === 0) {
            addText('assistant', 'No especificaste qué productos agregar.');
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({
                type: 'conversation.item.create',
                item: { type: 'function_call_output', call_id: id, output: JSON.stringify({ status: 'error', message: 'No selections provided' }) }
              }));
            }
            delete fnAcc[id];
            return;
          }

          console.log('[AGENTE] Múltiples selecciones recibidas:', JSON.stringify(selections, null, 2));

          // Cancelar respuesta del agente
          if (dc && dc.readyState === 'open') {
            dc.send(JSON.stringify({ type: 'response.cancel' }));
          }

          const results = [];
          const addedProducts = [];

          // Procesar cada selección en modo silencioso
          for (let i = 0; i < selections.length; i++) {
            const sel = selections[i];
            console.log(`[AGENTE] ========== PROCESANDO SELECCIÓN ${i + 1}/${selections.length} ==========`);
            console.log('[AGENTE] Selección recibida:', JSON.stringify(sel));

            let selectedIndex = -1;
            const qty = sel.qty && typeof sel.qty === 'number' && sel.qty > 0 ? Math.floor(sel.qty) : 1;
            console.log('[AGENTE] Cantidad parseada de sel.qty:', qty, 'original sel.qty:', sel.qty);

            // Seleccionar por índice
            if (sel.index && typeof sel.index === 'number') {
              selectedIndex = Math.floor(sel.index) - 1; // Convertir de base-1 a base-0
              console.log('[AGENTE] Seleccionado por índice:', sel.index, '→ array index:', selectedIndex);
            }
            // Seleccionar por descripción
            else if (sel.description && typeof sel.description === 'string') {
              const desc = sel.description.toLowerCase();
              console.log('[AGENTE] Buscando por descripción:', desc);
              selectedIndex = multipleProductsData.items.findIndex(item => {
                const itemDesc = [
                  item.variedad,
                  item.referencia,
                  item.tipo_producto,
                  ...(item.attrs || []).map(a => a.valor)
                ].join(' ').toLowerCase();
                return itemDesc.includes(desc);
              });
              console.log('[AGENTE] Resultado búsqueda por descripción:', selectedIndex);
            }

            if (selectedIndex >= 0 && selectedIndex < multipleProductsData.items.length) {
              const item = multipleProductsData.items[selectedIndex];
              console.log('[AGENTE] ===== PRODUCTO ENCONTRADO =====');
              console.log('[AGENTE] Producto:', item.variedad);
              console.log('[AGENTE] Referencia:', item.referencia);
              console.log('[AGENTE] ID Variedad:', item.id_variedad);
              console.log('[AGENTE] ⚠️ CANTIDAD QUE SE VA A AGREGAR:', qty, 'tipo:', typeof qty);
              console.log('[AGENTE] Tipo producto:', item.tipo_producto);

              // Agregar al carrito en modo silencioso (sin mostrar mensajes individuales)
              console.log('[AGENTE] Llamando addProductDirectlyToCart con qty:', qty);
              const addResult = await addProductDirectlyToCart(item, qty, 'retail', true);

              console.log('[AGENTE] Resultado de agregar:', addResult);
              console.log('[AGENTE] addResult.qty retornado:', addResult.qty);

              if (addResult.ok) {
                console.log('[AGENTE] ✅ Agregado exitosamente, guardando resultado con qty:', addResult.qty);
                results.push({ status: 'ok', product: item.variedad, qty: addResult.qty });
                addedProducts.push({ name: item.variedad, qty: addResult.qty, ref: item.referencia });
                console.log('[AGENTE] addedProducts actual:', JSON.stringify(addedProducts));
              } else {
                console.log('[AGENTE] ❌ Error al agregar:', addResult.error);
                results.push({ status: 'error', product: item.variedad, error: addResult.error });
              }
            } else {
              console.warn('[AGENTE] No se encontró producto para selección:', sel);
              results.push({ status: 'not_found', selection: sel });
            }
          }

          // Cerrar modal después de agregar todos
          closeProductSelector();

          // Mostrar mensaje consolidado con todos los productos agregados
          if (addedProducts.length > 0) {
            console.log('[AGENTE] ========== GENERANDO MENSAJE FINAL ==========');
            console.log('[AGENTE] addedProducts completo:', JSON.stringify(addedProducts));
            const summary = addedProducts.map((p, idx) => {
              console.log(`[AGENTE] Producto ${idx + 1}: ${p.qty}× ${p.name}`);
              return `${p.qty}× ${p.name}`;
            }).join(', ');
            console.log('[AGENTE] Summary final:', summary);

            // Obtener carrito actualizado
            const cartResult = await getCatalogCartData();
            if (cartResult.ok && cartResult.cart) {
              const line = composeCartLine(cartResult.cart);
              const message = `Agregué al carrito: ${summary}. ${line}`;
              console.log('[AGENTE] Mensaje que se mostrará:', message);
              addText('assistant', message);
              speak(message);
              showCart(true);
            }
          }

          // Enviar resultado al AI
          if (dc && dc.readyState === 'open') {
            dc.send(JSON.stringify({
              type: 'conversation.item.create',
              item: { type: 'function_call_output', call_id: id, output: JSON.stringify({ status: 'ok', results: results }) }
            }));
          }

        } else if (name === 'seleccionar_producto_de_opciones') {
          if (!multipleProductsData || !multipleProductsData.items) {
            addText('assistant', 'No hay opciones disponibles para seleccionar.');
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({
                type: 'conversation.item.create',
                item: { type: 'function_call_output', call_id: id, output: JSON.stringify({ status: 'error', message: 'No options available' }) }
              }));
            }
            delete fnAcc[id];
            return;
          }

          let selectedIndex = -1;
          const qty = args.qty && typeof args.qty === 'number' && args.qty > 0 ? Math.floor(args.qty) : 1;

          // Seleccionar por índice
          if (args.index && typeof args.index === 'number') {
            selectedIndex = Math.floor(args.index) - 1; // Convertir de base-1 a base-0
          }
          // Seleccionar por descripción
          else if (args.description && typeof args.description === 'string') {
            const desc = args.description.toLowerCase();
            selectedIndex = multipleProductsData.items.findIndex(item => {
              const itemDesc = [
                item.variedad,
                item.referencia,
                item.tipo_producto,
                ...(item.attrs || []).map(a => a.valor)
              ].join(' ').toLowerCase();
              return itemDesc.includes(desc);
            });
          }

          if (selectedIndex >= 0 && selectedIndex < multipleProductsData.items.length) {
            const item = multipleProductsData.items[selectedIndex];
            console.log('[AGENTE] Producto seleccionado por voz:', item, 'cantidad:', qty);

            // Cerrar modal
            closeProductSelector();

            // Cancelar respuesta del agente
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({ type: 'response.cancel' }));
            }

            // Agregar al carrito directamente con la cantidad especificada
            await addProductDirectlyToCart(item, qty, 'retail');

            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({
                type: 'conversation.item.create',
                item: { type: 'function_call_output', call_id: id, output: JSON.stringify({ status: 'ok', selected: item, qty: qty }) }
              }));
            }
          } else {
            addText('assistant', 'No pude identificar qué producto quieres. Intenta ser más específico o usa los botones.');
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({
                type: 'conversation.item.create',
                item: { type: 'function_call_output', call_id: id, output: JSON.stringify({ status: 'error', message: 'Could not identify product' }) }
              }));
              dc.send(JSON.stringify({
                type: 'response.create',
                response: { modalities: ['audio', 'text'] }
              }));
            }
          }
        }
        delete fnAcc[id];
        return;
      }

      if (msg.type === 'response.completed') {
        console.log('[AGENTE] response.completed:', JSON.stringify(msg));

        if (suppressCartText) {
          suppressCartText = false;
          return;
        }

        // Intentar extraer el texto de diferentes estructuras posibles
        let text = null;
        if (msg.output && msg.output.text) {
          text = msg.output.text;
        } else if (msg.response && msg.response.output) {
          // Buscar en los items de output
          for (const item of msg.response.output) {
            if (item.type === 'message' && item.content) {
              for (const content of item.content) {
                if (content.type === 'text' && content.text) {
                  text = content.text;
                  break;
                }
              }
            }
            if (text) break;
          }
        }

        if (text) {
          console.log('[AGENTE] Mostrando texto:', text);
          addText('assistant', text);
        } else {
          console.warn('[AGENTE] No se encontró texto en response.completed');
        }
      }
    };

    try {
      const offer = await pc.createOffer();
      await pc.setLocalDescription(offer);
      const sdp = await fetch('https://api.openai.com/v1/realtime?model=gpt-4o-realtime-preview-2024-12-17', {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + eph,
          'Content-Type': 'application/sdp'
        },
        body: offer.sdp
      });
      const ans = await sdp.text();
      await pc.setRemoteDescription({ type: 'answer', sdp: ans });
      log('rtc', 'SDP ok');
    } catch (e) {
      log('rtc', 'SDP error', String(e), 'err');
      addText('assistant', 'No pude establecer audio');
    }
  }

  function cleanup() {
    console.log('[AGENTE-WIDGET] Ejecutando cleanup...');

    // Cerrar DataChannel
    try {
      if (dc) dc.close();
    } catch { }

    // Cerrar RTCPeerConnection
    try {
      if (pc) pc.close();
    } catch { }

    // Detener stream del micrófono
    if (micStream) {
      console.log('[AGENTE-WIDGET] Deteniendo stream del micrófono...');
      try {
        micStream.getTracks().forEach(track => {
          console.log('[AGENTE-WIDGET] Deteniendo track:', track.kind, track.label);
          track.stop();
        });
      } catch (e) {
        console.error('[AGENTE-WIDGET] Error deteniendo stream:', e);
      }
      micStream = null;
    }

    // Detener audio remoto
    if (remote && remote.srcObject) {
      console.log('[AGENTE-WIDGET] Deteniendo audio remoto...');
      try {
        const tracks = remote.srcObject.getTracks();
        tracks.forEach(track => track.stop());
        remote.srcObject = null;
      } catch (e) {
        console.error('[AGENTE-WIDGET] Error deteniendo audio remoto:', e);
      }
    }

    // Limpiar avatar y escena de Three.js
    if (avatar) {
      console.log('[AGENTE-WIDGET] Limpiando avatar de Three.js...');
      scene.remove(avatar);
      avatar.traverse(o => {
        if (o.geometry) o.geometry.dispose();
        if (o.material) {
          if (Array.isArray(o.material)) {
            o.material.forEach(m => m.dispose());
          } else {
            o.material.dispose();
          }
        }
      });
      avatar = null;
    }

    // Detener animaciones
    if (mixer) {
      console.log('[AGENTE-WIDGET] Deteniendo mixer de animaciones...');
      mixer.stopAllAction();
      mixer = null;
    }

    // Limpiar arrays de morph targets y bones
    jawTargets.length = 0;
    blinkLT.length = 0;
    blinkRT.length = 0;
    Object.keys(bones).forEach(k => bones[k] = null);

    pc = null;
    dc = null;
    setConn('none');

    // Reset flags y acumuladores
    window._agenteConnectedMessageShown = false;
    window._transcriptAcc = {};

    // Reset anti-flood
    messageTimestamps = [];
    floodBlocked = false;
    floodBlockedUntil = 0;

    // Cerrar selector de productos si está abierto
    closeProductSelector();

    log('rtc', 'cleanup');
    console.log('[AGENTE-WIDGET] Cleanup completado');
  }

  // ========== HTTP ==========
  async function getJSON(url) {
    const t0 = performance.now();
    log('net', 'GET ' + url);
    try {
      const r = await fetch(url);
      const tx = await r.text();
      let j = {};
      try {
        j = JSON.parse(tx);
      } catch { }
      log('net', url + ' ' + r.status + ' ' + Math.round(performance.now() - t0) + 'ms', tx.slice(0, 180) + '…');
      return j;
    } catch (e) {
      log('net', url + ' error', String(e), 'err');
      return { status: 'error' };
    }
  }

  async function postJSON(url, body) {
    const t0 = performance.now();
    log('net', 'POST ' + url, JSON.stringify(body));
    try {
      const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const tx = await r.text();
      let j = {};
      try {
        j = JSON.parse(tx);
      } catch { }
      log('net', url + ' ' + r.status + ' ' + Math.round(performance.now() - t0) + 'ms', tx.slice(0, 180) + '…');
      return j;
    } catch (e) {
      log('net', url + ' error', String(e), 'err');
      return { status: 'error' };
    }
  }

  // ========== CART ==========
  function renderCart(c) {
    console.log('[AGENTE] Renderizando carrito:', c);

    const items = (c && Array.isArray(c.items)) ? c.items : [];
    console.log('[AGENTE] Items en carrito:', items.length);

    // Limpiar completamente el contenido
    cartFloatBody.innerHTML = '';

    if (items.length === 0) {
      // Carrito vacío
      cartFloatBody.innerHTML = `<div class="meta" style="opacity:.75">Carrito vacío.</div>`;
      if (cartFloatCount) cartFloatCount.textContent = '';
      return;
    }

    // Renderizar items
    const itemsHtml = items.map(it => {
      // Compatibilidad: formato del catálogo (unit_price_clp, nombre) y formato antiguo (price, name)
      const unitPrice = Number(it.unit_price_clp || it.price || 0);
      const qty = Number(it.qty || 0);
      const lineSub = it.line_total_clp ? Number(it.line_total_clp) :
                      (('subtotal' in it && it.subtotal != null) ? Number(it.subtotal) : unitPrice * qty);
      const imgUrl = it.imagen_url || it.image || 'https://placehold.co/96x96?text=%F0%9F%8C%B1';
      const productName = it.nombre || it.name || it.code || 'Producto';

      return `
      <div class="agente-cart-item">
        <img src="${imgUrl}" alt="">
        <div>
          <div><strong>${productName}</strong></div>
          <div class="meta">${qty} ${it.unit || 'unid.'} · ${it.tier || 'detalle'} · ${formatCLP(unitPrice)} c/u</div>
        </div>
        <div class="meta">${formatCLP(lineSub)}</div>
      </div>`;
    }).join('');

    // Usar total_clp del catálogo si existe, sino calcular
    const total = c.total_clp ? Number(c.total_clp) : items.reduce((s, i) => {
      const p = Number(i.unit_price_clp || i.price || 0), q = Number(i.qty || 0);
      return s + (i.line_total_clp ? Number(i.line_total_clp) :
                  (('subtotal' in i && i.subtotal != null) ? Number(i.subtotal) : p * q));
    }, 0);
    const units = c.item_count ? Number(c.item_count) : items.reduce((s, i) => s + Number(i.qty || 0), 0);
    const lines = items.length;

    // Agregar items y total
    cartFloatBody.innerHTML = itemsHtml + `<div class="agente-cart-total"><span>Total</span><strong>${formatCLP(total)}</strong></div>`;

    if (cartFloatCount) {
      cartFloatCount.textContent = lines ? `(${lines} ${lines === 1 ? 'línea' : 'líneas'} · ${units} unid.)` : '';
    }
  }

  function showCart(open) {
    if (cartFloatPanel) {
      cartFloatPanel.classList[open ? 'remove' : 'add']('hidden');
    }
  }

  // Función auxiliar para obtener CSRF token del catálogo
  function getCatalogCSRF() {
    return window.API?.csrf || '';
  }

  // Función para agregar al carrito del catálogo (usando su API)
  async function addToCatalogCart(productData) {
    console.log('[AGENTE] Agregando al carrito del catálogo:', productData);

    try {
      // Usar apiFetch del catálogo si está disponible (maneja CSRF automáticamente)
      const result = window.apiFetch
        ? await window.apiFetch(CATALOG_CART_ADD, {method: 'POST', body: JSON.stringify(productData)})
        : await (async () => {
            const headers = {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-Token': getCatalogCSRF()
            };
            const response = await fetch(CATALOG_CART_ADD, {
              method: 'POST',
              headers: headers,
              credentials: 'same-origin',
              body: JSON.stringify(productData)
            });
            return await response.json();
          })();

      console.log('[AGENTE] Respuesta del carrito:', result);

      if (result.ok && result.cart) {
        // Actualizar contador del carrito del catálogo
        const cartCount = document.getElementById('cartCount');
        if (cartCount && result.cart.item_count !== undefined) {
          cartCount.textContent = result.cart.item_count;
        }

        // Renderizar carrito del agente también
        renderCart(result.cart);
        return { ok: true, cart: result.cart };
      }

      return { ok: false, error: result.error || 'Error desconocido' };
    } catch (e) {
      console.error('[AGENTE] Error agregando al carrito:', e);
      return { ok: false, error: e.message };
    }
  }

  // Función para obtener el carrito del catálogo (sin renderizar)
  async function getCatalogCartData() {
    try {
      const result = window.apiFetch
        ? await window.apiFetch(CATALOG_CART_GET, {method: 'GET'})
        : await (async () => {
            const response = await fetch(CATALOG_CART_GET, {
              method: 'GET',
              credentials: 'same-origin'
            });
            return await response.json();
          })();

      if (result.ok && result.cart) {
        return { ok: true, cart: result.cart };
      }

      return { ok: false, error: result.error || 'Error desconocido' };
    } catch (e) {
      console.error('[AGENTE] Error obteniendo carrito:', e);
      return { ok: false, error: e.message };
    }
  }

  // Función para obtener y renderizar el carrito del catálogo
  async function getCatalogCart() {
    const result = await getCatalogCartData();
    if (result.ok && result.cart) {
      renderCart(result.cart);
    }
    return result;
  }

  // Función para remover un item del carrito del catálogo
  async function removeFromCatalogCart(itemId) {
    console.log('[AGENTE] Removiendo item del carrito:', itemId);

    try {
      const result = window.apiFetch
        ? await window.apiFetch(CATALOG_CART_REMOVE, {method: 'POST', body: JSON.stringify({ item_id: itemId })})
        : await (async () => {
            const headers = {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-Token': getCatalogCSRF()
            };
            const response = await fetch(CATALOG_CART_REMOVE, {
              method: 'POST',
              headers: headers,
              credentials: 'same-origin',
              body: JSON.stringify({ item_id: itemId })
            });
            return await response.json();
          })();

      console.log('[AGENTE] Respuesta remover item:', result);

      if (result.ok && result.cart) {
        const cartCount = document.getElementById('cartCount');
        if (cartCount && result.cart.item_count !== undefined) {
          cartCount.textContent = result.cart.item_count;
        }
        renderCart(result.cart);
        return { ok: true, cart: result.cart };
      }

      return { ok: false, error: result.error || 'Error desconocido' };
    } catch (e) {
      console.error('[AGENTE] Error removiendo del carrito:', e);
      return { ok: false, error: e.message };
    }
  }

  // Función para vaciar completamente el carrito del catálogo
  async function clearCatalogCart() {
    console.log('[AGENTE] Iniciando vaciado del carrito...');
    try {
      // Primero obtener todos los items del carrito (sin renderizar)
      const cartResult = await getCatalogCartData();
      console.log('[AGENTE] Carrito obtenido:', cartResult);

      if (!cartResult.ok || !cartResult.cart || !cartResult.cart.items) {
        console.error('[AGENTE] No se pudo obtener el carrito');
        return { ok: false, error: 'No se pudo obtener el carrito' };
      }

      const items = cartResult.cart.items;
      console.log('[AGENTE] Items a eliminar:', items.length);

      if (items.length === 0) {
        console.log('[AGENTE] El carrito ya estaba vacío');
        renderCart(cartResult.cart);
        return { ok: true, cart: cartResult.cart, message: 'El carrito ya estaba vacío' };
      }

      // Eliminar cada item
      for (const item of items) {
        console.log('[AGENTE] Eliminando item:', item.item_id, item.nombre);
        const result = await removeFromCatalogCart(item.item_id);
        console.log('[AGENTE] Resultado eliminación:', result);

        if (!result.ok) {
          console.error('[AGENTE] Error eliminando item:', result.error);
        }
      }

      // Obtener el carrito actualizado y renderizarlo
      console.log('[AGENTE] Obteniendo carrito actualizado...');
      const updatedCart = await getCatalogCart();
      console.log('[AGENTE] Carrito actualizado:', updatedCart);

      return { ok: true, cart: updatedCart.cart, message: 'Carrito vaciado exitosamente' };
    } catch (e) {
      console.error('[AGENTE] Error vaciando carrito:', e);
      return { ok: false, error: e.message };
    }
  }

  // Función para actualizar cantidad de un item en el carrito
  async function updateCatalogCartQty(itemId, qty) {
    console.log('[AGENTE] Actualizando cantidad del item:', itemId, 'a', qty);

    try {
      const result = window.apiFetch
        ? await window.apiFetch('/catalogo_detalle/api/cart/update.php', {method: 'POST', body: JSON.stringify({ item_id: itemId, qty: qty })})
        : await (async () => {
            const headers = {
              'Content-Type': 'application/json',
              'Accept': 'application/json',
              'X-CSRF-Token': getCatalogCSRF()
            };
            const response = await fetch('/catalogo_detalle/api/cart/update.php', {
              method: 'POST',
              headers: headers,
              credentials: 'same-origin',
              body: JSON.stringify({ item_id: itemId, qty: qty })
            });
            return await response.json();
          })();

      console.log('[AGENTE] Respuesta actualizar qty:', result);

      if (result.ok && result.cart) {
        const cartCount = document.getElementById('cartCount');
        if (cartCount && result.cart.item_count !== undefined) {
          cartCount.textContent = result.cart.item_count;
        }
        renderCart(result.cart);
        return { ok: true, cart: result.cart };
      }

      return { ok: false, error: result.error || 'Error desconocido' };
    } catch (e) {
      console.error('[AGENTE] Error actualizando cantidad:', e);
      return { ok: false, error: e.message };
    }
  }

  if (cartFloatClose) {
    cartFloatClose.onclick = () => showCart(false);
  }

  // ========== TTS ==========
  function speak(text) {
    if (!dc || dc.readyState !== 'open') return;
    log('send', 'response.create', text.slice(0, 120) + '…');
    dc.send(JSON.stringify({
      type: 'response.create',
      response: { modalities: ['audio', 'text'], instructions: text }
    }));
  }

  // ========== SELECTOR DE PRODUCTOS ==========
  let multipleProductsData = null;
  let pendingProductQuantity = 1; // Cantidad que el usuario pidió
  const productSelectorOverlay = document.getElementById('agenteProductSelector');
  const productSelectorTitle = document.getElementById('agenteProductSelectorTitle');
  const productGrid = document.getElementById('agenteProductGrid');
  const productSelectorClose = document.getElementById('agenteProductSelectorClose');

  if (productSelectorClose) {
    productSelectorClose.onclick = () => closeProductSelector();
  }

  function closeProductSelector() {
    if (productSelectorOverlay) {
      productSelectorOverlay.classList.remove('active');
    }
    multipleProductsData = null;
    pendingProductQuantity = 1;
  }

  function showProductSelector(data) {
    if (!productSelectorOverlay || !productGrid) return;

    multipleProductsData = data;

    // Actualizar título
    if (productSelectorTitle) {
      const countText = data.count === 1 ? '1 opción' : `${data.count} opciones`;
      productSelectorTitle.textContent = `Encontré ${countText} de "${data.searchTerm || 'este producto'}"`;
    }

    // Limpiar grid
    productGrid.innerHTML = '';

    // Crear cards
    data.items.forEach((item, index) => {
      const card = document.createElement('div');
      card.className = 'agente-product-card';
      card.dataset.index = index;
      card.dataset.idVariedad = item.id_variedad;

      const imgUrl = item.imagen || 'https://via.placeholder.com/300x300?text=Sin+imagen';

      // Construir HTML de tipo de producto
      let tipoHtml = '';
      if (item.tipo_producto) {
        tipoHtml = `<div class="agente-product-card-attr" style="color: #22d3ee; font-weight: 600;"><strong>Tipo:</strong> ${item.tipo_producto}</div>`;
      }

      // Construir HTML de atributos
      let attrsHtml = '';
      if (item.attrs && item.attrs.length > 0) {
        attrsHtml = item.attrs.map(attr =>
          `<div class="agente-product-card-attr"><strong>${attr.nombre}:</strong> ${attr.valor}</div>`
        ).join('');
      }

      const hasStock = item.stock > 0;

      card.innerHTML = `
        <img src="${imgUrl}" alt="${item.variedad}" class="agente-product-card-image" loading="lazy">
        <div class="agente-product-card-body">
          <h3 class="agente-product-card-title">${item.variedad}</h3>
          <p class="agente-product-card-ref">Ref: ${item.referencia}</p>
          ${tipoHtml ? `<div class="agente-product-card-attrs">${tipoHtml}</div>` : ''}
          ${attrsHtml ? `<div class="agente-product-card-attrs">${attrsHtml}</div>` : ''}
          <p class="agente-product-card-stock">Stock: ${item.stock} ${item.unidad || 'unidades'}</p>
          <p class="agente-product-card-price">${formatCLP(item.precio_detalle)}</p>
        </div>
        <div class="agente-product-card-footer">
          <div class="agente-product-card-qty">
            <label>Cantidad:</label>
            <input type="number"
                   class="agente-product-card-qty-input"
                   id="qty-${index}"
                   value="1"
                   min="1"
                   max="${item.stock}"
                   ${!hasStock ? 'disabled' : ''}
                   onchange="window.validateProductQty(${index}, ${item.stock})">
          </div>
          <button class="agente-product-card-btn" ${!hasStock ? 'disabled' : ''}
                  onclick="window.selectProductFromModal(${index})">
            ${hasStock ? 'Agregar al carrito' : 'Sin stock'}
          </button>
        </div>
      `;

      productGrid.appendChild(card);
    });

    // Mostrar modal
    productSelectorOverlay.classList.add('active');

    // NO hablar aquí - dejar que el AI hable usando los datos que le enviamos
    // El AI recibirá los items con precio_final_con_iva y los dirá correctamente
  }

  // Función para agregar producto directamente al carrito (sin buscarlo de nuevo)
  async function addProductDirectlyToCart(item, qty = 1, tier = 'retail', silent = false) {
    console.log('[AGENTE] *** addProductDirectlyToCart INICIO ***');
    console.log('[AGENTE] Item:', item.variedad);
    console.log('[AGENTE] Cantidad recibida:', qty, 'tipo:', typeof qty);

    // Verificar stock disponible
    const stock = item.stock || item.disponible_para_reservar || 0;
    if (stock <= 0) {
      if (!silent) {
        addText('assistant', `El producto "${item.variedad}" no tiene stock disponible.`);
        speak(`El producto ${item.variedad} no tiene stock disponible.`);
      }
      return { ok: false, error: 'Sin stock', item: item };
    }

    if (qty > stock) {
      if (!silent) {
        addText('assistant', `Solo hay ${stock} unidades disponibles de "${item.variedad}". Ajustando cantidad.`);
        speak(`Solo hay ${stock} unidades disponibles. Ajustando cantidad.`);
      }
      qty = stock;
    }

    // Asegurar que qty sea un número entero
    const finalQty = Math.max(1, Math.floor(qty));
    console.log('[AGENTE] Cantidad final a enviar:', finalQty);

    // Preparar datos para el API del catálogo
    const price = (tier === 'wholesale') ? item.precio : item.precio_detalle;
    const catalogPayload = {
      id_variedad: item.id_variedad,
      referencia: item.referencia,
      nombre: item.variedad,
      imagen_url: item.imagen || '',
      unit_price_clp: price,
      qty: finalQty
    };

    console.log('[AGENTE] Payload a enviar al API:', JSON.stringify(catalogPayload));

    // Agregar al carrito del catálogo
    const result = await addToCatalogCart(catalogPayload);

    console.log('[AGENTE] Resultado del API:', result);

    if (result.ok && result.cart) {
      if (!silent) {
        const c = result.cart;
        const line = composeCartLine(c);
        addText('assistant', line);
        speak(`Agregué ${finalQty} ${item.unidad || 'unidades'} de ${item.variedad} al carrito. ${line}`);
        showCart(true);
      }
      return { ok: true, cart: result.cart, qty: finalQty, item: item };
    } else {
      if (!silent) {
        const errMsg = result.error || 'No pude agregar el producto al carrito.';
        addText('assistant', errMsg);
        speak('No pude agregar el producto al carrito.');
      }
      return { ok: false, error: result.error, item: item };
    }
  }

  // Función para validar cantidad del producto
  window.validateProductQty = function(index, maxStock) {
    const input = document.getElementById(`qty-${index}`);
    if (!input) return;

    let value = parseInt(input.value) || 1;

    // Validar mínimo
    if (value < 1) {
      value = 1;
    }

    // Validar máximo
    if (value > maxStock) {
      value = maxStock;
      addText('assistant', `Stock máximo disponible: ${maxStock} unidades.`);
    }

    input.value = value;
  };

  // Función global para seleccionar producto desde el modal
  window.selectProductFromModal = async function(index) {
    if (!multipleProductsData || !multipleProductsData.items[index]) return;

    const item = multipleProductsData.items[index];
    console.log('[AGENTE] Producto seleccionado:', item);

    // Leer cantidad del input
    const qtyInput = document.getElementById(`qty-${index}`);
    let qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;

    // Validar cantidad vs stock
    if (qty > item.stock) {
      qty = item.stock;
    }
    if (qty < 1) {
      qty = 1;
    }

    // Cerrar modal
    closeProductSelector();

    // Cancelar respuesta actual del agente
    if (dc && dc.readyState === 'open') {
      dc.send(JSON.stringify({ type: 'response.cancel' }));
    }

    // Agregar al carrito directamente con la cantidad seleccionada
    await addProductDirectlyToCart(item, qty, 'retail');
  };

  // ========== PRODUCTO & LISTAS ==========
  function handleProducto(out) {
    // Verificar si hay resultados para mostrar en el selector
    if (out.status === 'multiple' && out.items && out.items.length >= 1) {
      console.log('[AGENTE] Mostrando selector con', out.items.length, 'producto(s)');
      showProductSelector(out);
      return;
    }

    // Si no hay items, no hacer nada (AI manejará el mensaje)
  }

  function handleLista(lst, label) {
    // Ya no agregamos texto ni hablamos, dejamos que el AI responda basándose en el resultado
    // Solo retornamos para que el tool pueda enviar el output al AI
  }

  const mapTier = (t) => {
    t = String(t || '').toLowerCase();
    if (/mayorista/.test(t) || /wholesale/.test(t)) return 'wholesale';
    return 'retail';
  };

  function composeCartLine(c) {
    const items = (c && Array.isArray(c.items)) ? c.items : [];
    if (!items.length) return 'Carrito vacío.';
    // Compatibilidad: nombre (catálogo) o name/code (formato antiguo)
    const first = items.slice(0, 4).map(it => `${it.qty}× ${it.nombre || it.name || it.code}`).join(' · ');
    // Usar total_clp del catálogo si existe, sino calcular
    const tot = c.total_clp ? Number(c.total_clp) : items.reduce((s, i) => {
      const p = Number(i.unit_price_clp || i.price || 0), q = Number(i.qty || 0);
      return s + (i.line_total_clp ? Number(i.line_total_clp) :
                  (('subtotal' in i && i.subtotal != null) ? Number(i.subtotal) : p * q));
    }, 0);
    const units = c.item_count ? Number(c.item_count) : items.reduce((s, i) => s + Number(i.qty || 0), 0);
    return `Tu carrito: ${first}${items.length > 4 ? ` … (${items.length} líneas)` : ''}. Total ${formatCLP(tot)} · ${units} unid.`;
  }

  // ========== CART HANDLER ==========
  async function handleCarrito(args) {
    const a = (args.action || '').toLowerCase();

    if (a === 'checkout') {
      console.log('[AGENTE] Procesando checkout...');

      // Verificar si el carrito tiene items
      const cartResult = await getCatalogCartData();
      console.log('[AGENTE] Carrito para checkout:', cartResult);

      if (!cartResult.ok || !cartResult.cart || !cartResult.cart.items || cartResult.cart.items.length === 0) {
        const msg = 'Tu carrito está vacío. Agrega productos antes de proceder al pago.';
        addText('assistant', msg);

        if (dc && dc.readyState === 'open') {
          dc.send(JSON.stringify({ type: 'response.cancel' }));
        }

        speak(msg);
        console.log('[AGENTE] Checkout cancelado: carrito vacío');
        return;
      }

      // Carrito tiene items, proceder al checkout
      const msg = 'Te llevo al checkout para completar tu compra.';
      addText('assistant', msg);

      if (dc && dc.readyState === 'open') {
        dc.send(JSON.stringify({ type: 'response.cancel' }));
      }

      speak(msg);

      // Redirigir al checkout después de que termine de hablar
      console.log('[AGENTE] Redirigiendo a checkout en 4 segundos...');
      setTimeout(() => {
        const checkoutUrl = window.buildUrl ? window.buildUrl('checkout.php') : '/catalogo_detalle/checkout.php';
        window.location.href = checkoutUrl;
      }, 4000); // Dar tiempo suficiente para que termine de hablar el mensaje completo

      return;
    }

    // Para agregar productos, usar el carrito del catálogo
    if (a === 'add_by_name' || a === 'add') {
      let productInfo = null;
      let qty = Number(args.qty || 1);
      let tier = mapTier(args.tier || 'retail');

      // Si es add_by_name, buscar el producto primero
      if (a === 'add_by_name') {
        const name = String(args.name || '').trim();
        if (!name) {
          addText('assistant', 'No especificaste el nombre del producto.');
          speak('No especificaste el nombre del producto.');
          return;
        }

        // Construir payload con tipo opcional
        const searchPayload = { nombre: name };
        if (args.tipo) {
          searchPayload.tipo = String(args.tipo).trim();
          console.log('[CARRITO] Buscando con tipo:', searchPayload.tipo);
        }

        console.log('[CARRITO] Buscando producto para agregar:', JSON.stringify(searchPayload));
        const p = await postJSON(TOOL_EP, searchPayload);
        console.log('[CARRITO] Resultado:', p ? `status=${p.status}, count=${p.count || 1}` : 'null');

        // Si hay múltiples resultados, mostrar selector y NO permitir que la AI siga hablando
        if (p && p.status === 'multiple') {
          // CANCELAR respuesta de la AI
          suppressCartText = true;
          if (dc && dc.readyState === 'open') {
            dc.send(JSON.stringify({ type: 'response.cancel' }));
          }

          addText('assistant', `Encontré ${p.count} opciones de "${name}". Por favor selecciona cuál quieres.`);
          speak(`Encontré ${p.count} opciones. Especifica cuál quieres o selecciona de las opciones.`);
          p.searchTerm = name;
          handleProducto(p);
          return;
        }

        if (!p || p.status !== 'ok') {
          addText('assistant', `No encontré el producto "${name}" o no tiene stock disponible.`);
          speak(`No encontré el producto ${name} o no tiene stock disponible.`);
          return;
        }
        productInfo = p;
      } else {
        // Si es 'add' con datos directos, buscar el producto para verificar stock y obtener id_variedad
        const name = String(args.name || '').trim();
        if (name) {
          // Construir payload con tipo opcional
          const searchPayload = { nombre: name };
          if (args.tipo) {
            searchPayload.tipo = String(args.tipo).trim();
            console.log('[CARRITO] Buscando con tipo:', searchPayload.tipo);
          }

          console.log('[CARRITO] Buscando producto para agregar:', JSON.stringify(searchPayload));
          const p = await postJSON(TOOL_EP, searchPayload);
          console.log('[CARRITO] Resultado:', p ? `status=${p.status}, count=${p.count || 1}` : 'null');

          // Si hay múltiples resultados, mostrar selector y NO permitir que la AI siga hablando
          if (p && p.status === 'multiple') {
            // CANCELAR respuesta de la AI
            suppressCartText = true;
            if (dc && dc.readyState === 'open') {
              dc.send(JSON.stringify({ type: 'response.cancel' }));
            }

            addText('assistant', `Encontré ${p.count} opciones de "${name}". Por favor selecciona cuál quieres.`);
            speak(`Encontré ${p.count} opciones. Especifica cuál quieres o selecciona de las opciones.`);
            p.searchTerm = name;
            handleProducto(p);
            return;
          }

          if (!p || p.status !== 'ok') {
            addText('assistant', `No encontré el producto "${name}" o no tiene stock disponible.`);
            speak(`No encontré el producto ${name} o no tiene stock disponible.`);
            return;
          }
          productInfo = p;
        }
      }

      // Verificar que productInfo no sea null
      if (!productInfo) {
        addText('assistant', 'Error: No se pudo obtener información del producto.');
        speak('Error: No se pudo obtener información del producto.');
        return;
      }

      // Verificar stock disponible
      const stock = productInfo.stock || productInfo.disponible_para_reservar || 0;
      if (stock <= 0) {
        addText('assistant', `El producto "${productInfo.variedad}" no tiene stock disponible.`);
        speak(`El producto ${productInfo.variedad} no tiene stock disponible.`);
        return;
      }

      if (qty > stock) {
        addText('assistant', `Solo hay ${stock} unidades disponibles de "${productInfo.variedad}". Ajustando cantidad.`);
        speak(`Solo hay ${stock} unidades disponibles. Ajustando cantidad.`);
        qty = stock;
      }

      // Preparar datos para el API del catálogo
      const price = (tier === 'wholesale') ? productInfo.precio : productInfo.precio_detalle;
      const catalogPayload = {
        id_variedad: productInfo.id_variedad,
        referencia: productInfo.referencia,
        nombre: productInfo.variedad,
        imagen_url: productInfo.imagen || '',
        unit_price_clp: price,
        qty: qty
      };

      // Agregar al carrito del catálogo
      const result = await addToCatalogCart(catalogPayload);

      if (result.ok && result.cart) {
        const c = result.cart;
        const line = composeCartLine(c);
        addText('assistant', line);
        if (dc && dc.readyState === 'open') {
          dc.send(JSON.stringify({ type: 'response.cancel' }));
        }
        speak(`Agregué ${qty} ${productInfo.unidad || 'unidades'} de ${productInfo.variedad} al carrito. ${line}`);
        showCart(true);
      } else {
        const errMsg = result.error || 'No pude agregar el producto al carrito.';
        addText('assistant', errMsg);
        if (dc && dc.readyState === 'open') {
          dc.send(JSON.stringify({ type: 'response.cancel' }));
        }
        speak('No pude agregar el producto al carrito.');
      }

      return;
    }

    // Para ver el resumen del carrito, usar el API del catálogo
    if (a === 'summary') {
      const result = await getCatalogCart();

      if (result.ok && result.cart) {
        const c = result.cart;
        const line = composeCartLine(c);
        addText('assistant', line);
        if (dc && dc.readyState === 'open') {
          dc.send(JSON.stringify({ type: 'response.cancel' }));
        }
        speak(line);
        showCart(true);
      } else {
        addText('assistant', 'No pude obtener el carrito.');
        if (dc && dc.readyState === 'open') {
          dc.send(JSON.stringify({ type: 'response.cancel' }));
        }
        speak('No pude obtener el carrito.');
      }

      return;
    }

    // Para vaciar el carrito
    if (a === 'clear') {
      const result = await clearCatalogCart();
      if (result.ok) {
        // No agregamos texto porque el servidor AI ya generó el mensaje con voz
        // Solo actualizamos visualmente
        showCart(false);
      }
      return;
    }

    // Para remover un item del carrito
    if (a === 'remove') {
      const name = String(args.name || args.code || '').trim();
      if (!name) {
        return;
      }

      // Obtener el carrito para buscar el item por nombre (sin renderizar aún)
      const cartResult = await getCatalogCartData();
      if (cartResult.ok && cartResult.cart && cartResult.cart.items) {
        const item = cartResult.cart.items.find(it =>
          (it.nombre || '').toLowerCase().includes(name.toLowerCase()) ||
          (it.referencia || '').toLowerCase() === name.toLowerCase()
        );

        if (item) {
          const result = await removeFromCatalogCart(item.item_id);
          console.log('[AGENTE] Resultado remover item:', result);
          // No agregamos texto porque el servidor AI ya generó el mensaje
        }
      }
      return;
    }

    // Para actualizar cantidad
    if (a === 'update_qty') {
      const name = String(args.name || args.code || '').trim();
      const qty = Number(args.qty || 0);

      if (!name) {
        return;
      }

      // Obtener el carrito para buscar el item por nombre (sin renderizar aún)
      const cartResult = await getCatalogCartData();
      if (cartResult.ok && cartResult.cart && cartResult.cart.items) {
        const item = cartResult.cart.items.find(it =>
          (it.nombre || '').toLowerCase().includes(name.toLowerCase()) ||
          (it.referencia || '').toLowerCase() === name.toLowerCase()
        );

        if (item) {
          const result = await updateCatalogCartQty(item.item_id, qty);
          console.log('[AGENTE] Resultado actualizar qty:', result);
          // No agregamos texto porque el servidor AI ya generó el mensaje
        }
      }
      return;
    }

    // Acción desconocida - no hacer nada para no contradecir al servidor AI
  }

  // ========== ANTI-FLOOD FUNCTIONS ==========
  function cleanOldMessages() {
    const now = Date.now();
    messageTimestamps = messageTimestamps.filter(ts => now - ts < FLOOD_WINDOW);
  }

  function checkFloodLimit() {
    const now = Date.now();

    // Verificar si está bloqueado
    if (floodBlocked && now < floodBlockedUntil) {
      const secsLeft = Math.ceil((floodBlockedUntil - now) / 1000);
      addText('assistant', `⚠️ Demasiados mensajes. Espera ${secsLeft} segundos más.`);
      return false;
    }

    // Desbloquear si pasó el tiempo
    if (floodBlocked && now >= floodBlockedUntil) {
      floodBlocked = false;
      floodBlockedUntil = 0;
      messageTimestamps = [];
      addText('assistant', '✓ Puedes continuar.');
    }

    // Limpiar mensajes antiguos
    cleanOldMessages();

    const count = messageTimestamps.length;

    // Advertencia suave (7 mensajes)
    if (count === 6) {
      addText('assistant', '⚠️ Vas rápido. Solo 2 mensajes más en este minuto.');
    }

    // Bloqueo (8+ mensajes)
    if (count >= FLOOD_LIMIT) {
      floodBlocked = true;
      floodBlockedUntil = now + FLOOD_COOLDOWN;
      addText('assistant', `🚫 Límite alcanzado (${FLOOD_LIMIT} mensajes/minuto). Bloqueado por 30 segundos.`);
      return false;
    }

    return true;
  }

  function recordMessage() {
    messageTimestamps.push(Date.now());
  }

  // ========== NLP + ENVÍO ==========
  const wantsPrice = (t) => /\b(precio|valor|cu[aá]nto|costo|stock\s+de|precio\s+de)\b/i.test(t);
  const wantsDispon = (t) => /\b(disponible|disponibles|en\s+stock|hay\s+disponible|lista|cat[aá]logo)\b/i.test(t);
  const wantsCartSummary = (t) => /\b(?:ver|mostrar|resumen|estado|mi|el|actualiza|actualizar|actualizado)\s+carrito\b/i.test(t);
  const extractTipo = (t) => {
    const m = t.match(/\b(plantas? de interior|plantas? de exterior|cubre\s*suelo[s]?|árboles|arboles|trepadoras|suculentas|helechos|nativas|introducidas)\b/i);
    return m ? m[0] : null;
  };

  function sendText(q) {
    const qn = String(q || '').trim();
    if (!dc || dc.readyState !== 'open') {
      addText('assistant', 'Conéctate primero con Iniciar.');
      return;
    }

    // Verificar límite anti-flood
    if (!checkFloodLimit()) {
      return;
    }

    // Registrar mensaje
    recordMessage();

    addText('user', qn);

    if (wantsCartSummary(qn)) {
      handleCarrito({ action: 'summary' });
      return;
    }

    let hint = '';
    if (wantsDispon(qn)) {
      const tipo = extractTipo(qn);
      hint = tipo ? ` (usa consultar_disponibles_por_tipo_roelplant con tipo="${tipo}")` : ' (usa consultar_disponibles_roelplant)';
    } else if (wantsPrice(qn)) {
      hint = ' (usa consultar_precio_producto_roelplant si aplica)';
    }

    log('send', 'input_text', qn + hint);
    dc.send(JSON.stringify({ type: 'input_text', text: qn + hint }));

    if (!wantsPrice(qn) && !wantsDispon(qn)) {
      dc.send(JSON.stringify({ type: 'response.create', response: { modalities: ['audio', 'text'] } }));
    }
  }

  // ========== START ==========
  function start() {
    console.log('[AGENTE-WIDGET] start() llamado');
    if (overlay) overlay.style.display = 'none';
    if (btnIniciar) btnIniciar.style.display = 'none';
    console.log('[AGENTE-WIDGET] Cargando avatar...');
    loadAvatar();

    if (!audioCtx) {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      console.log('[AGENTE-WIDGET] AudioContext creado');
      log('audio', 'AudioContext created on user gesture');
    }
    if (audioCtx.state === 'suspended') {
      audioCtx.resume().then(() => {
        log('audio', 'AudioContext resumed on start (iOS fix)');
      }).catch((err) => {
        log('audio', 'Failed to resume on start: ' + err, null, 'err');
      });
    }

    console.log('[AGENTE-WIDGET] Iniciando conexión...');
    connect().then(() => {
      console.log('[AGENTE-WIDGET] Conexión establecida exitosamente');
      const attemptPlay = () => {
        const playPromise = remote.play();
        if (playPromise !== undefined) {
          playPromise.then(() => {
            log('audio', 'remote.play() success');
          }).catch((err) => {
            log('audio', 'remote.play() failed: ' + err.message + ', retrying...', null, 'err');
            setTimeout(attemptPlay, 500);
          });
        }
      };
      attemptPlay();
    }).catch((err) => {
      console.error('[AGENTE-WIDGET] Error en connect():', err);
    });
  }

  if (btnSend) {
    btnSend.onclick = () => {
      const v = txt.value.trim();
      if (!v) return;
      txt.value = '';
      sendText(v);
    };
  }

  if (txt) {
    txt.addEventListener('keydown', e => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        if (btnSend) btnSend.click();
      }
    });
  }

  console.log('[AGENTE-WIDGET] Adjuntando event listeners...');

  document.querySelectorAll('.agente-chip[data-q]').forEach(el => {
    el.addEventListener('click', () => sendText(el.getAttribute('data-q')));
  });

  if (btnIniciar) {
    console.log('[AGENTE-WIDGET] Botón Iniciar encontrado, adjuntando onclick');
    btnIniciar.onclick = start;
  } else {
    console.warn('[AGENTE-WIDGET] Botón Iniciar NO encontrado');
  }

  if (startBig) {
    console.log('[AGENTE-WIDGET] Botón startBig encontrado, adjuntando onclick');
    startBig.onclick = start;
  } else {
    console.warn('[AGENTE-WIDGET] Botón startBig NO encontrado');
  }

  // ========== CARGAR CARRITO AL INICIO ==========
  console.log('[AGENTE-WIDGET] Cargando carrito inicial del catálogo...');
  getCatalogCart().then((res) => {
    console.log('[AGENTE-WIDGET] Respuesta del carrito del catálogo:', res);
    if (res?.ok && res?.cart?.items && Array.isArray(res.cart.items) && res.cart.items.length > 0) {
      showCart(true);
    }
  }).catch(err => {
    console.error('[AGENTE-WIDGET] Error cargando carrito:', err);
  });

  // Función para actualizar el carrito del agente (llamada cuando se abre el modal)
  window.agenteUpdateCart = async function() {
    console.log('[AGENTE] Actualizando carrito...');
    try {
      const result = await getCatalogCart();
      console.log('[AGENTE] Carrito actualizado:', result);
    } catch (err) {
      console.error('[AGENTE] Error actualizando carrito:', err);
    }
  };

  // Exponer funciones globalmente
  window.agenteWidgetStart = start;
  window.agenteWidgetStop = cleanup;

  log('module', 'ready');
  console.log('[AGENTE-WIDGET] Widget standalone loaded successfully');
})();
