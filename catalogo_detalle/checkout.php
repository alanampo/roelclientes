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

    /* ====== Botones Flotantes RRSS ====== */
    .float-social{
      position:fixed;
      bottom:20px;
      right:20px;
      display:flex;
      flex-direction:column;
      gap:12px;
      z-index:999;
    }
    .float-social a{
      display:flex;
      align-items:center;
      justify-content:center;
      width:56px;
      height:56px;
      border-radius:50%;
      box-shadow:0 4px 12px rgba(0,0,0,0.15);
      text-decoration:none;
      transition:all 0.3s ease;
    }
    .float-social a:hover{
      transform:scale(1.1);
      box-shadow:0 6px 16px rgba(0,0,0,0.2);
    }
    .float-social a svg{
      width:28px;
      height:28px;
      color:#fff;
    }
    .fab-wa{
      background:#25d366;
    }
    .fab-wa:hover{
      background:#20ba5a;
    }
    .fab-ig{
      background: radial-gradient(circle at 30% 110%, #fdf497 0%, #fdf497 5%, #fd5949 45%, #d6249f 60%, #285AEB 90%);
    }
    .fab-ig:hover{
      transform:scale(1.1);
    }
  </style>

  <!-- Meta Pixel Code -->
  <script>
  !function(f,b,e,v,n,t,s)
  {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};
  if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
  n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];
  s.parentNode.insertBefore(t,s)}(window, document,'script',
  'https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', '8850593125018234');
  fbq('track', 'PageView');
  </script>
  <noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id=8850593125018234&ev=PageView&noscript=1"
  /></noscript>
  <!-- End Meta Pixel Code -->

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-B13EZZR4R7"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-B13EZZR4R7');
  </script>
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
                  <span>Envío a Domicilio vía Starken</span>
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
                  <label style="display:block;margin-bottom:4px;font-weight:600">Comuna</label>
                  <select id="shippingCommune" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box">
                    <option value="">Seleccionar comuna...</option>
                  </select>
                </div>
                <div style="margin-bottom:10px">
                  <label style="display:block;margin-bottom:4px;font-weight:600">Dirección de Entrega</label>
                  <input type="text" id="shippingAddress" placeholder="Calle, número, depto" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box" />
                </div>
                <button id="btnQuoteShipping" type="button" style="width:100%;padding:10px;background:#0066cc;color:white;border:none;border-radius:3px;cursor:pointer;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px">
                  <span id="btnQuoteShippingText">Cotizar Envío</span>
                  <span id="btnQuoteShippingSpinner" style="display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top:2px solid white;border-radius:50%;animation:spin 0.8s linear infinite"></span>
                </button>
              </div>

              <!-- Select de sucursales para Retiro en Sucursal (búsqueda por nombre) -->
              <div id="shippingAgenciesForm" style="margin-top:14px;padding:12px;background:#f5f5f5;border-radius:4px;display:none">
                <div style="margin-bottom:10px">
                  <label style="display:block;margin-bottom:4px;font-weight:600">Sucursal (busca por nombre)</label>
                  <select id="shippingAgency" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:3px;box-sizing:border-box">
                    <option value="">Seleccionar sucursal...</option>
                  </select>
                </div>
                <button id="btnQuoteAgencyShipping" type="button" style="width:100%;padding:10px;background:#0066cc;color:white;border:none;border-radius:3px;cursor:pointer;font-weight:600;display:flex;align-items:center;justify-content:center;gap:8px">
                  <span id="btnQuoteAgencyShippingText">Cotizar Envío</span>
                  <span id="btnQuoteAgencyShippingSpinner" style="display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top:2px solid white;border-radius:50%;animation:spin 0.8s linear infinite"></span>
                </button>
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
  <script src="<?php echo htmlspecialchars(buildUrl('assets/checkout.js?v=6'), ENT_QUOTES, 'UTF-8'); ?>"></script>

  <!-- Burbujas flotantes RRSS -->
  <div class="float-social" aria-label="Accesos rápidos">
    <!-- WhatsApp -->
    <a class="fab-wa"
       href="https://wa.me/56984226651?text=%C2%A1Hola%21%20Quisiera%20saber%20m%C3%A1s%20sobre%20los%20servicios%20de%20producci%C3%B3n%20de%20Roelplant%20y%20la%20disponibilidad%20de%20plantines.%20%C2%BFPodr%C3%ADan%20ayudarme%3F"
       target="_blank" rel="noopener"
       aria-label="Escribir por WhatsApp a Roelplant">
      <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="M19.11 17.62c-.27-.14-1.62-.8-1.87-.89-.25-.09-.43-.14-.61.14-.18.27-.7.89-.86 1.07-.16.18-.32.2-.59.07-.27-.14-1.13-.42-2.16-1.33-.8-.71-1.34-1.58-1.5-1.85-.16-.27-.02-.42.12-.55.12-.12.27-.32.41-.48.14-.16.18-.27.27-.46.09-.18.05-.34-.02-.48-.07-.14-.61-1.47-.84-2.02-.22-.53-.45-.46-.61-.46l-.52-.01c-.18 0-.48.07-.73.34-.25.27-.95.93-.95 2.27 0 1.34.98 2.63 1.11 2.82.14.18 1.93 2.95 4.68 4.14.65.28 1.16.45 1.56.57.66.21 1.26.18 1.73.11.53-.08 1.62-.66 1.85-1.3.23-.64.23-1.18.16-1.3-.07-.12-.25-.2-.52-.34z"/>
        <path fill="currentColor" d="M16 3C8.83 3 3 8.73 3 15.78c0 2.3.62 4.55 1.8 6.5L3 29l6.92-1.79a13.24 13.24 0 0 0 6.08 1.47c7.17 0 13-5.73 13-12.78C29 8.73 23.17 3 16 3zm0 23.36c-1.93 0-3.83-.5-5.5-1.45l-.39-.22-4.1 1.06 1.09-3.99-.25-.41a11.17 11.17 0 0 1-1.73-5.98C5.12 9.98 10.07 5.12 16 5.12c5.93 0 10.88 4.86 10.88 10.66S21.93 26.36 16 26.36z"/>
      </svg>
    </a>

    <!-- Instagram -->
    <a class="fab-ig"
       href="https://www.instagram.com/roelplant/"
       target="_blank" rel="noopener"
       aria-label="Abrir Instagram de Roelplant">
      <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path fill="currentColor" d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9A5.5 5.5 0 0 1 16.5 22h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2zm9 2h-9A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4z"/>
        <path fill="currentColor" d="M12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6z"/>
        <circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>
      </svg>
    </a>
  </div>
</body>
</html>
