<?php
// catalogo_detalle/backoffice/orders_may.php
declare(strict_types=1);

require __DIR__ . '/_layout.php';
require __DIR__ . '/_helpers.php';

require_admin();
$db = db();

$q = trim((string)bo_q('q',''));
$status = trim((string)bo_q('status',''));
$page = bo_int(bo_q('page', 1), 1);
[$page,$per,$off] = bo_paginate($page, 25);

$where = "1=1";
$params = [];
$types = '';

if ($status !== '') {
  $where .= " AND status = ?";
  $params[] = $status; $types .= 's';
}
if ($q !== '') {
  $where .= " AND (order_code LIKE CONCAT('%',?,'%') OR customer_nombre LIKE CONCAT('%',?,'%') OR customer_email LIKE CONCAT('%',?,'%') OR customer_telefono LIKE CONCAT('%',?,'%') OR customer_rut LIKE CONCAT('%',?,'%'))";
  $params = array_merge($params, [$q,$q,$q,$q,$q]);
  $types .= 'sssss';
}

$countSql = "SELECT COUNT(*) FROM orders_may WHERE $where";
$stc = mysqli_prepare($db, $countSql);
if ($types) mysqli_stmt_bind_param($stc, $types, ...$params);
mysqli_stmt_execute($stc);
mysqli_stmt_bind_result($stc, $total);
mysqli_stmt_fetch($stc);
mysqli_stmt_close($stc);
$total = (int)$total;

$sql = "SELECT id, order_code, status, total_clp, created_at, customer_id, customer_nombre, customer_email
        FROM orders_may
        WHERE $where
        ORDER BY id DESC
        LIMIT ? OFFSET ?";
$st = mysqli_prepare($db, $sql);

$params2 = $params; $types2 = $types.'ii';
$params2[]=$per; $params2[]=$off;
mysqli_stmt_bind_param($st, $types2, ...$params2);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);

$rows=[];
while ($res && ($r=mysqli_fetch_assoc($res))) $rows[]=$r;
mysqli_stmt_close($st);

$pages = max(1,(int)ceil($total/$per));

$statuses=[];
$rs=mysqli_query($db,"SELECT DISTINCT status FROM orders_may ORDER BY status");
if($rs){while($x=mysqli_fetch_row($rs)) $statuses[]=$x[0];}

bo_header('Pedidos stock');
?>
<div class="card">
  <div class="card-h">
    <h1 class="h1">Pedidos stock</h1>
    <div class="muted small"><?= h($total) ?> total</div>
  </div>
  <div class="card-b">
    <form class="row" method="get">
      <div style="flex:2">
        <div class="small muted">Buscar</div>
        <input class="inp" name="q" value="<?= h($q) ?>" placeholder="Código, nombre, email, teléfono">
      </div>
      <div>
        <div class="small muted">Estado</div>
        <select class="inp" name="status">
          <option value="">(todos)</option>
          <?php foreach($statuses as $s): ?>
            <option value="<?= h($s) ?>" <?= $s===$status?'selected':'' ?>><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="max-width:170px">
        <div class="small muted">Página</div>
        <select name="page" class="inp">
          <?php for($i=1;$i<=$pages;$i++): ?>
            <option value="<?= $i ?>" <?= $i===$page?'selected':'' ?>><?= $i ?>/<?= $pages ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div style="max-width:170px; align-self:flex-end">
        <button class="btn btn-primary" type="submit">Filtrar</button>
      </div>
    </form>

    <div style="height:12px"></div>

    <table class="table">
      <thead><tr><th>ID</th><th>Código</th><th>Cliente</th><th>Estado</th><th>Total</th><th>Fecha</th></tr></thead>
      <tbody>
      <?php foreach($rows as $o): ?>
        <tr>
          <td><a href="order_detail.php?id=<?= h($o['id']) ?>"><?= h($o['id']) ?></a></td>
          <td class="mono"><?= h($o['order_code']) ?></td>
          <td>
            <div style="font-weight:900"><?= h($o['customer_nombre']) ?></div>
            <div class="muted small"><?= h($o['customer_email']) ?></div>
            <?php if ((int)$o['customer_id']>0): ?>
              <div class="small"><a href="customer_detail.php?id=<?= h($o['customer_id']) ?>">Ver cliente</a></div>
            <?php endif; ?>
          </td>
          <td><?= bo_badge((string)$o['status']) ?></td>
          <td><?= h(number_format((int)$o['total_clp'],0,',','.')) ?></td>
          <td class="mono"><?= h($o['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!count($rows)): ?><tr><td colspan="6" class="muted">Sin resultados.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php bo_footer(); ?>
