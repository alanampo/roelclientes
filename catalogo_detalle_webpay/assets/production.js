
// assets/production.js (v7.3)
// Fix: IDs alignment + field mapping + rendering list

(async function(){
  const me = await fetch(buildApiUrl('me.php')).then(r=>r.json()).catch(()=>({logged:false}));
  if(!me.logged){
    const rt = encodeURIComponent('produccion.php');
    location.href = buildUrl('index.php?openAuth=1&return_to='+rt);
    return;
  }

  const q = document.getElementById('q');
  const list = document.getElementById('list');
  const empty = document.getElementById('empty');
  const kSel = document.getElementById('kSel');
  const kUnits = document.getElementById('kUnits');

  let items = [];
  let selected = {};

  function render(){
    list.innerHTML = '';
    const term = (q.value||'').toLowerCase();
    const filtered = items.filter(i => i.nombre.toLowerCase().includes(term));
    if(!filtered.length){ empty.style.display='block'; return; }
    empty.style.display='none';

    filtered.forEach(i=>{
      const card = document.createElement('div');
      card.className = 'prod-card';
      card.innerHTML = `
        <div class="prod-name">${i.nombre}</div>
        <div class="prod-price">$${Number(i.precio_mayorista).toLocaleString('es-CL')}</div>
        <input type="number" min="0" step="1" value="${selected[i.id]||0}" />
      `;
      const inp = card.querySelector('input');
      inp.oninput = ()=>{
        const v = parseInt(inp.value||0);
        if(v>0) selected[i.id]=v; else delete selected[i.id];
        updateKpis();
      };
      list.appendChild(card);
    });
  }

  function updateKpis(){
    const ids = Object.keys(selected);
    const total = ids.reduce((a,id)=>a+selected[id],0);
    kSel.textContent = ids.length;
    kUnits.textContent = total;
  }

  async function load(){
    const res = await fetch(buildApiUrl('production/list.php')).then(r=>r.json());
    if(!res.ok){ empty.style.display='block'; return; }
    items = res.items.map(x=>({
      id: x.id,
      nombre: x.nombre,
      precio_mayorista: x.precio_mayorista_clp ?? x.precio_mayorista ?? 0
    }));
    render();
  }

  q && q.addEventListener('input', render);
  load();
})();
