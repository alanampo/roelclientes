<?php
declare(strict_types=1);

/**
 * Genera link de pago de Flow basado en el carrito actual
 * Requiere: email del usuario en la sesión
 * Retorna: URL de pago de Flow
 */

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'error' => 'method_not_allowed']);
    exit;
}

session_start();

require_once __DIR__ . '/config_cart.php';
require_once __DIR__ . '/flow/FlowApi.php';

// Leer input
$input = file_get_contents('php://input') ?: '{}';
$data = json_decode($input, true) ?: [];

// Debug: log raw input
$logFile = __DIR__ . '/../logs/flow_payment.log';
@mkdir(dirname($logFile), 0755, true);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - RAW INPUT:\n" . json_encode([
    'input_data' => $data,
    'session_id' => session_id(),
    'session_keys' => array_keys($_SESSION)
], JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Obtener email del input o sesión
$email = trim($data['email'] ?? $_SESSION['user_email'] ?? '');
$rut = trim($data['rut'] ?? $_SESSION['client_rut'] ?? '');
$userId = isset($data['user_id']) && is_numeric($data['user_id']) ? (int)$data['user_id'] : null;

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => 'email_required',
        'message' => 'Se requiere un email válido para generar el link de pago'
    ]);
    exit;
}

// Guardar email en sesión
$_SESSION['user_email'] = $email;
if ($rut) {
    $_SESSION['client_rut'] = $rut;
}

// Obtener carrito usando la misma lógica que cart_api.php
// IMPORTANTE: Debe ser consistente con cart_get() en cart_api.php
$cartKey = $userId ? "cart_user_{$userId}" : 'cart';
$cart = $_SESSION[$cartKey] ?? ['items' => [], 'total' => 0];

// Debug: registrar qué carrito estamos intentando leer
$logFile = __DIR__ . '/../logs/flow_payment.log';
@mkdir(dirname($logFile), 0755, true);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Reading cart:\n" . json_encode([
    'user_id' => $userId,
    'cart_key' => $cartKey,
    'cart_items_count' => count($cart['items'] ?? []),
    'session_id' => session_id(),
    'all_session_keys' => array_keys($_SESSION)
], JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

if (empty($cart['items'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => 'empty_cart',
        'message' => 'El carrito está vacío'
    ]);
    exit;
}

// Calcular total
$total = 0;
foreach ($cart['items'] as $item) {
    $qty = (int)($item['qty'] ?? 0);
    $price = (int)($item['price'] ?? 0);
    $total += $qty * $price;
}

if ($total <= 0) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => 'invalid_total',
        'message' => 'El monto total debe ser mayor a 0'
    ]);
    exit;
}

try {
    // Inicializar Flow
    global $ENV;

    // Debug: verificar que las credenciales existan
    if (empty($ENV['FLOW_API_KEY']) || empty($ENV['FLOW_SECRET'])) {
        throw new Exception('Flow credentials not configured. API_KEY: ' . ($ENV['FLOW_API_KEY'] ?? 'NOT SET') . ', SECRET: ' . (isset($ENV['FLOW_SECRET']) ? 'SET' : 'NOT SET'));
    }

    $flowApi = new FlowApi(
        $ENV['FLOW_API_KEY'],
        $ENV['FLOW_SECRET'],
        $ENV['FLOW_SANDBOX']
    );

    // Generar número de orden único
    $orderNumber = 'ROEL-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    // Preparar parámetros para Flow
    $params = [
        'commerceOrder' => $orderNumber,
        'subject' => 'Pedido Roelplant - ' . count($cart['items']) . ' productos',
        'currency' => 'CLP',
        'amount' => $total,
        'email' => $email,
        'paymentMethod' => 9, // Todos los métodos de pago
        'urlConfirmation' => $ENV['FLOW_CONFIRM_URL'],
        'urlReturn' => $ENV['FLOW_RETURN_URL']
    ];

    // Agregar información opcional si existe
    if ($rut) {
        $params['optional'] = json_encode(['rut' => $rut]);
    }

    // Llamar a Flow API
    // Log para debug
    $logFile = __DIR__ . '/../logs/flow_payment.log';
    @mkdir(dirname($logFile), 0755, true);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Sending to Flow:\n" . json_encode([
        'params' => $params,
        'api_key' => substr($ENV['FLOW_API_KEY'], 0, 10) . '...',
        'sandbox' => $ENV['FLOW_SANDBOX']
    ], JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

    $response = $flowApi->send('payment/create', $params, 'POST');

    // Guardar información de la transacción en sesión
    $_SESSION['flow_order'] = [
        'flowOrder' => $response['flowOrder'] ?? null,
        'token' => $response['token'] ?? null,
        'commerceOrder' => $orderNumber,
        'amount' => $total,
        'email' => $email,
        'created_at' => date('Y-m-d H:i:s')
    ];

    // Construir URL de pago
    $paymentUrl = ($response['url'] ?? '') . '?token=' . ($response['token'] ?? '');

    echo json_encode([
        'status' => 'ok',
        'payment_url' => $paymentUrl,
        'order_number' => $orderNumber,
        'amount' => $total,
        'flow_order' => $response['flowOrder'] ?? null
    ]);

} catch (Exception $e) {
    // Log del error
    $logFile = __DIR__ . '/../logs/flow_payment.log';
    @mkdir(dirname($logFile), 0755, true);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR:\n" . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'flow_api_error',
        'message' => $e->getMessage(),
        'trace' => $e->getFile() . ':' . $e->getLine()
    ]);
}
