-- catalogo_detalle/sql/04_migrate_orders_to_v2.sql
-- Migración desde esquema legacy (orders.user_id, order_items.product_ref)
-- al esquema v2 (orders.order_code, order_items.referencia, etc.)
--
-- Ejecutar en la BD del carrito (ej: roeluser1_carrito).
--
-- Estrategia: conservar historial renombrando tablas antiguas,
-- luego crear tablas nuevas usando 03_orders.sql.

START TRANSACTION;

-- Si tienes datos importantes en orders/order_items, se conservan aquí:
RENAME TABLE
  orders TO orders_legacy,
  order_items TO order_items_legacy;

-- Crear tablas v2
-- (mismo contenido que catalogo_detalle/sql/03_orders.sql)

CREATE TABLE IF NOT EXISTS orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_code VARCHAR(32) NOT NULL,
  customer_id BIGINT UNSIGNED NOT NULL,

  customer_rut VARCHAR(16) NOT NULL,
  customer_nombre VARCHAR(120) NOT NULL,
  customer_telefono VARCHAR(32) NOT NULL,
  customer_region VARCHAR(64) NOT NULL,
  customer_comuna VARCHAR(64) NOT NULL,
  customer_email VARCHAR(190) NOT NULL,

  currency CHAR(3) NOT NULL DEFAULT 'CLP',
  subtotal_clp INT UNSIGNED NOT NULL DEFAULT 0,
  shipping_code VARCHAR(32) NOT NULL DEFAULT 'retiro',
  shipping_label VARCHAR(80) NOT NULL DEFAULT 'Retiro en vivero',
  shipping_cost_clp INT UNSIGNED NOT NULL DEFAULT 0,
  total_clp INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT NULL,

  status VARCHAR(24) NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_order_code (order_code),
  KEY idx_orders_customer (customer_id),
  KEY idx_orders_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  id_variedad BIGINT UNSIGNED NULL,
  referencia VARCHAR(64) NOT NULL,
  nombre VARCHAR(190) NOT NULL,
  imagen_url VARCHAR(512) NULL,
  unit_price_clp INT UNSIGNED NOT NULL,
  qty INT UNSIGNED NOT NULL,
  line_total_clp INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_order_items_order (order_id),
  CONSTRAINT fk_order_items_order
    FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
