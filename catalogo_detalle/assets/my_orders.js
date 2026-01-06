(function(){
  'use strict';
  const $ = (id)=>document.getElementById(id);
  const alertBox = $('alertBox');

  function showAlert(type, msg){
    if(!alertBox) return;
    alertBox.dataset.type = type;
    alertBox.textContent = msg;
    alertBox.classList.remove('hidden');
    window.scrollTo({top:0, behavior:'smooth'});
  }

  function clp(n){
    try{ return '$'+Number(n||0).toLocaleString('es-CL'); }catch(e){ return '$'+(n||0); }
  }

  async function fetchJson(url, opts){
    const r = await fetch(url, Object.assign({credentials:'same-origin'}, opts||{}));
    const j = await r.json().catch(()=>({ok:false,error:'Respuesta inválida'}));
    if(!r.ok && !j.error) j.error = 'HTTP '+r.status;
    return j;
  }

  function orderCard(o){
    const el = document.createElement('div');
    el.className = 'order-card';
    const code = o.order_code ? ('<span class="badge">Código: '+escapeHtml(o.order_code)+'</span>') : '';
    el.innerHTML = `
      <div class="order-top">
        <div>
          <div style="font-weight:900">Pedido #${o.id}</div>
          <div class="muted2">${escapeHtml(o.created_at || '')}</div>
        </div>
        <div style="text-align:right">
          ${code}
          <div style="font-weight:900;margin-top:6px">${clp(o.total_clp)}</div>
          <div class="muted2">${escapeHtml(o.status || '')}</div>
        </div>
      </div>
      <div class="row-actions">
        <a class="btn btn-primary" href="${buildUrl('order_detail.php?id='+o.id)}">Ver detalle</a>
      </div>
    `;
    return el;
  }

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  async function init(){
    const me = await fetchJson(buildApiUrl('me.php'), {method:'GET'});
    if(!me.ok || !me.logged){ location.href=buildUrl('index.php?openAuth=1'); return; }

    const btnLogout = $('btnLogout');
    if(btnLogout){
      btnLogout.style.display='inline-flex';
      btnLogout.onclick = async ()=>{
        await fetchJson(buildApiUrl('auth/logout.php'), {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':me.csrf||''}, body:'{}'});
        location.href=buildUrl('index.php');
      };
    }

    const list = await fetchJson(buildApiUrl('order/list.php'), {method:'GET'});
    if(!list.ok){ showAlert('error', list.error||'No se pudo cargar pedidos'); $('ordersMeta').textContent=''; return; }

    $('ordersMeta').textContent = 'Pedidos encontrados: '+(list.orders?.length||0);
    const wrap = $('ordersList');
    wrap.innerHTML = '';
    if(!list.orders || list.orders.length===0){
      $('ordersEmpty').style.display='block';
      return;
    }
    list.orders.forEach(o=> wrap.appendChild(orderCard(o)));
  }

  document.addEventListener('DOMContentLoaded', init);
})();