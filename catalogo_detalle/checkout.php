<?php
// catalogo_detalle/checkout.php (v4.5)
// Checkout: genera pedido interno y abre WhatsApp con el detalle.
// Ajustado para calzar con assets/checkout.js + assets/checkout.css.

require __DIR__ . '/config/routes.php';
require __DIR__ . '/api/_bootstrap.php';
header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

$APP = require __DIR__ . '/config/app.php';

$logged = !empty($_SESSION['customer_id']);
$customerName = '';
if ($logged) {
  $db = db();
  $cid = (int)$_SESSION['customer_id'];
  $stmt = $db->prepare('SELECT nombre FROM clientes WHERE id_cliente = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $customerName = (string)($row['nombre'] ?? '');
    $stmt->close();
  }
}

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Checkout - Roelplant</title>
  <!-- Choices.js para selects searchables -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css" />
  <!-- checkout.css incluye layout propio, evitamos styles.css del catálogo para no romper el diseño -->
  <link rel="stylesheet" href="<?php echo htmlspecialchars(buildUrl('assets/checkout.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <style>
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    /* Ajustes de estilo para Choices.js */
    .choices {
      width: 100%;
    }
    .choices__inner {
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 3px;
      background-color: white;
    }
    .choices__list--single {
      display: flex;
      padding: 0;
    }
    .choices__item {
      padding: 0;
      margin: 0;
    }
    .choices__button {
      padding: 0 4px;
      margin-left: 4px;
    }
  </style>
</head>
<body>

  <header class="topbar">
    <div class="brand" aria-label="Roelplant">
      <span class="dot" aria-hidden="true"></span>
      <span>Roelplant</span>
    </div>

    <div class="actions">
      <a class="btn" href="index.php">Volver al carrito</a>
      <a class="btn" href="index.php">Seguir comprando</a>

      <span id="helloName" class="muted" style="font-weight:800"></span>
      <button id="btnGoLogin" class="btn btn-primary" type="button">Ingresar</button>
      <button id="btnLogout" class="btn btn-danger hidden" type="button">Salir</button>
    </div>
  </header>

  <main class="container">
    <h1 class="h1">Checkout</h1>
    <p class="subtitle">Revisa tu carrito y genera el pedido. El envío es <strong>por pagar</strong> al courier.</p>

    <div id="alertBox" class="alert hidden" data-type="info"></div>

    <div class="grid">
      <!-- Carrito -->
      <section class="card">
        <h2>Detalle del carrito</h2>
        <div class="muted" id="cartMeta" style="margin:-6px 0 12px">&nbsp;</div>

        <div id="cartEmpty" class="muted hidden" style="padding:10px 0">Tu carrito está vacío.</div>
        <div class="cart-items" id="cartItems"></div>

        <div class="summary">
          <div class="sumrow"><span class="muted">Subtotal</span><strong id="sumSubtotal">$0</strong></div>
          <div class="sumrow"><span class="muted">Packing</span><strong id="sumPacking">$0</strong></div>
          <div class="muted" id="sumBoxLabel" style="margin:-4px 0 10px 0;font-size:12px"></div>
          <div class="sumrow"><span class="muted">Envío</span><strong id="sumShipping">Por pagar</strong></div>
          <div class="sumrow"><span class="muted">Total</span><strong id="sumTotal">$0</strong></div>
          <div class="muted" style="margin-top:10px; font-size:13px;">
            Nota: el costo de envío se paga al courier (Starken u otro), según volumen/peso y destino.
          </div>
        </div>
      </section>

      <!-- Confirmación -->
      <section class="card">
        <h2>Confirmación</h2>
        <div class="muted" style="margin:-6px 0 12px">Datos del cliente y despacho</div>

        <div id="customerBox" class="muted">Cargando cliente...</div>

        <div class="ship">
          <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:10px;margin-bottom:14px">
            <div style="width:100%">
              <div style="font-weight:900">Método de Entrega</div>
              <div id="shippingMethods" style="margin-top:8px">
                <label style="display:flex;align-items:center;gap:8px;margin:6px 0;cursor:pointer">
                  <input type="radio" name="shipping_method" value="domicilio" checked style="cursor:pointer" />
                  <span>Envío a Domicilio</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;margin:6px 0;cursor:pointer">
                  <input type="radio" name="shipping_method" value="agencia" style="cursor:pointer" />
                  <span>Retiro en Sucursal Starken</span>
                </label>
                <label style="display:flex;align-items:center;gap:8px;margin:6px 0;cursor:pointer">
                  <input type="radio" name="shipping_method" value="vivero" style="cursor:pointer" />
                  <span>Retiro en Vivero (Gratis)</span>
                </label>
              </div>

              <!-- Formulario de dirección para Envío a Domicilio -->
              <div id="shippingAddressForm" style="margin-top:14px;padding:12px;background:#f5f5f5;border-radius:4px;display:none">
                <div style="margin-bottom:10px">
                  <label style="display:block;margin-bottom:4px;font-weight:600">Dirección de Entrega</label>
                  <input type="text" id="shippingAddress" placeholder="Calle, número, depto" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box" />
                </div>
                <div style="margin-bottom:10px">
                  <label style="display:block;margin-bottom:4px;font-weight:600">Comuna</label>
                  <select id="shippingCommune" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box">
                    <option value="">Seleccionar comuna...</option>
                  </select>
                </div>
                <button id="btnQuoteShipping" type="button" style="width:100%;padding:10px;background:#0066cc;color:white;border:none;border-radius:3px;cursor:pointer;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px">
                  <span id="btnQuoteShippingText">Cotizar Envío</span>
                  <span id="btnQuoteShippingSpinner" style="display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top:2px solid white;border-radius:50%;animation:spin 0.8s linear infinite"></span>
                </button>
              </div>

              <!-- Select de sucursales para Retiro en Sucursal -->
              <div id="shippingAgenciesForm" style="margin-top:14px;padding:12px;background:#f5f5f5;border-radius:4px;display:none">
                <div style="margin-bottom:10px">
                  <label style="display:block;margin-bottom:4px;font-weight:600">Selecciona una Sucursal</label>
                  <select id="shippingAgency" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box">
                    <option value="">Cargando sucursales...</option>
                  </select>
                </div>
              </div>

              <div id="shippingInfo" class="muted" style="font-size:13px;margin-top:10px"></div>
            </div>
          </div>
        </div>

        <div class="hr"  style="height:1px;background:var(--border);margin:14px 0"></div>

        <div style="font-weight:900;margin-bottom:8px">Instrucciones para el vendedor</div>
        <textarea id="notes" rows="4" placeholder="Ej: dejar en portería / horario de retiro / etc."></textarea>

        <div class="footer-actions">
          <button id="btnMakeReservation" class="btn btn-success" type="button">Pagar</button>
          <button id="btnCreateOrder" class="btn btn-primary" type="button">Enviar pedido por WhatsApp</button>
        </div>

        <div id="orderResult" class="muted hidden" style="margin-top:10px"></div>
      </section>
    </div>
  </main>

  <script>
    window.ROEL_CHECKOUT = {
      shipping_options: <?= json_encode($APP['SHIPPING_OPTIONS'] ?? ($APP['shipping_options'] ?? []), JSON_UNESCAPED_UNICODE) ?>,
      logged: <?= $logged ? 'true' : 'false' ?>,
      customer_name: <?= json_encode($customerName, JSON_UNESCAPED_UNICODE) ?>
    };
  </script>
  <!-- Choices.js para selects searchables -->
  <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
  <script src="<?php echo htmlspecialchars(buildUrl('assets/checkout.js?v=4.5'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
