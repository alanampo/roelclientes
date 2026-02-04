<?php
declare(strict_types=1);
// catalogo_detalle/api/order/detail.php
require __DIR__ . '/../_bootstrap.php';

$cid = require_auth();
$db = db();

function table_has_column(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  if (!$res) return false;
  return (bool)$res->fetch_assoc();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) bad_request('ID inválido');

$hasV2 = table_has_column($db,'orders','order_code') && table_has_column($db,'orders','customer_id');
$itemsV2 = table_has_column($db,'order_items','referencia'); // v2
if ($hasV2) {
  $sql = "SELECT id, order_code, status, subtotal_clp, shipping_label, shipping_cost_clp, total_clp, notes, created_at
          FROM orders WHERE id=? AND customer_id=? LIMIT 1";
  $st = $db->prepare($sql);
  if (!$st) json_out(['ok'=>false,'error'=>'No se pudo preparar consulta'],500);
  $st->bind_param('ii',$id,$cid);
  $st->execute();
  $o = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$o) unauthorized('Pedido no encontrado');

  $items = [];
  if ($itemsV2) {
    $st = $db->prepare("SELECT referencia, nombre, imagen_url, unit_price_clp, qty, line_total_clp FROM order_items WHERE order_id=? ORDER BY id ASC");
    if (!$st) json_out(['ok'=>false,'error'=>'No se pudo preparar items'],500);
    $st->bind_param('i',$id);
    $st->execute();
    $res = $st->get_result();
    while($r=$res->fetch_assoc()){
      $items[] = [
        'ref'=>(string)($r['referencia']??''),
        'name'=>(string)($r['nombre']??''),
        'image_url'=>(string)($r['imagen_url']??''),
        'unit_price_clp'=>(int)($r['unit_price_clp']??0),
        'qty'=>(int)($r['qty']??0),
        'line_total_clp'=>(int)($r['line_total_clp']??0),
      ];
    }
    $st->close();
  }

  json_out([
    'ok'=>true,
    'schema'=>'v2',
    'order'=>[
      'id'=>(int)$o['id'],
      'order_code'=>(string)($o['order_code']??''),
      'status'=>(string)($o['status']??''),
      'subtotal_clp'=>(int)($o['subtotal_clp']??0),
      'shipping_label'=>(string)($o['shipping_label']??''),
      'shipping_cost_clp'=>(int)($o['shipping_cost_clp']??0),
      'total_clp'=>(int)($o['total_clp']??0),
      'notes'=>(string)($o['notes']??''),
      'created_at'=>(string)($o['created_at']??''),
    ],
    'items'=>$items,
  ]);
} else {
  $sql = "SELECT id, status, shipping_label, shipping_amount, subtotal_amount, total_amount, notes, created_at
          FROM orders WHERE id=? AND user_id=? LIMIT 1";
  $st = $db->prepare($sql);
  if (!$st) json_out(['ok'=>false,'error'=>'No se pudo preparar consulta'],500);
  $st->bind_param('ii',$id,$cid);
  $st->execute();
  $o = $st->get_result()->fetch_assoc();
  $st->close();
  if (!$o) unauthorized('Pedido no encontrado');

  $items = [];
  $st = $db->prepare("SELECT product_ref, product_name, image_url, unit_price, qty, line_total FROM order_items WHERE order_id=? ORDER BY id ASC");
  if (!$st) json_out(['ok'=>false,'error'=>'No se pudo preparar items'],500);
  $st->bind_param('i',$id);
  $st->execute();
  $res = $st->get_result();
  while($r=$res->fetch_assoc()){
    $items[] = [
      'ref'=>(string)($r['product_ref']??''),
      'name'=>(string)($r['product_name']??''),
      'image_url'=>(string)($r['image_url']??''),
      'unit_price_clp'=>(int)($r['unit_price']??0),
      'qty'=>(int)($r['qty']??0),
      'line_total_clp'=>(int)($r['line_total']??0),
    ];
  }
  $st->close();

  json_out([
    'ok'=>true,
    'schema'=>'legacy',
    'order'=>[
      'id'=>(int)$o['id'],
      'order_code'=>'',
      'status'=>(string)($o['status']??''),
      'subtotal_clp'=>(int)($o['subtotal_amount']??0),
      'shipping_label'=>(string)($o['shipping_label']??''),
      'shipping_cost_clp'=>(int)($o['shipping_amount']??0),
      'total_clp'=>(int)($o['total_amount']??0),
      'notes'=>(string)($o['notes']??''),
      'created_at'=>(string)($o['created_at']??''),
    ],
    'items'=>$items,
  ]);
}
