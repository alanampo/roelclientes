<?php
// catalogo_detalle/config/cart_db.php
declare(strict_types=1);

/**
 * Configuración unificada: Usa la BD principal roel (shared database).
 * Autenticación: usuarios table (tipo_usuario=0 para clientes)
 * Datos del cliente: clientes table
 *
 * Cambia ENVIRONMENT a 'hosting' o 'local' según necesites
 */

define('ENVIRONMENT', 'local'); // 'local' o 'hosting'

if (ENVIRONMENT === 'local') {
  // Local development - usa BD roel (unificada)
  define('CART_DB_HOST', '127.0.0.1');
  define('CART_DB_USER', 'root');
  define('CART_DB_PASS', '');
  define('CART_DB_NAME', 'roel');
} else {
  // Hosting production - usa BD unificada roeluser1_bdsys (mismos credenciales que .env)
  define('CART_DB_HOST', '127.0.0.1');
  define('CART_DB_USER', 'roeluser1_usercli');
  define('CART_DB_PASS', 'SergioVM2022!!');
  define('CART_DB_NAME', 'roeluser1_bdsys');
}

// Nombres de tablas con prefijo "carrito_" (consistente en ambos entornos)
define('CART_TABLE', 'carrito_carts');
define('CART_ITEMS_TABLE', 'carrito_cart_items');
define('ORDERS_TABLE', 'carrito_orders');
define('ORDER_ITEMS_TABLE', 'carrito_order_items');
define('PROD_REQUESTS_TABLE', 'carrito_production_requests');
define('PROD_REQUEST_ITEMS_TABLE', 'carrito_production_request_items');
