<?php
require __DIR__ . '/config/routes.php';
require __DIR__ . '/api/_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Mi perfil - Roelplant</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(buildUrl('assets/checkout.css'), ENT_QUOTES, 'UTF-8'); ?>" />
  <style>
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
    .span2{grid-column:1 / -1}
    .inp{width:100%;padding:12px;border:1px solid var(--border);border-radius:12px;font-size:14px}
    .muted2{color:#6b7280;font-size:13px}
    .row-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
    @media (max-width: 768px){
      .form-grid{grid-template-columns:1fr}
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand" aria-label="Roelplant"><span class="dot" aria-hidden="true"></span><span>Roelplant</span></div>
    <div class="actions">
      <a class="btn" href="<?php echo htmlspecialchars(buildUrl('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Catálogo</a>
      <a class="btn" href="<?php echo htmlspecialchars(buildUrl('my_orders.php'), ENT_QUOTES, 'UTF-8'); ?>">Mis compras</a>
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
        <div>
          <label for="pRut" style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">RUT</label>
          <input class="inp" id="pRut" placeholder="RUT" disabled>
        </div>
        <div>
          <label for="pEmail" style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Email</label>
          <input class="inp" id="pEmail" placeholder="Email" disabled>
        </div>

        <div>
          <label for="pNombre" style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Nombre completo</label>
          <input class="inp" id="pNombre" placeholder="Ingresa tu nombre completo">
        </div>
        <div>
          <label for="pTelefono" style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Teléfono</label>
          <input class="inp" id="pTelefono" placeholder="Ej: +56912345678">
        </div>

        <div>
          <label for="pRegion" style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Región</label>
          <select class="inp" id="pRegion"><option value="">Selecciona Región</option></select>
        </div>
        <div>
          <label for="pComuna" style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Comuna</label>
          <select class="inp" id="pComuna" disabled><option value="">Selecciona Comuna</option></select>
        </div>

        <div class="span2 muted2" style="margin-top:6px;font-weight:600">Cambio de contraseña (opcional)</div>
        <div>
          <label for="pCurrentPass" style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Contraseña actual</label>
          <input class="inp" id="pCurrentPass" type="password" placeholder="Ingresa tu contraseña actual" autocomplete="current-password">
        </div>
        <div>
          <label for="pNewPass" style="display:block;margin-bottom:6px;font-weight:600;font-size:14px">Nueva contraseña</label>
          <input class="inp" id="pNewPass" type="password" placeholder="Mínimo 8 caracteres" autocomplete="new-password">
        </div>
      </div>

      <div class="row-actions" style="margin-top:14px">
        <button class="btn" type="button" onclick="location.href='<?php echo htmlspecialchars(buildUrl('my_orders.php'), ENT_QUOTES, 'UTF-8'); ?>'">Ver mis compras</button>
        <button class="btn btn-primary" id="btnSave" type="button">Guardar cambios</button>
      </div>
    </section>
  </main>

  <script src="<?php echo htmlspecialchars(buildUrl('assets/locations_cl.js?v=1'), ENT_QUOTES, 'UTF-8'); ?>"></script>
  <script src="<?php echo htmlspecialchars(buildUrl('assets/profile.js?v=5'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
