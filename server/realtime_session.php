<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Vary: Origin');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Método no permitido']); exit; }

$apiKey = getenv('OPENAI_API_KEY');
$cfg = __DIR__ . '/config.php';
if (file_exists($cfg)) { require_once $cfg; if (defined('OPENAI_API_KEY')) $apiKey = OPENAI_API_KEY; }
if (!$apiKey) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'OPENAI_API_KEY faltante']); exit; }

$instructions = <<<TXT
Eres vendedor senior de Roelplant. Español de Chile, respuestas claras y concisas.
Si piden precio/stock, usa la herramienta consultar_precio_producto_roelplant.
No inventes teléfonos. WhatsApp: +56 9 3321 7944. Correo: contacto@roelplant.cl.
Diferencia detalle vs mayorista (100+). No prometas plazos sin comuna y cantidad.
TXT;

$tools = [[
  'type'=>'function',
  'name'=>'consultar_precio_producto_roelplant',
  'description'=>'Devuelve precios detalle/mayorista y stock por nombre',
  'parameters'=>[
    'type'=>'object',
    'properties'=>['nombre'=>['type'=>'string','description'=>'Nombre del producto']],
    'required'=>['nombre'],
    'additionalProperties'=>false
  ],
]];

$body = json_encode([
  'model'        => 'gpt-realtime',
  'voice'        => 'alloy',      // puedes probar "verse" si está disponible
  'instructions' => $instructions,
  'tools'        => $tools
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.openai.com/v1/realtime/sessions');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => [
    'Authorization: Bearer '.$apiKey,
    'Content-Type: application/json'
  ],
  CURLOPT_POSTFIELDS     => $body,
  CURLOPT_TIMEOUT        => 30
]);
$resp = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false || $http < 200 || $http >= 300) {
  http_response_code(502);
  echo json_encode(['ok'=>false,'error'=>'realtime_session_failed','http'=>$http,'detail'=>$err,'raw'=>$resp]); exit;
}
echo $resp;
