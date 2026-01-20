<?php
declare(strict_types=1);

/**
 * DB helper (mysqli only) usando constantes de config/cart_db.php
 * Retorna:
 *  ['ok'=>true,'mysqli'=>mysqli] o ['ok'=>false,'error'=>...]
 */

function cart_db_mysqli(): array
{
    $configPath = __DIR__ . '/../config/cart_db.php';
    if (!is_file($configPath)) {
        return ['ok' => false, 'error' => 'No existe config/cart_db.php'];
    }

    require_once $configPath;

    foreach (['CART_DB_HOST','CART_DB_USER','CART_DB_PASS','CART_DB_NAME'] as $c) {
        if (!defined($c)) return ['ok' => false, 'error' => "Falta constante {$c} en config/cart_db.php"];
    }

    $host = (string)CART_DB_HOST;
    $user = (string)CART_DB_USER;
    $pass = (string)CART_DB_PASS;
    $name = (string)CART_DB_NAME;

    if (!class_exists('mysqli')) {
        return ['ok' => false, 'error' => 'mysqli no est¨¢ disponible en este PHP'];
    }

    // cache
    if (isset($GLOBALS['__cart_mysqli']) && $GLOBALS['__cart_mysqli'] instanceof mysqli) {
        return ['ok' => true, 'mysqli' => $GLOBALS['__cart_mysqli']];
    }

    $mysqli = @new mysqli($host, $user, $pass, $name);
    if ($mysqli->connect_errno) {
        return ['ok' => false, 'error' => 'mysqli connect error: ' . $mysqli->connect_error];
    }
    $mysqli->set_charset('utf8mb4');

    $GLOBALS['__cart_mysqli'] = $mysqli;
    return ['ok' => true, 'mysqli' => $mysqli];
}
