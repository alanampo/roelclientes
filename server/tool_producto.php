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

$in = read_json_input();
$nombre = trim((string)($in['nombre'] ?? ''));
if ($nombre === '') {
    http_response_code(400);
    echo json_encode(['status'=>'error','error'=>'nombre vacío']); exit;
}

$base = 'https://roelplant.cl/bot-Rg5y5r3MMs/api_ia/producto.php?nombre=';
$url  = $base . rawurlencode($nombre);
$res  = http_get_json($url);
if (!$res['ok']) {
    http_response_code(502);
    echo json_encode(['status'=>'error','error'=>$res['error']]); exit;
}

echo json_encode(normaliza($res), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
