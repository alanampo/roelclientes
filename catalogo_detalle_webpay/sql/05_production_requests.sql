-- Solicitudes de producción (pedido productivo mayorista)
-- Se crean automáticamente por la API si no existen.
-- NOTA: Las tablas usan prefijo "carrito_" según config/cart_db.php

CREATE TABLE IF NOT EXISTS carrito_production_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_cliente INT NOT NULL,
  request_code VARCHAR(40) NOT NULL,
  total_units INT NOT NULL,
  total_amount_clp INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cliente (id_cliente),
  UNIQUE KEY uq_code (request_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS carrito_production_request_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  product_id VARCHAR(64) NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  qty INT NOT NULL,
  unit_price_clp INT NOT NULL DEFAULT 0,
  line_total_clp INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_carrito_pri_req FOREIGN KEY (request_id) REFERENCES carrito_production_requests(id) ON DELETE CASCADE,
  KEY idx_req (request_id),
  KEY idx_prod (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
