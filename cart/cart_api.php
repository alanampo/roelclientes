<?php
declare(strict_types=1);

/**
 * Minimal cart endpoint.
 * Same logic as original. Only formatting and small comment fixes.
 */

// ---------- Headers ----------
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

// Preflight
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only POST
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'error' => 'method_not_allowed']);
    exit;
}

session_start();

// ---------- Global State ----------

// Cache the request data and user_id once
$REQUEST_DATA = null;
$CURRENT_USER_ID = null;

function init_request_data(?array $data = null): void
{
    global $REQUEST_DATA, $CURRENT_USER_ID;

    if ($REQUEST_DATA === null) {
        // Use provided data or read from input
        $REQUEST_DATA = $data ?? read_json();

        // Extract user_id from request
        if (isset($REQUEST_DATA['user_id']) && is_numeric($REQUEST_DATA['user_id'])) {
            $CURRENT_USER_ID = (int)$REQUEST_DATA['user_id'];
        }
    }
}

// ---------- Helpers ----------

/**
 * Read JSON body and merge with $_POST.
 */
function read_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    if (!is_array($j)) {
        $j = [];
    }
    foreach ($_POST as $k => $v) {
        if (!array_key_exists($k, $j)) {
            $j[$k] = $v;
        }
    }
    return $j;
}

/**
 * Get user ID from cached request data
 */
function get_user_id(): ?int
{
    global $CURRENT_USER_ID;
    init_request_data();
    return $CURRENT_USER_ID;
}

/**
 * Return cart from session with shape: ['items'=>[], 'count'=>0, 'total'=>0]
 * Cart is now user-specific using user_id from request
 */
function cart_get(): array
{
    // Get user_id from request to make cart user-specific
    $userId = get_user_id();
    $cartKey = $userId ? "cart_user_{$userId}" : 'cart';

    if (!isset($_SESSION[$cartKey]) || !is_array($_SESSION[$cartKey])) {
        $_SESSION[$cartKey] = ['items' => [], 'count' => 0, 'total' => 0];
    }
    return $_SESSION[$cartKey];
}

/**
 * Recalculate totals and persist back to session.
 * Note: $cart is passed by reference.
 */
function cart_save(array &$cart): void
{
    $total = 0;
    $count = 0;

    foreach ($cart['items'] as &$it) {
        $qty  = max(0, (int)($it['qty'] ?? 0));
        $pRaw = (string)($it['price'] ?? 0);
        $pInt = (int)preg_replace('/[^\d]/', '', $pRaw); // "$12.500" -> 12500 CLP

        $it['qty']      = $qty;
        $it['price']    = $pInt;
        $it['subtotal'] = $qty * $pInt;

        $total += $it['subtotal'];
        $count += $qty;

        if (!isset($it['unit']) || $it['unit'] === '') {
            $it['unit'] = 'plantines';
        }
        if (!isset($it['tier']) || $it['tier'] === '') {
            $it['tier'] = 'retail';
        }
    }
    unset($it);

    $cart['total'] = $total;
    $cart['count'] = $count;

    // Save to user-specific cart key
    $userId = get_user_id();
    $cartKey = $userId ? "cart_user_{$userId}" : 'cart';
    $_SESSION[$cartKey] = $cart;

    // Debug logging
    $logFile = __DIR__ . '/../logs/cart_debug.log';
    @mkdir(dirname($logFile), 0755, true);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Saving cart:\n" . json_encode([
        'user_id' => $userId,
        'cart_key' => $cartKey,
        'items_count' => count($cart['items']),
        'total' => $cart['total'],
        'session_id' => session_id()
    ], JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
}

/**
 * Create a URL-friendly slug from a product name.
 */
function slug_from_name(string $name): string
{
    $s = strtolower(trim($name));
    $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($converted !== false) {
        $s = $converted;
    }
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    return $s ?: ('item-' . substr(md5($name), 0, 6));
}

/**
 * Find item index by code or exact name (case-insensitive).
 */
function find_index_by_code_or_name(array $items, ?string $code, ?string $name): int
{
    foreach ($items as $i => $it) {
        if ($code && isset($it['code']) && $it['code'] === $code) {
            return $i;
        }
        if ($name && isset($it['name']) && mb_strtolower($it['name']) === mb_strtolower($name)) {
            return $i;
        }
    }
    return -1;
}

/**
 * Normalize tier string to 'retail' or 'wholesale'.
 */
function map_tier($t): string
{
    $t = strtolower((string)$t);
    if (in_array($t, ['mayorista', 'wholesale', 'wholesaler'], true)) {
        return 'wholesale';
    }
    if (in_array($t, ['detalle', 'retail'], true)) {
        return 'retail';
    }
    return 'retail';
}

// ---------- Product resolver ----------

/**
 * Attempt to infer the base path of the bot from the script name.
 */
function bot_base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $m = [];
    return preg_match('#^(/[^/]+)#', (string)$script, $m) ? (string)$m[1] : '/bot18';
}

/**
 * Current request host.
 */
function origin_host(): string
{
    return $_SERVER['HTTP_HOST'] ?? 'plantinera.cl';
}

/**
 * POST JSON to $url and decode the response as array or return null on error.
 */
function try_http_json_post(string $url, array $payload, int $timeout = 4): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => 1,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'bot18-cart/1.0',
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $j = json_decode((string)$res, true);
    return ($code < 400 && is_array($j)) ? $j : null;
}

/**
 * GET JSON from $url and decode the response as array or return null on error.
 */
function try_http_json_get(string $url, int $timeout = 4): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => 'bot18-cart/1.0',
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $j = json_decode((string)$res, true);
    return ($code < 400 && is_array($j)) ? $j : null;
}

/**
 * Resolve product by name against local tool and fallback API.
 * Returns: name, code, unit, image, pm (mayorista CLP), pd (detalle CLP)
 */
function resolve_product_by_name(string $name): ?array
{
    $name = trim($name);
    if ($name === '') {
        return null;
    }

    $host = origin_host();
    $base = bot_base_path();

    $urlsLocal = [
        "https://{$host}{$base}/server/tool_producto.php",
        "http://{$host}{$base}/server/tool_producto.php",
    ];

    foreach ($urlsLocal as $u) {
        $j = try_http_json_post($u, ['nombre' => $name], 4);
        if (is_array($j) && ($j['status'] ?? '') === 'ok') {
            $pm = (int)preg_replace('/[^\d]/', '', (string)($j['precio'] ?? 0));
            $pd = (int)preg_replace('/[^\d]/', '', (string)($j['precio_detalle'] ?? 0));
            if ($pm > 0 || $pd > 0) {
                return [
                    'name'  => $j['variedad'] ?? $name,
                    'code'  => $j['referencia'] ?? '',
                    'unit'  => $j['unidad'] ?? ($j['unidad_medida'] ?? 'plantines'),
                    'image' => $j['imagen'] ?? '',
                    'pm'    => $pm,
                    'pd'    => $pd,
                ];
            }
        }
    }

    $ext = try_http_json_get(
        'https://roelplant.cl/bot-Rg5y5r3MMs/api_ia/producto.php?nombre=' . urlencode($name),
        5
    );
    if (is_array($ext) && ($ext['status'] ?? '') === 'ok') {
        return [
            'name'  => $ext['variedad'] ?? $name,
            'code'  => $ext['referencia'] ?? '',
            'unit'  => $ext['unidad'] ?? ($ext['unidad_medida'] ?? 'plantines'),
            'image' => $ext['imagen'] ?? '',
            'pm'    => (int)preg_replace('/[^\d]/', '', (string)($ext['precio'] ?? 0)),
            'pd'    => (int)preg_replace('/[^\d]/', '', (string)($ext['precio_detalle'] ?? 0)),
        ];
    }

    return null;
}

// ---------- Main ----------

$in     = read_json();
$action = strtolower((string)($in['action'] ?? 'summary'));

// CRITICAL: Initialize request data with already-read input BEFORE calling cart_get()
// php://input can only be read once, so we pass the data we already read
init_request_data($in);

$cart   = cart_get();

switch ($action) {
    case 'clear': {
        $cart = ['items' => [], 'count' => 0, 'total' => 0];
        cart_save($cart);
        echo json_encode(['status' => 'ok', 'cart' => $cart]);
        break;
    }

    case 'add':
    case 'add_by_name': {
        $name  = trim((string)($in['name'] ?? ''));
        $code  = trim((string)($in['code'] ?? ''));
        $qty   = max(1, min(100000, (int)($in['qty'] ?? 1)));
        $tier  = map_tier($in['tier'] ?? 'retail');
        $unit  = trim((string)($in['unit'] ?? ''));
        $price = (int)preg_replace(
            '/[^\d]/',
            '',
            (string)($in['price'] ?? ($in['unit_price'] ?? ($in['precio'] ?? ($in['precio_unitario'] ?? 0))))
        );
        $image = trim((string)($in['image'] ?? ''));

        // Debug logging for add operation
        $logFile = __DIR__ . '/../logs/cart_debug.log';
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - ADD OPERATION:\n" . json_encode([
            'user_id' => get_user_id(),
            'name' => $name,
            'code' => $code,
            'qty' => $qty,
            'price' => $price,
            'session_id' => session_id(),
            'current_cart_items_count' => count($cart['items'])
        ], JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

        if ($action === 'add_by_name' && !$name) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'name_required']);
            break;
        }

        if ($price <= 0 || $unit === '' || $code === '' || $name === '') {
            $res = resolve_product_by_name($name ?: $code);
            if ($res) {
                if ($price <= 0) {
                    $price = ($tier === 'wholesale') ? ($res['pm'] ?? 0) : ($res['pd'] ?? 0);
                }
                if ($unit === '') {
                    $unit = $res['unit'] ?? 'plantines';
                }
                if ($image === '') {
                    $image = $res['image'] ?? '';
                }
                if ($code === '') {
                    $code = $res['code'] ?: ('N-' . slug_from_name($name ?: 'producto'));
                }
                if ($name === '') {
                    $name = $res['name'] ?? $name;
                }
            }
        }

        if (!$code) {
            $code = 'N-' . slug_from_name($name ?: 'producto');
        }

        $idx = find_index_by_code_or_name($cart['items'], $code, $name ?: null);
        if ($idx >= 0) {
            $cart['items'][$idx]['qty'] = max(1, (int)$cart['items'][$idx]['qty'] + $qty);
            if ($price > 0) {
                $cart['items'][$idx]['price'] = $price;
            }
            if ($image) {
                $cart['items'][$idx]['image'] = $image;
            }
            if ($unit) {
                $cart['items'][$idx]['unit'] = $unit;
            }
            if ($tier) {
                $cart['items'][$idx]['tier'] = $tier;
            }
        } else {
            $cart['items'][] = [
                'code'  => $code,
                'name'  => $name ?: $code,
                'qty'   => $qty,
                'unit'  => $unit ?: 'plantines',
                'tier'  => $tier,
                'price' => $price,
                'image' => $image,
            ];
        }

        cart_save($cart); // actualiza $cart y sesión
        echo json_encode(['status' => 'ok', 'cart' => $cart]);
        break;
    }

    case 'update_qty': {
        $code = trim((string)($in['code'] ?? ''));
        $qty  = (int)($in['qty'] ?? 0);

        if (!$code) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'code_required']);
            break;
        }

        $idx = find_index_by_code_or_name($cart['items'], $code, null);
        if ($idx < 0) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => 'item_not_found']);
            break;
        }

        if ($qty <= 0) {
            array_splice($cart['items'], $idx, 1);
        } else {
            $cart['items'][$idx]['qty'] = min(100000, $qty);
        }

        cart_save($cart);
        echo json_encode(['status' => 'ok', 'cart' => $cart]);
        break;
    }

    case 'remove': {
        $code = trim((string)($in['code'] ?? ''));
        $name = trim((string)($in['name'] ?? ''));
        $idx  = find_index_by_code_or_name($cart['items'], $code ?: null, $name ?: null);

        if ($idx < 0) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => 'item_not_found']);
            break;
        }

        array_splice($cart['items'], $idx, 1);
        cart_save($cart);
        echo json_encode(['status' => 'ok', 'cart' => $cart]);
        break;
    }

    case 'checkout': {
        cart_save($cart);
        echo json_encode(['status' => 'ok', 'cart' => $cart, 'message' => 'checkout_requested']);
        break;
    }

    case 'summary':
    default: {
        cart_save($cart);
        echo json_encode(['status' => 'ok', 'cart' => $cart]);
        break;
    }
}
