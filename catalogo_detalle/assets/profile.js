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
  function hideAlert(){ if(alertBox){ alertBox.classList.add('hidden'); alertBox.textContent=''; } }

  async function fetchJson(url, opts){
    const r = await fetch(url, Object.assign({credentials:'same-origin'}, opts||{}));
    const j = await r.json().catch(()=>({ok:false,error:'Respuesta inválida'}));
    if(!r.ok && !j.error) j.error = 'HTTP '+r.status;
    return j;
  }

  function fillRegiones(selected){
    const sel = $('pRegion');
    sel.innerHTML = '<option value="">Selecciona Región</option>';
    const data = window.ROEL_LOCATIONS;
    if(!data || !data.regiones) return;
    data.regiones.forEach(r=>{
      const o=document.createElement('option');
      o.value=r.nombre; o.textContent=r.nombre;
      if(selected && selected===r.nombre) o.selected=true;
      sel.appendChild(o);
    });
  }

function fillComunas(regionName, selected){
    if(!window.ROEL_LOCATIONS) return;
    const sel = $('pComuna');
    sel.innerHTML = '<option value="">Selecciona Comuna</option>';
    const region = window.ROEL_LOCATIONS.regiones.find(r=>r.nombre===regionName);
    if(!region){ sel.disabled = true; return; }

    // Normalizar selected para comparación case-insensitive
    const selectedNormalized = selected ? selected.toLowerCase().trim() : '';

    region.comunas.forEach(c=>{
      const o=document.createElement('option'); o.value=c; o.textContent=c;
      // Comparación case-insensitive
      if(selectedNormalized && c.toLowerCase().trim() === selectedNormalized) {
        o.selected=true;
      }
      sel.appendChild(o);
    });
    sel.disabled = false;
  }

  async function init(){
    hideAlert();
    const me = await fetchJson(buildApiUrl('me.php'), {method:'GET'});
    if(!me.ok || !me.logged){
      location.href = buildUrl('index.php?openAuth=1');
      return;
    }
    const btnLogout = $('btnLogout');
    if(btnLogout){
      btnLogout.style.display='inline-flex';
      btnLogout.onclick = async ()=>{
        await fetchJson(buildApiUrl('auth/logout.php'), {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':me.csrf||''}, body:'{}'});
        location.href=buildUrl('index.php');
      };
    }

    const prof = await fetchJson(buildApiUrl('customer/profile.php'), {method:'GET'});
    if(!prof.ok){ showAlert('error', prof.error||'No se pudo cargar el perfil'); return; }

    const c = prof.customer;
    $('profileMeta').textContent = 'Cliente ID: '+c.id;
    $('pRut').value = c.rut || '';
    $('pEmail').value = c.email || '';
    $('pNombre').value = c.nombre || '';
    $('pTelefono').value = c.telefono || '';

    // Cargar regiones y seleccionar la del cliente
    fillRegiones(c.region || '');

    // Cargar comunas de la región del cliente y preseleccionar la comuna
    if (c.region) {
      fillComunas(c.region, c.comuna || '');
    }

    $('pRegion').onchange = ()=>{ fillComunas(($('pRegion').value||''), ''); };

    $('btnSave').onclick = async ()=>{
      hideAlert();
      const payload = {
        nombre: ($('pNombre').value||'').trim(),
        telefono: ($('pTelefono').value||'').trim(),
        region: ($('pRegion').value||'').trim(),
        comuna: ($('pComuna').value||'').trim(),
        current_password: ($('pCurrentPass').value||''),
        new_password: ($('pNewPass').value||'')
      };
      const res = await fetchJson(buildApiUrl('customer/update.php'), {
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-Token':me.csrf||''},
        body: JSON.stringify(payload)
      });
      if(!res.ok){ showAlert('error', res.error||'No se pudo guardar'); return; }
      $('pCurrentPass').value=''; $('pNewPass').value='';
      showAlert('success','Datos actualizados correctamente.');
    };
  }

  document.addEventListener('DOMContentLoaded', init);
})();