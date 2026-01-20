<?php
declare(strict_types=1);
require_once __DIR__ . '/notify_lib.php';

NotifyLib::$DEBUG = in_array('--debug', $argv ?? [], true) || isset($_GET['debug']);
$chatIds = NotifyLib::chatIds('abandoned');
$db = NotifyLib::db();

$stateFile = '.last_abandoned_cart_id';
$lastId = (int)NotifyLib::stateRead($stateFile, 0);

// Abandonado explícito (status=abandoned). Si quieres por tiempo, ajustamos luego.
$sql = "SELECT c.id, c.customer_id, c.status, c.created_at, c.updated_at,
               cu.nombre AS customer_nombre, cu.telefono AS customer_telefono, cu.comuna AS customer_comuna, cu.region AS customer_region
        FROM carts_may c
        LEFT JOIN customers cu ON cu.id = c.customer_id
        WHERE c.status = 'abandoned' AND c.id > :last
        ORDER BY c.id ASC
        LIMIT 100";
$st = $db->prepare($sql);
$st->execute([':last' => $lastId]);
$carts_may = $st->fetchAll();

if (!$carts_may) { NotifyLib::out("NO_NEW\n"); return; }

$max = $lastId;

$stCount = $db->prepare("SELECT COALESCE(SUM(qty),0) AS qty_total, COUNT(*) AS lines
                         FROM cart_items_may
                         WHERE cart_id = :cid");

foreach ($carts_may as $c) {
    $id = (int)$c['id'];
    $cust = NotifyLib::esc($c['customer_nombre'] ?? '');
    $tel = NotifyLib::esc($c['customer_telefono'] ?? '');
    $loc = trim(NotifyLib::esc(($c['customer_comuna'] ?? '').' / '.($c['customer_region'] ?? '')));
    $fecha = '';
    try { $fecha = (new DateTime((string)($c['updated_at'] ?? $c['created_at'] ?? '')))->format('d/m/Y H:i'); } catch (Throwable $e) {}

    $stCount->execute([':cid' => $id]);
    $cnt = $stCount->fetch() ?: [];
    $qtyTot = (int)($cnt['qty_total'] ?? 0);
    $lines = (int)($cnt['lines'] ?? 0);

    $msg  = "⚠️🧺 <b>Carrito ABANDONADO #{$id}</b>\n";
    if ($fecha !== '') $msg .= "🗓️ <b>Últ. actualización:</b> {$fecha}\n";
    if ($cust !== '') $msg .= "👤 <b>Cliente:</b> {$cust}\n";
    if ($tel !== '') $msg .= "📞 <b>Tel:</b> {$tel}\n";
    if ($loc !== '/') $msg .= "📍 <b>Ubicación:</b> {$loc}\n";
    $msg .= "🧾 <b>Ítems:</b> {$lines} líneas / {$qtyTot} unidades\n";

    NotifyLib::tgSend($chatIds, $msg);
    if ($id > $max) $max = $id;
}

if (!NotifyLib::$DEBUG) NotifyLib::stateWrite($stateFile, $max);
NotifyLib::out("DONE\n");
