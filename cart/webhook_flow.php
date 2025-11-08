<?php
declare(strict_types=1);
require __DIR__ . '/cart_helpers.php';

$pdo = db();
// Store raw
$payload = file_get_contents('php://input') ?: '';
$headers = json_encode(getallheaders());
$provider = 'flow';
$event_type = $_GET['event'] ?? null;
$order_number = $_GET['commerceOrder'] ?? null;

$pdo->prepare("INSERT INTO webhooks (provider, event_type, event_id, order_id, signature, headers, payload, processed, created_at) VALUES (?,?,?,?,?,?,?,0,NOW())")
    ->execute([$provider, $event_type, null, null, $_SERVER['HTTP_X_SIGNATURE'] ?? null, $headers, $payload]);
$wh_id = intval($pdo->lastInsertId());

// TODO: validate signature and parse payload based on Flow docs.
// For now, try to update payments+orders by order_number if provided.
if ($order_number) {
  $st = $pdo->prepare("SELECT id FROM orders WHERE order_number=?");
  $st->execute([$order_number]);
  if ($ord = $st->fetch()) {
    $oid = intval($ord['id']);
    // naive update
    $pdo->prepare("UPDATE payments SET status='paid', paid_at=NOW(), updated_at=NOW() WHERE order_id=? AND method='flow'")->execute([$oid]);
    $pdo->prepare("UPDATE orders SET payment_status='paid', status='paid', paid_at=NOW(), updated_at=NOW() WHERE id=?")->execute([$oid]);
    $pdo->prepare("UPDATE webhooks SET order_id=?, processed=1 WHERE id=?")->execute([$oid, $wh_id]);
  }
}

http_response_code(200);
echo 'OK';
