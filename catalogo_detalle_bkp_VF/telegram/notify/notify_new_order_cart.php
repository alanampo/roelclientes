<?php
declare(strict_types=1);
require_once __DIR__ . '/notify_lib.php';

NotifyLib::$DEBUG = in_array('--debug', $argv ?? [], true) || isset($_GET['debug']);
$chatIds = NotifyLib::chatIds('new_order');
$db = NotifyLib::db();

$stateFile = '.last_cart_order_id';
$lastId = (int)NotifyLib::stateRead($stateFile, 0);

$sql = "SELECT id, order_code, customer_nombre, customer_telefono, customer_comuna, customer_region,
               subtotal_clp, shipping_label, shipping_cost_clp, total_clp, status, created_at
        FROM orders
        WHERE id > :last
        ORDER BY id ASC
        LIMIT 50";
$st = $db->prepare($sql);
$st->execute([':last' => $lastId]);
$orders = $st->fetchAll();

if (!$orders) { NotifyLib::out("NO_NEW\n"); return; }

$max = $lastId;

$stItems = $db->prepare("SELECT referencia, nombre, qty, unit_price_clp, line_total_clp
                         FROM order_items
                         WHERE order_id = :oid
                         ORDER BY id ASC
                         LIMIT 200");

foreach ($orders as $o) {
    $id = (int)$o['id'];
    $code = NotifyLib::esc($o['order_code'] ?? '');
    $cliente = NotifyLib::esc($o['customer_nombre'] ?? '');
    $tel = NotifyLib::esc($o['customer_telefono'] ?? '');
    $loc = trim(NotifyLib::esc(($o['customer_comuna'] ?? '').' / '.($o['customer_region'] ?? '')));
    $subtotal = (int)($o['subtotal_clp'] ?? 0);
    $shipLbl = NotifyLib::esc($o['shipping_label'] ?? '');
    $shipCost = (int)($o['shipping_cost_clp'] ?? 0);
    $total = (int)($o['total_clp'] ?? 0);
    $status = NotifyLib::esc($o['status'] ?? '');
    $fecha = '';
    try { $fecha = (new DateTime((string)($o['created_at'] ?? '')))->format('d/m/Y H:i'); } catch (Throwable $e) {}

    $stItems->execute([':oid' => $id]);
    $items = $stItems->fetchAll();

    $msg  = "🛒🆕 <b>Nuevo pedido (Carrito) #{$id}</b>\n";
    if ($code !== '') $msg .= "🔖 <b>Código:</b> {$code}\n";
    if ($fecha !== '') $msg .= "🗓️ <b>Fecha:</b> {$fecha}\n";
    if ($cliente !== '') $msg .= "👤 <b>Cliente:</b> {$cliente}\n";
    if ($tel !== '') $msg .= "📞 <b>Tel:</b> {$tel}\n";
    if ($loc !== '/') $msg .= "📍 <b>Ubicación:</b> {$loc}\n";
    $msg .= "📦 <b>Envío:</b> ".($shipLbl !== '' ? $shipLbl : 'Por pagar')." (".number_format($shipCost,0,',','.')." CLP)\n";
    $msg .= "💰 <b>Subtotal:</b> ".number_format($subtotal,0,',','.')." CLP\n";
    $msg .= "💳 <b>Total:</b> ".number_format($total,0,',','.')." CLP\n";
    if ($status !== '') $msg .= "📌 <b>Estado:</b> {$status}\n";
    $msg .= "\n<b>Detalle:</b>\n";

    if ($items) {
        foreach ($items as $it) {
            $ref = NotifyLib::esc($it['referencia'] ?? '');
            $nom = NotifyLib::esc($it['nombre'] ?? '');
            $qty = (int)($it['qty'] ?? 0);
            $unit = (int)($it['unit_price_clp'] ?? 0);
            $line = (int)($it['line_total_clp'] ?? 0);
            $msg .= "• <b>{$nom}</b> ".($ref!==''? "({$ref})":"")."\n";
            $msg .= "   Qty: {$qty} | Unit: ".number_format($unit,0,',','.')." | Line: ".number_format($line,0,',','.')."\n";
        }
    } else {
        $msg .= "(sin ítems)\n";
    }

    NotifyLib::tgSend($chatIds, $msg);
    if ($id > $max) $max = $id;
}

if (!NotifyLib::$DEBUG) NotifyLib::stateWrite($stateFile, $max);
NotifyLib::out("DONE\n");
