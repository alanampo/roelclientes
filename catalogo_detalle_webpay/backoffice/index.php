<?php
// /catalogo_detalle/backoffice/index.php
declare(strict_types=1);
require __DIR__ . '/_boot.php';
bo_require_login();

$db = bo_db();

$tab = $_GET['tab'] ?? 'kpis';
$q   = trim((string)($_GET['q'] ?? ''));

// Ver detalle
$viewOrderId = (int)($_GET['order_id'] ?? 0);
$viewProdId  = (int)($_GET['pr_id'] ?? 0);

/**
 * Estados permitidos (códigos internos) + etiqueta.
 * Se guardan como códigos en la BD para mantener consistencia.
 */
function bo_status_options(): array {
  return [
    'new'        => 'Nuevo',
    'paid'       => 'Pago aceptado',
    'preparing'  => 'Preparación en curso',
    'cancelled'  => 'Cancelado',
    'delivered'  => 'Entregado',
  ];
}

function bo_status_label(string $code): string {
  $map = bo_status_options();
  $k = strtolower(trim($code));
  return $map[$k] ?? $code;
}

function bo_status_is_allowed(string $code): bool {
  $map = bo_status_options();
  $k = strtolower(trim($code));
  return isset($map[$k]);
}

function like(string $q): string { return '%' . $q . '%'; }

// KPIs rápidos
$kpi = [
  'clientes' => (int)($db->query("SELECT COUNT(*) c FROM customers")->fetch_assoc()['c'] ?? 0),
  'orders'   => (int)($db->query("SELECT COUNT(*) c FROM orders")->fetch_assoc()['c'] ?? 0),
  'prodreq'  => (int)($db->query("SELECT COUNT(*) c FROM production_requests")->fetch_assoc()['c'] ?? 0),
];

// Listados
$customers = [];
$orders = [];
$prod = [];

// ------------------ Actions (cambio de estado) ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  bo_require_login();
  bo_require_csrf();

  $action = (string)($_POST['action'] ?? '');
  $id = (int)($_POST['id'] ?? 0);
  $newStatus = strtolower(trim((string)($_POST['status'] ?? '')));

  if ($id > 0 && bo_status_is_allowed($newStatus)) {
    if ($action === 'order_status') {
      $st = mysqli_prepare($db, "UPDATE orders SET status=? WHERE id=?");
      if (!$st) { throw new RuntimeException('SQL prepare error: '.mysqli_error($db)); }
      mysqli_stmt_bind_param($st, 'si', $newStatus, $id);
      mysqli_stmt_execute($st);
      mysqli_stmt_close($st);
      bo_audit('order_status', ['id'=>$id,'status'=>$newStatus]);
      header('Location: index.php?tab=pedidos&order_id='.$id);
      exit;
    }

    if ($action === 'prod_status') {
      $st = mysqli_prepare($db, "UPDATE production_requests SET status=? WHERE id=?");
      if (!$st) { throw new RuntimeException('SQL prepare error: '.mysqli_error($db)); }
      mysqli_stmt_bind_param($st, 'si', $newStatus, $id);
      mysqli_stmt_execute($st);
      mysqli_stmt_close($st);
      bo_audit('prod_status', ['id'=>$id,'status'=>$newStatus]);
      header('Location: index.php?tab=produccion&pr_id='.$id);
      exit;
    }
  }

  // Si cae acá, request inválida → vuelve sin romper el backoffice
  header('Location: index.php?tab='.rawurlencode($tab));
  exit;
}

if ($tab === 'clientes') {
  $sql = "SELECT id, rut, nombre, email, telefono, region, comuna, created_at
          FROM customers
          WHERE (?='' OR nombre LIKE ? OR email LIKE ? OR rut LIKE ?)
          ORDER BY id DESC
          LIMIT 200";
  $st = mysqli_prepare($db, $sql);
  if (!$st) { throw new RuntimeException('SQL prepare error: '.mysqli_error($db)); }
  $qq = $q;
  $lk = like($q);
  mysqli_stmt_bind_param($st, 'ssss', $qq, $lk, $lk, $lk);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  while ($r = $rs->fetch_assoc()) $customers[] = $r;
  mysqli_stmt_close($st);
}

if ($tab === 'pedidos') {
  $sql = "SELECT id, order_code, customer_id, customer_nombre, customer_email, customer_telefono, customer_rut,
                 status, subtotal_clp, shipping_cost_clp, total_clp, created_at
          FROM orders
          WHERE (?='' OR order_code LIKE ? OR customer_nombre LIKE ? OR customer_email LIKE ? OR customer_rut LIKE ? OR customer_telefono LIKE ? OR status LIKE ?)
          ORDER BY id DESC
          LIMIT 200";
  $st = mysqli_prepare($db, $sql);
  if (!$st) { throw new RuntimeException('SQL prepare error: '.mysqli_error($db)); }
  $qq = $q;
  $lk = like($q);
  mysqli_stmt_bind_param($st, 'sssssss', $qq, $lk, $lk, $lk, $lk, $lk, $lk);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  while ($r = $rs->fetch_assoc()) $orders[] = $r;
  mysqli_stmt_close($st);
}

if ($tab === 'produccion') {
  $sql = "SELECT pr.id, pr.request_code, pr.customer_id, pr.status, pr.total_units, pr.total_amount_clp, pr.created_at,
                 c.nombre AS customer_nombre, c.email AS customer_email, c.telefono AS customer_telefono, c.rut AS customer_rut
          FROM production_requests pr
          LEFT JOIN customers c ON c.id=pr.customer_id
          WHERE (?='' OR pr.request_code LIKE ? OR CAST(pr.customer_id AS CHAR) LIKE ? OR pr.status LIKE ? OR c.nombre LIKE ? OR c.email LIKE ? OR c.rut LIKE ?)
          ORDER BY id DESC
          LIMIT 200";
  $st = mysqli_prepare($db, $sql);
  if (!$st) { throw new RuntimeException('SQL prepare error: '.mysqli_error($db)); }
  $qq = $q;
  $lk = like($q);
  mysqli_stmt_bind_param($st, 'sssssss', $qq, $lk, $lk, $lk, $lk, $lk, $lk);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  while ($r = $rs->fetch_assoc()) $prod[] = $r;
  mysqli_stmt_close($st);
}

$csrf = bo_csrf_token();
$adminName = (string)($_SESSION['bo_admin']['name'] ?? 'Admin');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Backoffice • Roelplant</title>
  <link rel="stylesheet" href="assets/app.css?v=1">
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div class="brand">
        <span style="font-size:18px">Roelplant</span>
        <span class="badge">Backoffice</span>
        <span class="badge"><?=bo_h($adminName)?></span>
      </div>
      <div class="nav">
        <a class="btn <?=($tab==='kpis'?'btn-primary':'')?>" href="index.php?tab=kpis">Dashboard</a>
        <a class="btn <?=($tab==='clientes'?'btn-primary':'')?>" href="index.php?tab=clientes">Clientes</a>
        <a class="btn <?=($tab==='pedidos'?'btn-primary':'')?>" href="index.php?tab=pedidos">Pedidos stock</a>
        <a class="btn <?=($tab==='produccion'?'btn-primary':'')?>" href="index.php?tab=produccion">Pedidos producción</a>
        <a class="btn" href="../index.php">Catálogo</a>
        <a class="btn btn-danger" href="logout.php">Salir</a>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <div class="card-h">
          <div>
            <div class="muted" style="font-weight:800">Vista</div>
            <h1 class="h1"><?= $tab==='kpis'?'Dashboard':($tab==='clientes'?'Clientes':($tab==='pedidos'?'Pedidos de stock':'Pedidos de producción')) ?></h1>
          </div>
          <form method="get" class="row">
            <input type="hidden" name="tab" value="<?=bo_h($tab)?>">
            <input class="inp" style="min-width:260px" name="q" value="<?=bo_h($q)?>" placeholder="Buscar…">
            <button class="btn" type="submit">Filtrar</button>
          </form>
        </div>

        <div class="card-b">
          <?php if ($tab === 'kpis'): ?>
            <div class="kpis">
              <div class="kpi"><div class="t">Clientes</div><div class="v"><?=number_format($kpi['clientes'],0,',','.')?></div></div>
              <div class="kpi"><div class="t">Pedidos stock</div><div class="v"><?=number_format($kpi['orders'],0,',','.')?></div></div>
              <div class="kpi"><div class="t">Pedidos producción</div><div class="v"><?=number_format($kpi['prodreq'],0,',','.')?></div></div>
            </div>
            <div style="height:12px"></div>
            <div class="alert">
              Usa las pestañas para administrar: clientes, pedidos de stock y pedidos de producción.
            </div>

          <?php elseif ($tab === 'clientes'): ?>
            <table class="table">
              <thead><tr>
                <th>ID</th><th>RUT</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Región</th><th>Comuna</th><th>Alta</th>
              </tr></thead>
              <tbody>
              <?php foreach ($customers as $c): ?>
                <tr>
                  <td><a href="index.php?tab=clientes&q=<?=bo_h((string)$c['rut'])?>"><?=bo_h((string)$c['id'])?></a></td>
                  <td><?=bo_h((string)$c['rut'])?></td>
                  <td><?=bo_h((string)$c['nombre'])?></td>
                  <td><?=bo_h((string)$c['email'])?></td>
                  <td><?=bo_h((string)$c['telefono'])?></td>
                  <td><?=bo_h((string)$c['region'])?></td>
                  <td><?=bo_h((string)$c['comuna'])?></td>
                  <td><?=bo_h((string)$c['created_at'])?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>

          <?php elseif ($tab === 'pedidos'): ?>
            <table class="table">
              <thead><tr>
                <th>ID</th><th>Código</th><th>Cliente</th><th>Estado</th><th>Subtotal</th><th>Envío</th><th>Total</th><th>Fecha</th><th></th>
              </tr></thead>
              <tbody>
              <?php foreach ($orders as $o): ?>
                <tr>
                  <td><a href="index.php?tab=pedidos&order_id=<?=bo_h((string)$o['id'])?>"><?=bo_h((string)$o['id'])?></a></td>
                  <td class="mono"><?=bo_h((string)$o['order_code'])?></td>
                  <td>
                    <div style="font-weight:800"><?=bo_h((string)$o['customer_nombre'])?></div>
                    <div class="muted" style="font-size:12px"><?=bo_h((string)$o['customer_email'])?></div>
                    <div class="muted" style="font-size:12px"><?=bo_h((string)$o['customer_telefono'])?></div>
                  </td>
                  <td><span class="pill"><?=bo_h(bo_status_label((string)$o['status']))?></span></td>
                  <td>$<?=number_format((int)$o['subtotal_clp'],0,',','.')?></td>
                  <td>$<?=number_format((int)$o['shipping_cost_clp'],0,',','.')?></td>
                  <td><b>$<?=number_format((int)$o['total_clp'],0,',','.')?></b></td>
                  <td><?=bo_h((string)$o['created_at'])?></td>
                  <td><a class="btn" href="index.php?tab=pedidos&order_id=<?=bo_h((string)$o['id'])?>">Detalle</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>

            <?php if ($viewOrderId > 0): ?>
              <?php
                $st = mysqli_prepare($db, "SELECT * FROM orders WHERE id=?");
                if ($st) {
                  mysqli_stmt_bind_param($st, 'i', $viewOrderId);
                  mysqli_stmt_execute($st);
                  $res = mysqli_stmt_get_result($st);
                  $od = $res ? mysqli_fetch_assoc($res) : null;
                  mysqli_stmt_close($st);
                } else { $od = null; }
                $items=[];
                if ($od) {
                  $st2 = mysqli_prepare($db, "SELECT product_name, qty, unit_price_clp, line_total_clp FROM order_items WHERE order_id=? ORDER BY id ASC");
                  if ($st2) {
                    mysqli_stmt_bind_param($st2, 'i', $viewOrderId);
                    mysqli_stmt_execute($st2);
                    $res2 = mysqli_stmt_get_result($st2);
                    while ($res2 && ($r=mysqli_fetch_assoc($res2))) $items[]=$r;
                    mysqli_stmt_close($st2);
                  }
                }
              ?>
              <?php if ($od): ?>
                <div style="height:14px"></div>
                <div class="card" style="border:1px solid rgba(255,255,255,.08)">
                  <div class="card-h">
                    <div>
                      <div class="muted" style="font-weight:800">Detalle pedido</div>
                      <div class="h1" style="margin:0">#<?=bo_h((string)$od['id'])?> • <?=bo_h((string)$od['order_code'])?></div>
                    </div>
                    <a class="btn" href="index.php?tab=pedidos">Cerrar</a>
                  </div>
                  <div class="card-b">
                    <div class="row">
                      <div>
                        <div class="small muted">Cliente</div>
                        <div style="font-weight:900"><?=bo_h((string)$od['customer_nombre'])?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)$od['customer_email'])?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)$od['customer_telefono'])?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)$od['customer_rut'])?></div>
                      </div>
                      <div>
                        <div class="small muted">Totales</div>
                        <div class="muted" style="font-size:12px">Subtotal: $<?=number_format((int)$od['subtotal_clp'],0,',','.')?></div>
                        <div class="muted" style="font-size:12px">Envío: $<?=number_format((int)$od['shipping_cost_clp'],0,',','.')?></div>
                        <div style="font-weight:900">Total: $<?=number_format((int)$od['total_clp'],0,',','.')?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)$od['created_at'])?></div>
                      </div>
                    </div>

                    <div style="height:12px"></div>

                    <form method="post" class="row">
                      <input type="hidden" name="_csrf" value="<?=bo_h($csrf)?>">
                      <input type="hidden" name="action" value="order_status">
                      <input type="hidden" name="id" value="<?=bo_h((string)$od['id'])?>">
                      <div>
                        <div class="small muted">Cambiar estado</div>
                        <select class="inp" name="status">
                          <?php foreach (bo_status_options() as $code=>$label): ?>
                            <option value="<?=bo_h($code)?>" <?= strtolower((string)$od['status'])===$code?'selected':'' ?>><?=bo_h($label)?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div style="max-width:220px; align-self:flex-end">
                        <button class="btn btn-primary" type="submit">Guardar</button>
                      </div>
                    </form>

                    <div style="height:12px"></div>

                    <table class="table">
                      <thead><tr><th>Producto</th><th>Cant.</th><th>Unit</th><th>Total</th></tr></thead>
                      <tbody>
                        <?php foreach($items as $it): ?>
                          <tr>
                            <td><?=bo_h((string)$it['product_name'])?></td>
                            <td><?=bo_h((string)$it['qty'])?></td>
                            <td>$<?=number_format((int)$it['unit_price_clp'],0,',','.')?></td>
                            <td>$<?=number_format((int)$it['line_total_clp'],0,',','.')?></td>
                          </tr>
                        <?php endforeach; ?>
                        <?php if (!count($items)): ?><tr><td colspan="4" class="muted">Sin ítems.</td></tr><?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>

          <?php else: ?>
            <table class="table">
              <thead><tr>
                <th>ID</th><th>Código</th><th>Cliente</th><th>Estado</th><th>Unidades</th><th>Total</th><th>Fecha</th><th></th>
              </tr></thead>
              <tbody>
              <?php foreach ($prod as $p): ?>
                <tr>
                  <td><a href="index.php?tab=produccion&pr_id=<?=bo_h((string)$p['id'])?>"><?=bo_h((string)$p['id'])?></a></td>
                  <td><b><?=bo_h((string)$p['request_code'])?></b></td>
                  <td>
                    <div style="font-weight:800"><?=bo_h((string)($p['customer_nombre'] ?? ('ID '.$p['customer_id'])))?></div>
                    <div class="muted" style="font-size:12px"><?=bo_h((string)($p['customer_email'] ?? ''))?></div>
                  </td>
                  <td><span class="pill"><?=bo_h(bo_status_label((string)$p['status']))?></span></td>
                  <td><?=number_format((int)$p['total_units'],0,',','.')?></td>
                  <td><b>$<?=number_format((int)$p['total_amount_clp'],0,',','.')?></b></td>
                  <td><?=bo_h((string)$p['created_at'])?></td>
                  <td><a class="btn" href="index.php?tab=produccion&pr_id=<?=bo_h((string)$p['id'])?>">Detalle</a></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>

            <?php if ($viewProdId > 0): ?>
              <?php
                $st = mysqli_prepare($db, "SELECT pr.*, c.nombre AS customer_nombre, c.email AS customer_email, c.telefono AS customer_telefono, c.rut AS customer_rut
                                            FROM production_requests pr
                                            LEFT JOIN customers c ON c.id=pr.customer_id
                                            WHERE pr.id=?");
                if ($st) {
                  mysqli_stmt_bind_param($st, 'i', $viewProdId);
                  mysqli_stmt_execute($st);
                  $res = mysqli_stmt_get_result($st);
                  $pd = $res ? mysqli_fetch_assoc($res) : null;
                  mysqli_stmt_close($st);
                } else { $pd = null; }

                $pitems=[];
                if ($pd) {
                  $st2 = mysqli_prepare($db, "SELECT product_name, product_id, qty, unit_price_clp, line_total_clp FROM production_request_items WHERE request_id=? ORDER BY id ASC");
                  if ($st2) {
                    mysqli_stmt_bind_param($st2, 'i', $viewProdId);
                    mysqli_stmt_execute($st2);
                    $res2 = mysqli_stmt_get_result($st2);
                    while ($res2 && ($r=mysqli_fetch_assoc($res2))) $pitems[]=$r;
                    mysqli_stmt_close($st2);
                  }
                }
              ?>
              <?php if ($pd): ?>
                <div style="height:14px"></div>
                <div class="card" style="border:1px solid rgba(255,255,255,.08)">
                  <div class="card-h">
                    <div>
                      <div class="muted" style="font-weight:800">Detalle pedido producción</div>
                      <div class="h1" style="margin:0">#<?=bo_h((string)$pd['id'])?> • <?=bo_h((string)$pd['request_code'])?></div>
                    </div>
                    <a class="btn" href="index.php?tab=produccion">Cerrar</a>
                  </div>
                  <div class="card-b">
                    <div class="row">
                      <div>
                        <div class="small muted">Cliente</div>
                        <div style="font-weight:900"><?=bo_h((string)($pd['customer_nombre'] ?? ''))?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)($pd['customer_email'] ?? ''))?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)($pd['customer_telefono'] ?? ''))?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)($pd['customer_rut'] ?? ''))?></div>
                      </div>
                      <div>
                        <div class="small muted">Totales</div>
                        <div class="muted" style="font-size:12px">Unidades: <?=number_format((int)$pd['total_units'],0,',','.')?></div>
                        <div style="font-weight:900">Total: $<?=number_format((int)$pd['total_amount_clp'],0,',','.')?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)$pd['created_at'])?></div>
                      </div>
                    </div>

                    <div style="height:12px"></div>

                    <form method="post" class="row">
                      <input type="hidden" name="_csrf" value="<?=bo_h($csrf)?>">
                      <input type="hidden" name="action" value="prod_status">
                      <input type="hidden" name="id" value="<?=bo_h((string)$pd['id'])?>">
                      <div>
                        <div class="small muted">Cambiar estado</div>
                        <select class="inp" name="status">
                          <?php foreach (bo_status_options() as $code=>$label): ?>
                            <option value="<?=bo_h($code)?>" <?= strtolower((string)$pd['status'])===$code?'selected':'' ?>><?=bo_h($label)?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div style="max-width:220px; align-self:flex-end">
                        <button class="btn btn-primary" type="submit">Guardar</button>
                      </div>
                    </form>

                    <?php if (!empty($pd['notes'])): ?>
                      <div style="height:12px"></div>
                      <div class="small muted">Notas</div>
                      <div class="alert"><?=nl2br(bo_h((string)$pd['notes']))?></div>
                    <?php endif; ?>

                    <div style="height:12px"></div>

                    <table class="table">
                      <thead><tr><th>Producto</th><th>Cant.</th><th>Unit</th><th>Total</th></tr></thead>
                      <tbody>
                        <?php foreach($pitems as $it): ?>
                          <tr>
                            <td><?=bo_h((string)$it['product_name'])?><div class="muted" style="font-size:12px"><?=bo_h((string)$it['product_id'])?></div></td>
                            <td><?=bo_h((string)$it['qty'])?></td>
                            <td>$<?=number_format((int)$it['unit_price_clp'],0,',','.')?></td>
                            <td>$<?=number_format((int)$it['line_total_clp'],0,',','.')?></td>
                          </tr>
                        <?php endforeach; ?>
                        <?php if (!count($pitems)): ?><tr><td colspan="4" class="muted">Sin ítems.</td></tr><?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-h">
          <div>
            <div class="muted" style="font-weight:800">Operaciones rápidas</div>
            <div class="muted">Acciones típicas de vendedor</div>
          </div>
        </div>
        <div class="card-b">
          <div class="alert">
            <b>Tip:</b> desde “Pedidos producción” puedes buscar por <b>request_code</b> o <b>customer_id</b>.
          </div>
          <div style="height:10px"></div>
          <a class="btn btn-primary" href="index.php?tab=produccion">Ver pedidos de producción</a>
          <div style="height:8px"></div>
          <a class="btn" href="index.php?tab=clientes">Ver clientes</a>
          <div style="height:8px"></div>
          <a class="btn" href="index.php?tab=pedidos">Ver pedidos stock</a>
          <hr>
          <div class="muted" style="font-size:12px">
            Si algo vuelve a 500, revisa el error_log del hosting. Backoffice depende de <code>../api/_bootstrap.php</code>.
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
