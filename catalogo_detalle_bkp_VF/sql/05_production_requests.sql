-- Solicitudes de producción (pedido productivo mayorista)
-- Se crean automáticamente por la API si no existen.

CREATE TABLE IF NOT EXISTS production_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,
  request_code VARCHAR(40) NOT NULL,
  total_units INT NOT NULL,
  packing_cost_clp INT NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_customer (customer_id),
  UNIQUE KEY uq_code (request_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS production_request_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  request_id INT NOT NULL,
  variety_id INT NOT NULL,
  variety_name VARCHAR(255) NOT NULL,
  unit_price_clp INT NOT NULL,
  qty INT NOT NULL,
  FOREIGN KEY (request_id) REFERENCES production_requests(id) ON DELETE CASCADE,
  KEY idx_req (request_id),
  KEY idx_var (variety_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
