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

/**
 * Etiquetas para payment_status
 */
function bo_payment_status_label(string $status): string {
  $labels = [
    'paid' => 'Pago aceptado',
    'pending' => 'Pendiente',
    'failed' => 'Rechazado',
    'refunded' => 'Reembolsado'
  ];
  $k = strtolower(trim($status));
  return $labels[$k] ?? $status;
}

/**
 * Color de fondo para payment_status (mismo que en detalle)
 */
function bo_payment_status_color(string $status): string {
  $k = strtolower(trim($status));
  if ($k === 'paid') return '#5fe5d0';
  if ($k === 'failed') return '#f87171';
  return '#fbbf24'; // pending y otros
}

function like(string $q): string { return '%' . $q . '%'; }

// KPIs rápidos
$kpi = [
  'clientes' => (int)($db->query("SELECT COUNT(*) c FROM clientes")->fetch_assoc()['c'] ?? 0),
  'reservas'   => (int)($db->query("SELECT COUNT(*) c FROM reservas")->fetch_assoc()['c'] ?? 0),
  'prodreq'  => (int)($db->query("SELECT COUNT(*) c FROM carrito_production_requests")->fetch_assoc()['c'] ?? 0),
];

// Listados
$clientes = [];
$reservas = [];
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
      $st = mysqli_prepare($db, "UPDATE reservas SET payment_status=? WHERE id=?");
      if (!$st) { throw new RuntimeException('SQL prepare error: '.mysqli_error($db)); }
      mysqli_stmt_bind_param($st, 'si', $newStatus, $id);
      mysqli_stmt_execute($st);
      mysqli_stmt_close($st);
      bo_audit('order_status', ['id'=>$id,'status'=>$newStatus]);
      header('Location: index.php?tab=pedidos&order_id='.$id);
      exit;
    }

    if ($action === 'prod_status') {
      $st = mysqli_prepare($db, "UPDATE carrito_production_requests SET status=? WHERE id=?");
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
  $sql = "SELECT id_cliente, rut, nombre, mail, telefono, region, comuna
          FROM clientes
          WHERE (?='' OR nombre LIKE ? OR mail LIKE ? OR rut LIKE ?)
          ORDER BY id_cliente DESC
          LIMIT 200";
  $st = mysqli_prepare($db, $sql);
  if (!$st) { throw new RuntimeException('SQL prepare error: '.mysqli_error($db)); }
  $qq = $q;
  $lk = like($q);
  mysqli_stmt_bind_param($st, 'ssss', $qq, $lk, $lk, $lk);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  while ($r = $rs->fetch_assoc()) $clientes[] = $r;
  mysqli_stmt_close($st);
}

if ($tab === 'pedidos') {
  $sql = "SELECT r.id, r.id_cliente, r.payment_status AS status, r.subtotal_clp, r.shipping_cost_clp, r.total_clp, r.created_at,
                 c.nombre AS customer_nombre, c.mail AS customer_email, c.telefono AS customer_telefono, c.rut AS customer_rut
          FROM reservas r
          LEFT JOIN clientes c ON c.id_cliente=r.id_cliente
          WHERE (?='' OR c.nombre LIKE ? OR c.mail LIKE ? OR c.rut LIKE ? OR c.telefono LIKE ? OR r.payment_status LIKE ?)
          ORDER BY r.id DESC
          LIMIT 200";
  $st = mysqli_prepare($db, $sql);
  if (!$st) { throw new RuntimeException('SQL prepare error: '.mysqli_error($db)); }
  $qq = $q;
  $lk = like($q);
  mysqli_stmt_bind_param($st, 'ssssss', $qq, $lk, $lk, $lk, $lk, $lk);
  mysqli_stmt_execute($st);
  $rs = mysqli_stmt_get_result($st);
  while ($r = $rs->fetch_assoc()) $reservas[] = $r;
  mysqli_stmt_close($st);
}

if ($tab === 'produccion') {
  $sql = "SELECT pr.id, pr.request_code, pr.id_cliente, pr.status, pr.total_units, pr.total_amount_clp, pr.created_at,
                 c.nombre AS customer_nombre, c.mail AS customer_email, c.telefono AS customer_telefono, c.rut AS customer_rut
          FROM carrito_production_requests pr
          LEFT JOIN clientes c ON c.id_cliente=pr.id_cliente
          WHERE (?='' OR pr.request_code LIKE ? OR CAST(pr.id_cliente AS CHAR) LIKE ? OR pr.status LIKE ? OR c.nombre LIKE ? OR c.mail LIKE ? OR c.rut LIKE ?)
          ORDER BY pr.id DESC
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
              <div class="kpi"><div class="t">Pedidos stock</div><div class="v"><?=number_format($kpi['reservas'],0,',','.')?></div></div>
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
              <?php foreach ($clientes as $c): ?>
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

            <?php if ($viewOrderId > 0): ?>
              <?php
                $st = mysqli_prepare($db, "SELECT r.*, c.nombre AS customer_nombre, c.mail AS customer_email, c.telefono AS customer_telefono, c.rut AS customer_rut
                                            FROM reservas r
                                            LEFT JOIN clientes c ON c.id_cliente=r.id_cliente
                                            WHERE r.id=?");
                if ($st) {
                  mysqli_stmt_bind_param($st, 'i', $viewOrderId);
                  mysqli_stmt_execute($st);
                  $res = mysqli_stmt_get_result($st);
                  $od = $res ? mysqli_fetch_assoc($res) : null;
                  mysqli_stmt_close($st);
                } else { $od = null; }

                // Obtener datos de transacción Webpay si existe
                $wpTx = null;
                if ($od && !empty($od['webpay_transaction_id'])) {
                  $stWp = mysqli_prepare($db, "SELECT * FROM webpay_transactions WHERE id = ?");
                  if ($stWp) {
                    $wpTxId = (int)$od['webpay_transaction_id'];
                    mysqli_stmt_bind_param($stWp, 'i', $wpTxId);
                    mysqli_stmt_execute($stWp);
                    $resWp = mysqli_stmt_get_result($stWp);
                    $wpTx = $resWp ? mysqli_fetch_assoc($resWp) : null;
                    mysqli_stmt_close($stWp);
                  }
                }

                // Obtener nombre de comuna desde starken_cache si shipping_commune es un code_dls
                $communeName = '';
                if ($od && !empty($od['shipping_commune'])) {
                  $communeCodeDls = (int)$od['shipping_commune'];
                  if ($communeCodeDls > 0) {
                    $stCache = mysqli_query($db, "SELECT communes_json FROM starken_cache WHERE id = 1 LIMIT 1");
                    if ($stCache && ($rowCache = mysqli_fetch_assoc($stCache))) {
                      $communes = json_decode((string)$rowCache['communes_json'], true);
                      if (is_array($communes)) {
                        foreach ($communes as $comm) {
                          if ((int)($comm['code_dls'] ?? 0) === $communeCodeDls) {
                            $communeName = (string)($comm['name'] ?? '');
                            if (!empty($comm['city_name']) && $comm['city_name'] !== $communeName) {
                              $communeName .= ' (' . $comm['city_name'] . ')';
                            }
                            break;
                          }
                        }
                      }
                    }
                  }
                  if (empty($communeName)) {
                    $communeName = (string)$od['shipping_commune']; // fallback al código
                  }
                }

                $items=[];
                if ($od) {
                  $st2 = mysqli_prepare($db, "SELECT v.nombre AS product_name, rp.cantidad AS qty,
                                              COALESCE(v.precio_detalle, v.precio, 0) AS unit_price_clp,
                                              (rp.cantidad * COALESCE(v.precio_detalle, v.precio, 0)) AS line_total_clp,
                                              GROUP_CONCAT(DISTINCT
                                                CASE
                                                  WHEN a.nombre IS NULL THEN NULL
                                                  WHEN a.nombre = 'TIPO DE PLANTA' THEN NULL
                                                  WHEN NULLIF(TRIM(av.valor),'') IS NULL THEN NULL
                                                  ELSE CONCAT(a.nombre, ': ', TRIM(av.valor))
                                                END
                                                ORDER BY a.nombre
                                                SEPARATOR '||'
                                              ) AS attrs_activos
                                              FROM reservas_productos rp
                                              LEFT JOIN variedades_producto v ON v.id = rp.id_variedad
                                              LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = rp.id_variedad
                                              LEFT JOIN atributos_valores av ON av.id = avv.id_atributo_valor
                                              LEFT JOIN atributos a ON a.id = av.id_atributo
                                              WHERE rp.id_reserva=?
                                              GROUP BY rp.id, rp.id_variedad, rp.cantidad, v.nombre, v.precio_detalle, v.precio
                                              ORDER BY rp.id ASC");
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
                      <div class="muted" style="font-weight:800">Detalle Reserva Stock</div>
                      <div class="h1" style="margin:0">#<?=bo_h((string)$od['id'])?></div>
                      <div class="muted" style="font-size:12px"><?=bo_h((string)$od['created_at'])?></div>
                    </div>
                    <a class="btn" href="index.php?tab=pedidos">Cerrar</a>
                  </div>
                  <div class="card-b">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                      <div>
                        <div class="small muted">Cliente</div>
                        <div style="font-weight:900"><?=bo_h((string)$od['customer_nombre'])?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)$od['customer_email'])?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)$od['customer_telefono'])?></div>
                        <div class="muted" style="font-size:12px"><?=bo_h((string)$od['customer_rut'])?></div>
                      </div>
                      <div>
                        <div class="small muted">Información de pago</div>
                        <div style="font-weight:900"><?=bo_h((string)$od['payment_method'] ?: 'No especificado')?></div>
                        <?php
                          $paymentStatusLabels = [
                            'paid' => 'Pagado',
                            'pending' => 'Pendiente de pago',
                            'failed' => 'Fallido',
                            'refunded' => 'Reembolsado'
                          ];
                          $statusLabel = $paymentStatusLabels[$od['payment_status']] ?? $od['payment_status'];
                        ?>
                        <div class="muted" style="font-size:12px">Estado: <span style="color:#111;background:<?=$od['payment_status']==='paid'?'#5fe5d0':($od['payment_status']==='failed'?'#f87171':'#fbbf24')?>;padding:2px 6px;border-radius:4px;font-weight:900"><?=bo_h($statusLabel)?></span></div>

                        <?php if ($od['payment_method'] === 'webpay' && $wpTx): ?>
                          <!-- Detalles de transacción Webpay desde webpay_transactions -->
                          <div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.1);font-size:12px">
                            <div class="muted" style="font-size:11px;margin-bottom:6px">Detalles Webpay:</div>

                            <?php if (!empty($wpTx['token'])): ?>
                              <div class="muted">Token: <span style="font-family:monospace;font-size:10px"><?=bo_h((string)$wpTx['token'])?></span></div>
                            <?php endif; ?>

                            <?php if (!empty($wpTx['buy_order'])): ?>
                              <div class="muted">Orden: <b><?=bo_h((string)$wpTx['buy_order'])?></b></div>
                            <?php endif; ?>

                            <?php if (!empty($wpTx['authorization_code'])): ?>
                              <div class="muted">Código Auth: <b><?=bo_h((string)$wpTx['authorization_code'])?></b></div>
                            <?php endif; ?>

                            <?php if (!empty($wpTx['transaction_date'])): ?>
                              <div class="muted">Fecha Transacción: <?=bo_h((string)$wpTx['transaction_date'])?></div>
                            <?php endif; ?>

                            <?php if (!empty($wpTx['card_number'])): ?>
                              <div class="muted">Tarjeta: <b>****<?=bo_h((string)$wpTx['card_number'])?></b></div>
                            <?php endif; ?>

                            <?php if (!empty($wpTx['payment_type_code'])): ?>
                              <?php
                                $paymentTypes = [
                                  'VD' => 'Débito',
                                  'VN' => 'Crédito (sin cuotas)',
                                  'VC' => 'Crédito (cuotas)',
                                  'SI' => 'Crédito (sin interés)',
                                  'S2' => 'Crédito (2 cuotas sin interés)',
                                  'NC' => 'Crédito (N cuotas sin interés)',
                                ];
                                $typeLabel = $paymentTypes[$wpTx['payment_type_code']] ?? $wpTx['payment_type_code'];
                              ?>
                              <div class="muted">Tipo pago: <b><?=bo_h($typeLabel)?></b></div>
                            <?php endif; ?>

                            <?php if ((int)($wpTx['installments_number'] ?? 0) > 0): ?>
                              <div class="muted">Cuotas: <b><?=(int)$wpTx['installments_number']?></b></div>
                            <?php endif; ?>

                            <?php if (!empty($wpTx['vci'])): ?>
                              <div class="muted">VCI: <?=bo_h((string)$wpTx['vci'])?></div>
                            <?php endif; ?>

                            <?php if (!empty($wpTx['status'])): ?>
                              <div class="muted">Estado Transbank: <b style="color:<?=$wpTx['status']==='AUTHORIZED'?'#5fe5d0':'#f87171'?>"><?=bo_h((string)$wpTx['status'])?></b></div>
                            <?php endif; ?>

                            <?php if (isset($wpTx['response_code'])): ?>
                              <div class="muted">Código respuesta: <?=(int)$wpTx['response_code']?> <?=$wpTx['response_code']==0?'✓':''?></div>
                            <?php endif; ?>

                            <?php if ((int)($wpTx['amount'] ?? 0) > 0): ?>
                              <div class="muted" style="margin-top:6px;padding-top:6px;border-top:1px solid rgba(255,255,255,.1)">Monto: <b style="color:#5fe5d0">$<?=number_format((int)$wpTx['amount'],0,',','.')?></b></div>
                            <?php endif; ?>

                            <?php if (!empty($wpTx['confirmed_at'])): ?>
                              <div class="muted" style="font-size:10px;margin-top:4px">Confirmado: <?=bo_h((string)$wpTx['confirmed_at'])?></div>
                            <?php endif; ?>
                          </div>
                        <?php elseif (!empty($od['webpay_transaction_id'])): ?>
                          <div class="muted" style="font-size:12px">ID Transacción: <?=bo_h((string)$od['webpay_transaction_id'])?></div>
                        <?php endif; ?>
                      </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
                      <div>
                        <div class="small muted">Método de envío</div>
                        <div style="font-weight:900"><?=bo_h((string)$od['shipping_method'] ?: 'No especificado')?></div>
                        <?php if ($od['shipping_method'] === 'agencia' && !empty($od['shipping_agency_name'])): ?>
                          <div class="muted" style="font-size:12px">Agencia: <?=bo_h((string)$od['shipping_agency_name'])?></div>
                          <?php if (!empty($od['shipping_agency_address'])): ?>
                            <div class="muted" style="font-size:12px">Dirección: <?=bo_h((string)$od['shipping_agency_address'])?></div>
                          <?php endif; ?>
                          <?php if (!empty($od['shipping_agency_code_dls'])): ?>
                            <div class="muted" style="font-size:12px">Código: <?=bo_h((string)$od['shipping_agency_code_dls'])?></div>
                          <?php endif; ?>
                        <?php elseif ($od['shipping_method'] === 'domicilio' && !empty($od['shipping_address'])): ?>
                          <div class="muted" style="font-size:12px">Dirección: <?=bo_h((string)$od['shipping_address'])?></div>
                          <?php if (!empty($communeName)): ?>
                            <div class="muted" style="font-size:12px">Comuna: <?=bo_h($communeName)?></div>
                          <?php endif; ?>
                        <?php elseif ($od['shipping_method'] === 'vivero'): ?>
                          <div class="muted" style="font-size:12px">Retiro en vivero</div>
                        <?php endif; ?>
                      </div>
                      <div>
                        <div class="small muted">Montos</div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                          <span>Subtotal:</span>
                          <span style="font-weight:900">$<?=number_format((int)$od['subtotal_clp'],0,',','.')?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                          <span>Packing:</span>
                          <span style="font-weight:900">$<?=number_format((int)$od['packing_cost_clp'],0,',','.')?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:8px">
                          <span>Envío:</span>
                          <span style="font-weight:900">$<?=number_format((int)$od['shipping_cost_clp'],0,',','.')?></span>
                        </div>
                        <div style="border-top:1px solid rgba(255,255,255,.2);padding-top:8px;display:flex;justify-content:space-between">
                          <span style="font-weight:900">TOTAL:</span>
                          <span style="font-weight:900;font-size:16px;color:#5fe5d0">$<?=number_format((int)$od['total_clp'],0,',','.')?></span>
                        </div>
                        <?php if ((int)$od['paid_clp'] > 0): ?>
                          <div style="margin-top:8px;padding-top:8px;border-top:1px solid rgba(255,255,255,.2);display:flex;justify-content:space-between">
                            <span>Pagado:</span>
                            <span style="font-weight:900">$<?=number_format((int)$od['paid_clp'],0,',','.')?></span>
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>

                    <?php if (!empty($od['observaciones'])): ?>
                      <div style="background:rgba(255,255,255,.04);padding:12px;border-radius:10px;margin-bottom:16px;border-left:3px solid #f39c12">
                        <div class="small muted">Observaciones</div>
                        <div style="font-size:13px;line-height:1.5;margin-top:6px;white-space:pre-wrap"><?=bo_h((string)$od['observaciones'])?></div>
                      </div>
                    <?php endif; ?>

                    <div style="height:12px"></div>

                    <div class="small muted" style="margin-bottom:8px">Productos comprados</div>
                    <table class="table">
                      <thead><tr><th>Producto</th><th>Cant.</th><th>Precio Unit (IVA inc.)</th><th>Total</th></tr></thead>
                      <tbody>
                        <?php
                        $subtotalProductos = 0;
                        foreach($items as $it):
                          // Procesar atributos
                          $attrsRaw = (string)($it['attrs_activos'] ?? '');
                          $attrsHtml = '';
                          if (!empty($attrsRaw)) {
                            $attrs = array_filter(array_map('trim', explode('||', $attrsRaw)));
                            if (!empty($attrs)) {
                              $attrsHtml = '<div style="margin-top:4px;font-size:11px;color:#999">';
                              foreach ($attrs as $attr) {
                                $attrsHtml .= '<div>' . bo_h($attr) . '</div>';
                              }
                              $attrsHtml .= '</div>';
                            }
                          }

                          // Aplicar descuento de atributos si existe
                          $priceUnitario = (int)$it['unit_price_clp'];
                          $discountInfo = apply_discount_from_attrs($priceUnitario, $attrsRaw);
                          $precioFinal = $discountInfo['final_price'];
                          $precioConIvaFinal = round($precioFinal * 1.19);
                          $totalConIvaFinal = (int)$it['qty'] * $precioConIvaFinal;
                          $subtotalProductos += $totalConIvaFinal;

                          // Mostrar precio original tachado si hay descuento
                          $priceHtml = '$' . number_format($precioConIvaFinal, 0, ',', '.');
                          if ($discountInfo['discount_amount'] > 0) {
                            $discountPercent = $discountInfo['discount_percent'];
                            $originalPrice = round($priceUnitario * 1.19);
                            $originalTotal = (int)$it['qty'] * $originalPrice;
                            $discountLabel = $discountPercent > 0 ? "-{$discountPercent}%" : "-$" . number_format($discountInfo['discount_amount'], 0, ',', '.');
                            $priceHtml = '<div style="text-decoration:line-through;color:#999;font-size:12px">$' . number_format($originalPrice, 0, ',', '.') . '</div>'
                                      . '<div style="color:#5fe5d0;font-weight:bold">$' . number_format($precioConIvaFinal, 0, ',', '.') . '</div>'
                                      . '<div style="font-size:11px;color:#f39c12">' . $discountLabel . '</div>';
                          }
                        ?>
                          <tr>
                            <td>
                              <div><?=bo_h((string)$it['product_name'])?></div>
                              <?=$attrsHtml?>
                            </td>
                            <td><?=bo_h((string)$it['qty'])?></td>
                            <td><?=$priceHtml?></td>
                            <td>$<?=number_format($totalConIvaFinal,0,',','.')?></td>
                          </tr>
                        <?php endforeach; ?>

                        <?php if ((int)$od['packing_cost_clp'] > 0): ?>
                          <tr style="border-top:1px solid rgba(255,255,255,0.1)">
                            <td><i>Packing</i></td>
                            <td>1</td>
                            <td>$<?=number_format((int)$od['packing_cost_clp'],0,',','.')?></td>
                            <td>$<?=number_format((int)$od['packing_cost_clp'],0,',','.')?></td>
                          </tr>
                        <?php endif; ?>

                        <?php if (!count($items)): ?><tr><td colspan="4" class="muted">Sin ítems.</td></tr><?php endif; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <table class="table">
                <thead><tr>
                  <th>ID</th><th>Cliente</th><th>Estado</th><th>Subtotal</th><th>Envío</th><th>Total</th><th>Fecha</th><th></th>
                </tr></thead>
                <tbody>
                <?php foreach ($reservas as $o): ?>
                  <tr>
                    <td><a href="index.php?tab=pedidos&order_id=<?=bo_h((string)$o['id'])?>"><?=bo_h((string)$o['id'])?></a></td>
                    <td>
                      <div style="font-weight:800"><?=bo_h((string)$o['customer_nombre'])?></div>
                      <div class="muted" style="font-size:12px"><?=bo_h((string)$o['customer_email'])?></div>
                      <div class="muted" style="font-size:12px"><?=bo_h((string)$o['customer_telefono'])?></div>
                    </td>
                    <td>
                      <span style="color:#111;background:<?=bo_payment_status_color((string)$o['status'])?>;padding:2px 8px;border-radius:4px;font-weight:900;font-size:11px;white-space:nowrap">
                        <?=bo_h(bo_payment_status_label((string)$o['status']))?>
                      </span>
                    </td>
                    <td>$<?=number_format((int)$o['subtotal_clp'],0,',','.')?></td>
                    <td>$<?=number_format((int)$o['shipping_cost_clp'],0,',','.')?></td>
                    <td><b>$<?=number_format((int)$o['total_clp'],0,',','.')?></b></td>
                    <td><?=bo_h((string)$o['created_at'])?></td>
                    <td><a class="btn" href="index.php?tab=pedidos&order_id=<?=bo_h((string)$o['id'])?>">Detalle</a></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
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
                    <div style="font-weight:800"><?=bo_h((string)($p['customer_nombre'] ?? ('ID '.$p['id_cliente'])))?></div>
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
                $st = mysqli_prepare($db, "SELECT pr.*, c.nombre AS customer_nombre, c.mail AS customer_email, c.telefono AS customer_telefono, c.rut AS customer_rut
                                            FROM carrito_production_requests pr
                                            LEFT JOIN clientes c ON c.id_cliente=pr.id_cliente
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
                  $st2 = mysqli_prepare($db, "SELECT product_name, product_id, qty, unit_price_clp, line_total_clp FROM carrito_production_request_items WHERE request_id=? ORDER BY id ASC");
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
