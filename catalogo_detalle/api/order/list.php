<?php
declare(strict_types=1);
// catalogo_detalle/api/order/list.php
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

$hasV2 = table_has_column($db, ORDERS_TABLE, 'order_code') && table_has_column($db, ORDERS_TABLE, 'customer_id');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
if ($limit < 1) $limit = 50;
if ($limit > 200) $limit = 200;

if ($hasV2) {
  $sql = "SELECT id, order_code, status, subtotal_clp, shipping_cost_clp, total_clp, created_at
          FROM " . ORDERS_TABLE . "
          WHERE customer_id = ?
          ORDER BY id DESC
          LIMIT ?";
  $st = $db->prepare($sql);
  if (!$st) json_out(['ok'=>false,'error'=>'No se pudo preparar consulta'],500);
  $st->bind_param('ii',$cid,$limit);
  $st->execute();
  $res = $st->get_result();
  $rows = [];
  while($r = $res->fetch_assoc()){
    $rows[] = [
      'id'=>(int)$r['id'],
      'order_code'=>(string)($r['order_code']??''),
      'status'=>(string)($r['status']??''),
      'subtotal_clp'=>(int)($r['subtotal_clp']??0),
      'shipping_cost_clp'=>(int)($r['shipping_cost_clp']??0),
      'total_clp'=>(int)($r['total_clp']??0),
      'created_at'=>(string)($r['created_at']??''),
    ];
  }
  $st->close();
  json_out(['ok'=>true,'schema'=>'v2','orders'=>$rows]);
} else {
  $sql = "SELECT id, status, shipping_label, shipping_amount, subtotal_amount, total_amount, created_at
          FROM " . ORDERS_TABLE . "
          WHERE user_id = ?
          ORDER BY id DESC
          LIMIT ?";
  $st = $db->prepare($sql);
  if (!$st) json_out(['ok'=>false,'error'=>'No se pudo preparar consulta'],500);
  $st->bind_param('ii',$cid,$limit);
  $st->execute();
  $res = $st->get_result();
  $rows = [];
  while($r = $res->fetch_assoc()){
    $rows[] = [
      'id'=>(int)$r['id'],
      'order_code'=>'',
      'status'=>(string)($r['status']??''),
      'subtotal_clp'=>(int)($r['subtotal_amount']??0),
      'shipping_cost_clp'=>(int)($r['shipping_amount']??0),
      'total_clp'=>(int)($r['total_amount']??0),
      'created_at'=>(string)($r['created_at']??''),
      'shipping_label'=>(string)($r['shipping_label']??''),
    ];
  }
  $st->close();
  json_out(['ok'=>true,'schema'=>'legacy','orders'=>$rows]);
}
