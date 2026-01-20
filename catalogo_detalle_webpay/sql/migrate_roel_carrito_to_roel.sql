-- ============================================================================
-- Migración de roel_carrito a roel
-- ============================================================================
-- Nota: Esta script crea todas las tablas de roel_carrito en roel con:
--   - Nombres prefijados con "carrito_" para evitar conflictos
--   - Foreign keys apuntando a clientes.id_cliente en lugar de customers.id
--   - Mapeo automático: customers.id → clientes.id_cliente
-- ============================================================================

-- 1. CARRITO_CARTS - Carritos de compra
CREATE TABLE IF NOT EXISTS `carrito_carts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `status` enum('open','converted','abandoned') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_carrito_carts_cliente_open` (`id_cliente`, `status`),
  KEY `idx_carrito_carts_cliente` (`id_cliente`),
  CONSTRAINT `fk_carrito_carts_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. CARRITO_CART_ITEMS - Items en el carrito
CREATE TABLE IF NOT EXISTS `carrito_cart_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` bigint(20) unsigned NOT NULL,
  `id_variedad` int(11) NOT NULL,
  `referencia` varchar(32) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `imagen_url` text DEFAULT NULL,
  `unit_price_clp` int(10) unsigned NOT NULL,
  `qty` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_carrito_cart_items_cart_variedad` (`cart_id`, `id_variedad`),
  KEY `idx_carrito_cart_items_cart_id` (`cart_id`),
  CONSTRAINT `fk_carrito_cart_items_cart` FOREIGN KEY (`cart_id`) REFERENCES `carrito_carts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. CARRITO_ORDERS - Órdenes de venta
CREATE TABLE IF NOT EXISTS `carrito_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_code` varchar(32) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `customer_rut` varchar(16) NOT NULL,
  `customer_nombre` varchar(120) NOT NULL,
  `customer_telefono` varchar(32) NOT NULL,
  `customer_region` varchar(64) NOT NULL,
  `customer_comuna` varchar(64) NOT NULL,
  `customer_email` varchar(190) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'CLP',
  `subtotal_clp` int(10) unsigned NOT NULL DEFAULT 0,
  `shipping_code` varchar(32) NOT NULL DEFAULT 'retiro',
  `shipping_label` varchar(80) NOT NULL DEFAULT 'Retiro en vivero',
  `shipping_cost_clp` int(10) unsigned NOT NULL DEFAULT 0,
  `total_clp` int(10) unsigned NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_carrito_orders_code` (`order_code`),
  KEY `idx_carrito_orders_cliente` (`id_cliente`),
  KEY `idx_carrito_orders_created` (`created_at`),
  CONSTRAINT `fk_carrito_orders_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. CARRITO_ORDER_ITEMS - Items en las órdenes
CREATE TABLE IF NOT EXISTS `carrito_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `id_variedad` bigint(20) unsigned DEFAULT NULL,
  `referencia` varchar(64) NOT NULL,
  `nombre` varchar(190) NOT NULL,
  `imagen_url` varchar(512) DEFAULT NULL,
  `unit_price_clp` int(10) unsigned NOT NULL,
  `qty` int(10) unsigned NOT NULL,
  `line_total_clp` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_carrito_order_items_order` (`order_id`),
  CONSTRAINT `fk_carrito_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `carrito_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BACKOFFICE_ADMINS - Administradores del backoffice
CREATE TABLE IF NOT EXISTS `backoffice_admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `pass_hash` varchar(255) NOT NULL,
  `name` varchar(120) NOT NULL DEFAULT 'Administrador',
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BACKOFFICE_AUDIT - Auditoría del backoffice
CREATE TABLE IF NOT EXISTS `backoffice_audit` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `ip` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_admin` (`admin_id`),
  KEY `idx_action` (`action`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `backoffice_admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. CARRITO_PRODUCTION_REQUESTS - Solicitudes de producción
CREATE TABLE IF NOT EXISTS `carrito_production_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_code` varchar(32) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `total_units` int(11) NOT NULL DEFAULT 0,
  `total_amount_clp` int(11) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_code` (`request_code`),
  KEY `idx_cliente` (`id_cliente`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_carrito_prod_req_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id_cliente`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. CARRITO_PRODUCTION_REQUEST_ITEMS - Items en solicitudes de producción
CREATE TABLE IF NOT EXISTS `carrito_production_request_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) NOT NULL,
  `product_id` varchar(32) NOT NULL,
  `product_name` varchar(255) NOT NULL,
  `qty` int(11) NOT NULL,
  `unit_price_clp` int(11) NOT NULL DEFAULT 0,
  `line_total_clp` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_req` (`request_id`),
  KEY `idx_prod` (`product_id`),
  CONSTRAINT `fk_carrito_prod_req_items` FOREIGN KEY (`request_id`) REFERENCES `carrito_production_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MIGRACIÓN DE DATOS (comentado - ejecutar manualmente después)
-- ============================================================================
-- Una vez que hayas verificado que las tablas se crearon correctamente,
-- ejecuta estos INSERT para migrar datos desde roel_carrito:

/*
-- Migrar CARTS
INSERT INTO carrito_carts (id, id_cliente, status, created_at, updated_at)
SELECT c.id, cl.id_cliente, c.status, c.created_at, c.updated_at
FROM roel_carrito.carts c
JOIN roel_carrito.customers cu ON cu.id = c.customer_id
JOIN roel.clientes cl ON cl.rut = cu.rut
ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = VALUES(updated_at);

-- Migrar CART_ITEMS
INSERT INTO carrito_cart_items (id, cart_id, id_variedad, referencia, nombre, imagen_url, unit_price_clp, qty, created_at, updated_at)
SELECT * FROM roel_carrito.cart_items
ON DUPLICATE KEY UPDATE qty = VALUES(qty), updated_at = VALUES(updated_at);

-- Migrar ORDERS
INSERT INTO carrito_orders (id, order_code, id_cliente, customer_rut, customer_nombre, customer_telefono, customer_region, customer_comuna, customer_email, currency, subtotal_clp, shipping_code, shipping_label, shipping_cost_clp, total_clp, notes, status, created_at, updated_at)
SELECT o.id, o.order_code, cl.id_cliente, o.customer_rut, o.customer_nombre, o.customer_telefono, o.customer_region, o.customer_comuna, o.customer_email, o.currency, o.subtotal_clp, o.shipping_code, o.shipping_label, o.shipping_cost_clp, o.total_clp, o.notes, o.status, o.created_at, o.updated_at
FROM roel_carrito.orders o
JOIN roel.clientes cl ON cl.rut = o.customer_rut;

-- Migrar ORDER_ITEMS
INSERT INTO carrito_order_items (id, order_id, id_variedad, referencia, nombre, imagen_url, unit_price_clp, qty, line_total_clp, created_at)
SELECT * FROM roel_carrito.order_items
ON DUPLICATE KEY UPDATE qty = VALUES(qty);

-- Migrar BACKOFFICE_ADMINS
INSERT INTO backoffice_admins (id, email, pass_hash, name, role, is_active, last_login_at, created_at)
SELECT * FROM roel_carrito.backoffice_admins
ON DUPLICATE KEY UPDATE last_login_at = VALUES(last_login_at);

-- Migrar BACKOFFICE_AUDIT
INSERT INTO backoffice_audit (id, admin_id, action, meta, ip, created_at)
SELECT * FROM roel_carrito.backoffice_audit
ON DUPLICATE KEY UPDATE meta = VALUES(meta);

-- Migrar PRODUCTION_REQUESTS
INSERT INTO carrito_production_requests (id, request_code, id_cliente, status, total_units, total_amount_clp, notes, created_at, updated_at)
SELECT pr.id, pr.request_code, cl.id_cliente, pr.status, pr.total_units, pr.total_amount_clp, pr.notes, pr.created_at, pr.updated_at
FROM roel_carrito.production_requests pr
JOIN roel.clientes cl ON cl.id_cliente = pr.customer_id;

-- Migrar PRODUCTION_REQUEST_ITEMS
INSERT INTO carrito_production_request_items (id, request_id, product_id, product_name, qty, unit_price_clp, line_total_clp, created_at)
SELECT * FROM roel_carrito.production_request_items
ON DUPLICATE KEY UPDATE qty = VALUES(qty);
*/

-- ============================================================================
-- FIN DE SCRIPT
-- ============================================================================
