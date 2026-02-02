<?php
// catalogo_detalle/backoffice/production_request_detail.php
declare(strict_types=1);

require __DIR__ . '/_layout.php';
require __DIR__ . '/_helpers.php';

require_admin();
$db = db();

$id = bo_int(bo_q('id', 0), 0);
if ($id <= 0) { header('Location: carrito_production_requests.php'); exit; }

$err=''; $ok='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
  require_post();
  require_csrf_post();
  $newStatus = trim((string)bo_post('status',''));
  if ($newStatus==='') $err='Estado inválido.';
  else {
    $st = mysqli_prepare($db, "UPDATE carrito_production_requests SET status=? WHERE id=?");
    mysqli_stmt_bind_param($st, 'si', $newStatus, $id);
    if (!mysqli_stmt_execute($st)) $err = mysqli_stmt_error($st);
    else $ok = 'Estado actualizado.';
    mysqli_stmt_close($st);
  }
}

$sql="SELECT pr.*, c.nombre AS customer_name, c.mail AS customer_email, c.telefono AS customer_phone
      FROM carrito_production_requests pr
      LEFT JOIN clientes c ON c.id_cliente=pr.id_cliente
      WHERE pr.id=?";
$st=mysqli_prepare($db,$sql);
mysqli_stmt_bind_param($st,'i',$id);
mysqli_stmt_execute($st);
$res=mysqli_stmt_get_result($st);
$p=$res?mysqli_fetch_assoc($res):null;
mysqli_stmt_close($st);
if(!$p){ header('Location: carrito_production_requests.php'); exit; }

$items=[];
$st2=mysqli_prepare($db,"SELECT product_name, qty, unit_price_clp, line_total_clp, product_id FROM carrito_production_request_items WHERE request_id=? ORDER BY id ASC");
mysqli_stmt_bind_param($st2,'i',$id);
mysqli_stmt_execute($st2);
$res2=mysqli_stmt_get_result($st2);
while($res2 && ($r=mysqli_fetch_assoc($res2))) $items[]=$r;
mysqli_stmt_close($st2);

$statuses=[];
$rs=mysqli_query($db,"SELECT DISTINCT status FROM carrito_production_requests ORDER BY status");
if($rs){while($x=mysqli_fetch_row($rs)) $statuses[]=$x[0];}
if(!in_array($p['status'],$statuses,true)) $statuses[]=$p['status'];

bo_header('Pedido producción');
?>
<div class="grid grid-2">
  <section class="card">
    <div class="card-h">
      <h1 class="h1">Producción #<?= h($p['id']) ?></h1>
      <a class="btn" href="carrito_production_requests.php">Volver</a>
    </div>
    <div class="card-b">
      <?php if($err): ?><div class="alert bad"><?= h($err) ?></div><div style="height:10px"></div><?php endif; ?>
      <?php if($ok): ?><div class="alert"><?= h($ok) ?></div><div style="height:10px"></div><?php endif; ?>

      <div class="row">
        <div>
          <div class="small muted">Código</div>
          <div class="mono" style="font-weight:900"><?= h((string)$p['request_code']) ?></div>
        </div>
        <div>
          <div class="small muted">Estado</div>
          <div><?= bo_badge((string)$p['status']) ?></div>
        </div>
      </div>

      <div style="height:12px"></div>

      <form method="post" class="row">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div>
          <div class="small muted">Cambiar estado</div>
          <select class="inp" name="status">
            <?php foreach($statuses as $s): ?>
              <option value="<?= h($s) ?>" <?= $s===$p['status']?'selected':'' ?>><?= h($s) ?></option>
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
          <div style="font-weight:900"><?= h((string)($p['customer_name'] ?? '')) ?></div>
          <div class="muted small"><?= h((string)($p['customer_email'] ?? '')) ?></div>
          <div class="muted small"><?= h((string)($p['customer_phone'] ?? '')) ?></div>
          <?php if ((int)($p['id_cliente']??0)>0): ?>
            <div class="small"><a href="customer_detail.php?id=<?= h((int)$p['id_cliente']) ?>">Abrir ficha cliente</a></div>
          <?php endif; ?>
        </div>
        <div>
          <div class="small muted">Totales</div>
          <div class="muted small">Unidades: <?= h((int)$p['total_units']) ?></div>
          <div style="font-weight:900">Total estimado: <?= h(number_format((int)$p['total_amount_clp'],0,',','.')) ?></div>
        </div>
      </div>

      <?php if (!empty($p['notes'])): ?>
        <div style="height:12px"></div>
        <div class="small muted">Instrucciones</div>
        <div class="alert"><?= nl2br(h((string)$p['notes'])) ?></div>
      <?php endif; ?>

      <div style="height:12px"></div>
      <div class="small muted">Fecha: <span class="mono"><?= h((string)$p['created_at']) ?></span></div>
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
            <td>
              <?= h((string)$it['product_name']) ?>
              <div class="muted small mono"><?= h((string)$it['product_id']) ?></div>
            </td>
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
