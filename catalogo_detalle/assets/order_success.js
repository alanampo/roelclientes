// catalogo_detalle/assets/order_success.js
(function () {
  const $ = (id) => document.getElementById(id);

  const alertBox = $('alertBox');
  const orderDetails = $('orderDetails');
  const btnWhatsApp = $('btnWhatsApp');
  const btnCopy = $('btnCopy');

  function showAlert(msg, type) {
    if (!alertBox) return;
    alertBox.classList.remove('hidden');
    alertBox.classList.remove('danger', 'info', 'success');
    alertBox.classList.add(type || 'info');
    alertBox.textContent = msg;
  }

  function fmtCLP(n) {
    const x = Number(n || 0);
    return x.toLocaleString('es-CL', { style: 'currency', currency: 'CLP', maximumFractionDigits: 0 });
  }

  function getQS(name) {
    const u = new URL(window.location.href);
    return u.searchParams.get(name) || '';
  }

  function loadPayload() {
    try {
      const raw = sessionStorage.getItem('roel_last_order');
      if (raw) return JSON.parse(raw);
    } catch (e) {}
    // Fallback mínimo desde querystring
    return {
      order_id: getQS('order_id'),
      order_code: getQS('order_code'),
      total_clp: 0,
      shipping_label: 'por pagar',
      packing_cost_clp: 0,
      packing_label: '',
      whatsapp_url: '',
    };
  }

  function render(p) {
    const code = p.order_code ? String(p.order_code) : '';
    const pid = p.order_id ? String(p.order_id) : '';
    const total = fmtCLP(p.total_clp || 0);

    const pack = (p.packing_cost_clp && Number(p.packing_cost_clp) > 0)
      ? `${fmtCLP(p.packing_cost_clp)} (${p.packing_label || ''})`
      : '—';

    orderDetails.innerHTML = `
      <div style="display:grid;grid-template-columns:1fr;gap:8px">
        <div><strong>Código:</strong> <span id="codeSpan">${code || '—'}</span></div>
        <div><strong>Pedido ID:</strong> ${pid || '—'}</div>
        <div><strong>Total:</strong> ${total}</div>
        <div><strong>Envío:</strong> por pagar</div>
        <div><strong>Packing:</strong> ${pack}</div>
      </div>
    `;

    if (!p.whatsapp_url) {
      btnWhatsApp.disabled = true;
      btnWhatsApp.textContent = 'WhatsApp no configurado';
    } else {
      btnWhatsApp.disabled = false;
      btnWhatsApp.textContent = 'Abrir WhatsApp';
      // Auto-abrir 1 vez (si el navegador lo permite)
      try {
        const key = 'roel_wa_opened_' + (p.order_code || p.order_id || '');
        if (!sessionStorage.getItem(key)) {
          sessionStorage.setItem(key, '1');
          window.open(p.whatsapp_url, '_blank');
        }
      } catch (e) {}
    }

    btnWhatsApp.addEventListener('click', () => {
      if (p.whatsapp_url) window.open(p.whatsapp_url, '_blank');
    });

    btnCopy.addEventListener('click', async () => {
      const text = code || pid || '';
      if (!text) return showAlert('No hay código para copiar.', 'info');
      try {
        await navigator.clipboard.writeText(text);
        showAlert('Código copiado.', 'success');
      } catch (e) {
        showAlert('No se pudo copiar. Copia manualmente el código.', 'info');
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    const payload = loadPayload();
    if (!payload || (!payload.order_id && !payload.order_code)) {
      orderDetails.textContent = 'No se encontró información del pedido.';
      showAlert('No se encontró el detalle. Si vienes desde el checkout, vuelve a intentar generar el pedido.', 'info');
      btnWhatsApp.disabled = true;
      return;
    }
    render(payload);
  });
})();
