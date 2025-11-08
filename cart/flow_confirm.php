<?php
declare(strict_types=1);

/**
 * Endpoint de confirmación de Flow
 * Flow llama a este endpoint cuando un pago se completa
 */

session_start();

require_once __DIR__ . '/config_cart.php';
require_once __DIR__ . '/flow/FlowApi.php';

// Log de la request
$logFile = __DIR__ . '/../logs/flow_confirm.log';
@mkdir(dirname($logFile), 0755, true);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Flow Confirmation Request:\n" . json_encode([
    'GET' => $_GET,
    'POST' => $_POST,
    'headers' => getallheaders()
], JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Obtener token de Flow
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$token) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Token no proporcionado']);
    exit;
}

try {
    global $ENV;

    // Inicializar Flow API
    $flowApi = new FlowApi(
        $ENV['FLOW_API_KEY'],
        $ENV['FLOW_SECRET'],
        $ENV['FLOW_SANDBOX']
    );

    // Obtener estado del pago desde Flow
    $params = ['token' => $token];
    $paymentData = $flowApi->send('payment/getStatus', $params, 'GET');

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Payment Status Response:\n" . json_encode($paymentData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

    // Verificar el estado del pago
    $status = $paymentData['status'] ?? 0;
    $commerceOrder = $paymentData['commerceOrder'] ?? '';
    $flowOrder = $paymentData['flowOrder'] ?? '';
    $amount = $paymentData['amount'] ?? 0;
    $paymentEmail = $paymentData['payer'] ?? '';

    // Guardar el resultado en un archivo temporal para que el bot pueda consultarlo
    $statusFile = __DIR__ . '/../logs/payment_status.json';
    $statusData = [];

    if (file_exists($statusFile)) {
        $statusData = json_decode(file_get_contents($statusFile), true) ?: [];
    }

    // Agregar el nuevo estado de pago
    $statusData[$commerceOrder] = [
        'status' => $status, // 2 = pagado, 1 = pendiente, 3 = rechazado, 4 = anulado
        'commerce_order' => $commerceOrder,
        'flow_order' => $flowOrder,
        'amount' => $amount,
        'email' => $paymentEmail,
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s'),
        'session_id' => session_id(),
        'raw_data' => $paymentData
    ];

    file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT));

    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Payment status saved: {$commerceOrder} - Status: {$status}\n\n", FILE_APPEND);

    // Responder a Flow
    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR:\n" . $e->getMessage() . "\n\n", FILE_APPEND);

    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
