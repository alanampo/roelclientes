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
