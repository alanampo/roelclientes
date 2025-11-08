<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');

function http_get_json(string $url, int $timeout = 8): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'roelplant-proxy/1.1',
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

function norm(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);
    $s = preg_replace('/[^a-z0-9\/\s-]+/',' ',$s);
    $s = preg_replace('/\s+/',' ',$s);
    return trim($s);
}

function normalizaListado(array $j, ?string $tipoFiltroNorm = null): array {
    $items = $j['items'] ?? [];
    $out = [];
    foreach ($items as $p) {
        $tipoPlanta = $p['tipo_planta'] ?? '';
        if ($tipoFiltroNorm) {
            $tp = norm((string)$tipoPlanta);
            if ($tp === '' || strpos($tp, $tipoFiltroNorm) === false) continue;
        }
        $precios = $p['precios'] ?? [];
        $detalle = $precios['detalle']['bruto']   ?? ($precios['detalle_bruto']   ?? null);
        $mayor   = $precios['mayorista']['bruto'] ?? ($precios['mayorista_bruto'] ?? null);
        $out[] = [
            'status'         => 'ok',
            'id_variedad'    => $p['id_variedad'] ?? null,
            'variedad'       => $p['nombre'] ?? null,
            'referencia'     => $p['referencia'] ?? null,
            'tipo_planta'    => $tipoPlanta ?: null,
            'precio'         => $mayor,
            'precio_detalle' => $detalle,
            'stock'          => $p['stock'] ?? null,
            'disponible_para_reservar' => $p['stock'] ?? null,
            'unidad'         => 'plantines',
            'imagen'         => $p['imagen_url'] ?? null,
            'descripcion'    => $p['descripcion'] ?? null,
            '_raw'           => $p,
        ];
    }
    return [ 'status'=>'ok', 'count'=>count($out), 'items'=>$out ];
}

$tipo = $_GET['tipo'] ?? null;
if (!$tipo) {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw) { $j = json_decode($raw, true); if (is_array($j)) $tipo = $j['tipo'] ?? null; }
}
$tipoNorm = $tipo ? norm((string)$tipo) : null;

$api  = 'https://roelplant.cl/bot-Rg5y5r3MMs/api_ia/disponibles.php';
$res  = http_get_json($api);
if (!$res['ok']) {
    http_response_code(502);
    echo json_encode(['status'=>'error','error'=>$res['error']]); exit;
}
echo json_encode(normalizaListado($res, $tipoNorm), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
