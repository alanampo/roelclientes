<?php
// catalogo_detalle/backoffice/production_requests.php
declare(strict_types=1);

require __DIR__ . '/_layout.php';
require __DIR__ . '/_helpers.php';

require_admin();
$db = db();

$q = trim((string)bo_q('q',''));
$status = trim((string)bo_q('status',''));
$page = bo_int(bo_q('page', 1), 1);
[$page,$per,$off] = bo_paginate($page, 25);

$where="1=1"; $params=[]; $types='';

if($status!==''){ $where.=" AND status=?"; $params[]=$status; $types.='s'; }

if($q!==''){
  // busca por código, nombre/email/teléfono del cliente si existe join
  $where.=" AND (request_code LIKE CONCAT('%',?,'%') OR customer_id IN (SELECT id FROM customers WHERE nombre LIKE CONCAT('%',?,'%') OR email LIKE CONCAT('%',?,'%') OR telefono LIKE CONCAT('%',?,'%')))";
  $params=array_merge($params,[$q,$q,$q,$q]); $types.='ssss';
}

$countSql="SELECT COUNT(*) FROM production_requests WHERE $where";
$stc=mysqli_prepare($db,$countSql);
if($types) mysqli_stmt_bind_param($stc,$types,...$params);
mysqli_stmt_execute($stc);
mysqli_stmt_bind_result($stc,$total);
mysqli_stmt_fetch($stc); mysqli_stmt_close($stc);
$total=(int)$total;

$sql="SELECT pr.id, pr.request_code, pr.status, pr.total_units, pr.total_amount_clp, pr.created_at,
             c.nombre AS customer_nombre, c.email AS customer_email
      FROM production_requests pr
      LEFT JOIN customers c ON c.id=pr.customer_id
      WHERE $where
      ORDER BY pr.id DESC
      LIMIT ? OFFSET ?";
$st=mysqli_prepare($db,$sql);
$params2=$params; $types2=$types.'ii';
$params2[]=$per; $params2[]=$off;
mysqli_stmt_bind_param($st,$types2,...$params2);
mysqli_stmt_execute($st);
$res=mysqli_stmt_get_result($st);
$rows=[]; while($res && ($r=mysqli_fetch_assoc($res))) $rows[]=$r;
mysqli_stmt_close($st);

$pages=max(1,(int)ceil($total/$per));
$statuses=[]; $rs=mysqli_query($db,"SELECT DISTINCT status FROM production_requests ORDER BY status");
if($rs){while($x=mysqli_fetch_row($rs)) $statuses[]=$x[0];}

bo_header('Pedidos producción');
?>
<div class="card">
  <div class="card-h">
    <h1 class="h1">Pedidos producción</h1>
    <div class="muted small"><?= h($total) ?> total</div>
  </div>
  <div class="card-b">
    <form class="row" method="get">
      <div style="flex:2">
        <div class="small muted">Buscar</div>
        <input class="inp" name="q" value="<?= h($q) ?>" placeholder="Código o cliente (nombre/email/teléfono)">
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
      <thead><tr><th>ID</th><th>Código</th><th>Cliente</th><th>Estado</th><th>Unid</th><th>Total</th><th>Fecha</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><a href="production_request_detail.php?id=<?= h($r['id']) ?>"><?= h($r['id']) ?></a></td>
          <td class="mono"><?= h($r['request_code']) ?></td>
          <td>
            <div style="font-weight:900"><?= h($r['customer_nombre'] ?? '-') ?></div>
            <div class="muted small"><?= h($r['customer_email'] ?? '') ?></div>
          </td>
          <td><?= bo_badge((string)$r['status']) ?></td>
          <td><?= h((int)$r['total_units']) ?></td>
          <td><?= h(number_format((int)$r['total_amount_clp'],0,',','.')) ?></td>
          <td class="mono"><?= h((string)$r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if(!count($rows)): ?><tr><td colspan="7" class="muted">Sin resultados.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php bo_footer(); ?>
