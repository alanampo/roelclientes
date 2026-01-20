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

  function escapeHtml(s){
    return String(s||'').replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  }

  async function fetchJson(url, opts){
    const r = await fetch(url, Object.assign({credentials:'same-origin'}, opts||{}));
    const j = await r.json().catch(()=>({ok:false,error:'Respuesta inválida'}));
    if(!r.ok && !j.error) j.error = 'HTTP '+r.status;
    return j;
  }

  async function init(){
    const id = Number(window.ORDER_ID||0);
    if(!id){ showAlert('error','Pedido inválido'); return; }

    const me = await fetchJson('api/me.php', {method:'GET'});
    if(!me.ok || !me.logged){ location.href='index.php?openAuth=1'; return; }

    const btnLogout = $('btnLogout');
    if(btnLogout){
      btnLogout.style.display='inline-flex';
      btnLogout.onclick = async ()=>{
        await fetchJson('api/auth/logout.php', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':me.csrf||''}, body:'{}'});
        location.href='index.php';
      };
    }

    const data = await fetchJson('api/order/detail.php?id='+encodeURIComponent(id), {method:'GET'});
    if(!data.ok){ showAlert('error', data.error||'No se pudo cargar pedido'); return; }

    const o = data.order;
    $('headMeta').textContent = (o.created_at||'') + (o.status ? (' · '+o.status) : '');
    const codeText = o.order_code ? ('Código: '+o.order_code+' · ') : '';
    $('headMain').textContent = codeText + 'Pedido ID: '+o.id;

    const itemsWrap = $('items');
    itemsWrap.innerHTML = '';
    (data.items||[]).forEach(it=>{
      const row = document.createElement('div');
      row.className = 'it';
      const img = it.image_url ? `<img src="${escapeHtml(it.image_url)}" alt="">` : '<div style="width:56px;height:56px;border:1px solid var(--border);border-radius:12px"></div>';
      row.innerHTML = `
        ${img}
        <div style="flex:1;min-width:0">
          <div class="name">${escapeHtml(it.name||'')}</div>
          <div class="muted2">${escapeHtml(it.ref||'')} · ${it.qty} x ${clp(it.unit_price_clp)}</div>
        </div>
        <div style="font-weight:900">${clp(it.line_total_clp)}</div>
      `;
      itemsWrap.appendChild(row);
    });

    $('sumBox').style.display = 'block';
    $('sSubtotal').textContent = clp(o.subtotal_clp);
    $('sShipping').textContent = o.shipping_label ? o.shipping_label : 'Por pagar';
    $('sTotal').textContent = clp(o.total_clp);
    $('sNotes').textContent = o.notes ? ('Notas:\n'+o.notes) : '';

    const code = o.order_code || ('Pedido #'+o.id);
    $('btnCopy').onclick = async ()=>{
      try{
        await navigator.clipboard.writeText(code);
        showAlert('success','Copiado: '+code);
      }catch(e){
        showAlert('info','Código: '+code);
      }
    };
  }

  document.addEventListener('DOMContentLoaded', init);
})();