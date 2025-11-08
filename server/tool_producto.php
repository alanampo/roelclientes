<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

function read_json_input(): array {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}
function http_get_json(string $url, int $timeout = 6): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'roelplant-proxy/1.0',
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false || $code >= 400) {
        return ['ok'=>false, 'error' => $err ?: ("HTTP ".$code)];
    }
    $j = json_decode($resp, true);
    if (!is_array($j)) return ['ok'=>false, 'error'=>'Respuesta no JSON del servicio'];
    $j['ok'] = true;
    return $j;
}
function normaliza(array $j): array {
    $p = $j['producto'] ?? null;
    if (!$p && !empty($j['productos']) && is_array($j['productos'])) {
        $p = $j['productos'][0];
    }
    if (!is_array($p)) {
        return ['status'=>'not_found', 'message'=>'Sin coincidencias'];
    }

    $precios = $p['precios'] ?? [];
    $detalle_bruto   = $precios['detalle']['bruto']   ?? ($precios['detalle_bruto'] ?? null);
    $mayorista_bruto = $precios['mayorista']['bruto'] ?? ($precios['mayorista_bruto'] ?? null);

    $stockNode = $p['stock'] ?? [];
    $reservable = is_array($stockNode) ? ($stockNode['disponible_para_reservar'] ?? null) : null;
    $stockPlano = (is_array($p) && array_key_exists('stock', $p) && !is_array($p['stock'])) ? (int)$p['stock'] : null;

    return [
        'status'                  => 'ok',
        'variedad'                => $p['nombre'] ?? ($p['variedad'] ?? ''),
        'referencia'              => $p['referencia'] ?? null,
        'tipo_planta'             => $p['tipo_planta'] ?? null,
        'precio'                  => $mayorista_bruto,
        'precio_detalle'          => $detalle_bruto,
        'stock'                   => $stockPlano ?? ($reservable ?? null),
        'disponible_para_reservar'=> $reservable ?? $stockPlano,
        'unidad'                  => (is_array($stockNode) ? ($stockNode['unidad'] ?? 'plantines') : 'plantines'),
        'imagen'                  => $p['imagen_url'] ?? ($p['imagen'] ?? null),
        'descripcion'             => $p['descripcion'] ?? null,
        '_raw'                    => $p,
    ];
}

session_start();

// Verificar token de API
if (!isset($_SESSION['api_access_token'])) {
    http_response_code(401);
    echo json_encode(['status'=>'error','error'=>'No autorizado - falta token de API']); exit;
}

$in = read_json_input();
$nombre = trim((string)($in['nombre'] ?? ''));
if ($nombre === '') {
    http_response_code(400);
    echo json_encode(['status'=>'error','error'=>'nombre vacío']); exit;
}

// Llamar a la API real con token
$apiUrl = 'https://control.roelplant.cl/api/producto.php?nombre=' . rawurlencode($nombre);
$token = $_SESSION['api_access_token'];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ],
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

// Si el token expiró, intentar renovar
if ($httpCode === 401 && isset($_SESSION['api_refresh_token'])) {
    $refreshUrl = 'https://control.roelplant.cl/api/refresh.php';
    $refreshData = json_encode(['refresh_token' => $_SESSION['api_refresh_token']]);

    $ch2 = curl_init($refreshUrl);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $refreshData,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 10,
    ]);

    $refreshResponse = curl_exec($ch2);
    $refreshHttpCode = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($refreshHttpCode === 200 && $refreshResponse) {
        $refreshData = json_decode($refreshResponse, true);
        if (isset($refreshData['tokens']['access_token'])) {
            $_SESSION['api_access_token'] = $refreshData['tokens']['access_token'];
            $_SESSION['api_token_expires_at'] = $refreshData['tokens']['access_expires_at'];

            // Reintentar con nuevo token
            $ch3 = curl_init($apiUrl);
            curl_setopt_array($ch3, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $_SESSION['api_access_token'],
                    'Accept: application/json'
                ],
            ]);
            $response = curl_exec($ch3);
            $httpCode = (int)curl_getinfo($ch3, CURLINFO_HTTP_CODE);
            curl_close($ch3);
        }
    }
}

if ($httpCode !== 200 || !$response) {
    http_response_code(502);
    echo json_encode(['status'=>'error','error'=> $err ?: 'Error al consultar API']); exit;
}

$res = json_decode($response, true);
if (!is_array($res)) {
    http_response_code(502);
    echo json_encode(['status'=>'error','error'=>'Respuesta inválida de la API']); exit;
}

echo json_encode(normaliza($res), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
