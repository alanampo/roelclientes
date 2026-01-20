<?php
require __DIR__ . '/api/_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mi perfil - Roelplant</title>
  <link rel="stylesheet" href="assets/checkout.css" />
  <style>
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .span2{grid-column:1 / -1}
    .inp{width:100%;padding:12px;border:1px solid var(--border);border-radius:12px;font-size:14px}
    .muted2{color:#6b7280;font-size:13px}
    .row-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand" aria-label="Roelplant"><span class="dot" aria-hidden="true"></span><span>Roelplant</span></div>
    <div class="actions">
      <a class="btn" href="index.php">Catálogo</a>
      <a class="btn" href="my_orders.php">Mis pedidos</a>
      <button id="btnLogout" class="btn btn-danger" type="button" style="display:none">Salir</button>
    </div>
  </header>

  <main class="container">
    <h1 class="h1">Mi perfil</h1>
    <p class="subtitle">Actualiza tus datos para facilitar tus próximas compras.</p>

    <div id="alertBox" class="alert hidden" data-type="info"></div>

    <section class="card">
      <h2>Datos del cliente</h2>
      <div class="muted2" id="profileMeta">Cargando...</div>

      <div class="form-grid" style="margin-top:12px">
        <input class="inp" id="pRut" placeholder="RUT" disabled>
        <input class="inp" id="pEmail" placeholder="Email" disabled>

        <input class="inp" id="pNombre" placeholder="Nombre">
        <input class="inp" id="pTelefono" placeholder="Teléfono +56XXXXXXXX">

        <select class="inp" id="pRegion"><option value="">Selecciona Región</option></select>
        <select class="inp" id="pComuna" disabled><option value="">Selecciona Comuna</option></select>

        <div class="span2 muted2" style="margin-top:6px">Cambio de contraseña (opcional)</div>
        <input class="inp" id="pCurrentPass" type="password" placeholder="Contraseña actual" autocomplete="current-password">
        <input class="inp" id="pNewPass" type="password" placeholder="Nueva contraseña (mín 8)" autocomplete="new-password">
      </div>

      <div class="row-actions" style="margin-top:14px">
        <button class="btn" type="button" onclick="location.href='my_orders.php'">Ver mis pedidos</button>
        <button class="btn btn-primary" id="btnSave" type="button">Guardar cambios</button>
      </div>
    </section>
  </main>

  <script src="assets/locations_cl.js?v=1"></script>
  <script src="assets/profile.js?v=2"></script>
</body>
</html>
