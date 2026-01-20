<?php
// catalogo_detalle/produccion.php
declare(strict_types=1);

require __DIR__ . '/api/_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
$csrf = csrf_token();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Producción a pedido</title>
  <link rel="stylesheet" href="assets/app.css?v=1">
  <style>
    .wrap{max-width:1200px;margin:0 auto;padding:24px 16px}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
    .grid{display:grid;grid-template-columns:1.35fr 1fr;gap:18px}
    @media(max-width:980px){.grid{grid-template-columns:1fr}}
    .card{background:#fff;border:1px solid #e7eef4;border-radius:18px;box-shadow:0 8px 24px rgba(22,34,51,.06)}
    .card-h{padding:16px 18px;border-bottom:1px solid #eef3f7;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .card-b{padding:16px 18px}
    .muted{color:#6b7a8a}
    .pill{display:inline-flex;align-items:center;gap:8px;background:#eef9f6;color:#0f6b5a;border-radius:999px;padding:8px 12px;font-weight:700}
    .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .inp{border:1px solid #dbe6ee;border-radius:12px;padding:10px 12px;min-width:240px}
    .btn{border-radius:999px;padding:10px 14px;font-weight:700;border:1px solid #dbe6ee;background:#fff;cursor:pointer}
    .btn-primary{background:#0f6b5a;border-color:#0f6b5a;color:#fff}
    .btn-danger{background:#e94b4b;border-color:#e94b4b;color:#fff}
    .list{display:flex;flex-direction:column;gap:10px;margin-top:12px}
    .item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border:1px solid #eef3f7;border-radius:16px}
    .name{font-weight:800}
    .meta{color:#6b7a8a}
    .actions{display:flex;align-items:center;gap:10px}
    .qty{width:120px;min-width:120px;text-align:center}
    .cartline{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border:1px solid #eef3f7;border-radius:16px;margin-bottom:10px}
    .cartname{font-weight:800}
    .cartmeta{color:#6b7a8a}
    .qtyctl{display:flex;align-items:center;gap:8px}
    .qtyctl .btn{width:44px;height:44px;border-radius:999px;padding:0;display:inline-flex;align-items:center;justify-content:center}
    .qtyctl .inp{width:120px;min-width:120px;text-align:center}
    .kpis{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}
    .kpi{background:#f5fbff;border:1px solid #e3f0fb;border-radius:999px;padding:8px 12px;font-weight:800}
    .kpi b{font-weight:900}
    .sep{height:1px;background:#eef3f7;margin:14px 0}
    .ok{color:#127a2a;font-weight:900}
    .bad{color:#c23b3b;font-weight:900}
    .toast{display:none;margin-top:12px;padding:12px 14px;border-radius:14px}
    .toast.ok{background:#e9fff2;border:1px solid #bfead0;color:#127a2a}
    .toast.err{background:#fff0f0;border:1px solid #f1b5b5;color:#a33131}
    .prog{background:#eef3f7;border-radius:999px;height:10px;overflow:hidden}
    .prog>div{height:10px;background:#0f6b5a;width:0%}
    .navbtns{display:flex;gap:10px;flex-wrap:wrap}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div>
        <h1 style="margin:0">Producción a pedido</h1>
        <div class="muted" style="margin-top:6px">
          Arma un pedido productivo: cada especie mínimo <b>50</b> unidades y total <b>≥ 200</b> unidades (puede ser más). Precios: <b>mayorista</b>.
        </div>
      </div>
      <div class="navbtns">
        <a class="btn" href="my_production.php">Mis solicitudes</a>
        <a class="btn" href="my_orders.php">Mis pedidos</a>
        <a class="btn" href="index.php">Catálogo</a>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <div class="card-h">
          <div class="pill">🌿 Especies en producción (WIP)</div>
          <button id="reloadBtn" class="btn" type="button">Recargar</button>
        </div>
        <div class="card-b">
          <div class="row">
            <input id="q" class="inp" type="text" placeholder="Buscar especie...">
          </div>

          <div class="kpis">
            <div class="kpi">Seleccionadas: <b><span id="kSel">0</span></b></div>
            <div class="kpi">Unidades: <b><span id="kUnits">0</span></b></div>
            <div class="kpi">Mínimo por especie: <b>50</b></div>
          </div>

          <div id="empty" class="muted" style="margin-top:12px">Cargando…</div>
          <div id="list" class="list" aria-live="polite"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-h">
          <div>
            <div style="font-size:22px;font-weight:900">Carrito de producción</div>
            <div class="muted">Agrega especies y define cantidades. Se valida al enviar.</div>
          </div>
        </div>
        <div class="card-b">
          <div style="margin-bottom:10px">
            <div class="muted" style="margin-bottom:6px">Progreso hacia mínimo 200 unidades</div>
            <div class="prog"><div id="progBar"></div></div>
            <div id="progTxt" class="muted" style="margin-top:6px">0/200 (0%)</div>
          </div>

          <div id="cartEmpty" class="muted">Aún no agregas especies.</div>
          <div id="cart"></div>

          <div class="sep"></div>

          <div style="display:flex;justify-content:space-between;gap:12px">
            <div>
              <div style="font-weight:900">Total unidades</div>
              <div style="font-weight:900">Total estimado</div>
              <div style="font-weight:900">Reglas</div>
            </div>
            <div style="text-align:right">
              <div id="sumUnits" style="font-weight:900">0</div>
              <div id="sumTotal" style="font-weight:900">$0</div>
              <div id="ruleStatus" class="bad">Debes agregar especies.</div>
            </div>
          </div>

          <div style="margin-top:14px">
            <div class="muted" style="margin-bottom:6px">Instrucciones (opcional)</div>
            <textarea id="notes" class="inp" style="width:100%;min-width:0;min-height:110px" placeholder="Ej: fecha estimada, formato de entrega, etc."></textarea>
          </div>

          <div style="display:flex;gap:10px;justify-content:space-between;align-items:center;margin-top:12px;flex-wrap:wrap">
            <button id="clearBtn" class="btn" type="button">Limpiar</button>
            <button id="sendBtn" class="btn btn-primary" type="button">Enviar solicitud por WhatsApp</button>
          </div>

          <div id="toastOk" class="toast ok"></div>
          <div id="toastErr" class="toast err"></div>

          <div class="muted" style="margin-top:10px">
            Al enviar, se guarda la solicitud en el sistema y se abrirá WhatsApp con el detalle.
          </div>
        </div>
      </div>
    </div>
  </div>

<script>
  window.ROEL_CSRF = <?php echo json_encode($csrf, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/production_cart.js?v=9.1"></script>
</body>
</html>
