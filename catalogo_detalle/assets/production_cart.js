// assets/production_cart.js (v9.1)
// Carrito de producción: permite múltiples especies.
// Reglas: cada especie >= 50 unidades y total >= 200 unidades (o más). Precio: mayorista.

(async function(){
  const $ = (id)=>document.getElementById(id);
  const money = (n)=>'$'+Number(n||0).toLocaleString('es-CL');

  // Login check (reutiliza sesión del sitio)
  const me = await fetch(buildApiUrl('me.php'), { credentials: 'same-origin' })
    .then(r=>r.json())
    .catch(()=>({logged:false}));

  if(!me.logged){
    const rt = encodeURIComponent('produccion.php');
    location.href = buildUrl('index.php?openAuth=1&return_to=' + rt);
    return;
  }

  const q = $('q');
  const list = $('list');
  const empty = $('empty');
  const reloadBtn = $('reloadBtn');

  const kSel = $('kSel');
  const kUnits = $('kUnits');

  const cartEl = $('cart');
  const cartEmpty = $('cartEmpty');

  const sumUnits = $('sumUnits');
  const sumTotal = $('sumTotal');
  const ruleStatus = $('ruleStatus');

  const notesEl = $('notes');
  const clearBtn = $('clearBtn');
  const sendBtn = $('sendBtn');

  const toastOk = $('toastOk');
  const toastErr = $('toastErr');

  const progBar = $('progBar');
  const progTxt = $('progTxt');

  let items = [];
  let cart = {};

  const toast = (el, msg)=>{
    if(!el) return;
    if(toastOk) toastOk.style.display='none';
    if(toastErr) toastErr.style.display='none';
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(()=>{ el.style.display='none'; }, 6500);
  };

  const slug = (s)=>String(s||'').trim().toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
    .replace(/[^a-z0-9]+/g,'-').replace(/-+/g,'-')
    .replace(/^-|-$/g,'') || 'item';

  const sel = ()=>Object.keys(cart).length;
  const units = ()=>Object.values(cart).reduce((a,it)=>a + (it.qty||0), 0);
  const amount = ()=>Object.values(cart).reduce((a,it)=>a + (it.qty||0) * (it.precio||0), 0);

  function rules(){
    const n = sel();
    const u = units();
    if(n < 1) return {ok:false, reason:'Debes agregar al menos 1 especie.'};
    for(const it of Object.values(cart)){
      if((it.qty||0) < 50) return {ok:false, reason:`${it.nombre}: mínimo 50 unidades.`};
    }
    if(u < 200) return {ok:false, reason:`Total mínimo 200 unidades (te faltan ${200-u}).`};
    return {ok:true, reason:'OK'};
  }

  function progress(){
    const u = units();
    const pct = (u <= 0) ? 0 : Math.round((u/200)*100);
    const pctBar = Math.max(0, Math.min(100, pct));
    if(progBar) progBar.style.width = pctBar + '%';
    if(progTxt) progTxt.textContent = `${u}/200 (${pct}%)`;
  }

  function kpis(){
    if(kSel) kSel.textContent = String(sel());
    if(kUnits) kUnits.textContent = String(units());
    if(sumUnits) sumUnits.textContent = String(units());
    if(sumTotal) sumTotal.textContent = money(amount());

    const r = rules();
    if(ruleStatus){
      ruleStatus.textContent = r.ok ? 'OK' : r.reason;
      ruleStatus.className = r.ok ? 'ok' : 'bad';
    }
    progress();
  }

  function renderCart(){
    const vals = Object.values(cart);
    if(cartEl) cartEl.innerHTML = '';
    if(cartEmpty) cartEmpty.style.display = vals.length ? 'none' : 'block';

    vals.forEach(it=>{
      const row = document.createElement('div');
      row.className = 'cartline';
      row.innerHTML = `
        <div>
          <div class="cartname">${it.nombre}</div>
          <div class="cartmeta">${money(it.precio)} mayorista</div>
        </div>
        <div class="qtyctl">
          <button class="btn" type="button" aria-label="Disminuir">-</button>
          <input class="inp" type="number" min="50" step="1" value="${it.qty}">
          <button class="btn" type="button" aria-label="Aumentar">+</button>
          <button class="btn btn-danger" type="button" aria-label="Quitar">✕</button>
        </div>
      `;

      const btns = row.querySelectorAll('button');
      const dec = btns[0];
      const inc = btns[1];
      const rm  = btns[2];
      const inp = row.querySelector('input');

      dec.onclick = ()=>{
        cart[it.uid].qty = Math.max(50, (cart[it.uid].qty||50) - 10);
        inp.value = String(cart[it.uid].qty);
        kpis();
      };
      inc.onclick = ()=>{
        cart[it.uid].qty = (cart[it.uid].qty||50) + 10;
        inp.value = String(cart[it.uid].qty);
        kpis();
      };
      rm.onclick = ()=>{
        delete cart[it.uid];
        renderCart();
        renderList();
        kpis();
      };
      inp.oninput = ()=>{
        const v = parseInt(String(inp.value||'50'), 10);
        cart[it.uid].qty = isNaN(v) ? 50 : Math.max(50, v);
        inp.value = String(cart[it.uid].qty);
        kpis();
      };

      cartEl.appendChild(row);
    });

    kpis();
  }

  const inCart = (uid)=>!!cart[String(uid)];

  function renderList(){
    if(!list) return;
    list.innerHTML = '';

    const term = (q?.value || '').toLowerCase().trim();
    const filtered = items.filter(i => !term || i.nombre.toLowerCase().includes(term));

    if(!filtered.length){
      if(empty){
        empty.style.display='block';
        empty.textContent='Sin resultados.';
      }
      return;
    }

    if(empty) empty.style.display='none';

    filtered.forEach(i=>{
      const uid = String(i.uid);
      const already = inCart(uid);
      const qty = already ? (cart[uid].qty||50) : 50;

      const card = document.createElement('div');
      card.className = 'item';
      card.innerHTML = `
        <div>
          <div class="name">${i.nombre}</div>
          <div class="meta">${money(i.precio)} mayorista</div>
        </div>
        <div class="actions">
          <input class="inp qty" type="number" min="50" step="1" value="${qty}">
          <button class="btn ${already?'btn-danger':'btn-primary'}" type="button">${already?'Quitar':'Agregar'}</button>
        </div>
      `;

      const qtyInp = card.querySelector('input');
      const btn = card.querySelector('button');

      btn.onclick = ()=>{
        if(inCart(uid)){
          delete cart[uid];
          renderCart();
          renderList();
          kpis();
          return;
        }

        const v = parseInt(String(qtyInp.value||'50'), 10);
        cart[uid] = {
          uid,
          nombre: i.nombre,
          precio: i.precio,
          qty: isNaN(v) ? 50 : Math.max(50, v)
        };

        renderCart();
        renderList();
        kpis();
      };

      qtyInp.oninput = ()=>{
        if(!inCart(uid)) return;
        const v = parseInt(String(qtyInp.value||'50'), 10);
        cart[uid].qty = isNaN(v) ? 50 : Math.max(50, v);
        kpis();
        renderCart();
      };

      list.appendChild(card);
    });

    kpis();
  }

  async function load(){
    if(empty){
      empty.style.display='block';
      empty.textContent='Cargando especies en producción…';
    }
    if(list) list.innerHTML='';

    const res = await fetch(buildApiUrl('production/list.php'), { credentials:'same-origin' })
      .then(r=>r.json())
      .catch(()=>null);

    if(!res || !res.ok){
      if(empty) empty.textContent = res?.error ? ('Error: '+res.error) : 'No fue posible cargar especies.';
      return;
    }

    const seen = {};
    items = (res.items || []).map(x=>{
      const nombre = String(x.nombre || x.variedad || x.product_name || '').trim();
      const rawId = x.id ?? x.id_variedad ?? x.product_id ?? null;
      let uid = rawId ? String(rawId) : ('N:' + slug(nombre));
      if(seen[uid]){ seen[uid] += 1; uid = uid + ':' + seen[uid]; }
      else seen[uid] = 1;

      const precio = Number(x.precio_mayorista_clp ?? x.precio_mayorista ?? x.precio ?? 0);
      return { uid, nombre, precio };
    }).filter(i=>i.nombre);

    renderList();
    kpis();
  }

  if(reloadBtn) reloadBtn.onclick = load;
  if(q) q.addEventListener('input', renderList);

  if(clearBtn) clearBtn.onclick = ()=>{
    cart = {};
    if(notesEl) notesEl.value='';
    renderCart();
    renderList();
    kpis();
  };

  if(sendBtn) sendBtn.onclick = async ()=>{
    const r = rules();
    if(!r.ok){ toast(toastErr, r.reason); return; }

    const payload = {
      items: Object.values(cart).map(it=>({
        id: it.uid,
        nombre: it.nombre,
        precio_mayorista_clp: it.precio,
        qty: it.qty
      })),
      notes: (notesEl?.value || '').trim()
    };

    const res = await fetch(buildApiUrl('production/request_create.php'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': (window.ROEL_CSRF || '')
      },
      body: JSON.stringify(payload)
    }).then(r=>r.json()).catch(()=>null);

    if(!res || !res.ok){
      toast(toastErr, res?.error || 'Error creando la solicitud.');
      return;
    }

    // abre WhatsApp
    if(res.whatsapp_url){
      let w = null;
      try { w = window.open(res.whatsapp_url, '_blank'); } catch(e) {}
      if(!w) window.location.href = res.whatsapp_url;
      toast(toastOk, `Solicitud creada: ${res.request_code}. Abriendo WhatsApp…`);
    } else {
      toast(toastOk, `Solicitud creada: ${res.request_code}. WhatsApp no está configurado.`);
    }

    // limpia carrito luego de crear
    cart = {};
    if(notesEl) notesEl.value='';
    renderCart();
    renderList();
    kpis();
  };

  renderCart();
  load();
})();
