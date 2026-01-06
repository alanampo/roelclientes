<?php
// catalogo_detalle/production_detail.php
declare(strict_types=1);

require __DIR__ . '/api/_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');

start_session();
$cid = (int)($_SESSION['customer_id'] ?? 0);
if ($cid <= 0) {
  header('Location: index.php?openAuth=1&return_to=' . rawurlencode('my_production.php'));
  exit;
}

$rid = (int)($_GET['id'] ?? 0);
if ($rid <= 0) {
  header('Location: my_production.php');
  exit;
}

$db = db();

$st = $db->prepare("SELECT id, request_code, status, total_units, total_amount_clp, notes, created_at
                    FROM production_requests
                    WHERE id=? AND customer_id=?");
$st->bind_param('ii', $rid, $cid);
$st->execute();
$req = $st->get_result()->fetch_assoc();
$st->close();
if (!$req) {
  header('Location: my_production.php');
  exit;
}

$sti = $db->prepare("SELECT product_name, qty, unit_price_clp, line_total_clp
                     FROM production_request_items
                     WHERE request_id=?
                     ORDER BY id ASC");
$sti->bind_param('i', $rid);
$sti->execute();
$items = [];
$res = $sti->get_result();
while ($r = $res->fetch_assoc()) $items[] = $r;
$sti->close();

function clp($n){ return '$' . number_format((int)$n, 0, ',', '.'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Solicitud <?= htmlspecialchars((string)$req['request_code']) ?></title>
  <link rel="stylesheet" href="assets/app.css?v=1">
  <style>
    .wrap{max-width:980px;margin:0 auto;padding:24px 16px}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}
    .btn{border-radius:999px;padding:10px 14px;font-weight:700;border:1px solid #dbe6ee;background:#fff;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:8px;color:#111}
    .btn-primary{background:#0f6b5a;border-color:#0f6b5a;color:#fff}
    .card{background:#fff;border:1px solid #e7eef4;border-radius:18px;box-shadow:0 8px 24px rgba(22,34,51,.06)}
    .card-h{padding:16px 18px;border-bottom:1px solid #eef3f7}
    .card-b{padding:16px 18px}
    .muted{color:#6b7a8a}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px 8px;border-bottom:1px solid #eef3f7;text-align:left;vertical-align:top}
    th{font-size:12px;letter-spacing:.03em;text-transform:uppercase;color:#6b7a8a}
    .row{display:flex;gap:12px;flex-wrap:wrap;justify-content:space-between}
    .pill{display:inline-flex;align-items:center;gap:8px;background:#eef9f6;color:#0f6b5a;border-radius:999px;padding:6px 10px;font-weight:800}
    .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div class="brand">Roelplant · Producción a pedido</div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn" href="my_production.php">Mis solicitudes</a>
        <a class="btn btn-primary" href="produccion.php">Nueva solicitud</a>
        <a class="btn" href="index.php">Catálogo</a>
      </div>
    </div>

    <div class="card">
      <div class="card-h">
        <div class="row">
          <div>
            <div class="pill">Solicitud</div>
            <h2 style="margin:8px 0 0 0" class="mono"><?= htmlspecialchars((string)$req['request_code']) ?></h2>
            <div class="muted">Creada: <?= htmlspecialchars((string)$req['created_at']) ?> · Estado: <?= htmlspecialchars((string)$req['status']) ?></div>
          </div>
          <div style="text-align:right">
            <div class="muted">Total unidades</div>
            <div style="font-size:22px;font-weight:900"><?= (int)$req['total_units'] ?></div>
            <div class="muted">Total estimado</div>
            <div style="font-size:22px;font-weight:900"><?= clp((int)$req['total_amount_clp']) ?></div>
          </div>
        </div>
      </div>
      <div class="card-b">
        <table>
          <thead>
            <tr>
              <th>Especie</th>
              <th>Cantidad</th>
              <th>Precio mayorista</th>
              <th>Total línea</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= htmlspecialchars((string)$it['product_name']) ?></td>
              <td><?= (int)$it['qty'] ?></td>
              <td><?= clp((int)$it['unit_price_clp']) ?></td>
              <td><?= clp((int)$it['line_total_clp']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <?php if (!empty($req['notes'])): ?>
          <div style="margin-top:14px">
            <div class="muted" style="font-weight:800;margin-bottom:6px">Instrucciones</div>
            <div style="white-space:pre-wrap"><?= htmlspecialchars((string)$req['notes']) ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
