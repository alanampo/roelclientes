<?php
// catalogo_detalle/order_success.php (fix v1)
// - Corrige estructura/clases para calzar con assets/checkout.css (container/grid/card)
// - Ordena el bloque de transferencia (igual que checkout) con 1 solo botón copiar
// - Agrega JS local para copiar datos transferencia (sin depender de otros scripts)

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
  <link rel="stylesheet" href="assets/checkout.css" />
</head>
<body>

  <header class="topbar">
    <div class="brand" aria-label="Roelplant">
      <span class="dot" aria-hidden="true"></span>
      <span>Roelplant</span>
    </div>

    <div class="actions">
      <a class="btn" href="index.php">Seguir comprando</a>
      <a class="btn" href="index.php">Volver al catálogo</a>
      <?php if ($logged): ?>
        <a class="btn btn-danger" href="api/auth/logout.php">Salir</a>
      <?php endif; ?>
    </div>
  </header>

  <main class="container">
    <h1 class="h1">Pedido enviado</h1>
    <p class="subtitle">
      Tu pedido quedó registrado. El envío es <strong>por pagar</strong> al courier (Starken u otro).
      Retiro en vivero: <strong>gratis</strong>.
    </p>

    <div id="alertBox" class="alert hidden" data-type="info"></div>

    <div class="grid">
      <!-- Detalle -->
      <section class="card">
        <h2>Detalle del pedido</h2>
        <div class="muted" style="margin:-6px 0 12px">Guarda este código para seguimiento</div>

        <div id="orderDetails" class="muted">Cargando...</div>

        <div class="hr" style="height:1px;background:var(--border);margin:14px 0"></div>

        <div class="footer-actions" style="display:flex;flex-wrap:wrap;gap:10px">
          <button id="btnWhatsApp" class="btn btn-primary" type="button">Abrir WhatsApp</button>
          <button id="btnCopy" class="btn" type="button">Copiar código</button>
        
        </div>

        <div class="muted" style="margin-top:12px;font-size:13px;line-height:1.35">
          Si WhatsApp no se abre, revisa que tu navegador permita ventanas emergentes para este sitio.
        </div>

        <!-- Transferencia (ordenado / 1 botón) -->
        <div class="muted" style="margin-top:12px; font-size:13px;">
          <div id="transferBox" style="border:1px solid rgba(0,0,0,.08);border-radius:12px;background:#fff;padding:12px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;">
              <strong style="font-size:14px;">Datos para transferencia</strong>
              <button
                id="btnCopyTransfer"
                type="button"
                class="btn"
                style="padding:8px 10px;font-size:12px;border-radius:10px;"
              >Copiar datos</button>
            </div>

            <div id="transferLines" style="line-height:1.45;">
              <div><strong>Cuenta Corriente:</strong> 63308240</div>
              <div><strong>Banco:</strong> Banco de Crédito e Inversiones (BCI)</div>
              <div><strong>Tipo de Cuenta:</strong> Corriente</div>
              <div><strong>RUT Empresa:</strong> 77.436.423-4</div>
              <div><strong>Nombre Empresa:</strong> Plantinera V.V.</div>
              <div><strong>Correo:</strong> plantinera@roelplant.cl</div>
            </div>

            <div
              id="transferToast"
              aria-live="polite"
              style="display:none;margin-top:10px;font-size:12px;color:#0f5132;background:rgba(25,135,84,.12);border:1px solid rgba(25,135,84,.18);padding:8px 10px;border-radius:10px;"
            ></div>
          </div>
        </div>
      </section>

      <!-- Siguiente paso -->
      <section class="card">
        <h2>Siguiente paso</h2>
        <div class="muted" style="margin:-6px 0 12px">Recomendaciones rápidas</div>

        <ul class="muted" style="margin:0;padding-left:18px;line-height:1.6">
          <li>Confirma disponibilidad y fecha de despacho por WhatsApp.</li>
          <li>El packing se calcula automáticamente según unidades del carrito.</li>
          <li>Enviar comprobante de transferencia por WhatsApp.</li>
          <li>El envío se paga al courier al momento de recibir o retirar.</li>
        </ul>

        <div class="hr" style="height:1px;background:var(--border);margin:14px 0"></div>

        <div class="muted" style="font-size:13px;line-height:1.35">
          Puedes compartir el código del pedido a otra persona para coordinar el retiro.
        </div>
      </section>
    </div>
  </main>

  <script src="assets/order_success.js"></script>

  <!-- Copiar datos transferencia -->
  <script>
  (function(){
    function showToast(msg){
      var el = document.getElementById('transferToast');
      if(!el) return;
      el.textContent = msg;
      el.style.display = 'block';
      clearTimeout(el._t);
      el._t = setTimeout(function(){ el.style.display = 'none'; }, 1600);
    }

    async function copyText(text){
      try{
        if (navigator.clipboard && window.isSecureContext){
          await navigator.clipboard.writeText(text);
          return true;
        }
      }catch(e){}
      try{
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly','');
        ta.style.position = 'fixed';
        ta.style.top = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        var ok = document.execCommand('copy');
        document.body.removeChild(ta);
        return ok;
      }catch(e){
        return false;
      }
    }

    document.addEventListener('click', async function(e){
      var btn = e.target.closest('#btnCopyTransfer');
      if(!btn) return;

      var text =
        "Datos para transferencia\n" +
        "Banco: Banco de Crédito e Inversiones (BCI)\n" +
        "Tipo de Cuenta: Corriente\n" +
        "Cuenta Corriente: 63308240\n" +
        "RUT Empresa: 77.436.423-4\n" +
        "Nombre Empresa: Plantinera V.V.\n" +
        "Correo: plantinera@roelplant.cl";

      var ok = await copyText(text);
      showToast(ok ? "Copiado al portapapeles" : "No se pudo copiar (revisa permisos del navegador)");
    });
  })();
  </script>
</body>
</html>
