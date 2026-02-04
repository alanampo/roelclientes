<?php
// catalogo_detalle/backoffice/customers.php
declare(strict_types=1);

require __DIR__ . '/_layout.php';
require __DIR__ . '/_helpers.php';

require_admin();
$db = db();

$q = trim((string)bo_q('q',''));
$page = bo_int(bo_q('page', 1), 1);
[$page,$per,$off] = bo_paginate($page, 25);

$where = "1=1";
$params = [];
$types = '';

if ($q !== '') {
  $where .= " AND (nombre LIKE CONCAT('%',?,'%') OR email LIKE CONCAT('%',?,'%') OR telefono LIKE CONCAT('%',?,'%') OR rut LIKE CONCAT('%',?,'%') OR comuna LIKE CONCAT('%',?,'%') OR region LIKE CONCAT('%',?,'%'))";
  $params = array_merge($params, [$q,$q,$q,$q,$q,$q]);
  $types .= 'ssssss';
}

$countSql = "SELECT COUNT(*) FROM customers WHERE $where";
$stc = mysqli_prepare($db, $countSql);
if (!$stc) throw new RuntimeException(mysqli_error($db));
if ($types) mysqli_stmt_bind_param($stc, $types, ...$params);
mysqli_stmt_execute($stc);
mysqli_stmt_bind_result($stc, $total);
mysqli_stmt_fetch($stc);
mysqli_stmt_close($stc);
$total = (int)$total;

$sql = "SELECT id, rut, nombre, email, telefono, region, comuna, created_at
        FROM customers
        WHERE $where
        ORDER BY id DESC
        LIMIT ? OFFSET ?";
$st = mysqli_prepare($db, $sql);
if (!$st) throw new RuntimeException(mysqli_error($db));

$params2 = $params;
$types2  = $types . 'ii';
$params2[] = $per;
$params2[] = $off;

mysqli_stmt_bind_param($st, $types2, ...$params2);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);

$rows = [];
while ($res && ($r = mysqli_fetch_assoc($res))) $rows[] = $r;
mysqli_stmt_close($st);

$pages = max(1, (int)ceil($total / $per));

bo_header('Clientes');
?>
<div class="card">
  <div class="card-h">
    <h1 class="h1">Clientes</h1>
    <div class="muted small"><?= h($total) ?> total</div>
  </div>
  <div class="card-b">
    <form class="row" method="get" action="customers.php">
      <div style="flex:2">
        <div class="small muted">Buscar</div>
        <input class="inp" name="q" value="<?= h($q) ?>" placeholder="Nombre, email, teléfono, RUT, región, comuna">
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
      <thead>
        <tr>
          <th>ID</th><th>Cliente</th><th>Contacto</th><th>Ubicación</th><th>Alta</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a href="customer_detail.php?id=<?= h($r['id']) ?>"><?= h($r['id']) ?></a></td>
          <td>
            <div style="font-weight:900"><?= h($r['nombre']) ?></div>
            <div class="muted small mono"><?= h($r['rut']) ?></div>
          </td>
          <td>
            <div><?= h($r['email']) ?></div>
            <div class="muted small"><?= h($r['telefono']) ?></div>
          </td>
          <td>
            <div><?= h($r['region']) ?></div>
            <div class="muted small"><?= h($r['comuna']) ?></div>
          </td>
          <td class="mono"><?= h($r['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!count($rows)): ?>
        <tr><td colspan="5" class="muted">Sin resultados.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php bo_footer(); ?>
