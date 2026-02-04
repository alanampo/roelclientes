<?php
// catalogo_detalle/api/production/request_create.php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  require_post();
  require_csrf();

  // Debe estar logueado
  $cid = require_auth();

  $raw  = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true);
  if (!is_array($data)) throw new RuntimeException('JSON inválido.');

  $items = $data['items'] ?? [];
  if (!is_array($items) || count($items) < 1) throw new RuntimeException('Debes agregar al menos 1 especie.');

  // Normaliza items (dedupe por id/uid)
  $cart = [];
  foreach ($items as $it) {
    $id     = trim((string)($it['id'] ?? ''));
    $nombre = trim((string)($it['nombre'] ?? ''));
    $qty    = (int)($it['qty'] ?? 0);
    $precio = (int)($it['precio_mayorista_clp'] ?? $it['precio'] ?? 0);

    if ($id === '' || $nombre === '' || $qty <= 0) continue;
    $cart[$id] = ['id'=>$id,'nombre'=>$nombre,'qty'=>$qty,'precio'=>$precio];
  }

  if (count($cart) < 1) throw new RuntimeException('Items inválidos.');

  // ✅ Reglas: 1..4 especies
  $n = count($cart);
  if ($n < 1) throw new RuntimeException('Debes seleccionar al menos 1 especie.');

  // ✅ Reglas: min 50 c/u, total>=200 (sin tope)
  $totalUnits  = 0;
  $totalAmount = 0;
  foreach ($cart as $c) {
    if ($c['qty'] < 50) throw new RuntimeException($c['nombre'].': mínimo 50.');
    $totalUnits  += $c['qty'];
    $totalAmount += $c['qty'] * $c['precio'];
  }
  if ($totalUnits < 200) throw new RuntimeException('Total mínimo 200 unidades.');

  $notes = trim((string)($data['notes'] ?? ''));

  $db = db(); // mysqli

  // Tablas (idempotente)
  $sql1 = "
    CREATE TABLE IF NOT EXISTS " . PROD_REQUESTS_TABLE . " (
      id INT AUTO_INCREMENT PRIMARY KEY,
      request_code VARCHAR(32) NOT NULL UNIQUE,
      id_cliente INT NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'new',
      total_units INT NOT NULL DEFAULT 0,
      total_amount_clp INT NOT NULL DEFAULT 0,
      notes TEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_cliente (id_cliente)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";
  if (!mysqli_query($db, $sql1)) {
    throw new RuntimeException('No se pudo crear tabla production_requests: '.mysqli_error($db));
  }

  $sql2 = "
    CREATE TABLE IF NOT EXISTS " . PROD_REQUEST_ITEMS_TABLE . " (
      id INT AUTO_INCREMENT PRIMARY KEY,
      request_id INT NOT NULL,
      product_id VARCHAR(64) NOT NULL,
      product_name VARCHAR(255) NOT NULL,
      qty INT NOT NULL,
      unit_price_clp INT NOT NULL DEFAULT 0,
      line_total_clp INT NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_pri_req FOREIGN KEY (request_id) REFERENCES " . PROD_REQUESTS_TABLE . "(id) ON DELETE CASCADE,
      KEY idx_req (request_id),
      KEY idx_prod (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";
  if (!mysqli_query($db, $sql2)) {
    throw new RuntimeException('No se pudo crear tabla production_request_items: '.mysqli_error($db));
  }

  // Genera código
  $code = 'PR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

  // Transacción
  mysqli_begin_transaction($db);

  // Insert request
  $st = mysqli_prepare($db, "INSERT INTO " . PROD_REQUESTS_TABLE . " (request_code, id_cliente, total_units, total_amount_clp, notes) VALUES (?,?,?,?,?)");
  if (!$st) throw new RuntimeException('Prepare failed (requests): '.mysqli_error($db));

  // mysqli permite null en 's' en la práctica, pero si quieres evitar rarezas:
  $notesDb = ($notes !== '') ? $notes : null;

  mysqli_stmt_bind_param($st, 'siiis', $code, $cid, $totalUnits, $totalAmount, $notesDb);
  if (!mysqli_stmt_execute($st)) throw new RuntimeException('Execute failed (requests): '.mysqli_stmt_error($st));
  $rid = (int)mysqli_insert_id($db);
  mysqli_stmt_close($st);

  // Insert items
  $sti = mysqli_prepare($db, "INSERT INTO " . PROD_REQUEST_ITEMS_TABLE . " (request_id, product_id, product_name, qty, unit_price_clp, line_total_clp) VALUES (?,?,?,?,?,?)");
  if (!$sti) throw new RuntimeException('Prepare failed (items): '.mysqli_error($db));

  foreach ($cart as $c) {
    $line = (int)($c['qty'] * $c['precio']);
    mysqli_stmt_bind_param($sti, 'issiii', $rid, $c['id'], $c['nombre'], $c['qty'], $c['precio'], $line);
    if (!mysqli_stmt_execute($sti)) throw new RuntimeException('Execute failed (items): '.mysqli_stmt_error($sti));
  }
  mysqli_stmt_close($sti);

  mysqli_commit($db);

  // WhatsApp URL
  $cfg = require __DIR__ . '/../../config/app.php';
  $phone = (string)($cfg['WHATSAPP_SELLER_E164'] ?? ($cfg['WHATSAPP_NUMBER'] ?? ''));
  $digits = preg_replace('/\D+/', '', $phone);

  $lines = [];
  $lines[] = "🧪 *Solicitud de Producción*";
  $lines[] = "Código: *{$code}*";
  $lines[] = "Cliente ID: {$cid}";
  $lines[] = "";
  foreach ($cart as $c) {
    $lines[] = "• {$c['nombre']} — {$c['qty']} u — ".number_format($c['precio'],0,',','.')." c/u";
  }
  $lines[] = "";
  $lines[] = "Total unidades: *{$totalUnits}*";
  $lines[] = "Total estimado: *".number_format($totalAmount,0,',','.')."*";
  if ($notes !== '') { $lines[] = ""; $lines[] = "Instrucciones: {$notes}"; }

  $text = implode("\n", $lines);
  $wa   = $digits ? ("https://wa.me/".$digits."?text=".rawurlencode($text)) : null;

  json_out([
    'ok'=>true,
    'request_id'=>$rid,
    'request_code'=>$code,
    'total_units'=>$totalUnits,
    'total_amount_clp'=>$totalAmount,
    'whatsapp_url'=>$wa,
    'redirect'=>null
  ]);

} catch (Throwable $e) {
  // rollback por seguridad
  try {
    if (function_exists('db')) {
      $db = db();
      if ($db instanceof mysqli) @mysqli_rollback($db);
    }
  } catch (Throwable $ignore) {}

  json_out(['ok'=>false,'error'=>$e->getMessage()], 400);
}
