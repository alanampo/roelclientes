<?php
declare(strict_types=1);
require_once __DIR__ . '/notify_lib.php';

NotifyLib::$DEBUG = in_array('--debug', $argv ?? [], true) || isset($_GET['debug']);

$chatIds = NotifyLib::chatIds('new_customer');
$db = NotifyLib::db();

$stateFile = '.last_cart_customer_id';
$lastId = (int)NotifyLib::stateRead($stateFile, 0);

$sql = "SELECT id, rut, nombre, telefono, region, comuna, email, created_at
        FROM customers
        WHERE id > :last
        ORDER BY id ASC
        LIMIT 200";
$st = $db->prepare($sql);
$st->execute([':last' => $lastId]);
$rows = $st->fetchAll();

if (!$rows) {
    NotifyLib::out("NO_NEW\n");
    return;
}

$max = $lastId;

foreach ($rows as $c) {
    $id = (int)$c['id'];
    $rut = NotifyLib::esc($c['rut'] ?? '');
    $nombre = NotifyLib::esc($c['nombre'] ?? '');
    $tel = NotifyLib::esc($c['telefono'] ?? '');
    $reg = NotifyLib::esc($c['region'] ?? '');
    $com = NotifyLib::esc($c['comuna'] ?? '');
    $email = NotifyLib::esc($c['email'] ?? '');
    $fecha = '';
    try { $fecha = (new DateTime((string)($c['created_at'] ?? '')))->format('d/m/Y H:i'); } catch (Throwable $e) {}

    $msg  = "👤✨ <b>Nuevo cliente registrado (Carrito)</b>\n";
    $msg .= "🆔 <b>ID:</b> {$id}\n";
    if ($rut !== '') $msg .= "📄 <b>RUT:</b> {$rut}\n";
    if ($nombre !== '') $msg .= "👤 <b>Nombre:</b> {$nombre}\n";
    if ($tel !== '') $msg .= "📞 <b>Tel:</b> {$tel}\n";
    if ($email !== '') $msg .= "✉️ <b>Email:</b> {$email}\n";
    if ($reg !== '' || $com !== '') $msg .= "📍 <b>Ubicación:</b> {$com} / {$reg}\n";
    if ($fecha !== '') $msg .= "🗓️ <b>Fecha:</b> {$fecha}\n";

    NotifyLib::tgSend($chatIds, $msg);

    if ($id > $max) $max = $id;
}

if (!NotifyLib::$DEBUG) NotifyLib::stateWrite($stateFile, $max);
NotifyLib::out("DONE\n");
