// catalogo_detalle/assets/checkout.js (v4.5)

(function () {
  const state = {
    csrf: '',
    me: null,
    shippingCode: 'auto',
    packingCost: 0,
    packingLabel: '',
    shippingMethod: 'domicilio',
    shippingCost: 0,
    shippingLabel: 'Por pagar',
    shippingQuoted: false,  // Si ya se ejecutó la cotización
    cartDestination: 'Santiago',
    shippingAddress: '',
    shippingCommune: '',
    communes: [],
    agencies: [],
    shippingAgency: '',
    canPayButton: false,  // Si el botón pagar está habilitado
  };

  const $ = (id) => document.getElementById(id);

  const alertBox = $('alertBox');
  const helloName = $('helloName');
  const btnLogin = $('btnGoLogin');
  const btnLogout = $('btnLogout');

  const cartMeta = $('cartMeta');
  const cartItems = $('cartItems');
  const cartEmpty = $('cartEmpty');
  const sumSubtotal = $('sumSubtotal');
  const sumPacking = $('sumPacking');
  const sumBoxLabel = $('sumBoxLabel');
  const sumTotal = $('sumTotal');

  const customerBox = $('customerBox');
  const notesEl = $('notes');
  const btnMakeReservation = $('btnMakeReservation');
  const btnCreateOrder = $('btnCreateOrder');
  const orderResult = $('orderResult');

  // Shipping form elements
  const shippingAddressForm = $('shippingAddressForm');
  const shippingAddressInput = $('shippingAddress');
  const shippingCommuneSelect = $('shippingCommune');
  const btnQuoteShipping = $('btnQuoteShipping');
  const shippingAgenciesForm = $('shippingAgenciesForm');
  const shippingAgencySelect = $('shippingAgency');

  function fmtCLP(n) {
    const v = Number(n || 0);
    return '$' + Math.round(v).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function packingForQty(qtyTotal) {
    const q = Number(qtyTotal || 0);
    if (q <= 0) return { cost: 0, label: '' };
    if (q <= 50) return { cost: 2500, label: 'caja chica (1-50)' };
    if (q <= 100) return { cost: 4000, label: 'caja mediana (51-100)' };
    const packs = Math.ceil(q / 100);
    return { cost: 4500 * packs, label: `caja grande x${packs} (cada 100 unid.)` };
  }

  // Especificaciones de cajas (IDÉNTICAS al backend)
  const BOX_SPECS = {
    pequeña: { height: 13, width: 29, depth: 27, weight: 1.0 },
    mediana: { height: 13, width: 29, depth: 54, weight: 2.5 },
    grande: { height: 26, width: 29, depth: 54, weight: 3.0 }
  };

  // Calcular dimensiones y peso (MISMA LÓGICA que backend)
  function calculateShippingDimensions(qtyTotal) {
    const q = Number(qtyTotal || 0);

    if (q <= 0) {
      return { boxType: '', boxCount: 0, weight: 0, height: 0, width: 0, depth: 0 };
    }

    let boxType, boxCount;

    if (q <= 50) {
      boxType = 'pequeña';
      boxCount = 1;
    } else if (q <= 100) {
      boxType = 'mediana';
      boxCount = 1;
    } else {
      boxType = 'grande';
      boxCount = Math.ceil(q / 100);
    }

    const specs = BOX_SPECS[boxType];
    const totalWeight = specs.weight * boxCount;
    const totalHeight = specs.height * boxCount;
    const totalWidth = specs.width;
    const totalDepth = specs.depth;

    return {
      boxType,
      boxCount,
      weight: totalWeight,
      height: totalHeight,
      width: totalWidth,
      depth: totalDepth
    };
  }


  function showAlert(msg, type = 'info') {
    if (!alertBox) return;
    alertBox.textContent = msg;
    alertBox.dataset.type = type;
    alertBox.classList.remove('hidden');
  }

  function hideAlert() {
    if (!alertBox) return;
    alertBox.classList.add('hidden');
  }

  async function loadCommunes() {
    try {
      const j = await fetchJson(buildApiUrl('shipping/starken_communes.php'), { method: 'GET' });
      if (j.ok && j.communes) {
        state.communes = j.communes;
        populateCommuneSelect();
      }
    } catch (e) {
      console.error('Error loading communes:', e.message);
    }
  }

  function populateCommuneSelect() {
    if (!shippingCommuneSelect || state.communes.length === 0) return;

    // Clear all options and rebuild
    shippingCommuneSelect.innerHTML = '<option value="">Seleccionar comuna...</option>';

    // Add commune options
    state.communes.forEach(comm => {
      const opt = document.createElement('option');
      opt.value = comm.code_dls;
      opt.textContent = comm.name + (comm.city_name ? ` (${comm.city_name})` : '');
      shippingCommuneSelect.appendChild(opt);
    });

    // Reinitialize Choices if already initialized
    if (window.communesChoices) {
      window.communesChoices.destroy();
      window.communesChoices = new Choices(shippingCommuneSelect, { searchEnabled: true, itemSelectText: '' });
    }
  }

  function showShippingForm() {
    const method = document.querySelector('input[name="shipping_method"]:checked')?.value || 'domicilio';

    // Hide all forms first
    shippingAddressForm.style.display = 'none';
    shippingAgenciesForm.style.display = 'none';

    if (method === 'domicilio') {
      shippingAddressForm.style.display = 'block';
      state.shippingQuoted = false;
    } else if (method === 'agencia') {
      shippingAgenciesForm.style.display = 'block';
      state.shippingAgency = '';
      state.shippingQuoted = false;  // Requiere cotizar
      if (shippingAgencySelect) shippingAgencySelect.value = '';
    } else if (method === 'vivero') {
      state.shippingQuoted = true;  // Vivero es "autoaprobado"
    }

    updatePayButtonState();
  }

  async function loadAgencies() {
    try {
      const communeCode = shippingCommuneSelect?.value || '';

      const j = await fetchJson(buildApiUrl('shipping/starken_agencies.php'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrf,
        },
        body: JSON.stringify({
          commune_code_dls: Number(communeCode) || 1
        })
      });

      if (j.ok && j.agencies) {
        state.agencies = j.agencies;
        populateAgenciesSelect();
      }
    } catch (e) {
      console.error('Error loading agencies:', e.message);
    }
  }

  function populateAgenciesSelect() {
    if (!shippingAgencySelect || state.agencies.length === 0) return;

    // Clear all options and rebuild
    shippingAgencySelect.innerHTML = '<option value="">Seleccionar sucursal...</option>';

    // Add agency options
    state.agencies.forEach(agency => {
      const opt = document.createElement('option');
      opt.value = agency.code_dls;
      opt.textContent = agency.name + (agency.address ? ` - ${agency.address}` : '');
      shippingAgencySelect.appendChild(opt);
    });

    // Reinitialize Choices if already initialized
    if (window.agenciesChoices) {
      window.agenciesChoices.destroy();
      window.agenciesChoices = new Choices(shippingAgencySelect, { searchEnabled: true, itemSelectText: '' });
    }
  }

  function updatePayButtonState() {
    const method = state.shippingMethod;

    // Vivero: siempre se puede pagar
    if (method === 'vivero') {
      state.canPayButton = true;
      if (btnMakeReservation) btnMakeReservation.disabled = false;
      return;
    }

    // Domicilio: solo si se cotizó
    if (method === 'domicilio') {
      state.canPayButton = state.shippingQuoted;
      if (btnMakeReservation) btnMakeReservation.disabled = !state.shippingQuoted;
      return;
    }

    // Agencia: solo si se seleccionó sucursal Y se cotizó
    if (method === 'agencia') {
      state.canPayButton = state.shippingAgency !== '' && state.shippingQuoted;
      if (btnMakeReservation) btnMakeReservation.disabled = !(state.shippingAgency && state.shippingQuoted);
      return;
    }

    state.canPayButton = false;
    if (btnMakeReservation) btnMakeReservation.disabled = true;
  }

  async function quoteShipping() {
    const address = (shippingAddressInput?.value || '').trim();
    const commune = shippingCommuneSelect?.value || '';

    if (!address || !commune) {
      showAlert('Completa dirección y comuna antes de cotizar', 'warning');
      return;
    }

    try {
      // Obtener carrito para calcular cantidad total
      const cartResp = await fetchJson(buildApiUrl('cart/get.php'), { method: 'GET' });
      const cart = cartResp.cart || {};
      const items = cart.items || [];
      const qtyTotal = items.reduce((acc, it) => acc + Number(it.qty || 0), 0);

      // Calcular dimensiones localmente (rápido, sin esperar al backend)
      const dimensions = calculateShippingDimensions(qtyTotal);

      // Origen: usar domicilio2 (code_dls de la comuna del cliente)
      const originCommuneCodeDls = Number(2735) || 1;
      // Destino: usar la comuna seleccionada
      const destinationCommuneCodeDls = Number(commune) || 1;

      // Enviar dimensiones calculadas al backend para cotizar con Starken
      const response = await fetchJson(buildApiUrl('shipping/starken_quote.php'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrf,
        },
        body: JSON.stringify({
          destination: destinationCommuneCodeDls,
          origin: originCommuneCodeDls,
          quantity: qtyTotal,
          weight: dimensions.weight,
          height: dimensions.height,
          width: dimensions.width,
          depth: dimensions.depth
        })
      });

      if (response.ok && response.data && response.data.alternativas) {
        const options = response.data.alternativas;
        if (options.length > 0) {
          const firstOption = options[0];
          state.shippingCost = Math.ceil(Number(firstOption.precio || firstOption.valor || 0));
          state.shippingLabel = `Envío a domicilio (Starken) - ${dimensions.boxCount} caja(s) ${dimensions.boxType}`;
          state.shippingQuoted = true;
          showAlert('Cotización obtenida: ' + fmtCLP(state.shippingCost), 'success');
        } else {
          showAlert('No hay opciones de envío disponibles', 'warning');
        }
      } else {
        throw new Error(response.error || 'Error en cotización');
      }
    } catch (e) {
      showAlert(`Error cotizando: ${String(e.message || e)}`, 'danger');
      state.shippingQuoted = false;
    }

    updatePayButtonState();
    updateShippingDisplay();
  }

  async function quoteAgencyShipping() {
    const selectedAgencyCode = shippingAgencySelect?.value || '';
    const commune = shippingCommuneSelect?.value || '';

    if (!selectedAgencyCode || !commune) {
      showAlert('Selecciona una sucursal antes de cotizar', 'warning');
      return;
    }

    // Buscar la agencia seleccionada para obtener city_code_dls
    const agency = state.agencies.find(a => String(a.code_dls) === String(selectedAgencyCode));
    if (!agency || !agency.city_code_dls) {
      showAlert('No se pudo obtener información de la sucursal', 'danger');
      return;
    }

    try {
      // Obtener carrito para calcular cantidad total
      const cartResp = await fetchJson(buildApiUrl('cart/get.php'), { method: 'GET' });
      const cart = cartResp.cart || {};
      const items = cart.items || [];
      const qtyTotal = items.reduce((acc, it) => acc + Number(it.qty || 0), 0);

      // Calcular dimensiones localmente (rápido, sin esperar al backend)
      const dimensions = calculateShippingDimensions(qtyTotal);

      // Origen: usar domicilio2 (code_dls de la comuna del cliente)
      const originCommuneCodeDls = Number(2735) || 1;
      // Destino: usar la ciudad de la sucursal seleccionada (ya que es un city_code_dls)
      const destinationCityCodeDls = Number(agency.city_code_dls) || 1;

      // Enviar dimensiones calculadas al backend para cotizar con Starken
      const response = await fetchJson(buildApiUrl('shipping/starken_quote.php'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrf,
        },
        body: JSON.stringify({
          destination: destinationCityCodeDls,
          origin: originCommuneCodeDls,
          quantity: qtyTotal,
          weight: dimensions.weight,
          height: dimensions.height,
          width: dimensions.width,
          depth: dimensions.depth
        })
      });

      if (response.ok && response.data && response.data.alternativas) {
        const options = response.data.alternativas;
        if (options.length > 0) {
          const firstOption = options[0];
          state.shippingCost = Math.ceil(Number(firstOption.precio || firstOption.valor || 0));
          state.shippingLabel = `Retiro en sucursal Starken (${agency.name}) - ${dimensions.boxCount} caja(s) ${dimensions.boxType}`;
          state.shippingQuoted = true;
          showAlert('Cotización obtenida: ' + fmtCLP(state.shippingCost), 'success');
        } else {
          showAlert('No hay opciones de envío disponibles', 'warning');
        }
      } else {
        throw new Error(response.error || 'Error en cotización');
      }
    } catch (e) {
      showAlert(`Error cotizando: ${String(e.message || e)}`, 'danger');
      state.shippingQuoted = false;
    }

    updatePayButtonState();
    updateShippingDisplay();
  }

  async function updateCustomerShippingAddress() {
    if (state.shippingMethod !== 'domicilio') return;

    const address = (shippingAddressInput?.value || '').trim();
    const commune = shippingCommuneSelect?.value || '';

    if (!address || !commune) {
      return; // Not ready yet
    }

    try {
      await fetchJson(buildApiUrl('customer/update.php'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrf,
        },
        body: JSON.stringify({
          domicilio: address,
          comuna_code_dls: commune,
          t: Date.now(),
        }),
      });

      state.shippingAddress = address;
      state.shippingCommune = commune;

      // Trigger shipping cost update
      await updateShippingCost();
    } catch (e) {
      console.error('Error updating address:', e.message);
    }
  }

  async function updateShippingCost() {
    const method = document.querySelector('input[name="shipping_method"]:checked')?.value || 'domicilio';
    state.shippingMethod = method;

    if (method === 'vivero') {
      state.shippingCost = 0;
      state.shippingLabel = 'Retiro en vivero (Gratis)';
    } else if (method === 'domicilio') {
      // Wait for manual quote button
      state.shippingLabel = 'Haz clic en "Cotizar Envío"';
    } else if (method === 'agencia') {
      // Wait for agency selection
      state.shippingLabel = 'Selecciona una sucursal';
      await loadAgencies();
    }

    updatePayButtonState();
    updateShippingDisplay();
  }

  function updateShippingDisplay() {
    const sumShipping = $('sumShipping');
    const shippingInfo = $('shippingInfo');

    if (sumShipping) {
      sumShipping.textContent = state.shippingCost > 0 ? fmtCLP(state.shippingCost) : 'Por pagar';
    }

    if (shippingInfo) {
      shippingInfo.textContent = state.shippingLabel;
    }

    // Update total
    const sumSubtotalText = (sumSubtotal?.textContent || '').replace(/[^\d]/g, '');
    const subtotal = Number(sumSubtotalText) || 0;
    const total = subtotal + state.packingCost + state.shippingCost;
    if (sumTotal) {
      sumTotal.textContent = fmtCLP(total);
    }
  }

  async function fetchJson(url, opts = {}) {
    const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...opts });
    const txt = await res.text();
    let json = null;
    try { json = JSON.parse(txt); } catch (e) {}
    if (!json) throw new Error(`Respuesta inválida (${res.status}). ${txt.slice(0, 240)}`);
    if (!res.ok || json.ok === false) throw new Error(json.error || `HTTP ${res.status}`);
    return json;
  }

  async function refreshMe() {
    const j = await fetchJson('api/me.php', { method: 'GET' });
    state.csrf = j.csrf || state.csrf;
    state.me = j.logged ? (j.customer || null) : null;

    if (helloName) {
      helloName.textContent = state.me ? (`Hola, ${state.me.nombre || 'Cliente'}`) : '';
    }
    if (btnLogin) btnLogin.classList.toggle('hidden', !!state.me);
    if (btnLogout) btnLogout.classList.toggle('hidden', !state.me);
  }

  async function logout() {
    if (!state.csrf) await refreshMe();
    await fetchJson('api/auth/logout.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': state.csrf,
      },
      body: JSON.stringify({ t: Date.now() }),
    });
    state.me = null;
    showAlert('Sesión cerrada.', 'success');
    await refreshMe();
    await loadCart();
    await loadCustomer();
  }

  function cartItemRow(it) {
    const img = it.imagen_url || '';
    const ref = it.referencia || '';
    const name = it.nombre || '';
    const qty = Number(it.qty || 0);
    const unit = Number(it.unit_price_clp || 0);
    const line = Number(it.line_total_clp || (qty * unit));

    const d = document.createElement('div');
    d.className = 'cart-item';
    d.innerHTML = `
      <img src="${img}" alt="" loading="lazy" />
      <div class="ci-main" style="width: 70%;">
        <div class="ci-title">${name}</div>
        <div class="ci-sub muted">${ref}</div>
      </div>
      <div class="ci-right" style="
          width: 20%;
          text-align: right;
          display: flex;
          justify-content: end;
          gap: 20px;">
        <div class="ci-qty">x${qty}</div>
        <div class="ci-price">${fmtCLP(line)}</div>
      </div>
    `;
    return d;
  }

  async function loadCart() {
    hideAlert();
    cartItems.innerHTML = '';
    cartEmpty.classList.add('hidden');

    if (!state.me) {
      cartMeta.textContent = 'Debes iniciar sesión para ver el carrito.';
      cartEmpty.classList.remove('hidden');
      sumSubtotal.textContent = fmtCLP(0);
      if (sumPacking) sumPacking.textContent = fmtCLP(0);
      if (sumBoxLabel) sumBoxLabel.textContent = '';
      sumTotal.textContent = fmtCLP(0);
      return;
    }

    const j = await fetchJson('api/cart/get.php', { method: 'GET' });
    const cart = j.cart || {};
    const items = cart.items || [];
    const count = Number(cart.item_count || 0);
    const total = Number(cart.total_clp || 0);

    cartMeta.textContent = `${count} item(s) en carrito`;

    if (!items.length) {
      cartEmpty.classList.remove('hidden');
    } else {
      items.forEach((it) => cartItems.appendChild(cartItemRow(it)));
    }

    const qtyTotal = items.reduce((acc,it)=>acc+Number(it.qty||0),0);
    const pack = packingForQty(qtyTotal);
    state.packingCost = pack.cost;
    state.packingLabel = pack.label;
    sumSubtotal.textContent = fmtCLP(total);
    if (sumPacking) sumPacking.textContent = fmtCLP(pack.cost);
    if (sumBoxLabel) sumBoxLabel.textContent = pack.label ? `Caja estimada: ${pack.label}` : '';
    sumTotal.textContent = fmtCLP(total + pack.cost);
  }

  async function loadCustomer() {
    if (!state.me) {
      customerBox.innerHTML = '<span class="muted">Debes iniciar sesión para continuar.</span>';
      return;
    }

    const j = await fetchJson('api/customer/profile.php', { method: 'GET' });
    const c = j.customer || {};

    // Store destination for shipping quotes
    state.cartDestination = c.comuna || 'Santiago';

    customerBox.innerHTML = `
      <div style="font-weight:900;margin-bottom:6px">${c.nombre || 'Cliente'}</div>
      <div class="muted" style="font-size:14px">RUT: ${c.rut || '-'}</div>
      <div class="muted" style="font-size:14px">Teléfono: ${c.telefono || '-'}</div>
      <div class="muted" style="font-size:14px">Email: ${c.email || '-'}</div>
      <div class="muted" style="font-size:14px">Comuna/Región: ${c.comuna || '-'} / ${c.region || '-'}</div>
    `;

    // Load saved shipping address if available
    if (c.domicilio) {
      state.shippingAddress = c.domicilio;
      if (shippingAddressInput) {
        shippingAddressInput.value = c.domicilio;
      }
    }

    // Load communes and set saved value
    await loadCommunes();
    if (c.domicilio2 && shippingCommuneSelect) {
      shippingCommuneSelect.value = c.domicilio2;
      state.shippingCommune = c.domicilio2;
    }

    // Show form if domicilio is selected
    showShippingForm();

    // Update shipping cost based on customer location
    await updateShippingCost();

    // Deshabilitar botón pagar inicialmente
    updatePayButtonState();
  }

  async function makePayment() {
    hideAlert();
    orderResult.classList.add('hidden');
    orderResult.textContent = '';

    if (!state.me) {
      showAlert('Debes iniciar sesión para hacer la compra.', 'danger');
      return;
    }

    if (!state.csrf) await refreshMe();

    try {
      // Preparar payload con datos de envío
      const paymentPayload = {
        shipping_cost: state.shippingCost || 0
      };

      // Crear transacción de pago con Webpay
      const response = await fetchJson(buildApiUrl('payment/webpay_create.php'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrf,
        },
        body: JSON.stringify(paymentPayload),
      });

      if (response.ok && response.redirect_url && response.token) {
        // Crear un form POST para enviar a Webpay (es lo que Webpay espera)
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = response.redirect_url;

        // Agregar token como campo hidden
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'token_ws';
        tokenInput.value = response.token;
        form.appendChild(tokenInput);

        // Enviar form
        document.body.appendChild(form);
        form.submit();
      } else {
        throw new Error(response.error || 'Error iniciando pago. ' + JSON.stringify(response));
      }
    } catch (e) {
      showAlert(`Error al procesar pago: ${String(e.message || e)}`, 'danger');
    }
  }

  async function makeReservation() {
    hideAlert();
    orderResult.classList.add('hidden');
    orderResult.textContent = '';

    if (!state.me) {
      showAlert('Debes iniciar sesión para hacer la compra.', 'danger');
      return;
    }

    if (!state.csrf) await refreshMe();

    // Obtener el carrito actual desde la BD
    const cartResp = await fetchJson(buildApiUrl('cart/get.php'), { method: 'GET' });
    const cart = cartResp.cart || {};
    const items = cart.items || [];

    if (items.length === 0) {
      showAlert('Tu carrito está vacío.', 'warning');
      return;
    }

    // Preparar payload para la reserva
    const reservationItems = items.map(it => ({
      id_variedad: it.id_variedad || 0,
      qty: it.qty || 0,
      nombre: it.nombre || '',
      referencia: it.referencia || '',
      unit_price_clp: it.unit_price_clp || 0,
      comentario: ''
    }));

    const notes = notesEl ? String(notesEl.value || '').trim() : '';

    try {
      const j = await fetchJson(buildApiUrl('order/create_reservation.php'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': state.csrf,
        },
        body: JSON.stringify({
          items: reservationItems,
          notes,
          shipping_method: state.shippingMethod,
          shipping_cost: state.shippingCost,
          t: Date.now(),
        }),
      });

      showAlert('✓ Compra registrada correctamente. Tu reserva ha sido creada y el stock ha sido actualizado.', 'success');

      // Refrescar carrito (debería quedar vacío)
      await loadCart();

      // Limpiar notas
      if (notesEl) notesEl.value = '';
    } catch (e) {
      showAlert(`Error al procesar compra: ${String(e.message || e)}`, 'danger');
    }
  }

  async function createOrder() {
    hideAlert();
    orderResult.classList.add('hidden');
    orderResult.textContent = '';

    if (!state.me) {
      showAlert('Debes iniciar sesión para generar el pedido.', 'danger');
      return;
    }

    if (!state.csrf) await refreshMe();

    const notes = notesEl ? String(notesEl.value || '').trim() : '';

    // Preparar datos de envío según el método seleccionado
    const shippingData = {
      shipping_method: state.shippingMethod,
      shipping_cost: state.shippingCost || 0,
    };

    if (state.shippingMethod === 'domicilio') {
      shippingData.shipping_address = shippingAddressInput?.value || '';
      shippingData.shipping_commune = shippingCommuneSelect?.value || '';
    } else if (state.shippingMethod === 'agencia') {
      // Encontrar la agencia seleccionada en el array
      const selectedAgencyCode = shippingAgencySelect?.value || '';
      const agency = state.agencies.find(a => String(a.code_dls) === String(selectedAgencyCode));
      if (agency) {
        shippingData.shipping_agency_code = agency.code_dls;
        shippingData.shipping_agency_name = agency.name;
        shippingData.shipping_agency_address = agency.address;
        shippingData.shipping_agency_phone = agency.phone;
      }
    } else if (state.shippingMethod === 'vivero') {
      shippingData.shipping_vivero = true;
    }

    const j = await fetchJson('api/order/create.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': state.csrf,
      },
      body: JSON.stringify({
        shipping_code: 'por_pagar',
        packing_cost_clp: state.packingCost || 0,
        packing_label: state.packingLabel || '',
        ...shippingData,
        notes,
        t: Date.now(),
      }),
    });

    // Guardar resultado y pasar a vista de confirmación (salir del checkout)
    try {
      const payload = {
        created_at: Date.now(),
        order_id: j.order_id || null,
        order_code: j.order_code || '',
        total_clp: j.total_clp || 0,
        shipping_label: state.shippingLabel || 'por pagar',
        packing_cost_clp: j.packing_cost_clp || 0,
        packing_label: j.packing_label || '',
        whatsapp_url: j.whatsapp_url || '',
      };
      sessionStorage.setItem('roel_last_order', JSON.stringify(payload));
    } catch (e) {}

    // Ir a confirmación; allí se muestra el detalle y el botón a WhatsApp
    window.location.href = buildUrl(`order_success.php?order_id=${encodeURIComponent(j.order_id||'')}&order_code=${encodeURIComponent(j.order_code||'')}`);
// Refrescar carrito (debería quedar vacío)
    await loadCart();
  }

  function initializeSearchableSelects() {
    // Initialize Choices.js for communes
    if (shippingCommuneSelect && typeof Choices !== 'undefined') {
      window.communesChoices = new Choices(shippingCommuneSelect, {
        searchEnabled: true,
        itemSelectText: '',
        removeItemButton: true,
        placeholder: true,
        placeholderValue: 'Seleccionar comuna...'
      });
    }

    // Initialize Choices.js for agencies
    if (shippingAgencySelect && typeof Choices !== 'undefined') {
      window.agenciesChoices = new Choices(shippingAgencySelect, {
        searchEnabled: true,
        itemSelectText: '',
        removeItemButton: true,
        placeholder: true,
        placeholderValue: 'Seleccionar sucursal...'
      });
    }
  }

  function bindEvents() {
    btnLogin?.addEventListener('click', () => {
      // Redirige al catálogo para login
      const next = buildUrl('checkout.php');
      window.location.href = buildUrl(`index.php?openAuth=1&next=${encodeURIComponent(next)}`);
    });

    btnLogout?.addEventListener('click', () => logout().catch((e) => showAlert(String(e.message || e), 'danger')));

    // Shipping method change listeners
    document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
      radio.addEventListener('change', () => {
        showShippingForm();
        updateShippingCost().catch((e) => showAlert(String(e.message || e), 'warning'));
      });
    });

    // Shipping address change listeners
    if (shippingAddressInput) {
      shippingAddressInput.addEventListener('change', () => {
        updateCustomerShippingAddress().catch((e) => showAlert(String(e.message || e), 'warning'));
      });
    }

    if (shippingCommuneSelect) {
      shippingCommuneSelect.addEventListener('change', () => {
        updateCustomerShippingAddress().catch((e) => showAlert(String(e.message || e), 'warning'));
        // Cargar agencias si estamos en modo agencia
        if (state.shippingMethod === 'agencia') {
          loadAgencies().catch((e) => showAlert(String(e.message || e), 'warning'));
          // Resetear cotización cuando cambia la comuna
          state.shippingQuoted = false;
          updatePayButtonState();
        }
      });
    }

    // Event listener para seleccionar sucursal (cotizar automáticamente)
    if (shippingAgencySelect) {
      shippingAgencySelect.addEventListener('change', () => {
        const selectedCode = shippingAgencySelect.value || '';
        state.shippingAgency = selectedCode;

        if (selectedCode && state.shippingMethod === 'agencia') {
          // Cotizar automáticamente cuando se selecciona una sucursal
          quoteAgencyShipping().catch((e) => showAlert(String(e.message || e), 'danger'));
        } else {
          // Si se deselecciona, resetear estado
          state.shippingQuoted = false;
          updatePayButtonState();
        }
      });
    }

    // Botón Cotizar Envío
    if (btnQuoteShipping) {
      btnQuoteShipping.addEventListener('click', () => {
        btnQuoteShipping.disabled = true;
        const spinner = $('btnQuoteShippingSpinner');
        const text = $('btnQuoteShippingText');
        if (spinner) spinner.style.display = 'block';
        if (text) text.style.display = 'none';

        quoteShipping()
          .catch((e) => showAlert(String(e.message || e), 'danger'))
          .finally(() => {
            btnQuoteShipping.disabled = false;
            if (spinner) spinner.style.display = 'none';
            if (text) text.style.display = 'inline';
          });
      });
    }

    // Select de agencias
    if (shippingAgencySelect) {
      shippingAgencySelect.addEventListener('change', () => {
        state.shippingAgency = shippingAgencySelect.value;
        updatePayButtonState();
      });
    }

    btnMakeReservation?.addEventListener('click', () => {
      btnMakeReservation.disabled = true;
      makePayment()
        .catch((e) => showAlert(String(e.message || e), 'danger'))
        .finally(() => { btnMakeReservation.disabled = false; });
    });

    btnCreateOrder?.addEventListener('click', () => {
      btnCreateOrder.disabled = true;
      createOrder()
        .catch((e) => showAlert(String(e.message || e), 'danger'))
        .finally(() => { btnCreateOrder.disabled = false; });
    });

    // Initialize searchable selects
    initializeSearchableSelects();
  }

  async function init() {
    try {      bindEvents();
      await refreshMe();
      await loadCart();
      await loadCustomer();
    } catch (e) {
      showAlert(String(e.message || e), 'danger');
    }
  }

  document.addEventListener('DOMContentLoaded', init);
})();