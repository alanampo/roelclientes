<?php
require __DIR__ . '/config/routes.php';
require __DIR__ . '/api/_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mis Compras - Roelplant</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(buildUrl('assets/checkout.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <style>
    .orders{display:flex;flex-direction:column;gap:12px}
    .order-card{border:1px solid var(--border);border-radius:16px;padding:14px;background:#fff}
    .order-top{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#eef2ff;font-weight:800;font-size:12px}
    .muted2{color:#6b7280;font-size:13px}
    .row-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;margin-top:10px}
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
    <div class="brand" aria-label="Roelplant"><span class="dot"></span><span>Roelplant</span></div>
    <div class="actions">
      <a class="btn" href="<?php echo htmlspecialchars(buildUrl('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Catálogo</a>
      <a class="btn" href="<?php echo htmlspecialchars(buildUrl('profile.php'), ENT_QUOTES, 'UTF-8'); ?>">Mi perfil</a>
      <button id="btnLogout" class="btn btn-danger" type="button" style="display:none">Salir</button>
    </div>
  </header>

  <main class="container">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap">
      <div>
        <h1 class="h1">Mis Compras</h1>
        <p class="subtitle">Historial de compras realizadas desde tu cuenta.</p>
      </div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(buildUrl('produccion.php'), ENT_QUOTES, 'UTF-8'); ?>">Solicitar producción de especies</a>
      </div>
    </div>

    <div id="alertBox" class="alert hidden" data-type="info"></div>

    <section class="card">
      <div class="muted2" id="ordersMeta">Cargando...</div>
      <div class="orders" id="ordersList" style="margin-top:12px"></div>
      <div class="muted2" id="ordersEmpty" style="display:none;margin-top:12px">Aún no tienes compras registradas.</div>
    </section>
  </main>

  <script src="<?php echo htmlspecialchars(buildUrl('assets/my_orders.js?v=2'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
