<?php
// catalogo_detalle/my_production.php
declare(strict_types=1);

require __DIR__ . '/config/routes.php';
require __DIR__ . '/api/_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');

start_session();
$cid = (int)($_SESSION['customer_id'] ?? 0);
if ($cid <= 0) {
  header('Location: ' . buildUrl('index.php?openAuth=1&return_to=' . rawurlencode('my_production.php')));
  exit;
}

$db = db();

// Lista de solicitudes del cliente
$st = $db->prepare("SELECT id, request_code, status, total_units, total_amount_clp, created_at
                    FROM production_requests
                    WHERE customer_id=?
                    ORDER BY id DESC
                    LIMIT 100");
$st->bind_param('i', $cid);
$st->execute();
$res = $st->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) $rows[] = $r;
$st->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mis solicitudes de producción</title>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(buildUrl('assets/styles.css?v=4'), ENT_QUOTES, 'UTF-8'); ?>">
  <style>
    .wrap{max-width:1100px;margin:0 auto;padding:24px 16px}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
    .btn{border-radius:999px;padding:10px 14px;font-weight:700;border:1px solid #dbe6ee;background:#fff;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;color:#111}
    .btn-primary{background:#0f6b5a;border-color:#0f6b5a;color:#fff}
    .card{background:#fff;border:1px solid #e7eef4;border-radius:18px;box-shadow:0 8px 24px rgba(22,34,51,.06)}
    .card-h{padding:16px 18px;border-bottom:1px solid #eef3f7}
    .card-b{padding:16px 18px}
    .muted{color:#6b7a8a}
    .grid{display:grid;grid-template-columns:1fr;gap:12px}
    @media(min-width:860px){.grid{grid-template-columns:1fr 1fr}}
    .pill{display:inline-flex;align-items:center;gap:8px;background:#eef9f6;color:#0f6b5a;border-radius:999px;padding:6px 10px;font-weight:800}
    .row{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div class="brand">Roelplant · Catálogo detalle</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn" href="<?php echo htmlspecialchars(buildUrl('my_orders.php'), ENT_QUOTES, 'UTF-8'); ?>">Mis compras</a>
        <a class="btn" href="<?php echo htmlspecialchars(buildUrl('produccion.php'), ENT_QUOTES, 'UTF-8'); ?>">Solicitar producción</a>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(buildUrl('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Catálogo</a>
      </div>
    </div>

    <div class="card">
      <div class="card-h">
        <h2 style="margin:0">Mis solicitudes de producción</h2>
        <div class="muted">Aquí verás tus solicitudes productivas registradas.</div>
      </div>
      <div class="card-b">
        <?php if (!count($rows)): ?>
          <div class="muted">Aún no tienes solicitudes de producción.</div>
        <?php else: ?>
          <div class="grid">
            <?php foreach($rows as $r):
              $id = (int)$r['id'];
              $code = htmlspecialchars((string)$r['request_code']);
              $status = htmlspecialchars((string)$r['status']);
              $units = (int)$r['total_units'];
              $amount = (int)$r['total_amount_clp'];
              $created = htmlspecialchars((string)$r['created_at']);
            ?>
              <div class="card" style="box-shadow:none">
                <div class="card-b">
                  <div class="row">
                    <div>
                      <div class="mono" style="font-weight:900;font-size:14px"><?= $code ?></div>
                      <div class="muted" style="font-size:13px"><?= $created ?></div>
                    </div>
                    <div class="pill">Estado: <?= $status ?></div>
                  </div>
                  <div style="height:10px"></div>
                  <div class="row">
                    <div><strong>Unidades:</strong> <?= number_format($units,0,',','.') ?></div>
                    <div><strong>Total estimado:</strong> $<?= number_format($amount,0,',','.') ?></div>
                  </div>
                  <div style="height:12px"></div>
                  <a class="btn" href="<?php echo htmlspecialchars(buildUrl('production_detail.php?id=' . $id), ENT_QUOTES, 'UTF-8'); ?>">Ver detalle</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
