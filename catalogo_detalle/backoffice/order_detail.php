<?php
// catalogo_detalle/backoffice/order_detail.php
declare(strict_types=1);

require __DIR__ . '/_layout.php';
require __DIR__ . '/_helpers.php';

require_admin();
$db = db();

$id = bo_int(bo_q('id', 0), 0);
if ($id <= 0) { header('Location: orders.php'); exit; }

$err=''; $ok='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  require_post();
  require_csrf_post();
  $newStatus = trim((string)bo_post('status',''));
  if ($newStatus==='') $err='Estado inválido.';
  else {
    $st = mysqli_prepare($db, "UPDATE orders SET status=? WHERE id=?");
    mysqli_stmt_bind_param($st, 'si', $newStatus, $id);
    if (!mysqli_stmt_execute($st)) $err = mysqli_stmt_error($st);
    else $ok = 'Estado actualizado.';
    mysqli_stmt_close($st);
  }
}

$st = mysqli_prepare($db, "SELECT * FROM orders WHERE id=?");
mysqli_stmt_bind_param($st,'i',$id);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
$o = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($st);
if (!$o) { header('Location: orders.php'); exit; }

$items=[];
$st2 = mysqli_prepare($db, "SELECT nombre AS product_name, qty, unit_price_clp, line_total_clp, imagen_url, referencia, id_variedad FROM order_items WHERE order_id=? ORDER BY id ASC");
mysqli_stmt_bind_param($st2,'i',$id);
mysqli_stmt_execute($st2);
$res2 = mysqli_stmt_get_result($st2);
while ($res2 && ($r=mysqli_fetch_assoc($res2))) $items[]=$r;
mysqli_stmt_close($st2);

$statuses=[];
$rs=mysqli_query($db,"SELECT DISTINCT status FROM orders ORDER BY status");
if($rs){while($x=mysqli_fetch_row($rs)) $statuses[]=$x[0];}
if(!in_array($o['status'],$statuses,true)) $statuses[]=$o['status'];

bo_header('Pedido stock');
?>
<div class="grid grid-2">
  <section class="card">
    <div class="card-h">
      <h1 class="h1">Pedido #<?= h($o['id']) ?></h1>
      <a class="btn" href="orders.php">Volver</a>
    </div>
    <div class="card-b">
      <?php if($err): ?><div class="alert bad"><?= h($err) ?></div><div style="height:10px"></div><?php endif; ?>
      <?php if($ok): ?><div class="alert"><?= h($ok) ?></div><div style="height:10px"></div><?php endif; ?>

      <div class="row">
        <div>
          <div class="small muted">Código</div>
          <div class="mono" style="font-weight:900"><?= h((string)$o['order_code']) ?></div>
        </div>
        <div>
          <div class="small muted">Estado</div>
          <div><?= bo_badge((string)$o['status']) ?></div>
        </div>
      </div>

      <div style="height:12px"></div>

      <form method="post" class="row">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div>
          <div class="small muted">Cambiar estado</div>
          <select class="inp" name="status">
            <?php foreach($statuses as $s): ?>
              <option value="<?= h($s) ?>" <?= $s===$o['status']?'selected':'' ?>><?= h($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="max-width:220px; align-self:flex-end">
          <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
      </form>

      <div style="height:12px"></div>

      <div class="row">
        <div>
          <div class="small muted">Cliente</div>
          <div style="font-weight:900"><?= h((string)$o['customer_nombre']) ?></div>
          <div class="muted small"><?= h((string)$o['customer_email']) ?></div>
          <div class="muted small"><?= h((string)$o['customer_telefono']) ?></div>
          <?php if ((int)$o['customer_id']>0): ?>
            <div class="small"><a href="customer_detail.php?id=<?= h((int)$o['customer_id']) ?>">Abrir ficha cliente</a></div>
          <?php endif; ?>
        </div>
        <div>
          <div class="small muted">Totales</div>
          <div class="muted small">Subtotal: <?= h(number_format((int)$o['subtotal_clp'],0,',','.')) ?></div>
          <div class="muted small">Envío: <?= h(number_format((int)$o['shipping_cost_clp'],0,',','.')) ?></div>
          <div style="font-weight:900">Total: <?= h(number_format((int)$o['total_clp'],0,',','.')) ?></div>
        </div>
      </div>

      <div style="height:12px"></div>
      <div class="small muted">Fecha: <span class="mono"><?= h((string)$o['created_at']) ?></span></div>
    </div>
  </section>

  <aside class="card">
    <div class="card-h"><h2 class="h1">Ítems</h2></div>
    <div class="card-b">
      <table class="table">
        <thead><tr><th>Producto</th><th>Cant.</th><th>Unit</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach($items as $it): ?>
          <tr>
            <td><?= h((string)$it['product_name']) ?></td>
            <td><?= h((int)$it['qty']) ?></td>
            <td><?= h(number_format((int)$it['unit_price_clp'],0,',','.')) ?></td>
            <td><?= h(number_format((int)$it['line_total_clp'],0,',','.')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if(!count($items)): ?><tr><td colspan="4" class="muted">Sin ítems.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </aside>
</div>
<?php bo_footer(); ?>
