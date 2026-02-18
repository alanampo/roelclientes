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

  const ERP_COLORS = {
    '-1': '#FA5858', '0': '#D8D8D8', '1': '#FFFF00', '2': '#A9F5BC',
    '3': '#A9D0F5', '4': '#E0B0FF', '5': '#F5BCA9', '6': '#F5E0A9',
    '100': '#EAEAEA', '101': '#EAEAEA', '102': '#EAEAEA', '103': '#EAEAEA',
    '104': '#EAEAEA', '105': '#EAEAEA', '106': '#EAEAEA', '107': '#EAEAEA',
    '108': '#EAEAEA', '109': '#EAEAEA', '110': '#EAEAEA', '111': '#EAEAEA',
    '112': '#EAEAEA', '113': '#EAEAEA',
  };

  function orderCard(o){
    const el = document.createElement('div');
    el.className = 'order-card';
    const code = o.order_code ? ('<span class="badge">'+escapeHtml(o.order_code)+'</span>') : '';

    // Badge de estado con colores ERP
    let badgeBg;
    if (o.erp_estado != null) {
      badgeBg = ERP_COLORS[String(o.erp_estado)] || '#EAEAEA';
    } else {
      const fallback = {'paid':'#d4edda','pending':'#fff3cd','failed':'#f8d7da','refunded':'#e2e3e5'};
      badgeBg = fallback[o.payment_status] || fallback.pending;
    }
    const statusBadge = `<span class="badge" style="background:${badgeBg};color:#111">${escapeHtml(o.status || 'Pendiente')}</span>`;

    // Label de envío
    const shippingLabel = o.shipping_label ? `<div class="muted2" style="margin-top:4px"><small>📦 ${escapeHtml(o.shipping_label)}</small></div>` : '';

    el.innerHTML = `
      <div class="order-top">
        <div>
          <div style="font-weight:900">Reserva #${o.id}</div>
          <div class="muted2">${escapeHtml(o.created_at || '')}</div>
          ${shippingLabel}
        </div>
        <div style="text-align:right">
          ${code}
          ${statusBadge}
          <div style="font-weight:900;margin-top:6px">${clp(o.total_clp)}</div>
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
    if(!list.ok){ showAlert('error', list.error||'No se pudo cargar compras'); $('ordersMeta').textContent=''; return; }

    $('ordersMeta').textContent = 'Compras encontradas: '+(list.orders?.length||0);
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