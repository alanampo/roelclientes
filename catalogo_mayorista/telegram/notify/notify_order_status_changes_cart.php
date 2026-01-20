<?php
declare(strict_types=1);
require_once __DIR__ . '/notify_lib.php';

NotifyLib::$DEBUG = in_array('--debug', $argv ?? [], true) || isset($_GET['debug']);
$chatIds = NotifyLib::chatIds('status');
$db = NotifyLib::db();

$stateFile = '.state_order_status.json';
$state = NotifyLib::stateRead($stateFile, []);
if (!is_array($state)) $state = [];

// Tomamos órdenes recientes para detectar cambios
$sql = "SELECT id, order_code, customer_nombre, status, updated_at, created_at, total_clp
        FROM orders_may
        ORDER BY id DESC
        LIMIT 200";
$rows = $db->query($sql)->fetchAll();

if (!$rows) { NotifyLib::out("NO_NEW\n"); return; }

$changed = 0;

foreach (array_reverse($rows) as $o) {
    $id = (int)$o['id'];
    $status = (string)($o['status'] ?? '');
    $prev = $state[(string)$id] ?? null;

    if ($prev === null) {
        // primera vez que vemos esta orden -> solo registrar sin notificar
        $state[(string)$id] = $status;
        continue;
    }

    if ($prev !== $status) {
        $changed++;
        $code = NotifyLib::esc($o['order_code'] ?? '');
        $cliente = NotifyLib::esc($o['customer_nombre'] ?? '');
        $total = (int)($o['total_clp'] ?? 0);
        $fecha = '';
        try { $fecha = (new DateTime((string)($o['updated_at'] ?? $o['created_at'] ?? '')))->format('d/m/Y H:i'); } catch (Throwable $e) {}

        $msg  = "🔄📦 <b>Cambio de estado pedido #{$id}</b>\n";
        if ($code !== '') $msg .= "🔖 <b>Código:</b> {$code}\n";
        if ($cliente !== '') $msg .= "👤 <b>Cliente:</b> {$cliente}\n";
        if ($fecha !== '') $msg .= "🗓️ <b>Fecha:</b> {$fecha}\n";
        $msg .= "📌 <b>Estado:</b> ".NotifyLib::esc($prev)." ➜ <b>".NotifyLib::esc($status)."</b>\n";
        $msg .= "💳 <b>Total:</b> ".number_format($total,0,',','.')." CLP\n";

        NotifyLib::tgSend($chatIds, $msg);

        $state[(string)$id] = $status;
    }
}

// Reducir tamaño del estado
if (count($state) > 500) {
    // conservar últimas 300 ids numéricas más altas
    $keys = array_keys($state);
    usort($keys, fn($a,$b)=> (int)$b <=> (int)$a);
    $keep = array_slice($keys, 0, 300);
    $new = [];
    foreach ($keep as $k) $new[$k] = $state[$k];
    $state = $new;
}

if (!NotifyLib::$DEBUG) NotifyLib::stateWrite($stateFile, $state);

NotifyLib::out($changed ? "DONE changed={$changed}\n" : "NO_CHANGES\n");
