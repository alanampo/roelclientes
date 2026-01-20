const mq=window.matchMedia('(max-width:980px)');
const btn=document.getElementById('menuToggle');
const menu=document.getElementById('techMenu');
function upd(){btn.style.display=mq.matches?'flex':'none'}
(mq.addEventListener?mq.addEventListener('change',upd):mq.addListener(upd));upd();
btn?.addEventListener('click',()=>{
  const open=menu.classList.toggle('open');
  btn.setAttribute('aria-expanded',open?'true':'false')
});

document.querySelectorAll('.tech-menu a[href^="#"]').forEach(a=>{
  a.addEventListener('click',e=>{
    const t=document.querySelector(a.getAttribute('href'));
    if(t){
      e.preventDefault();
      if(menu.classList.contains('open'))menu.classList.remove('open');
      t.scrollIntoView({behavior:'smooth',block:'start'})
    }
  });
});

const links2=[...document.querySelectorAll('.tech-menu a[href^="#"]')];
const secs=links2.map(l=>document.querySelector(l.getAttribute('href'))).filter(Boolean);
const io=new IntersectionObserver(es=>{
  es.forEach(en=>{
    if(en.isIntersecting){
      links2.forEach(l=>l.classList.toggle('active',l.getAttribute('href')===('#'+en.target.id)))
    }
  })
},{rootMargin:'-55% 0px -40% 0px',threshold:0});
secs.forEach(s=>io.observe(s));

/* ===== Carrito + Auth ===== */
const API = {
  csrf: '',
  me: null,
  pendingAdd: null,
};


function escapeHtml(s){
  return String(s)
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#039;');
}

function initRegionComuna(regionSelId, comunaSelId, initialRegion=null, initialComuna=null){
  const data = (window.ROEL_LOCATIONS && Array.isArray(window.ROEL_LOCATIONS.regiones)) ? window.ROEL_LOCATIONS.regiones : [];
  const regionSel = document.getElementById(regionSelId);
  const comunaSel = document.getElementById(comunaSelId);
  if(!regionSel || !comunaSel || data.length===0) return;

  function fillRegions(){
    const cur = regionSel.value || '';
    regionSel.innerHTML = '<option value="">Selecciona Región</option>' + data.map(r=>{
      const n = escapeHtml(r.nombre);
      return `<option value="${n}">${n}</option>`;
    }).join('');
    if(initialRegion) regionSel.value = initialRegion;
    else if(cur) regionSel.value = cur;
  }

  function fillComunas(){
    const reg = regionSel.value;
    const found = data.find(r=>r.nombre===reg);
    const comunas = found ? (found.comunas||[]) : [];
    comunaSel.disabled = !reg;
    const cur = comunaSel.value || '';
    comunaSel.innerHTML = '<option value="">Selecciona Comuna</option>' + comunas.map(c=>{
      const n = escapeHtml(c);
      return `<option value="${n}">${n}</option>`;
    }).join('');
    if(initialComuna && comunas.includes(initialComuna)) comunaSel.value = initialComuna;
    else if(cur && comunas.includes(cur)) comunaSel.value = cur;
  }

  fillRegions();
  fillComunas();
  regionSel.addEventListener('change', ()=>{
    comunaSel.value = '';
    fillComunas();
  });
}

async function fetchJson(url, opts={}){
  const o = Object.assign({credentials:'same-origin', cache:'no-store', headers:{'Accept':'application/json'}}, opts);
  const r = await fetch(url, o);
  const j = await r.json().catch(()=>null);
  if(!j) throw new Error('Respuesta inválida');
  return j;
}


function toast(msg){
  const t=document.getElementById('toast');
  t.textContent=msg;
  t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2200);
}

async function apiFetch(url, opts={}){
  const o = Object.assign({headers:{}, credentials:'same-origin', cache:'no-store'}, opts);
  o.headers = Object.assign({'Accept':'application/json'}, o.headers);

  // Asegurar CSRF antes de cualquier POST
  if (o.method && o.method.toUpperCase()==='POST'){
    if(!API.csrf){
      try{ await refreshMe(); }catch(e){ /* ignore */ }
    }
    o.headers['Content-Type']='application/json';
    o.headers['X-CSRF-Token']=API.csrf || '';
  }

  const r = await fetch(url, o);
  let j = await r.json().catch(()=>null);

  // Si el servidor devolvió CSRF inválido, refrescar token y reintentar 1 vez
  if (j && (j.error==='CSRF inválido' || /csrf/i.test(String(j.error||''))) && o.method && o.method.toUpperCase()==='POST'){
    try{
      await refreshMe();
      o.headers['X-CSRF-Token']=API.csrf || '';
      const r2 = await fetch(url, o);
      j = await r2.json().catch(()=>null);
    }catch(e){ /* ignore */ }
  }

  if (!j) throw new Error('Respuesta inválida');
  return j;
}

function setErr(id, msg){
  const el=document.getElementById(id);
  if(!el) return;
  if(!msg){el.style.display='none';el.textContent='';return;}
  el.textContent=msg;
  el.style.display='block';
}

function setAuthTab(tab){
  document.getElementById('tabLogin').classList.toggle('active', tab==='login');
  document.getElementById('tabRegister').classList.toggle('active', tab==='register');
  document.getElementById('paneLogin').style.display = (tab==='login')?'block':'none';
  document.getElementById('paneRegister').style.display = (tab==='register')?'block':'none';
  setErr('authErr','');
}

function openAuthModal(){
  const m=document.getElementById('authModal');
  m.classList.add('open'); m.setAttribute('aria-hidden','false');
  document.body.style.overflow='hidden';
}
function closeAuthModal(){
  const m=document.getElementById('authModal');
  m.classList.remove('open'); m.setAttribute('aria-hidden','true');
  document.body.style.overflow='';
  setErr('authErr','');
}

async function refreshMe(){
  const me = await fetchJson(buildApiUrl('me.php'), {method:'GET'});
  if (me.ok){
    API.csrf = me.csrf || API.csrf;
    API.me = me.logged ? me.customer : null;
  }
  const btnAuth = document.getElementById('btnAuth');
  const btnLogout = document.getElementById('btnLogout');
  const btnAccount = document.getElementById('btnAccount');
  const btnOrders = document.getElementById('btnOrders');
  if (API.me){
    btnAuth.textContent = 'Hola, ' + (API.me.nombre || 'Cliente');
    btnAuth.classList.remove('primary');
    btnAuth.onclick = openCartModal; // acceso rápido
    btnLogout.style.display = 'inline-flex';
    if(btnAccount) btnAccount.style.display = 'inline-flex';
    if(btnOrders) btnOrders.style.display = 'inline-flex';
  }else{
    btnAuth.textContent = 'Ingresar / Registrarse';
    btnAuth.classList.add('primary');
    btnAuth.onclick = openAuthModal;
    btnLogout.style.display = 'none';
    if(btnAccount) btnAccount.style.display = 'none';
    if(btnOrders) btnOrders.style.display = 'none';
  }
}

async function doRegister(){
  setErr('authErr','');
  const rut = (document.getElementById('regRut').value||'').trim();
  const email = (document.getElementById('regEmail').value||'').trim();
  const nombre = (document.getElementById('regNombre').value||'').trim();
  const telefono = (document.getElementById('regTelefono').value||'').trim();
  const domicilio = (document.getElementById('regDomicilio').value||'').trim();
  const ciudad = (document.getElementById('regCiudad').value||'').trim();
  const region = (document.getElementById('regRegion').value||'').trim();
  const comuna = (document.getElementById('regComuna').value||'').trim();
  const password = (document.getElementById('regPass').value||'');

  if(rut.length < 8){ setErr('authErr','RUT inválido'); return; }
  if(email.length < 5 || !email.includes('@')){ setErr('authErr','Email inválido'); return; }
  if(nombre.length < 3){ setErr('authErr','Nombre inválido'); return; }
  if(telefono.length < 6){ setErr('authErr','Teléfono inválido'); return; }
  if(domicilio.length < 3){ setErr('authErr','Domicilio inválido'); return; }
  if(ciudad.length < 2){ setErr('authErr','Ciudad inválida'); return; }
  if(region.length < 2){ setErr('authErr','Región inválida'); return; }
  if(comuna.length < 2){ setErr('authErr','Comuna inválida'); return; }
  if(password.length < 8){ setErr('authErr','La contraseña debe tener al menos 8 caracteres'); return; }

  const payload = {rut,email,nombre,telefono,domicilio,ciudad,region,comuna,password};

  try{
    const j = await apiFetch(buildApiUrl('auth/register.php'), {method:'POST', body: JSON.stringify(payload)});
    if(!j.ok){ setErr('authErr', j.error||'No se pudo registrar'); return; }
    toast('Cuenta creada');
    closeAuthModal();
    await refreshMe();
    await refreshCartCount();
    // Redirect back if a page requested authentication (e.g., produccion.php)
    try{
      const sp = new URLSearchParams(window.location.search || '');
      const rt = sp.get('return_to');
      if(rt){
        // Only allow relative safe paths
        const safe = rt.replace(/^[\/]+/,'').replace(/\s/g,'');
        if(safe && !/^https?:/i.test(safe) && safe.indexOf('..') === -1){
          window.location.href = buildUrl(safe);
          return;
        }
      }
    }catch(_e){}

  }catch(e){
    setErr('authErr', e?.message || 'Error de conexión');
  }
}

async function doLogin(){
  setErr('authErr','');
  const email = (document.getElementById('loginEmail').value||'').trim();
  const password = (document.getElementById('loginPass').value||'');
  if(email.length < 5 || !email.includes('@')){ setErr('authErr','Email inválido'); return; }
  if(!password){ setErr('authErr','Contraseña requerida'); return; }

  try{
    const j = await apiFetch(buildApiUrl('auth/login.php'), {method:'POST', body: JSON.stringify({email,password})});
    if(!j.ok){ setErr('authErr', j.error||'No se pudo ingresar'); return; }
    toast('Sesión iniciada');
    closeAuthModal();
    await refreshMe();
    await refreshCartCount();
  }catch(e){
    setErr('authErr', e?.message || 'Error de conexión');
  }
}

async function doLogout(){
  const j = await apiFetch(buildApiUrl('auth/logout.php'), {method:'POST', body:'{}'});
  if(j.ok){ toast('Sesión cerrada'); }
  await refreshMe();
  document.getElementById('cartCount').textContent='0';
}

function ensureLoggedOrAuth(pending){
  if (API.me) return true;
  API.pendingAdd = pending || null;
  openAuthModal();
  return false;
}

async function refreshCartCount(){
  if (!API.me){ document.getElementById('cartCount').textContent='0'; return; }
  const j = await apiFetch(buildApiUrl('cart/get.php'), {method:'GET'});
  if (j.ok){
    document.getElementById('cartCount').textContent = (j.cart.item_count||0);
  }
}

async function addToCart(card, qty){
  const payload = {
    id_variedad: parseInt(card.dataset.idvariedad||'0',10),
    referencia: card.dataset.ref || '',
    nombre: card.dataset.nombre || '',
    imagen_url: card.dataset.imagen || '',
    unit_price_clp: parseInt(card.dataset.unitpriceclp||'0',10),
    qty: qty || 1,
  };
  const j = await apiFetch(buildApiUrl('cart/add.php'), {method:'POST', body: JSON.stringify(payload)});
  if(!j.ok){ toast(j.error||'No se pudo agregar'); return; }
  document.getElementById('cartCount').textContent = (j.cart.item_count||0);
  // Si el carrito está abierto, recargar sin refrescar la página
  if(document.getElementById('cartModal').classList.contains('open')){
    document.getElementById('cartTotal').textContent = fmtCLP(j.cart.total_clp||0);
    await loadCart();
  }
  toast('Agregado al carrito');
}

async function addToCartFromCard(card, qty){
  if(!card) return;
  if(!ensureLoggedOrAuth({card, qty})) return;
  await addToCart(card, qty);
}

let __currentCardForBuy = null;

function openProductoModal(card){
  __currentCardForBuy = card;

  const m=document.getElementById('productoModal');
  document.getElementById('mImagen').src=card.dataset.imagen||'';
  document.getElementById('modalTitle').textContent=card.dataset.nombre||'';
  document.getElementById('mRef').textContent='Referencia: '+(card.dataset.ref||'');
  document.getElementById('mStock').textContent='Stock disponible: '+(card.dataset.stock||'');
  const pd=card.dataset.preciodetalle||'';
  const pm=card.dataset.preciomayorista||'';
  const pdEl=document.getElementById('mPrecioDetalle');
  const pmEl=document.getElementById('mPrecioMayorista');
  if(pd){ pdEl.textContent='Precio detalle: $'+pd+' Imp. incl.'; pdEl.style.display='block'; }
  else { pdEl.style.display='none'; }
  pmEl.textContent=pm?('Precio mayorista: $'+pm+' Imp. incl.'):'';
  document.getElementById('mDesc').textContent=card.dataset.descripcion||'Sin descripción';
  document.getElementById('mQty').value='1';
  m.classList.add('open');
  m.setAttribute('aria-hidden','false');
  document.body.style.overflow='hidden';
}
function closeProductoModal(){
  const m=document.getElementById('productoModal');
  m.classList.remove('open');
  m.setAttribute('aria-hidden','true');
  document.body.style.overflow='';
}
document.getElementById('productoModal').addEventListener('click',e=>{
  if(e.target===e.currentTarget)closeProductoModal();
});
document.getElementById('authModal').addEventListener('click',e=>{
  if(e.target===e.currentTarget)closeAuthModal();
});
document.getElementById('cartModal').addEventListener('click',e=>{
  if(e.target===e.currentTarget)closeCartModal();
});
document.addEventListener('keydown',e=>{
  if(e.key==='Escape'){ closeProductoModal(); closeAuthModal(); closeCartModal(); }
});

function chgModalQty(delta){
  const inp=document.getElementById('mQty');
  let v=parseInt(inp.value||'1',10);
  v = isNaN(v)?1:v;
  v += delta;
  if(v<1) v=1;
  inp.value = String(v);
}
async function addToCartCurrent(){
  if(!__currentCardForBuy) return;
  const qty = parseInt(document.getElementById('mQty').value||'1',10) || 1;
  if(!ensureLoggedOrAuth({card: __currentCardForBuy, qty})) return;
  await addToCart(__currentCardForBuy, qty);
  closeProductoModal();
}

function openCartModal(){
  if(!ensureLoggedOrAuth(null)) return;
  const m=document.getElementById('cartModal');
  m.classList.add('open'); m.setAttribute('aria-hidden','false');
  document.body.style.overflow='hidden';
  loadCart();
}
function closeCartModal(){
  const m=document.getElementById('cartModal');
  m.classList.remove('open'); m.setAttribute('aria-hidden','true');
  document.body.style.overflow='';
  setErr('cartErr','');
}

function fmtCLP(n){
  try{ return new Intl.NumberFormat('es-CL', {style:'currency', currency:'CLP', maximumFractionDigits:0}).format(n); }
  catch(e){ return '$'+String(n); }
}

async function loadCart(){
  setErr('cartErr','');
  const j = await apiFetch(buildApiUrl('cart/get.php'), {method:'GET'});
  if(!j.ok){ setErr('cartErr', j.error||'No se pudo cargar carrito'); return; }
  const list = document.getElementById('cartList');
  list.innerHTML = '';
  const items = (j.cart.items||[]);
  if(items.length===0){
    list.innerHTML = '<div class="notice">Tu carrito está vacío.</div>';
    document.getElementById('cartTotal').textContent = fmtCLP(0);
    document.getElementById('cartCount').textContent = '0';
    const btn=document.getElementById('btnGoCheckout');
    if(btn){ btn.disabled=true; btn.style.opacity='0.6'; btn.style.cursor='not-allowed'; }
    return;
  }
  items.forEach(it=>{
    const row = document.createElement('div');
    row.className='cart-item';
    row.innerHTML = `
      <img src="${(it.imagen_url||'https://via.placeholder.com/120?text=Img')}" alt="">
      <div>
        <div class="cart-title">${escapeHtml(it.nombre||'')}</div>
        <div class="cart-sub">Ref: ${escapeHtml(it.referencia||'')} · ${fmtCLP(it.unit_price_clp||0)}</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px;align-items:flex-end">
        <div class="qty-ctl">
          <button type="button" aria-label="menos">−</button>
          <input class="inp" type="number" min="0" value="${it.qty||1}">
          <button type="button" aria-label="más">+</button>
        </div>
        <button class="btn-top" type="button" style="background:#fee2e2;color:#991b1b" aria-label="Eliminar">Eliminar</button>
      </div>
    `;
    const btnMinus=row.querySelectorAll('button')[0];
    const btnPlus=row.querySelectorAll('button')[1];
    const btnDel=row.querySelectorAll('button')[2];
    const inp=row.querySelector('input');
    const itemId = it.item_id;

    const applyQty = async (newQty)=>{
      const jj = await apiFetch(buildApiUrl('cart/update.php'), {method:'POST', body: JSON.stringify({item_id: itemId, qty: newQty})});
      if(!jj.ok){ toast(jj.error||'No se pudo actualizar'); return; }
      document.getElementById('cartCount').textContent = (jj.cart.item_count||0);
      document.getElementById('cartTotal').textContent = fmtCLP(jj.cart.total_clp||0);
      // Si se eliminó el ítem (qty 0) o el server ajustó el carrito, re-render completo
      if (newQty===0) { await loadCart(); return; }
      if ((jj.cart.items||[]).length===0) await loadCart();
    };

    btnMinus.addEventListener('click',()=>{
      let v=parseInt(inp.value||'0',10); v=isNaN(v)?0:v;
      v=Math.max(0,v-1);
      inp.value=String(v);
      applyQty(v);
    });
    btnPlus.addEventListener('click',()=>{
      let v=parseInt(inp.value||'0',10); v=isNaN(v)?0:v;
      v=Math.min(9999,v+1);
      inp.value=String(v);
      applyQty(v);
    });
    inp.addEventListener('change',()=>{
      let v=parseInt(inp.value||'0',10); v=isNaN(v)?0:v;
      v=Math.max(0, Math.min(9999, v));
      inp.value=String(v);
      applyQty(v);
    });
    btnDel.addEventListener('click',async ()=>{
      const jj = await apiFetch(buildApiUrl('cart/remove.php'), {method:'POST', body: JSON.stringify({item_id: itemId})});
      if(!jj.ok){ toast(jj.error||'No se pudo eliminar'); return; }
      toast('Eliminado');
      document.getElementById('cartCount').textContent = (jj.cart.item_count||0);
      document.getElementById('cartTotal').textContent = fmtCLP(jj.cart.total_clp||0);
      loadCart();
    });

    list.appendChild(row);
  });

  document.getElementById('cartTotal').textContent = fmtCLP(j.cart.total_clp||0);
  document.getElementById('cartCount').textContent = String(j.cart.item_count||0);
  const btn=document.getElementById('btnGoCheckout');
  if(btn){ btn.disabled = (j.cart.item_count||0)===0; btn.style.opacity = btn.disabled?'0.6':'1'; btn.style.cursor = btn.disabled?'not-allowed':'pointer'; }
}

function escapeHtml(s){
  return String(s||'').replace(/[&<>"']/g, m=>({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;" }[m]));
}


// Checkout
function bindCheckoutButton(){
  const btn=document.getElementById('btnGoCheckout');
  if(!btn) return;
  btn.addEventListener('click', ()=>{
    if(!ensureLoggedOrAuth(null)) return;
    window.location.href = buildUrl('checkout.php');
  });
}

/* Deep-link ref */
(function(){
  const params=new URLSearchParams(location.search);
  const ref=params.get('ref');
  if(!ref) return;
  if(typeof CSS==='undefined' || typeof CSS.escape!=='function'){
    window.CSS = window.CSS || {};
    CSS.escape = function(s){return (s+'').replace(/"/g,'\\"').replace(/'/g,"\\'");};
  }
  const card=document.querySelector('.producto[data-ref="'+CSS.escape(ref)+'"]');
  if(card){
    openProductoModal(card);
    card.scrollIntoView({behavior:'smooth',block:'center'});
  }
})();

/* Abrir carrito desde checkout (index.php?openCart=1) */
(function(){
  const u = new URL(window.location.href);
  if (u.searchParams.get('openCart') !== '1') return;
  u.searchParams.delete('openCart');
  history.replaceState({}, '', u.toString());
  setTimeout(()=>{ try{ openCartModal(); }catch(e){} }, 150);
})();

/* Abrir modal de login desde checkout (index.php?openAuth=1) */
(function(){
  const u = new URL(window.location.href);
  if (u.searchParams.get('openAuth') !== '1') return;
  u.searchParams.delete('openAuth');
  history.replaceState({}, '', u.toString());
  setTimeout(()=>{ try{ openAuthModal(); }catch(e){} }, 150);
})();

(async function init(){
  initRegionComuna("regRegion","regComuna");
  await refreshMe();
  bindCheckoutButton();
  await refreshCartCount();
})();

document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams(window.location.search);
  if (params.get('openCart') === '1') {
    setTimeout(() => {
      const btn = document.querySelector('#btn-open-cart');
      if (btn) btn.click();
    }, 300);
  }
});
