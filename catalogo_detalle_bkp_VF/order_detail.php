<?php
require __DIR__ . '/api/_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Detalle de pedido - Roelplant</title>
  <link rel="stylesheet" href="assets/checkout.css" />
  <style>
    .muted2{color:#6b7280;font-size:13px}
    .items{display:flex;flex-direction:column;gap:10px;margin-top:12px}
    .it{display:flex;gap:12px;align-items:center;border:1px solid var(--border);border-radius:14px;padding:10px;background:#fff}
    .it img{width:56px;height:56px;object-fit:cover;border-radius:12px;border:1px solid var(--border)}
    .it .name{font-weight:900}
    .sum{margin-top:12px;border-top:1px solid var(--border);padding-top:12px}
    .row{display:flex;justify-content:space-between;gap:10px;margin:6px 0}
    .row-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;margin-top:12px}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand"><span class="dot"></span><span>Roelplant</span></div>
    <div class="actions">
      <a class="btn" href="my_orders.php">Mis pedidos</a>
      <a class="btn" href="index.php">Catálogo</a>
      <button id="btnLogout" class="btn btn-danger" type="button" style="display:none">Salir</button>
    </div>
  </header>

  <main class="container">
    <h1 class="h1">Detalle de pedido</h1>
    <p class="subtitle">Revisa el detalle del pedido y copia el código si lo necesitas.</p>

    <div id="alertBox" class="alert hidden" data-type="info"></div>

    <section class="card">
      <div class="muted2" id="headMeta">Cargando...</div>
      <div id="headMain" style="margin-top:10px;font-weight:900"></div>

      <div class="items" id="items"></div>

      <div class="sum" id="sumBox" style="display:none">
        <div class="row"><span class="muted2">Subtotal</span><strong id="sSubtotal">$0</strong></div>
        <div class="row"><span class="muted2">Envío</span><strong id="sShipping">Por pagar</strong></div>
        <div class="row"><span class="muted2">Total</span><strong id="sTotal">$0</strong></div>
        <div class="muted2" id="sNotes" style="margin-top:10px;white-space:pre-wrap"></div>
      </div>

      <div class="row-actions">
        <button class="btn" id="btnCopy" type="button">Copiar código</button>
        <button class="btn btn-primary" type="button" onclick="location.href='my_orders.php'">Volver</button>
      </div>
    </section>
  </main>

  <script>window.ORDER_ID = <?= (int)$orderId ?>;</script>
  <script src="assets/order_detail.js?v=1"></script>
</body>
</html>
