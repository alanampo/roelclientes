<?php
/**
 * catalogo_detalle/api/payment/webpay_create.php
 * Crea una transacción de pago con Webpay
 * POST /api/payment/webpay_create
 */
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/../services/WebpayService.php';

require_post();
require_csrf();

$cid = require_auth();
$db = db();
$APP = require __DIR__ . '/../../config/app.php';

// Detectar protocolo y host
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptPath = dirname($_SERVER['SCRIPT_NAME']); // Ej: /catalogo_detalle/api/payment
$basePath = preg_replace('#/api/payment$#', '', $scriptPath); // Remover /api/payment
$baseUrl = rtrim($basePath, '/') . '/'; // Ej: /catalogo_detalle/
$absoluteBaseUrl = "{$protocol}://{$host}{$baseUrl}"; // Ej: http://localhost/catalogo_detalle/

// Obtener carrito
$cartId = cart_get_or_create($db, $cid);
$cart = cart_snapshot($db, $cartId);
$items = $cart['items'] ?? [];

if (!$items) {
  bad_request('Tu carrito está vacío');
}

// Calcular total
$subtotal = (int)($cart['total_clp'] ?? 0);
$qtyTotal = array_reduce($items, fn($acc, $it) => $acc + (int)($it['qty'] ?? 0), 0);

// Packing
$packingCost = 0;
if ($qtyTotal > 0 && $qtyTotal <= 50) {
  $packingCost = 2500;
} elseif ($qtyTotal <= 100) {
  $packingCost = 4000;
} else {
  $packs = (int)ceil($qtyTotal / 100);
  $packingCost = 4500 * $packs;
}

// Shipping (si viene del payload)
$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
$shippingCost = (int)($payload['shipping_cost'] ?? 0);

$total = $subtotal + $packingCost + $shippingCost;

// Generar order unique y session ID
$buyOrder = strtoupper('RP' . uniqid() . rand(0, 99));
$sessionId = session_id() ?: uniqid();

// Crear tabla de transacciones si no existe
$db->query("CREATE TABLE IF NOT EXISTS webpay_transactions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_cliente INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  buy_order VARCHAR(26) NOT NULL,
  amount INT NOT NULL,
  status VARCHAR(32) DEFAULT 'INITIATED',
  authorized BOOLEAN DEFAULT FALSE,
  authorization_code VARCHAR(6),
  card_number VARCHAR(19),
  vci VARCHAR(10),
  response_code INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  confirmed_at TIMESTAMP NULL,
  KEY idx_cliente (id_cliente),
  KEY idx_token (token),
  KEY idx_buy_order (buy_order)
)");

// Inicializar servicio Webpay
$webpay = new WebpayService(
  $APP['WEBPAY_ENVIRONMENT'],
  $APP['WEBPAY_COMMERCE_CODE'],
  $APP['WEBPAY_API_KEY']
);

// URL de retorno (DEBE ser absoluta con protocolo)
$returnUrl = $absoluteBaseUrl . 'api/payment/webpay_return.php';

// Crear transacción en Webpay
$result = $webpay->createTransaction($total, $buyOrder, $sessionId, $returnUrl);

if (!$result['ok']) {
  json_out(['ok' => false, 'error' => $result['error'], '_debug' => $result], 400);
}

$token = $result['token'];

// Validar que tenemos token válido
if (empty($token) || strlen($token) < 20) {
  json_out(['ok' => false, 'error' => 'Token inválido de Webpay', '_debug' => ['token' => $token, 'result' => $result]], 400);
}

// Guardar en BD
$st = $db->prepare("INSERT INTO webpay_transactions (id_cliente, token, buy_order, amount, status)
                    VALUES (?, ?, ?, ?, 'INITIATED')");
if ($st) {
  $st->bind_param('issi', $cid, $token, $buyOrder, $total);
  $st->execute();
  $st->close();
}

// Agregar token como parámetro a la URL de Webpay
$redirectUrl = $result['url'] . '?token=' . urlencode($token);

json_out([
  'ok' => true,
  'token' => $token,
  'redirect_url' => $redirectUrl,
  'buy_order' => $buyOrder,
  'amount' => $total
]);
