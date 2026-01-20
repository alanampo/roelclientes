<?php
/**
 * notify/config_tg.php
 * Config único dentro de notify/: Telegram + DB carrito.
 * Nota: rota credenciales si fueron expuestas.
 */
declare(strict_types=1);
date_default_timezone_set('America/Santiago');

/* ===== Telegram ===== */
$__BOT_TOKEN = getenv('BOT_TOKEN');
if (!defined('BOT_TOKEN')) {
    define('BOT_TOKEN', ($__BOT_TOKEN !== false && $__BOT_TOKEN !== '') ? (string)$__BOT_TOKEN : '8067521751:AAHHQodpFvkGEY1cRleocJsNYc9Yh67nxgk');
}
if (!defined('API_URL')) define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

/* ===== DB carrito ===== */
$__H = getenv('CART_DB_HOST'); $__U = getenv('CART_DB_USER'); $__P = getenv('CART_DB_PASS'); $__N = getenv('CART_DB_NAME');
if (!defined('CART_DB_HOST')) define('CART_DB_HOST', ($__H !== false && $__H !== '') ? (string)$__H : '127.0.0.1');
if (!defined('CART_DB_USER')) define('CART_DB_USER', ($__U !== false && $__U !== '') ? (string)$__U : 'roeluser1_cart_user');
if (!defined('CART_DB_PASS')) define('CART_DB_PASS', ($__P !== false && $__P !== '') ? (string)$__P : 'g]3,+[-*NneM@sA{');
if (!defined('CART_DB_NAME')) define('CART_DB_NAME', ($__N !== false && $__N !== '') ? (string)$__N : 'roeluser1_carrito_mayorista');

function cartDb(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    if ((string)CART_DB_USER === '' || CART_DB_USER === 'PEGAR_USER_AQUI') {
        throw new RuntimeException('config_tg.php: CART_DB_USER no configurado.');
    }
    if ((string)CART_DB_NAME === '') {
        throw new RuntimeException('config_tg.php: CART_DB_NAME no configurado.');
    }

    $dsn = 'mysql:host=' . CART_DB_HOST . ';dbname=' . CART_DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, CART_DB_USER, CART_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

/* ===== Telegram helpers ===== */
function tgRequest(string $method, array $params = []): array {
    $ch = curl_init(API_URL . $method);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 7,
        CURLOPT_POSTFIELDS => $params,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err) return ['ok'=>false,'error'=>$err ?: 'curl_exec_failed','code'=>$code];
    $j = json_decode((string)$raw, true);
    if (!is_array($j)) return ['ok'=>false,'error'=>'json_decode','code'=>$code,'raw'=>substr((string)$raw,0,300)];
    if (!($j['ok'] ?? false)) $j['code'] = $code;
    return $j;
}

function sendMessage(string $chat_id, string $text, string $mode='HTML', bool $disable_preview=true): array {
    return tgRequest('sendMessage', [
        'chat_id'=>$chat_id,
        'text'=>$text,
        'parse_mode'=>$mode,
        'disable_web_page_preview'=>$disable_preview ? 'true' : 'false',
    ]);
}
