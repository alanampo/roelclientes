<?php
/**
 * notify_new_cart.php
 * Notifica carritos NUEVOS creados en BD del carrito.
 *
 * Requiere:
 * - notify_lib.php (tu versión actual)
 * - Tablas: carts_may, cart_items_may (y opcional customers)
 *
 * Uso:
 *   php notify_new_cart.php
 *   php notify_new_cart.php --debug
 */

declare(strict_types=1);
require_once __DIR__ . '/notify_lib.php';

// Debug: imprime, NO envía TG y NO escribe estado (según tu patrón actual)
NotifyLib::$DEBUG = in_array('--debug', $argv ?? [], true) || isset($_GET['debug']);

// Chats destino (usa el mismo grupo que clientes/pedidos)
$chatIds = NotifyLib::loadChatIds('ops');

// DB
$db = NotifyLib::cartDb();

// Estado
$stateDir  = __DIR__ . '/state';
NotifyLib::ensureStateDir($stateDir);
$stateFile = $stateDir . '/.last_cart_id';

$lastId = (int)NotifyLib::stateRead($stateFile, 0);

// 1) Carritos nuevos
$sql = "SELECT id, customer_id, status, created_at, updated_at
        FROM carts_may
        WHERE id > :last
        ORDER BY id ASC
        LIMIT 200";
$st = $db->prepare($sql);
$st->execute([':last' => $lastId]);
$carts_may = $st->fetchAll(PDO::FETCH_ASSOC);

if (!$carts_may) {
    if (NotifyLib::$DEBUG || NotifyLib::isWeb()) echo "NO_NEW\n";
    return;
}

// 2) Preparar query agregado de items (evita SQL roto)
$sqlItems = "SELECT COUNT(*) AS items_count,
                    COALESCE(SUM(quantity),0) AS units
             FROM cart_items_may
             WHERE cart_id = :cart_id";
$stItems = $db->prepare($sqlItems);

// 3) Detectar si existe tabla customers (opcional)
$hasCustomers = false;
try {
    $db->query("SELECT 1 FROM customers LIMIT 1");
    $hasCustomers = true;
} catch (Throwable $e) {
    $hasCustomers = false;
}

$stCust = null;
if ($hasCustomers) {
    $stCust = $db->prepare("SELECT nombre, email, telefono, comuna, region
                            FROM customers
                            WHERE id = :id
                            LIMIT 1");
}

$max = $lastId;

foreach ($carts_may as $c) {
    $id = (int)($c['id'] ?? 0);
    $customerId = (int)($c['customer_id'] ?? 0);
    $status = NotifyLib::esc($c['status'] ?? '');

    $createdRaw = (string)($c['created_at'] ?? '');
    $created = $createdRaw;
    try { if ($createdRaw !== '') $created = (new DateTime($createdRaw))->format('d/m/Y H:i'); } catch (Throwable $e) {}

    // Agregado de items
    $stItems->execute([':cart_id' => $id]);
    $agg = $stItems->fetch(PDO::FETCH_ASSOC) ?: ['items_count' => 0, 'units' => 0];

    // Datos cliente (si existe)
    $custText = ($customerId > 0) ? "Cliente #{$customerId}" : "Sin cliente";
    if ($stCust && $customerId > 0) {
        $stCust->execute([':id' => $customerId]);
        $cu = $stCust->fetch(PDO::FETCH_ASSOC);
        if ($cu) {
            $n  = NotifyLib::esc($cu['nombre'] ?? '');
            $em = NotifyLib::esc($cu['email'] ?? '');
            $co = NotifyLib::esc($cu['comuna'] ?? '');
            $re = NotifyLib::esc($cu['region'] ?? '');
            $custText = ($n !== '') ? $n : "Cliente #{$customerId}";
            if ($em !== '') $custText .= " ({$em})";
            if ($co !== '' || $re !== '') $custText .= " - {$co} / {$re}";
        }
    }

    // Mensaje
    $msg  = "🛒✨ <b>Nuevo carrito</b>\n";
    $msg .= "🆔 <b>ID:</b> {$id}\n";
    $msg .= "👤 <b>Cliente:</b> {$custText}\n";
    if ($status !== '') $msg .= "🏷️ <b>Status:</b> {$status}\n";
    $msg .= "📦 <b>Ítems:</b> " . (int)$agg['items_count'] . " | <b>Unidades:</b> " . (int)$agg['units'] . "\n";
    if ($created !== '') $msg .= "🗓️ <b>Fecha:</b> {$created}\n";

    NotifyLib::tgSend($chatIds, $msg);

    if ($id > $max) $max = $id;
}

// Escribir estado solo en modo real (tu patrón)
if (!NotifyLib::$DEBUG) NotifyLib::stateWrite($stateFile, $max);

if (NotifyLib::$DEBUG || NotifyLib::isWeb()) echo "DONE\n";
