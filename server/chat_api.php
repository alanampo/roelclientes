<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

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
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

require_once __DIR__ . '/config.php';

$apiKey = OPENAI_API_KEY;
if (!$apiKey) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'OPENAI_API_KEY missing']);
  exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true) ?: [];

$message = trim($data['message'] ?? '');
$history = $data['history'] ?? [];

if ($message === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Empty message']);
  exit;
}

error_log('[CHAT_API] Mensaje: ' . $message);

// ===== DETECCIÓN: ¿Es búsqueda de producto? (como hace Realtime) =====
$isProductSearch = detectProductSearch($message);
$productName = null;
$productData = null;

if ($isProductSearch) {
  error_log('[CHAT_API] Detectada búsqueda de producto');
  $productName = extractProductName($message);

  if ($productName) {
    error_log('[CHAT_API] Buscando: ' . $productName);
    $productData = searchProduct($productName);
    error_log('[CHAT_API] Resultado: ' . json_encode($productData));
  }
}

// Construir mensajes
$messages = [];

$messages[] = [
  'role' => 'system',
  'content' => 'Eres vendedor de Roelplant. Responde en español (Chile). Si el cliente te proporciona datos de productos, úsalos. Si pregunta por productos que no encontraste, di que no tiene disponibilidad.'
];

// Agregar historial
foreach ($history as $msg) {
  $messages[] = [
    'role' => $msg['role'] ?? 'user',
    'content' => $msg['content'] ?? ''
  ];
}

// Mensaje del cliente + contexto de productos si encontramos algo
$userMessage = $message;
error_log('[CHAT_API] ¿Búsqueda de producto? ' . ($isProductSearch ? 'SÍ' : 'NO'));
error_log('[CHAT_API] ¿Datos encontrados? ' . ($productData ? 'SÍ' : 'NO'));

if ($productData) {
  $formatted = formatProductsForAI($productData);
  error_log('[CHAT_API] Datos formateados: ' . $formatted);
  $userMessage .= "\n\n[DATOS DE PRODUCTOS ENCONTRADOS EN BD:\n";
  $userMessage .= $formatted;
  $userMessage .= "\nUSA ESTOS DATOS PARA RESPONDER]";
} else {
  error_log('[CHAT_API] SIN datos de productos - Responder sin busca');
}

$messages[] = [
  'role' => 'user',
  'content' => $userMessage
];

error_log('[CHAT_API] Mensaje final a OpenAI: ' . substr($userMessage, 0, 200));

// Llamada a OpenAI (SIN tools, solo chat)
$payload = [
  'model' => 'gpt-4o-mini',
  'messages' => $messages,
  'temperature' => 0.7,
  'max_tokens' => 1000
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
  ],
  CURLOPT_POSTFIELDS => json_encode($payload),
  CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode < 200 || $httpCode >= 300) {
  error_log('[CHAT_API] Error OpenAI: HTTP ' . $httpCode);
  http_response_code(502);
  echo json_encode(['ok' => false, 'error' => 'OpenAI error']);
  exit;
}

$result = json_decode($response, true);

if (!isset($result['choices'][0]['message']['content'])) {
  error_log('[CHAT_API] Invalid response');
  http_response_code(502);
  echo json_encode(['ok' => false, 'error' => 'Invalid response']);
  exit;
}

$finalResponse = $result['choices'][0]['message']['content'];

error_log('[CHAT_API] Respuesta: ' . $finalResponse);

echo json_encode([
  'ok' => true,
  'response' => $finalResponse
]);

// ============= FUNCIONES AUXILIARES =============

function detectProductSearch($text) {
  // Palabras clave que indican búsqueda de producto
  $keywords = ['tienes?', 'tenes?', 'hay', 'tenemos', 'tienen', 'precio', 'costo', 'cuanto', 'cuánto', 'cuesta', 'vale', 'sale', 'disponible', 'stock', 'existe'];

  $lower = strtolower($text);
  foreach ($keywords as $kw) {
    if (stripos($lower, $kw) !== false) {
      return true;
    }
  }

  return false;
}

function extractProductName($text) {
  // Patrones específicos
  $patterns = [
    '/(?:tenes?|tienes?|hay|tenemos?|tienen)\s+(?:(?:el|la|los|las|un|una|unos|unas)\s+)?([a-záéíóúñ\s]+?)(?:\?|$)/i',
    '/(?:cuanto|cuánto|cual|cuál)\s+(?:cuesta|es|vale|sale)\s+(?:(?:el|la|los|las|un|una)\s+)?([a-záéíóúñ\s]+?)(?:\?|$)/i',
    '/precio\s+(?:de\s+)?(?:(?:el|la|los|las)\s+)?([a-záéíóúñ\s]+?)(?:\?|$)/i',
  ];

  foreach ($patterns as $pattern) {
    if (preg_match($pattern, $text, $matches) && isset($matches[1])) {
      return trim($matches[1]);
    }
  }

  // Fallback: eliminar palabras clave
  $clean = $text;
  $keywords = ['tienes?', 'tenes?', 'hay', 'precio', 'costo', 'cuanto', 'cuánto', 'cuesta', 'vale', 'sale', 'disponible', 'stock'];
  foreach ($keywords as $kw) {
    $clean = preg_replace('/\b' . $kw . '\b/i', '', $clean);
  }

  return trim(preg_replace('/\?|!|\./', '', $clean));
}

function searchProduct($name) {
  // Conectar a BD directamente
  require_once __DIR__ . '/../catalogo_detalle/api/_bootstrap.php';

  try {
    $db = db();

    error_log('[CHAT_API] Buscando producto: ' . $name);

    $ivaPct = 0.19;
    $nombreSinEspacios = str_replace(' ', '', $name);
    $like = "%" . $db->real_escape_string($name) . "%";
    $likeSinEspacios = "%" . $db->real_escape_string($nombreSinEspacios) . "%";

    error_log('[CHAT_API] LIKE: ' . $like . ' | SIN ESPACIOS: ' . $likeSinEspacios);

    // Query idéntica a tool_producto.php
    $sql = <<<SQL
SELECT
    sv.id_variedad,
    sv.variedad AS nombre,
    sv.referencia,
    sv.tipo AS tipo_producto,
    sv.precio AS precio_mayorista_sin_iva,
    sv.precio_detalle AS precio_detalle_sin_iva,
    sv.disponible_para_reservar,
    MAX(CASE WHEN a.nombre = 'TIPO DE PLANTA' THEN av.valor END) AS tipo_planta,
    GROUP_CONCAT(DISTINCT
        CASE
            WHEN a.nombre IS NULL THEN NULL
            WHEN a.nombre = 'TIPO DE PLANTA' THEN NULL
            WHEN NULLIF(TRIM(av.valor),'') IS NULL THEN NULL
            ELSE CONCAT(a.nombre, ': ', TRIM(av.valor))
        END
        ORDER BY a.nombre
        SEPARATOR '||'
    ) AS attrs_activos,
    MIN(v.descripcion) AS descripcion,
    MIN(img.nombre_archivo) AS imagen_nombre
FROM (
    SELECT
        v.id AS id_variedad,
        v.nombre AS variedad,
        CONCAT(t.codigo, LPAD(v.id_interno, 4, '0')) AS referencia,
        t.nombre AS tipo,
        v.precio,
        v.precio_detalle,
        (
            SUM(s.cantidad)
            - IFNULL((
                SELECT SUM(r.cantidad)
                FROM reservas_productos r
                WHERE r.id_variedad = v.id
                  AND (r.estado = 0 OR r.estado = 1)
            ), 0)
            - IFNULL((
                SELECT SUM(e.cantidad)
                FROM entregas_stock e
                JOIN reservas_productos r2 ON r2.id = e.id_reserva_producto
                WHERE r2.id_variedad = v.id
                  AND r2.estado = 2
            ), 0)
        ) AS disponible_para_reservar
    FROM stock_productos s
    JOIN articulospedidos ap ON ap.id = s.id_artpedido
    JOIN variedades_producto v ON v.id = ap.id_variedad
    JOIN tipos_producto t ON t.id = v.id_tipo
    WHERE ap.estado = 8
      AND (v.nombre LIKE ? OR REPLACE(v.nombre, ' ', '') LIKE ?)
    GROUP BY v.id
) AS sv
JOIN variedades_producto v ON v.id = sv.id_variedad
LEFT JOIN imagenes_variedades img ON img.id_variedad = sv.id_variedad
LEFT JOIN atributos_valores_variedades avv ON avv.id_variedad = sv.id_variedad
LEFT JOIN atributos_valores av ON av.id = avv.id_atributo_valor
LEFT JOIN atributos a ON a.id = av.id_atributo
WHERE sv.disponible_para_reservar > 0
GROUP BY sv.id_variedad
ORDER BY sv.disponible_para_reservar DESC
SQL;

    $stmt = $db->prepare($sql);
    if (!$stmt) {
      error_log('[CHAT_API] Error preparando SQL: ' . $db->error);
      return null;
    }

    $stmt->bind_param('ss', $like, $likeSinEspacios);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
      $detalleNeto = (float)($row['precio_detalle_sin_iva'] ?? 0);
      $imagenUrl = null;
      if (!empty($row['imagen_nombre'])) {
        $imagenUrl = 'https://control.roelplant.cl/uploads/variedades/' . $row['imagen_nombre'];
      }

      $attrsRaw = trim((string)($row['attrs_activos'] ?? ''));
      $attrs = [];
      if ($attrsRaw !== '') {
        foreach (explode('||', $attrsRaw) as $kv) {
          $kv = trim((string)$kv);
          if ($kv !== '') {
            $parts = explode(':', $kv, 2);
            if (count($parts) === 2) {
              $attrs[] = [
                'nombre' => trim($parts[0]),
                'valor' => trim($parts[1])
              ];
            }
          }
        }
      }

      $items[] = [
        'id_variedad' => (int)($row['id_variedad'] ?? 0),
        'variedad' => $row['nombre'],
        'referencia' => $row['referencia'],
        'tipo_producto' => $row['tipo_producto'] ?? null,
        'tipo_planta' => $row['tipo_planta'] ?? null,
        'attrs' => $attrs,
        'attrs_raw' => $attrsRaw,
        'precio' => (int)round(((float)($row['precio_mayorista_sin_iva'] ?? 0)) * (1 + $ivaPct)),
        'precio_detalle' => (int)round($detalleNeto * (1 + $ivaPct)),
        'stock' => max(0, (int)($row['disponible_para_reservar'] ?? 0)),
        'disponible_para_reservar' => max(0, (int)($row['disponible_para_reservar'] ?? 0)),
        'unidad' => 'plantines',
        'imagen' => $imagenUrl,
        'descripcion' => $row['descripcion'] ?? null
      ];
    }

    $stmt->close();

    error_log('[CHAT_API] Resultados: ' . count($items) . ' producto(s)');

    if (empty($items)) {
      return null;
    }

    if (count($items) === 1) {
      return ['status' => 'ok'] + $items[0];
    }

    return [
      'status' => 'multiple',
      'count' => count($items),
      'items' => $items
    ];

  } catch (Throwable $th) {
    error_log('[CHAT_API] Excepción en searchProduct: ' . $th->getMessage());
    return null;
  }
}

function formatProductsForAI($data) {
  $output = '';

  if (isset($data['items']) && is_array($data['items'])) {
    foreach ($data['items'] as $item) {
      $output .= formatSingleProduct($item) . "\n";
    }
  } elseif (isset($data['id_variedad'])) {
    $output .= formatSingleProduct($data);
  }

  return $output;
}

function formatSingleProduct($item) {
  $nombre = $item['variedad'] ?? '';
  $ref = $item['referencia'] ?? '';
  $stock = $item['stock'] ?? 0;
  $precio = $item['precio_detalle'] ?? 0;
  $tipo = $item['tipo_producto'] ?? '';
  $attrs = $item['attrs_raw'] ?? '';

  $line = "- {$nombre} (Ref: {$ref})";
  if ($tipo) $line .= " [{$tipo}]";
  if ($attrs) $line .= " {$attrs}";
  $line .= ": \${$precio} | Stock: {$stock} unidades";

  return $line;
}
