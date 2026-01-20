<?php
// catalogo_detalle/backoffice/customer_detail.php
declare(strict_types=1);

require __DIR__ . '/_layout.php';
require __DIR__ . '/_helpers.php';

require_admin();
$db = db();

$id = bo_int(bo_q('id', 0), 0);
if ($id <= 0) { header('Location: customers.php'); exit; }

$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_post();
  require_csrf_post();

  $nombre = trim((string)bo_post('nombre',''));
  $email  = trim((string)bo_post('email',''));
  $telefono  = trim((string)bo_post('telefono',''));
  $rut    = trim((string)bo_post('rut',''));
  $region = trim((string)bo_post('region',''));
  $comuna = trim((string)bo_post('comuna',''));

  if ($nombre === '') $err = 'Nombre es requerido.';
  if ($err === '') {
    $st = mysqli_prepare($db, "UPDATE customers SET rut=?, nombre=?, email=?, telefono=?, region=?, comuna=? WHERE id=?");
    if (!$st) $err = mysqli_error($db);
    else {
      mysqli_stmt_bind_param($st, 'ssssssi', $rut, $nombre, $email, $telefono, $region, $comuna, $id);
      if (!mysqli_stmt_execute($st)) $err = mysqli_stmt_error($st);
      else $ok = 'Cliente actualizado.';
      mysqli_stmt_close($st);
    }
  }
}

$st = mysqli_prepare($db, "SELECT id, rut, nombre, email, telefono, region, comuna, created_at FROM customers WHERE id=?");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
$c = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($st);
if (!$c) { header('Location: customers.php'); exit; }

$orders_may = [];
$st2 = mysqli_prepare($db, "SELECT id, status, total_clp, created_at FROM orders_may WHERE customer_id=? ORDER BY id DESC LIMIT 10");
mysqli_stmt_bind_param($st2, 'i', $id);
mysqli_stmt_execute($st2);
$res2 = mysqli_stmt_get_result($st2);
while ($res2 && ($r = mysqli_fetch_assoc($res2))) $orders_may[] = $r;
mysqli_stmt_close($st2);

$prods = [];
$st3 = mysqli_prepare($db, "SELECT id, request_code, status, total_units, total_amount_clp, created_at FROM production_requests WHERE customer_id=? ORDER BY id DESC LIMIT 10");
mysqli_stmt_bind_param($st3, 'i', $id);
mysqli_stmt_execute($st3);
$res3 = mysqli_stmt_get_result($st3);
while ($res3 && ($r = mysqli_fetch_assoc($res3))) $prods[] = $r;
mysqli_stmt_close($st3);

bo_header('Cliente');
?>
<div class="grid grid-2">
  <section class="card">
    <div class="card-h">
      <h1 class="h1">Cliente #<?= h($c['id']) ?></h1>
      <a class="btn" href="customers.php">Volver</a>
    </div>
    <div class="card-b">
      <?php if ($err): ?><div class="alert bad"><?= h($err) ?></div><div style="height:10px"></div><?php endif; ?>
      <?php if ($ok): ?><div class="alert"><?= h($ok) ?></div><div style="height:10px"></div><?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
        <div class="row">
          <div>
            <div class="small muted">RUT</div>
            <input class="inp" name="rut" value="<?= h($c['rut']) ?>">
          </div>
          <div>
            <div class="small muted">Nombre</div>
            <input class="inp" name="nombre" value="<?= h($c['nombre']) ?>" required>
          </div>
        </div>
        <div style="height:10px"></div>
        <div class="row">
          <div>
            <div class="small muted">Email</div>
            <input class="inp" name="email" value="<?= h($c['email']) ?>">
          </div>
          <div>
            <div class="small muted">Teléfono</div>
            <input class="inp" name="telefono" value="<?= h($c['telefono']) ?>">
          </div>
        </div>
        <div style="height:10px"></div>
        <div class="row">
          <div>
            <div class="small muted">Región</div>
            <input class="inp" name="region" value="<?= h($c['region']) ?>">
          </div>
          <div>
            <div class="small muted">Comuna</div>
            <input class="inp" name="comuna" value="<?= h($c['comuna']) ?>">
          </div>
        </div>
        <div style="height:12px"></div>
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
      </form>

      <div style="height:12px"></div>
      <div class="small muted">Alta: <span class="mono"><?= h($c['created_at']) ?></span></div>
    </div>
  </section>

  <aside class="card">
    <div class="card-h"><h2 class="h1">Acciones rápidas</h2></div>
    <div class="card-b">
      <div class="row">
        <a class="btn" href="orders_may.php?q=<?= h(urlencode((string)$c['email'])) ?>">Buscar pedidos stock</a>
        <a class="btn" href="production_requests.php?q=<?= h(urlencode((string)$c['email'])) ?>">Buscar producción</a>
      </div>
    </div>
  </aside>
</div>

<div style="height:14px"></div>

<div class="grid grid-2">
  <section class="card">
    <div class="card-h"><h2 class="h1">Pedidos stock (últimos 10)</h2></div>
    <div class="card-b">
      <table class="table">
        <thead><tr><th>ID</th><th>Estado</th><th>Total</th><th>Fecha</th></tr></thead>
        <tbody>
          <?php foreach ($orders_may as $o): ?>
            <tr>
              <td><a href="order_detail.php?id=<?= h($o['id']) ?>"><?= h($o['id']) ?></a></td>
              <td><?= bo_badge((string)$o['status']) ?></td>
              <td><?= h(number_format((int)$o['total_clp'],0,',','.')) ?></td>
              <td class="mono"><?= h($o['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!count($orders_may)): ?><tr><td colspan="4" class="muted">Sin pedidos.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <div class="card-h"><h2 class="h1">Producción (últimos 10)</h2></div>
    <div class="card-b">
      <table class="table">
        <thead><tr><th>ID</th><th>Código</th><th>Estado</th><th>Unid</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($prods as $p): ?>
            <tr>
              <td><a href="production_request_detail.php?id=<?= h($p['id']) ?>"><?= h($p['id']) ?></a></td>
              <td class="mono"><?= h($p['request_code']) ?></td>
              <td><?= bo_badge((string)$p['status']) ?></td>
              <td><?= h((int)$p['total_units']) ?></td>
              <td><?= h(number_format((int)$p['total_amount_clp'],0,',','.')) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!count($prods)): ?><tr><td colspan="5" class="muted">Sin solicitudes.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php bo_footer(); ?>
