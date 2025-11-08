<?php
declare(strict_types=1);

/**
 * Endpoint para consultar el estado de un pago
 * El bot JavaScript llama a este endpoint para verificar si el pago se completó
 */

// Headers CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Obtener order_number del request
$orderNumber = $_GET['order_number'] ?? $_POST['order_number'] ?? '';

if (!$orderNumber) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'order_number es requerido'
    ]);
    exit;
}

// Leer el archivo de estados
$statusFile = __DIR__ . '/../logs/payment_status.json';

if (!file_exists($statusFile)) {
    echo json_encode([
        'status' => 'pending',
        'message' => 'Pago en proceso'
    ]);
    exit;
}

$statusData = json_decode(file_get_contents($statusFile), true) ?: [];

// Buscar el pago
if (!isset($statusData[$orderNumber])) {
    echo json_encode([
        'status' => 'pending',
        'message' => 'Pago en proceso'
    ]);
    exit;
}

$payment = $statusData[$orderNumber];

// Mapear estados de Flow
// 1 = Pendiente de pago
// 2 = Pagado
// 3 = Rechazado
// 4 = Anulado

$flowStatus = (int)($payment['status'] ?? 0);

switch ($flowStatus) {
    case 2:
        echo json_encode([
            'status' => 'success',
            'message' => 'Pago exitoso',
            'data' => [
                'commerce_order' => $payment['commerce_order'],
                'flow_order' => $payment['flow_order'],
                'amount' => $payment['amount'],
                'email' => $payment['email'],
                'datetime' => $payment['datetime']
            ]
        ]);

        // Limpiar el estado después de consultarlo (opcional)
        // unset($statusData[$orderNumber]);
        // file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT));
        break;

    case 3:
        echo json_encode([
            'status' => 'rejected',
            'message' => 'Pago rechazado'
        ]);
        break;

    case 4:
        echo json_encode([
            'status' => 'cancelled',
            'message' => 'Pago anulado'
        ]);
        break;

    default:
        echo json_encode([
            'status' => 'pending',
            'message' => 'Pago en proceso'
        ]);
        break;
}
