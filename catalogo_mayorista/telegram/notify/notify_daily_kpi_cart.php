<?php
declare(strict_types=1);
require_once __DIR__ . '/notify_lib.php';

NotifyLib::$DEBUG = in_array('--debug', $argv ?? [], true) || isset($_GET['debug']);
$chatIds = NotifyLib::chatIds('kpi');
$db = NotifyLib::db();

$today = (new DateTime('now'))->format('Y-m-d');
$stateFile = '.last_kpi_date';
$lastDate = (string)NotifyLib::stateRead($stateFile, '');

// Evitar duplicado diario salvo debug
if (!NotifyLib::$DEBUG && $lastDate === $today) {
    NotifyLib::out("ALREADY_SENT\n");
    return;
}

$start = $today . ' 00:00:00';
$end   = $today . ' 23:59:59';

$k = [];
$k['customers_today'] = (int)$db->query("SELECT COUNT(*) FROM customers WHERE created_at BETWEEN '{$start}' AND '{$end}'")->fetchColumn();
$k['orders_today'] = (int)$db->query("SELECT COUNT(*) FROM orders_may WHERE created_at BETWEEN '{$start}' AND '{$end}'")->fetchColumn();
$k['revenue_today'] = (int)$db->query("SELECT COALESCE(SUM(total_clp),0) FROM orders_may WHERE created_at BETWEEN '{$start}' AND '{$end}'")->fetchColumn();
$k['open_carts'] = (int)$db->query("SELECT COUNT(*) FROM carts_may WHERE status='open'")->fetchColumn();
$k['abandoned_carts'] = (int)$db->query("SELECT COUNT(*) FROM carts_may WHERE status='abandoned'")->fetchColumn();

$msg  = "📊 <b>KPI Carrito (".$today.")</b>\n";
$msg .= "👤 <b>Clientes nuevos:</b> ".$k['customers_today']."\n";
$msg .= "🛒 <b>Pedidos:</b> ".$k['orders_today']."\n";
$msg .= "💰 <b>Ventas:</b> ".number_format($k['revenue_today'],0,',','.')." CLP\n";
$msg .= "🧺 <b>Carritos abiertos:</b> ".$k['open_carts']."\n";
$msg .= "⚠️ <b>Carritos abandonados:</b> ".$k['abandoned_carts']."\n";

NotifyLib::tgSend($chatIds, $msg);

if (!NotifyLib::$DEBUG) NotifyLib::stateWrite($stateFile, $today);
NotifyLib::out("DONE\n");
