<?php
// catalogo_detalle/checkout.php (v4.5)
// Checkout: genera pedido interno y abre WhatsApp con el detalle.
// Ajustado para calzar con assets/checkout.js + assets/checkout.css.

require __DIR__ . '/api/_bootstrap.php';
header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

$APP = require __DIR__ . '/config/app.php';

$logged = !empty($_SESSION['customer_id']);
$customerName = '';
if ($logged) {
  $db = db();
  $cid = (int)$_SESSION['customer_id'];
  $stmt = $db->prepare('SELECT nombre FROM customers WHERE id = ? LIMIT 1');
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
  <!-- checkout.css incluye layout propio, evitamos styles.css del catálogo para no romper el diseño -->
  <link rel="stylesheet" href="assets/checkout.css" />
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
          <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:10px;">
            <div>
              <div style="font-weight:900">Despacho</div>
              <div class="muted" style="font-size:13px">Retiro en vivero: <strong>gratis</strong> · Envío: <strong>por pagar</strong> (Starken u otro)</div>
            </div>
          </div>
        </div>

        <div class="hr"  style="height:1px;background:var(--border);margin:14px 0"></div>

        <div style="font-weight:900;margin-bottom:8px">Instrucciones para el vendedor</div>
        <textarea id="notes" rows="4" placeholder="Ej: dejar en portería / horario de retiro / etc."></textarea>

        <div class="footer-actions">
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
  <script src="assets/checkout.js?v=4.5"></script>
</body>
</html>
