<?php
// catalogo_detalle/order_success.php
require __DIR__ . '/api/_bootstrap.php';
header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

$logged = !empty($_SESSION['customer_id']);
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pedido creado - Roelplant</title>

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

  <link rel="stylesheet" href="assets/checkout.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand" aria-label="Roelplant">
      <span class="dot"></span>
      <span class="brand-name">Roelplant</span>
    </div>

    <div class="top-actions">
      <a class="btn btn-ghost" href="index.php">Seguir comprando</a>
      <?php if ($logged): ?>
        <a class="btn btn-danger" href="api/auth/logout.php">Salir</a>
      <?php endif; ?>
    </div>
  </header>

  <main class="wrap">
    <div class="title">
      <h1>Pedido enviado</h1>
      <p class="muted">Tu pedido quedó registrado. El envío es <strong>por pagar</strong> al courier (Starken u otro). Retiro en vivero: <strong>gratis</strong>.</p>
    </div>

    <div id="alertBox" class="alert hidden"></div>

    <section class="grid">
      <div class="card">
        <div class="card-hd">
          <div style="font-weight:900">Detalle del pedido</div>
          <div class="muted" style="font-size:13px">Guarda este código para seguimiento</div>
        </div>

        <div class="card-bd">
          <div id="orderDetails" class="muted">Cargando...</div>

          <div class="hr" style="height:1px;background:var(--border);margin:14px 0"></div>

          <div class="footer-actions" style="display:flex;flex-wrap:wrap;gap:10px">
            <button id="btnWhatsApp" class="btn btn-primary" type="button">Abrir WhatsApp</button>
            <button id="btnCopy" class="btn btn-ghost" type="button">Copiar código</button>
            <a class="btn btn-ghost" href="index.php">Volver al catálogo</a>
          </div>

          <div class="muted" style="margin-top:12px;font-size:13px;line-height:1.35">
            Si WhatsApp no se abre, revisa que tu navegador permita ventanas emergentes para este sitio.
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-hd">
          <div style="font-weight:900">Siguiente paso</div>
          <div class="muted" style="font-size:13px">Recomendaciones rápidas</div>
        </div>
        <div class="card-bd">
          <ul class="muted" style="margin:0;padding-left:18px;line-height:1.6">
            <li>Confirma disponibilidad y fecha de despacho por WhatsApp.</li>
            <li>El packing se calcula automáticamente según unidades del carrito.</li>
            <li>El envío se paga al courier al momento de recibir o retirar.</li>
          </ul>

          <div class="hr" style="height:1px;background:var(--border);margin:14px 0"></div>

          <div class="muted" style="font-size:13px;line-height:1.35">
            Mejora: puedes compartir el código del pedido a otra persona para coordinar el retiro.
          </div>
        </div>
      </div>
    </section>
  </main>

  <script src="assets/order_success.js"></script>
</body>
</html>
